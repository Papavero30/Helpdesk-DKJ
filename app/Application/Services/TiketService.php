<?php

namespace App\Application\Services;

use App\Models\GrupTiket;
use App\Models\Kategori;
use App\Models\Lampiran;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class TiketService
{
    public function __construct(
        private ActivityLogService $logService,
        private NotificationService $notificationService,
        private SlaPauseService $slaPauseService,
    ) {}

    public function buatTiket(array $data, ?UploadedFile $foto = null): Tiket
    {
        if ($this->cekDuplikat($data)) {
            throw new \DomainException('You have already submitted the same request. Please wait until the previous one is resolved.');
        }

        $fotoPath = null;
        if ($foto) {
            $fotoPath = $foto->store('bukti-tiket', 'public');
        }

        $kategori = Kategori::findOrFail($data['id_kategori']);
        $statusOpen = StatusTiketModel::findByName('Open');

        $tiket = Tiket::create([
            'id_pengguna' => $data['id_pengguna'],
            'id_lokasi' => $data['id_lokasi'],
            'id_kategori' => $kategori->id,
            'deskripsi' => $data['deskripsi'],
            'foto_bukti' => $fotoPath,
            'id_status_tiket' => $statusOpen->id,
            'berulang' => false,
        ]);

        $repetitiveLink = $this->cocokkanGrupTiket($tiket);

        $keterangan = "New ticket created, category: {$kategori->nama_kategori}";
        if ($repetitiveLink) {
            $keterangan .= ", automatically grouped as occurrence #{$repetitiveLink->jumlah}";
        }

        // Optional `actor_id` for the "admin creates on behalf" flow.
        // Falls back to the requester (default karyawan flow). The activity log records the actor
        // so audits can distinguish "user filed for themselves" from "admin filed for user".
        $actorId = $data['actor_id'] ?? $data['id_pengguna'] ?? null;
        $onBehalf = isset($data['actor_id']) && (int) $data['actor_id'] !== (int) ($data['id_pengguna'] ?? 0);
        if ($onBehalf) {
            $keterangan .= ', created by admin on behalf of the requester';
        }

        $this->logService->catat(
            $tiket,
            'ticket_created',
            userId: $actorId,
            statusBaru: $statusOpen->nama_status,
            keterangan: $keterangan,
        );

        $this->notificationService->ticketCreated($tiket);
        $this->notificationService->ticketCreatedForUser($tiket);
        Cache::forget("karyawan-live-{$tiket->id_pengguna}");
        Cache::forget("karyawan-dashboard-{$tiket->id_pengguna}");  // legacy key — defensive cleanup

        return $tiket;
    }

    public function cekDuplikat(array $data): bool
    {
        $closeId = StatusTiketModel::findByName('Close')?->id;

        return Tiket::where('id_pengguna', $data['id_pengguna'])
            ->where('id_kategori', $data['id_kategori'])
            ->where('id_lokasi', $data['id_lokasi'])
            ->where('deskripsi', $data['deskripsi'])
            ->whereNotIn('id_status_tiket', array_filter([$closeId]))
            ->exists();
    }

    public function assignAdmin(Tiket $tiket, int $adminId): void
    {
        $statusOpen = StatusTiketModel::findByName('Open');
        $statusInProgress = StatusTiketModel::findByName('In Progress');

        $kategori = $tiket->kategori;
        $targetPenyelesaian = $kategori ? now()->addHours($kategori->batas_jam_sla) : null;

        $tiket->update([
            'id_penanggung_jawab' => $adminId,
            'id_status_tiket' => $statusInProgress->id,
            'target_penyelesaian' => $targetPenyelesaian,
        ]);

        $this->logService->catat(
            $tiket,
            'ticket_assigned',
            userId: $adminId,
            statusLama: $statusOpen->nama_status,
            statusBaru: $statusInProgress->nama_status,
            keterangan: 'Admin took the ticket',
        );

        $tiket->load('assignedAdmin');
        $this->notificationService->ticketAssigned($tiket, $tiket->assignedAdmin);
    }

    /**
     * Reassign an already-assigned ticket to a different admin in the SAME plant.
     * Unlike assignAdmin(), this PRESERVES the SLA deadline and status — only the PIC changes.
     */
    public function reassignAdmin(Tiket $tiket, int $newAdminId, ?int $actorId = null): void
    {
        if ($tiket->id_penanggung_jawab === null) {
            throw new \DomainException('Only assigned tickets can be reassigned.');
        }
        if ($tiket->statusTiket?->nama_status === 'Close') {
            throw new \DomainException('Cannot reassign a closed ticket.');
        }
        if ($newAdminId === $tiket->id_penanggung_jawab) {
            throw new \DomainException('Ticket is already assigned to that admin.');
        }

        $newAdmin = User::with('karyawan')->find($newAdminId);
        if (! $newAdmin || $newAdmin->peran !== 'admin' || $newAdmin->status_akun !== 'active') {
            throw new \DomainException('Target is not an active admin.');
        }
        // Cross-plant reassignment is allowed (consistent with cross-plant assign):
        // admins can see/work tickets from any plant, so a manager may hand a ticket
        // to any active admin regardless of the ticket's plant.

        $oldAdmin = User::with('karyawan')->find($tiket->id_penanggung_jawab);
        $oldName = $oldAdmin?->karyawan?->nama ?? '—';
        $newName = $newAdmin->karyawan?->nama ?? '—';

        $tiket->update(['id_penanggung_jawab' => $newAdminId]);

        $this->logService->catat(
            $tiket,
            'reassigned',
            userId: $actorId,
            keterangan: "Reassigned from {$oldName} to {$newName} by manager",
        );

        $tiket->load('assignedAdmin');
        $this->notificationService->ticketAssigned($tiket, $newAdmin);
        $this->notificationService->ticketAssignedToAdmin($tiket, $newAdmin);
        if ($oldAdmin) {
            $this->notificationService->ticketReassignedAway($tiket, $oldAdmin, $newName);
        }
    }

    public function assignAsManager(Tiket $tiket, int $adminId, int $managerId): void
    {
        if ($tiket->id_penanggung_jawab !== null) {
            throw new \DomainException('Ticket is already assigned.');
        }

        $admin = User::with('karyawan')->find($adminId);
        if (! $admin || $admin->peran !== 'admin' || $admin->status_akun !== 'active') {
            throw new \DomainException('Target is not an active admin.');
        }
        // Cross-plant assignment is allowed: admins can see/work tickets from any
        // plant (cross-plant admin policy), so a manager may hand an unassigned
        // ticket to any active admin — useful when the ticket's plant has none.

        $statusInProgress = StatusTiketModel::findByName('In Progress');
        $kategori = $tiket->kategori;
        $targetPenyelesaian = $kategori ? now()->addHours($kategori->batas_jam_sla) : null;

        $tiket->update([
            'id_penanggung_jawab' => $adminId,
            'id_status_tiket' => $statusInProgress->id,
            'target_penyelesaian' => $targetPenyelesaian,
        ]);

        $adminName = $admin->karyawan?->nama ?? '—';
        $this->logService->catat(
            $tiket,
            'ticket_assigned',
            userId: $managerId,
            statusBaru: $statusInProgress->nama_status,
            keterangan: "Assigned to {$adminName} by manager",
        );

        $tiket->load('assignedAdmin');
        $this->notificationService->ticketAssigned($tiket, $admin);
        $this->notificationService->ticketAssignedToAdmin($tiket, $admin);
    }

    public function markAsResolved(Tiket $tiket, ?int $adminId = null): void
    {
        $statusInProgress = StatusTiketModel::findByName('In Progress');

        if ($tiket->id_status_tiket !== $statusInProgress->id) {
            throw new \DomainException('Only In Progress tickets can be marked as resolved.');
        }

        // Gate 1: repetitive status must be final before resolving — protects SLA & admin performance stats
        if ($tiket->repetitive_review_state !== 'none') {
            throw new \DomainException(
                'Cannot mark as resolved while repetitive status is under negotiation. '.
                'Please finalize the repetitive decision first.'
            );
        }

        $tiket->update([
            'siap_konfirmasi' => true,
            'siap_konfirmasi_at' => now(),
        ]);

        $this->logService->catat(
            $tiket,
            'status_changed',
            userId: $adminId,
            statusBaru: 'In Progress (Awaiting Confirmation)',
            keterangan: 'Admin marked ticket as resolved, awaiting employee confirmation',
        );

        $this->notificationService->ticketReadyForConfirmation($tiket);
    }

    /**
     * Admin manual validation of repetitive flag.
     * ON  → join existing group (matching user+kategori+lokasi with berulang=true) or create new group with another matching ticket.
     * OFF → remove from group, delete group if becomes single-member.
     */
    public function toggleRepetitive(Tiket $tiket, bool $isRepetitive, ?int $adminId = null, ?string $reason = null): void
    {
        if ($isRepetitive) {
            $this->markAsRepetitive($tiket, $adminId);
        } else {
            $this->unmarkRepetitive($tiket, $adminId, $reason);
        }
    }

    public function requestRepetitiveOff(Tiket $tiket, int $adminId, string $note): void
    {
        if (! $tiket->berulang) {
            throw new \DomainException('Cannot request OFF on a ticket that is not currently repetitive.');
        }
        // Defense-in-depth: mirror Gate 2 + Gate 3 from Livewire layer
        if ($tiket->siap_konfirmasi) {
            throw new \DomainException('Cannot request repetitive change while awaiting user confirmation.');
        }
        if ($tiket->statusTiket?->nama_status === 'Close') {
            throw new \DomainException('Cannot request repetitive change on a closed ticket.');
        }

        $tiket->update([
            'repetitive_review_state' => 'admin_requested_off',
            'repetitive_review_admin_note' => $note,
            'repetitive_review_admin_at' => now(),
            'repetitive_review_user_note' => null,
            'repetitive_review_user_at' => null,
        ]);

        $this->logService->catat(
            $tiket,
            'repetitive_off_requested',
            userId: $adminId,
            statusLama: 'Repetitive',
            statusBaru: 'Pending Review',
            keterangan: $note,
        );

        $this->notificationService->repetitiveOffRequested($tiket);
    }

    public function acceptRepetitiveOff(Tiket $tiket, int $userId): void
    {
        if ($tiket->repetitive_review_state !== 'admin_requested_off') {
            throw new \DomainException('No pending repetitive OFF request to accept.');
        }

        // Pass writeLog=false so this method writes only the canonical "repetitive_off_accepted" entry below
        $this->unmarkRepetitive($tiket, null, null, writeLog: false);

        $tiket->update([
            'repetitive_review_state' => 'none',
            'repetitive_review_admin_note' => null,
            'repetitive_review_user_note' => null,
            'repetitive_review_admin_at' => null,
            'repetitive_review_user_at' => null,
        ]);

        $this->logService->catat(
            $tiket,
            'repetitive_off_accepted',
            userId: $userId,
            statusLama: 'Repetitive',
            statusBaru: 'Not Repetitive',
            keterangan: "User accepted admin's request to remove from repetitive group",
        );

        $this->notificationService->repetitiveOffAccepted($tiket);
    }

    public function refuseRepetitiveOff(Tiket $tiket, int $userId, string $note): void
    {
        if ($tiket->repetitive_review_state !== 'admin_requested_off') {
            throw new \DomainException('No pending repetitive OFF request to refuse.');
        }

        $tiket->update([
            'repetitive_review_state' => 'user_refused',
            'repetitive_review_user_note' => $note,
            'repetitive_review_user_at' => now(),
        ]);

        $this->logService->catat(
            $tiket,
            'repetitive_off_refused',
            userId: $userId,
            statusLama: 'Repetitive',
            statusBaru: 'Repetitive',
            keterangan: $note,
        );

        $this->notificationService->repetitiveOffRefused($tiket);
    }

    public function acceptUserRefusal(Tiket $tiket, int $adminId): void
    {
        if ($tiket->repetitive_review_state !== 'user_refused') {
            throw new \DomainException('No pending user refusal to accept.');
        }

        $tiket->update([
            'repetitive_review_state' => 'none',
            'repetitive_review_admin_note' => null,
            'repetitive_review_user_note' => null,
            'repetitive_review_admin_at' => null,
            'repetitive_review_user_at' => null,
        ]);

        $this->logService->catat(
            $tiket,
            'repetitive_off_cancelled',
            userId: $adminId,
            statusLama: 'Repetitive',
            statusBaru: 'Repetitive',
            keterangan: "Admin accepted user's refusal, ticket remains repetitive",
        );

        $this->notificationService->repetitiveOffCancelled($tiket);
    }

    private function markAsRepetitive(Tiket $tiket, ?int $adminId): void
    {
        [$start, $end] = $this->monthRange($tiket->created_at);

        // Find existing group with same key + same calendar month as the ticket
        $matchingGroup = GrupTiket::query()
            ->where('user_id', $tiket->id_pengguna)
            ->where('id_kategori', $tiket->id_kategori)
            ->where('id_lokasi', $tiket->id_lokasi)
            ->whereBetween('created_at', [$start, $end])
            ->first();

        if ($matchingGroup) {
            // Skip if already in this group
            if ($tiket->grup_tiket_id === $matchingGroup->id && $tiket->berulang) {
                return;
            }

            $tiket->update([
                'berulang' => true,
                'grup_tiket_id' => $matchingGroup->id,
            ]);

            $matchingGroup->update([
                'last_ticket' => $tiket->id,
                'jumlah' => Tiket::where('grup_tiket_id', $matchingGroup->id)->where('berulang', true)->count(),
            ]);

            $this->logService->catat(
                $tiket,
                'status_changed',
                userId: $adminId,
                keterangan: "Admin validated as repetitive, joined existing group #{$matchingGroup->id}",
            );

            return;
        }

        // No group exists yet. Look for another ticket with same key + same month + berulang=true.
        $sibling = Tiket::query()
            ->where('id', '!=', $tiket->id)
            ->where('id_pengguna', $tiket->id_pengguna)
            ->where('id_kategori', $tiket->id_kategori)
            ->where('id_lokasi', $tiket->id_lokasi)
            ->where('berulang', true)
            ->whereNull('grup_tiket_id')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->first();

        if ($sibling) {
            $grup = GrupTiket::create([
                'user_id' => $tiket->id_pengguna,
                'last_ticket' => $tiket->id,
                'id_kategori' => $tiket->id_kategori,
                'id_lokasi' => $tiket->id_lokasi,
                'id_penanggung_jawab' => $tiket->id_penanggung_jawab,
                'jumlah' => 2,
            ]);

            $sibling->update(['grup_tiket_id' => $grup->id]);
            $tiket->update(['berulang' => true, 'grup_tiket_id' => $grup->id]);

            $this->logService->catat(
                $tiket,
                'status_changed',
                userId: $adminId,
                keterangan: "Admin validated as repetitive, new group #{$grup->id} created with ticket #{$sibling->id}",
            );

            return;
        }

        // No matching sibling — just flag this ticket. Group will form when next ticket arrives.
        $tiket->update(['berulang' => true]);

        $this->logService->catat(
            $tiket,
            'status_changed',
            userId: $adminId,
            keterangan: 'Admin validated as repetitive, no matching ticket yet, flagged solo',
        );
    }

    private function unmarkRepetitive(Tiket $tiket, ?int $adminId, ?string $reason = null, bool $writeLog = true): void
    {
        $previousGroupId = $tiket->grup_tiket_id;

        $tiket->update([
            'berulang' => false,
            'grup_tiket_id' => null,
        ]);

        if ($previousGroupId) {
            $grup = GrupTiket::find($previousGroupId);
            if ($grup) {
                $remainingCount = Tiket::where('grup_tiket_id', $grup->id)->count();

                if ($remainingCount <= 1) {
                    // Single member left — dissolve the group
                    $lastMember = Tiket::where('grup_tiket_id', $grup->id)->first();
                    if ($lastMember) {
                        $lastMember->update(['grup_tiket_id' => null, 'berulang' => false]);
                    }
                    $grup->delete();
                } else {
                    // Update count + last_ticket
                    $latestMember = Tiket::where('grup_tiket_id', $grup->id)->orderByDesc('id')->first();
                    $grup->update([
                        'jumlah' => $remainingCount,
                        'last_ticket' => $latestMember?->id,
                    ]);
                }
            }
        }

        // Skip duplicate log when caller (e.g. acceptRepetitiveOff) already writes a richer entry
        if (! $writeLog) {
            return;
        }

        $keterangan = $reason
            ?: 'Admin marked ticket as NOT repetitive, removed from group';

        $this->logService->catat(
            $tiket,
            'status_changed',
            userId: $adminId,
            keterangan: $keterangan,
        );
    }

    /**
     * Auto-accept stale repetitive OFF request. Called by scheduler when
     * `state=admin_requested_off` and `repetitive_review_admin_at <= now() - 4h`.
     * Behavior identik dengan manual acceptRepetitiveOff tapi tanpa user actor.
     */
    public function autoAcceptStaleRequest(Tiket $tiket): void
    {
        // Race-safe: state mungkin sudah berubah sejak query
        if ($tiket->repetitive_review_state !== 'admin_requested_off') {
            return;
        }

        $this->unmarkRepetitive($tiket, null, null, writeLog: false);

        $tiket->update([
            'repetitive_review_state' => 'none',
            'repetitive_review_admin_note' => null,
            'repetitive_review_user_note' => null,
            'repetitive_review_admin_at' => null,
            'repetitive_review_user_at' => null,
        ]);

        $this->logService->catat(
            $tiket,
            'repetitive_off_auto_accepted',
            userId: null, // sistem
            statusLama: 'Repetitive',
            statusBaru: 'Not Repetitive',
            keterangan: 'System automatically accepted the change request',
        );

        $this->notificationService->repetitiveOffAutoAccepted($tiket);
    }

    public function tutupTiket(Tiket $tiket): void
    {
        $now = now();

        // Finalize any open pause so its time is credited before scoring the outcome.
        $openPause = $tiket->slaPauseRequests()->whereIn('state', ['active', 'pending'])->first();
        if ($openPause) {
            if ($openPause->state === 'active') {
                $this->slaPauseService->resume($openPause, 'manual_admin', null);
            } else { // pending -> cancel it; the request never took effect
                $openPause->update(['state' => 'cancelled', 'decided_note' => 'Ticket closed before approval', 'approved_at' => now()]);
            }
            $tiket->refresh();
        }

        $deadline = $tiket->effectiveDeadline();
        $outcome = null;

        if ($deadline) {
            $hoursDelta = $now->diffInHours($deadline, false);
            // "Ahead" scales with the category's SLA window: closed with more than
            // 25% of the allotted time still remaining. A fixed 6-hour threshold
            // made "ahead" unreachable for SLAs <= 6h (e.g. a 1-hour category) and
            // trivially easy for long ones. 25% reproduces the old 6h for a 24h SLA.
            $slaWindow = (float) ($tiket->kategori?->batas_jam_sla ?? 0);
            $aheadThreshold = $slaWindow > 0 ? $slaWindow * 0.25 : PHP_INT_MAX;
            if ($hoursDelta < 0) {
                $outcome = 'overtime';
            } elseif ($hoursDelta > $aheadThreshold) {
                $outcome = 'ahead';
            } else {
                $outcome = 'on_time';
            }
        }

        $closeStatus = StatusTiketModel::findByName('Close');

        $tiket->update([
            'id_status_tiket' => $closeStatus->id,
            'ditutup_pada' => $now,
            'sla_outcome' => $outcome,
            'siap_konfirmasi' => false,
            'waktu_selesai' => $now,
        ]);

        $this->logService->catat(
            $tiket,
            'status_changed',
            statusBaru: 'Close',
            keterangan: 'Ticket closed after employee confirmation',
        );

        $this->notificationService->ticketClosed($tiket);
    }

    public function simpanLampiran(Tiket $tiket, array $files, ?int $komentarId = null): void
    {
        foreach ($files as $file) {
            $path = $file->store('tiket-lampiran', 'public');
            Lampiran::create([
                'tiket_id' => $tiket->id,
                'komentar_id' => $komentarId,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'ukuran' => $file->getSize(),
                'nama_asli' => $file->getClientOriginalName(),
            ]);
        }
    }

    public function daftarTiketByLokasi(int $lokasiId)
    {
        return Tiket::with([
            'user.karyawan.divisi',
            'user.karyawan.lokasi',
            'lokasi',
            'komentar.user.karyawan',
            'assignedAdmin.karyawan',
            'kategori',
            'statusTiket',
            'grupTiketAsLatest',
        ])
            ->where('id_lokasi', $lokasiId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function daftarTiketUser(int $userId)
    {
        return Tiket::with([
            'lokasi',
            'komentar.user.karyawan',
            'penilaian',
            'assignedAdmin.karyawan',
            'kategori',
            'statusTiket',
            'grupTiketAsLatest',
        ])
            ->where('id_pengguna', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function daftarTiketKaryawan(int $karyawanId)
    {
        return Tiket::query()
            ->whereHas('user', fn ($query) => $query->where('id_karyawan', $karyawanId))
            ->with([
                'lokasi',
                'komentar.user.karyawan',
                'penilaian',
                'assignedAdmin.karyawan',
                'kategori',
                'statusTiket',
                'grupTiketAsLatest',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /** Calendar-month range from a reference Carbon — used by all repetitive matching. */
    private function monthRange(Carbon $ref): array
    {
        return [
            $ref->copy()->startOfMonth(),
            $ref->copy()->endOfMonth(),
        ];
    }

    private function cocokkanGrupTiket(Tiket $tiket): ?GrupTiket
    {
        $statusClose = StatusTiketModel::findByName('Close');
        if (! $statusClose) {
            return null;
        }

        [$start, $end] = $this->monthRange($tiket->created_at);

        $matched = Tiket::query()
            ->where('id_pengguna', $tiket->id_pengguna)
            ->where('id_kategori', $tiket->id_kategori)
            ->where('id_lokasi', $tiket->id_lokasi)
            ->where('id_status_tiket', $statusClose->id)
            ->where('id', '!=', $tiket->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->get();

        if ($matched->isEmpty()) {
            $tiket->update([
                'berulang' => false,
                'grup_tiket_id' => null,
            ]);

            return null;
        }

        $tiket->update([
            'berulang' => true,
        ]);

        $ticketWithGroup = $matched->firstWhere('grup_tiket_id', '!=', null);
        if ($ticketWithGroup) {
            $grup = GrupTiket::find($ticketWithGroup->grup_tiket_id);

            if ($grup) {
                $grup->update([
                    'last_ticket' => $tiket->id,
                    'jumlah' => $grup->jumlah + 1,
                ]);

                $tiket->update([
                    'grup_tiket_id' => $grup->id,
                ]);

                return $grup->fresh();
            }
        }

        $oldestResolved = $matched->last();

        $grup = GrupTiket::create([
            'user_id' => $oldestResolved->id_pengguna,
            'last_ticket' => $tiket->id,
            'id_kategori' => $oldestResolved->id_kategori,
            'id_lokasi' => $oldestResolved->id_lokasi,
            'id_penanggung_jawab' => $oldestResolved->id_penanggung_jawab,
            'jumlah' => 2,
        ]);

        $oldestResolved->update([
            'grup_tiket_id' => $grup->id,
        ]);

        $tiket->update([
            'grup_tiket_id' => $grup->id,
        ]);

        return $grup;
    }
}

<?php

namespace App\Livewire;

use App\Application\Services\FeedbackService;
use App\Application\Services\KomentarService;
use App\Application\Services\PenilaianService;
use App\Application\Services\SlaPauseService;
use App\Application\Services\TiketService;
use App\Models\ActivityLog;
use App\Models\SlaPauseRequest;
use App\Models\Tiket;
use App\Models\User;
use App\Support\BreadcrumbStack;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Ticket Detail', 'description' => 'View ticket details and updates'])]
class TicketDetail extends Component
{
    use WithFileUploads;

    public Tiket $tiket;

    public string $isiKomentar = '';

    public array $komentarLampiran = [];

    public int $bintang = 0;

    public bool $tampilRating = false;

    public string $komentarRating = '';

    public bool $showGrupModal = false;

    public array $grupFilterStatuses = [];

    public string $grupSortColumn = 'id';

    public string $grupSortDir = 'desc';

    public ?string $grupDateMode = null;

    public ?string $grupDateExact = null;

    public ?string $grupDateStart = null;

    public ?string $grupDateEnd = null;

    public bool $showIssueReasonModal = false;

    public string $issueReason = '';

    public bool $showFeedbackConfirm = false;

    public ?bool $feedbackResolvedDecision = null;

    public bool $showResolveConfirm = false;

    public bool $showRatingConfirm = false;

    // Toggle OFF is_repetitive — admin request side
    public bool $showRepetitiveOffConfirm = false;

    public string $repetitiveOffReason = '';

    // Toggle OFF is_repetitive — user refuse side
    public bool $showRefuseRepetitiveModal = false;

    public string $refuseRepetitiveReason = '';

    public string $pauseReason = '';

    public ?string $pauseEta = null;

    public $pauseAttachment = null;

    public string $pauseRejectNote = '';

    public ?string $pauseExtendEta = null;

    public bool $showRejectPauseConfirm = false;

    public function mount(Tiket $tiket): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isKaryawan()) {
            if ($tiket->id_pengguna !== $user->id) {
                abort(403);
            }
        }
        // Admin: cross-plant access allowed. Action-level policy (chat/toggle/resolve)
        // remains protected by PIC ownership check (id_penanggung_jawab === Auth::id()).

        $this->tiket = $tiket;
        $this->tiket->load(['statusTiket', 'penilaian']);
        $this->tampilRating = $this->tiket->statusTiket?->nama_status === 'Close' && ! $this->tiket->penilaian;

        // Seed a parent crumb when the ticket is opened directly (e.g. from the
        // dashboard, which resets the stack) so the breadcrumb trail still renders
        // for every role, matching the karyawan "My Tickets > #TKT" experience.
        if (empty(BreadcrumbStack::get())) {
            [$parentLabel, $parentUrl] = match (true) {
                $user->isAdmin() => ['All Tickets', '/admin/all-tickets'],
                $user->isManager() => ['Oversight', '/manager/oversight'],
                default => ['My Tickets', '/my-tickets'],
            };
            BreadcrumbStack::push($parentLabel, $parentUrl);
        }

        $label = '#TKT'.str_pad($tiket->id, 2, '0', STR_PAD_LEFT);
        BreadcrumbStack::push($label, "/ticket/{$tiket->id}");

        $marked = Auth::user()->unreadNotifications()
            ->whereJsonContains('data->tiket_id', $tiket->id)
            ->update(['read_at' => now()]);

        if ($marked > 0) {
            Cache::forget('unread-notif-'.Auth::id());
            $this->dispatch('notifications-updated');
        }
    }

    public function kirimKomentar(KomentarService $komentarService, TiketService $tiketService): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        // Only allowed: ticket requester (karyawan owner) or assigned PIC admin.
        // Admin lain di plant yang sama hanya bisa view, tidak bisa chat.
        $isRequester = $user->isKaryawan() && $this->tiket->id_pengguna === $user->id;
        $isAssignedAdmin = $user->isAdmin() && $this->tiket->id_penanggung_jawab === $user->id;

        if (! $isRequester && ! $isAssignedAdmin) {
            $this->dispatch('notify', type: 'warning', content: 'Only the assigned admin or the ticket requester can send comments.');

            return;
        }

        $this->validate([
            'isiKomentar' => 'nullable|max:100',
            'komentarLampiran' => 'array|max:5',
            'komentarLampiran.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:2048',
        ]);

        if (empty(trim($this->isiKomentar)) && empty($this->komentarLampiran)) {
            $this->dispatch('notify', type: 'error', content: 'Please write a comment or attach a file.');

            return;
        }

        $isi = trim($this->isiKomentar) ?: '(file attached)';
        $komentar = $komentarService->simpanKomentar($this->tiket, Auth::id(), $isi);

        if (! empty($this->komentarLampiran)) {
            $tiketService->simpanLampiran($this->tiket, $this->komentarLampiran, $komentar->id);
        }

        $this->isiKomentar = '';
        $this->komentarLampiran = [];
        $this->tiket->refresh();
        $this->tiket->load(['komentar.user.karyawan', 'komentar.lampiran']);
    }

    public function removeKomentarLampiran(int $idx): void
    {
        array_splice($this->komentarLampiran, $idx, 1);
    }

    public function takeTicket(TiketService $tiketService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }

        // Prevent taking ticket that is already assigned
        if ($this->tiket->id_penanggung_jawab) {
            $this->dispatch('notify', type: 'warning', content: 'This ticket has already been taken by another admin.');

            return;
        }

        $tiketService->assignAdmin($this->tiket, Auth::id());
        $this->tiket->refresh();
        $this->dispatch('notify', type: 'success', content: 'Ticket assigned to you.');
    }

    public function openResolveConfirm(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }
        $this->showResolveConfirm = true;
    }

    public function toggleRepetitive(TiketService $tiketService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }

        // Only allow toggle if admin owns this ticket
        if ($this->tiket->id_penanggung_jawab !== Auth::id()) {
            $this->dispatch('notify', type: 'warning', content: 'You must take this ticket before validating repetitive status.');

            return;
        }

        // Gate 2: lock saat awaiting user confirmation (mencegah race dengan Feedback banner)
        if ($this->tiket->siap_konfirmasi) {
            $this->dispatch('notify', type: 'warning',
                content: 'Cannot change repetitive status while awaiting user confirmation.');

            return;
        }

        // Gate 3: lock saat ticket sudah Close (statistik admin sudah ter-snapshot)
        if ($this->tiket->statusTiket?->nama_status === 'Close') {
            $this->dispatch('notify', type: 'warning',
                content: 'Cannot change repetitive status of a closed ticket.');

            return;
        }

        $newValue = ! (bool) $this->tiket->berulang;

        // Toggle OFF requires double verification with reason
        if (! $newValue) {
            $this->repetitiveOffReason = '';
            $this->showRepetitiveOffConfirm = true;

            return;
        }

        // Toggle ON proceeds immediately
        try {
            $tiketService->toggleRepetitive($this->tiket, true, Auth::id());
            $this->tiket->refresh();
            $this->tiket->load(['grupTiket', 'grupTiketAsLatest']);
            $this->dispatch('notify', type: 'success', content: 'Ticket validated as repetitive.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', content: $e->getMessage());
        }
    }

    public function confirmRepetitiveOff(TiketService $tiketService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }
        if ($this->tiket->id_penanggung_jawab !== Auth::id()) {
            $this->dispatch('notify', type: 'warning', content: 'You must take this ticket before validating repetitive status.');

            return;
        }

        $reason = trim($this->repetitiveOffReason);
        if (mb_strlen($reason) < 10) {
            $this->dispatch('notify', type: 'error', content: 'Please provide a reason (at least 10 characters).');

            return;
        }
        if (mb_strlen($reason) > 200) {
            $this->dispatch('notify', type: 'error', content: 'Reason must not exceed 200 characters.');

            return;
        }

        try {
            $tiketService->requestRepetitiveOff($this->tiket, Auth::id(), $reason);
            $this->tiket->refresh();
            $this->showRepetitiveOffConfirm = false;
            $this->repetitiveOffReason = '';
            $this->dispatch('notify', type: 'success', content: 'Request sent to user — awaiting confirmation.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', content: $e->getMessage());
        }
    }

    public function acceptRepetitiveChange(TiketService $tiketService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isKaryawan()) {
            return;
        }
        if ($this->tiket->id_pengguna !== Auth::id()) {
            return;
        }

        try {
            $tiketService->acceptRepetitiveOff($this->tiket, Auth::id());
            $this->tiket->refresh();
            $this->tiket->load(['grupTiket', 'grupTiketAsLatest']);
            $this->dispatch('notify', type: 'success', content: 'Ticket removed from repetitive group.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', content: $e->getMessage());
        }
    }

    public function openRefuseRepetitiveModal(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isKaryawan()) {
            return;
        }
        if ($this->tiket->id_pengguna !== Auth::id()) {
            return;
        }
        $this->refuseRepetitiveReason = '';
        $this->showRefuseRepetitiveModal = true;
    }

    public function confirmRefuseRepetitive(TiketService $tiketService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isKaryawan()) {
            return;
        }
        if ($this->tiket->id_pengguna !== Auth::id()) {
            return;
        }

        $reason = trim($this->refuseRepetitiveReason);
        if (mb_strlen($reason) < 10) {
            $this->dispatch('notify', type: 'error', content: 'Please provide a reason (at least 10 characters).');

            return;
        }
        if (mb_strlen($reason) > 200) {
            $this->dispatch('notify', type: 'error', content: 'Reason must not exceed 200 characters.');

            return;
        }

        try {
            $tiketService->refuseRepetitiveOff($this->tiket, Auth::id(), $reason);
            $this->tiket->refresh();
            $this->showRefuseRepetitiveModal = false;
            $this->refuseRepetitiveReason = '';
            $this->dispatch('notify', type: 'success', content: 'Your refusal has been sent to the admin.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', content: $e->getMessage());
        }
    }

    public function confirmAcceptRefusal(TiketService $tiketService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }
        if ($this->tiket->id_penanggung_jawab !== Auth::id()) {
            return;
        }

        try {
            $tiketService->acceptUserRefusal($this->tiket, Auth::id());
            $this->tiket->refresh();
            $this->dispatch('notify', type: 'success', content: "User's refusal accepted. Ticket remains repetitive.");
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', content: $e->getMessage());
        }
    }

    /**
     * Admin Decline button on user_refused banner.
     * Shortcut yang membuka admin note modal — fungsi identik dengan toggle OFF dari footer,
     * tapi lebih ergonomis di-context banner. Setelah modal di-submit, cycle ulang ke admin_requested_off.
     */
    public function declineUserRefusal(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }
        if ($this->tiket->id_penanggung_jawab !== Auth::id()) {
            return;
        }
        if ($this->tiket->repetitive_review_state !== 'user_refused') {
            return;
        }

        $this->repetitiveOffReason = '';
        $this->showRepetitiveOffConfirm = true;
    }

    public function confirmResolve(TiketService $tiketService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }

        $this->showResolveConfirm = false;

        try {
            $tiketService->markAsResolved($this->tiket, Auth::id());
            $this->tiket->refresh();
            $this->dispatch('notify', type: 'success', content: 'Ticket marked as resolved. Awaiting employee confirmation.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'warning', content: $e->getMessage());
        }
    }

    public function openFeedbackConfirm(bool $resolved): void
    {
        $this->feedbackResolvedDecision = $resolved;
        $this->showFeedbackConfirm = true;
    }

    public function confirmFeedback(FeedbackService $feedbackService): void
    {
        if ($this->feedbackResolvedDecision === true) {
            $feedbackService->prosesFeedback($this->tiket, true);

            $this->tiket->refresh();
            $this->tiket->load(['statusTiket', 'komentar.user.karyawan', 'penilaian']);
            $this->tampilRating = true;

            $this->showFeedbackConfirm = false;
            $this->dispatch('notify', type: 'success', content: 'Resolved! Please rate the service below.');
            $this->dispatch('ticket-status-changed');

        } elseif ($this->feedbackResolvedDecision === false) {
            $this->showFeedbackConfirm = false;
            $this->openIssueReasonModal();
        }
    }

    public function openIssueReasonModal(): void
    {
        $this->issueReason = '';
        $this->showIssueReasonModal = true;
    }

    public function submitIssueReason(FeedbackService $feedbackService): void
    {
        $reason = trim($this->issueReason);
        if (mb_strlen($reason) < 10) {
            $this->dispatch('notify', type: 'error', content: 'Please describe the issue (at least 10 characters).');

            return;
        }
        if (mb_strlen($reason) > 100) {
            $this->dispatch('notify', type: 'error', content: 'Issue description must not exceed 100 characters.');

            return;
        }

        $feedbackService->prosesFeedback($this->tiket, false, $reason);

        $this->tiket->refresh();
        $this->tiket->load(['statusTiket', 'komentar.user.karyawan', 'penilaian', 'assignedAdmin.karyawan']);

        $this->showIssueReasonModal = false;
        $this->issueReason = '';

        $this->dispatch('notify', type: 'success', content: 'Issue reopened. The admin has been notified.');
        $this->dispatch('ticket-status-changed');
    }

    public function openRatingConfirm(): void
    {
        try {
            $this->validate([
                'bintang' => 'required|integer|min:1|max:5',
                'komentarRating' => 'required|min:10|max:100',
            ], [
                'bintang.required' => 'Please select 1 to 5 stars.',
                'bintang.min' => 'Please select at least 1 star.',
                'komentarRating.required' => 'Please write your feedback.',
                'komentarRating.min' => 'Feedback must be at least 10 characters.',
                'komentarRating.max' => 'Feedback must not exceed 100 characters.',
            ]);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('notify', type: 'error', content: $firstError);

            return;
        }

        if ($this->tiket->penilaian) {
            $this->dispatch('notify', type: 'warning', content: 'You have already rated this ticket.');

            return;
        }

        $this->showRatingConfirm = true;
    }

    public function confirmRating(PenilaianService $penilaianService): void
    {
        $this->showRatingConfirm = false;

        if ($this->tiket->penilaian) {
            $this->dispatch('notify', type: 'warning', content: 'You have already rated this ticket.');

            return;
        }

        $penilaianService->simpanPenilaian($this->tiket, $this->bintang, $this->komentarRating);

        $this->tiket->refresh();
        $this->tiket->load('penilaian');

        $this->tampilRating = false;
        $this->bintang = 0;
        $this->komentarRating = '';

        $this->dispatch('notify', type: 'success', content: 'Thanks for your feedback!');
        $this->dispatch('ticket-status-changed');
    }

    public function requestSlaPause(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin() || $this->tiket->id_penanggung_jawab !== $user->id) {
            abort(403);
        }
        $this->validate([
            'pauseReason' => ['required', 'string', 'max:1000'],
            'pauseEta' => ['required', 'date', 'after:now'],
            'pauseAttachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:2048'],
        ]);
        try {
            $attachmentPath = $this->pauseAttachment ? $this->pauseAttachment->store('sla-pause-attachments', 'public') : null;
            app(SlaPauseService::class)->request($this->tiket, $user->id, $this->pauseReason, Carbon::parse($this->pauseEta), $attachmentPath);
            $this->reset('pauseReason', 'pauseEta', 'pauseAttachment');
            $this->tiket->refresh();
            $this->dispatch('notify', type: 'success', content: 'Deadline pause requested.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'warning', content: $e->getMessage());
        }
    }

    public function approveSlaPause(int $reqId): void
    {
        $user = Auth::user();
        $req = SlaPauseRequest::find($reqId);
        if (! $user instanceof User || ! $req || $req->tiket_id !== $this->tiket->id || $this->tiket->id_pengguna !== $user->id) {
            abort(403);
        }
        app(SlaPauseService::class)->approve($req, $user->id);
        $this->tiket->refresh();
        $this->dispatch('notify', type: 'success', content: 'Deadline pause approved.');
    }

    public function openRejectPauseConfirm(): void
    {
        $this->reset('pauseRejectNote');
        $this->showRejectPauseConfirm = true;
    }

    public function rejectSlaPause(int $reqId): void
    {
        $user = Auth::user();
        $req = SlaPauseRequest::find($reqId);
        if (! $user instanceof User || ! $req || $req->tiket_id !== $this->tiket->id || $this->tiket->id_pengguna !== $user->id) {
            abort(403);
        }
        $this->validate(
            ['pauseRejectNote' => ['required', 'string', 'max:1000']],
            ['pauseRejectNote.required' => 'Please give a reason so the admin knows why you rejected it.'],
        );
        app(SlaPauseService::class)->reject($req, $user->id, $this->pauseRejectNote);
        $this->reset('pauseRejectNote');
        $this->showRejectPauseConfirm = false;
        $this->tiket->refresh();
        $this->dispatch('notify', type: 'success', content: 'Deadline pause rejected.');
    }

    public function resumeSlaPause(int $reqId): void
    {
        $user = Auth::user();
        $req = SlaPauseRequest::find($reqId);
        if (! $user instanceof User || ! $user->isAdmin() || ! $req || $req->tiket_id !== $this->tiket->id || $this->tiket->id_penanggung_jawab !== $user->id) {
            abort(403);
        }
        app(SlaPauseService::class)->resume($req, 'manual_admin', $user->id);
        $this->tiket->refresh();
        $this->dispatch('notify', type: 'success', content: 'Deadline resumed.');
    }

    public function extendSlaPause(int $reqId): void
    {
        $user = Auth::user();
        $req = SlaPauseRequest::find($reqId);
        if (! $user instanceof User || ! $user->isAdmin() || ! $req || $req->tiket_id !== $this->tiket->id || $this->tiket->id_penanggung_jawab !== $user->id) {
            abort(403);
        }
        $this->validate(['pauseExtendEta' => ['required', 'date', 'after:now']]);
        try {
            app(SlaPauseService::class)->extend($req, $user->id, Carbon::parse($this->pauseExtendEta));
            $this->reset('pauseExtendEta');
            $this->tiket->refresh();
            $this->dispatch('notify', type: 'success', content: 'Deadline pause extension requested.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'warning', content: $e->getMessage());
        }
    }

    public function cancelSlaPause(int $reqId): void
    {
        $user = Auth::user();
        $req = SlaPauseRequest::find($reqId);
        if (! $user instanceof User || ! $user->isAdmin() || ! $req || $req->tiket_id !== $this->tiket->id || $this->tiket->id_penanggung_jawab !== $user->id) {
            abort(403);
        }
        if ($req->state === 'pending') {
            $req->update(['state' => 'cancelled', 'decided_note' => 'Cancelled by admin', 'approved_at' => now()]);
            $this->tiket->refresh();
            $this->dispatch('notify', type: 'success', content: 'Deadline pause request cancelled.');
        }
    }

    public function sortGrupBy(string $column): void
    {
        if ($this->grupSortColumn === $column) {
            $this->grupSortDir = $this->grupSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->grupSortColumn = $column;
            $this->grupSortDir = 'asc';
        }
    }

    public function resetGrupFilters(): void
    {
        $this->grupFilterStatuses = [];
        $this->grupDateMode = null;
        $this->grupDateExact = null;
        $this->grupDateStart = null;
        $this->grupDateEnd = null;
        $this->dispatch('grup-filters-reset');
    }

    public function resetGrupSort(): void
    {
        $this->grupSortColumn = 'id';
        $this->grupSortDir = 'desc';
    }

    public function resetGrupDate(): void
    {
        $this->grupDateMode = null;
        $this->grupDateExact = null;
        $this->grupDateStart = null;
        $this->grupDateEnd = null;
    }

    protected function buildStatusSteps(): array
    {
        $statusOrder = ['Open', 'In Progress', 'Close'];
        $currentStatus = $this->tiket->statusTiket?->nama_status;
        $currentIdx = array_search($currentStatus, $statusOrder, true);
        if ($currentIdx === false) {
            $currentIdx = 0;
        }

        $statusTimes = ['Open' => $this->tiket->created_at];

        if ($this->tiket->ditutup_pada) {
            $statusTimes['Close'] = $this->tiket->ditutup_pada;
        }

        foreach (ActivityLog::where('tiket_id', $this->tiket->id)
            ->where('aksi', 'status_changed')
            ->orderBy('created_at')
            ->get() as $log
        ) {
            if ($log->status_baru && ! isset($statusTimes[$log->status_baru])) {
                $statusTimes[$log->status_baru] = $log->created_at;
            }
        }

        // Fallback: when admin takes a ticket we log it as 'ticket_assigned' (not 'status_changed').
        // Use that timestamp as the In Progress start time — also serves as the SLA clock start.
        if (! isset($statusTimes['In Progress'])) {
            $assignLog = ActivityLog::where('tiket_id', $this->tiket->id)
                ->where('aksi', 'ticket_assigned')
                ->orderBy('created_at')
                ->first();

            if ($assignLog) {
                $statusTimes['In Progress'] = $assignLog->created_at;
            }
        }

        $steps = [];
        foreach ($statusOrder as $idx => $label) {
            $isActive = $idx <= $currentIdx;
            $steps[] = [
                'label' => $label,
                'time' => $isActive ? ($statusTimes[$label] ?? null) : null,
                'active' => $isActive,
            ];
        }

        return $steps;
    }

    protected function loadGrupTikets(): Collection
    {
        if (! $this->tiket->grupTiket) {
            return collect();
        }

        $q = Tiket::query()
            ->with(['statusTiket'])
            ->where('tiket.grup_tiket_id', $this->tiket->grupTiket->id);

        if (! empty($this->grupFilterStatuses)) {
            $q->whereIn('id_status_tiket', $this->grupFilterStatuses);
        }

        if ($this->grupDateMode === 'exact' && $this->grupDateExact) {
            $q->whereDate('created_at', $this->grupDateExact);
        } elseif ($this->grupDateMode === 'range' && $this->grupDateStart && $this->grupDateEnd) {
            $q->whereBetween('created_at', [
                $this->grupDateStart.' 00:00:00',
                $this->grupDateEnd.' 23:59:59',
            ]);
        }

        match ($this->grupSortColumn) {
            'date' => $q->orderBy('tiket.created_at', $this->grupSortDir),
            'status' => $q->leftJoin('status_tiket', 'tiket.id_status_tiket', '=', 'status_tiket.id')
                ->orderBy('status_tiket.nama_status', $this->grupSortDir)
                ->select('tiket.*'),
            default => $q->orderBy('tiket.id', $this->grupSortDir),
        };

        return $q->get();
    }

    public function render()
    {
        $this->tiket->loadMissing([
            'user.karyawan.divisi',
            'user.karyawan.lokasi',
            'lokasi',
            'kategori',
            'statusTiket',
            'komentar' => fn ($q) => $q->orderBy('created_at'),
            'komentar.user.karyawan',
            'komentar.lampiran',
            'assignedAdmin.karyawan',
            'penilaian',
            'lampiran',
            'grupTiketAsLatest',
            'grupTiket',
            'slaPauseRequests',
        ]);

        if (! $this->tampilRating) {
            $this->tampilRating = $this->tiket->statusTiket?->nama_status === 'Close' && ! $this->tiket->penilaian;
        }

        $allLogs = ActivityLog::query()
            ->with('user.karyawan')
            ->where('tiket_id', $this->tiket->id)
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        $statusLogs = $allLogs->where('aksi', 'status_changed');
        $lastStatusChanged = $statusLogs->last();
        $activityLogs = $allLogs->sortByDesc('created_at')->values();

        $statusSteps = $this->buildStatusSteps();
        $grupTickets = $this->loadGrupTikets();

        $ticketLampiran = $this->tiket->lampiran->whereNull('komentar_id');

        return view('livewire.ticket-detail', [
            'statusSteps' => $statusSteps,
            'lastStatusChanged' => $lastStatusChanged,
            'activityLogs' => $activityLogs,
            'grupTickets' => $grupTickets,
            'ticketLampiran' => $ticketLampiran,
        ]);
    }
}

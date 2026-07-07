<?php

// app/Application/Services/SlaPauseService.php

namespace App\Application\Services;

use App\Models\SlaPauseRequest;
use App\Models\Tiket;
use Carbon\Carbon;

class SlaPauseService
{
    public const AUTO_APPROVE_HOURS = 24;

    public function __construct(
        private ActivityLogService $logService,
        private NotificationService $notificationService,
        private KomentarService $komentarService,
    ) {}

    public function request(Tiket $tiket, int $adminId, string $reason, Carbon $eta, ?string $attachmentPath = null): SlaPauseRequest
    {
        if ($tiket->statusTiket?->nama_status !== 'In Progress') {
            throw new \DomainException('Deadline pause can only be requested on In Progress tickets.');
        }
        if ($tiket->slaPauseRequests()->whereIn('state', ['pending', 'active'])->exists()) {
            throw new \DomainException('There is already a pending or active pause for this ticket.');
        }

        $req = $tiket->slaPauseRequests()->create([
            'requested_by' => $adminId,
            'requested_at' => now(),
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            'eta' => $eta,
            'state' => 'pending',
        ]);

        $this->logService->catat(
            $tiket,
            'sla_pause_requested',
            userId: $adminId,
            keterangan: 'Deadline pause requested, reason: '.$reason,
        );

        $this->notificationService->slaPauseRequested($req);

        return $req;
    }

    public function approve(SlaPauseRequest $req, ?int $approverId): void
    {
        if ($req->state !== 'pending') {
            return;
        }

        $tiket = $req->tiket;

        // Approving an extension while the original pause is still frozen: the old
        // span ends here and the new one starts immediately, so the freeze continues
        // without a gap and no second is ever counted twice.
        $sibling = $tiket->slaPauseRequests()
            ->where('state', 'active')
            ->where('id', '!=', $req->id)
            ->first();

        $req->update([
            'state' => 'active',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        if ($sibling) {
            $start = $tiket->sla_paused_at ?? $sibling->requested_at;
            $seconds = max(0, now()->getTimestamp() - $start->getTimestamp());

            $sibling->update([
                'state' => 'resumed',
                'resumed_at' => now(),
                'resume_kind' => 'extended',
                'paused_seconds' => $seconds,
            ]);

            $tiket->update([
                'sla_paused_at' => now(),
                'sla_paused_total_seconds' => (int) ($tiket->sla_paused_total_seconds ?? 0) + $seconds,
            ]);
        } else {
            // Retroactive: the wait counts from the moment it was requested, but never
            // earlier than the end of a span that was already counted (for example an
            // extension approved after the original pause auto resumed on its ETA).
            $start = $req->requested_at;
            $lastEnd = $tiket->slaPauseRequests()->where('state', 'resumed')->max('resumed_at');
            if ($lastEnd !== null && Carbon::parse($lastEnd)->greaterThan($start)) {
                $start = Carbon::parse($lastEnd);
            }
            $tiket->update(['sla_paused_at' => $start]);
        }

        $what = $sibling ? 'Deadline pause extension' : 'Deadline pause';
        $this->logService->catat(
            $tiket,
            $approverId === null ? 'sla_pause_auto_approved' : 'sla_pause_approved',
            userId: $approverId,
            keterangan: $approverId === null
                ? $what.' auto approved after '.self::AUTO_APPROVE_HOURS.' hours of no response'
                : $what.' approved by requester',
        );

        $this->notificationService->slaPauseApproved($req, auto: $approverId === null);
    }

    public function resume(SlaPauseRequest $req, string $kind, ?int $actorId): void
    {
        if ($req->state !== 'active') {
            return;
        }

        $tiket = $req->tiket;
        $start = $tiket->sla_paused_at ?? $req->requested_at;
        $seconds = max(0, now()->getTimestamp() - $start->getTimestamp());

        $tiket->update([
            'sla_paused_at' => null,
            'sla_paused_total_seconds' => (int) ($tiket->sla_paused_total_seconds ?? 0) + $seconds,
        ]);

        $req->update([
            'state' => 'resumed',
            'resumed_at' => now(),
            'resume_kind' => $kind,
            'paused_seconds' => $seconds,
        ]);

        $label = match ($kind) {
            'auto_eta' => 'Deadline resumed automatically at the ETA',
            'forced_manager' => 'Deadline resumed by manager',
            default => 'Deadline resumed by admin',
        };

        $this->logService->catat($tiket, 'sla_pause_resumed', userId: $actorId, keterangan: $label);

        $this->notificationService->slaPauseResumed($req);
    }

    public function extend(SlaPauseRequest $req, int $adminId, Carbon $newEta): SlaPauseRequest
    {
        if ($req->state !== 'active') {
            throw new \DomainException('Only an active pause can be extended.');
        }

        $tiket = $req->tiket;
        if ($tiket->slaPauseRequests()->where('state', 'pending')->exists()) {
            throw new \DomainException('An extension request is already waiting for an answer.');
        }

        // The original pause keeps running untouched until its own ETA; only the
        // extension asks for approval. Rejecting it therefore never ends the pause.
        $new = $tiket->slaPauseRequests()->create([
            'requested_by' => $adminId,
            'requested_at' => now(),
            'reason' => $req->reason,
            'attachment_path' => $req->attachment_path,
            'eta' => $newEta,
            'state' => 'pending',
        ]);

        $this->logService->catat($tiket, 'sla_pause_extended', userId: $adminId, keterangan: 'Deadline pause extension requested with a new ETA');

        $this->notificationService->slaPauseRequested($new);

        return $new;
    }

    public function forceResume(SlaPauseRequest $req, int $managerId): void
    {
        if ($req->state === 'active') {
            $this->resume($req, 'forced_manager', $managerId);

            return;
        }

        if ($req->state === 'pending') {
            $req->update(['state' => 'cancelled', 'approved_by' => $managerId, 'approved_at' => now(), 'decided_note' => 'Cancelled by manager']);
            $this->logService->catat($req->tiket, 'sla_pause_rejected', userId: $managerId, keterangan: 'Deadline pause request cancelled by manager');
        }
    }

    public function reject(SlaPauseRequest $req, int $userId, string $note): void
    {
        if ($req->state !== 'pending') {
            return;
        }

        $req->update([
            'state' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'decided_note' => $note,
        ]);

        $this->logService->catat(
            $req->tiket,
            'sla_pause_rejected',
            userId: $userId,
            keterangan: $note !== '' ? 'Deadline pause rejected, reason: '.$note : 'Deadline pause rejected',
        );

        $this->notificationService->slaPauseRejected($req);

        // Surface the reason in the ticket chat so the admin sees why it was rejected.
        if ($note !== '') {
            $this->komentarService->simpanKomentar(
                $req->tiket,
                $userId,
                'Rejected the deadline pause request. Reason: '.$note,
            );
        }
    }
}

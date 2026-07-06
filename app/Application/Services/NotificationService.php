<?php

namespace App\Application\Services;

use App\Models\SlaPauseRequest;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use App\Notifications\FeedbackNotResolved;
use App\Notifications\FeedbackSolved;
use App\Notifications\NewReplyPosted;
use App\Notifications\NewTicketCreated;
use App\Notifications\RatingSubmittedNotification;
use App\Notifications\RepetitiveOffAcceptedNotification;
use App\Notifications\RepetitiveOffAutoAcceptedNotification;
use App\Notifications\RepetitiveOffCancelledNotification;
use App\Notifications\RepetitiveOffRequestedNotification;
use App\Notifications\RepetitiveRefusedNotification;
use App\Notifications\SlaPauseApproved;
use App\Notifications\SlaPauseRejected;
use App\Notifications\SlaPauseRequested;
use App\Notifications\SlaPauseResumed;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketAssignedToAdmin;
use App\Notifications\TicketClosedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketMarkedDone;
use App\Notifications\TicketReassignedAway;
use App\Notifications\TicketStatusChanged;

class NotificationService
{
    public function ticketCreated(Tiket $tiket): void
    {
        // Smart fan-out: admin di plant tiket + admin yang pernah jadi PIC ticket di plant itu
        $localAdminIds = User::where('peran', 'admin')
            ->whereHas('karyawan', fn ($q) => $q->where('id_lokasi', $tiket->id_lokasi))
            ->pluck('id');

        $historyAdminIds = Tiket::where('id_lokasi', $tiket->id_lokasi)
            ->whereNotNull('id_penanggung_jawab')
            ->where('id', '!=', $tiket->id)
            ->distinct()
            ->pluck('id_penanggung_jawab');

        $allAdminIds = $localAdminIds->merge($historyAdminIds)->unique();

        $admins = User::whereIn('id', $allAdminIds)
            ->where('peran', 'admin')
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new NewTicketCreated($tiket));
        }
    }

    /** Notify the admin who now owns the ticket (manager assign/reassign). */
    public function ticketAssignedToAdmin(Tiket $tiket, User $admin): void
    {
        $admin->notify(new TicketAssignedToAdmin($tiket));
    }

    /** Notify the former PIC that the ticket was reassigned away from them. */
    public function ticketReassignedAway(Tiket $tiket, User $oldAdmin, string $newAdminName): void
    {
        $oldAdmin->notify(new TicketReassignedAway($tiket, $newAdminName));
    }

    public function ticketAssigned(Tiket $tiket, User $admin): void
    {
        $karyawanUser = $tiket->user;
        if ($karyawanUser) {
            $karyawanUser->notify(new TicketAssigned($tiket, $admin->name));
        }
    }

    public function ticketReadyForConfirmation(Tiket $tiket): void
    {
        $karyawanUser = $tiket->user;
        if ($karyawanUser) {
            $karyawanUser->notify(new TicketMarkedDone($tiket));
        }
    }

    public function statusChanged(Tiket $tiket, StatusTiketModel $newStatus): void
    {
        $karyawanUser = $tiket->user;

        if ($karyawanUser) {
            $karyawanUser->notify(new TicketStatusChanged($tiket, $newStatus));
        }
    }

    public function replyPosted(Tiket $tiket, User $replier): void
    {
        if ($replier->isAdmin()) {
            $karyawanUser = $tiket->user;
            if ($karyawanUser) {
                $karyawanUser->notify(new NewReplyPosted($tiket, $replier->name));
            }
        } else {
            if ($tiket->assignedAdmin) {
                $tiket->assignedAdmin->notify(new NewReplyPosted($tiket, $replier->name));
            }
        }
    }

    public function feedbackSolved(Tiket $tiket): void
    {
        if ($tiket->assignedAdmin) {
            $tiket->assignedAdmin->notify(new FeedbackSolved($tiket));
        }
    }

    public function feedbackNotResolved(Tiket $tiket): void
    {
        if ($tiket->assignedAdmin) {
            $tiket->assignedAdmin->notify(new FeedbackNotResolved($tiket));
        }
    }

    public function repetitiveOffRequested(Tiket $tiket): void
    {
        $karyawanUser = $tiket->user;
        $admin = $tiket->assignedAdmin;
        if ($karyawanUser && $admin) {
            $karyawanUser->notify(new RepetitiveOffRequestedNotification(
                $tiket,
                $admin->karyawan?->nama ?? $admin->name,
                $tiket->repetitive_review_admin_note ?? '',
            ));
        }
    }

    public function repetitiveOffAccepted(Tiket $tiket): void
    {
        // User accepted admin's request → notify the admin PIC.
        $admin = $tiket->assignedAdmin;
        $karyawanUser = $tiket->user;
        if ($admin && $karyawanUser) {
            $admin->notify(new RepetitiveOffAcceptedNotification(
                $tiket,
                $karyawanUser->karyawan?->nama ?? $karyawanUser->name,
            ));
        }
    }

    public function repetitiveOffRefused(Tiket $tiket): void
    {
        $admin = $tiket->assignedAdmin;
        $karyawanUser = $tiket->user;
        if ($admin && $karyawanUser) {
            $admin->notify(new RepetitiveRefusedNotification(
                $tiket,
                $karyawanUser->karyawan?->nama ?? $karyawanUser->name,
                $tiket->repetitive_review_user_note ?? '',
            ));
        }
    }

    /**
     * Notify user requester saat sistem auto-accept setelah 4 jam tanpa respond.
     * Admin tidak di-notify khusus — bisa lihat hasilnya via activity log + ticket detail.
     */
    public function repetitiveOffAutoAccepted(Tiket $tiket): void
    {
        $karyawanUser = $tiket->user;
        if ($karyawanUser) {
            $karyawanUser->notify(new RepetitiveOffAutoAcceptedNotification($tiket));
        }
    }

    /**
     * Notify ticket requester saat tiket berhasil dibuat (confirmation email).
     * Berbeda dari ticketCreated() yang fan-out ke admin.
     */
    public function ticketCreatedForUser(Tiket $tiket): void
    {
        $karyawanUser = $tiket->user;
        if ($karyawanUser) {
            $karyawanUser->notify(new TicketCreatedNotification($tiket));
        }
    }

    /**
     * Notify BOTH user (requester) and admin PIC saat ticket closed.
     */
    public function ticketClosed(Tiket $tiket): void
    {
        if ($tiket->user) {
            $tiket->user->notify(new TicketClosedNotification($tiket, 'user'));
        }
        if ($tiket->assignedAdmin) {
            $tiket->assignedAdmin->notify(new TicketClosedNotification($tiket, 'admin'));
        }
    }

    /**
     * Notify admin PIC saat user submit rating.
     */
    public function ratingSubmitted(Tiket $tiket, int $rating, ?string $comment = null): void
    {
        if ($tiket->assignedAdmin) {
            $tiket->assignedAdmin->notify(new RatingSubmittedNotification($tiket, $rating, $comment));
        }
    }

    /**
     * Notify ticket requester saat admin Concede (acceptUserRefusal) — ticket remains repetitive.
     */
    public function repetitiveOffCancelled(Tiket $tiket): void
    {
        if ($tiket->user) {
            $tiket->user->notify(new RepetitiveOffCancelledNotification($tiket));
        }
    }

    public function slaPauseRequested(SlaPauseRequest $req): void
    {
        $req->tiket->user?->notify(new SlaPauseRequested($req));
    }

    public function slaPauseApproved(SlaPauseRequest $req, bool $auto): void
    {
        $req->tiket->assignedAdmin?->notify(new SlaPauseApproved($req, $auto));
        if ($auto) {
            $req->tiket->user?->notify(new SlaPauseApproved($req, true));
        }
    }

    public function slaPauseRejected(SlaPauseRequest $req): void
    {
        $req->tiket->assignedAdmin?->notify(new SlaPauseRejected($req));
    }

    public function slaPauseResumed(SlaPauseRequest $req): void
    {
        $req->tiket->assignedAdmin?->notify(new SlaPauseResumed($req));
        $req->tiket->user?->notify(new SlaPauseResumed($req));
    }
}

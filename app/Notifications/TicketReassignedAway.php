<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReassignedAway extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket, public string $newAdminName) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);

        $mail = (new MailMessage)
            ->subject("{$tktNumber} reassigned")
            ->greeting("Hello {$notifiable->name},")
            ->line("Ticket {$tktNumber} has been reassigned from you to {$this->newAdminName} by the manager.")
            ->line('You no longer need to work on this ticket.')
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $notifiable->name ?? 'Admin');
    }

    public function toArray(object $notifiable): array
    {
        // NOTE: 'tiket_id' is intentionally OMITTED. After reassignment the former
        // PIC is no longer the ticket's assignee, so the Alerts/BellBadge admin
        // filter (tiket_id must match a ticket they own) would hide this. Omitting
        // it makes tiket_id SQL NULL → the notice reaches the former PIC.
        return [
            'title' => 'Ticket reassigned',
            'description' => "Ticket #{$this->tiket->id} has been reassigned from you to {$this->newAdminName}.",
            'action' => 'ticket_reassigned_away',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

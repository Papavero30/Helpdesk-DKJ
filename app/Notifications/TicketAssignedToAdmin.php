<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedToAdmin extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $plant = $this->tiket->lokasi?->nama_lokasi ?? 'a plant';

        $mail = (new MailMessage)
            ->subject("{$tktNumber} assigned to you")
            ->greeting("Hello {$notifiable->name},")
            ->line("Ticket {$tktNumber} at {$plant} has been assigned to you by the manager.")
            ->line('Please start working on it and keep the requester updated.')
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $notifiable->name ?? 'Admin');
    }

    public function toArray(object $notifiable): array
    {
        $plant = $this->tiket->lokasi?->nama_lokasi ?? '—';

        return [
            'title' => 'Ticket assigned to you',
            'description' => "Ticket #{$this->tiket->id} at {$plant} has been assigned to you by the manager.",
            'tiket_id' => $this->tiket->id,
            'action' => 'ticket_assigned_to_admin',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

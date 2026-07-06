<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket, public string $adminName) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);

        $mail = (new MailMessage)
            ->subject("{$tktNumber}: Your ticket has been assigned")
            ->greeting("Hello {$notifiable->name},")
            ->line("Good news, your ticket {$this->tiket->ref()} has been picked up by {$this->adminName}.")
            ->line('They will start working on it shortly. You can follow the progress and chat with the admin on the ticket page.')
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $this->adminName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ticket assigned',
            'description' => "Ticket {$this->tiket->ref()} has been taken by {$this->adminName}.",
            'tiket_id' => $this->tiket->id,
            'action' => 'ticket_assigned',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

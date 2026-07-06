<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReminderNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket, public string $audience) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tkt = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $line = $this->audience === 'admin'
            ? "This is a reminder about ticket {$this->tiket->ref()}, which is assigned to you."
            : "This is a reminder regarding your ticket {$this->tiket->ref()}.";

        $mail = (new MailMessage)
            ->subject("{$tkt}: Reminder")
            ->greeting("Hello {$notifiable->name},")
            ->line($line)
            ->line('(Reminder details will be finalized soon.)')
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, 'Manager');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ticket reminder',
            'description' => $this->audience === 'admin'
                ? "Reminder: ticket {$this->tiket->ref()} is awaiting your follow-up."
                : "Reminder: your ticket {$this->tiket->ref()} is being followed up.",
            'tiket_id' => $this->tiket->id,
            'action' => 'reminder',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

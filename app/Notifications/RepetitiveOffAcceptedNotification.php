<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the admin PIC when the user ACCEPTS the admin's request to mark a ticket
 * as NOT repetitive. The ticket's berulang flag is now false.
 */
class RepetitiveOffAcceptedNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket, public string $userName) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);

        $mail = (new MailMessage)
            ->subject("{$tktNumber} User accepted: ticket is now NOT repetitive")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->userName} accepted your request. Ticket {$this->tiket->ref()} is now marked as NOT repetitive and has been removed from the repetitive group.")
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $this->userName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'User accepted repetitive change',
            'description' => "{$this->userName} accepted removal of ticket {$this->tiket->ref()} from the repetitive group.",
            'tiket_id' => $this->tiket->id,
            'action' => 'repetitive_off_accepted',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

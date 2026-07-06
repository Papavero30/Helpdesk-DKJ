<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepetitiveRefusedNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket, public string $userName, public string $userNote) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $mail = (new MailMessage)
            ->subject("{$tktNumber}: User refused your request")
            ->greeting("Hello {$notifiable->name},")
            ->line("User {$this->userName} refused your request to remove ticket {$this->tiket->ref()} from the repetitive group.")
            ->line("User reason: \"{$this->userNote}\"")
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $this->userName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'User refused repetitive change',
            'description' => "User {$this->userName} refused removal of ticket {$this->tiket->ref()} from repetitive group.",
            'tiket_id' => $this->tiket->id,
            'action' => 'repetitive_off_refused',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepetitiveOffAutoAcceptedNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $mail = (new MailMessage)
            ->subject("{$tktNumber}: Auto-accepted repetitive change")
            ->greeting("Hello {$notifiable->name},")
            ->line("Ticket {$this->tiket->ref()} has been automatically removed from the repetitive group because you didn't respond within 4 hours.")
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Repetitive status auto-accepted',
            'description' => "Ticket {$this->tiket->ref()} was automatically removed from repetitive group (no response within 4 hours).",
            'tiket_id' => $this->tiket->id,
            'action' => 'repetitive_off_auto_accepted',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

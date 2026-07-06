<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepetitiveOffRequestedNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket, public string $adminName, public string $adminNote) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $mail = (new MailMessage)
            ->subject("{$tktNumber}: Admin requested NOT repetitive")
            ->greeting("Hello {$notifiable->name},")
            ->line("Admin {$this->adminName} requested to mark ticket {$this->tiket->ref()} as NOT repetitive.")
            ->line("Reason: \"{$this->adminNote}\"")
            ->action('Review Request', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $this->adminName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Repetitive status change requested',
            'description' => "Admin {$this->adminName} wants to remove ticket {$this->tiket->ref()} from repetitive group.",
            'tiket_id' => $this->tiket->id,
            'action' => 'repetitive_off_requested',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

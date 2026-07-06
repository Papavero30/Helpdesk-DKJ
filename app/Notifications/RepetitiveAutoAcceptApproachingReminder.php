<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepetitiveAutoAcceptApproachingReminder extends Notification implements ShouldQueue
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
        $adminName = $this->tiket->assignedAdmin?->karyawan?->nama ?? $this->tiket->assignedAdmin?->name ?? 'the admin';

        $mail = (new MailMessage)
            ->subject("⏰ {$tktNumber}: Please respond within 1 hour")
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a friendly reminder: {$adminName} has requested that ticket {$this->tiket->ref()} be marked as NOT Repetitive.")
            ->line('If you do not respond within the next 1 hour, the system will automatically accept the change.')
            ->line('If you disagree, please refuse and provide your reason now.')
            ->action('Respond Now', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Action needed: Repetitive change pending',
            'description' => "Auto-accept in ~1 hour for ticket {$this->tiket->ref()}",
            'tiket_id' => $this->tiket->id,
            'action' => 'repetitive_auto_accept_reminder',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

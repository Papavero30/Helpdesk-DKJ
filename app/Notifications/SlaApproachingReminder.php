<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaApproachingReminder extends Notification implements ShouldQueue
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
        $deadline = $this->tiket->target_penyelesaian?->format('d M Y, H:i') ?? '—';
        $userName = $this->tiket->user?->karyawan?->nama ?? $this->tiket->user?->name ?? 'the user';

        $mail = (new MailMessage)
            ->subject("⏰ {$tktNumber}: SLA approaching (1 hour left)")
            ->greeting("Hello {$notifiable->name},")
            ->line("Ticket {$this->tiket->ref()} (requested by {$userName}) is approaching its SLA deadline.")
            ->line("SLA deadline: {$deadline}")
            ->line('Please resolve this ticket soon to stay within SLA.')
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'SLA approaching',
            'description' => "Ticket {$this->tiket->ref()} SLA expires in approximately 1 hour.",
            'tiket_id' => $this->tiket->id,
            'action' => 'sla_reminder',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

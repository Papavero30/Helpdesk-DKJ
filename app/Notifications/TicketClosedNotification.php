<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketClosedNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    /** @param string $audience 'user'|'admin' — adjusts greeting & body */
    public function __construct(public Tiket $tiket, public string $audience = 'user') {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $outcomeText = match ($this->tiket->sla_outcome) {
            'on_time' => 'closed on time within SLA',
            'overtime' => 'closed (SLA exceeded)',
            'ahead' => 'closed ahead of schedule',
            default => 'closed',
        };

        $mail = (new MailMessage)
            ->subject("{$tktNumber}: Ticket closed")
            ->greeting("Hello {$notifiable->name},");

        if ($this->audience === 'admin') {
            $userName = $this->tiket->user?->karyawan?->nama ?? $this->tiket->user?->name ?? 'the requester';
            $mail->line("Ticket {$this->tiket->ref()} (requested by {$userName}) has been {$outcomeText}.");
        } else {
            $mail->line("Your ticket {$this->tiket->ref()} has been {$outcomeText}.");
        }

        if ($this->tiket->penilaian) {
            $stars = str_repeat('★', $this->tiket->penilaian->nilai);
            $mail->line("Final rating: {$stars} ({$this->tiket->penilaian->nilai}/5)");
        }

        $mail->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ticket closed',
            'description' => "Ticket {$this->tiket->ref()} has been closed.",
            'tiket_id' => $this->tiket->id,
            'action' => 'ticket_closed',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OverdueDigestNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    /** @param  Collection<int,Tiket>  $tickets */
    public function __construct(public Collection $tickets) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->tickets->count();

        $mail = (new MailMessage)
            ->subject("{$count} overdue ticket(s) currently past SLA")
            ->greeting("Hello {$notifiable->name},")
            ->line("There are {$count} ticket(s) currently overdue (past their SLA deadline):");

        foreach ($this->tickets as $tiket) {
            $tktNumber = '#TKT'.str_pad($tiket->id, 2, '0', STR_PAD_LEFT);
            $plant = $tiket->lokasi?->nama_lokasi ?? '—';
            $mail->line("• {$tktNumber} ({$plant}): ".Str::limit($tiket->deskripsi, 60));
        }

        $mail->action('Open Reports', url('/admin/report'));

        return $this->brand($mail, $notifiable->name ?? 'Manager');
    }
}

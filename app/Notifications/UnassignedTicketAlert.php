<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnassignedTicketAlert extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public Tiket $tiket) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $plant = $this->tiket->lokasi?->nama_lokasi ?? 'an unknown plant';

        $mail = (new MailMessage)
            ->subject("Unassigned ticket {$tktNumber} needs attention")
            ->greeting("Hello {$notifiable->name},")
            ->line("Ticket {$this->tiket->ref()} at {$plant}, from {$this->tiket->requesterName()}, has been open with no admin assigned.")
            ->line('Please assign it to an admin so work can begin.')
            ->action('Assign in Oversight', url('/manager/oversight'));

        return $this->brand($mail, $notifiable->name ?? 'Manager');
    }

    public function toArray(object $notifiable): array
    {
        $plant = $this->tiket->lokasi?->nama_lokasi ?? '—';

        return [
            'title' => 'Unassigned ticket',
            'description' => "Ticket {$this->tiket->ref()} at {$plant} from {$this->tiket->requesterName()} is still waiting for an admin.",
            'tiket_id' => $this->tiket->id,
            'action' => 'unassigned_alert',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

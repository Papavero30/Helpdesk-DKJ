<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketOverdueAlert extends Notification
{
    use Queueable;

    public function __construct(public Tiket $tiket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $plant = $this->tiket->lokasi?->nama_lokasi ?? '—';

        return [
            'title' => 'Ticket overdue',
            'description' => "Ticket {$this->tiket->ref()} at {$plant} from {$this->tiket->requesterName()} has breached its SLA deadline.",
            'tiket_id' => $this->tiket->id,
            'action' => 'overdue_alert',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

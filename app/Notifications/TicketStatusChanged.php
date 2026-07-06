<?php

namespace App\Notifications;

use App\Models\StatusTiketModel;
use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TicketStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tiket $tiket, public StatusTiketModel $newStatus) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Status updated',
            'description' => "Ticket {$this->tiket->ref()} status changed to {$this->newStatus->nama_status} by {$this->tiket->adminName()}.",
            'tiket_id' => $this->tiket->id,
            'action' => 'status_changed',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

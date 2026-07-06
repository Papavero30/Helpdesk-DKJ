<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewReplyPosted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tiket $tiket, public string $replierName) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New reply',
            'description' => "{$this->replierName} added a comment on ticket {$this->tiket->ref()}.",
            'tiket_id' => $this->tiket->id,
            'action' => 'reply_posted',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

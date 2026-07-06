<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackNotResolved extends Notification implements ShouldQueue
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
        $userName = $this->tiket->user?->karyawan?->nama ?? $this->tiket->user?->name ?? 'The requester';

        $mail = (new MailMessage)
            ->subject("{$tktNumber} Reopened: issue not resolved")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$userName} reported that ticket {$this->tiket->ref()} is not resolved yet.")
            ->line('The ticket has been reopened. Please take further action.')
            ->action('View Ticket Details', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $userName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ticket reopened',
            'description' => "Ticket {$this->tiket->ref()} reopened. The issue was not resolved.",
            'tiket_id' => $this->tiket->id,
            'action' => 'feedback_not_resolved',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

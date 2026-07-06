<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackSolved extends Notification implements ShouldQueue
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
            ->subject("{$tktNumber}: Resolution confirmed")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$userName} confirmed that ticket {$this->tiket->ref()} has been resolved.")
            ->line('The ticket is now closed.')
            ->action('View Ticket Details', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $userName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ticket resolved',
            'description' => "Ticket {$this->tiket->ref()} confirmed resolved by the employee.",
            'tiket_id' => $this->tiket->id,
            'action' => 'feedback_solved',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

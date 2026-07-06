<?php

namespace App\Notifications;

use App\Models\SlaPauseRequest;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaPauseRejected extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public SlaPauseRequest $req) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tkt = '#TKT'.str_pad($this->req->tiket_id, 2, '0', STR_PAD_LEFT);
        $mail = (new MailMessage)
            ->subject("{$tkt}: Deadline pause rejected")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->req->requesterName()} rejected the deadline pause request on ticket {$this->req->ticketRef()}. The clock keeps running.")
            ->line("Reason: \"{$this->req->decided_note}\"")
            ->action('View Ticket', url("/ticket/{$this->req->tiket_id}"));

        return $this->brand($mail, 'Helpdesk');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Deadline pause rejected',
            'description' => "{$this->req->requesterName()} rejected the deadline pause on ticket {$this->req->ticketRef()} (reason: {$this->req->decided_note}). The clock keeps running.",
            'tiket_id' => $this->req->tiket_id,
            'action' => 'sla_pause_rejected',
            'link' => "/ticket/{$this->req->tiket_id}",
        ];
    }
}

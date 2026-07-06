<?php

namespace App\Notifications;

use App\Models\SlaPauseRequest;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaPauseRequested extends Notification implements ShouldQueue
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
        $eta = $this->req->eta->format('d M Y, H:i');
        $mail = (new MailMessage)
            ->subject("{$tkt}: Deadline pause requested")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->req->adminName()} requested to pause the deadline on ticket {$this->req->ticketRef()} while waiting on an external dependency.")
            ->line("Reason: \"{$this->req->reason}\"")
            ->line("Expected resume: {$eta}.")
            ->action('Review Request', url("/ticket/{$this->req->tiket_id}"));

        return $this->brand($mail, 'Helpdesk');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Deadline pause requested',
            'description' => "{$this->req->adminName()} asked to pause the deadline on ticket {$this->req->ticketRef()} (reason: {$this->req->reason}).",
            'tiket_id' => $this->req->tiket_id,
            'action' => 'sla_pause_requested',
            'link' => "/ticket/{$this->req->tiket_id}",
        ];
    }
}

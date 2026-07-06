<?php

namespace App\Notifications;

use App\Models\SlaPauseRequest;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaPauseApproved extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(public SlaPauseRequest $req, public bool $auto = false) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tkt = '#TKT'.str_pad($this->req->tiket_id, 2, '0', STR_PAD_LEFT);
        $eta = $this->req->eta->format('d M Y, H:i');
        $ref = $this->req->ticketRef();
        $line = $this->auto
            ? "The deadline pause on ticket {$ref} was auto approved after 24 hours of no response and is now active until {$eta}."
            : "{$this->req->requesterName()} approved the deadline pause on ticket {$ref}. It is now active until {$eta}.";
        $mail = (new MailMessage)
            ->subject("{$tkt}: Deadline pause approved")
            ->greeting("Hello {$notifiable->name},")
            ->line($line)
            ->action('View Ticket', url("/ticket/{$this->req->tiket_id}"));

        return $this->brand($mail, 'Helpdesk');
    }

    public function toArray(object $notifiable): array
    {
        $ref = $this->req->ticketRef();
        $eta = $this->req->eta->format('d M Y, H:i');
        $description = $this->auto
            ? "The deadline pause on ticket {$ref} was auto approved after 24 hours of no response and is now active until {$eta}."
            : "{$this->req->requesterName()} approved the deadline pause on ticket {$ref}. It is now active until {$eta}.";

        return [
            'title' => 'Deadline pause approved',
            'description' => $description,
            'tiket_id' => $this->req->tiket_id,
            'action' => 'sla_pause_approved',
            'link' => "/ticket/{$this->req->tiket_id}",
        ];
    }
}

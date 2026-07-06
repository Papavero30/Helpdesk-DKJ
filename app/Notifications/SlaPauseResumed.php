<?php

namespace App\Notifications;

use App\Models\SlaPauseRequest;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaPauseResumed extends Notification implements ShouldQueue
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
            ->subject("{$tkt}: Deadline resumed")
            ->greeting("Hello {$notifiable->name},")
            ->line("The deadline on ticket {$this->req->ticketRef()} is running again ({$this->resumedBy()}).")
            ->action('View Ticket', url("/ticket/{$this->req->tiket_id}"));

        return $this->brand($mail, 'Helpdesk');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Deadline resumed',
            'description' => "The deadline on ticket {$this->req->ticketRef()} is running again ({$this->resumedBy()}).",
            'tiket_id' => $this->req->tiket_id,
            'action' => 'sla_pause_resumed',
            'link' => "/ticket/{$this->req->tiket_id}",
        ];
    }

    private function resumedBy(): string
    {
        return match ($this->req->resume_kind) {
            'auto_eta' => 'auto-resumed at the scheduled time',
            'forced_manager' => 'resumed by the manager',
            default => 'resumed by '.$this->req->adminName(),
        };
    }
}

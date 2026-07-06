<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketMarkedDone extends Notification implements ShouldQueue
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
        $adminName = $this->tiket->assignedAdmin?->karyawan?->nama
            ?? $this->tiket->assignedAdmin?->name
            ?? 'The IT admin';

        $mail = (new MailMessage)
            ->subject("{$tktNumber} Action required: please confirm resolution")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$adminName} has marked ticket {$this->tiket->ref()} as resolved.")
            ->line('Please confirm whether your issue has been fully resolved.')
            ->action('Confirm Resolution', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $adminName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Action required',
            'description' => "Ticket {$this->tiket->ref()} has been resolved. Please confirm.",
            'tiket_id' => $this->tiket->id,
            'action' => 'ticket_done',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

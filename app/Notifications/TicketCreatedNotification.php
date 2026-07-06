<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
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
        $kategori = $this->tiket->kategori?->nama_kategori ?? '—';
        $lokasi = $this->tiket->lokasi?->nama_lokasi ?? '—';

        $mail = (new MailMessage)
            ->subject("{$tktNumber}: Your ticket has been created")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your support ticket {$this->tiket->ref()} has been successfully created.")
            ->line("Category: {$kategori}")
            ->line("Location: {$lokasi}")
            ->line('An IT admin will pick it up and contact you shortly.')
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ticket created',
            'description' => "Your ticket {$this->tiket->ref()} has been created.",
            'tiket_id' => $this->tiket->id,
            'action' => 'ticket_created',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

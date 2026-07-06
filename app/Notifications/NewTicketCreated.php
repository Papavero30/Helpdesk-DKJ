<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewTicketCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tiket $tiket) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $namaKategori = $this->tiket->kategori?->nama_kategori ?? 'ticket';

        return (new MailMessage)
            ->subject('Ticket #TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT).': New ticket created')
            ->greeting("Hello {$notifiable->name},")
            ->line("A new {$namaKategori} ticket has been submitted.")
            ->line('Description: '.Str::limit($this->tiket->deskripsi, 100))
            ->action('View Ticket Details', url("/ticket/{$this->tiket->id}"));
    }

    public function toArray(object $notifiable): array
    {
        $namaKategori = $this->tiket->kategori?->nama_kategori ?? 'ticket';

        return [
            'title' => 'New ticket',
            'description' => "New {$namaKategori} ticket: ".Str::limit($this->tiket->deskripsi, 60),
            'tiket_id' => $this->tiket->id,
            'action' => 'ticket_created',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

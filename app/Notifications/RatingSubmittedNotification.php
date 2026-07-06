<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RatingSubmittedNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(
        public Tiket $tiket,
        public int $rating,
        public ?string $comment = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $stars = str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
        $userName = $this->tiket->user?->karyawan?->nama ?? $this->tiket->user?->name ?? 'A user';

        $mail = (new MailMessage)
            ->subject("{$tktNumber}: User rated your ticket ({$this->rating}★)")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$userName} just rated ticket {$this->tiket->ref()}:")
            ->line("Rating: {$stars} ({$this->rating}/5)");

        if ($this->comment) {
            $mail->line("Comment: \"{$this->comment}\"");
        }

        $mail->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $userName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New rating received',
            'description' => "Ticket {$this->tiket->ref()} was rated {$this->rating}★",
            'tiket_id' => $this->tiket->id,
            'action' => 'rating_submitted',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

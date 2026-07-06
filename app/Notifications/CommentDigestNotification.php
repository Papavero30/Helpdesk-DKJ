<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Digest email berisi 1+ komentar baru dalam window 30 menit (anti-spam).
 * Dipanggil oleh scheduled command, bukan per-komentar.
 */
class CommentDigestNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use Queueable;

    public function __construct(
        public Tiket $tiket,
        public Collection $komentars, // collection of KomentarTicket
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tktNumber = '#TKT'.str_pad($this->tiket->id, 2, '0', STR_PAD_LEFT);
        $count = $this->komentars->count();
        $plural = $count === 1 ? 'comment' : 'comments';

        $mail = (new MailMessage)
            ->subject("💬 {$tktNumber}: {$count} new {$plural}")
            ->greeting("Hello {$notifiable->name},")
            ->line("There {$this->verb($count)} {$count} new {$plural} on ticket {$this->tiket->ref()}:");

        foreach ($this->komentars as $k) {
            $authorName = $k->user?->karyawan?->nama ?? $k->user?->name ?? 'Someone';
            $body = mb_strlen($k->isi_komentar) > 200
                ? mb_substr($k->isi_komentar, 0, 200).'…'
                : $k->isi_komentar;
            // Show when each comment was actually posted — even though they are
            // delivered together in one digest, each line keeps its own timestamp.
            // Timestamps are stored in UTC (config/app.php), so convert to WIB
            // (Asia/Jakarta) for the reader; otherwise the email would show a
            // time 7 hours off from when the user actually commented.
            $postedAt = $k->created_at
                ? $k->created_at->copy()->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB'
                : '';
            $mail->line("**{$authorName}** ({$postedAt}): \"{$body}\"");
        }

        $mail->action('Open Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New comments',
            'description' => "{$this->komentars->count()} new comment(s) on ticket {$this->tiket->ref()}",
            'tiket_id' => $this->tiket->id,
            'action' => 'comment_digest',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }

    private function verb(int $count): string
    {
        return $count === 1 ? 'is' : 'are';
    }
}

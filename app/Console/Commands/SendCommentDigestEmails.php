<?php

namespace App\Console\Commands;

use App\Application\Services\ActivityLogService;
use App\Models\KomentarTicket;
use App\Notifications\CommentDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendCommentDigestEmails extends Command
{
    protected $signature = 'tickets:send-comment-digest
        {--minutes=5 : Look back window in minutes (default 5)}';

    protected $description = 'Send digest email for new comments (anti-spam: one email per ticket per recipient per window)';

    public function handle(ActivityLogService $logService): int
    {
        $minutes = (int) $this->option('minutes');
        $since   = now()->subMinutes($minutes);

        // Ambil komentar baru dalam window, eager-load relasi yang diperlukan
        $newComments = KomentarTicket::with(['tiket.user.karyawan', 'tiket.assignedAdmin.karyawan', 'user.karyawan'])
            ->where('created_at', '>=', $since)
            ->get();

        if ($newComments->isEmpty()) {
            $this->info("No new comments in the last {$minutes} minutes.");
            return self::SUCCESS;
        }

        // Group by ticket + recipient direction
        // Recipient = pihak lawan dari komentator:
        //   - kalau komentator = ticket requester (user) → recipient = admin PIC
        //   - kalau komentator = admin PIC → recipient = user requester
        $groups = $newComments->groupBy(function (KomentarTicket $k) {
            $isFromRequester = $k->id_pengirim === $k->tiket?->id_pengguna;
            $recipientType   = $isFromRequester ? 'admin' : 'user';
            return $k->id_tiket . '-' . $recipientType;
        });

        $this->info("Found " . $groups->count() . " group(s) of comments to digest...");

        $okCount = 0;
        foreach ($groups as $key => $komentarsInGroup) {
            try {
                [$tiketId, $recipientType] = explode('-', (string) $key);
                $tiket = $komentarsInGroup->first()->tiket;
                if (! $tiket) {
                    continue;
                }

                // Skip kalau digest sudah pernah dikirim dalam window untuk recipient type ini
                $alreadySent = $tiket->activityLogs()
                    ->where('aksi', 'comment_digest_sent')
                    ->where('keterangan', 'like', "%recipient={$recipientType}%")
                    ->where('created_at', '>=', $since)
                    ->exists();
                if ($alreadySent) {
                    $this->line("  ↪ Skipped #TKT{$tiketId} ({$recipientType}): digest already sent in this window");
                    continue;
                }

                $recipient = $recipientType === 'admin' ? $tiket->assignedAdmin : $tiket->user;
                if (! $recipient) {
                    continue;
                }

                // Filter: exclude komentar yang dikirim oleh recipient sendiri (mereka tahu komentarnya)
                $forRecipient = $komentarsInGroup->filter(fn ($k) => $k->id_pengirim !== $recipient->id);
                if ($forRecipient->isEmpty()) {
                    continue;
                }

                $recipient->notify(new CommentDigestNotification($tiket, $forRecipient));
                $logService->catat(
                    $tiket,
                    'comment_digest_sent',
                    userId: null,
                    keterangan: "Sent digest of {$forRecipient->count()} comment(s) (recipient={$recipientType})",
                );
                $this->line("  ✓ Digest sent for #TKT{$tiketId} → {$recipientType} ({$forRecipient->count()} comments)");
                $okCount++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed digest for group {$key}: " . $e->getMessage());
            }
        }

        $this->info("Done. {$okCount} digest email(s) sent.");
        return self::SUCCESS;
    }
}

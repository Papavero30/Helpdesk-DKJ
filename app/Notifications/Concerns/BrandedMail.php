<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Middleware\RateLimited;
use Symfony\Component\Mime\Email;

/**
 * Shared branding for all email notifications:
 *  - Embeds the company logo (logo-dkj.png) inline via Content-ID so it always
 *    renders in the recipient's inbox, even when the app runs on localhost.
 *  - Adds a consistent signature/salutation (regards) with the acting person's name.
 *  - Rate-limits queued sends to whatever the configured SMTP host accepts
 *    (mail.rate_limit_per_second).
 *
 * Header template (resources/views/vendor/mail/html/header.blade.php) references
 * `cid:dkj-logo`, which this trait wires up.
 *
 * Notification classes using this trait should also `implements ShouldQueue` so
 * email dispatch happens on the queue worker (async) rather than in the HTTP request.
 */
trait BrandedMail
{
    /**
     * Allow plenty of attempts: each time RateLimited releases a job back to the
     * queue (because the per-second limit was hit), it counts as an attempt. We
     * give a generous tries budget so throttled jobs are retried, not failed.
     */
    public int $tries = 25;

    /**
     * But only tolerate 2 REAL exceptions (e.g. a genuine SMTP error) before
     * failing the job — so a true error still fails fast instead of retrying 25x.
     */
    public int $maxExceptions = 2;

    /**
     * Wait this many seconds before retrying a released job. Combined with the
     * 2/sec rate limiter, this spaces out throttled sends smoothly.
     */
    public int $backoff = 3;

    /**
     * Queue middleware: throttle email jobs to the 'mail' rate limiter defined in
     * AppServiceProvider. When the limit is hit, the job is released back to the
     * queue and retried after `backoff` seconds — so no email is dropped, just
     * delayed a moment.
     */
    public function middleware(object $notifiable): array
    {
        return [new RateLimited('mail')];
    }

    /**
     * Apply company branding to a MailMessage: inline logo + signature.
     *
     * @param  string|null  $signerName  Name shown above the company line in the regards block.
     *                                   Pass the acting admin/user name; null = generic system signature.
     */
    protected function brand(MailMessage $mail, ?string $signerName = null): MailMessage
    {
        // Embed logo inline (Content-ID: dkj-logo) so the header <img src="cid:dkj-logo"> resolves.
        $logoPath = public_path('images/logo-dkj.png');
        if (is_file($logoPath)) {
            $mail->withSymfonyMessage(function (Email $message) use ($logoPath) {
                $message->embedFromPath($logoPath, 'dkj-logo', 'image/png');
            });
        }

        // Consistent regards block. Laravel renders `salutation` as the closing line.
        $regards = $signerName
            ? "Regards,\n{$signerName}\nIT Help, PT. Dunia Kimia Jaya"
            : "Regards,\nIT Help, PT. Dunia Kimia Jaya";

        return $mail->salutation($regards);
    }
}

<?php

namespace App\Notifications;

use App\Models\Tiket;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to ticket requester (user/karyawan) when the admin CONCEDES after user refused:
 * Admin accepts the user's refusal → ticket remains "Repetitive".
 *
 * NOTE: this is different from RepetitiveOffRequestedNotification (admin requested OFF)
 * and from the "user accepted admin's request" outcome (which has no notification).
 */
class RepetitiveOffCancelledNotification extends Notification implements ShouldQueue
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
        $adminName = $this->tiket->assignedAdmin?->karyawan?->nama ?? $this->tiket->assignedAdmin?->name ?? 'the admin';

        $mail = (new MailMessage)
            ->subject("{$tktNumber}: Repetitive status confirmed")
            ->greeting("Hello {$notifiable->name},")
            ->line("Good news: {$adminName} has accepted your reasoning. Ticket {$this->tiket->ref()} will remain marked as Repetitive.")
            ->line('Thank you for clarifying.')
            ->action('View Ticket', url("/ticket/{$this->tiket->id}"));

        return $this->brand($mail, $adminName);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Repetitive status confirmed',
            'description' => "Admin accepted your refusal. Ticket {$this->tiket->ref()} remains repetitive.",
            'tiket_id' => $this->tiket->id,
            'action' => 'repetitive_off_cancelled',
            'link' => "/ticket/{$this->tiket->id}",
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReferenceArchivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $entityLabel,
        public string $entityName,
        public int $activeCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        // NOTE: 'tiket_id' is intentionally OMITTED (not set to null). The
        // notifications.tiket_id generated column only stores JSON INTEGER
        // values — an omitted key (or JSON null) becomes SQL NULL, and the
        // Alerts/BellBadge admin filter treats `tiket_id IS NULL` as a config
        // notification that should always reach admins.
        return [
            'title' => "{$this->entityLabel} archived",
            'description' => "{$this->entityLabel} \"{$this->entityName}\" has been archived. Your {$this->activeCount} active ticket(s) continue normally; new tickets won't use this {$this->entityLabel}.",
            'action' => 'reference_archived',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminConfigChangeNotification extends Notification
{
    use Queueable;

    public function __construct(public ActivityLog $log, public User $actor) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $actorName = $this->actor->karyawan?->nama ?? ('#'.$this->actor->id);

        return [
            'title' => 'Configuration changed by admin',
            'description' => "{$actorName}: {$this->log->keterangan}",
            'action' => 'admin_config_change',
            'link' => '/admin/activity-log?tab=configuration',
        ];
    }
}

<?php

namespace App\Models;

use App\Notifications\AdminConfigChangeNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;

class ActivityLog extends Model
{
    protected $table = 'activity_log';

    protected $fillable = [
        'tiket_id', 'id_pengguna', 'aksi',
        'status_lama', 'status_baru', 'keterangan',
        'entity_type', 'entity_id',
    ];

    /**
     * When a config event (no ticket) is performed by an ADMIN, notify all active
     * managers for oversight — admins can now edit Master Data / People, and the
     * manager tier supervises those changes. Manager-made changes don't self-notify.
     */
    protected static function booted(): void
    {
        static::created(function (self $log) {
            if ($log->tiket_id !== null || $log->id_pengguna === null) {
                return;
            }

            $actor = User::find($log->id_pengguna);
            if (! $actor || $actor->peran !== 'admin') {
                return;
            }

            $managers = User::where('peran', 'manager')->where('status_akun', 'active')->get();
            if ($managers->isNotEmpty()) {
                Notification::send($managers, new AdminConfigChangeNotification($log, $actor));
            }
        });
    }

    public function scopeTicketEvents(Builder $query): Builder
    {
        return $query->whereNotNull('tiket_id');
    }

    public function scopeConfigEvents(Builder $query): Builder
    {
        return $query->whereNull('tiket_id');
    }

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}

<?php

namespace App\Models;

use App\Application\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Tiket extends Model
{
    protected $table = 'tiket';

    /**
     * Any ticket write invalidates cached report/dashboard aggregations
     * (see ReportService::remember — the version is part of every cache key).
     */
    protected static function booted(): void
    {
        $bump = fn () => ReportService::bumpCacheVersion();
        static::saved($bump);
        static::deleted($bump);
    }

    protected $fillable = [
        'id_pengguna', 'id_lokasi', 'id_kategori', 'deskripsi',
        'foto_bukti', 'id_status_tiket', 'id_penanggung_jawab', 'berulang', 'grup_tiket_id',
        'target_penyelesaian', 'waktu_selesai', 'siap_konfirmasi', 'siap_konfirmasi_at', 'ditutup_pada', 'sla_outcome',
        'repetitive_review_state', 'repetitive_review_admin_note', 'repetitive_review_user_note',
        'repetitive_review_admin_at', 'repetitive_review_user_at',
        'sla_paused_at', 'sla_paused_total_seconds',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'target_penyelesaian' => 'datetime',
            'waktu_selesai' => 'datetime',
            'ditutup_pada' => 'datetime',
            'berulang' => 'boolean',
            'siap_konfirmasi' => 'boolean',
            'siap_konfirmasi_at' => 'datetime',
            'repetitive_review_admin_at' => 'datetime',
            'repetitive_review_user_at' => 'datetime',
            'sla_paused_at' => 'datetime',
            'sla_paused_total_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'id_lokasi');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori');
    }

    public function statusTiket(): BelongsTo
    {
        return $this->belongsTo(StatusTiketModel::class, 'id_status_tiket');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penanggung_jawab');
    }

    public function komentar(): HasMany
    {
        return $this->hasMany(KomentarTicket::class, 'id_tiket');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'tiket_id')->orderBy('created_at');
    }

    public function penilaian(): HasOne
    {
        return $this->hasOne(Penilaian::class, 'id_tiket');
    }

    public function grupTiket(): BelongsTo
    {
        return $this->belongsTo(GrupTiket::class, 'grup_tiket_id');
    }

    public function grupTiketAsLatest(): HasOne
    {
        return $this->hasOne(GrupTiket::class, 'last_ticket');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class, 'tiket_id')->orderBy('created_at');
    }

    public function slaPauseRequests(): HasMany
    {
        return $this->hasMany(SlaPauseRequest::class, 'tiket_id');
    }

    public function isSlaPaused(): bool
    {
        return $this->sla_paused_at !== null;
    }

    /** Ticket reference with its description, e.g. #TKT43 "Printer not working". */
    public function ref(): string
    {
        $number = '#TKT'.str_pad((string) $this->id, 2, '0', STR_PAD_LEFT);
        $desc = Str::limit(trim((string) $this->deskripsi), 60);

        return $desc !== '' ? $number.' "'.$desc.'"' : $number;
    }

    /** Display name of the ticket requester (the employee who owns the ticket). */
    public function requesterName(): string
    {
        return $this->user?->karyawan?->nama ?? $this->user?->name ?? 'the requester';
    }

    /** Display name of the assigned admin (PIC), or a neutral fallback. */
    public function adminName(): string
    {
        return $this->assignedAdmin?->karyawan?->nama ?? $this->assignedAdmin?->name ?? 'the admin';
    }

    /** Raw SLA deadline plus all credited pause time. Null when the category has no SLA. */
    public function effectiveDeadline(): ?Carbon
    {
        if (! $this->target_penyelesaian) {
            return null;
        }

        return $this->target_penyelesaian->copy()->addSeconds((int) ($this->sla_paused_total_seconds ?? 0));
    }

    /** Not currently paused AND past the credited deadline. */
    public function scopeOverdueEffective($query)
    {
        return $query->whereNull('sla_paused_at')
            ->whereNotNull('target_penyelesaian')
            ->whereRaw('DATE_ADD(target_penyelesaian, INTERVAL COALESCE(sla_paused_total_seconds, 0) SECOND) < ?', [now()]);
    }

    /** Not currently paused AND still within the credited deadline. */
    public function scopeOnTrackEffective($query)
    {
        return $query->whereNull('sla_paused_at')
            ->whereNotNull('target_penyelesaian')
            ->whereRaw('DATE_ADD(target_penyelesaian, INTERVAL COALESCE(sla_paused_total_seconds, 0) SECOND) >= ?', [now()]);
    }
}

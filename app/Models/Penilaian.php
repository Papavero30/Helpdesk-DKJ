<?php

namespace App\Models;

use App\Application\Services\ReportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penilaian extends Model
{
    protected $table = 'penilaian';

    protected $fillable = ['id_tiket', 'nilai', 'komentar'];

    /**
     * Ratings feed report avg_rating — invalidate cached aggregations on write.
     */
    protected static function booted(): void
    {
        $bump = fn () => ReportService::bumpCacheVersion();
        static::saved($bump);
        static::deleted($bump);
    }

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'id_tiket');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Kategori extends Model
{
    protected $table = 'kategori';

    protected $fillable = [
        'nama_kategori', 'batas_jam_sla', 'urgensi', 'warna_grafik', 'is_active', 'contoh',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tikets(): HasMany
    {
        return $this->hasMany(Tiket::class, 'id_kategori');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Rank every category by SLA so urgensi reflects priority (shortest SLA = 1).
     * Urgensi drives the recommended ticket ordering, so it must follow SLA: when a
     * category's SLA is created or edited, call this to re-derive all ranks. Ties on
     * SLA are broken by id (stable). Existing tickets' frozen deadlines are untouched.
     */
    public static function reindexUrgencyBySla(): void
    {
        DB::transaction(function () {
            $rank = 0;
            foreach (static::orderBy('batas_jam_sla')->orderBy('id')->get() as $kategori) {
                $rank++;
                if ((int) $kategori->urgensi !== $rank) {
                    $kategori->urgensi = $rank;
                    $kategori->saveQuietly();
                }
            }
        });
    }

    public static function pilihanForm(): array
    {
        return static::active()->orderBy('urgensi')->get()->map(fn ($k) => [
            'value' => $k->id,
            'label' => $k->nama_kategori,
            'sla_hours' => $k->batas_jam_sla,
            'examples' => $k->contoh ?? '',
        ])->toArray();
    }
}

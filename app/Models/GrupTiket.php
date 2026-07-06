<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupTiket extends Model
{
    protected $table = 'grup_tiket';

    protected $fillable = [
        'user_id',
        'last_ticket',
        'id_kategori',
        'id_lokasi',
        'id_penanggung_jawab',
        'jumlah',
    ];

    protected $casts = [
        'jumlah' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function last(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'last_ticket');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'id_lokasi');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penanggung_jawab');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Tiket::class, 'grup_tiket_id');
    }
}
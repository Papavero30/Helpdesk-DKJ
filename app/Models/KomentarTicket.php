<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomentarTicket extends Model
{
    protected $table = 'komentar_tiket';
    protected $fillable = ['id_tiket', 'id_pengirim', 'isi_komentar'];

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'id_tiket');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengirim');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class, 'komentar_id')->orderBy('created_at');
    }
}

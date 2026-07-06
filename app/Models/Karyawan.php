<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Karyawan extends Model
{
    protected $table = 'karyawan';

    protected $fillable = ['nama', 'email', 'no_telepon', 'id_divisi', 'id_lokasi', 'jabatan', 'id_jabatan'];

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id_karyawan');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Divisi::class, 'id_divisi');
    }

    public function jabatanRef(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'id_jabatan');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'id_lokasi');
    }

    public function tiket(): HasManyThrough
    {
        return $this->hasManyThrough(
            Tiket::class,
            User::class,
            'id_karyawan',
            'id_pengguna',
            'id',
            'id',
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lokasi extends Model
{
    protected $table = 'lokasi';

    protected $fillable = ['nama_lokasi', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tiket(): HasMany
    {
        return $this->hasMany(Tiket::class, 'id_lokasi');
    }

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class, 'id_lokasi');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

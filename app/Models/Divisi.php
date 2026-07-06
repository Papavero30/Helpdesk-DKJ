<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Divisi extends Model
{
    protected $table = 'divisi';

    protected $fillable = ['nama_divisi', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class, 'id_divisi');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

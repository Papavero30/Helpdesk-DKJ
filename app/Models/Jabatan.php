<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jabatan extends Model
{
    protected $table = 'jabatan';

    protected $fillable = ['nama_jabatan', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class, 'id_jabatan');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

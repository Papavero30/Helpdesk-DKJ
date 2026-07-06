<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusTiketModel extends Model
{
    protected $table = 'status_tiket';

    protected $fillable = [
        'nama_status',
    ];

    public function tikets(): HasMany
    {
        return $this->hasMany(Tiket::class, 'id_status_tiket');
    }

    public function label(): string
    {
        return $this->nama_status;
    }

    public static function warnaFor(string $nama): string
    {
        return match ($nama) {
            'Open' => 'amber',
            'In Progress' => 'blue',
            'On Hold' => 'orange',
            'Close' => 'green',
            default => 'gray',
        };
    }

    public static function badgeClass(?string $nama): string
    {
        return match ($nama) {
            'Open' => 'bg-amber-500 text-white',
            'In Progress' => 'bg-blue-100 text-blue-700',
            'On Hold' => 'bg-orange-100 text-orange-700',
            'Close' => 'bg-green-100 text-green-700',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    public static function findByName(string $name): ?self
    {
        return static::where('nama_status', $name)->first();
    }
}

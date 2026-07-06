<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['id_karyawan', 'password', 'peran', 'foto_profil', 'status_akun', 'terakhir_masuk'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'pengguna';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'terakhir_masuk' => 'datetime',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'id_karyawan');
    }

    public function assignedTikets(): HasMany
    {
        return $this->hasMany(Tiket::class, 'id_penanggung_jawab');
    }

    public function getNameAttribute(): string
    {
        return $this->karyawan?->nama ?? 'Unknown User';
    }

    public function getEmailAttribute(): ?string
    {
        return $this->karyawan?->email;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->karyawan?->email ?? '';
    }

    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->karyawan?->email;
    }

    public function avatarUrl(): ?string
    {
        return $this->foto_profil ? Storage::url($this->foto_profil) : null;
    }

    public function initials(): string
    {
        return strtoupper(substr($this->karyawan?->nama ?? 'U', 0, 2));
    }

    public function isAdmin(): bool
    {
        return $this->peran === 'admin';
    }

    public function isKaryawan(): bool
    {
        return $this->peran === 'karyawan';
    }

    public function isManager(): bool
    {
        return $this->peran === 'manager';
    }

    /**
     * Read-only access to operational admin pages (AllTickets, ActivityLog, Reports).
     * Admin retains exclusive write access via the strict isAdmin() check.
     */
    public function hasAdminAccess(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }
}

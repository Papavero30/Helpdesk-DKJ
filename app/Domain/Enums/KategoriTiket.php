<?php

namespace App\Domain\Enums;

enum KategoriTiket: string
{
    case Troubleshooting = 'troubleshooting';
    case Security = 'security';
    case Cctv = 'cctv';
    case ItProject = 'it_project';
    case Other = 'other';

    public function examples(): string
    {
        return match ($this) {
            self::Troubleshooting => 'PC tidak menyala, printer tidak bisa cetak, akun terkunci',
            self::Security => 'Insiden virus, akses mencurigakan, password compromise',
            self::Cctv => 'Kamera mati, footage hilang, koneksi DVR putus',
            self::ItProject => 'Pengadaan laptop, instalasi software, request akses sistem',
            self::Other => 'Kebutuhan IT yang tidak masuk kategori lain',
        };
    }

    public static function fromLabel(string $label): self
    {
        $normalized = strtolower(trim($label));

        return match (true) {
            str_contains($normalized, 'trouble') => self::Troubleshooting,
            str_contains($normalized, 'security') => self::Security,
            str_contains($normalized, 'cctv') => self::Cctv,
            str_contains($normalized, 'project') => self::ItProject,
            str_contains($normalized, 'other') => self::Other,
            default => self::Other,
        };
    }
}

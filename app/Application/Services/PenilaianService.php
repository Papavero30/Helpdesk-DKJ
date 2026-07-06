<?php

namespace App\Application\Services;

use App\Models\Penilaian;
use App\Models\Tiket;
use Illuminate\Support\Facades\Cache;

class PenilaianService
{
    public function __construct(
        private ActivityLogService $logService,
        private NotificationService $notificationService,
    ) {}

    public function simpanPenilaian(Tiket $tiket, int $nilai, string $komentar): Penilaian
    {
        $komentar = trim($komentar);

        $penilaian = Penilaian::create([
            'id_tiket' => $tiket->id,
            'nilai' => $nilai,
            'komentar' => $komentar,
        ]);

        $this->logService->catat(
            $tiket,
            'rating_submitted',
            userId: $tiket->id_pengguna,
            keterangan: "User submitted {$nilai}★ rating",
        );

        $this->notificationService->ratingSubmitted($tiket, $nilai, $komentar ?: null);

        Cache::forget("karyawan-dashboard-{$tiket->id_pengguna}");

        return $penilaian;
    }
}

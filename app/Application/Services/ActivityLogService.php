<?php

namespace App\Application\Services;

use App\Models\ActivityLog;
use App\Models\Tiket;

class ActivityLogService
{
    public function catat(
        Tiket $tiket,
        string $aksi,
        ?int $userId = null,
        ?string $statusLama = null,
        ?string $statusBaru = null,
        ?string $keterangan = null,
    ): ActivityLog {
        return ActivityLog::create([
            'tiket_id' => $tiket->id,
            'id_pengguna' => $userId,
            'aksi' => $aksi,
            'status_lama' => $statusLama,
            'status_baru' => $statusBaru,
            'keterangan' => $keterangan,
        ]);
    }

    public function catatKonfigurasi(
        string $aksi,
        int $actorId,
        string $entityType,
        ?int $entityId,
        string $keterangan,
    ): ActivityLog {
        return ActivityLog::create([
            'tiket_id' => null,
            'id_pengguna' => $actorId,
            'aksi' => $aksi,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'keterangan' => $keterangan,
        ]);
    }

    public function daftarLog(int $limit = 50)
    {
        return ActivityLog::with(['tiket.user.karyawan', 'user.karyawan'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}

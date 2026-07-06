<?php

namespace App\Application\Services;

use App\Models\KomentarTicket;
use App\Models\Tiket;
use App\Models\User;
use Illuminate\Support\Str;

class KomentarService
{
    public function __construct(
        private ActivityLogService $logService,
        private NotificationService $notificationService,
    ) {}

    public function simpanKomentar(Tiket $tiket, int $userId, string $isiKomentar): KomentarTicket
    {
        $komentar = KomentarTicket::create([
            'id_tiket' => $tiket->id,
            'id_pengirim' => $userId,
            'isi_komentar' => $isiKomentar,
        ]);

        $tiket->touch();

        $this->logService->catat(
            $tiket,
            'comment_added',
            userId: $userId,
            keterangan: Str::limit(trim($isiKomentar), 200),
        );

        $replier = User::find($userId);
        if ($replier) {
            $this->notificationService->replyPosted($tiket, $replier);
        }

        return $komentar;
    }
}

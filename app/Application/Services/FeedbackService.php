<?php

namespace App\Application\Services;

use App\Domain\Services\SlaTiming;
use App\Models\Tiket;
use Illuminate\Support\Facades\Cache;

class FeedbackService
{
    public function __construct(
        private ActivityLogService $logService,
        private NotificationService $notificationService,
        private TiketService $tiketService,
    ) {}

    public function prosesFeedback(Tiket $tiket, bool $selesai, ?string $reason = null): void
    {
        $statusLama = $tiket->statusTiket->nama_status;

        if ($selesai) {
            // tutupTiket() already sends TicketClosedNotification to BOTH user & admin PIC.
            // We intentionally do NOT also send FeedbackSolved here — when solved == close,
            // a single "Ticket closed" email per recipient avoids redundant/racing emails.
            $this->tiketService->tutupTiket($tiket);

            // Activity log entry is still recorded (for the Activity Log tab), email is not.
            $this->logService->catat(
                $tiket,
                'feedback_solved',
                userId: $tiket->id_pengguna,
                keterangan: 'User confirmed the issue is resolved',
            );
        } else {
            $tiket->update([
                'siap_konfirmasi' => false,
                'siap_konfirmasi_at' => null,
            ]);

            if ($reason !== null && trim($reason) !== '') {
                $tiket->komentar()->create([
                    'id_pengirim' => $tiket->id_pengguna,
                    'isi_komentar' => '[Issue Reason] '.trim($reason),
                ]);
            }

            $this->logService->catat(
                $tiket,
                'feedback_not_resolved',
                userId: $tiket->id_pengguna,
                statusLama: $statusLama,
                statusBaru: $statusLama,
                keterangan: $reason ?? 'No reason provided',
            );

            $this->notificationService->feedbackNotResolved($tiket);
        }

        Cache::forget("karyawan-dashboard-{$tiket->id_pengguna}");
    }

    public function autoSelesaikanTiketExpired(): int
    {
        $candidates = Tiket::where('siap_konfirmasi', true)
            ->whereNotNull('siap_konfirmasi_at')
            ->with('kategori')
            ->get();

        $closed = 0;
        foreach ($candidates as $tiket) {
            $slaHours = $tiket->kategori?->batas_jam_sla ?? 24;
            $graceMin = SlaTiming::autoSolveGraceMinutes($slaHours);
            $autoSolveAt = $tiket->siap_konfirmasi_at->copy()->addMinutes($graceMin);

            if ($autoSolveAt->lte(now())) {
                $this->tiketService->tutupTiket($tiket);

                $this->logService->catat(
                    $tiket,
                    'auto_resolved',
                    statusBaru: 'Close',
                    keterangan: "Auto-closed after grace ({$graceMin} min from siap_konfirmasi based on SLA {$slaHours}h)",
                );

                $closed++;
            }
        }

        return $closed;
    }
}

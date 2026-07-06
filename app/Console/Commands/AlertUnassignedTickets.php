<?php

namespace App\Console\Commands;

use App\Application\Services\ActivityLogService;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use App\Notifications\UnassignedTicketAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class AlertUnassignedTickets extends Command
{
    protected $signature = 'tickets:alert-unassigned
        {--hours=2 : Alert when an open ticket has had no admin for at least this many hours}';

    protected $description = 'Notify managers about open tickets left unassigned beyond the threshold';

    public function handle(ActivityLogService $logService): int
    {
        $open = StatusTiketModel::findByName('Open');
        if (! $open) {
            $this->error('Status "Open" not found.');

            return self::FAILURE;
        }

        $cutoff = now()->subHours((int) $this->option('hours'));

        $candidates = Tiket::query()
            ->where('id_status_tiket', $open->id)
            ->whereNull('id_penanggung_jawab')
            ->where('created_at', '<', $cutoff)
            // Idempotent: skip tickets already alerted (one alert per ticket).
            ->whereDoesntHave('activityLogs', fn ($q) => $q->where('aksi', 'unassigned_alert_sent'))
            ->with('lokasi')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No unassigned tickets past threshold.');

            return self::SUCCESS;
        }

        $managers = User::where('peran', 'manager')->where('status_akun', 'active')->get();
        if ($managers->isEmpty()) {
            $this->warn('No active managers to notify.');

            return self::SUCCESS;
        }

        foreach ($candidates as $tiket) {
            Notification::send($managers, new UnassignedTicketAlert($tiket));
            $logService->catat(
                $tiket,
                'unassigned_alert_sent',
                userId: null,
                keterangan: 'Unassigned ticket alert sent to managers',
            );
            $this->line("  ✓ Alerted #TKT{$tiket->id}");
        }

        $this->info("Done. {$candidates->count()} ticket(s) alerted.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Application\Services\ActivityLogService;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use App\Notifications\TicketOverdueAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class AlertOverdueTickets extends Command
{
    protected $signature = 'tickets:alert-overdue';

    protected $description = 'Notify managers (in-app) about tickets that have newly breached their SLA deadline';

    public function handle(ActivityLogService $logService): int
    {
        $close = StatusTiketModel::findByName('Close');
        $closeId = $close?->id ?? 0;

        $candidates = Tiket::query()
            ->where('id_status_tiket', '!=', $closeId)
            ->overdueEffective()
            // Idempotent: one in-app alert per ticket breach.
            ->whereDoesntHave('activityLogs', fn ($q) => $q->where('aksi', 'overdue_alert_sent'))
            ->with('lokasi')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No newly overdue tickets.');

            return self::SUCCESS;
        }

        $managers = User::where('peran', 'manager')->where('status_akun', 'active')->get();
        if ($managers->isEmpty()) {
            $this->warn('No active managers to notify.');

            return self::SUCCESS;
        }

        foreach ($candidates as $tiket) {
            Notification::send($managers, new TicketOverdueAlert($tiket));
            $logService->catat(
                $tiket,
                'overdue_alert_sent',
                userId: null,
                keterangan: 'Overdue (SLA breach) alert sent to managers',
            );
            $this->line("  ✓ Alerted #TKT{$tiket->id}");
        }

        $this->info("Done. {$candidates->count()} overdue ticket(s) alerted.");

        return self::SUCCESS;
    }
}

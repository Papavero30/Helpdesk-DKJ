<?php

namespace App\Console\Commands;

use App\Application\Services\ActivityLogService;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Notifications\SlaApproachingReminder;
use Illuminate\Console\Command;

class SendSlaApproachingReminders extends Command
{
    protected $signature = 'tickets:remind-sla-approaching
        {--minutes-before=60 : Send reminder when target_penyelesaian is this many minutes away}
        {--window=10 : Time window (minutes) around the target to catch tickets — avoids missing ticks}';

    protected $description = 'Notify admin PIC when ticket SLA is approaching (default ~1 hour before)';

    public function handle(ActivityLogService $logService): int
    {
        $inProgress = StatusTiketModel::findByName('In Progress');
        if (! $inProgress) {
            $this->error('Status "In Progress" not found.');

            return self::FAILURE;
        }

        $minutesBefore = (int) $this->option('minutes-before');
        $window = (int) $this->option('window');

        // Window: tiket yang SLA deadline-nya antara [now + minutesBefore - window/2, now + minutesBefore + window/2]
        $lowerBound = now()->addMinutes($minutesBefore - intval($window / 2));
        $upperBound = now()->addMinutes($minutesBefore + intval($window / 2));

        $candidates = Tiket::query()
            ->where('id_status_tiket', $inProgress->id)
            ->whereNotNull('id_penanggung_jawab')
            ->whereNull('sla_paused_at')
            ->whereNotNull('target_penyelesaian')
            ->whereRaw(
                'DATE_ADD(target_penyelesaian, INTERVAL COALESCE(sla_paused_total_seconds, 0) SECOND) BETWEEN ? AND ?',
                [$lowerBound, $upperBound],
            )
            // Idempotent: skip kalau reminder sudah pernah dikirim
            ->whereDoesntHave('activityLogs', fn ($q) => $q->where('aksi', 'sla_reminder_sent'))
            ->with('assignedAdmin')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No tickets within SLA reminder window ({$minutesBefore}±".intval($window / 2).'min).');

            return self::SUCCESS;
        }

        $this->info("Processing {$candidates->count()} SLA-approaching ticket(s)...");

        $okCount = 0;
        foreach ($candidates as $tiket) {
            try {
                if (! $tiket->assignedAdmin) {
                    continue;
                }
                $tiket->assignedAdmin->notify(new SlaApproachingReminder($tiket));
                $logService->catat(
                    $tiket,
                    'sla_reminder_sent',
                    userId: null,
                    keterangan: "SLA reminder sent to admin (deadline ~{$minutesBefore} min away)",
                );
                $this->line("  ✓ Reminder sent for #TKT{$tiket->id}");
                $okCount++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for #TKT{$tiket->id}: ".$e->getMessage());
            }
        }

        $this->info("Done. {$okCount}/{$candidates->count()} reminders sent.");

        return self::SUCCESS;
    }
}

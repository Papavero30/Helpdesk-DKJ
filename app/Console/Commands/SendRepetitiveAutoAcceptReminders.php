<?php

namespace App\Console\Commands;

use App\Application\Services\ActivityLogService;
use App\Models\Tiket;
use App\Notifications\RepetitiveAutoAcceptApproachingReminder;
use Illuminate\Console\Command;

class SendRepetitiveAutoAcceptReminders extends Command
{
    protected $signature = 'tickets:remind-repetitive-auto-accept
        {--hours-since-request=3 : Send reminder N hours after admin_requested_off (default 3, so user gets ~1h before auto-accept at 4h)}
        {--window=10 : Time window (minutes) around the threshold}';

    protected $description = 'Notify user requester ~1 hour before auto-accept fires on repetitive OFF request';

    public function handle(ActivityLogService $logService): int
    {
        $hours  = (int) $this->option('hours-since-request');
        $window = (int) $this->option('window');

        // Window: tiket dengan repetitive_review_admin_at antara [now - hours - window/2, now - hours + window/2]
        $lowerBound = now()->subHours($hours)->subMinutes(intval($window / 2));
        $upperBound = now()->subHours($hours)->addMinutes(intval($window / 2));

        $candidates = Tiket::query()
            ->where('repetitive_review_state', 'admin_requested_off')
            ->whereBetween('repetitive_review_admin_at', [$lowerBound, $upperBound])
            ->whereDoesntHave('activityLogs', fn ($q) => $q->where('aksi', 'repetitive_reminder_sent'))
            ->with('user')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No tickets within repetitive auto-accept reminder window (~{$hours}h since admin request).");
            return self::SUCCESS;
        }

        $this->info("Processing {$candidates->count()} ticket(s) for auto-accept reminder...");

        $okCount = 0;
        foreach ($candidates as $tiket) {
            try {
                if (! $tiket->user) {
                    continue;
                }
                $tiket->user->notify(new RepetitiveAutoAcceptApproachingReminder($tiket));
                $logService->catat(
                    $tiket,
                    'repetitive_reminder_sent',
                    userId: null,
                    keterangan: 'Auto-accept reminder sent to user (~1 hour before timeout)',
                );
                $this->line("  ✓ Reminder sent for #TKT{$tiket->id}");
                $okCount++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for #TKT{$tiket->id}: " . $e->getMessage());
            }
        }

        $this->info("Done. {$okCount}/{$candidates->count()} reminders sent.");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Application\Services\TiketService;
use App\Domain\Services\SlaTiming;
use App\Models\Tiket;
use Illuminate\Console\Command;

class AutoAcceptStaleRepetitiveRequests extends Command
{
    protected $signature = 'tickets:auto-accept-stale-repetitive';

    protected $description = 'Auto-accept admin repetitive OFF requests whose per-SLA wait window has elapsed';

    public function handle(TiketService $tiketService): int
    {
        $candidates = Tiket::where('repetitive_review_state', 'admin_requested_off')
            ->whereNotNull('repetitive_review_admin_at')
            ->with('kategori')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No pending repetitive OFF requests.');

            return self::SUCCESS;
        }

        $okCount = 0;
        foreach ($candidates as $tiket) {
            $slaHours = $tiket->kategori?->batas_jam_sla ?? 24;
            $waitMin = SlaTiming::autoAcceptRepetitiveMinutes($slaHours);
            $autoAcceptAt = $tiket->repetitive_review_admin_at->copy()->addMinutes($waitMin);

            if ($autoAcceptAt->lte(now())) {
                try {
                    $tiketService->autoAcceptStaleRequest($tiket);
                    $this->line("  ✓ Auto-accepted #TKT{$tiket->id} (waited {$waitMin} min, SLA {$slaHours}h)");
                    $okCount++;
                } catch (\Throwable $e) {
                    $this->error("  ✗ Failed for #TKT{$tiket->id}: ".$e->getMessage());
                }
            }
        }

        $this->info("Done. {$okCount} request(s) auto-accepted.");

        return self::SUCCESS;
    }
}

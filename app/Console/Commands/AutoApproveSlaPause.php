<?php

// app/Console/Commands/AutoApproveSlaPause.php

namespace App\Console\Commands;

use App\Application\Services\SlaPauseService;
use App\Models\SlaPauseRequest;
use Illuminate\Console\Command;

class AutoApproveSlaPause extends Command
{
    protected $signature = 'sla-pause:auto-approve';

    protected $description = 'Auto approve SLA pause requests the requester left unanswered past the timeout';

    public function handle(SlaPauseService $service): int
    {
        $cutoff = now()->subHours(SlaPauseService::AUTO_APPROVE_HOURS);

        $pending = SlaPauseRequest::where('state', 'pending')
            ->where('requested_at', '<=', $cutoff)
            ->get();

        foreach ($pending as $req) {
            try {
                $service->approve($req, null);
                $this->line("  ok auto-approved pause #{$req->id} (ticket #{$req->tiket_id})");
            } catch (\Throwable $e) {
                $this->error("  failed for pause #{$req->id}: ".$e->getMessage());
            }
        }

        $this->info("Done. {$pending->count()} pause(s) auto-approved.");

        return self::SUCCESS;
    }
}

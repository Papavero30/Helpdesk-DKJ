<?php

// app/Console/Commands/AutoResumeSlaPause.php

namespace App\Console\Commands;

use App\Application\Services\SlaPauseService;
use App\Models\SlaPauseRequest;
use Illuminate\Console\Command;

class AutoResumeSlaPause extends Command
{
    protected $signature = 'sla-pause:auto-resume';

    protected $description = 'Resume SLA pauses whose ETA has passed';

    public function handle(SlaPauseService $service): int
    {
        $due = SlaPauseRequest::where('state', 'active')
            ->where('eta', '<=', now())
            ->get();

        foreach ($due as $req) {
            try {
                $service->resume($req, 'auto_eta', null);
                $this->line("  ok auto-resumed pause #{$req->id} (ticket #{$req->tiket_id})");
            } catch (\Throwable $e) {
                $this->error("  failed for pause #{$req->id}: ".$e->getMessage());
            }
        }

        $this->info("Done. {$due->count()} pause(s) auto-resumed.");

        return self::SUCCESS;
    }
}

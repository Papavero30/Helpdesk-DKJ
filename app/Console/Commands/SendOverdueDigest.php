<?php

namespace App\Console\Commands;

use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use App\Notifications\OverdueDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendOverdueDigest extends Command
{
    protected $signature = 'tickets:overdue-digest';

    protected $description = 'Email managers a daily digest of all currently-overdue tickets';

    public function handle(): int
    {
        $close = StatusTiketModel::findByName('Close');
        $closeId = $close?->id ?? 0;

        $overdue = Tiket::query()
            ->where('id_status_tiket', '!=', $closeId)
            ->overdueEffective()
            ->with('lokasi')
            ->orderBy('target_penyelesaian')
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue tickets, digest not sent.');

            return self::SUCCESS;
        }

        $managers = User::where('peran', 'manager')->where('status_akun', 'active')->get();
        if ($managers->isEmpty()) {
            $this->warn('No active managers to notify.');

            return self::SUCCESS;
        }

        Notification::send($managers, new OverdueDigestNotification($overdue));
        $this->info("Digest sent: {$overdue->count()} overdue ticket(s) to {$managers->count()} manager(s).");

        return self::SUCCESS;
    }
}

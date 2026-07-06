<?php

use App\Application\Services\FeedbackService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Heartbeat: lets /health (and uptime monitors) detect a dead scheduler.
// If this timestamp goes stale (> ~10 min), schedule:run/schedule:work is down.
Schedule::call(function () {
    Cache::put('scheduler-heartbeat', now()->toIso8601String(), now()->addHours(2));
})->everyFiveMinutes()->name('scheduler-heartbeat');

Schedule::call(function () {
    $service = app(FeedbackService::class);
    $count = $service->autoSelesaikanTiketExpired();
    if ($count > 0) {
        logger("Auto-selesai: {$count} tiket melewati tenggat verifikasi");
    }
})->everyFiveMinutes()
    ->between('08:00', '17:00')
    ->weekdays()
    ->timezone('Asia/Jakarta')
    ->name('auto-selesai-tiket');

// Auto-accept stale repetitive OFF requests (4 jam timeout)
Schedule::command('tickets:auto-accept-stale-repetitive')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('auto-accept-stale-repetitive');

// Email: SLA approaching reminder to admin PIC (~1 hour before SLA deadline)
Schedule::command('tickets:remind-sla-approaching')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('sla-approaching-reminder');

// Email: Repetitive auto-accept approaching reminder to user (~1 hour before auto-accept at 4h)
Schedule::command('tickets:remind-repetitive-auto-accept')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('repetitive-auto-accept-reminder');

// Email: Comment digest (anti-spam — collect new comments and send 1 email per 5 min per ticket/recipient)
Schedule::command('tickets:send-comment-digest')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('comment-digest');

// Manager alert: open tickets left unassigned > 2h (working hours only, one alert per ticket)
Schedule::command('tickets:alert-unassigned')
    ->everyFifteenMinutes()
    ->between('08:00', '17:00')
    ->weekdays()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->name('alert-unassigned');

// Manager alert: tickets that newly breached SLA (in-app, one alert per breach, working hours)
Schedule::command('tickets:alert-overdue')
    ->everyFifteenMinutes()
    ->between('08:00', '17:00')
    ->weekdays()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->name('alert-overdue');

// Manager email: daily digest of all currently-overdue tickets
Schedule::command('tickets:overdue-digest')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->name('overdue-digest');

// Auto-approve SLA pause requests that have been pending past the 24h timeout
Schedule::command('sla-pause:auto-approve')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('sla-pause-auto-approve');

// Auto-resume active SLA pauses whose ETA has passed
Schedule::command('sla-pause:auto-resume')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('sla-pause-auto-resume');

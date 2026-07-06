<?php

namespace App\Domain\Services;

class SlaTiming
{
    /**
     * Auto-accept-repetitive window in minutes.
     *
     * Formula: clamp(SLA_hours * 60 / 3, 60, 240)
     *   - ratio 1/3 of SLA (user gets a third of the SLA window for negotiation)
     *   - floor 60 min (minimum 1h to be fair)
     *   - ceiling 240 min (4h — never drag negotiation past half a workday)
     */
    public static function autoAcceptRepetitiveMinutes(int $slaHours): int
    {
        return (int) round(max(60, min(240, $slaHours * 60 / 3)));
    }

    /**
     * Auto-solve grace in minutes between admin marking siap_konfirmasi
     * and the system auto-closing if user doesn't verify.
     *
     * Formula: clamp(SLA_hours * 60 / 4, 30, 120)
     *   - ratio 1/4 of SLA (verification is quick — test & click)
     *   - floor 30 min (give user a chance to see the notification)
     *   - ceiling 120 min (2h — never let a "ready" ticket linger)
     */
    public static function autoSolveGraceMinutes(int $slaHours): int
    {
        return (int) round(max(30, min(120, $slaHours * 60 / 4)));
    }
}

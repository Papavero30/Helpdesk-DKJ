<?php

namespace App\Application\Services;

use App\Models\Kategori;
use App\Models\Penilaian;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class StatistikService
{
    /**
     * Tier 1 — live operational snapshot of the karyawan's tickets.
     * Cheap, cached briefly. Stable across date-range changes.
     */
    public function karyawanLiveSummary(int $userId): array
    {
        $cacheKey = "karyawan-live-{$userId}";
        $cached = null;

        try {
            $raw = Cache::get($cacheKey);
            if ($this->isValidLivePayload($raw)) {
                $cached = $raw;
            } else {
                Cache::forget($cacheKey);
            }
        } catch (\Throwable) {
            Cache::forget($cacheKey);
        }

        if ($cached === null) {
            $cached = ['summary' => $this->userSummaryCounts($userId)];
            $this->safeCachePut($cacheKey, $cached);
        }

        // Pending tasks are never cached — they reflect live actionable state.
        $cached['pending'] = $this->userPendingTasks($userId);

        return $cached;
    }

    /**
     * Tier 2 — period-scoped analytics for a karyawan within a date range.
     * Not cached because the range varies per call.
     */
    public function karyawanActivity(int $userId, Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        return [
            'frequency' => $this->userPeriodFrequency($userId, $start, $end),
            'kategori' => $this->userPeriodCategory($userId, $start, $end),
            'sla_outcome' => $this->userPeriodSlaOutcome($userId, $start, $end),
            'kpi' => $this->userPeriodKpis($userId, $start, $end),
        ];
    }

    private function safeCachePut(string $cacheKey, array $payload): void
    {
        try {
            Cache::put($cacheKey, $payload, now()->addSeconds(60));
        } catch (\Throwable) {
            // Ignore cache write failures and return fresh payload.
        }
    }

    private function isValidLivePayload(mixed $payload): bool
    {
        if (! is_array($payload) || ! isset($payload['summary'])) {
            return false;
        }

        return is_array($payload['summary']);
    }

    private function userSummaryCounts(int $userId): array
    {
        $baseQuery = Tiket::query()->where('id_pengguna', $userId);

        $openId = StatusTiketModel::findByName('Open')?->id;
        $inProgressId = StatusTiketModel::findByName('In Progress')?->id;
        $closeId = StatusTiketModel::findByName('Close')?->id;

        return [
            'total' => (clone $baseQuery)->count(),
            'open' => $openId ? (clone $baseQuery)->where('id_status_tiket', $openId)->count() : 0,
            'in_progress' => $inProgressId ? (clone $baseQuery)->where('id_status_tiket', $inProgressId)->count() : 0,
            'closed' => $closeId ? (clone $baseQuery)->where('id_status_tiket', $closeId)->count() : 0,
            'repetitive' => (clone $baseQuery)->where('berulang', true)->count(),
        ];
    }

    /**
     * Live actionable tasks for the karyawan under the current mechanism:
     * - awaiting_confirmation: admin marked resolved (siap_konfirmasi=true), user must confirm/reject
     * - awaiting_rating: Close tickets the user hasn't rated yet
     * - awaiting_repetitive: admin requested repetitive OFF, user must accept/refuse
     */
    private function userPendingTasks(int $userId): array
    {
        $closeId = StatusTiketModel::findByName('Close')?->id;

        $awaitingConfirmation = Tiket::query()
            ->with(['kategori', 'lokasi', 'statusTiket'])
            ->where('id_pengguna', $userId)
            ->where('siap_konfirmasi', true)
            ->orderByDesc('siap_konfirmasi_at')
            ->limit(20)
            ->get();

        $awaitingRating = $closeId
            ? Tiket::query()
                ->with(['kategori', 'lokasi', 'statusTiket'])
                ->where('id_pengguna', $userId)
                ->where('id_status_tiket', $closeId)
                ->whereDoesntHave('penilaian')
                ->orderByDesc('ditutup_pada')
                ->limit(20)
                ->get()
            : collect();

        $awaitingRepetitive = Tiket::query()
            ->with(['kategori', 'lokasi', 'statusTiket'])
            ->where('id_pengguna', $userId)
            ->where('repetitive_review_state', 'admin_requested_off')
            ->orderByDesc('repetitive_review_admin_at')
            ->limit(20)
            ->get();

        return [
            'awaiting_confirmation' => $awaitingConfirmation,
            'awaiting_rating' => $awaitingRating,
            'awaiting_repetitive' => $awaitingRepetitive,
        ];
    }

    /**
     * Period frequency: daily buckets when range ≤ 14 days, weekly otherwise.
     * The chart adapts naturally as the user widens the range.
     */
    private function userPeriodFrequency(int $userId, Carbon $start, Carbon $end): array
    {
        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->endOfDay()) + 1;
        $granularity = $days <= 14 ? 'daily' : 'weekly';

        if ($granularity === 'daily') {
            $rows = Tiket::query()
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as bucket, COUNT(*) as count")
                ->where('id_pengguna', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('bucket')
                ->pluck('count', 'bucket');

            $labels = [];
            $counts = [];
            $cursor = $start->copy()->startOfDay();
            while ($cursor->lte($end)) {
                $key = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('d/m');
                $counts[] = (int) ($rows[$key] ?? 0);
                $cursor->addDay();
            }
        } else {
            $rows = Tiket::query()
                ->selectRaw("DATE_FORMAT(created_at, '%x-%v') as bucket, COUNT(*) as count")
                ->where('id_pengguna', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('bucket')
                ->pluck('count', 'bucket');

            $labels = [];
            $counts = [];
            $cursor = $start->copy()->startOfWeek();
            while ($cursor->lte($end)) {
                $key = $cursor->format('o-W');
                $labels[] = $cursor->format('d M');
                $counts[] = (int) ($rows[$key] ?? 0);
                $cursor->addWeek();
            }
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
            'granularity' => $granularity,
            'total' => array_sum($counts),
        ];
    }

    private function userPeriodCategory(int $userId, Carbon $start, Carbon $end): array
    {
        $counts = Tiket::query()
            ->selectRaw('id_kategori, COUNT(*) as count')
            ->where('id_pengguna', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('id_kategori')
            ->pluck('count', 'id_kategori');

        return Kategori::orderBy('urgensi')->get()->map(function (Kategori $kategori) use ($counts) {
            return [
                'label' => $kategori->nama_kategori,
                'count' => (int) ($counts[$kategori->id] ?? 0),
                'color' => $kategori->warna_grafik,
            ];
        })
            ->filter(fn ($row) => $row['count'] > 0)  // Drop zero-count categories — donut looks cleaner
            ->values()
            ->toArray();
    }

    /**
     * SLA outcome distribution for tickets closed within the period — three distinct buckets
     * matching the `sla_outcome` enum directly: on_time, ahead, overtime.
     * Tickets without an outcome (still open) are excluded from this chart.
     */
    private function userPeriodSlaOutcome(int $userId, Carbon $start, Carbon $end): array
    {
        $row = Tiket::query()
            ->where('id_pengguna', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('sla_outcome')
            ->selectRaw("
                SUM(CASE WHEN sla_outcome = 'on_time' THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN sla_outcome = 'ahead' THEN 1 ELSE 0 END) as ahead,
                SUM(CASE WHEN sla_outcome = 'overtime' THEN 1 ELSE 0 END) as overtime
            ")
            ->first();

        return [
            'on_time' => (int) ($row->on_time ?? 0),
            'ahead' => (int) ($row->ahead ?? 0),
            'overtime' => (int) ($row->overtime ?? 0),
        ];
    }

    /**
     * Period KPIs:
     * - sla_met_pct: of closed tickets in period, what % met SLA (on_time + ahead vs overtime)
     * - avg_resolution_hours: wall-clock created→ditutup_pada for closed tickets in period
     * - avg_rating: average rating the user gave on tickets created in period
     */
    private function userPeriodKpis(int $userId, Carbon $start, Carbon $end): array
    {
        $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;

        $sla = $this->userPeriodSlaOutcome($userId, $start, $end);
        $slaResolved = $sla['on_time'] + $sla['ahead'] + $sla['overtime'];
        $slaMet = $sla['on_time'] + $sla['ahead'];
        $slaMetPct = $slaResolved > 0 ? (int) round($slaMet / $slaResolved * 100) : null;

        $avgHours = Tiket::query()
            ->where('id_pengguna', $userId)
            ->where('id_status_tiket', $closeId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('ditutup_pada')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, ditutup_pada)) as avg_hours')
            ->value('avg_hours');

        $avgRating = Penilaian::query()
            ->join('tiket', 'penilaian.id_tiket', '=', 'tiket.id')
            ->where('tiket.id_pengguna', $userId)
            ->whereBetween('tiket.created_at', [$start, $end])
            ->avg('penilaian.nilai');

        return [
            'sla_met_pct' => $slaMetPct,
            'avg_resolution_hours' => $avgHours !== null ? round((float) $avgHours, 1) : null,
            'avg_rating' => $avgRating !== null ? round((float) $avgRating, 1) : null,
        ];
    }
}

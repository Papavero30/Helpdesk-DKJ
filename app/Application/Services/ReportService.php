<?php

namespace App\Application\Services;

use App\Models\Divisi;
use App\Models\Kategori;
use App\Models\Lokasi;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReportService
{
    /**
     * Cache-version key. Bumped by Tiket/Penilaian model events on every write,
     * so cached aggregations are invalidated the moment underlying data changes
     * (the version is part of every cache key — old entries simply expire).
     */
    public const CACHE_VERSION_KEY = 'report-data-version';

    private const CACHE_TTL_MINUTES = 5;

    public static function bumpCacheVersion(): void
    {
        // increment() initialises the key to 1 when missing.
        Cache::increment(self::CACHE_VERSION_KEY);
    }

    /**
     * Cache an expensive aggregation, keyed by bucket + filters + data version.
     */
    private function remember(string $bucket, array $keyParts, \Closure $compute): mixed
    {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 0);
        $key = 'report:'.$bucket.':v'.$version.':'.md5(serialize($keyParts));

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), $compute);
    }

    /**
     * Build the base ticket query with all narrowing filters applied.
     * Filter keys (all optional): start, end (Y-m-d range), admin, category,
     * department, plant.
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Tiket::query();
        $this->applyPeriod($query, $filters);
        $this->applyNarrowingFilters($query, $filters);

        return $query;
    }

    private function applyNarrowingFilters(Builder $query, array $filters): void
    {
        // Columns are explicitly prefixed with `tiket.` — masterRows() joins
        // pengguna/karyawan/divisi/lokasi for the grouping dimension, and several
        // of those tables also have id_lokasi / id_kategori columns. Without the
        // prefix MySQL throws "Column ... is ambiguous" once a join is present.
        if (! empty($filters['admin'])) {
            $query->where('tiket.id_penanggung_jawab', (int) $filters['admin']);
        }
        if (! empty($filters['category'])) {
            $query->where('tiket.id_kategori', (int) $filters['category']);
        }
        if (! empty($filters['plant'])) {
            $query->where('tiket.id_lokasi', (int) $filters['plant']);
        }
        if (! empty($filters['department'])) {
            $deptId = (int) $filters['department'];
            $query->whereExists(function ($q) use ($deptId) {
                $q->selectRaw('1')
                    ->from('pengguna')
                    ->join('karyawan', 'pengguna.id_karyawan', '=', 'karyawan.id')
                    ->whereColumn('pengguna.id', 'tiket.id_pengguna')
                    ->where('karyawan.id_divisi', $deptId);
            });
        }
    }

    /**
     * Narrow the query to the selected Start–End date range (inclusive) on
     * tiket.created_at. Filters: 'start' and 'end' as Y-m-d strings.
     * A missing/invalid bound is simply skipped (no narrowing on that side).
     */
    private function applyPeriod(Builder $query, array $filters): void
    {
        [$start, $end] = $this->dateRange($filters);

        if ($start) {
            $query->where('tiket.created_at', '>=', $start->copy()->startOfDay());
        }
        if ($end) {
            $query->where('tiket.created_at', '<=', $end->copy()->endOfDay());
        }
    }

    /**
     * Parse the Start/End filter strings into Carbon dates.
     * Returns [?Carbon $start, ?Carbon $end]; if start is after end the two are
     * swapped so the range is always valid.
     *
     * @return array{0:?Carbon,1:?Carbon}
     */
    private function dateRange(array $filters): array
    {
        $parse = function (?string $value): ?Carbon {
            $value = trim((string) $value);
            if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return null;
            }

            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        };

        $start = $parse($filters['start'] ?? null);
        $end = $parse($filters['end'] ?? null);

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    public function masterRows(array $filters): array
    {
        return $this->remember('master', $filters, fn () => $this->computeMasterRows($filters));
    }

    private function computeMasterRows(array $filters): array
    {
        $groupBy = $filters['groupBy'] ?? 'admin';
        $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;

        $base = $this->baseQuery($filters)
            ->whereNotNull('tiket.id_penanggung_jawab');

        // Resolve label expression + joins per dimension.
        [$labelExpr, $applyJoins] = $this->dimension($groupBy);
        $applyJoins($base);

        $rows = $base
            ->selectRaw("$labelExpr as label")
            ->selectRaw('COUNT(*) as handled')
            ->selectRaw('SUM(CASE WHEN tiket.id_status_tiket = ? THEN 1 ELSE 0 END) as resolved', [$closeId])
            ->selectRaw('SUM(CASE WHEN tiket.berulang = 1 THEN 1 ELSE 0 END) as repetitive')
            ->selectRaw("SUM(CASE WHEN tiket.sla_outcome IN ('on_time','ahead') AND tiket.id_status_tiket = ? THEN 1 ELSE 0 END) as on_time", [$closeId])
            ->selectRaw("SUM(CASE WHEN tiket.sla_outcome = 'ahead' AND tiket.id_status_tiket = ? THEN 1 ELSE 0 END) as ahead", [$closeId])
            ->groupByRaw($labelExpr)
            ->orderByDesc('handled')
            ->get();

        // Avg resolution hours computed in PHP (timestamp fallback chain).
        $resolutionByLabel = $this->avgResolutionHoursByDimension($filters, $groupBy, $closeId);

        // Avg rating per dimension label (joins penilaian).
        $ratingByLabel = $this->avgRatingByDimension($filters, $groupBy);

        return $rows->map(function ($r) use ($resolutionByLabel, $ratingByLabel) {
            $handled = (int) $r->handled;
            $resolved = (int) $r->resolved;
            $label = $r->label ?? 'Unassigned Dept';

            return [
                'label' => $label,
                'handled' => $handled,
                'resolved' => $resolved,
                'resolution_rate' => $handled > 0 ? (int) round($resolved / $handled * 100) : 0,
                'repetitive' => (int) $r->repetitive,
                'repetitive_pct' => $handled > 0 ? (int) round(((int) $r->repetitive) / $handled * 100) : 0,
                'on_time' => (int) $r->on_time,
                'on_time_pct' => $resolved > 0 ? (int) round(((int) $r->on_time) / $resolved * 100) : 0,
                'ahead' => (int) $r->ahead,
                'avg_resolution_hours' => $resolutionByLabel[$label] ?? null,
                'avg_rating' => $ratingByLabel[$label] ?? null,
            ];
        })->all();
    }

    /**
     * @return array{0:string,1:callable} [labelSqlExpr, fn(Builder $q) applying needed joins]
     */
    private function dimension(string $groupBy): array
    {
        return match ($groupBy) {
            'department' => [
                "COALESCE(req_div.nama_divisi, 'Unassigned Dept')",
                function (Builder $q) {
                    $q->leftJoin('pengguna as req', 'tiket.id_pengguna', '=', 'req.id')
                        ->leftJoin('karyawan as req_kar', 'req.id_karyawan', '=', 'req_kar.id')
                        ->leftJoin('divisi as req_div', 'req_kar.id_divisi', '=', 'req_div.id');
                },
            ],
            'plant' => [
                'lok.nama_lokasi',
                function (Builder $q) {
                    $q->join('lokasi as lok', 'tiket.id_lokasi', '=', 'lok.id');
                },
            ],
            'category' => [
                'kat.nama_kategori',
                function (Builder $q) {
                    $q->join('kategori as kat', 'tiket.id_kategori', '=', 'kat.id');
                },
            ],
            'admin' => [
                'adm_kar.nama',
                function (Builder $q) {
                    $q->join('pengguna as adm', 'tiket.id_penanggung_jawab', '=', 'adm.id')
                        ->join('karyawan as adm_kar', 'adm.id_karyawan', '=', 'adm_kar.id');
                },
            ],
            default => [
                'adm_kar.nama',
                function (Builder $q) {
                    $q->join('pengguna as adm', 'tiket.id_penanggung_jawab', '=', 'adm.id')
                        ->join('karyawan as adm_kar', 'adm.id_karyawan', '=', 'adm_kar.id');
                },
            ],
        };
    }

    /**
     * Avg resolution hours per dimension label, for Close tickets only.
     * Uses waktu_selesai ?? ditutup_pada ?? updated_at as the resolution timestamp.
     */
    private function avgResolutionHoursByDimension(array $filters, string $groupBy, int $closeId): array
    {
        $base = $this->baseQuery($filters)
            ->whereNotNull('tiket.id_penanggung_jawab')
            ->where('tiket.id_status_tiket', $closeId);

        [$labelExpr, $applyJoins] = $this->dimension($groupBy);
        $applyJoins($base);

        $rows = $base
            ->selectRaw("$labelExpr as label")
            ->selectRaw('tiket.created_at, tiket.waktu_selesai, tiket.ditutup_pada, tiket.updated_at')
            ->get();

        $sums = [];
        $counts = [];
        foreach ($rows as $r) {
            $end = $r->waktu_selesai ?? $r->ditutup_pada ?? $r->updated_at;
            if (! $r->created_at || ! $end) {
                continue;
            }
            $hours = Carbon::parse($r->created_at)->diffInHours(Carbon::parse($end));
            $label = $r->label ?? 'Unassigned Dept';
            $sums[$label] = ($sums[$label] ?? 0) + $hours;
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        $avg = [];
        foreach ($sums as $label => $sum) {
            $avg[$label] = $counts[$label] > 0 ? round($sum / $counts[$label], 1) : null;
        }

        return $avg;
    }

    private function avgRatingByDimension(array $filters, string $groupBy): array
    {
        $base = $this->baseQuery($filters)
            ->whereNotNull('tiket.id_penanggung_jawab')
            ->join('penilaian', 'penilaian.id_tiket', '=', 'tiket.id');

        [$labelExpr, $applyJoins] = $this->dimension($groupBy);
        $applyJoins($base);

        return $base
            ->selectRaw("$labelExpr as label")
            ->selectRaw('AVG(penilaian.nilai) as avg_rating')
            ->groupByRaw($labelExpr)
            ->get()
            ->mapWithKeys(fn ($r) => [($r->label ?? 'Unassigned Dept') => round((float) $r->avg_rating, 1)])
            ->all();
    }

    public function chartPayload(array $filters): array
    {
        $rows = $this->masterRows($filters);
        $labels = array_column($rows, 'label');

        return [
            'handled_resolved' => [
                'labels' => $labels,
                'handled' => array_column($rows, 'handled'),
                'resolved' => array_column($rows, 'resolved'),
            ],
            'resolution_status' => [
                'labels' => $labels,
                'on_time' => array_map(
                    fn ($r) => max(0, $r['on_time'] - $r['ahead']),
                    $rows
                ),
                'ahead' => array_column($rows, 'ahead'),
                'overtime' => array_map(
                    fn ($r) => max(0, $r['resolved'] - $r['on_time']),
                    $rows
                ),
                'unresolved' => array_map(
                    fn ($r) => max(0, $r['handled'] - $r['resolved']),
                    $rows
                ),
            ],
            'repetitive_share' => [
                'labels' => $labels,
                'repetitive' => array_column($rows, 'repetitive'),
                'non_repetitive' => array_map(
                    fn ($r) => max(0, $r['handled'] - $r['repetitive']),
                    $rows
                ),
            ],
            'trend' => $this->trendSeries($filters),
        ];
    }

    /**
     * Created vs resolved across the selected Start–End range.
     *
     * The X-axis bucket size is chosen automatically from the range length:
     *   ≤ 31 days  → per day
     *   ≤ 366 days → per month
     *   longer     → per year
     *
     * Created tickets are bucketed by created_at; resolved (Close) tickets by
     * their resolution timestamp (ditutup_pada). Each series is ONE GROUP BY query.
     *
     * Public so callers that only need the trend (e.g. the dashboard's "My
     * Performance" section) can fetch it directly without the full chartPayload()
     * (which would re-run masterRows + its rating/resolution sub-queries). Ignores
     * the `groupBy` key — only the narrowing filters + start/end range matter.
     */
    public function trendSeries(array $filters): array
    {
        return $this->remember('trend', $filters, fn () => $this->computeTrendSeries($filters));
    }

    private function computeTrendSeries(array $filters): array
    {
        $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;

        // Resolve the range; fall back to the current month if unset.
        [$start, $end] = $this->dateRange($filters);
        $start = ($start ?: now()->startOfMonth())->copy()->startOfDay();
        $end = ($end ?: now())->copy()->endOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $days = $start->diffInDays($end) + 1;

        // Pick the bucket unit + the SQL date-format expression + bucket labels.
        if ($days <= 31) {
            $unit = 'day';
            $sqlFmt = fn (string $col) => "DATE_FORMAT($col, '%Y-%m-%d')";
            $period = CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end);
            $keyFmt = 'Y-m-d';
            $labelFmt = 'd M';
        } elseif ($days <= 366) {
            $unit = 'month';
            $sqlFmt = fn (string $col) => "DATE_FORMAT($col, '%Y-%m')";
            $period = CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end);
            $keyFmt = 'Y-m';
            $labelFmt = 'M Y';
        } else {
            $unit = 'year';
            $sqlFmt = fn (string $col) => "DATE_FORMAT($col, '%Y')";
            $period = CarbonPeriod::create($start->copy()->startOfYear(), '1 year', $end);
            $keyFmt = 'Y';
            $labelFmt = 'Y';
        }

        // Build the ordered list of bucket keys + human labels.
        $keys = [];
        $labels = [];
        foreach ($period as $point) {
            $keys[] = $point->format($keyFmt);
            $labels[] = $point->format($labelFmt);
        }

        // Created series — bucket by created_at within the range.
        $createdQuery = Tiket::query();
        $this->applyNarrowingFilters($createdQuery, $filters);
        $createdRaw = $createdQuery
            ->whereBetween('tiket.created_at', [$start, $end])
            ->selectRaw($sqlFmt('tiket.created_at').' as bucket, COUNT(*) as cnt')
            ->groupByRaw($sqlFmt('tiket.created_at'))
            ->pluck('cnt', 'bucket')
            ->all();

        // Resolved series — Close tickets bucketed by ditutup_pada within the range.
        $resolvedQuery = Tiket::query();
        $this->applyNarrowingFilters($resolvedQuery, $filters);
        $resolvedRaw = $resolvedQuery
            ->where('tiket.id_status_tiket', $closeId)
            ->whereNotNull('tiket.ditutup_pada')
            ->whereBetween('tiket.ditutup_pada', [$start, $end])
            ->selectRaw($sqlFmt('tiket.ditutup_pada').' as bucket, COUNT(*) as cnt')
            ->groupByRaw($sqlFmt('tiket.ditutup_pada'))
            ->pluck('cnt', 'bucket')
            ->all();

        $created = [];
        $resolved = [];
        foreach ($keys as $k) {
            $created[] = (int) ($createdRaw[$k] ?? 0);
            $resolved[] = (int) ($resolvedRaw[$k] ?? 0);
        }

        return compact('labels', 'created', 'resolved', 'unit');
    }

    public function totals(array $filters, ?array $rows = null): array
    {
        $rows ??= $this->masterRows($filters);

        $handled = array_sum(array_column($rows, 'handled'));
        $resolved = array_sum(array_column($rows, 'resolved'));
        $repetitive = array_sum(array_column($rows, 'repetitive'));
        $onTime = array_sum(array_column($rows, 'on_time'));

        return [
            'handled' => $handled,
            'resolved' => $resolved,
            'resolution_rate' => $handled > 0 ? (int) round($resolved / $handled * 100) : 0,
            'repetitive' => $repetitive,
            'repetitive_pct' => $handled > 0 ? (int) round($repetitive / $handled * 100) : 0,
            'on_time' => $onTime,
            'on_time_pct' => $resolved > 0 ? (int) round($onTime / $resolved * 100) : 0,
            'admins_count' => count($rows),
        ];
    }

    public function filterOptions(): array
    {
        $admins = User::query()
            ->where('pengguna.peran', 'admin')
            ->join('karyawan', 'pengguna.id_karyawan', '=', 'karyawan.id')
            ->orderBy('karyawan.nama')
            ->get(['pengguna.id', 'karyawan.nama'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->nama])
            ->all();

        return [
            'admins' => $admins,
            'categories' => Kategori::orderBy('urgensi')->get(['id', 'nama_kategori'])
                ->map(fn ($k) => ['value' => $k->id, 'label' => $k->nama_kategori])->all(),
            'departments' => Divisi::orderBy('nama_divisi')->get(['id', 'nama_divisi'])
                ->map(fn ($d) => ['value' => $d->id, 'label' => $d->nama_divisi])->all(),
            'plants' => Lokasi::orderBy('nama_lokasi')->get(['id', 'nama_lokasi'])
                ->map(fn ($l) => ['value' => $l->id, 'label' => $l->nama_lokasi])->all(),
        ];
    }

    /**
     * Returns the paginated list of individual tickets that make up one grouping
     * bucket in masterRows() — for the drill-down modal.
     *
     * @return array{total:int,totalPages:int,page:int,tickets:Collection}
     */
    public function detailTickets(array $filters, string $dimension, string $dimensionValue, int $page = 1, int $perPage = 10): array
    {
        $query = $this->baseQuery($filters)
            ->whereNotNull('tiket.id_penanggung_jawab');

        $resolved = $this->resolveDimensionConstraint($query, $dimension, $dimensionValue);
        if (! $resolved) {
            return ['total' => 0, 'totalPages' => 0, 'page' => 1, 'tickets' => collect()];
        }

        $total = (clone $query)->count('tiket.id');
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $page = max(1, min($page, max(1, $totalPages)));

        $tickets = $query
            ->with(['kategori', 'lokasi', 'statusTiket', 'user.karyawan'])
            ->select('tiket.*')
            ->orderByDesc('tiket.created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return compact('total', 'totalPages', 'page', 'tickets');
    }

    /**
     * Narrow the given query to one specific bucket of the chosen dimension.
     * Returns false if the label cannot be resolved (deleted entity / typo).
     */
    private function resolveDimensionConstraint(Builder $query, string $dimension, string $value): bool
    {
        switch ($dimension) {
            case 'admin':
                $adminId = User::query()
                    ->join('karyawan', 'pengguna.id_karyawan', '=', 'karyawan.id')
                    ->where('pengguna.peran', 'admin')
                    ->where('karyawan.nama', $value)
                    ->value('pengguna.id');
                if (! $adminId) {
                    return false;
                }
                $query->where('tiket.id_penanggung_jawab', $adminId);

                return true;

            case 'category':
                $catId = Kategori::where('nama_kategori', $value)->value('id');
                if (! $catId) {
                    return false;
                }
                $query->where('tiket.id_kategori', $catId);

                return true;

            case 'plant':
                $plantId = Lokasi::where('nama_lokasi', $value)->value('id');
                if (! $plantId) {
                    return false;
                }
                $query->where('tiket.id_lokasi', $plantId);

                return true;

            case 'department':
                if ($value === 'Unassigned Dept') {
                    $query->whereExists(function ($q) {
                        $q->selectRaw('1')
                            ->from('pengguna')
                            ->leftJoin('karyawan', 'pengguna.id_karyawan', '=', 'karyawan.id')
                            ->whereColumn('pengguna.id', 'tiket.id_pengguna')
                            ->whereNull('karyawan.id_divisi');
                    });

                    return true;
                }
                $deptId = Divisi::where('nama_divisi', $value)->value('id');
                if (! $deptId) {
                    return false;
                }
                $query->whereExists(function ($q) use ($deptId) {
                    $q->selectRaw('1')
                        ->from('pengguna')
                        ->join('karyawan', 'pengguna.id_karyawan', '=', 'karyawan.id')
                        ->whereColumn('pengguna.id', 'tiket.id_pengguna')
                        ->where('karyawan.id_divisi', $deptId);
                });

                return true;

            default:
                return false;
        }
    }
}

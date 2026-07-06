<?php

namespace App\Application\Services;

use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    /** Daily-rhythm counts: created today, resolved today, awaiting user confirmation. */
    public function todaySnapshot(): array
    {
        $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;

        return [
            'created_today' => Tiket::whereDate('created_at', today())->count(),
            'resolved_today' => Tiket::where('id_status_tiket', $closeId)
                ->whereNotNull('ditutup_pada')
                ->whereDate('ditutup_pada', today())
                ->count(),
            'awaiting_confirmation' => Tiket::where('siap_konfirmasi', true)->count(),
        ];
    }

    /**
     * Paginated list of the tickets behind a "today" snapshot metric, for the
     * card drill-down modal. $metric: 'created' | 'resolved' | 'awaiting'.
     * Filters mirror todaySnapshot() exactly so the modal total matches the card.
     *
     * @return array{total:int,totalPages:int,page:int,tickets:Collection}
     */
    public function todayTickets(string $metric, int $page = 1, int $perPage = 10): array
    {
        $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;

        $query = match ($metric) {
            'created' => Tiket::query()->whereDate('created_at', today()),
            'resolved' => Tiket::query()
                ->where('id_status_tiket', $closeId)
                ->whereNotNull('ditutup_pada')
                ->whereDate('ditutup_pada', today()),
            'awaiting' => Tiket::query()->where('siap_konfirmasi', true),
            default => null,
        };

        if ($query === null) {
            return ['total' => 0, 'totalPages' => 0, 'page' => 1, 'tickets' => collect()];
        }

        $total = (clone $query)->count();
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $page = max(1, min($page, max(1, $totalPages)));

        $tickets = $query
            ->with(['kategori', 'lokasi', 'statusTiket', 'user.karyawan'])
            ->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return compact('total', 'totalPages', 'page', 'tickets');
    }

    /**
     * Personal analytics for one admin over a Start–End date range — reuses
     * ReportService filtered to that admin so the numbers match the Report page.
     *
     * @return array{kpis:array,categories:array,trend:array}
     */
    public function myPerformance(int $adminId, string $start, string $end): array
    {
        $base = ['admin' => $adminId, 'start' => $start, 'end' => $end];

        $adminRows = $this->reportService->masterRows($base + ['groupBy' => 'admin']);
        $kpis = $adminRows[0] ?? [
            'label' => '', 'handled' => 0, 'resolved' => 0, 'resolution_rate' => 0,
            'repetitive' => 0, 'repetitive_pct' => 0, 'on_time' => 0, 'on_time_pct' => 0,
            'avg_resolution_hours' => null, 'avg_rating' => null,
        ];

        $categories = $this->reportService->masterRows($base + ['groupBy' => 'category']);

        // trendSeries directly (not chartPayload) — chartPayload would re-run
        // masterRows + its rating/resolution sub-queries only to discard them.
        $trend = $this->reportService->trendSeries($base);

        return compact('kpis', 'categories', 'trend');
    }

    /**
     * Tickets at SLA risk: not Close, with a target deadline overdue (past now)
     * or approaching (within $withinHours). Each list capped at $limit.
     *
     * When $adminId is provided, scope to tickets assigned to that admin only
     * (personal SLA risk — each admin sees only their own tickets).
     *
     * @return array{overdue:Collection,approaching:Collection,total:int}
     */
    public function slaAtRisk(int $withinHours = 6, int $limit = 6, ?int $adminId = null): array
    {
        $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;
        $now = now();
        $threshold = now()->addHours($withinHours);

        $base = fn () => Tiket::query()
            ->where('id_status_tiket', '!=', $closeId)
            ->whereNotNull('target_penyelesaian')
            ->when($adminId !== null, fn ($q) => $q->where('id_penanggung_jawab', $adminId))
            ->with(['kategori', 'lokasi', 'statusTiket', 'user.karyawan']);

        return [
            'overdue' => $base()
                ->whereNull('sla_paused_at')
                ->whereRaw('DATE_ADD(target_penyelesaian, INTERVAL COALESCE(sla_paused_total_seconds, 0) SECOND) < ?', [$now])
                ->orderBy('target_penyelesaian')
                ->limit($limit)
                ->get(),
            'approaching' => $base()
                ->whereNull('sla_paused_at')
                ->whereRaw('DATE_ADD(target_penyelesaian, INTERVAL COALESCE(sla_paused_total_seconds, 0) SECOND) BETWEEN ? AND ?', [$now, $threshold])
                ->orderBy('target_penyelesaian')
                ->limit($limit)
                ->get(),
            'total' => $base()
                ->whereNull('sla_paused_at')
                ->whereRaw('DATE_ADD(target_penyelesaian, INTERVAL COALESCE(sla_paused_total_seconds, 0) SECOND) < ?', [$threshold])
                ->count(),
        ];
    }

    /** Tickets assigned to this admin that are still open (Open / In Progress). */
    public function myQueue(int $adminId, int $limit = 6): array
    {
        $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;

        $base = fn () => Tiket::query()
            ->where('id_penanggung_jawab', $adminId)
            ->where('id_status_tiket', '!=', $closeId);

        return [
            'tickets' => $base()
                ->with(['kategori', 'lokasi', 'statusTiket', 'user.karyawan'])
                ->orderByRaw('target_penyelesaian IS NULL, target_penyelesaian ASC')
                ->limit($limit)
                ->get(),
            'total' => $base()->count(),
        ];
    }

    /**
     * Unassigned Open tickets waiting to be picked up, oldest first (urgensi as tiebreak).
     *
     * When $lokasiId is provided, scope to tickets at that plant/location only
     * (recommendations stay within the admin's own plant — Gresik admin only sees Gresik tickets).
     */
    public function pickupQueue(int $limit = 6, ?int $lokasiId = null): array
    {
        $openId = StatusTiketModel::findByName('Open')?->id ?? 0;

        $base = fn () => Tiket::query()
            ->whereNull('id_penanggung_jawab')
            ->where('id_status_tiket', $openId)
            ->when($lokasiId !== null, fn ($q) => $q->where('tiket.id_lokasi', $lokasiId));

        return [
            'tickets' => $base()
                ->leftJoin('kategori', 'tiket.id_kategori', '=', 'kategori.id')
                ->with(['kategori', 'lokasi', 'user.karyawan', 'statusTiket'])
                ->orderBy('tiket.created_at', 'asc')
                ->orderByRaw('COALESCE(kategori.urgensi, 999) ASC')
                ->select('tiket.*')
                ->limit($limit)
                ->get(),
            'total' => $base()->count(),
        ];
    }

    /**
     * Live workload per admin (peran=admin): active (non-Close assigned) tickets,
     * with overdue and awaiting-confirmation subcounts. Most-loaded admin first.
     *
     * @return Collection<int, array{admin_id:int,nama:string,plant:string,active:int,on_track:int,overdue:int,paused:int,awaiting:int}>
     */
    public function managerWorkload(): Collection
    {
        $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;
        $now = now();

        return User::where('peran', 'admin')
            ->with('karyawan.lokasi')
            ->get()
            ->map(function (User $admin) use ($closeId, $now) {
                $base = fn () => Tiket::where('id_penanggung_jawab', $admin->id)
                    ->where('id_status_tiket', '!=', $closeId);

                return [
                    'admin_id' => $admin->id,
                    'nama' => $admin->karyawan?->nama ?? '—',
                    'plant' => $admin->karyawan?->lokasi?->nama_lokasi ?? '—',
                    'active' => $base()->count(),
                    'on_track' => $base()->whereNull('sla_paused_at')->whereNotNull('target_penyelesaian')
                        ->whereRaw('DATE_ADD(target_penyelesaian, INTERVAL COALESCE(sla_paused_total_seconds, 0) SECOND) >= ?', [$now])->count(),
                    'overdue' => $base()->whereNull('sla_paused_at')->whereNotNull('target_penyelesaian')
                        ->whereRaw('DATE_ADD(target_penyelesaian, INTERVAL COALESCE(sla_paused_total_seconds, 0) SECOND) < ?', [$now])->count(),
                    'paused' => $base()->whereNotNull('sla_paused_at')->count(),
                    'awaiting' => $base()->where('siap_konfirmasi', true)->count(),
                ];
            })
            ->sortByDesc('active')
            ->values();
    }

    /** Repetitive tickets where the user refused — waiting for the admin to respond. */
    public function repetitiveNeedingAction(int $limit = 6): array
    {
        $base = fn () => Tiket::query()
            ->where('repetitive_review_state', 'user_refused');

        return [
            'tickets' => $base()
                ->with(['kategori', 'lokasi', 'user.karyawan'])
                ->orderByDesc('repetitive_review_user_at')
                ->limit($limit)
                ->get(),
            'total' => $base()->count(),
        ];
    }
}

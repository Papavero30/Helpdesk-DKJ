<?php

namespace App\Livewire;

use App\Application\Services\AdminDashboardService;
use App\Application\Services\ReportService;
use App\Application\Services\StatistikService;
use App\Models\Kategori;
use App\Models\Penilaian;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use App\Support\BreadcrumbStack;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Dashboard', 'description' => 'Summary and analytics for your support tickets'])]
class Dashboard extends Component
{
    use WithPagination;

    public string $filterKategori = '';

    public string $filterStatus = '';

    // Admin "Today" card drill-down modal.
    public bool $showTodayModal = false;

    public ?string $todayMetric = null;

    public int $todayPage = 1;

    public const TODAY_PER_PAGE = 10;

    // Admin "My Performance" personal-analytics date range (default: last 30 days).
    public string $myPerfStart = '';

    public string $myPerfEnd = '';

    public array $myPerfChartPayload = [];

    // Karyawan "My Activity" period date range (default: last 30 days).
    public string $myActivityStart = '';

    public string $myActivityEnd = '';

    public array $activityChartPayload = [];

    // Manager "Organization Overview" date range (default: last 30 days).
    public string $orgStart = '';

    public string $orgEnd = '';

    public array $orgChartPayload = [];

    // Karyawan status-card drill-down modal (table of tickets matching a status key)
    public bool $showStatusModal = false;

    public ?string $statusModalKey = null;  // total | open | in_progress | close

    public int $statusModalPage = 1;

    public const STATUS_MODAL_PER_PAGE = 10;

    public function mount(): void
    {
        BreadcrumbStack::reset();

        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        if ($this->myPerfStart === '') {
            $this->myPerfStart = now()->subDays(29)->format('Y-m-d');
        }
        if ($this->myPerfEnd === '') {
            $this->myPerfEnd = now()->format('Y-m-d');
        }

        if ($this->myActivityStart === '') {
            $this->myActivityStart = now()->subDays(29)->format('Y-m-d');
        }
        if ($this->myActivityEnd === '') {
            $this->myActivityEnd = now()->format('Y-m-d');
        }

        if ($this->orgStart === '') {
            $this->orgStart = now()->subDays(29)->format('Y-m-d');
        }
        if ($this->orgEnd === '') {
            $this->orgEnd = now()->format('Y-m-d');
        }

        $sessionKey = "dashboard_cache_guarded_{$user->id}";
        if (! session()->has($sessionKey)) {
            Cache::forget("karyawan-live-{$user->id}");
            Cache::forget("karyawan-dashboard-{$user->id}");  // legacy key from pre-rewrite, defensive
            session()->put($sessionKey, true);
        }
    }

    /** Karyawan Tier 2 tabel filters reset pagination so the user lands on page 1. */
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKategori(): void
    {
        $this->resetPage();
    }

    /** Karyawan date-range updates: validate Y-m-d and reset pagination (range changes Tier 2 dataset). */
    public function updateMyActivityStart(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            $this->myActivityStart = $value;
            $this->resetPage();
        }
    }

    public function updateMyActivityEnd(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            $this->myActivityEnd = $value;
            $this->resetPage();
        }
    }

    public function updateOrgStart(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            $this->orgStart = $value;
        }
    }

    public function updateOrgEnd(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            $this->orgEnd = $value;
        }
    }

    /** Open the karyawan status-card drill-down modal (Total / Open / In Progress / Close). */
    public function openStatusModal(string $key): void
    {
        if (! in_array($key, ['total', 'open', 'in_progress', 'close'], true)) {
            return;
        }
        $this->statusModalKey = $key;
        $this->statusModalPage = 1;
        $this->showStatusModal = true;
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
        $this->statusModalKey = null;
        $this->statusModalPage = 1;
    }

    public function statusModalNext(): void
    {
        $this->statusModalPage++;
    }

    public function statusModalPrev(): void
    {
        $this->statusModalPage = max(1, $this->statusModalPage - 1);
    }

    public function statusGoToPage(int $page): void
    {
        $this->statusModalPage = max(1, $page);
    }

    /** Open the admin "Today" drill-down modal for a metric: created|resolved|awaiting. */
    public function openTodayModal(string $metric): void
    {
        if (! in_array($metric, ['created', 'resolved', 'awaiting'], true)) {
            return;
        }
        $this->todayMetric = $metric;
        $this->todayPage = 1;
        $this->showTodayModal = true;
    }

    /** Close the admin "Today" drill-down modal and clear its state. */
    public function closeTodayModal(): void
    {
        $this->showTodayModal = false;
        $this->todayMetric = null;
        $this->todayPage = 1;
    }

    public function todayPageNext(): void
    {
        $this->todayPage++;
    }

    public function todayPagePrev(): void
    {
        $this->todayPage = max(1, $this->todayPage - 1);
    }

    public function todayGoToPage(int $page): void
    {
        $this->todayPage = max(1, $page);
    }

    public function updateMyPerfStart(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            $this->myPerfStart = $value;
        }
    }

    public function updateMyPerfEnd(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            $this->myPerfEnd = $value;
        }
    }

    public function render(StatistikService $statistikService, AdminDashboardService $adminDashboardService, ReportService $reportService)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isAdmin()) {
            $todayModal = ['total' => 0, 'totalPages' => 0, 'page' => 1, 'tickets' => collect()];
            if ($this->showTodayModal && $this->todayMetric !== null) {
                $todayModal = $adminDashboardService->todayTickets(
                    $this->todayMetric,
                    $this->todayPage,
                    self::TODAY_PER_PAGE,
                );
                // Snap the component page back to the service-clamped page when the
                // group has data, so todayPage can't drift past the last page if the
                // data shrank while the modal was open. Guarded on totalPages > 0 so
                // an empty group doesn't undo a deliberate page increment (matches
                // the Report drill-down modal's pattern).
                if ($todayModal['totalPages'] > 0) {
                    $this->todayPage = $todayModal['page'];
                }
            }

            $perf = $adminDashboardService->myPerformance($user->id, $this->myPerfStart, $this->myPerfEnd);
            $this->myPerfChartPayload = [
                'trend' => $perf['trend'],
                'categories' => [
                    'labels' => array_column($perf['categories'], 'label'),
                    'handled' => array_column($perf['categories'], 'handled'),
                ],
            ];

            return view('livewire.admin.panel', [
                'today' => $adminDashboardService->todaySnapshot(),
                // Personal: only tickets assigned to this admin (was global across all admins)
                'slaAtRisk' => $adminDashboardService->slaAtRisk(6, 6, $user->id),
                'myQueue' => $adminDashboardService->myQueue($user->id, 6),
                // Plant-scoped: only tickets in the admin's own plant/lokasi.
                // Null (admin without karyawan/lokasi) falls back to no plant filter — permissive default.
                'pickupQueue' => $adminDashboardService->pickupQueue(6, $user->karyawan?->id_lokasi),
                'repetitive' => $adminDashboardService->repetitiveNeedingAction(6),
                'todayModal' => $todayModal,
                'myPerf' => $perf,
            ]);
        }

        if ($user->isManager()) {
            $rangeStart = Carbon::parse($this->orgStart)->startOfDay();
            $rangeEnd = Carbon::parse($this->orgEnd)->endOfDay();
            $base = ['start' => $this->orgStart, 'end' => $this->orgEnd];

            $totals = $reportService->totals($base);
            $plantRows = $reportService->masterRows($base + ['groupBy' => 'plant']);
            $categoryRows = $reportService->masterRows($base + ['groupBy' => 'category']);
            $leaderboard = $reportService->masterRows($base + ['groupBy' => 'admin']);

            $this->orgChartPayload = [
                'plant' => [
                    'labels' => array_column($plantRows, 'label'),
                    'handled' => array_column($plantRows, 'handled'),
                    'resolved' => array_column($plantRows, 'resolved'),
                ],
                'category' => [
                    'labels' => array_column($categoryRows, 'label'),
                    'handled' => array_column($categoryRows, 'handled'),
                ],
            ];

            // KPIs not provided by totals(): org-wide avg rating + avg resolution hours (period-scoped).
            $avgRating = Penilaian::query()
                ->join('tiket', 'penilaian.id_tiket', '=', 'tiket.id')
                ->whereBetween('tiket.created_at', [$rangeStart, $rangeEnd])
                ->avg('penilaian.nilai');

            $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;
            $avgHours = Tiket::where('id_status_tiket', $closeId)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->whereNotNull('ditutup_pada')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, ditutup_pada)) as a')
                ->value('a');

            $openId = StatusTiketModel::findByName('Open')?->id ?? 0;
            $live = [
                'open' => Tiket::where('id_status_tiket', $openId)->count(),
                'unassigned' => Tiket::where('id_status_tiket', $openId)->whereNull('id_penanggung_jawab')->count(),
                'awaiting' => Tiket::where('siap_konfirmasi', true)->count(),
            ];

            return view('livewire.manager.dashboard', [
                'totals' => $totals,
                'leaderboard' => $leaderboard,
                'live' => $live,
                'avgRating' => $avgRating !== null ? round((float) $avgRating, 1) : null,
                'avgResolutionHours' => $avgHours !== null ? round((float) $avgHours, 1) : null,
            ]);
        }

        // Tier 1: live operational snapshot (status counts + actionable pending)
        $live = $statistikService->karyawanLiveSummary($user->id);

        // Tier 2: period analytics (date-range scoped)
        $rangeStart = Carbon::parse($this->myActivityStart);
        $rangeEnd = Carbon::parse($this->myActivityEnd);
        $activity = $statistikService->karyawanActivity($user->id, $rangeStart, $rangeEnd);

        $this->activityChartPayload = [
            'frequency' => $activity['frequency'],
            'kategori' => $activity['kategori'],
            'sla_outcome' => $activity['sla_outcome'],
        ];

        // Period ticket table — filtered by created_at in range + optional status/kategori
        $ticketsQuery = Tiket::query()
            ->where('id_pengguna', $user->id)
            ->whereBetween('created_at', [
                $rangeStart->copy()->startOfDay(),
                $rangeEnd->copy()->endOfDay(),
            ])
            ->with(['kategori', 'statusTiket', 'assignedAdmin.karyawan', 'penilaian']);

        if ($this->filterStatus !== '') {
            $ticketsQuery->where('id_status_tiket', $this->filterStatus);
        }
        if ($this->filterKategori !== '') {
            $ticketsQuery->where('id_kategori', $this->filterKategori);
        }

        // Status-card drill-down modal payload (built only when the modal is open)
        $statusModal = ['total' => 0, 'totalPages' => 0, 'page' => 1, 'tickets' => collect()];
        if ($this->showStatusModal && $this->statusModalKey !== null) {
            $modalQuery = Tiket::query()
                ->where('id_pengguna', $user->id)
                ->with(['kategori', 'statusTiket', 'assignedAdmin.karyawan', 'penilaian']);

            $modalStatusName = match ($this->statusModalKey) {
                'open' => 'Open',
                'in_progress' => 'In Progress',
                'close' => 'Close',
                default => null,  // 'total' → no status filter
            };
            if ($modalStatusName) {
                $modalQuery->whereHas('statusTiket', fn ($q) => $q->where('nama_status', $modalStatusName));
            }

            $modalTotal = (clone $modalQuery)->count();
            $modalTotalPages = $modalTotal > 0 ? (int) ceil($modalTotal / self::STATUS_MODAL_PER_PAGE) : 0;
            $modalPage = max(1, min($this->statusModalPage, max(1, $modalTotalPages)));

            $modalTickets = $modalQuery
                ->orderByDesc('created_at')
                ->skip(($modalPage - 1) * self::STATUS_MODAL_PER_PAGE)
                ->take(self::STATUS_MODAL_PER_PAGE)
                ->get();

            $statusModal = [
                'total' => $modalTotal,
                'totalPages' => $modalTotalPages,
                'page' => $modalPage,
                'tickets' => $modalTickets,
            ];

            // Snap component page to service-clamped page when the group has data
            if ($modalTotalPages > 0) {
                $this->statusModalPage = $modalPage;
            }
        }

        return view('livewire.dashboard', [
            'summary' => $live['summary'],
            'pending' => $live['pending'],
            'kpi' => $activity['kpi'],
            'frequencyData' => $activity['frequency'],
            'kategoriData' => $activity['kategori'],
            'slaOutcomeData' => $activity['sla_outcome'],
            'tickets' => $ticketsQuery->orderByDesc('created_at')->paginate(10),
            'statusModal' => $statusModal,
            'categories' => Kategori::orderBy('nama_kategori')->get(),
            'statuses' => StatusTiketModel::orderBy('id')->get(),
            'statusIds' => [
                'open' => StatusTiketModel::findByName('Open')?->id,
                'in_progress' => StatusTiketModel::findByName('In Progress')?->id,
                'close' => StatusTiketModel::findByName('Close')?->id,
            ],
            'isAdmin' => false,
        ]);
    }
}

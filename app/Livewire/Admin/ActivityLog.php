<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog as ActivityLogModel;
use App\Models\Lokasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Activity Log'])]
class ActivityLog extends Component
{
    #[Url(as: 'tab', except: 'all')]
    public string $activeTab = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public array $filterAksi = [];

    public array $filterLokasis = [];

    public ?string $dateMode = null;

    public ?string $dateExact = null;

    public ?string $dateStart = null;

    public ?string $dateEnd = null;

    public string $sortBy = 'created_desc';

    public int $jumlahTampil = 50;

    // Summary card drill-down modal
    public bool $showSummaryModal = false;

    public ?string $summaryModal = null;    // 'total' | 'today' | 'new_tickets' | 'status_changes' | null

    public int $summaryPage = 1;

    public const SUMMARY_PER_PAGE = 10;

    /** Map tab → array of `aksi` values that belong to it. Tickets tab handled separately (lifecycle only). */
    private const TAB_AKSI = [
        'tickets' => ['ticket_created', 'ticket_assigned', 'status_changed'],
        'comments' => ['reply_posted', 'comment_added'],
        'feedback' => ['feedback_solved', 'feedback_not_resolved', 'ticket_rated', 'rating_submitted'],
        'repetitive' => ['repetitive_off_requested', 'repetitive_off_accepted', 'repetitive_off_refused', 'repetitive_off_cancelled', 'repetitive_off_auto_accepted'],
    ];

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            $this->redirect('/');
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->jumlahTampil = 50;
    }

    public function muatLebihBanyak(): void
    {
        $this->jumlahTampil += 50;
    }

    public function resetFilters(): void
    {
        $this->filterAksi = [];
        $this->filterLokasis = [];
        $this->dateMode = null;
        $this->dateExact = null;
        $this->dateStart = null;
        $this->dateEnd = null;
        $this->dispatch('filters-reset');
    }

    public function resetSort(): void
    {
        $this->sortBy = 'created_desc';
        $this->dispatch('sort-reset');
    }

    public function applyFilters(): void {}

    public function applySort(): void {}

    private function applyBaseFilters(Builder $query): Builder
    {
        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('keterangan', 'like', $term)
                    ->orWhereHas('tiket', fn ($t) => $t->where('id', 'like', $term)->orWhere('deskripsi', 'like', $term))
                    ->orWhereHas('user.karyawan', fn ($u) => $u->where('nama', 'like', $term));
            });
        }

        if (! empty($this->filterAksi)) {
            $query->whereIn('aksi', $this->filterAksi);
        }

        if (! empty($this->filterLokasis)) {
            $query->whereHas('tiket', fn ($t) => $t->whereIn('id_lokasi', $this->filterLokasis));
        }

        if ($this->dateMode === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($this->dateMode === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek());
        } elseif ($this->dateMode === 'month') {
            $query->where('created_at', '>=', now()->startOfMonth());
        } elseif ($this->dateMode === 'exact' && $this->dateExact) {
            $query->whereDate('created_at', $this->dateExact);
        } elseif ($this->dateMode === 'range' && $this->dateStart && $this->dateEnd) {
            $query->whereBetween('created_at', [
                $this->dateStart.' 00:00:00',
                $this->dateEnd.' 23:59:59',
            ]);
        }

        return $query;
    }

    private function applyTabFilter(Builder $query, string $tab): Builder
    {
        if ($tab === 'configuration') {
            return $query->whereNull('tiket_id');
        }

        if ($tab === 'all' || ! isset(self::TAB_AKSI[$tab])) {
            return $query;
        }

        if ($tab === 'tickets') {
            // Tickets tab = ticket lifecycle only: created → assigned → closed.
            // Exclude status_changed yang sebenarnya repetitive-validation atau feedback-awaiting
            // (hanya status_changed dengan status_baru = 'Close' yang dianggap lifecycle).
            $query->where(function ($q) {
                $q->whereIn('aksi', ['ticket_created', 'ticket_assigned'])
                    ->orWhere(function ($qq) {
                        $qq->where('aksi', 'status_changed')->where('status_baru', 'Close');
                    });
            });

            return $query;
        }

        $query->whereIn('aksi', self::TAB_AKSI[$tab]);

        return $query;
    }

    private function applySortToQuery(Builder $query): Builder
    {
        match ($this->sortBy) {
            'created_asc' => $query->orderBy('created_at', 'asc'),
            'aksi_asc' => $query->orderBy('aksi', 'asc'),
            'aksi_desc' => $query->orderBy('aksi', 'desc'),
            'tiket_asc' => $query->orderBy('tiket_id', 'asc'),
            'tiket_desc' => $query->orderBy('tiket_id', 'desc'),
            'actor_asc',
            'actor_desc' => $query
                ->leftJoin('pengguna as actor_user', 'activity_log.id_pengguna', '=', 'actor_user.id')
                ->leftJoin('karyawan as actor_kar', 'actor_user.id_karyawan', '=', 'actor_kar.id')
                ->orderByRaw('actor_kar.nama IS NULL, actor_kar.nama '.($this->sortBy === 'actor_asc' ? 'asc' : 'desc'))
                ->select('activity_log.*'),
            default => $query->orderBy('created_at', 'desc'),
        };

        return $query;
    }

    protected function buildTabCounts(): array
    {
        $base = $this->applyBaseFilters(ActivityLogModel::query());

        $counts = ['all' => (clone $base)->count()];
        foreach (array_keys(self::TAB_AKSI) as $tab) {
            $counts[$tab] = $this->applyTabFilter(clone $base, $tab)->count();
        }
        $counts['configuration'] = (clone $base)->whereNull('tiket_id')->count();

        return $counts;
    }

    public function openSummaryModal(string $type): void
    {
        if (! in_array($type, ['total', 'today', 'new_tickets', 'status_changes'], true)) {
            return;
        }
        $this->summaryModal = $type;
        $this->summaryPage = 1;
        $this->showSummaryModal = true;
    }

    public function closeSummaryModal(): void
    {
        $this->showSummaryModal = false;
        $this->summaryModal = null;
        $this->summaryPage = 1;
    }

    public function summaryPageNext(): void
    {
        $this->summaryPage++;
    }

    public function summaryPagePrev(): void
    {
        $this->summaryPage = max(1, $this->summaryPage - 1);
    }

    public function summaryGoToPage(int $page): void
    {
        $this->summaryPage = max(1, $page);
    }

    /**
     * Build query untuk modal drill-down sesuai metric card yang diklik.
     */
    private function summaryModalQuery(): ?Builder
    {
        return match ($this->summaryModal) {
            'total' => ActivityLogModel::query()->with(['tiket.user.karyawan', 'user.karyawan'])->orderByDesc('created_at'),
            'today' => ActivityLogModel::query()->with(['tiket.user.karyawan', 'user.karyawan'])->whereDate('created_at', today())->orderByDesc('created_at'),
            'new_tickets' => ActivityLogModel::query()->with(['tiket.user.karyawan', 'user.karyawan'])->where('aksi', 'ticket_created')->whereDate('created_at', today())->orderByDesc('created_at'),
            'status_changes' => ActivityLogModel::query()->with(['tiket.user.karyawan', 'user.karyawan'])->where('aksi', 'status_changed')->whereDate('created_at', today())->orderByDesc('created_at'),
            default => null,
        };
    }

    public function render()
    {
        $query = ActivityLogModel::with(['tiket.user.karyawan', 'user.karyawan']);

        $this->applyBaseFilters($query);
        $this->applyTabFilter($query, $this->activeTab);
        $this->applySortToQuery($query);

        $logs = $query->limit($this->jumlahTampil)->get();

        $ringkasan = [
            'total' => ActivityLogModel::count(),
            'hari_ini' => ActivityLogModel::whereDate('created_at', today())->count(),
            'tiket_baru' => ActivityLogModel::where('aksi', 'ticket_created')->whereDate('created_at', today())->count(),
            'status_diubah' => ActivityLogModel::where('aksi', 'status_changed')->whereDate('created_at', today())->count(),
        ];

        // Summary modal data
        $modalLogs = collect();
        $modalTotal = 0;
        $modalTotalPages = 0;
        if ($this->summaryModal && ($modalQuery = $this->summaryModalQuery())) {
            $modalTotal = (clone $modalQuery)->count();
            $modalTotalPages = max(1, (int) ceil($modalTotal / self::SUMMARY_PER_PAGE));
            $this->summaryPage = min($this->summaryPage, $modalTotalPages);
            $offset = ($this->summaryPage - 1) * self::SUMMARY_PER_PAGE;
            $modalLogs = $modalQuery->skip($offset)->take(self::SUMMARY_PER_PAGE)->get();
        }

        return view('livewire.admin.activity-log', [
            'logs' => $logs,
            'ringkasan' => $ringkasan,
            'lokasis' => Lokasi::orderBy('nama_lokasi')->get(),
            'tabCounts' => $this->buildTabCounts(),
            'modalLogs' => $modalLogs,
            'modalTotal' => $modalTotal,
            'modalTotalPages' => $modalTotalPages,
        ]);
    }
}

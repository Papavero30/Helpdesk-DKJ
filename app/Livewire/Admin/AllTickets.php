<?php

namespace App\Livewire\Admin;

use App\Models\Kategori;
use App\Models\Lokasi;
use App\Models\Tiket;
use App\Models\User;
use App\Support\BreadcrumbStack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'All Tickets', 'description' => 'Manage all tickets in your location'])]
class AllTickets extends Component
{
    use WithPagination;

    #[Url(as: 'tab', except: 'all')]
    public string $statusTab = 'all';

    #[Url(as: 'mine', except: false)]
    public bool $myTicketsMode = false;

    /** True on the manager page (Manager\AllTickets): read-only list + assign control. */
    public bool $forManager = false;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public array $filterKategoris = [];

    public array $filterLokasis = [];

    public string $sortBy = 'id_desc';

    public ?string $dateMode = null;

    public ?string $dateExact = null;

    public ?string $dateStart = null;

    public ?string $dateEnd = null;

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403);
        }

        BreadcrumbStack::push('All Tickets', '/admin/all-tickets');
    }

    public function setTab(string $tab): void
    {
        $this->statusTab = $tab;
        $this->resetPage();
    }

    public function toggleMyTicketsMode(): void
    {
        $this->myTicketsMode = ! $this->myTicketsMode;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filterKategoris = [];
        $this->filterLokasis = [];
        $this->dateMode = null;
        $this->dateExact = null;
        $this->dateStart = null;
        $this->dateEnd = null;
        $this->resetPage();
        $this->dispatch('filters-reset');
    }

    public function resetSort(): void
    {
        $this->sortBy = 'id_desc';
        $this->resetPage();
        $this->dispatch('sort-reset');
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function applySort(): void
    {
        $this->resetPage();
    }

    #[On('ticket-status-changed')]
    public function refreshList(): void
    {
        $this->resetPage();
    }

    private function applyBaseFilters(Builder $query): Builder
    {
        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('deskripsi', 'like', $term)
                    ->orWhereHas('kategori', fn ($k) => $k->where('nama_kategori', 'like', $term))
                    ->orWhereHas('user.karyawan', fn ($k) => $k->where('nama', 'like', $term));
            });
        }

        if (! empty($this->filterKategoris)) {
            $query->whereIn('id_kategori', $this->filterKategoris);
        }

        if (! empty($this->filterLokasis)) {
            $query->whereIn('id_lokasi', $this->filterLokasis);
        }

        if ($this->dateMode === 'exact' && $this->dateExact) {
            $query->whereDate('created_at', $this->dateExact);
        } elseif ($this->dateMode === 'range' && $this->dateStart && $this->dateEnd) {
            $query->whereBetween('created_at', [
                $this->dateStart.' 00:00:00',
                $this->dateEnd.' 23:59:59',
            ]);
        }

        // MyTickets mode — filter to tickets owned by current admin, but only for tabs where
        // ownership matters. Never applies on the manager page (manager is not a PIC).
        if (! $this->forManager && $this->myTicketsMode && in_array($this->statusTab, ['in_progress', 'close', 'recurring'], true)) {
            $query->where('id_penanggung_jawab', Auth::id());
        }

        return $query;
    }

    public function render()
    {
        $userId = Auth::id();

        // Mark dot only counts notifications for tickets where current admin is the PIC.
        // Tickets owned by other admins (or unassigned) won't show red dot to this admin.
        $unreadSub = DB::table('notifications')
            ->selectRaw('COUNT(*)')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->whereRaw('notifications.tiket_id = tiket.id')
            ->whereRaw('tiket.id_penanggung_jawab = ?', [$userId]);

        $query = Tiket::query()
            ->addSelect('tiket.*')
            ->with(['lokasi', 'kategori', 'statusTiket', 'user.karyawan', 'assignedAdmin.karyawan']);

        // The red-dot subquery is PIC-based; a manager is never a PIC, so skip it there.
        if (! $this->forManager) {
            $query->selectSub($unreadSub, 'unread_count');
        }

        if ($this->statusTab === 'recurring') {
            $query->where('berulang', true);
        } elseif ($this->statusTab !== 'all') {
            $statusName = match ($this->statusTab) {
                'open' => 'Open',
                'in_progress' => 'In Progress',
                'close' => 'Close',
                default => null,
            };
            if ($statusName) {
                $query->whereHas('statusTiket', fn ($q) => $q->where('nama_status', $statusName));
            }
        }

        $this->applyBaseFilters($query);

        if (! $this->forManager) {
            $query->orderByRaw('(unread_count > 0) DESC');
        }

        match ($this->sortBy) {
            'id_asc' => $query->orderBy('tiket.id', 'asc'),
            'desc_asc' => $query->orderBy('tiket.deskripsi', 'asc'),
            'desc_desc' => $query->orderBy('tiket.deskripsi', 'desc'),
            'kategori_asc' => $query->leftJoin('kategori', 'tiket.id_kategori', '=', 'kategori.id')->orderBy('kategori.nama_kategori', 'asc'),
            'kategori_desc' => $query->leftJoin('kategori', 'tiket.id_kategori', '=', 'kategori.id')->orderBy('kategori.nama_kategori', 'desc'),
            'karyawan_asc' => $query->leftJoin('pengguna as req', 'tiket.id_pengguna', '=', 'req.id')->leftJoin('karyawan as req_kar', 'req.id_karyawan', '=', 'req_kar.id')->orderBy('req_kar.nama', 'asc'),
            'karyawan_desc' => $query->leftJoin('pengguna as req', 'tiket.id_pengguna', '=', 'req.id')->leftJoin('karyawan as req_kar', 'req.id_karyawan', '=', 'req_kar.id')->orderBy('req_kar.nama', 'desc'),
            default => $this->statusTab === 'open'
                ? $query
                    ->leftJoin('kategori as k_sort', 'tiket.id_kategori', '=', 'k_sort.id')
                    ->orderByRaw('COALESCE(k_sort.urgensi, 999) ASC')
                    ->orderByRaw('CASE WHEN tiket.target_penyelesaian IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('tiket.target_penyelesaian', 'asc')
                    ->orderBy('tiket.created_at', 'asc')
                : $query->orderBy('tiket.id', 'desc'),
        };

        // Same active-admin list Oversight uses for its assign dropdown (name + home plant).
        $allAdmins = $this->forManager
            ? User::where('peran', 'admin')
                ->where('status_akun', 'active')
                ->with('karyawan.lokasi')
                ->get()
                ->sortBy(fn (User $a) => $a->karyawan?->nama ?? '')
                ->values()
            : collect();

        return view('livewire.admin.all-tickets', [
            'tikets' => $query->paginate(15),
            'categories' => Kategori::orderBy('nama_kategori')->get(),
            'lokasis' => Lokasi::orderBy('nama_lokasi')->get(),
            'tabCounts' => $this->buildTabCounts(),
            'allAdmins' => $allAdmins,
        ]);
    }

    protected function buildTabCounts(): array
    {
        // Build a base WITHOUT the MyTickets ownership filter (we apply it conditionally per tab)
        $base = Tiket::query();
        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $base->where(function ($q) use ($term) {
                $q->where('deskripsi', 'like', $term)
                    ->orWhereHas('kategori', fn ($k) => $k->where('nama_kategori', 'like', $term))
                    ->orWhereHas('user.karyawan', fn ($k) => $k->where('nama', 'like', $term));
            });
        }
        if (! empty($this->filterKategoris)) {
            $base->whereIn('id_kategori', $this->filterKategoris);
        }
        if (! empty($this->filterLokasis)) {
            $base->whereIn('id_lokasi', $this->filterLokasis);
        }
        if ($this->dateMode === 'exact' && $this->dateExact) {
            $base->whereDate('created_at', $this->dateExact);
        } elseif ($this->dateMode === 'range' && $this->dateStart && $this->dateEnd) {
            $base->whereBetween('created_at', [
                $this->dateStart.' 00:00:00',
                $this->dateEnd.' 23:59:59',
            ]);
        }

        $userId = Auth::id();
        $applyOwnership = fn ($q) => (! $this->forManager && $this->myTicketsMode) ? $q->where('id_penanggung_jawab', $userId) : $q;

        return [
            'all' => (clone $base)->count(),
            'open' => (clone $base)->whereHas('statusTiket', fn ($q) => $q->where('nama_status', 'Open'))->count(),
            'in_progress' => $applyOwnership((clone $base)->whereHas('statusTiket', fn ($q) => $q->where('nama_status', 'In Progress')))->count(),
            'close' => $applyOwnership((clone $base)->whereHas('statusTiket', fn ($q) => $q->where('nama_status', 'Close')))->count(),
            'recurring' => $applyOwnership((clone $base)->where('berulang', true))->count(),
        ];
    }
}

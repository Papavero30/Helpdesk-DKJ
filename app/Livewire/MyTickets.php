<?php

namespace App\Livewire;

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

#[Layout('layouts.app', ['title' => 'My Tickets', 'description' => 'View and manage your support tickets'])]
class MyTickets extends Component
{
    use WithPagination;

    #[Url(as: 'tab', except: 'all')]
    public string $statusTab = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public array $filterKategoris = [];

    public array $filterLokasis = [];

    public string $sortBy = 'id_desc';

    public ?string $dateMode = null;

    public ?string $dateExact = null;

    public ?string $dateStart = null;

    public ?string $dateEnd = null;

    public bool $showNewTicketModal = false;

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isKaryawan()) {
            abort(403);
        }

        BreadcrumbStack::push('My Tickets', '/my-tickets');
    }

    public function setTab(string $tab): void
    {
        $this->statusTab = $tab;
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

    public function resetAll(): void
    {
        $this->filterKategoris = [];
        $this->filterLokasis = [];
        $this->dateMode = null;
        $this->dateExact = null;
        $this->dateStart = null;
        $this->dateEnd = null;
        $this->sortBy = 'id_desc';
        $this->search = '';
        $this->statusTab = 'all';
        $this->resetPage();
        $this->dispatch('filters-reset');
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
    #[On('ticket-created')]
    public function refreshList(): void
    {
        $this->dispatch('close-modal', id: 'new-ticket-modal');
        $this->resetPage();
    }

    private function applyBaseFilters(Builder $query): Builder
    {
        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('deskripsi', 'like', $term)
                    ->orWhereHas('kategori', fn ($k) => $k->where('nama_kategori', 'like', $term))
                    ->orWhereHas('lokasi', fn ($l) => $l->where('nama_lokasi', 'like', $term))
                    ->orWhereHas('assignedAdmin.karyawan', fn ($a) => $a->where('nama', 'like', $term));
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

        return $query;
    }

    public function render()
    {
        $userId = Auth::id();

        $unreadSub = DB::table('notifications')
            ->selectRaw('COUNT(*)')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->whereRaw('notifications.tiket_id = tiket.id');

        $query = Tiket::query()
            ->where('id_pengguna', $userId)
            ->selectSub($unreadSub, 'unread_count')
            ->addSelect('tiket.*')
            ->with(['lokasi', 'kategori', 'statusTiket', 'penilaian', 'assignedAdmin.karyawan']);

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

        $query->orderByRaw('(unread_count > 0) DESC');

        match ($this->sortBy) {
            'id_asc' => $query->orderBy('tiket.id', 'asc'),
            'desc_asc' => $query->orderBy('tiket.deskripsi', 'asc'),
            'desc_desc' => $query->orderBy('tiket.deskripsi', 'desc'),
            'kategori_asc' => $query->leftJoin('kategori', 'tiket.id_kategori', '=', 'kategori.id')->orderBy('kategori.nama_kategori', 'asc'),
            'kategori_desc' => $query->leftJoin('kategori', 'tiket.id_kategori', '=', 'kategori.id')->orderBy('kategori.nama_kategori', 'desc'),
            'pic_asc' => $query->leftJoin('pengguna as pic', 'tiket.id_penanggung_jawab', '=', 'pic.id')->leftJoin('karyawan as pic_kar', 'pic.id_karyawan', '=', 'pic_kar.id')->orderByRaw('SUBSTRING_INDEX(COALESCE(pic_kar.nama, ""), " ", 2) asc'),
            'pic_desc' => $query->leftJoin('pengguna as pic', 'tiket.id_penanggung_jawab', '=', 'pic.id')->leftJoin('karyawan as pic_kar', 'pic.id_karyawan', '=', 'pic_kar.id')->orderByRaw('SUBSTRING_INDEX(COALESCE(pic_kar.nama, ""), " ", 2) desc'),
            default => $query->orderBy('tiket.id', 'desc'),
        };

        return view('livewire.my-tickets', [
            'tikets' => $query->paginate(15),
            'categories' => Kategori::orderBy('nama_kategori')->get(),
            'lokasis' => Lokasi::orderBy('nama_lokasi')->get(),
            'tabCounts' => $this->buildTabCounts(),
        ]);
    }

    protected function buildTabCounts(): array
    {
        $base = $this->applyBaseFilters(
            Tiket::where('id_pengguna', Auth::id())
        );

        return [
            'all' => (clone $base)->count(),
            'open' => (clone $base)->whereHas('statusTiket', fn ($q) => $q->where('nama_status', 'Open'))->count(),
            'in_progress' => (clone $base)->whereHas('statusTiket', fn ($q) => $q->where('nama_status', 'In Progress'))->count(),
            'close' => (clone $base)->whereHas('statusTiket', fn ($q) => $q->where('nama_status', 'Close'))->count(),
            'recurring' => (clone $base)->where('berulang', true)->count(),
        ];
    }
}

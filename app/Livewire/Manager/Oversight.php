<?php

namespace App\Livewire\Manager;

use App\Application\Services\ActivityLogService;
use App\Application\Services\AdminDashboardService;
use App\Application\Services\SlaPauseService;
use App\Application\Services\TiketService;
use App\Models\Kategori;
use App\Models\Lokasi;
use App\Models\SlaPauseRequest;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use App\Notifications\TicketReminderNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Admin Oversight', 'description' => 'Workload and ticket reassignment'])]
class Oversight extends Component
{
    public bool $showDrilldownModal = false;

    public ?int $drilldownAdminId = null;

    // Drill-down modal search + filters.
    public string $ddSearch = '';

    public string $ddCategory = '';

    public string $ddSla = 'all';

    public int $ddPage = 1;

    private const DD_PER_PAGE = 10;

    // Admin Workload table controls.
    public string $wlSearch = '';

    /** @var array<int,string> selected plant names */
    public array $wlPlants = [];

    public string $wlSort = 'name_asc';

    public int $wlPage = 1;

    // Unassigned Tickets table controls.
    public string $unSearch = '';

    /** @var array<int,int> selected category ids */
    public array $unCategories = [];

    /** @var array<int,int> selected plant ids */
    public array $unPlants = [];

    public string $unSort = 'oldest';

    public int $unPage = 1;

    // Deadline Pauses table controls.
    public string $dpSearch = '';

    public string $dpStatus = 'all';

    public string $dpSort = 'eta_asc';

    public int $dpPage = 1;

    private const PER_PAGE = 10;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isManager()) {
            abort(403);
        }
    }

    public function openAdmin(int $adminId): void
    {
        $this->drilldownAdminId = $adminId;
        $this->showDrilldownModal = true;
        // Start each drill-down with a clean search/filter state.
        $this->reset('ddSearch', 'ddCategory', 'ddSla', 'ddPage');
    }

    public function ddGoToPage(int $page): void
    {
        $this->ddPage = max(1, $page);
    }

    // Any search/filter change returns to the first page of results.
    public function updatedDdSearch(): void
    {
        $this->ddPage = 1;
    }

    public function updatedDdCategory(): void
    {
        $this->ddPage = 1;
    }

    public function updatedDdSla(): void
    {
        $this->ddPage = 1;
    }

    // Any search/filter/sort change on a page table returns it to page one.
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'wl') && $property !== 'wlPage') {
            $this->wlPage = 1;
        }
        if (str_starts_with($property, 'un') && $property !== 'unPage') {
            $this->unPage = 1;
        }
        if (str_starts_with($property, 'dp') && $property !== 'dpPage') {
            $this->dpPage = 1;
        }
    }

    public function wlGoToPage(int $page): void
    {
        $this->wlPage = max(1, $page);
    }

    public function unGoToPage(int $page): void
    {
        $this->unPage = max(1, $page);
    }

    public function dpGoToPage(int $page): void
    {
        $this->dpPage = max(1, $page);
    }

    public function closeDrilldown(): void
    {
        $this->showDrilldownModal = false;
        $this->drilldownAdminId = null;
    }

    public function reassign(int $ticketId, int $newAdminId): void
    {
        $tiket = Tiket::find($ticketId);
        if (! $tiket) {
            $this->dispatch('notify', type: 'error', content: 'Ticket not found.');

            return;
        }

        try {
            app(TiketService::class)->reassignAdmin($tiket, $newAdminId, Auth::id());
            $this->dispatch('notify', type: 'success', content: 'Ticket reassigned.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'warning', content: $e->getMessage());
        }
    }

    public function assignUnassigned(int $ticketId, int $adminId): void
    {
        $tiket = Tiket::find($ticketId);
        if (! $tiket) {
            $this->dispatch('notify', type: 'error', content: 'Ticket not found.');

            return;
        }

        try {
            app(TiketService::class)->assignAsManager($tiket, $adminId, Auth::id());
            $this->dispatch('notify', type: 'success', content: 'Ticket assigned.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'warning', content: $e->getMessage());
        }
    }

    /**
     * Full stop for a ticket's pause: resume the active span and cancel any
     * request that still waits for an answer (including a pending extension).
     */
    public function forceResumePause(int $ticketId): void
    {
        if (! Auth::user()?->isManager()) {
            abort(403);
        }
        $reqs = SlaPauseRequest::where('tiket_id', $ticketId)
            ->whereIn('state', ['pending', 'active'])->get();
        if ($reqs->isEmpty()) {
            return;
        }

        $hadActive = $reqs->contains(fn ($r) => $r->state === 'active');
        foreach ($reqs as $req) {
            app(SlaPauseService::class)->forceResume($req, Auth::id());
        }
        $this->dispatch('notify', type: 'success', content: $hadActive ? 'Deadline pause resumed.' : 'Pause request cancelled.');
    }

    /** Cancel one waiting request only; an active pause on the ticket keeps running. */
    public function cancelPauseRequest(int $requestId): void
    {
        if (! Auth::user()?->isManager()) {
            abort(403);
        }
        $req = SlaPauseRequest::find($requestId);
        if ($req && $req->state === 'pending') {
            app(SlaPauseService::class)->forceResume($req, Auth::id());
            $this->dispatch('notify', type: 'success', content: 'Pause request cancelled.');
        }
    }

    public function remind(int $ticketId, string $audience): void
    {
        if (! in_array($audience, ['admin', 'requester'], true)) {
            return;
        }

        $tiket = Tiket::with(['assignedAdmin', 'user'])->find($ticketId);
        if (! $tiket) {
            $this->dispatch('notify', type: 'error', content: 'Ticket not found.');

            return;
        }

        $recipient = $audience === 'admin' ? $tiket->assignedAdmin : $tiket->user;
        if (! $recipient) {
            $this->dispatch('notify', type: 'warning', content: 'No recipient for this reminder.');

            return;
        }

        $recipient->notify(new TicketReminderNotification($tiket, $audience));

        app(ActivityLogService::class)->catat(
            $tiket,
            'reminder_sent',
            userId: Auth::id(),
            keterangan: 'Reminder sent to '.$audience.' by manager',
        );

        $this->dispatch('notify', type: 'success', content: 'Reminder sent.');
    }

    public function render()
    {
        $plants = Lokasi::orderBy('nama_lokasi')->get(['id', 'nama_lokasi']);
        $categories = Kategori::orderBy('urgensi')->get(['id', 'nama_kategori']);

        // ---- Admin Workload: search + plant filter + sort + pager (in memory,
        // the row count is bounded by the number of admins). ----
        $workloadAll = app(AdminDashboardService::class)->managerWorkload();

        $workloadFiltered = $workloadAll
            ->when(trim($this->wlSearch) !== '', function ($rows) {
                $term = mb_strtolower(trim($this->wlSearch));

                return $rows->filter(fn ($r) => str_contains(mb_strtolower($r['nama']), $term)
                    || str_contains(mb_strtolower($r['plant']), $term));
            })
            ->when(! empty($this->wlPlants), fn ($rows) => $rows->whereIn('plant', $this->wlPlants));

        $workloadSorted = match ($this->wlSort) {
            'name_desc' => $workloadFiltered->sortByDesc(fn ($r) => mb_strtolower($r['nama'])),
            'active_desc' => $workloadFiltered->sortByDesc('active'),
            'overdue_desc' => $workloadFiltered->sortByDesc('overdue'),
            'awaiting_desc' => $workloadFiltered->sortByDesc('awaiting'),
            default => $workloadFiltered->sortBy(fn ($r) => mb_strtolower($r['nama'])),
        };

        $wlTotal = $workloadSorted->count();
        $wlTotalPages = max(1, (int) ceil($wlTotal / self::PER_PAGE));
        $this->wlPage = min(max(1, $this->wlPage), $wlTotalPages);
        $workload = $workloadSorted->forPage($this->wlPage, self::PER_PAGE)->values();

        // Both Assign (unassigned) and Reassign (drill-down) are cross-plant: a flat
        // list of every active admin, labelled with their home plant.
        $allAdmins = User::where('peran', 'admin')
            ->where('status_akun', 'active')
            ->with('karyawan.lokasi')
            ->get()
            ->sortBy(fn (User $a) => $a->karyawan?->nama ?? '')
            ->values();

        // ---- Unassigned Tickets: search + category/plant filter + sort + pager. ----
        $openId = StatusTiketModel::findByName('Open')?->id ?? 0;
        $unBase = Tiket::whereNull('id_penanggung_jawab')
            ->where('id_status_tiket', $openId)
            ->when(! empty($this->unCategories), fn ($q) => $q->whereIn('id_kategori', $this->unCategories))
            ->when(! empty($this->unPlants), fn ($q) => $q->whereIn('id_lokasi', $this->unPlants))
            ->when(trim($this->unSearch) !== '', function ($q) {
                $term = trim($this->unSearch);
                $digits = preg_replace('/\D/', '', $term);
                $q->where(function ($w) use ($term, $digits) {
                    $w->where('deskripsi', 'like', "%{$term}%")
                        ->orWhereHas('user.karyawan', fn ($k) => $k->where('nama', 'like', "%{$term}%"));
                    if ($digits !== '') {
                        $w->orWhere('id', (int) $digits);
                    }
                });
            });

        $unTotal = (clone $unBase)->count();
        $unTotalPages = max(1, (int) ceil($unTotal / self::PER_PAGE));
        $this->unPage = min(max(1, $this->unPage), $unTotalPages);

        $unassignedTickets = $unBase
            ->with(['kategori', 'lokasi', 'user.karyawan'])
            ->when($this->unSort === 'newest', fn ($q) => $q->orderByDesc('created_at'))
            ->when($this->unSort === 'urgency', fn ($q) => $q
                ->orderBy(Kategori::select('urgensi')->whereColumn('kategori.id', 'tiket.id_kategori'))
                ->orderBy('created_at'))
            ->when(! in_array($this->unSort, ['newest', 'urgency'], true), fn ($q) => $q->orderBy('created_at'))
            ->forPage($this->unPage, self::PER_PAGE)
            ->get();

        // ---- Deadline Pauses: every pause that freezes a deadline right now
        // (active) or still waits for the requester's answer (pending). A pending
        // request whose ticket also has an active pause is an extension. Filtered
        // in memory so the extension detection always sees the full set. ----
        $pauseAll = SlaPauseRequest::whereIn('state', ['pending', 'active'])
            ->with(['tiket.assignedAdmin.karyawan'])
            ->get();

        $extendTicketIds = $pauseAll
            ->where('state', 'active')
            ->pluck('tiket_id');

        $pauseFiltered = $pauseAll
            ->when($this->dpStatus === 'active', fn ($rows) => $rows->where('state', 'active'))
            ->when($this->dpStatus === 'waiting', fn ($rows) => $rows->filter(
                fn ($r) => $r->state === 'pending' && ! $extendTicketIds->contains($r->tiket_id)))
            ->when($this->dpStatus === 'extend', fn ($rows) => $rows->filter(
                fn ($r) => $r->state === 'pending' && $extendTicketIds->contains($r->tiket_id)))
            ->when(trim($this->dpSearch) !== '', function ($rows) {
                $term = mb_strtolower(trim($this->dpSearch));
                $digits = preg_replace('/\D/', '', $term);

                return $rows->filter(function ($r) use ($term, $digits) {
                    $hay = mb_strtolower(($r->tiket?->deskripsi ?? '').' '.($r->reason ?? '').' '
                        .($r->tiket?->assignedAdmin?->karyawan?->nama ?? ''));

                    return str_contains($hay, $term)
                        || ($digits !== '' && str_contains((string) $r->tiket_id, $digits));
                });
            });

        $pauseSorted = match ($this->dpSort) {
            'eta_desc' => $pauseFiltered->sortByDesc('eta'),
            'ticket_desc' => $pauseFiltered->sortByDesc('tiket_id'),
            default => $pauseFiltered->sortBy('eta'),
        };

        $dpTotal = $pauseSorted->count();
        $dpTotalPages = max(1, (int) ceil($dpTotal / self::PER_PAGE));
        $this->dpPage = min(max(1, $this->dpPage), $dpTotalPages);
        $pauseRequests = $pauseSorted->forPage($this->dpPage, self::PER_PAGE)->values();

        $drilldownAdmin = null;
        $drilldownTickets = collect();
        $drilldownCategories = $categories;
        $ddTotalPages = 1;
        $ddTotal = 0;

        if ($this->showDrilldownModal && $this->drilldownAdminId) {
            $closeId = StatusTiketModel::findByName('Close')?->id ?? 0;

            $drilldownAdmin = User::with('karyawan')->find($this->drilldownAdminId);

            $base = Tiket::where('id_penanggung_jawab', $this->drilldownAdminId)
                ->where('id_status_tiket', '!=', $closeId)
                ->when($this->ddCategory !== '', fn ($q) => $q->where('id_kategori', $this->ddCategory))
                ->when($this->ddSla === 'overdue', fn ($q) => $q->overdueEffective())
                ->when($this->ddSla === 'on_track', fn ($q) => $q->onTrackEffective())
                ->when($this->ddSla === 'paused', fn ($q) => $q->whereNotNull('sla_paused_at'))
                ->when(trim($this->ddSearch) !== '', function ($q) {
                    $term = trim($this->ddSearch);
                    $digits = preg_replace('/\D/', '', $term);
                    $q->where(function ($w) use ($term, $digits) {
                        $w->where('deskripsi', 'like', "%{$term}%")
                            ->orWhereHas('user.karyawan', fn ($k) => $k->where('nama', 'like', "%{$term}%"));
                        if ($digits !== '') {
                            $w->orWhere('id', (int) $digits);
                        }
                    });
                });

            $ddTotal = (clone $base)->count();
            $ddTotalPages = max(1, (int) ceil($ddTotal / self::DD_PER_PAGE));
            $this->ddPage = min(max(1, $this->ddPage), $ddTotalPages);

            $drilldownTickets = $base
                ->with(['kategori', 'statusTiket', 'user.karyawan'])
                ->orderByRaw('target_penyelesaian IS NULL, target_penyelesaian ASC')
                ->forPage($this->ddPage, self::DD_PER_PAGE)
                ->get();
        }

        return view('livewire.manager.oversight', compact(
            'workload', 'wlTotal', 'wlTotalPages',
            'drilldownAdmin', 'drilldownTickets', 'drilldownCategories', 'ddTotalPages', 'ddTotal',
            'allAdmins', 'unassignedTickets', 'unTotal', 'unTotalPages',
            'pauseRequests', 'extendTicketIds', 'dpTotal', 'dpTotalPages',
            'plants', 'categories',
        ));
    }
}

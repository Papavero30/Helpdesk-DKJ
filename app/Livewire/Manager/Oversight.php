<?php

namespace App\Livewire\Manager;

use App\Application\Services\ActivityLogService;
use App\Application\Services\AdminDashboardService;
use App\Application\Services\SlaPauseService;
use App\Application\Services\TiketService;
use App\Models\Kategori;
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

    public function forceResumePause(int $ticketId): void
    {
        if (! Auth::user()?->isManager()) {
            abort(403);
        }
        $req = SlaPauseRequest::where('tiket_id', $ticketId)
            ->whereIn('state', ['pending', 'active'])->latest('id')->first();
        if ($req) {
            app(SlaPauseService::class)->forceResume($req, Auth::id());
            $this->dispatch('notify', type: 'success', content: 'Deadline pause resumed.');
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
        $workload = app(AdminDashboardService::class)->managerWorkload();

        // Both Assign (unassigned) and Reassign (drill-down) are cross-plant: a flat
        // list of every active admin, labelled with their home plant.
        $allAdmins = User::where('peran', 'admin')
            ->where('status_akun', 'active')
            ->with('karyawan.lokasi')
            ->get()
            ->sortBy(fn (User $a) => $a->karyawan?->nama ?? '')
            ->values();

        $openId = StatusTiketModel::findByName('Open')?->id ?? 0;
        $unassignedTickets = Tiket::whereNull('id_penanggung_jawab')
            ->where('id_status_tiket', $openId)
            ->with(['kategori', 'lokasi', 'user.karyawan'])
            ->orderBy('created_at')
            ->get();

        $drilldownAdmin = null;
        $drilldownTickets = collect();
        $drilldownCategories = collect();
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

            $drilldownCategories = Kategori::orderBy('urgensi')->get(['id', 'nama_kategori']);
        }

        return view('livewire.manager.oversight', compact('workload', 'drilldownAdmin', 'drilldownTickets', 'drilldownCategories', 'ddTotalPages', 'ddTotal', 'allAdmins', 'unassignedTickets'));
    }
}

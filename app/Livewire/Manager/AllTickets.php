<?php

namespace App\Livewire\Manager;

use App\Application\Services\TiketService;
use App\Livewire\Admin\AllTickets as AdminAllTickets;
use App\Models\Tiket;
use App\Models\User;
use App\Support\BreadcrumbStack;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

/**
 * Manager view of the ticket list: same engine as Admin\AllTickets, but
 * read-only except assigning an admin to unassigned Open tickets.
 */
#[Layout('layouts.app', ['title' => 'All Tickets', 'description' => 'Monitor all tickets across plants'])]
class AllTickets extends AdminAllTickets
{
    public bool $forManager = true;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isManager()) {
            abort(403);
        }

        BreadcrumbStack::push('All Tickets', '/manager/all-tickets');
    }

    /** Same behavior as Oversight::assignUnassigned, with an inline role guard. */
    public function assignUnassigned(int $ticketId, int $adminId): void
    {
        if (! Auth::user()?->isManager()) {
            abort(403);
        }

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
}

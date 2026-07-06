<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class BellBadge extends Component
{
    public int $unreadCount = 0;

    /**
     * The authenticated user's id, resolved ONCE at mount and kept as Livewire
     * state. The view uses this for the Echo private-channel name. Resolving it
     * via auth()->id() inline in the Blade/x-data would re-evaluate on every
     * Livewire morph and could yield null mid-update, producing a bogus
     * "App.Models.User.undefined" channel subscription that 403s.
     */
    public ?int $userId = null;

    public function mount(): void
    {
        $this->userId = Auth::id();
        $this->refreshCount();
    }

    /**
     * Listen for in-page Livewire events dispatched by Alerts / TicketDetail
     * setelah mark-as-read aksi. Update count langsung tanpa polling.
     */
    #[On('notifications-updated')]
    public function refreshFromEvent(): void
    {
        $this->refreshCount();
    }

    /**
     * Refresh the count when a new notification arrives over the Echo private
     * channel. The view subscribes to Echo and dispatches this event.
     *
     * NOTE: the listener name must NOT start with "echo" — Livewire treats an
     * #[On('echo-...')] / #[On('echo:...')] listener as an instruction to
     * auto-subscribe an Echo channel itself. The old 'echo-notification' name
     * made Livewire subscribe a malformed "private-undefined" channel that 403'd
     * on /broadcasting/auth. The Echo subscription is handled explicitly in the
     * Blade view, so this is a plain in-component event listener.
     */
    #[On('notification-received')]
    public function refreshFromBroadcast(): void
    {
        $this->refreshCount();
    }

    public function refreshCount(): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->unreadCount = 0;

            return;
        }

        // Forget cache supaya nilai segar (cache key consistent dengan layout lama)
        Cache::forget('unread-notif-'.$user->id);

        // PIC-scoped logic — admin hanya count notif untuk tiket yang mereka PIC
        if ($user->isAdmin()) {
            $this->unreadCount = (int) DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', User::class)
                ->whereNull('read_at')
                ->whereRaw('(
                    notifications.tiket_id IS NULL
                    OR EXISTS (
                        SELECT 1 FROM tiket
                        WHERE tiket.id = notifications.tiket_id
                        AND tiket.id_penanggung_jawab = ?
                    )
                )', [$user->id])
                ->count();

            return;
        }

        // Karyawan — count all unread (they're the requester)
        $this->unreadCount = $user->unreadNotifications()->count();
    }

    public function render()
    {
        return view('livewire.bell-badge');
    }
}

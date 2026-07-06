<?php

namespace App\Livewire;

use App\Support\BreadcrumbStack;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Alerts', 'description' => 'Stay updated on your ticket activities'])]
class Alerts extends Component
{
    public int $limit = 20;

    public function mount(): void
    {
        BreadcrumbStack::push('Alerts', '/alerts');
    }

    public function markAllRead(): void
    {
        $user = auth()->user();
        $query = $user->unreadNotifications();

        // For admin: only mark notifs relevant to their tickets (PIC)
        if ($user->isAdmin()) {
            $query->whereRaw('(
                notifications.tiket_id IS NULL
                OR EXISTS (
                    SELECT 1 FROM tiket
                    WHERE tiket.id = notifications.tiket_id
                    AND tiket.id_penanggung_jawab = ?
                )
            )', [$user->id]);
        }

        $query->update(['read_at' => now()]);

        Cache::forget('unread-notif-'.$user->id);
        $this->dispatch('notifications-updated');
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        Cache::forget('unread-notif-'.auth()->id());
        $this->dispatch('notifications-updated');
    }

    public function loadMore(): void
    {
        $this->limit += 20;
    }

    public function render()
    {
        $user = auth()->user();

        $baseQuery = $user->notifications();
        $unreadQuery = $user->unreadNotifications();

        // Admin: filter only notifications for tickets where they are the PIC.
        // Karyawan: see all (they're the requester so all relevant).
        if ($user->isAdmin()) {
            $picTicketIdsSql = '(
                notifications.tiket_id IS NULL
                OR EXISTS (
                    SELECT 1 FROM tiket
                    WHERE tiket.id = notifications.tiket_id
                    AND tiket.id_penanggung_jawab = ?
                )
            )';
            $baseQuery->whereRaw($picTicketIdsSql, [$user->id]);
            $unreadQuery->whereRaw($picTicketIdsSql, [$user->id]);
        }

        $unreadCount = $unreadQuery->count();
        $totalCount = (clone $baseQuery)->count();

        $notifications = $baseQuery->orderByDesc('created_at')
            ->limit($this->limit)
            ->get();

        $unread = $notifications->whereNull('read_at');
        $read = $notifications->whereNotNull('read_at');

        return view('livewire.alerts', [
            'unread' => $unread,
            'read' => $read,
            'unreadCount' => $unreadCount,
            'hasMore' => $totalCount > $this->limit,
        ]);
    }
}

@php
    $statusName = $ticket->statusTiket?->nama_status;
    $statusBadge = $statusName ? \App\Models\StatusTiketModel::badgeClass($statusName) : 'bg-gray-100 text-gray-600';

    $chip = null;
    if ($context === 'sla' && $ticket->target_penyelesaian) {
        $effectiveDeadline = $ticket->effectiveDeadline();
        if ($effectiveDeadline && $effectiveDeadline->isPast()) {
            $chip = ['text' => 'Overdue '.$effectiveDeadline->diffForHumans(null, true), 'class' => 'bg-red-100 text-red-700'];
        } elseif ($effectiveDeadline) {
            $chip = ['text' => $effectiveDeadline->diffForHumans(null, true).' left', 'class' => 'bg-amber-100 text-amber-700'];
        }
    } elseif ($context === 'myqueue') {
        $chip = ['text' => $ticket->target_penyelesaian ? 'Due '.$ticket->target_penyelesaian->diffForHumans() : 'No SLA', 'class' => 'bg-gray-100 text-gray-500'];
    } elseif ($context === 'pickup') {
        $chip = ['text' => $ticket->created_at?->diffForHumans() ?? '', 'class' => 'bg-gray-100 text-gray-500'];
    }
@endphp
<a href="/ticket/{{ $ticket->id }}" wire:navigate
   class="block px-4 py-3 hover:bg-gray-50 transition-colors">
    <div class="flex items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 min-w-0">
            <span class="font-mono text-xs font-semibold" style="color:#0E4260;">#TKT{{ str_pad($ticket->id, 2, '0', STR_PAD_LEFT) }}</span>
            @if($statusName)
                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusBadge }}">{{ $statusName }}</span>
            @endif
            @if($ticket->kategori)
                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold bg-dongker-100 text-dongker-700">{{ $ticket->kategori->nama_kategori }}</span>
            @endif
        </div>
        @if($chip)
            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $chip['class'] }}">{{ $chip['text'] }}</span>
        @endif
    </div>
    <p class="mt-1 truncate text-sm text-gray-700">{{ $ticket->deskripsi }}</p>
    <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-400">
        <span>{{ $ticket->lokasi?->nama_lokasi ?? '—' }}</span>
        @if($ticket->user?->karyawan)
            <span>&middot;</span>
            <span>{{ $ticket->user->karyawan->nama }}</span>
        @endif
    </div>
</a>

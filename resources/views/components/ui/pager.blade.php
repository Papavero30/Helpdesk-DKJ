@props([
    'current' => 1,
    'total' => 1,
    'jump' => null, // Livewire method name to call with a page number, e.g. "summaryGoToPage"
])

@php
    $current = max(1, (int) $current);
    $total = max(1, (int) $total);

    // Build the page token list with ellipsis. Always show first + last and a
    // window of +/-1 around the current page; gaps become a '...' token.
    if ($total <= 7) {
        $tokens = range(1, $total);
    } else {
        $tokens = [1];
        $start = max(2, $current - 1);
        $end = min($total - 1, $current + 1);
        if ($start > 2) {
            $tokens[] = '...';
        }
        for ($i = $start; $i <= $end; $i++) {
            $tokens[] = $i;
        }
        if ($end < $total - 1) {
            $tokens[] = '...';
        }
        $tokens[] = $total;
    }
@endphp

@if($total > 1)
    <div class="flex items-center gap-1" role="navigation" aria-label="Pagination">
        <button type="button" wire:click="{{ $jump }}({{ $current - 1 }})" @disabled($current <= 1)
                class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">
            <x-ui.icon name="chevron-left" class="size-3.5" /> Prev
        </button>

        @foreach($tokens as $token)
            @if($token === '...')
                <span class="select-none px-1.5 text-xs text-gray-400">…</span>
            @else
                <button type="button" wire:click="{{ $jump }}({{ $token }})"
                        @class([
                            'min-w-[2rem] rounded-lg px-2.5 py-1 text-xs font-semibold transition',
                            'bg-dongker-600 text-white shadow-sm' => $token === $current,
                            'text-gray-600 hover:bg-gray-100' => $token !== $current,
                        ])
                        @if($token === $current) aria-current="page" @endif>{{ $token }}</button>
            @endif
        @endforeach

        <button type="button" wire:click="{{ $jump }}({{ $current + 1 }})" @disabled($current >= $total)
                class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">
            Next <x-ui.icon name="chevron-right" class="size-3.5" />
        </button>
    </div>
@endif

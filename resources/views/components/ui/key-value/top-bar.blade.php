@props(['label' => null])
<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400  tracking-wider">
    @if (filled($label))
        {{ $label }}
    @else
        {{ $slot }}
    @endif
</th>
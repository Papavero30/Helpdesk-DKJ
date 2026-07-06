@aware([
    'placement' => 'top',
    'offset' => '3',
])

@php
    $classes = [
        'absolute whitespace-nowrap min-h-8 z-50 px-2 py-1 text-sm [:where(&)]:bg-white [:where(&)]:dark:bg-neutral-900 dark:backdrop-blur-lg [:where(&)]:text-black [:where(&)]:dark:text-white rounded shadow-lg pointer-events-none'
    ];
@endphp

<div    
    x-show="show" 
    x-on:click.away="hideTooltip()" 
    x-transition.duration.300 
    x-anchor.{{ $placement }}.offset.{{ $offset }}="$refs.tooltipTrigger"
    style="display: none"
    {{ $attributes->class($classes) }}    
>
    {{ $slot }}
</div>

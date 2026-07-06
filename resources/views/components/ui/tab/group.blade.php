@aware(['variant' => 'outlined'])

@php


$variantClasses = match($variant){
    'outlined' => [
        'gap-2 overflow-x-auto',
    ],
    'non-contained' => [
        // all handled on thier wrapper below
    ],
    'pills' => [
        'my-2'
    ],
    default => []
};
$classes = [
    'flex [:where(&)]:items-center [:where(&)]:justify-center', // all tabs group are centred until they can be overiden without !
    ...$variantClasses,
];
@endphp

<ul
    {{ $attributes->class(Arr::toCssClasses($classes))}}
    x-ref="tabItem"
    x-on:keydown.right.prevent.stop="$focus.wrap().next()"
    x-on:keydown.home.prevent.stop="$focus.first()"
    x-on:keydown.page-up.prevent.stop="$focus.first()"
    x-on:keydown.left.prevent.stop="$focus.wrap().prev()"
    x-on:keydown.end.prevent.stop="$focus.last()"
    x-on:keydown.page-down.prevent.stop="$focus.last()"
    role="tablist"
    data-slot="tabs-group"
>

{{-- non contained needs a wrapper for   --}}
@if($variant === 'non-contained')
    <div class="flex gap-1 w-fit bg-white/5 border-2 border-black/10 dark:border-white/10 rounded-(--noncontained-variant-radius) p-(--noncontained-variant-padding) [--noncontained-variant-radius:var(--radius-box)] [--noncontained-variant-padding:--spacing(.75)]">
        {{ $slot }}
    </div>
@else
    {{ $slot }}
@endif
</ul>

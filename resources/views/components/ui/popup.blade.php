{{-- 
    A helper to unify popover-like components (popover, dropdown, select, autocomplete, etc.)

    The key challenge: Alpine’s `x-show` directive toggles element visibility by 
    mutating DOM styles and setting an internal `_x_isShown` flag. That flag is not 
    reactive by default, so we can’t `$watch` it directly.

    This component bridges that gap by:
    - Mirroring `_x_isShown` into a reactive `shown` state (inside `x-data`)
    - Using a MutationObserver to watch style changes applied by `x-show`
    - Keeping `shown` in sync so we can reactively trap focus, model, or trigger side-effects

    With this, when the popup is opened (parent’s `x-show` → true):
    - `shown` updates automatically (shown → true)
    - We can use it to focus the popup (once it opens), trap keyboard navigation, or dispatch events
--}}

@props([
    'autofocus' => true
])

<div 
    @if($autofocus)
        x-data="{ shown: false }"
        x-modelable="shown"
        x-trap="shown"
        x-init="
            $nextTick(() => {
                let observer = new MutationObserver(() => {
                    shown = $el._x_isShown 
                })
                observer.observe($el, { attributes: true, attributeFilter: ['style'] })
            })
        "
    @endif
    {{ $attributes->class(["absolute z-50 bg-white w-full dark:bg-neutral-800 mt-1 backdrop-blur-xl border dark:border-neutral-700 border-neutral-200 rounded-(--popup-round) shadow-lg p-(--popup-padding)"]) }}
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 transform scale-95"
    x-transition:enter-end="opacity-100 transform scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 transform scale-100"
    x-transition:leave-end="opacity-0 transform scale-95"
    style="display:none;" {{-- avoid flickering --}}
>
    {{ $slot }}
</div>
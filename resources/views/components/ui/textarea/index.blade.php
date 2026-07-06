
@props([
    'disabled' => false,
    'resize' => 'vertical',
    'name' => $attributes->whereStartsWith('wire:model')->first() ?? $attributes->whereStartsWith('x-model')->first(),
    'rows' => null,
    'invalid' => null,
    ])
@php
    $rows ??= 3;

    $initialHeight = (($rows) * 1.5) + 0.75;

    $classes = [
        // Text colors
        'inline-block p-2 w-full text-base sm:text-sm text-neutral-800 disabled:text-neutral-500 placeholder-neutral-400 disabled:placeholder-neutral-400/70 dark:text-neutral-300 dark:disabled:text-neutral-400 dark:placeholder-neutral-400 dark:disabled:placeholder-neutral-500',

        // Background
        'bg-white dark:bg-neutral-900 dark:disabled:bg-neutral-800',

        // Cursor and transitions
        'disabled:cursor-not-allowed transition-colors duration-200',

        // Shadows and borders
        'shadow-sm disabled:shadow-none border rounded-box',

        // Focus outline
        'focus:ring-2 focus:ring-offset-0 focus:outline-none',

        // Normal state borders and focus rings
        'border-black/10 focus:border-black/15 focus:ring-neutral-900/15 dark:border-white/10 dark:focus:border-white/20 dark:focus:ring-neutral-100/15' => !$invalid,

        // Invalid state borders and focus rings
        'border-red-500 focus:border-red-500 focus:ring-red-500/25 dark:border-red-400 dark:focus:border-red-400 dark:focus:ring-red-400/25' => $invalid,

        // Resize handling
        match ($resize) {
            'none' => 'resize-none',
            'both' => 'resize',
            'horizontal' => 'resize-x',
            'vertical' => 'resize-y',
        },
    ];
@endphp


<textarea
    x-data="{
        initialHeight: @js($initialHeight) + 'rem',
        height: @js($initialHeight) + 'rem',
        name: @js($name),
        state: '',
        resize() {
            if (!this.$el) return;
            this.$el.style.height = 'auto';
            let newHeight = this.$el.scrollHeight + 'px';

            if (this.$el.scrollHeight < parseFloat(this.initialHeight)) {
                this.$el.style.height = this.initialHeight;
            } else {
                this.$el.style.height = newHeight;
            }
        }
    }"
    x-init="
        $nextTick(() => {
            // Initialize state from x-model or wire:model binding
            this.state = this.$root?._x_model?.get();
        })

        // Two-way data binding: sync internal state back to Alpine/Livewire
        $watch('state', (value) => {
            // Sync with Alpine.js x-model
            this.$root?._x_model?.set(value);
                
            // Sync with Livewire wire:model (if present)
            let wireModel = this?.$root.getAttributeNames().find(n => n.startsWith('wire:model'))
                
            if(this.$wire && wireModel){
                let prop = this.$root.getAttribute(wireModel)
                this.$wire.set(prop, value, wireModel?.includes('.live'));
            }
        });

        if(!this.$el) return;

        // give our textarea initial height based on the provided rows
        this.$el.style.height = this.initialHeight;

        const observer = new ResizeObserver(() => {
            this.resize();
        });

        observer.observe(this.$el);
    "
    {{ $attributes->class(Arr::toCssClasses($classes)) }}
    @disabled($disabled)
    @if ($invalid) aria-invalid="true" data-slot="invalid" @endif
    data-slot="textarea"
    x-intersect.once="resize()"
    rows={{ $rows }}
    x-on:input.stop="resize()"
    x-on:resize.window="resize()"
    x-on:keydown="resize()"
></textarea>

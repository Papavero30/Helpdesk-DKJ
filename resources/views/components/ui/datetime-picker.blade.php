@props([
    'model',                              // Livewire property name to bind, e.g. "pauseEta"
    'value' => null,                      // current value as 'YYYY-MM-DDTHH:MM' or null
    'placeholder' => 'Select date and time',
    'minuteStep' => 5,
    'pickAction' => null,                 // optional Livewire method to call once a value is picked
    'pickArgs' => [],                     // arguments passed to pickAction
    'align' => 'start',                   // popover alignment: 'start' (default) or 'end'
])

@php $hasTrigger = isset($trigger) && trim((string) $trigger) !== ''; @endphp

{{--
    Dongker-themed date + time picker. Writes 'YYYY-MM-DDTHH:MM' to the bound
    Livewire property (deferred — sent on the next action). The popover is
    teleported to <body> so it is never clipped by a banner's overflow, and it
    auto-closes the moment a day is picked (time is chosen first via the selects).

    Two trigger modes:
      • default — renders a placeholder field button showing the chosen value.
      • <x-slot:trigger> — your own button opens the popover; pair with `pickAction`
        to fire a Livewire method (e.g. "extendSlaPause") the instant a day is picked,
        so the trigger doubles as the confirm.
--}}
<div
    x-data="{
        open: false,
        value: @js($value ?? ''),
        pickAction: @js($pickAction),
        pickArgs: @js($pickArgs),
        view: { y: 0, m: 0 },
        sel: '',
        hour: '17',
        minute: '00',
        months: ['January','February','March','April','May','June','July','August','September','October','November','December'],
        dows: ['Su','Mo','Tu','We','Th','Fr','Sa'],
        init() {
            const now = new Date();
            if (this.value) {
                const [d, t] = this.value.split('T');
                this.sel = d;
                if (t) { const [h, m] = t.split(':'); this.hour = h; this.minute = m; }
                const dt = new Date(d + 'T00:00');
                this.view = { y: dt.getFullYear(), m: dt.getMonth() };
            } else {
                this.view = { y: now.getFullYear(), m: now.getMonth() };
            }
        },
        pad(n) { return String(n).padStart(2, '0'); },
        get todayIso() { const n = new Date(); return n.getFullYear() + '-' + this.pad(n.getMonth() + 1) + '-' + this.pad(n.getDate()); },
        get headerLabel() { return this.months[this.view.m] + ' ' + this.view.y; },
        get fieldLabel() {
            if (! this.sel) return '';
            const dt = new Date(this.sel + 'T00:00');
            return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' + this.hour + ':' + this.minute;
        },
        get hours() { return Array.from({ length: 24 }, (_, i) => this.pad(i)); },
        get minutes() { const out = []; for (let i = 0; i < 60; i += {{ (int) $minuteStep }}) out.push(this.pad(i)); return out; },
        get cells() {
            const first = new Date(this.view.y, this.view.m, 1);
            const start = new Date(this.view.y, this.view.m, 1 - first.getDay());
            const out = [];
            for (let i = 0; i < 42; i++) {
                const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
                const iso = d.getFullYear() + '-' + this.pad(d.getMonth() + 1) + '-' + this.pad(d.getDate());
                out.push({
                    iso,
                    day: d.getDate(),
                    inMonth: d.getMonth() === this.view.m,
                    isToday: iso === this.todayIso,
                    isPast: iso < this.todayIso,
                    isSel: iso === this.sel,
                });
            }
            return out;
        },
        prev() { let m = this.view.m - 1, y = this.view.y; if (m < 0) { m = 11; y--; } this.view = { y, m }; },
        next() { let m = this.view.m + 1, y = this.view.y; if (m > 11) { m = 0; y++; } this.view = { y, m }; },
        commit() { if (this.sel) { this.value = this.sel + 'T' + this.hour + ':' + this.minute; $wire.set(@js($model), this.value, false); } },
        pick(c) {
            if (c.isPast) return;
            this.sel = c.iso;
            this.commit();
            this.open = false;
            if (this.pickAction) { this.$wire.call(this.pickAction, ...this.pickArgs); }
        },
    }"
    x-on:keydown.escape.window="open = false"
    class="relative {{ $hasTrigger ? 'inline-flex' : 'w-full' }}"
>
    @if($hasTrigger)
        <span x-ref="dtbtn" x-on:click.stop="open = ! open" class="inline-flex">{{ $trigger }}</span>
    @else
        <button type="button" x-ref="dtbtn" x-on:click.stop="open = ! open"
                class="flex w-full items-center justify-between gap-2 rounded-md border border-dongker-200 bg-white px-2.5 py-1.5 text-[11px] focus:border-dongker-400 focus:outline-none focus:ring-1 focus:ring-dongker-400"
                :class="fieldLabel ? 'text-gray-700' : 'text-gray-400'">
            <span x-text="fieldLabel || @js($placeholder)"></span>
            <x-ui.icon name="calendar" class="size-3.5 shrink-0 text-dongker-400" />
        </button>
    @endif

    <template x-teleport="body">
        <div x-show="open" x-cloak
             {{-- Fallback to document.body when the trigger ref is momentarily gone
                  (e.g. during a Livewire morph / wire:navigate teardown). Without it
                  x-anchor throws "no element provided to x-anchor", which crashes the
                  page and freezes every control. Matches the Sheaf popover overlay. --}}
             x-anchor.bottom-{{ $align === 'end' ? 'end' : 'start' }}.offset.6="$refs.dtbtn || document.body"
             x-on:click.outside="open = false"
             x-transition.origin.top.left
             class="z-[70] w-72 rounded-xl border border-gray-100 bg-white p-3 shadow-xl ring-1 ring-black/5">

            {{-- Time --}}
            <div class="mb-2 flex items-center gap-2">
                <x-ui.icon name="clock" class="size-4 text-dongker-400" />
                <select x-model="hour" x-on:change="commit()"
                        class="rounded-md border border-dongker-200 bg-white px-2 py-1 text-xs text-gray-700 focus:ring-1 focus:ring-dongker-400">
                    <template x-for="h in hours" :key="h"><option :value="h" x-text="h"></option></template>
                </select>
                <span class="text-sm font-bold text-gray-400">:</span>
                <select x-model="minute" x-on:change="commit()"
                        class="rounded-md border border-dongker-200 bg-white px-2 py-1 text-xs text-gray-700 focus:ring-1 focus:ring-dongker-400">
                    <template x-for="m in minutes" :key="m"><option :value="m" x-text="m"></option></template>
                </select>
                <span class="ml-auto text-[10px] font-medium text-gray-400">24h</span>
            </div>

            {{-- Month nav --}}
            <div class="mb-1 flex items-center justify-between">
                <button type="button" x-on:click="prev()" class="rounded-md p-1 text-gray-500 transition hover:bg-dongker-50 hover:text-dongker-600">
                    <x-ui.icon name="chevron-left" class="size-4" />
                </button>
                <span class="text-xs font-bold text-dongker-700" x-text="headerLabel"></span>
                <button type="button" x-on:click="next()" class="rounded-md p-1 text-gray-500 transition hover:bg-dongker-50 hover:text-dongker-600">
                    <x-ui.icon name="chevron-right" class="size-4" />
                </button>
            </div>

            {{-- Day-of-week --}}
            <div class="grid grid-cols-7 text-center text-[10px] font-semibold text-gray-400">
                <template x-for="d in dows" :key="d"><span class="py-1" x-text="d"></span></template>
            </div>

            {{-- Days --}}
            <div class="grid grid-cols-7 gap-0.5 text-center">
                <template x-for="c in cells" :key="c.iso">
                    <button type="button" x-on:click="pick(c)" :disabled="c.isPast"
                            class="aspect-square rounded-md text-xs transition"
                            :class="{
                                'cursor-not-allowed text-gray-300': c.isPast,
                                'text-gray-300': ! c.inMonth && ! c.isPast,
                                'bg-dongker-600 font-bold text-white': c.isSel,
                                'text-gray-700 hover:bg-dongker-50': ! c.isSel && ! c.isPast && c.inMonth,
                                'ring-1 ring-dongker-300': c.isToday && ! c.isSel,
                            }"
                            x-text="c.day"></button>
                </template>
            </div>
        </div>
    </template>
</div>

<div>
    <x-ui.breadcrumb />

    {{-- Tabs + AllTicket/MyTicket switch (sejajar) --}}
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <div class="flex flex-wrap items-center gap-1 p-1 rounded-xl bg-gray-100 w-fit">
            @foreach([
                'all'         => 'All',
                'open'        => 'Open',
                'in_progress' => 'In Progress',
                'close'       => 'Close',
                'recurring'   => 'Repetitive',
            ] as $tabKey => $tabLabel)
                <button type="button" wire:click="setTab('{{ $tabKey }}')"
                        @class([
                            'inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-sm font-semibold transition',
                            'bg-dongker-600 text-white shadow-sm' => $statusTab === $tabKey,
                            'text-gray-600 hover:text-dongker-600' => $statusTab !== $tabKey,
                        ])>
                    {{ $tabLabel }}
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[10px] font-bold',
                        'bg-white/25 text-white' => $statusTab === $tabKey,
                        'bg-white text-gray-500' => $statusTab !== $tabKey,
                    ])>{{ $tabCounts[$tabKey] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Right cluster: New Ticket button + AllTicket/MyTicket toggle (kanan, sejajar tabs) --}}
        <div class="flex items-center gap-3">
            @if(auth()->user()->isAdmin())
                <button type="button"
                        x-on:click="$dispatch('open-modal', { id: 'new-ticket-as-admin-modal' })"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-dongker-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-dongker-500 hover:shadow-md">
                    <x-ui.icon name="plus" class="size-4 text-white" />
                    New Ticket
                </button>
            @endif

        @unless($forManager)
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold {{ ! $myTicketsMode ? 'text-dongker-700' : 'text-gray-400' }}">All Tickets</span>
            <button type="button" wire:click="toggleMyTicketsMode"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors
                           {{ $myTicketsMode ? 'bg-dongker-600' : 'bg-gray-300' }}"
                    role="switch" aria-checked="{{ $myTicketsMode ? 'true' : 'false' }}">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform
                             {{ $myTicketsMode ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
            </button>
            <span class="text-xs font-semibold {{ $myTicketsMode ? 'text-dongker-700' : 'text-gray-400' }}">My Tickets</span>
        </div>
        @endunless
        </div>
    </div>

    @php
        $activeFilterCount = count($filterKategoris) + count($filterLokasis) + ($dateMode ? 1 : 0);
        $hasSort = $sortBy !== 'id_desc';
    @endphp

    {{-- Search + controls --}}
    <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
        <div class="relative flex-1">
            <x-ui.icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search by description, category, or requester name..."
                   class="w-full rounded-lg border border-gray-200 bg-white pl-9 pr-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Filter popover --}}
            <x-ui.popover>
                <x-ui.popover.trigger>
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:border-dongker-300 hover:text-dongker-600">
                        <x-ui.icon name="funnel" class="size-4" />
                        Filter
                        @if($activeFilterCount > 0)
                            <span class="rounded-full bg-dongker-100 px-2 py-0.5 text-[10px] font-semibold text-dongker-700">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                </x-ui.popover.trigger>
                <x-ui.popover.overlay class="!w-72 p-4">
                    <div class="space-y-4"
                         x-data="{
                             stagedKategoris: @js($filterKategoris),
                             stagedLokasis: @js($filterLokasis),
                             stagedDateMode: @js($dateMode ?? ''),
                             stagedDateExact: @js($dateExact ?? ''),
                             stagedDateStart: @js($dateStart ?? ''),
                             stagedDateEnd: @js($dateEnd ?? ''),
                             applied() {
                                 $wire.set('filterKategoris', this.stagedKategoris, false);
                                 $wire.set('filterLokasis', this.stagedLokasis, false);
                                 $wire.set('dateMode', this.stagedDateMode || null, false);
                                 $wire.set('dateExact', this.stagedDateExact || null, false);
                                 $wire.set('dateStart', this.stagedDateStart || null, false);
                                 $wire.set('dateEnd', this.stagedDateEnd || null, false);
                                 $wire.applyFilters();
                                 hide();
                             },
                             resetStaged() {
                                 this.stagedKategoris = [];
                                 this.stagedLokasis = [];
                                 this.stagedDateMode = '';
                                 this.stagedDateExact = '';
                                 this.stagedDateStart = '';
                                 this.stagedDateEnd = '';
                                 $wire.resetFilters();
                             }
                         }"
                         x-on:filters-reset.window="
                             stagedKategoris = []; stagedLokasis = [];
                             stagedDateMode = ''; stagedDateExact = ''; stagedDateStart = ''; stagedDateEnd = '';
                         ">
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Plant</div>
                            <div class="mt-2 space-y-2">
                                @foreach($lokasis as $lok)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" value="{{ $lok->id }}" x-model="stagedLokasis"
                                               class="size-4 rounded border-gray-300 text-dongker-600 focus:ring-dongker-400" />
                                        <span class="text-sm text-gray-700">{{ $lok->nama_lokasi }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Category</div>
                            <div class="mt-2 space-y-2">
                                @foreach($categories as $cat)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" value="{{ $cat->id }}" x-model="stagedKategoris"
                                               class="size-4 rounded border-gray-300 text-dongker-600 focus:ring-dongker-400" />
                                        <span class="text-sm text-gray-700">{{ $cat->nama_kategori }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Date</div>
                            <div class="mt-2 space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="" x-model="stagedDateMode"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">All time</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="exact" x-model="stagedDateMode"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Exact Date</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="range" x-model="stagedDateMode"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Date Range</span>
                                </label>
                            </div>
                            <div class="mt-2 space-y-2">
                                <div x-show="stagedDateMode === 'exact'">
                                    <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1 block">Date</label>
                                    <input type="date" x-model="stagedDateExact"
                                           class="w-full rounded-lg border border-dongker-100 bg-dongker-50/30 px-3 py-2 text-sm focus:ring-2 focus:ring-dongker-400"
                                           style="accent-color:#0E4260;" />
                                </div>
                                <div x-show="stagedDateMode === 'range'" class="space-y-2">
                                    <div>
                                        <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1 block">Start Date</label>
                                        <input type="date" x-model="stagedDateStart"
                                               class="w-full rounded-lg border border-dongker-100 bg-dongker-50/30 px-3 py-2 text-sm focus:ring-2 focus:ring-dongker-400"
                                               style="accent-color:#0E4260;" />
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1 block">End Date</label>
                                        <input type="date" x-model="stagedDateEnd"
                                               class="w-full rounded-lg border border-dongker-100 bg-dongker-50/30 px-3 py-2 text-sm focus:ring-2 focus:ring-dongker-400"
                                               style="accent-color:#0E4260;" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                            <button type="button" x-on:click="resetStaged()"
                                    class="text-xs font-semibold text-gray-400 hover:text-dongker-600">
                                Reset
                            </button>
                            <button type="button" x-on:click="applied()"
                                    class="inline-flex items-center rounded-lg bg-dongker-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-dongker-500">
                                Apply
                            </button>
                        </div>
                    </div>
                </x-ui.popover.overlay>
            </x-ui.popover>

            {{-- Sort popover --}}
            <x-ui.popover>
                <x-ui.popover.trigger>
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:border-dongker-300 hover:text-dongker-600">
                        <x-ui.icon name="bars-arrow-down" class="size-4" />
                        Sort
                        @if($hasSort)
                            <span class="rounded-full bg-dongker-100 px-2 py-0.5 text-[10px] font-semibold text-dongker-700">1</span>
                        @endif
                    </button>
                </x-ui.popover.trigger>
                <x-ui.popover.overlay class="!w-72 p-4">
                    <div class="space-y-4"
                         x-data="{
                             stagedSortBy: @js($sortBy),
                             applied() {
                                 $wire.set('sortBy', this.stagedSortBy, false);
                                 $wire.applySort();
                                 hide();
                             },
                             resetStaged() {
                                 this.stagedSortBy = 'id_desc';
                                 $wire.resetSort();
                             }
                         }"
                         x-on:sort-reset.window="stagedSortBy = 'id_desc';">
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Sort by</div>
                            <div class="mt-2 space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="id_desc" x-model="stagedSortBy"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Ticket ID (Newest first)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="id_asc" x-model="stagedSortBy"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Ticket ID (Oldest first)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="kategori_asc" x-model="stagedSortBy"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Category (A &rarr; Z)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="kategori_desc" x-model="stagedSortBy"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Category (Z &rarr; A)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="karyawan_asc" x-model="stagedSortBy"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Requester (A &rarr; Z)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="karyawan_desc" x-model="stagedSortBy"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Requester (Z &rarr; A)</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                            <button type="button" x-on:click="resetStaged()"
                                    class="text-xs font-semibold text-gray-400 hover:text-dongker-600">
                                Reset
                            </button>
                            <button type="button" x-on:click="applied()"
                                    class="inline-flex items-center rounded-lg bg-dongker-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-dongker-500">
                                Apply
                            </button>
                        </div>
                    </div>
                </x-ui.popover.overlay>
            </x-ui.popover>
        </div>
    </div>

    {{-- Ticket list --}}
    <div class="flex flex-col gap-3 stagger-children min-h-[60vh]">
        @forelse($tikets as $tiket)
            @php $hasUnread = ($tiket->unread_count ?? 0) > 0; @endphp
            <a href="/ticket/{{ $tiket->id }}" wire:navigate
               class="block bg-white rounded-xl p-5 transition-all duration-200 hover:shadow-md group
                      {{ $hasUnread ? 'border border-dongker-300 ring-1 ring-dongker-100' : 'border border-gray-200 hover:border-dongker-200' }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-2 flex-1 min-w-0">
                        @if($hasUnread)
                            <span class="mt-1.5 size-2.5 rounded-full bg-red-500 animate-pulse-soft shrink-0"
                                  title="{{ $tiket->unread_count }} new update(s)"></span>
                        @else
                            <span class="mt-1.5 size-2.5 shrink-0"></span>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="text-[15px] {{ $hasUnread ? 'font-extrabold' : 'font-bold' }} text-dongker-700">#TKT{{ str_pad($tiket->id, 2, '0', STR_PAD_LEFT) }}</span>
                                @if($tiket->statusTiket)
                                    @php $statusBadgeClass = \App\Models\StatusTiketModel::badgeClass($tiket->statusTiket->nama_status); @endphp
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusBadgeClass }}">{{ $tiket->statusTiket->nama_status }}</span>
                                @endif
                                @if($tiket->kategori)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-dongker-100 text-dongker-700">{{ $tiket->kategori->nama_kategori }}</span>
                                    @unless($tiket->kategori->is_active)
                                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-gray-100 text-gray-500">arsip</span>
                                    @endunless
                                @endif
                                @if($tiket->berulang)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-orange-100 text-orange-600 inline-flex items-center gap-1">
                                        <x-ui.icon name="arrow-path" class="size-3" />
                                        Repetitive
                                    </span>
                                @endif
                            </div>
                            <p class="text-[15px] text-gray-700 truncate">{{ $tiket->deskripsi }}</p>
                            <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="map-pin" class="size-3.5 text-gray-400" />
                                    {{ $tiket->lokasi?->nama_lokasi ?? '—' }}
                                    @if($tiket->lokasi && ! $tiket->lokasi->is_active)
                                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-gray-100 text-gray-500">arsip</span>
                                    @endif
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="calendar" class="size-3.5 text-gray-400" />
                                    {{ $tiket->created_at->format('d/m/Y') }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="user" class="size-3.5 text-gray-400" />
                                    Requested by:
                                    <span class="font-semibold text-dongker-600 ml-0.5">{{ $tiket->user?->karyawan?->nama ?? $tiket->user?->name ?? '—' }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-1.5 shrink-0">
                        <span class="text-xs text-gray-400 group-hover:text-dongker-500 transition-colors">
                            View Details &rarr;
                        </span>
                        @if($tiket->id_penanggung_jawab && $tiket->assignedAdmin)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-dongker-50 text-dongker-700 text-[10px] font-semibold border border-dongker-100">
                                <x-ui.icon name="user-circle" class="size-3" />
                                Taken by: {{ $tiket->assignedAdmin->karyawan?->nama ?? $tiket->assignedAdmin->name }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-50 text-gray-400 text-[10px] font-semibold italic">
                                Unassigned
                            </span>
                        @endif
                        @if($forManager && ! $tiket->id_penanggung_jawab && $tiket->statusTiket?->nama_status === 'Open')
                            @if($allAdmins->isEmpty())
                                <span class="text-[11px] text-gray-400 italic">No admins available</span>
                            @else
                                {{-- Inside an <a wire:navigate> card: .prevent.stop keeps clicks here from navigating (selects open on mousedown, unaffected). --}}
                                <div x-data="{ target: '' }" x-on:click.prevent.stop class="flex items-center gap-2">
                                    <select x-model="target" class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 focus:ring-2 focus:ring-dongker-400">
                                        <option value="">Select admin</option>
                                        @foreach($allAdmins as $admin)
                                            <option value="{{ $admin->id }}">{{ $admin->karyawan?->nama ?? ('User #'.$admin->id) }} · {{ $admin->karyawan?->lokasi?->nama_lokasi ?? '—' }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button"
                                            x-on:click="if (target) { $wire.assignUnassigned({{ $tiket->id }}, parseInt(target)); target = '' }"
                                            class="rounded-lg bg-dongker-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-dongker-500">Assign</button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="text-center py-16">
                <x-ui.icon name="ticket" class="size-12 text-gray-300 mx-auto mb-3" />
                <p class="text-sm text-gray-400">No tickets in this view</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $tikets->links() }}
    </div>

    @unless($forManager)
    {{-- New Ticket on Behalf modal (admin creates ticket for another karyawan) --}}
    <x-ui.modal id="new-ticket-as-admin-modal" backdrop="blur" width="2xl"
                heading="Create Ticket on Behalf"
                description="File a ticket for another karyawan (e.g., a report relayed via WhatsApp).">
        <livewire:admin.new-ticket-as-admin :wire:key="'new-ticket-as-admin-form-' . now()->timestamp" />
    </x-ui.modal>
    @endunless
</div>

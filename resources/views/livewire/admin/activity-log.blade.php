<div>
    {{-- Summary Cards (clickable → drill-down modal) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 stagger-children">
        @php
            $cards = [
                ['key' => 'total',          'label' => 'Total Activities',     'count' => $ringkasan['total'],         'icon' => 'clipboard-document-list', 'color' => 'dongker'],
                ['key' => 'today',          'label' => "Today's Activities",   'count' => $ringkasan['hari_ini'],      'icon' => 'calendar',                'color' => 'lilac'],
                ['key' => 'new_tickets',    'label' => 'New Tickets Today',    'count' => $ringkasan['tiket_baru'],    'icon' => 'inbox',                   'color' => 'amber'],
                ['key' => 'status_changes', 'label' => 'Status Changes Today', 'count' => $ringkasan['status_diubah'], 'icon' => 'arrow-path',              'color' => 'blue'],
            ];
        @endphp
        @foreach($cards as $c)
            <button type="button" wire:click="openSummaryModal('{{ $c['key'] }}')"
                    class="text-left rounded-xl border border-gray-200 bg-white p-4 transition hover:border-dongker-300 hover:shadow-sm cursor-pointer">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-2xl font-bold text-{{ $c['color'] }}-600">{{ $c['count'] }}</span>
                    <x-ui.icon name="{{ $c['icon'] }}" class="size-5 text-{{ $c['color'] }}-400" />
                </div>
                <span class="text-sm text-gray-500">{{ $c['label'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Tabs (by Action Category) --}}
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <div class="flex flex-wrap items-center gap-1 p-1 rounded-xl bg-gray-100 w-fit">
            @foreach([
                'all'           => 'All',
                'tickets'       => 'Tickets',
                'comments'      => 'Comments',
                'feedback'      => 'Feedback',
                'repetitive'    => 'Repetitive',
                'configuration' => 'Configuration',
            ] as $tabKey => $tabLabel)
                <button type="button" wire:click="setTab('{{ $tabKey }}')"
                        @class([
                            'inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-sm font-semibold transition',
                            'bg-dongker-600 text-white shadow-sm' => $activeTab === $tabKey,
                            'text-gray-600 hover:text-dongker-600' => $activeTab !== $tabKey,
                        ])>
                    {{ $tabLabel }}
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[10px] font-bold',
                        'bg-white/25 text-white' => $activeTab === $tabKey,
                        'bg-white text-gray-500' => $activeTab !== $tabKey,
                    ])>{{ $tabCounts[$tabKey] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @php
        $activeFilterCount = count($filterAksi) + count($filterLokasis) + ($dateMode ? 1 : 0);
        $hasSort = $sortBy !== 'created_desc';

        $aksiOptions = [
            'ticket_created'               => 'Ticket Created',
            'ticket_assigned'              => 'Ticket Assigned',
            'status_changed'               => 'Status Changed',
            'reply_posted'                 => 'Reply Posted',
            'comment_added'                => 'Comment Added',
            'feedback_solved'              => 'Feedback: Solved',
            'feedback_not_resolved'        => 'Feedback: Not Resolved',
            'ticket_rated'                 => 'Ticket Rated',
            'rating_submitted'             => 'Rating Submitted',
            'auto_resolved'                => 'Auto-Closed',
            'repetitive_off_requested'     => 'Repetitive: Admin Requested OFF',
            'repetitive_off_accepted'      => 'Repetitive: User Accepted',
            'repetitive_off_refused'       => 'Repetitive: User Refused',
            'repetitive_off_cancelled'     => 'Repetitive: Admin Conceded',
            'repetitive_off_auto_accepted' => 'Repetitive: Auto-Accepted (Timeout)',
            'sla_pause_requested'          => 'Deadline Pause Requested',
            'sla_pause_approved'           => 'Deadline Pause Approved',
            'sla_pause_auto_approved'      => 'Deadline Pause Auto-Approved',
            'sla_pause_rejected'           => 'Deadline Pause Rejected',
            'sla_pause_resumed'            => 'Deadline Resumed',
            'sla_pause_extended'           => 'Deadline Pause Extended',
        ];
    @endphp

    {{-- Search + Filter + Sort --}}
    <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
        <div class="relative flex-1">
            <x-ui.icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search by ticket ID, description, or user name..."
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
                <x-ui.popover.overlay class="!w-80 p-4">
                    <div class="space-y-4 max-h-[60vh] overflow-y-auto"
                         x-data="{
                             stagedAksi: @js($filterAksi),
                             stagedLokasis: @js($filterLokasis),
                             stagedDateMode: @js($dateMode ?? ''),
                             stagedDateExact: @js($dateExact ?? ''),
                             stagedDateStart: @js($dateStart ?? ''),
                             stagedDateEnd: @js($dateEnd ?? ''),
                             applied() {
                                 $wire.set('filterAksi', this.stagedAksi, false);
                                 $wire.set('filterLokasis', this.stagedLokasis, false);
                                 $wire.set('dateMode', this.stagedDateMode || null, false);
                                 $wire.set('dateExact', this.stagedDateExact || null, false);
                                 $wire.set('dateStart', this.stagedDateStart || null, false);
                                 $wire.set('dateEnd', this.stagedDateEnd || null, false);
                                 $wire.applyFilters();
                                 hide();
                             },
                             resetStaged() {
                                 this.stagedAksi = [];
                                 this.stagedLokasis = [];
                                 this.stagedDateMode = '';
                                 this.stagedDateExact = '';
                                 this.stagedDateStart = '';
                                 this.stagedDateEnd = '';
                                 $wire.resetFilters();
                             }
                         }"
                         x-on:filters-reset.window="
                             stagedAksi = []; stagedLokasis = [];
                             stagedDateMode = ''; stagedDateExact = ''; stagedDateStart = ''; stagedDateEnd = '';
                         ">
                        {{-- Action --}}
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Action</div>
                            <div class="mt-2 space-y-2">
                                @foreach($aksiOptions as $aksiKey => $aksiLabel)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" value="{{ $aksiKey }}" x-model="stagedAksi"
                                               class="size-4 rounded border-gray-300 text-dongker-600 focus:ring-dongker-400" />
                                        <span class="text-sm text-gray-700">{{ $aksiLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Plant --}}
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

                        {{-- Period --}}
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Period</div>
                            <div class="mt-2 space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="" x-model="stagedDateMode"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">All time</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="today" x-model="stagedDateMode"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">Today</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="week" x-model="stagedDateMode"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">This Week</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" value="month" x-model="stagedDateMode"
                                           class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                    <span class="text-sm text-gray-700">This Month</span>
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
                                 this.stagedSortBy = 'created_desc';
                                 $wire.resetSort();
                             }
                         }"
                         x-on:sort-reset.window="stagedSortBy = 'created_desc';">
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Sort by</div>
                            <div class="mt-2 space-y-2">
                                @foreach([
                                    'created_desc' => 'Newest first',
                                    'created_asc'  => 'Oldest first',
                                    'aksi_asc'     => 'Action (A → Z)',
                                    'aksi_desc'    => 'Action (Z → A)',
                                    'tiket_asc'    => 'Ticket # (asc)',
                                    'tiket_desc'   => 'Ticket # (desc)',
                                    'actor_asc'    => 'Actor name (A → Z)',
                                    'actor_desc'   => 'Actor name (Z → A)',
                                ] as $sortKey => $sortLabel)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="{{ $sortKey }}" x-model="stagedSortBy"
                                               class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                        <span class="text-sm text-gray-700">{{ $sortLabel }}</span>
                                    </label>
                                @endforeach
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

    {{-- Activity Table --}}
    <div class="bg-white border border-gray-200 rounded-xl w-full overflow-hidden">
        @php
            // Status column removed — not needed in the audit view.
            $showStatusCol = false;
        @endphp
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider w-12"></th>
                        <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Action</th>
                        @if($showStatusCol)
                            <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                        @endif
                        <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Ticket</th>
                        <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider hidden lg:table-cell">Detail</th>
                        <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">By</th>
                        <th class="text-right py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Time</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-50">
                    @forelse($logs as $log)
                        @php
                            $iconMap = [
                                'ticket_created'               => ['icon' => 'plus-circle',            'bg' => 'bg-amber-100',  'text' => 'text-amber-600'],
                                'ticket_assigned'              => ['icon' => 'user-plus',              'bg' => 'bg-dongker-100','text' => 'text-dongker-600'],
                                'status_changed'               => ['icon' => 'arrow-path',             'bg' => 'bg-blue-100',   'text' => 'text-blue-600'],
                                'reply_posted'                 => ['icon' => 'chat-bubble-left-right', 'bg' => 'bg-lilac-100',  'text' => 'text-lilac-600'],
                                'comment_added'                => ['icon' => 'chat-bubble-left-right', 'bg' => 'bg-lilac-100',  'text' => 'text-lilac-600'],
                                'feedback_solved'              => ['icon' => 'check-circle',           'bg' => 'bg-green-100',  'text' => 'text-green-600'],
                                'feedback_not_resolved'        => ['icon' => 'exclamation-circle',     'bg' => 'bg-red-100',    'text' => 'text-red-600'],
                                'auto_resolved'                => ['icon' => 'clock',                  'bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
                                'ticket_rated'                 => ['icon' => 'star',                   'bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'],
                                'rating_submitted'             => ['icon' => 'star',                   'bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'],
                                'repetitive_off_requested'     => ['icon' => 'arrow-path',             'bg' => 'bg-orange-100', 'text' => 'text-orange-600'],
                                'repetitive_off_accepted'      => ['icon' => 'check-circle',           'bg' => 'bg-green-100',  'text' => 'text-green-600'],
                                'repetitive_off_refused'       => ['icon' => 'x-circle',               'bg' => 'bg-red-100',    'text' => 'text-red-600'],
                                'repetitive_off_cancelled'     => ['icon' => 'shield-check',           'bg' => 'bg-blue-100',   'text' => 'text-blue-600'],
                                'repetitive_off_auto_accepted' => ['icon' => 'clock',                  'bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
                                'sla_pause_requested'          => ['icon' => 'pause-circle',            'bg' => 'bg-amber-100',  'text' => 'text-amber-600'],
                                'sla_pause_approved'           => ['icon' => 'check-circle',            'bg' => 'bg-green-100',  'text' => 'text-green-600'],
                                'sla_pause_auto_approved'      => ['icon' => 'clock',                   'bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
                                'sla_pause_rejected'           => ['icon' => 'x-circle',                'bg' => 'bg-red-100',    'text' => 'text-red-600'],
                                'sla_pause_resumed'            => ['icon' => 'play-circle',             'bg' => 'bg-blue-100',   'text' => 'text-blue-600'],
                                'sla_pause_extended'           => ['icon' => 'clock',                   'bg' => 'bg-orange-100', 'text' => 'text-orange-600'],
                            ];
                            $style = $iconMap[$log->aksi] ?? ['icon' => 'information-circle', 'bg' => 'bg-gray-100', 'text' => 'text-gray-600'];

                            $aksiLabel = match($log->aksi) {
                                'ticket_created'               => 'Ticket Created',
                                'ticket_assigned'              => 'Ticket Assigned',
                                'status_changed'               => 'Status Changed',
                                'reply_posted', 'comment_added' => 'Comment Added',
                                'feedback_solved'              => 'Feedback: Solved',
                                'feedback_not_resolved'        => 'Feedback: Not Resolved',
                                'rating_submitted', 'ticket_rated' => 'Ticket Rated',
                                'auto_resolved'                => 'Auto-Closed',
                                'repetitive_off_requested'     => 'Repetitive: Admin Requested OFF',
                                'repetitive_off_accepted'      => 'Repetitive: User Accepted',
                                'repetitive_off_refused'       => 'Repetitive: User Refused',
                                'repetitive_off_cancelled'     => 'Repetitive: Admin Conceded',
                                'repetitive_off_auto_accepted' => 'Repetitive: Auto-Accepted (Timeout)',
                                'sla_pause_requested'          => 'Deadline Pause Requested',
                                'sla_pause_approved'           => 'Deadline Pause Approved',
                                'sla_pause_auto_approved'      => 'Deadline Pause Auto-Approved',
                                'sla_pause_rejected'           => 'Deadline Pause Rejected',
                                'sla_pause_resumed'            => 'Deadline Resumed',
                                'sla_pause_extended'           => 'Deadline Pause Extended',
                                default                        => $log->aksi,
                            };
                        @endphp

                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-3 px-4">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $style['bg'] }}">
                                    <x-ui.icon name="{{ $style['icon'] }}" class="size-4 {{ $style['text'] }}" />
                                </div>
                            </td>

                            <td class="py-3 px-4">
                                <span class="font-semibold text-[#1E293B]">{{ $aksiLabel }}</span>
                            </td>

                            @if($showStatusCol)
                                <td class="py-3 px-4">
                                    @php
                                        $badgeClass = fn ($s) => match ($s) {
                                            'Repetitive'     => 'border-orange-200 bg-orange-50 text-orange-600',
                                            'Not Repetitive' => 'border-gray-200 bg-gray-50 text-gray-500',
                                            'Pending Review' => 'border-amber-200 bg-amber-50 text-amber-600',
                                            default          => 'border-gray-200 text-gray-600',
                                        };
                                    @endphp
                                    @if($log->status_lama && $log->status_baru)
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $badgeClass($log->status_lama) }}">{{ $log->status_lama }}</span>
                                            <x-ui.icon name="arrow-right" class="size-3 text-gray-300" />
                                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $badgeClass($log->status_baru) }}">{{ $log->status_baru }}</span>
                                        </div>
                                    @elseif($log->status_baru)
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $badgeClass($log->status_baru) }}">{{ $log->status_baru }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @endif

                            <td class="py-3 px-4">
                                @if($log->tiket)
                                    <div class="font-semibold text-[#0E4260]">Ticket #{{ $log->tiket->id }}</div>
                                    @if($log->tiket->user?->karyawan)
                                        <div class="text-xs text-[#64748B]">{{ $log->tiket->user->karyawan->nama }}</div>
                                    @endif
                                @else
                                    <div class="font-semibold text-[#0E4260]">{{ ucfirst($log->entity_type ?? 'Config') }}</div>
                                    <div class="text-xs text-[#64748B]">Configuration</div>
                                @endif
                            </td>

                            <td class="py-3 px-4 hidden lg:table-cell align-top">
                                @php $detail = $log->keterangan ?? '—'; @endphp
                                @if(\Illuminate\Support\Str::length($detail) > 60)
                                    <div x-data="{ open: false }" class="max-w-md">
                                        <button type="button" x-on:click="open = ! open"
                                                class="group flex w-full items-start gap-1.5 text-left text-xs text-[#64748B] transition hover:text-dongker-600">
                                            <span class="block" :class="open ? 'whitespace-normal break-words' : 'truncate'">{{ $detail }}</span>
                                            <x-ui.icon name="chevron-down" class="mt-0.5 size-3 shrink-0 text-gray-400 transition-transform group-hover:text-dongker-500" x-bind:class="open && 'rotate-180'" />
                                        </button>
                                    </div>
                                @else
                                    <p class="max-w-xs text-xs text-[#64748B]">{{ $detail }}</p>
                                @endif
                            </td>

                            <td class="py-3 px-4">
                                @if($log->user)
                                    <span class="text-sm font-medium text-[#1E293B]">{{ $log->user->karyawan?->nama ?? $log->user->name }}</span>
                                @else
                                    <span class="text-sm text-[#94A3B8]">System</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-right">
                                <div class="text-xs text-[#64748B] whitespace-nowrap">{{ $log->created_at->diffForHumans() }}</div>
                                <div class="text-[10px] text-[#94A3B8]">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showStatusCol ? 7 : 6 }}" class="py-16 text-center">
                                <x-ui.icon name="clipboard-document-list" class="mx-auto size-10 text-gray-200 mb-3" />
                                <p class="text-gray-400 font-medium">No activities recorded yet</p>
                                <p class="text-xs text-gray-300 mt-1">Activities will appear as tickets are created or processed.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Load More --}}
        @if($logs->count() >= $jumlahTampil)
            <div class="py-4 border-t border-gray-100 text-center">
                <button
                    wire:click="muatLebihBanyak"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-500 transition hover:bg-dongker-50 hover:text-dongker-600 hover:border-dongker-200"
                >
                    <x-ui.icon name="arrow-down" class="size-4" />
                    Load More
                </button>
            </div>
        @endif
    </div>

    {{-- ============================================
         Summary Drill-Down Modal (Sheaf pattern — auto teleport to body + scroll lock)
         Trigger: klik summary card di atas
         Isi: mini-table activity sesuai metric, paginated 10/page
         ============================================ --}}
    @php
        // Static class strings — Tailwind purge-safe (no dynamic class composition)
        $modalConfig = match($summaryModal) {
            'total' => [
                'title' => 'All Activities',
                'description' => 'Complete audit trail across all tickets and actions',
                'icon' => 'clipboard-document-list',
                'heroBg' => 'bg-dongker-50 border-dongker-100',
                'heroIconBg' => 'border-dongker-100',
                'heroIconColor' => 'text-dongker-600',
                'heroCount' => 'text-dongker-700',
            ],
            'today' => [
                'title' => "Today's Activities",
                'description' => 'Actions recorded within the last 24 hours',
                'icon' => 'calendar',
                'heroBg' => 'bg-dongker-50 border-dongker-100',
                'heroIconBg' => 'border-dongker-100',
                'heroIconColor' => 'text-dongker-600',
                'heroCount' => 'text-dongker-700',
            ],
            'new_tickets' => [
                'title' => 'New Tickets Today',
                'description' => 'Tickets created today by employees',
                'icon' => 'inbox',
                'heroBg' => 'bg-amber-50 border-amber-100',
                'heroIconBg' => 'border-amber-100',
                'heroIconColor' => 'text-amber-600',
                'heroCount' => 'text-amber-700',
            ],
            'status_changes' => [
                'title' => 'Status Changes Today',
                'description' => 'Ticket status transitions recorded today',
                'icon' => 'arrow-path',
                'heroBg' => 'bg-blue-50 border-blue-100',
                'heroIconBg' => 'border-blue-100',
                'heroIconColor' => 'text-blue-600',
                'heroCount' => 'text-blue-700',
            ],
            default => [
                'title' => 'Activities',
                'description' => '',
                'icon' => 'clipboard-document-list',
                'heroBg' => 'bg-gray-50 border-gray-100',
                'heroIconBg' => 'border-gray-100',
                'heroIconColor' => 'text-gray-600',
                'heroCount' => 'text-gray-700',
            ],
        };
    @endphp
    <div
        x-data="{ open: @entangle('showSummaryModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'summary-drilldown-modal' }) : $dispatch('close-modal', { id: 'summary-drilldown-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'summary-drilldown-modal') { $wire.closeSummaryModal() }"
    >
        <x-ui.modal id="summary-drilldown-modal" width="4xl" backdrop="blur"
                    :heading="$modalConfig['title']"
                    :description="$modalConfig['description']">
            {{-- Fixed-frame container: tinggi konstan, scroll internal di tabel area --}}
            <div class="flex flex-col" style="height: 560px;">
                {{-- Hero metric strip (fixed) --}}
                <div class="shrink-0 flex items-center gap-3 rounded-xl border px-4 py-3 mb-4 {{ $modalConfig['heroBg'] }}">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-white border shadow-sm {{ $modalConfig['heroIconBg'] }}">
                        <x-ui.icon name="{{ $modalConfig['icon'] }}" class="size-5 {{ $modalConfig['heroIconColor'] }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-2xl font-extrabold leading-tight {{ $modalConfig['heroCount'] }}">{{ $modalTotal }}</div>
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">{{ Str::plural('entry', $modalTotal) }} total</div>
                    </div>
                    @if($modalTotalPages > 1)
                        <span class="text-[11px] font-semibold text-gray-400 shrink-0">
                            Showing {{ (($summaryPage - 1) * \App\Livewire\Admin\ActivityLog::SUMMARY_PER_PAGE) + 1 }}–{{ min($summaryPage * \App\Livewire\Admin\ActivityLog::SUMMARY_PER_PAGE, $modalTotal) }}
                        </span>
                    @endif
                </div>

                {{-- Mini table (flex-1: ambil sisa ruang, scroll internal) --}}
                <div class="flex-1 min-h-0 overflow-auto border border-gray-200 rounded-xl">
                    <table class="w-full text-sm table-fixed">
                        <colgroup>
                            <col style="width: 22%;" />
                            <col style="width: 16%;" />
                            <col style="width: 28%;" />
                            <col style="width: 16%;" />
                            <col style="width: 18%;" />
                        </colgroup>
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/60">
                                <th class="text-left py-3 px-4 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Action</th>
                                <th class="text-left py-3 px-4 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Ticket</th>
                                <th class="text-left py-3 px-4 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Detail</th>
                                <th class="text-left py-3 px-4 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">By</th>
                                <th class="text-right py-3 px-4 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($modalLogs as $log)
                                @php
                                    $modalAksiLabel = match($log->aksi) {
                                        'ticket_created'               => 'Ticket Created',
                                        'ticket_assigned'              => 'Ticket Assigned',
                                        'status_changed'               => 'Status Changed',
                                        'reply_posted', 'comment_added' => 'Comment Added',
                                        'feedback_solved'              => 'Feedback: Solved',
                                        'feedback_not_resolved'        => 'Feedback: Not Resolved',
                                        'rating_submitted', 'ticket_rated' => 'Ticket Rated',
                                        'auto_resolved'                => 'Auto-Closed',
                                        'repetitive_off_requested'     => 'Repetitive: Admin Requested OFF',
                                        'repetitive_off_accepted'      => 'Repetitive: User Accepted',
                                        'repetitive_off_refused'       => 'Repetitive: User Refused',
                                        'repetitive_off_cancelled'     => 'Repetitive: Admin Conceded',
                                        'repetitive_off_auto_accepted' => 'Repetitive: Auto-Accepted (Timeout)',
                                        'sla_pause_requested'          => 'Deadline Pause Requested',
                                        'sla_pause_approved'           => 'Deadline Pause Approved',
                                        'sla_pause_auto_approved'      => 'Deadline Pause Auto-Approved',
                                        'sla_pause_rejected'           => 'Deadline Pause Rejected',
                                        'sla_pause_resumed'            => 'Deadline Resumed',
                                        'sla_pause_extended'           => 'Deadline Pause Extended',
                                        default                        => $log->aksi,
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="py-3 px-4 align-top">
                                        <span class="text-xs font-semibold text-[#1E293B] line-clamp-2">{{ $modalAksiLabel }}</span>
                                    </td>
                                    <td class="py-3 px-4 align-top">
                                        @if($log->tiket)
                                            <div class="text-xs font-semibold text-dongker-600">#TKT{{ str_pad($log->tiket->id, 2, '0', STR_PAD_LEFT) }}</div>
                                        @else
                                            <div class="text-xs font-semibold text-dongker-600">{{ ucfirst($log->entity_type ?? 'Config') }}</div>
                                        @endif
                                        @if($log->tiket?->user?->karyawan)
                                            <div class="text-[10px] text-gray-500 truncate">{{ $log->tiket->user->karyawan->nama }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 align-top">
                                        <p class="text-[11px] text-gray-600 truncate">{{ $log->keterangan ?? '—' }}</p>
                                    </td>
                                    <td class="py-3 px-4 align-top">
                                        @if($log->user)
                                            <span class="text-xs text-[#1E293B] truncate block">{{ $log->user->karyawan?->nama ?? $log->user->name }}</span>
                                        @else
                                            <span class="text-xs text-gray-400 italic">System</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right align-top">
                                        <div class="text-[11px] text-gray-600 whitespace-nowrap">{{ $log->created_at->diffForHumans() }}</div>
                                        <div class="text-[9px] text-gray-400">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center">
                                        <x-ui.icon name="clipboard-document-list" class="mx-auto size-8 text-gray-200 mb-2" />
                                        <p class="text-xs text-gray-400">No activities to show</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination footer (fixed, slot tetap ada untuk jaga tinggi konsisten) --}}
                <div class="shrink-0 flex items-center justify-between gap-3 pt-4 mt-2 border-t border-gray-100 min-h-[44px]">
                    @if($modalTotalPages > 1)
                        <span class="text-xs text-gray-500">
                            Page <span class="font-semibold text-dongker-600">{{ $summaryPage }}</span> of {{ $modalTotalPages }}
                        </span>
                        <x-ui.pager :current="$summaryPage" :total="$modalTotalPages" jump="summaryGoToPage" />
                    @else
                        <span class="text-xs text-gray-400">{{ $modalTotal }} {{ Str::plural('entry', $modalTotal) }} (single page)</span>
                    @endif
                </div>
            </div>
        </x-ui.modal>
    </div>
</div>

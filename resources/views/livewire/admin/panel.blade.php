<div class="flex flex-col gap-6">
    {{-- Today metric cards (clickable → drill-down modal) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @php
            $todayCards = [
                ['metric' => 'created', 'label' => 'Created today', 'value' => $today['created_today'], 'sub' => 'tickets created', 'icon' => 'inbox-arrow-down', 'color' => '#0E4260'],
                ['metric' => 'resolved', 'label' => 'Resolved today', 'value' => $today['resolved_today'], 'sub' => 'closed by all admins', 'icon' => 'check-circle', 'color' => '#16a34a'],
                ['metric' => 'awaiting', 'label' => 'Awaiting confirmation', 'value' => $today['awaiting_confirmation'], 'sub' => 'waiting on customer', 'icon' => 'clock', 'color' => '#d97706'],
            ];
        @endphp
        @foreach($todayCards as $card)
            <button type="button" wire:click="openTodayModal('{{ $card['metric'] }}')"
                class="flex items-start justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 text-left transition-all duration-200 hover:shadow-md hover:-translate-y-0.5">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</div>
                    <div class="mt-1 text-2xl font-extrabold" style="color: {{ $card['color'] }};">{{ $card['value'] }}</div>
                    <div class="text-[11px] text-gray-400">{{ $card['sub'] }}</div>
                </div>
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg" style="background: {{ $card['color'] }}1a;">
                    <x-ui.icon name="{{ $card['icon'] }}" class="size-5" style="color: {{ $card['color'] }};" />
                </span>
            </button>
        @endforeach
    </div>

    {{-- SLA at-risk (hero) --}}
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
            <h2 class="flex items-center gap-2 text-sm font-bold" style="color:#0E4260;">
                <x-ui.icon name="exclamation-triangle" class="size-4" style="color:#ef4444;" />
                SLA at risk <span class="text-gray-400 font-semibold">({{ $slaAtRisk['total'] }})</span>
            </h2>
            <a href="/admin/all-tickets" class="text-xs font-semibold text-gray-500 hover:text-dongker-600" wire:navigate>View all →</a>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($slaAtRisk['overdue']->concat($slaAtRisk['approaching']) as $t)
                @include('livewire.admin.partials.dashboard-ticket-row', ['ticket' => $t, 'context' => 'sla'])
            @empty
                <div class="flex flex-col items-center gap-2 px-5 py-10 text-center">
                    <x-ui.icon name="check-circle" class="size-6 text-gray-300" />
                    <span class="text-sm text-gray-400">Nothing overdue or approaching SLA.</span>
                </div>
            @endforelse
        </div>
    </div>

    {{-- My Queue + Needs pickup --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-bold" style="color:#0E4260;">My queue <span class="text-gray-400 font-semibold">({{ $myQueue['total'] }})</span></h2>
                <a href="/admin/all-tickets?mine=1" class="text-xs font-semibold text-gray-500 hover:text-dongker-600" wire:navigate>View all →</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($myQueue['tickets'] as $t)
                    @include('livewire.admin.partials.dashboard-ticket-row', ['ticket' => $t, 'context' => 'myqueue'])
                @empty
                    <div class="flex flex-col items-center gap-2 px-5 py-10 text-center">
                        <x-ui.icon name="inbox" class="size-6 text-gray-300" />
                        <span class="text-sm text-gray-400">Your queue is clear.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-bold" style="color:#0E4260;">Needs pickup <span class="text-gray-400 font-semibold">({{ $pickupQueue['total'] }})</span></h2>
                <a href="/admin/all-tickets?tab=open" class="text-xs font-semibold text-gray-500 hover:text-dongker-600" wire:navigate>View all →</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($pickupQueue['tickets'] as $t)
                    @include('livewire.admin.partials.dashboard-ticket-row', ['ticket' => $t, 'context' => 'pickup'])
                @empty
                    <div class="flex flex-col items-center gap-2 px-5 py-10 text-center">
                        <x-ui.icon name="inbox" class="size-6 text-gray-300" />
                        <span class="text-sm text-gray-400">No tickets waiting to be picked up.</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Repetitive needing response — simple row, hidden when empty --}}
    @if($repetitive['total'] > 0)
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 class="flex items-center gap-2 text-sm font-bold" style="color:#0E4260;">
                    <x-ui.icon name="arrow-path" class="size-4" style="color:#a855f7;" />
                    Repetitive — needs your response <span class="text-gray-400 font-semibold">({{ $repetitive['total'] }})</span>
                </h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($repetitive['tickets'] as $t)
                    <a href="/ticket/{{ $t->id }}" wire:navigate class="flex items-center justify-between px-5 py-2.5 hover:bg-gray-50">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="font-mono text-xs font-semibold" style="color:#0E4260;">#TKT{{ str_pad($t->id, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="truncate text-sm text-gray-700">User refused removal — review the repetitive request</span>
                        </div>
                        <span class="shrink-0 text-xs text-gray-400">{{ $t->kategori?->nama_kategori ?? '—' }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ════════ My Performance (personal analytics) ════════ --}}
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="flex items-center gap-2 text-base font-bold" style="color:#0E4260;">
                <x-ui.icon name="chart-bar" class="size-4" style="color:#0E4260;" />
                My Performance
            </h2>
            <div class="flex items-end gap-2">
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Start</label>
                    <input type="date" value="{{ $myPerfStart }}" max="{{ $myPerfEnd }}"
                        wire:change="updateMyPerfStart($event.target.value)"
                        wire:key="myperf-start-{{ $myPerfStart }}"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">End</label>
                    <input type="date" value="{{ $myPerfEnd }}" min="{{ $myPerfStart }}"
                        wire:change="updateMyPerfEnd($event.target.value)"
                        wire:key="myperf-end-{{ $myPerfEnd }}"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                </div>
            </div>
        </div>

        @php $hasPerf = ($myPerf['kpis']['handled'] ?? 0) > 0; @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $perfKpis = [
                    ['label' => 'Handled', 'value' => $myPerf['kpis']['handled'], 'sub' => 'assigned to me', 'color' => '#0E4260'],
                    ['label' => 'Resolved', 'value' => $myPerf['kpis']['resolved'], 'sub' => $myPerf['kpis']['resolution_rate'].'% resolution rate', 'color' => '#16a34a'],
                    ['label' => 'On-Time', 'value' => $myPerf['kpis']['on_time_pct'].'%', 'sub' => 'of resolved', 'color' => '#0E4260'],
                    ['label' => 'Avg Rating', 'value' => $myPerf['kpis']['avg_rating'] !== null ? $myPerf['kpis']['avg_rating'].' ★' : '—', 'sub' => 'satisfaction received', 'color' => '#d97706'],
                ];
            @endphp
            @foreach($perfKpis as $k)
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $k['label'] }}</div>
                    <div class="mt-1 text-2xl font-extrabold" style="color: {{ $k['color'] }}; font-variant-numeric: tabular-nums;">{{ $k['value'] }}</div>
                    <div class="text-[11px] text-gray-400">{{ $k['sub'] }}</div>
                </div>
            @endforeach
        </div>

        @if($hasPerf)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">My trend</h3>
                    <p class="text-xs text-gray-400 mb-3">Your tickets created vs resolved over the selected range.</p>
                    <div style="height:260px;"><canvas id="adminMyTrend"></canvas></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">My categories</h3>
                    <p class="text-xs text-gray-400 mb-3">Tickets you handled per category.</p>
                    <div style="height:260px;"><canvas id="adminMyCategories"></canvas></div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-700">Rating per category</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr class="text-left">
                            <th class="px-5 py-2">Category</th>
                            <th class="px-5 py-2">Avg rating</th>
                            <th class="px-5 py-2">Handled</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($myPerf['categories'] as $c)
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-2 font-medium text-gray-800">{{ $c['label'] }}</td>
                                <td class="px-5 py-2">{{ $c['avg_rating'] !== null ? $c['avg_rating'].' ★' : '—' }}</td>
                                <td class="px-5 py-2 text-gray-500">{{ $c['handled'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-10 text-center">
                <span class="text-sm text-gray-400">You haven't handled any tickets in this range yet.</span>
            </div>
        @endif
    </div>

    {{-- Today drill-down modal. Kept INSIDE the single root <div> so Livewire's
         test harness sees only one root element (mirrors the report component pattern).
         Sheaf teleports the modal to <body> at runtime. --}}
    <div
        x-data="{ open: @entangle('showTodayModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'today-drilldown-modal' }) : $dispatch('close-modal', { id: 'today-drilldown-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'today-drilldown-modal') { $wire.closeTodayModal() }"
    >
        @php
            $todayHeading = match($todayMetric) {
                'created' => 'Created today',
                'resolved' => 'Resolved today',
                'awaiting' => 'Awaiting customer confirmation',
                default => 'Tickets',
            };
        @endphp
        <x-ui.modal id="today-drilldown-modal" width="4xl" backdrop="blur"
                    :heading="$todayHeading"
                    description="Click a ticket to open it in a new tab.">
            <div class="flex flex-col h-[520px]">
                <div class="shrink-0 flex items-center justify-between rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 mb-4">
                    <div>
                        <div class="text-2xl font-extrabold" style="color:#0E4260;">{{ $todayModal['total'] }}</div>
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Tickets</div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-sm table-fixed">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr class="text-left">
                                <th class="px-3 py-2 w-[10%]">#TKT</th>
                                <th class="px-3 py-2 w-[34%]">Description</th>
                                <th class="px-3 py-2 w-[14%]">Status</th>
                                <th class="px-3 py-2 w-[16%]">Category</th>
                                <th class="px-3 py-2 w-[16%]">Requester</th>
                                <th class="px-3 py-2 w-[10%]">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($todayModal['tickets'] as $t)
                                <tr class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono text-xs">
                                        <a href="/ticket/{{ $t->id }}" target="_blank" rel="noopener" class="hover:underline" style="color:#0E4260;">#TKT{{ str_pad($t->id, 2, '0', STR_PAD_LEFT) }}</a>
                                    </td>
                                    <td class="px-3 py-2 line-clamp-2 max-w-md">{{ $t->deskripsi }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold
                                            @if($t->statusTiket?->nama_status === 'Close') bg-green-100 text-green-700
                                            @elseif($t->statusTiket?->nama_status === 'In Progress') bg-amber-100 text-amber-700
                                            @else bg-gray-100 text-gray-700 @endif">
                                            {{ $t->statusTiket?->nama_status ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ $t->kategori?->nama_kategori ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $t->user?->karyawan?->nama ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $t->created_at?->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-gray-400">No tickets in this group.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($todayModal['totalPages'] > 1)
                    <div class="shrink-0 flex items-center justify-between mt-3 border-t border-gray-100 pt-3">
                        <div class="text-xs text-gray-500">Page <span class="font-semibold" style="color:#0E4260;">{{ $todayModal['page'] }}</span> of {{ $todayModal['totalPages'] }}</div>
                        <x-ui.pager :current="$todayModal['page']" :total="$todayModal['totalPages']" jump="todayGoToPage" />
                    </div>
                @endif
            </div>
        </x-ui.modal>
    </div>
</div>

@push('scripts')
    @vite('resources/js/admin-dashboard-charts.js')
@endpush

@script
<script>
    const buildAdminDashChartData = () => {
        const raw = $wire.get('myPerfChartPayload') ?? {};
        let payload;
        try { payload = JSON.parse(JSON.stringify(raw)); } catch (e) { payload = {}; }
        return {
            trend: payload.trend ?? { labels: [], created: [], resolved: [] },
            categories: payload.categories ?? { labels: [], handled: [] },
        };
    };

    const renderAdminDashCharts = () => {
        if (window.__initAdminDashCharts) {
            window.__initAdminDashCharts(buildAdminDashChartData());
        }
    };

    const ensureAdminDashCharts = () => {
        if (window.__initAdminDashCharts) { renderAdminDashCharts(); return; }
        window.addEventListener('admin-dashboard-charts:ready', renderAdminDashCharts, { once: true });
    };

    ensureAdminDashCharts();

    if (! window.__adminDashChartsHooked) {
        window.__adminDashChartsHooked = true;
        Livewire.hook('morphed', () => {
            if (! document.getElementById('adminMyTrend')) return;
            setTimeout(() => renderAdminDashCharts(), 50);
        });
        Livewire.hook('navigated', () => {
            window.__adminDashChartsHooked = false;
            if (window.__destroyAdminDashCharts) window.__destroyAdminDashCharts();
        });
    }
</script>
@endscript

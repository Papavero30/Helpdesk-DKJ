@php
    $awaitingConfirm = $pending['awaiting_confirmation'] ?? collect();
    $awaitingRating = $pending['awaiting_rating'] ?? collect();
    $awaitingRepetitive = $pending['awaiting_repetitive'] ?? collect();

    // Merge the three pending streams into one rows-list, each row tagged with its action type.
    $pendingRows = collect()
        ->concat($awaitingConfirm->map(fn ($t) => ['ticket' => $t, 'action' => 'confirm', 'when' => $t->siap_konfirmasi_at]))
        ->concat($awaitingRepetitive->map(fn ($t) => ['ticket' => $t, 'action' => 'repetitive', 'when' => $t->repetitive_review_admin_at]))
        ->concat($awaitingRating->map(fn ($t) => ['ticket' => $t, 'action' => 'rate', 'when' => $t->ditutup_pada]))
        ->sortByDesc(fn ($row) => $row['when'])
        ->values();

    $pendingCount = $pendingRows->count();

    $slaMetPct = $kpi['sla_met_pct'] ?? null;
    $avgHours = $kpi['avg_resolution_hours'] ?? null;
    $avgRating = $kpi['avg_rating'] ?? null;

    $avgResolutionLabel = $avgHours === null
        ? '—'
        : ($avgHours >= 24 ? round($avgHours / 24, 1).' days' : $avgHours.' hours');

    $slaOutcome = $slaOutcomeData ?? ['on_time' => 0, 'ahead' => 0, 'overtime' => 0];
    $slaTotal = $slaOutcome['on_time'] + $slaOutcome['ahead'] + $slaOutcome['overtime'];

    $statusModalHeading = match($statusModalKey ?? '') {
        'total' => 'All Tickets',
        'open' => 'Open Tickets',
        'in_progress' => 'In Progress Tickets',
        'close' => 'Closed Tickets',
        default => 'Tickets',
    };

    $cardButtonBase = 'rounded-xl border bg-white p-4 text-left transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 hover:border-dongker-200 focus:outline-none focus:ring-2 focus:ring-dongker-300';
@endphp

<div>
    {{-- ══ TIER 1: OPERATIONAL (live, snapshot) ══ --}}

    {{-- Clickable status cards → open drill-down modal --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <button type="button" wire:click="openStatusModal('total')" class="{{ $cardButtonBase }} border-gray-200">
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-dongker-600">{{ $summary['total'] }}</span>
                @if($summary['repetitive'] > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-600">
                        <x-ui.icon name="arrow-path" class="size-3" />
                        {{ $summary['repetitive'] }}
                    </span>
                @endif
            </div>
            <div class="mt-1 text-xs font-medium text-gray-500">Total Tickets</div>
        </button>

        <button type="button" wire:click="openStatusModal('open')" class="{{ $cardButtonBase }} border-gray-200">
            <span class="text-2xl font-extrabold text-amber-500">{{ $summary['open'] }}</span>
            <div class="mt-1 text-xs font-medium text-gray-500">Open</div>
        </button>

        <button type="button" wire:click="openStatusModal('in_progress')" class="{{ $cardButtonBase }} border-gray-200">
            <span class="text-2xl font-extrabold text-blue-500">{{ $summary['in_progress'] }}</span>
            <div class="mt-1 text-xs font-medium text-gray-500">In Progress</div>
        </button>

        <button type="button" wire:click="openStatusModal('close')" class="{{ $cardButtonBase }} border-gray-200">
            <span class="text-2xl font-extrabold text-green-600">{{ $summary['closed'] }}</span>
            <div class="mt-1 text-xs font-medium text-gray-500">Closed</div>
        </button>
    </div>

    {{-- Action Required callout — clean list-table, no bell/icon decoration --}}
    @if($pendingCount > 0)
        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
            <div class="flex items-baseline justify-between mb-3">
                <div>
                    <h3 class="text-sm font-bold text-dongker-700">Action Required</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ $pendingCount }} {{ $pendingCount === 1 ? 'ticket needs' : 'tickets need' }} your attention</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-100">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-[11px] uppercase tracking-wider text-gray-400">
                            <th class="px-4 py-2 font-semibold">Ticket</th>
                            <th class="px-3 py-2 font-semibold">Description</th>
                            <th class="px-3 py-2 font-semibold">Action</th>
                            <th class="px-4 py-2 font-semibold whitespace-nowrap text-right">Since</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingRows as $row)
                            @php
                                $t = $row['ticket'];
                                $action = $row['action'];
                                $when = $row['when'];
                                $actionMeta = match($action) {
                                    'confirm' => ['label' => 'Confirm resolution', 'class' => 'bg-blue-100 text-blue-700'],
                                    'rate' => ['label' => 'Give rating', 'class' => 'bg-amber-100 text-amber-700'],
                                    'repetitive' => ['label' => 'Repetitive review', 'class' => 'bg-orange-100 text-orange-700'],
                                };
                            @endphp
                            <tr wire:key="pending-{{ $action }}-{{ $t->id }}"
                                onclick="window.Livewire.navigate('/ticket/{{ $t->id }}')"
                                class="cursor-pointer border-b border-gray-50 last:border-b-0 transition hover:bg-dongker-50">
                                <td class="px-4 py-2.5 font-mono text-xs font-semibold whitespace-nowrap" style="color:#0E4260;">
                                    #TKT{{ str_pad($t->id, 2, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-3 py-2.5 max-w-[320px]">
                                    <span class="block truncate text-gray-700">{{ $t->deskripsi }}</span>
                                    @if($t->kategori)
                                        <span class="text-[10px] text-gray-400">{{ $t->kategori->nama_kategori }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $actionMeta['class'] }}">{{ $actionMeta['label'] }}</span>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-right text-[11px] text-gray-400">
                                    {{ $when ? $when->diffForHumans() : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ══ TIER 2: MY ACTIVITY (period analytics) ══ --}}

    <div class="rounded-2xl border border-gray-200 bg-white p-5 mb-6">
        <div class="flex flex-col gap-3 mb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-base font-bold text-dongker-700">My Activity</h2>
                <p class="text-xs text-gray-500">Trends, categories, and SLA quality of your tickets within the selected date range.</p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Start</span>
                    <input type="date"
                           value="{{ $myActivityStart }}"
                           wire:change="updateMyActivityStart($event.target.value)"
                           wire:key="my-act-start-{{ $myActivityStart }}"
                           max="{{ $myActivityEnd }}"
                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 focus:ring-2 focus:ring-dongker-400" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">End</span>
                    <input type="date"
                           value="{{ $myActivityEnd }}"
                           wire:change="updateMyActivityEnd($event.target.value)"
                           wire:key="my-act-end-{{ $myActivityEnd }}"
                           min="{{ $myActivityStart }}"
                           max="{{ now()->format('Y-m-d') }}"
                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 focus:ring-2 focus:ring-dongker-400" />
                </label>
            </div>
        </div>

        {{-- KPI strip (period-scoped) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
                    <x-ui.icon name="check-circle" class="size-4" />
                </div>
                <div>
                    <div class="text-lg font-extrabold text-dongker-600">{{ $slaMetPct === null ? '—' : $slaMetPct.'%' }}</div>
                    <div class="text-[11px] font-medium text-gray-500">SLA Met</div>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                    <x-ui.icon name="clock" class="size-4" />
                </div>
                <div>
                    <div class="text-lg font-extrabold text-dongker-600">{{ $avgResolutionLabel }}</div>
                    <div class="text-[11px] font-medium text-gray-500">Avg resolution</div>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-500 text-base">★</div>
                <div>
                    <div class="text-lg font-extrabold text-dongker-600">{{ $avgRating === null ? '—' : $avgRating }}</div>
                    <div class="text-[11px] font-medium text-gray-500">Avg rating given</div>
                </div>
            </div>
        </div>

        {{-- Frequency chart (full width) --}}
        <div class="grid grid-cols-1 gap-4 mb-4">
            <div class="rounded-xl border border-gray-100 bg-white p-4">
                <div class="flex items-baseline justify-between">
                    <h3 class="text-sm font-bold text-dongker-600">Request Frequency</h3>
                    <span class="text-[11px] font-medium text-gray-400">
                        @php $g = $frequencyData['granularity'] ?? 'daily'; @endphp
                        {{ $g === 'daily' ? 'daily' : 'weekly' }}
                    </span>
                </div>
                <div class="h-[180px] mt-2">
                    <canvas id="monthlyFrequencyChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Categories + SLA Outcome side-by-side --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-xl border border-gray-100 bg-white p-4">
                <h3 class="text-sm font-bold text-dongker-600">Categories</h3>
                <p class="text-[11px] text-gray-400 mb-2">Your tickets per category.</p>
                <div class="h-[180px]">
                    <canvas id="kategoriChart"></canvas>
                </div>
            </div>
            <div class="rounded-xl border border-gray-100 bg-white p-4">
                <div class="flex items-baseline justify-between">
                    <h3 class="text-sm font-bold text-dongker-600">SLA Outcome</h3>
                    <span class="text-[11px] font-medium text-gray-400">{{ $slaTotal }} closed</span>
                </div>
                <p class="text-[11px] text-gray-400 mb-2">On Time, Ahead of Schedule, Over Time.</p>
                <div class="h-[180px]">
                    <canvas id="slaOutcomeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Period ticket table --}}
    <div class="rounded-xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-bold text-dongker-600">My Tickets in Period</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">{{ $tickets->total() }} ticket{{ $tickets->total() === 1 ? '' : 's' }} created between {{ \Carbon\Carbon::parse($myActivityStart)->format('d M Y') }} – {{ \Carbon\Carbon::parse($myActivityEnd)->format('d M Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterStatus"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600 focus:ring-2 focus:ring-dongker-400">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->id }}">{{ $s->nama_status }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterKategori"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600 focus:ring-2 focus:ring-dongker-400">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                        <th class="px-5 py-3 font-semibold">Ticket</th>
                        <th class="px-3 py-3 font-semibold">Description</th>
                        <th class="px-3 py-3 font-semibold">Category</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-3 py-3 font-semibold">PIC</th>
                        <th class="px-3 py-3 font-semibold whitespace-nowrap">Created</th>
                        <th class="px-5 py-3 font-semibold text-right">Rating</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $t)
                        <tr wire:key="row-{{ $t->id }}"
                            onclick="window.Livewire.navigate('/ticket/{{ $t->id }}')"
                            class="cursor-pointer border-b border-gray-50 transition hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs font-semibold" style="color:#0E4260;">
                                #TKT{{ str_pad($t->id, 2, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-3 py-3 max-w-[260px]">
                                <span class="block truncate text-gray-700">{{ $t->deskripsi }}</span>
                            </td>
                            <td class="px-3 py-3">
                                @if($t->kategori)
                                    <span class="rounded-full bg-dongker-100 px-2 py-0.5 text-[10px] font-semibold text-dongker-700">{{ $t->kategori->nama_kategori }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @if($t->statusTiket)
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ \App\Models\StatusTiketModel::badgeClass($t->statusTiket->nama_status) }}">{{ $t->statusTiket->nama_status }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $t->assignedAdmin?->karyawan?->nama ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-400">
                                {{ $t->created_at?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if($t->penilaian)
                                    <span class="font-semibold text-amber-500">{{ $t->penilaian->nilai }} ★</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-400">
                                No tickets in this period{{ $filterStatus !== '' || $filterKategori !== '' ? ' with the current filter' : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="border-t border-gray-100 px-5 py-3">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

    {{-- Status-card drill-down modal (mirrors admin Today modal pattern). --}}
    <div
        x-data="{ open: @entangle('showStatusModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'karyawan-status-modal' }) : $dispatch('close-modal', { id: 'karyawan-status-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'karyawan-status-modal') { $wire.closeStatusModal() }"
    >
        <x-ui.modal id="karyawan-status-modal" width="4xl" backdrop="blur"
                    :heading="$statusModalHeading"
                    description="Click a ticket to open its detail page.">
            <div class="flex flex-col h-[520px]">
                <div class="shrink-0 flex items-center justify-between rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 mb-4">
                    <div>
                        <div class="text-2xl font-extrabold" style="color:#0E4260;">{{ $statusModal['total'] }}</div>
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Tickets</div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-sm table-fixed">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr class="text-left">
                                <th class="px-3 py-2 w-[9%]">#TKT</th>
                                <th class="px-3 py-2 w-[30%]">Description</th>
                                <th class="px-3 py-2 w-[14%]">Category</th>
                                <th class="px-3 py-2 w-[13%]">Status</th>
                                <th class="px-3 py-2 w-[14%]">PIC</th>
                                <th class="px-3 py-2 w-[12%]">Created</th>
                                <th class="px-3 py-2 w-[8%] text-right">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($statusModal['tickets'] as $t)
                                <tr class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono text-xs">
                                        <a href="/ticket/{{ $t->id }}" target="_blank" rel="noopener" class="hover:underline" style="color:#0E4260;">#TKT{{ str_pad($t->id, 2, '0', STR_PAD_LEFT) }}</a>
                                    </td>
                                    <td class="px-3 py-2 line-clamp-2 max-w-md">{{ $t->deskripsi }}</td>
                                    <td class="px-3 py-2">{{ $t->kategori?->nama_kategori ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        @if($t->statusTiket)
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ \App\Models\StatusTiketModel::badgeClass($t->statusTiket->nama_status) }}">{{ $t->statusTiket->nama_status }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $t->assignedAdmin?->karyawan?->nama ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ $t->created_at?->format('d M Y') }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        @if($t->penilaian)
                                            <span class="font-semibold text-amber-500">{{ $t->penilaian->nilai }} ★</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">No tickets in this group.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($statusModal['totalPages'] > 1)
                    <div class="shrink-0 flex items-center justify-between mt-3 border-t border-gray-100 pt-3">
                        <div class="text-xs text-gray-500">Page <span class="font-semibold" style="color:#0E4260;">{{ $statusModal['page'] }}</span> of {{ $statusModal['totalPages'] }}</div>
                        <x-ui.pager :current="$statusModal['page']" :total="$statusModal['totalPages']" jump="statusGoToPage" />
                    </div>
                @endif
            </div>
        </x-ui.modal>
    </div>
</div>

@push('scripts')
    @vite('resources/js/dashboard-charts.js')
@endpush

@script
<script>
    const readActivityPayload = () => {
        const raw = $wire.get('activityChartPayload') ?? {};
        return JSON.parse(JSON.stringify(raw));
    };

    const initDashboardCharts = () => {
        if (!document.getElementById('monthlyFrequencyChart')) {
            return;
        }
        if (window.__initKaryawanDashboardCharts) {
            window.__initKaryawanDashboardCharts(readActivityPayload());
        }
    };

    const ensureDashboardCharts = () => {
        if (window.__initKaryawanDashboardCharts) {
            initDashboardCharts();
            return;
        }
        window.addEventListener('karyawan-dashboard-charts:ready', initDashboardCharts, { once: true });
    };

    ensureDashboardCharts();

    if (!window.__karyawanDashboardHooked) {
        window.__karyawanDashboardHooked = true;
        Livewire.hook('morphed', () => {
            setTimeout(() => {
                ensureDashboardCharts();
            }, 50);
        });
        Livewire.hook('navigated', () => {
            if (window.__destroyKaryawanDashboardCharts) {
                window.__destroyKaryawanDashboardCharts();
            }
        });
    }
</script>
@endscript

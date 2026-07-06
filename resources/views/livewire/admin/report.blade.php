<div class="flex flex-col gap-6">
    @php
        // Computed for downstream use (table dimension header, chart captions, and
        // the PDF print data holder). The visible title band was removed — it
        // duplicated the top navbar's title + description. The active range stays
        // visible in the From/To date pickers below.
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '—';
        $periodLabel = $fmt($startDate).' to '.$fmt($endDate);
        $scopeLabel = $filterPlant ? (collect($options['plants'])->firstWhere('value', (int) $filterPlant)['label'] ?? 'All Plants') : 'All Plants';
        $dimHeader = match ($groupBy) { 'category' => 'Category', 'department' => 'Department', 'plant' => 'Plant', default => 'Admin' };
    @endphp

    {{-- Filter bar — two clearly separated sections:
         (1) VIEW: how to slice the report (period + which dimension breaks down the table)
         (2) FILTER: which subset of data to include --}}
    <div class="bg-white border border-gray-200 rounded-xl report-no-print">
        {{-- Section 1: View --}}
        <div class="p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2">View</p>
            <div class="flex flex-wrap items-end gap-3">
                {{-- Period = an inclusive Start–End date range. wire:change →
                     explicit action (not a morph-racing binding) and a value-keyed
                     wire:key so the native date picker always re-renders fresh. --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500">Start date</label>
                    <input type="date" value="{{ $startDate }}" max="{{ $endDate }}"
                        wire:change="updateStartDate($event.target.value)"
                        wire:key="period-start-{{ $startDate }}"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500">End date</label>
                    <input type="date" value="{{ $endDate }}" min="{{ $startDate }}"
                        wire:change="updateEndDate($event.target.value)"
                        wire:key="period-end-{{ $endDate }}"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                </div>

                {{-- Break down by — chooses what each TABLE ROW & chart bar represents.
                     This is the row axis, NOT a filter. --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500">Break down table by</label>
                    <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                        @foreach(['admin' => 'Admin', 'category' => 'Category', 'department' => 'Department', 'plant' => 'Plant'] as $val => $lbl)
                            <button type="button" wire:click="setGroupBy('{{ $val }}')"
                                class="px-3 py-1.5 text-sm {{ $groupBy === $val ? 'text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}"
                                style="{{ $groupBy === $val ? 'background:#0E4260;' : '' }}">
                                {{ $lbl }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Filter — narrows which data is counted (independent of the row axis above) --}}
        <div class="p-4 border-t border-gray-100 bg-gray-50/60">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-1">Filter data</p>
            <p class="text-xs text-gray-400 mb-2">Narrows which tickets are counted. Leave as “All” to include everything.</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500">Admin</label>
                    <select wire:model.live="filterAdmin" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm bg-white">
                        <option value="">All admins</option>
                        @foreach($options['admins'] as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500">Category</label>
                    <select wire:model.live="filterCategory" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm bg-white">
                        <option value="">All categories</option>
                        @foreach($options['categories'] as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500">Department</label>
                    <select wire:model.live="filterDepartment" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm bg-white">
                        <option value="">All departments</option>
                        @foreach($options['departments'] as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500">Plant</label>
                    <select wire:model.live="filterPlant" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm bg-white">
                        <option value="">All plants</option>
                        @foreach($options['plants'] as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI band --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php
            $kpis = [
                ['label' => 'Total Handled', 'value' => $totals['handled'], 'sub' => $totals['admins_count'].' '.($groupBy === 'admin' ? 'admins' : 'groups')],
                ['label' => 'Resolved', 'value' => $totals['resolved'], 'sub' => $totals['resolution_rate'].'% resolution rate'],
                ['label' => 'On-Time', 'value' => $totals['on_time_pct'].'%', 'sub' => 'of resolved tickets'],
                ['label' => 'Repetitive', 'value' => $totals['repetitive'], 'sub' => $totals['repetitive_pct'].'% of handled'],
            ];
        @endphp
        @foreach($kpis as $i => $kpi)
            <div class="bg-white border border-gray-200 rounded-xl p-4" style="animation: reportRise .4s ease both; animation-delay: {{ $i * 60 }}ms;">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</div>
                <div style="font-size:26px; font-weight:800; color:#0E4260; font-variant-numeric: tabular-nums;">{{ $kpi['value'] }}</div>
                <div class="text-xs text-gray-400">{{ $kpi['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Table heading + search (search lives above the card, not inside it) --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-800">Performance by {{ $dimHeader }}</h2>
            <p class="text-xs text-gray-400">Click any column header to sort. Click a row for ticket details. Search filters by {{ strtolower($dimHeader) }} name.</p>
        </div>
        <div class="flex items-end gap-3 report-no-print">
            <div class="flex items-end gap-2">
                <button type="button" wire:click="exportExcel"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-white shadow-sm hover:opacity-90"
                    style="background:#0E4260;"
                    title="Download visible table as Excel">
                    <x-ui.icon name="document-arrow-down" class="size-4" style="color:#ffffff;" />
                    Excel
                </button>
                <button type="button"
                    onclick="window.__printReport ? window.__printReport() : window.print();"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm bg-white text-gray-700 border border-gray-200 hover:bg-gray-50"
                    title="Print or save as PDF (table + charts)">
                    <x-ui.icon name="printer" class="size-4" />
                    PDF
                </button>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500">Search {{ $dimHeader }}</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                    placeholder="Search {{ strtolower($dimHeader) }} name…"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm w-64">
            </div>
        </div>
    </div>

    {{-- Master table.
         The <table> carries a wire:key that fingerprints the current dataset
         (grouping dimension + every row label + sort). When a filter changes the
         result set — e.g. data rows → the single "No tickets" empty-state row, or
         a switch of grouping dimension — the key changes, so Livewire REPLACES the
         whole table instead of morphing it. Livewire's morph cannot reliably bridge
         a <tbody> whose row structure changes drastically (verified: morph diverges
         and stops mid-tree), which is what made filter changes need a refresh. --}}
    @php
        $tableKey = 'tbl-'.$groupBy.'-'.$sortBy.'-'
            .md5(implode('|', array_map(fn ($r) => $r['label'], $rows)).':'.count($rows));
    @endphp
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div id="report-table-wrap" class="overflow-x-auto">
            <table wire:key="{{ $tableKey }}" class="w-full text-sm" style="font-variant-numeric: tabular-nums;">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                        @php
                            // key => full, unambiguous column label
                            $cols = [
                                'label' => $dimHeader,
                                'handled' => 'Handled',
                                'resolved' => 'Resolved',
                                'resolution_rate' => 'Resolution Rate',
                                'repetitive' => 'Repetitive',
                                'avg_resolution_hours' => 'Avg Resolution Time',
                                'on_time_pct' => 'On-Time Rate',
                                'avg_rating' => 'Avg Rating',
                            ];
                            [$activeSortKey, $activeSortDir] = [explode('_', $sortBy)[0], str_ends_with($sortBy, '_asc') ? 'asc' : 'desc'];
                        @endphp
                        @foreach($cols as $key => $lbl)
                            @php $isActive = str_starts_with($sortBy, $key.'_'); @endphp
                            <th wire:click="sortByColumn('{{ $key }}')"
                                class="px-4 py-3 cursor-pointer select-none whitespace-nowrap transition-colors {{ $isActive ? '' : 'hover:bg-gray-100' }}"
                                style="{{ $isActive ? 'background:#eef4f7;' : '' }}"
                                title="Sort by {{ $lbl }}">
                                <span class="inline-flex items-center gap-1" style="{{ $isActive ? 'color:#0E4260;font-weight:700;' : '' }}">
                                    {{ $lbl }}
                                    @if($isActive)
                                        <span style="font-size:10px;">{{ str_ends_with($sortBy, '_asc') ? '▲' : '▼' }}</span>
                                    @else
                                        <span class="text-gray-300" style="font-size:10px;">↕</span>
                                    @endif
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        {{-- wire:key keyed by dimension + label: when Group By changes the
                             whole row set changes, and a stable key lets Livewire's morph
                             replace rows cleanly instead of mis-diffing the old set. --}}
                        <tr wire:key="row-{{ $groupBy }}-{{ \Illuminate\Support\Str::slug($row['label']) }}"
                            wire:click="openDetailModal(@js($row['label']))"
                            class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $row['label'] }}</td>
                            <td class="px-4 py-3">{{ $row['handled'] }}</td>
                            <td class="px-4 py-3">{{ $row['resolved'] }}</td>
                            <td class="px-4 py-3">
                                <div class="relative w-24 h-5 bg-gray-100 rounded">
                                    <div class="absolute inset-y-0 left-0 rounded" style="width: {{ $row['resolution_rate'] }}%; background:#22c55e;"></div>
                                    <span class="absolute inset-0 flex items-center justify-center text-xs font-semibold">{{ $row['resolution_rate'] }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3"><span style="color:#a855f7; font-weight:600;">{{ $row['repetitive'] }}</span> <span class="text-gray-400 text-xs">({{ $row['repetitive_pct'] }}%)</span></td>
                            <td class="px-4 py-3">{{ $row['avg_resolution_hours'] !== null ? $row['avg_resolution_hours'].'h' : '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="relative w-24 h-5 bg-gray-100 rounded">
                                    <div class="absolute inset-y-0 left-0 rounded" style="width: {{ $row['on_time_pct'] }}%; background: {{ $row['on_time_pct'] >= 70 ? '#22c55e' : ($row['on_time_pct'] >= 40 ? '#f59e0b' : '#ef4444') }};"></div>
                                    <span class="absolute inset-0 flex items-center justify-center text-xs font-semibold">{{ $row['on_time_pct'] }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $row['avg_rating'] !== null ? $row['avg_rating'].' ★' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No tickets match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Charts grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="report-chart-card bg-white border border-gray-200 rounded-xl p-5"><h3 class="text-sm font-semibold text-gray-700 mb-1">Handled vs Resolved</h3><p class="text-xs text-gray-400 mb-3">Per {{ strtolower($dimHeader) }} — the gap is the backlog.</p><div style="height:280px;"><canvas id="reportHandledResolved"></canvas></div></div>
        <div class="report-chart-card bg-white border border-gray-200 rounded-xl p-5"><h3 class="text-sm font-semibold text-gray-700 mb-1">Resolution Status</h3><p class="text-xs text-gray-400 mb-3">On time vs ahead of schedule vs overtime vs unresolved.</p><div style="height:280px;"><canvas id="reportResolutionStatus"></canvas></div></div>
        <div class="report-chart-card bg-white border border-gray-200 rounded-xl p-5"><h3 class="text-sm font-semibold text-gray-700 mb-1">Repetitive Share</h3><p class="text-xs text-gray-400 mb-3">Repetitive tickets as a share of total handled.</p><div style="height:280px;"><canvas id="reportRepetitiveShare"></canvas></div></div>
        <div class="report-chart-card bg-white border border-gray-200 rounded-xl p-5"><h3 class="text-sm font-semibold text-gray-700 mb-1">Trend</h3><p class="text-xs text-gray-400 mb-3">Created vs resolved over the selected period.</p><div style="height:280px;"><canvas id="reportTrend"></canvas></div></div>
    </div>

    {{-- Print metadata holder (hidden). JS reads these data-* attributes to build
         a fresh #report-print-root on <body> at print time (see report-charts.js).
         This stays inside the component and is never moved, so Livewire morphs
         can't create duplicate-id elements. --}}
    <div id="report-print-data"
         class="report-print-only"
         data-report-title="Performance Report"
         data-report-period="{{ $periodLabel }} · {{ $scopeLabel }}"
         data-report-dimension="{{ $dimHeader }}"></div>

    {{-- Drill-down modal: tickets that make up the clicked row. Sheaf teleports
         this to <body> at runtime, so it does not affect layout. Kept INSIDE the
         single root <div> so Livewire's test harness sees only one root element
         (mirrors the activity-log component pattern). --}}
    <div
        x-data="{ open: @entangle('showDetailModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'report-detail-modal' }) : $dispatch('close-modal', { id: 'report-detail-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'report-detail-modal') { $wire.closeDetailModal() }"
    >
        <x-ui.modal id="report-detail-modal" width="4xl" backdrop="blur"
                    :heading="$detailLabel ? 'Tickets: '.$detailLabel : 'Ticket details'"
                    description="Click a ticket to open it in a new tab.">
            @php
                $fmtDate = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '—';
            @endphp
            <div class="flex flex-col h-[520px]">
                {{-- Header strip --}}
                <div class="shrink-0 flex items-center justify-between rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 mb-4">
                    <div>
                        <div class="text-2xl font-extrabold" style="color:#0E4260;">{{ $detail['total'] }}</div>
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Tickets</div>
                    </div>
                    <div class="text-xs text-gray-500 text-right">
                        <div>{{ $fmtDate($startDate) }} to {{ $fmtDate($endDate) }}</div>
                        <div class="text-gray-400">{{ ucfirst($groupBy) }}: <span class="font-semibold">{{ $detailLabel }}</span></div>
                    </div>
                </div>

                {{-- Ticket list --}}
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
                            @forelse($detail['tickets'] as $t)
                                <tr class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono text-xs">
                                        <a href="/ticket/{{ $t->id }}" target="_blank" rel="noopener" class="hover:underline" style="color:#0E4260;">
                                            #TKT{{ str_pad($t->id, 2, '0', STR_PAD_LEFT) }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 line-clamp-2 max-w-md">{{ $t->deskripsi }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold
                                            @if($t->statusTiket?->nama_status === 'Close') bg-green-100 text-green-700
                                            @elseif($t->statusTiket?->nama_status === 'In Progress') bg-amber-100 text-amber-700
                                            @else bg-gray-100 text-gray-700
                                            @endif">
                                            {{ $t->statusTiket?->nama_status ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ $t->kategori?->nama_kategori ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $t->user?->karyawan?->nama ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $t->created_at?->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-gray-400">No tickets in this grouping for the active filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($detail['totalPages'] > 1)
                    <div class="shrink-0 flex items-center justify-between mt-3 border-t border-gray-100 pt-3">
                        <div class="text-xs text-gray-500">Page <span class="font-semibold" style="color:#0E4260;">{{ $detail['page'] }}</span> of {{ $detail['totalPages'] }}</div>
                        <x-ui.pager :current="$detail['page']" :total="$detail['totalPages']" jump="detailGoToPage" />
                    </div>
                @endif
            </div>
        </x-ui.modal>
    </div>

</div>

{{-- IMPORTANT: @push / @script blocks MUST come AFTER the component's single root
     <div> closes — never before it. Livewire v4 treats the FIRST element of the
     template as the component root; any markup before it (even whitespace from a
     @push directive) breaks root detection, which corrupts the DOM morph and makes
     filter/sort changes silently fail until a full page refresh. Placing these
     blocks after the closing </div> mirrors the working Dashboard component. --}}
@push('styles')
    <style>
        @keyframes reportRise { from { opacity:0; transform: translateY(8px);} to {opacity:1; transform:none;} }

        /* The print container is invisible on screen. JS fills it (table clone +
           chart images) and lifts it to <body> right before printing. */
        .report-print-only { display: none; }

        @media print {
            @page { size: A4 landscape; margin: 12mm; }

            /* Isolation: hide everything in <body> EXCEPT the print container.
               Because the print root becomes the only rendered element, the
               app's fixed-height scrollable layout no longer clips the output —
               content flows naturally across as many pages as needed. This is
               why charts (captured as images by the builder) and the full table
               now appear in the PDF, whereas the old CSS-only approach lost them
               to the scroll container + canvas print quirks. */
            body > *:not(#report-print-root) { display: none !important; }

            #report-print-root {
                display: block !important;
                width: 100%;
                color: #1f2937;
                font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            }
            #report-print-root .rp-title { font-size: 20px; font-weight: 800; color: #0E4260; margin: 0 0 2px; }
            #report-print-root .rp-period { font-size: 11px; color: #6b7280; margin: 0 0 14px; }
            #report-print-root h3 { font-size: 13px; font-weight: 700; color: #374151; margin: 0 0 6px; }

            #report-print-root table { width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 22px; }
            #report-print-root th, #report-print-root td { border: 1px solid #e5e7eb; padding: 11px 14px; text-align: left; vertical-align: middle; }
            #report-print-root thead th { background: #f3f4f6; text-transform: uppercase; font-size: 11px; letter-spacing: .04em; color: #6b7280; }
            /* Scale the cloned inline mini-bars (Resolution Rate / On-Time) up so
               they stay proportional to the enlarged table text. */
            #report-print-root td .relative { width: 120px !important; height: 26px !important; }
            #report-print-root td .relative span { font-size: 12px !important; }

            /* Each chart block (image) kept whole across page breaks. */
            #report-print-root .rp-chart { break-inside: avoid; page-break-inside: avoid; margin-bottom: 18px; }
            #report-print-root .rp-chart img { width: 100%; max-width: 920px; height: auto; display: block; }

            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
@endpush

@push('scripts')
    @vite('resources/js/report-charts.js')
@endpush

@script
<script>
    const buildReportChartData = () => {
        // $wire.get() returns Livewire's REACTIVE proxy object. Handing a reactive
        // proxy straight to Chart.js causes infinite proxy-in-proxy recursion
        // ("Maximum call stack size exceeded") because Chart.js deep-traverses and
        // stores the data while Livewire's reactivity re-wraps every access — which
        // crashes the JS mid-update and leaves the DOM un-morphed until a refresh.
        // JSON round-trip produces a plain, proxy-free deep copy (chart data is only
        // numbers/strings/arrays, so this is lossless).
        const raw = $wire.get('chartPayload') ?? {};
        let payload;
        try {
            payload = JSON.parse(JSON.stringify(raw));
        } catch (e) {
            payload = {};
        }
        return {
            handled_resolved: payload.handled_resolved ?? [],
            resolution_status: payload.resolution_status ?? [],
            repetitive_share: payload.repetitive_share ?? [],
            trend: payload.trend ?? [],
        };
    };

    const renderReportCharts = () => {
        if (window.__initReportCharts) {
            window.__initReportCharts(buildReportChartData());
        }
    };

    const ensureReportCharts = () => {
        if (window.__initReportCharts) {
            renderReportCharts();
            return;
        }
        window.addEventListener('report-charts:ready', renderReportCharts, { once: true });
    };

    ensureReportCharts();

    // Re-render on Livewire morphs (filter/sort/search). Guarded so the hook is
    // registered only once across wire:navigate round-trips, and scoped so it only
    // acts when the report canvases are actually on the page (no-op for unrelated
    // component morphs like the navbar bell badge).
    if (! window.__reportChartsHooked) {
        window.__reportChartsHooked = true;

        Livewire.hook('morphed', () => {
            if (! document.getElementById('reportHandledResolved')) {
                return;
            }
            setTimeout(() => renderReportCharts(), 50);
        });

        Livewire.hook('navigated', () => {
            window.__reportChartsHooked = false;
            if (window.__destroyReportCharts) {
                window.__destroyReportCharts();
            }
        });
    }
</script>
@endscript

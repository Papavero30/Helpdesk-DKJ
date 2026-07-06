@php
    $avgResolutionLabel = $avgResolutionHours === null
        ? '—'
        : ($avgResolutionHours >= 24 ? round($avgResolutionHours / 24, 1).' days' : $avgResolutionHours.' hrs');
@endphp

<div>
    {{-- Header + date range --}}
    <div class="flex flex-col gap-3 mb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-dongker-700">Organization Overview</h1>
            <p class="mt-1 text-sm text-gray-500">Org wide ticket health for the selected period. Live counts are always current.</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Start</span>
                <input type="date" value="{{ $orgStart }}"
                       wire:change="updateOrgStart($event.target.value)"
                       wire:key="org-start-{{ $orgStart }}"
                       max="{{ $orgEnd }}"
                       class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 focus:ring-2 focus:ring-dongker-400" />
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">End</span>
                <input type="date" value="{{ $orgEnd }}"
                       wire:change="updateOrgEnd($event.target.value)"
                       wire:key="org-end-{{ $orgEnd }}"
                       min="{{ $orgStart }}"
                       max="{{ now()->format('Y-m-d') }}"
                       class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 focus:ring-2 focus:ring-dongker-400" />
            </label>
        </div>
    </div>

    {{-- KPI strip (period) --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-2xl font-extrabold text-dongker-600">{{ $totals['handled'] }}</div>
            <div class="mt-1 text-xs font-medium text-gray-500">Handled</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-2xl font-extrabold text-dongker-600">{{ $totals['resolution_rate'] }}%</div>
            <div class="mt-1 text-xs font-medium text-gray-500">Resolution rate</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-2xl font-extrabold text-green-600">{{ $totals['on_time_pct'] }}%</div>
            <div class="mt-1 text-xs font-medium text-gray-500">On time</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-2xl font-extrabold text-amber-500">{{ $avgRating === null ? '—' : $avgRating }}</div>
            <div class="mt-1 text-xs font-medium text-gray-500">Avg rating</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-2xl font-extrabold text-dongker-600">{{ $avgResolutionLabel }}</div>
            <div class="mt-1 text-xs font-medium text-gray-500">Avg resolution</div>
        </div>
    </div>

    {{-- Live snapshot --}}
    <div class="mb-6 rounded-xl border border-dongker-200 bg-dongker-50 p-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="inline-flex size-2 rounded-full bg-green-500"></span>
            <h2 class="text-[11px] font-bold uppercase tracking-wider text-dongker-700">Live now</h2>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div><div class="text-xl font-extrabold text-amber-500">{{ $live['open'] }}</div><div class="text-xs text-gray-500">Open</div></div>
            <div><div class="text-xl font-extrabold text-dongker-600">{{ $live['unassigned'] }}</div><div class="text-xs text-gray-500">Unassigned</div></div>
            <div><div class="text-xl font-extrabold text-blue-500">{{ $live['awaiting'] }}</div><div class="text-xs text-gray-500">Awaiting confirmation</div></div>
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-bold text-dongker-600 mb-3">Tickets per Plant</h3>
            <div class="h-[200px]"><canvas id="orgPlantChart"></canvas></div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-bold text-dongker-600 mb-3">Category Distribution</h3>
            <div class="h-[200px]"><canvas id="orgCategoryChart"></canvas></div>
        </div>
    </div>

    {{-- Admin leaderboard --}}
    <div class="rounded-xl border border-gray-200 bg-white">
        <div class="border-b border-gray-100 p-5">
            <h3 class="text-base font-bold text-dongker-600">Admin Leaderboard</h3>
            <p class="text-[11px] text-gray-500 mt-0.5">Per admin performance for the selected period.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                        <th class="px-5 py-3 font-semibold">Admin</th>
                        <th class="px-3 py-3 font-semibold">Handled</th>
                        <th class="px-3 py-3 font-semibold">Resolved</th>
                        <th class="px-3 py-3 font-semibold">On time %</th>
                        <th class="px-5 py-3 font-semibold text-right">Avg rating</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaderboard as $row)
                        <tr class="border-b border-gray-50">
                            <td class="px-5 py-3 font-semibold text-dongker-700">{{ $row['label'] }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $row['handled'] }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $row['resolved'] }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $row['on_time_pct'] }}%</td>
                            <td class="px-5 py-3 text-right">
                                @if(($row['avg_rating'] ?? null) !== null)
                                    <span class="font-semibold text-amber-500">{{ $row['avg_rating'] }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">No admin activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
    @vite('resources/js/manager-org-charts.js')
@endpush

@script
<script>
    const readOrgPayload = () => {
        const raw = $wire.get('orgChartPayload') ?? {};
        return JSON.parse(JSON.stringify(raw));
    };

    const initOrgCharts = () => {
        if (!document.getElementById('orgPlantChart')) return;
        if (window.__initManagerOrgCharts) {
            window.__initManagerOrgCharts(readOrgPayload());
        }
    };

    const ensureOrgCharts = () => {
        if (window.__initManagerOrgCharts) {
            initOrgCharts();
            return;
        }
        window.addEventListener('manager-org-charts:ready', initOrgCharts, { once: true });
    };

    ensureOrgCharts();

    if (!window.__managerOrgHooked) {
        window.__managerOrgHooked = true;
        Livewire.hook('morphed', () => {
            setTimeout(() => { ensureOrgCharts(); }, 50);
        });
        Livewire.hook('navigated', () => {
            if (window.__destroyManagerOrgCharts) window.__destroyManagerOrgCharts();
        });
    }
</script>
@endscript

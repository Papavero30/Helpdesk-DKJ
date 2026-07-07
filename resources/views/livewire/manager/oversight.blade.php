<div>
    <x-ui.breadcrumb />

    <div class="mb-4">
        <h1 class="text-xl font-bold text-dongker-700">Admin Workload</h1>
        <p class="text-xs text-gray-500">Live active ticket load per admin. Click an admin to rebalance their queue.</p>
    </div>

    {{-- Workload toolbar --}}
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
            <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="wlSearch"
                   placeholder="Search admin or plant"
                   class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200" />
        </div>
        <select wire:model.live="wlPlant"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
            <option value="">All plants</option>
            @foreach($plants as $p)
                <option value="{{ $p->nama_lokasi }}">{{ $p->nama_lokasi }}</option>
            @endforeach
        </select>
        <select wire:model.live="wlSort"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
            <option value="name_asc">Sort: Name A to Z</option>
            <option value="name_desc">Sort: Name Z to A</option>
            <option value="active_desc">Sort: Most active</option>
            <option value="overdue_desc">Sort: Most overdue</option>
            <option value="awaiting_desc">Sort: Most awaiting</option>
        </select>
    </div>

    {{-- Workload table --}}
    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">Admin</th>
                    <th class="px-3 py-3 font-semibold">Plant</th>
                    <th class="px-3 py-3 font-semibold">Active</th>
                    <th class="px-3 py-3 font-semibold">On Track</th>
                    <th class="px-3 py-3 font-semibold">Overdue</th>
                    <th class="px-3 py-3 font-semibold">Awaiting</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($workload as $row)
                    <tr wire:key="wl-{{ $row['admin_id'] }}" class="border-b border-gray-50 {{ $row['active'] === 0 ? 'opacity-50' : '' }}">
                        <td class="px-5 py-3 font-semibold text-dongker-700">{{ $row['nama'] }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $row['plant'] }}</td>
                        <td class="px-3 py-3 font-bold text-dongker-600">{{ $row['active'] }}</td>
                        <td class="px-3 py-3 font-semibold {{ $row['on_track'] > 0 ? 'text-dongker-600' : 'text-gray-300' }}">{{ $row['on_track'] }}</td>
                        <td class="px-3 py-3 font-semibold {{ $row['overdue'] > 0 ? 'text-dongker-600' : 'text-gray-300' }}">{{ $row['overdue'] }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $row['awaiting'] }}</td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" wire:click="openAdmin({{ $row['admin_id'] }})"
                                    @disabled($row['active'] === 0)
                                    class="rounded-lg border border-gray-200 px-3 py-1 text-[11px] font-semibold text-gray-600 transition hover:border-dongker-200 hover:text-dongker-600 disabled:opacity-40 disabled:cursor-not-allowed">View</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">{{ trim($wlSearch) !== '' || $wlPlant !== '' ? 'No admins match your search or filters.' : 'No admins found.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($wlTotal > 0)
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <span class="text-xs text-gray-500">Page <span class="font-semibold text-dongker-600">{{ $wlPage }}</span> of {{ $wlTotalPages }}</span>
            <x-ui.pager :current="$wlPage" :total="$wlTotalPages" jump="wlGoToPage" />
        </div>
    @endif

    {{-- Unassigned tickets --}}
    <div class="mt-6 mb-4">
        <h2 class="text-xl font-bold text-dongker-700">Unassigned Tickets</h2>
        <p class="text-xs text-gray-500">Open tickets with no admin yet. Assign to any admin across all plants.</p>
    </div>

    {{-- Unassigned toolbar --}}
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
            <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="unSearch"
                   placeholder="Search ticket number, description, or requester"
                   class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200" />
        </div>
        <select wire:model.live="unCategory"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
            <option value="">All categories</option>
            @foreach($categories as $c)
                <option value="{{ $c->id }}">{{ $c->nama_kategori }}</option>
            @endforeach
        </select>
        <select wire:model.live="unPlant"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
            <option value="">All plants</option>
            @foreach($plants as $p)
                <option value="{{ $p->id }}">{{ $p->nama_lokasi }}</option>
            @endforeach
        </select>
        <select wire:model.live="unSort"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
            <option value="oldest">Sort: Oldest first</option>
            <option value="newest">Sort: Newest first</option>
            <option value="urgency">Sort: Category urgency</option>
        </select>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">#TKT</th>
                    <th class="px-3 py-3 font-semibold">Description</th>
                    <th class="px-3 py-3 font-semibold">Requester</th>
                    <th class="px-3 py-3 font-semibold">Category</th>
                    <th class="px-3 py-3 font-semibold">Plant</th>
                    <th class="px-5 py-3 font-semibold text-right">Assign to</th>
                </tr>
            </thead>
            <tbody>
                @forelse($unassignedTickets as $t)
                    <tr wire:key="un-{{ $t->id }}" class="border-b border-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">
                            <a href="/ticket/{{ $t->id }}" target="_blank" rel="noopener" class="hover:underline" style="color:#0E4260;">#TKT{{ str_pad($t->id, 2, '0', STR_PAD_LEFT) }}</a>
                        </td>
                        <td class="px-3 py-3 max-w-xs"><span class="block truncate text-gray-700">{{ $t->deskripsi }}</span></td>
                        <td class="px-3 py-3 text-gray-700 font-medium">{{ $t->user?->karyawan?->nama ?? '—' }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $t->kategori?->nama_kategori ?? '—' }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $t->lokasi?->nama_lokasi ?? '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            @if($allAdmins->isEmpty())
                                <span class="text-[11px] text-gray-400 italic">No admins available</span>
                            @else
                                <div x-data="{ target: '' }" class="flex items-center justify-end gap-2">
                                    <select x-model="target" class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 focus:ring-2 focus:ring-dongker-400">
                                        <option value="">Select admin</option>
                                        @foreach($allAdmins as $admin)
                                            <option value="{{ $admin->id }}">{{ $admin->karyawan?->nama ?? ('User #'.$admin->id) }} · {{ $admin->karyawan?->lokasi?->nama_lokasi ?? '—' }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button"
                                            x-on:click="if (target) { $wire.assignUnassigned({{ $t->id }}, parseInt(target)); target = '' }"
                                            class="rounded-lg bg-dongker-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-dongker-500">Assign</button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">{{ trim($unSearch) !== '' || $unCategory !== '' || $unPlant !== '' ? 'No unassigned tickets match your search or filters.' : 'No unassigned tickets.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($unTotal > 0)
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <span class="text-xs text-gray-500">Page <span class="font-semibold text-dongker-600">{{ $unPage }}</span> of {{ $unTotalPages }}</span>
            <x-ui.pager :current="$unPage" :total="$unTotalPages" jump="unGoToPage" />
        </div>
    @endif

    {{-- Deadline pauses --}}
    <div class="mt-6 mb-4">
        <h2 class="text-xl font-bold text-dongker-700">Deadline Pauses</h2>
        <p class="text-xs text-gray-500">Tickets with a frozen deadline or a pause request that waits for an answer, including extend requests.</p>
    </div>

    {{-- Deadline pauses toolbar --}}
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
            <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="dpSearch"
                   placeholder="Search ticket, PIC, or reason"
                   class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200" />
        </div>
        <select wire:model.live="dpStatus"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
            <option value="all">All status</option>
            <option value="active">Paused</option>
            <option value="waiting">Waiting Approval</option>
            <option value="extend">Extend Requested</option>
        </select>
        <select wire:model.live="dpSort"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
            <option value="eta_asc">Sort: Until soonest first</option>
            <option value="eta_desc">Sort: Until latest first</option>
            <option value="ticket_desc">Sort: Newest ticket</option>
        </select>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">#TKT</th>
                    <th class="px-3 py-3 font-semibold">Description</th>
                    <th class="px-3 py-3 font-semibold">PIC</th>
                    <th class="px-3 py-3 font-semibold">Reason</th>
                    <th class="px-3 py-3 font-semibold">Until</th>
                    <th class="px-3 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pauseRequests as $pr)
                    @php $isExtend = $pr->state === 'pending' && $extendTicketIds->contains($pr->tiket_id); @endphp
                    <tr wire:key="pause-{{ $pr->id }}" class="border-b border-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">
                            <a href="/ticket/{{ $pr->tiket_id }}" target="_blank" rel="noopener" class="hover:underline" style="color:#0E4260;">#TKT{{ str_pad($pr->tiket_id, 2, '0', STR_PAD_LEFT) }}</a>
                        </td>
                        <td class="px-3 py-3 max-w-xs"><span class="block truncate text-gray-700">{{ $pr->tiket?->deskripsi }}</span></td>
                        <td class="px-3 py-3 text-gray-700 font-medium">{{ $pr->tiket?->assignedAdmin?->karyawan?->nama ?? 'No PIC' }}</td>
                        <td class="px-3 py-3 max-w-[220px]"><span class="block truncate text-gray-600" title="{{ $pr->reason }}">{{ $pr->reason }}</span></td>
                        <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ $pr->eta?->format('d M Y, H:i') }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            @if($pr->state === 'active')
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Paused</span>
                            @elseif($isExtend)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Extend Requested</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Waiting Approval</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if($pr->state === 'active')
                                <button type="button" wire:click="forceResumePause({{ $pr->tiket_id }})"
                                        class="rounded-lg border border-gray-200 px-3 py-1 text-[11px] font-semibold text-gray-600 transition hover:border-dongker-200 hover:text-dongker-600">
                                    Force resume
                                </button>
                            @else
                                <button type="button" wire:click="cancelPauseRequest({{ $pr->id }})"
                                        class="rounded-lg border border-gray-200 px-3 py-1 text-[11px] font-semibold text-gray-600 transition hover:border-dongker-200 hover:text-dongker-600">
                                    Cancel request
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">{{ trim($dpSearch) !== '' || $dpStatus !== 'all' ? 'No deadline pauses match your search or filters.' : 'No deadline pauses right now.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($dpTotal > 0)
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <span class="text-xs text-gray-500">Page <span class="font-semibold text-dongker-600">{{ $dpPage }}</span> of {{ $dpTotalPages }}</span>
            <x-ui.pager :current="$dpPage" :total="$dpTotalPages" jump="dpGoToPage" />
        </div>
    @endif

    {{-- Drill-down modal --}}
    <div
        x-data="{ open: @entangle('showDrilldownModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'oversight-drilldown' }) : $dispatch('close-modal', { id: 'oversight-drilldown' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'oversight-drilldown') { $wire.closeDrilldown() }"
    >
        <x-ui.modal id="oversight-drilldown" width="7xl" backdrop="blur"
                    :heading="($drilldownAdmin?->karyawan?->nama ?? 'Admin') . ' active tickets'"
                    description="Reassign a ticket to any admin (across all plants).">
            {{-- Search + filters --}}
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <div class="relative min-w-[220px] flex-1">
                    <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <input type="text" wire:model.live.debounce.300ms="ddSearch"
                           placeholder="Search ticket number, description, or requester"
                           class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200" />
                </div>
                <select wire:model.live="ddCategory"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
                    <option value="">All categories</option>
                    @foreach($drilldownCategories as $c)
                        <option value="{{ $c->id }}">{{ $c->nama_kategori }}</option>
                    @endforeach
                </select>
                <select wire:model.live="ddSla"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-dongker-300 focus:ring-2 focus:ring-dongker-200">
                    <option value="all">All SLA</option>
                    <option value="on_track">On Track</option>
                    <option value="overdue">Overdue</option>
                    <option value="paused">Paused</option>
                </select>
            </div>
            {{-- min-height keeps the frame constant across pages; no internal scroll so
                 the Actions (⋮) dropdown is never clipped. Rows are capped at 10/page. --}}
            <div class="min-h-[440px]">
                <table class="w-full text-sm table-fixed">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr class="text-left">
                            <th class="px-3 py-2 w-[9%]">#TKT</th>
                            <th class="px-3 py-2 w-[28%]">Description</th>
                            <th class="px-3 py-2 w-[16%]">Requester</th>
                            <th class="px-3 py-2 w-[14%]">Category</th>
                            <th class="px-3 py-2 w-[12%]">Status</th>
                            <th class="px-3 py-2 w-[13%]">SLA</th>
                            <th class="px-3 py-2 w-[8%] text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($drilldownTickets as $t)
                            @php
                                $targets = $allAdmins->where('id', '!=', $t->id_penanggung_jawab);
                                $hasSla = (bool) $t->target_penyelesaian;
                                $isOverdue = $hasSla && $t->effectiveDeadline()->isPast();
                            @endphp
                            <tr wire:key="dd-{{ $t->id }}" class="border-t border-gray-100">
                                <td class="px-3 py-2 font-mono text-xs">
                                    <a href="/ticket/{{ $t->id }}" target="_blank" rel="noopener" class="hover:underline" style="color:#0E4260;">#TKT{{ str_pad($t->id, 2, '0', STR_PAD_LEFT) }}</a>
                                </td>
                                <td class="px-3 py-2"><span class="block truncate text-gray-700">{{ $t->deskripsi }}</span></td>
                                <td class="px-3 py-2 text-gray-700"><span class="block truncate">{{ $t->user?->karyawan?->nama ?? '—' }}</span></td>
                                <td class="px-3 py-2 text-gray-600"><span class="block truncate">{{ $t->kategori?->nama_kategori ?? '—' }}</span></td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $t->statusTiket?->nama_status ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if($t->isSlaPaused())
                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Paused</span>
                                    @elseif(! $hasSla)
                                        <span class="text-[11px] text-gray-400">—</span>
                                    @elseif($isOverdue)
                                        <span title="Due {{ $t->target_penyelesaian->format('d M Y, H:i') }}"
                                              class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">Overdue</span>
                                    @else
                                        <span title="Due {{ $t->target_penyelesaian->format('d M Y, H:i') }}"
                                              class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700">On Track</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button type="button" x-on:click="open = ! open"
                                                class="inline-flex items-center justify-center rounded-lg border border-gray-200 p-1.5 text-gray-500 transition hover:border-dongker-200 hover:text-dongker-600"
                                                :class="open && 'border-dongker-300 text-dongker-600 bg-dongker-50'"
                                                aria-haspopup="true" :aria-expanded="open" aria-label="Ticket actions">
                                            <x-ui.icon name="ellipsis-vertical" class="size-4" />
                                        </button>

                                        <div x-show="open" x-cloak
                                             x-on:click.outside="open = false"
                                             x-on:keydown.escape.window="open = false"
                                             x-transition:enter="transition ease-out duration-150"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             class="absolute right-0 z-50 mt-1 w-60 origin-top-right rounded-xl border border-gray-100 bg-white p-1.5 text-left shadow-lg ring-1 ring-black/5">
                                            {{-- Reassign --}}
                                            <p class="px-2 pb-1 pt-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Reassign to</p>
                                            <div class="max-h-44 overflow-y-auto">
                                                @forelse($targets as $admin)
                                                    <button type="button"
                                                            wire:click="reassign({{ $t->id }}, {{ $admin->id }})" x-on:click="open = false"
                                                            class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-gray-700 transition hover:bg-dongker-50 hover:text-dongker-700">
                                                        <span class="truncate">{{ $admin->karyawan?->nama ?? ('User #'.$admin->id) }}</span>
                                                        <span class="shrink-0 text-[10px] text-gray-400">{{ $admin->karyawan?->lokasi?->nama_lokasi ?? '—' }}</span>
                                                    </button>
                                                @empty
                                                    <p class="px-2 py-1.5 text-xs italic text-gray-400">No other admin available</p>
                                                @endforelse
                                            </div>

                                            <div class="my-1 border-t border-gray-100"></div>

                                            {{-- Remind --}}
                                            <p class="px-2 pb-1 pt-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Remind</p>
                                            <button type="button" wire:click="remind({{ $t->id }}, 'admin')" x-on:click="open = false"
                                                    class="group flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-gray-700 transition hover:bg-dongker-50 hover:text-dongker-700">
                                                <x-ui.icon name="bell" class="size-3.5 text-gray-400 transition-colors group-hover:text-dongker-500" /> Remind admin
                                            </button>
                                            <button type="button" wire:click="remind({{ $t->id }}, 'requester')" x-on:click="open = false"
                                                    class="group flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-gray-700 transition hover:bg-dongker-50 hover:text-dongker-700">
                                                <x-ui.icon name="user" class="size-3.5 text-gray-400 transition-colors group-hover:text-dongker-500" /> Remind requester
                                            </button>

                                            @if($t->isSlaPaused())
                                                <div class="my-1 border-t border-gray-100"></div>
                                                <p class="px-2 pb-1 pt-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Deadline pause</p>
                                                <button type="button" wire:click="forceResumePause({{ $t->id }})" x-on:click="open = false"
                                                        class="group flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-gray-700 transition hover:bg-dongker-50 hover:text-dongker-700">
                                                    <x-ui.icon name="play" class="size-3.5 text-gray-400 transition-colors group-hover:text-dongker-500" /> Force resume deadline
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">No tickets match your search or filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($ddTotal > 0)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">
                        Showing {{ ($ddPage - 1) * 10 + 1 }} to {{ min($ddPage * 10, $ddTotal) }} of {{ $ddTotal }}
                    </p>
                    <x-ui.pager :current="$ddPage" :total="$ddTotalPages" jump="ddGoToPage" />
                </div>
            @endif
        </x-ui.modal>
    </div>
</div>

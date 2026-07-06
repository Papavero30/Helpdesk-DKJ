<div>
    <x-ui.breadcrumb />

    {{-- Tabs + toolbar --}}
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <div class="flex flex-wrap items-center gap-1 p-1 rounded-xl bg-gray-100 w-fit">
            @foreach(['categories' => 'Categories', 'plants' => 'Plants', 'divisions' => 'Divisions', 'jabatan' => 'Position'] as $tabKey => $tabLabel)
                <button type="button" wire:click="setTab('{{ $tabKey }}')"
                        @class([
                            'px-3.5 py-1.5 rounded-lg text-sm font-semibold transition',
                            'bg-dongker-600 text-white shadow-sm' => $tab === $tabKey,
                            'text-gray-600 hover:text-dongker-600' => $tab !== $tabKey,
                        ])>{{ $tabLabel }}</button>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-xs font-semibold text-gray-500 cursor-pointer">
                <input type="checkbox" wire:model.live="showArchived" class="size-4 rounded border-gray-300 text-dongker-600 focus:ring-dongker-400" />
                Show archived
            </label>
            <button type="button" wire:click="openCreate"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-dongker-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-dongker-500 hover:shadow-md">
                <x-ui.icon name="plus" class="size-4 text-white" />
                Add
            </button>
        </div>
    </div>

    {{-- Tables --}}
    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        @if($tab === 'categories')
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                        <th class="px-5 py-3 font-semibold">Name</th>
                        <th class="px-3 py-3 font-semibold">SLA (hours)</th>
                        <th class="px-3 py-3 font-semibold">Urgency</th>
                        <th class="px-3 py-3 font-semibold">Color</th>
                        <th class="px-3 py-3 font-semibold">Examples</th>
                        <th class="px-3 py-3 font-semibold">Usage</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $c)
                        <tr wire:key="cat-{{ $c->id }}" class="border-b border-gray-50 {{ $c->is_active ? '' : 'opacity-50' }}">
                            <td class="px-5 py-3 font-semibold text-dongker-700">{{ $c->nama_kategori }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $c->batas_jam_sla }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $c->urgensi }}</td>
                            <td class="px-3 py-3"><span class="inline-block size-5 rounded-full border border-gray-200" style="background: {{ $c->warna_grafik }};"></span></td>
                            <td class="px-3 py-3 max-w-[200px]"><span class="block truncate text-gray-500 text-xs">{{ $c->contoh ?? '—' }}</span></td>
                            <td class="px-3 py-3 text-xs text-gray-400">{{ $c->tikets_count }} tickets</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $c->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $c->is_active ? 'Active' : 'Archived' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @include('livewire.manager.partials.master-data-actions', ['row' => $c, 'usage' => $c->tikets_count])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">No categories{{ $showArchived ? '' : ' — toggle "Show archived" to see archived ones' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($tab === 'plants')
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                        <th class="px-5 py-3 font-semibold">Name</th>
                        <th class="px-3 py-3 font-semibold">Usage</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plants as $p)
                        <tr wire:key="plant-{{ $p->id }}" class="border-b border-gray-50 {{ $p->is_active ? '' : 'opacity-50' }}">
                            <td class="px-5 py-3 font-semibold text-dongker-700">{{ $p->nama_lokasi }}</td>
                            <td class="px-3 py-3 text-xs text-gray-400">{{ $p->tiket_count }} tickets · {{ $p->karyawan_count }} employees</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $p->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $p->is_active ? 'Active' : 'Archived' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @include('livewire.manager.partials.master-data-actions', ['row' => $p, 'usage' => $p->tiket_count + $p->karyawan_count])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">No plants.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($tab === 'divisions')
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                        <th class="px-5 py-3 font-semibold">Name</th>
                        <th class="px-3 py-3 font-semibold">Usage</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($divisions as $d)
                        <tr wire:key="div-{{ $d->id }}" class="border-b border-gray-50 {{ $d->is_active ? '' : 'opacity-50' }}">
                            <td class="px-5 py-3 font-semibold text-dongker-700">{{ $d->nama_divisi }}</td>
                            <td class="px-3 py-3 text-xs text-gray-400">{{ $d->karyawan_count }} employees</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $d->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $d->is_active ? 'Active' : 'Archived' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @include('livewire.manager.partials.master-data-actions', ['row' => $d, 'usage' => $d->karyawan_count])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">No divisions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                        <th class="px-5 py-3 font-semibold">Name</th>
                        <th class="px-3 py-3 font-semibold">Usage</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jabatans as $j)
                        <tr wire:key="jab-{{ $j->id }}" class="border-b border-gray-50 {{ $j->is_active ? '' : 'opacity-50' }}">
                            <td class="px-5 py-3 font-semibold text-dongker-700">{{ $j->nama_jabatan }}</td>
                            <td class="px-3 py-3 text-xs text-gray-400">{{ $j->karyawan_count }} employees</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $j->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $j->is_active ? 'Active' : 'Archived' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @include('livewire.manager.partials.master-data-actions', ['row' => $j, 'usage' => $j->karyawan_count])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">No jabatan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    {{-- Create/Edit modal --}}
    <div
        x-data="{ open: @entangle('showModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'master-data-modal' }) : $dispatch('close-modal', { id: 'master-data-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'master-data-modal') { $wire.closeModal() }"
    >
        <x-ui.modal id="master-data-modal" backdrop="blur" width="lg"
                    :heading="($editingId ? 'Edit ' : 'Add ') . $this->singularLabel">
            <form wire:submit.prevent="save" class="space-y-4">
                @if($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5">
                        <ul class="space-y-0.5">
                            @foreach($errors->all() as $err)
                                <li class="text-[12px] text-red-600">• {{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label class="text-[12px] font-semibold uppercase tracking-wider text-dongker-500">Name</label>
                    <input type="text" wire:model="nama"
                           class="mt-1 w-full rounded-lg border {{ $errors->has('nama') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400" />
                </div>

                @if($tab === 'categories')
                    <div>
                        <label class="text-[12px] font-semibold uppercase tracking-wider text-dongker-500">SLA (hours)</label>
                        <input type="number" min="1" wire:model="batasJamSla"
                               class="mt-1 w-full rounded-lg border {{ $errors->has('batasJamSla') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400" />
                        <p class="mt-1 text-[11px] text-gray-400">
                            @if($editingId)
                                Priority: {{ $urgensi }} (auto, not editable)
                            @else
                                Priority assigned automatically (next in sequence).
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold uppercase tracking-wider text-dongker-500">Color</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="color" wire:model="warnaGrafik" class="h-10 w-14 rounded border border-gray-200 cursor-pointer" />
                            <input type="text" wire:model="warnaGrafik"
                                   class="flex-1 rounded-lg border {{ $errors->has('warnaGrafik') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400" />
                        </div>
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold uppercase tracking-wider text-dongker-500">Examples (optional)</label>
                        <input type="text" wire:model="contoh" maxlength="255"
                               class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400" />
                    </div>
                @endif

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-dongker-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-dongker-500">
                        {{ $editingId ? 'Update' : 'Create' }}
                    </button>
                    <button type="button" x-on:click="$dispatch('close-modal', { id: 'master-data-modal' })"
                            class="text-sm text-gray-400 hover:text-gray-600">Cancel</button>
                </div>
            </form>
        </x-ui.modal>
    </div>

    {{-- Archive confirm / block modal --}}
    <div
        x-data="{ open: @entangle('showArchiveModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'archive-modal' }) : $dispatch('close-modal', { id: 'archive-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'archive-modal') { $wire.closeArchiveModal() }"
    >
        <x-ui.modal id="archive-modal" backdrop="blur" width="md" heading="Archive">
            @if(($archiveImpact['blocked'] ?? false))
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5">
                    <span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                        <x-ui.icon name="users" class="size-4" />
                    </span>
                    <p class="text-sm leading-relaxed text-gray-700">
                        This record still has <span class="font-bold text-dongker-700">{{ $archiveImpact['usage'] }}</span> employee(s).
                        Reassign them in <span class="font-semibold text-dongker-700">People</span> before archiving.
                    </p>
                </div>
                <div class="flex justify-end pt-5">
                    <button type="button" x-on:click="$dispatch('close-modal', { id: 'archive-modal' })"
                            class="rounded-xl bg-dongker-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg transition hover:bg-dongker-500">Got it</button>
                </div>
            @else
                <div class="flex items-start gap-3 rounded-xl border border-dongker-100 bg-dongker-50/60 px-4 py-3.5">
                    <span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-dongker-100 text-dongker-600">
                        <x-ui.icon name="archive-box" class="size-4" />
                    </span>
                    <div class="text-sm leading-relaxed text-gray-700">
                        @if(in_array($archiveImpact['type'] ?? '', ['divisions', 'jabatan'], true))
                            Archive this record? It has no employees and is safe to retire.
                        @else
                            <span class="font-bold text-dongker-700">{{ $archiveImpact['active'] ?? 0 }}</span> active ticket(s) still use this.
                            @if(! empty($archiveImpact['admins']))
                                Held by <span class="font-semibold text-dongker-700">{{ implode(', ', $archiveImpact['admins']) }}</span>.
                            @endif
                            @if(($archiveImpact['unassigned'] ?? 0) > 0)
                                {{ $archiveImpact['unassigned'] }} unassigned.
                            @endif
                            They continue normally; no new tickets will use it.
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-5">
                    <button type="button" x-on:click="$dispatch('close-modal', { id: 'archive-modal' })"
                            class="px-4 py-2.5 text-sm font-semibold text-gray-500 transition hover:text-gray-700">Cancel</button>
                    <button type="button" wire:click="archive"
                            class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-bold text-white shadow-lg transition hover:bg-amber-600">Archive</button>
                </div>
            @endif
        </x-ui.modal>
    </div>
</div>

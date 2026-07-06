<div>
    <x-ui.breadcrumb />

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
        <div class="relative flex-1">
            <x-ui.icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search by name or email..."
                   class="w-full rounded-lg border border-gray-200 bg-white pl-9 pr-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @php
                $rsCount = ($filterRole !== '' ? 1 : 0) + ($filterStatus !== '' ? 1 : 0);
                $pdCount = count($filterPlant) + count($filterDivisi);
            @endphp

            {{-- Filter 1: Role & Status (radio) --}}
            <x-ui.popover>
                <x-ui.popover.trigger>
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs font-semibold text-gray-600 hover:border-dongker-300 hover:text-dongker-600">
                        <x-ui.icon name="funnel" class="size-4" />
                        Role & Status
                        @if($rsCount > 0)
                            <span class="rounded-full bg-dongker-100 px-2 py-0.5 text-[10px] font-semibold text-dongker-700">{{ $rsCount }}</span>
                        @endif
                    </button>
                </x-ui.popover.trigger>
                <x-ui.popover.overlay class="!w-64 p-4">
                    <div class="space-y-4"
                         x-data="{
                             stagedRole: @js($filterRole),
                             stagedStatus: @js($filterStatus),
                             applied() {
                                 $wire.set('filterRole', this.stagedRole, false);
                                 $wire.set('filterStatus', this.stagedStatus, false);
                                 $wire.applyFilters();
                                 hide();
                             },
                             resetGroup() {
                                 this.stagedRole = ''; this.stagedStatus = '';
                                 $wire.set('filterRole', '', false);
                                 $wire.set('filterStatus', '', false);
                                 $wire.applyFilters();
                             }
                         }">
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Role</div>
                            <div class="mt-2 space-y-2">
                                @foreach(['' => 'All', 'karyawan' => 'Karyawan', 'admin' => 'Admin'] as $val => $label)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="{{ $val }}" x-model="stagedRole"
                                               class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                        <span class="text-sm text-gray-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</div>
                            <div class="mt-2 space-y-2">
                                @foreach(['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $val => $label)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="{{ $val }}" x-model="stagedStatus"
                                               class="size-4 text-dongker-600 focus:ring-dongker-400" />
                                        <span class="text-sm text-gray-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                            <button type="button" x-on:click="resetGroup()"
                                    class="text-xs font-semibold text-gray-400 hover:text-dongker-600">Reset</button>
                            <button type="button" x-on:click="applied()"
                                    class="inline-flex items-center rounded-lg bg-dongker-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-dongker-500">Apply</button>
                        </div>
                    </div>
                </x-ui.popover.overlay>
            </x-ui.popover>

            {{-- Filter 2: Plant & Division (checkbox, 2 kolom) --}}
            <x-ui.popover>
                <x-ui.popover.trigger>
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs font-semibold text-gray-600 hover:border-dongker-300 hover:text-dongker-600">
                        <x-ui.icon name="map-pin" class="size-4" />
                        Plant & Division
                        @if($pdCount > 0)
                            <span class="rounded-full bg-dongker-100 px-2 py-0.5 text-[10px] font-semibold text-dongker-700">{{ $pdCount }}</span>
                        @endif
                    </button>
                </x-ui.popover.trigger>
                <x-ui.popover.overlay class="!w-96 p-4">
                    <div class="space-y-4"
                         x-data="{
                             stagedPlant: @js($filterPlant),
                             stagedDivisi: @js($filterDivisi),
                             applied() {
                                 $wire.set('filterPlant', this.stagedPlant, false);
                                 $wire.set('filterDivisi', this.stagedDivisi, false);
                                 $wire.applyFilters();
                                 hide();
                             },
                             resetGroup() {
                                 this.stagedPlant = []; this.stagedDivisi = [];
                                 $wire.set('filterPlant', [], false);
                                 $wire.set('filterDivisi', [], false);
                                 $wire.applyFilters();
                             }
                         }">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Plant</div>
                                <div class="mt-2 space-y-2 max-h-52 overflow-y-auto pr-1">
                                    @foreach($lokasiOptions as $lok)
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" value="{{ $lok->id }}" x-model="stagedPlant"
                                                   class="size-4 rounded border-gray-300 text-dongker-600 focus:ring-dongker-400" />
                                            <span class="text-sm text-gray-700">{{ $lok->nama_lokasi }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Division</div>
                                <div class="mt-2 space-y-2 max-h-52 overflow-y-auto pr-1">
                                    @foreach($divisiOptions as $div)
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" value="{{ $div->id }}" x-model="stagedDivisi"
                                                   class="size-4 rounded border-gray-300 text-dongker-600 focus:ring-dongker-400" />
                                            <span class="text-sm text-gray-700">{{ $div->nama_divisi }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                            <button type="button" x-on:click="resetGroup()"
                                    class="text-xs font-semibold text-gray-400 hover:text-dongker-600">Reset</button>
                            <button type="button" x-on:click="applied()"
                                    class="inline-flex items-center rounded-lg bg-dongker-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-dongker-500">Apply</button>
                        </div>
                    </div>
                </x-ui.popover.overlay>
            </x-ui.popover>

            <button type="button" wire:click="openImport"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-dongker-200 bg-white px-4 py-2 text-sm font-semibold text-dongker-600 shadow-sm transition hover:bg-dongker-50">
                <x-ui.icon name="arrow-up-tray" class="size-4" />
                Import
            </button>

            <button type="button" wire:click="openCreate"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-dongker-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-dongker-500 hover:shadow-md">
                <x-ui.icon name="plus" class="size-4 text-white" />
                Add Person
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">Name</th>
                    <th class="px-3 py-3 font-semibold">Email</th>
                    <th class="px-3 py-3 font-semibold">Role</th>
                    <th class="px-3 py-3 font-semibold">Plant</th>
                    <th class="px-3 py-3 font-semibold">Division</th>
                    <th class="px-3 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($people as $p)
                    @php
                        $roleBadge = match($p->peran) {
                            'manager' => 'bg-dongker-100 text-dongker-700',
                            'admin' => 'bg-blue-100 text-blue-700',
                            default => 'bg-gray-100 text-gray-600',
                        };
                        $isSelf = $p->id === $currentUserId;
                        // Admins may only manage karyawan accounts; admin/manager rows are read-only to them.
                        $canManageRow = $currentUserIsManager || $p->peran === 'karyawan';
                    @endphp
                    <tr wire:key="ppl-{{ $p->id }}" class="border-b border-gray-50 {{ $p->status_akun === 'active' ? '' : 'opacity-50' }}">
                        <td class="px-5 py-3 font-semibold text-dongker-700">{{ $p->karyawan?->nama ?? '—' }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $p->karyawan?->email ?? '—' }}</td>
                        <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $roleBadge }}">{{ ucfirst($p->peran) }}</span></td>
                        <td class="px-3 py-3 text-gray-600">{{ $p->karyawan?->lokasi?->nama_lokasi ?? '—' }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $p->karyawan?->divisi?->nama_divisi ?? '—' }}</td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $p->status_akun === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ ucfirst($p->status_akun) }}</span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                @if(! $canManageRow)
                                    <span class="text-[10px] text-gray-300 italic px-1">manager only</span>
                                @else
                                    <button type="button" wire:click="openEdit({{ $p->id }})"
                                            class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-600 transition hover:border-dongker-200 hover:text-dongker-600">Edit</button>

                                    @unless($isSelf)
                                        @if($p->status_akun === 'active')
                                            <button type="button" wire:click="toggleStatus({{ $p->id }})"
                                                    class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-amber-600 transition hover:border-amber-200">Deactivate</button>
                                        @else
                                            <button type="button" wire:click="toggleStatus({{ $p->id }})"
                                                    class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-green-600 transition hover:border-green-200">Activate</button>
                                        @endif

                                        <button type="button" wire:click="deleteIfUnused({{ $p->id }})"
                                                class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-red-600 transition hover:border-red-200">Delete</button>
                                    @else
                                        <span class="text-[10px] text-gray-300 italic px-1">you</span>
                                    @endunless
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">No people match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($people->hasPages())
        <div class="mt-4">{{ $people->links() }}</div>
    @endif

    {{-- Add/Edit modal --}}
    <div
        x-data="{ open: @entangle('showModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'people-modal' }) : $dispatch('close-modal', { id: 'people-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'people-modal') { $wire.closeModal() }"
    >
        @php $editingSelf = $editingId !== null && $editingId === $currentUserId; @endphp
        <x-ui.modal id="people-modal" backdrop="blur" width="2xl"
                    :heading="$editingId ? 'Edit Person' : 'Add Person'">
            <form wire:submit.prevent="save" class="space-y-5">
                @if($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5">
                        <ul class="space-y-0.5">
                            @foreach($errors->all() as $err)
                                <li class="text-[12px] text-red-600">• {{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Person section --}}
                <div>
                    <h4 class="text-[12px] font-semibold uppercase tracking-wider text-dongker-500 mb-3">Person</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium text-gray-600">Name</label>
                            <input type="text" wire:model="nama" class="mt-1 w-full rounded-lg border {{ $errors->has('nama') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">Email</label>
                            <input type="email" wire:model="email" class="mt-1 w-full rounded-lg border {{ $errors->has('email') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">Phone <span class="text-gray-400 font-normal">(optional)</span></label>
                            <input type="text" wire:model="noTelepon" class="mt-1 w-full rounded-lg border {{ $errors->has('noTelepon') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">Jabatan</label>
                            <select wire:model="idJabatan" class="mt-1 w-full rounded-lg border {{ $errors->has('idJabatan') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400">
                                <option value="">Select jabatan</option>
                                @foreach($jabatanOptions as $j)
                                    <option value="{{ $j->id }}">{{ $j->nama_jabatan }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">Division</label>
                            <select wire:model="idDivisi" class="mt-1 w-full rounded-lg border {{ $errors->has('idDivisi') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400">
                                <option value="">Select division</option>
                                @foreach($divisiOptions as $d)
                                    <option value="{{ $d->id }}">{{ $d->nama_divisi }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">Location (Plant)</label>
                            <select wire:model="idLokasi" class="mt-1 w-full rounded-lg border {{ $errors->has('idLokasi') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400">
                                <option value="">Select location</option>
                                @foreach($lokasiOptions as $l)
                                    <option value="{{ $l->id }}">{{ $l->nama_lokasi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Account section --}}
                <div class="border-t border-gray-100 pt-4">
                    <h4 class="text-[12px] font-semibold uppercase tracking-wider text-dongker-500 mb-3">Account</h4>
                    @if($editingSelf)
                        <p class="mb-3 text-[11px] text-amber-600">You cannot change your own role or status.</p>
                    @elseif($editingRole === 'manager')
                        <p class="mb-3 text-[11px] text-amber-600">Manager role is locked and cannot be changed here.</p>
                    @elseif(! $currentUserIsManager)
                        <p class="mb-3 text-[11px] text-amber-600">Admins can only create employee (karyawan) accounts.</p>
                    @endif
                    @php $roleLocked = $editingSelf || ($editingRole === 'manager') || ! $currentUserIsManager; @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium text-gray-600">Role</label>
                            <select wire:model="peran" @disabled($roleLocked) class="mt-1 w-full rounded-lg border {{ $errors->has('peran') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                <option value="karyawan">Karyawan</option>
                                @if($currentUserIsManager)
                                    <option value="admin">Admin</option>
                                @endif
                                @if($editingRole === 'manager')
                                    <option value="manager">Manager</option>
                                @endif
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">Status</label>
                            <select wire:model="statusAkun" @disabled($editingSelf) class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">{{ $editingId ? 'Reset password (leave blank to keep)' : 'Password' }} @unless($editingId)<span class="text-gray-400 font-normal">(optional)</span>@endunless</label>
                            <input type="password" wire:model="password" class="mt-1 w-full rounded-lg border {{ $errors->has('password') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400" />
                            @unless($editingId)
                                <p class="mt-1 text-[11px] text-gray-400">Leave blank to use the default password <b>{{ \App\Livewire\Manager\People::DEFAULT_PASSWORD }}</b>.</p>
                            @endunless
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">Confirm password</label>
                            <input type="password" wire:model="password_confirmation" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-dongker-400" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-dongker-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-dongker-500">
                        {{ $editingId ? 'Update' : 'Create' }}
                    </button>
                    <button type="button" x-on:click="$dispatch('close-modal', { id: 'people-modal' })" class="text-sm text-gray-400 hover:text-gray-600">Cancel</button>
                </div>
            </form>
        </x-ui.modal>
    </div>

    {{-- Import modal --}}
    <div
        x-data="{ open: @entangle('showImportModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'people-import-modal' }) : $dispatch('close-modal', { id: 'people-import-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'people-import-modal') { $wire.set('showImportModal', false) }"
    >
        <x-ui.modal id="people-import-modal" backdrop="blur" width="3xl" heading="Import people from Excel">
            @php
                $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-lg bg-dongker-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-dongker-500 disabled:opacity-50 disabled:cursor-not-allowed';
                $btnSecondary = 'inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 hover:text-gray-800';
            @endphp

            {{-- STAGE 1: upload --}}
            @if($importStage === 'upload')
                <div class="space-y-4">
                    <div class="rounded-lg border border-dongker-100 bg-dongker-50/60 px-3 py-2.5 text-[12px] text-gray-600">
                        <p class="font-semibold text-dongker-700 mb-1">File requirements (.xlsx):</p>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <li><b>Display name</b> becomes Name, <b>User principal name</b> becomes Account (email).</li>
                            <li><b>Title</b> becomes Position, <b>Department</b> becomes Division, <b>Office</b> becomes Plant. Each value is matched to existing records (upper and lower case ignored). If nothing matches, it stays empty.</li>
                            <li>Accounts are created as active employees with the default password <b>{{ \App\Application\Services\PersonImportService::DEFAULT_PASSWORD }}</b> (must be changed).</li>
                            <li>Emails that already exist are <b>skipped</b>.</li>
                        </ul>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600">Excel file (.xlsx)</label>
                        <div class="mt-1 flex flex-wrap items-center gap-3">
                            <label class="{{ $btnSecondary }} cursor-pointer">
                                <x-ui.icon name="arrow-up-tray" class="size-4" />
                                Choose file
                                <input type="file" wire:model="importFile" accept=".xlsx" class="hidden" />
                            </label>
                            <span class="text-xs text-gray-500">
                                <span wire:loading.remove wire:target="importFile">{{ $importFile ? $importFile->getClientOriginalName() : 'No file chosen' }}</span>
                                <span wire:loading wire:target="importFile" class="text-gray-400">Uploading...</span>
                            </span>
                        </div>
                        @error('importFile') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="button" wire:click="previewImport" wire:loading.attr="disabled" wire:target="previewImport,importFile"
                                @disabled(! $importFile) class="{{ $btnPrimary }}">
                            <span wire:loading.remove wire:target="previewImport">Preview</span>
                            <span wire:loading wire:target="previewImport">Processing...</span>
                        </button>
                    </div>
                </div>
            @endif

            {{-- STAGE 2: preview --}}
            @if($importStage === 'preview')
                @php
                    $cNew = collect($importPreview)->where('status', 'new')->count();
                    $cDup = collect($importPreview)->where('status', 'duplicate')->count();
                    $cInv = collect($importPreview)->where('status', 'invalid')->count();
                @endphp
                <div class="space-y-3">
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span class="font-semibold text-green-700">Will create: {{ $cNew }}</span>
                        <span class="font-semibold text-gray-500">Duplicate: {{ $cDup }}</span>
                        <span class="font-semibold text-red-600">Invalid: {{ $cInv }}</span>
                        <span class="text-gray-400">Total: {{ count($importPreview) }}</span>
                    </div>

                    <div class="max-h-[420px] overflow-auto rounded-xl border border-gray-200">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr class="text-[10px] uppercase tracking-wider text-gray-400">
                                    <th class="px-3 py-2 font-semibold">Name</th>
                                    <th class="px-3 py-2 font-semibold">Email</th>
                                    <th class="px-3 py-2 font-semibold">Position</th>
                                    <th class="px-3 py-2 font-semibold">Division</th>
                                    <th class="px-3 py-2 font-semibold">Plant</th>
                                    <th class="px-3 py-2 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($importPreview as $r)
                                    <tr class="border-t border-gray-100 {{ $r['status'] === 'invalid' ? 'bg-red-50/40' : ($r['status'] === 'duplicate' ? 'opacity-60' : '') }}">
                                        <td class="px-3 py-1.5 text-gray-700">{{ $r['nama'] ?: '(empty)' }}</td>
                                        <td class="px-3 py-1.5 text-gray-600">{{ $r['email'] ?: '(empty)' }}</td>
                                        <td class="px-3 py-1.5 {{ $r['jabatan_name'] ? 'text-gray-700' : 'text-gray-300 italic' }}">{{ $r['jabatan_name'] ?? 'Empty' }}</td>
                                        <td class="px-3 py-1.5 {{ $r['divisi_name'] ? 'text-gray-700' : 'text-gray-300 italic' }}">{{ $r['divisi_name'] ?? 'Empty' }}</td>
                                        <td class="px-3 py-1.5 {{ $r['lokasi_name'] ? 'text-gray-700' : 'text-gray-300 italic' }}">{{ $r['lokasi_name'] ?? 'Empty' }}</td>
                                        <td class="px-3 py-1.5">
                                            @if($r['status'] === 'new')
                                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700">New</span>
                                            @elseif($r['status'] === 'duplicate')
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">Duplicate</span>
                                            @else
                                                <span title="{{ $r['error'] }}" class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">Invalid</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport"
                                @disabled($cNew === 0) class="{{ $btnPrimary }}">
                            <span wire:loading.remove wire:target="confirmImport">Confirm import ({{ $cNew }})</span>
                            <span wire:loading wire:target="confirmImport">Saving...</span>
                        </button>
                        <button type="button" wire:click="backToUpload" class="{{ $btnSecondary }}">Change file</button>
                        <button type="button" x-on:click="$dispatch('close-modal', { id: 'people-import-modal' })" class="{{ $btnSecondary }}">Close</button>
                    </div>
                </div>
            @endif

            {{-- STAGE 3: done --}}
            @if($importStage === 'done' && $importResult)
                <div class="space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            <span class="font-semibold text-green-700">Created: {{ $importResult['created'] }}</span>
                            <span class="font-semibold text-gray-500">Skipped: {{ $importResult['skipped'] }}</span>
                            <span class="font-semibold text-red-600">Invalid: {{ $importResult['invalid'] }}</span>
                        </div>
                        @if(count($importResult['errors']) > 0)
                            <div class="mt-2 max-h-40 overflow-y-auto border-t border-gray-100 pt-2">
                                <ul class="space-y-0.5">
                                    @foreach($importResult['errors'] as $e)
                                        <li class="text-[11px] text-red-600">Row {{ $e['row'] }}: {{ $e['message'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" wire:click="openImport" class="{{ $btnSecondary }}">Import another file</button>
                        <button type="button" x-on:click="$dispatch('close-modal', { id: 'people-import-modal' })" class="{{ $btnPrimary }}">Close</button>
                    </div>
                </div>
            @endif
        </x-ui.modal>
    </div>
</div>

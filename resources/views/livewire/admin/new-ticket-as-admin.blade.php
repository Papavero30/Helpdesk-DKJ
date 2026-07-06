<div>
    {{-- Full-screen submit loading overlay --}}
    <div wire:loading.flex wire:target="confirmSubmit"
         class="fixed inset-0 z-[60] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl px-8 py-7 flex flex-col items-center gap-4 max-w-sm mx-4 animate-fade-in">
            <div class="relative size-14">
                <div class="absolute inset-0 rounded-full border-4 border-dongker-100"></div>
                <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-dongker-600 animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <x-ui.icon name="paper-airplane" class="size-5 text-dongker-600" />
                </div>
            </div>
            <div class="text-center">
                <p class="text-sm font-bold text-dongker-700">Filing ticket on behalf...</p>
                <p class="text-xs text-gray-500 mt-1">Please don't close this window.</p>
            </div>
        </div>
    </div>

    {{-- Modal back button + next #TKT badge --}}
    <div class="flex items-center justify-between mb-4">
        <button type="button" x-on:click="$dispatch('close-modal', { id: 'new-ticket-as-admin-modal' })"
                class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-dongker-600 transition-colors">
            <x-ui.icon name="arrow-left" class="size-4" />
            Back
        </button>
        <span class="inline-flex items-center text-xl font-extrabold text-dongker-700 bg-dongker-50 border border-dongker-100 px-3 py-1 rounded-xl">
            #TKT{{ str_pad($nextTicketNumber, 2, '0', STR_PAD_LEFT) }}
        </span>
    </div>

    {{-- Validation summary --}}
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 animate-fade-in">
            <div class="flex items-start gap-2">
                <x-ui.icon name="exclamation-circle" class="size-4 text-red-500 shrink-0 mt-0.5" />
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold text-red-700 uppercase tracking-wider">Please fix the following:</p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li class="text-[12px] text-red-600 leading-snug">• {{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- ══ Requester picker (searchable dropdown) ══ --}}
    <x-ui.field class="mb-5">
        <x-ui.label class="!text-[12px] !font-semibold !uppercase !tracking-wider !text-dongker-500">Filing on behalf of</x-ui.label>

        @if($selectedUserId && $selectedUserMeta)
            {{-- Selected state: select-like surface showing the chosen user with an X to clear --}}
            <div class="relative">
                <div class="w-full rounded-lg border border-gray-200 bg-white pl-3 pr-10 py-2 text-sm">
                    <div class="font-semibold text-gray-800 truncate leading-tight">{{ $selectedUserMeta['name'] }}</div>
                    <div class="text-[11px] text-gray-500 truncate">{{ $selectedUserMeta['email'] }} · {{ $selectedUserMeta['lokasi_name'] }}</div>
                </div>
                <button type="button" wire:click="clearSelectedUser"
                        title="Change user"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 transition hover:text-red-500 hover:bg-red-50">
                    <x-ui.icon name="x-mark" class="size-4" />
                </button>
            </div>
        @else
            {{-- Unselected state: combobox — the input IS the search; click/focus opens the panel --}}
            <div x-data="{ open: false }"
                 x-on:click.outside="open = false"
                 class="relative">
                <div class="relative">
                    <input type="text"
                           wire:model.live.debounce.300ms="userSearch"
                           x-on:focus="open = true"
                           x-on:click="open = true"
                           placeholder="Select user to file ticket for..."
                           autocomplete="off"
                           class="w-full rounded-lg border {{ $errors->has('selectedUserId') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 pr-10 py-2.5 text-sm text-gray-700 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-dongker-400 focus:border-dongker-400" />
                    <button type="button" @click="open = !open"
                            tabindex="-1"
                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded text-gray-400 hover:text-gray-600">
                        <span class="block transition-transform" :class="{ 'rotate-180': open }">
                            <x-ui.icon name="chevron-down" class="size-4" />
                        </span>
                    </button>
                </div>

                <div x-show="open"
                     x-cloak
                     x-transition.opacity.duration.150ms
                     class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg overflow-hidden">
                    {{-- Result list capped at ~5 visible rows; scroll for the rest --}}
                    <div class="max-h-[260px] overflow-y-auto divide-y divide-gray-100">
                        @forelse($searchResults as $u)
                            <button type="button"
                                    @click="$wire.selectUser({{ $u->id }}); open = false"
                                    class="w-full text-left px-3 py-2 transition hover:bg-dongker-50">
                                <div class="text-sm font-medium text-gray-800 truncate">{{ $u->karyawan->nama }}</div>
                                <div class="text-[11px] text-gray-500 truncate">{{ $u->karyawan->email }} · {{ $u->karyawan->lokasi?->nama_lokasi ?? '—' }}</div>
                            </button>
                        @empty
                            <div class="px-3 py-6 text-center text-xs text-gray-400">
                                {{ $userSearch === '' ? 'No karyawan users available.' : 'No users match your search.' }}
                            </div>
                        @endforelse
                    </div>

                    @if($searchResults->count() >= 20)
                        <div class="px-3 py-1.5 border-t border-gray-100 bg-gray-50 text-[10px] text-gray-400">
                            Showing first 20 results. Refine your search if you don't see the user.
                        </div>
                    @endif
                </div>
            </div>
            @error('selectedUserId') <x-ui.error>{{ $message }}</x-ui.error> @enderror
        @endif
    </x-ui.field>

    <form wire:submit.prevent class="space-y-5">
        @php
            $selectedKategori = collect($daftarKategori)->firstWhere('value', (int) $kategori_id);
        @endphp

        {{-- Row 1: Location + Category --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-ui.field>
                <x-ui.label for="lokasi_id_admin" class="!text-[12px] !font-semibold !uppercase !tracking-wider !text-dongker-500">Incident Location</x-ui.label>
                <select wire:model.live="lokasi_id" id="lokasi_id_admin"
                        @disabled(!$selectedUserId)
                        class="w-full rounded-lg border {{ $errors->has('lokasi_id') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400 focus:border-dongker-400 disabled:bg-gray-100 disabled:cursor-not-allowed">
                    <option value="">Select incident location</option>
                    @foreach($daftarLokasi as $lok)
                        <option value="{{ $lok->id }}">{{ $lok->nama_lokasi }}</option>
                    @endforeach
                </select>
                @if(!$selectedUserId)
                    <p class="mt-1 text-[11px] text-gray-400 italic">Select a requester first.</p>
                @endif
                @error('lokasi_id') <x-ui.error>{{ $message }}</x-ui.error> @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.label for="kategori_id_admin" class="!text-[12px] !font-semibold !uppercase !tracking-wider !text-dongker-500">
                    Category
                    @if($selectedKategori && isset($selectedKategori['sla_hours']))
                        <span class="ml-1 text-[11px] font-normal text-gray-500 normal-case tracking-normal">(SLA: {{ $selectedKategori['sla_hours'] }} hours)</span>
                    @endif
                </x-ui.label>
                <select wire:model.live="kategori_id" id="kategori_id_admin"
                        @disabled(!$selectedUserId)
                        class="w-full rounded-lg border {{ $errors->has('kategori_id') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400 focus:border-dongker-400 disabled:bg-gray-100 disabled:cursor-not-allowed">
                    <option value="">Select category</option>
                    @foreach($daftarKategori as $kat)
                        <option value="{{ $kat['value'] }}">{{ $kat['label'] }}</option>
                    @endforeach
                </select>
                @if($selectedKategori && ! empty($selectedKategori['examples']))
                    <p class="mt-1.5 text-xs text-gray-400 italic">
                        {{ $selectedKategori['examples'] }}
                    </p>
                @endif
                @error('kategori_id') <x-ui.error>{{ $message }}</x-ui.error> @enderror
            </x-ui.field>
        </div>

        @if($repetitiveHint)
            <div class="rounded-xl border border-dongker-100 bg-dongker-50 p-4 text-sm text-dongker-700 flex items-start gap-3">
                <x-ui.icon name="arrow-path" class="size-5 mt-0.5 text-dongker-500" />
                <div>
                    <p class="font-semibold">This user has reported {{ $repetitiveHint['count'] }} similar closed issues at this location &amp; category before.</p>
                    <p class="text-dongker-500 text-xs mt-0.5">This ticket will automatically join the repetitive group on submit.</p>
                </div>
            </div>
        @endif

        <x-ui.field>
            <x-ui.label for="deskripsi_admin" class="!text-[12px] !font-semibold !uppercase !tracking-wider !text-dongker-500">Description</x-ui.label>
            <textarea wire:model.live="deskripsi" id="deskripsi_admin" rows="4" maxlength="100"
                      @disabled(!$selectedUserId)
                      placeholder="Describe the issue (relay what the requester reported)..."
                      class="w-full rounded-lg border {{ $errors->has('deskripsi') ? 'border-red-400' : 'border-gray-200' }} bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-dongker-400 focus:border-dongker-400 resize-none disabled:bg-gray-100 disabled:cursor-not-allowed"></textarea>
            <div class="flex items-center justify-between mt-1">
                <p class="text-[11px] text-gray-400">Min 10 chars · Max 100</p>
                <p class="text-[11px] {{ mb_strlen($deskripsi) >= 10 ? 'text-green-500 font-semibold' : 'text-gray-400' }}">
                    {{ mb_strlen($deskripsi) }}
                </p>
            </div>
            @error('deskripsi') <x-ui.error>{{ $message }}</x-ui.error> @enderror
        </x-ui.field>

        {{-- Attachments --}}
        <div class="border-t border-gray-100 pt-5 mt-5">
            <h4 class="text-xs font-semibold text-dongker-500 uppercase tracking-wider mb-3">Attachments (optional)</h4>

            <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-dongker-300 hover:bg-dongker-50/30 transition">
                <x-ui.icon name="paper-clip" class="size-5 text-gray-400 mb-1" />
                <span class="text-xs text-gray-500">Click to attach files</span>
                <span class="text-[10px] text-gray-400 mt-0.5">JPG, PNG, WEBP, PDF — max 2 MB each, up to 5 files</span>
                <input type="file" wire:model="lampiran" multiple accept="image/jpeg,image/png,image/webp,application/pdf"
                       class="hidden" />
            </label>

            @error('lampiran') <x-ui.error>{{ $message }}</x-ui.error> @enderror
            @error('lampiran.*') <x-ui.error>{{ $message }}</x-ui.error> @enderror

            @if(!empty($lampiran))
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach($lampiran as $idx => $file)
                        <div class="relative h-20 w-20 rounded-lg border border-gray-200 overflow-hidden group">
                            @if(str_starts_with($file->getMimeType(), 'image/'))
                                <img src="{{ $file->temporaryUrl() }}" class="h-full w-full object-cover" alt="preview" />
                            @else
                                <div class="flex flex-col items-center justify-center h-full text-gray-500 bg-gray-50">
                                    <x-ui.icon name="document-text" class="size-6 text-red-500" />
                                    <span class="text-[9px] truncate max-w-[60px] mt-0.5 px-1">{{ $file->getClientOriginalName() }}</span>
                                </div>
                            @endif
                            <button type="button" wire:click="removeLampiran({{ $idx }})"
                                    class="absolute top-0.5 right-0.5 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition">
                                <x-ui.icon name="x-mark" class="size-3" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="button"
                    wire:click="openSubmitConfirm"
                    @disabled(!$selectedUserId)
                    class="inline-flex items-center gap-2 rounded-xl bg-dongker-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-dongker-600/25 transition-all duration-200 hover:bg-dongker-500 hover:shadow-xl hover:-translate-y-0.5 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:shadow-none"
                    wire:loading.attr="disabled" wire:target="openSubmitConfirm">
                <span wire:loading.remove wire:target="openSubmitConfirm" class="inline-flex items-center gap-2">
                    <x-ui.icon name="paper-airplane" class="size-4 text-white" />
                    Submit 
                </span>
                <span wire:loading wire:target="openSubmitConfirm" class="inline-flex items-center gap-2">
                    <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Validating...
                </span>
            </button>
            <button type="button" x-on:click="$dispatch('close-modal', { id: 'new-ticket-as-admin-modal' })"
                    class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
                Cancel
            </button>
        </div>
    </form>

    {{-- Submit confirm modal --}}
    <div
        x-data="{ open: @entangle('showSubmitConfirm') }"
        x-effect="open ? $dispatch('open-modal', { id: 'submit-ticket-as-admin-confirm' }) : $dispatch('close-modal', { id: 'submit-ticket-as-admin-confirm' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'submit-ticket-as-admin-confirm') { open = false }"
    >
        <x-ui.modal id="submit-ticket-as-admin-confirm" backdrop="blur" width="md"
                    heading="Confirm filing">
            <p class="text-sm text-gray-600">
                File this ticket on behalf of
                <span class="font-semibold text-dongker-700">{{ $selectedUserMeta['name'] ?? 'the selected user' }}</span>?
            </p>
            <p class="text-xs text-gray-400 mt-1">The activity log will record you as the actor.</p>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" wire:click="$set('showSubmitConfirm', false)"
                        class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
                    Cancel
                </button>
                <button type="button"
                        x-on:click="$wire.set('showSubmitConfirm', false, false); $wire.confirmSubmit();"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-dongker-600 px-5 py-2 text-sm font-semibold text-white hover:bg-dongker-500 transition disabled:opacity-75 disabled:cursor-wait"
                        wire:loading.attr="disabled" wire:target="confirmSubmit">
                    <x-ui.icon name="check" class="size-4 text-white" />
                    Confirm
                </button>
            </div>
        </x-ui.modal>
    </div>
</div>

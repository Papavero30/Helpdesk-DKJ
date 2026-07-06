<div>
    {{-- Avatar preview --}}
    <div class="flex flex-col items-center py-4 px-5">
        @if($newAvatar)
            <img src="{{ $newAvatar->temporaryUrl() }}" class="h-20 w-20 rounded-full object-cover border-2 border-gray-200" alt="Preview" />
        @elseif(auth()->user()->avatarUrl())
            <img src="{{ auth()->user()->avatarUrl() }}" class="h-20 w-20 rounded-full object-cover border-2 border-gray-200" alt="Avatar" />
        @else
            <div class="flex h-20 w-20 items-center justify-center rounded-full text-2xl font-bold text-white" style="background:#0E4260;">
                {{ auth()->user()->initials() }}
            </div>
        @endif

        <div class="mt-3 text-center">
            <div class="font-semibold text-[#1E293B]">{{ auth()->user()->name }}</div>
            <div class="text-xs text-[#64748B]">{{ auth()->user()->email }}</div>
            <div class="text-[11px] text-[#94A3B8] capitalize mt-0.5">{{ auth()->user()->peran }}
                @if(auth()->user()->isAdmin() && auth()->user()->karyawan?->lokasi)
                    &middot; {{ auth()->user()->karyawan->lokasi->nama_lokasi }}
                @endif
                @if(auth()->user()->isKaryawan() && auth()->user()->karyawan?->divisi)
                    &middot; {{ auth()->user()->karyawan->divisi->nama_divisi }}
                @endif
            </div>
        </div>
    </div>

    {{-- Upload / Remove --}}
    <div class="border-t border-gray-100 px-5 py-3">
        @if($newAvatar)
            <div class="flex gap-2">
                <button wire:click="uploadAvatar" class="flex-1 rounded-lg py-2 text-xs font-semibold text-white transition" style="background:#0E4260;">
                    Save Photo
                </button>
                <button wire:click="$set('newAvatar', null)" class="rounded-lg px-3 py-2 text-xs font-semibold text-[#64748B] bg-gray-100 hover:bg-gray-200 transition">
                    Cancel
                </button>
            </div>
        @else
            <div class="flex gap-2">
                <label class="flex-1 cursor-pointer rounded-lg border border-gray-200 py-2 text-center text-xs font-semibold text-[#1E293B] hover:bg-gray-50 transition">
                    <input type="file" wire:model="newAvatar" accept="image/*" class="hidden" />
                    Change Photo
                </label>
                @if(auth()->user()->foto_profil)
                    <button wire:click="removeAvatar" wire:confirm="Remove profile photo?" class="rounded-lg px-3 py-2 text-xs font-semibold text-red-500 hover:bg-red-50 transition">
                        Remove
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- Logout --}}
    <div class="border-t border-gray-100 px-5 py-3">
        <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-red-500 hover:bg-red-50 transition">
                Sign Out
            </button>
        </form>
    </div>
</div>

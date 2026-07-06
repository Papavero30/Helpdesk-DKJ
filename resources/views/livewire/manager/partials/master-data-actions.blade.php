<div class="inline-flex items-center gap-1.5">
    <button type="button" wire:click="openEdit({{ $row->id }})"
            class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-600 transition hover:border-dongker-200 hover:text-dongker-600">Edit</button>

    @if($row->is_active)
        <button type="button" wire:click="confirmArchive({{ $row->id }})"
                class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-amber-600 transition hover:border-amber-200">Archive</button>
    @else
        <button type="button" wire:click="restore({{ $row->id }})"
                class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-green-600 transition hover:border-green-200">Restore</button>
    @endif

    @if($usage > 0)
        <button type="button" disabled title="In use by {{ $usage }} record(s). Archive instead"
                class="rounded-lg border border-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-300 cursor-not-allowed">Delete</button>
    @else
        <button type="button" wire:click="deleteIfUnused({{ $row->id }})"
                class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-red-600 transition hover:border-red-200">Delete</button>
    @endif
</div>

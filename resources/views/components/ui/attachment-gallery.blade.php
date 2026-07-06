@props(['files', 'maxVisible' => 3, 'centered' => false, 'chatMode' => false])
@php
    $allFiles = $files->values();
    $totalCount = $allFiles->count();
    $hiddenCount = max(0, $totalCount - $maxVisible);
@endphp

@if($files->isNotEmpty())
@if($chatMode)
{{-- ===== CHAT MODE: WhatsApp-style inline, fixed size, no popup ===== --}}
<div class="mt-2 space-y-1.5"
     x-data="{
         expandedIdx: null,
         toggle(idx) { this.expandedIdx = this.expandedIdx === idx ? null : idx; }
     }">
    @foreach($allFiles as $idx => $file)
        <div class="rounded-xl border border-gray-200 overflow-hidden bg-white w-full max-w-xs">
            <div class="grid items-center px-3 py-1.5 border-b border-gray-100 bg-gray-50/80"
                 style="grid-template-columns: 56px 1fr 56px;">
                <span class="text-[10px] text-gray-400 font-medium">
                    {{ $file->isImage() ? 'Image' : 'PDF' }}
                </span>
                <span class="text-[11px] font-semibold text-gray-700 truncate text-center px-1">{{ $file->nama_asli }}</span>
                <div class="flex items-center justify-end gap-1">
                    <a href="{{ $file->url() }}" download title="Download"
                       class="rounded p-1 text-gray-400 hover:bg-gray-200 transition">
                        <x-ui.icon name="arrow-down-tray" class="size-3.5" />
                    </a>
                    <button type="button" x-on:click="toggle({{ $idx }})" title="Toggle preview"
                            class="rounded p-1 text-gray-400 hover:bg-gray-200 transition">
                        <span x-show="expandedIdx !== {{ $idx }}">
                            <x-ui.icon name="chevron-down" class="size-3.5" />
                        </span>
                        <span x-show="expandedIdx === {{ $idx }}" style="display:none;">
                            <x-ui.icon name="chevron-up" class="size-3.5" />
                        </span>
                    </button>
                </div>
            </div>

            <div x-show="expandedIdx === {{ $idx }}"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="relative bg-gray-50 flex items-center justify-center"
                 style="display:none; height:160px;">
                @if($file->isImage())
                    <img src="{{ $file->url() }}" alt="{{ $file->nama_asli }}"
                         class="block w-full h-full object-contain" />
                @else
                    <div class="flex flex-col items-center justify-center h-full gap-2">
                        <x-ui.icon name="document-text" class="size-10 text-red-400" />
                        <a href="{{ $file->url() }}" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-dongker-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-dongker-500 transition">
                            <x-ui.icon name="arrow-top-right-on-square" class="size-3.5 text-white" />
                            Open PDF
                        </a>
                    </div>
                @endif
            </div>

            <div x-show="expandedIdx !== {{ $idx }}"
                 class="relative cursor-pointer bg-gray-100"
                 style="height:60px;"
                 x-on:click="toggle({{ $idx }})">
                @if($file->isImage())
                    <img src="{{ $file->url() }}" alt="{{ $file->nama_asli }}"
                         class="block w-full h-full object-cover" />
                @else
                    <div class="flex items-center justify-center h-full gap-2">
                        <x-ui.icon name="document-text" class="size-6 text-red-400" />
                        <span class="text-[10px] text-gray-500 font-medium">Tap to preview</span>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>

@else
{{-- ===== TICKET DETAIL MODE: stable single-slot preview, all thumbs revealable ===== --}}
<div class="border-t border-gray-100 pt-4 mt-4">
    <h4 class="text-xs font-semibold text-dongker-500 uppercase tracking-wider mb-2">
        Attachments ({{ $totalCount }})
    </h4>
    <div x-data="{
             expandedIdx: null,
             showAll: false,
             files: {{ Js::from($allFiles->map(fn($f) => ['url' => $f->url(), 'nama' => $f->nama_asli, 'isImage' => $f->isImage(), 'isPdf' => $f->isPdf()])) }},
             toggle(idx) {
                 this.expandedIdx = this.expandedIdx === idx ? null : idx;
             },
             close() { this.expandedIdx = null; }
         }"
         x-on:keydown.escape.window="close()">

        {{-- Thumbnail row: shows visible + (+N) button OR all when showAll --}}
        <div class="flex flex-wrap gap-2 {{ $centered ? 'justify-center' : '' }}">
            @foreach($allFiles as $idx => $file)
                <button type="button"
                        x-show="showAll || {{ $idx }} < {{ $maxVisible }}"
                        @if($idx >= $maxVisible) style="display:none;" @endif
                        x-on:click="toggle({{ $idx }})"
                        class="relative h-20 w-20 rounded-lg border overflow-hidden transition shrink-0"
                        :class="expandedIdx === {{ $idx }} ? 'border-dongker-400 ring-2 ring-dongker-200' : 'border-gray-200 hover:border-dongker-300'">
                    @if($file->isImage())
                        <img src="{{ $file->url() }}" class="h-full w-full object-cover" alt="{{ $file->nama_asli }}" />
                    @else
                        <div class="flex flex-col items-center justify-center h-full text-gray-500 bg-gray-50">
                            <x-ui.icon name="document-text" class="size-6 text-red-500" />
                            <span class="text-[9px] truncate max-w-[60px] mt-1 px-1">PDF</span>
                        </div>
                    @endif
                </button>
            @endforeach

            @if($hiddenCount > 0)
                <button type="button"
                        x-show="!showAll"
                        x-on:click="showAll = true"
                        class="h-20 w-20 rounded-lg border border-gray-200 bg-gray-50 hover:bg-dongker-50 hover:border-dongker-300 flex items-center justify-center text-sm font-bold text-gray-600 transition shrink-0">
                    +{{ $hiddenCount }}
                </button>
                <button type="button"
                        x-show="showAll"
                        x-on:click="showAll = false; close()"
                        style="display:none;"
                        class="h-20 w-20 rounded-lg border border-dashed border-gray-300 bg-white hover:bg-gray-50 flex flex-col items-center justify-center text-[10px] font-semibold text-gray-500 transition shrink-0 gap-0.5">
                    <x-ui.icon name="chevron-up" class="size-4" />
                    Show less
                </button>
            @endif
        </div>

        {{-- SINGLE preview slot (rendered once, content swaps via Alpine) — stable scroll position --}}
        <div x-show="expandedIdx !== null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="w-full mt-3 rounded-xl border border-gray-200 overflow-hidden bg-white"
             style="display:none;">

            {{-- Header: 3-col grid for centered title --}}
            <div class="grid items-center gap-2 px-4 py-2 border-b border-gray-100 bg-gray-50/80"
                 style="grid-template-columns: 80px 1fr 80px;">
                <span class="text-[10px] text-gray-400 font-medium"
                      x-text="files[expandedIdx]?.isImage ? 'Preview' : 'PDF'"></span>
                <span class="text-xs font-semibold text-gray-700 truncate text-center"
                      x-text="files[expandedIdx]?.nama"></span>
                <div class="flex items-center justify-end gap-1.5">
                    <a :href="files[expandedIdx]?.url" download
                       class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-1 text-[10px] font-semibold text-gray-600 hover:bg-gray-100 transition">
                        <x-ui.icon name="arrow-down-tray" class="size-3" />
                        Save
                    </a>
                    <button type="button" x-on:click="close()"
                            class="rounded-full p-1 text-gray-400 hover:bg-gray-200 transition">
                        <x-ui.icon name="x-mark" class="size-4" />
                    </button>
                </div>
            </div>

            {{-- Content area: fixed 280px so image + PDF look the same size --}}
            <div class="flex items-center justify-center bg-gray-50" style="height:280px;">
                {{-- Image --}}
                <template x-if="files[expandedIdx]?.isImage">
                    <img :src="files[expandedIdx]?.url" :alt="files[expandedIdx]?.nama"
                         class="block w-full h-full object-contain" />
                </template>
                {{-- PDF --}}
                <template x-if="files[expandedIdx] && !files[expandedIdx]?.isImage">
                    <div class="flex flex-col items-center justify-center w-full h-full gap-3 bg-white">
                        <x-ui.icon name="document-text" class="size-16 text-red-400" />
                        <p class="text-sm font-semibold text-gray-700 text-center break-all max-w-xs"
                           x-text="files[expandedIdx]?.nama"></p>
                        <div class="flex items-center gap-2">
                            <a :href="files[expandedIdx]?.url" target="_blank"
                               class="inline-flex items-center gap-2 rounded-xl bg-dongker-600 px-4 py-2 text-xs font-semibold text-white hover:bg-dongker-500 transition">
                                <x-ui.icon name="arrow-top-right-on-square" class="size-4 text-white" />
                                Open PDF
                            </a>
                            <a :href="files[expandedIdx]?.url" download
                               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
                                <x-ui.icon name="arrow-down-tray" class="size-4" />
                                Download
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endif
@endif

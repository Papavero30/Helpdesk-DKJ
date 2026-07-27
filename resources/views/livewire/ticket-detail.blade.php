@php
    $chatStatuses = ['In Progress', 'Close'];
    $hasChat = in_array($tiket->statusTiket?->nama_status, $chatStatuses, true);
    $activePause = $tiket->slaPauseRequests->whereIn('state', ['pending', 'active'])->sortByDesc('id')->first();
@endphp
<div class="flex flex-col gap-6 flex-1 min-h-0">
    {{-- Breadcrumb (includes back button) --}}
    <x-ui.breadcrumb />

    @php $totalInGroup = $tiket->grupTiket?->jumlah ?? 0; @endphp

    {{-- ===== MAIN 2x2 GRID =====
         TL: Ticket card + banner kondisional  |  TR: History log (mirror ticket card height)
         BL: Chat window                        |  BR: Repetitive Group card (static 240px)
         grid-template-rows: auto 1fr → row 1 natural, row 2 fill sisa main minus footer --}}
    <div class="w-full flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-3 gap-6"
         style="grid-template-rows: auto 1fr;"
         x-data="{
             mirrorHeight: 0,
             ro: null,
             mo: null,
             syncHeight() {
                 const ticketEl = this.$refs.ticketCard;
                 if (! ticketEl) return;
                 this.mirrorHeight = ticketEl.offsetHeight;
             },
             init() {
                 const ticketEl = this.$refs.ticketCard;
                 if (! ticketEl) return;
                 this.syncHeight();
                 if (typeof ResizeObserver !== 'undefined') {
                     this.ro = new ResizeObserver(() => this.syncHeight());
                     this.ro.observe(ticketEl);
                     // Observe all direct children too — catches banners that mount/unmount via Livewire
                     Array.from(ticketEl.children).forEach(c => this.ro.observe(c));
                 }
                 if (typeof MutationObserver !== 'undefined') {
                     this.mo = new MutationObserver(() => {
                         this.$nextTick(() => this.syncHeight());
                     });
                     this.mo.observe(ticketEl, { childList: true, subtree: true });
                 }
                 // Recalculate after Livewire morph (banner appear/disappear) + on resize.
                 this.onUpdated = () => this.$nextTick(() => this.syncHeight());
                 this.onResize = () => this.syncHeight();
                 document.addEventListener('livewire:updated', this.onUpdated);
                 window.addEventListener('resize', this.onResize);
                 // wire:navigate swaps this component's DOM out WITHOUT unbinding the
                 // document/window listeners above. Without this cleanup, every ticket
                 // visit leaks a handler that keeps firing stale callbacks on later
                 // pages and freezes SPA navigation (workaround was a hard reload).
                 // Unbind + disconnect right before Livewire navigates away.
                 document.addEventListener('livewire:navigating', () => {
                     document.removeEventListener('livewire:updated', this.onUpdated);
                     window.removeEventListener('resize', this.onResize);
                     this.ro?.disconnect();
                     this.mo?.disconnect();
                 }, { once: true });
             }
         }">

        {{-- ===== TL: Ticket card + edge case banners (col-span-2, row 1) — x-ref untuk track TOTAL TL height ===== --}}
        <div x-ref="ticketCard" class="lg:col-span-2 lg:row-start-1 flex flex-col">
                {{-- === TICKET CARD === --}}
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden animate-slide-up shrink-0">
                    {{-- Hero header --}}
                    <div class="p-5 border-b border-gray-100">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <span class="inline-flex items-center text-2xl font-extrabold text-dongker-700 bg-dongker-50 border border-dongker-100 px-4 py-1.5 rounded-xl">
                                    #TKT{{ str_pad($tiket->id, 2, '0', STR_PAD_LEFT) }}
                                </span>
                                @if($tiket->target_penyelesaian)
                                    <p class="text-[11px] text-gray-500 mt-1">
                                        Should be closed at {{ $tiket->effectiveDeadline()->format('d M Y, H:i') }}
                                        @if($tiket->isSlaPaused())
                                            <span class="ml-1 inline-flex items-center rounded-full bg-dongker-100 px-2 py-0.5 text-[10px] font-semibold text-dongker-700">Deadline paused</span>
                                        @endif
                                    </p>
                                @endif
                            </div>

                            <div class="text-right">
                                @if($tiket->statusTiket)
                                    @php $statusBadgeClass = \App\Models\StatusTiketModel::badgeClass($tiket->statusTiket->nama_status); @endphp
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $statusBadgeClass }}">{{ $tiket->statusTiket->nama_status }}</span>
                                    @if($lastStatusChanged)
                                        <div class="text-[10px] text-gray-400 mt-1">Status changed {{ $lastStatusChanged->created_at->diffForHumans() }}</div>
                                    @endif
                                @endif

                                @if($tiket->isSlaPaused())
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-dongker-100 text-dongker-700">
                                        Deadline paused
                                    </span>
                                @elseif($tiket->statusTiket?->nama_status === 'Close' && $tiket->sla_outcome)
                                    @php
                                        $outcomeBadge = match ($tiket->sla_outcome) {
                                            'on_time'  => ['On Time', 'bg-green-100 text-green-700'],
                                            'overtime' => ['Overtime', 'bg-red-100 text-red-700'],
                                            'ahead'    => ['Ahead of Schedule', 'bg-dongker-100 text-dongker-700'],
                                            default    => ['Unknown', 'bg-gray-100 text-gray-700'],
                                        };
                                    @endphp
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $outcomeBadge[1] }}">
                                        {{ $outcomeBadge[0] }}
                                    </span>
                                @endif
                            </div>
                        </div>

                    </div>

                    {{-- Status Stepper (3 steps: Open / In Progress / Close) — centered --}}
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60">
                        <div class="flex items-center justify-center max-w-2xl mx-auto">
                            @foreach($statusSteps as $step)
                                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                                    <div class="flex flex-col items-center min-w-[80px]">
                                        <span class="size-2 rounded-full {{ $step['active'] ? 'bg-dongker-600' : 'bg-gray-300' }}"></span>
                                        <span class="mt-1 text-[10px] font-semibold {{ $step['active'] ? 'text-dongker-600' : 'text-gray-400' }}">
                                            {{ $step['label'] }}
                                        </span>
                                        @if($step['time'])
                                            <span class="text-[9px] text-gray-400">{{ $step['time']->format('d/m H:i') }}</span>
                                        @endif
                                    </div>
                                    @if(! $loop->last)
                                        <div class="flex-1 h-px {{ $step['active'] ? 'bg-dongker-300' : 'bg-gray-200' }} mx-2 min-w-[40px]"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- === INFORMATION BANNERS (conditional, inside ticket card) === --}}
                    @php
                        $isPauseRequester = auth()->user()->isKaryawan() && auth()->id() === $tiket->id_pengguna;
                        $isPausePic       = auth()->user()->isAdmin() && $tiket->id_penanggung_jawab === auth()->id();
                        $hasPauseBanner   = ($activePause && $isPauseRequester && $activePause->state === 'pending')
                            || ($activePause && $isPausePic)
                            || ($isPausePic && ! $activePause && $tiket->statusTiket?->nama_status === 'In Progress');
                        $hasAnyBanner =
                            (auth()->user()->isKaryawan() && $tiket->siap_konfirmasi && $tiket->statusTiket?->nama_status === 'In Progress' && ! $tiket->penilaian)
                            || (auth()->user()->isKaryawan() && $tampilRating && ! $tiket->penilaian)
                            || ($tiket->penilaian && $tiket->statusTiket?->nama_status === 'Close' && ! $tampilRating)
                            || (auth()->user()->isKaryawan() && $tiket->id_pengguna === auth()->id() && $tiket->repetitive_review_state === 'admin_requested_off')
                            || (auth()->user()->isAdmin() && $tiket->id_penanggung_jawab === auth()->id() && $tiket->repetitive_review_state === 'user_refused')
                            || $hasPauseBanner;
                    @endphp

                    @if($hasAnyBanner)
                        <div class="px-5 pt-4 space-y-3">

                            {{-- 1. FEEDBACK BANNER --}}
                            @if(auth()->user()->isKaryawan() && $tiket->siap_konfirmasi && $tiket->statusTiket?->nama_status === 'In Progress' && ! $tiket->penilaian)
                                <div class="bg-dongker-50 border border-dongker-100 rounded-xl px-4 py-3 animate-fade-in flex items-center gap-3">
                                    <div class="flex items-center gap-2 flex-1 min-w-0">
                                        <x-ui.icon name="question-mark-circle" class="size-5 text-dongker-600 shrink-0" />
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-dongker-700 leading-tight">Has the issue been resolved?</p>
                                            <p class="text-[10px] text-dongker-500 leading-tight truncate">Admin marked as resolved — please confirm.</p>
                                        </div>
                                    </div>
                                    <div class="shrink-0 flex items-center gap-2">
                                        <button wire:click="openFeedbackConfirm(true)"
                                                class="inline-flex items-center gap-1 rounded-md bg-green-500 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-green-600 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="openFeedbackConfirm">
                                            <span wire:loading.remove wire:target="openFeedbackConfirm" class="inline-flex items-center gap-1">
                                                <x-ui.icon name="check-circle" class="size-3" />
                                                Solved
                                            </span>
                                            <span wire:loading wire:target="openFeedbackConfirm" class="inline-flex items-center gap-1">
                                                <svg class="size-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Loading...
                                            </span>
                                        </button>
                                        <button wire:click="openFeedbackConfirm(false)"
                                                class="inline-flex items-center gap-1 rounded-md bg-red-500 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-red-600 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="openFeedbackConfirm">
                                            <span wire:loading.remove wire:target="openFeedbackConfirm" class="inline-flex items-center gap-1">
                                                <x-ui.icon name="x-circle" class="size-3" />
                                                Still Issue
                                            </span>
                                            <span wire:loading wire:target="openFeedbackConfirm" class="inline-flex items-center gap-1">
                                                <svg class="size-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Loading...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- 2. RATING SECTION --}}
                            @if(auth()->user()->isKaryawan() && $tampilRating && ! $tiket->penilaian)
                                <div class="bg-dongker-50 border border-dongker-100 rounded-xl px-4 py-3 animate-slide-up">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-xs font-bold text-dongker-700 leading-tight">Rate our service</h3>
                                            <p class="text-[10px] text-gray-500 leading-tight truncate">How was the {{ $tiket->kategori?->nama_kategori }} support?</p>
                                        </div>
                                        <div x-data="{ hover: 0, rating: @entangle('bintang').live }" class="flex items-center gap-0.5 shrink-0">
                                            @for($i = 1; $i <= 5; $i++)
                                                <button type="button"
                                                        x-on:mouseenter="hover = {{ $i }}"
                                                        x-on:mouseleave="hover = 0"
                                                        x-on:click="rating = {{ $i }}"
                                                        class="text-xl leading-none transition-transform duration-150"
                                                        :class="(hover >= {{ $i }} || rating >= {{ $i }}) ? 'text-amber-400 scale-110' : 'text-gray-300'">
                                                    ★
                                                </button>
                                            @endfor
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-start gap-2">
                                        <div class="flex-1 min-w-0">
                                            <textarea wire:model.live="komentarRating" rows="1" maxlength="100"
                                                      placeholder="Share your experience (min 10 chars)..."
                                                      class="w-full rounded-md border border-dongker-100 bg-white px-2.5 py-1.5 text-[11px] text-gray-700 focus:ring-1 focus:ring-dongker-400 focus:border-dongker-400 resize-none"></textarea>
                                            <div class="flex items-center justify-between mt-0.5">
                                                <p class="text-[9px] text-gray-400">Min 10 · Max 100</p>
                                                <p class="text-[9px] {{ mb_strlen($komentarRating) >= 10 ? 'text-green-500 font-semibold' : 'text-gray-400' }}">{{ mb_strlen($komentarRating) }}</p>
                                            </div>
                                        </div>
                                        <button wire:click="openRatingConfirm"
                                                class="shrink-0 inline-flex items-center gap-1 rounded-md bg-dongker-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-dongker-500 active:scale-95 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="openRatingConfirm">
                                            <span wire:loading.remove wire:target="openRatingConfirm" class="inline-flex items-center gap-1">
                                                <x-ui.icon name="paper-airplane" class="size-3 text-white" />
                                                Submit
                                            </span>
                                            <span wire:loading wire:target="openRatingConfirm" class="inline-flex items-center gap-1">
                                                <svg class="size-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Loading...
                                            </span>
                                        </button>
                                    </div>
                                    @error('komentarRating') <p class="text-[10px] text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            {{-- 3. RATED CONFIRMATION --}}
                            @if($tiket->penilaian && $tiket->statusTiket?->nama_status === 'Close' && ! $tampilRating)
                                <div class="bg-dongker-50 border border-dongker-100 rounded-xl px-4 py-3 flex items-center gap-3">
                                    <div class="shrink-0 size-9 rounded-lg bg-white border border-dongker-100 flex items-center justify-center">
                                        <x-ui.icon name="star" class="size-5 text-amber-400" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            @php
                                                $raterName = auth()->user()->isAdmin()
                                                    ? ($tiket->user?->karyawan?->nama ?? $tiket->user?->name ?? 'User')
                                                    : 'You';
                                                $ratedLabel = $raterName === 'You' ? 'You rated this ticket' : "{$raterName} rated this ticket";
                                            @endphp
                                            <p class="text-[11px] font-bold text-dongker-700">{{ $ratedLabel }}</p>
                                            <span class="text-base tracking-wide text-amber-400 leading-none">
                                                {{ str_repeat('★', $tiket->penilaian->nilai) }}{{ str_repeat('☆', 5 - $tiket->penilaian->nilai) }}
                                            </span>
                                            <span class="text-[11px] font-bold text-dongker-700">{{ $tiket->penilaian->nilai }}/5</span>
                                        </div>
                                        @if($tiket->penilaian->komentar)
                                            <p class="text-[10px] text-gray-500 italic truncate mt-0.5">"{{ $tiket->penilaian->komentar }}"</p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- 4. REPETITIVE OFF REQUEST (user side) — dongker theme --}}
                            @if(auth()->user()->isKaryawan() && $tiket->id_pengguna === auth()->id() && $tiket->repetitive_review_state === 'admin_requested_off')
                                <div class="bg-dongker-50 border border-dongker-100 rounded-xl overflow-hidden animate-fade-in">
                                    {{-- Header --}}
                                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-dongker-100/70 bg-dongker-50">
                                        <h4 class="text-sm font-bold text-dongker-700">Admin requested: Mark as NOT Repetitive</h4>
                                        <span class="shrink-0 text-[10px] text-dongker-500">{{ $tiket->repetitive_review_admin_at?->diffForHumans() }}</span>
                                    </div>
                                    {{-- Body: reason block --}}
                                    <div class="px-4 py-3">
                                        <p class="text-[10px] font-semibold text-dongker-500 uppercase tracking-wider mb-1">Admin's reason</p>
                                        <blockquote data-selectable-text class="border-l-2 border-dongker-300 bg-white/70 rounded-r px-3 py-2 text-xs text-gray-700 italic">
                                            "{{ $tiket->repetitive_review_admin_note }}"
                                        </blockquote>
                                    </div>
                                    {{-- Footer: actions --}}
                                    <div class="flex justify-end gap-2 px-4 py-2.5 border-t border-dongker-100/70 bg-white/50">
                                        <button wire:click="openRefuseRepetitiveModal"
                                                class="inline-flex items-center gap-1 border border-dongker-200 text-dongker-700 bg-white px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-dongker-50 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="openRefuseRepetitiveModal">
                                            <span wire:loading.remove wire:target="openRefuseRepetitiveModal">Refuse</span>
                                            <span wire:loading wire:target="openRefuseRepetitiveModal" class="inline-flex items-center gap-1">
                                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Loading...
                                            </span>
                                        </button>
                                        <button wire:click="acceptRepetitiveChange"
                                                class="inline-flex items-center gap-1 bg-dongker-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-dongker-500 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="acceptRepetitiveChange">
                                            <span wire:loading.remove wire:target="acceptRepetitiveChange">Accept Change</span>
                                            <span wire:loading wire:target="acceptRepetitiveChange" class="inline-flex items-center gap-1">
                                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Accepting...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- 5. REPETITIVE REFUSED (admin side) — dongker theme, structured layout --}}
                            @if(auth()->user()->isAdmin() && $tiket->id_penanggung_jawab === auth()->id() && $tiket->repetitive_review_state === 'user_refused')
                                <div class="bg-dongker-50 border border-dongker-100 rounded-xl overflow-hidden animate-fade-in">
                                    {{-- Header --}}
                                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-dongker-100/70 bg-dongker-50">
                                        <h4 class="text-sm font-bold text-dongker-700">User refused your request</h4>
                                        <span class="shrink-0 text-[10px] text-dongker-500">{{ $tiket->repetitive_review_user_at?->diffForHumans() }}</span>
                                    </div>
                                    {{-- Body: reason block --}}
                                    <div class="px-4 py-3">
                                        <p class="text-[10px] font-semibold text-dongker-500 uppercase tracking-wider mb-1">User's reason</p>
                                        <blockquote data-selectable-text class="border-l-2 border-dongker-300 bg-white/70 rounded-r px-3 py-2 text-xs text-gray-700 italic">
                                            "{{ $tiket->repetitive_review_user_note }}"
                                        </blockquote>
                                    </div>
                                    {{-- Footer: actions (Decline = re-toggle OFF cycle baru, Confirm = admin menyerah) --}}
                                    <div class="flex justify-end gap-2 px-4 py-2.5 border-t border-dongker-100/70 bg-white/50">
                                        {{-- Decline: shortcut buka admin note modal untuk cycle ulang --}}
                                        <button type="button" wire:click="declineUserRefusal"
                                                class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-red-500 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="declineUserRefusal">
                                            <span wire:loading.remove wire:target="declineUserRefusal">Decline</span>
                                            <span wire:loading wire:target="declineUserRefusal" class="inline-flex items-center gap-1.5">
                                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Declining...
                                            </span>
                                        </button>
                                        {{-- Confirm: admin menerima alasan user, state ke none, berulang tetap true --}}
                                        <button type="button" wire:click="confirmAcceptRefusal"
                                                class="inline-flex items-center gap-1.5 rounded-md bg-dongker-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-dongker-500 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="confirmAcceptRefusal">
                                            <span wire:loading.remove wire:target="confirmAcceptRefusal">Confirm</span>
                                            <span wire:loading wire:target="confirmAcceptRefusal" class="inline-flex items-center gap-1.5">
                                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Confirming...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                        {{-- 6. SLA PAUSE BANNER (requester side — pending state) --}}
                        @if($activePause && $activePause->state === 'pending' && $isPauseRequester)
                            <div class="bg-dongker-50 border border-dongker-100 rounded-xl overflow-hidden animate-fade-in">
                                {{-- Header --}}
                                <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-dongker-100/70 bg-dongker-50">
                                    <h4 class="text-sm font-bold text-dongker-700">Deadline Pause Requested by Admin</h4>
                                    <span class="shrink-0 text-[10px] text-dongker-500">{{ $activePause->created_at->diffForHumans() }}</span>
                                </div>
                                {{-- Body: reason + ETA --}}
                                <div class="px-4 py-3 space-y-2">
                                    <p class="text-[10px] font-semibold text-dongker-500 uppercase tracking-wider mb-1">Reason</p>
                                    <blockquote data-selectable-text class="border-l-2 border-dongker-300 bg-white/70 rounded-r px-3 py-2 text-xs text-gray-700 italic">
                                        "{{ $activePause->reason }}"
                                    </blockquote>
                                    <p class="text-[11px] text-gray-600">
                                        <span class="font-semibold text-dongker-700">Pause until:</span>
                                        {{ $activePause->eta->format('d M Y, H:i') }}
                                    </p>
                                    @if($activePause->attachment_path)
                                        @php
                                            $attUrl = \Illuminate\Support\Facades\Storage::url($activePause->attachment_path);
                                            $attIsImage = \Illuminate\Support\Str::endsWith(strtolower($activePause->attachment_path), ['.jpg', '.jpeg', '.png', '.webp', '.gif']);
                                        @endphp
                                        <div x-data="{ show: false }" class="pt-1">
                                            <button type="button" x-on:click="show = ! show"
                                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-dongker-600 transition hover:text-dongker-800">
                                                <x-ui.icon name="paper-clip" class="size-3" />
                                                <span x-text="show ? 'Hide attachment' : 'View attachment'"></span>
                                            </button>
                                            <div x-show="show" x-cloak x-transition class="mt-2 overflow-hidden rounded-lg border border-gray-200 bg-white">
                                                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/80 px-3 py-1.5">
                                                    <span class="text-[10px] font-medium text-gray-400">{{ $attIsImage ? 'Image' : 'File' }}</span>
                                                    <a href="{{ $attUrl }}" download
                                                       class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 transition hover:bg-gray-100">
                                                        <x-ui.icon name="arrow-down-tray" class="size-3" /> Save
                                                    </a>
                                                </div>
                                                <div class="flex items-center justify-center bg-gray-50" style="height:220px;">
                                                    @if($attIsImage)
                                                        <img src="{{ $attUrl }}" alt="Pause attachment" class="block h-full w-full object-contain" />
                                                    @else
                                                        <a href="{{ $attUrl }}" target="_blank"
                                                           class="inline-flex items-center gap-2 rounded-lg bg-dongker-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-dongker-500">
                                                            <x-ui.icon name="arrow-top-right-on-square" class="size-4 text-white" /> Open file
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                {{-- Footer: Approve + Reject --}}
                                <div class="px-4 py-2.5 border-t border-dongker-100/70 bg-white/50 space-y-2">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="approveSlaPause({{ $activePause->id }})"
                                                class="inline-flex items-center gap-1 bg-dongker-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-dongker-500 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="approveSlaPause">
                                            <span wire:loading.remove wire:target="approveSlaPause">
                                                Approve
                                            </span>
                                            <span wire:loading wire:target="approveSlaPause" class="inline-flex items-center gap-1">
                                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Approving...
                                            </span>
                                        </button>
                                        <button type="button" wire:click="openRejectPauseConfirm"
                                                class="inline-flex items-center gap-1 bg-red-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-red-500 transition">
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                            {{-- Reject confirmation popup: reason is required; it is posted to the chat. --}}
                            <div
                                x-data="{ open: @entangle('showRejectPauseConfirm'), note: @entangle('pauseRejectNote') }"
                                x-effect="open ? $dispatch('open-modal', { id: 'reject-pause-modal' }) : $dispatch('close-modal', { id: 'reject-pause-modal' })"
                                x-on:modal-closed.window="if ($event.detail?.id === 'reject-pause-modal') { open = false }"
                            >
                                <x-ui.modal id="reject-pause-modal" backdrop="blur" width="md" heading="Reject deadline pause request?">
                                    <p class="text-sm text-gray-600">The deadline countdown keeps running. A reason is required so the admin knows why, and it will be posted to the ticket chat.</p>
                                    <textarea x-model="note" rows="2" maxlength="1000"
                                              placeholder="Reason for rejecting (required)..."
                                              class="mt-3 w-full resize-none rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-sm text-gray-700 focus:border-dongker-400 focus:ring-1 focus:ring-dongker-400"></textarea>
                                    @error('pauseRejectNote') <p class="mt-1 text-[11px] text-red-500">{{ $message }}</p> @enderror
                                    <div class="mt-4 flex justify-end gap-2">
                                        <button type="button" wire:click="$set('showRejectPauseConfirm', false)"
                                                class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">Cancel</button>
                                        <button type="button" wire:click="rejectSlaPause({{ $activePause->id }})"
                                                x-bind:disabled="! note || ! note.trim()"
                                                class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50">
                                            <span wire:loading.remove wire:target="rejectSlaPause" class="inline-flex items-center gap-1.5">
                                                Reject request
                                            </span>
                                            <span wire:loading wire:target="rejectSlaPause">Rejecting...</span>
                                        </button>
                                    </div>
                                </x-ui.modal>
                            </div>
                        @endif

                        {{-- 7. SLA PAUSE BANNER (PIC admin side) --}}
                        @if($isPausePic)
                            @if($activePause && $activePause->state === 'pending')
                                {{-- Admin: pending — waiting for requester --}}
                                <div class="bg-dongker-50 border border-dongker-100 rounded-xl overflow-hidden animate-fade-in">
                                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-dongker-100/70 bg-dongker-50">
                                        <h4 class="text-sm font-bold text-dongker-700">Pause Requested, Awaiting Requester Approval</h4>
                                        <span class="shrink-0 text-[10px] text-dongker-500">{{ $activePause->created_at->diffForHumans() }}</span>
                                    </div>
                                    <div class="px-4 py-3">
                                        <p class="text-[11px] text-gray-600">Pause until <span class="font-semibold text-dongker-700">{{ $activePause->eta->format('d M Y, H:i') }}</span></p>
                                    </div>
                                    <div class="flex justify-end px-4 py-2.5 border-t border-dongker-100/70 bg-white/50">
                                        <button wire:click="cancelSlaPause({{ $activePause->id }})"
                                                class="inline-flex items-center gap-1 border border-dongker-200 text-dongker-700 bg-white px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-dongker-50 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="cancelSlaPause">
                                            <span wire:loading.remove wire:target="cancelSlaPause">Cancel Pause</span>
                                            <span wire:loading wire:target="cancelSlaPause" class="inline-flex items-center gap-1">
                                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Cancelling...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @elseif($activePause && $activePause->state === 'active')
                                {{-- Admin: active pause — Resume or Extend --}}
                                <div class="bg-dongker-50 border border-dongker-100 rounded-xl overflow-hidden animate-fade-in">
                                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-dongker-100/70 bg-dongker-50">
                                        <h4 class="text-sm font-bold text-dongker-700">Deadline Paused</h4>
                                    </div>
                                    <div class="px-4 py-3 space-y-2">
                                        <p class="text-[11px] text-gray-600">Deadline frozen until <span class="font-semibold text-dongker-700">{{ $activePause->eta->format('d M Y, H:i') }}</span></p>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-end gap-2 px-4 py-2.5 border-t border-dongker-100/70 bg-white/50">
                                        <button wire:click="resumeSlaPause({{ $activePause->id }})"
                                                class="inline-flex items-center gap-1 bg-dongker-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-dongker-500 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="resumeSlaPause">
                                            <span wire:loading.remove wire:target="resumeSlaPause">Resume deadline</span>
                                            <span wire:loading wire:target="resumeSlaPause" class="inline-flex items-center gap-1">
                                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Resuming...
                                            </span>
                                        </button>
                                        <x-ui.datetime-picker model="pauseExtendEta" :value="$pauseExtendEta"
                                                              pickAction="extendSlaPause" :pickArgs="[$activePause->id]" align="end">
                                            <x-slot:trigger>
                                                <button type="button"
                                                        class="inline-flex items-center gap-1 border border-dongker-200 text-dongker-700 bg-white px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-dongker-50 transition disabled:opacity-75 disabled:cursor-wait"
                                                        wire:loading.attr="disabled" wire:target="extendSlaPause">
                                                    <span wire:loading.remove wire:target="extendSlaPause" class="inline-flex items-center gap-1">
                                                        <x-ui.icon name="calendar" class="size-3.5 text-dongker-400" />
                                                        Extend
                                                    </span>
                                                    <span wire:loading wire:target="extendSlaPause" class="inline-flex items-center gap-1">
                                                        <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        Extending...
                                                    </span>
                                                </button>
                                            </x-slot:trigger>
                                        </x-ui.datetime-picker>
                                        @error('pauseExtendEta') <p class="text-[10px] text-red-500 w-full text-right">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @elseif(! $activePause && $tiket->statusTiket?->nama_status === 'In Progress')
                                {{-- Admin: no active/pending pause + In Progress — show Request SLA Pause button/form --}}
                                <div class="bg-dongker-50 border border-dongker-100 rounded-xl overflow-hidden animate-fade-in">
                                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-dongker-100/70 bg-dongker-50">
                                        <h4 class="text-sm font-bold text-dongker-700">Request Deadline Pause</h4>
                                    </div>
                                    <div class="px-4 py-3 space-y-2">
                                        <textarea wire:model.live="pauseReason" rows="2" maxlength="1000"
                                                  placeholder="Reason for deadline pause (e.g. waiting for hardware delivery)..."
                                                  class="w-full rounded-md border border-dongker-200 bg-white px-2.5 py-1.5 text-[11px] text-gray-700 focus:ring-1 focus:ring-dongker-400 focus:border-dongker-400 resize-none"></textarea>
                                        @error('pauseReason') <p class="text-[10px] text-red-500">{{ $message }}</p> @enderror
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="flex-1 min-w-[200px]">
                                                <x-ui.datetime-picker model="pauseEta" :value="$pauseEta" placeholder="Pick expected resume date and time" />
                                            </div>
                                            @error('pauseEta') <p class="text-[10px] text-red-500 w-full">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Attachment (optional)</label>
                                            <input type="file" wire:model="pauseAttachment"
                                                   class="block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200" />
                                            @error('pauseAttachment') <span class="mt-1 block text-[11px] text-red-600">{{ $message }}</span> @enderror
                                            <div wire:loading wire:target="pauseAttachment" class="mt-1 text-[11px] text-gray-400">Uploading...</div>
                                        </div>
                                    </div>
                                    <div class="flex justify-end px-4 py-2.5 border-t border-dongker-100/70 bg-white/50">
                                        <button wire:click="requestSlaPause"
                                                class="inline-flex items-center gap-1 bg-dongker-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-dongker-500 transition disabled:opacity-75 disabled:cursor-wait"
                                                wire:loading.attr="disabled" wire:target="requestSlaPause">
                                            <span wire:loading.remove wire:target="requestSlaPause">
                                                Request Pause
                                            </span>
                                            <span wire:loading wire:target="requestSlaPause" class="inline-flex items-center gap-1">
                                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Sending...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @endif

                        </div>
                    @endif

                    {{-- === META INFO + CONTACT DETAILS (moved above description) === --}}
                    <div class="px-5 pt-4">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <span class="inline-flex items-center gap-1.5 text-sm text-gray-700 font-medium">
                                <x-ui.icon name="map-pin" class="size-4 text-gray-400" />
                                {{ $tiket->lokasi?->nama_lokasi ?? '—' }}
                                @if($tiket->lokasi && ! $tiket->lokasi->is_active)
                                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-gray-100 text-gray-500">arsip</span>
                                @endif
                            </span>
                            @if($tiket->kategori)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-dongker-100 text-dongker-700 text-sm font-semibold">
                                    {{ $tiket->kategori->nama_kategori }}
                                    @unless($tiket->kategori->is_active)
                                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-gray-200 text-gray-500">arsip</span>
                                    @endunless
                                </span>
                            @endif
                            @if(auth()->user()->isAdmin())
                                <span class="inline-flex items-center gap-1.5 text-sm text-gray-700 font-medium">
                                    <x-ui.icon name="user" class="size-4 text-gray-400" />
                                    Requested by:
                                    <span class="font-semibold text-dongker-600 ml-0.5">{{ $tiket->user?->karyawan?->nama ?? $tiket->user?->name ?? '—' }}</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-sm text-gray-700 font-medium">
                                    <x-ui.icon name="user" class="size-4 text-gray-400" />
                                    Assigned:
                                    @if($tiket->assignedAdmin)
                                        <span class="font-semibold text-dongker-600 ml-0.5">{{ $tiket->assignedAdmin->karyawan?->nama ?? $tiket->assignedAdmin->name }}</span>
                                    @else
                                        <span class="font-semibold text-gray-400 italic ml-0.5">Unassigned</span>
                                    @endif
                                </span>
                            @endif
                            @if($tiket->berulang)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-orange-50 text-orange-600 text-sm font-semibold">
                                    <x-ui.icon name="arrow-path" class="size-4" />
                                    Repetitive
                                </span>
                            @endif
                        </div>

                        {{-- Requester contact details (admin only, inline within ticket card) --}}
                        @if(auth()->user()->isAdmin() && $tiket->user?->karyawan)
                            @php $karyawan = $tiket->user->karyawan; @endphp
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 rounded-xl bg-gray-50/60 border border-gray-100 px-4 py-3">
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <x-ui.icon name="briefcase" class="size-3.5 text-gray-400 shrink-0" />
                                    <span class="text-gray-400">Division:</span>
                                    <span class="font-medium text-gray-700 truncate">{{ $karyawan->divisi?->nama_divisi ?? '—' }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <x-ui.icon name="identification" class="size-3.5 text-gray-400 shrink-0" />
                                    <span class="text-gray-400">Position:</span>
                                    <span class="font-medium text-gray-700 truncate">{{ $karyawan->jabatan ?? '—' }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <x-ui.icon name="phone" class="size-3.5 text-gray-400 shrink-0" />
                                    <span class="text-gray-400">Phone:</span>
                                    <a href="tel:{{ $karyawan->no_telepon }}" class="font-medium text-dongker-600 hover:underline truncate">{{ $karyawan->no_telepon ?? '—' }}</a>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-600 min-w-0">
                                    <x-ui.icon name="envelope" class="size-3.5 text-gray-400 shrink-0" />
                                    <span class="text-gray-400 shrink-0">Email:</span>
                                    <a href="mailto:{{ $karyawan->email }}" class="font-medium text-dongker-600 hover:underline truncate">{{ $karyawan->email ?? '—' }}</a>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Post body (description + attachments only) --}}
                    <div class="p-5">
                        <h4 class="text-xs font-semibold text-dongker-500 uppercase tracking-wider mb-2">Description</h4>
                        <p data-selectable-text class="text-gray-700 text-sm leading-relaxed mb-4 break-words whitespace-pre-wrap caret-transparent cursor-default">{{ $tiket->deskripsi }}</p>

                        {{-- Legacy single image fallback --}}
                        @if($tiket->foto_bukti && $ticketLampiran->isEmpty())
                            <div class="mb-4">
                                <img src="{{ Storage::url($tiket->foto_bukti) }}" alt="Evidence" class="rounded-xl max-h-64 object-cover border border-gray-100" />
                            </div>
                        @endif

                        {{-- Attachment gallery (ticket-level only, no chat attachments) --}}
                        @if($ticketLampiran->isNotEmpty())
                            <x-ui.attachment-gallery :files="$ticketLampiran" :max-visible="3" />
                        @endif
                    </div>

                    {{-- Admin controls --}}
                    @if(auth()->user()->isAdmin())
                        <div class="border-t border-gray-100 p-4 bg-gray-50/50 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3">
                                @if(!$tiket->id_penanggung_jawab && $tiket->statusTiket?->nama_status === 'Open')
                                    <button wire:click="takeTicket"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-dongker-600 px-4 py-2 text-xs font-semibold text-white transition-all hover:bg-dongker-500 active:scale-95 disabled:opacity-75 disabled:cursor-wait"
                                            wire:loading.attr="disabled" wire:target="takeTicket">
                                        <span wire:loading.remove wire:target="takeTicket">Take Ticket</span>
                                        <span wire:loading wire:target="takeTicket" class="inline-flex items-center gap-1.5">
                                            <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Taking...
                                        </span>
                                    </button>
                                @endif

                                @if($tiket->id_penanggung_jawab === auth()->id() && $tiket->statusTiket?->nama_status === 'In Progress' && ! $tiket->siap_konfirmasi)
                                    @php
                                        // Both open review states block Mark as Resolved, but they wait on
                                        // different people. Saying "awaiting user" while the ball is in the
                                        // admin's court reads as a dead end.
                                        $resolveBlocked = $tiket->repetitive_review_state !== 'none';
                                        $resolveBlockedHint = match ($tiket->repetitive_review_state) {
                                            'admin_requested_off' => 'Waiting for the requester to answer your repetitive request',
                                            'user_refused' => 'Answer the refusal above to continue',
                                            default => null,
                                        };
                                        $resolveBlockedTitle = $tiket->repetitive_review_state === 'user_refused'
                                            ? 'Confirm or decline the refusal first'
                                            : 'Finalize repetitive status first';
                                    @endphp
                                    <button wire:click="openResolveConfirm"
                                            @class([
                                                'inline-flex items-center gap-1.5 rounded-lg bg-green-500 px-4 py-2 text-xs font-semibold text-white transition-all active:scale-95',
                                                'hover:bg-green-600' => ! $resolveBlocked,
                                                'opacity-50 cursor-not-allowed' => $resolveBlocked,
                                                'disabled:opacity-75 disabled:cursor-wait' => ! $resolveBlocked,
                                            ])
                                            @disabled($resolveBlocked)
                                            title="{{ $resolveBlocked ? $resolveBlockedTitle : '' }}"
                                            wire:loading.attr="disabled" wire:target="openResolveConfirm">
                                        <span wire:loading.remove wire:target="openResolveConfirm">Mark as Resolved</span>
                                        <span wire:loading wire:target="openResolveConfirm" class="inline-flex items-center gap-1.5">
                                            <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Loading...
                                        </span>
                                    </button>
                                    @if($resolveBlocked && $resolveBlockedHint)
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 border border-amber-200 px-2.5 py-1 text-[10px] font-medium text-amber-700">
                                            <x-ui.icon name="information-circle" class="size-3.5 text-amber-600" />
                                            {{ $resolveBlockedHint }}
                                        </span>
                                    @endif
                                @elseif($tiket->id_penanggung_jawab === auth()->id() && $tiket->statusTiket?->nama_status === 'In Progress' && $tiket->siap_konfirmasi)
                                    {{-- Resolve already requested: the Mark as Resolved button is replaced in
                                         its exact slot by a status hint so the admin knows the ticket is now
                                         waiting on the requester to confirm. --}}
                                    <span class="inline-flex items-center rounded-lg bg-dongker-50 border border-dongker-100 px-4 py-2 text-xs font-semibold text-dongker-700">
                                        Waiting for the requester to confirm resolution
                                    </span>
                                @endif
                            </div>

                            {{-- Repetitive validation toggle (admin owner only) --}}
                            @if($tiket->id_penanggung_jawab === auth()->id())
                                @php
                                    $repetitiveLocked = $tiket->siap_konfirmasi
                                        || $tiket->statusTiket?->nama_status === 'Close';
                                    $repetitiveLockReason = match (true) {
                                        $tiket->statusTiket?->nama_status === 'Close' => 'Frozen — ticket is closed',
                                        (bool) $tiket->siap_konfirmasi => 'Locked while awaiting user feedback',
                                        default => 'Validate whether this ticket is genuinely repetitive',
                                    };
                                @endphp
                                <label @class([
                                            'inline-flex items-center gap-2.5 select-none group',
                                            'cursor-pointer' => ! $repetitiveLocked,
                                            'cursor-not-allowed opacity-50' => $repetitiveLocked,
                                       ])
                                       wire:loading.class="opacity-60 cursor-wait"
                                       wire:target="toggleRepetitive"
                                       title="{{ $repetitiveLockReason }}">
                                    <span class="text-[11px] font-semibold text-gray-600 uppercase tracking-wider">
                                        Repetitive
                                    </span>
                                    <button type="button"
                                            wire:click="toggleRepetitive"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleRepetitive"
                                            @disabled($repetitiveLocked)
                                            @class([
                                                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200',
                                                'cursor-pointer' => ! $repetitiveLocked,
                                                'cursor-not-allowed' => $repetitiveLocked,
                                                'bg-orange-500' => $tiket->berulang,
                                                'bg-gray-300 group-hover:bg-gray-400' => ! $tiket->berulang && ! $repetitiveLocked,
                                                'bg-gray-300' => ! $tiket->berulang && $repetitiveLocked,
                                            ])
                                            role="switch"
                                            :aria-checked="{{ $tiket->berulang ? 'true' : 'false' }}">
                                        <span @class([
                                            'inline-block h-5 w-5 transform rounded-full bg-white shadow-md transition-transform duration-200',
                                            'translate-x-5' => $tiket->berulang,
                                            'translate-x-0.5' => ! $tiket->berulang,
                                        ])></span>
                                    </button>
                                    <span @class([
                                        'text-[11px] font-bold transition-colors',
                                        'text-orange-600' => $tiket->berulang,
                                        'text-gray-400' => ! $tiket->berulang,
                                    ])>
                                        {{ $tiket->berulang ? 'YES' : 'NO' }}
                                    </span>
                                </label>
                            @endif
                        </div>
                    @endif
                </div>
            </div> {{-- /Left col (ticket+feedback+rating) --}}

            {{-- ===== TR: Ticket History (col 3, row 1) — height MIRROR ticket card via Alpine ResizeObserver
                 lg:min-h-0 + lg:overflow-hidden → cell tidak grow karena history content (prevent infinite expand) ===== --}}
            <div class="lg:col-start-3 lg:row-start-1 flex flex-col lg:min-h-0 lg:overflow-hidden">
                {{-- Ticket History — height = ticket card height, list scroll internal --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-5 flex flex-col overflow-hidden"
                     x-bind:style="`height: ${Math.max(mirrorHeight, 320)}px; max-height: ${Math.max(mirrorHeight, 320)}px;`">
                    <div class="flex items-center justify-between mb-4 shrink-0">
                        <h3 class="text-sm font-semibold text-dongker-700">Ticket History</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-xs font-semibold text-gray-500">{{ $activityLogs->count() }}</span>
                    </div>
                    <div class="relative pl-8 flex-1 min-h-0 overflow-y-auto overflow-x-hidden">
                        <div class="absolute left-3 top-0 bottom-0 w-px bg-gray-200"></div>
                        <div class="space-y-4">
                            @foreach($activityLogs as $idx => $log)
                                @php
                                    $actorName = $log->user?->karyawan?->nama ?? $log->user?->name ?? 'System';
                                    $isNewest = $idx === 0;
                                    $icon = match ($log->aksi) {
                                        'ticket_created'                       => 'plus-circle',
                                        'ticket_assigned'                      => 'user-plus',
                                        'status_changed'                       => 'arrow-path',
                                        'comment_added', 'reply_posted'        => 'chat-bubble-left-right',
                                        'feedback_solved'                      => 'check-circle',
                                        'feedback_not_resolved'                => 'x-circle',
                                        'rating_submitted', 'ticket_rated'     => 'star',
                                        'repetitive_off_requested'             => 'arrow-path',
                                        'repetitive_off_accepted'              => 'check-circle',
                                        'repetitive_off_refused'               => 'x-circle',
                                        'repetitive_off_cancelled'             => 'check-circle',
                                        'repetitive_off_auto_accepted'         => 'clock',
                                        'sla_pause_requested'                  => 'pause',
                                        'sla_pause_approved'                   => 'check-circle',
                                        'sla_pause_auto_approved'              => 'clock',
                                        'sla_pause_rejected'                   => 'x-circle',
                                        'sla_pause_resumed'                    => 'play',
                                        'sla_pause_extended'                   => 'pause',
                                        'comment_digest_sent'                  => 'envelope',
                                        default                                => 'information-circle',
                                    };
                                    // comment_digest_sent stores a technical marker ("recipient=...") for
                                    // anti-spam dedup; turn it into a human-readable sentence for display.
                                    $commentDigestLabel = (function () use ($log) {
                                        $who = str_contains((string) $log->keterangan, 'recipient=admin') ? 'admin' : 'requester';
                                        preg_match('/digest of (\d+)/', (string) $log->keterangan, $m);
                                        $n = (int) ($m[1] ?? 0);
                                        return "Emailed {$who} about {$n} new ".($n === 1 ? 'comment' : 'comments');
                                    })();
                                    // status_changed has multiple sub-cases distinguished by status_baru
                                    $statusChangedLabel = match (true) {
                                        $log->status_baru === 'Close'
                                            => 'Ticket resolved and closed by system',
                                        $log->status_baru === 'In Progress (Awaiting Confirmation)'
                                            => "{$actorName} solved the issue, waiting for confirmation",
                                        default
                                            => $log->keterangan ?? "Status changed by {$actorName}",
                                    };

                                    $label = match ($log->aksi) {
                                        'ticket_created'                   => "Ticket created by {$actorName}",
                                        'ticket_assigned'                  => "Ticket assigned to {$actorName}",
                                        'status_changed'                   => $statusChangedLabel,
                                        'comment_added', 'reply_posted'    => "{$actorName} added a comment",
                                        'feedback_solved'                  => "{$actorName} confirmed resolution",
                                        'feedback_not_resolved'            => "{$actorName} reported issue still unresolved",
                                        'rating_submitted', 'ticket_rated' => $log->keterangan ?? "{$actorName} submitted rating",
                                        'repetitive_off_requested'         => "{$actorName} requested NOT repetitive",
                                        'repetitive_off_accepted'          => "{$actorName} accepted admin's request",
                                        'repetitive_off_refused'           => "{$actorName} refused admin's request",
                                        'repetitive_off_cancelled'         => "{$actorName} accepted user's refusal, ticket remains repetitive",
                                        'repetitive_off_auto_accepted'     => 'System automatically accepted the change request',
                                        'auto_resolved'                    => 'Auto-closed by system',
                                        'sla_pause_requested'              => 'Deadline Pause Requested',
                                        'sla_pause_approved'               => 'Deadline Pause Approved',
                                        'sla_pause_auto_approved'          => 'Deadline Pause Auto-Approved',
                                        'sla_pause_rejected'               => 'Deadline Pause Rejected',
                                        'sla_pause_resumed'                => 'Deadline Resumed',
                                        'sla_pause_extended'               => 'Deadline Pause Extended',
                                        'comment_digest_sent'              => $commentDigestLabel,
                                        default                            => $log->keterangan ?? $log->aksi,
                                    };
                                @endphp
                                <div class="relative">
                                    <div class="absolute -left-8 top-0 flex h-6 w-6 items-center justify-center rounded-full bg-dongker-50 text-dongker-500">
                                        <x-ui.icon name="{{ $icon }}" class="size-3" />
                                    </div>
                                    <div @class([
                                        '-ml-2 -mr-1 px-2 py-1.5 rounded-md',
                                        'bg-dongker-50/60' => $isNewest,
                                    ])>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-semibold text-gray-700">{{ $label }}</span>
                                            @if($isNewest)
                                                <span class="inline-flex items-center rounded bg-dongker-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-dongker-700">
                                                    Newest
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-0.5">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->created_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>

        {{-- ===== BL: Chat (col-span-2, row 2) ===== --}}
        @if($hasChat)
            <div class="lg:col-span-2 lg:row-start-2 lg:col-start-1 w-full bg-white border border-gray-200 rounded-2xl overflow-hidden flex flex-col min-h-[400px]">
            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3 shrink-0">
                <div class="size-10 rounded-xl bg-dongker-100 flex items-center justify-center">
                    <x-ui.icon name="chat-bubble-left-right" class="size-5 text-dongker-600" />
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-dongker-700 text-base">Comments</h3>
                    <p class="text-xs text-gray-500">{{ $tiket->komentar->count() }} {{ $tiket->komentar->count() === 1 ? 'message' : 'messages' }} in this conversation</p>
                </div>
                @if($tiket->statusTiket?->nama_status === 'Close')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-[11px] font-semibold text-gray-500">
                        <x-ui.icon name="lock-closed" class="size-3" />
                        Read-only
                    </span>
                @endif
            </div>

            {{-- Comment list (scroll internal) --}}
            <div class="divide-y divide-gray-50 flex-1 min-h-0 overflow-y-auto"
                 x-data="{
                     lastCount: {{ $tiket->komentar->count() }},
                     init() {
                         this.$el.scrollTop = this.$el.scrollHeight;
                     }
                 }"
                 x-effect="
                     const c = {{ $tiket->komentar->count() }};
                     if (c !== lastCount) {
                         lastCount = c;
                         $nextTick(() => $el.scrollTop = $el.scrollHeight);
                     }
                 ">
                @forelse($tiket->komentar as $reply)
                    @php
                        $replyUser = $reply->user;
                        $isAdminReply = $replyUser?->isAdmin();
                        $isIssueReason = str_starts_with($reply->isi_komentar, '[Issue Reason]');
                        $replyLampiran = $reply->lampiran ?? collect();
                    @endphp
                    <div class="px-6 py-5 {{ $isAdminReply ? 'bg-dongker-50/30' : '' }} {{ $isIssueReason ? 'bg-red-50/40' : '' }}">
                        <div class="flex items-start gap-4">
                            <div @class([
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white',
                                'bg-gradient-to-br from-dongker-400 to-dongker-600' => $isAdminReply,
                                'bg-gradient-to-br from-gray-400 to-gray-600' => ! $isAdminReply,
                            ])>
                                {{ strtoupper(substr($replyUser?->karyawan?->nama ?? $replyUser?->name ?? '?', 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="text-sm font-bold text-dongker-700">{{ $replyUser?->name }}</span>
                                    @if($isAdminReply)
                                        <span class="text-[10px] font-bold text-dongker-600 bg-dongker-100 px-2 py-0.5 rounded-full">Admin</span>
                                    @endif
                                    @if($isIssueReason)
                                        <span class="text-[10px] font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded-full">Issue Reason</span>
                                    @endif
                                    <span class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</span>
                                </div>
                                <p data-selectable-text class="text-[15px] text-gray-800 break-words whitespace-pre-wrap leading-relaxed">{{ $reply->isi_komentar }}</p>
                                @if($replyLampiran->isNotEmpty())
                                    <x-ui.attachment-gallery :files="$replyLampiran" :max-visible="3" :chat-mode="true" />
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex-1 flex flex-col items-center justify-center py-16 px-6">
                        <div class="size-16 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                            <x-ui.icon name="chat-bubble-left-right" class="size-8 text-gray-400" />
                        </div>
                        <p class="text-base font-semibold text-gray-500">No comments yet</p>
                        <p class="text-sm text-gray-400 mt-1">Start the conversation by writing a comment below</p>
                    </div>
                @endforelse
            </div>

            {{-- Reply box (full width, larger) — only for assigned admin or ticket requester --}}
            @php
                $canChat = ($tiket->statusTiket?->nama_status !== 'Close') && (
                    (auth()->user()->isKaryawan() && $tiket->id_pengguna === auth()->id())
                    || (auth()->user()->isAdmin() && $tiket->id_penanggung_jawab === auth()->id())
                );
            @endphp
            @if(! $canChat && auth()->user()->isAdmin() && $tiket->id_penanggung_jawab && $tiket->id_penanggung_jawab !== auth()->id() && $tiket->statusTiket?->nama_status !== 'Close')
                <div class="border-t border-gray-100 px-6 py-3 bg-amber-50/40 shrink-0">
                    <p class="text-xs text-amber-700 text-center">
                        <x-ui.icon name="lock-closed" class="inline size-3.5 mb-0.5" />
                        Read-only — only the assigned PIC ({{ $tiket->assignedAdmin?->karyawan?->nama ?? 'admin' }}) can reply.
                    </p>
                </div>
            @endif

            @unless(auth()->user()->isManager())
            @if($canChat)
                <div class="border-t border-gray-100 px-6 py-4 bg-gray-50/30 shrink-0">
                    <form wire:submit="kirimKomentar" class="space-y-2">
                        <div class="flex gap-2.5">
                            <input type="text"
                                   wire:model="isiKomentar"
                                   maxlength="100"
                                   placeholder="Write a comment..."
                                   class="flex-1 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm focus:ring-2 focus:ring-dongker-400 focus:border-dongker-400" />
                            <label class="cursor-pointer inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-3 hover:bg-gray-50 transition" title="Attach files">
                                <x-ui.icon name="paper-clip" class="size-5 text-gray-500" />
                                <input type="file" wire:model="komentarLampiran" multiple
                                       accept="image/jpeg,image/png,image/webp,application/pdf"
                                       class="hidden" />
                            </label>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-dongker-600 px-5 py-3 text-sm font-semibold text-white hover:bg-dongker-500 active:scale-95 transition-all"
                                    wire:loading.attr="disabled">
                                <x-ui.icon name="paper-airplane" class="size-4 text-white" />
                                Send
                            </button>
                        </div>

                        @if(!empty($komentarLampiran))
                            <div class="flex flex-wrap gap-2 pt-1">
                                @foreach($komentarLampiran as $idx => $file)
                                    <div class="relative h-16 w-16 rounded-lg border border-gray-200 overflow-hidden group">
                                        @if(str_starts_with($file->getMimeType(), 'image/'))
                                            <img src="{{ $file->temporaryUrl() }}" class="h-full w-full object-cover" alt="preview" />
                                        @else
                                            <div class="flex flex-col items-center justify-center h-full bg-gray-50">
                                                <x-ui.icon name="document-text" class="size-5 text-red-500" />
                                            </div>
                                        @endif
                                        <button type="button" wire:click="removeKomentarLampiran({{ $idx }})"
                                                class="absolute top-0.5 right-0.5 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition">
                                            <x-ui.icon name="x-mark" class="size-3" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('isiKomentar') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        @error('komentarLampiran') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        @error('komentarLampiran.*') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    </form>
                </div>
            @endif
            @endunless
            </div>
        @endif

        {{-- ===== BR: Repetitive Group banner (col 3, row 2) — Compact white + dongker accent ===== --}}
        @if($tiket->grupTiket)
            <div class="lg:col-start-3 lg:row-start-2 lg:self-start bg-white border border-dongker-100 rounded-xl px-3 py-2 flex items-center justify-between gap-2 shadow-sm"
                 style="height: 56px;">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="size-7 shrink-0 rounded-lg bg-dongker-50 flex items-center justify-center">
                        <x-ui.icon name="arrow-path" class="size-4 text-dongker-600" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold text-dongker-700 leading-tight truncate">Repeated Issue</p>
                        <p class="text-[10px] text-gray-400 leading-tight">{{ $totalInGroup }} ticket{{ $totalInGroup === 1 ? '' : 's' }}</p>
                    </div>
                </div>
                <button type="button" wire:click="$set('showGrupModal', true)"
                        class="shrink-0 inline-flex items-center gap-1 rounded-md border border-dongker-200 bg-dongker-50 px-2.5 py-1.5 text-[10px] font-bold text-dongker-700 hover:bg-dongker-100 transition">
                    View
                    <x-ui.icon name="arrow-right" class="size-3" />
                </button>
            </div>
        @endif

    </div> {{-- /Main 2x2 Grid --}}

    {{-- === RESOLVE CONFIRM MODAL === --}}
    <div
        x-data="{ open: @entangle('showResolveConfirm') }"
        x-effect="open ? $dispatch('open-modal', { id: 'resolve-confirm-modal' }) : $dispatch('close-modal', { id: 'resolve-confirm-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'resolve-confirm-modal') { open = false }"
    >
        <x-ui.modal id="resolve-confirm-modal" backdrop="blur" width="md"
                    heading="Mark as Resolved">
            <p class="text-sm text-gray-600">Mark this ticket as resolved? The employee will be asked to verify whether the issue is fully fixed.</p>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" wire:click="$set('showResolveConfirm', false)"
                        class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
                    Cancel
                </button>
                <button type="button" wire:click="confirmResolve"
                        class="inline-flex items-center rounded-xl bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-500 transition disabled:opacity-75 disabled:cursor-wait"
                        wire:loading.attr="disabled" wire:target="confirmResolve">
                    <span wire:loading.remove wire:target="confirmResolve">Confirm</span>
                    <span wire:loading wire:target="confirmResolve" class="inline-flex items-center gap-1.5">
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Marking...
                    </span>
                </button>
            </div>
        </x-ui.modal>
    </div>

    {{-- === RATING CONFIRM MODAL === --}}
    <div
        x-data="{ open: @entangle('showRatingConfirm') }"
        x-effect="open ? $dispatch('open-modal', { id: 'rating-confirm-modal' }) : $dispatch('close-modal', { id: 'rating-confirm-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'rating-confirm-modal') { open = false }"
    >
        <x-ui.modal id="rating-confirm-modal" backdrop="blur" width="md"
                    heading="Submit Rating">
            <p class="text-sm text-gray-600">Submit your {{ $bintang }}-star rating? This cannot be changed after submission.</p>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" wire:click="$set('showRatingConfirm', false)"
                        class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
                    Cancel
                </button>
                <button type="button" wire:click="confirmRating"
                        class="inline-flex items-center rounded-xl bg-dongker-600 px-5 py-2 text-sm font-semibold text-white hover:bg-dongker-500 transition disabled:opacity-75 disabled:cursor-wait"
                        wire:loading.attr="disabled" wire:target="confirmRating">
                    <span wire:loading.remove wire:target="confirmRating">Confirm</span>
                    <span wire:loading wire:target="confirmRating" class="inline-flex items-center gap-1.5">
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submitting...
                    </span>
                </button>
            </div>
        </x-ui.modal>
    </div>

    {{-- === FEEDBACK CONFIRM MODAL === --}}
    <div
        x-data="{ open: @entangle('showFeedbackConfirm') }"
        x-effect="open ? $dispatch('open-modal', { id: 'feedback-confirm-modal' }) : $dispatch('close-modal', { id: 'feedback-confirm-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'feedback-confirm-modal') { open = false }"
    >
        <x-ui.modal id="feedback-confirm-modal" backdrop="blur" width="md"
                    heading="Confirm your decision">
            <p class="text-sm text-gray-600">
                @if($feedbackResolvedDecision)
                    Are you sure the issue is fully resolved? This will close the ticket and you will be asked to rate the service.
                @else
                    You're about to report that the issue is still unresolved. The admin will be asked to take further action.
                @endif
            </p>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" wire:click="$set('showFeedbackConfirm', false)"
                        class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
                    Cancel
                </button>
                <button type="button" wire:click="confirmFeedback"
                        @class([
                            'inline-flex items-center rounded-xl px-5 py-2 text-sm font-semibold text-white transition disabled:opacity-75 disabled:cursor-wait',
                            'bg-green-600 hover:bg-green-500' => $feedbackResolvedDecision,
                            'bg-red-600 hover:bg-red-500' => ! $feedbackResolvedDecision,
                        ])
                        wire:loading.attr="disabled" wire:target="confirmFeedback">
                    <span wire:loading.remove wire:target="confirmFeedback">Confirm</span>
                    <span wire:loading wire:target="confirmFeedback" class="inline-flex items-center gap-1.5">
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submitting...
                    </span>
                </button>
            </div>
        </x-ui.modal>
    </div>

    {{-- === ISSUE REASON MODAL === --}}
    <div
        x-data="{ open: @entangle('showIssueReasonModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'issue-reason-modal' }) : $dispatch('close-modal', { id: 'issue-reason-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'issue-reason-modal') { open = false }"
    >
        <x-ui.modal id="issue-reason-modal" width="lg"
                    heading="Why is this issue not resolved?"
                    description="Please describe what's still wrong so the admin can address it.">
            <div class="space-y-3">
                <textarea wire:model.live="issueReason" rows="5" maxlength="100"
                          placeholder="Describe the issue in detail (e.g. 'Screen still flickers after restart')..."
                          class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400 focus:border-dongker-400"></textarea>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] text-gray-400">Min 10 chars · Max 100</p>
                    <p class="text-[11px] {{ mb_strlen($issueReason) >= 10 ? 'text-green-500 font-semibold' : 'text-gray-400' }}">
                        {{ mb_strlen($issueReason) }}
                    </p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 mt-4">
                <button type="button" wire:click="$set('showIssueReasonModal', false)"
                        class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
                    Cancel
                </button>
                <button type="button" wire:click="submitIssueReason"
                        @class([
                            'inline-flex items-center rounded-xl px-5 py-2 text-sm font-semibold text-white transition disabled:opacity-75 disabled:cursor-wait',
                            'bg-dongker-600 hover:bg-dongker-500' => mb_strlen($issueReason) >= 10,
                            'bg-gray-300 cursor-not-allowed' => mb_strlen($issueReason) < 10,
                        ])
                        @disabled(mb_strlen($issueReason) < 10)
                        wire:loading.attr="disabled" wire:target="submitIssueReason">
                    <span wire:loading.remove wire:target="submitIssueReason">Submit</span>
                    <span wire:loading wire:target="submitIssueReason" class="inline-flex items-center gap-1.5">
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submitting...
                    </span>
                </button>
            </div>
        </x-ui.modal>
    </div>

    {{-- === REPETITIVE OFF CONFIRM MODAL (admin double verification) === --}}
    <div
        x-data="{ open: @entangle('showRepetitiveOffConfirm') }"
        x-effect="open ? $dispatch('open-modal', { id: 'repetitive-off-confirm-modal' }) : $dispatch('close-modal', { id: 'repetitive-off-confirm-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'repetitive-off-confirm-modal') { open = false }"
    >
        <x-ui.modal id="repetitive-off-confirm-modal" backdrop="blur" width="lg"
                    heading="Mark as NOT Repetitive"
                    description="This will remove the ticket from the repetitive group. Please provide a reason for audit trail.">
            <div class="space-y-3">
                <textarea wire:model.live="repetitiveOffReason" rows="4" maxlength="200"
                          placeholder="Reason for marking this ticket as NOT repetitive (e.g. 'Different root cause from previous tickets', 'Misclassified by system')..."
                          class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400 focus:border-dongker-400"></textarea>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] text-gray-400">Min 10 chars · Max 200</p>
                    <p class="text-[11px] {{ mb_strlen($repetitiveOffReason) >= 10 ? 'text-green-500 font-semibold' : 'text-gray-400' }}">
                        {{ mb_strlen($repetitiveOffReason) }}
                    </p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 mt-4">
                <button type="button" wire:click="$set('showRepetitiveOffConfirm', false)"
                        class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
                    Cancel
                </button>
                <button type="button" wire:click="confirmRepetitiveOff"
                        @class([
                            'inline-flex items-center rounded-xl px-5 py-2 text-sm font-semibold text-white transition disabled:opacity-75 disabled:cursor-wait',
                            'bg-dongker-600 hover:bg-dongker-500' => mb_strlen($repetitiveOffReason) >= 10,
                            'bg-gray-300 cursor-not-allowed' => mb_strlen($repetitiveOffReason) < 10,
                        ])
                        @disabled(mb_strlen($repetitiveOffReason) < 10)
                        wire:loading.attr="disabled" wire:target="confirmRepetitiveOff">
                    <span wire:loading.remove wire:target="confirmRepetitiveOff">Submit</span>
                    <span wire:loading wire:target="confirmRepetitiveOff" class="inline-flex items-center gap-1.5">
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Sending...
                    </span>
                </button>
            </div>
        </x-ui.modal>
    </div>

    {{-- === REPETITIVE REFUSE MODAL (user submits refusal note) === --}}
    <div
        x-data="{ open: @entangle('showRefuseRepetitiveModal') }"
        x-effect="open ? $dispatch('open-modal', { id: 'repetitive-refuse-modal' }) : $dispatch('close-modal', { id: 'repetitive-refuse-modal' })"
        x-on:modal-closed.window="if ($event.detail?.id === 'repetitive-refuse-modal') { open = false }"
    >
        <x-ui.modal id="repetitive-refuse-modal" backdrop="blur" width="lg"
                    heading="Refuse Admin's Request"
                    description="Tell the admin why you believe this ticket is still repetitive.">
            <div class="space-y-3">
                <textarea wire:model.live="refuseRepetitiveReason" rows="4" maxlength="200"
                          placeholder="Your reason (e.g. 'This is the third time the same printer fails in the same way')..."
                          class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-dongker-400 focus:border-dongker-400"></textarea>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] text-gray-400">Min 10 chars · Max 200</p>
                    <p class="text-[11px] {{ mb_strlen($refuseRepetitiveReason) >= 10 ? 'text-green-500 font-semibold' : 'text-gray-400' }}">
                        {{ mb_strlen($refuseRepetitiveReason) }}
                    </p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 mt-4">
                <button type="button" wire:click="$set('showRefuseRepetitiveModal', false)"
                        class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
                    Cancel
                </button>
                <button type="button" wire:click="confirmRefuseRepetitive"
                        @class([
                            'inline-flex items-center rounded-xl px-5 py-2 text-sm font-semibold text-white transition disabled:opacity-75 disabled:cursor-wait',
                            'bg-dongker-600 hover:bg-dongker-500' => mb_strlen($refuseRepetitiveReason) >= 10,
                            'bg-gray-300 cursor-not-allowed' => mb_strlen($refuseRepetitiveReason) < 10,
                        ])
                        @disabled(mb_strlen($refuseRepetitiveReason) < 10)
                        wire:loading.attr="disabled" wire:target="confirmRefuseRepetitive">
                    <span wire:loading.remove wire:target="confirmRefuseRepetitive">Submit</span>
                    <span wire:loading wire:target="confirmRefuseRepetitive" class="inline-flex items-center gap-1.5">
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Sending...
                    </span>
                </button>
            </div>
        </x-ui.modal>
    </div>

    {{-- === REPETITIVE GROUP MODAL === --}}
    @if($tiket->grupTiket)
        <div
            x-data="{ open: @entangle('showGrupModal') }"
            x-effect="open ? $dispatch('open-modal', { id: 'grup-tiket-modal' }) : $dispatch('close-modal', { id: 'grup-tiket-modal' })"
            x-on:modal-closed.window="if ($event.detail?.id === 'grup-tiket-modal') { open = false }"
        >
            <x-ui.modal id="grup-tiket-modal" width="4xl"
                        :heading="'Repetitive Group — ' . ($tiket->kategori?->nama_kategori ?? '—') . ' at ' . ($tiket->lokasi?->nama_lokasi ?? '—')"
                        :description="($tiket->grupTiket->jumlah ?? 0) . ' tickets reporting the same issue'">
                <div class="space-y-4">
                    @php
                        $grupActiveFilterCount = count($grupFilterStatuses) + ($grupDateMode ? 1 : 0);
                    @endphp
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        {{-- Filter: Status + Date --}}
                        <x-ui.popover>
                            <x-ui.popover.trigger>
                                <button type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:border-dongker-300 hover:text-dongker-600">
                                    <x-ui.icon name="funnel" class="size-4" />
                                    Filter
                                    @if($grupActiveFilterCount > 0)
                                        <span class="rounded-full bg-dongker-100 px-2 py-0.5 text-[10px] font-semibold text-dongker-700">{{ $grupActiveFilterCount }}</span>
                                    @endif
                                </button>
                            </x-ui.popover.trigger>
                            <x-ui.popover.overlay class="!w-64 p-4"
                                x-data
                                x-on:grup-filters-reset.window="
                                    $el.querySelectorAll('input[type=checkbox]').forEach(cb => {
                                        if (cb.checked) {
                                            cb.checked = false;
                                            cb.dispatchEvent(new Event('change', { bubbles: true }));
                                        }
                                    });
                                ">
                                <div class="space-y-3">
                                    <div>
                                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</div>
                                        <x-ui.checkbox.group wire:model.live="grupFilterStatuses" class="mt-2">
                                            @foreach(\App\Models\StatusTiketModel::orderBy('id')->get() as $st)
                                                <x-ui.checkbox value="{{ $st->id }}" label="{{ $st->nama_status }}" />
                                            @endforeach
                                        </x-ui.checkbox.group>
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Date</div>
                                        <x-ui.radio.group wire:model.live="grupDateMode" direction="vertical" class="mt-2">
                                            <x-ui.radio.item value="" label="All time" />
                                            <x-ui.radio.item value="exact" label="Exact Date" />
                                            <x-ui.radio.item value="range" label="Date Range" />
                                        </x-ui.radio.group>
                                        <div x-data="{ mode: $wire.entangle('grupDateMode') }" class="mt-2 space-y-2">
                                            <div x-show="mode === 'exact'">
                                                <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1 block">Date</label>
                                                <input type="date" wire:model.live="grupDateExact"
                                                       class="w-full rounded-lg border border-dongker-100 bg-dongker-50/30 px-3 py-2 text-sm focus:ring-2 focus:ring-dongker-400"
                                                       style="accent-color:#0E4260;" />
                                            </div>
                                            <div x-show="mode === 'range'" class="space-y-2">
                                                <div>
                                                    <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1 block">Start Date</label>
                                                    <input type="date" wire:model.live="grupDateStart"
                                                           class="w-full rounded-lg border border-dongker-100 bg-dongker-50/30 px-3 py-2 text-sm focus:ring-2 focus:ring-dongker-400"
                                                           style="accent-color:#0E4260;" />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1 block">End Date</label>
                                                    <input type="date" wire:model.live="grupDateEnd"
                                                           class="w-full rounded-lg border border-dongker-100 bg-dongker-50/30 px-3 py-2 text-sm focus:ring-2 focus:ring-dongker-400"
                                                           style="accent-color:#0E4260;" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                        <button type="button" wire:click="resetGrupFilters" class="text-xs font-semibold text-gray-400 hover:text-dongker-600">Reset</button>
                                        <button type="button" x-on:click="hide()" class="inline-flex items-center rounded-lg bg-dongker-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-dongker-500">Apply</button>
                                    </div>
                                </div>
                            </x-ui.popover.overlay>
                        </x-ui.popover>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="w-full text-sm table-fixed">
                            <colgroup>
                                <col style="width: 25%;" />
                                <col style="width: 35%;" />
                                <col style="width: 25%;" />
                                <col style="width: 15%;" />
                            </colgroup>
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/60">
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                                        <button wire:click="sortGrupBy('id')" class="inline-flex items-center gap-1 hover:text-dongker-600">
                                            #TKT
                                            @if($grupSortColumn === 'id')
                                                <x-ui.icon name="{{ $grupSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3 text-dongker-600" />
                                            @else
                                                <x-ui.icon name="chevron-up-down" class="size-3 text-gray-300" />
                                            @endif
                                        </button>
                                    </th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                                        <button wire:click="sortGrupBy('status')" class="inline-flex items-center gap-1 hover:text-dongker-600">
                                            Status
                                            @if($grupSortColumn === 'status')
                                                <x-ui.icon name="{{ $grupSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3 text-dongker-600" />
                                            @else
                                                <x-ui.icon name="chevron-up-down" class="size-3 text-gray-300" />
                                            @endif
                                        </button>
                                    </th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                                        <button wire:click="sortGrupBy('date')" class="inline-flex items-center gap-1 hover:text-dongker-600">
                                            Date
                                            @if($grupSortColumn === 'date')
                                                <x-ui.icon name="{{ $grupSortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3 text-dongker-600" />
                                            @else
                                                <x-ui.icon name="chevron-up-down" class="size-3 text-gray-300" />
                                            @endif
                                        </button>
                                    </th>
                                    <th class="text-right py-3 px-4 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($grupTickets as $item)
                                    @php
                                        $statusBadgeClass = $item->statusTiket
                                            ? \App\Models\StatusTiketModel::badgeClass($item->statusTiket->nama_status)
                                            : 'bg-gray-100 text-gray-700';
                                    @endphp
                                    <tr class="hover:bg-gray-50/60 transition">
                                        <td class="py-3 px-4 text-dongker-600 font-semibold">#TKT{{ str_pad($item->id, 2, '0', STR_PAD_LEFT) }}</td>
                                        <td class="py-3 px-4">
                                            @if($item->statusTiket)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusBadgeClass }}">
                                                    {{ $item->statusTiket->nama_status }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-xs text-gray-500">{{ $item->created_at->format('d/m/Y') }}</td>
                                        <td class="py-3 px-4 text-right">
                                            <a href="/ticket/{{ $item->id }}" class="text-xs font-semibold text-dongker-600 hover:text-dongker-500" wire:navigate>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-xs text-gray-400">No tickets in this group view.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-ui.modal>
        </div>
    @endif
</div>

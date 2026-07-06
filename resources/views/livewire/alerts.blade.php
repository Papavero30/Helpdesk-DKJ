<div wire:poll.30s>
    {{-- Compact unread bar --}}
    @if($unreadCount > 0)
        <div class="flex items-center justify-between mb-5 px-1">
            <span class="px-2.5 py-0.5 rounded-lg bg-red-100 text-red-600 text-sm font-bold">{{ $unreadCount }} unread</span>
            <button wire:click="markAllRead"
                    class="text-xs font-semibold text-dongker-500 hover:text-dongker-700 transition-colors">
                Mark All as Read
            </button>
        </div>
    @endif

    {{-- Unread Section --}}
    @if($unread->isNotEmpty())
        <div class="mb-6">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Unread</h3>
            <div class="flex flex-col gap-2 stagger-children">
                @foreach($unread as $notif)
                    @php $data = $notif->data; @endphp
                    <div class="bg-white border-l-4 border-dongker-400 border border-gray-200 rounded-xl p-4 flex items-start gap-3 transition-all hover:shadow-sm"
                         wire:click="markAsRead('{{ $notif->id }}')" role="button">
                        <div @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-full',
                            'bg-amber-100 text-amber-600' => ($data['action'] ?? '') === 'ticket_created',
                            'bg-dongker-100 text-dongker-600' => ($data['action'] ?? '') === 'ticket_assigned',
                            'bg-blue-100 text-blue-600' => ($data['action'] ?? '') === 'status_changed',
                            'bg-lilac-100 text-lilac-600' => ($data['action'] ?? '') === 'reply_posted',
                            'bg-green-100 text-green-600' => ($data['action'] ?? '') === 'feedback_solved',
                            'bg-red-100 text-red-600' => ($data['action'] ?? '') === 'feedback_not_resolved',
                            'bg-dongker-100 text-dongker-600' => ($data['action'] ?? '') === 'ticket_done',
                            'bg-amber-100 text-amber-600' => ($data['action'] ?? '') === 'rating_edited',
                        ])>
                            @switch($data['action'] ?? '')
                                @case('ticket_created') <x-ui.icon name="plus-circle" class="size-4" /> @break
                                @case('ticket_assigned') <x-ui.icon name="user-plus" class="size-4" /> @break
                                @case('status_changed') <x-ui.icon name="arrow-path" class="size-4" /> @break
                                @case('reply_posted') <x-ui.icon name="chat-bubble-left-right" class="size-4" /> @break
                                @case('feedback_solved') <x-ui.icon name="check-circle" class="size-4" /> @break
                                @case('feedback_not_resolved') <x-ui.icon name="exclamation-circle" class="size-4" /> @break
                                @case('ticket_done') <x-ui.icon name="clipboard-document-check" class="size-4" /> @break
                                @case('rating_edited') <x-ui.icon name="pencil-square" class="size-4" /> @break
                                @default <x-ui.icon name="bell" class="size-4" />
                            @endswitch
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-dongker-600">{{ $data['title'] ?? 'Notification' }}</div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $data['description'] ?? '' }}</p>
                            <div class="text-[10px] text-gray-400 mt-1">{{ $notif->created_at->diffForHumans() }}</div>
                        </div>
                        @if(isset($data['link']))
                            <a href="{{ $data['link'] }}" class="text-xs text-dongker-500 hover:text-dongker-700 font-semibold shrink-0" wire:navigate>
                                View &rarr;
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Read Section --}}
    @if($read->isNotEmpty())
        <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Read</h3>
            <div class="flex flex-col gap-2">
                @foreach($read as $notif)
                    @php $data = $notif->data; @endphp
                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 flex items-start gap-3 opacity-70">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                            <x-ui.icon name="bell" class="size-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-gray-600">{{ $data['title'] ?? 'Notification' }}</div>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $data['description'] ?? '' }}</p>
                            <div class="text-[10px] text-gray-400 mt-1">{{ $notif->created_at->diffForHumans() }}</div>
                        </div>
                        @if(isset($data['link']))
                            <a href="{{ $data['link'] }}" class="text-xs text-gray-400 hover:text-gray-600 font-semibold shrink-0" wire:navigate>
                                View &rarr;
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Empty state --}}
    @if($unread->isEmpty() && $read->isEmpty())
        <div class="text-center py-16">
            <x-ui.icon name="bell-slash" class="size-12 text-gray-300 mx-auto mb-3" />
            <p class="text-sm text-gray-400">No notifications yet</p>
        </div>
    @endif

    {{-- Load More --}}
    @if($hasMore)
        <div class="text-center mt-6">
            <button wire:click="loadMore" class="text-xs font-semibold text-dongker-500 hover:text-dongker-700 transition-colors">
                Load More
            </button>
        </div>
    @endif
</div>

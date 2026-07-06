<a href="/alerts"
   class="relative p-2 rounded-lg hover:bg-gray-100 transition-colors"
   wire:navigate
   x-data
   x-init="
       /* Subscribe to the user's private notification channel exactly ONCE per
          page session. Guarded with a global flag so Livewire morphs and
          wire:navigate re-inits don't subscribe again (double subscription was
          producing a second, malformed 'App.Models.User.undefined' channel that
          returned 403). Skips entirely if Echo is absent or the id is invalid. */
       (() => {
           const userId = {{ $userId ?? 'null' }};
           if (! userId || typeof window.Echo === 'undefined') return;
           if (window.__bellChannelSubscribed) return;
           window.__bellChannelSubscribed = true;

           window.Echo.private('App.Models.User.' + userId)
               .notification(() => {
                   /* Broadcast a window event; the listener below (Alpine-managed,
                      auto-cleaned) refreshes whichever bell-badge is on the page. */
                   window.dispatchEvent(new CustomEvent('bell-notification'));
               });
       })();
   "
   @bell-notification.window="$wire.dispatchSelf('notification-received')">
    <x-ui.icon name="bell" class="size-5" style="color:#64748B;" />
    @if($unreadCount > 0)
        <span class="absolute -top-0.5 -right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white animate-pulse-soft">
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
    @endif
</a>

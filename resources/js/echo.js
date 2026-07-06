import Echo from 'laravel-echo';

import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Only start Echo when Reverb is actually configured, and never let a bad or
// unreachable config throw here: an exception at module load would abort the
// whole bundle and kill every Livewire/Alpine interaction on the page.
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (e) {
        console.warn('Echo/Reverb not initialised:', e);
    }
}

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ref } from 'vue';

/**
 * The one connection to the live-updates server (Laravel Reverb), opened
 * the first time a page asks for it. Without its settings, pages simply
 * don't update live.
 */

export type LiveState = 'connecting' | 'connected' | 'offline';

export const liveState = ref<LiveState>('offline');

let echo: Echo<'reverb'> | null = null;

/** Laravel's CSRF cookie, which it refreshes on every response. */
function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

export function live(): Echo<'reverb'> | null {
    if (echo) {
        return echo;
    }

    const env = import.meta.env;

    if (!env.VITE_REVERB_APP_KEY) {
        return null;
    }

    const secure = (env.VITE_REVERB_SCHEME ?? 'https') === 'https';

    echo = new Echo({
        broadcaster: 'reverb',
        Pusher,
        key: env.VITE_REVERB_APP_KEY,
        wsHost: env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(env.VITE_REVERB_PORT ?? 443),
        forceTLS: secure,
        enabledTransports: ['ws', 'wss'],
        // Private channels are signed for by the app. The token comes from the
        // cookie rather than the page, which would be stale after logging in.
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then(async (response) => {
                        if (!response.ok) {
                            throw new Error(
                                `Live updates refused: ${response.status}`,
                            );
                        }

                        callback(null, await response.json());
                    })
                    .catch((error) => callback(error, null));
            },
        }),
    });

    liveState.value = 'connecting';
    echo.connector.pusher.connection.bind(
        'state_change',
        ({ current }: { current: string }) => {
            liveState.value =
                current === 'connected'
                    ? 'connected'
                    : current === 'connecting' || current === 'initialized'
                      ? 'connecting'
                      : 'offline';
        },
    );

    return echo;
}

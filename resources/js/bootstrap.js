/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const appBaseUrl = document
    .querySelector('meta[name="app-base-url"]')
    ?.getAttribute('content')
    ?.trim();

if (appBaseUrl) {
    window.axios.defaults.baseURL = appBaseUrl;
}

window.Pusher = Pusher;

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;

if (pusherKey) {
    const pusherHost = import.meta.env.VITE_PUSHER_HOST;
    const pusherScheme = import.meta.env.VITE_PUSHER_SCHEME || 'https';
    const pusherPort = Number(import.meta.env.VITE_PUSHER_PORT || (pusherScheme === 'https' ? 443 : 80));

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        wsHost: pusherHost || undefined,
        wsPort: pusherPort,
        wssPort: pusherPort,
        forceTLS: pusherScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

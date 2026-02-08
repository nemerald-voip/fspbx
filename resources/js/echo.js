/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const isHttps = window.location.protocol === 'https:'
const host = window.location.hostname
const port = window.location.port
    ? Number(window.location.port)
    : (isHttps ? 443 : 80)

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,

    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: isHttps,

    enabledTransports: ['ws', 'wss'],
})
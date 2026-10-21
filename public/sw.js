const CACHE_VERSION = 'dhermes-static-v1';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/favicon.svg',
    '/favicon-32.png',
    '/apple-touch-icon.png',
    '/icons/icon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/maskable-192.png',
    '/icons/maskable-512.png',
];

const CACHE_FIRST_PREFIXES = ['/build/'];
const STALE_WHILE_REVALIDATE_PREFIXES = ['/icons/', '/fonts/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE_URLS))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => key.startsWith('dhermes-static-') && key !== CACHE_VERSION)
                .map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || url.pathname === '/sw.js') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );

        return;
    }

    if (url.pathname.startsWith('/livewire/') || url.pathname.startsWith('/api/')) {
        return;
    }

    if (CACHE_FIRST_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (STALE_WHILE_REVALIDATE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))
        || url.pathname === '/manifest.webmanifest'
        || url.pathname.startsWith('/favicon')) {
        event.respondWith(staleWhileRevalidate(request));
    }
});

async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(CACHE_VERSION);
        cache.put(request, response.clone());
    }

    return response;
}

async function staleWhileRevalidate(request) {
    const cached = await caches.match(request);
    const network = fetch(request).then(async (response) => {
        if (response.ok) {
            const cache = await caches.open(CACHE_VERSION);
            cache.put(request, response.clone());
        }

        return response;
    }).catch(() => null);

    return cached || network || fetch(request);
}

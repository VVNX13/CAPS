/* eslint-env serviceworker */

/**
 * Taman Teras Cafe - service worker
 *
 * Strategy per request type:
 *   - /assets/*      stale-while-revalidate (fast + self-healing)
 *   - HTML pages     network-first, cache fallback (menu still opens offline)
 *   - /api/*         never cached (stale orders and prices would be wrong)
 *   - /receipt/*     never cached (contains live payment status)
 *   - non-GET        never touched (checkout must hit the server)
 *
 * Bump VERSION whenever this file changes so every installed app rebuilds its
 * caches on the next visit.
 */

const VERSION = 'v6';
const STATIC_CACHE = `tteras-static-${VERSION}`;
const PAGE_CACHE = `tteras-pages-${VERSION}`;

const PRECACHE = [
    '/',
    '/manifest.webmanifest',
    '/assets/css/style.css',
    '/assets/js/csrf.js',
    '/assets/js/customer.js',
    '/assets/js/owner.js',
    '/assets/js/receivable.js',
    '/assets/js/history.js',
    '/assets/js/pwa.js',
    '/assets/js/nav.js',
    '/assets/icons/icon-192.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== STATIC_CACHE && key !== PAGE_CACHE)
                        .map((key) => caches.delete(key))
                )
            )
            .then(() => self.clients.claim())
    );
});

function isCacheablePage(url) {
    return !url.pathname.startsWith('/api/')
        && !url.pathname.startsWith('/owner/')
        && !url.pathname.startsWith('/receipt/')
        // Order history shows live payment state, so it must never be served
        // from a cache that could claim an unpaid bill was settled.
        && url.pathname !== '/riwayat'
        && !url.pathname.startsWith('/login')
        && url.pathname !== '/up';
}

/**
 * Stale-while-revalidate: answer instantly from the cache, then quietly refresh
 * the entry for the next visit.
 *
 * A plain cache-first strategy pins a file forever once it has been stored,
 * which is what silently shipped an outdated stylesheet to installed phones.
 * Combined with the mtime query string that asset_versioned() adds, a changed
 * file always produces a new URL, so this can never serve a stale body.
 */
async function staleWhileRevalidate(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);

    const network = fetch(request)
        .then((response) => {
            if (response && response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => null);

    if (cached) {
        // Do not await: the cached copy is returned right away.
        network.catch(() => {});

        return cached;
    }

    const response = await network;

    if (response) {
        return response;
    }

    throw new Error('Offline and not cached: ' + request.url);
}

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        const copy = response.clone();

        if (response.ok) {
            caches.open(PAGE_CACHE).then((cache) => cache.put(request, copy));
        }

        return response;
    } catch (error) {
        const cached = await caches.match(request);

        if (cached) {
            return cached;
        }

        throw error;
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/assets/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(staleWhileRevalidate(request));

        return;
    }

    if (request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html')) {
        if (!isCacheablePage(url)) {
            return;
        }

        event.respondWith(networkFirst(request));
    }
});

/* ------------------------------------------------------------------ */
/* Web push                                                            */
/* ------------------------------------------------------------------ */

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Taman Teras Cafe';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || 'Ada aktivitas baru',
            icon: '/assets/icons/icon-192.png',
            badge: '/assets/icons/icon-192.png',
            tag: payload.tag || 'tteras',
            renotify: true,
            requireInteraction: Boolean(payload.requireInteraction),
            vibrate: payload.vibrate || [200, 100, 200],
            data: { url: payload.url || '/owner/dashboard' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = (event.notification.data && event.notification.data.url) || '/owner/dashboard';

    event.waitUntil(
        (async () => {
            const windows = await self.clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });

            for (const client of windows) {
                if (client.url.includes(target) && 'focus' in client) {
                    return client.focus();
                }
            }

            return self.clients.openWindow(target);
        })()
    );
});

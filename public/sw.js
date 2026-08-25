/*
 * A deliberately small service worker.
 *
 * It exists so the app opens on a phone with no signal and says something
 * useful instead of showing the browser's dinosaur. It does NOT try to make
 * the whole app work offline - stock levels that are three hours stale are
 * worse than no stock levels, because someone will act on them.
 */
const VERSION = 'v1';
const SHELL_CACHE = `shell-${VERSION}`;
const ASSET_CACHE = `assets-${VERSION}`;

const SHELL = ['/offline', '/favicon.svg', '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== SHELL_CACHE && key !== ASSET_CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Anything that changes data goes to the network or fails honestly. The
    // offline queue handles retries, not the cache.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) return;

    // Built assets are content-hashed, so once cached they are safe forever.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.open(ASSET_CACHE).then(async (cache) => {
                const hit = await cache.match(request);
                if (hit) return hit;

                const response = await fetch(request);
                if (response.ok) cache.put(request, response.clone());
                return response;
            }),
        );

        return;
    }

    // Pages: always try the network first, because stale stock is dangerous.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const cache = await caches.open(SHELL_CACHE);
                return (await cache.match('/offline')) ?? Response.error();
            }),
        );
    }
});

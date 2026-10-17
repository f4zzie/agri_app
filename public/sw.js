/* AgriTrack service worker
 * - Static assets (CSS/JS/icons, Bootstrap from the CDN): cache-first.
 * - Pages: network-first. The listing form (add_crop.php) is kept so it
 *   can be opened offline; other pages fall back to offline.html.
 *   Signed-in pages are otherwise not cached, so nothing private
 *   lingers on shared phones (logout also clears the cache).
 */
const VERSION = 'agritrack-v1';
const PRECACHE = [
    'offline.html', 'style.css', 'script.js', 'manifest.webmanifest',
    'assets/icon-192.png', 'assets/icon-512.png',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css'
];
const OFFLINE_PAGES = ['add_crop.php'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') {
        return; // never intercept form posts
    }
    const url = new URL(req.url);

    if (req.mode === 'navigate') {
        const keep = url.origin === location.origin && OFFLINE_PAGES.some((p) => url.pathname.endsWith('/' + p));
        event.respondWith(
            fetch(req)
                .then((res) => {
                    if (keep && res.ok) {
                        const copy = res.clone();
                        caches.open(VERSION).then((c) => c.put(req, copy));
                    }
                    return res;
                })
                .catch(() => caches.match(req).then((hit) => hit || caches.match('offline.html')))
        );
        return;
    }

    const isStatic = /\.(css|js|png|svg|jpg|woff2?|webmanifest)$/.test(url.pathname)
        && (url.origin === location.origin || url.hostname === 'cdn.jsdelivr.net');
    if (isStatic) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(VERSION).then((c) => c.put(req, copy));
                }
                return res;
            }))
        );
    }
});

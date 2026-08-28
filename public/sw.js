/**
 * خدمة موفّر العامل — تخزين مبدئي لصفحة عدم الاتصال والخطوط، وتخزين
 * تراكمي لأصول Vite المبنية (أسماء ملفاتها تتغيّر بكل بناء، فتُضاف إلى
 * الذاكرة عند أول طلب لا مسبقاً).
 */

const CACHE_VERSION = 'muwaffir-v1';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
    OFFLINE_URL,
    '/fonts/thmanyahsans-Light.woff2',
    '/fonts/thmanyahsans-Regular.woff2',
    '/fonts/thmanyahsans-Medium.woff2',
    '/fonts/thmanyahsans-Bold.woff2',
    '/fonts/thmanyahsans-Black.woff2',
    '/fonts/thmanyahserifdisplay-Bold.woff2',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // تنقّل بين الصفحات: الشبكة أولاً، وعند الفشل صفحة «بلا اتصال».
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );

        return;
    }

    const url = new URL(request.url);
    const isStaticAsset = url.pathname.startsWith('/build/') || url.pathname.startsWith('/fonts/');

    if (!isStaticAsset) {
        return;
    }

    // أصول ثابتة: من الذاكرة أولاً، وتحديثها في الخلفية.
    event.respondWith(
        caches.open(CACHE_VERSION).then(async (cache) => {
            const cached = await cache.match(request);
            const network = fetch(request).then((response) => {
                if (response.ok) {
                    cache.put(request, response.clone());
                }

                return response;
            }).catch(() => cached);

            return cached ?? network;
        }),
    );
});

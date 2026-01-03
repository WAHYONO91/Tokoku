// /tokoapp/service-worker.js

const CACHE_NAME = 'tokoapp-v1';

const CACHE_FIRST_EXTS = ['.css','.js','.png','.jpg','.jpeg','.svg','.webp','.ico','.woff','.woff2','.ttf'];

const PRECACHE_URLS = [
  '/tokoapp/index.php',
  '/tokoapp/auth/login.php',
  '/tokoapp/uploads/logo-192.png',
  '/tokoapp/uploads/logo-512.png',
  '/tokoapp/assets/vendor/pico/pico.min.css',
  '/tokoapp/assets/vendor/chart.umd.min.js',
  '/tokoapp/assets/vendor/JsBarcode.all.min.js',
  '/tokoapp/assets/vendor/qrcode.min.js'
];

// Install: cache beberapa asset dasar
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

// Activate: bersihkan cache lama & klaim clients
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

// Fetch: cache-first untuk asset statik, network-first untuk halaman/API
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    // biarkan request non-GET (POST, dll) lewat ke network langsung
    return;
  }

  event.respondWith(
    (async () => {
      try {
        const url = new URL(event.request.url);
        const isSameOrigin = url.origin === self.location.origin;
        const isGet = event.request.method === 'GET';
        const isStatic = isSameOrigin && isGet && CACHE_FIRST_EXTS.some(ext => url.pathname.endsWith(ext));
        if (isStatic) {
          const cached = await caches.match(event.request);
          if (cached) return cached;
          const fresh = await fetch(event.request);
          const cache = await caches.open(CACHE_NAME);
          cache.put(event.request, fresh.clone());
          return fresh;
        }
      } catch(e) {}

      try {
        // 1. Coba jaringan dulu
        const networkResponse = await fetch(event.request);
        return networkResponse;
      } catch (err) {
        // 2. Kalau gagal (mis. offline), coba dari cache
        const cached = await caches.match(event.request);
        if (cached) return cached;

        // 3. Fallback terakhir: index.php (dashboard)
        const fallback = await caches.match('/tokoapp/index.php');
        return fallback || Response.error();
      }
    })()
  );
});

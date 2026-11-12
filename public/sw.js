/*
 * Service Worker HRIS Chutex (PWA).
 * Strategi: Network-first untuk halaman navigasi (selalu coba jaringan dulu,
 * fallback ke cache kalau offline), Cache-first untuk aset statis
 * (css/js/img/icon) yang immutable-ish. Versi cache dinaikkan setiap
 * kali ada perubahan aset supaya konten lama tergantikan.
 */
const CACHE_VERSION = 'hrcii-v1';
const CACHE_STATIC = CACHE_VERSION + '-static';
const CACHE_PAGES = CACHE_VERSION + '-pages';

const PRECACHE_URLS = [
  '/',
  '/manifest.webmanifest',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
  '/icons/maskable-512x512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_STATIC).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys
        .filter((key) => key.startsWith('hrcii-') && key !== CACHE_STATIC && key !== CACHE_PAGES)
        .map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Abaikan request non-GET (POST form, API write, dsb) — biarkan lewat jaringan.
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // Jangan ikut campur request ke origin lain (CDN, fonts, dsb).
  if (url.origin !== self.location.origin) return;

  // Navigasi halaman: network-first, fallback ke cache (offline).
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.ok) {
            const clone = response.clone();
            caches.open(CACHE_PAGES).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match('/')))
    );
    return;
  }

  // Aset statis: cache-first, isi cache dari jaringan kalau belum ada.
  if (/\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff2?|ttf|webmanifest)$/i.test(url.pathname)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          if (response && response.ok) {
            const clone = response.clone();
            caches.open(CACHE_STATIC).then((cache) => cache.put(request, clone));
          }
          return response;
        }).catch(() => cached);
      })
    );
    return;
  }

  // Sisanya (API GET, dsb): network-first dengan fallback cache.
  event.respondWith(
    fetch(request)
      .then((response) => {
        if (response && response.ok && url.pathname.startsWith('/')) {
          const clone = response.clone();
          caches.open(CACHE_PAGES).then((cache) => cache.put(request, clone));
        }
        return response;
      })
      .catch(() => caches.match(request))
  );
});

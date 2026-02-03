// Service Worker for PWA
const CACHE_NAME = 'attendance-cache-v4';
// لا نخزن صفحات الدخول/الموظف/الإدارة في الكاش حتى تعمل الجلسة على الهاتف
const urlsToCache = [
  '/',
  '/index.php',
  '/logo.png',
  '/manifest.json'
];

// عدم اعتراض صفحة الموظف والدخول أبداً — الطلب يذهب مباشرة للسيرفر (يحل مشكلة الموبايل)
const SKIP_INTERCEPT_PATHS = ['/employee.php', '/admin.php', '/index.php'];

function shouldSkipIntercept(url) {
  try {
    const path = new URL(url).pathname;
    return path === '/' || SKIP_INTERCEPT_PATHS.some(p => path === p || path.endsWith(p));
  } catch (_) { return false; }
}

// Install event - cache static files only
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
      .catch(() => {})
  );
  self.skipWaiting();
});

// Activate event - clean old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch: عدم اعتراض employee/index/admin — الطلب يمر مباشرة (جلسة وكوكي يعملان على الموبايل)
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  if (shouldSkipIntercept(event.request.url)) return;

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) return response;
        return fetch(event.request).then(response => {
          if (!response || response.status !== 200 || response.type !== 'basic') return response;
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          return response;
        });
      })
      .catch(() => caches.match('/offline.html'))
  );
});

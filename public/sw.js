// Service Worker pour Eurovision Vote App
const CACHE_NAME = 'eurovision-vote-cache-v1';

// Fichiers à mettre en cache
const urlsToCache = [
  '/',
  '/styles/app.css',
  '/images/favicon.svg',
  '/images/favicon-16x16.png',
  '/images/favicon-32x32.png',
  '/images/apple-touch-icon.png',
  '/images/icon-192x192.png',
  '/images/icon-512x512.png',
  'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
  'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js'
];

// Installation du Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Stratégie de cache : Network First, fallback to cache
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request)
      .catch(() => {
        return caches.match(event.request);
      })
  );
});

// Nettoyage des anciennes caches
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});
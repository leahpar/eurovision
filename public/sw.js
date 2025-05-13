// Service Worker minimal pour Eurovision Vote App
const CACHE_NAME = 'eurovision-offline-cache-v1';

// Installation du Service Worker - mettre en cache uniquement la page offline
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.add('/offline.html').catch(error => {
          console.log('Erreur lors de la mise en cache de la page offline:', error);
        });
      })
  );
});

// Activation immédiate du Service Worker
self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
});

// Stratégie sans cache - uniquement pour servir la page offline en cas de déconnexion
self.addEventListener('fetch', event => {
  // N'intercepter que les requêtes de navigation (pas les assets, pas les API)
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          // En cas d'échec de navigation (client offline), servir la page offline
          return caches.match('/offline.html');
        })
    );
  }
  // Laisser toutes les autres requêtes se comporter normalement
});
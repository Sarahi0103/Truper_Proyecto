/**
 * Truper Platform - Service Worker
 * Soporte Offline y Caché para Escáner de Almacén y Catálogo
 */

const CACHE_NAME = 'truper-cache-v1';
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/inventory_scanner.php',
    '/css/styles.css',
    '/css/theme.css',
    '/css/toast-notifications.css',
    '/js/toast-notifications.js',
    '/truper_logo2.png',
    '/truper_logo1.png'
];

// Instalación del Service Worker
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activación y limpieza de cachés antiguos
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(name => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Estrategia Network-First con Fallback a Caché
self.addEventListener('fetch', event => {
    // Solo interceptar solicitudes GET y no API dinámicas
    if (event.request.method !== 'GET' || event.request.url.includes('/api/')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Clonar respuesta si es válida
                if (response && response.status === 200) {
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseToCache);
                    });
                }
                return response;
            })
            .catch(() => {
                // Fallback a caché si no hay internet
                return caches.match(event.request).then(cachedResponse => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    if (event.request.headers.get('accept').includes('text/html')) {
                        return caches.match('/inventory_scanner.php');
                    }
                });
            })
    );
});

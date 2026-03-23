// Service Worker - AsistenciaRLabs
// No cachear nada - siempre ir a la red
self.addEventListener('fetch', function(event) {
    // Solo manejar peticiones GET
    if (event.request.method !== 'GET') return;
    
    // No cachear rutas del checkin - siempre red
    const url = new URL(event.request.url);
    if (url.pathname.startsWith('/checkin')) {
        event.respondWith(fetch(event.request));
        return;
    }
});

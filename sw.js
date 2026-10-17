/* Studio FORMA – kleine Web-App-Hilfe.
   Seiten kommen immer frisch aus dem Netz (nur ohne Netz die zuletzt geladene Startseite).
   Schriften, Bilder und Styles werden zwischengespeichert und im Hintergrund aktualisiert.
   Buchungen, Zahlungen (api/) und die Verwaltung laufen nie über diesen Zwischenspeicher. */
var VERSION = 'forma-v1';
var STATIC = /^\/(fonts|img|assets)\//;
var SKIP = /^\/(api|verwaltung|video|einloesen|tools|downloads)\//;

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(VERSION).then(function (c) { return c.addAll(['/', '/img/forma-logo.png']); }).catch(function () {}));
  self.skipWaiting();
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);
  if (url.origin !== self.location.origin || SKIP.test(url.pathname) || req.headers.has('range')) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).then(function (res) {
      if (url.pathname === '/' && res.ok) { var copy = res.clone(); caches.open(VERSION).then(function (c) { c.put('/', copy); }); }
      return res;
    }).catch(function () {
      return caches.match('/').then(function (hit) {
        return hit || new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;background:#EEE2D4;color:#33211B;padding:40px 20px;text-align:center"><h1 style="color:#8C2C1D">Gerade offline</h1><p>Sobald du wieder Internet hast, geht es hier weiter.</p>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
      });
    }));
    return;
  }

  if (STATIC.test(url.pathname)) {
    e.respondWith(caches.open(VERSION).then(function (c) {
      return c.match(req).then(function (hit) {
        var net = fetch(req).then(function (res) { if (res.ok) c.put(req, res.clone()); return res; });
        if (hit) { net.catch(function () {}); return hit; }
        return net;
      });
    }));
  }
});

/* Movecia – Web-App-Hilfe (Vivien, 25.10.2026). Läuft nur auf moveciamovement.com (ausgeliefert als /sw.js).
   Seiten kommen immer frisch aus dem Netz; nur ohne Netz zeigt die App die zuletzt geladene Startseite oder einen Offline-Hinweis.
   Schriften, Bilder und Styles werden zwischengespeichert und im Hintergrund aktualisiert.
   Buchungen, Zahlungen, Videoräume, Videos und die Verwaltung laufen nie über diesen Zwischenspeicher.
   Bei größeren Bild- oder Style-Änderungen VERSION hochzählen. */
var VERSION = 'movecia-v1';
var STATIC = /^\/(fonts|img|wildhearts\/assets)\//;
var SKIP = /^\/(api|verwaltung|video|tools|downloads|natal\/raum|unfold\/call|gathering)(\/|$)/;

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(VERSION).then(function (c) {
    return c.addAll(['/', '/img/movecia/movecia-zeichen-klein.svg', '/img/movecia/movecia-app-192.png']);
  }).catch(function () {}));
  self.skipWaiting();
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k.indexOf('movecia-') === 0 && k !== VERSION; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

var OFFLINE = '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Movecia · offline</title>' +
  '<body style="margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#F2ECE4;color:#2E2529;text-align:center;padding:40px 24px;box-sizing:border-box">' +
  '<div><img src="/img/movecia/movecia-zeichen-klein.svg" width="64" height="64" alt="">' +
  '<h1 style="font-family:Georgia,serif;font-weight:400;font-size:2rem;margin:.6em 0 .3em;color:#7D5E68">Gerade offline</h1>' +
  '<p style="max-width:22em;margin:0 auto;line-height:1.55;color:#7D7076">Atme kurz durch. Sobald du wieder Internet hast, geht es hier weiter.<br><span style="font-size:.9em">You are offline – Movecia is back as soon as you are connected.</span></p>' +
  '<button onclick="location.reload()" style="margin-top:1.6em;font:inherit;border:0;border-radius:999px;background:#7D5E68;color:#FBF5F7;padding:.8em 1.6em">Nochmal versuchen</button></div>';

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
      return caches.match(url.pathname === '/' ? '/' : '__none__').then(function (hit) {
        return hit || new Response(OFFLINE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
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

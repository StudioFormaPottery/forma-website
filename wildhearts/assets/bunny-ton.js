/* Ton für ganze Videos (Talking Heads) sicherstellen (Vivien, 30.10.2026: „wieder kein Ton“).
   Der Bunny-Player merkt sich „stumm“ im Browser. Die Übungs-Clips laufen absichtlich stumm,
   danach starteten Talking Heads im selben Browser ebenfalls stumm. Beim Start schalten wir sie laut.
   Gilt für jedes Bunny-iframe ohne muted=true (Clips bleiben stumm). */
(function () {
  var q = [];
  function pj(cb) {
    if (window.playerjs) return cb();
    q.push(cb); if (q.length > 1) return;
    var s = document.createElement('script'); s.src = 'https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js';
    s.onload = function () { q.forEach(function (f) { f(); }); }; document.head.appendChild(s);
  }
  function loud(fr) {
    if (fr.dataset.mvTon) return; fr.dataset.mvTon = '1';
    pj(function () {
      try {
        var p = new window.playerjs.Player(fr);
        p.on('ready', function () {
          var up = function () { try { p.unmute(); p.setVolume(100); } catch (e) {} };
          up(); p.on('play', up);
        });
      } catch (e) {}
    });
  }
  function scan() {
    document.querySelectorAll('iframe[src*="mediadelivery.net/embed"]').forEach(function (f) { if (!/muted=true/.test(f.src)) loud(f); });
  }
  window.MvTon = scan;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
})();

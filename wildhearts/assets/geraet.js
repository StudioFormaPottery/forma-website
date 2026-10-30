/* Ein Zugang = eine Person (Vivien, 26.10.2026): Mitgliederbereich nur auf einem Gerät gleichzeitig aktiv.
   <script src="/wildhearts/assets/geraet.js" data-kind="mv|natal" defer></script> */
(function () {
  var me = document.currentScript, kind = (me && me.dataset.kind) || 'mv';
  var en = (document.documentElement.lang || '').indexOf('en') === 0;
  var T = en ? {
    busyH: 'Your access is open on another device',
    busy: 'Right now your access is being used on another device{o}. Your access belongs to you alone and cannot be shared with other people. If that other device is yours, just continue here and it will pause there.',
    pausedH: 'Paused on this device',
    paused: 'Your access is now being used on another device{o}, so it is paused here. Your access cannot be shared with several people. If it’s you, just continue here.',
    go: 'Continue here'
  } : {
    busyH: 'Dein Zugang ist gerade woanders geöffnet',
    busy: 'Dein Zugang wird gerade auf einem anderen Gerät genutzt{o}. Er gehört nur dir und kann nicht mit anderen Personen geteilt werden. Wenn das andere Gerät deins ist, mach einfach hier weiter, dort pausiert es dann.',
    pausedH: 'Hier ist dein Zugang pausiert',
    paused: 'Dein Zugang wird jetzt auf einem anderen Gerät genutzt{o}, deshalb ist er hier pausiert. Dein Zugang kann nicht mit mehreren Personen geteilt werden. Wenn du das bist, mach einfach hier weiter.',
    go: 'Hier weitermachen'
  };
  var box = null, frames = [];
  function css() {
    var s = document.createElement('style');
    s.textContent = '.mvdev{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:24px;background:rgba(46,37,41,.72);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}' +
      '.mvdev>div{max-width:30rem;background:#F5F1EB;color:#2A2026;border-radius:28px;padding:2.2rem 1.8rem;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.25);font:400 1.06rem/1.6 "Hanken Grotesk",system-ui,sans-serif}' +
      '.mvdev img{width:58px;height:58px}.mvdev h2{font:italic 400 1.9rem/1.15 "Cormorant Garamond",Georgia,serif;color:#4A3040;margin:.7rem 0 .7rem}' +
      '.mvdev p{margin:0;color:#2E1D27}.mvdev button{margin-top:1.5rem;font:500 1.02rem "Hanken Grotesk",system-ui,sans-serif;border:0;border-radius:999px;background:#4A3040;color:#F7F1EE;padding:.85em 1.7em;cursor:pointer}';
    document.head.appendChild(s);
  }
  function hold(on) {
    document.querySelectorAll('video').forEach(function (v) { try { if (on) v.pause(); } catch (e) {} });
    if (on) {
      document.querySelectorAll('iframe').forEach(function (f) { if (f.src && f.src !== 'about:blank') { frames.push([f, f.src]); f.src = 'about:blank'; } });
      document.querySelectorAll('audio').forEach(function (a) { try { a.pause(); } catch (e) {} });
      document.dispatchEvent(new CustomEvent('mvdev:hold'));
    }
  }
  function show(state, other) {
    hold(true);
    if (!box) { css(); box = document.createElement('div'); box.className = 'mvdev'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); document.body.appendChild(box); }
    var o = other ? ' (' + other + ')' : '';
    box.innerHTML = '<div><img src="/img/movecia/movecia-zeichen.svg" alt=""><h2>' + (state === 'busy' ? T.busyH : T.pausedH) + '</h2><p>' +
      (state === 'busy' ? T.busy : T.paused).replace('{o}', o.replace(/[<>&]/g, '')) + '</p><button type="button">' + T.go + '</button></div>';
    box.querySelector('button').onclick = take;
  }
  function take() {
    var b = new FormData(); b.append('k', kind); b.append('take', '1');
    fetch('/api/mm-device.php', { method: 'POST', body: b, credentials: 'same-origin' }).then(function () { location.reload(); }).catch(function () { location.reload(); });
  }
  function ping() {
    if (document.hidden) return;
    fetch('/api/mm-device.php?k=' + kind, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.state === 'busy' || d.state === 'paused') show(d.state, d.other || '');
      else if (d.state === 'out') location.reload();
    }).catch(function () {});
  }
  ping();
  setInterval(ping, 25000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) ping(); });
})();

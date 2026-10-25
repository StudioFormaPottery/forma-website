/* Movecia als App (Vivien, 25.10.2026): installierbare Web-App für moveciamovement.com.
   - registriert den Service Worker (/sw.js = wildhearts/app/sw.js, nur auf moveciamovement.com bzw. lokal)
   - jedes Element mit [data-mv-app] öffnet die Anleitung „Movecia aufs Handy holen“
     (iPhone: Teilen → Zum Home-Bildschirm; Android/Computer: echter Installieren-Knopf, sonst Browsermenü)
   - in der installierten App: Klasse html.mv-standalone, [data-mv-app] verschwindet */
(function () {
  var root = document.documentElement;
  var host = location.hostname;
  var okHost = /(^|\.)moveciamovement\.com$/.test(host) || host === '127.0.0.1' || host === 'localhost';
  var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  if (standalone) root.classList.add('mv-standalone');

  if (okHost && 'serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {}); });
  }

  var deferred = null;
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; });
  window.addEventListener('appinstalled', function () { deferred = null; close(); root.classList.add('mv-standalone'); });

  var ua = navigator.userAgent || '';
  var ios = /iPhone|iPad|iPod/i.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
  var android = /Android/i.test(ua);
  function lang() { var l = root.dataset.l || root.lang || 'de'; return l.indexOf('en') === 0 ? 'en' : 'de'; }

  var T = {
    de: {
      title: 'Movecia aufs Handy holen',
      lead: 'Mit dem Movecia-Icon auf deinem Home-Bildschirm bist du mit einem Tipp in deinem Mitgliederbereich, bei Unfold und den Gatherings. Ganz ohne App Store.',
      ios: ['Tippe in Safari auf <b>Teilen</b> <span class="mva-ico">' + share() + '</span> (beim neuen Safari zuerst auf <b>•••</b>).', 'Wähle <b>„Zum Home-Bildschirm“</b>.', 'Tippe auf <b>„Hinzufügen“</b>. Fertig, Movecia liegt jetzt bei deinen Apps.'],
      other: ['Öffne das Browsermenü <b>⋮</b> oben rechts.', 'Wähle <b>„App installieren“</b> oder <b>„Zum Startbildschirm hinzufügen“</b>.', 'Bestätige mit <b>„Installieren“</b>.'],
      btn: 'Jetzt installieren', done: 'Schließen', iosHint: 'Auf dem iPhone geht das am besten in Safari.'
    },
    en: {
      title: 'Get Movecia on your phone',
      lead: 'With the Movecia icon on your home screen you are one tap away from your members area, Unfold and the Gatherings. No app store needed.',
      ios: ['In Safari tap <b>Share</b> <span class="mva-ico">' + share() + '</span> (in the new Safari tap <b>•••</b> first).', 'Choose <b>“Add to Home Screen”</b>.', 'Tap <b>“Add”</b>. Done, Movecia now sits with your apps.'],
      other: ['Open the browser menu <b>⋮</b> at the top right.', 'Choose <b>“Install app”</b> or <b>“Add to Home screen”</b>.', 'Confirm with <b>“Install”</b>.'],
      btn: 'Install now', done: 'Close', iosHint: 'On iPhone this works best in Safari.'
    }
  };
  function share() { return '<svg width="16" height="18" viewBox="0 0 16 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 13V2M4.5 5.5L8 2l3.5 3.5M5 8H3v10h10V8h-2"/></svg>'; }

  var css = '.mv-standalone [data-mv-app]{display:none!important}' +
    '.mva{position:fixed;inset:0;z-index:1100;display:flex;align-items:flex-end;justify-content:center;background:rgba(46,37,41,.38);opacity:0;transition:opacity .22s ease;font-family:"Hanken",system-ui,sans-serif;color:#2E2529}' +
    '.mva.open{opacity:1}.mva[hidden]{display:none}' +
    '.mva-card{width:100%;max-width:460px;background:#F2ECE4;border-radius:26px 26px 0 0;padding:26px 22px max(24px,env(safe-area-inset-bottom));box-shadow:0 -10px 40px rgba(46,37,41,.18);transform:translateY(24px);transition:transform .25s ease;font-size:17px;line-height:1.5}' +
    '.mva.open .mva-card{transform:none}' +
    '@media(min-width:700px){.mva{align-items:center}.mva-card{border-radius:26px}}' +
    '.mva-head{display:flex;gap:14px;align-items:center}.mva-head img{width:56px;height:56px;border-radius:14px;flex:0 0 56px}' +
    '.mva h2{font-family:"Cormorant",Georgia,serif;font-weight:500;font-size:1.75rem;line-height:1.1;margin:0}' +
    '.mva p{margin:.8em 0 0;color:#7D7076;font-size:.95rem}' +
    '.mva ol{list-style:none;padding:0;margin:18px 0 0;counter-reset:s}' +
    '.mva li{counter-increment:s;display:grid;grid-template-columns:30px 1fr;gap:12px;align-items:start;padding:10px 0;border-top:1px solid #DDD2C6}' +
    '.mva li::before{content:counter(s);width:28px;height:28px;border-radius:50%;background:#EADFDE;color:#7D5E68;display:grid;place-items:center;font-size:.9rem;font-weight:500}' +
    '.mva b{font-weight:500;color:#2E2529}.mva-ico{display:inline-grid;place-items:center;vertical-align:-3px;color:#7D5E68}' +
    '.mva-row{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}' +
    '.mva-row button{flex:1;font:inherit;border-radius:999px;padding:.85em 1.3em;cursor:pointer;border:1px solid #DDD2C6;background:#FBF8F4;color:#2E2529}' +
    '.mva-row .go{background:#7D5E68;border-color:#7D5E68;color:#FBF5F7}' +
    '.mva small{display:block;margin-top:12px;color:#7D7076;font-size:.85rem}';

  var box = null;
  function build() {
    var t = T[lang()];
    var steps = (ios ? t.ios : t.other).map(function (s) { return '<li><span>' + s + '</span></li>'; }).join('');
    var showBtn = !!deferred && !ios;
    var hint = ios && !/Safari/.test(ua) ? '<small>' + t.iosHint + '</small>' : '';
    box.innerHTML = '<div class="mva-card" role="dialog" aria-modal="true" aria-labelledby="mva-t">' +
      '<div class="mva-head"><img src="/img/movecia/movecia-app-192.png" alt=""><h2 id="mva-t">' + t.title + '</h2></div>' +
      '<p>' + t.lead + '</p>' + (showBtn ? '' : '<ol>' + steps + '</ol>') + hint +
      '<div class="mva-row">' + (showBtn ? '<button type="button" class="go" data-mva="go">' + t.btn + '</button>' : '') +
      '<button type="button" data-mva="x">' + t.done + '</button></div></div>';
  }
  function open() {
    if (!box) {
      var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
      box = document.createElement('div'); box.className = 'mva'; box.hidden = true; document.body.appendChild(box);
      box.addEventListener('click', function (e) {
        var a = e.target.closest('[data-mva]');
        if (e.target === box || (a && a.dataset.mva === 'x')) return close();
        if (a && a.dataset.mva === 'go' && deferred) { deferred.prompt(); deferred.userChoice.then(function () { deferred = null; close(); }); }
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box && !box.hidden) close(); });
    }
    // Menü (falls offen) schließen, damit die Anleitung nicht dahinter liegt
    var x = document.querySelector('.mvm.open .mvm-x'); if (x) x.click();
    build(); box.hidden = false; requestAnimationFrame(function () { box.classList.add('open'); });
    var b = box.querySelector('button'); if (b) b.focus();
  }
  function close() { if (!box) return; box.classList.remove('open'); setTimeout(function () { if (!box.classList.contains('open')) box.hidden = true; }, 240); }

  // Ein Stil für alle Seiten, auch ohne Anleitung: Knöpfe in der installierten App ausblenden
  var s0 = document.createElement('style'); s0.textContent = '.mv-standalone [data-mv-app]{display:none!important}'; document.head.appendChild(s0);
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-mv-app]');
    if (!a) return; e.preventDefault(); open();
  });
  window.mvAppInstall = open;
  if (/[?&]install=1/.test(location.search) && !standalone) window.addEventListener('load', function () { setTimeout(open, 400); });
})();

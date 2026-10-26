/* Movecia · zartes Wasserzeichen über Mitglieder-Videos (Vivien, 26.10.2026)
   Kleine Affirmation + persönlicher Zugangs-Code (kein Name, keine E-Mail). Wandert langsam über das Bild,
   damit es sich nicht einfach wegschneiden lässt. Steht auf jedem Bildschirmfoto mit drauf.
   <script src="/wildhearts/assets/wasserzeichen.js" data-code="MV-XXXXXX" data-lang="de" defer></script> */
(function () {
  var me = document.currentScript;
  var code = (me && me.dataset.code) || '';
  if (!code) return;
  var en = (me.dataset.lang || document.documentElement.lang || '').indexOf('en') === 0;
  var AFF = en ? ['You are allowed to take your time', 'Your body is your home', 'You are enough, just as you are', 'Breathe, you are here', 'Gentle is strong, too', 'This moment belongs to you', 'Every movement counts', 'You may soften']
               : ['Du darfst dir Zeit lassen', 'Dein Körper ist dein Zuhause', 'Du bist genug, so wie du bist', 'Atme, du bist da', 'Sanft ist auch stark', 'Dieser Moment gehört dir', 'Jede Bewegung zählt', 'Du darfst weich werden'];
  var label = en ? 'Personal access' : 'Persönlicher Zugang';
  var SEL = 'iframe[src*="mediadelivery.net"], .mvf-stage, video[controls]';
  var POS = [[6, 7], [58, 9], [8, 70], [55, 74], [30, 40], [62, 44], [10, 38]];
  var all = [];
  var s = document.createElement('style');
  s.textContent = '.mvwm{position:absolute;z-index:6;pointer-events:none;user-select:none;-webkit-user-select:none;color:rgba(251,245,247,.62);' +
    'font:400 12px/1.35 "Hanken Grotesk",system-ui,sans-serif;letter-spacing:.02em;text-shadow:0 1px 3px rgba(46,37,41,.55);transition:left 2.5s ease,top 2.5s ease,opacity 1.2s ease;white-space:nowrap}' +
    '.mvwm i{display:block;font:italic 400 15px/1.2 "Cormorant Garamond",Georgia,serif;color:rgba(251,245,247,.75)}' +
    '@media(max-width:600px){.mvwm{font-size:10px}.mvwm i{font-size:13px}}' +
    '.mvwm-host{position:relative}' +
    '.mvwm-full{position:fixed!important;inset:0!important;z-index:99990!important;width:100vw!important;height:100vh!important;max-width:none!important;border-radius:0!important;background:#000!important;margin:0!important}' +
    '.mvwm-full iframe,.mvwm-full video{width:100%!important;height:100%!important;aspect-ratio:auto!important;border-radius:0!important}' +
    '.mvwm-x{position:absolute;z-index:7;right:12px;top:12px;border:0;border-radius:999px;background:rgba(46,37,41,.55);color:#FBF5F7;font:500 14px system-ui,sans-serif;padding:.5em .9em;cursor:pointer;display:none}' +
    '.mvwm-full .mvwm-x{display:block}';
  document.head.appendChild(s);
  // Texte im Mitgliederbereich nicht markieren/kopieren (Eingabefelder wie das Journal bleiben frei)
  var t = document.createElement('style');
  t.textContent = 'body{-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}input,textarea,select,[contenteditable],[contenteditable] *{-webkit-user-select:text;user-select:text;-webkit-touch-callout:default}';
  document.head.appendChild(t);
  var free = function (e) { var n = e.target; return n && n.closest && n.closest('input,textarea,select,[contenteditable]'); };
  ['copy', 'cut'].forEach(function (ev) { document.addEventListener(ev, function (e) { if (!free(e)) e.preventDefault(); }, true); });
  document.addEventListener('selectstart', function (e) { if (!free(e)) e.preventDefault(); }, true);

  function attach(el) {
    if (el.__mvwm) return;
    el.__mvwm = true;
    var host = el.matches('.mvf-stage') ? el : el.parentElement;
    if (!host) return;
    if (getComputedStyle(host).position === 'static') host.classList.add('mvwm-host');
    var w = document.createElement('div');
    w.className = 'mvwm'; w.setAttribute('aria-hidden', 'true');
    host.appendChild(w);
    var x = document.createElement('button');
    x.type = 'button'; x.className = 'mvwm-x'; x.textContent = en ? '✕ Close full screen' : '✕ Vollbild schließen';
    x.onclick = function () { host.classList.remove('mvwm-full'); };
    host.appendChild(x);
    var item = { el: el, host: host, w: w, i: Math.floor(Math.random() * POS.length), a: Math.floor(Math.random() * AFF.length) };
    all.push(item);
    place(item);
  }
  function place(it) {
    var hostR = it.host.getBoundingClientRect(), r = it.el.getBoundingClientRect();
    var ox = it.el === it.host ? 0 : r.left - hostR.left, oy = it.el === it.host ? 0 : r.top - hostR.top;
    var p = POS[it.i % POS.length];
    it.w.innerHTML = '<i>' + AFF[it.a % AFF.length] + '</i>' + label + ' · ' + code;
    var maxX = Math.max(0, r.width - it.w.offsetWidth - 8), maxY = Math.max(0, r.height - it.w.offsetHeight - 8);
    it.w.style.left = Math.round(ox + Math.min(maxX, r.width * p[0] / 100)) + 'px';
    it.w.style.top = Math.round(oy + Math.min(maxY, r.height * p[1] / 100)) + 'px';
    it.w.style.display = r.width > 40 ? '' : 'none';
  }
  function scan(root) { (root.querySelectorAll ? root : document).querySelectorAll(SEL).forEach(attach); if (root.matches && root.matches(SEL)) attach(root); }
  function tick() { all = all.filter(function (it) { return document.contains(it.el); }); all.forEach(function (it) { it.i++; if (it.i % 3 === 0) it.a++; place(it); }); }
  // Vollbild des Players: statt des echten Vollbilds (in dem das Wasserzeichen fehlen würde) ein Vollbild mit Wasserzeichen
  document.addEventListener('fullscreenchange', function () {
    var f = document.fullscreenElement;
    if (!f) return;
    var it = all.find(function (x) { return x.el === f; });
    if (!it) return;
    document.exitFullscreen().catch(function () {});
    it.host.classList.add('mvwm-full');
    setTimeout(function () { place(it); }, 300);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.querySelectorAll('.mvwm-full').forEach(function (h) { h.classList.remove('mvwm-full'); }); });
  // Bildschirmfoto-Tasten am Computer (Druck-Taste, Mac Cmd+Shift+3/4/5, Windows Win+Shift+S): liebevolles Fenster.
  // Am Handy meldet das System Bildschirmfotos keiner Website, dort schützt das Wasserzeichen.
  var nm = (me.dataset.name || '').replace(/[<>&"]/g, '');
  var box = null;
  function hint() {
    document.documentElement.classList.add('mvwm-hide');
    if (!box) {
      var st = document.createElement('style');
      st.textContent = '.mvwm-hide body>*:not(.mvwm-pop){filter:blur(26px)!important}' +
        '.mvwm-pop{position:fixed;inset:0;z-index:99995;display:grid;place-items:center;padding:24px;background:rgba(46,37,41,.6)}' +
        '.mvwm-pop>div{max-width:28rem;background:#F2ECE4;color:#2E2529;border-radius:28px;padding:2.1rem 1.8rem;text-align:center;font:400 1.05rem/1.6 "Hanken Grotesk",system-ui,sans-serif;box-shadow:0 20px 60px rgba(0,0,0,.25)}' +
        '.mvwm-pop img{width:56px;height:56px}.mvwm-pop h2{font:italic 400 1.8rem/1.2 "Cormorant Garamond",Georgia,serif;color:#7D5E68;margin:.6rem 0 .6rem}' +
        '.mvwm-pop .aff{font:italic 400 1.3rem/1.3 "Cormorant Garamond",Georgia,serif;color:#7D5E68;margin-top:1rem}' +
        '.mvwm-pop button{margin-top:1.4rem;font:500 1rem "Hanken Grotesk",system-ui,sans-serif;border:0;border-radius:999px;background:#7D5E68;color:#FBF5F7;padding:.8em 1.6em;cursor:pointer}';
      document.head.appendChild(st);
      box = document.createElement('div'); box.className = 'mvwm-pop'; box.setAttribute('role', 'dialog');
    }
    var aff = AFF[Math.floor(Math.random() * AFF.length)];
    box.innerHTML = '<div><img src="/img/movecia/movecia-zeichen.svg" alt=""><h2>' + (en ? (nm ? 'Dear ' + nm + ',' : 'Dear you,') : (nm ? 'Liebe ' + nm + ',' : 'Liebe du,')) + '</h2><p>' +
      (en ? 'this content is meant just for you, so it can’t be shared or recorded. Thank you for keeping it a protected space.' : 'dieser Inhalt ist nur für dich bestimmt und kann deshalb nicht geteilt oder aufgenommen werden. Danke, dass du ihn als geschützten Raum bewahrst.') +
      '</p><p class="aff">' + aff + ' ♡</p><button type="button">' + (en ? 'Back to my practice' : 'Zurück zu mir') + '</button></div>';
    document.body.appendChild(box);
    document.querySelectorAll('video').forEach(function (v) { try { v.pause(); } catch (e) {} });
    box.querySelector('button').onclick = function () { box.remove(); document.documentElement.classList.remove('mvwm-hide'); };
  }
  document.addEventListener('keydown', function (e) {
    var k = e.key || '', c = e.code || '';
    if (k === 'PrintScreen' || c === 'PrintScreen' || (e.metaKey && e.shiftKey && /^(Digit[3456]|KeyS)$/.test(c)) || (e.ctrlKey && e.shiftKey && c === 'KeyS' && e.metaKey)) hint();
  }, true);
  document.addEventListener('keyup', function (e) {
    if (e.key === 'PrintScreen' || e.code === 'PrintScreen') { hint(); try { navigator.clipboard.writeText(en ? 'This content is meant just for you. ♡ Movecia' : 'Dieser Inhalt ist nur für dich bestimmt. ♡ Movecia'); } catch (x) {} }
  }, true);
  window.addEventListener('resize', function () { all.forEach(place); });
  function go() {
    scan(document);
    if ('MutationObserver' in window) new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) scan(n); }); }); }).observe(document.body, { childList: true, subtree: true });
    setInterval(tick, 20000);
    setInterval(function () { all.forEach(place); }, 2000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();

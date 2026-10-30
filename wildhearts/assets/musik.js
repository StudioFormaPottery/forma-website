/* Leise Hintergrundmusik auf moveciamovement.com (Vivien, 28.10.2026).
   Kleiner Kiesel-Knopf unten links. Startet nur auf Tippen (Browser erlauben keinen Ton ohne Klick),
   leise (25 %), sanft ein- und ausgeblendet. Merkt sich an/aus, Lied und Stelle über die Seiten hinweg.
   Lieder kommen aus api/mv-musik.php (Ordner „🌐 Website“ in der Verwaltung). */
(function () {
  if (window.__mvMusik) return; window.__mvMusik = 1;
  // Nicht dort, wo eigene Videos/Stimme laufen
  if (/^\/(mitglieder|natal\/mitglied|natal\/raum|academy\/mitglieder|gathering|unfold\/call)/.test(location.pathname)) return;
  var KEY = 'mv-mu', VOL = 0.25, st = {};
  try { st = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) {}
  function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }
  var en = function () { return document.documentElement.dataset.l === 'en' || document.documentElement.lang === 'en'; };

  var css = '.mvmu{position:fixed;left:16px;bottom:calc(16px + env(safe-area-inset-bottom));z-index:60;display:flex;align-items:center;gap:8px;font-family:"Hanken Grotesk",system-ui,sans-serif;pointer-events:none}'
    + '.mvmu>*{pointer-events:auto}'
    + '.mvmu-b{position:relative;width:50px;height:50px;border:0;padding:0;cursor:pointer;background:none;color:#F7F1EE}'
    + '.mvmu-b .bg{position:absolute;inset:0;background:#4A3040;border-radius:52% 48% 44% 56% / 48% 54% 46% 52%;box-shadow:0 8px 22px rgba(74,58,65,.28);transition:transform .4s,border-radius 1.2s}'
    + '.mvmu-b:hover .bg{transform:scale(1.06);border-radius:46% 54% 52% 48% / 54% 46% 54% 46%}'
    + '.mvmu-b .ring{position:absolute;inset:-5px -6px -4px -3px;border:1.3px solid #4A3040;border-radius:48% 52% 56% 44% / 52% 44% 56% 48%;opacity:.55;translate:4px -3px;pointer-events:none}'
    + '.mvmu-b svg{position:relative;width:22px;height:22px}'
    + '.mvmu-b .eq{position:relative;display:none;gap:3px;align-items:flex-end;height:18px}'
    + '.mvmu-b .eq i{width:3px;background:#F7F1EE;border-radius:2px;animation:mvmuEq 1.4s ease-in-out infinite}'
    + '.mvmu-b .eq i:nth-child(2){animation-delay:-.5s}.mvmu-b .eq i:nth-child(3){animation-delay:-.9s}'
    + '@keyframes mvmuEq{0%,100%{height:5px}50%{height:17px}}'
    + '.mvmu.on .mvmu-b svg{display:none}.mvmu.on .mvmu-b .eq{display:flex}'
    + '.mvmu-p{display:flex;align-items:center;gap:6px;max-width:0;overflow:hidden;opacity:0;padding:0;background:rgba(251,248,244,.94);border:1px solid #D3C9BC;border-radius:999px;color:#2E1D27;font-size:12.5px;white-space:nowrap;transition:max-width .5s,opacity .4s,padding .4s;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}'
    + '.mvmu.on .mvmu-p,.mvmu.hint .mvmu-p{max-width:min(260px,calc(100vw - 100px));opacity:1;padding:6px 6px 6px 14px}'
    + '.mvmu-t{overflow:hidden;text-overflow:ellipsis;max-width:170px}'
    + '.mvmu-n{border:0;background:#E4DCD1;color:#2E1D27;width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:12px;flex:none}'
    + '.mvmu.hint .mvmu-n{display:none}'
    + '.mvmu.hint .mvmu-b .ring{animation:mvmuPulse 2s ease-in-out infinite}'
    + '@keyframes mvmuPulse{50%{opacity:.15;scale:1.12}}'
    + '.mvmu.intro{animation:mvmuIn .7s cubic-bezier(.3,1.3,.5,1) both}'
    + '@keyframes mvmuIn{from{opacity:0;transform:translateY(24px)}}'
    + '@media(prefers-reduced-motion:reduce){.mvmu-b .eq i,.mvmu.hint .mvmu-b .ring{animation:none}.mvmu-b .eq i{height:12px}}'
    + '@media print{.mvmu{display:none}}';
  var s = document.createElement('style'); s.textContent = css; document.head.appendChild(s);

  var box = document.createElement('div'); box.className = 'mvmu';
  box.innerHTML = '<button class="mvmu-b" type="button"><span class="ring"></span><span class="bg"></span>'
    + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/></svg>'
    + '<span class="eq" aria-hidden="true"><i></i><i></i><i></i></span></button>'
    + '<div class="mvmu-p"><span class="mvmu-t"></span><button class="mvmu-n" type="button">⏭</button></div>';
  var btn = box.querySelector('.mvmu-b'), tEl = box.querySelector('.mvmu-t'), nx = box.querySelector('.mvmu-n');
  var list = [], a = new Audio(), fadeT = 0, ready = false;
  a.preload = 'none'; a.volume = 0;
  a.setAttribute('controlsList', 'nodownload');

  function label() {
    var on = box.classList.contains('on');
    btn.setAttribute('aria-label', on ? (en() ? 'Pause music' : 'Musik pausieren') : (en() ? 'Play soft music' : 'Leise Musik abspielen'));
    btn.title = btn.getAttribute('aria-label');
    nx.setAttribute('aria-label', en() ? 'Next song' : 'Nächstes Lied');
    if (box.classList.contains('hint')) tEl.textContent = en() ? 'Soft music? Tap to listen' : 'Leise Musik? Hier antippen';
  }
  function fade(to, ms, done) {
    clearInterval(fadeT); var from = a.volume, t0 = Date.now();
    fadeT = setInterval(function () {
      var k = Math.min(1, (Date.now() - t0) / ms); a.volume = Math.max(0, Math.min(1, from + (to - from) * k));
      if (k >= 1) { clearInterval(fadeT); done && done(); }
    }, 50);
  }
  function title(i) { var t = (list[i] || {}).t || ''; return t; }
  function load(i, at) {
    st.i = (i + list.length) % list.length; save();
    a.src = '/api/mv-musik.php?s=' + list[st.i].id;
    if (at) a.addEventListener('loadedmetadata', function f() { a.removeEventListener('loadedmetadata', f); try { a.currentTime = at; } catch (e) {} });
    tEl.textContent = title(st.i);
  }
  function play() {
    if (!list.length) return Promise.reject();
    if (!a.src) load(st.i || 0, st.t || 0);
    return a.play().then(function () {
      box.classList.remove('hint'); box.classList.add('on'); tEl.textContent = title(st.i);
      st.on = 1; save(); fade(VOL, 2000); label();
    });
  }
  function pause() {
    st.on = 0; st.t = a.currentTime || 0; save();
    box.classList.remove('on', 'hint'); label();
    fade(0, 600, function () { a.pause(); });
  }
  btn.addEventListener('click', function () {
    if (box.classList.contains('on')) pause();
    else play().catch(function () {});
  });
  tEl.addEventListener('click', function () { if (box.classList.contains('hint')) play().catch(function () {}); });
  tEl.style.cursor = 'pointer';
  nx.addEventListener('click', function () {
    fade(0, 400, function () { load(st.i + 1, 0); a.play().then(function () { fade(VOL, 1500); }).catch(function () {}); });
  });
  a.addEventListener('ended', function () { load(st.i + 1, 0); a.play().catch(function () {}); });
  a.addEventListener('error', function () { if (list.length > 1 && box.classList.contains('on')) { load(st.i + 1, 0); a.play().catch(function () {}); } });
  setInterval(function () { if (box.classList.contains('on')) { st.t = a.currentTime || 0; save(); } }, 2000);
  window.addEventListener('pagehide', function () { if (box.classList.contains('on')) { st.t = a.currentTime || 0; save(); } });
  // Läuft ein Video mit Ton auf der Seite, macht die Musik Platz
  document.addEventListener('play', function (e) {
    var v = e.target; if (v === a || v.tagName !== 'VIDEO' || v.muted) return;
    if (box.classList.contains('on')) pause();
  }, true);
  document.addEventListener('mvlang', label);

  fetch('/api/mv-musik.php').then(function (r) { return r.json(); }).then(function (d) {
    list = (d && d.list) || []; if (!list.length) return;
    // Reihenfolge gemischt, aber pro Besuch gleich
    if (!st.order || st.order.length !== list.length) {
      st.order = list.map(function (_, i) { return i; }).sort(function () { return Math.random() - .5; }); st.i = 0; st.t = 0; save();
    }
    list = st.order.map(function (k) { return list[k]; }).filter(Boolean);
    if (st.i >= list.length) st.i = 0;
    // Über der Fußzeile stehen bleiben, damit der Name unten lesbar bleibt (Vivien, 28.10.2026)
    var foot = null;
    function lift() {
      if (!foot) foot = document.querySelector('footer, .foot');
      var b = 16;
      if (foot) { var top = foot.getBoundingClientRect().top; if (top < innerHeight) b = innerHeight - top + 16; }
      box.style.bottom = 'calc(' + Math.round(b) + 'px + env(safe-area-inset-bottom))';
    }
    var mount = function () { document.body.appendChild(box); label(); ready = true;
      lift(); addEventListener('scroll', lift, { passive: true }); addEventListener('resize', lift);
      if (st.on) { play().catch(function () { box.classList.add('hint'); label(); }); return; }
      // Am Anfang als kleines Pop-up unten links zeigen (einmal pro Besuch), damit man den Player sieht
      var seen = false; try { seen = sessionStorage.getItem('mv-mu-intro') === '1'; sessionStorage.setItem('mv-mu-intro', '1'); } catch (e) {}
      if (seen) return;
      box.classList.add('intro', 'hint'); label();
      var hov = false; box.addEventListener('mouseenter', function () { hov = true; }); box.addEventListener('mouseleave', function () { hov = false; });
      var close = function () { if (hov) return setTimeout(close, 1500); if (!box.classList.contains('on')) { box.classList.remove('hint'); label(); } };
      setTimeout(close, 9000);
    };
    if (document.body) mount(); else document.addEventListener('DOMContentLoaded', mount);
  }).catch(function () {});
})();

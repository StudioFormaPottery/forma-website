/* Titelkarte für Talking Heads (Vivien, 30.10.2026): am Anfang jedes Talking Heads etwa 6 Sekunden
   Thema, darunter „Vivien“ und wer sie ist. Gleiche Welle wie der Übungsname im Flow-Player.
   Nur über dem Player auf der Website, nie ins Video eingebrannt.
   Verwendung: <iframe data-mv-title='{"t":"Thema","r":"yoga|natal","l":"de|en"}' …> (Bunny-Embed)
   oder MvTitle(iframe, {t, r, l}). Vor dem Abspielen liegt sie als Titelbild über dem Vorschaubild,
   beim Start bleibt sie 6 Sekunden und blendet dann aus. */
(function () {
  var ROLE = {
    yoga: { de: 'Yogalehrerin (500+ Std.) · Somatic & Mindful Movement Teacher', en: '500+ hr Yoga Teacher · Somatic & Mindful Movement Teacher' },
    natal: { de: 'Zertifizierte Prä- & Postnatal-Trainerin · Mindful & Somatic Movement', en: 'Certified Pre- & Postnatal Coach · Mindful & Somatic Movement' }
  };
  var css = '.mvt-wrap{position:relative;container-type:inline-size}' +
    '.mvt{position:absolute;left:0;bottom:0;width:70cqw;height:33cqw;max-height:270px;color:#F7F1EE;pointer-events:none;opacity:0;transform:translateY(14px);transition:opacity .8s ease,transform .9s cubic-bezier(.2,.8,.2,1);z-index:2;border-bottom-left-radius:inherit;overflow:hidden}' +
    '.mvt.on{opacity:1;transform:none}' +
    '.mvt svg{position:absolute;inset:0;width:100%;height:100%}' +
    '.mvt .in{position:absolute;left:max(16px,4cqw);right:16cqw;top:36%;bottom:7%;display:flex;align-items:center;gap:max(10px,2.2cqw)}' +
    '.mvt .in>div{min-width:0}' +
    '.mvt img{width:clamp(28px,6.6cqw,56px);height:clamp(28px,6.6cqw,56px);flex:none}' +
    '.mvt b{display:block;font-family:"Cormorant","Cormorant Garamond",Georgia,serif;font-weight:500;font-size:clamp(1.1rem,3.9cqw,2.2rem);line-height:1.04;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-wrap:balance}' +
    '.mvt .who{display:block;margin-top:max(5px,1.1cqw);padding-top:max(5px,1cqw);border-top:1px solid rgba(247,241,238,.32);font-family:"Hanken","Hanken Grotesk",system-ui,sans-serif}' +
    '.mvt .who i{font-style:normal;font-weight:500;font-size:clamp(.8rem,2.2cqw,1.15rem);letter-spacing:.04em}' +
    '.mvt .who small{display:block;font-size:clamp(.5rem,1.45cqw,.74rem);letter-spacing:.14em;text-transform:uppercase;opacity:.86;margin-top:2px;line-height:1.35}' +
    '@container (max-width:560px){.mvt{width:92cqw;height:42cqw}.mvt .in{left:12px;right:20cqw;top:38%;bottom:6%;gap:8px}.mvt img{width:22px;height:22px}.mvt b{font-size:clamp(.9rem,4.4cqw,1.15rem)}.mvt .who{margin-top:3px;padding-top:3px}.mvt .who i{font-size:.72rem}.mvt .who small{font-size:.46rem;letter-spacing:.08em}}' +
    '@media(prefers-reduced-motion:reduce){.mvt{transition:none}}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
  var WAVE = '<svg viewBox="0 0 620 240" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="mvtg" x1="0" x2="1"><stop offset="0" stop-color="#4A3040" stop-opacity=".94"/><stop offset=".62" stop-color="#4A3040" stop-opacity=".9"/><stop offset="1" stop-color="#4A3040" stop-opacity="0"/></linearGradient><linearGradient id="mvtl" x1="0" x2="1"><stop offset="0" stop-color="#B58C99" stop-opacity=".75"/><stop offset=".7" stop-color="#B58C99" stop-opacity=".5"/><stop offset="1" stop-color="#B58C99" stop-opacity="0"/></linearGradient></defs><path d="M0 64 C110 30 210 98 310 84 S470 66 540 150 C572 188 600 222 620 240 H0Z" fill="url(#mvtg)"/><path d="M0 46 C120 14 220 80 320 66 S480 48 560 138 C588 172 606 200 618 222" fill="none" stroke="url(#mvtl)" stroke-width="2"/></svg>';
  var esc = function (s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var pj = null;
  function playerjs(cb) {
    if (window.playerjs) return cb();
    if (!pj) { pj = []; var s = document.createElement('script'); s.src = 'https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js'; s.onload = function () { pj.forEach(function (f) { f(); }); }; document.head.appendChild(s); }
    pj.push(cb);
  }
  window.MvTitle = function (fr, o) {
    if (!fr || fr.dataset.mvtDone) return; fr.dataset.mvtDone = '1';
    var lg = o.l === 'en' ? 'en' : 'de', role = (ROLE[o.r] || ROLE.yoga)[lg];
    var box = fr.parentNode;
    if (!box || box === document.body) return;
    box.classList.add('mvt-wrap');
    var cs = getComputedStyle(box); if (cs.position === 'static') box.style.position = 'relative';
    var el = document.createElement('div'); el.className = 'mvt on'; el.setAttribute('aria-hidden', 'true');
    el.style.borderBottomLeftRadius = getComputedStyle(fr).borderBottomLeftRadius;
    el.innerHTML = WAVE + '<div class="in"><img src="/img/movecia/movecia-zeichen-hell.svg" alt="" draggable="false"><div><b>' + esc(o.t) + '</b><span class="who"><i>Vivien</i><small>' + esc(role) + '</small></span></div></div>';
    box.appendChild(el);
    var tm = null, gone = false;
    function hide() { gone = true; el.classList.remove('on'); }
    function onPlay(sec) { if (gone) return; if (sec > 7) return hide(); if (!tm) tm = setTimeout(hide, Math.max(1500, 6000 - sec * 1000)); }
    playerjs(function () {
      try {
        var pl = new window.playerjs.Player(fr);
        pl.on('ready', function () {
          pl.on('play', function () { pl.getCurrentTime(function (s) { onPlay(s || 0); }); });
          pl.on('timeupdate', function (t) { if (!tm) onPlay(t.seconds || 0); });
        });
      } catch (e) {}
    });
  };
  function auto() { document.querySelectorAll('iframe[data-mv-title]').forEach(function (f) { try { window.MvTitle(f, JSON.parse(f.dataset.mvTitle)); } catch (e) {} }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', auto); else auto();
})();

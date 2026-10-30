/* Movecia · „Dieser Inhalt ist nur für dich“ (Vivien, 26.10.2026; Wasserzeichen auf ihren Wunsch wieder entfernt)
   Läuft auf allen Movecia-Seiten (geladen über schutz.js). Drückt jemand am Computer die Bildschirmfoto-Tasten
   (Druck-Taste, Mac Cmd+Shift+3/4/5, Windows Win+Shift+S), wird die Seite verschwommen und ein liebevolles Fenster erscheint.
   Am Handy melden iPhone und Android Bildschirmfotos keiner Website.
   Im Mitgliederbereich zusätzlich mit Vornamen und ohne Kopieren der Texte:
   <script src="/wildhearts/assets/wasserzeichen.js" data-name="Anna" data-nocopy="1" data-lang="de" defer></script> */
(function () {
  if (window.__mvNurFuerDich) return;
  window.__mvNurFuerDich = true;
  function cfg() {
    var t = document.querySelector('script[data-nocopy]');
    return { name: t ? (t.dataset.name || '') : '', lang: t ? (t.dataset.lang || '') : '', nocopy: !!t };
  }
  function isEn() { var c = cfg(); return (c.lang || document.documentElement.lang || '').indexOf('en') === 0; }
  var AFF_DE = ['Du darfst dir Zeit lassen', 'Dein Körper ist dein Zuhause', 'Du bist gut so, wie du bist', 'Atme, du bist da', 'Sanft ist auch stark', 'Dieser Moment gehört dir', 'Jede Bewegung zählt', 'Du darfst weich werden'];
  var AFF_EN = ['You are allowed to take your time', 'Your body is your home', 'You are enough, just as you are', 'Breathe, you are here', 'Gentle is strong, too', 'This moment belongs to you', 'Every movement counts', 'You are allowed to soften'];

  function nocopy() {
    if (!cfg().nocopy || document.getElementById('mvnc')) return;
    var t = document.createElement('style'); t.id = 'mvnc';
    t.textContent = 'body{-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}input,textarea,select,[contenteditable],[contenteditable] *{-webkit-user-select:text;user-select:text;-webkit-touch-callout:default}';
    document.head.appendChild(t);
    var free = function (e) { var n = e.target; return n && n.closest && n.closest('input,textarea,select,[contenteditable]'); };
    ['copy', 'cut'].forEach(function (ev) { document.addEventListener(ev, function (e) { if (!free(e)) e.preventDefault(); }, true); });
    document.addEventListener('selectstart', function (e) { if (!free(e)) e.preventDefault(); }, true);
  }

  var box = null;
  function hint() {
    var en = isEn(), nm = cfg().name.replace(/[<>&"]/g, '');
    var aff = (en ? AFF_EN : AFF_DE)[Math.floor(Math.random() * AFF_DE.length)];
    document.documentElement.classList.add('mvnfd-hide');
    if (!box) {
      var st = document.createElement('style');
      st.textContent = '.mvnfd-hide body>*:not(.mvnfd){filter:blur(26px)!important}' +
        '.mvnfd{position:fixed;inset:0;z-index:99995;display:grid;place-items:center;padding:24px;background:rgba(46,37,41,.6)}' +
        '.mvnfd>div{max-width:28rem;background:#F5F1EB;color:#2A2026;border-radius:28px;padding:2.1rem 1.8rem;text-align:center;font:400 1.05rem/1.6 "Hanken Grotesk",system-ui,sans-serif;box-shadow:0 20px 60px rgba(0,0,0,.25)}' +
        '.mvnfd img{width:56px;height:56px}.mvnfd h2{font:italic 400 1.8rem/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3040;margin:.6rem 0 .6rem}' +
        '.mvnfd .aff{font:italic 400 1.3rem/1.3 "Cormorant Garamond",Georgia,serif;color:#4A3040;margin-top:1rem}' +
        '.mvnfd button{margin-top:1.4rem;font:500 1rem "Hanken Grotesk",system-ui,sans-serif;border:0;border-radius:999px;background:#4A3040;color:#F7F1EE;padding:.8em 1.6em;cursor:pointer}';
      document.head.appendChild(st);
      box = document.createElement('div'); box.className = 'mvnfd'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    }
    box.innerHTML = '<div><img src="/img/movecia/movecia-zeichen.svg" alt=""><h2>' + (en ? (nm ? 'Dear ' + nm + ',' : 'Hi lovely,') : (nm ? 'Liebe ' + nm + ',' : 'Hallo, meine Liebe,')) + '</h2><p>' +
      (en ? 'this content is meant just for you, so it can’t be shared or recorded. Thank you for keeping it a protected space.' : 'dieser Inhalt ist nur für dich bestimmt und kann deshalb nicht geteilt oder aufgenommen werden. Danke, dass du ihn für dich behältst.') +
      '</p><p class="aff">' + aff + ' ♡</p><button type="button">' + (en ? 'Back to me' : 'Zurück zu mir') + '</button></div>';
    document.body.appendChild(box);
    document.querySelectorAll('video').forEach(function (v) { try { if (v.controls) v.pause(); } catch (e) {} });
    box.querySelector('button').onclick = function () { box.remove(); document.documentElement.classList.remove('mvnfd-hide'); };
  }
  function shot(e) { var c = e.code || ''; return e.key === 'PrintScreen' || c === 'PrintScreen' || (e.metaKey && e.shiftKey && /^(Digit[3456]|KeyS)$/.test(c)); }
  document.addEventListener('keydown', function (e) { if (shot(e)) hint(); }, true);
  document.addEventListener('keyup', function (e) {
    if (e.key === 'PrintScreen' || e.code === 'PrintScreen') { hint(); try { navigator.clipboard.writeText(isEn() ? 'This content is meant just for you. ♡ Movecia' : 'Dieser Inhalt ist nur für dich bestimmt. ♡ Movecia'); } catch (x) {} }
  }, true);
  window.mvNurFuerDich = hint; // zum Ausprobieren: ?nurfuerdich=1
  function go() { nocopy(); if (/[?&]nurfuerdich=1/.test(location.search)) hint(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();

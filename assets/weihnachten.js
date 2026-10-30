/* Weihnachtszeit auf forma-pottery.com (Vivien, 30.10.2026)
   Vom 2.11. bis 6.1. (Deko-Modus „weihnachten“):
   - weihnachtliche Icons fliegen sanft durchs Bild (wie die Fledermäuse zu Halloween), dazu ein paar Schneeflocken
   - der Adventkalender zum Selbermachen rückt bis 30.11. unter die Kurse
   - das Christkind versteckt sich immer wieder am Rand und schaut kurz herein (Antippen: kleiner Gruß)
   Am 24.12. fliegt das Christkind mit einem Stern am Faden wie mit einem Luftballon über die Seite,
   dazu „Stille Nacht“ als Spieluhr (Franz Xaver Gruber, 1818, gemeinfrei, im Browser erzeugt).
   Musik startet erst nach dem ersten Tippen/Klicken (Browser erlauben vorher keinen Ton), Antippen des Christkinds = leise/laut.
   Vorschau: /?deko=weihnachten, /?deko=weihnachten&christkind=24 */
(function () {
  var started = false;
  var Q = new URLSearchParams(location.search);
  var calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var now = new Date(), M = now.getMonth() + 1, D = now.getDate();
  var heiligabend = (M === 12 && D === 24) || Q.get('christkind') === '24';
  var adventZeit = (M === 11 && D >= 2) || Q.get('deko') === 'weihnachten'; // Adventkalender nach oben (bis 30.11.)

  var css = ''
    + '.xm-fx{position:fixed;inset:0;pointer-events:none;z-index:45;overflow:hidden}'
    + '.xm-fly{position:absolute;left:0;animation:xmfly linear infinite;will-change:transform}'
    + '.xm-fly.rev{animation-name:xmflyrev}'
    + '.xm-fly i{display:block;width:100%;height:100%;animation:xmbob 3.2s ease-in-out infinite alternate}'
    + '.xm-fly svg{width:100%;height:100%;display:block;overflow:visible;filter:drop-shadow(0 4px 8px rgba(103,48,30,.18))}'
    + '.xm-fly.spin svg{animation:xmspin 9s linear infinite}'
    + '@keyframes xmfly{from{transform:translateX(-12vw)}to{transform:translateX(112vw)}}'
    + '@keyframes xmflyrev{from{transform:translateX(112vw)}to{transform:translateX(-12vw)}}'
    + '@keyframes xmbob{from{transform:translateY(-30px) rotate(-10deg)}to{transform:translateY(30px) rotate(10deg)}}'
    + '@keyframes xmspin{to{transform:rotate(360deg)}}'
    + '.xm-flake{position:absolute;top:-30px;animation:xmfall linear infinite;opacity:.75}'
    + '.xm-flake svg{width:100%;height:100%;display:block}'
    + '@keyframes xmfall{0%{transform:translate(0,-20px) rotate(0)}50%{transform:translate(26px,52vh) rotate(140deg)}100%{transform:translate(-10px,108vh) rotate(280deg)}}'
    /* Adventkalender unter den Kursen */
    + '.xm-up{background:var(--paper,#F6EEE6);padding:clamp(10px,2vw,24px) 0 clamp(40px,5vw,70px);position:relative}'
    + '.xm-up .advent{margin-top:0}'
    /* Christkind versteckt sich */
    + '.ck-peek{position:fixed;z-index:60;width:110px;height:132px;pointer-events:auto;cursor:pointer;transition:transform 1.1s cubic-bezier(.3,1.4,.4,1);-webkit-tap-highlight-color:transparent}'
    + '.ck-peek svg{width:100%;height:100%;display:block;overflow:visible;filter:drop-shadow(0 6px 14px rgba(103,48,30,.25))}'
    + '.ck-peek.r{right:0;transform:translateX(110%) rotate(-14deg) scaleX(-1)}.ck-peek.r.in{transform:translateX(38%) rotate(-14deg) scaleX(-1)}'
    + '.ck-peek.l{left:0;transform:translateX(-110%) rotate(14deg)}.ck-peek.l.in{transform:translateX(-38%) rotate(14deg)}'
    + '.ck-peek.b{bottom:0;transform:translateY(110%)}.ck-peek.b.in{transform:translateY(46%)}'
    + '.ck-peek .ck-arm{transform-origin:66px 134px;transform-box:view-box;animation:ckwave .7s ease-in-out infinite alternate}'
    + '@keyframes ckwave{from{transform:rotate(-8deg)}to{transform:rotate(16deg)}}'
    + '.ck-say{position:fixed;z-index:61;max-width:230px;background:#FBF1EA;color:#33211B;border-radius:18px;padding:12px 15px;font-size:15px;line-height:1.4;box-shadow:0 12px 30px rgba(51,33,27,.22);opacity:0;transform:translateY(6px) scale(.96);transition:opacity .35s,transform .35s;pointer-events:none}'
    + '.ck-say.on{opacity:1;transform:none}'
    + '.ck-say b{display:block;font-family:var(--display,inherit);font-weight:600;color:#8C2C1D;margin-bottom:2px}'
    /* 24.12.: Christkind fliegt mit Stern */
    + '.ck-fly{position:fixed;left:0;top:0;z-index:62;width:170px;height:298px;cursor:pointer;will-change:transform;-webkit-tap-highlight-color:transparent}'
    + '.ck-fly svg{width:100%;height:100%;display:block;overflow:visible;filter:drop-shadow(0 8px 16px rgba(103,48,30,.28))}'
    + '.ck-fly .ck-star{transform-origin:84px 110px;transform-box:view-box;animation:ckstar 2.6s ease-in-out infinite alternate}'
    + '@keyframes ckstar{from{transform:rotate(-9deg)}to{transform:rotate(9deg)}}'
    + '.ck-fly .ck-wing{transform-box:view-box;transform-origin:54px 140px;animation:ckflap .5s ease-in-out infinite alternate}'
    + '.ck-fly .ck-wing.w2{transform-origin:66px 140px}'
    + '@keyframes ckflap{from{transform:scaleY(1) rotate(0)}to{transform:scaleY(.82) rotate(-6deg)}}'
    + '.ck-spark{position:fixed;z-index:61;width:14px;height:14px;pointer-events:none;color:#E2B65C;animation:ckspark 1.6s ease-out forwards}'
    + '.ck-spark svg{width:100%;height:100%;display:block}'
    + '@keyframes ckspark{from{opacity:1;transform:scale(1) translateY(0)}to{opacity:0;transform:scale(.3) translateY(26px) rotate(90deg)}}'
    + '.ck-note{position:fixed;z-index:63;left:50%;bottom:22px;transform:translateX(-50%);background:rgba(51,33,27,.86);color:#FBF1EA;border-radius:999px;padding:9px 16px;font-size:14px;opacity:0;transition:opacity .5s;pointer-events:none}'
    + '.ck-note.on{opacity:1}'
    /* Pop-ups im Advent: springen richtig heraus */
    + '.season.theme-advent[open]{animation:xmpop .9s cubic-bezier(.17,1.6,.35,1)}'
    + '@keyframes xmpop{0%{opacity:0;transform:translateY(60px) scale(.6) rotate(-3deg)}60%{opacity:1}100%{opacity:1;transform:none}}'
    + '.season.theme-advent{box-shadow:0 0 0 6px rgba(140,44,29,.18),0 30px 90px rgba(51,33,27,.45)}'
    + '.season.theme-advent .season-img img{animation:xmglow 3s ease-in-out infinite alternate}'
    + '@keyframes xmglow{from{filter:brightness(1)}to{filter:brightness(1.06) saturate(1.06)}}'
    + '.xm-bow{position:absolute;left:-6px;top:-8px;width:120px;height:120px;z-index:4;pointer-events:none;animation:xmbow 2.4s ease-in-out infinite alternate;transform-origin:30% 30%}'
    + '@keyframes xmbow{from{transform:rotate(-4deg)}to{transform:rotate(5deg) scale(1.04)}}'
    + '@media (max-width:700px){.ck-peek{width:82px;height:98px}.ck-fly{width:118px;height:206px}.xm-bow{width:84px;height:84px}}'
    + '@media (prefers-reduced-motion:reduce){.xm-fly,.xm-flake,.ck-peek .ck-arm,.ck-fly .ck-star,.ck-fly .ck-wing,.season.theme-advent[open],.xm-bow{animation:none}}';

  var C = { r: '#8C2C1D', b: '#DDBFA8', k: '#674B40', p: '#FBF1EA', g: '#E2B65C' };
  var STAR = '<path d="M32 3l8.6 17.5 19.4 2.8-14 13.6 3.3 19.3L32 47.1 14.7 56.2 18 36.9 4 23.3l19.4-2.8z" fill="' + C.g + '" stroke="#C9963F" stroke-width="2" stroke-linejoin="round"/><path d="M25 22l3 1M36 23l3-1" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".7"/>';
  var FLY = [
    '<svg viewBox="0 0 64 64">' + STAR + '</svg>',
    '<svg viewBox="0 0 64 64"><path d="M32 4v8" stroke="' + C.k + '" stroke-width="2"/><rect x="26" y="10" width="12" height="7" rx="2" fill="' + C.k + '"/><circle cx="32" cy="38" r="21" fill="' + C.r + '"/><path d="M12 34c7 5 33 5 40 0M13 44c7 5 31 5 38 0" stroke="' + C.p + '" stroke-width="2.5" fill="none" stroke-dasharray="1 5" stroke-linecap="round"/><circle cx="24" cy="29" r="4" fill="' + C.p + '" opacity=".45"/></svg>',
    '<svg viewBox="0 0 64 64"><rect x="10" y="26" width="44" height="32" rx="4" fill="' + C.b + '"/><rect x="7" y="18" width="50" height="11" rx="3" fill="' + C.r + '"/><rect x="28" y="18" width="8" height="40" fill="' + C.r + '"/><path d="M32 18c-6-10-18-10-16-2 1 4 10 3 16 2zM32 18c6-10 18-10 16-2-1 4-10 3-16 2z" fill="none" stroke="' + C.r + '" stroke-width="3.5"/></svg>',
    '<svg viewBox="0 0 64 64"><g stroke="' + C.b + '" stroke-width="3.4" stroke-linecap="round" fill="none"><path d="M32 6v52M9 19l46 26M9 45l46-26"/><path d="M26 10l6 6 6-6M26 54l6-6 6 6M10 28l8-2-2-8M54 36l-8 2 2 8M10 36l8 2-2 8M54 28l-8-2 2-8"/></g></svg>',
    '<svg viewBox="0 0 64 64"><path d="M32 4l14 18h-7l11 14h-7l11 14H10l11-14h-7l11-14h-7z" fill="' + C.k + '"/><rect x="28" y="50" width="8" height="10" fill="' + C.r + '"/><circle cx="26" cy="30" r="2.5" fill="' + C.r + '"/><circle cx="38" cy="40" r="2.5" fill="' + C.g + '"/><path d="M32 0l2 4 4 1-4 1-2 4-2-4-4-1 4-1z" fill="' + C.g + '"/></svg>'
  ];
  var FLAKE = '<svg viewBox="0 0 24 24"><g stroke="' + C.b + '" stroke-width="2" stroke-linecap="round"><path d="M12 2v20M3.3 7l17.4 10M3.3 17L20.7 7"/></g></svg>';
  var SPARK = '<svg viewBox="0 0 24 24"><path d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z" fill="currentColor"/></svg>';

  // Christkind (eigene Zeichnung): goldene Locken, Sternenkranz, weißes Kleid, kleine Flügel
  function kind(withStar, peek) {
    var star = withStar ? '<g class="ck-star"><path d="M84 110 C 88 84, 70 66, 62 46" stroke="#B28A4F" stroke-width="1.4" fill="none"/><g transform="translate(34 4) scale(.9)">' + STAR + '</g><circle cx="63" cy="33" r="34" fill="' + C.g + '" opacity=".14"/></g>' : '';
    // Arme mit feinem Rand, damit sie sich vom weißen Kleid abheben
    var armR = '<g class="ck-arm"><path d="M66 134c8-6 14-14 17-22" stroke="' + C.b + '" stroke-width="9" stroke-linecap="round" fill="none"/><path d="M66 134c8-6 14-14 17-22" stroke="#FBF6EF" stroke-width="6.4" stroke-linecap="round" fill="none"/><circle cx="84" cy="110" r="5" fill="#F4D3BE" stroke="#E2B7A0" stroke-width="1"/></g>';
    var armL = '<path d="M54 134c-6 6-10 13-12 20" stroke="' + C.b + '" stroke-width="9" stroke-linecap="round" fill="none"/><path d="M54 134c-6 6-10 13-12 20" stroke="#FBF6EF" stroke-width="6.4" stroke-linecap="round" fill="none"/><circle cx="42" cy="156" r="4.6" fill="#F4D3BE" stroke="#E2B7A0" stroke-width="1"/>';
    return '<svg viewBox="0 ' + (withStar ? 0 : 74) + ' 120 ' + (withStar ? 210 : 136) + '" aria-hidden="true">' + star
      + '<path class="ck-wing" d="M50 140c-22-18-38-14-44-4 10 2 14 8 16 16 8-6 18-8 28-12z" fill="#FFFFFF" stroke="' + C.b + '" stroke-width="1.6"/>'
      + '<path class="ck-wing w2" d="M70 140c22-18 38-14 44-4-10 2-14 8-16 16-8-6-18-8-28-12z" fill="#FFFFFF" stroke="' + C.b + '" stroke-width="1.6"/>'
      + '<path d="M60 124c-14 0-20 14-22 30l-6 40c18 8 38 8 56 0l-6-40c-2-16-8-30-22-30z" fill="#FBF6EF" stroke="' + C.b + '" stroke-width="1.6"/>'
      + '<path d="M34 190c18 7 34 7 52 0" stroke="' + C.g + '" stroke-width="3" fill="none"/>'
      + '<path d="M52 128l8 10 8-10" stroke="' + C.g + '" stroke-width="2" fill="none"/>'
      + armL
      + '<path d="M42 104c-6-16 4-30 18-30s24 14 18 30c4 8-2 16-6 14 0 0-8 6-12 6s-12-6-12-6c-4 2-10-6-6-14z" fill="#E7BE6A"/>'
      + '<circle cx="60" cy="104" r="15" fill="#F6DCC8"/>'
      + '<path d="M45 98c2-12 26-14 30 0-6-4-10-6-15-6s-9 2-15 6z" fill="#E7BE6A"/>'
      + '<circle cx="44" cy="106" r="5" fill="#E7BE6A"/><circle cx="76" cy="106" r="5" fill="#E7BE6A"/><circle cx="46" cy="116" r="4" fill="#E7BE6A"/><circle cx="74" cy="116" r="4" fill="#E7BE6A"/>'
      + '<path d="M53 104c1.5 1.5 3.5 1.5 5 0M62 104c1.5 1.5 3.5 1.5 5 0" stroke="#5B3A2E" stroke-width="1.6" fill="none" stroke-linecap="round"/>'
      + '<circle cx="51" cy="109" r="2.6" fill="#EBA79A" opacity=".7"/><circle cx="69" cy="109" r="2.6" fill="#EBA79A" opacity=".7"/>'
      + '<path d="M57 111c2 1.6 4 1.6 6 0" stroke="#B5584A" stroke-width="1.5" fill="none" stroke-linecap="round"/>'
      + '<g fill="' + C.g + '"><path d="M60 80l1.6 3.4 3.6.4-2.7 2.4.8 3.6L60 88l-3.3 1.8.8-3.6-2.7-2.4 3.6-.4z"/><circle cx="49" cy="85" r="2"/><circle cx="71" cy="85" r="2"/></g>'
      + armR
      + '</svg>';
  }

  function style() { var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st); }

  function flyers() {
    if (calm) return;
    var fx = document.createElement('div'); fx.className = 'xm-fx'; fx.setAttribute('aria-hidden', 'true');
    var n = innerWidth < 700 ? 3 : 5;
    for (var i = 0; i < n; i++) {
      var b = document.createElement('span'); b.className = 'xm-fly' + (i % 2 ? ' rev' : '') + (i % 3 === 0 ? ' spin' : '');
      var sz = 26 + Math.round(Math.random() * 22); b.style.width = b.style.height = sz + 'px';
      b.style.top = (8 + Math.random() * 72) + '%'; b.style.animationDuration = (22 + Math.random() * 16) + 's';
      b.style.animationDelay = (-Math.random() * 30 + i * 3) + 's';
      b.innerHTML = '<i>' + FLY[i % FLY.length] + '</i>'; fx.appendChild(b);
    }
    var f = innerWidth < 700 ? 6 : 10;
    for (var k = 0; k < f; k++) {
      var s = document.createElement('span'); s.className = 'xm-flake'; var z = 9 + Math.round(Math.random() * 9);
      s.style.width = s.style.height = z + 'px'; s.style.left = (Math.random() * 100) + '%';
      s.style.animationDuration = (16 + Math.random() * 14) + 's'; s.style.animationDelay = (-Math.random() * 30) + 's';
      s.innerHTML = FLAKE; fx.appendChild(s);
    }
    document.body.appendChild(fx);
  }

  // Adventkalender zum Selbermachen gleich unter die Kurse (bis Ende November)
  function adventUp() {
    if (!adventZeit) return;
    var a = document.getElementById('adventkalender'), kurse = document.getElementById('angebote');
    if (!a || !kurse || a.closest('.xm-up')) return;
    var sec = document.createElement('section'); sec.className = 'xm-up'; sec.setAttribute('aria-label', 'Adventkalender');
    var w = document.createElement('div'); w.className = 'wrap'; sec.appendChild(w); w.appendChild(a);
    kurse.parentNode.insertBefore(sec, kurse.nextSibling);
    window.dispatchEvent(new Event('resize'));
  }

  // Pop-up: Schleife oben rechts, wenn ein Advent-Pop-up aufgeht
  function bow() {
    var dlg = document.getElementById('season'); if (!dlg) return;
    var BOW = '<svg class="xm-bow" viewBox="0 0 100 100" aria-hidden="true"><path d="M50 40c-14-22-40-26-40-10 0 12 22 14 40 10z" fill="#8C2C1D"/><path d="M50 40c14-22 40-26 40-10 0 12-22 14-40 10z" fill="#8C2C1D"/><path d="M48 42l-16 40 10-4 6 10 4-44zM52 42l16 40-10-4-6 10-4-44z" fill="#A43A28"/><circle cx="50" cy="40" r="8" fill="#6E2116"/><path d="M22 28c4-4 10-4 16 2M78 28c-4-4-10-4-16 2" stroke="#C9584A" stroke-width="2.5" fill="none" stroke-linecap="round"/></svg>';
    new MutationObserver(function () {
      var on = dlg.open && dlg.classList.contains('theme-advent');
      var has = dlg.querySelector('.xm-bow');
      if (on && !has) dlg.insertAdjacentHTML('beforeend', BOW);
      if (!on && has) has.remove();
    }).observe(dlg, { attributes: true, attributeFilter: ['open', 'class'] });
  }

  // Christkind versteckt sich: schaut alle 35–70 s irgendwo am Rand herein
  var GRUESSE = [
    ['Psst …', 'Hier war das Christkind. Hast du deine Wunschliste schon geschrieben?'],
    ['Huch, entdeckt!', 'Ich schau nur nach, welche Tassen gerade bemalt werden.'],
    ['Pssst …', 'Selbstgemachte Geschenke mag ich am allerliebsten.'],
    ['Hallo!', 'Ich wünsch dir eine ruhige, warme Adventszeit.'],
    ['Gefunden!', 'Am 24. Dezember komme ich mit meinem Stern vorbei.']
  ];
  function peek() {
    if (heiligabend) return;
    var el = document.createElement('div'); el.className = 'ck-peek'; el.setAttribute('role', 'button'); el.setAttribute('aria-label', 'Das Christkind');
    el.innerHTML = kind(false, true); document.body.appendChild(el);
    var say = document.createElement('div'); say.className = 'ck-say'; say.setAttribute('role', 'status'); document.body.appendChild(say);
    var shown = 0, gi = Math.floor(Math.random() * GRUESSE.length), tOut;
    function hide() { el.classList.remove('in'); say.classList.remove('on'); }
    function once() {
      if (document.hidden || document.querySelector('dialog[open]')) return later();
      var side = ['r', 'l', 'b'][Math.floor(Math.random() * (innerWidth < 700 ? 2 : 3))];
      el.className = 'ck-peek ' + side;
      el.style.top = el.style.left = el.style.right = el.style.bottom = '';
      if (side === 'b') { el.style.left = (12 + Math.random() * 70) + '%'; el.style.bottom = '0'; }
      else { el.style.top = (22 + Math.random() * 50) + '%'; if (side === 'r') el.style.right = '0'; else el.style.left = '0'; }
      void el.offsetWidth; el.classList.add('in');
      clearTimeout(tOut); tOut = setTimeout(hide, 5200);
      if (++shown < 7) later();
    }
    function later() { setTimeout(once, (35 + Math.random() * 35) * 1000); }
    el.addEventListener('click', function () {
      var g = GRUESSE[gi++ % GRUESSE.length]; say.innerHTML = '<b>' + g[0] + '</b>' + g[1];
      var r = el.getBoundingClientRect();
      say.style.top = Math.max(10, Math.min(innerHeight - 120, r.top - 10)) + 'px';
      say.style.left = Math.max(10, Math.min(innerWidth - 250, r.left < innerWidth / 2 ? r.right + 6 : r.left - 240)) + 'px';
      say.classList.add('on'); clearTimeout(tOut); tOut = setTimeout(hide, 4500);
    });
    setTimeout(once, Q.get('deko') === 'weihnachten' ? 2500 : 14000);
  }

  // ---------- 24. Dezember: Christkind fliegt, „Stille Nacht“ als Spieluhr ----------
  var MEL = [['G4',3],['A4',1],['G4',2],['E4',6],['G4',3],['A4',1],['G4',2],['E4',6],['D5',4],['D5',2],['B4',6],['C5',4],['C5',2],['G4',6],
    ['A4',4],['A4',2],['C5',3],['B4',1],['A4',2],['G4',3],['A4',1],['G4',2],['E4',6],['A4',4],['A4',2],['C5',3],['B4',1],['A4',2],['G4',3],['A4',1],['G4',2],['E4',6],
    ['D5',4],['D5',2],['F5',3],['D5',1],['B4',2],['C5',6],['E5',6],['C5',3],['G4',1],['E4',2],['G4',3],['F4',1],['D4',2],['C4',12]];
  var BASS = ['C3','C3','C3','C3','G2','C3','C3','C3','F2','C3','F2','C3','F2','C3','F2','C3','G2','G2','C3','C3','C3','G2','C3','C3'];
  function hz(n) { var m = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 }[n[0]] + 12 * (+n[n.length - 1] + 1); return 440 * Math.pow(2, (m - 69) / 12); }
  var ac = null, master = null, playing = false, loopT = null;
  function audio() {
    if (ac) return ac;
    var A = window.AudioContext || window.webkitAudioContext; if (!A) return null;
    ac = new A(); master = ac.createGain(); master.gain.value = 0.0001;
    var conv = ac.createConvolver(), len = ac.sampleRate * 2.2, buf = ac.createBuffer(2, len, ac.sampleRate);
    for (var c = 0; c < 2; c++) { var d = buf.getChannelData(c); for (var i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 3); }
    conv.buffer = buf; var wet = ac.createGain(); wet.gain.value = 0.32;
    master.connect(ac.destination); master.connect(conv); conv.connect(wet); wet.connect(ac.destination);
    return ac;
  }
  function tone(f, t, dur, vol, bass) {
    var g = ac.createGain(); g.connect(master);
    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + 0.006); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    [[1, 1], [2, bass ? 0.15 : 0.32], [3, bass ? 0.04 : 0.12], [4.2, bass ? 0 : 0.05]].forEach(function (h) {
      if (!h[1]) return; var o = ac.createOscillator(), og = ac.createGain(); o.type = bass ? 'triangle' : 'sine'; o.frequency.value = f * h[0];
      og.gain.value = h[1]; o.connect(og); og.connect(g); o.start(t); o.stop(t + dur + 0.05);
    });
  }
  function song() {
    if (!playing) return;
    var e = 0.3, t = ac.currentTime + 0.15, x = t;
    MEL.forEach(function (n) { tone(hz(n[0]), x, Math.max(1.4, n[1] * e * 1.4), 0.22, false); x += n[1] * e; });
    BASS.forEach(function (b, i) { tone(hz(b), t + i * 6 * e, 6 * e * 1.1, 0.10, true); tone(hz(b) * 1.5, t + i * 6 * e + 3 * e, 3 * e, 0.05, true); });
    loopT = setTimeout(song, ((x - ac.currentTime) + 2.5) * 1000);
  }
  function play(on) {
    if (!audio()) return;
    if (on === playing) return; playing = on;
    if (on) { ac.resume(); master.gain.cancelScheduledValues(ac.currentTime); master.gain.setTargetAtTime(0.9, ac.currentTime, 0.4); song(); }
    else { clearTimeout(loopT); master.gain.setTargetAtTime(0.0001, ac.currentTime, 0.3); }
    try { sessionStorage.setItem('forma-ck-mu', on ? '1' : '0'); } catch (e) {}
  }
  function heilig() {
    var fly = document.createElement('div'); fly.className = 'ck-fly'; fly.setAttribute('role', 'button');
    fly.setAttribute('aria-label', 'Das Christkind mit seinem Stern – antippen für Musik an oder aus'); fly.title = 'Musik an / aus';
    fly.innerHTML = kind(true, false); document.body.appendChild(fly);
    var note = document.createElement('div'); note.className = 'ck-note'; document.body.appendChild(note);
    function hint(t) { note.textContent = t; note.classList.add('on'); clearTimeout(note._t); note._t = setTimeout(function () { note.classList.remove('on'); }, 3200); }
    var muted = false; try { muted = sessionStorage.getItem('forma-ck-mu') === '0'; } catch (e) {}
    fly.addEventListener('click', function (ev) { ev.stopPropagation(); muted = playing; play(!playing); hint(playing ? '♪ Stille Nacht, heilige Nacht' : 'Musik aus'); });
    // Ton erst nach dem ersten Tippen (vorher erlauben Browser keine Musik)
    function first() { document.removeEventListener('pointerdown', first, true); document.removeEventListener('keydown', first, true); if (!muted) { play(true); hint('♪ Frohe Weihnachten! Tipp das Christkind an für Musik aus.'); } }
    document.addEventListener('pointerdown', first, true); document.addEventListener('keydown', first, true);
    setTimeout(function () { if (!playing && !muted) hint('Frohe Weihnachten! Tipp irgendwo hin, dann singt die Spieluhr.'); }, 2500);
    if (calm) { fly.style.transform = 'translate(' + (innerWidth - 140) + 'px,' + (innerHeight - 230) + 'px)'; return; }
    var t0 = performance.now(), last = 0, dur = 17000;
    function frame(ts) {
      var t = ((ts - t0) % (dur * 2)) / dur, dir = t < 1 ? 1 : -1, p = t < 1 ? t : t - 1;
      var W = innerWidth, H = innerHeight, w = fly.offsetWidth;
      var x = dir > 0 ? -w + p * (W + 2 * w) : W + w - p * (W + 2 * w) - w;
      var y = H * (0.18 + 0.22 * Math.sin(p * Math.PI)) + Math.sin(ts / 700) * 18;
      fly.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)' + (dir < 0 ? ' scaleX(-1)' : '');
      if (ts - last > 260 && x > -w && x < W) {
        last = ts; var s = document.createElement('span'); s.className = 'ck-spark'; s.innerHTML = SPARK;
        s.style.left = (x + (dir > 0 ? 10 : w - 20) + Math.random() * 10) + 'px'; s.style.top = (y + w * 1.1 + Math.random() * 20) + 'px';
        document.body.appendChild(s); setTimeout(function (n) { return function () { n.remove(); }; }(s), 1700);
      }
      requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  function start(season) {
    if (started || season !== 'weihnachten') return; started = true;
    style(); flyers(); adventUp(); bow(); peek();
    if (heiligabend) heilig();
  }
  document.addEventListener('forma-deko', function (e) { start(e.detail); });
  if (window.__formaSeason) start(window.__formaSeason);
})();

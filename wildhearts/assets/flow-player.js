/* Movecia Übungs-Player (Vivien, 26.10.2026)
   Jede Übung ist ein kurzer Clip von Vivien (Bunny-Embed, läuft stumm in Schleife). Zwei Formen:
   · Flow: Vivien spricht pro Übung ein Voice-over, der Timer läuft so lange wie die Übung (meist 2 Minuten).
   · Bewegungsvideo: ohne Sprechen, die Teilnehmerin wählt 30 oder 45 Sekunden pro Übung.
   In den letzten 5 Sekunden blüht ein sanfter „Sonnenaufgang“-Klang auf, dann kommt die nächste Übung von selbst.
   Kein Vorspulen und kein Überspringen: Das Programm soll begleiten, nicht abgehakt werden. Am Ende meldet der
   Player „mvflow:done“ am Element (der Mitgliederbereich speichert dann „erledigt“).
   Dazu ein Musikfeld: Spotify, Apple Music, YouTube Music, Deezer, SoundCloud direkt auf der Seite, Amazon Music daneben.
   Aufruf: MvFlow(element, {lang, mode, title, items:[{name, src, sec, voice, text}], music:[{title,url}], preview})
   oder automatisch für jedes [data-mv-flow]. */
(function () {
  const CSS = `
.mvf{--mvf-ink:#2E2529;--mvf-muted:#7D7076;--mvf-line:#DDD2C6;--mvf-card:#FBF8F4;--mvf-plum:#7D5E68;--mvf-deep:#4A3A41;--mvf-blush:#EADFDE;--mvf-glow:#F1C9A5;
  display:flex;flex-direction:column;gap:14px;color:var(--mvf-ink);font-family:inherit}
.mvf *{box-sizing:border-box}
.mvf [hidden]{display:none!important}
.mvf-play{display:flex;flex-direction:column;gap:12px}
.mvf-setup{background:var(--mvf-card);border:1px solid var(--mvf-line);border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:14px}
.mvf-k{font-size:.78rem;letter-spacing:.18em;text-transform:uppercase;color:var(--mvf-muted);margin:0}
.mvf-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px}
.mvf-list li{font-size:.88rem;background:var(--mvf-blush);color:var(--mvf-deep);border-radius:999px;padding:4px 12px}
.mvf-seg{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.mvf-seg button{font:inherit;border:1.5px solid var(--mvf-line);background:#fff;color:var(--mvf-ink);border-radius:18px;padding:12px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px}
.mvf-seg b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:2rem;line-height:1}
.mvf-seg span{font-size:.82rem;color:var(--mvf-muted)}
.mvf-seg button[aria-pressed="true"]{border-color:var(--mvf-plum);background:var(--mvf-blush)}
.mvf-go{font:inherit;font-weight:500;border:0;border-radius:999px;background:var(--mvf-deep);color:#FBF5F7;padding:14px 24px;cursor:pointer}
.mvf-note{font-size:.88rem;color:var(--mvf-muted);margin:0;line-height:1.5}
.mvf-stage{position:relative;aspect-ratio:16/9;max-width:100%;border-radius:22px;overflow:hidden;background:linear-gradient(140deg,#7D5E68,#4A3A41)}
.mvf-stage iframe{position:absolute;inset:0;width:100%;height:100%;border:0;pointer-events:none}
.mvf-ph{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(251,245,247,.7);font-size:.9rem;text-align:center;padding:1rem}
.mvf-name{position:absolute;left:14px;top:12px;right:120px;color:#FBF5F7;text-shadow:0 1px 8px rgba(0,0,0,.35)}
.mvf-name small{display:block;font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;opacity:.85}
.mvf-name b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:clamp(1.2rem,3.4vw,1.9rem);line-height:1.1}
.mvf-ring{position:absolute;right:12px;bottom:12px;width:clamp(84px,19vw,124px);aspect-ratio:1}
.mvf-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.mvf-ring .t{fill:rgba(251,245,247,.82);stroke:rgba(74,58,65,.15);stroke-width:7}
.mvf-ring .p{fill:none;stroke:var(--mvf-deep);stroke-width:7;stroke-linecap:round;transition:stroke .6s}
.mvf-ring .n{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--mvf-deep)}
.mvf-ring .n b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:700;font-size:clamp(2rem,6vw,2.9rem);line-height:1;font-variant-numeric:tabular-nums}
.mvf-ring .n b.mm{font-size:clamp(1.55rem,4.6vw,2.25rem)}
.mvf-ring .n span{margin-top:.45em;font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;opacity:.7}
.mvf-stage.end .mvf-ring .p{stroke:#C98A5E}
.mvf-sun{position:absolute;left:0;right:0;bottom:-45%;height:85%;background:radial-gradient(closest-side,rgba(241,201,165,.85),transparent);opacity:0;transition:opacity 1.4s ease;pointer-events:none}
.mvf-stage.end .mvf-sun{opacity:1}
.mvf-next{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;background:rgba(74,58,65,.72);color:#FBF5F7;opacity:0;pointer-events:none;transition:opacity .5s;text-align:center;padding:1rem}
.mvf-next.on{opacity:1}
.mvf-next small{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase}
.mvf-next b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:clamp(1.4rem,4vw,2.2rem)}
.mvf-dots{display:flex;gap:5px;flex-wrap:wrap}.mvf-dots i{width:24px;height:5px;border-radius:9px;background:var(--mvf-line)}.mvf-dots i.d{background:var(--mvf-plum)}.mvf-dots i.c{background:var(--mvf-glow)}
.mvf-after{font-size:.9rem;color:var(--mvf-muted);margin:0}
.mvf-say{background:var(--mvf-card);border:1px dashed var(--mvf-line);border-radius:16px;padding:12px 14px;font-size:.92rem;line-height:1.55;white-space:pre-line;max-height:11em;overflow:auto}
.mvf-say small{display:block;font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:var(--mvf-muted);margin-bottom:4px}
.mvf-ctrl{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.mvf-ctrl button{font:inherit;font-size:.92rem;border:1.5px solid var(--mvf-line);background:var(--mvf-card);color:var(--mvf-ink);border-radius:999px;padding:9px 15px;cursor:pointer;min-height:44px}
.mvf-ctrl .main{background:var(--mvf-deep);border-color:var(--mvf-deep);color:#FBF5F7;min-width:116px}
.mvf-ctrl .r{margin-left:auto}
.mvf button:focus-visible,.mvf a:focus-visible,.mvf input:focus-visible{outline:3px solid var(--mvf-plum);outline-offset:2px}
.mvf-done{background:var(--mvf-card);border:1px solid var(--mvf-line);border-radius:22px;padding:24px;text-align:center;display:flex;flex-direction:column;gap:10px;align-items:center}
.mvf-done h3{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:1.7rem;margin:0}
.mvf-mu{border:1px solid var(--mvf-line);border-radius:18px;background:#fff;overflow:hidden}
.mvf-mu>summary{list-style:none;cursor:pointer;padding:12px 16px;display:flex;align-items:center;gap:10px;font-size:.95rem}
.mvf-mu>summary::-webkit-details-marker{display:none}
.mvf-mu>summary b{font-weight:500}
.mvf-mu>summary span{margin-left:auto;font-size:.82rem;color:var(--mvf-muted)}
.mvf-mub{padding:0 16px 16px;display:flex;flex-direction:column;gap:10px}
.mvf-prov{display:flex;flex-wrap:wrap;gap:6px}
.mvf-prov button{font:inherit;font-size:.86rem;border:1.5px solid var(--mvf-line);background:var(--mvf-card);color:var(--mvf-ink);border-radius:999px;padding:7px 13px;cursor:pointer}
.mvf-prov button[aria-pressed="true"]{border-color:var(--mvf-plum);background:var(--mvf-blush)}
.mvf-row{display:flex;gap:8px;flex-wrap:wrap}
.mvf-row input{flex:1 1 220px;font:inherit;font-size:.92rem;border:1.5px solid var(--mvf-line);border-radius:14px;padding:10px 12px;background:var(--mvf-card);color:var(--mvf-ink);min-width:0}
.mvf-row button,.mvf-row a{font:inherit;font-size:.9rem;border:0;border-radius:999px;background:var(--mvf-plum);color:#fff;padding:10px 16px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.mvf-row a.l{background:transparent;color:var(--mvf-deep);border:1.5px solid var(--mvf-line)}
.mvf-sugg{display:flex;flex-wrap:wrap;gap:6px}
.mvf-sugg button{font:inherit;font-size:.84rem;border:1px solid var(--mvf-line);background:var(--mvf-card);border-radius:999px;padding:6px 12px;cursor:pointer;color:var(--mvf-deep)}
.mvf-dock{border-radius:16px;overflow:hidden;background:var(--mvf-card)}
.mvf-dock iframe{display:block;width:100%;border:0}
.mvf-err{color:#8C3B3B;font-size:.86rem;margin:0}
@media (max-width:560px){.mvf-ctrl .r{margin-left:0}}`;
  function css() { if (document.getElementById('mvf-css')) return; const s = document.createElement('style'); s.id = 'mvf-css'; s.textContent = CSS; document.head.appendChild(s); }
  const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  // Sonnenaufgang: weicher Akkord, der aufblüht und warm ausklingt, am Ende ein zarter Glockenton
  let ac = null;
  function actx() { if (!ac) { try { ac = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { ac = null; } } if (ac && ac.state === 'suspended') ac.resume(); return ac; }
  function sunrise(len) {
    const a = actx(); if (!a) return;
    const t0 = a.currentTime, L = Math.max(2.2, len);
    const out = a.createGain(); out.gain.value = .0001; out.connect(a.destination);
    out.gain.exponentialRampToValueAtTime(.22, t0 + L * .55); out.gain.exponentialRampToValueAtTime(.0001, t0 + L + 2.2);
    const lp = a.createBiquadFilter(); lp.type = 'lowpass'; lp.Q.value = .6; lp.frequency.setValueAtTime(320, t0); lp.frequency.exponentialRampToValueAtTime(3200, t0 + L); lp.connect(out);
    [[146.83, 0], [220, .15], [293.66, .35], [369.99, .7], [440, 1.1], [659.25, 1.6]].forEach(([f, d], k) => {
      [-3, 3].forEach(det => {
        const o = a.createOscillator(), g = a.createGain();
        o.type = k < 3 ? 'sine' : 'triangle'; o.frequency.value = f; o.detune.value = det;
        g.gain.value = .0001; g.gain.setValueAtTime(.0001, t0 + d * L / 3);
        g.gain.exponentialRampToValueAtTime(k < 3 ? .5 : .22, t0 + d * L / 3 + 1.1); g.gain.exponentialRampToValueAtTime(.0001, t0 + L + 2.2);
        o.connect(g).connect(lp); o.start(t0 + d * L / 3); o.stop(t0 + L + 2.4);
      });
    });
    const b = a.createOscillator(), bg = a.createGain(); b.type = 'sine'; b.frequency.value = 880;
    bg.gain.value = .0001; bg.gain.setValueAtTime(.0001, t0 + L - .05); bg.gain.exponentialRampToValueAtTime(.08, t0 + L + .02); bg.gain.exponentialRampToValueAtTime(.0001, t0 + L + 2.5);
    b.connect(bg).connect(out); b.start(t0 + L - .05); b.stop(t0 + L + 2.6);
  }

  // --- Musik: eigener Anbieter, eigene Playlist ------------------------------------------------------------
  const PROV = {
    spotify: { n: 'Spotify', h: 152, login: 'https://accounts.spotify.com/login', ex: 'open.spotify.com/playlist/…' },
    apple: { n: 'Apple Music', h: 175, login: 'https://music.apple.com/', ex: 'music.apple.com/at/playlist/…' },
    youtube: { n: 'YouTube Music', h: 200, login: 'https://music.youtube.com/', ex: 'music.youtube.com/playlist?list=…' },
    deezer: { n: 'Deezer', h: 150, login: 'https://www.deezer.com/login', ex: 'deezer.com/playlist/…' },
    soundcloud: { n: 'SoundCloud', h: 166, login: 'https://soundcloud.com/signin', ex: 'soundcloud.com/…/sets/…' },
    amazon: { n: 'Amazon Music', h: 0, login: 'https://music.amazon.de/', ex: '' }
  };
  function embedOf(url) {
    url = String(url || '').trim(); let m;
    if ((m = url.match(/open\.spotify\.com\/(?:intl-[a-z-]+\/)?(?:embed\/)?(playlist|album|track|artist|show|episode)\/([A-Za-z0-9]{10,40})/))) return { p: 'spotify', src: `https://open.spotify.com/embed/${m[1]}/${m[2]}?utm_source=generator&theme=0` };
    if ((m = url.match(/^https?:\/\/(?:embed\.)?music\.apple\.com(\/[a-z]{2}\/(?:playlist|album|station|song)\/[^\s?#]+(?:\?i=\d+)?)/))) return { p: 'apple', src: 'https://embed.music.apple.com' + m[1] };
    if ((m = url.match(/(?:youtube\.com|youtu\.be)\/.*[?&]list=([A-Za-z0-9_-]+)/))) return { p: 'youtube', src: `https://www.youtube-nocookie.com/embed/videoseries?list=${m[1]}` };
    if ((m = url.match(/(?:youtube\.com\/watch\?(?:.*&)?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/))) return { p: 'youtube', src: `https://www.youtube-nocookie.com/embed/${m[1]}` };
    if ((m = url.match(/deezer\.com\/(?:[a-z]{2}\/)?(playlist|album|track|artist)\/(\d+)/))) return { p: 'deezer', src: `https://widget.deezer.com/widget/auto/${m[1]}/${m[2]}` };
    if (/^https?:\/\/(?:www\.|m\.)?soundcloud\.com\/[^\s]+/.test(url)) return { p: 'soundcloud', src: 'https://w.soundcloud.com/player/?url=' + encodeURIComponent(url.split('?')[0]) + '&color=%237D5E68&visual=false&show_comments=false' };
    if (/music\.amazon\./.test(url)) return { p: 'amazon', src: '', open: url };
    return null;
  }
  function musicBox(root, T, lists, flow) {
    const box = root.querySelector('[data-mu]'), dock = root.querySelector('[data-dock]');
    let st = {}; try { st = JSON.parse(localStorage.getItem('mvf-music') || '{}') || {}; } catch (e) {}
    let prov = PROV[st.p] ? st.p : 'spotify';
    const provBtns = Object.keys(PROV).map(k => `<button type="button" data-pv="${k}">${PROV[k].n}</button>`).join('');
    box.querySelector('[data-mub]').innerHTML = `
      <p class="mvf-note">${T('Such dir deinen Musikdienst aus und füg den Link zu deiner Playlist ein. Die Musik läuft hier neben den Übungen. Ganze Songs hörst du, wenn du bei deinem Anbieter angemeldet bist.', 'Pick your music service and paste the link to your playlist. The music plays right here next to the exercises. You hear full songs when you are logged in with your provider.')}${flow ? ' ' + T('Stell die Musik leise, damit du meine Stimme gut hörst.', 'Keep the music soft so you can hear my voice.') : ''}</p>
      <div class="mvf-prov" role="group">${provBtns}</div>
      <div data-pbody></div>
      <p class="mvf-err" data-merr hidden></p>
      <div class="mvf-row"><button type="button" data-moff hidden>${T('Musik ausschalten', 'Turn music off')}</button></div>
      <p class="mvf-note">${T('Der Player des Musikdienstes lädt erst, wenn du hier eine Playlist startest.', 'The music service player only loads once you start a playlist here.')} <a href="/terms/#musik" target="_blank" rel="noopener">${T('Datenschutz', 'Privacy')}</a></p>`;
    const pbody = box.querySelector('[data-pbody]'), err = box.querySelector('[data-merr]'), off = box.querySelector('[data-moff]'), lab = box.querySelector('summary span');
    function save() { try { localStorage.setItem('mvf-music', JSON.stringify(st)); } catch (e) {} }
    function mount() {
      const e = st.url ? embedOf(st.url) : null;
      dock.innerHTML = ''; dock.hidden = true; off.hidden = !st.url;
      if (e && e.src) {
        dock.innerHTML = `<iframe src="${esc(e.src)}" height="${PROV[e.p].h}" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy" title="${esc(PROV[e.p].n)}"></iframe>`;
        dock.hidden = false; lab.textContent = PROV[e.p].n + ' ' + T('ist an', 'is on');
      } else lab.textContent = T('aus', 'off');
    }
    function draw() {
      box.querySelectorAll('[data-pv]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.pv === prov)));
      const P = PROV[prov];
      if (prov === 'amazon') {
        pbody.innerHTML = `<p class="mvf-note">${T('Amazon Music lässt sich nicht direkt in andere Seiten einbauen. Öffne deine Playlist in der Amazon-Music-App oder in einem neuen Tab, starte sie dort und komm hierher zurück. Die Musik läuft weiter.', 'Amazon Music cannot be built into other pages. Open your playlist in the Amazon Music app or a new tab, start it there and come back here. The music keeps playing.')}</p>
          <div class="mvf-row"><a href="${P.login}" target="_blank" rel="noopener">${T('Amazon Music öffnen', 'Open Amazon Music')} ↗</a></div>`;
        return;
      }
      const sug = prov === 'spotify' && lists.length ? `<p class="mvf-k">${T('Meine Vorschläge', 'My suggestions')}</p><div class="mvf-sugg">${lists.map((x, i) => `<button type="button" data-sg="${i}">♪ ${esc(x.title)}</button>`).join('')}</div>` : '';
      pbody.innerHTML = `${sug}
        <p class="mvf-k">${T('Deine eigene Playlist', 'Your own playlist')}</p>
        <div class="mvf-row"><input type="url" inputmode="url" data-murl placeholder="${esc(P.ex)}" value="${esc(st.p === prov ? st.url || '' : '')}" aria-label="${T('Link zur Playlist', 'Playlist link')}"><button type="button" data-mgo>${T('Abspielen', 'Play')}</button></div>
        <div class="mvf-row"><a class="l" href="${P.login}" target="_blank" rel="noopener">${T('Bei ' + P.n + ' anmelden', 'Log in to ' + P.n)} ↗</a></div>
        <p class="mvf-note">${T('So findest du den Link: in der App bei der Playlist auf „Teilen“ und dann „Link kopieren“.', 'To find the link: in the app, tap “Share” on your playlist, then “Copy link”.')}</p>`;
      const inp = pbody.querySelector('[data-murl]');
      pbody.querySelector('[data-mgo]').addEventListener('click', () => {
        const e = embedOf(inp.value);
        if (!e) { err.textContent = T('Diesen Link kann ich leider nicht abspielen. Nimm bitte den Link über „Teilen → Link kopieren“.', 'I cannot play this link. Please use “Share → Copy link”.'); err.hidden = false; return; }
        err.hidden = true;
        if (e.p === 'amazon') { window.open(e.open, '_blank', 'noopener'); return; }
        prov = e.p; st = { p: e.p, url: inp.value.trim() }; save(); mount(); draw();
      });
      inp.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); pbody.querySelector('[data-mgo]').click(); } });
      pbody.querySelectorAll('[data-sg]').forEach(b => b.addEventListener('click', () => { st = { p: 'spotify', url: lists[+b.dataset.sg].url }; save(); mount(); draw(); }));
    }
    box.querySelectorAll('[data-pv]').forEach(b => b.addEventListener('click', () => { prov = b.dataset.pv; err.hidden = true; draw(); }));
    off.addEventListener('click', () => { st = {}; save(); mount(); draw(); });
    draw();
    // Fremde Musikdienste laden erst, wenn sie die Musik selbst aufklappt (Datenschutz)
    if (st.url) { lab.textContent = ((PROV[st.p] || {}).n || '') + ' · ' + T('antippen zum Laden', 'tap to load'); off.hidden = false; }
    box.addEventListener('toggle', () => { if (box.open && st.url && dock.hidden) mount(); });
  }

  function fmt(s) { s = Math.max(0, Math.ceil(s - 1e-6)); return s >= 60 ? Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') : String(s); }

  function MvFlow(root, data) {
    css();
    const en = data.lang === 'en', T = (de, e) => en ? e : de;
    const items = data.items || [];
    const flow = data.mode !== 'move';
    const pv = !!data.preview;
    let dur = 30; try { dur = +(localStorage.getItem('mvf-dur') || 30) === 45 ? 45 : 30; } catch (e) {}
    let idx = 0, left = 0, total = 30, run = false, last = 0, chimed = false, sound = true, raf = 0, sw = false, ended = false;
    const voice = new Audio(); voice.preload = 'auto';
    const C = 2 * Math.PI * 52;
    const secOf = i => flow ? Math.max(20, +items[i].sec || 120) : dur;
    const sum = () => items.reduce((a, _, i) => a + secOf(i), 0);
    root.classList.add('mvf');
    root.innerHTML = `
<div class="mvf-setup" data-s>
  <p class="mvf-k">${flow ? T('Dein Flow', 'Your flow') : T('Deine Übungen', 'Your exercises')}</p>
  <ul class="mvf-list">${items.map((x, i) => `<li>${i + 1}. ${esc(x.name)}</li>`).join('')}</ul>
  ${flow ? '' : `<p class="mvf-k">${T('Wie lange pro Übung?', 'How long per exercise?')}</p>
  <div class="mvf-seg" role="group">
    <button type="button" data-d="30"><b>30</b><span>${T('Sekunden · sanfter Einstieg', 'seconds · gentle start')}</span></button>
    <button type="button" data-d="45"><b>45</b><span>${T('Sekunden · etwas länger', 'seconds · a little longer')}</span></button>
  </div>`}
  <button type="button" class="mvf-go" data-go>${T("Los geht's", "Let's begin")}</button>
  <p class="mvf-note" data-tot></p>
</div>
<details class="mvf-mu" data-mu><summary>🎵 <b>${T('Deine Musik dazu', 'Add your music')}</b><span>${T('aus', 'off')}</span></summary><div class="mvf-mub" data-mub></div></details>
<div class="mvf-play" data-p hidden>
  <div class="mvf-stage" data-st>
    <iframe data-if title="" allow="autoplay;encrypted-media;picture-in-picture" loading="eager"></iframe>
    <div class="mvf-ph" data-ph hidden>${T('Clip folgt', 'Clip coming')}</div>
    <div class="mvf-sun"></div>
    <div class="mvf-name"><small data-tag></small><b data-nm></b></div>
    <div class="mvf-ring"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="t" cx="60" cy="60" r="52"></circle><circle class="p" data-pr cx="60" cy="60" r="52"></circle></svg><div class="n"><b data-sec>30</b><span data-unit>${T('Sek.', 'sec')}</span></div></div>
    <div class="mvf-next" data-nx><small>${T('Nächste Übung', 'Next exercise')}</small><b data-nxn></b></div>
  </div>
  <div class="mvf-dots" data-dots>${items.map(() => '<i></i>').join('')}</div>
  <p class="mvf-after" data-af></p>
  ${pv ? `<div class="mvf-say" data-say hidden></div>` : ''}
  <div class="mvf-ctrl">
    <button type="button" class="main" data-pause>❚❚ Pause</button>
    <button type="button" data-snd aria-pressed="true">${T('Klang an', 'Sound on')}</button>
    ${pv ? `<button type="button" class="r" data-prev>‹ ${T('Zurück', 'Back')}</button><button type="button" data-skip>${T('Überspringen', 'Skip')} ›</button>` : ''}
  </div>
</div>
<div class="mvf-dock" data-dock hidden></div>
<div class="mvf-done" data-done hidden><p class="mvf-k">${T('Geschafft', 'Done')}</p><h3>${T('Schön, dass du dir diese Zeit genommen hast.', 'Lovely that you took this time for yourself.')}</h3><p class="mvf-note" data-dt></p><button type="button" class="mvf-go" data-again>${T('Nochmal von vorn', 'Start again')}</button></div>`;
    const q = s => root.querySelector(s);
    const pr = q('[data-pr]'); pr.style.strokeDasharray = C;
    musicBox(root, T, data.music || [], flow);
    function seg() {
      root.querySelectorAll('[data-d]').forEach(b => b.setAttribute('aria-pressed', String(+b.dataset.d === dur)));
      const m = Math.max(1, Math.round(sum() / 60));
      q('[data-tot]').textContent = flow
        ? T(`${items.length} Übungen, zusammen etwa ${m} Minuten. Ich begleite dich mit meiner Stimme durch jede Übung.`, `${items.length} exercises, about ${m} minutes in total. My voice guides you through each one.`)
        : T(`${items.length} Übungen à ${dur} Sekunden, zusammen etwa ${m} Minuten.`, `${items.length} exercises of ${dur} seconds, about ${m} minutes in total.`);
    }
    root.querySelectorAll('[data-d]').forEach(b => b.addEventListener('click', () => { dur = +b.dataset.d; try { localStorage.setItem('mvf-dur', String(dur)); } catch (e) {} seg(); }));
    seg();
    function show(i) {
      const it = items[i];
      q('[data-tag]').textContent = T(`Übung ${i + 1} von ${items.length}`, `Exercise ${i + 1} of ${items.length}`);
      q('[data-nm]').textContent = it.name;
      const f = q('[data-if]'); f.title = it.name;
      if (it.src) { f.hidden = false; q('[data-ph]').hidden = true; if (f.getAttribute('src') !== it.src) f.src = it.src; }
      else { f.hidden = true; f.removeAttribute('src'); q('[data-ph]').hidden = false; }
      const n = items[i + 1];
      q('[data-af]').textContent = n ? T('Als Nächstes: ', 'Up next: ') + n.name : T('Danach bist du fertig.', 'After this you are done.');
      root.querySelectorAll('[data-dots] i').forEach((d, k) => d.className = k < i ? 'd' : (k === i ? 'c' : ''));
      total = secOf(i); left = total; chimed = false; q('[data-st]').classList.remove('end');
      const say = q('[data-say]'); if (say) { say.hidden = !it.text || !!it.voice; say.innerHTML = `<small>${T('Sprechtext (noch nicht eingesprochen)', 'Voice-over text (not recorded yet)')}</small>` + esc(it.text || ''); }
      voice.pause(); voice.onloadedmetadata = null;
      if (flow && it.voice) {
        // Ist das Voice-over länger als die Übung, läuft der Timer so lange wie die Stimme (plus kurzer Ausklang)
        voice.onloadedmetadata = () => { if (isFinite(voice.duration) && voice.duration + 4 > total) { const add = voice.duration + 4 - total; total += add; left += add; draw(); } };
        voice.src = it.voice;
        if (run) voice.play().catch(() => {});
      } else voice.removeAttribute('src');
      draw();
    }
    function draw() {
      const b = q('[data-sec]'), mm = left >= 60;
      b.textContent = fmt(left); b.classList.toggle('mm', mm);
      q('[data-unit]').textContent = mm ? T('Min.', 'min') : T('Sek.', 'sec');
      pr.style.strokeDashoffset = C * (1 - Math.max(0, left) / total);
    }
    function tick(now) {
      if (!run) return;
      const dt = Math.min(1, (now - last) / 1000); last = now;
      if (!sw) {
        left -= dt;
        if (left <= 5 && !chimed) { chimed = true; q('[data-st]').classList.add('end'); if (sound) sunrise(5); }
        if (left <= 0) { left = 0; draw(); advance(); } else draw();
      }
      raf = requestAnimationFrame(tick);
    }
    function advance() {
      if (idx >= items.length - 1) { finish(); return; }
      sw = true; voice.pause(); q('[data-nxn]').textContent = items[idx + 1].name; q('[data-nx]').classList.add('on');
      setTimeout(() => { idx++; show(idx); q('[data-nx]').classList.remove('on'); sw = false; last = performance.now(); }, 1300);
    }
    function play() { run = true; last = performance.now(); q('[data-pause]').textContent = '❚❚ Pause'; if (flow && voice.getAttribute('src') && !sw) voice.play().catch(() => {}); cancelAnimationFrame(raf); raf = requestAnimationFrame(tick); }
    function stop() { run = false; voice.pause(); q('[data-pause]').textContent = '▶ ' + T('Weiter', 'Resume'); }
    // Tab im Hintergrund: Timer anhalten, damit die Zeit nicht ohne sie verstreicht
    document.addEventListener('visibilitychange', () => { if (document.hidden && run && !pv) stop(); });
    function finish() {
      stop(); q('[data-p]').hidden = true; q('[data-done]').hidden = false; q('[data-if]').src = 'about:blank';
      q('[data-dt]').textContent = flow ? T(`${items.length} Übungen, etwa ${Math.round(sum() / 60)} Minuten nur für dich.`, `${items.length} exercises, about ${Math.round(sum() / 60)} minutes just for you.`) : T(`${items.length} Übungen, je ${dur} Sekunden.`, `${items.length} exercises, ${dur} seconds each.`);
      if (!ended) { ended = true; root.dispatchEvent(new CustomEvent('mvflow:done', { bubbles: true })); }
    }
    q('[data-go]').addEventListener('click', () => {
      actx(); q('[data-s]').hidden = true; q('[data-done]').hidden = true; q('[data-p]').hidden = false; idx = 0; run = true; show(0); play();
      try { navigator.wakeLock && navigator.wakeLock.request('screen').catch(() => {}); } catch (e) {}
    });
    q('[data-again]').addEventListener('click', () => { q('[data-done]').hidden = true; q('[data-s]').hidden = false; });
    q('[data-pause]').addEventListener('click', () => run ? stop() : play());
    q('[data-snd]').addEventListener('click', e => { sound = !sound; e.currentTarget.setAttribute('aria-pressed', String(sound)); e.currentTarget.textContent = sound ? T('Klang an', 'Sound on') : T('Klang aus', 'Sound off'); });
    if (pv) {
      q('[data-skip]').addEventListener('click', () => { if (sw) return; if (idx < items.length - 1) { idx++; show(idx); } else finish(); });
      q('[data-prev]').addEventListener('click', () => { if (sw) return; idx = Math.max(0, idx - 1); show(idx); });
    }
    return { stop: () => { stop(); q('[data-if]').src = 'about:blank'; } };
  }
  window.MvFlow = MvFlow;
  function auto() { document.querySelectorAll('[data-mv-flow]').forEach(el => { if (el.dataset.mvfOn) return; el.dataset.mvfOn = '1'; try { MvFlow(el, JSON.parse(el.dataset.mvFlow)); } catch (e) {} }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', auto); else auto();
})();

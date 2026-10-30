/* Movecia Übungs-Player (Vivien, 26.10.2026; neu gestaltet 27.10.2026: wärmer, freundlicher, schönere Musikauswahl)
   Jede Übung ist ein kurzer Clip von Vivien (Bunny-Embed, läuft stumm weiter). Zwei Formen:
   · Flow: Vivien spricht pro Übung ein Voice-over, der Timer läuft so lange wie die Übung (meist 2 Minuten).
   · Bewegungsvideo: ohne Sprechen, die Teilnehmerin wählt 30 oder 45 Sekunden pro Übung.
   In den letzten 5 Sekunden blüht ein sanfter „Sonnenaufgang“-Klang auf, dann kommt die nächste Übung von selbst.
   Kein Vorspulen und kein Überspringen: Das Programm soll begleiten, nicht abgehakt werden. Am Ende meldet der
   Player „mvflow:done“ am Element (der Mitgliederbereich speichert dann „erledigt“).
   Sprechtexte werden nie angezeigt (Vivien, 27.10.2026), auch nicht in der Vorschau.
   Dazu ein Musikfeld: Spotify, Apple Music, YouTube Music, Deezer, SoundCloud direkt auf der Seite, Amazon Music daneben.
   Aufruf: MvFlow(element, {lang, mode, title, intro, photo, items:[{name, src, sec, voice}], music:[{title,url}], preview})
   oder automatisch für jedes [data-mv-flow]. */
(function () {
  const CSS = `
.mvf{--ink:#2A2026;--muted:#6E6168;--line:#E3D8CD;--card:#FBF9F6;--plum:#4A3040;--deep:#2E1D27;--blush:#E4DCD1;--peach:#E6DBCF;--glow:#F1C9A5;--cream:#FFFCF8;
  display:flex;flex-direction:column;gap:16px;color:var(--ink);font-family:inherit;font-size:1rem}
.mvf *{box-sizing:border-box}
.mvf [hidden]{display:none!important}
.mvf button{font:inherit;color:inherit}
.mvf button:focus-visible,.mvf a:focus-visible,.mvf input:focus-visible{outline:3px solid var(--plum);outline-offset:2px}
.mvf svg.i{width:1.15em;height:1.15em;flex:none;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.mvf .serif{font-family:"Cormorant","Cormorant Garamond",Georgia,serif;font-weight:500}

/* Start */
.mvf-intro{position:relative;overflow:hidden;border-radius:30px;padding:clamp(22px,4vw,40px);
  background:radial-gradient(120% 90% at 100% 0%,#F6E3D6 0%,rgba(246,227,214,0) 60%),radial-gradient(90% 80% at 0% 100%,#F1E3E2 0%,rgba(241,227,226,0) 60%),var(--cream);
  border:1px solid var(--line);display:flex;flex-direction:column;gap:18px}
.mvf-intro>*{position:relative;z-index:1}
.mvf-itx{display:flex;flex-direction:column;gap:18px;min-width:0}
.mvf-pic{margin:0;border-radius:200px 200px 26px 26px;overflow:hidden;background:var(--peach);aspect-ratio:4/5;align-self:start}
.mvf-pic img{width:100%;height:100%;object-fit:cover;object-position:center 25%;display:block;-webkit-user-drag:none;user-select:none}
.mvf-intro.hasp{display:grid;grid-template-columns:1fr;gap:22px}
@media(min-width:860px){.mvf-intro.hasp{grid-template-columns:minmax(0,1.5fr) minmax(220px,.75fr);gap:clamp(28px,4vw,56px);align-items:center}.mvf-intro.hasp .mvf-ex{grid-template-columns:1fr}}
@media(min-width:1180px){.mvf-intro.hasp .mvf-ex{grid-template-columns:1fr 1fr}}
@media(max-width:859px){.mvf-pic{order:-1;aspect-ratio:16/11;border-radius:160px 160px 22px 22px}}
.mvf-blob{position:absolute!important;z-index:0!important;right:-60px;top:-50px;width:240px;height:240px;opacity:.55;pointer-events:none}
.mvf-wave{position:absolute!important;z-index:0!important;left:0;right:0;bottom:0;width:100%;height:90px;pointer-events:none;opacity:.6}
.mvf-eye{display:flex;align-items:center;gap:8px;font-size:.78rem;letter-spacing:.18em;text-transform:uppercase;color:var(--plum);margin:0}
.mvf-eye i{width:22px;height:1px;background:currentColor;opacity:.5}
.mvf-title{margin:0;font-family:"Cormorant","Cormorant Garamond",Georgia,serif;font-weight:500;font-style:italic;font-size:clamp(2rem,5vw,3rem);line-height:1.05;color:var(--deep)}
.mvf-desc{margin:0;color:#5E5157;line-height:1.6;max-width:38em}
.mvf-meta{display:flex;flex-wrap:wrap;gap:8px}
.mvf-chip{display:inline-flex;align-items:center;gap:7px;border-radius:999px;background:rgba(255,255,255,.75);border:1px solid var(--line);padding:7px 14px;font-size:.92rem;color:var(--deep)}
.mvf-ex{list-style:none;margin:0;padding:0;display:grid;gap:4px 22px;counter-reset:ex}
@media(min-width:620px){.mvf-ex{grid-template-columns:1fr 1fr}}
.mvf-ex li{counter-increment:ex;display:flex;align-items:center;gap:12px;padding:6px 0;border-bottom:1px dashed rgba(125,94,104,.18);font-size:.97rem}
.mvf-ex li::before{content:counter(ex);flex:none;width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:var(--blush);color:var(--plum);font-size:.8rem}
.mvf-lbl{margin:0;font-size:.95rem;color:var(--deep)}
.mvf-seg{display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:440px}
.mvf-seg button{border:1.5px solid var(--line);background:rgba(255,255,255,.8);border-radius:22px;padding:14px 12px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px;transition:border-color .2s,background .2s,transform .2s}
.mvf-seg button:hover{transform:translateY(-1px)}
.mvf-seg b{font-family:"Cormorant","Cormorant Garamond",Georgia,serif;font-weight:500;font-size:2.2rem;line-height:1;color:var(--deep)}
.mvf-seg span{font-size:.84rem;color:var(--muted)}
.mvf-seg button[aria-pressed="true"]{border-color:var(--plum);background:var(--blush)}
.mvf-go{align-self:flex-start;display:inline-flex;align-items:center;gap:12px;border:0;border-radius:999px;background:var(--deep);color:#F7F1EE!important;padding:10px 26px 10px 10px;cursor:pointer;font-size:1.05rem;font-weight:500;box-shadow:0 14px 30px -16px rgba(74,58,65,.7);transition:transform .2s,background .2s}
.mvf-go:hover{transform:translateY(-2px);background:var(--plum)}
.mvf-go .pl{width:44px;height:44px;border-radius:50%;background:var(--peach);color:var(--deep);display:grid;place-items:center}
.mvf-go .pl svg{width:18px;height:18px;fill:currentColor;stroke:none;margin-left:3px}

/* Abspielen */
.mvf-play,.mvf-done{scroll-margin-top:150px}
.mvf-play{display:flex;flex-direction:column;gap:14px}
.mvf-stage{position:relative;aspect-ratio:16/9;width:100%;border-radius:30px;overflow:hidden;background:radial-gradient(circle at 30% 30%,#8C727B,#2E1D27)}
.mvf-stage iframe{position:absolute;inset:0;width:100%;height:100%;border:0;pointer-events:none}
.mvf-ph{position:absolute;inset:0;display:flex;flex-direction:column;gap:10px;align-items:center;justify-content:center;color:#E4DCD1;font-size:.95rem;text-align:center;padding:1rem}
.mvf-ph .mk{width:64px;height:64px;opacity:.9}
.mvf-shade{position:absolute;left:0;right:0;bottom:0;height:55%;background:linear-gradient(to top,rgba(46,37,41,.62),rgba(46,37,41,0));pointer-events:none}
.mvf-name{position:absolute;left:clamp(14px,3vw,26px);bottom:clamp(12px,3vw,24px);right:clamp(110px,24vw,170px);color:#F7F1EE}
.mvf-name small{display:block;font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;opacity:.85;margin-bottom:2px}
.mvf-name b{display:block;font-family:"Cormorant","Cormorant Garamond",Georgia,serif;font-weight:500;font-size:clamp(1.35rem,3.6vw,2.2rem);line-height:1.08;text-shadow:0 1px 10px rgba(0,0,0,.25)}
.mvf-flag{position:absolute;left:14px;top:14px;font-size:.78rem;border-radius:999px;padding:5px 12px;background:rgba(251,248,244,.9);color:var(--deep)}
.mvf-ring{position:absolute;right:clamp(12px,2.6vw,22px);bottom:clamp(12px,2.6vw,22px);width:clamp(84px,18vw,128px);aspect-ratio:1}
.mvf-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.mvf-ring .t{fill:rgba(255,252,248,.9);stroke:rgba(255,255,255,.35);stroke-width:6}
.mvf-ring .p{fill:none;stroke:var(--plum);stroke-width:6;stroke-linecap:round;transition:stroke .6s}
.mvf-ring .n{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--deep)}
.mvf-ring .n b{font-family:"Cormorant","Cormorant Garamond",Georgia,serif;font-weight:600;font-size:clamp(2rem,6vw,3rem);line-height:1;font-variant-numeric:tabular-nums}
.mvf-ring .n b.mm{font-size:clamp(1.55rem,4.6vw,2.3rem)}
.mvf-ring .n span{margin-top:.35em;font-size:.6rem;letter-spacing:.16em;text-transform:uppercase;opacity:.65}
.mvf-stage.end .mvf-ring .p{stroke:#C98A5E}
.mvf-sun{position:absolute;left:0;right:0;bottom:-45%;height:85%;background:radial-gradient(closest-side,rgba(241,201,165,.85),transparent);opacity:0;transition:opacity 1.4s ease;pointer-events:none}
.mvf-stage.end .mvf-sun{opacity:1}
.mvf-next{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;background:rgba(74,58,65,.62);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:#F7F1EE;opacity:0;pointer-events:none;transition:opacity .5s;text-align:center;padding:1rem}
.mvf-next.on{opacity:1}
.mvf-next small{font-size:.74rem;letter-spacing:.2em;text-transform:uppercase;opacity:.85}
.mvf-next b{font-family:"Cormorant","Cormorant Garamond",Georgia,serif;font-weight:500;font-style:italic;font-size:clamp(1.6rem,4.4vw,2.6rem)}
.mvf-pausey{position:absolute;inset:0;display:grid;place-items:center;background:rgba(46,37,41,.28);opacity:0;pointer-events:none;transition:opacity .3s}
.mvf-pausey span{background:rgba(251,248,244,.92);color:var(--deep);border-radius:999px;padding:10px 20px;font-size:.95rem}
.mvf-stage.paused .mvf-pausey{opacity:1}

.mvf-bar{display:grid;gap:8px;background:var(--cream);border:1px solid var(--line);border-radius:24px;padding:16px 18px}
.mvf-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:6px 14px;font-size:.95rem}
.mvf-row .left{color:var(--muted)}
.mvf-row .rest{display:inline-flex;align-items:center;gap:7px;color:var(--deep);font-weight:500}
.mvf-track{height:6px;border-radius:9px;background:var(--blush);overflow:hidden}
.mvf-track i{display:block;height:100%;width:0;border-radius:9px;background:linear-gradient(90deg,#B99AA3,var(--plum));transition:width .9s linear}
.mvf-up{display:flex;align-items:center;gap:10px;font-size:.95rem;color:var(--muted);padding-top:4px}
.mvf-up b{font-weight:500;color:var(--deep)}

.mvf-ctrl{display:flex;align-items:center;justify-content:center;gap:16px;flex-wrap:wrap;padding:4px 0}
.mvf-rb{display:flex;flex-direction:column;align-items:center;gap:6px;background:none;border:0;cursor:pointer;font-size:.82rem;color:var(--muted)}
.mvf-rb .c{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;background:var(--cream);border:1px solid var(--line);color:var(--deep);transition:background .2s,transform .2s}
.mvf-rb:hover .c{transform:translateY(-1px);background:#fff}
.mvf-rb.main .c{width:70px;height:70px;background:var(--deep);border-color:var(--deep);color:#F7F1EE}
.mvf-rb.main svg.i{width:26px;height:26px}
.mvf-rb.main .c svg.f{fill:currentColor;stroke:none}
.mvf-rb[aria-pressed="false"] .c{color:var(--muted);background:transparent}
.mvf-pv{display:flex;justify-content:center;gap:14px;font-size:.88rem;color:var(--muted)}
.mvf-pv button{background:none;border:0;cursor:pointer;color:var(--plum);text-decoration:underline;text-underline-offset:3px;padding:4px}

/* Geschafft */
.mvf-done{position:relative;overflow:hidden;border-radius:30px;padding:clamp(28px,5vw,48px) 24px;text-align:center;display:flex;flex-direction:column;gap:12px;align-items:center;border:1px solid var(--line);
  background:radial-gradient(90% 90% at 50% 0%,#F6E3D6 0%,rgba(246,227,214,0) 70%),var(--cream)}
.mvf-done>*{position:relative;z-index:1}
.mvf-done .mk{width:70px;height:70px}
.mvf-done h3{font-family:"Cormorant","Cormorant Garamond",Georgia,serif;font-weight:500;font-style:italic;font-size:clamp(1.8rem,4.4vw,2.5rem);line-height:1.1;margin:0;color:var(--deep);max-width:16em}
.mvf-done p{margin:0;color:#5E5157}
.mvf-again{margin-top:8px;border:1.5px solid var(--line);background:rgba(255,255,255,.8);border-radius:999px;padding:11px 22px;cursor:pointer;color:var(--deep)!important}

/* Musik */
.mvf-mu{border-radius:26px;background:var(--cream);border:1px solid var(--line);overflow:hidden}
.mvf-mu>summary{list-style:none;cursor:pointer;padding:14px 18px;display:flex;align-items:center;gap:14px}
.mvf-mu>summary::-webkit-details-marker{display:none}
.mvf-mu .ico{width:44px;height:44px;border-radius:50%;background:var(--peach);color:var(--deep);display:grid;place-items:center;flex:none}
.mvf-mu .ico svg{width:20px;height:20px}
.mvf-mu .tt{display:flex;flex-direction:column;gap:1px;min-width:0}
.mvf-mu .tt b{font-weight:500;color:var(--deep)}
.mvf-mu .tt span{font-size:.86rem;color:var(--muted)}
.mvf-mu .chev{margin-left:auto;color:var(--muted);transition:transform .25s}
.mvf-mu[open] .chev{transform:rotate(180deg)}
.mvf-mub{padding:2px 18px 20px;display:flex;flex-direction:column;gap:14px}
.mvf-sm{font-size:.86rem;color:var(--muted);margin:0;line-height:1.5}
.mvf-sm a{color:var(--plum)}
.mvf-prov{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
@media(max-width:420px){.mvf-prov{grid-template-columns:repeat(2,1fr)}}
.mvf-prov button{display:flex;align-items:center;gap:9px;border:1.5px solid var(--line);background:#fff;border-radius:16px;padding:11px 12px;cursor:pointer;font-size:.9rem;text-align:left;transition:border-color .2s,background .2s}
.mvf-prov button i{width:10px;height:10px;border-radius:50%;flex:none}
.mvf-prov button[aria-pressed="true"]{border-color:var(--plum);background:var(--blush)}
.mvf-sug{display:grid;gap:8px}
@media(min-width:560px){.mvf-sug{grid-template-columns:1fr 1fr}}
.mvf-sug button{display:flex;align-items:center;gap:12px;border:1px solid var(--line);background:#fff;border-radius:16px;padding:10px 12px;cursor:pointer;text-align:left;font-size:.92rem;color:var(--deep)}
.mvf-sug button span{width:34px;height:34px;border-radius:10px;flex:none;display:grid;place-items:center;background:linear-gradient(135deg,#E4DCD1,#E6DBCF);color:var(--plum)}
.mvf-sug button span svg{width:16px;height:16px}
.mvf-sug button:hover{border-color:var(--plum)}
.mvf-k{margin:0;font-size:.74rem;letter-spacing:.16em;text-transform:uppercase;color:var(--muted)}
.mvf-in{display:flex;gap:8px;flex-wrap:wrap}
.mvf-in input{flex:1 1 220px;min-width:0;font:inherit;font-size:.95rem;border:1.5px solid var(--line);border-radius:16px;padding:12px 14px;background:#fff;color:var(--ink)}
.mvf-btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:999px;background:var(--plum);color:#fff!important;padding:11px 20px;cursor:pointer;text-decoration:none;font-size:.95rem}
.mvf-btn.l{background:#fff;color:var(--deep)!important;border:1.5px solid var(--line)}
.mvf-dock{border-radius:20px;overflow:hidden}
.mvf-dock iframe{display:block;width:100%;border:0;border-radius:14px}
.mvf-err{color:#8C3B3B;font-size:.88rem;margin:0}
@media(max-width:560px){.mvf-go{align-self:stretch;justify-content:center}.mvf-ctrl{gap:22px}}`;
  function css() { if (document.getElementById('mvf-css')) return; const s = document.createElement('style'); s.id = 'mvf-css'; s.textContent = CSS; document.head.appendChild(s); }
  const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const ICON = {
    clock: '<svg class="i" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>',
    play: '<svg viewBox="0 0 24 24"><path d="M7 4.5v15l12.5-7.5z"/></svg>',
    pause: '<svg class="i f" viewBox="0 0 24 24"><rect x="6.5" y="5" width="3.6" height="14" rx="1.4"/><rect x="13.9" y="5" width="3.6" height="14" rx="1.4"/></svg>',
    resume: '<svg class="i f" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>',
    bell: '<svg class="i" viewBox="0 0 24 24"><path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 1.5h-15z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/></svg>',
    note: '<svg class="i" viewBox="0 0 24 24"><path d="M9 18V5.5l11-2V16"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/></svg>',
    chev: '<svg class="i" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>',
    next: '<svg class="i" viewBox="0 0 24 24"><path d="M5 12h13m-5-5 5 5-5 5"/></svg>',
    paste: '<svg class="i" viewBox="0 0 24 24"><rect x="7" y="4" width="10" height="4" rx="1.2"/><path d="M7 6H5.5A1.5 1.5 0 0 0 4 7.5v12A1.5 1.5 0 0 0 5.5 21h13a1.5 1.5 0 0 0 1.5-1.5v-12A1.5 1.5 0 0 0 18.5 6H17"/></svg>'
  };
  // Movecia-Zeichen: weiche Form, Kiesel-Umriss, Punkt
  const MARK = '<img class="mk" src="/img/movecia/movecia-zeichen.svg" alt="" draggable="false">';
  const BLOB = '<svg class="mvf-blob" viewBox="0 0 200 200" aria-hidden="true"><path d="M150 40c26 22 34 64 12 98s-70 48-104 30S12 108 26 72 70 22 100 22s30 0 50 18z" fill="#EBD3C6"/><path d="M146 52c20 20 24 56 6 84s-58 38-88 24-40-50-26-80 46-44 72-44 20 0 36 16z" fill="none" stroke="#C9AEB5" stroke-width="1.4"/></svg>';
  const WAVE = '<svg class="mvf-wave" viewBox="0 0 800 90" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60 C120 30 220 85 360 58 S600 20 800 52 V90 H0z" fill="#F1E3E2"/><path d="M0 72 C150 52 260 92 420 70 S650 46 800 68" fill="none" stroke="#D9C3C6" stroke-width="1.2"/></svg>';

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
    spotify: { n: 'Spotify', c: '#1DB954', h: 152, login: 'https://accounts.spotify.com/login', ex: 'open.spotify.com/playlist/…' },
    apple: { n: 'Apple Music', c: '#FA2D48', h: 175, login: 'https://music.apple.com/', ex: 'music.apple.com/at/playlist/…' },
    youtube: { n: 'YouTube Music', c: '#FF0033', h: 200, login: 'https://music.youtube.com/', ex: 'music.youtube.com/playlist?list=…' },
    deezer: { n: 'Deezer', c: '#A238FF', h: 150, login: 'https://www.deezer.com/login', ex: 'deezer.com/playlist/…' },
    soundcloud: { n: 'SoundCloud', c: '#FF5500', h: 166, login: 'https://soundcloud.com/signin', ex: 'soundcloud.com/…/sets/…' },
    amazon: { n: 'Amazon Music', c: '#25D1DA', h: 0, login: 'https://music.amazon.de/', ex: '' }
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
    const provBtns = Object.keys(PROV).map(k => `<button type="button" data-pv="${k}"><i style="background:${PROV[k].c}"></i>${PROV[k].n}</button>`).join('');
    box.querySelector('[data-mub]').innerHTML = `
      <p class="mvf-sm">${flow ? T('Wenn du magst, läuft deine eigene Musik leise im Hintergrund. Stell sie so leise, dass du meine Stimme gut hörst.', 'If you like, your own music can play softly in the background. Keep it low enough to hear my voice.') : T('Such dir aus, womit du Musik hörst, und leg deine Lieblings-Playlist dazu.', 'Choose where you listen to music and add your favourite playlist.')}</p>
      <div class="mvf-prov" role="group" aria-label="${T('Musikdienst', 'Music service')}">${provBtns}</div>
      <div data-pbody></div>
      <p class="mvf-err" data-merr hidden></p>
      <p class="mvf-sm"><button type="button" class="mvf-btn l" data-moff hidden>${T('Musik ausschalten', 'Turn music off')}</button></p>
      <p class="mvf-sm">${T('Ganze Songs hörst du, wenn du bei deinem Musikdienst angemeldet bist. Der Player lädt erst, wenn du eine Playlist startest.', 'You’ll hear full songs when you’re logged in to your music service. The player only loads once you start a playlist.')} <a href="/terms/#musik" target="_blank" rel="noopener">${T('Datenschutz', 'Privacy')}</a></p>`;
    const pbody = box.querySelector('[data-pbody]'), err = box.querySelector('[data-merr]'), off = box.querySelector('[data-moff]'), lab = box.querySelector('[data-mstate]');
    function save() { try { localStorage.setItem('mvf-music', JSON.stringify(st)); } catch (e) {} }
    function mount() {
      const e = st.url ? embedOf(st.url) : null;
      dock.innerHTML = ''; dock.hidden = true; off.hidden = !st.url;
      if (e && e.src) {
        const h = e.p === 'spotify' ? 80 : PROV[e.p].h;
        dock.innerHTML = `<iframe src="${esc(e.src)}" height="${h}" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy" title="${esc(PROV[e.p].n)}"></iframe>`;
        dock.hidden = false; lab.textContent = PROV[e.p].n + ' ' + T('ist verbunden', 'is connected');
      } else lab.textContent = T('Spotify, Apple Music und mehr', 'Spotify, Apple Music and more');
    }
    function draw() {
      box.querySelectorAll('[data-pv]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.pv === prov)));
      const P = PROV[prov];
      if (prov === 'amazon') {
        pbody.innerHTML = `<p class="mvf-sm">${T('Amazon Music lässt sich leider nicht direkt hier einbauen. Starte deine Playlist in der Amazon-Music-App und komm einfach wieder zurück, die Musik läuft weiter.', 'Amazon Music can’t be built in here, unfortunately. Start your playlist in the Amazon Music app and come back, the music keeps playing.')}</p>
          <p class="mvf-sm"><a class="mvf-btn" href="${P.login}" target="_blank" rel="noopener">${T('Amazon Music öffnen', 'Open Amazon Music')} ↗</a></p>`;
        return;
      }
      const sug = prov === 'spotify' && lists.length ? `<p class="mvf-k">${T('Meine Lieblings-Playlists', 'My favourite playlists')}</p><div class="mvf-sug">${lists.map((x, i) => `<button type="button" data-sg="${i}"><span>${ICON.note}</span>${esc(x.title)}</button>`).join('')}</div>` : '';
      pbody.innerHTML = `<div style="display:flex;flex-direction:column;gap:12px">${sug}
        <p class="mvf-k">${T('Deine eigene Playlist', 'Your own playlist')}</p>
        <div class="mvf-in"><input type="url" inputmode="url" data-murl placeholder="${esc(P.ex)}" value="${esc(st.p === prov ? st.url || '' : '')}" aria-label="${T('Link zur Playlist', 'Playlist link')}">
          ${navigator.clipboard && navigator.clipboard.readText ? `<button type="button" class="mvf-btn l" data-mpaste>${ICON.paste}${T('Einfügen', 'Paste')}</button>` : ''}
          <button type="button" class="mvf-btn" data-mgo>${T('Abspielen', 'Play')}</button></div>
        <p class="mvf-sm">${T('Den Link findest du in der App bei deiner Playlist unter „Teilen“ → „Link kopieren“.', 'You’ll find the link in the app on your playlist under “Share” → “Copy link”.')} <a href="${P.login}" target="_blank" rel="noopener">${T('Bei ' + P.n + ' anmelden', 'Log in to ' + P.n)} ↗</a></p></div>`;
      const inp = pbody.querySelector('[data-murl]'), go = pbody.querySelector('[data-mgo]'), ps = pbody.querySelector('[data-mpaste]');
      go.addEventListener('click', () => {
        const e = embedOf(inp.value);
        if (!e) { err.textContent = T('Diesen Link kann ich leider nicht abspielen. Nimm bitte den Link über „Teilen“ → „Link kopieren“.', 'Sorry, I can’t play this link. Please use “Share” → “Copy link”.'); err.hidden = false; return; }
        err.hidden = true;
        if (e.p === 'amazon') { window.open(e.open, '_blank', 'noopener'); return; }
        prov = e.p; st = { p: e.p, url: inp.value.trim() }; save(); mount(); draw();
      });
      if (ps) ps.addEventListener('click', () => { navigator.clipboard.readText().then(t => { inp.value = String(t || '').trim(); if (inp.value) go.click(); }).catch(() => inp.focus()); });
      inp.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); go.click(); } });
      pbody.querySelectorAll('[data-sg]').forEach(b => b.addEventListener('click', () => { st = { p: 'spotify', url: lists[+b.dataset.sg].url }; save(); mount(); draw(); }));
    }
    box.querySelectorAll('[data-pv]').forEach(b => b.addEventListener('click', () => { prov = b.dataset.pv; err.hidden = true; draw(); }));
    off.addEventListener('click', () => { st = {}; save(); mount(); draw(); });
    draw();
    // Fremde Musikdienste laden erst, wenn sie die Musik selbst aufklappt (Datenschutz)
    if (st.url) { lab.textContent = ((PROV[st.p] || {}).n || '') + ' · ' + T('zum Laden antippen', 'tap to load'); off.hidden = false; }
    else lab.textContent = T('Spotify, Apple Music und mehr', 'Spotify, Apple Music and more');
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
    const mins = s => Math.max(1, Math.round(s / 60));
    root.classList.add('mvf');
    root.innerHTML = `
<div class="mvf-intro${data.photo ? ' hasp' : ''}" data-s>
  ${data.photo ? '' : BLOB}${WAVE}
  <div class="mvf-itx">
  <p class="mvf-eye"><i></i>${flow ? T('Dein Flow', 'Your flow') : T('Dein Bewegungsvideo', 'Your movement session')}</p>
  ${data.title ? `<h2 class="mvf-title">${esc(data.title)}</h2>` : ''}
  ${data.intro ? `<p class="mvf-desc">${esc(data.intro)}</p>` : ''}
  <div class="mvf-meta"><span class="mvf-chip">${ICON.clock}<span data-tot></span></span></div>
  ${flow ? '' : `<p class="mvf-lbl">${T('Wie lange magst du jede Übung halten?', 'How long would you like to hold each exercise?')}</p>
  <div class="mvf-seg" role="group">
    <button type="button" data-d="30"><b>30</b><span>${T('Sekunden · sanft', 'seconds · gentle')}</span></button>
    <button type="button" data-d="45"><b>45</b><span>${T('Sekunden · etwas länger', 'seconds · a little longer')}</span></button>
  </div>`}
  <ol class="mvf-ex">${items.map(x => `<li>${esc(x.name)}</li>`).join('')}</ol>
  <button type="button" class="mvf-go" data-go><span class="pl">${ICON.play}</span>${T("Los geht's", "Let's begin")}</button>
  </div>
  ${data.photo ? `<figure class="mvf-pic"><img src="${esc(data.photo)}" alt="" draggable="false"></figure>` : ''}
</div>
<div class="mvf-play" data-p hidden>
  <div class="mvf-stage" data-st>
    <iframe data-if title="" allow="autoplay;encrypted-media;picture-in-picture" loading="eager"></iframe>
    <div class="mvf-ph" data-ph hidden>${MARK}<span>${T('Clip folgt', 'Clip coming soon')}</span></div>
    <div class="mvf-sun"></div>
    <div class="mvf-shade"></div>
    ${pv && flow ? `<span class="mvf-flag" data-vflag hidden>${T('Vorschau · Voice-over fehlt noch', 'Preview · voice-over still missing')}</span>` : ''}
    <div class="mvf-name"><small data-tag></small><b data-nm></b></div>
    <div class="mvf-ring"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="t" cx="60" cy="60" r="52"></circle><circle class="p" data-pr cx="60" cy="60" r="52"></circle></svg><div class="n"><b data-sec>30</b><span data-unit>${T('Sek.', 'sec')}</span></div></div>
    <div class="mvf-pausey"><span>${T('Pausiert', 'Paused')}</span></div>
    <div class="mvf-next" data-nx><small>${T('Als Nächstes', 'Up next')}</small><b data-nxn></b></div>
  </div>
  <div class="mvf-bar">
    <div class="mvf-row"><span class="left" data-pos></span><span class="rest">${ICON.clock}<span data-rest></span></span></div>
    <div class="mvf-track"><i data-trk></i></div>
    <div class="mvf-up" data-up>${ICON.next}<span data-af></span></div>
  </div>
  <div class="mvf-ctrl">
    <button type="button" class="mvf-rb" data-snd aria-pressed="true"><span class="c">${ICON.bell}</span><span data-sndl>${T('Klang an', 'Chime on')}</span></button>
    <button type="button" class="mvf-rb main" data-pause><span class="c" data-pi>${ICON.pause}</span><span data-pl>Pause</span></button>
    <button type="button" class="mvf-rb" data-mus><span class="c">${ICON.note}</span><span>${T('Musik', 'Music')}</span></button>
  </div>
  ${pv ? `<div class="mvf-pv"><span>${T('Nur in deiner Vorschau:', 'Preview only:')}</span><button type="button" data-prev>‹ ${T('zurück', 'back')}</button><button type="button" data-skip>${T('weiter', 'next')} ›</button></div>` : ''}
</div>
<div class="mvf-done" data-done hidden>${WAVE}${MARK}<p class="mvf-eye" style="justify-content:center"><i></i>${T('Geschafft', 'Done')}<i></i></p><h3>${T('Schön, dass du dir diese Zeit genommen hast.', 'I’m so glad you took this time for yourself.')}</h3><p data-dt></p><button type="button" class="mvf-again" data-again>${T('Nochmal von vorn', 'Start again')}</button></div>
<details class="mvf-mu" data-mu><summary><span class="ico">${ICON.note}</span><span class="tt"><b>${T('Deine Musik dazu', 'Add your music')}</b><span data-mstate></span></span><span class="chev">${ICON.chev}</span></summary><div class="mvf-mub" data-mub></div></details>
<div class="mvf-dock" data-dock hidden></div>`;
    const q = s => root.querySelector(s);
    const pr = q('[data-pr]'); pr.style.strokeDasharray = C;
    musicBox(root, T, data.music || [], flow);
    function seg() {
      root.querySelectorAll('[data-d]').forEach(b => b.setAttribute('aria-pressed', String(+b.dataset.d === dur)));
      q('[data-tot]').textContent = T(`etwa ${mins(sum())} Minuten`, `about ${mins(sum())} minutes`);
    }
    root.querySelectorAll('[data-d]').forEach(b => b.addEventListener('click', () => { dur = +b.dataset.d; try { localStorage.setItem('mvf-dur', String(dur)); } catch (e) {} seg(); }));
    seg();
    function show(i) {
      const it = items[i];
      q('[data-tag]').textContent = T(`Übung ${i + 1}`, `Exercise ${i + 1}`);
      q('[data-nm]').textContent = it.name;
      q('[data-pos]').textContent = T(`Übung ${i + 1} von ${items.length}`, `Exercise ${i + 1} of ${items.length}`);
      const f = q('[data-if]'); f.title = it.name;
      if (it.src) { f.hidden = false; q('[data-ph]').hidden = true; if (f.getAttribute('src') !== it.src) f.src = it.src; }
      else { f.hidden = true; f.removeAttribute('src'); q('[data-ph]').hidden = false; }
      const n = items[i + 1];
      q('[data-af]').innerHTML = n ? T('Als Nächstes: ', 'Up next: ') + '<b>' + esc(n.name) + '</b>' : T('Das ist deine letzte Übung.', 'This is your last exercise.');
      total = secOf(i); left = total; chimed = false; q('[data-st]').classList.remove('end');
      const vf = q('[data-vflag]'); if (vf) vf.hidden = !!it.voice;
      voice.pause(); voice.onloadedmetadata = null;
      if (flow && it.voice) {
        // Ist das Voice-over länger als die Übung, läuft der Timer so lange wie die Stimme (plus kurzer Ausklang)
        voice.onloadedmetadata = () => { if (isFinite(voice.duration) && voice.duration + 4 > total) { const add = voice.duration + 4 - total; total += add; left += add; draw(); } };
        voice.src = it.voice;
        if (run) voice.play().catch(() => {});
      } else voice.removeAttribute('src');
      draw();
    }
    function rest() { let r = Math.max(0, left); for (let j = idx + 1; j < items.length; j++) r += secOf(j); return r; }
    function draw() {
      const b = q('[data-sec]'), mm = left >= 60;
      b.textContent = fmt(left); b.classList.toggle('mm', mm);
      q('[data-unit]').textContent = mm ? T('Min.', 'min') : T('Sek.', 'sec');
      pr.style.strokeDashoffset = C * (1 - Math.max(0, left) / total);
      const r = rest(), all = Math.max(1, sum() + Math.max(0, total - secOf(idx)));
      q('[data-rest]').textContent = r >= 60 ? T(`noch ${Math.ceil(r / 60)} Min.`, `${Math.ceil(r / 60)} min left`) : T(`noch ${Math.ceil(r)} Sek.`, `${Math.ceil(r)} sec left`);
      q('[data-trk]').style.width = Math.min(100, 100 * (1 - r / all)) + '%';
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
      setTimeout(() => { idx++; show(idx); q('[data-nx]').classList.remove('on'); sw = false; last = performance.now(); }, 1500);
    }
    function play() {
      run = true; last = performance.now(); q('[data-st]').classList.remove('paused');
      q('[data-pi]').innerHTML = ICON.pause; q('[data-pl]').textContent = 'Pause';
      if (flow && voice.getAttribute('src') && !sw) voice.play().catch(() => {});
      cancelAnimationFrame(raf); raf = requestAnimationFrame(tick);
    }
    function stop() {
      run = false; voice.pause(); q('[data-st]').classList.add('paused');
      q('[data-pi]').innerHTML = ICON.resume; q('[data-pl]').textContent = T('Weiter', 'Resume');
    }
    // Tab im Hintergrund: Timer anhalten, damit die Zeit nicht ohne sie verstreicht
    document.addEventListener('visibilitychange', () => { if (document.hidden && run && !pv) stop(); });
    document.addEventListener('mvdev:hold', () => { if (run) stop(); });
    function finish() {
      stop(); q('[data-st]').classList.remove('paused'); q('[data-p]').hidden = true; q('[data-done]').hidden = false; q('[data-if]').src = 'about:blank';
      q('[data-dt]').textContent = T(`Etwa ${mins(sum())} Minuten nur für dich.`, `About ${mins(sum())} minutes just for you.`);
      q('[data-done]').scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (!ended) { ended = true; root.dispatchEvent(new CustomEvent('mvflow:done', { bubbles: true })); }
    }
    q('[data-go]').addEventListener('click', () => {
      actx(); q('[data-s]').hidden = true; q('[data-done]').hidden = true; q('[data-p]').hidden = false; idx = 0; run = true; show(0); play();
      q('[data-p]').scrollIntoView({ behavior: 'smooth', block: 'start' });
      try { navigator.wakeLock && navigator.wakeLock.request('screen').catch(() => {}); } catch (e) {}
    });
    q('[data-again]').addEventListener('click', () => { q('[data-done]').hidden = true; q('[data-s]').hidden = false; });
    q('[data-pause]').addEventListener('click', () => run ? stop() : play());
    q('[data-snd]').addEventListener('click', e => { sound = !sound; e.currentTarget.setAttribute('aria-pressed', String(sound)); q('[data-sndl]').textContent = sound ? T('Klang an', 'Chime on') : T('Klang aus', 'Chime off'); });
    q('[data-mus]').addEventListener('click', () => { const m = q('[data-mu]'); m.open = true; m.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
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

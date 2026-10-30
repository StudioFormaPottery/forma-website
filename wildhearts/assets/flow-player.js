/* Movecia Übungs-Player (Vivien, 26.10.2026; neu gestaltet 27.10.2026: wärmer, freundlicher, schönere Musikauswahl)
   Jede Übung ist ein kurzer Clip von Vivien (Bunny-Embed, läuft stumm weiter). Zwei Formen:
   · Flow: Vivien spricht pro Übung ein Voice-over, der Timer läuft so lange wie die Übung (meist 2 Minuten).
   · Bewegungsvideo: ohne Sprechen, die Teilnehmerin wählt 30 oder 45 Sekunden pro Übung.
   In den letzten 5 Sekunden blüht ein sanfter „Sonnenaufgang“-Klang auf, dann kommt die nächste Übung von selbst.
   Kein Vorspulen und kein Überspringen: Das Programm soll begleiten, nicht abgehakt werden. Am Ende meldet der
   Player „mvflow:done“ am Element (der Mitgliederbereich speichert dann „erledigt“).
   Sprechtexte werden nie angezeigt (Vivien, 27.10.2026), auch nicht in der Vorschau.
   Musik: zu jedem Video die Musik aus der Verwaltung; wer lieber eigene hört, verbindet Spotify oder Apple Music (dann geht die Video-Musik aus).
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
.mvf .serif{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400}

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
.mvf .mvf-title{margin:0;line-height:1.12!important;font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400;letter-spacing:-.01em;font-size:clamp(1.7rem,4.2vw,2.5rem);line-height:1.1.05;color:var(--deep)}
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
.mvf-seg b{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400;font-size:1.9rem;line-height:1;color:var(--deep)}
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
.mvf-stage{background-size:cover;background-position:center}
.mvf-stage iframe{position:absolute;inset:0;width:100%;height:100%;border:0;pointer-events:none;opacity:0;transition:opacity .35s ease,filter 1.2s ease,transform 1.2s ease}
.mvf-stage iframe.on{opacity:1}
/* Einleitung (Vivien, 30.10.2026): Titelbild → Welle mit der ersten Übung, Bild weich verschwommen, 10 Sekunden in der Mitte */
.mvf-stage.ready iframe.on{filter:blur(18px) saturate(.9);transform:scale(1.1)}
.mvf-stage.ready .mvf-ring,.mvf-stage.ready .mvf-ph{opacity:0}
.mvf-ring{transition:opacity .6s}
.mvf-cd{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;color:#FFFCF8;text-align:center;opacity:0;pointer-events:none;transition:opacity .6s;text-shadow:0 2px 18px rgba(46,29,39,.45)}
.mvf-stage.ready .mvf-cd{opacity:1}
.mvf-cd b{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400;font-size:clamp(4rem,14cqw,8rem);line-height:1;font-variant-numeric:tabular-nums}
.mvf-cd span{font-size:clamp(.8rem,2.2cqw,1.05rem);letter-spacing:.18em;text-transform:uppercase;opacity:.9}
@container (max-width:560px){.mvf-cd{align-items:flex-end;justify-content:flex-start;padding:5% 7% 0 0;text-align:right}.mvf-cd b{font-size:26cqw}.mvf-cd span{font-size:.62rem;letter-spacing:.14em}}
.mvf-tc{position:absolute;inset:0;z-index:4;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;text-align:center;padding:6%;color:#FFFCF8;background:#4A3040 center/cover no-repeat;opacity:0;pointer-events:none;transition:opacity .8s ease}
.mvf-tc::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(46,29,39,.15),rgba(46,29,39,.55))}
.mvf-tc>*{position:relative}
.mvf-tc.on{opacity:1}
.mvf-tc small{font-size:clamp(.66rem,1.8cqw,.9rem);letter-spacing:.24em;text-transform:uppercase;opacity:.9}
.mvf-tc b{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400;letter-spacing:-.01em;font-size:clamp(1.5rem,5.4cqw,3rem);line-height:1.1;max-width:16em}
.mvf-tc img{width:clamp(40px,8cqw,70px);height:auto}
.mvf-ph{position:absolute;inset:0;display:flex;flex-direction:column;gap:10px;align-items:center;justify-content:center;color:#E4DCD1;font-size:.95rem;text-align:center;padding:1rem}
.mvf-ph .mk{width:64px;height:64px;opacity:.9}
.mvf-shade{position:absolute;left:0;right:0;bottom:0;height:55%;background:linear-gradient(to top,rgba(46,37,41,.62),rgba(46,37,41,0));pointer-events:none}
.mvf-stage{container-type:inline-size}
/* Übungsname als Welle mit Logo links unten (Vivien, 30.10.2026): läuft rechts in einer Spitze aus, blendet bei jeder neuen Übung kurz ein und nach 3,5 s wieder aus */
.mvf-name{position:absolute;left:0;bottom:0;width:70cqw;height:24cqw;max-height:190px;color:#F7F1EE;pointer-events:none;opacity:0;transform:translateY(14px);transition:opacity .7s ease,transform .8s cubic-bezier(.2,.8,.2,1)}
.mvf-name.on,.mvf-stage.ready .mvf-name{opacity:1;transform:none}
.mvf-name svg{position:absolute;inset:0;width:100%;height:100%}
.mvf-name .in{position:absolute;left:max(16px,4.2cqw);right:14cqw;top:38%;bottom:6%;display:flex;align-items:center;gap:max(10px,2.2cqw)}
.mvf-name .in>div{min-width:0}
.mvf-name .in b{font-size:clamp(1.05rem,3.8cqw,2rem);line-height:1.05;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-wrap:balance}
@container (max-width:560px){.mvf-name{width:88cqw;height:44cqw}.mvf-name .in{left:14px;right:30cqw;top:40%;bottom:8%;gap:9px}.mvf-name small{font-size:.54rem;letter-spacing:.16em;margin-bottom:2px}.mvf-name .in b{font-size:clamp(.92rem,4.6cqw,1.2rem)}.mvf-name .in img{width:26px;height:26px}}
.mvf-name .in img{width:clamp(28px,7.2cqw,58px);height:clamp(28px,7.2cqw,58px)}
.mvf-name small{display:block;font-size:clamp(.56rem,1.7cqw,.78rem);letter-spacing:.22em;text-transform:uppercase;opacity:.85;margin-bottom:.8cqw}
.mvf-name b{display:block;font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400;font-size:clamp(1.1rem,4.6cqw,2.2rem);line-height:1.05}
@media(prefers-reduced-motion:reduce){.mvf-name{transition:none}}
.mvf-flag{position:absolute;left:14px;top:14px;font-size:.78rem;border-radius:999px;padding:5px 12px;background:rgba(251,248,244,.9);color:var(--deep)}
.mvf-ring{position:absolute;right:clamp(12px,2.6vw,22px);bottom:clamp(12px,2.6vw,22px);width:clamp(84px,18vw,128px);aspect-ratio:1}
.mvf-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.mvf-ring .t{fill:rgba(255,252,248,.9);stroke:rgba(255,255,255,.35);stroke-width:6}
.mvf-ring .p{fill:none;stroke:var(--plum);stroke-width:6;stroke-linecap:round;transition:stroke .6s}
.mvf-ring .n{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--deep)}
.mvf-ring .n b{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400;font-size:clamp(1.7rem,5vw,2.5rem);line-height:1;font-variant-numeric:tabular-nums}
.mvf-ring .n b.mm{font-size:clamp(1.35rem,4vw,2rem)}
.mvf-ring .n span{margin-top:.35em;font-size:.6rem;letter-spacing:.16em;text-transform:uppercase;opacity:.65}
.mvf-stage.end .mvf-ring .p{stroke:#C98A5E}
.mvf-sun{position:absolute;left:0;right:0;bottom:-45%;height:85%;background:radial-gradient(closest-side,rgba(241,201,165,.85),transparent);opacity:0;transition:opacity 1.4s ease;pointer-events:none}
.mvf-stage.end .mvf-sun{opacity:1}
.mvf-next{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;background:rgba(74,58,65,.62);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:#F7F1EE;opacity:0;pointer-events:none;transition:opacity .5s;text-align:center;padding:1rem}
.mvf-next.on{opacity:1}
.mvf-next small{font-size:.74rem;letter-spacing:.2em;text-transform:uppercase;opacity:.85}
.mvf-next b{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400;letter-spacing:-.01em;font-size:clamp(1.4rem,3.8vw,2.2rem)}
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
.mvf-done .mvf-dh{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;font-weight:400;letter-spacing:-.01em;font-size:clamp(1.5rem,3.8vw,2.1rem);line-height:1.15.1;margin:0;color:var(--deep);max-width:16em}
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
.mvf-prov{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;max-width:420px}
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
  let ac = null, pjP = null;
  // Am Computer (v. a. Safari) blieb der Web-Audio-Ton stumm (Vivien, 30.10.2026: keine Musik, keine Glocke). Deshalb dort
  // Glocke und Musik über normale Audio-Elemente; Web Audio nur noch am iPhone/iPad (dort lässt sich die Lautstärke sonst nicht regeln).
  const IOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  function actx() { if (IOS) { try { if (navigator.audioSession) navigator.audioSession.type = 'playback'; } catch (e) {} } // iPhone: Klang auch im Lautlos-Modus
    if (!ac) { try { ac = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { ac = null; } } if (ac && ac.state !== 'running') { try { ac.resume(); } catch (e) {} } return ac; }
  // Klänge einmal vorab als WAV berechnen (OfflineAudioContext braucht keinen Klick) und über <audio> abspielen
  function wavUrl(buf) {
    const d = buf.getChannelData(0), n = d.length, b = new ArrayBuffer(44 + n * 2), v = new DataView(b), w = (o, t) => { for (let i = 0; i < t.length; i++) v.setUint8(o + i, t.charCodeAt(i)); };
    w(0, 'RIFF'); v.setUint32(4, 36 + n * 2, true); w(8, 'WAVE'); w(12, 'fmt '); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
    v.setUint32(24, buf.sampleRate, true); v.setUint32(28, buf.sampleRate * 2, true); v.setUint16(32, 2, true); v.setUint16(34, 16, true); w(36, 'data'); v.setUint32(40, n * 2, true);
    for (let i = 0; i < n; i++) v.setInt16(44 + i * 2, Math.max(-1, Math.min(1, d[i])) * 32767, true);
    return URL.createObjectURL(new Blob([b], { type: 'audio/wav' }));
  }
  const SND = {};
  function sndPrep() {
    if (SND.ready) return; SND.ready = 1;
    const OC = window.OfflineAudioContext || window.webkitOfflineAudioContext; if (!OC) return;
    const mk = (k, len, fn) => { try { const oc = new OC(1, Math.ceil(44100 * len), 44100); fn(oc); const r = oc.startRendering(); const done = buf => { SND[k] = wavUrl(buf); }; if (r && r.then) r.then(done); else oc.oncomplete = e => done(e.renderedBuffer); } catch (e) {} };
    mk('lo', 1.1, oc => bellOn(oc, 0, false)); mk('hi', 1.8, oc => bellOn(oc, 0, true)); mk('sun', 7.8, oc => sunriseOn(oc, 0, 5));
    SND.el = [new Audio(), new Audio(), new Audio()]; SND.k = 0;
  }
  function sndUnlock() { sndPrep(); (SND.el || []).forEach(el => { try { el.muted = true; el.src = SND.sil || (SND.sil = wavUrl({ getChannelData: () => new Float32Array(2205), sampleRate: 44100 })); const p = el.play(); if (p && p.then) p.then(() => { el.pause(); el.muted = false; }).catch(() => { el.muted = false; }); else el.muted = false; } catch (e) {} }); }
  function sndPlay(k) { if (!SND.el || !SND[k]) return false; const el = SND.el[SND.k++ % SND.el.length]; try { el.muted = false; el.volume = 1; el.src = SND[k]; el.currentTime = 0; const p = el.play(); if (p && p.catch) p.catch(() => {}); return true; } catch (e) { return false; } }
  // Countdown-Klang (Vivien, 30.10.2026: „in den letzten Sekunden hört man den Timer nicht“): klare, weiche Glocke bei 3, 2, 1, heller bei 0
  function bell(hi) { if (!IOS && sndPlay(hi ? 'hi' : 'lo')) return; const a = actx(); if (a) bellOn(a, a.currentTime, hi); }
  function sunrise(len) { if (!IOS && sndPlay('sun')) return; const a = actx(); if (a) sunriseOn(a, a.currentTime, len); }
  function bellOn(a, t, hi) {
    const out = a.createGain(); out.gain.value = .0001; out.connect(a.destination);
    out.gain.exponentialRampToValueAtTime(hi ? .5 : .38, t + .015); out.gain.exponentialRampToValueAtTime(.0001, t + (hi ? 1.6 : .9));
    [[hi ? 880 : 660, 1], [hi ? 1320 : 990, .35], [hi ? 1760 : 1320, .12]].forEach(([f, v]) => { const o = a.createOscillator(), g = a.createGain(); o.type = 'sine'; o.frequency.value = f; g.gain.value = v; o.connect(g).connect(out); o.start(t); o.stop(t + (hi ? 1.7 : 1)); });
  }
  function sunriseOn(a, t0, len) {
    const L = Math.max(2.2, len);
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
    apple: { n: 'Apple Music', c: '#FA2D48', h: 175, login: 'https://music.apple.com/', ex: 'music.apple.com/at/playlist/…' }
  };
  function embedOf(url) {
    url = String(url || '').trim(); let m;
    if ((m = url.match(/open\.spotify\.com\/(?:intl-[a-z-]+\/)?(?:embed\/)?(playlist|album|track|artist|show|episode)\/([A-Za-z0-9]{10,40})/))) return { p: 'spotify', src: `https://open.spotify.com/embed/${m[1]}/${m[2]}?utm_source=generator&theme=0` };
    if ((m = url.match(/^https?:\/\/(?:embed\.)?music\.apple\.com(\/[a-z]{2}\/(?:playlist|album|station|song)\/[^\s?#]+(?:\?i=\d+)?)/))) return { p: 'apple', src: 'https://embed.music.apple.com' + m[1] };
    return null;
  }
  function musicBox(root, T, lists, flow) {
    const box = root.querySelector('[data-mu]'), dock = root.querySelector('[data-dock]');
    let st = {}; try { st = JSON.parse(localStorage.getItem('mvf-music') || '{}') || {}; } catch (e) {}
    let prov = PROV[st.p] ? st.p : 'spotify';
    const provBtns = Object.keys(PROV).map(k => `<button type="button" data-pv="${k}"><i style="background:${PROV[k].c}"></i>${PROV[k].n}</button>`).join('');
    box.querySelector('[data-mub]').innerHTML = `
      <p class="mvf-sm">${T('Zu jedem Video läuft schon meine Musik. Lieber deine eigene? Dann verbinde Spotify oder Apple Music, meine Musik geht dann aus.', 'Every session already comes with my music. Prefer your own? Connect Spotify or Apple Music and mine will switch off.')}${flow ? ' ' + T('Stell sie so leise, dass du meine Stimme gut hörst.', 'Keep it low enough to hear my voice.') : ''}</p>
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
      } else lab.textContent = T('Spotify oder Apple Music', 'Spotify or Apple Music');
    }
    function draw() {
      box.querySelectorAll('[data-pv]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.pv === prov)));
      const P = PROV[prov];
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
        prov = e.p; st = { p: e.p, url: inp.value.trim() }; save(); mount(); draw(); root.dispatchEvent(new CustomEvent('mvf:own', { detail: true }));
      });
      if (ps) ps.addEventListener('click', () => { navigator.clipboard.readText().then(t => { inp.value = String(t || '').trim(); if (inp.value) go.click(); }).catch(() => inp.focus()); });
      inp.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); go.click(); } });
      pbody.querySelectorAll('[data-sg]').forEach(b => b.addEventListener('click', () => { st = { p: 'spotify', url: lists[+b.dataset.sg].url }; save(); mount(); draw(); root.dispatchEvent(new CustomEvent('mvf:own', { detail: true })); }));
    }
    box.querySelectorAll('[data-pv]').forEach(b => b.addEventListener('click', () => { prov = b.dataset.pv; err.hidden = true; draw(); }));
    off.addEventListener('click', () => { st = {}; save(); mount(); draw(); root.dispatchEvent(new CustomEvent('mvf:own', { detail: false })); });
    draw();
    // Fremde Musikdienste laden erst, wenn sie die Musik selbst aufklappt (Datenschutz)
    if (st.url) { lab.textContent = ((PROV[st.p] || {}).n || '') + ' · ' + T('zum Laden antippen', 'tap to load'); off.hidden = false; }
    else lab.textContent = T('Spotify oder Apple Music', 'Spotify or Apple Music');
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
  ${data.title ? `<p class="mvf-title" role="heading" aria-level="2">${esc(data.title)}</p>` : ''}
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
    <iframe data-if="0" title="" allow="autoplay;encrypted-media;picture-in-picture" loading="eager"></iframe>
    <iframe data-if="1" title="" allow="autoplay;encrypted-media;picture-in-picture" loading="eager" aria-hidden="true"></iframe>
    <div class="mvf-ph" data-ph hidden>${MARK}<span>${T('Clip folgt', 'Clip coming soon')}</span></div>
    <div class="mvf-sun"></div>
    <div class="mvf-shade"></div>
    ${pv && flow ? `<span class="mvf-flag" data-vflag hidden>${T('Vorschau · Voice-over fehlt noch', 'Preview · voice-over still missing')}</span>` : ''}
    <div class="mvf-name" data-nmw><svg viewBox="0 0 620 240" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="mvfwg" x1="0" x2="1"><stop offset="0" stop-color="#4A3040" stop-opacity=".94"/><stop offset=".62" stop-color="#4A3040" stop-opacity=".9"/><stop offset="1" stop-color="#4A3040" stop-opacity="0"/></linearGradient><linearGradient id="mvfwl" x1="0" x2="1"><stop offset="0" stop-color="#B58C99" stop-opacity=".75"/><stop offset=".7" stop-color="#B58C99" stop-opacity=".5"/><stop offset="1" stop-color="#B58C99" stop-opacity="0"/></linearGradient></defs><path d="M0 64 C110 30 210 98 310 84 S470 66 540 150 C572 188 600 222 620 240 H0Z" fill="url(#mvfwg)"/><path d="M0 46 C120 14 220 80 320 66 S480 48 560 138 C588 172 606 200 618 222" fill="none" stroke="url(#mvfwl)" stroke-width="2"/></svg><div class="in"><img src="/img/movecia/movecia-zeichen-hell.svg" alt="" draggable="false"><div><small data-tag></small><b data-nm></b></div></div></div>
    <div class="mvf-ring"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="t" cx="60" cy="60" r="52"></circle><circle class="p" data-pr cx="60" cy="60" r="52"></circle></svg><div class="n"><b data-sec>30</b><span data-unit>${T('Sek.', 'sec')}</span></div></div>
    <div class="mvf-cd" aria-live="polite"><span>${T('Gleich geht’s los', 'Get ready')}</span><b data-cd>10</b><span data-cdn hidden></span></div>
    <div class="mvf-tc" data-tc${data.photo ? ` style="background-image:url('${esc(data.photo)}')"` : ''}><img src="/img/movecia/movecia-zeichen-hell.svg" alt="" draggable="false"><small>${flow ? T('Dein Flow', 'Your flow') : T('Dein Bewegungsvideo', 'Your movement session')}</small>${data.title ? `<b>${esc(data.title)}</b>` : ''}</div>
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
<div class="mvf-done" data-done hidden>${WAVE}${MARK}<p class="mvf-eye" style="justify-content:center"><i></i>${T('Geschafft', 'Done')}<i></i></p><p class="mvf-dh" role="heading" aria-level="3">${T('Schön, dass du dir diese Zeit genommen hast.', 'I’m so glad you took this time for yourself.')}</p><p data-dt></p><button type="button" class="mvf-again" data-again>${T('Nochmal von vorn', 'Start again')}</button></div>
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
    let nmT = 0;
    function nameShow(keep) { const w = q('[data-nmw]'); if (!w) return; w.classList.remove('on'); void w.offsetWidth; w.classList.add('on'); clearTimeout(nmT); if (!keep) nmT = setTimeout(() => w.classList.remove('on'), 3500); }
    // ---- Clips: zwei Bunny-Player im Wechsel (Vivien, 30.10.2026: „das Video startet nicht von selbst, der Timer läuft trotzdem“) ----
    // Der nächste Clip lädt schon während der aktuellen Übung, beim Wechsel startet er sofort. Jeder Clip läuft stumm in Schleife,
    // egal wie kurz er ist. Der Timer zählt erst, wenn das Video wirklich läuft (spätestens nach 5 Sekunden).
    const slots = [0, 1].map(k => ({ f: q('[data-if="' + k + '"]'), src: '', pl: null, playing: false }));
    let act = 0, shownAt = 0;
    function pj() { if (window.playerjs) return Promise.resolve(window.playerjs); if (!pjP) pjP = new Promise(ok => { const sc = document.createElement('script'); sc.src = 'https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js'; sc.onload = () => ok(window.playerjs || null); sc.onerror = () => ok(null); document.head.appendChild(sc); setTimeout(() => ok(window.playerjs || null), 6000); }); return pjP; }
    function call(sl, fn, ...a) { try { if (sl.pl) sl.pl[fn](...a); } catch (e) {} }
    function load(sl, src) {
      if (sl.src === src) return; sl.src = src; sl.playing = false; sl.pl = null;
      if (!src) { sl.f.removeAttribute('src'); return; }
      sl.f.src = src;
      const mine = src; sl.f.onload = () => { if (sl.src === mine && !window.playerjs) setTimeout(() => { if (sl.src === mine) sl.playing = true; }, 1500); };
      pj().then(P => { if (!P || sl.src !== mine) { if (sl.src === mine) sl.playing = true; return; }
        let pl; try { pl = new P.Player(sl.f); } catch (e) { sl.playing = true; return; } sl.pl = pl;
        pl.on('ready', () => { if (sl.src !== mine) return; call(sl, 'mute'); call(sl, 'setLoop', true);
          if (slots[act] === sl && run) call(sl, 'play'); else call(sl, 'pause');
          pl.on('play', () => { if (sl.src === mine) sl.playing = true; }); pl.on('timeupdate', () => { if (sl.src === mine) sl.playing = true; });
          pl.on('ended', () => { call(sl, 'setCurrentTime', 0); call(sl, 'play'); }); }); });
    }
    function clipOn(i) { // Clip der Übung i zeigen und starten, den nächsten schon vorladen
      const it = items[i], src = it.src || '';
      let k = slots.findIndex(x => x.src === src && src);
      if (k < 0) { k = slots[act].src === '' || !run ? act : 1 - act; if (slots[k] === slots[act] && i > 0) k = 1 - act; load(slots[k], src); }
      act = k; shownAt = performance.now();
      slots.forEach((x, j) => { x.f.classList.toggle('on', j === act && !!src); if (j !== act) call(x, 'pause'); });
      const sl = slots[act]; sl.f.title = it.name; call(sl, 'setCurrentTime', 0); if (run) call(sl, 'play');
      q('[data-ph]').hidden = !!src;
      // Vorschaubild sofort zeigen, solange der Clip lädt (Vivien, 30.10.2026: „das Bild lädt sehr langsam“)
      q('[data-st]').style.backgroundImage = it.thumb ? 'url("' + it.thumb.replace(/"/g, '') + '")' : '';
      // Den nächsten Clip erst laden, wenn der aktuelle schon läuft – sonst teilen sich beide die Leitung
      clearTimeout(preT); const n = items[i + 1];
      if (n && n.src && n.src !== src) { const want = i; const tryPre = () => { if (idx !== want) return; if (slots[act].playing || performance.now() - shownAt > 6000) load(slots[1 - act], n.src); else preT = setTimeout(tryPre, 700); }; preT = setTimeout(tryPre, 1200); }
    }
    let preT = 0;
    items.forEach(x => { if (x.thumb) { const im = new Image(); im.src = x.thumb; } });
    const vidOk = () => !items[idx].src || slots[act].playing || performance.now() - shownAt > 5000;

    // ---- Musik darunter (vom Video, z. B. „Ankommen“): ein oder zwei Lieder, sanft ineinander geblendet ----
    const bgm = data.bgm || []; let bgOn = bgm.length > 0, bgK = 0, bgCur = 0, bgX = false, bgG = null, bgRun = false;
    try { if (localStorage.getItem('mvf-bgm') === '0') bgOn = false; } catch (e) {}
    const bgA = [new Audio(), new Audio()], bgE = [null, null], BGV = flow ? .16 : .5;
    bgA.forEach(a => { a.preload = 'auto'; });
    function bgApply() { bgA.forEach((el, k) => { try { el.volume = Math.max(0, Math.min(1, bgG.v * (bgE[k] ? bgE[k].v : 1))); } catch (e) {} }); }
    function bgSetup() { if (bgG) return; if (!IOS) { bgG = { fake: 1, v: 0 }; bgE[0] = { fake: 1, v: 0 }; bgE[1] = { fake: 1, v: 0 }; bgApply(); return; } const a = actx(); if (!a) return; try { bgG = a.createGain(); bgG.gain.value = 0; bgG.connect(a.destination); bgA.forEach((el, k) => { const g = a.createGain(); g.gain.value = 0; a.createMediaElementSource(el).connect(g); g.connect(bgG); bgE[k] = g; }); } catch (e) { bgG = null; } }
    function ramp(g, v, sec) {
      if (g && g.fake) { clearInterval(g.tm); const from = g.v, t0 = performance.now(), ms = Math.max(1, sec * 1000); g.tm = setInterval(() => { const x = Math.min(1, (performance.now() - t0) / ms); g.v = from + (v - from) * x; bgApply(); if (x >= 1) clearInterval(g.tm); }, 40); return; }
      if (!g || !ac) return; const t = ac.currentTime; try { g.gain.cancelScheduledValues(t); g.gain.setValueAtTime(g.gain.value, t); g.gain.linearRampToValueAtTime(v, t + sec); } catch (e) {} }
    function bgSong(k, slot) { const el = bgA[slot], m = bgm[k % bgm.length]; bgK = k; if (el.getAttribute('src') !== m.url) el.src = m.url; try { el.currentTime = 0; } catch (e) {} el.play().catch(() => { // Browser lässt das zweite Element nicht: im ersten weiterspielen
        if (slot !== bgCur) return; const o = 1 - slot; bgCur = o; const x = bgA[o]; x.src = m.url; try { x.currentTime = 0; } catch (e) {} x.play().catch(() => {}); if (bgE[o]) ramp(bgE[o], 1, .3); }); if (bgE[slot]) ramp(bgE[slot], 1, k ? 2.4 : .2); else el.volume = 1; }
    bgA.forEach((el, k) => el.addEventListener('timeupdate', () => {
      if (!bgRun || k !== bgCur || bgX || !isFinite(el.duration) || el.duration - el.currentTime > 2.6) return;
      bgX = true; const o = bgCur; bgCur = 1 - bgCur; if (bgE[o]) ramp(bgE[o], 0, 2.4); setTimeout(() => { if (o !== bgCur) bgA[o].pause(); bgX = false; }, 2700); bgSong(bgK + 1, bgCur); }));
    function bgPlay() { if (!bgm.length || !bgOn) return; bgSetup(); if (!bgRun) { bgRun = true; if (!bgA[bgCur].getAttribute('src')) bgSong(0, bgCur); else bgA[bgCur].play().catch(() => {}); } else bgA[bgCur].play().catch(() => {}); if (bgG) ramp(bgG, BGV, 1.5); else bgA.forEach(a => a.volume = BGV); }
    function bgPause(fade) { if (!bgm.length) return; if (bgG && fade) { ramp(bgG, 0, fade); setTimeout(() => bgA.forEach(a => a.pause()), fade * 1000 + 100); } else bgA.forEach(a => a.pause()); }
    function bgUi() { const b = q('[data-mus]'); if (!bgm.length || !b) return; b.setAttribute('aria-pressed', String(bgOn)); b.querySelector('span:last-child').textContent = bgOn ? T('Musik an', 'Music on') : T('Musik aus', 'Music off'); }

    function show(i) {
      const it = items[i];
      q('[data-tag]').textContent = T(`Übung ${i + 1} von ${items.length}`, `Exercise ${i + 1} of ${items.length}`);
      q('[data-nm]').textContent = it.name;
      if (phase === 'run') nameShow();
      q('[data-pos]').textContent = T(`Übung ${i + 1} von ${items.length}`, `Exercise ${i + 1} of ${items.length}`);
      clipOn(i);
      const n = items[i + 1];
      q('[data-af]').innerHTML = n ? T('Als Nächstes: ', 'Up next: ') + '<b>' + esc(n.name) + '</b>' : T('Das ist deine letzte Übung.', 'This is your last exercise.');
      total = secOf(i); left = total; chimed = false; lastBeep = 99; q('[data-st]').classList.remove('end'); if (bgG && bgRun && bgOn && run) ramp(bgG, BGV, 1.2);
      const vf = q('[data-vflag]'); if (vf) vf.hidden = !!it.voice;
      voice.pause(); voice.onloadedmetadata = null;
      if (flow && it.voice) {
        // Ist das Voice-over länger als die Übung, läuft der Timer so lange wie die Stimme (plus kurzer Ausklang)
        voice.onloadedmetadata = () => { if (isFinite(voice.duration) && voice.duration + 4 > total) { const add = voice.duration + 4 - total; total += add; left += add; draw(); } };
        voice.src = it.voice;
        if (run && phase === 'run') voice.play().catch(() => {});
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
    // Ablauf: title (Titelbild, 3 s) → ready (verschwommen, Welle mit Übung 1, 10 Sekunden in der Mitte) → run
    let phase = 'run', phT = 0, lastBeep = 99; const READY = 10, TITLE = (data.photo || data.title) ? 3 : 0;
    function setPhase(p) {
      phase = p; const st = q('[data-st]'), tc = q('[data-tc]');
      tc.classList.toggle('on', p === 'title'); st.classList.toggle('ready', p === 'ready');
      if (p === 'title') phT = TITLE;
      if (p === 'ready') { phT = READY; q('[data-cd]').textContent = String(READY); q('[data-cdn]').textContent = T('Erste Übung: ', 'First up: ') + items[0].name; nameShow(true); }
      if (p === 'run') { nameShow(); last = performance.now(); if (flow && voice.getAttribute('src')) voice.play().catch(() => {}); }
    }
    function tick(now) {
      if (!run) return;
      const dt = Math.min(1, (now - last) / 1000); last = now;
      if (phase === 'title') { phT -= dt; if (phT <= 0) setPhase('ready'); }
      else if (phase === 'ready') { if (vidOk()) phT -= dt; q('[data-cd]').textContent = String(Math.max(1, Math.ceil(phT))); if (phT <= 0) setPhase('run'); }
      else if (!sw && vidOk()) {
        left -= dt;
        if (left <= 5 && !chimed) { chimed = true; q('[data-st]').classList.add('end'); if (sound) sunrise(5); if (bgG && bgRun && bgOn) ramp(bgG, BGV * .3, .6); }
        const sec = Math.ceil(left); if (sound && sec <= 3 && sec >= 0 && sec !== lastBeep && left > -0.2) { lastBeep = sec; bell(sec === 0); }
        if (left <= 0) { left = 0; if (sound && lastBeep !== 0) { lastBeep = 0; bell(true); } draw(); advance(); } else draw();
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
      call(slots[act], 'play'); bgPlay();
      if (flow && phase === 'run' && voice.getAttribute('src') && !sw) voice.play().catch(() => {});
      cancelAnimationFrame(raf); raf = requestAnimationFrame(tick);
    }
    function stop() {
      run = false; voice.pause(); call(slots[act], 'pause'); bgPause(.4); q('[data-st]').classList.add('paused');
      q('[data-pi]').innerHTML = ICON.resume; q('[data-pl]').textContent = T('Weiter', 'Resume');
    }
    // Tab im Hintergrund: Timer anhalten, damit die Zeit nicht ohne sie verstreicht
    document.addEventListener('visibilitychange', () => { if (document.hidden && run && !pv) stop(); });
    document.addEventListener('mvdev:hold', () => { if (run) stop(); });
    function clearClips() { slots.forEach(x => { x.src = ''; x.pl = null; x.playing = false; x.f.classList.remove('on'); x.f.src = 'about:blank'; }); }
    function finish() {
      stop(); bgPause(2); bgRun = false; q('[data-st]').classList.remove('paused'); q('[data-p]').hidden = true; q('[data-done]').hidden = false; clearClips();
      q('[data-dt]').textContent = T(`Etwa ${mins(sum())} Minuten nur für dich.`, `About ${mins(sum())} minutes just for you.`);
      q('[data-done]').scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (!ended) { ended = true; root.dispatchEvent(new CustomEvent('mvflow:done', { bubbles: true })); }
    }
    q('[data-go]').addEventListener('click', () => {
      actx(); bgSetup(); q('[data-s]').hidden = true; q('[data-done]').hidden = true; q('[data-p]').hidden = false; idx = 0; run = true;
      bgK = 0; bgCur = 0; bgRun = false; bgA.forEach(a => { a.pause(); a.removeAttribute('src'); }); bgE.forEach(g => { if (g) { if (g.fake) { clearInterval(g.tm); g.v = 0; } else g.gain.value = 0; } }); if (bgG && bgG.fake) { clearInterval(bgG.tm); bgG.v = 0; bgApply(); }
      sndUnlock();
      phase = TITLE ? 'title' : 'ready'; show(0); setPhase(phase); play();
      if (bgm.length && bgOn) { const el = bgA[1]; el.src = bgm[1 % bgm.length].url; const pr = el.play(); if (pr && pr.then) pr.then(() => { if (bgCur !== 1) el.pause(); }).catch(() => {}); } // 2. Lied jetzt freischalten (Safari), läuft leise
      q('[data-p]').scrollIntoView({ behavior: 'smooth', block: 'start' });
      try { navigator.wakeLock && navigator.wakeLock.request('screen').catch(() => {}); } catch (e) {}
    });
    q('[data-again]').addEventListener('click', () => { q('[data-done]').hidden = true; q('[data-s]').hidden = false; });
    q('[data-pause]').addEventListener('click', () => run ? stop() : play());
    q('[data-snd]').addEventListener('click', e => { sound = !sound; e.currentTarget.setAttribute('aria-pressed', String(sound)); q('[data-sndl]').textContent = sound ? T('Klang an', 'Chime on') : T('Klang aus', 'Chime off'); });
    q('[data-mus]').addEventListener('click', () => {
      if (bgm.length) { bgOn = !bgOn; try { localStorage.setItem('mvf-bgm', bgOn ? '1' : '0'); } catch (e) {} if (bgOn && run) bgPlay(); else bgPause(.6); bgUi(); return; }
      const m = q('[data-mu]'); m.open = true; m.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
    root.addEventListener('mvf:own', e => { if (!bgm.length) return; bgOn = !e.detail; if (bgOn && run) bgPlay(); else bgPause(.6); bgUi(); });
    bgUi();
    if (pv) {
      q('[data-skip]').addEventListener('click', () => { if (sw) return; if (phase !== 'run') { setPhase('run'); return; } if (idx < items.length - 1) { idx++; show(idx); } else finish(); });
      q('[data-prev]').addEventListener('click', () => { if (sw) return; idx = Math.max(0, idx - 1); show(idx); });
    }
    return { stop: () => { stop(); clearClips(); } };
  }
  window.MvFlow = MvFlow;
  function auto() { document.querySelectorAll('[data-mv-flow]').forEach(el => { if (el.dataset.mvfOn) return; el.dataset.mvfOn = '1'; try { MvFlow(el, JSON.parse(el.dataset.mvFlow)); } catch (e) {} }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', auto); else auto();
})();

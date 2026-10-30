/* ✂️ Video-Schnitt (Vivien, 30.10.2026: „den Video-Editor so bauen, dass ich wirklich gut bearbeiten kann“)
 * Mehrere Clips (vom iPhone, vom Computer oder aus dem Studio) zu einem Video zusammenschneiden.
 * - Vorschau läuft über normale <video>-Elemente (flüssig, kein Bild-für-Bild-Rechnen); Licht & Farbe als CSS-Filter auf der Vorschau.
 * - Je Clip: Anfang/Ende setzen, an der aktuellen Stelle teilen, verschieben, löschen, Ton aus.
 * - Musik aus dem Musik-Ordner oder vom Gerät, leiser beim Sprechen, ein-/ausblenden.
 * - Fertig stellen: WebCodecs über Mediabunny (Bild für Bild dekodieren, derselbe Filter als WebGL, kodieren), Ton offline gemischt.
 *   Geht das nicht, Rückfall in Echtzeit (Canvas + MediaRecorder).
 * - ✨ Automatisch schneiden (30.10.2026): Pausen rausschneiden, Neustarts (Versprecher) erkennen, je Abschnitt ein sanfter
 *   Zoom hinein/heraus im Wechsel (kaschiert die Schnitte). Analyse in schnitt-auto.js (bei Bedarf geladen), Rückgängig möglich.
 * - Alle Clips liegen in IndexedDB auf dem Gerät („mv-schnitt“), bis Vivien sie löscht. Das Studio legt Takes dort ab.
 *   Clips aus derselben Datei teilen sich ein <video>-Element (viele Abschnitte = kein Speicherproblem).
 * Schnitt.open({mount, musicUrl, onDone(blob), toStudio(blob)}) · Schnitt.addTake(blob, name) (aus dem Studio)
 */
(function () {
  'use strict';
  const DB = 'mv-schnitt', VER = 1;
  function idb() { return new Promise((ok, no) => { const r = indexedDB.open(DB, VER); r.onupgradeneeded = () => { const d = r.result; if (!d.objectStoreNames.contains('clips')) d.createObjectStore('clips', { keyPath: 'id' }); if (!d.objectStoreNames.contains('kv')) d.createObjectStore('kv'); }; r.onsuccess = () => ok(r.result); r.onerror = () => no(r.error); }); }
  async function dbAll() { const d = await idb(); return new Promise((ok, no) => { const r = d.transaction('clips').objectStore('clips').getAll(); r.onsuccess = () => ok(r.result || []); r.onerror = () => no(r.error); }); }
  async function dbPut(c) { const d = await idb(); return new Promise((ok, no) => { const t = d.transaction('clips', 'readwrite'); t.objectStore('clips').put(c); t.oncomplete = ok; t.onerror = () => no(t.error); }); }
  async function dbDel(id) { const d = await idb(); return new Promise((ok, no) => { const t = d.transaction('clips', 'readwrite'); t.objectStore('clips').delete(id); t.oncomplete = ok; t.onerror = () => no(t.error); }); }
  async function kvGet(k) { const d = await idb(); return new Promise((ok, no) => { const r = d.transaction('kv').objectStore('kv').get(k); r.onsuccess = () => ok(r.result); r.onerror = () => no(r.error); }); }
  async function kvSet(k, v) { const d = await idb(); return new Promise((ok, no) => { const t = d.transaction('kv', 'readwrite'); t.objectStore('kv').put(v, k); t.oncomplete = ok; t.onerror = () => no(t.error); }); }
  async function kvDel(k) { const d = await idb(); return new Promise((ok, no) => { const t = d.transaction('kv', 'readwrite'); t.objectStore('kv').delete(k); t.oncomplete = ok; t.onerror = () => no(t.error); }); }
  const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  const fmt = s => { s = Math.max(0, s || 0); return Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0'); };
  const fmt1 = s => fmt(s) + ',' + Math.floor((Math.max(0, s || 0) % 1) * 10);
  const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const ls = { get: (k, d) => { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } }, set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };

  // Ein Take aus dem Studio in den Schnitt legen (bleibt auf dem Gerät)
  async function addTake(blob, name) {
    const c = { id: uid(), blob, name: name || 'Aufnahme', added: Date.now(), in: 0, out: 0, mute: false };
    await dbPut(c); const order = (await kvGet('order')) || []; order.push(c.id); await kvSet('order', order); return c.id;
  }

  const CSS = `
.sn{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;color:#2E2529;display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,400px);gap:22px;align-items:start}
@media(max-width:980px){.sn{display:flex;flex-direction:column;gap:14px}}
.sn *{box-sizing:border-box}
.sn h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;color:#4A3A41;font-size:26px;margin:0 0 8px}
.sn-l{min-width:0}
.sn-pv{position:relative;background:#1E171B;border-radius:22px;overflow:hidden;display:flex;align-items:center;justify-content:center;aspect-ratio:16/9;max-height:62vh}
@media(max-width:980px){.sn-pv{position:sticky;top:60px;z-index:7;border-radius:16px;max-height:38vh;box-shadow:0 10px 18px -14px rgba(46,37,41,.6)}}
.sn-pv.p916{aspect-ratio:9/16;max-width:min(100%,62vh * 9 / 16);margin:0 auto}
.sn-pv.p45{aspect-ratio:4/5;max-width:min(100%,62vh * 4 / 5);margin:0 auto}
.sn-pv.p11{aspect-ratio:1/1;max-width:min(100%,62vh);margin:0 auto}
@media(max-width:980px){.sn-pv.p916{max-width:min(100%,38vh * 9 / 16)}.sn-pv.p45{max-width:min(100%,38vh * 4 / 5)}.sn-pv.p11{max-width:min(100%,38vh)}}
.sn-pv video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;background:#1E171B;transform-origin:50% 50%;will-change:transform}
.sn-pv video.on{display:block}
.sn-pv .big{position:absolute;inset:0;margin:auto;width:76px;height:76px;border-radius:50%;border:0;background:rgba(251,245,247,.9);color:#4A3A41;font-size:30px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2}
.sn-pv .big[hidden]{display:none}
.sn-pv .empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:#EADFDE;text-align:center;padding:20px;font-size:17px}
.sn-pv .empty b{font-family:"Cormorant Garamond",Georgia,serif;font-size:30px;font-weight:500;color:#fff}
.sn-pv .tag{position:absolute;left:12px;top:12px;padding:4px 10px;border-radius:999px;background:rgba(46,37,41,.7);color:#fff;font-size:14px;z-index:2}
.sn-tl{margin-top:10px}
.sn-scrub{position:relative;height:46px}
.sn-scrub .bar{position:absolute;top:17px;height:12px;border-radius:9px;background:#EADFDE;left:0;right:0;overflow:hidden;display:flex}
.sn-scrub .bar i{display:block;height:100%;border-right:2px solid #F2ECE4}
.sn-scrub .bar i.sel{background:#B9A2AC}
.sn-scrub input{position:absolute;inset:0;width:100%;height:46px;margin:0;padding:0;background:transparent;-webkit-appearance:none;appearance:none;cursor:pointer}
.sn-scrub input::-webkit-slider-runnable-track{background:transparent;height:46px}
.sn-scrub input::-moz-range-track{background:transparent}
.sn-scrub input::-webkit-slider-thumb{-webkit-appearance:none;width:26px;height:26px;margin-top:10px;border-radius:50%;background:#4A3A41;border:3px solid #fff;box-shadow:0 1px 4px rgba(46,37,41,.4)}
.sn-scrub input::-moz-range-thumb{width:22px;height:22px;border-radius:50%;background:#4A3A41;border:3px solid #fff}
.sn-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px}
.sn-row .t{font-size:17px;color:#4A3A41;font-variant-numeric:tabular-nums;margin-right:auto}
.sn-b{font:inherit;font-size:17px;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:46px;padding:9px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;text-decoration:none}
.sn-b.pl{background:#7D5E68;border-color:#7D5E68;color:#fff}
.sn-b.main{background:#3F6B3E;border-color:#3F6B3E;color:#fff;font-size:19px;min-height:56px;padding:12px 26px}
.sn-b.dk{background:#4A3A41;border-color:#4A3A41;color:#fff}
.sn-b.sm{min-height:40px;font-size:15px;padding:6px 12px}
.sn-b.del{color:#8C2C1D;border-color:#E2C4BC}
.sn-b:disabled{opacity:.5;cursor:default}
.sn-b[aria-pressed=true]{background:#4A3A41;border-color:#4A3A41;color:#fff}
/* Clips */
.sn-clips{display:flex;gap:10px;overflow-x:auto;padding:6px 2px 10px;scrollbar-width:thin;scroll-snap-type:x proximity}
.sn-c{flex:0 0 auto;width:132px;border-radius:14px;overflow:hidden;background:#fff;border:2px solid #E6DCD2;cursor:pointer;position:relative;scroll-snap-align:start;text-align:left;padding:0;font:inherit}
.sn-c.on{border-color:#7D5E68;box-shadow:0 0 0 2px #7D5E68}
.sn-c img,.sn-c .ph{display:block;width:100%;height:84px;object-fit:cover;background:#D9CBBF}
.sn-c .ph{display:flex;align-items:center;justify-content:center;color:#7D5E68;font-size:26px}
.sn-c b{display:block;font-weight:500;font-size:13px;color:#4A3A41;padding:6px 8px 1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sn-c small{display:block;color:#7D7076;font-size:12px;padding:0 8px 7px;font-variant-numeric:tabular-nums}
.sn-c .n{position:absolute;left:6px;top:6px;background:rgba(46,37,41,.75);color:#fff;border-radius:999px;font-size:12px;padding:1px 7px}
.sn-c .mu{position:absolute;right:6px;top:6px;background:rgba(46,37,41,.75);color:#fff;border-radius:999px;font-size:12px;padding:1px 7px}
.sn-add{flex:0 0 auto;width:132px;height:132px;border-radius:14px;border:2px dashed #C9B0B8;background:#FBF8F4;color:#7D5E68;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;font:inherit;font-size:15px;text-align:center}
.sn-add b{font-size:28px;font-weight:400;line-height:1}
.sn-ed{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:18px;padding:12px 14px;margin-top:4px}
.sn-ed .h{display:flex;flex-wrap:wrap;gap:8px;align-items:baseline;justify-content:space-between}
.sn-ed .h b{font-weight:500;font-size:17px;color:#4A3A41}.sn-ed .h span{color:#7D7076;font-size:15px}
.sn-ed .cut{position:relative;height:40px;margin-top:8px}
.sn-ed .cut .bg{position:absolute;top:14px;left:0;right:0;height:12px;border-radius:9px;background:#EADFDE}
.sn-ed .cut .in{position:absolute;top:14px;height:12px;border-radius:9px;background:#7D5E68}
.sn-ed .cut .ph{position:absolute;top:8px;width:3px;height:24px;background:#2E2529;border-radius:2px;transform:translateX(-1px)}
.sn-r{display:flex;flex-direction:column;gap:14px;min-width:0}
.sn-box{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:22px;padding:14px 16px}
.sn-box summary{cursor:pointer;font-weight:500;font-size:19px;color:#4A3A41;list-style:none;display:flex;justify-content:space-between;align-items:center}
.sn-box summary::-webkit-details-marker{display:none}
.sn-box summary::after{content:"＋";color:#7D5E68;font-weight:300}
.sn-box[open] summary::after{content:"−"}
.sn-box .bd{display:flex;flex-direction:column;gap:12px;margin-top:12px}
.sn-chips{display:flex;flex-wrap:wrap;gap:6px}
.sn-chips button{font:inherit;font-size:15px;padding:7px 13px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer}
.sn-chips button[aria-pressed=true]{background:#4A3A41;border-color:#4A3A41;color:#fff}
.sn-sl label{display:flex;justify-content:space-between;font-size:16px;color:#4A3A41;margin-bottom:2px}.sn-sl small{color:#7D7076}
.sn-sl input{width:100%;accent-color:#7D5E68}
.sn-note{font-size:15px;line-height:1.5;color:#7D7076;margin:0}
.sn-msg{font-size:17px;color:#6B5658;margin:8px 2px 0;min-height:1.4em}.sn-msg.ok{color:#3F6B3E;font-weight:500}.sn-msg.bad{color:#8C2C1D}
.sn-prog{height:10px;border-radius:9px;background:#EADFDE;overflow:hidden;margin-top:8px}.sn-prog i{display:block;height:100%;width:0;background:#3F6B3E;transition:width .3s}
.sn-prog[hidden]{display:none}
.sn-lib ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;max-height:260px;overflow:auto}
.sn-lib li{display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid #E6DCD2;border-radius:16px;padding:6px 8px}
.sn-lib li.on{border-color:#7D5E68;box-shadow:0 0 0 2px #7D5E68 inset}
.sn-lib li span{flex:1;min-width:0;font-size:16px;line-height:1.3}.sn-lib li small{display:block;color:#7D7076;font-size:13px}
.sn-lib li button{font:inherit;flex:0 0 auto;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer;min-height:40px;padding:6px 12px;font-size:15px}
.sn-lib li button.pk{background:#7D5E68;border-color:#7D5E68;color:#fff}
.sn-sw{display:flex;align-items:center;gap:10px;font-size:16px;color:#4A3A41}.sn-sw input{width:22px;height:22px;accent-color:#7D5E68}
.sn-res{background:#fff;border:1.5px solid #CFE0CE;border-radius:22px;padding:14px 16px;margin-top:12px}
.sn-res video{width:100%;max-height:50vh;border-radius:14px;background:#000;display:block}
.sn-busy .sn-r,.sn-busy .sn-clips,.sn-busy .sn-ed,.sn-busy .sn-tl{opacity:.4;pointer-events:none}
.sn-box.auto{background:linear-gradient(135deg,#FBF8F4,#F3E7EA);border-color:#E2CBD2}
.sn-auto-res{background:#fff;border:1.5px solid #E6DCD2;border-radius:16px;padding:10px 12px;font-size:16px;color:#4A3A41;line-height:1.5}
.sn-auto-res ul{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-direction:column;gap:6px}
.sn-auto-res li{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#FBF8F4;border-radius:12px;padding:6px 8px}
.sn-auto-res li span{flex:1;min-width:120px;font-size:15px}
.sn-auto-res li small{color:#7D7076}
.sn-c.auto b::before{content:"✨ ";font-size:11px}
`;
  function css() { if (document.getElementById('sn-css')) return; const s = document.createElement('style'); s.id = 'sn-css'; s.textContent = CSS; document.head.appendChild(s); }

  // CSS-Filter genau wie im Browser (W3C Filter Effects), damit Vorschau und Export gleich aussehen
  const VS = 'attribute vec2 p;varying vec2 uv;void main(){uv=p*.5+.5;gl_Position=vec4(p,0.,1.);}';
  const FS = `precision highp float;varying vec2 uv;uniform sampler2D t;uniform vec2 so,oo;uniform float br,ct,sa,se,zm;
void main(){
 float ra=so.x/so.y, ro=oo.x/oo.y; vec2 q=uv; // Mitte ausschneiden (object-fit: cover)
 if(ra>ro){float k=ro/ra;q.x=.5+(uv.x-.5)*k;}else{float k=ra/ro;q.y=.5+(uv.y-.5)*k;}
 q=.5+(q-.5)/zm; // sanfter Zoom je Abschnitt
 vec3 c=texture2D(t,clamp(q,vec2(.0005),vec2(.9995))).rgb;
 c*=br; c=(c-.5)*ct+.5; c=clamp(c,0.,1.);
 float r=c.r,g=c.g,b=c.b;
 c=vec3((.213+.787*sa)*r+(.715-.715*sa)*g+(.072-.072*sa)*b,(.213-.213*sa)*r+(.715+.285*sa)*g+(.072-.072*sa)*b,(.213-.213*sa)*r+(.715-.715*sa)*g+(.072+.928*sa)*b);
 r=c.r;g=c.g;b=c.b;
 c=vec3((.393+.607*(1.-se))*r+(.769-.769*(1.-se))*g+(.189-.189*(1.-se))*b,(.349-.349*(1.-se))*r+(.686+.314*(1.-se))*g+(.168-.168*(1.-se))*b,(.272-.272*(1.-se))*r+(.534-.534*(1.-se))*g+(.131+.869*(1.-se))*b);
 gl_FragColor=vec4(clamp(c,0.,1.),1.);}`;
  const LOOKS = [['natur', 'Natürlich', { br: 1, ct: 1, sa: 1, se: 0 }], ['hell', 'Heller & frisch', { br: 1.08, ct: 1.04, sa: 1.06, se: 0 }], ['warm', 'Warm', { br: 1.03, ct: 1.02, sa: 1.02, se: .22 }], ['weich', 'Weich & ruhig', { br: 1.04, ct: .92, sa: .9, se: .12 }], ['sw', 'Schwarz-Weiß', { br: 1.02, ct: 1.06, sa: 0, se: 0 }]];
  const FMTS = [['orig', 'Wie aufgenommen'], ['9:16', 'Hoch 9:16'], ['4:5', '4:5'], ['1:1', 'Quadrat'], ['16:9', 'Quer 16:9']];
  const filterCss = L => `brightness(${L.br}) contrast(${L.ct}) saturate(${L.sa}) sepia(${L.se})`;
  const brate = (w, h) => { const p = w * h; return p >= 7e6 ? 40e6 : p >= 3.2e6 ? 20e6 : p >= 1.8e6 ? 12e6 : p >= .8e6 ? 8e6 : 5e6; };
  function mime() { const ts = ['video/mp4;codecs=avc1.640028,mp4a.40.2', 'video/mp4;codecs=avc1,mp4a', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']; return ts.find(t => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || ''; }

  function open(o) {
    css();
    const mount = o.mount; mount.innerHTML = '';
    const root = document.createElement('div'); root.className = 'sn'; mount.appendChild(root);
    root.innerHTML = `<div class="sn-l">
  <div class="sn-pv" id="snpv"><div class="empty" id="snempty"><b>Noch keine Clips</b>Tipp unten auf „＋ Clips“ und wähl Videos vom iPhone oder Computer, oder nimm im Studio auf.</div><button type="button" class="big" data-k="big" hidden aria-label="Abspielen">▶</button><span class="tag" data-k="tag"></span></div>
  <div class="sn-tl">
    <div class="sn-scrub"><div class="bar" data-k="bar"></div><input type="range" min="0" max="1000" step="1" value="0" data-k="scrub" aria-label="Stelle im Video"></div>
    <div class="sn-row"><span class="t" data-k="time">0:00 / 0:00</span>
      <button type="button" class="sn-b pl" data-k="play">▶ Abspielen</button>
      <button type="button" class="sn-b" data-k="cmp" title="Licht & Farbe kurz ausschalten">Vorher zeigen</button></div>
  </div>
  <h2 style="margin-top:16px">Deine Clips <span style="font-size:16px;color:#7D7076;font-family:'Hanken Grotesk',sans-serif;font-weight:400" data-k="cnt"></span></h2>
  <div class="sn-clips" data-k="clips"></div>
  <div class="sn-row" style="margin-top:0">
    <label class="sn-b pl" style="cursor:pointer">＋ Clips vom Gerät<input type="file" accept="video/*,.mov,.mp4,.m4v,.webm" multiple hidden data-k="file"></label>
    ${o.studioUrl ? `<a class="sn-b" href="${esc(o.studioUrl)}">● Im Studio aufnehmen</a>` : ''}
    <button type="button" class="sn-b sm del" data-k="clearAll">Alle Clips löschen</button>
  </div>
  <div class="sn-ed" data-k="ed" hidden>
    <div class="h"><b data-k="edT"></b><span data-k="edD"></span></div>
    <div class="cut"><div class="bg"></div><div class="in" data-k="edIn"></div><div class="ph" data-k="edPh"></div></div>
    <div class="sn-row">
      <button type="button" class="sn-b sm" data-k="setA">⟦ Anfang hier</button>
      <button type="button" class="sn-b sm" data-k="setB">Ende hier ⟧</button>
      <button type="button" class="sn-b sm" data-k="split">✂ Hier teilen</button>
      <button type="button" class="sn-b sm" data-k="resetCut">Schnitt zurück</button>
    </div>
    <div class="sn-row">
      <button type="button" class="sn-b sm" data-k="left">← Nach vorne</button>
      <button type="button" class="sn-b sm" data-k="right">Nach hinten →</button>
      <button type="button" class="sn-b sm" data-k="mute">🔇 Ton aus</button>
      <button type="button" class="sn-b sm del" data-k="delClip">🗑 Clip löschen</button>
    </div>
  </div>
  <div class="sn-row" style="margin-top:14px">
    <button type="button" class="sn-b main" data-k="done">✓ Fertig stellen</button>
  </div>
  <div class="sn-prog" data-k="prog" hidden><i></i></div>
  <p class="sn-msg" data-k="msg"></p>
  <div class="sn-res" data-k="res" hidden>
    <h2>Dein fertiges Video</h2>
    <video controls playsinline data-k="resv"></video>
    <div class="sn-row" style="margin-top:12px">
      <button type="button" class="sn-b pl" data-k="share" hidden>📲 In Fotos sichern</button>
      <a class="sn-b" data-k="dl" download>⬇ Als Datei herunterladen</a>
      ${o.toStudio ? '<button type="button" class="sn-b dk" data-k="toStudio">⬆ Im Studio hochladen</button>' : ''}
      <button type="button" class="sn-b sm" data-k="resX">Schließen</button>
    </div>
    <p class="sn-note" style="margin-top:8px" data-k="resN"></p>
  </div>
</div>
<div class="sn-r">
  <details class="sn-box auto" open><summary>✨ Automatisch schneiden</summary><div class="bd">
    <p class="sn-note" style="color:#4A3A41">Ein Tipp, und ich schneide Pausen raus, erkenne Stellen, an denen du neu angesetzt hast, und lege einen sanften Zoom über die Abschnitte, damit es sich flüssig und lebendig anschaut.</p>
    <button type="button" class="sn-b main" data-k="auto" style="font-size:18px;min-height:52px">✨ Jetzt automatisch schneiden</button>
    <div><div style="font-size:16px;color:#4A3A41;margin-bottom:6px">Pausen rausschneiden ab</div><div class="sn-chips" data-k="pauses"></div></div>
    <label class="sn-sw"><input type="checkbox" data-k="stumbles"> Versprecher erkennen (wenn du neu ansetzt)</label>
    <label class="sn-sw"><input type="checkbox" data-k="zoomfx"> Sanfter Zoom hinein und heraus</label>
    <div class="sn-auto-res" data-k="autoRes" hidden></div>
    <button type="button" class="sn-b sm" data-k="undo" hidden>↩ Automatischen Schnitt rückgängig</button>
  </div></details>
  <details class="sn-box" open><summary>☀️ Licht &amp; Farbe</summary><div class="bd">
    <div class="sn-chips" data-k="looks"></div>
    <div data-sl="br"></div><div data-sl="ct"></div><div data-sl="sa"></div><div data-sl="se"></div>
    <p class="sn-note">Gilt für alle Clips, damit das Video aus einem Guss ist.</p>
  </div></details>
  <details class="sn-box"><summary>🖼 Format</summary><div class="bd">
    <div class="sn-chips" data-k="fmts"></div>
    <p class="sn-note">Bei einem anderen Format wird die Mitte ausgeschnitten. Für Reels und Storys „Hoch 9:16“.</p>
  </div></details>
  <details class="sn-box"><summary>🎵 Musik darunter</summary><div class="bd">
    <div><b style="font-weight:500;font-size:17px;color:#4A3A41" data-k="mname">Noch keine Musik</b> <button type="button" class="sn-b sm" data-k="mdel" hidden>Musik entfernen</button></div>
    <div class="sn-lib" data-k="lib"><p class="sn-note">Deine Musik wird geladen …</p></div>
    <label class="sn-b" style="width:max-content;cursor:pointer">📁 Andere Datei vom Gerät<input type="file" accept="audio/*,.mp3,.m4a,.wav,.aac" hidden data-k="mfile"></label>
    <div data-sl="vol"></div><div data-sl="voice"></div>
    <label class="sn-sw"><input type="checkbox" data-k="duck"> Musik leiser, wenn ich spreche</label>
    <label class="sn-sw"><input type="checkbox" data-k="fade"> Musik sanft ein- und ausblenden</label>
  </div></details>
  <p class="sn-note">Deine Clips bleiben auf diesem Gerät gespeichert (auch nach dem Schließen), bis du sie hier löschst. Die Vorschau zeigt alles so, wie es nachher gespeichert wird.</p>
</div>`;
    const q = s => root.querySelector(s), K = k => root.querySelector('[data-k="' + k + '"]');
    const pv = q('#snpv'), msg = K('msg'), big = K('big'), scrub = K('scrub');
    const say = (t, c) => { msg.className = 'sn-msg' + (c ? ' ' + c : ''); msg.textContent = t; };

    // ---- Zustand ----
    let clips = [], sel = -1, closed = false, busy = false;
    const st = Object.assign({ look: 'natur', br: 1, ct: 1, sa: 1, se: 0, fmt: 'orig' }, ls.get('sn-st', {}));
    const mus = Object.assign({ vol: .18, voice: 1, duck: true, fade: true }, ls.get('sn-mus', {}));
    const auto = Object.assign({ pause: .7, stumbles: true, zoom: true }, ls.get('sn-auto', {}));
    let mfile = null;
    const vids = new Map(); // Quelldatei (url) → <video>, von allen Clips/Abschnitten aus dieser Datei geteilt
    function vidFor(c) {
      let v = vids.get(c.url);
      if (v) { if (!c.dur && v.readyState >= 1 && isFinite(v.duration) && v.duration > 0) { c.dur = v.duration; if (!c.out) c.out = c.dur; } return v; }
      v = document.createElement('video'); v.playsInline = true; v.setAttribute('playsinline', ''); v.preload = 'auto'; v.muted = false;
      v.src = c.url; pv.insertBefore(v, big); vids.set(c.url, v);
      v.addEventListener('loadedmetadata', () => { const d = isFinite(v.duration) && v.duration > 0 ? v.duration : 0; let probe = false; clips.filter(x => x.url === c.url && !x.dur).forEach(x => { x.dur = d; if (!x.out) x.out = d; if (!d) probe = true; }); if (!c.dur) { c.dur = d; if (!c.out) c.out = d; if (!d) probe = true; } if (probe) probeDur(c, v); else ui(); });
      return v;
    }
    const vidUsers = url => clips.filter(x => x.url === url).length;
    const dropVid = url => { if (vidUsers(url)) return; const v = vids.get(url); if (v) { v.remove(); vids.delete(url); } };
    // Zoom je Clip: [von, bis] als Faktor, wird über die Clip-Länge gleichmäßig gefahren
    const zAt = (c, off) => { if (!c || !c.zoom) return 1; const L = len(c) || 1, p = Math.max(0, Math.min(1, off / L)); return c.zoom[0] + (c.zoom[1] - c.zoom[0]) * p; };
    // MediaRecorder-Dateien kennen ihre Länge oft nicht: einmal ans Ende springen
    async function probeDur(c, v) { try { v.currentTime = 1e7; await new Promise(r => { v.onseeked = r; setTimeout(r, 2500); }); c.dur = isFinite(v.duration) && v.duration > 0 ? v.duration : (v.currentTime || 1); v.currentTime = 0; } catch (e) { c.dur = c.dur || 1; } if (!c.out || c.out > c.dur) c.out = c.dur; dbPut(dbRow(c)); ui(); }
    const dbRow = c => ({ id: c.id, blob: c.blob, name: c.name, added: c.added, in: c.in, out: c.out, mute: !!c.mute, dur: c.dur || 0, zoom: c.zoom || null, auto: !!c.auto });
    const len = c => Math.max(0, (c.out || c.dur || 0) - (c.in || 0));
    const total = () => clips.reduce((a, c) => a + len(c), 0);
    const tv = document.createElement('video'); tv.muted = true; tv.playsInline = true; tv.preload = 'auto'; let thumbQ = Promise.resolve();
    function thumb(c) {
      if (c.thumb) return; thumbQ = thumbQ.then(async () => {
        if (closed || c.thumb || !clips.includes(c)) return; try {
          if (tv.src !== c.url) { tv.src = c.url; await new Promise((r, no) => { tv.addEventListener('loadedmetadata', r, { once: true }); tv.addEventListener('error', no, { once: true }); setTimeout(no, 6000); }); }
          const t = Math.min((c.in || 0) + .3, Math.max(0, (c.dur || tv.duration || 1) - .1));
          if (Math.abs(tv.currentTime - t) > .1) { tv.currentTime = t; await new Promise(r => { tv.onseeked = r; setTimeout(r, 1500); }); }
          const cv = document.createElement('canvas'); const w = tv.videoWidth || 320, h = tv.videoHeight || 180, k = 200 / Math.max(w, h); cv.width = Math.round(w * k); cv.height = Math.round(h * k);
          cv.getContext('2d').drawImage(tv, 0, 0, cv.width, cv.height); c.thumb = cv.toDataURL('image/jpeg', .7); renderClips();
        } catch (e) {}
      });
    }

    // ---- Laden ----
    async function load() {
      try {
        const rows = await dbAll(), order = (await kvGet('order')) || [];
        rows.sort((a, b) => (order.indexOf(a.id) + 1 || 1e9) - (order.indexOf(b.id) + 1 || 1e9) || a.added - b.added);
        const urls = new Map(); clips = rows.map(r => { let u = urls.get(r.blob); if (!u) { u = URL.createObjectURL(r.blob); urls.set(r.blob, u); } return Object.assign({}, r, { url: u }); });
        clips.forEach(c => vidFor(c));
        if (clips.length) { sel = 0; }
        ui(); clips.forEach(thumb);
      } catch (e) { say('Die gespeicherten Clips konnten nicht geladen werden.', 'bad'); }
    }
    async function addFiles(files) {
      let n = 0;
      for (const f of files) { if (!f || !f.size) continue; const c = { id: uid(), blob: f, name: f.name.replace(/\.\w+$/, ''), added: Date.now() + n, in: 0, out: 0, mute: false, dur: 0 }; c.url = URL.createObjectURL(f); clips.push(c); await dbPut(dbRow(c)); vidFor(c); thumb(c); n++; }
      await saveOrder(); if (sel < 0 && clips.length) sel = 0; if (n) say('✓ ' + n + (n > 1 ? ' Clips' : ' Clip') + ' dazu. Tipp auf einen Clip, um Anfang und Ende zu setzen.', 'ok'); ui();
    }
    const saveOrder = () => kvSet('order', clips.map(c => c.id)).catch(() => {});
    K('file').onchange = e => { addFiles([...e.target.files]); e.target.value = ''; };
    K('clearAll').onclick = async () => { if (!clips.length || !confirm('Wirklich alle ' + clips.length + ' Clips von diesem Gerät löschen?')) return; stop(); for (const c of clips) { await dbDel(c.id); } clips.forEach(c => { const v = vids.get(c.url); if (v) { v.remove(); vids.delete(c.url); } URL.revokeObjectURL(c.url); }); clips = []; sel = -1; await saveOrder(); undoRows = null; K('undo').hidden = true; K('autoRes').hidden = true; ui(); say('Alle Clips gelöscht.'); };

    // ---- Vorschau: Clips nacheinander abspielen ----
    let playing = false, pos = 0, raf = 0, cmp = false; // pos = Stelle im Gesamtvideo
    const cur = () => (sel >= 0 && clips[sel]) ? vids.get(clips[sel].url) : null;
    function locate(t) { let a = 0; for (let i = 0; i < clips.length; i++) { const L = len(clips[i]); if (t < a + L || i === clips.length - 1) return { i, off: Math.min(L, Math.max(0, t - a)), a }; a += L; } return { i: -1, off: 0, a: 0 }; }
    function show(i, off, seek) {
      if (i < 0 || !clips[i]) return; const c = clips[i], v = vids.get(c.url);
      vids.forEach(vv => { if (vv !== v) { vv.classList.remove('on'); if (!vv.paused) vv.pause(); } });
      v.classList.add('on'); v.muted = !!c.mute; v.style.filter = cmp ? '' : filterCss(st);
      if (seek !== false) { const t = (c.in || 0) + off; if (Math.abs(v.currentTime - t) > .08) v.currentTime = t; }
      v.style.transform = c.zoom ? 'scale(' + zAt(c, seek !== false ? off : Math.max(0, v.currentTime - (c.in || 0))).toFixed(4) + ')' : '';
    }
    function tick() {
      if (!playing) return;
      const c = clips[sel]; const v = cur(); if (!c || !v) { stop(); return; }
      const off = Math.max(0, v.currentTime - (c.in || 0)); pos = starts()[sel] + off; timeUi();
      if (c.zoom) v.style.transform = 'scale(' + zAt(c, off).toFixed(4) + ')';
      if (v.currentTime >= (c.out || c.dur) - .04 || v.ended) {
        if (sel < clips.length - 1) { sel++; const n = clips[sel], nv = vids.get(n.url); show(sel, 0); nv.play().catch(() => {}); renderClips(false); edUi(); }
        else { stop(); pos = 0; sel = 0; show(0, 0); renderClips(false); edUi(); timeUi(); return; }
      }
      raf = requestAnimationFrame(tick);
    }
    const starts = () => { let a = 0; return clips.map(c => { const s = a; a += len(c); return s; }); };
    async function play() {
      if (busy || !clips.length) return; if (sel < 0) sel = 0; const c = clips[sel], v = vids.get(c.url);
      if (v.currentTime < (c.in || 0) || v.currentTime >= (c.out || c.dur) - .05) show(sel, 0); else show(sel, 0, false);
      playing = true; big.hidden = true; K('play').textContent = '❚❚ Pause';
      try { await v.play(); } catch (e) { playing = false; big.hidden = false; K('play').textContent = '▶ Abspielen'; say('Abspielen klappt nicht. Bitte nochmal auf ▶ tippen.'); return; }
      if (mfile) { musicPlay(); }
      cancelAnimationFrame(raf); raf = requestAnimationFrame(tick);
    }
    function stop() { playing = false; cancelAnimationFrame(raf); const v = cur(); if (v) v.pause(); if (au) au.pause(); big.hidden = !clips.length; K('play').textContent = '▶ Abspielen'; }
    K('play').onclick = () => playing ? stop() : play(); big.onclick = play;
    K('cmp').onclick = () => { cmp = !cmp; K('cmp').textContent = cmp ? 'Nachher zeigen' : 'Vorher zeigen'; K('cmp').setAttribute('aria-pressed', String(cmp)); K('tag').textContent = cmp ? 'Vorher' : ''; const v = cur(); if (v) v.style.filter = cmp ? '' : filterCss(st); };
    let scrubbing = false;
    scrub.oninput = () => { scrubbing = true; if (playing) stop(); const T = total() || 1; pos = +scrub.value / 1000 * T; const L = locate(pos); if (L.i !== sel) { sel = L.i; renderClips(false); edUi(); } show(L.i, L.off); timeUi(); };
    scrub.onchange = () => { scrubbing = false; };
    function timeUi() { K('time').textContent = fmt(pos) + ' / ' + fmt(total()); if (!scrubbing) scrub.value = Math.round(pos / (total() || 1) * 1000); if (sel >= 0 && clips[sel]) { const c = clips[sel]; K('edPh').style.left = (Math.min(1, Math.max(0, ((cur() ? cur().currentTime : 0)) / (c.dur || 1))) * 100) + '%'; } }
    function barUi() { const T = total() || 1; K('bar').innerHTML = clips.map((c, i) => '<i style="width:' + (len(c) / T * 100) + '%" class="' + (i === sel ? 'sel' : '') + '"></i>').join(''); }

    // ---- Clip-Liste ----
    function renderClips(full) {
      const box = K('clips');
      if (full !== false) box.innerHTML = clips.map((c, i) => `<button type="button" class="sn-c ${i === sel ? 'on' : ''}${c.auto ? ' auto' : ''}" data-ci="${i}"><span class="n">${i + 1}</span>${c.mute ? '<span class="mu">🔇</span>' : ''}${c.thumb ? `<img src="${c.thumb}" alt="">` : '<span class="ph">🎬</span>'}<b>${esc(c.name)}</b><small>${fmt1(len(c))}${(c.in || (c.out && c.dur && c.out < c.dur - .05)) ? ' · geschnitten' : ''}</small></button>`).join('')
        + `<label class="sn-add"><b>＋</b>Clips<input type="file" accept="video/*,.mov,.mp4,.m4v,.webm" multiple hidden></label>`;
      else box.querySelectorAll('.sn-c').forEach(b => b.classList.toggle('on', +b.dataset.ci === sel));
      box.querySelectorAll('.sn-c').forEach(b => b.onclick = () => { if (busy) return; stop(); sel = +b.dataset.ci; pos = starts()[sel]; show(sel, 0); renderClips(false); edUi(); barUi(); timeUi(); });
      const add = box.querySelector('.sn-add input'); if (add) add.onchange = e => { addFiles([...e.target.files]); e.target.value = ''; };
      K('cnt').textContent = clips.length ? '· ' + clips.length + (clips.length > 1 ? ' Clips' : ' Clip') + ' · ' + fmt(total()) : '';
      q('#snempty').hidden = !!clips.length; big.hidden = !!playing || !clips.length;
      K('done').disabled = !clips.length;
    }
    function edUi() {
      const c = clips[sel]; K('ed').hidden = !c; if (!c) return;
      K('edT').textContent = 'Clip ' + (sel + 1) + ' von ' + clips.length + ' · ' + c.name;
      K('edD').textContent = 'von ' + fmt1(c.in || 0) + ' bis ' + fmt1(c.out || c.dur || 0) + ' · Länge ' + fmt1(len(c));
      const D = c.dur || 1; K('edIn').style.left = ((c.in || 0) / D * 100) + '%'; K('edIn').style.width = (len(c) / D * 100) + '%';
      K('mute').textContent = c.mute ? '🔊 Ton an' : '🔇 Ton aus'; K('left').disabled = sel === 0; K('right').disabled = sel === clips.length - 1;
    }
    function ui() { renderClips(); edUi(); barUi(); timeUi(); if (sel >= 0 && clips[sel]) show(sel, 0, false); }
    async function change(c) { await dbPut(dbRow(c)); c.thumb = null; ui(); thumb(c); }
    K('setA').onclick = () => { const c = clips[sel], v = cur(); if (!c || !v) return; c.in = Math.min(v.currentTime, (c.out || c.dur) - .3); change(c); say('⟦ Anfang gesetzt bei ' + fmt1(c.in) + '.'); };
    K('setB').onclick = () => { const c = clips[sel], v = cur(); if (!c || !v) return; c.out = Math.max(v.currentTime, (c.in || 0) + .3); change(c); say('Ende gesetzt bei ' + fmt1(c.out) + ' ⟧.'); };
    K('resetCut').onclick = () => { const c = clips[sel]; if (!c) return; c.in = 0; c.out = c.dur; change(c); };
    K('split').onclick = async () => { const c = clips[sel], v = cur(); if (!c || !v) return; const t = v.currentTime; if (t <= (c.in || 0) + .3 || t >= (c.out || c.dur) - .3) { say('Zum Teilen die Stelle etwas weiter in die Mitte schieben.'); return; }
      const n = Object.assign({}, c, { id: uid(), in: t, added: c.added + 1, thumb: null, url: c.url }); c.out = t; clips.splice(sel + 1, 0, n); await dbPut(dbRow(c)); await dbPut(dbRow(n)); await saveOrder(); c.thumb = null; ui(); thumb(c); thumb(n); say('✂ Geteilt bei ' + fmt1(t) + '. Der zweite Teil ist jetzt Clip ' + (sel + 2) + '.', 'ok'); };
    K('left').onclick = async () => { if (sel <= 0) return; [clips[sel - 1], clips[sel]] = [clips[sel], clips[sel - 1]]; sel--; await saveOrder(); ui(); };
    K('right').onclick = async () => { if (sel >= clips.length - 1) return; [clips[sel + 1], clips[sel]] = [clips[sel], clips[sel + 1]]; sel++; await saveOrder(); ui(); };
    K('mute').onclick = () => { const c = clips[sel]; if (!c) return; c.mute = !c.mute; dbPut(dbRow(c)); ui(); };
    K('delClip').onclick = async () => { const c = clips[sel]; if (!c) return; stop(); await dbDel(c.id); clips.splice(sel, 1); dropVid(c.url); if (!clips.some(x => x.url === c.url)) URL.revokeObjectURL(c.url); sel = Math.min(sel, clips.length - 1); await saveOrder(); pos = sel >= 0 ? starts()[sel] : 0; ui(); say('🗑 Clip gelöscht.'); };

    // ---- Regler ----
    const SL = { br: ['Helligkeit', .6, 1.5, .01, x => Math.round((x - 1) * 100) > 0 ? '+' + Math.round((x - 1) * 100) : String(Math.round((x - 1) * 100))], ct: ['Kontrast', .6, 1.5, .01, x => (x > 1 ? '+' : '') + Math.round((x - 1) * 100)], sa: ['Farbe', 0, 1.6, .01, x => Math.round(x * 100) + ' %'], se: ['Wärme', 0, .6, .01, x => x > .02 ? 'wärmer' : 'neutral'],
      vol: ['Lautstärke Musik', 0, 1, .01, x => Math.round(x * 100) + ' %'], voice: ['Lautstärke Stimme', 0, 1.5, .01, x => Math.round(x * 100) + ' %'] };
    const inputs = {};
    root.querySelectorAll('[data-sl]').forEach(el => {
      const k = el.dataset.sl, d = SL[k], isM = ['vol', 'voice'].includes(k), obj = isM ? mus : st;
      el.className = 'sn-sl'; el.innerHTML = '<label><span>' + d[0] + '</span><small></small></label><input type="range" min="' + d[1] + '" max="' + d[2] + '" step="' + d[3] + '">';
      const inp = el.querySelector('input'), sm = el.querySelector('small'); inputs[k] = inp; inp.value = obj[k]; sm.textContent = d[4](+obj[k]);
      inp.oninput = () => { obj[k] = +inp.value; sm.textContent = d[4](obj[k]); if (isM) ls.set('sn-mus', mus); else { st.look = ''; ls.set('sn-st', st); looksUi(); } apply(); };
    });
    function apply() { const v = cur(); if (v) v.style.filter = cmp ? '' : filterCss(st); }
    function sync() { Object.keys(inputs).forEach(k => { const obj = ['vol', 'voice'].includes(k) ? mus : st; inputs[k].value = obj[k]; inputs[k].previousElementSibling.querySelector('small').textContent = SL[k][4](+obj[k]); }); }
    function looksUi() { K('looks').innerHTML = LOOKS.map(l => '<button type="button" data-lk="' + l[0] + '" aria-pressed="' + (st.look === l[0]) + '">' + l[1] + '</button>').join('');
      K('looks').querySelectorAll('button').forEach(b => b.onclick = () => { const l = LOOKS.find(x => x[0] === b.dataset.lk); Object.assign(st, l[2]); st.look = l[0]; ls.set('sn-st', st); sync(); looksUi(); apply(); }); }
    looksUi();
    K('fmts').innerHTML = FMTS.map(f => '<button type="button" data-f="' + f[0] + '" aria-pressed="' + (st.fmt === f[0]) + '">' + f[1] + '</button>').join('');
    K('fmts').querySelectorAll('button').forEach(b => b.onclick = () => { st.fmt = b.dataset.f; ls.set('sn-st', st); fmtUi(); });
    function fmtUi() { K('fmts').querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.f === st.fmt))); pv.className = 'sn-pv' + ({ '9:16': ' p916', '4:5': ' p45', '1:1': ' p11' }[st.fmt] || ''); if (st.fmt === 'orig') { const v = cur(); if (v && v.videoWidth) pv.style.aspectRatio = v.videoWidth + '/' + v.videoHeight; else pv.style.aspectRatio = '16/9'; } else pv.style.aspectRatio = ''; }
    fmtUi();
    root.addEventListener('loadedmetadata', e => { if (e.target.tagName === 'VIDEO' && st.fmt === 'orig' && e.target === cur()) fmtUi(); }, true);

    // ---- Musik ----
    const au = new Audio(); au.loop = true; au.preload = 'auto';
    let lib = [], libTags = {}, libSel = 0; const pre = new Audio(); let preId = 0;
    function setMusic(f) { mfile = f; if (au.src) URL.revokeObjectURL(au.src); if (f) au.src = URL.createObjectURL(f); else au.removeAttribute('src'); K('mname').textContent = f ? '🎵 ' + f.name.replace(/\.\w+$/, '') : 'Noch keine Musik'; K('mdel').hidden = !f; libUi(); }
    function musicPlay() { if (!mfile) return; au.volume = Math.min(1, mus.vol); au.currentTime = pos % Math.max(.1, au.duration || 1e9); au.play().catch(() => {}); }
    K('mfile').onchange = e => { const f = e.target.files[0]; if (f) { libSel = 0; setMusic(f); } e.target.value = ''; };
    K('mdel').onclick = () => { au.pause(); libSel = 0; setMusic(null); };
    K('duck').checked = mus.duck; K('fade').checked = mus.fade;
    K('duck').onchange = () => { mus.duck = K('duck').checked; ls.set('sn-mus', mus); }; K('fade').onchange = () => { mus.fade = K('fade').checked; ls.set('sn-mus', mus); };
    function libUi() {
      const box = K('lib'); if (!o.musicUrl) { box.innerHTML = ''; return; }
      if (!lib.length) { box.innerHTML = '<p class="sn-note">Noch keine Lieder im Musik-Ordner.</p>'; return; }
      const f2 = s2 => s2 > 0 ? fmt(s2) : '';
      box.innerHTML = '<b style="font-weight:500;font-size:17px;color:#4A3A41">Aus deiner Musik</b><ul>' + lib.map(x => '<li class="' + (x.id === libSel ? 'on' : '') + '"><button type="button" data-pv="' + x.id + '" aria-label="Reinhören">' + (preId === x.id ? '❚❚' : '▶') + '</button><span>' + esc(x.t) + '<small>' + f2(x.dur) + '</small></span><button type="button" class="pk" data-take="' + x.id + '">' + (x.id === libSel ? '✓ gewählt' : 'Nehmen') + '</button></li>').join('') + '</ul>';
      box.querySelectorAll('[data-pv]').forEach(b => b.onclick = () => { const id = +b.dataset.pv; if (preId === id) { pre.pause(); preId = 0; } else { pre.src = o.musicUrl + id; pre.volume = .6; pre.play().catch(() => {}); preId = id; } libUi(); });
      box.querySelectorAll('[data-take]').forEach(b => b.onclick = async () => { const x = lib.find(y => y.id === +b.dataset.take); b.disabled = true; b.textContent = 'Lädt …'; try { const r = await fetch(o.musicUrl + x.id, { credentials: 'same-origin' }); const bl = await r.blob(); const f = new File([bl], x.t + '.mp3', { type: bl.type || 'audio/mpeg' }); pre.pause(); preId = 0; libSel = x.id; setMusic(f); say('🎵 „' + x.t + '“ liegt jetzt darunter.', 'ok'); } catch (e) { say('Das Lied konnte nicht laden.', 'bad'); libUi(); } });
    }
    if (o.musicUrl) fetch(o.musicUrl.replace(/&dl=$/, '&json=1'), { credentials: 'same-origin' }).then(r => r.json()).then(j => { lib = j.list || []; libTags = j.tags || {}; libUi(); }).catch(() => { K('lib').innerHTML = ''; });
    else libUi();

    // ---- ✨ Automatisch schneiden (Vivien, 30.10.2026) ----
    let undoRows = null, autoLog = [], autoP = null;
    const PAUSES = [[.4, 'kurz · 0,4 s'], [.7, 'normal · 0,7 s'], [1.2, 'nur lange · 1,2 s']];
    const ZOOMS = [[1, 1.06], [1.09, 1.04], [1.03, 1.08], [1.1, 1.05]]; // im Wechsel langsam hinein / heraus, leicht versetzt (kaschiert die Schnitte)
    function autoUi() {
      K('pauses').innerHTML = PAUSES.map(p => `<button type="button" data-p="${p[0]}" aria-pressed="${auto.pause === p[0]}">${p[1]}</button>`).join('');
      K('pauses').querySelectorAll('button').forEach(b => b.onclick = () => { auto.pause = +b.dataset.p; ls.set('sn-auto', auto); autoUi(); });
      K('stumbles').checked = auto.stumbles; K('zoomfx').checked = auto.zoom;
    }
    autoUi();
    K('stumbles').onchange = () => { auto.stumbles = K('stumbles').checked; ls.set('sn-auto', auto); };
    K('zoomfx').onchange = async () => { auto.zoom = K('zoomfx').checked; ls.set('sn-auto', auto); if (clips.some(c => c.auto)) { assignZoom(clips, auto.zoom); for (const c of clips) await dbPut(dbRow(c)); ui(); say(auto.zoom ? '✓ Sanfter Zoom liegt wieder über allen Abschnitten.' : 'Zoom ist aus.'); } };
    function assignZoom(list, on) { list.forEach((c, i) => c.zoom = on ? ZOOMS[i % ZOOMS.length].slice() : null); }
    function loadAuto() { if (window.SchnittAuto) return Promise.resolve(window.SchnittAuto); if (!autoP) autoP = new Promise((ok, no) => { const sc = document.createElement('script'); sc.src = 'schnitt-auto.js?v=1'; sc.onload = () => window.SchnittAuto ? ok(window.SchnittAuto) : no(new Error('auto')); sc.onerror = () => { autoP = null; no(new Error('auto')); }; document.head.appendChild(sc); }); return autoP; }
    // Ton eines Clips (nur in/out) stückweise in die Analyse füttern
    async function clipFrames(M, SA, c, onProg) {
      const input = new M.Input({ source: new M.BlobSource(c.blob), formats: M.ALL_FORMATS });
      const at = await input.getPrimaryAudioTrack(); if (!at || !(await at.canDecode())) return null;
      const sink = new M.AudioBufferSink(at), f = SA.framer(), a = c.in || 0, b = c.out || c.dur || 1e9; let got = 0;
      for await (const x of sink.buffers(a, b)) { if (closed) return null; const ab = x.buffer; const chans = []; for (let k = 0; k < ab.numberOfChannels; k++) chans.push(ab.getChannelData(k));
        // nur den Teil innerhalb von in/out nehmen
        let s0 = 0, s1 = ab.length; if (x.timestamp < a) s0 = Math.min(ab.length, Math.round((a - x.timestamp) * ab.sampleRate)); if (x.timestamp + ab.duration > b) s1 = Math.max(s0, Math.round((b - x.timestamp) * ab.sampleRate));
        if (s1 > s0) f.push(SA.toMono16k(chans.map(ch => ch.subarray(s0, s1)), ab.sampleRate)); got += s1 - s0; onProg && onProg(Math.min(1, (x.timestamp + ab.duration - a) / Math.max(.1, b - a))); }
      return got ? f.done() : null;
    }
    function autoResUi(sum) {
      const box = K('autoRes'); if (!sum) { box.hidden = true; return; } box.hidden = false;
      let h = `<b style="font-weight:500">✨ ${esc(sum.text)}</b>`;
      if (autoLog.length) h += `<div style="margin-top:6px">Rausgeschnitten, weil es nach einem Versprecher klingt (du hast kurz danach neu angesetzt). Hör rein, und hol es zurück, wenn ich mich geirrt habe:</div><ul>` + autoLog.map((e, i) => `<li><button type="button" class="sn-b sm" data-au="${i}">▶ Anhören</button><span>${esc(e.name)}<small> · ${fmt1(e.s)} bis ${fmt1(e.e)} (${fmt1(e.e - e.s)})</small></span><button type="button" class="sn-b sm" data-keep="${i}">Doch behalten</button></li>`).join('') + '</ul>';
      box.innerHTML = h;
      box.querySelectorAll('[data-au]').forEach(b => b.onclick = () => audition(autoLog[+b.dataset.au]));
      box.querySelectorAll('[data-keep]').forEach(b => b.onclick = () => keepBack(+b.dataset.keep));
    }
    let audT = 0;
    async function audition(e) { // die rausgeschnittene Stelle kurz vorspielen
      if (!e || busy) return; stop(); const v = vids.get(e.url); if (!v) return; vids.forEach(vv => { if (vv !== v) vv.classList.remove('on'); }); v.classList.add('on'); v.style.transform = ''; v.muted = false; v.currentTime = e.s; K('tag').textContent = 'Rausgeschnitten · Anhören';
      const my = ++audT; try { await v.play(); } catch (x) { return; }
      await new Promise(ok => { const st2 = () => { if (audT !== my || closed || v.paused && v.currentTime < e.e - .05) { ok(); return; } if (v.currentTime >= e.e - .03) { ok(); return; } requestAnimationFrame(st2); }; st2(); });
      if (audT !== my) return; v.pause(); K('tag').textContent = cmp ? 'Vorher' : ''; if (sel >= 0) show(sel, 0);
    }
    async function keepBack(i) {
      const e = autoLog[i]; if (!e) return; autoLog.splice(i, 1);
      const c = { id: uid(), blob: e.blob, url: e.url, name: e.name, added: Date.now(), in: e.s, out: e.e, mute: false, dur: e.dur, zoom: null, auto: true };
      let at = clips.findIndex(x => x.url === e.url && (x.in || 0) >= e.e); if (at < 0) { let last = -1; clips.forEach((x, k) => { if (x.url === e.url) last = k; }); at = last + 1; }
      clips.splice(at, 0, c); if (auto.zoom) assignZoom(clips, true); await dbPut(dbRow(c)); for (const x of clips) if (x.zoom) await dbPut(dbRow(x)); await saveOrder(); vidFor(c); sel = at; pos = starts()[sel]; ui(); thumb(c);
      autoResUi(lastSum); say('✓ Die Stelle ist wieder drin (Clip ' + (at + 1) + ').', 'ok');
    }
    let lastSum = null;
    K('auto').onclick = async () => {
      if (busy || !clips.length) return; stop(); busy = true; root.classList.add('sn-busy'); K('auto').disabled = K('done').disabled = true;
      const bar = K('prog'), fill = bar.querySelector('i'); bar.hidden = false; fill.style.width = '0%'; say('Ich höre mir deine Clips an …');
      const before = total(), snap = { rows: clips.map(dbRow), order: clips.map(c => c.id) };
      try {
        const [M, SA] = await Promise.all([loadMB(), loadAuto()]);
        const next = [], log = []; let pn = 0, psec = 0, noAudio = 0;
        for (let i = 0; i < clips.length; i++) {
          const c = clips[i]; say('Ich höre mir Clip ' + (i + 1) + ' von ' + clips.length + ' an …');
          let F = null; try { F = await clipFrames(M, SA, c, p => { fill.style.width = ((i + p) / clips.length * 100) + '%'; }); } catch (e) { console.warn('auto audio', e); F = null; }
          if (closed) return;
          if (!F) { noAudio++; next.push(Object.assign({}, c, { auto: true })); continue; }
          const r = SA.analyze(F, 16000, { minPause: auto.pause, stumbles: auto.stumbles });
          if (!r.segs.length) { next.push(Object.assign({}, c, { auto: true })); continue; } // nur Stille erkannt → lieber so lassen
          pn += r.pauses.n; psec += r.pauses.sec; const a0 = c.in || 0;
          r.segs.forEach((sg, k) => next.push({ id: uid(), blob: c.blob, url: c.url, name: c.name, added: c.added + k, in: a0 + sg.s, out: a0 + sg.e, mute: !!c.mute, dur: c.dur, auto: true, zoom: null }));
          r.cut.forEach(sg => log.push({ url: c.url, blob: c.blob, name: c.name, dur: c.dur, s: a0 + sg.s, e: a0 + sg.e }));
        }
        if (!next.length) { say('Da war nichts zu schneiden.'); return; }
        // Übernehmen: alte Zeilen raus, neue rein
        for (const c of clips) await dbDel(c.id);
        clips = next; assignZoom(clips, auto.zoom); for (const c of clips) await dbPut(dbRow(c)); await saveOrder();
        undoRows = snap; autoLog = log; sel = 0; pos = 0; clips.forEach(c => vidFor(c)); ui(); clips.forEach(thumb);
        const after = total(), saved = before - after;
        const parts = []; if (pn) parts.push(pn + (pn === 1 ? ' Pause' : ' Pausen') + ' rausgeschnitten'); if (log.length) parts.push(log.length + (log.length === 1 ? ' Versprecher' : ' Versprecher') + ' entfernt'); if (auto.zoom) parts.push('sanfter Zoom liegt über ' + clips.length + ' Abschnitten');
        lastSum = { text: (parts.length ? parts.join(', ') : 'nichts zu kürzen gefunden') + '. Vorher ' + fmt(before) + ', jetzt ' + fmt(after) + (saved > .5 ? ' (−' + fmt(saved) + ')' : '') + '.' + (noAudio ? ' ' + noAudio + (noAudio === 1 ? ' Clip hat' : ' Clips haben') + ' keinen Ton und blieb' + (noAudio === 1 ? '' : 'en') + ' wie ' + (noAudio === 1 ? 'er ist' : 'sie sind') + '.' : '') };
        autoResUi(lastSum); K('undo').hidden = false;
        say('✓ Fertig geschnitten. Mit ▶ anschauen – jeder Abschnitt ist jetzt ein eigener Clip, du kannst alles noch anpassen.', 'ok');
      } catch (e) { console.warn('auto', e); say('Das automatische Schneiden hat nicht geklappt. Bitte nochmal versuchen (am besten in Safari oder Chrome).', 'bad'); }
      finally { busy = false; root.classList.remove('sn-busy'); K('auto').disabled = false; K('done').disabled = !clips.length; bar.hidden = true; }
    };
    K('undo').onclick = async () => {
      if (busy || !undoRows) return; stop();
      const urls = new Map(clips.map(c => [c.blob, c.url]));
      for (const c of clips) await dbDel(c.id);
      clips = undoRows.rows.map(r => { let u = urls.get(r.blob); if (!u) u = URL.createObjectURL(r.blob); return Object.assign({}, r, { url: u }); });
      for (const c of clips) await dbPut(dbRow(c)); await kvSet('order', undoRows.order).catch(() => {});
      undoRows = null; autoLog = []; lastSum = null; K('undo').hidden = true; autoResUi(null);
      [...vids.keys()].forEach(u => dropVid(u)); clips.forEach(c => vidFor(c)); sel = 0; pos = 0; ui(); clips.forEach(thumb); say('↩ Zurück auf den Stand vor dem automatischen Schnitt.');
    };

    // ---- Fertig stellen ----
    let mbP = null;
    function loadMB() { if (window.Mediabunny) return Promise.resolve(window.Mediabunny); if (!mbP) mbP = new Promise((ok, no) => { const sc = document.createElement('script'); sc.src = (o.vendor || 'vendor/') + 'mediabunny.min.js?v=1'; sc.onload = () => window.Mediabunny ? ok(window.Mediabunny) : no(new Error('mb')); sc.onerror = () => { mbP = null; no(new Error('mb')); }; document.head.appendChild(sc); }); return mbP; }
    function outSize(w0, h0) { let w = w0, h = h0; if (st.fmt !== 'orig') { const [x, y] = st.fmt.split(':').map(Number), r = x / y; if (r >= w0 / h0) { w = w0; h = w0 / r; } else { h = h0; w = h0 * r; } } const k = Math.min(1, 3840 / Math.max(w, h)); return [Math.round(w * k / 2) * 2, Math.round(h * k / 2) * 2]; }
    // WebGL-Filter (gleiche Rechnung wie die CSS-Filter der Vorschau)
    function makeGL(cv) {
      const gl = cv.getContext('webgl', { preserveDrawingBuffer: true, premultipliedAlpha: false }); if (!gl) return null;
      const sh = (t, s) => { const x = gl.createShader(t); gl.shaderSource(x, s); gl.compileShader(x); return x; };
      const P = gl.createProgram(); gl.attachShader(P, sh(gl.VERTEX_SHADER, VS)); gl.attachShader(P, sh(gl.FRAGMENT_SHADER, FS)); gl.linkProgram(P); gl.useProgram(P);
      const bf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, bf); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
      const loc = gl.getAttribLocation(P, 'p'); gl.enableVertexAttribArray(loc); gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
      const U = {}; ['t', 'so', 'oo', 'br', 'ct', 'sa', 'se', 'zm'].forEach(n => U[n] = gl.getUniformLocation(P, n));
      const tex = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, tex);
      [[gl.TEXTURE_MIN_FILTER, gl.LINEAR], [gl.TEXTURE_MAG_FILTER, gl.LINEAR], [gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE], [gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE]].forEach(x => gl.texParameteri(gl.TEXTURE_2D, x[0], x[1]));
      gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
      return (el, sw, shh, z) => { gl.viewport(0, 0, cv.width, cv.height); gl.bindTexture(gl.TEXTURE_2D, tex); try { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, el); } catch (e) { return false; }
        gl.uniform1i(U.t, 0); gl.uniform2f(U.so, sw, shh); gl.uniform2f(U.oo, cv.width, cv.height); gl.uniform1f(U.br, st.br); gl.uniform1f(U.ct, st.ct); gl.uniform1f(U.sa, st.sa); gl.uniform1f(U.se, st.se); gl.uniform1f(U.zm, z || 1); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4); return true; };
    }
    async function mixAudio(M, inputs, D, SR) { // inputs: [{input, c, start}] → AudioBuffer oder null
      const N = Math.max(1, Math.ceil(D * SR)); let bufs = [];
      for (const x of inputs) { if (x.c.mute) continue; try { const at = await x.input.getPrimaryAudioTrack(); if (!at || !(await at.canDecode())) continue; const as = new M.AudioBufferSink(at);
        for await (const b of as.buffers(Math.max(0, x.c.in - 1), x.c.out + 1)) { if (closed) return null; const rel = b.timestamp - x.c.in; const endRel = rel + b.buffer.duration; if (endRel <= 0 || rel >= len(x.c)) continue; bufs.push({ buffer: b.buffer, at: x.start + rel, clipStart: x.start, clipEnd: x.start + len(x.c) }); } } catch (e) {} }
      if (!bufs.length && !mfile) return null;
      const place = (ctx, dst) => { const gains = new Map(); bufs.forEach(b => { let g = gains.get(b.clipStart); if (!g) { // je Clip 20 ms ein-/ausblenden, damit die Schnitte nicht knacken
          g = new GainNode(ctx, { gain: 0 }); g.connect(dst); const f = Math.min(.02, (b.clipEnd - b.clipStart) / 4); g.gain.setValueAtTime(0, b.clipStart); g.gain.linearRampToValueAtTime(1, b.clipStart + f); g.gain.setValueAtTime(1, Math.max(b.clipStart + f, b.clipEnd - f)); g.gain.linearRampToValueAtTime(0, b.clipEnd); gains.set(b.clipStart, g); }
        const s0 = new AudioBufferSourceNode(ctx, { buffer: b.buffer }); s0.connect(g); const off = b.at < 0 ? -b.at : 0, when = Math.max(0, b.at), dur = Math.min(b.buffer.duration - off, b.clipEnd - when); if (dur > 0) s0.start(when, off, dur); }); };
      let rms = null;
      if (mfile && mus.duck && bufs.length) { const c1 = new OfflineAudioContext(1, N, SR); place(c1, c1.destination); const r1 = (await c1.startRendering()).getChannelData(0); const win = Math.round(SR * .05); rms = []; for (let i = 0; i < r1.length; i += win) { let s = 0, n = Math.min(win, r1.length - i); for (let j = 0; j < n; j++) s += r1[i + j] * r1[i + j]; rms.push(Math.sqrt(s / Math.max(1, n))); } }
      const c2 = new OfflineAudioContext(2, N, SR), vg = new GainNode(c2, { gain: mus.voice }); vg.connect(c2.destination); place(c2, vg);
      if (mfile) { const mb = await new Promise((ok, no) => mfile.arrayBuffer().then(ab => { const tmp = new OfflineAudioContext(2, SR, SR); tmp.decodeAudioData(ab, ok, no); }, no)).catch(() => null);
        if (mb) { const ms = new AudioBufferSourceNode(c2, { buffer: mb, loop: true }), mg = new GainNode(c2, { gain: 0 }); ms.connect(mg); mg.connect(c2.destination); ms.start(0);
          let duckT = -9, prev = 0; for (let k = 0, t = 0; t < D; k++, t = k * .05) { let g = mus.vol; if (mus.fade) g *= Math.max(0, Math.min(1, t / 1.5, (D - t) / 2.5)); if (rms && (rms[k] || 0) > .025) duckT = t; if (rms && t - duckT < .6) g *= .35; if (Math.abs(g - prev) > .001 || k === 0) { mg.gain.setTargetAtTime(g, t, g < prev ? .12 : .45); prev = g; } } } }
      return c2.startRendering();
    }
    async function fastRender(fill) {
      if (!window.VideoEncoder || !window.VideoDecoder || !window.OfflineAudioContext) return null;
      const M = await loadMB(); const D = total(), SR = 48000;
      const ins = []; let start = 0; for (const c of clips) { const input = new M.Input({ source: new M.BlobSource(c.blob), formats: M.ALL_FORMATS }); const vt = await input.getPrimaryVideoTrack(); if (!vt || !(await vt.canDecode())) return null; const rot = (await vt.getRotation?.()) ?? vt.rotation ?? 0; ins.push({ input, vt, rot, c, start }); start += len(c); }
      // Ausgabegröße nach dem ersten Clip (Drehung beachten)
      const f0 = ins[0]; let w0 = f0.vt.displayWidth || f0.vt.codedWidth, h0 = f0.vt.displayHeight || f0.vt.codedHeight; if (f0.rot % 180) [w0, h0] = [h0, w0];
      const [W, H] = outSize(w0, h0), cv = document.createElement('canvas'); cv.width = W; cv.height = H; const draw = makeGL(cv); if (!draw) return null;
      const vbr = brate(W, H), vc = await M.getFirstEncodableVideoCodec(['avc', 'hevc', 'vp9', 'av1'], { width: W, height: H, bitrate: vbr }); if (!vc) return null;
      say('Ton wird gemischt …'); const mixed = await mixAudio(M, ins, D, SR); if (closed) return 'x';
      let parts = [], end = 0;
      const put = (p, data) => { const bl = new Blob([data]), pe = p + data.byteLength; if (p === end) { parts.push({ p, b: bl }); end = pe; return; } if (p > end) { parts.push({ p: end, b: new Blob([new Uint8Array(p - end)]) }, { p, b: bl }); end = pe; return; }
        const nx = []; for (const x of parts) { const xs = x.p, xe = x.p + x.b.size; if (xe <= p || xs >= pe) { nx.push(x); continue; } if (xs < p) nx.push({ p: xs, b: x.b.slice(0, p - xs) }); if (xe > pe) nx.push({ p: pe, b: x.b.slice(pe - xs) }); } nx.push({ p, b: bl }); nx.sort((x, y) => x.p - y.p); parts = nx; if (pe > end) end = pe; };
      const target = new M.StreamTarget(new WritableStream({ write(ch) { put(ch.position, ch.data); } }), { chunked: true, chunkSize: 4 * 1024 * 1024 });
      const out = new M.Output({ format: new M.Mp4OutputFormat({ fastStart: false }), target });
      const cs = new M.CanvasSource(cv, { codec: vc, bitrate: vbr, keyFrameInterval: 2, latencyMode: 'realtime' }); out.addVideoTrack(cs, { frameRate: 30 });
      let as2 = null; if (mixed) { const ac2 = await M.getFirstEncodableAudioCodec(['aac', 'opus'], { numberOfChannels: 2, sampleRate: SR, bitrate: 160000 }); if (ac2) { as2 = new M.AudioBufferSource({ codec: ac2, bitrate: 160000 }); out.addAudioTrack(as2); } else if (!window.MediaRecorder) return null; }
      await out.start(); if (as2) await as2.add(mixed);
      say('Wird fertig gestellt … Seite offen lassen.'); const W0 = performance.now(); let lastTs = -1;
      for (const x of ins) {
        const direct = !x.rot, vs = direct ? new M.VideoSampleSink(x.vt) : new M.CanvasSink(x.vt, { poolSize: 2 }), L = len(x.c);
        const it = direct ? vs.samples(x.c.in, x.c.out) : vs.canvases(x.c.in, x.c.out);
        for await (const smp of it) {
          if (closed) { try { await out.cancel(); } catch (e) {} return 'x'; }
          let el, sw, shh, tsrc, dur; if (direct) { const f = smp.toVideoFrame(); smp.close(); el = f; sw = f.displayWidth; shh = f.displayHeight; tsrc = f.timestamp / 1e6; dur = (f.duration || 0) / 1e6; } else { el = smp.canvas; sw = el.width; shh = el.height; tsrc = smp.timestamp; dur = smp.duration; }
          const ts = x.start + Math.max(0, tsrc - x.c.in); const ok2 = ts > lastTs && ts < D;
          if (ok2) { draw(el, sw, shh, zAt(x.c, tsrc - x.c.in)); lastTs = ts; } if (direct) el.close();
          if (ok2) await cs.add(ts, Math.max(1 / 60, Math.min(dur || 1 / 30, D - ts)));
          fill.style.width = Math.min(100, ts / D * 100) + '%';
          const el2 = (performance.now() - W0) / 1000; if (!window.__snFastForce && el2 > 4 && D - ts > 4 && ts / el2 < 1.1) { try { await out.cancel(); } catch (e) {} return null; }
        }
      }
      await out.finalize(); return new Blob(parts.map(x => x.b), { type: 'video/mp4' });
    }
    // Rückfall: in Echtzeit über Canvas + MediaRecorder (alle Browser, dauert so lang wie das Video)
    async function slowRender(fill) {
      if (!window.MediaRecorder) return null; const v0 = vids.get(clips[0].url); if (!v0 || !v0.videoWidth) return null;
      const [W, H] = outSize(v0.videoWidth, v0.videoHeight), cv = document.createElement('canvas'); cv.width = W; cv.height = H; const draw = makeGL(cv); if (!draw) return null;
      const ac = new (window.AudioContext || window.webkitAudioContext)(); await ac.resume().catch(() => {}); const dest = ac.createMediaStreamDestination(), vg = ac.createGain(); vg.gain.value = mus.voice; vg.connect(dest);
      const srcs = new Map(); let mg = null, msrc = null;
      if (mfile) { try { msrc = ac.createMediaElementSource(au); mg = ac.createGain(); mg.gain.value = 0; msrc.connect(mg); mg.connect(dest); } catch (e) {} }
      const stream = cv.captureStream(30); dest.stream.getAudioTracks().forEach(t => stream.addTrack(t));
      const mt = mime(), ch = [], r = new MediaRecorder(stream, Object.assign(mt ? { mimeType: mt } : {}, { videoBitsPerSecond: brate(W, H), audioBitsPerSecond: 160000 })); r.ondataavailable = e => { if (e.data && e.data.size) ch.push(e.data); }; const done = new Promise(ok => r.onstop = ok);
      const D = total(); say('Wird fertig gestellt … dauert so lang wie das Video (' + fmt(D) + '). Seite offen lassen.');
      r.start(500); let t0 = 0; const startAt = performance.now();
      if (mfile) { au.currentTime = 0; au.play().catch(() => {}); }
      for (let i = 0; i < clips.length && !closed; i++) {
        const c = clips[i], v = vids.get(c.url); v.classList.remove('on'); v.style.transform = ''; v.muted = true; v.currentTime = c.in || 0; await new Promise(ok => { v.onseeked = ok; setTimeout(ok, 1500); });
        if (!c.mute && !srcs.has(c.url)) { try { const s = ac.createMediaElementSource(v); s.connect(vg); srcs.set(c.url, s); } catch (e) {} }
        if (c.mute) v.muted = true; else v.muted = false; v.volume = 1;
        await v.play().catch(() => {});
        await new Promise(ok => { const step = () => { if (closed) { ok(); return; } draw(v, v.videoWidth, v.videoHeight, zAt(c, v.currentTime - (c.in || 0))); const t = t0 + (v.currentTime - (c.in || 0));
          if (mg) { let g = mus.vol; if (mus.fade) g *= Math.max(0, Math.min(1, t / 1.5, (D - t) / 2.5)); mg.gain.setTargetAtTime(g, ac.currentTime, .2); }
          fill.style.width = Math.min(100, t / D * 100) + '%'; if (v.ended || v.currentTime >= (c.out || c.dur) - .03) { ok(); return; } (v.requestVideoFrameCallback ? v.requestVideoFrameCallback(step) : requestAnimationFrame(step)); }; step(); v.onended = ok; });
        v.pause(); t0 += len(c);
      }
      await new Promise(x => setTimeout(x, 150)); r.stop(); await done; au.pause(); stream.getVideoTracks().forEach(t => t.stop()); try { ac.close(); } catch (e) {}
      clips.forEach(c => { const v = vids.get(c.url); if (v) v.muted = !!c.mute; });
      if (closed) return 'x'; const type = (r.mimeType || mt || 'video/webm').split(';')[0]; return new Blob(ch, { type });
    }
    let result = null;
    K('done').onclick = async () => {
      if (busy || !clips.length) return; stop(); busy = true; root.classList.add('sn-busy'); K('done').disabled = true; K('res').hidden = true;
      const bar = K('prog'), fill = bar.querySelector('i'); bar.hidden = false; fill.style.width = '0%'; say('Wird vorbereitet …');
      let nb = null, fast = true;
      try { nb = await fastRender(fill); } catch (e) { console.warn('schnitt fast', e); nb = null; }
      if (nb === 'x' || closed) { busy = false; return; }
      if (!nb || nb.size < 1000) { fast = false; fill.style.width = '0%'; try { nb = await slowRender(fill); } catch (e) { console.warn('schnitt slow', e); nb = null; } }
      busy = false; root.classList.remove('sn-busy'); K('done').disabled = false; bar.hidden = true;
      if (nb === 'x' || closed) return;
      if (!nb || nb.size < 1000) { say('Das hat nicht geklappt. Bitte nochmal „Fertig stellen“ tippen (am besten in Safari oder Chrome).', 'bad'); return; }
      result = nb; const url = URL.createObjectURL(nb), ext = nb.type.includes('mp4') ? 'mp4' : 'webm';
      const d = new Date(), stamp = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + '_' + String(d.getHours()).padStart(2, '0') + '-' + String(d.getMinutes()).padStart(2, '0');
      K('resv').src = url; K('dl').href = url; K('dl').download = 'Movecia_Schnitt_' + stamp + '.' + ext; K('res').hidden = false;
      const canSh = !!(navigator.canShare && (() => { try { return navigator.canShare({ files: [new File([nb], 'v.' + ext, { type: nb.type })] }); } catch (e) { return false; } })());
      K('share').hidden = !canSh; K('dl').className = 'sn-b' + (canSh ? '' : ' pl');
      K('resN').textContent = fmt(total()) + ' · ' + clips.length + (clips.length > 1 ? ' Clips' : ' Clip') + (mfile ? ' · mit Musik' : '') + (fast ? '' : ' · in Echtzeit gespeichert');
      say(canSh ? '✓ Fertig. Schau es dir an, dann „In Fotos sichern“ (landet direkt in deiner Galerie) oder im Studio hochladen.' : '✓ Fertig. Schau es dir an, dann herunterladen oder im Studio hochladen.', 'ok'); K('res').scrollIntoView({ behavior: 'smooth', block: 'start' });
      try { await kvSet('result', { blob: nb, at: Date.now() }); } catch (e) {}
    };
    K('share').onclick = async () => { if (!result) return; const ext = result.type.includes('mp4') ? 'mp4' : 'webm'; try { await navigator.share({ files: [new File([result], K('dl').download || ('video.' + ext), { type: result.type })] }); } catch (e) {} };
    K('resX').onclick = () => { K('res').hidden = true; };
    if (K('toStudio')) K('toStudio').onclick = async () => { if (!result) return; try { await kvSet('result', { blob: result, at: Date.now() }); } catch (e) {} o.toStudio(result); };
    K('dl').addEventListener('click', () => {});

    function close() { closed = true; stop(); try { pre.pause(); } catch (e) {} clips.forEach(c => URL.revokeObjectURL(c.url)); mount.innerHTML = ''; }
    load();
    return { close, add: addFiles };
  }
  // Fertiges Video aus dem Schnitt holen (für das Studio) und wieder vergessen
  async function takeResult() { try { const r = await kvGet('result'); if (r && r.blob) { await kvDel('result'); return r.blob; } } catch (e) {} return null; }
  window.Schnitt = { open, addTake, takeResult };
})();

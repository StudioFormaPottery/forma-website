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
 * - Entwürfe (Vivien, 30.10.2026): jedes Video ist ein eigener Entwurf. Erst alle Aufnahmen machen, später in Ruhe schneiden.
 *   Takes aus dem Studio landen automatisch im Entwurf mit dem Namen der Lektion.
 * Schnitt.open({mount, musicUrl, onDone(blob), toStudio(blob)}) · Schnitt.addTake(blob, name, {draft, open}) (aus dem Studio)
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
  const sec1 = v => (Math.round((v || 0) * 10) / 10).toFixed(1).replace('.', ',') + ' s'; // kurze Dauer als Sekunden, nicht als Uhrzeit
  const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const ls = { get: (k, d) => { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } }, set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };

  // ---- Entwürfe: je Video ein eigener (Clips tragen die Entwurfs-Nummer) ----
  const ordKey = id => 'order:' + id;
  async function drafts() { return (await kvGet('drafts')) || []; }
  async function draftsSave(l) { await kvSet('drafts', l); }
  // Sorgt dafür, dass es einen aktuellen Entwurf gibt; holt alte Clips ohne Entwurf einmalig hinein
  async function ensureDraft() {
    let l = await drafts(), cur = await kvGet('cur');
    const all = await dbAll(), alt = all.filter(c => !c.draft);
    if (alt.length && !l.some(d => d.migrated)) {
      const d = { id: uid(), name: 'Mein Schnitt', at: Date.now(), migrated: true };
      l.unshift(d); await draftsSave(l);
      const ord = (await kvGet('order')) || alt.map(c => c.id);
      for (const c of alt) { c.draft = d.id; await dbPut(c); }
      await kvSet(ordKey(d.id), ord.filter(x => alt.some(c => c.id === x))); cur = d.id; await kvSet('cur', cur);
    }
    if (!l.length) { const d = { id: uid(), name: '', at: Date.now() }; l = [d]; await draftsSave(l); cur = d.id; await kvSet('cur', cur); }
    if (!cur || !l.some(d => d.id === cur)) { cur = l[0].id; await kvSet('cur', cur); }
    return cur;
  }
  // Entwurf mit diesem Namen finden oder anlegen
  async function draftFor(name) {
    const n = String(name || '').trim(); if (!n) return ensureDraft();
    const l = await drafts(); const hit = l.find(d => (d.name || '').toLowerCase() === n.toLowerCase());
    if (hit) return hit.id;
    const d = { id: uid(), name: n, at: Date.now() }; l.unshift(d); await draftsSave(l); return d.id;
  }
  // Ein Take aus dem Studio in den Schnitt legen (bleibt auf dem Gerät)
  async function addTake(blob, name, opt) {
    opt = opt || {};
    const did = opt.draft ? await draftFor(opt.draft) : await ensureDraft();
    const c = { id: uid(), blob, name: name || 'Aufnahme', added: Date.now(), in: 0, out: 0, mute: false, draft: did };
    await dbPut(c); const order = (await kvGet(ordKey(did))) || []; order.push(c.id); await kvSet(ordKey(did), order);
    const l = await drafts(); const d = l.find(x => x.id === did); if (d) { d.at = Date.now(); await draftsSave(l); }
    if (opt.open) await kvSet('cur', did);
    return c.id;
  }

  const CSS = `
.sn{font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;color:#2E2529;display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,400px);gap:22px;align-items:start}
@media(max-width:980px){.sn{display:flex;flex-direction:column;gap:14px}.sn>*{min-width:0;max-width:100%}}
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
.sn-zt{position:relative;max-width:100%;margin-top:10px;border-radius:16px;background:#fff;border:1.5px solid #E6DCD2;overflow:hidden}
.sn-zt-sc{overflow-x:auto;overflow-y:hidden;scrollbar-width:thin;overscroll-behavior-x:contain;touch-action:pan-x pinch-zoom;cursor:crosshair}
.sn-zt-in{position:relative;height:104px;min-width:100%;user-select:none;-webkit-user-select:none;touch-action:pan-x}
.sn-zt-tt{position:absolute;left:0;right:0;top:0;height:20px}
.sn-zt-blocks{position:absolute;left:0;right:0;top:20px;bottom:0}
.sn-zt-bl{position:absolute;top:6px;bottom:8px;border-radius:10px;background:linear-gradient(#EFE3E6,#E3D4D9);border:1.5px solid #CBB6BE;overflow:hidden;cursor:pointer}
.sn-zt-bl.on{border-color:#7D5E68;background:linear-gradient(#E7D7DC,#D8C2CA);box-shadow:0 0 0 2px rgba(125,94,104,.35)}
.sn-zt-bl .lb{position:absolute;left:7px;top:5px;right:7px;font-size:12.5px;color:#4A3A41;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none}
.sn-zt-bl .ln{position:absolute;left:7px;bottom:5px;font-size:12px;color:#6E6168;font-variant-numeric:tabular-nums;pointer-events:none}
.sn-zt-bl.mu .lb::after{content:" 🔇"}
.sn-zt-g{position:absolute;top:6px;bottom:8px;border-radius:10px;background:repeating-linear-gradient(135deg,#F3ECE4,#F3ECE4 5px,#E8DCD0 5px,#E8DCD0 10px);border:1.5px dashed #CFBEB0;overflow:hidden;pointer-events:none}
.sn-zt-g span{position:absolute;left:5px;right:5px;top:50%;transform:translateY(-50%);font-size:11.5px;color:#8A7A6E;white-space:nowrap;overflow:hidden;text-align:center}
.sn-zt-cut{position:absolute;top:6px;bottom:8px;width:16px;margin-left:-8px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#8C2C1D;background:rgba(140,44,29,.10);border-left:1.5px dashed #C08C82;border-right:1.5px dashed #C08C82;border-radius:4px;pointer-events:none;z-index:1}
.sn-zt-h{position:absolute;top:6px;bottom:8px;width:16px;border-radius:8px;background:#7D5E68;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;cursor:ew-resize;touch-action:none;box-shadow:0 1px 4px rgba(46,37,41,.35);z-index:2}
.sn-zt-h.a{border-top-right-radius:2px;border-bottom-right-radius:2px}
.sn-zt-h.b{border-top-left-radius:2px;border-bottom-left-radius:2px;margin-left:-16px}
.sn-zt-h.off{background:#B9A2AC}
.sn-zt-tk{position:absolute;top:0;bottom:0;width:1px;background:#EADFDE}
.sn-zt-tk.big{background:#D3C4CB}
.sn-zt-tk span{position:absolute;top:4px;left:4px;font-size:11px;color:#9A8C93;white-space:nowrap;font-variant-numeric:tabular-nums}
.sn-zt-ph{position:absolute;top:0;bottom:0;width:2px;background:#2E2529;transform:translateX(-1px);z-index:3;pointer-events:none}
.sn-zt-ph::before{content:"";position:absolute;top:0;left:-8px;border:9px solid transparent;border-top:12px solid #2E2529;border-bottom:0}
.sn-zt-ph::after{content:"";position:absolute;bottom:0;left:-7px;width:16px;height:16px;border-radius:50%;background:#2E2529}
.sn-zt-bar{display:flex;align-items:center;gap:8px;padding:6px 8px;border-top:1.5px solid #EFE7DE;background:#FBF8F4;flex-wrap:wrap}
.sn-zt-bar .t{font-size:16px;color:#4A3A41;font-variant-numeric:tabular-nums;margin-left:auto}
.sn-zt-bar input[type=range]{flex:1;min-width:90px;max-width:220px;accent-color:#7D5E68}
.sn-zt-bar .zb{font:inherit;font-size:18px;width:36px;height:36px;border-radius:50%;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer}
.sn-ml{display:flex;flex-direction:column;gap:6px}
.sn-ml ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:5px}
.sn-ml li{display:flex;align-items:center;gap:8px;background:#FBF8F4;border:1.5px solid #EFE7DE;border-radius:12px;padding:6px 8px}
.sn-ml li .n{width:22px;height:22px;flex:none;border-radius:50%;background:#4A3040;color:#fff;font-size:12.5px;display:flex;align-items:center;justify-content:center}
.sn-ml li span{flex:1;min-width:0;font-size:15.5px;color:#4A3040;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sn-ml li span small{color:#7D7076;margin-left:6px}
.sn-ml li button{font:inherit;font-size:15px;width:34px;height:34px;flex:none;border-radius:50%;border:1.5px solid #DDD2C6;background:#fff;color:#4A3040;cursor:pointer}
.sn-ovl{display:none;margin:8px 2px 0;padding:10px 12px;border-radius:14px;background:#FBF1E8;border:1.5px solid #E2C9B4;font-size:15.5px;color:#4A3040;line-height:1.45}
.sn-ovl.on{display:block}
.sn-ovl b{font-weight:600}
.sn-ovl .r{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
.sn-ovl button{font:inherit;font-size:15px;padding:8px 12px;border-radius:12px;border:1.5px solid #D3C9BC;background:#fff;color:#4A3040;cursor:pointer}
.sn-ovl button.del{border-color:#C9A79A;color:#8C2C1D}
.sn-zt-hint{font-size:14px;color:#7D7076;margin:6px 2px 0;line-height:1.45}
.sn-box.dr{background:linear-gradient(135deg,#FBF8F4,#EFEAE2);border-color:#DDD2C6}
.sn-dr-name{width:100%;font:inherit;font-size:18px;padding:10px 12px;border-radius:14px;border:1.5px solid #DDD2C6;background:#fff;color:#2E2529}
.sn-dr-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;max-height:300px;overflow:auto}
.sn-dr-list li{display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid #E6DCD2;border-radius:16px;padding:8px 10px}
.sn-dr-list li.on{border-color:#7D5E68;box-shadow:0 0 0 2px #7D5E68 inset}
.sn-dr-list li span{flex:1;min-width:0;font-size:16px;color:#2E2529;line-height:1.3}
.sn-dr-list li small{display:block;color:#7D7076;font-size:13px}
.sn-dr-list li button{font:inherit;flex:0 0 auto;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer;min-height:38px;padding:5px 12px;font-size:15px}
.sn-dr-list li button.x{color:#8C2C1D;border-color:#E2C4BC;padding:5px 10px}
.sn-dr-cur{font-size:15px;color:#6E6168}
@media(max-width:980px){.sn-zt-in{height:92px}.sn-zt-hint{font-size:13px}}
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
.sn-pz .lb{font-size:16px;color:#4A3A41;margin-bottom:2px}
.sn-pz .lb b{color:#4A3040;font-variant-numeric:tabular-nums;font-size:18px}
.sn-pz input[type=range]{width:100%;accent-color:#7D5E68;height:36px;margin:0}
.sn-pz .sc{display:flex;justify-content:space-between;gap:10px;font-size:12.5px;color:#9A8C93;margin-top:-6px}
.sn-pz .sn-note{margin:4px 0 0}
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
    <div class="sn-row" style="margin-top:8px"><span class="t" data-k="time">0:00 / 0:00</span>
      <button type="button" class="sn-b pl" data-k="play">▶ Abspielen</button>
      <button type="button" class="sn-b" data-k="cmp" title="Licht & Farbe kurz ausschalten">Vorher zeigen</button></div>
    <div class="sn-zt">
      <div class="sn-zt-sc" data-k="ztsc"><div class="sn-zt-in" data-k="ztin"><div class="sn-zt-tt" data-k="ztt"></div><div class="sn-zt-blocks" data-k="ztb"></div><div class="sn-zt-ph" data-k="ztph"></div></div></div>
      <div class="sn-zt-bar"><button type="button" class="zb" data-k="zoomOut" aria-label="Zeitleiste zusammenziehen">－</button><input type="range" min="0" max="100" step="1" value="0" data-k="zoomR" aria-label="Zeitleiste auseinanderziehen"><button type="button" class="zb" data-k="zoomIn" aria-label="Zeitleiste auseinanderziehen">＋</button><span class="t" data-k="ztime">0:00,0</span></div>
    </div>
    <div class="sn-ovl" data-k="ovl"></div>
    <p class="sn-zt-hint" data-k="zthint">Tippen = Stelle anspringen · die Griffe <b>⟦ ⟧</b> an den Rändern ziehen = Anfang und Ende länger oder kürzer machen (das schraffierte Stück daneben ist das, was weggeschnitten wurde – da kannst du wieder hineinziehen) · der Griff darf auch über den nächsten Clip hinaus (was doppelt ist, kannst du darunter löschen) · zwei Finger auseinander (oder ＋) = Zeitleiste auseinanderziehen</p>
  </div>
  <h2 style="margin-top:16px" data-k="h2">Deine Clips <span style="font-size:16px;color:#7D7076;font-family:'Hanken Grotesk',sans-serif;font-weight:400" data-k="cnt"></span></h2>
  <div class="sn-clips" data-k="clips"></div>
  <div class="sn-row" style="margin-top:0">
    <label class="sn-b pl" style="cursor:pointer">＋ Clips vom Gerät<input type="file" accept="video/*,.mov,.mp4,.m4v,.webm" multiple hidden data-k="file"></label>
    ${o.studioUrl ? `<a class="sn-b" href="${esc(o.studioUrl)}">● Im Studio aufnehmen</a>` : ''}
    <button type="button" class="sn-b sm del" data-k="clearAll">Clips dieses Entwurfs löschen</button>
  </div>
  <div class="sn-ed" data-k="ed" hidden>
    <div class="h"><b data-k="edT"></b><span data-k="edD"></span></div>
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
  <details class="sn-box dr" open><summary>📝 Entwürfe</summary><div class="bd">
    <div><label style="display:block;font-size:16px;color:#4A3A41;margin-bottom:4px">Woran arbeitest du gerade?</label>
      <input type="text" class="sn-dr-name" maxlength="60" placeholder="z. B. The Method · Morgen-Flow" data-k="drName"></div>
    <p class="sn-note">Jede Aufnahme und jeder Schnitt gehört zu einem Entwurf. So kannst du zuerst alle Videos aufnehmen und später in Ruhe eines nach dem anderen fertig schneiden. Alles bleibt auf diesem Gerät gespeichert.</p>
    <ul class="sn-dr-list" data-k="drList"></ul>
    <button type="button" class="sn-b" data-k="drNew">＋ Neuer Entwurf</button>
  </div></details>
  <details class="sn-box auto" open><summary>✨ Automatisch schneiden</summary><div class="bd">
    <p class="sn-note" style="color:#4A3A41">Ein Tipp, und ich schneide Pausen raus, nehme heraus, wo du dich erst zurechtsetzt oder neu angesetzt hast, und lege einen sanften Zoom über die Abschnitte, damit es sich flüssig und lebendig anschaut. Danach siehst du jeden Schnitt in der Zeitleiste und kannst ihn mit den Griffen länger oder kürzer ziehen.</p>
    <button type="button" class="sn-b main" data-k="auto" style="font-size:18px;min-height:52px">✨ Jetzt automatisch schneiden</button>
    <div class="sn-pz">
      <div class="lb">Pausen rausschneiden ab <b data-k="pauseV">0,7 s</b></div>
      <input type="range" min="20" max="250" step="5" value="70" data-k="pauseR" aria-label="Pausen rausschneiden ab">
      <div class="sc"><span>jede Atempause</span><span>nur lange Pausen</span></div>
      <p class="sn-note" data-k="pauseH"></p>
      <button type="button" class="sn-b sm" data-k="pauseGo" hidden>✨ Mit dieser Einstellung nochmal schneiden</button>
    </div>
    <label class="sn-sw"><input type="checkbox" data-k="stumbles"> Versprecher erkennen (wenn du neu ansetzt)</label>
    <label class="sn-sw"><input type="checkbox" data-k="noisefx"> Stellen ohne Sprechen rausnehmen (in Position gehen, rascheln)</label>
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
    <div class="sn-ml" data-k="mlist"></div>
    <div class="sn-lib" data-k="lib"><p class="sn-note">Deine Musik wird geladen …</p></div>
    <label class="sn-b" style="width:max-content;cursor:pointer">📁 Lieder vom Gerät<input type="file" accept="audio/*,.mp3,.m4a,.wav,.aac" multiple hidden data-k="mfile"></label>
    <div data-sl="vol"></div><div data-sl="voice"></div>
    <label class="sn-sw"><input type="checkbox" data-k="duck"> Musik leiser, wenn ich spreche</label>
    <label class="sn-sw"><input type="checkbox" data-k="fade"> Musik sanft ein- und ausblenden</label>
  </div></details>
  <p class="sn-note">Deine Clips bleiben auf diesem Gerät gespeichert (auch nach dem Schließen), bis du sie hier löschst. Die Vorschau zeigt alles so, wie es nachher gespeichert wird.</p>
</div>`;
    const q = s => root.querySelector(s), K = k => root.querySelector('[data-k="' + k + '"]');
    const pv = q('#snpv'), msg = K('msg'), big = K('big');
    const say = (t, c) => { msg.className = 'sn-msg' + (c ? ' ' + c : ''); msg.textContent = t; };

    // ---- Zustand ----
    let clips = [], sel = -1, closed = false, busy = false, curDraft = null, drList = [];
    const st = Object.assign({ look: 'natur', br: 1, ct: 1, sa: 1, se: 0, fmt: 'orig' }, ls.get('sn-st', {}));
    const mus = Object.assign({ vol: .18, voice: 1, duck: true, fade: true }, ls.get('sn-mus', {}));
    const auto = Object.assign({ pause: .7, stumbles: true, zoom: true, noise: true }, ls.get('sn-auto', {}));
    let mlist = []; const XF = 2.5; // Musik-Playlist; XF = Sekunden Überblendung (bei kurzen Liedern weniger)
    const xfOf = d => Math.max(.4, Math.min(XF, (d || 30) / 4));
    const vids = new Map(); // „Platz“ (url#0 / url#1) → <video>: zwei je Datei im Wechsel, damit der nächste Abschnitt
    // schon vorgespult bereitliegt und es am Schnitt nicht stockt (Vivien, 30.10.2026: „Video stockt in der Mitte“)
    function slots() { const n = {}; clips.forEach(c => { n[c.url] = (n[c.url] || 0) + 1; c.slot = c.url + '#' + ((n[c.url] - 1) % 2); }); }
    function vidFor(c) {
      if (!c.slot) slots();
      let v = vids.get(c.slot);
      if (v) { if (!c.dur && v.readyState >= 1 && isFinite(v.duration) && v.duration > 0) { c.dur = v.duration; if (!c.out) c.out = c.dur; } return v; }
      v = document.createElement('video'); v.playsInline = true; v.setAttribute('playsinline', ''); v.preload = 'auto'; v.muted = false;
      v.src = c.url; pv.insertBefore(v, big); vids.set(c.slot, v);
      v.addEventListener('loadedmetadata', () => { const d = isFinite(v.duration) && v.duration > 0 ? v.duration : 0; let probe = false; clips.filter(x => x.url === c.url && !x.dur).forEach(x => { x.dur = d; if (!x.out) x.out = d; if (!d) probe = true; }); if (!c.dur) { c.dur = d; if (!c.out) c.out = d; if (!d) probe = true; } if (probe) probeDur(c, v); else ui(); });
      return v;
    }
    // Plätze aufräumen, die kein Clip mehr braucht
    function dropVids() { const used = new Set(clips.map(c => c.slot)); [...vids.keys()].forEach(k => { if (!used.has(k)) { const v = vids.get(k); if (v) { v.pause(); v.removeAttribute('src'); v.remove(); } vids.delete(k); } }); }
    // Zoom je Clip: [von, bis] als Faktor, wird über die Clip-Länge gleichmäßig gefahren
    const zAt = (c, off) => { if (!c || !c.zoom) return 1; const L = len(c) || 1, p = Math.max(0, Math.min(1, off / L)); return c.zoom[0] + (c.zoom[1] - c.zoom[0]) * p; };
    // MediaRecorder-Dateien kennen ihre Länge oft nicht: einmal ans Ende springen
    async function probeDur(c, v) { try { v.currentTime = 1e7; await new Promise(r => { v.onseeked = r; setTimeout(r, 2500); }); c.dur = isFinite(v.duration) && v.duration > 0 ? v.duration : (v.currentTime || 1); v.currentTime = 0; } catch (e) { c.dur = c.dur || 1; } if (!c.out || c.out > c.dur) c.out = c.dur; dbPut(dbRow(c)); ui(); }
    const dbRow = c => ({ id: c.id, blob: c.blob, name: c.name, added: c.added, in: c.in, out: c.out, mute: !!c.mute, dur: c.dur || 0, zoom: c.zoom || null, auto: !!c.auto, draft: c.draft || curDraft });
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
        curDraft = await ensureDraft(); drList = await drafts();
        const all = await dbAll(), rows = all.filter(r => r.draft === curDraft), order = (await kvGet(ordKey(curDraft))) || [];
        rows.sort((a, b) => (order.indexOf(a.id) + 1 || 1e9) - (order.indexOf(b.id) + 1 || 1e9) || a.added - b.added);
        const urls = new Map(); clips = rows.map(r => { let u = urls.get(r.blob); if (!u) { u = URL.createObjectURL(r.blob); urls.set(r.blob, u); } return Object.assign({}, r, { url: u }); });
        slots(); if (clips.length) { sel = 0; vidFor(clips[0]); prep(1); }
        drawDrafts(); ui(); clips.forEach(thumb);
      } catch (e) { say('Die gespeicherten Clips konnten nicht geladen werden.', 'bad'); }
    }
    // ---- Entwürfe ----
    const drName = id => { const d = drList.find(x => x.id === id); return (d && d.name) || 'Ohne Namen'; };
    async function drawDrafts() {
      if (closed) return;
      const inp = K('drName'); if (document.activeElement !== inp) inp.value = (drList.find(d => d.id === curDraft) || {}).name || '';
      const all = await dbAll().catch(() => []);
      const info = {}; all.forEach(c => { const k = c.draft; if (!k) return; const i = info[k] || (info[k] = { n: 0, sec: 0 }); i.n++; i.sec += Math.max(0, (c.out || c.dur || 0) - (c.in || 0)); });
      const when = t => { const d = new Date(t || 0), h = new Date(), s2 = x => String(x).padStart(2, '0');
        return (d.toDateString() === h.toDateString() ? 'heute' : d.getDate() + '.' + (d.getMonth() + 1) + '.') + ' ' + s2(d.getHours()) + ':' + s2(d.getMinutes()); };
      const L = drList.slice().sort((a, b) => (b.at || 0) - (a.at || 0));
      K('drList').innerHTML = L.map(d => { const i = info[d.id] || { n: 0, sec: 0 }, on = d.id === curDraft;
        return `<li class="${on ? 'on' : ''}"><span>${esc(d.name || 'Ohne Namen')}<small>${i.n ? i.n + (i.n > 1 ? ' Clips' : ' Clip') + (i.sec > .05 ? ' · ' + fmt(i.sec) : '') : 'noch leer'} · ${when(d.at)}${on ? ' · offen' : ''}</small></span>`
          + (on ? '' : `<button type="button" data-dopen="${d.id}">Öffnen</button>`)
          + `<button type="button" class="x" data-ddel="${d.id}" aria-label="Entwurf löschen" title="Entwurf löschen">🗑</button></li>`; }).join('');
      K('drList').querySelectorAll('[data-dopen]').forEach(b => b.onclick = () => openDraft(b.dataset.dopen));
      K('drList').querySelectorAll('[data-ddel]').forEach(b => b.onclick = () => delDraft(b.dataset.ddel));
      K('h2').firstChild.textContent = (drList.find(d => d.id === curDraft) || {}).name ? 'Clips in „' + drName(curDraft) + '“ ' : 'Deine Clips ';
    }
    K('drName').oninput = () => { const d = drList.find(x => x.id === curDraft); if (!d) return; d.name = K('drName').value.trim().slice(0, 60); d.at = Date.now(); draftsSave(drList).catch(() => {}); clearTimeout(K('drName')._t); K('drName')._t = setTimeout(drawDrafts, 600); };
    async function openDraft(id) {
      if (busy || id === curDraft) return; stop();
      clips.forEach(c => { if (!clips.some(x => x !== c && x.url === c.url)) URL.revokeObjectURL(c.url); });
      clips = []; dropVids(); sel = -1; pos = 0; undoRows = null; autoLog = []; lastSum = null; K('undo').hidden = true; autoResUi(null);
      await kvSet('cur', id).catch(() => {}); K('res').hidden = true;
      await load(); say('📝 „' + drName(id) + '“ ist offen.', 'ok');
    }
    async function delDraft(id) {
      const nm = drName(id); if (!confirm('Entwurf „' + nm + '“ mit allen Clips von diesem Gerät löschen?')) return;
      const all = await dbAll(); for (const c of all) if (c.draft === id) await dbDel(c.id);
      await kvDel(ordKey(id)).catch(() => {});
      drList = (await drafts()).filter(d => d.id !== id); await draftsSave(drList);
      if (id === curDraft) { clips.forEach(c => URL.revokeObjectURL(c.url)); clips = []; dropVids(); sel = -1; pos = 0; await kvDel('cur').catch(() => {}); await load(); }
      else drawDrafts();
      say('🗑 „' + nm + '“ gelöscht.');
    }
    K('drNew').onclick = async () => {
      if (busy) return; const d = { id: uid(), name: '', at: Date.now() };
      drList = await drafts(); drList.unshift(d); await draftsSave(drList);
      await openDraft(d.id); K('drName').focus();
    };
    async function addFiles(files) {
      let n = 0;
      for (const f of files) { if (!f || !f.size) continue; const c = { id: uid(), blob: f, name: f.name.replace(/\.\w+$/, ''), added: Date.now() + n, in: 0, out: 0, mute: false, dur: 0, draft: curDraft }; c.url = URL.createObjectURL(f); clips.push(c); await dbPut(dbRow(c)); slots(); vidFor(c); thumb(c); n++; }
      await saveOrder(); await touch(); if (sel < 0 && clips.length) sel = 0; if (n) say('✓ ' + n + (n > 1 ? ' Clips' : ' Clip') + ' dazu. Tipp auf einen Clip, um Anfang und Ende zu setzen.', 'ok'); ui();
    }
    const saveOrder = () => kvSet(ordKey(curDraft), clips.map(c => c.id)).catch(() => {});
    const touch = async () => { const d = drList.find(x => x.id === curDraft); if (d) { d.at = Date.now(); await draftsSave(drList).catch(() => {}); drawDrafts(); } };
    K('file').onchange = e => { addFiles([...e.target.files]); e.target.value = ''; };
    K('clearAll').onclick = async () => { if (!clips.length || !confirm('Wirklich alle ' + clips.length + ' Clips von diesem Gerät löschen?')) return; stop(); for (const c of clips) { await dbDel(c.id); } const urls = new Set(clips.map(c => c.url)); clips = []; dropVids(); urls.forEach(u => URL.revokeObjectURL(u)); sel = -1; await saveOrder(); undoRows = null; K('undo').hidden = true; K('autoRes').hidden = true; drawDrafts(); ui(); say('Alle Clips in „' + drName(curDraft) + '“ gelöscht.'); };

    // ---- Vorschau: Clips nacheinander abspielen ----
    let playing = false, pos = 0, raf = 0, cmp = false, prepped = false; // pos = Stelle im Gesamtvideo
    const cur = () => (sel >= 0 && clips[sel]) ? vids.get(clips[sel].slot) : null;
    // den nächsten Abschnitt still an seine Anfangsstelle setzen, solange der aktuelle noch läuft
    function prep(i) { const c = clips[i]; if (!c) return; const v = vidFor(c), t = c.in || 0; if (v !== cur() && Math.abs(v.currentTime - t) > .08) { try { v.currentTime = t; } catch (e) {} } }
    function locate(t) { let a = 0; for (let i = 0; i < clips.length; i++) { const L = len(clips[i]); if (t < a + L || i === clips.length - 1) return { i, off: Math.min(L, Math.max(0, t - a)), a }; a += L; } return { i: -1, off: 0, a: 0 }; }
    function show(i, off, seek) {
      if (i < 0 || !clips[i]) return; const c = clips[i], v = vidFor(c);
      vids.forEach(vv => { if (vv !== v) { vv.classList.remove('on'); if (!vv.paused) vv.pause(); } });
      v.classList.add('on'); v.muted = !!c.mute; v.style.filter = cmp ? '' : filterCss(st);
      if (seek !== false) { const t = (c.in || 0) + off; if (Math.abs(v.currentTime - t) > .08) v.currentTime = t; }
      v.style.transform = c.zoom ? 'scale(' + zAt(c, seek !== false ? off : Math.max(0, v.currentTime - (c.in || 0))).toFixed(4) + ')' : '';
    }
    function tick() {
      if (!playing) return;
      const c = clips[sel]; const v = cur(); if (!c || !v) { stop(); return; }
      const off = Math.max(0, v.currentTime - (c.in || 0)); pos = starts()[sel] + off; timeUi(); musDrive(pos, true);
      if (c.zoom) v.style.transform = 'scale(' + zAt(c, off).toFixed(4) + ')';
      if (!prepped && (c.out || c.dur) - v.currentTime < 1.2) { prepped = true; prep(sel + 1); }
      if (v.currentTime >= (c.out || c.dur) - .04 || v.ended) {
        if (sel < clips.length - 1) { sel++; prepped = false; const n = clips[sel], nv = vidFor(n); show(sel, 0); nv.play().catch(() => {}); renderClips(false); edUi(); ztDraw(); }
        else { stop(); pos = 0; sel = 0; show(0, 0); renderClips(false); edUi(); timeUi(); return; }
      }
      raf = requestAnimationFrame(tick);
    }
    const starts = () => { let a = 0; return clips.map(c => { const s = a; a += len(c); return s; }); };
    async function play() {
      if (busy || !clips.length) return; if (sel < 0) sel = 0; const c = clips[sel], v = vidFor(c); prepped = false; prep(sel + 1);
      if (v.currentTime < (c.in || 0) || v.currentTime >= (c.out || c.dur) - .05) show(sel, 0); else show(sel, 0, false);
      playing = true; big.hidden = true; K('play').textContent = '❚❚ Pause';
      try { await v.play(); } catch (e) { playing = false; big.hidden = false; K('play').textContent = '▶ Abspielen'; say('Abspielen klappt nicht. Bitte nochmal auf ▶ tippen.'); return; }
      musDrive(pos, true);
      cancelAnimationFrame(raf); raf = requestAnimationFrame(tick);
    }
    function stop() { playing = false; cancelAnimationFrame(raf); const v = cur(); if (v) v.pause(); musStop(); big.hidden = !clips.length; K('play').textContent = '▶ Abspielen'; }
    K('play').onclick = () => playing ? stop() : play(); big.onclick = play;
    K('cmp').onclick = () => { cmp = !cmp; K('cmp').textContent = cmp ? 'Nachher zeigen' : 'Vorher zeigen'; K('cmp').setAttribute('aria-pressed', String(cmp)); K('tag').textContent = cmp ? 'Vorher' : ''; const v = cur(); if (v) v.style.filter = cmp ? '' : filterCss(st); };
    function timeUi() { K('time').textContent = fmt(pos) + ' / ' + fmt(total()); if (clips.length) ztPh(); }
    const barUi = () => { if (clips.length) ztDraw(); };

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
    // Zeigt an, wenn ein anderer Clip denselben Bereich noch einmal zeigt (nach dem Ziehen über einen Nachbarn)
    function ovlUi() {
      const box = K('ovl'), c = clips[sel], list = c ? overlaps(sel) : [];
      if (!list.length) { box.className = 'sn-ovl'; box.innerHTML = ''; return; }
      box.className = 'sn-ovl on';
      box.innerHTML = list.map(o => '<div><b>Clip ' + (o.i + 1) + '</b> zeigt ' + sec1(o.ov) + ' davon noch einmal.'
        + '<div class="r"><button type="button" class="del" data-ovd="' + o.i + '">🗑 Clip ' + (o.i + 1) + ' löschen</button>'
        + (o.whole ? '' : '<button type="button" data-ovc="' + o.i + '">✂️ Clip ' + (o.i + 1) + ' kürzen</button>') + '</div></div>').join('');
      box.querySelectorAll('[data-ovd]').forEach(b => b.onclick = async () => {
        if (busy) return; const i = +b.dataset.ovd; await delAt(i); say('🗑 Clip ' + (i + 1) + ' gelöscht.', 'ok');
      });
      box.querySelectorAll('[data-ovc]').forEach(b => b.onclick = async () => {
        if (busy) return; const i = +b.dataset.ovc, x = clips[i], c2 = clips[sel]; if (!x || !c2) return; stop();
        const a = c2.in || 0, e = c2.out || c2.dur || 0;
        if ((x.in || 0) >= a) x.in = Math.min(e, (x.out || x.dur || 0) - .3); else x.out = Math.max(a, (x.in || 0) + .3);
        await dbPut(dbRow(x)); x.thumb = null; ui(); thumb(x); say('✂️ Clip ' + (i + 1) + ' gekürzt, jetzt ist nichts mehr doppelt.', 'ok');
      });
    }
    function edUi() {
      const c = clips[sel]; K('ed').hidden = !c; if (!c) { K('ovl').className = 'sn-ovl'; K('ovl').innerHTML = ''; return; }
      K('edT').textContent = 'Clip ' + (sel + 1) + ' von ' + clips.length + ' · ' + c.name;
      K('edD').textContent = 'von ' + fmt1(c.in || 0) + ' bis ' + fmt1(c.out || c.dur || 0) + ' · Länge ' + fmt1(len(c));
      ztDraw();
      K('mute').textContent = c.mute ? '🔊 Ton an' : '🔇 Ton aus'; K('left').disabled = sel === 0; K('right').disabled = sel === clips.length - 1;
      ovlUi();
    }
    function ui() { slots(); dropVids(); renderClips(); edUi(); timeUi(); schedCache = null; mlUi(); if (sel >= 0 && clips[sel]) { show(sel, 0, false); prep(sel + 1); } }
    async function change(c) { await dbPut(dbRow(c)); c.thumb = null; ui(); thumb(c); }
    K('setA').onclick = () => { const c = clips[sel], v = cur(); if (!c || !v) return; c.in = Math.min(v.currentTime, (c.out || c.dur) - .3); change(c); say('⟦ Anfang gesetzt bei ' + fmt1(c.in) + '.'); };
    K('setB').onclick = () => { const c = clips[sel], v = cur(); if (!c || !v) return; c.out = Math.max(v.currentTime, (c.in || 0) + .3); change(c); say('Ende gesetzt bei ' + fmt1(c.out) + ' ⟧.'); };
    K('resetCut').onclick = () => { const c = clips[sel]; if (!c) return; c.in = 0; c.out = c.dur; change(c); };
    K('split').onclick = async () => { const c = clips[sel], v = cur(); if (!c || !v) return; const t = v.currentTime; if (t <= (c.in || 0) + .3 || t >= (c.out || c.dur) - .3) { say('Zum Teilen die Stelle etwas weiter in die Mitte schieben.'); return; }
      const n = Object.assign({}, c, { id: uid(), in: t, added: c.added + 1, thumb: null, url: c.url }); c.out = t; clips.splice(sel + 1, 0, n); await dbPut(dbRow(c)); await dbPut(dbRow(n)); await saveOrder(); c.thumb = null; ui(); thumb(c); thumb(n); say('✂ Geteilt bei ' + fmt1(t) + '. Der zweite Teil ist jetzt Clip ' + (sel + 2) + '.', 'ok'); };
    K('left').onclick = async () => { if (sel <= 0) return; [clips[sel - 1], clips[sel]] = [clips[sel], clips[sel - 1]]; sel--; await saveOrder(); ui(); };
    K('right').onclick = async () => { if (sel >= clips.length - 1) return; [clips[sel + 1], clips[sel]] = [clips[sel], clips[sel + 1]]; sel++; await saveOrder(); ui(); };
    K('mute').onclick = () => { const c = clips[sel]; if (!c) return; c.mute = !c.mute; dbPut(dbRow(c)); ui(); };
    async function delAt(i) {
      const c = clips[i]; if (!c) return; stop();
      await dbDel(c.id); clips.splice(i, 1); slots(); dropVids();
      if (!clips.some(x => x.url === c.url)) URL.revokeObjectURL(c.url);
      if (i < sel) sel--; sel = Math.min(sel, clips.length - 1);
      await saveOrder(); pos = sel >= 0 ? starts()[sel] : 0; ui();
    }
    K('delClip').onclick = async () => { if (!clips[sel]) return; await delAt(sel); say('🗑 Clip gelöscht.'); };

    // ---- Zeitleiste über das ganze Video (Vivien, 30.10.2026: „Cut, Cut, Cut sehen und mit zwei Punkten ziehen“) ----
    // Jeder Clip ist ein Block, dazwischen die Schnittstellen. Die Griffe ⟦ ⟧ an den Rändern machen den Clip länger
    // oder kürzer (holen also weggeschnittenes Material zurück), solange im Ausgangsvideo noch etwas übrig ist.
    let Z = 1, ztDrag = null, pinch = null; const ZMAX = 80;
    const ztSc = K('ztsc'), ztIn = K('ztin');
    const basePps = () => Math.max(1, ztSc.clientWidth - 4) / Math.max(.4, total());
    const pps = () => basePps() * Z;
    // Wie weit lässt sich Clip i aufziehen? Immer bis ans Ende des Ausgangsvideos – auch über einen Nachbarn
    // hinaus (Vivien, 30.10.2026: „den Regler länger schieben und das Video danach löschen“).
    // Was sich dabei überschneidet, zeigt die Box darunter zum Löschen oder Kürzen an.
    function room(i) { const c = clips[i]; return [0, c.dur || 0]; }
    // Clips aus derselben Datei, die denselben Bereich noch einmal zeigen
    function overlaps(i) {
      const c = clips[i]; if (!c) return [];
      const a = c.in || 0, b = c.out || c.dur || 0, out = [];
      clips.forEach((x, k) => { if (k === i || x.url !== c.url) return;
        const xa = x.in || 0, xb = x.out || x.dur || 0, ov = Math.min(b, xb) - Math.max(a, xa);
        if (ov > .15) out.push({ i: k, ov, whole: xa >= a - .05 && xb <= b + .05, after: xa >= a }); });
      return out;
    }
    // Wie viel weggeschnittenes Material wird neben dem gewählten Clip gezeigt? (Fenster, damit die Leiste nicht endlos wird)
    let GW = null;
    function ghostWin() {
      const c = clips[sel]; if (!c) return null;
      const r = room(sel), p = pps(), cap = (Math.max(260, ztSc.clientWidth) * .3) / p;
      return { g0: Math.max(r[0], (c.in || 0) - cap), g1: Math.min(r[1], (c.out || c.dur || 0) + cap), lo: r[0], hi: r[1] };
    }
    const ztLim = i => (GW && i === sel) ? [GW.g0, GW.g1] : room(i);
    let LAY = { p: 1, head: 0, tail: 0, selS: 0, selE: 0 };
    // Zeit im Gesamtvideo -> Pixel (die Schatten-Stücke schieben alles dahinter nach rechts)
    const ZTX = t => t * LAY.p + (LAY.head && t >= LAY.selS - 1e-6 ? LAY.head : 0) + (LAY.tail && t >= LAY.selE - 1e-6 ? LAY.tail : 0);
    function ZTT(x) { // Pixel -> Zeit im Gesamtvideo
      const p = LAY.p; if (!LAY.head && !LAY.tail) return x / p;
      const a = LAY.selS * p; if (x < a) return x / p;
      if (x < a + LAY.head) return LAY.selS;
      const b = a + LAY.head + (LAY.selE - LAY.selS) * p; if (x < b) return LAY.selS + (x - a - LAY.head) / p;
      if (x < b + LAY.tail) return LAY.selE;
      return LAY.selE + (x - b - LAY.tail) / p;
    }
    function ztDraw() {
      const T = total(), p = pps(), S = starts(), c0 = clips[sel];
      if (!ztDrag || ztDrag.h === 'ph') GW = ghostWin();
      if (!c0) GW = null;
      const head = GW ? Math.max(0, ((c0.in || 0) - GW.g0)) * p : 0, tail = GW ? Math.max(0, (GW.g1 - (c0.out || c0.dur || 0))) * p : 0;
      LAY = { p, head, tail, selS: c0 ? S[sel] : 0, selE: c0 ? S[sel] + len(c0) : 0 };
      const W = Math.max(ztSc.clientWidth, Math.round(T * p + head + tail)); ztIn.style.width = W + 'px';
      const st2 = [.2, .5, 1, 2, 5, 10, 15, 30, 60, 120, 300, 600].find(x => x * p >= 64) || 900;
      let h = ''; for (let t = 0; t <= T + 1e-6; t += st2) h += '<div class="sn-zt-tk big" style="left:' + ZTX(t) + 'px"><span>' + (st2 < 1 ? fmt1(t) : fmt(t)) + '</span></div>';
      K('ztt').innerHTML = h;
      let b2 = '', x = 0;
      clips.forEach((c, i) => {
        const on = i === sel, w = Math.max(3, len(c) * p);
        if (on && head > 0) { const rest = (c.in || 0) - GW.lo;
          b2 += '<div class="sn-zt-g a" style="left:' + x + 'px;width:' + head + 'px" title="Hier wurde vorne etwas weggeschnitten – Griff ⟦ hierher ziehen">' + (head > 48 ? '<span>⟵ noch ' + sec1(rest) + '</span>' : '') + '</div>';
          x += head; }
        b2 += '<div class="sn-zt-bl' + (on ? ' on' : '') + (c.mute ? ' mu' : '') + '" data-bl="' + i + '" style="left:' + x + 'px;width:' + w + 'px">'
          + (w > 54 ? '<span class="lb">' + (i + 1) + ' · ' + esc(c.name) + '</span><span class="ln">' + fmt1(len(c)) + '</span>' : '') + '</div>';
        if (on) { const canA = (c.in || 0) - GW.lo > .05, canB = GW.hi - (c.out || c.dur || 0) > .05;
          b2 += '<div class="sn-zt-h a' + (canA ? '' : ' off') + '" data-h="a" data-i="' + i + '" style="left:' + x + 'px" title="Anfang ziehen – nach links wird der Clip länger">⟦</div>'
            + '<div class="sn-zt-h b' + (canB ? '' : ' off') + '" data-h="b" data-i="' + i + '" style="left:' + (x + w) + 'px" title="Ende ziehen – nach rechts wird der Clip länger">⟧</div>'; }
        x += w;
        if (on && tail > 0) { const rest = GW.hi - (c.out || c.dur || 0);
          b2 += '<div class="sn-zt-g b" style="left:' + x + 'px;width:' + tail + 'px" title="Hier wurde hinten etwas weggeschnitten – Griff ⟧ hierher ziehen">' + (tail > 48 ? '<span>noch ' + sec1(rest) + ' ⟶</span>' : '') + '</div>';
          x += tail; }
        // Schnittstelle: hier wurde etwas herausgeschnitten (gleiche Datei, Lücke) oder zwei Aufnahmen stoßen aneinander
        if (i < clips.length - 1) { const n2 = clips[i + 1];
          const gap = n2.url === c.url ? Math.max(0, (n2.in || 0) - (c.out || c.dur || 0)) : 0;
          b2 += '<div class="sn-zt-cut" style="left:' + x + 'px" title="' + (gap > .05 ? fmt1(gap) + ' herausgeschnitten' : 'Schnitt') + '">✂</div>'; }
      });
      K('ztb').innerHTML = b2;
      K('ztb').querySelectorAll('[data-bl]').forEach(el => el.onclick = e => { if (ztDrag) return; const i = +el.dataset.bl; if (i !== sel) { stop(); sel = i; pos = starts()[sel]; show(sel, 0); renderClips(false); edUi(); } });
      K('zoomR').value = Math.round(Math.log(Z) / Math.log(ZMAX) * 100); ztPh();
    }
    function ztPh() { K('ztph').style.left = ZTX(pos) + 'px'; K('ztime').textContent = fmt1(pos) + ' / ' + fmt1(total());
      if (!ztDrag) { const x = ZTX(pos), l = ztSc.scrollLeft, w = ztSc.clientWidth; if (x < l + 8 || x > l + w - 8) ztSc.scrollLeft = Math.max(0, x - w * (playing ? .3 : .5)); } }
    function setZ(z, anchorT) { // anchorT = Zeit, die beim Zoomen an Ort und Stelle bleibt
      z = Math.max(1, Math.min(ZMAX, z)); if (anchorT == null) anchorT = pos;
      const before = ZTX(anchorT) - ztSc.scrollLeft; Z = z; ztDraw(); ztSc.scrollLeft = Math.max(0, ZTX(anchorT) - before);
    }
    K('zoomIn').onclick = () => setZ(Z * 1.7); K('zoomOut').onclick = () => setZ(Z / 1.7);
    K('zoomR').oninput = () => setZ(Math.pow(ZMAX, +K('zoomR').value / 100));
    // Stelle im Gesamtvideo anspringen
    let seekRaf = 0, seekT = 0;
    function seekTo(t) {
      const T = total(); seekT = Math.max(0, Math.min(T, t)); const L = locate(seekT);
      if (L.i >= 0 && L.i !== sel) { sel = L.i; renderClips(false); edUi(); }
      pos = seekT; if (seekRaf) return;
      seekRaf = requestAnimationFrame(() => { seekRaf = 0; const L2 = locate(pos); if (L2.i >= 0) show(L2.i, L2.off); K('time').textContent = fmt(pos) + ' / ' + fmt(total()); ztPh(); });
    }
    const ptrs = new Map();
    ztIn.addEventListener('pointerdown', e => {
      if (busy || !clips.length) return; ptrs.set(e.pointerId, e.clientX);
      if (ptrs.size === 2) { const [x1, x2] = [...ptrs.values()], r = ztIn.getBoundingClientRect(); pinch = { d: Math.abs(x1 - x2), z: Z, t: ZTT((x1 + x2) / 2 - r.left) }; ztDrag = null; return; }
      const hEl = e.target.closest('[data-h]'); if (playing) stop();
      const r = ztIn.getBoundingClientRect(), x = e.clientX - r.left;
      if (hEl) { const i = +hEl.dataset.i, c = clips[i]; ztDrag = { h: hEl.dataset.h, i, x0: e.clientX, in0: c.in || 0, out0: c.out || c.dur || 0, id: e.pointerId, moved: false }; }
      else ztDrag = { h: 'ph', id: e.pointerId, moved: false };
      ztIn.setPointerCapture(e.pointerId); if (ztDrag.h === 'ph') seekTo(ZTT(x)); e.preventDefault();
    });
    ztIn.addEventListener('pointermove', e => {
      if (ptrs.has(e.pointerId)) ptrs.set(e.pointerId, e.clientX);
      if (pinch && ptrs.size === 2) { const [x1, x2] = [...ptrs.values()], d = Math.abs(x1 - x2); if (pinch.d > 12) setZ(pinch.z * d / pinch.d, pinch.t); return; }
      if (!ztDrag || ztDrag.id !== e.pointerId) return; ztDrag.moved = true;
      const r = ztIn.getBoundingClientRect(), p = pps();
      if (ztDrag.h === 'ph') { seekTo(ZTT(e.clientX - r.left)); }
      else {
        const c = clips[ztDrag.i], rm = ztLim(ztDrag.i), d = (e.clientX - ztDrag.x0) / p;
        if (ztDrag.h === 'a') { c.in = Math.max(rm[0], Math.min(ztDrag.in0 + d, (c.out || c.dur) - .3)); ztDraw(); seekTo(starts()[ztDrag.i] + .02); }
        else { c.out = Math.min(rm[1], Math.max(ztDrag.out0 + d, (c.in || 0) + .3)); ztDraw(); seekTo(starts()[ztDrag.i] + len(c) - .05); }
        K('edD').textContent = 'von ' + fmt1(c.in || 0) + ' bis ' + fmt1(c.out || c.dur || 0) + ' · Länge ' + fmt1(len(c));
      }
      const l = ztSc.scrollLeft; if (e.clientX > ztSc.getBoundingClientRect().right - 26) ztSc.scrollLeft = l + 10; else if (e.clientX < ztSc.getBoundingClientRect().left + 26) ztSc.scrollLeft = l - 10;
    });
    const ztUp = async e => {
      ptrs.delete(e.pointerId); if (pinch && ptrs.size < 2) pinch = null;
      if (!ztDrag || ztDrag.id !== e.pointerId) return; const d = ztDrag; ztDrag = null;
      if (d.h === 'ph' || !d.moved) return; const c = clips[d.i]; if (!c) return;
      await dbPut(dbRow(c)); c.thumb = null; renderClips(); edUi(); barUi(); ztDraw(); thumb(c);
      say(d.h === 'a' ? '⟦ Anfang von Clip ' + (d.i + 1) + ': ' + fmt1(c.in || 0) + (c.in < d.in0 ? ' (länger)' : ' (kürzer)') : 'Ende von Clip ' + (d.i + 1) + ': ' + fmt1(c.out) + (c.out > d.out0 ? ' (länger)' : ' (kürzer)') + ' ⟧');
    };
    ztIn.addEventListener('pointerup', ztUp); ztIn.addEventListener('pointercancel', ztUp);
    ztSc.addEventListener('wheel', e => { if (e.ctrlKey || e.metaKey) { e.preventDefault(); const r = ztIn.getBoundingClientRect(); setZ(Z * (e.deltaY < 0 ? 1.15 : 1 / 1.15), ZTT(e.clientX - r.left)); } }, { passive: false });
    addEventListener('resize', () => { if (clips.length) ztDraw(); });

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

    // ---- Musik (mehrere Lieder, automatisch auf die Länge zugeschnitten) ----
    // Vivien, 30.10.2026: „mehrere Lieder auswählen und das System schneidet es auf die Länge zu
    // und macht Ein- und Ausfaden, sodass es flüssig im Hintergrund läuft.“
    const auA = new Audio(), auB = new Audio(); [auA, auB].forEach(a => { a.preload = 'auto'; a.hidden = true; root.appendChild(a); });
    let lib = [], libTags = {}; const pre = new Audio(); let preId = 0;
    let schedCache = null, schedD = -1;
    // Ablaufplan: ein Lied nach dem anderen, je XF Sekunden Überblendung; reicht die Musik nicht, geht es von vorne los
    function sched() {
      const D = total();
      if (schedCache && Math.abs(schedD - D) < .05) return schedCache;
      schedD = D; const out = [];
      if (mlist.length && D > .2) { let t = 0, k = 0;
        while (t < D - .05 && out.length < 60) { const m = mlist[k % mlist.length], d = Math.max(1, m.dur || 30);
          out.push({ m, start: t, dur: d }); t += Math.max(1, d - xfOf(d)); k++; } }
      return (schedCache = out);
    }
    // Wie laut läuft welches Lied zur Zeit t (0…1, noch ohne Lautstärke-Regler)?
    function musPlan(t) {
      const D = schedD < 0 ? total() : schedD, out = [];
      sched().forEach((x, i) => { const end = Math.min(D, x.start + x.dur);
        if (t < x.start - .01 || t >= end) return;
        const xf = xfOf(x.dur), fi = x.start < .01 ? .4 : xf, fo = Math.min(xf, (end - x.start) / 2);
        let g = 1; if (t < x.start + fi) g = (t - x.start) / fi; else if (t > end - fo) g = (end - t) / Math.max(.1, fo);
        out.push({ i, x, g: Math.max(0, Math.min(1, g)), off: t - x.start }); });
      return out;
    }
    // Treibt die zwei Audio-Elemente für Vorschau und Echtzeit-Speichern
    function musDrive(t, run) {
      if (!mlist.length) return;
      const plan = musPlan(t), keep = new Set();
      plan.forEach(p => { const a = (p.i % 2) ? auB : auA; keep.add(a);
        if (a.__u !== p.x.m.url) { a.__u = p.x.m.url; a.src = p.x.m.url; }
        a.volume = Math.max(0, Math.min(1, p.g * mus.vol));
        if (run) { if (Math.abs(a.currentTime - p.off) > .4) { try { a.currentTime = p.off; } catch (e) {} } if (a.paused) a.play().catch(() => {}); }
      });
      [auA, auB].forEach(a => { if (!keep.has(a) && !a.paused) a.pause(); });
    }
    const musStop = () => [auA, auB].forEach(a => a.pause());
    function mlUi() {
      const box = K('mlist'), D = total();
      if (!mlist.length) { box.innerHTML = '<p class="sn-note">Noch keine Musik. Nimm unten ein oder mehrere Lieder – ich schneide sie auf die Länge deines Videos zu und blende sie ineinander.</p>'; return; }
      const ges = mlist.reduce((a, m) => a + (m.dur || 0), 0);
      box.innerHTML = '<ul>' + mlist.map((m, i) => '<li><b class="n">' + (i + 1) + '</b><span>' + esc(m.name) + '<small>' + (m.dur ? fmt(m.dur) : '') + '</small></span>'
        + (i ? '<button type="button" data-mup="' + i + '" aria-label="nach oben">↑</button>' : '')
        + '<button type="button" data-mx="' + i + '" aria-label="entfernen">✕</button></li>').join('') + '</ul>'
        + '<p class="sn-note">' + (mlist.length > 1 ? 'Die Lieder laufen in dieser Reihenfolge und blenden ineinander. ' : '')
        + (ges < D - .5 ? 'Zusammen ' + fmt(ges) + ' – dein Video ist ' + fmt(D) + ' lang, danach fange ich wieder vorne an.' : 'Reicht für dein ganzes Video (' + fmt(D) + ').') + '</p>';
      box.querySelectorAll('[data-mx]').forEach(b => b.onclick = () => { const i = +b.dataset.mx; URL.revokeObjectURL(mlist[i].url); mlist.splice(i, 1); schedCache = null; musStop(); mlUi(); libUi(); });
      box.querySelectorAll('[data-mup]').forEach(b => b.onclick = () => { const i = +b.dataset.mup; mlist.splice(i - 1, 0, mlist.splice(i, 1)[0]); schedCache = null; musStop(); mlUi(); });
    }
    const fileDur = url => new Promise(ok => { const a = new Audio(); a.preload = 'metadata'; a.onloadedmetadata = () => ok(a.duration || 0); a.onerror = () => ok(0); a.src = url; });
    async function addMusic(file, name, id) {
      const url = URL.createObjectURL(file), d = await fileDur(url);
      mlist.push({ file, url, name: name || file.name.replace(/\.\w+$/, ''), id: id || 0, dur: d });
      schedCache = null; mlUi(); libUi();
    }
    K('mfile').onchange = async e => { const fs = [...e.target.files]; e.target.value = ''; for (const f of fs) await addMusic(f); if (fs.length) say('🎵 ' + (fs.length > 1 ? fs.length + ' Lieder liegen' : '„' + fs[0].name.replace(/\.\w+$/, '') + '“ liegt') + ' jetzt darunter.', 'ok'); };
    K('duck').checked = mus.duck; K('fade').checked = mus.fade;
    K('duck').onchange = () => { mus.duck = K('duck').checked; ls.set('sn-mus', mus); }; K('fade').onchange = () => { mus.fade = K('fade').checked; ls.set('sn-mus', mus); };
    function libUi() {
      const box = K('lib'); if (!o.musicUrl) { box.innerHTML = ''; return; }
      if (!lib.length) { box.innerHTML = '<p class="sn-note">Noch keine Lieder im Musik-Ordner.</p>'; return; }
      const f2 = s2 => s2 > 0 ? fmt(s2) : '';
      const drin = id => mlist.some(m => m.id === id);
      box.innerHTML = '<b style="font-weight:500;font-size:17px;color:#4A3A41">Aus deiner Musik</b><ul>' + lib.map(x => '<li class="' + (drin(x.id) ? 'on' : '') + '"><button type="button" data-pv="' + x.id + '" aria-label="Reinhören">' + (preId === x.id ? '❚❚' : '▶') + '</button><span>' + esc(x.t) + '<small>' + f2(x.dur) + '</small></span><button type="button" class="pk" data-take="' + x.id + '">' + (drin(x.id) ? '✓ drin · nochmal' : '＋ Nehmen') + '</button></li>').join('') + '</ul>';
      box.querySelectorAll('[data-pv]').forEach(b => b.onclick = () => { const id = +b.dataset.pv; if (preId === id) { pre.pause(); preId = 0; } else { pre.src = o.musicUrl + id; pre.volume = .6; pre.play().catch(() => {}); preId = id; } libUi(); });
      box.querySelectorAll('[data-take]').forEach(b => b.onclick = async () => { const x = lib.find(y => y.id === +b.dataset.take); b.disabled = true; b.textContent = 'Lädt …'; try { const r = await fetch(o.musicUrl + x.id, { credentials: 'same-origin' }); const bl = await r.blob(); const f = new File([bl], x.t + '.mp3', { type: bl.type || 'audio/mpeg' }); pre.pause(); preId = 0; await addMusic(f, x.t, x.id); say('🎵 „' + x.t + '“ liegt jetzt darunter.', 'ok'); } catch (e) { say('Das Lied konnte nicht laden.', 'bad'); libUi(); } });
    }
    mlUi();
    if (o.musicUrl) fetch(o.musicUrl.replace(/&dl=$/, '&json=1'), { credentials: 'same-origin' }).then(r => r.json()).then(j => { lib = j.list || []; libTags = j.tags || {}; libUi(); }).catch(() => { K('lib').innerHTML = ''; });
    else libUi();

    // ---- ✨ Automatisch schneiden (Vivien, 30.10.2026) ----
    let undoRows = null, autoLog = [], autoP = null;
    let lastPause = null; // mit welcher Einstellung zuletzt geschnitten wurde
    // Pausen-Regler: Vivien stellt selbst ein, ab welcher Länge eine Pause rausfliegt (0,2 s bis 2,5 s)
    const pauseTxt = v => v < .4 ? 'Sehr streng: auch kurze Atempausen fallen weg. Wirkt flott, kann gehetzt klingen.'
      : v < .65 ? 'Streng: kleine Hänger und Atempausen fallen weg.'
      : v < .95 ? 'Ausgewogen: Denkpausen fallen weg, zum Atmen bleibt Luft.'
      : v < 1.4 ? 'Ruhig: nur deutliche Pausen fallen weg, dein Rhythmus bleibt.'
      : 'Sehr ruhig: nur lange Pausen fallen weg, sonst bleibt alles wie aufgenommen.';
    const ZOOMS = [[1, 1.06], [1.09, 1.04], [1.03, 1.08], [1.1, 1.05]]; // im Wechsel langsam hinein / heraus, leicht versetzt (kaschiert die Schnitte)
    function autoUi() {
      const v = Math.max(.2, Math.min(2.5, +auto.pause || .7));
      K('pauseR').value = Math.round(v * 100); pauseUi(v);
      K('stumbles').checked = auto.stumbles; K('zoomfx').checked = auto.zoom; K('noisefx').checked = auto.noise !== false;
    }
    function pauseUi(v) {
      K('pauseV').textContent = sec1(v); K('pauseH').textContent = pauseTxt(v);
      const go = K('pauseGo'), again = clips.some(c => c.auto) && lastPause != null && Math.abs(lastPause - v) > .001;
      go.hidden = !again; if (again) go.textContent = '✨ Mit ' + sec1(v) + ' nochmal schneiden';
    }
    K('pauseR').oninput = () => { auto.pause = +K('pauseR').value / 100; pauseUi(auto.pause); };
    K('pauseR').onchange = () => { auto.pause = +K('pauseR').value / 100; ls.set('sn-auto', auto); pauseUi(auto.pause); };
    K('pauseGo').onclick = async () => {
      if (busy) return;
      if (undoRows) { await K('undo').onclick(); }
      K('auto').onclick();
    };
    autoUi();
    K('stumbles').onchange = () => { auto.stumbles = K('stumbles').checked; ls.set('sn-auto', auto); };
    K('noisefx').onchange = () => { auto.noise = K('noisefx').checked; ls.set('sn-auto', auto); };
    K('zoomfx').onchange = async () => { auto.zoom = K('zoomfx').checked; ls.set('sn-auto', auto); if (clips.some(c => c.auto)) { assignZoom(clips, auto.zoom); for (const c of clips) await dbPut(dbRow(c)); ui(); say(auto.zoom ? '✓ Sanfter Zoom liegt wieder über allen Abschnitten.' : 'Zoom ist aus.'); } };
    function assignZoom(list, on) { list.forEach((c, i) => c.zoom = on ? ZOOMS[i % ZOOMS.length].slice() : null); }
    function loadAuto() { if (window.SchnittAuto) return Promise.resolve(window.SchnittAuto); if (!autoP) autoP = new Promise((ok, no) => { const sc = document.createElement('script'); sc.src = 'schnitt-auto.js?v=2'; sc.onload = () => window.SchnittAuto ? ok(window.SchnittAuto) : no(new Error('auto')); sc.onerror = () => { autoP = null; no(new Error('auto')); }; document.head.appendChild(sc); }); return autoP; }
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
    // Zweiter Weg (Safari ohne WebCodecs-Ton usw.): ganze Datei mit decodeAudioData, direkt als Mono 16 kHz (OfflineAudioContext mit 16000 Hz rechnet beim Dekodieren um)
    async function clipFramesDecode(SA, c, onProg) {
      const ab = await c.blob.arrayBuffer(); if (closed) return null;
      const ctx = new OfflineAudioContext(1, 16000, 16000);
      const buf = await new Promise((ok, no) => { const p = ctx.decodeAudioData(ab, ok, no); if (p && p.then) p.then(ok, no); });
      const a = c.in || 0, b = Math.min(buf.duration, c.out || c.dur || buf.duration), ch = buf.getChannelData(0);
      const s0 = Math.max(0, Math.round(a * buf.sampleRate)), s1 = Math.max(s0, Math.round(b * buf.sampleRate)); if (s1 - s0 < 1600) return null;
      const f = SA.framer(); const step = 16000 * 30; for (let i = s0; i < s1; i += step) { f.push(ch.subarray(i, Math.min(s1, i + step))); onProg && onProg((i - s0) / (s1 - s0)); await new Promise(r => setTimeout(r, 0)); if (closed) return null; }
      return f.done();
    }
    function autoResUi(sum) {
      const box = K('autoRes'); if (!sum) { box.hidden = true; return; } box.hidden = false;
      let h = `<b style="font-weight:500">✨ ${esc(sum.text)}</b>`;
      const WHY = { versprecher: 'neu angesetzt', geraeusch: 'kein Sprechen (z. B. in Position gehen)' };
      if (autoLog.length) h += `<div style="margin-top:6px">Diese Stellen habe ich herausgenommen. Hör rein und hol sie zurück, wenn ich mich geirrt habe:</div><ul>` + autoLog.map((e, i) => `<li><button type="button" class="sn-b sm" data-au="${i}">▶ Anhören</button><span>${esc(WHY[e.why] || 'Schnitt')}<small> · ${fmt1(e.s)} bis ${fmt1(e.e)} (${fmt1(e.e - e.s)})</small></span><button type="button" class="sn-b sm" data-keep="${i}">Doch behalten</button></li>`).join('') + '</ul>';
      box.innerHTML = h;
      box.querySelectorAll('[data-au]').forEach(b => b.onclick = () => audition(autoLog[+b.dataset.au]));
      box.querySelectorAll('[data-keep]').forEach(b => b.onclick = () => keepBack(+b.dataset.keep));
    }
    let audT = 0;
    async function audition(e) { // die rausgeschnittene Stelle kurz vorspielen
      if (!e || busy) return; stop();
      const c0 = clips.find(x => x.url === e.url); let v = c0 ? vidFor(c0) : null;
      if (!v) { v = document.createElement('video'); v.playsInline = true; v.setAttribute('playsinline', ''); v.src = e.url; pv.insertBefore(v, big); vids.set('tmp:' + e.url, v); }
      if (!v) return; vids.forEach(vv => { if (vv !== v) vv.classList.remove('on'); }); v.classList.add('on'); v.style.transform = ''; v.muted = false; v.currentTime = e.s; K('tag').textContent = 'Rausgeschnitten · Anhören';
      const my = ++audT; try { await v.play(); } catch (x) { return; }
      await new Promise(ok => { const st2 = () => { if (audT !== my || closed || v.paused && v.currentTime < e.e - .05) { ok(); return; } if (v.currentTime >= e.e - .03) { ok(); return; } requestAnimationFrame(st2); }; st2(); });
      if (audT !== my) return; v.pause(); K('tag').textContent = cmp ? 'Vorher' : ''; if (sel >= 0) show(sel, 0);
    }
    async function keepBack(i) {
      const e = autoLog[i]; if (!e) return; autoLog.splice(i, 1);
      const c = { id: uid(), blob: e.blob, url: e.url, name: e.name, added: Date.now(), in: e.s, out: e.e, mute: false, dur: e.dur, zoom: null, auto: true, draft: curDraft };
      let at = clips.findIndex(x => x.url === e.url && (x.in || 0) >= e.e); if (at < 0) { let last = -1; clips.forEach((x, k) => { if (x.url === e.url) last = k; }); at = last + 1; }
      clips.splice(at, 0, c); if (auto.zoom) assignZoom(clips, true); await dbPut(dbRow(c)); for (const x of clips) if (x.zoom) await dbPut(dbRow(x)); await saveOrder(); sel = at; pos = starts()[sel]; ui(); thumb(c);
      autoResUi(lastSum); say('✓ Die Stelle ist wieder drin (Clip ' + (at + 1) + ').', 'ok');
    }
    let lastSum = null;
    K('auto').onclick = async () => {
      if (busy || !clips.length) return; stop(); busy = true; root.classList.add('sn-busy'); K('auto').disabled = K('done').disabled = true;
      const bar = K('prog'), fill = bar.querySelector('i'); bar.hidden = false; fill.style.width = '0%'; say('Ich höre mir deine Clips an …');
      const before = total(), snap = { rows: clips.map(dbRow), order: clips.map(c => c.id) }; let lastErr = null;
      try {
        const SA = await loadAuto(); const M = await loadMB().catch(e => { console.warn('mediabunny', e); return null; });
        const next = [], log = []; let pn = 0, psec = 0, noAudio = 0;
        for (let i = 0; i < clips.length; i++) {
          const c = clips[i]; say('Ich höre mir Clip ' + (i + 1) + ' von ' + clips.length + ' an …');
          const prog = p => { fill.style.width = ((i + p) / clips.length * 100) + '%'; };
          let F = null; try { F = M ? await clipFrames(M, SA, c, prog) : null; } catch (e) { console.warn('auto audio (webcodecs)', e); F = null; }
          if (closed) return;
          if (!F) { try { F = await clipFramesDecode(SA, c, prog); } catch (e) { console.warn('auto audio (decode)', e); lastErr = e; F = null; } }
          if (closed) return;
          if (!F) { noAudio++; next.push(Object.assign({}, c, { auto: true })); continue; }
          const r = SA.analyze(F, 16000, { minPause: auto.pause, stumbles: auto.stumbles, noise: auto.noise !== false });
          if (!r.segs.length) { next.push(Object.assign({}, c, { auto: true })); continue; } // nur Stille erkannt → lieber so lassen
          pn += r.pauses.n; psec += r.pauses.sec; const a0 = c.in || 0;
          r.segs.forEach((sg, k) => next.push({ id: uid(), blob: c.blob, url: c.url, name: c.name, added: c.added + k, in: a0 + sg.s, out: a0 + sg.e, mute: !!c.mute, dur: c.dur, auto: true, zoom: null, draft: curDraft }));
          r.cut.forEach(sg => log.push({ url: c.url, blob: c.blob, name: c.name, dur: c.dur, s: a0 + sg.s, e: a0 + sg.e, why: sg.why }));
        }
        if (!next.length) { say('Da war nichts zu schneiden.'); return; }
        if (noAudio === clips.length) { say('Ich konnte den Ton nicht lesen' + (lastErr ? ' (' + String(lastErr.message || lastErr).slice(0, 100) + ')' : '') + '. Hat das Video Ton? Wenn ja, schick mir bitte diesen Text.', 'bad'); return; }
        // Übernehmen: alte Zeilen raus, neue rein
        for (const c of clips) await dbDel(c.id);
        clips = next; assignZoom(clips, auto.zoom); for (const c of clips) await dbPut(dbRow(c)); await saveOrder();
        undoRows = snap; autoLog = log; lastPause = auto.pause; sel = 0; pos = 0; ui(); clips.forEach(thumb);
        const after = total(), saved = before - after;
        const nV = log.filter(x => x.why === 'versprecher').length, nG = log.filter(x => x.why === 'geraeusch').length;
        const parts = []; if (pn) parts.push(pn + (pn === 1 ? ' Pause' : ' Pausen') + ' rausgeschnitten');
        if (nV) parts.push(nV + (nV === 1 ? ' Stelle' : ' Stellen') + ', wo du neu angesetzt hast');
        if (nG) parts.push(nG + (nG === 1 ? ' Stelle ohne Sprechen' : ' Stellen ohne Sprechen') + ' (Vorbereiten, Rascheln)');
        if (auto.zoom) parts.push('sanfter Zoom liegt über ' + clips.length + ' Abschnitten');
        lastSum = { text: (parts.length ? parts.join(', ') : 'nichts zu kürzen gefunden') + '. Vorher ' + fmt(before) + ', jetzt ' + fmt(after) + (saved > .5 ? ' (−' + fmt(saved) + ')' : '') + '.' + (noAudio ? ' ' + noAudio + (noAudio === 1 ? ' Clip hat' : ' Clips haben') + ' keinen Ton und blieb' + (noAudio === 1 ? '' : 'en') + ' wie ' + (noAudio === 1 ? 'er ist' : 'sie sind') + '.' : '') };
        autoResUi(lastSum); K('undo').hidden = false;
        say('✓ Fertig geschnitten. Mit ▶ anschauen – jeder Abschnitt ist jetzt ein eigener Clip, du kannst alles noch anpassen.', 'ok');
      } catch (e) { console.warn('auto', e); lastErr = e; say('Das automatische Schneiden hat nicht geklappt (' + String((e && e.message) || e).slice(0, 120) + '). Bitte nochmal versuchen, am besten in Safari oder Chrome. Wenn es wieder nicht geht, schick mir bitte diesen Text.', 'bad'); }
      finally { busy = false; root.classList.remove('sn-busy'); K('auto').disabled = false; K('done').disabled = !clips.length; bar.hidden = true; }
    };
    K('undo').onclick = async () => {
      if (busy || !undoRows) return; stop();
      const urls = new Map(clips.map(c => [c.blob, c.url]));
      for (const c of clips) await dbDel(c.id);
      clips = undoRows.rows.map(r => { let u = urls.get(r.blob); if (!u) u = URL.createObjectURL(r.blob); return Object.assign({}, r, { url: u }); });
      for (const c of clips) await dbPut(dbRow(c)); await kvSet('order', undoRows.order).catch(() => {});
      undoRows = null; autoLog = []; lastSum = null; lastPause = null; K('undo').hidden = true; autoResUi(null);
      sel = 0; pos = 0; ui(); clips.forEach(thumb); say('↩ Zurück auf den Stand vor dem automatischen Schnitt.');
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
      if (!bufs.length && !mlist.length) return null;
      const place = (ctx, dst) => { const gains = new Map(); bufs.forEach(b => { let g = gains.get(b.clipStart); if (!g) { // je Clip 20 ms ein-/ausblenden, damit die Schnitte nicht knacken
          g = new GainNode(ctx, { gain: 0 }); g.connect(dst); const f = Math.min(.02, (b.clipEnd - b.clipStart) / 4); g.gain.setValueAtTime(0, b.clipStart); g.gain.linearRampToValueAtTime(1, b.clipStart + f); g.gain.setValueAtTime(1, Math.max(b.clipStart + f, b.clipEnd - f)); g.gain.linearRampToValueAtTime(0, b.clipEnd); gains.set(b.clipStart, g); }
        const s0 = new AudioBufferSourceNode(ctx, { buffer: b.buffer }); s0.connect(g); const off = b.at < 0 ? -b.at : 0, when = Math.max(0, b.at), dur = Math.min(b.buffer.duration - off, b.clipEnd - when); if (dur > 0) s0.start(when, off, dur); }); };
      let rms = null;
      if (mlist.length && mus.duck && bufs.length) { const c1 = new OfflineAudioContext(1, N, SR); place(c1, c1.destination); const r1 = (await c1.startRendering()).getChannelData(0); const win = Math.round(SR * .05); rms = []; for (let i = 0; i < r1.length; i += win) { let s = 0, n = Math.min(win, r1.length - i); for (let j = 0; j < n; j++) s += r1[i + j] * r1[i + j]; rms.push(Math.sqrt(s / Math.max(1, n))); } }
      const c2 = new OfflineAudioContext(2, N, SR), vg = new GainNode(c2, { gain: mus.voice }); vg.connect(c2.destination); place(c2, vg);
      if (mlist.length) {
        // Alle gewählten Lieder einmal entschlüsseln, dann der Reihe nach legen und ineinander blenden
        const dec = new Map();
        for (const m of mlist) { if (dec.has(m.url)) continue;
          const b2 = await new Promise((ok, no) => m.file.arrayBuffer().then(ab => { const tmp = new OfflineAudioContext(2, SR, SR); tmp.decodeAudioData(ab, ok, no); }, no)).catch(() => null);
          if (b2) dec.set(m.url, b2); }
        const master = new GainNode(c2, { gain: 0 }); master.connect(c2.destination);
        let any = false;
        for (const x of sched()) { const mb = dec.get(x.m.url); if (!mb) continue; any = true;
          const xf = xfOf(x.dur), end = Math.min(D, x.start + Math.min(x.dur, mb.duration)), fi = x.start < .01 ? .4 : xf, fo = Math.min(xf, (end - x.start) / 2);
          if (end - x.start < .3) continue;
          const src = new AudioBufferSourceNode(c2, { buffer: mb }), g = new GainNode(c2, { gain: 0 });
          src.connect(g); g.connect(master);
          g.gain.setValueAtTime(0, x.start); g.gain.linearRampToValueAtTime(1, x.start + fi);
          g.gain.setValueAtTime(1, Math.max(x.start + fi, end - fo)); g.gain.linearRampToValueAtTime(0, end);
          src.start(x.start, 0, end - x.start + .05); }
        if (any) { let duckT = -9, prev = 0; for (let k = 0, t = 0; t < D; k++, t = k * .05) { let g = mus.vol; if (mus.fade) g *= Math.max(0, Math.min(1, t / 1.5, (D - t) / 2.5)); if (rms && (rms[k] || 0) > .025) duckT = t; if (rms && t - duckT < .6) g *= .35; if (Math.abs(g - prev) > .001 || k === 0) { master.gain.setTargetAtTime(g, t, g < prev ? .12 : .45); prev = g; } } } }
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
      if (!window.MediaRecorder) return null; const v0 = vidFor(clips[0]); if (!v0 || !v0.videoWidth) return null;
      const [W, H] = outSize(v0.videoWidth, v0.videoHeight), cv = document.createElement('canvas'); cv.width = W; cv.height = H; const draw = makeGL(cv); if (!draw) return null;
      const ac = new (window.AudioContext || window.webkitAudioContext)(); await ac.resume().catch(() => {}); const dest = ac.createMediaStreamDestination(), vg = ac.createGain(); vg.gain.value = mus.voice; vg.connect(dest);
      const srcs = new Map(); let mg = null, msrc = null;
      if (mlist.length) { try { mg = ac.createGain(); mg.gain.value = 0; mg.connect(dest); [auA, auB].forEach(a => { const sx = ac.createMediaElementSource(a); sx.connect(mg); }); } catch (e) { mg = null; } }
      const stream = cv.captureStream(30); dest.stream.getAudioTracks().forEach(t => stream.addTrack(t));
      const mt = mime(), ch = [], r = new MediaRecorder(stream, Object.assign(mt ? { mimeType: mt } : {}, { videoBitsPerSecond: brate(W, H), audioBitsPerSecond: 160000 })); r.ondataavailable = e => { if (e.data && e.data.size) ch.push(e.data); }; const done = new Promise(ok => r.onstop = ok);
      const D = total(); say('Wird fertig gestellt … dauert so lang wie das Video (' + fmt(D) + '). Seite offen lassen.');
      r.start(500); let t0 = 0; const startAt = performance.now();
      if (mg) musDrive(0, true);
      for (let i = 0; i < clips.length && !closed; i++) {
        const c = clips[i], v = vidFor(c); v.classList.remove('on'); v.style.transform = ''; v.muted = true; v.currentTime = c.in || 0; await new Promise(ok => { v.onseeked = ok; setTimeout(ok, 1500); });
        if (!c.mute && !srcs.has(c.slot)) { try { const s = ac.createMediaElementSource(v); s.connect(vg); srcs.set(c.slot, s); } catch (e) {} }
        if (c.mute) v.muted = true; else v.muted = false; v.volume = 1;
        await v.play().catch(() => {});
        await new Promise(ok => { const step = () => { if (closed) { ok(); return; } draw(v, v.videoWidth, v.videoHeight, zAt(c, v.currentTime - (c.in || 0))); const t = t0 + (v.currentTime - (c.in || 0));
          if (mg) { musDrive(t, true); const g = mus.fade ? Math.max(0, Math.min(1, t / 1.5, (D - t) / 2.5)) : 1; mg.gain.setTargetAtTime(g, ac.currentTime, .2); }
          fill.style.width = Math.min(100, t / D * 100) + '%'; if (v.ended || v.currentTime >= (c.out || c.dur) - .03) { ok(); return; } (v.requestVideoFrameCallback ? v.requestVideoFrameCallback(step) : requestAnimationFrame(step)); }; step(); v.onended = ok; });
        v.pause(); t0 += len(c);
      }
      await new Promise(x => setTimeout(x, 150)); r.stop(); await done; musStop(); stream.getVideoTracks().forEach(t => t.stop()); try { ac.close(); } catch (e) {}
      clips.forEach(c => { const v = vids.get(c.slot); if (v) v.muted = !!c.mute; });
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
      K('resN').textContent = fmt(total()) + ' · ' + clips.length + (clips.length > 1 ? ' Clips' : ' Clip') + (mlist.length ? ' · mit Musik' : '') + (fast ? '' : ' · in Echtzeit gespeichert');
      say(canSh ? '✓ Fertig. Schau es dir an, dann „In Fotos sichern“ (landet direkt in deiner Galerie) oder im Studio hochladen.' : '✓ Fertig. Schau es dir an, dann herunterladen oder im Studio hochladen.', 'ok'); K('res').scrollIntoView({ behavior: 'smooth', block: 'start' });
      try { await kvSet('result', { blob: nb, at: Date.now(), draft: drName(curDraft) }); } catch (e) {}
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
  // Entwurf (nach Name) zum offenen machen – das Studio ruft das vor dem Wechsel in den Schnitt auf
  async function setDraft(name) { const id = await draftFor(name); await kvSet('cur', id); return id; }
  window.Schnitt = { open, addTake, takeResult, draftFor, setDraft, drafts };
})();

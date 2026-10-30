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
  const DB = 'mv-schnitt', VER = 2;
  // Videodateien liegen seit 30.10.2026 einmal je Datei im Speicher „files“, die Clips verweisen nur darauf (fid).
  // Vorher steckte die ganze Datei in jedem Clip: jeder Schnitt, jedes Löschen, jede Änderung schrieb das
  // ganze Video (oft mehrere GB) neu auf die Festplatte – deshalb stand der Editor nach „Hier schneiden“ still.
  let dbP = null;
  function idb() { if (!dbP) dbP = new Promise((ok, no) => { const r = indexedDB.open(DB, VER); r.onupgradeneeded = () => { const d = r.result; if (!d.objectStoreNames.contains('clips')) d.createObjectStore('clips', { keyPath: 'id' }); if (!d.objectStoreNames.contains('kv')) d.createObjectStore('kv'); if (!d.objectStoreNames.contains('files')) d.createObjectStore('files'); }; r.onsuccess = () => { const d = r.result; d.onversionchange = () => { d.close(); dbP = null; }; ok(d); }; r.onerror = () => { dbP = null; no(r.error); }; r.onblocked = () => {}; }); return dbP; }
  const fidOf = new WeakMap(); // Blob → schon gespeicherte Datei
  const req = r => new Promise((ok, no) => { r.onsuccess = () => ok(r.result); r.onerror = () => no(r.error); });
  async function dbAll() {
    const d = await idb(), t = d.transaction(['clips', 'files']), rows = (await req(t.objectStore('clips').getAll())) || [];
    const need = new Set(rows.filter(r => r.fid && !r.blob).map(r => r.fid)), files = new Map();
    if (need.size) { const st = t.objectStore('files'); await Promise.all([...need].map(async f => { const b = await req(st.get(f)); if (b) { files.set(f, b); fidOf.set(b, f); } })); }
    rows.forEach(r => { if (r.fid && !r.blob) r.blob = files.get(r.fid) || null; });
    return rows;
  }
  async function dbPut(c) {
    const d = await idb(), row = Object.assign({}, c); let newFile = null;
    if (row.blob) { let f = fidOf.get(row.blob); if (!f) { f = 'f' + uid(); newFile = [f, row.blob]; } row.fid = f; }
    delete row.blob; delete row.url; delete row.thumb; delete row.slot;
    return new Promise((ok, no) => { const t = d.transaction(['clips', 'files'], 'readwrite'); if (newFile) t.objectStore('files').put(newFile[1], newFile[0]); t.objectStore('clips').put(row);
      t.oncomplete = () => { if (newFile) fidOf.set(newFile[1], newFile[0]); ok(); }; t.onerror = () => no(t.error); });
  }
  async function dbDel(id) {
    const d = await idb(); const t0 = d.transaction('clips'), row = await req(t0.objectStore('clips').get(id)).catch(() => null);
    await new Promise((ok, no) => { const t = d.transaction('clips', 'readwrite'); t.objectStore('clips').delete(id); t.oncomplete = ok; t.onerror = () => no(t.error); });
    const f = row && row.fid; if (!f) return;
    const rest = (await req(d.transaction('clips').objectStore('clips').getAll())) || [];
    if (!rest.some(r => r.fid === f)) await new Promise(ok => { const t = d.transaction('files', 'readwrite'); t.objectStore('files').delete(f); t.oncomplete = ok; t.onerror = ok; });
  }
  async function kvGet(k) { const d = await idb(); return new Promise((ok, no) => { const r = d.transaction('kv').objectStore('kv').get(k); r.onsuccess = () => ok(r.result); r.onerror = () => no(r.error); }); }
  async function kvSet(k, v) { const d = await idb(); return new Promise((ok, no) => { const t = d.transaction('kv', 'readwrite'); t.objectStore('kv').put(v, k); t.oncomplete = ok; t.onerror = () => no(t.error); }); }
  async function kvDel(k) { const d = await idb(); return new Promise((ok, no) => { const t = d.transaction('kv', 'readwrite'); t.objectStore('kv').delete(k); t.oncomplete = ok; t.onerror = () => no(t.error); }); }
  // Kleine Rückfrage im Movecia-Stil statt der großen Browser-Fenster (Vivien, 30.10.2026: „riesige Pop-ups will ich nicht“)
  function ask(text) { return new Promise(ok => { const w = document.createElement('div');
    w.style.cssText = 'position:fixed;left:50%;bottom:22px;transform:translateX(-50%);z-index:99999;width:min(380px,calc(100% - 32px));background:#fff;border:1.5px solid #E6DCD2;border-radius:18px;box-shadow:0 18px 40px -18px rgba(46,37,41,.45);padding:14px 16px;font:15.5px/1.45 "Hanken Grotesk",system-ui,sans-serif;color:#2E2529';
    w.innerHTML = '<div></div><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button type="button" data-n style="font:inherit;padding:8px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer">Abbrechen</button><button type="button" data-y style="font:inherit;padding:8px 16px;border-radius:999px;border:0;background:#7D5E68;color:#fff;cursor:pointer">Ja</button></div>';
    w.firstChild.textContent = text; document.body.appendChild(w); const done = v => { w.remove(); ok(v); };
    w.querySelector('[data-y]').onclick = () => done(true); w.querySelector('[data-n]').onclick = () => done(false); w.querySelector('[data-y]').focus(); }); }
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
  async function draftFor(name, renew) { // renew: war der Entwurf schon hochgeladen, einen frischen mit gleichem Namen anlegen
    const n = String(name || '').trim(); if (!n) return ensureDraft();
    const l = await drafts(); const hit = l.find(d => (d.name || '').toLowerCase() === n.toLowerCase());
    if (hit && !(renew && hit.up)) return hit.id;
    const d = { id: uid(), name: n, at: Date.now() }; l.unshift(d); await draftsSave(l); return d.id;
  }
  // Ein Take aus dem Studio in den Schnitt legen (bleibt auf dem Gerät)
  async function addTake(blob, name, opt) {
    opt = opt || {};
    const did = opt.draft ? await draftFor(opt.draft, opt.renew) : await ensureDraft();
    const c = { id: uid(), blob, name: name || 'Aufnahme', added: Date.now(), in: 0, out: 0, mute: false, draft: did, rec: 1 }; c.src = c.id;
    await dbPut(c); const order = (await kvGet(ordKey(did))) || []; order.push(c.id); await kvSet(ordKey(did), order);
    // target = wohin das fertige Video kommt (Lektion + Sprache, Übungs-Clip oder Instagram-Tag), damit „Fertig stellen“ gleich hochlädt
    const l = await drafts(); const d = l.find(x => x.id === did); if (d) { d.at = Date.now(); if (opt.target) { d.tg = opt.target; d.up = null; } await draftsSave(l); }
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
.sn-pv video.sn-ov{z-index:1}
.sn-trash{margin-top:12px;background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:18px;padding:10px 14px}
.sn-trash summary{cursor:pointer;font-size:17px;color:#4A3A41;font-weight:500}
.sn-trash li{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:8px 0;border-top:1px solid #EFE7DE;font-size:15.5px;color:#4A3A41}
.sn-trash li span{flex:1;min-width:160px}.sn-trash li small{display:block;color:#7D7076;font-size:13.5px}
.sn-trash ul{list-style:none;margin:6px 0 0;padding:0}
.sn-pv .sn-glc{position:absolute;inset:0;width:100%;height:100%;z-index:2;display:none;pointer-events:none}
.sn-bro{margin-top:10px;display:flex;flex-direction:column;gap:10px}
.sn-bro:empty{display:none}
.sn-bo{background:#fff;border:1.5px solid #E6DCD2;border-radius:16px;padding:10px 12px;font-size:15.5px;color:#4A3A41}
.sn-bo .t{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap}.sn-bo .t b{font-weight:500}.sn-bo .t small{color:#7D7076}
.sn-bo label{display:block;margin-top:8px;font-size:14.5px;color:#6B5658}.sn-bo input[type=range]{width:100%;accent-color:#7D5E68}
.sn-bo .r{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.sn-c .br{position:absolute;left:6px;bottom:44px;background:rgba(125,94,104,.88);color:#fff;border-radius:999px;font-size:12px;padding:1px 7px}
.sn-hint{font-size:14.5px;color:#7D7076;margin:8px 2px 0;line-height:1.45}
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
.sn-c.drag{opacity:.35}
.sn-ghost{position:fixed;z-index:9999;width:120px;border-radius:14px;overflow:hidden;pointer-events:none;box-shadow:0 14px 28px -10px rgba(46,37,41,.55);transform:rotate(-3deg) scale(1.04);background:#fff;border:2px solid #7D5E68}
.sn-ghost img{width:100%;display:block}.sn-ghost b{display:block;padding:4px 8px;font-size:13px;font-weight:500;color:#4A3A41;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sn-drop{flex:0 0 4px;align-self:stretch;border-radius:4px;background:#7D5E68;margin:0 -7px}
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
.sn-zt-bar .cut{font:inherit;font-size:16px;height:38px;padding:0 14px;border-radius:999px;border:1.5px solid #7D5E68;background:#7D5E68;color:#fff;cursor:pointer;white-space:nowrap}
.sn-zt-bar .cut.del{background:#fff;color:#9A3B3B;border-color:#E2C3C3}
.sn-zt-bar .cut:disabled{opacity:.45;cursor:default}
.sn-zt-bar .t{font-size:16px;color:#4A3A41;font-variant-numeric:tabular-nums;margin-left:auto}
.sn-zt-bar input[type=range]{flex:1;min-width:90px;max-width:220px;accent-color:#7D5E68}
.sn-zt-bar .zb{font:inherit;font-size:18px;width:36px;height:36px;border-radius:50%;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer}
.sn-sug{margin-top:8px}
.sn-sug .hd{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px}
.sn-sug .hd b{font-weight:500;font-size:17px;color:#4A3040;flex:1;min-width:140px}
.sn-sug .hd button{font:inherit;font-size:14.5px;padding:6px 10px;border-radius:11px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3040;cursor:pointer}
.sn-sug ol{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:5px;counter-reset:sg}
.sn-sug li{display:flex;align-items:center;gap:8px;background:#F6F1EA;border:1.5px solid #E6DCD2;border-radius:12px;padding:6px 8px}
.sn-sug li.on{background:#EFE3E6;border-color:#C7B0B9}
.sn-sug li span{flex:1;min-width:0;font-size:15.5px;color:#4A3040;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sn-sug li span small{color:#7D7076;margin-left:6px}
.sn-sug li button{font:inherit;font-size:15px;min-width:34px;height:34px;flex:none;border-radius:17px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3040;cursor:pointer;padding:0 10px}
.sn-sug .take{width:100%;margin-top:8px;font:inherit;font-size:17px;padding:12px;border-radius:14px;border:0;background:#4A3040;color:#fff;cursor:pointer}
.sn-all{margin-top:10px;border-top:1.5px solid #EFE7DE;padding-top:8px}
.sn-all>summary{cursor:pointer;font-size:16px;color:#4A3040;list-style:none}
.sn-all>summary::-webkit-details-marker{display:none}
.sn-all>summary::before{content:"▸ ";color:#9A8C93}
.sn-all[open]>summary::before{content:"▾ "}
.sn-all input[type=search]{width:100%;margin:8px 0;font:inherit;font-size:16px;padding:9px 11px;border-radius:12px;border:1.5px solid #DDD2C6;background:#fff;color:#2A2026}
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
.sn-fr{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:18px;padding:12px 14px;margin:10px 0 4px}
.sn-fr[hidden]{display:none}
.sn-fr .h{display:flex;flex-direction:column;gap:2px;margin-bottom:4px}.sn-fr .h b{font-weight:500;font-size:17px;color:#4A3A41}.sn-fr .h small{font-size:14px;color:#7D7076}
.sn-fr label{display:block;margin-top:8px;font-size:15.5px;color:#4A3A41}.sn-fr label span{display:flex;justify-content:space-between}.sn-fr label i{font-style:normal;color:#7D7076}
.sn-fr input[type=range]{width:100%;accent-color:#7D5E68}
.sn-pv.frmode video.on{cursor:grab;touch-action:none}
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
/* Wohin kommt das Video? (Vivien, 30.10.2026: „hochladen und es soll direkt drin sein“) */
.sn-tg{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;background:#FBF5F7;border:1.5px solid #E2CBD2;border-radius:18px;padding:10px 14px;margin:0 0 10px;font-size:17px;color:#4A3A41}
.sn-tg.on{background:#F4F7F1;border-color:#CFE0CE}
.sn-tg>span{flex:1;min-width:200px;line-height:1.4}.sn-tg>span b{font-weight:500}.sn-tg small{display:block;font-size:14.5px;color:#7D7076}
.sn-tg select{flex:1 1 100%;font:inherit;font-size:16px;padding:10px 12px;border:1.5px solid #DDD2C6;border-radius:12px;background:#fff;color:#2E2529;max-width:100%}
.sn-tg select[hidden]{display:none}
/* Titelbild ganz am Anfang (Vivien, 30.10.2026) */
.sn-covimg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:3;display:none;background:#1E171B}.sn-covimg.on{display:block}
.sn-c.cov{background:#FBF5F7;border-style:solid}.sn-c.cov .n{background:#7D5E68}
.sn-c.cov.off img{opacity:.35;filter:grayscale(.6)}
.sn-add.cov{border-color:#B98A9A;background:#FBF5F7}
.sn-cov{background:#FBF5F7;border:1.5px solid #E2CBD2;border-radius:18px;padding:12px 14px;margin:0 0 10px;display:flex;flex-direction:column;gap:10px}
.sn-cov[hidden]{display:none}
.sn-cov .h{display:flex;justify-content:space-between;align-items:center;gap:8px}.sn-cov .h b{font-weight:500;font-size:18px;color:#4A3A41}
.sn-cov .ths{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.sn-cov .th{position:relative;width:76px;height:76px;border-radius:14px;overflow:hidden;border:2.5px solid #E6DCD2;cursor:pointer;padding:0;background:#EEE5E1}
.sn-cov .th.on{border-color:#7D5E68;box-shadow:0 0 0 2px #7D5E68}
.sn-cov .th img{width:100%;height:100%;object-fit:cover;display:block}
.sn-cov .th .x{position:absolute;top:3px;right:3px;width:24px;height:24px;border-radius:50%;border:0;background:rgba(46,37,41,.72);color:#fff;font-size:13px;line-height:24px;cursor:pointer;padding:0}
.sn-cov .new{width:76px;height:76px;border-radius:14px;border:2px dashed #C9B0B8;background:#fff;color:#7D5E68;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:13px;cursor:pointer;text-align:center;line-height:1.2}
.sn-cov .new b{font-size:24px;font-weight:400}
/* Hochladen */
.sn-up{border-radius:18px;padding:12px 14px;margin:10px 0 0;font-size:17px;line-height:1.45;background:#F4F7F1;border:1.5px solid #CFE0CE;color:#2F4A2E}
.sn-up[hidden]{display:none}.sn-up b{display:block;font-weight:500;font-size:20px;margin-bottom:2px}
.sn-up.run{background:#FBF8F4;border-color:#E6DCD2;color:#4A3A41}.sn-up.bad{background:#FBF1EF;border-color:#E8C9C2;color:#7A3A2E}
.sn-up .sn-row{margin-top:10px}
.sn-dr-list li small.up{color:#3F6B3E}
`;
  function css() { if (document.getElementById('sn-css')) return; const s = document.createElement('style'); s.id = 'sn-css'; s.textContent = CSS; document.head.appendChild(s); }

  // CSS-Filter genau wie im Browser (W3C Filter Effects), damit Vorschau und Export gleich aussehen
  const VS = 'attribute vec2 p;varying vec2 uv;void main(){uv=p*.5+.5;gl_Position=vec4(p,0.,1.);}';
  const FS = `precision highp float;varying vec2 uv;uniform sampler2D t;uniform vec2 so,oo,fo;uniform float br,ct,sa,se,zm,fz,fr,fe,cin,lb,bb;uniform sampler2D mk;
void main(){
 float ra=so.x/so.y, ro=oo.x/oo.y;
 vec2 p=uv-.5;
 if(fe>0.){vec2 pa=vec2(p.x*ro,p.y); float rr=dot(pa,pa)/(.25*(ro*ro+1.)); p*=1.-fe*.42*(1.-rr);} // Fischauge: Mitte gewölbt
 p.x*=ro; float cs=cos(fr), sn=sin(fr); p=vec2(cs*p.x-sn*p.y, sn*p.x+cs*p.y); p.x/=ro; vec2 q=.5+p/fz+fo; // eigener Ausschnitt: gerade richten, zoomen, verschieben
 if(ra>ro){float k=ro/ra;q.x=.5+(q.x-.5)*k;}else{float k=ra/ro;q.y=.5+(q.y-.5)*k;} // Mitte ausschneiden (object-fit: cover)
 q=.5+(q-.5)/zm; // sanfter Zoom je Abschnitt
 vec2 qq=clamp(q,vec2(.0005),vec2(.9995)); vec3 c=texture2D(t,qq).rgb;
 if(bb>0.){float m=smoothstep(.25,.75,texture2D(mk,qq).r); if(m<.999){vec3 s=c; float n=1.; vec2 rr=vec2(bb*.03/max(ra,1.),bb*.03*min(ra,1.));
   for(int i=1;i<=24;i++){float fi=float(i); float a=fi*2.39996; vec2 o=vec2(cos(a),sin(a))*sqrt(fi/24.)*rr; s+=texture2D(t,clamp(qq+o,vec2(.0005),vec2(.9995))).rgb; n+=1.;}
   c=mix(s/n,c,m);}} // Hintergrund weich (Person bleibt scharf)
 c*=br; c=(c-.5)*ct+.5; c=clamp(c,0.,1.);
 float r=c.r,g=c.g,b=c.b;
 c=vec3((.213+.787*sa)*r+(.715-.715*sa)*g+(.072-.072*sa)*b,(.213-.213*sa)*r+(.715+.285*sa)*g+(.072-.072*sa)*b,(.213-.213*sa)*r+(.715-.715*sa)*g+(.072+.928*sa)*b);
 r=c.r;g=c.g;b=c.b;
 c=vec3((.393+.607*(1.-se))*r+(.769-.769*(1.-se))*g+(.189-.189*(1.-se))*b,(.349-.349*(1.-se))*r+(.686+.314*(1.-se))*g+(.168-.168*(1.-se))*b,(.272-.272*(1.-se))*r+(.534-.534*(1.-se))*g+(.131+.869*(1.-se))*b);
 if(cin>0.){float l=dot(c,vec3(.299,.587,.114)); c+=cin*(vec3(.07,.015,-.06)*smoothstep(.35,1.,l)+vec3(-.06,.02,.07)*(1.-smoothstep(0.,.55,l))); c=mix(c,c*.93+.035,cin*.6);
  float vd=length((uv-.5)*vec2(ro/max(ro,1.),1./max(ro,1.))*1.4); c*=1.-cin*.32*smoothstep(.45,1.,vd);} // Kino-Look: Teal & Orange, weiche Schwärzen, Vignette
 if(fe>0.){float vd=length((uv-.5)*vec2(ro,1.))/(.5*sqrt(ro*ro+1.)); c*=1.-fe*.55*smoothstep(.62,1.,vd);}
 if(lb>.5){float h=max(ro/2.39,.82)*.5; if(abs(uv.y-.5)>h) c=vec3(0.);} // Kino-Balken
 gl_FragColor=vec4(clamp(c,0.,1.),1.);}`;
  const LOOKS = [['natur', 'Natürlich', { br: 1, ct: 1, sa: 1, se: 0, cin: 0 }], ['hell', 'Heller & frisch', { br: 1.08, ct: 1.04, sa: 1.06, se: 0, cin: 0 }], ['warm', 'Warm', { br: 1.03, ct: 1.02, sa: 1.02, se: .22, cin: 0 }], ['kino', '🎬 Kino', { br: 1.01, ct: 1.07, sa: .88, se: .04, cin: 1 }], ['weich', 'Weich & ruhig', { br: 1.04, ct: .92, sa: .9, se: .12, cin: 0 }], ['sw', 'Schwarz-Weiß', { br: 1.02, ct: 1.06, sa: 0, se: 0, cin: 0 }]];
  const FMTS = [['orig', 'Wie aufgenommen'], ['9:16', 'Hoch 9:16'], ['4:5', '4:5'], ['1:1', 'Quadrat'], ['16:9', 'Quer 16:9']];
  // Eigener Bildausschnitt je Clip (Vivien, 30.10.2026: „im Schnitt direkt zoomen, verschieben und gerade stellen“)
  // F = { z: Zoom 1–3, x/y: -1…1 (wohin der Ausschnitt rückt), r: Grad }. Beim Drehen wird so viel mitgezoomt, dass keine Ecken fehlen.
  function frVals(F, asp) {
    const z = F ? Math.max(1, Math.min(3, +F.z || 1)) : 1, th = F ? (+F.r || 0) * Math.PI / 180 : 0, a = Math.abs(th);
    const kr = th ? Math.cos(a) + Math.max(asp, 1 / asp) * Math.sin(a) : 1, m = (1 - 1 / z) / 2;
    return { Z: z * kr, th, ox: F ? Math.max(-1, Math.min(1, +F.x || 0)) * m : 0, oy: F ? Math.max(-1, Math.min(1, +F.y || 0)) * m : 0, z };
  }
  // Hintergrund weich im Nachhinein (Vivien, 30.10.2026): Person per MediaPipe erkennen (wie im Studio), Rest weichzeichnen
  const MPV = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14';
  const SEG_B = 'https://storage.googleapis.com/mediapipe-models/image_segmenter/selfie_multiclass_256x256/float32/latest/selfie_multiclass_256x256.tflite';
  const SEG_S = 'https://storage.googleapis.com/mediapipe-models/image_segmenter/selfie_segmenter/float16/latest/selfie_segmenter.tflite';
  let segP = null, segTs = 0, segPrev = null; const segCv = document.createElement('canvas');
  function segLoad() {
    if (!segP) segP = (async () => { const m = await import(MPV + '/vision_bundle.mjs'), files = await m.FilesetResolver.forVisionTasks(MPV + '/wasm');
      const mk = (path, d) => m.ImageSegmenter.createFromOptions(files, { baseOptions: { modelAssetPath: path, delegate: d }, runningMode: 'VIDEO', outputCategoryMask: false, outputConfidenceMasks: true });
      let t = null, body = true; for (const [pth, d, b] of [[SEG_B, 'GPU', 1], [SEG_B, 'CPU', 1], [SEG_S, 'GPU', 0], [SEG_S, 'CPU', 0]]) { try { t = await mk(pth, d); body = !!b; break; } catch (e) {} }
      if (!t) throw new Error('seg'); t._body = body; return t; })().catch(e => { segP = null; throw e; });
    return segP;
  }
  async function segMask(src, sw, shh) {
    if (window.__snSegTest) return window.__snSegTest(src, sw, shh);
    const t = await segLoad(), W = 256, H = Math.max(16, Math.round(256 * shh / Math.max(1, sw)));
    if (segCv.width !== W || segCv.height !== H) { segCv.width = W; segCv.height = H; } segCv.getContext('2d').drawImage(src, 0, 0, W, H);
    segTs = Math.max(segTs + 1, performance.now()); const r = t.segmentForVideo(segCv, segTs), mk = r.confidenceMasks && r.confidenceMasks[0]; let out = null;
    if (mk) { const a = mk.getAsFloat32Array(), n = a.length; if (!segPrev || segPrev.length !== n) segPrev = new Float32Array(n); const b = new Uint8Array(n);
      for (let i = 0; i < n; i++) { const v = t._body ? 1 - a[i] : a[i], d = Math.abs(v - segPrev[i]); segPrev[i] = d > .25 ? v : segPrev[i] * .35 + v * .65; b[i] = segPrev[i] * 255; }
      out = { data: b, w: mk.width, h: mk.height }; }
    if (r.close) r.close(); return out;
  }
  const filterCss = L => `brightness(${L.br}) contrast(${L.ct}) saturate(${L.sa}) sepia(${L.se})`;
  const brate = (w, h) => { const p = w * h; return p >= 7e6 ? 40e6 : p >= 3.2e6 ? 20e6 : p >= 1.8e6 ? 12e6 : p >= .8e6 ? 8e6 : 5e6; };
  function mime() { const ts = ['video/mp4;codecs=avc1.640028,mp4a.40.2', 'video/mp4;codecs=avc1,mp4a', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']; return ts.find(t => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || ''; }

  function open(o) {
    css();
    const mount = o.mount; mount.innerHTML = '';
    const root = document.createElement('div'); root.className = 'sn'; mount.appendChild(root);
    root.innerHTML = `<div class="sn-l">
  <div class="sn-tg" data-k="tg"><span data-k="tgT"></span><button type="button" class="sn-b sm" data-k="tgBtn">Ändern</button><select data-k="tgSel" hidden aria-label="Wohin kommt das Video?"></select></div>
  <div class="sn-pv" id="snpv"><div class="empty" id="snempty"><b>Noch keine Clips</b>Tipp unten auf „＋ Clips“ und wähl Videos vom iPhone oder Computer, oder nimm im Studio auf.</div><img class="sn-covimg" data-k="covimg" alt=""><button type="button" class="big" data-k="big" hidden aria-label="Abspielen">▶</button><span class="tag" data-k="tag"></span></div>
  <div class="sn-tl">
    <div class="sn-row" style="margin-top:8px"><span class="t" data-k="time">0:00 / 0:00</span>
      <button type="button" class="sn-b pl" data-k="play">▶ Abspielen</button>
      <button type="button" class="sn-b" data-k="cut2" title="An der Stelle der Linie schneiden (Taste S)">✂️ Hier schneiden</button>
      <button type="button" class="sn-b" data-k="frBtn" aria-expanded="false">🔍 Zoom &amp; Ausschnitt</button>
      <button type="button" class="sn-b" data-k="ztDel" title="Das markierte Stück löschen" style="color:#9A3B3B">🗑 Stück löschen</button>
      <button type="button" class="sn-b" data-k="cmp" title="Licht & Farbe kurz ausschalten">Vorher zeigen</button></div>
    <div class="sn-fr" data-k="fr" hidden>
      <div class="h"><b data-k="frT">Bildausschnitt</b><small>Gilt für diesen Clip. Du kannst das Bild auch direkt oben in der Vorschau verschieben.</small></div>
      <label><span>🔍 Zoom <i data-k="frZv">100 %</i></span><input type="range" min="100" max="300" step="1" value="100" data-fr="z"></label>
      <label><span>↔ Links / Rechts</span><input type="range" min="-100" max="100" step="1" value="0" data-fr="x"></label>
      <label><span>↕ Hoch / Runter</span><input type="range" min="-100" max="100" step="1" value="0" data-fr="y"></label>
      <label><span>📐 Gerade richten <i data-k="frRv">0°</i></span><input type="range" min="-10" max="10" step="0.1" value="0" data-fr="r"></label>
      <div style="margin-top:12px;font-size:15.5px;color:#4A3A41">🎥 Bewegung in diesem Clip</div>
      <div class="sn-chips" data-k="zmv"><button type="button" data-zm="none">Keine</button><button type="button" data-zm="slow">Langsam rein (Kino-Zoom)</button><button type="button" data-zm="punch">Schnell rein</button><button type="button" data-zm="out">Langsam raus</button></div>
      <label><span>🐟 Fischauge <i data-k="feV">aus</i></span><input type="range" min="0" max="100" step="1" value="0" data-k="fe"></label>
      <div class="sn-zt-hint">Soll der Zoom erst später anfangen? Mit ✂ an der Stelle schneiden und nur dem zweiten Teil die Bewegung geben.</div>
      <div class="sn-row"><button type="button" class="sn-b sm" data-k="frAll">Für alle Clips übernehmen</button><button type="button" class="sn-b sm" data-k="frReset">Zurücksetzen</button></div>
    </div>
    <div class="sn-zt">
      <div class="sn-zt-sc" data-k="ztsc"><div class="sn-zt-in" data-k="ztin"><div class="sn-zt-tt" data-k="ztt"></div><div class="sn-zt-blocks" data-k="ztb"></div><div class="sn-zt-ph" data-k="ztph"></div></div></div>
      <div class="sn-zt-bar"><button type="button" class="zb" data-k="zoomOut" aria-label="Zeitleiste zusammenziehen">－</button><input type="range" min="0" max="100" step="1" value="0" data-k="zoomR" aria-label="Zeitleiste auseinanderziehen"><button type="button" class="zb" data-k="zoomIn" aria-label="Zeitleiste auseinanderziehen">＋</button><span class="t" data-k="ztime">0:00,0</span></div>
    </div>
    <div class="sn-ovl" data-k="ovl"></div>
    <div class="sn-zt-hint" data-k="zthint">Tippen = Stelle anspringen · die Griffe <b>⟦ ⟧</b> an den Rändern ziehen = Anfang und Ende länger oder kürzer machen (das schraffierte Stück daneben ist das, was weggeschnitten wurde – da kannst du wieder hineinziehen) · der Griff darf auch über den nächsten Clip hinaus (was doppelt ist, kannst du darunter löschen) · zwei Finger auseinander (oder ＋) = Zeitleiste auseinanderziehen</div>
  </div>
  <h2 style="margin-top:16px" data-k="h2">Deine Clips <span style="font-size:16px;color:#7D7076;font-family:'Hanken Grotesk',sans-serif;font-weight:400" data-k="cnt"></span></h2>
  <div class="sn-zt-hint" style="margin:0 2px 4px">Reihenfolge ändern: Kachel nehmen und an die neue Stelle ziehen (am Handy kurz drauf halten, dann ziehen).</div>
  <div class="sn-clips" data-k="clips"></div>
  <div class="sn-cov" data-k="coved" hidden>
    <div class="h"><b>🖼 Titelbild ganz am Anfang</b><button type="button" class="sn-b sm" data-k="covX">Schließen</button></div>
    <label class="sn-sw"><input type="checkbox" data-k="covOn"> Titelbild vor dem ersten Clip zeigen</label>
    <div class="ths" data-k="covThs"></div>
    <div><div style="font-size:16px;color:#4A3A41;margin-bottom:4px">Wie lange?</div><div class="sn-chips" data-k="covDur"></div></div>
    <div class="sn-note">Das Bild steht ganz vorne, noch vor deinem ersten Clip, und ist auch das Vorschaubild, wenn das Video hochgeladen wird. Die Musik läuft schon darunter. Dein zuletzt gewähltes Titelbild nehme ich für die nächsten Talking Heads automatisch wieder.</div>
  </div>
  <div class="sn-row" style="margin-top:0">
    <label class="sn-b pl" style="cursor:pointer">＋ Clips vom Gerät<input type="file" accept="video/*,.mov,.mp4,.m4v,.webm" multiple hidden data-k="file"></label>
    ${o.studioUrl ? `<a class="sn-b" href="${esc(o.studioUrl)}">● Im Studio aufnehmen</a>` : ''}
    <button type="button" class="sn-b sm del" data-k="clearAll">Clips dieses Entwurfs löschen</button>
  </div>
  <details class="sn-trash" data-k="trash" hidden><summary>🗑 Papierkorb <span data-k="trN"></span></summary><div data-k="trL"></div>
    <div class="sn-row"><button type="button" class="sn-b sm del" data-k="trEmpty">Papierkorb leeren (endgültig)</button></div>
    <div class="sn-zt-hint">Gelöschte Clips bleiben hier 30 Tage auf diesem Gerät, dann werden sie endgültig gelöscht.</div></details>
  <details class="sn-trash" data-k="resc" hidden open><summary>🛟 Gerettete Videos <span data-k="rsN"></span></summary><div class="sn-zt-hint">Diese Videodateien liegen noch auf deinem Gerät, gehören aber zu keinem Clip mehr (z. B. die ursprüngliche Fassung einer Aufnahme). Ansehen und bei Bedarf zurückholen.</div><div data-k="rsL"></div></details>
  <div class="sn-ed" data-k="ed" hidden>
    <div class="h"><b data-k="edT"></b><span data-k="edD"></span></div>
    <div class="sn-row">
      <button type="button" class="sn-b sm" data-k="setA">⟦ Anfang hier</button>
      <button type="button" class="sn-b sm" data-k="setB">Ende hier ⟧</button>
      <button type="button" class="sn-b sm" data-k="split">✂ Hier teilen</button>
      <button type="button" class="sn-b sm" data-k="resetCut">Schnitt zurück</button>
    </div>
    <div class="sn-row">
      <button type="button" class="sn-b sm" data-k="first">⇤ Ganz nach vorne</button>
      <button type="button" class="sn-b sm" data-k="left">← Nach vorne</button>
      <button type="button" class="sn-b sm" data-k="right">Nach hinten →</button>
      <button type="button" class="sn-b sm" data-k="mute">🔇 Ton aus</button>
      <button type="button" class="sn-b sm del" data-k="delClip">🗑 Clip löschen</button>
    </div>
    <div class="sn-row">
      <button type="button" class="sn-b sm" data-k="broMk">🎞 Nur als Bild über das Video davor (Stimme läuft weiter)</button>
      <label class="sn-b sm" style="cursor:pointer">＋ Bild darüber legen (Video vom Gerät)<input type="file" accept="video/*,.mov,.mp4,.m4v,.webm" hidden data-k="broFile"></label>
    </div>
    <div class="sn-hint" data-k="broHint"></div>
    <div class="sn-bro" data-k="bro"></div>
  </div>
  <div class="sn-row" style="margin-top:14px">
    <button type="button" class="sn-b main" data-k="done">✓ Fertig stellen</button>
  </div>
  <div class="sn-prog" data-k="prog" hidden><i></i></div>
  <div class="sn-msg" data-k="msg"></div>
  <div class="sn-res" data-k="res" hidden>
    <h2>Dein fertiges Video</h2>
    <div class="sn-up" data-k="up" hidden></div>
    <video controls playsinline data-k="resv" style="margin-top:10px"></video>
    <div class="sn-row" style="margin-top:12px">
      <button type="button" class="sn-b pl" data-k="share" hidden>📲 In Fotos sichern</button>
      <a class="sn-b" data-k="dl" download>⬇ Als Datei herunterladen</a>
      ${o.upload ? '<button type="button" class="sn-b dk" data-k="upBtn">⬆ Hochladen</button>' : ''}
      <button type="button" class="sn-b sm" data-k="resX">Schließen</button>
    </div>
    <div class="sn-note" style="margin-top:8px" data-k="resN"></div>
  </div>
</div>
<div class="sn-r">
  <details class="sn-box dr" open><summary>📝 Entwürfe</summary><div class="bd">
    <div><label style="display:block;font-size:16px;color:#4A3A41;margin-bottom:4px">Woran arbeitest du gerade?</label>
      <input type="text" class="sn-dr-name" maxlength="60" placeholder="z. B. The Method · Morgen-Flow" data-k="drName"></div>
    <div class="sn-note">Jede Aufnahme und jeder Schnitt gehört zu einem Entwurf. So kannst du zuerst alle Videos aufnehmen und später in Ruhe eines nach dem anderen fertig schneiden. Alles bleibt auf diesem Gerät gespeichert.</div>
    <ul class="sn-dr-list" data-k="drList"></ul>
    <button type="button" class="sn-b" data-k="drNew">＋ Neuer Entwurf</button>
  </div></details>
  <details class="sn-box auto" open><summary>✨ Automatisch schneiden</summary><div class="bd">
    <div class="sn-note" style="color:#4A3A41">Ein Tipp, und ich schneide Pausen raus, nehme heraus, wo du dich erst zurechtsetzt oder neu angesetzt hast, und lege einen sanften Zoom über die Abschnitte, damit es sich flüssig und lebendig anschaut. Danach siehst du jeden Schnitt in der Zeitleiste und kannst ihn mit den Griffen länger oder kürzer ziehen.</div>
    <button type="button" class="sn-b main" data-k="auto" style="font-size:18px;min-height:52px">✨ Jetzt automatisch schneiden</button>
    <div class="sn-pz">
      <div class="lb">Pausen rausschneiden ab <b data-k="pauseV">0,7 s</b></div>
      <input type="range" min="20" max="250" step="5" value="70" data-k="pauseR" aria-label="Pausen rausschneiden ab">
      <div class="sc"><span>jede Atempause</span><span>nur lange Pausen</span></div>
      <div class="sn-note" data-k="pauseH"></div>
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
    <div data-sl="br"></div><div data-sl="ct"></div><div data-sl="sa"></div><div data-sl="se"></div><div data-sl="bb"></div>
    <label class="sn-sw"><input type="checkbox" data-k="lbx"> 🎬 Kino-Balken (schmale schwarze Streifen oben und unten)</label>
    <div class="sn-note">Gilt für alle Clips, damit das Video aus einem Guss ist.</div>
  </div></details>
  <details class="sn-box"><summary>🖼 Format</summary><div class="bd">
    <div class="sn-chips" data-k="fmts"></div>
    <div class="sn-note">Bei einem anderen Format wird die Mitte ausgeschnitten. Für Reels und Storys „Hoch 9:16“.</div>
  </div></details>
  <details class="sn-box"><summary>🎵 Musik darunter</summary><div class="bd">
    <div class="sn-ml" data-k="mlist"></div>
    <div class="sn-sug" data-k="sug"></div>
    <details class="sn-all"><summary data-k="allsum">Alle Lieder durchsuchen</summary>
      <input type="search" data-k="msearch" placeholder="Lied oder Künstlerin suchen …" aria-label="Lied suchen">
      <div class="sn-lib" data-k="lib"><div class="sn-note">Deine Musik wird geladen …</div></div>
    </details>
    <label class="sn-b" style="width:max-content;cursor:pointer">📁 Lieder vom Gerät<input type="file" accept="audio/*,.mp3,.m4a,.wav,.aac" multiple hidden data-k="mfile"></label>
    <div data-sl="vol"></div><div data-sl="voice"></div>
    <label class="sn-sw"><input type="checkbox" data-k="duck"> Musik leiser, wenn ich spreche</label>
    <label class="sn-sw"><input type="checkbox" data-k="fade"> Musik sanft ein- und ausblenden</label>
  </div></details>
  <div class="sn-note">Deine Clips bleiben auf diesem Gerät gespeichert (auch nach dem Schließen), bis du sie hier löschst. Die Vorschau zeigt alles so, wie es nachher gespeichert wird.</div>
</div>`;
    const q = s => root.querySelector(s), K = k => root.querySelector('[data-k="' + k + '"]');
    const pv = q('#snpv'), msg = K('msg'), big = K('big');
    const say = (t, c) => { msg.className = 'sn-msg' + (c ? ' ' + c : ''); msg.textContent = t; };

    // ---- Zustand ----
    let clips = [], sel = -1, closed = false, busy = false, curDraft = null, drList = [];
    let bros = []; const ovV = new Map(); // „Bild darüber“: Clips, die nur ihr Bild über ein Talking Head legen (dessen Stimme läuft weiter)
    let covers = [], cov = null, shAC = null; const elSrcs = new WeakMap(); // Titelbilder · gemeinsamer Ton-Mischer
    const st = Object.assign({ look: 'natur', br: 1, ct: 1, sa: 1, se: 0, bb: 0, fmt: 'orig' }, ls.get('sn-st', {}));
    const mus = Object.assign({ vol: .18, voice: 1, duck: true, fade: true }, ls.get('sn-mus', {}));
    const auto = Object.assign({ pause: .7, stumbles: true, zoom: true, noise: true }, ls.get('sn-auto', {}));
    let mlist = []; const XF = 2.5; // Musik-Playlist; XF = Sekunden Überblendung (bei kurzen Liedern weniger)
    const xfOf = d => Math.max(.4, Math.min(XF, (d || 30) / 4));
    const vids = new Map(); // „Platz“ (url#0 / url#1) → <video>: zwei je Datei im Wechsel, damit der nächste Abschnitt
    // schon vorgespult bereitliegt und es am Schnitt nicht stockt (Vivien, 30.10.2026: „Video stockt in der Mitte“)
    // Schließt ein Clip nahtlos an den vorigen an (gleiche Datei, Ende = Anfang, also z. B. nach „Hier schneiden“), nimmt er
    // dasselbe Video-Element: es läuft einfach weiter, ohne zu springen. Nur echte Sprünge wechseln auf das zweite Element.
    function slots() { const last = {}; let prev = null; clips.forEach(c => { const lk = last[c.url];
      if (prev && prev.url === c.url && lk != null && Math.abs((prev.out || prev.dur || 0) - (c.in || 0)) < .06) c.slot = c.url + '#' + lk;
      else { const k = lk == null ? 0 : 1 - lk; c.slot = c.url + '#' + k; last[c.url] = k; } prev = c; }); }
    function vidFor(c) {
      if (!c.slot) slots();
      let v = vids.get(c.slot);
      if (v) { if (!c.dur && v.readyState >= 1 && isFinite(v.duration) && v.duration > 0) { c.dur = v.duration; if (!c.out) c.out = c.dur; } return v; }
      v = document.createElement('video'); v.playsInline = true; v.setAttribute('playsinline', ''); v.preload = 'auto'; v.muted = false;
      v.src = c.url; pv.insertBefore(v, big); vids.set(c.slot, v);
      v.addEventListener('error', () => { const e = v.error; console.warn('Schnitt: Video lässt sich nicht abspielen', c.name, e && e.code, e && e.message); say('„' + c.name + '“ lässt sich in diesem Browser gerade nicht abspielen (Fehler ' + ((e && e.code) || '?') + '). Bitte schreib mir, ob du Safari oder Chrome nutzt.', 'bad'); });
      v.addEventListener('loadedmetadata', () => { const d = isFinite(v.duration) && v.duration > 0 ? v.duration : 0; let probe = false; clips.filter(x => x.url === c.url && !x.dur).forEach(x => { x.dur = d; if (!x.out) x.out = d; if (!d) probe = true; }); if (!c.dur) { c.dur = d; if (!c.out) c.out = d; if (!d) probe = true; } if (probe) probeDur(c, v); else ui(); });
      return v;
    }
    // Plätze aufräumen, die kein Clip mehr braucht
    function dropVids() { const used = new Set(clips.map(c => c.slot)); [...vids.keys()].forEach(k => { if (!used.has(k)) { const v = vids.get(k); if (v) { v.pause(); v.removeAttribute('src'); v.remove(); } vids.delete(k); } }); }
    // Zoom je Clip: [von, bis] als Faktor, wird über die Clip-Länge gleichmäßig gefahren
    const zAt = (c, off) => { if (!c || !c.zoom) return 1; const L = len(c) || 1; let p = Math.max(0, Math.min(1, off / L));
      if (c.zm === 'ease') p = p * p * (3 - 2 * p); else if (c.zm === 'punch') { const q = Math.max(0, Math.min(1, off / .45)); p = 1 - Math.pow(1 - q, 3); }
      return c.zoom[0] + (c.zoom[1] - c.zoom[0]) * p; };
    // MediaRecorder-Dateien kennen ihre Länge oft nicht: einmal ans Ende springen
    async function probeDur(c, v) { try { v.currentTime = 1e7; await new Promise(r => { v.onseeked = r; setTimeout(r, 2500); }); c.dur = isFinite(v.duration) && v.duration > 0 ? v.duration : (v.currentTime || 1); v.currentTime = 0; } catch (e) { c.dur = c.dur || 1; } if (!c.out || c.out > c.dur) c.out = c.dur; dbPut(dbRow(c)); ui(); }
    const dbRow = c => ({ id: c.id, blob: c.blob, name: c.name, added: c.added, in: c.in, out: c.out, mute: !!c.mute, dur: c.dur || 0, zoom: c.zoom || null, auto: !!c.auto, draft: c.draft || curDraft, src: c.src || null, bro: !!c.bro, osrc: c.osrc || null, ost: c.ost || 0, fr: c.fr || null, zm: c.zm || null, fe: c.fe || 0, trash: c.trash || 0, tpos: c.tpos || 0, fx: c.fx || 0, rec: c.rec || 0 });
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
        const all = await dbAll(), rows = all.filter(r => r.draft === curDraft && !r.trash && r.blob), order = (await kvGet(ordKey(curDraft))) || [];
        rows.sort((a, b) => (order.indexOf(a.id) + 1 || 1e9) - (order.indexOf(b.id) + 1 || 1e9) || a.added - b.added);
        const urls = new Map(); const objs = rows.map(r => { let u = urls.get(r.blob); if (!u) { u = URL.createObjectURL(r.blob); urls.set(r.blob, u); } return Object.assign({}, r, { url: u }); });
        clips = objs.filter(r => !r.bro); bros = objs.filter(r => r.bro); bros.forEach(ovVid);
        slots(); if (clips.length) { sel = 0; vidFor(clips[0]); prep(1); }
        await covLoad().catch(() => {});
        drawDrafts(); ui(); clips.forEach(thumb); trashUi(); rescueUi();
      } catch (e) { say('Die gespeicherten Clips konnten nicht geladen werden.', 'bad'); }
    }
    // ---- Entwürfe ----
    const drName = id => { const d = drList.find(x => x.id === id); return (d && d.name) || 'Ohne Namen'; };
    async function drawDrafts() {
      if (closed) return;
      const inp = K('drName'); if (document.activeElement !== inp) inp.value = (drList.find(d => d.id === curDraft) || {}).name || '';
      const all = await dbAll().catch(() => []);
      const info = {}; all.forEach(c => { const k = c.draft; if (!k || c.bro || c.trash) return; const i = info[k] || (info[k] = { n: 0, sec: 0 }); i.n++; i.sec += Math.max(0, (c.out || c.dur || 0) - (c.in || 0)); });
      const when = t => { const d = new Date(t || 0), h = new Date(), s2 = x => String(x).padStart(2, '0');
        return (d.toDateString() === h.toDateString() ? 'heute' : d.getDate() + '.' + (d.getMonth() + 1) + '.') + ' ' + s2(d.getHours()) + ':' + s2(d.getMinutes()); };
      const L = drList.slice().sort((a, b) => (b.at || 0) - (a.at || 0));
      K('drList').innerHTML = L.map(d => { const i = info[d.id] || { n: 0, sec: 0 }, on = d.id === curDraft;
        return `<li class="${on ? 'on' : ''}"><span>${esc(d.name || 'Ohne Namen')}<small>${i.n ? i.n + (i.n > 1 ? ' Clips' : ' Clip') + (i.sec > .05 ? ' · ' + fmt(i.sec) : '') : 'noch leer'} · ${when(d.at)}${on ? ' · offen' : ''}</small>${d.up ? '<small class="up">✓ hochgeladen ' + when(d.up.at) + '</small>' : ''}</span>`
          + (on ? '' : `<button type="button" data-dopen="${d.id}">Öffnen</button>` + (i.n ? `<button type="button" data-dmerge="${d.id}" title="Alle Clips aus diesem Entwurf hinten an den offenen Entwurf hängen">＋ Anhängen</button>` : ''))
          + `<button type="button" class="x" data-ddel="${d.id}" aria-label="Entwurf löschen" title="Entwurf löschen">🗑</button></li>`; }).join('');
      K('drList').querySelectorAll('[data-dopen]').forEach(b => b.onclick = () => openDraft(b.dataset.dopen));
      K('drList').querySelectorAll('[data-ddel]').forEach(b => b.onclick = () => delDraft(b.dataset.ddel));
      K('drList').querySelectorAll('[data-dmerge]').forEach(b => b.onclick = () => mergeDraft(b.dataset.dmerge));
      K('h2').firstChild.textContent = (drList.find(d => d.id === curDraft) || {}).name ? 'Clips in „' + drName(curDraft) + '“ ' : 'Deine Clips ';
      tgUi();
    }
    K('drName').oninput = () => { const d = drList.find(x => x.id === curDraft); if (!d) return; d.name = K('drName').value.trim().slice(0, 60); d.at = Date.now(); draftsSave(drList).catch(() => {}); clearTimeout(K('drName')._t); K('drName')._t = setTimeout(drawDrafts, 600); };
    async function openDraft(id) {
      if (busy || id === curDraft) return; stop();
      clips.forEach(c => { if (!clips.some(x => x !== c && x.url === c.url)) URL.revokeObjectURL(c.url); });
      clips = []; broClear(); dropVids(); sel = -1; pos = 0; undoRows = null; autoLog = []; lastSum = null; K('undo').hidden = true; autoResUi(null);
      await kvSet('cur', id).catch(() => {}); K('res').hidden = true; K('coved').hidden = true; covShow(false); result = null;
      await load(); say('📝 „' + drName(id) + '“ ist offen.', 'ok');
    }
    // Zwei Entwürfe zu einem (Vivien, 30.10.2026: „den einen Entwurf auf den anderen draufsetzen, damit ich das ganze Video bearbeiten kann“)
    async function mergeDraft(id) {
      if (busy || id === curDraft) return; const nm = drName(id), into = drName(curDraft);
      if (!await ask('Alle Clips aus „' + nm + '“ hinten an „' + into + '“ anhängen? Danach ist es ein Entwurf.')) return; stop();
      const all = await dbAll(), rows = all.filter(r => r.draft === id), ord = (await kvGet(ordKey(id))) || [];
      rows.sort((a, b) => (ord.indexOf(a.id) + 1 || 1e9) - (ord.indexOf(b.id) + 1 || 1e9) || a.added - b.added);
      for (const r of rows) { r.draft = curDraft; await dbPut(r); }
      const mine = (await kvGet(ordKey(curDraft))) || clips.map(c => c.id);
      await kvSet(ordKey(curDraft), mine.concat(rows.filter(r => !r.bro).map(r => r.id)));
      await kvDel(ordKey(id)).catch(() => {}); drList = (await drafts()).filter(d => d.id !== id); await draftsSave(drList);
      const keep = sel; clips.forEach(c => { if (!clips.some(x => x !== c && x.url === c.url)) URL.revokeObjectURL(c.url); }); broClear(); clips = []; dropVids();
      await load(); if (keep >= 0 && clips[keep]) { sel = keep; ui(); }
      say('✓ „' + nm + '“ hängt jetzt hinten an. Alles ist ein Video mit ' + clips.length + ' Clips, du kannst es ganz bearbeiten.', 'ok');
    }
    async function delDraft(id) {
      const nm = drName(id); if (!await ask('Entwurf „' + nm + '“ löschen? Die Clips kommen in den Papierkorb.')) return;
      const all = await dbAll(), tt = Date.now(); for (const c of all) if (c.draft === id && !c.trash) { c.trash = tt; c.tname = nm; await dbPut(c); }
      await kvDel(ordKey(id)).catch(() => {});
      drList = (await drafts()).filter(d => d.id !== id); await draftsSave(drList);
      if (id === curDraft) { clips.forEach(c => URL.revokeObjectURL(c.url)); clips = []; dropVids(); sel = -1; pos = 0; await kvDel('cur').catch(() => {}); await load(); }
      else drawDrafts();
      trashUi(); say('🗑 „' + nm + '“ gelöscht, die Clips liegen im Papierkorb.');
    }
    K('drNew').onclick = async () => {
      if (busy) return; const d = { id: uid(), name: '', at: Date.now() };
      drList = await drafts(); drList.unshift(d); await draftsSave(drList);
      await openDraft(d.id); K('drName').focus();
    };
    // ---- Clips fürs Schneiden vorbereiten (Vivien, 30.10.2026: „beim Bearbeiten stockt es immer“) ----
    // Aufnahmen aus dem Browser (Studio, MediaRecorder) haben kein Inhaltsverzeichnis: jedes Springen an eine Schnittstelle
    // muss die Datei von vorne durchsuchen, darum hängt die Vorschau nach ein paar Schnitten. Einmal ohne Neu-Kodieren
    // umpacken (Mediabunny, nur Pakete kopieren) → Datei mit Index, Springen geht sofort. Läuft still im Hintergrund.
    let prepRun = false;
    async function remux(blob) {
      const M = await loadMB(); const input = new M.Input({ source: new M.BlobSource(blob), formats: M.ALL_FORMATS });
      const fmt = await input.getFormat().catch(() => null); const webm = /webm|matroska/i.test((fmt && fmt.name) || '') || /webm/.test(blob.type || '');
      let parts = [], end = 0;
      const put = (p, data) => { const bl = new Blob([data]), pe = p + data.byteLength; if (p === end) { parts.push({ p, b: bl }); end = pe; return; } if (p > end) { parts.push({ p: end, b: new Blob([new Uint8Array(p - end)]) }, { p, b: bl }); end = pe; return; }
        const nx = []; for (const x of parts) { const xs = x.p, xe = x.p + x.b.size; if (xe <= p || xs >= pe) { nx.push(x); continue; } if (xs < p) nx.push({ p: xs, b: x.b.slice(0, p - xs) }); if (xe > pe) nx.push({ p: pe, b: x.b.slice(pe - xs) }); } nx.push({ p, b: bl }); nx.sort((x, y) => x.p - y.p); parts = nx; if (pe > end) end = pe; };
      const target = new M.StreamTarget(new WritableStream({ write(ch) { put(ch.position, ch.data); } }), { chunked: true, chunkSize: 4 * 1024 * 1024 });
      const output = new M.Output({ format: webm ? new M.WebMOutputFormat() : new M.Mp4OutputFormat({ fastStart: false }), target });
      const conv = await M.Conversion.init({ input, output });
      if (!conv.isValid || (conv.discardedTracks || []).length) return null; // nichts verlieren (z. B. Ton): dann lieber so lassen
      if ((conv.utilizedTracks || []).some(t => t.type === 'video') && !(await input.getPrimaryVideoTrack())) return null;
      await conv.execute(); const nb = new Blob(parts.map(x => x.b), { type: webm ? 'video/webm' : 'video/mp4' });
      let dur = 0; try { const chk = new M.Input({ source: new M.BlobSource(nb), formats: M.ALL_FORMATS }); dur = await chk.computeDuration(); const a0 = await input.getPrimaryAudioTrack(), a1 = await chk.getPrimaryAudioTrack(); if (a0 && !a1) return null; } catch (e) { return null; }
      return { blob: nb, dur };
    }
    async function prepAll() {
      if (prepRun) return; prepRun = true;
      try {
        for (;;) {
          if (closed) break; const list = clips.concat(bros).filter(c => !c.fx && c.blob && c.blob.size < 1.5e9 && (c.rec || /webm/.test(c.blob.type || '') || /webm$|· Take \d|^Aufnahme/i.test(c.name || ''))); if (!list.length) break;
          const c = list[0], key = srcOf(c), size = c.blob.size, same = clips.concat(bros).filter(x => !x.fx && x.blob && (x.blob === c.blob || (x.blob.size === size && srcOf(x) === key)));
          if (!K('msg').textContent || /vorbereitet/.test(K('msg').textContent)) say('⏳ „' + c.name + '“ wird fürs Schneiden vorbereitet, du kannst schon weitermachen …');
          let r = null; try { r = await remux(c.blob); } catch (e) { console.warn('Schnitt: Vorbereiten nicht möglich', e); }
          while (playing || busy) await new Promise(x => setTimeout(x, 800));
          if (closed) break;
          const oldUrl = c.url, nu = r ? URL.createObjectURL(r.blob) : null;
          for (const x of same) { x.fx = 1; if (r) { x.blob = r.blob; x.url = nu; if (r.dur > 0) { x.dur = r.dur; if (x.out > r.dur) x.out = r.dur; } } await dbPut(dbRow(x)); }
          if (r) { if (!clips.concat(bros).some(x => x.url === oldUrl)) URL.revokeObjectURL(oldUrl); same.filter(x => x.bro).forEach(o => { const v = ovV.get(o.id); if (v) { v.pause(); v.remove(); ovV.delete(o.id); } });
            const keep = cur() ? cur().currentTime : 0, sc = clips[sel]; slots(); dropVids(); ui(); if (sc && same.includes(sc)) show(sel, Math.max(0, keep - (sc.in || 0))); }
        }
        if (/vorbereitet/.test(K('msg').textContent)) say('✓ Alle Clips sind fürs Schneiden bereit.', 'ok');
      } finally { prepRun = false; }
    }
    async function addFiles(files) {
      let n = 0;
      for (const f of files) { if (!f || !f.size) continue; const c = { id: uid(), blob: f, name: f.name.replace(/\.\w+$/, ''), added: Date.now() + n, in: 0, out: 0, mute: false, dur: 0, draft: curDraft }; c.src = c.id; c.url = URL.createObjectURL(f); clips.push(c); await dbPut(dbRow(c)); slots(); vidFor(c); thumb(c); n++; }
      await saveOrder(); await touch(); if (sel < 0 && clips.length) sel = 0; if (n) say('✓ ' + n + (n > 1 ? ' Clips' : ' Clip') + ' dazu. Tipp auf einen Clip, um Anfang und Ende zu setzen.', 'ok'); ui();
    }
    const saveOrder = () => kvSet(ordKey(curDraft), clips.map(c => c.id)).catch(() => {});
    const touch = async () => { const d = drList.find(x => x.id === curDraft); if (d) { d.at = Date.now(); await draftsSave(drList).catch(() => {}); drawDrafts(); } };
    K('file').onchange = e => { addFiles([...e.target.files]); e.target.value = ''; };
    K('clearAll').onclick = async () => { if (!clips.length || !await ask('Alle ' + clips.length + ' Clips in den Papierkorb legen?')) return; stop(); const tt = Date.now(); for (const [i, c] of clips.entries()) { c.trash = tt; c.tpos = i; await dbPut(dbRow(c)); } for (const o of bros) { o.trash = tt; await dbPut(dbRow(o)); } broClear(); const urls = new Set(clips.map(c => c.url)); clips = []; dropVids(); urls.forEach(u => URL.revokeObjectURL(u)); sel = -1; await saveOrder(); undoRows = null; K('undo').hidden = true; K('autoRes').hidden = true; drawDrafts(); ui(); trashUi(); say('Alle Clips aus „' + drName(curDraft) + '“ liegen im Papierkorb.'); };

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
      v.style.transform = vtf(c, seek !== false ? off : Math.max(0, v.currentTime - (c.in || 0)));
      broSync(c, seek !== false ? (c.in || 0) + off : v.currentTime, false);
    }
    function tick() {
      if (!playing) return;
      const c = clips[sel]; const v = cur(); if (!c || !v) { stop(); return; }
      const off = Math.max(0, v.currentTime - (c.in || 0)); pos = starts()[sel] + off; timeUi();
      musDrive(pos + CD(), true);
      if (c.zoom || c.fr) v.style.transform = vtf(c, off);
      broSync(c, v.currentTime, true);
      if (!prepped && (c.out || c.dur) - v.currentTime < 1.2) { prepped = true; prep(sel + 1); }
      if (v.currentTime >= (c.out || c.dur) - .04 || v.ended) {
        if (sel < clips.length - 1) { sel++; prepped = false; const n = clips[sel], nv = vidFor(n); show(sel, 0); nv.play().catch(() => {}); renderClips(false); edUi(); ztDraw(); }
        else { stop(); pos = 0; sel = 0; show(0, 0); renderClips(false); edUi(); timeUi(); return; }
      }
      raf = requestAnimationFrame(tick);
    }
    // Hängt die Vorschau (am Mac mit großen 4K-Dateien ab und zu nach ein paar Schnitten), wird das Video-Element
    // still neu geladen und läuft an derselben Stelle weiter – kein Neuladen der Seite mehr (Vivien, 30.10.2026)
    const wd = { t: -1, at: 0 };
    function heal(i, run) {
      const c = clips[i]; if (!c) return; const old = vids.get(c.slot), t = old ? old.currentTime : (c.in || 0);
      if (old) { try { old.pause(); old.removeAttribute('src'); old.load(); } catch (e) {} old.remove(); vids.delete(c.slot); }
      const v = vidFor(c); v.addEventListener('loadeddata', () => { try { v.currentTime = Math.max(c.in || 0, t); } catch (e) {} if (i === sel) { show(i, Math.max(0, t - (c.in || 0)), false); if (run && playing) v.play().catch(() => {}); } }, { once: true });
      console.warn('Schnitt: Vorschau hing, neu geladen');
    }
    let ltT = 0; const ltQ = new Set();
    function lazyThumb(c) { ltQ.add(c); clearTimeout(ltT); ltT = setTimeout(function go() { if (playing || busy) { ltT = setTimeout(go, 2500); return; } ltQ.forEach(x => { x.thumb = null; thumb(x); }); ltQ.clear(); }, 2500); }
    const starts = () => { let a = 0; return clips.map(c => { const s = a; a += len(c); return s; }); };
    async function play() {
      if (busy || !clips.length) return; if (sel < 0) sel = 0; const c = clips[sel], v = vidFor(c); prepped = false; prep(sel + 1);
      if (shAC && shAC.state === 'suspended') shAC.resume().catch(() => {});
      // Titelbild: von ganz vorne abgespielt, steht es zuerst ein paar Sekunden (Musik läuft schon darunter)
      const wantCov = CD() && sel === 0 && pos < .05 && !covRun && !covSkip; covSkip = false;
      if (wantCov) { show(0, 0); playing = true; big.hidden = true; K('play').textContent = '❚❚ Pause'; covPlay(); return; }
      if (v.currentTime < (c.in || 0) || v.currentTime >= (c.out || c.dur) - .05) show(sel, 0); else show(sel, 0, false);
      playing = true; big.hidden = true; K('play').textContent = '❚❚ Pause'; covShow(false);
      try { await v.play(); } catch (e) { playing = false; big.hidden = false; K('play').textContent = '▶ Abspielen'; say('Abspielen klappt nicht. Bitte nochmal auf ▶ tippen.'); return; }
      musDrive(pos + CD(), true);
      cancelAnimationFrame(raf); raf = requestAnimationFrame(tick);
    }
    let covRun = 0, covSkip = false;
    function covPlay() {
      const t0 = performance.now(), D0 = CD(); covShow(true); musDrive(0, true);
      const step = () => { if (!playing) { covRun = 0; return; } const t = (performance.now() - t0) / 1000;
        K('time').textContent = 'Titelbild ' + fmt1(t) + ' / ' + fmt(total() + D0); musDrive(t, true);
        if (t >= D0) { covRun = 0; covShow(false); pos = 0; covSkip = true; playing = false; play(); return; } covRun = requestAnimationFrame(step); };
      covRun = requestAnimationFrame(step);
    }
    function stop() { playing = false; cancelAnimationFrame(raf); ovV.forEach(x => { if (!x.paused) x.pause(); }); if (covRun) { cancelAnimationFrame(covRun); covRun = 0; covShow(false); } const v = cur(); if (v) v.pause(); musStop(); big.hidden = !clips.length; K('play').textContent = '▶ Abspielen'; }
    K('play').onclick = () => playing ? stop() : play(); big.onclick = play;
    K('cmp').onclick = () => { cmp = !cmp; K('cmp').textContent = cmp ? 'Nachher zeigen' : 'Vorher zeigen'; K('cmp').setAttribute('aria-pressed', String(cmp)); K('tag').textContent = cmp ? 'Vorher' : ''; const v = cur(); if (v) v.style.filter = cmp ? '' : filterCss(st); };
    function timeUi() { K('time').textContent = fmt(pos) + ' / ' + fmt(total()); if (clips.length) ztPh(); }
    const barUi = () => { if (clips.length) ztDraw(); };

    // ---- Clip-Liste ----
    function renderClips(full) {
      const box = K('clips');
      if (full !== false) box.innerHTML = covTile() + clips.map((c, i) => `<button type="button" class="sn-c ${i === sel ? 'on' : ''}${c.auto ? ' auto' : ''}" data-ci="${i}"><span class="n">${i + 1}</span>${c.mute ? '<span class="mu">🔇</span>' : ''}${broOf(c).length ? '<span class="br">🎞 ' + broOf(c).length + '</span>' : ''}${c.thumb ? `<img src="${c.thumb}" alt="">` : '<span class="ph">🎬</span>'}<b>${esc(c.name)}</b><small>${fmt1(len(c))}${(c.in || (c.out && c.dur && c.out < c.dur - .05)) ? ' · geschnitten' : ''}</small></button>`).join('')
        + `<label class="sn-add"><b>＋</b>Clips<input type="file" accept="video/*,.mov,.mp4,.m4v,.webm" multiple hidden></label>`;
      else box.querySelectorAll('.sn-c').forEach(b => b.classList.toggle('on', +b.dataset.ci === sel));
      if (full !== false) dragInit(box);
      box.querySelectorAll('.sn-c[data-ci]').forEach(b => b.onclick = () => { if (busy || dragJust) return; stop(); covShow(false); sel = +b.dataset.ci; pos = starts()[sel]; show(sel, 0); renderClips(false); edUi(); barUi(); timeUi(); });
      const add = box.querySelector('.sn-add:not(.cov) input'); if (add) add.onchange = e => { addFiles([...e.target.files]); e.target.value = ''; };
      const ct = box.querySelector('[data-cov]'); if (ct) ct.onclick = () => { if (busy) return; stop(); const o2 = K('coved').hidden; K('coved').hidden = !o2; covShow(o2 && !!covObj()); if (o2) covEdUi(); };
      const cf = box.querySelector('.sn-add.cov input'); if (cf) cf.onchange = e => { const f = e.target.files[0]; e.target.value = ''; if (f) covAdd(f); };
      K('cnt').textContent = clips.length ? '· ' + clips.length + (clips.length > 1 ? ' Clips' : ' Clip') + ' · ' + fmt(total() + CD()) + (CD() ? ' mit Titelbild' : '') : '';
      q('#snempty').hidden = !!clips.length; big.hidden = !!playing || !clips.length;
      K('done').disabled = !clips.length; doneLbl();
    }
    // Kacheln mit dem Finger/der Maus verschieben (Vivien, 30.10.2026: „Takes anklicken und ganz nach vorne schieben“)
    // Maus: einfach ziehen. Handy: kurz drauf halten, dann ziehen (sonst würde das Wischen durch die Leiste nicht mehr gehen).
    let dragJust = false, dragOn = false;
    document.addEventListener('touchmove', e => { if (dragOn) e.preventDefault(); }, { passive: false });
    function dragInit(box) {
      box.querySelectorAll('.sn-c[data-ci]').forEach(tile => {
        tile.style.touchAction = 'pan-x pan-y'; tile.draggable = false; tile.querySelectorAll('img').forEach(i => i.draggable = false);
        tile.onpointerdown = e => {
          if (busy || e.button > 0) return; const from = +tile.dataset.ci, x0 = e.clientX, y0 = e.clientY, touch = e.pointerType !== 'mouse';
          let armed = !touch, started = false, ghost = null, drop = null, to = from, timer = 0, scr = 0;
          const tiles = () => [...box.querySelectorAll('.sn-c[data-ci]')];
          const start = () => { started = true; dragOn = true; stop(); tile.classList.add('drag'); ghost = document.createElement('div'); ghost.className = 'sn-ghost';
            const im = tile.querySelector('img'); ghost.innerHTML = (im ? '<img src="' + im.src + '" alt="">' : '') + '<b>' + esc(clips[from].name) + '</b>'; document.body.appendChild(ghost);
            drop = document.createElement('span'); drop.className = 'sn-drop'; if (navigator.vibrate) try { navigator.vibrate(15); } catch (er) {} };
          const place = (x, y) => { ghost.style.left = (x - 60) + 'px'; ghost.style.top = (y - 50) + 'px';
            const T = tiles(); let i = T.length; for (let k = 0; k < T.length; k++) { const r = T[k].getBoundingClientRect(); if (x < r.left + r.width / 2) { i = k; break; } }
            to = i; const ref = T[i] || box.querySelector('.sn-add:not(.cov)'); if (ref && drop.nextSibling !== ref) box.insertBefore(drop, ref);
            const br = box.getBoundingClientRect(); cancelAnimationFrame(scr); const edge = x < br.left + 50 ? -12 : x > br.right - 50 ? 12 : 0;
            if (edge) { const go = () => { box.scrollLeft += edge; scr = requestAnimationFrame(go); }; scr = requestAnimationFrame(go); } };
          if (touch) timer = setTimeout(() => { armed = true; start(); place(x0, y0); }, 320);
          const move = ev => { const dx = ev.clientX - x0, dy = ev.clientY - y0;
            if (!started) { if (touch && !armed && Math.hypot(dx, dy) > 10) { clearTimeout(timer); end(); return; } if (armed && Math.hypot(dx, dy) > 6) start(); else return; }
            ev.preventDefault(); place(ev.clientX, ev.clientY); };
          const end = async ev => { clearTimeout(timer); cancelAnimationFrame(scr); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', end); window.removeEventListener('pointercancel', end);
            if (!started) return; dragOn = false; dragJust = true; setTimeout(() => dragJust = false, 250); if (ghost) ghost.remove(); if (drop) drop.remove(); tile.classList.remove('drag');
            if (!ev || ev.type === 'pointercancel') { renderClips(); return; }
            let t = to > from ? to - 1 : to; if (t === from) { renderClips(); return; }
            const [c] = clips.splice(from, 1); clips.splice(t, 0, c); sel = t; await saveOrder(); pos = starts()[sel]; ui();
            say('↔ „' + c.name + '“ ist jetzt Clip ' + (t + 1) + (t === 0 ? ' (ganz vorne)' : t === clips.length - 1 ? ' (ganz hinten)' : '') + '.', 'ok'); };
          window.addEventListener('pointermove', move, { passive: false }); window.addEventListener('pointerup', end); window.addEventListener('pointercancel', end);
        };
        tile.oncontextmenu = e => e.preventDefault();
      });
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
      broUi(); frUi();
      K('mute').textContent = c.mute ? '🔊 Ton an' : '🔇 Ton aus'; K('left').disabled = sel === 0; K('first').disabled = sel === 0; K('right').disabled = sel === clips.length - 1;
      ovlUi();
    }
    function ui() { slots(); dropVids(); renderClips(); edUi(); timeUi(); schedCache = null; mlUi(); sugUi(); if (sel >= 0 && clips[sel]) { show(sel, 0, false); prep(sel + 1); } }
    async function change(c) { await dbPut(dbRow(c)); c.thumb = null; ui(); thumb(c); }
    K('setA').onclick = () => { const c = clips[sel], v = cur(); if (!c || !v) return; c.in = Math.min(v.currentTime, (c.out || c.dur) - .3); change(c); say('⟦ Anfang gesetzt bei ' + fmt1(c.in) + '.'); };
    K('setB').onclick = () => { const c = clips[sel], v = cur(); if (!c || !v) return; c.out = Math.max(v.currentTime, (c.in || 0) + .3); change(c); say('Ende gesetzt bei ' + fmt1(c.out) + ' ⟧.'); };
    K('resetCut').onclick = () => { const c = clips[sel]; if (!c) return; c.in = 0; c.out = c.dur; change(c); };
    K('split').onclick = async () => { const L = locate(pos); if (L.i >= 0) sel = L.i; const c = clips[sel], v = cur(); if (!c || !v) return; const t = L.i >= 0 ? (c.in || 0) + L.off : v.currentTime; if (t <= (c.in || 0) + .3 || t >= (c.out || c.dur) - .3) { say('Zum Teilen die Stelle etwas weiter in die Mitte schieben.'); return; }
      const n = Object.assign({}, c, { id: uid(), in: t, added: c.added + 1, thumb: c.thumb || null, url: c.url }); c.out = t; clips.splice(sel + 1, 0, n); await dbPut(dbRow(c)); await dbPut(dbRow(n)); await saveOrder(); ui(); lazyThumb(n); say('✂ Geteilt bei ' + fmt1(t) + '. Der zweite Teil ist jetzt Clip ' + (sel + 2) + '.', 'ok'); };
    K('cut2').onclick = () => { if (busy || !clips[sel]) return; K('split').onclick(); }; // läuft beim Schneiden einfach weiter
    document.addEventListener('keydown', e => { if (closed || busy || e.metaKey || e.ctrlKey || e.altKey || (e.key !== 's' && e.key !== 'S')) return; const t = e.target; if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return; if (!root.isConnected || !clips[sel]) return; e.preventDefault(); K('cut2').click(); });
    K('left').onclick = async () => { if (sel <= 0) return; [clips[sel - 1], clips[sel]] = [clips[sel], clips[sel - 1]]; sel--; await saveOrder(); ui(); };
    K('first').onclick = async () => { if (sel <= 0) return; const [c] = clips.splice(sel, 1); clips.unshift(c); sel = 0; await saveOrder(); pos = 0; ui(); say('⇤ „' + c.name + '“ ist jetzt ganz vorne.', 'ok'); };
    K('right').onclick = async () => { if (sel >= clips.length - 1) return; [clips[sel + 1], clips[sel]] = [clips[sel], clips[sel + 1]]; sel++; await saveOrder(); ui(); };
    K('mute').onclick = () => { const c = clips[sel]; if (!c) return; c.mute = !c.mute; dbPut(dbRow(c)); ui(); };
    // ---- Bild darüber, Stimme läuft weiter (Vivien, 30.10.2026: „im Talking Head zeige ich etwas dazwischen,
    // die Stimme soll weiterlaufen, nur das Bild wechselt“). Ein Clip wird zum Bild über einem Talking Head:
    // verankert an der Stelle im Ausgangsvideo (osrc = Quelle, ost = Sekunde darin), eigener Ton fällt weg.
    function srcOf(c) { return c.src || c.id; }
    async function ensureSrc(P) { if (P.src) return P.src; const g = clips.filter(x => !x.src && x.name === P.name && Math.abs((x.dur || 0) - (P.dur || 0)) < .05); if (!g.includes(P)) g.push(P); for (const x of g) { x.src = P.id; await dbPut(dbRow(x)); } return P.src; }
    function broOf(c) { const k = srcOf(c), a = c.in || 0, b = c.out || c.dur || 0; return bros.filter(o => o.osrc === k && o.ost >= a - .01 && o.ost < b); }
    function broAt(c, s) { if (!c) return null; const k = srcOf(c); for (const o of bros) { const L = len(o); if (o.osrc === k && L > 0 && s >= o.ost && s < o.ost + L - .001) return o; } return null; }
    function ovVid(o) {
      let v = ovV.get(o.id); if (v) return v;
      v = document.createElement('video'); v.className = 'sn-ov'; v.muted = true; v.playsInline = true; v.setAttribute('playsinline', ''); v.preload = 'auto'; v.src = o.url;
      pv.insertBefore(v, big); ovV.set(o.id, v);
      v.addEventListener('loadedmetadata', () => { if (o.dur) return; const d = isFinite(v.duration) && v.duration > 0 ? v.duration : 0; if (d) { o.dur = d; if (!o.out) o.out = d; dbPut(dbRow(o)); broUi(); renderClips(); } else probeDur(o, v); });
      return v;
    }
    function broSync(c, s, run) {
      const o = broAt(c, s);
      ovV.forEach((v, id) => { if (!o || id !== o.id) { v.classList.remove('on'); if (!v.paused) v.pause(); } });
      if (!o) return; const v = ovVid(o), want = (o.in || 0) + (s - o.ost);
      v.style.filter = cmp ? '' : filterCss(st); v.classList.add('on');
      if (Math.abs(v.currentTime - want) > (run ? .3 : .05)) { try { v.currentTime = want; } catch (e) {} }
      if (run && v.paused) v.play().catch(() => {}); if (!run && !v.paused) v.pause();
    }
    function broClear() { ovV.forEach(v => { v.pause(); v.removeAttribute('src'); v.remove(); }); ovV.clear(); bros.forEach(o => { if (!clips.some(c => c.url === o.url)) URL.revokeObjectURL(o.url); }); bros = []; }
    function broUi() {
      const c = clips[sel], box = K('bro'); if (!c) { box.innerHTML = ''; return; }
      K('broMk').style.display = sel < 1 ? 'none' : '';
      K('broHint').textContent = sel < 1 ? 'Tipp: Leg den Clip, den du zeigen willst, direkt hinter dein Talking Head und tipp dort auf „🎞 Nur als Bild über das Video davor“.' : 'Dieser Clip wird zum Bild über Clip ' + sel + ': man sieht ihn, hört aber weiter die Stimme aus Clip ' + sel + '.';
      const a = c.in || 0, b = c.out || c.dur || 0, list = broOf(c);
      box.innerHTML = list.length ? '<b style="font-weight:500;color:#4A3A41">🎞 Bild darüber · Stimme läuft weiter</b>' + list.map(o => { const mx = Math.max(.6, (o.dur || len(o)) - (o.in || 0));
        return '<div class="sn-bo" data-bo="' + o.id + '"><div class="t"><b>' + esc(o.name) + '</b><small data-bl>' + fmt1(len(o)) + ' lang · ab ' + fmt1(o.ost - a) + ' in diesem Clip</small></div>'
          + '<label>Beginnt bei<input type="range" data-bs min="' + a.toFixed(2) + '" max="' + Math.max(a + .1, b - .3).toFixed(2) + '" step="0.1" value="' + o.ost.toFixed(2) + '"></label>'
          + '<label>Länge<input type="range" data-bd min="0.5" max="' + mx.toFixed(2) + '" step="0.1" value="' + Math.min(mx, len(o) || mx).toFixed(2) + '"></label>'
          + '<div class="r"><button type="button" class="sn-b sm" data-bp>▶ Ansehen</button><button type="button" class="sn-b sm" data-bb>↩ Wieder eigener Clip</button><button type="button" class="sn-b sm del" data-bx>🗑 Entfernen</button></div></div>'; }).join('') : '';
      box.querySelectorAll('[data-bo]').forEach(el => {
        const o = bros.find(x => x.id === el.dataset.bo); if (!o) return;
        const info = () => { el.querySelector('[data-bl]').textContent = fmt1(len(o)) + ' lang · ab ' + fmt1(o.ost - a) + ' in diesem Clip'; };
        const peek = () => { stop(); pos = starts()[sel] + Math.max(0, o.ost - a); show(sel, Math.max(0, o.ost - a) + .05); timeUi(); };
        el.querySelector('[data-bs]').oninput = e => { o.ost = +e.target.value; info(); peek(); };
        el.querySelector('[data-bd]').oninput = e => { o.out = Math.min(o.dur || 1e9, (o.in || 0) + +e.target.value); info(); peek(); };
        el.querySelectorAll('input').forEach(i => i.onchange = () => { dbPut(dbRow(o)); schedCache = null; });
        el.querySelector('[data-bp]').onclick = () => { stop(); const off = Math.max(0, o.ost - a - 1.5); pos = starts()[sel] + off; show(sel, off); play(); };
        el.querySelector('[data-bb]').onclick = async () => { stop(); bros = bros.filter(x => x !== o); const v = ovV.get(o.id); if (v) { v.pause(); v.remove(); ovV.delete(o.id); } o.bro = false; o.osrc = null; o.ost = 0; o.mute = false; clips.splice(sel + 1, 0, o); await dbPut(dbRow(o)); await saveOrder(); sel++; ui(); thumb(o); say('↩ „' + o.name + '“ ist wieder ein eigener Clip (Clip ' + (sel + 1) + ').', 'ok'); };
        el.querySelector('[data-bx]').onclick = async () => { stop(); bros = bros.filter(x => x !== o); const v = ovV.get(o.id); if (v) { v.pause(); v.remove(); ovV.delete(o.id); } o.trash = Date.now(); await dbPut(dbRow(o)); if (!clips.some(c2 => c2.url === o.url) && !bros.some(x => x.url === o.url)) URL.revokeObjectURL(o.url); ui(); trashUi(); say('🗑 Bild darüber liegt im Papierkorb.'); };
      });
    }
    K('broMk').onclick = async () => {
      if (busy || sel < 1) return; stop(); const c = clips[sel], P = clips[sel - 1];
      const k = await ensureSrc(P), pa = P.in || 0, pb = P.out || P.dur || 0;
      c.bro = true; c.osrc = k; c.ost = Math.max(pa, pb - len(c)); c.mute = true;
      clips.splice(sel, 1); bros.push(c); sel--; await dbPut(dbRow(c)); await saveOrder();
      ui(); ovVid(c);
      say('🎞 „' + c.name + '“ liegt jetzt als Bild über Clip ' + (sel + 1) + '. Die Stimme läuft darunter weiter. Mit „Beginnt bei“ schiebst du es an die richtige Stelle.', 'ok');
    };
    K('broFile').onchange = async e => {
      const f = e.target.files[0]; e.target.value = ''; const c = clips[sel]; if (!f || !c) return; stop();
      const k = await ensureSrc(c), v = cur();
      const o = { id: uid(), blob: f, name: f.name.replace(/\.\w+$/, ''), added: Date.now(), in: 0, out: 0, mute: true, dur: 0, draft: curDraft, bro: true, osrc: k, ost: v ? Math.max(c.in || 0, Math.min(v.currentTime, (c.out || c.dur || 0) - .5)) : (c.in || 0) };
      o.url = URL.createObjectURL(f); bros.push(o); await dbPut(dbRow(o)); ovVid(o); ui();
      say('🎞 „' + o.name + '“ liegt als Bild über Clip ' + (sel + 1) + ', ab der Stelle, an der du gerade warst. Die Stimme läuft weiter.', 'ok');
    };
    // Vorschau über WebGL, sobald Kino-Look, Kino-Balken oder Fischauge dabei sind (geht mit CSS nicht) – zeigt genau den Export
    const glc = document.createElement('canvas'); glc.className = 'sn-glc'; pv.insertBefore(glc, big); let glDraw = null, glMt = -1, segWarned = false;
    function needGL() { const c = clips[sel]; return !cmp && !!c && ((st.cin || 0) > 0 || !!st.lb || (c.fe || 0) > 0 || (st.bb || 0) > 0) && !K('covimg').classList.contains('on'); }
    (function glLoop() { if (closed) return; requestAnimationFrame(glLoop);
      if (!needGL()) { if (glc.style.display !== 'none') glc.style.display = 'none'; return; }
      const c = clips[sel], v = vids.get(c.slot); if (!v || v.readyState < 2 || !v.videoWidth) return;
      const W = Math.min(1280, Math.round(pv.clientWidth * (window.devicePixelRatio || 1))), H = Math.round(W * pv.clientHeight / Math.max(1, pv.clientWidth));
      if (glc.width !== W || glc.height !== H) { glc.width = W; glc.height = H; glDraw = null; } if (!glDraw) glDraw = makeGL(glc); if (!glDraw) return;
      const bo = broAt(c, v.currentTime), ov = bo ? ovV.get(bo.id) : null;
      if ((st.bb || 0) > 0 && !sgBusy && (!v.paused || v.currentTime !== glMt)) { sgBusy = true; glMt = v.currentTime; segMask(v, v.videoWidth, v.videoHeight).then(m => { sgLast = m; sgBusy = false; }).catch(() => { sgBusy = false; if (!segWarned) { segWarned = true; say('„Hintergrund weich“ konnte die Personen-Erkennung nicht laden. Bitte kurz prüfen, ob das Internet da ist.', 'bad'); } }); }
      const okD = (ov && ov.readyState >= 2 && ov.videoWidth) ? glDraw(ov, ov.videoWidth, ov.videoHeight, 1) : glDraw(v, v.videoWidth, v.videoHeight, zAt(c, Math.max(0, v.currentTime - (c.in || 0))), false, c.fr, c.fe, sgLast);
      glc.style.display = okD ? 'block' : 'none'; })();
    function vtf(c, off) {
      const z = c.zoom ? zAt(c, off) : 1; if (!c.fr && z === 1) return '';
      const f = frVals(c.fr, (pv.clientWidth || 16) / (pv.clientHeight || 9));
      return 'rotate(' + (f.th * 180 / Math.PI).toFixed(2) + 'deg) scale(' + (f.Z * z).toFixed(4) + ') translate(' + (-f.ox * 100).toFixed(3) + '%,' + (-f.oy * 100).toFixed(3) + '%)';
    }
    const frEls = () => [...root.querySelectorAll('[data-fr]')];
    function frUi() {
      const c = clips[sel], box = K('fr'); if (!c || box.hidden) return; const F = c.fr || { z: 1, x: 0, y: 0, r: 0 };
      K('frT').textContent = 'Bildausschnitt · Clip ' + (sel + 1);
      frEls().forEach(i => { const k = i.dataset.fr; i.value = k === 'z' ? Math.round((F.z || 1) * 100) : k === 'r' ? (F.r || 0) : Math.round((F[k] || 0) * 100); });
      const zmk = !c.zoom ? 'none' : c.zm === 'punch' ? 'punch' : c.zoom[1] < c.zoom[0] ? 'out' : c.zm === 'ease' ? 'slow' : '';
      K('zmv').querySelectorAll('[data-zm]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.zm === zmk)));
      K('fe').value = Math.round((c.fe || 0) * 100); K('feV').textContent = c.fe ? Math.round(c.fe * 100) + ' %' : 'aus';
      K('frZv').textContent = Math.round((F.z || 1) * 100) + ' %'; K('frRv').textContent = (+(F.r || 0)).toFixed(1).replace('.', ',') + '°';
    }
    let frT = 0;
    function frSet(c, F, save) {
      const empty = (F.z || 1) <= 1.001 && !F.x && !F.y && !F.r; c.fr = empty ? null : { z: F.z || 1, x: F.x || 0, y: F.y || 0, r: F.r || 0 };
      const v = vids.get(c.slot); if (v && clips[sel] === c) v.style.transform = vtf(c, Math.max(0, v.currentTime - (c.in || 0)));
      clearTimeout(frT); if (save !== false) frT = setTimeout(() => dbPut(dbRow(c)).catch(() => {}), 300);
    }
    K('frBtn').onclick = () => { const b = K('fr'); b.hidden = !b.hidden; K('frBtn').setAttribute('aria-expanded', String(!b.hidden)); pv.classList.toggle('frmode', !b.hidden); frUi(); };
    frEls().forEach(i => i.oninput = () => { const c = clips[sel]; if (!c) return; const F = Object.assign({ z: 1, x: 0, y: 0, r: 0 }, c.fr || {}), k = i.dataset.fr;
      F[k] = k === 'z' ? +i.value / 100 : k === 'r' ? +i.value : +i.value / 100; frSet(c, F); frUi(); });
    const ZM = { none: [null, null], slow: [[1, 1.15], 'ease'], punch: [[1, 1.25], 'punch'], out: [[1.15, 1], 'ease'] };
    K('zmv').querySelectorAll('[data-zm]').forEach(b => b.onclick = () => { const c = clips[sel]; if (!c) return; const z = ZM[b.dataset.zm]; c.zoom = z[0] ? z[0].slice() : null; c.zm = z[1]; dbPut(dbRow(c)).catch(() => {}); frUi();
      if (c.zoom) { stop(); pos = starts()[sel]; show(sel, 0); play(); } else show(sel, 0, false); });
    K('fe').oninput = () => { const c = clips[sel]; if (!c) return; c.fe = +K('fe').value / 100; frUi(); clearTimeout(frT); frT = setTimeout(() => dbPut(dbRow(c)).catch(() => {}), 300); };
    K('frReset').onclick = () => { const c = clips[sel]; if (!c) return; c.fe = 0; c.zoom = null; c.zm = null; frSet(c, {}); frUi(); say('Ausschnitt zurückgesetzt.'); };
    K('frAll').onclick = async () => { const c = clips[sel]; if (!c) return; for (const x of clips) { x.fr = c.fr ? Object.assign({}, c.fr) : null; x.fe = c.fe || 0; x.zoom = c.zoom ? c.zoom.slice() : null; x.zm = c.zm || null; await dbPut(dbRow(x)); } show(sel, Math.max(0, (cur() ? cur().currentTime : 0) - (c.in || 0)), false); say('✓ Gleicher Ausschnitt für alle ' + clips.length + ' Clips.', 'ok'); };
    // Bild direkt in der Vorschau verschieben (Ziehen) und zoomen (Mausrad / zwei Finger)
    { let d = null; const pts = new Map();
      pv.addEventListener('pointerdown', e => { if (K('fr').hidden || e.target.tagName !== 'VIDEO' || !clips[sel]) return; pts.set(e.pointerId, [e.clientX, e.clientY]); try { pv.setPointerCapture(e.pointerId); } catch (er) {}
        const c = clips[sel], F = Object.assign({ z: 1, x: 0, y: 0, r: 0 }, c.fr || {}); d = { c, F0: F, x0: e.clientX, y0: e.clientY, dist: 0 };
        if (pts.size === 2) { const [a, b] = [...pts.values()]; d.dist = Math.hypot(a[0] - b[0], a[1] - b[1]); } e.preventDefault(); });
      pv.addEventListener('pointermove', e => { if (!d || !pts.has(e.pointerId)) return; pts.set(e.pointerId, [e.clientX, e.clientY]); const F = Object.assign({}, d.F0);
        if (pts.size >= 2 && d.dist) { const [a, b] = [...pts.values()]; F.z = Math.max(1, Math.min(3, d.F0.z * Math.hypot(a[0] - b[0], a[1] - b[1]) / d.dist)); }
        else { const fv = frVals(F, pv.clientWidth / pv.clientHeight), m = (1 - 1 / (F.z || 1)) / 2; if (m > .001) {
          F.x = Math.max(-1, Math.min(1, d.F0.x - (e.clientX - d.x0) / (pv.clientWidth * fv.Z) / m)); F.y = Math.max(-1, Math.min(1, d.F0.y - (e.clientY - d.y0) / (pv.clientHeight * fv.Z) / m)); } }
        frSet(d.c, F); frUi(); });
      const up = e => { pts.delete(e.pointerId); if (!pts.size) d = null; else if (d) { const c = d.c; d = { c, F0: Object.assign({ z: 1, x: 0, y: 0, r: 0 }, c.fr || {}), x0: [...pts.values()][0][0], y0: [...pts.values()][0][1], dist: 0 }; } };
      pv.addEventListener('pointerup', up); pv.addEventListener('pointercancel', up);
      pv.addEventListener('wheel', e => { if (K('fr').hidden || !clips[sel]) return; e.preventDefault(); const c = clips[sel], F = Object.assign({ z: 1, x: 0, y: 0, r: 0 }, c.fr || {}); F.z = Math.max(1, Math.min(3, F.z * Math.exp(-e.deltaY * .0015))); frSet(c, F); frUi(); }, { passive: false });
    }
    // ---- Papierkorb (Vivien, 30.10.2026: „gelöschte Clips erst endgültig weg, wenn ich endgültig löschen sage“) ----
    async function trashUi() {
      if (closed) return; const all = await dbAll().catch(() => []), now = Date.now(), T = [];
      for (const r of all) { if (!r.trash) continue; if (now - r.trash > 30 * 864e5) { await dbDel(r.id).catch(() => {}); continue; } T.push(r); }
      T.sort((a, b) => b.trash - a.trash); const box = K('trash'); box.hidden = !T.length; K('trN').textContent = T.length ? '· ' + T.length : '';
      const when = t => { const d = new Date(t); return d.getDate() + '.' + (d.getMonth() + 1) + '. ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); };
      K('trL').innerHTML = '<ul>' + T.map(r => '<li><span>' + (r.bro ? '🎞 ' : '🎬 ') + esc(r.name || 'Clip') + '<small>' + fmt1(Math.max(0, (r.out || r.dur || 0) - (r.in || 0))) + ' · gelöscht ' + when(r.trash) + (r.draft !== curDraft ? ' · aus „' + esc(r.tname || drName(r.draft)) + '“' : '') + '</small></span>'
        + '<button type="button" class="sn-b sm" data-tr="' + r.id + '">↩ Zurückholen</button><button type="button" class="sn-b sm del" data-tx="' + r.id + '">Endgültig löschen</button></li>').join('') + '</ul>';
      K('trL').querySelectorAll('[data-tr]').forEach(b => b.onclick = () => trashBack(b.dataset.tr));
      K('trL').querySelectorAll('[data-tx]').forEach(b => b.onclick = async () => { if (!await ask('Diesen Clip endgültig löschen? Das geht nicht mehr rückgängig.')) return; await dbDel(b.dataset.tx); trashUi(); drawDrafts(); say('Endgültig gelöscht.'); });
    }
    async function rescueUi() {
      if (closed) return; let L = []; try { L = await orphanFiles(); } catch (e) {} const box = K('resc'); box.hidden = !L.length; K('rsN').textContent = L.length ? '· ' + L.length : '';
      K('rsL').innerHTML = '<ul>' + L.map((x, i) => '<li><span>🎬 Video ' + (i + 1) + '<small>' + (x.blob.size / 1048576).toFixed(0) + ' MB · ' + esc((x.blob.type || '').replace('video/', '') || 'Video') + '</small><video controls playsinline preload="metadata" style="width:100%;max-width:320px;border-radius:12px;margin-top:6px;display:block" data-rv="' + i + '"></video></span><button type="button" class="sn-b sm" data-rb="' + i + '">↩ In diesen Entwurf holen</button></li>').join('') + '</ul>';
      K('rsL').querySelectorAll('[data-rv]').forEach(v => { v.src = URL.createObjectURL(L[+v.dataset.rv].blob); });
      K('rsL').querySelectorAll('[data-rb]').forEach(b => b.onclick = async () => { const x = L[+b.dataset.rb]; stop(); fidOf.set(x.blob, x.fid);
        const c = { id: uid(), blob: x.blob, name: 'Gerettetes Video ' + (+b.dataset.rb + 1), added: Date.now(), in: 0, out: 0, mute: false, dur: 0, draft: curDraft, fx: 1 }; c.src = c.id; c.url = URL.createObjectURL(x.blob);
        clips.push(c); await dbPut(dbRow(c)); await saveOrder(); slots(); vidFor(c); thumb(c); sel = clips.length - 1; ui(); rescueUi(); say('↩ Das Video ist zurück (Clip ' + clips.length + ').', 'ok'); });
    }
    async function trashBack(id) {
      if (busy) return; stop(); const r = (await dbAll()).find(x => x.id === id); if (!r) return;
      const same = r.draft === curDraft; r.trash = 0; r.draft = curDraft; await dbPut(r);
      if (!r.bro) { const ord = clips.map(c => c.id), at = same ? Math.min(r.tpos || 0, ord.length) : ord.length; ord.splice(at, 0, r.id); await kvSet(ordKey(curDraft), ord); }
      clips.forEach(c => { if (!clips.some(x => x !== c && x.url === c.url)) URL.revokeObjectURL(c.url); }); broClear(); clips = []; dropVids(); await load();
      const k = clips.findIndex(c => c.id === id); if (k >= 0) { sel = k; pos = starts()[k]; ui(); }
      say('↩ „' + (r.name || 'Clip') + '“ ist zurück' + (k >= 0 ? ' (Clip ' + (k + 1) + ')' : '') + '.', 'ok');
    }
    K('trEmpty').onclick = async () => { const all = await dbAll(), T = all.filter(r => r.trash); if (!T.length || !await ask('Papierkorb mit ' + T.length + ' Clips endgültig leeren? Das geht nicht mehr rückgängig.')) return; for (const r of T) await dbDel(r.id); trashUi(); drawDrafts(); say('Papierkorb geleert.'); };
    async function delAt(i) {
      const c = clips[i]; if (!c) return; stop();
      c.trash = Date.now(); c.tpos = i; await dbPut(dbRow(c)); clips.splice(i, 1); slots(); dropVids();
      if (!clips.some(x => x.url === c.url)) URL.revokeObjectURL(c.url);
      if (i < sel) sel--; sel = Math.min(sel, clips.length - 1);
      await saveOrder(); pos = sel >= 0 ? starts()[sel] : 0; ui();
    }
    K('ztDel').onclick = () => K('delClip').onclick();
    K('delClip').onclick = async () => { if (!clips[sel]) return; await delAt(sel); trashUi(); say('🗑 Clip liegt im Papierkorb. Von dort kannst du ihn zurückholen.'); };

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
    const SL = { br: ['Helligkeit', .6, 1.5, .01, x => Math.round((x - 1) * 100) > 0 ? '+' + Math.round((x - 1) * 100) : String(Math.round((x - 1) * 100))], ct: ['Kontrast', .6, 1.5, .01, x => (x > 1 ? '+' : '') + Math.round((x - 1) * 100)], sa: ['Farbe', 0, 1.6, .01, x => Math.round(x * 100) + ' %'], se: ['Wärme', 0, .6, .01, x => x > .02 ? 'wärmer' : 'neutral'], bb: ['Hintergrund weich (du bleibst scharf)', 0, 1, .01, x => x > .005 ? Math.round(x * 100) + ' %' : 'aus'],
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
    K('lbx').checked = !!st.lb; K('lbx').onchange = () => { st.lb = K('lbx').checked; ls.set('sn-st', st); };
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
      const D = total() + CD();
      if (schedCache && Math.abs(schedD - D) < .05) return schedCache;
      schedD = D; const out = [];
      if (mlist.length && D > .2) { let t = 0, k = 0;
        while (t < D - .05 && out.length < 60) { const m = mlist[k % mlist.length], d = Math.max(1, m.dur || 30);
          out.push({ m, start: t, dur: d }); t += Math.max(1, d - xfOf(d)); k++; } }
      return (schedCache = out);
    }
    // Wie laut läuft welches Lied zur Zeit t (0…1, noch ohne Lautstärke-Regler)?
    function musPlan(t) {
      const D = schedD < 0 ? total() + CD() : schedD, out = [];
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
      const box = K('mlist'), D = total() + CD();
      if (!mlist.length) { box.innerHTML = '<div class="sn-note">Noch keine Musik. Nimm unten ein oder mehrere Lieder – ich schneide sie auf die Länge deines Videos zu und blende sie ineinander.</div>'; return; }
      const ges = mlist.reduce((a, m) => a + (m.dur || 0), 0);
      box.innerHTML = '<ul>' + mlist.map((m, i) => '<li><b class="n">' + (i + 1) + '</b><span>' + esc(m.name) + '<small>' + (m.dur ? fmt(m.dur) : '') + '</small></span>'
        + (i ? '<button type="button" data-mup="' + i + '" aria-label="nach oben">↑</button>' : '')
        + '<button type="button" data-mx="' + i + '" aria-label="entfernen">✕</button></li>').join('') + '</ul>'
        + '<div class="sn-note">' + (mlist.length > 1 ? 'Die Lieder laufen in dieser Reihenfolge und blenden ineinander. ' : '')
        + (ges < D - .5 ? 'Zusammen ' + fmt(ges) + ' – dein Video ist ' + fmt(D) + ' lang, danach fange ich wieder vorne an.' : 'Reicht für dein ganzes Video (' + fmt(D) + ').') + '</div>';
      box.querySelectorAll('[data-mx]').forEach(b => b.onclick = () => { const i = +b.dataset.mx; URL.revokeObjectURL(mlist[i].url); mlist.splice(i, 1); schedCache = null; musStop(); mlUi(); libUi(); sugUi(); });
      box.querySelectorAll('[data-mup]').forEach(b => b.onclick = () => { const i = +b.dataset.mup; mlist.splice(i - 1, 0, mlist.splice(i, 1)[0]); schedCache = null; musStop(); mlUi(); });
    }
    // ---- Vorschlag (Vivien, 30.10.2026: „ich horche mir die nicht an, ich klicke die ersten 4, 5, 6 an“) ----
    // Welche Ordner passen zu diesem Entwurf? (gleiche Logik wie im Studio)
    function wishTags() {
      const t = String(drName(curDraft) || '').toLowerCase(), d = new Date();
      const wi = (d.getMonth() === 10 && d.getDate() >= 15) || d.getMonth() === 11;
      if (/reel|story|insta/.test(t)) return wi ? ['winter', 'reel', 'lebendig'] : ['reel', 'lebendig', 'morgen'];
      if (/abend|nacht|schlaf|meditation|atem|breath|ruhe|loslassen|savasana|evening|sleep|night/.test(t)) return ['meditation', 'abend'];
      if (/morgen|morning|willkommen|welcome|ankommen|start/.test(t)) return ['morgen', 'reel'];
      if (/übung|uebung|flow|bewegung|move|workout|kraft/.test(t)) return ['flow', 'morgen'];
      return ['flow', 'morgen', 'reel'];
    }
    let sugSeed = 0, sugList = [];
    function suggest() {
      const want = wishTags(), score = x => { const i = want.findIndex(t => (x.tags || []).includes(t)); return i < 0 ? 9 : i; };
      const pool = lib.filter(x => score(x) < 9), base = pool.length >= 3 ? pool : lib;
      const key = String(curDraft || '') + '|' + sugSeed; let h = 2166136261;
      for (let i = 0; i < key.length; i++) { h ^= key.charCodeAt(i); h = Math.imul(h, 16777619) >>> 0; }
      const rnd = () => (h = (Math.imul(h, 1664525) + 1013904223) >>> 0) / 4294967296;
      const ranked = base.map(x => ({ x, s: score(x), r: rnd() })).sort((a, b) => a.s - b.s || a.r - b.r).map(o => o.x);
      const D = total() + CD(), out = []; let t = 0;
      for (const x of ranked) { out.push(x); t += Math.max(20, (x.dur || 150) - XF); if (t >= D + 2 && out.length >= 3) break; }
      return out.slice(0, 8);
    }
    function sugUi() {
      const box = K('sug'); if (!o.musicUrl || !lib.length) { box.innerHTML = ''; return; }
      sugList = suggest(); const D = total() + CD(), drin = id => mlist.some(m => m.id === id);
      let t = 0, n = 0; for (const x of sugList) { n++; t += Math.max(20, (x.dur || 150) - XF); if (t >= D) break; }
      box.innerHTML = '<div class="hd"><b>✦ Vorschlag für dieses Video</b><button type="button" data-k2="sugnew">🎲 andere</button></div><ol>'
        + sugList.map(x => '<li class="' + (drin(x.id) ? 'on' : '') + '"><button type="button" data-spv="' + x.id + '" aria-label="Reinhören">' + (preId === x.id ? '❚❚' : '▶') + '</button><span>' + esc(x.t) + '<small>' + (x.dur ? fmt(x.dur) : '') + '</small></span><button type="button" data-sadd="' + x.id + '">' + (drin(x.id) ? '✓' : '＋') + '</button></li>').join('')
        + '</ol><button type="button" class="take" data-k2="sugtake">✨ ' + (n === 1 ? 'Das erste Lied nehmen' : 'Die ersten ' + n + ' nehmen') + ' – reicht für dein Video (' + fmt(D) + ')</button>';
      box.querySelector('[data-k2=sugnew]').onclick = () => { sugSeed++; sugUi(); };
      box.querySelector('[data-k2=sugtake]').onclick = async () => { const b = box.querySelector('[data-k2=sugtake]'); b.disabled = true; b.textContent = 'Lädt …';
        for (const x of sugList.slice(0, n)) { if (!mlist.some(m => m.id === x.id)) await takeSong(x); }
        say('🎵 ' + (n === 1 ? 'Das Lied liegt' : n + ' Lieder liegen') + ' jetzt darunter' + (n > 1 ? ' – sie blenden ineinander' : '') + '.', 'ok'); };
      box.querySelectorAll('[data-spv]').forEach(b => b.onclick = () => { const id = +b.dataset.spv; if (preId === id) { pre.pause(); preId = 0; } else { pre.src = o.musicUrl + id; pre.volume = .6; pre.play().catch(() => {}); preId = id; } sugUi(); libUi(); });
      box.querySelectorAll('[data-sadd]').forEach(b => b.onclick = async () => { const x = sugList.find(y => y.id === +b.dataset.sadd); if (!x || mlist.some(m => m.id === x.id)) return; b.disabled = true; b.textContent = '…'; await takeSong(x); });
    }
    // Lied aus der Bibliothek holen und in die Playlist legen
    async function takeSong(x) {
      try { const r = await fetch(o.musicUrl + x.id, { credentials: 'same-origin' }); const bl = await r.blob();
        const f = new File([bl], x.t + '.mp3', { type: bl.type || 'audio/mpeg' });
        pre.pause(); preId = 0; await addMusic(f, x.t, x.id); return true;
      } catch (e) { say('„' + x.t + '“ konnte nicht laden.', 'bad'); sugUi(); libUi(); return false; }
    }
    const fileDur = url => new Promise(ok => { const a = new Audio(); a.preload = 'metadata'; a.onloadedmetadata = () => ok(a.duration || 0); a.onerror = () => ok(0); a.src = url; });
    async function addMusic(file, name, id) {
      const url = URL.createObjectURL(file), d = await fileDur(url);
      mlist.push({ file, url, name: name || file.name.replace(/\.\w+$/, ''), id: id || 0, dur: d });
      schedCache = null; mlUi(); libUi(); sugUi();
    }
    K('mfile').onchange = async e => { const fs = [...e.target.files]; e.target.value = ''; for (const f of fs) await addMusic(f); if (fs.length) say('🎵 ' + (fs.length > 1 ? fs.length + ' Lieder liegen' : '„' + fs[0].name.replace(/\.\w+$/, '') + '“ liegt') + ' jetzt darunter.', 'ok'); };
    K('duck').checked = mus.duck; K('fade').checked = mus.fade;
    K('duck').onchange = () => { mus.duck = K('duck').checked; ls.set('sn-mus', mus); }; K('fade').onchange = () => { mus.fade = K('fade').checked; ls.set('sn-mus', mus); };
    function libUi() {
      const box = K('lib'); if (!o.musicUrl) { box.innerHTML = ''; K('allsum').textContent = ''; return; }
      if (!lib.length) { box.innerHTML = '<div class="sn-note">Noch keine Lieder im Musik-Ordner.</div>'; K('allsum').textContent = 'Alle Lieder'; return; }
      const f2 = s2 => s2 > 0 ? fmt(s2) : '', drin = id => mlist.some(m => m.id === id);
      const qs = String(K('msearch').value || '').trim().toLowerCase();
      const hit = qs ? lib.filter(x => x.t.toLowerCase().includes(qs)) : lib;
      const show = hit.slice(0, 60);
      K('allsum').textContent = 'Alle Lieder durchsuchen (' + lib.length + ')';
      box.innerHTML = (hit.length ? '' : '<div class="sn-note">Nichts gefunden.</div>') + '<ul>' + show.map(x => '<li class="' + (drin(x.id) ? 'on' : '') + '"><button type="button" data-pv="' + x.id + '" aria-label="Reinhören">' + (preId === x.id ? '❚❚' : '▶') + '</button><span>' + esc(x.t) + '<small>' + f2(x.dur) + '</small></span><button type="button" class="pk" data-take="' + x.id + '">' + (drin(x.id) ? '✓ drin · nochmal' : '＋ Nehmen') + '</button></li>').join('') + '</ul>'
        + (hit.length > show.length ? '<div class="sn-note">… und ' + (hit.length - show.length) + ' weitere. Tipp den Namen oben ein, um sie zu finden.</div>' : '');
      box.querySelectorAll('[data-pv]').forEach(b => b.onclick = () => { const id = +b.dataset.pv; if (preId === id) { pre.pause(); preId = 0; } else { pre.src = o.musicUrl + id; pre.volume = .6; pre.play().catch(() => {}); preId = id; } libUi(); sugUi(); });
      box.querySelectorAll('[data-take]').forEach(b => b.onclick = async () => { const x = lib.find(y => y.id === +b.dataset.take); b.disabled = true; b.textContent = 'Lädt …'; if (await takeSong(x)) say('🎵 „' + x.t + '“ liegt jetzt darunter.', 'ok'); });
    }
    K('msearch').oninput = () => libUi();
    mlUi();
    if (o.musicUrl) fetch(o.musicUrl.replace(/&dl=$/, '&json=1'), { credentials: 'same-origin' }).then(r => r.json()).then(j => { lib = j.list || []; libTags = j.tags || {}; libUi(); sugUi(); }).catch(() => { K('lib').innerHTML = ''; });
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
      const at = (await pickAudio(input)).dec; if (!at) return null;
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
          r.segs.forEach((sg, k) => next.push({ id: uid(), fr: c.fr || null, fe: c.fe || 0, src: c.src || c.id, blob: c.blob, url: c.url, name: c.name, added: c.added + k, in: a0 + sg.s, out: a0 + sg.e, mute: !!c.mute, dur: c.dur, auto: true, zoom: null, draft: curDraft }));
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
    // WebGL-Filter (gleiche Rechnung wie die CSS-Filter der Vorschau); neutral = ohne Licht & Farbe (Titelbild)
    function makeGL(cv) {
      const gl = cv.getContext('webgl', { preserveDrawingBuffer: true, premultipliedAlpha: false }); if (!gl) return null;
      const sh = (t, s) => { const x = gl.createShader(t); gl.shaderSource(x, s); gl.compileShader(x); return x; };
      const P = gl.createProgram(); gl.attachShader(P, sh(gl.VERTEX_SHADER, VS)); gl.attachShader(P, sh(gl.FRAGMENT_SHADER, FS)); gl.linkProgram(P); gl.useProgram(P);
      const bf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, bf); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
      const loc = gl.getAttribLocation(P, 'p'); gl.enableVertexAttribArray(loc); gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
      const U = {}; ['t', 'so', 'oo', 'br', 'ct', 'sa', 'se', 'zm', 'fz', 'fr', 'fo', 'fe', 'cin', 'lb', 'bb', 'mk'].forEach(n => U[n] = gl.getUniformLocation(P, n));
      const tex = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, tex);
      [[gl.TEXTURE_MIN_FILTER, gl.LINEAR], [gl.TEXTURE_MAG_FILTER, gl.LINEAR], [gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE], [gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE]].forEach(x => gl.texParameteri(gl.TEXTURE_2D, x[0], x[1]));
      gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
      const mtex = gl.createTexture(); gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, mtex);
      [[gl.TEXTURE_MIN_FILTER, gl.LINEAR], [gl.TEXTURE_MAG_FILTER, gl.LINEAR], [gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE], [gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE]].forEach(x => gl.texParameteri(gl.TEXTURE_2D, x[0], x[1]));
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.LUMINANCE, 1, 1, 0, gl.LUMINANCE, gl.UNSIGNED_BYTE, new Uint8Array([255])); gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, tex);
      return (el, sw, shh, z, neutral, F, fe, M) => {
        const useB = !neutral && (st.bb || 0) > 0 && M && M.data;
        if (useB) { gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, mtex); gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1); gl.texImage2D(gl.TEXTURE_2D, 0, gl.LUMINANCE, M.w, M.h, 0, gl.LUMINANCE, gl.UNSIGNED_BYTE, M.data); gl.pixelStorei(gl.UNPACK_ALIGNMENT, 4); gl.activeTexture(gl.TEXTURE0); } gl.viewport(0, 0, cv.width, cv.height); gl.bindTexture(gl.TEXTURE_2D, tex); try { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, el); } catch (e) { return false; }
        const L = neutral ? { br: 1, ct: 1, sa: 1, se: 0, cin: 0, lb: 0 } : st;
        gl.uniform1i(U.t, 0); gl.uniform2f(U.so, sw, shh); gl.uniform2f(U.oo, cv.width, cv.height); gl.uniform1f(U.br, L.br); gl.uniform1f(U.ct, L.ct); gl.uniform1f(U.sa, L.sa); gl.uniform1f(U.se, L.se); const fv = frVals(F, cv.width / cv.height); gl.uniform1f(U.fz, fv.Z); gl.uniform1f(U.fr, fv.th); gl.uniform2f(U.fo, fv.ox, -fv.oy); gl.uniform1i(U.mk, 1); gl.uniform1f(U.bb, useB ? Math.min(1, st.bb) : 0); gl.uniform1f(U.fe, neutral ? 0 : Math.max(0, Math.min(1, +fe || 0))); gl.uniform1f(U.cin, +L.cin || 0); gl.uniform1f(U.lb, L.lb ? 1 : 0); gl.uniform1f(U.zm, z || 1); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4); return true; };
    }
    // ---- Ton der Clips holen (Vivien, 30.10.2026: „beim Exportieren ist der Ton weg. Komplett.“) ----
    // Am iPhone (Safari bis iOS 18) gibt es keinen AudioDecoder/AudioEncoder. Früher wurde die Stimme dann still übersprungen
    // und das Video ganz ohne Tonspur gespeichert. Jetzt drei Wege, und wenn keiner klappt, der Echtzeit-Weg (nimmt den Ton mit):
    // 1) WebCodecs, 2) Tonspur ohne Neu-Kodieren in eine kleine Audiodatei umpacken und vom Browser entschlüsseln lassen,
    // 3) die ganze Datei vom Browser entschlüsseln lassen.
    const decodeAB = (ab, SR) => new Promise((ok, no) => { const ctx = new OfflineAudioContext(2, 1, SR); let fin = false;
      const r = ctx.decodeAudioData(ab, b => { if (!fin) { fin = true; ok(b); } }, e => { if (!fin) { fin = true; no(e || new Error('decode')); } });
      if (r && r.then) r.then(b => { if (!fin) { fin = true; ok(b); } }, e => { if (!fin) { fin = true; no(e); } }); });
    // Welche Tonspur? Neuere iPhones speichern zuerst eine „räumliche“ Tonspur (APAC), die Browser nicht lesen können,
    // und daneben eine normale Stereo-Spur (AAC). Früher wurde nur die erste genommen → kein Ton. Jetzt: die erste lesbare, AAC bevorzugt.
    async function pickAudio(input) {
      let list = []; try { list = await input.getAudioTracks(); } catch (e) {}
      if (!list.length) { try { const p = await input.getPrimaryAudioTrack(); if (p) list = [p]; } catch (e) {} }
      if (!list.length) return { dec: null, raw: null };
      const rank = t => ({ aac: 0, opus: 1, mp3: 2, vorbis: 3, flac: 4 }[t.codec] ?? (/^pcm/.test(t.codec || '') ? 5 : 9));
      const sorted = list.slice().sort((a, b) => rank(a) - rank(b));
      let dec = null; for (const t of sorted) { try { if (await t.canDecode()) { dec = t; break; } } catch (e) {} }
      return { dec, raw: sorted.find(t => rank(t) < 9) || sorted[0] };
    }
    // Mikrofon-Ton aufräumen (Vivien, 30.10.2026: „Ton über mein Rode-Mikrofon, am MacBook ging es, hochgeladen war er weg“):
    // Externe Mikros liefern oft zwei Kanäle, bei denen einer leer ist oder beide gegenläufig sind (dann löscht sich die Stimme
    // aus, sobald ein Handy oder Player auf Mono zusammenrechnet). Deshalb: Stimme je Clip auf einen sauberen Mono-Kanal bringen
    // (lauter Kanal, oder Mittel der aktiven Kanäle; gegenläufig → nur einer) und auf beide Seiten legen.
    function voiceMode(pieces) {
      let nch = 0; pieces.forEach(p => nch = Math.max(nch, p.buffer.numberOfChannels));
      const sq = new Float64Array(Math.max(1, nch)); let xy = 0, n = 0;
      pieces.forEach(p => { const b = p.buffer, L = b.length, step = Math.max(1, Math.floor(L / 4000)), ch = []; for (let k = 0; k < b.numberOfChannels; k++) ch.push(b.getChannelData(k));
        for (let i = 0; i < L; i += step) { for (let k = 0; k < ch.length; k++) sq[k] += ch[k][i] * ch[k][i]; if (ch.length > 1) xy += ch[0][i] * ch[1][i]; n++; } });
      const rms = [...sq].map(v => Math.sqrt(v / Math.max(1, n))), mx = Math.max(...rms);
      if (nch < 2 || mx < 1e-6) return { use: [0], rms: mx };
      const act = rms.map((r, k) => r >= mx * .15 ? k : -1).filter(k => k >= 0);
      const corr = (rms[0] && rms[1]) ? (xy / Math.max(1, n)) / (rms[0] * rms[1]) : 0;
      if (act.includes(0) && act.includes(1) && corr < -.3) return { use: [0], inv: true, rms: mx };
      return { use: act, rms: mx, one: act.length === 1 };
    }
    function toMono(b, use) {
      const out = new AudioBuffer({ numberOfChannels: 1, length: b.length, sampleRate: b.sampleRate }), d = out.getChannelData(0), ks = use.filter(k => k < b.numberOfChannels);
      if (!ks.length) ks.push(0); const f = 1 / ks.length; for (const k of ks) { const s0 = b.getChannelData(k); for (let i = 0; i < d.length; i++) d[i] += s0[i] * f; }
      return out;
    }
    async function voicePieces(M, input, c, SR, whole) { // → { pieces: [{buffer, ts}], fail } · ts = Startzeit im Ausgangsvideo
      const a = c.in || 0, b = c.out || c.dur || 0;
      const pk = await pickAudio(input), at = pk.raw;
      if (!at) return { pieces: [] }; // Clip ohne Tonspur: nichts zu tun
      try { if (pk.dec) { const as = new M.AudioBufferSink(pk.dec), out = [];
          for await (const x of as.buffers(Math.max(0, a - 1), b + 1)) { if (closed) return null; out.push({ buffer: x.buffer, ts: x.timestamp }); }
          if (out.length) return { pieces: out }; } } catch (e) { console.warn('Ton (WebCodecs)', e); }
      try { const sink = new M.EncodedPacketSink(at); const p0 = (await sink.getPacket(Math.max(0, a - .5))) || (await sink.getFirstPacket());
        if (p0) { const codec = at.codec, cfg = await at.getDecoderConfig();
          const tgt = new M.BufferTarget(), op = new M.Output({ format: (codec === 'opus' || codec === 'vorbis') ? new M.WebMOutputFormat() : new M.Mp4OutputFormat(), target: tgt });
          const src = new M.EncodedAudioPacketSource(codec); op.addAudioTrack(src); await op.start();
          let first = true, n = 0; const t0 = p0.timestamp;
          for await (const p of sink.packets(p0)) { if (closed) return null; if (p.timestamp > b + .5) break;
            await src.add(new M.EncodedPacket(p.data, p.type, Math.max(0, p.timestamp - t0), p.duration), first ? { decoderConfig: cfg } : undefined); first = false; n++; }
          await op.finalize();
          if (n && tgt.buffer) return { pieces: [{ buffer: await decodeAB(tgt.buffer, SR), ts: t0 }] }; } } catch (e) { console.warn('Ton (umpacken)', e); }
      try { let buf = whole.get(c.blob); if (!buf && c.blob.size < 450e6) { buf = await decodeAB(await c.blob.arrayBuffer(), SR); whole.set(c.blob, buf); } if (buf) return { pieces: [{ buffer: buf, ts: 0 }] }; } catch (e) { console.warn('Ton (ganze Datei)', e); }
      return { pieces: [], fail: true };
    }
    let lastVoice = null; // { quiet, none, fixed } vom letzten Fertigstellen
    async function mixAudio(M, inputs, D, SR) { // inputs: [{input, c, start}] → { buf: AudioBuffer|null } oder { fail } oder null (abgebrochen)
      const N = Math.max(1, Math.ceil(D * SR)), segs = [], whole = new Map(); let fail = 0, k = 0, voiceN = 0, voiceMax = 0, voiceFix = 0;
      for (const x of inputs) { k++; if (x.c.mute) continue; if (inputs.length > 1) say('Ton wird vorbereitet … ' + k + ' von ' + inputs.length);
        const r = await voicePieces(M, x.input, x.c, SR, whole); if (r === null) return null; if (r.fail) { fail++; continue; }
        if (r.pieces.length) { const vm = voiceMode(r.pieces); voiceN++; if ((vm.rms || 0) > voiceMax) voiceMax = vm.rms || 0; if (vm.inv || vm.one) voiceFix++;
          r.pieces = r.pieces.map(p => ({ buffer: toMono(p.buffer, vm.use), ts: p.ts })); }
        const a = x.c.in || 0, e = x.c.out || x.c.dur || 0;
        r.pieces.forEach(p => { const s0 = Math.max(p.ts, a), s1 = Math.min(p.ts + p.buffer.duration, e);
          if (s1 - s0 > .001) segs.push({ buffer: p.buffer, when: x.start + (s0 - a), off: s0 - p.ts, dur: s1 - s0, cs: x.start, ce: x.start + (e - a) }); }); }
      if (fail) return { fail };
      lastVoice = { quiet: voiceN > 0 && voiceMax < .004, none: !voiceN && inputs.some(x => !x.c.mute), fixed: voiceFix };
      if (!segs.length && !mlist.length) return { buf: null };
      const place = (ctx, dst) => { const gains = new Map(); segs.forEach(sg => { let g = gains.get(sg.cs); if (!g) { // je Clip 20 ms ein-/ausblenden, damit die Schnitte nicht knacken
          g = new GainNode(ctx, { gain: 0 }); g.connect(dst); const f = Math.min(.02, (sg.ce - sg.cs) / 4); g.gain.setValueAtTime(0, sg.cs); g.gain.linearRampToValueAtTime(1, sg.cs + f); g.gain.setValueAtTime(1, Math.max(sg.cs + f, sg.ce - f)); g.gain.linearRampToValueAtTime(0, sg.ce); gains.set(sg.cs, g); }
        const s0 = new AudioBufferSourceNode(ctx, { buffer: sg.buffer }); s0.connect(g); s0.start(sg.when, sg.off, sg.dur); }); };
      let rms = null;
      if (mlist.length && mus.duck && segs.length) { const c1 = new OfflineAudioContext(1, N, SR); place(c1, c1.destination); const r1 = (await c1.startRendering()).getChannelData(0); const win = Math.round(SR * .05); rms = []; for (let i = 0; i < r1.length; i += win) { let s = 0, n = Math.min(win, r1.length - i); for (let j = 0; j < n; j++) s += r1[i + j] * r1[i + j]; rms.push(Math.sqrt(s / Math.max(1, n))); } }
      const c2 = new OfflineAudioContext(2, N, SR), vg = new GainNode(c2, { gain: mus.voice }); vg.connect(c2.destination); place(c2, vg);
      if (mlist.length) {
        // Alle gewählten Lieder einmal entschlüsseln, dann der Reihe nach legen und ineinander blenden
        const dec = new Map();
        for (const m of mlist) { if (dec.has(m.url)) continue; const b2 = await m.file.arrayBuffer().then(ab => decodeAB(ab, SR)).catch(() => null); if (b2) dec.set(m.url, b2); }
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
        if (any) { let duckT = -9, prev = 0; for (let k2 = 0, t = 0; t < D; k2++, t = k2 * .05) { let g = mus.vol; if (mus.fade) g *= Math.max(0, Math.min(1, t / 1.5, (D - t) / 2.5)); if (rms && (rms[k2] || 0) > .025) duckT = t; if (rms && t - duckT < .6) g *= .35; if (Math.abs(g - prev) > .001 || k2 === 0) { master.gain.setTargetAtTime(g, t, g < prev ? .12 : .45); prev = g; } } } }
      return { buf: await c2.startRendering() };
    }
    async function fastRender(fill) {
      if (!window.VideoEncoder || !window.VideoDecoder || !window.OfflineAudioContext) return null;
      const M = await loadMB(); const C0 = CD(), D = total() + C0, SR = 48000;
      const ins = []; let start = C0; for (const c of clips) { const input = new M.Input({ source: new M.BlobSource(c.blob), formats: M.ALL_FORMATS }); const vt = await input.getPrimaryVideoTrack(); if (!vt || !(await vt.canDecode())) return null; const rot = (await vt.getRotation?.()) ?? vt.rotation ?? 0; ins.push({ input, vt, rot, c, start }); start += len(c); }
      // Ausgabegröße nach dem ersten Clip (Drehung beachten)
      const f0 = ins[0]; let w0 = f0.vt.displayWidth || f0.vt.codedWidth, h0 = f0.vt.displayHeight || f0.vt.codedHeight; if (f0.rot % 180) [w0, h0] = [h0, w0];
      const [W, H] = outSize(w0, h0), cv = document.createElement('canvas'); cv.width = W; cv.height = H; const draw = makeGL(cv); if (!draw) return null;
      const vbr = brate(W, H), vc = await M.getFirstEncodableVideoCodec(['avc', 'hevc', 'vp9', 'av1'], { width: W, height: H, bitrate: vbr }); if (!vc) return null;
      say('Ton wird vorbereitet …'); const mx = await mixAudio(M, ins, D, SR); if (closed) return 'x'; if (!mx) return null;
      if (mx.fail) { console.warn('Schnitt: Ton nicht lesbar, nehme den Echtzeit-Weg'); return null; }
      const mixed = mx.buf;
      // Ton: AAC in MP4; am iPhone ohne Ton-Kodierer unkomprimiert in .mov (spielt in Fotos, Instagram und Bunny); sonst Opus
      let acodec = null, mov = false; const aopt = { numberOfChannels: 2, sampleRate: SR, bitrate: 160000 };
      if (mixed) {
        if (await M.canEncodeAudio('aac', aopt).catch(() => false)) acodec = 'aac';
        else if (!window.AudioEncoder) { acodec = 'pcm-s16'; mov = true; }
        else if (await M.canEncodeAudio('opus', aopt).catch(() => false)) acodec = 'opus';
        else return null; // kein Ton-Kodierer: lieber der Echtzeit-Weg, der den Ton mitnimmt
      }
      let parts = [], end = 0;
      const put = (p, data) => { const bl = new Blob([data]), pe = p + data.byteLength; if (p === end) { parts.push({ p, b: bl }); end = pe; return; } if (p > end) { parts.push({ p: end, b: new Blob([new Uint8Array(p - end)]) }, { p, b: bl }); end = pe; return; }
        const nx = []; for (const x of parts) { const xs = x.p, xe = x.p + x.b.size; if (xe <= p || xs >= pe) { nx.push(x); continue; } if (xs < p) nx.push({ p: xs, b: x.b.slice(0, p - xs) }); if (xe > pe) nx.push({ p: pe, b: x.b.slice(pe - xs) }); } nx.push({ p, b: bl }); nx.sort((x, y) => x.p - y.p); parts = nx; if (pe > end) end = pe; };
      const target = new M.StreamTarget(new WritableStream({ write(ch) { put(ch.position, ch.data); } }), { chunked: true, chunkSize: 4 * 1024 * 1024 });
      const out = new M.Output({ format: mov ? new M.MovOutputFormat({ fastStart: false }) : new M.Mp4OutputFormat({ fastStart: false }), target });
      const cs = new M.CanvasSource(cv, { codec: vc, bitrate: vbr, keyFrameInterval: 2, latencyMode: 'realtime' }); out.addVideoTrack(cs, { frameRate: 30 });
      let as2 = null; if (mixed) { as2 = new M.AudioBufferSource(acodec === 'pcm-s16' ? { codec: 'pcm-s16' } : { codec: acodec, bitrate: 160000 }); out.addAudioTrack(as2); }
      await out.start(); if (as2) await as2.add(mixed);
      say('Wird fertig gestellt … Seite offen lassen.'); const W0 = performance.now(); let lastTs = -1;
      // Titelbild ganz vorne (ohne Licht & Farbe, so wie hochgeladen)
      if (C0) { const cvC = await covCanvas(); if (cvC) for (let k = 0; ; k++) { const t = k / 30; if (t >= C0 - 1e-6) break; if (closed) { try { await out.cancel(); } catch (e) {} return 'x'; } draw(cvC, cvC.width, cvC.height, 1, true); await cs.add(t, Math.min(1 / 30, C0 - t)); lastTs = t; fill.style.width = Math.min(100, t / D * 100) + '%'; } }
      // Bild darüber: je Clip der Reihe nach dekodiert, die Stimme bleibt die vom Talking Head
      const ovS = new Map();
      const ovFrame = async (o, t) => {
        let S = ovS.get(o.id);
        if (S && S.cur && t < S.cur.timestamp - .1) { try { await S.it.return(); } catch (e) {} S = null; }
        if (!S) { S = { cur: null, done: false, bad: false }; ovS.set(o.id, S);
          try { const inp = new M.Input({ source: new M.BlobSource(o.blob), formats: M.ALL_FORMATS }), vt = await inp.getPrimaryVideoTrack();
            if (!vt || !(await vt.canDecode())) S.bad = true; else S.it = new M.CanvasSink(vt, { poolSize: 3 }).canvases(Math.max(o.in || 0, t - .05), o.out || o.dur)[Symbol.asyncIterator](); } catch (e) { S.bad = true; } }
        if (S.bad) return null;
        while (!S.done && (!S.cur || S.cur.timestamp + (S.cur.duration || 1 / 30) <= t)) { const r = await S.it.next(); if (r.done) { S.done = true; break; } S.cur = r.value; }
        return S.cur;
      };
      for (const x of ins) {
        const eOut = x.c.out || x.c.dur, direct = !x.rot, vs = direct ? new M.VideoSampleSink(x.vt) : new M.CanvasSink(x.vt, { poolSize: 2 });
        const it = direct ? vs.samples(x.c.in || 0, eOut) : vs.canvases(x.c.in || 0, eOut);
        for await (const smp of it) {
          if (closed) { try { await out.cancel(); } catch (e) {} return 'x'; }
          let el, sw, shh, tsrc, dur; if (direct) { const f = smp.toVideoFrame(); smp.close(); el = f; sw = f.displayWidth; shh = f.displayHeight; tsrc = f.timestamp / 1e6; dur = (f.duration || 0) / 1e6; } else { el = smp.canvas; sw = el.width; shh = el.height; tsrc = smp.timestamp; dur = smp.duration; }
          const ts = x.start + Math.max(0, tsrc - (x.c.in || 0)); const ok2 = ts > lastTs && ts < D;
          const bo = ok2 ? broAt(x.c, tsrc) : null, bf = bo ? await ovFrame(bo, (bo.in || 0) + (tsrc - bo.ost)) : null;
          let msk = null; if (ok2 && !bf && (st.bb || 0) > 0) { try { msk = await segMask(el, sw, shh); } catch (e) { msk = null; } }
          if (ok2) { if (bf) draw(bf.canvas, bf.canvas.width, bf.canvas.height, 1); else draw(el, sw, shh, zAt(x.c, tsrc - (x.c.in || 0)), false, x.c.fr, x.c.fe, msk); lastTs = ts; } if (direct) el.close();
          if (ok2) await cs.add(ts, Math.max(1 / 60, Math.min(dur || 1 / 30, D - ts)));
          fill.style.width = Math.min(100, ts / D * 100) + '%';
          const el2 = (performance.now() - W0) / 1000; if (!window.__snFastForce && !((st.bb || 0) > 0) && el2 > 4 && D - ts > 4 && ts / el2 < 1.1) { try { await out.cancel(); } catch (e) {} return null; }
        }
      }
      for (const S of ovS.values()) { try { if (S.it) await S.it.return(); } catch (e) {} }
      await out.finalize(); const blob = new Blob(parts.map(x => x.b), { type: mov ? 'video/quicktime' : 'video/mp4' });
      // Sicherheitsnetz: sollte Ton drin sein, aber die Datei hat keine Tonspur → lieber der Echtzeit-Weg
      if (mixed) { try { const chk = new M.Input({ source: new M.BlobSource(blob), formats: M.ALL_FORMATS }); if (!(await chk.getPrimaryAudioTrack())) { console.warn('Schnitt: ohne Tonspur gespeichert, nehme den Echtzeit-Weg'); return null; } } catch (e) {} }
      return blob;
    }
    // Ein <video>/<audio> darf nur ein einziges Mal an den Ton-Mischer (Web Audio). Früher wurde bei jedem Fertigstellen neu
    // verbunden – beim zweiten Mal klappte das nicht mehr und der Ton war weg. Jetzt: einmal verbinden, merken, wiederverwenden.
    function sharedAC() { if (!shAC || shAC.state === 'closed') shAC = new (window.AudioContext || window.webkitAudioContext)(); return shAC; }
    function elSource(el) { let s = elSrcs.get(el); if (s) return s; const ac = sharedAC(), src = ac.createMediaElementSource(el), spk = ac.createGain(); src.connect(spk); spk.connect(ac.destination); s = { src, spk }; elSrcs.set(el, s); return s; }
    // Rückfall: in Echtzeit über Canvas + MediaRecorder (alle Browser, dauert so lang wie das Video)
    let sgBusy = false, sgLast = null;
    async function slowRender(fill) {
      if (!window.MediaRecorder) return null; const v0 = vidFor(clips[0]); if (!v0 || !v0.videoWidth) return null;
      const [W, H] = outSize(v0.videoWidth, v0.videoHeight), cv = document.createElement('canvas'); cv.width = W; cv.height = H; const draw = makeGL(cv); if (!draw) return null;
      const C0 = CD(), D = total() + C0, cvC = C0 ? await covCanvas() : null;
      const ac = sharedAC(); await ac.resume().catch(() => {}); const dest = ac.createMediaStreamDestination(), vg = ac.createGain(); vg.gain.value = mus.voice; vg.connect(dest);
      const used = []; let mg = null;
      if (mlist.length) { try { mg = ac.createGain(); mg.gain.value = 0; mg.connect(dest); [auA, auB].forEach(a => { const s = elSource(a); s.src.connect(mg); s.spk.gain.value = 0; used.push([s, mg]); }); } catch (e) { console.warn('Musik (Echtzeit)', e); mg = null; } }
      const stream = cv.captureStream(30); dest.stream.getAudioTracks().forEach(t => stream.addTrack(t));
      const mt = mime(), ch = [], r = new MediaRecorder(stream, Object.assign(mt ? { mimeType: mt } : {}, { videoBitsPerSecond: brate(W, H), audioBitsPerSecond: 160000 })); r.ondataavailable = e => { if (e.data && e.data.size) ch.push(e.data); }; const done = new Promise(ok => r.onstop = ok);
      say('Wird fertig gestellt … dauert so lang wie das Video (' + fmt(D) + '). Seite offen lassen.');
      r.start(500); let t0 = 0;
      if (mg) musDrive(0, true);
      if (cvC) { const s0 = performance.now(); await new Promise(ok => { const step = () => { if (closed) { ok(); return; } const t = (performance.now() - s0) / 1000; draw(cvC, cvC.width, cvC.height, 1, true);
          if (mg) { musDrive(t, true); mg.gain.setTargetAtTime(mus.fade ? Math.max(0, Math.min(1, t / 1.5)) : 1, ac.currentTime, .2); }
          fill.style.width = Math.min(100, t / D * 100) + '%'; if (t >= C0) { ok(); return; } requestAnimationFrame(step); }; step(); }); t0 = C0; }
      for (let i = 0; i < clips.length && !closed; i++) {
        const c = clips[i], v = vidFor(c); v.classList.remove('on'); v.style.transform = ''; v.muted = true; v.currentTime = c.in || 0; await new Promise(ok => { v.onseeked = ok; setTimeout(ok, 1500); });
        if (!c.mute) { try { const s = elSource(v); if (!used.some(u => u[0] === s)) { s.src.connect(vg); s.spk.gain.value = 0; used.push([s, vg]); } } catch (e) { console.warn('Stimme (Echtzeit)', e); } }
        v.muted = !!c.mute; v.volume = 1;
        await v.play().catch(() => {});
        await new Promise(ok => { const step = () => { if (closed) { ok(); return; }
          const bo = broAt(c, v.currentTime), ov = bo ? ovVid(bo) : null;
          ovV.forEach(x => { if (x !== ov && !x.paused) x.pause(); });
          if (ov) { const want = (bo.in || 0) + (v.currentTime - bo.ost); if (Math.abs(ov.currentTime - want) > .3) { try { ov.currentTime = want; } catch (e) {} } if (ov.paused) ov.play().catch(() => {}); }
          if ((st.bb || 0) > 0 && !sgBusy) { sgBusy = true; segMask(v, v.videoWidth, v.videoHeight).then(m => { sgLast = m; sgBusy = false; }).catch(() => { sgBusy = false; }); }
          if (ov && ov.readyState >= 2 && ov.videoWidth) draw(ov, ov.videoWidth, ov.videoHeight, 1); else draw(v, v.videoWidth, v.videoHeight, zAt(c, v.currentTime - (c.in || 0)), false, c.fr, c.fe, sgLast); const t = t0 + (v.currentTime - (c.in || 0));
          if (mg) { musDrive(t, true); const g = mus.fade ? Math.max(0, Math.min(1, t / 1.5, (D - t) / 2.5)) : 1; mg.gain.setTargetAtTime(g, ac.currentTime, .2); }
          fill.style.width = Math.min(100, t / D * 100) + '%'; if (v.ended || v.currentTime >= (c.out || c.dur) - .03) { ok(); return; } (v.requestVideoFrameCallback ? v.requestVideoFrameCallback(step) : requestAnimationFrame(step)); }; step(); v.onended = ok; });
        v.pause(); t0 += len(c);
      }
      ovV.forEach(x => x.pause());
      await new Promise(x => setTimeout(x, 150)); r.stop(); await done; musStop(); stream.getVideoTracks().forEach(t => t.stop());
      used.forEach(([s, n]) => { try { s.src.disconnect(n); } catch (e) {} s.spk.gain.value = 1; }); try { vg.disconnect(); if (mg) mg.disconnect(); } catch (e) {}
      clips.forEach(c => { const v = vids.get(c.slot); if (v) v.muted = !!c.mute; });
      if (closed) return 'x'; const type = (r.mimeType || mt || 'video/webm').split(';')[0]; return new Blob(ch, { type });
    }

    // ---- Titelbild (Vivien, 30.10.2026: „ein einheitliches Titelbild, das ganz am Anfang vom Video sitzt, direkt vorm ersten Schnitt“) ----
    // Bilder liegen auf dem Gerät (IndexedDB kv.covers), je Entwurf kv['cov:'+id] = { on, id, dur }. Neue Talking-Head-Entwürfe
    // bekommen automatisch das zuletzt gewählte Bild (je Programm und Sprache gemerkt in localStorage sn-cov-last).
    function covObj() { return cov && cov.on ? covers.find(x => x.id === cov.id) || null : null; }
    function CD() { return covObj() ? Math.max(.5, +cov.dur || 2) : 0; }
    function covGrp() { const t = curTg(); return t ? (t.k === 'talk' ? 'talk:' + (t.g || '') + ':' + (t.lang || '') : t.k) : 'frei'; }
    async function covLoad() {
      if (!covers.length) covers = ((await kvGet('covers')) || []).map(x => Object.assign({}, x, { url: URL.createObjectURL(x.blob) }));
      cov = (await kvGet('cov:' + curDraft)) || null; if (!cov) covDefault();
    }
    function covDefault() { const t = curTg(), last = ls.get('sn-cov-last', {}), id = last[covGrp()] || last.talk || last.any, has = !!(id && covers.some(x => x.id === id));
      cov = { on: has && !!t && t.k === 'talk', id: has ? id : (covers[0] ? covers[0].id : null), dur: +ls.get('sn-cov-dur', 2) || 2, auto: true }; }
    const covSave = async () => { if (!cov) return; const c2 = Object.assign({}, cov); delete c2.auto; cov.auto = false; await kvSet('cov:' + curDraft, c2).catch(() => {}); };
    const covsSave = () => kvSet('covers', covers.map(x => ({ id: x.id, blob: x.blob, name: x.name, at: x.at }))).catch(() => {});
    function covRemember() { const c = covObj(); if (!c) return; const last = ls.get('sn-cov-last', {}); last[covGrp()] = c.id; if (curTg() && curTg().k === 'talk') last.talk = c.id; last.any = c.id; ls.set('sn-cov-last', last); ls.set('sn-cov-dur', cov.dur); }
    function covTile() {
      const c = cov && covers.find(x => x.id === cov.id);
      if (c) return '<button type="button" class="sn-c cov' + (cov.on ? '' : ' off') + '" data-cov="1"><span class="n">🖼</span><img src="' + c.url + '" alt=""><b>Titelbild</b><small>' + (cov.on ? sec1(CD()) + ' ganz vorne' : 'aus · antippen') + '</small></button>';
      if (covers.length) return '<button type="button" class="sn-add cov" data-cov="1"><b>🖼</b>Titelbild</button>';
      return '<label class="sn-add cov"><b>🖼</b>Titelbild<br>hochladen<input type="file" accept="image/*" hidden data-k="covfile"></label>';
    }
    async function covAdd(f) {
      if (!/^image\//.test(f.type || '') && !/\.(jpe?g|png|heic|heif|webp)$/i.test(f.name || '')) { say('Bitte ein Bild wählen (JPG oder PNG).', 'bad'); return; }
      let blob = f; // kleiner rechnen (höchstens 2400 px), damit es leicht bleibt
      try { const bm = await createImageBitmap(f), k = Math.min(1, 2400 / Math.max(bm.width, bm.height)), cv = document.createElement('canvas'); cv.width = Math.round(bm.width * k); cv.height = Math.round(bm.height * k); cv.getContext('2d').drawImage(bm, 0, 0, cv.width, cv.height); blob = (await new Promise(ok => cv.toBlob(ok, 'image/jpeg', .92))) || f; } catch (e) {}
      const x = { id: uid(), blob, name: String(f.name || 'Titelbild').replace(/\.\w+$/, ''), at: Date.now() }; x.url = URL.createObjectURL(blob);
      covers.unshift(x); while (covers.length > 12) { const r0 = covers.pop(); URL.revokeObjectURL(r0.url); } await covsSave();
      cov = Object.assign(cov || {}, { on: true, id: x.id, dur: (cov && cov.dur) || +ls.get('sn-cov-dur', 2) || 2 }); await covSave(); covRemember();
      K('coved').hidden = false; covChanged(true); say('🖼 Titelbild ist drin. Es steht ganz am Anfang, vor deinem ersten Clip.', 'ok');
    }
    function covEdUi() {
      const box = K('covThs'); K('covOn').checked = !!covObj();
      box.innerHTML = covers.map(x => '<button type="button" class="th' + (cov && cov.id === x.id ? ' on' : '') + '" data-cth="' + x.id + '" aria-label="Dieses Titelbild nehmen"><img src="' + x.url + '" alt=""><span class="x" data-cdel="' + x.id + '" aria-label="Bild löschen">✕</span></button>').join('')
        + '<label class="new"><b>＋</b>Neues Bild<input type="file" accept="image/*" hidden></label>';
      box.querySelector('.new input').onchange = e => { const f = e.target.files[0]; e.target.value = ''; if (f) covAdd(f); };
      box.querySelectorAll('[data-cth]').forEach(b => b.onclick = async e => { if (e.target.closest('[data-cdel]')) return; cov = Object.assign(cov || {}, { id: b.dataset.cth, on: true, dur: (cov && cov.dur) || 2 }); await covSave(); covRemember(); covChanged(true); });
      box.querySelectorAll('[data-cdel]').forEach(b => b.onclick = async e => { e.stopPropagation(); if (!await ask('Dieses Titelbild von diesem Gerät löschen?')) return; const id = b.dataset.cdel, i = covers.findIndex(x => x.id === id);
        if (i >= 0) { URL.revokeObjectURL(covers[i].url); covers.splice(i, 1); } await covsSave(); if (cov && cov.id === id) { cov.id = covers[0] ? covers[0].id : null; if (!cov.id) cov.on = false; await covSave(); } covChanged(); });
      K('covDur').innerHTML = [1, 1.5, 2, 3, 4].map(s => '<button type="button" data-cd="' + s + '" aria-pressed="' + (!!cov && +cov.dur === s) + '">' + String(s).replace('.', ',') + ' s</button>').join('');
      K('covDur').querySelectorAll('button').forEach(b => b.onclick = async () => { if (!cov) cov = { on: false, id: covers[0] ? covers[0].id : null, dur: 2 }; cov.dur = +b.dataset.cd; ls.set('sn-cov-dur', cov.dur); await covSave(); covChanged(true); });
    }
    function covChanged(showIt) { covEdUi(); covShow(showIt ? !!covObj() : (!K('coved').hidden && !!covObj())); schedCache = null; renderClips(); timeUi(); mlUi(); sugUi(); }
    K('covOn').onchange = async () => { if (!covers.length) { K('covOn').checked = false; return; }
      cov = Object.assign(cov || { dur: 2 }, { on: K('covOn').checked, id: (cov && cov.id && covers.some(x => x.id === cov.id)) ? cov.id : covers[0].id }); await covSave(); if (cov.on) covRemember(); covChanged(true); };
    K('covX').onclick = () => { K('coved').hidden = true; covShow(false); };
    function covShow(on) { const im = K('covimg'), c = covObj(); if (on && c) { if (im.getAttribute('src') !== c.url) im.src = c.url; im.classList.add('on'); } else im.classList.remove('on'); }
    async function covCanvas() { const c = covObj(); if (!c) return null; let src = null;
      try { src = await createImageBitmap(c.blob); } catch (e) { src = await new Promise(ok => { const im = new Image(); im.onload = () => ok(im); im.onerror = () => ok(null); im.src = c.url; }); }
      if (!src) return null; const w = src.width || src.naturalWidth, h = src.height || src.naturalHeight, k = Math.min(1, 2048 / Math.max(w, h));
      const cv = document.createElement('canvas'); cv.width = Math.max(2, Math.round(w * k)); cv.height = Math.max(2, Math.round(h * k)); cv.getContext('2d').drawImage(src, 0, 0, cv.width, cv.height); return cv; }
    // Vorschaubild für Bunny im Format des fertigen Videos (Mitte ausgeschnitten wie im Video)
    async function covThumb() {
      const cv = await covCanvas(); if (!cv) return null; const v = K('resv'), W = v.videoWidth || 1280, H = v.videoHeight || 720, k = Math.min(1, 1600 / Math.max(W, H));
      const o2 = document.createElement('canvas'); o2.width = Math.round(W * k); o2.height = Math.round(H * k); const ra = cv.width / cv.height, ro = o2.width / o2.height;
      let sw = cv.width, sh = cv.height; if (ra > ro) sw = sh * ro; else sh = sw / ro;
      o2.getContext('2d').drawImage(cv, (cv.width - sw) / 2, (cv.height - sh) / 2, sw, sh, 0, 0, o2.width, o2.height);
      return new Promise(ok => o2.toBlob(ok, 'image/jpeg', .9)); }

    // ---- Wohin kommt das Video? (Vivien, 30.10.2026: „hochladen, dann soll es direkt drin sein“) ----
    function curTg() { return (drList.find(x => x.id === curDraft) || {}).tg || null; }
    const LGN = { de: 'Deutsch', en: 'Englisch' };
    const dShort = d => { if (!d) return ''; const x = new Date(d + 'T12:00:00'); return isNaN(x) ? '' : ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][x.getDay()] + ' ' + x.getDate() + '.' + (x.getMonth() + 1) + '.'; };
    function tgLabel(t) { if (!t) return ''; return t.k === 'talk' ? '„' + t.n + '“ · ' + (LGN[t.lang] || '') : t.k === 'clip' ? 'Übung „' + t.n + '“' : (t.kind === 'reel' ? 'Reel' : 'Story') + ' für ' + dShort(t.due) + ' · „' + t.n + '“'; }
    const tgKey = t => t ? (t.k === 'talk' ? 't' + t.id + t.lang : t.k === 'clip' ? 'c' + t.c : 'i' + t.id) : '';
    function doneLbl() { K('done').textContent = curTg() && o.upload ? '✓ Fertig stellen & hochladen' : '✓ Fertig stellen'; }
    function tgUi() {
      const box = K('tg'); if (!o.upload) { box.hidden = true; return; } const t = curTg(); box.classList.toggle('on', !!t);
      K('tgT').innerHTML = t ? '📤 Kommt hinein in: <b>' + esc(tgLabel(t)) + '</b><small>' + (t.due ? 'fertig bis ' + esc(dShort(t.due)) + ' · ' : '') + '„Fertig stellen“ lädt es gleich dorthin hoch.</small>'
        : '📤 <b>Wohin kommt das Video?</b><small>Auswählen, dann lädt „Fertig stellen“ es gleich an die richtige Stelle. Ohne Auswahl kannst du es nur sichern oder herunterladen.</small>';
      K('tgBtn').textContent = t ? 'Ändern' : 'Auswählen'; doneLbl();
    }
    let targets = null, upWanted = false;
    async function tgOpen(force) {
      const s = K('tgSel'); if (!s.hidden && !force) { s.hidden = true; return; } s.hidden = false; s.innerHTML = '<option>Wird geladen …</option>';
      if (!targets) { try { targets = await o.targets(); } catch (e) { targets = []; } }
      const now = tgKey(curTg()), open = targets.filter(t => !t.done), fin = targets.filter(t => t.done);
      const opt = t => '<option value="' + esc(tgKey(t)) + '"' + (tgKey(t) === now ? ' selected' : '') + '>' + esc((t.due ? dShort(t.due) + ' · ' : '') + (t.k === 'talk' ? '🗣 ' : t.k === 'clip' ? '🧘 ' : '📱 ') + tgLabel(t)) + '</option>';
      s.innerHTML = '<option value="">— Nirgends, nur sichern oder herunterladen —</option>' + (open.length ? '<optgroup label="Noch offen · nach Datum">' + open.map(opt).join('') + '</optgroup>' : '')
        + (fin.length ? '<optgroup label="Schon hochgeladen · wird ersetzt">' + fin.map(opt).join('') + '</optgroup>' : '');
      if (now && !targets.some(t => tgKey(t) === now)) s.insertAdjacentHTML('beforeend', '<option value="' + esc(now) + '" selected>' + esc(tgLabel(curTg())) + '</option>');
    }
    K('tgBtn').onclick = () => tgOpen();
    K('tgSel').onchange = async () => {
      const v = K('tgSel').value, d = drList.find(x => x.id === curDraft); if (!d) return;
      const t = v ? ((targets || []).find(x => tgKey(x) === v) || (tgKey(d.tg) === v ? d.tg : null)) : null;
      d.tg = t ? { k: t.k, id: t.id, c: t.c, lang: t.lang, n: t.n, g: t.g, due: t.due, kind: t.kind } : null; d.up = null;
      if (t && !d.name) d.name = t.k === 'talk' ? t.n + (t.lang === 'en' ? ' (EN)' : '') : t.k === 'clip' ? 'Übung · ' + t.n : (t.kind === 'reel' ? 'Reel ' : 'Story ') + t.due;
      await draftsSave(drList).catch(() => {}); K('tgSel').hidden = true; if (!cov || cov.auto) { covDefault(); covChanged(); } drawDrafts();
      say(t ? '📤 Kommt hinein in: ' + tgLabel(t) : 'Ohne Ziel: nach dem Fertigstellen sichern oder herunterladen.', 'ok');
      if (t && result && upWanted) { upWanted = false; doUpload(); }
    };

    let result = null, upping = false;
    const extOf = b => /quicktime/.test(b.type || '') ? 'mov' : /webm/.test(b.type || '') ? 'webm' : 'mp4';
    K('done').onclick = async () => {
      if (busy || !clips.length) return; stop(); covShow(false); busy = true; root.classList.add('sn-busy'); K('done').disabled = true; K('res').hidden = true;
      const bar = K('prog'), fill = bar.querySelector('i'); bar.hidden = false; fill.style.width = '0%'; say('Wird vorbereitet …');
      let nb = null, fast = true; lastVoice = null;
      try { nb = await fastRender(fill); } catch (e) { console.warn('schnitt fast', e); nb = null; }
      if (nb === 'x' || closed) { busy = false; return; }
      if (!nb || nb.size < 1000) { fast = false; fill.style.width = '0%'; try { nb = await slowRender(fill); } catch (e) { console.warn('schnitt slow', e); nb = null; } }
      busy = false; root.classList.remove('sn-busy'); K('done').disabled = false; bar.hidden = true;
      if (nb === 'x' || closed) return;
      if (!nb || nb.size < 1000) { say('Das hat nicht geklappt. Bitte nochmal „Fertig stellen“ tippen (am besten in Safari oder Chrome).', 'bad'); return; }
      result = nb; const url = URL.createObjectURL(nb), ext = extOf(nb), tg = curTg();
      const d = new Date(), stamp = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + '_' + String(d.getHours()).padStart(2, '0') + '-' + String(d.getMinutes()).padStart(2, '0');
      const base = tg ? String(tg.n + (tg.lang === 'en' ? ' EN' : '')).replace(/[^\wäöüÄÖÜß-]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 48) || 'Movecia' : 'Movecia_Schnitt';
      K('resv').src = url; K('dl').href = url; K('dl').download = base + '_' + stamp + '.' + ext; K('res').hidden = false; K('up').hidden = true;
      const canSh = !!(navigator.canShare && (() => { try { return navigator.canShare({ files: [new File([nb], 'v.' + ext, { type: nb.type })] }); } catch (e) { return false; } })());
      K('share').hidden = !canSh; K('dl').className = 'sn-b' + (canSh ? '' : ' pl');
      K('resN').textContent = fmt(total() + CD()) + ' · ' + clips.length + (clips.length > 1 ? ' Clips' : ' Clip') + (CD() ? ' · mit Titelbild' : '') + (mlist.length ? ' · mit Musik' : '') + (fast ? '' : ' · in Echtzeit gespeichert');
      try { await kvSet('result', { blob: nb, at: Date.now(), draft: drName(curDraft) }); } catch (e) {}
      // Ton-Check: Stimme fehlt oder ist kaum hörbar → deutlich sagen (und nicht still hochladen)
      const lv = fast ? lastVoice : null, warnV = lv && (lv.none || lv.quiet);
      if (warnV) { K('up').hidden = false; K('up').className = 'sn-up bad'; K('up').innerHTML = '<b>🎙 In deinen Clips ist ' + (lv.none ? 'kein Ton' : 'kaum Ton') + ' zu hören</b>War beim Aufnehmen das richtige Mikrofon gewählt (z. B. dein Rode)? Hör dir das Video unten kurz an. Ich lade es erst hoch, wenn du auf „⬆ Hochladen“ tippst.'; }
      else if (lv && lv.fixed) K('resN').textContent += ' · Mikrofon-Ton auf beide Seiten gelegt';
      K('res').scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (tg && o.upload && !warnV) await doUpload();
      else say(canSh ? '✓ Fertig. Schau es dir an, dann „In Fotos sichern“ (landet direkt in deiner Galerie)' + (o.upload ? ' oder „⬆ Hochladen“.' : '.') : '✓ Fertig. Schau es dir an, dann herunterladen' + (o.upload ? ' oder „⬆ Hochladen“.' : '.'), 'ok');
    };
    async function doUpload() {
      const tg = curTg(); if (!result || !tg || !o.upload || upping) return; upping = true;
      const box = K('up'); box.hidden = false; box.className = 'sn-up run';
      box.innerHTML = '<b>⬆ Wird hochgeladen …</b><span data-k2="upt">' + esc(tgLabel(tg)) + '</span><div class="sn-prog"><i></i></div><small>Bitte die Seite offen lassen, bis hier „Ist drin“ steht.</small>';
      const fillU = box.querySelector('.sn-prog i'), txt = box.querySelector('[data-k2=upt]'); if (K('upBtn')) K('upBtn').disabled = true;
      say('⬆ Wird hochgeladen … Seite bitte offen lassen.');
      let r = null; try { r = await o.upload(result, tg, p => { const pc = Math.round(Math.max(0, Math.min(1, p)) * 100); fillU.style.width = pc + '%'; txt.textContent = tgLabel(tg) + ' · ' + pc + ' %'; }, extOf(result)); } catch (e) { r = { error: (e && e.message) || '' }; }
      upping = false; if (K('upBtn')) K('upBtn').disabled = false;
      if (!r || r.error) { box.className = 'sn-up bad'; box.innerHTML = '<b>Hochladen hat nicht geklappt</b>' + esc((r && r.error) || 'Die Verbindung ist abgebrochen.') + '<div class="sn-row"><button type="button" class="sn-b pl" data-k2="again">⬆ Nochmal hochladen</button></div><small>Dein Video ist nicht verloren, es liegt hier fertig. Du kannst es auch zuerst „In Fotos sichern“.</small>';
        box.querySelector('[data-k2=again]').onclick = doUpload; say('Hochladen hat nicht geklappt. Tipp auf „Nochmal hochladen“.', 'bad'); return; }
      if (r.guid && CD() && o.thumb) { try { const tb = await covThumb(); if (tb) await o.thumb(r.guid, tb); } catch (e) { console.warn('Vorschaubild', e); } }
      const dd = drList.find(x => x.id === curDraft); if (dd) { dd.up = { at: Date.now(), guid: r.guid || '' }; await draftsSave(drList).catch(() => {}); drawDrafts(); }
      if (targets) { const tt = targets.find(x => tgKey(x) === tgKey(tg)); if (tt) tt.done = true; }
      box.className = 'sn-up'; box.innerHTML = '<b>✓ Ist drin!</b>' + esc(tgLabel(tg)) + ' ist hochgeladen.' + (r.note ? ' ' + esc(r.note) : '')
        + '<div class="sn-row">' + (o.seeUrl && o.seeUrl(tg, r) ? '<a class="sn-b dk" href="' + esc(o.seeUrl(tg, r)) + '">👁 So sieht es die Teilnehmerin</a>' : '') + (o.calUrl ? '<a class="sn-b pl" href="' + esc(o.calUrl) + '">📅 Zurück zum Kalender</a>' : '') + '</div>';
      say('✓ Hochgeladen: ' + tgLabel(tg), 'ok');
    }
    if (K('upBtn')) K('upBtn').onclick = () => { if (!result) return; if (curTg()) { doUpload(); return; } upWanted = true; tgOpen(true); K('tg').scrollIntoView({ behavior: 'smooth', block: 'start' }); say('Wähl oben aus, wohin das Video kommt, dann lade ich es gleich hoch.'); };
    K('share').onclick = async () => { if (!result) return; const ext = extOf(result); try { await navigator.share({ files: [new File([result], K('dl').download || ('video.' + ext), { type: result.type })] }); } catch (e) {} };
    K('resX').onclick = () => { const v = K('resv'); try { v.pause(); } catch (e) {} K('res').hidden = true; };
    K('dl').addEventListener('click', () => {});

    function close() { closed = true; stop(); try { pre.pause(); } catch (e) {} clips.forEach(c => URL.revokeObjectURL(c.url)); mount.innerHTML = ''; }
    load();
    return { close, add: addFiles };
  }
  // Fertiges Video aus dem Schnitt holen (für das Studio) und wieder vergessen
  async function takeResult() { try { const r = await kvGet('result'); if (r && r.blob) { await kvDel('result'); return r.blob; } } catch (e) {} return null; }
  // Entwurf (nach Name) zum offenen machen – das Studio ruft das vor dem Wechsel in den Schnitt auf
  async function setDraft(name, target) { const id = await draftFor(name); await kvSet('cur', id);
    if (target) { const l = await drafts(), d = l.find(x => x.id === id); if (d) { d.tg = target; d.up = null; await draftsSave(l); } }
    return id; }
  // Für das Studio: ersetzte/gelöschte Absatz-Takes in den Papierkorb, Reihenfolge der Absätze festlegen
  async function trashTake(id) { const r = (await dbAll()).find(x => x.id === id); if (r && !r.trash) { r.trash = Date.now(); await dbPut(r); } }
  async function orderTakes(name, ids) { const did = await draftFor(name), cur = (await kvGet(ordKey(did))) || []; await kvSet(ordKey(did), ids.concat(cur.filter(x => !ids.includes(x)))); return did; }
  async function orphanFiles() { const d = await idb(), t = d.transaction(['clips', 'files']), rows = (await req(t.objectStore('clips').getAll())) || [], used = new Set(rows.map(r => r.fid).filter(Boolean));
    const keys = (await req(t.objectStore('files').getAllKeys())) || [], out = []; for (const k of keys) if (!used.has(k)) { const b = await req(d.transaction('files').objectStore('files').get(k)); if (b) out.push({ fid: k, blob: b }); } return out; }
  window.Schnitt = { open, addTake, takeResult, draftFor, setDraft, drafts, trashTake, orderTakes };
})();

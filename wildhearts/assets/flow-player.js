/* Movecia Übungs-Player (Vivien, 26.10.2026): Jede Übung ist ein kurzer Clip von Vivien (Bunny-Embed, läuft stumm
   in Schleife). Vorher wählt man 30 oder 45 Sekunden, ein runder Timer zählt herunter, in den letzten 5 Sekunden
   blüht ein sanfter „Sonnenaufgang“-Klang auf, dann startet die nächste Übung von selbst.
   Aufruf: MvFlow(element, {lang, title, items:[{name, src}]}) oder automatisch für jedes [data-mv-flow]. */
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
.mvf-note{font-size:.85rem;color:var(--mvf-muted);margin:0}
.mvf-stage{position:relative;aspect-ratio:16/9;max-width:100%;border-radius:22px;overflow:hidden;background:#4A3A41}
.mvf-stage iframe{position:absolute;inset:0;width:100%;height:100%;border:0;pointer-events:none}
.mvf-name{position:absolute;left:14px;top:12px;right:120px;color:#FBF5F7;text-shadow:0 1px 8px rgba(0,0,0,.35)}
.mvf-name small{display:block;font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;opacity:.85}
.mvf-name b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:clamp(1.2rem,3.4vw,1.9rem);line-height:1.1}
.mvf-ring{position:absolute;right:12px;bottom:12px;width:clamp(84px,19vw,124px);aspect-ratio:1}
.mvf-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.mvf-ring .t{fill:rgba(251,245,247,.82);stroke:rgba(74,58,65,.15);stroke-width:7}
.mvf-ring .p{fill:none;stroke:var(--mvf-deep);stroke-width:7;stroke-linecap:round;transition:stroke .6s}
.mvf-ring .n{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--mvf-deep)}
.mvf-ring .n b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:700;font-size:clamp(2rem,6vw,2.9rem);line-height:1;font-variant-numeric:tabular-nums}
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
.mvf-ctrl{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.mvf-ctrl button{font:inherit;font-size:.92rem;border:1.5px solid var(--mvf-line);background:var(--mvf-card);color:var(--mvf-ink);border-radius:999px;padding:9px 15px;cursor:pointer;min-height:44px}
.mvf-ctrl .main{background:var(--mvf-deep);border-color:var(--mvf-deep);color:#FBF5F7;min-width:116px}
.mvf-ctrl .snd{margin-left:auto}
.mvf button:focus-visible{outline:3px solid var(--mvf-plum);outline-offset:2px}
.mvf-done{background:var(--mvf-card);border:1px solid var(--mvf-line);border-radius:22px;padding:24px;text-align:center;display:flex;flex-direction:column;gap:10px;align-items:center}
.mvf-done h3{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:1.7rem;margin:0}
@media (max-width:560px){.mvf-ctrl .snd{margin-left:0}}`;
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

  function MvFlow(root, data) {
    css();
    const en = data.lang === 'en', T = (de, e) => en ? e : de;
    const items = data.items || [];
    let dur = 30; try { dur = +(localStorage.getItem('mvf-dur') || 30) === 45 ? 45 : 30; } catch (e) {}
    let idx = 0, left = 0, run = false, last = 0, chimed = false, sound = true, raf = 0, sw = false;
    const C = 2 * Math.PI * 52;
    root.classList.add('mvf');
    root.innerHTML = `
<div class="mvf-setup" data-s>
  <p class="mvf-k">${T('Deine Übungen', 'Your exercises')}</p>
  <ul class="mvf-list">${items.map((x, i) => `<li>${i + 1}. ${esc(x.name)}</li>`).join('')}</ul>
  <p class="mvf-k">${T('Wie lange pro Übung?', 'How long per exercise?')}</p>
  <div class="mvf-seg" role="group">
    <button type="button" data-d="30"><b>30</b><span>${T('Sekunden · sanfter Einstieg', 'seconds · gentle start')}</span></button>
    <button type="button" data-d="45"><b>45</b><span>${T('Sekunden · etwas länger', 'seconds · a little longer')}</span></button>
  </div>
  <button type="button" class="mvf-go" data-go>${T("Los geht's", "Let's begin")}</button>
  <p class="mvf-note" data-tot></p>
</div>
<div class="mvf-play" data-p hidden>
  <div class="mvf-stage" data-st>
    <iframe data-if title="" allow="autoplay;encrypted-media;picture-in-picture" loading="eager"></iframe>
    <div class="mvf-sun"></div>
    <div class="mvf-name"><small data-tag></small><b data-nm></b></div>
    <div class="mvf-ring"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="t" cx="60" cy="60" r="52"></circle><circle class="p" data-pr cx="60" cy="60" r="52"></circle></svg><div class="n"><b data-sec>30</b><span>${T('Sek.', 'sec')}</span></div></div>
    <div class="mvf-next" data-nx><small>${T('Nächste Übung', 'Next exercise')}</small><b data-nxn></b></div>
  </div>
  <div class="mvf-dots" data-dots>${items.map(() => '<i></i>').join('')}</div>
  <p class="mvf-after" data-af></p>
  <div class="mvf-ctrl"><button type="button" data-prev>‹ ${T('Zurück', 'Back')}</button><button type="button" class="main" data-pause>❚❚ Pause</button><button type="button" data-skip>${T('Weiter', 'Next')} ›</button><button type="button" class="snd" data-snd aria-pressed="true">${T('Klang an', 'Sound on')}</button></div>
</div>
<div class="mvf-done" data-done hidden><p class="mvf-k">${T('Geschafft', 'Done')}</p><h3>${T('Schön, dass du dir diese Zeit genommen hast.', 'Lovely that you took this time for yourself.')}</h3><p class="mvf-note" data-dt></p><button type="button" class="mvf-go" data-again>${T('Nochmal von vorn', 'Start again')}</button></div>`;
    const q = s => root.querySelector(s);
    const pr = q('[data-pr]'); pr.style.strokeDasharray = C;
    function seg() { root.querySelectorAll('[data-d]').forEach(b => b.setAttribute('aria-pressed', String(+b.dataset.d === dur))); q('[data-tot]').textContent = T(`${items.length} Übungen à ${dur} Sekunden, zusammen etwa ${Math.max(1, Math.round(items.length * dur / 60))} Minuten.`, `${items.length} exercises of ${dur} seconds, about ${Math.max(1, Math.round(items.length * dur / 60))} minutes in total.`); }
    root.querySelectorAll('[data-d]').forEach(b => b.addEventListener('click', () => { dur = +b.dataset.d; try { localStorage.setItem('mvf-dur', String(dur)); } catch (e) {} seg(); }));
    seg();
    function show(i) {
      const it = items[i];
      q('[data-tag]').textContent = T(`Übung ${i + 1} von ${items.length}`, `Exercise ${i + 1} of ${items.length}`);
      q('[data-nm]').textContent = it.name;
      const f = q('[data-if]'); f.title = it.name; if (f.getAttribute('src') !== it.src) f.src = it.src;
      const n = items[i + 1];
      q('[data-af]').textContent = n ? T('Als Nächstes: ', 'Up next: ') + n.name : T('Danach bist du fertig.', 'After this you are done.');
      root.querySelectorAll('[data-dots] i').forEach((d, k) => d.className = k < i ? 'd' : (k === i ? 'c' : ''));
      left = dur; chimed = false; q('[data-st]').classList.remove('end'); draw();
    }
    function draw() { q('[data-sec]').textContent = Math.max(0, Math.ceil(left - 1e-6)); pr.style.strokeDashoffset = C * (1 - Math.max(0, left) / dur); }
    function tick(now) {
      if (!run) return;
      const dt = (now - last) / 1000; last = now;
      if (!sw) {
        left -= dt;
        if (left <= 5 && !chimed) { chimed = true; q('[data-st]').classList.add('end'); if (sound) sunrise(5); }
        if (left <= 0) { left = 0; draw(); advance(); } else draw();
      }
      raf = requestAnimationFrame(tick);
    }
    function advance() {
      if (idx >= items.length - 1) { finish(); return; }
      sw = true; q('[data-nxn]').textContent = items[idx + 1].name; q('[data-nx]').classList.add('on');
      setTimeout(() => { idx++; show(idx); q('[data-nx]').classList.remove('on'); sw = false; last = performance.now(); }, 1300);
    }
    function play() { run = true; last = performance.now(); q('[data-pause]').textContent = '❚❚ Pause'; cancelAnimationFrame(raf); raf = requestAnimationFrame(tick); }
    function stop() { run = false; q('[data-pause]').textContent = '▶ ' + T('Weiter', 'Resume'); }
    function finish() { stop(); q('[data-p]').hidden = true; q('[data-done]').hidden = false; q('[data-if]').src = 'about:blank'; q('[data-dt]').textContent = T(`${items.length} Übungen, je ${dur} Sekunden.`, `${items.length} exercises, ${dur} seconds each.`); }
    let wake = null;
    q('[data-go]').addEventListener('click', () => {
      actx(); q('[data-s]').hidden = true; q('[data-done]').hidden = true; q('[data-p]').hidden = false; idx = 0; show(0); play();
      try { navigator.wakeLock && navigator.wakeLock.request('screen').then(w => wake = w).catch(() => {}); } catch (e) {}
    });
    q('[data-again]').addEventListener('click', () => { q('[data-done]').hidden = true; q('[data-s]').hidden = false; });
    q('[data-pause]').addEventListener('click', () => run ? stop() : play());
    q('[data-skip]').addEventListener('click', () => { if (sw) return; if (idx < items.length - 1) { idx++; show(idx); } else finish(); });
    q('[data-prev]').addEventListener('click', () => { if (sw) return; idx = Math.max(0, idx - 1); show(idx); });
    q('[data-snd]').addEventListener('click', e => { sound = !sound; e.currentTarget.setAttribute('aria-pressed', String(sound)); e.currentTarget.textContent = sound ? T('Klang an', 'Sound on') : T('Klang aus', 'Sound off'); });
    return { stop: () => { stop(); q('[data-if]').src = 'about:blank'; } };
  }
  window.MvFlow = MvFlow;
  function auto() { document.querySelectorAll('[data-mv-flow]').forEach(el => { if (el.dataset.mvfOn) return; el.dataset.mvfOn = '1'; try { MvFlow(el, JSON.parse(el.dataset.mvFlow)); } catch (e) {} }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', auto); else auto();
})();

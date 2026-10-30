/* ✨ Automatisch schneiden (Vivien, 30.10.2026: „ein Knopf, der Stille entfernt und Versprecher ausbessert“)
 * Reine Rechenfunktionen auf dem Ton eines Clips (Mono, 16 kHz). Wird von schnitt.js bei Bedarf nachgeladen.
 * - Hüllkurve je 20 ms → Sprechen/Pause mit Schwelle aus dem Grundrauschen des Clips (Hysterese).
 * - Segmente = Sprechen mit etwas Luft davor/danach; Pausen länger als „minPause“ fallen weg.
 * - Versprecher = Neustart: Klingt der Anfang eines Abschnitts wie der Anfang des nächsten (gleiche Wörter),
 *   wird der erste als Versprecher vorgeschlagen (Spektral-Vergleich mit DTW, kein Internet, kein Spracherkenner).
 * SchnittAuto.analyze(samples | framer().done(), sr, {minPause, stumbles}) → {segs:[{s,e}], cut:[{s,e,why}], pauses:{n,sec}, dur}
 * Lange Clips: f = SchnittAuto.framer(); f.push(SchnittAuto.toMono16k(chans, sr)) je Stück; analyze(f.done(), 16000, opt)
 */
(function () {
  'use strict';
  const N = 512, HOP = 320, SR = 16000; // 32 ms Fenster, 20 ms Schritt

  // Mono + 16 kHz aus einem AudioBuffer (oder Kanälen) machen
  function toMono16k(chans, sr) {
    const L = chans[0].length, mono = new Float32Array(L);
    for (const ch of chans) for (let i = 0; i < L; i++) mono[i] += ch[i] / chans.length;
    if (sr === SR) return mono;
    const r = sr / SR, M = Math.floor(L / r), out = new Float32Array(M);
    for (let i = 0; i < M; i++) { const p = i * r, a = Math.floor(p), b = Math.min(L - 1, a + 1), f = p - a; out[i] = mono[a] * (1 - f) + mono[b] * f; }
    return out;
  }

  // ---- FFT (radix-2, in place) ----
  const REV = new Uint16Array(N), COS = new Float32Array(N / 2), SIN = new Float32Array(N / 2), WIN = new Float32Array(N);
  (function () { let bits = Math.log2(N); for (let i = 0; i < N; i++) { let r = 0; for (let b = 0; b < bits; b++) r = (r << 1) | ((i >> b) & 1); REV[i] = r; } for (let i = 0; i < N / 2; i++) { COS[i] = Math.cos(2 * Math.PI * i / N); SIN[i] = -Math.sin(2 * Math.PI * i / N); } for (let i = 0; i < N; i++) WIN[i] = .5 - .5 * Math.cos(2 * Math.PI * i / (N - 1)); })();
  function fft(re, im) {
    for (let i = 0; i < N; i++) { const j = REV[i]; if (j > i) { let t = re[i]; re[i] = re[j]; re[j] = t; t = im[i]; im[i] = im[j]; im[j] = t; } }
    for (let s = 2; s <= N; s <<= 1) { const h = s >> 1, st = N / s; for (let k = 0; k < N; k += s) for (let j = 0; j < h; j++) { const c = COS[j * st], sn = SIN[j * st], a = k + j, b = a + h, tr = re[b] * c - im[b] * sn, ti = re[b] * sn + im[b] * c; re[b] = re[a] - tr; im[b] = im[a] - ti; re[a] += tr; im[a] += ti; } }
  }
  // Mel-Bänder (24, 120–6000 Hz) als Dreiecksfilter über den FFT-Bins
  const NB = 24, MEL = (function () { const m = f => 2595 * Math.log10(1 + f / 700), im = x => 700 * (Math.pow(10, x / 2595) - 1); const lo = m(120), hi = m(6000), pts = []; for (let i = 0; i <= NB + 1; i++) pts.push(im(lo + (hi - lo) * i / (NB + 1)) / SR * N); const F = []; for (let b = 0; b < NB; b++) { const a = pts[b], c = pts[b + 1], d = pts[b + 2], w = []; for (let k = Math.floor(a); k <= Math.ceil(d) && k < N / 2; k++) { const x = k < c ? (k - a) / (c - a) : (d - k) / (d - c); if (x > 0) w.push([k, x]); } F.push(w); } return F; })();

  // Je Frame: Pegel (dB) und Spektrum (Mel-Log, mittelwertfrei, normiert). Stückweise fütterbar (lange Videos ohne riesigen Speicher).
  function framer() {
    let carry = new Float32Array(0), dbs = [], spec = [], cnt = 0; const re = new Float32Array(N), im = new Float32Array(N), pw = new Float32Array(N / 2);
    let dbBuf = new Float32Array(4096);
    function run(x) {
      const n = Math.max(0, Math.floor((x.length - N) / HOP) + 1);
      for (let f = 0; f < n; f++) {
        const o = f * HOP; let e = 0; for (let i = 0; i < N; i++) { const v = x[o + i]; e += v * v; re[i] = v * WIN[i]; im[i] = 0; }
        if (cnt >= dbBuf.length) { const nb = new Float32Array(dbBuf.length * 2); nb.set(dbBuf); dbBuf = nb; }
        dbBuf[cnt++] = 10 * Math.log10(e / N + 1e-10); fft(re, im); for (let k = 0; k < N / 2; k++) pw[k] = re[k] * re[k] + im[k] * im[k];
        const v = new Float32Array(NB); let mean = 0; for (let b = 0; b < NB; b++) { let s = 0; for (const [k, w] of MEL[b]) s += pw[k] * w; v[b] = Math.log(s + 1e-9); mean += v[b]; } mean /= NB; let nrm = 0; for (let b = 0; b < NB; b++) { v[b] -= mean; nrm += v[b] * v[b]; } nrm = Math.sqrt(nrm) || 1; for (let b = 0; b < NB; b++) v[b] /= nrm; spec.push(v);
      }
      return n * HOP;
    }
    return {
      push(x) { const all = new Float32Array(carry.length + x.length); all.set(carry); all.set(x, carry.length); const used = run(all); carry = all.slice(used); },
      done() { return { db: dbBuf.subarray(0, cnt), spec, n: cnt }; }
    };
  }
  function frames(x) { const f = framer(); f.push(x); return f.done(); }
  const pct = (arr, p) => { const a = Array.from(arr).sort((x, y) => x - y); return a.length ? a[Math.min(a.length - 1, Math.floor(a.length * p))] : 0; };

  // Sprechen/Pause → Sprech-Abschnitte („runs“, mit Luft davor/danach) in Sekunden, noch nicht verschmolzen
  function speechRuns(db, opt) {
    const hop = HOP / SR, n = db.length; if (!n) return [];
    const floor = pct(db, .15), top = pct(db, .92);
    const thr = top - floor < 12 ? floor + 6 : Math.max(floor + 10, top - 24); // Schwelle aus dem Grundrauschen
    const loose = thr - 6;
    const on = new Uint8Array(n); for (let i = 0; i < n; i++) on[i] = db[i] > loose ? 1 : 0;
    // lose Abschnitte nur behalten, wenn irgendwo drin die strenge Schwelle erreicht wird (Hysterese)
    let runs = []; for (let i = 0; i < n;) { if (!on[i]) { i++; continue; } let j = i; let strong = false; while (j < n && on[j]) { if (db[j] > thr) strong = true; j++; } if (strong) runs.push([i, j]); i = j; }
    runs = runs.filter(r => (r[1] - r[0]) * hop >= .2); // winzige Geräusche (< 0,2 s) weg
    const pre = opt.pre ?? .12, post = opt.post ?? .25, dur = n * hop;
    return runs.map(r => ({ s: Math.max(0, r[0] * hop - pre), e: Math.min(dur, r[1] * hop + post), raw: r[0] * hop }));
  }
  // Abschnitte verschmelzen, wenn die Pause dazwischen kürzer als minPause ist
  function merge(runs, minP) { const segs = []; for (const r of runs) { const last = segs[segs.length - 1]; if (last && r.s - last.e < minP) last.e = Math.max(last.e, r.e); else segs.push({ s: r.s, e: r.e }); } return segs; }

  // Ähnlichkeit zweier Spektrum-Folgen (DTW mit Band, Kosinus je Frame) → 0…1
  function dtwSim(A, B, band) {
    const n = A.length, m = B.length; if (!n || !m) return 0; const INF = 1e9, D = new Float32Array((n + 1) * (m + 1)).fill(INF); D[0] = 0;
    const cost = (i, j) => { let s = 0; const a = A[i], b = B[j]; for (let k = 0; k < NB; k++) s += a[k] * b[k]; return 1 - s; };
    for (let i = 1; i <= n; i++) { const lo = Math.max(1, Math.round(i * m / n) - band), hi = Math.min(m, Math.round(i * m / n) + band); for (let j = lo; j <= hi; j++) { const c = cost(i - 1, j - 1); D[i * (m + 1) + j] = c + Math.min(D[(i - 1) * (m + 1) + j], D[i * (m + 1) + j - 1], D[(i - 1) * (m + 1) + j - 1]); } }
    const best = D[n * (m + 1) + m]; if (best >= INF) return 0;
    return Math.max(0, 1 - best / Math.max(n, m)); // 1 = gleich, 0 = völlig anders
  }

  function analyze(x, sr, opt) {
    opt = opt || {}; let F;
    if (x && x.spec) F = x; else { if (sr !== SR) x = toMono16k([x], sr); F = frames(x); }
    const hop = HOP / SR, dur = F.n * hop;
    const runs = speechRuns(F.db, opt), cut = [];
    if (opt.stumbles !== false && runs.length > 1) {
      // Neustart: klingt der Anfang eines Abschnitts wie der Anfang des nächsten, ist der erste ein Versprecher
      const fr = t => Math.max(0, Math.min(F.n, Math.round(t / hop)));
      for (let i = 0; i < runs.length - 1; i++) {
        const A = runs[i], B = runs[i + 1]; if (A.why || B.s - A.e > 3) continue;
        const w = Math.min(1.4, A.e - A.s - .3, B.e - B.s - .3); if (w < .45) continue;
        const a0 = fr(A.raw), b0 = fr(B.raw), len = Math.round(w / hop);
        const sim = dtwSim(F.spec.slice(a0, a0 + len), F.spec.slice(b0, b0 + len), 10); A.sim = sim;
        if (sim >= (opt.simThr ?? .72)) { A.why = 'versprecher'; cut.push({ s: A.s, e: A.e, why: 'versprecher', sim }); }
      }
    }
    const segs = merge(runs.filter(r => !r.why), opt.minPause ?? .7);
    let pauseSec = dur, pn = 0; for (const s of segs) pauseSec -= (s.e - s.s); for (const c of cut) pauseSec -= (c.e - c.s);
    for (let i = 1; i < segs.length; i++) if (segs[i].s - segs[i - 1].e > .01) pn++;
    if (segs.length && segs[0].s > .3) pn++; if (segs.length && dur - segs[segs.length - 1].e > .3) pn++;
    return { segs, cut, pauses: { n: pn, sec: Math.max(0, pauseSec) }, dur };
  }

  const G = typeof window !== 'undefined' ? window : globalThis;
  G.SchnittAuto = { analyze, toMono16k, framer, _frames: frames, _dtw: dtwSim };
  if (typeof module !== 'undefined') module.exports = G.SchnittAuto;
})();

/* Studio · Video bearbeiten (Vivien, 28.10.2026)
 * Nach der Aufnahme (oder mit einem Video vom Gerät): kürzen, Licht verbessern, Bildausschnitt
 * (Format, Zoom, Position, gerade richten), Musik von einer eigenen Datei darunterlegen
 * (Lautstärke, leiser beim Sprechen, ein-/ausblenden), dann neu „fertig stellen“.
 * Das Fertigstellen läuft in Echtzeit im Browser (Canvas + Web Audio + MediaRecorder).
 * StudioEdit.open({blob, mount, name, dur, onDone(blob, info), onCancel})
 */
(function () {
  'use strict';
  const CSS = `
.se{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,420px);gap:20px;align-items:start;font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;color:#2E2529}
@media(max-width:980px){.se{grid-template-columns:1fr}}
.se-l{position:sticky;top:72px;z-index:6;background:#F2ECE4;padding-bottom:6px}
@media(max-width:980px){.se{display:flex;flex-direction:column;align-items:stretch;gap:12px}.se-l{position:static;background:none;padding:0}
 .se>.se-pv{position:sticky;top:60px;z-index:7;margin:0 -12px;border-radius:0;box-shadow:0 10px 18px -14px rgba(46,37,41,.55)}
 .se-row .se-b{min-height:42px;font-size:16px;padding:8px 13px}}
.se-pv{position:relative;border-radius:22px;overflow:hidden;background:#2E2529;display:flex;justify-content:center;cursor:grab}
@media(pointer:fine){.se-pv{touch-action:none}}
.se-pv canvas{display:block;max-width:100%;max-height:var(--mh,66vh);width:auto;height:auto}
.se-pv .se-big{position:absolute;inset:0;margin:auto;width:84px;height:84px;border-radius:50%;border:0;background:rgba(251,245,247,.88);color:#4A3A41;font-size:34px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.se-pv .se-big[hidden]{display:none}
.se-pv .se-tag{position:absolute;left:12px;top:12px;padding:5px 12px;border-radius:999px;background:rgba(46,37,41,.72);color:#fff;font-size:15px}
.se-pv .se-tag:empty{display:none}
.se-tl{margin-top:10px;display:flex;flex-direction:column;gap:8px}
.se-scrub{position:relative;height:44px}
.se-scrub .rng{position:absolute;top:16px;height:12px;border-radius:9px;background:#EADFDE;left:0;right:0}
.se-scrub .sel{position:absolute;top:16px;height:12px;border-radius:9px;background:#7D5E68}
.se-scrub input{position:absolute;inset:0;width:100%;height:44px;margin:0;padding:0;background:transparent;-webkit-appearance:none;appearance:none;cursor:pointer}
.se-scrub input::-webkit-slider-runnable-track{background:transparent;height:44px}
.se-scrub input::-moz-range-track{background:transparent}
.se-scrub input::-webkit-slider-thumb{-webkit-appearance:none;width:26px;height:26px;margin-top:9px;border-radius:50%;background:#4A3A41;border:3px solid #fff;box-shadow:0 1px 4px rgba(46,37,41,.4)}
.se-scrub input::-moz-range-thumb{width:22px;height:22px;border-radius:50%;background:#4A3A41;border:3px solid #fff}
.se-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.se-row .t{font-size:17px;color:#4A3A41;font-variant-numeric:tabular-nums;margin-right:auto}
.se-b{font:inherit;font-size:17px;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:46px;padding:9px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer}
.se-b.main{background:#3F6B3E;border-color:#3F6B3E;color:#fff;font-size:19px;min-height:56px;padding:12px 26px}
.se-b.dk{background:#4A3A41;border-color:#4A3A41;color:#fff}
.se-b.pl{background:#7D5E68;border-color:#7D5E68;color:#fff}
.se-b:disabled{opacity:.5;cursor:default}
.se-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:12px}
.se-msg{font-size:17px;color:#6B5658;margin:8px 2px 0}.se-msg.ok{color:#3F6B3E;font-weight:500}
.se-prog{height:10px;border-radius:9px;background:#EADFDE;overflow:hidden;margin-top:8px}.se-prog i{display:block;height:100%;width:0;background:#3F6B3E}
.se-prog[hidden]{display:none}
.se-r{display:flex;flex-direction:column;gap:14px}
.se-r .tc-gb{gap:14px}
.se-file{display:flex;flex-direction:column;gap:8px}
.se-file b{font-weight:500;font-size:17px;color:#4A3A41;word-break:break-word}
.se-note{font-size:15px;line-height:1.5;color:#7D7076;margin:0}
.se-lib{display:flex;flex-direction:column;gap:8px}
.se-lib .chips{display:flex;flex-wrap:wrap;gap:6px}
.se-lib .chips button{font:inherit;font-size:14px;padding:6px 12px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer}
.se-lib .chips button[aria-pressed=true]{background:#4A3A41;border-color:#4A3A41;color:#fff}
.se-lib ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;max-height:300px;overflow:auto}
.se-lib li{display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid #E6DCD2;border-radius:16px;padding:6px 8px}
.se-lib li.on{border-color:#7D5E68;box-shadow:0 0 0 2px #7D5E68 inset}
.se-lib li span{flex:1;min-width:0;font-size:16px;color:#2E2529;line-height:1.3}.se-lib li small{display:block;color:#7D7076;font-size:13px}
.se-lib li button{font:inherit;flex:0 0 auto;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer;min-height:40px;padding:6px 12px;font-size:15px}
.se-lib li button.pk{background:#7D5E68;border-color:#7D5E68;color:#fff}
.se-busy .se-r,.se-busy .se-tl{opacity:.45;pointer-events:none}
`;
  function css() { if (document.getElementById('se-css')) return; const s = document.createElement('style'); s.id = 'se-css'; s.textContent = CSS; document.head.appendChild(s); }
  const ls = { get: (k, d) => { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } }, set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };
  const fmt = s => { s = Math.max(0, s || 0); return Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0') + ',' + Math.floor((s % 1) * 10); };
  const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  function mime() { const ts = ['video/mp4;codecs=avc1.640028,mp4a.40.2', 'video/mp4;codecs=avc1,mp4a', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']; return ts.find(t => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || ''; }

  const VS = 'attribute vec2 p;varying vec2 uv;void main(){uv=p*.5+.5;gl_Position=vec4(p,0.,1.);}';
  const FS = `precision highp float;varying vec2 uv;uniform sampler2D t;uniform vec2 so,oo,off;uniform float sc,ang,ex,con,wm,sat,sh,hi,on;
float lu(vec3 c){return dot(c,vec3(.2126,.7152,.0722));}
void main(){vec2 po=(uv-.5)*oo;float c=cos(ang),s=sin(ang);vec2 pr=vec2(c*po.x-s*po.y,s*po.x+c*po.y);
vec2 ps=so*.5+off+pr/sc;vec3 col=texture2D(t,clamp(ps/so,vec2(.0005),vec2(.9995))).rgb;
if(on>.5){col*=exp2(ex);float l=clamp(lu(col),0.,1.);
col+=sh*(1.-col)*pow(1.-l,2.)*.55;
col-=hi*col*pow(l,3.)*.45;
col=clamp(col,0.,1.);col=mix(col,col*col*(3.-2.*col),con);col=(col-.5)*(1.+max(con,0.)*.15)+.5;
col*=vec3(1.+.09*wm,1.+.015*wm,1.-.1*wm);
l=lu(col);col=mix(vec3(l),col,sat);}
gl_FragColor=vec4(clamp(col,0.,1.),1.);}`;

  const DEF = { ex: 0, con: 0, wm: 0, sat: 1, sh: 0, hi: 0, fmt: 'orig', zoom: 1, ox: 0, oy: 0, rot: 0 };
  const FMTS = [['orig', 'Wie aufgenommen'], ['16:9', 'Quer 16:9'], ['9:16', 'Hoch 9:16 (Reel)'], ['4:5', '4:5 (Feed)'], ['1:1', 'Quadrat']];
  let lastMusic = null; // Musikdatei bleibt für die nächsten Clips gewählt

  function open(o) {
    css();
    const mount = o.mount; mount.innerHTML = '';
    const st = Object.assign({}, DEF);
    const mus = Object.assign({ vol: .18, voice: 1, duck: true, fade: true, from: 0 }, ls.get('se-mus', {}));
    let file = lastMusic, a = 0, b = 0, dur = 0, busy = false, closed = false;

    const root = document.createElement('div'); root.className = 'se'; mount.appendChild(root);
    root.innerHTML = `<div class="se-l">
  <div class="se-pv"><canvas></canvas><button type="button" class="se-big" aria-label="Abspielen">▶</button><span class="se-tag"></span></div>
  <div class="se-tl">
    <div class="se-scrub"><div class="rng"></div><div class="sel"></div><input type="range" min="0" max="1000" step="1" value="0" aria-label="Stelle im Video"></div>
    <div class="se-row"><span class="t" data-k="time">0:00</span>
      <button type="button" class="se-b" data-k="setA">⟦ Anfang hier</button><button type="button" class="se-b" data-k="setB">Ende hier ⟧</button></div>
    <div class="se-row"><span class="t" data-k="cut" style="font-size:16px;color:#6B5658"></span><button type="button" class="se-b" data-k="resetCut">Schnitt zurück</button></div>
  </div>
  <div class="se-bar">
    <button type="button" class="se-b pl" data-k="play">▶ Abspielen</button>
    <button type="button" class="se-b" data-k="cmp">Vorher zeigen</button>
    <button type="button" class="se-b main" data-k="done">✓ Fertig stellen</button>
    <button type="button" class="se-b" data-k="cancel">Abbrechen</button>
  </div>
  <div class="se-prog" hidden><i></i></div>
  <p class="se-msg"></p>
</div>
<div class="se-r tc-p">
  <details class="tc-g" open><summary>☀️ Licht &amp; Farbe</summary><div class="tc-gb">
    <button type="button" class="se-b pl" data-k="auto">✨ Automatisch verbessern</button>
    <div data-sl="ex"></div><div data-sl="sh"></div><div data-sl="hi"></div><div data-sl="con"></div><div data-sl="wm"></div><div data-sl="sat"></div>
    <button type="button" class="se-b" data-k="resetLight">Licht zurücksetzen</button>
    <div class="tc-mine"><h3>💛 Meine Filter</h3><div class="tc-chips" data-k="mine"></div>
      <div class="tc-mine-f" data-k="mform" hidden><input type="text" maxlength="40" data-k="mname2" aria-label="Name für deinen Filter"><button type="button" class="tc-save" data-k="mok">Speichern</button><button type="button" class="tc-save lite" data-k="mno">Abbrechen</button></div>
      <div data-k="mrow"><button type="button" class="tc-save" data-k="msave">＋ So wie jetzt speichern</button></div><p class="tc-mmsg" data-k="mmsg"></p></div></div></details>
  <details class="tc-g" open><summary>🖼 Ausschnitt &amp; Ausrichtung</summary><div class="tc-gb">
    <div class="tc-chips" data-k="fmts"></div>
    <div data-sl="zoom"></div><div data-sl="ox"></div><div data-sl="oy"></div><div data-sl="rot"></div>
    <p class="se-note">Tipp: Am Computer kannst du das Bild in der Vorschau auch mit der Maus verschieben.</p>
    <button type="button" class="se-b" data-k="resetFrame">Ausschnitt zurücksetzen</button></div></details>
  <details class="tc-g" open><summary>🎵 Musik darunter</summary><div class="tc-gb">
    <div class="se-file"><b data-k="mname">Noch keine Musik</b>
      <button type="button" class="se-b" data-k="mdel" hidden>Musik entfernen</button></div>
    <div class="se-lib" data-k="lib"><p class="se-note">Deine Musik wird geladen …</p></div>
    <label class="se-b" style="width:max-content">📁 Andere Datei vom Gerät<input type="file" accept="audio/*,.mp3,.m4a,.wav,.aac,.ogg" hidden data-k="mfile"></label>
    <div data-sl="vol"></div><div data-sl="voice"></div><div data-sl="from"></div>
    <label class="tc-sw">Musik leiser, wenn ich spreche<input type="checkbox" data-k="duck"></label>
    <label class="tc-sw">Musik sanft ein- und ausblenden<input type="checkbox" data-k="fade"></label>
    <p class="se-note">Neue Lieder (z. B. von Epidemic Sound) legst du unter „🎵 Musik“ in deinen Musik-Ordner. Spotify lässt sich nicht in ein Video mischen, die Lieder sind kopiergeschützt.</p>
  </div></details>
</div>`;
    const q = s => root.querySelector(s), K = k => root.querySelector('[data-k="' + k + '"]');
    const cv = q('canvas'), msg = q('.se-msg'), big = q('.se-big'), scrub = q('.se-scrub input');
    const mh = () => (matchMedia('(max-width:980px)').matches ? 34 : 62) + 'vh';
    root.style.setProperty('--mh', mh());
    // Am Handy: nur das Vorschaubild bleibt oben stehen, Zeitleiste und Regler scrollen darunter
    const pv = q('.se-pv'), lcol = q('.se-l');
    const place = () => { const m = matchMedia('(max-width:980px)').matches; if (m && pv.parentNode !== root) root.insertBefore(pv, root.firstChild); else if (!m && pv.parentNode !== lcol) lcol.insertBefore(pv, lcol.firstChild); };
    place();
    const onRs = () => { root.style.setProperty('--mh', mh()); place(); }; addEventListener('resize', onRs);

    // ---- Video ----
    const v = document.createElement('video'); v.playsInline = true; v.setAttribute('playsinline', ''); v.preload = 'auto';
    const url = URL.createObjectURL(o.blob); v.src = url;
    const au = new Audio(); au.loop = true; au.preload = 'auto';
    let ac = null, vSrc = null, vGain, mGain, spk, recDest, an, abuf, mSrc = null;
    function audio() {
      if (ac) return; ac = new (window.AudioContext || window.webkitAudioContext)();
      spk = ac.createGain(); spk.connect(ac.destination); recDest = ac.createMediaStreamDestination();
      vGain = ac.createGain(); mGain = ac.createGain(); mGain.gain.value = 0;
      try { vSrc = ac.createMediaElementSource(v); vSrc.connect(vGain); an = ac.createAnalyser(); an.fftSize = 1024; abuf = new Float32Array(an.fftSize); vSrc.connect(an); } catch (e) { vSrc = null; }
      [vGain, mGain].forEach(g => { g.connect(spk); g.connect(recDest); });
    }
    function musicSrc() { if (!ac || mSrc || !file) return; try { mSrc = ac.createMediaElementSource(au); mSrc.connect(mGain); } catch (e) {} }

    // ---- WebGL ----
    const gl = cv.getContext('webgl', { preserveDrawingBuffer: true, premultipliedAlpha: false }) || cv.getContext('experimental-webgl', { preserveDrawingBuffer: true });
    if (!gl) { msg.textContent = 'Dieser Browser kann das Video nicht bearbeiten. Bitte Chrome oder Safari nehmen.'; }
    let P, U = {}, tex;
    if (gl) {
      const sh = (t, s) => { const x = gl.createShader(t); gl.shaderSource(x, s); gl.compileShader(x); if (!gl.getShaderParameter(x, gl.COMPILE_STATUS)) console.warn(gl.getShaderInfoLog(x)); return x; };
      P = gl.createProgram(); gl.attachShader(P, sh(gl.VERTEX_SHADER, VS)); gl.attachShader(P, sh(gl.FRAGMENT_SHADER, FS)); gl.linkProgram(P); gl.useProgram(P);
      const bf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, bf); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
      const loc = gl.getAttribLocation(P, 'p'); gl.enableVertexAttribArray(loc); gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
      ['t', 'so', 'oo', 'off', 'sc', 'ang', 'ex', 'con', 'wm', 'sat', 'sh', 'hi', 'on'].forEach(n => U[n] = gl.getUniformLocation(P, n));
      tex = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, tex);
      [[gl.TEXTURE_MIN_FILTER, gl.LINEAR], [gl.TEXTURE_MAG_FILTER, gl.LINEAR], [gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE], [gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE]].forEach(x => gl.texParameteri(gl.TEXTURE_2D, x[0], x[1]));
      gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
    }
    let cmp = false, Ws = 0, Hs = 0, Wo = 0, Ho = 0;
    function outSize() {
      Ws = v.videoWidth || 1280; Hs = v.videoHeight || 720;
      let w = Ws, h = Hs; const f = st.fmt;
      if (f !== 'orig') { const [x, y] = f.split(':').map(Number), r = x / y; if (r >= Ws / Hs) { w = Ws; h = Ws / r; } else { h = Hs; w = Hs * r; } }
      const k = Math.min(1, 1920 / Math.max(w, h)); w = Math.round(w * k / 2) * 2; h = Math.round(h * k / 2) * 2;
      if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; }
      Wo = w; Ho = h;
    }
    function geo() {
      const an = st.rot * Math.PI / 180, c = Math.abs(Math.cos(an)), s = Math.abs(Math.sin(an));
      const W2 = Wo * c + Ho * s, H2 = Wo * s + Ho * c, base = Math.max(W2 / Ws, H2 / Hs), sc = base * st.zoom;
      const mx = Math.max(0, Ws / 2 - W2 / (2 * sc)), my = Math.max(0, Hs / 2 - H2 / (2 * sc));
      return { an, sc, mx, my, ox: st.ox * mx, oy: -st.oy * my };
    }
    function draw() {
      if (!gl || !v.videoWidth) return; outSize();
      gl.viewport(0, 0, Wo, Ho); gl.bindTexture(gl.TEXTURE_2D, tex);
      try { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, v); } catch (e) { return; }
      const g = geo();
      gl.uniform1i(U.t, 0); gl.uniform2f(U.so, Ws, Hs); gl.uniform2f(U.oo, Wo, Ho); gl.uniform2f(U.off, g.ox, g.oy);
      gl.uniform1f(U.sc, g.sc); gl.uniform1f(U.ang, g.an); gl.uniform1f(U.ex, st.ex); gl.uniform1f(U.con, st.con); gl.uniform1f(U.wm, st.wm);
      gl.uniform1f(U.sat, st.sat); gl.uniform1f(U.sh, st.sh); gl.uniform1f(U.hi, st.hi); gl.uniform1f(U.on, cmp ? 0 : 1);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }

    // ---- Regler ----
    const SL = {
      ex: ['Helligkeit', -1, 1, .02, x => (x > 0 ? '+' : '') + Math.round(x * 100)],
      sh: ['Schatten aufhellen', 0, 1, .02, x => Math.round(x * 100)],
      hi: ['Helle Stellen dämpfen', 0, 1, .02, x => Math.round(x * 100)],
      con: ['Kontrast', -.6, .8, .02, x => (x > 0 ? '+' : '') + Math.round(x * 100)],
      wm: ['Wärme', -1, 1, .02, x => x > .02 ? 'wärmer' : x < -.02 ? 'kühler' : 'neutral'],
      sat: ['Farbe', 0, 1.6, .02, x => Math.round(x * 100) + ' %'],
      zoom: ['Zoom', 1, 2.5, .01, x => Math.round(x * 100) + ' %'],
      ox: ['Links ↔ rechts', -1, 1, .01, x => Math.round(x * 100)],
      oy: ['Hoch ↕ runter', -1, 1, .01, x => Math.round(x * 100)],
      rot: ['Gerade richten', -8, 8, .1, x => x.toFixed(1).replace('.', ',') + '°'],
      vol: ['Lautstärke Musik', 0, 1, .01, x => Math.round(x * 100) + ' %'],
      voice: ['Lautstärke Stimme', 0, 1.5, .01, x => Math.round(x * 100) + ' %'],
      from: ['Musik startet bei', 0, 600, 1, x => fmt(x).replace(/,\d$/, '')]
    };
    const inputs = {};
    root.querySelectorAll('[data-sl]').forEach(el => {
      const k = el.dataset.sl, d = SL[k], isM = ['vol', 'voice', 'from'].includes(k), obj = isM ? mus : st;
      el.className = 'tc-sl'; el.innerHTML = '<label><span>' + d[0] + '</span><small></small></label><input type="range" min="' + d[1] + '" max="' + d[2] + '" step="' + d[3] + '">';
      const inp = el.querySelector('input'), sm = el.querySelector('small'); inputs[k] = inp;
      inp.value = obj[k]; sm.textContent = d[4](+obj[k]);
      inp.oninput = () => { obj[k] = +inp.value; sm.textContent = d[4](obj[k]); if (isM) { ls.set('se-mus', { vol: mus.vol, voice: mus.voice, duck: mus.duck, fade: mus.fade }); if (k === 'from') syncMusic(); } else if (curMine) { curMine = ''; drawMine(); } draw(); };
      inp.ondblclick = () => { obj[k] = isM ? ({ vol: .18, voice: 1, from: 0 })[k] : DEF[k]; sync(); draw(); };
    });
    function sync() { Object.keys(inputs).forEach(k => { const obj = ['vol', 'voice', 'from'].includes(k) ? mus : st; inputs[k].value = obj[k]; inputs[k].previousElementSibling.querySelector('small').textContent = SL[k][4](+obj[k]); });
      K('fmts').querySelectorAll('button').forEach(b2 => b2.setAttribute('aria-pressed', String(b2.dataset.f === st.fmt)));
      K('duck').checked = mus.duck; K('fade').checked = mus.fade;
      K('mname').textContent = file ? '🎵 ' + file.name.replace(/\.\w+$/, '') : 'Noch keine Musik'; K('mdel').hidden = !file;
      ['vol', 'from'].forEach(k => inputs[k].closest('.tc-sl').style.opacity = file ? '' : '.45'); K('duck').closest('label').style.opacity = K('fade').closest('label').style.opacity = file ? '' : '.45'; }
    K('fmts').innerHTML = FMTS.map(f => '<button type="button" data-f="' + f[0] + '">' + f[1] + '</button>').join('');
    K('fmts').querySelectorAll('button').forEach(b2 => b2.onclick = () => { st.fmt = b2.dataset.f; sync(); draw(); });
    K('duck').onchange = () => { mus.duck = K('duck').checked; ls.set('se-mus', mus); };
    K('fade').onchange = () => { mus.fade = K('fade').checked; ls.set('se-mus', mus); };
    K('resetLight').onclick = () => { ['ex', 'con', 'wm', 'sat', 'sh', 'hi'].forEach(k => st[k] = DEF[k]); curMine = ''; sync(); drawMine(); draw(); };
    // ---- 💛 Meine Filter (Vivien, 28.10.2026): Licht & Farbe speichern, antippen = übernehmen, × = löschen ----
    const LK = ['ex', 'con', 'wm', 'sat', 'sh', 'hi'], store = o.store || { get: () => Promise.resolve(ls.get('se-mine', [])), set: (sl, l) => Promise.resolve(l) };
    let mine = ls.get('se-mine', []), curMine = '';
    const mmsg = K('mmsg');
    function drawMine() {
      K('mine').innerHTML = mine.length ? mine.map((m, i) => `<span class="tc-mc"><button type="button" data-mi="${i}" aria-pressed="${String(curMine === m.n)}">${esc(m.n)}</button><button type="button" class="tc-x" data-mx="${i}" aria-label="${esc(m.n)} löschen" title="löschen">×</button></span>`).join('')
        : '<p class="tc-mmsg" style="margin:0">Stell Licht &amp; Farbe so ein, wie es dir gefällt, und speichere es hier. Beim nächsten Video ist dein Filter mit einem Tipp wieder da.</p>';
      K('mine').querySelectorAll('[data-mi]').forEach(b2 => b2.onclick = () => { const m = mine[+b2.dataset.mi]; if (!m) return; LK.forEach(k => { if (typeof m.s[k] === 'number') st[k] = m.s[k]; }); curMine = m.n; sync(); drawMine(); draw(); mmsg.textContent = '✓ „' + m.n + '“ ist eingestellt.'; });
      K('mine').querySelectorAll('[data-mx]').forEach(b2 => b2.onclick = () => { const m = mine[+b2.dataset.mx]; if (!m || !confirm('„' + m.n + '“ löschen?')) return; mine.splice(+b2.dataset.mx, 1); if (curMine === m.n) curMine = ''; putMine('„' + m.n + '“ gelöscht.'); });
    }
    function putMine(ok) {
      ls.set('se-mine', mine); drawMine(); mmsg.textContent = 'Wird gespeichert …';
      store.set('edit', mine).then(l => { if (Array.isArray(l)) { mine = l; ls.set('se-mine', mine); drawMine(); } mmsg.textContent = ok; })
        .catch(() => { mmsg.textContent = ok + ' (Nur auf diesem Gerät – die Verbindung hat gerade nicht geklappt.)'; });
    }
    const closeMine = () => { K('mform').hidden = true; K('mrow').hidden = false; };
    K('msave').onclick = () => { let n = 1; while (mine.some(m => m.n === 'Mein Filter ' + n)) n++; K('mname2').value = curMine || 'Mein Filter ' + n; K('mform').hidden = false; K('mrow').hidden = true; mmsg.textContent = ''; K('mname2').focus(); K('mname2').select(); };
    K('mno').onclick = closeMine;
    K('mname2').onkeydown = e => { e.stopPropagation(); if (e.key === 'Enter') { e.preventDefault(); K('mok').click(); } if (e.key === 'Escape') closeMine(); };
    K('mok').onclick = () => {
      const n = K('mname2').value.trim().slice(0, 40); if (!n) { K('mname2').focus(); return; }
      const v = {}; LK.forEach(k => v[k] = +st[k]);
      const i = mine.findIndex(m => m.n === n), had = i >= 0;
      if (had) mine[i] = { n, s: v }; else mine.push({ n, s: v });
      curMine = n; closeMine(); putMine(had ? '✓ „' + n + '“ ist aktualisiert.' : '✓ „' + n + '“ ist gespeichert.');
    };
    drawMine();
    store.get('edit').then(l => { if (!Array.isArray(l)) return; if (!l.length && mine.length) { store.set('edit', mine).catch(() => {}); return; } mine = l; ls.set('se-mine', mine); drawMine(); }).catch(() => {});
    K('resetFrame').onclick = () => { ['fmt', 'zoom', 'ox', 'oy', 'rot'].forEach(k => st[k] = DEF[k]); sync(); draw(); };

    // Musik
    function setMusic(f) { file = f; lastMusic = f; if (au.src) URL.revokeObjectURL(au.src); if (f) { au.src = URL.createObjectURL(f); au.onloadedmetadata = () => { inputs.from.max = Math.max(1, Math.floor(au.duration || 600) - 5); if (mus.from > +inputs.from.max) mus.from = 0; sync(); }; } else au.removeAttribute('src'); sync(); }
    K('mfile').onchange = e => { const f = e.target.files[0]; if (f) setMusic(f); e.target.value = ''; libUi(); };
    // Musik-Ordner auf dem Server (Verwaltung → 🎵 Musik)
    let lib = [], libTags = {}, libF = '', libSel = file && file._libId || 0; const pre = new Audio(); let preId = 0;
    function libUi() {
      const box = K('lib'); if (closed) return;
      if (!lib.length) { box.innerHTML = '<p class="se-note">Noch keine Lieder in deinem Musik-Ordner. <a href="?view=musik" target="_blank">🎵 Musik öffnen</a> und Lieder hineinziehen.</p>'; return; }
      const used = Object.keys(libTags).filter(k => lib.some(x => x.tags.includes(k)));
      const L = lib.filter(x => !libF || x.tags.includes(libF)), f2 = s2 => s2 > 0 ? Math.floor(s2 / 60) + ':' + String(Math.floor(s2 % 60)).padStart(2, '0') : '';
      box.innerHTML = '<b style="font-weight:500;font-size:17px;color:#4A3A41">Aus deiner Musik</b>' + (used.length ? '<div class="chips"><button type="button" data-lf="" aria-pressed="' + (!libF) + '">Alle</button>' + used.map(k => '<button type="button" data-lf="' + k + '" aria-pressed="' + (libF === k) + '">' + esc(libTags[k]) + '</button>').join('') + '</div>' : '')
        + '<ul>' + L.map(x => '<li class="' + (x.id === libSel ? 'on' : '') + '"><button type="button" data-pv="' + x.id + '" aria-label="Reinhören">' + (preId === x.id ? '❚❚' : '▶') + '</button><span>' + esc(x.t) + '<small>' + f2(x.dur) + '</small></span><button type="button" class="pk" data-take="' + x.id + '">' + (x.id === libSel ? '✓ gewählt' : 'Nehmen') + '</button></li>').join('') + '</ul>';
      box.querySelectorAll('[data-lf]').forEach(b => b.onclick = () => { libF = b.dataset.lf; libUi(); });
      box.querySelectorAll('[data-pv]').forEach(b => b.onclick = () => { const id = +b.dataset.pv; if (preId === id) { pre.pause(); preId = 0; } else { pre.src = '?view=musik&dl=' + id; pre.volume = .6; pre.play().catch(() => {}); preId = id; } libUi(); });
      box.querySelectorAll('[data-take]').forEach(b => b.onclick = () => { b.disabled = true; b.textContent = 'Lädt …'; take(+b.dataset.take, false); });
    }
    async function take(id, auto) {
      const x = lib.find(y => y.id === id); if (!x) return;
      try { const r = await fetch('?view=musik&dl=' + id, { credentials: 'same-origin' }); const bl = await r.blob(); if (closed) return; const f = new File([bl], x.t + '.mp3', { type: bl.type || 'audio/mpeg' }); f._libId = id;
        pre.pause(); preId = 0; libSel = id; setMusic(f); msg.className = 'se-msg ok';
        msg.textContent = auto ? '🎵 Ich habe „' + x.t + '“ darunter gelegt, leise und leiser, wenn du sprichst. Passt es nicht, wähl unter „Musik darunter“ ein anderes Lied oder tippe auf „Musik entfernen“.' : '🎵 „' + x.t + '“ liegt jetzt darunter. Mit ▶ Abspielen anhören.'; } catch (e) { msg.textContent = 'Das Lied konnte nicht laden.'; }
      libUi();
    }
    // Passendes Lied automatisch darunterlegen (Vivien, 28.10.2026: „muss ich das selber machen?“)
    function autoPick() {
      const hint = o.musicHint || []; if (file || !hint.length || !lib.length) return;
      let m = []; for (const t of hint) { m = lib.filter(x => x.tags.includes(t)); if (m.length) break; }
      if (!m.length) m = lib.filter(x => !x.tags.length || x.tags.some(t => hint.includes(t))); if (!m.length) m = lib;
      const seed = String(o.seed || ''), n = [...seed].reduce((a, c) => (a * 31 + c.charCodeAt(0)) >>> 0, 7);
      mus.duck = true; mus.fade = true; sync(); take(m[n % m.length].id, true);
    }
    fetch('?view=musik&json=1', { credentials: 'same-origin' }).then(r => r.json()).then(j => { lib = j.list || []; libTags = j.tags || {}; libUi(); autoPick(); }).catch(() => { K('lib').innerHTML = ''; });
    K('mdel').onclick = () => { au.pause(); setMusic(null); libSel = 0; libUi(); };
    if (file) setMusic(file);
    function syncMusic() { if (!file || !au.duration) return; const d = au.duration, t = mus.from + Math.max(0, v.currentTime - a); au.currentTime = d ? t % d : 0; }
    function mixTick() {
      if (!ac) return; const now = ac.currentTime, t = v.currentTime;
      vGain.gain.setTargetAtTime(mus.voice, now, .05);
      let g = file ? mus.vol : 0;
      if (file && mus.fade) { const fi = Math.min(1, (t - a) / 1.5), fo = Math.min(1, (b - t) / 2.5); g *= Math.max(0, Math.min(fi, fo)); }
      if (file && mus.duck && an) { an.getFloatTimeDomainData(abuf); let s = 0; for (const x of abuf) s += x * x; const rms = Math.sqrt(s / abuf.length); if (rms > .025) duckT = now; if (now - duckT < .6) g *= .35; }
      mGain.gain.setTargetAtTime(g, now, (g < mGain.gain.value ? .12 : .45));
    }
    let duckT = -9;

    // ---- Zeitleiste ----
    function cutUi() {
      const D = dur || 1, sel = q('.se-scrub .sel'); sel.style.left = (a / D * 100) + '%'; sel.style.width = ((b - a) / D * 100) + '%';
      K('cut').textContent = 'Video: ' + fmt(a) + ' bis ' + fmt(b) + ' · Länge ' + fmt(b - a);
    }
    function timeUi() { K('time').textContent = fmt(v.currentTime) + ' / ' + fmt(dur); if (!scrubbing) scrub.value = Math.round(v.currentTime / (dur || 1) * 1000); }
    let scrubbing = false;
    scrub.oninput = () => { scrubbing = true; v.currentTime = +scrub.value / 1000 * dur; timeUi(); };
    scrub.onchange = () => { scrubbing = false; };
    K('setA').onclick = () => { a = Math.min(v.currentTime, b - .5); cutUi(); };
    K('setB').onclick = () => { b = Math.max(v.currentTime, a + .5); cutUi(); };
    K('resetCut').onclick = () => { a = 0; b = dur; cutUi(); };
    v.addEventListener('seeked', () => { draw(); timeUi(); });
    v.addEventListener('timeupdate', timeUi);

    // ---- Abspielen ----
    let playing = false, raf = 0;
    function loop() { if (!playing) return; draw(); mixTick(); timeUi(); if (v.currentTime >= b - .03 && !rendering) { stop(); v.currentTime = a; return; } raf = v.requestVideoFrameCallback ? v.requestVideoFrameCallback(loop) : requestAnimationFrame(loop); }
    async function play() {
      if (busy) return; audio(); musicSrc(); await ac.resume().catch(() => {});
      if (v.currentTime < a || v.currentTime >= b - .05) v.currentTime = a;
      spk.gain.value = 1; syncMusic(); playing = true;
      try { await v.play(); } catch (e) { playing = false; msg.textContent = 'Abspielen klappt nicht. Bitte tippe nochmal auf ▶.'; return; }
      if (file) au.play().catch(() => {}); K('play').textContent = '❚❚ Pause'; big.hidden = true; loop();
    }
    function stop() { playing = false; v.pause(); au.pause(); K('play').textContent = '▶ Abspielen'; big.hidden = false; draw(); }
    K('play').onclick = () => playing ? stop() : play(); big.onclick = play;
    K('cmp').onclick = () => { cmp = !cmp; K('cmp').classList.toggle('dk', cmp); K('cmp').textContent = cmp ? 'Nachher zeigen' : 'Vorher zeigen'; q('.se-tag').textContent = cmp ? 'Vorher' : ''; draw(); };

    // Bild in der Vorschau verschieben
    let drag = null;
    q('.se-pv').addEventListener('pointerdown', e => { if (e.target === big || busy || e.pointerType === 'touch') return; const g = geo(); drag = { x: e.clientX, y: e.clientY, ox: st.ox, oy: st.oy, g, k: cv.width / cv.getBoundingClientRect().width }; q('.se-pv').setPointerCapture(e.pointerId); });
    q('.se-pv').addEventListener('pointermove', e => { if (!drag) return; const g = drag.g, dx = (e.clientX - drag.x) * drag.k / g.sc, dy = (e.clientY - drag.y) * drag.k / g.sc;
      if (g.mx > 0) st.ox = Math.max(-1, Math.min(1, drag.ox - dx / g.mx)); if (g.my > 0) st.oy = Math.max(-1, Math.min(1, drag.oy - dy / g.my)); sync(); draw(); });
    q('.se-pv').addEventListener('pointerup', () => { drag = null; });
    q('.se-pv').addEventListener('wheel', e => { if (busy) return; e.preventDefault(); st.zoom = Math.max(1, Math.min(2.5, st.zoom * (e.deltaY < 0 ? 1.04 : 1 / 1.04))); sync(); draw(); }, { passive: false });

    // ---- Automatisch verbessern: ein paar Bilder ansehen, Helligkeit/Schatten/Wärme/Kontrast setzen ----
    async function seek(t) { return new Promise(ok => { const h = () => { v.removeEventListener('seeked', h); ok(); }; v.addEventListener('seeked', h); v.currentTime = t; setTimeout(h, 1500); }); }
    K('auto').onclick = async () => {
      if (busy || !v.videoWidth) return; stop(); const keep = v.currentTime, c2 = document.createElement('canvas'); c2.width = 96; c2.height = Math.max(2, Math.round(96 * v.videoHeight / v.videoWidth));
      const g2 = c2.getContext('2d', { willReadFrequently: true }), L = []; let R = 0, B = 0, n = 0;
      for (const f of [.2, .5, .8]) { await seek(a + (b - a) * f); g2.drawImage(v, 0, 0, c2.width, c2.height); const d = g2.getImageData(0, 0, c2.width, c2.height).data;
        for (let i = 0; i < d.length; i += 4) { L.push(.2126 * d[i] + .7152 * d[i + 1] + .0722 * d[i + 2]); R += d[i]; B += d[i + 2]; n++; } }
      await seek(keep); L.sort((x, y) => x - y);
      const pc = p => L[Math.min(L.length - 1, Math.floor(L.length * p))] / 255, mean = L.reduce((x, y) => x + y, 0) / L.length / 255;
      const p5 = pc(.05), p95 = pc(.95), p99 = pc(.99);
      st.ex = Math.max(-.6, Math.min(.8, Math.log2(.47 / Math.max(.05, mean)) * .8));
      st.sh = p5 < .08 ? .35 : p5 < .15 ? .2 : .08;
      st.hi = p99 * Math.pow(2, st.ex) > .97 ? .35 : .1;
      const spread = p95 - p5; st.con = spread < .45 ? .25 : spread < .6 ? .12 : 0;
      const cast = (R - B) / n / 255; st.wm = Math.max(-.3, Math.min(.5, .12 - cast * 2));
      st.sat = 1.06; sync(); draw(); msg.className = 'se-msg'; msg.textContent = '✨ Licht angepasst. Mit „Vorher zeigen“ vergleichen, mit den Reglern feiner einstellen.';
    };

    // ---- Fertig stellen (Echtzeit) ----
    let rendering = false;
    K('done').onclick = async () => {
      if (busy || !gl) return; if (!window.MediaRecorder || !cv.captureStream) { msg.textContent = 'Dieser Browser kann das Video nicht neu speichern. Bitte Chrome oder Safari nehmen.'; return; }
      stop(); busy = rendering = true; root.classList.add('se-busy'); K('done').disabled = K('play').disabled = true;
      audio(); musicSrc(); await ac.resume().catch(() => {}); spk.gain.value = 0; // beim Fertigstellen nichts über die Lautsprecher
      await seek(a); syncMusic();
      const mt = mime(), out = cv.captureStream(30); recDest.stream.getAudioTracks().forEach(t => out.addTrack(t));
      const ch = [], r = new MediaRecorder(out, Object.assign(mt ? { mimeType: mt } : {}, { videoBitsPerSecond: Wo * Ho > 1.5e6 ? 8000000 : 5000000, audioBitsPerSecond: 160000 }));
      r.ondataavailable = e => { if (e.data && e.data.size) ch.push(e.data); }; const done = new Promise(ok => r.onstop = ok);
      const bar = q('.se-prog'), fill = bar.querySelector('i'); bar.hidden = false; msg.className = 'se-msg'; msg.textContent = 'Wird fertig gestellt … dauert so lang wie das Video (' + fmt(b - a) + '). Seite offen lassen.';
      draw(); r.start(500); playing = true;
      try { await v.play(); } catch (e) { }
      if (file) au.play().catch(() => {});
      await new Promise(ok => { const step = () => { if (closed) { ok(); return; } draw(); mixTick(); fill.style.width = Math.min(100, (v.currentTime - a) / (b - a) * 100) + '%';
        if (v.ended || v.currentTime >= b - .02) { ok(); return; } if (v.requestVideoFrameCallback) v.requestVideoFrameCallback(step); else requestAnimationFrame(step); }; step(); v.onended = ok; });
      draw(); await new Promise(x => setTimeout(x, 150)); r.stop(); await done; playing = false; v.pause(); au.pause(); out.getVideoTracks().forEach(t => t.stop());
      spk.gain.value = 1; busy = rendering = false; root.classList.remove('se-busy'); K('done').disabled = K('play').disabled = false; bar.hidden = true;
      if (closed) return;
      const type = (r.mimeType || mt || 'video/webm').split(';')[0], nb = new Blob(ch, { type });
      if (!nb.size) { msg.textContent = 'Das hat nicht geklappt. Bitte nochmal „Fertig stellen“.'; return; }
      const info = { a, b, dur: b - a, music: file ? file.name : '', fmt: st.fmt };
      close(); o.onDone && o.onDone(nb, info);
    };
    K('cancel').onclick = () => { if (busy && !confirm('Fertigstellen abbrechen?')) return; close(); o.onCancel && o.onCancel(); };
    function close() { closed = true; stop(); try { pre.pause(); } catch (e) {} removeEventListener('resize', onRs); try { ac && ac.close(); } catch (e) {} URL.revokeObjectURL(url); if (au.src) au.pause(); mount.innerHTML = ''; }

    // ---- Laden (MediaRecorder-Dateien kennen ihre Länge oft nicht: kurz ans Ende springen) ----
    v.addEventListener('loadedmetadata', async () => {
      dur = isFinite(v.duration) && v.duration > 0 ? v.duration : 0;
      if (!dur) { await seek(1e7); dur = isFinite(v.duration) && v.duration > 0 ? v.duration : (v.currentTime || o.dur || 0); await seek(0); }
      if (!dur) dur = o.dur || 1;
      a = 0; b = dur; outSize(); cutUi(); timeUi(); await seek(Math.min(.1, dur / 2)); draw();
    }, { once: true });
    v.addEventListener('error', () => { msg.textContent = 'Dieses Video kann der Browser nicht öffnen. Am besten MP4 nehmen.'; });
    sync();
    return { close };
  }
  window.StudioEdit = { open };
})();

/*
 * Movecia Kamera-Look (Vivien, 28.10.2026)
 * Für alle Aufnahmen, in denen Vivien selbst in die Kamera spricht: Haut sanft weich, Augenringe aufhellen,
 * Hintergrund unscharf, Licht (Helligkeit, Gesicht aufhellen wie ein Ringlicht, Wärme, Kontrast) und eine
 * Hilfe für die Kopfposition (Kreuz, Raster oder Kopf-Oval mit Hinweis „passt“).
 *
 * Alles läuft nur im Browser (WebGL), nichts wird hochgeladen. Hintergrund und Gesicht erkennt MediaPipe
 * (Google, Apache-2.0), geladen von jsDelivr bzw. storage.googleapis.com, erst wenn die Funktion gebraucht wird.
 *
 *   const tc = await TalkCam.create(rawStream, {mount: el});   // el bekommt Vorschau + Hilfslinien
 *   tc.stream()            -> MediaStream mit bearbeitetem Bild + Originalton (zum Aufnehmen)
 *   TalkCam.panel(tc, el)  -> Einstellungen (Look, Regler, Hilfslinien, Format)
 *   TalkCam.wrap(raw)      -> bearbeiteter Stream mit den gespeicherten Einstellungen (oder raw, wenn aus)
 *   TalkCam.button(el)     -> Knopf „✨ Kamera-Look“, öffnet ein Fenster mit Vorschau und Einstellungen
 * Hilfslinien und Teleprompter liegen nur über der Vorschau, sie sind nie in der Aufnahme.
 */
(function () {
  'use strict';
  if (window.TalkCam) return;
  const MP = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14';
  const SEG_MODEL = 'https://storage.googleapis.com/mediapipe-models/image_segmenter/selfie_segmenter/float16/latest/selfie_segmenter.tflite';
  const FACE_MODEL = 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task';
  const KEY = 'mv-talkcam';
  const DEF = { on: true, look: 'natur', beauty: 35, eyes: 45, blur: 0, light: 0, ring: 25, warm: 8, contrast: 0, blush: 0, eyeBig: 0, eyeBright: 20, lashes: 15, brows: 15, lipColor: 'rose', lip: 0, lipBig: 0, filter: 'none', filterAmt: 70, guide: 'oval', mirror: true, aspect: '16:9' };
  // Looks setzen Haut, Augen, Lippen und Farbfilter auf einmal (danach frei anpassbar)
  const LOOKS = {
    natur: { label: 'Natürlich', beauty: 35, eyes: 45, ring: 25, warm: 8, light: 0, contrast: 0, blush: 0, eyeBig: 0, eyeBright: 20, lashes: 15, brows: 15, lip: 0, lipBig: 0, filter: 'none' },
    frisch: { label: 'Frisch ohne Make-up', beauty: 55, eyes: 75, ring: 40, warm: 12, light: 6, contrast: -4, blush: 25, eyeBig: 8, eyeBright: 35, lashes: 35, brows: 30, lipColor: 'rose', lip: 30, lipBig: 8, filter: 'golden', filterAmt: 35 },
    glam: { label: 'Make-up-Look', beauty: 55, eyes: 65, ring: 35, warm: 8, light: 4, contrast: 4, blush: 35, eyeBig: 18, eyeBright: 45, lashes: 65, brows: 55, lipColor: 'beere', lip: 55, lipBig: 22, filter: 'rose', filterAmt: 40 },
    golden: { label: 'Golden Hour', beauty: 45, eyes: 55, ring: 35, warm: 18, light: 8, contrast: -6, blush: 20, eyeBig: 6, eyeBright: 30, lashes: 30, brows: 25, lipColor: 'koralle', lip: 30, lipBig: 8, filter: 'golden', filterAmt: 70 },
    klar: { label: 'Klar & frisch', beauty: 25, eyes: 40, ring: 20, warm: -4, light: 4, contrast: 8, blush: 10, eyeBig: 0, eyeBright: 40, lashes: 25, brows: 20, lip: 15, lipColor: 'nude', lipBig: 0, filter: 'klar', filterAmt: 60 },
  };
  const LIPS = { natur: ['Natur', [.62, .33, .34]], rose: ['Rosé', [.82, .44, .5]], nude: ['Nude', [.74, .52, .45]], koralle: ['Koralle', [.9, .44, .37]], beere: ['Beere', [.56, .17, .3]], rot: ['Rot', [.72, .12, .16]] };
  // Farbfilter (wie bei Instagram): Sättigung, Kontrast, Aufhellen der Tiefen, Farbstich in Schatten/Lichtern, Schwarz-Weiß
  const FILTERS = {
    none: ['Kein', { sat: 1, con: 1, fade: 0, sh: [0, 0, 0], hi: [0, 0, 0], bw: 0 }],
    golden: ['Golden', { sat: 1.08, con: 1.04, fade: .03, sh: [.03, .015, -.03], hi: [.07, .035, -.05], bw: 0 }],
    rose: ['Rosé', { sat: 1, con: .97, fade: .045, sh: [.045, 0, .03], hi: [.045, .01, .025], bw: 0 }],
    pastell: ['Pastell', { sat: .8, con: .88, fade: .08, sh: [.02, .02, .045], hi: [.025, .012, 0], bw: 0 }],
    film: ['Film', { sat: .9, con: 1.08, fade: .07, sh: [-.015, .03, .045], hi: [.05, .03, -.025], bw: 0 }],
    klar: ['Klar', { sat: 1.14, con: 1.1, fade: 0, sh: [0, 0, .012], hi: [.01, .005, 0], bw: 0 }],
    mono: ['Schwarz-Weiß', { sat: 1, con: 1.12, fade: .04, sh: [0, 0, 0], hi: [.02, .015, 0], bw: 1 }],
  };

  function load() { try { return Object.assign({}, DEF, JSON.parse(localStorage.getItem(KEY) || '{}')); } catch (e) { return Object.assign({}, DEF); } }
  function save(s) { try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {} }

  // ---------- MediaPipe (einmal laden, von allen Kameras geteilt) ----------
  let mpPromise = null, segPromise = null, facePromise = null;
  function vision() {
    if (!mpPromise) mpPromise = import(MP + '/vision_bundle.mjs').then(async m => ({ m, files: await m.FilesetResolver.forVisionTasks(MP + '/wasm') }));
    return mpPromise;
  }
  async function makeTask(kind) {
    const { m, files } = await vision();
    const opts = d => kind === 'seg'
      ? { baseOptions: { modelAssetPath: SEG_MODEL, delegate: d }, runningMode: 'VIDEO', outputCategoryMask: false, outputConfidenceMasks: true }
      : { baseOptions: { modelAssetPath: FACE_MODEL, delegate: d }, runningMode: 'VIDEO', numFaces: 1 };
    const C = kind === 'seg' ? m.ImageSegmenter : m.FaceLandmarker;
    try { return await C.createFromOptions(files, opts('GPU')); } catch (e) { return await C.createFromOptions(files, opts('CPU')); }
  }
  function segmenter() { if (!segPromise) segPromise = makeTask('seg').catch(e => { segPromise = null; throw e; }); return segPromise; }
  function faceTask() { if (!facePromise) facePromise = makeTask('face').catch(e => { facePromise = null; throw e; }); return facePromise; }

  // ---------- Shader ----------
  const VS = `#version 300 es
in vec2 p; out vec2 uv;
void main(){ uv = vec2(p.x*.5+.5, .5-p.y*.5); gl_Position = vec4(p,0.,1.); }`;
  // uv: Ursprung oben links. Video und Maske liegen „oben zuerst“, eigene Zwischenbilder (FBO) „unten zuerst“.
  const FS_BASE = `#version 300 es
precision highp float; in vec2 uv; out vec4 o;
uniform sampler2D vid; uniform vec4 crop;
void main(){ o = vec4(texture(vid, crop.xy + uv*crop.zw).rgb, 1.); }`;
  const FS_DOWN = `#version 300 es
precision highp float; in vec2 uv; out vec4 o;
uniform sampler2D base; uniform sampler2D mask; uniform vec4 crop; uniform float useMask;
void main(){
  vec3 c = texture(base, vec2(uv.x, 1.-uv.y)).rgb;
  float m = useMask > .5 ? smoothstep(.15,.6, texture(mask, crop.xy + uv*crop.zw).r) : 0.;
  float a = 1.-m; o = vec4(c*a, a);
}`;
  const FS_BLUR = `#version 300 es
precision highp float; in vec2 uv; out vec4 o;
uniform sampler2D src; uniform vec2 dir;
void main(){
  vec2 t = vec2(uv.x, 1.-uv.y);
  vec4 s = texture(src,t)*.2270270;
  s += (texture(src,t+dir*1.3846154)+texture(src,t-dir*1.3846154))*.3162162;
  s += (texture(src,t+dir*3.2307692)+texture(src,t-dir*3.2307692))*.0702703;
  o = s;
}`;
  const FS_FINAL = `#version 300 es
precision highp float; in vec2 uv; out vec4 o;
uniform sampler2D base; uniform sampler2D bg; uniform sampler2D mask; uniform sampler2D fmask;
uniform vec4 crop; uniform vec2 res;
uniform float beauty, eyes, blur, light, ring, warm, contrast, useMask, hasFace, bypass;
uniform float blush, eyeBig, eyeBright, lashes, brows, lip, lipBig;
uniform vec3 lipCol;
uniform float fSat, fCon, fFade, fBW; uniform vec3 fSh, fHi;
uniform vec2 eyeL, eyeR, eyeRad; uniform vec2 faceC, faceR;
uniform vec2 eyeCL, eyeCR; uniform float eyeW; uniform vec2 lipC, lipRad; uniform vec2 chL, chR;
vec3 B(vec2 u){ return texture(base, vec2(u.x, 1.-u.y)).rgb; }
float luma(vec3 c){ return dot(c, vec3(.299,.587,.114)); }
vec4 FM(vec2 u){ vec2 d = 1.5/res; return (texture(fmask,u)*2. + texture(fmask,u+vec2(d.x,0.)) + texture(fmask,u-vec2(d.x,0.)) + texture(fmask,u+vec2(0.,d.y)) + texture(fmask,u-vec2(0.,d.y)))/6.; }
float skinOf(vec3 c){
  float y = luma(c); float cb = .5 + (-.168736*c.r - .331264*c.g + .5*c.b); float cr = .5 + (.5*c.r - .418688*c.g - .081312*c.b);
  float s = smoothstep(.28,.32,cb) * (1.-smoothstep(.50,.54,cb)) * smoothstep(.51,.545,cr) * (1.-smoothstep(.67,.71,cr));
  return s * smoothstep(.08,.2,y);
}
// Farbe übernehmen, Helligkeit behalten (wie Ebenenmodus „Farbe“)
vec3 setLum(vec3 c, float l){ float d = l - luma(c); c += d; float L = luma(c), n = min(min(c.r,c.g),c.b), x = max(max(c.r,c.g),c.b);
  if (n < 0.) c = L + (c-L)*L/max(L-n,1e-4); if (x > 1.) c = L + (c-L)*(1.-L)/max(x-L,1e-4); return c; }
vec2 mag(vec2 p, vec2 c, vec2 R, float k){ vec2 d = p-c; float r = length(d/R); if (r < 1.) { float t = 1.-r*r; d *= 1.-k*t*t; } return c+d; }
void main(){
  vec2 wuv = uv;
  if (bypass < .5 && hasFace > .5) {
    vec2 p = uv*res;
    if (eyeBig > 0.) { vec2 R = vec2(eyeW*1.05, eyeW*.85); p = mag(p, eyeCL*res, R, eyeBig*.2); p = mag(p, eyeCR*res, R, eyeBig*.2); }
    if (lipBig > 0.) p = mag(p, lipC*res, lipRad*1.35, lipBig*.17);
    wuv = p/res;
  }
  vec3 c = B(wuv);
  if (bypass > .5) { o = vec4(c,1.); return; }
  vec2 px = 1./res;
  float m = useMask > .5 ? smoothstep(.35,.75, texture(mask, crop.xy + uv*crop.zw).r) : 1.;
  vec4 fm = hasFace > .5 ? FM(wuv) : vec4(0.);
  float fd = hasFace > .5 ? length((uv-faceC)*res/faceR) : 0.;
  float faceW = hasFace > .5 ? 1.-smoothstep(1.0, 1.45, fd) : 1.;
  float ew = 0.;
  if (hasFace > .5 && eyes > 0.) {
    float dl = length((uv-eyeL)*res/eyeRad), dr = length((uv-eyeR)*res/eyeRad);
    ew = max(1.-smoothstep(.35,1.,dl), 1.-smoothstep(.35,1.,dr));
  }
  // Haut weich (Kanten bleiben), nur auf Haut, nicht auf Lippen, Brauen, Wimpern
  float sk = skinOf(c) * (1. - clamp(fm.r + fm.g + fm.b, 0., 1.));
  float amt = (beauty*.9 + ew*eyes*.5) * sk * faceW * m;
  if (amt > .002) {
    float r = max(2., (hasFace > .5 ? faceR.x : res.y*.12) * .045);
    vec3 acc = c; float ws = 1.;
    for (int i = 0; i < 12; i++) {
      float a = float(i) * .5235988; vec2 d = vec2(cos(a), sin(a));
      for (int k = 1; k <= 2; k++) {
        vec3 s = B(wuv + d * r * float(k) * (k == 2 ? 1. : .5) * px);
        vec3 df = s - c; float w = exp(-dot(df,df) * 160.);
        acc += s * w; ws += w;
      }
    }
    c = mix(c, acc / ws, clamp(amt, 0., .92));
  }
  // Augenringe
  if (ew > 0.) {
    vec2 eye = length((uv-eyeL)*res) < length((uv-eyeR)*res) ? eyeL : eyeR;
    vec2 ck = eye + vec2(0., eyeRad.y * 2.4) * px;
    vec3 cheek = (B(ck) + B(ck + vec2(eyeRad.x*.4, 0.)*px) + B(ck - vec2(eyeRad.x*.4, 0.)*px) + B(ck + vec2(0., eyeRad.y*.5)*px)) * .25;
    float lc = luma(c), lk = luma(cheek);
    vec3 fix = c * clamp(lk / max(lc, .03), 1., 1.45);
    fix = mix(fix, cheek * (luma(fix) / max(lk, .03)), .35);
    c = mix(c, fix, clamp(ew * eyes * mix(.5, 1., sk), 0., 1.));
  }
  if (hasFace > .5) {
    // Augen strahlen: Weiß und Iris klarer, leicht geschärft
    if (eyeBright > 0.) {
      vec2 R = vec2(eyeW*.6, eyeW*.32);
      float w = max(1.-smoothstep(.45,1.,length((wuv-eyeCL)*res/R)), 1.-smoothstep(.45,1.,length((wuv-eyeCR)*res/R)));
      if (w > 0.) {
        vec3 bl = (B(wuv+vec2(2.,0.)*px)+B(wuv-vec2(2.,0.)*px)+B(wuv+vec2(0.,2.)*px)+B(wuv-vec2(0.,2.)*px))*.25;
        vec3 sh = c + (c - bl) * 1.2;
        float l = luma(sh); sh = mix(sh, sh * 1.12 + .02, smoothstep(.35, .8, l));
        c = mix(c, clamp(sh,0.,1.), w * eyeBright * .85);
      }
    }
    // Wimpern betonen: nur was am Lidrand schon dunkel ist, wird kräftiger (keine aufgemalte Linie)
    if (lashes > 0. && fm.b > .01) {
      vec3 mean = (B(wuv+vec2(0.,eyeW*.12)*px) + B(wuv-vec2(0.,eyeW*.12)*px) + B(wuv+vec2(eyeW*.12,0.)*px) + B(wuv-vec2(eyeW*.12,0.)*px)) * .25;
      float hair = clamp((luma(mean) - luma(c)) * 6. + .1, 0., 1.);
      c = mix(c, pow(c, vec3(1.6)) * .8, clamp(fm.b * lashes * hair * 1.2, 0., .9));
    }
    // Brauen betonen: nur die Härchen dunkler und dichter (Lücken füllen), Haut dazwischen bleibt
    if (brows > 0. && fm.g > .01) {
      float r = eyeW * .08; vec3 mn = c, mean = vec3(0.);
      for (int i = 0; i < 8; i++) { float a = float(i) * .785398; vec3 sm = B(wuv + vec2(cos(a), sin(a)) * r * px); mn = min(mn, sm); mean += sm; }
      mean *= .125;
      float hair = clamp((luma(mean) - luma(c)) * 7. + .2, 0., 1.);
      vec3 fill = mix(c, mn, .45 * hair) * mix(1., .8, hair);
      c = mix(c, fill, clamp(fm.g * brows, 0., .8));
    }
    // Rouge
    if (blush > 0.) {
      vec2 R = vec2(eyeW*1.05, eyeW*.75);
      float w = max(1.-smoothstep(.0,1.,length((uv-chL)*res/R)), 1.-smoothstep(.0,1.,length((uv-chR)*res/R)));
      vec3 bc = setLum(vec3(.86,.42,.48), luma(c));
      c = mix(c, bc, w * blush * .42 * mix(.4, 1., skinOf(c)));
    }
    // Lippenfarbe: Farbton der gewählten Farbe, Licht und Struktur der Lippen bleiben
    if (lip > 0. && fm.r > .01) {
      float l = luma(c);
      vec3 tint = setLum(lipCol, min(mix(l, luma(lipCol), .3), l * 1.04));
      tint += (c - B(wuv + vec2(1.5,1.5)*px)) * .6;
      c = mix(c, clamp(tint,0.,1.), clamp(fm.r * lip * .8, 0., .78));
    }
  }
  // Glow / Gesicht aufhellen
  if (ring > 0.) {
    float rw = hasFace > .5 ? 1.-smoothstep(.55, 1.6, fd) : m * .6;
    c = c + (1.-c) * ring * .22 * rw * (1. - .5*luma(c));
  }
  if (blur > 0.) {
    vec4 b = texture(bg, vec2(uv.x, 1.-uv.y));
    vec3 bc = b.a > .02 ? b.rgb / b.a : c;
    bc = mix(bc, bc * .9 + .02, blur * .5);
    c = mix(bc, c, m);
  }
  // Licht
  c *= exp2(light * 1.1);
  c = (c - .5) * (1. + contrast * .7) + .5;
  c += vec3(.07, .02, -.07) * warm;
  // Farbfilter
  c = clamp(c, 0., 1.);
  float L = luma(c);
  c += fSh * (1.-L) + fHi * L;
  c = mix(vec3(luma(c)), c, fSat);
  c = (c - .5) * fCon + .5;
  c = c * (1. - fFade) + fFade * vec3(1., .98, .96);
  c = mix(c, vec3(luma(c)), fBW);
  o = vec4(clamp(c, 0., 1.), 1.);
}`;

  function compile(gl, vs, fs) {
    const sh = (t, s) => { const x = gl.createShader(t); gl.shaderSource(x, s); gl.compileShader(x); if (!gl.getShaderParameter(x, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(x)); return x; };
    const p = gl.createProgram(); gl.attachShader(p, sh(gl.VERTEX_SHADER, vs)); gl.attachShader(p, sh(gl.FRAGMENT_SHADER, fs));
    gl.bindAttribLocation(p, 0, 'p'); gl.linkProgram(p);
    if (!gl.getProgramParameter(p, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(p));
    const u = {}; const n = gl.getProgramParameter(p, gl.ACTIVE_UNIFORMS);
    for (let i = 0; i < n; i++) { const a = gl.getActiveUniform(p, i); u[a.name] = gl.getUniformLocation(p, a.name); }
    return { p, u };
  }

  // ---------- Kamera mit Look ----------
  class Cam {
    constructor(raw, opts) {
      this.raw = raw; this.opts = opts || {}; this.s = load();
      if (this.opts.aspect) this.s.aspect = this.opts.aspect;
      this.listeners = []; this.face = null; this.faceT = 0; this.maskArr = null; this.frame = 0; this.dead = false;
      this.status = ''; this.compare = false; this.fps = 30;
      this.video = document.createElement('video'); this.video.muted = true; this.video.playsInline = true; this.video.setAttribute('playsinline', '');
      this.video.srcObject = new MediaStream(raw.getVideoTracks());
      this.canvas = document.createElement('canvas'); this.canvas.className = 'tc-cv';
      this.ok = this.initGL();
    }
    initGL() {
      const gl = this.canvas.getContext('webgl2', { premultipliedAlpha: false, preserveDrawingBuffer: false, antialias: false });
      if (!gl) { this.ctx2d = this.canvas.getContext('2d'); return false; }
      try {
        this.gl = gl;
        this.pBase = compile(gl, VS, FS_BASE); this.pDown = compile(gl, VS, FS_DOWN); this.pBlur = compile(gl, VS, FS_BLUR); this.pFin = compile(gl, VS, FS_FINAL);
        const buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
        gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
        const tex = () => { const t = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, t); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE); return t; };
        this.tVid = tex(); this.tMask = tex();
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.R8, 1, 1, 0, gl.RED, gl.UNSIGNED_BYTE, new Uint8Array([255]));
        // Gesichtsmaske: R = Lippen, G = Brauen, B = Wimpernrand (aus den Gesichtspunkten gezeichnet)
        this.tFace = tex(); gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, new Uint8Array([0, 0, 0, 0]));
        this.fc = document.createElement('canvas'); this.fx = this.fc.getContext('2d');
        this.tex = tex; this.fbos = {};
        return true;
      } catch (e) { console.warn('TalkCam WebGL', e); return false; }
    }
    fbo(name, w, h) {
      const gl = this.gl; let f = this.fbos[name];
      if (f && f.w === w && f.h === h) return f;
      if (f) { gl.deleteTexture(f.t); gl.deleteFramebuffer(f.f); }
      const t = this.tex(); gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA8, w, h, 0, gl.RGBA, gl.UNSIGNED_BYTE, null);
      const fb = gl.createFramebuffer(); gl.bindFramebuffer(gl.FRAMEBUFFER, fb); gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, t, 0);
      f = this.fbos[name] = { t, f: fb, w, h }; return f;
    }
    async start() {
      try { await this.video.play(); } catch (e) {}
      await new Promise(r => { if (this.video.videoWidth) r(); else this.video.addEventListener('loadedmetadata', r, { once: true }); });
      this.layout();
      this.ensureModels();
      const loop = () => {
        if (this.dead) return;
        this.draw();
        if (document.hidden) this.timer = setTimeout(loop, 1000 / this.fps);
        else if (this.video.requestVideoFrameCallback && !this.opts.noRVFC) this.video.requestVideoFrameCallback(loop);
        else this.raf = requestAnimationFrame(loop);
      };
      loop();
      return this;
    }
    layout() {
      const vw = this.video.videoWidth || 1280, vh = this.video.videoHeight || 720;
      const [aw, ah] = this.s.aspect === '9:16' ? [9, 16] : this.s.aspect === '1:1' ? [1, 1] : [16, 9];
      let cw = vw, ch = vw * ah / aw; if (ch > vh) { ch = vh; cw = vh * aw / ah; }
      this.crop = [(vw - cw) / 2 / vw, (vh - ch) / 2 / vh, cw / vw, ch / vh];
      const long = this.opts.maxLong || (Math.max(cw, ch) >= 1700 ? 1920 : 1280);
      const sc = Math.min(1, long / Math.max(cw, ch));
      const W = Math.round(cw * sc / 2) * 2, H = Math.round(ch * sc / 2) * 2;
      if (this.canvas.width !== W || this.canvas.height !== H) { this.canvas.width = W; this.canvas.height = H; }
      this.emit('layout');
    }
    set(patch) {
      const aspectBefore = this.s.aspect;
      Object.assign(this.s, patch); if (!this.opts.noSave) save(this.s);
      if (this.s.aspect !== aspectBefore) this.layout();
      this.ensureModels(); this.emit('change');
    }
    on(f) { this.listeners.push(f); }
    emit(t) { this.listeners.forEach(f => { try { f(t, this); } catch (e) {} }); }
    needFace() { const s = this.s; return s.on && (s.eyes > 0 || s.ring > 0 || s.beauty > 0 || s.blush > 0 || s.eyeBig > 0 || s.eyeBright > 0 || s.lashes > 0 || s.brows > 0 || s.lip > 0 || s.lipBig > 0) || s.guide === 'oval'; }
    needSeg() { return this.s.on && this.s.blur > 0; }
    ensureModels() {
      if (!this.ok) return;
      const T = window.TalkCam._test;
      if (this.needSeg() && !this.seg && !this.segLoading && !(T && T.mask)) {
        this.segLoading = true; this.setStatus('Hintergrund-Erkennung lädt …');
        segmenter().then(s => { this.seg = s; this.setStatus(''); }).catch(e => { console.warn(e); this.setStatus('Der Hintergrund-Weichzeichner konnte nicht laden. Bitte Seite neu laden oder Chrome/Safari verwenden.'); }).finally(() => { this.segLoading = false; });
      }
      if (this.needFace() && !this.fl && !this.faceLoading && !(T && T.face)) {
        this.faceLoading = true;
        faceTask().then(f => { this.fl = f; }).catch(e => { console.warn(e); this.setStatus('Die Gesichtserkennung konnte nicht laden. Licht und Haut wirken trotzdem, nur ohne Augenringe.'); }).finally(() => { this.faceLoading = false; });
      }
    }
    setStatus(t) { this.status = t; this.emit('status'); }
    analyse(now) {
      const v = this.video, T = window.TalkCam._test;
      if (this.needSeg()) {
        let arr = null, w = 0, h = 0;
        if (T && T.mask) { const r = T.mask(v); arr = r.data; w = r.w; h = r.h; }
        else if (this.seg) {
          try {
            const res = this.seg.segmentForVideo(v, now);
            const mk = res.confidenceMasks && res.confidenceMasks[0];
            if (mk) { arr = mk.getAsFloat32Array(); w = mk.width; h = mk.height; }
            if (res.close) res.close();
          } catch (e) { console.warn(e); }
        }
        if (arr) {
          if (!this.maskArr || this.maskArr.length !== w * h) { this.maskArr = new Float32Array(arr); this.mask8 = new Uint8Array(w * h); }
          const a = this.maskArr, b = this.mask8;
          for (let i = 0; i < a.length; i++) { a[i] = a[i] * .45 + arr[i] * .55; b[i] = a[i] * 255; }
          const gl = this.gl; gl.bindTexture(gl.TEXTURE_2D, this.tMask); gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
          gl.texImage2D(gl.TEXTURE_2D, 0, gl.R8, w, h, 0, gl.RED, gl.UNSIGNED_BYTE, b);
          this.hasMask = true;
        }
      }
      if (this.needFace() && this.frame % 2 === 0) {
        let lm = null;
        if (T && T.face) lm = T.face(v);
        else if (this.fl) { try { const r = this.fl.detectForVideo(v, now); lm = r.faceLandmarks && r.faceLandmarks[0]; } catch (e) { console.warn(e); } }
        if (lm) { this.faceT = now; this.updFace(lm); } else if (now - this.faceT > 700) this.face = null;
      }
    }
    updFace(lm) {
      // in Ausgabe-Koordinaten (0..1, oben links), geglättet
      const [cx, cy, cw, ch] = this.crop, W = this.canvas.width, H = this.canvas.height;
      const P = i => ({ x: (lm[i].x - cx) / cw, y: (lm[i].y - cy) / ch });
      const top = P(10), chin = P(152), lc = P(234), rc = P(454);
      const eyeOut = (a, b, lid) => { const A = P(a), Bp = P(b), L = P(lid); const ew = Math.hypot((A.x - Bp.x) * W, (A.y - Bp.y) * H); return { x: (A.x + Bp.x) / 2 * .4 + L.x * .6, y: L.y + ew * .32 / H, ew }; };
      const el = eyeOut(33, 133, 145), er = eyeOut(362, 263, 374);
      const f = {
        cx: (lc.x + rc.x) / 2, cy: (top.y + chin.y) / 2,
        rx: Math.abs(rc.x - lc.x) * W / 2, ry: Math.abs(chin.y - top.y) * H / 2,
        el, er, ew: (el.ew + er.ew) / 2, eyeY: (P(159).y + P(386).y) / 2,
      };
      // Augenmitten (Iris, wenn vorhanden), Lippen, Wangen
      const mid = (a, b) => { const A = P(a), Bp = P(b); return { x: (A.x + Bp.x) / 2, y: (A.y + Bp.y) / 2 }; };
      f.cl = lm.length > 473 ? P(468) : mid(33, 133); f.cr = lm.length > 473 ? P(473) : mid(362, 263);
      const ml = P(61), mr = P(291), mt = P(0), mb = P(17);
      f.lc = { x: (ml.x + mr.x) / 2, y: (mt.y + mb.y) / 2 }; f.lrx = Math.hypot((mr.x - ml.x) * W, (mr.y - ml.y) * H) / 2; f.lry = Math.abs(mb.y - mt.y) * H / 2;
      f.chl = mid(205, 50); f.chr = mid(425, 280);
      this.drawFaceMask(P, W, H, f.ew);
      const o = this.face; if (!o) { this.face = f; return; }
      const k = .45, L = (a, b) => a + (b - a) * k, LP = (a, b) => ({ x: L(a.x, b.x), y: L(a.y, b.y) });
      this.face = { cx: L(o.cx, f.cx), cy: L(o.cy, f.cy), rx: L(o.rx, f.rx), ry: L(o.ry, f.ry), ew: L(o.ew, f.ew), eyeY: L(o.eyeY, f.eyeY),
        el: LP(o.el, el), er: LP(o.er, er), cl: LP(o.cl, f.cl), cr: LP(o.cr, f.cr), lc: LP(o.lc, f.lc), lrx: L(o.lrx, f.lrx), lry: L(o.lry, f.lry), chl: LP(o.chl, f.chl), chr: LP(o.chr, f.chr) };
    }
    drawFaceMask(P, W, H, ew) {
      const s = this.s; if (!(s.lip > 0 || s.brows > 0 || s.lashes > 0)) return;
      const c = this.fc, x = this.fx, w = Math.max(64, Math.round(W / 3)), h = Math.max(64, Math.round(H / 3));
      if (c.width !== w || c.height !== h) { c.width = w; c.height = h; }
      x.setTransform(1, 0, 0, 1, 0, 0); x.globalCompositeOperation = 'source-over'; x.clearRect(0, 0, w, h);
      const poly = (ids, close) => { x.beginPath(); ids.forEach((i, n) => { const p = P(i); n ? x.lineTo(p.x * w, p.y * h) : x.moveTo(p.x * w, p.y * h); }); if (close) x.closePath(); };
      const sc = w / W, soft = 'filter' in x;
      if (soft) x.filter = 'blur(' + Math.max(1, ew * sc * .06).toFixed(1) + 'px)';
      // Lippen: außen füllen, Mund innen aussparen
      poly([61, 146, 91, 181, 84, 17, 314, 405, 321, 375, 291, 409, 270, 269, 267, 0, 37, 39, 40, 185], true); x.fillStyle = 'rgb(255,0,0)'; x.fill();
      x.globalCompositeOperation = 'destination-out';
      poly([78, 95, 88, 178, 87, 14, 317, 402, 318, 324, 308, 415, 310, 311, 312, 13, 82, 81, 80, 191], true); x.fill();
      x.globalCompositeOperation = 'lighter';
      // Brauen
      x.fillStyle = 'rgb(0,255,0)';
      poly([70, 63, 105, 66, 107, 55, 65, 52, 53, 46], true); x.fill();
      poly([300, 293, 334, 296, 336, 285, 295, 282, 283, 276], true); x.fill();
      // Wimpernrand oben (etwas über dem Lid)
      x.strokeStyle = 'rgb(0,0,255)'; x.lineWidth = Math.max(1.2, ew * sc * .1); x.lineCap = 'round'; x.lineJoin = 'round';
      x.save(); x.translate(0, -ew * sc * .03);
      poly([33, 246, 161, 160, 159, 158, 157, 173, 133]); x.stroke();
      poly([263, 466, 388, 387, 386, 385, 384, 398, 362]); x.stroke();
      x.restore();
      if (soft) x.filter = 'none';
      const gl = this.gl; gl.bindTexture(gl.TEXTURE_2D, this.tFace); gl.pixelStorei(gl.UNPACK_ALIGNMENT, 4);
      try { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, c); } catch (e) {}
    }
    draw() {
      const v = this.video; if (v.readyState < 2) return;
      if (v.videoWidth && this.lastVW !== v.videoWidth + 'x' + v.videoHeight) { this.lastVW = v.videoWidth + 'x' + v.videoHeight; this.layout(); }
      this.frame++;
      const W = this.canvas.width, H = this.canvas.height;
      if (!this.ok) { const c = this.ctx2d, [cx, cy, cw, ch] = this.crop; c.drawImage(v, cx * v.videoWidth, cy * v.videoHeight, cw * v.videoWidth, ch * v.videoHeight, 0, 0, W, H); this.emit('frame'); return; }
      const gl = this.gl, s = this.s, now = performance.now();
      gl.bindTexture(gl.TEXTURE_2D, this.tVid); gl.pixelStorei(gl.UNPACK_ALIGNMENT, 4);
      try { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, v); } catch (e) { return; }
      if (s.on && !this.compare) this.analyse(now);
      const crop = this.crop;
      // 1. Ausschnitt
      const base = this.fbo('base', W, H);
      gl.bindFramebuffer(gl.FRAMEBUFFER, base.f); gl.viewport(0, 0, W, H);
      gl.useProgram(this.pBase.p); gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, this.tVid); gl.uniform1i(this.pBase.u.vid, 0); gl.uniform4fv(this.pBase.u.crop, crop);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      const on = s.on && !this.compare, blur = on ? s.blur / 100 : 0, useMask = blur > 0 && this.hasMask ? 1 : 0;
      // 2. Hintergrund unscharf (ohne Person, damit kein Schein um dich entsteht)
      let bgT = base.t;
      if (blur > 0) {
        const w4 = Math.max(16, Math.round(W / 4)), h4 = Math.max(16, Math.round(H / 4));
        const a = this.fbo('a', w4, h4), b = this.fbo('b', w4, h4);
        gl.viewport(0, 0, w4, h4);
        gl.bindFramebuffer(gl.FRAMEBUFFER, a.f); gl.useProgram(this.pDown.p);
        gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, base.t); gl.uniform1i(this.pDown.u.base, 0);
        gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, this.tMask); gl.uniform1i(this.pDown.u.mask, 1);
        gl.uniform4fv(this.pDown.u.crop, crop); gl.uniform1f(this.pDown.u.useMask, useMask);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        gl.useProgram(this.pBlur.p); gl.uniform1i(this.pBlur.u.src, 0); gl.activeTexture(gl.TEXTURE0);
        const st = .8 + blur * 2.6, it = blur > .5 ? 3 : 2;
        for (let i = 0; i < it; i++) {
          gl.bindFramebuffer(gl.FRAMEBUFFER, b.f); gl.bindTexture(gl.TEXTURE_2D, a.t); gl.uniform2f(this.pBlur.u.dir, st / w4, 0); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
          gl.bindFramebuffer(gl.FRAMEBUFFER, a.f); gl.bindTexture(gl.TEXTURE_2D, b.t); gl.uniform2f(this.pBlur.u.dir, 0, st / h4); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        }
        bgT = a.t;
      }
      // 3. Look
      gl.bindFramebuffer(gl.FRAMEBUFFER, null); gl.viewport(0, 0, W, H);
      const P = this.pFin, u = P.u; gl.useProgram(P.p);
      gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, base.t); gl.uniform1i(u.base, 0);
      gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, bgT); gl.uniform1i(u.bg, 1);
      gl.activeTexture(gl.TEXTURE2); gl.bindTexture(gl.TEXTURE_2D, this.tMask); gl.uniform1i(u.mask, 2);
      gl.activeTexture(gl.TEXTURE3); gl.bindTexture(gl.TEXTURE_2D, this.tFace); gl.uniform1i(u.fmask, 3);
      gl.uniform4fv(u.crop, crop); gl.uniform2f(u.res, W, H);
      const f = this.face, hasFace = f ? 1 : 0;
      for (const k of ['blush', 'eyeBig', 'eyeBright', 'lashes', 'brows', 'lip', 'lipBig']) gl.uniform1f(u[k], (s[k] || 0) / 100);
      gl.uniform3fv(u.lipCol, (LIPS[s.lipColor] || LIPS.rose)[1]);
      const F = (FILTERS[s.filter] || FILTERS.none)[1], fa = s.filter === 'none' ? 0 : (s.filterAmt == null ? 70 : s.filterAmt) / 100;
      gl.uniform1f(u.fSat, 1 + (F.sat - 1) * fa); gl.uniform1f(u.fCon, 1 + (F.con - 1) * fa); gl.uniform1f(u.fFade, F.fade * fa); gl.uniform1f(u.fBW, F.bw * fa);
      gl.uniform3f(u.fSh, F.sh[0] * fa, F.sh[1] * fa, F.sh[2] * fa); gl.uniform3f(u.fHi, F.hi[0] * fa, F.hi[1] * fa, F.hi[2] * fa);
      gl.uniform1f(u.bypass, on ? 0 : 1);
      gl.uniform1f(u.beauty, s.beauty / 100); gl.uniform1f(u.eyes, s.eyes / 100); gl.uniform1f(u.blur, useMask ? blur : 0);
      gl.uniform1f(u.light, s.light / 100); gl.uniform1f(u.ring, s.ring / 100); gl.uniform1f(u.warm, s.warm / 100); gl.uniform1f(u.contrast, s.contrast / 100);
      gl.uniform1f(u.useMask, useMask); gl.uniform1f(u.hasFace, hasFace);
      if (f) {
        gl.uniform2f(u.eyeL, f.el.x, f.el.y); gl.uniform2f(u.eyeR, f.er.x, f.er.y);
        gl.uniform2f(u.eyeRad, f.ew * .62, f.ew * .30);
        gl.uniform2f(u.faceC, f.cx, f.cy); gl.uniform2f(u.faceR, f.rx * 1.05, f.ry * 1.1);
        gl.uniform2f(u.eyeCL, f.cl.x, f.cl.y); gl.uniform2f(u.eyeCR, f.cr.x, f.cr.y); gl.uniform1f(u.eyeW, f.ew);
        gl.uniform2f(u.lipC, f.lc.x, f.lc.y); gl.uniform2f(u.lipRad, f.lrx, Math.max(f.lry, f.lrx * .35));
        gl.uniform2f(u.chL, f.chl.x, f.chl.y); gl.uniform2f(u.chR, f.chr.x, f.chr.y);
      }
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      this.emit('frame');
    }
    stream(fps) {
      const out = this.canvas.captureStream(fps || 30);
      this.raw.getAudioTracks().forEach(t => out.addTrack(t));
      return out;
    }
    destroy(stopRaw) {
      this.dead = true; cancelAnimationFrame(this.raf); clearTimeout(this.timer);
      try { this.video.pause(); this.video.srcObject = null; } catch (e) {}
      if (stopRaw) this.raw.getTracks().forEach(t => t.stop());
      this.listeners = [];
    }
  }

  // ---------- Vorschau mit Hilfslinien ----------
  const CSS = `
.tc-stage{position:relative;width:100%;background:#2E2529;border-radius:22px;overflow:hidden;display:flex;align-items:center;justify-content:center}
.tc-stage .tc-cv{display:block;width:100%;height:100%;object-fit:contain}
.tc-stage.mir .tc-cv{transform:scaleX(-1)}
.tc-guide{position:absolute;pointer-events:none}
.tc-guide svg{position:absolute;inset:0;width:100%;height:100%}
.tc-hint{position:absolute;left:50%;bottom:14px;transform:translateX(-50%);padding:8px 16px;border-radius:999px;background:rgba(46,37,41,.72);color:#FBF5F7;font:500 17px/1.3 "Hanken Grotesk","Outfit",system-ui,sans-serif;white-space:nowrap;transition:background .3s}
.tc-hint.ok{background:rgba(63,107,62,.85)}
.tc-hint:empty{display:none}
.tc-st{position:absolute;left:12px;bottom:64px;right:12px;z-index:4;padding:8px 14px;border-radius:14px;background:rgba(251,248,244,.92);color:#4A3A41;font:15px/1.4 "Hanken Grotesk","Outfit",system-ui,sans-serif}
.tc-st:empty{display:none}
.tc-p{display:flex;flex-direction:column;gap:16px;font-family:"Hanken Grotesk","Outfit",system-ui,sans-serif;color:#2E2529}
.tc-p h3{margin:0;font:500 15px/1 "Hanken Grotesk","Outfit",system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#7D7076}
.tc-chips{display:flex;flex-wrap:wrap;gap:8px}
.tc-chips button{font:inherit;font-size:17px;padding:10px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer;min-height:46px}
.tc-chips button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.tc-sw{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-radius:18px;background:#FBF8F4;border:1.5px solid #DDD2C6;font-size:18px;cursor:pointer}
.tc-sw input{width:26px;height:26px;padding:0;flex:0 0 auto;accent-color:#7D5E68}
.tc-p input[type=range]{padding:0;border:0;background:transparent;outline-offset:2px}
.tc-sl{display:flex;flex-direction:column;gap:4px}
.tc-sl label{display:flex;justify-content:space-between;font-size:17px;color:#4A3A41}
.tc-sl label small{color:#7D7076;font-size:15px}
.tc-sl input[type=range]{width:100%;accent-color:#7D5E68;height:30px}
.tc-g{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:18px;padding:0 14px}
.tc-g>summary{list-style:none;cursor:pointer;padding:13px 0;font-size:18px;color:#4A3A41;display:flex;justify-content:space-between;align-items:center}
.tc-g>summary::-webkit-details-marker{display:none}
.tc-g>summary::after{content:"▾";color:#7D5E68}.tc-g[open]>summary::after{content:"▴"}
.tc-gb{display:flex;flex-direction:column;gap:12px;padding:0 0 14px}
.tc-lips button{display:inline-flex;align-items:center;gap:8px}.tc-lips i{width:16px;height:16px;border-radius:50%;display:inline-block;border:1px solid rgba(0,0,0,.12)}
.tc-more{border-top:1px solid #EADFDE;padding-top:12px}
.tc-more>summary{cursor:pointer;font-size:17px;color:#7D5E68;list-style:none}
.tc-more>summary::-webkit-details-marker{display:none}
.tc-more[open]>summary{margin-bottom:12px}
.tc-cmp{font:inherit;font-size:17px;padding:12px 18px;border-radius:999px;border:1.5px solid #4A3A41;background:transparent;color:#4A3A41;cursor:pointer;user-select:none;-webkit-user-select:none;touch-action:none}
.tc-cmp.on{background:#4A3A41;color:#fff}
.tc-dlg{position:fixed;inset:0;z-index:2147483000;background:rgba(46,37,41,.6);display:flex;align-items:flex-start;justify-content:center;overflow:auto;padding:16px}
.tc-dlg-in{background:#F2ECE4;border-radius:28px;max-width:1180px;width:100%;padding:18px;display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,1fr);gap:18px;margin:auto 0}
@media(max-width:820px){.tc-dlg-in{grid-template-columns:1fr}}
.tc-dlg-h{grid-column:1/-1;display:flex;justify-content:space-between;align-items:center;gap:10px}
.tc-dlg-h b{font:500 30px/1.1 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.tc-dlg-h button,.tc-btn{font:inherit;font-size:17px;border:1.5px solid #DDD2C6;background:#fff;border-radius:999px;padding:10px 18px;cursor:pointer;color:#4A3A41}
.tc-btn.main{background:#7D5E68;border-color:#7D5E68;color:#fff}
.tc-open{font:inherit;font-size:16px;display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:999px;border:1.5px solid #7D5E68;background:#FBF5F7;color:#7D5E68;cursor:pointer}
.tc-open b{font-weight:500}
`;
  function css() { if (document.getElementById('tc-css')) return; const st = document.createElement('style'); st.id = 'tc-css'; st.textContent = CSS; document.head.appendChild(st); }

  // Zielposition für Talking Heads: Augen auf der oberen Drittel-Linie, Kopf mittig
  function target(aspect) {
    if (aspect === '9:16') return { x: .5, eye: .36, faceH: .2 };
    if (aspect === '1:1') return { x: .5, eye: .36, faceH: .32 };
    return { x: .5, eye: .37, faceH: .4 };
  }

  function stage(tc, el) {
    css();
    el.classList.add('tc-stage');
    el.innerHTML = '';
    el.appendChild(tc.canvas);
    tc.video.style.cssText = 'position:absolute;left:0;top:0;width:2px;height:2px;opacity:.01;pointer-events:none';
    el.appendChild(tc.video);
    const g = document.createElement('div'); g.className = 'tc-guide'; el.appendChild(g);
    const hint = document.createElement('div'); hint.className = 'tc-hint'; el.appendChild(hint);
    const st = document.createElement('div'); st.className = 'tc-st'; el.appendChild(st);
    const fit = () => {
      const W = tc.canvas.width || 16, H = tc.canvas.height || 9;
      el.style.aspectRatio = W + '/' + H;
      el.style.width = 'min(100%, calc(' + (tc.opts.maxVh || 74) + 'vh * ' + (W / H).toFixed(4) + '))';
      el.style.marginInline = 'auto';
      g.style.inset = '0';
      paint();
    };
    let okSince = 0, lastKey = '';
    function paint() {
      const s = tc.s, W = tc.canvas.width, H = tc.canvas.height;
      el.classList.toggle('mir', !!s.mirror);
      const t = target(s.aspect), col = 'rgba(251,245,247,.75)', soft = 'rgba(251,245,247,.35)';
      let svg = '';
      if (s.guide === 'kreuz') {
        svg = `<line x1="${W / 2}" y1="${H * .5 - H * .06}" x2="${W / 2}" y2="${H * .5 + H * .06}" stroke="${col}" stroke-width="${W / 400}"/><line x1="${W / 2 - H * .06}" y1="${H / 2}" x2="${W / 2 + H * .06}" y2="${H / 2}" stroke="${col}" stroke-width="${W / 400}"/>`
          + `<line x1="${W * .3}" y1="${H * t.eye}" x2="${W * .7}" y2="${H * t.eye}" stroke="${soft}" stroke-width="${W / 500}" stroke-dasharray="${W / 80} ${W / 80}"/>`
          + `<circle cx="${W / 2}" cy="${H * t.eye}" r="${W / 140}" fill="none" stroke="${col}" stroke-width="${W / 500}"/>`;
      } else if (s.guide === 'raster') {
        for (const k of [1, 2]) svg += `<line x1="${W * k / 3}" y1="0" x2="${W * k / 3}" y2="${H}" stroke="${soft}" stroke-width="${W / 600}"/><line x1="0" y1="${H * k / 3}" x2="${W}" y2="${H * k / 3}" stroke="${soft}" stroke-width="${W / 600}"/>`;
        svg += `<line x1="${W / 2 - W / 60}" y1="${H / 2}" x2="${W / 2 + W / 60}" y2="${H / 2}" stroke="${col}" stroke-width="${W / 500}"/><line x1="${W / 2}" y1="${H / 2 - W / 60}" x2="${W / 2}" y2="${H / 2 + W / 60}" stroke="${col}" stroke-width="${W / 500}"/>`;
      } else if (s.guide === 'oval') {
        const ry = H * t.faceH / 2, rx = ry * .78, cy = H * t.eye + ry * .12;
        const sh = cy + ry * 1.35, sw = rx * 3.1;
        svg = `<ellipse cx="${W * t.x}" cy="${cy}" rx="${rx}" ry="${ry}" fill="none" stroke="${tc._ok ? 'rgba(170,215,160,.95)' : col}" stroke-width="${W / 320}" stroke-dasharray="${W / 90} ${W / 110}"/>`
          + `<path d="M${W / 2 - sw} ${Math.min(H, sh + ry * 1.6)} Q${W / 2 - sw * .9} ${sh} ${W / 2 - rx * .9} ${sh - ry * .05} M${W / 2 + sw} ${Math.min(H, sh + ry * 1.6)} Q${W / 2 + sw * .9} ${sh} ${W / 2 + rx * .9} ${sh - ry * .05}" fill="none" stroke="${soft}" stroke-width="${W / 400}" stroke-linecap="round"/>`
          + `<line x1="${W * .38}" y1="${H * t.eye}" x2="${W * .62}" y2="${H * t.eye}" stroke="${soft}" stroke-width="${W / 600}" stroke-dasharray="${W / 120} ${W / 120}"/>`;
      }
      const key = s.guide + s.aspect + W + H + (tc._ok ? 1 : 0) + (s.mirror ? 1 : 0);
      if (key !== lastKey) { lastKey = key; g.innerHTML = svg ? `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none">${svg}</svg>` : ''; }
    }
    function feedback() {
      const s = tc.s;
      if (s.guide !== 'oval') { hint.textContent = ''; hint.className = 'tc-hint'; if (tc._ok) { tc._ok = false; paint(); } return; }
      const f = tc.face, t = target(s.aspect);
      let msg = '', ok = false;
      if (!f) msg = (tc.fl || (window.TalkCam._test && window.TalkCam._test.face)) ? 'Setz dich so, dass dein Gesicht im Oval ist' : '';
      else {
        const fh = f.ry * 2 / tc.canvas.height, dx = f.cx - t.x, dy = f.eyeY - t.eye;
        // im gespiegelten Bild wirkt „nach rechts rücken“ so wie im Spiegel
        const sideR = s.mirror ? dx < 0 : dx > 0;
        if (fh < t.faceH * .78) msg = 'Etwas näher zur Kamera';
        else if (fh > t.faceH * 1.25) msg = 'Etwas weiter weg';
        else if (Math.abs(dx) > .045) msg = sideR ? 'Ein kleines Stück nach links' : 'Ein kleines Stück nach rechts';
        else if (dy > .045) msg = 'Kamera etwas tiefer oder dich etwas aufrichten';
        else if (dy < -.045) msg = 'Kamera etwas höher oder etwas tiefer sitzen';
        else { ok = true; msg = '✓ Perfekt so'; }
      }
      const now = performance.now();
      if (ok && !okSince) okSince = now; if (!ok) okSince = 0;
      if (ok !== !!tc._ok) { tc._ok = ok; paint(); }
      hint.textContent = msg; hint.className = 'tc-hint' + (ok ? ' ok' : '');
    }
    let fc = 0;
    tc.on(t => {
      if (t === 'layout' || t === 'change') fit();
      if (t === 'frame' && ++fc % 6 === 0) feedback();
      if (t === 'status') st.textContent = tc.status;
    });
    st.textContent = tc.status;
    fit();
    return el;
  }

  // ---------- Einstellungen ----------
  function panel(tc, el, opts) {
    css(); opts = opts || {};
    const sl = (k, label, min, max) => `<div class="tc-sl"><label for="tc-${k}"><span>${label}</span><small data-v="${k}"></small></label><input id="tc-${k}" type="range" min="${min}" max="${max}" step="1" data-k="${k}"></div>`;
    const grp = (id, title, body, open) => `<details class="tc-g" data-grp="${id}"${open ? ' open' : ''}><summary>${title}</summary><div class="tc-gb">${body}</div></details>`;
    el.innerHTML = `<div class="tc-p">
  <label class="tc-sw"><span>✨ Kamera-Look an</span><input type="checkbox" data-on></label>
  <div><h3>Look</h3><div class="tc-chips" data-looks>${Object.keys(LOOKS).map(k => `<button type="button" data-look="${k}">${LOOKS[k].label}</button>`).join('')}</div></div>
  ${grp('haut', '🌸 Haut', sl('beauty', 'Haut weich', 0, 100) + sl('eyes', 'Augenringe aufhellen', 0, 100) + sl('ring', 'Glow (Gesicht aufhellen)', 0, 100) + sl('blush', 'Rouge', 0, 100), true)}
  ${grp('augen', '👁 Augen &amp; Brauen', sl('eyeBright', 'Augen strahlen', 0, 100) + sl('eyeBig', 'Augen etwas größer', 0, 100) + sl('lashes', 'Wimpern betonen', 0, 100) + sl('brows', 'Brauen betonen', 0, 100))}
  ${grp('lippen', '💋 Lippen', `<div class="tc-chips tc-lips" data-lips>${Object.keys(LIPS).map(k => `<button type="button" data-lipc="${k}"><i style="background:rgb(${LIPS[k][1].map(v => Math.round(v * 255)).join(',')})"></i>${LIPS[k][0]}</button>`).join('')}</div>` + sl('lip', 'Farbe', 0, 100) + sl('lipBig', 'Lippen voller', 0, 100))}
  ${grp('filter', '🎞 Farbfilter', `<div class="tc-chips" data-filters>${Object.keys(FILTERS).map(k => `<button type="button" data-flt="${k}">${FILTERS[k][0]}</button>`).join('')}</div>` + sl('filterAmt', 'Stärke', 0, 100))}
  ${grp('licht', '☀️ Hintergrund &amp; Licht', sl('blur', 'Hintergrund unscharf', 0, 100) + sl('light', 'Helligkeit', -50, 50) + sl('warm', 'Wärme', -50, 50) + sl('contrast', 'Kontrast', -50, 50))}
  <div><h3>Hilfe für die Position</h3><div class="tc-chips" data-guides><button type="button" data-g="oval">Kopf-Oval</button><button type="button" data-g="kreuz">Kreuz</button><button type="button" data-g="raster">Raster</button><button type="button" data-g="aus">Aus</button></div></div>
  ${opts.aspect === false ? '' : `<div><h3>Format</h3><div class="tc-chips" data-aspects><button type="button" data-a="16:9">Quer 16:9</button><button type="button" data-a="9:16">Hoch 9:16 (Story, Reel)</button></div></div>`}
  <label class="tc-sw"><span>Vorschau spiegeln <small style="color:#7D7076;font-size:15px">(wie ein Spiegel, die Aufnahme ist richtig herum)</small></span><input type="checkbox" data-mir></label>
  <div><button type="button" class="tc-cmp" data-cmp>👁 Gedrückt halten: ohne Look</button></div>
</div>`;
    const q = sel => el.querySelector(sel), qa = sel => el.querySelectorAll(sel);
    function sync() {
      const s = tc.s;
      q('[data-on]').checked = !!s.on; q('[data-mir]').checked = !!s.mirror;
      qa('[data-k]').forEach(i => { const k = i.dataset.k, n = +(s[k] == null ? DEF[k] : s[k]); i.value = n; i.disabled = !s.on; q(`[data-v="${k}"]`).textContent = i.min < 0 ? (n > 0 ? '+' + n : n) : n + ' %'; });
      qa('[data-look]').forEach(b => b.setAttribute('aria-pressed', String(s.on && s.look === b.dataset.look)));
      qa('[data-lipc]').forEach(b => b.setAttribute('aria-pressed', String(s.lipColor === b.dataset.lipc)));
      qa('[data-flt]').forEach(b => b.setAttribute('aria-pressed', String((s.filter || 'none') === b.dataset.flt)));
      qa('[data-g]').forEach(b => b.setAttribute('aria-pressed', String(s.guide === b.dataset.g)));
      qa('[data-a]').forEach(b => b.setAttribute('aria-pressed', String(s.aspect === b.dataset.a)));
    }
    q('[data-on]').onchange = e => { tc.set({ on: e.target.checked }); sync(); };
    q('[data-mir]').onchange = e => { tc.set({ mirror: e.target.checked }); sync(); };
    qa('[data-k]').forEach(i => i.addEventListener('input', () => { tc.set({ [i.dataset.k]: +i.value, look: 'eigen' }); sync(); }));
    qa('[data-look]').forEach(b => b.onclick = () => { const L = Object.assign({}, LOOKS[b.dataset.look]); delete L.label; tc.set(Object.assign(L, { on: true, look: b.dataset.look })); sync(); });
    qa('[data-lipc]').forEach(b => b.onclick = () => { const p = { lipColor: b.dataset.lipc, look: 'eigen' }; if (!(tc.s.lip > 0)) p.lip = 40; tc.set(p); sync(); });
    qa('[data-flt]').forEach(b => b.onclick = () => { tc.set({ filter: b.dataset.flt, look: 'eigen' }); sync(); });
    qa('[data-g]').forEach(b => b.onclick = () => { tc.set({ guide: b.dataset.g }); sync(); });
    qa('[data-a]').forEach(b => b.onclick = () => { tc.set({ aspect: b.dataset.a }); sync(); });
    // aufgeklappte Bereiche merken
    let og = {}; try { og = JSON.parse(localStorage.getItem('mv-talkcam-open') || '{}'); } catch (e) {}
    qa('[data-grp]').forEach(d => { if (og[d.dataset.grp] != null) d.open = !!og[d.dataset.grp]; d.addEventListener('toggle', () => { og[d.dataset.grp] = d.open; try { localStorage.setItem('mv-talkcam-open', JSON.stringify(og)); } catch (e) {} }); });
    const cmp = q('[data-cmp]');
    const down = e => { e.preventDefault(); tc.compare = true; cmp.classList.add('on'); }, up = () => { tc.compare = false; cmp.classList.remove('on'); };
    cmp.addEventListener('pointerdown', down); ['pointerup', 'pointerleave', 'pointercancel'].forEach(t => cmp.addEventListener(t, up));
    tc.on(t => { if (t === 'external') sync(); });
    sync();
    return el;
  }

  async function create(raw, opts) {
    opts = opts || {};
    const tc = new Cam(raw, opts);
    await tc.start();
    if (opts.mount) stage(tc, opts.mount);
    else {
      // unsichtbar im Dokument halten, sonst drosseln manche Browser (Safari) Video und Canvas
      const hid = document.createElement('div');
      hid.style.cssText = 'position:fixed;left:0;top:0;width:2px;height:2px;overflow:hidden;opacity:.01;pointer-events:none;z-index:-1';
      hid.appendChild(tc.video); hid.appendChild(tc.canvas); document.body.appendChild(hid);
      const d = tc.destroy.bind(tc); tc.destroy = s => { d(s); hid.remove(); };
    }
    return tc;
  }

  // Bearbeiteter Stream mit den gespeicherten Einstellungen (für vorhandene Aufnahme-Knöpfe)
  async function wrap(raw, opts) {
    const s = load();
    if (!s.on || !raw || !raw.getVideoTracks().length) return { stream: raw, stop() { raw && raw.getTracks().forEach(t => t.stop()); } };
    try {
      const tc = await create(raw, Object.assign({ aspect: '16:9' }, opts || {}, { noSave: true }));
      if (!tc.ok) { tc.destroy(false); return { stream: raw, stop() { raw.getTracks().forEach(t => t.stop()); } }; }
      const out = tc.stream(30);
      return { stream: out, tc, stop() { out.getVideoTracks().forEach(t => t.stop()); tc.destroy(true); } };
    } catch (e) { console.warn('TalkCam wrap', e); return { stream: raw, stop() { raw.getTracks().forEach(t => t.stop()); } }; }
  }

  // Fenster mit Vorschau + Einstellungen (eigene Kamera, wird beim Schließen beendet)
  async function dialog(opts) {
    css(); opts = opts || {};
    const d = document.createElement('div'); d.className = 'tc-dlg'; d.setAttribute('role', 'dialog'); d.setAttribute('aria-modal', 'true');
    d.innerHTML = `<div class="tc-dlg-in"><div class="tc-dlg-h"><b>✨ Kamera-Look</b><button type="button" data-x>Fertig</button></div><div><div data-stage class="tc-stage" style="aspect-ratio:16/9"><div class="tc-st">Kamera startet …</div></div>
<p style="font:16px/1.5 'Hanken Grotesk','Outfit',system-ui,sans-serif;color:#6B5658;margin:10px 4px 0">${opts.note || 'Der Look wird gespeichert und gilt für deine Aufnahmen hier. Hilfslinien siehst nur du, sie sind nie in der Aufnahme.'}</p></div><div data-panel></div></div>`;
    document.body.appendChild(d); const prevOv = document.body.style.overflow; document.body.style.overflow = 'hidden';
    let tc = null;
    const close = () => { if (tc) tc.destroy(true); d.remove(); document.body.style.overflow = prevOv; if (opts.onClose) opts.onClose(load()); };
    d.querySelector('[data-x]').onclick = close; d.addEventListener('click', e => { if (e.target === d) close(); });
    try {
      const raw = await navigator.mediaDevices.getUserMedia({ video: Object.assign({ width: { ideal: 1280 }, height: { ideal: 720 } }, opts.deviceId ? { deviceId: { exact: opts.deviceId } } : {}), audio: false });
      tc = await create(raw, { mount: d.querySelector('[data-stage]'), aspect: opts.aspect });
      panel(tc, d.querySelector('[data-panel]'), { aspect: opts.aspect ? false : undefined });
    } catch (e) { d.querySelector('[data-stage]').innerHTML = '<div class="tc-st">Die Kamera ist gesperrt oder wird gerade benutzt. Bitte im Browser die Kamera erlauben.</div>'; }
    return { close };
  }

  // Kleiner Knopf zum Öffnen des Fensters
  function button(where, opts) {
    css(); opts = opts || {};
    const b = document.createElement('button'); b.type = 'button'; b.className = 'tc-open ' + (opts.cls || '');
    const lab = () => { const s = load(); b.innerHTML = '✨ <b>Kamera-Look</b> ' + (s.on ? 'an' : 'aus'); };
    lab(); b.onclick = () => dialog(Object.assign({}, opts, { onClose: () => { lab(); if (opts.onClose) opts.onClose(load()); } }));
    if (where) where.appendChild(b);
    return b;
  }

  window.TalkCam = { create, wrap, panel, stage, dialog, button, settings: load, LOOKS, _test: null };
})();

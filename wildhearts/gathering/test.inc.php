<?php
// Technik-Test für Viv: Kamera, Mikro, Lautsprecher prüfen, danach in den Whereby-Testraum.
// Wird nur von gathering/index.php eingebunden (geheimes Token geprüft).
if (!isset($testTok, $ready, $src, $guest)) { http_response_code(404); exit; }
$h = 'forma_h';
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Technik-Test · Wild Hearts</title>
<meta name="theme-color" content="#F3F1E7">
<link rel="icon" href="/img/wildhearts/viv.jpg">
<style>
@font-face{font-family:"Italiana";font-weight:400;font-display:swap;src:url(/fonts/Italiana-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:300 500;font-display:swap;src:url(/fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Hand";font-weight:400;font-display:swap;src:url(/fonts/HomemadeApple-Regular.woff) format("woff")}
:root{--cream:#F6F3EA;--oat:#E3DED0;--blush:#D8DCC6;--wine:#5E6B35;--wine-deep:#3F4A26;--earth:#3E372C;--brown:#6B5D4C;--ink:#2F2B22;--sage-soft:#DCE0D2;--line:#CFC9B5;--blob:58% 42% 47% 53% / 52% 46% 54% 48%}
*{box-sizing:border-box;margin:0}
[hidden]{display:none!important}
html,body{background:var(--cream)}
body{min-height:100dvh;font-family:"Outfit",system-ui,sans-serif;color:var(--ink);font-size:18px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
.bg{position:fixed;inset:0;pointer-events:none;overflow:hidden}
.bg span{position:absolute;border-radius:var(--blob)}
.bg .a{width:420px;height:420px;right:-200px;top:-150px;background:var(--oat);opacity:.8}
.bg .b{width:300px;height:300px;left:-190px;bottom:-60px;background:var(--blush);opacity:.5}
main{position:relative;max-width:760px;margin:0 auto;padding:max(30px,env(safe-area-inset-top)) 16px 40px}
.hand{font-family:"Hand","Bradley Hand",cursive;color:var(--wine);font-size:20px;transform:rotate(-2deg);display:inline-block}
h1{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(36px,8vw,52px);line-height:1.05;color:var(--wine-deep);margin-top:6px}
.lead{color:var(--brown);margin-top:10px}
.card{margin-top:22px;padding:22px;border-radius:26px;background:#FCFBF6;border:1.5px solid var(--line);box-shadow:0 14px 34px rgba(62,58,40,.08)}
.card h2{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:28px;color:var(--wine-deep);margin-bottom:6px}
.step{font-family:"Outfit",sans-serif;display:inline-block;width:30px;height:30px;border-radius:50%;background:var(--sage-soft);color:var(--wine-deep);text-align:center;line-height:30px;font-size:16px;margin-right:8px;vertical-align:4px}
.preview{position:relative;margin-top:14px;border-radius:20px;overflow:hidden;background:var(--earth);aspect-ratio:16/10}
.preview video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1);display:block}
.preview p{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--blush);text-align:center;padding:20px}
.meter{margin-top:14px;height:16px;border-radius:999px;background:var(--sage-soft);overflow:hidden}
.meter i{display:block;height:100%;width:0;background:var(--wine);border-radius:999px;transition:width .08s linear}
.status{margin-top:8px;font-size:16px;color:var(--brown)}
.status.ok{color:var(--wine-deep);font-weight:500}
.row{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
label.f{display:flex;flex-direction:column;gap:4px;font-size:15px;color:var(--brown);flex:1 1 240px}
select{font:inherit;font-size:16px;padding:11px 14px;border-radius:14px;border:1.5px solid var(--line);background:var(--cream);color:var(--ink)}
button,.btn{font:inherit;font-weight:500;font-size:17px;padding:13px 22px;border-radius:999px;border:0;background:var(--wine);color:#F6F3EA;cursor:pointer;text-decoration:none;display:inline-block}
button.ghost{background:var(--cream);color:var(--wine-deep);border:1.5px solid var(--line)}
button:disabled{opacity:.55;cursor:default}
.tip{font-size:15px;color:var(--brown);margin-top:12px}
.roomwrap{position:fixed;inset:0;display:flex;flex-direction:column;background:var(--earth);z-index:5}
.roombar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 16px;color:var(--cream);font-size:15px}
.roombar button{padding:7px 16px;font-size:15px;background:var(--sage-soft);color:var(--wine-deep)}
.roomwrap iframe{flex:1;width:100%;border:0;background:#2a1b16}
</style>
</head>
<body>
<div class="bg" aria-hidden="true"><span class="a"></span><span class="b"></span></div>
<main>
  <p class="hand"><?= $guest ? 'schön, dass du testest ♡' : 'kurz durchatmen, dann testen ♡' ?></p>
  <h1>Technik-Test</h1>
  <p class="lead"><?= $guest ? 'Hier prüfst du Kamera und Mikro und gehst dann in den Testraum zu Viv.' : 'Hier prüfst du Kamera, Mikro und Ton, ganz ohne dass jemand zusieht. Danach kannst du in deinen eigenen Testraum gehen.' ?></p>

  <section class="card">
    <h2><span class="step">1</span>Kamera &amp; Mikro</h2>
    <div class="preview"><video id="v" playsinline muted autoplay></video><p id="ph">Tippe auf „Kamera &amp; Mikro starten“ und erlaube den Zugriff.</p></div>
    <div class="meter" aria-hidden="true"><i id="lvl"></i></div>
    <p class="status" id="st">Sprich etwas, sobald es läuft: Der grüne Balken schlägt dann aus.</p>
    <div class="row">
      <button id="go" type="button">Kamera &amp; Mikro starten</button>
    </div>
    <div class="row" id="pick" hidden>
      <label class="f">Mikrofon<select id="mic"></select></label>
      <label class="f">Kamera<select id="cam"></select></label>
    </div>
  </section>

  <section class="card">
    <h2><span class="step">2</span>So klingst du</h2>
    <p class="tip">Nimm 5 Sekunden auf und hör dir an, wie dich die Frauen hören werden. Am besten mit Kopfhörern oder leiser gestellt, damit es nicht hallt.</p>
    <div class="row">
      <button id="rec" type="button" class="ghost" disabled>5 Sekunden aufnehmen</button>
      <button id="tone" type="button" class="ghost">Lautsprecher testen</button>
    </div>
    <audio id="play" controls hidden style="width:100%;margin-top:14px"></audio>
  </section>

  <section class="card">
    <h2><span class="step">3</span>Testraum</h2>
    <?php if ($ready): ?>
      <p class="tip"><?= $guest ? 'Jetzt geht es in denselben Raum, in dem Viv testet.' : 'Das ist ein echter Whereby-Raum auf deiner Website, genau wie bei einem Gathering. Whereby fragt dort noch einmal nach Kamera und Mikro, das ist normal. Wenn du mit einem zweiten Gerät oder einer Freundin testen willst, schick ihr den Gast-Link aus der Verwaltung.' ?></p>
      <div class="row"><button id="enter" type="button">In den Testraum gehen</button></div>
    <?php else: ?>
      <p class="tip">Der Testraum ist noch nicht da. Trag in der Verwaltung unter „Einstellungen für Movecia“ deinen Whereby-Schlüssel ein, dann entsteht er automatisch. Kamera und Mikro kannst du oben trotzdem schon prüfen.</p>
    <?php endif; ?>
  </section>
</main>

<?php if ($ready): ?>
<div class="roomwrap" id="room" hidden>
  <div class="roombar"><span>Testraum · Wild Hearts</span><button id="leave" type="button">Raum verlassen</button></div>
  <iframe id="frame" title="Whereby Testraum" allow="camera; microphone; fullscreen; speaker; display-capture; autoplay; compute-pressure"></iframe>
</div>
<?php endif; ?>

<script>
(() => {
  const $ = id => document.getElementById(id);
  const v = $('v'), ph = $('ph'), lvl = $('lvl'), st = $('st'), mic = $('mic'), cam = $('cam');
  let stream = null, ctx = null, raf = 0, heard = false;

  const stop = () => {
    cancelAnimationFrame(raf);
    if (stream) stream.getTracks().forEach(t => t.stop());
    stream = null;
    if (ctx) { ctx.close().catch(() => {}); ctx = null; }
    lvl.style.width = '0';
  };

  const fill = async () => {
    const devs = await navigator.mediaDevices.enumerateDevices();
    const put = (sel, kind, cur) => {
      sel.innerHTML = '';
      devs.filter(d => d.kind === kind).forEach((d, i) => {
        const o = document.createElement('option');
        o.value = d.deviceId; o.textContent = d.label || (kind === 'audioinput' ? 'Mikrofon ' : 'Kamera ') + (i + 1);
        if (d.deviceId === cur) o.selected = true;
        sel.appendChild(o);
      });
    };
    const a = stream.getAudioTracks()[0], c = stream.getVideoTracks()[0];
    put(mic, 'audioinput', a && a.getSettings().deviceId);
    put(cam, 'videoinput', c && c.getSettings().deviceId);
    $('pick').hidden = false;
  };

  const start = async () => {
    stop();
    st.className = 'status'; st.textContent = 'Einen Moment …';
    const audio = mic.value ? {deviceId: {exact: mic.value}, echoCancellation: true, noiseSuppression: true} : {echoCancellation: true, noiseSuppression: true};
    const video = cam.value ? {deviceId: {exact: cam.value}} : {facingMode: 'user'};
    try {
      stream = await navigator.mediaDevices.getUserMedia({audio, video});
    } catch (e) {
      try { stream = await navigator.mediaDevices.getUserMedia({audio}); }
      catch (e2) {
        st.textContent = 'Der Browser hat keinen Zugriff bekommen. Erlaube Kamera und Mikrofon (am iPhone: Einstellungen › Safari › Kamera/Mikrofon › Fragen) und tippe noch einmal auf Starten.';
        return;
      }
      ph.textContent = 'Keine Kamera gefunden oder nicht erlaubt. Das Mikro läuft aber.';
    }
    if (stream.getVideoTracks().length) { v.srcObject = stream; ph.hidden = true; } else { ph.hidden = false; }
    ctx = new (window.AudioContext || window.webkitAudioContext)();
    const an = ctx.createAnalyser(); an.fftSize = 1024;
    ctx.createMediaStreamSource(stream).connect(an);
    const buf = new Float32Array(an.fftSize);
    heard = false;
    st.textContent = 'Sprich jetzt etwas …';
    const loop = () => {
      an.getFloatTimeDomainData(buf);
      let s = 0; for (const x of buf) s += x * x;
      const pct = Math.min(100, Math.sqrt(s / buf.length) * 400);
      lvl.style.width = pct + '%';
      if (pct > 12 && !heard) { heard = true; st.className = 'status ok'; st.textContent = 'Dein Mikro funktioniert ♡'; }
      raf = requestAnimationFrame(loop);
    };
    loop();
    setTimeout(() => { if (!heard && stream) st.textContent = 'Noch nichts gehört. Sprich etwas lauter oder wähl unten ein anderes Mikrofon.'; }, 6000);
    $('go').textContent = 'Neu starten';
    $('rec').disabled = !window.MediaRecorder;
    fill().catch(() => {});
  };

  $('go').addEventListener('click', start);
  mic.addEventListener('change', start);
  cam.addEventListener('change', start);

  $('rec').addEventListener('click', () => {
    if (!stream) return;
    const b = $('rec'), chunks = [];
    const rec = new MediaRecorder(new MediaStream(stream.getAudioTracks()));
    rec.ondataavailable = e => e.data.size && chunks.push(e.data);
    rec.onstop = () => {
      const p = $('play');
      p.src = URL.createObjectURL(new Blob(chunks, {type: rec.mimeType || 'audio/mp4'}));
      p.hidden = false; p.play().catch(() => {});
      b.disabled = false; b.textContent = 'Nochmal aufnehmen';
    };
    b.disabled = true;
    let n = 5; b.textContent = 'Aufnahme läuft … ' + n;
    const t = setInterval(() => { n--; if (n > 0) b.textContent = 'Aufnahme läuft … ' + n; else { clearInterval(t); rec.stop(); } }, 1000);
    rec.start();
  });

  $('tone').addEventListener('click', () => {
    const c = new (window.AudioContext || window.webkitAudioContext)();
    [523.25, 659.25, 783.99].forEach((f, i) => {
      const o = c.createOscillator(), g = c.createGain(), t = c.currentTime + i * 0.28;
      o.type = 'sine'; o.frequency.value = f;
      g.gain.setValueAtTime(0, t); g.gain.linearRampToValueAtTime(0.25, t + 0.03); g.gain.exponentialRampToValueAtTime(0.001, t + 0.9);
      o.connect(g).connect(c.destination); o.start(t); o.stop(t + 1);
    });
    setTimeout(() => c.close(), 2000);
  });

  const enter = $('enter');
  if (enter) {
    enter.addEventListener('click', () => {
      stop();
      $('frame').src = <?= json_encode($src) ?>;
      $('room').hidden = false;
      document.body.style.overflow = 'hidden';
    });
    $('leave').addEventListener('click', () => {
      $('frame').src = 'about:blank';
      $('room').hidden = true;
      document.body.style.overflow = '';
    });
  }
})();
</script>
</body>
</html>

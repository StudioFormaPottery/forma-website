<?php
// Technik-Test für Viv: Kamera, Mikro, Lautsprecher prüfen, danach in den Whereby-Testraum.
// Wird nur von gathering/index.php eingebunden (geheimes Token geprüft).
if (!isset($testTok, $ready, $src, $guest)) { http_response_code(404); exit; }
$h = 'forma_h';
$gath = $gath ?? null;
$prompter = $prompter ?? [];
$pText = (string)($prompter['text'] ?? '');
$pSpeed = (int)($prompter['speed'] ?? 40);
$pSize = (int)($prompter['size'] ?? 34);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= $gath ? 'Gathering · Gastgeberin' : 'Technik-Test' ?> · Wild Hearts</title>
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
.roomwrap iframe{flex:1;width:100%;border:0;background:#2a1b16;min-height:0}
textarea{font:inherit;font-size:17px;width:100%;min-height:220px;padding:14px 16px;border-radius:18px;border:1.5px solid var(--line);background:var(--cream);color:var(--ink);line-height:1.6;resize:vertical;margin-top:12px}
.saved{font-size:14px;color:var(--brown);margin-top:6px;min-height:1.2em}
.prompter{position:relative;flex:0 0 auto;height:34vh;min-height:150px;background:#1F1C16;color:#F6F3EA;overflow:hidden;border-bottom:1px solid #4a4337}
.prompter.solo{position:fixed;inset:0;height:auto;z-index:6}
.prompter .scroll{position:absolute;inset:0;overflow-y:auto;padding:18px 7vw 60vh;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.prompter .scroll::-webkit-scrollbar{display:none}
.prompter .txt{white-space:pre-wrap;line-height:1.45;max-width:900px;margin:0 auto}
.prompter .mark{position:absolute;left:8px;top:24px;width:0;height:0;border:8px solid transparent;border-left:12px solid #9DA58F;pointer-events:none}
.prompter .fade{position:absolute;left:0;right:0;bottom:0;height:46px;background:linear-gradient(transparent,#1F1C16);pointer-events:none}
.pctl{position:absolute;right:10px;bottom:8px;display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;z-index:2}
.pctl button{padding:6px 12px;font-size:15px;background:rgba(220,224,210,.92);color:var(--wine-deep);border-radius:999px;min-width:40px}
.pctl .play{background:#9DA58F;color:#1F1C16;font-weight:600}
.pctl span{align-self:center;color:#B3A78F;font-size:13px;padding:0 4px}
.pmin{position:absolute;left:10px;bottom:8px;z-index:2;padding:6px 12px;font-size:14px;background:transparent;color:#B3A78F;border:1px solid #4a4337}
.roombar .rb{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.roombar .recon{background:#93402F;color:#FBF1EA;border-color:#93402F}
.roombar .ghostbtn{background:transparent;color:var(--blush);border:1px solid #6B5D4C}
</style>
</head>
<body>
<div class="bg" aria-hidden="true"><span class="a"></span><span class="b"></span></div>
<main>
  <p class="hand"><?= $guest ? 'schön, dass du testest ♡' : 'kurz durchatmen, dann testen ♡' ?></p>
  <h1><?= $gath ? $h($gath['title']) : 'Technik-Test' ?></h1>
  <p class="lead"><?= $gath ? 'Dein Fenster als Gastgeberin: kurz Kamera und Mikro prüfen, deinen Text in den Teleprompter legen und dann in den Raum. Den Teleprompter siehst nur du, er wird nicht übertragen.' : ($guest ? 'Hier prüfst du Kamera und Mikro und gehst dann in den Testraum zu Viv.' : 'Hier prüfst du Kamera, Mikro und Ton, ganz ohne dass jemand zusieht. Danach kannst du in deinen eigenen Testraum gehen.') ?></p>

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

<?php if (!$guest): ?>
  <section class="card">
    <h2><span class="step">3</span>Teleprompter</h2>
    <p class="tip">Schreib oder kopier hier deinen Text hinein. Er wird automatisch gespeichert und läuft im Raum oben über dem Video, nah an der Kamera, damit dein Blick bei den Frauen bleibt. Nur du siehst ihn, er wird nicht übertragen. Leere Zeilen machen Absätze, so findest du leichter wieder hinein.</p>
    <textarea id="ptext" placeholder="Zum Beispiel: Willkommen, schön, dass du heute da bist …"><?= $h($pText) ?></textarea>
    <p class="saved" id="psaved"></p>
    <div class="row"><button id="ptry" type="button" class="ghost">Teleprompter ausprobieren</button></div>
  </section>
<?php endif; ?>

  <section class="card">
    <h2><span class="step"><?= $guest ? 3 : 4 ?></span><?= $gath ? 'In den Raum' : 'Testraum' ?></h2>
    <?php if ($ready): ?>
      <p class="tip"><?= $gath ? 'Der Raum öffnet sich mit Gastgeber-Rechten. Whereby fragt dort noch einmal nach Kamera und Mikro, das ist normal.' : ($guest ? 'Jetzt geht es in denselben Raum, in dem Viv testet.' : 'Das ist ein echter Whereby-Raum auf deiner Website, genau wie bei einem Gathering. Whereby fragt dort noch einmal nach Kamera und Mikro, das ist normal. Wenn du mit einem zweiten Gerät oder einer Freundin testen willst, schick ihr den Gast-Link aus der Verwaltung.') ?></p>
      <div class="row"><button id="enter" type="button"><?= $gath ? 'Als Gastgeberin in den Raum' : 'In den Testraum gehen' ?></button></div>
    <?php else: ?>
      <p class="tip">Der Testraum ist noch nicht da. Trag in der Verwaltung unter „Einstellungen für Movecia“ deinen Whereby-Schlüssel ein, dann entsteht er automatisch. Kamera und Mikro kannst du oben trotzdem schon prüfen.</p>
    <?php endif; ?>
  </section>
</main>

<?php if ($ready): ?>
<div class="roomwrap" id="room" hidden>
  <div class="roombar"><span><?= $gath ? $h($gath['title']) : 'Testraum · Wild Hearts' ?></span><span class="rb"><?php if (!$guest): ?><button id="rec2" type="button" class="ghostbtn">● Aufnahme</button><button id="ptoggle" type="button" class="ghostbtn">Teleprompter</button><a class="btn ghostbtn" style="padding:7px 16px;font-size:15px" href="?test=<?= $h(rawurlencode($testTok)) ?>&amp;buehne=1" target="_blank" rel="noopener">Video einspielen</a><?php endif; ?><button id="leave" type="button">Raum verlassen</button></span></div>
  <iframe id="frame" title="Whereby Testraum" allow="camera; microphone; fullscreen; speaker; display-capture; autoplay; compute-pressure"></iframe>
</div>
<?php endif; ?>
<?php if (!$guest): ?>
<div class="prompter" id="prompter" hidden>
  <div class="scroll" id="pscroll"><div class="txt" id="ptxt"></div></div>
  <div class="mark" aria-hidden="true"></div><div class="fade" aria-hidden="true"></div>
  <button class="pmin" id="pclose" type="button">ausblenden</button>
  <div class="pctl">
    <button type="button" data-p="top" title="Zum Anfang">⏮</button>
    <button type="button" data-p="slower" title="Langsamer">−</button><span id="pspd"></span><button type="button" data-p="faster" title="Schneller">+</button>
    <button type="button" data-p="small" title="Schrift kleiner">A−</button><button type="button" data-p="big" title="Schrift größer">A+</button>
    <button type="button" class="play" id="pplay">▶ Start</button>
  </div>
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

  // --- Teleprompter: läuft nur in diesem Fenster, wird nie übertragen ---
  const P = $('prompter');
  let pSpeed = <?= $pSpeed ?>, pSize = <?= $pSize ?>, pOn = false, pLast = 0, pPos = 0, pRaf = 0, solo = false;
  if (P) {
    const ta = $('ptext'), txt = $('ptxt'), sc = $('pscroll'), sv = $('psaved');
    let tSave = 0, tPref = 0;
    const save = data => fetch(location.href, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)})
      .then(r => { if ('text' in data) sv.textContent = r.ok ? 'Gespeichert ✓' : 'Konnte nicht speichern, bitte nochmal versuchen.'; })
      .catch(() => { if ('text' in data) sv.textContent = 'Keine Verbindung, der Text bleibt hier im Feld.'; });
    const paint = () => { txt.textContent = ta.value.trim() || 'Noch kein Text. Schreib ihn in das Feld „Teleprompter“.'; txt.style.fontSize = pSize + 'px'; $('pspd').textContent = 'Tempo ' + pSpeed; };
    const pref = () => { clearTimeout(tPref); tPref = setTimeout(() => save({speed: pSpeed, size: pSize}), 600); };
    ta.addEventListener('input', () => { sv.textContent = ''; clearTimeout(tSave); tSave = setTimeout(() => save({text: ta.value}), 800); });
    const step = t => {
      if (pLast) { pPos += (t - pLast) / 1000 * pSpeed; sc.scrollTop = pPos; if (sc.scrollTop + sc.clientHeight >= sc.scrollHeight - 2) { pause(); return; } }
      pLast = t; pRaf = requestAnimationFrame(step);
    };
    const play = () => { pOn = true; pLast = 0; pPos = sc.scrollTop; $('pplay').textContent = '❚❚ Pause'; pRaf = requestAnimationFrame(step); };
    const pause = () => { pOn = false; cancelAnimationFrame(pRaf); $('pplay').textContent = '▶ Weiter'; };
    $('pplay').addEventListener('click', () => pOn ? pause() : play());
    sc.addEventListener('click', () => pOn ? pause() : play());
    sc.addEventListener('scroll', () => { if (!pOn) pPos = sc.scrollTop; });
    P.querySelectorAll('[data-p]').forEach(b => b.addEventListener('click', () => {
      const a = b.dataset.p;
      if (a === 'top') { pause(); sc.scrollTop = pPos = 0; $('pplay').textContent = '▶ Start'; }
      if (a === 'slower') pSpeed = Math.max(5, pSpeed - 5);
      if (a === 'faster') pSpeed = Math.min(200, pSpeed + 5);
      if (a === 'small') pSize = Math.max(18, pSize - 3);
      if (a === 'big') pSize = Math.min(72, pSize + 3);
      paint(); if (a !== 'top') pref();
    }));
    document.addEventListener('keydown', e => {
      if (P.hidden || e.target === ta) return;
      if (e.code === 'Space') { e.preventDefault(); pOn ? pause() : play(); }
      if (e.code === 'ArrowUp') { e.preventDefault(); pSpeed = Math.min(200, pSpeed + 5); paint(); pref(); }
      if (e.code === 'ArrowDown') { e.preventDefault(); pSpeed = Math.max(5, pSpeed - 5); paint(); pref(); }
    });
    window.showPrompter = inRoom => { paint(); solo = !inRoom; P.classList.toggle('solo', solo); P.hidden = false; };
    window.hidePrompter = () => { pause(); P.hidden = true; };
    $('pclose').addEventListener('click', () => { window.hidePrompter(); if (solo) document.body.style.overflow = ''; });
    $('ptry').addEventListener('click', () => { window.showPrompter(false); document.body.style.overflow = 'hidden'; });
    paint();
  }

  // --- Aufnahme nur von Viv (eigene Kamera + Mikro, nicht der Whereby-Raum) ---
  const R = $('rec2');
  let rStream = null, rRec = null, rChunks = [], rT0 = 0, rTimer = 0;
  if (R) {
    const fmt = () => { const s = Math.floor((Date.now() - rT0) / 1000); return '■ Stopp ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
    const finish = () => {
      clearInterval(rTimer);
      const type = rRec.mimeType || 'video/webm', ext = type.includes('mp4') ? 'mp4' : 'webm';
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob(rChunks, {type}));
      a.download = 'Wild-Hearts-Gathering-' + new Date().toISOString().slice(0, 10) + '.' + ext;
      document.body.appendChild(a); a.click(); a.remove();
      rStream.getTracks().forEach(t => t.stop());
      rStream = rRec = null; rChunks = [];
      R.textContent = '● Aufnahme'; R.classList.remove('recon');
      alert('Die Aufnahme ist gespeichert (Downloads-Ordner). Darauf bist nur du zu sehen und zu hören.');
    };
    R.addEventListener('click', async () => {
      if (rRec) { rRec.stop(); return; }
      if (!window.MediaRecorder) { alert('Dieser Browser kann nicht aufnehmen. Bitte nimm Chrome, Edge oder Safari am Computer.'); return; }
      if (!confirm('Aufnahme starten? Aufgenommen werden nur deine Kamera und dein Mikro, die Teilnehmerinnen sind nicht zu sehen. Lass dieses Fenster bis zum Ende offen und tipp dann auf Stopp, dann wird die Datei gespeichert.')) return;
      try {
        rStream = await navigator.mediaDevices.getUserMedia({video: {width: {ideal: 1280}, height: {ideal: 720}}, audio: {echoCancellation: true, noiseSuppression: true}});
      } catch (e) { alert('Kein Zugriff auf Kamera oder Mikro. Bitte im Browser erlauben.'); return; }
      const types = ['video/mp4;codecs=avc1,mp4a', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];
      const mimeType = types.find(t => MediaRecorder.isTypeSupported(t)) || '';
      rRec = new MediaRecorder(rStream, mimeType ? {mimeType, videoBitsPerSecond: 1500000} : {videoBitsPerSecond: 1500000});
      rChunks = [];
      rRec.ondataavailable = e => e.data.size && rChunks.push(e.data);
      rRec.onstop = finish;
      rRec.start(5000); rT0 = Date.now();
      R.classList.add('recon'); R.textContent = fmt(); rTimer = setInterval(() => R.textContent = fmt(), 1000);
    });
    window.addEventListener('beforeunload', e => { if (rRec) { e.preventDefault(); e.returnValue = ''; } });
  }

  const enter = $('enter');
  if (enter) {
    enter.addEventListener('click', () => {
      stop();
      $('frame').src = <?= json_encode($src) ?>;
      $('room').hidden = false;
      document.body.style.overflow = 'hidden';
      if (P) {
        $('room').insertBefore(P, $('frame'));
        if ($('ptext').value.trim()) window.showPrompter(true); else P.hidden = true;
        $('ptoggle').onclick = () => P.hidden ? window.showPrompter(true) : window.hidePrompter();
      }
    });
    $('leave').addEventListener('click', () => {
      if (rRec && !confirm('Die Aufnahme läuft noch. Beim Verlassen wird sie jetzt beendet und gespeichert. Raum verlassen?')) return;
      if (rRec) rRec.stop();
      $('frame').src = 'about:blank';
      $('room').hidden = true;
      document.body.style.overflow = '';
      if (P) { window.hidePrompter(); document.body.appendChild(P); }
    });
  }
})();
</script>
</body>
</html>

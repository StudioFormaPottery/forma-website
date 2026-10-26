<?php
// Video-Bühne: Viv wählt Videos von ihrem eigenen Gerät (nichts wird hochgeladen) und teilt
// diesen Tab in Whereby. Die Frauen sehen das Video groß, Viv spricht live das Voice-over.
if (!isset($testTok)) { http_response_code(404); exit; }
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Video-Bühne · Wild Hearts</title>
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<style>
@font-face{font-family:"Outfit";font-weight:300 500;font-display:swap;src:url(/fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Italiana";font-weight:400;font-display:swap;src:url(/fonts/Italiana-Regular.woff) format("woff")}
*{box-sizing:border-box;margin:0}
[hidden]{display:none!important}
html,body{height:100%;background:#1F181B;color:#F2ECE4;font-family:"Outfit",system-ui,sans-serif;font-size:17px;line-height:1.55}
.stage{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:#000}
.stage video{width:100%;height:100%;object-fit:contain;background:#000}
.idle{position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;background:#4A3A41;text-align:center;padding:24px}
.idle h1{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(36px,6vw,60px);color:#EADFDE}
.idle p{color:#C9B2B8}
.panel{position:fixed;left:16px;top:16px;width:min(420px,calc(100% - 32px));max-height:calc(100% - 32px);overflow:auto;background:rgba(31,22,26,.94);border:1px solid #5A4C52;border-radius:20px;padding:16px;z-index:3}
.panel h2{font-size:18px;font-weight:500;color:#EADFDE;margin-bottom:6px}
.panel ol{padding-left:20px;font-size:15px;color:#E8DED2}
.panel li{margin:4px 0}
.panel small{display:block;color:#C9B2B8;font-size:13.5px;margin-top:8px}
.list{display:flex;flex-direction:column;gap:6px;margin-top:12px}
.list button{text-align:left;background:#2B2226;color:#F2ECE4;border:1px solid #5A4C52}
.list button.on{background:#7D5E68;border-color:#7D5E68}
button,label.btn{font:inherit;font-size:16px;padding:10px 16px;border-radius:999px;border:0;background:#B79FA6;color:#1F181B;cursor:pointer;display:inline-block}
.row{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;align-items:center}
.chk{display:flex;gap:8px;align-items:center;font-size:15px;color:#E8DED2}
.chk input{width:18px;height:18px;accent-color:#B79FA6}
.ghost{background:transparent;color:#EADFDE;border:1px solid #7D7076}
.show{position:fixed;left:16px;top:16px;z-index:3;padding:6px 12px;font-size:14px;background:rgba(31,22,26,.7);color:#C9B2B8;border:1px solid #5A4C52}
</style>
<script src="/wildhearts/assets/schutz.js?v=2"></script>
</head>
<body>
<div class="idle" id="idle"><h1>Wild Hearts</h1><p>Gleich geht es weiter ♡</p></div>
<div class="stage" id="stage" hidden><video id="vid" playsinline></video></div>

<div class="panel" id="panel">
  <h2>Video-Bühne</h2>
  <ol>
    <li>Wähl unten ein oder mehrere Videos von deinem Gerät. Hochgeladen wird nichts, sie laufen nur hier.</li>
    <li>Geh zurück in den Raum, tipp in Whereby auf <b>Bildschirm teilen</b> und wähl diesen Tab <b>„Video-Bühne“</b>.</li>
    <li>Schalt in Whereby deine <b>Kamera aus</b>, das Mikro bleibt an. Die Frauen sehen dann nur das Video groß und hören dich live.</li>
    <li>Hier auf <b>Abspielen</b> tippen und dazu sprechen. Danach Teilen beenden und Kamera wieder an.</li>
  </ol>
  <label class="btn" style="margin-top:12px">Videos auswählen<input id="file" type="file" accept="video/*" multiple hidden></label>
  <div class="list" id="list"></div>
  <div class="row">
    <button id="play" type="button" disabled>▶ Abspielen</button>
    <button id="stop" type="button" class="ghost" disabled>Pause</button>
  </div>
  <div class="row">
    <label class="chk"><input type="checkbox" id="snd"> Ton vom Video mit übertragen (z. B. Musik)</label>
  </div>
  <div class="row">
    <label class="chk"><input type="checkbox" id="loop"> In Schleife abspielen</label>
  </div>
  <h2 style="margin-top:18px">Hintergrundmusik</h2>
  <p style="font-size:15px;color:#E8DED2">Für Bewegungseinheiten: Musik ganz leise im Hintergrund. Wähl eine Musikdatei von deinem Gerät, teil diesen Tab in Whereby mit „Tab-Audio teilen“ und lass deine Kamera an. Die Frauen sehen dich und hören die Musik leise unter deiner Stimme.</p>
  <label class="btn" style="margin-top:10px">Musik auswählen<input id="mfile" type="file" accept="audio/*" multiple hidden></label>
  <p id="mname" style="font-size:14px;color:#C9B2B8;margin-top:6px"></p>
  <div class="row">
    <button id="mplay" type="button" disabled>▶ Musik</button>
    <label class="chk" style="flex:1 1 180px">Lautstärke <input id="mvol" type="range" min="0" max="60" value="15" style="flex:1;accent-color:#B79FA6"></label>
  </div>
  <audio id="mus" loop></audio>
  <small>Ohne Häkchen läuft das Video stumm und die Frauen hören nur dich. Mit Ton: beim Teilen in Whereby „Tab-Audio teilen“ anhaken. Am besten am Computer mit Chrome oder Edge, vom Handy aus lässt sich kein Tab teilen. Die Hilfe hier blendet sich beim Abspielen aus, damit die Frauen nur das Video sehen.</small>
</div>
<button class="show" id="show" type="button" hidden>Bedienung</button>

<script>
(() => {
  const $ = id => document.getElementById(id), v = $('vid');
  let files = [], cur = -1;
  const sel = i => {
    cur = i; v.src = URL.createObjectURL(files[i]); v.load();
    [...$('list').children].forEach((b, k) => b.classList.toggle('on', k === i));
    $('play').disabled = $('stop').disabled = false;
  };
  $('file').addEventListener('change', e => {
    files = [...e.target.files]; $('list').innerHTML = '';
    files.forEach((f, i) => { const b = document.createElement('button'); b.type = 'button'; b.textContent = f.name.replace(/\.[^.]+$/, ''); b.onclick = () => sel(i); $('list').appendChild(b); });
    if (files.length) sel(0);
  });
  const hidePanel = () => { $('panel').hidden = true; $('show').hidden = false; };
  $('show').onclick = () => { $('panel').hidden = false; $('show').hidden = true; };
  $('play').onclick = () => {
    if (cur < 0) return;
    v.muted = !$('snd').checked; v.loop = $('loop').checked;
    $('idle').hidden = true; $('stage').hidden = false; v.play(); hidePanel();
  };
  $('stop').onclick = () => v.pause();
  // Hintergrundmusik: Playlist von eigenen Dateien, leise, in Schleife
  const mu = $('mus'); let mfiles = [], mi = 0;
  const mset = () => { mu.volume = $('mvol').value / 100; };
  const mload = i => { mi = i; mu.src = URL.createObjectURL(mfiles[i]); $('mname').textContent = '♪ ' + mfiles[i].name.replace(/\.[^.]+$/, ''); mset(); };
  $('mfile').addEventListener('change', e => { mfiles = [...e.target.files]; if (mfiles.length) { mload(0); mu.loop = mfiles.length === 1; $('mplay').disabled = false; } });
  mu.addEventListener('ended', () => { if (mfiles.length > 1) { mload((mi + 1) % mfiles.length); mu.play(); } });
  $('mvol').addEventListener('input', mset);
  $('mplay').onclick = () => { if (mu.paused) { mset(); mu.play(); $('mplay').textContent = '❚❚ Musik'; } else { mu.pause(); $('mplay').textContent = '▶ Musik'; } };
  $('snd').onchange = () => { v.muted = !$('snd').checked; };
  $('loop').onchange = () => { v.loop = $('loop').checked; };
  v.addEventListener('ended', () => {
    if (cur + 1 < files.length) { sel(cur + 1); v.muted = !$('snd').checked; v.play(); }
    else { $('stage').hidden = true; $('idle').hidden = false; $('show').click(); }
  });
  document.addEventListener('keydown', e => { if (e.code === 'Space' && cur >= 0) { e.preventDefault(); v.paused ? $('play').click() : v.pause(); } });
})();
</script>
</body>
</html>

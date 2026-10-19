<?php
// Technik-Test für Viv: Kamera, Mikro, Lautsprecher prüfen, danach in den Whereby-Testraum.
// Wird nur von gathering/index.php eingebunden (geheimes Token geprüft).
if (!isset($testTok, $ready, $src, $guest)) { http_response_code(404); exit; }
$h = 'forma_h';
$gath = $gath ?? null;
$prompter = $prompter ?? [];
$pText = (string)($prompter['text'] ?? '');
$uFragen = $guest ? null : ((mm_cfg('uebung') ?? [])['fragen'] ?? null);
$pSpeed = (int)($prompter['speed'] ?? 40);
$pSize = (int)($prompter['size'] ?? 34);
$offers = $guest ? [] : mm_offers();
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
.practice{position:fixed;inset:0;z-index:7;background:#1F1C16;display:flex;flex-direction:column}
.practice canvas{flex:1;width:100%;min-height:0;object-fit:contain;background:#1F1C16}
.practice .pbar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:10px 16px;color:#B3A78F;font-size:14px}
.practice .pbar button{padding:7px 16px;font-size:15px;background:#DCE0D2;color:#3F4A26}
.practice .pbar .recon{background:#93402F;color:#FBF1EA}
.pask{display:flex;gap:8px;flex-wrap:wrap;padding:10px 16px;background:#1F1C16;border-top:1px solid #4a4337}
.pask select,.pask input{font:inherit;font-size:16px;padding:9px 12px;border-radius:12px;border:1px solid #4a4337;background:#2c2820;color:#F6F3EA}
.pask input{flex:1 1 260px;min-width:0}
.pask button{padding:9px 16px;font-size:15px;background:#DCE0D2;color:#3F4A26}
.pask button.ghost{background:transparent;color:#DCE0D2;border:1px solid #6B5D4C}
.ublist{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.ubrow{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.ubrow input[type=text]{font:inherit;font-size:16px;padding:10px 14px;border-radius:14px;border:1.5px solid var(--line);background:var(--cream);flex:1 1 160px;min-width:0}
.ubrow label.pick{font-size:14px;padding:8px 12px;border-radius:999px;border:1.5px solid var(--line);cursor:pointer;color:var(--wine-deep);background:var(--cream)}
.ubrow .sw{width:34px;height:34px;border-radius:10px;border:1.5px solid var(--line);background-size:cover;background-position:center;flex:0 0 auto}
.ubrow button{padding:8px 12px;font-size:14px}
.offd{position:fixed;left:16px;bottom:16px;z-index:21;width:min(380px,calc(100% - 32px));max-height:calc(100% - 90px);overflow:auto;background:#FCFBF6;color:var(--ink);border-radius:22px;padding:18px;box-shadow:0 18px 44px rgba(20,18,12,.4);font-size:16px}
.offd h3{font-size:19px;font-weight:500;color:var(--wine-deep);margin-bottom:8px}
.offd label{display:flex;flex-direction:column;gap:4px;font-size:14px;color:var(--brown);margin-top:10px}
.offd select,.offd input{font:inherit;font-size:16px;padding:10px 12px;border-radius:12px;border:1.5px solid var(--line);background:var(--cream);color:var(--ink)}
.offd .two{display:flex;gap:10px}.offd .two label{flex:1;min-width:0}.offd .two input{width:100%;min-width:0}
.offd .st{margin-top:12px;padding:10px 12px;border-radius:14px;background:var(--sage-soft);font-size:15px;color:var(--wine-deep)}
.offd .warn{margin-top:10px;font-size:13.5px;color:#8A3B2E}
.offd .acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.offd .acts button{padding:10px 16px;font-size:15px}
.offd .cl{position:absolute;right:10px;top:8px;background:transparent;color:var(--brown);font-size:22px;padding:4px 8px}
.qd{position:fixed;right:16px;top:70px;z-index:21;width:min(400px,calc(100% - 32px));max-height:calc(100% - 90px);overflow:auto;background:#FCFBF6;color:var(--ink);border-radius:22px;padding:18px;box-shadow:0 18px 44px rgba(20,18,12,.4);font-size:16px}
.qd h3{font-size:19px;font-weight:500;color:var(--wine-deep);margin-bottom:4px}
.qd .hint{font-size:13.5px;color:var(--brown);margin-bottom:10px}
.qd .q{border-top:1px solid var(--line);padding:10px 0}
.qd .q small{display:block;font-size:12.5px;color:var(--brown)}
.qd .q p{margin:2px 0 8px;font-size:16px;line-height:1.45}
.qd .q.on{background:var(--sage-soft);border-radius:12px;padding:10px;border-top:0}
.qd .q.done p{color:#9A9282}
.qd .q button{padding:6px 12px;font-size:14px}
.qd .own{display:flex;flex-direction:column;gap:8px;margin-top:12px;border-top:1px solid var(--line);padding-top:12px}
.qd .own select,.qd .own input{font:inherit;font-size:15px;padding:9px 12px;border-radius:12px;border:1.5px solid var(--line);background:var(--cream);color:var(--ink);width:100%}
.qd .cl{position:absolute;right:10px;top:8px;background:transparent;color:var(--brown);font-size:22px;padding:4px 8px}
#qbadge:not(:empty){display:inline-block;min-width:20px;padding:0 6px;border-radius:999px;background:#B9C77A;color:#1F1C16;font-size:12px;margin-left:4px}
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

<?php if (!$guest && !$gath): ?>
  <section class="card">
    <h2><span class="step">✦</span>Übungsansicht</h2>
    <p class="tip">Zum Üben vor einem vollen Kreis: dein echtes Kamerabild und dazu bis zu 15 Fenster mit Namen, als hätten die Frauen ihre Kamera aus. Diese Fenster gibt es nur hier auf deinem Bildschirm, im echten Raum tauchen sie nie auf. Mit der Aufnahme kannst du dir danach ansehen, wie du wirkst. Hintergrundbilder wählst du von deinem Gerät, hochgeladen wird nichts.</p>
    <div class="ublist" id="ublist"></div>
    <div class="row"><button type="button" class="ghost" id="ubadd">+ Fenster</button></div>
    <label class="f" style="margin-top:16px">Name des Calls (steht oben im Raum und im Dateinamen der Aufnahme)<input id="ubtitle" type="text" maxlength="80" placeholder="z. B. Wild Hearts Gathering · Dezember" style="font:inherit;font-size:16px;padding:11px 14px;border-radius:14px;border:1.5px solid var(--line);background:var(--cream)"></label>
    <h3 style="font-size:18px;font-weight:500;margin-top:18px;color:var(--wine-deep)">Übungsfragen</h3>
    <p class="tip">Eine Frage pro Zeile. Die Fragen kommen während der Übung wie Nachrichten in den Chat rechts, und das fragende Fenster hebt die Hand. Es können mehrere Fragen gleichzeitig offen sein, „Beantwortet ✓“ hakt immer die älteste ab. Schreibst du „Anna: …“ davor, fragt genau dieses Fenster, sonst ein zufälliges.</p>
    <p class="tip"><strong>Feste Zeitpunkte:</strong> Mit „10 Min:“ am Anfang kommt die Frage genau 10 Minuten nach Beginn der Übung, zum Beispiel „10 Min: Sophie: How do you find time for yourself?“. So kannst du dein Gathering durchplanen. Fragen ohne Zeit kommen zwischendurch, wenn du „Nächste Frage“ tippst oder wie unten eingestellt. Die Liste wird automatisch gespeichert, auch für deine anderen Geräte.</p>
    <textarea id="ubq" style="min-height:200px"></textarea>
    <p class="saved" id="ubqsaved"></p>
    <div class="row">
      <label class="f" style="flex:0 1 260px">Fragen kommen<select id="ubqmode"><option value="0">wenn ich auf „Nächste Frage“ tippe</option><option value="30">etwa alle 30 Sekunden</option><option value="60">etwa jede Minute</option><option value="120">etwa alle 2 Minuten</option><option value="180">etwa alle 3 Minuten</option></select></label>
      <label class="chk" style="align-self:flex-end;display:flex;gap:8px;align-items:center;font-size:15px;color:var(--brown)"><input type="checkbox" id="ubqmix" checked style="width:18px;height:18px;accent-color:var(--wine)"> zufällige Reihenfolge</label>
      <label class="chk" style="align-self:flex-end;display:flex;gap:8px;align-items:center;font-size:15px;color:var(--brown)"><input type="checkbox" id="ubqburst" style="width:18px;height:18px;accent-color:var(--wine)"> manchmal mehrere Fragen kurz hintereinander</label>
    </div>
    <div class="row"><button type="button" id="ubopen">Übungsansicht öffnen</button></div>
  </section>
<?php endif; ?>
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
  <div class="roombar"><span><?= $gath ? $h($gath['title']) : 'Testraum · Wild Hearts' ?></span><span class="rb"><?php if (!$guest): ?><button id="qbtn" type="button" class="ghostbtn">? Fragen <span id="qbadge"></span></button><button id="offbtn" type="button" class="ghostbtn">✦ Angebot</button><button id="rec2" type="button" class="ghostbtn">● Aufnahme</button><button id="ptoggle" type="button" class="ghostbtn">Teleprompter</button><a class="btn ghostbtn" style="padding:7px 16px;font-size:15px" href="?test=<?= $h(rawurlencode($testTok)) ?>&amp;buehne=1" target="_blank" rel="noopener">Video &amp; Musik</a><?php endif; ?><button id="leave" type="button">Raum verlassen</button></span></div>
  <iframe id="frame" title="Whereby Testraum" allow="camera; microphone; fullscreen; speaker; display-capture; autoplay; compute-pressure"></iframe>
</div>
<?php endif; ?>
<?php if (!$guest && $ready): ?>
<?php $pre = array_values(array_filter(array_map(fn($l) => trim(preg_replace('/^\d{1,3}(?:[.,]\d{1,2})?\s*(?:min\.?|minute[n]?|m)\s*:?\s*/i', '', preg_replace('/^[^:?!.]{1,30}:\s*(?=\S)/u', '', trim($l)))), explode("\n", (string)((mm_cfg('uebung') ?? [])['fragen'] ?? ''))))); ?>
<div class="qd" id="qd" hidden>
  <button class="cl" type="button" id="qcl" aria-label="Schließen">×</button>
  <h3>Fragen</h3>
  <p class="hint">Fragen, die Frauen anonym über „Ask anonymously“ schicken, landen hier. „Einblenden“ zeigt sie allen groß oben im Raum, ohne Namen. Deine eigenen Fragen erscheinen offen als „A question I often get“.</p>
  <div id="qlist"><p class="hint">Noch keine Fragen.</p></div>
  <div class="own">
    <?php if ($pre): ?><select id="qpre"><option value="">Aus deiner vorbereiteten Liste wählen …</option><?php foreach (array_unique($pre) as $q): ?><option><?= $h($q) ?></option><?php endforeach; ?></select><?php endif; ?>
    <input id="qown" type="text" maxlength="500" placeholder="Eigene Frage eintippen …">
    <button type="button" id="qownbtn">Als meine Frage einblenden</button>
  </div>
</div>
<div class="offd" id="offd" hidden>
  <button class="cl" type="button" id="offcl" aria-label="Schließen">×</button>
  <h3>Angebot einblenden</h3>
  <?php $anyPrice = array_filter($offers, fn($o) => $o['price_cents'] > 0); ?>
  <?php if (!$anyPrice): ?><p class="warn">Trag zuerst in der Verwaltung unter Movecia Movement → Angebote die regulären Preise ein.</p><?php endif; ?>
  <label>Angebot<select id="offsel"><?php foreach ($offers as $k => $o): ?><option value="<?= $h($k) ?>" data-reg="<?= (int)$o['price_cents'] ?>" data-sp="<?= (int)$o['special_cents'] ?>" data-min="<?= (int)$o['minutes'] ?>" data-mon="<?= $o['billing'] === 'monthly' ? 1 : 0 ?>"<?= $o['price_cents'] ? '' : ' disabled' ?>><?= $h($o['title']) ?><?= $o['price_cents'] ? ' · regulär ' . $h(forma_euro($o['price_cents'])) . ($o['billing'] === 'monthly' ? '/Monat' : '') : ' · noch kein Preis' ?></option><?php endforeach; ?></select></label>
  <div class="two">
    <label>Gathering-Preis in €<input id="offsp" inputmode="decimal" placeholder="z. B. 290"></label>
    <label>gilt für Minuten<input id="offmin" type="number" min="1" max="180" value="25"></label>
  </div>
  <div class="acts"><button type="button" id="offshow">Jetzt einblenden</button><button type="button" class="ghost" id="offhide" hidden>Ausblenden</button></div>
  <div class="st" id="offst" hidden></div>
  <p class="warn" id="offerr" hidden></p>
  <p class="warn" id="offstripe" hidden></p>
</div>
<?php endif; ?>
<?php if (!$guest && !$gath): ?>
<div class="practice" id="practice" hidden>
  <div class="pbar"><span>Übung · <span id="ubttl"></span> · <span id="ubclock"></span></span><span style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" id="ubnext">Nächste Frage</button><button type="button" id="ubdone" hidden>Beantwortet ✓</button><button type="button" id="ubrec">● Übung aufnehmen</button><button type="button" id="ubprompt">Teleprompter</button><button type="button" id="ubclose">Schließen</button></span></div>
  <canvas id="ubcv" width="1920" height="1080"></canvas>
  <form class="pask" id="ubask"><select id="ubwho" aria-label="Wer fragt"></select><input id="ubtxt" type="text" placeholder="Neue Frage, die dir gerade einfällt …" maxlength="300" autocomplete="off"><button type="submit">Jetzt senden</button><button type="button" id="ublater" class="ghost">Für später</button></form>
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

<script src="/wildhearts/gathering/offer.js?v=2"></script>
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
    // Am iPhone muss die Tonauswertung direkt beim Antippen starten, sonst bleibt sie stumm
    const AC = window.AudioContext || window.webkitAudioContext;
    const ac = AC ? new AC() : null;
    if (ac && ac.state !== 'running') ac.resume().catch(() => {});
    st.className = 'status'; st.textContent = 'Einen Moment …';
    const audio = mic.value ? {deviceId: {exact: mic.value}, echoCancellation: true, noiseSuppression: true} : {echoCancellation: true, noiseSuppression: true};
    const video = cam.value ? {deviceId: {exact: cam.value}} : {facingMode: 'user'};
    try {
      stream = await navigator.mediaDevices.getUserMedia({audio, video});
    } catch (e) {
      try { stream = await navigator.mediaDevices.getUserMedia({audio}); }
      catch (e2) {
        if (ac) ac.close().catch(() => {});
        st.textContent = 'Der Browser hat keinen Zugriff bekommen. Erlaube Kamera und Mikrofon (am iPhone: Einstellungen › Safari › Kamera/Mikrofon › Fragen) und tippe noch einmal auf Starten.';
        return;
      }
      ph.textContent = 'Keine Kamera gefunden oder nicht erlaubt. Das Mikro läuft aber.';
    }
    if (stream.getVideoTracks().length) { v.srcObject = stream; ph.hidden = true; } else { ph.hidden = false; }
    ctx = ac;
    if (!ctx) { st.textContent = 'Dieser Browser kann den Mikro-Pegel nicht anzeigen. Nimm die 5-Sekunden-Aufnahme unten, um dein Mikro zu prüfen.'; $('rec').disabled = !window.MediaRecorder; fill().catch(() => {}); return; }
    if (ctx.state !== 'running') { await ctx.resume().catch(() => {}); }
    const an = ctx.createAnalyser(); an.fftSize = 1024;
    ctx.createMediaStreamSource(stream).connect(an);
    const buf = new Float32Array(an.fftSize);
    heard = false;
    st.textContent = 'Sprich jetzt etwas …';
    const loop = () => {
      an.getFloatTimeDomainData(buf);
      let s = 0; for (const x of buf) s += x * x;
      const pct = Math.min(100, Math.sqrt(s / buf.length) * 700);
      lvl.style.width = pct + '%';
      if (pct > 6 && !heard) { heard = true; st.className = 'status ok'; st.textContent = 'Dein Mikro funktioniert ♡'; }
      raf = requestAnimationFrame(loop);
    };
    loop();
    setTimeout(() => { if (!heard && stream) st.textContent = ctx && ctx.state !== 'running' ? 'Tippe einmal hier auf „Neu starten“, dann zeigt der Balken deine Stimme.' : 'Noch nichts gehört. Sprich etwas lauter, wähl unten ein anderes Mikrofon oder prüf es mit der 5-Sekunden-Aufnahme.'; }, 6000);
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
      if (P.hidden || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
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

  // --- Übungsansicht: nur lokal gezeichnet, nichts davon geht in den Whereby-Raum ---
  const UL = $('ublist');
  if (UL) {
    const SW = ['#5E6B35', '#9DA58F', '#6B5D4C', '#B3A78F', '#3F4A26', '#8A8F6E'];
    const DEF = ['Anna', 'Sophie', 'Lena', 'Emily', 'Julia', 'Katrin', 'Sarah', 'Clara', 'Hannah', 'Jessica', 'Maria', 'Theresa', 'Olivia', 'Nina', 'Rachel'];
    let rows = [];
    try { rows = JSON.parse(localStorage.getItem('wh-uebung') || 'null') || []; } catch (e) {}
    if (!rows.length) rows = DEF.slice(0, 5).map((n, i) => ({name: n, color: SW[i % SW.length]}));
    const imgs = new Map();
    const store = () => { try { localStorage.setItem('wh-uebung', JSON.stringify(rows.map(r => ({name: r.name, color: r.color})))); } catch (e) {} };
    const draw = () => {
      UL.innerHTML = '';
      rows.forEach((r, i) => {
        const d = document.createElement('div'); d.className = 'ubrow';
        const sw = document.createElement('span'); sw.className = 'sw'; sw.style.background = imgs.get(i) ? 'url(' + imgs.get(i).src + ') center/cover' : r.color;
        const n = document.createElement('input'); n.type = 'text'; n.value = r.name; n.maxLength = 30; n.oninput = () => { r.name = n.value; store(); };
        const pick = document.createElement('label'); pick.className = 'pick'; pick.textContent = 'Bild';
        const f = document.createElement('input'); f.type = 'file'; f.accept = 'image/*'; f.hidden = true; pick.appendChild(f);
        f.onchange = () => { if (!f.files[0]) return; const im = new Image(); im.onload = () => { imgs.set(i, im); draw(); }; im.src = URL.createObjectURL(f.files[0]); };
        const col = document.createElement('button'); col.type = 'button'; col.className = 'ghost'; col.textContent = 'Farbe';
        col.onclick = () => { imgs.delete(i); r.color = SW[(SW.indexOf(r.color) + 1) % SW.length]; store(); draw(); };
        const del = document.createElement('button'); del.type = 'button'; del.className = 'ghost'; del.textContent = '✕';
        del.onclick = () => { rows.splice(i, 1); const m = new Map(); imgs.forEach((v, k) => { if (k < i) m.set(k, v); else if (k > i) m.set(k - 1, v); }); imgs.clear(); m.forEach((v, k) => imgs.set(k, v)); store(); draw(); };
        d.append(sw, n, pick, col, del); UL.appendChild(d);
      });
    };
    $('ubadd').onclick = () => { if (rows.length >= 15) return; rows.push({name: DEF.find(d => !rows.some(r => r.name.trim() === d)) || 'Gast ' + (rows.length + 1), color: SW[rows.length % SW.length]}); store(); draw(); };
    draw();

    const QDEF = ["Hi Viv! Where are you joining from today?", "How do you find time for yourself with four kids?", "What do you do on days when you feel you got nothing done?", "I always feel guilty when I take time for myself. How do I stop that?", "Is this circle also for women without children?", "How long have you been doing this?", "What exactly happens in The Inner Circle, and what does it cost?", "Can I join the Alpine Retreat with my partner?", "I'm new here and a bit shy. Do I have to turn my camera on?", "What is one small thing I can do tomorrow morning?", "Do you ever feel like giving up?", "Will there be a recording of today?"];
    const ta2 = $('ubq'), qm = $('ubqmode'), qx = $('ubqmix');
    const SRV = <?= json_encode($uFragen) ?>, SRVT = <?= json_encode($guest ? null : ((mm_cfg('uebung') ?? [])['titel'] ?? null)) ?>;
    const tIn = $('ubtitle');
    try { tIn.value = SRVT ?? localStorage.getItem('wh-call-titel') ?? 'Wild Hearts Gathering'; } catch (e) { tIn.value = SRVT ?? 'Wild Hearts Gathering'; }
    let tSave2 = 0;
    tIn.oninput = () => { try { localStorage.setItem('wh-call-titel', tIn.value); } catch (e) {} clearTimeout(tSave2); tSave2 = setTimeout(() => fetch(location.href, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({titel: tIn.value})}).catch(() => {}), 800); };
    const callTitle = () => tIn.value.trim() || 'Wild Hearts Gathering';
    try { ta2.value = SRV ?? localStorage.getItem('wh-fragen') ?? QDEF.join('\n'); qm.value = localStorage.getItem('wh-fragen-takt') || '0'; } catch (e) { ta2.value = SRV ?? QDEF.join('\n'); }
    let qSave = 0;
    ta2.oninput = () => {
      try { localStorage.setItem('wh-fragen', ta2.value); } catch (e) {}
      $('ubqsaved').textContent = ''; clearTimeout(qSave);
      qSave = setTimeout(() => fetch(location.href, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({fragen: ta2.value})}).then(r => { $('ubqsaved').textContent = r.ok ? 'Gespeichert ✓' : ''; }).catch(() => {}), 800);
    };
    qm.onchange = () => { try { localStorage.setItem('wh-fragen-takt', qm.value); } catch (e) {} if (!PR.hidden) qLoop(); };
    let qList = [], qTimed = [], qMsgs = [], qTimer = 0, qStart = 0, qClock = 0;
    const NAMECOL = ['#5E6B35', '#8A5A44', '#3F6B6B', '#7A5C8A', '#A0703A', '#4E6E3A', '#9A4F5F', '#3F5A82', '#6B5D4C', '#7C7A2F'];
    const parseQ = l => {
      let at = -1;
      const tm = l.match(/^(\d{1,3}(?:[.,]\d{1,2})?)\s*(?:min\.?|minute[n]?|m)\s*:?\s*/i);
      if (tm) { at = parseFloat(tm[1].replace(',', '.')) * 60000; l = l.slice(tm[0].length); }
      const m = l.match(/^([^:?!.]{1,30}):\s*(.+)$/);
      const who = m ? rows.findIndex(r => r.name.trim().toLowerCase() === m[1].trim().toLowerCase()) : -1;
      return {who, text: m && who >= 0 ? m[2] : l, at};
    };
    const qPrep = () => {
      const all = ta2.value.split('\n').map(l => l.trim()).filter(Boolean).map(parseQ);
      qTimed = all.filter(q => q.at >= 0).sort((a, b) => a.at - b.at);
      qList = all.filter(q => q.at < 0);
      if (qx.checked) for (let i = qList.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [qList[i], qList[j]] = [qList[j], qList[i]]; }
    };
    const pling = () => {
      try {
        const c = new (window.AudioContext || window.webkitAudioContext)(), o = c.createOscillator(), gn = c.createGain(), t = c.currentTime;
        o.type = 'sine'; o.frequency.setValueAtTime(880, t); o.frequency.setValueAtTime(1320, t + 0.09);
        gn.gain.setValueAtTime(0, t); gn.gain.linearRampToValueAtTime(0.12, t + 0.02); gn.gain.exponentialRampToValueAtTime(0.001, t + 0.35);
        o.connect(gn).connect(c.destination); o.start(t); o.stop(t + 0.4); setTimeout(() => c.close(), 600);
      } catch (e) {}
    };
    const openCount = () => qMsgs.filter(m => !m.done && m.who >= 0).length;
    const qSync = () => { const n = openCount(); $('ubdone').hidden = !n; $('ubdone').textContent = n > 1 ? 'Beantwortet ✓ (' + n + ' offen)' : 'Beantwortet ✓'; };
    const qPush = q => {
      const now = new Date(), time = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
      qMsgs.push({who: q.who >= 0 && q.who < rows.length ? q.who : Math.floor(Math.random() * rows.length), text: q.text, at: performance.now(), time, done: false});
      if (qMsgs.length > 40) qMsgs.shift();
      pling(); qSync();
    };
    const qNext = () => {
      if (!rows.length) return;
      const now = new Date(), time = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
      if (!qList.length) { if (!qMsgs.some(m => m.who < 0)) qMsgs.push({who: -1, text: qTimed.length ? 'Die freien Fragen sind durch, die geplanten kommen noch ♡' : 'Das waren alle Übungsfragen ♡', at: performance.now(), time, done: true}); qSync(); return; }
      const q = qList.shift();
      qMsgs.push({who: q.who >= 0 && q.who < rows.length ? q.who : Math.floor(Math.random() * rows.length), text: q.text, at: performance.now(), time, done: false});
      if (qMsgs.length > 40) qMsgs.shift();
      pling(); qSync();
    };
    const qLoop = () => {
      clearTimeout(qTimer);
      const t = +qm.value; if (!t || PR.hidden) return;
      qTimer = setTimeout(() => {
        const burst = $('ubqburst').checked ? 1 + Math.floor(Math.random() * 3) : 1;
        for (let i = 0; i < burst; i++) setTimeout(qNext, i * (2500 + Math.random() * 4000));
        qLoop();
      }, t * 1000 * (0.7 + Math.random() * 0.6));
    };
    $('ubnext').onclick = qNext;
    const askAdd = later => {
      const t = $('ubtxt').value.trim(); if (!t) return;
      const q = {who: +$('ubwho').value, text: t, at: -1};
      if (later) { qList.push(q); $('ubtxt').placeholder = 'Gemerkt, kommt später ♡'; } else qPush(q);
      // auch in die gespeicherte Liste übernehmen, damit sie beim nächsten Üben wieder da ist
      const name = q.who >= 0 && rows[q.who] ? rows[q.who].name.trim() + ': ' : '';
      ta2.value = ta2.value.replace(/\s*$/, '') + '\n' + name + t; ta2.oninput();
      $('ubtxt').value = ''; $('ubtxt').focus();
    };
    $('ubask').onsubmit = e => { e.preventDefault(); askAdd(false); };
    $('ublater').onclick = () => askAdd(true);
    $('ubdone').onclick = () => { const m = qMsgs.find(x => !x.done && x.who >= 0); if (m) { m.done = true; m.doneAt = performance.now(); } qSync(); };
    const wrap = (t, maxW) => { const out = []; let line = ''; t.split(' ').forEach(w => { const tryL = line ? line + ' ' + w : w; if (g.measureText(tryL).width > maxW && line) { out.push(line); line = w; } else line = tryL; }); if (line) out.push(line); return out; };

    const PR = $('practice'), cv = $('ubcv'), g = cv.getContext('2d');
    const cam = document.createElement('video'); cam.muted = true; cam.playsInline = true;
    let uStream = null, uRaf = 0, uRec = null, uChunks = [], uT0 = 0;
    const rr = (x, y, w, h, r) => { g.beginPath(); g.moveTo(x + r, y); g.arcTo(x + w, y, x + w, y + h, r); g.arcTo(x + w, y + h, x, y + h, r); g.arcTo(x, y + h, x, y, r); g.arcTo(x, y, x + w, y, r); g.closePath(); };
    const cover = (src, sw, sh, x, y, w, h, mirror) => {
      const s = Math.max(w / sw, h / sh), dw = sw * s, dh = sh * s;
      g.save(); rr(x, y, w, h, 14); g.clip();
      if (mirror) { g.translate(x + w, 0); g.scale(-1, 1); g.drawImage(src, (w - dw) / 2, y + (h - dh) / 2, dw, dh); }
      else g.drawImage(src, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
      g.restore();
    };
    const label = (t, x, y, h, small) => {
      g.font = '500 ' + (small ? 16 : 20) + 'px Outfit, system-ui, sans-serif';
      const w = g.measureText(t).width + 20, lh = small ? 26 : 32;
      g.fillStyle = 'rgba(31,28,22,.62)'; rr(x + 8, y + h - lh - 8, w, lh, 9); g.fill();
      g.fillStyle = '#F6F3EA'; g.fillText(t, x + 18, y + h - (small ? 15 : 18));
    };
    const drawChat = (x0, W, H) => {
      const pw = W - x0, now = performance.now();
      g.fillStyle = '#EFE9DC'; g.fillRect(x0, 0, pw, H);
      g.fillStyle = 'rgba(94,107,53,.07)';
      for (let yy = 70; yy < H; yy += 34) for (let xx = x0 + ((yy / 34) % 2 ? 10 : 27); xx < W; xx += 34) { g.beginPath(); g.arc(xx, yy, 2.2, 0, Math.PI * 2); g.fill(); }
      g.fillStyle = '#3F4A26'; g.fillRect(x0, 0, pw, 64);
      g.fillStyle = '#DCE0D2'; g.beginPath(); g.arc(x0 + 34, 32, 18, 0, Math.PI * 2); g.fill();
      g.fillStyle = '#3F4A26'; g.font = '500 20px Outfit, system-ui, sans-serif'; g.textAlign = 'center'; g.fillText('♡', x0 + 34, 39); g.textAlign = 'start';
      g.fillStyle = '#F6F3EA'; g.font = '500 20px Outfit, system-ui, sans-serif'; g.fillText('Wild Hearts Chat', x0 + 62, 28);
      const oc = openCount();
      g.fillStyle = '#C9D0B8'; g.font = '400 15px Outfit, system-ui, sans-serif'; g.fillText(oc ? oc + (oc === 1 ? ' Frage offen' : ' Fragen offen') : (rows.length + 1) + ' im Kreis', x0 + 62, 50);
      g.save(); g.beginPath(); g.rect(x0, 64, pw, H - 64); g.clip();
      let yb = H - 14;
      for (let i = qMsgs.length - 1; i >= 0 && yb > 64; i--) {
        const m = qMsgs[i], age = now - m.at, k = Math.min(1, age / 260), ease = 1 - Math.pow(1 - k, 3);
        if (m.who < 0) {
          g.font = '400 16px Outfit, system-ui, sans-serif';
          const tw2 = g.measureText(m.text).width + 28, bx = x0 + (pw - tw2) / 2, bh = 32; yb -= bh;
          g.fillStyle = '#E1EAC9'; rr(bx, yb, tw2, bh, 10); g.fill(); g.fillStyle = '#3F4A26'; g.fillText(m.text, bx + 14, yb + 21); yb -= 10; continue;
        }
        const name = rows[m.who] ? rows[m.who].name.trim() || 'Gast' : 'Gast', nc = NAMECOL[m.who % NAMECOL.length];
        g.font = '400 20px Outfit, system-ui, sans-serif';
        const maxW = pw - 72, ls = wrap(m.text, maxW - 28);
        g.font = '500 16px Outfit, system-ui, sans-serif'; const nw = g.measureText(name).width;
        g.font = '400 20px Outfit, system-ui, sans-serif';
        const bw = Math.min(maxW, Math.max(nw + 28, ...ls.map(l => g.measureText(l).width + 28), 130));
        const bh = 12 + 20 + ls.length * 27 + 24, bx = x0 + 22;
        yb -= bh;
        g.save(); g.globalAlpha = ease; g.translate(bx, yb + bh); g.scale(0.85 + 0.15 * ease, 0.85 + 0.15 * ease); g.translate(-bx, -(yb + bh)) ;
        g.shadowColor = 'rgba(62,55,44,.14)'; g.shadowBlur = 6; g.shadowOffsetY = 2;
        g.fillStyle = m.done ? '#F4F6EE' : '#FFFFFF'; rr(bx, yb, bw, bh, 14); g.fill();
        g.beginPath(); g.moveTo(bx + 2, yb); g.lineTo(bx - 10, yb); g.lineTo(bx + 2, yb + 14); g.closePath(); g.fill();
        g.shadowColor = 'transparent';
        g.fillStyle = nc; g.font = '500 16px Outfit, system-ui, sans-serif'; g.fillText(name, bx + 14, yb + 26);
        g.fillStyle = '#2F2B22'; g.font = '400 20px Outfit, system-ui, sans-serif';
        ls.forEach((l, j) => g.fillText(l, bx + 14, yb + 53 + j * 27));
        g.fillStyle = '#9A9282'; g.font = '400 13px Outfit, system-ui, sans-serif';
        const tt = m.done ? m.time + '  ✓✓' : m.time; const tw3 = g.measureText(tt).width;
        if (m.done) { g.fillText(m.time, bx + bw - tw3 - 12, yb + bh - 9); g.fillStyle = '#6E9A3A'; g.fillText('✓✓', bx + bw - 26, yb + bh - 9); }
        else g.fillText(tt, bx + bw - tw3 - 12, yb + bh - 9);
        g.restore();
        yb -= 12;
      }
      g.restore();
    };
    const nowHM = () => { const d = new Date(); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); };
    const drawTop = (W, TOP, n) => {
      g.fillStyle = '#3E372C'; g.fillRect(0, 0, W, TOP);
      g.fillStyle = '#D8DCC6'; g.font = '400 24px Hand, "Bradley Hand", cursive'; g.fillText('welcome, wild heart ♡', 28, 47);
      g.font = '500 26px Outfit, system-ui, sans-serif'; g.fillStyle = '#F6F3EA'; g.textAlign = 'center';
      g.fillText(callTitle(), W / 2, 36);
      g.font = '400 17px Outfit, system-ui, sans-serif'; g.fillStyle = '#B3A78F';
      g.fillText(new Date().toLocaleDateString('de-AT', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'}) + ' · ' + nowHM() + ' · moveciamovement.com', W / 2, 60);
      g.textAlign = 'end';
      let xr = W - 28;
      // kleiner, unaufdringlicher Hinweis, dass es eine Übung ist
      g.font = '500 15px Outfit, system-ui, sans-serif'; const tag = 'Übung', tw2 = g.measureText(tag).width + 22;
      g.fillStyle = 'rgba(220,224,210,.16)'; rr(xr - tw2, 24, tw2, 28, 14); g.fill(); g.fillStyle = '#B3A78F'; g.fillText(tag, xr - 11, 43); xr -= tw2 + 16;
      g.fillStyle = '#DCE0D2'; g.font = '500 18px Outfit, system-ui, sans-serif'; g.fillText(n + ' im Raum', xr, 44); xr -= g.measureText(n + ' im Raum').width + 12;
      g.beginPath(); g.arc(xr - 8, 37, 7, 0, Math.PI * 2); g.fill(); g.beginPath(); g.arc(xr - 8, 54, 12, Math.PI, 0); g.fill();
      if (uRec) { const s2 = Math.floor((Date.now() - uT0) / 1000); xr -= 40; g.fillStyle = '#C0533F'; g.beginPath(); g.arc(xr - 6, 38, 7, 0, Math.PI * 2); g.fill(); g.fillStyle = '#F6F3EA'; g.textAlign = 'end'; g.fillText(Math.floor(s2 / 60) + ':' + String(s2 % 60).padStart(2, '0'), xr - 20, 44); }
      g.textAlign = 'start';
    };
    const icon = (kind, cx, cy, col) => {
      g.strokeStyle = col; g.fillStyle = col; g.lineWidth = 3; g.lineCap = 'round'; g.lineJoin = 'round';
      if (kind === 'mic') { rr(cx - 7, cy - 16, 14, 22, 7); g.stroke(); g.beginPath(); g.arc(cx, cy - 2, 13, 0.15 * Math.PI, 0.85 * Math.PI); g.stroke(); g.beginPath(); g.moveTo(cx, cy + 11); g.lineTo(cx, cy + 17); g.stroke(); }
      if (kind === 'cam') { rr(cx - 17, cy - 11, 24, 22, 5); g.stroke(); g.beginPath(); g.moveTo(cx + 9, cy - 4); g.lineTo(cx + 18, cy - 9); g.lineTo(cx + 18, cy + 9); g.lineTo(cx + 9, cy + 4); g.closePath(); g.stroke(); }
      if (kind === 'share') { rr(cx - 17, cy - 13, 34, 22, 4); g.stroke(); g.beginPath(); g.moveTo(cx, cy + 4); g.lineTo(cx, cy - 7); g.moveTo(cx - 5, cy - 2); g.lineTo(cx, cy - 7); g.lineTo(cx + 5, cy - 2); g.moveTo(cx - 8, cy + 16); g.lineTo(cx + 8, cy + 16); g.stroke(); }
      if (kind === 'chat') { rr(cx - 16, cy - 13, 32, 22, 8); g.stroke(); g.beginPath(); g.moveTo(cx - 6, cy + 9); g.lineTo(cx - 10, cy + 16); g.lineTo(cx + 2, cy + 9); g.stroke(); }
      if (kind === 'hand') { g.font = '22px system-ui, sans-serif'; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText('✋', cx, cy + 1); g.textAlign = 'start'; g.textBaseline = 'alphabetic'; }
      if (kind === 'more') { [-10, 0, 10].forEach(d => { g.beginPath(); g.arc(cx + d, cy, 3, 0, Math.PI * 2); g.fill(); }); }
    };
    const drawBottom = (W, FH, BOT) => {
      const y = FH - BOT;
      g.fillStyle = '#3E372C'; g.fillRect(0, y, W, BOT);
      const btns = [['mic', 'Mikro'], ['cam', 'Kamera'], ['share', 'Teilen'], ['chat', 'Chat'], ['hand', 'Melden'], ['more', 'Mehr']];
      const bw = 92, total = btns.length * bw + 140, x0 = (W - total) / 2;
      btns.forEach(([k, lab], i) => {
        const cx = x0 + i * bw + bw / 2, cy = y + 38, active = k === 'chat';
        g.fillStyle = active ? '#5E6B35' : 'rgba(246,243,234,.1)'; g.beginPath(); g.arc(cx, cy, 26, 0, Math.PI * 2); g.fill();
        icon(k, cx, cy, '#F6F3EA');
        g.fillStyle = '#B3A78F'; g.font = '400 14px Outfit, system-ui, sans-serif'; g.textAlign = 'center'; g.fillText(lab, cx, y + 86); g.textAlign = 'start';
      });
      const lx = x0 + btns.length * bw + 20;
      g.fillStyle = '#6B5D4C'; rr(lx, y + 18, 120, 42, 21); g.fill();
      g.fillStyle = '#FBF1EA'; g.font = '500 17px Outfit, system-ui, sans-serif'; g.textAlign = 'center'; g.fillText('Verlassen', lx + 60, y + 45); g.textAlign = 'start';
    };
    const frame = () => {
      const n = rows.length + 1, W = cv.width, FH = cv.height, gap = 10, CW = 440, GW = W - CW, TOP = 76, BOT = 104, H = FH - TOP - BOT;
      const c = n <= 2 ? n : n <= 4 ? 2 : n <= 9 ? 3 : 4, rws = Math.ceil(n / c), small = n > 9;
      const tw = Math.min((GW - gap * (c + 1)) / c, ((H - gap * (rws + 1)) / rws) * 16 / 9), th = tw * 9 / 16;
      const open = new Set(qMsgs.filter(m => !m.done && m.who >= 0).map(m => m.who));
      g.fillStyle = '#2A1B16'; g.fillRect(0, 0, W, FH);
      drawTop(W, TOP, n); drawBottom(W, FH, BOT);
      g.save(); g.translate(0, TOP);
      for (let k = 0; k < n; k++) {
        const row = Math.floor(k / c), inRow = row === rws - 1 ? n - row * c : c;
        const x = (GW - (inRow * tw + (inRow - 1) * gap)) / 2 + (k - row * c) * (tw + gap);
        const y = (H - (rws * th + (rws - 1) * gap)) / 2 + row * (th + gap);
        if (k === 0) {
          if (cam.videoWidth) cover(cam, cam.videoWidth, cam.videoHeight, x, y, tw, th, true);
          else { g.fillStyle = '#3E372C'; rr(x, y, tw, th, 14); g.fill(); }
          label('Viv', x, y, th, small);
        } else {
          const r = rows[k - 1], im = imgs.get(k - 1);
          if (im) cover(im, im.naturalWidth, im.naturalHeight, x, y, tw, th, false);
          else { g.fillStyle = r.color; rr(x, y, tw, th, 14); g.fill(); }
          const rad = Math.min(tw, th) * 0.19;
          g.fillStyle = 'rgba(246,243,234,.9)'; g.beginPath(); g.arc(x + tw / 2, y + th / 2 - 8, rad, 0, Math.PI * 2); g.fill();
          g.fillStyle = '#3F4A26'; g.font = '500 ' + Math.round(rad * 0.9) + 'px Outfit, system-ui, sans-serif'; g.textAlign = 'center'; g.textBaseline = 'middle';
          g.fillText((r.name.trim()[0] || '?').toUpperCase(), x + tw / 2, y + th / 2 - 6); g.textAlign = 'start'; g.textBaseline = 'alphabetic';
          label(r.name.trim() || 'Gast', x, y, th, small);
          if (open.has(k - 1)) {
            const pulse = 3 + 2 * Math.sin(performance.now() / 180);
            g.strokeStyle = '#B9C77A'; g.lineWidth = pulse; rr(x + 2, y + 2, tw - 4, th - 4, 13); g.stroke();
            g.fillStyle = '#F6F3EA'; g.beginPath(); g.arc(x + tw - 24, y + 24, small ? 14 : 17, 0, Math.PI * 2); g.fill();
            g.font = (small ? 15 : 18) + 'px system-ui, sans-serif'; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText('✋', x + tw - 24, y + 25); g.textAlign = 'start'; g.textBaseline = 'alphabetic';
          }
        }
      }
      drawChat(GW, W, H);
      g.restore();
      uRaf = requestAnimationFrame(frame);
    };
    const fmtU = () => { const s = Math.floor((Date.now() - uT0) / 1000); return '■ Stopp ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
    let uTimer = 0;
    $('ubopen').onclick = async () => {
      stop();
      try { uStream = await navigator.mediaDevices.getUserMedia({video: {width: {ideal: 1280}, height: {ideal: 720}}, audio: {echoCancellation: true, noiseSuppression: true}}); }
      catch (e) { alert('Kein Zugriff auf Kamera oder Mikro. Bitte im Browser erlauben.'); return; }
      cam.srcObject = uStream; cam.play().catch(() => {});
      PR.hidden = false; document.body.style.overflow = 'hidden'; frame(); $('ubttl').textContent = callTitle();
      qPrep(); qMsgs = []; qSync(); qLoop();
      qStart = performance.now(); clearInterval(qClock);
      qClock = setInterval(() => {
        const el = performance.now() - qStart;
        while (qTimed.length && qTimed[0].at <= el) qPush(qTimed.shift());
        const s = Math.floor(el / 1000); $('ubclock').textContent = 'Übung läuft ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') + (qTimed.length ? ' · nächste geplante Frage bei ' + (qTimed[0].at / 60000).toLocaleString('de-AT', {maximumFractionDigits: 2}) + ' Min.' : '');
      }, 500);
      $('ubwho').innerHTML = '<option value="-1">zufälliges Fenster</option>' + rows.map((r, i) => '<option value="' + i + '">' + (r.name.trim() || 'Gast').replace(/[<&]/g, '') + '</option>').join('');
    };
    const closeU = () => {
      if (uRec) uRec.stop();
      cancelAnimationFrame(uRaf);
      if (uStream) uStream.getTracks().forEach(t => t.stop());
      uStream = null; PR.hidden = true; document.body.style.overflow = '';
      clearTimeout(qTimer); clearInterval(qClock); qMsgs = [];
      if (P) { window.hidePrompter(); document.body.appendChild(P); }
    };
    $('ubclose').onclick = closeU;
    $('ubprompt').onclick = () => {
      if (!P) return;
      if (P.hidden) { PR.insertBefore(P, cv); window.showPrompter(true); } else window.hidePrompter();
    };
    $('ubrec').onclick = () => {
      const b = $('ubrec');
      if (uRec) { uRec.stop(); return; }
      if (!window.MediaRecorder || !cv.captureStream) { alert('Dieser Browser kann die Übung nicht aufnehmen. Bitte nimm Chrome oder Edge am Computer.'); return; }
      const st = new MediaStream([...cv.captureStream(30).getVideoTracks(), ...uStream.getAudioTracks()]);
      const types = ['video/mp4;codecs=avc1,mp4a', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
      const mimeType = types.find(t => MediaRecorder.isTypeSupported(t)) || '';
      uRec = new MediaRecorder(st, mimeType ? {mimeType, videoBitsPerSecond: 2500000} : {});
      uChunks = [];
      uRec.ondataavailable = e => e.data.size && uChunks.push(e.data);
      uRec.onstop = () => {
        clearInterval(uTimer);
        const type = uRec.mimeType || 'video/webm', a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(uChunks, {type}));
        const d = new Date(), stamp = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + '_' + String(d.getHours()).padStart(2, '0') + '-' + String(d.getMinutes()).padStart(2, '0');
        const slug = callTitle().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/ß/g, 'ss').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60) || 'Gathering';
        a.download = 'Uebung_' + slug + '_' + stamp + '.' + (type.includes('mp4') ? 'mp4' : 'webm');
        document.body.appendChild(a); a.click(); a.remove();
        uRec = null; b.textContent = '● Übung aufnehmen'; b.classList.remove('recon');
      };
      uRec.start(5000); uT0 = Date.now(); b.classList.add('recon'); b.textContent = fmtU(); uTimer = setInterval(() => b.textContent = fmtU(), 1000);
    };
  }

  // --- Angebot einblenden (Viv) / Angebotskarte (alle) ---
  const OD = $('offd');
  let offApi = null;
  const startOffer = () => {
    if (offApi || !window.whOffer) return;
    offApi = whOffer({t: <?= json_encode($testTok) ?>, g: <?= (int)($gath['id'] ?? 0) ?>, gast: <?= $guest ? 'true' : 'false' ?>, preview: <?= $guest ? 'false' : 'true' ?>, interval: <?= $guest ? 6000 : 4000 ?>, onStatus: OD ? offStatus : null});
  };
  let offUntil = 0, offSkew = 0, offOn = false;
  function offStatus(j) {
    qStatus(j);
    const o = j.offer, st = $('offst');
    offOn = !!o; $('offhide').hidden = !o;
    $('offstripe').hidden = j.stripe === 'live';
    $('offstripe').textContent = j.stripe === 'none' ? 'Stripe ist noch nicht verbunden: Die Frauen sehen das Angebot, können aber noch nicht bezahlen.' : 'Stripe ist im Testmodus: Bezahlt wird nur mit Testkarten (4242 4242 4242 4242).';
    if (!o) { st.hidden = true; return; }
    offSkew = Date.now() - o.now * 1000; offUntil = o.until * 1000;
    const b = j.booked || {n: 0, sum: 0};
    st.hidden = false;
    st.dataset.base = '„' + o.title + '“ ist eingeblendet. ' + (+b.n ? b.n + (b.n == 1 ? ' Buchung' : ' Buchungen') + ' · € ' + (b.sum / 100).toLocaleString('de-AT') : 'Noch keine Buchung.');
    offPaint();
  }
  let qSeen = -1;
  function qStatus(j) {
    const L = $('qlist'); if (!L || !j.questions) return;
    const qs = j.questions, cur = j.question ? j.question.id : 0;
    const fresh = qs.filter(q => q.status === 'neu' && q.source !== 'viv').length;
    $('qbadge').textContent = fresh || '';
    if (qSeen >= 0 && fresh > qSeen && $('qd').hidden) { $('qbtn').animate([{transform: 'scale(1)'}, {transform: 'scale(1.12)'}, {transform: 'scale(1)'}], {duration: 500, iterations: 2}); }
    qSeen = fresh;
    const esc = t => String(t).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const ord = [...qs].sort((a, b) => (a.status === 'neu' ? 0 : 1) - (b.status === 'neu' ? 0 : 1) || a.id - b.id);
    L.innerHTML = ord.length ? ord.map(q => '<div class="q' + (+q.id === cur ? ' on' : '') + (q.status === 'beantwortet' ? ' done' : '') + '"><small>' + esc(q.label) + ' · ' + esc(q.created_at.slice(11, 16)) + (+q.id === cur ? ' · wird gerade gezeigt' : q.status === 'beantwortet' ? ' · beantwortet' : '') + '</small><p>' + esc(q.text) + '</p>'
      + (+q.id === cur ? '<button type="button" data-q="qhide" data-id="' + q.id + '">Ausblenden ✓</button>' : '<button type="button" data-q="qshow" data-id="' + q.id + '">Einblenden</button> ' + (q.status === 'neu' ? '<button type="button" class="ghost" data-q="qdone" data-id="' + q.id + '">Erledigt</button>' : '')) + '</div>').join('') : '<p class="hint">Noch keine Fragen.</p>';
    L.querySelectorAll('[data-q]').forEach(b => b.onclick = () => qCall({action: b.dataset.q, id: +b.dataset.id}));
  }
  const qCall = async body => {
    const r = await fetch('/api/wh-offer.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(Object.assign({t: <?= json_encode($testTok) ?>, g: <?= (int)($gath['id'] ?? 0) ?>}, body))});
    const j = await r.json(); if (!j.ok && j.error) alert(j.error);
    if (offApi) offApi.refresh();
  };
  if ($('qd')) {
    $('qbtn').onclick = () => { $('qd').hidden = !$('qd').hidden; };
    $('qcl').onclick = () => { $('qd').hidden = true; };
    if ($('qpre')) $('qpre').onchange = () => { $('qown').value = $('qpre').value; };
    $('qownbtn').onclick = () => { const t = $('qown').value.trim(); if (!t) return; qCall({action: 'qown', text: t}); $('qown').value = ''; if ($('qpre')) $('qpre').value = ''; };
  }
  const offPaint = () => {
    const st = $('offst'); if (!st || st.hidden) return;
    const ms = offUntil - (Date.now() - offSkew), s = Math.ceil(ms / 1000);
    st.textContent = st.dataset.base + ' ' + (ms > 0 ? 'Gathering-Preis noch ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') + '.' : 'Jetzt gilt der reguläre Preis.');
  };
  if (OD) {
    setInterval(offPaint, 1000);
    const sel = $('offsel');
    const fill = () => { const op = sel.selectedOptions[0]; if (!op) return; $('offsp').value = +op.dataset.sp ? (op.dataset.sp / 100).toString().replace('.', ',') : ''; $('offmin').value = op.dataset.min || 25; };
    const firstOk = [...sel.options].find(o => !o.disabled); if (firstOk) firstOk.selected = true;
    fill(); sel.onchange = fill;
    $('offbtn').onclick = () => { OD.hidden = !OD.hidden; };
    $('offcl').onclick = () => { OD.hidden = true; };
    const call = async body => {
      $('offerr').hidden = true;
      const r = await fetch('/api/wh-offer.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(Object.assign({t: <?= json_encode($testTok) ?>, g: <?= (int)($gath['id'] ?? 0) ?>}, body))});
      const j = await r.json();
      if (!j.ok) { $('offerr').hidden = false; $('offerr').textContent = j.error || 'Das hat nicht geklappt.'; return; }
      if (offApi) offApi.refresh();
    };
    $('offshow').onclick = () => {
      if (offOn && !confirm('Es ist schon ein Angebot eingeblendet. Durch das neue ersetzen? Der Countdown startet dann neu.')) return;
      call({action: 'show', offer: sel.value, special: $('offsp').value, minutes: +$('offmin').value || 25});
    };
    $('offhide').onclick = () => call({action: 'hide'});
  }

  const enter = $('enter');
  if (enter) {
    enter.addEventListener('click', () => {
      stop();
      $('frame').src = <?= json_encode($src) ?>;
      $('room').hidden = false;
      document.body.style.overflow = 'hidden';
      startOffer();
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

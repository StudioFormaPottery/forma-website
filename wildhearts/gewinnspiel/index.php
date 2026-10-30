<?php
// moveciamovement.com/gewinnspiel – Weihnachts-Gewinnspiel 2026 (Vivien, 30.10.2026), nur Deutsch.
// Teilnahme mit Vorname + E-Mail + Wunschgewinn, Double-Opt-in über api/gewinnspiel.php. Auslosung in der Verwaltung.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/gewinnspiel-lib.php';
header('Cache-Control: no-cache');
$pdo = forma_db();
mm_schema($pdo);
gw_schema($pdo);
$open = gw_open();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$in = isset($_GET['dabei']);
$old = isset($_GET['link']);
$end = strtotime(GW_END);
$days = max(0, (int)ceil(($end - time()) / 86400));
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Weihnachts-Gewinnspiel · Movecia</title>
<meta name="description" content="Gewinne Zeit für dich: 3 Monate The Mindful Movement Method oder 2 Online-Einheiten Prä- oder Postnatal Training mit Vivien. Mitmachen bis 20. Dezember.">
<meta name="robots" content="noindex, follow">
<link rel="canonical" href="https://moveciamovement.com/gewinnspiel/">
<meta name="theme-color" content="#F5F1EB">
<meta property="og:title" content="Weihnachts-Gewinnspiel · Movecia">
<meta property="og:description" content="Gewinne 3 Monate The Mindful Movement Method oder 2 Online-Einheiten Prä- oder Postnatal Training.">
<meta property="og:image" content="https://moveciamovement.com/img/movecia/og-start.jpg">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="stylesheet" href="/wildhearts/assets/cute.css?v=7">
<script src="/wildhearts/assets/schutz.js?v=3"></script>
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff");font-display:swap}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff");font-display:swap}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff");font-display:swap}
:root{--bg:#F5F1EB;--card:#FBF9F6;--ink:#2A2026;--muted:#6E6168;--line:#D3C9BC;--plum:#4A3040;--deep:#2E1D27;--stone:#E4DCD1;--peach:#E6DBCF;--on:#F7F1EE;--t1:#EEE4D9;--t2:#ECE3E4;--t3:#E7E1D5;--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
*{box-sizing:border-box;margin:0}
html{overflow-x:hidden;overflow-x:clip}
body{background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.55;-webkit-font-smoothing:antialiased;overflow-x:hidden;overflow-x:clip}
img{max-width:100%;display:block}
a{color:inherit}
[hidden]{display:none!important}
.page{width:min(100% - 32px,720px);margin:0 auto;padding:max(14px,env(safe-area-inset-top)) 0 2rem}
.top{display:flex;justify-content:space-between;align-items:center;padding:.6rem 0}
.top a{display:flex;align-items:center;gap:.5rem;text-decoration:none;font-family:var(--serif);font-size:1.5rem}
.top img{width:30px;height:30px}
.top small{font-size:.8rem;color:var(--muted)}
.hero{position:relative;text-align:center;padding:1.6rem 0 1rem}
.arch{position:relative;width:min(70%,280px);aspect-ratio:4/5;margin:0 auto}
.arch::after{content:"";position:absolute;inset:-9px 9px 9px -9px;border:1.5px solid var(--plum);opacity:.45;border-radius:999px 999px 26px 26px}
.arch img{position:relative;z-index:1;width:100%;height:100%;object-fit:cover;border-radius:999px 999px 26px 26px}
.bow{position:absolute;z-index:2;right:-14px;top:18%;width:64px;height:64px;border-radius:50%;background:var(--plum);color:var(--on);display:grid;place-items:center;font-size:1.7rem;box-shadow:0 12px 24px -14px rgba(46,29,39,.8)}
.kick{font-size:.72rem;letter-spacing:.22em;text-transform:uppercase;font-weight:600;color:var(--plum);margin-top:1.6rem}
h1{font-family:var(--serif);font-weight:500;font-size:clamp(2.4rem,9vw,3.4rem);line-height:1.02;margin-top:.4rem}
h1 em{color:var(--plum)}
.lead{color:var(--muted);max-width:32em;margin:.9rem auto 0}
.chips{display:flex;flex-wrap:wrap;justify-content:center;gap:.45rem;margin-top:1rem}
.chips span{font-size:.82rem;font-weight:500;border-radius:999px;padding:.35em .95em;background:var(--card);border:1px solid var(--line);color:var(--deep)}
.chips .hot{background:var(--plum);color:var(--on);border-color:var(--plum)}
h2{font-family:var(--serif);font-weight:500;font-size:2rem;line-height:1.1;text-align:center;margin:2.4rem 0 1rem}
.prizes{display:grid;gap:.8rem}
@media(min-width:640px){.prizes{grid-template-columns:1fr 1fr}}
.pz{position:relative;border-radius:26px;padding:1.2rem 1.2rem 1.3rem;overflow:hidden;isolation:isolate}
.pz::before{content:"";position:absolute;z-index:-1;width:170px;height:150px;right:-60px;top:-50px;border-radius:58% 42% 55% 45%/52% 58% 42% 48%;background:rgba(251,249,246,.6)}
.pz.a{background:var(--t1)}.pz.b{background:var(--t2)}
.pz .ph{width:100%;aspect-ratio:16/10;border-radius:18px;overflow:hidden;margin-bottom:.9rem}
.pz .ph img{width:100%;height:100%;object-fit:cover}
.pz small{font-size:.7rem;letter-spacing:.18em;text-transform:uppercase;font-weight:600;color:var(--plum)}
.pz h3{font-family:var(--serif);font-weight:600;font-size:1.55rem;line-height:1.1;margin:.25rem 0 .4rem}
.pz p{font-size:.95rem;color:var(--muted)}
.or{text-align:center;font-family:var(--serif);font-style:italic;font-size:1.3rem;color:var(--plum);margin:.2rem 0}
@media(min-width:640px){.or{display:none}}
.form{background:var(--card);border-radius:28px;padding:1.4rem 1.2rem;box-shadow:0 22px 40px -32px rgba(46,29,39,.6)}
.form form{display:grid;gap:.8rem}
.form label.l{font-size:.85rem;font-weight:600;color:var(--deep)}
.form input[type=text],.form input[type=email]{font:inherit;font-size:1rem;padding:.85rem 1rem;border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--ink);width:100%}
.form input:focus{outline:2px solid var(--plum);outline-offset:1px}
.pick{display:grid;gap:.45rem}
.pick label{display:flex;gap:.7rem;align-items:center;padding:.75rem .9rem;border:1px solid var(--line);border-radius:16px;background:#fff;cursor:pointer;font-size:.98rem}
.pick input{accent-color:var(--plum);width:18px;height:18px;flex:0 0 18px}
.pick label:has(input:checked){border-color:var(--plum);background:#F6EFF1}
.check{display:flex;gap:.6rem;align-items:flex-start;font-size:.88rem;color:var(--muted)}
.check input{margin-top:.25rem;accent-color:var(--plum);flex:0 0 auto}
.form button{font:600 1.05rem var(--sans);background:var(--plum);color:var(--on);border:0;border-radius:999px;padding:1rem 1.2rem;cursor:pointer}
.form button:disabled{opacity:.6}
.hp{position:absolute;left:-9999px}
.msg{background:var(--stone);border-radius:14px;padding:.85rem 1rem;font-size:.97rem}
.done{text-align:center;padding:1.6rem 1.2rem}
.done .big{font-family:var(--serif);font-size:2rem;line-height:1.1;margin:.3rem 0 .6rem}
.steps{display:grid;gap:.6rem;margin-top:.4rem}
@media(min-width:640px){.steps{grid-template-columns:repeat(3,1fr)}}
.st{background:var(--card);border-radius:22px;padding:1rem 1.1rem}
.st b{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:var(--plum);color:var(--on);font-weight:600;margin-bottom:.5rem}
.st p{font-size:.95rem}
details.rules{margin-top:2rem;background:var(--card);border-radius:22px;padding:1rem 1.2rem}
details.rules summary{cursor:pointer;font-weight:600;color:var(--deep)}
details.rules ul{margin:.8rem 0 0;padding-left:1.1rem;font-size:.92rem;color:var(--muted);display:grid;gap:.4rem}
.foot{text-align:center;font-size:.8rem;color:var(--muted);padding:1.6rem 0 2.4rem}
.foot a{color:inherit}
</style>
</head>
<body>
<main class="page">
  <div class="top"><a href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt="" width="30" height="30">Movecia</a><small>Mindful &amp; Somatic Movement</small></div>

  <section class="hero">
    <div class="arch"><img src="/img/movecia/fotos/ev-ueber-mich.jpg?v=10" alt="Vivien in ihrem Bewegungsraum" width="560" height="700"><span class="bow" aria-hidden="true">🎁</span></div>
    <p class="kick">Weihnachts-Gewinnspiel</p>
    <h1>Gewinne <em>Zeit für dich</em></h1>
    <p class="lead">Der Dezember ist für so viele von uns der vollste Monat des Jahres. Darum verlose ich genau das, was dabei am meisten fehlt: ein paar Wochen, in denen du wieder bei dir ankommst. Du suchst dir aus, was du gerade brauchst.</p>
    <div class="chips"><?php if ($open): ?><span class="hot">noch <?= $days ?> <?= $days === 1 ? 'Tag' : 'Tage' ?></span><?php endif; ?><span>bis 20. Dezember</span><span>Auslosung 21. Dezember</span><span>kostenlos</span></div>
  </section>

  <h2>Das kannst du gewinnen</h2>
  <div class="prizes">
    <div class="pz a">
      <div class="ph"><img src="/img/movecia/fotos/ev-method-foto.jpg?v=10" alt="" width="640" height="400" loading="lazy"></div>
      <small>Online · für zu Hause</small>
      <h3>3 Monate The Mindful Movement Method</h3>
      <p>Meine Videobibliothek: sanfte Flows für volle Tage, kurze Einheiten für müde Tage, Atem und Meditation, Monat für Monat in deinem Tempo.</p>
    </div>
    <p class="or">oder</p>
    <div class="pz b">
      <div class="ph"><img src="/img/movecia/fotos/ev-natal.jpg?v=10" alt="" width="640" height="400" loading="lazy"></div>
      <small>Online · 1:1 per Video</small>
      <h3>2 Einheiten Prä- oder Postnatal Training</h3>
      <p>Zwei Stunden nur für dich und deinen Körper, in der Schwangerschaft oder nach der Geburt, live mit mir, egal wo du wohnst.</p>
    </div>
  </div>

  <h2 id="mitmachen">So bist du dabei</h2>
  <?php if ($in): ?>
  <div class="form done"><p style="font-size:2.2rem">🎁</p><p class="big">Du bist im Lostopf!</p><p>Danke, dass du mitmachst. Am 21. Dezember lose ich aus, und wenn du gewinnst, schreibe ich dir per E-Mail. Erzähl gern einer Freundin davon, die sich das genauso verdient hat.</p><p style="margin-top:1rem"><a href="/bio/">Mehr von Movecia ansehen →</a></p></div>
  <?php elseif (!$open): ?>
  <div class="form done"><p class="big">Das Gewinnspiel ist vorbei</p><p>Danke an alle, die mitgemacht haben! Die Gewinnerin habe ich am 21. Dezember ausgelost und per E-Mail benachrichtigt.</p><p style="margin-top:1rem"><a href="/bio/">Mehr von Movecia ansehen →</a></p></div>
  <?php else: ?>
  <div class="steps">
    <div class="st"><b>1</b><p>Such dir deinen Wunschgewinn aus und trag Vornamen und E-Mail ein.</p></div>
    <div class="st"><b>2</b><p>Bestätige deine Teilnahme mit einem Klick in der Mail, die ich dir schicke.</p></div>
    <div class="st"><b>3</b><p>Am 21. Dezember lose ich aus und schreibe der Gewinnerin per E-Mail.</p></div>
  </div>
  <div class="form" style="margin-top:1rem">
    <?php if ($old): ?><p class="msg" style="margin-bottom:.8rem">Dieser Link funktioniert leider nicht mehr. Trag dich einfach noch einmal ein.</p><?php endif; ?>
    <form id="gf" novalidate>
      <span class="l" style="font-size:.85rem;font-weight:600">Was wünschst du dir?</span>
      <div class="pick" role="radiogroup">
        <?php foreach (GW_PRIZES as $k => $p): ?><label><input type="radio" name="prize" value="<?= $h($k) ?>"><span><?= $h($p[0]) ?></span></label><?php endforeach; ?>
      </div>
      <input type="text" name="name" placeholder="Vorname" autocomplete="given-name" required maxlength="80" aria-label="Vorname">
      <input type="email" name="email" placeholder="E-Mail" autocomplete="email" required maxlength="160" aria-label="E-Mail">
      <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <label class="check"><input type="checkbox" name="terms" required><span>Ich bin mindestens 18 Jahre alt und einverstanden mit den <a href="#regeln">Teilnahmebedingungen</a>. Meine Angaben werden nur für das Gewinnspiel verwendet (<a href="/terms/#datenschutz">Datenschutz</a>).</span></label>
      <label class="check"><input type="checkbox" name="news"><span>Freiwillig: Ich möchte auch Viviens Post mit Impulsen, Übungen und Angeboten bekommen. Abmelden geht jederzeit.</span></label>
      <button type="submit">Ich mache mit 🎁</button>
      <p class="msg" id="gm" role="status" aria-live="polite" hidden></p>
    </form>
  </div>
  <?php endif; ?>

  <details class="rules" id="regeln"<?= isset($_GET['regeln']) ? ' open' : '' ?>>
    <summary>Teilnahmebedingungen</summary>
    <ul>
      <li>Veranstalterin ist Vivien Malonga, Movecia (Kontakt und Impressum unter <a href="/terms/#impressum">moveciamovement.com/terms</a>).</li>
      <li>Teilnahme kostenlos und ohne Kauf, ab 18 Jahren, nur einmal pro Person und E-Mail-Adresse. Mitmachen kannst du bis 20. Dezember 2026 um 23:59 Uhr; deine Teilnahme zählt, sobald du sie per E-Mail bestätigt hast.</li>
      <li>Gewinn nach Wahl der Gewinnerin: 3 Monate Zugang zu The Mindful Movement Method (ohne automatische Verlängerung) oder 2 Online-Einheiten Prä- oder Postnatal Training 1:1 per Video (einzulösen bis 30. April 2027, nach Absprache mit Ärztin oder Hebamme). Der Gewinn kann nicht in bar ausgezahlt oder getauscht werden.</li>
      <li>Die Gewinnerin wird am 21. Dezember 2026 per Zufall aus allen bestätigten Teilnahmen gezogen und per E-Mail benachrichtigt; ihr Vorname kann in der Instagram-Story von @moveciamovement genannt werden. Meldet sie sich nicht innerhalb von 7 Tagen, lose ich neu aus. Ich frage nie nach Bankdaten oder Passwörtern.</li>
      <li>Deine Angaben (Vorname, E-Mail, Wunschgewinn) werden nur für das Gewinnspiel verwendet und spätestens am 31. Jänner 2027 gelöscht, außer du hast dich zusätzlich freiwillig für die Post angemeldet.</li>
      <li>Dieses Gewinnspiel steht in keiner Verbindung zu Instagram oder Meta und wird von Instagram weder gesponsert noch unterstützt oder organisiert.</li>
    </ul>
  </details>
</main>
<footer class="foot">Movecia · Vivien Malonga · <a href="/terms/#impressum">Impressum</a> · <a href="/terms/#datenschutz">Datenschutz</a></footer>
<?php if ($open && !$in): ?>
<script>
(function(){
  var f=document.getElementById('gf'),m=document.getElementById('gm'),t0=Date.now();
  f.addEventListener('submit',async function(e){e.preventDefault();
    var p=f.querySelector('input[name=prize]:checked');
    if(!p){m.textContent='Bitte such dir zuerst deinen Wunschgewinn aus.';m.hidden=false;return}
    if(!f.name.value.trim()||f.email.value.indexOf('@')<0){m.textContent='Bitte Vornamen und E-Mail eintragen.';m.hidden=false;return}
    if(!f.terms.checked){m.textContent='Bitte bestätige die Teilnahmebedingungen.';m.hidden=false;return}
    var b=f.querySelector('button');b.disabled=true;
    try{var r=await fetch('/api/gewinnspiel.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({prize:p.value,name:f.name.value.trim(),email:f.email.value.trim(),terms:true,news:f.news.checked,website:f.website.value,elapsed:Date.now()-t0})});var x=await r.json();
      if(x.ok){f.querySelectorAll('input,label,button,.pick,.l').forEach(function(n){n.hidden=true});
        m.textContent=x.already?'Du bist schon im Lostopf! Deinen Wunschgewinn habe ich aktualisiert.':'Fast geschafft! Bitte bestätige deine Teilnahme mit dem Knopf in der E-Mail, die gerade zu dir unterwegs ist. Schau zur Sicherheit auch im Spam-Ordner nach.';}
      else{m.textContent=x.error||'Das hat gerade nicht geklappt.';b.disabled=false}}
    catch(err){m.textContent='Das hat gerade nicht geklappt. Bitte versuch es noch einmal.';b.disabled=false}
    m.hidden=false;});
})();
</script>
<?php endif; ?>
</body>
</html>

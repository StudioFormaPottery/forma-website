<?php
// moveciamovement.com/bio – Link in Bio für das neue Instagram-Konto @moveciamovement „Mindful & Somatic Movement“ (Vivien, 30.10.2026).
// Nur Deutsch, Fokus auf Studio in Mathon und Website. Preise und Termine live aus den Einstellungen,
// damit hier nie ein alter Preis steht. Der ältere zweisprachige Link in Bio (/links) bleibt für @movementwithviv.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/method-lib.php';
require_once dirname(__DIR__, 2) . '/api/natal-lib.php';
require_once dirname(__DIR__, 2) . '/api/academy-lib.php';
require_once dirname(__DIR__) . '/assets/waves.inc.php';
require_once dirname(__DIR__) . '/assets/raum.inc.php';
header('Cache-Control: no-cache');
$pdo = forma_db();
mv_schema($pdo);
$c = mv_cfg();
$ac = ac_cfg();
$eu = fn(int $x) => '€ ' . number_format($x / 100, 0, ',', '.');
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$natalMin = PHP_INT_MAX;
foreach (natal_packages_for('studio') as $p) $natalMin = min($natalMin, (int)round($p['cents'] / max(1, $p['units'])));
$uSt = mv_ts($c['u_start']);
$uFree = mv_unfold_free($pdo);
$mLaunch = mv_ts($c['launch']);
?><!doctype html>
<html lang="de" data-l="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Movecia · Alle Links</title>
<meta name="description" content="Movecia: Mindful & Somatic Movement mit Vivien Malonga, online und im Studio in Mathon. Prä- & Postnatal Training, 1:1, Online-Programme und die Ausbildung.">
<meta name="robots" content="noindex, follow">
<link rel="canonical" href="https://moveciamovement.com/bio/">
<meta name="theme-color" content="#F5F1EB">
<meta property="og:title" content="Movecia · Mindful & Somatic Movement">
<meta property="og:description" content="Bewegung beginnt bei dir. Im Studio in Mathon und online, mit Vivien.">
<meta property="og:image" content="https://moveciamovement.com/img/movecia/og-start.jpg">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="manifest" href="/app.webmanifest"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Movecia"><script src="/wildhearts/assets/app.js?v=2" defer></script>
<link rel="stylesheet" href="/wildhearts/assets/cute.css?v=7">
<script src="/wildhearts/assets/schutz.js?v=3"></script>
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff");font-display:swap}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff");font-display:swap}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff");font-display:swap}
:root{--bg:#F5F1EB;--card:#FBF9F6;--ink:#2A2026;--muted:#6E6168;--line:#D3C9BC;--plum:#4A3040;--deep:#2E1D27;--stone:#E4DCD1;--peach:#E6DBCF;--on:#F7F1EE;--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,-apple-system,sans-serif}
*{box-sizing:border-box;margin:0}
html{overflow-x:hidden;overflow-x:clip}
body{background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.55;-webkit-font-smoothing:antialiased;overflow-x:hidden;overflow-x:clip}
img{max-width:100%;display:block}
a{color:inherit}
[hidden]{display:none!important}
.page{width:min(100% - 32px,600px);margin:0 auto;padding:max(18px,env(safe-area-inset-top)) 0 2rem}
.head{position:relative;text-align:center;padding:2.4rem 0 1.4rem}
.logo{position:relative;width:132px;height:132px;margin:0 auto;border-radius:50%;overflow:hidden;background:var(--plum);box-shadow:0 18px 34px -22px rgba(46,29,39,.7)}
.logo img{width:100%;height:100%;object-fit:cover}
.k{font-size:.72rem;letter-spacing:.2em;text-transform:uppercase;color:var(--plum);font-weight:600}
.head .k{margin-top:1.4rem}
.head h1{font-family:var(--serif);font-weight:500;font-size:clamp(2.6rem,11vw,3.4rem);line-height:1;margin-top:.35rem}
.head .sub{font-family:var(--serif);font-style:italic;font-size:1.4rem;color:var(--plum);margin-top:.25rem}
.head .lead{color:var(--muted);max-width:28em;margin:.8rem auto 0;font-size:1rem}
.sec-t{font-family:var(--serif);font-weight:500;font-size:1.65rem;line-height:1.1;margin:2rem 0 .7rem;display:flex;align-items:center;gap:.6rem}
.sec-t::after{content:"";flex:1;height:1px;background:var(--line)}
.links{display:grid;gap:.7rem}
.btn{display:flex;align-items:center;gap:.9rem;text-decoration:none;color:var(--ink);background:var(--card);border:1px solid var(--line);border-radius:22px;padding:.6rem 1.1rem .6rem .6rem;min-height:78px;transition:transform .35s ease,box-shadow .35s ease,border-color .35s;font:inherit;text-align:left;width:100%;cursor:pointer}
.btn:hover,.btn:focus-visible{transform:translateY(-2px);box-shadow:0 16px 30px -22px rgba(74,58,65,.55);border-color:var(--peach)}
.btn:focus-visible{outline:2px solid var(--plum);outline-offset:3px}
.btn .th{flex:0 0 60px;height:60px;border-radius:16px;overflow:hidden;background:var(--peach)}
.btn .th img{width:100%;height:100%;object-fit:cover}
.btn .tx{flex:1;min-width:0;display:flex;flex-direction:column;line-height:1.25}
.btn .tx b{font-family:var(--serif);font-weight:600;font-size:1.32rem;line-height:1.1}
.btn .tx small{color:var(--muted);font-size:.86rem;margin-top:.22rem}
.btn .ar{color:var(--plum);font-size:1.1rem;flex:0 0 auto}
.btn .tag{align-self:flex-start;font-size:.64rem;letter-spacing:.14em;text-transform:uppercase;font-weight:700;border-radius:999px;padding:.2em .7em;margin-bottom:.35rem;background:var(--stone);color:var(--plum)}
.btn.main{background:var(--plum);border-color:var(--plum);color:var(--on)}
.btn.main .tx small{color:rgba(247,241,238,.82)}
.btn.main .ar{color:var(--on)}
.btn.main .tag{background:var(--on)}
.btn.gift{background:var(--stone);border-color:var(--stone)}
.btn.gift .th{flex-basis:50px;height:70px;border-radius:6px;border:1px solid var(--line);transform:rotate(-4deg);margin-left:.3rem;background:#fff}
.btn.gift em{font-family:var(--serif);font-size:1.02rem;color:var(--plum)}
.studio{margin-top:.2rem}
.studio .raum{margin-top:0}
.studio p{color:var(--muted);font-size:.95rem;margin-top:.7rem}
.join{margin-top:.7rem;background:var(--card);border:1px solid var(--line);border-radius:22px;padding:1.2rem 1.2rem 1.3rem}
.join form{display:grid;gap:.7rem}
.join input[type=text],.join input[type=email]{font:inherit;font-size:1rem;padding:.8rem 1rem;border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--ink);width:100%}
.join input:focus{outline:2px solid var(--plum);outline-offset:1px}
.consent{display:flex;gap:.6rem;align-items:flex-start;font-size:.86rem;color:var(--muted)}
.consent input{margin-top:.25rem;accent-color:var(--plum)}
.join button{font:600 1rem var(--sans);background:var(--plum);color:var(--on);border:0;border-radius:999px;padding:.9rem 1.2rem;cursor:pointer}
.hp{position:absolute;left:-9999px}
.msg{background:var(--stone);border-radius:14px;padding:.8rem 1rem;font-size:.95rem}
.affirm{margin-top:2.2rem;background:var(--plum);color:var(--on);border-radius:28px;padding:2rem 1.4rem;text-align:center;position:relative;overflow:hidden}
.affirm .k{color:rgba(247,241,238,.7)}
.affirm .big{font-family:var(--serif);font-style:italic;font-size:clamp(1.9rem,8vw,2.4rem);line-height:1.1;margin-top:.5rem}
.affirm img{position:absolute;opacity:.16;width:120px;right:-24px;bottom:-24px}
.small-links{display:flex;flex-wrap:wrap;justify-content:center;gap:.5rem;margin-top:1.6rem}
.small-links a{display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;font-size:.9rem;color:var(--deep);border:1px solid var(--line);border-radius:999px;padding:.45rem .95rem}
.small-links a:hover{border-color:var(--plum)}
.foot{text-align:center;color:var(--muted);font-size:.84rem;padding:1.8rem 16px 2.6rem}
.foot a{color:var(--plum)}
@media(prefers-reduced-motion:reduce){.btn{transition:none}}
</style>
</head>
<body>
<main class="page">
  <header class="head has-hw">
    <?= mv_waves(false, 'calm bio') ?>
    <div class="logo"><img src="/img/movecia/insta-profil-320.png?v=1" alt="Movecia" width="132" height="132"></div>
    <p class="k">Online · im Studio in Mathon</p>
    <h1>Movecia</h1>
    <p class="sub">Mindful &amp; Somatic Movement</p>
    <p class="lead">Ich bin Vivien. Online und in meinem kleinen Studio in Mathon begleite ich Frauen zurück in ihren Körper, mit achtsamer Bewegung, sanfter Kraft und Atem.</p>
  </header>

  <h2 class="sec-t">Im Studio</h2>
  <nav class="links" aria-label="Im Studio">
    <a class="btn main" href="/natal/">
      <span class="th"><img src="/img/movecia/fotos/ev-natal.jpg?v=10" alt="" width="120" height="120"></span>
      <span class="tx"><span class="tag">Studio &amp; online</span><b>Prä- &amp; Postnatal Training</b><small>1:1 durch die Schwangerschaft und danach · im Paket ab <?= $eu($natalMin) ?> pro Einheit · Kennenlernen gratis</small></span>
      <span class="ar" aria-hidden="true">→</span>
    </a>
    <a class="btn" href="/#anfrage">
      <span class="th"><img src="/img/movecia/fotos/ev-platz-quadrat.jpg?v=10" alt="" width="120" height="120"></span>
      <span class="tx"><b>1:1 Personal Training</b><small>Im Studio in Mathon oder draußen in der Natur · Anfrage schicken</small></span>
      <span class="ar" aria-hidden="true">→</span>
    </a>
  </nav>

  <section class="studio" aria-label="Das Studio">
    <h2 class="sec-t">So sieht mein Studio aus</h2>
    <?= mv_raum_gallery(false, 'raum-bio') ?>
    <p>Ein heller Raum mit Sprossenwand, Matten und viel Platz zum Bewegen. Kleine Runde, ganz in Ruhe, nur für dich.</p>
  </section>

  <h2 class="sec-t">Online</h2>
  <nav class="links" aria-label="Online">
    <a class="btn" href="/unfold/">
      <span class="th"><img src="/img/movecia/fotos/ev-unfold-karte.jpg?v=10" alt="" width="120" height="120"></span>
      <span class="tx"><span class="tag"><?= $uSt > time() ? 'Start ' . $h(mv_date($uSt)) : '3 Monate' ?></span><b>Unfold · 3 Monate Begleitung</b><small>Wöchentlicher Live-Call, Bewegung und Workbooks<?= $c['eb_u'] ? ' · Early Bird ' . $eu($c['u_once']) : '' ?><?= $uFree > 0 ? ' · nur ' . (int)$c['u_seats'] . ' Plätze' : ' · Warteliste' ?></small></span>
      <span class="ar" aria-hidden="true">→</span>
    </a>
    <a class="btn" href="/method/">
      <span class="th"><img src="/img/movecia/fotos/ev-method-foto.jpg?v=3" alt="" width="120" height="120"></span>
      <span class="tx"><span class="tag"><?= $mLaunch > time() ? 'Ab ' . $h(mv_date($mLaunch)) : 'On demand' ?></span><b>The Mindful Movement Method</b><small>Deine Videobibliothek für zu Hause<?= $c['eb_m'] ? ' · Early Bird ' . $eu($c['m1']) . ' im Monat' : ' · ab ' . $eu((int)round($c['m12'] / 12)) . ' im Monat' ?> · auch auf Englisch</small></span>
      <span class="ar" aria-hidden="true">→</span>
    </a>
    <a class="btn" href="/academy/teacher-training/">
      <span class="th"><img src="/img/movecia/fotos/ev-lunge.jpg?v=1" alt="" width="120" height="120"></span>
      <span class="tx"><span class="tag">Ausbildung</span><b>The Mindful Becoming Teacher Training</b><small>Start <?= $h(mv_date($ac['start_ts'])) ?> · 550 Stunden inkl. 200-Std.-Yogalehrerausbildung<?= $ac['eb'] ? ' · Early Bird bis ' . $h(mv_date($ac['eb_end'])) : '' ?></small></span>
      <span class="ar" aria-hidden="true">→</span>
    </a>
  </nav>

  <h2 class="sec-t">Für dich</h2>
  <div class="links">
    <button class="btn gift" type="button" id="giftBtn" aria-expanded="false" aria-controls="join">
      <span class="th"><img src="/img/wildhearts/reset-cover-de.jpg?v=4" alt="" width="100" height="140"></span>
      <span class="tx"><em>mein Geschenk für dich</em><b>Reset-Routine für müde Tage</b><small>Kostenlos als PDF in dein Postfach</small></span>
      <span class="ar" aria-hidden="true">↓</span>
    </button>
  </div>
  <section class="join" id="join" hidden>
    <form id="jf" novalidate>
      <input type="text" name="name" placeholder="Vorname" autocomplete="given-name" required maxlength="80" aria-label="Vorname">
      <input type="email" name="email" placeholder="E-Mail" autocomplete="email" required maxlength="160" aria-label="E-Mail">
      <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <label class="consent"><input type="checkbox" name="consent" required><span>Ja, Vivien darf mir schreiben. Abmelden geht jederzeit. Mehr im <a href="/terms/#datenschutz">Datenschutz</a>.</span></label>
      <button type="submit">Schick sie mir</button>
      <p class="msg" id="jm" role="status" aria-live="polite" hidden></p>
    </form>
  </section>

  <section class="affirm" aria-label="Für heute">
    <img src="/img/movecia/movecia-zeichen-hell.svg" alt="" width="120" height="120">
    <p class="k">für heute</p>
    <p class="big">Bewegung beginnt bei dir.</p>
  </section>

  <div class="small-links">
    <a href="/">moveciamovement.com</a>
    <a href="/mitglieder/">Login</a>
    <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a>
  </div>
</main>
<footer class="foot">Movecia · Vivien Malonga · <a href="/terms/#impressum">Impressum</a> · <a href="/terms/#datenschutz">Datenschutz</a></footer>
<script>
(function(){
  var b=document.getElementById('giftBtn'),j=document.getElementById('join'),f=document.getElementById('jf'),m=document.getElementById('jm'),t0=Date.now();
  b.addEventListener('click',function(){j.hidden=!j.hidden;b.setAttribute('aria-expanded',String(!j.hidden));if(!j.hidden)f.name.focus();});
  f.addEventListener('submit',async function(e){e.preventDefault();
    if(!f.name.value.trim()||f.email.value.indexOf('@')<0||!f.consent.checked){m.textContent='Bitte Vorname, E-Mail und das Häkchen ausfüllen.';m.hidden=false;return}
    var btn=f.querySelector('button');btn.disabled=true;
    try{var r=await fetch('/api/wh-subscribe.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:f.name.value.trim(),email:f.email.value.trim(),consent:true,website:f.website.value,source:'reset',lang:'de',elapsed:Date.now()-t0})});var x=await r.json();
      if(x.ok){f.querySelectorAll('input,label,button').forEach(function(n){n.hidden=true});m.textContent='Fast geschafft! Bitte bestätige deine E-Mail-Adresse in deinem Postfach, dann kommt die Reset-Routine zu dir.';}
      else{m.textContent=x.error||'Das hat gerade nicht geklappt.';btn.disabled=false}}
    catch(err){m.textContent='Das hat gerade nicht geklappt. Bitte versuch es noch einmal.';btn.disabled=false}
    m.hidden=false;});
})();
</script>
</body>
</html>

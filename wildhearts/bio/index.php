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
require_once dirname(__DIR__) . '/assets/icons.inc.php';
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
$MON = ['', 'Jän', 'Feb', 'März', 'Apr', 'Mai', 'Juni', 'Juli', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
$next = [];
if ($mLaunch > time()) $next[] = [$mLaunch, 'The Method', '/method/'];
if ($uSt > time()) $next[] = [$uSt, 'Unfold', '/unfold/'];
if ((int)$ac['start_ts'] > time()) $next[] = [(int)$ac['start_ts'], 'Ausbildung', '/academy/teacher-training/'];
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
:root{--bg:#F5F1EB;--card:#FBF9F6;--ink:#2A2026;--muted:#6E6168;--line:#D3C9BC;--plum:#4A3040;--deep:#2E1D27;--stone:#E4DCD1;--peach:#E6DBCF;--on:#F7F1EE;--t1:#EEE4D9;--t2:#ECE3E4;--t3:#E7E1D5;--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,-apple-system,sans-serif}
*{box-sizing:border-box;margin:0}
html{overflow-x:hidden;overflow-x:clip}
body{background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.55;-webkit-font-smoothing:antialiased;overflow-x:hidden;overflow-x:clip}
img{max-width:100%;display:block}
a{color:inherit}
[hidden]{display:none!important}
.page{width:min(100% - 28px,600px);margin:0 auto;padding:max(14px,env(safe-area-inset-top)) 0 1.5rem}
/* Kopf */
.head{position:relative;text-align:center;padding:2.2rem 0 1rem}
.ava{position:relative;width:150px;height:150px;margin:0 auto}
.ava::before{content:"";position:absolute;inset:-10px -16px -6px -8px;background:var(--peach);border-radius:58% 42% 55% 45%/52% 58% 42% 48%;animation:breathe 9s ease-in-out infinite}
.ava::after{content:"";position:absolute;inset:-4px -2px -12px -14px;border:1.5px solid var(--plum);opacity:.55;border-radius:45% 55% 48% 52%/55% 45% 55% 45%;transform:rotate(-8deg)}
.ava img{position:relative;z-index:1;width:150px;height:150px;border-radius:50%;object-fit:cover;box-shadow:0 18px 30px -22px rgba(46,29,39,.75)}
.ava .dot{position:absolute;z-index:2;width:12px;height:12px;border-radius:50%;background:var(--plum);right:-6px;top:38%}
@keyframes breathe{50%{transform:scale(1.04) rotate(3deg)}}
.head h1{font-family:var(--serif);font-weight:500;font-size:clamp(2.7rem,11vw,3.4rem);line-height:1;margin-top:1.5rem}
.head .sub{font-family:var(--serif);font-style:italic;font-size:1.4rem;color:var(--plum);margin-top:.2rem}
.chips{display:flex;flex-wrap:wrap;justify-content:center;gap:.4rem;margin-top:.9rem}
.chips span{font-size:.78rem;font-weight:500;border-radius:999px;padding:.3em .85em;background:var(--card);border:1px solid var(--line);color:var(--deep)}
.head .lead{color:var(--muted);max-width:28em;margin:.9rem auto 0;font-size:.98rem}
/* Als Nächstes */
.next{display:flex;gap:.55rem;margin:1.2rem 0 0;overflow-x:auto;scrollbar-width:none;padding:.2rem .1rem .4rem}
.next::-webkit-scrollbar{display:none}
.nx{flex:1 0 108px;display:flex;flex-direction:column;align-items:center;text-decoration:none;background:var(--card);border:1px solid var(--line);border-radius:20px;padding:.65rem .5rem .7rem;text-align:center}
.nx .d{font-family:var(--serif);font-size:1.55rem;font-weight:500;line-height:1;color:var(--plum)}
.nx .m{font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;font-weight:600;color:var(--muted);margin-top:.2rem}
.nx .w{font-size:.8rem;margin-top:.35rem;line-height:1.2}
.nx-t{font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;font-weight:600;color:var(--plum);text-align:center;margin-top:1.6rem}
/* Trenner */
.sep{display:flex;justify-content:center;align-items:center;gap:.6rem;margin:1.4rem 0;color:var(--plum);opacity:.55}
.sep svg{width:70px;height:12px}
.sep i{width:7px;height:7px;border-radius:50%;background:currentColor}
/* Bereiche */
.blk{position:relative;border-radius:30px;padding:1.3rem 1rem 1rem;overflow:hidden;isolation:isolate}
.blk::before{content:"";position:absolute;z-index:-1;width:200px;height:170px;right:-70px;top:-60px;border-radius:58% 42% 55% 45%/52% 58% 42% 48%;background:rgba(251,249,246,.55)}
.blk.t1{background:var(--t1)}.blk.t2{background:var(--t2)}.blk.t3{background:var(--t3)}
.bh{display:flex;align-items:center;gap:.75rem;margin:0 .2rem 1rem}
.bh .ib{flex:0 0 50px;height:50px;border-radius:50%;background:var(--card);color:var(--plum);display:grid;place-items:center;box-shadow:0 8px 18px -14px rgba(46,29,39,.6)}
.bh .ib svg{width:30px;height:30px}
.bh h2{font-family:var(--serif);font-weight:500;font-size:1.7rem;line-height:1.05}
.bh p{font-size:.86rem;color:var(--muted);line-height:1.3;margin-top:.1rem}
.links{display:grid;gap:.6rem}
.btn{display:flex;align-items:center;gap:.85rem;text-decoration:none;color:var(--ink);background:var(--card);border:0;border-radius:22px;padding:.6rem .8rem .6rem .6rem;min-height:84px;transition:transform .35s ease,box-shadow .35s ease;font:inherit;text-align:left;width:100%;cursor:pointer;box-shadow:0 10px 22px -20px rgba(46,29,39,.55)}
.btn:hover,.btn:focus-visible{transform:translateY(-2px);box-shadow:0 18px 30px -20px rgba(74,58,65,.55)}
.btn:focus-visible{outline:2px solid var(--plum);outline-offset:3px}
.btn .th{flex:0 0 58px;height:72px;border-radius:999px 999px 14px 14px;overflow:hidden;background:var(--peach)}
.btn .th img{width:100%;height:100%;object-fit:cover}
.btn .tx{flex:1;min-width:0;display:flex;flex-direction:column;line-height:1.25}
.btn .tx b{font-family:var(--serif);font-weight:600;font-size:1.3rem;line-height:1.1}
.btn .tx small{color:var(--muted);font-size:.84rem;margin-top:.22rem}
.btn .ar{flex:0 0 34px;height:34px;border-radius:50%;border:1px solid var(--line);display:grid;place-items:center;color:var(--plum);font-size:.95rem;transition:background .3s,color .3s}
.btn:hover .ar{background:var(--plum);color:var(--on);border-color:var(--plum)}
.btn .tag{align-self:flex-start;font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;font-weight:700;border-radius:999px;padding:.22em .75em;margin-bottom:.35rem;background:var(--t2);color:var(--plum)}
.blk.t2 .btn .tag{background:var(--t1)}
.btn.main{background:var(--plum);color:var(--on)}
.btn.main .tx small{color:rgba(247,241,238,.82)}
.btn.main .ar{border-color:rgba(247,241,238,.4);color:var(--on)}
.btn.main .tag{background:var(--on)}
.btn.gift .th{flex-basis:52px;height:72px;border-radius:6px;border:1px solid var(--line);transform:rotate(-4deg);margin-left:.3rem;background:#fff}
.btn.gift em{font-family:var(--serif);font-size:1.02rem;color:var(--plum)}
.studio{margin-top:1rem;background:var(--card);border-radius:22px;padding:.55rem .55rem .9rem}
.studio .raum{margin-top:0;border-radius:18px}
.studio p{color:var(--muted);font-size:.9rem;margin:.6rem .4rem 0}
.join{margin-top:.6rem;background:var(--card);border-radius:22px;padding:1.1rem 1.1rem 1.2rem}
.join form{display:grid;gap:.7rem}
.join input[type=text],.join input[type=email]{font:inherit;font-size:1rem;padding:.8rem 1rem;border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--ink);width:100%}
.join input:focus{outline:2px solid var(--plum);outline-offset:1px}
.consent{display:flex;gap:.6rem;align-items:flex-start;font-size:.86rem;color:var(--muted)}
.consent input{margin-top:.25rem;accent-color:var(--plum)}
.join button{font:600 1rem var(--sans);background:var(--plum);color:var(--on);border:0;border-radius:999px;padding:.9rem 1.2rem;cursor:pointer}
.hp{position:absolute;left:-9999px}
.msg{background:var(--stone);border-radius:14px;padding:.8rem 1rem;font-size:.95rem}
.affirm{margin-top:.6rem;background:var(--plum);color:var(--on);border-radius:26px;padding:1.8rem 1.4rem;text-align:center;position:relative;overflow:hidden}
.affirm .k{font-size:.7rem;letter-spacing:.2em;text-transform:uppercase;font-weight:600;color:rgba(247,241,238,.7)}
.affirm .big{font-family:var(--serif);font-style:italic;font-size:clamp(1.8rem,8vw,2.3rem);line-height:1.1;margin-top:.45rem}
.affirm img{position:absolute;opacity:.16;width:120px;right:-24px;bottom:-24px}
.affirm .sp{position:absolute;color:var(--peach);opacity:.7;font-size:.9rem;animation:tw 4s ease-in-out infinite}
.affirm .sp.a{left:14%;top:18%}.affirm .sp.b{right:20%;top:12%;animation-delay:1.3s;font-size:.7rem}.affirm .sp.c{left:24%;bottom:14%;animation-delay:2.4s;font-size:.65rem}
@keyframes tw{50%{opacity:.15;transform:scale(.7)}}
.small-links{display:flex;flex-wrap:wrap;justify-content:center;gap:.5rem;margin-top:1.6rem}
.small-links a{display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;font-size:.88rem;color:var(--deep);background:var(--card);border:1px solid var(--line);border-radius:999px;padding:.45rem .95rem}
.small-links a:hover{border-color:var(--plum)}
.foot{text-align:center;color:var(--muted);font-size:.84rem;padding:1.6rem 16px 2.6rem}
.foot a{color:var(--plum)}
@media(min-width:640px){.blk{padding:1.6rem 1.4rem 1.3rem}}
@media(prefers-reduced-motion:reduce){.btn,.btn .ar{transition:none}.ava::before,.affirm .sp{animation:none}}
</style>
</head>
<body>
<main class="page">
  <header class="head has-hw">
    <?= mv_waves(false, 'calm bio') ?>
    <div class="ava"><img src="/img/movecia/insta-profil-320.png?v=1" alt="Movecia" width="150" height="150"><span class="dot" aria-hidden="true"></span></div>
    <h1>Movecia</h1>
    <p class="sub">Mindful &amp; Somatic Movement</p>
    <div class="chips"><span>Online</span><span>Studio in Mathon</span><span>mit Vivien</span></div>
    <p class="lead">Online und in meinem kleinen Studio in Mathon begleite ich Frauen zurück in ihren Körper, mit achtsamer Bewegung, sanfter Kraft und Atem.</p>
  </header>

<?php if (time() <= strtotime('2026-12-20 23:59:59')): // Weihnachts-Gewinnspiel (Vivien, 30.10.2026) ?>
  <nav class="links" style="margin-top:1.2rem" aria-label="Gewinnspiel">
    <a class="btn main" href="/gewinnspiel/">
      <span class="th"><img src="/img/movecia/fotos/ev-ueber-mich.jpg?v=10" alt="" width="120" height="150"></span>
      <span class="tx"><span class="tag">🎁 Gewinnspiel</span><b>Gewinne Zeit für dich</b><small>3 Monate The Method oder 2 Online-Einheiten Prä-/Postnatal · bis 20. Dezember</small></span>
      <span class="ar" aria-hidden="true">→</span>
    </a>
  </nav>
<?php endif ?>
<?php if ($next): ?>
  <p class="nx-t">Als Nächstes</p>
  <div class="next">
<?php foreach ($next as [$ts, $w, $u]): ?>
    <a class="nx" href="<?= $u ?>"><span class="d"><?= date('j', $ts) ?></span><span class="m"><?= $MON[(int)date('n', $ts)] ?> <?= date('Y', $ts) ?></span><span class="w"><?= $h($w) ?></span></a>
<?php endforeach ?>
  </div>
<?php endif ?>

  <div class="sep" aria-hidden="true"><svg viewBox="0 0 70 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M1 6c5.5-5 11-5 17 0s11.5 5 17 0 11.5-5 17 0 11.5 5 17 0"/></svg><i></i><svg viewBox="0 0 70 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M1 6c5.5-5 11-5 17 0s11.5 5 17 0 11.5-5 17 0 11.5 5 17 0"/></svg></div>

  <section class="blk t1" aria-labelledby="h-studio">
    <div class="bh"><span class="ib"><?= mv_icon_svg('herz') ?></span><div><h2 id="h-studio">Im Studio</h2><p>1:1 in Mathon, ganz in Ruhe</p></div></div>
    <nav class="links" aria-label="Im Studio">
      <a class="btn main" href="/natal/">
        <span class="th"><img src="/img/movecia/fotos/ev-natal.jpg?v=10" alt="" width="120" height="150"></span>
        <span class="tx"><span class="tag">Studio &amp; online</span><b>Prä- &amp; Postnatal Training</b><small>Im Paket ab <?= $eu($natalMin) ?> pro Einheit · Kennenlernen gratis</small></span>
        <span class="ar" aria-hidden="true">→</span>
      </a>
      <a class="btn" href="/#anfrage">
        <span class="th"><img src="/img/movecia/fotos/ev-platz-quadrat.jpg?v=10" alt="" width="120" height="150"></span>
        <span class="tx"><b>1:1 Personal Training</b><small>Im Studio oder draußen in der Natur · Anfrage schicken</small></span>
        <span class="ar" aria-hidden="true">→</span>
      </a>
    </nav>
    <div class="studio">
      <?= mv_raum_gallery(false, 'raum-bio') ?>
      <p>So sieht mein Studio aus: hell, mit Sprossenwand, Matten und viel Platz zum Bewegen.</p>
    </div>
  </section>

  <div class="sep" aria-hidden="true"><svg viewBox="0 0 70 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M1 6c5.5-5 11-5 17 0s11.5 5 17 0 11.5-5 17 0 11.5 5 17 0"/></svg><i></i><svg viewBox="0 0 70 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M1 6c5.5-5 11-5 17 0s11.5 5 17 0 11.5-5 17 0 11.5 5 17 0"/></svg></div>

  <section class="blk t2" aria-labelledby="h-online">
    <div class="bh"><span class="ib"><?= mv_icon_svg('welle') ?></span><div><h2 id="h-online">Online</h2><p>Von zu Hause aus, wann es für dich passt</p></div></div>
    <nav class="links" aria-label="Online">
      <a class="btn" href="/unfold/">
        <span class="th"><img src="/img/movecia/fotos/ev-unfold-karte.jpg?v=10" alt="" width="120" height="150"></span>
        <span class="tx"><span class="tag"><?= $uSt > time() ? 'Start ' . $h(mv_date($uSt)) : '3 Monate' ?></span><b>Unfold · 3 Monate Begleitung</b><small>Live-Call jede Woche, Bewegung und Workbooks<?= $c['eb_u'] ? ' · Early Bird ' . $eu($c['u_once']) : '' ?><?= $uFree > 0 ? ' · nur ' . (int)$c['u_seats'] . ' Plätze' : ' · Warteliste' ?></small></span>
        <span class="ar" aria-hidden="true">→</span>
      </a>
      <a class="btn" href="/method/">
        <span class="th"><img src="/img/movecia/fotos/ev-method-foto.jpg?v=3" alt="" width="120" height="150"></span>
        <span class="tx"><span class="tag"><?= $mLaunch > time() ? 'Ab ' . $h(mv_date($mLaunch)) : 'On demand' ?></span><b>The Movecia Daily Method</b><small>Jeden Tag ein paar Minuten Bewegung, on demand<?= $c['eb_m'] ? ' · Early Bird ' . $eu($c['m1']) . ' im Monat' : ' · ab ' . $eu((int)round($c['m12'] / 12)) . ' im Monat' ?> · auch auf Englisch</small></span>
        <span class="ar" aria-hidden="true">→</span>
      </a>
    </nav>
  </section>

  <div class="sep" aria-hidden="true"><svg viewBox="0 0 70 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M1 6c5.5-5 11-5 17 0s11.5 5 17 0 11.5-5 17 0 11.5 5 17 0"/></svg><i></i><svg viewBox="0 0 70 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M1 6c5.5-5 11-5 17 0s11.5 5 17 0 11.5-5 17 0 11.5 5 17 0"/></svg></div>

  <section class="blk t3" aria-labelledby="h-aus">
    <div class="bh"><span class="ib"><?= mv_icon_svg('knospe') ?></span><div><h2 id="h-aus">Ausbildung</h2><p>Für alle, die selbst unterrichten möchten</p></div></div>
    <nav class="links" aria-label="Ausbildung">
      <a class="btn" href="/academy/teacher-training/">
        <span class="th"><img src="/img/movecia/fotos/ev-lunge.jpg?v=1" alt="" width="120" height="150"></span>
        <span class="tx"><span class="tag">Start <?= $h(mv_date($ac['start_ts'])) ?></span><b>The Mindful Becoming Teacher Training</b><small>550 Stunden inkl. 200-Std.-Yogalehrerausbildung<?= $ac['eb'] ? ' · Early Bird bis ' . $h(mv_date($ac['eb_end'])) : '' ?></small></span>
        <span class="ar" aria-hidden="true">→</span>
      </a>
    </nav>
  </section>

  <div class="sep" aria-hidden="true"><svg viewBox="0 0 70 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M1 6c5.5-5 11-5 17 0s11.5 5 17 0 11.5-5 17 0 11.5 5 17 0"/></svg><i></i><svg viewBox="0 0 70 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M1 6c5.5-5 11-5 17 0s11.5 5 17 0 11.5-5 17 0 11.5 5 17 0"/></svg></div>

  <section class="blk t1" aria-labelledby="h-dich">
    <div class="bh"><span class="ib"><?= mv_icon_svg('bluete') ?></span><div><h2 id="h-dich">Für dich</h2><p>Ein kleines Geschenk von mir</p></div></div>
    <div class="links">
      <button class="btn gift" type="button" id="giftBtn" aria-expanded="false" aria-controls="join">
        <span class="th"><img src="/img/wildhearts/reset-cover-de.jpg?v=4" alt="" width="100" height="140"></span>
        <span class="tx"><em>gratis als PDF</em><b>Reset-Routine für müde Tage</b><small>Kommt direkt in dein Postfach</small></span>
        <span class="ar" aria-hidden="true">↓</span>
      </button>
    </div>
    <div class="join" id="join" hidden>
      <form id="jf" novalidate>
        <input type="text" name="name" placeholder="Vorname" autocomplete="given-name" required maxlength="80" aria-label="Vorname">
        <input type="email" name="email" placeholder="E-Mail" autocomplete="email" required maxlength="160" aria-label="E-Mail">
        <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <label class="consent"><input type="checkbox" name="consent" required><span>Ja, Vivien darf mir schreiben. Abmelden geht jederzeit. Mehr im <a href="/terms/#datenschutz">Datenschutz</a>.</span></label>
        <button type="submit">Schick sie mir</button>
        <p class="msg" id="jm" role="status" aria-live="polite" hidden></p>
      </form>
    </div>
    <div class="affirm" aria-label="Für heute">
      <img src="/img/movecia/movecia-zeichen-hell.svg" alt="" width="120" height="120">
      <span class="sp a" aria-hidden="true">✦</span><span class="sp b" aria-hidden="true">✦</span><span class="sp c" aria-hidden="true">✦</span>
      <p class="k">für heute</p>
      <p class="big">Bewegung beginnt bei dir.</p>
    </div>
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

<?php
// moveciamovement.com/gutscheine – Movecia-Gutscheine verschenken (Vivien, 31.10.2026)
// VORSCHAU: Seite und Auswahl stehen, Bezahlen ist noch nicht angeschlossen (erst nach Viviens Okay).
// Angebote mit festem Preis (Unfold, Method, Personal Training online/Studio, Prä- & Postnatal) oder eigener Betrag.
// Alle Preise kommen vom Server aus den jeweiligen Angeboten.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/ptb-lib.php';
require_once dirname(__DIR__, 2) . '/api/method-lib.php';
header('Cache-Control: no-store');
$h = 'forma_h';
$eur = fn(int $c) => '€ ' . ($c % 100 ? number_format($c / 100, 2, ',', '.') : number_format($c / 100, 0, ',', '.'));
$mp = mv_plans();
$np = natal_packages();
$pt = ptb_price_table(1);
$ptOn = (int)$np['o_einzel']['unit_cents']; // Personal Training online = Preis wie 1:1 Prä-/Postnatal online (Vivien, 31.10.2026)

// Angebote: key => [Titel, Untertitel, Bild, Varianten [key => [Label, Cent]]]
$offers = [
    'unfold' => ['Unfold', 'The Mindful Becoming · 3 Monate Begleitung mit Live-Calls, Bewegung und Workbooks', 'ev-unfold-karte.jpg',
        ['u1' => ['3 Monate', (int)$mp['u1']['cents']]]],
    'method' => ['The Method', 'Mindful Movement on demand · Flows für jeden Tag, wann immer sie Zeit hat', 'ev-method-karte.jpg',
        ['m1' => ['1 Monat', (int)$mp['m1']['cents']], 'm3' => ['3 Monate', (int)$mp['m3']['cents']], 'm12' => ['1 Jahr', (int)$mp['m12']['cents']]]],
    'pt_online' => ['Personal Training online', '1:1 live per Video, ganz auf sie abgestimmt, von zu Hause aus', 'ev-gathering.jpg',
        array_combine(['o1', 'o2', 'o3', 'o4'], array_map(fn($n) => [$n === 1 ? '1 Einheit' : $n . ' Einheiten', $ptOn * $n], [1, 2, 3, 4]))],
    'pt_studio' => ['Personal Training im Studio', '1:1 in meinem kleinen Studio in Mathon, ' . PTB_MINUTES . ' Minuten nur für sie', 'pt-baum.jpg',
        array_combine(['s1', 's2', 's3', 's4'], array_map(fn($n) => [$n === 1 ? '1 Einheit' : $n . ' Einheiten', (int)$pt[$n]['total']], [1, 2, 3, 4]))],
    'natal' => ['Prä- & Postnatal', '1:1 durch Schwangerschaft und Rückbildung, im Studio oder online', 'ev-post-baby.jpg',
        ['einzel' => ['Einzelstunde', (int)$np['einzel']['unit_cents']], 'prae' => ['Pränatal · 6 Einheiten', (int)$np['prae']['unit_cents'] * 6],
         'post' => ['Postnatal · 8 Einheiten', (int)$np['post']['unit_cents'] * 8], 'kombi' => ['Kombi · 14 Einheiten', (int)$np['kombi']['unit_cents'] * 14]]],
];
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Gutscheine · Schenke Me-Time · Movecia</title>
<meta name="description" content="Schenke Me-Time: Gutscheine für Personal Training online oder im Studio in Mathon, Unfold, The Method und Prä- & Postnatal Training bei Movecia. Sofort als PDF zum Ausdrucken.">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#F5F1EB">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--bg:#F5F1EB;--card:#FBF9F6;--ink:#2A2026;--muted:#6E6168;--line:#D3C9BC;--plum:#4A3040;--deep:#2E1D27;--blush:#E4DCD1;--peach:#E6DBCF;--rose:#B58C99;--on:#F7F1EE;--r:26px;--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
*{box-sizing:border-box}[hidden]{display:none!important}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
@media(min-width:900px){body{font-size:19px}}
.w{padding-left:max(16px,env(safe-area-inset-left));padding-right:max(16px,env(safe-area-inset-right))}
@media(min-width:900px){.w{padding-left:56px;padding-right:56px}}
.in{max-width:1500px;margin:0 auto}
h1,h2,h3{font-family:var(--serif);font-weight:500;line-height:1.05;margin:0}
h1{font-size:clamp(2.8rem,7.5vw,5.8rem)}h2{font-size:clamp(2rem,4.4vw,3.2rem)}h3{font-size:1.6rem}
em{font-style:italic;color:var(--plum)}p{margin:0}a{color:var(--plum)}
.k{font-size:.72rem;letter-spacing:.2em;text-transform:uppercase;color:var(--plum);font-weight:500}
.mut{color:var(--muted)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5em;padding:.9em 1.6em;border-radius:999px;background:var(--plum);color:var(--on);font:inherit;font-size:.95rem;font-weight:500;text-decoration:none;border:1px solid var(--plum);cursor:pointer}
.top{display:flex;justify-content:space-between;align-items:center;padding-top:14px;padding-bottom:14px}
.brand{font-family:var(--serif);font-size:1.7rem;color:var(--ink);text-decoration:none;display:flex;align-items:center;gap:.4rem}.brand img{width:30px;height:30px}
/* Kopf */
.hero{display:grid;gap:1.6rem;align-items:center;padding-top:clamp(.6rem,3vw,2.4rem);padding-bottom:clamp(2rem,5vw,4rem)}
@media(min-width:900px){.hero{grid-template-columns:1.05fr .95fr;gap:4rem}}
.hero p.lead{font-size:clamp(1.05rem,1.6vw,1.25rem);color:var(--muted);max-width:32em;margin-top:1.1rem}
.hero .ctas{display:flex;flex-wrap:wrap;gap:.8rem;align-items:center;margin-top:1.6rem}
.hero .ctas span{font-size:.9rem;color:var(--muted)}
.arch{position:relative;justify-self:center;width:min(100%,520px)}
.arch .ph{aspect-ratio:4/5;border-radius:999px 999px var(--r) var(--r);overflow:hidden;background:var(--blush)}
.arch img{width:100%;height:100%;object-fit:cover;display:block;-webkit-touch-callout:none;user-select:none;pointer-events:none}
.arch .blob{position:absolute;border-radius:62% 38% 46% 54%/55% 45% 55% 45%;z-index:-1}
.arch .b1{width:46%;aspect-ratio:1;left:-12%;bottom:-6%;background:var(--peach)}.arch .b2{width:28%;aspect-ratio:1;right:-6%;top:8%;background:var(--blush)}
.arch .tag{position:absolute;left:-4%;bottom:12%;background:var(--card);border-radius:18px;padding:.7rem 1rem;box-shadow:0 20px 40px -26px var(--deep);font-size:.85rem;line-height:1.35}
.arch .tag b{display:block;font-family:var(--serif);font-size:1.35rem;font-weight:500;color:var(--plum)}
@media(max-width:899px){.arch{width:min(86%,420px)}.arch .tag{left:-6%}}
/* Auswahl */
.sec{padding-top:clamp(1rem,3vw,2rem)}
.steps{display:grid;gap:18px}
@media(min-width:1100px){.steps{grid-template-columns:1fr 380px;align-items:start;gap:28px}}
.step{background:var(--card);border-radius:var(--r);padding:clamp(1.2rem,3vw,2rem)}
.step+.step{margin-top:18px}
.step h3 .n{font-family:var(--sans);font-size:.8rem;font-weight:600;display:inline-grid;place-items:center;width:1.8rem;height:1.8rem;border-radius:50%;background:var(--plum);color:var(--on);margin-right:.6rem;vertical-align:.35em}
.offers{display:grid;gap:12px;margin-top:1.2rem}
@media(min-width:700px){.offers{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1400px){.offers{grid-template-columns:repeat(3,1fr)}}
.of{position:relative;display:flex;flex-direction:column;border-radius:20px;background:var(--bg);overflow:hidden;cursor:pointer;border:1.5px solid transparent;transition:border-color .2s,box-shadow .2s}
.of:hover{box-shadow:0 18px 36px -30px var(--deep)}
.of.on{border-color:var(--plum);box-shadow:0 0 0 1.5px var(--plum)}
.of .im{aspect-ratio:16/10;overflow:hidden}.of .im img{width:100%;height:100%;object-fit:cover;display:block;-webkit-touch-callout:none;user-select:none;pointer-events:none}
.of .tx{padding:.9rem 1rem 1rem;display:flex;flex-direction:column;gap:.3rem;flex:1}
.of h4{font-family:var(--serif);font-weight:500;font-size:1.55rem;line-height:1.1;margin:0}
.of .d{font-size:.88rem;color:var(--muted);line-height:1.45}
.of .from{font-size:.85rem;color:var(--plum);margin-top:auto;padding-top:.4rem}
@media(max-width:699px){.of{flex-direction:row}.of .im{aspect-ratio:auto;width:36%;flex:0 0 36%}.of .tx{padding:.8rem .9rem;padding-right:2.6rem}.of h4{font-size:1.35rem}.of .d{font-size:.82rem}}
.of .ck{position:absolute;right:12px;top:12px;width:28px;height:28px;border-radius:50%;background:var(--card);box-shadow:inset 0 0 0 1.5px var(--line)}
.of.on .ck{background:var(--plum);box-shadow:none}.of.on .ck::after{content:"";position:absolute;left:10px;top:6px;width:6px;height:12px;border:solid var(--on);border-width:0 2px 2px 0;transform:rotate(45deg)}
.vars{display:flex;flex-wrap:wrap;gap:8px;margin-top:1.1rem}
.vars button{font:inherit;font-size:.92rem;border:1px solid var(--line);background:var(--bg);border-radius:999px;padding:.55em 1.1em;cursor:pointer;color:var(--ink)}
.vars button b{font-weight:600;margin-left:.35em;color:var(--plum)}
.vars button[aria-pressed=true]{background:var(--plum);color:var(--on);border-color:var(--plum)}.vars button[aria-pressed=true] b{color:var(--peach)}
.own{display:flex;align-items:center;gap:.6rem;margin-top:1rem;flex-wrap:wrap;font-size:.95rem;color:var(--muted)}
.own input{font:inherit;font-size:16px;width:7.5em;padding:.6em .9em;border-radius:14px;border:1px solid var(--line);background:var(--bg);color:var(--ink)}
.dz{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:1.2rem;max-width:720px}
.dz label{cursor:pointer;display:flex;flex-direction:column;gap:.35rem;text-align:center;font-size:.9rem;color:var(--muted)}
.dz input{position:absolute;opacity:0}.dz img{width:100%;aspect-ratio:297/210;object-fit:cover;border-radius:14px;border:2px solid var(--line);-webkit-touch-callout:none;user-select:none}
.dz label:has(input:checked) img{border-color:var(--plum);box-shadow:0 0 0 2px var(--plum)}
.f{display:flex;flex-direction:column;gap:.35rem;font-size:.86rem;color:var(--muted)}
.grid2{display:grid;gap:12px;margin-top:1.1rem}@media(min-width:700px){.grid2{grid-template-columns:1fr 1fr}}
.f input,.f textarea{font:inherit;font-size:16px;color:var(--ink);background:var(--bg);border:1px solid transparent;border-radius:14px;padding:.8em 1em;width:100%}
.f textarea{min-height:90px;resize:vertical}
input:focus,textarea:focus{outline:2px solid var(--plum);outline-offset:1px}
/* Zusammenfassung */
.sum{background:var(--deep);color:var(--on);border-radius:var(--r);padding:clamp(1.4rem,3vw,2rem);position:relative;overflow:hidden}
@media(min-width:1100px){.sum{position:sticky;top:24px}}
.sum .k{color:var(--peach)}
.sum .t{font-family:var(--serif);font-size:1.8rem;line-height:1.1;margin-top:.5rem}
.sum .amt{font-family:var(--serif);font-size:clamp(3rem,6vw,4.4rem);line-height:1;margin:.6rem 0 1rem;font-variant-numeric:lining-nums}
.sum .ln{display:flex;justify-content:space-between;gap:1rem;font-size:.9rem;border-top:1px solid rgba(251,245,247,.16);padding:.65rem 0}
.sum .ln span:first-child{color:rgba(251,245,247,.7)}
.sum .btn{width:100%;background:var(--on);color:var(--deep);border-color:var(--on);margin-top:.8rem}
.sum .small{font-size:.78rem;color:rgba(251,245,247,.7);line-height:1.5;margin-top:.9rem}
.sum .bl{position:absolute;width:200px;height:190px;right:-80px;bottom:-90px;border-radius:62% 38% 46% 54%/55% 45% 55% 45%;background:rgba(181,140,153,.28)}
.sum>*{position:relative}
.how{display:grid;gap:12px;margin-top:1.4rem}@media(min-width:800px){.how{grid-template-columns:repeat(3,1fr)}}
.how div{background:var(--card);border-radius:20px;padding:1.2rem 1.3rem}
.how b{font-family:var(--serif);font-weight:500;font-size:2.6rem;line-height:1;color:var(--rose);display:block}
.how h4{font-family:var(--serif);font-weight:500;font-size:1.4rem;margin:.4rem 0 .2rem}
.how p{font-size:.92rem;color:var(--muted)}
/* Handy: Schritte zum Aufklappen, damit es keine lange Wurst wird */
.sh{display:flex;align-items:center;gap:.6rem;width:100%;background:none;border:0;padding:0;font:inherit;color:inherit;text-align:left;cursor:default}
.sh h3{margin:0;flex:1}.sh .cv{display:none}.sh .pk{display:none}
.nx{display:none}
@media(max-width:899px){
  .step{padding:1rem 1.1rem}.step+.step{margin-top:10px}
  .sh{cursor:pointer}.sh h3{font-size:1.45rem}
  .sh .pk{display:block;font-size:.82rem;color:var(--muted);margin-top:.15rem;font-family:var(--sans)}
  .sh .cv{display:block;width:12px;height:12px;border:solid var(--plum);border-width:0 2px 2px 0;transform:rotate(45deg);margin:0 .4rem .3rem 0;transition:transform .25s;flex:0 0 auto}
  .step.open .sh .cv{transform:rotate(225deg);margin-bottom:-.2rem}
  .step:not(.open) .sb{display:none}
  .step.open .sh .pk{display:none}
  .nx{display:flex;width:100%;margin-top:1.1rem}
  .how{grid-template-columns:1fr 1fr 1fr;gap:8px}.how div{padding:.8rem .7rem}.how b{font-size:1.8rem}.how h4{font-size:1.1rem}.how p{display:none}
  .sum .small{display:none}.sum .ln:nth-of-type(3){display:none}
}
.preview{background:var(--plum);color:var(--on);text-align:center;font-size:.85rem;padding:.5rem}
footer{padding-top:3rem;padding-bottom:2rem;font-size:.85rem;color:var(--muted);text-align:center}
</style>
<script src="/wildhearts/assets/schutz.js?v=3"></script>
</head>
<body>
<p class="preview">Vorschau · Bezahlen ist noch nicht angeschlossen</p>
<header class="w"><div class="in top">
  <a class="brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia</span></a>
  <?php require_once dirname(__DIR__) . '/assets/menu.inc.php'; mv_menu('gutscheine', ['lang' => false]); ?>
</div></header>

<main>
<section class="w"><div class="in hero">
  <div>
    <p class="k">Gutscheine · Schenke Me-Time</p>
    <h1 style="margin-top:.7rem">Schenk ihr <em>Zeit</em> für sich</h1>
    <p class="lead">Eine Stunde, in der sich alles nur um sie dreht. Ob 1:1 online vom Sofa aus, in meinem kleinen Studio in Mathon, drei Monate Begleitung mit Unfold oder Bewegung für jeden Tag mit The Method: Du suchst aus, ich kümmere mich um den Rest.</p>
    <div class="ctas"><a class="btn" href="#auswahl">Gutschein aussuchen</a><span>Sofort als PDF zum Ausdrucken</span></div>
  </div>
  <div class="arch"><span class="blob b1"></span><span class="blob b2"></span>
    <div class="ph"><img src="/img/movecia/fotos/ev-gathering.jpg" alt="Kaffee in der Hand, auf dem Laptop läuft ein Online-Training mit Vivien" draggable="false"></div>
    <p class="tag"><b>Online oder in Mathon</b>1:1 mit Vivien</p>
  </div>
</div></section>

<section class="w sec" id="auswahl"><div class="in steps">
  <div>
    <div class="step open">
      <button type="button" class="sh"><span style="flex:1"><h3><span class="n">1</span>Was möchtest du schenken?</h3><span class="pk" id="pk1"></span></span><span class="cv"></span></button>
      <div class="sb">
      <div class="offers" role="radiogroup">
        <?php foreach ($offers as $ok => [$t, $d, $img, $vars]): $min = min(array_column($vars, 1)); ?>
        <div class="of<?= $ok === 'pt_online' ? ' on' : '' ?>" role="radio" tabindex="0" aria-checked="<?= $ok === 'pt_online' ? 'true' : 'false' ?>" data-o="<?= $h($ok) ?>" data-vars="<?= $h(json_encode($vars, JSON_UNESCAPED_UNICODE)) ?>" data-t="<?= $h($t) ?>">
          <span class="ck" aria-hidden="true"></span>
          <div class="im"><img src="/img/movecia/fotos/<?= $h($img) ?>" alt="" loading="lazy" draggable="false"></div>
          <div class="tx"><h4><?= $h($t) ?></h4><p class="d"><?= $h($d) ?></p><p class="from"><?= count($vars) > 1 ? 'ab ' : '' ?><?= $h($eur($min)) ?></p></div>
        </div>
        <?php endforeach; ?>
        <div class="of" role="radio" tabindex="0" aria-checked="false" data-o="wert" data-vars="{}" data-t="Eigener Betrag">
          <span class="ck" aria-hidden="true"></span>
          <div class="tx" style="padding-top:1.2rem;justify-content:center;min-height:180px"><h4>Eigener <em>Betrag</em></h4><p class="d">Sie sucht sich selbst aus, was ihr guttut. Einlösbar für alles bei Movecia.</p><p class="from">ab € 20</p></div>
        </div>
      </div>
      <div class="vars" id="vars"></div>
      <label class="own" id="own" hidden>Betrag in Euro <input type="number" id="ownv" min="20" max="1000" step="5" value="50" inputmode="numeric"></label>
      <button type="button" class="btn nx">Weiter zum Design</button>
      </div>
    </div>

    <div class="step">
      <button type="button" class="sh"><span style="flex:1"><h3><span class="n">2</span>Wähl das Design</h3><span class="pk" id="pk2">Bogen</span></span><span class="cv"></span></button>
      <div class="sb">
      <div class="dz">
        <label><input type="radio" name="design" value="1" checked><img src="/img/movecia/gutschein-1.jpg?v=3" alt="Design Bogen" draggable="false"><span>Bogen</span></label>
        <label><input type="radio" name="design" value="2"><img src="/img/movecia/gutschein-2.jpg?v=3" alt="Design Zart" draggable="false"><span>Zart</span></label>
      </div>
      <button type="button" class="btn nx">Weiter</button>
      </div>
    </div>

    <div class="step">
      <button type="button" class="sh"><span style="flex:1"><h3><span class="n">3</span>Mach ihn persönlich</h3><span class="pk" id="pk3">Für wen, liebe Worte, deine E-Mail</span></span><span class="cv"></span></button>
      <div class="sb">
      <div class="grid2">
        <label class="f">Für<input type="text" id="to" maxlength="60" placeholder="z. B. Lena"></label>
        <label class="f">Von<input type="text" id="from" maxlength="60" placeholder="z. B. deine Schwester"></label>
      </div>
      <label class="f" style="margin-top:12px">Ein paar Worte auf dem Gutschein<textarea id="msg" maxlength="160" placeholder="z. B. Für dich, weil du immer für alle anderen da bist."></textarea></label>
      <div class="grid2">
        <label class="f">Dein Name<input type="text" autocomplete="name" maxlength="80"></label>
        <label class="f">Deine E-Mail (dorthin kommt das PDF)<input type="email" autocomplete="email" maxlength="160"></label>
      </div>
      <button type="button" class="btn nx">Zur Übersicht</button>
      </div>
    </div>
  </div>

  <aside class="sum">
    <span class="bl" aria-hidden="true"></span>
    <p class="k">Dein Gutschein</p>
    <p class="t" id="st"></p>
    <p class="amt" id="sa"></p>
    <div class="ln"><span>Design</span><span id="sd">Bogen</span></div>
    <div class="ln"><span>Für</span><span id="sto">–</span></div>
    <div class="ln"><span>Gültig</span><span>ohne Ablaufdatum</span></div>
    <button class="btn" type="button" id="pay">Weiter zur Zahlung</button>
    <p class="small">Sichere Zahlung über Stripe. Danach bekommst du den Gutschein sofort als PDF zum Ausdrucken, mit eigener Nummer und Code. Wertgutschein, einlösbar für alle Angebote bei Movecia, ein Restwert bleibt erhalten.</p>
  </aside>
</div></section>

<section class="w sec" style="padding-top:clamp(2.4rem,6vw,4.5rem)"><div class="in">
  <p class="k">So einfach geht's</p>
  <h2 style="margin-top:.5rem">Geschenkt ist <em>geschenkt</em></h2>
  <div class="how">
    <div><b>1</b><h4>Aussuchen &amp; bezahlen</h4><p>Angebot oder Betrag wählen, Design aussuchen, ein paar liebe Worte dazu.</p></div>
    <div><b>2</b><h4>Sofort als PDF</h4><p>Der Gutschein kommt direkt per E-Mail, schön zum Ausdrucken oder zum Weiterschicken.</p></div>
    <div><b>3</b><h4>Einlösen</h4><p>Sie gibt den Code beim Buchen ein oder schreibt mir einfach, dann machen wir die Termine gemeinsam aus.</p></div>
  </div>
</div></section>
</main>

<footer class="w"><div class="in"><p>Movecia – Personal Fitness · Vivien Malonga · <a href="/terms?lang=de">Impressum · Bedingungen</a></p></div></footer>
<script>
(() => {
  const euro = c => '€ ' + (c % 100 ? (c / 100).toFixed(2).replace('.', ',') : String(c / 100));
  const cards = document.querySelectorAll('.of'), vb = document.getElementById('vars'), own = document.getElementById('own'), ov = document.getElementById('ownv');
  let cur = null, v = null;
  function pick(c) {
    cur = c; cards.forEach(x => { x.classList.toggle('on', x === c); x.setAttribute('aria-checked', String(x === c)); });
    const vars = JSON.parse(c.dataset.vars), keys = Object.keys(vars);
    own.hidden = c.dataset.o !== 'wert';
    vb.innerHTML = ''; v = keys[0] || null;
    if (keys.length > 1) keys.forEach((k, i) => { const b = document.createElement('button'); b.type = 'button'; b.setAttribute('aria-pressed', String(!i)); b.innerHTML = vars[k][0] + '<b>' + euro(vars[k][1]) + '</b>'; b.onclick = () => { v = k; vb.querySelectorAll('button').forEach(x => x.setAttribute('aria-pressed', String(x === b))); sum(); }; vb.appendChild(b); });
    sum();
  }
  function sum() {
    const vars = JSON.parse(cur.dataset.vars);
    const cents = cur.dataset.o === 'wert' ? Math.max(2000, Math.min(100000, Math.round((+ov.value || 0) * 100))) : vars[v][1];
    document.getElementById('st').textContent = cur.dataset.t + (v && Object.keys(vars).length > 1 ? ' · ' + vars[v][0] : (v ? ' · ' + vars[v][0] : ''));
    document.getElementById('sa').textContent = euro(cents);
    document.getElementById('pk1').textContent = document.getElementById('st').textContent + ' · ' + euro(cents);
    document.getElementById('pay').textContent = 'Weiter zur Zahlung · ' + euro(cents);
  }
  cards.forEach(c => { c.addEventListener('click', () => pick(c)); c.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(c); } }); });
  ov.addEventListener('input', sum);
  document.querySelectorAll('input[name=design]').forEach(r => r.addEventListener('change', () => { document.getElementById('sd').textContent = r.value === '2' ? 'Zart' : 'Bogen'; }));
  document.getElementById('to').addEventListener('input', e => { document.getElementById('sto').textContent = e.target.value || '–'; });
  document.getElementById('pay').onclick = () => alert('Vorschau: Das Bezahlen schließe ich an, sobald dir die Seite gefällt.');
  // Handy: Schritte auf- und zuklappen
  const steps = [...document.querySelectorAll('.step')], mob = matchMedia('(max-width:899px)');
  function openStep(i, scroll) { steps.forEach((s, j) => s.classList.toggle('open', j === i)); if (scroll && mob.matches && steps[i]) steps[i].scrollIntoView({behavior: 'smooth', block: 'start'}); }
  steps.forEach((s, i) => {
    s.querySelector('.sh').onclick = () => { if (mob.matches) openStep(s.classList.contains('open') ? -1 : i, false); };
    s.querySelector('.nx').onclick = () => { if (i + 1 < steps.length) openStep(i + 1, true); else { openStep(-1); document.querySelector('.sum').scrollIntoView({behavior: 'smooth', block: 'start'}); } };
  });
  const p3 = () => { const t = document.getElementById('to').value.trim(); document.getElementById('pk3').textContent = t ? 'Für ' + t : 'Für wen, liebe Worte, deine E-Mail'; };
  document.getElementById('to').addEventListener('input', p3);
  document.querySelectorAll('input[name=design]').forEach(r => r.addEventListener('change', () => { document.getElementById('pk2').textContent = r.value === '2' ? 'Zart' : 'Bogen'; }));
  pick(document.querySelector('.of.on'));
})();
</script>
</body>
</html>

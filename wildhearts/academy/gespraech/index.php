<?php
// moveciamovement.com/academy/gespraech/?c=ID&k=TOKEN – persönlicher Gesprächsraum fürs Kennenlerngespräch Teacher Training
// (Vivien, 28.10.2026, Runde 4). Movecia-Seite mit eingebettetem Whereby-Raum. Tippt Vivien in der Verwaltung
// „✦ Angebot zeigen“, erscheint hier innerhalb weniger Sekunden die Karte mit dem Kennenlern-Preis (20 Min. Countdown,
// „Jetzt Platz sichern“ → normale Buchung). Preis und Link kommen nur über api/academy.php?call=…&k=… (nie öffentlich).
// Nur Deutsch, noindex.
declare(strict_types=1);
require_once __DIR__ . '/../../assets/icons.inc.php';
require dirname(__DIR__, 3) . '/api/academy-lib.php';
require __DIR__ . '/../../assets/layout.inc.php';
$GLOBALS['mvl_de_only'] = true;
$GLOBALS['mvl_noindex'] = true;
ob_start('mv_strip_en');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$pdo = forma_db();
ac_schema($pdo);
$h = 'forma_h';
$c = ac_call_by_link($pdo, (int)($_GET['c'] ?? 0), (string)($_GET['k'] ?? ''));
$now = time();
$state = 'invalid'; $src = ''; $start = 0;
if ($c) {
    $start = (new DateTimeImmutable($c['date'] . ' ' . $c['slot'], new DateTimeZone('Europe/Vienna')))->getTimestamp();
    $end = $start + AC_CALL_MINUTES * 60;
    if ($c['status'] === 'abgesagt') $state = 'cancelled';
    elseif ($c['status'] !== 'bestaetigt') $state = 'pending';
    elseif ($now < $start - 15 * 60) $state = 'soon';
    elseif ($now > $end + 60 * 60 && !ac_offer_live($c)) $state = 'over';
    elseif ((string)$c['room_url'] === '') $state = 'noroom';
    else {
        $state = 'open';
        $base = (string)$c['room_url'];
        $src = $base . (str_contains($base, '?') ? '&' : '?') . http_build_query(['displayName' => forma_first_name((string)$c['name']), 'background' => 'off', 'logo' => 'off', 'precallReview' => 'on', 'chat' => 'on', 'people' => 'off', 'locking' => 'off']);
    }
}
$first = $c ? forma_first_name((string)$c['name']) : '';
$css = '<link rel="stylesheet" href="/wildhearts/assets/academy.css?v=7"><style>
body.acall{min-height:100dvh;display:flex;flex-direction:column}
.acall main{flex:1;display:flex;flex-direction:column}
.cr-wait{max-width:560px;margin:clamp(1.5rem,6vh,4rem) auto;padding:0 16px;text-align:center}
.cr-card{background:rgba(251,248,244,.94);border-radius:32px;padding:clamp(1.6rem,4vw,2.6rem);box-shadow:0 22px 50px -34px rgba(74,58,65,.5)}
.cr-card .ac-seal{width:116px;margin:0 auto 1rem}
.cr-card h1{font-family:var(--serif);font-weight:500;font-size:clamp(2rem,6vw,2.8rem);line-height:1.08;margin:.2rem 0 .6rem}
.cr-card h1 em{color:var(--plum)}
.cr-card p{color:var(--muted);line-height:1.6}
.cr-when{display:inline-block;margin:.8rem 0 .2rem;background:var(--blush);color:var(--deep);border-radius:99px;padding:.45em 1.1em;font-size:.98rem}
.cr-cd{font-family:var(--serif);font-size:2.4rem;color:var(--plum);margin:.6rem 0 0}
.cr-tips{list-style:none;padding:0;margin:1.2rem 0 0;display:grid;gap:.5rem;text-align:left}
.cr-tips li{background:#FBF5F7;border-radius:16px;padding:.7rem 1rem;font-size:.95rem;color:var(--ink)}
.cr-room{flex:1;display:flex;flex-direction:column;padding:0 clamp(0px,1.4vw,18px) clamp(0px,1.4vw,18px)}
.cr-room iframe{flex:1;width:100%;min-height:calc(100dvh - 120px);border:0;border-radius:clamp(0px,1.4vw,20px);background:var(--deep);display:block}
/* Angebotskarte (wie im Gathering): erscheint, sobald Vivien das Angebot zeigt */
.offer{position:fixed;z-index:60;left:50%;bottom:max(14px,env(safe-area-inset-bottom));transform:translate(-50%,130%);width:min(440px,calc(100vw - 24px));
  background:linear-gradient(140deg,#5E4A51,#6B5658 45%,#7D5E68);color:#FBF5F7;border-radius:30px;padding:1.2rem 1.3rem 1.25rem;box-shadow:0 26px 60px -20px rgba(46,37,41,.65);transition:transform .7s cubic-bezier(.2,.9,.2,1),opacity .4s;opacity:0;overflow:hidden}
.offer.on{transform:translate(-50%,0);opacity:1}
.offer::before{content:"";position:absolute;right:-50px;top:-70px;width:200px;height:190px;border-radius:58% 42% 47% 53%/52% 46% 54% 48%;background:rgba(232,214,202,.16)}
.offer .o-top{display:flex;align-items:center;gap:.8rem;position:relative}
.offer .o-top .ac-seal{width:46px;flex:0 0 46px}
.offer .o-k{font-size:.7rem;letter-spacing:.2em;text-transform:uppercase;color:#EADFDE!important;margin:0}
.offer .o-t{font-family:var(--serif);font-size:1.45rem;line-height:1.1;margin:.15rem 0 0;color:#FBF5F7!important;white-space:nowrap}
.offer .o-d,.offer .o-p{color:#F3E2D8}.offer .o-p b{color:#FBF5F7}
.offer .o-x{margin-left:auto;align-self:flex-start;background:rgba(251,245,247,.14);border:0;color:inherit;width:34px;height:34px;border-radius:50%;font-size:1.1rem;cursor:pointer}
.offer .o-p{display:flex;align-items:baseline;flex-wrap:wrap;gap:.2rem .7rem;margin:.9rem 0 .2rem;position:relative}
.offer .o-p b{font-family:var(--serif);font-weight:500;font-size:2.7rem;line-height:1}
.offer .o-p s{opacity:.65;font-size:1.05rem}
.offer .o-p em{font-style:normal;background:#F3E2D8;color:#4A3A41;border-radius:99px;padding:.2em .7em;font-size:.82rem}
.offer .o-d{font-size:.9rem;color:#F3E2D8;margin:.2rem 0 .9rem;line-height:1.45;position:relative}
.offer .o-row{display:flex;align-items:center;gap:.8rem;position:relative}
.offer .o-cd{font-family:var(--serif);font-size:1.6rem;min-width:4.2ch;text-align:center;background:rgba(251,245,247,.12);border-radius:16px;padding:.25em .5em;line-height:1.1}
.offer .o-cd small{display:block;font-family:var(--sans);font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;color:#EADFDE}
.offer .o-go{flex:1;display:block;text-align:center;background:#FBF5F7;color:#4A3A41;text-decoration:none;border-radius:99px;padding:.95em 1em;font-weight:500;font-size:1.02rem}
.offer-pill{position:fixed;z-index:60;right:14px;bottom:max(14px,env(safe-area-inset-bottom));background:#6B5658;color:#FBF5F7;border:0;border-radius:99px;padding:.75em 1.15em;font:500 .95rem var(--sans);box-shadow:0 14px 30px -14px rgba(46,37,41,.6);cursor:pointer;display:none}
.offer-pill.on{display:block}
@media(min-width:900px){.offer{left:auto;right:28px;transform:translate(0,130%)}.offer.on{transform:translate(0,0)}}
@media(prefers-reduced-motion:reduce){.offer{transition:none}}
</style>';
mvl_head('Kennenlerngespräch · Movecia Academy', '', mm_site() . '/academy/gespraech/', '/img/movecia/og-academy.jpg', $css);
?>
<body class="acad acall">
<header class="w site-top"><div class="in top"><a class="brand" href="/academy/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia<i></i></span></a><div class="top-r"><span class="muted" style="font-size:.9rem">Kennenlerngespräch</span></div></div></header>
<?= ac_band('Unser Kennenlerngespräch') ?>
<main id="top">
<?php if ($state === 'open'): ?>
  <div class="cr-room"><iframe src="<?= $h($src) ?>" allow="camera; microphone; fullscreen; speaker; display-capture; autoplay; compute-pressure" title="Movecia Videoraum"></iframe></div>
<?php else: ?>
  <div class="cr-wait"><div class="cr-card">
    <?= ac_seal() ?>
    <?php if ($state === 'invalid'): ?>
      <h1>Dieser Link <em>passt nicht</em></h1><p>Bitte nimm den Knopf aus deiner Bestätigungsmail. Wenn es trotzdem nicht klappt, schreib mir einfach an hello@moveciamovement.com.</p>
    <?php elseif ($state === 'cancelled'): ?>
      <h1>Dieses Gespräch ist <em>abgesagt</em></h1><p>Du hast dazu eine Mail von mir bekommen. Einen neuen Termin findest du jederzeit auf der <a href="/academy/teacher-training/#gespraech">Seite zur Ausbildung</a>.</p>
    <?php elseif ($state === 'pending'): ?>
      <h1>Gleich <em>fix</em></h1><p>Ich bestätige dir deinen Termin in Kürze per E-Mail. Dann öffnet sich hier dein Gesprächsraum.</p>
    <?php elseif ($state === 'over'): ?>
      <h1>Danke für <em>unser Gespräch</em> ♡</h1><p>Der Raum ist jetzt geschlossen. Alles zur Ausbildung findest du jederzeit auf der <a href="/academy/teacher-training/">Seite zum Teacher Training</a>.</p>
    <?php elseif ($state === 'noroom'): ?>
      <h1>Schön, dass du da bist, <em><?= $h($first) ?></em></h1><p>Den Link zu unserem Videoraum schicke ich dir vor dem Gespräch per E-Mail.</p>
    <?php else: ?>
      <h1>Schön, dass du da bist, <em><?= $h($first) ?></em></h1>
      <span class="cr-when"><?= $h(ac_call_when($c)) ?></span>
      <p class="cr-cd" id="crcd" data-open="<?= $start - 15 * 60 ?>" data-now="<?= $now ?>">–</p>
      <p>Dein Gesprächsraum öffnet sich hier 15 Minuten vor Beginn von selbst. Du brauchst keine App und kein Konto.</p>
      <ul class="cr-tips"><li>Such dir einen ruhigen Platz und nimm dir ein Glas Wasser oder einen Tee mit.</li><li>Am Handy oder Laptop: Erlaube Kamera und Mikrofon, wenn dein Browser fragt.</li><li>Überleg dir gern vorher: Was wünschst du dir von der Ausbildung?</li></ul>
    <?php endif; ?>
  </div></div>
<?php endif; ?>
</main>
<?php if ($c): ?>
<div class="offer" id="offer" role="dialog" aria-live="polite" aria-label="Dein Kennenlern-Preis" hidden>
  <div class="o-top"><?= ac_seal('mini', null, true) ?><div><p class="o-k">nur für dich, nur jetzt</p><p class="o-t">Dein Kennenlern-Preis</p></div><button class="o-x" type="button" id="ox" aria-label="Kleiner machen">–</button></div>
  <div class="o-p"><b id="op">–</b><s id="ob"></s><em id="os"></em></div>
  <p class="o-d">Mindful Becoming Teacher Training · <?= AC_HOURS ?> Stunden · Start <?= mv_date(ac_cfg()['start_ts']) ?><br>50 % Anzahlung (<span id="odp"></span>), den Rest auf einmal oder in Teilen, ohne Aufschlag.</p>
  <div class="o-row"><span class="o-cd"><span id="ocd">20:00</span><small>Min. übrig</small></span><a class="o-go" id="ogo" href="#" target="_blank" rel="noopener">Jetzt Platz sichern →</a></div>
</div>
<button class="offer-pill" id="opill" type="button">✦ Dein Kennenlern-Preis · <span id="opcd"></span></button>
<script>
(function(){
  var cd=document.getElementById('crcd');
  if(cd){var open=+cd.dataset.open*1000,skew=Date.now()-(+cd.dataset.now*1000);
    var t=function(){var ms=open-(Date.now()-skew);if(ms<=0){location.reload();return}var s=Math.ceil(ms/1000),d=Math.floor(s/86400),hh=Math.floor(s%86400/3600),m=Math.floor(s%3600/60);
      cd.textContent=d>0?('noch '+d+(d===1?' Tag ':' Tage ')+hh+' Std.'):(hh>0?('noch '+hh+' Std. '+m+' Min.'):('noch '+m+':'+String(s%60).padStart(2,'0')));setTimeout(t,1000)};t();}
  var box=document.getElementById('offer'),pill=document.getElementById('opill'),q='/api/academy.php?call=<?= (int)$c['id'] ?>&k=<?= $h((string)$c['token']) ?>';
  var until=0,skew=0,small=false,shown=false;
  function fmt(ms){var s=Math.max(0,Math.ceil(ms/1000));return Math.floor(s/60)+':'+String(s%60).padStart(2,'0')}
  function tick(){if(!until)return;var ms=until-(Date.now()-skew);document.getElementById('ocd').textContent=fmt(ms);document.getElementById('opcd').textContent=fmt(ms);
    if(ms<=0){hide();return}setTimeout(tick,500)}
  function hide(){until=0;shown=false;box.classList.remove('on');pill.classList.remove('on');setTimeout(function(){box.hidden=true},700)}
  function show(o,now){skew=Date.now()-now*1000;var first=!until;until=o.until*1000;
    document.getElementById('op').textContent=o.total;document.getElementById('ob').textContent=o.before;document.getElementById('os').textContent='du sparst '+o.save;
    document.getElementById('odp').textContent=o.deposit;document.getElementById('ogo').href=o.link;
    if(!shown){shown=true;box.hidden=false;requestAnimationFrame(function(){requestAnimationFrame(function(){if(!small)box.classList.add('on');else pill.classList.add('on')})})}
    if(first)tick()}
  async function poll(){try{var r=await fetch(q,{cache:'no-store'});var j=await r.json();if(j.ok&&j.offer)show(j.offer,j.now);else if(until)hide()}catch(e){}setTimeout(poll,shown?15000:5000)}
  document.getElementById('ox').addEventListener('click',function(){small=true;box.classList.remove('on');pill.classList.add('on')});
  pill.addEventListener('click',function(){small=false;pill.classList.remove('on');box.classList.add('on')});
  poll();
})();
</script>
<?php endif; ?>
</body></html>

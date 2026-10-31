<?php
// moveciamovement.com/academy – Movecia Academy (Vivien, 28.10.2026): Übersicht der Ausbildungen.
// Zurzeit eine Ausbildung: The Mindful Becoming Teacher Training (Start 3.11.2027).
// Platz für weitere Ausbildungen ist vorgesehen (zweite Karte „in Vorbereitung“), aber noch nichts gebaut.
declare(strict_types=1);
require_once __DIR__ . '/../assets/icons.inc.php';
require dirname(__DIR__, 2) . '/api/academy-lib.php';
require __DIR__ . '/../assets/layout.inc.php';
$GLOBALS['mvl_de_only'] = true; // Ausbildung nur auf Deutsch (Vivien, 28.10.2026)
ob_start('mv_strip_en');
header('Cache-Control: no-cache');
$pdo = forma_db();
ac_schema($pdo);
try { ac_tick($pdo); } catch (Throwable $e) { error_log('AC tick: ' . $e->getMessage()); }
$c = ac_cfg();
$free = ac_free($pdo) > 0 && $c['open'];
$site = mm_site();
$st = $c['start_ts'];
$ld = [
    ['@context' => 'https://schema.org', '@type' => 'EducationalOrganization', '@id' => $site . '/academy/#org', 'name' => 'Movecia Academy', 'url' => $site . '/academy/', 'logo' => $site . '/img/movecia/movecia-zeichen-1024.png',
     'description' => 'Ausbildungen für Mindful Movement, Yoga, Somatic Movement und Breathwork mit Vivien Malonga, hybrid aus den Tiroler Alpen.', 'founder' => ['@type' => 'Person', 'name' => 'Vivien Malonga'], 'parentOrganization' => ['@id' => $site . '/#movecia'],
     'hasOfferCatalog' => ['@type' => 'OfferCatalog', 'name' => 'Ausbildungen', 'itemListElement' => [['@type' => 'Offer', 'itemOffered' => ['@type' => 'Course', '@id' => $site . '/academy/teacher-training/#course', 'name' => AC_TITLE, 'url' => $site . '/academy/teacher-training/']]]]],
    ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Movecia', 'item' => $site . '/'], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Academy', 'item' => $site . '/academy/']]],
];
$css = '<link rel="stylesheet" href="/wildhearts/assets/academy.css?v=18">';
mvl_head('Movecia Academy · Yogalehrerausbildung auf Deutsch, Tirol & online', 'Ausbildungen der Movecia Academy mit Vivien Malonga, ausschließlich auf Deutsch, für Österreich, Deutschland, die Schweiz und Südtirol: das Mindful Becoming Teacher Training mit 550 Stunden, inkl. vollständiger 200-Stunden-Yogalehrerausbildung, Somatic Movement und Breathwork. Hybrid, mit Präsenzwochenende in den Tiroler Alpen. Nur 14 Plätze, Start November 2027.', $site . '/academy/', '/img/movecia/og-academy.jpg', $css, $ld);
?>
<body class="acad">
<?php mvl_top('academy'); ?>
<p class="en-note">This training is held in German only.</p>
<?= ac_band('Ausbildungen') ?>
<div class="acc-wrap"><?= ac_countdown() ?></div>
<main>
<section class="w"><div class="in hero-x ac-hero two ac-land">
  <?= mv_waves(false, 'calm') ?>
  <div>
    <h1><span class="de">Lernen, <em>andere</em> zu begleiten.</span><span class="en">Learn to <em>guide</em> others.</span></h1>
    <p class="sub"><span class="de">Ohne dich selbst dabei zu verlieren.</span><span class="en">Without losing yourself along the way.</span></p>
    <p class="lead"><span class="de">In der Movecia Academy gebe ich weiter, was ich in vielen Jahren Unterricht gelernt habe: Mindful Movement, Yoga, Somatic Movement und Breathwork, verbunden zu einer Praxis, die Körper und Kopf spürbar stärkt. Für Frauen, die selbst schon eine Weile in Bewegung sind und spüren, dass sie das weitergeben möchten. Alle Ausbildungen finden auf Deutsch statt, online aus Österreich, Deutschland, der Schweiz und Südtirol, mit Präsenzzeit in Tirol.</span><span class="en">In the Movecia Academy I pass on what many years of teaching have taught me: mindful movement, yoga, somatic movement and breathwork, woven into one practice that leaves body and mind noticeably stronger. For women who have been moving for a while themselves and feel that they want to share it.</span></p>
    <div class="cta"><a class="btn" href="/academy/teacher-training/"><span class="de">Zum Teacher Training</span><span class="en">See the teacher training</span> →</a><a class="btn line" href="#aufbau"><span class="de">So ist die Ausbildung aufgebaut</span><span class="en">How the training works</span></a></div>
  </div>
  <figure class="pic"><img src="/img/movecia/fotos/ev-gathering.jpg?v=1" alt="Laptop mit Videocall und eine Tasse Kaffee im Movecia-Studio" loading="eager"><figcaption class="stamp"><small>Hybrid · aus den Tiroler Bergen</small>Online lernen, live üben, gemeinsam wachsen.</figcaption></figure>
</div></section>
<div class="ac-ribbon" aria-hidden="true"><div class="rb-in"><?php for ($i = 0; $i < 4; $i++): ?><span><span class="de">nur 14 Plätze</span><span class="en">only 14 places</span></span><i>✦</i><span><?= AC_HOURS ?> <span class="de">Stunden</span><span class="en">hours</span></span><i>✦</i><span><span class="de">inkl. 200-Std.-Yogalehrerausbildung</span><span class="en">incl. 200-hour yoga teacher training</span></span><i>✦</i><?php endfor; ?></div></div>

<section class="w sec tint t1 ac-tight" id="ausbildungen"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Ausbildungen</span><span class="en">Trainings</span></p><h2 style="margin-top:.6rem"><span class="de">Dein nächster <em>Schritt</em></span><span class="en">Your next <em>step</em></span></h2></div>
    <p><span class="de">Kleine Gruppen, persönliche Begleitung und viel Zeit zum Üben. Hybrid, damit es neben Familie und Alltag Platz hat.</span><span class="en">Small groups, personal guidance and plenty of time to practise. Hybrid, so it fits alongside family and everyday life.</span></p></div>
  <div class="ac-list">
    <a class="ac-card rv" href="/academy/teacher-training/">
      <div class="ac-pic"><img src="/img/movecia/fotos/ev-lunge.jpg?v=1" alt="" loading="lazy"><span class="onum">01</span></div>
      <div class="ac-tx">
        <?= mv_icons(['spirale', 'atem', 'herz'], 'ico', 0) ?>
        <p class="k"><span class="de"><?= AC_HOURS ?> Stunden · hybrid · 11 Monate</span><span class="en"><?= AC_HOURS ?> hours · hybrid · 11 months</span></p>
        <h3>The Mindful Becoming Teacher Training</h3>
        <p class="ac-sub"><span class="de">200 Std. Yoga Teacher · Somatic Movement · Mindful Movement &amp; Mindset</span><span class="en">200 h Yoga Teacher · Somatic Movement · Mindful Movement &amp; Mindset</span></p>
        <p><span class="de">Andere begleiten und dabei bei dir selbst bleiben. Mit einer vollständigen 200-Stunden-Yogalehrerausbildung inklusive, dazu Somatic Movement, Breathwork, Anatomie und Body Scanning, achtsame Berührung und Rituale, Mental Load und Nervensystem und deine Selbstständigkeit.</span><span class="en">Guide others and stay close to yourself while you do. A complete 200-hour yoga teacher training is included, plus somatic movement, breathwork, anatomy and body scanning, mindful touch and rituals, mental load and the nervous system, and building your own business.</span></p>
        <div class="meta">
          <span class="chip"><span class="de">Start <?= mv_date($st) ?></span><span class="en">Starts <?= mv_date($st, 'en') ?></span></span>
          <span class="chip"><span class="de">Präsenzwochenende in den Tiroler Alpen</span><span class="en">In-person weekend in the Tyrolean Alps</span></span>
          <span class="chip <?= $free ? 'ok' : '' ?>"><?= $free ? '<span class="de">Plätze frei</span><span class="en">Places available</span>' : '<span class="de">ausgebucht</span><span class="en">fully booked</span>' ?></span>
        </div>
        <?php if ($c['eb']): ?><span class="ebird" style="margin-top:1.8rem"><?= ac_birds() ?><b>Early Bird</b><span class="eb-p"><?= str_replace('€ ', '€&nbsp;', mv_eur($c['eb_total'])) ?></span><span class="eb-d"><span class="de">bis <?= mv_date($c['eb_end']) ?> · danach <?= str_replace('€ ', '€&nbsp;', mv_eur($c['r_total'])) ?></span><span class="en">until <?= mv_date($c['eb_end'], 'en') ?> · then <?= ac_eur($c['r_total'], 'en') ?></span></span></span><?php endif; ?>
        <p class="more"><span class="de">Alles zur Ausbildung</span><span class="en">All about the training</span> →</p>
      </div>
    </a>
    <div class="ac-card ac-callc rv" id="gespraech">
      <div class="ac-callc-in">
        <?= mv_icons(['kreis', 'herz'], 'ico', 1.5) ?>
        <p class="k">Kostenlos · 30 Minuten · freiwillig</p>
        <h3>Lass uns <em>kennenlernen</em></h3>
        <p class="mut">Im Videogespräch erzähle ich dir alles zur Ausbildung und du kannst mich fragen, was dir auf dem Herzen liegt. Wähl deinen Wunschtag, ich schicke dir die genaue Uhrzeit per E-Mail.</p>
        <form id="cf" class="cform" novalidate>
          <label class="f"><span>Wunschtag (Mo–Fr)</span><input type="date" id="wday" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= date('Y-m-d', strtotime('+' . AC_CALL_WEEKS . ' weeks')) ?>"></label>
          <div class="f" id="wish"><span>Tageszeit</span><div class="parts" role="radiogroup" aria-label="Tageszeit"><?php foreach (AC_CALL_PARTS as $k => [$lbl]): ?><button type="button" class="part" data-p="<?= $k ?>" role="radio" aria-checked="false"><?= $lbl ?></button><?php endforeach; ?></div></div>
          <label class="f"><span>Dein Name</span><input type="text" name="name" autocomplete="name" required maxlength="80"></label>
          <label class="f"><span>E-Mail</span><input type="email" name="email" autocomplete="email" required maxlength="160"></label>
          <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="btn" type="submit" id="cb">Wunschtermin schicken</button>
          <p class="mut small">Deine Angaben nutze ich nur für unser Gespräch (<a href="/terms/#datenschutz">Datenschutz</a>).</p>
          <p class="err" id="ce" hidden></p>
        </form>
        <div class="okmsg" id="co" hidden></div>
      </div>
    </div>
  </div>
</div></section>

<section class="band"><span class="band-blob b1" aria-hidden="true"></span><span class="band-blob b2" aria-hidden="true"></span><div class="w"><div class="in">
  <p class="k"><span class="de">Was die Academy ausmacht</span><span class="en">What makes the Academy different</span></p>
  <blockquote><span class="de">Gute Begleitung beginnt mit Zuhören. Bei der Person vor dir und bei dir selbst.</span><span class="en">Good guidance begins with listening. To the person in front of you, and to yourself.</span></blockquote>
  <p class="after"><span class="de">Viele Frauen tragen heute sehr viel Verantwortung und einen vollen Kopf. In meinen Ausbildungen lernst du, darauf einzugehen: mit Wissen über Körper und Nervensystem, mit Feingefühl und mit Bewegung, die sich nach dem Menschen richtet.</span><span class="en">Many women today carry a great deal of responsibility and a very full head. In my trainings you learn to respond to that: with knowledge of the body and the nervous system, with sensitivity, and with movement that adapts to the person.</span></p>
</div></div></section>

<section class="w sec" id="aufbau"><div class="in">
  <div class="rv" style="text-align:center"><p class="k">Dein Weg zur Ausbildung</p><h2 style="margin-top:.6rem">So ist die Ausbildung <em>aufgebaut</em></h2></div>
  <ol class="aufbau rv">
    <li><span class="ab-dot"><?= mv_icons(['sonne', 'welle'], 'si', 0) ?></span><div class="ab-c">
      <p class="ab-k">Vorher · 6 Monate bis 1,5 Jahre</p>
      <h3>Deine eigene <em>Praxis</em></h3>
      <p>Je nach Motivation übst du ein halbes bis eineinhalb Jahre selbst, am besten mit <a href="/method/">The Movecia Daily Method</a>. Wenn du die Movecia-Haltung erst einmal in Ruhe kennenlernen möchtest, ist <a href="/unfold/">Unfold</a> ein schöner, freiwilliger Einstieg.</p></div></li>
    <li><span class="ab-dot"><?= mv_icons(['spirale', 'atem'], 'si', 1) ?></span><div class="ab-c">
      <p class="ab-k">Start <?= mv_date($st) ?> · 11 Monate hybrid</p>
      <h3>The Mindful Becoming <em>Teacher Training</em></h3>
      <p>Wir starten mit der vollständigen 200-Stunden-Yogalehrerausbildung, die inklusive ist und dem internationalen Curriculum folgt. Darauf bauen Somatic Movement, Breathwork, Anatomie und alles Weitere auf, bis du bei <?= AC_HOURS ?> Stunden bist:</p>
      <ul class="ab-hours"><?php foreach (ac_hours() as [$de, $en, $hh, $col]): ?><li><i style="background:<?= $col ?>"></i><span><?= forma_h($de) ?></span><b><?= (int)$hh ?> Std.</b></li><?php endforeach; ?><li class="sum"><span>Gesamt</span><b><?= AC_HOURS ?> Std.</b></li></ul></div></li>
    <li><span class="ab-dot"><?= mv_icons(['berg', 'kerze'], 'si', 2) ?></span><div class="ab-c">
      <p class="ab-k">September 2028 · in den Tiroler Bergen</p>
      <h3>Das <em>Community</em>-Wochenende</h3>
      <p>Beim Präsenzwochenende kommen wir alle zusammen, mit Unterkunft und Verpflegung. Hier gehst du noch tiefer in deine Praxis und hältst deine Lehrprobe.</p></div></li>
    <li><span class="ab-dot"><?= mv_icons(['blatt', 'kreis'], 'si', 3) ?></span><div class="ab-c">
      <p class="ab-k">Danach · online</p>
      <h3>Deine <em>Abschlussprüfung</em></h3>
      <p>Eine Multiple-Choice-Prüfung, die du ganz entspannt von zu Hause aus schreibst.</p></div></li>
    <li class="ab-end"><span class="ab-dot"><?= mv_icons(['bluete', 'herz'], 'si', 4) ?></span><div class="ab-c">
      <p class="ab-k">Gratuliere ♡</p>
      <h3>Dein Weg als Mindful &amp; Somatic <em>Movement Teacher</em></h3>
      <p><?= AC_HOURS ?> Stunden Ausbildung liegen hinter dir. Jetzt beginnt dein eigener Weg, andere Menschen zurück in ihre Bewegung zu begleiten.</p>
      <a class="btn" href="/academy/teacher-training/">Alles zum Teacher Training →</a></div></li>
  </ol>
</div></section>

<section class="w sec tint t3"><div class="in">
  <div class="rv" style="text-align:center"><p class="k"><span class="de">So lernst du bei mir</span><span class="en">How you learn with me</span></p><h2 style="margin-top:.6rem"><span class="de">Hybrid und <em>persönlich</em></span><span class="en">Hybrid and <em>personal</em></span></h2></div>
  <div class="cats cute-cards">
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('welle') ?></span><b><span class="de">Online, in deinem Tempo</span><span class="en">Online, at your pace</span></b><p><span class="de">Theorie-Videos und ausführliche Workbooks, jeden Monat ein neues Modul.</span><span class="en">Theory videos and extensive workbooks, with a new module every month.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('kreis') ?></span><b><span class="de">Jede Woche live</span><span class="en">Live every week</span></b><p><span class="de">Im Live-Call üben wir, besprechen Fragen und lernen voneinander.</span><span class="en">In the live call we practise, talk through questions and learn from each other.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('berg') ?></span><b><span class="de">Zusammen in den Bergen</span><span class="en">Together in the mountains</span></b><p><span class="de">Ein Präsenzwochenende in den Tiroler Alpen, mit Unterkunft und Verpflegung.</span><span class="en">An in-person weekend in the Tyrolean Alps, with accommodation and meals.</span></p></div>
  </div>
</div></section>
</main>
<script>
(function(){
  var T=Date.now(),part='',wday=document.getElementById('wday');if(!wday)return;
  document.querySelectorAll('#wish .part').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('#wish .part').forEach(function(x){x.setAttribute('aria-checked','false')});b.setAttribute('aria-checked','true');part=b.dataset.p})});
  var cf=document.getElementById('cf'),ce=document.getElementById('ce'),co=document.getElementById('co'),cb=document.getElementById('cb');
  cf.addEventListener('submit',async function(ev){ev.preventDefault();ce.hidden=true;
    var wd=wday.value?new Date(wday.value+'T12:00:00').getDay():-1;
    if(!wday.value){ce.textContent='Bitte wähl deinen Wunschtag.';ce.hidden=false;return}
    if(wd===0||wd===6){ce.textContent='Bitte wähl einen Tag von Montag bis Freitag.';ce.hidden=false;return}
    if(!part){ce.textContent='Bitte wähl Vormittag, Mittag oder Abend.';ce.hidden=false;return}
    if(cf.name.value.trim().length<2||cf.email.value.indexOf('@')<1){ce.textContent='Bitte gib deinen Namen und deine E-Mail an.';ce.hidden=false;return}
    var d=Object.fromEntries(new FormData(cf));d.action='call';d.date=wday.value;d.daypart=part;d.wish_time='';d.lang='de';d.t=T;cb.disabled=true;
    try{var r=await fetch('/api/academy.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      if(j.ok){cf.hidden=true;co.textContent='Danke! Dein Wunschtermin: '+j.when+'. Ich schicke dir in den nächsten ein, zwei Tagen die genaue Uhrzeit per E-Mail ♡';co.hidden=false;return}
      ce.textContent=j.error||'Das hat gerade nicht geklappt.';ce.hidden=false}catch(x){ce.textContent='Das hat gerade nicht geklappt. Bitte versuch es noch einmal.';ce.hidden=false}
    cb.disabled=false});
})();
</script>
<?php mvl_foot(); ?>
</body>
</html>

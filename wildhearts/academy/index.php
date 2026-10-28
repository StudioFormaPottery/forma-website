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
$css = '<link rel="stylesheet" href="/wildhearts/assets/academy.css?v=7">';
mvl_head('Movecia Academy · Yogalehrerausbildung auf Deutsch, Tirol & online', 'Ausbildungen der Movecia Academy mit Vivien Malonga, ausschließlich auf Deutsch, für Österreich, Deutschland, die Schweiz und Südtirol: das Mindful Becoming Teacher Training mit 550 Stunden, inkl. vollständiger 200-Stunden-Yogalehrerausbildung, Somatic Movement und Breathwork. Hybrid, mit Präsenzwochenende in den Tiroler Alpen. Nur alle 2 Jahre, nur 10 Plätze, Start November 2027.', $site . '/academy/', '/img/movecia/og-academy.jpg', $css, $ld);
?>
<body class="acad">
<?php mvl_top('academy'); ?>
<p class="en-note">This training is held in German only.</p>
<?= ac_band('Ausbildungen') ?>
<main>
<section class="w"><div class="in hero-x ac-hero">
  <?= mv_waves(false) ?>
  <div>
    <div class="ac-head"><?= ac_seal() ?><p class="k" style="margin:0"><span class="de">Movecia Academy · Ausbildungen</span><span class="en">Movecia Academy · trainings</span><br><span class="de" style="color:var(--muted)">klein, persönlich, nur alle zwei Jahre</span><span class="en" style="color:var(--muted)">small, personal, only every two years</span></p></div>
    <h1><span class="de">Lernen, <em>andere</em> zu begleiten.</span><span class="en">Learn to <em>guide</em> others.</span></h1>
    <p class="sub"><span class="de">Und dabei bei dir zu bleiben.</span><span class="en">And stay close to yourself while you do.</span></p>
    <p class="lead"><span class="de">In der Movecia Academy gebe ich weiter, was ich in vielen Jahren Unterricht gelernt habe: wie Bewegung, Atem und echtes Zuhören zusammenkommen. Für Frauen, die selbst schon eine Weile in Bewegung sind und spüren, dass sie das weitergeben möchten. Alle Ausbildungen finden auf Deutsch statt, online aus Österreich, Deutschland, der Schweiz und Südtirol, mit Präsenzzeit in Tirol.</span><span class="en">In the Movecia Academy I pass on what many years of teaching have taught me: how movement, breath and real listening come together. For women who have been moving for a while themselves and feel that they want to share it.</span></p>
    <div class="cta"><a class="btn" href="/academy/teacher-training/"><span class="de">Zum Teacher Training</span><span class="en">See the teacher training</span> →</a><a class="btn line" href="#ausbildungen"><span class="de">Alle Ausbildungen</span><span class="en">All trainings</span></a></div>
  </div>
</div></section>
<div class="ac-ribbon" aria-hidden="true"><div class="rb-in"><?php for ($i = 0; $i < 2; $i++): ?><span><span class="de">nur 10 Plätze</span><span class="en">only 10 places</span></span><i>✦</i><span><span class="de">nur alle 2 Jahre</span><span class="en">only every 2 years</span></span><i>✦</i><span><?= AC_HOURS ?> <span class="de">Stunden</span><span class="en">hours</span></span><i>✦</i><span><span class="de">inkl. 200-Std.-Yogalehrerausbildung</span><span class="en">incl. 200-hour yoga teacher training</span></span><i>✦</i><?php endfor; ?></div></div>

<section class="w sec tint t1" id="ausbildungen"><div class="in">
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
    <div class="ac-card ac-soon rv" aria-label="Weitere Ausbildungen">
      <div class="ac-soon-in">
        <?= mv_icons(['knospe', 'bluete'], 'ico', 1.5) ?>
        <p class="k"><span class="de">In Vorbereitung</span><span class="en">In the making</span></p>
        <h3><span class="de">Weitere Ausbildungen</span><span class="en">More trainings</span></h3>
        <p><span class="de">Hier wächst gerade noch etwas. Wenn es so weit ist, erfährst du es zuerst in den Gatherings und auf Instagram.</span><span class="en">Something else is still growing here. When it is ready, you will hear about it first in the Gatherings and on Instagram.</span></p>
        <a class="btn line sm" href="/gathering/"><span class="de">Zu den Gatherings</span><span class="en">Join a Gathering</span></a>
      </div>
    </div>
  </div>
  <div class="acad-cta rv"><p><b>Noch nicht so weit?</b> Setz deinen Namen auf die Liste, dann bekommst du alle Infos zum Teacher Training und eine Erinnerung, bevor der Early-Bird-Preis endet.</p><a class="btn line" href="/academy/teacher-training/#liste">Auf die Liste setzen</a></div>
</div></section>

<section class="band"><span class="band-blob b1" aria-hidden="true"></span><span class="band-blob b2" aria-hidden="true"></span><div class="w"><div class="in">
  <p class="k"><span class="de">Was die Academy ausmacht</span><span class="en">What makes the Academy different</span></p>
  <blockquote><span class="de">Gute Begleitung beginnt mit Zuhören. Bei der Person vor dir und bei dir selbst.</span><span class="en">Good guidance begins with listening. To the person in front of you, and to yourself.</span></blockquote>
  <p class="after"><span class="de">Viele Frauen tragen heute sehr viel Verantwortung und einen vollen Kopf. In meinen Ausbildungen lernst du, darauf einzugehen: mit Wissen über Körper und Nervensystem, mit Feingefühl und mit Bewegung, die sich nach dem Menschen richtet.</span><span class="en">Many women today carry a great deal of responsibility and a very full head. In my trainings you learn to respond to that: with knowledge of the body and the nervous system, with sensitivity, and with movement that adapts to the person.</span></p>
</div></div></section>

<section class="w sec tint t3"><div class="in">
  <div class="rv" style="text-align:center"><p class="k"><span class="de">So lernst du bei mir</span><span class="en">How you learn with me</span></p><h2 style="margin-top:.6rem"><span class="de">Hybrid und <em>persönlich</em></span><span class="en">Hybrid and <em>personal</em></span></h2></div>
  <div class="cats cute-cards">
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('welle') ?></span><b><span class="de">Online, in deinem Tempo</span><span class="en">Online, at your pace</span></b><p><span class="de">Theorie-Videos und ausführliche Workbooks, jeden Monat ein neues Modul.</span><span class="en">Theory videos and extensive workbooks, with a new module every month.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('kreis') ?></span><b><span class="de">Jede Woche live</span><span class="en">Live every week</span></b><p><span class="de">Im Live-Call üben wir, besprechen Fragen und lernen voneinander.</span><span class="en">In the live call we practise, talk through questions and learn from each other.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('berg') ?></span><b><span class="de">Zusammen in den Bergen</span><span class="en">Together in the mountains</span></b><p><span class="de">Ein Präsenzwochenende in den Tiroler Alpen, mit Unterkunft und Verpflegung.</span><span class="en">An in-person weekend in the Tyrolean Alps, with accommodation and meals.</span></p></div>
  </div>
</div></section>
</main>
<?php mvl_foot(); ?>
</body>
</html>

<?php
// moveciamovement.com/academy/teacher-training – The Movecia Method · Mindful Movement Teacher Training (Vivien, 28.10.2026, Runde 2)
// 550 Std. inkl. vollständiger 200-Std.-Yogalehrerausbildung, 11 Monate hybrid, Start Mi 3.11.2027, nur alle 2 Jahre, max. 10 Plätze.
// Ende: Präsenzwochenende September 2028 (Lehrprobe) → Online-Abschlussprüfung mit Kamera → Zertifikat.
// Buchung: 50 % Anzahlung, Rest auf einmal / in 2 / in 3 Zahlungen bis 15.9.2027. Early-Bird € 3.390 bis 31.3.2027, danach € 4.500.
// ?danke=1 nach der Zahlung, ?angebot=TOKEN = persönlicher Kennenlern-Preis aus dem Gespräch (20 Min. Countdown, nie öffentlich).
declare(strict_types=1);
require_once __DIR__ . '/../../assets/icons.inc.php';
require dirname(__DIR__, 3) . '/api/academy-lib.php';
require __DIR__ . '/../../assets/layout.inc.php';
$GLOBALS['mvl_de_only'] = true; // Ausbildung nur auf Deutsch (Vivien, 28.10.2026)
ob_start('mv_strip_en');
header('Cache-Control: no-store');
$pdo = forma_db();
ac_schema($pdo);
try { ac_tick($pdo); } catch (Throwable $e) { error_log('AC tick: ' . $e->getMessage()); }
$c = ac_cfg();
$free = ac_free($pdo) > 0 && $c['open'];
$site = mm_site();
$st = $c['start_ts'];
$offer = ac_offer_by_token($pdo, (string)($_GET['angebot'] ?? ''));
$offerLive = ac_offer_live($offer);
$p = ac_prices($offerLive ? $offer : null);
$mods = ac_modules();
$slots = ac_call_slots($pdo);
$e = fn(int $x) => str_replace('€ ', '€&nbsp;', mv_eur($x));
$ee = fn(int $x) => ac_eur($x, 'en');
$dd = fn(string $ymd) => ac_d($ymd);
$de2 = fn(string $ymd) => ac_d($ymd, 'en');
if ($offer || isset($_GET['danke']) || isset($_GET['abbruch'])) $GLOBALS['mvl_noindex'] = true;
$css = '<link rel="stylesheet" href="/wildhearts/assets/academy.css?v=7">';
mvl_head('Yogalehrerausbildung auf Deutsch · Mindful Movement Teacher Training ' . AC_HOURS . ' Std. | Movecia Academy Tirol',
    'Die ' . AC_HOURS . '-Stunden-Ausbildung mit Vivien Malonga, ausschließlich auf Deutsch: vollständige 200-Stunden-Yogalehrerausbildung nach internationalem Curriculum, Somatic Movement, Breathwork, Anatomie & Body Scanning, Mental Load & Nervensystem. 11 Monate hybrid, online aus Österreich, Deutschland, der Schweiz und Südtirol, Präsenzwochenende in Tirol. Start ' . mv_date($st) . ', nur 10 Plätze.' . ($c['eb'] ? ' Early-Bird bis ' . mv_date($c['eb_end']) . '.' : ''),
    $site . '/academy/teacher-training/', '/img/movecia/og-academy.jpg', $css, ac_jsonld($pdo));
$hours = ac_hours();
$pr = ['lt1' => ['weniger als 1 Jahr', 'less than a year'], '1to2' => ['1–2 Jahre', '1–2 years'], 'gt2' => ['mehr als 2 Jahre', 'more than 2 years'], 'teach' => ['ich unterrichte schon', 'I already teach']];
$wdDe = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$mDe = ['', 'Jän.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'];
$spark = fn(string $cls) => '<svg class="spk ' . $cls . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1c1 6 5 10 11 11-6 1-10 5-11 11-1-6-5-10-11-11 6-1 10-5 11-11z"/></svg>';
// Donut der Stundenverteilung
$donut = (function () use ($hours) {
    $r = 70; $C = 2 * M_PI * $r; $off = 0; $o = '';
    foreach ($hours as $i => [$de, $en, $h, $col]) {
        $len = $C * $h / AC_HOURS;
        $o .= '<circle class="seg" style="--i:' . $i . '" cx="90" cy="90" r="' . $r . '" stroke="' . $col . '" stroke-dasharray="' . round($len - 2.2, 2) . ' ' . round($C, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '"/>';
        $off += $len;
    }
    return '<svg viewBox="0 0 180 180" class="donut" aria-hidden="true"><g transform="rotate(-90 90 90)">' . $o . '</g></svg>';
})();
$plans = ac_plans_open();
$defPlan = isset($plans['two']) ? 'two' : 'full';
$sched = fn(string $k) => ac_schedule($p['total'], $k);
?>
<body class="acad">
<?php mvl_top('academy'); ?>
<p class="en-note">This training is held in German only.</p>
<?= ac_band('Mindful Movement Teacher Training') ?>
<main id="top">
<?php if ($offerLive): ?>
<div class="w"><div class="offerbar" id="offerbar" data-until="<?= (int)$offer['offer_until'] ?>" data-now="<?= time() ?>">
  <span><span class="de">Dein Kennenlern-Preis aus unserem Gespräch:</span><span class="en">Your price from our call:</span> <b><?= $e($p['total']) ?></b> <s style="opacity:.6"><?= $e($c['price']) ?></s></span>
  <span><span class="de">gilt noch</span><span class="en">valid for</span> <span class="cd" id="offercd">–</span></span>
  <a class="btn light sm" href="#buchen"><span class="de">Jetzt Platz sichern</span><span class="en">Book my place</span></a>
</div></div>
<?php endif; ?>

<section class="w"><div class="in hero-x ac-hero two">
  <?= mv_waves(false) ?>
  <div class="ac-hero-tx">
    <?= ac_seal('herom') ?>
    <p class="ac-kick"><?= ac_seal('mini') ?><span><a href="/academy/">Movecia Academy</a><small><span class="de">Teacher Training 2027 · nur alle 2 Jahre</span><span class="en">Teacher Training 2027 · every two years</span></small></span></p>
    <h1>The Movecia <em>Method</em></h1>
    <p class="sub">Mindful Movement Teacher Training</p>
    <p class="lead"><span class="de">Andere begleiten und dabei bei dir selbst bleiben. Eine Ausbildung für Frauen, die Menschen wirklich sehen möchten: zuhören, Körper lesen, das Nervensystem beruhigen. Mit einer vollständigen 200-Stunden-Yogalehrerausbildung inklusive und allem, was ich mir in meinen eigenen Ausbildungen immer gewünscht habe.</span><span class="en">Guide others and stay close to yourself while you do. A training for women who want to truly see people: listening, reading bodies, calming the nervous system. With a complete 200-hour yoga teacher training included, and everything I always wished for in my own trainings.</span></p>
    <div class="facts">
      <span><span class="de"><?= AC_HOURS ?> Stunden inkl. 200 Std. Yoga</span><span class="en"><?= AC_HOURS ?> hours incl. 200 h yoga</span></span>
      <span><span class="de">11 Monate · hybrid</span><span class="en">11 months · hybrid</span></span>
      <span><span class="de">Start <?= mv_date($st) ?></span><span class="en">Starts <?= mv_date($st, 'en') ?></span></span>
      <span><span class="de">ausschließlich auf Deutsch</span></span>
      <span><span class="de">für Österreich, Deutschland, Schweiz &amp; Südtirol</span></span>
      <span class="chip ok"><?= $free ? '<span class="de">Plätze frei</span><span class="en">Places available</span>' : '<span class="de">ausgebucht</span><span class="en">fully booked</span>' ?></span>
    </div>
    <?php if ($c['eb']): ?>
    <a class="ebird" href="#buchen"><?= ac_birds() ?><b>Early Bird</b><span class="eb-p"><?= $e($c['eb_total']) ?></span><span class="eb-d"><span class="de">bis <?= mv_date($c['eb_end']) ?> · danach <?= $e($c['r_total']) ?></span><span class="en">until <?= mv_date($c['eb_end'], 'en') ?> · then <?= $ee($c['r_total']) ?></span></span></a>
    <?php endif; ?>
    <div class="cta"><a class="btn" href="#buchen"><span class="de">Platz sichern</span><span class="en">Book my place</span> →</a><a class="btn line" href="#gespraech"><span class="de">Kennenlerngespräch</span><span class="en">Free intro call</span></a></div>
    <?php if (isset($_GET['danke'])): ?><div class="thanks"><b><span class="de">Konfetti, Konfetti! Du bist dabei ♡</span><span class="en">Confetti, confetti! You're in ♡</span></b><p><span class="de">Deine Konfetti-Mail mit dem Zugang zu deinem Mitgliederbereich ist unterwegs, deine Rechnung kommt gleich hinterher.</span><span class="en">Your confetti email with access to your members area is on its way, with your invoice right behind it.</span></p><p style="margin-top:.7rem"><a class="btn sm" href="/academy/mitglieder/"><span class="de">Zum Mitgliederbereich</span><span class="en">Members area</span></a></p></div><?php endif; ?>
  </div>
  <div class="ac-photo rv">
    <?= $spark('s1') . $spark('s2') . $spark('s3') ?>
    <div class="pic"><img src="/img/movecia/fotos/ev-lunge.jpg?v=1" alt="Vivien in einem tiefen Ausfallschritt mit erhobenen Armen in ihrem hellen Studio unter dem Dachstuhl" fetchpriority="high"></div>
    <?= ac_seal('big') ?>
    <p class="stamp"><small><span class="de">Nur 10 Plätze</span><span class="en">Only 10 places</span></small><span class="de">und nur alle zwei Jahre</span><span class="en">and only every two years</span></p>
  </div>
</div></section>

<div class="ac-ribbon" aria-hidden="true"><div class="rb-in"><?php for ($i = 0; $i < 2; $i++): ?><span><span class="de">nur 10 Plätze</span><span class="en">only 10 places</span></span><i>✦</i><span><span class="de">nur alle 2 Jahre</span><span class="en">only every 2 years</span></span><i>✦</i><span><?= AC_HOURS ?> <span class="de">Stunden</span><span class="en">hours</span></span><i>✦</i><span><span class="de">inkl. 200-Std.-Yogalehrerausbildung</span><span class="en">incl. 200-hour yoga teacher training</span></span><i>✦</i><span><span class="de">Präsenzwochenende in den Tiroler Alpen</span><span class="en">in-person weekend in the Tyrolean Alps</span></span><i>✦</i><?php endfor; ?></div></div>

<section class="band" id="herz"><span class="band-blob b1" aria-hidden="true"></span><span class="band-blob b2" aria-hidden="true"></span><div class="w"><div class="in">
  <p class="k"><span class="de">Das Herzstück</span><span class="en">The heart of it</span></p>
  <blockquote><span class="de">Keine klassische Fitnessausbildung. Eine Ausbildung im Zuhören.</span><span class="en">Not a classic fitness course. A training in listening.</span></blockquote>
  <p class="after"><span class="de">Viele Frauen tragen heute einen vollen Kopf und sehr viel Verantwortung. Wer sie begleitet, braucht mehr als einen guten Trainingsplan: ein feines Gespür für den Menschen, der gerade vor einem steht, und für sich selbst. Genau das lernst du hier.</span><span class="en">Many women today carry a very full head and a great deal of responsibility. Anyone who guides them needs more than a good training plan: a fine sense for the person standing in front of them, and for themselves. That is exactly what you learn here.</span></p>
  <div class="heart">
    <div class="rv"><span class="ib"><?= mv_icon_svg('herz') ?></span><b><span class="de">Mental Load verstehen</span><span class="en">Understanding mental load</span></b><p><span class="de">Was Dauerverantwortung im Körper macht und wie du Frauen dort abholst, wo sie wirklich stehen.</span><span class="en">What constant responsibility does to the body, and how to meet women exactly where they are.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('kreis') ?></span><b><span class="de">Zuhören, anderen und dir</span><span class="en">Listening to others and to yourself</span></b><p><span class="de">Du nimmst wahr, was dein Gegenüber braucht, und spürst gleichzeitig deine eigenen Grenzen.</span><span class="en">You notice what the person in front of you needs while staying aware of your own limits.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('atem') ?></span><b><span class="de">Das Nervensystem beruhigen</span><span class="en">Calming the nervous system</span></b><p><span class="de">Mit Atem, langsamer Bewegung, Berührung und Ritualen schaffst du Momente, in denen Körper und Kopf zur Ruhe kommen.</span><span class="en">With breath, slow movement, touch and rituals you create moments in which body and mind can settle.</span></p></div>
  </div>
</div></div></section>

<section class="w sec tint t1" id="wunsch"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Was du bekommst</span><span class="en">What you get</span></p><h2 style="margin-top:.6rem"><span class="de">Alles, was ich mir selbst <em>gewünscht</em> hätte</span><span class="en">Everything I wish I'd been <em>given</em></span></h2></div>
    <p><span class="de">Du bekommst eine vollständige 200-Stunden-Yogalehrerausbildung inklusive. Und dazu all das, was ich in meinen eigenen Ausbildungen so oft vermisst habe.</span><span class="en">You get a complete 200-hour yoga teacher training included. And on top of it, everything I so often missed in my own trainings.</span></p></div>
  <div class="wish cute-cards">
    <div class="rv"><span class="ib"><?= mv_icon_svg('sonne') ?></span><b><span class="de">Eine vollständige Yogalehrerausbildung</span><span class="en">A complete yoga teacher training</span></b><p><span class="de">200 Stunden nach internationalem Curriculum: Asana, Alignment, Philosophie, Pranayama und Unterrichtspraxis, damit dir nichts fehlt.</span><span class="en">200 hours following an international curriculum: asana, alignment, philosophy, pranayama and teaching practice, so that you miss nothing.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('welle') ?></span><b><span class="de">Somatic Movement &amp; Breathwork</span><span class="en">Somatic movement &amp; breathwork</span></b><p><span class="de">Langsame, spürende Bewegung und Atem, der auffängt. Damit gibst du Menschen etwas, das sie im Alltag wirklich tragen kann.</span><span class="en">Slow, sensing movement and breath that holds you. With it, you give people something that truly carries them through everyday life.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('kerze') ?></span><b><span class="de">Achtsame Berührung &amp; Rituale</span><span class="en">Mindful touch &amp; rituals</span></b><p><span class="de">Wie du sanft und mit Erlaubnis berührst, Halt gibst und kleine Rituale gestaltest, vom Ankommen bis zum Räuchern zum Abschluss einer Stunde.</span><span class="en">How to touch gently and with consent, offer support and create small rituals, from arriving on the mat to a little smoke cleansing at the end of a class.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('spirale') ?></span><b><span class="de">Stunden halten, ohne dich zu verausgaben</span><span class="en">Holding classes without draining yourself</span></b><p><span class="de">Du lernst, für andere da zu sein und deine eigene Energie zu schützen, damit du nach der Stunde nicht leer, sondern erfüllt nach Hause gehst.</span><span class="en">You learn to be there for others while protecting your own energy, so that you leave a class feeling full rather than empty.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('blatt') ?></span><b><span class="de">Faire Preise, ohne Angst</span><span class="en">Fair prices, without fear</span></b><p><span class="de">Was deine Arbeit wert ist, wie du Preise festlegst und dazu stehst, und wie du deine Kurse verkaufst, ohne dich dabei schlecht zu fühlen.</span><span class="en">What your work is worth, how to set your prices and stand by them, and how to sell your classes without feeling awkward about it.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('bluete') ?></span><b><span class="de">Sichtbar werden &amp; deine Selbstständigkeit</span><span class="en">Being seen &amp; your own business</span></b><p><span class="de">Wie die Menschen um dich herum von deiner Arbeit erfahren. Im Modul Selbstständigkeit helfe ich dir persönlich bei Website, Flyern und deinem ersten Angebot.</span><span class="en">How the people around you get to know your work. In the business module I help you personally with your website, flyers and your first offer.</span></p></div>
  </div>
</div></section>

<section class="w sec" id="inhalte"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Inhalte &amp; Stunden</span><span class="en">Content &amp; hours</span></p><h2 style="margin-top:.6rem"><span class="de"><?= AC_HOURS ?> Stunden, die <em>zusammengehören</em></span><span class="en"><?= AC_HOURS ?> hours that <em>belong together</em></span></h2></div>
    <p><span class="de">Die vollständige 200-Stunden-Yogalehrerausbildung ist das Fundament. Alles andere macht daraus die Movecia Method.</span><span class="en">The complete 200-hour yoga teacher training is the foundation. Everything else turns it into the Movecia Method.</span></p></div>
  <div class="comp rv">
    <div class="donut-w"><?= $donut ?><div class="donut-c"><b><?= AC_HOURS ?></b><span><span class="de">Stunden</span><span class="en">hours</span></span><small><span class="de">ca. 10–12 Std. pro&nbsp;Woche</span><span class="en">about 10–12 h a&nbsp;week</span></small></div><?= $spark('s4') ?></div>
    <ul class="legend">
      <?php foreach ($hours as [$de, $en, $h, $col, $ic]): ?>
      <li<?= $h === 60 && str_starts_with($de, 'Anatomie') ? ' class="star"' : '' ?>><span class="dot" style="background:<?= $col ?>"></span><span class="ic"><?= mv_icon_svg($ic) ?></span><span class="t"><span class="de"><?= forma_h($de) ?></span><span class="en"><?= forma_h($en) ?></span></span><span class="h"><?= $h ?> <span class="de">Std.</span><span class="en">h</span></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="anat-row">
    <div class="anat rv">
      <img src="/img/movecia/fotos/ev-savasana.jpg?v=1" alt="" loading="lazy">
      <div class="tx">
        <p class="k"><span class="de">Mir besonders wichtig</span><span class="en">Especially close to my heart</span></p>
        <h3><span class="de">Anatomie &amp; Body Scanning</span><span class="en">Anatomy &amp; body scanning</span></h3>
        <p><span class="de">Wer Menschen begleitet, muss den Körper verstehen. Deshalb bekommt die Anatomie zwei eigene Module und am Schluss ein großes Workbook zum Nachschlagen, das dich über die Ausbildung hinaus begleitet.</span><span class="en">If you guide people, you need to understand the body. That is why anatomy gets two modules of its own and, at the end, a big reference workbook that stays with you long after the training.</span></p>
        <ul>
          <li><span class="de">Haltung und Muskelketten sehen und verstehen</span><span class="en">Seeing and understanding posture and muscle chains</span></li>
          <li><span class="de">Tiefenmuskulatur, Beckenboden und Atmung</span><span class="en">Deep core, pelvic floor and breathing</span></li>
          <li><span class="de">Body Scan: erkennen, was ein Mensch heute braucht</span><span class="en">Body scan: recognising what someone needs today</span></li>
          <li><span class="de">Sicher anpassen bei Beschwerden, mit klaren Grenzen</span><span class="en">Adapting safely for aches and pains, with clear boundaries</span></li>
        </ul>
      </div>
    </div>
    <div class="anat soft2 rv">
      <div class="tx">
        <p class="k"><span class="de">Zwischen den Zeilen</span><span class="en">Between the lines</span></p>
        <h3><span class="de">Bei dir bleiben</span><span class="en">Staying with yourself</span></h3>
        <p><span class="de">Durch alle Module zieht sich eine Frage: Wie kannst du andere begleiten, ohne dich selbst zu verlieren? Du lernst, deine eigenen Grenzen zu spüren, dein Nervensystem nach einer Stunde wieder zu beruhigen und Mental Load nicht nur bei anderen, sondern auch bei dir zu erkennen. Das ist Wissen und Selbstfürsorge, keine Therapie, und genau das macht dich zu einer Begleiterin, der Menschen gern vertrauen.</span><span class="en">One question runs through every module: how can you guide others without losing yourself? You learn to sense your own limits, to settle your nervous system after a class, and to recognise mental load not only in others but in yourself. This is knowledge and self-care, not therapy, and it is exactly what makes you someone people love to trust.</span></p>
      </div>
    </div>
  </div>
</div></section>

<section class="w sec tint t3" id="ablauf"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">So lernst du</span><span class="en">How you learn</span></p><h2 style="margin-top:.6rem"><span class="de">Elf Monate, <em>nie allein</em></span><span class="en">Eleven months, <em>never alone</em></span></h2></div>
    <p><span class="de">Jeden Monat öffnet sich ein neues Modul, immer am 1., nur das erste startet am Mittwoch, <?= mv_date($st) ?>. Plan etwa 10 bis 12 Stunden pro Woche ein, für Videos, Workbook, deine eigene Praxis, den Live-Call und deine Lerngruppe.</span><span class="en">A new module opens every month, always on the 1st; only the first opens on Wednesday, <?= mv_date($st, 'en') ?>. Allow about 10 to 12 hours a week for videos, workbook, your own practice, the live call and your learning group.</span></p></div>
  <div class="how6 cute-cards">
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('sonne') ?></span><b><span class="de">Theorie-Videos</span><span class="en">Theory videos</span></b><p><span class="de">Kompakte Videos zu jedem Thema, mit Kapitelübersicht, auf Handy, Tablet oder Computer.</span><span class="en">Compact videos on every topic, with a chapter overview, on your phone, tablet or computer.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('blatt') ?></span><b><span class="de">Ausführliche Workbooks</span><span class="en">Extensive workbooks</span></b><p><span class="de">Zu jedem Modul ein Workbook zum Nachlesen, Üben und Reflektieren, schön gestaltet und zum Behalten.</span><span class="en">A workbook for every module to read, practise and reflect with, beautifully designed and yours to keep.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('kreis') ?></span><b><span class="de">Jede Woche live</span><span class="en">Live every week</span></b><p><span class="de">Im Live-Call üben wir gemeinsam, besprechen deine Fragen und unterrichten uns gegenseitig. Verpasste Calls werden aufgenommen.</span><span class="en">In the live call we practise together, talk through your questions and teach each other. Missed calls are recorded.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('kerze') ?></span><b><span class="de">Online-Coworking</span><span class="en">Online co-working</span></b><p><span class="de">Zu festen Zeiten lernen wir zusammen im Online-Raum, jede bei sich zu Hause, aber nicht allein. Das trägt durch volle Wochen.</span><span class="en">At set times we study together in our online room, each at home but not alone. It carries you through busy weeks.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('herz') ?></span><b><span class="de">Lerngruppen</span><span class="en">Learning groups</span></b><p><span class="de">In kleinen Gruppen fragt ihr euch gegenseitig ab, übt Sequenzen und unterrichtet euch. Oft entstehen daraus Freundschaften fürs Leben.</span><span class="en">In small groups you quiz each other, practise sequences and teach each other. Often friendships for life grow out of them.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('berg') ?></span><b><span class="de">Deine eigene Praxis</span><span class="en">Your own practice</span></b><p><span class="de">Du bewegst dich selbst weiter und dokumentierst ca. 100 Praxisstunden, damit das Wissen auch in deinem Körper ankommt.</span><span class="en">You keep moving yourself and document around 100 practice hours, so that what you learn settles into your own body too.</span></p></div>
  </div>
  <div class="mods">
    <?php foreach ($mods as $m): ?>
    <div class="mod rv<?= in_array($m['n'], [2, 7], true) ? ' hl' : '' ?><?= $m['n'] === 11 ? ' last' : '' ?>"><span class="n"><?= $m['n'] ?></span><span class="d"><span class="de"><?= $m['n'] === 1 ? mv_date($st) : 'ab ' . ac_month($m['date']) ?></span><span class="en"><?= $m['n'] === 1 ? mv_date($st, 'en') : 'from ' . ac_month($m['date'], 'en') ?></span></span><b><span class="de"><?= forma_h($m['de']) ?></span><span class="en"><?= forma_h($m['en']) ?></span></b></div>
    <?php endforeach; ?>
    <div class="mod wk rv"><span class="d"><span class="de">September 2028</span><span class="en">September 2028</span></span><b><span class="de">Präsenzwochenende in den Tiroler Alpen</span><span class="en">In-person weekend in the Tyrolean Alps</span></b></div>
  </div>
</div></section>

<section class="w sec" id="wochenende"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Der Abschluss</span><span class="en">The finale</span></p><h2 style="margin-top:.6rem"><span class="de">Zusammen in den <em>Bergen</em></span><span class="en">Together in the <em>mountains</em></span></h2></div>
    <p><span class="de">Nach zehn Monaten online kommen wir im September 2028 zusammen. Danach folgen die Prüfung und dein Zertifikat.</span><span class="en">After ten months online, we come together in September 2028. Then come the exam and your certificate.</span></p></div>
  <div class="duo">
    <div class="wkd rv">
      <img src="/img/movecia/fotos/ev-method-foto.jpg?v=1" alt="" loading="lazy" style="object-position:50% 40%">
      <div class="tx">
        <p class="k"><span class="de">Präsenzwochenende · September 2028</span><span class="en">In-person weekend · September 2028</span></p>
        <h3><span class="de">Drei Tage in den Tiroler Alpen</span><span class="en">Three days in the Tyrolean Alps</span></h3>
        <p><span class="de">Von Freitag 8:30 Uhr bis Sonntag 17:30 Uhr üben, unterrichten und lernen wir gemeinsam. Zwei Nächte mit Unterkunft und Verpflegung sind im Preis enthalten. Der genaue Termin und der Ort werden noch bekannt gegeben.</span><span class="en">From Friday 8:30 am to Sunday 5:30 pm we practise, teach and learn together. Two nights with accommodation and meals are included in the price. The exact date and venue will be announced.</span></p>
        <div class="lp"><b><span class="de">Deine Lehrprobe</span><span class="en">Your teaching practice</span></b><p><span class="de">Im Modul Unterrichten &amp; Sequencing entwickelst du deine eigene Einheit von etwa 25 Minuten, und am Wochenende unterrichtest du sie. Aufgeregt? Das ist ganz normal und sogar ein gutes Zeichen. Wir üben vorher viel gemeinsam, deine Lerngruppe kennt deine Stunde schon, und am Wochenende sitzen Frauen vor dir, die dir alles Gute wünschen. Das ist keine Prüfung, sondern dein Moment, in dem alles zusammenkommt.</span><span class="en">In the teaching &amp; sequencing module you create your own session of about 25 minutes, and at the weekend you teach it. Nervous? That is completely normal, and actually a good sign. We practise a lot together beforehand, your learning group already knows your class, and at the weekend you will be teaching women who are rooting for you. It is not an exam; it is your moment, when everything comes together.</span></p></div>
        <div class="facts"><span><span class="de">Fr 8:30 – So 17:30</span><span class="en">Fri 8:30 am – Sun 5:30 pm</span></span><span><span class="de">2 Nächte inklusive</span><span class="en">2 nights included</span></span><span><span class="de">Verpflegung inklusive</span><span class="en">Meals included</span></span></div>
        <p class="must"><span class="de">Die Termine stehen gut ein Jahr im Voraus fest. Bitte plan das Wochenende fix ein, es kann nicht nachgeholt werden.</span><span class="en">The dates are fixed well over a year in advance. Please plan for the weekend, as it cannot be made up later.</span></p>
      </div>
    </div>
    <div class="fin rv">
      <p class="k"><span class="de">Dein Weg zum Zertifikat</span><span class="en">Your path to the certificate</span></p>
      <h3><span class="de">Drei Schritte zum Abschluss</span><span class="en">Three steps to graduation</span></h3>
      <ol>
        <li><span class="ib"><?= mv_icon_svg('berg') ?></span><div><b><span class="de">Präsenzwochenende mit Lehrprobe</span><span class="en">In-person weekend with teaching practice</span></b><p><span class="de">Du unterrichtest deine eigene Einheit von etwa 25 Minuten.</span><span class="en">You teach your own session of about 25 minutes.</span></p></div></li>
        <li><span class="ib"><?= mv_icon_svg('kerze') ?></span><div><b><span class="de">Online-Abschlussprüfung</span><span class="en">Online final exam</span></b><p><span class="de">Nach dem Wochenende beantwortest du zu Hause die Multiple-Choice-Fragen, mit eingeschalteter Kamera in unserem Online-Prüfungsraum.</span><span class="en">After the weekend you answer the multiple-choice questions at home, with your camera on in our online exam room.</span></p></div></li>
        <li><span class="ib"><?= mv_icon_svg('bluete') ?></span><div><b><span class="de">Dein Zertifikat</span><span class="en">Your certificate</span></b><p><span class="de">Mit bestandener Prüfung, dem Präsenzwochenende und ca. 100 dokumentierten Praxisstunden bekommst du dein Zertifikat der Movecia Academy über <?= AC_HOURS ?> Stunden.</span><span class="en">Once you have passed the exam, completed the in-person weekend and documented around 100 practice hours, you receive your <?= AC_HOURS ?>-hour Movecia Academy certificate.</span></p></div></li>
      </ol>
      <div class="room"><img src="/img/movecia/fotos/ev-gathering.jpg?v=1" alt="" loading="lazy"><p><span class="de"><b>Der Prüfungsraum</b> ist wie ein ruhiger Online-Coworking-Raum: Ihr sitzt mit Live-Kamera jeweils bei euch zu Hause, und ich bin die ganze Zeit dabei.</span><span class="en"><b>The exam room</b> is like a quiet online co-working space: each of you sits at home with your camera on, and I am there the whole time.</span></p></div>
    </div>
  </div>
</div></section>

<section class="w sec tint t4" id="weg"><div class="in">
  <div class="rv" style="text-align:center"><p class="k"><span class="de">Dein Weg</span><span class="en">Your path</span></p><h2 style="margin-top:.6rem"><span class="de">Von der eigenen Praxis zum <em>Zertifikat</em></span><span class="en">From your own practice to your <em>certificate</em></span></h2></div>
  <div class="path">
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('welle') ?></span><div><span class="wd"><span class="de">Vorher · empfohlen</span><span class="en">Beforehand · recommended</span></span><b><span class="de">Etwa 1,5 Jahre eigene Praxis</span><span class="en">About 1.5 years of your own practice</span></b><p><span class="de">Mit der <a href="/method/">Method</a>, mit <a href="/unfold/">Unfold</a> oder in den <a href="/gathering/">Gatherings</a> lernst du die Movecia-Art zu bewegen von innen kennen.</span><span class="en">With the <a href="/method/">Method</a>, <a href="/unfold/">Unfold</a> or the <a href="/gathering/">Gatherings</a> you get to know the Movecia way of moving from the inside.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('knospe') ?></span><div><span class="wd"><span class="de">Dein Moment</span><span class="en">Your moment</span></span><b><span class="de">Die Entscheidung</span><span class="en">The decision</span></b><p><span class="de">Du spürst, dass du das, was dir gut tut, weitergeben möchtest.</span><span class="en">You feel that you want to pass on what does you good.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('kreis') ?></span><div><span class="wd"><span class="de">Kostenlos</span><span class="en">Free</span></span><b><span class="de">Kennenlerngespräch</span><span class="en">Intro call</span> <span class="opt"><span class="de">optional</span><span class="en">optional</span></span></b><p><span class="de">30 Minuten per Video, um deine Fragen zu klären.</span><span class="en">30 minutes by video to answer your questions.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('herz') ?></span><div><span class="wd"><span class="de">Online</span><span class="en">Online</span></span><b><span class="de">Buchung &amp; Konfetti</span><span class="en">Booking &amp; confetti</span></b><p><span class="de">Mit der Anzahlung ist dein Platz sicher, und dein Mitgliederbereich öffnet sich sofort.</span><span class="en">Your deposit secures your place, and your members area opens straight away.</span></p></div></div>
    <div class="pst big rv"><span class="dot"><?= mv_icon_svg('sonne') ?></span><div><span class="wd"><?= mv_date($st) ?></span><b><span class="de">Start mit Modul 1</span><span class="en">Module 1 begins</span></b><p><span class="de">Gleich nach den Tiroler Herbstferien geht es los.</span><span class="en">We begin right after the Tyrolean autumn school holidays.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('blatt') ?></span><div><span class="wd"><span class="de">Nov. 2027 – Sept. 2028</span><span class="en">Nov 2027 – Sept 2028</span></span><b><span class="de">Elf Module</span><span class="en">Eleven modules</span></b><p><span class="de">Videos, Workbooks, Coworking, Lerngruppe und jede Woche unser Live-Call.</span><span class="en">Videos, workbooks, co-working, learning group and our live call every week.</span></p></div></div>
    <div class="pst big rv"><span class="dot"><?= mv_icon_svg('berg') ?></span><div><span class="wd"><span class="de">September 2028</span><span class="en">September 2028</span></span><b><span class="de">Präsenzwochenende &amp; Lehrprobe</span><span class="en">In-person weekend &amp; teaching practice</span></b><p><span class="de">Drei Tage zusammen in den Tiroler Alpen.</span><span class="en">Three days together in the Tyrolean Alps.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('bluete') ?></span><div><span class="wd"><span class="de">Danach</span><span class="en">Afterwards</span></span><b><span class="de">Online-Prüfung &amp; Zertifikat</span><span class="en">Online exam &amp; certificate</span></b><p><span class="de">Multiple Choice mit Kamera im Prüfungsraum, dann hältst du dein Zertifikat in der Hand.</span><span class="en">Multiple choice with your camera on in the exam room, then your certificate is in your hands.</span></p></div></div>
  </div>
</div></section>

<section class="w sec tint t2" id="wo"><div class="in wo">
  <div class="wo-t">
    <p class="k">Sprache &amp; Region</p>
    <h2>Auf Deutsch, <em>von überall</em></h2>
    <p class="lead">Die Ausbildung findet ausschließlich auf Deutsch statt. Sie ist für Teilnehmerinnen aus Österreich, Deutschland, der Schweiz und Südtirol gedacht: Module, Live-Calls, Coworking und Lerngruppen machst du online, ganz gleich, wo du wohnst. Nur zum Präsenzwochenende kommen wir in den Tiroler Alpen zusammen.</p>
    <p class="mut">Wenn du eine Yogalehrerausbildung auf Deutsch suchst, die du großteils online machen kannst und die sich trotzdem persönlich und nah anfühlt, bist du hier richtig, egal ob du in Wien, München, Zürich oder Bozen zu Hause bist.</p>
  </div>
  <ul class="wo-l">
    <li><b>🇦🇹 Österreich</b><span>Yogalehrerausbildung in Tirol, hybrid</span></li>
    <li><b>🇩🇪 Deutschland</b><span>online dabei, Wochenende in Tirol</span></li>
    <li><b>🇨🇭 Schweiz</b><span>online dabei, bezahlt wird in Euro</span></li>
    <li><b>Südtirol</b><span>online dabei, Wochenende gleich ums Eck</span></li>
  </ul>
  <?= ac_birds() ?>
</div></section>

<section class="w sec tint t1" id="gespraech"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Kostenlos &amp; unverbindlich</span><span class="en">Free &amp; no obligation</span></p><h2 style="margin-top:.6rem"><span class="de">Lass uns <em>reden</em></span><span class="en">Let's <em>talk</em></span></h2></div>
    <p><span class="de">In 30 Minuten per Video lernen wir uns kennen, und du kannst alles fragen, was dir auf dem Herzen liegt. Das Gespräch ist freiwillig, du kannst auch direkt buchen.</span><span class="en">In 30 minutes by video we get to know each other, and you can ask me anything that is on your mind. The call is optional; you can also book straight away.</span></p></div>
  <div class="callbox rv">
    <div>
      <p class="k"><span class="de">Wunschtermin wählen</span><span class="en">Choose a time</span></p>
      <p class="mut" style="margin-top:.4rem;font-size:.95rem"><span class="de">Mittwoch, Donnerstag und Freitag um 8:00 Uhr, Mittwoch und Donnerstag auch um 18:30 Uhr. <?= mm_whereby_key() !== '' ? 'Du bekommst sofort die Bestätigung mit dem Link zum Videoraum.' : 'Ich bestätige dir den Termin per E-Mail.' ?></span><span class="en">Wednesday, Thursday and Friday at 8:00 am, and Wednesday and Thursday at 6:30 pm too (Vienna time). I will confirm the time by email.</span></p>
      <div class="slots" id="slots" role="group" aria-label="Termine">
        <?php foreach (array_slice($slots, 0, 24) as $s): $t = strtotime($s['date']); ?>
        <button type="button" class="slot" data-d="<?= $s['date'] ?>" data-s="<?= $s['slot'] ?>" aria-pressed="false"><span class="de"><?= $wdDe[(int)date('N', $t)] ?>, <?= date('j', $t) ?>. <?= $mDe[(int)date('n', $t)] ?></span><span class="en"><?= date('D j M', $t) ?></span><small><span class="de"><?= $s['slot'] ?> Uhr</span><span class="en"><?= date('g:i a', strtotime('2000-01-01 ' . $s['slot'])) ?></span></small></button>
        <?php endforeach; ?>
        <?php if (!$slots): ?><p class="mut"><span class="de">Gerade ist kein Termin frei. Schreib mir einfach an <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a>.</span><span class="en">No times are free right now. Just write to me at <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a>.</span></p><?php endif; ?>
      </div>
    </div>
    <form class="form" id="cf" novalidate>
      <label class="f"><span><span class="de">Dein Name</span><span class="en">Your name</span></span><input type="text" name="name" autocomplete="name" required maxlength="80"></label>
      <label class="f"><span>E-Mail</span><input type="email" name="email" autocomplete="email" required maxlength="160"></label>
      <label class="f"><span><span class="de">Telefon (freiwillig)</span><span class="en">Phone (optional)</span></span><input type="text" name="phone" autocomplete="tel" maxlength="40" inputmode="tel"></label>
      <label class="f"><span><span class="de">Was möchtest du mich fragen? (freiwillig)</span><span class="en">What would you like to ask me? (optional)</span></span><textarea name="note" maxlength="600"></textarea></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <p class="mut" style="font-size:.82rem"><span class="de">Deine Angaben nutze ich nur für unser Gespräch, mehr dazu im <a href="/terms/#datenschutz">Datenschutz</a>.</span><span class="en">I only use your details for our call; see the <a href="/terms/#datenschutz">privacy notice</a>.</span></p>
      <div><button class="btn" type="submit" id="cb"><?= mm_whereby_key() !== '' ? 'Termin buchen' : 'Termin anfragen' ?></button></div>
      <p class="err" id="ce" hidden></p>
      <div class="okmsg" id="co" hidden></div>
    </form>
  </div>
</div></section>

<section class="w sec tint t2" id="liste"><div class="in liste">
  <div class="liste-t rv">
    <p class="k"><?= $free ? 'Noch nicht so weit?' : 'Ausgebucht' ?></p>
    <h2><?= $free ? 'Setz deinen Namen <em>auf die Liste</em>' : 'Trag dich auf die <em>Warteliste</em> ein' ?></h2>
    <p class="lead"><?= $free ? 'Du spürst, dass dich die Ausbildung ruft, aber du möchtest noch in Ruhe überlegen? Trag dich ein, dann schicke ich dir alle Infos und sag dir rechtzeitig Bescheid, bevor der Early-Bird-Preis endet.' : 'Gerade sind alle zehn Plätze vergeben. Trag dich ein, dann erfährst du als Erste, wenn ein Platz frei wird, und bist bei der nächsten Runde ganz vorne dabei.' ?></p>
    <?= ac_birds() ?>
  </div>
  <form class="form liste-f rv" id="lf" novalidate>
    <?php if (isset($_GET['liste'])): ?><div class="okmsg">Danke, deine E-Mail ist bestätigt ♡ Die Infos sind schon in deinem Postfach.</div><?php endif; ?>
    <label class="f"><span>Vorname</span><input type="text" name="name" autocomplete="given-name" required maxlength="60"></label>
    <label class="f"><span>E-Mail</span><input type="email" name="email" autocomplete="email" required maxlength="160"></label>
    <fieldset><legend>Wie lange praktizierst du schon? (freiwillig)</legend>
      <div class="prc"><?php foreach ($pr as $k => [$de, $en]): ?><label><input type="radio" name="practice" value="<?= $k ?>"><span><?= $de ?></span></label><?php endforeach; ?></div>
    </fieldset>
    <label class="chk"><input type="checkbox" name="consent" value="1"><span>Ja, schick mir Infos zum Teacher Training per E-Mail. Abmelden geht jederzeit mit einem Klick (<a href="/terms/#datenschutz">Datenschutz</a>).</span></label>
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div><button class="btn" type="submit" id="lb"><?= $free ? 'Auf die Liste setzen' : 'Auf die Warteliste' ?></button></div>
    <p class="err" id="le" hidden></p>
    <div class="okmsg" id="lo" hidden>Fast geschafft: Ich habe dir gerade eine E-Mail geschickt. Bitte bestätige kurz deine Adresse, dann kommen alle Infos.</div>
  </form>
</div></section>

<section class="w sec" id="buchen"><div class="in">
  <div class="pricebox">
    <?= $spark('s5') . $spark('s6') ?>
    <div>
      <p class="k"><span class="de">Dein Platz</span><span class="en">Your place</span></p>
      <h2 style="margin-top:.5rem"><span class="de">Einer von nur zehn</span><span class="en">One of only ten</span></h2>
      <?php if (!empty($p['call'])): ?><p class="ebird on-plum"><?= ac_birds() ?><b>Kennenlern-Preis</b><span class="eb-p"><?= $e($p['total']) ?></span><span class="eb-d"><span class="de">nur für dich, aus unserem Gespräch · statt <?= $e($c['price']) ?></span></span></p>
      <?php elseif ($p['eb']): ?><p class="ebird on-plum"><?= ac_birds() ?><b>Early Bird</b><span class="eb-p"><?= $e($p['total']) ?></span><span class="eb-d"><span class="de"><?= 'bis ' . mv_date($c['eb_end']) . ' · danach ' . $e($c['r_total']) ?></span><span class="en"><?= 'until ' . mv_date($c['eb_end'], 'en') . ' · then ' . $ee($c['r_total']) ?></span></span></p>
      <?php else: ?><p class="amt"><?= $e($p['total']) ?></p><?php endif; ?>
      <ul class="incl">
        <li><span class="de"><?= AC_HOURS ?> Stunden inkl. vollständiger 200-Std.-Yogalehrerausbildung</span><span class="en"><?= AC_HOURS ?> hours incl. a complete 200-hour yoga teacher training</span></li>
        <li><span class="de">11 Module mit Videos und ausführlichen Workbooks</span><span class="en">11 modules with videos and extensive workbooks</span></li>
        <li><span class="de">Wöchentlicher Live-Call, Coworking und Lerngruppe</span><span class="en">Weekly live call, co-working and learning group</span></li>
        <li><span class="de">Präsenzwochenende mit 2 Nächten, Unterkunft und Verpflegung</span><span class="en">In-person weekend with 2 nights, accommodation and meals</span></li>
        <li><span class="de">Mitgliederbereich ab sofort, mit Chat direkt zu mir</span><span class="en">Members area from day one, with a chat straight to me</span></li>
        <li><span class="de">Online-Abschlussprüfung und Zertifikat der Movecia Academy</span><span class="en">Online final exam and Movecia Academy certificate</span></li>
      </ul>
      <div class="ebn"><b><span class="de">So zahlst du</span><span class="en">How you pay</span></b><span class="de">Bei der Buchung zahlst du 50 % an, den Rest auf einmal oder in Teilen bis <?= $dd($c['due_final']) ?>, ohne Aufschlag. Dein Preis bleibt so, wie er bei der Buchung war. Etwa zehn Tage vor jeder Zahlung bekommst du einen Bezahl-Link. Innerhalb von 14 Tagen nach der Buchung kannst du ohne Angabe von Gründen zurücktreten und bekommst alles zurück.</span><span class="en">When you book, you pay a 50 % deposit, and the rest at once or in parts by <?= $de2($c['due_final']) ?>, with no surcharge. Your price stays exactly as it was when you booked. About ten days before each payment you get a payment link. Within 14 days of booking you can withdraw without giving a reason and get everything back.</span></div>
    </div>
    <form class="bookf" id="bf" novalidate>
      <?php if ($free): ?>
      <p class="k"><span class="de">Anmeldung</span><span class="en">Registration</span></p>
      <fieldset><legend><span class="de">Wie möchtest du zahlen?</span><span class="en">How would you like to pay?</span></legend>
      <div class="payplans" role="radiogroup">
        <?php foreach ($plans as $k => $pl): $s = $sched($k); ?>
        <label class="pp"><input type="radio" name="plan" value="<?= $k ?>"<?= $k === $defPlan ? ' checked' : '' ?>><span class="pp-h"><span class="de"><?= $pl['de'] ?></span><span class="en"><?= $pl['en'] ?></span><?= $k === 'two' && count($plans) > 2 ? '<em><span class="de">beliebt</span><span class="en">popular</span></em>' : '' ?></span>
          <span class="pp-rows"><?php foreach ($s as $x): ?><span class="pp-r"><span class="pp-w"><?= $x['due'] === '' ? '<span class="de">heute</span><span class="en">today</span>' : '<span class="de">bis ' . date('j.n.Y', strtotime($x['due'])) . '</span><span class="en">by ' . date('j M Y', strtotime($x['due'])) . '</span>' ?></span><b><span class="de"><?= $e((int)$x['cents']) ?></span><span class="en"><?= $ee((int)$x['cents']) ?></span></b></span><?php endforeach; ?></span></label>
        <?php endforeach; ?>
      </div>
      <p class="pp-note"><span class="de">♡ Je früher du buchst, desto mehr Zahlungsmöglichkeiten hast du: bis 15. Juni 2027 in drei Teilen, bis 31. August 2027 in zwei, danach auf einmal.</span><span class="en">♡ The earlier you book, the more ways you have to pay: in three parts until 15 June 2027, in two until 31 August 2027, and in one go after that.</span></p></fieldset>
      <label class="f"><span><span class="de">Vor- und Nachname</span><span class="en">Full name</span></span><input type="text" name="name" autocomplete="name" required maxlength="80"></label>
      <label class="f"><span>E-Mail</span><input type="email" name="email" autocomplete="email" required maxlength="160"></label>
      <label class="f"><span><span class="de">Telefon</span><span class="en">Phone</span></span><input type="text" name="phone" autocomplete="tel" required maxlength="40" inputmode="tel"></label>
      <fieldset><legend><span class="de">Wie lange bewegst du dich schon achtsam (Yoga, Method, Unfold …)?</span><span class="en">How long have you been practising mindful movement (yoga, the Method, Unfold …)?</span></legend>
        <div class="prc"><?php foreach ($pr as $k => [$de, $en]): ?><label><input type="radio" name="practice" value="<?= $k ?>"><span><span class="de"><?= $de ?></span><span class="en"><?= $en ?></span></span></label><?php endforeach; ?></div>
      </fieldset>
      <label class="f"><span><span class="de">Erzähl mir kurz von deiner Praxis (freiwillig)</span><span class="en">Tell me a little about your practice (optional)</span></span><textarea name="practice_note" maxlength="800" style="min-height:80px"></textarea></label>
      <label class="f"><span><span class="de">Warum möchtest du die Ausbildung machen?</span><span class="en">Why would you like to do the training?</span></span><textarea name="why" maxlength="1500" required></textarea></label>
      <label class="chk"><input type="checkbox" name="consent" value="1"><span><span class="de">Ich habe die <a href="/terms/#teacher" target="_blank">Bedingungen</a> gelesen: 14 Tage Rücktrittsrecht ab Buchung, danach die Storno-Staffel (bis 30.6.2027 25 %, bis 15.9.2027 50 %, danach der volle Preis), Ersatzteilnehmerin jederzeit möglich. Das Präsenzwochenende im September 2028 ist ein fester Termin, der nicht nachgeholt und bei Nichtteilnahme nicht erstattet wird.</span><span class="en">I have read the <a href="/terms/#teacher" target="_blank">terms</a>: a 14-day right of withdrawal from booking, then the cancellation scale (25 % until 30 June 2027, 50 % until 15 September 2027, the full price after that), and I can name someone to take my place at any time. The in-person weekend in September 2028 is a fixed date that cannot be made up and is not refunded if I don't attend.</span></span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div><button class="btn" type="submit" id="bb"><span class="de">Anzahlung leisten &amp; Platz sichern</span><span class="en">Pay my deposit &amp; book</span> →</button></div>
      <p class="mut" style="font-size:.82rem"><span class="de">Sichere Zahlung über Stripe (gemeinsames Konto von FORMA &amp; Movecia).</span><span class="en">Secure payment with Stripe (shared FORMA &amp; Movecia account).</span></p>
      <p class="err" id="be" hidden></p>
      <?php if (isset($_GET['abbruch'])): ?><p class="note"><span class="de">Die Zahlung wurde abgebrochen, es wurde nichts abgebucht.</span><span class="en">The payment was cancelled; nothing was charged.</span></p><?php endif; ?>
      <?php else: ?>
      <p class="k"><span class="de">Ausgebucht</span><span class="en">Fully booked</span></p>
      <p>Alle zehn Plätze sind vergeben. Trag dich auf die Warteliste ein, dann erfährst du als Erste, wenn ein Platz frei wird.</p>
      <p><a class="btn" href="#liste">Auf die Warteliste</a></p>
      <?php endif; ?>
    </form>
  </div>
</div></section>

<section class="w sec"><div class="in me2">
  <img src="/img/movecia/fotos/ev-profil.jpg?v=1" alt="Vivien beim Seitstretch im Sitzen" loading="lazy">
  <div class="rv">
    <p class="k"><span class="de">Deine Ausbilderin</span><span class="en">Your trainer</span></p>
    <h2 style="margin-top:.6rem"><span class="de">Hallo, ich bin <em>Vivien</em></span><span class="en">Hi, I'm <em>Vivien</em></span></h2>
    <p style="margin-top:1rem"><span class="de">Ich unterrichte seit 2021 und habe rund 700 Stunden Yogalehrerausbildung und Bewegungslehre sowie knapp 400 Stunden Rehabilitation, Prävention und Gesundheitstraining hinter mir, dazu Weiterbildungen in somatischer Bewegung, Nervensystem-Regulation und prä- und postnatalem Training. Als Mama von vier Kindern weiß ich, wie sich ein voller Kopf anfühlt. Diese Ausbildung ist mein Herzensprojekt: Ich habe alles hineingepackt, was ich mir selbst immer gewünscht hätte.</span><span class="en">I have been teaching since 2021, with around 700 hours of yoga teacher training and movement education and almost 400 hours of rehabilitation, prevention and health training behind me, plus further training in somatic movement, nervous system regulation and pre- and postnatal training. As a mother of four, I know what a full head feels like. This training is my heart project: I have put into it everything I always wished I'd had myself.</span></p>
    <p class="hand"><span class="de">Ich freu mich auf dich.</span><span class="en">I can't wait to meet you.</span></p>
  </div>
</div></section>

<section class="w sec tint t3" id="faq"><div class="in fq">
  <p class="k"><span class="de">Fragen</span><span class="en">Questions</span></p>
  <h2 style="margin:.6rem 0 1rem"><span class="de">Gut zu <em>wissen</em></span><span class="en">Good to <em>know</em></span></h2>
  <div class="cute-faq">
  <?php foreach (ac_faq() as [$qde, $qen, $ade, $aen]): ?>
  <details class="q"><summary><span class="de"><?= forma_h($qde) ?></span><span class="en"><?= forma_h($qen) ?></span></summary><p><span class="de"><?= forma_h($ade) ?></span><span class="en"><?= forma_h($aen) ?></span></p></details>
  <?php endforeach; ?>
  <details class="q"><summary><span class="de">Kann ich zurücktreten oder stornieren?</span><span class="en">Can I withdraw or cancel?</span></summary><p><span class="de">Innerhalb von 14 Tagen nach der Buchung kannst du ohne Angabe von Gründen zurücktreten und bekommst alles zurück. Danach gilt: bis 30. Juni 2027 beträgt die Stornogebühr 25 % des Gesamtpreises, vom 1. Juli bis 15. September 2027 50 %, danach ist der volle Preis fällig. Du kannst jederzeit kostenlos eine Ersatzteilnehmerin nennen. Muss ich absagen, bekommst du alles zurück. Für den Krankheitsfall empfehle ich eine Seminar- oder Reisestornoversicherung. Alle Details stehen in den <a href="/terms/#teacher">Bedingungen</a>.</span><span class="en">Within 14 days of booking you can withdraw without giving a reason and get everything back. After that: until 30 June 2027 the cancellation fee is 25 % of the total price, from 1 July to 15 September 2027 it is 50 %, and after that the full price is due. You can name someone to take your place at any time at no cost. If I have to cancel, you get everything back. For illness, I recommend a seminar or travel cancellation insurance. All the details are in the <a href="/terms/#teacher">terms</a>.</span></p></details>
  </div>
</div></section>
</main>
<?php mvl_foot(); ?>
<script>
(function(){
  var T=Date.now(),L=function(){return document.documentElement.dataset.l==='en'?'en':'de'};
  var ob=document.getElementById('offerbar');
  if(ob){var until=+ob.dataset.until*1000,skew=Date.now()-(+ob.dataset.now*1000),cd=document.getElementById('offercd');
    var tick=function(){var ms=until-(Date.now()-skew);if(ms<=0){cd.textContent='0:00';setTimeout(function(){location.href=location.pathname+(L()==='en'?'?lang=en':'')+'#buchen'},1500);return}var s=Math.ceil(ms/1000),m=Math.floor(s/60);cd.textContent=m+':'+String(s%60).padStart(2,'0');setTimeout(tick,500)};tick();}
  var angebot=new URLSearchParams(location.search).get('angebot')||'';
  var pick=null;document.querySelectorAll('#slots .slot').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('#slots .slot').forEach(function(x){x.setAttribute('aria-pressed','false')});b.setAttribute('aria-pressed','true');pick=b})});
  var cf=document.getElementById('cf'),ce=document.getElementById('ce'),co=document.getElementById('co'),cb=document.getElementById('cb');
  cf.addEventListener('submit',async function(ev){ev.preventDefault();ce.hidden=true;var en=L()==='en';
    if(!pick){ce.textContent=en?'Please choose a time on the left.':'Bitte wähl zuerst einen Termin.';ce.hidden=false;return}
    if(cf.name.value.trim().length<2||cf.email.value.indexOf('@')<1){ce.textContent=en?'Please enter your name and email.':'Bitte gib deinen Namen und deine E-Mail an.';ce.hidden=false;return}
    var d=Object.fromEntries(new FormData(cf));d.action='call';d.date=pick.dataset.d;d.slot=pick.dataset.s;d.lang=L();d.t=T;cb.disabled=true;
    try{var r=await fetch('/api/academy.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      if(j.ok){cf.querySelectorAll('label,button').forEach(function(x){x.hidden=true});co.textContent=j.fixed?('Wunderbar, unser Gespräch ist fix: '+j.when+'. Die Bestätigung mit dem Link zum Videoraum ist schon in deinem Postfach ♡'):('Danke! Dein Wunschtermin: '+j.when+'. Ich bestätige ihn dir in Kürze per E-Mail.');co.hidden=false;pick.disabled=true;return}
      ce.textContent=j.error||'…';ce.hidden=false}catch(x){ce.textContent=en?'Something went wrong. Please try again.':'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.';ce.hidden=false}
    cb.disabled=false});
  var bf=document.getElementById('bf'),be=document.getElementById('be'),bb=document.getElementById('bb');
  if(bb)bf.addEventListener('submit',async function(ev){ev.preventDefault();be.hidden=true;var en=L()==='en',err=[];
    if(bf.name.value.trim().length<2||bf.email.value.indexOf('@')<1)err.push(en?'Please enter your name and email.':'Bitte gib deinen Namen und deine E-Mail an.');
    if(bf.phone.value.replace(/\D/g,'').length<6)err.push(en?'Please enter your phone number.':'Bitte gib deine Telefonnummer an.');
    if(!bf.querySelector('input[name=practice]:checked'))err.push(en?'Please tell me how long you have been practising.':'Bitte sag mir, wie lange du schon praktizierst.');
    if(bf.why.value.trim().length<10)err.push(en?'Please write a few words about why you want to do the training.':'Bitte schreib mir ein paar Sätze, warum du die Ausbildung machen möchtest.');
    if(!bf.consent.checked)err.push(en?'Please tick the box about the terms.':'Bitte setz das Häkchen zu den Bedingungen.');
    if(err.length){be.textContent=err.join(' ');be.hidden=false;return}
    var d=Object.fromEntries(new FormData(bf));d.action='book';d.lang=L();d.t=T;d.angebot=angebot;bb.disabled=true;
    try{var r=await fetch('/api/academy.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      if(j.url){location.href=j.url;return}be.textContent=j.error||'…';be.hidden=false}catch(x){be.textContent=en?'Something went wrong. Please try again.':'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.';be.hidden=false}
    bb.disabled=false});
})();
</script>
<script>
(function(){var f=document.getElementById('lf');if(!f)return;var t0=Date.now();
f.addEventListener('submit',function(e){e.preventDefault();var b=document.getElementById('lb'),er=document.getElementById('le'),ok=document.getElementById('lo');er.hidden=true;
 var d={name:f.name.value.trim(),email:f.email.value.trim(),practice:(f.querySelector('[name=practice]:checked')||{}).value||'',consent:f.consent.checked?1:0,website:f.website.value,elapsed:Date.now()-t0,source:'academy',lang:'de'};
 b.disabled=true;fetch('/api/wh-subscribe.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}).then(function(r){return r.json()}).then(function(j){b.disabled=false;
  if(j.ok){ok.hidden=false;if(j.already)ok.textContent='Du bist schon auf der Liste ♡ Ich habe dir die Infos gerade noch einmal geschickt.';f.querySelectorAll('label.f,fieldset,label.chk').forEach(function(x){x.style.display='none'});b.parentNode.style.display='none'}
  else{er.textContent=j.error||'Das hat nicht geklappt. Bitte versuch es noch einmal.';er.hidden=false}}).catch(function(){b.disabled=false;er.textContent='Bitte prüf deine Verbindung.';er.hidden=false})});
})();
</script>
</body>
</html>

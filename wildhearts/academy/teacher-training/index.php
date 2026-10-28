<?php
// moveciamovement.com/academy/teacher-training – The Movecia Method · Mindful Movement Teacher Training (Vivien, 28.10.2026)
// 500 Std. inkl. 200-Std.-Yogalehrerausbildung, 11 Monate hybrid, Start Mi 3.11.2027, Präsenzwochenende September 2028,
// danach Online-Abschlussprüfung mit Kamera, dann Zertifikat. Max. 10 Plätze. Early-Bird € 3.390 bis 31.5.2027, danach € 4.500.
// ?danke=1 nach der Zahlung, ?angebot=TOKEN = persönliches Angebot aus dem Kennenlerngespräch (Countdown, Early-Bird-Preis).
declare(strict_types=1);
require_once __DIR__ . '/../../assets/icons.inc.php';
require dirname(__DIR__, 3) . '/api/academy-lib.php';
require __DIR__ . '/../../assets/layout.inc.php';
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
$wu = ac_withdraw_until(['paid_at' => date('c')]);
if ($offer || isset($_GET['danke']) || isset($_GET['abbruch'])) $GLOBALS['mvl_noindex'] = true;
$css = '<link rel="stylesheet" href="/wildhearts/assets/academy.css?v=1">';
mvl_head('Mindful Movement Teacher Training · Yogalehrerausbildung 500 Std. hybrid, Tirol | Movecia Academy',
    'The Movecia Method: 500-Stunden-Ausbildung mit Vivien Malonga, inkl. Yogalehrerausbildung nach internationalem 200-Stunden-Curriculum, Somatic Movement, Breathwork, Anatomie & Body Scanning, Mental Load & Nervensystem. 11 Monate hybrid (online + Präsenzwochenende in Tirol), Start ' . mv_date($st) . ', max. ' . $c['seats'] . ' Plätze.' . ($c['eb'] ? ' Early-Bird bis ' . mv_date($c['eb_end']) . '.' : ''),
    $site . '/academy/teacher-training/', '/img/movecia/og-academy.jpg', $css, ac_jsonld($pdo));
$hours = [
    ['Yoga', 'Yoga', 200, 'Vollständige Yogalehrerausbildung nach internationalem 200-Stunden-Curriculum: Asana, Alignment, Philosophie, Pranayama, Unterrichtspraxis.', 'A complete yoga teacher training following an international 200-hour curriculum: asana, alignment, philosophy, pranayama, teaching practice.', ''],
    ['Somatic Movement', 'Somatic movement', 80, 'Langsame, spürende Bewegung, die Spannung löst und Menschen wieder in Kontakt mit ihrem Körper bringt.', 'Slow, sensing movement that releases tension and brings people back into contact with their bodies.', ''],
    ['Breathwork & Meditation', 'Breathwork & meditation', 60, 'Atemtechniken und Meditationen sicher anleiten, für Ruhe, Energie und Konzentration.', 'Guiding breathing techniques and meditations safely, for calm, energy and focus.', ''],
    ['Movecia Movement Method & Bodyweight', 'Movecia Movement Method & bodyweight', 60, 'Meine Methode: fließende Bewegung und Training mit dem eigenen Körpergewicht für eine starke, tiefe Mitte.', 'My method: flowing movement and bodyweight training for a strong, deep core.', ''],
    ['Anatomie & Body Scanning', 'Anatomy & body scanning', 50, 'Den Körper verstehen und lesen lernen: Haltung, Muskelketten, Tiefenmuskulatur und wie du im Body Scan erkennst, was ein Mensch gerade braucht.', 'Understanding and reading the body: posture, muscle chains, the deep core and how a body scan shows you what someone needs right now.', 'star'],
    ['Mental Load & Nervensystem', 'Mental load & the nervous system', 30, 'Was Stress und Verantwortung im Körper machen und wie Bewegung, Atem und Rituale das Nervensystem beruhigen. Wissen und Selbstfürsorge, keine Therapie.', 'What stress and responsibility do to the body and how movement, breath and rituals calm the nervous system. Knowledge and self-care, not therapy.', ''],
    ['Aufbau deiner Selbstständigkeit', 'Building your own business', 20, 'Vom ersten Kurs bis zu Website, Flyern und eigenem Angebot: Wir setzen deine Ideen gemeinsam um.', 'From your first class to your website, flyers and your own offer: we bring your ideas to life together.', ''],
];
$pr = ['lt1' => ['weniger als 1 Jahr', 'less than a year'], '1to2' => ['1–2 Jahre', '1–2 years'], 'gt2' => ['mehr als 2 Jahre', 'more than 2 years'], 'teach' => ['ich unterrichte schon', 'I already teach']];
$wdDe = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$mDe = ['', 'Jän.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'];
?>
<body>
<?php mvl_top('academy'); ?>
<main>
<?php if ($offerLive): ?>
<div class="w"><div class="offerbar" id="offerbar" data-until="<?= (int)$offer['offer_until'] ?>" data-now="<?= time() ?>">
  <span><span class="de">Dein Angebot aus unserem Gespräch:</span><span class="en">Your offer from our call:</span> <b><?= $e($p['once']) ?></b><?= !$c['eb'] ? ' <s style="opacity:.6">' . $e($c['r_once']) . '</s>' : '' ?></span>
  <span><span class="de">gilt noch</span><span class="en">valid for</span> <span class="cd" id="offercd">–</span></span>
  <a class="btn light sm" href="#buchen"><span class="de">Jetzt Platz sichern</span><span class="en">Book my place</span></a>
</div></div>
<?php endif; ?>

<section class="w"><div class="in hero-x ac-hero two">
  <?= mv_waves(false) ?>
  <div>
    <p class="k"><a href="/academy/" style="text-decoration:none">Movecia Academy</a> · <span class="de">Ausbildung</span><span class="en">Training</span></p>
    <h1>The Movecia <em>Method</em></h1>
    <p class="sub">Mindful Movement Teacher Training</p>
    <p class="lead"><span class="de">Eine Ausbildung für Frauen, die Menschen wirklich begleiten möchten. Du lernst, zuzuhören, Körper zu lesen und das Nervensystem zu beruhigen, und du bekommst dabei eine vollständige Yogalehrerausbildung. 500 Stunden, elf Monate, online und in den Tiroler Alpen.</span><span class="en">A training for women who want to truly accompany people. You learn to listen, to read bodies and to calm the nervous system, and along the way you complete a full yoga teacher training. 500 hours over eleven months, online and in the Tyrolean Alps.</span></p>
    <div class="facts">
      <span><span class="de">500 Stunden inkl. 200 Std. Yoga</span><span class="en">500 hours incl. 200 h yoga</span></span>
      <span><span class="de">11 Monate · hybrid</span><span class="en">11 months · hybrid</span></span>
      <span><span class="de">Start <?= mv_date($st) ?></span><span class="en">Starts <?= mv_date($st, 'en') ?></span></span>
      <span><span class="de">max. <?= (int)$c['seats'] ?> Plätze</span><span class="en">max. <?= (int)$c['seats'] ?> places</span></span>
      <span class="<?= $free ? 'chip ok' : '' ?>" style="<?= $free ? 'display:inline-flex;gap:.45em;align-items:center' : '' ?>"><?= $free ? '<span class="de">Plätze frei</span><span class="en">Places available</span>' : '<span class="de">ausgebucht</span><span class="en">fully booked</span>' ?></span>
    </div>
    <?php if ($c['eb']): ?><p class="ebadge"><b>Early Bird</b><span><span class="de"><?= $e($c['eb_once']) ?> statt <?= $e($c['r_once']) ?> · bis <?= mv_date($c['eb_end']) ?></span><span class="en"><?= $ee($c['eb_once']) ?> instead of <?= $ee($c['r_once']) ?> · until <?= mv_date($c['eb_end'], 'en') ?></span></span></p><?php endif; ?>
    <div class="cta"><a class="btn" href="#buchen"><span class="de">Platz sichern</span><span class="en">Book my place</span> →</a><a class="btn line" href="#gespraech"><span class="de">Kennenlerngespräch</span><span class="en">Free intro call</span></a></div>
    <?php if (isset($_GET['danke'])): ?><div class="thanks"><b><span class="de">Danke, du bist dabei ♡</span><span class="en">Thank you, you're in ♡</span></b><p><span class="de">Deine Bestätigung ist per E-Mail unterwegs, deine Rechnung kommt gleich hinterher. Wir starten am <?= mv_date($st) ?>.</span><span class="en">Your confirmation is on its way by email, with your invoice right behind it. We begin on <?= mv_date($st, 'en') ?>.</span></p></div><?php endif; ?>
  </div>
  <div class="pic rv"><img src="/img/movecia/fotos/ev-lunge.jpg?v=1" alt="Vivien in einem tiefen Ausfallschritt mit erhobenen Armen in ihrem hellen Studio unter dem Dachstuhl" fetchpriority="high"><p class="stamp"><small><span class="de">Start</span><span class="en">Begins</span></small><span class="de"><?= mv_date($st) ?>, gleich nach den Herbstferien</span><span class="en"><?= mv_date($st, 'en') ?>, right after the autumn school holidays</span></p></div>
</div></section>

<section class="band" id="herz"><span class="band-blob b1" aria-hidden="true"></span><span class="band-blob b2" aria-hidden="true"></span><div class="w"><div class="in">
  <p class="k"><span class="de">Das Herzstück</span><span class="en">The heart of it</span></p>
  <blockquote><span class="de">Keine klassische Fitnessausbildung. Eine Ausbildung im Zuhören.</span><span class="en">Not a classic fitness course. A training in listening.</span></blockquote>
  <p class="after"><span class="de">Viele Frauen tragen heute einen vollen Kopf und sehr viel Verantwortung. Wer sie begleitet, braucht mehr als einen guten Trainingsplan: ein feines Gespür für den Menschen, der gerade vor einem steht, und für sich selbst.</span><span class="en">Many women today carry a very full head and a great deal of responsibility. Anyone who guides them needs more than a good training plan: a fine sense for the person standing in front of them, and for themselves.</span></p>
  <div class="heart">
    <div class="rv"><span class="ib"><?= mv_icon_svg('herz') ?></span><b><span class="de">Mental Load verstehen</span><span class="en">Understanding mental load</span></b><p><span class="de">Du lernst, was Dauerverantwortung im Körper macht, und wie du Frauen dort abholst, wo sie wirklich stehen.</span><span class="en">You learn what constant responsibility does to the body and how to meet women exactly where they are.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('kreis') ?></span><b><span class="de">Zuhören, anderen und dir</span><span class="en">Listening to others and to yourself</span></b><p><span class="de">Du nimmst wahr, was dein Gegenüber braucht, und spürst gleichzeitig deine eigenen Grenzen.</span><span class="en">You notice what the person in front of you needs while staying aware of your own limits.</span></p></div>
    <div class="rv"><span class="ib"><?= mv_icon_svg('atem') ?></span><b><span class="de">Das Nervensystem beruhigen</span><span class="en">Calming the nervous system</span></b><p><span class="de">Mit Atem, langsamer Bewegung und Ritualen schaffst du Momente, in denen Körper und Kopf zur Ruhe kommen.</span><span class="en">With breath, slow movement and rituals you create moments in which body and mind can settle.</span></p></div>
  </div>
  <div class="notx">
    <div class="no"><b><span class="de">Wofür die Ausbildung nicht gedacht ist</span><span class="en">What this training is not about</span></b><span class="de">Starre Programme abspulen, Wiederholungen zählen, Menschen an einen Plan anpassen.</span><span class="en">Running through rigid programmes, counting reps, making people fit a plan.</span></div>
    <div class="yes"><b><span class="de">Was du hier lernst</span><span class="en">What you learn here</span></b><span class="de">Dich wirklich auf den Menschen einzulassen und die Bewegung nach ihm zu richten.</span><span class="en">To truly engage with the person and shape the movement around them.</span></div>
  </div>
</div></div></section>

<section class="w sec" id="inhalte"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Inhalte &amp; Stunden</span><span class="en">Content &amp; hours</span></p><h2 style="margin-top:.6rem"><span class="de">500 Stunden, die <em>zusammengehören</em></span><span class="en">500 hours that <em>belong together</em></span></h2></div>
    <p><span class="de">Eine vollständige Yogalehrerausbildung nach internationalem 200-Stunden-Curriculum ist das Fundament. Alles andere macht daraus die Movecia Method.</span><span class="en">A complete yoga teacher training following an international 200-hour curriculum is the foundation. Everything else turns it into the Movecia Method.</span></p></div>
  <div class="hours">
    <div class="hbars rv">
      <?php foreach ($hours as [$de, $en, $h, $sde, $sen, $cls]): ?>
      <div class="hb <?= $cls ?>"><span class="t"><span class="de"><?= $de ?></span><span class="en"><?= $en ?></span></span><span class="h"><?= $h ?> <span class="de">Std.</span><span class="en">h</span></span><span class="bar"><i style="--w:<?= round($h / 2) ?>%"></i></span><span class="s"><span class="de"><?= $sde ?></span><span class="en"><?= $sen ?></span></span></div>
      <?php endforeach; ?>
      <div class="hsum"><span><span class="de">Gesamt</span><span class="en">Total</span></span><span>500 <span class="de">Std.</span><span class="en">h</span></span></div>
    </div>
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
  </div>
</div></section>

<section class="w sec" id="ablauf"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">So läuft es ab</span><span class="en">How it works</span></p><h2 style="margin-top:.6rem"><span class="de">Elf Monate, <em>hybrid</em></span><span class="en">Eleven months, <em>hybrid</em></span></h2></div>
    <p><span class="de">Jeden Monat öffnet sich ein neues Modul, immer am 1., nur das erste startet am <?= mv_date($st) ?>. Du lernst, wann es in deinen Alltag passt, und jede Woche sehen wir uns live.</span><span class="en">A new module opens every month, always on the 1st; only the first one opens on <?= mv_date($st, 'en') ?>. You learn whenever it fits into your day, and every week we meet live.</span></p></div>
  <div class="how cute-cards">
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('sonne') ?></span><b><span class="de">Theorie-Videos</span><span class="en">Theory videos</span></b><p><span class="de">Kompakte Videos zu jedem Thema, mit Kapitelübersicht, auf Handy, Tablet oder Computer.</span><span class="en">Compact videos on every topic, with a chapter overview, on your phone, tablet or computer.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('blatt') ?></span><b><span class="de">Ausführliche Workbooks</span><span class="en">Extensive workbooks</span></b><p><span class="de">Zu jedem Modul ein Workbook zum Nachlesen, Üben und Reflektieren, schön gestaltet und zum Behalten.</span><span class="en">A workbook for every module to read, practise and reflect with, beautifully designed and yours to keep.</span></p></div>
    <div class="cat rv"><span class="ib"><?= mv_icon_svg('kreis') ?></span><b><span class="de">Wöchentlicher Live-Call</span><span class="en">Weekly live call</span></b><p><span class="de">Wir üben gemeinsam, besprechen deine Fragen und unterrichten uns gegenseitig, in einer kleinen Gruppe von höchstens zehn Frauen.</span><span class="en">We practise together, talk through your questions and teach each other, in a small group of no more than ten women.</span></p></div>
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
        <p><span class="de">Von Freitag 8:30 Uhr bis Sonntag 17:30 Uhr üben, unterrichten und lernen wir gemeinsam. Zwei Nächte mit Unterkunft und Verpflegung sind im Preis enthalten. Den genauen Ort gebe ich rechtzeitig bekannt.</span><span class="en">From Friday 8:30 am to Sunday 5:30 pm we practise, teach and learn together. Two nights with accommodation and meals are included in the price. I will announce the exact venue in good time.</span></p>
        <p><span class="de"><b>Deine Lehrprobe:</b> Im Modul Unterrichten &amp; Sequencing entwickelst du deine eigene, etwa 25-minütige Einheit, und am Wochenende unterrichtest du sie. Das ist keine Prüfung, sondern dein schöner Moment, in dem alles zusammenkommt.</span><span class="en"><b>Your teaching practice:</b> in the teaching &amp; sequencing module you create your own session of about 25 minutes, and at the weekend you teach it. It is not an exam; it is your moment, when everything comes together.</span></p>
        <div class="facts"><span><span class="de">Fr 8:30 – So 17:30</span><span class="en">Fri 8:30 am – Sun 5:30 pm</span></span><span><span class="de">2 Nächte inklusive</span><span class="en">2 nights included</span></span><span><span class="de">Verpflegung inklusive</span><span class="en">Meals included</span></span></div>
        <p class="must"><span class="de">Die Termine stehen ein Jahr im Voraus fest. Bitte plan das Wochenende fix ein, es kann nicht nachgeholt werden.</span><span class="en">The dates are fixed a year in advance. Please plan for the weekend, as it cannot be made up later.</span></p>
      </div>
    </div>
    <div class="fin rv">
      <p class="k"><span class="de">Dein Weg zum Zertifikat</span><span class="en">Your path to the certificate</span></p>
      <h3><span class="de">Drei Schritte zum Abschluss</span><span class="en">Three steps to graduation</span></h3>
      <ol>
        <li><span class="ib"><?= mv_icon_svg('berg') ?></span><div><b><span class="de">Präsenzwochenende mit Lehrprobe</span><span class="en">In-person weekend with teaching practice</span></b><p><span class="de">Du unterrichtest deine eigene Einheit von etwa 25 Minuten.</span><span class="en">You teach your own session of about 25 minutes.</span></p></div></li>
        <li><span class="ib"><?= mv_icon_svg('kerze') ?></span><div><b><span class="de">Online-Abschlussprüfung</span><span class="en">Online final exam</span></b><p><span class="de">Nach dem Wochenende beantwortest du zu Hause die Multiple-Choice-Fragen, mit eingeschalteter Kamera in unserem Online-Prüfungsraum.</span><span class="en">After the weekend you answer the multiple-choice questions at home, with your camera on in our online exam room.</span></p></div></li>
        <li><span class="ib"><?= mv_icon_svg('bluete') ?></span><div><b><span class="de">Dein Zertifikat</span><span class="en">Your certificate</span></b><p><span class="de">Mit bestandener Prüfung, dem Präsenzwochenende und ca. 100 dokumentierten Praxisstunden bekommst du dein Zertifikat der Movecia Academy über 500 Stunden.</span><span class="en">Once you have passed the exam, completed the in-person weekend and documented around 100 practice hours, you receive your 500-hour Movecia Academy certificate.</span></p></div></li>
      </ol>
      <div class="room"><img src="/img/movecia/fotos/ev-gathering.jpg?v=1" alt="" loading="lazy"><p><span class="de"><b>Der Prüfungsraum</b> ist wie ein ruhiger Online-Coworking-Raum: Ihr sitzt mit Live-Kamera jeweils bei euch zu Hause, und ich bin die ganze Zeit dabei.</span><span class="en"><b>The exam room</b> is like a quiet online co-working space: each of you sits at home with your camera on, and I am there the whole time.</span></p></div>
    </div>
  </div>
</div></section>

<section class="w sec" id="weg"><div class="in">
  <div class="rv" style="text-align:center"><p class="k"><span class="de">Dein Weg</span><span class="en">Your path</span></p><h2 style="margin-top:.6rem"><span class="de">Von der eigenen Praxis zum <em>Zertifikat</em></span><span class="en">From your own practice to your <em>certificate</em></span></h2></div>
  <div class="path">
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('welle') ?></span><div><span class="wd"><span class="de">Vorher · empfohlen</span><span class="en">Beforehand · recommended</span></span><b><span class="de">Etwa 1,5 Jahre eigene Praxis</span><span class="en">About 1.5 years of your own practice</span></b><p><span class="de">Mit der <a href="/method/">Method</a>, mit <a href="/unfold/">Unfold</a> oder in den <a href="/gathering/">Gatherings</a> lernst du die Movecia-Art zu bewegen von innen kennen.</span><span class="en">With the <a href="/method/">Method</a>, <a href="/unfold/">Unfold</a> or the <a href="/gathering/">Gatherings</a> you get to know the Movecia way of moving from the inside.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('knospe') ?></span><div><span class="wd"><span class="de">Dein Moment</span><span class="en">Your moment</span></span><b><span class="de">Die Entscheidung</span><span class="en">The decision</span></b><p><span class="de">Du spürst, dass du das, was dir gut tut, weitergeben möchtest.</span><span class="en">You feel that you want to pass on what does you good.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('kreis') ?></span><div><span class="wd"><span class="de">Kostenlos</span><span class="en">Free</span></span><b><span class="de">Kennenlerngespräch</span><span class="en">Intro call</span> <span class="opt"><span class="de">optional</span><span class="en">optional</span></span></b><p><span class="de">30 Minuten per Video, um deine Fragen zu klären.</span><span class="en">30 minutes by video to answer your questions.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('herz') ?></span><div><span class="wd"><span class="de">Online</span><span class="en">Online</span></span><b><span class="de">Deine Buchung</span><span class="en">Your booking</span></b><p><span class="de">Kurzer Fragebogen, Zahlung, und dein Platz ist sicher.</span><span class="en">A short questionnaire, payment, and your place is secured.</span></p></div></div>
    <div class="pst big rv"><span class="dot"><?= mv_icon_svg('sonne') ?></span><div><span class="wd"><?= mv_date($st) ?></span><b><span class="de">Start mit Modul 1</span><span class="en">Module 1 begins</span></b><p><span class="de">Gleich nach den Tiroler Herbstferien geht es los.</span><span class="en">We begin right after the Tyrolean autumn school holidays.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('blatt') ?></span><div><span class="wd"><span class="de">Nov. 2027 – Sept. 2028</span><span class="en">Nov 2027 – Sept 2028</span></span><b><span class="de">Elf Module</span><span class="en">Eleven modules</span></b><p><span class="de">Videos, Workbooks und jede Woche unser Live-Call.</span><span class="en">Videos, workbooks and our live call every week.</span></p></div></div>
    <div class="pst big rv"><span class="dot"><?= mv_icon_svg('berg') ?></span><div><span class="wd"><span class="de">September 2028</span><span class="en">September 2028</span></span><b><span class="de">Präsenzwochenende &amp; Lehrprobe</span><span class="en">In-person weekend &amp; teaching practice</span></b><p><span class="de">Drei Tage zusammen in den Tiroler Alpen.</span><span class="en">Three days together in the Tyrolean Alps.</span></p></div></div>
    <div class="pst rv"><span class="dot"><?= mv_icon_svg('bluete') ?></span><div><span class="wd"><span class="de">Danach</span><span class="en">Afterwards</span></span><b><span class="de">Online-Prüfung &amp; Zertifikat</span><span class="en">Online exam &amp; certificate</span></b><p><span class="de">Multiple Choice mit Kamera im Prüfungsraum, dann hältst du dein Zertifikat in der Hand.</span><span class="en">Multiple choice with your camera on in the exam room, then your certificate is in your hands.</span></p></div></div>
  </div>
</div></section>

<section class="w sec" id="gespraech"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Kostenlos &amp; unverbindlich</span><span class="en">Free &amp; no obligation</span></p><h2 style="margin-top:.6rem"><span class="de">Lass uns <em>reden</em></span><span class="en">Let's <em>talk</em></span></h2></div>
    <p><span class="de">In 30 Minuten per Video lernen wir uns kennen, und du kannst alles fragen, was dir auf dem Herzen liegt. Das Gespräch ist freiwillig, du kannst auch direkt buchen.</span><span class="en">In 30 minutes by video we get to know each other, and you can ask me anything that is on your mind. The call is optional; you can also book straight away.</span></p></div>
  <div class="callbox rv">
    <div>
      <p class="k"><span class="de">Wunschtermin wählen</span><span class="en">Choose a time</span></p>
      <p class="mut" style="margin-top:.4rem;font-size:.95rem"><span class="de">Mittwoch, Donnerstag und Freitag um 8:00 Uhr, Mittwoch und Donnerstag auch um 18:30 Uhr. Ich bestätige dir den Termin per E-Mail.</span><span class="en">Wednesday, Thursday and Friday at 8:00 am, and Wednesday and Thursday at 6:30 pm too (Vienna time). I will confirm the time by email.</span></p>
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
      <div><button class="btn" type="submit" id="cb"><span class="de">Termin anfragen</span><span class="en">Request this time</span></button></div>
      <p class="err" id="ce" hidden></p>
      <div class="okmsg" id="co" hidden></div>
    </form>
  </div>
</div></section>

<section class="w sec" id="buchen"><div class="in">
  <div class="pricebox">
    <div>
      <p class="k"><span class="de">Investition</span><span class="en">Investment</span></p>
      <h2 style="margin-top:.5rem"><span class="de">Dein Platz in der Ausbildung</span><span class="en">Your place on the training</span></h2>
      <p class="amt"><?= $e($p['once']) ?><?= $p['eb'] ? '<s>' . $e($c['r_once']) . '</s>' : '' ?></p>
      <?php if ($p['eb']): ?><p style="margin-top:.4rem;color:#F3E4DA"><span class="de"><?= $offerLive && !$c['eb'] ? 'Dein Angebot aus unserem Gespräch' : 'Early-Bird bis ' . mv_date($c['eb_end']) . ', danach ' . $e($c['r_once']) ?></span><span class="en"><?= $offerLive && !$c['eb'] ? 'Your offer from our call' : 'Early bird until ' . mv_date($c['eb_end'], 'en') . ', then ' . $ee($c['r_once']) ?></span></p><?php endif; ?>
      <ul class="incl">
        <li><span class="de">500 Stunden Ausbildung inkl. 200 Std. Yoga nach internationalem Curriculum</span><span class="en">500 hours of training incl. 200 h yoga following an international curriculum</span></li>
        <li><span class="de">11 Module mit Theorie-Videos und ausführlichen Workbooks</span><span class="en">11 modules with theory videos and extensive workbooks</span></li>
        <li><span class="de">Jede Woche ein Live-Call in kleiner Gruppe</span><span class="en">A live call every week in a small group</span></li>
        <li><span class="de">Präsenzwochenende mit 2 Nächten, Unterkunft und Verpflegung</span><span class="en">In-person weekend with 2 nights, accommodation and meals</span></li>
        <li><span class="de">Online-Abschlussprüfung und Zertifikat der Movecia Academy</span><span class="en">Online final exam and Movecia Academy certificate</span></li>
      </ul>
      <div class="ebn"><b><span class="de">Höchstens <?= (int)$c['seats'] ?> Plätze</span><span class="en">No more than <?= (int)$c['seats'] ?> places</span></b><span class="de"><?= $free ? 'Gerade sind noch Plätze frei.' : 'Die Ausbildung ist ausgebucht. Schreib mir gern, falls ein Platz frei wird.' ?> Dein 14-tägiges Rücktrittsrecht beginnt erst mit dem Start, also bis <?= mv_date($wu) ?>.</span><span class="en"><?= $free ? 'There are still places available.' : 'The training is fully booked. Feel free to write to me in case a place opens up.' ?> Your 14-day right of withdrawal only starts when the training begins, so it runs until <?= mv_date($wu, 'en') ?>.</span></div>
    </div>
    <form class="bookf" id="bf" novalidate>
      <?php if ($free): ?>
      <p class="k"><span class="de">Anmeldung</span><span class="en">Registration</span></p>
      <div class="plans" role="radiogroup">
        <label class="plan"><input type="radio" name="plan" value="once" checked><span class="badge"><span class="de">Beliebteste Wahl</span><span class="en">Most popular</span></span><span class="pt"><span class="de">Auf einmal</span><span class="en">In full</span></span><span class="pa"><?= $e($p['once']) ?></span><span class="ps"><span class="de">du sparst <?= $e($p['inst'] * $p['n'] - $p['once']) ?></span><span class="en">you save <?= $ee($p['inst'] * $p['n'] - $p['once']) ?></span></span></label>
        <label class="plan"><input type="radio" name="plan" value="inst"><span class="pt"><span class="de"><?= (int)$p['n'] ?> Monatsraten</span><span class="en"><?= (int)$p['n'] ?> monthly payments</span></span><span class="pa"><?= (int)$p['n'] ?> × <?= $e($p['inst']) ?></span><span class="ps"><span class="de">gesamt <?= $e($p['inst'] * $p['n']) ?></span><span class="en"><?= $ee($p['inst'] * $p['n']) ?> in total</span></span></label>
      </div>
      <label class="f"><span><span class="de">Vor- und Nachname</span><span class="en">Full name</span></span><input type="text" name="name" autocomplete="name" required maxlength="80"></label>
      <label class="f"><span>E-Mail</span><input type="email" name="email" autocomplete="email" required maxlength="160"></label>
      <label class="f"><span><span class="de">Telefon</span><span class="en">Phone</span></span><input type="text" name="phone" autocomplete="tel" required maxlength="40" inputmode="tel"></label>
      <fieldset><legend><span class="de">Wie lange bewegst du dich schon achtsam (Yoga, Method, Unfold …)?</span><span class="en">How long have you been practising mindful movement (yoga, the Method, Unfold …)?</span></legend>
        <div class="prc"><?php foreach ($pr as $k => [$de, $en]): ?><label><input type="radio" name="practice" value="<?= $k ?>"><span><span class="de"><?= $de ?></span><span class="en"><?= $en ?></span></span></label><?php endforeach; ?></div>
      </fieldset>
      <label class="f"><span><span class="de">Erzähl mir kurz von deiner Praxis (freiwillig)</span><span class="en">Tell me a little about your practice (optional)</span></span><textarea name="practice_note" maxlength="800" style="min-height:80px"></textarea></label>
      <label class="f"><span><span class="de">Warum möchtest du die Ausbildung machen?</span><span class="en">Why would you like to do the training?</span></span><textarea name="why" maxlength="1500" required></textarea></label>
      <fieldset class="wrf"><legend><span class="de">Dein Rücktrittsrecht</span><span class="en">Your right of withdrawal</span></legend>
        <label class="wro"><input type="radio" name="wr" value="verzicht"><span><b><span class="de">Ab dem Start gleich alles nutzen</span><span class="en">Use everything from the start</span></b><small><span class="de">Videos und Workbook-Downloads sind ab dem <?= mv_date($st) ?> ganz offen. Für diese digitalen Inhalte verzichte ich auf mein Rücktrittsrecht.</span><span class="en">Videos and workbook downloads are fully open from <?= mv_date($st, 'en') ?>. I waive my right of withdrawal for this digital content.</span></small></span></label>
        <label class="wro"><input type="radio" name="wr" value="behalten"><span><b><span class="de">Rücktrittsrecht behalten</span><span class="en">Keep my right of withdrawal</span></b><small><span class="de">Live-Calls und Videos starten wie geplant, die Workbook-Downloads öffnen sich nach dem Ende der 14-tägigen Frist ab dem Start.</span><span class="en">Live calls and videos start as planned; the workbook downloads open once the 14-day period from the start has ended.</span></small></span></label>
      </fieldset>
      <label class="chk"><input type="checkbox" name="weekend" value="1"><span><span class="de">Ich weiß, dass das Präsenzwochenende im September 2028 ein fester Termin ist, der nicht nachgeholt werden kann. Kann ich nicht teilnehmen, wird es nicht erstattet.</span><span class="en">I understand that the in-person weekend in September 2028 is a fixed date that cannot be made up later. If I cannot attend, it is not refunded.</span></span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div><button class="btn" type="submit" id="bb"><span class="de">Weiter zur Zahlung</span><span class="en">Continue to payment</span> →</button></div>
      <p class="mut" style="font-size:.82rem"><span class="de">Sichere Zahlung über Stripe (gemeinsames Konto von FORMA &amp; Movecia). Es gelten die <a href="/terms/#teacher">Bedingungen</a>.</span><span class="en">Secure payment with Stripe (shared FORMA &amp; Movecia account). The <a href="/terms/#teacher">terms</a> apply.</span></p>
      <p class="err" id="be" hidden></p>
      <?php if (isset($_GET['abbruch'])): ?><p class="note"><span class="de">Die Zahlung wurde abgebrochen, es wurde nichts abgebucht.</span><span class="en">The payment was cancelled; nothing was charged.</span></p><?php endif; ?>
      <?php else: ?>
      <p class="k"><span class="de">Ausgebucht</span><span class="en">Fully booked</span></p>
      <p><span class="de">Alle Plätze sind vergeben. Schreib mir gern an <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a>, dann melde ich mich, wenn ein Platz frei wird.</span><span class="en">All places are taken. Write to me at <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a> and I'll let you know if a place opens up.</span></p>
      <?php endif; ?>
    </form>
  </div>
</div></section>

<section class="w sec"><div class="in me2">
  <img src="/img/movecia/fotos/ev-profil.jpg?v=1" alt="Vivien beim Seitstretch im Sitzen" loading="lazy">
  <div class="rv">
    <p class="k"><span class="de">Deine Ausbilderin</span><span class="en">Your trainer</span></p>
    <h2 style="margin-top:.6rem"><span class="de">Hallo, ich bin <em>Vivien</em></span><span class="en">Hi, I'm <em>Vivien</em></span></h2>
    <p style="margin-top:1rem"><span class="de">Ich unterrichte seit 2021 und habe rund 700 Stunden Yogalehrerausbildung und Bewegungslehre sowie knapp 400 Stunden Rehabilitation, Prävention und Gesundheitstraining hinter mir, dazu Weiterbildungen in somatischer Bewegung, Nervensystem-Regulation und prä- und postnatalem Training. Als Mama von vier Kindern weiß ich, wie sich ein voller Kopf anfühlt. Genau deshalb habe ich die Movecia Method entwickelt.</span><span class="en">I have been teaching since 2021, with around 700 hours of yoga teacher training and movement education and almost 400 hours of rehabilitation, prevention and health training behind me, plus further training in somatic movement, nervous system regulation and pre- and postnatal training. As a mother of four, I know what a full head feels like. That is exactly why I created the Movecia Method.</span></p>
    <p class="hand"><span class="de">Ich freu mich auf dich.</span><span class="en">I can't wait to meet you.</span></p>
  </div>
</div></section>

<section class="w sec"><div class="in fq">
  <p class="k"><span class="de">Fragen</span><span class="en">Questions</span></p>
  <h2 style="margin:.6rem 0 1rem"><span class="de">Gut zu <em>wissen</em></span><span class="en">Good to <em>know</em></span></h2>
  <div class="cute-faq">
  <?php foreach (ac_faq() as [$qde, $qen, $ade, $aen]): ?>
  <details class="q"><summary><span class="de"><?= forma_h($qde) ?></span><span class="en"><?= forma_h($qen) ?></span></summary><p><span class="de"><?= forma_h($ade) ?></span><span class="en"><?= forma_h($aen) ?></span></p></details>
  <?php endforeach; ?>
  <details class="q"><summary><span class="de">Kann ich zurücktreten?</span><span class="en">Can I withdraw?</span></summary><p><span class="de">Ja. Deine 14-tägige Rücktrittsfrist beginnt erst mit dem Start am <?= mv_date($st) ?>. Vor dem Start bekommst du alles zurück, danach zahlst du nur den Teil, der bis dahin genutzt wurde. Das Präsenzwochenende kann nicht nachgeholt und bei Nichtteilnahme nicht erstattet werden. Alle Details stehen in den <a href="/terms/#teacher">Bedingungen</a>.</span><span class="en">Yes. Your 14-day withdrawal period only begins with the start on <?= mv_date($st, 'en') ?>. Before the start you get everything back; after that you pay only for the part already used. The in-person weekend cannot be made up later and is not refunded if you do not attend. All the details are in the <a href="/terms/#teacher">terms</a>.</span></p></details>
  </div>
</div></section>
</main>
<?php mvl_foot(); ?>
<script>
(function(){
  var T=Date.now(),L=function(){return document.documentElement.dataset.l==='en'?'en':'de'};
  // Countdown fürs Angebot aus dem Gespräch
  var ob=document.getElementById('offerbar');
  if(ob){var until=+ob.dataset.until*1000,skew=Date.now()-(+ob.dataset.now*1000),cd=document.getElementById('offercd');
    var tick=function(){var ms=until-(Date.now()-skew);if(ms<=0){cd.textContent='0:00';setTimeout(function(){location.href=location.pathname+(L()==='en'?'?lang=en':'')+'#buchen'},1500);return}var s=Math.ceil(ms/1000),m=Math.floor(s/60);cd.textContent=m+':'+String(s%60).padStart(2,'0');setTimeout(tick,500)};tick();}
  var angebot=new URLSearchParams(location.search).get('angebot')||'';
  // Kennenlerngespräch
  var pick=null;document.querySelectorAll('#slots .slot').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('#slots .slot').forEach(function(x){x.setAttribute('aria-pressed','false')});b.setAttribute('aria-pressed','true');pick=b})});
  var cf=document.getElementById('cf'),ce=document.getElementById('ce'),co=document.getElementById('co'),cb=document.getElementById('cb');
  cf.addEventListener('submit',async function(ev){ev.preventDefault();ce.hidden=true;var en=L()==='en';
    if(!pick){ce.textContent=en?'Please choose a time on the left.':'Bitte wähl zuerst einen Termin.';ce.hidden=false;return}
    if(cf.name.value.trim().length<2||cf.email.value.indexOf('@')<1){ce.textContent=en?'Please enter your name and email.':'Bitte gib deinen Namen und deine E-Mail an.';ce.hidden=false;return}
    var d=Object.fromEntries(new FormData(cf));d.action='call';d.date=pick.dataset.d;d.slot=pick.dataset.s;d.lang=L();d.t=T;cb.disabled=true;
    try{var r=await fetch('/api/academy.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      if(j.ok){cf.querySelectorAll('label,button').forEach(function(x){x.hidden=true});co.textContent=(en?'Thank you! Your preferred time: ':'Danke! Dein Wunschtermin: ')+j.when+(en?'. I will confirm it by email shortly.':'. Ich bestätige ihn dir in Kürze per E-Mail.');co.hidden=false;pick.disabled=true;return}
      ce.textContent=j.error||'…';ce.hidden=false}catch(x){ce.textContent=en?'Something went wrong. Please try again.':'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.';ce.hidden=false}
    cb.disabled=false});
  // Buchung
  var bf=document.getElementById('bf'),be=document.getElementById('be'),bb=document.getElementById('bb');
  if(bb)bf.addEventListener('submit',async function(ev){ev.preventDefault();be.hidden=true;var en=L()==='en',err=[];
    if(bf.name.value.trim().length<2||bf.email.value.indexOf('@')<1)err.push(en?'Please enter your name and email.':'Bitte gib deinen Namen und deine E-Mail an.');
    if(bf.phone.value.replace(/\D/g,'').length<6)err.push(en?'Please enter your phone number.':'Bitte gib deine Telefonnummer an.');
    if(!bf.querySelector('input[name=practice]:checked'))err.push(en?'Please tell me how long you have been practising.':'Bitte sag mir, wie lange du schon praktizierst.');
    if(bf.why.value.trim().length<10)err.push(en?'Please write a few words about why you want to do the training.':'Bitte schreib mir ein paar Sätze, warum du die Ausbildung machen möchtest.');
    if(!bf.querySelector('input[name=wr]:checked'))err.push(en?'Please choose whether you keep your right of withdrawal.':'Bitte wähle, ob du dein Rücktrittsrecht behältst.');
    if(!bf.weekend.checked)err.push(en?'Please tick the box about the in-person weekend.':'Bitte setz das Häkchen zum Präsenzwochenende.');
    if(err.length){be.textContent=err.join(' ');be.hidden=false;return}
    var d=Object.fromEntries(new FormData(bf));d.action='book';d.lang=L();d.t=T;d.angebot=angebot;bb.disabled=true;
    try{var r=await fetch('/api/academy.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      if(j.url){location.href=j.url;return}be.textContent=j.error||'…';be.hidden=false}catch(x){be.textContent=en?'Something went wrong. Please try again.':'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.';be.hidden=false}
    bb.disabled=false});
})();
</script>
</body>
</html>

<?php
// moveciamovement.com – Hauptseite Movecia (Vivien, 25.10.2026): Überschrift nur „Ich bin Movecia.“
// Ruhig nach Vorbild Mindful Body: große feine Schrift, Mauve-Band, weich verschwommene Bilder.
// Die Bilder sind vorerst Viviens eigene Fotos (künstlerisch weichgezeichnet) und gemalte Platzhalter,
// später kommen Everygen-Bilder (Liste der Bildideen in CLAUDE.md). Teacher Training steht seit 28.10.2026 als Academy-Leiste unter den Angeboten.
declare(strict_types=1);
require_once __DIR__ . '/../assets/icons.inc.php';
require dirname(__DIR__, 2) . '/api/method-lib.php';
require_once dirname(__DIR__, 2) . '/api/natal-lib.php';
require __DIR__ . '/../assets/layout.inc.php';
header('Cache-Control: no-cache');
$pdo = forma_db();
mv_schema($pdo);
try { mv_tick($pdo); } catch (Throwable $e) { error_log('MV tick: ' . $e->getMessage()); } // u. a. Freischalt-Mails am Starttag
$c = mv_cfg();
require_once dirname(__DIR__, 2) . '/api/academy-lib.php';
$acc = ac_cfg();
$eu = fn(int $x) => '€ ' . number_format($x / 100, 0, ',', '.');
$natalMin = PHP_INT_MAX;
foreach (natal_packages() as $p) $natalMin = min($natalMin, (int)round($p['cents'] / max(1, $p['units'])));
$st = mv_ts($c['u_start']);
$uFree = mv_unfold_free($pdo);
$g = null;
try { mm_schema($pdo); $g = mm_next_gathering($pdo); } catch (Throwable $e) {}
$site = mm_site();
$org = ['@context' => 'https://schema.org', '@type' => ['SportsActivityLocation', 'LocalBusiness'], '@id' => $site . '/#movecia', 'name' => 'Movecia', 'alternateName' => 'Movecia – Personal Fitness', 'url' => $site . '/', 'logo' => $site . '/img/movecia/movecia-zeichen-1024.png', 'image' => $site . '/img/movecia/og-start.jpg',
    'description' => 'Mindful Movement, Yoga, Somatic Movement, Breathwork und Meditation mit Vivien Malonga – online on demand, live per Video und im Paznaun (Tirol).',
    'email' => 'hello@moveciamovement.com', 'telephone' => NATAL_COMPANY['phone'], 'founder' => ['@type' => 'Person', 'name' => 'Vivien Malonga', 'jobTitle' => 'Yogalehrerin und Bewegungstrainerin'],
    'address' => ['@type' => 'PostalAddress', 'postalCode' => '6562', 'addressLocality' => 'Mathon', 'addressRegion' => 'Tirol', 'addressCountry' => 'AT'],
    'areaServed' => [['@type' => 'Place', 'name' => 'Paznaun'], ['@type' => 'Place', 'name' => 'Landeck'], ['@type' => 'Country', 'name' => 'Österreich'], ['@type' => 'Place', 'name' => 'Online']],
    'knowsLanguage' => ['de', 'en'], 'priceRange' => '€€'];
$web = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Movecia', 'url' => $site . '/', 'inLanguage' => ['de', 'en'], 'publisher' => ['@id' => $site . '/#movecia']];
mvl_head('Movecia · Mindful Movement, Yoga & Somatic Movement mit Vivien Malonga', 'Mindful Movement mit Vivien Malonga: Yoga, Somatic Movement, Breathwork und Meditation für Frauen. Online on demand, Unfold 3-Monats-Begleitung, Prä- & Postnatal Training und 1:1 im Paznaun, Tirol.', $site . '/', '/img/movecia/og-start.jpg', '', [$org, $web]);
?>
<body>
<?php mvl_top(); ?>
<main>
<section class="w"><div class="in hero-x">
  <!-- Bewegter Hintergrund oben (Vivien, 27.10.2026: „sieht so langweilig aus, gern Wellen“): fließende Wellen, feine Linien, Movecia-Kiesel -->
  <?= mv_waves() ?>
  <p class="k"><span class="de">Mindful Movement · Yoga · Somatic · Breathwork</span><span class="en">Mindful movement · yoga · somatic · breathwork</span></p>
  <h1><span class="de"><em>Ich bin Movecia.</em></span><span class="en"><em>I am Movecia.</em></span></h1>
  <p class="sub"><span class="de">Bewegung beginnt bei dir.</span><span class="en">Movement begins with you.</span></p>
  <p class="lead"><span class="de">Movecia heißt „in Bewegung“. Ich begleite Frauen zurück in ihren Körper, mit sanfter, achtsamer Bewegung, mit Atem und mit Raum für das, was sie wirklich beschäftigt. Online, wann immer du magst, live in kleinen Runden und persönlich bei mir in den Tiroler Bergen.</span><span class="en">Movecia means “in motion”. I guide women back into their bodies with gentle, mindful movement, with breath and with room for whatever is really on their minds. Online whenever you like, live in small circles and in person with me in the Tyrolean mountains.</span></p>
  <div class="cta"><a class="btn" href="#angebote"><span class="de">So begleite ich dich</span><span class="en">Ways to move with me</span> →</a><a class="btn line" href="/unfold/"><span class="de">Unfold · Start <?= mv_date($st) ?></span><span class="en">Unfold · starts <?= mv_date($st, 'en') ?></span></a></div>
</div>
<div class="in"><div class="art wide soft rv"><video src="/video/movecia/start.mp4?v=10" poster="/img/movecia/fotos/ev-start.jpg?v=10" autoplay muted loop playsinline preload="metadata" aria-label="Vivien bewegt sich langsam in ihrem lichten Studio mit Blick auf die Berge"></video></div></div></section>

<section class="band"><span class="band-blob b1" aria-hidden="true"></span><span class="band-blob b2" aria-hidden="true"></span><div class="w"><div class="in">
  <p class="k"><span class="de">Wofür Movecia steht</span><span class="en">What Movecia stands for</span></p>
  <blockquote><span class="de">Für Frauen, die sich in ihrem Körper wieder sicher, stark und zu Hause fühlen wollen.</span><span class="en">For women who want to feel safe, strong and at home in their bodies again.</span></blockquote>
  <p class="after"><span class="de">Hier musst du nichts leisten. Du darfst langsam werden, spüren, was du brauchst, und dir Schritt für Schritt deine Kraft zurückholen.</span><span class="en">Here you don't have to perform. You can slow down, feel what you need and, step by step, take back your strength.</span></p>
</div></div></section>

<section class="w vids" aria-label="Movecia in Bewegung"><div class="in">
  <!-- Nur noch das Tanz-Video, darüber die Linie ankommen · spüren · entfalten (Vivien, 26.10.2026) -->
  <div class="vpair">
  <figure class="vsolo soft rv">
    <video src="/video/movecia/unfold.mp4?v=10" poster="/img/movecia/fotos/ev-unfold.jpg?v=10" autoplay muted loop playsinline preload="metadata" aria-label="Vivien tanzt langsam in ihrem lichten Studio"></video>
    <div class="flow" aria-hidden="true">
      <span class="f-lab f-top"><span class="de">ankommen</span><span class="en">arrive</span></span>
      <span class="f-line"></span>
      <span class="f-dot"><svg viewBox="17 14 94 94" aria-hidden="true"><path d="M56 32 C 76 29, 92 44, 91 64 C 90 84, 72 96, 53 92 C 34 88, 25 72, 29 53 C 32 40, 43 33, 56 32 Z" fill="#E8D6CA" transform="translate(-6 -4)"/><path d="M44 30 C 62 20, 90 28, 98 50 C 104 68, 92 78, 84 88 C 76 100, 50 98, 36 88 C 22 78, 22 58, 28 46 C 32 38, 38 33, 44 30 Z" fill="none" stroke="#7D5E68" stroke-width="4.2" stroke-linejoin="round" transform="translate(7 4) translate(63 60) scale(0.85) translate(-63 -60)"/><circle cx="81" cy="54" r="10" fill="#7D5E68"/></svg></span>
      <span class="f-lab f-mid"><span class="de">spüren</span><span class="en">feel</span></span>
      <span class="f-lab f-bot"><span class="de">entfalten</span><span class="en">unfold</span></span>
    </div>
    <figcaption class="sr"><span class="de">ankommen, spüren, entfalten</span><span class="en">arrive, feel, unfold</span></figcaption>
  </figure>
  <!-- am Computer daneben: Armbewegung vor den Bergen (Vivien, 26.10.2026) -->
  <figure class="vside soft rv" aria-hidden="true">
    <video src="/video/movecia/start-arme.mp4?v=10" poster="/img/movecia/fotos/ev-start-arme.jpg?v=10" autoplay muted loop playsinline preload="none"></video>
    <figcaption><span class="de">Bewegung beginnt bei dir.</span><span class="en">Movement begins with you.</span></figcaption>
  </figure>
  </div>
</div></section>

<section class="w sec" id="angebote"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">So begleite ich dich</span><span class="en">Ways to move with me</span></p><h2 style="margin-top:.6rem"><span class="de">Für jeden <em>Moment</em> deines Lebens</span><span class="en">For every <em>season</em> of your life</span></h2></div>
    <p><span class="de">Ob du jeden Tag ein paar ruhige Minuten für dich suchst, eine Begleitung über drei Monate oder Unterstützung rund um die Geburt: Hier findest du deinen Weg.</span><span class="en">Whether you are looking for a few quiet minutes every day, three months of guidance or support around the birth of your baby, you will find your way here.</span></p></div>
  <div class="offers">
    <a class="offer o-method rv" href="/method/"><span class="onum">01</span><div class="art"><img src="/img/movecia/fotos/ev-method-foto.jpg?v=3" alt="" loading="lazy"></div><?= mv_icons(['sonne','blatt','welle'], 'ico', 0) ?><div class="tx">
      <p class="k">On demand</p><h3>Mindful Movement Method</h3>
      <p><span class="de">Deine Videobibliothek für zu Hause: Flows, Somatic Movement, Breathwork und Meditation, jeden Monat ein neues Programm mit Workbook.</span><span class="en">Your video library at home: flows, somatic movement, breathwork and meditation, a new programme with a workbook every month.</span></p>
      <div class="meta"><span class="chip"><span class="de">ab <?= $eu((int)round($c['m12'] / 12)) ?> im Monat</span><span class="en">from <?= $eu((int)round($c['m12'] / 12)) ?> a month</span></span><span class="chip">DE · EN</span></div>
      <p class="more"><span class="de">Zur Method</span><span class="en">See the Method</span> →</p></div></a>
    <a class="offer o-unfold rv" href="/unfold/"><span class="onum">02</span><div class="art"><img src="/img/movecia/fotos/ev-unfold-karte.jpg?v=10" alt="" loading="lazy"></div><?= mv_icons(['knospe','bluete','spirale'], 'ico', 1.5) ?><div class="tx">
      <p class="k"><span class="de">3 Monate Begleitung</span><span class="en">3 months of guidance</span></p><h3>Unfold</h3>
      <p><span class="de">Dein 3-Monats-Begleiter: wöchentlicher Live-Call mit Impulsvortrag, zwei Bewegungsvideos pro Woche, Workbooks für Mental Load, Selbstwert und deine Ideen.</span><span class="en">Your 3-month companion: a weekly live call with a talk, two movement videos a week, workbooks for mental load, self-worth and your ideas.</span></p>
      <div class="meta"><span class="chip"><span class="de">Start <?= mv_date($st) ?></span><span class="en">Starts <?= mv_date($st, 'en') ?></span></span><span class="chip"><span class="de">max. <?= (int)$c['u_seats'] ?> Plätze</span><span class="en">max. <?= (int)$c['u_seats'] ?> spots</span></span></div>
      <p class="more"><?= $uFree > 0 ? '<span class="de">Platz sichern</span><span class="en">Book your spot</span>' : '<span class="de">Warteliste</span><span class="en">Waitlist</span>' ?> →</p></div></a>
    <a class="offer o-natal rv" href="/natal/"><span class="onum">03</span><div class="art"><img src="/img/movecia/fotos/ev-natal.jpg?v=10" alt="" loading="lazy"></div><?= mv_icons(['mond','herz','atem'], 'ico', 3) ?><div class="tx">
      <p class="k">1:1 · <span class="de">Studio &amp; online</span><span class="en">studio &amp; online</span></p><h3><span class="de">Prä- &amp; Postnatal</span><span class="en">Pre- &amp; postnatal</span></h3>
      <p><span class="de">Achtsames Training in der Schwangerschaft und danach, mit Atem, Beckenboden und Impulsen für eine leichtere Geburt und ein sanftes Ankommen danach.</span><span class="en">Mindful training during pregnancy and beyond, with breath, pelvic floor work and guidance for an easier birth and a gentle start afterwards.</span></p>
      <div class="meta"><span class="chip"><span class="de">ab <?= $eu($natalMin) ?> pro Einheit</span><span class="en">from <?= $eu($natalMin) ?> per session</span></span><span class="chip"><span class="de">Kennenlernen gratis</span><span class="en">free intro call</span></span></div>
      <p class="more"><span class="de">Mehr erfahren</span><span class="en">Learn more</span> →</p></div></a>
    <a class="offer o-gather rv" href="/gathering/"><span class="onum">04</span><div class="art"><img src="/img/movecia/fotos/ev-gathering.jpg?v=10" alt="" loading="lazy"></div><?= mv_icons(['kerze','kreis','berg'], 'ico', 4.5) ?><div class="tx">
      <p class="k"><span class="de">Live · kostenlos</span><span class="en">Live · free</span></p><h3>Gatherings</h3>
      <p><span class="de">Einmal im Monat treffen wir uns live: ein ruhiger Abend mit Bewegung, Atem und Gesprächen, offen für alle Frauen.</span><span class="en">Once a month we meet live: a calm evening with movement, breath and conversation, open to all women.</span></p>
      <div class="meta"><span class="chip"><?= $g ? '<span class="de">nächstes: ' . forma_h(mm_when_de($g)) . '</span><span class="en">next: ' . forma_h(mm_when($g)) . '</span>' : '<span class="de">monatlich</span><span class="en">monthly</span>' ?></span></div>
      <p class="more"><span class="de">Dabei sein</span><span class="en">Join</span> →</p></div></a>
  </div>
  <!-- Movecia Academy (Vivien, 28.10.2026): Teacher Training als eigene Leiste unter den Angeboten -->
  <a class="acad rv" href="/academy/teacher-training/"><img src="/img/movecia/fotos/ev-lunge.jpg?v=1" alt="" loading="lazy"><span class="acad-tx"><span class="k">Movecia Academy · <span class="de">Ausbildung ab <?= mv_date($acc['start_ts']) ?></span><span class="en">training from <?= mv_date($acc['start_ts'], 'en') ?></span></span><b>The Mindful Becoming Teacher Training</b><span class="acad-p"><span class="de">550 Stunden inkl. vollständiger 200-Std.-Yogalehrerausbildung, hybrid mit Präsenzwochenende in den Tiroler Alpen. Nur zehn Plätze.</span><span class="en">550 hours incl. a complete 200-hour yoga teacher training, hybrid with an in-person weekend in the Tyrolean Alps. Only ten places.</span></span></span><span class="acad-go"><span class="de">Zur Ausbildung</span><span class="en">See the training</span> →</span></a>
  <style>.acad{display:grid;grid-template-columns:1fr;gap:0;margin-top:26px;background:var(--deep);color:var(--on);border-radius:32px;overflow:hidden;text-decoration:none;align-items:center;transition:transform .5s ease}.acad:hover{transform:translateY(-3px)}.acad img{width:100%;aspect-ratio:16/9;object-fit:cover;display:block}.acad-tx{display:flex;flex-direction:column;gap:.35rem;padding:1.4rem 1.5rem .4rem}.acad .k{color:var(--peach)}.acad b{font-family:var(--serif);font-weight:400;font-size:clamp(1.8rem,3vw,2.6rem);line-height:1.05}.acad-p{color:#E3D6DA}.acad-go{padding:.6rem 1.5rem 1.5rem;color:var(--peach);font-weight:500}@media(min-width:900px){.acad{grid-template-columns:300px 1fr auto}.acad img{height:100%;aspect-ratio:auto;min-height:200px}.acad-tx{padding:1.6rem 2rem}.acad-go{padding:0 2rem}}</style>
</div></section>

<section class="w sec"><div class="in">
  <div class="rv" style="text-align:center"><p class="k"><span class="de">Der Weg</span><span class="en">The path</span></p><h2 style="margin-top:.6rem"><span class="de">In drei <em>Schritten</em> zurück zu dir</span><span class="en">Back to yourself in three <em>steps</em></span></h2></div>
  <div class="steps rv"><svg class="steps-line" viewBox="0 0 1200 60" preserveAspectRatio="none" aria-hidden="true"><path d="M0 30 C150 0 250 60 400 30 S650 0 800 30 S1050 60 1200 30"/></svg>
    <div><span class="n step-ico"><?= mv_icons(['atem','welle'], 'si', 0) ?><b>1</b></span><h3><span class="de">Ankommen</span><span class="en">Arrive</span></h3><p><span class="de">Du lernst wieder zu spüren, was dein Körper braucht, und beruhigst dein Nervensystem mit Atem und sanfter Bewegung.</span><span class="en">You learn to feel what your body needs again and calm your nervous system with breath and gentle movement.</span></p></div>
    <div><span class="n step-ico"><?= mv_icons(['berg','blatt'], 'si', 2) ?><b>2</b></span><h3><span class="de">Kraft aufbauen</span><span class="en">Build strength</span></h3><p><span class="de">Mit Bewegung ohne Erschöpfung wächst deine Kraft, in deinem Tempo und ohne Leistungsdruck.</span><span class="en">Your strength grows through movement that does not drain you, at your own pace and without pressure.</span></p></div>
    <div><span class="n step-ico"><?= mv_icons(['bluete','sonne'], 'si', 4) ?><b>3</b></span><h3><span class="de">Entfalten</span><span class="en">Unfold</span></h3><p><span class="de">Du nimmst die Ruhe und die Kraft mit in deinen Alltag und gehst die Dinge an, die dir wirklich wichtig sind.</span><span class="en">You take the calm and the strength into your everyday life and go after what truly matters to you.</span></p></div>
  </div>
</div></section>

<section class="w sec"><div class="in">
  <div class="rv"><p class="k"><span class="de">Meine Haltung</span><span class="en">My approach</span></p><h2 style="margin-top:.6rem"><span class="de">Was mir in jeder Stunde <em>wichtig</em> ist</span><span class="en">What <em>matters</em> to me in every session</span></h2></div>
  <div class="values rv">
    <div class="vc"><span class="vn">01</span><?= mv_icons(['atem'], 'vi', 0) ?><b><span class="de">Wahrnehmen statt Autopilot</span><span class="en">Awareness over autopilot</span></b><p><span class="de">Du bewegst dich bewusst und spürst, was gerade passiert.</span><span class="en">You move mindfully and notice what is happening right now.</span></p></div>
    <div class="vc"><span class="vn">02</span><?= mv_icons(['spirale'], 'vi', 0) ?><b><span class="de">Erkunden statt Perfektion</span><span class="en">Exploring over perfection</span></b><p><span class="de">Es gibt keine perfekte Haltung, nur deine eigene Art, dich zu bewegen.</span><span class="en">There is no perfect pose, only your own way of moving.</span></p></div>
    <div class="vc"><span class="vn">03</span><?= mv_icons(['welle'], 'vi', 0) ?><b><span class="de">Anpassen statt starrer Regeln</span><span class="en">Adapting over rigid rules</span></b><p><span class="de">Jeder Tag ist anders, deshalb gibt es Varianten für volle und für müde Tage.</span><span class="en">Every day is different, so there are options for full days and for tired days.</span></p></div>
    <div class="vc"><span class="vn">04</span><?= mv_icons(['sonne'], 'vi', 0) ?><b><span class="de">Wissen statt leerer Versprechen</span><span class="en">Knowledge over empty promises</span></b><p><span class="de">Was ich weitergebe, beruht auf fundierter Ausbildung und vielen Jahren Praxis.</span><span class="en">What I share is based on solid training and many years of practice.</span></p></div>
    <div class="vc"><span class="vn">05</span><?= mv_icons(['kreis'], 'vi', 0) ?><b><span class="de">Verbindung statt Leistungsdruck</span><span class="en">Connection over pressure</span></b><p><span class="de">Du bist nicht allein: Wir bewegen uns gemeinsam, auch wenn jede bei sich zu Hause ist.</span><span class="en">You are not alone: we move together, even when each of us is at home.</span></p></div>
  </div>
</div></section>

<section class="w sec" id="vivien"><div class="in about">
  <div class="art arch tall rv"><img src="/img/movecia/fotos/ev-ueber-mich.jpg?v=2" alt="Vivien beim Seitstretch in ihrem Studio mit Bergblick" loading="lazy"></div>
  <div class="rv">
    <p class="k"><span class="de">Hallo, ich bin Vivien</span><span class="en">Hi, I'm Vivien</span></p>
    <h2 style="margin-top:.6rem"><span class="de">Mama von vier Kindern, <em>Bewegungstrainerin</em> und Frau, die selbst neu anfangen musste.</span><span class="en">Mother of four, <em>movement teacher</em> and a woman who had to begin again herself.</span></h2>
    <p style="margin-top:1.2rem"><span class="de">2019 bin ich schwanger nach Tirol gezogen, ohne zu wissen, wie es beruflich weitergehen soll. Nach der Geburt habe ich angefangen, Yogastunden zu geben, und daraus ist meine Ausbildung gewachsen: rund 700 Stunden Yogalehrerausbildung und Bewegungslehre sowie knapp 400 Stunden Rehabilitation, Prävention und Gesundheitstraining, dazu Weiterbildungen in somatischer Bewegung, Nervensystem-Regulation, Yoga Nidra und prä- und postnatalem Training.</span><span class="en">In 2019 I moved to Tyrol while pregnant, with no idea what my work would look like. After the birth I started teaching yoga, and my training grew from there: around 700 hours of yoga teacher training and movement education and almost 400 hours of rehabilitation, prevention and health training, plus further training in somatic movement, nervous system regulation, yoga nidra and pre- and postnatal training.</span></p>
    <p><span class="de">Seit 2021 unterrichte ich, inzwischen sind es über 2.000 Stunden. Was ich dabei gelernt habe: Frauen brauchen nicht noch mehr Programme, die sie antreiben. Sie brauchen einen Ort, an dem sie wieder bei sich ankommen. Genau das ist Movecia.</span><span class="en">I have been teaching since 2021, more than 2,000 hours so far. What I have learned: women don't need more programmes that push them. They need a place where they can come home to themselves. That is exactly what Movecia is.</span></p>
    <div class="facts"><span><span class="de">Unterricht seit 2021</span><span class="en">teaching since 2021</span></span><span><span class="de">über 2.000 Unterrichtsstunden</span><span class="en">2,000+ teaching hours</span></span><span><span class="de">Paznaun, Tirol</span><span class="en">Paznaun, Tyrol</span></span></div>
    <p class="sig"><span class="de">Schön, dass du da bist.</span><span class="en">I'm so glad you're here.</span></p>
  </div>
</div></section>

<section class="w sec"><div class="in split">
  <div class="box rv">
    <p class="k"><span class="de">Persönlich im Paznaun</span><span class="en">In person in the Paznaun valley</span></p>
    <h3 style="font-size:2rem;margin-top:.5rem"><span class="de">1:1 Personal Training</span><span class="en">1:1 personal training</span></h3>
    <p class="mut" style="margin-top:.6rem"><span class="de">Im Studio in Mathon oder draußen in der Natur: Training, das zu deinem Körper und deinem Alltag passt. Auch für Gäste im Urlaub.</span><span class="en">In the studio in Mathon or outdoors in nature: training that fits your body and your everyday life. Holiday guests are welcome too.</span></p>
    <p style="margin-top:1.2rem"><button class="btn line" type="button" data-pt-open><span class="de">Anfrage schicken</span><span class="en">Send a request</span></button></p>
  </div>
  <div class="box dark rv">
    <p class="k"><span class="de">Geschenk für dich</span><span class="en">A gift for you</span></p>
    <h3 style="font-size:2rem;margin-top:.5rem"><span class="de">Reset-Routine für müde Tage</span><span class="en">Reset routine for tired days</span></h3>
    <p style="margin-top:.6rem"><span class="de">Ein kleines PDF mit Bewegung, Atem und Ritualen für Tage, an denen nichts mehr geht. Dazu Einladungen zu den Gatherings.</span><span class="en">A small PDF with movement, breath and rituals for days when you have nothing left. Plus invitations to the Gatherings.</span></p>
    <form id="rf" novalidate>
      <input type="text" name="name" placeholder="Vorname / first name" autocomplete="given-name" required maxlength="80">
      <input type="email" name="email" placeholder="E-Mail" autocomplete="email" required maxlength="160">
      <label class="check" style="color:#E3D6DA"><input type="checkbox" name="consent" value="1" required><span><span class="de">Ja, Vivien darf mir schreiben. Abmelden geht jederzeit.</span><span class="en">Yes, Vivien may email me. I can unsubscribe any time.</span></span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div><button class="btn light" type="submit"><span class="de">Schick sie mir</span><span class="en">Send it to me</span></button></div>
      <p class="small" id="rmsg" hidden></p>
    </form>
  </div>
</div></section>
</main>
<?php mvl_foot(); ?>
<?php require_once __DIR__ . '/../assets/next-popup.inc.php'; mv_next_popup(); ?>
<?php require_once dirname(__DIR__, 2) . '/api/mm-pt.inc.php';
$ptChips = function (string $name, array $map, string $type = 'checkbox') { foreach ($map as $k => [$de, $en]) echo '<label class="ptc"><input type="' . $type . '" name="' . $name . '[]" value="' . $k . '"><span><span class="de">' . htmlspecialchars($de) . '</span><span class="en">' . htmlspecialchars($en) . '</span></span></label>'; }; ?>
<!-- 1:1 Personal Training anfragen (Vivien, 26.10.2026): Formular statt E-Mail, landet in der Verwaltung -->
<dialog id="ptd" class="ptd" aria-labelledby="pth">
  <form id="ptf" novalidate>
    <button class="ptx" type="button" data-pt-close aria-label="Schließen">✕</button>
    <p class="k"><span class="de">1:1 Personal Training</span><span class="en">1:1 personal training</span></p>
    <h2 id="pth"><span class="de">Erzähl mir, was du dir <em>wünschst</em></span><span class="en">Tell me what you're <em>hoping for</em></span></h2>
    <p class="ptq"><span class="de">Was wünschst du dir?</span><span class="en">What are you looking for?</span></p>
    <div class="ptg"><?php $ptChips('goals', MM_PT_GOALS); ?></div>
    <p class="ptq"><span class="de">Wo möchtest du trainieren?</span><span class="en">Where would you like to train?</span></p>
    <div class="ptg"><?php $ptChips('places', MM_PT_PLACES); ?></div>
    <p class="ptq"><span class="de">Wann passt es dir meistens?</span><span class="en">When usually works best for you?</span></p>
    <div class="ptg"><?php $ptChips('times', MM_PT_TIMES); ?></div>
    <p class="ptq"><span class="de">Ein paar Worte zu dir</span><span class="en">A few words about you</span></p>
    <textarea name="message" rows="4" maxlength="1500" data-ph-de="Zum Beispiel, wie es dir gerade geht, was du vorhast oder ob es Beschwerden gibt, die ich kennen sollte." data-ph-en="For example how you are feeling right now, what you have in mind or any aches or health issues I should know about."></textarea>
    <div class="ptrow">
      <input type="text" name="name" autocomplete="name" maxlength="80" required data-ph-de="Dein Name" data-ph-en="Your name">
      <input type="email" name="email" autocomplete="email" maxlength="160" required placeholder="E-Mail">
    </div>
    <input type="text" name="phone" autocomplete="tel" maxlength="40" data-ph-de="Telefon (optional, falls ich dich anrufen darf)" data-ph-en="Phone (optional, if I may call you)">
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <p class="small ptn"><span class="de">Ich nutze deine Angaben nur, um mich bei dir zu melden. Mehr dazu im <a href="/terms/#datenschutz">Datenschutz</a>.</span><span class="en">I only use your details to get back to you. More in the <a href="/terms/#datenschutz">privacy notice</a>.</span></p>
    <div><button class="btn" type="submit"><span class="de">Anfrage abschicken</span><span class="en">Send my request</span></button></div>
    <p class="ptmsg" id="ptmsg" hidden></p>
  </form>
  <div class="ptok" id="ptok" hidden>
    <img src="/img/movecia/movecia-zeichen.svg" alt="" width="58" height="58">
    <h2><span class="de">Danke dir von Herzen</span><span class="en">Thank you so much</span></h2>
    <p><span class="de">Deine Anfrage ist bei mir angekommen. Ich melde mich in den nächsten Tagen persönlich bei dir, und eine kleine Bestätigung liegt schon in deinem Postfach.</span><span class="en">I've received your request. I will get back to you personally within the next few days, and a short confirmation is already in your inbox.</span></p>
    <button class="btn line" type="button" data-pt-close><span class="de">Schließen</span><span class="en">Close</span></button>
  </div>
</dialog>
<style>
.ptd{border:0;padding:0;border-radius:28px;background:var(--bg,#F2ECE4);color:var(--ink,#2E2529);width:min(640px,calc(100vw - 24px));max-height:calc(100dvh - 24px);overflow:auto;box-shadow:0 30px 80px rgba(46,37,41,.3)}
.ptd::backdrop{background:rgba(46,37,41,.5);backdrop-filter:blur(4px)}
.ptd form,.ptok{padding:clamp(1.4rem,4vw,2.4rem);display:flex;flex-direction:column;gap:.7rem;position:relative}
.ptd h2{font-size:clamp(1.7rem,4vw,2.3rem);margin:.2rem 0 .4rem;line-height:1.1}
.ptq{margin:.7rem 0 0;font-weight:500;color:var(--deep,#4A3A41)}
.ptg{display:flex;flex-wrap:wrap;gap:.5rem}
.ptc{cursor:pointer}.ptc input{position:absolute;opacity:0;pointer-events:none}
.ptc>span{display:inline-block;padding:.55em 1em;border:1px solid var(--line,#DDD2C6);border-radius:999px;background:#FBF8F4;font-size:.98rem;transition:background .2s,color .2s,border-color .2s}
.ptc input:checked+span{background:var(--plum,#7D5E68);border-color:var(--plum,#7D5E68);color:#FBF5F7}
.ptc input:focus-visible+span{outline:2px solid var(--peach,#E8D6CA);outline-offset:2px}
.ptrow{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}@media(max-width:560px){.ptrow{grid-template-columns:1fr}}
.ptx{position:absolute;right:16px;top:14px;border:0;background:none;font-size:1.3rem;color:var(--muted,#7D7076);cursor:pointer;padding:.3em}
.ptn{color:var(--muted,#7D7076);margin:.2rem 0}.ptn a{color:inherit}
.ptmsg{color:var(--plum,#7D5E68);margin:0}
.ptok{align-items:center;text-align:center}
</style>
<script>
(function(){
  var d=document.getElementById('ptd'),f=document.getElementById('ptf'),ok=document.getElementById('ptok'),m=document.getElementById('ptmsg'),t0=Date.now();
  if(!d)return;
  function ph(){var en=document.documentElement.dataset.l==='en';d.querySelectorAll('[data-ph-de]').forEach(function(x){x.placeholder=en?x.dataset.phEn:x.dataset.phDe;});}
  document.querySelectorAll('[data-pt-open]').forEach(function(b){b.addEventListener('click',function(){ph();f.hidden=false;ok.hidden=true;m.hidden=true;if(d.showModal)d.showModal();else d.setAttribute('open','');});});
  d.addEventListener('click',function(e){if(e.target.closest('[data-pt-close]')||e.target===d)d.close();});
  if(/[#&]anfrage/.test(location.hash)){ph();d.showModal&&d.showModal();}
  f.addEventListener('submit',async function(e){e.preventDefault();var en=document.documentElement.dataset.l==='en';
    var fd=new FormData(f);fd.append('lang',en?'en':'de');fd.append('t',String(t0));
    var b=f.querySelector('[type=submit]');b.disabled=true;
    try{var r=await fetch('/api/pt-anfrage.php',{method:'POST',body:fd});var j=await r.json();
      if(j.ok){f.hidden=true;ok.hidden=false;f.reset();}else{m.textContent=j.error||'…';m.hidden=false;}}
    catch(x){m.textContent=en?'Something went wrong just now. Please try again.':'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.';m.hidden=false;}
    b.disabled=false;});
})();
</script>
<script>
(function(){
  var f=document.getElementById('rf'),m=document.getElementById('rmsg'),t0=Date.now();
  f.addEventListener('submit',async function(ev){ev.preventDefault();var de=document.documentElement.dataset.l==='de';
    var d=Object.fromEntries(new FormData(f));d.source='reset';d.lang=document.documentElement.dataset.l;d.elapsed=Date.now()-t0;
    try{var r=await fetch('/api/wh-subscribe.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      m.textContent=j.ok?(de?'Fast geschafft! Bitte bestätige deine E-Mail-Adresse in deinem Postfach.':'Almost there! Please confirm your email in your inbox.'):(j.error||'…');}
    catch(x){m.textContent=de?'Das hat gerade nicht geklappt.':'Something went wrong.';}
    m.hidden=false;});
})();
</script>
</body>
</html>

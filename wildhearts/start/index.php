<?php
// moveciamovement.com – Hauptseite Movecia (Vivien, 25.10.2026): Überschrift nur „Ich bin Movecia.“
// Ruhig nach Vorbild Mindful Body: große feine Schrift, Mauve-Band, weich verschwommene Bilder.
// Die Bilder sind vorerst Viviens eigene Fotos (künstlerisch weichgezeichnet) und gemalte Platzhalter,
// später kommen Everygen-Bilder (Liste der Bildideen in CLAUDE.md). Teacher Training wird bewusst nicht gezeigt.
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
  <p class="k"><span class="de">Mindful Movement · Yoga · Somatic · Breathwork</span><span class="en">Mindful movement · yoga · somatic · breathwork</span></p>
  <h1><span class="de"><em>Ich bin Movecia.</em></span><span class="en"><em>I am Movecia.</em></span></h1>
  <p class="sub"><span class="de">Bewegung beginnt bei dir.</span><span class="en">Movement begins with you.</span></p>
  <p class="lead"><span class="de">Movecia heißt „in Bewegung“. Ich begleite Frauen zurück in ihren Körper, mit sanfter, achtsamer Bewegung, mit Atem und mit Raum für das, was sie wirklich beschäftigt. Online, wann immer du magst, live in kleinen Runden und persönlich bei mir in den Tiroler Bergen.</span><span class="en">Movecia means “in motion”. I guide women back into their bodies with gentle, mindful movement, with breath and with room for what truly matters to them. Online whenever you like, live in small circles and in person with me in the Tyrolean mountains.</span></p>
  <div class="cta"><a class="btn" href="#angebote"><span class="de">So begleite ich dich</span><span class="en">Ways to move with me</span> →</a><a class="btn line" href="/unfold/"><span class="de">Unfold · Start <?= mv_date($st) ?></span><span class="en">Unfold · starts <?= mv_date($st, 'en') ?></span></a></div>
</div>
<div class="in"><div class="art wide rv"><video src="/video/movecia/start.mp4?v=10" poster="/img/movecia/fotos/ev-start.jpg?v=10" autoplay muted loop playsinline preload="metadata" aria-label="Vivien bewegt sich langsam in ihrem lichten Studio mit Blick auf die Berge"></video></div></div></section>

<section class="band"><div class="w"><div class="in">
  <p class="k"><span class="de">Wofür Movecia steht</span><span class="en">What Movecia stands for</span></p>
  <blockquote><span class="de">Für Frauen, die sich in ihrem Körper wieder sicher, stark und zu Hause fühlen wollen.</span><span class="en">For women who want to feel safe, strong and at home in their bodies again.</span></blockquote>
  <p class="after"><span class="de">Hier musst du nichts leisten. Du darfst langsam werden, spüren, was du brauchst, und dir Schritt für Schritt deine Kraft zurückholen.</span><span class="en">Here you don't have to perform. You may slow down, feel what you need and, step by step, take back your strength.</span></p>
</div></div></section>

<section class="w vids" aria-label="Movecia in Bewegung"><div class="in">
  <!-- Nur noch das Tanz-Video, darüber die Linie ankommen · spüren · entfalten (Vivien, 26.10.2026) -->
  <figure class="vsolo rv">
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
</div></section>

<section class="w sec" id="angebote"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">So begleite ich dich</span><span class="en">Ways to move with me</span></p><h2 style="margin-top:.6rem"><span class="de">Für jeden <em>Moment</em> deines Lebens</span><span class="en">For every <em>season</em> of your life</span></h2></div>
    <p><span class="de">Ob du jeden Tag ein paar ruhige Minuten für dich suchst, eine Begleitung über drei Monate oder Unterstützung rund um die Geburt: Hier findest du deinen Weg.</span><span class="en">Whether you are looking for a few quiet minutes every day, three months of guidance or support around birth: here you find your way.</span></p></div>
  <div class="offers">
    <a class="offer o-method rv" href="/method/"><span class="onum">01</span><div class="art"><img src="/img/movecia/fotos/ev-method-foto.jpg?v=3" alt="" loading="lazy"></div><?= mv_icons(['sonne','blatt','welle'], 'ico', 0) ?><div class="tx">
      <p class="k">On demand</p><h3>Mindful Movement Method</h3>
      <p><span class="de">Deine Videobibliothek für zu Hause: Flows, Somatic Movement, Breathwork und Meditation, jeden Monat ein neues Programm mit Workbook.</span><span class="en">Your video library at home: flows, somatic movement, breathwork and meditation, a new programme with workbook every month.</span></p>
      <div class="meta"><span class="chip"><span class="de">ab <?= $eu((int)round($c['m12'] / 12)) ?> im Monat</span><span class="en">from <?= $eu((int)round($c['m12'] / 12)) ?> a month</span></span><span class="chip">DE · EN</span></div>
      <p class="more"><span class="de">Zur Method</span><span class="en">See the Method</span> →</p></div></a>
    <a class="offer o-unfold rv" href="/unfold/"><span class="onum">02</span><div class="art"><img src="/img/movecia/fotos/ev-unfold-karte.jpg?v=10" alt="" loading="lazy"></div><?= mv_icons(['knospe','bluete','spirale'], 'ico', 1.5) ?><div class="tx">
      <p class="k"><span class="de">3 Monate Begleitung</span><span class="en">3 months of guidance</span></p><h3>Unfold</h3>
      <p><span class="de">Dein 3-Monats-Begleiter: wöchentlicher Live-Call mit Impulsvortrag, zwei Bewegungsvideos pro Woche, Workbooks für Mental Load, Selbstwert und deine Ideen.</span><span class="en">Your 3-month companion: a weekly live call with a talk, two movement videos a week, workbooks for mental load, self-worth and your ideas.</span></p>
      <div class="meta"><span class="chip"><span class="de">Start <?= mv_date($st) ?></span><span class="en">Starts <?= mv_date($st, 'en') ?></span></span><span class="chip"><span class="de">max. <?= (int)$c['u_seats'] ?> Plätze</span><span class="en">max. <?= (int)$c['u_seats'] ?> spots</span></span></div>
      <p class="more"><?= $uFree > 0 ? '<span class="de">Platz sichern</span><span class="en">Book your spot</span>' : '<span class="de">Warteliste</span><span class="en">Waitlist</span>' ?> →</p></div></a>
    <a class="offer o-natal rv" href="/natal/"><span class="onum">03</span><div class="art"><img src="/img/movecia/fotos/ev-natal.jpg?v=10" alt="" loading="lazy"></div><?= mv_icons(['mond','herz','atem'], 'ico', 3) ?><div class="tx">
      <p class="k">1:1 · <span class="de">Studio &amp; online</span><span class="en">studio &amp; online</span></p><h3><span class="de">Prä- &amp; Postnatal</span><span class="en">Pre- &amp; postnatal</span></h3>
      <p><span class="de">Achtsames Training in der Schwangerschaft und danach, mit Atem, Beckenboden und Impulsen für eine leichtere Geburt und ein sanftes Ankommen danach.</span><span class="en">Mindful training during pregnancy and after, with breath, pelvic floor work and guidance for an easier birth and a gentle arrival afterwards.</span></p>
      <div class="meta"><span class="chip"><span class="de">ab <?= $eu($natalMin) ?> pro Einheit</span><span class="en">from <?= $eu($natalMin) ?> per session</span></span><span class="chip"><span class="de">Kennenlernen gratis</span><span class="en">free intro call</span></span></div>
      <p class="more"><span class="de">Mehr erfahren</span><span class="en">Learn more</span> →</p></div></a>
    <a class="offer o-gather rv" href="/gathering/"><span class="onum">04</span><div class="art"><img src="/img/movecia/fotos/ev-gathering.jpg?v=10" alt="" loading="lazy"></div><?= mv_icons(['kerze','kreis','berg'], 'ico', 4.5) ?><div class="tx">
      <p class="k"><span class="de">Live · kostenlos</span><span class="en">Live · free</span></p><h3>Gatherings</h3>
      <p><span class="de">Einmal im Monat treffen wir uns live: ein ruhiger Abend mit Bewegung, Atem und Gesprächen, offen für alle Frauen.</span><span class="en">Once a month we meet live: a calm evening with movement, breath and conversation, open to all women.</span></p>
      <div class="meta"><span class="chip"><?= $g ? '<span class="de">nächstes: ' . forma_h(mm_when_de($g)) . '</span><span class="en">next: ' . forma_h(mm_when($g)) . '</span>' : '<span class="de">monatlich</span><span class="en">monthly</span>' ?></span></div>
      <p class="more"><span class="de">Dabei sein</span><span class="en">Join</span> →</p></div></a>
  </div>
</div></section>

<section class="w sec"><div class="in">
  <div class="rv" style="text-align:center"><p class="k"><span class="de">Der Weg</span><span class="en">The path</span></p><h2 style="margin-top:.6rem"><span class="de">In drei <em>Schritten</em> zurück zu dir</span><span class="en">Back to yourself in three <em>steps</em></span></h2></div>
  <div class="steps rv">
    <div><span class="n step-ico"><?= mv_icons(['atem','welle'], 'si', 0) ?><b>1</b></span><h3><span class="de">Ankommen</span><span class="en">Arrive</span></h3><p><span class="de">Du lernst wieder zu spüren, was dein Körper braucht, und beruhigst dein Nervensystem mit Atem und sanfter Bewegung.</span><span class="en">You learn to feel what your body needs again and calm your nervous system with breath and gentle movement.</span></p></div>
    <div><span class="n step-ico"><?= mv_icons(['berg','blatt'], 'si', 2) ?><b>2</b></span><h3><span class="de">Kraft aufbauen</span><span class="en">Build strength</span></h3><p><span class="de">Mit Bewegung ohne Erschöpfung wächst deine Kraft, in deinem Tempo und ohne Leistungsdruck.</span><span class="en">With movement without exhaustion your strength grows, at your pace and without pressure.</span></p></div>
    <div><span class="n step-ico"><?= mv_icons(['bluete','sonne'], 'si', 4) ?><b>3</b></span><h3><span class="de">Entfalten</span><span class="en">Unfold</span></h3><p><span class="de">Du nimmst die Ruhe und die Kraft mit in deinen Alltag und gehst die Dinge an, die dir wirklich wichtig sind.</span><span class="en">You take the calm and the strength into your everyday life and go after what truly matters to you.</span></p></div>
  </div>
</div></section>

<section class="w sec"><div class="in">
  <div class="rv"><p class="k"><span class="de">Meine Haltung</span><span class="en">My approach</span></p><h2 style="margin-top:.6rem"><span class="de">Was mir in jeder Stunde <em>wichtig</em> ist</span><span class="en">What <em>matters</em> to me in every session</span></h2></div>
  <div class="values rv">
    <div><span>01</span><b><span class="de">Wahrnehmen statt Autopilot</span><span class="en">Awareness over autopilot</span></b><p><span class="de">Du bewegst dich bewusst und spürst, was gerade passiert.</span><span class="en">You move consciously and feel what is happening right now.</span></p></div>
    <div><span>02</span><b><span class="de">Erkunden statt Perfektion</span><span class="en">Exploring over perfection</span></b><p><span class="de">Es gibt keine perfekte Haltung, nur deine eigene Art, dich zu bewegen.</span><span class="en">There is no perfect pose, only your own way of moving.</span></p></div>
    <div><span>03</span><b><span class="de">Anpassen statt starrer Regeln</span><span class="en">Adapting over rigid rules</span></b><p><span class="de">Jeder Tag ist anders, deshalb gibt es Varianten für volle und für müde Tage.</span><span class="en">Every day is different, so there are options for full days and for tired days.</span></p></div>
    <div><span>04</span><b><span class="de">Wissen statt leerer Versprechen</span><span class="en">Knowledge over empty promises</span></b><p><span class="de">Was ich weitergebe, beruht auf fundierter Ausbildung und vielen Jahren Praxis.</span><span class="en">What I share is based on sound training and many years of practice.</span></p></div>
    <div><span>05</span><b><span class="de">Verbindung statt Leistungsdruck</span><span class="en">Connection over pressure</span></b><p><span class="de">Du bist nicht allein: Wir bewegen uns gemeinsam, auch wenn jede bei sich zu Hause ist.</span><span class="en">You are not alone: we move together, even when everyone is at home.</span></p></div>
  </div>
</div></section>

<section class="w sec" id="vivien"><div class="in about">
  <div class="art arch tall rv"><img src="/img/movecia/fotos/ev-ueber-mich.jpg?v=1" alt="Vivien beim Seitstretch in ihrem Studio mit Bergblick" loading="lazy"></div>
  <div class="rv">
    <p class="k"><span class="de">Hallo, ich bin Vivien</span><span class="en">Hi, I'm Vivien</span></p>
    <h2 style="margin-top:.6rem"><span class="de">Mama von vier Kindern, <em>Bewegungstrainerin</em> und Frau, die selbst neu anfangen musste.</span><span class="en">Mother of four, <em>movement teacher</em> and a woman who had to begin again herself.</span></h2>
    <p style="margin-top:1.2rem"><span class="de">2019 bin ich schwanger nach Tirol gezogen, ohne zu wissen, wie es beruflich weitergehen soll. Nach der Geburt habe ich angefangen, Yogastunden zu geben, und daraus ist meine Ausbildung gewachsen: rund 700 Stunden Yogalehrerausbildung und Bewegungslehre sowie knapp 400 Stunden Rehabilitation, Prävention und Gesundheitstraining, dazu Weiterbildungen in somatischer Bewegung, Nervensystem-Regulation, Yoga Nidra und prä- und postnatalem Training.</span><span class="en">In 2019 I moved to Tyrol, pregnant, without knowing what my work would look like. After the birth I started teaching yoga, and my training grew from there: around 700 hours of yoga teacher training and movement education and almost 400 hours of rehabilitation, prevention and health training, plus further training in somatic movement, nervous system regulation, yoga nidra and pre- and postnatal training.</span></p>
    <p><span class="de">Seit 2021 unterrichte ich, inzwischen sind es über 2.000 Stunden. Was ich dabei gelernt habe: Frauen brauchen nicht noch mehr Programme, die sie antreiben. Sie brauchen einen Ort, an dem sie wieder bei sich ankommen. Genau das ist Movecia.</span><span class="en">I have been teaching since 2021, by now more than 2,000 hours. What I have learned: women don't need more programmes that push them. They need a place where they can come home to themselves. That is exactly what Movecia is.</span></p>
    <div class="facts"><span><span class="de">seit 2021 unterrichtend</span><span class="en">teaching since 2021</span></span><span><span class="de">über 2.000 Unterrichtsstunden</span><span class="en">2,000+ teaching hours</span></span><span><span class="de">Paznaun, Tirol</span><span class="en">Paznaun, Tyrol</span></span></div>
    <p class="sig"><span class="de">Schön, dass du da bist.</span><span class="en">So lovely that you are here.</span></p>
  </div>
</div></section>

<section class="w sec"><div class="in split">
  <div class="box rv">
    <p class="k"><span class="de">Persönlich im Paznaun</span><span class="en">In person in the Paznaun valley</span></p>
    <h3 style="font-size:2rem;margin-top:.5rem"><span class="de">1:1 Personal Training</span><span class="en">1:1 personal training</span></h3>
    <p class="mut" style="margin-top:.6rem"><span class="de">Im Studio in Mathon oder draußen in der Natur: Training, das zu deinem Körper und deinem Alltag passt. Auch für Gäste im Urlaub.</span><span class="en">In the studio in Mathon or outdoors in nature: training that fits your body and your everyday life. Also for holiday guests.</span></p>
    <p style="margin-top:1.2rem"><a class="btn line" href="mailto:hello@moveciamovement.com?subject=Personal%20Training"><span class="de">Anfrage schicken</span><span class="en">Send a request</span></a></p>
  </div>
  <div class="box dark rv">
    <p class="k"><span class="de">Geschenk für dich</span><span class="en">A gift for you</span></p>
    <h3 style="font-size:2rem;margin-top:.5rem"><span class="de">Reset-Routine für müde Tage</span><span class="en">Reset routine for tired days</span></h3>
    <p style="margin-top:.6rem"><span class="de">Ein kleines PDF mit Bewegung, Atem und Ritualen für Tage, an denen nichts mehr geht. Dazu Einladungen zu den Gatherings.</span><span class="en">A small PDF with movement, breath and rituals for days when nothing works anymore. Plus invitations to the Gatherings.</span></p>
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
<script>
(function(){
  var f=document.getElementById('rf'),m=document.getElementById('rmsg'),t0=Date.now();
  f.addEventListener('submit',async function(ev){ev.preventDefault();var de=document.documentElement.dataset.l==='de';
    var d=Object.fromEntries(new FormData(f));d.source='reset';d.lang=document.documentElement.dataset.l;d.elapsed=Date.now()-t0;
    try{var r=await fetch('/api/wh-subscribe.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      m.textContent=j.ok?(de?'Fast geschafft! Bitte bestätige deine E-Mail-Adresse in deinem Postfach.':'Almost there! Please confirm your email in your inbox.'):(j.error||'…');}
    catch(x){m.textContent=de?'Das hat gerade nicht geklappt.':'That did not work.';}
    m.hidden=false;});
})();
</script>
</body>
</html>

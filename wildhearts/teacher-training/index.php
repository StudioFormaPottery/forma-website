<?php
// moveciamovement.com/teacher-training – Teaser + Warteliste fürs Mindful Becoming Teacher Training (Vivien, 28.10.2026).
// Start November 2027, insgesamt 550 Stunden, Yoga · Somatic Movement · Bodyweight. Nie „Pilates“ nennen, noch keine Preise.
// Eintragen über api/wh-subscribe.php mit source „teacher“ (Double-Opt-in, MailerLite-Gruppe „Teacher Training“, Info an Viv).
declare(strict_types=1);
require_once __DIR__ . '/../assets/icons.inc.php';
require dirname(__DIR__, 2) . '/api/method-lib.php';
require __DIR__ . '/../assets/layout.inc.php';
header('Cache-Control: no-cache');
$site = mm_site();
$course = ['@context' => 'https://schema.org', '@type' => 'Course', 'name' => 'Mindful Becoming Teacher Training', 'inLanguage' => ['de', 'en'],
    'description' => 'Ausbildung in Yoga, Somatic Movement und Bodyweight mit insgesamt 550 Stunden bei Vivien Malonga (Movecia). Start im November 2027.',
    'provider' => ['@type' => 'Organization', 'name' => 'Movecia', 'url' => $site . '/']];
mvl_head('Mindful Becoming Teacher Training · 550 Stunden · ab November 2027 | Movecia',
    'Mindful Becoming Teacher Training mit Vivien Malonga: Ausbildung in Yoga, Somatic Movement und Bodyweight, insgesamt 550 Stunden, Start im November 2027. Jetzt auf die Liste setzen.',
    $site . '/teacher-training/', '/img/movecia/og-start.jpg', '', [$course]);
?>
<body>
<?php mvl_top('teacher'); ?>
<main>
<section class="w"><div class="in hero-x">
  <?= mv_waves() ?>
  <p class="k"><span class="de">Bald bei Movecia · Start November 2027</span><span class="en">Coming soon to Movecia · starts November 2027</span></p>
  <h1 style="font-size:clamp(2.8rem,8vw,6.6rem)">The Mindful Becoming <em>Teacher Training</em></h1>
  <p class="sub"><span class="de">550 Stunden · Yoga · Somatic Movement · Bodyweight</span><span class="en">550 hours · yoga · somatic movement · bodyweight</span></p>
  <p class="lead"><span class="de">Du möchtest selbst unterrichten, achtsam und nah am Körper? In meiner Ausbildung lernst du, Menschen sicher und liebevoll durch Bewegung zu begleiten, mit Yoga, Somatic Movement und Bodyweight. Alle Details, Termine und Preise folgen bald.</span><span class="en">Would you love to teach, mindfully and close to the body? In my training you'll learn to guide people through movement safely and with care, using yoga, somatic movement and bodyweight training. All the details, dates and prices are coming soon.</span></p>
  <div class="cta"><a class="btn" href="#liste"><span class="de">Setz deinen Namen auf die Liste</span><span class="en">Put your name on the list</span> →</a></div>
</div>
<div class="in"><div class="art wide soft rv"><img src="/img/movecia/fotos/ev-ueber-mich-weit.jpg?v=1" alt="Vivien in einer Seitdehnung im lichten Studio mit Bergblick" style="width:100%;display:block;aspect-ratio:16/9;object-fit:cover;object-position:50% 60%"></div></div></section>

<section class="w sec"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Was dich erwartet</span><span class="en">What to expect</span></p><h2 style="margin-top:.6rem"><span class="de">Unterrichten, das von <em>innen</em> kommt</span><span class="en">Teaching that comes from <em>within</em></span></h2></div></div>
  <div class="tt-grid cute-cards">
    <div class="tt-card rv"><?= mv_icon_svg('sonne') ?><h3>550 <span class="de">Stunden</span><span class="en">hours</span></h3><p class="mut"><span class="de">Eine fundierte Ausbildung mit viel Zeit zum Üben, Fühlen und Unterrichten.</span><span class="en">An in-depth training with plenty of time to practise, feel and teach.</span></p></div>
    <div class="tt-card rv"><?= mv_icon_svg('blatt') ?><h3><span class="de">Drei Wege der Bewegung</span><span class="en">Three ways of moving</span></h3><p class="mut"><span class="de">Yoga, Somatic Movement und Bodyweight, verbunden zu einer achtsamen Praxis.</span><span class="en">Yoga, somatic movement and bodyweight, woven together into one mindful practice.</span></p></div>
    <div class="tt-card rv"><?= mv_icon_svg('welle') ?><h3><span class="de">Start im November 2027</span><span class="en">Starting November 2027</span></h3><p class="mut"><span class="de">Wer auf der Liste steht, erfährt alle Infos als Erste, noch vor der offiziellen Ankündigung.</span><span class="en">Everyone on the list hears all the details first, before the official announcement.</span></p></div>
  </div>
</div></section>

<section class="w sec" id="liste"><div class="in">
  <div class="box dark rv tt-join">
    <div>
      <p class="k"><span class="de">Warteliste</span><span class="en">Waiting list</span></p>
      <h2 style="margin-top:.5rem;font-size:clamp(1.9rem,4vw,2.8rem)"><span class="de">Setz deinen Namen auf die Liste</span><span class="en">Put your name on the list</span></h2>
      <p style="margin-top:.6rem"><span class="de">Trag dich ein und bestätige deine E-Mail-Adresse. Sobald alle Infos feststehen, schreibe ich dir als Erste. Als kleines Dankeschön bekommst du meine Reset-Routine für müde Tage.</span><span class="en">Leave your details and confirm your email. As soon as everything is ready, you'll be the first to hear from me. As a little thank you, you'll get my Reset Routine for Tired Days.</span></p>
    </div>
    <form id="tf" novalidate>
      <input type="text" name="name" placeholder="Vorname / first name" autocomplete="given-name" required maxlength="80">
      <input type="email" name="email" placeholder="E-Mail" autocomplete="email" required maxlength="160">
      <label class="check" style="color:#E3D6DA"><input type="checkbox" name="consent" value="1" required><span><span class="de">Ja, Vivien darf mir zum Teacher Training und zu Movecia schreiben. Abmelden geht jederzeit.</span><span class="en">Yes, Vivien may email me about the Teacher Training and Movecia. I can unsubscribe any time.</span></span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div><button class="btn light" type="submit" id="tb"><span class="de">Auf die Liste</span><span class="en">Add me to the list</span></button></div>
      <p class="small" id="tmsg" hidden></p>
    </form>
  </div>
  <?php if (isset($_GET['ok'])): ?><p class="ok" style="margin-top:1rem;text-align:center"><span class="de">Danke, du stehst auf der Liste! Eine kleine Willkommensmail ist schon unterwegs. 🤍</span><span class="en">Thank you, you're on the list! A little welcome email is already on its way. 🤍</span></p><?php endif; ?>
</div></section>

<section class="w sec"><div class="in split">
  <div class="box rv"><p class="k"><span class="de">Hallo, ich bin Vivien</span><span class="en">Hi, I'm Vivien</span></p>
    <p style="margin-top:.6rem"><span class="de">Seit 2021 unterrichte ich, inzwischen sind es über 2.000 Stunden. In meiner Ausbildung gebe ich dir alles weiter, was ich auf diesem Weg gelernt habe: wie du Bewegung so anleitest, dass sich Menschen sicher, gesehen und wieder zu Hause in ihrem Körper fühlen.</span><span class="en">I have been teaching since 2021, more than 2,000 hours so far. In my training I'll pass on everything I've learned along the way: how to guide movement so that people feel safe, seen and at home in their bodies again.</span></p>
    <p style="margin-top:1rem"><a class="btn line" href="/#vivien"><span class="de">Mehr über mich</span><span class="en">More about me</span></a></p></div>
  <div class="box rv"><p class="k"><span class="de">Schon jetzt mit mir bewegen</span><span class="en">Move with me now</span></p>
    <p style="margin-top:.6rem"><span class="de">Bis zum Start kannst du meine Art zu unterrichten schon kennenlernen, zum Beispiel in der Mindful Movement Method, mit kurzen Videos für jeden Tag.</span><span class="en">Until we start, you can already get to know the way I teach, for example in the Mindful Movement Method, with short videos for every day.</span></p>
    <p style="margin-top:1rem"><a class="btn line" href="/method/"><span class="de">Zur Mindful Movement Method</span><span class="en">See the Mindful Movement Method</span></a></p></div>
</div></section>
</main>
<?php mvl_foot(); ?>
<style>
.tt-grid{display:grid;gap:14px}@media(min-width:900px){.tt-grid{grid-template-columns:repeat(3,1fr)}}
.tt-card{background:var(--card);border-radius:var(--r);padding:clamp(1.3rem,3vw,2rem);position:relative;overflow:hidden}
.tt-card h3{font-size:1.6rem;margin:.6rem 0 .4rem}
.tt-join{display:grid;gap:1.2rem}@media(min-width:900px){.tt-join{grid-template-columns:1.1fr 1fr;align-items:center}}
.tt-join form{margin-top:0}
</style>
<script>
(function(){
  var f=document.getElementById('tf'),m=document.getElementById('tmsg'),b=document.getElementById('tb'),t0=Date.now();
  f.addEventListener('submit',async function(ev){ev.preventDefault();var de=document.documentElement.dataset.l==='de';
    var d=Object.fromEntries(new FormData(f));d.source='teacher';d.lang=document.documentElement.dataset.l;d.elapsed=Date.now()-t0;
    b.disabled=true;
    try{var r=await fetch('/api/wh-subscribe.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      if(j.ok){m.textContent=j.already?(de?'Du stehst schon auf der Liste. Ich habe dir die Infos noch einmal geschickt. 🤍':'You\'re already on the list. I\'ve sent you the details again. 🤍'):(de?'Fast geschafft! Bitte bestätige deine E-Mail-Adresse in deinem Postfach. 🤍':'Almost there! Please confirm your email in your inbox. 🤍');f.querySelectorAll('input,label,div').forEach(function(x){x.hidden=true});}
      else m.textContent=j.error||'…';}
    catch(x){m.textContent=de?'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.':'That didn\'t work just now. Please try again.';}
    m.hidden=false;b.disabled=false;});
})();
</script>
</body>
</html>

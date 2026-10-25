<?php
// moveciamovement.com/method – Verkaufsseite der Movecia Mindful Movement Method (On-Demand-Mitgliedschaft).
// Buchung direkt hier (Stripe-Abo über api/method.php). Vor dem Start: Karte hinterlegen, erste Abbuchung am Starttag.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/method-lib.php';
require __DIR__ . '/../assets/layout.inc.php';
header('Cache-Control: no-cache');
$pdo = forma_db();
mv_schema($pdo);
try { mv_tick($pdo); } catch (Throwable $e) { error_log('MV tick: ' . $e->getMessage()); } // u. a. Freischalt-Mails am Starttag
$c = mv_cfg();
$eu = fn(int $x) => '€ ' . ($x % 100 ? number_format($x / 100, 2, ',', '.') : number_format($x / 100, 0, ',', '.'));
$launch = mv_launch();
$pre = !mv_launched();
$site = mm_site();
$prod = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'Movecia Mindful Movement Method', 'brand' => ['@type' => 'Brand', 'name' => 'Movecia'], 'image' => $site . '/img/movecia/og-method.jpg',
    'description' => 'On-Demand-Mitgliedschaft mit Videos für Mindful Movement, Yoga-Flows, Somatic Movement, Breathwork und Meditation, jeden Monat ein neues Programm mit Workbook. Auf Deutsch und Englisch.',
    'offers' => array_map(fn($k, $n) => ['@type' => 'Offer', 'name' => $n, 'price' => number_format($c[$k] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock', 'url' => $site . '/method/#buchen'], ['m1', 'm3', 'm12'], ['Monatlich', '3 Monate', '1 Jahr'])];
mvl_head('Mindful Movement Method · Online Yoga, Somatic Movement & Breathwork on demand | Movecia', 'Online-Mitgliedschaft mit Vivien Malonga: Yoga-Flows, Somatic Movement, Breathwork und Meditation on demand, 5–30 Minuten, für volle und müde Tage. Jeden Monat ein neues Programm mit Workbook. Ab € ' . round($c['m12'] / 1200) . ' im Monat.', $site . '/method/', '/img/movecia/og-method.jpg', '', [$prod]);
?>
<body>
<?php mvl_top('method'); ?>
<main>
<section class="w"><div class="in hero-x" style="display:grid;gap:2.4rem;align-items:center;grid-template-columns:minmax(0,1fr)">
  <div>
    <p class="k"><span class="de">On demand · Deutsch &amp; English</span><span class="en">On demand · English &amp; German</span></p>
    <h1><span class="de">Mindful<br><em>Movement</em> Method</span><span class="en">Mindful<br><em>Movement</em> Method</span></h1>
    <p class="sub"><span class="de">Ein paar ruhige Minuten für dich, jeden Tag.</span><span class="en">A few quiet minutes for you, every day.</span></p>
    <p class="lead"><span class="de">Deine Videobibliothek für zu Hause: sanfte Flows, Somatic Movement, Breathwork, Meditation und Kraft ohne Erschöpfung. Du wählst nach Zeit und Tagesform, und jeden Monat kommt ein neues Programm mit Workbook dazu.</span><span class="en">Your video library at home: gentle flows, somatic movement, breathwork, meditation and strength without exhaustion. You choose by time and how you feel, and every month a new programme with workbook arrives.</span></p>
    <div class="cta"><a class="btn" href="#buchen"><span class="de">Mitglied werden</span><span class="en">Become a member</span> →</a><a class="btn line" href="#inhalt"><span class="de">Was dich erwartet</span><span class="en">What's inside</span></a></div>
    <?php if ($pre): ?><div class="facts"><span><span class="de">Start am <?= mv_date($launch) ?></span><span class="en">Opens on <?= mv_date($launch, 'en') ?></span></span><span><span class="de">jetzt buchen, bezahlt wird erst ab dem Start</span><span class="en">book now, pay from the start</span></span></div><?php endif; ?>
  </div>
</div>
<div class="in"><div class="art wide rv"><img src="/img/movecia/fotos/art-fold.jpg" alt="" fetchpriority="high"></div></div></section>

<section class="w sec" id="inhalt"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">In der Bibliothek</span><span class="en">In the library</span></p><h2 style="margin-top:.6rem"><span class="de">Bewegung, die sich <em>gut</em> anfühlt</span><span class="en">Movement that feels <em>good</em></span></h2></div>
    <p><span class="de">Zwischen 5 und 30 Minuten, für volle und für müde Tage. Du filterst einfach nach dem, was du heute brauchst.</span><span class="en">Between 5 and 30 minutes, for full days and tired days. You simply filter by what you need today.</span></p></div>
  <div class="cats">
    <div class="cat rv"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 34c6-10 12-10 18 0s12 10 18 0"/><circle cx="24" cy="13" r="4"/></svg><b>Movecia Movement</b><p><span class="de">Fließende Flows aus Yoga und freier Bewegung, die dich beweglich und weich machen.</span><span class="en">Flowing sequences of yoga and free movement that make you supple and soft.</span></p></div>
    <div class="cat rv"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M24 8c-7 6-7 14 0 20s7 14 0 20"/><path d="M14 18c4 3 4 9 0 12M34 18c-4 3-4 9 0 12"/></svg><b>Somatic Movement</b><p><span class="de">Langsame Bewegungen, die Spannung lösen und dein Nervensystem beruhigen.</span><span class="en">Slow movements that release tension and calm your nervous system.</span></p></div>
    <div class="cat rv"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M24 8v6M16 18h16M24 14v12l-6 14M24 26l6 14"/></svg><b><span class="de">Kraft ohne Erschöpfung</span><span class="en">Strength without exhaustion</span></b><p><span class="de">Training mit dem eigenen Körpergewicht für eine starke Mitte, ohne dich auszupowern.</span><span class="en">Bodyweight training for a strong centre, without wearing you out.</span></p></div>
    <div class="cat rv"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M10 20h20a5 5 0 1 0-5-5"/><path d="M8 28h26a5 5 0 1 1-5 5"/></svg><b>Breathwork</b><p><span class="de">Kurze Atemübungen, die dich auffangen, wenn es zu viel wird, und dir Energie geben, wenn sie fehlt.</span><span class="en">Short breathing practices that hold you when it gets too much and give you energy when it's missing.</span></p></div>
    <div class="cat rv"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="24" cy="13" r="4"/><path d="M10 38c4-6 9-9 14-9s10 3 14 9"/></svg><b>Meditation</b><p><span class="de">Geführte Meditationen für den Morgen, den Abend und alle Momente dazwischen.</span><span class="en">Guided meditations for the morning, the evening and every moment in between.</span></p></div>
    <div class="cat rv"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="9" y="7" width="30" height="34" rx="5"/><path d="M16 16h16M16 23h16M16 30h10"/></svg><b><span class="de">Workbook jeden Monat</span><span class="en">A workbook every month</span></b><p><span class="de">Mit Planner, Meal-Prep und Rezepten für Tired Days und Full Days, dazu dein privates Journal.</span><span class="en">With a planner, meal prep and recipes for tired days and full days, plus your private journal.</span></p></div>
  </div>
</div></section>

<section class="band"><div class="w"><div class="in">
  <p class="k"><span class="de">So funktioniert es</span><span class="en">How it works</span></p>
  <blockquote><span class="de">Kein Streak, kein Druck. Jeder Tag, an dem du dir Zeit schenkst, zählt.</span><span class="en">No streaks, no pressure. Every day you give yourself time counts.</span></blockquote>
  <p class="after"><span class="de">Am 1. jedes Monats öffnet sich ein neues Programm. Wer neu dazukommt, startet mit dem Ankommen-Modul und sieht danach alle bisherigen Monate. Niemand kommt zu spät.</span><span class="en">On the 1st of every month a new programme opens. New members start with the welcome module and then see all previous months. Nobody is ever too late.</span></p>
</div></div></section>

<section class="w sec" id="buchen"><div class="in">
  <div class="sec-h rv"><div><p class="k"><span class="de">Mitgliedschaft</span><span class="en">Membership</span></p><h2 style="margin-top:.6rem"><span class="de">Wähl deinen <em>Rhythmus</em></span><span class="en">Choose your <em>rhythm</em></span></h2></div>
    <p><span class="de">Jederzeit zum Ende des bezahlten Zeitraums kündbar, mit einem Klick im Mitgliederbereich.</span><span class="en">Cancel any time to the end of the paid period, with one click in your members area.</span></p></div>
  <form id="mf" novalidate>
  <div class="prices">
    <label class="pc"><input type="radio" name="plan" value="m1"><p class="k"><span class="de">Monatlich</span><span class="en">Monthly</span></p><p class="amt"><?= $eu($c['m1']) ?></p><p class="mut"><span class="de">pro Monat</span><span class="en">per month</span></p></label>
    <label class="pc"><input type="radio" name="plan" value="m3" checked><p class="k"><span class="de">3 Monate</span><span class="en">3 months</span></p><p class="amt"><?= $eu($c['m3']) ?></p><p class="mut"><span class="de">alle 3 Monate · <?= $eu((int)round($c['m3'] / 3)) ?> im Monat</span><span class="en">every 3 months · <?= $eu((int)round($c['m3'] / 3)) ?> a month</span></p></label>
    <label class="pc best"><input type="radio" name="plan" value="m12"><span class="flag"><span class="de">bester Wert</span><span class="en">best value</span></span><p class="k" style="color:var(--peach)"><span class="de">1 Jahr</span><span class="en">1 year</span></p><p class="amt"><?= $eu($c['m12']) ?></p><p class="mut"><span class="de">pro Jahr · <?= $eu((int)round($c['m12'] / 12)) ?> im Monat</span><span class="en">per year · <?= $eu((int)round($c['m12'] / 12)) ?> a month</span></p></label>
  </div>
  <div class="split" style="margin-top:14px">
    <div class="box">
      <div style="display:grid;gap:.8rem">
        <input type="text" name="name" placeholder="Vor- und Nachname / full name" autocomplete="name" required maxlength="80">
        <input type="email" name="email" placeholder="E-Mail" autocomplete="email" required maxlength="160">
        <fieldset class="wrf"><legend><span class="de">Dein Rücktrittsrecht</span><span class="en">Your right of withdrawal</span></legend>
          <label class="wro"><input type="radio" name="wr" value="verzicht"><span><b><span class="de">Gleich alles nutzen</span><span class="en">Use everything right away</span></b><small><span class="de">Ich verzichte auf mein Rücktrittsrecht.</span><span class="en">I waive my right of withdrawal.</span></small></span></label>
          <label class="wro"><input type="radio" name="wr" value="behalten"><span><b><span class="de">Rücktrittsrecht behalten</span><span class="en">Keep my right of withdrawal</span></b><small><span class="de">14 Tage lang, bis dahin ist das Ankommen-Modul offen.</span><span class="en">for 14 days, until then the welcome module is open.</span></small></span></label>
          <small class="wrn"><span class="de">Alle Details stehen in den</span><span class="en">All details are in the</span> <a href="/terms/#method" target="_blank"><span class="de">Bedingungen</span><span class="en">terms</span></a>.</small>
        </fieldset>
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div><button class="btn" type="submit" id="mb"><span class="de">Weiter zur Zahlung</span><span class="en">Continue to payment</span> →</button></div>
        <p class="err" id="me" hidden></p>
        <?php if (isset($_GET['abbruch'])): ?><p class="note"><span class="de">Die Zahlung wurde abgebrochen, es wurde nichts abgebucht.</span><span class="en">The payment was cancelled, nothing was charged.</span></p><?php endif; ?>
      </div>
    </div>
    <div class="box dark">
      <p class="k"><span class="de">Gut zu wissen</span><span class="en">Good to know</span></p>
      <p><?= $pre ? '<span class="de">Die Method startet am ' . mv_date($launch) . '. Du kannst jetzt schon buchen, deine Karte wird hinterlegt und die erste Zahlung kommt erst am Starttag. Bis dahin bekommst du eine Bestätigung per E-Mail, am Starttag schaltet sich dein Zugang frei. Dein 14-tägiges Rücktrittsrecht beginnt erst mit dem Start, du kannst also bis ' . mv_date(mv_withdraw_until(['kind' => 'method', 'paid_at' => date('c'), 'bill_start' => $launch])) . ' zurücktreten.</span><span class="en">The Method opens on ' . mv_date($launch, 'en') . '. You can book now, your card is saved and the first payment is only taken on the opening day. Until then you get a confirmation by email, and on the opening day your access unlocks. Your 14-day right of withdrawal only begins with the start, so you can withdraw until ' . mv_date(mv_withdraw_until(['kind' => 'method', 'paid_at' => date('c'), 'bill_start' => $launch]), 'en') . '.</span>' : '<span class="de">Nach der Zahlung legst du dein Passwort fest und kannst sofort loslegen, auf dem Handy, Tablet oder Computer.</span><span class="en">After payment you choose your password and can start right away, on your phone, tablet or computer.</span>' ?></p>
      <p><span class="de">Nach jeder Zahlung bekommst du eine Rechnung per E-Mail. Bezahlt wird sicher über Stripe.</span><span class="en">You receive an invoice by email after every payment. Payment is handled securely by Stripe.</span></p>
      <p><span class="de">Lieber mit persönlicher Begleitung in kleiner Runde? <a href="/unfold/" style="color:var(--peach)">Unfold</a> ist mein eigenes 3-Monats-Programm mit Live-Calls und Workbooks.</span><span class="en">Would you like personal guidance in a small circle? <a href="/unfold/" style="color:var(--peach)">Unfold</a> is my separate 3-month programme with live calls and workbooks.</span></p>
    </div>
  </div>
  </form>
</div></section>

<section class="w sec"><div class="in" style="max-width:900px">
  <p class="k"><span class="de">Fragen</span><span class="en">Questions</span></p>
  <h2 style="margin:.6rem 0 1rem"><span class="de">Gut zu <em>wissen</em></span><span class="en">Good to <em>know</em></span></h2>
  <details class="q"><summary><span class="de">Muss ich sportlich sein?</span><span class="en">Do I need to be fit?</span></summary><p><span class="de">Nein. Jede Einheit hat leichtere Varianten, und für müde Tage gibt es eigene, sanfte Programme.</span><span class="en">No. Every session has easier options, and there are gentle programmes just for tired days.</span></p></details>
  <details class="q"><summary><span class="de">Was brauche ich?</span><span class="en">What do I need?</span></summary><p><span class="de">Eine Matte, eine Decke und ein Polster reichen. Manchmal hilft ein Block oder eine Wand.</span><span class="en">A mat, a blanket and a cushion are enough. Sometimes a block or a wall helps.</span></p></details>
  <details class="q"><summary><span class="de">Gibt es alles auf Deutsch und Englisch?</span><span class="en">Is everything in English and German?</span></summary><p><span class="de">Ja, ich spreche jedes Video in beiden Sprachen ein. Du wählst deine Sprache im Mitgliederbereich.</span><span class="en">Yes, I record every video in both languages. You choose your language in the members area.</span></p></details>
  <details class="q"><summary><span class="de">Wie kündige ich?</span><span class="en">How do I cancel?</span></summary><p><span class="de">Im Mitgliederbereich unter Konto, jederzeit zum Ende des bezahlten Zeitraums. Danach wird nichts mehr abgebucht.</span><span class="en">In your members area under account, any time to the end of the paid period. Nothing more is charged after that.</span></p></details>
</div></section>
</main>
<?php mvl_foot(); ?>
<script>
(function(){
  var f=document.getElementById('mf'),e=document.getElementById('me'),b=document.getElementById('mb');
  f.addEventListener('submit',async function(ev){ev.preventDefault();e.hidden=true;var de=document.documentElement.dataset.l==='de';
    if(!f.name.value.trim()||f.email.value.indexOf('@')<1){e.textContent=de?'Bitte gib deinen Namen und deine E-Mail an.':'Please enter your name and email.';e.hidden=false;return;}
    var d=Object.fromEntries(new FormData(f));d.lang=document.documentElement.dataset.l;if(!f.querySelector('input[name=wr]:checked')){e.textContent=document.documentElement.dataset.l==='en'?'Please choose whether you keep your right of withdrawal.':'Bitte wähle, ob du dein Rücktrittsrecht behältst.';e.hidden=false;return;}b.disabled=true;
    try{var r=await fetch('/api/method.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      if(j.url){location.href=j.url;return;}e.textContent=j.error||'…';e.hidden=false;}
    catch(x){e.textContent=de?'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.':'That did not work. Please try again.';e.hidden=false;}
    b.disabled=false;});
})();
</script>
</body>
</html>

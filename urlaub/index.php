<?php
// Versteckte Buchungsseite für Urlaubsgäste (QR-Code auf dem Hotel-Flyer). Nicht verlinkt, nicht in der Suche.
declare(strict_types=1);
require dirname(__DIR__) . '/api/lib.php';
header('X-Robots-Tag: noindex, nofollow');
$en = ($_GET['lang'] ?? '') === 'en';
$spin = [];
try {
    $pdo = forma_db();
    foreach (forma_upcoming_courses($pdo) as $c) {
        if ($c['title'] === 'Spin & Go' && $c['free'] > 0) $spin[] = $c;
        if (count($spin) >= 6) break;
    }
} catch (Throwable $e) { error_log('FORMA urlaub: ' . $e->getMessage()); }
$T = $en ? [
    'title' => 'Pottery on holiday', 'kick' => 'Winter in Paznaun · FORMA studio in Mathon',
    'lead' => 'A creative break between your ski day and dinner: sit down at the pottery wheel in my small studio in Mathon, just a few minutes from Ischgl, and enjoy your time with the clay. No experience needed.',
    's_k' => 'Wheel class · 2 hours', 's_t' => 'Spin & Go', 's_d' => 'Two hours at the pottery wheel, guided from start to finish. Wednesday & Friday, 9–11 am, takes place from 2 people.',
    's_p' => '€ 70', 's_ps' => 'per person', 's_free' => 'places free', 's_btn' => 'Book a place', 's_none' => 'No free dates at the moment. Message me on WhatsApp and we will find a time: ',
    'p_k' => '1:1 at the wheel · 2 hours', 'p_t' => 'Private Pottery', 'p_d' => 'I take two hours just for you and your wishes: at your own pace at the pottery wheel, cosy with coffee or tea. Afterwards you pick your favourite piece from my current collection.',
    'p_p' => '€ 130', 'p_ps' => 'for 2 hours', 'p_how' => 'Pay online now, tell me two preferred dates and times, and I will confirm your appointment by email.',
    'w1' => '1st preferred date', 'w2' => '2nd preferred date', 'time' => 'Time', 'name' => 'First and last name', 'email' => 'Email', 'phone' => 'Phone / WhatsApp', 'opt' => '(optional)',
    'hotel' => 'Your hotel', 'consent' => 'I agree that my details are stored to process my booking.', 'privacy' => 'Privacy', 'pay' => 'Continue to payment · € 130',
    'gut' => 'A little souvenir for you', 'gut_t' => 'Pottery takes time: it takes a few weeks until a piece is truly finished, which sadly doesn’t fit into your holiday. That’s why my classes are all about the experience at the wheel. And so you still take something lovely home, you get to choose a finished piece from my current collection.',
    'err_d' => 'Please choose your 1st preferred date and time.', 'err_n' => 'Please enter your name.', 'err_e' => 'Please enter a valid email address.', 'err_c' => 'Please tick the privacy box.', 'wait' => 'One moment …', 'lang' => 'Deutsch', 'langurl' => '?',
] : [
    'title' => 'Töpfern im Urlaub', 'kick' => 'Winter im Paznaun · FORMA Atelier in Mathon',
    'lead' => 'Eine kreative Auszeit zwischen Skitag und Abendessen: In meinem kleinen Atelier in Mathon, nur wenige Minuten von Ischgl, setzt du dich an die Töpferscheibe und genießt die Zeit am Ton. Ganz ohne Vorkenntnisse.',
    's_k' => 'Kurs an der Drehscheibe · 2 Stunden', 's_t' => 'Spin & Go', 's_d' => 'Zwei Stunden an der Töpferscheibe, begleitet von Anfang an. Mittwoch & Freitag, 9–11 Uhr, findet ab 2 Personen statt.',
    's_p' => '€ 70', 's_ps' => 'pro Person', 's_free' => 'Plätze frei', 's_btn' => 'Platz buchen', 's_none' => 'Gerade sind keine Termine frei. Schreib mir per WhatsApp, dann finden wir einen: ',
    'p_k' => '1:1 an der Drehscheibe · 2 Stunden', 'p_t' => 'Private Pottery', 'p_d' => 'Ich nehme mir zwei Stunden Zeit nur für dich und deine Wünsche: ganz in deinem Tempo an der Drehscheibe, gemütlich bei Kaffee oder Tee. Danach suchst du dir dein Lieblingsstück aus meiner aktuellen Kollektion aus.',
    'p_p' => '€ 130', 'p_ps' => 'für 2 Stunden', 'p_how' => 'Du bezahlst gleich online, gibst mir zwei Wunschtermine mit Uhrzeit, und ich bestätige dir deinen Termin per E-Mail.',
    'w1' => '1. Wunschtermin', 'w2' => '2. Wunschtermin', 'time' => 'Uhrzeit', 'name' => 'Vor- und Nachname', 'email' => 'E-Mail', 'phone' => 'Telefon / WhatsApp', 'opt' => '(optional)',
    'hotel' => 'Dein Hotel', 'consent' => 'Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Buchung gespeichert werden.', 'privacy' => 'Datenschutz', 'pay' => 'Weiter zur Bezahlung · € 130',
    'gut' => 'Ein kleines Andenken für dich', 'gut_t' => 'Töpfern braucht Zeit: Bis ein Stück wirklich fertig ist, vergehen einige Wochen, und das geht sich während deines Urlaubs leider nicht aus. Deshalb geht es bei mir ganz ums Erlebnis an der Drehscheibe. Und damit du trotzdem etwas Schönes mit nach Hause nimmst, darfst du dir ein fertiges Stück aus meiner aktuellen Kollektion aussuchen.',
    'err_d' => 'Bitte wähl deinen 1. Wunschtermin mit Uhrzeit.', 'err_n' => 'Bitte gib deinen Namen an.', 'err_e' => 'Bitte gib eine gültige E-Mail-Adresse an.', 'err_c' => 'Bitte bestätige noch den Datenschutz.', 'wait' => 'Einen Moment …', 'lang' => 'English', 'langurl' => '?lang=en',
];
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?><!doctype html>
<html lang="<?= $en ? 'en' : 'de' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= $h($T['title']) ?> · FORMA</title><link rel="icon" type="image/png" href="../img/forma-logo.png">
<style>
@font-face{font-family:"Outfit";font-weight:300 500;font-display:swap;src:url(../fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:600 800;font-display:swap;src:url(../fonts/Outfit-Bold.woff) format("woff")}
@font-face{font-family:"Bricolage Grotesque";font-weight:600 800;font-display:swap;src:url(../fonts/BricolageGrotesque-Bold.woff) format("woff")}
@font-face{font-family:"Italiana";font-display:swap;src:url(../fonts/Italiana-Regular.woff) format("woff")}
:root{--sand:#EEE2D4;--card:#FBF1EA;--blush:#DDBFA8;--line:#D9C5B2;--ink:#33211B;--soft:#674B40;--clay:#8C2C1D}
*{box-sizing:border-box}body{margin:0;background:var(--sand);color:var(--ink);font:19px/1.6 "Outfit",system-ui,sans-serif}
.head{background:var(--blush);padding:28px 20px 48px;border-radius:0 0 50% 50%/0 0 40px 40px;position:relative;overflow:hidden}
.wrap{max-width:1100px;margin:0 auto}
.top{display:flex;justify-content:space-between;align-items:center}.top img{width:64px}.top a{color:var(--clay);font-weight:500}
.kick{font-family:"Italiana",serif;font-size:22px;color:var(--soft);margin:18px 0 0}
h1{font-family:"Bricolage Grotesque",sans-serif;font-weight:650;font-size:clamp(40px,7vw,68px);line-height:1;color:var(--clay);margin:8px 0 14px;letter-spacing:-.02em}
.lead{max-width:640px;margin:0}
.snow{position:absolute;opacity:.8;pointer-events:none}
main{padding:28px 20px 60px}
.cards{display:grid;grid-template-columns:1fr 1fr;gap:22px}@media (max-width:820px){.cards{grid-template-columns:1fr}}
.card{background:var(--card);border-radius:30px;padding:28px;border:2px dashed var(--clay)}
.card.dark{background:var(--clay);color:var(--card);border-color:var(--blush)}
.ok{font-size:14px;letter-spacing:.12em;text-transform:uppercase;color:var(--soft);margin:0}.dark .ok{color:var(--blush)}
h2{font-family:"Bricolage Grotesque",sans-serif;font-weight:650;font-size:36px;color:var(--clay);margin:4px 0 8px}.dark h2{color:var(--card)}
.price{font-family:"Bricolage Grotesque",sans-serif;font-size:40px;font-weight:650;margin:10px 0 4px}.price small{font-family:"Outfit";font-size:16px;font-weight:400;margin-left:8px}
.dates{list-style:none;padding:0;margin:16px 0 0;display:grid;gap:10px}
.dates li{display:flex;justify-content:space-between;align-items:center;gap:12px;background:var(--sand);border-radius:16px;padding:12px 14px;flex-wrap:wrap}
.dates b{font-weight:600}.dates span{color:var(--soft);font-size:16px}
.btn{display:inline-block;background:var(--clay);color:var(--card);border:0;border-radius:999px;padding:12px 22px;font:inherit;font-size:17px;font-weight:500;text-decoration:none;cursor:pointer}
.dark .btn{background:var(--card);color:var(--clay)}
form{display:grid;gap:12px;margin-top:16px}
label{font-size:16px;font-weight:500}
input{width:100%;font:inherit;font-size:18px;padding:12px 16px;border-radius:14px;border:1px solid var(--line);background:#fff;color:var(--ink)}
.two{display:grid;grid-template-columns:1.4fr 1fr;gap:10px}
.consent{display:flex;gap:10px;align-items:flex-start;font-size:15px;font-weight:400}.consent input{width:auto;margin-top:5px}
.consent a{color:var(--blush)}
.err{background:var(--card);color:var(--clay);border-radius:12px;padding:10px 14px;margin:0}
.gut{margin-top:24px;background:#E4D2BF;border-radius:24px;padding:22px 26px;display:flex;gap:18px;align-items:center}
.gut b{font-family:"Bricolage Grotesque",sans-serif;font-size:22px;color:var(--clay)}.gut p{margin:4px 0 0}
.gut svg{flex:0 0 56px}
.foot{text-align:center;color:var(--soft);font-size:16px;margin-top:28px}.foot a{color:var(--clay)}
</style>
</head>
<body>
<div class="head"><div class="wrap">
 <svg class="snow" style="right:6%;top:30px;width:40px" viewBox="0 0 64 64"><g stroke="#8C2C1D" stroke-width="3" stroke-linecap="round" fill="none"><path d="M32 6v52M9 19l46 26M9 45l46-26"/></g></svg>
 <svg class="snow" style="right:22%;top:90px;width:26px" viewBox="0 0 24 24"><path fill="#FBF1EA" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/></svg>
 <div class="top"><img src="../img/forma-logo.png" alt="FORMA Earth &amp; Art"><a href="<?= $h($T['langurl']) ?>"><?= $h($T['lang']) ?></a></div>
 <p class="kick"><?= $h($T['kick']) ?></p><h1><?= $h($T['title']) ?></h1><p class="lead"><?= $h($T['lead']) ?></p>
</div></div>
<main><div class="wrap">
 <div class="cards">
  <section class="card">
   <p class="ok"><?= $h($T['s_k']) ?></p><h2><?= $h($T['s_t']) ?></h2><p><?= $h($T['s_d']) ?></p>
   <p class="price"><?= $h($T['s_p']) ?><small><?= $h($T['s_ps']) ?></small></p>
   <?php if ($spin): ?><ul class="dates">
    <?php foreach ($spin as $c): ?><li><div><b><?= $h(preg_replace('/\s*·.*$/u', '', (string)$c['date_text'])) ?></b><br><span>9–11 · <?= (int)$c['free'] ?> <?= $h($T['s_free']) ?></span></div><a class="btn" href="../#spin-and-go"><?= $h($T['s_btn']) ?></a></li><?php endforeach; ?>
   </ul><?php else: ?><p><?= $h($T['s_none']) ?><a href="https://wa.me/4367763907080">+43 677 63 90 70 80</a></p><?php endif; ?>
  </section>
  <section class="card dark">
   <p class="ok"><?= $h($T['p_k']) ?></p><h2><?= $h($T['p_t']) ?></h2><p><?= $h($T['p_d']) ?></p>
   <p class="price"><?= $h($T['p_p']) ?><small><?= $h($T['p_ps']) ?></small></p><p><?= $h($T['p_how']) ?></p>
   <form id="pf" novalidate>
    <input type="hidden" name="kind" value="buchung"><input type="hidden" name="hours" value="2"><input type="hidden" name="contact" value="email">
    <input type="hidden" name="t" id="t"><input type="hidden" name="project" id="project"><input type="hidden" name="wish_date" id="wish_date"><input type="hidden" name="wish" id="wish">
    <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
    <label><?= $h($T['w1']) ?></label><div class="two"><input type="date" id="d1" min="<?= $tomorrow ?>"><input type="time" id="t1" step="900" placeholder="<?= $h($T['time']) ?>"></div>
    <label><?= $h($T['w2']) ?> <span style="font-weight:400"><?= $h($T['opt']) ?></span></label><div class="two"><input type="date" id="d2" min="<?= $tomorrow ?>"><input type="time" id="t2" step="900"></div>
    <label for="name"><?= $h($T['name']) ?></label><input id="name" name="name" autocomplete="name">
    <label for="email"><?= $h($T['email']) ?></label><input id="email" name="email" type="email" autocomplete="email">
    <label for="phone"><?= $h($T['phone']) ?> <span style="font-weight:400"><?= $h($T['opt']) ?></span></label><input id="phone" name="phone" type="tel" autocomplete="tel">
    <label for="hotel"><?= $h($T['hotel']) ?> <span style="font-weight:400"><?= $h($T['opt']) ?></span></label><input id="hotel">
    <label class="consent"><input type="checkbox" name="consent" id="consent" value="1"><span><?= $h($T['consent']) ?> <a href="../#datenschutz"><?= $h($T['privacy']) ?></a></span></label>
    <p class="err" id="err" hidden></p>
    <button class="btn" type="submit" id="go"><?= $h($T['pay']) ?></button>
   </form>
  </section>
 </div>
 <div class="gut"><svg viewBox="0 0 64 64"><rect x="10" y="26" width="44" height="28" rx="4" fill="#8C2C1D"/><rect x="7" y="19" width="50" height="10" rx="3" fill="#674B40"/><path d="M32 19v35" stroke="#FBF1EA" stroke-width="4"/><path d="M32 19c-4-9-14-10-13-4s9 5 13 4zm0 0c4-9 14-10 13-4s-9 5-13 4z" fill="none" stroke="#FBF1EA" stroke-width="3"/></svg>
  <div><b><?= $h($T['gut']) ?></b><p><?= $h($T['gut_t']) ?></p></div></div>
 <p class="foot">FORMA Earth &amp; Art · Silvrettastraße 58 · 6562 Mathon · <a href="https://wa.me/4367763907080">+43 677 63 90 70 80</a> · <a href="../">forma-pottery.com</a></p>
</div></main>
<script>
(function(){
  var f=document.getElementById('pf'),err=document.getElementById('err'),go=document.getElementById('go');
  document.getElementById('t').value=Date.now();
  function fmt(d,t){if(!d)return '';var p=d.split('-');return p[2]+'.'+p[1]+'.'+p[0]+(t?' um '+t:'');}
  f.addEventListener('submit',function(e){
    e.preventDefault();err.hidden=true;
    var d1=document.getElementById('d1').value,t1=document.getElementById('t1').value,d2=document.getElementById('d2').value,t2=document.getElementById('t2').value;
    var name=document.getElementById('name').value.trim(),email=document.getElementById('email').value.trim(),msg='';
    if(!d1||!t1)msg=<?= json_encode($T['err_d']) ?>;else if(name.length<2)msg=<?= json_encode($T['err_n']) ?>;else if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))msg=<?= json_encode($T['err_e']) ?>;else if(!document.getElementById('consent').checked)msg=<?= json_encode($T['err_c']) ?>;
    if(msg){err.textContent=msg;err.hidden=false;return;}
    var hotel=document.getElementById('hotel').value.trim();
    document.getElementById('wish_date').value=d1;
    document.getElementById('wish').value='1. Wunsch: '+fmt(d1,t1)+(d2?' · 2. Wunsch: '+fmt(d2,t2):'');
    document.getElementById('project').value='Private Pottery an der Drehscheibe, 2 Stunden (Urlaubsgast'+(hotel?', Hotel: '+hotel:'')+')';
    go.disabled=true;go.textContent=<?= json_encode($T['wait']) ?>;
    var wait=3200-(Date.now()-(+document.getElementById('t').value||0));
    setTimeout(function(){
      fetch('../api/private.php',{method:'POST',body:new FormData(f)}).then(function(r){return r.json().catch(function(){return {ok:false};});}).then(function(d){
        if(d&&d.ok&&d.redirect){location.href=d.redirect;return;}
        err.textContent=(d&&d.error)||'Das hat leider nicht geklappt. Bitte versuch es noch einmal.';err.hidden=false;go.disabled=false;go.textContent=<?= json_encode($T['pay']) ?>;
      }).catch(function(){err.textContent='Keine Verbindung.';err.hidden=false;go.disabled=false;go.textContent=<?= json_encode($T['pay']) ?>;});
    },Math.max(0,wait));
  });
})();
</script>
</body>
</html>

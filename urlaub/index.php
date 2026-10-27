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
        if ($c['title'] === 'Spin & Go') $spin[] = ['id' => (int)$c['id'], 'd' => (string)$c['start_date'], 'free' => (int)$c['free'] > 0 ? 1 : 0];
        if (count($spin) >= 120) break;
    }
} catch (Throwable $e) { error_log('FORMA urlaub: ' . $e->getMessage()); }
$T = $en ? [
    'title' => 'Pottery on holiday', 'kick' => 'Winter in the Paznaun Valley · FORMA studio in Mathon',
    'lead' => 'Discover the art of wheel throwing at Studio FORMA, nestled in the beautiful mountains of Tyrol. My small studio in Mathon is just a few minutes from Ischgl. No experience needed.',
    's_k' => 'Hands-on wheel workshop · 2 hours', 's_t' => 'Spin & Go', 's_d' => 'This 2-hour workshop introduces you to working on the potter’s wheel. Whether you’re touching clay for the first time, want to refine your skills or are simply looking for a creative break, there’s space to experiment, learn and create. Wednesdays and Fridays, 9–11 am, minimum 2 people.',
    's_p' => '€ 70', 's_ps' => 'per person', 's_free' => 'places available', 's_btn' => 'Book a place', 'cal_hint' => 'Tap a highlighted day to choose it.', 'cal_full' => 'fully booked', 'cal_one' => 'places available', 'cal_many' => 'places available', 'months' => ['January','February','March','April','May','June','July','August','September','October','November','December'], 'wd' => ['Mo','Tu','We','Th','Fr','Sa','Su'], 'prev' => 'Previous month', 'next' => 'Next month', 'free_l' => 'free', 'full_l' => 'fully booked', 's_none' => 'No dates available at the moment. Send me a message on WhatsApp and we’ll find a time that works: ',
    'p_k' => '1:1 at the wheel · 2 hours', 'p_t' => 'Private Pottery', 'p_d' => 'Two hours just for you at the potter’s wheel, at your own pace, with a cup of coffee or tea.',
    'p_p' => '€ 130', 'p_ps' => 'for 2 hours', 'p_how' => 'Pay online, give me two preferred dates and times, and I’ll confirm your booking by email.',
    'w1' => '1st preferred date', 'w2' => '2nd preferred date', 'time' => 'Time', 'name' => 'Full name', 'email' => 'Email', 'phone' => 'Phone / WhatsApp', 'opt' => '(optional)',
    'hotel' => 'Your hotel', 'consent' => 'I agree to my details being stored to process my booking.', 'privacy' => 'Privacy policy', 'gs' => 'Do you have a gift voucher?', 'gs_btn' => 'Check', 'gs_ok' => '✓ Voucher found: %s will be deducted from your booking.', 'post' => 'When you book, you’ll occasionally receive FORMA Post, my newsletter with news from the studio (in German). You can unsubscribe at any time with one click.', 'nopost' => 'No thanks, I don’t want to receive FORMA Post.', 'pay' => 'Continue to payment · € 130',
    'gut' => 'Good to know', 'gut_t' => 'Spin & Go is a purely hands-on workshop: the pieces you make can’t be taken home, as the focus is entirely on the experience and on learning the technique. If you’d like a keepsake to take home, you’ll find a small selection of handmade pieces from our studio to buy on site.',
    'err_d' => 'Please choose your 1st preferred date and time.', 'err_n' => 'Please enter your name.', 'err_e' => 'Please enter a valid email address.', 'err_c' => 'Please tick the privacy box.', 'wait' => 'One moment …', 'lang' => 'Deutsch', 'langurl' => '?', 'more' => 'Details & booking', 'less' => 'Close',
] : [
    'title' => 'Töpfern im Urlaub', 'kick' => 'Winter im Paznaun · FORMA Atelier in Mathon',
    'lead' => 'Erlebe die Kunst des Töpferns an der Drehscheibe im Studio FORMA, eingebettet in die wunderschöne Bergwelt Tirols. Mein kleines Atelier in Mathon liegt nur wenige Minuten von Ischgl entfernt. Vorkenntnisse brauchst du keine.',
    's_k' => 'Praxisworkshop an der Drehscheibe · 2 Stunden', 's_t' => 'Spin & Go', 's_d' => 'In 2 Stunden bekommst du eine Einführung in das Arbeiten an der Töpferscheibe. Ob du zum ersten Mal Ton in den Händen hältst, deine Technik vertiefen möchtest oder einfach eine kreative Auszeit suchst, hier ist Raum zum Ausprobieren, Lernen und Gestalten. Mittwochs und freitags, 9–11 Uhr, ab 2 Personen.',
    's_p' => '€ 70', 's_ps' => 'pro Person', 's_free' => 'Plätze frei', 's_btn' => 'Platz buchen', 'cal_hint' => 'Tipp auf einen markierten Tag, um ihn auszuwählen.', 'cal_full' => 'ausgebucht', 'cal_one' => 'Plätze frei', 'cal_many' => 'Plätze frei', 'months' => ['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'], 'wd' => ['Mo','Di','Mi','Do','Fr','Sa','So'], 'prev' => 'Vorheriger Monat', 'next' => 'Nächster Monat', 'free_l' => 'frei', 'full_l' => 'ausgebucht', 's_none' => 'Gerade sind keine Termine frei. Schreib mir per WhatsApp, dann finden wir gemeinsam einen Termin: ',
    'p_k' => '1:1 an der Drehscheibe · 2 Stunden', 'p_t' => 'Private Pottery', 'p_d' => '2 Stunden ganz für dich, in deinem Tempo an der Drehscheibe, gemütlich bei Kaffee oder Tee.',
    'p_p' => '€ 130', 'p_ps' => 'für 2 Stunden', 'p_how' => 'Du bezahlst gleich online, nennst mir zwei Wunschtermine mit Uhrzeit, und ich bestätige dir deinen Termin per E-Mail.',
    'w1' => '1. Wunschtermin', 'w2' => '2. Wunschtermin', 'time' => 'Uhrzeit', 'name' => 'Vor- und Nachname', 'email' => 'E-Mail', 'phone' => 'Telefon / WhatsApp', 'opt' => '(optional)',
    'hotel' => 'Dein Hotel', 'consent' => 'Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Buchung gespeichert werden.', 'privacy' => 'Datenschutz', 'gs' => 'Du hast einen Gutschein?', 'gs_btn' => 'Prüfen', 'gs_ok' => '✓ Gutschein gefunden: %s werden bei deiner Buchung abgezogen.', 'post' => 'Mit deiner Buchung bekommst du ab und zu die FORMA Post mit Neuem aus dem Atelier. Abmelden geht jederzeit mit einem Klick.', 'nopost' => 'Nein danke, ich möchte keine FORMA Post bekommen.', 'pay' => 'Weiter zur Bezahlung · € 130',
    'gut' => 'Gut zu wissen', 'gut_t' => 'Spin & Go ist ein reiner Praxisworkshop: Die Stücke, die dabei entstehen, können nicht mitgenommen werden, denn der Fokus liegt ganz auf dem Erlebnis und dem Erlernen der Technik. Wer sich ein Erinnerungsstück mit nach Hause nehmen möchte, findet vor Ort eine kleine Auswahl handgefertigter Stücke aus unserem Atelier zum Kaufen.',
    'err_d' => 'Bitte wähl deinen 1. Wunschtermin mit Uhrzeit.', 'err_n' => 'Bitte gib deinen Namen an.', 'err_e' => 'Bitte gib eine gültige E-Mail-Adresse an.', 'err_c' => 'Bitte bestätige noch den Datenschutz.', 'wait' => 'Einen Moment …', 'lang' => 'English', 'langurl' => '?lang=en', 'more' => 'Details & buchen', 'less' => 'Schließen',
];
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?><!doctype html>
<html lang="<?= $en ? 'en' : 'de' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= $h($T['title']) ?> · FORMA</title><link rel="icon" type="image/png" href="../img/forma-logo.png">
<link rel="stylesheet" href="../assets/gast.css?v=8">
<style>
.slides{position:relative}.slides img{position:absolute;inset:0;opacity:0;transition:opacity 1.4s ease}.slides img.on{opacity:1}
.acc-btn{display:none}
.two{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
input[type=date],input[type=time]{-webkit-appearance:none;appearance:none;display:block;width:100%;min-width:0;max-width:100%;min-height:52px;text-align:left}
input[type=date]::-webkit-date-and-time-value,input[type=time]::-webkit-date-and-time-value{text-align:left;margin:0}
@media (max-width:820px){
 .acc-btn{display:flex;align-items:center;justify-content:space-between;width:100%;margin-top:16px;padding:14px 20px;border-radius:999px;border:1.5px solid currentColor;background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer}
 .card:not(.dark) .acc-btn{color:#8C2C1D}
 .acc-btn i{width:10px;height:10px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);margin:-4px 4px 0 12px;transition:transform .25s}
 .acc-btn .l{display:none}
 .card.open .acc-btn .m{display:none}.card.open .acc-btn .l{display:inline}.card.open .acc-btn i{transform:rotate(-135deg);margin-top:4px}
 .acc-body{display:none;margin-top:18px}.card.open .acc-body{display:block}
}
@media (max-width:420px){.two{grid-template-columns:1fr}}
.card.dark .post-note{color:#F3DCCD}
@media (max-width:600px){.gut{flex-direction:column;align-items:flex-start;gap:10px}.gut .gi{flex:0 0 auto;width:52px}}
</style>
</head>
<body>
<div class="head"><div class="wrap">
 <span class="ic" style="right:7%;top:40px;width:38px"><svg viewBox="0 0 64 64"><g stroke="#8C2C1D" stroke-width="3" stroke-linecap="round" fill="none"><path d="M32 6v52M9 19l46 26M9 45l46-26"/><path d="M26 10l6 6 6-6M26 54l6-6 6 6"/></g></svg></span>
 <span class="ic" style="left:45%;top:60px;width:22px;animation-delay:-2s"><svg viewBox="0 0 24 24"><path fill="#FBF1EA" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/></svg></span>
 <span class="ic" style="left:52%;bottom:26px;width:46px;animation-delay:-3s"><svg viewBox="0 0 64 64"><path d="M2 54l20-32 10 14 8-10 22 28z" fill="#674B40"/><path d="M22 22l6 9-4-2-4 4-4-2z" fill="#FBF1EA"/><path d="M40 26l6 8-3-1-3 3-3-2z" fill="#FBF1EA"/></svg></span>
 <div class="top"><img src="../img/forma-logo.png" alt="FORMA Earth &amp; Art"><a href="<?= $h($T['langurl']) ?>"><?= $h($T['lang']) ?></a></div>
 <div class="hero"><div><p class="kick"><?= $h($T['kick']) ?></p><h1><?= $h($T['title']) ?></h1><p class="lead"><?= $h($T['lead']) ?></p></div>
 <div class="arch slides" id="slides"><img src="../img/scheiben-abend.jpg" alt="" class="on"><img src="../img/tonkugeln.jpg" alt="" loading="lazy"><img src="../img/toepferscheiben.jpg" alt="" loading="lazy"><img src="../img/becher-tonkugeln.jpg" alt="" loading="lazy"></div></div>
</div></div>
<main><div class="wrap">
 <div class="cards">
  <section class="card"><svg class="wv" aria-hidden="true"><path></path></svg><span class="cic"><svg viewBox="0 0 64 64"><ellipse cx="32" cy="48" rx="24" ry="7" fill="#674B40"/><ellipse cx="32" cy="45" rx="24" ry="7" fill="#DDBFA8"/><path d="M22 44c0-14 4-22 10-26 6 4 10 12 10 26z" fill="#8C2C1D"/><path d="M26 30c4 2 8 2 12 0" stroke="#FBF1EA" stroke-width="2" fill="none" stroke-linecap="round"/></svg></span>
   <p class="ok"><?= $h($T['s_k']) ?></p><h2><?= $h($T['s_t']) ?></h2>
   <p class="price"><?= $h($T['s_p']) ?><small><?= $h($T['s_ps']) ?></small></p>
   <button type="button" class="acc-btn" aria-expanded="false"><span class="m"><?= $h($T['more']) ?></span><span class="l"><?= $h($T['less']) ?></span><i aria-hidden="true"></i></button>
   <div class="acc-body"><p><?= $h($T['s_d']) ?></p>
   <?php if (array_filter($spin, fn($c) => $c['free'] > 0)): ?>
   <div class="mcal" id="mcal" data-dates="<?= $h(json_encode($spin)) ?>">
    <div class="mcal-head"><button type="button" class="mcal-nav" id="mc-prev" aria-label="<?= $h($T['prev']) ?>">‹</button><b id="mc-month"></b><button type="button" class="mcal-nav" id="mc-next" aria-label="<?= $h($T['next']) ?>">›</button></div>
    <div class="mcal-grid" id="mc-grid"></div>
    <p class="mcal-legend"><span class="lg free"></span><?= $h($T['free_l']) ?> <span class="lg full"></span><?= $h($T['full_l']) ?></p>
    <div class="mcal-pick" id="mc-pick"><p id="mc-text"><?= $h($T['cal_hint']) ?></p><a class="btn" id="mc-btn" href="../#spin-and-go" hidden><?= $h($T['s_btn']) ?></a></div>
   </div>
   <?php else: ?><p><?= $h($T['s_none']) ?><a href="https://wa.me/4367763907080">+43 677 63 90 70 80</a></p><?php endif; ?>
   </div>
  </section>
  <section class="card dark"><svg class="wv" aria-hidden="true"><path></path></svg><span class="cic"><svg viewBox="0 0 64 64"><path d="M32 54C18 45 10 38 10 28c0-6 5-11 11-11 5 0 8 3 11 7 3-4 6-7 11-7 6 0 11 5 11 11 0 10-8 17-22 26z" fill="#FBF1EA"/></svg></span>
   <p class="ok"><?= $h($T['p_k']) ?></p><h2><?= $h($T['p_t']) ?></h2>
   <p class="price"><?= $h($T['p_p']) ?><small><?= $h($T['p_ps']) ?></small></p>
   <button type="button" class="acc-btn" aria-expanded="false"><span class="m"><?= $h($T['more']) ?></span><span class="l"><?= $h($T['less']) ?></span><i aria-hidden="true"></i></button>
   <div class="acc-body"><p><?= $h($T['p_d']) ?></p><p><?= $h($T['p_how']) ?></p>
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
    <details class="gs-box"><summary><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M12 8c-1-3-5-4-5-1.5S10 8 12 8zm0 0c1-3 5-4 5-1.5S14 8 12 8z"/></svg><?= $h($T['gs']) ?></summary><div class="gs-row"><input name="voucher" class="gs-code" placeholder="FORMA-XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-label="Code"><button type="button" class="btn gs-check" data-ok="<?= $h($T['gs_ok']) ?>"><?= $h($T['gs_btn']) ?></button></div><p class="gs-msg" role="status" hidden></p></details>
    <label class="consent"><input type="checkbox" name="consent" id="consent" value="1"><span><?= $h($T['consent']) ?> <a href="../#datenschutz"><?= $h($T['privacy']) ?></a></span></label>
    <p class="post-note"><?= $h($T['post']) ?></p><label class="consent"><input type="checkbox" name="no_post" value="1"><span><?= $h($T['nopost']) ?></span></label>
    <p class="err" id="err" hidden></p>
    <button class="btn" type="submit" id="go"><?= $h($T['pay']) ?></button>
   </form>
   </div>
  </section>
 </div>
 <div class="gut"><svg class="wv" aria-hidden="true"><path></path></svg><span class="gi"><svg viewBox="0 0 64 64"><rect x="10" y="26" width="44" height="28" rx="4" fill="#8C2C1D"/><rect x="7" y="19" width="50" height="10" rx="3" fill="#674B40"/><path d="M32 19v35" stroke="#FBF1EA" stroke-width="4"/><path d="M32 19c-4-9-14-10-13-4s9 5 13 4zm0 0c4-9 14-10 13-4s-9 5-13 4z" fill="none" stroke="#FBF1EA" stroke-width="3"/></svg></span>
  <div><b><?= $h($T['gut']) ?></b><p><?= $h($T['gut_t']) ?></p></div></div>
 <p class="foot">FORMA Earth &amp; Art · Mathon im Paznaun · <a href="https://wa.me/4367763907080">+43 677 63 90 70 80</a> · <a href="../">forma-pottery.com</a></p>
</div></main>
<script src="../assets/gast.js?v=3"></script>
<script>
(function(){
  var im=document.querySelectorAll('#slides img'),i=0;
  if(im.length>1&&!(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches))setInterval(function(){im[i].classList.remove('on');i=(i+1)%im.length;im[i].classList.add('on');},5000);
  document.querySelectorAll('.acc-btn').forEach(function(b){b.addEventListener('click',function(){var c=b.closest('.card'),o=c.classList.toggle('open');b.setAttribute('aria-expanded',o?'true':'false');if(o&&typeof window.dispatchEvent==='function')window.dispatchEvent(new Event('resize'));});});
})();
</script>
<script>
(function(){
  var box=document.getElementById('mcal');if(!box)return;
  var list=JSON.parse(box.getAttribute('data-dates')||'[]'),by={},months=[];
  list.forEach(function(c){by[c.d]=c;var m=c.d.slice(0,7);if(months.indexOf(m)<0)months.push(m);});
  months.sort();
  var MN=<?= json_encode($T['months']) ?>,WD=<?= json_encode($T['wd']) ?>,lang=<?= json_encode($en ? 'en' : 'de') ?>;
  var tFull=<?= json_encode($T['cal_full']) ?>,tOne=<?= json_encode($T['cal_one']) ?>,tMany=<?= json_encode($T['cal_many']) ?>;
  var first=list.filter(function(c){return c.free>0;})[0],cur=first?first.d.slice(0,7):months[0],sel=null;
  var grid=document.getElementById('mc-grid'),btn=document.getElementById('mc-btn'),txt=document.getElementById('mc-text');
  function freeT(n){return n<=0?tFull:(n===1?tOne:tMany.replace('%d',n));}
  function nice(d){var x=new Date(d+'T12:00:00');return x.toLocaleDateString(lang==='en'?'en-GB':'de-AT',{weekday:'long',day:'numeric',month:'long'});}
  function pick(c){sel=c;txt.innerHTML='<b>'+nice(c.d)+'</b> · 9–11 · '+freeT(c.free);btn.href='../?termin='+c.id;btn.hidden=false;render();}
  function render(){
    var p=cur.split('-'),y=+p[0],mo=+p[1]-1;
    document.getElementById('mc-month').textContent=MN[mo]+' '+y;
    var i=months.indexOf(cur);document.getElementById('mc-prev').disabled=i<=0;document.getElementById('mc-next').disabled=i>=months.length-1;
    grid.innerHTML='';WD.forEach(function(w){var s=document.createElement('span');s.className='wd';s.textContent=w;grid.appendChild(s);});
    var lead=(new Date(y,mo,1).getDay()+6)%7,days=new Date(y,mo+1,0).getDate();
    for(var k=0;k<lead;k++)grid.appendChild(document.createElement('span'));
    for(var d=1;d<=days;d++){
      var key=y+'-'+String(mo+1).padStart(2,'0')+'-'+String(d).padStart(2,'0'),c=by[key],e;
      if(!c){e=document.createElement('span');e.className='d';e.textContent=d;grid.appendChild(e);continue;}
      e=document.createElement('button');e.type='button';e.textContent=d;e.className='d on'+(c.free<=0?' full':'')+(sel&&sel.id===c.id?' sel':'');
      e.setAttribute('aria-label',nice(c.d)+', '+freeT(c.free));
      if(c.free<=0)e.disabled=true;else(function(c){e.addEventListener('click',function(){pick(c);});})(c);
      grid.appendChild(e);
    }
  }
  document.getElementById('mc-prev').addEventListener('click',function(){var i=months.indexOf(cur);if(i>0){cur=months[i-1];render();}});
  document.getElementById('mc-next').addEventListener('click',function(){var i=months.indexOf(cur);if(i<months.length-1){cur=months[i+1];render();}});
  render();if(first)pick(first);
})();
</script>
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
        err.textContent=(d&&d.error)||<?= json_encode($en ? 'Sorry, something went wrong. Please try again.' : 'Das hat leider nicht geklappt. Bitte versuch es noch einmal.') ?>;err.hidden=false;go.disabled=false;go.textContent=<?= json_encode($T['pay']) ?>;
      }).catch(function(){err.textContent=<?= json_encode($en ? 'No connection. Please check your internet and try again.' : 'Keine Verbindung. Bitte prüfe dein Internet und versuch es noch einmal.') ?>;err.hidden=false;go.disabled=false;go.textContent=<?= json_encode($T['pay']) ?>;});
    },Math.max(0,wait));
  });
})();
</script>
</body>
</html>

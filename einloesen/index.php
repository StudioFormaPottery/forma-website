<?php
// Versteckte Seite für Gäste mit altem Gutschein (ohne Nummer). Nur über den persönlichen Einmal-Link aus der Verwaltung erreichbar.
declare(strict_types=1);
require dirname(__DIR__) . '/api/lib.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$token = (string)($_GET['t'] ?? '');
$link = null; $spin = [];
$preview = $token === 'vorschau';
try {
    $pdo = forma_db();
    $link = $preview ? ['label' => 'Anna Muster', 'persons' => 1, 'status' => 'offen', 'value_cents' => 14000] : forma_old_link_get($pdo, $token);
    if ($link && $link['status'] === 'offen') {
        foreach (forma_upcoming_courses($pdo) as $c) {
            if ($c['title'] !== FORMA_SERIES['spin']['title']) continue;
            $time = preg_match('/(\d{1,2}(?::\d{2})?\s*[–-]\s*\d{1,2}(?::\d{2})?)\s*Uhr/u', (string)$c['date_text'], $m) ? str_replace(' ', '', $m[1]) . ' Uhr' : '';
            $spin[] = ['id' => (int)$c['id'], 'd' => (string)$c['start_date'], 'free' => (int)$c['free'], 'time' => $time];
            if (count($spin) >= 120) break;
        }
    }
} catch (Throwable $e) { error_log('FORMA einloesen: ' . $e->getMessage()); }

$valid = $link && $link['status'] === 'offen';
$plainName = $valid ? forma_old_link_name((string)$link['label'])[1] : '';
$first = $plainName !== '' ? forma_first_name($plainName) : '';
$persons = 1;
$what = '1 Platz im Spin & Go Workshop';
$rest = $valid ? max(0, (int)$link['value_cents'] - (int)FORMA_SERIES['spin']['price_cents']) : 0;
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Gutschein einlösen · Studio FORMA</title><link rel="icon" type="image/png" href="../img/forma-logo.png">
<link rel="stylesheet" href="../assets/gast.css?v=8">
<style>
.deco-slot{display:inline-block;width:40px;height:40px;transform:rotate(var(--r,0deg));animation:sway 7s ease-in-out infinite alternate;vertical-align:middle}
.deco-slot svg{width:100%;height:100%;display:block;filter:drop-shadow(0 3px 6px rgba(103,48,30,.14))}
.no-deco .deco-slot{display:none}
.head .deco-slot{position:absolute;z-index:0;pointer-events:none}
.head .h1{right:6%;top:38px;width:46px;height:46px}.head .h2{left:4%;bottom:34px;width:38px;height:38px}.head .h3{right:38%;top:26px;width:34px;height:34px}.head .h4{right:4%;bottom:40px;width:52px;height:52px}
@media (max-width:760px){.head .h1{right:30px;top:120px}.head .h3{left:24px;right:auto;top:130px}.head .h4{display:none}}
.deco-slot.in{width:32px;height:32px;margin-left:6px}
.foot-deco{display:flex;justify-content:center;gap:22px;margin:30px 0 0}
.pv{position:sticky;top:0;z-index:50;background:var(--clay);color:var(--card);text-align:center;padding:10px 16px;font-size:16px}
.gpill{display:inline-flex;align-items:center;gap:10px;background:var(--sand);border-radius:999px;padding:8px 18px 8px 10px;font-weight:500;margin:6px 0 4px}
.gpill svg{width:30px;height:30px;flex:0 0 30px}
.steps{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0 0;padding:0;list-style:none;font-size:16px;color:var(--soft)}
.steps li{background:var(--sand);border-radius:999px;padding:4px 14px}
.steps b{color:var(--clay)}
.sum{display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap;background:var(--sand);border-radius:18px;padding:14px 18px}
.sum s{color:var(--soft);font-size:17px;margin-right:8px}
.sum .total{color:var(--clay)}
.sec{font-family:"Bricolage Grotesque",sans-serif;font-weight:600;font-size:22px;color:var(--clay);margin:14px 0 0}
.chosen{background:var(--card);border:1.5px dashed var(--clay);border-radius:18px;padding:10px 16px;font-weight:500}
.chosen.empty{border-color:var(--line);color:var(--soft);font-weight:400}
.post-note{font-size:15.5px;color:var(--soft);margin:2px 0 -4px}
#form[hidden],.done[hidden]{display:none!important}
.done{border:2px solid var(--blush)}
.two.plz{grid-template-columns:minmax(0,1fr) minmax(0,2fr)}
.done p{margin:8px 0 0}
@media (max-width:520px){.card{padding:26px 18px}.mcal{padding:12px 10px 14px}}
</style>
</head>
<body>
<?php if ($preview): ?><div class="pv">👀 Vorschau: So sieht die Seite für deine Gäste aus. Hier wird nichts gebucht.</div><?php endif; ?>
<div class="head"><div class="wrap">
 <span class="deco-slot h1"></span><span class="deco-slot h2"></span><span class="deco-slot h3"></span><span class="deco-slot h4"></span>
 <div class="top"><img src="../img/forma-logo.png" alt="FORMA Earth &amp; Art"><a href="../">forma-pottery.com</a></div>
 <div class="hero"><div>
  <p class="kick">Studio FORMA · Atelier in Mathon</p>
  <?php if ($valid): ?>
  <h1>Schön, dass du zum Töpfern kommst<?= $first !== '' ? ', ' . $h($first) : '' ?>!</h1>
  <p class="lead">Du hast noch einen FORMA Gutschein ohne Nummer, und das ist überhaupt kein Problem. Über diesen persönlichen Link suchst du dir ganz einfach deinen Spin & Go Termin aus. Dein Gutschein ist schon bezahlt, du musst also nichts mehr bezahlen.</p>
  <?php else: ?>
  <h1>Dieser Link ist nicht mehr gültig</h1>
  <p class="lead">Dein persönlicher Gutschein-Link funktioniert nur einmal. Wenn du deinen Termin schon gebucht hast, findest du die Bestätigung in deinem E-Mail-Postfach. Sonst melde dich einfach bei mir, dann schicke ich dir gern einen neuen Link.</p>
  <?php endif; ?>
 </div><div class="arch"><img src="../img/drehen.jpg" alt=""></div></div>
</div></div>
<main><div class="wrap">
<?php if ($valid): ?>
 <div class="cards one">
  <section class="card"><svg class="wv" aria-hidden="true"><path></path></svg>
   <span class="cic"><svg viewBox="0 0 64 64"><rect x="10" y="26" width="44" height="28" rx="4" fill="#8C2C1D"/><rect x="7" y="19" width="50" height="10" rx="3" fill="#674B40"/><path d="M32 19v35" stroke="#FBF1EA" stroke-width="4"/><path d="M32 19c-4-9-14-10-13-4s9 5 13 4zm0 0c4-9 14-10 13-4s-9 5-13 4z" fill="none" stroke="#FBF1EA" stroke-width="3"/></svg></span>
   <p class="ok">Dein Gutschein</p>
   <h2>Spin &amp; Go</h2>
   <span class="gpill"><svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#DDBFA8"/><path d="M22 44c0-14 4-22 10-26 6 4 10 12 10 26z" fill="#8C2C1D"/><ellipse cx="32" cy="46" rx="16" ry="4" fill="#674B40"/></svg><?= $h($what) ?> · schon bezahlt</span>
   <p>Zwei Stunden an der Töpferscheibe, begleitet von Anfang an. Für Einsteiger und Fortgeschrittene, du brauchst keine Vorkenntnisse.</p>
   <ol class="steps"><li><b>1</b> Tag aussuchen</li><li><b>2</b> Deine Angaben</li><li><b>3</b> Fertig, Bestätigung kommt per Mail</li></ol>

   <div id="form-wrap">
   <?php if (array_filter($spin, fn($c) => $c['free'] > 0)): ?>
   <p class="sec">1 · Such dir deinen Tag aus <span class="deco-slot in"></span></p>
   <div class="mcal" id="mcal" data-dates="<?= $h(json_encode($spin)) ?>">
    <div class="mcal-head"><button type="button" class="mcal-nav" id="mc-prev" aria-label="Vorheriger Monat">‹</button><b id="mc-month"></b><button type="button" class="mcal-nav" id="mc-next" aria-label="Nächster Monat">›</button></div>
    <div class="mcal-grid" id="mc-grid"></div>
    <p class="mcal-legend"><span class="lg free"></span>frei <span class="lg full"></span>ausgebucht</p>
   </div>

   <form id="form" novalidate>
    <input type="hidden" name="t" value="<?= $h($token) ?>"><input type="hidden" name="course" id="course">
    <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
    <p class="chosen empty" id="chosen">Tipp oben im Kalender auf einen markierten Tag.</p>
    <p class="sec">2 · Deine Angaben <span class="deco-slot in"></span></p>
    <label for="name">Vor- und Nachname</label><input id="name" name="name" autocomplete="name" value="<?= $h($plainName) ?>">
    <label for="email">E-Mail</label><input id="email" name="email" type="email" autocomplete="email" inputmode="email">
    <label for="phone">Telefon / WhatsApp</label><input id="phone" name="phone" type="tel" autocomplete="tel">
    <label for="street">Rechnungsadresse: Straße und Hausnummer</label><input id="street" name="street" autocomplete="street-address">
    <div class="two plz"><div style="display:grid;gap:12px"><label for="zip">PLZ</label><input id="zip" name="zip" autocomplete="postal-code" inputmode="numeric"></div><div style="display:grid;gap:12px"><label for="city">Ort</label><input id="city" name="city" autocomplete="address-level2"></div></div>
    <label for="country">Land</label><input id="country" name="country" autocomplete="country-name" value="Österreich">
    <div class="sum"><span>Zu bezahlen</span><span><s><?= $h(forma_euro($persons * (int)FORMA_SERIES['spin']['price_cents'])) ?></s><span class="total">€ 0</span></span></div>
    <?php if ($rest > 0): ?><p class="chosen" style="margin:0">🎁 Auf deinem Gutschein bleiben danach noch <?= $h(forma_euro($rest)) ?> übrig. Dafür bekommst du mit der Bestätigung einen eigenen Gutscheincode, den du jederzeit bei einer nächsten Buchung einlösen kannst.</p><?php endif; ?>
    <p class="post-note" style="margin:0">Dein Gutschein wird automatisch eingelöst. Du bekommst die Terminbestätigung und die Rechnung (bezahlt mit Gutschein) per E-Mail.</p>
    <label class="consent"><input type="checkbox" name="consent" id="consent" value="1"><span>Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Buchung gespeichert werden. <a href="../#datenschutz">Datenschutz</a></span></label>
    <p class="post-note">Mit deiner Buchung bekommst du ab und zu die FORMA Post mit Neuem aus dem Atelier. Abmelden geht jederzeit mit einem Klick.</p>
    <label class="consent"><input type="checkbox" name="no_post" value="1"><span>Nein danke, ich möchte keine FORMA Post bekommen.</span></label>
    <p class="err" id="err" hidden></p>
    <button class="btn" type="submit" id="go">Termin verbindlich buchen</button>
   </form>
   <?php else: ?>
   <p class="err">Gerade sind leider keine Spin &amp; Go Termine frei. Schreib mir kurz per WhatsApp, dann finden wir gemeinsam einen Tag: <a href="https://wa.me/4367763907080">+43 677 63 90 70 80</a></p>
   <?php endif; ?>
   </div>
   <div class="done" id="done" hidden><b>Juhu, dein Termin ist fix! <span class="deco-slot in"></span></b><p id="done-text"></p><p>Bitte komm in Kleidung, die schmutzig werden darf. Schürzen und Werkzeug habe ich im Atelier. Ich freu mich schon sehr auf dich!</p><p>Bis bald,<br>Vivien</p></div>
  </section>
 </div>
<?php endif; ?>
 <p class="foot-deco"><span class="deco-slot"></span><span class="deco-slot"></span><span class="deco-slot"></span></p>
 <p class="foot">FORMA Earth &amp; Art · Silvrettastraße 58 · 6562 Mathon · <a href="https://wa.me/4367763907080">+43 677 63 90 70 80</a> · <a href="../">forma-pottery.com</a></p>
</div></main>
<script src="../assets/gast.js?v=3"></script>
<script src="../verwaltung/deko.js?v=1" defer></script>
<?php if ($valid && array_filter($spin, fn($c) => $c['free'] > 0)): ?>
<script>
(function(){
  var PREVIEW=<?= $preview ? 'true' : 'false' ?>;
  var box=document.getElementById('mcal'),list=JSON.parse(box.getAttribute('data-dates')||'[]'),by={},months=[];
  list.forEach(function(c){by[c.d]=c;var m=c.d.slice(0,7);if(months.indexOf(m)<0)months.push(m);});months.sort();
  var MN=['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],WD=['Mo','Di','Mi','Do','Fr','Sa','So'];
  var first=list.filter(function(c){return c.free>0;})[0],cur=first.d.slice(0,7),sel=null;
  var grid=document.getElementById('mc-grid'),chosen=document.getElementById('chosen'),course=document.getElementById('course');
  function nice(d){return new Date(d+'T12:00:00').toLocaleDateString('de-AT',{weekday:'long',day:'numeric',month:'long'});}
  function pick(c){sel=c;course.value=c.id;chosen.className='chosen';chosen.textContent='Dein Termin: '+nice(c.d)+(c.time?', '+c.time:'');render();}
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
      e.setAttribute('aria-label',nice(c.d)+(c.free<=0?', ausgebucht':''));
      if(c.free<=0)e.disabled=true;else(function(c){e.addEventListener('click',function(){pick(c);});})(c);
      grid.appendChild(e);
    }
  }
  document.getElementById('mc-prev').addEventListener('click',function(){var i=months.indexOf(cur);if(i>0){cur=months[i-1];render();}});
  document.getElementById('mc-next').addEventListener('click',function(){var i=months.indexOf(cur);if(i<months.length-1){cur=months[i+1];render();}});
  render();

  var f=document.getElementById('form'),err=document.getElementById('err'),go=document.getElementById('go');
  function v(id){return document.getElementById(id).value.trim();}
  f.addEventListener('submit',function(e){
    e.preventDefault();err.hidden=true;var msg='';
    if(!course.value)msg='Bitte tipp zuerst im Kalender auf deinen Wunschtag.';
    else if(v('name').length<2)msg='Bitte gib deinen Vor- und Nachnamen an.';
    else if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email')))msg='Bitte gib eine gültige E-Mail-Adresse an.';
    else if(v('phone').replace(/\D/g,'').length<6)msg='Bitte gib deine Telefonnummer an.';
    else if(!v('street')||!v('zip')||!v('city'))msg='Bitte gib deine Rechnungsadresse vollständig an.';
    else if(!document.getElementById('consent').checked)msg='Bitte bestätige noch den Datenschutz.';
    if(msg){err.textContent=msg;err.hidden=false;if(!course.value)box.scrollIntoView({behavior:'smooth',block:'center'});return;}
    go.disabled=true;go.textContent='Einen Moment …';
    if(PREVIEW){setTimeout(function(){
        document.getElementById('form-wrap').hidden=true;document.querySelector('.steps').hidden=true;
        document.getElementById('done-text').textContent='Dein Spin & Go Termin: '+chosen.textContent.replace('Dein Termin: ','')+'. Die Bestätigung mit Kalendereintrag und deine Rechnung (bezahlt mit Gutschein ALT-2026-001) sind unterwegs an '+v('email')+'. (Vorschau: es wurde nichts gebucht und keine Mail verschickt.)';
        var dn=document.getElementById('done');dn.hidden=false;dn.scrollIntoView({behavior:'smooth',block:'center'});},600);return;}
    fetch('../api/einloesen.php',{method:'POST',body:new FormData(f)}).then(function(r){return r.json().catch(function(){return {ok:false};});}).then(function(d){
      if(d&&d.ok){
        document.getElementById('form-wrap').hidden=true;document.querySelector('.steps').hidden=true;
        document.getElementById('done-text').textContent='Dein Spin & Go Termin: '+d.termin+'. Die Bestätigung mit Kalendereintrag und deine Rechnung (bezahlt mit Gutschein '+d.nr+') sind unterwegs an '+d.email+'.';
        var dn=document.getElementById('done');dn.hidden=false;dn.scrollIntoView({behavior:'smooth',block:'center'});return;
      }
      err.textContent=(d&&d.error)||'Das hat leider nicht geklappt. Bitte versuch es noch einmal.';err.hidden=false;go.disabled=false;go.textContent='Termin verbindlich buchen';
    }).catch(function(){err.textContent='Keine Verbindung. Bitte versuch es noch einmal.';err.hidden=false;go.disabled=false;go.textContent='Termin verbindlich buchen';});
  });
})();
</script>
<?php endif; ?>
</body>
</html>

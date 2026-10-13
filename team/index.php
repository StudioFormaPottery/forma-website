<?php
// Versteckte Buchungsseite Teambuilding (QR-Code auf den Teambuilding- und Weihnachts-Flyern). Nicht verlinkt, nicht in der Suche.
declare(strict_types=1);
require dirname(__DIR__) . '/api/lib.php';
header('X-Robots-Tag: noindex, nofollow');
$a = (string)($_GET['a'] ?? '');
$show = $a === 'halbtag' ? ['halbtag'] : ($a === 'wochenende' ? ['wochenende'] : ['halbtag', 'wochenende']);
$canPay = (bool)forma_stripe_key();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$xmas = date('n') >= 10 || date('n') <= 1;
$I = [
 'gift' => '<svg viewBox="0 0 64 64"><rect x="10" y="26" width="44" height="28" rx="4" fill="#8C2C1D"/><rect x="7" y="19" width="50" height="10" rx="3" fill="#674B40"/><path d="M32 19v35" stroke="#FBF1EA" stroke-width="4"/><path d="M32 19c-4-9-14-10-13-4s9 5 13 4zm0 0c4-9 14-10 13-4s-9 5-13 4z" fill="none" stroke="#FBF1EA" stroke-width="3"/></svg>',
 'house' => '<svg viewBox="0 0 64 64"><path d="M10 30L32 12l22 18v24a3 3 0 01-3 3H13a3 3 0 01-3-3z" fill="#DDBFA8"/><path d="M7 32L32 11l25 21" stroke="#FBF1EA" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="27" y="38" width="10" height="19" rx="2" fill="#FBF1EA"/></svg>',
 'team' => '<svg viewBox="0 0 64 64"><circle cx="20" cy="22" r="7" fill="#674B40"/><circle cx="44" cy="22" r="7" fill="#674B40"/><circle cx="32" cy="18" r="8" fill="#8C2C1D"/><path d="M8 50c0-9 5-15 12-15s9 4 9 4M56 50c0-9-5-15-12-15s-9 4-9 4" fill="#674B40"/><path d="M18 52c0-10 6-17 14-17s14 7 14 17z" fill="#8C2C1D"/></svg>',
 'star' => '<svg viewBox="0 0 24 24"><path fill="#8C2C1D" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/></svg>',
 'starl' => '<svg viewBox="0 0 24 24"><path fill="#FBF1EA" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/></svg>',
 'mug' => '<svg viewBox="0 0 64 64"><path d="M24 6c-4 4 4 6 0 11M32 4c-4 4 4 6 0 11M40 6c-4 4 4 6 0 11" stroke="#674B40" stroke-width="2.5" fill="none" stroke-linecap="round"/><rect x="12" y="22" width="34" height="32" rx="8" fill="#FBF1EA"/><path d="M46 29h4a7 7 0 010 14h-4" stroke="#FBF1EA" stroke-width="5" fill="none"/><path d="M14 31h30" stroke="#8C2C1D" stroke-width="4"/></svg>',
 'tree' => '<svg viewBox="0 0 64 64"><path d="M32 4l14 18h-7l11 14h-7l11 14H10l11-14h-7l11-14h-7z" fill="#674B40"/><rect x="28" y="50" width="8" height="10" fill="#8C2C1D"/><circle cx="26" cy="30" r="2.5" fill="#8C2C1D"/><circle cx="38" cy="40" r="2.5" fill="#FBF1EA"/></svg>',
];
?><!doctype html>
<html lang="de"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Teambuilding buchen · FORMA</title><link rel="icon" type="image/png" href="../img/forma-logo.png">
<link rel="stylesheet" href="../assets/gast.css?v=7">
</head><body>
<div class="head"><div class="wrap">
 <span class="ic" style="right:8%;top:40px;width:34px"><?= $I['star'] ?></span>
 <span class="ic" style="left:46%;top:56px;width:22px;animation-delay:-2s"><?= $I['starl'] ?></span>
 <span class="ic" style="left:52%;bottom:30px;width:48px;animation-delay:-3s"><?= $xmas ? $I['tree'] : $I['mug'] ?></span>
 <div class="top"><img src="../img/forma-logo.png" alt="FORMA Earth &amp; Art"><a href="../#teambuilding">forma-pottery.com</a></div>
 <div class="hero"><div>
  <p class="kick">Teambuilding &amp; Events · FORMA Atelier in Mathon</p>
  <h1><?= $xmas ? 'Nutzt die besinnliche Zeit' : 'Gemeinsam formen verbindet' ?></h1>
  <p class="lead">Töpfern verbindet: Raus aus dem Alltag, rein in den Ton. Ihr arbeitet an der Drehscheibe und mit den Händen, ganz ohne Vorkenntnisse, und ich begleite euch Schritt für Schritt.</p>
 </div><div class="arch"><img src="../img/drehen.jpg" alt=""></div></div>
</div></div>
<main><div class="wrap">
 <?php if (!empty($_GET['abgebrochen'])): ?><p class="err" style="margin-bottom:18px">Die Bezahlung wurde abgebrochen, es wurde nichts abgebucht. Du kannst es jederzeit noch einmal versuchen.</p><?php endif; ?>
 <div class="cards<?= count($show) === 1 ? ' one' : '' ?>">
 <?php foreach ($show as $o): $half = $o === 'halbtag'; ?>
  <section class="card<?= $half ? '' : ' dark' ?>"><svg class="wv" aria-hidden="true"><path></path></svg>
   <span class="cic"><?= $half ? $I['gift'] : $I['house'] ?></span>
   <p class="ok"><?= $half ? 'Ohne Übernachtung · 4 Stunden' : 'Mit Übernachtung im Heindalishof' ?></p>
   <h2><?= $half ? 'Halbtages-Event' : 'Töpfer-Wochenende' ?></h2>
   <p class="price"><?= $half ? '€ 159' : '€ 284' ?><small>pro Person<?= $half ? ', alles inklusive' : '' ?></small></p>
   <ul class="facts">
    <?php if ($half): ?>
     <li>4 Stunden gemeinsam töpfern im Studio</li><li>Drehscheibe und Handbuilding: ihr probiert beides aus</li><li>4 bis 8 Personen</li><li>Material, Fertigstellung und ein Werkstück pro Person inklusive</li>
    <?php else: ?>
     <li>Samstag ab 14 Uhr und Sonntag: 2 × 3 Stunden töpfern</li><li>Übernachtung mit Frühstück im Heindalishof (€ 219 Töpfern + € 65 Übernachtung)</li><li>Gemeinsam kochen in der Gemeinschaftsküche, Abendessen optional</li><li>4 bis 8 Personen</li>
    <?php endif; ?>
   </ul>
   <button class="btn fold-btn" type="button" aria-expanded="<?= count($show) === 1 ? 'true' : 'false' ?>"<?= count($show) === 1 ? ' hidden' : '' ?>><?= ($half && $canPay) ? 'Jetzt buchen' : 'Jetzt anfragen' ?></button>
   <div class="fold<?= count($show) === 1 ? ' open' : '' ?>">
   <form class="tf" data-offer="<?= $o ?>" novalidate>
    <input type="hidden" name="offer" value="<?= $o ?>"><input type="hidden" name="t" class="t"><input type="hidden" name="wish_date" class="wd"><input type="hidden" name="wish" class="ws">
    <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
    <label>Wie viele seid ihr?</label>
    <select name="persons" class="pers"><option value="">Bitte wählen</option><?php for ($n = 4; $n <= 8; $n++): ?><option value="<?= $n ?>"><?= $n ?> Personen</option><?php endfor; ?></select>
    <?php if ($half && $canPay): ?><p class="total">Gesamt: <span class="sum">–</span></p><?php endif; ?>
    <label>1. Wunschtermin</label><div class="two"><input type="date" class="d1" min="<?= $tomorrow ?>"><input type="time" class="t1" step="900"></div>
    <label>2. Wunschtermin <span class="opt">(optional)</span></label><div class="two"><input type="date" class="d2" min="<?= $tomorrow ?>"><input type="time" class="t2" step="900"></div>
    <label>Vor- und Nachname</label><input name="name" autocomplete="name">
    <label>Firma / Verein <span class="opt">(optional, für die Rechnung)</span></label><input name="company" autocomplete="organization">
    <label>E-Mail</label><input name="email" type="email" autocomplete="email">
    <label>Telefon <span class="opt">(optional)</span></label><input name="phone" type="tel" autocomplete="tel">
    <label>Wünsche oder Fragen <span class="opt">(optional)</span></label><textarea name="note" rows="2" maxlength="800"></textarea>
    <?php if ($half && $canPay): ?><details class="gs-box"><summary><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M12 8c-1-3-5-4-5-1.5S10 8 12 8zm0 0c1-3 5-4 5-1.5S14 8 12 8z"/></svg>Ihr habt einen Gutschein?</summary><div class="gs-row"><input name="voucher" class="gs-code" placeholder="FORMA-XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-label="Gutscheincode"><button type="button" class="btn gs-check" data-ok="✓ Gutschein gefunden: %s werden bei eurer Buchung abgezogen.">Prüfen</button></div><p class="gs-msg" role="status" hidden></p></details><?php endif; ?>
    <label class="consent"><input type="checkbox" name="consent" value="1"><span>Ich bin einverstanden, dass meine Angaben zur Bearbeitung der Buchung gespeichert werden. <a href="../#datenschutz">Datenschutz</a></span></label>
    <p class="post-note">Mit eurer Buchung bekommst du ab und zu die FORMA Post mit Neuem aus dem Atelier. Abmelden geht jederzeit mit einem Klick.</p><label class="consent"><input type="checkbox" name="no_post" value="1"><span>Nein danke, ich möchte keine FORMA Post bekommen.</span></label>
    <p class="err" hidden></p>
    <button class="btn go" type="submit"><?= ($half && $canPay) ? 'Weiter zur Bezahlung' : 'Verbindlich anfragen' ?></button>
    <p class="hint" style="font-size:16px;margin:0"><?= ($half && $canPay) ? 'Ihr bezahlt jetzt online, die Rechnung kommt sofort per E-Mail. Den Termin bestätige ich euch persönlich per E-Mail.' : 'Ich melde mich mit der Bestätigung eures Wunschtermins und allen Details bei euch.' ?></p>
   </form>
   </div>
   <div class="done" hidden><b>Danke für eure Anfrage!</b>Ich sehe mir eure Wunschtermine an und melde mich so bald wie möglich mit der Bestätigung. Eine Kopie eurer Anfrage ist schon in deinem Postfach.</div>
  </section>
 <?php endforeach; ?>
 </div>
 <div class="gut"><svg class="wv" aria-hidden="true"><path></path></svg><span class="gi"><?= $I['team'] ?></span>
  <div><b>Perfekt für</b><p>Weihnachtsfeier oder Jahresabschluss, ein Dankeschön ans Team, Vereinsausflug, Geburtstage oder einen Tag mit Freundinnen. Eure Werkstücke sind nach 3–4 Wochen abholbereit.</p></div></div>
 <p class="foot">FORMA Earth &amp; Art · Silvrettastraße 58 · 6562 Mathon · <a href="tel:+4367763907080">+43 677 63 90 70 80</a> · <a href="mailto:hello@forma-pottery.com">hello@forma-pottery.com</a></p>
</div></main>
<script src="../assets/gast.js?v=3"></script>
<script>document.querySelectorAll('.fold-btn').forEach(function(b){b.addEventListener('click',function(){var f=b.nextElementSibling;f.classList.add('open');b.hidden=true;var s=f.querySelector('select');if(s)s.focus();});});</script>
<script>
document.querySelectorAll('.tf').forEach(function(f){
  var q=function(c){return f.querySelector(c);},err=q('.err'),go=q('.go'),label=go.textContent,half=f.dataset.offer==='halbtag';
  q('.t').value=Date.now();
  var sum=q('.sum');if(sum)q('.pers').addEventListener('change',function(){var n=+this.value;sum.textContent=n?('€ '+(159*n).toLocaleString('de-AT')+' für '+n+' Personen'):'–';});
  function fmt(d,t){if(!d)return '';var p=d.split('-');return p[2]+'.'+p[1]+'.'+p[0]+(t?' um '+t:'');}
  f.addEventListener('submit',function(e){
    e.preventDefault();err.hidden=true;var msg='';
    var d1=q('.d1').value,t1=q('.t1').value,d2=q('.d2').value,t2=q('.t2').value;
    if(!q('.pers').value)msg='Bitte wähl, wie viele Personen ihr seid.';else if(!d1)msg='Bitte wähl euren 1. Wunschtermin.';
    else if(f.name.value.trim().length<2)msg='Bitte gib deinen Namen an.';else if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.value.trim()))msg='Bitte gib eine gültige E-Mail-Adresse an.';
    else if(!f.consent.checked)msg='Bitte bestätige noch den Datenschutz.';
    if(msg){err.textContent=msg;err.hidden=false;return;}
    q('.wd').value=d1;q('.ws').value='1. Wunsch: '+fmt(d1,t1)+(d2?' · 2. Wunsch: '+fmt(d2,t2):'');
    go.disabled=true;go.textContent='Einen Moment …';
    var wait=3200-(Date.now()-(+q('.t').value||0));
    setTimeout(function(){fetch('../api/team.php',{method:'POST',body:new FormData(f)}).then(function(r){return r.json().catch(function(){return {ok:false};});}).then(function(d){
      if(d&&d.ok&&d.redirect){location.href=d.redirect;return;}
      if(d&&d.ok){f.hidden=true;f.parentNode.querySelector('.done').hidden=false;return;}
      err.textContent=(d&&d.error)||'Das hat leider nicht geklappt.';err.hidden=false;go.disabled=false;go.textContent=label;
    }).catch(function(){err.textContent='Keine Verbindung.';err.hidden=false;go.disabled=false;go.textContent=label;});},Math.max(0,wait));
  });
});
</script>
</body></html>

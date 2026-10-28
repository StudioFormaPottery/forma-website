<?php
// Angebotsseite für Gruppen (Vivien, 28.10.2026): /angebot/?t=TOKEN
// Offen: Anzahl Kinder pro Termin eintragen (keine Namen), Gesamtbetrag live, „Angebot bestätigen“.
// Bestätigt: Übersicht, Vorabrechnung (PDF), online bezahlen per Knopf oder QR-Code, bar vor Ort.
// Logik in api/angebote.inc.php.
declare(strict_types=1);
require __DIR__ . '/../api/lib.php';

header('X-Robots-Tag: noindex');
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
try {
    $pdo = forma_db();
    $o = forma_offer_by_token($pdo, $token);
} catch (Throwable $e) {
    error_log('FORMA angebot: ' . $e->getMessage());
    forma_message_page('Das hat leider nicht geklappt', 'Das Angebot konnte gerade nicht geladen werden. Versuch es bitte in ein paar Minuten noch einmal.');
}
if (!$o) forma_message_page('Angebot nicht gefunden', 'Dieser Link stimmt leider nicht. Schreib mir kurz an hello@forma-pottery.com oder ruf mich an: ' . FORMA_PHONE);
if ($o['status'] === 'zurueckgezogen') forma_message_page('Angebot nicht mehr aktuell', 'Dieses Angebot ist nicht mehr gültig. Melde dich gern bei mir, dann schauen wir gemeinsam nach einem neuen Termin: ' . FORMA_PHONE);

$self = '/angebot/?t=' . $o['token'];

// Vorabrechnung als PDF
if (($_GET['pdf'] ?? '') === 'vorab' && $o['status'] === 'bestaetigt') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Vorabrechnung_' . $o['nr'] . '.pdf"');
    echo forma_invoice_pdf(forma_offer_vorab($o));
    exit;
}

// Bestätigen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    $kids = array_map('intval', (array)($_POST['kids'] ?? []));
    $note = forma_clean((string)($_POST['note'] ?? ''), 1500);
    $bill = ['to' => in_array($_POST['bill_to'] ?? '', ['schule', 'person', 'andere'], true) ? $_POST['bill_to'] : 'schule',
        'name' => forma_clean((string)($_POST['bill_name'] ?? ''), 160), 'attn' => forma_clean((string)($_POST['bill_attn'] ?? ''), 120),
        'address' => trim(str_replace("\r", '', mb_substr((string)($_POST['bill_address'] ?? ''), 0, 300))),
        'email' => filter_var(trim((string)($_POST['bill_email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: ''];
    $ok = forma_offer_confirm($pdo, $o, $kids, $note, $bill);
    header('Location: ' . $self . ($ok ? '&ok=1' : '&leer=1'), true, 303);
    exit;
}

// Online-Zahlung schon durch?
if ($o['status'] === 'bestaetigt' && $o['stripe_session'] !== '') {
    try { if (forma_offer_check($pdo, $o) === 'bezahlt') $o = forma_offer_get($pdo, (int)$o['id']); } catch (Throwable $e) { error_log('FORMA angebot check: ' . $e->getMessage()); }
}

$h = 'forma_h';
$dates = forma_offer_dates($o);
$open = $o['status'] === 'offen';
$unit = (int)$o['unit_cents'];
$first = forma_offer_first($o);
$total = forma_offer_total($o);
$eur = fn(int $c) => '€ ' . number_format($c / 100, 2, ',', '.');
$eurShort = fn(int $c) => '€ ' . ($c % 100 === 0 ? (string)intdiv($c, 100) : number_format($c / 100, 2, ',', '.'));
$created = date('j.n.Y', strtotime((string)$o['created_at']));
$valid = $o['valid_until'] !== '' ? date('j.n.Y', strtotime((string)$o['valid_until'])) : '';
$payUrl = forma_offer_pay_url($o);
$stripe = (bool)forma_stripe_key();
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title><?= $h($open ? 'Angebot' : 'Bestätigt') ?> · <?= $h($o['title']) ?> · FORMA</title>
<link rel="icon" type="image/png" href="/img/forma-logo.png">
<style>
@font-face{font-family:"Outfit";src:url(/fonts/Outfit-Regular.woff) format("woff");font-weight:400;font-display:swap}
@font-face{font-family:"Outfit";src:url(/fonts/Outfit-Bold.woff) format("woff");font-weight:600;font-display:swap}
@font-face{font-family:"Bricolage Grotesque";src:url(/fonts/BricolageGrotesque-Regular.woff) format("woff");font-weight:400;font-display:swap}
@font-face{font-family:"Bricolage Grotesque";src:url(/fonts/BricolageGrotesque-Bold.woff) format("woff");font-weight:600 800;font-display:swap}
@font-face{font-family:"Italiana";src:url(/fonts/Italiana-Regular.woff) format("woff");font-weight:400;font-display:swap}
:root{--sand:#EEE2D4;--hafer:#E4D2BF;--ton:#DDBFA8;--paper:#FBF6F0;--rot:#8C2C1D;--braun:#674B40;--text:#33211B;--muted:#7A6258;--line:#E2D2C2;--ok:#4F6B3F;--okbg:#E3EAD9;
  --display:"Bricolage Grotesque","Avenir Next",system-ui,sans-serif;--body:"Outfit","Avenir Next",system-ui,sans-serif}
*{box-sizing:border-box}
body{margin:0;background:var(--sand);color:var(--text);font:400 17px/1.6 var(--body);-webkit-font-smoothing:antialiased}
.pad{padding-left:max(16px,env(safe-area-inset-left,0px));padding-right:max(16px,env(safe-area-inset-right,0px))}
/* Kopf wie auf der Startseite */
.top{background:var(--ton)}
.top .in{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:16px;padding-block:12px}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--text)}
.brand img{width:60px;height:60px;object-fit:contain}
.brand span{font-size:13px;font-weight:400;letter-spacing:.16em;text-transform:uppercase}
.top .nr{font-size:14px;color:var(--braun);text-align:right;line-height:1.4}
.top .nr b{font-weight:600;color:var(--text);white-space:nowrap}
@media (min-width:861px){.brand img{width:96px;height:96px}.brand span{font-size:16px}}
/* Kopfbereich mit Foto im Bogen */
.hero{background:var(--ton);position:relative;overflow:hidden}
.hero .in{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:clamp(20px,5vw,64px);align-items:center;padding-block:clamp(14px,3vw,36px) 0;position:relative;z-index:1}
.kicker{font-family:"Italiana",Georgia,serif;font-size:clamp(26px,3vw,34px);line-height:1.15;color:var(--rot)}
.hero h1{font:650 clamp(36px,5.4vw,66px)/1.04 var(--display);letter-spacing:-.025em;color:var(--rot);margin:6px 0 14px;text-wrap:balance}
.for{font:600 clamp(18px,2vw,22px)/1.3 var(--display);color:var(--text)}
.to{font-size:16px;color:var(--braun);margin-top:2px}
.hero .lead{margin-top:14px;max-width:46ch}
.art{position:relative;justify-self:center;width:min(100%,300px);padding:10px 0 26px}
.art::before{content:"";position:absolute;inset:6% -10% 4% -6%;background:var(--hafer);border-radius:58% 42% 47% 53%/52% 46% 54% 48%;animation:morph 14s ease-in-out infinite alternate}
.art img{position:relative;width:100%;height:auto;aspect-ratio:4/5;object-fit:cover;border-radius:999px 999px 26px 26px;border:6px solid #FBF1EA;box-shadow:0 18px 40px rgba(51,33,27,.18)}
.spark{position:absolute;z-index:2;width:26px;animation:twinkle 3.6s ease-in-out infinite}
.spark path{fill:var(--rot)}
.spark.s1{left:-6px;top:18%}.spark.s2{right:-4px;bottom:22%;width:18px;animation-delay:1.2s}
@keyframes morph{to{border-radius:46% 54% 56% 44%/44% 56% 46% 54%}}
@keyframes twinkle{50%{transform:scale(.6) rotate(20deg);opacity:.5}}
.wave{display:block;width:100%;height:clamp(40px,6vw,90px);margin-top:-1px}
.wave path{fill:var(--sand)}
@media (max-width:700px){.hero .in{grid-template-columns:1fr}.art{width:min(46%,170px);order:-1;padding:4px 0 0}.hero h1{font-size:clamp(32px,9vw,40px)}}
/* Inhalt */
.wrap{max-width:760px;margin:0 auto}
@media (min-width:900px){.wrap{max-width:920px}body{font-size:18px}.when b{font-size:21px}}
.sheet{display:grid;gap:26px;padding-block:6px 48px}
.head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;position:relative;z-index:1}
.logo{display:flex;align-items:center;gap:12px;color:var(--rot);text-decoration:none}
.logo img{width:54px;height:auto}
.logo span{font:400 11px/1.4 var(--body);letter-spacing:.18em;color:var(--muted)}
.meta{font-size:14px;color:var(--muted);text-align:right;position:relative;z-index:1}
.meta b{color:var(--text);font-weight:600}
section{position:relative;z-index:1}
.eyebrow{font-size:13px;letter-spacing:.14em;text-transform:uppercase;color:var(--rot)}
.to{font-size:16px;color:var(--muted);margin-top:2px}
h1{font:600 clamp(30px,6vw,44px)/1.08 var(--display);margin:6px 0 12px;text-wrap:balance;letter-spacing:-.01em}
.lead{margin:0;max-width:60ch;white-space:pre-line}
.facts{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.chip{background:#FBF1EA;border-radius:999px;padding:4px 14px;font-size:14px;color:var(--braun)}
h2{font:650 clamp(24px,3vw,30px)/1.15 var(--display);letter-spacing:-.02em;color:var(--rot);margin:0 0 4px}
.date{position:relative}
.badge{width:56px;height:56px;border-radius:50%;background:var(--rot);color:#FBF1EA;display:grid;place-items:center;align-content:center;line-height:1;flex:none}
.badge b{font:650 22px var(--display)}.badge small{font-size:11px;letter-spacing:.12em;text-transform:uppercase;margin-top:2px}
.whenrow{display:flex;gap:14px;align-items:center}
.dates{display:grid;gap:12px;margin-top:12px}
.date{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px 18px;align-items:center;border-radius:26px;padding:16px 18px;background:var(--paper);box-shadow:0 6px 18px rgba(103,48,30,.06)}
.when b{font:600 19px/1.2 var(--display);display:block;color:var(--text)}
.when span{font-size:15px;color:var(--muted)}
.step{display:flex;align-items:center;gap:6px}
.step button{width:44px;height:44px;border-radius:50%;border:1.5px solid var(--rot);background:transparent;color:var(--rot);font:400 22px/1 var(--body);cursor:pointer;touch-action:manipulation}
.step input{width:64px;height:44px;border-radius:14px;border:1px solid var(--line);background:var(--sand);color:var(--text);text-align:center;font:400 20px var(--body);font-variant-numeric:tabular-nums;-moz-appearance:textfield}
.step input::-webkit-inner-spin-button,.step input::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
.step label{font-size:14px;color:var(--muted);margin-left:4px}
.count{font:600 20px var(--display);color:var(--rot);white-space:nowrap;text-align:right}
.count small{display:block;font:400 14px var(--body);color:var(--muted)}
.date.fixed{padding:12px 16px}
.line{grid-column:1/-1;display:flex;justify-content:space-between;font-size:15px;color:var(--muted);border-top:1px dashed var(--line);padding-top:10px;font-variant-numeric:tabular-nums}
.sum{background:var(--hafer);border-radius:22px;padding:18px 20px;display:grid;gap:6px;font-variant-numeric:tabular-nums}
.sum .row{display:flex;justify-content:space-between;gap:12px}
.sum .total{font:600 clamp(21px,5.6vw,26px)/1.2 var(--display);color:var(--rot);border-top:1px solid var(--ton);padding-top:10px;margin-top:4px;align-items:baseline}
.sum .total span:last-child{white-space:nowrap}
.sum small{color:var(--muted);font-size:14px}
textarea{width:100%;min-height:84px;border-radius:16px;border:1px solid var(--line);background:var(--sand);color:var(--text);font:400 16px/1.5 var(--body);padding:12px 14px;margin-top:8px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;border:0;border-radius:999px;background:var(--rot);color:#FBF1EA;font:400 18px var(--body);padding:15px 28px;cursor:pointer;width:100%;text-decoration:none;text-align:center}
.btn[disabled]{opacity:.45;cursor:not-allowed}
.btn.ghost{background:transparent;color:var(--rot);border:1.5px solid var(--rot)}
.btn.sm{width:auto;font-size:16px;padding:11px 20px}
.step button:focus-visible,.btn:focus-visible,input:focus-visible,textarea:focus-visible{outline:3px solid var(--rot);outline-offset:2px}
.fine{font-size:14px;color:var(--muted);margin:10px 0 0;text-align:center}
.done{border-radius:22px;background:var(--okbg);color:var(--ok);padding:18px 20px;font-size:16px}
.done b{display:block;font:600 20px var(--display);margin-bottom:4px}
.pay{display:flex;gap:18px;align-items:center;flex-wrap:wrap;background:var(--paper);border-radius:22px;padding:18px 20px;font-size:15px}
.pay b{font:600 18px var(--display)}
.pay .btns{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.qr{width:128px;height:128px;flex:none;border-radius:14px;background:#fff;padding:8px;line-height:0}
.qr svg{width:100%;height:100%}
.alert{border-radius:16px;background:#F4E6CC;color:#7a5216;padding:12px 16px;font-size:15px;margin:0}
.seg{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 12px}
.seg label{display:inline-flex;align-items:center;gap:6px;border:1.5px solid var(--ton);border-radius:999px;padding:7px 14px;font-size:15px;cursor:pointer;color:var(--braun)}
.seg label:has(input:checked){background:var(--rot);border-color:var(--rot);color:#FBF1EA}
.seg input{position:absolute;opacity:0;pointer-events:none}
.seg label:has(input:focus-visible){outline:3px solid var(--rot);outline-offset:2px}
.bill{display:grid;grid-template-columns:1fr 1fr;gap:10px 14px}
.bill label{display:grid;gap:4px;font-size:14px;color:var(--muted)}
.bill label span{font-size:13px}
.bill .wide{grid-column:1/-1}
.bill input,.bill textarea{width:100%;min-height:46px;border-radius:14px;border:1px solid var(--line);background:var(--sand);color:var(--text);font:400 16px/1.4 var(--body);padding:10px 12px;margin:0}
.bill textarea{min-height:70px}
.more{border-top:1px dashed var(--line);padding-top:12px}
.more summary{cursor:pointer;font:600 18px/1.3 var(--display);list-style:none}
.more summary::-webkit-details-marker{display:none}
.more summary::after{content:"+";float:right;color:var(--rot);font-weight:400}
.more[open] summary::after{content:"–"}
.more summary span{font:400 14px var(--body);color:var(--muted)}
@media (max-width:520px){.bill{grid-template-columns:1fr}}
.sign{font-size:15px;color:var(--muted);margin:0}
.sign i{font-style:normal;color:var(--rot)}
@media (max-width:520px){.meta{text-align:left}.date:not(.fixed){grid-template-columns:1fr}.when b{font-size:17px}.qr{width:112px;height:112px}}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>
<header class="top"><div class="in pad">
  <a class="brand" href="/"><img src="/img/forma-logo.png" alt="FORMA Earth &amp; Art Logo"><span>Töpferstudio Mathon</span></a>
  <div class="nr">Angebot <b><?= $h($o['nr']) ?></b><br>vom <?= $h($created) ?><?= $valid !== '' && $open ? '<br>gültig bis ' . $h($valid) : '' ?></div>
</div></header>
<section class="hero">
  <div class="in pad">
  <div>
    <div class="kicker"><?= $open ? 'Unverbindliches Angebot' : ($o['status'] === 'bezahlt' ? 'Bestätigt und bezahlt' : 'Bestätigtes Angebot') ?></div>
    <h1><?= $h($o['title']) ?></h1>
    <div class="for">für <?= $h($o['org']) ?></div>
    <?php if ($o['contact'] !== ''): ?><div class="to">z. H. <?= $h($o['contact']) ?></div><?php endif; ?>
    <?php if ($open): ?>
      <p class="lead"><?= $o['intro'] !== '' ? $h($o['intro']) : $h(($first !== '' ? "Liebe $first" : 'Hallo') . ', schön, dass ihr zu uns ins Atelier kommt! Trag einfach ein, wie viele Kinder pro Termin dabei sind, und bestätige mit einem Klick.') ?></p>
    <?php endif; ?>
    <div class="facts">
      <span class="chip">1 <?= $h($o['item']) ?> pro Kind</span>
      <span class="chip">Farben &amp; Fertigstellung inklusive</span>
      <span class="chip"><?= $h($eurShort($unit)) ?> pro Kind</span>
    </div>
  </div>
  <div class="art">
    <svg class="spark s1" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/></svg>
    <svg class="spark s2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/></svg>
    <img src="/img/bemalen-hand.jpg" alt="Eine Hand bemalt eine Tasse mit Pinsel und Farbe" width="560" height="700">
  </div>
  </div>
  <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 64c120-34 248-52 384-30s246 70 392 66 266-62 412-70 192 14 252 30v60H0z"/></svg>
</section>
<div class="wrap pad">
<article class="sheet">

  <?php if (!empty($_GET['leer']) && $open): ?><p class="alert">Bitte trag bei mindestens einem Termin die Anzahl Kinder ein und füll aus, an wen die Rechnung geht (Name und Adresse).</p><?php endif; ?>

  <?php if ($open): ?>
  <form method="post" action="<?= $h($self) ?>" id="f" class="stack" style="display:grid;gap:26px">
    <input type="hidden" name="t" value="<?= $h($o['token']) ?>"><input type="hidden" name="action" value="confirm">
    <section>
      <h2>Wie viele Kinder kommen?</h2>
      <div class="dates">
        <?php foreach ($dates as $i => $d): $k = (int)($d['kids'] ?? 0); $label = date('j. n.', strtotime($d['date'])); ?>
        <div class="date">
          <div class="whenrow"><span class="badge" aria-hidden="true"><b><?= date('j', strtotime($d['date'])) ?></b><small><?= ['Jän','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'][(int)date('n', strtotime($d['date'])) - 1] ?></small></span><div class="when"><b><?= $h(forma_offer_day($d['date'])) ?></b><span><?= $h(forma_offer_when($d) ?: 'Uhrzeit nach Absprache') ?></span></div></div>
          <div class="step">
            <button type="button" data-i="<?= $i ?>" data-s="-1" aria-label="Ein Kind weniger am <?= $h($label) ?>">−</button>
            <input id="kids-<?= $i ?>" name="kids[<?= $i ?>]" type="number" inputmode="numeric" min="0" max="<?= FORMA_OFFER_MAX_KIDS ?>" value="<?= $k ?>" aria-label="Kinder am <?= $h($label) ?>">
            <button type="button" data-i="<?= $i ?>" data-s="1" aria-label="Ein Kind mehr am <?= $h($label) ?>">+</button>
            <label for="kids-<?= $i ?>">Kinder</label>
          </div>
          <div class="line"><span id="lab-<?= $i ?>"><?= $k ?> × <?= $h($eurShort($unit)) ?></span><span id="sub-<?= $i ?>"><?= $h($eur($k * $unit)) ?></span></div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="sum" aria-live="polite">
      <div class="row"><span>Kinder gesamt</span><span id="n"><?= forma_offer_kids($o) ?></span></div>
      <div class="row total"><span>Gesamtbetrag</span><span id="tot"><?= $h($eur($total)) ?></span></div>
      <small id="vat">inkl. 20 % USt. (<?= $h($eur($total - (int)round($total / 1.2))) ?>)</small>
    </section>

    <section>
      <h2>Rechnung an</h2>
      <div class="seg" role="radiogroup" aria-label="Wer bekommt die Rechnung?">
        <label><input type="radio" name="bill_to" value="schule" checked> die Schule</label>
        <?php if ($o['contact'] !== ''): ?><label><input type="radio" name="bill_to" value="person"> mich</label><?php endif; ?>
        <label><input type="radio" name="bill_to" value="andere"> jemand anderen</label>
      </div>
      <div class="bill">
        <label class="wide">Name / Schule<input name="bill_name" id="bill_name" required maxlength="160" value="<?= $h($o['org']) ?>" autocomplete="organization"></label>
        <label>z. H. (freiwillig)<input name="bill_attn" id="bill_attn" maxlength="120" value="<?= $h($o['contact']) ?>"></label>
        <label>E-Mail für die Rechnung<input name="bill_email" type="email" maxlength="160" value="<?= $h($o['email']) ?>" autocomplete="email"></label>
        <label class="wide">Adresse<textarea name="bill_address" required rows="2" maxlength="300" placeholder="Straße Nr.&#10;PLZ Ort" autocomplete="street-address"></textarea></label>
      </div>
    </section>

    <details class="more">
      <summary>Noch etwas, das ich wissen sollte? <span>(freiwillig)</span></summary>
      <textarea name="note" maxlength="1500" placeholder="z. B. Klasse, Alter der Kinder, Begleitpersonen, Wünsche zum Motiv"></textarea>
    </details>

    <section>
      <button class="btn" id="confirm" type="submit"<?= $total > 0 ? '' : ' disabled' ?>>Angebot bestätigen</button>
      <p class="fine">Danach kommt die Vorabrechnung. Bezahlen: bar vor Ort oder online per QR-Code.</p>
    </section>
  </form>
  <?php else: ?>
  <?php if (!empty($_GET['ok'])): ?>
    <div class="done"><b>Danke<?= $first !== '' ? ', ' . $h($first) : '' ?>! Das Angebot ist bestätigt.</b>Ich freu mich schon sehr auf euch.<?= trim((string)$o['email']) !== '' ? ' Die Vorabrechnung ist gerade per E-Mail zu dir unterwegs.' : '' ?></div>
  <?php endif; ?>
  <section>
    <h2>Eure Termine</h2>
    <div class="dates">
      <?php foreach ($dates as $d): $k = forma_offer_bill_kids($d); ?>
      <div class="date fixed">
        <div class="whenrow"><span class="badge" aria-hidden="true"><b><?= date('j', strtotime($d['date'])) ?></b><small><?= ['Jän','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'][(int)date('n', strtotime($d['date'])) - 1] ?></small></span><div class="when"><b><?= $h(forma_offer_day($d['date'])) ?></b><span><?= $h(forma_offer_when($d) ?: 'Uhrzeit nach Absprache') ?></span></div></div>
        <div class="count"><?= $k ?> Kinder<small><?= $h($eur($k * $unit)) ?></small></div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="sum">
    <div class="row"><span>Kinder gesamt</span><span><?= forma_offer_kids($o) ?></span></div>
    <div class="row total"><span>Gesamtbetrag</span><span><?= $h($eur($total)) ?></span></div>
    <small>inkl. 20 % USt. (<?= $h($eur($total - (int)round($total / 1.2))) ?>)</small>
  </section>
  <?php if ($o['status'] === 'bezahlt'): ?>
    <div class="done"><b>Alles bezahlt, danke!</b>Die Rechnung ist per E-Mail gekommen. Bis bald im Atelier.</div>
  <?php else: ?>
    <section class="pay">
      <?php if ($stripe): ?><div class="qr" id="qr" data-url="<?= $h($payUrl) ?>" role="img" aria-label="QR-Code zum Online-Bezahlen"></div><?php endif; ?>
      <div style="flex:1;min-width:220px">
        <b>Noch nicht bezahlt</b><br>
        Ihr könnt bar vor Ort bezahlen<?= $stripe ? ' oder gleich online: QR-Code scannen oder Knopf tippen. Nach der Zahlung kommt die fertige Rechnung automatisch.' : '.' ?>
        <div class="btns">
          <?php if ($stripe): ?><a class="btn sm" href="<?= $h($payUrl) ?>">Jetzt online bezahlen · <?= $h($eur($total)) ?></a><?php endif; ?>
          <a class="btn sm ghost" href="<?= $h($self) ?>&amp;pdf=vorab" target="_blank" rel="noopener">Vorabrechnung (PDF)</a>
        </div>
      </div>
    </section>
    <?php [$bn, $ba, $be] = forma_offer_bill($o); ?>
    <details class="more"><summary>Rechnung an <?= $h($bn) ?></summary><p style="margin:8px 0 0;white-space:pre-line"><?= $h(trim($bn . "\n" . $ba . ($be !== '' ? "\n" . $be : ''))) ?></p><p class="fine" style="text-align:left">Stimmt etwas nicht oder kommen weniger Kinder? Sag mir einfach Bescheid: <?= $h(FORMA_PHONE) ?>. Verrechnet wird, wie viele Kinder wirklich da sind.</p></details>
  <?php endif; ?>
  <?php endif; ?>

  <p class="sign">Bis bald, Vivien · <i>Mit ganz viel Liebe zum Ton</i></p>
</article>
</div>
<?php if ($open): ?>
<script>
(function(){
  var P=<?= $unit ?>, max=<?= FORMA_OFFER_MAX_KIDS ?>, cnt=<?= count($dates) ?>;
  function fmt(c){return '€ '+(c/100).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');}
  function short(c){return c%100===0?'€ '+(c/100):fmt(c);}
  function get(i){var v=parseInt(document.getElementById('kids-'+i).value,10);return isNaN(v)||v<0?0:Math.min(v,max);}
  function upd(){
    var n=0;
    for(var i=0;i<cnt;i++){var k=get(i);n+=k;document.getElementById('sub-'+i).textContent=fmt(k*P);document.getElementById('lab-'+i).textContent=k+' × '+short(P);}
    var t=n*P;
    document.getElementById('n').textContent=n;
    document.getElementById('tot').textContent=fmt(t);
    document.getElementById('vat').textContent='inkl. 20 % USt. ('+fmt(t-Math.round(t/1.2))+')';
    document.getElementById('confirm').disabled=n===0;
  }
  document.querySelectorAll('.step button').forEach(function(b){b.addEventListener('click',function(){
    var i=b.dataset.i,inp=document.getElementById('kids-'+i);inp.value=Math.max(0,Math.min(max,get(i)+ +b.dataset.s));upd();});});
  document.querySelectorAll('.step input').forEach(function(i){i.addEventListener('input',upd);});
  var org=<?= json_encode((string)$o['org'], JSON_UNESCAPED_UNICODE) ?>, who=<?= json_encode((string)$o['contact'], JSON_UNESCAPED_UNICODE) ?>;
  document.querySelectorAll('input[name=bill_to]').forEach(function(r){r.addEventListener('change',function(){
    var n=document.getElementById('bill_name'),a=document.getElementById('bill_attn');
    if(r.value==='schule'){n.value=org;a.value=who;}else if(r.value==='person'){n.value=who;a.value='';}else{n.value='';a.value='';n.focus();}
  });});
  document.getElementById('f').addEventListener('submit',function(){var b=document.getElementById('confirm');setTimeout(function(){b.disabled=true;b.textContent='Wird bestätigt …';},0);});
  upd();
})();
</script>
<?php elseif ($o['status'] === 'bestaetigt' && $stripe): ?>
<script src="/verwaltung/qrcode.js"></script>
<script>(function(){var b=document.getElementById('qr');if(b&&window.qrcode){var q=qrcode(0,'M');q.addData(b.dataset.url);q.make();b.innerHTML=q.createSvgTag({cellSize:4,margin:0,scalable:true});}})();</script>
<?php endif; ?>
</body>
</html>

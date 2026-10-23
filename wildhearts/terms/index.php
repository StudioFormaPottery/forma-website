<?php
// Bedingungen & Rücktrittsrecht für alle Movecia-Angebote (Englisch/Deutsch), Preise live aus den Einstellungen.
// Stand 23.10.2026: Prä- & Postnatal (Studio + Online), Alpine Retreat, kostenlose Gatherings/Kennenlerngespräch.
// Inner Circle und Jahreskreis werden nicht mehr angeboten, stehen nur noch eingeklappt für bestehende Buchungen.
// Rücktrittsfunktion nach § 13a FAGG (ab 1.10.2026): moveciamovement.com/withdraw
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/natal-lib.php';
header('Cache-Control: no-cache');
$o = mm_offers();
$eur = fn(int $c) => '€ ' . ($c % 100 === 0 ? number_format($c / 100, 0, ',', '.') : number_format($c / 100, 2, ',', '.'));
$c = $o['circle']; $y = $o['year']; $r = $o['retreat'];
$np = natal_packages();
$h = 'forma_h';
$adr = 'Vivien Malonga, Movecia – Personal Fitness, ' . NATAL_COMPANY['street'] . ', ' . NATAL_COMPANY['city'];
$mail = $h(MM_FROM_EMAIL);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Terms &amp; withdrawal · Movecia</title>
<meta name="description" content="Terms, cancellation policy and right of withdrawal for Movecia: prenatal and postnatal training, retreat and free gatherings.">
<link rel="icon" href="/img/wildhearts/viv.jpg">
<style>
@font-face{font-family:"Italiana";font-display:swap;src:url(/fonts/Italiana-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:300 500;font-display:swap;src:url(/fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:600 800;font-display:swap;src:url(/fonts/Outfit-Bold.woff) format("woff")}
@font-face{font-family:"Hand";font-display:swap;src:url(/fonts/HomemadeApple-Regular.woff) format("woff")}
:root{--cream:#F2ECE4;--wine:#7D5E68;--wine-deep:#6B5658;--brown:#7D7076;--ink:#2E2529;--blush:#EADFDE;--line:#DDD2C6;--paper:#FBF8F4}
*{box-sizing:border-box;margin:0}
html,body{background:var(--cream)}
body{font-family:"Outfit",system-ui,sans-serif;color:var(--ink);font-size:18px;line-height:1.65;-webkit-font-smoothing:antialiased}
main{max-width:920px;margin:0 auto;padding:40px 16px 60px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.hand{font-family:"Hand",cursive;color:var(--wine);font-size:20px;transform:rotate(-2deg);display:inline-block}
h1{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(38px,7vw,56px);line-height:1.08;color:var(--wine-deep);margin:8px 0 6px}
h2{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(28px,4vw,34px);color:var(--wine-deep);margin:40px 0 8px;scroll-margin-top:16px}
h3{font-size:18px;font-weight:600;color:var(--wine-deep);margin:22px 0 4px;scroll-margin-top:16px}
p,li{color:#4A3A41}
p+p{margin-top:8px}
ul{padding-left:22px}
li{margin:5px 0}
.lead{color:var(--brown)}
.card{margin-top:14px;padding:18px 22px;border-radius:22px;background:var(--paper);border:1.5px solid var(--line)}
.legal{margin-top:12px;padding:20px 22px;border-radius:22px;background:var(--paper);border:1.5px solid var(--line)}
.legal h3:first-child{margin-top:0}
.price{display:inline-block;padding:3px 12px;border-radius:999px;background:var(--blush);color:var(--wine-deep);font-weight:500;font-size:15px;margin:3px 4px 3px 0}
.plabel{font-size:14px;color:var(--brown);margin:10px 0 2px}
.nav{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
.nav a{font-size:15px;padding:7px 14px;border-radius:999px;border:1.5px solid var(--line);text-decoration:none;color:var(--wine-deep);background:var(--paper)}
.nav a.go{background:var(--wine);border-color:var(--wine);color:var(--cream)}
.lang{display:flex;gap:6px}
.lang button{font:inherit;font-size:14px;padding:6px 14px;border-radius:999px;border:1.5px solid var(--line);background:transparent;color:var(--wine-deep);cursor:pointer}
.lang button.on{background:var(--wine);border-color:var(--wine);color:var(--cream)}
.btn{display:inline-block;margin-top:10px;padding:13px 26px;border-radius:999px;background:var(--wine);color:var(--cream);text-decoration:none;font-weight:500}
details{border-top:1px solid var(--line);padding:12px 0}
summary{cursor:pointer;font-weight:500;color:var(--wine-deep)}
details p,details ul{margin-top:6px}
details.old{margin-top:14px;padding:14px 20px;border:1.5px solid var(--line);border-radius:22px;background:var(--paper)}
details.old>summary{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:24px}
.small{font-size:15px;color:var(--brown)}
.form{font-size:16px;line-height:1.7}
[lang="de"] .en,[lang="en"] .de{display:none}
a{color:var(--wine)}
@media (max-width:600px){body{font-size:17px}}
</style>
</head>
<body>
<main>
  <div class="top"><span class="hand">with love, from the mountains ♡</span><div class="lang"><button type="button" data-l="en">EN</button><button type="button" data-l="de">DE</button></div></div>
  <h1><span class="en">Terms &amp; right of withdrawal</span><span class="de">Bedingungen &amp; Rücktrittsrecht</span></h1>
  <p class="lead"><span class="en">Clear and fair, so you can book with a good feeling. If anything is unclear or life gets in the way, just write to me. We will always find a way together.</span><span class="de">Klar und fair, damit du mit einem guten Gefühl buchen kannst. Wenn etwas unklar ist oder das Leben dazwischenkommt, schreib mir einfach. Wir finden immer gemeinsam einen Weg.</span></p>

  <div class="card">
    <p><strong>Movecia – Personal Fitness</strong> · Vivien Malonga<br><a href="mailto:<?= $mail ?>"><?= $mail ?></a> · <?= $h(NATAL_COMPANY['phone']) ?> · UID <?= $h(NATAL_COMPANY['uid']) ?></p>
    <p class="small"><span class="en">Payments are processed securely by Stripe. After every booking you receive a confirmation and your invoice by email. All prices include 20 % VAT.</span><span class="de">Zahlungen laufen sicher über Stripe. Nach jeder Buchung bekommst du eine Bestätigung und deine Rechnung per E-Mail. Alle Preise inklusive 20 % USt.</span></p>
  </div>
  <nav class="nav">
    <a href="#natal"><span class="en">Prenatal &amp; postnatal</span><span class="de">Prä- &amp; Postnatal</span></a>
    <a href="#retreat">Retreat</a>
    <a href="#free"><span class="en">Free offers</span><span class="de">Kostenlos</span></a>
    <a href="#withdrawal"><span class="en">Right of withdrawal</span><span class="de">Rücktrittsrecht</span></a>
    <a class="go" href="/withdraw"><span class="en">Withdraw from the contract</span><span class="de">Vertrag widerrufen</span></a>
  </nav>

  <h2 id="natal"><span class="en">Prenatal &amp; postnatal training</span><span class="de">Prä- &amp; Postnatal Training</span></h2>
  <p class="plabel"><span class="en">In the studio in Mathon</span><span class="de">Im Studio in Mathon</span></p>
  <p><?php foreach (natal_packages_for('studio') as $p): ?><span class="price"><?= $h(str_replace(' online', '', $p['title'])) ?> <?= $eur($p['cents']) ?></span><?php endforeach; ?></p>
  <p class="plabel"><span class="en">Online, live by video</span><span class="de">Online, live per Video</span></p>
  <p><?php foreach (natal_packages_for('online') as $p): ?><span class="price"><?= $h(str_replace(' online', '', $p['title'])) ?> <?= $eur($p['cents']) ?></span><?php endforeach; ?></p>
  <ul>
    <li><span class="en">Every session is 60 minutes, one to one with me, either in the Movecia studio or live by video. The contract is concluded when you complete your payment, and you then receive your confirmation and invoice by email.</span><span class="de">Jede Einheit dauert 60 Minuten und findet 1:1 mit mir statt, entweder im Movecia Studio oder live per Video. Der Vertrag kommt mit deiner Zahlung zustande, danach bekommst du Bestätigung und Rechnung per E-Mail.</span></li>
    <li><span class="en">Single sessions and packages are valid until six months after the birth. If your baby arrives earlier than planned, you simply use the open sessions after the birth, and until your doctor clears you for training your sessions are on hold.</span><span class="de">Einzelstunden und Pakete gelten bis sechs Monate nach der Geburt. Kommt dein Baby früher als geplant, nutzt du die offenen Einheiten einfach nach der Geburt, und bis zur Freigabe durch deine Ärztin ruhen deine Einheiten.</span></li>
    <li><span class="en">You choose your preferred days and time of day, and I confirm every session with an exact time by email. If you need to move a session, please let me know as early as possible, ideally 24 hours before. If your baby or your health gets in the way, we will always find a solution.</span><span class="de">Du wählst deine Wunschtage und die Tageszeit, und ich bestätige dir jede Einheit mit Uhrzeit per E-Mail. Musst du einen Termin verschieben, gib mir bitte so früh wie möglich Bescheid, am besten bis 24 Stunden vorher. Wenn dein Baby oder deine Gesundheit dazwischenkommt, finden wir immer eine Lösung.</span></li>
    <li><span class="en">Please discuss the training with your doctor or midwife beforehand. The training accompanies your pregnancy and the time after the birth, but it does not replace medical care.</span><span class="de">Bitte sprich das Training vorher mit deiner Ärztin oder Hebamme ab. Das Training begleitet deine Schwangerschaft und die Zeit nach der Geburt, ersetzt aber keine medizinische Betreuung.</span></li>
    <li><span class="en">If I cannot hold a session (for example because I am ill), we arrange a new date. If that is not possible within the validity period, you get back the sessions you have not used.</span><span class="de">Kann ich eine Einheit nicht halten (zum Beispiel weil ich krank bin), vereinbaren wir einen neuen Termin. Geht das innerhalb der Gültigkeit nicht mehr, bekommst du die nicht genutzten Einheiten zurückerstattet.</span></li>
    <li><span class="en">After the getting-to-know-you call you may receive a personal bonus by email for a limited time. It applies only until the date stated in the email; the price shown when you pay is always the one that counts.</span><span class="de">Nach dem Kennenlerngespräch bekommst du eventuell einen persönlichen Bonus per E-Mail, der nur bis zu dem dort genannten Datum gilt. Es zählt immer der Preis, der dir beim Bezahlen angezeigt wird.</span></li>
    <li><span class="en">You have a 14-day right of withdrawal, see <a href="#withdrawal">below</a>.</span><span class="de">Du hast ein 14-tägiges Rücktrittsrecht, siehe <a href="#withdrawal">unten</a>.</span></li>
  </ul>

  <h2 id="retreat"><?= $h($r['title']) ?></h2>
  <p><span class="price"><?= $eur($r['price_cents']) ?> <span class="en">per person, all in</span><span class="de">pro Person, alles inklusive</span></span></p>
  <ul>
    <li><span class="en">Three days with two nights, Friday to Sunday. The price includes the full programme and two nights with breakfast in our guesthouse in the mountains.</span><span class="de">Drei Tage mit zwei Übernachtungen, von Freitag bis Sonntag. Der Preis umfasst das ganze Programm und zwei Übernachtungen mit Frühstück in unserem Gästehaus in den Bergen.</span></li>
    <li><span class="en">Retreats take place on fixed dates. For leisure services on a fixed date there is no statutory right of withdrawal (§ 18 (1) 10 FAGG). Instead, this cancellation policy applies:</span><span class="de">Retreats finden zu festen Terminen statt. Für Freizeitdienstleistungen zu einem festen Termin gibt es kein gesetzliches Rücktrittsrecht (§ 18 Abs. 1 Z 10 FAGG). Stattdessen gelten diese Stornobedingungen:</span>
      <ul>
        <li><span class="en">More than 60 days before the start: full refund, minus a handling fee of € 50.</span><span class="de">Mehr als 60 Tage vor Beginn: volle Rückerstattung abzüglich einer Bearbeitungsgebühr von € 50.</span></li>
        <li><span class="en">60 to 30 days before the start: 50 % refund.</span><span class="de">60 bis 30 Tage vor Beginn: 50 % Rückerstattung.</span></li>
        <li><span class="en">Less than 30 days before the start, or if you do not come: no refund.</span><span class="de">Weniger als 30 Tage vor Beginn oder bei Nichterscheinen: keine Rückerstattung.</span></li>
        <li><span class="en">You can send someone in your place at any time, free of charge.</span><span class="de">Du kannst jederzeit kostenlos eine Ersatzperson schicken.</span></li>
      </ul>
    </li>
    <li><span class="en">If I have to cancel the retreat (for example too few participants, illness or force majeure), you get the full price back. Travel costs cannot be refunded.</span><span class="de">Muss ich das Retreat absagen (zum Beispiel bei zu wenigen Teilnehmerinnen, Krankheit oder höherer Gewalt), bekommst du den vollen Preis zurück. Reisekosten können nicht erstattet werden.</span></li>
  </ul>

  <h2 id="free"><span class="en">Free offers</span><span class="de">Kostenlose Angebote</span></h2>
  <ul>
    <li><span class="en"><strong>Wild Hearts Gatherings:</strong> our online gatherings are free. You can sign up and cancel at any time, with no costs and no obligations.</span><span class="de"><strong>Wild Hearts Gatherings:</strong> Unsere Online-Gatherings sind kostenlos. Du kannst dich jederzeit an- und wieder abmelden, ohne Kosten und ohne Verpflichtung.</span></li>
    <li><span class="en"><strong>Getting-to-know-you call:</strong> the 30-minute video call before the prenatal and postnatal training is free and non-binding. If you can't make it, a short message is enough.</span><span class="de"><strong>Kennenlerngespräch:</strong> Das 30-minütige Videogespräch vor dem Prä- &amp; Postnatal Training ist kostenlos und unverbindlich. Wenn du doch nicht kannst, genügt eine kurze Nachricht.</span></li>
  </ul>

  <h2 id="withdrawal"><span class="en">Right of withdrawal</span><span class="de">Rücktrittsrecht</span></h2>
  <p><span class="en">This applies to everything you book with me online, in particular the prenatal and postnatal training in the studio and online. It does not apply to the retreat, for which the cancellation policy above applies. In short: you can withdraw within 14 days without giving reasons. If sessions have already taken place at your request, you only pay for those sessions.</span><span class="de">Das gilt für alles, was du online bei mir buchst, vor allem für das Prä- &amp; Postnatal Training im Studio und online. Für das Retreat gilt es nicht, dort gelten die Stornobedingungen oben. Kurz gesagt: Du kannst binnen 14 Tagen ohne Angabe von Gründen zurücktreten. Haben auf deinen Wunsch schon Einheiten stattgefunden, zahlst du nur diese Einheiten.</span></p>

  <div class="legal">
    <h3><span class="en">Withdrawal information</span><span class="de">Widerrufsbelehrung</span></h3>
    <p><strong><span class="en">Right of withdrawal</span><span class="de">Widerrufsrecht</span></strong></p>
    <p><span class="en">You have the right to withdraw from this contract within fourteen days without giving any reason. The withdrawal period is fourteen days from the day the contract is concluded.</span><span class="de">Du hast das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsabschlusses.</span></p>
    <p><span class="en">To exercise your right of withdrawal, you must inform me (<?= $h($adr) ?>, Austria, phone <?= $h(NATAL_COMPANY['phone']) ?>, email <?= $mail ?>) of your decision to withdraw from this contract by an unequivocal statement (for example a letter sent by post or an email). You can also simply use the button <a href="/withdraw">„Withdraw from the contract“</a> on this website. You may use the model withdrawal form below, but it is not obligatory. To meet the withdrawal deadline, it is sufficient for you to send your notice before the withdrawal period has expired.</span><span class="de">Um dein Widerrufsrecht auszuüben, musst du mich (<?= $h($adr) ?>, Österreich, Telefon <?= $h(NATAL_COMPANY['phone']) ?>, E-Mail <?= $mail ?>) mittels einer eindeutigen Erklärung (zum Beispiel ein mit der Post versandter Brief oder eine E-Mail) über deinen Entschluss, diesen Vertrag zu widerrufen, informieren. Du kannst deinen Rücktritt auch ganz einfach über die Schaltfläche <a href="/withdraw?lang=de">„Vertrag widerrufen“</a> auf dieser Website erklären. Du kannst dafür das untenstehende Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist. Zur Wahrung der Widerrufsfrist reicht es aus, dass du die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absendest.</span></p>
    <p><strong><span class="en">Effects of withdrawal</span><span class="de">Folgen des Widerrufs</span></strong></p>
    <p><span class="en">If you withdraw from this contract, I will reimburse all payments received from you without undue delay and in any event no later than fourteen days from the day on which I receive your notice of withdrawal. I will carry out the reimbursement using the same means of payment you used for the original transaction, unless expressly agreed otherwise with you; in any event, you will not incur any fees as a result of the reimbursement.</span><span class="de">Wenn du diesen Vertrag widerrufst, zahle ich dir alle Zahlungen, die ich von dir erhalten habe, unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurück, an dem die Mitteilung über deinen Widerruf dieses Vertrags bei mir eingegangen ist. Für diese Rückzahlung verwende ich dasselbe Zahlungsmittel, das du bei der ursprünglichen Transaktion eingesetzt hast, es sei denn, mit dir wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden dir wegen dieser Rückzahlung Entgelte berechnet.</span></p>
    <p><span class="en">If you requested that the services begin during the withdrawal period, you shall pay me an amount proportionate to what has been provided until you informed me of your withdrawal, in comparison with the full coverage of the contract. For the prenatal and postnatal training, this means the sessions that have already taken place.</span><span class="de">Hast du verlangt, dass die Dienstleistungen während der Widerrufsfrist beginnen sollen, so hast du mir einen angemessenen Betrag zu zahlen, der dem Anteil der bis zu dem Zeitpunkt, zu dem du mich von der Ausübung des Widerrufsrechts hinsichtlich dieses Vertrags unterrichtest, bereits erbrachten Dienstleistungen im Vergleich zum Gesamtumfang der im Vertrag vorgesehenen Dienstleistungen entspricht. Beim Prä- &amp; Postnatal Training sind das die Einheiten, die bereits stattgefunden haben.</span></p>
    <p><strong><span class="en">Early expiry</span><span class="de">Vorzeitiges Erlöschen</span></strong></p>
    <p><span class="en">The right of withdrawal expires before the end of the period once the service has been fully provided, if you expressly asked for it to begin during the withdrawal period and acknowledged that you lose your right of withdrawal on full performance (§ 18 (1) 1 FAGG). For a single session, this is the case once the session has taken place; for a package, once all sessions have taken place.</span><span class="de">Das Rücktrittsrecht erlischt vorzeitig, sobald die Dienstleistung vollständig erbracht ist, wenn du ausdrücklich verlangt hast, dass sie schon während der Rücktrittsfrist beginnt, und bestätigt hast, dass du dein Rücktrittsrecht bei vollständiger Vertragserfüllung verlierst (§ 18 Abs. 1 Z 1 FAGG). Bei einer Einzelstunde ist das der Fall, sobald die Einheit stattgefunden hat, bei einem Paket, sobald alle Einheiten stattgefunden haben.</span></p>
  </div>

  <p style="margin-top:16px"><a class="btn" href="/withdraw"><span class="en">Withdraw from the contract</span><span class="de">Vertrag widerrufen</span></a></p>

  <div class="legal">
    <h3 id="form"><span class="en">Model withdrawal form</span><span class="de">Muster-Widerrufsformular</span></h3>
    <div class="form">
      <p class="de">(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</p>
      <p class="de">– An <?= $h($adr) ?>, Österreich, E-Mail: <?= $mail ?>:<br>
      – Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*)/die Erbringung der folgenden Dienstleistung (*)<br>
      – Bestellt am (*)/erhalten am (*)<br>
      – Name des/der Verbraucher(s)<br>
      – Anschrift des/der Verbraucher(s)<br>
      – Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)<br>
      – Datum</p>
      <p class="de small">(*) Unzutreffendes streichen.</p>
      <p class="en">(Complete and return this form only if you wish to withdraw from the contract.)</p>
      <p class="en">– To <?= $h($adr) ?>, Austria, email: <?= $mail ?>:<br>
      – I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*)/for the provision of the following service (*),<br>
      – Ordered on (*)/received on (*),<br>
      – Name of consumer(s),<br>
      – Address of consumer(s),<br>
      – Signature of consumer(s) (only if this form is notified on paper),<br>
      – Date</p>
      <p class="en small">(*) Delete as appropriate.</p>
    </div>
  </div>

  <details class="old">
    <summary><span class="en">Earlier offers: The Inner Circle &amp; The Year Circle</span><span class="de">Frühere Angebote: The Inner Circle &amp; Jahreskreis</span></summary>
    <p class="small"><span class="en">These offers can no longer be booked. For bookings that already exist, the following terms continue to apply, together with the right of withdrawal above.</span><span class="de">Diese Angebote sind nicht mehr buchbar. Für bereits bestehende Buchungen gelten weiterhin die folgenden Bedingungen, zusammen mit dem Rücktrittsrecht oben.</span></p>
    <h3><?= $h($c['title']) ?></h3>
    <ul>
      <li><span class="en">A monthly membership (<?= $eur($c['price_cents']) ?> per month) that renews every month until you cancel. You can cancel at any time, effective at the end of the period you have already paid, with a short email. Periods that have already started are not refunded, except within the withdrawal period.</span><span class="de">Eine monatliche Mitgliedschaft (<?= $eur($c['price_cents']) ?> pro Monat), die sich jeden Monat verlängert, bis du kündigst. Du kannst jederzeit zum Ende des bereits bezahlten Zeitraums kündigen, eine kurze E-Mail genügt. Begonnene Zeiträume werden außerhalb der Rücktrittsfrist nicht erstattet.</span></li>
<?php $cst = mm_circle_start(); if ($cst): $cd = (new DateTimeImmutable('@' . $cst))->setTimezone(new DateTimeZone('Europe/Vienna')); ?>
      <li><span class="en">If you booked before the opening day (<?= $h($cd->format('j F Y')) ?>), nothing is charged before that day and your months count from then. You can withdraw or cancel free of charge before the opening day.</span><span class="de">Hast du vor dem Eröffnungstag (<?= $h($cd->format('j.n.Y')) ?>) gebucht, wird vorher nichts abgebucht, und deine Monate zählen ab diesem Tag. Vor dem Eröffnungstag kannst du kostenlos widerrufen oder kündigen.</span></li>
<?php endif; ?>
      <li><span class="en">A Gathering price (<?= $eur($c['special_cents']) ?> per month) applies only to the number of months stated in your booking confirmation, at most <?= (int)$c['max_months'] ?> months in total. After that, the regular price applies.</span><span class="de">Ein Gathering-Preis (<?= $eur($c['special_cents']) ?> pro Monat) gilt nur für die in deiner Buchungsbestätigung genannten Monate, insgesamt höchstens <?= (int)$c['max_months'] ?> Monate. Danach gilt der reguläre Preis.</span></li>
    </ul>
    <h3><?= $h($y['title']) ?></h3>
    <ul>
      <li><span class="en">A fixed twelve-month agreement (<?= $eur($y['price_cents']) ?>, or in instalments that end automatically after the last payment). After your booking we have a personal call; if it is not the right fit, you get your full payment back. Both of us can end it early for an important reason, and you get back the part you have paid for and not yet received.</span><span class="de">Eine feste Vereinbarung über zwölf Monate (<?= $eur($y['price_cents']) ?> oder in Raten, die nach der letzten Rate automatisch enden). Nach deiner Buchung führen wir ein persönliches Gespräch; passt es nicht, bekommst du deine Zahlung vollständig zurück. Wir beide können aus wichtigem Grund vorzeitig beenden, dann bekommst du den bezahlten, noch nicht erhaltenen Teil zurück.</span></li>
    </ul>
    <h3 id="digital"><span class="en">Course area and downloads (digital content)</span><span class="de">Kursbereich und Downloads (digitale Inhalte)</span></h3>
    <p><span class="en">If you ticked the box for immediate access when booking, you expressly agreed that the course area and downloads are unlocked right away and acknowledged that you thereby lose your right of withdrawal for this digital content (§ 18 (1) 11 FAGG). Without the tick, the full course area opens 14 days after your booking. For the live parts, the right of withdrawal above remains.</span><span class="de">Hast du bei der Buchung das Häkchen für den sofortigen Zugang gesetzt, hast du ausdrücklich zugestimmt, dass Kursbereich und Downloads sofort freigeschaltet werden, und bestätigt, dass du damit dein Rücktrittsrecht für diese digitalen Inhalte verlierst (§ 18 Abs. 1 Z 11 FAGG). Ohne Häkchen öffnet sich der ganze Kursbereich 14 Tage nach deiner Buchung. Für die Live-Teile bleibt das Rücktrittsrecht oben bestehen.</span></p>
  </details>

  <h2><span class="en">Questions</span><span class="de">Häufige Fragen</span></h2>
  <details><summary><span class="en">How do I withdraw?</span><span class="de">Wie trete ich zurück?</span></summary><p><span class="en">Tap <a href="/withdraw">„Withdraw from the contract“</a>, fill in the short form and confirm. You immediately receive a confirmation with the date and time by email, and I refund you within 14 days.</span><span class="de">Tipp auf <a href="/withdraw?lang=de">„Vertrag widerrufen“</a>, füll das kurze Formular aus und bestätige. Du bekommst sofort eine Bestätigung mit Datum und Uhrzeit per E-Mail, und ich erstatte dir binnen 14 Tagen.</span></p></details>
  <details><summary><span class="en">I have already had sessions. What do I pay if I withdraw?</span><span class="de">Ich hatte schon Einheiten. Was zahle ich, wenn ich zurücktrete?</span></summary><p><span class="en">Only the sessions that have already taken place, at the price per session of your package. You get the rest back.</span><span class="de">Nur die Einheiten, die schon stattgefunden haben, zum Einheitenpreis deines Pakets. Den Rest bekommst du zurück.</span></p></details>
  <details><summary><span class="en">I need to move a session. What now?</span><span class="de">Ich muss einen Termin verschieben. Was nun?</span></summary><p><span class="en">Let me know as early as possible, ideally 24 hours before, by WhatsApp or email. If your baby or your health gets in the way, we will always find a solution.</span><span class="de">Gib mir so früh wie möglich Bescheid, am besten bis 24 Stunden vorher, per WhatsApp oder E-Mail. Wenn dein Baby oder deine Gesundheit dazwischenkommt, finden wir immer eine Lösung.</span></p></details>
  <details><summary><span class="en">What if my baby comes early?</span><span class="de">Was ist, wenn mein Baby früher kommt?</span></summary><p><span class="en">Then you use your open sessions after the birth. Nothing expires before six months after the birth.</span><span class="de">Dann nutzt du deine offenen Einheiten nach der Geburt. Vor Ablauf von sechs Monaten nach der Geburt verfällt nichts.</span></p></details>
  <details><summary><span class="en">Do I get an invoice?</span><span class="de">Bekomme ich eine Rechnung?</span></summary><p><span class="en">Yes, automatically by email after your payment. Write to me if you need an invoice with your company details.</span><span class="de">Ja, automatisch per E-Mail nach deiner Zahlung. Schreib mir, wenn du eine Rechnung mit deinen Firmendaten brauchst.</span></p></details>
  <details><summary><span class="en">Can my partner or children come to the retreat?</span><span class="de">Können mein Partner oder meine Kinder zum Retreat mitkommen?</span></summary><p><span class="en">Depending on the group, partners and children can sometimes be included, and private retreats are possible too. Just ask me.</span><span class="de">Je nach Gruppe können Partner und Kinder manchmal mit eingebunden werden, und auch private Retreats sind möglich. Frag mich einfach.</span></p></details>
  <details><summary><span class="en">Do I have to turn my camera on in a Gathering?</span><span class="de">Muss ich bei einem Gathering meine Kamera einschalten?</span></summary><p><span class="en">No. Your camera can stay on, but it doesn't have to. Please keep your microphone muted unless you want to speak.</span><span class="de">Nein. Deine Kamera darf an bleiben, muss aber nicht. Bitte lass dein Mikrofon stumm, außer du möchtest etwas sagen.</span></p></details>

  <p class="small" style="margin-top:36px"><span class="en">As of 23 October 2026.</span><span class="de">Stand: 23. Oktober 2026.</span> · <a href="/">moveciamovement.com</a></p>
</main>
<script>
(() => {
  const set = l => { document.documentElement.lang = l; document.querySelectorAll('.lang button').forEach(b => b.classList.toggle('on', b.dataset.l === l)); try { localStorage.setItem('wh-lang', l); } catch (e) {} };
  let l = new URLSearchParams(location.search).get('lang');
  if (!l) { try { l = localStorage.getItem('wh-lang'); } catch (e) {} }
  if (!l) l = (navigator.language || 'de').slice(0, 2) === 'en' ? 'en' : 'de';
  set(l === 'en' ? 'en' : 'de');
  document.querySelectorAll('.lang button').forEach(b => b.onclick = () => set(b.dataset.l));
  if (location.hash === '#digital') { const d = document.querySelector('details.old'); if (d) d.open = true; }
})();
</script>
</body>
</html>

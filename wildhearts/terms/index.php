<?php
// Bedingungen für die Wild-Hearts-Angebote (Englisch/Deutsch), Preise live aus den Einstellungen.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/movecia-lib.php';
header('Cache-Control: no-cache');
$o = mm_offers();
$eur = fn(int $c) => '€' . ($c % 100 === 0 ? number_format($c / 100, 0, ',', '.') : number_format($c / 100, 2, ',', '.'));
$c = $o['circle']; $y = $o['year']; $r = $o['retreat'];
$h = 'forma_h';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Terms &amp; cancellation · Wild Hearts</title>
<meta name="description" content="Terms, cancellation policy, right of withdrawal and frequently asked questions for The Inner Circle, The Year Circle and the Wild Hearts Alpine Retreat.">
<link rel="icon" href="/img/wildhearts/viv.jpg">
<style>
@font-face{font-family:"Italiana";font-display:swap;src:url(/fonts/Italiana-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:300 500;font-display:swap;src:url(/fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:600 800;font-display:swap;src:url(/fonts/Outfit-Bold.woff) format("woff")}
@font-face{font-family:"Hand";font-display:swap;src:url(/fonts/HomemadeApple-Regular.woff) format("woff")}
:root{--cream:#F2ECE4;--wine:#7D5E68;--wine-deep:#6B5658;--brown:#7D7076;--ink:#2E2529;--sage-soft:#EADFDE;--line:#DDD2C6}
*{box-sizing:border-box;margin:0}
html,body{background:var(--cream)}
body{font-family:"Outfit",system-ui,sans-serif;color:var(--ink);font-size:17px;line-height:1.65;-webkit-font-smoothing:antialiased}
main{max-width:860px;margin:0 auto;padding:40px 16px 60px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.hand{font-family:"Hand",cursive;color:var(--wine);font-size:20px;transform:rotate(-2deg);display:inline-block}
h1{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(36px,7vw,52px);line-height:1.08;color:var(--wine-deep);margin:8px 0 6px}
h2{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:30px;color:var(--wine-deep);margin:36px 0 8px}
h3{font-size:18px;font-weight:600;color:var(--wine-deep);margin:20px 0 4px}
p,li{color:#4A3A41}
ul{padding-left:22px}
li{margin:4px 0}
.lead{color:var(--brown)}
.card{margin-top:14px;padding:18px 20px;border-radius:22px;background:#FBF8F4;border:1.5px solid var(--line)}
.price{display:inline-block;padding:3px 12px;border-radius:999px;background:var(--sage-soft);color:var(--wine-deep);font-weight:500;font-size:15px;margin:2px 4px 2px 0}
.lang{display:flex;gap:6px}
.lang button{font:inherit;font-size:14px;padding:6px 14px;border-radius:999px;border:1.5px solid var(--line);background:transparent;color:var(--wine-deep);cursor:pointer}
.lang button.on{background:var(--wine);border-color:var(--wine);color:var(--cream)}
.btn{display:inline-block;margin-top:10px;padding:13px 24px;border-radius:999px;background:var(--wine);color:var(--cream);text-decoration:none;font-weight:500}
details{border-top:1px solid var(--line);padding:12px 0}
summary{cursor:pointer;font-weight:500;color:var(--wine-deep)}
details p{margin-top:6px}
.small{font-size:14px;color:var(--brown)}
[lang="de"] .en,[lang="en"] .de{display:none}
a{color:var(--wine)}
</style>
</head>
<body>
<main>
  <div class="top"><span class="hand">with love, from the mountains ♡</span><div class="lang"><button type="button" data-l="en">EN</button><button type="button" data-l="de">DE</button></div></div>
  <h1><span class="en">Terms &amp; cancellation</span><span class="de">Bedingungen &amp; Storno</span></h1>
  <p class="lead"><span class="en">Clear and fair, so you can decide with a good feeling. If anything is unclear or life gets in the way, just write to me. We will always find a way together.</span><span class="de">Klar und fair, damit du mit einem guten Gefühl entscheiden kannst. Wenn etwas unklar ist oder das Leben dazwischenkommt, schreib mir einfach. Wir finden immer gemeinsam einen Weg.</span></p>

  <div class="card">
    <p><strong>Movecia – Personal Fitness</strong> · Vivien Malonga<br>Silvrettastraße 58, 6562 Mathon, Austria<br><a href="mailto:<?= $h(MM_FROM_EMAIL) ?>"><?= $h(MM_FROM_EMAIL) ?></a> · UID ATU83602825</p>
    <p class="small" style="margin-top:6px"><span class="en">Payments are processed securely by Stripe. You receive a confirmation by email after every booking.</span><span class="de">Zahlungen werden sicher über Stripe abgewickelt. Nach jeder Buchung bekommst du eine Bestätigung per E-Mail.</span></p>
  </div>

  <h2><span class="en">Wild Hearts Gatherings</span><span class="de">Wild Hearts Gatherings</span></h2>
  <p><span class="en">Our online Gatherings are free. You can sign up and cancel at any time, no costs and no obligations.</span><span class="de">Unsere Online-Gatherings sind kostenlos. Du kannst dich jederzeit anmelden und wieder abmelden, ohne Kosten und ohne Verpflichtung.</span></p>

  <h2><?= $h($c['title']) ?></h2>
  <p><span class="price"><?= $eur($c['price_cents']) ?> <span class="en">per month</span><span class="de">pro Monat</span></span></p>
  <ul>
    <li><span class="en">A monthly membership. It starts when you book and renews every month until you cancel.</span><span class="de">Eine monatliche Mitgliedschaft. Sie beginnt mit deiner Buchung und verlängert sich jeden Monat, bis du kündigst.</span></li>
    <li><span class="en">You can cancel at any time, effective at the end of the month you have already paid. Simply write to me by email. Months that have already started are not refunded (except within the withdrawal period, see below).</span><span class="de">Du kannst jederzeit zum Ende des bereits bezahlten Monats kündigen, eine kurze E-Mail an mich genügt. Begonnene Monate werden nicht erstattet (außer innerhalb der Rücktrittsfrist, siehe unten).</span></li>
<?php $cst = mm_circle_start(); if ($cst): $cd = (new DateTimeImmutable('@' . $cst))->setTimezone(new DateTimeZone('Europe/Vienna')); ?>
    <li><span class="en">The Inner Circle opens on <?= $h($cd->format('j F Y')) ?>. You can book before that date: your card is saved, nothing is charged, and your first payment is taken on the opening day. Your membership months, the Gathering price months and your notice period count from that day. You can withdraw or cancel free of charge before the opening day.</span><span class="de">The Inner Circle öffnet am <?= $h($cd->format('j.n.Y')) ?>. Du kannst schon vorher buchen: Deine Karte wird hinterlegt, abgebucht wird nichts, die erste Zahlung kommt am Eröffnungstag. Deine Mitgliedsmonate, die Monate zum Gathering-Preis und die Kündigung zählen ab diesem Tag. Vor dem Eröffnungstag kannst du kostenlos widerrufen oder kündigen.</span></li>
<?php endif; ?>
<?php foreach ($c['periods'] as $pp): $pm = $eur((int)round($pp[1] / $pp[0])); ?>
    <li><span class="en"><?= $pp[0] === 12 ? 'Yearly' : '6 months' ?> in advance: <?= $eur($pp[1]) ?> (<?= $pm ?> per month). It renews for the same period until you cancel; you can cancel at any time, effective at the end of the period you have paid. No additional Gathering discount applies to this price.</span><span class="de"><?= $pp[0] === 12 ? 'Ein Jahr' : '6 Monate' ?> im Voraus: <?= $eur($pp[1]) ?> (<?= $pm ?> pro Monat). Verlängert sich um denselben Zeitraum, bis du kündigst; du kannst jederzeit zum Ende des bezahlten Zeitraums kündigen. Auf diesen Preis gibt es keinen zusätzlichen Gathering-Rabatt.</span></li>
<?php endforeach; ?>
    <li><span class="en">If you book at a Gathering price (<?= $eur($c['special_cents']) ?> per month), this price applies to your first <?= (int)$c['intro_months'] ?> months. After that, the regular price of <?= $eur($c['price_cents']) ?> per month applies automatically.</span><span class="de">Buchst du zum Gathering-Preis (<?= $eur($c['special_cents']) ?> pro Monat), gilt dieser Preis für deine ersten <?= (int)$c['intro_months'] ?> Monate. Danach gilt automatisch der reguläre Preis von <?= $eur($c['price_cents']) ?> pro Monat.</span></li>
    <li><span class="en">Invite a friend: at the beginning of your third month you receive a personal invitation link. If a friend books with it, she pays <?= $eur($c['special_cents']) ?> per month for her first <?= (int)$c['intro_months'] ?> months, and you get <?= (int)$c['ref_months'] ?> more months at <?= $eur($c['special_cents']) ?>. This works again for every friend.</span><span class="de">Freundin einladen: Zu Beginn deines dritten Monats bekommst du einen persönlichen Einladungslink. Bucht eine Freundin damit, zahlt sie für ihre ersten <?= (int)$c['intro_months'] ?> Monate <?= $eur($c['special_cents']) ?> pro Monat, und du bekommst <?= (int)$c['ref_months'] ?> weitere Monate zu <?= $eur($c['special_cents']) ?>. Das gilt für jede Freundin wieder.</span></li>
    <li><span class="en">If you have not invited anyone, you receive a personal code shortly before your first <?= (int)$c['intro_months'] ?> months end: <?= (int)$c['ref_months'] ?> more months at <?= $eur($c['special_cents']) ?>, directly afterwards. The code is valid only until the date stated in the email, after that the regular price applies.</span><span class="de">Hast du niemanden eingeladen, bekommst du kurz vor Ende deiner ersten <?= (int)$c['intro_months'] ?> Monate einen persönlichen Code: <?= (int)$c['ref_months'] ?> weitere Monate zu <?= $eur($c['special_cents']) ?>, direkt im Anschluss. Der Code gilt nur bis zu dem Datum in der E-Mail, danach gilt der reguläre Preis.</span></li>
    <li><span class="en">Each woman pays the Gathering price for a maximum of <?= (int)$c['max_months'] ?> months in total, including her first months, however many friends she invites. Your friend always gets her first <?= (int)$c['intro_months'] ?> months.</span><span class="de">Jede Frau zahlt den Gathering-Preis insgesamt höchstens <?= (int)$c['max_months'] ?> Monate lang, ihre ersten Monate mitgezählt, egal wie viele Freundinnen sie einlädt. Deine Freundin bekommt ihre ersten <?= (int)$c['intro_months'] ?> Monate immer.</span></li>
  </ul>

  <h2><?= $h($y['title']) ?></h2>
  <p><span class="price"><?= $eur($y['price_cents']) ?> <span class="en">in full</span><span class="de">auf einmal</span></span><?php foreach ($y['plans'] as $pl): ?><span class="price"><span class="en">or</span><span class="de">oder</span> <?= (int)$pl[0] ?> × <?= $eur($pl[1]) ?> <span class="en">monthly</span><span class="de">monatlich</span></span><?php endforeach; ?></p>
  <ul>
    <li><span class="en">Twelve months of mentoring with The Inner Circle included. There are only four places each year.</span><span class="de">Zwölf Monate Begleitung, The Inner Circle ist inklusive. Es gibt nur vier Plätze im Jahr.</span></li>
    <li><span class="en">After your booking, we have a personal call. If we both feel that the Year Circle is not the right fit for you right now, you get your full payment back.</span><span class="de">Nach deiner Buchung führen wir ein persönliches Gespräch. Wenn wir beide spüren, dass der Jahreskreis gerade nicht das Richtige für dich ist, bekommst du deine Zahlung vollständig zurück.</span></li>
    <li><span class="en">The Year Circle is a fixed twelve-month agreement. You can pay in full or in 3 or 6 monthly payments. These are a payment plan for this agreement, not a subscription, and they end automatically after the last payment.</span><span class="de">Der Jahreskreis ist eine feste Vereinbarung über zwölf Monate. Du kannst auf einmal oder in 3 oder 6 Monatsraten zahlen. Die Raten sind eine Ratenzahlung dieser Vereinbarung und kein Abo, und sie enden automatisch nach der letzten Rate.</span></li>
    <li><span class="en">Both of us can end the Year Circle early for an important reason (for example serious illness). If I can no longer accompany you, you get back the part you have paid for and not yet received.</span><span class="de">Wir beide können den Jahreskreis aus wichtigem Grund vorzeitig beenden (zum Beispiel bei schwerer Krankheit). Kann ich dich nicht mehr begleiten, bekommst du den bezahlten, noch nicht erhaltenen Teil zurück.</span></li>
  </ul>

  <h2><?= $h($r['title']) ?></h2>
  <p><span class="price"><?= $eur($r['price_cents']) ?> <span class="en">per person, all in</span><span class="de">pro Person, alles inklusive</span></span></p>
  <ul>
    <li><span class="en">Three days with two nights, Friday to Sunday. The price includes the full programme (yoga, Pilates, breathwork, forest walks, pottery including material, the women's circle and mentoring) and two nights with breakfast in our guesthouse in the mountains.</span><span class="de">Drei Tage mit zwei Übernachtungen, von Freitag bis Sonntag. Der Preis umfasst das ganze Programm (Yoga, Pilates, Atemarbeit, Waldspaziergänge, Töpfern inklusive Material, Frauenkreis und Mentoring) und zwei Übernachtungen mit Frühstück in unserem Gästehaus in den Bergen.</span></li>
    <li><span class="en">Retreats take place on fixed dates. For leisure services on a fixed date there is no statutory right of withdrawal (§ 18 (1) 10 FAGG). Instead, this cancellation policy applies:</span><span class="de">Retreats finden zu festen Terminen statt. Für Freizeitdienstleistungen zu einem festen Termin gibt es kein gesetzliches Rücktrittsrecht (§ 18 Abs. 1 Z 10 FAGG). Stattdessen gelten diese Stornobedingungen:</span>
      <ul>
        <li><span class="en">More than 60 days before the start: full refund, minus a handling fee of €50.</span><span class="de">Mehr als 60 Tage vor Beginn: volle Rückerstattung abzüglich einer Bearbeitungsgebühr von € 50.</span></li>
        <li><span class="en">60 to 30 days before the start: 50 % refund.</span><span class="de">60 bis 30 Tage vor Beginn: 50 % Rückerstattung.</span></li>
        <li><span class="en">Less than 30 days before the start, or if you do not come: no refund.</span><span class="de">Weniger als 30 Tage vor Beginn oder bei Nichterscheinen: keine Rückerstattung.</span></li>
        <li><span class="en">You can send someone in your place at any time, free of charge.</span><span class="de">Du kannst jederzeit kostenlos eine Ersatzperson schicken.</span></li>
      </ul>
    </li>
    <li><span class="en">If I have to cancel the retreat (for example too few participants, illness or force majeure), you get the full price back. Travel costs cannot be refunded.</span><span class="de">Muss ich das Retreat absagen (zum Beispiel bei zu wenigen Teilnehmerinnen, Krankheit oder höherer Gewalt), bekommst du den vollen Preis zurück. Reisekosten können nicht erstattet werden.</span></li>
  </ul>

  <h2 id="withdrawal"><span class="en">Right of withdrawal</span><span class="de">Rücktrittsrecht</span></h2>
  <p class="small"><span class="en">For The Inner Circle and The Year Circle.</span><span class="de">Für The Inner Circle und den Jahreskreis.</span></p>
  <h3><span class="en">Your right</span><span class="de">Dein Recht</span></h3>
  <p><span class="en">You can withdraw from this contract within 14 days without giving any reason. The withdrawal period is 14 days from the day the contract is concluded.</span><span class="de">Du kannst binnen 14 Tagen ohne Angabe von Gründen von diesem Vertrag zurücktreten. Die Rücktrittsfrist beträgt 14 Tage ab dem Tag des Vertragsabschlusses.</span></p>
  <h3><span class="en">How to withdraw</span><span class="de">So trittst du zurück</span></h3>
  <p><span class="en">Use the button below, or send a clear statement (for example by email) to Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon, Austria, <?= $h(MM_FROM_EMAIL) ?>. It is enough to send your withdrawal before the 14 days have passed.</span><span class="de">Nutze den Knopf unten oder schick eine eindeutige Erklärung (zum Beispiel per E-Mail) an Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon, Österreich, <?= $h(MM_FROM_EMAIL) ?>. Es genügt, wenn du deinen Rücktritt vor Ablauf der 14 Tage abschickst.</span></p>
  <p><a class="btn" href="/withdraw"><span class="en">Withdraw from the contract</span><span class="de">Vertrag widerrufen</span></a></p>
  <h3><span class="en">What happens then</span><span class="de">Was dann passiert</span></h3>
  <p><span class="en">I will refund all payments you made within 14 days of receiving your withdrawal, using the same payment method you used. If you asked for your access to start during the withdrawal period, you pay an amount proportional to what was already provided until your withdrawal.</span><span class="de">Ich erstatte dir alle Zahlungen binnen 14 Tagen ab Eingang deines Rücktritts, mit demselben Zahlungsmittel, das du verwendet hast. Hast du verlangt, dass dein Zugang schon während der Rücktrittsfrist beginnt, zahlst du einen anteiligen Betrag für das, was bis zu deinem Rücktritt bereits erbracht wurde.</span></p>
  <h3 id="digital"><span class="en">Course area and downloads (digital content)</span><span class="de">Kursbereich und Downloads (digitale Inhalte)</span></h3>
  <p><span class="en">The Inner Circle and The Year Circle include a course area with videos, PDFs and downloads. When you book, you can choose:</span><span class="de">Zu The Inner Circle und dem Jahreskreis gehört ein Kursbereich mit Videos, PDFs und Downloads. Bei der Buchung kannst du wählen:</span></p>
  <ul>
    <li><span class="en"><strong>Immediate access:</strong> you tick the box that the course area and downloads are unlocked right away, and you confirm that you thereby lose your right of withdrawal for this digital content (§ 18 (1) 11 FAGG). You receive this consent in writing with your booking confirmation.</span><span class="de"><strong>Sofortiger Zugang:</strong> Du setzt das Häkchen, dass Kursbereich und Downloads sofort freigeschaltet werden, und bestätigst, dass du damit dein Rücktrittsrecht für diese digitalen Inhalte verlierst (§ 18 Abs. 1 Z 11 FAGG). Diese Zustimmung bekommst du mit deiner Buchungsbestätigung schriftlich.</span></li>
    <li><span class="en"><strong>Without the tick:</strong> you start with the starter section. The full course area and all downloads unlock automatically 14 days after your booking, when the withdrawal period is over.</span><span class="de"><strong>Ohne Häkchen:</strong> Du startest mit dem Starter-Bereich. Der ganze Kursbereich und alle Downloads öffnen sich automatisch 14 Tage nach deiner Buchung, wenn die Rücktrittsfrist vorbei ist.</span></li>
  </ul>
  <p><span class="en">For the live parts (gatherings, circles, 1:1 mentoring) your right of withdrawal remains as described above.</span><span class="de">Für die Live-Teile (Gatherings, Kreise, 1:1-Mentoring) bleibt dein Rücktrittsrecht wie oben beschrieben bestehen.</span></p>
  <h3><span class="en">Model withdrawal form</span><span class="de">Muster-Widerrufsformular</span></h3>
  <p class="small"><span class="en">(If you want to withdraw, you can fill in this form and send it to me.) To Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon, Austria, <?= $h(MM_FROM_EMAIL) ?>: I hereby withdraw from the contract I concluded for the following service: … / Ordered on: … / Name: … / Address: … / Date: … / Signature (only on paper).</span><span class="de">(Wenn du den Vertrag widerrufen willst, kannst du dieses Formular ausfüllen und an mich schicken.) An Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon, Österreich, <?= $h(MM_FROM_EMAIL) ?>: Hiermit trete ich von dem von mir abgeschlossenen Vertrag über die Erbringung der folgenden Dienstleistung zurück: … / Bestellt am: … / Name: … / Anschrift: … / Datum: … / Unterschrift (nur auf Papier).</span></p>

  <h2><span class="en">Questions</span><span class="de">Häufige Fragen</span></h2>
  <details><summary><span class="en">Do I need any experience?</span><span class="de">Brauche ich Vorerfahrung?</span></summary><p><span class="en">No. Everything is gentle and you can adapt every practice to how you feel that day.</span><span class="de">Nein. Alles ist sanft, und du kannst jede Übung an deine Tagesform anpassen.</span></p></details>
  <details><summary><span class="en">Do I have to turn my camera on?</span><span class="de">Muss ich meine Kamera einschalten?</span></summary><p><span class="en">No. Your camera can stay on, but it doesn't have to. Please keep your microphone muted unless you want to speak.</span><span class="de">Nein. Deine Kamera darf an bleiben, muss aber nicht. Bitte lass dein Mikrofon stumm, außer du möchtest etwas sagen.</span></p></details>
  <details><summary><span class="en">What if I miss a Gathering?</span><span class="de">Was ist, wenn ich ein Gathering verpasse?</span></summary><p><span class="en">Selected Gatherings are recorded. Only I am visible in the recordings, never the participants.</span><span class="de">Ausgewählte Gatherings werden aufgezeichnet. Auf den Aufnahmen bin nur ich zu sehen, nie die Teilnehmerinnen.</span></p></details>
  <details><summary><span class="en">How long does the Gathering price last?</span><span class="de">Wie lange gilt der Gathering-Preis?</span></summary><p><span class="en">For your first <?= (int)$c['intro_months'] ?> months. With every friend you invite, or with your personal code at the end of your first months, it continues for <?= (int)$c['ref_months'] ?> more months, up to <?= (int)$c['max_months'] ?> months in total. Then the regular price of <?= $eur($c['price_cents']) ?> per month applies.</span><span class="de">Für deine ersten <?= (int)$c['intro_months'] ?> Monate. Mit jeder Freundin, die du einlädst, oder mit deinem persönlichen Code am Ende deiner ersten Monate gilt er <?= (int)$c['ref_months'] ?> weitere Monate, insgesamt höchstens <?= (int)$c['max_months'] ?> Monate. Danach gilt der reguläre Preis von <?= $eur($c['price_cents']) ?> pro Monat.</span></p></details>
  <details><summary><span class="en">How do I cancel The Inner Circle?</span><span class="de">Wie kündige ich The Inner Circle?</span></summary><p><span class="en">Write me a short email. Your membership ends at the end of the month you have already paid.</span><span class="de">Schreib mir eine kurze E-Mail. Deine Mitgliedschaft endet zum Ende des bereits bezahlten Monats.</span></p></details>
  <details><summary><span class="en">Can I pay the Year Circle in instalments?</span><span class="de">Kann ich den Jahreskreis in Raten zahlen?</span></summary><p><span class="en">Yes, in 3 or 6 monthly payments, or all at once. They are taken automatically and stop after the last one.</span><span class="de">Ja, in 3 oder 6 Monatsraten oder auf einmal. Sie werden automatisch abgebucht und hören nach der letzten Rate auf.</span></p></details>
  <details><summary><span class="en">Why is the course area not fully open right away?</span><span class="de">Warum ist der Kursbereich nicht sofort ganz offen?</span></summary><p><span class="en">It is, if you choose immediate access when you book. If you don't, you keep your full right of withdrawal, start with the starter section, and everything unlocks after 14 days.</span><span class="de">Doch, wenn du bei der Buchung den sofortigen Zugang wählst. Wenn nicht, behältst du dein volles Rücktrittsrecht, startest mit dem Starter-Bereich, und nach 14 Tagen öffnet sich alles.</span></p></details>
  <details><summary><span class="en">Can I withdraw after booking?</span><span class="de">Kann ich nach der Buchung zurücktreten?</span></summary><p><span class="en">For The Inner Circle and The Year Circle: yes, within 14 days, using the button „Withdraw from the contract“. If you chose immediate access to the course area, the withdrawal right for that digital content no longer applies, and for live parts already used you pay a proportional amount. For the retreat, the cancellation policy above applies.</span><span class="de">Bei The Inner Circle und dem Jahreskreis: ja, innerhalb von 14 Tagen über den Knopf „Vertrag widerrufen“. Hast du den sofortigen Zugang zum Kursbereich gewählt, gilt das Rücktrittsrecht für diese digitalen Inhalte nicht mehr, und für bereits genutzte Live-Teile zahlst du anteilig. Beim Retreat gelten die Stornobedingungen oben.</span></p></details>
  <details><summary><span class="en">Can my partner or children come to the retreat?</span><span class="de">Können mein Partner oder meine Kinder zum Retreat mitkommen?</span></summary><p><span class="en">Depending on the group, partners and children can sometimes be included, and private retreats are possible too. Just ask me.</span><span class="de">Je nach Gruppe können Partner und Kinder manchmal mit eingebunden werden, und auch private Retreats sind möglich. Frag mich einfach.</span></p></details>
  <details><summary><span class="en">Do I get an invoice?</span><span class="de">Bekomme ich eine Rechnung?</span></summary><p><span class="en">Yes. Write to me if you need an invoice with your company details.</span><span class="de">Ja. Schreib mir, wenn du eine Rechnung mit deinen Firmendaten brauchst.</span></p></details>

  <p class="small" style="margin-top:36px"><span class="en">As of October 2026.</span><span class="de">Stand: Oktober 2026.</span> · <a href="/">moveciamovement.com</a></p>
</main>
<script>
(() => {
  const set = l => { document.documentElement.lang = l; document.querySelectorAll('.lang button').forEach(b => b.classList.toggle('on', b.dataset.l === l)); try { localStorage.setItem('wh-lang', l); } catch (e) {} };
  let l = new URLSearchParams(location.search).get('lang');
  if (!l) { try { l = localStorage.getItem('wh-lang'); } catch (e) {} }
  if (!l) l = (navigator.language || 'en').slice(0, 2) === 'de' ? 'de' : 'en';
  set(l === 'de' ? 'de' : 'en');
  document.querySelectorAll('.lang button').forEach(b => b.onclick = () => set(b.dataset.l));
})();
</script>
</body>
</html>

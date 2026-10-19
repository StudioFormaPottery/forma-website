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
:root{--cream:#F6F3EA;--wine:#5E6B35;--wine-deep:#3F4A26;--brown:#6B5D4C;--ink:#2F2B22;--sage-soft:#DCE0D2;--line:#CFC9B5}
*{box-sizing:border-box;margin:0}
html,body{background:var(--cream)}
body{font-family:"Outfit",system-ui,sans-serif;color:var(--ink);font-size:17px;line-height:1.65;-webkit-font-smoothing:antialiased}
main{max-width:860px;margin:0 auto;padding:40px 16px 60px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.hand{font-family:"Hand",cursive;color:var(--wine);font-size:20px;transform:rotate(-2deg);display:inline-block}
h1{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(36px,7vw,52px);line-height:1.08;color:var(--wine-deep);margin:8px 0 6px}
h2{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:30px;color:var(--wine-deep);margin:36px 0 8px}
h3{font-size:18px;font-weight:600;color:var(--wine-deep);margin:20px 0 4px}
p,li{color:#3E372C}
ul{padding-left:22px}
li{margin:4px 0}
.lead{color:var(--brown)}
.card{margin-top:14px;padding:18px 20px;border-radius:22px;background:#FCFBF6;border:1.5px solid var(--line)}
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
    <li><span class="en">If you booked at a Gathering price, that price stays yours for as long as your membership continues.</span><span class="de">Hast du zum Gathering-Preis gebucht, bleibt dieser Preis für dich bestehen, solange deine Mitgliedschaft läuft.</span></li>
  </ul>

  <h2><?= $h($y['title']) ?></h2>
  <p><span class="price"><?= $eur($y['price_cents']) ?> <span class="en">in full</span><span class="de">auf einmal</span></span><?php if ($y['inst_count'] >= 2): ?><span class="price"><span class="en">or</span><span class="de">oder</span> <?= (int)$y['inst_count'] ?> × <?= $eur($y['inst_cents']) ?> <span class="en">monthly</span><span class="de">monatlich</span></span><?php endif; ?></p>
  <ul>
    <li><span class="en">Twelve months of mentoring with The Inner Circle included. There are only four places each year.</span><span class="de">Zwölf Monate Begleitung, The Inner Circle ist inklusive. Es gibt nur vier Plätze im Jahr.</span></li>
    <li><span class="en">After your booking, we have a personal call. If we both feel that the Year Circle is not the right fit for you right now, you get your full payment back.</span><span class="de">Nach deiner Buchung führen wir ein persönliches Gespräch. Wenn wir beide spüren, dass der Jahreskreis gerade nicht das Richtige für dich ist, bekommst du deine Zahlung vollständig zurück.</span></li>
    <li><span class="en">The Year Circle is a fixed twelve-month agreement. The monthly payments are a payment plan for this agreement, not a monthly subscription. They end automatically after the last payment.</span><span class="de">Der Jahreskreis ist eine feste Vereinbarung über zwölf Monate. Die Monatsraten sind eine Ratenzahlung dieser Vereinbarung und kein monatliches Abo. Sie enden automatisch nach der letzten Rate.</span></li>
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
  <h3><span class="en">Model withdrawal form</span><span class="de">Muster-Widerrufsformular</span></h3>
  <p class="small"><span class="en">(If you want to withdraw, you can fill in this form and send it to me.) To Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon, Austria, <?= $h(MM_FROM_EMAIL) ?>: I hereby withdraw from the contract I concluded for the following service: … / Ordered on: … / Name: … / Address: … / Date: … / Signature (only on paper).</span><span class="de">(Wenn du den Vertrag widerrufen willst, kannst du dieses Formular ausfüllen und an mich schicken.) An Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon, Österreich, <?= $h(MM_FROM_EMAIL) ?>: Hiermit trete ich von dem von mir abgeschlossenen Vertrag über die Erbringung der folgenden Dienstleistung zurück: … / Bestellt am: … / Name: … / Anschrift: … / Datum: … / Unterschrift (nur auf Papier).</span></p>

  <h2><span class="en">Questions</span><span class="de">Häufige Fragen</span></h2>
  <details><summary><span class="en">Do I need any experience?</span><span class="de">Brauche ich Vorerfahrung?</span></summary><p><span class="en">No. Everything is gentle and you can adapt every practice to how you feel that day.</span><span class="de">Nein. Alles ist sanft, und du kannst jede Übung an deine Tagesform anpassen.</span></p></details>
  <details><summary><span class="en">Do I have to turn my camera on?</span><span class="de">Muss ich meine Kamera einschalten?</span></summary><p><span class="en">No. Your camera can stay on, but it doesn't have to. Please keep your microphone muted unless you want to speak.</span><span class="de">Nein. Deine Kamera darf an bleiben, muss aber nicht. Bitte lass dein Mikrofon stumm, außer du möchtest etwas sagen.</span></p></details>
  <details><summary><span class="en">What if I miss a Gathering?</span><span class="de">Was ist, wenn ich ein Gathering verpasse?</span></summary><p><span class="en">Selected Gatherings are recorded. Only I am visible in the recordings, never the participants.</span><span class="de">Ausgewählte Gatherings werden aufgezeichnet. Auf den Aufnahmen bin nur ich zu sehen, nie die Teilnehmerinnen.</span></p></details>
  <details><summary><span class="en">How do I cancel The Inner Circle?</span><span class="de">Wie kündige ich The Inner Circle?</span></summary><p><span class="en">Write me a short email. Your membership ends at the end of the month you have already paid.</span><span class="de">Schreib mir eine kurze E-Mail. Deine Mitgliedschaft endet zum Ende des bereits bezahlten Monats.</span></p></details>
  <details><summary><span class="en">Can I pay the Year Circle in instalments?</span><span class="de">Kann ich den Jahreskreis in Raten zahlen?</span></summary><p><span class="en">Yes, in <?= (int)$y['inst_count'] ?> monthly payments. They are taken automatically and stop after the last one.</span><span class="de">Ja, in <?= (int)$y['inst_count'] ?> Monatsraten. Sie werden automatisch abgebucht und hören nach der letzten Rate auf.</span></p></details>
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

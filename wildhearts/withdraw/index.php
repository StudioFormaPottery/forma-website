<?php
// Rücktritts-Button (Pflicht seit 19.06.2026): Formular, Bestätigungsschritt, Eingangsbestätigung per E-Mail mit Datum und Uhrzeit.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/movecia-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
$pdo->exec('CREATE TABLE IF NOT EXISTS mm_withdrawals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL, email TEXT NOT NULL, contract TEXT NOT NULL, reference TEXT NOT NULL DEFAULT \'\',
    scope_note TEXT NOT NULL DEFAULT \'\', lang TEXT NOT NULL DEFAULT \'en\', ip_hash TEXT NOT NULL DEFAULT \'\', received_at TEXT NOT NULL
)');
$h = 'forma_h';
$lang = (($_POST['lang'] ?? $_GET['lang'] ?? '') === 'de') ? 'de' : (($_POST['lang'] ?? $_GET['lang'] ?? '') === 'en' ? 'en' : '');
$done = null; $err = '';
$contracts = ['circle' => 'The Inner Circle', 'year' => 'The Year Circle', 'retreat' => 'Wild Hearts Alpine Retreat', 'natal' => 'Prä- & Postnatal Training (Movecia)', 'other' => 'Other / Sonstiges'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $L = ($_POST['lang'] ?? 'en') === 'de' ? 'de' : 'en';
    $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 120));
    $email = strtolower(trim(mb_substr((string)($_POST['email'] ?? ''), 0, 160)));
    $contract = (string)($_POST['contract'] ?? '');
    $ref = trim(mb_substr((string)($_POST['reference'] ?? ''), 0, 200));
    $part = trim(mb_substr((string)($_POST['part'] ?? ''), 0, 600));
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
    $c = $pdo->prepare('SELECT COUNT(*) FROM mm_withdrawals WHERE ip_hash = ? AND received_at >= ?');
    $c->execute([$ip, date('c', time() - 3600)]);
    if (!empty($_POST['website'])) { $done = ['at' => date('c')]; }
    elseif ((int)$c->fetchColumn() >= 5) $err = $L === 'de' ? 'Zu viele Versuche. Bitte schreib an ' . MM_FROM_EMAIL . '.' : 'Too many attempts. Please write to ' . MM_FROM_EMAIL . '.';
    elseif (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset($contracts[$contract])) $err = $L === 'de' ? 'Bitte gib deinen Namen, deine E-Mail-Adresse und den Vertrag an.' : 'Please enter your name, your email address and the contract.';
    else {
        $at = date('c');
        $pdo->prepare('INSERT INTO mm_withdrawals (name, email, contract, reference, scope_note, lang, ip_hash, received_at) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$name, $email, $contracts[$contract], $ref, $part, $L, $ip, $at]);
        $when = date('d.m.Y, H:i', strtotime($at)) . ' (Vienna time)';
        if ($L === 'de') {
            $t = "Liebe(r) $name,\n\nhiermit bestätige ich den Eingang deines Rücktritts.\n\nVertrag: {$contracts[$contract]}\n" . ($ref !== '' ? "Angaben zur Buchung: $ref\n" : '') . ($part !== '' ? "Umfang (Teilrücktritt): $part\n" : "Umfang: der gesamte Vertrag\n") . "Eingegangen am: " . date('d.m.Y, H:i', strtotime($at)) . " Uhr (Wiener Zeit)\n\nIch melde mich persönlich bei dir. Eine Rückerstattung erfolgt binnen 14 Tagen über dasselbe Zahlungsmittel, das du verwendet hast.\n\nAlles Liebe\nViv · Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon";
            $subj = 'Eingangsbestätigung deines Rücktritts';
        } else {
            $t = "Dear $name,\n\nI hereby confirm that I have received your withdrawal.\n\nContract: {$contracts[$contract]}\n" . ($ref !== '' ? "Booking details: $ref\n" : '') . ($part !== '' ? "Scope (partial withdrawal): $part\n" : "Scope: the whole contract\n") . "Received on: $when\n\nI will get in touch with you personally. Any refund is made within 14 days using the same payment method you used.\n\nWith love\nViv · Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon, Austria";
            $subj = 'Confirmation of your withdrawal';
        }
        mm_mail($email, $subj, $t, mm_mail_html($subj, nl2br(forma_h($t)), $L));
        $v = "Hallo Viv,\n\n$name ($email) ist über das Rücktritts-Formular zurückgetreten.\n\nVertrag: {$contracts[$contract]}\nAngaben: " . ($ref ?: '–') . "\nTeilrücktritt: " . ($part ?: 'nein, ganzer Vertrag') . "\nEingegangen: " . date('d.m.Y, H:i', strtotime($at)) . " Uhr\n\nDie Eingangsbestätigung ist automatisch an sie gegangen. Bitte die Rückerstattung binnen 14 Tagen in Stripe veranlassen (bei vorzeitigem Beginn anteilig) und ein Abo/Ratenplan beenden.\n";
        mm_mail(MM_FROM_EMAIL, 'Rücktritt: ' . $name . ' · ' . $contracts[$contract], $v, mm_mail_html('Rücktritt eingegangen', nl2br(forma_h($v)), 'de'));
        $done = ['at' => $at, 'email' => $email];
    }
    $lang = $L;
}
?><!doctype html>
<html lang="<?= $lang ?: 'en' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Withdraw from the contract · Wild Hearts</title>
<link rel="icon" href="/img/wildhearts/viv.jpg">
<style>
@font-face{font-family:"Italiana";src:url(/fonts/Italiana-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:300 500;src:url(/fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:600 800;src:url(/fonts/Outfit-Bold.woff) format("woff")}
:root{--cream:#F2ECE4;--wine:#7D5E68;--wine-deep:#6B5658;--brown:#7D7076;--ink:#2E2529;--line:#DDD2C6}
*{box-sizing:border-box;margin:0}
body{background:var(--cream);font-family:"Outfit",system-ui,sans-serif;color:var(--ink);font-size:17px;line-height:1.6}
main{max-width:620px;margin:0 auto;padding:40px 16px 60px}
h1{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(34px,7vw,46px);line-height:1.1;color:var(--wine-deep);margin-bottom:10px}
p{color:var(--brown)}
form{display:flex;flex-direction:column;gap:12px;margin-top:20px;padding:22px;border-radius:24px;background:#FBF8F4;border:1.5px solid var(--line)}
label{display:flex;flex-direction:column;gap:5px;font-size:14px;color:var(--brown)}
input,select,textarea{font:inherit;font-size:16px;padding:12px 14px;border-radius:14px;border:1.5px solid var(--line);background:var(--cream);color:var(--ink)}
button{font:inherit;font-weight:500;font-size:17px;padding:14px 22px;border-radius:999px;border:0;background:var(--wine);color:var(--cream);cursor:pointer}
.ghost{background:transparent;color:var(--wine-deep);border:1.5px solid var(--line)}
.err{color:#8A3B2E}
.ok{margin-top:20px;padding:22px;border-radius:24px;background:#E7EBDD;color:var(--wine-deep)}
.hp{position:absolute;left:-9999px}
.lang{display:flex;gap:6px;justify-content:flex-end}
.lang a{font-size:14px;padding:5px 12px;border-radius:999px;border:1.5px solid var(--line);text-decoration:none;color:var(--wine-deep)}
.lang a.on{background:var(--wine);color:var(--cream);border-color:var(--wine)}
[lang="de"] .en,[lang="en"] .de{display:none}
a{color:var(--wine)}
</style>
</head>
<body>
<main>
  <div class="lang"><a href="?lang=en" data-l="en">EN</a><a href="?lang=de" data-l="de">DE</a></div>
  <h1><span class="en">Withdraw from the contract</span><span class="de">Vertrag widerrufen</span></h1>
  <?php if ($done): ?>
    <div class="ok">
      <p><strong><span class="en">Your withdrawal has been received.</span><span class="de">Dein Rücktritt ist eingegangen.</span></strong></p>
      <p><span class="en">Received on</span><span class="de">Eingegangen am</span> <?= $h(date('d.m.Y, H:i', strtotime($done['at']))) ?> <span class="en">(Vienna time).</span><span class="de">Uhr (Wiener Zeit).</span> <span class="en">A confirmation is on its way to your email address.</span><span class="de">Eine Bestätigung ist an deine E-Mail-Adresse unterwegs.</span></p>
    </div>
  <?php else: ?>
    <p><span class="en">Fill in this form to withdraw from your contract for The Inner Circle or The Year Circle. You will receive a confirmation by email with the date and time it was received. The details of your right of withdrawal are in the <a href="/terms#withdrawal">terms</a>.</span><span class="de">Fülle dieses Formular aus, um von deinem Vertrag für The Inner Circle oder den Jahreskreis zurückzutreten. Du bekommst eine Bestätigung per E-Mail mit Datum und Uhrzeit des Eingangs. Die Einzelheiten zu deinem Rücktrittsrecht findest du in den <a href="/terms?lang=de#withdrawal">Bedingungen</a>.</span></p>
    <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
    <form method="post" id="f">
      <input type="hidden" name="lang" id="fl" value="<?= $h($lang ?: 'en') ?>">
      <label><span><span class="en">Your name</span><span class="de">Dein Name</span></span><input name="name" required maxlength="120" autocomplete="name" value="<?= $h((string)($_POST['name'] ?? '')) ?>"></label>
      <label><span><span class="en">Your email address (the confirmation is sent here)</span><span class="de">Deine E-Mail-Adresse (dorthin geht die Bestätigung)</span></span><input name="email" type="email" required maxlength="160" autocomplete="email" value="<?= $h((string)($_POST['email'] ?? '')) ?>"></label>
      <label><span><span class="en">Which contract?</span><span class="de">Welcher Vertrag?</span></span><select name="contract" required><?php foreach ($contracts as $k => $t): ?><option value="<?= $h($k) ?>"<?= ($_POST['contract'] ?? ($_GET['v'] ?? '')) === $k ? ' selected' : '' ?>><?= $h($t) ?></option><?php endforeach; ?></select></label>
      <label><span><span class="en">Booking details, if you have them (date, email of the booking or invoice number)</span><span class="de">Angaben zur Buchung, falls vorhanden (Datum, E-Mail der Buchung oder Rechnungsnummer)</span></span><input name="reference" maxlength="200" value="<?= $h((string)($_POST['reference'] ?? '')) ?>"></label>
      <label><span><span class="en">Only part of the contract? Tell me which part (leave empty for the whole contract)</span><span class="de">Nur einen Teil des Vertrags? Schreib, welchen (leer lassen für den ganzen Vertrag)</span></span><textarea name="part" rows="2" maxlength="600"><?= $h((string)($_POST['part'] ?? '')) ?></textarea></label>
      <div class="hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
      <button type="button" id="b1"><span class="en">Withdraw from the contract</span><span class="de">Vertrag widerrufen</span></button>
      <div id="step2" hidden>
        <p style="margin-bottom:10px"><span class="en">Please check your details. With the next click your withdrawal is sent.</span><span class="de">Bitte prüfe deine Angaben. Mit dem nächsten Klick wird dein Rücktritt abgeschickt.</span></p>
        <button type="submit"><span class="en">Confirm withdrawal</span><span class="de">Widerruf bestätigen</span></button>
        <button type="button" class="ghost" id="b0" style="margin-left:6px"><span class="en">Back</span><span class="de">Zurück</span></button>
      </div>
    </form>
  <?php endif; ?>
  <p style="margin-top:26px;font-size:14px">Movecia – Personal Fitness · Vivien Malonga · Silvrettastraße 58, 6562 Mathon, Austria · <a href="mailto:<?= $h(MM_FROM_EMAIL) ?>"><?= $h(MM_FROM_EMAIL) ?></a></p>
</main>
<script>
(() => {
  let l = <?= json_encode($lang) ?>;
  if (!l) { try { l = localStorage.getItem('wh-lang'); } catch (e) {} }
  if (!l) l = (navigator.language || 'en').slice(0, 2) === 'de' ? 'de' : 'en';
  document.documentElement.lang = l;
  document.querySelectorAll('.lang a').forEach(a => a.classList.toggle('on', a.dataset.l === l));
  const fl = document.getElementById('fl'); if (fl) fl.value = l;
  const f = document.getElementById('f'); if (!f) return;
  document.getElementById('b1').onclick = () => { if (!f.reportValidity()) return; document.getElementById('step2').hidden = false; document.getElementById('b1').hidden = true; };
  document.getElementById('b0').onclick = () => { document.getElementById('step2').hidden = true; document.getElementById('b1').hidden = false; };
})();
</script>
</body>
</html>

<?php
// Rücktrittsfunktion nach § 13a FAGG (in AT für Verträge ab 1.10.2026): Schaltfläche „Vertrag widerrufen“ → Formular → „Widerruf bestätigen“
// → Eingangsbestätigung per E-Mail mit Inhalt, Datum und Uhrzeit. Stand 23.10.2026: Prä- & Postnatal zuerst, Inner Circle/Jahreskreis nur noch als frühere Buchung.
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
$contracts = ['natal' => 'Prä- & Postnatal Training (Studio)', 'natal_online' => 'Prä- & Postnatal Training (Online)', 'retreat' => 'Wild Hearts Alpine Retreat', 'circle' => 'The Inner Circle (frühere Buchung)', 'year' => 'The Year Circle / Jahreskreis (frühere Buchung)', 'other' => 'Sonstiges / Other'];
$sel = (string)($_POST['contract'] ?? ($_GET['v'] ?? ''));
if ($lang === '' && in_array($sel, ['natal', 'natal_online'], true)) $lang = 'de';

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
            $t = "Hallo " . forma_first_name($name) . ",\n\nhiermit bestätige ich den Eingang deines Rücktritts. Das hast du mir mitgeteilt:\n\nName: $name\nE-Mail: $email\nVertrag: {$contracts[$contract]}\n" . ($ref !== '' ? "Angaben zur Buchung: $ref\n" : '') . ($part !== '' ? "Umfang (Teilrücktritt): $part\n" : "Umfang: der gesamte Vertrag\n") . "Eingegangen am: " . date('d.m.Y, H:i', strtotime($at)) . " Uhr (Wiener Zeit)\n\nIch melde mich persönlich bei dir. Die Rückerstattung bekommst du spätestens binnen 14 Tagen über dasselbe Zahlungsmittel, das du verwendet hast. Hast du dir gewünscht, dass die Einheiten schon in der Rücktrittsfrist stattfinden, ziehe ich nur die Einheiten ab, die bereits stattgefunden haben.\n\nBis bald\nVivien · Movecia – Personal Fitness, Vivien Malonga, Silvrettastraße 58, 6562 Mathon";
            $subj = 'Eingangsbestätigung deines Rücktritts';
        } else {
            $t = "Dear " . forma_first_name($name) . ",\n\nI hereby confirm that I have received your withdrawal. This is what you told me:\n\nName: $name\nEmail: $email\nContract: {$contracts[$contract]}\n" . ($ref !== '' ? "Booking details: $ref\n" : '') . ($part !== '' ? "Scope (partial withdrawal): $part\n" : "Scope: the whole contract\n") . "Received on: $when\n\nI will get in touch with you personally. You receive your refund no later than 14 days from now, using the same payment method you used. If you asked for sessions to take place during the withdrawal period, only the sessions that have already taken place are deducted.\n\nSee you soon\nVivien · Movecia, Vivien Malonga, Silvrettastraße 58, 6562 Mathon, Austria";
            $subj = 'Confirmation of your withdrawal';
        }
        mm_mail($email, $subj, $t, mm_mail_html($subj, nl2br(forma_h($t)), $L), [], 'Vivien · Movecia');
        $v = "Hallo Viv,\n\n$name ($email) ist über das Rücktritts-Formular zurückgetreten.\n\nVertrag: {$contracts[$contract]}\nAngaben: " . ($ref ?: '–') . "\nTeilrücktritt: " . ($part ?: 'nein, ganzer Vertrag') . "\nEingegangen: " . date('d.m.Y, H:i', strtotime($at)) . " Uhr\n\nDie Eingangsbestätigung ist automatisch an sie gegangen. Bitte die Rückerstattung binnen 14 Tagen in Stripe veranlassen (bei vorzeitigem Beginn nur die stattgefundenen Einheiten abziehen) und beim Prä- & Postnatal Training offene Termine in der Verwaltung absagen bzw. ein Abo/Ratenplan beenden.\n";
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
<title>Withdraw from the contract · Movecia</title>
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
    <p><span class="en">Fill in this form to withdraw from a contract with Movecia, for example your prenatal and postnatal training. After you confirm, you immediately receive a confirmation by email with your details and the date and time it was received. All details are in the <a href="/terms?lang=en#withdrawal">right of withdrawal</a>.</span><span class="de">Mit diesem Formular trittst du von einem Vertrag mit Movecia zurück, zum Beispiel von deinem Prä- &amp; Postnatal Training. Nach dem Bestätigen bekommst du sofort eine Bestätigung per E-Mail mit deinen Angaben sowie Datum und Uhrzeit des Eingangs. Alle Einzelheiten findest du im <a href="/terms?lang=de#withdrawal">Rücktrittsrecht</a>.</span></p>
    <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
    <form method="post" id="f">
      <input type="hidden" name="lang" id="fl" value="<?= $h($lang ?: 'en') ?>">
      <label><span><span class="en">Your name</span><span class="de">Dein Name</span></span><input name="name" required maxlength="120" autocomplete="name" value="<?= $h((string)($_POST['name'] ?? '')) ?>"></label>
      <label><span><span class="en">Your email address (the confirmation is sent here)</span><span class="de">Deine E-Mail-Adresse (dorthin geht die Bestätigung)</span></span><input name="email" type="email" required maxlength="160" autocomplete="email" value="<?= $h((string)($_POST['email'] ?? '')) ?>"></label>
      <label><span><span class="en">Which contract?</span><span class="de">Welcher Vertrag?</span></span><select name="contract" required><?php foreach ($contracts as $k => $t): ?><option value="<?= $h($k) ?>"<?= $sel === $k ? ' selected' : '' ?>><?= $h($t) ?></option><?php endforeach; ?></select></label>
      <label><span><span class="en">Which booking? (invoice number or booking date, if you have it)</span><span class="de">Welche Buchung? (Rechnungsnummer oder Buchungsdatum, falls zur Hand)</span></span><input name="reference" maxlength="200" value="<?= $h((string)($_POST['reference'] ?? '')) ?>"></label>
      <label><span><span class="en">Only part of the contract? Tell me which part, for example only the postnatal sessions (leave empty for the whole contract)</span><span class="de">Nur einen Teil des Vertrags? Schreib, welchen, zum Beispiel nur die Postnatal-Einheiten (leer lassen für den ganzen Vertrag)</span></span><textarea name="part" rows="2" maxlength="600"><?= $h((string)($_POST['part'] ?? '')) ?></textarea></label>
      <div class="hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
      <button type="button" id="b1"><span class="en">Withdraw from the contract</span><span class="de">Vertrag widerrufen</span></button>
      <div id="step2" hidden>
        <p style="margin-bottom:10px"><span class="en">Please check your details. With the next click your withdrawal is sent.</span><span class="de">Bitte prüfe deine Angaben. Mit dem nächsten Klick wird dein Rücktritt abgeschickt.</span></p>
        <button type="submit"><span class="en">Confirm withdrawal</span><span class="de">Widerruf bestätigen</span></button>
        <button type="button" class="ghost" id="b0" style="margin-left:6px"><span class="en">Back</span><span class="de">Zurück</span></button>
      </div>
    </form>
  <?php endif; ?>
  <p style="margin-top:26px;font-size:14px">Movecia – Personal Fitness · Vivien Malonga · <a href="mailto:<?= $h(MM_FROM_EMAIL) ?>"><?= $h(MM_FROM_EMAIL) ?></a> · <a href="/terms<?= $lang === 'de' ? '?lang=de' : '' ?>#withdrawal"><span class="en">Right of withdrawal</span><span class="de">Rücktrittsrecht</span></a></p>
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

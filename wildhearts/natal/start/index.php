<?php
// moveciamovement.com/natal/start/?o=ID&k=TOKEN – Start bestätigen (Vivien, 31.10.2026)
// Aus der Buchungsmail: Pflicht-Häkchen (Beginn in der Rücktrittsfrist, Abzug genutzter Einheiten) und freiwilliges
// Häkchen „Mitgliederbereich sofort komplett“ (Verzicht auf das Rücktrittsrecht für die digitalen Inhalte). Danach weiter in den Bereich.
declare(strict_types=1);
require dirname(__DIR__, 3) . '/api/natal-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$pdo = forma_db();
natal_schema($pdo);
$h = 'forma_h';
$o = natal_order_auth($pdo, (int)($_GET['o'] ?? $_POST['o'] ?? 0), (string)($_GET['k'] ?? $_POST['k'] ?? ''));
$err = '';
$saved = false;
if ($o && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $o['status'] === 'bezahlt') {
    if (empty($_POST['start']) && !$o['confirm_at']) $err = 'Bitte setz das erste Häkchen, damit wir starten können.';
    else { $o = natal_start_confirm($pdo, $o, !empty($_POST['digital'])); $saved = true; }
}
$member = $o && natal_order_is_member($o);
$next = !$o ? '' : ($member ? (natal_member_by_email($pdo, (string)$o['email']) ? natal_member_link() : natal_member_start_link($o)) : natal_link($o));
$first = $o ? forma_first_name((string)$o['name']) : '';
$confirmed = $o && $o['confirm_at'];
$digital = $o && (int)$o['consent_digital'] === 1;
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Start bestätigen · Movecia</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#F5F1EB">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--bg:#F5F1EB;--card:#FBF9F6;--ink:#2A2026;--muted:#6E6168;--line:#D3C9BC;--plum:#4A3040;--deep:#2E1D27;--blush:#E4DCD1;--peach:#E6DBCF;--on:#F7F1EE;--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased}
@media(min-width:900px){body{font-size:19px}}
main{max-width:760px;margin:0 auto;padding:18px 16px 60px}
.top{display:flex;align-items:center;gap:.4rem;font-family:var(--serif);font-size:1.7rem;color:var(--ink);text-decoration:none}
.top img{width:30px;height:30px}
h1{font-family:var(--serif);font-weight:500;font-size:clamp(2.4rem,7vw,3.6rem);line-height:1.05;margin:1.4rem 0 .6rem}
h1 em{font-style:italic;color:var(--plum)}
.k{font-size:.72rem;letter-spacing:.2em;text-transform:uppercase;color:var(--plum);font-weight:500;margin:0}
.mut{color:var(--muted)}
.card{background:var(--card);border-radius:24px;padding:clamp(1.2rem,4vw,1.8rem);margin-top:1.2rem;position:relative;overflow:hidden}
.sum{background:var(--blush);border-radius:16px;padding:.8rem 1rem;margin:1rem 0 0;font-size:.95rem}
.chk{display:flex;gap:.8rem;align-items:flex-start;margin-top:1.1rem;cursor:pointer;font-size:.97rem}
.chk input{width:22px;height:22px;flex:0 0 22px;margin-top:.15rem;accent-color:var(--plum)}
.chk.opt{background:var(--bg);border-radius:16px;padding:.9rem 1rem}
.chk b{display:block;font-weight:600;color:var(--plum)}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:.85em 1.5em;border-radius:999px;background:var(--plum);color:var(--on);font:inherit;font-weight:500;text-decoration:none;border:1px solid var(--plum);cursor:pointer;margin-top:1.4rem}
.err{color:#A0463A;margin-top:1rem}
.ok{background:var(--deep);color:var(--on)}
.ok .k{color:var(--peach)}.ok p{color:#E8DCDF}
.ok ul{padding-left:1.2rem;margin:.8rem 0 0;color:#E8DCDF}.ok li{margin:.3rem 0}
.hand{font-family:var(--serif);font-style:italic;font-size:1.5rem;margin-top:1rem}
a{color:var(--plum)}
</style>
</head>
<body>
<main>
  <a class="top" href="/natal/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia</span></a>
<?php if (!$o): ?>
  <h1>Dieser Link <em>funktioniert nicht</em></h1>
  <p class="mut">Bitte nimm den Knopf aus deiner Buchungsmail oder schreib mir an <a href="mailto:<?= $h(NATAL_COMPANY['email']) ?>"><?= $h(NATAL_COMPANY['email']) ?></a>.</p>
<?php elseif ($o['status'] !== 'bezahlt'): ?>
  <h1>Hallo <em><?= $h($first) ?></em></h1>
  <p class="mut"><?= $o['status'] === 'widerrufen' ? 'Du bist von dieser Buchung zurückgetreten, hier gibt es nichts mehr zu bestätigen.' : 'Deine Zahlung ist noch nicht angekommen. Sobald sie da ist, bekommst du eine Mail.' ?></p>
<?php elseif ($saved || ($confirmed && ($digital || !$member))): ?>
  <p class="k" style="margin-top:1.6rem">Alles bestätigt</p>
  <h1>Danke, <em><?= $h($first) ?></em></h1>
  <div class="card ok">
    <p class="k">Das hast du bestätigt<?= $o['confirm_at'] ? ' am ' . $h(date('d.m.Y, H:i', strtotime((string)$o['confirm_at']))) . ' Uhr' : '' ?></p>
    <ul>
      <li>Wir dürfen schon in deiner 14-tägigen Rücktrittsfrist starten.</li>
      <li>Trittst du zurück, werden nur die Einheiten abgezogen, die schon stattgefunden haben.</li>
      <?php if ($digital): ?><li>Dein Mitgliederbereich ist ab sofort ganz für dich offen.</li><?php elseif ($member): ?><li>Dein Mitgliederbereich öffnet sich ganz nach deinen ersten 14 Tagen, bis dahin kannst du schon in dein erstes Workbook hineinblättern.</li><?php endif; ?>
    </ul>
    <p class="hand">Ich freue mich auf dich. Vivien</p>
    <a class="btn" style="background:var(--peach);color:var(--deep);border-color:var(--peach)" href="<?= $h($next) ?>"><?= $member ? 'Zu meinem Mitgliederbereich' : 'Meine Termine ansehen' ?></a>
  </div>
  <p class="mut" style="font-size:.85rem;margin-top:1rem">Eine Bestätigung ist auch per E-Mail an dich unterwegs.</p>
<?php else: ?>
  <p class="k" style="margin-top:1.6rem"><?= $h($o['title']) ?></p>
  <h1>Schön, dass wir uns bald sehen, <em><?= $h($first) ?></em></h1>
  <p class="mut">Bevor es mit unserer ersten Einheit losgeht, brauche ich noch kurz dein Okay.</p>
  <form method="post" class="card">
    <input type="hidden" name="o" value="<?= (int)$o['id'] ?>"><input type="hidden" name="k" value="<?= $h((string)$o['token']) ?>">
    <p class="sum"><?= $h((int)$o['units'] === 1 ? '1 Einheit' : $o['units'] . ' Einheiten') ?> à 60 Minuten, 1:1 · gebucht am <?= $h(date('d.m.Y', strtotime((string)($o['paid_at'] ?: $o['created_at'])))) ?> · Rücktrittsfrist bis <?= $h(date('d.m.Y', natal_wd_until($o))) ?></p>
    <?php if (!$confirmed): ?>
    <label class="chk"><input type="checkbox" name="start" value="1" required><span>Ich möchte ausdrücklich, dass unsere Einheiten schon innerhalb der 14-tägigen Rücktrittsfrist stattfinden. Trete ich zurück, werden die Einheiten, die bereits stattgefunden haben, zum Paketpreis pro Einheit abgezogen, den Rest bekomme ich zurück. Mir ist bewusst, dass mein Rücktrittsrecht erlischt, sobald alle gebuchten Einheiten stattgefunden haben.</span></label>
    <?php else: ?>
    <p class="mut" style="margin-top:1rem;font-size:.95rem">✓ Den Start hast du schon am <?= $h(date('d.m.Y', strtotime((string)$o['confirm_at']))) ?> bestätigt.</p>
    <?php endif; ?>
    <?php if ($member): ?>
    <label class="chk opt"><input type="checkbox" name="digital" value="1"><span><b>Freiwillig: Mitgliederbereich sofort ganz öffnen</b>Ich möchte alle Workbooks, Videos und Aufnahmen gleich vollständig nutzen und nicht erst nach 14 Tagen. Mir ist bewusst, dass ich damit mein Rücktrittsrecht für diese digitalen Inhalte verliere. Für die Einheiten selbst gilt mein Rücktrittsrecht weiter. Ohne Häkchen öffnet sich alles automatisch nach 14 Tagen.</span></label>
    <?php endif; ?>
    <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
    <button class="btn" type="submit"><?= $confirmed ? 'Speichern' : 'Start bestätigen' ?></button>
    <p class="mut" style="font-size:.84rem;margin-top:1rem">Alle Details stehen in den <a href="/terms?lang=de#withdrawal" target="_blank" rel="noopener">Bedingungen &amp; im Rücktrittsrecht</a>.</p>
  </form>
<?php endif; ?>
</main>
</body>
</html>

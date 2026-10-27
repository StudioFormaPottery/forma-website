<?php
// Gast-Seite, wenn ein Kurs die Mindestteilnehmerzahl nicht erreicht: anderen Termin wählen oder stornieren.
// Aktionen nur per Knopfdruck (POST), damit Mail-Programme beim Vorschau-Laden nichts auslösen.
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('X-Robots-Tag: noindex, nofollow');

$pdo = forma_db();
forma_min_schema($pdo);
$id = (int)($_REQUEST['b'] ?? 0);
$token = (string)($_REQUEST['k'] ?? '');
$st = $pdo->prepare('SELECT b.*, c.title, c.date_text, c.start_date, c.min_participants FROM bookings b JOIN courses c ON c.id = b.course_id WHERE b.id = ?');
$st->execute([$id]);
$b = $st->fetch();
if (!$b || $token === '' || !hash_equals((string)$b['pay_token'], $token)) {
    forma_message_page('Link nicht gültig', 'Dieser Link ist leider nicht gültig. Ruf mich gern an: ' . FORMA_PHONE);
}
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$msg = '';
$done = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $b['status'] === 'bestaetigt' && $b['min_state'] === 'gefragt') {
    if (($_POST['do'] ?? '') === 'move') {
        $err = forma_booking_move($pdo, $id, (int)($_POST['course'] ?? 0));
        if ($err === '') {
            $nc = $pdo->prepare('SELECT date_text FROM courses WHERE id = ?');
            $nc->execute([(int)$_POST['course']]);
            $done = 'Geschafft! Dein Platz ist umgebucht auf ' . $nc->fetchColumn() . '. Die Bestätigung mit Kalender-Eintrag ist schon in deinem Postfach. Ich freue mich auf dich!';
        } else {
            $msg = $err;
        }
    } elseif (($_POST['do'] ?? '') === 'storno' && !empty($_POST['sure'])) {
        $r = forma_booking_refund_cancel($pdo, $id);
        $done = 'Dein Platz ist storniert.' . ($r['cash'] > 0 ? ' ' . forma_euro($r['cash']) . ($r['refunded'] ? ' gehen zurück auf deine Karte.' : ' überweise ich dir in den nächsten Tagen zurück.') : '')
            . ($r['gift'] ? ' Und als Dankeschön bekommst du einen Gutschein über ' . forma_euro($r['gift']['cents']) . ', der Code ist schon in deinem Postfach.' : '') . ' Ich hoffe, wir sehen uns bald im Atelier!';
    } elseif (($_POST['do'] ?? '') === 'storno') {
        $msg = 'Bitte setz noch das Häkchen, wenn du wirklich stornieren möchtest.';
    }
    $st->execute([$id]);
    $b = $st->fetch();
}
$alts = $b['status'] === 'bestaetigt' && $b['min_state'] === 'gefragt' ? forma_min_alternatives($pdo, $b, (int)$b['persons']) : [];
$days = [];
foreach ($alts as $c) {
    $ymd = (string)$c['start_date'];
    if (isset($days[$ymd])) continue;
    $time = preg_match('/(\d{1,2}(?::\d{2})?–\d{1,2}(?::\d{2})?)/u', (string)$c['date_text'], $m) ? $m[1] : '';
    $days[$ymd] = '<form method="post" style="margin:0"><input type="hidden" name="b" value="' . $id . '"><input type="hidden" name="k" value="' . $h($token) . '"><input type="hidden" name="do" value="move"><input type="hidden" name="course" value="' . (int)$c['id'] . '">'
        . '<button class="cal-on" type="submit" title="' . $h($c['date_text']) . ' · frei" onclick="return confirm(\'Auf ' . $h(addslashes((string)$c['date_text'])) . ' umbuchen?\')">' . (int)substr($ymd, 8) . ($time ? '<small>' . $h($time) . '</small>' : '') . '</button></form>';
}
$paid = (int)$b['amount_cents'] > 0 || (int)($b['voucher_cents'] ?? 0) > 0;
?><!doctype html>
<html lang="de"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Neuer Termin · FORMA</title><link rel="icon" type="image/png" href="../img/forma-logo.png">
<link rel="stylesheet" href="../assets/gast.css?v=7">
</head><body>
<div class="head"><div class="wrap">
 <div class="top"><img src="../img/forma-logo.png" alt="FORMA Earth &amp; Art"><a href="../">forma-pottery.com</a></div>
 <div class="hero" style="grid-template-columns:1fr"><div>
  <p class="kick"><?= $h($b['title']) ?> · <?= $h($b['date_text']) ?></p>
  <h1><?= $done ? 'Alles erledigt' : 'Was ist dir lieber?' ?></h1>
  <?php if (!$done): ?><p class="lead">Hallo <?= $h(forma_first_name((string)$b['name'])) ?>, dieses Mal sind leider nicht genug Anmeldungen zusammengekommen, damit der Kurs stattfinden kann. Das tut mir leid! Such dir einfach einen neuen Termin aus oder storniere ganz unkompliziert.</p><?php endif; ?>
 </div></div>
</div></div>
<main><div class="wrap">
<?php if ($msg): ?><p class="err" style="margin-bottom:18px"><?= $h($msg) ?></p><?php endif; ?>
<?php if ($done): ?>
 <section class="card"><svg class="wv" aria-hidden="true"><path></path></svg><h2>Danke!</h2><p><?= $h($done) ?></p><p><a class="btn" href="../">Zur Website</a></p></section>
<?php elseif ($b['status'] !== 'bestaetigt' || $b['min_state'] !== 'gefragt'): ?>
 <section class="card"><svg class="wv" aria-hidden="true"><path></path></svg><h2>Schon erledigt</h2><p>Für diese Buchung ist schon alles geklärt<?= $b['min_state'] === 'verschoben' ? ': Dein Platz ist umgebucht auf ' . $h($b['date_text']) . '.' : ($b['status'] === 'storniert' ? ': Sie ist storniert.' : '.') ?> Bei Fragen ruf mich gern an: <?= $h(FORMA_PHONE) ?></p></section>
<?php else: ?>
 <section class="card"><svg class="wv" aria-hidden="true"><path></path></svg>
  <p class="ok">Kostenlos umbuchen</p><h2>Neuen Termin wählen</h2>
  <?php if ($days): ?>
   <p>Tippe auf einen dunklen Tag, dann ist dein Platz sofort umgebucht. Bezahlt ist schon alles.</p>
   <?= forma_cal_html($days) ?>
   <p class="cal-legend"><span><i></i>freier Termin</span></p>
  <?php else: ?>
   <p>Gerade sind online keine weiteren Termine frei. Ruf mich an oder schreib mir per WhatsApp, dann finden wir gemeinsam einen: <?= $h(FORMA_PHONE) ?></p>
  <?php endif; ?>
 </section>
 <section class="card" style="margin-top:22px"><svg class="wv" aria-hidden="true"><path></path></svg>
  <p class="ok">Oder</p><h2>Stornieren</h2>
  <p><?= $paid ? 'Du bekommst dein Geld zurück' . ((int)($b['voucher_cents'] ?? 0) > 0 ? ' (ein eingelöster Gutschein wird wieder gutgeschrieben)' : '') . ', und als kleines Dankeschön für deine Geduld schenke ich dir einen Gutschein über ' . FORMA_MIN_GIFT_PERCENT . ' % für deine nächste Buchung.' : 'Es entstehen dir keine Kosten.' ?></p>
  <form method="post"><input type="hidden" name="b" value="<?= $id ?>"><input type="hidden" name="k" value="<?= $h($token) ?>"><input type="hidden" name="do" value="storno">
   <label class="consent"><input type="checkbox" name="sure" value="1"><span>Ja, ich möchte meinen Platz stornieren.</span></label>
   <button class="btn" type="submit" style="margin-top:12px">Stornieren</button>
  </form>
 </section>
<?php endif; ?>
 <p class="foot">FORMA Earth &amp; Art · Silvrettastraße 58 · 6562 Mathon · <a href="tel:+4367763907080">+43 677 63 90 70 80</a></p>
</div></main>
<script src="../assets/gast.js?v=3"></script>
</body></html>

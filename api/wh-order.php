<?php
// Rückkehr von Stripe nach der Buchung eines Wild-Hearts-Angebots.
declare(strict_types=1);
require __DIR__ . '/movecia-lib.php';
$pdo = forma_db();
mm_offer_schema($pdo);
$s = $pdo->prepare('SELECT * FROM mm_orders WHERE id = ?');
$s->execute([(int)($_GET['o'] ?? 0)]);
$o = $s->fetch();
if (!$o || !hash_equals((string)$o['token'], (string)($_GET['k'] ?? ''))) mm_page('hmm', 'Not found', 'This link does not seem to work. Please write to ' . MM_FROM_EMAIL . '.');
if (!empty($_GET['cancel']) && $o['status'] === 'offen') {
    mm_page('no worries ♡', 'Nothing was booked', 'You can simply close this tab and go back to the Gathering. If you have a question, write to ' . MM_FROM_EMAIL . '.');
}
try { $st = mm_order_check($pdo, $o); } catch (Throwable $e) { error_log('MM order check: ' . $e->getMessage()); $st = 'offen'; }
if ($st === 'bezahlt') mm_page('you are in ♡', $o['title'], 'Thank you so much! Your booking is confirmed and an email is on its way to you. You can close this tab and go back to the Gathering.');
mm_page('one moment ♡', 'Almost there', 'Your payment is still being confirmed. Please reload this page in a minute. If anything seems wrong, write to ' . MM_FROM_EMAIL . '.');

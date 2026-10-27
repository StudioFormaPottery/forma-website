<?php
// Wild Hearts: Abmelden mit einem Klick (Link in jeder Mail).
declare(strict_types=1);
require __DIR__ . '/movecia-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
$sub = mm_sub_by_token($pdo, (string)($_GET['t'] ?? ''));
if (!$sub) mm_page('hmm…', 'Link not found', 'This link no longer works. If you still get emails, just reply to one of them and I\'ll take you off the list.', '/', 'moveciamovement.com');
if ($sub['status'] !== 'abgemeldet') {
    $pdo->prepare("UPDATE mm_subscribers SET status = 'abgemeldet' WHERE id = ?")->execute([$sub['id']]);
    mm_ml_push($pdo, $sub, false);
}
mm_page('take care ♡', "You're unsubscribed", 'You won\'t get any more emails from the Wild Hearts Collective. The door stays open, you can come back anytime.', '/', 'moveciamovement.com');

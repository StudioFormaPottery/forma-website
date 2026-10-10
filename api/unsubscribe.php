<?php
// Abmeldung von der E-Mail-Liste.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$token = (string)($_GET['t'] ?? '');
$pdo = forma_db();
$stmt = $pdo->prepare('SELECT * FROM subscribers WHERE token = ?');
$stmt->execute([$token]);
$s = $token !== '' ? $stmt->fetch() : false;
if ($s) {
    $pdo->prepare("UPDATE subscribers SET status = 'abgemeldet' WHERE id = ?")->execute([$s['id']]);
    forma_ml_sync($s['email'], '', false);
}
forma_message_page('Du bist abgemeldet', 'Schade, dass du gehst! Du bekommst ab jetzt keine E-Mails mehr von meiner Liste.');

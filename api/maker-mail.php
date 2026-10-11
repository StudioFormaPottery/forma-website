<?php
// Direkter Download aus dem Maker-Club-Newsletter: ?e={$email} aus MailerLite.
// Bestätigte Mitglieder kommen ohne erneutes Eintragen direkt zu ihrem Template.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$email = mb_strtolower(trim((string)($_GET['e'] ?? '')));
$pdo = forma_db();
$s = false;
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = $pdo->prepare("SELECT * FROM subscribers WHERE lower(email) = ? AND status = 'bestaetigt' AND source LIKE '%Maker%'");
    $stmt->execute([$email]);
    $s = $stmt->fetch();
}
if ($s) {
    header('Location: ' . forma_maker_link($s), true, 302);
    exit;
}
forma_message_page('Dein Gratis-Template', 'Ich konnte deine E-Mail-Adresse gerade nicht im Maker Club finden. Trag dich auf der Website kurz ein, dann bekommst du den Download-Link sofort per E-Mail.', 'Zum Maker Club', '/#templates');

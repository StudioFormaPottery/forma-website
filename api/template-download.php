<?php
// Download eines gekauften Templates.
declare(strict_types=1);
require __DIR__ . '/lib.php';
$id = (int)($_GET['o'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$pdo = forma_db();
$stmt = $pdo->prepare('SELECT o.*, t.file FROM template_orders o JOIN templates t ON t.id = o.template_id WHERE o.id = ?');
$stmt->execute([$id]);
$o = $stmt->fetch();
if (!$o || $token === '' || !hash_equals((string)$o['token'], $token)) {
    forma_message_page('Link nicht gültig', 'Dieser Download-Link ist leider nicht gültig. Schreib mir gern an hello@forma-pottery.com.');
}
if ($o['status'] === 'offen') {
    try { forma_template_check($pdo, $o); $stmt->execute([$id]); $o = $stmt->fetch(); } catch (Throwable $e) {}
}
$file = forma_template_dir() . '/' . basename((string)$o['file']);
if ($o['status'] !== 'bezahlt' || $o['file'] === '' || !is_file($file)) {
    forma_message_page('Noch nicht bereit', 'Dein Download ist noch nicht freigeschaltet. Bitte versuch es in ein paar Minuten noch einmal oder schreib mir an hello@forma-pottery.com.');
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="FORMA-Template-' . preg_replace('/[^A-Za-z0-9-]+/', '-', (string)$o['title']) . '.pdf"');
header('Content-Length: ' . filesize($file));
readfile($file);

<?php
// Gratis-Template des Monats für bestätigte Maker-Club-Mitglieder.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$token = (string)($_GET['t'] ?? '');
$pdo = forma_db();
$stmt = $pdo->prepare("SELECT * FROM subscribers WHERE token = ? AND status = 'bestaetigt'");
$stmt->execute([$token]);
$s = $token !== '' ? $stmt->fetch() : false;
$m = forma_maker($pdo);
$file = forma_template_dir() . '/' . basename((string)$m['file']);
if (!$s) {
    forma_message_page('Link nicht gültig', 'Dieser Download-Link ist nicht (mehr) gültig. Trag dich auf der Website einfach in den Maker Club ein, dann bekommst du einen neuen Link.');
}
if ($m['file'] === '' || !is_file($file)) {
    forma_message_page('Bald ist es so weit', 'Das nächste Template des Monats ist gerade in Arbeit. Ich schicke es dir per E-Mail, sobald es fertig ist.');
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="FORMA-Maker-Club-' . preg_replace('/[^A-Za-z0-9-]+/', '-', (string)$m['title']) . '.pdf"');
header('Content-Length: ' . filesize($file));
readfile($file);

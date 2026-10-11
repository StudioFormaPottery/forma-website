<?php
// Gratis-Template des Monats für bestätigte Maker-Club-Mitglieder.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$token = (string)($_GET['t'] ?? '');
$pdo = forma_db();
$stmt = $pdo->prepare("SELECT * FROM subscribers WHERE token = ? AND status IN ('bestaetigt','offen')");
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
$lang = (string)($_GET['lang'] ?? '');
if ((string)$m['file_en'] !== '' && $lang === '') {
    $base = '/api/maker-download.php?t=' . rawurlencode($token);
    forma_message_page('Dein Gratis-Template', "„{$m['title']}“ gibt es auf Deutsch und auf Englisch. Lade dir die Version herunter, die du brauchst, oder gleich beide. Viel Freude beim Töpfern!\n\nYour free template is available in German and English.", 'Deutsch herunterladen', $base . '&lang=de', 'English download', $base . '&lang=en');
}
$useEn = $lang === 'en' && (string)$m['file_en'] !== '';
if ($useEn) {
    $file = forma_template_dir() . '/' . basename((string)$m['file_en']);
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="FORMA-Maker-Club-' . preg_replace('/[^A-Za-z0-9-]+/', '-', strtr((string)$m['title'], ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss'])) . ($useEn ? '-EN' : '') . '.pdf"');
header('Content-Length: ' . filesize($file));
readfile($file);

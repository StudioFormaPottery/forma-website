<?php
// Download eines gekauften Templates.
declare(strict_types=1);
require __DIR__ . '/lib.php';
$id = (int)($_GET['o'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$pdo = forma_db();
$stmt = $pdo->prepare('SELECT o.*, t.file, t.file_en FROM template_orders o JOIN templates t ON t.id = o.template_id WHERE o.id = ?');
$stmt->execute([$id]);
$o = $stmt->fetch();
if (!$o || $token === '' || !hash_equals((string)$o['token'], $token)) {
    forma_message_page('Link nicht gültig', 'Dieser Download-Link ist leider nicht gültig. Schreib mir gern an hello@forma-pottery.com.');
}
if ($o['status'] === 'offen') {
    try { forma_template_check($pdo, $o); $stmt->execute([$id]); $o = $stmt->fetch(); } catch (Throwable $e) {}
}
$lang = (string)($_GET['lang'] ?? '');
if ($o['status'] === 'bezahlt' && (string)$o['file_en'] !== '' && $lang === '') {
    $base = '/api/template-download.php?o=' . $id . '&k=' . rawurlencode($token);
    forma_message_page('Dein Template ist bereit', "„{$o['title']}“ gibt es auf Deutsch und auf Englisch. Lade dir die Version herunter, die du brauchst, oder gleich beide.\n\nYour template is available in German and English.", 'Deutsch herunterladen', $base . '&lang=de', 'English download', $base . '&lang=en');
}
$useEn = $lang === 'en' && (string)$o['file_en'] !== '';
$file = forma_template_dir() . '/' . basename((string)($useEn ? $o['file_en'] : $o['file']));
if ($o['status'] !== 'bezahlt' || $o['file'] === '' || !is_file($file)) {
    forma_message_page('Noch nicht bereit', 'Dein Download ist noch nicht freigeschaltet. Bitte versuch es in ein paar Minuten noch einmal oder schreib mir an hello@forma-pottery.com.');
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="FORMA-Template-' . preg_replace('/[^A-Za-z0-9-]+/', '-', strtr((string)$o['title'], ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss'])) . ($useEn ? '-EN' : '') . '.pdf"');
header('Content-Length: ' . filesize($file));
readfile($file);

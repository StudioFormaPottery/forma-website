<?php
// Bild für das Saison-Pop-up (liegt im Datenordner außerhalb von public_html).
declare(strict_types=1);
require __DIR__ . '/lib.php';

$p = forma_popup(forma_db());
$file = forma_data_dir() . '/' . basename((string)$p['image']);
if ($p['image'] === '' || !is_file($file)) {
    http_response_code(404);
    exit;
}
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($file));
readfile($file);

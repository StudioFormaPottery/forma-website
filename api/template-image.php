<?php
// Vorschaubilder für Templates und das Template des Monats.
declare(strict_types=1);
require __DIR__ . '/lib.php';
$pdo = forma_db();
if (isset($_GET['maker'])) {
    $name = forma_maker($pdo)['image'];
} else {
    $stmt = $pdo->prepare('SELECT image FROM templates WHERE id = ? AND visible = 1');
    $stmt->execute([(int)($_GET['id'] ?? 0)]);
    $name = (string)($stmt->fetchColumn() ?: '');
}
$file = forma_template_dir() . '/' . basename($name);
if ($name === '' || !is_file($file)) {
    http_response_code(404);
    exit;
}
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=86400');
readfile($file);

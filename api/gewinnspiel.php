<?php
// Weihnachts-Gewinnspiel: POST = Teilnahme eintragen (JSON), GET ?t= = Bestätigungslink aus der Mail.
declare(strict_types=1);
require __DIR__ . '/gewinnspiel-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
mm_schema($pdo);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in)) $in = $_POST;
    $r = gw_enter($pdo, $in);
    http_response_code($r['ok'] ? 200 : 400);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
$r = gw_confirm($pdo, (string)($_GET['t'] ?? ''));
header('Location: /gewinnspiel/?' . ($r ? 'dabei=1' : 'link=alt') . '#mitmachen', true, 302);
exit;

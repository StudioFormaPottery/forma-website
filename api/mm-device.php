<?php
// Lebenszeichen der Mitgliederbereiche: ein Zugang ist nur auf einem Gerät gleichzeitig aktiv (Vivien, 26.10.2026)
declare(strict_types=1);
require __DIR__ . '/method-lib.php';
require_once __DIR__ . '/natal-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$pdo = forma_db();
$kind = ($_POST['k'] ?? $_GET['k'] ?? '') === 'natal' ? 'natal' : 'mv';
try {
    if ($kind === 'natal') { mm_schema($pdo); $me = natal_member_current($pdo); }
    else { mv_schema($pdo); $me = mv_current($pdo); }
    if (!$me) { echo json_encode(['state' => 'out']); exit; }
    $take = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['take'] ?? '') === '1';
    echo json_encode(mm_dev_ping($pdo, $kind, (int)$me['id'], $take));
} catch (Throwable $e) {
    error_log('MM device: ' . $e->getMessage());
    echo json_encode(['state' => 'ok']);
}

<?php
// Movecia-Gutschein kaufen (POST → Stripe) und Rückkehr nach der Zahlung (GET ?paid=ID&k=TOKEN). Vivien, 31.10.2026.
declare(strict_types=1);
require __DIR__ . '/mv-geschenk.inc.php';
header('Cache-Control: no-store');
$pdo = forma_db();
mv_gift_schema($pdo);
if (isset($_GET['paid'])) {
    $g = mv_gift_order($pdo, (int)$_GET['paid']);
    $k = (string)($_GET['k'] ?? '');
    if (!$g || $k === '' || !hash_equals((string)$g['token'], $k)) { header('Location: /gutscheine/', true, 303); exit; }
    try { mv_gift_check($pdo, $g); } catch (Throwable $e) { error_log('MV gift paid: ' . $e->getMessage()); }
    header('Location: /gutscheine/?danke=' . (int)$g['id'] . '&k=' . $g['token'], true, 303);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo '{}'; exit; }
$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
if (!empty($in['website']) || (int)($in['t'] ?? 0) < 2500) { echo json_encode(['error' => 'Bitte versuch es noch einmal.']); exit; }
try {
    if (!forma_stripe_key()) throw new FormaUserError('Die Bezahlung ist gerade nicht eingerichtet. Bitte schreib mir, dann schicke ich dir den Gutschein persönlich.');
    echo json_encode(['url' => mv_gift_checkout($pdo, $in)]);
} catch (FormaUserError $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('MV gift checkout: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Die Bezahlung klappt gerade nicht. Bitte versuch es gleich noch einmal oder schreib mir.']);
}

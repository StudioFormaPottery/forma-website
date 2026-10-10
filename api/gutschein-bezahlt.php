<?php
// Rückkehr von Stripe nach dem Gutschein-Kauf.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$id = (int)($_GET['v'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$result = 'fehler';
try {
    $pdo = forma_db();
    $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE id = ?');
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if ($v && $token !== '' && hash_equals((string)$v['token'], $token)) {
        $status = forma_voucher_check($pdo, $v);
        $result = $status === 'bezahlt' || $status === 'eingeloest' ? 'bezahlt' : ($status === 'offen' ? 'wartet' : 'abgebrochen');
    }
} catch (Throwable $e) {
    error_log('FORMA voucher paid: ' . $e->getMessage());
    $result = 'wartet';
}
$q = $result === 'bezahlt' ? '&v=' . $id . '&k=' . rawurlencode($token) : '';
header('Location: ' . forma_site_url() . '/?gutschein=' . $result . $q . '#gutschein', true, 303);

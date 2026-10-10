<?php
// Rückkehr von Stripe nach dem Template-Kauf.
declare(strict_types=1);
require __DIR__ . '/lib.php';
$id = (int)($_GET['o'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$result = 'fehler';
try {
    $pdo = forma_db();
    $stmt = $pdo->prepare('SELECT * FROM template_orders WHERE id = ?');
    $stmt->execute([$id]);
    $o = $stmt->fetch();
    if ($o && $token !== '' && hash_equals((string)$o['token'], $token)) {
        $st = forma_template_check($pdo, $o);
        $result = $st === 'bezahlt' ? 'bezahlt' : ($st === 'offen' ? 'wartet' : 'abgebrochen');
    }
} catch (Throwable $e) {
    error_log('FORMA template paid: ' . $e->getMessage());
    $result = 'wartet';
}
$q = $result === 'bezahlt' ? '&o=' . $id . '&k=' . rawurlencode($token) : '';
header('Location: ' . forma_site_url() . '/?template=' . $result . $q . '#templates', true, 303);

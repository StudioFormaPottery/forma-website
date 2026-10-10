<?php
// Rückkehr von Stripe nach der Buchung von Private Pottery.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$id = (int)($_GET['b'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$result = 'fehler';
try {
    $pdo = forma_db();
    $stmt = $pdo->prepare('SELECT * FROM private_bookings WHERE id = ?');
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if ($b && $token !== '' && hash_equals((string)$b['token'], $token)) {
        $status = forma_private_check($pdo, $b);
        $result = $status === 'bezahlt' ? 'bezahlt' : ($status === 'offen' ? 'wartet' : 'abgebrochen');
    }
} catch (Throwable $e) {
    error_log('FORMA private paid: ' . $e->getMessage());
    $result = 'wartet';
}
header('Location: ' . forma_site_url() . '/?private=' . $result . '#angebote', true, 303);

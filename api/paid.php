<?php
// Rückkehr von Stripe nach der Bezahlung: Zahlung prüfen, Buchung bestätigen, Mails verschicken.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$id = (int)($_GET['b'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$result = 'fehler';
try {
    $pdo = forma_db();
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if ($b && $token !== '' && hash_equals((string)$b['pay_token'], $token)) {
        $status = forma_check_payment($pdo, $b);
        $result = $status === 'bestaetigt' ? 'bezahlt' : ($status === 'offen' ? 'wartet' : 'abgebrochen');
    }
} catch (Throwable $e) {
    error_log('FORMA paid: ' . $e->getMessage());
    $result = 'wartet';
}
header('Location: ' . forma_site_url() . '/?buchung=' . $result . '#anmeldung', true, 303);

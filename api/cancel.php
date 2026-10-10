<?php
// Die Kundin hat die Bezahlung bei Stripe abgebrochen: Platz wieder freigeben.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$id = (int)($_GET['b'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$result = 'abgebrochen';
try {
    $pdo = forma_db();
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if ($b && $token !== '' && hash_equals((string)$b['pay_token'], $token) && $b['status'] === 'offen') {
        // Sicherheitshalber nachsehen, ob doch schon bezahlt wurde.
        if (forma_check_payment($pdo, $b) === 'bestaetigt') {
            $result = 'bezahlt';
        } else {
            try {
                forma_stripe('POST', '/v1/checkout/sessions/' . rawurlencode($b['stripe_session']) . '/expire');
            } catch (Throwable $e) {
                // Sitzung ist evtl. schon abgelaufen, das ist in Ordnung.
            }
            $pdo->prepare("UPDATE bookings SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([$id]);
        }
    }
} catch (Throwable $e) {
    error_log('FORMA cancel: ' . $e->getMessage());
}
header('Location: ' . forma_site_url() . '/?buchung=' . $result . '#anmeldung', true, 303);

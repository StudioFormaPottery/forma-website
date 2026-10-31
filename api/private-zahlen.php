<?php
// Bezahl-Link aus der Terminbestätigung: Eine Anfrage, die Vivien im Kalender eingeplant hat, online bezahlen.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$id = (int)($_GET['b'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$site = forma_site_url();
$go = function (string $result) use ($site): void {
    header('Location: ' . $site . '/?private=' . $result . '#angebote', true, 303);
    exit;
};
try {
    $pdo = forma_db();
    $st = $pdo->prepare('SELECT * FROM private_bookings WHERE id = ?');
    $st->execute([$id]);
    $b = $st->fetch();
    if (!$b || $token === '' || !hash_equals((string)$b['token'], $token)) $go('fehler');
    if ($b['status'] === 'bezahlt') $go('termin');
    if ($b['status'] !== 'anfrage' || !forma_stripe_key() || str_starts_with((string)$b['project'], 'Teambuilding/Event:')) $go('fehler');
    // Läuft schon eine Bezahlung? Dann zuerst nachsehen, ob sie inzwischen durch ist.
    if ($b['stripe_session'] !== '' && forma_private_check($pdo, $b) === 'bezahlt') $go('termin');
    $sessions = forma_private_sessions($pdo, $id);
    $hours = forma_private_planned($pdo, $id);
    if (!$sessions || $hours <= 0) $go('fehler');
    $amount = (int)round($hours * FORMA_PRIVATE_HOUR);
    if ($reuse = forma_stripe_reuse((string)$b['stripe_session'], $amount)) { header('Location: ' . $reuse, true, 303); exit; }
    $dates = implode(', ', array_map(fn($x) => date('d.m.', strtotime((string)$x['date'])) . ' ' . forma_de_time((string)$x['start']), $sessions));
    $session = forma_stripe('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => $b['email'],
        'client_reference_id' => 'private-' . $id,
        'metadata' => ['private_id' => (string)$id],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $amount,
                'product_data' => ['name' => 'Private Pottery · ' . forma_hours_text($hours), 'description' => 'Termin: ' . $dates . ' · FORMA Studio, Mathon'],
            ],
        ]],
        'payment_intent_data' => ['description' => 'Private Pottery ' . forma_hours_text($hours) . ' · ' . $b['name']],
        'expires_at' => time() + 60 * 60,
        'success_url' => $site . '/api/private-bezahlt.php?b=' . $id . '&k=' . rawurlencode($token),
        'cancel_url' => $site . '/?private=abgebrochen',
    ]);
    $pdo->prepare('UPDATE private_bookings SET stripe_session = ?, hours = ?, amount_cents = ? WHERE id = ? AND status = ?')
        ->execute([(string)$session['id'], $hours, $amount, $id, 'anfrage']);
    header('Location: ' . (string)$session['url'], true, 303);
    exit;
} catch (Throwable $e) {
    error_log('FORMA private pay later: ' . $e->getMessage());
    $go('fehler');
}

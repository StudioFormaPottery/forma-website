<?php
// Bezahl-Link für eine Private-Pottery-Verlängerung (zusätzliche Stunden, von Vivien in der Verwaltung freigeschaltet).
// ?e=ID&k=TOKEN       → Stripe-Bezahlseite öffnen
// ?e=ID&k=TOKEN&ok=1  → Rückkehr von Stripe: Zahlung prüfen und Ergebnis zeigen
declare(strict_types=1);
require __DIR__ . '/lib.php';

$id = (int)($_GET['e'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$site = forma_site_url();
$done = function (string $title, string $text): void {
    forma_message_page($title, $text, 'Zur Website', '/');
    exit;
};
try {
    $pdo = forma_db();
    $st = $pdo->prepare('SELECT x.*, p.name, p.email, p.project, p.status AS booking_status FROM private_extensions x JOIN private_bookings p ON p.id = x.private_id WHERE x.id = ?');
    $st->execute([$id]);
    $x = $st->fetch();
    if (!$x || $token === '' || !hash_equals((string)$x['token'], $token)) {
        $done('Link nicht gefunden', 'Dieser Bezahl-Link stimmt leider nicht. Schreib mir kurz an hello@forma-pottery.com oder ruf mich an, dann schicke ich dir einen neuen.');
    }
    $hours = forma_hours_text((float)$x['hours']);
    // Läuft schon eine Bezahlung? Dann zuerst nachsehen, ob sie inzwischen durch ist.
    if ($x['status'] === 'offen' && $x['stripe_session'] !== '' && forma_private_plus_check($pdo, $x) === 'bezahlt') $x['status'] = 'bezahlt';
    if ($x['status'] === 'bezahlt') {
        $done('Danke, alles bezahlt!', "Deine zusätzlichen $hours Private Pottery sind gebucht. Die Bestätigung und die Rechnung kommen per E-Mail. Ich melde mich bei dir, damit wir die neuen Termine ausmachen.");
    }
    if (!empty($_GET['ok'])) {
        $done('Fast geschafft', 'Deine Zahlung wird gerade noch bestätigt. Sobald sie angekommen ist, bekommst du eine E-Mail. Das dauert meist nur ein paar Minuten.');
    }
    if (!empty($_GET['abbruch']) && $x['status'] === 'offen') {
        forma_message_page('Bezahlung abgebrochen', "Kein Problem, es wurde nichts abgebucht. Dein Link bleibt gültig, du kannst die $hours jederzeit bezahlen.", 'Jetzt bezahlen', '/api/private-plus.php?e=' . $id . '&k=' . rawurlencode($token), 'Zur Website', '/');
        exit;
    }
    if ($x['status'] !== 'offen' || $x['booking_status'] !== 'bezahlt' || !forma_stripe_key()) {
        $done('Link nicht mehr gültig', 'Diese Verlängerung ist nicht mehr offen. Melde dich gern bei mir, wenn du noch Stunden dazubuchen möchtest.');
    }
    $amount = (int)round((float)$x['hours'] * FORMA_PRIVATE_HOUR);   // Preis immer hier am Server berechnet
    if ($reuse = forma_stripe_reuse((string)$x['stripe_session'], $amount)) { header('Location: ' . $reuse, true, 303); exit; }
    $session = forma_stripe('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => $x['email'],
        'client_reference_id' => 'private-plus-' . $id,
        'metadata' => ['private_id' => (string)$x['private_id'], 'extension_id' => (string)$id],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $amount,
                'product_data' => ['name' => 'Private Pottery · ' . $hours . ' zusätzlich', 'description' => 'Verlängerung deines Projekts · FORMA Studio, Mathon'],
            ],
        ]],
        'payment_intent_data' => ['description' => 'Private Pottery Verlängerung ' . $hours . ' · ' . $x['name']],
        'expires_at' => time() + 60 * 60,
        'success_url' => $site . '/api/private-plus.php?e=' . $id . '&k=' . rawurlencode($token) . '&ok=1',
        'cancel_url' => $site . '/api/private-plus.php?e=' . $id . '&k=' . rawurlencode($token) . '&abbruch=1',
    ]);
    $pdo->prepare("UPDATE private_extensions SET stripe_session = ?, amount_cents = ? WHERE id = ? AND status = 'offen'")
        ->execute([(string)$session['id'], $amount, $id]);
    header('Location: ' . (string)$session['url'], true, 303);
    exit;
} catch (Throwable $e) {
    error_log('FORMA private plus: ' . $e->getMessage());
    forma_message_page('Das hat leider nicht geklappt', 'Die Bezahlseite konnte gerade nicht geöffnet werden. Versuch es bitte in ein paar Minuten noch einmal oder melde dich kurz bei mir.', 'Zur Website', '/');
}

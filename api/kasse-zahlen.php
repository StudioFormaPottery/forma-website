<?php
// Bezahl-Link aus der Atelier-Kasse (Pottery bemalen).
// ?s=ID&k=TOKEN       → Stripe-Bezahlseite öffnen
// ?s=ID&k=TOKEN&ok=1  → Rückkehr von Stripe: Zahlung prüfen und Ergebnis zeigen
declare(strict_types=1);
require __DIR__ . '/lib.php';

$id = (int)($_GET['s'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$site = forma_site_url();
$done = function (string $title, string $text): void {
    forma_message_page($title, $text, 'Zur Website', '/');
    exit;
};
try {
    $pdo = forma_db();
    $s = forma_kasse_get($pdo, $id);
    if (!$s || $token === '' || !hash_equals((string)$s['token'], $token)) {
        $done('Link nicht gefunden', 'Dieser Bezahl-Link stimmt leider nicht. Schreib mir kurz an hello@forma-pottery.com oder ruf mich an, dann schicke ich dir einen neuen.');
    }
    $self = '/api/kasse-zahlen.php?s=' . $id . '&k=' . rawurlencode($token);
    // Läuft schon eine Bezahlung? Dann zuerst nachsehen, ob sie inzwischen durch ist.
    if ($s['status'] === 'offen' && $s['stripe_session'] !== '' && forma_kasse_check($pdo, $s) === 'bezahlt') $s['status'] = 'bezahlt';
    if ($s['status'] === 'bezahlt') {
        $done('Danke, alles bezahlt!', 'Deine Zahlung für Pottery bemalen ist angekommen. Die Rechnung kommt per E-Mail. Deine Stücke kannst du am Montag darauf im Atelier abholen.');
    }
    if (!empty($_GET['ok'])) {
        $done('Fast geschafft', 'Deine Zahlung wird gerade noch bestätigt. Sobald sie angekommen ist, bekommst du die Rechnung per E-Mail. Das dauert meist nur ein paar Minuten.');
    }
    if (!empty($_GET['abbruch']) && $s['status'] === 'offen') {
        forma_message_page('Bezahlung abgebrochen', 'Kein Problem, es wurde nichts abgebucht. Dein Link bleibt gültig, du kannst jederzeit bezahlen.', 'Jetzt bezahlen', $self, 'Zur Website', '/');
        exit;
    }
    if ($s['status'] !== 'offen' || !forma_stripe_key()) {
        $done('Link nicht mehr gültig', 'Dieser Bezahl-Link ist nicht mehr offen. Melde dich gern bei mir, falls noch etwas offen ist.');
    }
    $items = json_decode((string)$s['items'], true) ?: [];
    $lineItems = [];
    foreach ($items as $i) {
        $lineItems[] = [
            'quantity' => (int)$i['qty'],
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int)$i['cents'],
                'product_data' => ['name' => 'Pottery bemalen Workshop · ' . $i['name'], 'description' => $i['key'] === 'beitrag' ? 'Platz im Atelier, Pinsel und Farben' : 'Werkstück zum Bemalen inkl. Farben und Fertigstellung'],
            ],
        ];
    }
    $params = [
        'mode' => 'payment',
        'locale' => 'de',
        'client_reference_id' => 'kasse-' . $id,
        'metadata' => ['kasse_id' => (string)$id],
        'line_items' => $lineItems,
        'payment_intent_data' => ['description' => 'Pottery bemalen Workshop · ' . $s['name']],
        'expires_at' => time() + 60 * 60,
        'success_url' => $site . $self . '&ok=1',
        'cancel_url' => $site . $self . '&abbruch=1',
    ];
    if (trim((string)$s['email']) !== '') $params['customer_email'] = (string)$s['email'];
    if ($reuse = forma_stripe_reuse((string)$s['stripe_session'], forma_line_total($lineItems))) { header('Location: ' . $reuse, true, 303); exit; }
    $session = forma_stripe('POST', '/v1/checkout/sessions', $params);
    $pdo->prepare("UPDATE kasse_sales SET stripe_session = ? WHERE id = ? AND status = 'offen'")->execute([(string)$session['id'], $id]);
    header('Location: ' . (string)$session['url'], true, 303);
    exit;
} catch (Throwable $e) {
    error_log('FORMA kasse zahlen: ' . $e->getMessage());
    forma_message_page('Das hat leider nicht geklappt', 'Die Bezahlseite konnte gerade nicht geöffnet werden. Versuch es bitte in ein paar Minuten noch einmal oder melde dich kurz bei mir.', 'Zur Website', '/');
}

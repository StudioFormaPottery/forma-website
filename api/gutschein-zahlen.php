<?php
// Bezahlseite für Gutscheine aus der Atelier-Kasse (QR-Code in der Verwaltung).
// ?v=ID&k=TOKEN          → Stripe-Bezahlseite öffnen
// ?v=ID&k=TOKEN&abbruch=1 → zurück von Stripe ohne Zahlung
// Nach der Zahlung führt Stripe zu api/gutschein-bezahlt.php (Nummer, Code, Mails, PDF).
declare(strict_types=1);
require __DIR__ . '/lib.php';

$id = (int)($_GET['v'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$site = forma_site_url();
$done = function (string $title, string $text, string $btn = '', string $url = ''): void {
    forma_message_page($title, $text, $btn, $url);
    exit;
};
try {
    $pdo = forma_db();
    $v = forma_kasse_voucher_get($pdo, $id);
    if (!$v || $token === '' || !hash_equals((string)$v['token'], $token)) {
        $done('Link nicht gefunden', 'Dieser Bezahl-Link stimmt leider nicht. Frag mich einfach kurz im Atelier oder ruf mich an: ' . FORMA_PHONE);
    }
    $self = '/api/gutschein-zahlen.php?v=' . $id . '&k=' . rawurlencode($token);
    $status = forma_kasse_voucher_status($pdo, $v);
    if ($status === 'bezahlt' || $status === 'eingeloest') {
        $v = forma_kasse_voucher_get($pdo, $id);
        $done('Danke, dein Gutschein ist bezahlt!', 'Gutschein-Nr. ' . $v['nr'] . ' über ' . forma_euro((int)$v['amount_cents']) . '. Die Mail mit deinem Gutscheincode und dem Button zum Termin-Einbuchen ist schon unterwegs.', 'Gutschein als PDF', forma_voucher_link($v));
    }
    if ($status !== 'offen' || !forma_stripe_key()) {
        $done('Link nicht mehr gültig', 'Dieser Gutschein ist nicht mehr offen. Frag mich einfach kurz im Atelier.');
    }
    if (!empty($_GET['abbruch'])) {
        forma_message_page('Bezahlung abgebrochen', 'Kein Problem, es wurde nichts abgebucht. Du kannst gleich noch einmal bezahlen.', 'Jetzt bezahlen', $self);
        exit;
    }
    // Läuft schon eine Bezahlseite? Dann dieselbe wieder öffnen (verhindert doppelte Zahlungen).
    if ((string)$v['stripe_session'] !== '') {
        $old = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$v['stripe_session']));
        if (($old['status'] ?? '') === 'open' && !empty($old['url'])) {
            header('Location: ' . (string)$old['url'], true, 303);
            exit;
        }
    }
    $p = forma_voucher_product($v);
    $title = forma_voucher_title($v);
    $session = forma_stripe('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => (string)$v['buyer_email'],
        'client_reference_id' => 'gutschein-' . $id,
        'metadata' => ['gutschein_id' => (string)$id, 'inhalt' => $title, 'quelle' => 'Atelier-Kasse'],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int)$v['amount_cents'],
                'product_data' => ['name' => 'FORMA Gutschein · ' . $title, 'description' => 'Wertgutschein, einlösbar für alle Kurse im FORMA Atelier'],
            ],
        ]],
        'payment_intent_data' => ['description' => 'Gutschein (Atelier) ' . $title . ' · ' . $v['buyer_name']],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/gutschein-bezahlt.php?v=' . $id . '&k=' . rawurlencode($token),
        'cancel_url' => $site . $self . '&abbruch=1',
    ]);
    $pdo->prepare("UPDATE vouchers SET stripe_session = ? WHERE id = ? AND status = 'offen'")->execute([(string)$session['id'], $id]);
    header('Location: ' . (string)$session['url'], true, 303);
    exit;
} catch (Throwable $e) {
    error_log('FORMA gutschein zahlen: ' . $e->getMessage());
    forma_message_page('Das hat leider nicht geklappt', 'Die Bezahlseite konnte gerade nicht geöffnet werden. Versuch es bitte gleich noch einmal.', 'Nochmal versuchen', '/api/gutschein-zahlen.php?v=' . $id . '&k=' . rawurlencode($token));
}

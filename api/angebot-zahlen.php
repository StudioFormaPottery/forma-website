<?php
// Online bezahlen für ein bestätigtes Gruppen-Angebot (Link und QR-Code auf Vorabrechnung und Angebotsseite).
// ?t=TOKEN          → Stripe-Bezahlseite öffnen
// ?t=TOKEN&ok=1     → Rückkehr von Stripe: Zahlung prüfen und Ergebnis zeigen
declare(strict_types=1);
require __DIR__ . '/lib.php';

$token = (string)($_GET['t'] ?? '');
$done = function (string $title, string $text, string $btn = '', string $url = ''): void {
    forma_message_page($title, $text, $btn, $url);
    exit;
};
try {
    $pdo = forma_db();
    $o = forma_offer_by_token($pdo, $token);
    if (!$o) $done('Link nicht gefunden', 'Dieser Bezahl-Link stimmt leider nicht. Schreib mir kurz an hello@forma-pottery.com oder ruf mich an: ' . FORMA_PHONE);
    $page = '/angebot/?t=' . $o['token'];
    $self = '/api/angebot-zahlen.php?t=' . $o['token'];
    if ($o['status'] === 'bestaetigt' && $o['stripe_session'] !== '' && forma_offer_check($pdo, $o) === 'bezahlt') $o['status'] = 'bezahlt';
    if ($o['status'] === 'bezahlt') $done('Danke, alles bezahlt!', 'Die Zahlung ist angekommen. Die fertige Rechnung kommt per E-Mail. Ich freu mich auf euch!', 'Zum Angebot', $page);
    if (!empty($_GET['ok'])) $done('Fast geschafft', 'Die Zahlung wird gerade noch bestätigt. Sobald sie angekommen ist, kommt die Rechnung per E-Mail. Das dauert meist nur ein paar Minuten.', 'Zum Angebot', $page);
    if (!empty($_GET['abbruch']) && $o['status'] === 'bestaetigt') {
        forma_message_page('Bezahlung abgebrochen', 'Kein Problem, es wurde nichts abgebucht. Der Link bleibt gültig, ihr könnt jederzeit online bezahlen oder bar vor Ort.', 'Jetzt bezahlen', $self, 'Zum Angebot', $page);
        exit;
    }
    if ($o['status'] === 'offen') $done('Erst bestätigen', 'Bitte bestätige zuerst das Angebot mit der Anzahl der Kinder, dann kannst du bezahlen.', 'Zum Angebot', $page);
    if ($o['status'] !== 'bestaetigt' || !forma_stripe_key() || forma_offer_total($o) <= 0) $done('Link nicht mehr gültig', 'Dieser Bezahl-Link ist nicht mehr offen. Melde dich gern bei mir, falls noch etwas offen ist.');
    $lineItems = [];
    foreach (forma_offer_items($o) as $it) {
        $lineItems[] = ['quantity' => (int)$it['qty'], 'price_data' => ['currency' => 'eur', 'unit_amount' => (int)$it['unit'],
            'product_data' => ['name' => $it['text'], 'description' => $it['detail']]]];
    }
    $site = forma_site_url();
    $params = [
        'mode' => 'payment',
        'locale' => 'de',
        'client_reference_id' => 'angebot-' . $o['id'],
        'metadata' => ['offer_id' => (string)$o['id']],
        'line_items' => $lineItems,
        'payment_intent_data' => ['description' => $o['title'] . ' · ' . $o['org'] . ' · ' . $o['nr']],
        'expires_at' => time() + 60 * 60,
        'success_url' => $site . $self . '&ok=1',
        'cancel_url' => $site . $self . '&abbruch=1',
    ];
    if (trim((string)$o['email']) !== '') $params['customer_email'] = (string)$o['email'];
    if ($reuse = forma_stripe_reuse((string)$o['stripe_session'], forma_line_total($lineItems))) { header('Location: ' . $reuse, true, 303); exit; }
    $session = forma_stripe('POST', '/v1/checkout/sessions', $params);
    $pdo->prepare("UPDATE offers SET stripe_session = ? WHERE id = ? AND status = 'bestaetigt'")->execute([(string)$session['id'], (int)$o['id']]);
    header('Location: ' . (string)$session['url'], true, 303);
    exit;
} catch (Throwable $e) {
    error_log('FORMA angebot zahlen: ' . $e->getMessage());
    forma_message_page('Das hat leider nicht geklappt', 'Die Bezahlseite konnte gerade nicht geöffnet werden. Versuch es bitte in ein paar Minuten noch einmal oder melde dich kurz bei mir.');
}

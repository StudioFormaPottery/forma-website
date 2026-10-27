<?php
// Gutschein kaufen: Angaben prüfen, Stripe-Checkout anlegen.
declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
}
$in = $_POST;
if (!empty($in['website'])) {
    forma_json(['ok' => true]);
}
$loadedAt = (int)($in['t'] ?? 0);
if ($loadedAt > 0 && ((int)(microtime(true) * 1000) - $loadedAt) < 3000) {
    forma_json(['ok' => false, 'error' => 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);
}
$product = (string)($in['product'] ?? '');
$qty = (int)($in['qty'] ?? 1);
$design = (int)($in['design'] ?? 1) === 2 ? 2 : 1;
$recipient = forma_clean((string)($in['recipient'] ?? ''), 40);
$sender = forma_clean((string)($in['sender'] ?? ''), 40);
$message = trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($in['message'] ?? '')) ?? '', 0, 220));
$name = forma_clean((string)($in['buyer_name'] ?? ''), 120);
$email = forma_clean((string)($in['buyer_email'] ?? ''), 160);

$errors = [];
if (!isset(FORMA_VOUCHERS[$product])) $errors[] = 'Bitte wähle einen Gutschein.';
if ($qty < 1 || $qty > 10) $errors[] = 'Bitte wähle eine Anzahl zwischen 1 und 10.';
if (mb_strlen($name) < 2) $errors[] = 'Bitte gib deinen Namen an.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
if (empty($in['consent'])) $errors[] = 'Bitte bestätige noch die Hinweise zum Datenschutz.';
if ($errors) {
    forma_json(['ok' => false, 'error' => implode(' ', $errors)], 422);
}
if (!forma_stripe_key()) {
    forma_json(['ok' => false, 'error' => 'Gutscheine kannst du gerade noch nicht online kaufen. Schreib mir gern unter ' . FORMA_PHONE . ' oder hello@forma-pottery.com, dann stelle ich dir einen aus.'], 503);
}

$p = FORMA_VOUCHERS[$product];
$amount = $p['price'] * $qty;
$token = bin2hex(random_bytes(16));
try {
    $pdo = forma_db();
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'forma');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vouchers WHERE note = ? AND created_at >= ?');
    $stmt->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$stmt->fetchColumn() >= 8) {
        forma_json(['ok' => false, 'error' => 'Bitte versuch es später noch einmal.'], 429);
    }
    $pdo->prepare('INSERT INTO vouchers (product, qty, amount_cents, recipient, sender, message, design, buyer_name, buyer_email, status, token, note, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'offen\', ?, ?, ?)')
        ->execute([$product, $qty, $amount, $recipient, $sender, $message, $design, $name, $email, $token, $ipHash, date('c')]);
    $id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('FORMA voucher: ' . $e->getMessage());
    forma_json(['ok' => false, 'error' => 'Das hat gerade nicht geklappt. Bitte versuch es später noch einmal.'], 500);
}

$v = ['product' => $product, 'qty' => $qty];
$site = forma_site_url();
try {
    $session = forma_stripe('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => $email,
        'client_reference_id' => 'gutschein-' . $id,
        'metadata' => ['gutschein_id' => (string)$id, 'inhalt' => forma_voucher_title($v)],
        'line_items' => [[
            'quantity' => $qty,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $p['price'],
                'product_data' => ['name' => 'Gutschein ' . $p['name'], 'description' => forma_voucher_title($v) . ' · zum Ausdrucken'],
            ],
        ]],
        'payment_intent_data' => ['description' => 'Gutschein ' . forma_voucher_title($v) . ' · ' . $name],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/gutschein-bezahlt.php?v=' . $id . '&k=' . $token,
        'cancel_url' => $site . '/?gutschein=abgebrochen',
    ]);
} catch (Throwable $e) {
    error_log('FORMA voucher Stripe: ' . $e->getMessage());
    $pdo->prepare("UPDATE vouchers SET status = 'abgebrochen' WHERE id = ?")->execute([$id]);
    forma_json(['ok' => false, 'error' => 'Die Online-Bezahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.'], 502);
}
$pdo->prepare('UPDATE vouchers SET stripe_session = ? WHERE id = ?')->execute([(string)$session['id'], $id]);
forma_json(['ok' => true, 'redirect' => (string)$session['url']]);

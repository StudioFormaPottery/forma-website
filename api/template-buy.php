<?php
// Template kaufen: Stripe-Checkout anlegen.
declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
}
$in = $_POST;
if (!empty($in['website'])) {
    forma_json(['ok' => true]);
}
$id = (int)($in['template_id'] ?? 0);
$name = forma_clean((string)($in['name'] ?? ''), 120);
$email = forma_clean((string)($in['email'] ?? ''), 160);
$errors = [];
if (mb_strlen($name) < 2) $errors[] = 'Bitte gib deinen Namen an.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
if (empty($in['waiver'])) $errors[] = 'Bitte bestätige den Hinweis zum sofortigen Download.';
if (empty($in['consent'])) $errors[] = 'Bitte bestätige noch die Hinweise zum Datenschutz.';
if ($errors) {
    forma_json(['ok' => false, 'error' => implode(' ', $errors)], 422);
}
$pdo = forma_db();
$stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ? AND visible = 1 AND file != ''");
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t || !forma_stripe_key() || (int)$t['price_cents'] <= 0) {
    forma_json(['ok' => false, 'error' => 'Dieses Template kann gerade nicht gekauft werden.'], 404);
}
$token = bin2hex(random_bytes(16));
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'forma');
$pdo->prepare('INSERT INTO template_orders (template_id, title, amount_cents, name, email, status, token, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, \'offen\', ?, ?, ?)')
    ->execute([$id, $t['title'], (int)$t['price_cents'], $name, $email, $token, $ipHash, date('c')]);
$oid = (int)$pdo->lastInsertId();
$site = forma_site_url();
try {
    $session = forma_stripe('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => $email,
        'client_reference_id' => 'template-' . $oid,
        'metadata' => ['template_order_id' => (string)$oid],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => ['currency' => 'eur', 'unit_amount' => (int)$t['price_cents'],
                'product_data' => ['name' => 'Template: ' . $t['title'], 'description' => 'Digitaler Download (PDF) · FORMA Earth & Art']],
        ]],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/template-bezahlt.php?o=' . $oid . '&k=' . $token,
        'cancel_url' => $site . '/?template=abgebrochen#templates',
    ]);
} catch (Throwable $e) {
    error_log('FORMA template Stripe: ' . $e->getMessage());
    $pdo->prepare("UPDATE template_orders SET status = 'abgebrochen' WHERE id = ?")->execute([$oid]);
    forma_json(['ok' => false, 'error' => 'Die Online-Bezahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.'], 502);
}
$pdo->prepare('UPDATE template_orders SET stripe_session = ? WHERE id = ?')->execute([(string)$session['id'], $oid]);
forma_json(['ok' => true, 'redirect' => (string)$session['url']]);

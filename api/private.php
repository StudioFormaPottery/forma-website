<?php
// Private Pottery: Stunden direkt buchen (Stripe) oder erst einmal anfragen.
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
$kind = ($in['kind'] ?? '') === 'anfrage' ? 'anfrage' : 'buchung';
$hours = (int)($in['hours'] ?? 0);
$persons = 1;
$name = forma_clean((string)($in['name'] ?? ''), 120);
$email = forma_clean((string)($in['email'] ?? ''), 160);
$phone = forma_clean((string)($in['phone'] ?? ''), 40);
$project = trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($in['project'] ?? '')) ?? '', 0, 1500));
if (($in['topic'] ?? '') === 'team' && $project !== '') {
    $project = 'Teambuilding/Event: ' . $project;
}
$ref = forma_clean((string)($in['ref'] ?? ''), 60);
if ($ref !== '' && mb_strlen($project) >= 5) $project = mb_substr('[Empfohlen von: ' . $ref . '] ' . $project, 0, 1600);
$wish = forma_clean((string)($in['wish'] ?? ''), 300);
$contact = ($in['contact'] ?? '') === 'telefon' ? 'telefon' : 'email';
$wishDate = (string)($in['wish_date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wishDate) || strtotime($wishDate) === false || $wishDate < date('Y-m-d')) $wishDate = '';
$wishTime = array_key_exists((string)($in['wish_time'] ?? ''), FORMA_WISH_TIMES) ? (string)$in['wish_time'] : '';

$errors = [];
if (mb_strlen($name) < 2) $errors[] = 'Bitte gib deinen Namen an.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
if (mb_strlen($project) < 5) $errors[] = 'Beschreib bitte kurz, was du gestalten möchtest.';
if ($kind === 'buchung' && ($hours < 1 || $hours > 8)) $errors[] = 'Bitte wähle, wie viele Stunden du buchen möchtest.';
if ($contact === 'telefon' && mb_strlen(preg_replace('/\D/', '', $phone)) < 6) $errors[] = 'Bitte gib deine Telefonnummer an, damit ich dich anrufen kann.';
if ($kind === 'buchung' && $wishDate === '') $errors[] = 'Bitte wähl deinen Wunschtermin aus.';
if (empty($in['consent'])) $errors[] = 'Bitte bestätige noch die Hinweise zum Datenschutz.';
if ($errors) {
    forma_json(['ok' => false, 'error' => implode(' ', $errors)], 422);
}
if ($kind === 'buchung' && !forma_stripe_key()) {
    $kind = 'anfrage';
}

$amount = $kind === 'buchung' ? FORMA_PRIVATE_HOUR * $hours * $persons : 0;
$token = bin2hex(random_bytes(16));
try {
    $pdo = forma_db();
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'forma');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM private_bookings WHERE ip_hash = ? AND created_at >= ?');
    $stmt->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$stmt->fetchColumn() >= 6) {
        forma_json(['ok' => false, 'error' => 'Bitte versuch es später noch einmal oder ruf mich an: ' . FORMA_PHONE], 429);
    }
    $pdo->prepare('INSERT INTO private_bookings (kind, hours, amount_cents, name, email, phone, persons, project, wish, status, token, ip_hash, created_at, contact, wish_date, wish_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$kind, $kind === 'buchung' ? $hours : max(0, min(8, $hours)), $amount, $name, $email, $phone, $persons, $project, $wish, $kind === 'anfrage' ? 'anfrage' : 'offen', $token, $ipHash, date('c'), $contact, $wishDate, $wishTime]);
    $id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('FORMA private: ' . $e->getMessage());
    forma_json(['ok' => false, 'error' => 'Das hat gerade nicht geklappt. Bitte ruf mich an: ' . FORMA_PHONE], 500);
}

if ($kind === 'anfrage') {
    forma_private_mails($pdo, $id);
    forma_json(['ok' => true, 'kind' => 'anfrage']);
}

$site = forma_site_url();
$label = ($hours === 1 ? '1 Stunde' : "$hours Stunden") . ' Private Pottery' . ($persons > 1 ? " für $persons Personen" : '');
try {
    $session = forma_stripe('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => $email,
        'client_reference_id' => 'private-' . $id,
        'metadata' => ['private_id' => (string)$id],
        'line_items' => [[
            'quantity' => $hours * $persons,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => FORMA_PRIVATE_HOUR,
                'product_data' => ['name' => 'Private Pottery', 'description' => $label . ' · Termin nach Vereinbarung · FORMA Studio, Mathon'],
            ],
        ]],
        'payment_intent_data' => ['description' => $label . ' · ' . $name],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/private-bezahlt.php?b=' . $id . '&k=' . $token,
        'cancel_url' => $site . '/?private=abgebrochen',
    ]);
} catch (Throwable $e) {
    error_log('FORMA private Stripe: ' . $e->getMessage());
    $pdo->prepare("UPDATE private_bookings SET status = 'abgebrochen' WHERE id = ?")->execute([$id]);
    forma_json(['ok' => false, 'error' => 'Die Online-Bezahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.'], 502);
}
$pdo->prepare('UPDATE private_bookings SET stripe_session = ? WHERE id = ?')->execute([(string)$session['id'], $id]);
forma_json(['ok' => true, 'redirect' => (string)$session['url']]);

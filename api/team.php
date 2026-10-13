<?php
// Teambuilding über die versteckte Seite /team/: Halbtages-Event direkt buchen und bezahlen, Töpfer-Wochenende verbindlich anfragen.
declare(strict_types=1);
require __DIR__ . '/lib.php';

const FORMA_TEAM_HALBTAG = 15900;
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
$in = $_POST;
if (!empty($in['website'])) forma_json(['ok' => true]);
$loadedAt = (int)($in['t'] ?? 0);
if ($loadedAt > 0 && ((int)(microtime(true) * 1000) - $loadedAt) < 3000) forma_json(['ok' => false, 'error' => 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);

$offer = ($in['offer'] ?? '') === 'wochenende' ? 'wochenende' : 'halbtag';
$persons = max(0, min(8, (int)($in['persons'] ?? 0)));
$name = forma_clean((string)($in['name'] ?? ''), 120);
$email = forma_clean((string)($in['email'] ?? ''), 160);
$phone = forma_clean((string)($in['phone'] ?? ''), 40);
$company = forma_clean((string)($in['company'] ?? ''), 120);
$note = trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($in['note'] ?? '')) ?? '', 0, 800));
$wish = forma_clean((string)($in['wish'] ?? ''), 300);
$wishDate = (string)($in['wish_date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wishDate) || $wishDate < date('Y-m-d')) $wishDate = '';

$errors = [];
if ($persons < 4) $errors[] = 'Bitte wähl, wie viele Personen ihr seid (4 bis 8).';
if ($wishDate === '') $errors[] = 'Bitte wähl euren 1. Wunschtermin.';
if (mb_strlen($name) < 2) $errors[] = 'Bitte gib deinen Namen an.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
if (empty($in['consent'])) $errors[] = 'Bitte bestätige noch die Hinweise zum Datenschutz.';
if ($errors) forma_json(['ok' => false, 'error' => implode(' ', $errors)], 422);

$label = $offer === 'halbtag' ? 'Halbtages-Event (4 Std.)' : 'Töpfer-Wochenende mit Übernachtung';
$project = 'Teambuilding/Event: ' . $label . ' für ' . $persons . ' Personen' . ($company !== '' ? ', ' . $company : '') . ($note !== '' ? '. ' . $note : '');
$kind = ($offer === 'halbtag' && forma_stripe_key()) ? 'buchung' : 'anfrage';
$amount = $kind === 'buchung' ? FORMA_TEAM_HALBTAG * $persons : 0;
$token = bin2hex(random_bytes(16));
try {
    $pdo = forma_db();
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'forma');
    $st = $pdo->prepare('SELECT COUNT(*) FROM private_bookings WHERE ip_hash = ? AND created_at >= ?');
    $st->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$st->fetchColumn() >= 6) forma_json(['ok' => false, 'error' => 'Bitte versuch es später noch einmal oder ruf mich an: ' . FORMA_PHONE], 429);
    $pdo->prepare('INSERT INTO private_bookings (kind, hours, amount_cents, name, email, phone, persons, project, wish, status, token, ip_hash, created_at, contact, wish_date, wish_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$kind, $offer === 'halbtag' ? 4 : 6, $amount, $name, $email, $phone, $persons, $project, $wish, $kind === 'anfrage' ? 'anfrage' : 'offen', $token, $ipHash, date('c'), 'email', $wishDate, '']);
    $id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('FORMA team: ' . $e->getMessage());
    forma_json(['ok' => false, 'error' => 'Das hat gerade nicht geklappt. Bitte ruf mich an: ' . FORMA_PHONE], 500);
}
if ($kind === 'anfrage') {
    forma_private_mails($pdo, $id);
    forma_json(['ok' => true, 'kind' => 'anfrage']);
}
$site = forma_site_url();
try {
    $session = forma_stripe('POST', '/v1/checkout/sessions', [
        'mode' => 'payment', 'locale' => 'de', 'customer_email' => $email,
        'client_reference_id' => 'private-' . $id, 'metadata' => ['private_id' => (string)$id],
        'line_items' => [['quantity' => $persons, 'price_data' => ['currency' => 'eur', 'unit_amount' => FORMA_TEAM_HALBTAG,
            'product_data' => ['name' => 'Teambuilding Halbtages-Event', 'description' => '4 Stunden töpfern im FORMA Studio, Mathon · pro Person · Termin nach Bestätigung']]]],
        'payment_intent_data' => ['description' => 'Teambuilding Halbtag · ' . $persons . ' Personen · ' . $name],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/private-bezahlt.php?b=' . $id . '&k=' . $token,
        'cancel_url' => $site . '/team/?a=halbtag&abgebrochen=1',
    ]);
} catch (Throwable $e) {
    error_log('FORMA team Stripe: ' . $e->getMessage());
    $pdo->prepare("UPDATE private_bookings SET status = 'abgebrochen' WHERE id = ?")->execute([$id]);
    forma_json(['ok' => false, 'error' => 'Die Online-Bezahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.'], 502);
}
$pdo->prepare('UPDATE private_bookings SET stripe_session = ? WHERE id = ?')->execute([(string)$session['id'], $id]);
forma_json(['ok' => true, 'redirect' => (string)$session['url']]);

<?php
// Nimmt eine Kursanmeldung entgegen, prüft freie Plätze und verschickt die E-Mails.
declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
}

$in = $_POST;
if (empty($in)) {
    $raw = file_get_contents('php://input') ?: '';
    $in = json_decode($raw, true) ?: [];
}

// Spam-Schutz: verstecktes Feld muss leer bleiben, Formular darf nicht in unter 3 Sekunden abgeschickt werden.
if (!empty($in['website'])) {
    forma_json(['ok' => true]);
}
$loadedAt = (int)($in['t'] ?? 0);
if ($loadedAt > 0 && ((int)(microtime(true) * 1000) - $loadedAt) < 3000) {
    forma_json(['ok' => false, 'error' => 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);
}

$courseId = (int)($in['course_id'] ?? 0);
$name     = forma_clean((string)($in['name'] ?? ''), 120);
$email    = forma_clean((string)($in['email'] ?? ''), 160);
$phone    = forma_clean((string)($in['phone'] ?? ''), 40);
$persons  = (int)($in['persons'] ?? 1);
$message  = forma_clean((string)($in['message'] ?? ''), 1000);
$ref = forma_clean((string)($in['ref'] ?? ''), 60);
if ($ref !== '') $message = mb_substr('Empfohlen von: ' . $ref . ($message !== '' ? ' · ' . $message : ''), 0, 1000);
$consent  = !empty($in['consent']);

$errors = [];
if ($courseId <= 0) $errors[] = 'Bitte wähle einen Kurs.';
if (mb_strlen($name) < 2) $errors[] = 'Bitte gib deinen Namen an.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
if ($persons < 1 || $persons > 20) $errors[] = 'Bitte gib an, für wie viele Personen du buchst.';
if (!$consent) $errors[] = 'Bitte bestätige die Hinweise zum Datenschutz.';
if ($errors) {
    forma_json(['ok' => false, 'error' => implode(' ', $errors)], 422);
}

try {
    $pdo = forma_db();
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'forma');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE ip_hash = ? AND created_at >= ?');
    $stmt->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$stmt->fetchColumn() >= 6) {
        forma_json(['ok' => false, 'error' => 'Es sind gerade sehr viele Anmeldungen eingegangen. Bitte melde dich telefonisch unter ' . FORMA_PHONE . '.'], 429);
    }

    $pdo->exec('BEGIN IMMEDIATE');
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND visible = 1 AND start_date >= ?');
    $stmt->execute([$courseId, date('Y-m-d')]);
    $course = $stmt->fetch();
    if (!$course) {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => 'Diesen Kurs gibt es leider nicht mehr.'], 404);
    }
    if ($course['start_date'] < forma_first_bookable_date()) {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => 'Für diesen Termin ist die Anmeldung schon geschlossen. Anmeldeschluss ist immer am Vorabend um 17 Uhr. Ruf mich gern an: ' . FORMA_PHONE], 409);
    }
    $free = (int)$course['capacity'] - forma_booked($pdo, $courseId);
    if ($free <= 0) {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => 'Dieser Kurs ist leider gerade ausgebucht. Schreib mir gern, dann melde ich mich, falls ein Platz frei wird.', 'free' => 0], 409);
    }
    if ($persons > $free) {
        $pdo->exec('ROLLBACK');
        $msg = $free === 1 ? 'Es ist nur noch 1 Platz frei.' : "Es sind nur noch $free Plätze frei.";
        forma_json(['ok' => false, 'error' => $msg, 'free' => $free], 409);
    }
    $priceCents = (int)$course['price_cents'];
    $online = $priceCents > 0 && forma_stripe_key() !== null;
    $payToken = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO bookings (course_id, name, email, phone, persons, message, status, ip_hash, created_at, amount_cents, pay_token)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$courseId, $name, $email, $phone, $persons, $message, $online ? 'offen' : 'bestaetigt', $ipHash, date('c'),
            $online ? $priceCents * $persons : 0, $payToken]);
    $bookingId = (int)$pdo->lastInsertId();
    $pdo->exec('COMMIT');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->exec('ROLLBACK');
    }
    error_log('FORMA book: ' . $e->getMessage());
    forma_json(['ok' => false, 'error' => 'Die Anmeldung konnte gerade nicht gespeichert werden. Bitte melde dich telefonisch unter ' . FORMA_PHONE . '.'], 500);
}

if (!$online) {
    forma_send_booking_mails($pdo, $bookingId);
    forma_json(['ok' => true, 'booking_id' => $bookingId, 'free' => $free - $persons]);
}

// Online bezahlen: Stripe-Checkout anlegen und die Kundin dorthin weiterleiten.
$site = forma_site_url();
try {
    $session = forma_stripe('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => $email,
        'client_reference_id' => (string)$bookingId,
        'metadata' => ['booking_id' => (string)$bookingId, 'kurs' => $course['title'], 'termin' => $course['date_text']],
        'line_items' => [[
            'quantity' => $persons,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $priceCents,
                'product_data' => ['name' => $course['title'], 'description' => $course['date_text'] . ' · FORMA Studio, Mathon'],
            ],
        ]],
        'payment_intent_data' => ['description' => $course['title'] . ' · ' . $course['date_text'] . ' · ' . $name],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/paid.php?b=' . $bookingId . '&k=' . $payToken,
        'cancel_url' => $site . '/api/cancel.php?b=' . $bookingId . '&k=' . $payToken,
    ]);
} catch (Throwable $e) {
    error_log('FORMA Stripe: ' . $e->getMessage());
    $pdo->prepare("UPDATE bookings SET status = 'abgebrochen' WHERE id = ?")->execute([$bookingId]);
    forma_json(['ok' => false, 'error' => 'Die Online-Bezahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal oder melde dich unter ' . FORMA_PHONE . '.'], 502);
}
$pdo->prepare('UPDATE bookings SET stripe_session = ? WHERE id = ?')->execute([(string)$session['id'], $bookingId]);
forma_json(['ok' => true, 'redirect' => (string)$session['url']]);

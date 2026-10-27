<?php
// Nimmt eine Kursanmeldung entgegen, prüft freie Plätze und verschickt die E-Mails.
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_once __DIR__ . '/belegt.inc.php';

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
$noPost   = !empty($in['no_post']) ? 1 : 0;
$voucherCode = forma_clean((string)($in['voucher'] ?? ''), 40);

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

    // Gutscheincode vorab prüfen (außerhalb der Transaktion, damit die Bremse gegen Durchprobieren greift)
    $voucher = null;
    if ($voucherCode !== '') {
        $lk = forma_voucher_lookup($pdo, $voucherCode);
        if (!$lk['ok']) forma_json(['ok' => false, 'error' => $lk['error']], 422);
        $voucher = $lk['voucher'];
    }

    $pdo->exec('BEGIN IMMEDIATE');
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND visible = 1 AND start_date >= ?');
    $stmt->execute([$courseId, date('Y-m-d')]);
    $course = $stmt->fetch();
    if (!$course) {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => 'Diesen Kurs gibt es leider nicht mehr.'], 404);
    }
    if ($course['title'] === FORMA_SERIES['bemalen']['title'] && date('Y-m-d') < '2026-10-19') {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => 'Pottery bemalen ist ab 19. Oktober buchbar. Trag dich gern in den Newsletter ein, dann sage ich dir Bescheid.'], 409);
    }
    if ($course['start_date'] < forma_first_bookable_date()) {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => 'Für diesen Termin ist die Anmeldung schon geschlossen. Anmeldeschluss ist immer am Vorabend um 17 Uhr. Ruf mich gern an: ' . FORMA_PHONE], 409);
    }
    // Gemeinsamer Kalender: Kurs hat noch niemanden, aber Vivien ist zu dieser Zeit schon anderswo gebucht
    $vbSpan = vb_course_span((string)$course['date_text']);
    if ($vbSpan && !forma_slot_hours($course) && forma_booked($pdo, $courseId) === 0 && !vb_free(vb_busy($pdo, (string)$course['start_date'], (string)$course['start_date'], ['forma']), (string)$course['start_date'], $vbSpan[0], $vbSpan[1] - $vbSpan[0])) {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => 'Dieser Termin ist leider nicht mehr frei. Bitte wähl einen anderen Tag oder schreib mir: ' . FORMA_PHONE, 'free' => 0], 409);
    }
    $isAtelier = forma_is_atelier($course);
    $hours = forma_slot_hours($course);
    $slots = [];
    if ($hours) {
        $slotFree = forma_slots_free($pdo, $course);
        // Gemeinsamer Kalender: leere Stunde, in der Vivien woanders gebucht ist → nicht buchbar
        $vbB = vb_busy($pdo, (string)$course['start_date'], (string)$course['start_date'], ['forma']);
        foreach ($slotFree as $vh => $vf) if ($vf >= (int)$course['capacity'] && !vb_free($vbB, (string)$course['start_date'], (int)$vh * 60, 60)) $slotFree[$vh] = 0;
        $want = array_values(array_unique(array_map('intval', (array)($in['slots'] ?? []))));
        $slots = array_values(array_filter($hours, fn($h) => in_array($h, $want, true)));
        if (!$slots) {
            $pdo->exec('ROLLBACK');
            forma_json(['ok' => false, 'error' => 'Bitte wähle mindestens eine Stunde aus.'], 422);
        }
        foreach ($slots as $h) {
            if ($slotFree[$h] < $persons) {
                $pdo->exec('ROLLBACK');
                forma_json(['ok' => false, 'error' => 'Von ' . forma_atelier_slot_label($h) . ' ist ' . ($slotFree[$h] === 0 ? 'leider schon alles ausgebucht.' : 'die Buchung leider nur noch für ' . ($slotFree[$h] === 1 ? '1 Person' : $slotFree[$h] . ' Personen') . ' möglich. Bitte passe die Anzahl an.')], 409);
            }
        }
        $free = max($slotFree);
    } else {
    $free = (int)$course['capacity'] - forma_booked($pdo, $courseId);
    }
    if (!$hours && $free <= 0) {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => 'Dieser Kurs ist leider gerade ausgebucht. Schreib mir gern, dann melde ich mich, falls ein Platz frei wird.', 'free' => 0], 409);
    }
    if (!$hours && $persons > $free) {
        $pdo->exec('ROLLBACK');
        $msg = 'Für diesen Termin ist die Buchung leider nur noch für ' . ($free === 1 ? '1 Person' : "$free Personen") . ' möglich. Bitte passe die Anzahl an.';
        forma_json(['ok' => false, 'error' => $msg, 'free' => $free], 409);
    }
    $priceCents = (int)$course['price_cents'];
    $pieces = $course['title'] === FORMA_SERIES['bemalen']['title'] ? forma_bemalen_pick($in['items'] ?? []) : [];
    if ($pieces) {
        // Nur so viele Stücke, wie gerade im Atelier frei sind (Bestand in der Verwaltung).
        $stock = forma_bemalen_free_map($pdo);
        foreach ($pieces as $pc) {
            $f = $stock[$pc['key']] ?? null;
            if ($f !== null && $pc['qty'] > $f) {
                $pdo->exec('ROLLBACK');
                forma_json(['ok' => false, 'error' => $f === 0
                    ? $pc['name'] . ' ist gerade leider vergriffen. Bitte nimm das Stück aus deiner Auswahl oder such dir ein anderes aus.'
                    : 'Von „' . $pc['name'] . '“ sind gerade nur noch ' . $f . ' Stück verfügbar. Bitte passe die Anzahl an.', 'stock' => $stock], 409);
            }
        }
    }
    if ($isAtelier) {
        $pieces = array_map(fn($h) => ['key' => 'slot' . $h, 'name' => 'Offenes Atelier ' . forma_atelier_slot_label($h), 'qty' => $persons, 'cents' => $priceCents], $slots);
        $priceCents = 0;
    } elseif ($slots) {
        // Pottery bemalen: die gewählten Stunden kosten nichts extra, sie reservieren nur den Platz in dieser Stunde.
        foreach ($slots as $h) $pieces[] = ['key' => 'slot' . $h, 'name' => 'Pottery bemalen ' . forma_atelier_slot_label($h), 'qty' => $persons, 'cents' => 0];
    }
    $piecesCents = array_sum(array_map(fn($i) => $i['qty'] * $i['cents'], $pieces));
    $total = $priceCents * $persons + $piecesCents;   // Preis wird immer hier am Server berechnet
    $vUse = $voucher ? min(forma_voucher_balance($pdo, $voucher), $total) : 0;
    if ($voucher && $vUse <= 0) {
        $pdo->exec('ROLLBACK');
        forma_json(['ok' => false, 'error' => $total > 0 ? 'Dieser Gutschein ist schon vollständig eingelöst.' : 'Für diesen Termin ist kein Betrag zu bezahlen, der Gutschein bleibt dir erhalten.'], 422);
    }
    $cash = $total - $vUse;
    $online = $cash > 0 && forma_stripe_key() !== null;
    $payToken = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO bookings (course_id, name, email, phone, persons, message, status, ip_hash, created_at, amount_cents, pay_token, extras, voucher_id, voucher_cents, no_post, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$courseId, $name, $email, $phone, $persons, $message, $online ? 'offen' : 'bestaetigt', $ipHash, date('c'),
            $online ? $cash : 0, $payToken, $pieces ? json_encode($pieces, JSON_UNESCAPED_UNICODE) : '', $voucher ? (int)$voucher['id'] : null, $vUse, $noPost,
            (!$online && $vUse > 0) ? date('c') : null]);
    $bookingId = (int)$pdo->lastInsertId();
    if ($voucher) forma_voucher_use($pdo, (int)$voucher['id'], 'kurs', $bookingId, $vUse, !$online);
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
        'line_items' => $vUse > 0 ? [[
            'quantity' => 1,
            'price_data' => ['currency' => 'eur', 'unit_amount' => $cash, 'product_data' => ['name' => $course['title'] . ' · Restbetrag', 'description' => $course['date_text'] . ' · Gesamt ' . forma_euro($total) . ', davon ' . forma_euro($vUse) . ' mit Gutschein bezahlt']],
        ]] : array_merge($priceCents <= 0 ? [] : [[
            'quantity' => $persons,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $priceCents,
                'product_data' => ['name' => $course['title'] . ($pieces ? ' · Atelierbeitrag' : ''), 'description' => ($slots && !$isAtelier ? preg_replace('/\s*·?\s*\d{1,2}\s*[–-]\s*\d{1,2}\s*Uhr.*$/u', '', $course['date_text']) . ' · ' . forma_slots_text($slots) : $course['date_text']) . ' · FORMA Studio, Mathon'],
            ],
        ]], array_map(fn($i) => [
            'quantity' => $i['qty'],
            'price_data' => ['currency' => 'eur', 'unit_amount' => $i['cents'], 'product_data' => ['name' => $isAtelier ? $i['name'] . ' · ' . $course['date_text'] : $i['name'] . ' zum Bemalen']],
        ], array_values(array_filter($pieces, fn($i) => (int)$i['cents'] > 0)))),
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

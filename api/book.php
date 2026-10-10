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
if ($loadedAt > 0 && (time() * 1000 - $loadedAt) < 3000) {
    forma_json(['ok' => false, 'error' => 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);
}

$courseId = (int)($in['course_id'] ?? 0);
$name     = forma_clean((string)($in['name'] ?? ''), 120);
$email    = forma_clean((string)($in['email'] ?? ''), 160);
$phone    = forma_clean((string)($in['phone'] ?? ''), 40);
$persons  = (int)($in['persons'] ?? 1);
$message  = forma_clean((string)($in['message'] ?? ''), 1000);
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
    $pdo->prepare('INSERT INTO bookings (course_id, name, email, phone, persons, message, status, ip_hash, created_at)
        VALUES (?, ?, ?, ?, ?, ?, \'bestaetigt\', ?, ?)')
        ->execute([$courseId, $name, $email, $phone, $persons, $message, $ipHash, date('c')]);
    $bookingId = (int)$pdo->lastInsertId();
    $pdo->exec('COMMIT');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->exec('ROLLBACK');
    }
    error_log('FORMA book: ' . $e->getMessage());
    forma_json(['ok' => false, 'error' => 'Die Anmeldung konnte gerade nicht gespeichert werden. Bitte melde dich telefonisch unter ' . FORMA_PHONE . '.'], 500);
}

$firstName = preg_split('/\s+/', $name)[0] ?? $name;
$personsText = $persons === 1 ? '1 Person' : "$persons Personen";
$freeAfter = $free - $persons;
$formaPhone = FORMA_PHONE;
$siteUrl = FORMA_SITE_URL;
$minNote = ((int)$course['min_participants'] > 1)
    ? "\nDer Kurs findet ab {$course['min_participants']} Personen statt. Falls es zu wenige Anmeldungen gibt, melde ich mich rechtzeitig bei dir.\n"
    : '';

$customerBody = <<<TXT
Hallo $firstName,

danke, dass du dich für „{$course['title']}“ angemeldet hast! Deine Anmeldung ist verbindlich bei mir eingegangen, und ich freue mich schon sehr auf dich.

Hier noch einmal alles auf einen Blick:

Kurs: {$course['title']}
Termin: {$course['date_text']}
Personen: $personsText
Preis: {$course['price_text']}
Ort: FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon
$minNote
Falls du Fragen hast oder doch verhindert bist, antworte einfach auf diese E-Mail oder ruf mich an: $formaPhone

Bis bald im Studio!
Vivien
FORMA Earth & Art
www.forma-pottery.com
TXT;

$adminBody = <<<TXT
Neue Kursanmeldung über die Website

Kurs: {$course['title']}
Termin: {$course['date_text']}
Personen: $personsText

Name: $name
E-Mail: $email
Telefon: $phone
Nachricht: $message

Freie Plätze danach: $freeAfter

Alle Buchungen: $siteUrl/verwaltung/
TXT;

forma_mail($email, 'Deine Anmeldung: ' . $course['title'], $customerBody);
forma_mail(FORMA_NOTIFY_EMAIL, 'Neue Anmeldung: ' . $course['title'] . ' (' . $name . ')', $adminBody, $email);

forma_json(['ok' => true, 'booking_id' => $bookingId, 'free' => $freeAfter]);

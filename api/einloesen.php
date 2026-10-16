<?php
// Gast mit altem Gutschein löst über den Einmal-Link (/einloesen/?t=…) selbst einen Spin & Go Termin ein.
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_once __DIR__ . '/invoices.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') forma_json(['ok' => false, 'error' => 'Nur POST.'], 405);
if (trim((string)($_POST['website'] ?? '')) !== '') forma_json(['ok' => false, 'error' => 'Das hat leider nicht geklappt.'], 400);

$pdo = forma_db();
$link = forma_old_link_get($pdo, (string)($_POST['t'] ?? ''));
if (!$link || $link['status'] !== 'offen') {
    forma_json(['ok' => false, 'error' => 'Dieser Link wurde schon verwendet oder ist nicht mehr gültig. Melde dich gern bei mir: ' . FORMA_PHONE]);
}

$name = forma_clean((string)($_POST['name'] ?? ''), 120);
$email = mb_strtolower(forma_clean((string)($_POST['email'] ?? ''), 160));
$phone = forma_clean((string)($_POST['phone'] ?? ''), 40);
$street = forma_clean((string)($_POST['street'] ?? ''), 120);
$zip = forma_clean((string)($_POST['zip'] ?? ''), 12);
$city = forma_clean((string)($_POST['city'] ?? ''), 80);
$country = forma_clean((string)($_POST['country'] ?? 'Österreich'), 60) ?: 'Österreich';
$courseId = (int)($_POST['course'] ?? 0);

if (mb_strlen($name) < 2) forma_json(['ok' => false, 'error' => 'Bitte gib deinen Vor- und Nachnamen an.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) forma_json(['ok' => false, 'error' => 'Bitte gib eine gültige E-Mail-Adresse an.']);
if (strlen(preg_replace('/\D/', '', $phone)) < 6) forma_json(['ok' => false, 'error' => 'Bitte gib deine Telefonnummer an, damit ich dich bei Änderungen erreiche.']);
if ($street === '' || $zip === '' || $city === '') forma_json(['ok' => false, 'error' => 'Bitte gib deine Rechnungsadresse vollständig an.']);
if (empty($_POST['consent'])) forma_json(['ok' => false, 'error' => 'Bitte bestätige noch den Datenschutz.']);

// Nur echte, sichtbare Spin & Go Termine ab dem nächsten buchbaren Tag
$ok = false;
foreach (forma_upcoming_courses($pdo) as $c) {
    if ((int)$c['id'] === $courseId && $c['title'] === FORMA_SERIES['spin']['title']) { $ok = $c['free'] >= 1; break; }
}
if (!$ok) forma_json(['ok' => false, 'error' => 'Dieser Termin ist leider nicht mehr frei. Bitte such dir einen anderen Tag aus.']);

// Link sofort sperren, damit er wirklich nur einmal funktioniert
$pdo->exec('BEGIN IMMEDIATE');
$st = $pdo->prepare("UPDATE voucher_links SET status = 'eingeloest', used_at = ? WHERE id = ? AND status = 'offen'");
$st->execute([date('c'), $link['id']]);
$pdo->exec('COMMIT');
if ($st->rowCount() !== 1) forma_json(['ok' => false, 'error' => 'Dieser Link wurde gerade schon verwendet.']);

try {
    $r = forma_old_voucher_booking($pdo, [
        'course_id' => $courseId, 'name' => $name, 'email' => $email, 'phone' => $phone,
        'persons' => 1, 'value_cents' => (int)$link['value_cents'], 'nr' => '',
        'no_post' => !empty($_POST['no_post']), 'note' => (string)$link['note'], 'via_link' => true,
        'address' => implode("\n", array_filter([$street, trim("$zip $city"), $country !== 'Österreich' ? $country : ''])),
    ]);
} catch (Throwable $e) {
    $pdo->prepare("UPDATE voucher_links SET status = 'offen', used_at = NULL WHERE id = ?")->execute([$link['id']]);
    forma_json(['ok' => false, 'error' => $e instanceof RuntimeException ? $e->getMessage() : 'Das hat leider nicht geklappt. Bitte versuch es noch einmal oder ruf mich an: ' . FORMA_PHONE]);
}
$pdo->prepare('UPDATE voucher_links SET booking_id = ? WHERE id = ?')->execute([$r['booking'], $link['id']]);
$st = $pdo->prepare('SELECT date_text FROM courses WHERE id = ?');
$st->execute([$courseId]);
forma_json(['ok' => true, 'termin' => (string)$st->fetchColumn(), 'nr' => $r['nr'], 'email' => $email, 'first' => forma_first_name($name)]);

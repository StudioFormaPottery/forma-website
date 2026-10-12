<?php
// Kalendereintrag (.ics) zu einer Buchung – für „Zu Apple Kalender hinzufügen“ in den Bestätigungsmails.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$kind = ($_GET['k'] ?? '') === 'p' ? 'p' : 'b';
$id = (int)($_GET['id'] ?? 0);
$sig = (string)($_GET['s'] ?? '');
try {
    $pdo = forma_db();
    $ok = $id > 0 && hash_equals(forma_cal_sig($pdo, $kind, $id), $sig);
    $events = $ok ? forma_booking_events($pdo, $kind, $id) : [];
} catch (Throwable $e) {
    error_log('FORMA kalender: ' . $e->getMessage());
    $events = [];
}
if (!$events) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Dieser Termin wurde nicht gefunden. Bitte melde dich bei FORMA: ' . FORMA_PHONE;
    exit;
}
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="FORMA-Termin' . (count($events) > 1 ? 'e' : '') . '.ics"');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
echo forma_ics($events);

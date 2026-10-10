<?php
// Öffentliche Liste der kommenden Kurse mit freien Plätzen.
declare(strict_types=1);
require __DIR__ . '/lib.php';

try {
    $pdo = forma_db();
    $out = [];
    foreach (forma_upcoming_courses($pdo) as $c) {
        $out[] = [
            'id' => (int)$c['id'],
            'title' => $c['title'],
            'date_text' => $c['date_text'],
            'price_text' => $c['price_text'],
            'description' => $c['description'],
            'free' => (int)$c['free'],
            'min_participants' => (int)$c['min_participants'],
        ];
    }
    forma_json(['ok' => true, 'courses' => $out]);
} catch (Throwable $e) {
    error_log('FORMA courses: ' . $e->getMessage());
    forma_json(['ok' => false, 'error' => 'Die Termine konnten gerade nicht geladen werden.'], 500);
}

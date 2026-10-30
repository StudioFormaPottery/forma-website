<?php
// Welche saisonale Deko auf der Website erscheint (auto = nach Monat).
declare(strict_types=1);
require __DIR__ . '/lib.php';
$community = '';
try {
    $pdo = forma_db();
    $mode = forma_setting($pdo, 'deko') ?? 'auto';
    $community = forma_setting($pdo, 'community') ?? '';
} catch (Throwable $e) {
    $mode = 'auto';
}
header('Cache-Control: public, max-age=300');
forma_json(['ok' => true, 'community' => $community, 'mode' => in_array($mode, ['auto', 'halloween', 'herbst', 'winter', 'fruehling', 'sommer', 'aus'], true) ? $mode : 'auto']);

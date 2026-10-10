<?php
// Welche saisonale Deko auf der Website erscheint (auto = nach Monat).
declare(strict_types=1);
require __DIR__ . '/lib.php';
try {
    $mode = forma_setting(forma_db(), 'deko') ?? 'auto';
} catch (Throwable $e) {
    $mode = 'auto';
}
header('Cache-Control: public, max-age=300');
forma_json(['ok' => true, 'mode' => in_array($mode, ['auto', 'herbst', 'winter', 'fruehling', 'sommer', 'aus'], true) ? $mode : 'auto']);

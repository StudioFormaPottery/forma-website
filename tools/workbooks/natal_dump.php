<?php
// Gibt die 14 Prä-/Postnatal-Einheiten als JSON aus (für tools/workbooks/build.py). Aufruf: php natal_dump.php > out.json
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require $root . '/api/natal-units.inc.php';
require $root . '/api/natal-icons.inc.php';
require $root . '/api/natal-workbook.inc.php';
$out = [];
foreach (natal_units() as $k => $u) {
    $u['label'] = natal_unit_label($k);
    $u['icon_svg'] = natal_icon(natal_unit_icon($k), 150);
    $out[$k] = $u;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

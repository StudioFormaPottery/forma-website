<?php
// Löst den geplanten Presse-Versand aus (nur Mails aus PRESSE_SCHEDULE, nur wenn fällig, jede genau einmal).
// Gibt nur den Status zurück, keine Inhalte oder Adressen.
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_once __DIR__ . '/movecia-lib.php';
require_once __DIR__ . '/presse.inc.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['zeit' => date('c'), 'status' => presse_due()], JSON_UNESCAPED_UNICODE);

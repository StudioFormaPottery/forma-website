<?php
// Rückkehr von Stripe: Zahlung prüfen und zurück zur persönlichen Seite des Gasts.
declare(strict_types=1);
require __DIR__ . '/ptb-lib.php';
$pdo = forma_db();
ptb_schema($pdo);
$o = ptb_order_auth($pdo, (int)($_GET['o'] ?? 0), (string)($_GET['k'] ?? ''));
if (!$o) { http_response_code(404); exit('Dieser Link funktioniert leider nicht. Bitte schreib an ' . MM_FROM_EMAIL); }
$st = ptb_check_paid($pdo, $o);
header('Location: ' . ptb_link($o) . ($st === 'bezahlt' ? '&danke=1' : ''), true, 303);

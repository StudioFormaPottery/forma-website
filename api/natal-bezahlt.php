<?php
// Rückkehr von Stripe: Zahlung prüfen und zur persönlichen Seite weiterleiten.
declare(strict_types=1);
require __DIR__ . '/natal-lib.php';
$pdo = forma_db();
natal_schema($pdo);
$o = natal_order_auth($pdo, (int)($_GET['o'] ?? 0), (string)($_GET['k'] ?? ''));
if (!$o) { http_response_code(404); exit('Dieser Link funktioniert leider nicht. Bitte schreib an ' . MM_FROM_EMAIL); }
$st = natal_check_paid($pdo, $o);
// Pakete: direkt zum Mitgliederbereich (Passwort wählen bzw. anmelden), Einzelstunde: persönliche Terminseite (Vivien, 24.10.2026)
if ($st === 'bezahlt' && natal_order_is_member($o)) {
    header('Location: ' . (natal_member_by_email($pdo, (string)$o['email']) ? natal_member_link() . '?danke=1' : natal_member_start_link($o) . '&danke=1'), true, 303);
    exit;
}
header('Location: ' . natal_link($o) . ($st === 'bezahlt' ? '&danke=1' : ''), true, 303);

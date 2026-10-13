<?php
// Prüft einen Gutscheincode im Buchungsformular und zeigt den Restwert. Abgerechnet wird erst beim Buchen am Server.
declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
$code = forma_clean((string)($_POST['code'] ?? ''), 40);
if ($code === '') forma_json(['ok' => false, 'error' => 'Bitte gib deinen Gutscheincode ein.'], 422);
$pdo = forma_db();
$r = forma_voucher_lookup($pdo, $code);
if (!$r['ok']) forma_json(['ok' => false, 'error' => $r['error']], 422);
forma_json(['ok' => true, 'balance_cents' => $r['balance'], 'balance' => forma_euro($r['balance'])]);

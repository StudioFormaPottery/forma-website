<?php
// Workbook einer Einheit als PDF (Mitgliederbereich). Nur angemeldet, nur eigene Buchung, nur nach der Einheit
// und erst nach den ersten 14 Tagen (Widerrufsfrist, Vivien 24.10.2026).
declare(strict_types=1);
require __DIR__ . '/natal-lib.php';
require_once __DIR__ . '/natal-workbook.inc.php';
header('Cache-Control: no-store');
$pdo = forma_db();
natal_schema($pdo);
$me = natal_member_current($pdo);
$o = natal_order($pdo, (int)($_GET['o'] ?? 0));
$key = (string)($_GET['u'] ?? '');
$ok = $me && $o && strtolower((string)$o['email']) === strtolower((string)$me['email']) && $o['status'] === 'bezahlt' && !$o['purged_at'] && natal_member_full($o);
$sess = null;
if ($ok) foreach (natal_order_plan($pdo, $o) as [$k, $s]) if ($k === $key && natal_session_done($s)) $sess = $s;
if (!$ok || !$sess) { http_response_code(403); exit('Dieses Workbook ist gerade nicht für dich verfügbar.'); }
$pdf = natal_workbook_pdf($key, forma_first_name((string)$o['name']), date('j. n. Y', strtotime((string)$sess['date'])));
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Movecia-Workbook-' . preg_replace('/[^A-Za-z0-9]+/', '-', natal_unit_label($key)) . '.pdf"');
echo $pdf;

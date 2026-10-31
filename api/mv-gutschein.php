<?php
// Movecia-Gutschein als PDF zum Ausdrucken (Link aus der Rücktritts-Mail und dem Mitgliederbereich, nur mit Token).
declare(strict_types=1);
require __DIR__ . '/natal-lib.php';
$pdo = forma_db();
natal_schema($pdo);
$q = $pdo->prepare('SELECT * FROM mm_vouchers WHERE id = ?');
$q->execute([(int)($_GET['v'] ?? 0)]);
$v = $q->fetch();
$k = (string)($_GET['k'] ?? '');
if (!$v || $k === '' || !hash_equals((string)$v['token'], $k)) { http_response_code(404); exit('Gutschein nicht gefunden.'); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Movecia_Gutschein_' . $v['nr'] . '.pdf"');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
echo mv_voucher_pdf($v);

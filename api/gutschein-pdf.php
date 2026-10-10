<?php
// Gutschein als PDF zum Ausdrucken (nur mit gültigem Link und nach Bezahlung).
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/fpdf/fpdf.php';

$id = (int)($_GET['v'] ?? 0);
$token = (string)($_GET['k'] ?? '');
$pdo = forma_db();
$stmt = $pdo->prepare('SELECT * FROM vouchers WHERE id = ?');
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v || $token === '' || !hash_equals((string)$v['token'], $token)) {
    http_response_code(404);
    exit('Gutschein nicht gefunden.');
}
if ($v['status'] === 'offen') {
    try {
        forma_voucher_check($pdo, $v);
        $stmt->execute([$id]);
        $v = $stmt->fetch();
    } catch (Throwable $e) {
    }
}
if (!in_array($v['status'], ['bezahlt', 'eingeloest'], true)) {
    http_response_code(402);
    exit('Dieser Gutschein ist noch nicht bezahlt.');
}

function cp(string $s): string
{
    return (string)iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
}
function rgb(string $hex): array
{
    return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
}

$design = (int)$v['design'] === 2 ? 2 : 1;
$layout = json_decode((string)file_get_contents(__DIR__ . "/gutschein/design$design.json"), true);
$p = FORMA_VOUCHERS[$v['product']];
$texts = [
    'big' => forma_voucher_qty($v),
    'small' => $p['small'],
    'desc' => $p['desc'],
    'value' => 'Wert ' . forma_euro((int)$v['amount_cents']),
    'line' => forma_voucher_title($v) . ' · Wert ' . forma_euro((int)$v['amount_cents']),
    'to' => $v['recipient'],
    'frm' => $v['sender'],
    'msg' => $v['message'] !== '' ? ($design === 2 ? '„' . $v['message'] . '“' : $v['message']) : '',
    'nr' => 'Nr. ' . $v['nr'] . ($design === 2 ? ' · ausgestellt am ' . date('j. n. Y', strtotime((string)$v['paid_at'])) : ''),
    'date' => 'ausgestellt am ' . date('j. n. Y', strtotime((string)$v['paid_at'])),
];

define('FPDF_FONTPATH', __DIR__ . '/fpdf/font/');
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetTitle(cp('FORMA Gutschein ' . $v['nr']), false);
$pdf->SetAuthor('FORMA Earth & Art');
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(0, 0, 0);
$pdf->AddFont('B', '', 'BricolageGrotesque-Bold.json');
$pdf->AddFont('O', '', 'Outfit-Regular.json');
$pdf->AddFont('OB', '', 'Outfit-Bold.json');
$pdf->AddPage();
$pdf->Image(__DIR__ . "/gutschein/design$design.jpg", 0, 0, 297, 210);
foreach ($layout as $key => $box) {
    $text = $texts[$key] ?? '';
    if ($text === '') {
        continue;
    }
    $pdf->SetFont($box['font'], '', $box['pt']);
    [$r, $g, $b] = rgb($box['color']);
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetXY($box['x'], $box['y']);
    $pdf->MultiCell($box['w'], $box['lh'], cp($text), 0, $box['align']);
}
$pdf->Output('I', 'FORMA-Gutschein-' . $v['nr'] . '.pdf');

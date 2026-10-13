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
    'nr' => $design === 2 ? 'Nr. ' . $v['nr'] . ' · Gutscheincode ' . $v['code'] : 'Nr. ' . $v['nr'],
    'date' => 'Code: ' . $v['code'],
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
// Seite 2: So löst du deinen Gutschein ein
$pdf->AddPage();
$pdf->SetFillColor(248, 241, 234);
$pdf->Rect(0, 0, 297, 210, 'F');
$logo = dirname(__DIR__) . '/img/forma-logo.png';
if (is_file($logo)) $pdf->Image($logo, 133.5, 16, 30);
[$r, $g, $b] = rgb('#8C2C1D');
$pdf->SetTextColor($r, $g, $b);
$pdf->SetFont('B', '', 24);
$pdf->SetXY(20, 52); $pdf->Cell(257, 12, cp('So löst du deinen Gutschein ein'), 0, 2, 'C');
$pdf->SetDrawColor($r, $g, $b); $pdf->SetLineWidth(.6);
$pdf->Rect(78.5, 70, 140, 26, 'D');
$pdf->SetFont('O', '', 11); [$r2, $g2, $b2] = rgb('#674B40'); $pdf->SetTextColor($r2, $g2, $b2);
$pdf->SetXY(78.5, 73); $pdf->Cell(140, 6, cp('Dein Gutscheincode'), 0, 2, 'C');
$pdf->SetFont('B', '', 22); $pdf->SetTextColor($r, $g, $b);
$pdf->SetXY(78.5, 80); $pdf->Cell(140, 12, cp((string)$v['code']), 0, 2, 'C');
$pdf->SetFont('O', '', 12.5); [$r3, $g3, $b3] = rgb('#33211B'); $pdf->SetTextColor($r3, $g3, $b3);
$steps = [
    '1.  Such dir auf www.forma-pottery.com deinen Kurs und Termin aus.',
    '2.  Gib vor dem Bezahlen bei „Du hast einen Gutschein?“ deinen Code ein.',
    '3.  Der Wert wird sofort abgezogen. Ist alles gedeckt, ist deine Buchung gleich fix.',
    'Lieber persönlich? Ruf mich an oder schreib mir per WhatsApp: ' . FORMA_PHONE,
];
$y = 106;
foreach ($steps as $s) { $pdf->SetXY(48, $y); $pdf->MultiCell(201, 7, cp($s), 0, 'L'); $y += 9; }
$pdf->SetFont('OB', '', 11); $pdf->SetTextColor($r, $g, $b);
$pdf->SetXY(48, $y + 6); $pdf->Cell(201, 6, cp('Gut zu wissen'), 0, 2, 'L');
$pdf->SetFont('O', '', 10.5); $pdf->SetTextColor($r2, $g2, $b2);
$pdf->SetXY(48, $y + 13); $pdf->MultiCell(201, 5.6, cp(FORMA_VOUCHER_TERMS . ' Gutschein-Nr. ' . $v['nr'] . ', ausgestellt am ' . date('j. n. Y', strtotime((string)$v['paid_at'])) . '.'), 0, 'L');
$pdf->SetFont('O', '', 9.5);
$pdf->SetXY(20, 194); $pdf->Cell(257, 5, cp('FORMA Earth & Art · Silvrettastraße 58 · 6562 Mathon · hello@forma-pottery.com · www.forma-pottery.com'), 0, 0, 'C');
$pdf->Output('I', 'FORMA-Gutschein-' . $v['nr'] . '.pdf');

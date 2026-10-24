<?php
// Workbook je Einheit (Vivien, 24.10.2026): gleiche Seiten am Bildschirm (Mitgliederbereich) und als PDF.
// Inhalte aus api/natal-units.inc.php. In den ersten 14 Tagen nach der Buchung nur das erste Drittel als Bildschirm-Vorschau.
declare(strict_types=1);
require_once __DIR__ . '/natal-units.inc.php';
require_once __DIR__ . '/natal-icons.inc.php';

/** Icon passend zur Einheit. */
function natal_unit_icon(string $key): string
{
    $m = ['prae1' => 'kugel', 'prae2' => 'knospe', 'prae3' => 'feder', 'prae4' => 'mond', 'prae5' => 'sterne', 'prae6' => 'wolke',
        'post1' => 'herz', 'post2' => 'knospe', 'post3' => 'kugel', 'post4' => 'schuh', 'post5' => 'rassel', 'post6' => 'mond', 'post7' => 'wagen', 'post8' => 'mobile'];
    return $m[$key] ?? 'herz';
}

/** Seiten eines Workbooks: [['kind' => cover|text|ex|journal, …]] */
function natal_workbook_pages(string $key): array
{
    $u = natal_units()[$key] ?? null;
    if (!$u) return [];
    $p = [['kind' => 'cover', 'label' => natal_unit_label($key), 'title' => $u['title'], 'theme' => $u['theme'], 'merk' => $u['merk'], 'icon' => natal_unit_icon($key)]];
    $p[] = ['kind' => 'text', 'k' => 'Impuls', 'title' => 'Worum es heute ging', 'text' => $u['impuls']];
    foreach (array_chunk($u['ex'], 2) as $i => $chunk) {
        $p[] = ['kind' => 'ex', 'k' => 'Deine Übungsabfolge' . (count($u['ex']) > 2 ? ' · ' . ($i + 1) . '/' . (int)ceil(count($u['ex']) / 2) : ''), 'start' => $i * 2, 'ex' => $chunk];
    }
    $p[] = ['kind' => 'text', 'k' => 'Breathwork', 'title' => $u['breath'][0], 'text' => $u['breath'][1]];
    $p[] = ['kind' => 'text', 'k' => 'Meditation', 'title' => $u['med'][0], 'text' => $u['med'][1]];
    $p[] = ['kind' => 'journal', 'k' => 'Für dich', 'home' => $u['home'], 'journal' => $u['journal'], 'close' => $u['close']];
    return $p;
}

/** Wie viele Seiten in der Vorschau (erstes Drittel, mindestens 2). */
function natal_workbook_preview_count(int $pages): int
{
    return max(2, (int)ceil($pages / 3));
}

/** Seiten als HTML (Mitgliederbereich, Verwaltung). $limit = sichtbare Seiten (Rest gesperrt). */
function natal_workbook_html(string $key, int $limit = 99, string $locked = ''): string
{
    $h = 'forma_h';
    $pages = natal_workbook_pages($key);
    $n = count($pages);
    $o = '<div class="wb" data-n="' . min($n, $limit) . '" data-total="' . $n . '">';
    foreach ($pages as $i => $pg) {
        if ($i >= $limit) break;
        $o .= '<article class="wbp' . ($i === 0 ? ' on' : '') . '" data-i="' . $i . '">';
        if ($pg['kind'] === 'cover') {
            $o .= '<div class="wbc">' . natal_icon($pg['icon'], 84) . '<p class="k">' . $h($pg['label']) . '</p><h3>' . $h($pg['title']) . '</h3><p class="mut">' . $h($pg['theme']) . '</p>'
                . '<div class="merk"><p class="k">Das nimmst du mit</p><ol>' . implode('', array_map(fn($x) => '<li>' . $h($x) . '</li>', $pg['merk'])) . '</ol></div></div>';
        } elseif ($pg['kind'] === 'text') {
            $o .= '<p class="k">' . $h($pg['k']) . '</p><h3>' . $h($pg['title']) . '</h3>' . implode('', array_map(fn($x) => '<p>' . nl2br($h($x)) . '</p>', explode("\n\n", $pg['text'])));
        } elseif ($pg['kind'] === 'ex') {
            $o .= '<p class="k">' . $h($pg['k']) . '</p>';
            foreach ($pg['ex'] as $j => [$name, $dose, $how, $tip]) {
                $o .= '<div class="wbe"><span class="nr">' . ($pg['start'] + $j + 1) . '</span><div><h4>' . $h($name) . '</h4><p class="dose">' . $h($dose) . '</p><p>' . $h($how) . '</p>' . ($tip !== '' ? '<p class="tip">' . $h($tip) . '</p>' : '') . '</div></div>';
            }
        } else {
            $o .= '<p class="k">' . $h($pg['k']) . '</p><h3>Für zu Hause</h3><p>' . $h($pg['home']) . '</p><h3 style="margin-top:1.4rem">Dein Journal</h3>';
            foreach ($pg['journal'] as $q) $o .= '<div class="jq"><p>' . $h($q) . '</p><span></span><span></span><span></span></div>';
            $o .= '<p class="close">' . $h($pg['close']) . '</p>';
        }
        $o .= '</article>';
    }
    $o .= '<div class="wbnav"><button type="button" class="wbprev" aria-label="Zurück">‹</button><span class="wbnum"></span><button type="button" class="wbnext" aria-label="Weiter">›</button></div>';
    if ($limit < $n && $locked !== '') $o .= '<p class="wblock" hidden>' . $locked . '</p>';
    return $o . '</div>';
}

/** CSS + JS fürs Workbook am Bildschirm. */
function natal_workbook_assets(): string
{
    return <<<'H'
<style>
.wb{position:relative;background:#fff;border-radius:22px;box-shadow:0 1px 0 var(--line),0 18px 40px -28px rgba(74,58,65,.45);padding:clamp(1.2rem,4vw,2.4rem);min-height:420px;display:flex;flex-direction:column}
.wbp{display:none}.wbp.on{display:block;animation:wbin .35s ease}
@keyframes wbin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}
.wbp h3{font-family:var(--serif);font-weight:500;font-size:1.9rem;line-height:1.1;margin:.3rem 0 .8rem}
.wbp h4{font-family:var(--serif);font-weight:500;font-size:1.4rem;margin:0}
.wbp p{margin:.6rem 0 0}
.wbc{text-align:center}.wbc svg{margin:0 auto .6rem;display:block}.wbc h3{font-size:2.4rem}
.merk{background:var(--bg);border-radius:18px;padding:1rem 1.2rem;margin-top:1.4rem;text-align:left}.merk ol{margin:.5rem 0 0;padding-left:1.2rem}.merk li{margin:.35rem 0}
.wbe{display:flex;gap:1rem;padding:1rem 0;border-top:1px solid var(--line)}.wbe:first-of-type{border-top:0}
.wbe .nr{flex:0 0 2.2rem;height:2.2rem;border-radius:50%;background:var(--blush);color:var(--plum);display:flex;align-items:center;justify-content:center;font-family:var(--serif);font-size:1.3rem}
.wbe .dose{color:var(--plum);font-size:.9rem;margin-top:.1rem}.wbe .tip{font-size:.88rem;color:var(--muted);font-style:italic}
.jq{margin-top:1rem}.jq p{font-weight:500}.jq span{display:block;border-bottom:1px solid var(--line);height:2rem}
.close{font-family:var(--serif);font-style:italic;font-size:1.35rem;color:var(--plum);margin-top:1.6rem!important}
.wbnav{display:flex;align-items:center;justify-content:center;gap:1rem;margin-top:auto;padding-top:1.4rem}
.wbnav button{width:2.6rem;height:2.6rem;border-radius:50%;border:1px solid var(--line);background:var(--card);font-size:1.4rem;color:var(--plum);cursor:pointer}
.wbnav button:disabled{opacity:.35;cursor:default}.wbnum{font-size:.88rem;color:var(--muted);min-width:5em;text-align:center}
.wblock{background:var(--blush);border-radius:16px;padding:1rem 1.2rem;margin-top:1rem;text-align:center;font-size:.95rem}
.wb.locked .wbp,.wb.locked .wbnav{user-select:none;-webkit-user-select:none}
</style>
<script>
function Workbook(el){const ps=[...el.querySelectorAll('.wbp')],n=ps.length,tot=+el.dataset.total,pv=el.querySelector('.wbprev'),nx=el.querySelector('.wbnext'),num=el.querySelector('.wbnum'),lk=el.querySelector('.wblock');let i=0;
const show=()=>{ps.forEach((p,j)=>p.classList.toggle('on',j===i));const bx=el.closest('.box');if(bx)bx.scrollTo({top:0,behavior:'smooth'});pv.disabled=i===0;nx.disabled=i>=n-1&&!lk;num.textContent='Seite '+(i+1)+' von '+tot;if(lk)lk.hidden=i<n-1;};
pv.onclick=()=>{if(i>0){i--;show();}};nx.onclick=()=>{if(i<n-1){i++;show();}else if(lk){lk.hidden=false;lk.animate([{transform:'scale(.97)'},{transform:'none'}],250);}};
let x0=null;el.addEventListener('touchstart',e=>x0=e.touches[0].clientX,{passive:true});el.addEventListener('touchend',e=>{if(x0===null)return;const d=e.changedTouches[0].clientX-x0;if(Math.abs(d)>50)(d<0?nx:pv).click();x0=null;});
show();}
</script>
H;
}

/** Workbook als PDF (A4, Movecia-Design). */
function natal_workbook_pdf(string $key, string $name = '', string $date = ''): string
{
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
    $t = fn(string $s) => iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
    $pages = natal_workbook_pages($key);
    $pdf = new FPDF('P', 'mm', 'A4');
    $fd = __DIR__ . '/vendor/fpdf/font/';
    $pdf->AddFont('Inter', '', 'Inter-Regular.json', $fd);
    $pdf->AddFont('Inter', 'B', 'Inter-SemiBold.json', $fd);
    $pdf->AddFont('Corm', '', 'Cormorant-Medium.json', $fd);
    $pdf->AddFont('Corm', 'I', 'Cormorant-Italic.json', $fd);
    $pdf->SetTitle($t('Workbook · ' . natal_unit_label($key)));
    $pdf->SetAuthor('Movecia · Vivien Malonga');
    $pdf->SetMargins(22, 22, 22);
    $pdf->SetAutoPageBreak(true, 22);
    $ink = [46, 37, 41]; $plum = [125, 94, 104]; $soft = [125, 112, 118]; $bg = [242, 236, 228]; $blush = [234, 223, 222]; $line = [221, 210, 198];
    $img = dirname(__DIR__) . '/img/movecia/';
    $total = count($pages);
    $head = function (int $nr) use ($pdf, $t, $soft, $img, $key, $total, $line) {
        $pdf->AddPage();
        if (is_file($img . 'movecia-zeichen-1024.png')) $pdf->Image($img . 'movecia-zeichen-1024.png', 22, 12, 7);
        $pdf->SetFont('Corm', '', 13); $pdf->SetTextColor(...$soft);
        $pdf->SetXY(31, 12.6); $pdf->Cell(80, 6, $t('Movecia'));
        $pdf->SetFont('Inter', '', 7.5);
        $pdf->SetXY(100, 13); $pdf->Cell(88, 6, $t(natal_unit_label($key)), 0, 0, 'R');
        $pdf->SetDrawColor(...$line); $pdf->SetLineWidth(.2); $pdf->Line(22, 21, 188, 21);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetXY(22, 283); $pdf->Cell(166, 5, $t('Seite ' . $nr . ' von ' . $total . ' · nur für dich · moveciamovement.com'), 0, 0, 'C');
        $pdf->SetAutoPageBreak(true, 22);
        $pdf->SetXY(22, 30);
    };
    $kicker = function (string $s) use ($pdf, $t, $plum) { $pdf->SetFont('Inter', 'B', 7.5); $pdf->SetTextColor(...$plum); $pdf->Cell(0, 5, $t(mb_strtoupper($s)), 0, 1); $pdf->Ln(1); };
    $title = function (string $s, float $size = 26) use ($pdf, $t, $ink) { $pdf->SetFont('Corm', '', $size); $pdf->SetTextColor(...$ink); $pdf->MultiCell(0, $size * .45, $t($s)); $pdf->Ln(3); };
    $body = function (string $s) use ($pdf, $t, $ink) { $pdf->SetFont('Inter', '', 10.5); $pdf->SetTextColor(...$ink); foreach (explode("\n\n", $s) as $par) { $pdf->MultiCell(0, 5.6, $t($par), 0, 'L'); $pdf->Ln(2.6); } };
    // freie Zeilen für eigene Notizen, wenn unten noch Platz ist
    $notes = function () use ($pdf, $t, $plum, $line) {
        $y = $pdf->GetY() + 8;
        if ($y > 230) return;
        $pdf->SetXY(22, $y); $pdf->SetFont('Inter', 'B', 7.5); $pdf->SetTextColor(...$plum); $pdf->Cell(0, 5, $t('MEINE NOTIZEN'), 0, 1);
        $pdf->SetDrawColor(...$line);
        for ($yy = $y + 14; $yy < 272; $yy += 9) $pdf->Line(22, $yy, 188, $yy);
    };
    foreach ($pages as $i => $pg) {
        $head($i + 1);
        if ($pg['kind'] === 'cover') {
            $pdf->SetFillColor(...$bg); $pdf->Rect(0, 24, 210, 120, 'F');
            $ic = $img . 'icons/' . $pg['icon'] . '.png';
            if (is_file($ic)) $pdf->Image($ic, 90, 36, 30);
            $pdf->SetXY(22, 72); $pdf->SetFont('Inter', 'B', 8); $pdf->SetTextColor(...$plum); $pdf->Cell(166, 5, $t(mb_strtoupper($pg['label'] === '' ? '' : 'Workbook · ' . explode(' · ', $pg['label'])[0])), 0, 1, 'C');
            $pdf->SetFont('Corm', '', 34); $pdf->SetTextColor(...$ink); $pdf->SetX(22); $pdf->MultiCell(166, 14, $t($pg['title']), 0, 'C');
            $pdf->SetFont('Corm', 'I', 16); $pdf->SetTextColor(...$plum); $pdf->SetX(32); $pdf->MultiCell(146, 7, $t($pg['theme']), 0, 'C');
            if ($name !== '' || $date !== '') { $pdf->Ln(3); $pdf->SetFont('Inter', '', 9); $pdf->SetTextColor(...$soft); $pdf->Cell(0, 5, $t(trim(($name !== '' ? 'für ' . $name : '') . ($date !== '' ? ' · ' . $date : ''), ' ·')), 0, 1, 'C'); }
            $pdf->SetY(156);
            $kicker('Das nimmst du mit');
            foreach ($pg['merk'] as $j => $m) {
                $y = $pdf->GetY();
                $pdf->SetFillColor(...$blush); $pdf->SetDrawColor(...$blush);
                $pdf->SetFont('Corm', '', 18); $pdf->SetTextColor(...$plum); $pdf->SetXY(22, $y); $pdf->Cell(10, 8, (string)($j + 1));
                $pdf->SetFont('Inter', '', 11); $pdf->SetTextColor(...$ink); $pdf->SetXY(33, $y + 1); $pdf->MultiCell(155, 6, $t($m), 0, 'L'); $pdf->Ln(4);
            }
        } elseif ($pg['kind'] === 'text') {
            $kicker($pg['k']); $title($pg['title']); $body($pg['text']); $notes();
        } elseif ($pg['kind'] === 'ex') {
            $kicker($pg['k']);
            foreach ($pg['ex'] as $j => [$nm, $dose, $how, $tip]) {
                $y = $pdf->GetY() + 2;
                $pdf->SetFillColor(...$blush); $pdf->Rect(22, $y, 11, 11, 'F');
                $pdf->SetFont('Corm', '', 18); $pdf->SetTextColor(...$plum); $pdf->SetXY(22, $y + 1.2); $pdf->Cell(11, 8, (string)($pg['start'] + $j + 1), 0, 0, 'C');
                $pdf->SetXY(38, $y - .5); $pdf->SetFont('Corm', '', 20); $pdf->SetTextColor(...$ink); $pdf->MultiCell(150, 8, $t($nm));
                $pdf->SetX(38); $pdf->SetFont('Inter', 'B', 9); $pdf->SetTextColor(...$plum); $pdf->Cell(150, 5, $t($dose), 0, 1); $pdf->Ln(1);
                $pdf->SetX(38); $pdf->SetFont('Inter', '', 10.3); $pdf->SetTextColor(...$ink); $pdf->MultiCell(150, 5.5, $t($how), 0, 'L');
                if ($tip !== '') { $pdf->Ln(1); $pdf->SetX(38); $pdf->SetFont('Corm', 'I', 13); $pdf->SetTextColor(...$soft); $pdf->MultiCell(150, 5.6, $t($tip)); }
                $pdf->Ln(6);
            }
            $notes();
        } else {
            $kicker($pg['k']); $title('Für zu Hause', 22);
            $pdf->SetFillColor(...$bg); $pdf->SetFont('Inter', '', 10.8); $pdf->SetTextColor(...$ink); $pdf->MultiCell(0, 6, $t($pg['home']), 0, 'L', true); $pdf->Ln(6);
            $title('Dein Journal', 22);
            foreach ($pg['journal'] as $q) {
                $pdf->SetFont('Inter', 'B', 10); $pdf->SetTextColor(...$ink); $pdf->MultiCell(0, 5.4, $t($q)); $pdf->Ln(1);
                $pdf->SetDrawColor(...$line);
                for ($l = 0; $l < 4; $l++) { $y = $pdf->GetY() + 8; $pdf->Line(22, $y, 188, $y); $pdf->SetY($y); }
                $pdf->Ln(6);
            }
            $pdf->SetFont('Corm', 'I', 15); $pdf->SetTextColor(...$plum); $pdf->MultiCell(0, 7, $t($pg['close']), 0, 'C');
        }
    }
    return $pdf->Output('S');
}

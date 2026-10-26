<?php
// Workbook je Einheit (Vivien, 24.10.2026): gleiche Seiten am Bildschirm (Mitgliederbereich) und als PDF.
// Inhalte aus api/natal-units.inc.php. In den ersten 14 Tagen nach der Buchung nur das erste Drittel als Bildschirm-Vorschau.
declare(strict_types=1);
require_once __DIR__ . '/natal-units.inc.php';
require_once __DIR__ . '/natal-icons.inc.php';
require_once __DIR__ . '/movecia-icons.inc.php';

/** Icon passend zur Einheit. */
function natal_unit_icon(string $key): string
{
    $m = ['prae1' => 'kugel', 'prae2' => 'knospe', 'prae3' => 'feder', 'prae4' => 'mond', 'prae5' => 'sterne', 'prae6' => 'wolke',
        'post1' => 'herz', 'post2' => 'knospe', 'post3' => 'kugel', 'post4' => 'schuh', 'post5' => 'rassel', 'post6' => 'mond', 'post7' => 'wagen', 'post8' => 'mobile'];
    return $m[$key] ?? 'herz';
}

/** Zusatzkapitel „Gut zu wissen“ je Einheit (Vivien, 26.10.2026: Workbooks sehr umfangreich), aus api/seed/workbooks/content/natal-wissen.de.json. */
function natal_wissen(string $key): ?array
{
    static $all = null;
    if ($all === null) $all = json_decode((string)@file_get_contents(__DIR__ . '/seed/workbooks/content/natal-wissen.de.json'), true) ?: [];
    return $all[$key] ?? null;
}

/** Seiten eines Workbooks: [['kind' => cover|text|ex|calm|wissen|journal, …]] */
function natal_workbook_pages(string $key): array
{
    $u = natal_units()[$key] ?? null;
    if (!$u) return [];
    $p = [['kind' => 'cover', 'label' => natal_unit_label($key), 'title' => $u['title'], 'theme' => $u['theme'], 'merk' => $u['merk'], 'icon' => natal_unit_icon($key)]];
    $p[] = ['kind' => 'text', 'icon' => 'impuls', 'k' => 'Impuls', 'title' => 'Worum es heute ging', 'text' => $u['impuls']];
    $n = (int)ceil(count($u['ex']) / 2);
    foreach (array_chunk($u['ex'], 2) as $i => $chunk) {
        $p[] = ['kind' => 'ex', 'icon' => 'uebung', 'k' => 'Deine Übungsabfolge' . ($n > 1 ? ' · ' . ($i + 1) . '/' . $n : ''), 'title' => $i === 0 ? 'So bewegen wir uns' : 'Weiter geht’s', 'start' => $i * 2, 'ex' => $chunk, 'last' => $i === $n - 1];
    }
    $p[] = ['kind' => 'calm', 'breath' => $u['breath'], 'med' => $u['med']];
    if ($w = natal_wissen($key)) $p[] = ['kind' => 'wissen', 'icon' => $w['icon'] ?? 'impuls', 'k' => 'Gut zu wissen', 'title' => $w['title'] ?? 'Gut zu wissen', 'intro' => $w['intro'] ?? '', 'blocks' => $w['blocks'] ?? []];
    $p[] = ['kind' => 'journal', 'icon' => 'home', 'k' => 'Für dich', 'home' => $u['home'], 'journal' => $u['journal'], 'close' => $u['close']];
    return $p;
}

/** Wie viele Seiten in der Vorschau (erstes Drittel, mindestens 2). */
function natal_workbook_preview_count(int $pages): int
{
    return max(2, (int)ceil($pages / 3));
}

/** Ein Inhaltsblock (Format tools/workbooks/SCHEMA.md) als HTML für den Bildschirm. */
function mv_wb_block(array $b): string
{
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $ln = fn(int $n) => str_repeat('<span class="ln"></span>', $n);
    $li = fn(array $xs) => implode('', array_map(fn($x) => '<li>' . $h($x) . '</li>', $xs));
    switch ($b['t'] ?? '') {
        case 'p': return '<p>' . $h($b['text']) . '</p>';
        case 'h': return '<h4 class="wsh">' . $h($b['text']) . '</h4>';
        case 'list': return '<ul class="wl">' . $li($b['items']) . '</ul>';
        case 'steps': return '<ol class="wl">' . $li($b['items']) . '</ol>';
        case 'fact': return '<div class="wfact"><p class="k">' . mv_wb_icon('impuls', 22) . ' Gut zu wissen</p><h4>' . $h($b['title'] ?? '') . '</h4><p>' . $h($b['text'] ?? '') . '</p></div>';
        case 'tip': return '<div class="wtip">' . mv_wb_icon('tipp', 24) . '<div><span>Mein Tipp für dich</span><p>' . $h($b['text']) . '</p></div></div>';
        case 'quote': return '<p class="wquote">' . $h($b['text']) . '</p>';
        case 'exercise': return '<div class="wex"><h4>' . mv_wb_icon('uebung', 26) . $h($b['title'] ?? '') . (!empty($b['time']) ? ' <span class="dose">' . $h($b['time']) . '</span>' : '') . '</h4>' . (!empty($b['intro']) ? '<p>' . $h($b['intro']) . '</p>' : '') . (!empty($b['steps']) ? '<ol class="wl">' . $li($b['steps']) . '</ol>' : '') . '</div>';
        case 'prompts': return '<div class="wpr">' . (!empty($b['title']) ? '<h4>' . mv_wb_icon('journal', 24) . $h($b['title']) . '</h4>' : '') . implode('', array_map(fn($q) => '<div class="jq"><p>' . $h($q) . '</p>' . $ln(2) . '</div>', $b['items'])) . '</div>';
        case 'checklist': return '<div class="wfact"><h4>' . mv_wb_icon('haken', 22) . ' ' . $h($b['title'] ?? '') . '</h4><ul class="wchk">' . $li($b['items']) . '</ul></div>';
        case 'table':
            $cols = $b['cols']; $rows = is_array($b['rows']) ? $b['rows'] : array_fill(0, min(4, (int)$b['rows']), array_fill(0, count($cols), ''));
            return '<div class="wtab"><h4>' . $h($b['title'] ?? '') . '</h4><div class="scr"><table><tr>' . implode('', array_map(fn($c) => '<th>' . $h($c) . '</th>', $cols)) . '</tr>'
                . implode('', array_map(fn($r) => '<tr>' . implode('', array_map(fn($c) => '<td>' . $h($c) . '</td>', array_pad(array_slice($r, 0, count($cols)), count($cols), ''))) . '</tr>', $rows)) . '</table></div></div>';
        case 'tracker': return '<div class="wfact"><h4>' . mv_wb_icon('kalender', 22) . ' ' . $h($b['title'] ?? '') . '</h4><div class="scr"><table class="wtr"><tr><th></th><th>Mo</th><th>Di</th><th>Mi</th><th>Do</th><th>Fr</th><th>Sa</th><th>So</th></tr>' . implode('', array_map(fn($x) => '<tr><td>' . $h($x) . '</td>' . str_repeat('<td><i></i></td>', 7) . '</tr>', $b['items'])) . '</table></div></div>';
        case 'scale': return '<div class="wfact"><h4>' . mv_wb_icon('stimmung', 22) . ' ' . $h($b['title'] ?? '') . '</h4>' . implode('', array_map(fn($x) => '<div class="wsc"><span>' . $h($x) . '</span><span class="d">' . implode('', array_map(fn($i) => '<i>' . $i . '</i>', range(1, 10))) . '</span></div>', $b['items'])) . '</div>';
        case 'breath': return '<div class="wfact"><h4>' . mv_wb_icon('atem', 22) . ' ' . $h($b['title'] ?? '') . (!empty($b['pattern']) ? ' · ' . $h($b['pattern']) : '') . '</h4><p>' . $h($b['text'] ?? '') . '</p></div>';
        case 'notes': return '<div class="wpr"><h4>' . mv_wb_icon('journal', 24) . $h($b['title'] ?? 'Meine Notizen') . '</h4>' . $ln(3) . '</div>';
    }
    return '';
}

/** Seiten als HTML (Mitgliederbereich, Verwaltung). $limit = sichtbare Seiten (Rest gesperrt). */
function natal_workbook_html(string $key, int $limit = 99, string $locked = ''): string
{
    $h = 'forma_h';
    $pages = natal_workbook_pages($key);
    $n = count($pages);
    $head = fn(string $ic, string $k, string $t) => '<div class="wsh2"><span class="badge">' . mv_wb_icon($ic, 34) . '</span><div><p class="k">' . $h($k) . '</p><h3>' . $h($t) . '</h3></div></div>';
    $paras = fn(string $t) => implode('', array_map(fn($x) => '<p>' . nl2br($h($x)) . '</p>', explode("\n\n", $t)));
    $o = '<div class="wb" data-n="' . min($n, $limit) . '" data-total="' . $n . '">';
    foreach ($pages as $i => $pg) {
        if ($i >= $limit) break;
        $o .= '<article class="wbp' . ($i === 0 ? ' on' : '') . '" data-i="' . $i . '">';
        if ($pg['kind'] === 'cover') {
            $o .= '<div class="wbc"><span class="blob"></span><span class="sp s1">✦</span><span class="sp s2">✧</span><span class="sp s3">♡</span><div class="ci">' . natal_icon($pg['icon'], 96) . '</div><p class="tape">Workbook · ' . $h(explode(' · ', $pg['label'])[0]) . '</p><h3>' . $h($pg['title']) . '</h3><p class="mut">' . $h($pg['theme']) . '</p>'
                . '<div class="merk"><p class="k">Das nimmst du mit</p><ul>' . implode('', array_map(fn($x) => '<li>' . mv_wb_icon('merk', 20) . '<span>' . $h($x) . '</span></li>', $pg['merk'])) . '</ul></div></div>';
        } elseif ($pg['kind'] === 'text') {
            $o .= $head($pg['icon'], $pg['k'], $pg['title']) . $paras($pg['text']) . '<div class="wpr"><h4>' . mv_wb_icon('journal', 24) . 'Meine Gedanken dazu</h4><span class="ln"></span><span class="ln"></span></div>';
        } elseif ($pg['kind'] === 'ex') {
            $o .= $head($pg['icon'], $pg['k'], $pg['title']);
            foreach ($pg['ex'] as $j => [$name, $dose, $how, $tip]) {
                $o .= '<div class="wbe"><span class="pi">' . mv_wb_icon(mv_wb_pose($name), 44) . '<b>' . ($pg['start'] + $j + 1) . '</b></span><div><h4>' . $h($name) . '</h4><p class="dose">' . mv_wb_icon('uhr', 14) . $h($dose) . '</p><p>' . $h($how) . '</p>' . ($tip !== '' ? '<p class="tip">' . mv_wb_icon('tipp', 16) . '<span>' . $h($tip) . '</span></p>' : '') . '</div></div>';
            }
            if ($pg['last']) $o .= '<p class="done">Geschafft? <i></i><i></i><i></i> <em>ein Kreis pro Durchgang</em></p>';
        } elseif ($pg['kind'] === 'calm') {
            $o .= $head('atem', 'Breathwork', $pg['breath'][0]) . '<div class="ring"><span class="r1"></span><span class="r2"></span><span class="r3"></span><b>ein · aus</b></div>' . $paras($pg['breath'][1])
                . '<div style="margin-top:1.6rem">' . $head('meditation', 'Meditation', $pg['med'][0]) . '</div><div class="medt">' . $paras($pg['med'][1]) . '</div>';
        } elseif ($pg['kind'] === 'wissen') {
            $o .= $head($pg['icon'], $pg['k'], $pg['title']) . ($pg['intro'] !== '' ? '<p class="intro">' . $h($pg['intro']) . '</p>' : '') . implode('', array_map('mv_wb_block', $pg['blocks']));
        } else {
            $o .= $head('home', $pg['k'], 'Für zu Hause') . '<p class="homet">' . $h($pg['home']) . '</p><div class="week">' . implode('', array_map(fn($d) => '<span><b>' . $d . '</b><i></i><i></i><i></i></span>', ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'])) . '</div>'
                . '<div class="wpr"><h4>' . mv_wb_icon('journal', 24) . 'Dein Journal</h4>';
            foreach ($pg['journal'] as $q) $o .= '<div class="jq"><p>' . $h($q) . '</p><span class="ln"></span><span class="ln"></span><span class="ln"></span></div>';
            $o .= '</div><p class="close">' . mv_wb_icon('zweig', 28) . '<span>' . $h($pg['close']) . '</span></p>';
        }
        $o .= '</article>';
    }
    $o .= '<div class="wbnav"><button type="button" class="wbprev" aria-label="Zurück">‹</button><span class="wbnum"></span><button type="button" class="wbnext" aria-label="Weiter">›</button></div>';
    if ($limit < $n && $locked !== '') $o .= '<p class="wblock" hidden>' . $locked . '</p>';
    return $o . '</div>';
}

/** CSS + JS fürs Workbook am Bildschirm (Design wie die PDFs, Vivien 26.10.2026). */
function natal_workbook_assets(): string
{
    return <<<'H'
<style>
.wb{position:relative;background:#FFFDFB;border-radius:22px;box-shadow:0 1px 0 var(--line),0 18px 40px -28px rgba(74,58,65,.45);padding:clamp(1.2rem,4vw,2.2rem);min-height:420px;display:flex;flex-direction:column;overflow:hidden}
.wb::before{content:"";position:absolute;right:-50px;bottom:-60px;width:180px;height:180px;border-radius:50%;background:var(--blush);opacity:.5;pointer-events:none}
.wbp{display:none;position:relative}.wbp.on{display:block;animation:wbin .35s ease}
@keyframes wbin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}
.wbp h3{font-family:var(--serif);font-weight:500;font-size:1.75rem;line-height:1.1;margin:0}
.wbp h4{font-family:var(--serif);font-weight:600;font-size:1.25rem;line-height:1.15;margin:0;display:flex;align-items:center;gap:.4rem}
.wbp p{margin:.6rem 0 0}
.wbp .k{font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:var(--plum);font-weight:600;margin:0;display:flex;align-items:center;gap:.35rem}
.wsh2{display:flex;align-items:center;gap:.9rem;margin-bottom:1rem;padding-bottom:.8rem;border-bottom:1.5px dashed var(--line)}
.wsh2 .badge{flex:0 0 58px;height:58px;border-radius:46% 54% 50% 50%/55% 45% 55% 45%;background:var(--bg);display:flex;align-items:center;justify-content:center;border:1.5px dashed #D9B8B4}
.wbc{position:relative;text-align:center;padding-top:.6rem}
.wbc .blob{position:absolute;left:50%;top:0;width:200px;height:170px;transform:translateX(-50%);background:var(--peach);border-radius:58% 42% 50% 50%/48% 56% 44% 52%;opacity:.7}
.wbc>*{position:relative}.wbc .sp{position:absolute;color:var(--plum)}.wbc .s1{left:16%;top:1rem}.wbc .s2{right:20%;top:3rem;color:#D9B8B4;font-size:1.3rem}.wbc .s3{left:24%;top:7rem;color:#D9B8B4}
.wbc .ci{display:flex;justify-content:center;padding-top:1.2rem}
.wbc .tape{display:inline-block;margin:1.2rem 0 .4rem;background:var(--blush);padding:.3rem .9rem;font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;color:var(--plum);font-weight:600;transform:rotate(-2deg);border-radius:3px}
.wbc h3{font-size:2.3rem}
.wbc .mut{font-family:var(--serif);font-style:italic;font-size:1.15rem;color:var(--plum)}
.merk{background:var(--bg);border-radius:18px;padding:1rem 1.1rem;margin-top:1.3rem;text-align:left}
.merk ul{list-style:none;margin:.5rem 0 0;padding:0;display:grid;gap:.55rem}.merk li{display:flex;gap:.55rem;align-items:flex-start}.merk li svg{flex:0 0 20px;margin-top:.15rem}
.wbe{display:flex;gap:.9rem;padding:.9rem 0;border-top:1.5px dashed var(--line)}.wbe:first-of-type{border-top:0}
.wbe .pi{position:relative;flex:0 0 62px;height:62px;border-radius:50%;background:var(--blush);display:flex;align-items:center;justify-content:center}
.wbe .pi b{position:absolute;top:-4px;right:-4px;width:24px;height:24px;border-radius:50%;background:var(--plum);color:#fff;font-family:var(--serif);font-size:.95rem;display:flex;align-items:center;justify-content:center;border:2px solid #FFFDFB}
.dose{display:inline-flex;align-items:center;gap:.3rem;background:var(--bg);color:var(--plum);font-size:.8rem;font-weight:600;border-radius:999px;padding:.12rem .7rem .12rem .4rem}
.wbe .tip{display:flex;gap:.45rem;align-items:flex-start;background:#FBF4F2;border:1px solid var(--blush);border-radius:12px;padding:.5rem .7rem;font-family:var(--serif);font-style:italic;font-size:1.05rem;color:var(--deep)}
.done{display:flex;align-items:center;gap:.4rem;font-size:.85rem;color:var(--muted);margin-top:1rem!important}.done i{width:18px;height:18px;border-radius:50%;border:1.5px solid #D9B8B4}.done em{font-style:italic}
.ring{position:relative;width:130px;height:130px;margin:.4rem auto .6rem;display:flex;align-items:center;justify-content:center}
.ring span{position:absolute;border-radius:50%}.ring .r1{inset:0;border:1.5px dashed #D9B8B4}.ring .r2{inset:18px;background:var(--bg);border:1.5px solid var(--peach)}.ring .r3{inset:36px;background:var(--peach)}
.ring b{position:relative;font-family:var(--serif);color:var(--plum);font-size:1.05rem;font-weight:500}
@media(prefers-reduced-motion:no-preference){.ring .r3{animation:wbbr 9s ease-in-out infinite}}
@keyframes wbbr{0%,100%{transform:scale(.82)}45%{transform:scale(1.14)}}
.medt p{font-family:var(--serif);font-size:1.15rem;line-height:1.5}
.intro{font-family:var(--serif);font-style:italic;font-size:1.15rem;color:var(--plum)}
.wfact{background:var(--bg);border-radius:16px;padding:.9rem 1rem;margin-top:.9rem}
.wtip{display:flex;gap:.6rem;background:#FBF4F2;border:1px solid var(--blush);border-radius:16px;padding:.8rem 1rem;margin-top:.9rem}.wtip svg{flex:0 0 24px}
.wtip span{font-size:.72rem;color:var(--plum);letter-spacing:.06em}.wtip p{margin:.1rem 0 0;font-family:var(--serif);font-style:italic;font-size:1.08rem;color:var(--deep)}
.wquote{font-family:var(--serif);font-style:italic;font-size:1.45rem;color:var(--plum);text-align:center;margin:1.4rem .4rem!important;line-height:1.25}
.wex{border:1px solid var(--line);border-radius:16px;padding:.9rem 1rem;margin-top:.9rem;background:#fff}
.wsh{margin-top:1.2rem!important}
.wl{padding-left:1.2rem;margin:.5rem 0 0}.wl li{margin:.3rem 0}
.wchk{list-style:none;padding:0;margin:.5rem 0 0}.wchk li{display:flex;gap:.5rem;margin:.35rem 0}.wchk li::before{content:"";flex:0 0 16px;height:16px;border:1.5px solid var(--plum);border-radius:5px;margin-top:.2rem}
.wtab{margin-top:1rem}.scr{overflow-x:auto}.wtab table,.wtr{width:100%;border-collapse:collapse;font-size:.88rem;margin-top:.4rem}
.wtab th{background:var(--blush);text-align:left;padding:.4rem .5rem;font-weight:600}.wtab td{border-bottom:1px dotted #D9B8B4;height:2rem;padding:.3rem .5rem}
.wtr th{font-size:.72rem;color:var(--plum)}.wtr td{padding:.3rem;border-bottom:1px dotted var(--line);text-align:center}.wtr td:first-child{text-align:left}.wtr i{display:inline-block;width:16px;height:16px;border-radius:50%;border:1.5px solid #D9B8B4}
.wsc{display:flex;flex-wrap:wrap;gap:.4rem .8rem;align-items:center;margin:.5rem 0}.wsc .d{display:flex;gap:4px}.wsc i{width:22px;height:22px;border-radius:50%;border:1px solid #D9B8B4;font-style:normal;font-size:.68rem;color:var(--muted);display:flex;align-items:center;justify-content:center}
.wpr{margin-top:1.2rem}.jq{margin-top:.8rem}.jq p{font-weight:500}.ln{display:block;border-bottom:1.5px dotted #D9B8B4;height:2rem}
.homet{background:var(--bg);border-radius:16px;padding:.8rem 1rem;margin-top:0!important}
.week{display:grid;grid-template-columns:repeat(7,1fr);gap:.3rem;margin-top:.8rem}.week span{display:flex;flex-direction:column;align-items:center;gap:.25rem;border:1px solid var(--line);border-radius:12px;padding:.4rem 0}.week b{font-size:.72rem;color:var(--plum)}.week i{width:12px;height:12px;border-radius:50%;border:1.5px solid #D9B8B4}
.close{display:flex;gap:.6rem;align-items:center;font-family:var(--serif);font-style:italic;font-size:1.25rem;color:var(--plum);margin-top:1.4rem!important}.close svg{flex:0 0 28px}
.wbnav{display:flex;align-items:center;justify-content:center;gap:1rem;margin-top:auto;padding-top:1.4rem;position:relative}
.wbnav button{width:2.6rem;height:2.6rem;border-radius:50%;border:1px solid var(--line);background:var(--card);font-size:1.4rem;color:var(--plum);cursor:pointer}
.wbnav button:disabled{opacity:.35;cursor:default}.wbnum{font-size:.88rem;color:var(--muted);min-width:5em;text-align:center}
.wblock{background:var(--blush);border-radius:16px;padding:1rem 1.2rem;margin-top:1rem;text-align:center;font-size:.95rem;position:relative}
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
    // Neues Design (26.10.2026): fertig gerenderte PDFs aus tools/workbooks/build.py
    $static = __DIR__ . '/seed/workbooks/pdf/natal-' . preg_replace('/[^a-z0-9]/', '', $key) . '.de.pdf';
    if (is_file($static)) return (string)file_get_contents($static);
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

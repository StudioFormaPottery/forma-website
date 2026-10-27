<?php
// Wintersaison 2026/27 (Vivien, 27.10.2026): alles für die Hotels an einem Ort.
//   FORMA   ?view=winter      Hotel-Flyer + Buchungsseite /urlaub/
//   Movecia ?view=winter_mm   Personal-Fitness-Flyer + Buchungsseite moveciamovement.com/personal-training
// Flyer-Dateien: mitgelieferte im Repo (WINTER_FILES) plus eigene Uploads in ../forma-data/winter/<forma|movecia>/.
// Eingebunden aus verwaltung/index.php (nur angemeldet, CSRF schon geprüft).
declare(strict_types=1);

const WINTER_SEASON = 'Wintersaison 2026/27';
const WINTER_FILES = [
    'forma' => [],
    'movecia' => [
        ['/img/movecia/flyer/Movecia_PersonalTraining_A4.pdf', 'Flyer A4 (PDF, zum Drucken)'],
        ['/img/movecia/flyer/Movecia_PersonalTraining_A5.pdf', 'Flyer A5 (PDF, zum Drucken)'],
        ['/img/movecia/flyer/Movecia_PersonalTraining_Instagram_4x5.png', 'Instagram 4:5 (PNG)'],
        ['/img/movecia/flyer/Movecia_PersonalTraining_WhatsApp_9x16.png', 'WhatsApp-Status 9:16 (PNG)'],
        ['/img/movecia/flyer/Movecia_PersonalTraining_Flyer.zip', 'Alles zusammen (ZIP)'],
    ],
];

function winter_dir(string $b): string
{
    $d = forma_data_dir() . '/winter/' . ($b === 'movecia' ? 'movecia' : 'forma');
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

/** Hochgeladene Dateien: [[name, bytes, zeit], …] neueste zuerst. */
function winter_uploads(string $b): array
{
    $out = [];
    foreach (glob(winter_dir($b) . '/*') ?: [] as $f) if (is_file($f)) $out[] = [basename($f), filesize($f), filemtime($f)];
    usort($out, fn($x, $y) => $y[2] <=> $x[2]);
    return $out;
}

$wb = ($_POST['b'] ?? $_GET['b'] ?? '') === 'movecia' || $view === 'winter_mm' ? 'movecia' : 'forma';
$wBack = $self . '?view=' . ($wb === 'movecia' ? 'winter_mm' : 'winter');

if ($action === 'winter_upload') {
    $n = 0;
    $files = $_FILES['f'] ?? null;
    if ($files && is_array($files['name'])) {
        foreach ($files['name'] as $i => $name) {
            if ((int)$files['error'][$i] !== UPLOAD_ERR_OK || (int)$files['size'][$i] > 40 * 1024 * 1024) continue;
            $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'zip'], true)) continue;
            $base = trim(preg_replace('/[^A-Za-z0-9äöüÄÖÜß._ -]+/u', '', pathinfo((string)$name, PATHINFO_FILENAME)) ?? '') ?: 'Flyer';
            $dest = winter_dir($wb) . '/' . mb_substr($base, 0, 80) . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) $n++;
        }
    }
    go($wBack . '#flyer', $n ? ($n === 1 ? 'Datei gespeichert.' : $n . ' Dateien gespeichert.') : 'Es wurde nichts gespeichert. Erlaubt sind PDF, PNG, JPG und ZIP bis 40 MB.');
}
if ($action === 'winter_delete') {
    $f = winter_dir($wb) . '/' . basename((string)($_POST['f'] ?? ''));
    if (is_file($f)) @unlink($f);
    go($wBack . '#flyer', 'Gelöscht.');
}
if (($view === 'winter' || $view === 'winter_mm') && isset($_GET['dl'])) {
    $f = winter_dir($wb) . '/' . basename((string)$_GET['dl']);
    if (!is_file($f)) { http_response_code(404); exit('Nicht gefunden'); }
    $types = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'zip' => 'application/zip'];
    header('Content-Type: ' . ($types[strtolower(pathinfo($f, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    header('Content-Disposition: ' . (isset($_GET['save']) ? 'attachment' : 'inline') . '; filename="' . basename($f) . '"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

if ($view === 'winter' || $view === 'winter_mm') {
    $isMm = $wb === 'movecia';
    if ($isMm) {
        require_once dirname(__DIR__) . '/api/ptb-lib.php';
        ptb_schema($pdo);
        $c = ptb_cfg();
        $base = mm_site() . '/personal-training/';
        $pages = [['Deutsch', $base], ['Englisch', $base . '?lang=en']];
        $facts = [PTB_MINUTES . ' Minuten pro Einheit', forma_euro($c['unit']) . ' pro Einheit allein', forma_euro($c['unit2']) . ' pro Einheit zu zweit', '1–' . PTB_MAX_UNITS . ' Einheiten, Wunschtag und Uhrzeit frei wählbar', 'Gast bestätigt die E-Mail, du bestätigst die Zeiten, dann zahlt der Gast online'];
        $manage = [['?view=training', 'Anfragen & Termine'], ['?view=training#einstellungen', 'Preise & Zeiten'], ['?view=training#test', 'Als Gast testen']];
        $title = 'Personal Fitness für Hotelgäste';
    } else {
        $base = forma_site_url() . '/urlaub/';
        $pages = [['Deutsch', $base], ['Englisch', $base . '?lang=en']];
        $facts = ['Spin & Go an der Drehscheibe (dieselben Termine und freien Plätze wie auf der Startseite)', 'Private Pottery 1:1 an der Drehscheibe, 2 Stunden', 'Gäste bezahlen online, Rechnung kommt automatisch', 'Ein gemeinsamer Flyer auf Deutsch und Englisch für alle Hotels'];
        $manage = [['?view=kalender', 'Kalender & Buchungen'], ['?view=private', 'Private Pottery'], ['?view=tests', 'Testbuchungen']];
        $title = 'Töpfern für Hotelgäste';
    }
    $repo = WINTER_FILES[$wb];
    $ups = winter_uploads($wb);
    render_page(WINTER_SEASON, function () use ($self, $flash, $isMm, $pages, $facts, $manage, $title, $repo, $ups, $wb) {
        $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '"><input type="hidden" name="b" value="' . $wb . '">';
        $acc = $isMm ? '#7D5E68' : '#8C2C1D'; ?>
<style>
.ws h1{margin:.2rem 0 .3rem}
<?php if ($isMm): ?>.ws h1,.ws h2{color:#6B5658}.ws .eyebrow{color:#7D5E68}<?php endif; ?>
.ws .card{margin-top:14px}
.ws h2{font-size:22px;font-weight:500;margin:0 0 .6rem}
.ws .lk{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:12px 0;border-top:1px solid rgba(0,0,0,.08)}
.ws .lk:first-of-type{border-top:0}
.ws .lk code{font-size:16px;word-break:break-all;flex:1 1 260px}
.ws .lk b{min-width:80px}
.ws .bt{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border-radius:999px;border:1px solid <?= $acc ?>;color:<?= $acc ?>;background:#fff;text-decoration:none;font:inherit;font-size:16px;cursor:pointer}
.ws .bt.on{background:<?= $acc ?>;color:#fff}
.ws .qr{display:none;margin-top:10px;width:100%}
.ws .qr.open{display:flex;flex-wrap:wrap;gap:14px;align-items:center}
.ws .qr div{width:180px;height:180px;background:#fff;padding:8px;border-radius:12px}
.ws .qr svg{width:100%;height:100%}
.ws ul.f{margin:0;padding-left:1.1rem;font-size:17px;line-height:1.7}
.ws .fl{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px 14px;padding:12px 0;border-top:1px solid rgba(0,0,0,.08);font-size:17px}
.ws .fl:first-child{border-top:0}
.ws .fl span{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.ws .grid{display:grid;gap:14px}
@media(min-width:900px){.ws .grid{grid-template-columns:1.3fr 1fr}}
</style>
<div class="ws">
    <?php if ($isMm) mm_subnav('winter'); else { ?><p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p><?php } ?>
    <p class="eyebrow">❄️ <?= WINTER_SEASON ?> · <?= $isMm ? 'Movecia' : 'FORMA' ?></p>
    <h1><?= forma_h($title) ?></h1>
    <p class="muted">Buchungsseite und Flyer für die Hotels an einem Ort. Die Seite ist nur über den Link bzw. den QR-Code auf dem Flyer erreichbar, Google zeigt sie nicht an.</p>
    <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>

    <div class="grid">
    <div class="card">
        <h2>Buchungsseite</h2>
        <?php foreach ($pages as $i => [$lab, $url]): ?>
        <div class="lk"><b><?= $lab ?></b><code><?= forma_h($url) ?></code>
            <span style="display:flex;gap:8px;flex-wrap:wrap"><a class="bt on" href="<?= forma_h($url) ?>" target="_blank" rel="noopener">Öffnen</a><button class="bt" type="button" data-copy="<?= forma_h($url) ?>">Link kopieren</button><button class="bt" type="button" data-qr="q<?= $i ?>">QR-Code</button></span>
            <div class="qr" id="q<?= $i ?>"><div data-url="<?= forma_h($url) ?>"></div><a class="bt" href="#" data-save="q<?= $i ?>">QR als Bild speichern</a></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Das Angebot</h2>
        <ul class="f"><?php foreach ($facts as $f): ?><li><?= forma_h($f) ?></li><?php endforeach; ?></ul>
        <p style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px"><?php foreach ($manage as [$q, $l]): ?><a class="bt" href="<?= forma_h($self . $q) ?>"><?= forma_h($l) ?></a><?php endforeach; ?></p>
    </div>
    </div>

    <div class="card" id="flyer">
        <h2>Hotel-Flyer</h2>
        <?php if (!$repo && !$ups): ?><p class="muted">Hier ist noch kein Flyer. Lade ihn unten hoch, dann hast du ihn immer griffbereit, auch am Handy.</p><?php endif; ?>
        <div>
        <?php foreach ($repo as [$path, $lab]): ?>
            <div class="fl"><span><?= forma_h($lab) ?></span><span><?php if (!str_ends_with($path, '.zip')): ?><a class="bt on" href="<?= forma_h($path) ?>" target="_blank" rel="noopener">Ansehen</a><?php endif; ?><a class="bt" href="<?= forma_h($path) ?>" download>Herunterladen</a></span></div>
        <?php endforeach; ?>
        <?php foreach ($ups as [$name, $size, $t]): $u = $self . '?view=' . ($isMm ? 'winter_mm' : 'winter') . '&dl=' . rawurlencode($name); ?>
            <div class="fl"><span><?= forma_h($name) ?> <small class="muted"><?= number_format($size / 1048576, 1, ',', '.') ?> MB · <?= date('d.m.Y', $t) ?></small></span><span><a class="bt on" href="<?= forma_h($u) ?>" target="_blank" rel="noopener">Ansehen</a><a class="bt" href="<?= forma_h($u) ?>&amp;save=1">Herunterladen</a>
                <form method="post" onsubmit="return confirm('Diese Datei löschen?')" style="margin:0"><?= $csrf ?><input type="hidden" name="action" value="winter_delete"><input type="hidden" name="f" value="<?= forma_h($name) ?>"><button class="link small" type="submit">löschen</button></form></span></div>
        <?php endforeach; ?>
        </div>
        <form method="post" enctype="multipart/form-data" style="margin-top:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center"><?= $csrf ?><input type="hidden" name="action" value="winter_upload">
            <input type="file" name="f[]" accept=".pdf,.png,.jpg,.jpeg,.zip" multiple required style="font-size:16px"><button class="bt on" type="submit">Flyer hochladen</button></form>
        <p class="small muted" style="margin-top:6px">PDF, PNG, JPG oder ZIP, mehrere auf einmal möglich. Eine Datei mit gleichem Namen wird ersetzt.</p>
    </div>
</div>
<script src="qrcode.js"></script>
<script>
document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', () => { navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(() => { const t = b.textContent; b.textContent = 'kopiert ✓'; setTimeout(() => b.textContent = t, 1800); }); }));
document.querySelectorAll('[data-qr]').forEach(b => b.addEventListener('click', () => {
  const w = document.getElementById(b.dataset.qr), box = w.querySelector('[data-url]');
  if (!box.innerHTML && window.qrcode) { const q = qrcode(0, 'M'); q.addData(box.dataset.url); q.make(); box.innerHTML = q.createSvgTag(6, 2); }
  w.classList.toggle('open');
}));
document.querySelectorAll('[data-save]').forEach(a => a.addEventListener('click', e => {
  e.preventDefault();
  const svg = document.querySelector('#' + a.dataset.save + ' svg'); if (!svg) return;
  const img = new Image(), c = document.createElement('canvas'); c.width = c.height = 1000;
  img.onload = () => { const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, 1000, 1000); x.drawImage(img, 0, 0, 1000, 1000); const l = document.createElement('a'); l.href = c.toDataURL('image/png'); l.download = 'QR-Code.png'; l.click(); };
  img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(svg));
}));
</script>
<?php });
    exit;
}

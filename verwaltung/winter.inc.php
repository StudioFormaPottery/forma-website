<?php
// Wintersaison 2026/27 (Vivien, 27.10.2026): alles für die Hotels an einem Ort.
//   FORMA   ?view=winter      Hotel-Flyer + Buchungsseite /urlaub/
//   Movecia ?view=winter_mm   Personal-Fitness-Flyer + Buchungsseite moveciamovement.com/personal-training
// Flyer-Dateien: mitgelieferte im Repo (WINTER_FILES) plus eigene Uploads in ../forma-data/winter/<forma|movecia>/.
// Eingebunden aus verwaltung/index.php (nur angemeldet, CSRF schon geprüft).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/belegt.inc.php';

const WINTER_SEASON = 'Wintersaison 2026/27';
const WINTER_FILES = [
    'forma' => [
        ['/img/forma-flyer/FORMA_Winter_Hotelgaeste_DE_A4.pdf', 'Deutsch · A4 (PDF, zum Drucken)'],
        ['/img/forma-flyer/FORMA_Winter_Hotelgaeste_DE_A5.pdf', 'Deutsch · A5 (PDF, zum Drucken)'],
        ['/img/forma-flyer/FORMA_Winter_HotelGuests_EN_A4.pdf', 'Englisch · A4 (PDF, zum Drucken)'],
        ['/img/forma-flyer/FORMA_Winter_HotelGuests_EN_A5.pdf', 'Englisch · A5 (PDF, zum Drucken)'],
        ['/img/forma-flyer/FORMA_Winter_Hotelgaeste_DE_Instagram_4x5.png', 'Deutsch · Instagram 4:5 (PNG)'],
        ['/img/forma-flyer/FORMA_Winter_Hotelgaeste_DE_WhatsApp_9x16.png', 'Deutsch · WhatsApp-Status 9:16 (PNG)'],
        ['/img/forma-flyer/FORMA_Winter_HotelGuests_EN_Instagram_4x5.png', 'Englisch · Instagram 4:5 (PNG)'],
        ['/img/forma-flyer/FORMA_Winter_HotelGuests_EN_WhatsApp_9x16.png', 'Englisch · WhatsApp-Status 9:16 (PNG)'],
        ['/img/forma-flyer/FORMA_Winter_Hotelflyer.zip', 'Alles zusammen (ZIP)'],
    ],
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
        $title = 'Personal Training · Flyer & Buchungslink';
    } else {
        $base = forma_site_url() . '/urlaub/';
        $pages = [['Deutsch', $base], ['Englisch', $base . '?lang=en']];
        $facts = ['Spin & Go an der Drehscheibe (dieselben Termine und freien Plätze wie auf der Startseite)', 'Private Pottery 1:1 an der Drehscheibe, 2 Stunden', 'Gäste bezahlen online, Rechnung kommt automatisch', 'Ein gemeinsamer Flyer auf Deutsch und Englisch für alle Hotels'];
        $manage = [['?view=kalender', 'Kalender & Buchungen'], ['?view=private', 'Private Pottery'], ['?view=tests', 'Testbuchungen']];
        $title = 'Töpfern für Hotelgäste';
    }
    $repo = WINTER_FILES[$wb];
    $ups = winter_uploads($wb);
    // Gemeinsamer Kalender FORMA + Movecia
    $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? (string)$_GET['m'] : date('Y-m');
    $mFrom = $month . '-01';
    $mTo = date('Y-m-t', strtotime($mFrom));
    $items = vb_items($pdo, $mFrom, $mTo);
    $byDay = [];
    foreach ($items as $it) $byDay[$it['date']][] = $it;
    $conf = vb_conflicts($items);
    $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['d'] ?? '')) && str_starts_with((string)$_GET['d'], $month) ? (string)$_GET['d'] : (str_starts_with(date('Y-m-d'), $month) ? date('Y-m-d') : $mFrom);
    $cal = compact('month', 'mFrom', 'mTo', 'byDay', 'conf', 'day');
    render_page(WINTER_SEASON, function () use ($self, $flash, $isMm, $pages, $facts, $manage, $title, $repo, $ups, $wb, $cal) {
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
.vc-h{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
.vc-h a{font-size:22px;text-decoration:none;padding:4px 14px;border-radius:999px;border:1px solid rgba(0,0,0,.12);color:inherit}
.vc-h b{font-size:20px;font-weight:500}
.vc{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px}
.vc .wd{text-align:center;font-size:13px;color:#8a7a72;padding:2px 0}
.vc a.dy{display:flex;flex-direction:column;gap:3px;min-height:86px;padding:6px;border-radius:12px;background:#fff;text-decoration:none;color:inherit;border:2px solid transparent;overflow:hidden}
.vc a.dy.sel{border-color:<?= $acc ?>}
.vc a.dy.today .n{background:<?= $acc ?>;color:#fff}
.vc a.dy.cf{background:#FBE3DE}
.vc .n{align-self:flex-start;font-size:14px;min-width:24px;text-align:center;border-radius:999px;padding:1px 4px}
.vc .c{display:block;font-size:11.5px;line-height:1.25;padding:2px 5px;border-radius:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.k-forma{background:#8C2C1D;color:#fff}.k-forma0{background:#F1E1D6;color:#8C2C1D}.k-private{background:#B05A38;color:#fff}
.k-pt{background:#7D5E68;color:#fff}.k-pt-wait{background:#EADFDE;color:#4A3A41}.k-natal{background:#4A3A41;color:#fff}.k-call{background:#E8D6CA;color:#4A3A41}
.vc-leg{display:flex;flex-wrap:wrap;gap:6px 12px;margin:10px 0 0;font-size:14px}.vc-leg span{display:inline-flex;align-items:center;gap:5px}.vc-leg i{width:14px;height:14px;border-radius:4px;display:inline-block}
.vc-day{margin-top:14px;display:flex;flex-direction:column;gap:8px}
.vc-day .it{display:flex;gap:12px;align-items:center;padding:10px 12px;border-radius:12px;background:#fff;font-size:17px}
.vc-day .it b{min-width:118px;font-variant-numeric:tabular-nums}
@media(max-width:700px){.vc a.dy{min-height:58px;padding:4px}.vc .c{font-size:0;height:7px;padding:0}.vc .c.more{display:none}}
@media(min-width:900px){.ws .grid{grid-template-columns:1.3fr 1fr}}
</style>
<div class="ws">
    <?php if ($isMm) { mm_subnav('training'); ?><p><a href="<?= forma_h($self) ?>?view=training">← Zurück zu Personal Training</a></p><?php } ?>
    <p class="eyebrow">❄️ <?= WINTER_SEASON ?> · <?= $isMm ? 'Movecia' : 'FORMA' ?></p>
    <h1><?= forma_h($title) ?></h1>
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

    <div class="card" id="mitarbeiter" style="background:#FBF1EA;border:1.5px dashed <?= $isMm ? '#C9B3BA' : '#DDBFA8' ?>">
        <h2>📌 Mitarbeiter-Angebote · Winter Paznaun</h2>
        <p class="muted small" style="margin:-.3rem 0 .6rem">Nur für dich: so beim Tourismusverband eingetragen. Organisation und Buchung bauen wir erst, wenn jemand bucht.</p>
        <?php if (!$isMm): ?>
        <ul class="f"><li>Spin &amp; Go für Mitarbeiter: <b>Montag und Freitag, 9–11 Uhr</b></li><li>Exklusiv für Mitarbeiter: 10er-Block <b>€ 600</b> statt € 700</li></ul>
        <p class="small muted" style="margin:.6rem 0 0">Movecia Personal Training für Mitarbeiter: € 85 einzeln, € 140 zu zweit, 10er-Block € 700 statt € 850 (mit kostenloser Kennenlern- und Anamneseeinheit, 60 Min.).</p>
        <?php else: ?>
        <ul class="f"><li>Einzeltraining (ca. 75 Min.) <b>€ 85</b>, zwei Mitarbeiter gemeinsam <b>€ 140</b></li><li>Exklusiv für Mitarbeiter: 10er-Block <b>€ 700</b> statt € 850 (€ 70 pro Einheit)</li><li>Dazu eine kostenlose Kennenlern- und Anamneseeinheit (60 Min.)</li></ul>
        <p class="small muted" style="margin:.6rem 0 0">FORMA Spin &amp; Go für Mitarbeiter: Mo + Fr 9–11 Uhr, 10er-Block € 600 statt € 700.</p>
        <?php endif; ?>
    </div>

    <div class="card" id="kalender">
        <h2>Alle Termine · FORMA &amp; Movecia</h2>
        <p class="muted small" style="margin:-.3rem 0 .8rem">Hier steht alles, was stattfindet. Was belegt ist, können Gäste auf den Hotel-Buchungsseiten (FORMA und Movecia) automatisch nicht mehr wählen, mit 15 Minuten Puffer. Kurse ohne Anmeldung sind hell und blockieren nichts. Rot hinterlegt = zwei Termine überschneiden sich.</p>
        <?php extract($cal); $ts = strtotime($mFrom); $v = $self . '?view=' . ($isMm ? 'winter_mm' : 'winter');
        $mn = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']; ?>
        <div class="vc-h"><a href="<?= forma_h($v . '&m=' . date('Y-m', strtotime('-1 month', $ts))) ?>#kalender" aria-label="Vormonat">‹</a><b><?= $mn[(int)date('n', $ts)] . ' ' . date('Y', $ts) ?></b><a href="<?= forma_h($v . '&m=' . date('Y-m', strtotime('+1 month', $ts))) ?>#kalender" aria-label="Nächster Monat">›</a></div>
        <div class="vc">
            <?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $w): ?><span class="wd"><?= $w ?></span><?php endforeach; ?>
            <?php for ($i = 1; $i < (int)date('N', $ts); $i++): ?><span></span><?php endfor; ?>
            <?php for ($dn = 1; $dn <= (int)date('t', $ts); $dn++): $d = $month . '-' . sprintf('%02d', $dn); $list = $byDay[$d] ?? []; ?>
                <a class="dy<?= $d === $day ? ' sel' : '' ?><?= $d === date('Y-m-d') ? ' today' : '' ?><?= isset($conf[$d]) ? ' cf' : '' ?>" href="<?= forma_h($v . '&m=' . $month . '&d=' . $d) ?>#kalender"><span class="n"><?= $dn ?></span>
                <?php foreach ($list as $k => $it): $kc = $it['kind'] === 'forma' && !$it['block'] ? 'forma0' : $it['kind']; ?><span class="c k-<?= $kc ?><?= $k >= 3 ? ' more' : '' ?>"><?= sprintf('%d:%02d', intdiv($it['a'], 60), $it['a'] % 60) ?> <?= forma_h($it['kind'] === 'forma' ? $it['title'] : ($it['kind'] === 'private' ? 'Private' : ($it['kind'] === 'call' ? 'Gespräch' : ($it['kind'] === 'natal' ? 'Natal' : 'PT')))) ?></span><?php endforeach; ?></a>
            <?php endfor; ?>
        </div>
        <div class="vc-leg"><span><i class="k-forma"></i>FORMA-Kurs mit Anmeldung</span><span><i class="k-forma0"></i>FORMA-Kurs noch leer</span><span><i class="k-private"></i>Private Pottery</span><span><i class="k-pt"></i>Personal Training</span><span><i class="k-pt-wait"></i>PT wartet auf Zahlung</span><span><i class="k-natal"></i>Prä-/Postnatal</span><span><i class="k-call"></i>Kennenlerngespräch</span></div>
        <div class="vc-day">
            <p style="margin:0;font-size:18px"><b><?= forma_h(['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'][(int)date('N', strtotime($day))] . ', ' . date('j. n. Y', strtotime($day))) ?></b><?= isset($conf[$day]) ? ' <span style="color:#B5544A">· ⚠ Überschneidung</span>' : '' ?></p>
            <?php if (empty($byDay[$day])): ?><p class="muted" style="margin:0">An diesem Tag ist noch nichts eingetragen.</p><?php endif; ?>
            <?php foreach ($byDay[$day] ?? [] as $it): $kc = $it['kind'] === 'forma' && !$it['block'] ? 'forma0' : $it['kind'];
                $lnk = ['forma' => '?view=kalender', 'private' => '?view=private', 'pt' => '?view=training', 'pt-wait' => '?view=training', 'natal' => '?view=natal', 'call' => '?view=natal'][$it['kind']]; ?>
                <a class="it" href="<?= forma_h($self . $lnk) ?>" style="text-decoration:none;color:inherit"><b><?= sprintf('%d:%02d–%d:%02d', intdiv($it['a'], 60), $it['a'] % 60, intdiv($it['b'], 60), $it['b'] % 60) ?></b><span class="c k-<?= $kc ?>" style="font-size:13px"><?= $it['kind'] === 'forma' || $it['kind'] === 'private' ? 'FORMA' : 'Movecia' ?></span><span><?= forma_h($it['title'] . ($it['who'] !== '' ? ' · ' . $it['who'] : '') . ($it['kind'] === 'forma' ? ' · ' . ($it['n'] ? $it['n'] . ' angemeldet' : 'noch niemand angemeldet') : '') . ($it['kind'] === 'pt-wait' ? ' · wartet auf Zahlung' : '')) ?></span></a>
            <?php endforeach; ?>
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

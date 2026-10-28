<?php
// „📱 Instagram täglich“ (Vivien, 28.10.2026): Talking Heads für Storys und Reels für den ganzen Monat planen,
// im Studio in Ruhe vorab aufnehmen (9:16, Teleprompter, Look, Musik im Bearbeiten-Schritt), am Tag selbst am Handy
// nur noch „Teilen → Instagram“. Videos liegen bis 30 Tage nach dem Tag in ../forma-data/insta/ (nie im Repo).
// ?view=insta[&m=JJJJ-MM]. Aktionen mm_ig_*.
declare(strict_types=1);

function ig_schema(PDO $pdo): void
{
    static $done = false; if ($done) return; $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS mm_insta (id INTEGER PRIMARY KEY AUTOINCREMENT, day TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'story',
        acc TEXT NOT NULL DEFAULT 'privat', title TEXT NOT NULL DEFAULT '', text TEXT NOT NULL DEFAULT '', music TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'offen', file TEXT NOT NULL DEFAULT '', size INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT)");
}
function ig_dir(): string { $d = forma_data_dir() . '/insta'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function ig_get(PDO $pdo, int $id): ?array { $s = $pdo->prepare('SELECT * FROM mm_insta WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }
const IG_ACC = ['privat' => 'Privat', 'movecia' => 'Movecia', 'forma' => 'FORMA'];
function ig_wd(string $d, bool $long = false): string
{
    $t = strtotime($d);
    $W = $long ? ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'] : ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    return $W[(int)date('w', $t)] . ($long ? ', ' : ' ') . date('j.n.', $t);
}
function ig_title(array $r): string
{
    if (trim($r['title']) !== '') return trim($r['title']);
    $t = trim(preg_replace('/\[[^\]]*\]/', '', $r['text']));
    $t = preg_split('/\R/', $t)[0] ?? '';
    return $t === '' ? 'Noch ohne Text' : (mb_strlen($t) > 60 ? mb_substr($t, 0, 58) . ' …' : $t);
}
function ig_cleanup(PDO $pdo): void
{
    $old = date('Y-m-d', strtotime('-30 days'));
    $s = $pdo->prepare("SELECT id, file FROM mm_insta WHERE file <> '' AND day < ?"); $s->execute([$old]);
    foreach ($s->fetchAll() as $r) { @unlink(ig_dir() . '/' . basename($r['file'])); $pdo->prepare("UPDATE mm_insta SET file = '', size = 0 WHERE id = ?")->execute([$r['id']]); }
}
/** Ganzen Monat einfügen: Blöcke mit „---“ getrennt oder mit Datumszeile („3.11.“, „Mo 3.11. Reel“, „3.11. Story: Titel“). */
function ig_parse(string $raw, string $start, string $kind): array
{
    $out = []; $day = $start; $cur = null; $year = (int)substr($start, 0, 4);
    $flush = function () use (&$cur, &$out, &$day) {
        if ($cur === null) return; $cur['text'] = trim($cur['text']);
        if ($cur['text'] !== '' || $cur['title'] !== '') { $out[] = $cur; $day = date('Y-m-d', strtotime($cur['day'] . ' +1 day')); }
        $cur = null;
    };
    foreach (preg_split('/\R/', str_replace("\r", '', $raw)) as $line) {
        if (preg_match('/^\s*-{3,}\s*$/', $line)) { $flush(); continue; }
        if (preg_match('/^\s*(?:(?:Mo|Di|Mi|Do|Fr|Sa|So)[a-z]*\.?,?\s*)?(\d{1,2})\.\s*(\d{1,2})\.(\d{2,4})?\s*(?:[·:\-–]\s*)?(story|reel|storys?)?\s*[:·\-–]?\s*(.*)$/iu', $line, $m)) {
            $flush();
            $y = $m[3] !== '' ? (int)$m[3] : $year; if ($y < 100) $y += 2000;
            $d = sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[1]);
            if ($m[3] === '' && $d < date('Y-m-d', strtotime($start . ' -60 days'))) $d = sprintf('%04d-%02d-%02d', $y + 1, (int)$m[2], (int)$m[1]);
            $cur = ['day' => $d, 'kind' => $m[4] !== '' ? (stripos($m[4], 'reel') === 0 ? 'reel' : 'story') : $kind, 'title' => trim($m[5]), 'text' => ''];
            continue;
        }
        if ($cur === null) { if (trim($line) === '') continue; $cur = ['day' => $day, 'kind' => $kind, 'title' => '', 'text' => '']; }
        if ($cur['text'] === '' && preg_match('/^\s*(story|reel)\s*:?\s*$/i', $line, $k)) { $cur['kind'] = strtolower($k[1]); continue; }
        $cur['text'] .= $line . "\n";
    }
    $flush();
    return $out;
}

if ($view === 'insta' || str_starts_with((string)$action, 'mm_ig_')) {
    ig_schema($pdo);
    $igBack = strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?') . '?view=insta&m=' . preg_replace('/[^0-9-]/', '', (string)($_POST['m'] ?? date('Y-m')));
    $igJson = function (array $x): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($x); exit; };

    if ($action === 'mm_ig_save') {
        $id = (int)($_POST['id'] ?? 0);
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['day'] ?? '')) ? $_POST['day'] : date('Y-m-d');
        $kind = ($_POST['kind'] ?? '') === 'reel' ? 'reel' : 'story';
        $acc = isset(IG_ACC[$_POST['acc'] ?? '']) ? $_POST['acc'] : 'privat';
        $vals = [$day, $kind, $acc, trim((string)($_POST['title'] ?? '')), trim((string)($_POST['text'] ?? '')), trim((string)($_POST['music'] ?? '')), date('c')];
        if ($id && ig_get($pdo, $id)) { $pdo->prepare('UPDATE mm_insta SET day=?, kind=?, acc=?, title=?, text=?, music=?, updated_at=? WHERE id=?')->execute([...$vals, $id]); }
        else { $pdo->prepare('INSERT INTO mm_insta (day, kind, acc, title, text, music, updated_at, created_at) VALUES (?,?,?,?,?,?,?,?)')->execute([...$vals, date('c')]); $id = (int)$pdo->lastInsertId(); }
        if (!empty($_POST['studio'])) go(strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?') . '?view=studio&ig=' . $id);
        go($igBack . '#d' . $day, 'Gespeichert.');
    }
    if ($action === 'mm_ig_status') {
        $r = ig_get($pdo, (int)($_POST['id'] ?? 0)); $st = in_array($_POST['st'] ?? '', ['offen', 'aufgenommen', 'gepostet'], true) ? $_POST['st'] : 'offen';
        if ($r) $pdo->prepare('UPDATE mm_insta SET status = ?, updated_at = ? WHERE id = ?')->execute([$st, date('c'), $r['id']]);
        if (!empty($_POST['ajax'])) $igJson(['ok' => 1]);
        go($igBack . '#d' . ($r['day'] ?? ''));
    }
    if ($action === 'mm_ig_del') {
        $r = ig_get($pdo, (int)($_POST['id'] ?? 0));
        if ($r) { if ($r['file'] !== '') @unlink(ig_dir() . '/' . basename($r['file'])); $pdo->prepare('DELETE FROM mm_insta WHERE id = ?')->execute([$r['id']]); }
        go($igBack . '#d' . ($r['day'] ?? ''), 'Gelöscht.');
    }
    if ($action === 'mm_ig_delvid') {
        $r = ig_get($pdo, (int)($_POST['id'] ?? 0));
        if ($r && $r['file'] !== '') { @unlink(ig_dir() . '/' . basename($r['file'])); $pdo->prepare("UPDATE mm_insta SET file = '', size = 0, status = 'offen' WHERE id = ?")->execute([$r['id']]); }
        go($igBack . '#d' . ($r['day'] ?? ''), 'Video gelöscht.');
    }
    if ($action === 'mm_ig_bulk') {
        $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['start'] ?? '')) ? $_POST['start'] : date('Y-m-d');
        $list = ig_parse((string)($_POST['raw'] ?? ''), $start, ($_POST['kind'] ?? '') === 'reel' ? 'reel' : 'story');
        $acc = isset(IG_ACC[$_POST['acc'] ?? '']) ? $_POST['acc'] : 'privat';
        $ins = $pdo->prepare('INSERT INTO mm_insta (day, kind, acc, title, text, created_at, updated_at) VALUES (?,?,?,?,?,?,?)');
        foreach ($list as $x) $ins->execute([$x['day'], $x['kind'], $acc, $x['title'], $x['text'], date('c'), date('c')]);
        $m = $list ? substr($list[0]['day'], 0, 7) : substr($start, 0, 7);
        go(strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?') . '?view=insta&m=' . $m, count($list) . ' Talking Heads eingetragen.');
    }
    // Video in Stücken (8 MB) hochladen: n = 0 legt neu an, last = 1 schließt ab
    if ($action === 'mm_ig_up') {
        $r = ig_get($pdo, (int)($_POST['id'] ?? 0));
        if (!$r) $igJson(['error' => 'Eintrag nicht gefunden.']);
        $f = $_FILES['b'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) $igJson(['error' => 'Upload-Stück fehlt (Fehler ' . (int)($f['error'] ?? -1) . ').']);
        $ext = ($_POST['ext'] ?? '') === 'webm' ? 'webm' : 'mp4';
        $tmp = ig_dir() . '/up-' . $r['id'] . '.part';
        if ((int)($_POST['n'] ?? 0) === 0) @unlink($tmp);
        file_put_contents($tmp, file_get_contents($f['tmp_name']), FILE_APPEND);
        if (!empty($_POST['last'])) {
            if ($r['file'] !== '') @unlink(ig_dir() . '/' . basename($r['file']));
            $name = 'ig-' . $r['id'] . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            rename($tmp, ig_dir() . '/' . $name);
            $pdo->prepare("UPDATE mm_insta SET file = ?, size = ?, status = CASE WHEN status = 'gepostet' THEN status ELSE 'aufgenommen' END, updated_at = ? WHERE id = ?")
                ->execute([$name, filesize(ig_dir() . '/' . $name), date('c'), $r['id']]);
            $igJson(['ok' => 1, 'done' => 1]);
        }
        $igJson(['ok' => 1]);
    }
    if ($view === 'insta' && isset($_GET['dl'])) {
        $r = ig_get($pdo, (int)$_GET['dl']);
        $p = $r && $r['file'] !== '' ? ig_dir() . '/' . basename($r['file']) : '';
        if ($p === '' || !is_file($p)) { http_response_code(404); exit('Kein Video.'); }
        $ext = pathinfo($p, PATHINFO_EXTENSION);
        $fn = date('Y-m-d', strtotime($r['day'])) . '_' . ($r['kind'] === 'reel' ? 'Reel' : 'Story') . '_' . preg_replace('/[^\wäöüÄÖÜß-]+/u', '-', mb_substr(ig_title($r), 0, 30)) . '.' . $ext;
        $size = filesize($p); $from = 0; $to = $size - 1;
        header('Content-Type: video/' . ($ext === 'webm' ? 'webm' : 'mp4'));
        header('Accept-Ranges: bytes');
        header('Content-Disposition: ' . (isset($_GET['save']) ? 'attachment' : 'inline') . '; filename="' . rawurlencode($fn) . '"; filename*=UTF-8\'\'' . rawurlencode($fn));
        header('Cache-Control: private, max-age=3600');
        if (preg_match('/bytes=(\d*)-(\d*)/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
            if ($m[1] !== '') $from = (int)$m[1]; if ($m[2] !== '') $to = min($to, (int)$m[2]); if ($m[1] === '' && $m[2] !== '') { $from = $size - (int)$m[2]; $to = $size - 1; }
            http_response_code(206); header("Content-Range: bytes $from-$to/$size");
        }
        header('Content-Length: ' . ($to - $from + 1));
        $fh = fopen($p, 'rb'); fseek($fh, $from); $left = $to - $from + 1;
        while ($left > 0 && !feof($fh)) { $c = fread($fh, min(1 << 20, $left)); echo $c; $left -= strlen($c); flush(); }
        exit;
    }

    if ($view === 'insta') {
        ig_cleanup($pdo);
        $m = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');
        $first = $m . '-01'; $days = (int)date('t', strtotime($first)); $last = $m . '-' . sprintf('%02d', $days);
        $s = $pdo->prepare('SELECT * FROM mm_insta WHERE day BETWEEN ? AND ? ORDER BY day, kind DESC, id'); $s->execute([$first, $last]);
        $by = []; foreach ($s->fetchAll() as $r) $by[$r['day']][] = $r;
        $today = date('Y-m-d');
        $ts = $pdo->prepare("SELECT * FROM mm_insta WHERE day = ? ORDER BY kind DESC, id"); $ts->execute([$today]); $todayList = $ts->fetchAll();
        $cnt = ['plan' => 0, 'auf' => 0, 'post' => 0];
        foreach ($by as $l) foreach ($l as $r) { $cnt['plan']++; if ($r['status'] === 'aufgenommen') $cnt['auf']++; if ($r['status'] === 'gepostet') $cnt['post']++; }
        $mon = ['Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $mName = $mon[(int)substr($m, 5, 2) - 1] . ' ' . substr($m, 0, 4);
        $prev = date('Y-m', strtotime($first . ' -1 month')); $next = date('Y-m', strtotime($first . ' +1 month'));
        $self = strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?');
        // Fertige Texte (verwaltung/insta-skripte.json) + welche schon verwendet sind
        $sk = json_decode((string)@file_get_contents(__DIR__ . '/insta-skripte.json'), true)['list'] ?? [];
        $usedT = array_map('mb_strtolower', $pdo->query("SELECT title FROM mm_insta WHERE title <> ''")->fetchAll(PDO::FETCH_COLUMN));
        foreach ($sk as &$x) $x['used'] = in_array(mb_strtolower($x['de_t']), $usedT, true) || in_array(mb_strtolower($x['en_t']), $usedT, true);
        unset($x);
        render_page('Instagram täglich', function () use ($self, $m, $first, $days, $by, $today, $todayList, $cnt, $mName, $prev, $next, $sk) {
            $h = 'forma_h';
            $card = function (array $r, bool $big = false) use ($self, $h, $m) {
                $st = $r['status']; $has = $r['file'] !== '';
                ob_start(); ?>
<div class="ig<?= $big ? ' big' : '' ?> st-<?= $h($st) ?>" id="e<?= (int)$r['id'] ?>">
  <div class="ig-top"><span class="kind k-<?= $h($r['kind']) ?>"><?= $r['kind'] === 'reel' ? '🎬 Reel' : '⭕ Story' ?></span><span class="acc"><?= $h(IG_ACC[$r['acc']] ?? 'Privat') ?></span>
    <span class="stt"><?= $st === 'gepostet' ? '✓ gepostet' : ($has ? '🎥 aufgenommen' : ($r['text'] === '' ? '✍️ Text fehlt' : '○ noch aufnehmen')) ?></span></div>
  <b class="tt"><?= $h(ig_title($r)) ?></b>
  <?php if ($r['music'] !== ''): ?><p class="mu">🎵 <?= $h($r['music']) ?></p><?php endif; ?>
  <?php if ($has): ?><video src="<?= $h($self) ?>?view=insta&amp;dl=<?= (int)$r['id'] ?>#t=0.1" controls playsinline preload="metadata"></video><?php endif; ?>
  <div class="ig-b">
    <?php if ($has): ?>
      <button type="button" class="b main" data-share="<?= (int)$r['id'] ?>">📲 Teilen → Instagram</button>
      <a class="b" href="<?= $h($self) ?>?view=insta&amp;dl=<?= (int)$r['id'] ?>&amp;save=1">⬇ Laden</a>
    <?php endif; ?>
    <a class="b <?= $has ? '' : 'rec' ?>" href="<?= $h($self) ?>?view=studio&amp;ig=<?= (int)$r['id'] ?>"><?= $has ? '↺ Neu aufnehmen' : '🎥 Im Studio aufnehmen' ?></a>
    <?php if ($st !== 'gepostet'): ?><form method="post"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_status"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="st" value="gepostet"><button class="b">✓ Gepostet</button></form>
    <?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_status"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="st" value="<?= $has ? 'aufgenommen' : 'offen' ?>"><button class="b sm">doch nicht gepostet</button></form><?php endif; ?>
  </div>
  <details class="ed"><summary>✏️ Text, Art, Musik ändern</summary>
    <form method="post" class="f"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_save"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <div class="row"><label>Tag<input type="date" name="day" value="<?= $h($r['day']) ?>"></label>
        <label>Art<select name="kind"><option value="story"<?= $r['kind'] === 'story' ? ' selected' : '' ?>>⭕ Story</option><option value="reel"<?= $r['kind'] === 'reel' ? ' selected' : '' ?>>🎬 Reel</option></select></label>
        <label>Konto<select name="acc"><?php foreach (IG_ACC as $k => $n): ?><option value="<?= $k ?>"<?= $r['acc'] === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label></div>
      <label>Überschrift (nur für dich, optional)<input name="title" value="<?= $h($r['title']) ?>"></label>
      <label>Text für den Teleprompter <small>(Leerzeile = neuer Absatz)</small><textarea name="text" rows="6"><?= $h($r['text']) ?></textarea></label>
      <label>Musik <small>(Liedname, den du in Instagram dazu wählst)</small><input name="music" value="<?= $h($r['music']) ?>" placeholder="z. B. Ludovico Einaudi – Experience"></label>
      <div class="ig-b"><button class="b main">Speichern</button><button class="b" name="studio" value="1">Speichern &amp; aufnehmen</button></div>
    </form>
    <div class="ig-b" style="margin-top:8px">
      <?php if ($has): ?><form method="post" onsubmit="return confirm('Video löschen? Der Text bleibt.')"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_delvid"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="b sm">Video löschen</button></form><?php endif; ?>
      <form method="post" onsubmit="return confirm('Diesen Eintrag ganz löschen?')"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_del"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="b sm">Eintrag löschen</button></form>
    </div>
  </details>
</div>
<?php return ob_get_clean(); };
            ?>
<style>
body{background:#F2ECE4}
main{max-width:1180px}
.ih h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;color:#4A3A41;font-size:clamp(34px,4vw,46px);margin:6px 0 4px}
.ih p{margin:0 0 16px;color:#6B5658;font-size:19px;max-width:72ch;line-height:1.5}
.box{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:24px;padding:16px;margin-bottom:16px}
.box>h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:28px;color:#4A3A41;margin:0 0 10px}
.mnav{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0 14px}
.mnav b{font:500 30px/1.1 "Cormorant Garamond",Georgia,serif;color:#4A3A41;margin:0 6px}
.mnav a{font-size:20px;text-decoration:none;color:#4A3A41;border:1.5px solid #DDD2C6;background:#fff;border-radius:999px;padding:6px 16px}
.cnt{font-size:17px;color:#6B5658;margin-left:auto}
.b{font:inherit;font-size:17px;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:46px;padding:9px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;text-decoration:none}
.b.main{background:#7D5E68;border-color:#7D5E68;color:#fff}.b.rec{background:#A0413B;border-color:#A0413B;color:#fff}.b.sm{min-height:40px;font-size:15px;color:#7D7076}
.days{display:flex;flex-direction:column;gap:10px}
.day{display:grid;grid-template-columns:110px minmax(0,1fr);gap:12px;align-items:start;padding:12px;border-radius:20px;background:#FBF8F4;border:1.5px solid #DDD2C6;scroll-margin-top:80px}
.day.today{border-color:#7D5E68;box-shadow:0 0 0 2px #7D5E68 inset}
.day.past{opacity:.65}
.dl{font-size:18px;color:#4A3A41;line-height:1.25}.dl b{display:block;font:500 30px/1 "Cormorant Garamond",Georgia,serif}.dl small{color:#7D7076;font-size:14px}
.dc{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start}
.add{display:flex;gap:6px;flex-wrap:wrap;align-self:center}.add .b{min-height:40px;font-size:15px;border-style:dashed}
.ig{flex:1 1 300px;max-width:520px;background:#fff;border:1.5px solid #DDD2C6;border-radius:18px;padding:12px;display:flex;flex-direction:column;gap:8px}
.ig.st-gepostet{background:#F3F7F1;border-color:#C8D8C3}
.ig-top{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:15px}
.kind{padding:3px 10px;border-radius:999px;font-weight:500}.k-story{background:#EADFDE;color:#6B5658}.k-reel{background:#4A3A41;color:#fff}
.acc{padding:3px 10px;border-radius:999px;background:#F2ECE4;color:#6B5658}.stt{margin-left:auto;color:#6B5658}
.tt{font-weight:500;font-size:18px;color:#2E2529;line-height:1.35}
.mu{margin:0;font-size:16px;color:#7D5E68}
.ig video{width:100%;max-width:170px;aspect-ratio:9/16;object-fit:cover;border-radius:14px;background:#2E2529}
.ig.big video{max-width:240px}
.ig-b{display:flex;flex-wrap:wrap;gap:8px}.ig-b form{margin:0}
.ed>summary{cursor:pointer;color:#7D5E68;font-size:16px;list-style:none}.ed>summary::-webkit-details-marker{display:none}
.f{display:flex;flex-direction:column;gap:10px;margin-top:10px}
.f label{display:flex;flex-direction:column;gap:4px;font-size:16px;color:#6B5658}.f label small{color:#7D7076;font-size:14px}
.f input,.f select,.f textarea{font:inherit;font-size:17px;padding:10px 12px;border:1.5px solid #DDD2C6;border-radius:12px;background:#fff;color:#2E2529}
.f textarea{line-height:1.5}
.f .row{display:flex;flex-wrap:wrap;gap:10px}.f .row label{flex:1 1 140px}
.tip{font-size:16px;color:#7D7076;line-height:1.5;margin:6px 0 0}
/* Kalender (Vivien, 28.10.2026) */
.ical-wrap{display:grid;grid-template-columns:minmax(300px,400px) minmax(0,1fr);gap:20px;align-items:start}
@media(max-width:860px){.ical-wrap{grid-template-columns:minmax(0,1fr)}}
.iday-w,.ical{min-width:0}.iday .dc .ig{min-width:0;max-width:100%}
.ical{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:24px;padding:12px;position:sticky;top:70px}
@media(max-width:860px){.ical{position:static}}
.ical .mnav{margin:0 0 8px;justify-content:space-between}.ical .mnav b{font-size:24px}
.ical-g{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px}
.ical-g .wd{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#7D7076;text-align:center;padding-bottom:2px}
.dd{aspect-ratio:1/1.1;min-width:0;border-radius:14px;border:1.5px solid #EFE6E1;background:#fff;font:inherit;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:4px 2px;gap:2px;color:#4A3A41}
.dd .n{font-size:15px}.dd.past{opacity:.55}.dd.today{border-color:#7D5E68}.dd.sel{background:#7D5E68;border-color:#7D5E68;color:#fff}
.dd .dots{display:flex;flex-wrap:wrap;justify-content:center;gap:1px}.dd .dots i{font-style:normal;font-size:10px;line-height:16px;width:16px;border-radius:50%;text-align:center}
.dots i.e{background:#F3EAEE}.dots i.o{background:#E3CFD7}.dots i.a{background:#9B7B87}.dots i.p{background:#CFE0C9}
.ical-leg{display:flex;flex-wrap:wrap;gap:4px 12px;margin:10px 2px 0;font-size:12px;color:#7D7076}.ical-leg i{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:4px;vertical-align:-1px}
.ical-leg .o{background:#E3CFD7}.ical-leg .a{background:#9B7B87}.ical-leg .p{background:#CFE0C9}
.ical .cnt{margin:8px 2px 0;font-size:14px}
@media(max-width:640px){.ig.big video{max-width:150px}.ig video{max-width:130px}}
.iday h2{font:500 28px/1.15 "Cormorant Garamond",Georgia,serif;color:#4A3A41;margin:0 0 12px}
.iday .add{margin-top:12px}.iday .add .b{border-style:solid;min-height:48px;font-size:17px}
.pick-sk{margin-top:14px;background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:24px;padding:14px}
.psk-h{display:flex;justify-content:space-between;align-items:center;gap:10px}.psk-h b{font:500 23px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.psk-tools{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:10px 0}
.seg2{display:flex;gap:6px}.seg2 button{font:inherit;font-size:16px;padding:8px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:42px}
.seg2 button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.chk2{display:flex;align-items:center;gap:8px;font-size:15px;color:#6B5658}.chk2 input{width:20px;height:20px;accent-color:#7D5E68}
.psk-list{display:flex;flex-direction:column;gap:10px;max-height:62vh;overflow:auto;padding-right:2px}
.sk{background:#fff;border:1.5px solid #E6DCD2;border-radius:18px;padding:12px}
.sk.used{opacity:.7}
.sk-h{display:flex;justify-content:space-between;gap:8px;font-size:13px;color:#7D5E68}.sk-u{color:#7D7076}
.sk b{display:block;font:500 21px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41;margin:4px 0 6px}
.sk p{margin:0 0 6px;font-size:15px;line-height:1.5;color:#4A3A41}
#pskOwn{margin-top:10px}
.todayw{display:flex;flex-wrap:wrap;gap:12px}
pre.ex{white-space:pre-wrap;background:#fff;border:1px dashed #DDD2C6;border-radius:12px;padding:10px;font-size:15px;color:#6B5658;margin:6px 0}
@media(max-width:640px){.day{grid-template-columns:1fr}.dl b{display:inline;font-size:26px;margin-right:6px}}
</style>
<?php mm_subnav('insta'); ?>
<div class="ih"><h1>📱 Instagram täglich</h1>
  <p>Plane hier die Talking Heads für deine Storys und Reels für den ganzen Monat. Aufnehmen kannst du sie in Ruhe im Studio, auch schon für Tage im Voraus. Am Tag selbst öffnest du diese Seite am Handy und tippst nur noch auf „Teilen → Instagram“.</p></div>

<?php if ($todayList): ?>
<div class="box"><h2>Heute · <?= $h(ig_wd($today, true)) ?></h2><div class="todayw"><?php foreach ($todayList as $r) echo $card($r, true); ?></div>
  <p class="tip">„Teilen“ öffnet am iPhone das Teilen-Menü: dort Instagram wählen, dann Story oder Reel. Musik fügst du in Instagram dazu (die Musik dort ist für dein Konto erlaubt).</p></div>
<?php endif; ?>

<div class="ical-wrap">
  <div class="ical">
    <div class="mnav"><a href="<?= $h($self) ?>?view=insta&amp;m=<?= $h($prev) ?>" aria-label="Monat davor">‹</a><b><?= $h($mName) ?></b><a href="<?= $h($self) ?>?view=insta&amp;m=<?= $h($next) ?>" aria-label="nächster Monat">›</a></div>
    <div class="ical-g">
      <?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $w): ?><span class="wd"><?= $w ?></span><?php endforeach; ?>
      <?php for ($i = 0; $i < ((int)date('N', strtotime($first)) - 1); $i++): ?><span></span><?php endfor; ?>
      <?php for ($i = 1; $i <= $days; $i++): $d = $m . '-' . sprintf('%02d', $i); $l = $by[$d] ?? []; ?>
      <button type="button" class="dd<?= $d === $today ? ' today' : '' ?><?= $d < $today ? ' past' : '' ?>" data-day="<?= $h($d) ?>"><span class="n"><?= $i ?></span><span class="dots"><?php foreach ($l as $r): ?><i class="<?= $h($r['status'] === 'gepostet' ? 'p' : ($r['file'] !== '' ? 'a' : ($r['text'] === '' ? 'e' : 'o'))) ?>"><?= $r['kind'] === 'reel' ? '🎬' : '⭕' ?></i><?php endforeach; ?></span></button>
      <?php endfor; ?>
    </div>
    <div class="ical-leg"><span><i class="o"></i>Text da, noch aufnehmen</span><span><i class="a"></i>aufgenommen</span><span><i class="p"></i>gepostet</span></div>
    <p class="cnt"><?= $cnt['plan'] ?> geplant · <?= $cnt['auf'] ?> aufgenommen · <?= $cnt['post'] ?> gepostet</p>
  </div>
  <div class="iday-w">
    <?php for ($i = 1; $i <= $days; $i++): $d = $m . '-' . sprintf('%02d', $i); $l = $by[$d] ?? []; ?>
    <div class="iday" data-iday="<?= $h($d) ?>" hidden>
      <h2><?= $h(ig_wd($d, true)) ?><?= $d === $today ? ' · heute' : '' ?></h2>
      <?php if ($l): ?><div class="dc"><?php foreach ($l as $r) echo $card($r); ?></div><?php else: ?><p class="tip">Für diesen Tag ist noch nichts geplant.</p><?php endif; ?>
      <div class="add"><button type="button" class="b main" data-new="story" data-d="<?= $h($d) ?>">＋ Story</button><button type="button" class="b main" data-new="reel" data-d="<?= $h($d) ?>">＋ Reel</button></div>
    </div>
    <?php endfor; ?>
    <div class="pick-sk" id="pickSk" hidden>
      <div class="psk-h"><b id="pskT">Welcher Text?</b><button type="button" class="b sm" id="pskX">Schließen</button></div>
      <div class="psk-tools"><div class="seg2"><button type="button" data-lang2="de" aria-pressed="true">Deutsch</button><button type="button" data-lang2="en" aria-pressed="false">Englisch</button></div>
        <label class="chk2"><input type="checkbox" id="pskAll"> auch die anderen Texte zeigen</label></div>
      <div class="psk-list" id="pskList"></div>
      <form method="post" id="pskF"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_save"><input type="hidden" name="m" value="<?= $h($m) ?>">
        <input type="hidden" name="day"><input type="hidden" name="kind"><input type="hidden" name="title"><input type="hidden" name="text"><input type="hidden" name="studio"></form>
      <button type="button" class="b" id="pskOwn">✍️ Eigenen Text schreiben</button>
    </div>
  </div>
</div>

<details class="box" style="margin-top:18px"><summary style="cursor:pointer;font:500 24px/1.2 'Cormorant Garamond',Georgia,serif;color:#4A3A41;list-style:none">✍️ Ganzen Monat auf einmal einfügen</summary>
  <form method="post" class="f"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_bulk"><input type="hidden" name="m" value="<?= $h($m) ?>">
    <div class="row"><label>Ab Tag<input type="date" name="start" value="<?= $h(max($first, date('Y-m-d')) <= date('Y-m-d', strtotime($first . ' +' . ($days - 1) . ' days')) ? max($first, date('Y-m-d')) : $first) ?>"></label>
      <label>Wenn nichts dabei steht<select name="kind"><option value="story">⭕ Story</option><option value="reel">🎬 Reel</option></select></label>
      <label>Konto<select name="acc"><?php foreach (IG_ACC as $k => $n): ?><option value="<?= $k ?>"><?= $n ?></option><?php endforeach; ?></select></label></div>
    <label>Deine Texte<textarea name="raw" rows="10" placeholder="Hier alle Texte einfügen."></textarea></label>
    <p class="tip">Jeder Text beginnt mit einer Zeile mit Datum, dahinter Story oder Reel und gern eine Überschrift, z. B. „2.11. Reel: Mein Morgen“. Ohne Datum: Texte mit einer Zeile „---“ trennen, sie kommen dann Tag für Tag ab dem gewählten Tag.</p>
    <div class="ig-b"><button class="b main">Eintragen</button></div>
  </form></details>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>, cache={};
  // ---- Kalender: Tag antippen → rechts (am Handy darunter) die Einträge ----
  const SK=<?= json_encode(array_values($sk), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;let skLang='de',skKind='story',skDay='';
  const esc=t=>String(t).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  function pickDay(d,scroll){document.querySelectorAll('.dd').forEach(b=>b.classList.toggle('sel',b.dataset.day===d));document.querySelectorAll('.iday').forEach(x=>x.hidden=x.dataset.iday!==d);
    $p('pickSk').hidden=true;try{history.replaceState(null,'','#d'+d)}catch(e){}if(scroll&&matchMedia('(max-width:860px)').matches){const x=document.querySelector('.iday:not([hidden])');if(x)x.scrollIntoView({behavior:'smooth',block:'start'})}}
  const $p=id=>document.getElementById(id);
  document.querySelectorAll('.dd').forEach(b=>b.onclick=()=>pickDay(b.dataset.day,true));
  const hd=(location.hash.match(/^#d(\d{4}-\d{2}-\d{2})/)||[])[1],days=[...document.querySelectorAll('.dd')].map(b=>b.dataset.day),td=new Date().toISOString().slice(0,10);
  pickDay(days.includes(hd)?hd:(days.includes(td)?td:days[0]),false);
  function skList(){const all=$p('pskAll').checked,L=SK.filter(x=>all||x.kind===skKind);
    $p('pskList').innerHTML=L.map((x,i)=>{const t=skLang==='en'?x.en_t:x.de_t,tx=skLang==='en'?x.en:x.de;return '<div class="sk'+(x.used?' used':'')+'"><div class="sk-h"><span class="sk-k">'+(x.kind==='reel'?'🎬 Reel':'⭕ Story')+' · '+esc(x.topic)+'</span>'+(x.used?'<span class="sk-u">schon verwendet</span>':'')+'</div><b>'+esc(t)+'</b><p>'+esc(tx).replace(/\n\n/g,'</p><p>')+'</p>'
      +'<div class="ig-b"><button type="button" class="b main" data-take="'+SK.indexOf(x)+'" data-rec="1">🎥 Nehmen &amp; aufnehmen</button><button type="button" class="b" data-take="'+SK.indexOf(x)+'">Nur eintragen</button></div></div>'}).join('')||'<p class="tip">Keine passenden Texte. Hake „auch die anderen Texte zeigen“ an.</p>';
    $p('pskList').querySelectorAll('[data-take]').forEach(b=>b.onclick=()=>{const x=SK[+b.dataset.take],f=$p('pskF');f.day.value=skDay;f.kind.value=skKind;f.title.value=skLang==='en'?x.en_t:x.de_t;f.text.value=skLang==='en'?x.en:x.de;f.studio.value=b.dataset.rec?'1':'';f.submit()})}
  document.querySelectorAll('[data-new]').forEach(b=>b.onclick=()=>{skKind=b.dataset.new;skDay=b.dataset.d;const ps=$p('pickSk');b.closest('.iday').appendChild(ps);ps.hidden=false;
    $p('pskT').textContent=(skKind==='reel'?'🎬 Reel':'⭕ Story')+' am '+b.closest('.iday').querySelector('h2').textContent.replace(' · heute','')+': welcher Text?';skList();ps.scrollIntoView({behavior:'smooth',block:'start'})});
  document.querySelectorAll('[data-lang2]').forEach(b=>b.onclick=()=>{skLang=b.dataset.lang2;document.querySelectorAll('[data-lang2]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));skList()});
  $p('pskAll').onchange=skList;$p('pskX').onclick=()=>{$p('pickSk').hidden=true};
  $p('pskOwn').onclick=()=>{const f=$p('pskF');f.day.value=skDay;f.kind.value=skKind;f.title.value='';f.text.value='';f.studio.value='';f.submit()};
  // Erst laden, dann teilen (das iPhone braucht das Teilen direkt nach dem Antippen)
  document.querySelectorAll('[data-share]').forEach(b=>b.onclick=async()=>{const id=b.dataset.share;
    if(!navigator.share||!navigator.canShare){location.href='?view=insta&dl='+id+'&save=1';return}
    if(cache[id]){try{await navigator.share({files:[cache[id]]});const f=new FormData();f.append('csrf',csrf);f.append('action','mm_ig_status');f.append('id',id);f.append('st','gepostet');f.append('ajax','1');
        if(confirm('Hast du es gepostet? Dann hake ich es als „gepostet“ ab.'))fetch(location.pathname,{method:'POST',body:f}).then(()=>location.reload())}catch(e){}return}
    const t=b.textContent;b.disabled=true;b.textContent='Lädt …';
    try{const r=await fetch('?view=insta&dl='+id,{credentials:'same-origin'});const bl=await r.blob();const cd=r.headers.get('Content-Disposition')||'';
      const nm=decodeURIComponent((cd.match(/filename\*=UTF-8''([^;]+)/)||[,'Instagram.mp4'])[1]);const f=new File([bl],nm,{type:bl.type||'video/mp4'});
      if(!navigator.canShare({files:[f]})){location.href='?view=insta&dl='+id+'&save=1';b.textContent=t;b.disabled=false;return}
      cache[id]=f;b.textContent='📲 Jetzt teilen';b.disabled=false;}catch(e){b.textContent=t;b.disabled=false}});
})();
</script>
<?php
        });
        exit;
    }
}

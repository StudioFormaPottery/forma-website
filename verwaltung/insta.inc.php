<?php
// „📱 Instagram täglich“ (Vivien, 28.10.2026; seit 30.10.2026 je Tag ein Thema mit Reel, Story, Channel-Nachricht + Foto-Idee): Talking Heads für Storys und Reels für den ganzen Monat planen,
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
    // Tagesthemen (Vivien, 30.10.2026): je Tag ein Thema mit Reel, Story und Channel-Nachricht (+ Foto-Idee)
    $cols = array_column($pdo->query('PRAGMA table_info(mm_insta)')->fetchAll(), 'name');
    foreach (['theme' => "TEXT NOT NULL DEFAULT ''", 'photo' => "TEXT NOT NULL DEFAULT ''", 'lang' => "TEXT NOT NULL DEFAULT ''"] as $c => $t)
        if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE mm_insta ADD COLUMN $c $t");
}
const IG_KIND = ['reel' => '🎬 Reel', 'story' => '⭕ Story', 'channel' => '💬 Channel', 'post' => '🖼 Beitrag'];
/** Tagesthemen aus verwaltung/insta-skripte.json */
function ig_themes(): array
{
    static $t = null;
    if ($t === null) { $t = []; foreach (json_decode((string)@file_get_contents(__DIR__ . '/insta-skripte.json'), true)['themes'] ?? [] as $x) $t[$x['id']] = $x; }
    return $t;
}
/** Legt für einen Tag die drei Einträge eines Themas an (vorhandene, noch nicht aufgenommene Themen-Einträge des Tages werden ersetzt). */
function ig_take_theme(PDO $pdo, string $day, array $th, string $lang, string $acc = 'movecia'): array
{
    $en = $lang === 'en';
    $pdo->prepare("DELETE FROM mm_insta WHERE day = ? AND theme <> '' AND file = '' AND status <> 'gepostet'")->execute([$day]);
    $ins = $pdo->prepare('INSERT INTO mm_insta (day, kind, acc, title, text, photo, theme, lang, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $ids = [];
    foreach (['reel', 'story', 'channel'] as $k) {
        $ins->execute([$day, $k, $acc, $en ? $th['en_t'] : $th['de_t'], (string)($th[$k . '_' . $lang] ?? ''), $k === 'channel' ? (string)($th['foto'] ?? '') : '', $th['id'], $lang, date('c'), date('c')]);
        $ids[$k] = (int)$pdo->lastInsertId();
    }
    return $ids;
}
/** Wie oft jedes Thema schon verwendet wurde */
function ig_theme_use(PDO $pdo): array
{
    $u = [];
    foreach ($pdo->query("SELECT theme, COUNT(DISTINCT day) n FROM mm_insta WHERE theme <> '' GROUP BY theme")->fetchAll() as $r) $u[$r['theme']] = (int)$r['n'];
    return $u;
}
function ig_dir(): string { $d = forma_data_dir() . '/insta'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function ig_get(PDO $pdo, int $id): ?array { $s = $pdo->prepare('SELECT * FROM mm_insta WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }
const IG_ACC = ['privat' => 'Privat', 'movecia' => 'Movecia', 'forma' => 'FORMA', 'mvig' => '@moveciamovement'];
/**
 * Zwei getrennte Instagram-Konten (Vivien, 30.10.2026): „mvig“ = neues Konto @moveciamovement (Mindful & Somatic Movement, nur Deutsch,
 * eigener Contentplan aus verwaltung/insta-studio-plan.json), alles andere = dein bisheriges Instagram mit den Tagesthemen.
 */
function ig_tab(): string { $a = (string)($_GET['a'] ?? $_POST['a'] ?? 'mvig'); return $a === 'mein' ? 'mein' : 'mvig'; }
function ig_acc_sql(string $tab): string { return $tab === 'mvig' ? "acc = 'mvig'" : "acc <> 'mvig'"; }
/** Contentplan für @moveciamovement einmal eintragen (neue Version = nur fehlende Einträge dazu). */
function ig_seed_mvig(PDO $pdo): void
{
    $d = json_decode((string)@file_get_contents(__DIR__ . '/insta-studio-plan.json'), true);
    $v = (int)($d['version'] ?? 0);
    if (!$v || (int)forma_setting($pdo, 'ig_mvig_plan') >= $v) return;
    $q = $pdo->prepare("SELECT COUNT(*) FROM mm_insta WHERE acc = 'mvig' AND day = ? AND title = ?");
    $ins = $pdo->prepare("INSERT INTO mm_insta (day, kind, acc, title, text, photo, lang, created_at, updated_at) VALUES (?,?,?,?,?,?,'de',?,?)");
    foreach ($d['items'] ?? [] as $x) {
        $q->execute([$x['day'], $x['title']]); if ((int)$q->fetchColumn()) continue;
        $ins->execute([$x['day'], isset(IG_KIND[$x['kind']]) ? $x['kind'] : 'post', 'mvig', $x['title'], $x['text'], (string)($x['photo'] ?? ''), date('c'), date('c')]);
    }
    forma_set_setting($pdo, 'ig_mvig_plan', (string)$v);
}
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
    ig_seed_mvig($pdo);
    $igBack = strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?') . '?view=insta&a=' . ig_tab() . '&m=' . preg_replace('/[^0-9-]/', '', (string)($_POST['m'] ?? date('Y-m')));
    $igJson = function (array $x): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($x); exit; };

    if ($action === 'mm_ig_save') {
        $id = (int)($_POST['id'] ?? 0);
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['day'] ?? '')) ? $_POST['day'] : date('Y-m-d');
        $kind = isset(IG_KIND[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'story';
        $acc = isset(IG_ACC[$_POST['acc'] ?? '']) ? $_POST['acc'] : 'privat';
        $vals = [$day, $kind, $acc, trim((string)($_POST['title'] ?? '')), trim((string)($_POST['text'] ?? '')), trim((string)($_POST['music'] ?? '')), trim((string)($_POST['photo'] ?? '')), date('c')];
        if ($id && ig_get($pdo, $id)) { $pdo->prepare('UPDATE mm_insta SET day=?, kind=?, acc=?, title=?, text=?, music=?, photo=?, updated_at=? WHERE id=?')->execute([...$vals, $id]); }
        else { $pdo->prepare('INSERT INTO mm_insta (day, kind, acc, title, text, music, photo, updated_at, created_at) VALUES (?,?,?,?,?,?,?,?,?)')->execute([...$vals, date('c')]); $id = (int)$pdo->lastInsertId(); }
        if (!empty($_POST['studio'])) go(strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?') . '?view=studio&ig=' . $id);
        go($igBack . '#d' . $day, 'Gespeichert.');
    }
    // Thema für einen Tag nehmen (Reel + Story + Channel auf einmal)
    if ($action === 'mm_ig_theme') {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['day'] ?? '')) ? $_POST['day'] : date('Y-m-d');
        $th = ig_themes()[(string)($_POST['theme'] ?? '')] ?? null;
        if (!$th) go($igBack . '#d' . $day, 'Thema nicht gefunden.');
        $ids = ig_take_theme($pdo, $day, $th, ($_POST['lang'] ?? '') === 'de' ? 'de' : 'en', 'privat');
        $rec = (string)($_POST['rec'] ?? '');
        if (isset($ids[$rec]) && $rec !== 'channel') go(strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?') . '?view=studio&ig=' . $ids[$rec]);
        go($igBack . '#d' . $day, 'Thema eingetragen: Reel, Story und Channel.');
    }
    // Alle freien Tage bis Monatsende mit verschiedenen Themen füllen (zuerst die noch nie verwendeten)
    if ($action === 'mm_ig_fill') {
        $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['start'] ?? '')) ? $_POST['start'] : date('Y-m-d');
        $end = date('Y-m-t', strtotime($start)); $lang = ($_POST['lang'] ?? '') === 'de' ? 'de' : 'en';
        $use = ig_theme_use($pdo); $list = array_values(array_filter(ig_themes(), fn($x) => empty($x['promo'])));
        usort($list, fn($a, $b) => ($use[$a['id']] ?? 0) <=> ($use[$b['id']] ?? 0));
        $busy = $pdo->prepare("SELECT COUNT(*) FROM mm_insta WHERE day = ? AND acc <> 'mvig'"); $n = 0; $i = 0;
        for ($d = $start; $d <= $end && $list; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $busy->execute([$d]); if ((int)$busy->fetchColumn() > 0) continue;
            ig_take_theme($pdo, $d, $list[$i % count($list)], $lang, 'privat'); $i++; $n++;
        }
        go(strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?') . '?view=insta&a=mein&m=' . substr($start, 0, 7), $n ? $n . ' Tage mit Themen gefüllt.' : 'Alle Tage haben schon ein Thema.');
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
        go(strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?') . '?view=insta&a=' . ($acc === 'mvig' ? 'mvig' : 'mein') . '&m=' . $m, count($list) . ' Einträge eingetragen.');
    }
    // Video in Stücken (8 MB) hochladen: n = 0 legt neu an, last = 1 schließt ab
    if ($action === 'mm_ig_up') {
        $r = ig_get($pdo, (int)($_POST['id'] ?? 0));
        if (!$r) $igJson(['error' => 'Eintrag nicht gefunden.']);
        $f = $_FILES['b'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) $igJson(['error' => 'Upload-Stück fehlt (Fehler ' . (int)($f['error'] ?? -1) . ').']);
        $ext = in_array($_POST['ext'] ?? '', ['webm', 'mov'], true) ? (string)$_POST['ext'] : 'mp4'; // .mov = vom iPhone fertig gestellt (Ton unkomprimiert)
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
        header('Content-Type: video/' . ($ext === 'webm' ? 'webm' : ($ext === 'mov' ? 'quicktime' : 'mp4')));
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
        $tab = ig_tab(); $accW = ig_acc_sql($tab);
        $ord = "CASE kind WHEN 'post' THEN 0 WHEN 'reel' THEN 1 WHEN 'story' THEN 2 ELSE 3 END, id";
        $s = $pdo->prepare("SELECT * FROM mm_insta WHERE $accW AND day BETWEEN ? AND ? ORDER BY day, $ord"); $s->execute([$first, $last]);
        $by = []; foreach ($s->fetchAll() as $r) $by[$r['day']][] = $r;
        $today = date('Y-m-d');
        $ts = $pdo->prepare("SELECT * FROM mm_insta WHERE $accW AND day = ? ORDER BY $ord"); $ts->execute([$today]); $todayList = $ts->fetchAll();
        $cnt = ['plan' => 0, 'post' => 0, 'free' => 0];
        foreach ($by as $l) foreach ($l as $r) { $cnt['plan']++; if ($r['status'] === 'gepostet') $cnt['post']++; }
        for ($i = 1; $i <= $days; $i++) { $d = $m . '-' . sprintf('%02d', $i); if ($d >= $today && empty($by[$d])) $cnt['free']++; }
        $mon = ['Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $mName = $mon[(int)substr($m, 5, 2) - 1] . ' ' . substr($m, 0, 4);
        $prev = date('Y-m', strtotime($first . ' -1 month')); $next = date('Y-m', strtotime($first . ' +1 month'));
        $self = strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?');
        $themes = ig_themes(); $use = ig_theme_use($pdo);
        $TH = [];
        foreach ($themes as $x) $TH[] = $x + ['used' => $use[$x['id']] ?? 0];
        $fillFrom = max($first, $today);
        render_page('Instagram täglich', function () use ($self, $m, $first, $days, $by, $today, $todayList, $cnt, $mName, $prev, $next, $TH, $themes, $fillFrom, $last, $tab) {
            $h = 'forma_h'; $mv = $tab === 'mvig'; $qa = 'a=' . $tab . '&amp;';
            $hid = fn(string $a, array $f) => '<input type="hidden" name="csrf" value="' . $h(csrf()) . '"><input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="m" value="' . $h($m) . '"><input type="hidden" name="a" value="' . $h($tab) . '">'
                . implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . $k . '" value="' . $h((string)$v) . '">', array_keys($f), $f));
            $card = function (array $r, bool $big = false) use ($self, $h, $hid) {
                $st = $r['status']; $has = $r['file'] !== ''; $ch = in_array($r['kind'], ['channel', 'post'], true);
                $lab = ['reel' => '🎬 Reel Talking Head', 'story' => '⭕ Story Talking Head', 'channel' => '💬 Channel-Nachricht', 'post' => '🖼 Beitrag · Karussell'][$r['kind']] ?? '⭕ Story';
                ob_start(); ?>
<div class="ig k2-<?= $h($r['kind']) ?><?= $big ? ' big' : '' ?> st-<?= $h($st) ?>" id="e<?= (int)$r['id'] ?>">
  <div class="ig-top"><span class="kind k-<?= $h($r['kind']) ?>"><?= $lab ?></span>
    <span class="stt"><?= $st === 'gepostet' ? '✓ gepostet' : ($ch ? '○ noch posten' : ($has ? '🎥 aufgenommen' : ($r['text'] === '' ? '✍️ Text fehlt' : '○ noch aufnehmen'))) ?></span></div>
  <?php if ($r['theme'] === ''): ?><b class="tt"><?= $h(ig_title($r)) ?></b><?php endif; ?>
  <?php if ($ch): ?>
    <div class="txt" data-txt><?= nl2br($h($r['text'])) ?></div>
    <?php if ($r['photo'] !== ''): ?><div class="foto"><span>📷 <?= $r['kind'] === 'post' ? 'Bild-Idee' : 'Foto-Idee' ?></span><?= $h($r['photo']) ?></div><?php endif; ?>
  <?php else: ?>
    <details class="txtd"<?= $big ? ' open' : '' ?>><summary>Text ansehen</summary><div class="txt" data-txt><?= nl2br($h($r['text'])) ?></div></details>
    <?php if ($r['photo'] !== ''): ?><div class="foto"><span>🎬 Bild-Idee</span><?= $h($r['photo']) ?></div><?php endif; ?>
    <?php if ($r['music'] !== ''): ?><p class="mu">🎵 <?= $h($r['music']) ?></p><?php endif; ?>
    <?php if ($has): ?><video src="<?= $h($self) ?>?view=insta&amp;dl=<?= (int)$r['id'] ?>#t=0.1" controls playsinline preload="metadata"></video><?php endif; ?>
  <?php endif; ?>
  <div class="ig-b">
    <?php if ($ch): ?>
      <button type="button" class="b main" data-copy>📋 Text kopieren</button>
    <?php else: ?>
      <?php if ($has): ?><button type="button" class="b main" data-share="<?= (int)$r['id'] ?>">📲 Teilen → Instagram</button><?php endif; ?>
      <a class="b <?= $has ? '' : 'rec' ?>" href="<?= $h($self) ?>?view=studio&amp;ig=<?= (int)$r['id'] ?>"><?= $has ? '↺ Neu aufnehmen' : '🎥 Aufnehmen' ?></a>
      <button type="button" class="b sm" data-copy title="Text als Bildunterschrift kopieren">📋 Caption</button>
    <?php endif; ?>
    <?php if ($st !== 'gepostet'): ?><form method="post"><?= $hid('mm_ig_status', ['id' => $r['id'], 'st' => 'gepostet']) ?><button class="b sm">✓ Gepostet</button></form>
    <?php else: ?><form method="post"><?= $hid('mm_ig_status', ['id' => $r['id'], 'st' => $has ? 'aufgenommen' : 'offen']) ?><button class="b sm">doch nicht gepostet</button></form><?php endif; ?>
  </div>
  <details class="ed"><summary>✏️ Ändern</summary>
    <form method="post" class="f"><?= $hid('mm_ig_save', ['id' => $r['id'], 'kind' => $r['kind'], 'acc' => $r['acc']]) ?>
      <div class="row"><label>Tag<input type="date" name="day" value="<?= $h($r['day']) ?>"></label><label>Überschrift<input name="title" value="<?= $h($r['title']) ?>"></label></div>
      <label><?= $r['kind'] === 'post' ? 'Folien und Caption' : ($ch ? 'Text für den Kanal' : 'Text für den Teleprompter <small>(Leerzeile = neuer Absatz)</small>') ?><textarea name="text" rows="7"><?= $h($r['text']) ?></textarea></label>
      <label><?= $ch ? 'Foto-Idee' : 'Bild-Idee' ?><input name="photo" value="<?= $h($r['photo']) ?>"></label>
      <?php if (!$ch): ?><label>Musik <small>(Liedname, den du in Instagram dazu wählst)</small><input name="music" value="<?= $h($r['music']) ?>"></label><?php endif; ?>
      <div class="ig-b"><button class="b main">Speichern</button><?php if (!$ch): ?><button class="b" name="studio" value="1">Speichern &amp; aufnehmen</button><?php endif; ?></div>
    </form>
    <div class="ig-b" style="margin-top:8px">
      <?php if ($has): ?><form method="post" onsubmit="return confirm('Video löschen? Der Text bleibt.')"><?= $hid('mm_ig_delvid', ['id' => $r['id']]) ?><button class="b sm">Video löschen</button></form><?php endif; ?>
      <form method="post" onsubmit="return confirm('Diesen Eintrag löschen?')"><?= $hid('mm_ig_del', ['id' => $r['id']]) ?><button class="b sm">Eintrag löschen</button></form>
    </div>
  </details>
</div>
<?php return ob_get_clean(); };
            // Kopf eines Tages: Thema + drei Karten
            $dayBlock = function (string $d, array $l, bool $big = false) use ($card, $h, $themes) {
                $th = ''; $top = '';
                foreach ($l as $r) if ($r['theme'] !== '') { $th = $r['theme']; $top = $r['title']; break; }
                ob_start();
                if ($th !== ''): ?>
<div class="thead"><span class="chip"><?= $h($themes[$th]['topic'] ?? 'Thema') ?></span><b><?= $h($top) ?></b>
  <button type="button" class="b sm" data-pick="<?= $h($d) ?>">↺ Anderes Thema</button></div>
<?php endif;
                if ($l): ?><div class="dc"><?php foreach ($l as $r) echo $card($r, $big); ?></div><?php endif;
                return ob_get_clean();
            };
            ?>
<style>
body{background:#F2ECE4}
main{max-width:1240px}
.ih h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;color:#4A3A41;font-size:clamp(34px,4vw,46px);margin:6px 0 4px}
.ih p{margin:0 0 16px;color:#6B5658;font-size:19px;max-width:76ch;line-height:1.5}
.box{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:24px;padding:16px;margin-bottom:16px}
.box>h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:28px;color:#4A3A41;margin:0 0 10px}
.mnav{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0 14px}
.mnav b{font:500 30px/1.1 "Cormorant Garamond",Georgia,serif;color:#4A3A41;margin:0 6px}
.mnav a{font-size:20px;text-decoration:none;color:#4A3A41;border:1.5px solid #DDD2C6;background:#fff;border-radius:999px;padding:6px 16px}
.b{font:inherit;font-size:17px;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:46px;padding:9px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;text-decoration:none}
.b.main{background:#7D5E68;border-color:#7D5E68;color:#fff}.b.rec{background:#A0413B;border-color:#A0413B;color:#fff}.b.sm{min-height:40px;font-size:15px;color:#7D7076}
.b.big{min-height:56px;font-size:19px;padding:12px 22px}
.dc{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;align-items:start}
.ig{min-width:0;background:#fff;border:1.5px solid #DDD2C6;border-radius:20px;padding:14px;display:flex;flex-direction:column;gap:10px}
.ig.k2-reel{border-top:5px solid #4A3A41}.ig.k2-story{border-top:5px solid #C9A9B4}.ig.k2-channel{border-top:5px solid #B7C4AE;background:#FBFCF8}
.ig.st-gepostet{background:#F3F7F1;border-color:#C8D8C3}
.ig-top{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:15px}
.kind{padding:4px 12px;border-radius:999px;font-weight:500;font-size:16px}.k-story{background:#EADFDE;color:#6B5658}.k-reel{background:#4A3A41;color:#fff}.k-channel{background:#E3EBDD;color:#4E5E45}
.stt{margin-left:auto;color:#6B5658}
.tt{font-weight:500;font-size:18px;color:#2E2529;line-height:1.35}
.txt{font-size:16px;line-height:1.6;color:#2E2529;max-height:330px;overflow:auto;padding-right:4px}
.txtd>summary{cursor:pointer;color:#7D5E68;font-size:16px}
.txtd .txt{margin-top:8px}
.foto{background:#F6F1EA;border-radius:14px;padding:10px 12px;font-size:16px;line-height:1.5;color:#4A3A41}.foto span{display:block;font-size:14px;color:#7D5E68;margin-bottom:2px}
.mu{margin:0;font-size:16px;color:#7D5E68}
.ig video{width:100%;max-width:170px;aspect-ratio:9/16;object-fit:cover;border-radius:14px;background:#2E2529}
.ig-b{display:flex;flex-wrap:wrap;gap:8px}.ig-b form{margin:0}
.ed>summary{cursor:pointer;color:#7D7076;font-size:15px;list-style:none}.ed>summary::-webkit-details-marker{display:none}
.f{display:flex;flex-direction:column;gap:10px;margin-top:10px}
.f label{display:flex;flex-direction:column;gap:4px;font-size:16px;color:#6B5658}.f label small{color:#7D7076;font-size:14px}
.f input,.f select,.f textarea{font:inherit;font-size:17px;padding:10px 12px;border:1.5px solid #DDD2C6;border-radius:12px;background:#fff;color:#2E2529}
.f textarea{line-height:1.5}
.f .row{display:flex;flex-wrap:wrap;gap:10px}.f .row label{flex:1 1 160px}
.tip{font-size:16px;color:#7D7076;line-height:1.5;margin:6px 0 0}
.thead{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin:0 0 12px}
.thead b{font:500 26px/1.15 "Cormorant Garamond",Georgia,serif;color:#4A3A41}.thead .b{margin-left:auto}
.chip{display:inline-block;padding:4px 12px;border-radius:999px;background:#EADFDE;color:#6B5658;font-size:14px;letter-spacing:.02em}
/* Kalender */
.ical-wrap{display:grid;grid-template-columns:minmax(300px,380px) minmax(0,1fr);gap:20px;align-items:start}
@media(max-width:900px){.ical-wrap{grid-template-columns:minmax(0,1fr)}}
.iday-w,.ical{min-width:0}
.ical{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:24px;padding:12px;position:sticky;top:70px}
@media(max-width:900px){.ical{position:static}}
.ical .mnav{margin:0 0 8px;justify-content:space-between}.ical .mnav b{font-size:24px}
.ical-g{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px}
.ical-g .wd{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#7D7076;text-align:center;padding-bottom:2px}
.dd{aspect-ratio:1/1.1;min-width:0;border-radius:14px;border:1.5px solid #EFE6E1;background:#fff;font:inherit;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:4px 2px;gap:2px;color:#4A3A41}
.dd .n{font-size:15px}.dd.past{opacity:.55}.dd.today{border-color:#7D5E68}.dd.sel{background:#7D5E68;border-color:#7D5E68;color:#fff}
.dd.has{background:#F6EEF1}.dd.has.sel{background:#7D5E68}
.dd .dots{display:flex;gap:2px}.dd .dots i{width:7px;height:7px;border-radius:50%;background:#C9A9B4}.dd .dots i.p{background:#8FB08A}.dd .dots i.a{background:#7D5E68}
.dd.sel .dots i{background:#fff}.dd.sel .dots i.p{background:#CFE0C9}
.ical-leg{display:flex;flex-wrap:wrap;gap:4px 12px;margin:10px 2px 0;font-size:12px;color:#7D7076}.ical-leg i{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:4px;vertical-align:0}
.ical .cnt{margin:8px 2px 0;font-size:14px;color:#6B5658}
.fill{margin-top:12px;padding:12px;border-radius:18px;background:#fff;border:1.5px dashed #C9A9B4}
.fill p{margin:0 0 8px;font-size:15px;color:#6B5658;line-height:1.45}.fill .row2{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.fill select{font:inherit;font-size:16px;padding:8px 10px;border-radius:12px;border:1.5px solid #DDD2C6;background:#fff}
.iday h2{font:500 30px/1.15 "Cormorant Garamond",Georgia,serif;color:#4A3A41;margin:0 0 12px}
.empty{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:22px;padding:18px;text-align:center}
.empty p{margin:0 0 12px;color:#6B5658;font-size:17px}
.own{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
/* Themen-Auswahl */
.pick{margin-top:14px;background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:24px;padding:14px}
.pk-h{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.pk-h b{font:500 25px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.seg2{display:flex;gap:6px}.seg2 button{font:inherit;font-size:16px;padding:8px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:42px}
.seg2 button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.tl{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;margin-top:12px}
.tc{font:inherit;text-align:left;cursor:pointer;background:#fff;border:1.5px solid #E6DCD2;border-radius:18px;padding:12px;display:flex;flex-direction:column;gap:6px;color:#2E2529}
.tc:hover{border-color:#C9A9B4}.tc.used{opacity:.62}.tc b{font:500 21px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.tc small{font-size:14px;line-height:1.45;color:#6B5658}.tc .u{font-size:12px;color:#7D7076}
.tv{margin-top:12px;background:#fff;border:1.5px solid #C9A9B4;border-radius:22px;padding:14px}
.tv-h{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}.tv-h b{font:500 26px/1.15 "Cormorant Garamond",Georgia,serif;color:#4A3A41;margin-right:auto}
.tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}.tabs button{font:inherit;font-size:16px;padding:8px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:42px}
.tabs button[aria-pressed=true]{background:#4A3A41;border-color:#4A3A41;color:#fff}
.tv .txt{background:#FBF8F4;border-radius:14px;padding:12px;max-height:300px}
.tv .ig-b{margin-top:12px}
.todayw .dc{grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
.acct{display:flex;flex-wrap:wrap;gap:10px;margin:6px 0 14px}.acct a{flex:1 1 240px;display:flex;flex-direction:column;gap:2px;text-decoration:none;padding:12px 16px;border-radius:20px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41}
.acct a b{font-size:19px;font-weight:500}.acct a span{font-size:15px;color:#7D7076}.acct a.on{background:#4A3040;border-color:#4A3040;color:#fff}.acct a.on span{color:#E6DBCF}
.ig.k2-post{border-top:5px solid #7D5E68}.k-post{background:#F1E6EA;color:#4A3040}
@media(max-width:640px){.dc{grid-template-columns:minmax(0,1fr)}.thead .b{margin-left:0}.tl{grid-template-columns:minmax(0,1fr)}}
</style>
<?php mm_subnav('insta'); ?>
<div class="ih"><h1>📱 Instagram</h1>
  <div class="acct" role="tablist"><a href="<?= $h($self) ?>?view=insta&amp;a=mvig&amp;m=<?= $h($m) ?>" class="<?= $mv ? 'on' : '' ?>"><b>🌿 @moveciamovement</b><span>Mindful &amp; Somatic Movement · Deutsch</span></a><a href="<?= $h($self) ?>?view=insta&amp;a=mein&amp;m=<?= $h($m) ?>" class="<?= $mv ? '' : 'on' ?>"><b>🤍 Dein Instagram</b><span>Tagesthemen · Deutsch &amp; Englisch</span></a></div>
  <?php if ($mv): ?><p>Der Contentplan für dein Movecia-Konto: montags ein Karussell mit Wissen, mittwochs ein Reel, freitags ein Beitrag zu einem Angebot, dazu dienstags und samstags eine Story. Bei Beiträgen kopierst du einfach Folien und Caption, Reels und Storys nimmst du im Studio auf. Link in Bio: <b>moveciamovement.com/bio</b> <button type="button" class="b sm" data-copylink="https://moveciamovement.com/bio">📋 Link kopieren</button></p>
  <?php else: ?><p>Jeden Tag ein Thema aus deinem Leben: dazu ein Reel und eine Story als Talking Head und eine Nachricht für deinen Instagram- und WhatsApp-Kanal mit einer Foto-Idee. Aufnehmen kannst du in Ruhe im Studio, am Tag selbst tippst du nur noch auf „Teilen“ bzw. „Text kopieren“.</p><?php endif; ?></div>

<?php if ($todayList): ?>
<div class="box todayw"><h2>Heute · <?= $h(ig_wd($today, true)) ?></h2><?= $dayBlock($today, $todayList, true) ?>
  <p class="tip">„Teilen“ öffnet am iPhone das Teilen-Menü: dort Instagram wählen, dann Story oder Reel. Musik wählst du in Instagram dazu.</p></div>
<?php endif; ?>

<div class="ical-wrap">
  <div class="ical">
    <div class="mnav"><a href="<?= $h($self) ?>?view=insta&amp;<?= $qa ?>m=<?= $h($prev) ?>" aria-label="Monat davor">‹</a><b><?= $h($mName) ?></b><a href="<?= $h($self) ?>?view=insta&amp;<?= $qa ?>m=<?= $h($next) ?>" aria-label="nächster Monat">›</a></div>
    <div class="ical-g">
      <?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $w): ?><span class="wd"><?= $w ?></span><?php endforeach; ?>
      <?php for ($i = 0; $i < ((int)date('N', strtotime($first)) - 1); $i++): ?><span></span><?php endfor; ?>
      <?php for ($i = 1; $i <= $days; $i++): $d = $m . '-' . sprintf('%02d', $i); $l = $by[$d] ?? []; ?>
      <button type="button" class="dd<?= $d === $today ? ' today' : '' ?><?= $d < $today ? ' past' : '' ?><?= $l ? ' has' : '' ?>" data-day="<?= $h($d) ?>"><span class="n"><?= $i ?></span><span class="dots"><?php foreach ($l as $r): ?><i class="<?= $r['status'] === 'gepostet' ? 'p' : ($r['file'] !== '' ? 'a' : '') ?>" title="<?= $h(IG_KIND[$r['kind']] ?? '') ?>"></i><?php endforeach; ?></span></button>
      <?php endfor; ?>
    </div>
    <div class="ical-leg"><span><i style="background:#C9A9B4"></i>geplant</span><span><i style="background:#7D5E68"></i>aufgenommen</span><span><i style="background:#8FB08A"></i>gepostet</span></div>
    <p class="cnt"><?= $cnt['plan'] ?> geplant · <?= $cnt['post'] ?> gepostet</p>
    <?php if (!$mv && $cnt['free'] > 0 && $TH): ?>
    <form method="post" class="fill"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_fill"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="a" value="mein"><input type="hidden" name="start" value="<?= $h($fillFrom) ?>">
      <p>✨ <?= $cnt['free'] ?> Tage haben noch kein Thema. Ich fülle sie mit verschiedenen Themen, die du noch nicht verwendet hast.</p>
      <div class="row2"><select name="lang" data-langsel><option value="en">auf Englisch</option><option value="de">auf Deutsch</option></select><button class="b main">Freie Tage füllen</button></div>
    </form>
    <?php endif; ?>
  </div>
  <div class="iday-w">
    <?php for ($i = 1; $i <= $days; $i++): $d = $m . '-' . sprintf('%02d', $i); $l = $by[$d] ?? []; ?>
    <div class="iday" data-iday="<?= $h($d) ?>" hidden>
      <h2><?= $h(ig_wd($d, true)) ?><?= $d === $today ? ' · heute' : '' ?></h2>
      <?php if ($l): ?><?= $dayBlock($d, $l) ?>
      <?php elseif ($mv): ?><div class="empty"><p>An diesem Tag ist nichts geplant. Ruhetage sind ausdrücklich erlaubt.</p></div>
      <?php else: ?><div class="empty"><p>Für diesen Tag gibt es noch kein Thema.</p><button type="button" class="b main big" data-pick="<?= $h($d) ?>">✨ Thema für diesen Tag wählen</button></div><?php endif; ?>
      <div class="own"><?php if ($mv): ?><button type="button" class="b sm" data-own="post" data-d="<?= $h($d) ?>">＋ Beitrag</button><?php endif; ?><button type="button" class="b sm" data-own="reel" data-d="<?= $h($d) ?>">＋ <?= $mv ? 'Reel' : 'eigenes Reel' ?></button><button type="button" class="b sm" data-own="story" data-d="<?= $h($d) ?>">＋ <?= $mv ? 'Story' : 'eigene Story' ?></button><?php if (!$mv): ?><button type="button" class="b sm" data-own="channel" data-d="<?= $h($d) ?>">＋ eigene Channel-Nachricht</button><?php endif; ?></div>
    </div>
    <?php endfor; ?>
    <div class="pick" id="pick" hidden>
      <div class="pk-h"><b id="pkT">Welches Thema?</b><div class="seg2"><button type="button" data-lang2="en" aria-pressed="true">Englisch</button><button type="button" data-lang2="de" aria-pressed="false">Deutsch</button></div><button type="button" class="b sm" id="pkX">Schließen</button></div>
      <div class="tv" id="tv" hidden></div>
      <div class="tl" id="tl"></div>
      <form method="post" id="pkF"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_theme"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="a" value="mein">
        <input type="hidden" name="day"><input type="hidden" name="theme"><input type="hidden" name="lang"><input type="hidden" name="rec"></form>
      <form method="post" id="ownF"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_save"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="a" value="<?= $h($tab) ?>"><input type="hidden" name="acc" value="<?= $mv ? 'mvig' : 'privat' ?>">
        <input type="hidden" name="day"><input type="hidden" name="kind"><input type="hidden" name="studio"></form>
    </div>
  </div>
</div>

<details class="box" style="margin-top:18px"><summary style="cursor:pointer;font:500 22px/1.2 'Cormorant Garamond',Georgia,serif;color:#4A3A41;list-style:none">✍️ Eigene Texte für mehrere Tage einfügen</summary>
  <form method="post" class="f"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_ig_bulk"><input type="hidden" name="m" value="<?= $h($m) ?>"><input type="hidden" name="acc" value="<?= $mv ? 'mvig' : 'privat' ?>">
    <div class="row"><label>Ab Tag<input type="date" name="start" value="<?= $h($fillFrom <= $last ? $fillFrom : $first) ?>"></label>
      <label>Wenn nichts dabei steht<select name="kind"><option value="story">⭕ Story</option><option value="reel">🎬 Reel</option></select></label></div>
    <label>Deine Texte<textarea name="raw" rows="8" placeholder="Hier alle Texte einfügen."></textarea></label>
    <p class="tip">Jeder Text beginnt mit einer Zeile mit Datum, dahinter Story oder Reel, z. B. „2.11. Reel: Mein Morgen“. Ohne Datum: Texte mit einer Zeile „---“ trennen, sie kommen dann Tag für Tag.</p>
    <div class="ig-b"><button class="b main">Eintragen</button></div>
  </form></details>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>, cache={}, $p=id=>document.getElementById(id);
  const TH=<?= json_encode(array_values($TH), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const esc=t=>String(t).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const para=t=>'<p>'+esc(t).replace(/\n\n+/g,'</p><p>').replace(/\n/g,'<br>')+'</p>';
  let lang='en',pDay='',open=null,tab='reel';
  try{lang=localStorage.getItem('ig-lang')==='de'?'de':'en'}catch(e){}
  document.querySelectorAll('[data-langsel]').forEach(s=>s.value=lang);
  // ---- Kalender ----
  function pickDay(d,scroll){document.querySelectorAll('.dd').forEach(b=>b.classList.toggle('sel',b.dataset.day===d));document.querySelectorAll('.iday').forEach(x=>x.hidden=x.dataset.iday!==d);
    $p('pick').hidden=true;try{history.replaceState(null,'','#d'+d)}catch(e){}if(scroll&&matchMedia('(max-width:900px)').matches){const x=document.querySelector('.iday:not([hidden])');if(x)x.scrollIntoView({behavior:'smooth',block:'start'})}}
  document.querySelectorAll('.dd').forEach(b=>b.onclick=()=>pickDay(b.dataset.day,true));
  const hd=(location.hash.match(/^#d(\d{4}-\d{2}-\d{2})/)||[])[1],days=[...document.querySelectorAll('.dd')].map(b=>b.dataset.day),td=<?= json_encode($today) ?>;
  pickDay(days.includes(hd)?hd:(days.includes(td)?td:days[0]),false);
  // ---- Themen-Auswahl ----
  function list(){const L=TH.slice().sort((a,b)=>(!a.promo-!b.promo)||((a.used>0)-(b.used>0)));
    $p('tl').innerHTML=L.map(x=>'<button type="button" class="tc'+(x.used?' used':'')+'" data-t="'+esc(x.id)+'"><span class="chip">'+(x.promo?'🎁 ':'')+esc(x.topic)+'</span><b>'+esc(lang==='en'?x.en_t:x.de_t)+'</b><small>'+esc((lang==='en'?x.story_en:x.story_de).split(/\n/)[0].slice(0,120))+' …</small>'+(x.used?'<span class="u">schon '+x.used+'× verwendet</span>':'')+'</button>').join('');
    $p('tl').querySelectorAll('[data-t]').forEach(b=>b.onclick=()=>{open=TH.find(x=>x.id===b.dataset.t);tab='reel';view();$p('tv').scrollIntoView({behavior:'smooth',block:'start'})})}
  function view(){const tv=$p('tv');if(!open){tv.hidden=true;return}const x=open,L=lang;
    const parts={reel:x['reel_'+L],story:x['story_'+L],channel:x['channel_'+L]};
    tv.innerHTML='<div class="tv-h"><span class="chip">'+esc(x.topic)+'</span><b>'+esc(L==='en'?x.en_t:x.de_t)+'</b></div>'
      +'<div class="tabs">'+[['reel','🎬 Reel'],['story','⭕ Story'],['channel','💬 Channel & Foto']].map(([k,n])=>'<button type="button" data-tab="'+k+'" aria-pressed="'+(tab===k)+'">'+n+'</button>').join('')+'</div>'
      +'<div class="txt">'+para(parts[tab]||'')+'</div>'+(tab==='channel'&&x.foto?'<div class="foto" style="margin-top:10px"><span>📷 Foto-Idee</span>'+esc(x.foto)+'</div>':'')
      +'<div class="ig-b"><button type="button" class="b main big" data-take>✓ Dieses Thema nehmen</button><button type="button" class="b" data-take="reel">🎥 Nehmen &amp; Reel aufnehmen</button><button type="button" class="b sm" data-back>Andere Themen</button></div>'
      +'<p class="tip">Du bekommst für den Tag alle drei: Reel, Story und Channel-Nachricht mit Foto-Idee.</p>';
    tv.hidden=false;
    tv.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>{tab=b.dataset.tab;view()});
    tv.querySelector('[data-back]').onclick=()=>{open=null;view()};
    tv.querySelectorAll('[data-take]').forEach(b=>b.onclick=()=>{const f=$p('pkF');f.day.value=pDay;f.theme.value=x.id;f.lang.value=lang;f.rec.value=b.dataset.take||'';b.disabled=true;f.submit()})}
  function openPick(d,host){pDay=d;open=null;const ps=$p('pick');host.appendChild(ps);ps.hidden=false;
    $p('pkT').textContent='Thema für '+host.querySelector('h2').textContent.replace(' · heute','');list();view();ps.scrollIntoView({behavior:'smooth',block:'start'})}
  document.querySelectorAll('[data-pick]').forEach(b=>b.onclick=()=>openPick(b.dataset.pick,document.querySelector('.iday[data-iday="'+b.dataset.pick+'"]')||b.closest('.box')));
  document.querySelectorAll('[data-lang2]').forEach(b=>{b.setAttribute('aria-pressed',String(b.dataset.lang2===lang));b.onclick=()=>{lang=b.dataset.lang2;try{localStorage.setItem('ig-lang',lang)}catch(e){}
    document.querySelectorAll('[data-lang2]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));document.querySelectorAll('[data-langsel]').forEach(s=>s.value=lang);list();view()}});
  document.querySelectorAll('[data-langsel]').forEach(s=>s.onchange=()=>{try{localStorage.setItem('ig-lang',s.value)}catch(e){}});
  $p('pkX').onclick=()=>{$p('pick').hidden=true};
  document.querySelectorAll('[data-own]').forEach(b=>b.onclick=()=>{const f=$p('ownF');f.day.value=b.dataset.d;f.kind.value=b.dataset.own;f.studio.value='';f.submit()});
  document.querySelectorAll('[data-copylink]').forEach(b=>b.onclick=async()=>{try{await navigator.clipboard.writeText(b.dataset.copylink)}catch(e){}const o=b.textContent;b.textContent='✓ Kopiert';setTimeout(()=>b.textContent=o,1800)});
  // ---- Text kopieren (Channel-Nachricht oder Caption) ----
  document.querySelectorAll('[data-copy]').forEach(b=>b.onclick=async()=>{const el=b.closest('.ig').querySelector('[data-txt]');const t=el?el.innerText.trim():'';const o=b.textContent;
    try{await navigator.clipboard.writeText(t)}catch(e){const a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();document.execCommand('copy');a.remove()}
    b.textContent='✓ Kopiert';setTimeout(()=>b.textContent=o,1800)});
  // ---- Video teilen (erst laden, dann teilen – das iPhone braucht das Teilen direkt nach dem Antippen) ----
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

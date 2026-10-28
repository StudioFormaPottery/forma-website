<?php
// „🎵 Musik“ (Vivien, 28.10.2026): eigener Musik-Ordner auf dem Server (z. B. Lieder von Epidemic Sound),
// im Studio unter „Bearbeiten → Musik“ mit einem Tipp wählbar. Dateien in ../forma-data/musik/ (nie im Repo,
// gekaufte Musik darf nicht öffentlich liegen), nur angemeldet abrufbar. ?view=musik, Aktionen mm_mu_*.
declare(strict_types=1);

const MU_TAGS = ['morgen' => '☀️ Morgen', 'abend' => '🌙 Abend', 'flow' => '🧘 Flow & Bewegung', 'meditation' => '🕯️ Meditation & Atem', 'reel' => '📱 Reel & Story', 'lebendig' => '✨ Lebendig', 'winter' => '❄️ Winter & Advent'];

function mu_schema(PDO $pdo): void
{
    static $d = false; if ($d) return; $d = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS mm_music (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, file TEXT NOT NULL, size INTEGER NOT NULL DEFAULT 0,
        dur REAL NOT NULL DEFAULT 0, tags TEXT NOT NULL DEFAULT '', created_at TEXT)");
}
function mu_dir(): string { $d = forma_data_dir() . '/musik'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function mu_get(PDO $pdo, int $id): ?array { $s = $pdo->prepare('SELECT * FROM mm_music WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }
/** „ES_Stay Brave (Instrumental Version) - Bird Of Figment.mp3“ → „Stay Brave – Bird Of Figment“ */
function mu_title(string $fn): string
{
    $t = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $fn);
    $t = preg_replace('/^[0-9a-f]{6,12}-/i', '', $t);
    $t = preg_replace('/^ES_/', '', $t);
    if (!str_contains($t, ' ')) $t = str_replace('_', ' ', $t);
    $t = preg_replace('/\s*\(?Instrumental Version\)?/i', '', $t);
    $t = preg_replace('/\s*\((Instrumental Version|Instrumental)\)/i', '', $t);
    $t = str_replace(' - ', ' – ', $t);
    return trim($t) !== '' ? trim($t) : 'Lied';
}
/** Stichwörter aus dem Titel vorschlagen */
function mu_map(): array
{
    static $map = null; if ($map === null) { $map = json_decode((string)@file_get_contents(__DIR__ . '/musik-ordner.json'), true) ?: []; }
    return $map;
}
function mu_song(string $t): string { return trim(mb_strtolower(explode(' – ', $t)[0])); }
function mu_guess(string $t): array
{
    // Automatisch einsortieren nach Wörtern im Titel (Vivien, 28.10.2026: Ordner sollen sich selbst füllen)
    $song = mu_song($t); $map = mu_map();
    if (isset($map[$song]) && is_array($map[$song])) return array_values(array_filter($map[$song], fn($x) => isset(MU_TAGS[$x])));
    $l = ' ' . $song . ' '; $g = [];
    $re = ['morgen' => 'morning|sunrise|daylight|dawn|morgen|coffee|bright|new day|awake|wake|sunny|hello|brave|hope',
        'abend' => 'night|evening|moon|sleep|dusk|abend|lullaby|stars?|dream|twilight|sunset|goodnight|partir|rest',
        'meditation' => 'breath|meditat|calm|peace|still|pines?|ocean|rain|forest|silence|zen|healing|ambient|drift|float|serene|tranquil',
        'flow' => 'flow|move|motion|yoga|stretch|balance|wave|groove',
        'reel' => 'happy|joy|fun|summer|smile|bloom',
        'lebendig' => 'dance|energy|power|alive|run|party|upbeat|fire|wild'];
    foreach ($re as $k => $r) if (preg_match('/\b(' . $r . ')/u', $l)) $g[] = $k;
    return $g;
}

if ($view === 'musik' || str_starts_with((string)$action, 'mm_mu_')) {
    mu_schema($pdo);
    $muJson = function (array $x): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; };

    if ($action === 'mm_mu_up') {
        $key = preg_replace('/[^a-z0-9]/', '', (string)($_POST['key'] ?? ''));
        $f = $_FILES['b'] ?? null;
        if ($key === '' || !$f || $f['error'] !== UPLOAD_ERR_OK) $muJson(['error' => 'Upload-Stück fehlt (Fehler ' . (int)($f['error'] ?? -1) . ').']);
        $tmp = mu_dir() . '/up-' . $key . '.part';
        if ((int)($_POST['n'] ?? 0) === 0) @unlink($tmp);
        file_put_contents($tmp, file_get_contents($f['tmp_name']), FILE_APPEND);
        if (empty($_POST['last'])) $muJson(['ok' => 1]);
        $name = (string)($_POST['name'] ?? 'Lied.mp3');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp3', 'm4a', 'wav', 'aac', 'ogg', 'mp4'], true)) { @unlink($tmp); $muJson(['error' => 'Bitte eine Musikdatei (MP3, M4A, WAV).']); }
        $file = 'mu-' . bin2hex(random_bytes(6)) . '.' . $ext;
        rename($tmp, mu_dir() . '/' . $file);
        $title = mu_title($name);
        $want = (string)($_POST['tag'] ?? '');
        $tags = isset(MU_TAGS[$want]) ? [$want] : mu_guess($title);
        $pdo->prepare('INSERT INTO mm_music (title, file, size, dur, tags, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$title, $file, filesize(mu_dir() . '/' . $file), max(0, (float)($_POST['dur'] ?? 0)), implode(',', $tags), date('c')]);
        $muJson(['ok' => 1, 'id' => (int)$pdo->lastInsertId(), 'title' => $title]);
    }
    // Lieder-Paket von Claude: der Server holt das Lied selbst bei Epidemic (signierter Link, nur audiocdn.epidemicsound.com)
    if ($action === 'mm_mu_fetch') {
        $url = trim((string)($_POST['url'] ?? '')); $title = trim((string)($_POST['title'] ?? ''));
        if (!preg_match('~^https://audiocdn\.epidemicsound\.com/~', $url) || $title === '') $muJson(['error' => 'Ungültige Zeile.']);
        $ex = $pdo->prepare('SELECT id FROM mm_music WHERE lower(title) = lower(?)'); $ex->execute([$title]);
        if ($ex->fetchColumn()) $muJson(['ok' => 1, 'skip' => 1]);
        @set_time_limit(180);
        $file = 'mu-' . bin2hex(random_bytes(6)) . '.mp3'; $dest = mu_dir() . '/' . $file; $okDl = false;
        if (function_exists('curl_init')) {
            $fh = fopen($dest, 'wb'); $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 150, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_FAILONERROR => true]);
            $okDl = curl_exec($ch) !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400; curl_close($ch); fclose($fh);
        } else {
            $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 150]]));
            $okDl = $data !== false && strlen($data) > 10000 && file_put_contents($dest, $data) !== false;
        }
        if (!$okDl || !is_file($dest) || filesize($dest) < 10000) { @unlink($dest); $muJson(['error' => 'Der Link ist abgelaufen oder nicht erreichbar.']); }
        $tags = array_values(array_filter(explode(',', (string)($_POST['tags'] ?? '')), fn($x) => isset(MU_TAGS[$x])));
        if (!$tags) $tags = mu_guess($title);
        $pdo->prepare('INSERT INTO mm_music (title, file, size, dur, tags, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$title, $file, filesize($dest), max(0, (float)($_POST['dur'] ?? 0)), implode(',', $tags), date('c')]);
        $muJson(['ok' => 1, 'id' => (int)$pdo->lastInsertId()]);
    }
    if ($action === 'mm_mu_tag') {
        $r = mu_get($pdo, (int)($_POST['id'] ?? 0)); $t = (string)($_POST['tag'] ?? '');
        if (!$r || !isset(MU_TAGS[$t])) $muJson(['error' => 'Fehlt.']);
        $tags = array_filter(explode(',', $r['tags']));
        $tags = in_array($t, $tags, true) ? array_values(array_diff($tags, [$t])) : [...$tags, $t];
        $pdo->prepare('UPDATE mm_music SET tags = ? WHERE id = ?')->execute([implode(',', $tags), $r['id']]);
        $muJson(['ok' => 1, 'tags' => $tags]);
    }
    if ($action === 'mm_mu_name') {
        $r = mu_get($pdo, (int)($_POST['id'] ?? 0)); $t = trim((string)($_POST['title'] ?? ''));
        if ($r && $t !== '') $pdo->prepare('UPDATE mm_music SET title = ? WHERE id = ?')->execute([mb_substr($t, 0, 120), $r['id']]);
        $muJson(['ok' => 1]);
    }
    if ($action === 'mm_mu_del') {
        $r = mu_get($pdo, (int)($_POST['id'] ?? 0));
        if ($r) { @unlink(mu_dir() . '/' . basename($r['file'])); $pdo->prepare('DELETE FROM mm_music WHERE id = ?')->execute([$r['id']]); }
        $muJson(['ok' => 1]);
    }
    if ($view === 'musik' && isset($_GET['json'])) {
        $out = [];
        foreach ($pdo->query('SELECT id, title, dur, tags FROM mm_music ORDER BY id DESC')->fetchAll() as $r)
            $out[] = ['id' => (int)$r['id'], 't' => $r['title'], 'dur' => (float)$r['dur'], 'tags' => array_values(array_filter(explode(',', $r['tags'])))];
        $muJson(['list' => $out, 'tags' => MU_TAGS]);
    }
    if ($view === 'musik' && isset($_GET['dl'])) {
        $r = mu_get($pdo, (int)$_GET['dl']);
        $p = $r ? mu_dir() . '/' . basename($r['file']) : '';
        if ($p === '' || !is_file($p)) { http_response_code(404); exit('Nicht gefunden.'); }
        $ext = pathinfo($p, PATHINFO_EXTENSION);
        $types = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg'];
        $size = filesize($p); $from = 0; $to = $size - 1;
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=86400');
        header('Content-Disposition: inline; filename="' . rawurlencode($r['title'] . '.' . $ext) . '"');
        if (preg_match('/bytes=(\d*)-(\d*)/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
            if ($m[1] !== '') $from = (int)$m[1]; if ($m[2] !== '') $to = min($to, (int)$m[2]); if ($m[1] === '' && $m[2] !== '') { $from = $size - (int)$m[2]; $to = $size - 1; }
            http_response_code(206); header("Content-Range: bytes $from-$to/$size");
        }
        header('Content-Length: ' . ($to - $from + 1));
        $fh = fopen($p, 'rb'); fseek($fh, $from); $left = $to - $from + 1;
        while ($left > 0 && !feof($fh)) { $c = fread($fh, min(1 << 20, $left)); echo $c; $left -= strlen($c); flush(); }
        exit;
    }

    if ($view === 'musik') {
        // Noch nicht einsortierte Lieder: hat Claude sie inzwischen in musik-ordner.json eingetragen? Dann jetzt einsortieren.
        $map = mu_map(); $upd = $pdo->prepare('UPDATE mm_music SET tags = ? WHERE id = ?');
        foreach ($pdo->query("SELECT id, title FROM mm_music WHERE tags = ''")->fetchAll() as $r) {
            $m = $map[mu_song($r['title'])] ?? null;
            if (is_array($m) && ($m = array_values(array_filter($m, fn($x) => isset(MU_TAGS[$x]))))) $upd->execute([implode(',', $m), $r['id']]);
        }
        $list = $pdo->query('SELECT * FROM mm_music ORDER BY title COLLATE NOCASE')->fetchAll();
        $self = strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?');
        render_page('Musik', function () use ($list, $self) {
            $h = 'forma_h'; $fmt = fn($s) => $s > 0 ? floor($s / 60) . ':' . str_pad((string)floor(fmod($s, 60)), 2, '0', STR_PAD_LEFT) : '';
            $folders = ['' => ['📥', 'Noch nicht einsortiert', 'Tippe bei jedem Lied an, in welchen Ordner es gehört.']] + [
                'morgen' => ['☀️', 'Morgen', 'Warm, hell, zuversichtlich: Morgen-Flows, Willkommen, Storys am Vormittag'],
                'abend' => ['🌙', 'Abend', 'Ruhig und weich: Abendflows, Ausklang, Schlafenszeit'],
                'meditation' => ['🕯️', 'Meditation & Atem', 'Fast still, Klangflächen, Klavier: Meditationen, Breathwork'],
                'flow' => ['🧘', 'Flow & Bewegung', 'Gleichmäßiger Fluss für Bewegungsvideos und Flows'],
                'reel' => ['📱', 'Reel & Story', 'Kurz, freundlich, gute Laune für Instagram'],
                'lebendig' => ['✨', 'Lebendig', 'Mehr Schwung: Kraft, Cardio, Tanz'],
                'winter' => ['❄️', 'Winter & Advent', 'Kerzenlicht, Schnee, Weihnachtszeit: für November und Dezember']];
            $in = []; foreach ($list as $r) { $tg = array_values(array_filter(explode(',', $r['tags']))); if (!$tg) $in[''][] = $r; foreach ($tg as $t) if (isset($folders[$t])) $in[$t][] = $r; }
            $row = function (array $r) use ($h, $fmt) { $tags = array_filter(explode(',', $r['tags']));
                $o = '<div class="song" data-id="' . (int)$r['id'] . '"><button type="button" class="pl" data-play="' . (int)$r['id'] . '" aria-label="Anhören">▶</button>'
                    . '<div class="tt"><b contenteditable="true" spellcheck="false" title="Zum Umbenennen antippen">' . $h($r['title']) . '</b><small>' . $h($fmt((float)$r['dur'])) . '</small>'
                    . '<div class="tg"><span>Ordner:</span>';
                foreach (MU_TAGS as $k => $n) $o .= '<button type="button" data-tag="' . $k . '" aria-pressed="' . (in_array($k, $tags, true) ? 'true' : 'false') . '">' . $h($n) . '</button>';
                return $o . '</div></div><button type="button" class="del" aria-label="Lied löschen" title="Lied löschen">✕</button></div>'; }; ?>
<style>
main{max-width:1240px}
.mh p{margin:0 0 18px;color:#6B5658;font-size:19px;line-height:1.5;max-width:70ch}
.drop{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:26px 18px;border:2px dashed #C9B0B8;border-radius:28px;background:linear-gradient(135deg,#FBF8F4,#F4E9E3);cursor:pointer;transition:background .2s,border-color .2s;margin-bottom:10px}
.drop.on,.fold.on>summary{border-color:#7D5E68!important;background:#EADFDE!important}
.drop b{font:500 28px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.drop span{font-size:17px;color:#6B5658}
.drop i{font-style:normal;font-size:38px}
.into{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:0 0 16px;font-size:16px;color:#6B5658}
.into button{font:inherit;font-size:15px;padding:7px 13px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:40px}
.into button[aria-pressed=true]{background:#4A3A41;border-color:#4A3A41;color:#fff}
.prog{height:10px;border-radius:9px;background:#EADFDE;overflow:hidden;margin:0 0 14px}.prog i{display:block;height:100%;width:0;background:#7D5E68;transition:width .3s}
.prog[hidden]{display:none}
.msg{font-size:18px;color:#6B5658;margin:0 0 14px}.msg.ok{color:#3F6B3E}.msg:empty{display:none}
.folds{display:flex;flex-direction:column;gap:10px}
/* Ordner als kleine Pastell-Boxen zum Aufklappen */
.fold{border-radius:22px;background:#F6EEEC;border:1.5px solid transparent}
.fold.c1{background:#F4E9E1}.fold.c2{background:#EFE6EB}.fold.c3{background:#F1ECE4}.fold.inbox{background:#FBF5F0;border:1.5px dashed #C9B0B8}
.fold[open]{background:#FBF8F4;border-color:#E6DCD2;box-shadow:0 12px 26px -22px rgba(74,58,65,.6)}
.fold>summary{list-style:none;display:flex;align-items:center;gap:14px;padding:12px 14px;cursor:pointer;border-radius:20px;border:1.5px solid transparent}
.fold>summary::-webkit-details-marker{display:none}
.fic{flex:0 0 auto;width:54px;height:54px;border-radius:18px;background:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;box-shadow:0 4px 10px -8px rgba(74,58,65,.6)}
.fdt{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
.fdt b{font:500 24px/1.15 "Cormorant Garamond",Georgia,serif;color:#4A3A41}.fdt small{font-size:15px;color:#7D7076;line-height:1.35}
.fcnt{flex:0 0 auto;font-size:15px;color:#6B5658;background:rgba(255,255,255,.75);border-radius:999px;padding:4px 12px}
.chev{flex:0 0 auto;width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.75);position:relative}
.chev::after{content:"";position:absolute;left:10px;top:9px;width:8px;height:8px;border-right:2px solid #7D5E68;border-bottom:2px solid #7D5E68;transform:rotate(45deg);transition:transform .2s}
.fold[open] .chev::after{transform:translateY(4px) rotate(-135deg)}
.fbody{padding:0 12px 12px;display:flex;flex-direction:column;gap:8px}
.fempty{font-size:16px;color:#7D7076;padding:4px 6px 8px}
.song{display:flex;align-items:flex-start;gap:12px;background:#fff;border:1.5px solid #E6DCD2;border-radius:18px;padding:10px 12px}
.song .pl{flex:0 0 auto;width:44px;height:44px;border-radius:50%;border:0;background:#7D5E68;color:#fff;font-size:16px;cursor:pointer}
.song .pl.on{background:#4A3A41}
.song .tt{flex:1;min-width:0}.song .tt b{display:block;font-weight:500;font-size:18px;color:#2E2529;line-height:1.3;cursor:text}.song .tt small{color:#7D7076;font-size:14px}
.tg{display:flex;flex-wrap:wrap;gap:5px;align-items:center;margin-top:8px}.tg span{font-size:13px;color:#7D7076;margin-right:2px}
.tg button{font:inherit;font-size:13px;padding:4px 10px;border-radius:999px;border:1.5px solid #E6DCD2;background:#FBF8F4;color:#6B5658;cursor:pointer;min-height:32px}
.tg button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.del{flex:0 0 auto;width:34px;height:34px;border-radius:50%;border:0;background:none;color:#B3A3A8;font-size:16px;cursor:pointer}
.del:hover{background:#F3D9D5;color:#A0413B}
.tip{font-size:16px;color:#7D7076;line-height:1.5}
.pack{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:22px;padding:4px 16px;margin:0 0 12px}
.pack>summary{cursor:pointer;list-style:none;padding:12px 0;font:500 22px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41}.pack>summary::-webkit-details-marker{display:none}
.pack p{margin:0 0 10px;font-size:16px;color:#6B5658;line-height:1.5}
.pack textarea{width:100%;font:inherit;font-size:14px;border:1.5px solid #DDD2C6;border-radius:14px;padding:10px;background:#fff;box-sizing:border-box}
.pack button{font:inherit;font-size:17px;min-height:48px;padding:10px 22px;border-radius:999px;border:0;background:#7D5E68;color:#fff;cursor:pointer;margin:10px 0 14px}
.ask{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;background:#F4E9E3;border-radius:16px;padding:10px 12px;font-size:16px;color:#4A3A41}
.ask button{font:inherit;font-size:16px;min-height:44px;padding:8px 16px;border-radius:999px;border:0;background:#7D5E68;color:#fff;cursor:pointer}
@media(max-width:640px){.fold>summary{gap:10px;padding:10px}.fic{width:46px;height:46px;font-size:22px}.fdt b{font-size:21px}.fdt small{display:none}}
</style>
<?php mm_subnav('musik'); ?>
<div class="mh"><h1>🎵 Deine Musik</h1>
  <p>Dein Musik-Ordner, z. B. für die Lieder von Epidemic Sound. Neue Lieder sortieren sich nach dem Titel selbst ein, oder du ziehst sie direkt auf den passenden Ordner. Im Studio legt das Bearbeiten-Fenster dann von selbst ein Lied aus dem passenden Ordner unter dein Video.</p></div>
<label class="drop" id="drop"><i>🎵</i><b>Lieder hierher ziehen oder antippen</b><span>MP3, M4A oder WAV · gern mehrere auf einmal</span><input type="file" id="muF" accept="audio/*,.mp3,.m4a,.wav,.aac,.ogg" multiple hidden></label>
<details class="pack" id="pack"><summary>📦 Lieder-Paket von Claude einfügen</summary>
  <p>Füge hier die Liste ein, die dir Claude im Chat gibt. Dein Server holt die Lieder dann selbst bei Epidemic Sound und sortiert sie gleich in die Ordner, du musst nichts herunterladen. Die Links gelten nur kurz, also am besten gleich einfügen.</p>
  <textarea id="packT" rows="5" placeholder="Titel – Künstler | Ordner | Dauer | Link"></textarea>
  <button type="button" id="packGo">Lieder holen</button>
</details>
<div class="into" id="into"><span>Neue Lieder kommen in:</span><button type="button" data-into="" aria-pressed="true">✨ automatisch</button>
  <?php foreach (MU_TAGS as $k => $n): ?><button type="button" data-into="<?= $k ?>" aria-pressed="false"><?= $h($n) ?></button><?php endforeach; ?></div>
<div class="prog" id="prog" hidden><i></i></div>
<p class="msg" id="msg"></p>
<div class="folds">
<?php $c = 0; foreach ($folders as $k => [$ic, $nm, $ds]): $l = $in[$k] ?? []; if ($k === '' && !$l) continue; ?>
  <details class="fold <?= $k === '' ? 'inbox' : 'c' . ($c++ % 4) ?>" data-fold="<?= $h($k) ?>"<?= $k === '' ? ' open' : '' ?>>
    <summary><span class="fic"><?= $ic ?></span><span class="fdt"><b><?= $h($nm) ?></b><small><?= $h($ds) ?></small></span><span class="fcnt"><?= count($l) ?> <?= count($l) === 1 ? 'Lied' : 'Lieder' ?></span><span class="chev" aria-hidden="true"></span></summary>
    <div class="fbody"><?php if ($k === '' && $l): ?><div class="ask"><span>Schick mir die Liste, dann sortiere ich die Lieder für dich ein.</span><button type="button" id="copyU" data-list="<?= $h(implode("\n", array_map(fn($r) => '• ' . $r['title'], $l))) ?>">📋 Liste für Claude kopieren</button></div><?php endif; ?><?= $l ? implode('', array_map($row, $l)) : '<p class="fempty">Noch leer. Zieh Lieder direkt auf diesen Ordner oder wähle oben „Neue Lieder kommen in: ' . $h($nm) . '“.</p>' ?></div>
  </details>
<?php endforeach; ?>
</div>
<p class="tip" style="margin-top:18px">Ein Lied kann in mehreren Ordnern sein. Die Lieder liegen nur auf deinem Server und sind nur hier in der Verwaltung abrufbar, nie öffentlich.</p>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>,$=id=>document.getElementById(id);let into='';
  const post=(a,d,file)=>{const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);if(file)f.append('b',file,'teil');return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())};
  const dur=f=>new Promise(ok=>{const a=new Audio();a.preload='metadata';a.onloadedmetadata=()=>{ok(isFinite(a.duration)?a.duration:0);URL.revokeObjectURL(a.src)};a.onerror=()=>ok(0);a.src=URL.createObjectURL(f);setTimeout(()=>ok(0),4000)});
  async function upload(files,tag){files=[...files].filter(f=>/audio|\.(mp3|m4a|wav|aac|ogg)$/i.test(f.type+' '+f.name));if(!files.length)return;
    const bar=$('prog'),fill=bar.querySelector('i'),CH=1536*1024;let done=0,ok=0,total=files.reduce((s,f)=>s+f.size,0);bar.hidden=false;
    for(const f of files){$('msg').className='msg';$('msg').textContent='Lädt hoch: '+f.name+' …';const d=await dur(f),key=Math.random().toString(36).slice(2)+Date.now().toString(36),n=Math.max(1,Math.ceil(f.size/CH));
      try{for(let i=0;i<n;i++){const extra={key:key,n:i,name:f.name,dur:d,tag:tag||''};if(i===n-1)extra.last=1;let j=null;
          for(let t=0;t<3&&!j;t++){try{j=await post('mm_mu_up',extra,f.slice(i*CH,(i+1)*CH))}catch(e){await new Promise(r=>setTimeout(r,2000))}}
          if(!j||j.error)throw new Error(j?j.error:'Netz');done+=Math.min(CH,f.size-i*CH);fill.style.width=Math.round(done/total*100)+'%'}ok++}
      catch(e){$('msg').textContent='„'+f.name+'“ hat nicht geklappt'+(e.message!=='Netz'?' ('+e.message+')':'')+'.';await new Promise(r=>setTimeout(r,1500))}}
    bar.hidden=true;if(!ok){$('msg').className='msg';$('msg').textContent='Das Hochladen hat nicht geklappt. Bitte nochmal versuchen.';return}
    $('msg').className='msg ok';$('msg').textContent='✓ '+ok+(ok===1?' Lied':' Lieder')+' gespeichert und einsortiert.';setTimeout(()=>location.reload(),900);}
  document.querySelectorAll('[data-into]').forEach(b=>b.onclick=()=>{into=b.dataset.into;document.querySelectorAll('[data-into]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)))});
  const drop=$('drop');$('muF').onchange=e=>upload(e.target.files,into);
  const dz=(el,tag)=>{['dragenter','dragover'].forEach(t=>el.addEventListener(t,e=>{e.preventDefault();e.stopPropagation();el.classList.add('on')}));
    ['dragleave','drop'].forEach(t=>el.addEventListener(t,e=>{e.preventDefault();e.stopPropagation();el.classList.remove('on')}));
    el.addEventListener('drop',e=>upload(e.dataTransfer.files,tag()))};
  dz(drop,()=>into);
  document.querySelectorAll('.fold[data-fold]').forEach(f=>{if(f.dataset.fold)dz(f,()=>f.dataset.fold)});
  $('packGo').onclick=async()=>{const L=$('packT').value.split(/\n/).map(x=>x.trim()).filter(x=>x&&x.includes('https://')).map(x=>{const p=x.split('|').map(y=>y.trim());return {title:p[0].replace(/^[•\-\d.\s]+/,''),tags:p[1]||'',dur:p[2]||0,url:p.slice(3).join('|')||p[p.length-1]}});
    if(!L.length){$('msg').className='msg';$('msg').textContent='In der Liste ist kein Lied. Bitte die ganze Liste von Claude einfügen.';return}
    const bar=$('prog'),fill=bar.querySelector('i');bar.hidden=false;$('packGo').disabled=true;let ok=0,skip=0,bad=[];
    for(let i=0;i<L.length;i++){$('msg').className='msg';$('msg').textContent='Hole Lied '+(i+1)+' von '+L.length+': '+L[i].title+' …';
      try{const j=await post('mm_mu_fetch',L[i]);if(j.skip)skip++;else if(j.ok)ok++;else bad.push(L[i].title)}catch(e){bad.push(L[i].title)}fill.style.width=Math.round((i+1)/L.length*100)+'%'}
    bar.hidden=true;$('packGo').disabled=false;
    $('msg').className=bad.length||!ok?'msg':'msg ok';$('msg').textContent=(ok?'✓ '+ok+(ok===1?' Lied':' Lieder')+' geholt und einsortiert':'Keine neuen Lieder geholt')+(skip?', '+(skip===1?'1 war':skip+' waren')+' schon da':'')+'.'+(bad.length?' Nicht geklappt: '+bad.join(', ')+'. Sag Claude „neue Links“, dann bekommst du frische.':'');
    if(!bad.length)setTimeout(()=>location.reload(),1200)};
  const cu=$('copyU');if(cu)cu.onclick=async()=>{const t='Bitte sortier diese Lieder in meine Musik-Ordner:\n'+cu.dataset.list;
    try{await navigator.clipboard.writeText(t);cu.textContent='✓ Kopiert – jetzt bei Claude einfügen'}catch(e){prompt('Kopieren und bei Claude einfügen:',t)}};
  const au=new Audio();let playing=0;
  document.querySelectorAll('[data-play]').forEach(b=>b.onclick=()=>{const id=+b.dataset.play;document.querySelectorAll('[data-play]').forEach(x=>{x.textContent='▶';x.classList.remove('on')});
    if(playing===id){au.pause();playing=0;return}au.src='?view=musik&dl='+id;au.play().catch(()=>{});playing=id;document.querySelectorAll('[data-play="'+id+'"]').forEach(x=>{x.textContent='❚❚';x.classList.add('on')})});
  au.onended=()=>{playing=0;document.querySelectorAll('[data-play]').forEach(x=>{x.textContent='▶';x.classList.remove('on')})};
  document.querySelectorAll('.song').forEach(s=>{const id=s.dataset.id;
    s.querySelectorAll('[data-tag]').forEach(b=>b.onclick=()=>post('mm_mu_tag',{id:id,tag:b.dataset.tag}).then(j=>{if(j.tags){document.querySelectorAll('.song[data-id="'+id+'"] [data-tag]').forEach(x=>x.setAttribute('aria-pressed',String(j.tags.includes(x.dataset.tag))));
      $('msg').className='msg ok';$('msg').textContent='✓ Gespeichert. Die Ordner ordnen sich beim nächsten Öffnen neu.'}}));
    const t=s.querySelector('.tt b');t.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();t.blur()}});t.addEventListener('blur',()=>post('mm_mu_name',{id:id,title:t.textContent.trim()}));
    s.querySelector('.del').onclick=()=>{if(confirm('„'+t.textContent.trim()+'“ ganz löschen?'))post('mm_mu_del',{id:id}).then(()=>document.querySelectorAll('.song[data-id="'+id+'"]').forEach(x=>x.remove()))};});
})();
</script>
<?php
        });
        exit;
    }
}

<?php
// Leise Hintergrundmusik auf moveciamovement.com (Vivien, 28.10.2026). Spielt nur Lieder aus dem Ordner
// „🌐 Website“ (Verwaltung → Movecia → Musik); solange der leer ist, ruhige Lieder aus Meditation & Atem / Abend.
// GET → Liste (JSON), GET ?s=ID → Audio mit Range. Andere Lieder aus dem Musik-Ordner sind hier nie abrufbar.
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('X-Robots-Tag: noindex');
$pdo = forma_db();
$pdo->exec("CREATE TABLE IF NOT EXISTS mm_music (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, file TEXT NOT NULL, size INTEGER NOT NULL DEFAULT 0,
    dur REAL NOT NULL DEFAULT 0, tags TEXT NOT NULL DEFAULT '', created_at TEXT)");
$all = $pdo->query('SELECT id, title, file, tags FROM mm_music ORDER BY id')->fetchAll();
$pick = fn(array $want) => array_values(array_filter($all, fn($r) => array_intersect($want, explode(',', (string)$r['tags']))));
$list = $pick(['web']) ?: $pick(['meditation', 'abend']);
$list = array_slice($list, 0, 30);

if (isset($_GET['s'])) {
    $r = null; foreach ($list as $x) if ((int)$x['id'] === (int)$_GET['s']) $r = $x;
    $p = $r ? forma_data_dir() . '/musik/' . basename((string)$r['file']) : '';
    if ($p === '' || !is_file($p)) { http_response_code(404); exit; }
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    $types = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg'];
    $size = filesize($p); $from = 0; $to = $size - 1;
    header('Content-Type: ' . ($types[$ext] ?? 'audio/mpeg'));
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=604800');
    header('Content-Disposition: inline');
    if (preg_match('/bytes=(\d*)-(\d*)/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
        if ($m[1] !== '') $from = (int)$m[1];
        if ($m[2] !== '') $to = min($to, (int)$m[2]);
        if ($m[1] === '' && $m[2] !== '') { $from = max(0, $size - (int)$m[2]); $to = $size - 1; }
        if ($from > $to) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
        http_response_code(206); header("Content-Range: bytes $from-$to/$size");
    }
    header('Content-Length: ' . ($to - $from + 1));
    $fh = fopen($p, 'rb'); fseek($fh, $from); $left = $to - $from + 1;
    while ($left > 0 && !feof($fh)) { $c = fread($fh, min(1 << 20, $left)); echo $c; $left -= strlen($c); flush(); }
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
// Titel ohne Künstler-Kürzel aus dem Dateinamen, nur „Lied – Künstlerin“
echo json_encode(['list' => array_map(fn($r) => ['id' => (int)$r['id'], 't' => (string)$r['title']], $list)], JSON_UNESCAPED_UNICODE);

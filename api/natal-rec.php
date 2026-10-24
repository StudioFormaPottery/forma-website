<?php
// Aufnahmen der Prä-/Postnatal-Einheiten (Vivien, 24.10.2026): nur Viviens Kamera und Mikro.
// Hochladen in Stücken (Videoraum der Gastgeberin oder Verwaltung, beide mit dem Gastgeberinnen-Schlüssel des Termins),
// Ansehen nur für die eigene Kundin (Mitgliederbereich, nach den ersten 14 Tagen) oder für Vivien (signierter Link).
declare(strict_types=1);
require __DIR__ . '/natal-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$pdo = forma_db();
natal_schema($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    $room = natal_room_resolve($pdo, (string)($_GET['r'] ?? ''), (string)($_GET['k'] ?? ''));
    if (!$room || !$room['host'] || $room['kind'] !== 's') { http_response_code(403); exit('{"ok":false}'); }
    $sid = (int)$room['id'];
    $act = (string)($_GET['a'] ?? '');
    if ($act === 'start') {
        $mime = preg_match('~^video/(webm|mp4|quicktime)~', (string)($_GET['mime'] ?? ''), $m) ? 'video/' . $m[1] : 'video/webm';
        $file = 's' . $sid . '-' . bin2hex(random_bytes(10)) . ($mime === 'video/mp4' ? '.mp4' : ($mime === 'video/quicktime' ? '.mov' : '.webm'));
        touch(natal_rec_dir() . '/' . $file);
        $pdo->prepare("INSERT INTO mm_natal_recs (session_id, order_id, file, mime, status, created_at) VALUES (?,?,?,?, 'upload', ?)")->execute([$sid, (int)$room['row']['order_id'], $file, $mime, date('c')]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]); exit;
    }
    $q = $pdo->prepare("SELECT * FROM mm_natal_recs WHERE id = ? AND session_id = ? AND status = 'upload'");
    $q->execute([(int)($_GET['id'] ?? 0), $sid]);
    $r = $q->fetch();
    if (!$r) { http_response_code(404); exit('{"ok":false}'); }
    $path = natal_rec_dir() . '/' . basename((string)$r['file']);
    if ($act === 'chunk') {
        $off = (int)($_GET['off'] ?? -1);
        clearstatcache(true, $path);
        $size = (int)@filesize($path);
        if ($off !== $size) { echo json_encode(['ok' => false, 'size' => $size]); exit; }
        $in = fopen('php://input', 'rb'); $out = fopen($path, 'ab');
        $n = stream_copy_to_stream($in, $out, 64 * 1024 * 1024);
        fclose($in); fclose($out);
        if ($size + $n > 4 * 1024 * 1024 * 1024) { natal_rec_delete($pdo, $r); http_response_code(413); exit('{"ok":false}'); }
        echo json_encode(['ok' => true, 'size' => $size + $n]); exit;
    }
    if ($act === 'finish') {
        clearstatcache(true, $path);
        $bytes = (int)@filesize($path);
        if ($bytes < 1000) { natal_rec_delete($pdo, $r); echo '{"ok":false}'; exit; }
        // nur die neueste Aufnahme pro Termin behalten
        $old = $pdo->prepare("SELECT * FROM mm_natal_recs WHERE session_id = ? AND status = 'fertig'");
        $old->execute([$sid]);
        foreach ($old->fetchAll() as $x) natal_rec_delete($pdo, $x);
        $pdo->prepare("UPDATE mm_natal_recs SET status = 'fertig', bytes = ?, seconds = ? WHERE id = ?")->execute([$bytes, max(0, (int)($_GET['sec'] ?? 0)), $r['id']]);
        echo json_encode(['ok' => true, 'bytes' => $bytes]); exit;
    }
    if ($act === 'cancel') { natal_rec_delete($pdo, $r); echo '{"ok":true}'; exit; }
    http_response_code(400); exit('{"ok":false}');
}

// Ansehen (mit Range, damit man im Video springen kann)
$q = $pdo->prepare("SELECT * FROM mm_natal_recs WHERE id = ? AND status = 'fertig'");
$q->execute([(int)($_GET['id'] ?? 0)]);
$r = $q->fetch();
$ok = false;
if ($r) {
    if (isset($_GET['k']) && hash_equals(natal_rec_admin_key((int)$r['id']), (string)$_GET['k'])) $ok = true;
    else {
        $me = natal_member_current($pdo);
        $o = natal_order($pdo, (int)$r['order_id']);
        $ok = $me && $o && strtolower((string)$o['email']) === strtolower((string)$me['email']) && !$o['purged_at'] && natal_member_full($o);
    }
}
$path = $r ? natal_rec_dir() . '/' . basename((string)$r['file']) : '';
if (!$ok || !is_file($path)) { http_response_code(404); exit('Nicht verfügbar.'); }
$size = filesize($path);
$start = 0; $end = $size - 1;
header('Content-Type: ' . $r['mime']);
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="Movecia-Aufnahme.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
if (preg_match('/bytes=(\d*)-(\d*)/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
    if ($m[1] !== '') { $start = (int)$m[1]; if ($m[2] !== '') $end = min($end, (int)$m[2]); }
    elseif ($m[2] !== '') $start = max(0, $size - (int)$m[2]);
    if ($start > $end) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));
$f = fopen($path, 'rb');
fseek($f, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($f)) { $chunk = fread($f, min(1024 * 256, $left)); echo $chunk; $left -= strlen($chunk); flush(); }
fclose($f);

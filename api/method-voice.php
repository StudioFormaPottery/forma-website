<?php
// Voice-over je Übung im Flow (Vivien, 26.10.2026). Liefert die Aufnahme mit Range aus.
// Der Link ist signiert (mv_voice_url), die Dateien liegen nur im Datenordner (../forma-data/voice/).
declare(strict_types=1);
require __DIR__ . '/method-lib.php';
$lid = (int)($_GET['l'] ?? 0);
$i = (int)($_GET['i'] ?? -1);
$g = ($_GET['g'] ?? '') === 'en' ? 'en' : 'de';
if ($lid < 1 || $i < 0 || !hash_equals(mv_voice_sig($lid, $i, $g), (string)($_GET['k'] ?? ''))) { http_response_code(403); exit; }
$f = mv_voice_path($lid, $i, $g);
if (!$f || !is_file($f)) { http_response_code(404); exit; }
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$type = ['webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'mp4' => 'audio/mp4', 'm4a' => 'audio/mp4', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'aac' => 'audio/aac'][$ext] ?? 'application/octet-stream';
$size = filesize($f);
header('Content-Type: ' . $type);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=86400');
$start = 0; $end = $size - 1;
if (preg_match('/bytes=(\d*)-(\d*)/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
    if ($m[1] === '' && $m[2] !== '') { $start = max(0, $size - (int)$m[2]); }
    else { $start = (int)$m[1]; if ($m[2] !== '') $end = min($end, (int)$m[2]); }
    if ($start > $end) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));
$fh = fopen($f, 'rb');
fseek($fh, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fh)) { $buf = fread($fh, min(65536, $left)); echo $buf; $left -= strlen($buf); flush(); }
fclose($fh);

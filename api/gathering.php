<?php
// Wild Hearts Gathering: nächster Termin (GET), Anmeldung (POST), „bin im Raum“ (POST ping).
declare(strict_types=1);
require __DIR__ . '/movecia-lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$pdo = forma_db();
mm_schema($pdo);
$out = function (array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };

try { mm_send_reminders($pdo); } catch (Throwable $e) { error_log('MM reminders: ' . $e->getMessage()); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $g = mm_next_gathering($pdo);
    $out(['gathering' => $g ? ['title' => $g['title'], 'when' => mm_when($g), 'starts_at' => date('c', strtotime($g['starts_at'])), 'minutes' => (int)$g['minutes'], 'description' => $g['description']] : null]);
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;
$act = (string)($in['action'] ?? 'signup');

if ($act === 'ping') {
    $tok = (string)($in['k'] ?? '');
    if ($tok === '') $out(['ok' => false], 400);
    $pdo->prepare("UPDATE mm_signups SET last_seen = ?, joined_at = COALESCE(joined_at, ?) WHERE token = ? AND status = 'angemeldet'")->execute([date('c'), date('c'), $tok]);
    $out(['ok' => true]);
}

// Anmeldung
$name = trim((string)($in['name'] ?? ''));
$email = strtolower(trim((string)($in['email'] ?? '')));
if (!empty($in['website'])) $out(['ok' => true]); // Honeypot
if ((int)($in['elapsed'] ?? 0) < 3000) $out(['ok' => false, 'error' => 'One moment please, then try again.'], 400);
if (mb_strlen($name) < 2) $out(['ok' => false, 'error' => 'Please add your first name.'], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $out(['ok' => false, 'error' => 'Please check your email address.'], 400);
if (empty($in['consent'])) $out(['ok' => false, 'error' => 'Please tick the box so I am allowed to email you about the Gathering.'], 400);
$g = mm_next_gathering($pdo);
if (!$g) $out(['ok' => false, 'error' => 'There is no Gathering planned right now. Join the community channel and you will hear about the next one first.'], 400);

$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
$c = $pdo->prepare('SELECT COUNT(*) FROM mm_signups WHERE ip_hash = ? AND created_at >= ?');
$c->execute([$ip, date('c', time() - 3600)]);
if ((int)$c->fetchColumn() >= 6) $out(['ok' => false, 'error' => 'Too many sign-ups from here. Please try again later.'], 429);

$s = $pdo->prepare('SELECT * FROM mm_signups WHERE gathering_id = ? AND email = ?');
$s->execute([$g['id'], $email]);
$row = $s->fetch();
if ($row) {
    // schon angemeldet: Link einfach noch einmal schicken
    $pdo->prepare("UPDATE mm_signups SET status = 'angemeldet' WHERE id = ?")->execute([$row['id']]);
} else {
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO mm_signups (gathering_id, name, email, token, ip_hash, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$g['id'], mb_substr($name, 0, 80), mb_substr($email, 0, 160), $tok, $ip, date('c')]);
    $row = ['name' => mb_substr($name, 0, 80), 'email' => $email, 'token' => $tok];
}
mm_mail_signup($g, $row);
$out(['ok' => true, 'when' => mm_when($g)]);

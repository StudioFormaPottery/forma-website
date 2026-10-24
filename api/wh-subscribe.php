<?php
// Wild Hearts: Anmeldung zur E-Mail-Liste (Reset Routine, Inner-Circle-Warteliste). Double-Opt-in, Mails von hello@moveciamovement.com.
declare(strict_types=1);
require __DIR__ . '/movecia-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$out = function (array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $out(['ok' => false], 405);

$pdo = forma_db();
mm_schema($pdo);
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;
$name = trim((string)($in['name'] ?? ''));
$email = strtolower(trim((string)($in['email'] ?? '')));
$source = in_array($in['source'] ?? '', ['reset', 'circle', 'mentoring', 'year', 'retreat', 'unfold'], true) ? $in['source'] : 'link';
$lang = ($in['lang'] ?? '') === 'de' ? 'de' : 'en';
$de = $lang === 'de';
if (!empty($in['website'])) $out(['ok' => true]); // Honeypot
if ((int)($in['elapsed'] ?? 0) < 2000) $out(['ok' => false, 'error' => ($de ? 'Einen Moment bitte, dann versuch es noch einmal.' : 'One moment please, then try again.')], 400);
if (mb_strlen($name) < 2) $out(['ok' => false, 'error' => ($de ? 'Bitte gib deinen Vornamen ein.' : 'Please add your first name.')], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $out(['ok' => false, 'error' => ($de ? 'Bitte prüf deine E-Mail-Adresse.' : 'Please check your email address.')], 400);
if (empty($in['consent'])) $out(['ok' => false, 'error' => ($de ? 'Bitte setz das Häkchen, damit ich dir schreiben darf.' : 'Please tick the box so I am allowed to email you.')], 400);

$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
$c = $pdo->prepare('SELECT COUNT(*) FROM mm_subscribers WHERE ip_hash = ? AND created_at >= ?');
$c->execute([$ip, date('c', time() - 3600)]);
if ((int)$c->fetchColumn() >= 6) $out(['ok' => false, 'error' => ($de ? 'Zu viele Anmeldungen von hier. Bitte versuch es später noch einmal.' : 'Too many sign-ups from here. Please try again later.')], 429);

$s = $pdo->prepare('SELECT * FROM mm_subscribers WHERE email = ?');
$s->execute([$email]);
$sub = $s->fetch();
if ($sub && $sub['status'] === 'bestaetigt') {
    if ($sub['source'] !== $source && $source !== 'link') $pdo->prepare('UPDATE mm_subscribers SET source = ? WHERE id = ?')->execute([$source, $sub['id']]);
    // schon dabei: Willkommensmail (bzw. Unfold-Infos) einfach noch einmal schicken
    if ($source === 'unfold') { $sub['lang'] = $lang; mm_mail_unfold_info($sub); mm_notify_interest($sub, 'unfold'); } else mm_mail_welcome($sub);
    $out(['ok' => true, 'already' => true]);
}
if ($sub) {
    $pdo->prepare("UPDATE mm_subscribers SET name = ?, source = ?, lang = ?, status = 'offen', ip_hash = ? WHERE id = ?")->execute([mb_substr($name, 0, 80), $source, $lang, $ip, $sub['id']]);
    $sub['name'] = mb_substr($name, 0, 80);
    $sub['lang'] = $lang;
} else {
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO mm_subscribers (name, email, source, lang, token, ip_hash, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([mb_substr($name, 0, 80), mb_substr($email, 0, 160), $source, $lang, $tok, $ip, date('c')]);
    $sub = ['id' => (int)$pdo->lastInsertId(), 'name' => mb_substr($name, 0, 80), 'email' => $email, 'token' => $tok, 'lang' => $lang];
}
$sub['source'] = $source;
$out(['ok' => mm_mail_confirm($sub)]);

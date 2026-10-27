<?php
// Movecia · 1:1 Personal Training anfragen (Vivien, 26.10.2026)
declare(strict_types=1);
require __DIR__ . '/natal-lib.php';
require_once __DIR__ . '/mm-pt.inc.php';
header('X-Robots-Tag: noindex');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
$in = $_POST;
if (!empty($in['website'])) forma_json(['ok' => true]);
$en = ($in['lang'] ?? '') === 'en';
$E = fn(string $de, string $enT) => $en ? $enT : $de;
$loaded = (int)($in['t'] ?? 0);
if ($loaded > 0 && ((int)(microtime(true) * 1000) - $loaded) < 3000) forma_json(['ok' => false, 'error' => $E('Einen Moment bitte, dann versuch es noch einmal.', 'One moment, please try again in a few seconds.')], 429);

$pdo = forma_db();
mm_schema($pdo);
mm_pt_schema($pdo);
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'pt');
$q = $pdo->prepare('SELECT COUNT(*) FROM mm_pt_requests WHERE ip_hash = ? AND created_at >= ?');
$q->execute([$ip, date('c', time() - 3600)]);
if ((int)$q->fetchColumn() >= 5) forma_json(['ok' => false, 'error' => $E('Du hast schon mehrere Anfragen geschickt. Ich melde mich bald bei dir.', 'You’ve already sent a few requests. I’ll be in touch soon.')], 429);

$name = forma_clean((string)($in['name'] ?? ''), 80);
$email = strtolower(forma_clean((string)($in['email'] ?? ''), 160));
$phone = forma_clean((string)($in['phone'] ?? ''), 40);
$msg = trim(mb_substr(str_replace("\r", '', (string)($in['message'] ?? '')), 0, 1500));
$pick = fn(string $f, array $map) => implode(',', array_values(array_intersect(array_keys($map), (array)($in[$f] ?? []))));
$goals = $pick('goals', MM_PT_GOALS);
$places = $pick('places', MM_PT_PLACES);
$times = $pick('times', MM_PT_TIMES);

if (mb_strlen($name) < 2) forma_json(['ok' => false, 'error' => $E('Bitte gib deinen Namen ein.', 'Please enter your name.')], 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) forma_json(['ok' => false, 'error' => $E('Bitte prüf deine E-Mail-Adresse.', 'Please check your email address.')], 422);
if ($goals === '' && $msg === '') forma_json(['ok' => false, 'error' => $E('Kreuz bitte an, was du dir wünschst, oder schreib mir ein paar Worte.', 'Please choose what you’re looking for or write me a few words.')], 422);

$pdo->prepare('INSERT INTO mm_pt_requests (name, email, phone, goals, places, times, message, lang, ip_hash, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
    ->execute([$name, $email, $phone, $goals, $places, $times, $msg, $en ? 'en' : 'de', $ip, date('c')]);

$lang = $en ? 'en' : 'de';
$lines = function (string $l) use ($goals, $places, $times, $msg, $phone): array {
    $r = [];
    if ($g = mm_pt_labels($goals, MM_PT_GOALS, $l)) $r[] = [$l === 'en' ? 'What you’re looking for' : 'Ich wünsche mir', implode(', ', $g)];
    if ($p = mm_pt_labels($places, MM_PT_PLACES, $l)) $r[] = [$l === 'en' ? 'Where' : 'Wo', implode(', ', $p)];
    if ($t = mm_pt_labels($times, MM_PT_TIMES, $l)) $r[] = [$l === 'en' ? 'When' : 'Wann', implode(', ', $t)];
    if ($phone !== '') $r[] = [$l === 'en' ? 'Phone' : 'Telefon', $phone];
    if ($msg !== '') $r[] = [$l === 'en' ? 'Your message' : 'Deine Nachricht', $msg];
    return $r;
};
$tbl = function (array $rows): string {
    $h = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:14px 0;background:#FBF8F4;border-radius:16px;">';
    foreach ($rows as [$k, $v]) $h .= '<tr><td style="padding:10px 16px;font-size:14px;color:#7D7076;vertical-align:top;width:34%;">' . forma_h($k) . '</td><td style="padding:10px 16px;font-size:15px;">' . nl2br(forma_h($v)) . '</td></tr>';
    return $h . '</table>';
};
$first = trim((string)strtok($name, ' '));

// Eingangsbestätigung an die Interessentin
try {
    $html = $en
        ? '<p>Hi ' . forma_h($first) . ',</p><p>thank you so much for reaching out about 1:1 personal training. Your request has arrived safely, and I’ll get back to you personally in the next few days so we can figure out together what’s right for you.</p>' . $tbl($lines('en')) . '<p>If anything else comes to mind, just reply to this email.</p>'
        : '<p>Hallo ' . forma_h($first) . ',</p><p>danke dir von Herzen für deine Anfrage zum 1:1 Personal Training. Sie ist gut bei mir angekommen, und ich melde mich in den nächsten Tagen persönlich bei dir, damit wir gemeinsam schauen, was für dich passt.</p>' . $tbl($lines('de')) . '<p>Wenn dir noch etwas einfällt, antworte einfach auf diese Mail.</p>';
    $text = strip_tags(str_replace(['</p>', '</tr>'], ["\n\n", "\n"], $html));
    mm_mail($email, $E('Deine Anfrage für Personal Training', 'Your personal training request'), $text, mm_mail_html($E('Deine Anfrage', 'Your request'), $html, $lang));
} catch (Throwable $e) { error_log('PT Bestätigung: ' . $e->getMessage()); }

// Info an Vivien
try {
    $rows = array_merge([['Name', $name], ['E-Mail', $email]], $lines('de'));
    $html = '<p>Neue Anfrage für 1:1 Personal Training' . ($en ? ' (auf Englisch)' : '') . ':</p>' . $tbl($rows)
        . '<p><a href="' . forma_h(forma_site_url() . '/verwaltung/?view=movecia#pt') . '" style="display:inline-block;background:#7D5E68;color:#FBF5F7;text-decoration:none;padding:12px 22px;border-radius:999px;">In der Verwaltung ansehen</a></p>';
    mm_mail(natal_notify_email(), 'Personal Training Anfrage: ' . $name, strip_tags(str_replace(['</p>', '</tr>'], ["\n\n", "\n"], $html)), mm_mail_html('Neue Anfrage', $html, 'de'));
} catch (Throwable $e) { error_log('PT Info: ' . $e->getMessage()); }

forma_json(['ok' => true]);

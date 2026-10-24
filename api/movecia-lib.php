<?php
// Movecia Movement / Wild Hearts Collective: eigene Daten, eigene Mails, getrennt von FORMA.
// Gemeinsam genutzt wird nur die Datenbank-Datei und der MailerLite-Schlüssel des Kontos.
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

const MM_FROM_EMAIL = 'hello@moveciamovement.com';
const MM_FROM_NAME = 'Viv · Wild Hearts Collective';
const MM_SITE = 'https://moveciamovement.com';
const MM_ML_GROUP = 'Wild Hearts';
const MM_CHANNEL = 'https://www.instagram.com/channel/wrOyV88oT-GAwNiR/';

function mm_site(): string
{
    return rtrim(getenv('MM_SITE_URL') ?: MM_SITE, '/');
}

function mm_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_gatherings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        starts_at TEXT NOT NULL,
        minutes INTEGER NOT NULL DEFAULT 60,
        description TEXT NOT NULL DEFAULT \'\',
        room_url TEXT NOT NULL DEFAULT \'\',
        host_url TEXT NOT NULL DEFAULT \'\',
        meeting_id TEXT NOT NULL DEFAULT \'\',
        visible INTEGER NOT NULL DEFAULT 1,
        reminded_day INTEGER NOT NULL DEFAULT 0,
        reminded_hour INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_signups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gathering_id INTEGER NOT NULL REFERENCES mm_gatherings(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        token TEXT NOT NULL,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'angemeldet\',
        joined_at TEXT,
        last_seen TEXT,
        created_at TEXT NOT NULL,
        UNIQUE(gathering_id, email)
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        source TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'offen\',
        token TEXT NOT NULL,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        ml_synced INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        confirmed_at TEXT
    )');
    $gc = array_column($pdo->query('PRAGMA table_info(mm_gatherings)')->fetchAll(), 'name');
    if (!in_array('script', $gc, true)) $pdo->exec("ALTER TABLE mm_gatherings ADD COLUMN script TEXT NOT NULL DEFAULT ''");
    $cols = array_column($pdo->query('PRAGMA table_info(mm_subscribers)')->fetchAll(), 'name');
    if (!in_array('lang', $cols, true)) $pdo->exec("ALTER TABLE mm_subscribers ADD COLUMN lang TEXT NOT NULL DEFAULT 'en'");
}

// --- Einstellungen (liegen nur im Datenordner, nie im Repo) ---------------------------

function mm_cfg(string $name): ?array
{
    $file = forma_data_dir() . '/movecia-' . $name . '.json';
    $cfg = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return is_array($cfg) ? $cfg : null;
}

function mm_save_cfg(string $name, ?array $cfg): void
{
    $file = forma_data_dir() . '/movecia-' . $name . '.json';
    if ($cfg === null) { @unlink($file); return; }
    file_put_contents($file, json_encode($cfg));
    @chmod($file, 0600);
}

function mm_whereby_key(): string
{
    return (string)(mm_cfg('whereby')['api_key'] ?? '');
}

// --- Whereby ----------------------------------------------------------------------------

function mm_whereby_request(string $method, string $path, ?array $body, string $key): array
{
    $base = rtrim(getenv('MM_WHEREBY_API') ?: 'https://api.whereby.dev/v1', '/');
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    if ($raw === false || $status >= 400) {
        throw new RuntimeException('Whereby: ' . (is_array($data) ? ($data['error'] ?? $data['message'] ?? 'HTTP ' . $status) : 'nicht erreichbar'));
    }
    return is_array($data) ? $data : [];
}

/** Prüft den Schlüssel, indem die Liste der Räume abgefragt wird. */
function mm_whereby_check(string $key): bool
{
    try { mm_whereby_request('GET', '/meetings?limit=1', null, $key); return true; } catch (Throwable $e) { error_log('MM whereby check: ' . $e->getMessage()); return false; }
}

/** Legt den Videoraum für ein Gathering an (Gruppenraum, offen bis eine Stunde nach Ende). */
function mm_whereby_room(PDO $pdo, int $gid): bool
{
    $key = mm_whereby_key();
    $g = mm_gathering($pdo, $gid);
    if ($key === '' || !$g) return false;
    $end = strtotime($g['starts_at']) + ((int)$g['minutes'] + 120) * 60;
    $r = mm_whereby_request('POST', '/meetings', [
        'endDate' => gmdate('Y-m-d\TH:i:s.000\Z', $end),
        'roomMode' => 'group',
        'roomNamePrefix' => 'wildhearts',
        'fields' => ['hostRoomUrl'],
    ], $key);
    if (empty($r['roomUrl'])) return false;
    $pdo->prepare('UPDATE mm_gatherings SET room_url = ?, host_url = ?, meeting_id = ? WHERE id = ?')
        ->execute([(string)$r['roomUrl'], (string)($r['hostRoomUrl'] ?? ''), (string)($r['meetingId'] ?? ''), $gid]);
    return true;
}

/**
 * Testraum zum Ausprobieren von Mikro, Kamera und Ton (unabhängig von den Gatherings).
 * Bleibt eine Woche bestehen und wird bei Bedarf neu angelegt. Die Testseite ist nur mit
 * dem geheimen Token erreichbar: moveciamovement.com/gathering/?test=TOKEN
 */
function mm_testroom(bool $renew = false): array
{
    $t = mm_cfg('testroom') ?? [];
    if (empty($t['token'])) $t['token'] = bin2hex(random_bytes(12));
    $valid = !empty($t['room_url']) && (int)($t['ends'] ?? 0) > time() + 3600;
    $key = mm_whereby_key();
    if (($renew || !$valid) && $key !== '') {
        $end = time() + 7 * 86400;
        $r = mm_whereby_request('POST', '/meetings', [
            'endDate' => gmdate('Y-m-d\TH:i:s.000\Z', $end),
            'roomMode' => 'group',
            'roomNamePrefix' => 'wildhearts-test',
            'fields' => ['hostRoomUrl'],
        ], $key);
        if (!empty($r['roomUrl'])) {
            $t['room_url'] = (string)$r['roomUrl'];
            $t['host_url'] = (string)($r['hostRoomUrl'] ?? $r['roomUrl']);
            $t['ends'] = $end;
        }
    }
    mm_save_cfg('testroom', $t);
    return $t;
}

function mm_testroom_link(array $t, bool $guest = false): string
{
    return mm_site() . '/gathering/?test=' . rawurlencode((string)$t['token']) . ($guest ? '&gast=1' : '');
}

// --- Gatherings -------------------------------------------------------------------------

function mm_gathering(PDO $pdo, int $id): ?array
{
    mm_schema($pdo);
    $s = $pdo->prepare('SELECT * FROM mm_gatherings WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** Das nächste sichtbare Gathering (läuft gerade oder kommt noch). */
function mm_next_gathering(PDO $pdo): ?array
{
    mm_schema($pdo);
    $all = $pdo->query('SELECT * FROM mm_gatherings WHERE visible = 1 ORDER BY starts_at')->fetchAll();
    foreach ($all as $g) {
        if (strtotime($g['starts_at']) + (int)$g['minutes'] * 60 > time()) return $g;
    }
    return null;
}

function mm_signup_count(PDO $pdo, int $gid): int
{
    $s = $pdo->prepare("SELECT COUNT(*) FROM mm_signups WHERE gathering_id = ? AND status = 'angemeldet'");
    $s->execute([$gid]);
    return (int)$s->fetchColumn();
}

/** Wer in den letzten 2 Minuten im Raum war (die Seite meldet sich jede Minute). */
function mm_live_count(PDO $pdo, int $gid): int
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM mm_signups WHERE gathering_id = ? AND last_seen >= ?');
    $s->execute([$gid, date('c', time() - 120)]);
    return (int)$s->fetchColumn();
}

function mm_joined_count(PDO $pdo, int $gid): int
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM mm_signups WHERE gathering_id = ? AND joined_at IS NOT NULL');
    $s->execute([$gid]);
    return (int)$s->fetchColumn();
}

/** Englische Zeitangabe für die Gäste, z. B. „Wednesday, 3 December · 7:00 pm (Vienna time)“. */
function mm_when(array $g): string
{
    $t = strtotime($g['starts_at']);
    return date('l, j F', $t) . ' · ' . date('g:i a', $t) . ' (Vienna time)';
}

function mm_when_de(array $g): string
{
    $t = strtotime($g['starts_at']);
    return forma_de_date(date('Y-m-d', $t)) . ', ' . date('H:i', $t) . ' Uhr';
}

function mm_room_link(array $s): string
{
    return mm_site() . '/gathering/?k=' . rawurlencode($s['token']);
}

/** Raum ist offen: 15 Minuten vor Beginn bis eine Stunde nach dem geplanten Ende. */
function mm_room_open(array $g): bool
{
    $t = strtotime($g['starts_at']);
    return time() >= $t - 15 * 60 && time() <= $t + ((int)$g['minutes'] + 60) * 60;
}

// --- Mails von hello@moveciamovement.com --------------------------------------------------

function mm_mail_html(string $title, string $html, string $lang = 'en'): string
{
    return '<!DOCTYPE html><html lang="' . ($lang === 'de' ? 'de' : 'en') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . forma_h($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#E8DED2;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E8DED2;"><tr><td align="center" style="padding:26px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#F2ECE4;border-radius:24px;font-family:Helvetica,Arial,sans-serif;color:#2E2529;">'
        . '<tr><td style="padding:24px 30px 0;"><img src="' . forma_h(mm_site()) . '/img/movecia/movecia-mail.png" width="160" height="36" alt="Movecia." style="display:block;border:0;width:160px;height:auto;"></td></tr>'
        . '<tr><td style="padding:18px 30px 26px;font-size:16px;line-height:1.65;">' . $html
        . '<p style="margin:22px 0 0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:24px;color:#7D5E68;">' . ($lang === 'de' ? 'alles Liebe, Viv' : 'with love, Viv') . '</p></td></tr></table>'
        . '<p style="max-width:560px;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#7D7076;margin:16px auto 0;">Vivien Malonga · Movecia – Personal Fitness · Silvrettastraße 58 · 6562 Mathon · Austria · ' . MM_FROM_EMAIL . '</p>'
        . '</td></tr></table></body></html>';
}

function mm_button(string $href, string $label): string
{
    return '<p style="margin:22px 0 4px;text-align:center;"><a href="' . forma_h($href) . '" style="display:inline-block;padding:15px 30px;border-radius:999px;background:#7D5E68;color:#F2ECE4;text-decoration:none;font-weight:bold;">' . forma_h($label) . '</a></p>';
}

/** Verschickt eine Mail als Viv. Mit hinterlegtem Postfach-Passwort per SMTP, sonst per mail(). */
function mm_mail(string $to, string $subject, string $text, string $html, array $attachments = [], string $fromName = MM_FROM_NAME): bool
{
    $b = 'mm' . bin2hex(random_bytes(10));
    $enc = fn(string $s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
    $mime = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($text) . "\r\n"
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($html) . "\r\n--$b--";
    $ctype = 'multipart/alternative; boundary="' . $b . '"';
    if ($attachments) {
        // Anhänge (z. B. Rechnung als PDF, Kalenderdatei): Text + HTML als ein Teil, dahinter die Dateien.
        $m = 'mx' . bin2hex(random_bytes(10));
        $mixed = "--$m\r\nContent-Type: $ctype\r\n\r\n$mime\r\n";
        foreach ($attachments as [$name, $data, $type]) {
            $mixed .= "--$m\r\nContent-Type: $type; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$name\"\r\n\r\n" . $enc((string)$data) . "\r\n";
        }
        $mime = $mixed . "--$m--";
        $ctype = 'multipart/mixed; boundary="' . $m . '"';
    }
    $from = mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . MM_FROM_EMAIL . '>';
    if (getenv('FORMA_MAIL_LOG')) {
        file_put_contents(getenv('FORMA_MAIL_LOG'), "FROM: $fromName <" . MM_FROM_EMAIL . ">\nTO: $to\nSUBJECT: $subject\n$text\n" . ($attachments ? 'ANHANG: ' . implode(', ', array_column($attachments, 0)) . "\n" : '') . "-----\n", FILE_APPEND);
        foreach ($attachments as [$n, $d]) @file_put_contents(dirname(getenv('FORMA_MAIL_LOG')) . '/mail_' . $n, $d);
        if (getenv('FORMA_MAIL_HTML_DIR')) @file_put_contents(getenv('FORMA_MAIL_HTML_DIR') . '/' . preg_replace('/[^a-z0-9]+/i', '_', $subject) . '.html', $html);
        if (!getenv('FORMA_SMTP_HOST')) return true;
    }
    $pw = (string)(mm_cfg('mail')['password'] ?? '');
    $err = '';
    if ($pw !== '') {
        try {
            mm_smtp($pw, $to, $subject, $ctype, $mime, $from);
            mm_mail_log($to, $subject, 'Postfach (SMTP)', true, '');
            return true;
        } catch (Throwable $e) {
            $err = $e->getMessage();
            error_log('MM SMTP: ' . $err);
        }
    } else {
        $err = 'Kein Postfach-Passwort hinterlegt';
    }
    $headers = ['From' => $from, 'Reply-To' => MM_FROM_EMAIL, 'MIME-Version' => '1.0', 'Content-Type' => $ctype];
    $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $mime, $headers, '-f' . MM_FROM_EMAIL);
    mm_mail_log($to, $subject, 'Ersatzweg mail()', $ok, $err);
    return $ok;
}

/** Protokoll der letzten Movecia-Mails, damit man in der Verwaltung sieht, ob etwas rausging. */
function mm_mail_log(string $to, string $subject, string $method, bool $ok, string $err): void
{
    try {
        $pdo = forma_db();
        $pdo->exec('CREATE TABLE IF NOT EXISTS mm_mail_log (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT NOT NULL, to_addr TEXT NOT NULL, subject TEXT NOT NULL, method TEXT NOT NULL, ok INTEGER NOT NULL, error TEXT NOT NULL DEFAULT \'\')');
        $pdo->prepare('INSERT INTO mm_mail_log (at, to_addr, subject, method, ok, error) VALUES (?,?,?,?,?,?)')->execute([date('c'), $to, mb_substr($subject, 0, 160), $method, $ok ? 1 : 0, mb_substr($err, 0, 300)]);
        $pdo->exec('DELETE FROM mm_mail_log WHERE id NOT IN (SELECT id FROM mm_mail_log ORDER BY id DESC LIMIT 200)');
    } catch (Throwable $e) {
        error_log('MM mail log: ' . $e->getMessage());
    }
}

function mm_smtp(string $password, string $to, string $subject, string $ctype, string $mime, string $from): void
{
    $host = getenv('FORMA_SMTP_HOST') ?: 'ssl://smtp.hostinger.com:465';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($host, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("Verbindung fehlgeschlagen: $errstr");
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $out;
    };
    $cmd = function (?string $line, array $ok) use ($fp, $read): string {
        if ($line !== null) fwrite($fp, $line . "\r\n");
        $resp = $read();
        if (!in_array((int)substr($resp, 0, 3), $ok, true)) throw new RuntimeException('SMTP-Antwort: ' . trim($resp));
        return $resp;
    };
    try {
        $cmd(null, [220]);
        $cmd('EHLO moveciamovement.com', [250]);
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(MM_FROM_EMAIL), [334]);
        $cmd(base64_encode($password), [235]);
        $cmd('MAIL FROM:<' . MM_FROM_EMAIL . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $headers = [
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@moveciamovement.com>',
            'From: ' . $from,
            'To: <' . $to . '>',
            'Reply-To: ' . MM_FROM_EMAIL,
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'MIME-Version: 1.0',
            'Content-Type: ' . $ctype,
        ];
        $cmd(implode("\r\n", $headers) . "\r\n\r\n" . $mime . "\r\n.", [250]);
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/** Bestätigung mit persönlichem Raum-Link. */
function mm_mail_signup(array $g, array $s): bool
{
    $first = forma_first_name($s['name']);
    $link = mm_room_link($s);
    $when = mm_when($g);
    $text = "Hey $first,\n\nyou're in! I've saved your spot for our Wild Hearts Gathering:\n\n{$g['title']}\n$when\n\nThis is your personal link to the room. It opens 15 minutes before we start, right on my website, no app or Zoom needed:\n$link\n\nI'll send you a little reminder the day before and one hour before we start.\n\nWith love, Viv\n\nYou get this email because you signed up for this Gathering on moveciamovement.com.\nVivien Malonga, Movecia – Personal Fitness, Silvrettastraße 58, 6562 Mathon, Austria";
    $html = '<p style="margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#7D5E68;">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">you\'re in! I\'ve saved your spot for our Wild Hearts Gathering.</p>'
        . '<p style="margin:16px 0 0;padding:16px 18px;background:#EADFDE;border-radius:16px;"><strong>' . forma_h($g['title']) . '</strong><br>' . forma_h($when) . '</p>'
        . '<p style="margin:16px 0 0;">This is your personal link to the room. It opens 15 minutes before we start, right on my website, no app or Zoom needed.</p>'
        . mm_button($link, 'My room link') . '<p style="margin:14px 0 0;">I\'ll send you a little reminder the day before and one hour before we start.</p>';
    return mm_mail($s['email'], 'Your spot is saved: ' . $g['title'] . ' 🌙', $text, mm_mail_html($g['title'], $html));
}

function mm_mail_reminder(array $g, array $s, bool $soon): bool
{
    $first = forma_first_name($s['name']);
    $link = mm_room_link($s);
    $intro = $soon ? 'we start in one hour. Grab something warm to drink, find a cosy spot and come as you are.' : 'tomorrow is our Wild Hearts Gathering, and I am so looking forward to seeing you.';
    $text = "Hey $first,\n\n$intro\n\n{$g['title']}\n" . mm_when($g) . "\n\nYour room link (opens 15 minutes before):\n$link\n\nWith love, Viv";
    $html = '<p style="margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#7D5E68;">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">' . forma_h($intro) . '</p>'
        . '<p style="margin:16px 0 0;padding:16px 18px;background:#EADFDE;border-radius:16px;"><strong>' . forma_h($g['title']) . '</strong><br>' . forma_h(mm_when($g)) . '</p>'
        . mm_button($link, 'Open my room') . '<p style="margin:10px 0 0;font-size:14px;color:#7D7076;text-align:center;">The room opens 15 minutes before we start.</p>';
    return mm_mail($s['email'], ($soon ? 'In one hour: ' : 'Tomorrow: ') . $g['title'] . ' 🌙', $text, mm_mail_html($g['title'], $html));
}

/** Erinnerungen automatisch: 24 Std. und 1 Std. vorher. Wird bei Seitenaufrufen angestoßen. */
function mm_send_reminders(PDO $pdo): void
{
    mm_schema($pdo);
    $now = time();
    foreach ($pdo->query('SELECT * FROM mm_gatherings WHERE visible = 1')->fetchAll() as $g) {
        $t = strtotime($g['starts_at']);
        if ($t < $now) continue;
        foreach ([['reminded_day', 86400, false], ['reminded_hour', 3600, true]] as [$col, $before, $soon]) {
            if ((int)$g[$col] || $t - $now > $before) continue;
            if ($col === 'reminded_day' && $t - $now < 3 * 3600) { $pdo->prepare("UPDATE mm_gatherings SET reminded_day = 1 WHERE id = ?")->execute([$g['id']]); continue; }
            // erst sperren, dann verschicken, damit nichts doppelt rausgeht
            $u = $pdo->prepare("UPDATE mm_gatherings SET $col = 1 WHERE id = ? AND $col = 0");
            $u->execute([$g['id']]);
            if ($u->rowCount() !== 1) continue;
            $s = $pdo->prepare("SELECT * FROM mm_signups WHERE gathering_id = ? AND status = 'angemeldet'");
            $s->execute([$g['id']]);
            foreach ($s->fetchAll() as $row) {
                try { mm_mail_reminder($g, $row, $soon); } catch (Throwable $e) { error_log('MM reminder: ' . $e->getMessage()); }
            }
        }
    }
}

// --- MailerLite: Zahlen der Gruppe „Wild Hearts“ -----------------------------------------

/** Liefert active/unconfirmed/unsubscribed der Gruppe oder null, wenn MailerLite nicht verbunden ist. */
function mm_ml_stats(string $name = MM_ML_GROUP): ?array
{
    $cfg = forma_mailerlite();
    if (!$cfg) return null;
    try {
        $list = forma_ml_request('GET', '/api/groups?limit=100&filter%5Bname%5D=' . rawurlencode($name), null, $cfg['api_key']);
        foreach ($list['data'] ?? [] as $g) {
            if (($g['name'] ?? '') === $name) {
                return [
                    'active' => (int)($g['active_count'] ?? 0),
                    'unconfirmed' => (int)($g['unconfirmed_count'] ?? 0),
                    'unsubscribed' => (int)($g['unsubscribed_count'] ?? 0),
                    'open_rate' => (string)($g['open_rate']['string'] ?? ''),
                    'id' => (string)$g['id'],
                ];
            }
        }
        return ['active' => 0, 'unconfirmed' => 0, 'unsubscribed' => 0, 'open_rate' => '', 'id' => ''];
    } catch (Throwable $e) {
        error_log('MM ML stats: ' . $e->getMessage());
        return ['error' => true];
    }
}

// --- Eigene E-Mail-Liste Wild Hearts (Double-Opt-in über die Website) -------------------

function mm_sub_by_token(PDO $pdo, string $tok): ?array
{
    mm_schema($pdo);
    if (!preg_match('/^[0-9a-f]{32}$/', $tok)) return null;
    $s = $pdo->prepare('SELECT * FROM mm_subscribers WHERE token = ?');
    $s->execute([$tok]);
    return $s->fetch() ?: null;
}

function mm_mail_confirm(array $sub): bool
{
    $de = ($sub['lang'] ?? 'en') === 'de';
    $first = forma_first_name($sub['name']);
    $link = mm_site() . '/api/wh-confirm.php?t=' . $sub['token'];
    $hand = 'margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#7D5E68;';
    if (($sub['source'] ?? '') === 'unfold') {
        // Unfold-Infoliste (Vivien, 24.10.2026): erst bestätigen, dann kommen alle Infos, bevor Unfold offiziell gepostet wird
        if ($de) {
            $text = "Hey $first,\n\ndanke, dass du dich für Unfold – The Mindful Becoming eingetragen hast!\n\nBitte bestätige zuerst deine E-Mail-Adresse:\n$link\n\nAnschließend schicke ich dir alle Infos, noch bevor ich offiziell poste, wann Unfold startet. Es gibt höchstens 10 Plätze.\n\nFalls du dich nicht eingetragen hast, ignoriere diese Mail einfach.\n\nAlles Liebe, Viv";
            $html = '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
                . '<p style="margin:14px 0 0;">danke, dass du dich für <strong>Unfold – The Mindful Becoming</strong> eingetragen hast!</p>'
                . '<p style="margin:14px 0 0;">Bitte bestätige zuerst deine E-Mail-Adresse. Anschließend schicke ich dir alle Infos, noch bevor ich offiziell poste, wann Unfold startet. Es gibt höchstens 10 Plätze.</p>'
                . mm_button($link, 'Ja, ich bestätige meine E-Mail')
                . '<p style="margin:16px 0 0;font-size:14px;color:#7D7076;">Falls du dich nicht eingetragen hast, ignoriere diese Mail einfach.</p>';
            return mm_mail($sub['email'], 'Bitte bestätige deine E-Mail für Unfold 🌿', $text, mm_mail_html('E-Mail bestätigen', $html, 'de'));
        }
        $text = "Hey $first,\n\nthank you for joining the list for Unfold – The Mindful Becoming!\n\nPlease confirm your email address first:\n$link\n\nRight after that I'll send you all the details, before I officially share when Unfold starts. There are no more than 10 spots.\n\nIf you didn't sign up, simply ignore this email.\n\nWith love, Viv";
        $html = '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
            . '<p style="margin:14px 0 0;">thank you for joining the list for <strong>Unfold – The Mindful Becoming</strong>!</p>'
            . '<p style="margin:14px 0 0;">Please confirm your email address first. Right after that I\'ll send you all the details, before I officially share when Unfold starts. There are no more than 10 spots.</p>'
            . mm_button($link, 'Yes, confirm my email')
            . '<p style="margin:16px 0 0;font-size:14px;color:#7D7076;">If you didn\'t sign up, simply ignore this email.</p>';
        return mm_mail($sub['email'], 'Please confirm your email for Unfold 🌿', $text, mm_mail_html('Confirm your email', $html));
    }
    if ($de) {
        $text = "Hey $first,\n\nnur noch ein Klick, dann bist du dabei. Bitte bestätige hier deine E-Mail-Adresse:\n$link\n\nGleich danach wartet deine Reset-Routine für müde Tage auf dich, auf Deutsch und Englisch.\n\nFalls du dich nicht angemeldet hast, ignoriere diese Mail einfach. Dann hörst du nichts mehr von mir.\n\nAlles Liebe, Viv";
        $html = '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
            . '<p style="margin:14px 0 0;">nur noch ein Klick, dann bist du dabei. Bitte bestätige deine E-Mail-Adresse. Gleich danach wartet deine <strong>Reset-Routine für müde Tage</strong> auf dich, auf Deutsch und Englisch.</p>'
            . mm_button($link, 'Ja, ich bestätige meine E-Mail')
            . '<p style="margin:16px 0 0;font-size:14px;color:#7D7076;">Falls du dich nicht angemeldet hast, ignoriere diese Mail einfach. Dann hörst du nichts mehr von mir.</p>';
        return mm_mail($sub['email'], 'Ein Klick, und du bist dabei 🌙', $text, mm_mail_html('E-Mail bestätigen', $html, 'de'));
    }
    $text = "Hey $first,\n\none click and you're in. Please confirm your email address here:\n$link\n\nRight after that your Reset Routine for Tired Days is ready for you, in English and Deutsch.\n\nIf you didn't sign up, simply ignore this email and you won't hear from me again.\n\nWith love, Viv";
    $html = '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">one click and you\'re in. Please confirm your email address, and right after that your <strong>Reset Routine for Tired Days</strong> is ready for you, in English and Deutsch.</p>'
        . mm_button($link, 'Yes, confirm my email')
        . '<p style="margin:16px 0 0;font-size:14px;color:#7D7076;">If you didn\'t sign up, simply ignore this email and you won\'t hear from me again.</p>';
    return mm_mail($sub['email'], "One click and you're in 🌙", $text, mm_mail_html('Confirm your email', $html));
}

/** Viv bekommt Bescheid, wenn jemand Interesse an einem Angebot zeigt (nach der Bestätigung). */
function mm_notify_interest(array $sub, string $source): void
{
    $what = ['mentoring' => 'Mentoring für ihr Herzensbusiness', 'year' => 'den Jahreskreis', 'circle' => 'den Inner Circle', 'retreat' => 'das Alpine Retreat', 'unfold' => 'Unfold – The Mindful Becoming'][$source] ?? $source;
    $t = "Hallo Viv,\n\n{$sub['name']} ({$sub['email']}) interessiert sich für $what und hat ihre E-Mail-Adresse bestätigt.\nSprache: " . (($sub['lang'] ?? 'en') === 'de' ? 'Deutsch' : 'Englisch') . "\n\nDu siehst sie in der Verwaltung unter Movecia Movement → E-Mail-Liste.\n";
    mm_mail(MM_FROM_EMAIL, 'Neue Interessentin: ' . $sub['name'] . ' · ' . $what, $t, mm_mail_html('Neue Interessentin', nl2br(forma_h($t)), 'de'));
}

/** Unfold-Infomail nach der Bestätigung (DE/EN): was es ist, Preise, Info-Gathering, Reset-Routine als kleines Geschenk. */
function mm_mail_unfold_info(array $sub): bool
{
    $de = ($sub['lang'] ?? 'en') === 'de';
    $first = forma_first_name($sub['name']);
    $page = mm_site() . '/unfold/?lang=' . ($de ? 'de' : 'en');
    $out = mm_site() . '/api/wh-unsubscribe.php?t=' . $sub['token'];
    $hand = 'margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#7D5E68;';
    $img = '<img src="' . mm_site() . '/img/wildhearts/cover.jpg" width="500" alt="" style="display:block;width:100%;max-width:500px;height:auto;max-height:340px;object-fit:cover;border-radius:16px;margin:0 0 20px;">';
    $li = fn(array $xs) => '<ul style="margin:10px 0 0;padding-left:20px;">' . implode('', array_map(fn($x) => '<li style="margin:4px 0;">' . $x . '</li>', $xs)) . '</ul>';
    if ($de) {
        $items = ['Ein Live-Call pro Woche in kleiner Runde, immer ein Termin auf Deutsch und einer auf Englisch', 'Jeden Monat ein Workbook zu Mindset, Selbstwert und deinen Ideen, mit Journaling-Fragen', 'Kurze Impuls-Videos von mir', 'Drei Monate Zugang zur Movecia Mindful Movement Method: sanfte Flows, Breathwork, Somatic Movement, Meditation', 'Alle Aufnahmen, falls du einmal nicht live dabei sein kannst'];
        $text = "Hey $first,\n\nschön, dass du dabei bist! Hier sind alle Infos zu Unfold – The Mindful Becoming, noch bevor ich es offiziell poste.\n\nUnfold ist eine Begleitung über drei Monate für Frauen, die ins Tun kommen wollen, aber nicht wissen, wo sie anfangen sollen. Wir starten am 1. Februar 2027.\n\nFebruar · Wurzeln: ankommen und Raum schaffen\nMärz · Knospen: Selbstwert und Ideen\nApril · Entfalten: ins Tun kommen\n\nDabei:\n– " . implode("\n– ", $items) . "\n\nEs gibt höchstens 10 Plätze.\nPreis: € 450 auf einmal oder 3 × € 160.\nVor dem Start gibt es ein kostenloses Info-Gathering (30 Minuten). Nur dort bekommst du Unfold für € 390. Den Termin schicke ich dir rechtzeitig.\n\nAlle Details: $page\n\nAlles Liebe, Viv\n\nAbmelden: $out";
        $html = $img . '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
            . '<p style="margin:14px 0 0;">schön, dass du dabei bist! Hier sind alle Infos zu <strong>Unfold – The Mindful Becoming</strong>, noch bevor ich es offiziell poste.</p>'
            . '<p style="margin:14px 0 0;">Unfold ist eine Begleitung über drei Monate für Frauen, die ins Tun kommen wollen, aber nicht wissen, wo sie anfangen sollen. Wir starten am <strong>1. Februar 2027</strong>.</p>'
            . '<p style="margin:16px 0 0;"><strong>Februar · Wurzeln</strong> ankommen und Raum schaffen<br><strong>März · Knospen</strong> Selbstwert und Ideen<br><strong>April · Entfalten</strong> ins Tun kommen</p>'
            . '<p style="margin:16px 0 0;">Das ist dabei:</p>' . $li($items)
            . '<p style="margin:16px 0 0;background:#F2ECE4;border-radius:14px;padding:14px 16px;">Höchstens <strong>10 Plätze</strong>.<br>Preis: <strong>€ 450</strong> auf einmal oder 3 × € 160.<br>Vor dem Start gibt es ein kostenloses <strong>Info-Gathering</strong> (30 Minuten). Nur dort bekommst du Unfold für <strong>€ 390</strong>. Den Termin schicke ich dir rechtzeitig.</p>'
            . mm_button($page, 'Alle Details ansehen')
            . '<p style="margin:22px 0 0;font-size:12px;color:#7D7076;">Du bekommst diese E-Mail, weil du dich auf moveciamovement.com eingetragen hast. <a href="' . forma_h($out) . '" style="color:#7D7076;">Abmelden</a></p>';
        return mm_mail($sub['email'], 'Unfold – The Mindful Becoming: alle Infos für dich 🌿', $text, mm_mail_html('Unfold · The Mindful Becoming', $html, 'de'));
    }
    $items = ['One live call per week in a small circle, always one call in English and one in German', 'A monthly workbook for mindset, self-worth and your ideas, with journaling prompts', 'Short talk videos from me', 'Three months of the Movecia Mindful Movement Method: gentle flows, breathwork, somatic movement, meditation', 'All recordings, in case you can\'t join live'];
    $text = "Hey $first,\n\nso lovely to have you! Here is everything about Unfold – The Mindful Becoming, before I officially share it.\n\nUnfold is a three-month journey for women who are ready to begin but don't know where to start. We start on 1 February 2027.\n\nFebruary · Roots: arrive and make space\nMarch · Buds: self-worth and ideas\nApril · Unfold: begin\n\nIncluded:\n– " . implode("\n– ", $items) . "\n\nThere are no more than 10 spots.\nPrice: € 450 at once or 3 × € 160.\nBefore the start there is a free Info Gathering (30 minutes). Only there you get Unfold for € 390. I'll send you the date in good time.\n\nAll details: $page\n\nWith love, Viv\n\nUnsubscribe: $out";
    $html = $img . '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">so lovely to have you! Here is everything about <strong>Unfold – The Mindful Becoming</strong>, before I officially share it.</p>'
        . '<p style="margin:14px 0 0;">Unfold is a three-month journey for women who are ready to begin but don\'t know where to start. We start on <strong>1 February 2027</strong>.</p>'
        . '<p style="margin:16px 0 0;"><strong>February · Roots</strong> arrive and make space<br><strong>March · Buds</strong> self-worth and ideas<br><strong>April · Unfold</strong> begin</p>'
        . '<p style="margin:16px 0 0;">What\'s included:</p>' . $li($items)
        . '<p style="margin:16px 0 0;background:#F2ECE4;border-radius:14px;padding:14px 16px;">No more than <strong>10 spots</strong>.<br>Price: <strong>€ 450</strong> at once or 3 × € 160.<br>Before the start there is a free <strong>Info Gathering</strong> (30 minutes). Only there you get Unfold for <strong>€ 390</strong>. I\'ll send you the date in good time.</p>'
        . mm_button($page, 'See all details')
        . '<p style="margin:22px 0 0;font-size:12px;color:#7D7076;">You get this email because you signed up on moveciamovement.com. <a href="' . forma_h($out) . '" style="color:#7D7076;">Unsubscribe</a></p>';
    return mm_mail($sub['email'], 'Unfold – The Mindful Becoming: everything for you 🌿', $text, mm_mail_html('Unfold · The Mindful Becoming', $html));
}

function mm_mail_welcome(array $sub): bool
{
    $de = ($sub['lang'] ?? 'en') === 'de';
    $first = forma_first_name($sub['name']);
    $dl = mm_site() . '/reset/';
    $out = mm_site() . '/api/wh-unsubscribe.php?t=' . $sub['token'];
    $hand = 'margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#7D5E68;';
    $img = '<img src="' . mm_site() . '/img/wildhearts/cover.jpg" width="500" alt="" style="display:block;width:100%;max-width:500px;height:auto;max-height:360px;object-fit:cover;border-radius:16px;margin:0 0 20px;">';
    if ($de) {
        $text = "Hey $first,\n\ndu hast es geschafft, du bist dabei. Willkommen im Wild Hearts Collective.\n\nHier ist mein kleines Geschenk für dich: die Reset-Routine für müde Tage. Zehn stille Minuten für die Abende, an denen du alles gegeben hast.\n\nDownload (Deutsch oder Englisch): $dl\n\nDas Wild Hearts Collective gibt es, damit du nie vergisst, dass auch du ein ganzer Mensch bist, selbst wenn du immer für alle anderen da bist. Wir sind wichtig. Wir sind da. Und wir werden gesehen.\n\nKomm in unseren kostenlosen Community-Kanal auf Instagram: " . MM_CHANNEL . "\nDort schreiben wir auf Englisch, aber du bist von Herzen willkommen. Unser erstes Live-Gathering findet im Dezember statt.\n\nAlles Liebe, Viv\n\nAbmelden: $out";
        $html = $img . '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
            . '<p style="margin:14px 0 0;">du hast es geschafft, du bist dabei. Willkommen im Wild Hearts Collective.</p>'
            . '<p style="margin:14px 0 0;">Hier ist mein kleines Geschenk für dich: die <strong>Reset-Routine für müde Tage</strong>. Zehn stille Minuten für die Abende, an denen du alles gegeben hast. Ein bewusster Atemzug, ein kleines Ritual zum Loslassen, eine warme Tasse Goldene Mondmilch und ein paar ehrliche Zeilen, nur für dich.</p>'
            . mm_button($dl, 'Zu meiner Reset-Routine')
            . '<p style="margin:8px 0 0;font-size:14px;color:#7D7076;text-align:center;">Auf der Seite wählst du Deutsch oder Englisch.</p>'
            . '<p style="margin:20px 0 0;">Das Wild Hearts Collective gibt es, damit du nie vergisst, dass auch du ein ganzer Mensch bist, selbst wenn du immer für alle anderen da bist. Wir sind wichtig. Wir sind da. Und wir werden gesehen.</p>'
            . '<p style="margin:14px 0 0;">Komm und sag Hallo in unserem kostenlosen <a href="' . MM_CHANNEL . '" style="color:#7D5E68;">Community-Kanal auf Instagram</a>. Dort schreiben wir auf Englisch, aber du bist von Herzen willkommen. Unser erstes Live-Gathering findet im Dezember statt.</p>'
            . '<p style="margin:22px 0 0;font-size:12px;color:#7D7076;">Du bekommst diese E-Mail, weil du dich auf moveciamovement.com angemeldet hast. <a href="' . forma_h($out) . '" style="color:#7D7076;">Abmelden</a></p>';
        return mm_mail($sub['email'], 'Deine Reset-Routine für müde Tage 🌙', $text, mm_mail_html('Deine Reset-Routine', $html, 'de'));
    }
    $text = "Hey $first,\n\nyou did it, you're in. Welcome to the Wild Hearts Collective.\n\nHere is my little gift for you: the Reset Routine for Tired Days. Ten quiet minutes for the evenings you gave everything away.\n\nDownload (English or Deutsch): $dl\n\nThe Wild Hearts Collective exists so you never forget that you are a whole person too, even when you're always there for everyone else. We matter. We are here. And we are seen.\n\nCome and say hi in our free community channel on Instagram: " . MM_CHANNEL . "\nOur first live Wild Hearts Gathering is in December.\n\nWith love, Viv\n\nUnsubscribe: $out";
    $html = $img . '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">you did it, you\'re in. Welcome to the Wild Hearts Collective.</p>'
        . '<p style="margin:14px 0 0;">Here is my little gift for you: the <strong>Reset Routine for Tired Days</strong>. Ten quiet minutes for the evenings you gave everything away. A breath, a small ritual to let go, a warm cup of Golden Moon Milk and a few honest lines, just for you.</p>'
        . mm_button($dl, 'Get my Reset Routine')
        . '<p style="margin:8px 0 0;font-size:14px;color:#7D7076;text-align:center;">English or Deutsch, you choose on the page.</p>'
        . '<p style="margin:20px 0 0;">The Wild Hearts Collective exists so you never forget that you are a whole person too, even when you\'re always there for everyone else. We matter. We are here. And we are seen.</p>'
        . '<p style="margin:14px 0 0;">Come and say hi in our free <a href="' . MM_CHANNEL . '" style="color:#7D5E68;">community channel on Instagram</a>. Our first live Wild Hearts Gathering is in December.</p>'
        . '<p style="margin:22px 0 0;font-size:12px;color:#7D7076;">You get this email because you signed up on moveciamovement.com. <a href="' . forma_h($out) . '" style="color:#7D7076;">Unsubscribe</a></p>';
    return mm_mail($sub['email'], 'Your Reset Routine for Tired Days 🌙', $text, mm_mail_html('Your Reset Routine', $html));
}

/** Bestätigte Adresse in die MailerLite-Gruppe „Wild Hearts“ übertragen (oder abmelden). */
function mm_ml_push(PDO $pdo, array $sub, bool $active = true): bool
{
    $cfg = forma_mailerlite();
    if (!$cfg) return false;
    try {
        $body = ['email' => $sub['email'], 'fields' => ['name' => $sub['name']], 'status' => $active ? 'active' : 'unsubscribed'];
        if ($active) $body['groups'] = [forma_ml_group($cfg['api_key'], MM_ML_GROUP)];
        forma_ml_request('POST', '/api/subscribers', $body, $cfg['api_key']);
        $pdo->prepare('UPDATE mm_subscribers SET ml_synced = 1 WHERE id = ?')->execute([$sub['id']]);
        return true;
    } catch (Throwable $e) {
        error_log('MM ML push: ' . $e->getMessage());
        return false;
    }
}

/** Liste nachziehen, falls MailerLite beim Bestätigen nicht erreichbar war. */
function mm_ml_retry(PDO $pdo): void
{
    mm_schema($pdo);
    foreach ($pdo->query("SELECT * FROM mm_subscribers WHERE status = 'bestaetigt' AND ml_synced = 0 LIMIT 20")->fetchAll() as $s) mm_ml_push($pdo, $s);
}

/** Seite für Bestätigung/Abmeldung im Wild-Hearts-Look. */
function mm_page(string $hi, string $title, string $text, string $btnHref = '', string $btnLabel = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>' . forma_h($title) . ' · Wild Hearts</title>'
        . '<style>@font-face{font-family:"Italiana";src:url(/fonts/Italiana-Regular.woff) format("woff")}@font-face{font-family:"Outfit";src:url(/fonts/Outfit-Regular.woff) format("woff")}@font-face{font-family:"Hand";src:url(/fonts/HomemadeApple-Regular.woff) format("woff")}'
        . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F2ECE4;color:#2E2529;font:17px/1.6 Outfit,system-ui,sans-serif;text-align:center;padding:24px 16px}main{max-width:520px}'
        . '.hi{font-family:Hand,cursive;color:#7D5E68;font-size:22px;transform:rotate(-2deg)}h1{font-family:Italiana,Georgia,serif;font-weight:400;font-size:44px;line-height:1.05;color:#6B5658;margin:8px 0 12px}p{color:#7D7076}'
        . 'a.b{display:inline-block;margin-top:18px;padding:14px 26px;border-radius:999px;background:#7D5E68;color:#F2ECE4;text-decoration:none}</style></head><body><main>'
        . '<p class="hi">' . forma_h($hi) . '</p><h1>' . forma_h($title) . '</h1><p>' . forma_h($text) . '</p>'
        . ($btnHref ? '<a class="b" href="' . forma_h($btnHref) . '">' . forma_h($btnLabel) . '</a>' : '')
        . '</main></body></html>';
    exit;
}


// --- Angebote am Ende eines Gatherings (Inner Circle, Jahreskreis, Alpine Retreat) ----------

const MM_OFFER_KEYS = ['circle', 'year', 'retreat'];

function mm_offer_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    // Eine Einblendung pro Gathering bzw. Testraum: Preis fürs Gathering gilt bis „until“ (Server-Zeit)
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_spotlights (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        scope TEXT NOT NULL,
        offer TEXT NOT NULL,
        title TEXT NOT NULL,
        text TEXT NOT NULL DEFAULT \'\',
        billing TEXT NOT NULL DEFAULT \'once\',
        regular_cents INTEGER NOT NULL,
        special_cents INTEGER NOT NULL,
        shown_at INTEGER NOT NULL,
        until INTEGER NOT NULL,
        active INTEGER NOT NULL DEFAULT 1
    )');
    $sc = array_column($pdo->query('PRAGMA table_info(mm_spotlights)')->fetchAll(), 'name');
    if (!in_array('items', $sc, true)) $pdo->exec("ALTER TABLE mm_spotlights ADD COLUMN items TEXT NOT NULL DEFAULT ''");
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        spotlight_id INTEGER NOT NULL DEFAULT 0,
        scope TEXT NOT NULL DEFAULT \'\',
        offer TEXT NOT NULL,
        title TEXT NOT NULL,
        billing TEXT NOT NULL DEFAULT \'once\',
        name TEXT NOT NULL DEFAULT \'\',
        email TEXT NOT NULL DEFAULT \'\',
        amount_cents INTEGER NOT NULL,
        special INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT \'offen\',
        stripe_session TEXT NOT NULL DEFAULT \'\',
        token TEXT NOT NULL,
        test INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        paid_at TEXT
    )');
    $oc = array_column($pdo->query("SELECT name FROM pragma_table_info('mm_orders')")->fetchAll(), 'name');
    if (!in_array('plan', $oc, true)) { $pdo->exec("ALTER TABLE mm_orders ADD COLUMN plan TEXT NOT NULL DEFAULT 'full'"); $pdo->exec("ALTER TABLE mm_orders ADD COLUMN consent INTEGER NOT NULL DEFAULT 0"); $pdo->exec("ALTER TABLE mm_orders ADD COLUMN sub_id TEXT NOT NULL DEFAULT ''"); }
    if (!in_array('consent_digital', $oc, true)) $pdo->exec("ALTER TABLE mm_orders ADD COLUMN consent_digital INTEGER NOT NULL DEFAULT 0");
    if (!in_array('ref_code', $oc, true)) {
        // Empfehlungen (Inner Circle): eigener Einladungslink, wer wen eingeladen hat, wie viele Monate zum Vorteilspreis
        $pdo->exec("ALTER TABLE mm_orders ADD COLUMN ref_code TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE mm_orders ADD COLUMN referred_by INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE mm_orders ADD COLUMN ref_mail_sent INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE mm_orders ADD COLUMN special_months INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE mm_orders ADD COLUMN ref_bonus INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('bill_start', $oc, true)) $pdo->exec("ALTER TABLE mm_orders ADD COLUMN bill_start INTEGER NOT NULL DEFAULT 0");
    if (!in_array('stay_code', $oc, true)) {
        // Letzte Chance nach den Einführungsmonaten: persönlicher Code für weitere Monate zum Vorteilspreis
        $pdo->exec("ALTER TABLE mm_orders ADD COLUMN stay_code TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE mm_orders ADD COLUMN stay_until INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE mm_orders ADD COLUMN stay_used INTEGER NOT NULL DEFAULT 0");
    }
}

/** Die drei Angebote mit Viv's Einstellungen (Titel, Text auf Englisch, regulärer Preis, Zahlungsart). */
function mm_offers(): array
{
    $def = [
        'circle' => ['price' => 3900, 'special' => 2900, 'periods' => [[6, 17400], [12, 31200]], 'title' => 'The Inner Circle', 'billing' => 'monthly', 'text' => 'Your monthly circle: live gatherings, breathwork, gentle movement and a members area with rituals and affirmations. A space where women support each other and come back to themselves.'],
        'year' => ['price' => 240000, 'special' => 199000, 'plans' => [[3, 80000, 66400], [6, 40000, 33200]], 'title' => 'The Year Circle', 'billing' => 'once', 'text' => 'Twelve months together: The Inner Circle plus 1:1 mentoring for the idea that is close to your heart. Like a best friend who walks the whole year with you.'],
        'retreat' => ['price' => 79000, 'special' => 69000, 'title' => 'Wild Hearts Alpine Retreat', 'billing' => 'once', 'text' => 'A weekend in the Austrian Alps: yoga, Pilates, forest walks, breathwork, pottery, cooking together and a women\'s circle. Lots of quiet, lots of nature, back to you.'],
    ];
    $cfg = mm_cfg('offers') ?? [];
    $out = [];
    foreach (MM_OFFER_KEYS as $k) {
        $c = is_array($cfg[$k] ?? null) ? $cfg[$k] : [];
        $out[$k] = [
            'title' => (string)($c['title'] ?? $def[$k]['title']),
            'text' => (string)($c['text'] ?? $def[$k]['text']),
            'billing' => in_array($c['billing'] ?? '', ['once', 'monthly'], true) ? $c['billing'] : $def[$k]['billing'],
            'price_cents' => max(0, (int)($c['price_cents'] ?? 0)) ?: $def[$k]['price'],
            'special_cents' => max(0, (int)($c['special_cents'] ?? 0)) ?: $def[$k]['special'],
            // Ratenpläne (nur Jahreskreis): [Anzahl Raten, Rate regulär, Rate zum Gathering-Preis], z. B. 3 oder 6 Raten
            'plans' => array_values(array_filter(array_map(fn($x) => [max(0, (int)($x[0] ?? 0)), max(0, (int)($x[1] ?? 0)), max(0, (int)($x[2] ?? 0))], is_array($c['plans'] ?? null) ? $c['plans'] : ($def[$k]['plans'] ?? [])), fn($x) => $x[0] >= 2 && $x[1] > 0)),
            // Inner Circle im Voraus: [Monate, Preis für den ganzen Zeitraum], verlängert sich, kündbar zum Ende des Zeitraums
            'periods' => array_values(array_filter(array_map(fn($x) => [(int)($x[0] ?? 0), max(0, (int)($x[1] ?? 0))], is_array($c['periods'] ?? null) ? $c['periods'] : ($def[$k]['periods'] ?? [])), fn($x) => in_array($x[0], [6, 12], true) && $x[1] > 0)),
            'minutes' => max(5, min(180, (int)($c['minutes'] ?? 25))),
            // Inner Circle: Vorteilspreis gilt nur für die ersten Monate, Empfehlung verlängert ihn, mit Obergrenze
            'intro_months' => max(1, (int)($c['intro_months'] ?? 3)),
            'ref_months' => max(1, (int)($c['ref_months'] ?? 3)),
            'max_months' => max(1, (int)($c['max_months'] ?? 6)),
            // Inner Circle: Start der Mitgliedschaft; bis dahin buchbar, abgebucht wird erst ab diesem Tag
            'start' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($c['start'] ?? '')) ? (string)$c['start'] : ($k === 'circle' ? '2026-12-01' : ''),
        ];
    }
    return $out;
}

function mm_euro_in(string $v): int
{
    $v = str_replace([' ', '€'], '', trim($v));
    if ($v === '') return 0;
    if (str_contains($v, ',')) $v = str_replace(['.', ','], ['', '.'], $v);
    return max(0, (int)round((float)$v * 100));
}

function mm_price_en(int $cents, string $billing): string
{
    $p = '€' . ($cents % 100 === 0 ? number_format($cents / 100, 0, '.', ',') : number_format($cents / 100, 2, '.', ','));
    return $billing === 'monthly' ? $p . ' / month' : $p;
}

/** Die gerade eingeblendete Einblendung eines Raums (Gathering „g:ID“ oder „test“), oder null. */
/** Ratenplan eines Angebots für die Anzeige (Preis bestimmt beim Bezahlen immer der Server). */
function mm_inst_public(string $offer, bool $live): ?array
{
    $o = mm_offers()[$offer] ?? null;
    if ($o && $o['periods']) return array_map(fn($p) => ['plan' => 'p' . $p[0], 'count' => $p[0], 'regular' => mm_price_en($p[1], 'once'), 'special' => null, 'label' => mm_period_label($p)], $o['periods']);
    if (!$o || !$o['plans']) return null;
    return array_map(fn($p) => ['plan' => 'inst' . $p[0], 'count' => $p[0], 'regular' => mm_price_en($p[1], 'once'),
        'special' => $live && $p[2] > 0 && $p[2] < $p[1] ? mm_price_en($p[2], 'once') : null], $o['plans']);
}

/** „for your first 3 months, then €39 / month“ beim Inner Circle (Monats-Abo mit Vorteilspreis). */
function mm_intro_note(string $offer, int $regular, string $billing): ?string
{
    if ($billing !== 'monthly') return null;
    $o = mm_offers()[$offer] ?? null;
    return $o ? 'for your first ' . $o['intro_months'] . ' months, then ' . mm_price_en($regular, 'monthly') : null;
}

/** Stripe-Gutschein „X € weniger für N Monate“, einmal angelegt und gemerkt. */
function mm_coupon(int $amountOff, int $months): string
{
    $cfg = mm_cfg('coupons') ?? [];
    $key = $amountOff . 'x' . $months . (forma_stripe_test_mode() ? 't' : 'l');
    if (!empty($cfg[$key])) return (string)$cfg[$key];
    $c = forma_stripe('POST', '/v1/coupons', ['amount_off' => $amountOff, 'currency' => 'eur', 'duration' => 'repeating', 'duration_in_months' => $months, 'name' => 'Wild Hearts · ' . $months . ' months']);
    $cfg[$key] = (string)$c['id'];
    mm_save_cfg('coupons', $cfg);
    return (string)$c['id'];
}

/** Bestellung zu einem Einladungscode (nur bezahlte Inner-Circle-Mitgliedschaften). */
function mm_ref_order(PDO $pdo, string $code): ?array
{
    if ($code === '' || !preg_match('/^[a-z0-9]{6,16}$/', $code)) return null;
    mm_offer_schema($pdo);
    $s = $pdo->prepare("SELECT * FROM mm_orders WHERE ref_code = ? AND status = 'bezahlt' LIMIT 1");
    $s->execute([$code]);
    return $s->fetch() ?: null;
}

/** Ratenplan zu einem Plan-Kürzel wie „inst3“, oder null. */
/** Zeitpunkt der ersten Abbuchung, solange der Inner Circle noch nicht gestartet ist (sonst 0). Stripe braucht mind. 48 Std. Vorlauf. */
function mm_circle_start(string $offer = 'circle'): int
{
    $o = mm_offers()[$offer] ?? null;
    if (!$o || $o['start'] === '') return 0;
    $t = (new DateTimeImmutable($o['start'] . ' 09:00', new DateTimeZone('Europe/Vienna')))->getTimestamp();
    return $t > time() + 50 * 3600 ? $t : 0;
}

/** „Opens on 1 December 2026 · book now, your first payment is taken then“ (nur vor dem Start). */
function mm_start_note(string $offer): ?string
{
    $t = mm_circle_start($offer);
    return $t ? 'Opens on ' . mm_start_en($t) . ' · book now, nothing is charged before then' : null;
}

/** „1 December 2026“ */
function mm_start_en(int $t): string
{
    return (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('j F Y');
}

/** „or €312 per year (€26 / month)“ */
function mm_period_label(array $p): string
{
    $per = mm_price_en((int)round($p[1] / $p[0]), 'monthly');
    return 'or ' . mm_price_en($p[1], 'once') . ($p[0] === 12 ? ' per year' : ' every ' . $p[0] . ' months') . ' (' . $per . ')';
}

function mm_period_pick(string $offer, string $plan): ?array
{
    foreach (mm_offers()[$offer]['periods'] ?? [] as $p) if ('p' . $p[0] === $plan) return $p;
    return null;
}

function mm_plan_pick(string $offer, string $plan): ?array
{
    foreach (mm_offers()[$offer]['plans'] ?? [] as $p) if ('inst' . $p[0] === $plan) return $p;
    return null;
}

function mm_spotlight(PDO $pdo, string $scope): ?array
{
    mm_offer_schema($pdo);
    $s = $pdo->prepare('SELECT * FROM mm_spotlights WHERE scope = ? AND active = 1 ORDER BY id DESC LIMIT 1');
    $s->execute([$scope]);
    $r = $s->fetch() ?: null;
    // Im Testraum bleibt ein altes Angebot nicht ewig stehen: nach 3 Stunden ist es weg
    if ($r && $scope === 'test' && (int)$r['shown_at'] < time() - 3 * 3600) { mm_spotlight_hide($pdo, $scope); return null; }
    return $r;
}

/** Was die Teilnehmerinnen sehen. Der Gathering-Preis gilt nur bis „until“, danach der reguläre Preis. */
function mm_spotlight_public(?array $sp): ?array
{
    if (!$sp) return null;
    if ($sp['offer'] === 'all') {
        $items = [];
        $open = time() <= (int)$sp['until'];
        foreach (json_decode((string)$sp['items'], true) ?: [] as $it) {
            $live = $open && (int)$it['special_cents'] > 0 && (int)$it['special_cents'] < (int)$it['regular_cents'];
            $items[] = ['offer' => $it['offer'], 'title' => $it['title'], 'text' => $it['text'], 'billing' => $it['billing'],
                'regular' => mm_price_en((int)$it['regular_cents'], $it['billing']), 'special' => $live ? mm_price_en((int)$it['special_cents'], $it['billing']) : null, 'live' => $live,
                'inst' => mm_inst_public($it['offer'], $live), 'note' => $live ? mm_intro_note($it['offer'], (int)$it['regular_cents'], $it['billing']) : null, 'start' => mm_start_note($it['offer'])];
        }
        $any = (bool)array_filter($items, fn($i) => $i['live']);
        return ['id' => (int)$sp['id'], 'offer' => 'all', 'title' => 'Ways to walk with me', 'items' => $items, 'until' => (int)$sp['until'], 'now' => time(), 'live' => $any];
    }
    $live = time() <= (int)$sp['until'] && (int)$sp['special_cents'] > 0 && (int)$sp['special_cents'] < (int)$sp['regular_cents'];
    return [
        'id' => (int)$sp['id'], 'offer' => $sp['offer'], 'title' => $sp['title'], 'text' => $sp['text'], 'billing' => $sp['billing'],
        'regular' => mm_price_en((int)$sp['regular_cents'], $sp['billing']),
        'special' => $live ? mm_price_en((int)$sp['special_cents'], $sp['billing']) : null,
        'until' => (int)$sp['until'], 'now' => time(), 'live' => $live,
        'inst' => mm_inst_public($sp['offer'], $live),
        'note' => $live ? mm_intro_note($sp['offer'], (int)$sp['regular_cents'], $sp['billing']) : null,
        'start' => mm_start_note($sp['offer']),
    ];
}

function mm_spotlight_show(PDO $pdo, string $scope, string $offer, int $special, int $minutes, int $regular = 0): array
{
    mm_offer_schema($pdo);
    if ($offer === 'all') {
        $items = [];
        foreach (mm_offers() as $k => $x) {
            if ($x['price_cents'] <= 0) continue;
            $items[] = ['offer' => $k, 'title' => $x['title'], 'text' => $x['text'], 'billing' => $x['billing'], 'regular_cents' => $x['price_cents'], 'special_cents' => $x['special_cents'] < $x['price_cents'] ? $x['special_cents'] : 0];
        }
        if (!$items) throw new RuntimeException('Blende zuerst die einzelnen Angebote mit ihren Preisen ein, dann kennt die Übersicht alle Preise.');
        $minutes = max(1, min(180, $minutes));
        $pdo->prepare('UPDATE mm_spotlights SET active = 0 WHERE scope = ?')->execute([$scope]);
        $pdo->prepare('INSERT INTO mm_spotlights (scope, offer, title, text, billing, regular_cents, special_cents, shown_at, until, items) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$scope, 'all', 'Ways to walk with me', '', 'once', 0, 0, time(), time() + $minutes * 60, json_encode($items, JSON_UNESCAPED_UNICODE)]);
        return mm_spotlight($pdo, $scope);
    }
    $o = mm_offers()[$offer] ?? null;
    if (!$o) throw new RuntimeException('Unbekanntes Angebot.');
    if ($regular > 0) $o['price_cents'] = $regular;
    if ($o['price_cents'] <= 0) throw new RuntimeException('Bitte gib zuerst den regulären Preis ein.');
    if ($special >= $o['price_cents']) throw new RuntimeException('Der Gathering-Preis muss unter dem regulären Preis liegen.');
    $minutes = max(1, min(180, $minutes));
    $pdo->prepare('UPDATE mm_spotlights SET active = 0 WHERE scope = ?')->execute([$scope]);
    $pdo->prepare('INSERT INTO mm_spotlights (scope, offer, title, text, billing, regular_cents, special_cents, shown_at, until) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$scope, $offer, $o['title'], $o['text'], $o['billing'], $o['price_cents'], $special, time(), time() + $minutes * 60]);
    return mm_spotlight($pdo, $scope);
}

function mm_spotlight_hide(PDO $pdo, string $scope): void
{
    mm_offer_schema($pdo);
    $pdo->prepare('UPDATE mm_spotlights SET active = 0 WHERE scope = ?')->execute([$scope]);
}

/** Legt eine Bestellung an und gibt die Stripe-Bezahlseite zurück. Preis wird hier am Server bestimmt. */
function mm_order_checkout(PDO $pdo, array $sp, string $name, string $email, string $item = '', string $plan = 'full', int $consent = 0, int $digital = 0): string
{
    mm_offer_schema($pdo);
    if ($sp['offer'] === 'all') {
        $pick = null;
        foreach (json_decode((string)$sp['items'], true) ?: [] as $it) if ($it['offer'] === $item) $pick = $it;
        if (!$pick) throw new RuntimeException('Unknown offer.');
        $sp = array_merge($sp, ['offer' => $pick['offer'], 'title' => $pick['title'], 'text' => $pick['text'], 'billing' => $pick['billing'], 'regular_cents' => $pick['regular_cents'], 'special_cents' => $pick['special_cents'], 'items' => '']);
    }
    $pub = mm_spotlight_public($sp);
    $special = $pub['live'];
    $amount = $special ? (int)$sp['special_cents'] : (int)$sp['regular_cents'];
    // Ratenzahlung: Rate am Server aus den Einstellungen, als Monats-Abo, das nach der letzten Rate endet
    $off = mm_offers()[$sp['offer']] ?? null;
    $pp = $off ? mm_plan_pick($sp['offer'], $plan) : null;
    $inst = $pp !== null;
    if ($inst) {
        $amount = $special && $pp[2] > 0 && $pp[2] < $pp[1] ? $pp[2] : $pp[1];
        $sp['billing'] = 'monthly';
    }
    // Inner Circle im Voraus (6 Monate / 1 Jahr): fester Preis, kein Gathering-Rabatt, keine Empfehlung
    $per = (!$inst && $off) ? mm_period_pick($sp['offer'], $plan) : null;
    if ($per) { $amount = $per[1]; $special = false; $sp['billing'] = 'monthly'; $sp['referred_by'] = 0; unset($sp['force_intro']); }
    // Inner Circle (Monats-Abo): immer der reguläre Preis, der Vorteilspreis kommt als Gutschein für die ersten Monate
    $coupon = '';
    $refBy = (int)($sp['referred_by'] ?? 0);
    if ($sp['billing'] === 'monthly' && !$inst && !$per && $off && ($special || $refBy || !empty($sp['force_intro'])) && (int)$sp['special_cents'] > 0 && (int)$sp['special_cents'] < (int)$sp['regular_cents']) {
        $months = (int)($sp['force_intro'] ?? 0) ?: $off['intro_months'];
        // Vor dem Start: der Gutschein läuft ab der Buchung, soll aber genau die ersten N Abbuchungen ab dem Start treffen
        $st0 = mm_circle_start($sp['offer']);
        if ($st0) { $need = strtotime('+' . ($months - 1) . ' months', $st0); $m = $months; while (strtotime('+' . $m . ' months') <= $need && $m < 24) $m++; $months = $m; }
        $coupon = mm_coupon((int)$sp['regular_cents'] - (int)$sp['special_cents'], $months);
        $amount = (int)$sp['special_cents'];
        $special = true;
    }
    $tok = bin2hex(random_bytes(16));
    $test = $sp['scope'] === 'test' ? 1 : 0;
    $pdo->prepare('INSERT INTO mm_orders (spotlight_id, scope, offer, title, billing, name, email, amount_cents, special, token, test, created_at, plan, consent, consent_digital) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([(int)$sp['id'], $sp['scope'], $sp['offer'], $sp['title'], $sp['billing'], mb_substr($name, 0, 80), mb_substr($email, 0, 160), $amount, $special ? 1 : 0, $tok, $test, date('c'), $inst ? 'inst' . $pp[0] : ($per ? 'p' . $per[0] : 'full'), $consent ? 1 : 0, $digital ? 1 : 0]);
    $oid = (int)$pdo->lastInsertId();
    if ($refBy) $pdo->prepare('UPDATE mm_orders SET referred_by = ? WHERE id = ?')->execute([$refBy, $oid]);
    $site = mm_site();
    $price = ['currency' => 'eur', 'unit_amount' => $coupon ? (int)$sp['regular_cents'] : $amount, 'product_data' => ['name' => $sp['title'] . ($inst ? ' · ' . $pp[0] . ' monthly payments' : '') . ($per ? ($per[0] === 12 ? ' · yearly' : ' · every 6 months') : '') . ($special && !$coupon ? ' · Gathering price' : ''), 'description' => 'Wild Hearts Collective · Movecia, Vivien Malonga']];
    if ($sp['billing'] === 'monthly') $price['recurring'] = $per ? ($per[0] === 12 ? ['interval' => 'year'] : ['interval' => 'month', 'interval_count' => $per[0]]) : ['interval' => 'month'];
    $params = [
        'mode' => $sp['billing'] === 'monthly' ? 'subscription' : 'payment',
        'locale' => 'en',
        'billing_address_collection' => 'required',
        'client_reference_id' => 'wh-order-' . $oid,
        'metadata' => ['wh_order_id' => (string)$oid, 'plan' => $inst ? 'inst' . $pp[0] : ($per ? 'p' . $per[0] : 'full')],
        'custom_text' => ['submit' => ['message' => 'By booking you accept the terms and cancellation policy: ' . $site . '/terms' . ($sp['offer'] !== 'retreat' && $consent ? ' · You asked for your access to start right away.' : '')]],
        'line_items' => [['quantity' => 1, 'price_data' => $price]],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/wh-order.php?o=' . $oid . '&k=' . $tok,
        'cancel_url' => $site . '/api/wh-order.php?o=' . $oid . '&k=' . $tok . '&cancel=1',
    ];
    if ($email !== '') $params['customer_email'] = $email;
    // Gutscheincode-Feld im Bezahlfenster (geht bei Stripe nur, wenn nicht schon ein Rabatt drin ist)
    // Inner Circle vor dem Start: jetzt buchen, Karte hinterlegen, erste Abbuchung am Starttag
    $start = ($params['mode'] === 'subscription' && !$inst) ? mm_circle_start($sp['offer']) : 0;
    if ($start) {
        $params['subscription_data'] = ['trial_end' => $start, 'metadata' => ['wh_order_id' => (string)$oid]];
        $params['payment_method_collection'] = 'always';
        $params['custom_text']['submit']['message'] = 'Nothing is charged today. The Inner Circle opens on ' . mm_start_en($start) . ', your first payment is taken then. ' . $params['custom_text']['submit']['message'];
        $pdo->prepare('UPDATE mm_orders SET bill_start = ? WHERE id = ?')->execute([$start, $oid]);
    }
    if ($coupon) $params['discounts'] = [['coupon' => $coupon]];
    else $params['allow_promotion_codes'] = 'true';
    $session = forma_stripe('POST', '/v1/checkout/sessions', $params);
    $pdo->prepare('UPDATE mm_orders SET stripe_session = ? WHERE id = ?')->execute([(string)$session['id'], $oid]);
    return (string)$session['url'];
}

/** Prüft eine Bestellung bei Stripe. Bei Zahlung: Danke-Mail an die Käuferin, Info an Viv (genau einmal). */
function mm_order_check(PDO $pdo, array $o): string
{
    if ($o['status'] !== 'offen' || $o['stripe_session'] === '') return (string)$o['status'];
    $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($o['stripe_session']));
    if (($s['payment_status'] ?? '') !== 'paid' && ($s['status'] ?? '') !== 'complete') {
        if (($s['status'] ?? '') === 'expired') { $pdo->prepare("UPDATE mm_orders SET status = 'abgebrochen' WHERE id = ?")->execute([$o['id']]); return 'abgebrochen'; }
        return 'offen';
    }
    $email = (string)($s['customer_details']['email'] ?? $o['email']);
    $name = (string)($s['customer_details']['name'] ?? $o['name']);
    if (str_starts_with((string)($o['plan'] ?? 'full'), 'inst') && !empty($s['subscription']) && ($o['sub_id'] ?? '') === '') {
        $cnt = max(2, (int)substr((string)$o['plan'], 4));
        try {
            // endet kurz nach der Fälligkeit der letzten Rate, dann wird nichts mehr abgebucht
            forma_stripe('POST', '/v1/subscriptions/' . rawurlencode((string)$s['subscription']), ['cancel_at' => strtotime('+' . ($cnt - 1) . ' months +10 days')]);
        } catch (Throwable $e) { error_log('MM inst cancel_at: ' . $e->getMessage()); }
        $pdo->prepare('UPDATE mm_orders SET sub_id = ? WHERE id = ?')->execute([(string)$s['subscription'], $o['id']]);
    }
    if ($o['billing'] === 'monthly' && (string)($o['plan'] ?? 'full') === 'full' && ($o['ref_code'] ?? '') === '') {
        $off = mm_offers()[$o['offer']] ?? null;
        $pdo->prepare('UPDATE mm_orders SET ref_code = ?, sub_id = CASE WHEN sub_id = \'\' THEN ? ELSE sub_id END, special_months = ? WHERE id = ?')
            ->execute([substr(bin2hex(random_bytes(8)), 0, 10), (string)($s['subscription'] ?? ''), $o['special'] ? ($off['intro_months'] ?? 3) : 0, $o['id']]);
    }
    if (str_starts_with((string)($o['plan'] ?? ''), 'p') && !empty($s['subscription']) && ($o['sub_id'] ?? '') === '') $pdo->prepare('UPDATE mm_orders SET sub_id = ? WHERE id = ?')->execute([(string)$s['subscription'], $o['id']]);
    $u = $pdo->prepare("UPDATE mm_orders SET status = 'bezahlt', paid_at = ?, email = ?, name = CASE WHEN name = '' THEN ? ELSE name END WHERE id = ? AND status = 'offen'");
    $u->execute([date('c'), $email, $name, $o['id']]);
    if ($u->rowCount() === 1) {
        $first = forma_first_name($name ?: 'love');
        $cnt = max(2, (int)substr((string)($o['plan'] ?? 'inst0'), 4));
        $pl = (string)($o['plan'] ?? 'full');
        $price = str_starts_with($pl, 'inst') ? $cnt . ' monthly payments of ' . mm_price_en((int)$o['amount_cents'], 'once')
            : ($pl === 'p12' ? mm_price_en((int)$o['amount_cents'], 'once') . ' per year' : ($pl === 'p6' ? mm_price_en((int)$o['amount_cents'], 'once') . ' every 6 months' : mm_price_en((int)$o['amount_cents'], $o['billing'])));
        $site = mm_site();
        $legal = $o['offer'] === 'retreat'
            ? "Retreats take place on fixed dates, so there is no statutory right of withdrawal (§ 18 (1) 10 FAGG). Our cancellation policy applies: $site/terms"
            : ((int)($o['bill_start'] ?? 0) > time() ? 'You have a 14-day right of withdrawal from today. Nothing is charged before the opening day, so you can withdraw free of charge. Terms, cancellation policy and the withdrawal form: ' . $site . '/terms' : '')
            . ((int)($o['bill_start'] ?? 0) > time() ? '' : "You have a 14-day right of withdrawal for the live parts (gatherings, circles, mentoring). " . (($o['consent'] ?? 0) ? 'You asked for them to start right away, so if you withdraw within 14 days you pay only for the part already used. ' : '')
              . (($o['consent_digital'] ?? 0)
                  ? 'Confirmation: you expressly agreed that the course area and downloads (digital content) are unlocked immediately, and you acknowledged that you thereby lose your right of withdrawal for this digital content (§ 18 (1) 11 FAGG). '
                  : 'You did not choose immediate access to the course area: you start with the starter section, and the full course area and downloads unlock 14 days after your booking. ')
              . "Terms, cancellation policy and the withdrawal form: $site/terms");
        $startNote = (int)($o['bill_start'] ?? 0) > time() ? 'The Inner Circle opens on ' . mm_start_en((int)$o['bill_start']) . '. Nothing has been charged yet, your first payment is taken on that day.' : '';
        $t = "Dear $first,\n\nthank you so much, you are in! Your booking for {$o['title']} ($price) is confirmed." . ($startNote ? "\n\n$startNote" : '') . "\n\nI will write to you personally in the next days with everything you need for the start. If you have any questions, simply reply to this email.\n\nI am so happy to walk this way with you.\n\n$legal\nWithdraw from the contract: $site/withdraw";
        $html = '<p style="margin:0 0 6px;font-family:\'Brush Script MT\',cursive;font-size:24px;color:#7D5E68;">you are in ♡</p><h1 style="font-family:Georgia,serif;font-weight:400;font-size:30px;color:#6B5658;margin:0 0 14px;">' . forma_h($o['title']) . '</h1>'
            . '<p>Dear ' . forma_h($first) . ',</p><p>thank you so much, your booking for <strong>' . forma_h($o['title']) . '</strong> (' . forma_h($price) . ') is confirmed.</p>' . ($startNote ? '<p>' . forma_h($startNote) . '</p>' : '') . '<p>I will write to you personally in the next days with everything you need for the start. If you have any questions, simply reply to this email.</p><p>I am so happy to walk this way with you.</p>'
            . '<p style="font-size:13px;color:#7D7076;border-top:1px solid #DDD2C6;padding-top:12px;margin-top:22px;">' . forma_h($legal) . '<br><a href="' . forma_h($site . '/withdraw') . '" style="color:#7D5E68;">Withdraw from the contract</a></p>';
        if ($email !== '') mm_mail($email, ($o['test'] ? '[TEST] ' : '') . 'You are in: ' . $o['title'], $t, mm_mail_html('You are in', $html));
        $v = "Hallo Viv,\n\n" . ($name ?: 'Jemand') . " ($email) hat gerade {$o['title']} gebucht" . ((int)($o['bill_start'] ?? 0) > time() ? ' (Abbuchung ab ' . date('d.m.Y', (int)$o['bill_start']) . ')' : ' und bezahlt') . ": $price" . ($o['special'] ? ' (Gathering-Preis)' : ' (regulärer Preis)') . ".\n" . ($o['test'] ? "\nDas war eine Testbuchung aus dem Testraum.\n" : '') . "\nDu siehst alle Buchungen in der Verwaltung unter Movecia Movement → Angebote.\n";
        mm_mail(MM_FROM_EMAIL, ($o['test'] ? '[TEST] ' : '') . 'Neue Buchung: ' . $o['title'] . ' · ' . ($name ?: $email), $v, mm_mail_html('Neue Buchung', nl2br(forma_h($v)), 'de'));
        if ((int)($o['referred_by'] ?? 0)) { try { mm_ref_reward($pdo, (int)$o['referred_by'], $name ?: 'a friend'); } catch (Throwable $e) { error_log('MM ref reward: ' . $e->getMessage()); } }
    }
    return 'bezahlt';
}

// --- Fragen im Live-Gathering: anonym von Teilnehmerinnen oder offen von Viv ----------------

function mm_q_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        scope TEXT NOT NULL,
        text TEXT NOT NULL,
        name TEXT NOT NULL DEFAULT \'\',
        source TEXT NOT NULL DEFAULT \'anon\',
        asker TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'neu\',
        created_at TEXT NOT NULL,
        shown_at TEXT
    )');
}

/** Ehrliche Beschriftung, die alle sehen: anonym, mit Namen (nur wenn die Frau das will) oder von Viv. */
function mm_q_label(array $q): string
{
    if ($q['source'] === 'viv') return 'A question I often get';
    if ($q['source'] === 'named' && $q['name'] !== '') return 'A question from ' . forma_first_name($q['name']);
    return 'An anonymous question from the circle';
}

function mm_q_current(PDO $pdo, string $scope): ?array
{
    mm_q_schema($pdo);
    $s = $pdo->prepare("SELECT * FROM mm_questions WHERE scope = ? AND status = 'gezeigt' ORDER BY shown_at DESC LIMIT 1");
    $s->execute([$scope]);
    $q = $s->fetch();
    return $q ? ['id' => (int)$q['id'], 'label' => mm_q_label($q), 'text' => $q['text']] : null;
}


// --- Gathering-Vorlagen (ein Klick füllt Titel, Dauer, Text und Teleprompter-Skript) -----------

/** Alle Teleprompter-Skripte: Vorlagen aus wildhearts/gathering/skripte.inc.php plus eigene aus der Bibliothek. */
function mm_script_titles(): array
{
    $p = mm_cfg('prompter') ?? [];
    $out = [];
    foreach ((require dirname(__DIR__) . '/wildhearts/gathering/skripte.inc.php') as $row) if (!in_array($row[0], $p['hidden'] ?? [], true)) $out[] = $row[0];
    foreach (array_keys($p['library'] ?? []) as $t) if (!in_array((string)$t, $out, true)) $out[] = (string)$t;
    return $out;
}

function mm_gathering_presets(): array
{
    return [
        ['label' => 'Morgen · 15 Min', 'title' => 'Sunday Morning Gathering · Breathwork', 'minutes' => 15, 'time' => '08:00', 'script' => 'Morning · 15 min · Breathwork',
         'text' => "Start your Sunday with 15 quiet minutes just for you. We breathe together, gently and slowly, and set one word for the day. Bring a cup of tea, come in your pyjamas, camera on or off."],
        ['label' => 'Morgen · 30 Min', 'title' => 'Morning Gathering · Breathwork & Intention', 'minutes' => 30, 'time' => '08:00', 'script' => 'Morning · 30 min · Breathwork & intention',
         'text' => "Thirty minutes to begin your day softly: calming breathwork, a humming breath, gentle movement and an intention for the day. No experience needed. Have a pen and paper ready."],
        ['label' => 'Abend · 15 Min', 'title' => 'Evening Gathering · Smoke & Release', 'minutes' => 15, 'time' => '19:00', 'script' => 'Evening · 15 min · Smoke & release ritual',
         'text' => "Close your day with a small cleansing ritual. Bring a candle, or herbs or incense if you like, and we let go of the day together. Fifteen minutes, then off to rest."],
        ['label' => 'Abend · 30 Min', 'title' => 'Evening Gathering · Ritual & Deep Rest', 'minutes' => 30, 'time' => '19:30', 'script' => 'Evening · 30 min · Ritual & deep rest',
         'text' => "Thirty minutes to end your day: a candle ritual to clear the day, gentle stretches and a deep guided rest. Have a blanket, a pillow and a pen ready. You can fall asleep right after."],
        ['label' => 'Samhain · 20 Min', 'title' => 'Samhain Gathering · The Old New Year', 'minutes' => 20, 'time' => '19:00', 'date' => '2026-10-31', 'script' => 'Samhain · 20 min · Free gathering',
         'text' => "On the 31st of October we celebrate Samhain, the old New Year in the Wheel of the Year. A free 20-minute circle by candlelight: we honour the women before us, let go of what is finished and choose a seed to carry through winter. Bring a candle, a pen and paper."],
    ];
}


// --- Empfehlungen im Inner Circle -----------------------------------------------------------

/** Die Einladende bekommt weitere Monate zum Vorteilspreis, höchstens bis zur Obergrenze. */
function mm_ref_reward(PDO $pdo, int $refId, string $friend): void
{
    $s = $pdo->prepare("SELECT * FROM mm_orders WHERE id = ? AND status = 'bezahlt'");
    $s->execute([$refId]);
    $r = $s->fetch();
    if (!$r) return;
    $o = mm_offers()[$r['offer']] ?? null;
    if (!$o) return;
    $used = (int)$r['special_months'];
    $add = min($o['ref_months'], max(0, $o['max_months'] - $used));
    $first = forma_first_name((string)$r['name'] ?: 'love');
    $friend = forma_first_name($friend);
    $pdo->prepare('UPDATE mm_orders SET ref_bonus = ref_bonus + 1 WHERE id = ?')->execute([$refId]);
    if ($add > 0 && $r['sub_id'] !== '') {
        $sub = forma_stripe('GET', '/v1/subscriptions/' . rawurlencode((string)$r['sub_id']));
        $left = 0;
        $end = (int)($sub['discount']['end'] ?? 0);
        if ($end > time()) $left = (int)round(($end - time()) / (30.44 * 86400));
        $coupon = mm_coupon((int)$o['price_cents'] - (int)$o['special_cents'], $left + $add);
        forma_stripe('POST', '/v1/subscriptions/' . rawurlencode((string)$r['sub_id']), ['discounts' => [['coupon' => $coupon]]]);
        $pdo->prepare('UPDATE mm_orders SET special_months = special_months + ? WHERE id = ?')->execute([$add, $refId]);
        $price = mm_price_en((int)$o['special_cents'], 'monthly');
        $t = "Dear $first,\n\n$friend just joined The Inner Circle with your invitation. Thank you so much for sharing our circle! 🤍\n\nAs a thank you, you keep your price of $price for $add more months.\n\nWith love\nViv";
    } else {
        $t = "Dear $first,\n\n$friend just joined The Inner Circle with your invitation. Thank you so much for sharing our circle! 🤍\n\nYou have already received the full thank-you of " . $o['max_months'] . " months at the circle price, but your friend still gets her first months at the circle price thanks to you.\n\nWith love\nViv";
    }
    if ($r['email'] !== '') mm_mail((string)$r['email'], ($r['test'] ? '[TEST] ' : '') . $friend . ' joined thanks to you', $t, mm_mail_html('Thank you', nl2br(forma_h($t))));
}

/** Zu Beginn des 3. Monats: Mail mit dem persönlichen Einladungslink (läuft bei Seitenaufrufen mit). */
function mm_send_ref_mails(PDO $pdo): void
{
    mm_offer_schema($pdo);
    $chk = mm_cfg('refmail') ?? [];
    if (time() - (int)($chk['at'] ?? 0) < 900) return;
    mm_save_cfg('refmail', ['at' => time()]);
    $q = $pdo->prepare("SELECT * FROM mm_orders WHERE status = 'bezahlt' AND billing = 'monthly' AND ref_code <> '' AND ref_mail_sent = 0 AND paid_at <= ? AND bill_start <= ? LIMIT 20");
    $q->execute([date('c', strtotime('-2 months')), strtotime('-2 months')]);
    foreach ($q->fetchAll() as $r) {
        $pdo->prepare('UPDATE mm_orders SET ref_mail_sent = 1 WHERE id = ?')->execute([$r['id']]);
        if ($r['sub_id'] !== '' && forma_stripe_key()) {
            try { $sub = forma_stripe('GET', '/v1/subscriptions/' . rawurlencode((string)$r['sub_id'])); if (!in_array($sub['status'] ?? '', ['active', 'trialing', 'past_due'], true)) continue; }
            catch (Throwable $e) { error_log('MM ref mail sub: ' . $e->getMessage()); }
        }
        $o = mm_offers()[$r['offer']] ?? null;
        if (!$o || $r['email'] === '') continue;
        $first = forma_first_name((string)$r['name'] ?: 'love');
        $link = mm_site() . '/circle/?ref=' . $r['ref_code'];
        $sp = mm_price_en((int)$o['special_cents'], 'monthly');
        $more = ((int)$r['special_months'] < $o['max_months']) ? "and you get {$o['ref_months']} more months at $sp yourself" : 'as a little gift from you';
        $t = "Dear $first,\n\nyou have been part of The Inner Circle for two months now, and I am so grateful you are here.\n\nIs there a friend who would love this circle too? Here is your personal invitation link:\n$link\n\nWith your link, your friend gets her first {$o['intro_months']} months at $sp, $more.\n\nWith love\nViv";
        $html = '<p>Dear ' . forma_h($first) . ',</p><p>you have been part of The Inner Circle for two months now, and I am so grateful you are here.</p><p>Is there a friend who would love this circle too? Share your personal invitation link with her:</p>'
            . mm_button($link, 'Your invitation link') . '<p style="font-size:14px;word-break:break-all;color:#7D7076;">' . forma_h($link) . '</p>'
            . '<p>With your link, your friend gets her first ' . (int)$o['intro_months'] . ' months at ' . forma_h($sp) . ', ' . forma_h($more) . '.</p>';
        mm_mail((string)$r['email'], ($r['test'] ? '[TEST] ' : '') . 'Invite a friend to The Inner Circle', $t, mm_mail_html('Invite a friend', $html));
    }
}

// --- Einmalige Einrichtung durch die Website selbst (läuft beim ersten Seitenaufruf nach dem Update) ---

/**
 * Legt Dinge an, die Viv nicht selbst anlegen möchte:
 * - das Samhain-Gathering am 31.10.2026 aus der Vorlage (mit Skript und Videoraum), genau einmal;
 * Wird ein Eintrag später gelöscht, legt die Website ihn nicht wieder an.
 */
function mm_auto_setup(PDO $pdo): void
{
    $st = mm_cfg('setup') ?? [];
    $changed = false;
    // Samhain-Gathering
    if (empty($st['samhain']) && time() < strtotime('2026-11-01')) {
        $pr = null;
        foreach (mm_gathering_presets() as $x) if (($x['date'] ?? '') === '2026-10-31') $pr = $x;
        if ($pr) {
            $q = $pdo->prepare("SELECT id FROM mm_gatherings WHERE starts_at LIKE '2026-10-31%' LIMIT 1");
            $q->execute();
            $gid = (int)($q->fetchColumn() ?: 0);
            if (!$gid) {
                $pdo->prepare('INSERT INTO mm_gatherings (title, starts_at, minutes, description, script, created_at) VALUES (?,?,?,?,?,?)')
                    ->execute([$pr['title'], $pr['date'] . 'T' . $pr['time'], $pr['minutes'], $pr['text'], $pr['script'], date('c')]);
                $gid = (int)$pdo->lastInsertId();
            }
            $st['samhain'] = $gid;
            $changed = true;
        }
    }
    // Videoraum fürs Samhain-Gathering nachholen, falls er fehlt (höchstens stündlich versuchen)
    if (!empty($st['samhain']) && mm_whereby_key() !== '' && time() - (int)($st['room_try'] ?? 0) > 3600) {
        $g = mm_gathering($pdo, (int)$st['samhain']);
        if ($g && $g['room_url'] === '' && strtotime($g['starts_at']) > time() - 3600) {
            $st['room_try'] = time();
            $changed = true;
            try { mm_whereby_room($pdo, (int)$g['id']); } catch (Throwable $e) { error_log('MM samhain room: ' . $e->getMessage()); }
        }
    }
    if ($changed) mm_save_cfg('setup', $st);
}

/**
 * 100-%-Gutscheincode für Vivs eigene Testbuchungen. Wird nur aus der Verwaltung (nach dem Login) angelegt,
 * zufällig erzeugt, nirgends öffentlich und nicht per Mail. Höchstens 3 Mal, 30 Tage gültig.
 */
function mm_test_promo(): ?array
{
    $st = mm_cfg('setup') ?? [];
    $changed = false;
    if (empty($st['testpromo']) && forma_stripe_key() && time() - (int)($st['promo_try'] ?? 0) > 3600) {
        $st['promo_try'] = time();
        $changed = true;
        try {
            $until = time() + 30 * 86400;
            $c = forma_stripe('POST', '/v1/coupons', ['percent_off' => 100, 'duration' => 'forever', 'max_redemptions' => 3, 'redeem_by' => $until, 'name' => 'Testbuchung Viv 100 %', 'metadata' => ['wild_hearts' => 'testbuchung']]);
            $code = 'VIVTEST' . strtoupper(bin2hex(random_bytes(4)));
            $p = forma_stripe('POST', '/v1/promotion_codes', ['coupon' => (string)$c['id'], 'code' => $code, 'max_redemptions' => 3, 'expires_at' => $until]);
            $st['testpromo'] = ['code' => (string)($p['code'] ?? $code), 'id' => (string)($p['id'] ?? ''), 'coupon' => (string)$c['id'], 'until' => $until, 'live' => !forma_stripe_test_mode()];
        } catch (Throwable $e) { error_log('MM test promo: ' . $e->getMessage()); }
    }
    if ($changed) mm_save_cfg('setup', $st);
    return $st['testpromo'] ?? null;
}


// --- Letzte Chance: nach den Einführungsmonaten ohne Empfehlung noch einmal Monate zum Vorteilspreis ---

/**
 * Kurz bevor die Einführungsmonate enden (und die Frau niemanden eingeladen hat), kommt eine Mail mit
 * einem persönlichen Code: noch einmal ref_months Monate zum Vorteilspreis, direkt im Anschluss.
 * Der Code gilt nur bis 7 Tage nach Ende der Einführungsmonate, danach gilt der reguläre Preis.
 * Die Obergrenze max_months gilt auch hier. Läuft bei Seitenaufrufen mit (höchstens alle 15 Min.).
 */
function mm_send_stay_mails(PDO $pdo): void
{
    mm_offer_schema($pdo);
    if (!forma_stripe_key()) return;
    $chk = mm_cfg('staymail') ?? [];
    if (time() - (int)($chk['at'] ?? 0) < 900) return;
    mm_save_cfg('staymail', ['at' => time()]);
    $o = mm_offers()['circle'];
    $q = $pdo->prepare("SELECT * FROM mm_orders WHERE status = 'bezahlt' AND offer = 'circle' AND billing = 'monthly' AND plan = 'full' AND sub_id <> '' AND stay_code = '' AND ref_bonus = 0 AND special_months > 0 AND special_months < ? AND paid_at <= ? LIMIT 20");
    $q->execute([$o['max_months'], date('c', strtotime('-' . max(1, $o['intro_months'] - 1) . ' months'))]);
    foreach ($q->fetchAll() as $r) {
        try { $sub = forma_stripe('GET', '/v1/subscriptions/' . rawurlencode((string)$r['sub_id'])); } catch (Throwable $e) { error_log('MM stay sub: ' . $e->getMessage()); continue; }
        $end = (int)($sub['discount']['end'] ?? 0);
        if (!$end) $end = strtotime((string)$r['paid_at'] . ' +' . (int)$r['special_months'] . ' months');
        if ($end > time() + 7 * 86400) continue; // noch zu früh
        $code = 'STAY-' . strtoupper(bin2hex(random_bytes(4)));
        $until = max($end, time()) + 7 * 86400;
        $pdo->prepare('UPDATE mm_orders SET stay_code = ?, stay_until = ? WHERE id = ?')->execute([$code, $until, $r['id']]);
        if ($end < time() - 7 * 86400 || !in_array($sub['status'] ?? '', ['active', 'trialing', 'past_due', 'canceled'], true) || $r['email'] === '') continue;
        $first = forma_first_name((string)$r['name'] ?: 'love');
        $months = min($o['ref_months'], $o['max_months'] - (int)$r['special_months']);
        $sp = mm_price_en((int)$o['special_cents'], 'monthly');
        $reg = mm_price_en((int)$o['price_cents'], 'monthly');
        $link = mm_site() . '/circle/?stay=' . $code;
        $ref = $r['ref_code'] !== '' ? mm_site() . '/circle/?ref=' . $r['ref_code'] : '';
        $date = date('j F Y', $until);
        $t = "Dear $first,\n\nI really hope you have enjoyed your first months in The Inner Circle. It means so much to me that you are part of it.\n\nYour circle price is coming to an end now. If you would like to stay, here is a little gift for you: $months more months at $sp, right after your first ones.\n\nYour personal code: $code\nKeep your circle price: $link\n\nThe code is only valid if you use it straight away, until $date. After that, the regular price of $reg applies.\n"
            . ($ref ? "\nAnd if a friend would love the circle too, your invitation link still works: $ref\nShe gets her first {$o['intro_months']} months at $sp.\n" : '') . "\nWith love\nViv";
        $html = '<p>Dear ' . forma_h($first) . ',</p><p>I really hope you have enjoyed your first months in The Inner Circle. It means so much to me that you are part of it.</p>'
            . '<p>Your circle price is coming to an end now. If you would like to stay, here is a little gift for you: <strong>' . $months . ' more months at ' . forma_h($sp) . '</strong>, right after your first ones.</p>'
            . '<p style="font-size:22px;letter-spacing:.08em;color:#6B5658;margin:18px 0 6px;">' . forma_h($code) . '</p>'
            . mm_button($link, 'Keep my circle price')
            . '<p style="font-size:14px;color:#7D7076;">The code is only valid if you use it straight away, until ' . forma_h($date) . '. After that, the regular price of ' . forma_h($reg) . ' applies.</p>'
            . ($ref ? '<p>And if a friend would love the circle too, your invitation link still works. She gets her first ' . (int)$o['intro_months'] . ' months at ' . forma_h($sp) . ':</p>' . mm_button($ref, 'Invite a friend') : '');
        mm_mail((string)$r['email'], ($r['test'] ? '[TEST] ' : '') . 'A little gift, if you would like to stay', $t, mm_mail_html('Stay with us', $html));
    }
}

/** Bestellung zu einem gültigen Bleibe-Code. */
function mm_stay_order(PDO $pdo, string $code): ?array
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^STAY-[0-9A-F]{8}$/', $code)) return null;
    mm_offer_schema($pdo);
    $s = $pdo->prepare("SELECT * FROM mm_orders WHERE stay_code = ? AND status = 'bezahlt' AND stay_used = 0 AND stay_until >= ? LIMIT 1");
    $s->execute([$code, time()]);
    return $s->fetch() ?: null;
}

/**
 * Löst den Bleibe-Code ein. Läuft das Abo noch (auch gekündigt zum Monatsende), bekommt es den Rabatt
 * für die nächsten Monate und läuft weiter. Ist es schon beendet, entsteht ein neuer Checkout mit dem Rabatt.
 * Gibt ['done' => true] oder ['redirect' => URL] zurück.
 */
function mm_stay_redeem(PDO $pdo, array $r, int $consent, int $digital): array
{
    $o = mm_offers()['circle'];
    $months = max(1, min($o['ref_months'], $o['max_months'] - (int)$r['special_months']));
    $sub = $r['sub_id'] !== '' ? forma_stripe('GET', '/v1/subscriptions/' . rawurlencode((string)$r['sub_id'])) : [];
    if (in_array($sub['status'] ?? '', ['active', 'trialing', 'past_due'], true)) {
        $left = 0;
        $end = (int)($sub['discount']['end'] ?? 0);
        if ($end > time()) $left = (int)round(($end - time()) / (30.44 * 86400));
        $coupon = mm_coupon((int)$o['price_cents'] - (int)$o['special_cents'], $left + $months);
        forma_stripe('POST', '/v1/subscriptions/' . rawurlencode((string)$r['sub_id']), ['discounts' => [['coupon' => $coupon]], 'cancel_at_period_end' => 'false']);
        $pdo->prepare('UPDATE mm_orders SET stay_used = 1, special_months = special_months + ? WHERE id = ?')->execute([$months, $r['id']]);
        $first = forma_first_name((string)$r['name'] ?: 'love');
        $t = "Dear $first,\n\nhow lovely that you are staying! Your circle price of " . mm_price_en((int)$o['special_cents'], 'monthly') . " continues for $months more months, then the regular price of " . mm_price_en((int)$o['price_cents'], 'monthly') . " applies. You can still cancel at any time, effective at the end of the month you have paid.\n\nWith love\nViv";
        mm_mail((string)$r['email'], ($r['test'] ? '[TEST] ' : '') . 'You are staying in The Inner Circle', $t, mm_mail_html('You are staying', nl2br(forma_h($t))));
        mm_mail(MM_FROM_EMAIL, 'Bleibt im Inner Circle: ' . ($r['name'] ?: $r['email']), "Hallo Viv,\n\n" . ($r['name'] ?: 'Jemand') . " ({$r['email']}) hat ihren Bleibe-Code eingelöst und zahlt $months weitere Monate den Gathering-Preis.\n", mm_mail_html('Bleibt im Inner Circle', nl2br(forma_h(($r['name'] ?: 'Jemand') . " ({$r['email']}) hat ihren Bleibe-Code eingelöst und zahlt $months weitere Monate den Gathering-Preis.")), 'de'));
        return ['done' => true];
    }
    // Abo schon beendet: neue Mitgliedschaft mit dem Rabatt für die Monate
    $pdo->prepare('UPDATE mm_orders SET stay_used = 1 WHERE id = ?')->execute([$r['id']]);
    $sp = ['id' => 0, 'scope' => 'circle', 'offer' => 'circle', 'title' => $o['title'], 'text' => $o['text'], 'billing' => 'monthly',
        'regular_cents' => $o['price_cents'], 'special_cents' => $o['special_cents'], 'until' => 0, 'items' => '', 'force_intro' => $months];
    $url = mm_order_checkout($pdo, $sp, (string)$r['name'], (string)$r['email'], '', 'full', $consent, $digital);
    return ['redirect' => $url];
}

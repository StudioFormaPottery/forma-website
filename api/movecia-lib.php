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
        . '<body style="margin:0;padding:0;background:#EEEADF;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEEADF;"><tr><td align="center" style="padding:26px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#F6F3EA;border-radius:24px;font-family:Helvetica,Arial,sans-serif;color:#2F2B22;">'
        . '<tr><td style="padding:30px 30px 26px;font-size:16px;line-height:1.65;">' . $html
        . '<p style="margin:22px 0 0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:24px;color:#5E6B35;">' . ($lang === 'de' ? 'alles Liebe, Viv' : 'with love, Viv') . '</p></td></tr></table>'
        . '<p style="max-width:560px;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#6B5D4C;margin:16px auto 0;">Vivien Malonga · Movecia – Personal Fitness · Silvrettastraße 58 · 6562 Mathon · Austria · ' . MM_FROM_EMAIL . '</p>'
        . '</td></tr></table></body></html>';
}

function mm_button(string $href, string $label): string
{
    return '<p style="margin:22px 0 4px;text-align:center;"><a href="' . forma_h($href) . '" style="display:inline-block;padding:15px 30px;border-radius:999px;background:#5E6B35;color:#F6F3EA;text-decoration:none;font-weight:bold;">' . forma_h($label) . '</a></p>';
}

/** Verschickt eine Mail als Viv. Mit hinterlegtem Postfach-Passwort per SMTP, sonst per mail(). */
function mm_mail(string $to, string $subject, string $text, string $html): bool
{
    $b = 'mm' . bin2hex(random_bytes(10));
    $enc = fn(string $s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
    $mime = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($text) . "\r\n"
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($html) . "\r\n--$b--";
    $ctype = 'multipart/alternative; boundary="' . $b . '"';
    $from = mb_encode_mimeheader(MM_FROM_NAME, 'UTF-8') . ' <' . MM_FROM_EMAIL . '>';
    if (getenv('FORMA_MAIL_LOG')) {
        file_put_contents(getenv('FORMA_MAIL_LOG'), "FROM: " . MM_FROM_EMAIL . "\nTO: $to\nSUBJECT: $subject\n$text\n-----\n", FILE_APPEND);
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
    $html = '<p style="margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#5E6B35;">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">you\'re in! I\'ve saved your spot for our Wild Hearts Gathering.</p>'
        . '<p style="margin:16px 0 0;padding:16px 18px;background:#DCE0D2;border-radius:16px;"><strong>' . forma_h($g['title']) . '</strong><br>' . forma_h($when) . '</p>'
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
    $html = '<p style="margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#5E6B35;">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">' . forma_h($intro) . '</p>'
        . '<p style="margin:16px 0 0;padding:16px 18px;background:#DCE0D2;border-radius:16px;"><strong>' . forma_h($g['title']) . '</strong><br>' . forma_h(mm_when($g)) . '</p>'
        . mm_button($link, 'Open my room') . '<p style="margin:10px 0 0;font-size:14px;color:#6B5D4C;text-align:center;">The room opens 15 minutes before we start.</p>';
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
    $hand = 'margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#5E6B35;';
    if ($de) {
        $text = "Hey $first,\n\nnur noch ein Klick, dann bist du dabei. Bitte bestätige hier deine E-Mail-Adresse:\n$link\n\nGleich danach wartet deine Reset-Routine für müde Tage auf dich, auf Deutsch und Englisch.\n\nFalls du dich nicht angemeldet hast, ignoriere diese Mail einfach. Dann hörst du nichts mehr von mir.\n\nAlles Liebe, Viv";
        $html = '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
            . '<p style="margin:14px 0 0;">nur noch ein Klick, dann bist du dabei. Bitte bestätige deine E-Mail-Adresse. Gleich danach wartet deine <strong>Reset-Routine für müde Tage</strong> auf dich, auf Deutsch und Englisch.</p>'
            . mm_button($link, 'Ja, ich bestätige meine E-Mail')
            . '<p style="margin:16px 0 0;font-size:14px;color:#6B5D4C;">Falls du dich nicht angemeldet hast, ignoriere diese Mail einfach. Dann hörst du nichts mehr von mir.</p>';
        return mm_mail($sub['email'], 'Ein Klick, und du bist dabei 🌙', $text, mm_mail_html('E-Mail bestätigen', $html, 'de'));
    }
    $text = "Hey $first,\n\none click and you're in. Please confirm your email address here:\n$link\n\nRight after that your Reset Routine for Tired Days is ready for you, in English and Deutsch.\n\nIf you didn't sign up, simply ignore this email and you won't hear from me again.\n\nWith love, Viv";
    $html = '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">one click and you\'re in. Please confirm your email address, and right after that your <strong>Reset Routine for Tired Days</strong> is ready for you, in English and Deutsch.</p>'
        . mm_button($link, 'Yes, confirm my email')
        . '<p style="margin:16px 0 0;font-size:14px;color:#6B5D4C;">If you didn\'t sign up, simply ignore this email and you won\'t hear from me again.</p>';
    return mm_mail($sub['email'], "One click and you're in 🌙", $text, mm_mail_html('Confirm your email', $html));
}

function mm_mail_welcome(array $sub): bool
{
    $de = ($sub['lang'] ?? 'en') === 'de';
    $first = forma_first_name($sub['name']);
    $dl = mm_site() . '/reset/';
    $out = mm_site() . '/api/wh-unsubscribe.php?t=' . $sub['token'];
    $hand = 'margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#5E6B35;';
    $img = '<img src="' . mm_site() . '/img/wildhearts/cover.jpg" width="500" alt="" style="display:block;width:100%;max-width:500px;height:auto;max-height:360px;object-fit:cover;border-radius:16px;margin:0 0 20px;">';
    if ($de) {
        $text = "Hey $first,\n\ndu hast es geschafft, du bist dabei. Willkommen im Wild Hearts Collective.\n\nHier ist mein kleines Geschenk für dich: die Reset-Routine für müde Tage. Zehn stille Minuten für die Abende, an denen du alles gegeben hast.\n\nDownload (Deutsch oder Englisch): $dl\n\nDas Wild Hearts Collective gibt es, damit du nie vergisst, dass auch du ein ganzer Mensch bist, selbst wenn du immer für alle anderen da bist. Wir sind wichtig. Wir sind da. Und wir werden gesehen.\n\nKomm in unseren kostenlosen Community-Kanal auf Instagram: " . MM_CHANNEL . "\nDort schreiben wir auf Englisch, aber du bist von Herzen willkommen. Unser erstes Live-Gathering findet im Dezember statt.\n\nAlles Liebe, Viv\n\nAbmelden: $out";
        $html = $img . '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
            . '<p style="margin:14px 0 0;">du hast es geschafft, du bist dabei. Willkommen im Wild Hearts Collective.</p>'
            . '<p style="margin:14px 0 0;">Hier ist mein kleines Geschenk für dich: die <strong>Reset-Routine für müde Tage</strong>. Zehn stille Minuten für die Abende, an denen du alles gegeben hast. Ein bewusster Atemzug, ein kleines Ritual zum Loslassen, eine warme Tasse Goldene Mondmilch und ein paar ehrliche Zeilen, nur für dich.</p>'
            . mm_button($dl, 'Zu meiner Reset-Routine')
            . '<p style="margin:8px 0 0;font-size:14px;color:#6B5D4C;text-align:center;">Auf der Seite wählst du Deutsch oder Englisch.</p>'
            . '<p style="margin:20px 0 0;">Das Wild Hearts Collective gibt es, damit du nie vergisst, dass auch du ein ganzer Mensch bist, selbst wenn du immer für alle anderen da bist. Wir sind wichtig. Wir sind da. Und wir werden gesehen.</p>'
            . '<p style="margin:14px 0 0;">Komm und sag Hallo in unserem kostenlosen <a href="' . MM_CHANNEL . '" style="color:#5E6B35;">Community-Kanal auf Instagram</a>. Dort schreiben wir auf Englisch, aber du bist von Herzen willkommen. Unser erstes Live-Gathering findet im Dezember statt.</p>'
            . '<p style="margin:22px 0 0;font-size:12px;color:#6B5D4C;">Du bekommst diese E-Mail, weil du dich auf moveciamovement.com angemeldet hast. <a href="' . forma_h($out) . '" style="color:#6B5D4C;">Abmelden</a></p>';
        return mm_mail($sub['email'], 'Deine Reset-Routine für müde Tage 🌙', $text, mm_mail_html('Deine Reset-Routine', $html, 'de'));
    }
    $text = "Hey $first,\n\nyou did it, you're in. Welcome to the Wild Hearts Collective.\n\nHere is my little gift for you: the Reset Routine for Tired Days. Ten quiet minutes for the evenings you gave everything away.\n\nDownload (English or Deutsch): $dl\n\nThe Wild Hearts Collective exists so you never forget that you are a whole person too, even when you're always there for everyone else. We matter. We are here. And we are seen.\n\nCome and say hi in our free community channel on Instagram: " . MM_CHANNEL . "\nOur first live Wild Hearts Gathering is in December.\n\nWith love, Viv\n\nUnsubscribe: $out";
    $html = $img . '<p style="' . $hand . '">Hey ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">you did it, you\'re in. Welcome to the Wild Hearts Collective.</p>'
        . '<p style="margin:14px 0 0;">Here is my little gift for you: the <strong>Reset Routine for Tired Days</strong>. Ten quiet minutes for the evenings you gave everything away. A breath, a small ritual to let go, a warm cup of Golden Moon Milk and a few honest lines, just for you.</p>'
        . mm_button($dl, 'Get my Reset Routine')
        . '<p style="margin:8px 0 0;font-size:14px;color:#6B5D4C;text-align:center;">English or Deutsch, you choose on the page.</p>'
        . '<p style="margin:20px 0 0;">The Wild Hearts Collective exists so you never forget that you are a whole person too, even when you\'re always there for everyone else. We matter. We are here. And we are seen.</p>'
        . '<p style="margin:14px 0 0;">Come and say hi in our free <a href="' . MM_CHANNEL . '" style="color:#5E6B35;">community channel on Instagram</a>. Our first live Wild Hearts Gathering is in December.</p>'
        . '<p style="margin:22px 0 0;font-size:12px;color:#6B5D4C;">You get this email because you signed up on moveciamovement.com. <a href="' . forma_h($out) . '" style="color:#6B5D4C;">Unsubscribe</a></p>';
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
        . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F6F3EA;color:#2F2B22;font:17px/1.6 Outfit,system-ui,sans-serif;text-align:center;padding:24px 16px}main{max-width:520px}'
        . '.hi{font-family:Hand,cursive;color:#5E6B35;font-size:22px;transform:rotate(-2deg)}h1{font-family:Italiana,Georgia,serif;font-weight:400;font-size:44px;line-height:1.05;color:#3F4A26;margin:8px 0 12px}p{color:#6B5D4C}'
        . 'a.b{display:inline-block;margin-top:18px;padding:14px 26px;border-radius:999px;background:#5E6B35;color:#F6F3EA;text-decoration:none}</style></head><body><main>'
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
}

/** Die drei Angebote mit Viv's Einstellungen (Titel, Text auf Englisch, regulärer Preis, Zahlungsart). */
function mm_offers(): array
{
    $def = [
        'circle' => ['title' => 'The Inner Circle', 'billing' => 'monthly', 'text' => 'Your monthly circle: live gatherings, breathwork, gentle movement and a members area with rituals and affirmations. A space where women support each other and come back to themselves.'],
        'year' => ['title' => 'The Year Circle', 'billing' => 'once', 'text' => 'Twelve months together: The Inner Circle plus 1:1 mentoring for the idea that is close to your heart. Like a best friend who walks the whole year with you.'],
        'retreat' => ['title' => 'Wild Hearts Alpine Retreat', 'billing' => 'once', 'text' => 'A weekend in the Austrian Alps: yoga, Pilates, forest walks, breathwork, pottery, cooking together and a women\'s circle. Lots of quiet, lots of nature, back to you.'],
    ];
    $cfg = mm_cfg('offers') ?? [];
    $out = [];
    foreach (MM_OFFER_KEYS as $k) {
        $c = is_array($cfg[$k] ?? null) ? $cfg[$k] : [];
        $out[$k] = [
            'title' => (string)($c['title'] ?? $def[$k]['title']),
            'text' => (string)($c['text'] ?? $def[$k]['text']),
            'billing' => in_array($c['billing'] ?? '', ['once', 'monthly'], true) ? $c['billing'] : $def[$k]['billing'],
            'price_cents' => max(0, (int)($c['price_cents'] ?? 0)),
            'special_cents' => max(0, (int)($c['special_cents'] ?? 0)),
            'minutes' => max(5, min(180, (int)($c['minutes'] ?? 25))),
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
function mm_spotlight(PDO $pdo, string $scope): ?array
{
    mm_offer_schema($pdo);
    $s = $pdo->prepare('SELECT * FROM mm_spotlights WHERE scope = ? AND active = 1 ORDER BY id DESC LIMIT 1');
    $s->execute([$scope]);
    return $s->fetch() ?: null;
}

/** Was die Teilnehmerinnen sehen. Der Gathering-Preis gilt nur bis „until“, danach der reguläre Preis. */
function mm_spotlight_public(?array $sp): ?array
{
    if (!$sp) return null;
    $live = time() <= (int)$sp['until'] && (int)$sp['special_cents'] > 0 && (int)$sp['special_cents'] < (int)$sp['regular_cents'];
    return [
        'id' => (int)$sp['id'], 'offer' => $sp['offer'], 'title' => $sp['title'], 'text' => $sp['text'], 'billing' => $sp['billing'],
        'regular' => mm_price_en((int)$sp['regular_cents'], $sp['billing']),
        'special' => $live ? mm_price_en((int)$sp['special_cents'], $sp['billing']) : null,
        'until' => (int)$sp['until'], 'now' => time(), 'live' => $live,
    ];
}

function mm_spotlight_show(PDO $pdo, string $scope, string $offer, int $special, int $minutes): array
{
    mm_offer_schema($pdo);
    $o = mm_offers()[$offer] ?? null;
    if (!$o) throw new RuntimeException('Unbekanntes Angebot.');
    if ($o['price_cents'] <= 0) throw new RuntimeException('Für „' . $o['title'] . '“ ist noch kein regulärer Preis eingetragen (Verwaltung → Movecia → Angebote).');
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
function mm_order_checkout(PDO $pdo, array $sp, string $name, string $email): string
{
    mm_offer_schema($pdo);
    $pub = mm_spotlight_public($sp);
    $special = $pub['live'];
    $amount = $special ? (int)$sp['special_cents'] : (int)$sp['regular_cents'];
    $tok = bin2hex(random_bytes(16));
    $test = $sp['scope'] === 'test' ? 1 : 0;
    $pdo->prepare('INSERT INTO mm_orders (spotlight_id, scope, offer, title, billing, name, email, amount_cents, special, token, test, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([(int)$sp['id'], $sp['scope'], $sp['offer'], $sp['title'], $sp['billing'], mb_substr($name, 0, 80), mb_substr($email, 0, 160), $amount, $special ? 1 : 0, $tok, $test, date('c')]);
    $oid = (int)$pdo->lastInsertId();
    $site = mm_site();
    $price = ['currency' => 'eur', 'unit_amount' => $amount, 'product_data' => ['name' => $sp['title'] . ($special ? ' · Gathering price' : ''), 'description' => 'Wild Hearts Collective · Movecia, Vivien Malonga']];
    if ($sp['billing'] === 'monthly') $price['recurring'] = ['interval' => 'month'];
    $params = [
        'mode' => $sp['billing'] === 'monthly' ? 'subscription' : 'payment',
        'locale' => 'en',
        'billing_address_collection' => 'required',
        'client_reference_id' => 'wh-order-' . $oid,
        'metadata' => ['wh_order_id' => (string)$oid],
        'line_items' => [['quantity' => 1, 'price_data' => $price]],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/wh-order.php?o=' . $oid . '&k=' . $tok,
        'cancel_url' => $site . '/api/wh-order.php?o=' . $oid . '&k=' . $tok . '&cancel=1',
    ];
    if ($email !== '') $params['customer_email'] = $email;
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
    $u = $pdo->prepare("UPDATE mm_orders SET status = 'bezahlt', paid_at = ?, email = ?, name = CASE WHEN name = '' THEN ? ELSE name END WHERE id = ? AND status = 'offen'");
    $u->execute([date('c'), $email, $name, $o['id']]);
    if ($u->rowCount() === 1) {
        $first = forma_first_name($name ?: 'love');
        $price = mm_price_en((int)$o['amount_cents'], $o['billing']);
        $t = "Dear $first,\n\nthank you so much, you are in! Your booking for {$o['title']} ($price) is confirmed.\n\nI will write to you personally in the next days with everything you need for the start. If you have any questions, simply reply to this email.\n\nI am so happy to walk this way with you.";
        $html = '<p style="margin:0 0 6px;font-family:\'Brush Script MT\',cursive;font-size:24px;color:#5E6B35;">you are in ♡</p><h1 style="font-family:Georgia,serif;font-weight:400;font-size:30px;color:#3F4A26;margin:0 0 14px;">' . forma_h($o['title']) . '</h1>'
            . '<p>Dear ' . forma_h($first) . ',</p><p>thank you so much, your booking for <strong>' . forma_h($o['title']) . '</strong> (' . forma_h($price) . ') is confirmed.</p><p>I will write to you personally in the next days with everything you need for the start. If you have any questions, simply reply to this email.</p><p>I am so happy to walk this way with you.</p>';
        if ($email !== '') mm_mail($email, ($o['test'] ? '[TEST] ' : '') . 'You are in: ' . $o['title'], $t, mm_mail_html('You are in', $html));
        $v = "Hallo Viv,\n\n" . ($name ?: 'Jemand') . " ($email) hat gerade {$o['title']} gebucht und bezahlt: $price" . ($o['special'] ? ' (Gathering-Preis)' : ' (regulärer Preis)') . ".\n" . ($o['test'] ? "\nDas war eine Testbuchung aus dem Testraum.\n" : '') . "\nDu siehst alle Buchungen in der Verwaltung unter Movecia Movement → Angebote.\n";
        mm_mail(MM_FROM_EMAIL, ($o['test'] ? '[TEST] ' : '') . 'Neue Buchung: ' . $o['title'] . ' · ' . ($name ?: $email), $v, mm_mail_html('Neue Buchung', nl2br(forma_h($v)), 'de'));
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

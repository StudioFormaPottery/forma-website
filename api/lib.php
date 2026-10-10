<?php
// Gemeinsame Funktionen für das FORMA Buchungssystem.
// Daten liegen außerhalb von public_html, damit sie bei einem neuen Deployment
// erhalten bleiben und nicht über das Web abrufbar sind.

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'lib.php') {
    http_response_code(404);
    exit;
}

const FORMA_NOTIFY_EMAIL = 'hello@forma-pottery.com';
const FORMA_FROM_EMAIL   = 'hello@forma-pottery.com';
const FORMA_FROM_NAME    = 'FORMA Earth & Art';
const FORMA_SITE_URL     = 'https://www.forma-pottery.com';
const FORMA_PHONE        = '+43 677 63 90 70 80';
const FORMA_ADDRESS      = 'FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon';

date_default_timezone_set('Europe/Vienna');

function forma_data_dir(): string
{
    $env = getenv('FORMA_DATA_DIR');
    if ($env) {
        $dir = $env;
    } else {
        // .../domains/forma-pottery.com/public_html/api -> .../domains/forma-pottery.com/forma-data
        $dir = dirname(__DIR__, 2) . '/forma-data';
    }
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Datenordner kann nicht angelegt werden: ' . $dir);
        }
    }
    return $dir;
}

function forma_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . forma_data_dir() . '/buchungen.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    forma_migrate($pdo);
    return $pdo;
}

function forma_migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        date_text TEXT NOT NULL,
        start_date TEXT NOT NULL,
        capacity INTEGER NOT NULL DEFAULT 1,
        min_participants INTEGER NOT NULL DEFAULT 1,
        price_text TEXT NOT NULL DEFAULT \'\',
        description TEXT NOT NULL DEFAULT \'\',
        visible INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT \'\',
        persons INTEGER NOT NULL DEFAULT 1,
        message TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'bestaetigt\',
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');

    // Startinhalt beim allerersten Aufruf: der laufende Pottery-Pause-Kurs.
    if (forma_setting($pdo, 'seeded') === null) {
        $stmt = $pdo->prepare('INSERT INTO courses (title, date_text, start_date, capacity, min_participants, price_text, description, visible, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
        $stmt->execute([
            'Pottery Pause · 5-Wochen-Kurs',
            'Freitags, 6. 11. – 4. 12. 2026, jeweils 18–20 Uhr',
            '2026-11-06',
            1,
            1,
            '€ 295 für alle fünf Termine',
            'Fünf Freitagabende an der Töpferscheibe, 8 eigene Keramikstücke. Für Einsteiger und Fortgeschrittene.',
            date('c'),
        ]);
        forma_set_setting($pdo, 'seeded', '1');
    }
}

function forma_setting(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (string)$v;
}

function forma_set_setting(PDO $pdo, string $key, ?string $value): void
{
    if ($value === null) {
        $pdo->prepare('DELETE FROM settings WHERE key = ?')->execute([$key]);
        return;
    }
    $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

function forma_booked(PDO $pdo, int $courseId): int
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0) FROM bookings WHERE course_id = ? AND status = 'bestaetigt'");
    $stmt->execute([$courseId]);
    return (int)$stmt->fetchColumn();
}

function forma_upcoming_courses(PDO $pdo, bool $onlyVisible = true): array
{
    $sql = 'SELECT * FROM courses WHERE start_date >= ?' . ($onlyVisible ? ' AND visible = 1' : '') . ' ORDER BY start_date, id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([date('Y-m-d')]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['booked'] = forma_booked($pdo, (int)$r['id']);
        $r['free'] = max(0, (int)$r['capacity'] - $r['booked']);
    }
    return $rows;
}

function forma_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function forma_clean(string $s, int $max): string
{
    $s = trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $s) ?? '');
    return mb_substr($s, 0, $max);
}

function forma_mail_config(): ?array
{
    $file = forma_data_dir() . '/mail.json';
    if (!is_file($file)) {
        return null;
    }
    $cfg = json_decode((string)file_get_contents($file), true);
    return (is_array($cfg) && !empty($cfg['password'])) ? $cfg : null;
}

function forma_save_mail_password(?string $password): void
{
    $file = forma_data_dir() . '/mail.json';
    if ($password === null || $password === '') {
        @unlink($file);
        return;
    }
    file_put_contents($file, json_encode(['password' => $password]));
    @chmod($file, 0600);
}

function forma_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    if (getenv('FORMA_MAIL_LOG')) {
        // Testbetrieb: Mails zusätzlich in eine Datei schreiben.
        file_put_contents(getenv('FORMA_MAIL_LOG'), "TO: $to\nSUBJECT: $subject\n$body\n-----\n", FILE_APPEND);
        if (!getenv('FORMA_SMTP_HOST')) {
            return true;
        }
    }
    $cfg = forma_mail_config();
    if ($cfg) {
        try {
            forma_smtp_send($cfg['password'], $to, $subject, $body, $replyTo);
            return true;
        } catch (Throwable $e) {
            error_log('FORMA SMTP: ' . $e->getMessage());
            // Fällt unten auf den einfachen Versand zurück.
        }
    }
    $headers = [
        'From' => mb_encode_mimeheader(FORMA_FROM_NAME, 'UTF-8') . ' <' . FORMA_FROM_EMAIL . '>',
        'Reply-To' => $replyTo ?: FORMA_FROM_EMAIL,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    return @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers, '-f' . FORMA_FROM_EMAIL);
}

/**
 * Versand über das Postfach hello@forma-pottery.com bei Hostinger (SMTP mit Anmeldung).
 * So werden die Mails mit der Domain signiert und landen nicht im Spam.
 */
function forma_smtp_send(string $password, string $to, string $subject, string $body, ?string $replyTo): void
{
    $host = getenv('FORMA_SMTP_HOST') ?: 'ssl://smtp.hostinger.com:465';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($host, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("Verbindung fehlgeschlagen: $errstr");
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $cmd = function (?string $line, array $ok) use ($fp, $read): string {
        if ($line !== null) {
            fwrite($fp, $line . "\r\n");
        }
        $resp = $read();
        if (!in_array((int)substr($resp, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP-Antwort: ' . trim($resp));
        }
        return $resp;
    };
    try {
        $cmd(null, [220]);
        $cmd('EHLO forma-pottery.com', [250]);
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(FORMA_FROM_EMAIL), [334]);
        $cmd(base64_encode($password), [235]);
        $cmd('MAIL FROM:<' . FORMA_FROM_EMAIL . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $headers = [
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@forma-pottery.com>',
            'From: ' . mb_encode_mimeheader(FORMA_FROM_NAME, 'UTF-8') . ' <' . FORMA_FROM_EMAIL . '>',
            'To: <' . $to . '>',
            'Reply-To: ' . ($replyTo ?: FORMA_FROM_EMAIL),
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $data = implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n")) . "\r\n.";
        $cmd($data, [250]);
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

function forma_h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

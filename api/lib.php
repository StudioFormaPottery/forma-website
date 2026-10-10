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

function forma_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $headers = [
        'From' => mb_encode_mimeheader(FORMA_FROM_NAME, 'UTF-8') . ' <' . FORMA_FROM_EMAIL . '>',
        'Reply-To' => $replyTo ?: FORMA_FROM_EMAIL,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    if (getenv('FORMA_MAIL_LOG')) {
        // Testbetrieb: Mails in eine Datei schreiben statt verschicken.
        file_put_contents(getenv('FORMA_MAIL_LOG'), "TO: $to\nSUBJECT: $subject\n$body\n-----\n", FILE_APPEND);
        return true;
    }
    return @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers, '-f' . FORMA_FROM_EMAIL);
}

function forma_h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

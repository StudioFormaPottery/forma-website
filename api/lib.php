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
const FORMA_HOLD_SECONDS = 2400;

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

    $pdo->exec('CREATE TABLE IF NOT EXISTS subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL DEFAULT \'\',
        source TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'offen\',
        token TEXT NOT NULL,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        confirmed_at TEXT
    )');
    forma_add_column($pdo, 'courses', 'price_cents', 'INTEGER NOT NULL DEFAULT 0');
    forma_add_column($pdo, 'bookings', 'amount_cents', 'INTEGER NOT NULL DEFAULT 0');
    forma_add_column($pdo, 'bookings', 'stripe_session', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'bookings', 'pay_token', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'bookings', 'paid_at', 'TEXT');
    forma_add_column($pdo, 'bookings', 'mailed', 'INTEGER NOT NULL DEFAULT 0');

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

    // Oktober 2026: feste Wochentermine (Spin & Go Mi + Fr, offenes Atelier Do) bis Jahresende,
    // Online-Preis für Pottery Pause und ein Entwurf für das Advents-Pop-up.
    if (forma_setting($pdo, 'seeded_v2') === null) {
        $pdo->exec("UPDATE courses SET price_cents = 29500 WHERE title LIKE 'Pottery Pause%' AND price_cents = 0");
        $from = date('Y-m-d', strtotime('+1 day'));
        forma_create_series($pdo, FORMA_SERIES['spin'], [3, 5], $from, '2026-12-31');
        forma_create_series($pdo, FORMA_SERIES['atelier'], [4], $from, '2026-12-31');
        if (forma_setting($pdo, 'popup') === null) {
            forma_set_setting($pdo, 'popup', json_encode([
                'active' => false,
                'eyebrow' => 'Special im Advent',
                'title' => 'Pottery bemalen im Advent',
                'text' => "Gestalte deine Weihnachtsgeschenke dieses Jahr selbst: Tassen, Schalen und kleine Teller bemalen, ganz entspannt bei Kerzenlicht im Studio.\nDie Termine kommen bald. Trag dich ein, dann erfährst du als Erste davon.",
                'date_line' => 'Ab Ende November im FORMA Studio',
                'start' => date('Y-m-d'),
                'end' => '2026-12-20',
                'signup' => true,
                'button_text' => '',
                'button_link' => '',
                'image' => '',
                'version' => 1,
            ], JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'seeded_v2', '1');
    }

    // 10.10.2026: Vivien möchte das Advents-Pop-up sofort einschalten.
    if (forma_setting($pdo, 'popup_on_v3') === null) {
        $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
        if (is_array($p)) {
            $p['active'] = true;
            $p['start'] = '2026-10-10';
            $p['end'] = $p['end'] ?: '2026-12-20';
            $p['version'] = (int)($p['version'] ?? 1) + 1;
            forma_set_setting($pdo, 'popup', json_encode($p, JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'popup_on_v3', '1');
    }
}

/** Vorlagen für Serientermine. */
const FORMA_SERIES = [
    'spin' => [
        'title' => 'Spin & Go',
        'from' => '9', 'to' => '11',
        'capacity' => 4, 'min' => 2,
        'price_text' => '€ 70 pro Person, inkl. eines Werkstücks',
        'price_cents' => 7000,
        'description' => 'Zwei Stunden an der Töpferscheibe, begleitet von Anfang an. Für Einsteiger und Fortgeschrittene.',
    ],
    'atelier' => [
        'title' => 'Offenes Atelier',
        'from' => '8', 'to' => '11',
        'capacity' => 4, 'min' => 1,
        'price_text' => '€ 30 pro Stunde, Brennen & Glasieren € 22/kg, Bezahlung vor Ort',
        'price_cents' => 0,
        'description' => 'Selbstständig an eigenen Projekten arbeiten. Kein betreuter Kurs, für alle mit Vorerfahrung.',
    ],
];

const FORMA_WEEKDAYS = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

function forma_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    if (!in_array($column, $cols, true)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

/** „Mi, 14. 10. 2026 · 9–11 Uhr“ */
function forma_date_text(string $ymd, string $from, string $to): string
{
    $t = strtotime($ymd);
    return FORMA_WEEKDAYS[(int)date('N', $t)] . ', ' . date('j. n. Y', $t) . ' · ' . $from . '–' . $to . ' Uhr';
}

/**
 * Legt für jeden gewählten Wochentag zwischen $from und $to einen Termin an.
 * Bereits vorhandene Termine (gleicher Name, gleicher Tag) werden übersprungen.
 * @param int[] $weekdays ISO-Wochentage, 1 = Montag
 */
function forma_create_series(PDO $pdo, array $tpl, array $weekdays, string $from, string $to): int
{
    $exists = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE title = ? AND start_date = ?');
    $insert = $pdo->prepare('INSERT INTO courses (title, date_text, start_date, capacity, min_participants, price_text, price_cents, description, visible, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $count = 0;
    $day = strtotime($from);
    $end = strtotime($to);
    for ($i = 0; $day <= $end && $i < 400; $i++, $day = strtotime('+1 day', $day)) {
        if (!in_array((int)date('N', $day), $weekdays, true)) {
            continue;
        }
        $ymd = date('Y-m-d', $day);
        $exists->execute([$tpl['title'], $ymd]);
        if ((int)$exists->fetchColumn() > 0) {
            continue;
        }
        $insert->execute([$tpl['title'], forma_date_text($ymd, (string)$tpl['from'], (string)$tpl['to']), $ymd,
            (int)$tpl['capacity'], (int)$tpl['min'], $tpl['price_text'], (int)$tpl['price_cents'], $tpl['description'], date('c')]);
        $count++;
    }
    return $count;
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
    // Offene Online-Zahlungen halten den Platz 40 Minuten lang frei.
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0) FROM bookings WHERE course_id = ?
        AND (status = 'bestaetigt' OR (status = 'offen' AND created_at >= ?))");
    $stmt->execute([$courseId, date('c', time() - FORMA_HOLD_SECONDS)]);
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

/** Basis-Adresse der Website (lokal zum Testen per Umgebungsvariable überschreibbar). */
function forma_site_url(): string
{
    return rtrim(getenv('FORMA_SITE_URL') ?: FORMA_SITE_URL, '/');
}

function forma_euro(int $cents): string
{
    return '€ ' . ($cents % 100 === 0 ? (string)intdiv($cents, 100) : number_format($cents / 100, 2, ',', '.'));
}

function forma_first_name(string $name): string
{
    return preg_split('/\s+/', trim($name))[0] ?? $name;
}

// --- Stripe -------------------------------------------------------------------

function forma_stripe_key(): ?string
{
    $file = forma_data_dir() . '/stripe.json';
    if (!is_file($file)) {
        return null;
    }
    $cfg = json_decode((string)file_get_contents($file), true);
    return (is_array($cfg) && !empty($cfg['secret_key'])) ? (string)$cfg['secret_key'] : null;
}

function forma_save_stripe_key(?string $key): void
{
    $file = forma_data_dir() . '/stripe.json';
    if ($key === null || $key === '') {
        @unlink($file);
        return;
    }
    file_put_contents($file, json_encode(['secret_key' => $key]));
    @chmod($file, 0600);
}

function forma_stripe_test_mode(?string $key = null): bool
{
    $key = $key ?? forma_stripe_key() ?? '';
    return str_contains($key, '_test_');
}

/** Aufruf der Stripe-API. Wirft eine Exception mit verständlicher Meldung, wenn etwas schiefgeht. */
function forma_stripe(string $method, string $path, array $params = [], ?string $key = null): array
{
    $key = $key ?? forma_stripe_key();
    if (!$key) {
        throw new RuntimeException('Stripe ist nicht eingerichtet.');
    }
    $base = rtrim(getenv('FORMA_STRIPE_API') ?: 'https://api.stripe.com', '/');
    $url = $base . $path;
    $body = http_build_query($params, '', '&');
    if ($method === 'GET' && $body !== '') {
        $url .= '?' . $body;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => $key . ':',
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Stripe-Version: 2024-06-20'],
    ]);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('Stripe nicht erreichbar: ' . $err);
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Unerwartete Antwort von Stripe.');
    }
    if ($status >= 400) {
        throw new RuntimeException('Stripe: ' . ($data['error']['message'] ?? ('HTTP ' . $status)));
    }
    return $data;
}

/**
 * Prüft eine offene Online-Buchung bei Stripe. Ist sie bezahlt, wird sie bestätigt und die Mails gehen raus.
 * Gibt den neuen Status zurück: bestaetigt, offen oder abgebrochen.
 */
function forma_check_payment(PDO $pdo, array $booking): string
{
    if ($booking['status'] !== 'offen' || $booking['stripe_session'] === '') {
        return (string)$booking['status'];
    }
    $session = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($booking['stripe_session']));
    if (($session['payment_status'] ?? '') === 'paid' || ($session['payment_status'] ?? '') === 'no_payment_required') {
        forma_confirm_paid($pdo, (int)$booking['id'], (int)($session['amount_total'] ?? $booking['amount_cents']));
        return 'bestaetigt';
    }
    if (($session['status'] ?? '') === 'expired') {
        $pdo->prepare("UPDATE bookings SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([(int)$booking['id']]);
        return 'abgebrochen';
    }
    return 'offen';
}

function forma_confirm_paid(PDO $pdo, int $bookingId, int $amountCents): void
{
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'bestaetigt', paid_at = ?, amount_cents = ? WHERE id = ? AND status IN ('offen', 'abgebrochen')");
    $stmt->execute([date('c'), $amountCents, $bookingId]);
    forma_send_booking_mails($pdo, $bookingId);
}

/** Gleicht offene Zahlungen mit Stripe ab (höchstens einmal pro Minute). */
function forma_reconcile_payments(PDO $pdo, bool $force = false): void
{
    if (!forma_stripe_key()) {
        return;
    }
    $last = (int)(forma_setting($pdo, 'reconciled_at') ?? 0);
    if (!$force && time() - $last < 60) {
        return;
    }
    forma_set_setting($pdo, 'reconciled_at', (string)time());
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE status = 'offen' AND stripe_session != '' AND created_at >= ?");
    $stmt->execute([date('c', time() - 3 * 86400)]);
    foreach ($stmt->fetchAll() as $b) {
        try {
            forma_check_payment($pdo, $b);
        } catch (Throwable $e) {
            error_log('FORMA reconcile: ' . $e->getMessage());
        }
    }
}

/** Bestätigung an die Kundin und Info an Vivien. Wird pro Buchung nur einmal verschickt. */
function forma_send_booking_mails(PDO $pdo, int $bookingId): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    $stmt = $pdo->prepare("SELECT b.*, c.title, c.date_text, c.price_text, c.min_participants, c.capacity
        FROM bookings b JOIN courses c ON c.id = b.course_id WHERE b.id = ? AND b.status = 'bestaetigt' AND b.mailed = 0");
    $stmt->execute([$bookingId]);
    $b = $stmt->fetch();
    if (!$b) {
        $pdo->exec('ROLLBACK');
        return;
    }
    $pdo->prepare('UPDATE bookings SET mailed = 1 WHERE id = ?')->execute([$bookingId]);
    $pdo->exec('COMMIT');

    $first = forma_first_name($b['name']);
    $persons = (int)$b['persons'];
    $personsText = $persons === 1 ? '1 Person' : "$persons Personen";
    $paid = (int)$b['amount_cents'] > 0 && $b['paid_at'];
    $free = max(0, (int)$b['capacity'] - forma_booked($pdo, (int)$b['course_id']));
    $phone = FORMA_PHONE;
    $site = forma_site_url();

    $payLine = $paid
        ? 'Bezahlt: ' . forma_euro((int)$b['amount_cents']) . ' (online, danke!)'
        : 'Preis: ' . $b['price_text'];
    $intro = $paid
        ? "danke für deine Buchung! Deine Zahlung ist angekommen und dein Platz für „{$b['title']}“ ist fix. Ich freue mich schon sehr auf dich."
        : "danke, dass du dich für „{$b['title']}“ angemeldet hast! Deine Anmeldung ist verbindlich bei mir eingegangen, und ich freue mich schon sehr auf dich.";
    $minNote = '';
    if ((int)$b['min_participants'] > 1) {
        $minNote = "\nDer Termin findet ab {$b['min_participants']} Personen statt. Falls es zu wenige Anmeldungen gibt, melde ich mich rechtzeitig bei dir"
            . ($paid ? ' und wir finden einen neuen Termin oder du bekommst dein Geld zurück.' : '.') . "\n";
    }

    $customer = "Hallo $first,\n\n$intro\n\nHier noch einmal alles auf einen Blick:\n\n"
        . "Kurs: {$b['title']}\nTermin: {$b['date_text']}\nPersonen: $personsText\n$payLine\nOrt: FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon\n"
        . $minNote
        . "\nBitte komm in Kleidung, die schmutzig werden darf. Schürzen und Werkzeug habe ich im Studio.\n"
        . "\nFalls doch etwas dazwischenkommt: Bis 48 Stunden vor Kursbeginn kannst du kostenlos stornieren. Danach werden 50 % der Kursgebühr fällig, wenn du ohne Absage nicht kommst, der volle Betrag. Melde dich aber bitte trotzdem jederzeit bei mir, gemeinsam finden wir bestimmt eine Lösung.\n"
        . "\nFalls du Fragen hast oder doch verhindert bist, antworte einfach auf diese E-Mail oder ruf mich an: $phone\n\nBis bald im Studio!\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n";

    $admin = ($paid ? 'Neue bezahlte Buchung über die Website' : 'Neue Kursanmeldung über die Website') . "\n\n"
        . "Kurs: {$b['title']}\nTermin: {$b['date_text']}\nPersonen: $personsText\n"
        . ($paid ? 'Bezahlt: ' . forma_euro((int)$b['amount_cents']) . " über Stripe\n" : "Bezahlung: vor Ort\n")
        . "\nName: {$b['name']}\nE-Mail: {$b['email']}\nTelefon: {$b['phone']}\nNachricht: {$b['message']}\n\n"
        . "Freie Plätze danach: $free\n\nAlle Buchungen: $site/verwaltung/\n";

    forma_mail($b['email'], ($paid ? 'Dein Platz ist fix: ' : 'Deine Anmeldung: ') . $b['title'], $customer);
    forma_mail(FORMA_NOTIFY_EMAIL, ($paid ? 'Bezahlt: ' : 'Neue Anmeldung: ') . $b['title'] . ' (' . $b['name'] . ')', $admin, $b['email']);
}

// --- MailerLite -------------------------------------------------------------------

function forma_mailerlite(): ?array
{
    $file = forma_data_dir() . '/mailerlite.json';
    $cfg = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return (is_array($cfg) && !empty($cfg['api_key'])) ? $cfg : null;
}

function forma_save_mailerlite(?array $cfg): void
{
    $file = forma_data_dir() . '/mailerlite.json';
    if ($cfg === null) {
        @unlink($file);
        return;
    }
    file_put_contents($file, json_encode($cfg));
    @chmod($file, 0600);
}

function forma_ml_request(string $method, string $path, ?array $body, string $key): array
{
    $base = rtrim(getenv('FORMA_MAILERLITE_API') ?: 'https://connect.mailerlite.com', '/');
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    if ($raw === false || $status >= 400) {
        throw new RuntimeException('MailerLite: ' . (is_array($data) ? ($data['message'] ?? 'HTTP ' . $status) : 'nicht erreichbar'));
    }
    return is_array($data) ? $data : [];
}

/** Sucht die Gruppe „Website“ in MailerLite oder legt sie an. Gibt die Gruppen-ID zurück. */
function forma_ml_group(string $key, string $name = 'Website'): string
{
    $list = forma_ml_request('GET', '/api/groups?limit=100&filter%5Bname%5D=' . rawurlencode($name), null, $key);
    foreach ($list['data'] ?? [] as $g) {
        if (($g['name'] ?? '') === $name) {
            return (string)$g['id'];
        }
    }
    $g = forma_ml_request('POST', '/api/groups', ['name' => $name], $key);
    return (string)($g['data']['id'] ?? '');
}

/** Überträgt eine bestätigte Adresse (oder eine Abmeldung) an MailerLite. Fehler werden nur protokolliert. */
function forma_ml_sync(string $email, string $name, bool $active = true): bool
{
    $cfg = forma_mailerlite();
    if (!$cfg) {
        return false;
    }
    $body = ['email' => $email, 'status' => $active ? 'active' : 'unsubscribed'];
    if ($active) {
        $body['groups'] = array_values(array_filter([(string)($cfg['group_id'] ?? '')]));
        if ($name !== '') {
            $body['fields'] = ['name' => $name];
        }
        $body['opted_in_at'] = date('Y-m-d H:i:s');
    }
    try {
        forma_ml_request('POST', '/api/subscribers', $body, $cfg['api_key']);
        return true;
    } catch (Throwable $e) {
        error_log('FORMA MailerLite: ' . $e->getMessage());
        return false;
    }
}

// --- Pop-up & E-Mail-Liste ------------------------------------------------------

function forma_popup(PDO $pdo): array
{
    $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
    $defaults = ['active' => false, 'eyebrow' => '', 'title' => '', 'text' => '', 'date_line' => '', 'start' => '', 'end' => '',
        'signup' => true, 'button_text' => '', 'button_link' => '', 'image' => '', 'version' => 1, 'theme' => ''];
    return is_array($p) ? array_merge($defaults, $p) : $defaults;
}

function forma_popup_live(array $p): bool
{
    $today = date('Y-m-d');
    return $p['active'] && $p['title'] !== ''
        && ($p['start'] === '' || $p['start'] <= $today)
        && ($p['end'] === '' || $p['end'] >= $today);
}

/** Einfache Seite für Bestätigungs- und Abmeldelinks. */
function forma_message_page(string $title, string $text): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $t = forma_h($title);
    $x = nl2br(forma_h($text));
    echo <<<HTML
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>$t · FORMA</title><link rel="icon" href="/img/forma-logo.png">
<style>@font-face{font-family:"Outfit";src:url(/fonts/Outfit-Regular.woff) format("woff")}@font-face{font-family:"Bricolage Grotesque";src:url(/fonts/BricolageGrotesque-Bold.woff) format("woff")}@font-face{font-family:"Italiana";src:url(/fonts/Italiana-Regular.woff) format("woff")}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#DDBFA8;color:#33211B;font:18px/1.6 "Outfit",system-ui,sans-serif;padding:16px;box-sizing:border-box}
main{background:#EEE2D4;max-width:520px;width:100%;padding:56px 32px 36px;border-radius:220px 220px 28px 28px;text-align:center;box-sizing:border-box}
img{width:72px;margin:0 auto 10px;display:block}p.e{font-family:"Italiana",serif;font-size:28px;color:#8C2C1D;margin:0}
h1{font-family:"Bricolage Grotesque",sans-serif;font-weight:650;color:#8C2C1D;font-size:34px;line-height:1.1;margin:6px 0 14px;letter-spacing:-.02em}
a.b{display:inline-block;margin-top:22px;background:#8C2C1D;color:#FBF1EA;border-radius:999px;padding:12px 24px;text-decoration:none}</style></head>
<body><main><img src="/img/forma-logo.png" alt="FORMA"><p class="e">FORMA Earth &amp; Art</p><h1>$t</h1><p>$x</p><a class="b" href="/">Zur Website</a></main></body></html>
HTML;
    exit;
}

function forma_h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

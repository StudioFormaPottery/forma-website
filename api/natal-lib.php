<?php
// Movecia · Prä- & Postnatal Training: Pakete online buchen (Stripe), Wunschtermine anfragen,
// Vivien bestätigt in der Verwaltung (Reiter „Prä- & Postnatal“). Rechnung im Movecia-Design.
// Zahlung läuft über das gemeinsame Stripe-Konto (FORMA & Movecia), Bezahlseite trägt Movecia-Namen.
declare(strict_types=1);
require_once __DIR__ . '/movecia-lib.php';

const NATAL_FROM_NAME = 'Vivien · Movecia';
const NATAL_COMPANY = [
    'firma' => 'Movecia – Personal Fitness',
    'name' => 'Vivien Malonga',
    'street' => 'Silvrettastraße 58',
    'city' => '6562 Mathon',
    'uid' => 'ATU83602825',
    'phone' => '+43 677 63 90 70 80',
    'email' => 'hello@moveciamovement.com',
    'web' => 'moveciamovement.com',
];
const NATAL_DAYPARTS = [
    'vormittag' => 'Vormittag (8–12 Uhr)',
    'nachmittag' => 'Nachmittag (13–17 Uhr)',
    'abend' => 'Abend (ab 18 Uhr)',
];
const NATAL_WEEKDAYS = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

function natal_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_natal_offers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        salutation TEXT NOT NULL DEFAULT \'Liebe\',
        name TEXT NOT NULL,
        note TEXT NOT NULL DEFAULT \'\',
        token TEXT NOT NULL UNIQUE,
        mode TEXT NOT NULL DEFAULT \'studio\',
        opened_at TEXT,
        created_at TEXT NOT NULL
    )');
    $oc = array_column($pdo->query('PRAGMA table_info(mm_natal_offers)')->fetchAll(), 'name');
    if ($oc && !in_array('mode', $oc, true)) $pdo->exec("ALTER TABLE mm_natal_offers ADD COLUMN mode TEXT NOT NULL DEFAULT 'studio'");
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_natal_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        offer_id INTEGER,
        package TEXT NOT NULL,
        title TEXT NOT NULL,
        units INTEGER NOT NULL,
        unit_cents INTEGER NOT NULL,
        amount_cents INTEGER NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT \'\',
        due_date TEXT NOT NULL DEFAULT \'\',
        note TEXT NOT NULL DEFAULT \'\',
        wishes TEXT NOT NULL DEFAULT \'[]\',
        status TEXT NOT NULL DEFAULT \'offen\',
        token TEXT NOT NULL,
        stripe_session TEXT NOT NULL DEFAULT \'\',
        ip_hash TEXT NOT NULL DEFAULT \'\',
        consent_start INTEGER NOT NULL DEFAULT 0,
        mailed INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        paid_at TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_natal_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL REFERENCES mm_natal_orders(id) ON DELETE CASCADE,
        date TEXT NOT NULL,
        daypart TEXT NOT NULL DEFAULT \'\',
        start TEXT NOT NULL DEFAULT \'\',
        minutes INTEGER NOT NULL DEFAULT 60,
        status TEXT NOT NULL DEFAULT \'angefragt\',
        note TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        confirmed_at TEXT
    )');
}

// --- Einstellungen (Preise, Wochentage, Bankverbindung für die Rechnung) -------------------

function natal_cfg(): array
{
    return mm_cfg('natal') ?? [];
}

/** Pakete mit Preisen (brutto inkl. 20 % USt.). Preise in der Verwaltung änderbar. */
function natal_packages(): array
{
    $p = [
        'einzel' => ['title' => 'Einzelstunde', 'units' => 1, 'cents' => 7500, 'sub' => '60 Minuten, 1:1', 'tag' => ''],
        'prae' => ['title' => 'Pränatal-Paket', 'units' => 6, 'cents' => 34200, 'sub' => '6 Einheiten bis zur Geburt', 'tag' => ''],
        'post' => ['title' => 'Postnatal-Paket', 'units' => 8, 'cents' => 43200, 'sub' => '8 Einheiten nach der Geburt', 'tag' => ''],
        'kombi' => ['title' => 'Kombi-Paket', 'units' => 14, 'cents' => 72800, 'sub' => '6 Einheiten vor und 8 nach der Geburt', 'tag' => 'Bestpreis'],
        // Online live per Video (Vorschlag 23.10.2026, ca. 13–15 % unter dem Studio, in der Verwaltung änderbar)
        'o_einzel' => ['title' => 'Einzelstunde online', 'units' => 1, 'cents' => 6500, 'sub' => '60 Minuten, 1:1 live per Video', 'tag' => ''],
        'o_prae' => ['title' => 'Pränatal-Paket online', 'units' => 6, 'cents' => 29400, 'sub' => '6 Einheiten bis zur Geburt', 'tag' => ''],
        'o_post' => ['title' => 'Postnatal-Paket online', 'units' => 8, 'cents' => 36800, 'sub' => '8 Einheiten nach der Geburt', 'tag' => ''],
        'o_kombi' => ['title' => 'Kombi-Paket online', 'units' => 14, 'cents' => 61600, 'sub' => '6 Einheiten vor und 8 nach der Geburt', 'tag' => 'Bestpreis'],
    ];
    foreach ($p as $k => $x) $p[$k]['mode'] = str_starts_with($k, 'o_') ? 'online' : 'studio';
    foreach ((natal_cfg()['prices'] ?? []) as $k => $c) if (isset($p[$k]) && (int)$c > 0) $p[$k]['cents'] = (int)$c;
    foreach ($p as $k => $x) $p[$k]['unit_cents'] = (int)round($x['cents'] / $x['units']);
    return $p;
}

/** Wochentage, an denen Wunschtermine möglich sind (1 = Mo … 7 = So). */
/** Pakete einer Variante (studio oder online). */
function natal_packages_for(string $mode): array
{
    return array_filter(natal_packages(), fn($p) => $p['mode'] === $mode);
}

function natal_is_online(array $o): bool
{
    return str_starts_with((string)($o['package'] ?? ''), 'o_');
}

/** Wo die Einheit stattfindet: Studio-Adresse oder Videoraum. */
function natal_place(bool $online): string
{
    if (!$online) return 'Movecia Studio, ' . NATAL_COMPANY['street'] . ', ' . NATAL_COMPANY['city'];
    $room = trim((string)(natal_cfg()['room'] ?? ''));
    return $room !== '' ? 'Online per Video: ' . $room : 'Online per Video (den Link bekommst du vor dem Termin)';
}

function natal_weekdays(): array
{
    $w = natal_cfg()['weekdays'] ?? [1, 2, 3, 4, 5];
    return array_values(array_filter(array_map('intval', (array)$w), fn($d) => $d >= 1 && $d <= 7)) ?: [1, 2, 3, 4, 5];
}

function natal_notify_email(): string
{
    $e = (string)(natal_cfg()['notify'] ?? '');
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : MM_FROM_EMAIL;
}

// --- Daten ---------------------------------------------------------------------------------

function natal_order(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mm_natal_orders WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function natal_order_auth(PDO $pdo, int $id, string $tok): ?array
{
    $o = natal_order($pdo, $id);
    return $o && $tok !== '' && hash_equals((string)$o['token'], $tok) ? $o : null;
}

function natal_sessions(PDO $pdo, int $oid): array
{
    $st = $pdo->prepare("SELECT * FROM mm_natal_sessions WHERE order_id = ? ORDER BY date, CASE start WHEN '' THEN '99' ELSE start END, id");
    $st->execute([$oid]);
    return $st->fetchAll();
}

function natal_used(PDO $pdo, int $oid): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM mm_natal_sessions WHERE order_id = ? AND status IN ('angefragt','bestaetigt','erledigt')");
    $st->execute([$oid]);
    return (int)$st->fetchColumn();
}

function natal_left(PDO $pdo, array $o): int
{
    return max(0, (int)$o['units'] - natal_used($pdo, (int)$o['id']));
}

function natal_link(array $o): string
{
    return mm_site() . '/natal/?o=' . (int)$o['id'] . '&k=' . $o['token'];
}

function natal_offer_link(array $a): string
{
    return mm_site() . (($a['mode'] ?? 'studio') === 'online' ? '/natal/online/' : '/natal/') . '?a=' . $a['token'];
}

function natal_admin_link(): string
{
    return forma_site_url() . '/verwaltung/?view=natal';
}

// --- Datum & Text --------------------------------------------------------------------------

function natal_date_de(string $ymd, bool $year = true): string
{
    $t = strtotime($ymd);
    if (!$t) return $ymd;
    return NATAL_WEEKDAYS[(int)date('N', $t)] . ', ' . date('j. n.', $t) . ($year ? ' ' . date('Y', $t) : '');
}

function natal_when(array $s): string
{
    $d = natal_date_de((string)$s['date']);
    if ((string)$s['start'] !== '') return $d . ' um ' . ltrim(substr((string)$s['start'], 0, 5), '0') . ' Uhr';
    return $d . ((string)$s['daypart'] !== '' ? ', ' . (NATAL_DAYPARTS[$s['daypart']] ?? $s['daypart']) : '');
}

function natal_phase(array $o, string $date): string
{
    $due = (string)$o['due_date'];
    if ($due === '') return '';
    return $date < $due ? 'vor der Geburt' : 'nach der Geburt';
}

function natal_status_label(string $s): string
{
    return ['angefragt' => 'angefragt', 'bestaetigt' => 'bestätigt', 'erledigt' => 'war schon', 'abgesagt' => 'abgesagt'][$s] ?? $s;
}

/** Wunschtermine prüfen: Datum ab übermorgen, erlaubter Wochentag, gültige Tageszeit. */
function natal_clean_wishes($raw): array
{
    $out = [];
    if (is_string($raw)) $raw = json_decode($raw, true);
    if (!is_array($raw)) return [];
    $min = date('Y-m-d', strtotime('+1 day'));
    $max = date('Y-m-d', strtotime('+9 months'));
    $days = natal_weekdays();
    foreach ($raw as $w) {
        $d = (string)($w['date'] ?? '');
        $p = (string)($w['daypart'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d) || $d < $min || $d > $max) continue;
        if (!in_array((int)date('N', strtotime($d)), $days, true)) continue;
        if (!isset(NATAL_DAYPARTS[$p])) $p = '';
        $out[$d] = ['date' => $d, 'daypart' => $p];
    }
    ksort($out);
    return array_values($out);
}

// --- Mails im Movecia-Design (Pflaume), Absender hello@moveciamovement.com ------------------

function natal_mail_html(string $title, string $inner): string
{
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . forma_h($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F2ECE4;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F2ECE4;"><tr><td align="center" style="padding:26px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FBF8F4;border-radius:24px;font-family:Helvetica,Arial,sans-serif;color:#2E2529;">'
        . '<tr><td style="padding:26px 30px 0;font-family:Georgia,\'Times New Roman\',serif;font-size:26px;color:#2E2529;">Movecia<span style="color:#7D5E68;">.</span></td></tr>'
        . '<tr><td style="padding:14px 30px 28px;font-size:16px;line-height:1.65;">' . $inner
        . '<p style="margin:24px 0 0;">Bis bald,<br>Vivien</p></td></tr></table>'
        . '<p style="max-width:560px;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#7D7076;margin:16px auto 0;">Vivien Malonga · Movecia – Personal Fitness · ' . NATAL_COMPANY['street'] . ' · ' . NATAL_COMPANY['city'] . ' · ' . NATAL_COMPANY['email'] . '</p>'
        . '</td></tr></table></body></html>';
}

function natal_btn(string $href, string $label): string
{
    return '<p style="margin:22px 0 6px;text-align:center;"><a href="' . forma_h($href) . '" style="display:inline-block;padding:14px 28px;border-radius:999px;background:#7D5E68;color:#FBF5F7;text-decoration:none;font-weight:bold;">' . forma_h($label) . '</a></p>';
}

function natal_h1(string $t): string
{
    return '<h1 style="font-family:Georgia,\'Times New Roman\',serif;font-weight:400;font-size:28px;line-height:1.2;color:#2E2529;margin:0 0 14px;">' . forma_h($t) . '</h1>';
}

/** Schickt eine Mail: Text-Version wird aus den Absätzen gebaut, HTML aus denselben Teilen. */
function natal_mail(string $to, string $subject, string $title, array $paras, ?array $button = null, array $attachments = []): bool
{
    $text = implode("\n\n", array_map(fn($p) => is_array($p) ? implode("\n", $p) : $p, $paras));
    if ($button) $text .= "\n\n" . $button[1] . ': ' . $button[0];
    $text .= "\n\nBis bald,\nVivien\n\nVivien Malonga · Movecia – Personal Fitness · " . NATAL_COMPANY['email'];
    $html = natal_h1($title);
    foreach ($paras as $p) {
        $html .= is_array($p)
            ? '<p style="margin:0 0 14px;padding:12px 16px;background:#F2ECE4;border-radius:14px;">' . implode('<br>', array_map('forma_h', $p)) . '</p>'
            : '<p style="margin:0 0 14px;">' . nl2br(forma_h($p)) . '</p>';
    }
    if ($button) $html .= natal_btn($button[0], $button[1]);
    return mm_mail($to, $subject, $text, natal_mail_html($title, $html), $attachments, NATAL_FROM_NAME);
}

// --- Kalender ------------------------------------------------------------------------------

function natal_ics(array $sessions, string $who = '', bool $online = false): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $f = fn(DateTime $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $esc = fn(string $v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $v);
    $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Movecia//Natal//DE\r\nMETHOD:PUBLISH\r\n";
    foreach ($sessions as $s) {
        if ((string)$s['start'] === '' || !in_array($s['status'], ['bestaetigt', 'erledigt'], true)) continue;
        $from = new DateTime($s['date'] . ' ' . $s['start'], $tz);
        $to = (clone $from)->modify('+' . (int)$s['minutes'] . ' minutes');
        $out .= "BEGIN:VEVENT\r\nUID:natal-{$s['id']}@moveciamovement.com\r\nDTSTAMP:" . $f(new DateTime('now', $tz)) . "\r\nDTSTART:" . $f($from) . "\r\nDTEND:" . $f($to) . "\r\n"
            . 'SUMMARY:' . $esc('Movecia · Prä-/Postnatal Training' . ($online ? ' online' : '') . ($who !== '' ? ' · ' . $who : '')) . "\r\nLOCATION:" . $esc(natal_place($online))
            . "\r\nDESCRIPTION:" . $esc(($online ? '1:1 live per Video mit Vivien. Matte, etwas Platz und Handy oder Laptop bereitlegen.' : '1:1 mit Vivien. Bequeme Kleidung, eine Flasche Wasser und gern ein Handtuch mitbringen.') . ' Bei Fragen: ' . NATAL_COMPANY['phone']) . "\r\nEND:VEVENT\r\n";
    }
    return $out . "END:VCALENDAR\r\n";
}

function natal_gcal(array $s, bool $online = false): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $from = new DateTime($s['date'] . ' ' . $s['start'], $tz);
    $to = (clone $from)->modify('+' . (int)$s['minutes'] . ' minutes');
    $f = fn(DateTime $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode('Movecia · Prä-/Postnatal Training') . '&dates=' . $f($from) . '/' . $f($to)
        . '&location=' . rawurlencode(natal_place($online)) . '&details=' . rawurlencode('1:1 mit Vivien');
}

// --- Stripe --------------------------------------------------------------------------------

/** Legt die Stripe-Bezahlseite an. Preis bestimmt immer der Server. Oben steht „Movecia“, nicht FORMA. */
function natal_checkout(PDO $pdo, array $o): string
{
    $site = mm_site();
    $params = [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => $o['email'],
        'client_reference_id' => 'natal-' . $o['id'],
        'billing_address_collection' => 'required',
        'metadata' => ['bereich' => 'Movecia', 'natal_order_id' => (string)$o['id'], 'paket' => $o['package']],
        'line_items' => [[
            'quantity' => (int)$o['units'],
            'price_data' => ['currency' => 'eur', 'unit_amount' => (int)$o['unit_cents'], 'product_data' => [
                'name' => 'Movecia · Prä-/Postnatal Training – ' . $o['title'],
                'description' => ((int)$o['units'] === 1 ? '1 Einheit' : $o['units'] . ' Einheiten') . ' à 60 Min., ' . (natal_is_online($o) ? '1:1 live per Video' : '1:1 im Movecia Studio') . ' · Leistung von Movecia – Personal Fitness (Vivien Malonga)',
            ]],
        ]],
        'payment_intent_data' => ['description' => 'Movecia · Prä-/Postnatal ' . $o['title'] . ' · ' . $o['name'], 'metadata' => ['bereich' => 'Movecia', 'natal_order_id' => (string)$o['id']]],
        'custom_text' => ['submit' => ['message' => 'Leistung von Movecia – Personal Fitness (Vivien Malonga). Die Zahlung läuft über das gemeinsame Konto von FORMA & Movecia. Deine Rechnung im Movecia-Design bekommst du per E-Mail.']],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/natal-bezahlt.php?o=' . $o['id'] . '&k=' . $o['token'],
        'cancel_url' => $site . '/natal/?o=' . $o['id'] . '&k=' . $o['token'] . '&abgebrochen=1',
    ];
    $branded = $params;
    $branded['branding_settings'] = ['display_name' => 'Movecia – Personal Fitness', 'background_color' => '#F2ECE4', 'button_color' => '#7D5E68', 'border_style' => 'rounded', 'font_family' => 'inter'];
    $branded['payment_intent_data']['statement_descriptor_suffix'] = 'MOVECIA';
    try {
        $s = forma_stripe('POST', '/v1/checkout/sessions', $branded, null, '2025-09-30.clover');
    } catch (Throwable $e) {
        // Ältere Konto-Einstellungen kennen den Movecia-Namen pro Zahlung evtl. nicht: dann ohne, Zahlung geht trotzdem.
        error_log('Natal checkout (branded): ' . $e->getMessage());
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params);
    }
    $pdo->prepare('UPDATE mm_natal_orders SET stripe_session = ? WHERE id = ?')->execute([(string)$s['id'], $o['id']]);
    return (string)$s['url'];
}

/** Prüft die Zahlung. Bei Zahlung genau einmal: Wunschtermine anlegen, Mails, Rechnung. */
function natal_check_paid(PDO $pdo, array $o): string
{
    if ($o['status'] !== 'offen' || (string)$o['stripe_session'] === '' || !forma_stripe_key()) return (string)$o['status'];
    try {
        $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$o['stripe_session']));
    } catch (Throwable $e) {
        error_log('Natal check: ' . $e->getMessage());
        return 'offen';
    }
    if (($s['payment_status'] ?? '') !== 'paid') {
        if (($s['status'] ?? '') === 'expired') $pdo->prepare("UPDATE mm_natal_orders SET stripe_session = '' WHERE id = ? AND status = 'offen'")->execute([$o['id']]);
        return 'offen';
    }
    $u = $pdo->prepare("UPDATE mm_natal_orders SET status = 'bezahlt', paid_at = ? WHERE id = ? AND status = 'offen'");
    $u->execute([date('c'), $o['id']]);
    if ($u->rowCount() !== 1) return 'bezahlt';
    $o = natal_order($pdo, (int)$o['id']);
    $wishes = array_slice(natal_clean_wishes((string)$o['wishes']), 0, (int)$o['units']);
    $ins = $pdo->prepare("INSERT INTO mm_natal_sessions (order_id, date, daypart, status, created_at) VALUES (?,?,?,'angefragt',?)");
    foreach ($wishes as $w) $ins->execute([$o['id'], $w['date'], $w['daypart'], date('c')]);
    natal_paid_mails($pdo, $o, $wishes);
    if (function_exists('forma_invoice_for')) forma_invoice_for($pdo, 'natal', (int)$o['id']);
    return 'bezahlt';
}

function natal_paid_mails(PDO $pdo, array $o, array $wishes): void
{
    $first = forma_first_name((string)$o['name']);
    $wl = array_map(fn($w) => natal_when($w + ['start' => '']), $wishes);
    $paras = ["Hallo $first,", 'danke für deine Buchung! Deine Zahlung ist angekommen und dein ' . $o['title'] . ' ist fix.',
        [$o['title'] . ' · ' . ((int)$o['units'] === 1 ? '1 Einheit' : $o['units'] . ' Einheiten') . ' à 60 Minuten, 1:1', 'Bezahlt: ' . forma_euro((int)$o['amount_cents']) . ' (inkl. 20 % USt.)', 'Gültig bis 6 Monate nach der Geburt']];
    $paras[] = $wl
        ? "Deine Wunschtermine habe ich bekommen:\n– " . implode("\n– ", $wl) . "\n\nIch schaue sie mir an und bestätige dir jeden Termin mit Uhrzeit per E-Mail."
        : 'Deine Termine wählst du ganz einfach über deine persönliche Seite aus. Ich bestätige dir jeden Termin mit Uhrzeit per E-Mail.';
    $paras[] = natal_is_online($o)
        ? 'Bitte sprich das Training vorher mit deiner Ärztin oder Hebamme ab. Für unsere Online-Einheiten brauchst du nur eine Matte, etwas Platz und dein Handy oder Laptop, am besten so aufgestellt, dass ich dich ganz sehe. Den Link zum Videoraum findest du in jeder Terminbestätigung.'
        : 'Bitte sprich das Training vorher mit deiner Ärztin oder Hebamme ab. Komm in bequemer Kleidung und bring eine Flasche Wasser mit. Matten und alles andere habe ich im Studio.';
    $paras[] = 'Die Rechnung bekommst du in einer eigenen E-Mail. Du hast ein 14-tägiges Rücktrittsrecht. Weil du dir gewünscht hast, dass die Termine schon in dieser Zeit stattfinden können, zahlst du bei einem Rücktritt nur die Einheiten, die schon stattgefunden haben. Rücktritts-Formular: ' . mm_site() . '/withdraw?v=natal';
    natal_mail((string)$o['email'], 'Deine Buchung bei Movecia: ' . $o['title'], 'Schön, dass du dabei bist', $paras, [natal_link($o), 'Meine Termine ansehen']);

    $v = ["Neue Buchung Prä- & Postnatal Training:", [
        $o['name'] . ' · ' . $o['email'] . ($o['phone'] !== '' ? ' · ' . $o['phone'] : ''),
        $o['title'] . ' · ' . forma_euro((int)$o['amount_cents']) . ' (online bezahlt)',
        'Geburtstermin: ' . ($o['due_date'] !== '' ? natal_date_de((string)$o['due_date']) : 'nicht angegeben'),
    ]];
    if ($o['note'] !== '') $v[] = 'Ihre Nachricht: ' . $o['note'];
    $v[] = $wl ? "Wunschtermine zum Bestätigen:\n– " . implode("\n– ", $wl) : 'Noch keine Wunschtermine. Sie wählt sie über ihre persönliche Seite aus.';
    natal_mail(natal_notify_email(), 'Neue Buchung Prä-/Postnatal: ' . $o['name'] . ' · ' . $o['title'], 'Neue Buchung', $v, [natal_admin_link(), 'In der Verwaltung öffnen']);
}

/** Neue Wunschtermine aus der persönlichen Seite. Gibt die angelegten Termine zurück. */
function natal_add_wishes(PDO $pdo, array $o, array $wishes, string $note = ''): array
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $left = natal_left($pdo, $o);
        $have = $pdo->prepare("SELECT date FROM mm_natal_sessions WHERE order_id = ? AND status IN ('angefragt','bestaetigt')");
        $have->execute([$o['id']]);
        $taken = array_flip($have->fetchAll(PDO::FETCH_COLUMN));
        $new = [];
        $ins = $pdo->prepare("INSERT INTO mm_natal_sessions (order_id, date, daypart, status, note, created_at) VALUES (?,?,?,'angefragt',?,?)");
        foreach ($wishes as $w) {
            if (count($new) >= $left || isset($taken[$w['date']])) continue;
            $ins->execute([$o['id'], $w['date'], $w['daypart'], mb_substr($note, 0, 400), date('c')]);
            $new[] = $w + ['start' => ''];
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
    if ($new) {
        $wl = array_map('natal_when', $new);
        $p = [$o['name'] . ' wünscht sich ' . (count($new) === 1 ? 'einen neuen Termin' : count($new) . ' neue Termine') . ' (' . $o['title'] . '):', "– " . implode("\n– ", $wl)];
        if ($note !== '') $p[] = 'Ihre Nachricht: ' . $note;
        natal_mail(natal_notify_email(), 'Terminanfrage Prä-/Postnatal: ' . $o['name'], 'Neue Terminanfrage', $p, [natal_admin_link(), 'Jetzt bestätigen']);
    }
    return $new;
}

function natal_session(PDO $pdo, int $sid): ?array
{
    $st = $pdo->prepare('SELECT * FROM mm_natal_sessions WHERE id = ?');
    $st->execute([$sid]);
    return $st->fetch() ?: null;
}

/** Vivien bestätigt (Datum darf sie dabei ändern). Mail mit Kalenderdatei an die Kundin. */
function natal_confirm(PDO $pdo, int $sid, string $date, string $time, bool $mail = true): ?array
{
    $s = natal_session($pdo, $sid);
    if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{1,2}:\d{2}$/', $time)) return null;
    $time = str_pad($time, 5, '0', STR_PAD_LEFT);
    $moved = $date !== $s['date'];
    $pdo->prepare("UPDATE mm_natal_sessions SET date = ?, start = ?, status = 'bestaetigt', confirmed_at = ? WHERE id = ?")->execute([$date, $time, date('c'), $sid]);
    $s = natal_session($pdo, $sid);
    $o = natal_order($pdo, (int)$s['order_id']);
    if ($mail && $o) {
        $first = forma_first_name((string)$o['name']);
        $left = natal_left($pdo, $o);
        $p = ["Hallo $first,", $moved ? 'an deinem Wunschtag ging es sich leider nicht aus, deshalb habe ich dir diesen Termin eingetragen. Passt er dir nicht, antworte einfach auf diese E-Mail, dann finden wir einen anderen.' : 'dein Termin ist bestätigt. Ich freue mich auf dich!',
            natal_is_online($o) ? [natal_when($s), '60 Minuten, 1:1 live per Video', natal_place(true)] : [natal_when($s), '60 Minuten, 1:1 im Movecia Studio', NATAL_COMPANY['street'] . ', ' . NATAL_COMPANY['city']],
            'Mit der angehängten Kalenderdatei übernimmst du den Termin mit einem Tipp in deinen Kalender. Auf deiner persönlichen Seite findest du ihn auch für Google Kalender.',
            'Musst du einen Termin verschieben, gib mir bitte so bald wie möglich Bescheid, am besten bis 24 Stunden vorher. Wenn das Baby dazwischenkommt, finden wir natürlich immer eine Lösung.'];
        if ($left > 0) $p[] = 'Du hast noch ' . ($left === 1 ? '1 Einheit' : "$left Einheiten") . ', die du jederzeit über deine Seite anfragen kannst.';
        natal_mail((string)$o['email'], 'Dein Termin ist bestätigt: ' . natal_when($s), 'Dein Termin steht', $p, [natal_link($o), 'Meine Termine'],
            [['Movecia-Termin.ics', natal_ics([$s], '', natal_is_online($o)), 'text/calendar; charset=utf-8; method=PUBLISH']]);
    }
    return $s;
}

/** Wunschtermin oder bestätigten Termin absagen: Einheit wird wieder frei. */
function natal_cancel(PDO $pdo, int $sid, string $reason, bool $mail = true): void
{
    $s = natal_session($pdo, $sid);
    if (!$s || $s['status'] === 'abgesagt') return;
    $pdo->prepare("UPDATE mm_natal_sessions SET status = 'abgesagt', note = TRIM(note || ' ' || ?) WHERE id = ?")->execute([$reason !== '' ? '[Absage: ' . $reason . ']' : '', $sid]);
    $o = natal_order($pdo, (int)$s['order_id']);
    if ($mail && $o) {
        $first = forma_first_name((string)$o['name']);
        $p = ["Hallo $first,", ($s['status'] === 'angefragt' ? 'dein Wunschtermin am ' : 'dein Termin am ') . natal_when($s) . ' geht sich leider nicht aus.' . ($reason !== '' ? "\n\n" . $reason : ''),
            'Die Einheit ist natürlich nicht verloren. Such dir über deine Seite einfach einen neuen Wunschtermin aus.'];
        natal_mail((string)$o['email'], 'Neuer Termin nötig: ' . natal_date_de((string)$s['date'], false), 'Lass uns einen neuen Termin finden', $p, [natal_link($o), 'Neuen Termin wählen']);
    }
}

/** Fertige Nachricht für WhatsApp zum persönlichen Angebot. */
function natal_offer_message(array $a): string
{
    $first = trim((string)$a['name']);
    return trim(($a['salutation'] ?: 'Liebe') . ' ' . $first . ",\n\nwie besprochen schicke ich dir hier mein Angebot für dein Prä- und Postnatal-Training " . (($a['mode'] ?? 'studio') === 'online' ? 'online, live per Video' : 'bei mir im Studio') . ". Du siehst alle Pakete und kannst direkt online buchen. Deine Wunschtermine wählst du im Kalender aus, ich bestätige sie dir dann persönlich.\n\n" . natal_offer_link($a) . "\n\nWenn du Fragen hast, schreib mir einfach.\n\nBis bald,\nVivien");
}

// --- Rechnung im Movecia-Design (wird von api/invoices.php aufgerufen) ----------------------

function natal_invoice_source(PDO $pdo, int $id): ?array
{
    $o = natal_order($pdo, $id);
    if (!$o || $o['status'] !== 'bezahlt' || (int)$o['amount_cents'] <= 0) return null;
    $units = (int)$o['units'];
    return ['name' => $o['name'], 'email' => $o['email'], 'session' => (string)$o['stripe_session'], 'service' => 'nach Vereinbarung',
        'items' => [['text' => 'Prä-/Postnatal Training · ' . $o['title'], 'detail' => ($units === 1 ? '1 Einheit' : $units . ' Einheiten') . ' à 60 Min., ' . (natal_is_online($o) ? '1:1 live online per Video' : '1:1 im Movecia Studio, Mathon') . ' · Termine nach Vereinbarung, gültig bis 6 Monate nach der Geburt',
            'qty' => $units, 'unit' => (int)$o['unit_cents']]],
        'gross' => (int)$o['amount_cents'], 'paid' => $o['paid_at'], 'type' => 'Movecia',
        'pay_note' => 'Der Betrag wurde am ' . date('d.m.Y', strtotime((string)$o['paid_at'])) . ' online bezahlt (Kartenzahlung über Stripe, gemeinsames Konto FORMA & Movecia). Buchungsvermerk: Leistung von Movecia – Personal Fitness.'];
}

function natal_invoice_pdf(array $inv): string
{
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
    $t = 'forma_pdf_text';
    $co = NATAL_COMPANY;
    $cfg = natal_cfg();
    $pdf = new FPDF('P', 'mm', 'A4');
    $fd = __DIR__ . '/vendor/fpdf/font/';
    $pdf->AddFont('Inter', '', 'Inter-Regular.json', $fd);
    $pdf->AddFont('Inter', 'B', 'Inter-SemiBold.json', $fd);
    $pdf->SetTitle($t('Rechnung ' . $inv['nr']));
    $pdf->SetAuthor($t($co['firma']));
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(18, 16, 18);
    $pdf->AddPage();
    $ink = [46, 59, 52]; $text = [58, 63, 59]; $soft = [122, 132, 124]; $sage = [125, 145, 127]; $mist = [238, 242, 236]; $line = [213, 220, 211];
    $storno = (int)$inv['gross_cents'] < 0;

    $img = dirname(__DIR__) . '/img/movecia/';
    if (is_file($img . 'Movecia_Ecke.png')) $pdf->Image($img . 'Movecia_Ecke.png', 142, 0, 68);
    if (is_file($img . 'Movecia_Logo.png')) $pdf->Image($img . 'Movecia_Logo.png', 18, 16, 66);

    // „RECHNUNG“ gesperrt
    $word = $storno ? 'STORNORECHNUNG' : 'RECHNUNG';
    $pdf->SetTextColor(...$ink); $pdf->SetFont('Inter', '', $storno ? 18 : 24);
    $x = 18; $y = 44;
    foreach (str_split($word) as $ch) { $pdf->SetXY($x, $y); $pdf->Cell(0, 10, $ch); $x += $pdf->GetStringWidth($ch) + ($storno ? 2.2 : 3.2); }
    $pdf->SetTextColor(...$soft); $pdf->SetFont('Inter', '', 7.5);
    $pdf->SetXY(18, 57); $pdf->Cell(120, 4, $t($co['firma'] . ' · ' . $co['name'] . ' · ' . $co['street'] . ' · ' . $co['city']));

    // Empfänger
    $pdf->SetTextColor(...$sage); $pdf->SetFont('Inter', 'B', 7.5);
    $pdf->SetXY(18, 66); $pdf->Cell(60, 4, $t('RECHNUNG AN'));
    $pdf->SetTextColor(...$ink); $pdf->SetFont('Inter', 'B', 10.5);
    $pdf->SetXY(18, 71); $pdf->MultiCell(80, 5, $t((string)$inv['name']));
    $pdf->SetTextColor(...$text); $pdf->SetFont('Inter', '', 9.3);
    $pdf->SetX(18); $pdf->MultiCell(80, 4.6, $t(trim($inv['address'] . "\n" . $inv['email'])), 0, 'L');

    // Rechnungsdaten rechts
    $meta = [['Rechnungsnr.', $inv['nr']], ['Rechnungsdatum', date('d.m.Y', strtotime($inv['issued_at']))], ['Leistungsdatum', $inv['service_date']], ['UID-Nr.', $co['uid']]];
    $y = 66;
    foreach ($meta as [$k, $v]) {
        $pdf->SetTextColor(...$soft); $pdf->SetFont('Inter', '', 8.5); $pdf->SetXY(120, $y); $pdf->Cell(32, 5, $t($k));
        $pdf->SetTextColor(...$ink); $pdf->SetFont('Inter', 'B', 9); $pdf->SetXY(150, $y); $pdf->Cell(42, 5, $t((string)$v), 0, 0, 'R');
        $y += 5.6;
    }

    // Positionen
    $y = 100;
    $pdf->SetFillColor(...$mist); $pdf->Rect(18, $y, 174, 8, 'F');
    $pdf->SetTextColor(...$ink); $pdf->SetFont('Inter', 'B', 8.3);
    $cols = [[20, 10, 'Pos.', 'L'], [30, 94, 'Leistung', 'L'], [124, 16, 'Menge', 'R'], [140, 26, 'Einzelpreis', 'R'], [166, 24, 'Gesamt', 'R']];
    foreach ($cols as [$cx, $w, $h, $al]) { $pdf->SetXY($cx, $y + 1.5); $pdf->Cell($w, 5, $t($h), 0, 0, $al); }
    $y += 12;
    $pdf->SetDrawColor(...$line); $pdf->SetLineWidth(.2);
    foreach (json_decode((string)$inv['items'], true) as $i => $it) {
        $pdf->SetTextColor(...$text); $pdf->SetFont('Inter', '', 9.3);
        $pdf->SetXY(20, $y); $pdf->Cell(10, 5, (string)($i + 1));
        $pdf->SetTextColor(...$ink); $pdf->SetFont('Inter', 'B', 9.5); $pdf->SetXY(30, $y); $pdf->MultiCell(92, 5, $t($it['text']));
        $yy = $pdf->GetY();
        if (($it['detail'] ?? '') !== '') { $pdf->SetFont('Inter', '', 8); $pdf->SetTextColor(...$soft); $pdf->SetX(30); $pdf->MultiCell(92, 4.2, $t($it['detail']), 0, 'L'); $yy = $pdf->GetY(); }
        $pdf->SetTextColor(...$text); $pdf->SetFont('Inter', '', 9.3);
        $pdf->SetXY(124, $y); $pdf->Cell(16, 5, (string)$it['qty'], 0, 0, 'R');
        $pdf->SetXY(140, $y); $pdf->Cell(26, 5, $t(forma_cents_de((int)$it['unit'])), 0, 0, 'R');
        $pdf->SetXY(166, $y); $pdf->Cell(24, 5, $t(forma_cents_de((int)$it['unit'] * (int)$it['qty'])), 0, 0, 'R');
        $y = $yy + 3.5;
        $pdf->Line(18, $y - 1.8, 192, $y - 1.8);
    }

    // Summen
    $y += 6;
    $rate = rtrim(rtrim(number_format((float)$inv['vat_rate'], 1, ',', ''), '0'), ',');
    foreach ([['Summe netto', forma_cents_de((int)$inv['net_cents'])], ['zzgl. ' . $rate . ' % USt.', forma_cents_de((int)$inv['vat_cents'])]] as [$k, $v]) {
        $pdf->SetTextColor(...$text); $pdf->SetFont('Inter', '', 9.3);
        $pdf->SetXY(118, $y); $pdf->Cell(44, 5.5, $t($k)); $pdf->SetXY(162, $y); $pdf->Cell(28, 5.5, $t($v), 0, 0, 'R');
        $y += 6;
    }
    $pdf->SetFillColor(...$ink); $pdf->Rect(116, $y + 1, 76, 11, 'F');
    $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Inter', 'B', 10.5);
    $pdf->SetXY(120, $y + 3.5); $pdf->Cell(40, 6, $t('Gesamt brutto'));
    $pdf->SetXY(160, $y + 3.5); $pdf->Cell(30, 6, $t(forma_cents_de((int)$inv['gross_cents'])), 0, 0, 'R');
    $y += 24;

    // Zahlungshinweis
    $paid = $storno
        ? 'Der Betrag wurde am ' . date('d.m.Y', strtotime($inv['issued_at'])) . ' auf das Zahlungsmittel zurückerstattet, mit dem bezahlt wurde.'
        : (($inv['pay_note'] ?? '') !== '' ? (string)$inv['pay_note'] : 'Der Betrag wurde online bezahlt.');
    if ((string)$inv['vat_note'] !== '') $paid .= "\n" . $inv['vat_note'];
    $pdf->SetTextColor(...$text); $pdf->SetFont('Inter', '', 9);
    $pdf->SetXY(18, $y); $pdf->MultiCell(174, 4.8, $t($paid), 0, 'L');
    $pdf->SetTextColor(...$sage); $pdf->SetFont('Inter', 'B', 11); $pdf->SetX(18); $pdf->Cell(174, 9, $t('Vielen Dank!'), 0, 1);
    $pdf->SetTextColor(...$soft); $pdf->SetFont('Inter', '', 7.8); $pdf->SetX(18);
    $pdf->MultiCell(174, 4, $t('Leistungszeitraum laut Terminvereinbarung. Sofern nicht gesondert angegeben, entspricht das Leistungsdatum dem Rechnungsdatum.'), 0, 'L');

    // Fußzeile: drei Spalten
    $fy = 258;
    $pdf->SetDrawColor(...$line); $pdf->Line(18, $fy, 192, $fy);
    $bank = trim((string)($cfg['bank'] ?? ''));
    $iban = trim((string)($cfg['iban'] ?? ''));
    $bic = trim((string)($cfg['bic'] ?? ''));
    $colsF = [
        [18, 'Vivien Malonga', $co['firma'] . "\n" . $co['street'] . "\n" . $co['city']],
        [78, 'Kontakt', 'Tel. ' . $co['phone'] . "\n" . $co['email'] . "\n" . $co['web']],
        [138, 'Zahlung', ($iban !== '' ? trim(($bank !== '' ? $bank . "\n" : '') . 'IBAN ' . $iban . ($bic !== '' ? "\nBIC " . $bic : '')) : "Online bezahlt per Karte\nüber Stripe") . "\nUID " . $co['uid']],
    ];
    foreach ($colsF as [$cx, $head, $body]) {
        $pdf->SetTextColor(...$ink); $pdf->SetFont('Inter', 'B', 8); $pdf->SetXY($cx, $fy + 4); $pdf->Cell(56, 4.4, $t($head));
        $pdf->SetTextColor(...$soft); $pdf->SetFont('Inter', '', 7.8); $pdf->SetXY($cx, $fy + 8.6); $pdf->MultiCell(56, 4, $t($body), 0, 'L');
    }
    return $pdf->Output('S');
}

function natal_invoice_mail(array $inv, string $pdf): void
{
    $file = 'Rechnung_' . $inv['nr'] . '_Movecia.pdf';
    $first = forma_first_name((string)$inv['name']);
    if (trim((string)$inv['email']) !== '') {
        $storno = (int)$inv['gross_cents'] < 0;
        natal_mail((string)$inv['email'], ($storno ? 'Deine Stornorechnung ' : 'Deine Rechnung ') . $inv['nr'] . ' von Movecia', $storno ? 'Deine Stornorechnung' : 'Deine Rechnung',
            ["Hallo $first,", $storno ? 'im Anhang findest du die Stornorechnung ' . $inv['nr'] . ' für deine Unterlagen. Der Betrag von ' . forma_cents_de(-(int)$inv['gross_cents']) . ' wird dir zurückerstattet.'
                : 'im Anhang findest du deine Rechnung ' . $inv['nr'] . ' als PDF. Rechnungsbetrag: ' . forma_cents_de((int)$inv['gross_cents']) . ' (bereits bezahlt).', 'Bei Fragen antworte einfach auf diese E-Mail.'],
            null, [[$file, $pdf, 'application/pdf']]);
    }
    forma_mail(FORMA_NOTIFY_EMAIL, 'Rechnung ' . $inv['nr'] . ' (Movecia): ' . $inv['name'] . ' (' . forma_cents_de((int)$inv['gross_cents']) . ')',
        "Neue Online-Rechnung für deine Buchhaltung, Bereich Movecia (Prä-/Postnatal Training).\nBezahlt über das gemeinsame Stripe-Konto FORMA & Movecia.\n\nNummer: {$inv['nr']}\nDatum: " . date('d.m.Y', strtotime($inv['issued_at'])) . "\nKundin: {$inv['name']} ({$inv['email']})\nBrutto: " . forma_cents_de((int)$inv['gross_cents']) . "\nNetto: " . forma_cents_de((int)$inv['net_cents']) . "\nUSt. {$inv['vat_rate']} %: " . forma_cents_de((int)$inv['vat_cents']) . "\n\nAlle Buchungen: " . natal_admin_link() . "\n",
        null, [[$file, $pdf, 'application/pdf']]);
}

// --- Kennenlerngespräch (gratis, 30 Min., online per Video) ----------------------------------
// Mo–Fr um 8:00 oder 19:00, jeder Termin nur einmal buchbar, sofort fix, mit eigenem Whereby-Raum.

const NATAL_CALL_SLOTS = ['08:00', '19:00'];
const NATAL_CALL_MINUTES = 30;

function natal_call_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_natal_calls (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL,
        slot TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT \'\',
        note TEXT NOT NULL DEFAULT \'\',
        mode TEXT NOT NULL DEFAULT \'studio\',
        offer_id INTEGER,
        room_url TEXT NOT NULL DEFAULT \'\',
        host_url TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'gebucht\',
        token TEXT NOT NULL,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL
    )');
}

/** Ab wann ein Termin buchbar ist: mindestens 3 Stunden im Voraus, höchstens 6 Wochen. */
function natal_call_bookable(string $date, string $slot): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($slot, NATAL_CALL_SLOTS, true)) return false;
    $t = strtotime($date . ' ' . $slot);
    if (!$t || (int)date('N', $t) > 5) return false;
    return $t >= time() + 3 * 3600 && $t <= time() + 42 * 86400;
}

/** Belegte Termine als Liste „JJJJ-MM-TT|HH:MM“. */
function natal_call_taken(PDO $pdo): array
{
    natal_call_schema($pdo);
    $st = $pdo->prepare("SELECT date || '|' || slot FROM mm_natal_calls WHERE status = 'gebucht' AND date >= ?");
    $st->execute([date('Y-m-d')]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

function natal_call_when(array $c): string
{
    return natal_date_de((string)$c['date']) . ' um ' . ltrim((string)$c['slot'], '0') . ' Uhr';
}

/** Eigener Videoraum je Gespräch (Whereby, wenn eingerichtet), sonst der allgemeine Raum-Link. */
function natal_call_room(array $c): array
{
    $key = function_exists('mm_whereby_key') ? mm_whereby_key() : '';
    if ($key !== '') {
        try {
            $end = strtotime($c['date'] . ' ' . $c['slot']) + (NATAL_CALL_MINUTES + 60) * 60;
            $r = mm_whereby_request('POST', '/meetings', ['endDate' => gmdate('Y-m-d\TH:i:s.000\Z', $end), 'roomMode' => 'normal', 'roomNamePrefix' => 'movecia', 'fields' => ['hostRoomUrl']], $key);
            if (!empty($r['roomUrl'])) return [(string)$r['roomUrl'], (string)($r['hostRoomUrl'] ?? $r['roomUrl'])];
        } catch (Throwable $e) {
            error_log('Natal call room: ' . $e->getMessage());
        }
    }
    $room = trim((string)(natal_cfg()['room'] ?? ''));
    return [$room, $room];
}

function natal_call_ics(array $c): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $f = fn(DateTime $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $esc = fn(string $v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $v);
    $from = new DateTime($c['date'] . ' ' . $c['slot'], $tz);
    $to = (clone $from)->modify('+' . NATAL_CALL_MINUTES . ' minutes');
    $where = $c['room_url'] !== '' ? $c['room_url'] : 'Online per Video (Link per E-Mail)';
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Movecia//Kennenlernen//DE\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:natal-call-{$c['id']}@moveciamovement.com\r\nDTSTAMP:" . $f(new DateTime('now', $tz))
        . "\r\nDTSTART:" . $f($from) . "\r\nDTEND:" . $f($to) . "\r\nSUMMARY:" . $esc('Kennenlerngespräch mit Vivien (Movecia)') . "\r\nLOCATION:" . $esc($where)
        . "\r\nDESCRIPTION:" . $esc('30 Minuten, kostenlos und unverbindlich, online per Video. Zum Gespräch: ' . $where) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}

/** Bucht das Gespräch (in einer Transaktion, damit kein Termin doppelt vergeben wird) und schickt die Mails. */
function natal_book_call(PDO $pdo, array $in): array
{
    natal_call_schema($pdo);
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM mm_natal_calls WHERE date = ? AND slot = ? AND status = 'gebucht'");
        $q->execute([$in['date'], $in['slot']]);
        if ((int)$q->fetchColumn() > 0) { $pdo->exec('ROLLBACK'); return ['ok' => false, 'error' => 'Dieser Termin ist gerade vergeben worden. Bitte wähl einen anderen.']; }
        $pdo->prepare('INSERT INTO mm_natal_calls (date, slot, name, email, phone, note, mode, offer_id, token, ip_hash, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$in['date'], $in['slot'], $in['name'], $in['email'], $in['phone'], $in['note'], $in['mode'], $in['offer_id'], bin2hex(random_bytes(12)), $in['ip_hash'], date('c')]);
        $id = (int)$pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
        throw $e;
    }
    $c = $pdo->query('SELECT * FROM mm_natal_calls WHERE id = ' . $id)->fetch();
    [$room, $host] = natal_call_room($c);
    $pdo->prepare('UPDATE mm_natal_calls SET room_url = ?, host_url = ? WHERE id = ?')->execute([$room, $host, $id]);
    $c['room_url'] = $room; $c['host_url'] = $host;
    $first = forma_first_name((string)$c['name']);
    $p = ["Hallo $first,", 'schön, dass wir uns kennenlernen! Dein kostenloses Gespräch ist eingetragen:',
        [natal_call_when($c), '30 Minuten, online per Video'],
        $room !== '' ? 'Zum Gespräch tippst du einfach auf den Knopf unten, du brauchst keine App und kein Konto. Am besten mit Handy oder Laptop an einem ruhigen Platz.' : 'Den Link zum Videogespräch schicke ich dir rechtzeitig vor dem Termin.',
        'Wir sprechen darüber, wie es dir gerade geht, was du dir wünschst und welches Paket zu dir passt. Es ist ganz unverbindlich. Die angehängte Kalenderdatei übernimmt den Termin in deinen Kalender.',
        'Kommt etwas dazwischen, antworte einfach auf diese E-Mail.'];
    natal_mail((string)$c['email'], 'Dein Kennenlerngespräch: ' . natal_call_when($c), 'Wir lernen uns kennen', $p, $room !== '' ? [$room, 'Zum Videogespräch'] : null,
        [['Kennenlerngespraech-Movecia.ics', natal_call_ics($c), 'text/calendar; charset=utf-8; method=PUBLISH']]);
    $v = ['Neues Kennenlerngespräch (' . ($c['mode'] === 'online' ? 'Interesse online' : 'Interesse Studio') . '):', [natal_call_when($c), $c['name'] . ' · ' . $c['email'] . ($c['phone'] !== '' ? ' · ' . $c['phone'] : '')]];
    if ($c['note'] !== '') $v[] = 'Ihre Nachricht: ' . $c['note'];
    $v[] = $host !== '' ? 'Dein Link als Gastgeberin: ' . $host : 'Es gibt noch keinen Videoraum. Schick ihr bitte vor dem Termin einen Link.';
    $hostIcs = $c; $hostIcs['room_url'] = $host;
    natal_mail(natal_notify_email(), 'Kennenlerngespräch: ' . $c['name'] . ' · ' . natal_call_when($c), 'Neues Kennenlerngespräch', $v, [natal_admin_link(), 'In der Verwaltung'],
        [['Kennenlerngespraech-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string)$c['name']) . '.ics', natal_call_ics($hostIcs), 'text/calendar; charset=utf-8; method=PUBLISH']]);
    return ['ok' => true, 'when' => natal_call_when($c), 'room' => $room];
}

function natal_cancel_call(PDO $pdo, int $id, string $reason, bool $mail): void
{
    natal_call_schema($pdo);
    $c = $pdo->query('SELECT * FROM mm_natal_calls WHERE id = ' . $id)->fetch();
    if (!$c || $c['status'] !== 'gebucht') return;
    $pdo->prepare("UPDATE mm_natal_calls SET status = 'abgesagt' WHERE id = ?")->execute([$id]);
    if ($mail) {
        $link = mm_site() . ($c['mode'] === 'online' ? '/natal/online/' : '/natal/') . '#kennenlernen';
        natal_mail((string)$c['email'], 'Neuer Termin fürs Kennenlerngespräch', 'Lass uns einen neuen Termin finden',
            ['Hallo ' . forma_first_name((string)$c['name']) . ',', 'unser Gespräch am ' . natal_call_when($c) . ' geht sich leider nicht aus.' . ($reason !== '' ? "\n\n" . $reason : ''), 'Such dir gern einfach einen neuen Termin aus.'],
            [$link, 'Neuen Termin wählen']);
    }
}

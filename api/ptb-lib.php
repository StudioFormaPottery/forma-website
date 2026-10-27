<?php
// Movecia · Personal Training im Studio in Mathon, buchbar über moveciamovement.com/personal-training
// (versteckte Seite, nur per Link/QR vom Hotel-Flyer, Vivien 27.10.2026).
//
// Ablauf:
//  1. Gast wählt 1–4 Einheiten mit Wunschtag + Wunschuhrzeit, gibt Kontaktdaten an → Status „email“
//  2. Gast bestätigt die E-Mail-Adresse (Link in der Mail) → Status „anfrage“, Vivien bekommt eine Info
//  3. Vivien prüft in der Verwaltung (?view=training) jeden Wunsch: passt oder anderer Vorschlag → „An Gast schicken“
//     → Status „angebot“, Gast bekommt Mail mit allen Terminen und dem Bezahl-Knopf
//  4. Gast bezahlt alles auf einmal über Stripe → Status „bezahlt“, Termine fix, Mail mit Kalenderdatei, Rechnung
//  5. Eine Stunde nach der letzten Einheit: Bitte um eine Google-Bewertung (wartet, solange kein Bewertungslink hinterlegt ist)
//
// Preise (Vivien, 27.10.2026): 75 Min., € 85 pro Einheit allein, € 140 pro Einheit zu zweit (in der Verwaltung änderbar,
// dort auch ein optionaler Mengenpreis ab x Einheiten, Standard aus). Brutto inkl. 20 % USt.
// Keine 14-Tage-Rücktrittsfrist (Freizeit-Dienstleistung zu fixem Termin, § 18 Abs. 1 Z 10 FAGG), dafür kulant:
// bis 24 Std. vorher kostenlos verschieben oder absagen (Geld zurück).
declare(strict_types=1);
require_once __DIR__ . '/natal-lib.php';
require_once __DIR__ . '/belegt.inc.php';

const PTB_MAX_UNITS = 4;
const PTB_MINUTES = 75;
const PTB_WD_DE = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
const PTB_WD_EN = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
const PTB_MON_EN = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

function ptb_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    natal_schema($pdo);
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_ptb_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        units INTEGER NOT NULL DEFAULT 0,
        unit_cents INTEGER NOT NULL DEFAULT 0,
        amount_cents INTEGER NOT NULL DEFAULT 0,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT \'\',
        hotel TEXT NOT NULL DEFAULT \'\',
        note TEXT NOT NULL DEFAULT \'\',
        lang TEXT NOT NULL DEFAULT \'de\',
        status TEXT NOT NULL DEFAULT \'email\',
        token TEXT NOT NULL,
        email_token TEXT NOT NULL,
        email_ok_at TEXT,
        offer_note TEXT NOT NULL DEFAULT \'\',
        offered_at TEXT,
        stripe_session TEXT NOT NULL DEFAULT \'\',
        paid_at TEXT,
        decline_note TEXT NOT NULL DEFAULT \'\',
        ip_hash TEXT NOT NULL DEFAULT \'\',
        consent_terms INTEGER NOT NULL DEFAULT 0,
        review_sent_at TEXT,
        is_test INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_ptb_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL REFERENCES mm_ptb_orders(id) ON DELETE CASCADE,
        pos INTEGER NOT NULL DEFAULT 0,
        wish_date TEXT NOT NULL,
        wish_time TEXT NOT NULL,
        date TEXT NOT NULL,
        start TEXT NOT NULL,
        minutes INTEGER NOT NULL DEFAULT 75,
        status TEXT NOT NULL DEFAULT \'offen\',
        created_at TEXT NOT NULL
    )');
    $cols = array_column($pdo->query('PRAGMA table_info(mm_ptb_orders)')->fetchAll(), 'name');
    if (!in_array('persons', $cols, true)) $pdo->exec('ALTER TABLE mm_ptb_orders ADD COLUMN persons INTEGER NOT NULL DEFAULT 1');
}

// --- Einstellungen -------------------------------------------------------------------------

/** Einstellungen mit Standardwerten. Preise in Cent, brutto. */
function ptb_cfg(): array
{
    $c = mm_cfg('ptb') ?? [];
    return [
        'unit' => max(100, (int)($c['unit'] ?? 8500)),
        'unit2' => max(100, (int)($c['unit2'] ?? 14000)),
        'disc_from' => max(2, min(PTB_MAX_UNITS, (int)($c['disc_from'] ?? 4))),
        'disc_unit' => max(0, (int)($c['disc_unit'] ?? 0)),
        'weekdays' => array_values(array_filter(array_map('intval', (array)($c['weekdays'] ?? [1, 2, 3, 4, 5, 6])), fn($d) => $d >= 1 && $d <= 7)) ?: [1, 2, 3, 4, 5, 6],
        'from' => preg_match('/^\d{2}:\d{2}$/', (string)($c['from'] ?? '')) ? $c['from'] : '07:00',
        'to' => preg_match('/^\d{2}:\d{2}$/', (string)($c['to'] ?? '')) ? $c['to'] : '19:00',
        'blocked' => array_values(array_filter((array)($c['blocked'] ?? []), fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d))),
    ];
}

/** Preis pro Einheit bei $n Einheiten für $p Personen (1 oder 2). Mengenpreis nur allein und nur, wenn eingestellt. */
function ptb_unit_cents(int $n, int $p = 1): int
{
    $c = ptb_cfg();
    if ($p >= 2) return $c['unit2'];
    return $c['disc_unit'] > 0 && $c['disc_unit'] < $c['unit'] && $n >= $c['disc_from'] ? $c['disc_unit'] : $c['unit'];
}

/** Alle Preisstufen für die Seite: [n => [unit, total]]. */
function ptb_price_table(int $p = 1): array
{
    $out = [];
    for ($n = 1; $n <= PTB_MAX_UNITS; $n++) $out[$n] = ['unit' => ptb_unit_cents($n, $p), 'total' => ptb_unit_cents($n, $p) * $n];
    return $out;
}

/** Personen einer Buchung (1 oder 2). */
function ptb_persons(array $o): int
{
    return (int)($o['persons'] ?? 1) >= 2 ? 2 : 1;
}

/** „75 Min., 1:1“ bzw. „75 Min., zu zweit“. */
function ptb_kind(array $o, bool $en, bool $long = false): string
{
    $two = ptb_persons($o) === 2;
    if ($en) return PTB_MINUTES . ($long ? ' minutes' : ' min') . ', ' . ($two ? 'for two' : '1:1');
    return PTB_MINUTES . ($long ? ' Minuten' : ' Min.') . ', ' . ($two ? 'zu zweit' : '1:1');
}

/** Mögliche Startzeiten (halbstündlich), letzte Einheit beginnt spätestens zur Endzeit. */
function ptb_times(): array
{
    $c = ptb_cfg();
    $out = [];
    for ($t = strtotime('2000-01-01 ' . $c['from']); $t <= strtotime('2000-01-01 ' . $c['to']); $t += 1800) $out[] = date('H:i', $t);
    return $out;
}

// --- Daten ---------------------------------------------------------------------------------

function ptb_order(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mm_ptb_orders WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function ptb_order_auth(PDO $pdo, int $id, string $tok): ?array
{
    $o = ptb_order($pdo, $id);
    return $o && $tok !== '' && hash_equals((string)$o['token'], $tok) ? $o : null;
}

function ptb_sessions(PDO $pdo, int $oid, bool $active = false): array
{
    $st = $pdo->prepare('SELECT * FROM mm_ptb_sessions WHERE order_id = ?' . ($active ? " AND status <> 'gestrichen'" : '') . ' ORDER BY date, start, id');
    $st->execute([$oid]);
    return $st->fetchAll();
}

function ptb_link(array $o): string
{
    return mm_site() . '/personal-training/?o=' . (int)$o['id'] . '&k=' . $o['token'] . ((string)$o['lang'] === 'en' ? '&lang=en' : '');
}

function ptb_admin_link(?array $o = null): string
{
    return forma_site_url() . '/verwaltung/?view=training' . ($o ? '#t' . (int)$o['id'] : '');
}

function ptb_en(array $o): bool
{
    return (string)($o['lang'] ?? 'de') === 'en';
}

// --- Datum & Text --------------------------------------------------------------------------

function ptb_date(string $ymd, bool $en = false, bool $year = false): string
{
    $t = strtotime($ymd);
    if (!$t) return $ymd;
    if ($en) return PTB_WD_EN[(int)date('N', $t)] . ' ' . date('j', $t) . ' ' . PTB_MON_EN[(int)date('n', $t)] . ($year ? ' ' . date('Y', $t) : '');
    return PTB_WD_DE[(int)date('N', $t)] . ', ' . date('j. n.', $t) . ($year ? ' ' . date('Y', $t) : '');
}

function ptb_time(string $hm, bool $en = false): string
{
    $hm = substr($hm, 0, 5);
    if ($en) { $t = strtotime('2000-01-01 ' . $hm); return date('g', $t) . (date('i', $t) !== '00' ? ':' . date('i', $t) : '') . (date('H', $t) < 12 ? 'am' : 'pm'); }
    return ltrim($hm, '0') . ' Uhr';
}

function ptb_when(string $date, string $start, bool $en = false): string
{
    return ptb_date($date, $en) . ($en ? ' at ' : ' um ') . ptb_time($start, $en);
}

function ptb_status_label(string $s): string
{
    return ['email' => 'E-Mail noch nicht bestätigt', 'anfrage' => 'Neue Anfrage', 'angebot' => 'Wartet auf Zahlung', 'bezahlt' => 'Bezahlt', 'abgesagt' => 'Abgesagt', 'abgelaufen' => 'Abgelaufen'][$s] ?? $s;
}

/** Beginn und Ende eines Termins als Zeitstempel (Wien). */
function ptb_span(string $date, string $start, int $min = PTB_MINUTES): array
{
    $from = strtotime($date . ' ' . $start);
    return [$from, $from + $min * 60];
}

// --- Belegte Zeiten (damit Gäste nichts wählen, was schon vergeben ist) --------------------

/** Liefert [datum => [[von, bis], …]] in Minuten ab Mitternacht für bestätigte/angebotene Termine (PT + Prä-/Postnatal). */
function ptb_busy(PDO $pdo, string $from, string $to, int $exceptOrder = 0): array
{
    $busy = [];
    $add = function (string $d, string $s, int $m) use (&$busy) {
        if ($s === '' || !preg_match('/^\d{1,2}:\d{2}/', $s)) return;
        [$h, $i] = array_map('intval', explode(':', substr($s, 0, 5)));
        $busy[$d][] = [$h * 60 + $i, $h * 60 + $i + $m];
    };
    $st = $pdo->prepare("SELECT s.date, s.start, s.minutes FROM mm_ptb_sessions s JOIN mm_ptb_orders o ON o.id = s.order_id
        WHERE s.date BETWEEN ? AND ? AND o.id <> ? AND ((o.status = 'bezahlt' AND s.status IN ('bestaetigt','erledigt')) OR (o.status = 'angebot' AND s.status IN ('passt','vorschlag')))");
    $st->execute([$from, $to, $exceptOrder]);
    foreach ($st->fetchAll() as $r) $add($r['date'], $r['start'], (int)$r['minutes']);
    // alles andere aus dem gemeinsamen Kalender: FORMA-Kurse mit Anmeldungen, Private Pottery, Prä-/Postnatal, Kennenlerngespräche
    foreach (vb_busy($pdo, $from, $to, ['pt', 'pt-wait']) as $d => $list) foreach ($list as $x) $busy[$d][] = $x;
    return $busy;
}

/** FORMA-Kurstermine im Zeitraum mit Uhrzeit (aus „9–11 Uhr“ im Termintext). $booked = nur mit Anmeldungen. */
function ptb_forma_courses(PDO $pdo, string $from, string $to, bool $booked = false): array
{
    $out = [];
    try {
        $st = $pdo->prepare("SELECT c.id, c.title, c.date_text, c.start_date, (SELECT COALESCE(SUM(b.persons),0) FROM bookings b WHERE b.course_id = c.id AND b.status IN ('bestaetigt','offen')) AS n
            FROM courses c WHERE c.start_date BETWEEN ? AND ? ORDER BY c.start_date");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $c) {
            if ($booked && (int)$c['n'] <= 0) continue;
            if (str_contains(mb_strtolower((string)$c['title']), 'woche')) continue;
            if (!preg_match('/(\d{1,2})(?:[:.](\d{2}))?\s*[–-]\s*(\d{1,2})(?:[:.](\d{2}))?\s*Uhr/u', (string)$c['date_text'], $m)) continue;
            $out[] = ['date' => substr((string)$c['start_date'], 0, 10), 'a' => (int)$m[1] * 60 + (int)($m[2] ?? 0), 'b' => (int)$m[3] * 60 + (int)($m[4] ?? 0), 'title' => (string)$c['title'], 'n' => (int)$c['n'], 'id' => (int)$c['id']];
        }
    } catch (Throwable $e) {}
    return $out;
}

/** Alles, was an einem Tag schon im Kalender steht (für die Verwaltung): [[von, bis, Text, Art], …] sortiert. */
function ptb_day_items(PDO $pdo, string $date, int $exceptOrder = 0): array
{
    $items = [];
    $st = $pdo->prepare("SELECT s.*, o.name, o.status AS ost FROM mm_ptb_sessions s JOIN mm_ptb_orders o ON o.id = s.order_id
        WHERE s.date = ? AND o.id <> ? AND ((o.status = 'bezahlt' AND s.status IN ('bestaetigt','erledigt')) OR (o.status = 'angebot' AND s.status IN ('passt','vorschlag')) OR (o.status = 'anfrage' AND s.status <> 'gestrichen'))");
    $st->execute([$date, $exceptOrder]);
    foreach ($st->fetchAll() as $s) {
        $kind = $s['ost'] === 'bezahlt' ? 'pt' : ($s['ost'] === 'angebot' ? 'pt-wait' : 'pt-wish');
        $items[] = [substr((string)$s['start'], 0, 5), date('H:i', strtotime($s['date'] . ' ' . $s['start']) + (int)$s['minutes'] * 60), 'Personal Training · ' . $s['name'] . ($kind === 'pt-wait' ? ' (wartet auf Zahlung)' : ($kind === 'pt-wish' ? ' (Anfrage)' : '')), $kind];
    }
    try {
        $st = $pdo->prepare("SELECT s.*, o.name FROM mm_natal_sessions s JOIN mm_natal_orders o ON o.id = s.order_id WHERE s.date = ? AND s.status IN ('bestaetigt','erledigt','angefragt')");
        $st->execute([$date]);
        foreach ($st->fetchAll() as $s) {
            if ((string)$s['start'] === '') { $items[] = ['', '', 'Prä-/Postnatal · ' . $s['name'] . ' (Wunsch: ' . (NATAL_DAYPARTS[$s['daypart']] ?? 'ohne Zeit') . ')', 'natal-wish']; continue; }
            $items[] = [substr((string)$s['start'], 0, 5), date('H:i', strtotime($s['date'] . ' ' . $s['start']) + (int)$s['minutes'] * 60), 'Prä-/Postnatal · ' . $s['name'], 'natal'];
        }
    } catch (Throwable $e) {}
    foreach (ptb_forma_courses($pdo, $date, $date) as $c) {
        $items[] = [sprintf('%02d:%02d', intdiv($c['a'], 60), $c['a'] % 60), sprintf('%02d:%02d', intdiv($c['b'], 60), $c['b'] % 60), 'FORMA · ' . $c['title'] . ' (' . ($c['n'] ? $c['n'] . ' angemeldet' : 'noch niemand angemeldet') . ')', $c['n'] ? 'forma' : 'forma-leer'];
    }
    usort($items, fn($x, $y) => strcmp($x[0] ?: '99', $y[0] ?: '99'));
    return $items;
}

/** Ist der Start an diesem Tag frei? (inkl. 15 Min. Puffer vor und nach anderen Terminen) */
function ptb_slot_free(array $busy, string $date, string $start, int $min = PTB_MINUTES): bool
{
    [$h, $i] = array_map('intval', explode(':', substr($start, 0, 5)));
    $a = $h * 60 + $i; $b = $a + $min;
    foreach ($busy[$date] ?? [] as [$x, $y]) if ($a < $y + 15 && $b > $x - 15) return false;
    return true;
}

/** Wunschtermine prüfen: ab morgen, bis 6 Monate, erlaubter Wochentag, erlaubte Uhrzeit, nicht gesperrt, nicht belegt, keine Doppelten. */
function ptb_clean_wishes(PDO $pdo, $raw): array
{
    if (is_string($raw)) $raw = json_decode($raw, true);
    if (!is_array($raw)) return [];
    $c = ptb_cfg();
    $times = ptb_times();
    $min = date('Y-m-d', strtotime('+1 day'));
    $max = date('Y-m-d', strtotime('+6 months'));
    $busy = ptb_busy($pdo, $min, $max);
    $out = [];
    foreach ($raw as $w) {
        $d = (string)($w['date'] ?? '');
        $t = substr((string)($w['time'] ?? ''), 0, 5);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d) || $d < $min || $d > $max) continue;
        if (!in_array((int)date('N', strtotime($d)), $c['weekdays'], true) || in_array($d, $c['blocked'], true)) continue;
        if (!in_array($t, $times, true) || !ptb_slot_free($busy, $d, $t)) continue;
        // zwei Wünsche dürfen sich nicht überschneiden
        $clash = false;
        foreach ($out as $o) if ($o['date'] === $d && abs(strtotime("$d $t") - strtotime($o['date'] . ' ' . $o['time'])) < PTB_MINUTES * 60) $clash = true;
        if ($clash) continue;
        $out[] = ['date' => $d, 'time' => $t];
        if (count($out) >= PTB_MAX_UNITS) break;
    }
    usort($out, fn($a, $b) => strcmp($a['date'] . $a['time'], $b['date'] . $b['time']));
    return $out;
}

// --- Mails ---------------------------------------------------------------------------------

/** HTML-Rahmen im Movecia-Design, Gruß passend zur Sprache. */
function ptb_mail_html(string $title, string $inner, bool $en): string
{
    return '<!DOCTYPE html><html lang="' . ($en ? 'en' : 'de') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . forma_h($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F2ECE4;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F2ECE4;"><tr><td align="center" style="padding:26px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FBF8F4;border-radius:24px;font-family:Helvetica,Arial,sans-serif;color:#2E2529;">'
        . '<tr><td style="padding:24px 30px 0;"><img src="' . forma_h(mm_site()) . '/img/movecia/movecia-mail.png" width="180" height="40" alt="Movecia." style="display:block;border:0;width:180px;height:auto;"></td></tr>'
        . '<tr><td style="padding:14px 30px 28px;font-size:16px;line-height:1.65;">' . $inner
        . '<p style="margin:24px 0 0;">' . ($en ? 'See you soon,<br>Vivien' : 'Bis bald,<br>Vivien') . '</p></td></tr></table>'
        . '<p style="max-width:560px;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#7D7076;margin:16px auto 0;">Vivien Malonga · Movecia – Personal Fitness · Mathon, Paznaun · ' . NATAL_COMPANY['email'] . ' · ' . NATAL_COMPANY['phone'] . '</p>'
        . '</td></tr></table></body></html>';
}

/** Mail an den Gast in seiner Sprache. $paras: Strings oder Arrays (= hervorgehobener Kasten mit Zeilen). */
function ptb_mail_guest(array $o, string $subject, string $title, array $paras, ?array $button = null, array $attachments = []): bool
{
    $en = ptb_en($o);
    $text = implode("\n\n", array_map(fn($p) => is_array($p) ? implode("\n", $p) : $p, $paras));
    if ($button) $text .= "\n\n" . $button[1] . ': ' . $button[0];
    $text .= "\n\n" . ($en ? 'See you soon,' : 'Bis bald,') . "\nVivien\n\nVivien Malonga · Movecia – Personal Fitness · " . NATAL_COMPANY['email'] . ' · ' . NATAL_COMPANY['phone'];
    $html = natal_h1($title);
    foreach ($paras as $p) {
        $html .= is_array($p)
            ? '<p style="margin:0 0 14px;padding:12px 16px;background:#F2ECE4;border-radius:14px;">' . implode('<br>', array_map(fn($l) => str_starts_with($l, '**') ? '<b>' . forma_h(substr($l, 2)) . '</b>' : forma_h($l), $p)) . '</p>'
            : '<p style="margin:0 0 14px;">' . nl2br(forma_h($p)) . '</p>';
    }
    if ($button) $html .= natal_btn($button[0], $button[1]);
    $text = str_replace("\n**", "\n", $text);
    return mm_mail((string)$o['email'], $subject, $text, ptb_mail_html($title, $html, $en), $attachments, NATAL_FROM_NAME);
}

/** Bestätigungsmail für die E-Mail-Adresse (Schritt 1). */
function ptb_mail_confirm(array $o): bool
{
    $en = ptb_en($o);
    $first = forma_first_name((string)$o['name']);
    $url = mm_site() . '/api/ptb.php?bestaetigen=' . $o['email_token'];
    return $en
        ? ptb_mail_guest($o, 'Please confirm your email · Personal training at Movecia', 'One quick tap', ["Hi $first,", 'thank you for your request! Please confirm your email address with the button below so I know it has really reached you. Only then can I check your preferred times and get back to you.', "If you didn't send this request, you can simply ignore this email."], [$url, 'Confirm my email'])
        : ptb_mail_guest($o, 'Bitte bestätige deine E-Mail-Adresse · Personal Training bei Movecia', 'Nur noch ein Tipp', ["Hallo $first,", 'danke für deine Anfrage! Bitte bestätige kurz deine E-Mail-Adresse mit dem Knopf unten, damit ich weiß, dass sie wirklich bei dir ankommt. Erst dann schaue ich mir deine Wunschtermine an und melde mich bei dir.', 'Falls du diese Anfrage nicht geschickt hast, kannst du diese Mail einfach ignorieren.'], [$url, 'E-Mail-Adresse bestätigen']);
}

/** Zeilen mit den Terminen für Mails (mit Hinweis, ob Wunsch oder neuer Vorschlag). */
function ptb_session_lines(array $sessions, bool $en, bool $marks = true): array
{
    $out = [];
    foreach ($sessions as $s) {
        if ($s['status'] === 'gestrichen') continue;
        $line = ptb_when((string)$s['date'], (string)$s['start'], $en);
        if ($marks && $s['status'] === 'vorschlag') $line .= $en ? '  (new suggestion, instead of ' . ptb_when((string)$s['wish_date'], (string)$s['wish_time'], true) . ')' : '  (neuer Vorschlag statt ' . ptb_when((string)$s['wish_date'], (string)$s['wish_time']) . ')';
        $out[] = $line;
    }
    return $out;
}

// --- Schritte ------------------------------------------------------------------------------

/** E-Mail-Adresse bestätigt → Anfrage geht an Vivien. Liefert die Buchung oder null. */
function ptb_email_ok(PDO $pdo, string $tok): ?array
{
    if (strlen($tok) < 20) return null;
    $st = $pdo->prepare('SELECT * FROM mm_ptb_orders WHERE email_token = ?');
    $st->execute([$tok]);
    $o = $st->fetch();
    if (!$o) return null;
    $u = $pdo->prepare("UPDATE mm_ptb_orders SET status = 'anfrage', email_ok_at = ? WHERE id = ? AND status = 'email'");
    $u->execute([date('c'), $o['id']]);
    if ($u->rowCount() !== 1) return $o;
    $o = ptb_order($pdo, (int)$o['id']);
    $en = ptb_en($o);
    $first = forma_first_name((string)$o['name']);
    $lines = array_map(fn($s) => ptb_when((string)$s['wish_date'], (string)$s['wish_time'], $en), ptb_sessions($pdo, (int)$o['id']));
    $en
        ? ptb_mail_guest($o, 'Your request has arrived · Personal training at Movecia', 'Thank you, ' . $first, ["Hi $first,", 'your email is confirmed and your request is with me. These are your preferred times:', $lines, "I'll check my calendar and get back to you as soon as I can, usually within a day. If a time doesn't work, I'll suggest another one. You only pay once your times are confirmed."], [ptb_link($o), 'View my request'])
        : ptb_mail_guest($o, 'Deine Anfrage ist angekommen · Personal Training bei Movecia', 'Danke, ' . $first, ["Hallo $first,", 'deine E-Mail-Adresse ist bestätigt und deine Anfrage ist bei mir. Das sind deine Wunschtermine:', $lines, 'Ich schaue in meinen Kalender und melde mich so schnell wie möglich, meistens innerhalb eines Tages. Passt ein Termin nicht, schlage ich dir einen anderen vor. Bezahlen musst du erst, wenn deine Termine bestätigt sind.'], [ptb_link($o), 'Meine Anfrage ansehen']);
    $v = ['Neue Anfrage Personal Training:', [
        $o['name'] . ' · ' . $o['email'] . ($o['phone'] !== '' ? ' · ' . $o['phone'] : ''),
        (ptb_persons($o) === 2 ? 'Zu zweit (2 Personen) · ' : 'Allein (1:1) · ') . ($o['hotel'] !== '' ? 'Unterkunft: ' . $o['hotel'] . ' · ' : '') . 'Sprache: ' . ($en ? 'Englisch' : 'Deutsch'),
    ], "Wunschtermine:\n– " . implode("\n– ", array_map(fn($s) => ptb_when((string)$s['wish_date'], (string)$s['wish_time']), ptb_sessions($pdo, (int)$o['id'])))];
    if ($o['note'] !== '') $v[] = 'Nachricht: ' . $o['note'];
    $v[] = 'In der Verwaltung tippst du bei jedem Termin auf „passt“ oder schlägst eine andere Zeit vor, dann „An Gast schicken“. Der Gast bezahlt danach online, erst dann sind die Termine fix.';
    natal_mail(natal_notify_email(), 'Neue Anfrage Personal Training: ' . $o['name'] . ' (' . count($lines) . ($en ? ', EN' : '') . ')', 'Neue Anfrage', $v, [ptb_admin_link($o), 'Termine bestätigen']);
    return $o;
}

/** Vivien schickt die geprüften Termine an den Gast (mit Bezahl-Knopf). */
function ptb_offer_send(PDO $pdo, array $o, string $note = ''): array
{
    if (!in_array($o['status'], ['anfrage', 'angebot'], true)) return ['ok' => false, 'error' => 'Diese Anfrage ist nicht mehr offen.'];
    $ss = ptb_sessions($pdo, (int)$o['id']);
    $act = array_values(array_filter($ss, fn($s) => $s['status'] !== 'gestrichen'));
    if (!$act) return ['ok' => false, 'error' => 'Es ist kein Termin mehr übrig. Sag die Anfrage lieber ab.'];
    if (array_filter($act, fn($s) => $s['status'] === 'offen')) return ['ok' => false, 'error' => 'Bitte tipp zuerst bei jedem Termin auf „passt“ oder trag eine andere Zeit ein.'];
    $n = count($act);
    $unit = ptb_unit_cents($n, ptb_persons($o));
    $pdo->prepare("UPDATE mm_ptb_orders SET status = 'angebot', units = ?, unit_cents = ?, amount_cents = ?, offer_note = ?, offered_at = ?, stripe_session = '' WHERE id = ?")
        ->execute([$n, $unit, $unit * $n, mb_substr($note, 0, 800), date('c'), $o['id']]);
    $o = ptb_order($pdo, (int)$o['id']);
    $en = ptb_en($o);
    $first = forma_first_name((string)$o['name']);
    $changed = (bool)array_filter($act, fn($s) => $s['status'] === 'vorschlag');
    $dropped = count($ss) - $n;
    $box = array_merge(ptb_session_lines($ss, $en), ['', '**' . ($en ? $n . ($n === 1 ? ' session' : ' sessions') . ' × ' . ptb_kind($o, true) . ' · ' . forma_euro($unit * $n) . ($n > 1 ? ' (' . forma_euro($unit) . ' each)' : '') : $n . ($n === 1 ? ' Einheit' : ' Einheiten') . ' à ' . ptb_kind($o, false) . ' · ' . forma_euro($unit * $n) . ($n > 1 ? ' (je ' . forma_euro($unit) . ')' : ''))]);
    if ($en) {
        $p = ["Hi $first,", $changed ? "I've looked at your preferred times. Not all of them work for me, so I've suggested a different time where needed:" : 'good news: your times work for me!', $box];
        if ($dropped > 0) $p[] = "Unfortunately I couldn't fit in every session, so I've only listed the times that work.";
        if ($note !== '') $p[] = $note;
        $p[] = "To book, simply pay online. Once your payment is through, the times are fixed for you and you'll get a confirmation with a calendar file. If a time doesn't suit you, just reply to this email or message me on WhatsApp (" . NATAL_COMPANY['phone'] . ') and we\'ll find another.';
        $p[] = 'I can only hold these times for a little while until you pay, so the sooner the better.';
        $ok = ptb_mail_guest($o, $changed ? 'Your times for personal training (with a new suggestion)' : 'Your times are free · Personal training at Movecia', $changed ? 'Your times' : 'Your times are free', $p, [ptb_link($o), 'Book & pay ' . forma_euro($unit * $n)]);
    } else {
        $p = ["Hallo $first,", $changed ? 'ich habe mir deine Wunschtermine angeschaut. Nicht alle gehen sich bei mir aus, deshalb habe ich dir dort eine andere Zeit vorgeschlagen:' : 'schöne Nachricht: Deine Wunschtermine passen!', $box];
        if ($dropped > 0) $p[] = 'Leider kann ich nicht alle Einheiten unterbringen, deshalb stehen hier nur die Termine, die sich ausgehen.';
        if ($note !== '') $p[] = $note;
        $p[] = 'Zum Buchen bezahlst du einfach online. Sobald die Zahlung da ist, sind die Termine fix für dich reserviert und du bekommst die Bestätigung mit Kalenderdatei. Passt dir eine Zeit nicht, antworte einfach auf diese Mail oder schreib mir auf WhatsApp (' . NATAL_COMPANY['phone'] . '), dann finden wir eine andere.';
        $p[] = 'Bis zur Zahlung kann ich die Termine nur eine Weile für dich freihalten, also am besten bald.';
        $ok = ptb_mail_guest($o, $changed ? 'Deine Termine fürs Personal Training (mit neuem Vorschlag)' : 'Deine Termine sind frei · Personal Training bei Movecia', $changed ? 'Deine Termine' : 'Deine Termine sind frei', $p, [ptb_link($o), 'Jetzt buchen & bezahlen ' . forma_euro($unit * $n)]);
    }
    return ['ok' => true, 'mailed' => $ok, 'order' => $o];
}

/** Anfrage absagen (nichts frei). */
function ptb_decline(PDO $pdo, array $o, string $note, bool $mail = true): void
{
    $pdo->prepare("UPDATE mm_ptb_orders SET status = 'abgesagt', decline_note = ? WHERE id = ? AND status <> 'bezahlt'")->execute([mb_substr($note, 0, 800), $o['id']]);
    if (!$mail) return;
    $en = ptb_en($o);
    $first = forma_first_name((string)$o['name']);
    $new = mm_site() . '/personal-training/' . ($en ? '?lang=en' : '');
    $en
        ? ptb_mail_guest($o, 'About your personal training request', "I'm so sorry", ["Hi $first,", "thank you for your request. Unfortunately I can't offer you any sessions at those times." . ($note !== '' ? "\n\n" . $note : ''), "If you'd like to try other days, you're very welcome to send a new request."], [$new, 'Choose new times'])
        : ptb_mail_guest($o, 'Zu deiner Anfrage fürs Personal Training', 'Es tut mir leid', ["Hallo $first,", 'danke für deine Anfrage. Leider kann ich dir zu diesen Zeiten keine Einheit anbieten.' . ($note !== '' ? "\n\n" . $note : ''), 'Wenn du andere Tage ausprobieren möchtest, schick mir gern eine neue Anfrage.'], [$new, 'Neue Termine wählen']);
}

// --- Stripe --------------------------------------------------------------------------------

function ptb_checkout(PDO $pdo, array $o): string
{
    $site = mm_site();
    $en = ptb_en($o);
    $ss = ptb_sessions($pdo, (int)$o['id'], true);
    $dates = implode(', ', array_map(fn($s) => ptb_when((string)$s['date'], (string)$s['start'], $en), $ss));
    $params = [
        'mode' => 'payment',
        'locale' => $en ? 'en' : 'de',
        'customer_email' => $o['email'],
        'client_reference_id' => 'ptb-' . $o['id'],
        'billing_address_collection' => 'required',
        'metadata' => ['bereich' => 'Movecia', 'ptb_order_id' => (string)$o['id']],
        'line_items' => [[
            'quantity' => (int)$o['units'],
            'price_data' => ['currency' => 'eur', 'unit_amount' => (int)$o['unit_cents'], 'product_data' => [
                'name' => 'Movecia · Personal Training ' . (ptb_persons($o) === 2 ? 'zu zweit' : '1:1') . ' (' . PTB_MINUTES . ' Min.)',
                'description' => mb_substr($dates, 0, 480) . ' · Mathon',
            ]],
        ]],
        'payment_intent_data' => ['description' => 'Movecia · Personal Training · ' . $o['name'], 'metadata' => ['bereich' => 'Movecia', 'ptb_order_id' => (string)$o['id']]],
        'custom_text' => ['submit' => ['message' => $en
            ? 'Service by Movecia – Personal Fitness (Vivien Malonga). Payment goes through the shared account of FORMA & Movecia. Free rescheduling or cancellation up to 24 hours before a session. Terms: ' . $site . '/terms?lang=en#training'
            : 'Leistung von Movecia – Personal Fitness (Vivien Malonga). Die Zahlung läuft über das gemeinsame Konto von FORMA & Movecia. Bis 24 Stunden vorher kostenlos verschieben oder absagen. Bedingungen: ' . $site . '/terms?lang=de#training']],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/ptb-bezahlt.php?o=' . $o['id'] . '&k=' . $o['token'],
        'cancel_url' => ptb_link($o) . '&abgebrochen=1',
    ];
    $branded = $params;
    $branded['branding_settings'] = ['display_name' => 'Movecia – Personal Fitness', 'background_color' => '#F2ECE4', 'button_color' => '#7D5E68', 'border_style' => 'rounded', 'font_family' => 'inter'];
    $branded['payment_intent_data']['statement_descriptor_suffix'] = 'MOVECIA';
    try {
        $s = forma_stripe('POST', '/v1/checkout/sessions', $branded, null, '2025-09-30.clover');
    } catch (Throwable $e) {
        error_log('PTB checkout (branded): ' . $e->getMessage());
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params);
    }
    $pdo->prepare('UPDATE mm_ptb_orders SET stripe_session = ? WHERE id = ?')->execute([(string)$s['id'], $o['id']]);
    return (string)$s['url'];
}

/** Zahlung prüfen. Genau einmal: Termine fix, Mails mit Kalenderdatei, Rechnung. */
function ptb_check_paid(PDO $pdo, array $o): string
{
    if ($o['status'] !== 'angebot' || (string)$o['stripe_session'] === '' || !forma_stripe_key()) return (string)$o['status'];
    try {
        $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$o['stripe_session']));
    } catch (Throwable $e) {
        error_log('PTB check: ' . $e->getMessage());
        return 'angebot';
    }
    if (($s['payment_status'] ?? '') !== 'paid') return 'angebot';
    $u = $pdo->prepare("UPDATE mm_ptb_orders SET status = 'bezahlt', paid_at = ? WHERE id = ? AND status = 'angebot'");
    $u->execute([date('c'), $o['id']]);
    if ($u->rowCount() !== 1) return 'bezahlt';
    $pdo->prepare("UPDATE mm_ptb_sessions SET status = 'bestaetigt' WHERE order_id = ? AND status IN ('passt','vorschlag')")->execute([$o['id']]);
    $o = ptb_order($pdo, (int)$o['id']);
    ptb_paid_mails($pdo, $o);
    if (function_exists('forma_invoice_for')) forma_invoice_for($pdo, 'ptb', (int)$o['id']);
    return 'bezahlt';
}

function ptb_paid_mails(PDO $pdo, array $o): void
{
    $en = ptb_en($o);
    $first = forma_first_name((string)$o['name']);
    $ss = ptb_sessions($pdo, (int)$o['id'], true);
    $addr = NATAL_COMPANY['street'] . ', ' . NATAL_COMPANY['city'];
    $box = array_merge(ptb_session_lines($ss, $en, false), ['', '**' . ($en ? 'Movecia Studio · ' . $addr : 'Movecia Studio · ' . $addr)]);
    $ics = [['Movecia-Personal-Training.ics', ptb_ics($ss, $en), 'text/calendar; charset=utf-8; method=PUBLISH']];
    $en
        ? ptb_mail_guest($o, 'Booked! Your personal training at Movecia', "You're booked", ["Hi $first,", 'thank you, your payment has arrived and your sessions are fixed:', $box,
            'Come in comfortable clothes and bring a bottle of water. Mats, weights and everything else are ready in the studio. If you have any injuries or health concerns, please let me know before your first session.',
            'With the attached calendar file you can add all sessions to your calendar in one tap. Your invoice comes in a separate email.',
            'Need to change something? You can reschedule or cancel free of charge up to 24 hours before a session, just reply to this email or message me on WhatsApp (' . NATAL_COMPANY['phone'] . ').',
            "I'm looking forward to training with you!"], [ptb_link($o), 'My sessions'], $ics)
        : ptb_mail_guest($o, 'Gebucht! Dein Personal Training bei Movecia', 'Du bist gebucht', ["Hallo $first,", 'danke, deine Zahlung ist angekommen und deine Termine sind fix:', $box,
            'Komm in bequemer Kleidung und bring eine Flasche Wasser mit. Matten, Gewichte und alles andere liegen im Studio bereit. Wenn du Verletzungen oder gesundheitliche Beschwerden hast, sag mir bitte vor der ersten Einheit Bescheid.',
            'Mit der angehängten Kalenderdatei übernimmst du alle Termine mit einem Tipp in deinen Kalender. Die Rechnung bekommst du in einer eigenen E-Mail.',
            'Musst du etwas ändern? Bis 24 Stunden vor einem Termin kannst du kostenlos verschieben oder absagen. Antworte einfach auf diese Mail oder schreib mir auf WhatsApp (' . NATAL_COMPANY['phone'] . ').',
            'Ich freue mich auf dich!'], [ptb_link($o), 'Meine Termine'], $ics);
    $v = ['Bezahlt und fix:', [$o['name'] . ' · ' . $o['email'] . ($o['phone'] !== '' ? ' · ' . $o['phone'] : ''), (int)$o['units'] . ' × ' . ptb_kind($o, false) . ' · ' . forma_euro((int)$o['amount_cents']) . ' (online bezahlt)'],
        "Termine:\n– " . implode("\n– ", ptb_session_lines($ss, false, false))];
    natal_mail(natal_notify_email(), 'Bezahlt: Personal Training ' . $o['name'] . ' · ' . forma_euro((int)$o['amount_cents']), 'Gebucht und bezahlt', $v, [ptb_admin_link($o), 'In der Verwaltung'], [['Personal-Training-' . preg_replace('/\W+/', '-', (string)$o['name']) . '.ics', ptb_ics($ss, false, (string)$o['name']), 'text/calendar; charset=utf-8; method=PUBLISH']]);
}

/** Einen bezahlten Termin verschieben (Vivien in der Verwaltung). Mail mit neuer Kalenderdatei. */
function ptb_move(PDO $pdo, int $sid, string $date, string $time, bool $mail): ?array
{
    $st = $pdo->prepare('SELECT * FROM mm_ptb_sessions WHERE id = ?');
    $st->execute([$sid]);
    $s = $st->fetch();
    if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{1,2}:\d{2}$/', $time)) return null;
    $time = str_pad($time, 5, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE mm_ptb_sessions SET date = ?, start = ? WHERE id = ?')->execute([$date, $time, $sid]);
    $o = ptb_order($pdo, (int)$s['order_id']);
    $s['date'] = $date; $s['start'] = $time;
    if ($mail && $o && $o['status'] === 'bezahlt') {
        $en = ptb_en($o);
        $first = forma_first_name((string)$o['name']);
        $ics = [['Movecia-Termin.ics', ptb_ics([$s + ['status' => 'bestaetigt']], $en), 'text/calendar; charset=utf-8; method=PUBLISH']];
        $en
            ? ptb_mail_guest($o, 'New time: ' . ptb_when($date, $time, true), 'Your new time', ["Hi $first,", 'as agreed, your session is now on:', [ptb_when($date, $time, true), ucfirst(ptb_kind($o, true, true)) . ' · Movecia Studio, Mathon'], 'The attached calendar file has the new time.'], [ptb_link($o), 'My sessions'], $ics)
            : ptb_mail_guest($o, 'Neuer Termin: ' . ptb_when($date, $time), 'Dein neuer Termin', ["Hallo $first,", 'wie besprochen ist deine Einheit jetzt am:', [ptb_when($date, $time), ptb_kind($o, false, true) . ' · Movecia Studio, Mathon'], 'In der angehängten Kalenderdatei steht die neue Zeit.'], [ptb_link($o), 'Meine Termine'], $ics);
    }
    return $s;
}

// --- Kalender ------------------------------------------------------------------------------

function ptb_ics(array $sessions, bool $en = false, string $who = ''): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $f = fn(DateTime $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $esc = fn(string $v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $v);
    $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Movecia//Personal Training//DE\r\nMETHOD:PUBLISH\r\n";
    foreach ($sessions as $s) {
        if ((string)$s['start'] === '' || !in_array($s['status'], ['bestaetigt', 'erledigt', 'passt', 'vorschlag'], true)) continue;
        $from = new DateTime($s['date'] . ' ' . $s['start'], $tz);
        $to = (clone $from)->modify('+' . (int)$s['minutes'] . ' minutes');
        $out .= "BEGIN:VEVENT\r\nUID:ptb-{$s['id']}@moveciamovement.com\r\nDTSTAMP:" . $f(new DateTime('now', $tz)) . "\r\nDTSTART:" . $f($from) . "\r\nDTEND:" . $f($to) . "\r\n"
            . 'SUMMARY:' . $esc('Movecia · Personal Training' . ($who !== '' ? ' · ' . $who : '')) . "\r\nLOCATION:" . $esc('Movecia Studio, ' . NATAL_COMPANY['street'] . ', ' . NATAL_COMPANY['city'])
            . "\r\nDESCRIPTION:" . $esc(($en ? 'With Vivien. Comfortable clothes and a bottle of water. Questions: ' : 'Mit Vivien. Bequeme Kleidung und eine Flasche Wasser mitbringen. Bei Fragen: ') . NATAL_COMPANY['phone']) . "\r\nEND:VEVENT\r\n";
    }
    return $out . "END:VCALENDAR\r\n";
}

function ptb_gcal(array $s, bool $en = false): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $from = new DateTime($s['date'] . ' ' . $s['start'], $tz);
    $to = (clone $from)->modify('+' . (int)$s['minutes'] . ' minutes');
    $f = fn(DateTime $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode('Movecia · Personal Training') . '&dates=' . $f($from) . '/' . $f($to)
        . '&location=' . rawurlencode('Movecia Studio, ' . NATAL_COMPANY['street'] . ', ' . NATAL_COMPANY['city']) . '&details=' . rawurlencode($en ? '1:1 with Vivien' : '1:1 mit Vivien');
}

// --- Hintergrund: Zahlungen nachprüfen, alte Anfragen schließen, Bewertungs-Mail ------------

function ptb_tick(PDO $pdo): void
{
    $f = forma_data_dir() . '/movecia-ptb.tick';
    if (is_file($f) && filemtime($f) > time() - 180) return;
    @touch($f);
    try {
        ptb_schema($pdo);
        // nicht bestätigte E-Mail-Adressen nach 3 Tagen schließen
        $pdo->prepare("UPDATE mm_ptb_orders SET status = 'abgelaufen' WHERE status = 'email' AND created_at < ?")->execute([date('c', time() - 3 * 86400)]);
        // Zahlung nachprüfen, falls jemand nach dem Bezahlen das Fenster geschlossen hat
        if (forma_stripe_key()) foreach ($pdo->query("SELECT * FROM mm_ptb_orders WHERE status = 'angebot' AND stripe_session <> '' ORDER BY id DESC LIMIT 8")->fetchAll() as $o) ptb_check_paid($pdo, $o);
        // vorbei → erledigt
        $now = date('Y-m-d H:i');
        foreach ($pdo->query("SELECT id, date, start, minutes FROM mm_ptb_sessions WHERE status = 'bestaetigt'")->fetchAll() as $s) {
            if (date('Y-m-d H:i', ptb_span((string)$s['date'], (string)$s['start'], (int)$s['minutes'])[1]) <= $now) $pdo->prepare("UPDATE mm_ptb_sessions SET status = 'erledigt' WHERE id = ?")->execute([$s['id']]);
        }
        // Bewertung: 1 Std. nach dem Ende der letzten Einheit (wartet ohne hinterlegten Link)
        if (mm_review_url() !== '') {
            foreach ($pdo->query("SELECT * FROM mm_ptb_orders WHERE status = 'bezahlt' AND review_sent_at IS NULL AND is_test = 0")->fetchAll() as $o) {
                $ss = ptb_sessions($pdo, (int)$o['id'], true);
                if (!$ss || array_filter($ss, fn($s) => $s['status'] !== 'erledigt' && $s['status'] !== 'abgesagt')) continue;
                $last = max(array_map(fn($s) => ptb_span((string)$s['date'], (string)$s['start'], (int)$s['minutes'])[1], $ss));
                if (time() < $last + 3600) continue;
                if (mm_review_mail((string)$o['email'], (string)$o['name'], (string)$o['lang'], count($ss) > 1 ? 'pt' : 'pt1')) $pdo->prepare('UPDATE mm_ptb_orders SET review_sent_at = ? WHERE id = ?')->execute([date('c'), $o['id']]);
            }
        }
    } catch (Throwable $e) {
        error_log('PTB tick: ' . $e->getMessage());
    }
}

// --- Rechnung (wird von api/invoices.php aufgerufen, Movecia-Design) -----------------------

function ptb_invoice_source(PDO $pdo, int $id): ?array
{
    $o = ptb_order($pdo, $id);
    if (!$o || $o['status'] !== 'bezahlt' || (int)$o['amount_cents'] <= 0) return null;
    $ss = ptb_sessions($pdo, $id, true);
    $dates = implode(', ', array_map(fn($s) => date('d.m.Y', strtotime((string)$s['date'])) . ' ' . substr((string)$s['start'], 0, 5), $ss));
    $units = (int)$o['units'];
    return ['name' => $o['name'], 'email' => $o['email'], 'session' => (string)$o['stripe_session'],
        'service' => $ss ? implode(', ', array_unique(array_map(fn($s) => date('d.m.Y', strtotime((string)$s['date'])), $ss))) : 'laut Terminvereinbarung',
        'items' => [['text' => 'Personal Training ' . (ptb_persons($o) === 2 ? 'zu zweit (2 Personen)' : '1:1'), 'detail' => ($units === 1 ? '1 Einheit' : $units . ' Einheiten') . ' à ' . PTB_MINUTES . ' Min. im Movecia Studio, Mathon · ' . $dates, 'qty' => $units, 'unit' => (int)$o['unit_cents']]],
        'gross' => (int)$o['amount_cents'], 'paid' => $o['paid_at'], 'type' => 'Movecia',
        'pay_note' => 'Der Betrag wurde am ' . date('d.m.Y', strtotime((string)$o['paid_at'])) . ' online bezahlt (Kartenzahlung über Stripe, gemeinsames Konto FORMA & Movecia). Buchungsvermerk: Leistung von Movecia – Personal Fitness.'];
}

// --- Testbuchung (Verwaltung → Personal Training → „Als Gast testen“) -----------------------

/** Legt eine Test-Anfrage an, die gleich als bestätigte E-Mail gilt. Mails gehen an $email. */
function ptb_test_create(PDO $pdo, string $email, string $lang, int $n, int $persons = 1): ?array
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
    $n = max(1, min(PTB_MAX_UNITS, $n));
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO mm_ptb_orders (persons, name, email, phone, hotel, note, lang, status, token, email_token, ip_hash, consent_terms, is_test, created_at) VALUES (?,?,?,?,?,?,?, 'email', ?, ?, '', 1, 1, ?)")
        ->execute([$persons >= 2 ? 2 : 1, $lang === 'en' ? 'Test Guest' : 'Test Gast', $email, '', 'Testhotel', 'TEST', $lang === 'en' ? 'en' : 'de', $tok, bin2hex(random_bytes(16)), date('c')]);
    $oid = (int)$pdo->lastInsertId();
    $times = ptb_times();
    $ins = $pdo->prepare("INSERT INTO mm_ptb_sessions (order_id, pos, wish_date, wish_time, date, start, minutes, status, created_at) VALUES (?,?,?,?,?,?," . PTB_MINUTES . ", 'offen', ?)");
    for ($i = 0; $i < $n; $i++) {
        $d = date('Y-m-d', strtotime('+' . (2 + $i) . ' days'));
        $t = $times[min(count($times) - 1, 4 + $i * 2)];
        $ins->execute([$oid, $i, $d, $t, $d, $t, date('c')]);
    }
    $o = ptb_order($pdo, $oid);
    return ptb_email_ok($pdo, (string)$o['email_token']);
}

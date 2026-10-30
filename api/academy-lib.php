<?php
// Movecia Academy (Vivien, 28.10.2026): Ausbildungen auf moveciamovement.com/academy.
// Erste Ausbildung: The Mindful Becoming Teacher Training (550 Std., 11 Monate, hybrid, jährlich, max. 14 Plätze).
// Runde 2 (28.10.2026): Buchung = 50 % Anzahlung über Stripe, Rest auf einmal, in 2 oder 3 Zahlungen bis 15.9.2027 (Bezahl-Links per Mail),
// Rücktritt 14 Tage ab Buchung, Storno-Staffel, Konfetti-Mail, Mitgliederbereich vor dem Start (/academy/mitglieder/) mit Countdown,
// Willkommensvideo, Notizen (verschlüsselt), Fragen & Antworten und persönlichem Chat mit Vivien.
declare(strict_types=1);
require_once __DIR__ . '/method-lib.php';

const AC_HOLD = 40 * 60;                 // so lange hält eine offene Zahlung einen Platz
const AC_TITLE = 'The Mindful Becoming Teacher Training';
const AC_SHORT = 'Mindful Becoming Teacher Training';
const AC_HOURS = 550;
// Kennenlerngespräch (Vivien): Mi/Do/Fr 8:00, Mi/Do 18:30, 30 Minuten, online per Video
const AC_CALL_SLOTS = [3 => ['08:00', '18:30'], 4 => ['08:00', '18:30'], 5 => ['08:00']];
const AC_CALL_MINUTES = 30;
// Wunschtermin fürs Kennenlerngespräch (Vivien, 29.10.2026): Mo–Fr, Tageszeit + freiwillige Uhrzeit; Vivien bucht in der Verwaltung ein
const AC_CALL_PARTS = ['vormittag' => ['Vormittag', '09:00'], 'mittag' => ['Mittag', '12:00'], 'abend' => ['Abend', '18:30']];
const AC_CALL_WEEKS = 10;
const AC_OFFER_MINUTES = 20;             // so lange gilt der Kennenlern-Preis aus dem Gespräch (Vivien, 28.10.2026, Runde 4)
const AC_MAIL_BEFORE = 10;               // Bezahl-Link so viele Tage vor dem Fälligkeitstag

// --- Einstellungen ------------------------------------------------------------------------------

/**
 * Preise und Termine (Vivien, 28.10.2026 spätabends): Early-Bird € 3.890 bis inkl. 31.3.2027, danach € 4.900, max. 14 Plätze (vorher € 3.590 / € 4.500 / 10 Plätze). Kennenlern-Preis € 3.890 (= Early Bird als Bonus im Gespräch, auch nach dem 31.3.).
 * Zahlung: 50 % Anzahlung bei Buchung; Rest auf einmal, oder 50 % bis 15.9.2027, oder 25 % am 15.6.2027 + 25 % am 15.9.2027. Kein Aufschlag.
 * Start Mi 3.11.2027 (Herbstferien 27.–31.10., 1.11. Allerheiligen, 2.11. Allerseelen schulfrei), Module 2–11 jeweils am 1.
 */
function ac_cfg_raw(): array
{
    $def = [
        'seats' => 14, 'open' => 1,
        'start' => '2027-11-03', 'end' => '2028-10-31',
        'eb_until' => '2027-03-31',
        'eb_total' => 389000, 'total' => 490000,
        'due_mid' => '2027-06-15', 'due_final' => '2027-09-15',
        'two_until' => '2027-08-31', // ab 16.6. nur noch auf einmal oder 2 Zahlungen, ab 1.9. nur auf einmal
        'storno1' => '2027-06-30',
        // Kennenlern-Preis (Runde 4): nur im Kennenlerngespräch, 20 Minuten, auch nach dem Early-Bird. Nie öffentlich nennen.
        'call_total' => 389000, // = Early-Bird-Preis als Bonus nach dem 31.3.2027 (Vivien, 28.10.2026)
    ];
    $c = mm_cfg('academy') ?? [];
    // Alte gespeicherte Standardwerte (Runde 4) gelten nicht mehr: Kennenlern-Preis jetzt € 3.390, Early-Bird € 3.590.
    if (in_array((int)($c['call_total'] ?? 0), [319000, 339000], true)) unset($c['call_total']);
    if (in_array((int)($c['eb_total'] ?? 0), [339000, 359000], true)) unset($c['eb_total']);
    // Preise und Plätze seit 28.10.2026 spätabends: € 3.890 / € 4.900, 14 Plätze. Alte gespeicherte Werte nicht mehr verwenden.
    if ((int)($c['total'] ?? 0) === 450000) unset($c['total']);
    if ((int)($c['seats'] ?? 0) === 10) unset($c['seats']);
    foreach ($def as $k => $v) $def[$k] = is_int($v) ? (int)($c[$k] ?? $v) : (string)($c[$k] ?? $v);
    return $def;
}

/** Gültige Preise: price = jetzt, r_total = regulär, eb = Early-Bird gerade aktiv, eb_end = Zeitstempel. */
function ac_cfg(): array
{
    $c = ac_cfg_raw();
    $c['eb_end'] = mv_ts($c['eb_until'], '23:59');
    $c['eb'] = time() <= $c['eb_end'];
    $c['r_total'] = $c['total'];
    $c['price'] = $c['eb'] ? $c['eb_total'] : $c['total'];
    $c['start_ts'] = mv_ts($c['start'], '00:00');
    return $c;
}

function ac_eur(int $c, string $lang = 'de'): string
{
    if ($lang === 'en') return '€' . ($c % 100 === 0 ? number_format($c / 100, 0, '.', ',') : number_format($c / 100, 2, '.', ','));
    return mv_eur($c);
}

/** Zusammensetzung der 550 Stunden (Vivien, 28.10.2026). [de, en, Stunden, Farbe, Icon] */
function ac_hours(): array
{
    return [
        ['Yoga nach internationalem 200-Std.-Curriculum', 'Yoga, following an international 200-hour curriculum', 200, '#4A3040', 'sonne'],
        ['Somatic Movement', 'Somatic movement', 80, '#B58C99', 'welle'],
        ['Breathwork & Meditation', 'Breathwork & meditation', 60, '#C8B5BF', 'atem'],
        ['Movecia Movement Method & Bodyweight', 'Movecia Movement Method & bodyweight', 60, '#E8C9B8', 'spirale'],
        ['Anatomie & Body Scanning', 'Anatomy & body scanning', 60, '#9A6F7A', 'herz'],
        ['Achtsame Berührung & Rituale', 'Mindful touch & rituals', 30, '#DDB7A7', 'kerze'],
        ['Mental Load & Nervensystem', 'Mental load & the nervous system', 30, '#E4DCD1', 'kreis'],
        ['Deine Selbstständigkeit', 'Your own business', 30, '#2E1D27', 'bluete'],
    ];
}

/** Die elf Module mit Freischalttag (Modul 1 am Starttag, danach jeweils am 1. des Monats). */
function ac_modules(): array
{
    $m = [
        ['Ankommen & Philosophie', 'Arriving & philosophy'],
        ['Anatomie & Body Scan I', 'Anatomy & body scan I'],
        ['Asana & Alignment', 'Asana & alignment'],
        ['Atem & Breathwork', 'Breath & breathwork'],
        ['Nervensystem & Mental Load', 'Nervous system & mental load'],
        ['Somatic Movement', 'Somatic movement'],
        ['Anatomie II & Tiefenmuskulatur', 'Anatomy II & deep core'],
        ['Meditation, Berührung & Rituale', 'Meditation, touch & rituals'],
        ['Zuhören & Begleiten', 'Listening & guiding'],
        ['Unterrichten & Sequencing', 'Teaching & sequencing'],
        ['Deine Selbstständigkeit & Abschluss', 'Your own business & graduation'],
    ];
    $c = ac_cfg_raw();
    $tz = new DateTimeZone('Europe/Vienna');
    $start = new DateTimeImmutable($c['start'], $tz);
    $out = [];
    foreach ($m as $i => [$de, $en]) {
        $d = $i === 0 ? $start : $start->modify('first day of this month')->modify('+' . $i . ' months');
        $out[] = ['n' => $i + 1, 'de' => $de, 'en' => $en, 'date' => $d->format('Y-m-d')];
    }
    return $out;
}

function ac_month(string $ymd, string $lang = 'de'): string
{
    $t = strtotime($ymd);
    if ($lang === 'en') return date('F Y', $t);
    $m = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    return $m[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function ac_d(string $ymd, string $lang = 'de'): string { return mv_date(mv_ts($ymd, '12:00'), $lang); }

// --- Zahlungsplan --------------------------------------------------------------------------------

/** Die drei Zahlungsarten mit Anteilen [Prozent, Fälligkeit ('' = sofort)]. */
function ac_plans(): array
{
    $c = ac_cfg_raw();
    return [
        'full' => ['de' => 'Alles auf einmal', 'en' => 'Everything at once', 'parts' => [[100, '']]],
        'two' => ['de' => 'In 2 Zahlungen', 'en' => 'In 2 payments', 'parts' => [[50, ''], [50, $c['due_final']]]],
        'three' => ['de' => 'In 3 Zahlungen', 'en' => 'In 3 payments', 'parts' => [[50, ''], [25, $c['due_mid']], [25, $c['due_final']]]],
    ];
}

/** Zahlungsarten, die am Buchungstag angeboten werden (Vivien, 28.10.2026): bis 15.6.2027 alle drei,
 *  16.6.–31.8.2027 auf einmal oder 2 Zahlungen, ab 1.9.2027 nur auf einmal. */
function ac_plans_open(?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $c = ac_cfg_raw();
    $all = ac_plans();
    $keys = ['full'];
    if ($today <= $c['two_until']) $keys[] = 'two';
    if ($today <= $c['due_mid']) $keys[] = 'three';
    return array_intersect_key($all, array_flip($keys));
}

/** Teilbeträge für Gesamtpreis + Plan. Teile, deren Fälligkeit schon erreicht ist, sind sofort fällig (werden zusammengelegt). */
function ac_schedule(int $total, string $plan, ?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $plans = ac_plans();
    $p = $plans[$plan] ?? $plans['full'];
    $parts = [];
    $now = 0; $sum = 0;
    foreach ($p['parts'] as $i => [$pct, $due]) {
        $cents = $i === count($p['parts']) - 1 ? $total - $sum : (int)round($total * $pct / 100);
        $sum += $cents;
        if ($due === '' || $due <= $today) $now += $cents;
        else $parts[] = ['cents' => $cents, 'due' => $due];
    }
    array_unshift($parts, ['cents' => $now, 'due' => '']);
    foreach ($parts as $i => &$x) $x += ['n' => $i + 1, 'paid' => '', 'ref' => '', 'session' => '', 'mailed' => 0, 'remind' => 0];
    unset($x);
    return $parts;
}

function ac_parts(array $r): array { return json_decode((string)($r['parts'] ?? '[]'), true) ?: []; }

function ac_paid_cents(array $r): int { return array_sum(array_map(fn($p) => $p['paid'] !== '' ? (int)$p['cents'] : 0, ac_parts($r))); }

/** Nächste offene Teilzahlung oder null. */
function ac_next_part(array $r): ?array
{
    foreach (ac_parts($r) as $p) if ($p['paid'] === '') return $p;
    return null;
}

/** „Anzahlung“, „2. Zahlung“ … für Mails, Rechnung und Verwaltung. */
function ac_part_label(array $r, int $n, string $lang = 'de'): string
{
    $cnt = count(ac_parts($r));
    if ($cnt === 1) return $lang === 'en' ? 'Full payment' : 'Gesamtbetrag';
    if ($n === 1) return $lang === 'en' ? 'Deposit' : 'Anzahlung';
    if ($n === $cnt) return $lang === 'en' ? 'Final payment' : 'Restzahlung';
    return $lang === 'en' ? 'Payment ' . $n . ' of ' . $cnt : $n . '. Zahlung von ' . $cnt;
}

/** Zeilen für Mails/Seite: „€ 1.695 · Anzahlung · bezahlt am …“ */
function ac_parts_lines(array $r, string $lang = 'de'): array
{
    $en = $lang === 'en';
    $out = [];
    foreach (ac_parts($r) as $p) {
        $amt = ac_eur((int)$p['cents'], $lang);
        $lab = ac_part_label($r, (int)$p['n'], $lang);
        if ($p['paid'] !== '') $st = $en ? 'paid on ' . mv_date(strtotime((string)$p['paid']), 'en') : 'bezahlt am ' . mv_date(strtotime((string)$p['paid']));
        else $st = $p['due'] === '' ? ($en ? 'due now' : 'jetzt fällig') : ($en ? 'due by ' . ac_d($p['due'], 'en') : 'fällig bis ' . ac_d($p['due']));
        $out[] = $amt . ' · ' . $lab . ' · ' . $st;
    }
    return $out;
}

// --- Datenbank ------------------------------------------------------------------------------------

function ac_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    mv_schema($pdo);
    $pdo->exec('CREATE TABLE IF NOT EXISTS ac_bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course TEXT NOT NULL DEFAULT \'tt2027\',
        plan TEXT NOT NULL DEFAULT \'full\',
        eb INTEGER NOT NULL DEFAULT 0,
        total_cents INTEGER NOT NULL DEFAULT 0,
        parts TEXT NOT NULL DEFAULT \'[]\',
        name TEXT NOT NULL DEFAULT \'\',
        email TEXT NOT NULL DEFAULT \'\',
        phone TEXT NOT NULL DEFAULT \'\',
        practice TEXT NOT NULL DEFAULT \'\',
        practice_note TEXT NOT NULL DEFAULT \'\',
        why TEXT NOT NULL DEFAULT \'\',
        lang TEXT NOT NULL DEFAULT \'de\',
        consent INTEGER NOT NULL DEFAULT 0,
        call_id INTEGER,
        status TEXT NOT NULL DEFAULT \'offen\',
        token TEXT NOT NULL,
        stripe_session TEXT NOT NULL DEFAULT \'\',
        customer TEXT NOT NULL DEFAULT \'\',
        address TEXT NOT NULL DEFAULT \'\',
        country TEXT NOT NULL DEFAULT \'AT\',
        mailed INTEGER NOT NULL DEFAULT 0,
        test INTEGER NOT NULL DEFAULT 0,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        paid_at TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS ac_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_id INTEGER NOT NULL,
        ref TEXT NOT NULL UNIQUE,
        cents INTEGER NOT NULL,
        n INTEGER NOT NULL DEFAULT 1,
        paid_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS ac_calls (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL,
        slot TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT \'\',
        note TEXT NOT NULL DEFAULT \'\',
        lang TEXT NOT NULL DEFAULT \'de\',
        status TEXT NOT NULL DEFAULT \'angefragt\',
        room_url TEXT NOT NULL DEFAULT \'\',
        host_url TEXT NOT NULL DEFAULT \'\',
        offer_token TEXT NOT NULL DEFAULT \'\',
        offer_until INTEGER NOT NULL DEFAULT 0,
        token TEXT NOT NULL,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL
    )');
    // Mitgliederbereich vor dem Start: private Notizen (verschlüsselt) und Chat mit Vivien
    $pdo->exec('CREATE TABLE IF NOT EXISTS ac_notes (
        member_id INTEGER PRIMARY KEY,
        body TEXT NOT NULL DEFAULT \'\',
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS ac_msgs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_id INTEGER NOT NULL,
        who TEXT NOT NULL DEFAULT \'tn\',
        text TEXT NOT NULL,
        seen INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )');
    // Kennenlern-Preis (Runde 4): Buchung zum Preis aus dem Gespräch
    $bc = array_column($pdo->query('PRAGMA table_info(ac_bookings)')->fetchAll(), 'name');
    if (!in_array('call_price', $bc, true)) $pdo->exec('ALTER TABLE ac_bookings ADD COLUMN call_price INTEGER NOT NULL DEFAULT 0');
    $cc = array_column($pdo->query('PRAGMA table_info(ac_calls)')->fetchAll(), 'name');
    if (!in_array('offer_mailed', $cc, true)) $pdo->exec('ALTER TABLE ac_calls ADD COLUMN offer_mailed INTEGER NOT NULL DEFAULT 0');
    // Wunschtermin statt fester Zeiten (Vivien, 29.10.2026): Tag + Tageszeit + freiwillige Wunschuhrzeit, slot bleibt leer bis Vivien einbucht
    if (!in_array('daypart', $cc, true)) $pdo->exec("ALTER TABLE ac_calls ADD COLUMN daypart TEXT NOT NULL DEFAULT ''");
    if (!in_array('wish_time', $cc, true)) $pdo->exec("ALTER TABLE ac_calls ADD COLUMN wish_time TEXT NOT NULL DEFAULT ''");
    ac_welcome_lesson($pdo);
}

function ac_booking(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM ac_bookings WHERE id = ?');
    $q->execute([$id]);
    $r = $q->fetch() ?: null;
    if ($r) $r['lang'] = 'de'; // Ausbildung, Mails, Rechnungen nur auf Deutsch
    return $r;
}

/** Bezahlte (Anzahlung da) Buchung zu einer E-Mail-Adresse. */
function ac_booking_of(PDO $pdo, string $email): ?array
{
    ac_schema($pdo);
    $q = $pdo->prepare("SELECT * FROM ac_bookings WHERE lower(email) = ? AND status = 'bezahlt' ORDER BY id DESC LIMIT 1");
    $q->execute([strtolower(trim($email))]);
    $r = $q->fetch() ?: null;
    if ($r) $r['lang'] = 'de';
    return $r;
}

/** Belegte Plätze: bezahlt + offene Zahlungen in der Haltezeit (Testbuchungen zählen nicht). */
function ac_taken(PDO $pdo): int
{
    ac_schema($pdo);
    $q = $pdo->prepare("SELECT COUNT(*) FROM ac_bookings WHERE test = 0 AND (status = 'bezahlt' OR (status = 'offen' AND created_at >= ?))");
    $q->execute([date('c', time() - AC_HOLD)]);
    return (int)$q->fetchColumn();
}

function ac_free(PDO $pdo): int { return max(0, ac_cfg()['seats'] - ac_taken($pdo)); }

/** Ende der gesetzlichen Rücktrittsfrist: 14 Tage ab Buchung (Zahlung der Anzahlung), bis 23:59. */
function ac_withdraw_until(array $r): int
{
    $paid = strtotime((string)($r['paid_at'] ?? '')) ?: time();
    return mv_ts((new DateTimeImmutable('@' . ($paid + 14 * 86400)))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('Y-m-d'), '23:59');
}

// --- Angebot im Kennenlerngespräch (persönlicher Link mit Countdown) -----------------------------

function ac_call(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM ac_calls WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function ac_offer_by_token(PDO $pdo, string $tok): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $tok)) return null;
    ac_schema($pdo);
    $q = $pdo->prepare('SELECT * FROM ac_calls WHERE offer_token = ?');
    $q->execute([$tok]);
    return $q->fetch() ?: null;
}

function ac_offer_live(?array $call): bool { return $call !== null && (int)$call['offer_until'] > time(); }

function ac_offer_start(PDO $pdo, int $callId, int $minutes = AC_OFFER_MINUTES): string
{
    $c = ac_call($pdo, $callId);
    if (!$c) throw new RuntimeException('Gespräch nicht gefunden.');
    $tok = $c['offer_token'] !== '' ? $c['offer_token'] : bin2hex(random_bytes(12));
    $until = time() + max(5, min(180, $minutes)) * 60;
    $pdo->prepare('UPDATE ac_calls SET offer_token = ?, offer_until = ? WHERE id = ?')->execute([$tok, $until, $callId]);
    $link = ac_offer_link($tok);
    // Zur Sicherheit auch per Mail (falls sie den Gesprächsraum schon geschlossen hat oder ihr Browser die Karte nicht zeigt)
    $c = ac_call($pdo, $callId);
    try { ac_offer_mail($c, $link, $until); $pdo->prepare('UPDATE ac_calls SET offer_mailed = offer_mailed + 1 WHERE id = ?')->execute([$callId]); }
    catch (Throwable $e) { error_log('AC offer mail: ' . $e->getMessage()); }
    return $link;
}

function ac_offer_link(string $tok): string
{
    return mm_site() . '/academy/teacher-training/?angebot=' . $tok . '#buchen';
}

/** Persönliche Mail mit dem Kennenlern-Preis (nur an die Frau aus dem Gespräch, nie an die Liste). */
function ac_offer_blocks(array $c, int $until): array
{
    $cfg = ac_cfg();
    $p = ac_prices(array_merge($c, ['offer_until' => $until]));
    $first = forma_first_name((string)$c['name']);
    return [
        'Hallo ' . $first . ',',
        'danke für unser Gespräch ♡ Wie versprochen bekommst du für die nächsten ' . AC_OFFER_MINUTES . ' Minuten deinen persönlichen Kennenlern-Preis für das Mindful Becoming Teacher Training.',
        ['box' => ['Dein Kennenlern-Preis: ' . ac_eur($p['total']) . ' statt ' . ac_eur($cfg['price']), 'gilt bis ' . date('G:i', $until) . ' Uhr', 'bei der Buchung 50 % Anzahlung (' . ac_eur(intdiv($p['total'], 2)) . '), den Rest auf einmal oder in Teilen bis ' . mv_date(mv_ts($cfg['due_final'], '12:00')) . ', ohne Aufschlag']],
        'Über den Knopf kommst du direkt zur Anmeldung, der Preis ist dort schon eingetragen. Wenn du noch Zeit zum Nachdenken brauchst, ist das völlig in Ordnung. Dann gilt einfach wieder der normale Preis.',
    ];
}

function ac_offer_mail(array $c, string $link, int $until): void
{
    $blocks = ac_offer_blocks($c, $until);
    $r = ['lang' => 'de', 'email' => (string)$c['email'], 'name' => (string)$c['name']];
    $html = ac_mail_html($r, 'nur für dich ♡', 'Dein Kennenlern-Preis', $blocks, $link, 'Jetzt Platz sichern', '', false);
    mm_mail((string)$c['email'], 'Dein persönlicher Kennenlern-Preis · Movecia Academy', ac_mail_text($blocks, $link, 'Jetzt Platz sichern', '', false), $html, [], 'Vivien · Movecia Academy');
}

/** Persönlicher Gesprächsraum (Movecia-Seite mit eingebettetem Videoraum und Angebotskarte). */
function ac_call_room_link(array $c): string
{
    return mm_site() . '/academy/gespraech/?c=' . (int)$c['id'] . '&k=' . $c['token'];
}

function ac_call_by_link(PDO $pdo, int $id, string $k): ?array
{
    ac_schema($pdo);
    $c = ac_call($pdo, $id);
    return $c && $k !== '' && hash_equals((string)$c['token'], $k) ? $c : null;
}

/** Gesamtpreis für eine Buchung: Kennenlern-Preis solange das Angebot aus dem Gespräch läuft, sonst Early-Bird bzw. regulär. Immer am Server. */
function ac_prices(?array $offer = null): array
{
    $c = ac_cfg();
    if (ac_offer_live($offer)) return ['eb' => false, 'call' => true, 'total' => min($c['call_total'], $c['price'])];
    return ['eb' => $c['eb'], 'call' => false, 'total' => $c['eb'] ? $c['eb_total'] : $c['r_total']];
}

// --- Buchung über Stripe --------------------------------------------------------------------------

/** Stripe-Checkout für einen Teilbetrag (Anzahlung oder spätere Zahlung). Preis kommt immer aus der Buchung. */
function ac_part_checkout(PDO $pdo, array $r, array $part): string
{
    $en = $r['lang'] === 'en';
    $site = mm_site();
    $c = ac_cfg();
    $label = AC_TITLE . ' · ' . ac_part_label($r, (int)$part['n'], $r['lang']) . ((int)$r['eb'] ? ' · Early Bird' : ((int)($r['call_price'] ?? 0) ? ' · Kennenlern-Preis' : ''));
    $first = (int)$part['n'] === 1;
    $msg = ($en ? 'Your training starts on ' . mv_date($c['start_ts'], 'en') . '. ' : 'Deine Ausbildung startet am ' . mv_date($c['start_ts']) . '. ')
        . ($en ? 'Terms and cancellation: ' : 'Bedingungen und Storno: ') . $site . '/terms#teacher';
    $params = [
        'mode' => 'payment',
        'locale' => $en ? 'en' : 'de',
        'billing_address_collection' => 'required',
        'customer_email' => (string)$r['email'],
        'client_reference_id' => 'ac-' . $r['id'] . '-' . $part['n'],
        'metadata' => ['ac_booking' => (string)$r['id'], 'ac_part' => (string)$part['n']],
        'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => (int)$part['cents'], 'product_data' => ['name' => $label, 'description' => ($en ? AC_HOURS . '-hour hybrid training, starts ' : AC_HOURS . '-Stunden-Ausbildung, hybrid, Start ') . mv_date($c['start_ts'], $r['lang']) . ' · Movecia Academy, Vivien Malonga']]]],
        'custom_text' => ['submit' => ['message' => mb_substr($msg, 0, 1000)]],
        'expires_at' => time() + 31 * 60,
        'payment_intent_data' => ['statement_descriptor_suffix' => 'MOVECIA', 'description' => AC_TITLE],
        'success_url' => $site . '/api/academy.php?paid=' . $r['id'] . '&p=' . $part['n'] . '&k=' . $r['token'],
        'cancel_url' => $site . ($first ? '/academy/teacher-training/?abbruch=1' . ($en ? '&lang=en' : '') . '#buchen' : '/academy/mitglieder/' . ($en ? '?lang=en' : '')),
        'branding_settings' => ['display_name' => 'Movecia Academy'],
    ];
    try { $s = forma_stripe('POST', '/v1/checkout/sessions', $params, null, '2025-09-30.clover'); }
    catch (Throwable $e) { unset($params['branding_settings']); $s = forma_stripe('POST', '/v1/checkout/sessions', $params); }
    $parts = ac_parts($r);
    foreach ($parts as &$x) if ((int)$x['n'] === (int)$part['n']) $x['session'] = (string)$s['id'];
    unset($x);
    $pdo->prepare('UPDATE ac_bookings SET parts = ?' . ($first ? ', stripe_session = ?' : '') . ' WHERE id = ?')->execute($first ? [json_encode($parts), (string)$s['id'], $r['id']] : [json_encode($parts), $r['id']]);
    return (string)$s['url'];
}

/** Legt die Buchung an und gibt die Stripe-Bezahlseite für die erste Zahlung zurück. */
function ac_checkout(PDO $pdo, array $in, string $ipHash = ''): string
{
    ac_schema($pdo);
    $c = ac_cfg();
    $en = false; // Ausbildung nur auf Deutsch (Vivien, 28.10.2026)
    $name = forma_clean((string)($in['name'] ?? ''), 80);
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $phone = forma_clean((string)($in['phone'] ?? ''), 40);
    $practice = in_array($in['practice'] ?? '', ['lt1', '1to2', 'gt2', 'teach'], true) ? (string)$in['practice'] : '';
    $note = trim(mb_substr((string)($in['practice_note'] ?? ''), 0, 800));
    $why = trim(mb_substr((string)($in['why'] ?? ''), 0, 1500));
    $plan = array_key_exists((string)($in['plan'] ?? ''), ac_plans_open()) ? (string)$in['plan'] : '';
    $err = [];
    if ($plan === '') $err[] = $en ? 'Please choose how you would like to pay.' : 'Bitte wähl, wie du zahlen möchtest.';
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = $en ? 'Please enter your name and email.' : 'Bitte gib deinen Namen und deine E-Mail an.';
    if (mb_strlen(preg_replace('/\D/', '', $phone)) < 6) $err[] = $en ? 'Please enter your phone number.' : 'Bitte gib deine Telefonnummer an.';
    if ($practice === '') $err[] = $en ? 'Please tell me how long you have been practising.' : 'Bitte sag mir, wie lange du schon praktizierst.';
    if (mb_strlen($why) < 10) $err[] = $en ? 'Please write a few words about why you want to do the training.' : 'Bitte schreib mir ein paar Sätze, warum du die Ausbildung machen möchtest.';
    if (empty($in['consent'])) $err[] = $en ? 'Please confirm the box about the cancellation terms and the in-person weekend.' : 'Bitte bestätige das Häkchen zu Storno und Präsenzwochenende.';
    if ($err) throw new RuntimeException(implode(' ', $err));
    if (!$c['open']) throw new RuntimeException($en ? 'Booking is not open right now.' : 'Die Buchung ist gerade nicht geöffnet.');
    if (ac_free($pdo) <= 0) throw new RuntimeException($en ? 'The training is fully booked. Write to me and I will let you know if a place opens up.' : 'Die Ausbildung ist ausgebucht. Schreib mir gern, dann sage ich dir Bescheid, sobald ein Platz frei wird.');
    if (ac_booking_of($pdo, $email)) throw new RuntimeException($en ? 'You are already booked. Simply log in to your members area.' : 'Du bist schon angemeldet. Melde dich einfach in deinem Mitgliederbereich an.');
    $offer = ac_offer_by_token($pdo, (string)($in['angebot'] ?? ''));
    $p = ac_prices($offer);
    $parts = ac_schedule($p['total'], $plan);
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO ac_bookings (plan, eb, call_price, total_cents, parts, name, email, phone, practice, practice_note, why, lang, consent, call_id, token, test, ip_hash, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$plan, $p['eb'] ? 1 : 0, !empty($p['call']) ? 1 : 0, $p['total'], json_encode($parts), $name, $email, $phone, $practice, $note, $why, $en ? 'en' : 'de', 1, $offer ? (int)$offer['id'] : null, $tok, forma_stripe_test_mode() ? 1 : 0, $ipHash, date('c')]);
    $r = ac_booking($pdo, (int)$pdo->lastInsertId());
    return ac_part_checkout($pdo, $r, $parts[0]);
}

/** Prüft eine Teilzahlung bei Stripe. Erste Zahlung: Buchung gilt, Konfetti-Mail, Konto, Info an Vivien. */
function ac_check_part(PDO $pdo, array $r, int $n): bool
{
    $parts = ac_parts($r);
    $i = null;
    foreach ($parts as $k => $x) if ((int)$x['n'] === $n) $i = $k;
    if ($i === null) return false;
    if ($parts[$i]['paid'] !== '') return true;
    if ($parts[$i]['session'] === '') return false;
    $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($parts[$i]['session']));
    if (($s['status'] ?? '') === 'expired') {
        if ($n === 1) $pdo->prepare("UPDATE ac_bookings SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([$r['id']]);
        return false;
    }
    if (($s['status'] ?? '') !== 'complete' && ($s['payment_status'] ?? '') !== 'paid') return false;
    $parts[$i]['paid'] = date('c');
    $parts[$i]['ref'] = (string)($s['payment_intent'] ?? $s['id']);
    $first = $n === 1;
    if ($first) {
        $cd = $s['customer_details'] ?? [];
        $a = $cd['address'] ?? [];
        $country = strtoupper((string)($a['country'] ?? 'AT')) ?: 'AT';
        $addr = implode("\n", array_filter([(string)($a['line1'] ?? ''), (string)($a['line2'] ?? ''), trim(($a['postal_code'] ?? '') . ' ' . ($a['city'] ?? '')), $country !== 'AT' ? $country : '']));
        $u = $pdo->prepare("UPDATE ac_bookings SET status = 'bezahlt', paid_at = ?, parts = ?, customer = ?, address = ?, country = ? WHERE id = ? AND status = 'offen'");
        $u->execute([date('c'), json_encode($parts), (string)($s['customer'] ?? ''), $addr, $country, $r['id']]);
        if ($u->rowCount() !== 1) return true;
    } else {
        $pdo->prepare('UPDATE ac_bookings SET parts = ? WHERE id = ?')->execute([json_encode($parts), $r['id']]);
    }
    $r = ac_booking($pdo, (int)$r['id']);
    // Rechnung wie gewohnt (Nummernkreis, Movecia-Design, Kopie an hello@, Google-Tabelle), das PDF aber als Anhang der Academy-Mail statt eigener Mail
    $GLOBALS['ac_inv_capture'] = [];
    ac_payment($pdo, $r, 'cs:' . $parts[$i]['session'], (int)($s['amount_total'] ?? $parts[$i]['cents']), $n);
    $att = $GLOBALS['ac_inv_capture']; unset($GLOBALS['ac_inv_capture']);
    if ($first) ac_after_paid($pdo, $r, $att);
    else { try { ac_mail_part_thanks($r, $n, $att); } catch (Throwable $e) { error_log('AC part thanks: ' . $e->getMessage()); } }
    return true;
}

/** Zahlung erfassen und Rechnung ausstellen (je Zahlung einmal). */
function ac_payment(PDO $pdo, array $r, string $ref, int $cents, int $n): void
{
    if ($cents <= 0) return;
    $ins = $pdo->prepare('INSERT OR IGNORE INTO ac_payments (booking_id, ref, cents, n, paid_at) VALUES (?,?,?,?,?)');
    $ins->execute([$r['id'], $ref, $cents, $n, date('c')]);
    if ($ins->rowCount() !== 1) return;
    require_once __DIR__ . '/invoices.php';
    $was = $GLOBALS['FORMA_TEST'] ?? null;
    if ((int)$r['test'] === 1) $GLOBALS['FORMA_TEST'] = true;
    forma_invoice_for($pdo, 'ac_tt', (int)$pdo->lastInsertId());
    $GLOBALS['FORMA_TEST'] = $was;
}

/** Link zum Bezahlen der nächsten offenen Teilzahlung (für Mails und den Mitgliederbereich). */
function ac_pay_link(array $r): string
{
    return mm_site() . '/api/academy.php?pay=' . $r['id'] . '&k=' . $r['token'];
}

/** Gedrosselt bei Seitenaufrufen: offene Zahlungen nachprüfen, Bezahl-Links und Erinnerungen verschicken. */
function ac_tick(PDO $pdo): void
{
    ac_schema($pdo);
    $f = forma_data_dir() . '/movecia-academy-tick';
    if (is_file($f) && (int)file_get_contents($f) > time() - 600) return;
    @file_put_contents($f, (string)time());
    try { ac_eb_reminder_run($pdo); } catch (Throwable $e) { error_log('AC EB reminder: ' . $e->getMessage()); }
    try { ac_waitlist_run($pdo); } catch (Throwable $e) { error_log('AC waitlist: ' . $e->getMessage()); }
    if (!forma_stripe_key()) return;
    $open = $pdo->prepare("SELECT * FROM ac_bookings WHERE status = 'offen' AND stripe_session <> '' AND created_at >= ? ORDER BY id LIMIT 10");
    $open->execute([date('c', time() - 2 * 86400)]);
    foreach ($open->fetchAll() as $r) { try { ac_check_part($pdo, $r, 1); } catch (Throwable $e) { error_log('AC tick: ' . $e->getMessage()); } }
    ac_due_run($pdo);
}

/** Spätere Teilzahlungen: Bezahl-Link AC_MAIL_BEFORE Tage vorher, Erinnerung am Fälligkeitstag (jeweils einmal). */
function ac_due_run(PDO $pdo): void
{
    // Vollautomatisch (Vivien, 28.10.2026): Bezahl-Link 10 Tage vorher, Erinnerung am Fälligkeitstag und 3 Tage danach,
    // Info an Vivien erst, wenn 7 Tage nach Fälligkeit noch nichts da ist.
    $today = date('Y-m-d');
    foreach ($pdo->query("SELECT * FROM ac_bookings WHERE status = 'bezahlt'")->fetchAll() as $r) {
        $parts = ac_parts($r); $changed = false;
        foreach ($parts as &$p) {
            if ($p['paid'] !== '' || $p['due'] === '') continue;
            if ($p['session'] !== '') { try { if (ac_check_part($pdo, $r, (int)$p['n'])) { $changed = false; continue 2; } } catch (Throwable $e) {} }
            $p += ['remind2' => 0, 'viv' => 0];
            $kind = '';
            if (!$p['mailed'] && $today >= date('Y-m-d', strtotime($p['due'] . ' -' . AC_MAIL_BEFORE . ' days'))) { $p['mailed'] = 1; $kind = 'link'; }
            elseif ($p['mailed'] && !$p['remind'] && $today >= $p['due']) { $p['remind'] = 1; $kind = 'remind'; }
            elseif ($p['remind'] && !$p['remind2'] && $today >= date('Y-m-d', strtotime($p['due'] . ' +3 days'))) { $p['remind2'] = 1; $kind = 'remind'; }
            elseif ($p['remind2'] && !$p['viv'] && $today >= date('Y-m-d', strtotime($p['due'] . ' +7 days'))) { $p['viv'] = 1; $kind = 'viv'; }
            if ($kind === 'viv') {
                $changed = true;
                $v = "Hallo Viv,\n\n" . $r['name'] . ' (' . $r['email'] . ', ' . $r['phone'] . ') hat die ' . ac_part_label($r, (int)$p['n']) . ' über ' . ac_eur((int)$p['cents']) . ' fürs Teacher Training noch nicht bezahlt (fällig seit ' . ac_d($p['due']) . "). Sie hat den Bezahl-Link und zwei Erinnerungen bekommen.\n\nVielleicht magst du ihr kurz persönlich schreiben: " . forma_site_url() . "/verwaltung/?view=academy\n";
                try { mm_mail(natal_notify_email_safe(), 'Teacher Training: Zahlung offen · ' . $r['name'], $v, mm_mail_html('Zahlung offen', nl2br(forma_h($v)), 'de')); } catch (Throwable $e) {}
            } elseif ($kind !== '') { $changed = true; try { ac_mail_due($r, $p, $kind === 'remind'); } catch (Throwable $e) { error_log('AC due mail: ' . $e->getMessage()); } }
            break; // immer nur die nächste offene Zahlung
        }
        unset($p);
        if ($changed) $pdo->prepare('UPDATE ac_bookings SET parts = ? WHERE id = ?')->execute([json_encode($parts), $r['id']]);
    }
}

// --- Mails ----------------------------------------------------------------------------------------

/** Rücktritt und Storno für Bestätigungsmail (Vorschlag, von der WKO prüfen lassen). */
function ac_legal(array $r): string
{
    $en = $r['lang'] === 'en';
    $c = ac_cfg();
    $site = mm_site();
    $until = ac_withdraw_until($r);
    $d = fn(string $ymd) => ac_d($ymd, $en ? 'en' : 'de');
    if ($en) return 'Your right of withdrawal: you can withdraw from your booking without giving a reason within 14 days, that is until ' . mv_date($until, 'en') . ', and you get back everything you have paid. '
        . 'Cancellation after that: until ' . $d($c['storno1']) . ' the cancellation fee is 25 % of the total price, from ' . $d(date('Y-m-d', strtotime($c['storno1'] . ' +1 day'))) . ' to ' . $d($c['due_final']) . ' it is 50 %, and after ' . $d($c['due_final']) . ' the full price is due. '
        . 'You can name someone to take your place at any time, at no cost. If I have to cancel the training, you get back everything you have paid. '
        . 'The in-person weekend in September 2028 is a fixed date and cannot be made up later; if you cannot attend, it is not refunded. For illness, a seminar or travel cancellation insurance is a good idea. '
        . 'Terms and withdrawal form: ' . $site . '/terms#teacher · Withdraw: ' . $site . '/withdraw?v=teacher';
    return 'Dein Rücktrittsrecht: Du kannst binnen 14 Tagen ohne Angabe von Gründen von deiner Buchung zurücktreten, also bis ' . mv_date($until) . ', und bekommst alles zurück, was du bezahlt hast. '
        . 'Storno danach: bis ' . $d($c['storno1']) . ' beträgt die Stornogebühr 25 % des Gesamtpreises, vom ' . $d(date('Y-m-d', strtotime($c['storno1'] . ' +1 day'))) . ' bis ' . $d($c['due_final']) . ' 50 %, nach dem ' . $d($c['due_final']) . ' ist der volle Preis fällig. '
        . 'Du kannst jederzeit kostenlos eine Ersatzteilnehmerin nennen. Muss ich die Ausbildung absagen, bekommst du alles zurück, was du bezahlt hast. '
        . 'Das Präsenzwochenende im September 2028 ist ein fester Termin und kann nicht nachgeholt werden; kannst du nicht teilnehmen, wird es nicht erstattet. Für den Krankheitsfall empfehle ich eine Seminar- oder Reisestornoversicherung. '
        . 'Bedingungen und Widerrufsformular: ' . $site . '/terms#teacher · Vertrag widerrufen: ' . $site . '/withdraw?v=teacher';
}

function ac_after_paid(PDO $pdo, array $r, array $att = []): void
{
    $u = $pdo->prepare('UPDATE ac_bookings SET mailed = 1 WHERE id = ? AND mailed = 0');
    $u->execute([$r['id']]);
    if ($u->rowCount() !== 1) return;
    try { ac_mail_welcome($pdo, $r, $att); } catch (Throwable $e) { error_log('AC welcome: ' . $e->getMessage()); }
    try { ac_notify_viv($pdo, $r); } catch (Throwable $e) { error_log('AC notify: ' . $e->getMessage()); }
}

function ac_area(string $lang = 'de'): string { return mm_site() . '/academy/mitglieder/' . ($lang === 'en' ? '?lang=en' : ''); }

/** Link „Passwort festlegen“ für den Academy-Mitgliederbereich (gleiche Konten wie Method/Unfold). */
function ac_pw_link(PDO $pdo, array $m, int $hours): string
{
    $tok = bin2hex(random_bytes(20));
    $pdo->prepare('UPDATE mv_members SET reset_token = ?, reset_until = ? WHERE id = ?')->execute([hash('sha256', $tok), date('c', time() + $hours * 3600), $m['id']]);
    return mm_site() . '/academy/mitglieder/?pw=' . $tok;
}

/** Konto anlegen (falls nötig) und Link zum Mitgliederbereich bzw. zum Passwort-Festlegen liefern. */
function ac_account_link(PDO $pdo, array $r): array
{
    $m = mv_member_ensure($pdo, (string)$r['email'], (string)$r['name'], (string)$r['lang']);
    $pdo->prepare('UPDATE mv_members SET email_ok = 1 WHERE id = ?')->execute([$m['id']]); // Adresse kommt aus der Zahlung
    $en = $r['lang'] === 'en';
    if ($m['pass_hash'] !== '') return [ac_area($r['lang']), $en ? 'Open my members area' : 'Zum Mitgliederbereich'];
    return [ac_pw_link($pdo, $m, 24 * 14), $en ? 'Choose my password & log in' : 'Passwort festlegen & loslegen'];
}

/** Gemeinsamer Mail-Aufbau Academy: Konfetti-Kopf (Bild), Handschrift-Zeile, Titel, Absätze, Listen, Knopf, Rechtliches. */
function ac_mail_html(array $r, string $hand, string $title, array $blocks, string $link, string $btn, string $legal, bool $confetti = false): string
{
    // Academy-Mails mit eigenem Kopf (Pflaume-Band mit Academy-Logo) bzw. Konfetti-Bild (Vivien, 28.10.2026)
    $html = '<p style="margin:0 0 16px;"><img src="' . forma_h(mm_site()) . '/img/movecia/' . ($confetti ? 'mail-academy-konfetti.png' : 'mail-academy-kopf.png') . '?v=4" width="500" alt="Movecia Academy" style="display:block;width:100%;max-width:500px;height:auto;border:0;border-radius:18px;"></p>';
    $html .= '<p style="margin:0 0 6px;font-family:\'Brush Script MT\',cursive;font-size:24px;color:#4A3040;">' . forma_h($hand) . '</p><h1 style="font-family:Georgia,serif;font-weight:400;font-size:28px;line-height:1.2;color:#3A2631;margin:0 0 14px;">' . forma_h($title) . '</h1>';
    foreach ($blocks as $b) {
        if (is_array($b) && isset($b['h'])) $html .= '<h2 style="font-family:Georgia,serif;font-weight:400;font-size:21px;color:#3A2631;margin:24px 0 6px;">' . forma_h($b['h']) . '</h2>';
        elseif (is_array($b) && isset($b['plan'])) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 6px;margin:8px 0;">';
            foreach ($b['plan'] as $x) $html .= '<tr><td style="background:' . ($x['paid'] ? '#E4DCD1' : '#FBF9F6') . ';border-radius:14px 0 0 14px;padding:10px 0 10px 14px;width:26px;font-size:16px;color:#4A3040;">' . ($x['paid'] ? '✓' : '○') . '</td><td style="background:' . ($x['paid'] ? '#E4DCD1' : '#FBF9F6') . ';padding:10px 8px;"><b style="font-weight:600;color:#2A2026;">' . forma_h($x['lab']) . '</b><br><span style="font-size:13px;color:#6E6168;">' . forma_h($x['when']) . '</span></td><td align="right" style="background:' . ($x['paid'] ? '#E4DCD1' : '#FBF9F6') . ';border-radius:0 14px 14px 0;padding:10px 14px 10px 8px;white-space:nowrap;font-size:16px;color:#2A2026;">' . forma_h($x['amt']) . '</td></tr>';
            $html .= '</table><p style="font-size:13px;color:#6E6168;margin:2px 0 10px;">' . forma_h($b['sum']) . '</p>';
        }
        elseif (is_array($b) && isset($b['box'])) $html .= '<div style="background:#FBF9F6;border-radius:16px;padding:14px 18px;margin:12px 0;">' . implode('', array_map(fn($x) => '<p style="margin:4px 0;">' . forma_h($x) . '</p>', $b['box'])) . '</div>';
        elseif (is_array($b)) $html .= '<ul style="padding-left:20px;margin:8px 0;">' . implode('', array_map(fn($x) => '<li style="margin:5px 0;">' . forma_h($x) . '</li>', $b)) . '</ul>';
        else $html .= '<p>' . forma_h($b) . '</p>';
    }
    if ($link !== '') $html .= mm_button($link, $btn);
    if ($legal !== '') $html .= '<p style="font-size:13px;color:#6E6168;border-top:1px solid #D3C9BC;padding-top:12px;margin-top:22px;">' . forma_h($legal) . '</p>';
    return mm_mail_html($title, $html, (string)$r['lang']);
}

function ac_mail_text(array $blocks, string $link, string $btn, string $legal, bool $en): string
{
    $t = [];
    foreach ($blocks as $b) {
        if (is_array($b) && isset($b['h'])) $t[] = strtoupper($b['h']);
        elseif (is_array($b) && isset($b['plan'])) $t[] = implode("\n", array_map(fn($x) => ($x['paid'] ? '✓ ' : '○ ') . $x['lab'] . ': ' . $x['amt'] . ' (' . $x['when'] . ')', $b['plan'])) . "\n" . $b['sum'];
        elseif (is_array($b) && isset($b['box'])) $t[] = implode("\n", $b['box']);
        elseif (is_array($b)) $t[] = implode("\n", array_map(fn($x) => '· ' . $x, $b));
        else $t[] = $b;
    }
    return implode("\n\n", $t) . ($link !== '' ? "\n\n$btn: $link" : '') . ($en ? "\n\nWith love,\nVivien" : "\n\nAlles Liebe,\nVivien") . ($legal !== '' ? "\n\n$legal" : '');
}

/** Zahlungsplan als Block für Mails: jede Zahlung mit Betrag, Art, Fälligkeit, bezahlt/offen, dazu Summen. */
function ac_plan_block(array $r): array
{
    $rows = [];
    foreach (ac_parts($r) as $p) {
        $paid = $p['paid'] !== '';
        $rows[] = ['amt' => ac_eur((int)$p['cents']), 'lab' => ac_part_label($r, (int)$p['n']), 'paid' => $paid,
            'when' => $paid ? 'bezahlt am ' . mv_date(strtotime((string)$p['paid'])) : ($p['due'] === '' ? 'jetzt fällig' : 'fällig bis ' . ac_d((string)$p['due']))];
    }
    $paid = ac_paid_cents($r);
    return ['plan' => $rows, 'sum' => 'Gesamtpreis ' . ac_eur((int)$r['total_cents']) . ((int)$r['eb'] ? ' (Early Bird)' : ((int)($r['call_price'] ?? 0) ? ' (Kennenlern-Preis)' : '')) . ' · bezahlt ' . ac_eur($paid) . ' · offen ' . ac_eur((int)$r['total_cents'] - $paid)];
}

/** Die Konfetti-Mail nach der Anzahlung (Vivien, 28.10.2026): warm, ausführlich, wie an die beste Freundin, aber professionell. Nur Deutsch. */
function ac_welcome_blocks(PDO $pdo, array $r, string $invNr = ''): array
{
    $c = ac_cfg();
    $first = forma_first_name((string)$r['name']) ?: 'meine Liebe';
    $next = ac_next_part($r);
    return [
        "Hallo $first,",
        'Konfetti, Konfetti! Du hast dir deinen Platz im Mindful Becoming Teacher Training 2027 gesichert, und ich sitze gerade hier und strahle über das ganze Gesicht.',
        'Es gibt nur vierzehn Plätze. Dass einer dieser Plätze jetzt dir gehört, bedeutet mir wirklich viel. Diese Ausbildung ist mein Herzensprojekt. Ich habe alles hineingepackt, was ich mir in meinen eigenen Ausbildungen immer gewünscht und so oft vermisst habe: Zeit, um wirklich anzukommen, eine kleine Gruppe, in der du gesehen wirst, und eine Ausbilderin, die hinter dir steht.',
        'Wir folgen dem internationalen 200-Stunden-Curriculum für Yogalehrerausbildungen, damit dir nichts fehlt: Eine vollständige 200-Stunden-Yogalehrerausbildung ist inklusive. Dazu kommen Somatic Movement, Breathwork und Meditation, Sequencing, Anatomie und Body Scanning (das liegt mir besonders am Herzen), achtsame Berührung und Rituale, Mental Load und Nervensystem und ein Modul für deine Selbstständigkeit, in dem ich dir bei deinen ersten Schritten persönlich helfe.',
        ['h' => 'Wie es jetzt weitergeht'],
        ['Dein Mitgliederbereich ist ab heute offen. Melde dich über den Knopf unten an und leg dein Passwort fest.', 'Dort findest du den Countdown bis zu unserem Start, dein Willkommensvideo und bald das Einstiegs-Workbook „Schön, dass du da bist“ mit Intentionen, Affirmationen und ganz viel Vorfreude.', 'Du hast deine eigenen privaten Notizen für Fragen, Ideen und Intentionen. Die siehst nur du.', 'Und es gibt einen persönlichen Chat mit mir. Schreib mir, wann immer dich etwas beschäftigt, ich antworte dir persönlich.'],
        ['h' => 'Dein Zahlungsplan'],
        ac_plan_block($r),
        $next ? 'Etwa zehn Tage vor jedem offenen Termin schicke ich dir einen Bezahl-Link, und in deinem Mitgliederbereich kannst du jederzeit selbst bezahlen. Bis ' . ac_d($c['due_final']) . ' ist alles beglichen. Es gibt keinen Aufschlag, dein Preis bleibt genau so.' : 'Alles ist schon bezahlt, danke dir von Herzen.',
        $invNr !== '' ? 'Die Rechnung ' . $invNr . ' über ' . ($next ? 'deine Anzahlung' : 'den Gesamtbetrag') . ' hängt als PDF an dieser Mail.' : 'Deine Rechnung bekommst du als PDF.',
        ['h' => 'Wann wir starten'],
        'Modul 1 öffnet sich am ' . mv_date($c['start_ts']) . ', gleich nach den Herbstferien. Danach kommt jeweils am 1. des Monats ein neues Modul dazu, wir treffen uns jede Woche live, und im September 2028 sehen wir uns zu unserem Wochenende in den Tiroler Alpen. Den genauen Termin sage ich dir, sobald er fix ist.',
        'Wenn irgendetwas unklar ist, antworte einfach auf diese Mail oder schreib mir im Chat.',
        'Ich freu mich so auf dich.',
    ];
}

/** $att = Rechnungs-PDF(s) aus ac_payment() als Anhang. */
function ac_mail_welcome(PDO $pdo, array $r, array $att = []): void
{
    [$link, $btn] = ac_account_link($pdo, $r);
    $blocks = ac_welcome_blocks($pdo, $r, (string)($att[0][3] ?? ''));
    $legal = ac_legal($r);
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    mm_mail((string)$r['email'], $pfx . 'Konfetti, Konfetti! Dein Platz im Teacher Training 2027 🎉', ac_mail_text($blocks, $link, $btn, $legal, false),
        ac_mail_html($r, 'willkommen in der Academy ♡', 'Dein Platz ist dir sicher', $blocks, $link, $btn, $legal, true), array_map(fn($a) => [$a[0], $a[1], $a[2]], $att), 'Vivien · Movecia Academy');
}

/** Bezahl-Link bzw. Erinnerung für eine spätere Teilzahlung. */
function ac_mail_due(array $r, array $p, bool $remind): void
{
    $first = forma_first_name((string)$r['name']);
    $amt = ac_eur((int)$p['cents']);
    $lab = ac_part_label($r, (int)$p['n']);
    $blocks = ["Hallo $first,", $remind ? "eine liebe Erinnerung: Deine $lab über $amt fürs Teacher Training ist heute fällig (" . ac_d($p['due']) . '). Wenn du schon bezahlt hast, danke dir, dann ignorier diese Mail einfach.' : "deine $lab über $amt fürs Teacher Training ist am " . ac_d($p['due']) . ' fällig. Du kannst sie gleich über den Knopf unten bezahlen oder jederzeit in deinem Mitgliederbereich.',
        ['h' => 'Dein Zahlungsplan'], ac_plan_block($r), 'Wenn sich bei dir etwas verändert hat, antworte einfach auf diese Mail, dann finden wir gemeinsam eine Lösung.'];
    $btn = $amt . ' bezahlen';
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    mm_mail((string)$r['email'], $pfx . ($remind ? 'Erinnerung: deine Zahlung fürs Teacher Training' : 'Deine nächste Zahlung fürs Teacher Training'), ac_mail_text($blocks, ac_pay_link($r), $btn, '', false), ac_mail_html($r, 'kurz zur Info ♡', AC_SHORT, $blocks, ac_pay_link($r), $btn, ''), [], 'Vivien · Movecia Academy');
}

/** Danke-Mail für eine spätere Teilzahlung, mit aktuellem Zahlungsplan und Rechnung als Anhang. */
function ac_mail_part_thanks(array $r, int $n, array $att = []): void
{
    $first = forma_first_name((string)$r['name']);
    $nr = (string)($att[0][3] ?? '');
    $p = null; foreach (ac_parts($r) as $x) if ((int)$x['n'] === $n) $p = $x;
    $blocks = ["Hallo $first,", 'danke dir, deine ' . ac_part_label($r, $n) . ($p ? ' über ' . ac_eur((int)$p['cents']) : '') . ' ist angekommen. So sieht dein Zahlungsplan jetzt aus:', ac_plan_block($r),
        $nr !== '' ? 'Die Rechnung ' . $nr . ' hängt als PDF an dieser Mail.' : 'Deine Rechnung bekommst du als PDF.', ac_next_part($r) ? 'Für die nächste Zahlung schicke ich dir rechtzeitig wieder einen Bezahl-Link.' : 'Damit ist alles bezahlt. Danke dir von Herzen ♡'];
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    mm_mail((string)$r['email'], $pfx . 'Danke, deine Zahlung ist angekommen', ac_mail_text($blocks, ac_area(), 'Mitgliederbereich', '', false), ac_mail_html($r, 'danke ♡', AC_SHORT, $blocks, ac_area(), 'Zum Mitgliederbereich', ''), array_map(fn($a) => [$a[0], $a[1], $a[2]], $att), 'Vivien · Movecia Academy');
}

function ac_practice_label(string $k): string
{
    return ['lt1' => 'weniger als 1 Jahr', '1to2' => '1–2 Jahre', 'gt2' => 'mehr als 2 Jahre', 'teach' => 'unterrichtet schon'][$k] ?? '–';
}

function ac_plan_label(string $k): string { return ac_plans()[$k]['de'] ?? $k; }

function ac_notify_viv(PDO $pdo, array $r): void
{
    $c = ac_cfg();
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    $v = "Hallo Viv,\n\n" . $r['name'] . ' (' . $r['email'] . ', ' . $r['phone'] . ') hat sich gerade einen Platz im Teacher Training gesichert: ' . mv_eur((int)$r['total_cents']) . ((int)$r['eb'] ? ' (Early-Bird)' : ((int)($r['call_price'] ?? 0) ? ' (Kennenlern-Preis)' : '')) . ', ' . ac_plan_label((string)$r['plan']) . ($r['call_id'] ? ', aus dem Kennenlerngespräch' : '')
        . ". Noch " . ac_free($pdo) . ' von ' . $c['seats'] . " Plätzen frei.\n\nZahlungsplan:\n" . implode("\n", ac_parts_lines($r)) . "\n\nEigene Praxis: " . ac_practice_label((string)$r['practice']) . ($r['practice_note'] !== '' ? "\n" . $r['practice_note'] : '')
        . "\n\nWarum sie die Ausbildung machen möchte:\n" . $r['why'] . "\n\nRücktritt möglich bis " . date('d.m.Y', ac_withdraw_until($r)) . "\n\nAlle Buchungen: " . forma_site_url() . "/verwaltung/?view=academy\n";
    mm_mail(natal_notify_email_safe(), $pfx . 'Neue Buchung: Teacher Training · ' . $r['name'], $v, mm_mail_html('Neue Buchung', nl2br(forma_h($v)), 'de'));
}

function natal_notify_email_safe(): string
{
    if (!function_exists('natal_notify_email')) require_once __DIR__ . '/natal-lib.php';
    return natal_notify_email();
}

// --- Rechnung ------------------------------------------------------------------------------------

function ac_invoice_source(PDO $pdo, int $payId): ?array
{
    ac_schema($pdo);
    $q = $pdo->prepare('SELECT p.*, b.id AS bid FROM ac_payments p JOIN ac_bookings b ON b.id = p.booking_id WHERE p.id = ?');
    $q->execute([$payId]);
    $p = $q->fetch();
    if (!$p || (int)$p['cents'] <= 0) return null;
    $r = ac_booking($pdo, (int)$p['bid']);
    $c = ac_cfg();
    $cnt = count(ac_parts($r));
    $lab = ac_part_label($r, (int)$p['n']);
    $text = AC_TITLE . ' · ' . $lab . ($cnt > 1 && !str_contains($lab, ' von ') ? ' (' . (int)$p['n'] . '. von ' . $cnt . ' Zahlungen)' : '');
    $detail = 'Ausbildung, ' . AC_HOURS . ' Std. inkl. 200-Std.-Yogalehrerausbildung, hybrid, ' . date('d.m.Y', $c['start_ts']) . ' bis September 2028, inkl. Präsenzwochenende mit Unterkunft und Verpflegung, Abschlussprüfung und Zertifikat'
        . ' · Gesamtpreis ' . mv_eur((int)$r['total_cents']) . ((int)$r['eb'] ? ' (Early-Bird)' : ((int)($r['call_price'] ?? 0) ? ' (Kennenlern-Preis)' : '')) . ($cnt > 1 ? ', davon mit dieser Zahlung ' . mv_eur((int)$p['cents']) : '');
    return ['name' => $r['name'], 'email' => $r['email'], 'session' => '', 'address' => $r['address'], 'country' => $r['country'] ?: 'AT', 'service' => date('d.m.Y', $c['start_ts']),
        'items' => [['text' => $text, 'detail' => $detail, 'qty' => 1, 'unit' => (int)$p['cents']]],
        'gross' => (int)$p['cents'], 'paid' => $p['paid_at'], 'type' => 'Movecia',
        'pay_note' => 'Der Betrag wurde am ' . date('d.m.Y', strtotime((string)$p['paid_at'])) . ' online bezahlt (Kartenzahlung über Stripe, gemeinsames Konto FORMA & Movecia). Buchungsvermerk: Leistung der Movecia Academy (Movecia – Personal Fitness).'];
}

// --- Kennenlerngespräch (kostenlos, 30 Min., Wunschtermin aus festen Zeiten) -------------------------

function ac_call_slots(PDO $pdo, int $weeks = 8): array
{
    ac_schema($pdo);
    $taken = $pdo->query("SELECT date || '|' || slot FROM ac_calls WHERE status IN ('angefragt','bestaetigt') AND date >= '" . date('Y-m-d') . "'")->fetchAll(PDO::FETCH_COLUMN);
    $busy = [];
    try { require_once __DIR__ . '/belegt.inc.php'; $busy = vb_busy($pdo, date('Y-m-d'), date('Y-m-d', strtotime('+' . $weeks . ' weeks'))); } catch (Throwable $e) {}
    $out = [];
    $tz = new DateTimeZone('Europe/Vienna');
    $d = new DateTimeImmutable('today', $tz);
    for ($i = 0; $i < $weeks * 7; $i++, $d = $d->modify('+1 day')) {
        foreach (AC_CALL_SLOTS[(int)$d->format('N')] ?? [] as $slot) {
            $ymd = $d->format('Y-m-d');
            $t = (new DateTimeImmutable($ymd . ' ' . $slot, $tz))->getTimestamp();
            if ($t < time() + 12 * 3600 || in_array($ymd . '|' . $slot, $taken, true)) continue;
            if (function_exists('vb_free') && !vb_free($busy, $ymd, vb_min($slot), AC_CALL_MINUTES)) continue;
            $out[] = ['date' => $ymd, 'slot' => $slot];
        }
    }
    return $out;
}

/** Wunsch als Text: „Montag, 8. November 2027 · Vormittag, gern um 9:30 Uhr“. */
function ac_call_wish(array $c): string
{
    $t = strtotime((string)$c['date'] . ' 12:00');
    $wd = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $p = AC_CALL_PARTS[(string)($c['daypart'] ?? '')][0] ?? '';
    $w = trim((string)($c['wish_time'] ?? ''));
    return $wd[(int)date('N', $t)] . ', ' . mv_date($t) . ($p !== '' ? ' · ' . $p : '') . ($w !== '' ? ', gern um ' . $w . (preg_match('/^\d{1,2}[:.]\d{2}$/', $w) ? ' Uhr' : '') : '');
}

/** Uhrzeit-Vorschlag zum Einbuchen: Wunschuhrzeit, sonst Beginn der Tageszeit. */
function ac_call_suggest_time(array $c): string
{
    if (preg_match('/^(\d{1,2})[:.](\d{2})$/', trim((string)($c['wish_time'] ?? '')), $m) && (int)$m[1] < 24 && (int)$m[2] < 60) return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    return AC_CALL_PARTS[(string)($c['daypart'] ?? '')][1] ?? '09:00';
}

function ac_call_when(array $c, string $lang = 'de'): string
{
    if ((string)$c['slot'] === '') return ac_call_wish($c);
    $t = strtotime($c['date'] . ' ' . $c['slot']);
    if ($lang === 'en') return date('l, j F Y', $t) . ', ' . date('g:i a', $t) . ' (Vienna time)';
    $wd = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    return $wd[(int)date('N', $t)] . ', ' . mv_date($t) . ' um ' . date('G:i', $t) . ' Uhr';
}

function ac_call_request(PDO $pdo, array $in, string $ipHash): array
{
    ac_schema($pdo);
    $date = (string)($in['date'] ?? '');
    $part = (string)($in['daypart'] ?? '');
    $wish = forma_clean((string)($in['wish_time'] ?? ''), 20);
    $name = forma_clean((string)($in['name'] ?? ''), 80);
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $phone = forma_clean((string)($in['phone'] ?? ''), 40);
    $note = trim(mb_substr((string)($in['note'] ?? ''), 0, 600));
    $tz = new DateTimeZone('Europe/Vienna');
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
    $today = new DateTimeImmutable('today', $tz);
    if (!$d || $d->format('Y-m-d') !== $date || (int)$d->format('N') > 5 || $d <= $today || $d > $today->modify('+' . AC_CALL_WEEKS . ' weeks'))
        return ['ok' => false, 'error' => 'Bitte wähl einen Wunschtag von Montag bis Freitag (ab morgen).'];
    if (!isset(AC_CALL_PARTS[$part])) return ['ok' => false, 'error' => 'Bitte wähl Vormittag, Mittag oder Abend.'];
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Bitte gib deinen Namen und deine E-Mail an.'];
    $q = $pdo->prepare('SELECT COUNT(*) FROM ac_calls WHERE ip_hash = ? AND created_at >= ?');
    $q->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$q->fetchColumn() >= 4) return ['ok' => false, 'error' => 'Bitte versuch es später noch einmal.'];
    $pdo->prepare("INSERT INTO ac_calls (date, slot, daypart, wish_time, name, email, phone, note, lang, token, ip_hash, created_at) VALUES (?,'',?,?,?,?,?,?,'de',?,?,?)")
        ->execute([$date, $part, $wish, $name, $email, $phone, $note, bin2hex(random_bytes(12)), $ipHash, date('c')]);
    $c = ac_call($pdo, (int)$pdo->lastInsertId());
    $first = forma_first_name($name);
    $lines = ["Hallo $first,", 'schön, dass du dich für das Mindful Becoming Teacher Training interessierst! Dein Wunschtermin für unser kostenloses 30-Minuten-Gespräch: ' . ac_call_wish($c) . '.',
        'Ich schaue in meinen Kalender und schicke dir in den nächsten ein, zwei Tagen die genaue Uhrzeit, zusammen mit dem Link zu unserem Videoraum. Du brauchst dafür keine App und kein Konto.'];
    try { mv_send_member_mail(['lang' => 'de', 'email' => $email], 'Dein Kennenlerngespräch zum Teacher Training', 'lass uns reden ♡', AC_SHORT, $lines, '', '', ''); } catch (Throwable $e) { error_log('AC wish mail: ' . $e->getMessage()); }
    $v = "Hallo Viv,\n\nneuer Wunschtermin für ein Kennenlerngespräch zum Teacher Training:\n\n" . ac_call_wish($c) . "\n" . $name . ' · ' . $email . ($phone !== '' ? ' · ' . $phone : '') . ($note !== '' ? "\n\nIhre Nachricht: " . $note : '')
        . "\n\nIn der Verwaltung siehst du ihn gleich oben als Pop-up und buchst ihn mit einem Tipp ein: " . forma_site_url() . "/verwaltung/\n";
    try { mm_mail(natal_notify_email_safe(), 'Wunschtermin Kennenlerngespräch: ' . $name, $v, mm_mail_html('Wunschtermin', nl2br(forma_h($v)), 'de')); } catch (Throwable $e) { error_log('AC wish viv: ' . $e->getMessage()); }
    return ['ok' => true, 'when' => ac_call_wish($c)];
}

/** Vivien bucht einen Wunschtermin ein (Datum + Uhrzeit), dann wie bisher bestätigen (Videoraum, Mail an sie). */
function ac_call_book(PDO $pdo, int $id, string $date, string $time): string
{
    $c = ac_call($pdo, $id);
    if (!$c || $c['status'] !== 'angefragt') return 'Diese Anfrage gibt es nicht mehr.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m) || (int)$m[1] > 23 || (int)$m[2] > 59) return 'Bitte Datum und Uhrzeit eintragen.';
    $time = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    if (strtotime($date . ' ' . $time) < time()) return 'Dieser Zeitpunkt liegt in der Vergangenheit.';
    $warn = '';
    try { require_once __DIR__ . '/belegt.inc.php'; $busy = vb_busy($pdo, $date, $date); if (!vb_free($busy, $date, vb_min($time), AC_CALL_MINUTES)) $warn = ' Achtung: Zu dieser Zeit hast du schon etwas anderes im Kalender.'; } catch (Throwable $e) {}
    $pdo->prepare('UPDATE ac_calls SET date = ?, slot = ? WHERE id = ?')->execute([$date, $time, $id]);
    return 'Eingebucht für ' . ac_call_when(ac_call($pdo, $id)) . '. ' . ac_call_confirm($pdo, $id) . $warn;
}

function ac_call_confirm(PDO $pdo, int $id): string
{
    $c = ac_call($pdo, $id);
    if (!$c || $c['status'] === 'abgesagt') return 'Gespräch nicht gefunden.';
    $url = (string)$c['room_url']; $host = (string)$c['host_url'];
    if ($url === '' && mm_whereby_key() !== '') {
        try {
            $end = strtotime($c['date'] . ' ' . $c['slot']) + (AC_CALL_MINUTES + 120) * 60;
            $w = mm_whereby_request('POST', '/meetings', ['endDate' => gmdate('Y-m-d\TH:i:s.000\Z', $end), 'roomMode' => 'normal', 'roomNamePrefix' => 'movecia', 'fields' => ['hostRoomUrl']], mm_whereby_key());
            $url = (string)($w['roomUrl'] ?? ''); $host = (string)($w['hostRoomUrl'] ?? $url);
        } catch (Throwable $e) { error_log('AC room: ' . $e->getMessage()); }
    }
    $pdo->prepare("UPDATE ac_calls SET status = 'bestaetigt', room_url = ?, host_url = ? WHERE id = ?")->execute([$url, $host, $id]);
    $en = $c['lang'] === 'en';
    $first = forma_first_name((string)$c['name']);
    $lines = $en ? ["Dear $first,", 'our call is confirmed: ' . ac_call_when($c, 'en') . ', 30 minutes, online by video.', $url !== '' ? 'Simply tap the button below at the time. The room works in your browser on your phone or laptop.' : 'I will send you the link to our video room before the call.', 'We will talk about your practice, what you are hoping for and whether the training is right for you. It is completely free and without obligation.']
        : ["Hallo $first,", 'unser Gespräch ist fix: ' . ac_call_when($c) . ', 30 Minuten, online per Video.', $url !== '' ? 'Tipp zur Uhrzeit einfach auf den Knopf unten. Der Raum läuft im Browser, am Handy oder am Laptop.' : 'Den Link zu unserem Videoraum schicke ich dir vor dem Gespräch.', 'Wir sprechen über deine Praxis, über deine Wünsche und darüber, ob die Ausbildung zu dir passt. Ganz kostenlos und unverbindlich.'];
    // Sie kommt über unseren eigenen Gesprächsraum (Movecia-Seite mit eingebettetem Videoraum), dort erscheint auch das Angebot
    $join = $url !== '' ? ac_call_room_link(ac_call($pdo, $id)) : '';
    mv_send_member_mail(['lang' => $c['lang'], 'email' => $c['email']], $en ? 'Confirmed: our call about the Teacher Training' : 'Bestätigt: unser Gespräch zum Teacher Training', $en ? 'see you soon ♡' : 'bis bald ♡', AC_SHORT, $lines, $join, $join !== '' ? ($en ? 'Join the video call' : 'Zum Videogespräch') : '', '');
    return $url !== '' ? 'Bestätigt, die Mail mit dem Videoraum ist unterwegs.' : 'Bestätigt. Es ist noch kein Whereby-Schlüssel hinterlegt, bitte schick ihr den Link zum Gespräch selbst.';
}

function ac_call_cancel(PDO $pdo, int $id): void
{
    $c = ac_call($pdo, $id);
    if (!$c || $c['status'] === 'abgesagt') return;
    $pdo->prepare("UPDATE ac_calls SET status = 'abgesagt' WHERE id = ?")->execute([$id]);
    $en = $c['lang'] === 'en';
    if ((string)$c['slot'] === '') { // Wunschtermin passt nicht (noch nicht eingebucht)
        $first = forma_first_name((string)$c['name']);
        mv_send_member_mail(['lang' => 'de', 'email' => $c['email']], 'Ein anderer Termin für unser Gespräch', 'neuer Termin ♡', AC_SHORT,
            ["Hallo $first,", 'danke für deinen Wunschtermin (' . ac_call_wish($c) . '). Genau da geht es sich bei mir leider nicht aus, das tut mir leid. Magst du mir einfach einen anderen Wunschtermin schicken? Ich freu mich auf unser Gespräch.'],
            mm_site() . '/academy/teacher-training/#gespraech', 'Anderen Wunschtermin schicken', '');
        return;
    }
    $first = forma_first_name((string)$c['name']);
    $link = mm_site() . '/academy/teacher-training/' . ($en ? '?lang=en' : '') . '#gespraech';
    mv_send_member_mail(['lang' => $c['lang'], 'email' => $c['email']], $en ? 'A new time for our call' : 'Ein neuer Termin für unser Gespräch', $en ? 'let\'s find a new time ♡' : 'neuer Termin ♡', AC_SHORT,
        $en ? ["Dear $first,", 'unfortunately ' . ac_call_when($c, 'en') . ' doesn\'t work for me after all. I\'m sorry! Please simply choose a new time.'] : ["Hallo $first,", 'der ' . ac_call_when($c) . ' geht sich bei mir leider doch nicht aus, das tut mir leid. Such dir bitte einfach einen neuen Termin aus.'],
        $link, $en ? 'Choose a new time' : 'Neuen Termin wählen', '');
}

// --- Mitgliederbereich vor dem Start ------------------------------------------------------------------

/** Willkommensvideo als Talking-Head-Lektion (Studio lädt direkt hinein), einmal angelegt. */
function ac_welcome_lesson(PDO $pdo): ?array
{
    $q = $pdo->prepare("SELECT * FROM mv_lessons WHERE ukey = 'academy:welcome'");
    $q->execute();
    $l = $q->fetch();
    if ($l && ($l['script_en'] !== '' || $l['title_en'] !== '')) { // nur Deutsch (Vivien, 28.10.2026)
        $pdo->prepare("UPDATE mv_lessons SET script_en = '', title_en = '', text_en = '' WHERE id = ?")->execute([$l['id']]);
        $pdo->prepare("UPDATE mv_programs SET title_en = '' WHERE audience = 'academy'")->execute();
        $q->execute(); $l = $q->fetch();
    }
    if ($l) return $l;
    $pid = (int)($pdo->query("SELECT id FROM mv_programs WHERE audience = 'academy' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
    if (!$pid) {
        $pdo->prepare("INSERT INTO mv_programs (audience, title_de, title_en, intro_de, intro_en, theme, sort, visible, created_at) VALUES ('academy', 'Teacher Training · vor dem Start', '', '', '', 'academy', 0, 1, ?)")->execute([date('c')]);
        $pid = (int)$pdo->lastInsertId();
    }
    [$de, $en] = ac_welcome_script();
    $pdo->prepare("INSERT INTO mv_lessons (program_id, title_de, title_en, text_de, text_en, cat, energy, minutes, sort, visible, created_at, script_de, script_en, ukey, due) VALUES (?,?,?,?,?,'talk','any',4,0,1,?,?,?,'academy:welcome','2026-11-08')")
        ->execute([$pid, 'Willkommen im Teacher Training', '', 'Mein Willkommen an dich, bevor wir starten.', '', date('c'), $de, '']);
    $q->execute();
    return $q->fetch() ?: null;
}

/** Teleprompter-Entwurf fürs Willkommensvideo (DE + EN, ca. 3–4 Min., Viviens Ton, fließende Sätze). */
function ac_welcome_script(): array
{
    $de = "Hallo, meine Liebe, und herzlich willkommen im Mindful Becoming Teacher Training. Ich freue mich so sehr, dass du hier bist, und ich möchte dir in diesen paar Minuten erzählen, warum mir diese Ausbildung so am Herzen liegt und was dich in den nächsten Monaten erwartet.\n\n"
        . "Als ich selbst meine ersten Ausbildungen gemacht habe, war ich oft beeindruckt von dem vielen Wissen, aber ich habe mich auch oft ein bisschen verloren gefühlt. Es ging viel um Haltungen, um Abläufe und um Programme, und viel zu wenig um den Menschen, der vor einem auf der Matte liegt. Genau das wollte ich anders machen. Deshalb gibt es diese Ausbildung nur mit vierzehn Plätzen, damit ich jede von euch wirklich sehen und begleiten kann.\n\n"
        . "Du bekommst bei mir eine vollständige 200-Stunden-Yogalehrerausbildung nach dem internationalen Curriculum, und dazu alles, was ich mir selbst immer gewünscht habe: Somatic Movement, Breathwork und Meditation, achtsame Berührung und Rituale, ganz viel Anatomie und Body Scanning, und das Wissen darüber, was Mental Load mit unserem Nervensystem macht. Und weil so viele von uns irgendwann ihre eigenen Stunden geben möchten, begleite ich dich auch beim Schritt in die Selbstständigkeit, ganz persönlich.\n\n"
        . "Bis zu unserem Start am dritten November 2027 ist noch ein bisschen Zeit, und das ist gut so. Oben siehst du den Countdown, und ich lade dich ein, diese Zeit bewusst zu nutzen. In deinen Notizen kannst du alles festhalten, was dir einfällt: deine Fragen, deine Wünsche, deine Intentionen für dieses Jahr. Die Notizen gehören nur dir, niemand sonst kann sie lesen.\n\n"
        . "Und wenn dich etwas beschäftigt, schreib mir einfach im Chat. Die Nachricht landet direkt bei mir, und ich antworte dir persönlich. Es gibt keine Frage, die zu klein ist.\n\n"
        . "Bald findest du hier auch dein erstes Workbook, „Schön, dass du da bist“. Darin setzen wir gemeinsam deine Intentionen, und du bekommst ein paar Affirmationen, die dich bis zum Start begleiten.\n\n"
        . "Ich danke dir von Herzen für dein Vertrauen. Mach es dir bis dahin schön, beweg dich, atme, und dann sehen wir uns am dritten November. Ich freue mich riesig auf dich.";
    $en = "Hello, lovely, and a very warm welcome to the Mindful Becoming Teacher Training. I am so happy you are here, and in these few minutes I would like to tell you why this training means so much to me and what is waiting for you over the coming months.\n\n"
        . "When I did my own first trainings, I was often amazed by how much I learnt, but I also often felt a little lost. So much of it was about poses, sequences and programmes, and far too little about the person lying on the mat in front of you. That is exactly what I wanted to do differently. It is why this training only has fourteen places, so that I can truly see and support each one of you.\n\n"
        . "With me you get a complete 200-hour yoga teacher training following the international curriculum, and on top of that everything I always wished I had been given: somatic movement, breathwork and meditation, mindful touch and rituals, plenty of anatomy and body scanning, and an understanding of what mental load does to our nervous system. And because so many of us dream of teaching our own classes one day, I will also walk with you, very personally, as you take your first steps into working for yourself.\n\n"
        . "There is still a little time until we begin on the third of November 2027, and that is a good thing. You can see the countdown at the top, and I invite you to use this time mindfully. In your notes you can write down anything that comes to mind: your questions, your hopes, your intentions for this year. Your notes belong to you alone, nobody else can read them.\n\n"
        . "And whenever something is on your mind, just write to me in the chat. Your message comes straight to me, and I will answer you personally. No question is too small.\n\n"
        . "Soon you will also find your first workbook here, \"So glad you're here\". In it we will set your intentions together, and you will get a few affirmations to carry you through until we start.\n\n"
        . "Thank you so much for your trust. Look after yourself until then, keep moving, keep breathing, and I will see you on the third of November. I cannot wait to meet you.";
    return [$de, $en];
}

/** Fragen & Antworten im Mitgliederbereich (auch Vorschläge im Chat). [de-Frage, de-Antwort, en-Frage, en-Antwort] */
/** „Häufig gefragt“ im Chat (Vivien, 28.10.2026, Runde 4): kurzer Text für den Chip → Frage aus ac_member_faq(). */
function ac_member_chips(): array
{
    return [
        ['Wie viel Zeit pro Woche?', 'Wie viel Zeit brauche ich pro Woche?'],
        ['Wann zahle ich die Rate?', 'Wann zahle ich den Rest?'],
        ['Was, wenn ich krank bin?', 'Was ist, wenn ich mal krank bin?'],
        ['Bekomme ich ein Zertifikat?', 'Bekomme ich ein Zertifikat?'],
        ['Brauche ich Vorerfahrung?', 'Brauche ich Vorkenntnisse?'],
        ['Wo ist das Präsenzwochenende?', 'Wann ist das Präsenzwochenende?'],
        ['Wann ist der Live-Call?', 'Wann ist der Live-Call?'],
        ['Wann geht es los?', 'Wann geht es los?'],
    ];
}

function ac_member_faq(): array
{
    $c = ac_cfg();
    return [
        ['Wann geht es los?', 'Modul 1 öffnet sich am Mittwoch, ' . mv_date($c['start_ts']) . ', gleich nach den Herbstferien. Danach kommt jeweils am 1. des Monats ein neues Modul dazu, das letzte im September 2028.', 'When do we start?', 'Module 1 opens on Wednesday, ' . mv_date($c['start_ts'], 'en') . ', right after the autumn school holidays. After that a new module opens on the 1st of every month, the last one in September 2028.'],
        ['Wie viel Zeit brauche ich pro Woche?', 'Plan etwa 10 bis 12 Stunden pro Woche ein: Videos, Workbook, deine eigene Praxis, unseren Live-Call und die Lerngruppe. Manche Wochen sind ruhiger, manche voller, und du lernst in deinem Tempo.', 'How much time do I need each week?', 'Allow about 10 to 12 hours a week: videos, workbook, your own practice, our live call and your learning group. Some weeks are quieter, some fuller, and you learn at your own pace.'],
        ['Wann ist der Live-Call?', 'Den genauen Wochentag und die Uhrzeit legen wir gemeinsam fest, damit es für möglichst alle passt. Verpasste Calls werden aufgenommen.', 'When is the live call?', 'We will choose the day and time together so that it works for as many of us as possible. Missed calls are recorded.'],
        ['Was ist der Coworking-Raum?', 'Ein Online-Raum, in dem wir zu festen Zeiten gemeinsam lernen, jede bei sich zu Hause, aber nicht allein. Dazu kommen kleine Lerngruppen, in denen ihr euch gegenseitig abfragt und unterrichtet.', 'What is the co-working space?', 'An online room where we study together at set times, each at home but not alone. On top of that there are small learning groups where you quiz and teach each other.'],
        ['Wann zahle ich den Rest?', 'Je nach deiner Wahl auf einmal, bis 15. September 2027 oder in zwei Teilen am 15. Juni und 15. September 2027. Etwa zehn Tage vorher bekommst du einen Bezahl-Link, und hier im Mitgliederbereich kannst du jederzeit selbst zahlen. Welche Möglichkeiten du hast, hängt vom Buchungstag ab: je früher, desto mehr.', 'When do I pay the rest?', 'Depending on your choice, all at once, by 15 September 2027, or in two parts on 15 June and 15 September 2027. About ten days before, you get a payment link, and you can pay here in the members area at any time. Which options you have depends on when you booked: the earlier, the more.'],
        ['Was ist, wenn ich nicht mehr teilnehmen kann?', 'Innerhalb von 14 Tagen nach der Buchung bekommst du alles zurück. Danach gilt die Storno-Staffel aus den Bedingungen, und du kannst jederzeit kostenlos eine Ersatzteilnehmerin nennen. Schreib mir einfach, wir finden gemeinsam einen Weg.', 'What if I can no longer take part?', 'Within 14 days of booking you get everything back. After that the cancellation scale in the terms applies, and you can name someone to take your place at any time at no cost. Just write to me and we will find a way together.'],
        ['Wann ist das Präsenzwochenende?', 'Im September 2028 in den Tiroler Alpen, von Freitag bis Sonntag. Den genauen Termin und die Uhrzeiten gebe ich bekannt, sobald er fix ist, gut ein Jahr im Voraus. Unterkunft und Verpflegung sind inklusive.', 'When is the in-person weekend?', 'In September 2028 in the Tyrolean Alps, from Friday to Sunday. I will announce the exact date and times as soon as it is fixed, well over a year in advance. Accommodation and meals are included.'],
        ['Was ist die Lehrprobe?', 'Im Modul Sequencing entwickelst du deine eigene Einheit von etwa 25 Minuten und unterrichtest sie beim Wochenende. Das ist keine Prüfung, sondern dein Moment. Aufgeregt sein ist ganz normal, und wir üben vorher viel gemeinsam.', 'What is the teaching practice?', 'In the sequencing module you create your own session of about 25 minutes and teach it at the weekend. It is not an exam, it is your moment. Feeling nervous is completely normal, and we practise a lot together beforehand.'],
        ['Wie läuft die Abschlussprüfung?', 'Nach dem Präsenzwochenende beantwortest du online Multiple-Choice-Fragen, zu Hause mit eingeschalteter Kamera in unserem Prüfungsraum. Danach, mit ca. 100 dokumentierten Praxisstunden, bekommst du dein Zertifikat.', 'How does the final exam work?', 'After the in-person weekend you answer multiple-choice questions online, at home with your camera on in our exam room. After that, with around 100 documented practice hours, you receive your certificate.'],
        ['Ich wohne in Deutschland, der Schweiz oder Südtirol. Passt das?', 'Natürlich. Alles außer dem Präsenzwochenende läuft online, und bezahlt wird in Euro, auch aus der Schweiz ganz einfach per Karte. Die Ausbildung findet ausschließlich auf Deutsch statt.', '', ''],
        ['Was ist, wenn ich mal krank bin?', 'Dann ruh dich zuerst aus. Videos und Workbooks kannst du jederzeit nachholen, und jeder Live-Call wird aufgenommen. Gib mir einfach im Chat Bescheid, dann schauen wir gemeinsam, wie du gut wieder reinkommst. Nur das Präsenzwochenende lässt sich nicht nachholen, dafür empfehle ich eine Seminar- oder Reisestornoversicherung.', '', ''],
        ['Bekomme ich ein Zertifikat?', 'Ja. Wenn du die Lehrprobe beim Präsenzwochenende, die Online-Abschlussprüfung und deine etwa 100 dokumentierten Praxisstunden gemacht hast, bekommst du das Zertifikat der Movecia Academy über ' . AC_HOURS . ' Stunden, inklusive der vollständigen 200-Stunden-Yogalehrerausbildung.', '', ''],
        ['Brauche ich Vorkenntnisse?', 'Etwa eineinhalb Jahre eigene Praxis sind ideal. Wenn du dir unsicher bist, schreib mir im Chat, dann schauen wir gemeinsam, was dir jetzt noch guttut.', 'Do I need experience?', 'About a year and a half of your own practice is ideal. If you are unsure, write to me in the chat and we will look together at what would help you now.'],
    ];
}

function ac_note_get(PDO $pdo, int $mid): string
{
    $q = $pdo->prepare('SELECT body FROM ac_notes WHERE member_id = ?');
    $q->execute([$mid]);
    $raw = base64_decode((string)($q->fetchColumn() ?: ''));
    if (strlen($raw) < 29) return '';
    return (string)openssl_decrypt(substr($raw, 28), 'aes-256-gcm', mv_crypt_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
}

function ac_note_set(PDO $pdo, int $mid, string $text): void
{
    $text = mb_substr($text, 0, 20000);
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($text, 'aes-256-gcm', mv_crypt_key(), OPENSSL_RAW_DATA, $iv, $tag);
    $pdo->prepare('INSERT INTO ac_notes (member_id, body, updated_at) VALUES (?,?,?) ON CONFLICT(member_id) DO UPDATE SET body = excluded.body, updated_at = excluded.updated_at')
        ->execute([$mid, base64_encode($iv . $tag . $ct), date('c')]);
}

function ac_msgs(PDO $pdo, int $bid): array
{
    // Leere Zeilen = „ohne Antwort erledigt“-Merker der Verwaltung, nie anzeigen
    $q = $pdo->prepare("SELECT id, who, text, created_at FROM ac_msgs WHERE booking_id = ? AND text <> '' ORDER BY id");
    $q->execute([$bid]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

/** Nachricht der Teilnehmerin → Verwaltung + Mail an Vivien. */
function ac_msg_send(PDO $pdo, array $r, string $text): void
{
    $text = trim(mb_substr($text, 0, 2000));
    if ($text === '') throw new RuntimeException('');
    $q = $pdo->prepare("SELECT COUNT(*) FROM ac_msgs WHERE booking_id = ? AND who = 'tn' AND created_at >= ?");
    $q->execute([$r['id'], date('c', time() - 3600)]);
    if ((int)$q->fetchColumn() >= 20) throw new RuntimeException($r['lang'] === 'en' ? 'Please give me a moment to reply.' : 'Gib mir kurz Zeit zum Antworten.');
    $pdo->prepare("INSERT INTO ac_msgs (booking_id, who, text, created_at) VALUES (?, 'tn', ?, ?)")->execute([$r['id'], $text, date('c')]);
    $v = "Hallo Viv,\n\n" . $r['name'] . " hat dir im Teacher-Training-Chat geschrieben:\n\n„" . $text . "“\n\nAntworten: " . forma_site_url() . "/verwaltung/?view=academy#chat\n";
    try { mm_mail(natal_notify_email_safe(), ((int)$r['test'] ? '[TEST] ' : '') . 'Chat Teacher Training: ' . $r['name'], $v, mm_mail_html('Neue Nachricht', nl2br(forma_h($v)), 'de')); } catch (Throwable $e) { error_log('AC chat mail: ' . $e->getMessage()); }
}

/** Antwort von Vivien → erscheint im Chat + Mail „Vivien hat dir geantwortet“. */
function ac_msg_reply(PDO $pdo, array $r, string $text): void
{
    $text = trim(mb_substr($text, 0, 4000));
    if ($text === '') return;
    $pdo->prepare("INSERT INTO ac_msgs (booking_id, who, text, created_at) VALUES (?, 'viv', ?, ?)")->execute([$r['id'], $text, date('c')]);
    $pdo->prepare("UPDATE ac_msgs SET seen = 1 WHERE booking_id = ? AND who = 'tn'")->execute([$r['id']]);
    $en = $r['lang'] === 'en';
    $first = forma_first_name((string)$r['name']);
    $blocks = $en ? ["Dear $first,", 'I have just answered your message in our chat. You can read it in your members area.'] : ["Hallo $first,", 'ich habe dir gerade im Chat geantwortet. Du findest meine Antwort in deinem Mitgliederbereich.'];
    $link = ac_area($r['lang']) . '#chat';
    mm_mail((string)$r['email'], ((int)$r['test'] ? '[TEST] ' : '') . ($en ? 'Vivien has replied to you' : 'Vivien hat dir geantwortet'), ac_mail_text($blocks, $link, $en ? 'Read my reply' : 'Antwort lesen', '', $en), ac_mail_html($r, $en ? 'a new message ♡' : 'neue Nachricht ♡', AC_SHORT, $blocks, $link, $en ? 'Read my reply' : 'Antwort lesen', ''), [], 'Vivien · Movecia Academy');
}

/** Unbeantwortete Chats (letzte Nachricht von der Teilnehmerin) für Verwaltung und Hinweis-Box. */
function ac_unanswered(PDO $pdo): int
{
    try {
        ac_schema($pdo);
        return (int)$pdo->query("SELECT COUNT(*) FROM ac_bookings b WHERE (SELECT who FROM ac_msgs m WHERE m.booking_id = b.id ORDER BY m.id DESC LIMIT 1) = 'tn'")->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** Beispiel-Buchung für die Vorschau des Mitgliederbereichs (?demo=1). */
function ac_demo_booking(): array
{
    $c = ac_cfg();
    $r = ['id' => 0, 'plan' => 'three', 'eb' => 1, 'total_cents' => $c['eb_total'], 'name' => 'Lena Beispiel', 'email' => 'lena@example.com', 'lang' => 'de', 'token' => '', 'test' => 1, 'paid_at' => date('c'), 'status' => 'bezahlt'];
    $p = ac_schedule($c['eb_total'], 'three', '2026-10-28');
    $p[0]['paid'] = date('c');
    $r['parts'] = json_encode($p);
    return $r;
}

// --- Google & Auffindbarkeit ---------------------------------------------------------------------

function ac_faq(): array
{
    $c = ac_cfg();
    return [
        ['Für wen ist die Ausbildung?', 'For whom is the training?',
         'Für Frauen, die selbst schon eine Weile achtsam in Bewegung sind und andere begleiten möchten. Ideal sind etwa eineinhalb Jahre eigene Praxis, zum Beispiel mit der Method, mit Unfold oder in den Gatherings. Du musst keine Sportlerin sein, wichtiger sind Neugier und Freude am Zuhören.',
         'For women who have been moving mindfully for a while themselves and want to guide others. About a year and a half of your own practice is ideal, for example with the Method, Unfold or the Gatherings. You don\'t need to be athletic; curiosity and a love of listening matter more.'],
        ['In welcher Sprache findet die Ausbildung statt?', '',
         'Die Ausbildung findet ausschließlich auf Deutsch statt: Videos, Workbooks, Live-Calls, Lerngruppen, das Präsenzwochenende und die Abschlussprüfung. Du solltest Deutsch gut verstehen und sprechen, damit du dich in der Gruppe wohlfühlst und später selbst auf Deutsch unterrichten kannst.', ''],
        ['Kann ich aus Deutschland, der Schweiz oder Südtirol teilnehmen?', '',
         'Ja, sehr gerne. Die Ausbildung ist für Teilnehmerinnen aus Österreich, Deutschland, der Schweiz und Südtirol gedacht. Module, Live-Calls, Coworking und Lerngruppen machst du online von zu Hause aus, nur zum Präsenzwochenende kommst du nach Tirol. Bezahlt wird in Euro, auch aus der Schweiz ganz einfach per Karte.', ''],
        ['Ist eine Yogalehrerausbildung enthalten?', 'Does it include a yoga teacher training?',
         'Ja. Du bekommst eine vollständige 200-Stunden-Yogalehrerausbildung nach internationalem Curriculum inklusive. Insgesamt umfasst die Ausbildung ' . AC_HOURS . ' Stunden, mit Somatic Movement, Breathwork und Meditation, Anatomie und Body Scanning, achtsamer Berührung und Ritualen, Mental Load und Nervensystem, der Movecia Movement Method und deiner Selbstständigkeit.',
         'Yes. A complete 200-hour yoga teacher training following an international curriculum is included. In total the training covers ' . AC_HOURS . ' hours, with somatic movement, breathwork and meditation, anatomy and body scanning, mindful touch and rituals, mental load and the nervous system, the Movecia Movement Method and building your own business.'],
        ['Wie läuft die Ausbildung ab und wie viel Zeit brauche ich?', 'How does it work and how much time do I need?',
         'Hybrid über 11 Monate: Am ' . mv_date($c['start_ts']) . ' öffnet sich Modul 1, danach jeweils am 1. des Monats ein neues Modul mit Theorie-Videos und Workbook. Jede Woche treffen wir uns live, dazu kommen Lerngruppen und ein Online-Coworking-Raum. Plan etwa 10 bis 12 Stunden pro Woche ein.',
         'Hybrid over 11 months: module 1 opens on ' . mv_date($c['start_ts'], 'en') . ', then a new module with theory videos and a workbook on the 1st of every month. We meet live every week, plus learning groups and an online co-working space. Allow about 10 to 12 hours a week.'],
        ['Was passiert beim Präsenzwochenende?', 'What happens at the in-person weekend?',
         'Im September 2028 (genauer Termin wird noch bekannt gegeben) treffen wir uns von Freitag bis Sonntag in den Tiroler Alpen, Unterkunft und Verpflegung sind inklusive. Jede Teilnehmerin hält ihre Lehrprobe, eine eigene Einheit von etwa 25 Minuten. Das Wochenende kann nicht nachgeholt werden.',
         'In September 2028 (exact date to be announced) we meet from Friday to Sunday in the Tyrolean Alps, with accommodation and meals included. Each participant teaches her own session of about 25 minutes. The weekend cannot be made up later.'],
        ['Wie bekomme ich mein Zertifikat?', 'How do I get my certificate?',
         'Nach dem Präsenzwochenende legst du online die Multiple-Choice-Abschlussprüfung ab, zu Hause mit eingeschalteter Kamera in unserem Prüfungsraum. Mit bestandener Prüfung und ca. 100 dokumentierten Praxisstunden bekommst du dein Zertifikat der Movecia Academy.',
         'After the in-person weekend you take the online multiple-choice final exam at home, with your camera on in our exam room. Once you have passed and documented around 100 practice hours, you receive your Movecia Academy certificate.'],
        ['Was kostet die Ausbildung und wie zahle ich?', 'How much does it cost and how do I pay?',
         'Early-Bird bis ' . mv_date($c['eb_end']) . ': ' . mv_eur($c['eb_total']) . ', danach ' . mv_eur($c['r_total']) . '. Alle Preise sind in Euro, bezahlt wird online per Karte (auch aus der Schweiz). Bei der Buchung zahlst du 50 % an, den Rest auf einmal oder in Teilen bis 15. September 2027, ohne Aufschlag. Je früher du buchst, desto mehr Zahlungsmöglichkeiten hast du: Bis 15. Juni 2027 kannst du in drei Teilen zahlen, bis 31. August 2027 in zwei, danach zahlst du auf einmal. Unterkunft und Verpflegung am Präsenzwochenende, Prüfung und Zertifikat sind inklusive. Pro Durchgang gibt es höchstens ' . $c['seats'] . ' Plätze.',
         'Early bird until ' . mv_date($c['eb_end'], 'en') . ': ' . ac_eur($c['eb_total'], 'en') . ', then ' . ac_eur($c['r_total'], 'en') . '. You pay a 50 % deposit when you book and the rest at once or in parts by 15 September 2027, with no surcharge. The earlier you book, the more ways you have to pay: until 15 June 2027 you can pay in three parts, until 31 August 2027 in two, and after that in one go. Accommodation and meals at the in-person weekend, the exam and the certificate are included. Each round has no more than ' . $c['seats'] . ' places.'],
    ];
}

function ac_jsonld(PDO $pdo): array
{
    $c = ac_cfg();
    $site = mm_site();
    $url = $site . '/academy/teacher-training/';
    $free = ac_free($pdo);
    $avail = $free > 0 ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/SoldOut';
    $org = ['@type' => 'EducationalOrganization', '@id' => $site . '/academy/#org', 'name' => 'Movecia Academy', 'url' => $site . '/academy/', 'logo' => $site . '/img/movecia/movecia-zeichen-1024.png', 'parentOrganization' => ['@id' => $site . '/#movecia'],
        'founder' => ['@type' => 'Person', 'name' => 'Vivien Malonga'], 'areaServed' => [['@type' => 'Country', 'name' => 'Österreich'], ['@type' => 'Country', 'name' => 'Deutschland'], ['@type' => 'Country', 'name' => 'Schweiz'], ['@type' => 'AdministrativeArea', 'name' => 'Südtirol']]];
    $area = [['@type' => 'Country', 'name' => 'Österreich', 'identifier' => 'AT'], ['@type' => 'Country', 'name' => 'Deutschland', 'identifier' => 'DE'], ['@type' => 'Country', 'name' => 'Schweiz', 'identifier' => 'CH'], ['@type' => 'AdministrativeArea', 'name' => 'Südtirol', 'containedInPlace' => ['@type' => 'Country', 'name' => 'Italien', 'identifier' => 'IT']]];
    $offers = [];
    if ($c['eb']) $offers[] = ['@type' => 'Offer', 'name' => 'Early Bird', 'price' => number_format($c['eb_total'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'validThrough' => $c['eb_until'], 'availability' => $avail, 'url' => $url . '#buchen', 'category' => 'Paid', 'areaServed' => $area, 'eligibleRegion' => $area];
    $offers[] = ['@type' => 'Offer', 'name' => 'Regulär', 'price' => number_format($c['r_total'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'availability' => $avail, 'url' => $url . '#buchen', 'category' => 'Paid', 'areaServed' => $area, 'eligibleRegion' => $area] + ($c['eb'] ? ['validFrom' => date('Y-m-d', $c['eb_end'] + 86400)] : []);
    $course = ['@context' => 'https://schema.org', '@type' => 'Course', '@id' => $url . '#course', 'name' => AC_TITLE,
        'description' => AC_HOURS . '-Stunden-Ausbildung ausschließlich auf Deutsch (hybrid, 11 Monate, online aus Österreich, Deutschland, der Schweiz und Südtirol) inkl. vollständiger 200-Stunden-Yogalehrerausbildung nach internationalem Curriculum, Somatic Movement, Breathwork & Meditation, Anatomie & Body Scanning, achtsame Berührung & Rituale, Mental Load & Nervensystem, Selbstständigkeit. Online-Module, wöchentlicher Live-Call, Lerngruppen und Präsenzwochenende in den Tiroler Alpen. Max. 14 Plätze.',
        'provider' => $org, 'inLanguage' => 'de', 'availableLanguage' => ['@type' => 'Language', 'name' => 'Deutsch', 'alternateName' => 'de'],
        'audience' => ['@type' => 'Audience', 'audienceType' => 'Deutschsprachige Teilnehmerinnen aus Österreich, Deutschland, der Schweiz und Südtirol', 'geographicArea' => $area], 'image' => $site . '/img/movecia/og-academy.jpg', 'url' => $url,
        'educationalCredentialAwarded' => 'Zertifikat der Movecia Academy (' . AC_HOURS . ' Stunden)', 'timeRequired' => 'P11M', 'numberOfCredits' => AC_HOURS,
        'coursePrerequisites' => 'Etwa 1,5 Jahre eigene Bewegungspraxis empfohlen',
        'teaches' => array_map(fn($h) => $h[0], ac_hours()),
        'offers' => $offers,
        'hasCourseInstance' => [['@type' => 'CourseInstance', 'courseMode' => 'Blended', 'startDate' => $c['start'], 'endDate' => $c['end'], 'inLanguage' => 'de',
            'courseSchedule' => ['@type' => 'Schedule', 'repeatFrequency' => 'P1W', 'startDate' => $c['start'], 'endDate' => $c['end']],
            'courseWorkload' => 'PT' . AC_HOURS . 'H', 'maximumAttendeeCapacity' => $c['seats'], 'instructor' => ['@type' => 'Person', 'name' => 'Vivien Malonga'],
            'location' => [['@type' => 'VirtualLocation', 'url' => $url], ['@type' => 'Place', 'name' => 'Präsenzwochenende in den Tiroler Alpen', 'address' => ['@type' => 'PostalAddress', 'addressRegion' => 'Tirol', 'addressCountry' => 'AT']]]]]];
    $faq = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[2]]], ac_faq())];
    $bc = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Movecia', 'item' => $site . '/'], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Academy', 'item' => $site . '/academy/'], ['@type' => 'ListItem', 'position' => 3, 'name' => 'Teacher Training', 'item' => $url]]];
    return [$course, $faq, $bc];
}

// --- Gemeinsame Gestaltung: Academy-Siegel, Early-Bird-Vögel ------------------------------------------

// --- Academy-Logo (Vivien, 28.10.2026, Runde 3: eigenes Untermarken-Logo, keine Vögel) -------------------
// Drei Entwürfe: 'bogen' (Kiesel in einem Torbogen = Schwelle in die Ausbildung), 'zwei' (zwei Kiesel:
// Lehrerin und Schülerin), 'ring' (Siegel mit ACADEMY / Movecia im Kreis, STANDARD seit Runde 4). AC_LOGO wählt den Standard.
// Unter ca. 70 px wird der Ring ohne Schrift gezeichnet ('ring-s'), sonst wäre sie nicht lesbar.
const AC_LOGO = 'ring'; // Vivien, 28.10.2026 (Runde 4): Entwurf C „Ring“ ist das Academy-Logo

/** Movecia-Kiesel (weiche Form, Umriss, Punkt) als <g>, zentriert auf (cx, cy), Breite w. */
function ac_pebble(float $cx, float $cy, float $w, string $line, string $soft, string $dot, float $sw = 3.6, bool $withSoft = true): string
{
    $k = $w / 94;
    return '<g transform="translate(' . round($cx - 64 * $k, 2) . ' ' . round($cy - 61 * $k, 2) . ') scale(' . round($k, 4) . ')">'
        . ($withSoft ? '<path fill="' . $soft . '" d="M56 32 C 76 29, 92 44, 91 64 C 90 84, 72 96, 53 92 C 34 88, 25 72, 29 53 C 32 40, 43 33, 56 32 Z" transform="translate(-6 -4)"/>' : '')
        . '<path fill="none" stroke="' . $line . '" stroke-width="' . $sw . '" stroke-linejoin="round" d="M44 30 C 62 20, 90 28, 98 50 C 104 68, 92 78, 84 88 C 76 100, 50 98, 36 88 C 22 78, 22 58, 28 46 C 32 38, 38 33, 44 30 Z" transform="translate(7 4) translate(63 60) scale(0.85) translate(-63 -60)"/>'
        . '<circle fill="' . $dot . '" cx="81" cy="54" r="9"/></g>';
}

/** Farben hell (auf Beige) oder auf Pflaume. */
function ac_logo_colors(bool $onPlum): array
{
    return $onPlum ? ['line' => '#F7F1EE', 'soft' => 'rgba(232,214,202,.55)', 'dot' => '#F3E2D8', 'fill' => 'rgba(251,245,247,.10)', 'thin' => 'rgba(251,245,247,.55)', 'text' => '#F7F1EE', 'sub' => '#E4DCD1']
        : ['line' => '#4A3040', 'soft' => '#E6DBCF', 'dot' => '#4A3040', 'fill' => '#F6EAE6', 'thin' => '#C9A3AE', 'text' => '#2E1D27', 'sub' => '#4A3040'];
}

/** Nur das Zeichen (ohne Schrift). Gibt [innerSVG, breite, höhe] zurück. */
function ac_logo_mark(string $v, bool $onPlum = false): array
{
    $c = ac_logo_colors($onPlum);
    if ($v === 'zwei') {
        $o = '<g transform="rotate(-8 70 62)">' . ac_pebble(56, 60, 82, $c['line'], $c['soft'], $c['dot'], 3.4, false) . '</g>'
           . ac_pebble(92, 70, 56, $c['line'], $c['soft'], $c['dot'], 4.2, true);
        return [$o, 140, 124];
    }
    if ($v === 'ring-s') {
        $o = '<circle cx="100" cy="100" r="94" fill="' . $c['fill'] . '" stroke="' . $c['line'] . '" stroke-width="5"/>'
           . '<circle cx="100" cy="100" r="78" fill="none" stroke="' . $c['thin'] . '" stroke-width="2.4"/>'
           . ac_pebble(100, 100, 104, $c['line'], $c['soft'], $c['dot'], 4.6);
        return [$o, 200, 200];
    }
    if ($v === 'ring') {
        static $n = 0; $n++;
        $o = '<defs><path id="acT' . $n . '" d="M 33 100 A 67 67 0 0 1 167 100"/><path id="acB' . $n . '" d="M 36 100 A 64 64 0 0 0 164 100"/></defs>'
           . '<circle cx="100" cy="100" r="96" fill="' . $c['fill'] . '" stroke="' . $c['line'] . '" stroke-width="1.6"/>'
           . '<circle cx="100" cy="100" r="86" fill="none" stroke="' . $c['thin'] . '" stroke-width="1"/>'
           . '<text font-family="Hanken, Hanken Grotesk, Helvetica, Arial, sans-serif" font-size="16.5" font-weight="500" letter-spacing="6" fill="' . $c['line'] . '"><textPath href="#acT' . $n . '" startOffset="51%" text-anchor="middle">ACADEMY</textPath></text>'
           . '<text font-family="Cormorant, Cormorant Garamond, Georgia, serif" font-style="italic" font-size="21" fill="' . $c['line'] . '" letter-spacing="1.2"><textPath href="#acB' . $n . '" startOffset="50%" text-anchor="middle" dominant-baseline="hanging">Movecia</textPath></text>'
           . '<circle cx="34" cy="100" r="2.2" fill="' . $c['thin'] . '"/><circle cx="166" cy="100" r="2.2" fill="' . $c['thin'] . '"/>'
           . ac_pebble(100, 98, 70, $c['line'], $c['soft'], $c['dot'], 3.6);
        return [$o, 200, 200];
    }
    // 'bogen': Torbogen mit Schwelle, innen ein zweiter feiner Bogen, Kiesel in der Tür, kleiner Stern über dem Scheitel
    $o = '<path d="M16 150 V66 A46 46 0 0 1 108 66 V150" fill="' . $c['fill'] . '" stroke="' . $c['line'] . '" stroke-width="2.6" stroke-linecap="round"/>'
       . '<path d="M26 150 V68 A36 36 0 0 1 98 68 V150" fill="none" stroke="' . $c['thin'] . '" stroke-width="1.2" stroke-dasharray="0.1 4.4" stroke-linecap="round"/>'
       . '<path d="M6 150 H118" stroke="' . $c['line'] . '" stroke-width="2.6" stroke-linecap="round"/>'
       . ac_pebble(62, 104, 56, $c['line'], $c['soft'], $c['dot'], 4.0)
       . '<path transform="translate(62 6)" d="M0 -6c.6 3.4 2.6 5.4 6 6-3.4.6-5.4 2.6-6 6-.6-3.4-2.6-5.4-6-6 3.4-.6 5.4-2.6 6-6z" fill="' . $c['thin'] . '"/>';
    return [$o, 124, 156];
}

/** Zeichen als SVG-Element (für Seiten). $cls für Größe/Position. */
function ac_seal(string $cls = '', ?string $v = null, bool $onPlum = false): string
{
    $v = $v ?? AC_LOGO;
    $small = $v === 'ring' && preg_match('/\b(mini|acband-mark)\b/', $cls);
    [$o, $w, $h] = ac_logo_mark($small ? 'ring-s' : $v, $onPlum);
    return '<svg class="ac-seal ac-mark-' . $v . ' ' . forma_h($cls) . '" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true">' . $o . '</svg>';
}

/** Logo als eigenständige SVG-Datei: 'mark' oder 'stack' (Zeichen über Schriftzug „Movecia / ACADEMY / Mindful Becoming Teacher Training“). */
function ac_logo_file(string $v, string $layout = 'stack', bool $onPlum = false): string
{
    [$o, $w, $h] = ac_logo_mark($v, $onPlum);
    $c = ac_logo_colors($onPlum);
    if ($layout === 'mark') return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="' . (int)round(1024 * $h / $w) . '" viewBox="0 0 ' . $w . ' ' . $h . '">' . $o . '</svg>';
    $W = 420; $mw = $v === 'ring' ? 150 : ($v === 'zwei' ? 150 : 118); $mh = $mw * $h / $w;
    $y = 20 + $mh;
    $txt = '<text x="' . ($W / 2) . '" y="' . ($y + 66) . '" text-anchor="middle" font-family="Cormorant, Cormorant Garamond, Georgia, serif" font-weight="500" font-size="62" fill="' . $c['text'] . '">Movecia<tspan fill="' . $c['dot'] . '">.</tspan></text>'
        . '<text x="' . ($W / 2 + 6) . '" y="' . ($y + 104) . '" text-anchor="middle" font-family="Hanken, Hanken Grotesk, Helvetica, Arial, sans-serif" font-weight="500" font-size="21" letter-spacing="12" fill="' . $c['sub'] . '">ACADEMY</text>'
        . '<path d="M' . ($W / 2 - 70) . ' ' . ($y + 122) . ' H' . ($W / 2 + 70) . '" stroke="' . $c['thin'] . '" stroke-width="1"/>'
        . '<text x="' . ($W / 2 + 1.5) . '" y="' . ($y + 146) . '" text-anchor="middle" font-family="Hanken, Hanken Grotesk, Helvetica, Arial, sans-serif" font-size="12.5" letter-spacing="3" fill="' . $c['sub'] . '">MINDFUL BECOMING TEACHER TRAINING</text>';
    $H = (int)round($y + 166);
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $W * 2 . '" height="' . $H * 2 . '" viewBox="0 0 ' . $W . ' ' . $H . '">'
        . '<svg x="' . (($W - $mw) / 2) . '" y="20" width="' . $mw . '" height="' . round($mh, 2) . '" viewBox="0 0 ' . $w . ' ' . $h . '">' . $o . '</svg>' . $txt . '</svg>';
}

/** Academy-Kopfband (Pflaume) mit Logo für alle Academy-Seiten. */
function ac_band(string $sub = 'Mindful Becoming Teacher Training'): string
{
    return '<div class="acband"><div class="acband-in"><a href="/academy/" class="acband-l">' . ac_seal('acband-mark', null, true)
        . '<span><b>Movecia <i>Academy</i></b><small>' . forma_h($sub) . '</small></span></a><span class="acband-r">nur 14 Plätze</span></div></div>';
}

/** Countdown-Banner (Vivien, 29.10.2026): bis Ende Early-Bird, danach bis Ausbildungsstart; Tage/Std./Min./Sek. live, mit Preis und Knopf. */
function ac_countdown(): string
{
    $c = ac_cfg();
    $now = time();
    $eb = $c['eb'];
    $to = $eb ? $c['eb_end'] + 59 : $c['start_ts'];
    if ($to <= $now) return '';
    $eu = fn(int $x) => '€&nbsp;' . number_format($x / 100, 0, ',', '.');
    $u = fn(string $k, string $l) => '<span class="acc-u"><b data-k="' . $k . '">00</b><small>' . $l . '</small></span>';
    $sep = '<i class="acc-sep" aria-hidden="true">:</i>';
    $head = $eb ? '<span class="acc-k">' . ac_birds() . 'Early Bird</span><span class="acc-h">endet in</span>' : '<span class="acc-k">Teacher Training</span><span class="acc-h">startet in</span>';
    $side = $eb ? '<span class="acc-p"><b>' . $eu($c['eb_total']) . '</b><s>' . $eu($c['r_total']) . '</s></span>' : '<span class="acc-p"><b>' . mv_date($c['start_ts']) . '</b></span>';
    return '<div class="acc" data-to="' . (int)$to . '" data-now="' . $now . '" role="timer">'
        . '<svg class="acc-arc" viewBox="0 0 200 120" aria-hidden="true"><path d="M20 120V70a80 80 0 0 1 160 0v50" fill="none" stroke="currentColor" stroke-width="1.2"/><path d="M44 120V74a56 56 0 0 1 112 0v46" fill="none" stroke="currentColor" stroke-width="1"/></svg>'
        . '<div class="acc-lab">' . $head . '</div>'
        . '<div class="acc-t">' . $u('d', 'Tage') . $sep . $u('h', 'Std.') . $sep . $u('m', 'Min.') . $sep . $u('s', 'Sek.') . '</div>'
        . '<div class="acc-side">' . $side . '<a class="acc-btn" href="/academy/teacher-training/#buchen">Platz sichern →</a></div>'
        . '</div>'
        . '<script>(function(){var e=document.currentScript.previousElementSibling,to=+e.dataset.to*1000,sk=Date.now()-e.dataset.now*1000,q=function(k){return e.querySelector(\'[data-k="\'+k+\'"]\')};'
        . 'function t(){var ms=Math.max(0,to-(Date.now()-sk)),x=Math.floor(ms/1000),p=function(n){return n<10?"0"+n:""+n};'
        . 'var v={d:""+Math.floor(x/86400),h:p(Math.floor(x%86400/3600)),m:p(Math.floor(x%3600/60)),s:p(x%60)};for(var k in v){var el=q(k);if(el.textContent!==v[k]){el.textContent=v[k];el.classList.remove("hop");void el.offsetWidth;el.classList.add("hop")}}if(ms>0)setTimeout(t,1000-(Date.now()%1000))}t()})();</script>';
}

/** Kleine handgezeichnete Vögel fürs Early-Bird-Abzeichen. */
// Winterzeit für die Vögelchen (Vivien, 30.10.2026: „bis Jänner lassen“): 1. November bis 15. Jänner
function ac_winter(): bool
{
    $md = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('md');
    return $md >= '1101' || $md <= '0115';
}

// Vögelchen mit Mütze und Schal auf Early-Bird-Kästchen der übrigen Movecia-Seiten (nur in der Winterzeit;
// ?winter=1 zeigt sie zur Vorschau). $cls positioniert die Gruppe (Styles .mvb in cute.css).
function mv_perch_birds(string $cls = ''): string
{
    if (!ac_winter() && empty($_GET['winter'])) return '';
    $GLOBALS['ac_force_winter'] = true;
    $b = ac_birds();
    unset($GLOBALS['ac_force_winter']);
    return str_replace('class="birds"', 'class="birds mvb ' . $cls . '"', $b);
}

function ac_birds(): string
{
    $b = fn(string $cls, string $tr) => '<svg class="bird ' . $cls . '" viewBox="0 0 40 30" style="' . $tr . '" aria-hidden="true"><path d="M8 18c3-6 10-8 16-5 2-4 6-6 10-5-2 1-3 3-3 5 1 6-5 11-13 11-6 0-10-2-12-6z" fill="currentColor" opacity=".92"/><path class="wing" d="M14 16c2-6 7-9 12-8-2 4-5 7-12 8z" fill="#F5F1EB" opacity=".75"/><circle cx="30" cy="11" r="1.1" fill="#2A2026"/><path d="M34 11.5l3 .6-3 1" fill="none" stroke="#E8B48F" stroke-width="1.4" stroke-linecap="round"/><path d="M14 27l-2 2M18 28l-1 2" stroke="currentColor" stroke-width="1" stroke-linecap="round"/></svg>';
    // Winter (Vivien, 30.10.2026): vom 1. November bis 15. Jänner tragen die Vögelchen Strickmütze und Schal
    if (ac_winter() || !empty($GLOBALS['ac_force_winter'])) {
        $hat = [['#FBF9F6', '#B58C99'], ['#B58C99', '#FBF9F6'], ['#4A3040', '#E6DBCF']];
        $i = 0;
        $b = function (string $cls, string $tr) use (&$i, $hat) {
            [$h, $bd] = $hat[$i++ % 3];
            return '<svg class="bird ' . $cls . '" viewBox="0 0 40 30" style="' . $tr . '" aria-hidden="true"><path d="M8 18c3-6 10-8 16-5 2-4 6-6 10-5-2 1-3 3-3 5 1 6-5 11-13 11-6 0-10-2-12-6z" fill="currentColor" opacity=".92"/><path class="wing" d="M14 16c2-6 7-9 12-8-2 4-5 7-12 8z" fill="#F5F1EB" opacity=".75"/><circle cx="30" cy="11" r="1.1" fill="#2A2026"/><path d="M34 11.5l3 .6-3 1" fill="none" stroke="#E8B48F" stroke-width="1.4" stroke-linecap="round"/>'
                . '<path d="M22.6 14.2c2.4 1.9 5.8 1.8 8.2 0l.5 2c-2.8 1.8-6.4 1.8-9.2-.1z" fill="' . $bd . '"/><path d="M24 15.9l-1.4 4.8 2.2.4 1-4.6z" fill="' . $bd . '"/>'
                . '<path d="M25.6 9.6c-.3-3.6 1.8-6 4.6-6s4.9 2.4 4.4 5.8z" fill="' . $h . '" stroke="' . $bd . '" stroke-width=".5"/><path d="M25.3 9.4c3-.9 6.5-.9 9.6-.3l.1 1.6c-3.2-.6-6.6-.6-9.8.3z" fill="' . $bd . '"/><circle cx="30.4" cy="3" r="1.8" fill="' . $bd . '"/>'
                . '<path d="M14 27l-2 2M18 28l-1 2" stroke="currentColor" stroke-width="1" stroke-linecap="round"/></svg>';
        };
    }
    return '<span class="birds" aria-hidden="true">' . $b('b1', '') . $b('b2', '') . $b('b3', '') . '</span>';
}


// --- Interessentinnen-Liste Teacher Training (Vivien, 28.10.2026) -----------------------------------
// Anmeldung über api/wh-subscribe.php (source academy, Double-Opt-in, mm_subscribers.academy_at/practice),
// auch Warteliste, wenn alle Plätze vergeben sind. Nur Deutsch.

function ac_interest_list(PDO $pdo): array
{
    mm_schema($pdo);
    return $pdo->query("SELECT * FROM mm_subscribers WHERE (academy_at <> '' OR source = 'teacher') AND status = 'bestaetigt' ORDER BY COALESCE(NULLIF(academy_at, ''), confirmed_at, created_at) DESC")->fetchAll();
}

function ac_interest_unsub(array $sub): string { return mm_site() . '/api/wh-unsubscribe.php?t=' . $sub['token'] . '&de=1'; }

function ac_interest_confirm_mail(array $sub, string $link): bool
{
    $first = forma_first_name((string)$sub['name']) ?: 'meine Liebe';
    $r = ['lang' => 'de', 'test' => 0];
    $blocks = ["Hallo $first,", 'schön, dass du dich für das Mindful Becoming Teacher Training interessierst! Bitte bestätige noch kurz deine E-Mail-Adresse, dann schicke ich dir gleich alle Infos zur Ausbildung.', 'Falls du dich nicht eingetragen hast, ignorier diese Mail einfach.'];
    return mm_mail((string)$sub['email'], 'Bitte bestätige deine E-Mail fürs Teacher Training 🌿', ac_mail_text($blocks, $link, 'Ja, ich bestätige meine E-Mail', '', false),
        ac_mail_html($r, 'nur noch ein Klick ♡', 'Teacher Training 2027', $blocks, $link, 'Ja, ich bestätige meine E-Mail', ''), [], 'Vivien · Movecia Academy');
}

/** Infomail nach der Bestätigung: was dich erwartet, Preise vom Server, Link zum Buchen und zum Kennenlerngespräch. */
function ac_interest_info_blocks(array $sub): array
{
    $c = ac_cfg();
    $full = ac_free(forma_db()) <= 0;
    $first = forma_first_name((string)$sub['name']) ?: 'meine Liebe';
    return [
        "Hallo $first,",
        $full ? 'danke, dass du dich auf die Warteliste fürs Teacher Training 2027 gesetzt hast. Gerade sind alle vierzehn Plätze vergeben. Wird ein Platz frei, erfährst du es als Erste.' : 'wie schön, dass du dich für das Mindful Becoming Teacher Training interessierst! Hier ist alles, was du wissen musst, ganz in Ruhe zum Nachlesen.',
        ['h' => 'Was dich erwartet'],
        ['Eine Ausbildung mit ' . AC_HOURS . ' Stunden, inklusive vollständiger 200-Stunden-Yogalehrerausbildung nach internationalem Curriculum', 'Somatic Movement, Breathwork und Meditation, ganz viel Anatomie und Body Scanning, achtsame Berührung und Rituale', 'Mental Load und Nervensystem, und wie du Stunden hältst, ohne deine eigene Energie zu verbrennen', 'Deine Selbstständigkeit: faire Preise, sichtbar werden, mit meiner persönlichen Hilfe', '11 Monate hybrid ab ' . mv_date($c['start_ts']) . ', wöchentlicher Live-Call, Coworking und Lerngruppen, ein Präsenzwochenende in den Tiroler Alpen', 'Ausschließlich auf Deutsch, online aus Österreich, Deutschland, der Schweiz und Südtirol'],
        ['h' => 'Preis'],
        ['box' => $c['eb'] ? ['Early Bird bis ' . mv_date($c['eb_end']) . ': ' . ac_eur($c['eb_total']), 'danach ' . ac_eur($c['r_total']), 'bei der Buchung 50 % Anzahlung, den Rest auf einmal oder in Teilen bis 15. September 2027, ohne Aufschlag'] : [ac_eur($c['r_total']), 'bei der Buchung 50 % Anzahlung, den Rest bis 15. September 2027']],
        'Es gibt nur vierzehn Plätze. Wenn du magst, lernen wir uns vorher in einem kostenlosen Gespräch kennen (30 Minuten, online). Den Link findest du auf der Seite.',
        'Ich freu mich, von dir zu hören.',
    ];
}

function ac_interest_info_mail(array $sub): bool
{
    $r = ['lang' => 'de', 'test' => 0];
    $blocks = ac_interest_info_blocks($sub);
    $link = mm_site() . '/academy/teacher-training/#buchen';
    $legal = 'Du bekommst diese E-Mail, weil du dich auf moveciamovement.com für das Teacher Training eingetragen hast. Abmelden: ' . ac_interest_unsub($sub);
    return mm_mail((string)$sub['email'], 'Teacher Training 2027: alle Infos für dich 🌿', ac_mail_text($blocks, $link, 'Zur Ausbildung', $legal, false),
        ac_mail_html($r, 'schön, dass du da bist ♡', 'Mindful Becoming Teacher Training', $blocks, $link, 'Zur Ausbildung & zum Kennenlerngespräch', $legal), [], 'Vivien · Movecia Academy');
}

/** „Noch 2 Wochen Early Bird“ (Vivien, 28.10.2026): am 17.3.2027 um 10:00 Wien, an bestätigte Interessentinnen ohne Buchung, genau einmal. */
function ac_eb_reminder_at(): int { $c = ac_cfg_raw(); return mv_ts(date('Y-m-d', strtotime($c['eb_until'] . ' -14 days')), '10:00'); }

function ac_eb_reminder_blocks(array $sub): array
{
    $c = ac_cfg();
    $first = forma_first_name((string)$sub['name']) ?: 'meine Liebe';
    return [
        "Hallo $first,",
        'nur eine kleine Erinnerung, ganz ohne Druck: Der Early-Bird-Preis fürs Teacher Training 2027 gilt noch zwei Wochen, bis ' . mv_date($c['eb_end']) . '.',
        ['box' => ['Early Bird: ' . ac_eur($c['eb_total']) . ' (bis ' . mv_date($c['eb_end']) . ')', 'danach: ' . ac_eur($c['r_total']), 'bei der Buchung 50 % Anzahlung, den Rest bis 15. September 2027, ohne Aufschlag']],
        'Es gibt nur vierzehn Plätze. Wenn du noch Fragen hast, buch dir gern ein kostenloses Kennenlerngespräch, dann reden wir in Ruhe darüber.',
        'Alles Liebe, und egal wie du dich entscheidest: Schön, dass du da bist.',
    ];
}

function ac_eb_reminder_mail(array $sub): bool
{
    $r = ['lang' => 'de', 'test' => 0];
    $blocks = ac_eb_reminder_blocks($sub);
    $link = mm_site() . '/academy/teacher-training/#buchen';
    $legal = 'Du bekommst diese E-Mail, weil du dich auf moveciamovement.com für das Teacher Training eingetragen hast. Abmelden: ' . ac_interest_unsub($sub);
    return mm_mail((string)$sub['email'], 'Noch 2 Wochen Early Bird fürs Teacher Training 🕊', ac_mail_text($blocks, $link, 'Platz sichern', $legal, false),
        ac_mail_html($r, 'noch zwei Wochen ♡', 'Noch 2 Wochen Early Bird', $blocks, $link, 'Platz sichern', $legal), [], 'Vivien · Movecia Academy');
}

function ac_eb_reminder_run(PDO $pdo): void
{
    $c = ac_cfg();
    if (time() < ac_eb_reminder_at() || !$c['eb']) return;
    $st = mm_cfg('academy-mails') ?? [];
    if (!empty($st['eb2w_done'])) return;
    $sent = $st['eb2w'] ?? [];
    $booked = array_map('strtolower', $pdo->query("SELECT email FROM ac_bookings WHERE status = 'bezahlt'")->fetchAll(PDO::FETCH_COLUMN));
    $n = 0;
    foreach (ac_interest_list($pdo) as $sub) {
        if (in_array((int)$sub['id'], $sent, true) || in_array(strtolower((string)$sub['email']), $booked, true)) continue;
        if ($n++ >= 40) break; // gedrosselt, Rest beim nächsten Durchlauf
        $sent[] = (int)$sub['id'];
        mm_save_cfg('academy-mails', ['eb2w' => $sent] + $st); // vor dem Versand merken: nie doppelt
        try { ac_eb_reminder_mail($sub); } catch (Throwable $e) { error_log('AC EB mail: ' . $e->getMessage()); }
    }
    if ($n < 40) mm_save_cfg('academy-mails', ['eb2w' => $sent, 'eb2w_done' => date('c')] + $st);
}


/** Kalenderdatei fürs Kennenlerngespräch (für Vivien mit Gastgeberinnen-Link). */
function ac_call_ics(array $c, bool $host = false): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $f = fn(DateTime $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $esc = fn(string $v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $v);
    $from = new DateTime($c['date'] . ' ' . $c['slot'], $tz);
    $to = (clone $from)->modify('+' . AC_CALL_MINUTES . ' minutes');
    $where = $host ? ((string)$c['host_url'] ?: (string)$c['room_url']) : ((string)$c['room_url'] !== '' ? ac_call_room_link($c) : '');
    $sum = $host ? 'Kennenlerngespräch Teacher Training: ' . $c['name'] : 'Kennenlerngespräch mit Vivien (Movecia Academy)';
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Movecia//Academy//DE\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:ac-call-{$c['id']}@moveciamovement.com\r\nDTSTAMP:" . $f(new DateTime('now', $tz))
        . "\r\nDTSTART:" . $f($from) . "\r\nDTEND:" . $f($to) . "\r\nSUMMARY:" . $esc($sum) . "\r\nLOCATION:" . $esc($where ?: 'Online per Video')
        . "\r\nDESCRIPTION:" . $esc(($host ? $c['email'] . ($c['phone'] !== '' ? ' · ' . $c['phone'] : '') . ($c['note'] !== '' ? ' · ' . $c['note'] : '') . ' · ' : '') . 'Videoraum: ' . ($where ?: 'Link per E-Mail')) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}

/** Warteliste automatisch (Vivien, 28.10.2026): War die Ausbildung voll und wird wieder ein Platz frei, bekommen alle Interessentinnen ohne Buchung eine Mail. */
function ac_waitlist_run(PDO $pdo): void
{
    $st = mm_cfg('academy-mails') ?? [];
    $free = ac_free($pdo);
    if ($free <= 0) { if (empty($st['was_full'])) mm_save_cfg('academy-mails', ['was_full' => date('c')] + $st); return; }
    if (empty($st['was_full'])) return;
    unset($st['was_full']);
    mm_save_cfg('academy-mails', $st + ['wait_sent' => date('c')]);
    $booked = array_map('strtolower', $pdo->query("SELECT email FROM ac_bookings WHERE status IN ('bezahlt','offen')")->fetchAll(PDO::FETCH_COLUMN));
    $r = ['lang' => 'de', 'test' => 0];
    $link = mm_site() . '/academy/teacher-training/#buchen';
    foreach (ac_interest_list($pdo) as $sub) {
        if (in_array(strtolower((string)$sub['email']), $booked, true)) continue;
        $first = forma_first_name((string)$sub['name']) ?: 'meine Liebe';
        $blocks = ["Hallo $first,", 'gute Nachrichten: Im Teacher Training 2027 ist gerade wieder ein Platz frei geworden. Du stehst auf meiner Warteliste, deshalb erfährst du es als Erste.', 'Wer zuerst bucht, bekommt den Platz. Wenn du noch Fragen hast, schreib mir einfach.'];
        $legal = 'Du bekommst diese E-Mail, weil du auf der Warteliste fürs Teacher Training stehst. Abmelden: ' . ac_interest_unsub($sub);
        try { mm_mail((string)$sub['email'], 'Ein Platz im Teacher Training ist frei geworden 🕊', ac_mail_text($blocks, $link, 'Platz sichern', $legal, false), ac_mail_html($r, 'ein Platz ist frei ♡', 'Teacher Training 2027', $blocks, $link, 'Platz sichern', $legal), [], 'Vivien · Movecia Academy'); } catch (Throwable $e) {}
    }
}


// --- Stornieren mit Erstattung (Vivien, 28.10.2026: Buchhaltung lückenlos) ------------------------------
// Vorschlag nach der Storno-Staffel; Erstattung über Stripe je Zahlung (neueste zuerst), je erstatteter Zahlung eine
// Stornorechnung im gemeinsamen Nummernkreis (forma_invoice_storno, in einer Transaktion, Google-Tabelle). Platz wird frei,
// die Warteliste bekommt automatisch Bescheid (ac_waitlist_run).

/** Erstattungsvorschlag in Cent nach der Storno-Staffel (Stand heute). */
function ac_storno_suggest(array $r): array
{
    $c = ac_cfg();
    $paid = ac_paid_cents($r); $total = (int)$r['total_cents'];
    $today = date('Y-m-d');
    if (time() <= ac_withdraw_until($r)) return [$paid, 'Rücktritt innerhalb von 14 Tagen: alles zurück'];
    if ($today <= $c['storno1']) { $fee = (int)round($total * .25); return [max(0, $paid - $fee), 'Storno bis ' . ac_d($c['storno1']) . ': Gebühr 25 % (' . ac_eur($fee) . ')']; }
    if ($today <= $c['due_final']) { $fee = (int)round($total * .5); return [max(0, $paid - $fee), 'Storno bis ' . ac_d($c['due_final']) . ': Gebühr 50 % (' . ac_eur($fee) . ')']; }
    return [0, 'Storno nach dem ' . ac_d($c['due_final']) . ': keine Erstattung'];
}

function ac_cancel(PDO $pdo, array $r, int $refund): string
{
    if ($r['status'] !== 'bezahlt') return 'Diese Buchung ist nicht aktiv.';
    $refund = max(0, min($refund, ac_paid_cents($r)));
    $left = $refund; $done = 0;
    require_once __DIR__ . '/invoices.php';
    $pays = $pdo->prepare('SELECT * FROM ac_payments WHERE booking_id = ? ORDER BY id DESC');
    $pays->execute([$r['id']]);
    $parts = ac_parts($r);
    foreach ($pays->fetchAll() as $pay) {
        if ($left <= 0) break;
        $amt = min($left, (int)$pay['cents']);
        $pi = '';
        foreach ($parts as $pt) if ((int)$pt['n'] === (int)$pay['n']) $pi = (string)$pt['ref'];
        if ($pi === '') continue;
        try {
            forma_stripe('POST', '/v1/refunds', ['payment_intent' => $pi, 'amount' => $amt, 'reason' => 'requested_by_customer', 'metadata' => ['academy_booking' => (string)$r['id']]]);
        } catch (Throwable $e) { error_log('AC refund: ' . $e->getMessage()); return 'Die Erstattung bei Stripe hat nicht geklappt (' . $e->getMessage() . '). Es wurde nichts storniert.'; }
        $was = $GLOBALS['FORMA_TEST'] ?? null;
        if ((int)$r['test'] === 1) $GLOBALS['FORMA_TEST'] = true;
        if (empty($GLOBALS['FORMA_TEST'])) forma_invoice_storno($pdo, 'ac_tt', (int)$pay['id'], $amt);
        $GLOBALS['FORMA_TEST'] = $was;
        $left -= $amt; $done += $amt;
    }
    $pdo->prepare("UPDATE ac_bookings SET status = 'storniert' WHERE id = ?")->execute([$r['id']]);
    $first = forma_first_name((string)$r['name']);
    $blocks = ["Hallo $first,", 'deine Buchung fürs Teacher Training 2027 ist storniert.' . ($done > 0 ? ' ' . ac_eur($done) . ' bekommst du über deine Karte zurück, das dauert meist ein paar Tage. Die Stornorechnung schicke ich dir in einer eigenen E-Mail.' : ''), 'Danke dir für dein Vertrauen. Wenn du später doch dabei sein möchtest, bist du jederzeit herzlich willkommen.'];
    try { mm_mail((string)$r['email'], ((int)$r['test'] ? '[TEST] ' : '') . 'Deine Stornierung · Teacher Training', ac_mail_text($blocks, '', '', '', false), ac_mail_html($r, 'alles Gute ♡', 'Deine Stornierung', $blocks, '', '', ''), [], 'Vivien · Movecia Academy'); } catch (Throwable $e) {}
    try { ac_waitlist_run($pdo); } catch (Throwable $e) {}
    return 'Storniert. ' . ($done > 0 ? ac_eur($done) . ' werden erstattet, die Stornorechnung ist verschickt.' : 'Keine Erstattung.');
}

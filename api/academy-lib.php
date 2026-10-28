<?php
// Movecia Academy (Vivien, 28.10.2026): Ausbildungen auf moveciamovement.com/academy.
// Erste Ausbildung: The Movecia Method – Mindful Movement Teacher Training (550 Std., 11 Monate, hybrid, nur alle 2 Jahre, max. 10 Plätze).
// Runde 2 (28.10.2026): Buchung = 50 % Anzahlung über Stripe, Rest auf einmal, in 2 oder 3 Zahlungen bis 15.9.2027 (Bezahl-Links per Mail),
// Rücktritt 14 Tage ab Buchung, Storno-Staffel, Konfetti-Mail, Mitgliederbereich vor dem Start (/academy/mitglieder/) mit Countdown,
// Willkommensvideo, Notizen (verschlüsselt), Fragen & Antworten und persönlichem Chat mit Vivien.
declare(strict_types=1);
require_once __DIR__ . '/method-lib.php';

const AC_HOLD = 40 * 60;                 // so lange hält eine offene Zahlung einen Platz
const AC_TITLE = 'The Movecia Method – Mindful Movement Teacher Training';
const AC_SHORT = 'Mindful Movement Teacher Training';
const AC_HOURS = 550;
// Kennenlerngespräch (Vivien): Mi/Do/Fr 8:00, Mi/Do 18:30, 30 Minuten, online per Video
const AC_CALL_SLOTS = [3 => ['08:00', '18:30'], 4 => ['08:00', '18:30'], 5 => ['08:00']];
const AC_CALL_MINUTES = 30;
const AC_OFFER_MINUTES = 30;             // so lange gilt das Angebot aus dem Gespräch
const AC_MAIL_BEFORE = 10;               // Bezahl-Link so viele Tage vor dem Fälligkeitstag

// --- Einstellungen ------------------------------------------------------------------------------

/**
 * Preise und Termine (Vivien, 28.10.2026, Runde 2): Early-Bird € 3.390 bis inkl. 31.3.2027, danach € 4.500.
 * Zahlung: 50 % Anzahlung bei Buchung; Rest auf einmal, oder 50 % bis 15.9.2027, oder 25 % am 15.6.2027 + 25 % am 15.9.2027. Kein Aufschlag.
 * Start Mi 3.11.2027 (Herbstferien 27.–31.10., 1.11. Allerheiligen, 2.11. Allerseelen schulfrei), Module 2–11 jeweils am 1.
 */
function ac_cfg_raw(): array
{
    $def = [
        'seats' => 10, 'open' => 1,
        'start' => '2027-11-03', 'end' => '2028-10-31',
        'eb_until' => '2027-03-31',
        'eb_total' => 339000, 'total' => 450000,
        'due_mid' => '2027-06-15', 'due_final' => '2027-09-15',
        'two_until' => '2027-08-31', // ab 16.6. nur noch auf einmal oder 2 Zahlungen, ab 1.9. nur auf einmal
        'storno1' => '2027-06-30',
    ];
    $c = mm_cfg('academy') ?? [];
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
        ['Yoga nach internationalem 200-Std.-Curriculum', 'Yoga, following an international 200-hour curriculum', 200, '#7D5E68', 'sonne'],
        ['Somatic Movement', 'Somatic movement', 80, '#B58C99', 'welle'],
        ['Breathwork & Meditation', 'Breathwork & meditation', 60, '#C9B0B8', 'atem'],
        ['Movecia Movement Method & Bodyweight', 'Movecia Movement Method & bodyweight', 60, '#E8C9B8', 'spirale'],
        ['Anatomie & Body Scanning', 'Anatomy & body scanning', 60, '#9A6F7A', 'herz'],
        ['Achtsame Berührung & Rituale', 'Mindful touch & rituals', 30, '#DDB7A7', 'kerze'],
        ['Mental Load & Nervensystem', 'Mental load & the nervous system', 30, '#EADFDE', 'kreis'],
        ['Deine Selbstständigkeit', 'Your own business', 30, '#4A3A41', 'bluete'],
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
    ac_welcome_lesson($pdo);
}

function ac_booking(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM ac_bookings WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

/** Bezahlte (Anzahlung da) Buchung zu einer E-Mail-Adresse. */
function ac_booking_of(PDO $pdo, string $email): ?array
{
    ac_schema($pdo);
    $q = $pdo->prepare("SELECT * FROM ac_bookings WHERE lower(email) = ? AND status = 'bezahlt' ORDER BY id DESC LIMIT 1");
    $q->execute([strtolower(trim($email))]);
    return $q->fetch() ?: null;
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
    $pdo->prepare('UPDATE ac_calls SET offer_token = ?, offer_until = ? WHERE id = ?')->execute([$tok, time() + max(5, min(180, $minutes)) * 60, $callId]);
    return mm_site() . '/academy/teacher-training/?angebot=' . $tok . ($c['lang'] === 'en' ? '&lang=en' : '') . '#buchen';
}

/** Gesamtpreis für eine Buchung: Early-Bird solange er gilt oder solange das Angebot aus dem Gespräch läuft. */
function ac_prices(?array $offer = null): array
{
    $c = ac_cfg();
    $eb = $c['eb'] || ac_offer_live($offer);
    return ['eb' => $eb, 'total' => $eb ? $c['eb_total'] : $c['r_total']];
}

// --- Buchung über Stripe --------------------------------------------------------------------------

/** Stripe-Checkout für einen Teilbetrag (Anzahlung oder spätere Zahlung). Preis kommt immer aus der Buchung. */
function ac_part_checkout(PDO $pdo, array $r, array $part): string
{
    $en = $r['lang'] === 'en';
    $site = mm_site();
    $c = ac_cfg();
    $label = AC_TITLE . ' · ' . ac_part_label($r, (int)$part['n'], $r['lang']) . ((int)$r['eb'] ? ' · Early Bird' : '');
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
    $en = ($in['lang'] ?? 'de') === 'en';
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
    $pdo->prepare('INSERT INTO ac_bookings (plan, eb, total_cents, parts, name, email, phone, practice, practice_note, why, lang, consent, call_id, token, test, ip_hash, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$plan, $p['eb'] ? 1 : 0, $p['total'], json_encode($parts), $name, $email, $phone, $practice, $note, $why, $en ? 'en' : 'de', 1, $offer ? (int)$offer['id'] : null, $tok, forma_stripe_test_mode() ? 1 : 0, $ipHash, date('c')]);
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
    ac_payment($pdo, $r, 'cs:' . $parts[$i]['session'], (int)($s['amount_total'] ?? $parts[$i]['cents']), $n);
    if ($first) ac_after_paid($pdo, $r);
    else { try { ac_mail_part_thanks($r, $n); } catch (Throwable $e) { error_log('AC part thanks: ' . $e->getMessage()); } }
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
    if (!forma_stripe_key()) return;
    $open = $pdo->prepare("SELECT * FROM ac_bookings WHERE status = 'offen' AND stripe_session <> '' AND created_at >= ? ORDER BY id LIMIT 10");
    $open->execute([date('c', time() - 2 * 86400)]);
    foreach ($open->fetchAll() as $r) { try { ac_check_part($pdo, $r, 1); } catch (Throwable $e) { error_log('AC tick: ' . $e->getMessage()); } }
    ac_due_run($pdo);
}

/** Spätere Teilzahlungen: Bezahl-Link AC_MAIL_BEFORE Tage vorher, Erinnerung am Fälligkeitstag (jeweils einmal). */
function ac_due_run(PDO $pdo): void
{
    $today = date('Y-m-d');
    foreach ($pdo->query("SELECT * FROM ac_bookings WHERE status = 'bezahlt'")->fetchAll() as $r) {
        $parts = ac_parts($r); $changed = false;
        foreach ($parts as &$p) {
            if ($p['paid'] !== '' || $p['due'] === '') continue;
            if ($p['session'] !== '') { try { if (ac_check_part($pdo, $r, (int)$p['n'])) { $changed = false; continue 2; } } catch (Throwable $e) {} }
            $kind = '';
            if (!$p['mailed'] && $today >= date('Y-m-d', strtotime($p['due'] . ' -' . AC_MAIL_BEFORE . ' days'))) { $p['mailed'] = 1; $kind = 'link'; }
            elseif ($p['mailed'] && !$p['remind'] && $today >= $p['due']) { $p['remind'] = 1; $kind = 'remind'; }
            if ($kind !== '') { $changed = true; try { ac_mail_due($r, $p, $kind === 'remind'); } catch (Throwable $e) { error_log('AC due mail: ' . $e->getMessage()); } }
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

function ac_after_paid(PDO $pdo, array $r): void
{
    $u = $pdo->prepare('UPDATE ac_bookings SET mailed = 1 WHERE id = ? AND mailed = 0');
    $u->execute([$r['id']]);
    if ($u->rowCount() !== 1) return;
    try { ac_mail_welcome($pdo, $r); } catch (Throwable $e) { error_log('AC welcome: ' . $e->getMessage()); }
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
    $html = $confetti ? '<p style="margin:0 0 14px;"><img src="' . forma_h(mm_site()) . '/img/movecia/mail-academy-konfetti.png" width="500" alt="" style="display:block;width:100%;max-width:500px;height:auto;border:0;border-radius:18px;"></p>' : '';
    $html .= '<p style="margin:0 0 6px;font-family:\'Brush Script MT\',cursive;font-size:24px;color:#7D5E68;">' . forma_h($hand) . '</p><h1 style="font-family:Georgia,serif;font-weight:400;font-size:28px;line-height:1.2;color:#6B5658;margin:0 0 14px;">' . forma_h($title) . '</h1>';
    foreach ($blocks as $b) {
        if (is_array($b) && isset($b['h'])) $html .= '<h2 style="font-family:Georgia,serif;font-weight:400;font-size:21px;color:#6B5658;margin:24px 0 6px;">' . forma_h($b['h']) . '</h2>';
        elseif (is_array($b) && isset($b['box'])) $html .= '<div style="background:#FBF8F4;border-radius:16px;padding:14px 18px;margin:12px 0;">' . implode('', array_map(fn($x) => '<p style="margin:4px 0;">' . forma_h($x) . '</p>', $b['box'])) . '</div>';
        elseif (is_array($b)) $html .= '<ul style="padding-left:20px;margin:8px 0;">' . implode('', array_map(fn($x) => '<li style="margin:5px 0;">' . forma_h($x) . '</li>', $b)) . '</ul>';
        else $html .= '<p>' . forma_h($b) . '</p>';
    }
    if ($link !== '') $html .= mm_button($link, $btn);
    if ($legal !== '') $html .= '<p style="font-size:13px;color:#7D7076;border-top:1px solid #DDD2C6;padding-top:12px;margin-top:22px;">' . forma_h($legal) . '</p>';
    return mm_mail_html($title, $html, (string)$r['lang']);
}

function ac_mail_text(array $blocks, string $link, string $btn, string $legal, bool $en): string
{
    $t = [];
    foreach ($blocks as $b) {
        if (is_array($b) && isset($b['h'])) $t[] = strtoupper($b['h']);
        elseif (is_array($b) && isset($b['box'])) $t[] = implode("\n", $b['box']);
        elseif (is_array($b)) $t[] = implode("\n", array_map(fn($x) => '· ' . $x, $b));
        else $t[] = $b;
    }
    return implode("\n\n", $t) . ($link !== '' ? "\n\n$btn: $link" : '') . ($en ? "\n\nWith love,\nVivien" : "\n\nAlles Liebe,\nVivien") . ($legal !== '' ? "\n\n$legal" : '');
}

/** Die Konfetti-Mail nach der Anzahlung (Vivien, 28.10.2026): warm, ausführlich, wie an die beste Freundin, aber professionell. */
function ac_welcome_blocks(PDO $pdo, array $r): array
{
    $en = $r['lang'] === 'en';
    $c = ac_cfg();
    $first = forma_first_name((string)$r['name'] ?: ($en ? 'lovely' : 'meine Liebe'));
    $start = mv_date($c['start_ts'], $en ? 'en' : 'de');
    $next = ac_next_part($r);
    $plan = ac_parts_lines($r, $en ? 'en' : 'de');
    if ($en) return [
        "Hey $first,",
        'confetti, confetti! You have secured your place on the Movecia Mindful Movement Teacher Training 2027, and I am honestly sitting here with the biggest smile on my face.',
        'There are only ten places, and the training only runs every two years. So the fact that one of those places is now yours means a great deal to me. This training is my heart project. I have packed into it everything I always wished for in my own trainings and so often missed: time to really arrive, a small group where you are seen, and a teacher who has your back.',
        'We follow the international 200-hour yoga teacher training curriculum, so you won\'t miss a thing: a complete 200-hour yoga teacher training is included. On top of that come somatic movement, breathwork and meditation, sequencing, anatomy and body scanning (very close to my heart), mindful touch and rituals, mental load and the nervous system, and a module on building your own business, where I help you personally with your first steps.',
        ['h' => 'What happens next'],
        ['Your members area is open from today. Log in with the button below and choose your password.', 'There you will find the countdown to our start, your welcome video and soon the intro workbook "So glad you\'re here", with intentions, affirmations and a little positivity for the months ahead.', 'You have your own private notes for questions, ideas and intentions. Only you can see them.', 'And there is a personal chat with me. Write to me whenever something is on your mind; I answer personally.'],
        ['h' => 'Your payment plan'],
        ['box' => $plan],
        $next ? 'I will send you a payment link about ten days before each due date, and you can pay at any time in your members area too. Everything is fully paid by ' . ac_d($c['due_final'], 'en') . '.' : 'Everything is paid in full, thank you so much.',
        ['h' => 'When we begin'],
        'Module 1 opens on ' . $start . ', right after the autumn school holidays. From then on a new module opens on the 1st of every month, we meet live every week, and in September 2028 we come together for our weekend in the Tyrolean Alps. I will let you know the exact date as soon as it is fixed.',
        'Your invoice comes in a separate email. And if anything at all is unclear, simply reply to this email or write to me in the chat.',
        'I can\'t wait to meet you.',
    ];
    return [
        "Hey $first,",
        'Konfetti, Konfetti! Du hast dir deinen Platz im Movecia Mindful Movement Teacher Training 2027 gesichert, und ich sitze gerade hier und strahle über das ganze Gesicht.',
        'Es gibt nur zehn Plätze, und die Ausbildung findet nur alle zwei Jahre statt. Dass einer dieser Plätze jetzt dir gehört, bedeutet mir wirklich viel. Diese Ausbildung ist mein Herzensprojekt. Ich habe alles hineingepackt, was ich mir in meinen eigenen Ausbildungen immer gewünscht und so oft vermisst habe: Zeit, um wirklich anzukommen, eine kleine Gruppe, in der du gesehen wirst, und eine Ausbilderin, die hinter dir steht.',
        'Wir folgen dem internationalen 200-Stunden-Curriculum für Yogalehrerausbildungen, damit dir nichts fehlt: Eine vollständige 200-Stunden-Yogalehrerausbildung ist inklusive. Dazu kommen Somatic Movement, Breathwork und Meditation, Sequencing, Anatomie und Body Scanning (das liegt mir besonders am Herzen), achtsame Berührung und Rituale, Mental Load und Nervensystem und ein Modul für deine Selbstständigkeit, in dem ich dir bei deinen ersten Schritten persönlich helfe.',
        ['h' => 'Wie es jetzt weitergeht'],
        ['Dein Mitgliederbereich ist ab heute offen. Melde dich über den Knopf unten an und leg dein Passwort fest.', 'Dort findest du den Countdown bis zu unserem Start, dein Willkommensvideo und bald das Einstiegs-Workbook „Schön, dass du da bist“ mit Intentionen, Affirmationen und ganz viel Vorfreude.', 'Du hast deine eigenen privaten Notizen für Fragen, Ideen und Intentionen. Die siehst nur du.', 'Und es gibt einen persönlichen Chat mit mir. Schreib mir, wann immer dich etwas beschäftigt, ich antworte dir persönlich.'],
        ['h' => 'Dein Zahlungsplan'],
        ['box' => $plan],
        $next ? 'Etwa zehn Tage vor jedem Termin schicke ich dir einen Bezahl-Link, und in deinem Mitgliederbereich kannst du jederzeit selbst bezahlen. Bis ' . ac_d($c['due_final']) . ' ist alles beglichen.' : 'Alles ist schon bezahlt, danke dir von Herzen.',
        ['h' => 'Wann wir starten'],
        'Modul 1 öffnet sich am ' . $start . ', gleich nach den Herbstferien. Danach kommt jeweils am 1. des Monats ein neues Modul dazu, wir treffen uns jede Woche live, und im September 2028 sehen wir uns zu unserem Wochenende in den Tiroler Alpen. Den genauen Termin sage ich dir, sobald er fix ist.',
        'Deine Rechnung bekommst du in einer eigenen E-Mail. Und wenn irgendetwas unklar ist, antworte einfach auf diese Mail oder schreib mir im Chat.',
        'Ich freu mich so auf dich.',
    ];
}

function ac_mail_welcome(PDO $pdo, array $r): void
{
    $en = $r['lang'] === 'en';
    [$link, $btn] = ac_account_link($pdo, $r);
    $blocks = ac_welcome_blocks($pdo, $r);
    $legal = ac_legal($r);
    $title = $en ? 'Your place is yours' : 'Dein Platz ist dir sicher';
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    $subj = $pfx . ($en ? 'Confetti, confetti! Your place on the Teacher Training 2027 🎉' : 'Konfetti, Konfetti! Dein Platz im Teacher Training 2027 🎉');
    mm_mail((string)$r['email'], $subj, ac_mail_text($blocks, $link, $btn, $legal, $en), ac_mail_html($r, $en ? 'welcome to the Academy ♡' : 'willkommen in der Academy ♡', $title, $blocks, $link, $btn, $legal, true), [], 'Vivien · Movecia Academy');
}

/** Bezahl-Link bzw. Erinnerung für eine spätere Teilzahlung. */
function ac_mail_due(array $r, array $p, bool $remind): void
{
    $en = $r['lang'] === 'en';
    $first = forma_first_name((string)$r['name']);
    $amt = ac_eur((int)$p['cents'], $en ? 'en' : 'de');
    $lab = ac_part_label($r, (int)$p['n'], $en ? 'en' : 'de');
    $blocks = $en
        ? ["Dear $first,", $remind ? "a gentle reminder: your $lab of $amt for the Teacher Training is due today (" . ac_d($p['due'], 'en') . '). If you have already paid, thank you, and please ignore this email.' : "your $lab of $amt for the Teacher Training is due on " . ac_d($p['due'], 'en') . '. You can pay it right now with the button below, or any time in your members area.', 'If anything has changed for you, simply reply to this email and we will find a way together.']
        : ["Hallo $first,", $remind ? "eine liebe Erinnerung: Deine $lab über $amt fürs Teacher Training ist heute fällig (" . ac_d($p['due']) . '). Wenn du schon bezahlt hast, danke dir, dann ignorier diese Mail einfach.' : "deine $lab über $amt fürs Teacher Training ist am " . ac_d($p['due']) . ' fällig. Du kannst sie gleich über den Knopf unten bezahlen oder jederzeit in deinem Mitgliederbereich.', 'Wenn sich bei dir etwas verändert hat, antworte einfach auf diese Mail, dann finden wir gemeinsam eine Lösung.'];
    $btn = $en ? 'Pay ' . $amt : $amt . ' bezahlen';
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    $subj = $pfx . ($remind ? ($en ? 'Reminder: your payment for the Teacher Training' : 'Erinnerung: deine Zahlung fürs Teacher Training') : ($en ? 'Your next payment for the Teacher Training' : 'Deine nächste Zahlung fürs Teacher Training'));
    mm_mail((string)$r['email'], $subj, ac_mail_text($blocks, ac_pay_link($r), $btn, '', $en), ac_mail_html($r, $en ? 'a little note ♡' : 'kurz zur Info ♡', AC_SHORT, $blocks, ac_pay_link($r), $btn, ''), [], 'Vivien · Movecia Academy');
}

function ac_mail_part_thanks(array $r, int $n): void
{
    $en = $r['lang'] === 'en';
    $first = forma_first_name((string)$r['name']);
    $lines = ac_parts_lines($r, $en ? 'en' : 'de');
    $blocks = $en ? ["Dear $first,", 'thank you, your payment has arrived. Here is your payment plan as it stands now:', ['box' => $lines], 'Your invoice comes in a separate email.']
        : ["Hallo $first,", 'danke dir, deine Zahlung ist angekommen. So sieht dein Zahlungsplan jetzt aus:', ['box' => $lines], 'Deine Rechnung bekommst du in einer eigenen E-Mail.'];
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    mm_mail((string)$r['email'], $pfx . ($en ? 'Thank you, your payment has arrived' : 'Danke, deine Zahlung ist angekommen'), ac_mail_text($blocks, ac_area($r['lang']), $en ? 'Members area' : 'Mitgliederbereich', '', $en), ac_mail_html($r, $en ? 'thank you ♡' : 'danke ♡', AC_SHORT, $blocks, ac_area($r['lang']), $en ? 'Members area' : 'Zum Mitgliederbereich', ''), [], 'Vivien · Movecia Academy');
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
    $v = "Hallo Viv,\n\n" . $r['name'] . ' (' . $r['email'] . ', ' . $r['phone'] . ') hat sich gerade einen Platz im Teacher Training gesichert: ' . mv_eur((int)$r['total_cents']) . ((int)$r['eb'] ? ' (Early-Bird)' : '') . ', ' . ac_plan_label((string)$r['plan']) . ($r['call_id'] ? ', aus dem Kennenlerngespräch' : '')
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
    $text = AC_TITLE . ' · ' . ac_part_label($r, (int)$p['n']) . ($cnt > 1 ? ' (' . (int)$p['n'] . '/' . $cnt . ')' : '');
    $detail = 'Ausbildung, ' . AC_HOURS . ' Std. inkl. 200-Std.-Yogalehrerausbildung, hybrid, ' . date('d.m.Y', $c['start_ts']) . ' bis September 2028, inkl. Präsenzwochenende mit Unterkunft und Verpflegung, Abschlussprüfung und Zertifikat'
        . ' · Gesamtpreis ' . mv_eur((int)$r['total_cents']) . ((int)$r['eb'] ? ' (Early-Bird)' : '') . ($cnt > 1 ? ', davon mit dieser Zahlung ' . mv_eur((int)$p['cents']) : '');
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

function ac_call_when(array $c, string $lang = 'de'): string
{
    $t = strtotime($c['date'] . ' ' . $c['slot']);
    if ($lang === 'en') return date('l, j F Y', $t) . ', ' . date('g:i a', $t) . ' (Vienna time)';
    $wd = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    return $wd[(int)date('N', $t)] . ', ' . mv_date($t) . ' um ' . date('G:i', $t) . ' Uhr';
}

function ac_call_request(PDO $pdo, array $in, string $ipHash): array
{
    ac_schema($pdo);
    $en = ($in['lang'] ?? '') === 'en';
    $date = (string)($in['date'] ?? '');
    $slot = (string)($in['slot'] ?? '');
    $ok = false;
    foreach (ac_call_slots($pdo) as $s) if ($s['date'] === $date && $s['slot'] === $slot) $ok = true;
    $name = forma_clean((string)($in['name'] ?? ''), 80);
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $phone = forma_clean((string)($in['phone'] ?? ''), 40);
    $note = trim(mb_substr((string)($in['note'] ?? ''), 0, 600));
    if (!$ok) return ['ok' => false, 'error' => $en ? 'Please choose one of the free times.' : 'Bitte wähl einen der freien Termine.'];
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => $en ? 'Please enter your name and email.' : 'Bitte gib deinen Namen und deine E-Mail an.'];
    $q = $pdo->prepare('SELECT COUNT(*) FROM ac_calls WHERE ip_hash = ? AND created_at >= ?');
    $q->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$q->fetchColumn() >= 4) return ['ok' => false, 'error' => $en ? 'Please try again later.' : 'Bitte versuch es später noch einmal.'];
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM ac_calls WHERE date = ? AND slot = ? AND status IN ('angefragt','bestaetigt')");
        $q->execute([$date, $slot]);
        if ((int)$q->fetchColumn() > 0) { $pdo->exec('ROLLBACK'); return ['ok' => false, 'error' => $en ? 'Someone has just taken this time. Please choose another one.' : 'Dieser Termin ist gerade vergeben worden. Bitte wähl einen anderen.']; }
        $pdo->prepare('INSERT INTO ac_calls (date, slot, name, email, phone, note, lang, token, ip_hash, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$date, $slot, $name, $email, $phone, $note, $en ? 'en' : 'de', bin2hex(random_bytes(12)), $ipHash, date('c')]);
        $id = (int)$pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
        throw $e;
    }
    $c = ac_call($pdo, $id);
    $first = forma_first_name($name);
    $r = ['lang' => $en ? 'en' : 'de', 'email' => $email];
    $lines = $en ? ["Dear $first,", 'thank you for your interest in the Mindful Movement Teacher Training! Your preferred time for our free 30-minute call is: ' . ac_call_when($c, 'en') . '.', 'I will confirm the time by email within the next day or two, together with the link to our video room. You don\'t need an app or an account for it.']
        : ["Hallo $first,", 'schön, dass du dich für das Mindful Movement Teacher Training interessierst! Dein Wunschtermin für unser kostenloses 30-Minuten-Gespräch: ' . ac_call_when($c) . '.', 'Ich bestätige dir den Termin in den nächsten ein, zwei Tagen per E-Mail, zusammen mit dem Link zu unserem Videoraum. Du brauchst dafür keine App und kein Konto.'];
    mv_send_member_mail($r, $en ? 'Your call about the Teacher Training' : 'Dein Kennenlerngespräch zum Teacher Training', $en ? 'let\'s talk ♡' : 'lass uns reden ♡', AC_SHORT, $lines, '', '', '');
    $v = "Hallo Viv,\n\nneuer Gesprächswunsch zum Teacher Training:\n\n" . ac_call_when($c) . "\n" . $name . ' · ' . $email . ($phone !== '' ? ' · ' . $phone : '') . ($note !== '' ? "\n\nIhre Nachricht: " . $note : '') . "\n\nBitte in der Verwaltung bestätigen: " . forma_site_url() . "/verwaltung/?view=academy\n";
    mm_mail(natal_notify_email_safe(), 'Kennenlerngespräch Teacher Training: ' . $name, $v, mm_mail_html('Gesprächswunsch', nl2br(forma_h($v)), 'de'));
    return ['ok' => true, 'when' => ac_call_when($c, $en ? 'en' : 'de')];
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
    mv_send_member_mail(['lang' => $c['lang'], 'email' => $c['email']], $en ? 'Confirmed: our call about the Teacher Training' : 'Bestätigt: unser Gespräch zum Teacher Training', $en ? 'see you soon ♡' : 'bis bald ♡', AC_SHORT, $lines, $url, $url !== '' ? ($en ? 'Join the video call' : 'Zum Videogespräch') : '', '');
    return $url !== '' ? 'Bestätigt, die Mail mit dem Videoraum ist unterwegs.' : 'Bestätigt. Es ist noch kein Whereby-Schlüssel hinterlegt, bitte schick ihr den Link zum Gespräch selbst.';
}

function ac_call_cancel(PDO $pdo, int $id): void
{
    $c = ac_call($pdo, $id);
    if (!$c || $c['status'] === 'abgesagt') return;
    $pdo->prepare("UPDATE ac_calls SET status = 'abgesagt' WHERE id = ?")->execute([$id]);
    $en = $c['lang'] === 'en';
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
    if ($l) return $l;
    $pid = (int)($pdo->query("SELECT id FROM mv_programs WHERE audience = 'academy' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
    if (!$pid) {
        $pdo->prepare("INSERT INTO mv_programs (audience, title_de, title_en, intro_de, intro_en, theme, sort, visible, created_at) VALUES ('academy', 'Teacher Training · vor dem Start', 'Teacher Training · before we begin', '', '', 'academy', 0, 1, ?)")->execute([date('c')]);
        $pid = (int)$pdo->lastInsertId();
    }
    [$de, $en] = ac_welcome_script();
    $pdo->prepare("INSERT INTO mv_lessons (program_id, title_de, title_en, text_de, text_en, cat, energy, minutes, sort, visible, created_at, script_de, script_en, ukey, due) VALUES (?,?,?,?,?,'talk','any',4,0,1,?,?,?,'academy:welcome','2026-11-08')")
        ->execute([$pid, 'Willkommen im Teacher Training', 'Welcome to the Teacher Training', 'Mein Willkommen an dich, bevor wir starten.', 'My welcome to you before we begin.', date('c'), $de, $en]);
    $q->execute();
    return $q->fetch() ?: null;
}

/** Teleprompter-Entwurf fürs Willkommensvideo (DE + EN, ca. 3–4 Min., Viviens Ton, fließende Sätze). */
function ac_welcome_script(): array
{
    $de = "Hallo, meine Liebe, und herzlich willkommen im Movecia Mindful Movement Teacher Training. Ich freue mich so sehr, dass du hier bist, und ich möchte dir in diesen paar Minuten erzählen, warum mir diese Ausbildung so am Herzen liegt und was dich in den nächsten Monaten erwartet.\n\n"
        . "Als ich selbst meine ersten Ausbildungen gemacht habe, war ich oft beeindruckt von dem vielen Wissen, aber ich habe mich auch oft ein bisschen verloren gefühlt. Es ging viel um Haltungen, um Abläufe und um Programme, und viel zu wenig um den Menschen, der vor einem auf der Matte liegt. Genau das wollte ich anders machen. Deshalb gibt es diese Ausbildung nur alle zwei Jahre und nur mit zehn Plätzen, damit ich jede von euch wirklich sehen und begleiten kann.\n\n"
        . "Du bekommst bei mir eine vollständige 200-Stunden-Yogalehrerausbildung nach dem internationalen Curriculum, und dazu alles, was ich mir selbst immer gewünscht habe: Somatic Movement, Breathwork und Meditation, achtsame Berührung und Rituale, ganz viel Anatomie und Body Scanning, und das Wissen darüber, was Mental Load mit unserem Nervensystem macht. Und weil so viele von uns irgendwann ihre eigenen Stunden geben möchten, begleite ich dich auch beim Schritt in die Selbstständigkeit, ganz persönlich.\n\n"
        . "Bis zu unserem Start am dritten November 2027 ist noch ein bisschen Zeit, und das ist gut so. Oben siehst du den Countdown, und ich lade dich ein, diese Zeit bewusst zu nutzen. In deinen Notizen kannst du alles festhalten, was dir einfällt: deine Fragen, deine Wünsche, deine Intentionen für dieses Jahr. Die Notizen gehören nur dir, niemand sonst kann sie lesen.\n\n"
        . "Und wenn dich etwas beschäftigt, schreib mir einfach im Chat. Die Nachricht landet direkt bei mir, und ich antworte dir persönlich. Es gibt keine Frage, die zu klein ist.\n\n"
        . "Bald findest du hier auch dein erstes Workbook, „Schön, dass du da bist“. Darin setzen wir gemeinsam deine Intentionen, und du bekommst ein paar Affirmationen, die dich bis zum Start begleiten.\n\n"
        . "Ich danke dir von Herzen für dein Vertrauen. Mach es dir bis dahin schön, beweg dich, atme, und dann sehen wir uns am dritten November. Ich freue mich riesig auf dich.";
    $en = "Hello, lovely, and a very warm welcome to the Movecia Mindful Movement Teacher Training. I am so happy you are here, and in these few minutes I would like to tell you why this training means so much to me and what is waiting for you over the coming months.\n\n"
        . "When I did my own first trainings, I was often amazed by how much I learnt, but I also often felt a little lost. So much of it was about poses, sequences and programmes, and far too little about the person lying on the mat in front of you. That is exactly what I wanted to do differently. It is why this training only runs every two years and only has ten places, so that I can truly see and support each one of you.\n\n"
        . "With me you get a complete 200-hour yoga teacher training following the international curriculum, and on top of that everything I always wished I had been given: somatic movement, breathwork and meditation, mindful touch and rituals, plenty of anatomy and body scanning, and an understanding of what mental load does to our nervous system. And because so many of us dream of teaching our own classes one day, I will also walk with you, very personally, as you take your first steps into working for yourself.\n\n"
        . "There is still a little time until we begin on the third of November 2027, and that is a good thing. You can see the countdown at the top, and I invite you to use this time mindfully. In your notes you can write down anything that comes to mind: your questions, your hopes, your intentions for this year. Your notes belong to you alone, nobody else can read them.\n\n"
        . "And whenever something is on your mind, just write to me in the chat. Your message comes straight to me, and I will answer you personally. No question is too small.\n\n"
        . "Soon you will also find your first workbook here, \"So glad you're here\". In it we will set your intentions together, and you will get a few affirmations to carry you through until we start.\n\n"
        . "Thank you so much for your trust. Look after yourself until then, keep moving, keep breathing, and I will see you on the third of November. I cannot wait to meet you.";
    return [$de, $en];
}

/** Fragen & Antworten im Mitgliederbereich (auch Vorschläge im Chat). [de-Frage, de-Antwort, en-Frage, en-Antwort] */
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
        ['Wann ist das Präsenzwochenende?', 'Im September 2028 in den Tiroler Alpen, von Freitag 8:30 Uhr bis Sonntag 17:30 Uhr. Den genauen Termin gebe ich bekannt, sobald er fix ist, gut ein Jahr im Voraus. Unterkunft und Verpflegung sind inklusive.', 'When is the in-person weekend?', 'In September 2028 in the Tyrolean Alps, from Friday 8:30 am to Sunday 5:30 pm. I will announce the exact date as soon as it is fixed, well over a year in advance. Accommodation and meals are included.'],
        ['Was ist die Lehrprobe?', 'Im Modul Sequencing entwickelst du deine eigene Einheit von etwa 25 Minuten und unterrichtest sie beim Wochenende. Das ist keine Prüfung, sondern dein Moment. Aufgeregt sein ist ganz normal, und wir üben vorher viel gemeinsam.', 'What is the teaching practice?', 'In the sequencing module you create your own session of about 25 minutes and teach it at the weekend. It is not an exam, it is your moment. Feeling nervous is completely normal, and we practise a lot together beforehand.'],
        ['Wie läuft die Abschlussprüfung?', 'Nach dem Präsenzwochenende beantwortest du online Multiple-Choice-Fragen, zu Hause mit eingeschalteter Kamera in unserem Prüfungsraum. Danach, mit ca. 100 dokumentierten Praxisstunden, bekommst du dein Zertifikat.', 'How does the final exam work?', 'After the in-person weekend you answer multiple-choice questions online, at home with your camera on in our exam room. After that, with around 100 documented practice hours, you receive your certificate.'],
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
        ['Ist eine Yogalehrerausbildung enthalten?', 'Does it include a yoga teacher training?',
         'Ja. Du bekommst eine vollständige 200-Stunden-Yogalehrerausbildung nach internationalem Curriculum inklusive. Insgesamt umfasst die Ausbildung ' . AC_HOURS . ' Stunden, mit Somatic Movement, Breathwork und Meditation, Anatomie und Body Scanning, achtsamer Berührung und Ritualen, Mental Load und Nervensystem, der Movecia Movement Method und deiner Selbstständigkeit.',
         'Yes. A complete 200-hour yoga teacher training following an international curriculum is included. In total the training covers ' . AC_HOURS . ' hours, with somatic movement, breathwork and meditation, anatomy and body scanning, mindful touch and rituals, mental load and the nervous system, the Movecia Movement Method and building your own business.'],
        ['Wie läuft die Ausbildung ab und wie viel Zeit brauche ich?', 'How does it work and how much time do I need?',
         'Hybrid über 11 Monate: Am ' . mv_date($c['start_ts']) . ' öffnet sich Modul 1, danach jeweils am 1. des Monats ein neues Modul mit Theorie-Videos und Workbook. Jede Woche treffen wir uns live, dazu kommen Lerngruppen und ein Online-Coworking-Raum. Plan etwa 10 bis 12 Stunden pro Woche ein.',
         'Hybrid over 11 months: module 1 opens on ' . mv_date($c['start_ts'], 'en') . ', then a new module with theory videos and a workbook on the 1st of every month. We meet live every week, plus learning groups and an online co-working space. Allow about 10 to 12 hours a week.'],
        ['Was passiert beim Präsenzwochenende?', 'What happens at the in-person weekend?',
         'Im September 2028 (genauer Termin wird noch bekannt gegeben) treffen wir uns von Freitag 8:30 Uhr bis Sonntag 17:30 Uhr in den Tiroler Alpen, Unterkunft und Verpflegung sind inklusive. Jede Teilnehmerin hält ihre Lehrprobe, eine eigene Einheit von etwa 25 Minuten. Das Wochenende kann nicht nachgeholt werden.',
         'In September 2028 (exact date to be announced) we meet from Friday 8:30 am to Sunday 5:30 pm in the Tyrolean Alps, with accommodation and meals included. Each participant teaches her own session of about 25 minutes. The weekend cannot be made up later.'],
        ['Wie bekomme ich mein Zertifikat?', 'How do I get my certificate?',
         'Nach dem Präsenzwochenende legst du online die Multiple-Choice-Abschlussprüfung ab, zu Hause mit eingeschalteter Kamera in unserem Prüfungsraum. Mit bestandener Prüfung und ca. 100 dokumentierten Praxisstunden bekommst du dein Zertifikat der Movecia Academy.',
         'After the in-person weekend you take the online multiple-choice final exam at home, with your camera on in our exam room. Once you have passed and documented around 100 practice hours, you receive your Movecia Academy certificate.'],
        ['Was kostet die Ausbildung und wie zahle ich?', 'How much does it cost and how do I pay?',
         'Early-Bird bis ' . mv_date($c['eb_end']) . ': ' . mv_eur($c['eb_total']) . ', danach ' . mv_eur($c['r_total']) . '. Bei der Buchung zahlst du 50 % an, den Rest auf einmal oder in Teilen bis 15. September 2027, ohne Aufschlag. Je früher du buchst, desto mehr Zahlungsmöglichkeiten hast du: Bis 15. Juni 2027 kannst du in drei Teilen zahlen, bis 31. August 2027 in zwei, danach zahlst du auf einmal. Unterkunft und Verpflegung am Präsenzwochenende, Prüfung und Zertifikat sind inklusive. Die Ausbildung findet nur alle zwei Jahre statt, mit höchstens ' . $c['seats'] . ' Plätzen.',
         'Early bird until ' . mv_date($c['eb_end'], 'en') . ': ' . ac_eur($c['eb_total'], 'en') . ', then ' . ac_eur($c['r_total'], 'en') . '. You pay a 50 % deposit when you book and the rest at once or in parts by 15 September 2027, with no surcharge. The earlier you book, the more ways you have to pay: until 15 June 2027 you can pay in three parts, until 31 August 2027 in two, and after that in one go. Accommodation and meals at the in-person weekend, the exam and the certificate are included. The training only runs every two years, with no more than ' . $c['seats'] . ' places.'],
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
        'founder' => ['@type' => 'Person', 'name' => 'Vivien Malonga'], 'areaServed' => [['@type' => 'Country', 'name' => 'Österreich'], ['@type' => 'Country', 'name' => 'Deutschland'], ['@type' => 'Country', 'name' => 'Schweiz'], ['@type' => 'Place', 'name' => 'Online']]];
    $offers = [];
    if ($c['eb']) $offers[] = ['@type' => 'Offer', 'name' => 'Early Bird', 'price' => number_format($c['eb_total'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'validThrough' => $c['eb_until'], 'availability' => $avail, 'url' => $url . '#buchen', 'category' => 'Paid'];
    $offers[] = ['@type' => 'Offer', 'name' => 'Regulär', 'price' => number_format($c['r_total'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'availability' => $avail, 'url' => $url . '#buchen', 'category' => 'Paid'] + ($c['eb'] ? ['validFrom' => date('Y-m-d', $c['eb_end'] + 86400)] : []);
    $course = ['@context' => 'https://schema.org', '@type' => 'Course', '@id' => $url . '#course', 'name' => AC_TITLE,
        'description' => AC_HOURS . '-Stunden-Ausbildung (hybrid, 11 Monate) inkl. vollständiger 200-Stunden-Yogalehrerausbildung nach internationalem Curriculum, Somatic Movement, Breathwork & Meditation, Anatomie & Body Scanning, achtsame Berührung & Rituale, Mental Load & Nervensystem, Selbstständigkeit. Online-Module, wöchentlicher Live-Call, Lerngruppen und Präsenzwochenende in den Tiroler Alpen. Nur alle 2 Jahre, max. 10 Plätze.',
        'provider' => $org, 'inLanguage' => 'de', 'image' => $site . '/img/movecia/og-academy.jpg', 'url' => $url,
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

/** Academy-Siegel (Kiesel im Bogen mit Lorbeer, „Academy“ im Kreis). $cls für Größe/Farbe. */
function ac_seal(string $cls = ''): string
{
    $leaf = fn(float $a, int $side) => '<ellipse cx="0" cy="-6" rx="3.2" ry="7.5" transform="translate(' . (60 + $side * 41 * sin(deg2rad($a))) . ' ' . (64 - 41 * cos(deg2rad($a))) . ') rotate(' . ($side * $a + ($side > 0 ? 28 : -28)) . ')"/>';
    $leaves = '';
    foreach ([120, 140, 160, 180, 200] as $a) { $leaves .= $leaf((float)$a, 1) . $leaf((float)$a, -1); }
    return '<svg class="ac-seal ' . $cls . '" viewBox="0 0 120 120" aria-hidden="true">'
        . '<defs><path id="acArc" d="M 22 62 A 38 38 0 0 1 98 62"/></defs>'
        . '<circle cx="60" cy="60" r="57" class="s-bg"/><circle cx="60" cy="60" r="52" class="s-ring"/>'
        . '<g class="s-leaf">' . $leaves . '</g>'
        . '<text class="s-txt"><textPath href="#acArc" startOffset="50%" text-anchor="middle">ACADEMY</textPath></text>'
        . '<g transform="translate(36 52) scale(.38)"><path class="s-soft" d="M56 32 C 76 29, 92 44, 91 64 C 90 84, 72 96, 53 92 C 34 88, 25 72, 29 53 C 32 40, 43 33, 56 32 Z" transform="translate(-6 -4)"/><path class="s-line" d="M44 30 C 62 20, 90 28, 98 50 C 104 68, 92 78, 84 88 C 76 100, 50 98, 36 88 C 22 78, 22 58, 28 46 C 32 38, 38 33, 44 30 Z" transform="translate(7 4) translate(63 60) scale(0.85) translate(-63 -60)"/><circle class="s-dot" cx="81" cy="54" r="10"/></g>'
        . '<text x="60" y="98" class="s-yr" text-anchor="middle">2027</text></svg>';
}

/** Kleine handgezeichnete Vögel fürs Early-Bird-Abzeichen. */
function ac_birds(): string
{
    $b = fn(string $cls, string $tr) => '<svg class="bird ' . $cls . '" viewBox="0 0 40 30" style="' . $tr . '" aria-hidden="true"><path d="M8 18c3-6 10-8 16-5 2-4 6-6 10-5-2 1-3 3-3 5 1 6-5 11-13 11-6 0-10-2-12-6z" fill="currentColor" opacity=".92"/><path class="wing" d="M14 16c2-6 7-9 12-8-2 4-5 7-12 8z" fill="#F2ECE4" opacity=".75"/><circle cx="30" cy="11" r="1.1" fill="#2E2529"/><path d="M34 11.5l3 .6-3 1" fill="none" stroke="#E8B48F" stroke-width="1.4" stroke-linecap="round"/><path d="M14 27l-2 2M18 28l-1 2" stroke="currentColor" stroke-width="1" stroke-linecap="round"/></svg>';
    return '<span class="birds" aria-hidden="true">' . $b('b1', '') . $b('b2', '') . $b('b3', '') . '</span>';
}

<?php
// Movecia Academy (Vivien, 28.10.2026): Ausbildungen auf moveciamovement.com/academy.
// Erste Ausbildung: The Movecia Method – Mindful Movement Teacher Training (500 Std., 11 Monate, hybrid).
// Buchung über das gemeinsame Stripe-Konto (Movecia-Branding wie Method/Unfold), Vorab-Buchung mit Start 3.11.2027,
// Rücktrittsfrist ab Start (wie Unfold), Rechnung im Movecia-Design (Rechnungsart ac_tt), kostenloses Kennenlerngespräch
// als Anfrage mit fixen Zeiten, im Gespräch ein persönliches Angebot mit Countdown (Early-Bird-Preis, Preis am Server).
declare(strict_types=1);
require_once __DIR__ . '/method-lib.php';

const AC_HOLD = 40 * 60;                 // so lange hält eine offene Zahlung einen Platz
const AC_TITLE = 'The Movecia Method – Mindful Movement Teacher Training';
const AC_SHORT = 'Mindful Movement Teacher Training';
// Kennenlerngespräch (Vivien): Mi/Do/Fr 8:00, Mi/Do 18:30, 30 Minuten, online per Video
const AC_CALL_SLOTS = [3 => ['08:00', '18:30'], 4 => ['08:00', '18:30'], 5 => ['08:00']];
const AC_CALL_MINUTES = 30;
const AC_OFFER_MINUTES = 30;             // so lange gilt das Angebot aus dem Gespräch

// --- Einstellungen ------------------------------------------------------------------------------

/**
 * Preise und Termine (Vivien, 28.10.2026). Early-Bird € 3.390 bis inkl. 31.5.2027, danach € 4.500.
 * Raten: 3 Monatsraten mit kleinem Aufschlag (Early-Bird 3 × € 1.170, danach 3 × € 1.550).
 * Start Mi 3.11.2027 (Modul 1), Module 2–11 jeweils am 1. des Monats bis September 2028.
 */
function ac_cfg_raw(): array
{
    $def = [
        'seats' => 10, 'open' => 1,
        'start' => '2027-11-03', 'end' => '2028-10-31',
        'eb_until' => '2027-05-31',
        'eb_once' => 339000, 'once' => 450000,
        'eb_inst' => 117000, 'inst' => 155000, 'n' => 3,
    ];
    $c = mm_cfg('academy') ?? [];
    foreach ($def as $k => $v) $def[$k] = is_int($v) ? (int)($c[$k] ?? $v) : (string)($c[$k] ?? $v);
    return $def;
}

/** Gültige Preise: once/inst = jetzt, r_* = regulär, eb = Early-Bird gerade aktiv, eb_end = Zeitstempel. */
function ac_cfg(): array
{
    $c = ac_cfg_raw();
    $c['eb_end'] = mv_ts($c['eb_until'], '23:59');
    $c['eb'] = time() <= $c['eb_end'];
    $c['r_once'] = $c['once']; $c['r_inst'] = $c['inst'];
    if ($c['eb']) { $c['once'] = $c['eb_once']; $c['inst'] = $c['eb_inst']; }
    $c['start_ts'] = mv_ts($c['start'], '00:00');
    return $c;
}

function ac_eur(int $c, string $lang = 'de'): string
{
    if ($lang === 'en') return '€' . ($c % 100 === 0 ? number_format($c / 100, 0, '.', ',') : number_format($c / 100, 2, '.', ','));
    return mv_eur($c);
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
        ['Meditation & Stille', 'Meditation & stillness'],
        ['Zuhören & Begleiten', 'Listening & guiding'],
        ['Unterrichten & Sequencing', 'Teaching & sequencing'],
        ['Deine Selbstständigkeit & Abschluss', 'Your own practice & graduation'],
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

/** Monat + Jahr, z. B. „November 2027“ / „November 2027“. */
function ac_month(string $ymd, string $lang = 'de'): string
{
    $t = strtotime($ymd);
    if ($lang === 'en') return date('F Y', $t);
    $m = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    return $m[(int)date('n', $t)] . ' ' . date('Y', $t);
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
        course TEXT NOT NULL DEFAULT \'tt\',
        plan TEXT NOT NULL DEFAULT \'once\',
        eb INTEGER NOT NULL DEFAULT 0,
        amount_cents INTEGER NOT NULL DEFAULT 0,
        rate_cents INTEGER NOT NULL DEFAULT 0,
        name TEXT NOT NULL DEFAULT \'\',
        email TEXT NOT NULL DEFAULT \'\',
        phone TEXT NOT NULL DEFAULT \'\',
        practice TEXT NOT NULL DEFAULT \'\',
        practice_note TEXT NOT NULL DEFAULT \'\',
        why TEXT NOT NULL DEFAULT \'\',
        lang TEXT NOT NULL DEFAULT \'de\',
        wr TEXT NOT NULL DEFAULT \'\',
        consent_weekend INTEGER NOT NULL DEFAULT 0,
        call_id INTEGER,
        status TEXT NOT NULL DEFAULT \'offen\',
        token TEXT NOT NULL,
        stripe_session TEXT NOT NULL DEFAULT \'\',
        sub_id TEXT NOT NULL DEFAULT \'\',
        customer TEXT NOT NULL DEFAULT \'\',
        address TEXT NOT NULL DEFAULT \'\',
        country TEXT NOT NULL DEFAULT \'AT\',
        synced_at INTEGER NOT NULL DEFAULT 0,
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
}

function ac_booking(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM ac_bookings WHERE id = ?');
    $q->execute([$id]);
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

/** Ende der Rücktrittsfrist: 14 Tage ab Start (freiwillig verlängert wie bei Unfold), sonst ab Zahlung. Bis 23:59. */
function ac_withdraw_until(array $r): int
{
    $paid = strtotime((string)($r['paid_at'] ?? '')) ?: time();
    $from = max($paid, ac_cfg()['start_ts']);
    return mv_ts((new DateTimeImmutable('@' . ($from + 14 * 86400)))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('Y-m-d'), '23:59');
}

// --- Angebot im Kennenlerngespräch (persönlicher Link mit Countdown) -----------------------------

function ac_call(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM ac_calls WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

/** Gültiges Gesprächsangebot zu einem Token, sonst null. */
function ac_offer_by_token(PDO $pdo, string $tok): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $tok)) return null;
    ac_schema($pdo);
    $q = $pdo->prepare('SELECT * FROM ac_calls WHERE offer_token = ?');
    $q->execute([$tok]);
    return $q->fetch() ?: null;
}

function ac_offer_live(?array $call): bool
{
    return $call !== null && (int)$call['offer_until'] > time();
}

/** Startet (oder verlängert) das Angebot im Gespräch und gibt den Link zurück. */
function ac_offer_start(PDO $pdo, int $callId, int $minutes = AC_OFFER_MINUTES): string
{
    $c = ac_call($pdo, $callId);
    if (!$c) throw new RuntimeException('Gespräch nicht gefunden.');
    $tok = $c['offer_token'] !== '' ? $c['offer_token'] : bin2hex(random_bytes(12));
    $pdo->prepare('UPDATE ac_calls SET offer_token = ?, offer_until = ? WHERE id = ?')->execute([$tok, time() + max(5, min(180, $minutes)) * 60, $callId]);
    return mm_site() . '/academy/teacher-training/?angebot=' . $tok . ($c['lang'] === 'en' ? '&lang=en' : '') . '#buchen';
}

/** Preise für eine Buchung: Early-Bird solange er gilt oder solange das Angebot aus dem Gespräch läuft. */
function ac_prices(?array $offer = null): array
{
    $c = ac_cfg();
    $eb = $c['eb'] || ac_offer_live($offer);
    return ['eb' => $eb, 'once' => $eb ? $c['eb_once'] : $c['r_once'], 'inst' => $eb ? $c['eb_inst'] : $c['r_inst'], 'n' => $c['n']];
}

// --- Buchung über Stripe --------------------------------------------------------------------------

/** Legt die Buchung an und gibt die Stripe-Bezahlseite zurück. Preis bestimmt immer der Server. */
function ac_checkout(PDO $pdo, array $in, string $ipHash = ''): string
{
    ac_schema($pdo);
    $c = ac_cfg();
    $en = ($in['lang'] ?? 'de') === 'en';
    $lang = $en ? 'en' : 'de';
    $name = forma_clean((string)($in['name'] ?? ''), 80);
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $phone = forma_clean((string)($in['phone'] ?? ''), 40);
    $practice = in_array($in['practice'] ?? '', ['lt1', '1to2', 'gt2', 'teach'], true) ? (string)$in['practice'] : '';
    $note = trim(mb_substr((string)($in['practice_note'] ?? ''), 0, 800));
    $why = trim(mb_substr((string)($in['why'] ?? ''), 0, 1500));
    $plan = ($in['plan'] ?? '') === 'inst' ? 'inst' : 'once';
    $err = [];
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = $en ? 'Please enter your name and email.' : 'Bitte gib deinen Namen und deine E-Mail an.';
    if (mb_strlen(preg_replace('/\D/', '', $phone)) < 6) $err[] = $en ? 'Please enter your phone number.' : 'Bitte gib deine Telefonnummer an.';
    if ($practice === '') $err[] = $en ? 'Please tell me how long you have been practising.' : 'Bitte sag mir, wie lange du schon praktizierst.';
    if (mb_strlen($why) < 10) $err[] = $en ? 'Please write a few words about why you want to do the training.' : 'Bitte schreib mir ein paar Sätze, warum du die Ausbildung machen möchtest.';
    if (!in_array($in['wr'] ?? '', ['verzicht', 'behalten'], true)) $err[] = $en ? 'Please choose whether you keep your right of withdrawal.' : 'Bitte wähle, ob du dein Rücktrittsrecht behältst.';
    if (empty($in['weekend'])) $err[] = $en ? 'Please confirm the box about the in-person weekend.' : 'Bitte bestätige das Häkchen zum Präsenzwochenende.';
    if ($err) throw new RuntimeException(implode(' ', $err));
    if (!$c['open']) throw new RuntimeException($en ? 'Booking is not open right now.' : 'Die Buchung ist gerade nicht geöffnet.');
    if (ac_free($pdo) <= 0) throw new RuntimeException($en ? 'The training is fully booked. Write to me and I will let you know when places open up.' : 'Die Ausbildung ist ausgebucht. Schreib mir gern, dann sage ich dir Bescheid, sobald ein Platz frei wird.');
    $dup = $pdo->prepare("SELECT COUNT(*) FROM ac_bookings WHERE lower(email) = ? AND status = 'bezahlt'");
    $dup->execute([$email]);
    if ((int)$dup->fetchColumn() > 0) throw new RuntimeException($en ? 'You are already booked. If anything is unclear, simply write to me.' : 'Du bist schon angemeldet. Wenn etwas unklar ist, schreib mir einfach.');
    $offer = ac_offer_by_token($pdo, (string)($in['angebot'] ?? ''));
    $p = ac_prices($offer);
    $amount = $plan === 'inst' ? $p['inst'] * $p['n'] : $p['once'];
    $rate = $plan === 'inst' ? $p['inst'] : $p['once'];
    $tok = bin2hex(random_bytes(16));
    $test = forma_stripe_test_mode() ? 1 : 0;
    $pdo->prepare('INSERT INTO ac_bookings (plan, eb, amount_cents, rate_cents, name, email, phone, practice, practice_note, why, lang, wr, consent_weekend, call_id, token, test, ip_hash, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$plan, $p['eb'] ? 1 : 0, $amount, $rate, $name, $email, $phone, $practice, $note, $why, $lang, (string)$in['wr'], 1, $offer ? (int)$offer['id'] : null, $tok, $test, $ipHash, date('c')]);
    $id = (int)$pdo->lastInsertId();
    $site = mm_site();
    $start = mv_date($c['start_ts'], $lang);
    $label = AC_TITLE . ($plan === 'inst' ? ($en ? ' · instalment' : ' · Rate') : '') . ($p['eb'] ? ' · Early Bird' : '');
    $price = ['currency' => 'eur', 'unit_amount' => $rate, 'product_data' => ['name' => $label, 'description' => ($en ? '500-hour hybrid training, starts ' : '500-Stunden-Ausbildung, hybrid, Start ') . $start . ' · Movecia Academy, Vivien Malonga']];
    if ($plan === 'inst') $price['recurring'] = ['interval' => 'month'];
    $terms = ($en ? 'Terms and cancellation: ' : 'Bedingungen und Rücktritt: ') . $site . '/terms#teacher';
    $msg = ($en ? 'Your training starts on ' . $start . '. ' : 'Deine Ausbildung startet am ' . $start . '. ') . ($plan === 'inst' ? ($en ? 'You pay ' . $p['n'] . ' monthly instalments of ' . ac_eur($rate, 'en') . ', starting today. ' : 'Du zahlst ' . $p['n'] . ' Monatsraten zu ' . ac_eur($rate) . ', beginnend heute. ') : '') . $terms;
    $params = [
        'mode' => $plan === 'inst' ? 'subscription' : 'payment',
        'locale' => $en ? 'en' : 'de',
        'billing_address_collection' => 'required',
        'customer_email' => $email,
        'client_reference_id' => 'ac-' . $id,
        'metadata' => ['ac_booking' => (string)$id],
        'line_items' => [['quantity' => 1, 'price_data' => $price]],
        'custom_text' => ['submit' => ['message' => mb_substr($msg, 0, 1000)]],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/academy.php?paid=' . $id . '&k=' . $tok,
        'cancel_url' => $site . '/academy/teacher-training/?abbruch=1' . ($en ? '&lang=en' : '') . '#buchen',
        'branding_settings' => ['display_name' => 'Movecia Academy'],
    ];
    if ($plan === 'inst') $params['subscription_data'] = ['metadata' => ['ac_booking' => (string)$id], 'description' => AC_TITLE];
    else $params['payment_intent_data'] = ['statement_descriptor_suffix' => 'MOVECIA', 'description' => AC_TITLE];
    try {
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params, null, '2025-09-30.clover');
    } catch (Throwable $e) {
        unset($params['branding_settings']);
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params);
    }
    $pdo->prepare('UPDATE ac_bookings SET stripe_session = ? WHERE id = ?')->execute([(string)$s['id'], $id]);
    return (string)$s['url'];
}

/** Offene Buchung bei Stripe prüfen. Bei Zahlung: Mails, Rechnung, Info an Vivien (genau einmal). */
function ac_check_paid(PDO $pdo, array $r): string
{
    if ($r['status'] !== 'offen' || $r['stripe_session'] === '') return (string)$r['status'];
    $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($r['stripe_session']));
    if (($s['status'] ?? '') === 'expired') { $pdo->prepare("UPDATE ac_bookings SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([$r['id']]); return 'abgebrochen'; }
    if (($s['status'] ?? '') !== 'complete' && ($s['payment_status'] ?? '') !== 'paid') return 'offen';
    $cd = $s['customer_details'] ?? [];
    $a = $cd['address'] ?? [];
    $country = strtoupper((string)($a['country'] ?? 'AT')) ?: 'AT';
    $addr = implode("\n", array_filter([(string)($a['line1'] ?? ''), (string)($a['line2'] ?? ''), trim(($a['postal_code'] ?? '') . ' ' . ($a['city'] ?? '')), $country !== 'AT' ? $country : '']));
    $u = $pdo->prepare("UPDATE ac_bookings SET status = 'bezahlt', paid_at = ?, sub_id = ?, customer = ?, address = ?, country = ? WHERE id = ? AND status = 'offen'");
    $u->execute([date('c'), (string)($s['subscription'] ?? ''), (string)($s['customer'] ?? ''), $addr, $country, $r['id']]);
    if ($u->rowCount() !== 1) return 'bezahlt';
    $r = ac_booking($pdo, (int)$r['id']);
    if ($r['sub_id'] !== '') {
        // Raten: Abo endet kurz nach der letzten Rate
        try { forma_stripe('POST', '/v1/subscriptions/' . rawurlencode($r['sub_id']), ['cancel_at' => strtotime('+' . (ac_cfg()['n'] - 1) . ' months +10 days')]); }
        catch (Throwable $e) { error_log('AC cancel_at: ' . $e->getMessage()); }
        ac_sync($pdo, $r);
    } else {
        ac_payment($pdo, $r, 'cs:' . $r['stripe_session'], (int)($s['amount_total'] ?? $r['amount_cents']), 1);
    }
    ac_after_paid($pdo, ac_booking($pdo, (int)$r['id']));
    return 'bezahlt';
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

/** Ratenzahlung bei Stripe abgleichen: jede bezahlte Rate bekommt ihre Rechnung. */
function ac_sync(PDO $pdo, array $r): void
{
    if ($r['sub_id'] === '') return;
    try {
        $inv = forma_stripe('GET', '/v1/invoices', ['subscription' => $r['sub_id'], 'status' => 'paid', 'limit' => 12]);
        $n = 0;
        foreach (array_reverse($inv['data'] ?? []) as $i) {
            if ((int)($i['amount_paid'] ?? 0) <= 0) continue;
            $n++;
            ac_payment($pdo, $r, 'in:' . $i['id'], (int)$i['amount_paid'], $n);
        }
        $pdo->prepare('UPDATE ac_bookings SET synced_at = ? WHERE id = ?')->execute([time(), $r['id']]);
    } catch (Throwable $e) {
        error_log('AC sync: ' . $e->getMessage());
    }
}

/** Gedrosselt bei Seitenaufrufen: offene Zahlungen nachprüfen, Raten abgleichen. */
function ac_tick(PDO $pdo): void
{
    ac_schema($pdo);
    $f = forma_data_dir() . '/movecia-academy-tick';
    if (is_file($f) && (int)file_get_contents($f) > time() - 600) return;
    @file_put_contents($f, (string)time());
    if (!forma_stripe_key()) return;
    $open = $pdo->prepare("SELECT * FROM ac_bookings WHERE status = 'offen' AND stripe_session <> '' AND created_at >= ? ORDER BY id LIMIT 10");
    $open->execute([date('c', time() - 2 * 86400)]);
    foreach ($open->fetchAll() as $r) { try { ac_check_paid($pdo, $r); } catch (Throwable $e) { error_log('AC tick: ' . $e->getMessage()); } }
    $due = $pdo->prepare("SELECT * FROM ac_bookings WHERE status = 'bezahlt' AND sub_id <> '' AND synced_at < ? AND (SELECT COUNT(*) FROM ac_payments p WHERE p.booking_id = ac_bookings.id) < ? LIMIT 10");
    $due->execute([time() - 6 * 3600, ac_cfg()['n']]);
    foreach ($due->fetchAll() as $r) ac_sync($pdo, $r);
}

// --- Mails ----------------------------------------------------------------------------------------

/** Rechtliches für die Bestätigungsmail (kurz, mit Links). */
function ac_legal(array $r): string
{
    $en = $r['lang'] === 'en';
    $c = ac_cfg();
    $d = fn(int $t) => mv_date($t, $en ? 'en' : 'de');
    $until = ac_withdraw_until($r);
    $site = mm_site();
    $x = $en
        ? 'Your right of withdrawal: because your training only starts on ' . $d($c['start_ts']) . ', your 14-day withdrawal period only starts then too. You can withdraw without giving a reason until ' . $d($until) . '. If you withdraw before the start, you get everything back; after the start you pay only for the part already used. '
        : 'Dein Rücktrittsrecht: Weil deine Ausbildung erst am ' . $d($c['start_ts']) . ' beginnt, beginnt auch deine 14-tägige Rücktrittsfrist erst an diesem Tag. Du kannst also bis ' . $d($until) . ' ohne Angabe von Gründen zurücktreten. Vor dem Start bekommst du alles zurück, nach dem Start zahlst du nur den Teil, der bis dahin genutzt wurde. ';
    $x .= $r['wr'] === 'verzicht'
        ? ($en ? 'Your choice: you use everything from the start, including all videos and workbook downloads, and you waive your right of withdrawal for this digital content (§ 18 (1) 11 FAGG). '
               : 'Deine Wahl: Du nutzt ab dem Start gleich alles, auch alle Videos und Workbook-Downloads, und verzichtest für diese digitalen Inhalte auf dein Rücktrittsrecht (§ 18 Abs. 1 Z 11 FAGG). ')
        : ($en ? 'Your choice: you keep your right of withdrawal. Live calls and videos start as planned, and the workbook downloads unlock on ' . $d($until + 60) . '. '
               : 'Deine Wahl: Du behältst dein Rücktrittsrecht. Live-Calls und Videos starten wie geplant, die Workbook-Downloads öffnen sich am ' . $d($until + 60) . '. ');
    $x .= $en
        ? 'The in-person weekend in September 2028 is a fixed date and cannot be made up later; if you cannot attend, the weekend is not refunded. '
        : 'Das Präsenzwochenende im September 2028 ist ein fester Termin und kann nicht nachgeholt werden; kannst du nicht teilnehmen, wird das Wochenende nicht erstattet. ';
    if ($r['plan'] === 'inst') $x .= $en ? 'The instalments are owed for the whole training, even if you stop taking part. ' : 'Die Raten bleiben für die ganze Ausbildung geschuldet, auch wenn du nicht mehr teilnimmst. ';
    return $x . ($en ? 'Terms and withdrawal form: ' : 'Bedingungen und Widerrufsformular: ') . $site . '/terms#teacher · ' . ($en ? 'Withdraw: ' : 'Vertrag widerrufen: ') . $site . '/withdraw?v=teacher';
}

function ac_after_paid(PDO $pdo, array $r): void
{
    $u = $pdo->prepare('UPDATE ac_bookings SET mailed = 1 WHERE id = ? AND mailed = 0');
    $u->execute([$r['id']]);
    if ($u->rowCount() !== 1) return;
    try { ac_mail_welcome($r); } catch (Throwable $e) { error_log('AC welcome: ' . $e->getMessage()); }
    try { ac_notify_viv($pdo, $r); } catch (Throwable $e) { error_log('AC notify: ' . $e->getMessage()); }
}

function ac_mail_welcome(array $r): void
{
    $en = $r['lang'] === 'en';
    $c = ac_cfg();
    $first = forma_first_name((string)$r['name'] ?: ($en ? 'friend' : 'meine Liebe'));
    $start = mv_date($c['start_ts'], $en ? 'en' : 'de');
    $price = $r['plan'] === 'inst'
        ? ($en ? $c['n'] . ' monthly instalments of ' . ac_eur((int)$r['rate_cents'], 'en') : $c['n'] . ' Monatsraten zu ' . ac_eur((int)$r['rate_cents']))
        : ac_eur((int)$r['amount_cents'], $en ? 'en' : 'de');
    $lines = $en ? [
        "Dear $first,",
        "I'm so happy you're joining! Your place on " . AC_TITLE . " ($price" . ((int)$r['eb'] ? ', early bird' : '') . ') is confirmed.',
        'Our first module opens on ' . $start . ', right after the autumn school holidays. From then on, a new module opens on the 1st of every month until September 2028, with theory videos, an extensive workbook and our weekly live call.',
        'Until the start there is nothing you need to do. If you like, keep practising with the Method, Unfold or the free Gatherings, which is the best preparation there is. A few weeks before we begin, you will get an email from me with your login and everything you need for the first module.',
        'The in-person weekend in the Tyrolean Alps takes place in September 2028, from Friday 8:30 am to Sunday 5:30 pm, with accommodation and meals included. I will send you the exact dates and venue as soon as they are fixed, well over a year in advance, so you can plan for it.',
        'Your 14-day withdrawal period only begins with the start, so you can withdraw until ' . mv_date(ac_withdraw_until($r), 'en') . '. You will receive your invoice in a separate email.',
    ] : [
        "Hallo $first,",
        'ich freue mich so, dass du dabei bist! Dein Platz in ' . AC_TITLE . " ($price" . ((int)$r['eb'] ? ', Early-Bird' : '') . ') ist bestätigt.',
        'Unser erstes Modul öffnet sich am ' . $start . ', gleich nach den Herbstferien. Danach kommt jeweils am 1. des Monats ein neues Modul dazu, bis September 2028, mit Theorie-Videos, einem ausführlichen Workbook und unserem wöchentlichen Live-Call.',
        'Bis zum Start musst du nichts tun. Wenn du magst, übst du weiter mit der Method, mit Unfold oder in den kostenlosen Gatherings, eine bessere Vorbereitung gibt es nicht. Ein paar Wochen vor dem Beginn bekommst du von mir eine E-Mail mit deinem Zugang und allem, was du fürs erste Modul brauchst.',
        'Das Präsenzwochenende in den Tiroler Alpen findet im September 2028 statt, von Freitag 8:30 Uhr bis Sonntag 17:30 Uhr, Unterkunft und Verpflegung sind inklusive. Die genauen Tage und den Ort schicke ich dir, sobald alles fix ist, gut ein Jahr im Voraus, damit du planen kannst.',
        'Deine 14-tägige Rücktrittsfrist beginnt erst mit dem Start, du kannst also bis ' . mv_date(ac_withdraw_until($r)) . ' zurücktreten. Deine Rechnung bekommst du in einer eigenen E-Mail.',
    ];
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    mv_send_member_mail($r, $pfx . ($en ? 'You\'re in: Mindful Movement Teacher Training' : 'Du bist dabei: Mindful Movement Teacher Training'), $en ? 'welcome to the Academy ♡' : 'willkommen in der Academy ♡', AC_SHORT, $lines, '', '', ac_legal($r));
}

function ac_practice_label(string $k): string
{
    return ['lt1' => 'weniger als 1 Jahr', '1to2' => '1–2 Jahre', 'gt2' => 'mehr als 2 Jahre', 'teach' => 'unterrichtet schon'][$k] ?? '–';
}

function ac_notify_viv(PDO $pdo, array $r): void
{
    $c = ac_cfg();
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    $v = "Hallo Viv,\n\n" . $r['name'] . ' (' . $r['email'] . ', ' . $r['phone'] . ') hat gerade das Teacher Training gebucht: '
        . ($r['plan'] === 'inst' ? $c['n'] . ' Raten zu ' . mv_eur((int)$r['rate_cents']) : mv_eur((int)$r['amount_cents']) . ' auf einmal') . ((int)$r['eb'] ? ' (Early-Bird)' : '') . ($r['call_id'] ? ', aus dem Kennenlerngespräch' : '')
        . '. Noch ' . ac_free($pdo) . ' von ' . $c['seats'] . " Plätzen frei.\n\nEigene Praxis: " . ac_practice_label((string)$r['practice']) . ($r['practice_note'] !== '' ? "\n" . $r['practice_note'] : '')
        . "\n\nWarum sie die Ausbildung machen möchte:\n" . $r['why'] . "\n\nRücktrittsrecht: " . ($r['wr'] === 'verzicht' ? 'verzichtet (alles ab Start offen)' : 'behalten') . ', Rücktritt möglich bis ' . date('d.m.Y', ac_withdraw_until($r))
        . "\n\nAlle Buchungen: " . forma_site_url() . "/verwaltung/?view=academy\n";
    mm_mail(natal_notify_email_safe(), $pfx . 'Neue Buchung: Teacher Training · ' . $r['name'], $v, mm_mail_html('Neue Buchung', nl2br(forma_h($v)), 'de'));
}

/** Benachrichtigungsadresse wie bei Prä-/Postnatal (in der Verwaltung änderbar), sonst hello@moveciamovement.com. */
function natal_notify_email_safe(): string
{
    if (!function_exists('natal_notify_email')) require_once __DIR__ . '/natal-lib.php';
    return natal_notify_email();
}

// --- Rechnung ------------------------------------------------------------------------------------

function ac_invoice_source(PDO $pdo, int $payId): ?array
{
    ac_schema($pdo);
    $q = $pdo->prepare('SELECT p.*, b.plan, b.name, b.email, b.address, b.country, b.eb FROM ac_payments p JOIN ac_bookings b ON b.id = p.booking_id WHERE p.id = ?');
    $q->execute([$payId]);
    $p = $q->fetch();
    if (!$p || (int)$p['cents'] <= 0) return null;
    $c = ac_cfg();
    $text = AC_TITLE . ($p['plan'] === 'inst' ? ' · Rate ' . (int)$p['n'] . ' von ' . $c['n'] : '');
    $detail = 'Ausbildung, 500 Std. inkl. 200-Std.-Yogalehrerausbildung, hybrid, ' . date('d.m.Y', $c['start_ts']) . ' bis September 2028, inkl. Präsenzwochenende mit Unterkunft und Verpflegung, Abschlussprüfung und Zertifikat' . ((int)$p['eb'] ? ' · Early-Bird' : '');
    return ['name' => $p['name'], 'email' => $p['email'], 'session' => '', 'address' => $p['address'], 'country' => $p['country'] ?: 'AT', 'service' => date('d.m.Y', $c['start_ts']),
        'items' => [['text' => $text, 'detail' => $detail, 'qty' => 1, 'unit' => (int)$p['cents']]],
        'gross' => (int)$p['cents'], 'paid' => $p['paid_at'], 'type' => 'Movecia',
        'pay_note' => 'Der Betrag wurde am ' . date('d.m.Y', strtotime((string)$p['paid_at'])) . ' online bezahlt (Kartenzahlung über Stripe, gemeinsames Konto FORMA & Movecia). Buchungsvermerk: Leistung der Movecia Academy (Movecia – Personal Fitness).'];
}

// --- Kennenlerngespräch (kostenlos, 30 Min., Wunschtermin aus festen Zeiten) -------------------------

/** Wählbare Termine der nächsten 8 Wochen (ab 12 Std. Vorlauf), ohne schon angefragte/bestätigte. */
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
        $wd = (int)$d->format('N');
        foreach (AC_CALL_SLOTS[$wd] ?? [] as $slot) {
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

/** Anfrage speichern (Termin wird sofort für andere gesperrt), Eingangsbestätigung + Info an Vivien. */
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

/** Vivien bestätigt: Videoraum anlegen (Whereby, falls eingerichtet), Mail mit Link an die Interessentin. */
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

// --- Google & Auffindbarkeit ---------------------------------------------------------------------

function ac_faq(): array
{
    $c = ac_cfg();
    return [
        ['Für wen ist die Ausbildung?', 'For whom is the training?',
         'Für Frauen, die selbst schon eine Weile achtsam in Bewegung sind und andere begleiten möchten. Ideal sind etwa eineinhalb Jahre eigene Praxis, zum Beispiel mit der Method, mit Unfold oder in den Gatherings. Du musst keine Sportlerin sein, wichtiger sind Neugier und Freude am Zuhören.',
         'For women who have been moving mindfully for a while themselves and want to guide others. About a year and a half of your own practice is ideal, for example with the Method, Unfold or the Gatherings. You don\'t need to be athletic; curiosity and a love of listening matter more.'],
        ['Ist eine Yogalehrerausbildung enthalten?', 'Does it include a yoga teacher training?',
         'Ja. 200 der 500 Stunden sind eine vollständige Yogalehrerausbildung nach internationalem 200-Stunden-Curriculum. Dazu kommen Somatic Movement, Breathwork und Meditation, Anatomie und Body Scanning, die Movecia Movement Method mit Training der Tiefenmuskulatur, Mental Load und Nervensystem sowie der Aufbau deiner Selbstständigkeit.',
         'Yes. 200 of the 500 hours are a complete yoga teacher training following an international 200-hour curriculum. On top of that come somatic movement, breathwork and meditation, anatomy and body scanning, the Movecia Movement Method with deep core training, mental load and the nervous system, and building your own business.'],
        ['Wie läuft die Ausbildung ab?', 'How does the training work?',
         'Hybrid über 11 Monate: Am ' . mv_date($c['start_ts']) . ' öffnet sich Modul 1, danach jeweils am 1. des Monats ein neues Modul mit Theorie-Videos und ausführlichem Workbook. Jede Woche treffen wir uns live per Video. Im September 2028 kommen wir zu einem Präsenzwochenende in den Tiroler Alpen zusammen.',
         'Hybrid over 11 months: module 1 opens on ' . mv_date($c['start_ts'], 'en') . ', then a new module with theory videos and an extensive workbook opens on the 1st of every month. Every week we meet live by video. In September 2028 we come together for an in-person weekend in the Tyrolean Alps.'],
        ['Was passiert beim Präsenzwochenende?', 'What happens at the in-person weekend?',
         'Von Freitag 8:30 Uhr bis Sonntag 17:30 Uhr üben, unterrichten und lernen wir gemeinsam, mit zwei Übernachtungen, Unterkunft und Verpflegung sind im Preis enthalten. Jede Teilnehmerin hält dort ihre Lehrprobe: eine eigene, etwa 25-minütige Einheit, die sie im Modul Sequencing entwickelt hat. Das Wochenende ist ein fester Termin und kann nicht nachgeholt werden.',
         'From Friday 8:30 am to Sunday 5:30 pm we practise, teach and learn together, with two nights; accommodation and meals are included in the price. Each participant teaches her own unit of about 25 minutes, which she developed in the sequencing module. The weekend is a fixed date and cannot be made up later.'],
        ['Wie bekomme ich mein Zertifikat?', 'How do I get my certificate?',
         'Nach dem Präsenzwochenende mit deiner Lehrprobe legst du online die Multiple-Choice-Abschlussprüfung ab, zu Hause, mit eingeschalteter Kamera in unserem Online-Prüfungsraum. Hast du die Prüfung bestanden und ca. 100 Praxisstunden dokumentiert, bekommst du dein Zertifikat der Movecia Academy.',
         'After the in-person weekend with your teaching practice, you take the online multiple-choice final exam at home, with your camera on in our online exam room. Once you have passed and documented around 100 practice hours, you receive your Movecia Academy certificate.'],
        ['Was kostet die Ausbildung?', 'How much does the training cost?',
         'Early-Bird bis ' . mv_date($c['eb_end']) . ': ' . mv_eur($c['eb_once']) . ', danach ' . mv_eur($c['r_once']) . '. Unterkunft und Verpflegung am Präsenzwochenende, die Prüfung und das Zertifikat sind inklusive. Du zahlst auf einmal oder in ' . $c['n'] . ' Monatsraten mit kleinem Aufschlag. Es gibt höchstens ' . $c['seats'] . ' Plätze.',
         'Early bird until ' . mv_date($c['eb_end'], 'en') . ': ' . ac_eur($c['eb_once'], 'en') . ', after that ' . ac_eur($c['r_once'], 'en') . '. Accommodation and meals at the in-person weekend, the exam and the certificate are included. You pay in full or in ' . $c['n'] . ' monthly instalments with a small surcharge. There are no more than ' . $c['seats'] . ' places.'],
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
    if ($c['eb']) $offers[] = ['@type' => 'Offer', 'name' => 'Early Bird', 'price' => number_format($c['eb_once'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'validThrough' => date('Y-m-d', $c['eb_end']), 'availability' => $avail, 'url' => $url . '#buchen', 'category' => 'Paid'];
    $offers[] = ['@type' => 'Offer', 'name' => $c['eb'] ? 'Regulär ab ' . date('j.n.Y', $c['eb_end'] + 86400) : 'Regulär', 'price' => number_format($c['r_once'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'availability' => $avail, 'url' => $url . '#buchen', 'category' => 'Paid'] + ($c['eb'] ? ['validFrom' => date('Y-m-d', $c['eb_end'] + 86400)] : []);
    $course = ['@context' => 'https://schema.org', '@type' => 'Course', '@id' => $url . '#course', 'name' => AC_TITLE,
        'description' => '500-Stunden-Ausbildung (hybrid, 11 Monate) inkl. 200-Stunden-Yogalehrerausbildung nach internationalem Curriculum, Somatic Movement, Breathwork & Meditation, Anatomie & Body Scanning, Mental Load & Nervensystem. Online-Module, wöchentlicher Live-Call und Präsenzwochenende in den Tiroler Alpen.',
        'provider' => $org, 'inLanguage' => 'de', 'image' => $site . '/img/movecia/og-academy.jpg', 'url' => $url,
        'educationalCredentialAwarded' => 'Zertifikat der Movecia Academy (500 Stunden)', 'timeRequired' => 'P11M', 'numberOfCredits' => 500,
        'coursePrerequisites' => 'Etwa 1,5 Jahre eigene Bewegungspraxis empfohlen',
        'teaches' => ['Yoga (200 Stunden)', 'Somatic Movement', 'Breathwork', 'Meditation', 'Anatomie und Body Scanning', 'Nervensystem und Mental Load', 'Training der Tiefenmuskulatur', 'Unterrichten und Sequencing', 'Aufbau der Selbstständigkeit'],
        'offers' => $offers,
        'hasCourseInstance' => [['@type' => 'CourseInstance', 'courseMode' => 'Blended', 'startDate' => $c['start'], 'endDate' => $c['end'], 'inLanguage' => 'de',
            'courseSchedule' => ['@type' => 'Schedule', 'repeatFrequency' => 'P1W', 'startDate' => $c['start'], 'endDate' => $c['end']],
            'courseWorkload' => 'PT500H', 'maximumAttendeeCapacity' => $c['seats'], 'instructor' => ['@type' => 'Person', 'name' => 'Vivien Malonga'],
            'location' => [['@type' => 'VirtualLocation', 'url' => $url], ['@type' => 'Place', 'name' => 'Präsenzwochenende in den Tiroler Alpen', 'address' => ['@type' => 'PostalAddress', 'addressRegion' => 'Tirol', 'addressCountry' => 'AT']]]]]];
    $faq = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[2]]], ac_faq())];
    $bc = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Movecia', 'item' => $site . '/'], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Academy', 'item' => $site . '/academy/'], ['@type' => 'ListItem', 'position' => 3, 'name' => 'Teacher Training', 'item' => $url]]];
    return [$course, $faq, $bc];
}

<?php
// Movecia-Gutscheine (Vivien, 26.10.2026): wie bei FORMA, aber eigener Bereich auf moveciamovement.com/gutschein.
// Gutscheine für Unfold, die Movecia Method (1, 3 oder 12 Monate), Prä- & Postnatal Pakete und Wertgutscheine,
// je in zwei Designs („Kiesel“ und „Welle“), zum Ausdrucken. Nach der Zahlung: Nummer MOV-JJJJ-0001, geheimer Code
// MOV-XXXX-XXXX-XXXX, Mail mit PDF, Rechnung im Movecia-Design (Rechnungsart mv_gutschein, gemeinsamer Nummernkreis).
// Einlösen online bei der Buchung (Unfold, Method, Prä-/Postnatal) mit dem Code. Ein Programm-Gutschein deckt sein
// Programm ganz, auch wenn der Preis inzwischen gestiegen ist. Für andere Angebote zählt sein Wert, ein Rest bleibt.
// Vivien sieht in der Verwaltung → Movecia → Gutscheine, wer gekauft hat, ob bezahlt ist, Rechnungsnummer, Restwert.
declare(strict_types=1);
require_once __DIR__ . '/method-lib.php';
require_once __DIR__ . '/natal-lib.php';

const MVG_CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const MVG_HOLD = 40 * 60;   // so lange bleibt ein Gutscheinbetrag für eine offene Zahlung reserviert
const MVG_WERT_MIN = 2500;
const MVG_WERT_MAX = 100000;
const MVG_DESIGNS = [1 => ['de' => 'Kiesel', 'en' => 'Pebble'], 2 => ['de' => 'Welle', 'en' => 'Wave']];

function mvg_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    mv_schema($pdo);
    natal_schema($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS mv_vouchers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nr TEXT,
        code TEXT,
        product TEXT NOT NULL,
        amount_cents INTEGER NOT NULL,
        design INTEGER NOT NULL DEFAULT 1,
        lang TEXT NOT NULL DEFAULT 'de',
        recipient TEXT NOT NULL DEFAULT '',
        sender TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL DEFAULT '',
        buyer_name TEXT NOT NULL DEFAULT '',
        buyer_email TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'offen',
        kind TEXT NOT NULL DEFAULT 'kauf',
        via TEXT NOT NULL DEFAULT 'web',
        pay_method TEXT NOT NULL DEFAULT '',
        stripe_session TEXT NOT NULL DEFAULT '',
        token TEXT NOT NULL,
        mailed INTEGER NOT NULL DEFAULT 0,
        test INTEGER NOT NULL DEFAULT 0,
        note TEXT NOT NULL DEFAULT '',
        ip_hash TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        paid_at TEXT,
        redeemed_at TEXT
    )");
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS mv_vouchers_code ON mv_vouchers(code)');
    // E-Mail zuerst bestätigen (Vivien, 26.10.2026): Gutschein-Mail mit PDF/Code und Rechnung erst danach
    $vc = array_column($pdo->query('PRAGMA table_info(mv_vouchers)')->fetchAll(), 'name');
    if (!in_array('email_ok', $vc, true)) {
        $pdo->exec('ALTER TABLE mv_vouchers ADD COLUMN email_ok INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE mv_vouchers ADD COLUMN confirm_sent INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE mv_vouchers ADD COLUMN confirm_reminded INTEGER NOT NULL DEFAULT 0');
        $pdo->exec("UPDATE mv_vouchers SET email_ok = 1 WHERE mailed = 1");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS mv_voucher_uses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        voucher_id INTEGER NOT NULL,
        ref_kind TEXT NOT NULL,
        ref_id INTEGER NOT NULL,
        cents INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'reserviert',
        note TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
    )");
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_voucher_tries (ip_hash TEXT NOT NULL, at INTEGER NOT NULL)');
    // Einlösen: Gutschein an der Buchung merken (Rechnung zeigt ihn als eigene Zeile)
    foreach (['mv_subs', 'mm_natal_orders'] as $t) {
        $cols = array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(), 'name');
        if (!in_array('voucher_id', $cols, true)) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN voucher_id INTEGER");
            $pdo->exec("ALTER TABLE $t ADD COLUMN voucher_cents INTEGER NOT NULL DEFAULT 0");
        }
    }
}

// --- Angebote ------------------------------------------------------------------------------------

/**
 * Alle Gutscheine mit aktuellem Preis. group: unfold, method, natal, wert.
 * title/sub: groß auf dem Gutschein, desc: eine Zeile darunter.
 */
function mvg_products(): array
{
    $c = mv_cfg();
    $p = [
        'unfold' => ['group' => 'unfold', 'cents' => (int)$c['u_once'],
            'title' => ['de' => 'Unfold', 'en' => 'Unfold'], 'sub' => ['de' => 'The Mindful Becoming', 'en' => 'The Mindful Becoming'],
            'name' => ['de' => 'Unfold – The Mindful Becoming', 'en' => 'Unfold – The Mindful Becoming'],
            'desc' => ['de' => '3 Monate Begleitung: wöchentlicher Live-Call, Bewegungsvideos, Workbooks und kleine Rituale für dich.', 'en' => '3 months of guidance: a weekly live call, movement videos, workbooks and small rituals for you.']],
    ];
    foreach (['m1' => 1, 'm3' => 3, 'm12' => 12] as $plan => $mo) {
        $t = $mo === 12 ? ['de' => '1 Jahr Method', 'en' => '1 year of the Method'] : ($mo === 1 ? ['de' => '1 Monat Method', 'en' => '1 month of the Method'] : ['de' => $mo . ' Monate Method', 'en' => $mo . ' months of the Method']);
        $p['method_' . $plan] = ['group' => 'method', 'cents' => (int)$c[$plan], 'months' => $mo, 'plan' => $plan, 'title' => $t,
            'sub' => ['de' => 'Mindful Movement on demand', 'en' => 'Mindful movement on demand'],
            'name' => ['de' => 'Movecia Method · ' . ($mo === 12 ? '1 Jahr' : ($mo === 1 ? '1 Monat' : $mo . ' Monate')), 'en' => 'Movecia Method · ' . ($mo === 12 ? '1 year' : ($mo === 1 ? '1 month' : $mo . ' months'))],
            'desc' => ['de' => 'Bewegung, Breathwork und Meditation in der Mitgliederbibliothek, Monat für Monat ein neues Programm. Ohne Abo, endet von selbst.', 'en' => 'Movement, breathwork and meditation in the members library, a new programme every month. No subscription, it simply ends.']];
    }
    foreach (natal_packages() as $k => $x) {
        $on = $x['mode'] === 'online';
        $base = str_replace(' online', '', $x['title']);
        $en = ['einzel' => 'Single session', 'prae' => 'Prenatal package', 'post' => 'Postnatal package', 'kombi' => 'Combined package'][preg_replace('/^o_/', '', $k)] ?? $base;
        $units = (int)$x['units'];
        $p['natal_' . $k] = ['group' => 'natal', 'cents' => (int)$x['cents'], 'natal' => $k, 'online' => $on,
            'title' => ['de' => $base, 'en' => $en],
            'sub' => ['de' => ($units === 1 ? '60 Minuten' : $units . ' Einheiten') . ' · 1:1 ' . ($on ? 'online' : 'im Studio'), 'en' => ($units === 1 ? '60 minutes' : $units . ' sessions') . ' · 1:1 ' . ($on ? 'online' : 'in the studio')],
            'name' => ['de' => 'Prä- & Postnatal Training · ' . $x['title'], 'en' => 'Pre- & postnatal training · ' . $en . ($on ? ' online' : '')],
            'desc' => ['de' => 'Prä- & Postnatal Training mit Vivien: Bewegung, Breathwork und Entspannung für Schwangerschaft und die Zeit danach.', 'en' => 'Pre- & postnatal training with Vivien: movement, breathwork and relaxation for pregnancy and the time after.']];
    }
    $p['wert'] = ['group' => 'wert', 'cents' => 0,
        'title' => ['de' => 'Wertgutschein', 'en' => 'Gift voucher'], 'sub' => ['de' => 'für alles bei Movecia', 'en' => 'for everything at Movecia'],
        'name' => ['de' => 'Wertgutschein', 'en' => 'Gift voucher'],
        'desc' => ['de' => 'Einlösbar für Unfold, die Movecia Method und das Prä- & Postnatal Training.', 'en' => 'For Unfold, the Movecia Method and the pre- & postnatal training.']];
    return $p;
}

function mvg_product(array $v): array
{
    return mvg_products()[$v['product']] ?? mvg_products()['wert'];
}

/** „Unfold – The Mindful Becoming“ bzw. „Wertgutschein über € 100“ */
function mvg_title(array $v, string $lang = 'de'): string
{
    $p = mvg_product($v);
    if ($v['product'] === 'wert' || !isset(mvg_products()[$v['product']])) return ($lang === 'en' ? 'Gift voucher for ' : 'Wertgutschein über ') . mv_eur((int)$v['amount_cents']);
    return $p['name'][$lang] ?? $p['name']['de'];
}

function mvg_terms(string $lang = 'de'): string
{
    return $lang === 'en'
        ? 'No expiry date. Redeem it online at moveciamovement.com with your code when you book Unfold, the Movecia Method or pre- & postnatal training. A programme voucher covers its programme in full, even if the price has gone up in the meantime; for other offers its value counts. Any remaining value stays on the voucher. Unfold has limited places: if a round is full, the voucher is valid for the next one. No cash payout. Whoever knows the code can redeem it, so please keep it safe.'
        : 'Ohne Ablaufdatum. Einlösbar online auf moveciamovement.com mit dem Gutscheincode bei der Buchung von Unfold, der Movecia Method oder dem Prä- & Postnatal Training. Ein Programm-Gutschein deckt sein Programm ganz, auch wenn der Preis inzwischen gestiegen ist; für andere Angebote zählt sein Wert. Ein Restwert bleibt auf dem Gutschein. Unfold hat begrenzte Plätze: Ist eine Runde voll, gilt der Gutschein für die nächste. Keine Barauszahlung. Wer den Code kennt, kann ihn einlösen: bitte gut aufbewahren.';
}

// --- Codes, Restwert, Einlösen -------------------------------------------------------------------

function mvg_new_code(): string
{
    $c = '';
    for ($i = 0; $i < 12; $i++) $c .= MVG_CODE_CHARS[random_int(0, 31)];
    return 'MOV-' . substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4);
}

function mvg_norm(string $code): string
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if (str_starts_with($c, 'MOV')) $c = substr($c, 3);
    $c = strtr($c, ['0' => 'O', '1' => 'I']);
    return strlen($c) === 12 ? 'MOV-' . substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4) : '';
}

function mvg_get(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM mv_vouchers WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function mvg_auth(PDO $pdo, int $id, string $tok): ?array
{
    $v = mvg_get($pdo, $id);
    return ($v && $tok !== '' && hash_equals((string)$v['token'], $tok)) ? $v : null;
}

/** Schon verbrauchter bzw. reservierter Betrag. */
function mvg_used(PDO $pdo, int $id): int
{
    $q = $pdo->prepare("SELECT COALESCE(SUM(cents), 0) FROM mv_voucher_uses WHERE voucher_id = ? AND status IN ('reserviert','eingeloest')");
    $q->execute([$id]);
    return (int)$q->fetchColumn();
}

function mvg_balance(PDO $pdo, array $v): int
{
    if ($v['status'] !== 'bezahlt') return 0;
    return max(0, (int)$v['amount_cents'] - mvg_used($pdo, (int)$v['id']));
}

/** Code suchen, mit Bremse gegen Durchprobieren (8 Fehlversuche pro Stunde und Gerät). */
function mvg_lookup(PDO $pdo, string $code, string $lang = 'de'): array
{
    mvg_schema($pdo);
    $en = $lang === 'en';
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'mvg');
    $pdo->prepare('DELETE FROM mv_voucher_tries WHERE at < ?')->execute([time() - 3600]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM mv_voucher_tries WHERE ip_hash = ?');
    $q->execute([$ip]);
    if ((int)$q->fetchColumn() >= 8) return ['ok' => false, 'error' => $en ? 'Too many attempts. Please try again in an hour or write to me.' : 'Zu viele Versuche. Bitte probier es in einer Stunde noch einmal oder schreib mir.'];
    $norm = mvg_norm($code);
    $v = null;
    if ($norm !== '') {
        $q = $pdo->prepare('SELECT * FROM mv_vouchers WHERE code = ?');
        $q->execute([$norm]);
        $v = $q->fetch() ?: null;
    }
    if (!$v) {
        $pdo->prepare('INSERT INTO mv_voucher_tries (ip_hash, at) VALUES (?, ?)')->execute([$ip, time()]);
        return ['ok' => false, 'error' => $en ? 'I do not know this code. Please check it again (it is on your voucher, e.g. MOV-AB12-CD34-EF56).' : 'Diesen Gutscheincode kenne ich leider nicht. Bitte prüf ihn noch einmal (er steht auf deinem Gutschein, z. B. MOV-AB12-CD34-EF56).'];
    }
    mvg_sweep($pdo);
    $bal = mvg_balance($pdo, $v);
    if ($bal <= 0) return ['ok' => false, 'error' => $en ? 'This voucher has already been redeemed in full.' : 'Dieser Gutschein ist schon vollständig eingelöst.'];
    return ['ok' => true, 'voucher' => $v, 'balance' => $bal];
}

/**
 * Was deckt ein Gutschein bei dieser Buchung?
 * $target: 'unfold', 'method', 'natal:<paket>'. $price: Preis bzw. erste Zahlung in Cent.
 * Ergebnis: cover (wird vom Preis abgezogen), use (wird vom Gutschein abgebucht), full (alles gedeckt),
 * gift_months (Method-Gutschein: so viele Monate ohne Abo), program (Programm-Gutschein für genau dieses Angebot).
 */
function mvg_quote(PDO $pdo, array $v, int $bal, string $target, int $price): array
{
    $p = mvg_product($v);
    $fresh = mvg_used($pdo, (int)$v['id']) === 0;
    $match = $fresh && (($target === 'unfold' && $v['product'] === 'unfold')
        || ($target === 'method' && $p['group'] === 'method')
        || (str_starts_with($target, 'natal:') && $v['product'] === 'natal_' . substr($target, 6)));
    if ($match && $p['group'] === 'method') return ['cover' => 0, 'use' => $bal, 'full' => true, 'gift_months' => (int)$p['months'], 'program' => true];
    if ($match) return ['cover' => $price, 'use' => $bal, 'full' => true, 'gift_months' => 0, 'program' => true];
    $cover = min($bal, $price);
    return ['cover' => $cover, 'use' => $cover, 'full' => $cover >= $price, 'gift_months' => 0, 'program' => false];
}

/** Betrag vom Gutschein abbuchen (reserviert bis zur Zahlung oder gleich eingelöst). */
function mvg_use(PDO $pdo, int $vid, string $refKind, int $refId, int $cents, bool $final, string $note = ''): void
{
    if ($cents <= 0) return;
    $pdo->prepare('INSERT INTO mv_voucher_uses (voucher_id, ref_kind, ref_id, cents, status, note, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$vid, $refKind, $refId, $cents, $final ? 'eingeloest' : 'reserviert', $note, date('c')]);
    if ($final) mvg_after_use($pdo, $vid);
}

function mvg_after_use(PDO $pdo, int $vid): void
{
    $v = mvg_get($pdo, $vid);
    if (!$v || $v['status'] !== 'bezahlt') return;
    $open = $pdo->prepare("SELECT COUNT(*) FROM mv_voucher_uses WHERE voucher_id = ? AND status = 'reserviert'");
    $open->execute([$vid]);
    if (mvg_balance($pdo, $v) <= 0 && (int)$open->fetchColumn() === 0) $pdo->prepare("UPDATE mv_vouchers SET status = 'eingeloest', redeemed_at = ? WHERE id = ?")->execute([date('c'), $vid]);
}

/** Nach der Zahlung einer Buchung: reservierten Gutscheinbetrag endgültig einlösen. */
function mvg_finalize(PDO $pdo, string $refKind, int $refId): void
{
    mvg_schema($pdo);
    $q = $pdo->prepare("SELECT * FROM mv_voucher_uses WHERE ref_kind = ? AND ref_id = ? AND status = 'reserviert'");
    $q->execute([$refKind, $refId]);
    foreach ($q->fetchAll() as $u) {
        $pdo->prepare("UPDATE mv_voucher_uses SET status = 'eingeloest' WHERE id = ?")->execute([$u['id']]);
        mvg_after_use($pdo, (int)$u['voucher_id']);
    }
}

/** Reservierungen ohne Zahlung nach 40 Minuten wieder freigeben. */
function mvg_sweep(PDO $pdo): void
{
    foreach ($pdo->query("SELECT * FROM mv_voucher_uses WHERE status = 'reserviert'")->fetchAll() as $u) {
        $paid = false;
        if ($u['ref_kind'] === 'natal') { $s = $pdo->prepare('SELECT status FROM mm_natal_orders WHERE id = ?'); $s->execute([$u['ref_id']]); $paid = $s->fetchColumn() === 'bezahlt'; }
        if ($u['ref_kind'] === 'mv') { $s = $pdo->prepare('SELECT status FROM mv_subs WHERE id = ?'); $s->execute([$u['ref_id']]); $paid = in_array($s->fetchColumn(), ['aktiv', 'beendet'], true); }
        if ($paid) { $pdo->prepare("UPDATE mv_voucher_uses SET status = 'eingeloest' WHERE id = ?")->execute([$u['id']]); mvg_after_use($pdo, (int)$u['voucher_id']); }
        elseif (strtotime((string)$u['created_at']) < time() - MVG_HOLD) $pdo->prepare("UPDATE mv_voucher_uses SET status = 'frei' WHERE id = ?")->execute([$u['id']]);
    }
}

/** Stripe-Rabatt in Höhe des Gutscheinbetrags, genau einmal nutzbar (für Abos und Raten: nur die erste Zahlung). */
function mvg_stripe_coupon(int $cents, string $nr): string
{
    $c = forma_stripe('POST', '/v1/coupons', ['amount_off' => $cents, 'currency' => 'eur', 'duration' => 'once', 'max_redemptions' => 1, 'name' => 'Gutschein ' . $nr]);
    return (string)$c['id'];
}

// --- Kauf ----------------------------------------------------------------------------------------

/** Neuer Gutschein (offen). $d: product, amount_cents (nur wert), design, lang, recipient, sender, message, buyer_name, buyer_email, via, note, ip_hash */
function mvg_create(PDO $pdo, array $d): array
{
    mvg_schema($pdo);
    $ps = mvg_products();
    $key = (string)($d['product'] ?? '');
    if (!isset($ps[$key])) throw new RuntimeException('Bitte wähl einen Gutschein aus.');
    $cents = $key === 'wert' ? (int)($d['amount_cents'] ?? 0) : (int)$ps[$key]['cents'];
    if ($key === 'wert' && ($cents < MVG_WERT_MIN || $cents > MVG_WERT_MAX)) throw new RuntimeException('Bitte wähl einen Betrag zwischen ' . mv_eur(MVG_WERT_MIN) . ' und ' . mv_eur(MVG_WERT_MAX) . '.');
    if ($cents <= 0) throw new RuntimeException('Für diesen Gutschein fehlt der Preis.');
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO mv_vouchers (product, amount_cents, design, lang, recipient, sender, message, buyer_name, buyer_email, status, kind, via, token, test, note, ip_hash, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$key, $cents, (int)($d['design'] ?? 1) === 2 ? 2 : 1, ($d['lang'] ?? 'de') === 'en' ? 'en' : 'de',
            forma_clean((string)($d['recipient'] ?? ''), 60), forma_clean((string)($d['sender'] ?? ''), 60),
            trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($d['message'] ?? '')) ?? '', 0, 200)),
            forma_clean((string)($d['buyer_name'] ?? ''), 120), strtolower(forma_clean((string)($d['buyer_email'] ?? ''), 160)),
            'offen', (string)($d['kind'] ?? 'kauf'), (string)($d['via'] ?? 'web'), $tok, forma_stripe_test_mode() ? 1 : 0, (string)($d['note'] ?? ''), (string)($d['ip_hash'] ?? ''), date('c')]);
    return mvg_get($pdo, (int)$pdo->lastInsertId());
}

function mvg_pay_link(array $v): string
{
    return mm_site() . '/api/mv-gutschein.php?pay=' . (int)$v['id'] . '&k=' . $v['token'];
}

function mvg_pdf_link(array $v): string
{
    return mm_site() . '/api/mv-gutschein.php?pdf=' . (int)$v['id'] . '&k=' . $v['token'];
}

/** Link aus der Mail: Gutschein-Seite mit eingetragenem Code, von dort weiter zum passenden Angebot. */
function mvg_redeem_link(array $v): string
{
    return mm_site() . '/gutschein/?code=' . rawurlencode((string)$v['code']) . ((string)$v['lang'] === 'en' ? '&lang=en' : '');
}

/** Wohin der Code führt: Seite mit schon eingetragenem Code. */
function mvg_target_url(array $v): string
{
    $p = mvg_product($v);
    $q = '?code=' . rawurlencode((string)$v['code']) . ((string)$v['lang'] === 'en' ? '&lang=en' : '');
    if ($p['group'] === 'unfold') return mm_site() . '/unfold/' . $q . '#buchen';
    if ($p['group'] === 'method') return mm_site() . '/method/' . $q . '#buchen';
    if ($p['group'] === 'natal') return mm_site() . '/natal/' . $q . '&m=' . (!empty($p['online']) ? 'online' : 'studio') . '&p=' . rawurlencode((string)$p['natal']) . '#buchen';
    return mm_site() . '/gutschein/' . $q;
}

/** Stripe-Bezahlseite für einen offenen Gutschein (Kauf auf der Seite oder Bezahl-Link aus der Verwaltung). */
function mvg_checkout(PDO $pdo, array $v): string
{
    if ($v['status'] !== 'offen') throw new RuntimeException('Dieser Gutschein ist schon bezahlt.');
    $en = $v['lang'] === 'en';
    $site = mm_site();
    // offene Bezahlseite wiederverwenden
    if ($v['stripe_session'] !== '') {
        try {
            $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$v['stripe_session']));
            if (($s['payment_status'] ?? '') === 'paid') { mvg_paid($pdo, (int)$v['id']); throw new RuntimeException('Dieser Gutschein ist schon bezahlt.'); }
            if (($s['status'] ?? '') === 'open' && !empty($s['url'])) return (string)$s['url'];
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'schon bezahlt')) throw $e;
        }
    }
    $title = mvg_title($v, $v['lang']);
    $params = [
        'mode' => 'payment',
        'locale' => $en ? 'en' : 'de',
        'billing_address_collection' => 'required',
        'client_reference_id' => 'mv-gutschein-' . $v['id'],
        'metadata' => ['bereich' => 'Movecia', 'mv_gutschein' => (string)$v['id']],
        'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => (int)$v['amount_cents'], 'product_data' => [
            'name' => ($en ? 'Movecia gift voucher · ' : 'Movecia Gutschein · ') . $title,
            'description' => ($en ? 'Printable voucher (PDF) with code' : 'Gutschein zum Ausdrucken (PDF) mit Code') . ' · Movecia – Personal Fitness, Vivien Malonga']]]],
        'payment_intent_data' => ['description' => 'Movecia Gutschein · ' . $title . ' · ' . $v['buyer_name'], 'metadata' => ['bereich' => 'Movecia', 'mv_gutschein' => (string)$v['id']]],
        'custom_text' => ['submit' => ['message' => $en ? 'Right after payment you get your voucher as a PDF to print and your invoice by email.' : 'Gleich nach der Zahlung bekommst du deinen Gutschein als PDF zum Ausdrucken und deine Rechnung per E-Mail.']],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/mv-gutschein.php?paid=' . $v['id'] . '&k=' . $v['token'],
        'cancel_url' => $site . ($v['via'] === 'web' ? '/gutschein/?abbruch=1' . ($en ? '&lang=en' : '') : '/gutschein/?offen=' . $v['id'] . '&k=' . $v['token']),
    ];
    if (filter_var((string)$v['buyer_email'], FILTER_VALIDATE_EMAIL)) $params['customer_email'] = (string)$v['buyer_email'];
    $branded = $params;
    $branded['branding_settings'] = ['display_name' => 'Movecia – Personal Fitness', 'background_color' => '#F2ECE4', 'button_color' => '#7D5E68', 'border_style' => 'rounded', 'font_family' => 'inter'];
    $branded['payment_intent_data']['statement_descriptor_suffix'] = 'MOVECIA';
    try {
        $s = forma_stripe('POST', '/v1/checkout/sessions', $branded, null, '2025-09-30.clover');
    } catch (Throwable $e) {
        error_log('MVG checkout (branded): ' . $e->getMessage());
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params);
    }
    $pdo->prepare('UPDATE mv_vouchers SET stripe_session = ? WHERE id = ?')->execute([(string)$s['id'], $v['id']]);
    return (string)$s['url'];
}

/** Zahlung bei Stripe prüfen. Web-Käufe verfallen nach Ablauf, Verwaltungs-Gutscheine bleiben offen. */
function mvg_check(PDO $pdo, array $v): string
{
    if ($v['status'] !== 'offen' || $v['stripe_session'] === '' || !forma_stripe_key()) return (string)$v['status'];
    $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$v['stripe_session']));
    if (($s['payment_status'] ?? '') === 'paid') {
        $cd = $s['customer_details'] ?? [];
        if ((string)$v['buyer_email'] === '' && !empty($cd['email'])) $pdo->prepare('UPDATE mv_vouchers SET buyer_email = ? WHERE id = ?')->execute([strtolower((string)$cd['email']), $v['id']]);
        if ((string)$v['buyer_name'] === '' && !empty($cd['name'])) $pdo->prepare('UPDATE mv_vouchers SET buyer_name = ? WHERE id = ?')->execute([(string)$cd['name'], $v['id']]);
        mvg_paid($pdo, (int)$v['id'], 'stripe');
        return 'bezahlt';
    }
    if (($s['status'] ?? '') === 'expired') {
        if ($v['via'] !== 'web') { $pdo->prepare("UPDATE mv_vouchers SET stripe_session = '' WHERE id = ?")->execute([$v['id']]); return 'offen'; }
        $pdo->prepare("UPDATE mv_vouchers SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([$v['id']]);
        return 'abgebrochen';
    }
    return 'offen';
}

/** Bezahlt: fortlaufende Nummer MOV-JJJJ-0001, Code, Mails mit PDF, Rechnung. Genau einmal. */
function mvg_paid(PDO $pdo, int $id, string $method = 'stripe'): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $q = $pdo->prepare("SELECT * FROM mv_vouchers WHERE id = ? AND status IN ('offen','abgebrochen')");
        $q->execute([$id]);
        if ($q->fetch()) {
            $year = date('Y');
            $n = (int)$pdo->query("SELECT COUNT(*) FROM mv_vouchers WHERE nr LIKE 'MOV-$year-%'")->fetchColumn() + 1;
            do {
                $code = mvg_new_code();
                $c = $pdo->prepare('SELECT COUNT(*) FROM mv_vouchers WHERE code = ?');
                $c->execute([$code]);
            } while ((int)$c->fetchColumn() > 0);
            $pdo->prepare("UPDATE mv_vouchers SET status = 'bezahlt', paid_at = ?, nr = ?, code = ?, pay_method = ? WHERE id = ?")
                ->execute([date('c'), sprintf('MOV-%s-%04d', $year, $n), $code, $method, $id]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
        throw $e;
    }
    mvg_mails($pdo, $id);
}

/** Wurde diese Adresse schon einmal bestätigt (früherer Gutschein oder Movecia-Konto)? */
function mvg_email_known(PDO $pdo, string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '') return false;
    $q = $pdo->prepare('SELECT COUNT(*) FROM mv_vouchers WHERE lower(buyer_email) = ? AND email_ok = 1');
    $q->execute([$email]);
    if ((int)$q->fetchColumn() > 0) return true;
    $m = mv_member_by_email($pdo, $email);
    return $m && (int)($m['email_ok'] ?? 0) === 1;
}

function mvg_confirm_link(array $v): string
{
    return mm_site() . '/api/mv-gutschein.php?bestaetigen=' . (int)$v['id'] . '&k=' . hash_hmac('sha256', 'mvg-ok:' . $v['id'] . ':' . $v['token'], mv_secret());
}

function mvg_confirm_ok(PDO $pdo, int $id, string $k): ?array
{
    $v = mvg_get($pdo, $id);
    return ($v && hash_equals(hash_hmac('sha256', 'mvg-ok:' . $v['id'] . ':' . $v['token'], mv_secret()), $k)) ? $v : null;
}

/** „Bitte bestätige deine E-Mail-Adresse“ – erst danach kommt der Gutschein. */
function mvg_send_confirm(PDO $pdo, array $v, bool $reminder = false): void
{
    if (!filter_var((string)$v['buyer_email'], FILTER_VALIDATE_EMAIL)) return;
    $en = $v['lang'] === 'en';
    $first = forma_first_name((string)$v['buyer_name']) ?: ($en ? 'love' : 'du');
    $lines = $en ? ["Dear $first,", ($reminder ? 'just a gentle reminder: ' : '') . 'thank you, your payment for the voucher has arrived! Please confirm your email address with one click, then I will send you the voucher as a PDF to print, with its code, and your invoice.']
        : ["Hallo $first,", ($reminder ? 'nur eine kleine Erinnerung: ' : '') . 'danke, deine Zahlung für den Gutschein ist angekommen! Bitte bestätige noch kurz deine E-Mail-Adresse, dann schicke ich dir den Gutschein als PDF zum Ausdrucken mit seinem Code und deine Rechnung.'];
    $btn = $en ? 'Confirm my email' : 'E-Mail bestätigen';
    $text = implode("\n\n", $lines) . "\n\n$btn: " . mvg_confirm_link($v) . ($en ? "\n\nSee you soon,\nVivien" : "\n\nBis bald,\nVivien");
    $html = '<p style="margin:0 0 6px;font-family:\'Brush Script MT\',cursive;font-size:24px;color:#7D5E68;">' . ($en ? 'one last click ♡' : 'nur noch ein Klick ♡') . '</p>';
    foreach ($lines as $l) $html .= '<p>' . forma_h($l) . '</p>';
    $html .= mm_button(mvg_confirm_link($v), $btn) . '<p>' . ($en ? 'See you soon,<br>Vivien' : 'Bis bald,<br>Vivien') . '</p>';
    mm_mail((string)$v['buyer_email'], ((int)$v['test'] === 1 ? '[TEST] ' : '') . ($en ? 'Please confirm your email for your voucher' : 'Bitte bestätige deine E-Mail für deinen Gutschein'), $text, mm_mail_html($en ? 'Your voucher' : 'Dein Gutschein', $html, (string)$v['lang']), [], 'Vivien · Movecia');
    $pdo->prepare('UPDATE mv_vouchers SET confirm_sent = ?' . ($reminder ? ', confirm_reminded = 1' : '') . ' WHERE id = ?')->execute([time(), $v['id']]);
}

/** Klick auf den Link: Adresse gilt für alle bezahlten Gutscheine dieser Adresse, Gutschein-Mails und Rechnungen gehen raus. */
function mvg_confirm(PDO $pdo, array $v): void
{
    $q = $pdo->prepare("SELECT id FROM mv_vouchers WHERE lower(buyer_email) = ? AND email_ok = 0 AND status IN ('bezahlt','eingeloest')");
    $q->execute([strtolower((string)$v['buyer_email'])]);
    $ids = $q->fetchAll(PDO::FETCH_COLUMN);
    $pdo->prepare('UPDATE mv_vouchers SET email_ok = 1 WHERE lower(buyer_email) = ?')->execute([strtolower((string)$v['buyer_email'])]);
    require_once __DIR__ . '/invoices.php';
    foreach ($ids as $id) {
        mvg_send_voucher($pdo, (int)$id);
        $iq = $pdo->prepare("SELECT id FROM invoices WHERE kind = 'mv_gutschein' AND ref_id = ? AND mailed = 0");
        $iq->execute([(int)$id]);
        foreach ($iq->fetchAll(PDO::FETCH_COLUMN) as $iid) { try { forma_invoice_send($pdo, (int)$iid); } catch (Throwable $e) { error_log('MVG invoice release: ' . $e->getMessage()); } }
    }
}

/**
 * Nach der Zahlung (genau einmal): Info an Vivien, Rechnung anlegen (Versand wartet auf die Bestätigung),
 * Kundin: bestätigte Adresse → gleich der Gutschein, sonst zuerst „Bitte bestätige deine E-Mail-Adresse“.
 */
function mvg_mails(PDO $pdo, int $id, bool $again = false): void
{
    $v = mvg_get($pdo, $id);
    if (!$v || !in_array($v['status'], ['bezahlt', 'eingeloest'], true)) return;
    if ($again) { mvg_send_voucher($pdo, $id, true); return; }
    $u = $pdo->prepare('UPDATE mv_vouchers SET mailed = 1 WHERE id = ? AND mailed = 0');
    $u->execute([$id]);
    if ($u->rowCount() !== 1) return;
    if ((int)$v['email_ok'] !== 1 && mvg_email_known($pdo, (string)$v['buyer_email'])) {
        $pdo->prepare('UPDATE mv_vouchers SET email_ok = 1 WHERE id = ?')->execute([$id]);
        $v['email_ok'] = 1;
    }
    mvg_notify_viv($pdo, $v);
    if ($v['kind'] === 'kauf') {
        require_once __DIR__ . '/invoices.php';
        $was = $GLOBALS['FORMA_TEST'] ?? null;
        if ((int)$v['test'] === 1) $GLOBALS['FORMA_TEST'] = true;
        forma_invoice_for($pdo, 'mv_gutschein', $id);
        $GLOBALS['FORMA_TEST'] = $was;
    }
    if ((int)$v['email_ok'] === 1) mvg_send_voucher($pdo, $id);
    else mvg_send_confirm($pdo, $v);
}

/** Gutschein-Mail mit PDF und Code an die Käuferin (nur mit bestätigter Adresse). */
function mvg_send_voucher(PDO $pdo, int $id, bool $again = false): void
{
    $v = mvg_get($pdo, $id);
    if (!$v || !in_array($v['status'], ['bezahlt', 'eingeloest'], true) || ((int)$v['email_ok'] !== 1 && !$again)) return;
    $en = $v['lang'] === 'en';
    $title = mvg_title($v, $v['lang']);
    $pdf = mvg_pdf($v);
    $file = 'Movecia-Gutschein-' . $v['nr'] . '.pdf';
    $first = forma_first_name((string)$v['buyer_name']) ?: ($en ? 'love' : 'du');
    $atelier = $v['via'] !== 'web';
    $lines = $en ? [
        "Dear $first,",
        $atelier ? 'thank you for your voucher! Here it is as a PDF, ready to print and give.' : 'thank you so much for your voucher, what a lovely gift! It is attached as a PDF, ready to print.',
        "Voucher: $title\nValue: " . mv_eur((int)$v['amount_cents']) . "\nVoucher no.: {$v['nr']}\nCode: {$v['code']}" . ($v['recipient'] !== '' ? "\nFor: {$v['recipient']}" : ''),
        'To redeem it, simply enter the code when booking on moveciamovement.com. The button below opens the right page with the code already filled in.',
        mvg_terms('en'),
        'Your invoice comes in a separate email.',
    ] : [
        "Hallo $first,",
        $atelier ? 'danke für deinen Gutschein! Hier ist er als PDF, fertig zum Ausdrucken und Verschenken.' : 'danke von Herzen für deinen Gutschein, was für ein schönes Geschenk! Du findest ihn im Anhang als PDF, fertig zum Ausdrucken.',
        "Gutschein: $title\nWert: " . mv_eur((int)$v['amount_cents']) . "\nGutschein-Nr.: {$v['nr']}\nCode: {$v['code']}" . ($v['recipient'] !== '' ? "\nFür: {$v['recipient']}" : ''),
        'Eingelöst wird er ganz einfach mit dem Code bei der Buchung auf moveciamovement.com. Der Knopf unten öffnet gleich die passende Seite, der Code ist schon eingetragen.',
        mvg_terms('de'),
        'Die Rechnung bekommst du in einer eigenen E-Mail.',
    ];
    $pfx = (int)$v['test'] === 1 ? '[TEST] ' : '';
    $text = implode("\n\n", $lines) . "\n\n" . ($en ? 'Redeem now' : 'Jetzt einlösen') . ': ' . mvg_target_url($v) . "\n" . ($en ? 'Download the PDF again' : 'PDF erneut herunterladen') . ': ' . mvg_pdf_link($v) . ($en ? "\n\nSee you soon,\nVivien" : "\n\nBis bald,\nVivien");
    $html = '<p style="margin:0 0 6px;font-family:\'Brush Script MT\',cursive;font-size:24px;color:#7D5E68;">' . ($en ? 'a little gift ♡' : 'ein kleines Geschenk ♡') . '</p><h1 style="font-family:Georgia,serif;font-weight:400;font-size:28px;color:#6B5658;margin:0 0 14px;">' . forma_h($title) . '</h1>';
    foreach ($lines as $i => $l) $html .= $i === 2
        ? '<p style="background:#F2ECE4;border-radius:14px;padding:14px 16px;">' . nl2br(forma_h($l)) . '</p>'
        : ($i === 4 ? '<p style="font-size:13px;color:#7D7076;">' . forma_h($l) . '</p>' : '<p>' . forma_h($l) . '</p>');
    $html .= mm_button(mvg_target_url($v), $en ? 'Redeem now' : 'Jetzt einlösen') . '<p style="font-size:14px;"><a href="' . forma_h(mvg_pdf_link($v)) . '" style="color:#7D5E68;">' . ($en ? 'Download the PDF again' : 'PDF erneut herunterladen') . '</a></p><p>' . ($en ? 'See you soon,<br>Vivien' : 'Bis bald,<br>Vivien') . '</p>';
    if (filter_var((string)$v['buyer_email'], FILTER_VALIDATE_EMAIL)) {
        mm_mail((string)$v['buyer_email'], $pfx . ($en ? 'Your Movecia gift voucher ' : 'Dein Movecia Gutschein ') . $v['nr'], $text, mm_mail_html($title, $html, (string)$v['lang']), [[$file, $pdf, 'application/pdf']], 'Vivien · Movecia');
    }
}

/** Info an Vivien gleich nach der Zahlung (mit PDF). */
function mvg_notify_viv(PDO $pdo, array $v): void
{
    $atelier = $v['via'] !== 'web';
    $pfx = (int)$v['test'] === 1 ? '[TEST] ' : '';
    $pdf = mvg_pdf($v);
    $file = 'Movecia-Gutschein-' . $v['nr'] . '.pdf';
    $viv = "Hallo Viv,\n\n" . ($atelier ? 'ein Gutschein aus der Verwaltung ist bezahlt (' . ($v['pay_method'] === 'bar' ? 'bar' : 'online') . ').' : 'gerade wurde ein Movecia-Gutschein online gekauft und bezahlt.') . ((int)$v['email_ok'] === 1 ? '' : ' Die Käuferin bekommt den Gutschein, sobald sie ihre E-Mail-Adresse bestätigt hat.') . "\n\nNr.: {$v['nr']}\nCode: {$v['code']}\nGutschein: " . mvg_title($v) . "\nBetrag: " . mv_eur((int)$v['amount_cents'])
        . "\nDesign: " . MVG_DESIGNS[(int)$v['design']]['de'] . ($v['recipient'] !== '' ? "\nFür: {$v['recipient']}" : '') . ($v['sender'] !== '' ? "\nVon: {$v['sender']}" : '')
        . "\n\nKäuferin: {$v['buyer_name']} ({$v['buyer_email']})\n\nAlle Gutscheine: " . forma_site_url() . "/verwaltung/?view=mvgutscheine\n";
    mm_mail(natal_notify_email(), $pfx . 'Gutschein verkauft: ' . $v['nr'] . ' · ' . mvg_title($v) . ' (' . mv_eur((int)$v['amount_cents']) . ')', $viv, mm_mail_html('Gutschein verkauft', nl2br(forma_h($viv)), 'de'), [[$file, $pdf, 'application/pdf']], 'Movecia');
}

/** Rechnung zum Gutscheinkauf (Movecia-Design). Die Leistung wird später mit dem Gutschein bezahlt. */
function mvg_invoice_source(PDO $pdo, int $id): ?array
{
    mvg_schema($pdo);
    $v = mvg_get($pdo, $id);
    if (!$v || !in_array($v['status'], ['bezahlt', 'eingeloest'], true) || $v['kind'] !== 'kauf' || (int)$v['amount_cents'] <= 0) return null;
    $p = mvg_product($v);
    $detail = ['unfold' => 'Gutschein für Unfold – The Mindful Becoming (3 Monate Online-Begleitung)', 'method' => 'Gutschein für die Movecia Mindful Movement Method (On-Demand-Bibliothek, ' . ((int)($p['months'] ?? 0) === 12 ? '1 Jahr' : (int)($p['months'] ?? 0) . ((int)($p['months'] ?? 0) === 1 ? ' Monat' : ' Monate')) . ')',
        'natal' => 'Gutschein für Prä- & Postnatal Training, ' . ($p['sub']['de'] ?? ''), 'wert' => 'Wertgutschein für Unfold, Method und Prä- & Postnatal Training'][$p['group']] ?? '';
    $paid = date('d.m.Y', strtotime((string)$v['paid_at']));
    return ['name' => $v['buyer_name'], 'email' => $v['buyer_email'], 'session' => $v['pay_method'] === 'bar' ? '' : (string)$v['stripe_session'], 'service' => $paid,
        'items' => [['text' => 'Gutschein ' . $v['nr'] . ' · ' . mvg_title($v), 'detail' => $detail . ', einlösbar auf moveciamovement.com, ohne Ablaufdatum', 'qty' => 1, 'unit' => (int)$v['amount_cents']]],
        'gross' => (int)$v['amount_cents'], 'paid' => $v['paid_at'], 'type' => 'Gutschein',
        'pay_note' => $v['pay_method'] === 'bar'
            ? 'Der Betrag wurde am ' . $paid . ' bar bezahlt. Buchungsvermerk: Leistung von Movecia – Personal Fitness.'
            : 'Der Betrag wurde am ' . $paid . ' online bezahlt (Kartenzahlung über Stripe, gemeinsames Konto FORMA & Movecia). Buchungsvermerk: Leistung von Movecia – Personal Fitness.'];
}

/** Rechnungszeile für einen eingelösten Gutschein (negativ), damit die Summe den bezahlten Betrag ergibt. */
function mvg_invoice_line(PDO $pdo, int $vid, int $cents): array
{
    $v = mvg_get($pdo, $vid) ?: ['nr' => '', 'kind' => 'kauf'];
    return ['text' => ($v['kind'] === 'kauf' ? 'Gutschein ' : 'Gutschrift ') . $v['nr'] . ' eingelöst', 'detail' => $v['kind'] === 'kauf' ? 'bereits beim Gutscheinkauf bezahlt und verrechnet' : 'Preisnachlass', 'qty' => 1, 'unit' => -$cents];
}

// --- PDF zum Ausdrucken ----------------------------------------------------------------------------

function mvg_pdf(array $v): string
{
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
    $t = fn(string $s) => (string)iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
    $en = $v['lang'] === 'en';
    $L = $en ? 'en' : 'de';
    $p = mvg_product($v);
    $wert = $v['product'] === 'wert';
    $big = $wert ? mv_eur((int)$v['amount_cents']) : $p['title'][$L];
    $sub = $wert ? ($en ? 'for everything at Movecia' : 'für alles bei Movecia') : $p['sub'][$L];
    $desc = $p['desc'][$L];
    $design = (int)$v['design'] === 2 ? 2 : 1;
    $plum = [125, 94, 104]; $ink = [46, 37, 41]; $muted = [125, 112, 118]; $line = [221, 210, 198];

    $pdf = new FPDF('L', 'mm', 'A4');
    $fd = __DIR__ . '/vendor/fpdf/font/';
    $pdf->AddFont('C', '', 'Cormorant-Medium.json', $fd);
    $pdf->AddFont('CI', '', 'Cormorant-Italic.json', $fd);
    $pdf->AddFont('I', '', 'Inter-Regular.json', $fd);
    $pdf->AddFont('IB', '', 'Inter-SemiBold.json', $fd);
    $pdf->SetTitle($t('Movecia Gutschein ' . $v['nr']), false);
    $pdf->SetAuthor('Movecia – Vivien Malonga');
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->AddPage();
    $pdf->Image(__DIR__ . "/mv-gutschein/design$design.jpg", 0, 0, 297, 210);

    // gesperrte Zeile (z. B. GUTSCHEIN), links bündig oder mittig
    $spaced = function (string $s, float $x, float $y, float $pt, array $col, float $gap, string $align = 'L', float $w = 0) use ($pdf, $t) {
        $pdf->SetFont('IB', '', $pt); $pdf->SetTextColor(...$col);
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $width = 0; foreach ($chars as $ch) $width += $pdf->GetStringWidth($t($ch)) + $gap; $width -= $gap;
        if ($align === 'C') $x = $x + ($w - $width) / 2;
        foreach ($chars as $ch) { $pdf->Text($x, $y, $t($ch)); $x += $pdf->GetStringWidth($t($ch)) + $gap; }
    };
    $label = $en ? 'GIFT VOUCHER' : 'GUTSCHEIN';
    $who = [];
    if ($v['recipient'] !== '') $who[] = [$en ? 'for' : 'für', (string)$v['recipient']];
    if ($v['sender'] !== '') $who[] = [$en ? 'from' : 'von', (string)$v['sender']];
    $foot = 'Nr. ' . $v['nr'] . '   ·   Code ' . $v['code'];
    $redeem = ($en ? 'Redeem online at ' : 'Einlösen auf ') . 'moveciamovement.com/gutschein';

    if ($design === 1) {
        // Design „Kiesel“: Text rechts in der Spalte ab x = 150 mm
        $x = 150; $w = 124; $y = 56;
        $spaced($label, $x, $y, 8.5, $plum, 1.6);
        $y += 5;
        $pt = mb_strlen($big) > 16 ? 34 : 44;
        $pdf->SetFont('C', '', $pt); $pdf->SetTextColor(...$ink);
        $pdf->SetXY($x - 1, $y); $pdf->MultiCell($w, $pt * .42, $t($big), 0, 'L');
        $y = $pdf->GetY() + 1.5;
        $pdf->SetFont('CI', '', 17); $pdf->SetTextColor(...$plum);
        $pdf->SetXY($x, $y); $pdf->MultiCell($w, 7, $t($sub), 0, 'L');
        $y = $pdf->GetY() + 4;
        $pdf->SetFont('I', '', 8.6); $pdf->SetTextColor(...$muted);
        $pdf->SetXY($x, $y); $pdf->MultiCell(108, 4.5, $t($desc), 0, 'L');
        $y = $pdf->GetY() + 7;
        if ($who) {
            foreach ($who as [$k, $n]) {
                $pdf->SetFont('I', '', 7.6); $pdf->SetTextColor(...$muted); $pdf->SetXY($x, $y + 1.6); $pdf->Cell(12, 5, $t($k));
                $pdf->SetFont('C', '', 16); $pdf->SetTextColor(...$ink); $pdf->SetXY($x + 12, $y); $pdf->Cell($w - 12, 7, $t($n));
                $pdf->SetDrawColor(...$line); $pdf->SetLineWidth(.25); $pdf->Line($x + 12, $y + 7.6, $x + 100, $y + 7.6);
                $y += 11;
            }
            $y += 1;
        }
        if ($v['message'] !== '') {
            $pdf->SetFont('CI', '', 13.5); $pdf->SetTextColor(...$plum);
            $pdf->SetXY($x, $y); $pdf->MultiCell(112, 5.8, $t(($en ? '“' : '„') . $v['message'] . '“'), 0, 'L');
        }
        $pdf->SetFont('I', '', 7.4); $pdf->SetTextColor(...$muted);
        $pdf->SetXY($x, 178); $pdf->Cell($w, 4, $t($foot));
        $pdf->SetXY($x, 182.5); $pdf->Cell($w, 4, $t($redeem));
    } else {
        // Design „Welle“: alles mittig, Bewegungslinie unten
        $x = 38.5; $w = 220; $y = 62;
        $spaced($label, $x, $y, 8.5, $plum, 1.6, 'C', $w);
        $y += 5;
        $pt = mb_strlen($big) > 18 ? 34 : 44;
        $pdf->SetFont('C', '', $pt); $pdf->SetTextColor(...$ink);
        $pdf->SetXY($x, $y); $pdf->MultiCell($w, $pt * .42, $t($big), 0, 'C');
        $y = $pdf->GetY() + 1.5;
        $pdf->SetFont('CI', '', 17); $pdf->SetTextColor(...$plum);
        $pdf->SetXY($x, $y); $pdf->MultiCell($w, 7, $t($sub), 0, 'C');
        $y = $pdf->GetY() + 3.5;
        $pdf->SetFont('I', '', 8.6); $pdf->SetTextColor(...$muted);
        $pdf->SetXY(78.5, $y); $pdf->MultiCell(140, 4.5, $t($desc), 0, 'C');
        $y = $pdf->GetY() + 6;
        if ($who) {
            $s = implode('   ·   ', array_map(fn($a) => $a[0] . ' ' . $a[1], $who));
            $pdf->SetFont('C', '', 16); $pdf->SetTextColor(...$ink);
            $pdf->SetXY($x, $y); $pdf->MultiCell($w, 7, $t($s), 0, 'C');
            $y = $pdf->GetY() + 2;
        }
        if ($v['message'] !== '' && $y < 140) {
            $pdf->SetFont('CI', '', 13.5); $pdf->SetTextColor(...$plum);
            $pdf->SetXY(68.5, $y); $pdf->MultiCell(160, 5.8, $t(($en ? '“' : '„') . $v['message'] . '“'), 0, 'C');
        }
        $pdf->SetFont('I', '', 7.4); $pdf->SetTextColor(...$muted);
        $pdf->SetXY($x, 186); $pdf->Cell($w, 4, $t($foot . '   ·   ' . $redeem), 0, 0, 'C');
    }

    // Seite 2: So löst du deinen Gutschein ein
    $pdf->AddPage();
    $mark = dirname(__DIR__) . '/img/movecia/movecia-zeichen-1024.png';
    if (is_file($mark)) $pdf->Image($mark, 140, 18, 17);
    $pdf->SetFont('C', '', 27); $pdf->SetTextColor(...$ink);
    $pdf->SetXY(20, 40); $pdf->Cell(257, 12, $t($en ? 'How to redeem your voucher' : 'So löst du deinen Gutschein ein'), 0, 2, 'C');
    $pdf->SetDrawColor(...$plum); $pdf->SetLineWidth(.4);
    $pdf->Rect(88.5, 58, 120, 24, 'D');
    $pdf->SetFont('I', '', 8.6); $pdf->SetTextColor(...$muted);
    $pdf->SetXY(88.5, 61); $pdf->Cell(120, 5, $t($en ? 'Your voucher code' : 'Dein Gutscheincode'), 0, 2, 'C');
    $pdf->SetFont('IB', '', 17); $pdf->SetTextColor(...$plum);
    $pdf->SetXY(88.5, 67.5); $pdf->Cell(120, 10, $t((string)$v['code']), 0, 2, 'C');
    $where = ['unfold' => 'moveciamovement.com/unfold', 'method' => 'moveciamovement.com/method', 'natal' => 'moveciamovement.com/natal', 'wert' => 'moveciamovement.com/gutschein'][$p['group']];
    $steps = $en ? [
        '1.  Open ' . $where . ($p['group'] === 'wert' ? ' and choose what you would like: Unfold, the Method or pre- & postnatal training.' : ' and start your booking.'),
        '2.  Enter your code under “Do you have a voucher?”.',
        ['method' => '3.  Your months of the Method unlock right away, without a subscription. They simply end.', 'unfold' => '3.  Your place in Unfold is fully paid, and you get your confirmation straight away.', 'natal' => '3.  Your package is fully paid. We arrange the dates together afterwards.'][$p['group']] ?? '3.  The voucher is deducted straight away. If everything is covered, your booking is confirmed immediately.',
        'Questions? Write to me: hello@moveciamovement.com',
    ] : [
        '1.  Öffne ' . $where . ($p['group'] === 'wert' ? ' und such dir aus, was du möchtest: Unfold, die Method oder das Prä- & Postnatal Training.' : ' und starte deine Buchung.'),
        '2.  Gib bei „Du hast einen Gutschein?“ deinen Code ein.',
        ['method' => '3.  Deine Monate Method sind sofort freigeschaltet, ohne Abo. Sie enden von selbst.', 'unfold' => '3.  Dein Platz in Unfold ist damit ganz bezahlt, du bekommst gleich deine Bestätigung.', 'natal' => '3.  Dein Paket ist damit ganz bezahlt. Die Termine stimmen wir danach gemeinsam ab.'][$p['group']] ?? '3.  Der Gutschein wird sofort abgezogen. Ist alles gedeckt, ist deine Buchung gleich fix.',
        'Fragen? Schreib mir: hello@moveciamovement.com',
    ];
    $pdf->SetFont('I', '', 10.5); $pdf->SetTextColor(...$ink);
    $y = 94;
    foreach ($steps as $s) { $pdf->SetXY(58, $y); $pdf->MultiCell(181, 5.6, $t($s), 0, 'L'); $y = $pdf->GetY() + 3; }
    $pdf->SetFont('IB', '', 9); $pdf->SetTextColor(...$plum);
    $pdf->SetXY(58, $y + 5); $pdf->Cell(181, 5, $t($en ? 'Good to know' : 'Gut zu wissen'));
    $pdf->SetFont('I', '', 8.4); $pdf->SetTextColor(...$muted);
    $pdf->SetXY(58, $y + 11); $pdf->MultiCell(181, 4.4, $t(mvg_terms($L) . ' ' . ($en ? 'Voucher no. ' : 'Gutschein-Nr. ') . $v['nr'] . ($v['paid_at'] ? ($en ? ', issued on ' : ', ausgestellt am ') . date('j. n. Y', strtotime((string)$v['paid_at'])) : '') . '.'), 0, 'L');
    $pdf->SetFont('I', '', 7.6);
    $pdf->SetXY(20, 194); $pdf->Cell(257, 5, $t('Movecia – Personal Fitness · Vivien Malonga · hello@moveciamovement.com · moveciamovement.com'), 0, 0, 'C');
    return $pdf->Output('S');
}

// --- Einlösen bei Unfold & Method --------------------------------------------------------------------

/**
 * Method-Gutschein: Mitgliedschaft für die Monate des Gutscheins, ohne Abo und ohne Zahlung.
 * Startet mit dem Start der Method (vorher Vorab-Buchung) bzw. sofort. Gibt die Buchung zurück.
 */
function mvg_method_gift(PDO $pdo, array $v, int $use, array $in): array
{
    $p = mvg_product($v);
    $lang = ($in['lang'] ?? 'de') === 'en' ? 'en' : 'de';
    $start = max(mv_launch(), time());
    $end = mv_month_at($start, (int)$p['months']);
    $tok = bin2hex(random_bytes(16));
    $digital = ($in['wr'] ?? '') === 'verzicht' ? 1 : 0;
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare("INSERT INTO mv_subs (kind, plan, email, name, lang, amount_cents, first_cents, token, consent_digital, consent_live, source, test, created_at, paid_at, status, period_end, bill_start, voucher_id, voucher_cents) VALUES ('method',?,?,?,?,0,0,?,?,1,'gutschein',?,?,?,'aktiv',?,?,?,?)")
            ->execute([$p['plan'], strtolower(trim((string)$in['email'])), forma_clean((string)$in['name'], 80), $lang, $tok, $digital, (int)$v['test'], date('c'), date('c'), $end, $start, $v['id'], $use]);
        $sid = (int)$pdo->lastInsertId();
        mvg_use($pdo, (int)$v['id'], 'mv', $sid, $use, true, 'Method ' . $p['months'] . ' Mon.');
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
        throw $e;
    }
    $r = mv_sub($pdo, $sid);
    mv_after_paid($pdo, $r);
    return $r;
}

/** Unfold ganz mit Gutschein bezahlt: Platz gleich fix, ohne Stripe. */
function mvg_unfold_full(PDO $pdo, array $v, int $use, int $price, array $in): array
{
    $lang = ($in['lang'] ?? 'de') === 'en' ? 'en' : 'de';
    $callLang = in_array($in['call_lang'] ?? '', ['de', 'en'], true) ? $in['call_lang'] : $lang;
    $tok = bin2hex(random_bytes(16));
    $digital = ($in['wr'] ?? '') === 'verzicht' ? 1 : 0;
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        if (mv_unfold_free($pdo) <= 0) throw new RuntimeException($lang === 'en' ? 'Unfold is fully booked. Your voucher stays valid for the next round, simply write to me.' : 'Unfold ist ausgebucht. Dein Gutschein bleibt für die nächste Runde gültig, schreib mir einfach.');
        $pdo->prepare("INSERT INTO mv_subs (kind, plan, email, name, lang, call_lang, amount_cents, first_cents, token, consent_digital, consent_live, source, test, created_at, paid_at, status, voucher_id, voucher_cents) VALUES ('unfold','u1',?,?,?,?,?,0,?,?,1,'gutschein',?,?,?,'aktiv',?,?)")
            ->execute([strtolower(trim((string)$in['email'])), forma_clean((string)$in['name'], 80), $lang, $callLang, $price, $tok, $digital, (int)$v['test'], date('c'), date('c'), $v['id'], $price]);
        $sid = (int)$pdo->lastInsertId();
        mvg_use($pdo, (int)$v['id'], 'mv', $sid, $use, true, 'Unfold');
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
        throw $e;
    }
    $r = mv_sub($pdo, $sid);
    mv_after_paid($pdo, $r);
    return $r;
}

/** Zeile für Mails: „mit deinem Gutschein MOV-2026-0001“ */
function mvg_paid_with(PDO $pdo, array $row, string $lang = 'de'): string
{
    $v = !empty($row['voucher_id']) ? mvg_get($pdo, (int)$row['voucher_id']) : null;
    if (!$v) return '';
    return $lang === 'en' ? 'with your voucher ' . $v['nr'] : 'mit deinem Gutschein ' . $v['nr'];
}

// --- Verwaltung ------------------------------------------------------------------------------------

/** Status für die Liste: offen (Betrag offen), bezahlt, teilweise eingelöst, eingelöst, zurückgezogen. */
function mvg_state(PDO $pdo, array $v): array
{
    if ($v['status'] === 'offen') return ['offen', 'Betrag offen', 'wait'];
    if ($v['status'] === 'zurueckgezogen') return ['zurueckgezogen', 'zurückgezogen', 'off'];
    if ($v['status'] === 'abgebrochen') return ['abgebrochen', 'Kauf abgebrochen', 'off'];
    $bal = mvg_balance($pdo, $v);
    if ($v['status'] === 'eingeloest' || $bal <= 0) return ['eingeloest', 'eingelöst', 'done'];
    if ($bal < (int)$v['amount_cents']) return ['teil', 'Rest ' . mv_eur($bal), 'ok'];
    return ['bezahlt', 'bezahlt, noch nicht eingelöst', 'ok'];
}

/** Wo wurde der Gutschein eingelöst? */
function mvg_uses(PDO $pdo, int $vid): array
{
    $q = $pdo->prepare("SELECT * FROM mv_voucher_uses WHERE voucher_id = ? AND status IN ('reserviert','eingeloest') ORDER BY id");
    $q->execute([$vid]);
    $out = [];
    foreach ($q->fetchAll() as $u) {
        $what = $u['note'] !== '' ? $u['note'] : 'von Hand';
        if ($u['ref_kind'] === 'natal') { $o = natal_order($pdo, (int)$u['ref_id']); $what = 'Prä-/Postnatal ' . ($o['title'] ?? '') . ' · ' . ($o['name'] ?? ''); }
        if ($u['ref_kind'] === 'mv') { $s = mv_sub($pdo, (int)$u['ref_id']); $what = ($s && $s['kind'] === 'unfold' ? 'Unfold' : 'Method') . ' · ' . ($s['name'] ?? ''); }
        $out[] = ['when' => date('d.m.Y', strtotime((string)$u['created_at'])), 'what' => $what, 'cents' => (int)$u['cents'], 'status' => $u['status']];
    }
    return $out;
}

/** Offene Online-Zahlungen nachprüfen (gedrosselt) und Reservierungen aufräumen. */
function mvg_tick(PDO $pdo, bool $force = false): void
{
    mvg_schema($pdo);
    $f = forma_data_dir() . '/movecia-gutschein-tick';
    if (!$force && is_file($f) && (int)file_get_contents($f) > time() - 300) return;
    @file_put_contents($f, (string)time());
    mvg_sweep($pdo);
    $r = $pdo->prepare("SELECT * FROM mv_vouchers WHERE status IN ('bezahlt','eingeloest') AND email_ok = 0 AND confirm_sent > 0 AND confirm_sent < ? AND confirm_reminded = 0 LIMIT 10");
    $r->execute([time() - 2 * 86400]);
    foreach ($r->fetchAll() as $v) { try { mvg_send_confirm($pdo, $v, true); } catch (Throwable $e) { error_log('MVG reminder: ' . $e->getMessage()); } }
    if (!forma_stripe_key()) return;
    $q = $pdo->prepare("SELECT * FROM mv_vouchers WHERE status = 'offen' AND stripe_session <> '' AND created_at >= ? ORDER BY id LIMIT 15");
    $q->execute([date('c', time() - 30 * 86400)]);
    foreach ($q->fetchAll() as $v) { try { mvg_check($pdo, $v); } catch (Throwable $e) { error_log('MVG tick: ' . $e->getMessage()); } }
}

/** Fertige Nachricht mit Bezahl-Link (für WhatsApp oder zum Kopieren). */
function mvg_pay_message(array $v): string
{
    $first = forma_first_name((string)$v['buyer_name']);
    return 'Hallo' . ($first !== '' ? ' ' . $first : '') . "! 🤍\n\nHier ist der Link für deinen Movecia Gutschein (" . mvg_title($v) . ', ' . mv_eur((int)$v['amount_cents']) . "):\n\n" . mvg_pay_link($v)
        . "\n\nSobald bezahlt ist, bekommst du den Gutschein als PDF zum Ausdrucken und die Rechnung per E-Mail.\n\nBis bald,\nVivien";
}

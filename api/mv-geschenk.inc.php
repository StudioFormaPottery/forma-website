<?php
// Movecia-Gutscheine verschenken (Vivien, 31.10.2026: „gerade jetzt vor Weihnachten ist das wichtig“)
// Seite moveciamovement.com/gutscheine → mv_gift_checkout (Stripe) → mv_gift_check (nach der Zahlung genau einmal):
// Wertgutschein in mm_vouchers (origin „kauf“, mit Für/Von/Nachricht und „gedacht für“), PDF per Mail an die Käuferin, Info an Vivien.
// Mehrzweckgutschein: keine Rechnung beim Kauf, Umsatzsteuer erst bei der Einlösung (wie bei FORMA).
declare(strict_types=1);
require_once __DIR__ . '/ptb-lib.php';
require_once __DIR__ . '/method-lib.php';

const MV_GIFT_MIN = 2000;
const MV_GIFT_MAX = 100000;

/** Angebote zum Verschenken: key => [Titel, Untertitel, Bild, [Variante => [Label, Cent]]]. Preise immer aus den echten Angeboten. */
function mv_gift_offers(): array
{
    $mp = mv_plans();
    $np = natal_packages();
    $pt = ptb_price_table(1);
    $ptOn = (int)$np['o_einzel']['unit_cents']; // Personal Training online = Preis wie 1:1 Prä-/Postnatal online (Vivien, 31.10.2026)
    return [
        'unfold' => ['Unfold', 'The Mindful Becoming · 3 Monate Begleitung mit Live-Calls, Bewegung und Workbooks', 'ev-unfold-karte.jpg',
            ['u1' => ['3 Monate', (int)$mp['u1']['cents']]]],
        'method' => ['The Method', 'Mindful Movement on demand · Flows für jeden Tag, wann immer sie Zeit hat', 'ev-method-karte.jpg',
            ['m1' => ['1 Monat', (int)$mp['m1']['cents']], 'm3' => ['3 Monate', (int)$mp['m3']['cents']], 'm12' => ['1 Jahr', (int)$mp['m12']['cents']]]],
        'pt_online' => ['Personal Training online', '1:1 live per Video, ganz auf sie abgestimmt, von zu Hause aus', 'ev-gathering.jpg',
            array_combine(['o1', 'o2', 'o3', 'o4'], array_map(fn($n) => [$n === 1 ? '1 Einheit' : $n . ' Einheiten', $ptOn * $n], [1, 2, 3, 4]))],
        'pt_studio' => ['Personal Training im Studio', '1:1 in meinem kleinen Studio in Mathon, ' . PTB_MINUTES . ' Minuten nur für sie', 'pt-baum.jpg',
            array_combine(['s1', 's2', 's3', 's4'], array_map(fn($n) => [$n === 1 ? '1 Einheit' : $n . ' Einheiten', (int)$pt[$n]['total']], [1, 2, 3, 4]))],
        'natal' => ['Prä- & Postnatal', '1:1 durch Schwangerschaft und Rückbildung, im Studio oder online', 'ev-post-baby.jpg',
            ['einzel' => ['Einzelstunde', (int)$np['einzel']['unit_cents']], 'prae' => ['Pränatal · 6 Einheiten', (int)$np['prae']['unit_cents'] * 6],
             'post' => ['Postnatal · 8 Einheiten', (int)$np['post']['unit_cents'] * 8], 'kombi' => ['Kombi · 14 Einheiten', (int)$np['kombi']['unit_cents'] * 14]]],
    ];
}

/** Wo und wie man den Gutschein einlöst, je nach Angebot (für Mail und Danke-Seite). */
function mv_gift_howto(string $offer): string
{
    return match ($offer) {
        'method' => 'Einlösen: auf moveciamovement.com/method die Mitgliedschaft wählen und den Code ins Feld „Hast du einen Gutschein?“ eingeben.',
        'unfold' => 'Einlösen: auf moveciamovement.com/unfold buchen und den Code ins Feld „Hast du einen Gutschein?“ eingeben.',
        'natal' => 'Einlösen: auf moveciamovement.com/natal das Paket wählen und den Code bei der Buchung eingeben.',
        'pt_online', 'pt_studio' => 'Einlösen: einfach Vivien schreiben (hello@moveciamovement.com), dann macht ihr die Termine gemeinsam aus.',
        default => 'Einlösen: beim Buchen auf moveciamovement.com den Code eingeben oder einfach Vivien schreiben.',
    };
}

function mv_gift_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    natal_wd_schema($pdo);
    $vc = array_column($pdo->query('PRAGMA table_info(mm_vouchers)')->fetchAll(), 'name');
    if (!in_array('label', $vc, true)) $pdo->exec("ALTER TABLE mm_vouchers ADD COLUMN label TEXT NOT NULL DEFAULT ''");
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_gift_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        offer TEXT NOT NULL,
        variant TEXT NOT NULL DEFAULT \'\',
        label TEXT NOT NULL,
        cents INTEGER NOT NULL,
        design INTEGER NOT NULL DEFAULT 1,
        recipient TEXT NOT NULL DEFAULT \'\',
        sender TEXT NOT NULL DEFAULT \'\',
        message TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        token TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'offen\',
        stripe_session TEXT NOT NULL DEFAULT \'\',
        voucher_id INTEGER NOT NULL DEFAULT 0,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        test INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        paid_at TEXT
    )');
}

function mv_gift_order(PDO $pdo, int $id): ?array
{
    mv_gift_schema($pdo);
    $q = $pdo->prepare('SELECT * FROM mm_gift_orders WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

/** Kauf anlegen und Stripe-Bezahlseite zurückgeben. Preis und Bezeichnung bestimmt der Server. */
function mv_gift_checkout(PDO $pdo, array $in): string
{
    mv_gift_schema($pdo);
    $offers = mv_gift_offers();
    $offer = (string)($in['offer'] ?? '');
    $variant = (string)($in['variant'] ?? '');
    if ($offer === 'wert') {
        $cents = (int)round((float)str_replace(',', '.', (string)($in['amount'] ?? '0')) * 100);
        if ($cents < MV_GIFT_MIN || $cents > MV_GIFT_MAX) throw new FormaUserError('Bitte wähl einen Betrag zwischen € 20 und € 1.000.');
        $cents = (int)(round($cents / 100) * 100);
        $label = 'Wertgutschein';
        $variant = '';
    } else {
        if (!isset($offers[$offer][3][$variant])) throw new FormaUserError('Bitte wähl ein Angebot aus.');
        [$vl, $cents] = $offers[$offer][3][$variant];
        $label = $offers[$offer][0] . ' · ' . $vl;
    }
    if ($cents <= 0) throw new FormaUserError('Dieses Angebot lässt sich gerade nicht verschenken.');
    $name = forma_clean((string)($in['name'] ?? ''), 80);
    $email = strtolower(trim((string)($in['email'] ?? '')));
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new FormaUserError('Bitte gib deinen Namen und deine E-Mail-Adresse an, dorthin kommt der Gutschein.');
    if (empty($in['terms'])) throw new FormaUserError('Bitte bestätige kurz die Bedingungen.');
    $design = (int)($in['design'] ?? 1) === 2 ? 2 : 1;
    $to = forma_clean((string)($in['to'] ?? ''), 40);
    $from = forma_clean((string)($in['from'] ?? ''), 40);
    $msg = trim(preg_replace('/\s+/u', ' ', (string)($in['msg'] ?? '')) ?? '');
    $msg = mb_substr(strip_tags($msg), 0, 160);
    // Bremse: höchstens 8 offene Käufe pro Stunde und Anschluss
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'mv-gift');
    $st = $pdo->prepare('SELECT COUNT(*) FROM mm_gift_orders WHERE ip_hash = ? AND created_at > ?');
    $st->execute([$ip, date('c', time() - 3600)]);
    if ((int)$st->fetchColumn() >= 8) throw new FormaUserError('Zu viele Versuche. Bitte probier es in einer Stunde noch einmal oder schreib mir.');
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO mm_gift_orders (offer, variant, label, cents, design, recipient, sender, message, name, email, token, ip_hash, test, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$offer, $variant, $label, $cents, $design, $to, $from, $msg, $name, $email, $tok, $ip, forma_stripe_test_mode() ? 1 : 0, date('c')]);
    $id = (int)$pdo->lastInsertId();
    $site = mm_site();
    $params = [
        'mode' => 'payment',
        'locale' => 'de',
        'customer_email' => $email,
        'client_reference_id' => 'mvgift-' . $id,
        'metadata' => ['mv_gift' => (string)$id],
        'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $cents,
            'product_data' => ['name' => 'Movecia Gutschein · ' . $label, 'description' => 'Wertgutschein ohne Ablaufdatum, einlösbar für alles bei Movecia. Sofort als PDF.']]]],
        'payment_intent_data' => ['statement_descriptor_suffix' => 'MOVECIA', 'description' => 'Movecia Gutschein · ' . $label, 'metadata' => ['mv_gift' => (string)$id]],
        'custom_text' => ['submit' => ['message' => 'Der Gutschein kommt sofort nach der Zahlung als PDF an ' . $email . '. Bedingungen: ' . $site . '/terms#gutscheine']],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/mv-gutschein-kauf.php?paid=' . $id . '&k=' . $tok,
        'cancel_url' => $site . '/gutscheine/?abbruch=1',
        'branding_settings' => ['display_name' => 'Movecia – Personal Fitness'],
    ];
    try {
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params, null, '2025-09-30.clover');
    } catch (Throwable $e) {
        unset($params['branding_settings']);
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params);
    }
    $pdo->prepare('UPDATE mm_gift_orders SET stripe_session = ? WHERE id = ?')->execute([(string)$s['id'], $id]);
    return (string)$s['url'];
}

/** Prüft bei Stripe. Bei Zahlung genau einmal: Gutschein anlegen, Mails schicken. Gibt den Status zurück. */
function mv_gift_check(PDO $pdo, array $g): string
{
    if ($g['status'] !== 'offen' || $g['stripe_session'] === '') return (string)$g['status'];
    $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($g['stripe_session']));
    if (($s['status'] ?? '') === 'expired') { $pdo->prepare("UPDATE mm_gift_orders SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([$g['id']]); return 'abgebrochen'; }
    if (($s['payment_status'] ?? '') !== 'paid') return 'offen';
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $u = $pdo->prepare("UPDATE mm_gift_orders SET status = 'bezahlt', paid_at = ? WHERE id = ? AND status = 'offen'");
        $u->execute([date('c'), $g['id']]);
        if ($u->rowCount() !== 1) { $pdo->exec('ROLLBACK'); return 'bezahlt'; }
        $v = mv_voucher_create($pdo, (int)$g['cents'], (int)$g['design'], ['id' => 0, 'name' => $g['name'], 'email' => $g['email'], 'is_test' => (int)$g['test']], 0, '', 'kauf',
            'gekauft von ' . $g['name'] . ' (' . $g['label'] . ')' . ($g['recipient'] !== '' ? ', für ' . $g['recipient'] : ''));
        $pdo->prepare('UPDATE mm_vouchers SET recipient = ?, sender = ?, message = ?, label = ? WHERE id = ?')->execute([$g['recipient'], $g['sender'], $g['message'], $g['offer'] === 'wert' ? '' : $g['label'], $v['id']]);
        $pdo->prepare('UPDATE mm_gift_orders SET voucher_id = ? WHERE id = ?')->execute([$v['id'], $g['id']]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $e2) {}
        error_log('MV Gutschein-Kauf: ' . $e->getMessage());
        return 'offen';
    }
    $g = mv_gift_order($pdo, (int)$g['id']);
    $v = $pdo->query('SELECT * FROM mm_vouchers WHERE id = ' . (int)$g['voucher_id'])->fetch();
    try { mv_gift_mails($pdo, $g, $v); } catch (Throwable $e) { error_log('MV Gutschein-Mail: ' . $e->getMessage()); }
    return 'bezahlt';
}

/** Gedrosselt bei Seitenaufrufen: offene Käufe nachprüfen (falls jemand das Fenster nach der Zahlung schließt). */
function mv_gift_tick(PDO $pdo): void
{
    mv_gift_schema($pdo);
    $f = forma_data_dir() . '/movecia-gift-tick';
    if (is_file($f) && (int)file_get_contents($f) > time() - 300) return;
    @file_put_contents($f, (string)time());
    if (!forma_stripe_key()) return;
    $q = $pdo->prepare("SELECT * FROM mm_gift_orders WHERE status = 'offen' AND stripe_session <> '' AND created_at >= ? ORDER BY id LIMIT 10");
    $q->execute([date('c', time() - 2 * 86400)]);
    foreach ($q->fetchAll() as $g) { try { mv_gift_check($pdo, $g); } catch (Throwable $e) { error_log('MV gift tick: ' . $e->getMessage()); } }
}

function mv_gift_mails(PDO $pdo, array $g, array $v): void
{
    $first = forma_first_name((string)$g['name']);
    $pdf = mv_voucher_pdf($v);
    $att = [['Movecia_Gutschein_' . $v['nr'] . '.pdf', $pdf, 'application/pdf']];
    $p = ["Hallo $first,", 'danke von Herzen, dass du jemandem Zeit für sich schenkst. Dein Gutschein hängt als PDF an dieser E-Mail, zum Ausdrucken oder Weiterschicken.',
        ['Gutschein ' . $v['nr'] . ' · ' . forma_euro((int)$v['amount_cents']), 'Gedacht für: ' . $g['label'], 'Code: ' . $v['code']]
            + ($g['recipient'] !== '' ? [3 => 'Für: ' . $g['recipient']] : []),
        mv_gift_howto((string)$g['offer']),
        'Der Gutschein hat kein Ablaufdatum und gilt für alles bei Movecia: The Method, Unfold, Prä- & Postnatal und Personal Training. Ein Restwert bleibt erhalten. Bitte heb den Code gut auf, wer ihn kennt, kann ihn einlösen.',
        'Rücktritt: Solange der Gutschein noch nicht eingelöst ist, kannst du innerhalb von 14 Tagen ab dem Kauf ohne Angabe von Gründen zurücktreten und bekommst den vollen Betrag zurück. Schreib mir dafür einfach oder nutze ' . mm_site() . '/withdraw?v=gutschein&lang=de.'];
    natal_mail((string)$g['email'], 'Dein Movecia-Gutschein ' . $v['nr'], 'Dein Gutschein ist da', $p, [mv_voucher_pdf_link($v), 'Gutschein als PDF öffnen'], $att);
    $vv = ['Neuer Gutschein verkauft' . ((int)$g['test'] ? ' (TEST)' : '') . ':', [
        $g['name'] . ' · ' . $g['email'],
        $g['label'] . ' · ' . forma_euro((int)$g['cents']) . ' · ' . $v['nr'],
        ($g['recipient'] !== '' ? 'Für ' . $g['recipient'] : 'ohne Namen') . ($g['sender'] !== '' ? ' · von ' . $g['sender'] : ''),
    ], 'Es gibt keine Rechnung beim Kauf (Mehrzweckgutschein), sie entsteht erst beim Einlösen. Online (Method, Unfold, Prä-/Postnatal) passiert das automatisch, Personal Training löst du in der Verwaltung → Prä- & Postnatal → Gutscheine „Von Hand einlösen“ ein.'];
    natal_mail(natal_notify_email(), 'Gutschein verkauft: ' . $g['label'] . ' · ' . forma_euro((int)$g['cents']), 'Gutschein verkauft', $vv, [natal_admin_link() . '#gutscheine', 'In der Verwaltung öffnen'], $att);
}

/** Rücktritt vom Gutscheinkauf (nur solange nichts eingelöst ist): Stripe-Erstattung, Gutschein gesperrt. */
function mv_gift_refund(PDO $pdo, int $vid): array
{
    mv_gift_schema($pdo);
    $q = $pdo->prepare("SELECT * FROM mm_gift_orders WHERE voucher_id = ? AND status = 'bezahlt'");
    $q->execute([$vid]);
    $g = $q->fetch();
    $v = $pdo->query('SELECT * FROM mm_vouchers WHERE id = ' . $vid)->fetch();
    if (!$g || !$v) return ['ok' => false, 'error' => 'Zu diesem Gutschein gibt es keinen Online-Kauf.'];
    $used = $pdo->query("SELECT COUNT(*) FROM mm_voucher_uses WHERE voucher_id = $vid AND status IN ('reserviert','eingeloest')")->fetchColumn();
    if ((int)$used > 0) return ['ok' => false, 'error' => 'Der Gutschein ist schon (teilweise) eingelöst, deshalb geht der Rücktritt nicht mehr.'];
    // zuerst sperren, damit er in der Zwischenzeit nicht eingelöst werden kann
    $pdo->prepare("UPDATE mm_vouchers SET status = 'storniert', note = TRIM(note || ' · Rücktritt, erstattet') WHERE id = ? AND status IN ('aktiv','test')")->execute([$vid]);
    try {
        $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$g['stripe_session']));
        $pi = is_array($s['payment_intent'] ?? null) ? ($s['payment_intent']['id'] ?? '') : (string)($s['payment_intent'] ?? '');
        if ($pi === '') throw new RuntimeException('keine Zahlung gefunden');
        $rid = '';
        $list = forma_stripe('GET', '/v1/refunds?payment_intent=' . rawurlencode($pi) . '&limit=10');
        foreach ((array)($list['data'] ?? []) as $r) if (($r['status'] ?? '') !== 'failed') $rid = (string)$r['id'];
        if ($rid === '') $rid = (string)(forma_stripe('POST', '/v1/refunds', ['payment_intent' => $pi, 'reason' => 'requested_by_customer', 'metadata' => ['mv_gift' => (string)$g['id']]])['id'] ?? '');
    } catch (Throwable $e) {
        error_log('MV gift refund: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Der Gutschein ist gesperrt, aber die Erstattung über Stripe ging nicht. Bitte in Stripe von Hand erstatten.'];
    }
    $pdo->prepare("UPDATE mm_gift_orders SET status = 'erstattet' WHERE id = ?")->execute([$g['id']]);
    natal_mail((string)$g['email'], 'Dein Rücktritt vom Gutschein ' . $v['nr'], 'Rücktritt bestätigt', ['Hallo ' . forma_first_name((string)$g['name']) . ',',
        'dein Rücktritt vom Gutschein ' . $v['nr'] . ' (' . forma_euro((int)$g['cents']) . ') ist erledigt. Der Betrag ist auf dem Weg zurück auf das Zahlungsmittel, mit dem du bezahlt hast. Je nach Bank dauert es ein paar Tage. Der Gutschein-Code gilt ab jetzt nicht mehr.']);
    return ['ok' => true, 'cents' => (int)$g['cents']];
}

<?php
// Prä- & Postnatal · Start bestätigen, Rücktritt mit Rückerstattung oder Gutschein, Movecia-Gutscheine (Vivien, 31.10.2026)
//
// 1. Start bestätigen: Die Buchungsmail führt zu /natal/start/?o=ID&k=TOKEN. Pflicht-Häkchen: Einheiten dürfen in der
//    Rücktrittsfrist beginnen, bei Rücktritt werden genutzte Einheiten zum Paketpreis abgezogen (§ 10, § 16 FAGG).
//    Freiwilliges Häkchen: sofort voller Mitgliederbereich, dafür erlischt das Rücktrittsrecht für die digitalen Inhalte
//    (§ 18 Abs. 1 Z 11 FAGG). Gespeichert in mm_natal_orders.confirm_at / consent_digital / digital_at.
// 2. Rücktritt im Mitgliederbereich (unten) oder durch Vivien in der Verwaltung: Betrag = Gesamtwert minus stattgefundene
//    Einheiten (Paketpreis je Art). Wahl: Rückerstattung über Stripe oder Movecia-Gutschein (1 oder 2 Stück, 50/50,
//    Design wählbar, PDF). Pro Buchung genau ein Rücktritt (UNIQUE order_id), Erstattung und Gutschein schließen sich aus.
// 3. Movecia-Gutscheine (Tabelle mm_vouchers, Code MOVECIA-XXXX-XXXX-XXXX, Nummer MV-JJJJ-001) sind bei jeder
//    Prä-/Postnatal-Buchung einlösbar, Restwert bleibt.
declare(strict_types=1);

const NATAL_WD_DAYS = 14;
const MV_VOUCHER_TERMS = 'Wertgutschein ohne Ablaufdatum, einlösbar für alle Pakete und Einzelstunden des Prä- & Postnatal Trainings bei Movecia, im Studio oder online. Einfach bei der Buchung auf moveciamovement.com/natal den Gutscheincode eingeben. Ein Restwert bleibt erhalten. Keine Barauszahlung. Wer den Code kennt, kann ihn einlösen, bitte gut aufbewahren.';
const MV_VOUCHER_DESIGNS = [1 => 'Bogen', 2 => 'Zart'];

function natal_wd_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $oc = array_column($pdo->query('PRAGMA table_info(mm_natal_orders)')->fetchAll(), 'name');
    foreach (['confirm_at' => 'TEXT', 'consent_digital' => 'INTEGER NOT NULL DEFAULT 0', 'digital_at' => 'TEXT', 'voucher_id' => 'INTEGER', 'voucher_cents' => 'INTEGER NOT NULL DEFAULT 0', 'withdrawn_at' => 'TEXT'] as $c => $t) {
        if ($oc && !in_array($c, $oc, true)) $pdo->exec("ALTER TABLE mm_natal_orders ADD COLUMN $c $t");
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_natal_wd (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL UNIQUE,
        choice TEXT NOT NULL,
        total_cents INTEGER NOT NULL,
        used_cents INTEGER NOT NULL,
        used_text TEXT NOT NULL DEFAULT \'\',
        back_cents INTEGER NOT NULL,
        refund_cents INTEGER NOT NULL DEFAULT 0,
        voucher_cents INTEGER NOT NULL DEFAULT 0,
        refund_id TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'offen\',
        source TEXT NOT NULL DEFAULT \'mitglied\',
        note TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        done_at TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_vouchers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nr TEXT NOT NULL UNIQUE,
        code TEXT NOT NULL UNIQUE,
        amount_cents INTEGER NOT NULL,
        design INTEGER NOT NULL DEFAULT 1,
        origin TEXT NOT NULL DEFAULT \'ruecktritt\',
        order_id INTEGER,
        wd_id INTEGER,
        part TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL DEFAULT \'\',
        email TEXT NOT NULL DEFAULT \'\',
        token TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'aktiv\',
        note TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        redeemed_at TEXT
    )');
    $vc = array_column($pdo->query('PRAGMA table_info(mm_vouchers)')->fetchAll(), 'name');
    foreach (['recipient', 'sender', 'message'] as $c) if ($vc && !in_array($c, $vc, true)) $pdo->exec("ALTER TABLE mm_vouchers ADD COLUMN $c TEXT NOT NULL DEFAULT ''");
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_voucher_uses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        voucher_id INTEGER NOT NULL,
        order_id INTEGER NOT NULL,
        cents INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT \'reserviert\',
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_voucher_tries (ip_hash TEXT NOT NULL, at INTEGER NOT NULL)');
}

// --- 1. Start bestätigen -------------------------------------------------------------------------

function natal_start_link(array $o): string
{
    return mm_site() . '/natal/start/?o=' . (int)$o['id'] . '&k=' . $o['token'];
}

/** Speichert die Bestätigung aus /natal/start/. Gibt die neue Buchung zurück. */
function natal_start_confirm(PDO $pdo, array $o, bool $digital): array
{
    natal_wd_schema($pdo);
    $now = date('c');
    $pdo->prepare('UPDATE mm_natal_orders SET confirm_at = COALESCE(confirm_at, ?), consent_start = 1 WHERE id = ?')->execute([$now, $o['id']]);
    if ($digital && !(int)($o['consent_digital'] ?? 0)) {
        $pdo->prepare('UPDATE mm_natal_orders SET consent_digital = 1, digital_at = ? WHERE id = ?')->execute([$now, $o['id']]);
    }
    $o2 = natal_order($pdo, (int)$o['id']);
    if (!$o['confirm_at'] || ($digital && !(int)($o['consent_digital'] ?? 0))) {
        // Dauerhafter Datenträger: Bestätigung der Erklärungen per Mail (§ 7 Abs. 3 FAGG)
        $first = forma_first_name((string)$o2['name']);
        $p = ["Hallo $first,", 'danke, deine Bestätigung ist angekommen. Das hast du mir am ' . date('d.m.Y \u\m H:i', strtotime($now)) . ' Uhr bestätigt:',
            ['✓ Die Einheiten dürfen schon in der 14-tägigen Rücktrittsfrist beginnen.', '✓ Trittst du zurück, werden nur die Einheiten abgezogen, die bereits stattgefunden haben (zum Paketpreis pro Einheit).', '✓ Dein Rücktrittsrecht erlischt, sobald alle gebuchten Einheiten stattgefunden haben.']];
        if ((int)$o2['consent_digital']) $p[] = ['✓ Du möchtest deinen Mitgliederbereich mit Workbooks, Videos und Aufnahmen sofort vollständig nutzen und weißt, dass dein Rücktrittsrecht für diese digitalen Inhalte damit erlischt. Für die Einheiten selbst gilt dein Rücktrittsrecht weiter wie oben.'];
        $p[] = 'Ich freue mich riesig auf unsere erste Einheit!';
        natal_mail((string)$o2['email'], 'Deine Bestätigung für das Prä- & Postnatal Training', 'Alles bestätigt', $p, natal_order_is_member($o2) ? [natal_member_by_email($pdo, (string)$o2['email']) ? natal_member_link() : natal_member_start_link($o2), 'Zu meinem Mitgliederbereich'] : [natal_link($o2), 'Meine Termine ansehen']);
    }
    return $o2;
}

// --- 2. Rücktritt ---------------------------------------------------------------------------------

/** Preis pro Einheit je Art (Paketpreis). Testbuchungen (€ 0) rechnen mit den aktuellen Paketpreisen. */
function natal_wd_prices(array $o): array
{
    if ((int)($o['is_test'] ?? 0) === 1 || (int)$o['amount_cents'] <= 0) {
        $p = natal_packages();
        $base = preg_replace('/^(x_|o_)/', '', natal_base_package($o));
        $st = (int)($p[$base]['unit_cents'] ?? 0);
        $on = (int)($p['o_' . $base]['unit_cents'] ?? $st);
        if (natal_is_online($o) && !natal_is_mix($o)) $st = $on;
        return ['studio' => $st, 'online' => $on];
    }
    if (natal_is_mix($o)) {
        $m = natal_mix_parts($o);
        return ['studio' => $m['unit_studio'] ?: (int)$o['unit_cents'], 'online' => $m['unit_online']];
    }
    return ['studio' => (int)$o['unit_cents'], 'online' => (int)$o['unit_cents']];
}

/** Gesamtwert der Buchung (inkl. eines eingelösten Gutscheins). */
function natal_wd_total(array $o): int
{
    if ((int)($o['is_test'] ?? 0) === 1 || (int)$o['amount_cents'] <= 0) {
        $pr = natal_wd_prices($o);
        if (natal_is_mix($o)) { $m = natal_mix_parts($o); return $m['studio'] * $pr['studio'] + $m['online'] * $pr['online']; }
        return (int)$o['units'] * $pr['studio'];
    }
    return (int)$o['amount_cents'];
}

/** Letzter Tag der Rücktrittsfrist (14 Tage ab Vertragsabschluss = Zahlung), bis 23:59. */
function natal_wd_until(array $o): int
{
    return strtotime(date('Y-m-d', strtotime((string)($o['paid_at'] ?: $o['created_at']))) . ' +' . NATAL_WD_DAYS . ' days 23:59:59');
}

function natal_wd_row(PDO $pdo, int $oid): ?array
{
    natal_wd_schema($pdo);
    $q = $pdo->prepare('SELECT * FROM mm_natal_wd WHERE order_id = ?');
    $q->execute([$oid]);
    return $q->fetch() ?: null;
}

/** Was zurückkäme, wenn sie jetzt zurücktritt. */
function natal_wd_calc(PDO $pdo, array $o): array
{
    natal_wd_schema($pdo);
    $pr = natal_wd_prices($o);
    $total = natal_wd_total($o);
    $usedS = 0; $usedO = 0;
    foreach (natal_sessions($pdo, (int)$o['id']) as $s) {
        if (!natal_session_done($s)) continue;
        if (natal_sess_online($o, $s)) $usedO++; else $usedS++;
    }
    $used = min($total, $usedS * $pr['studio'] + $usedO * $pr['online']);
    $lines = [];
    if ($usedS) $lines[] = $usedS . ' × im Studio à ' . forma_euro($pr['studio']);
    if ($usedO) $lines[] = $usedO . ' × online à ' . forma_euro($pr['online']);
    $back = max(0, $total - $used);
    $isTest = (int)($o['is_test'] ?? 0) === 1 || (int)$o['amount_cents'] <= 0;
    $cash = $isTest ? $back : max(0, (int)$o['amount_cents'] - (int)($o['voucher_cents'] ?? 0));
    $cashBack = min($cash, $back);
    return ['total' => $total, 'used' => $used, 'used_n' => $usedS + $usedO, 'used_text' => $lines ? implode(' + ', $lines) : 'noch keine', 'back' => $back,
        'cash_back' => $cashBack, 'voucher_back' => $back - $cashBack, 'until' => natal_wd_until($o), 'test' => $isTest];
}

/** Darf sie im Mitgliederbereich selbst zurücktreten? (in der Frist, noch nicht alles stattgefunden, noch kein Rücktritt) */
function natal_wd_open(PDO $pdo, array $o): bool
{
    if ($o['status'] !== 'bezahlt' || time() > natal_wd_until($o) || natal_wd_row($pdo, (int)$o['id'])) return false;
    return !natal_order_finished($pdo, $o);
}

/** Teilt einen Betrag auf 1 oder 2 Gutscheine (50/50, ein ungerader Cent kommt zum ersten). */
function natal_wd_split(int $cents, int $count): array
{
    if ($count !== 2 || $cents < 2) return [$cents];
    $half = intdiv($cents, 2);
    return [$cents - $half, $half];
}

/**
 * Führt den Rücktritt aus. Genau einmal pro Buchung: der Eintrag in mm_natal_wd (UNIQUE order_id) entsteht in derselben
 * Transaktion wie die Statusänderung und die Gutscheine. Die Stripe-Erstattung läuft danach und wird über die Metadaten
 * abgesichert (vor einem neuen Versuch wird bei Stripe nachgesehen, ob es schon eine gibt).
 */
function natal_wd_execute(PDO $pdo, int $oid, string $choice, int $count, int $design, string $source): array
{
    natal_wd_schema($pdo);
    $choice = $choice === 'gutschein' ? 'gutschein' : 'erstattung';
    $count = $count === 2 ? 2 : 1;
    $design = isset(MV_VOUCHER_DESIGNS[$design]) ? $design : 1;
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $o = natal_order($pdo, $oid);
        if (!$o || $o['status'] !== 'bezahlt') { $pdo->exec('ROLLBACK'); return ['ok' => false, 'error' => 'Diese Buchung ist nicht (mehr) aktiv.']; }
        if (natal_wd_row($pdo, $oid)) { $pdo->exec('ROLLBACK'); return ['ok' => false, 'error' => 'Für diese Buchung ist der Rücktritt schon eingegangen.']; }
        $c = natal_wd_calc($pdo, $o);
        $refund = $choice === 'erstattung' ? $c['cash_back'] : 0;
        $vcents = $c['back'] - $refund; // Gutschein: alles; Erstattung: nur ein Anteil, der schon mit Gutschein bezahlt war
        $now = date('c');
        $pdo->prepare('INSERT INTO mm_natal_wd (order_id, choice, total_cents, used_cents, used_text, back_cents, refund_cents, voucher_cents, status, source, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$oid, $choice, $c['total'], $c['used'], $c['used_text'], $c['back'], $refund, $vcents, $refund > 0 && !$c['test'] ? 'offen' : 'erledigt', $source, $now]);
        $wid = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE mm_natal_orders SET status = 'widerrufen', withdrawn_at = ? WHERE id = ?")->execute([$now, $oid]);
        // Offene Termine (angefragt / bestätigt, noch nicht stattgefunden) fallen weg
        foreach (natal_sessions($pdo, $oid) as $s) {
            if (in_array($s['status'], ['angefragt', 'bestaetigt'], true) && !natal_session_done($s))
                $pdo->prepare("UPDATE mm_natal_sessions SET status = 'abgesagt', note = TRIM(note || ' [Rücktritt]') WHERE id = ?")->execute([$s['id']]);
        }
        $vouchers = [];
        if ($vcents > 0) {
            $parts = $choice === 'gutschein' ? natal_wd_split($vcents, $count) : [$vcents];
            foreach ($parts as $i => $pc) $vouchers[] = mv_voucher_create($pdo, $pc, $design, $o, $wid, count($parts) === 2 ? ($i + 1) . '/2' : '');
        }
        if ($c['test']) $pdo->prepare("UPDATE mm_natal_wd SET done_at = ?, note = 'Testbuchung: keine Zahlung, keine Stornorechnung' WHERE id = ?")->execute([$now, $wid]);
        elseif ($refund <= 0) $pdo->prepare('UPDATE mm_natal_wd SET done_at = ? WHERE id = ?')->execute([$now, $wid]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $e2) {}
        error_log('Natal Rücktritt: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal oder schreib mir.'];
    }
    $w = natal_wd_row($pdo, $oid);
    if ($refund > 0 && !$c['test']) $w = natal_wd_refund($pdo, $w);
    // Stornorechnung für alles, was nicht genutzt wurde (Erstattung und Gutschein gleichermaßen)
    if (!$c['test'] && $c['back'] > 0 && function_exists('forma_invoice_storno')) {
        try {
            forma_invoice_storno($pdo, 'natal', $oid, $c['back'], 'Rücktritt, nicht genutzte Einheiten · ' . ($vcents > 0 && $refund <= 0 ? 'als Movecia-Gutschein ausgestellt (' . implode(', ', array_column($vouchers, 'nr')) . ')' : ($vcents > 0 ? forma_euro($refund) . ' erstattet, ' . forma_euro($vcents) . ' als Gutschein' : 'Betrag erstattet')));
        } catch (Throwable $e) { error_log('Natal Storno-Rechnung: ' . $e->getMessage()); }
    }
    natal_wd_mails($pdo, natal_order($pdo, $oid), $w, $vouchers);
    return ['ok' => true, 'wd' => $w, 'vouchers' => $vouchers];
}

/** Stripe-Erstattung (nur wenn noch keine da ist). */
function natal_wd_refund(PDO $pdo, array $w): array
{
    if ($w['refund_id'] !== '' || (int)$w['refund_cents'] <= 0) return $w;
    $o = natal_order($pdo, (int)$w['order_id']);
    $rid = ''; $err = '';
    try {
        if (!$o || (string)$o['stripe_session'] === '' || !forma_stripe_key()) throw new RuntimeException('keine Stripe-Zahlung gefunden');
        $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$o['stripe_session']));
        $pi = is_array($s['payment_intent'] ?? null) ? ($s['payment_intent']['id'] ?? '') : (string)($s['payment_intent'] ?? '');
        if ($pi === '') throw new RuntimeException('keine Zahlung zur Sitzung');
        // Schon erstattet? (z. B. nach einem abgebrochenen Versuch)
        $list = forma_stripe('GET', '/v1/refunds?payment_intent=' . rawurlencode($pi) . '&limit=20');
        foreach ((array)($list['data'] ?? []) as $r) if ((string)($r['metadata']['natal_wd'] ?? '') === (string)$w['id'] && ($r['status'] ?? '') !== 'failed') $rid = (string)$r['id'];
        if ($rid === '') {
            $r = forma_stripe('POST', '/v1/refunds', ['payment_intent' => $pi, 'amount' => (int)$w['refund_cents'], 'reason' => 'requested_by_customer',
                'metadata' => ['natal_wd' => (string)$w['id'], 'natal_order_id' => (string)$w['order_id'], 'grund' => 'Rücktritt Prä-/Postnatal']]);
            $rid = (string)($r['id'] ?? '');
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
        error_log('Natal Erstattung: ' . $err);
    }
    if ($rid !== '') $pdo->prepare("UPDATE mm_natal_wd SET refund_id = ?, status = 'erledigt', done_at = ?, note = '' WHERE id = ? AND refund_id = ''")->execute([$rid, date('c'), $w['id']]);
    else $pdo->prepare("UPDATE mm_natal_wd SET status = 'fehler', note = ? WHERE id = ?")->execute(['Erstattung über Stripe ging nicht: ' . mb_substr($err, 0, 200), $w['id']]);
    return natal_wd_row($pdo, (int)$w['order_id']);
}

function natal_wd_mails(PDO $pdo, array $o, array $w, array $vouchers): void
{
    $first = forma_first_name((string)$o['name']);
    $at = date('d.m.Y, H:i', strtotime((string)$w['created_at']));
    $p = ["Hallo $first,", 'hiermit bestätige ich den Eingang deines Rücktritts. Danke, dass du mir Bescheid gegeben hast.',
        ['Vertrag: Prä- & Postnatal Training · ' . $o['title'], 'Name: ' . $o['name'] . ' · E-Mail: ' . $o['email'], 'Eingegangen am: ' . $at . ' Uhr (Wiener Zeit)', 'Umfang: der gesamte Vertrag'],
        ['Gesamtwert deiner Buchung: ' . forma_euro((int)$w['total_cents']), 'Bereits stattgefunden: ' . $w['used_text'] . ((int)$w['used_cents'] ? ' = ' . forma_euro((int)$w['used_cents']) : ''), 'Für dich zurück: ' . forma_euro((int)$w['back_cents'])]];
    if ((int)$w['refund_cents'] > 0) $p[] = $w['status'] === 'fehler'
        ? forma_euro((int)$w['refund_cents']) . ' erstatte ich dir binnen 14 Tagen auf dasselbe Zahlungsmittel, mit dem du bezahlt hast. Du musst dafür nichts weiter tun.'
        : forma_euro((int)$w['refund_cents']) . ' sind schon auf dem Weg zurück auf dasselbe Zahlungsmittel, mit dem du bezahlt hast. Je nach Bank dauert es ein paar Tage, bis du den Betrag siehst.';
    $att = [];
    if ($vouchers) {
        $lines = [];
        foreach ($vouchers as $v) {
            $lines[] = 'Gutschein ' . $v['nr'] . ' über ' . forma_euro((int)$v['amount_cents']) . ' · Code: ' . $v['code'] . ' · direkt einlösen: ' . mm_site() . '/natal/?gutschein=' . $v['code'] . '#buchen';
            $att[] = ['Movecia_Gutschein_' . $v['nr'] . '.pdf', mv_voucher_pdf($v), 'application/pdf'];
        }
        $p[] = (count($vouchers) === 2 ? 'Deine zwei Gutscheine hängen' : 'Dein Gutschein hängt') . ' als PDF zum Ausdrucken an dieser E-Mail:';
        $p[] = $lines;
        $p[] = MV_VOUCHER_TERMS;
    }
    $p[] = 'Offene Termine habe ich abgesagt. Die Stornorechnung bekommst du in einer eigenen E-Mail. Ich hoffe sehr, dass wir uns irgendwann wiedersehen, und wünsche dir von Herzen alles Gute.';
    natal_mail((string)$o['email'], 'Eingangsbestätigung deines Rücktritts', 'Dein Rücktritt ist angekommen', $p, null, $att);

    $v = ['Rücktritt Prä- & Postnatal Training ' . ($w['source'] === 'verwaltung' ? '(von dir in der Verwaltung)' : '(von ihr im Mitgliederbereich)') . ':', [
        $o['name'] . ' · ' . $o['email'] . ' · ' . $o['title'] . ((int)($o['is_test'] ?? 0) ? ' · TEST' : ''),
        'Gesamt ' . forma_euro((int)$w['total_cents']) . ' · stattgefunden ' . $w['used_text'] . ' · zurück ' . forma_euro((int)$w['back_cents']),
        $w['choice'] === 'gutschein' ? 'Gewählt: Gutschein (' . implode(', ', array_map(fn($x) => $x['nr'] . ' ' . forma_euro((int)$x['amount_cents']), $vouchers)) . ')' : 'Gewählt: Rückerstattung ' . forma_euro((int)$w['refund_cents']) . ($vouchers ? ' + Gutschein ' . $vouchers[0]['nr'] . ' (Anteil, der mit Gutschein bezahlt war)' : ''),
    ]];
    if ($w['status'] === 'fehler') $v[] = '⚠ Die Erstattung über Stripe hat nicht geklappt (' . $w['note'] . '). Bitte in der Verwaltung „Erstattung erneut versuchen“ tippen oder in Stripe von Hand erstatten.';
    elseif ((int)$w['refund_cents'] > 0) $v[] = 'Die Erstattung ist bei Stripe angestoßen, du musst nichts tun.';
    $v[] = 'Offene Termine sind abgesagt, die Stornorechnung ist automatisch erstellt.';
    natal_mail(natal_notify_email(), 'Rücktritt: ' . $o['name'] . ' · ' . $o['title'], 'Rücktritt eingegangen', $v, [natal_admin_link(), 'In der Verwaltung öffnen'], $att);
}

// --- 3. Movecia-Gutscheine -----------------------------------------------------------------------

function mv_voucher_code(): string
{
    $c = '';
    for ($i = 0; $i < 12; $i++) $c .= FORMA_CODE_CHARS[random_int(0, 31)];
    return 'MOVECIA-' . substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4);
}

function mv_voucher_norm(string $code): string
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if (str_starts_with($c, 'MOVECIA')) $c = substr($c, 7);
    $c = strtr($c, ['0' => 'O', '1' => 'I']);
    return strlen($c) === 12 ? 'MOVECIA-' . substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4) : '';
}

/** Legt einen Gutschein an (innerhalb einer Transaktion aufrufen). Nummer MV-JJJJ-001 fortlaufend. */
function mv_voucher_create(PDO $pdo, int $cents, int $design, array $o, int $wid, string $part): array
{
    natal_wd_schema($pdo);
    $year = date('Y');
    $n = (int)$pdo->query("SELECT COUNT(*) FROM mm_vouchers WHERE nr LIKE 'MV-$year-%'")->fetchColumn() + 1;
    $nr = sprintf('MV-%s-%03d', $year, $n);
    do { $code = mv_voucher_code(); $q = $pdo->prepare('SELECT COUNT(*) FROM mm_vouchers WHERE code = ?'); $q->execute([$code]); } while ((int)$q->fetchColumn());
    $note = 'aus Rücktritt Buchung #' . (int)$o['id'] . ' (' . $o['title'] . ', ' . $o['name'] . ')' . ($part !== '' ? ', Teil ' . $part : '') . ((int)($o['is_test'] ?? 0) ? ' · TEST' : '');
    $pdo->prepare('INSERT INTO mm_vouchers (nr, code, amount_cents, design, origin, order_id, wd_id, part, name, email, token, status, note, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$nr, $code, $cents, $design, 'ruecktritt', $o['id'], $wid, $part, $o['name'], $o['email'], bin2hex(random_bytes(16)), (int)($o['is_test'] ?? 0) ? 'test' : 'aktiv', $note, date('c')]);
    $q = $pdo->prepare('SELECT * FROM mm_vouchers WHERE id = ?');
    $q->execute([(int)$pdo->lastInsertId()]);
    return $q->fetch();
}

function mv_voucher_pdf_link(array $v): string
{
    return mm_site() . '/api/mv-gutschein.php?v=' . (int)$v['id'] . '&k=' . $v['token'];
}

/** Restwert = Betrag minus reservierte und eingelöste Beträge. */
function mv_voucher_balance(PDO $pdo, array $v): int
{
    if (!in_array($v['status'], ['aktiv', 'test'], true)) return 0;
    $st = $pdo->prepare("SELECT COALESCE(SUM(cents), 0) FROM mm_voucher_uses WHERE voucher_id = ? AND status IN ('reserviert','eingeloest')");
    $st->execute([$v['id']]);
    return max(0, (int)$v['amount_cents'] - (int)$st->fetchColumn());
}

/** Code prüfen, mit Bremse gegen Durchprobieren (8 Fehlversuche pro Stunde). */
function mv_voucher_lookup(PDO $pdo, string $code): array
{
    natal_wd_schema($pdo);
    mv_voucher_sweep($pdo);
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'mv-gs');
    $pdo->prepare('DELETE FROM mm_voucher_tries WHERE at < ?')->execute([time() - 3600]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM mm_voucher_tries WHERE ip_hash = ?');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 8) return ['ok' => false, 'error' => 'Zu viele Versuche. Bitte probier es in einer Stunde noch einmal oder schreib mir.'];
    $norm = mv_voucher_norm($code);
    $v = null;
    if ($norm !== '') { $q = $pdo->prepare('SELECT * FROM mm_vouchers WHERE code = ?'); $q->execute([$norm]); $v = $q->fetch() ?: null; }
    if (!$v) {
        $pdo->prepare('INSERT INTO mm_voucher_tries (ip_hash, at) VALUES (?, ?)')->execute([$ip, time()]);
        return ['ok' => false, 'error' => 'Diesen Gutscheincode kenne ich leider nicht. Bitte prüf ihn noch einmal (z. B. MOVECIA-AB12-CD34-EF56).'];
    }
    if ($v['status'] === 'test') return ['ok' => false, 'error' => 'Das ist ein Test-Gutschein aus der Verwaltung, er lässt sich nicht einlösen.'];
    $bal = mv_voucher_balance($pdo, $v);
    if ($bal <= 0) return ['ok' => false, 'error' => 'Dieser Gutschein ist schon vollständig eingelöst.'];
    return ['ok' => true, 'voucher' => $v, 'balance' => $bal];
}

/** Reservierungen: bezahlt → eingelöst, nach 40 Min. ohne Zahlung → wieder frei. */
function mv_voucher_sweep(PDO $pdo): void
{
    natal_wd_schema($pdo);
    foreach ($pdo->query("SELECT u.*, o.status AS ost, o.stripe_session AS osess FROM mm_voucher_uses u LEFT JOIN mm_natal_orders o ON o.id = u.order_id WHERE u.status = 'reserviert'")->fetchAll() as $u) {
        if (in_array($u['ost'], ['bezahlt', 'widerrufen'], true)) mv_voucher_finalize($pdo, (int)$u['order_id']);
        // Sicherheit (31.10.2026): eine noch offene Bestellung erst nach 2 Tagen freigeben (Stripe-Sitzungen laufen spätestens nach 24 Std. ab),
        // sonst wäre der Gutscheinwert nach einem kurzen Stripe-Ausfall doppelt einlösbar
        elseif ($u['ost'] === null || $u['ost'] === 'abgebrochen' || (($u['ost'] !== 'offen' || (string)$u['osess'] === '') && strtotime((string)$u['created_at']) < time() - 40 * 60) || strtotime((string)$u['created_at']) < time() - 2 * 86400)
            $pdo->prepare("UPDATE mm_voucher_uses SET status = 'frei' WHERE id = ?")->execute([$u['id']]);
    }
}

/** Nach der Zahlung: Reservierung wird zur Einlösung, voll eingelöste Gutscheine bekommen den Status „eingelöst“. */
function mv_voucher_finalize(PDO $pdo, int $oid): void
{
    natal_wd_schema($pdo);
    // auch „frei“: falls die Zahlung erst nach Ablauf der Reservierung bestätigt wurde
    $q = $pdo->prepare("SELECT * FROM mm_voucher_uses WHERE order_id = ? AND status IN ('reserviert','frei')");
    $q->execute([$oid]);
    foreach ($q->fetchAll() as $u) {
        $pdo->prepare("UPDATE mm_voucher_uses SET status = 'eingeloest' WHERE id = ?")->execute([$u['id']]);
        $v = $pdo->query('SELECT * FROM mm_vouchers WHERE id = ' . (int)$u['voucher_id'])->fetch();
        if ($v && mv_voucher_balance($pdo, $v) <= 0) $pdo->prepare("UPDATE mm_vouchers SET status = 'eingeloest', redeemed_at = ? WHERE id = ?")->execute([date('c'), $v['id']]);
    }
}

/** Nur für Stripe: ein einmaliger Rabatt in Höhe des Gutscheins. */
function mv_voucher_coupon(array $o): string
{
    $c = forma_stripe('POST', '/v1/coupons', ['amount_off' => (int)$o['voucher_cents'], 'currency' => 'eur', 'duration' => 'once', 'max_redemptions' => 1,
        'name' => 'Movecia Gutschein', 'metadata' => ['natal_order_id' => (string)$o['id']]]);
    return (string)$c['id'];
}

/** PDF zum Ausdrucken: Hintergrund pro Design (api/gutschein/movecia1|2.jpg), Text mit Cormorant und Inter. */
function mv_voucher_pdf(array $v): string
{
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
    $t = fn(string $s) => (string)iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
    $fd = __DIR__ . '/vendor/fpdf/font/';
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddFont('Cormorant', '', 'Cormorant-Medium.json', $fd);
    $pdf->AddFont('Cormorant', 'I', 'Cormorant-Italic.json', $fd);
    $pdf->AddFont('Inter', '', 'Inter-Regular.json', $fd);
    $pdf->AddFont('Inter', 'B', 'Inter-SemiBold.json', $fd);
    $pdf->SetTitle($t('Movecia Gutschein ' . $v['nr']), false);
    $pdf->SetAuthor('Movecia - Personal Fitness');
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->AddPage();
    $d = (int)$v['design'] === 2 ? 2 : 1;
    $bg = __DIR__ . "/gutschein/movecia$d.jpg";
    if (is_file($bg)) $pdf->Image($bg, 0, 0, 297, 210);
    $plum = [74, 48, 64]; $ink = [42, 32, 38]; $soft = [110, 97, 104];
    if ($d === 1) {
        // Persönlicher Text im Bogen (Vivien, 31.10.2026): oben Zeichen, unten Movecia Movement, dazwischen die lieben Worte
        $to = trim((string)($v['recipient'] ?? '')); $from = trim((string)($v['sender'] ?? '')); $msg = trim((string)($v['message'] ?? ''));
        if ($msg === '') $msg = 'Ein Moment nur für dich. Zum Atmen, Bewegen und Ankommen.';
        if (mb_strlen($msg) > 200) $msg = rtrim(mb_substr($msg, 0, 197)) . '…'; // passt sicher in den Bogen
        $ax = 30; $aw = 82; $ay = 70;
        if ($to !== '') { $pdf->SetTextColor(125, 94, 104); $pdf->SetFont('Inter', 'B', 7.5); $pdf->SetXY($ax, $ay); $pdf->Cell($aw, 5, $t('F Ü R'), 0, 0, 'C');
            $pdf->SetTextColor(...$plum); $pdf->SetFont('Cormorant', '', 22); $pdf->SetXY($ax, $ay + 5); $pdf->Cell($aw, 10, $t($to), 0, 0, 'C'); $ay += 20; }
        $pdf->SetTextColor(...$ink); $pdf->SetFont('Cormorant', 'I', mb_strlen($msg) > 110 ? 14 : 16.5);
        $pdf->SetXY($ax + 2, $ay); $pdf->MultiCell($aw - 4, mb_strlen($msg) > 110 ? 6.4 : 7.4, $t('„' . $msg . '“'), 0, 'C');
        if ($from !== '') { $pdf->SetTextColor(125, 94, 104); $pdf->SetFont('Inter', '', 9); $pdf->SetXY($ax, $pdf->GetY() + 4); $pdf->Cell($aw, 5, $t('von ' . $from), 0, 0, 'C'); }
    }
    // Textblock rechts (Design 1: neben dem Bogen) bzw. mittig (Design 2)
    [$x, $w] = $d === 1 ? [140, 138] : [48, 201];
    $y = $d === 1 ? 46 : 50;
    $pdf->SetTextColor(...$plum); $pdf->SetFont('Inter', 'B', 8.5);
    $pdf->SetXY($x, $y); $pdf->Cell($w, 5, $t('G U T S C H E I N'), 0, 0, 'C');
    $pdf->SetTextColor(...$ink); $pdf->SetFont('Cormorant', 'I', 30);
    $pdf->SetXY($x, $y + 9); $pdf->Cell($w, 12, $t('Zeit für dich'), 0, 0, 'C');
    $pdf->SetTextColor(...$plum); $pdf->SetFont('Cormorant', '', 58);
    $pdf->SetXY($x, $y + 26); $pdf->Cell($w, 24, $t((int)$v['amount_cents'] < 0 ? 'dein Betrag' : forma_euro((int)$v['amount_cents'])), 0, 0, 'C'); // < 0 nur für die Vorschaubilder
    $pdf->SetTextColor(...$soft); $pdf->SetFont('Inter', '', 10.5);
    $pdf->SetXY($x + 10, $y + 55); $pdf->MultiCell($w - 20, 5.6, $t('für das Prä- & Postnatal Training bei Movecia, 1:1 im Studio in Mathon oder online'), 0, 'C');
    // Code-Feld
    $cy = $y + 78;
    $pdf->SetDrawColor(...$plum); $pdf->SetLineWidth(.3);
    $pdf->SetXY($x + 24, $cy); $pdf->SetFont('Inter', '', 7.5); $pdf->SetTextColor(...$soft);
    $pdf->Cell($w - 48, 5, $t('GUTSCHEINCODE'), 0, 2, 'C');
    $pdf->SetFont('Inter', 'B', 13.5); $pdf->SetTextColor(...$plum);
    $pdf->Cell($w - 48, 10, $t((string)$v['code']), 'TB', 0, 'C');
    $pdf->SetFont('Inter', '', 7.6); $pdf->SetTextColor(...$soft);
    $pdf->SetXY($x + 8, $cy + 21); $pdf->MultiCell($w - 16, 4, $t('Einlösen: auf moveciamovement.com/natal bei der Buchung den Code eingeben. Ohne Ablaufdatum, Restwert bleibt erhalten, keine Barauszahlung.'), 0, 'C');
    $pdf->SetXY($x, $cy + 33); $pdf->Cell($w, 4, $t('Nr. ' . $v['nr'] . ' · ausgestellt am ' . date('d.m.Y', strtotime((string)$v['created_at'])) . ' · Vivien Malonga, Movecia'), 0, 0, 'C');
    return $pdf->Output('S');
}

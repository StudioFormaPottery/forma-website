<?php
// Angebote für Gruppen (Vivien, 28.10.2026), z. B. Schulgruppen beim Pottery bemalen.
// Vivien legt in der Verwaltung (?view=angebote) ein unverbindliches Angebot an: Gruppe, z. H., Termine, Preis pro Kind.
// Sie schickt den Link per WhatsApp. Die Gruppe öffnet /angebot/?t=TOKEN, trägt pro Termin die Anzahl Kinder ein
// (keine Namen), sieht den Gesamtbetrag live und bestätigt mit einem Knopf.
// Bestätigt → Status „bestätigt“, Termine stehen im Kalender (verwaltung ?view=kalender und belegt.inc.php),
//             Vorabrechnung (ohne Rechnungsnummer, „noch nicht bezahlt“) per Mail + als PDF auf der Angebotsseite.
// Bezahlt  → online über Stripe (api/angebot-zahlen.php, auch per QR-Code) oder bar („Bar bezahlt“ in der Verwaltung):
//             erst dann die fertige Rechnung (forma_invoice_for 'angebot'), an die Gruppe und als Kopie an Vivien.
// Kommen weniger Kinder, trägt Vivien vorher die tatsächliche Zahl ein (kids_final); verrechnet wird immer diese.
// Wortwahl: Pottery bemalen, Werkstück, Fertigstellung. Nie „Keramik“.
// Wird von lib.php eingebunden.
declare(strict_types=1);

const FORMA_OFFER_MAX_KIDS = 80;

function forma_offer_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS offers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nr TEXT NOT NULL DEFAULT '',
        token TEXT NOT NULL,
        org TEXT NOT NULL,
        contact TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        phone TEXT NOT NULL DEFAULT '',
        title TEXT NOT NULL,
        intro TEXT NOT NULL DEFAULT '',
        item TEXT NOT NULL DEFAULT 'Teller',
        unit_cents INTEGER NOT NULL,
        dates TEXT NOT NULL,
        valid_until TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'offen',
        note TEXT NOT NULL DEFAULT '',
        confirmed_at TEXT,
        paid_at TEXT,
        pay_method TEXT NOT NULL DEFAULT '',
        stripe_session TEXT NOT NULL DEFAULT '',
        vorab_sent TEXT,
        created_at TEXT NOT NULL
    )");
    // Rechnungsempfänger, von der Gruppe beim Bestätigen eingetragen (Vivien, 28.10.2026: offizielle Rechnung an die Schule)
    forma_add_column($pdo, 'offers', 'bill_to', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'offers', 'bill_name', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'offers', 'bill_attn', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'offers', 'bill_address', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'offers', 'bill_email', "TEXT NOT NULL DEFAULT ''");
    $done = true;
}

function forma_offer_get(PDO $pdo, int $id): ?array
{
    forma_offer_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM offers WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function forma_offer_by_token(PDO $pdo, string $token): ?array
{
    forma_offer_schema($pdo);
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $st = $pdo->prepare('SELECT * FROM offers WHERE token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function forma_offers(PDO $pdo): array
{
    forma_offer_schema($pdo);
    return $pdo->query("SELECT * FROM offers ORDER BY CASE status WHEN 'offen' THEN 0 WHEN 'bestaetigt' THEN 1 WHEN 'bezahlt' THEN 2 ELSE 3 END, id DESC")->fetchAll();
}

/** Termine: [['date' => 'Y-m-d', 'from' => 'H:i', 'to' => 'H:i', 'place' => '', 'kids' => int, 'final' => int|null], …] */
function forma_offer_dates(array $o): array
{
    $d = json_decode((string)$o['dates'], true);
    return is_array($d) ? $d : [];
}

/** Kinder, die verrechnet werden: tatsächliche Zahl, sobald Vivien sie eingetragen hat. */
function forma_offer_bill_kids(array $d): int
{
    return isset($d['final']) && $d['final'] !== null && $d['final'] !== '' ? (int)$d['final'] : (int)($d['kids'] ?? 0);
}

function forma_offer_total(array $o): int
{
    $n = 0;
    foreach (forma_offer_dates($o) as $d) $n += forma_offer_bill_kids($d);
    return $n * (int)$o['unit_cents'];
}

function forma_offer_kids(array $o): int
{
    return array_sum(array_map('forma_offer_bill_kids', forma_offer_dates($o)));
}

function forma_offer_link(array $o): string
{
    return forma_site_url() . '/angebot/?t=' . $o['token'];
}

function forma_offer_pay_url(array $o): string
{
    return forma_site_url() . '/api/angebot-zahlen.php?t=' . $o['token'];
}

/** „Montag, 16. November 2026“ */
function forma_offer_day(string $ymd): string
{
    $wd = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $mo = ['Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    $t = strtotime($ymd);
    return $wd[(int)date('N', $t) - 1] . ', ' . (int)date('j', $t) . '. ' . $mo[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** „9–11 Uhr · im Atelier“ oder '' */
function forma_offer_when(array $d): string
{
    $time = '';
    if (($d['from'] ?? '') !== '') {
        $time = forma_de_time((string)$d['from']);
        if (($d['to'] ?? '') !== '') $time = preg_replace('/\s*Uhr$/u', '', $time) . '–' . forma_de_time((string)$d['to']);
    }
    return implode(' · ', array_filter([$time, trim((string)($d['place'] ?? ''))]));
}

function forma_offer_status_label(array $o): string
{
    return match ((string)$o['status']) {
        'offen' => 'Noch nicht bestätigt',
        'bestaetigt' => 'Bestätigt · ' . forma_offer_kids($o) . ' Kinder · noch nicht bezahlt',
        'bezahlt' => 'Bezahlt' . ($o['pay_method'] === 'bar' ? ' (bar)' : ' (online)'),
        default => 'Zurückgezogen',
    };
}

/** Rechnungsempfänger: [Name, Adresszeilen (z. H. + Anschrift), E-Mail]. */
function forma_offer_bill(array $o): array
{
    $name = trim((string)($o['bill_name'] ?? '')) ?: (string)$o['org'];
    $attn = trim((string)($o['bill_attn'] ?? ''));
    if (($o['bill_name'] ?? '') === '' && $attn === '') $attn = (string)$o['contact'];
    $addr = trim(($attn !== '' ? 'z. H. ' . $attn . "\n" : '') . trim((string)($o['bill_address'] ?? '')));
    $email = trim((string)($o['bill_email'] ?? '')) ?: (string)$o['email'];
    return [$name, $addr, strtolower($email)];
}

/** Rechnung zusätzlich an die Ansprechperson, wenn sie an eine andere Adresse ging. */
function forma_offer_copy_invoice(PDO $pdo, array $o): void
{
    [, , $billEmail] = forma_offer_bill($o);
    $contactEmail = strtolower(trim((string)$o['email']));
    if ($contactEmail === '' || $contactEmail === $billEmail) return;
    $st = $pdo->prepare("SELECT * FROM invoices WHERE kind = 'angebot' AND ref_id = ?");
    $st->execute([(int)$o['id']]);
    if (!$inv = $st->fetch()) return;
    $first = forma_offer_first($o);
    forma_mail($contactEmail, 'Rechnung ' . $inv['nr'] . ' von FORMA (Kopie)', ($first !== '' ? "Liebe $first" : 'Hallo') . ",\n\ndanke für die Zahlung! Die Rechnung {$inv['nr']} ist an {$inv['name']} ($billEmail) gegangen, hier ist eine Kopie für dich.\n\nBis bald,\nVivien\n",
        null, [['Rechnung_' . $inv['nr'] . '.pdf', forma_invoice_pdf($inv), 'application/pdf']]);
}

/** Ansprechperson für die Anrede („Liebe Carmen“). */
function forma_offer_first(array $o): string
{
    $c = trim((string)$o['contact']);
    return $c !== '' ? forma_first_name($c) : '';
}

/** Neues Angebot anlegen. Gibt die ID zurück. */
function forma_offer_create(PDO $pdo, array $f): int
{
    forma_offer_schema($pdo);
    $year = (int)date('Y');
    $n = (int)$pdo->query("SELECT COUNT(*) FROM offers WHERE nr LIKE 'AN-$year-%'")->fetchColumn() + 1;
    $pdo->prepare('INSERT INTO offers (nr, token, org, contact, email, phone, title, intro, item, unit_cents, dates, valid_until, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([sprintf('AN-%d-%03d', $year, $n), bin2hex(random_bytes(16)), $f['org'], $f['contact'], $f['email'], $f['phone'], $f['title'], $f['intro'], $f['item'],
            (int)$f['unit_cents'], json_encode(array_values($f['dates']), JSON_UNESCAPED_UNICODE), $f['valid_until'], 'offen', date('c')]);
    return (int)$pdo->lastInsertId();
}

/** Fertige WhatsApp-Nachricht mit dem Angebotslink. */
function forma_offer_whatsapp(array $o): string
{
    $first = forma_offer_first($o);
    $days = implode(' und ', array_map(fn($d) => date('j.n.', strtotime($d['date'])), forma_offer_dates($o)));
    return ($first !== '' ? "Liebe $first" : 'Hallo') . ", hier ist mein unverbindliches Angebot für " . $o['title'] . " ($days):\n\n" . forma_offer_link($o)
        . "\n\nTrag dort einfach ein, wie viele Kinder pro Termin dabei sind, dann siehst du gleich den Gesamtbetrag und kannst mit einem Klick bestätigen.\n\nBis bald, Vivien";
}

/** Die Gruppe bestätigt: Anzahl pro Termin speichern, Kalender, Vorabrechnung, Info an Vivien. */
function forma_offer_confirm(PDO $pdo, array $o, array $kids, string $note, array $bill = []): bool
{
    if ($o['status'] !== 'offen') return false;
    $dates = forma_offer_dates($o);
    $sum = 0;
    foreach ($dates as $i => &$d) {
        $d['kids'] = max(0, min(FORMA_OFFER_MAX_KIDS, (int)($kids[$i] ?? 0)));
        $d['final'] = null;
        $sum += $d['kids'];
    }
    unset($d);
    if ($sum === 0) return false;
    if (trim((string)($bill['name'] ?? '')) === '' || trim((string)($bill['address'] ?? '')) === '') return false;
    $st = $pdo->prepare("UPDATE offers SET status = 'bestaetigt', dates = ?, note = ?, confirmed_at = ?, bill_to = ?, bill_name = ?, bill_attn = ?, bill_address = ?, bill_email = ? WHERE id = ? AND status = 'offen'");
    $st->execute([json_encode($dates, JSON_UNESCAPED_UNICODE), $note, date('c'), (string)($bill['to'] ?? ''), trim((string)$bill['name']), trim((string)($bill['attn'] ?? '')),
        trim((string)$bill['address']), strtolower(trim((string)($bill['email'] ?? ''))), (int)$o['id']]);
    if ($st->rowCount() === 0) return false;
    $o = forma_offer_get($pdo, (int)$o['id']);
    $lines = implode("\n", array_map(fn($d) => '– ' . forma_offer_day($d['date']) . ': ' . (int)$d['kids'] . ' Kinder', $dates));
    forma_mail(FORMA_NOTIFY_EMAIL, 'Angebot bestätigt: ' . $o['org'] . ' (' . $sum . ' Kinder, ' . forma_euro(forma_offer_total($o)) . ')',
        ($o['contact'] !== '' ? $o['contact'] . ' hat' : 'Die Gruppe hat') . " das Angebot {$o['nr']} bestätigt.\n\n$lines\n\nGesamt: " . forma_euro(forma_offer_total($o))
        . ($note !== '' ? "\n\nNachricht: $note" : '') . "\n\nRechnung an:\n" . implode("\n", array_filter(array_slice(forma_offer_bill($o), 0, 3))) . "\n\nDie Termine stehen jetzt in deinem Kalender. Die Vorabrechnung ist " . (trim((string)$o['email']) !== '' ? 'per Mail rausgegangen' : 'auf der Angebotsseite (keine E-Mail-Adresse hinterlegt)') . ".\n"
        . 'Angebote: ' . forma_site_url() . "/verwaltung/?view=angebote\n");
    forma_offer_send_vorab($pdo, (int)$o['id'], false);
    return true;
}

/** Vorabrechnung als Rechnungs-Array für forma_invoice_pdf() (doc = vorab, ohne Rechnungsnummer). */
function forma_offer_vorab(array $o): array
{
    $gross = forma_offer_total($o);
    $net = (int)round($gross / 1.2);
    $first = min(array_map(fn($d) => $d['date'], forma_offer_dates($o)) ?: [date('Y-m-d')]);
    return ['doc' => 'vorab', 'kind' => 'angebot', 'nr' => $o['nr'], 'issued_at' => substr((string)($o['confirmed_at'] ?: date('c')), 0, 10),
        'service_date' => implode(', ', array_map(fn($d) => date('d.m.Y', strtotime($d['date'])), forma_offer_dates($o))),
        'name' => forma_offer_bill($o)[0], 'email' => forma_offer_bill($o)[2], 'address' => forma_offer_bill($o)[1], 'country' => 'AT',
        'items' => json_encode(forma_offer_items($o), JSON_UNESCAPED_UNICODE), 'vat_rate' => 20.0, 'vat_note' => '',
        'pay_note' => "Noch nicht bezahlt. Bitte bar vor Ort bezahlen oder online über den QR-Code bzw. diesen Link:\n" . forma_offer_pay_url($o) . "\nDie fertige Rechnung kommt nach der Zahlung.",
        'pay_qr' => forma_offer_pay_url($o), 'first_date' => $first,
        'net_cents' => $net, 'vat_cents' => $gross - $net, 'gross_cents' => $gross];
}

/** Rechnungspositionen: ein Termin = eine Zeile. */
function forma_offer_items(array $o): array
{
    $out = [];
    foreach (forma_offer_dates($o) as $d) {
        $n = forma_offer_bill_kids($d);
        if ($n <= 0) continue;
        $out[] = ['text' => $o['title'] . ' · ' . date('d.m.Y', strtotime($d['date'])),
            'detail' => implode(' · ', array_filter([forma_offer_when($d), 'pro Kind 1 ' . $o['item'] . ' inkl. Farben und Fertigstellung'])), 'qty' => $n, 'unit' => (int)$o['unit_cents']];
    }
    return $out;
}

/** Vorabrechnung (neu) an die Gruppe mailen. */
function forma_offer_send_vorab(PDO $pdo, int $id, bool $again): bool
{
    $o = forma_offer_get($pdo, $id);
    if (!$o || $o['status'] !== 'bestaetigt') return false;
    $pdo->prepare('UPDATE offers SET vorab_sent = ? WHERE id = ?')->execute([date('c'), $id]);
    $to = array_values(array_unique(array_filter([strtolower(trim((string)$o['email'])), forma_offer_bill($o)[2]])));
    if (!$to) return false;
    $first = forma_offer_first($o);
    $lines = implode("\n", array_map(fn($d) => '– ' . forma_offer_day($d['date']) . (forma_offer_when($d) !== '' ? ', ' . forma_offer_when($d) : '') . ': ' . forma_offer_bill_kids($d) . ' Kinder', forma_offer_dates($o)));
    $pdf = forma_invoice_pdf(forma_offer_vorab($o));
    $ok = false;
    foreach ($to as $addr) $ok = forma_mail($addr, ($again ? 'Aktualisierte ' : 'Deine ') . 'Vorabrechnung für ' . $o['title'],
        ($first !== '' ? "Liebe $first" : 'Hallo') . ",\n\n" . ($again ? "hier ist die aktualisierte Vorabrechnung mit der richtigen Anzahl Kinder.\n\n" : "danke für deine Bestätigung, ich freu mich schon sehr auf euch!\n\n")
        . "$lines\n\nGesamt: " . forma_euro(forma_offer_total($o)) . " (inkl. 20 % USt.)\n\n"
        . "Im Anhang findest du die Vorabrechnung. Sie ist noch nicht bezahlt: Ihr könnt bar vor Ort bezahlen oder online über den QR-Code auf der Vorabrechnung bzw. über diesen Link:\n"
        . forma_offer_pay_url($o) . "\n\nSobald bezahlt ist, bekommst du die fertige Rechnung. Ändert sich die Anzahl noch, sag mir einfach Bescheid.\n\n"
        . "Alle Details findest du jederzeit hier: " . forma_offer_link($o) . "\n\nBis bald,\nVivien\n",
        null, [['Vorabrechnung_' . $o['nr'] . '.pdf', $pdf, 'application/pdf']]) || $ok;
    return $ok;
}

/** Vivien trägt die tatsächliche Anzahl Kinder ein (vor der Zahlung). */
function forma_offer_set_final(PDO $pdo, int $id, array $final): bool
{
    $o = forma_offer_get($pdo, $id);
    if (!$o || $o['status'] !== 'bestaetigt') return false;
    $dates = forma_offer_dates($o);
    foreach ($dates as $i => &$d) {
        $v = trim((string)($final[$i] ?? ''));
        $d['final'] = $v === '' ? null : max(0, min(FORMA_OFFER_MAX_KIDS, (int)$v));
    }
    unset($d);
    // Eine laufende Stripe-Bezahlseite hätte noch den alten Betrag: neu anlegen lassen.
    $pdo->prepare("UPDATE offers SET dates = ?, stripe_session = '' WHERE id = ?")->execute([json_encode($dates, JSON_UNESCAPED_UNICODE), $id]);
    return true;
}

/** Bar bezahlt → fertige Rechnung. */
function forma_offer_mark_cash(PDO $pdo, int $id): bool
{
    $o = forma_offer_get($pdo, $id);
    if (!$o || forma_offer_total($o) <= 0) return false;
    $st = $pdo->prepare("UPDATE offers SET status = 'bezahlt', pay_method = 'bar', paid_at = ? WHERE id = ? AND status = 'bestaetigt'");
    $st->execute([date('c'), $id]);
    if ($st->rowCount() === 0) return false;
    forma_invoice_for($pdo, 'angebot', $id);
    forma_offer_copy_invoice($pdo, $o);
    return true;
}

/** Online-Zahlung bei Stripe nachsehen. */
function forma_offer_check(PDO $pdo, array $o): string
{
    if ($o['status'] !== 'bestaetigt' || $o['stripe_session'] === '' || !forma_stripe_key()) return (string)$o['status'];
    $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$o['stripe_session']));
    if (($s['payment_status'] ?? '') === 'paid') {
        $st = $pdo->prepare("UPDATE offers SET status = 'bezahlt', pay_method = 'online', paid_at = ? WHERE id = ? AND status = 'bestaetigt'");
        $st->execute([date('c'), (int)$o['id']]);
        if ($st->rowCount() > 0) {
            forma_mail(FORMA_NOTIFY_EMAIL, 'Bezahlt: Angebot ' . $o['org'] . ' (' . forma_euro(forma_offer_total($o)) . ')',
                "Das Angebot {$o['nr']} ({$o['org']}) ist online über Stripe bezahlt.\n\nDie fertige Rechnung ist automatisch rausgegangen, eine Kopie bekommst du gleich.\nAngebote: " . forma_site_url() . "/verwaltung/?view=angebote\n");
            forma_invoice_for($pdo, 'angebot', (int)$o['id']);
            forma_offer_copy_invoice($pdo, $o);
        }
        return 'bezahlt';
    }
    if (($s['status'] ?? '') === 'expired') {
        $pdo->prepare("UPDATE offers SET stripe_session = '' WHERE id = ? AND status = 'bestaetigt'")->execute([(int)$o['id']]);
    }
    return 'bestaetigt';
}

/** Rechnungsdaten für forma_invoice_source('angebot'). */
function forma_offer_invoice_source(PDO $pdo, int $id): ?array
{
    $o = forma_offer_get($pdo, $id);
    if (!$o || $o['status'] !== 'bezahlt' || forma_offer_total($o) <= 0) return null;
    $bar = $o['pay_method'] === 'bar';
    [$bn, $ba, $be] = forma_offer_bill($o);
    return ['name' => $bn, 'email' => $be, 'session' => '', 'address' => $ba,
        'service' => implode(', ', array_map(fn($d) => date('d.m.Y', strtotime($d['date'])), array_filter(forma_offer_dates($o), fn($d) => forma_offer_bill_kids($d) > 0))),
        'items' => forma_offer_items($o), 'gross' => forma_offer_total($o), 'paid' => $o['paid_at'], 'type' => 'Gruppe',
        'pay_note' => $bar ? 'Der Betrag wurde am ' . date('d.m.Y', strtotime((string)$o['paid_at'])) . ' bar bezahlt.' : 'Der Betrag wurde am ' . date('d.m.Y', strtotime((string)$o['paid_at'])) . ' online bezahlt (Zahlung über Stripe).'];
}

/** Termine bestätigter (und offener) Angebote für Kalender und Belegt-Prüfung. */
function forma_offer_events(PDO $pdo, string $from, string $to, bool $withOpen = true): array
{
    $out = [];
    try {
        forma_offer_schema($pdo);
        foreach ($pdo->query("SELECT * FROM offers WHERE status IN ('offen','bestaetigt','bezahlt')")->fetchAll() as $o) {
            if ($o['status'] === 'offen' && !$withOpen) continue;
            foreach (forma_offer_dates($o) as $d) {
                if ($d['date'] < $from || $d['date'] > $to) continue;
                $out[] = ['date' => $d['date'], 'from' => (string)($d['from'] ?? ''), 'to' => (string)($d['to'] ?? ''), 'kids' => forma_offer_bill_kids($d), 'place' => (string)($d['place'] ?? ''), 'o' => $o];
            }
        }
    } catch (Throwable $e) {
        error_log('FORMA offer events: ' . $e->getMessage());
    }
    return $out;
}

/** QR-Code als Matrix aus '0'/'1'-Zeilen (für das PDF). */
function forma_qr_matrix(string $text): array
{
    if (!class_exists('QRcode', false)) require_once __DIR__ . '/vendor/phpqrcode/phpqrcode.php';
    return QRcode::text($text, false, QR_ECLEVEL_M, 1, 0);
}

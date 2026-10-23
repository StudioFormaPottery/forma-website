<?php
// Automatische Rechnungen nach Online-Zahlung (Kurse, Private Pottery, Templates).
// Wird von lib.php eingebunden. Eigener Nummernkreis für Online-Verkäufe, lückenlos pro Jahr.
declare(strict_types=1);

const FORMA_INVOICE_COMPANY = [
    'firma' => 'Studio Forma – Kreativwerkstatt',
    'name' => 'Vivien Malonga',
    'street' => 'Silvrettastraße 58',
    'city' => '6562 Mathon',
    'country' => 'Österreich',
    'uid' => 'ATU83602825',
    'phone' => '+43 677 63 90 70 80',
    'email' => 'hello@forma-pottery.com',
    'web' => 'www.forma-pottery.com',
];

/** Normalsteuersätze der EU-Länder (Stand 2026, bei Änderungen hier anpassen). */
const FORMA_EU_VAT = [
    'AT' => 20, 'BE' => 21, 'BG' => 20, 'HR' => 25, 'CY' => 19, 'CZ' => 21, 'DK' => 25, 'EE' => 24, 'FI' => 25.5,
    'FR' => 20, 'DE' => 19, 'GR' => 24, 'HU' => 27, 'IE' => 23, 'IT' => 22, 'LV' => 21, 'LT' => 21, 'LU' => 17,
    'MT' => 18, 'NL' => 21, 'PL' => 23, 'PT' => 23, 'RO' => 21, 'SK' => 23, 'SI' => 22, 'ES' => 21, 'SE' => 25,
];

function forma_invoice_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nr TEXT NOT NULL UNIQUE,
        year INTEGER NOT NULL,
        seq INTEGER NOT NULL,
        kind TEXT NOT NULL,
        ref_id INTEGER NOT NULL,
        issued_at TEXT NOT NULL,
        service_date TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        address TEXT NOT NULL DEFAULT \'\',
        country TEXT NOT NULL DEFAULT \'AT\',
        items TEXT NOT NULL,
        vat_rate REAL NOT NULL,
        vat_note TEXT NOT NULL DEFAULT \'\',
        net_cents INTEGER NOT NULL,
        vat_cents INTEGER NOT NULL,
        gross_cents INTEGER NOT NULL,
        token TEXT NOT NULL,
        mailed INTEGER NOT NULL DEFAULT 0,
        synced INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        UNIQUE(kind, ref_id)
    )');
    forma_add_column($pdo, 'invoices', 'pay_note', "TEXT NOT NULL DEFAULT ''");
}

/** Nummernformat: Präfix + Jahr + laufende Nummer, z. B. „FO-2026-001“. Präfix und Startnummer in den Einstellungen. */
function forma_invoice_next(PDO $pdo, int $year): array
{
    forma_invoice_schema($pdo);
    $prefix = forma_setting($pdo, 'invoice_prefix') ?? '';
    $start = (int)(forma_setting($pdo, 'invoice_start_' . $year) ?? 1);
    $stmt = $pdo->prepare('SELECT MAX(seq) FROM invoices WHERE year = ?');
    $stmt->execute([$year]);
    $seq = max($start, (int)$stmt->fetchColumn() + 1);
    // Ohne Kürzel: fortlaufend wie in Viviens Buchhaltung (01, 02 … 19, 20 …), pro Jahr neu.
    return [$seq, $prefix === '' ? sprintf('%02d', $seq) : sprintf('%s-%d-%03d', $prefix, $year, $seq)];
}

/** Steuersatz: Kurse finden in Mathon statt → immer AT 20 %. Digitale Templates: Wohnsitz der Käuferin. */
function forma_invoice_vat(PDO $pdo, string $kind, string $country): array
{
    $country = strtoupper($country ?: 'AT');
    if ($kind !== 'template' || $country === 'AT') {
        return [20.0, ''];
    }
    if (!isset(FORMA_EU_VAT[$country])) {
        return [0.0, 'Leistungsort im Drittland, in Österreich nicht steuerbar (§ 3a Abs. 5 Z 3 UStG).'];
    }
    // Unter der EU-weiten Lieferschwelle von 10.000 € gilt österreichische USt.; mit OSS-Registrierung der Satz des Wohnsitzlandes.
    if (forma_setting($pdo, 'oss_active') === '1') {
        return [(float)FORMA_EU_VAT[$country], 'Umsatzsteuer des Wohnsitzlandes der Käuferin (' . $country . '), Abfuhr über OSS.'];
    }
    return [20.0, ''];
}

/** Rechnungsdaten aus der Buchung zusammenstellen. Gibt null zurück, wenn nichts zu verrechnen ist. */
function forma_invoice_source(PDO $pdo, string $kind, int $refId): ?array
{
    if ($kind === 'kurs') {
        $st = $pdo->prepare('SELECT b.*, c.title, c.date_text, c.start_date, c.price_cents AS unit FROM bookings b JOIN courses c ON c.id = b.course_id WHERE b.id = ?');
        $st->execute([$refId]);
        $b = $st->fetch();
        if (!$b || !$b['paid_at']) return null;
        // Alter Gutschein (Papier, schon früher bezahlt, nicht über das System verrechnet): Die Leistung wird
        // jetzt bei der Einlösung verrechnet (Mehrzweckgutschein, USt. bei Einlösung), bezahlt mit dem Gutschein.
        $old = null;
        if ((int)$b['amount_cents'] <= 0 && (int)$b['voucher_cents'] > 0 && !empty($b['voucher_id'])) {
            $vs = $pdo->prepare("SELECT nr, kind, paid_at FROM vouchers WHERE id = ? AND kind = 'alt'");
            $vs->execute([(int)$b['voucher_id']]);
            $old = $vs->fetch() ?: null;
        }
        if ((int)$b['amount_cents'] <= 0 && !$old) return null;
        if ($old) {
            $vc = (int)$b['voucher_cents'];
            return ['name' => $b['name'], 'email' => $b['email'], 'session' => '', 'address' => (string)($b['bill_address'] ?? ''), 'service' => date('d.m.Y', strtotime($b['start_date'])),
                'items' => [['text' => $b['title'], 'detail' => $b['date_text'] . ' · FORMA Studio, Mathon', 'qty' => (int)$b['persons'], 'unit' => (int)round($vc / max(1, (int)$b['persons']))]],
                'gross' => $vc, 'paid' => $b['paid_at'], 'type' => 'Kurs',
                'pay_note' => 'Der Betrag ist bereits bezahlt: eingelöst mit deinem FORMA Gutschein Nr. ' . $old['nr'] . '.'];
        }
        if (($b['manual_pay'] ?? '') === 'bar') {
            // Von Vivien eingetragen und bar bezahlt: Betrag so, wie sie ihn eingegeben hat.
            return ['name' => $b['name'], 'email' => $b['email'], 'session' => '', 'address' => (string)($b['bill_address'] ?? ''), 'service' => date('d.m.Y', strtotime($b['start_date'])),
                'items' => [['text' => $b['title'], 'detail' => $b['date_text'] . ' · FORMA Studio, Mathon', 'qty' => 1, 'unit' => (int)$b['amount_cents']]],
                'gross' => (int)$b['amount_cents'], 'paid' => $b['paid_at'], 'type' => 'Kurs', 'pay_note' => 'Der Betrag wurde bar im Atelier bezahlt.'];
        }
        $bSlots = forma_is_bemalen($b) ? forma_extras_slots($b['extras'] ?? '') : [];
        $when = $bSlots ? forma_date_without_time((string)$b['date_text']) . ' · ' . forma_slots_text($bSlots) : $b['date_text'];
        $items = [['text' => $b['title'], 'detail' => $when . ' · FORMA Studio, Mathon', 'qty' => (int)$b['persons'], 'unit' => (int)$b['unit']]];
        $pieces = forma_extras_pieces($b['extras'] ?? '', $b);
        $atelier = forma_is_atelier($b);
        if ($atelier) $items = [];
        if (is_array($pieces)) {
            foreach ($pieces as $p) $items[] = $atelier
                ? ['text' => $p['name'], 'detail' => date('d.m.Y', strtotime($b['start_date'])) . ' · Werkstattplatz inkl. Ton und Werkzeug', 'qty' => (int)$p['qty'], 'unit' => (int)$p['cents']]
                : ['text' => $p['name'] . ' zum Bemalen', 'detail' => '', 'qty' => (int)$p['qty'], 'unit' => (int)$p['cents']];
        }
        if (!$atelier && count($items) > 1) $items[0]['text'] .= ' · Atelierbeitrag';
        if ($vi = forma_invoice_voucher_item($pdo, $b)) $items[] = $vi;
        return ['name' => $b['name'], 'email' => $b['email'], 'session' => $b['stripe_session'], 'service' => date('d.m.Y', strtotime($b['start_date'])), 'items' => $items, 'gross' => (int)$b['amount_cents'], 'paid' => $b['paid_at'], 'type' => 'Kurs'];
    }
    if ($kind === 'private') {
        $st = $pdo->prepare("SELECT * FROM private_bookings WHERE id = ? AND status = 'bezahlt'");
        $st->execute([$refId]);
        $b = $st->fetch();
        if (!$b || (int)$b['amount_cents'] <= 0) return null;
        $team = str_starts_with((string)$b['project'], 'Teambuilding/Event:');
        $ss = function_exists('forma_private_sessions') ? forma_private_sessions($pdo, (int)$b['id']) : [];
        $service = $ss ? implode(', ', array_map(fn($x) => date('d.m.Y', strtotime((string)$x['date'])), $ss)) : 'nach Vereinbarung';
        $src = ['name' => $b['name'], 'email' => $b['email'], 'session' => $b['stripe_session'], 'service' => $service,
            'items' => $team ? [['text' => str_contains((string)$b['project'], 'Wochenende') ? 'Teambuilding Töpfer-Wochenende (2 × 3 Stunden Töpfern)' : 'Teambuilding Halbtages-Event (4 Stunden)', 'detail' => 'inkl. Material, Fertigstellung und ein Werkstück pro Person' . (str_contains((string)$b['project'], 'Wochenende') ? ' · ohne Übernachtung (separate Rechnung Heindalishof)' : ''), 'qty' => max(1, (int)$b['persons']), 'unit' => (int)round((int)$b['amount_cents'] / max(1, (int)$b['persons']))]] : [floor((float)$b['hours']) == (float)$b['hours']
                ? ['text' => ($team ? 'Teambuilding/Event' : 'Private Pottery – Handbuilding') . ' (Stunden)', 'detail' => (int)$b['persons'] > 1 ? $b['persons'] . ' Personen, inkl. Material und Fertigstellung' : 'inkl. Material und Fertigstellung', 'qty' => (int)$b['hours'] * max(1, (int)$b['persons']), 'unit' => FORMA_PRIVATE_HOUR]
                : ['text' => ($team ? 'Teambuilding/Event' : 'Private Pottery – Handbuilding') . ' (' . forma_hours_text((float)$b['hours']) . ')', 'detail' => 'inkl. Material und Fertigstellung', 'qty' => 1, 'unit' => (int)$b['amount_cents']]],
            'gross' => (int)$b['amount_cents'], 'paid' => $b['paid_at'], 'type' => 'Private Pottery'];
        if ($vi = forma_invoice_voucher_item($pdo, $b)) {
            $src['items'][] = $vi;
            if (count($src['items']) === 2 && (int)$src['items'][0]['qty'] > 0) {
                // Positionen müssen den Rechnungsbetrag ergeben: Preis vor Gutschein = Betrag + Gutschein
                $full = (int)$b['amount_cents'] + (int)$b['voucher_cents'];
                $src['items'][0]['unit'] = (int)round($full / (int)$src['items'][0]['qty']);
            }
        }
        return $src;
    }
    if ($kind === 'private_plus') {
        // Verlängerung einer Private-Pottery-Buchung: eigene Rechnung über die zusätzlichen Stunden
        $st = $pdo->prepare("SELECT x.*, p.name, p.email, p.persons FROM private_extensions x JOIN private_bookings p ON p.id = x.private_id WHERE x.id = ? AND x.status = 'bezahlt'");
        $st->execute([$refId]);
        $x = $st->fetch();
        if (!$x || (int)$x['amount_cents'] <= 0) return null;
        $whole = floor((float)$x['hours']) == (float)$x['hours'];
        return ['name' => $x['name'], 'email' => $x['email'], 'session' => $x['stripe_session'], 'service' => 'nach Vereinbarung',
            'items' => [$whole
                ? ['text' => 'Private Pottery – Handbuilding (Stunden, Verlängerung)', 'detail' => 'zusätzliche Stunden zu deinem Projekt, inkl. Ton und Fertigstellung', 'qty' => (int)$x['hours'], 'unit' => FORMA_PRIVATE_HOUR]
                : ['text' => 'Private Pottery – Handbuilding (' . forma_hours_text((float)$x['hours']) . ', Verlängerung)', 'detail' => 'zusätzliche Stunden zu deinem Projekt, inkl. Ton und Fertigstellung', 'qty' => 1, 'unit' => (int)$x['amount_cents']]],
            'gross' => (int)$x['amount_cents'], 'paid' => $x['paid_at'], 'type' => 'Private Pottery'];
    }
    if ($kind === 'kasse') {
        // Atelier-Kasse Pottery bemalen: Workshop-Leistung, bar oder über den Bezahl-Link bezahlt.
        $st = $pdo->prepare("SELECT * FROM kasse_sales WHERE id = ? AND status = 'bezahlt'");
        $st->execute([$refId]);
        $k = $st->fetch();
        if (!$k || (int)$k['amount_cents'] <= 0) return null;
        $day = date('d.m.Y', strtotime((string)($k['paid_at'] ?: $k['created_at'])));
        return ['name' => $k['name'], 'email' => (string)$k['email'], 'session' => $k['method'] === 'bar' ? '' : (string)$k['stripe_session'], 'service' => $day,
            'items' => forma_kasse_invoice_items(json_decode((string)$k['items'], true) ?: []),
            'gross' => (int)$k['amount_cents'], 'paid' => $k['paid_at'], 'type' => 'Pottery bemalen',
            'pay_note' => $k['method'] === 'bar' ? 'Der Betrag wurde am ' . $day . ' bar im Atelier bezahlt.' : ''];
    }
    if ($kind === 'gutschein') {
        $st = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? AND status IN ('bezahlt','eingeloest') AND kind = 'kauf'");
        $st->execute([$refId]);
        $v = $st->fetch();
        if (!$v || (int)$v['amount_cents'] <= 0) return null;
        return ['name' => $v['buyer_name'], 'email' => $v['buyer_email'], 'session' => $v['stripe_session'], 'service' => date('d.m.Y', strtotime((string)$v['paid_at'])),
            'items' => [['text' => 'Gutschein ' . $v['nr'] . ' · ' . forma_voucher_title($v), 'detail' => 'Wertgutschein, einlösbar für alle Kurse im FORMA Atelier', 'qty' => 1, 'unit' => (int)$v['amount_cents']]],
            'gross' => (int)$v['amount_cents'], 'paid' => $v['paid_at'], 'type' => 'Gutschein',
            'pay_note' => ($v['pay_method'] ?? '') === 'bar' ? 'Der Betrag wurde bar im Atelier bezahlt.' : ''];
    }
    if ($kind === 'template') {
        $st = $pdo->prepare("SELECT * FROM template_orders WHERE id = ? AND status = 'bezahlt'");
        $st->execute([$refId]);
        $o = $st->fetch();
        if (!$o || (int)$o['amount_cents'] <= 0) return null;
        return ['name' => $o['name'], 'email' => $o['email'], 'session' => $o['stripe_session'], 'service' => date('d.m.Y', strtotime((string)$o['paid_at'])),
            'items' => [['text' => 'Pottery-Template „' . $o['title'] . '“', 'detail' => 'Digitaler Download (PDF, Deutsch & Englisch)', 'qty' => 1, 'unit' => (int)$o['amount_cents']]],
            'gross' => (int)$o['amount_cents'], 'paid' => $o['paid_at'], 'type' => 'Template'];
    }
    return null;
}

/** Legt die Rechnung an (einmalig pro Buchung), erzeugt das PDF und verschickt es. Fehler stören die Buchung nie. */
/** Storno-Rechnung (Gutschrift) zu einer bereits ausgestellten Rechnung, z. B. wenn ein Kurs nicht stattfindet. */
function forma_invoice_storno(PDO $pdo, string $kind, int $refId, int $cents): void
{
    forma_invoice_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM invoices WHERE kind = ? AND ref_id = ? ORDER BY id LIMIT 1');
    $st->execute([$kind, $refId]);
    $orig = $st->fetch();
    if (!$orig || $cents <= 0) return;
    $ex = $pdo->prepare('SELECT id FROM invoices WHERE kind = ? AND ref_id = ?');
    $ex->execute([$kind . '_storno', $refId]);
    if ($ex->fetchColumn()) return;
    $rate = (float)$orig['vat_rate'];
    $gross = -$cents;
    $net = (int)round($gross / (1 + $rate / 100));
    $vat = $gross - $net;
    $items = json_decode((string)$orig['items'], true) ?: [];
    $first = $items[0]['text'] ?? 'Leistung';
    $year = (int)date('Y');
    $pdo->exec('BEGIN IMMEDIATE');
    [$seq, $nr] = forma_invoice_next($pdo, $year);
    $pdo->prepare('INSERT INTO invoices (nr, year, seq, kind, ref_id, issued_at, service_date, name, email, address, country, items, vat_rate, vat_note, net_cents, vat_cents, gross_cents, token, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$nr, $year, $seq, $kind . '_storno', $refId, date('Y-m-d'), $orig['service_date'], $orig['name'], $orig['email'], $orig['address'], $orig['country'],
            json_encode([['text' => 'Storno zu Rechnung ' . $orig['nr'], 'detail' => $first . ' · Kurs hat nicht stattgefunden, Betrag erstattet', 'qty' => 1, 'unit' => $gross]], JSON_UNESCAPED_UNICODE),
            $rate, trim('Stornorechnung zu Rechnung ' . $orig['nr'] . ' vom ' . date('d.m.Y', strtotime((string)$orig['issued_at'])) . '. ' . (string)$orig['vat_note']), $net, $vat, $gross, bin2hex(random_bytes(16)), date('c')]);
    $id = (int)$pdo->lastInsertId();
    $pdo->exec('COMMIT');
    forma_invoice_send($pdo, $id);
    forma_sheet_sync($pdo);
}

/** Rechnungsposition für einen eingelösten Gutschein (negativ), damit die Summe den bezahlten Betrag ergibt. */
function forma_invoice_voucher_item(PDO $pdo, array $b): ?array
{
    $vc = (int)($b['voucher_cents'] ?? 0);
    if ($vc <= 0 || empty($b['voucher_id'])) return null;
    $st = $pdo->prepare('SELECT nr, kind FROM vouchers WHERE id = ?');
    $st->execute([(int)$b['voucher_id']]);
    $v = $st->fetch() ?: ['nr' => '', 'kind' => 'kauf'];
    return ['text' => ($v['kind'] === 'kauf' ? 'Gutschein ' : 'Gutschrift ') . $v['nr'] . ' eingelöst',
        'detail' => $v['kind'] === 'kauf' ? 'bereits beim Gutscheinkauf bezahlt und verrechnet' : 'Preisnachlass', 'qty' => 1, 'unit' => -$vc];
}

function forma_invoice_for(PDO $pdo, string $kind, int $refId): void
{
    try {
        forma_invoice_schema($pdo);
        $exists = $pdo->prepare('SELECT id FROM invoices WHERE kind = ? AND ref_id = ?');
        $exists->execute([$kind, $refId]);
        if ($exists->fetchColumn()) return;
        $src = forma_invoice_source($pdo, $kind, $refId);
        if (!$src) return;

        // Rechnungsadresse und Land aus Stripe (Checkout fragt die Adresse ab)
        $address = (string)($src['address'] ?? ''); $country = 'AT'; $name = $src['name']; $email = (string)$src['email'];
        if ($src['session'] !== '' && forma_stripe_key()) {
            try {
                $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($src['session']));
                $cd = $s['customer_details'] ?? [];
                $a = $cd['address'] ?? [];
                if (!empty($cd['name'])) $name = (string)$cd['name'];
                if ($email === '' && !empty($cd['email'])) $email = (string)$cd['email'];
                $country = strtoupper((string)($a['country'] ?? 'AT')) ?: 'AT';
                $lines = array_filter([(string)($a['line1'] ?? ''), (string)($a['line2'] ?? ''), trim(($a['postal_code'] ?? '') . ' ' . ($a['city'] ?? '')), $country !== 'AT' ? $country : '']);
                $address = implode("\n", $lines);
            } catch (Throwable $e) {
                error_log('FORMA invoice stripe: ' . $e->getMessage());
            }
        }
        [$rate, $note] = forma_invoice_vat($pdo, $kind, $country);
        $gross = (int)$src['gross'];
        $net = (int)round($gross / (1 + $rate / 100));
        $vat = $gross - $net;
        $year = (int)date('Y');
        if (!empty($GLOBALS['FORMA_TEST'])) {
            // Testbuchung: Rechnung nur zeigen und mailen, keine Nummer verbrauchen, nichts speichern.
            forma_invoice_deliver(['nr' => 'TEST-' . $year . '-000', 'issued_at' => date('Y-m-d'), 'service_date' => $src['service'], 'name' => $name, 'email' => $email,
                'address' => $address, 'country' => $country, 'items' => json_encode($src['items'], JSON_UNESCAPED_UNICODE), 'vat_rate' => $rate,
                'vat_note' => trim($note . ' TESTBUCHUNG – keine gültige Rechnung.'), 'pay_note' => (string)($src['pay_note'] ?? ''), 'net_cents' => $net, 'vat_cents' => $vat, 'gross_cents' => $gross]);
            return;
        }

        $pdo->exec('BEGIN IMMEDIATE');
        [$seq, $nr] = forma_invoice_next($pdo, $year);
        $pdo->prepare('INSERT INTO invoices (nr, year, seq, kind, ref_id, issued_at, service_date, name, email, address, country, items, vat_rate, vat_note, net_cents, vat_cents, gross_cents, token, created_at, pay_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$nr, $year, $seq, $kind, $refId, date('Y-m-d'), $src['service'], $name, $email, $address, $country,
                json_encode($src['items'], JSON_UNESCAPED_UNICODE), $rate, $note, $net, $vat, $gross, bin2hex(random_bytes(16)), date('c'), (string)($src['pay_note'] ?? '')]);
        $id = (int)$pdo->lastInsertId();
        $pdo->exec('COMMIT');

        forma_invoice_send($pdo, $id);
        forma_sheet_sync($pdo);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
        error_log('FORMA invoice: ' . $e->getMessage());
    }
}

function forma_invoice_get(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function forma_pdf_text(string $s): string
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
}

function forma_cents_de(int $c): string
{
    return number_format($c / 100, 2, ',', '.') . ' €';
}

/** PDF im FORMA-Rechnungsdesign: Kunde links, Logo Mitte, Rechnungsdaten rechts, Positionen, Summen, Fußzeile mit UID. */
function forma_invoice_pdf(array $inv): string
{
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
    $t = 'forma_pdf_text';
    $co = FORMA_INVOICE_COMPANY;
    $pdf = new FPDF('P', 'mm', 'A4');
    $fd = __DIR__ . '/vendor/fpdf/font/';
    $pdf->AddFont('Outfit', '', 'Outfit-Regular.json', $fd);
    $pdf->AddFont('Outfit', 'B', 'Outfit-Bold.json', $fd);
    $pdf->AddFont('Brico', 'B', 'BricolageGrotesque-Bold.json', $fd);
    $pdf->SetTitle($t('Rechnung ' . $inv['nr']));
    $pdf->SetAuthor($t($co['firma']));
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(18, 16, 18);
    $pdf->AddPage();
    $ink = [43, 42, 40]; $soft = [74, 71, 66]; $clay = [140, 44, 29]; $line = [201, 196, 188];

    // Kopf
    $pdf->SetTextColor(...$soft); $pdf->SetFont('Outfit', '', 7.5);
    $pdf->SetXY(18, 22); $pdf->Cell(60, 4, $t('RECHNUNG AN'));
    $pdf->SetTextColor(...$ink); $pdf->SetFont('Outfit', 'B', 10.5);
    $pdf->SetXY(18, 27); $pdf->MultiCell(62, 5, $t($inv['name']));
    $pdf->SetFont('Outfit', '', 9.5);
    $pdf->SetX(18); $pdf->MultiCell(62, 4.6, $t(trim($inv['address'] . "\n" . $inv['email'])));
    $logo = dirname(__DIR__) . '/img/forma-logo.png';
    if (is_file($logo)) $pdf->Image($logo, 92, 14, 26);
    $pdf->SetTextColor(...$clay); $pdf->SetFont('Brico', 'B', 20);
    $pdf->SetXY(122, 18); $pdf->Cell(70, 9, (int)$inv['gross_cents'] < 0 ? 'STORNORECHNUNG' : 'RECHNUNG', 0, 2, 'R');
    $meta = [['Rechnungsnr.', $inv['nr']], ['Rechnungsdatum', date('d.m.Y', strtotime($inv['issued_at']))], ['Leistungsdatum', $inv['service_date']]];
    $y = 30;
    foreach ($meta as [$k, $v]) {
        $pdf->SetTextColor(...$soft); $pdf->SetFont('Outfit', '', 8.5); $pdf->SetXY(122, $y); $pdf->Cell(32, 5, $t($k));
        $pdf->SetTextColor(...$ink); $pdf->SetFont('Outfit', 'B', 9); $pdf->SetXY(154, $y); $pdf->Cell(38, 5, $t($v), 0, 0, 'R');
        $y += 5.5;
    }

    // Positionen
    $y = 74;
    $pdf->SetDrawColor(...$ink); $pdf->SetLineWidth(.3); $pdf->Line(18, $y + 7, 192, $y + 7);
    $pdf->SetTextColor(...$soft); $pdf->SetFont('Outfit', 'B', 8.5);
    $cols = [[18, 12, 'Pos.', 'L'], [30, 96, 'Leistung', 'L'], [126, 18, 'Menge', 'R'], [144, 24, 'Einzelpreis', 'R'], [168, 24, 'Gesamt', 'R']];
    foreach ($cols as [$x, $w, $h, $al]) { $pdf->SetXY($x, $y); $pdf->Cell($w, 6, $t($h), 0, 0, $al); }
    $y += 10;
    $pdf->SetDrawColor(...$line); $pdf->SetLineWidth(.2);
    foreach (json_decode($inv['items'], true) as $i => $it) {
        $pdf->SetTextColor(...$ink); $pdf->SetFont('Outfit', '', 9.5);
        $pdf->SetXY(18, $y); $pdf->Cell(12, 5, (string)($i + 1));
        $pdf->SetFont('Outfit', 'B', 9.5); $pdf->SetXY(30, $y); $pdf->MultiCell(94, 5, $t($it['text']));
        $yy = $pdf->GetY();
        if (($it['detail'] ?? '') !== '') { $pdf->SetFont('Outfit', '', 8.5); $pdf->SetTextColor(...$soft); $pdf->SetX(30); $pdf->MultiCell(94, 4.4, $t($it['detail'])); $yy = $pdf->GetY(); }
        $pdf->SetTextColor(...$ink); $pdf->SetFont('Outfit', '', 9.5);
        $pdf->SetXY(126, $y); $pdf->Cell(18, 5, (string)$it['qty'], 0, 0, 'R');
        $pdf->SetXY(144, $y); $pdf->Cell(24, 5, $t(forma_cents_de((int)$it['unit'])), 0, 0, 'R');
        $pdf->SetXY(168, $y); $pdf->Cell(24, 5, $t(forma_cents_de((int)$it['unit'] * (int)$it['qty'])), 0, 0, 'R');
        $y = $yy + 3;
        $pdf->Line(18, $y - 1.5, 192, $y - 1.5);
    }
    $pdf->SetFont('Outfit', '', 8); $pdf->SetTextColor(...$soft); $pdf->SetXY(30, $y);
    $pdf->Cell(100, 4, $t('Alle Preise inklusive Umsatzsteuer.'));

    // Summen
    $y += 9;
    $rows = [['Gesamtbetrag netto', forma_cents_de((int)$inv['net_cents'])],
        [((float)$inv['vat_rate'] > 0 ? 'zzgl. ' . rtrim(rtrim(number_format((float)$inv['vat_rate'], 1, ',', ''), '0'), ',') . ' % USt.' : 'USt. 0 %'), forma_cents_de((int)$inv['vat_cents'])]];
    foreach ($rows as [$k, $v]) {
        $pdf->SetTextColor(...$ink); $pdf->SetFont('Outfit', '', 9.5);
        $pdf->SetXY(120, $y); $pdf->Cell(44, 5.5, $t($k)); $pdf->SetXY(164, $y); $pdf->Cell(28, 5.5, $t($v), 0, 0, 'R');
        $y += 6;
    }
    $pdf->SetDrawColor(...$ink); $pdf->SetLineWidth(.3); $pdf->Line(120, $y + 1, 192, $y + 1);
    $pdf->SetFont('Outfit', 'B', 11); $pdf->SetXY(120, $y + 2.5); $pdf->Cell(44, 7, $t('Gesamtbetrag brutto'));
    $pdf->SetXY(164, $y + 2.5); $pdf->Cell(28, 7, $t(forma_cents_de((int)$inv['gross_cents'])), 0, 0, 'R');
    $y += 20;

    // Hinweis
    $pdf->SetTextColor(...$soft); $pdf->SetFont('Outfit', '', 9.5);
    $paidLine = (int)$inv['gross_cents'] < 0
        ? 'Der Betrag wurde am ' . date('d.m.Y', strtotime($inv['issued_at'])) . ' auf das Zahlungsmittel zurückerstattet, mit dem bezahlt wurde.'
        : (($inv['pay_note'] ?? '') !== '' ? (string)$inv['pay_note'] : 'Der Betrag wurde am ' . date('d.m.Y', strtotime($inv['issued_at'])) . ' online bezahlt (Kartenzahlung über Stripe).');
    if ($inv['vat_note'] !== '') $paidLine .= "\n" . $inv['vat_note'];
    $pdf->SetXY(30, $y); $pdf->MultiCell(150, 5, $t($paidLine), 0, 'C');
    $pdf->SetTextColor(...$clay); $pdf->SetFont('Outfit', 'B', 10.5); $pdf->SetX(30); $pdf->Cell(150, 7, $t('Vielen Dank!'), 0, 1, 'C');

    // Fußzeile
    $fy = 258;
    $pdf->SetDrawColor(...$line); $pdf->SetLineWidth(.2); $pdf->Line(18, $fy, 192, $fy);
    $pdf->SetTextColor(...$soft); $pdf->SetFont('Outfit', 'B', 8.5);
    $pdf->SetXY(18, $fy + 4); $pdf->Cell(80, 4.4, $t('Kontakt'));
    $pdf->SetXY(112, $fy + 4); $pdf->Cell(80, 4.4, $t($co['name']), 0, 0, 'R');
    $pdf->SetFont('Outfit', '', 8.5);
    $pdf->SetXY(18, $fy + 8.6); $pdf->MultiCell(80, 4.4, $t("Tel. {$co['phone']}\n{$co['email']}\n{$co['web']}"));
    $pdf->SetXY(112, $fy + 8.6); $pdf->MultiCell(80, 4.4, $t("{$co['firma']}\n{$co['street']}, {$co['city']}\nUID: {$co['uid']}"), 0, 'R');
    return $pdf->Output('S');
}

function forma_invoice_mailtext(array $inv): string
{
    $first = forma_first_name($inv['name']);
    if ((int)$inv['gross_cents'] < 0) {
        return "Hallo $first,\n\nim Anhang findest du die Stornorechnung {$inv['nr']} für deine Unterlagen. Der Betrag von " . forma_cents_de(-(int)$inv['gross_cents']) . " wird dir zurückerstattet.\n\nBis bald,\nVivien\n";
    }
    $vg = forma_invoice_is_voucher($inv);
    $cash = forma_invoice_is_cash($inv);
    return "Hallo $first,\n\n" . ($vg ? 'für deine Unterlagen findest du im Anhang die Rechnung' : 'vielen Dank für deine Zahlung! Im Anhang findest du deine Rechnung') . " {$inv['nr']} als PDF.\n\n"
        . 'Rechnungsbetrag: ' . forma_cents_de((int)$inv['gross_cents']) . ($vg ? " (bereits mit deinem Gutschein bezahlt, du musst nichts mehr überweisen)\n\n" : ($cash ? " (bar im Atelier bezahlt)\n\n" : " (bereits bezahlt)\n\n"))
        . "Bewahre sie gern für deine Unterlagen auf. Bei Fragen antworte einfach auf diese E-Mail.\n\nBis bald,\nVivien\n";
}

/** Rechnung mit einem alten Gutschein bezahlt (kein neuer Zahlungseingang). */
function forma_invoice_is_voucher(array $inv): bool
{
    return str_contains((string)($inv['pay_note'] ?? ''), 'Gutschein');
}

/** Bar im Atelier bezahlt (Atelier-Kasse). */
function forma_invoice_is_cash(array $inv): bool
{
    return str_contains((string)($inv['pay_note'] ?? ''), ' bar ');
}

function forma_invoice_send(PDO $pdo, int $id): void
{
    $inv = forma_invoice_get($pdo, $id);
    if (!$inv || (int)$inv['mailed'] === 1) return;
    $pdo->prepare('UPDATE invoices SET mailed = 1 WHERE id = ?')->execute([$id]);
    forma_invoice_deliver($inv);
}

/** Schickt die Rechnung als PDF an die Kundin und an Vivien. */
function forma_invoice_deliver(array $inv): void
{
    $pdf = forma_invoice_pdf($inv);
    $file = 'Rechnung_' . $inv['nr'] . '.pdf';
    if (trim((string)$inv['email']) !== '') forma_mail($inv['email'], ((int)$inv['gross_cents'] < 0 ? 'Deine Stornorechnung ' : 'Deine Rechnung ') . $inv['nr'] . ' von FORMA', forma_invoice_mailtext($inv), null, [[$file, $pdf, 'application/pdf']]);
    forma_mail(FORMA_NOTIFY_EMAIL, ((int)$inv['gross_cents'] < 0 ? 'Stornorechnung ' : 'Rechnung ') . $inv['nr'] . ': ' . $inv['name'] . ' (' . forma_cents_de((int)$inv['gross_cents']) . ')',
        (forma_invoice_is_cash($inv) ? "Neue Rechnung aus der Atelier-Kasse (bar bezahlt, Geld liegt in der Kassa, nicht am Konto)." : "Neue Online-Rechnung für deine Buchhaltung.") . "\n\n" . (trim((string)$inv['email']) === '' ? "Ohne E-Mail-Adresse: Die Rechnung ging nur an dich, druck sie bei Bedarf aus oder gib sie der Kundin.\n\n" : '') . "Nummer: {$inv['nr']}\nDatum: " . date('d.m.Y', strtotime($inv['issued_at'])) . "\nKunde: {$inv['name']} ({$inv['email']}, {$inv['country']})\n"
        . (forma_invoice_is_voucher($inv) ? "Bezahlt mit altem Gutschein: kein neuer Zahlungseingang am Konto (das Geld kam schon beim Gutscheinkauf).\n" : '')
        . 'Brutto: ' . forma_cents_de((int)$inv['gross_cents']) . "\nNetto: " . forma_cents_de((int)$inv['net_cents']) . "\nUSt. {$inv['vat_rate']} %: " . forma_cents_de((int)$inv['vat_cents']) . "\n\n"
        . 'Alle Rechnungen: ' . forma_site_url() . "/verwaltung/?view=rechnungen\n", null, [[$file, $pdf, 'application/pdf']]);
}

// --- Google Sheets: Rechnung automatisch in die Buchhaltungsliste eintragen ------------------------
// Aktiv, sobald in der Verwaltung ein Google-Dienstkonto-Schlüssel (JSON) hinterlegt ist und die Tabelle
// für die E-Mail-Adresse des Dienstkontos freigegeben wurde. Der Schlüssel liegt nur im Datenordner.

function forma_google_key(): ?array
{
    $f = forma_data_dir() . '/google-key.json';
    if (!is_file($f)) return null;
    $k = json_decode((string)file_get_contents($f), true);
    return is_array($k) && !empty($k['private_key']) && !empty($k['client_email']) ? $k : null;
}

function forma_google_token(array $key): string
{
    $b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $now = time();
    $head = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = $b64(json_encode(['iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3000]));
    openssl_sign("$head.$claim", $sig, $key['private_key'], 'sha256WithRSAEncryption');
    $res = forma_http('POST', 'https://oauth2.googleapis.com/token', http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$head.$claim." . $b64($sig)]), ['Content-Type: application/x-www-form-urlencoded']);
    if (empty($res['access_token'])) throw new RuntimeException('Google-Anmeldung fehlgeschlagen');
    return (string)$res['access_token'];
}

function forma_http(string $method, string $url, ?string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$out, true) ?: [];
    if ($code >= 300) throw new RuntimeException("HTTP $code: " . substr((string)$out, 0, 300));
    return $data;
}

/** Trägt alle noch nicht übertragenen Rechnungen in die Blätter „Rechnungen“ und „Kurse & Leistungen“ ein. */
function forma_sheet_sync(PDO $pdo): void
{
    $key = forma_google_key();
    $sheet = forma_setting($pdo, 'sheet_id') ?? '';
    if (!$key || $sheet === '') return;
    forma_invoice_schema($pdo);
    $list = $pdo->query('SELECT * FROM invoices WHERE synced = 0 ORDER BY seq LIMIT 20')->fetchAll();
    if (!$list) return;
    try {
        $token = forma_google_token($key);
        $hdr = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
        $eur = fn(int $c) => round($c / 100, 2);
        foreach ($list as $inv) {
            $date = date('d.m.Y', strtotime($inv['issued_at']));
            $rate = (float)$inv['vat_rate'];
            $rowsInv = [['Forma', $inv['nr'], '', $date, $inv['name'], $eur((int)$inv['gross_cents']), $eur((int)$inv['net_cents']), $eur((int)$inv['vat_cents']), forma_invoice_is_voucher($inv) ? 'bezahlt (alter Gutschein)' : (forma_invoice_is_cash($inv) ? 'bezahlt (bar)' : 'bezahlt (online)')]];
            $rowsPos = [];
            foreach (json_decode($inv['items'], true) as $it) {
                $g = (int)$it['unit'] * (int)$it['qty'];
                $n = (int)round($g / (1 + $rate / 100));
                $svc = preg_match('/^\d\d\.\d\d\.\d{4}$/', $inv['service_date']) ? $inv['service_date'] : $date;
                $rowsPos[] = ['Forma', $inv['nr'], '', $date, $svc, (int)substr($svc, 3, 2), $inv['kind'] === 'template' ? 'Template (online)' : ($inv['kind'] === 'kasse' ? (forma_invoice_is_cash($inv) ? 'Atelier-Kasse (bar)' : 'Atelier-Kasse (Bezahl-Link)') : (forma_invoice_is_voucher($inv) ? 'Gutschein-Einlösung' : 'Online-Buchung')),
                    trim($it['text']), $inv['name'], $inv['country'], (int)$it['qty'], $eur((int)$it['unit']),
                    $inv['kind'] === 'template' || str_contains($it['text'], 'zum Bemalen') || ($it['kasse'] ?? '') === 'stueck' ? 0 : (int)$it['qty'], $eur($g), $eur($n), $eur($g - $n)];
            }
            foreach (['Rechnungen!A:I' => $rowsInv, "'Kurse & Leistungen'!A:P" => $rowsPos] as $range => $rows) {
                forma_http('POST', 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($sheet) . '/values/' . rawurlencode($range) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS',
                    json_encode(['values' => $rows], JSON_UNESCAPED_UNICODE), $hdr);
            }
            $pdo->prepare('UPDATE invoices SET synced = 1 WHERE id = ?')->execute([$inv['id']]);
        }
        forma_set_setting($pdo, 'sheet_error', '');
    } catch (Throwable $e) {
        forma_set_setting($pdo, 'sheet_error', date('d.m.Y H:i') . ' ' . $e->getMessage());
        error_log('FORMA sheet: ' . $e->getMessage());
    }
}

/** Probe: Buchungsbestätigung und Muster-Rechnung an Vivien, ohne Nummer aus dem Nummernkreis zu verbrauchen. */
function forma_invoice_sample_send(): void
{
    $inv = ['nr' => 'MUSTER-' . date('Y') . '-000', 'issued_at' => date('Y-m-d'), 'service_date' => '04.11.2026', 'name' => 'Lena Muster',
        'email' => FORMA_NOTIFY_EMAIL, 'address' => "Musterweg 1\n6561 Ischgl", 'country' => 'AT', 'vat_rate' => 20, 'vat_note' => 'MUSTER – keine gültige Rechnung.',
        'items' => json_encode([
            ['text' => 'Pottery bemalen · Atelierbeitrag', 'detail' => 'Mi, 4. 11. 2026 · 15–18 Uhr · FORMA Studio, Mathon', 'qty' => 1, 'unit' => 1500],
            ['text' => 'Tasse mit Henkel zum Bemalen', 'detail' => '', 'qty' => 1, 'unit' => 1800],
            ['text' => 'FORMA Mug zum Bemalen', 'detail' => '', 'qty' => 1, 'unit' => 2200],
        ], JSON_UNESCAPED_UNICODE),
        'gross_cents' => 5500, 'net_cents' => 4583, 'vat_cents' => 917];
    $confirm = "Hallo Lena,\n\ndanke für deine Buchung! Deine Zahlung ist angekommen und dein Platz für „Pottery bemalen“ ist fix. Ich freue mich schon sehr auf dich.\n\n"
        . "Hier noch einmal alles auf einen Blick:\n\nKurs: Pottery bemalen\nTermin: Mi, 4. 11. 2026 · 15–18 Uhr\nPersonen: 1 Person\n"
        . "Deine Stücke: 1× Tasse mit Henkel (€ 18), 1× FORMA Mug (€ 22)\nBezahlt: € 55 (online, danke!)\nOrt: FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon\n\n"
        . "Bitte komm in Kleidung, die schmutzig werden darf. Schürzen und Werkzeug habe ich im Studio.\n\n"
        . "Falls du Fragen hast oder doch verhindert bist, antworte einfach auf diese E-Mail oder ruf mich an: " . FORMA_PHONE . "\n\nBis bald im Studio!\nVivien\n";
    forma_mail(FORMA_NOTIFY_EMAIL, '[Test] Dein Platz ist fix: Pottery bemalen', $confirm);
    forma_mail(FORMA_NOTIFY_EMAIL, '[Test] Deine Rechnung ' . $inv['nr'] . ' von FORMA', forma_invoice_mailtext($inv), null, [['Rechnung_' . $inv['nr'] . '.pdf', forma_invoice_pdf($inv), 'application/pdf']]);
}

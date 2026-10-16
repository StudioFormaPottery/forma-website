<?php
// Atelier-Kasse (Pottery bemalen)
// Vivien tippt in der Verwaltung an, was ein Gast im Atelier bemalt hat.
// Bar bezahlt → Rechnung sofort. Bezahl-Link → Gast zahlt über Stripe (api/kasse-zahlen.php), danach kommt die Rechnung automatisch.
// Wortwahl auf Rechnung und in Mails: Pottery bemalen Workshop, Werkstück, Fertigstellung. Nie „Keramik“.
// Wird von lib.php eingebunden.
declare(strict_types=1);

/** Alles, was an der Kasse antippbar ist: Atelierbeitrag plus die Stücke zum Bemalen. Preise in Cent. */
function forma_kasse_products(): array
{
    return ['beitrag' => ['Atelierbeitrag', (int)FORMA_SERIES['bemalen']['price_cents']]] + FORMA_BEMALEN_ITEMS;
}

/** Eingabe wie ['tasse' => 2] prüfen. Preise kommen immer vom Server. */
function forma_kasse_pick($raw): array
{
    $out = [];
    if (!is_array($raw)) return $out;
    foreach (forma_kasse_products() as $key => [$label, $cents]) {
        $q = max(0, min(50, (int)($raw[$key] ?? 0)));
        if ($q > 0) $out[] = ['key' => $key, 'name' => $label, 'qty' => $q, 'cents' => $cents];
    }
    return $out;
}

function forma_kasse_sum(array $items): int
{
    return array_sum(array_map(fn($i) => (int)$i['qty'] * (int)$i['cents'], $items));
}

/** Kurze Liste für Mails: „1× Tasse mit Henkel (€ 18)“ je Zeile. */
function forma_kasse_lines(array $items): string
{
    return implode("\n", array_map(fn($i) => $i['qty'] . '× ' . $i['name'] . ' (' . forma_euro((int)$i['cents'] * (int)$i['qty']) . ')', $items));
}

/** Rechnungspositionen: immer als Workshop-Leistung, nie als Warenverkauf. */
function forma_kasse_invoice_items(array $items): array
{
    $out = [];
    foreach ($items as $i) {
        $out[] = $i['key'] === 'beitrag'
            ? ['text' => 'Pottery bemalen Workshop · Atelierbeitrag', 'detail' => 'Platz im Atelier, Pinsel und Farben', 'qty' => (int)$i['qty'], 'unit' => (int)$i['cents'], 'kasse' => 'beitrag']
            : ['text' => 'Pottery bemalen Workshop · ' . $i['name'], 'detail' => 'Werkstück zum Bemalen inkl. Farben und Fertigstellung', 'qty' => (int)$i['qty'], 'unit' => (int)$i['cents'], 'kasse' => 'stueck'];
    }
    return $out;
}

function forma_kasse_get(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM kasse_sales WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function forma_kasse_pay_url(array $s): string
{
    return forma_site_url() . '/api/kasse-zahlen.php?s=' . (int)$s['id'] . '&k=' . rawurlencode((string)$s['token']);
}

/** Neuer Verkauf. 'bar' → sofort bezahlt + Rechnung, 'link' → Bezahl-Link per Mail (falls E-Mail da). Gibt die ID zurück. */
function forma_kasse_create(PDO $pdo, string $name, string $email, array $items, string $method, int $courseId = 0): int
{
    $bar = $method === 'bar';
    $pdo->prepare('INSERT INTO kasse_sales (course_id, name, email, items, amount_cents, method, status, token, created_at, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$courseId, $name, $email, json_encode($items, JSON_UNESCAPED_UNICODE), forma_kasse_sum($items), $bar ? 'bar' : 'link', $bar ? 'bezahlt' : 'offen',
            bin2hex(random_bytes(16)), date('c'), $bar ? date('c') : null]);
    $id = (int)$pdo->lastInsertId();
    if ($bar) {
        forma_invoice_for($pdo, 'kasse', $id);
    } elseif ($email !== '') {
        forma_kasse_link_mail($pdo, $id, false);
    }
    return $id;
}

/** Ein offener Link wurde doch bar bezahlt. */
function forma_kasse_mark_cash(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare("UPDATE kasse_sales SET status = 'bezahlt', method = 'bar', paid_at = ? WHERE id = ? AND status = 'offen'");
    $st->execute([date('c'), $id]);
    if ($st->rowCount() === 0) return false;
    forma_invoice_for($pdo, 'kasse', $id);
    return true;
}

/** Fertige WhatsApp-Nachricht mit dem Bezahl-Link. */
function forma_kasse_whatsapp(array $s): string
{
    $items = json_decode((string)$s['items'], true) ?: [];
    return 'Hallo ' . forma_first_name((string)$s['name']) . ", schön, dass du beim Pottery bemalen warst! Hier ist dein Bezahl-Link:\n\n"
        . forma_kasse_lines($items) . "\nGesamt: " . forma_euro((int)$s['amount_cents']) . "\n\n" . forma_kasse_pay_url($s)
        . "\n\nDie Rechnung kommt nach der Zahlung automatisch per E-Mail. Bis bald, Vivien";
}

/** Mail mit Bezahl-Link an den Gast (auch als Erinnerung). */
function forma_kasse_link_mail(PDO $pdo, int $id, bool $reminder): bool
{
    $s = forma_kasse_get($pdo, $id);
    if (!$s || $s['status'] !== 'offen' || trim((string)$s['email']) === '') return false;
    $first = forma_first_name((string)$s['name']);
    $items = json_decode((string)$s['items'], true) ?: [];
    return forma_mail((string)$s['email'], ($reminder ? 'Kleine Erinnerung: ' : '') . 'Dein Bezahl-Link für Pottery bemalen',
        "Hallo $first,\n\n" . ($reminder ? "nur eine kleine Erinnerung an deinen Bezahl-Link vom Pottery bemalen.\n\n" : "schön, dass du beim Pottery bemalen im Atelier warst! Hier ist dein Bezahl-Link für deine Stücke.\n\n")
        . forma_kasse_lines($items) . "\nGesamt: " . forma_euro((int)$s['amount_cents']) . "\n\nJetzt bezahlen:\n" . forma_kasse_pay_url($s) . "\n\n"
        . "Gleich nach der Zahlung bekommst du die Rechnung per E-Mail. Deine Stücke kannst du am Montag darauf im Atelier abholen.\n\n"
        . "Falls etwas nicht stimmt, antworte einfach auf diese E-Mail oder ruf mich an: " . FORMA_PHONE . "\n\nBis bald,\nVivien\n");
}

/** Zahlung über den Bezahl-Link bei Stripe nachsehen. */
function forma_kasse_check(PDO $pdo, array $s): string
{
    if ($s['status'] !== 'offen' || $s['stripe_session'] === '') return (string)$s['status'];
    $session = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($s['stripe_session']));
    if (($session['payment_status'] ?? '') === 'paid') {
        $st = $pdo->prepare("UPDATE kasse_sales SET status = 'bezahlt', paid_at = ? WHERE id = ? AND status = 'offen'");
        $st->execute([date('c'), (int)$s['id']]);
        if ($st->rowCount() > 0) {
            forma_mail(FORMA_NOTIFY_EMAIL, 'Bezahlt: Pottery bemalen ' . $s['name'] . ' (' . forma_euro((int)$s['amount_cents']) . ')',
                "{$s['name']} hat den Bezahl-Link bezahlt.\n\n" . forma_kasse_lines(json_decode((string)$s['items'], true) ?: []) . "\nGesamt: " . forma_euro((int)$s['amount_cents'])
                . " über Stripe\n\nDie Rechnung ist automatisch rausgegangen.\nKasse: " . forma_site_url() . "/verwaltung/?view=kasse\n");
            forma_invoice_for($pdo, 'kasse', (int)$s['id']);
        }
        return 'bezahlt';
    }
    if (($session['status'] ?? '') === 'expired') {
        // Link bleibt gültig, beim nächsten Klick gibt es eine neue Bezahlseite.
        $pdo->prepare("UPDATE kasse_sales SET stripe_session = '' WHERE id = ? AND status = 'offen'")->execute([(int)$s['id']]);
    }
    return 'offen';
}

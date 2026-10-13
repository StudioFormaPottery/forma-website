<?php
// Testbuchungen aus der Verwaltung: spielt eine Buchung komplett durch (Termin, Bezahlseite, alle Mails,
// Rechnung, Terminbestätigung, Bewertungs-Mail) und löscht danach alles wieder. Nichts davon bleibt sichtbar:
// kein belegter Platz, keine Rechnungsnummer, kein Newsletter-Kontakt. Mails gehen nur an Vivien, mit [TEST].
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

const FORMA_TEST_GUEST = ['name' => 'Test Gast', 'email' => 'testgast@forma-pottery.com', 'phone' => '+43 000 000000'];

/** Alle Angebote, die man testen kann: key => [Titel, Untertitel, Icon] */
const FORMA_TEST_OFFERS = [
    'spin'      => ['Spin & Go', 'Kurs an der Drehscheibe, online bezahlt', 'kurs'],
    'atelier'   => ['Offenes Atelier', 'Stunden wählen, online bezahlt', 'kurs'],
    'bemalen'   => ['Pottery bemalen', 'Atelierbeitrag plus Stücke zum Bemalen', 'kurs'],
    'pause'     => ['Pottery Pause', '5-Wochen-Kurs, online bezahlt', 'kurs'],
    'private'   => ['Private Pottery · Buchung', '2 Stunden bezahlt, dann Termin im Kalender', 'private'],
    'anfrage'   => ['Private Pottery · Anfrage', 'Anfrage, Termin einplanen, Bezahl-Link', 'private'],
    'urlaub'    => ['Urlaubsgäste · Drehscheibe 1:1', 'Hotel-QR-Seite, 2 Stunden € 130', 'private'],
    'halbtag'   => ['Teambuilding · Halbtag', '6 Personen à € 159, online bezahlt', 'team'],
    'wochenende'=> ['Teambuilding · Wochenende', 'Verbindliche Anfrage, 6 Personen', 'team'],
    'gutschein' => ['Gutschein', '1 Platz Spin & Go', 'gutschein'],
    'template'  => ['Template aus dem Shop', 'Kauf mit Download-Link', 'template'],
];

function forma_test_run(PDO $pdo, string $offer, bool $send): array
{
    $res = ['title' => FORMA_TEST_OFFERS[$offer][0] ?? $offer, 'steps' => [], 'mails' => []];
    $step = function (string $state, string $text) use (&$res) { $res['steps'][] = [$state, $text]; };
    $GLOBALS['FORMA_TEST'] = true;
    $GLOBALS['FORMA_TEST_SEND'] = $send;
    $GLOBALS['FORMA_TEST_MAILS'] = [];
    $cleanup = [];
    $g = FORMA_TEST_GUEST;
    try {
        if (in_array($offer, ['spin', 'atelier', 'bemalen', 'pause'], true)) {
            $course = null; $fallback = null;
            foreach (forma_upcoming_courses($pdo, false) as $c) {
                $match = $offer === 'pause' ? str_starts_with((string)$c['title'], 'Pottery Pause') : $c['title'] === FORMA_SERIES[$offer]['title'];
                if (!$match || $c['start_date'] < forma_first_bookable_date()) continue;
                $fallback = $fallback ?? $c;
                if ((int)$c['visible'] === 1 && (int)$c['free'] > 0) { $course = $c; break; }
            }
            if (!$course && !$fallback) {
                $step('fail', 'Es gibt keinen kommenden Termin für dieses Angebot. Leg einen Termin an, damit man es buchen kann.');
                return $res;
            }
            if (!$course) {
                $course = $fallback;
                $step('info', (int)$course['visible'] !== 1
                    ? 'Der nächste Termin (' . $course['date_text'] . ') ist auf der Website noch nicht sichtbar. Der Test zeigt dir trotzdem, wie die Buchung abläuft.'
                    : 'Alle kommenden Termine sind ausgebucht, Gäste können gerade nicht buchen. Der Test zeigt dir trotzdem die Mails am Beispiel ' . $course['date_text'] . '.');
            } else {
            $step('ok', 'Nächster buchbarer Termin: ' . $course['date_text'] . ' · ' . $course['free'] . ' ' . ((int)$course['free'] === 1 ? 'Platz' : 'Plätze') . ' frei');
            }
            if ($offer === 'bemalen' && date('Y-m-d') < '2026-10-19') $step('info', 'Auf der Website ist Pottery bemalen erst ab 19. Oktober buchbar. Der Test zeigt dir schon jetzt, wie die Buchung abläuft.');
            $pieces = [];
            $price = (int)$course['price_cents'];
            if ($offer === 'atelier') {
                $slot = null;
                foreach (($course['slots'] ?? []) as $h => $f) if ($f > 0) { $slot = (int)$h; break; }
                $slot = $slot ?? FORMA_ATELIER_SLOTS[0];
                $pieces = [['key' => 'slot' . $slot, 'name' => 'Offenes Atelier ' . forma_atelier_slot_label($slot), 'qty' => 1, 'cents' => $price]];
                $price = 0;
            } elseif ($offer === 'bemalen') {
                $pieces = forma_bemalen_pick(array_fill_keys(array_slice(array_keys(FORMA_BEMALEN_ITEMS), 0, 2), 1));
            }
            $amount = $price + array_sum(array_map(fn($i) => $i['qty'] * $i['cents'], $pieces));
            if ($amount <= 0) $step('info', 'Für diesen Termin ist kein Online-Preis hinterlegt, Gäste melden sich ohne Bezahlung an.');
            else forma_test_stripe($step, $amount, $course['title']);
            $pdo->prepare("INSERT INTO bookings (course_id, name, email, phone, persons, message, status, ip_hash, created_at, amount_cents, pay_token, extras, paid_at)
                VALUES (?, ?, ?, ?, 1, 'Testbuchung aus der Verwaltung', 'bestaetigt', 'test', ?, ?, ?, ?, ?)")
                ->execute([$course['id'], $g['name'], $g['email'], $g['phone'], date('c'), $amount, bin2hex(random_bytes(8)), $pieces ? json_encode($pieces, JSON_UNESCAPED_UNICODE) : '', $amount > 0 ? date('c') : null]);
            $id = (int)$pdo->lastInsertId();
            $cleanup[] = ['DELETE FROM bookings WHERE id = ?', $id];
            $step('ok', 'Buchung gespeichert' . ($amount > 0 ? ' und als bezahlt markiert (' . forma_euro($amount) . ')' : ''));
            forma_send_booking_mails($pdo, $id);
            $step('ok', 'Buchungsbestätigung mit Kalender-Eintrag verschickt' . ($amount > 0 ? ', Rechnung erstellt' : ''));
            forma_test_review($pdo, $step, $course['title']);
        } elseif (in_array($offer, ['private', 'anfrage', 'urlaub', 'halbtag', 'wochenende'], true)) {
            $wd = date('Y-m-d', strtotime('+14 days'));
            $wd2 = date('Y-m-d', strtotime('+16 days'));
            $wish = '1. Wunsch: ' . date('d.m.Y', strtotime($wd)) . ' um 10:00 · 2. Wunsch: ' . date('d.m.Y', strtotime($wd2)) . ' um 14:00';
            $cfg = [
                'private'    => ['buchung', 2, 1, 2 * FORMA_PRIVATE_HOUR, 'Ich möchte eine Vase mit Henkel machen (Test)', 'Private Pottery'],
                'anfrage'    => ['anfrage', 2, 1, 0, 'Ich weiß noch nicht genau, vielleicht ein Geschirr-Set (Test)', 'Private Pottery'],
                'urlaub'     => ['buchung', 2, 1, 13000, 'Private Pottery an der Drehscheibe, 2 Stunden (Urlaubsgast, Hotel: Testhotel)', 'Private Pottery'],
                'halbtag'    => ['buchung', 4, 6, 6 * 15900, 'Teambuilding/Event: Halbtages-Event (4 Stunden) für 6 Personen, Testfirma', 'Teambuilding'],
                'wochenende' => ['anfrage', 6, 6, 0, 'Teambuilding/Event: Töpfer-Wochenende mit Übernachtung für 6 Personen, Testfirma', 'Teambuilding'],
            ][$offer];
            [$kind, $hours, $persons, $amount, $project, $what] = $cfg;
            $paid = $kind === 'buchung';
            if ($paid) forma_test_stripe($step, $amount, $what);
            $pdo->prepare("INSERT INTO private_bookings (kind, hours, amount_cents, name, email, phone, persons, project, wish, status, token, ip_hash, created_at, contact, wish_date, wish_time, paid_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'test', ?, 'email', ?, '10:00', ?)")
                ->execute([$kind, $hours, $amount, $g['name'], $g['email'], $g['phone'], $persons, $project, $wish, $paid ? 'bezahlt' : 'anfrage', bin2hex(random_bytes(16)), date('c'), $wd, $paid ? date('c') : null]);
            $id = (int)$pdo->lastInsertId();
            $cleanup[] = ['DELETE FROM private_sessions WHERE private_id = ?', $id];
            $cleanup[] = ['DELETE FROM private_bookings WHERE id = ?', $id];
            $step('ok', ($paid ? 'Buchung gespeichert und als bezahlt markiert (' . forma_euro($amount) . ')' : 'Anfrage gespeichert') . ' · Wunschtermine: ' . str_replace(' · ', ', ', $wish));
            forma_private_mails($pdo, $id);
            $step('ok', $paid ? 'Bestätigung „Zahlung angekommen“ an den Gast, Info mit „Im Kalender einplanen“ an dich, Rechnung erstellt' : 'Eingangsbestätigung an den Gast, Info mit „Im Kalender einplanen“ an dich');
            $pdo->prepare('INSERT INTO private_sessions (private_id, date, start, hours, note, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $wd, '10:00', $hours, '', date('c')]);
            $ok = forma_private_confirm_mail($pdo, $id, '');
            $step($ok ? 'ok' : 'fail', $ok ? 'Du planst den Termin im Kalender ein: Terminbestätigung mit Apple/Google Kalender' . (!$paid && $offer === 'anfrage' ? ' und Bezahl-Link' : '') . ' an den Gast' : 'Terminbestätigung konnte nicht verschickt werden.');
            if ($offer === 'wochenende') $step('info', 'Das Wochenende stellst du selbst in Rechnung (Übernachtung hat einen anderen Steuersatz).');
            forma_test_review($pdo, $step, $what);
        } elseif ($offer === 'gutschein') {
            forma_test_stripe($step, FORMA_VOUCHERS['spin']['price'], 'Gutschein');
            $pdo->prepare("INSERT INTO vouchers (product, qty, amount_cents, recipient, sender, message, design, buyer_name, buyer_email, status, token, note, created_at)
                VALUES ('spin', 1, ?, 'Lena', 'Test Gast', 'Alles Liebe zum Geburtstag! (Test)', 1, ?, ?, 'offen', ?, 'test', ?)")
                ->execute([FORMA_VOUCHERS['spin']['price'], $g['name'], $g['email'], bin2hex(random_bytes(16)), date('c')]);
            $id = (int)$pdo->lastInsertId();
            $cleanup[] = ['DELETE FROM vouchers WHERE id = ?', $id];
            forma_voucher_paid($pdo, $id);
            $step('ok', 'Gutschein bezahlt, Gutschein-Nummer vergeben, Mail mit PDF-Link an den Gast, Info an dich');
            $step('info', 'Der PDF-Link in der Test-Mail funktioniert nicht, weil der Test-Gutschein danach gelöscht wird.');
        } elseif ($offer === 'template') {
            $t = $pdo->query("SELECT * FROM templates WHERE visible = 1 AND file != '' AND price_cents > 0 ORDER BY sort, id LIMIT 1")->fetch();
            if (!$t) { $step('fail', 'Im Shop ist gerade kein Template mit Preis und PDF hinterlegt.'); return $res; }
            $step('ok', 'Template im Shop: „' . $t['title'] . '“ · ' . forma_euro((int)$t['price_cents']));
            forma_test_stripe($step, (int)$t['price_cents'], 'Template');
            $pdo->prepare("INSERT INTO template_orders (template_id, title, amount_cents, name, email, status, token, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, 'offen', ?, 'test', ?)")
                ->execute([$t['id'], $t['title'], $t['price_cents'], $g['name'], $g['email'], bin2hex(random_bytes(16)), date('c')]);
            $id = (int)$pdo->lastInsertId();
            $cleanup[] = ['DELETE FROM template_orders WHERE id = ?', $id];
            forma_template_order_paid($pdo, $id);
            $step('ok', 'Kauf bezahlt, Download-Link an den Gast, Info an dich, Rechnung erstellt');
        }
    } catch (Throwable $e) {
        $step('fail', 'Fehler: ' . $e->getMessage());
    } finally {
        foreach ($cleanup as [$sql, $id]) {
            try { $pdo->prepare($sql)->execute([$id]); } catch (Throwable $e) { error_log('FORMA test cleanup: ' . $e->getMessage()); }
        }
        $res['mails'] = $GLOBALS['FORMA_TEST_MAILS'];
        unset($GLOBALS['FORMA_TEST'], $GLOBALS['FORMA_TEST_SEND'], $GLOBALS['FORMA_TEST_MAILS']);
    }
    if ($cleanup) $step('ok', 'Testbuchung wieder gelöscht: kein Platz belegt, keine Rechnungsnummer verbraucht, kein Newsletter-Kontakt');
    $step('ok', count($res['mails']) . ' E-Mails' . ($send ? ' mit [TEST] an ' . FORMA_NOTIFY_EMAIL . ' geschickt' : ' erstellt (nur Vorschau)'));
    return $res;
}

/** Legt bei Stripe eine echte Bezahlseite an und löscht sie sofort wieder: zeigt, dass die Bezahlung funktioniert. */
function forma_test_stripe(callable $step, int $cents, string $label): void
{
    if (!forma_stripe_key()) { $step('fail', 'Stripe ist nicht verbunden: Gäste könnten nicht online bezahlen.'); return; }
    try {
        $s = forma_stripe('POST', '/v1/checkout/sessions', [
            'mode' => 'payment', 'locale' => 'de', 'customer_email' => FORMA_TEST_GUEST['email'],
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $cents, 'product_data' => ['name' => 'TEST ' . $label]]]],
            'success_url' => forma_site_url() . '/', 'cancel_url' => forma_site_url() . '/',
        ]);
        if (!empty($s['id'])) {
            try { forma_stripe('POST', '/v1/checkout/sessions/' . rawurlencode($s['id']) . '/expire'); } catch (Throwable $e) {}
            $step('ok', 'Stripe-Bezahlseite über ' . forma_euro($cents) . ' erstellt und gleich wieder gelöscht (nichts abgebucht)' . (forma_stripe_test_mode() ? ' · Achtung: Stripe läuft im Testmodus' : ''));
        } else {
            $step('fail', 'Stripe hat keine Bezahlseite erstellt.');
        }
    } catch (Throwable $e) {
        $step('fail', 'Stripe-Fehler: ' . $e->getMessage());
    }
}

function forma_test_review(PDO $pdo, callable $step, string $what): void
{
    if (forma_setting($pdo, 'review_off') === '1') { $step('info', 'Bewertungs-Mails sind in den Einstellungen ausgeschaltet.'); return; }
    forma_review_send($pdo, FORMA_TEST_GUEST['email'], FORMA_TEST_GUEST['name'], $what);
    $step('ok', 'Bewertungs-Mail (kommt sonst automatisch 1 Stunde nach dem Ende)');
}

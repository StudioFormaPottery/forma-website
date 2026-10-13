<?php
// Mindestteilnehmerzahl (z. B. Spin & Go ab 2 Personen):
// Am Vorabend um 17 Uhr (Anmeldeschluss) bekommt Vivien eine Info, wenn zu wenige angemeldet sind.
// Sie entscheidet selbst: Kurs findet statt, oder Gäste informieren. Die Gäste wählen dann selbst:
// auf einen anderen Termin wechseln oder stornieren (Geld zurück + 10-%-Gutschein als Dankeschön).
declare(strict_types=1);

const FORMA_MIN_GIFT_PERCENT = 10;

function forma_min_schema(PDO $pdo): void
{
    if (forma_setting($pdo, 'min_v26') !== null) return;
    forma_add_column($pdo, 'courses', 'min_status', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'courses', 'min_at', 'TEXT');
    forma_add_column($pdo, 'bookings', 'min_state', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'bookings', 'refund_cents', 'INTEGER NOT NULL DEFAULT 0');
    forma_set_setting($pdo, 'min_v26', '1');
}

/** Läuft regelmäßig mit (bei jedem Seitenaufruf, höchstens alle 5 Minuten). */
function forma_min_check(PDO $pdo, bool $force = false): int
{
    forma_min_schema($pdo);
    $last = (int)(forma_setting($pdo, 'min_last') ?? 0);
    if (!$force && time() - $last < 300) return 0;
    forma_set_setting($pdo, 'min_last', (string)time());
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $days = date('H') >= 17 ? [date('Y-m-d'), $tomorrow] : [date('Y-m-d')];
    $in = implode(',', array_fill(0, count($days), '?'));
    $st = $pdo->prepare("SELECT * FROM courses WHERE start_date IN ($in) AND min_participants > 1 AND min_status = '' AND visible = 1");
    $st->execute($days);
    $n = 0;
    foreach ($st->fetchAll() as $c) {
        $booked = forma_booked($pdo, (int)$c['id']);
        if ($booked >= (int)$c['min_participants']) {
            $pdo->prepare("UPDATE courses SET min_status = 'ok', min_at = ? WHERE id = ?")->execute([date('c'), $c['id']]);
            continue;
        }
        if ($booked === 0) {
            $pdo->prepare("UPDATE courses SET min_status = 'leer', min_at = ? WHERE id = ?")->execute([date('c'), $c['id']]);
            continue;
        }
        $pdo->prepare("UPDATE courses SET min_status = 'gemeldet', min_at = ? WHERE id = ?")->execute([date('c'), $c['id']]);
        $names = $pdo->prepare("SELECT name, persons FROM bookings WHERE course_id = ? AND status = 'bestaetigt'");
        $names->execute([$c['id']]);
        $list = implode("\n", array_map(fn($b) => '· ' . $b['name'] . ' (' . $b['persons'] . ($b['persons'] == 1 ? ' Person' : ' Personen') . ')', $names->fetchAll()));
        forma_mail(FORMA_NOTIFY_EMAIL, 'Zu wenige Anmeldungen: ' . $c['title'] . ' ' . preg_replace('/\s*·.*$/u', '', (string)$c['date_text']),
            "Hallo Vivien,\n\nfür „{$c['title']}“ am {$c['date_text']} ist die Anmeldung jetzt geschlossen, und es sind nur $booked von mindestens {$c['min_participants']} Personen angemeldet:\n\n$list\n\n"
            . "Du entscheidest: Findet der Kurs trotzdem statt (z. B. weil sich noch jemand telefonisch angemeldet hat), oder sollen die Gäste informiert werden? Dann können sie selbst auf einen anderen Termin wechseln oder stornieren und bekommen ihr Geld zurück plus einen kleinen Gutschein.\n\n"
            . "Ohne deinen Klick passiert nichts.\n\n" . forma_site_url() . '/verwaltung/?view=course&id=' . (int)$c['id'] . "\n");
        $n++;
    }
    return $n;
}

/** Vivien hat entschieden: Gäste informieren. */
function forma_min_inform(PDO $pdo, int $courseId): int
{
    forma_min_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $st->execute([$courseId]);
    $c = $st->fetch();
    if (!$c) return 0;
    $pdo->prepare("UPDATE courses SET min_status = 'gast_informiert', min_at = ? WHERE id = ?")->execute([date('c'), $courseId]);
    $bs = $pdo->prepare("SELECT * FROM bookings WHERE course_id = ? AND status = 'bestaetigt' AND min_state = ''");
    $bs->execute([$courseId]);
    $n = 0;
    foreach ($bs->fetchAll() as $b) {
        if (!filter_var((string)$b['email'], FILTER_VALIDATE_EMAIL)) continue;
        if ((string)$b['pay_token'] === '') {
            $tok = bin2hex(random_bytes(16));
            $pdo->prepare('UPDATE bookings SET pay_token = ? WHERE id = ?')->execute([$tok, $b['id']]);
            $b['pay_token'] = $tok;
        }
        $pdo->prepare("UPDATE bookings SET min_state = 'gefragt' WHERE id = ?")->execute([$b['id']]);
        $paid = (int)$b['amount_cents'] > 0 || (int)($b['voucher_cents'] ?? 0) > 0;
        $first = forma_first_name((string)$b['name']);
        forma_mail((string)$b['email'], 'Dein Kurs ' . preg_replace('/\s*·.*$/u', '', (string)$c['date_text']) . ': bitte kurz wählen',
            "Hallo $first,\n\nich freue mich so, dass du dich für „{$c['title']}“ am {$c['date_text']} angemeldet hast! Leider ist dieses Mal die Mindestteilnehmerzahl von {$c['min_participants']} Personen nicht erreicht, deshalb kann der Kurs so nicht stattfinden. Das tut mir wirklich leid.\n\n"
            . "Du kannst ganz einfach selbst wählen, was dir lieber ist:\n\n"
            . "· Auf einen anderen Termin wechseln, kostenlos und mit einem Klick\n"
            . ($paid ? "· Oder stornieren: Du bekommst dein Geld zurück und als kleines Dankeschön für deine Geduld einen Gutschein über " . FORMA_MIN_GIFT_PERCENT . " % für deine nächste Buchung\n\n" : "· Oder stornieren, ganz ohne Kosten\n\n")
            . forma_site_url() . '/api/kurs-aendern.php?b=' . (int)$b['id'] . '&k=' . $b['pay_token'] . "\n\n"
            . "Du hast Fragen oder möchtest lieber telefonisch etwas ausmachen? Ruf mich einfach an: " . FORMA_PHONE . "\n\nBis bald,\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n");
        $n++;
    }
    return $n;
}

/** Andere Termine desselben Kurses mit genug freien Plätzen. */
function forma_min_alternatives(PDO $pdo, array $course, int $persons): array
{
    $out = [];
    foreach (forma_upcoming_courses($pdo) as $c) {
        if ($c['title'] !== $course['title'] || (int)$c['id'] === (int)$course['id']) continue;
        if ((int)$c['free'] < $persons) continue;
        $out[] = $c;
        if (count($out) >= 12) break;
    }
    return $out;
}

/** Gast wechselt auf einen anderen Termin. */
function forma_booking_move(PDO $pdo, int $bookingId, int $newCourseId): string
{
    $pdo->exec('BEGIN IMMEDIATE');
    $st = $pdo->prepare("SELECT b.*, c.title, c.date_text FROM bookings b JOIN courses c ON c.id = b.course_id WHERE b.id = ? AND b.status = 'bestaetigt'");
    $st->execute([$bookingId]);
    $b = $st->fetch();
    $nc = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND visible = 1 AND start_date >= ?');
    $nc->execute([$newCourseId, forma_first_bookable_date()]);
    $n = $nc->fetch();
    if (!$b || !$n || $n['title'] !== $b['title']) { $pdo->exec('ROLLBACK'); return 'Dieser Termin ist leider nicht mehr verfügbar.'; }
    if ((int)$n['capacity'] - forma_booked($pdo, (int)$n['id']) < (int)$b['persons']) { $pdo->exec('ROLLBACK'); return 'Dieser Termin ist leider gerade voll geworden. Bitte wähl einen anderen.'; }
    $pdo->prepare("UPDATE bookings SET course_id = ?, min_state = 'verschoben', review_sent = NULL WHERE id = ?")->execute([$newCourseId, $bookingId]);
    $pdo->exec('COMMIT');
    $first = forma_first_name((string)$b['name']);
    [$calTxt, $calAtt] = forma_cal_block($pdo, 'b', $bookingId);
    forma_mail((string)$b['email'], 'Dein neuer Termin: ' . $n['title'] . ' ' . preg_replace('/\s*·.*$/u', '', (string)$n['date_text']),
        "Hallo $first,\n\nwie schön, dass du dabei bleibst! Dein Platz ist jetzt umgebucht:\n\nKurs: {$n['title']}\nNeuer Termin: {$n['date_text']}\nOrt: FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon\n"
        . $calTxt . "\nBezahlt ist schon alles, du musst nichts weiter tun.\n\nBis bald im Studio!\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n", null, $calAtt);
    forma_mail(FORMA_NOTIFY_EMAIL, 'Umgebucht: ' . $b['name'] . ' auf ' . preg_replace('/\s*·.*$/u', '', (string)$n['date_text']),
        "{$b['name']} wechselt wegen zu weniger Anmeldungen von {$b['date_text']} auf {$n['date_text']} ({$n['title']}).\n\nDer Platz ist dort schon eingetragen.\n\n" . forma_site_url() . '/verwaltung/?view=course&id=' . (int)$n['id'] . "\n", (string)$b['email']);
    return '';
}

/** Gast storniert: Geld zurück (Stripe), Storno-Rechnung, Gutschein-Anteil wieder frei, 10-%-Gutschein als Dankeschön. */
function forma_booking_refund_cancel(PDO $pdo, int $bookingId): array
{
    $pdo->exec('BEGIN IMMEDIATE');
    $st = $pdo->prepare("SELECT b.*, c.title, c.date_text FROM bookings b JOIN courses c ON c.id = b.course_id WHERE b.id = ? AND b.status = 'bestaetigt'");
    $st->execute([$bookingId]);
    $b = $st->fetch();
    if (!$b) { $pdo->exec('ROLLBACK'); return ['ok' => false]; }
    $pdo->prepare("UPDATE bookings SET status = 'storniert', min_state = 'storniert' WHERE id = ?")->execute([$bookingId]);
    $pdo->exec('COMMIT');

    $cash = (int)$b['amount_cents'];
    $vc = (int)($b['voucher_cents'] ?? 0);
    $refunded = false;
    if ($cash > 0 && (string)$b['stripe_session'] !== '' && forma_stripe_key()) {
        try {
            $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$b['stripe_session']));
            $pi = is_array($s['payment_intent'] ?? null) ? ($s['payment_intent']['id'] ?? '') : (string)($s['payment_intent'] ?? '');
            if ($pi !== '') {
                $r = forma_stripe('POST', '/v1/refunds', ['payment_intent' => $pi, 'reason' => 'requested_by_customer', 'metadata' => ['booking_id' => (string)$bookingId, 'grund' => 'Mindestteilnehmerzahl nicht erreicht']]);
                $refunded = !empty($r['id']);
            }
        } catch (Throwable $e) {
            error_log('FORMA refund: ' . $e->getMessage());
        }
    }
    if ($refunded) {
        $pdo->prepare('UPDATE bookings SET refund_cents = ? WHERE id = ?')->execute([$cash, $bookingId]);
        try { forma_invoice_storno($pdo, 'kurs', $bookingId, $cash); } catch (Throwable $e) { error_log('FORMA storno invoice: ' . $e->getMessage()); }
    }
    if ($vc > 0) {
        // Der eingelöste Gutschein-Betrag steht wieder zur Verfügung
        $pdo->prepare("UPDATE voucher_uses SET status = 'frei' WHERE ref_kind = 'kurs' AND ref_id = ?")->execute([$bookingId]);
        if (!empty($b['voucher_id'])) $pdo->prepare("UPDATE vouchers SET status = 'bezahlt', redeemed_at = NULL WHERE id = ? AND status = 'eingeloest'")->execute([(int)$b['voucher_id']]);
    }
    $gift = null;
    $total = $cash + $vc;
    if ($total > 0) {
        $giftCents = max(500, (int)(ceil($total * FORMA_MIN_GIFT_PERCENT / 100 / 100) * 100));
        $gift = forma_voucher_gift($pdo, $giftCents, (string)$b['name'], (string)$b['email'], 'Dankeschön: ' . $b['title'] . ' ' . $b['date_text'] . ' fand nicht statt');
        $gift['cents'] = $giftCents;
    }
    $first = forma_first_name((string)$b['name']);
    $lines = [];
    if ($cash > 0) $lines[] = $refunded ? '· ' . forma_euro($cash) . ' gehen zurück auf deine Karte, das dauert je nach Bank ein paar Tage. Die Storno-Rechnung bekommst du in einer eigenen E-Mail.' : '· ' . forma_euro($cash) . ' überweise ich dir in den nächsten Tagen zurück.';
    if ($vc > 0) $lines[] = '· Dein Gutschein über ' . forma_euro($vc) . ' ist wieder gutgeschrieben und weiter einlösbar.';
    forma_mail((string)$b['email'], 'Dein Kurs ist storniert', "Hallo $first,\n\nalles klar, dein Platz für „{$b['title']}“ am {$b['date_text']} ist storniert.\n\n" . implode("\n", $lines) . "\n"
        . ($gift ? "\nUnd weil du so geduldig warst, schenke ich dir einen Gutschein über " . forma_euro($gift['cents']) . " für deine nächste Buchung:\n\nGutscheincode: {$gift['code']}\n\nDu gibst ihn einfach bei deiner nächsten Buchung auf www.forma-pottery.com vor dem Bezahlen ein. " . FORMA_VOUCHER_TERMS . "\n" : '')
        . "\nIch hoffe sehr, wir sehen uns bald im Atelier!\n\nBis bald,\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n");
    forma_mail(FORMA_NOTIFY_EMAIL, 'Storniert (zu wenige Anmeldungen): ' . $b['name'], "{$b['name']} hat „{$b['title']}“ am {$b['date_text']} storniert, weil die Mindestteilnehmerzahl nicht erreicht war.\n\n"
        . ($cash > 0 ? ($refunded ? 'Rückerstattung über Stripe: ' . forma_euro($cash) . " (automatisch erledigt, Storno-Rechnung ist erstellt).\n" : 'ACHTUNG: Die automatische Rückerstattung über ' . forma_euro($cash) . " hat nicht geklappt. Bitte im Stripe-Dashboard unter Zahlungen selbst erstatten.\n") : '')
        . ($vc > 0 ? 'Gutschein-Anteil ' . forma_euro($vc) . " wieder gutgeschrieben.\n" : '')
        . ($gift ? 'Dankeschön-Gutschein: ' . forma_euro($gift['cents']) . " ({$gift['nr']}, Code {$gift['code']})\n" : '') . "\n" . forma_site_url() . "/verwaltung/?view=course&id=" . (int)$b['course_id'] . "\n", (string)$b['email']);
    return ['ok' => true, 'refunded' => $refunded, 'cash' => $cash, 'gift' => $gift];
}

/**
 * Kleiner Monatskalender für Gäste: nur Tage mit freien Terminen sind anklickbar.
 * $days: ['Y-m-d' => html der Zelle (Link oder Knopf)], $lang: 'de' | 'en'
 */
function forma_cal_html(array $days, string $lang = 'de'): string
{
    if (!$days) return '';
    ksort($days);
    $wd = $lang === 'en' ? ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] : ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $mn = $lang === 'en' ? ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] : FORMA_MONTHS;
    $first = new DateTime(array_key_first($days));
    $last = new DateTime(array_key_last($days));
    $m = new DateTime($first->format('Y-m-01'));
    $end = new DateTime($last->format('Y-m-01'));
    $today = date('Y-m-d');
    $out = '<div class="cal">';
    while ($m <= $end) {
        $out .= '<div class="cal-m"><p class="cal-t">' . $mn[(int)$m->format('n')] . ' ' . $m->format('Y') . '</p><div class="cal-g">';
        foreach ($wd as $w) $out .= '<span class="cal-h">' . $w . '</span>';
        $pad = (int)$m->format('N') - 1;
        for ($i = 0; $i < $pad; $i++) $out .= '<span></span>';
        $dim = (int)$m->format('t');
        for ($d = 1; $d <= $dim; $d++) {
            $ymd = $m->format('Y-m-') . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
            $out .= isset($days[$ymd]) ? $days[$ymd] : '<span class="cal-d' . ($ymd < $today ? ' past' : '') . '">' . $d . '</span>';
        }
        $out .= '</div></div>';
        $m->modify('+1 month');
    }
    return $out . '</div>';
}

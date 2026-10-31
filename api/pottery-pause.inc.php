<?php
/**
 * Pottery Pause: Erinnerungs-Mail mit Handbuch, 5 Tage vor Kursbeginn um 9 Uhr automatisch
 * an alle bestätigten Anmeldungen (Vivien, 31.10.2026). Termine kommen aus dem Kurs,
 * das Handbuch (downloads/pottery-pause-handbuch.pdf) ist ohne Datum und bleibt gleich.
 */

const FORMA_PP_DAYS_BEFORE = 5;
const FORMA_PP_HOUR = 9;
const FORMA_PP_HANDBOOK = FORMA_SITE_URL . '/downloads/pottery-pause-handbuch.pdf';
const FORMA_PP_WEEKS = [
    'Ankommen & die Basics',
    'Der Zylinder & Cups',
    'Schüsseln & Matcha-Schälchen',
    'Mini-Vasen & Garlic Grater',
    'Freies Arbeiten & deine Herzensprojekte',
];

function forma_pp_is(array $course): bool
{
    return str_starts_with(mb_strtolower(trim((string)($course['title'] ?? ''))), 'pottery pause');
}

/** Wann die Erinnerung rausgeht (Zeitstempel). */
function forma_pp_send_at(array $course): ?int
{
    $d = (string)($course['start_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
    $t = strtotime($d . ' ' . sprintf('%02d:00', FORMA_PP_HOUR) . ' -' . FORMA_PP_DAYS_BEFORE . ' days');
    return $t === false ? null : $t;
}

/** [„18 Uhr“, „18–20 Uhr“] aus dem Termintext, sonst leer. */
function forma_pp_times(array $course): array
{
    if (preg_match('/(\d{1,2})(?:[:.](\d{2}))?\s*[–-]\s*(\d{1,2})(?:[:.](\d{2}))?\s*Uhr/u', (string)$course['date_text'], $m)) {
        $f = fn($h, $mi) => (int)$h . ((int)$mi ? ':' . sprintf('%02d', (int)$mi) : '');
        return [$f($m[1], $m[2] ?? 0) . ' Uhr', $f($m[1], $m[2] ?? 0) . '–' . $f($m[3], $m[4] ?? 0) . ' Uhr'];
    }
    return ['', ''];
}

/** Die fünf Abende (wöchentlich ab dem Startdatum). */
function forma_pp_dates(array $course): array
{
    $out = [];
    $t = strtotime((string)$course['start_date'] . ' 12:00');
    for ($i = 0; $i < 5 && $t; $i++) $out[] = strtotime('+' . (7 * $i) . ' days', $t);
    return $out;
}

function forma_pp_short(int $ts): string
{
    return (int)date('j', $ts) . '. ' . mb_substr(FORMA_MONTHS[(int)date('n', $ts)], 0, 3);
}

function forma_pp_subject(array $course): string
{
    $wd = FORMA_WEEKDAYS_LONG[(int)date('w', strtotime((string)$course['start_date'] . ' 12:00'))];
    return 'Am ' . $wd . " geht's los: deine Pottery Pause ♡";
}

function forma_pp_text(string $name, array $course): string
{
    [$start, $span] = forma_pp_times($course);
    $first = $name !== '' ? forma_first_name($name) : '';
    $lines = ['Hallo' . ($first !== '' ? ' ' . $first : '') . ',', '',
        'wie schön, dass du dich für die Pottery Pause angemeldet hast! Am ' . forma_de_date((string)$course['start_date'], false) . ($start ? ', um ' . $start : '') . ' starten wir, und ich freue mich schon sehr auf unsere fünf gemeinsamen Abende an der Drehscheibe.', '',
        'Dein kleines Handbuch (PDF): ' . FORMA_PP_HANDBOOK, '', 'Deine 5 Abende:'];
    foreach (forma_pp_dates($course) as $i => $ts) $lines[] = forma_pp_short($ts) . ' · ' . FORMA_PP_WEEKS[$i];
    $lines[] = ($span ? 'Jeweils ' . $span . ' im Atelier in Mathon.' : 'Im Atelier in Mathon.');
    array_push($lines, '', 'Hast du schon eine Idee für ein Herzensprojekt? Trag sie im Handbuch ins Ideen-Feld ein oder antworte einfach auf diese Mail.', '',
        'Gut zu wissen: Nimm dir in jeder Einheit die letzten 20 bis 25 Minuten Zeit, um deine Werkstücke vom vorigen Mal fertigzumachen und zu bemalen.', '',
        'Komm am besten in bequemer Kleidung, die ruhig etwas Ton abbekommen darf.', '', 'Bis bald, ich freue mich auf dich!', 'Vivien');
    return implode("\n", $lines);
}

function forma_pp_html(string $name, array $course): string
{
    $e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
    [$start, $span] = forma_pp_times($course);
    $first = $name !== '' ? forma_first_name($name) : '';
    $f = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;";
    $p = fn($t, $mb = 14) => '<p style="margin:0 0 ' . $mb . 'px;font-size:16px;line-height:1.65;">' . $t . '</p>';
    $weeks = '';
    foreach (forma_pp_dates($course) as $i => $ts) $weeks .= '<b>' . $e(forma_pp_short($ts)) . '</b> · ' . $e(FORMA_PP_WEEKS[$i]) . '<br>';
    $when = $e(forma_de_date((string)$course['start_date'], false)) . ($start ? ', um ' . $e($start) : '');
    return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Deine Pottery Pause</title></head>'
        . '<body style="margin:0;padding:0;background:#F4ECE7;">'
        . '<div style="display:none;max-height:0;overflow:hidden;">Fünf Abende an der Drehscheibe, und dein kleines Handbuch ist schon da.</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4ECE7;"><tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FFFDFB;border-radius:22px;overflow:hidden;' . $f . 'color:#33211B;">'
        . '<tr><td><img src="' . FORMA_SITE_URL . '/img/mail/pottery-pause-header.jpg" width="600" alt="Deine Pottery Pause – bald geht\'s los!" style="display:block;width:100%;max-width:600px;height:auto;border:0;"></td></tr>'
        . '<tr><td style="padding:22px 40px 8px;">'
        . $p('Hallo' . ($first !== '' ? ' ' . $e($first) : '') . ',')
        . $p('wie schön, dass du dich für die Pottery Pause angemeldet hast! Am <b>' . $when . '</b> starten wir, und ich freue mich schon sehr auf unsere fünf gemeinsamen Abende an der Drehscheibe.')
        . $p('Damit du weißt, was dich erwartet, habe ich dir ein kleines Handbuch zusammengestellt: deine fünf Wochen auf einen Blick, wie ein Abend abläuft und alles, was du vorher wissen solltest.', 22)
        . '</td></tr>'
        . '<tr><td align="center" style="padding:0 40px 30px;"><a href="' . $e(FORMA_PP_HANDBOOK) . '" style="display:inline-block;background:#8C2C1D;color:#FBF1EA;text-decoration:none;font-size:16px;font-weight:600;padding:15px 30px;border-radius:40px;">Dein Handbuch öffnen (PDF)</a></td></tr>'
        . '<tr><td style="padding:0 40px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEE2D4;border-radius:18px;"><tr><td style="padding:22px 24px;font-size:15px;line-height:1.7;">'
        . '<p style="margin:0 0 8px;font-size:17px;font-weight:700;color:#8C2C1D;">Deine 5 Abende</p>' . $weeks
        . '<span style="color:#674B40;font-size:13px;">' . ($span ? 'Jeweils ' . $e($span) . ' im Atelier in Mathon. ' : 'Im Atelier in Mathon. ') . 'Wir gehen ganz in deinem Tempo vor.</span>'
        . '</td></tr></table></td></tr>'
        . '<tr><td style="padding:28px 40px 6px;"><p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#8C2C1D;">Hast du schon eine Idee?</p>'
        . $p('Im Kurs geht es wirklich ums Drehen an der Töpferscheibe: Ich bringe dir die Basics bei, damit du sicher wirst und Freude am Arbeiten hast. Natürlich bleibt auch Zeit für deine kleinen Herzensprojekte. Wenn du schon eine Vorstellung hast, was du gerne machen möchtest, trag sie im Handbuch ins Ideen-Feld ein oder antworte mir einfach auf diese Mail.')
        . '</td></tr>'
        . '<tr><td style="padding:10px 40px 6px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:2px dashed #DDBFA8;border-radius:18px;"><tr><td style="padding:18px 22px;font-size:15px;line-height:1.65;">'
        . '<b style="color:#8C2C1D;">Gut zu wissen:</b> Nimm dir in jeder Einheit die letzten 20 bis 25 Minuten Zeit, um deine Werkstücke vom vorigen Mal fertigzumachen und zu bemalen. So sammelt sich nichts an, und du hast in der letzten Stunde keinen Stress.'
        . '</td></tr></table></td></tr>'
        . '<tr><td style="padding:22px 40px 4px;">' . $p('Komm am besten in bequemer Kleidung, die ruhig etwas Ton abbekommen darf. Alles andere liegt im Atelier für dich bereit.') . $p('Bis bald, ich freue mich auf dich!', 0) . '</td></tr>'
        . '<tr><td style="padding:6px 40px 34px;"><img src="' . FORMA_SITE_URL . '/img/signatur-liebe-zum-ton.png" width="230" alt="Mit ganz viel Liebe zum Ton" style="display:block;border:0;"></td></tr>'
        . '<tr><td align="center" style="background:#EEE2D4;padding:20px 30px;font-size:12px;line-height:1.6;color:#674B40;">Studio FORMA · <a href="https://www.forma-pottery.com" style="color:#8C2C1D;">forma-pottery.com</a> · <a href="mailto:hello@forma-pottery.com" style="color:#8C2C1D;">hello@forma-pottery.com</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

function forma_pp_send(string $to, string $name, array $course, string $prefix = ''): bool
{
    $GLOBALS['FORMA_MAIL_HTML'] = forma_pp_html($name, $course);
    try {
        return forma_mail($to, $prefix . forma_pp_subject($course), forma_pp_text($name, $course), FORMA_NOTIFY_EMAIL);
    } finally {
        unset($GLOBALS['FORMA_MAIL_HTML']);
    }
}

/** Läuft bei Seitenaufrufen (höchstens alle 15 Minuten). Jede Anmeldung bekommt die Mail genau einmal. */
function forma_pp_reminders(PDO $pdo, bool $force = false): int
{
    if (forma_setting($pdo, 'pp_reminder_off') === '1') return 0;
    $last = (int)(forma_setting($pdo, 'pp_reminder_last') ?? 0);
    if (!$force && time() - $last < 900) return 0;
    forma_set_setting($pdo, 'pp_reminder_last', (string)time());
    $st = $pdo->prepare("SELECT b.id, b.name, b.email, c.id AS cid, c.title, c.date_text, c.start_date
        FROM bookings b JOIN courses c ON c.id = b.course_id
        WHERE b.status = 'bestaetigt' AND b.reminder_sent IS NULL AND c.title LIKE 'Pottery Pause%' AND c.start_date BETWEEN ? AND ?
        ORDER BY b.id");
    $st->execute([date('Y-m-d'), date('Y-m-d', strtotime('+' . FORMA_PP_DAYS_BEFORE . ' days'))]);
    $sent = 0; $seen = [];
    foreach ($st->fetchAll() as $b) {
        $at = forma_pp_send_at($b);
        if ($at === null || time() < $at) continue;
        // erst als erledigt merken, dann senden: nie doppelt
        $up = $pdo->prepare('UPDATE bookings SET reminder_sent = ? WHERE id = ? AND reminder_sent IS NULL');
        $up->execute([date('c'), $b['id']]);
        if ($up->rowCount() !== 1) continue;
        $key = mb_strtolower(trim((string)$b['email'])) . '#' . $b['cid'];
        if (isset($seen[$key]) || !filter_var($b['email'], FILTER_VALIDATE_EMAIL)) continue;
        $seen[$key] = true;
        if (forma_pp_send((string)$b['email'], (string)$b['name'], $b)) $sent++;
    }
    return $sent;
}

<?php
// Persönliche Termin-Links für Videogespräche (Vivien, 29.10.2026: Gespräch mit Maria und Jakob vom Marigold).
// Vivien legt in der Verwaltung (Movecia → Übersicht → „📅 Termin-Links“) einen Tag und ein paar Uhrzeiten an und schickt den Link
// moveciamovement.com/termin/?t=TOKEN. Die Gästin tippt eine Uhrzeit an und trägt ihre E-Mail ein → der Termin ist sofort fix:
// Whereby-Raum, Mail mit Kalenderdatei an sie, Mail mit Gastgeberinnen-Link an Vivien. Viviens Link öffnet das Gastgeberinnen-Fenster
// der Gatherings (Teleprompter mit eigenem Gesprächsleitfaden), dazu legt die Bibliothek ein unsichtbares Gathering an (visible = 0).
// Die Gästin kommt über dieselbe Termin-Seite in den Raum (öffnet 15 Min. vorher). Nur Deutsch, noindex.
declare(strict_types=1);
require_once __DIR__ . '/movecia-lib.php';
require_once __DIR__ . '/natal-lib.php';
require_once __DIR__ . '/method-lib.php';

function tm_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    mm_schema($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS mm_termine (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        guest TEXT NOT NULL DEFAULT '',
        intro TEXT NOT NULL DEFAULT '',
        date TEXT NOT NULL,
        times TEXT NOT NULL,
        minutes INTEGER NOT NULL DEFAULT 45,
        script TEXT NOT NULL DEFAULT '',
        token TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'offen',
        slot TEXT NOT NULL DEFAULT '',
        name TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        note TEXT NOT NULL DEFAULT '',
        gid INTEGER NOT NULL DEFAULT 0,
        booked_at TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
    )");
    try { $pdo->exec("ALTER TABLE mm_termine ADD COLUMN msg TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    tm_seed($pdo);
}

/** Einmal angelegt: der Termin-Link für das Gespräch mit dem Marigold (Montag, 2. November 2026, vormittags). */
function tm_seed(PDO $pdo): void
{
    $cfg = mm_cfg('termine') ?? [];
    if (!empty($cfg['seed_marigold'])) {
        if (empty($cfg['script_marigold2'])) {
            $pdo->prepare("UPDATE mm_termine SET script = ? WHERE title = 'Präsenzwochenende Teacher Training' AND date = '2026-11-02' AND status = 'offen'")->execute([tm_script_marigold()]);
            $cfg = ['script_marigold2' => date('c')] + $cfg; mm_save_cfg('termine', $cfg);
        }
        if (empty($cfg['msg_marigold'])) {
            $pdo->prepare("UPDATE mm_termine SET msg = ? WHERE title = 'Präsenzwochenende Teacher Training' AND date = '2026-11-02' AND msg = ''")->execute([tm_msg_marigold()]);
            mm_save_cfg('termine', ['msg_marigold' => date('c')] + $cfg);
        }
        return;
    }
    tm_create($pdo, [
        'title' => 'Präsenzwochenende Teacher Training',
        'guest' => 'Maria und Jakob',
        'intro' => 'Ich freue mich so auf unser Gespräch über das Präsenzwochenende im September 2028! Sucht euch einfach eine Uhrzeit aus, die für euch passt. Wir sprechen dann ganz entspannt per Video über die Planung, die Kosten, den Ablauf und alles, was euch noch einfällt.',
        'date' => '2026-11-02', 'times' => '08:00,08:30,09:00,09:30,10:00', 'minutes' => 45,
        'script' => tm_script_marigold(),
    ]);
    mm_save_cfg('termine', ['seed_marigold' => date('c')] + $cfg);
}

/** Fertige Mail an Maria und Jakob (Vivien, 29.10.2026), {link} wird beim Kopieren ersetzt. Einmal nachgetragen. */
function tm_msg_marigold(): string
{
    return "Hallo Maria, hallo Jakob,\n\nschön, dass es am Montag klappt! Am besten geht es bei mir zwischen 8 und 11 Uhr, da sind meine Kinder in Betreuung (Au-pair).\n\n"
        . "Hier könnt ihr euch einfach eine Uhrzeit aussuchen:\n{link}\n\n"
        . "Ihr bekommt dann gleich eine Bestätigung, und über denselben Link kommt ihr am Montag direkt in unser Videogespräch, ganz ohne App.\n\n"
        . "Es wäre super, wenn ihr mir vorher schon die Kostenaufstellung schicken könntet. Falls das übers Wochenende zu knapp ist, macht das gar nichts, dann besprechen wir einfach alles am Montag.\n\n"
        . "Ich freu mich auf euch!\n\nLiebe Grüße\nVivien";
}

/** Nachricht zum Kopieren: eigene Nachricht des Termins oder ein kurzer Standardtext, immer mit Link. */
function tm_message(array $t): string
{
    $m = trim((string)($t['msg'] ?? ''));
    if ($m === '') $m = ($t['guest'] !== '' ? 'Hallo ' . $t['guest'] . ",\n\n" : "Hallo,\n\n") . 'such dir für unser Videogespräch (' . $t['title'] . ', ' . tm_day($t) . ") einfach eine Uhrzeit aus:\n{link}\n\nDu bekommst dann gleich eine Bestätigung, und über denselben Link kommst du direkt in unser Videogespräch, ganz ohne App.\n\nLiebe Grüße\nVivien";
    return str_replace('{link}', tm_link($t), $m);
}

/** Gesprächsleitfaden für den Teleprompter (nur Vivien sieht ihn). */
function tm_script_marigold(): string
{
    return "[Begrüßung]\nHallo Maria, hallo Jakob! Schön, dass es heute klappt. Ich habe mir ein paar Punkte notiert, damit wir nichts vergessen.\n\n"
        . "[Kurz zur Ausbildung]\nThe Mindful Becoming Teacher Training startet am 3. November 2027 und dauert elf Monate. Höchstens 14 Teilnehmerinnen, mit uns beiden Ausbilderinnen also maximal 16 Personen. Das Präsenzwochenende ist der Abschluss im September 2028.\n\n"
        . "[Der Termin im September 2028]\nWelches Wochenende im September 2028 passt bei euch? Ich hätte gern Freitag bis Sonntag, also zum Beispiel 22. bis 24. September.\n\n"
        . "[Ablauf und Zeiten]\nFreitag ab 9 Uhr bis Sonntag 16 Uhr. Geht ein Early Check-in am Freitag und ein Late Check-out am Sonntag?\n\n"
        . "[Räume: Open Space und Praxisraum]\nOpen Space und Praxisraum an allen drei Tagen. Frage: Zählen wir zwei Ausbilderinnen bei den 12 Personen für den kostenlosen Open Space mit?\n\n"
        . "[Zimmer und Belegung]\nDoppelzimmer für die Teilnehmerinnen, Einzelzimmer nur auf Wunsch, den Aufpreis rechne ich selbst mit ihnen ab. Wie läuft die Zimmerverteilung bei euch?\n\n"
        . "[Küche und Verpflegung]\nCommunity Kitchen für alle drei Tage. Ein Mietkoch ohne Küchenhilfe, die Teilnehmerinnen kochen in kleinen Gruppen mit, als Kochworkshop mit unserer Ernährungsberaterin. Dürfen sie in der Gastroküche mitarbeiten? Gibt es Hygieneregeln? Zimmerchen für den Koch buchen. Habt ihr einen Koch, den ihr empfehlt?\n\n"
        . "[Kosten und euer Angebot]\nKönnt ihr mir ein schriftliches Angebot für September 2028 schicken, mit den Preisen fix für uns? Wie hoch ist die Anzahlung, und ab wann gelten die Stornobedingungen?\n\n"
        . "[Fotografin oder Fotograf]\nIch möchte gern eine Fotografin oder einen Fotografen dabeihaben. Ist das bei euch in Ordnung, auch Fotos vom Haus und vom Garten? Dürfen wir die Fotos auf unserer Website verwenden?\n\n"
        . "[Sonstiges]\nAbschlusszeremonie am Sonntag, ganz ruhig, ohne Feuer. Klangschalen und Meditationskissen von euch? Parken, Anreise, WLAN.\n\n"
        . "[Abschluss]\nDanke euch! Ich schicke euch alles noch einmal schriftlich, und ihr schickt mir das Angebot. Ich freu mich riesig!";
}

function tm_create(PDO $pdo, array $in): int
{
    $times = array_values(array_filter(array_map('trim', explode(',', (string)$in['times'])), fn($t) => preg_match('/^\d{2}:\d{2}$/', $t)));
    $pdo->prepare('INSERT INTO mm_termine (title, guest, intro, date, times, minutes, script, token, created_at) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([(string)$in['title'], (string)($in['guest'] ?? ''), (string)($in['intro'] ?? ''), (string)$in['date'], implode(',', $times), max(15, min(180, (int)($in['minutes'] ?? 45))), (string)($in['script'] ?? ''), bin2hex(random_bytes(12)), date('c')]);
    return (int)$pdo->lastInsertId();
}

function tm_get(PDO $pdo, int $id): ?array
{
    tm_schema($pdo);
    $q = $pdo->prepare('SELECT * FROM mm_termine WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function tm_by_token(PDO $pdo, string $t): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $t)) return null;
    tm_schema($pdo);
    $q = $pdo->prepare('SELECT * FROM mm_termine WHERE token = ?');
    $q->execute([$t]);
    return $q->fetch() ?: null;
}

function tm_list(PDO $pdo): array
{
    tm_schema($pdo);
    return $pdo->query("SELECT * FROM mm_termine WHERE status != 'geloescht' ORDER BY date DESC, id DESC LIMIT 20")->fetchAll();
}

function tm_link(array $t): string { return mm_site() . '/termin/?t=' . $t['token']; }

/** Gastgeberinnen-Fenster (Teleprompter + Whereby als Gastgeberin), wie bei den Gatherings. */
function tm_host_link(array $t): string
{
    if ((int)$t['gid'] <= 0) return '';
    mm_testroom_auth('');
    $tr = mm_cfg('testroom') ?? [];
    if (empty($tr['token'])) { try { $tr = mm_testroom(); } catch (Throwable $e) { $tr = mm_cfg('testroom') ?? []; } }
    return empty($tr['token']) ? '' : mm_site() . '/gathering/?test=' . rawurlencode((string)$tr['token']) . '&g=' . (int)$t['gid'];
}

function tm_ts(array $t, string $slot = ''): int
{
    return (new DateTimeImmutable($t['date'] . ' ' . ($slot !== '' ? $slot : $t['slot']), new DateTimeZone('Europe/Vienna')))->getTimestamp();
}

function tm_when(array $t, string $slot = ''): string
{
    $ts = tm_ts($t, $slot);
    $wd = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    return $wd[(int)date('N', $ts)] . ', ' . mv_date($ts) . ' um ' . date('G:i', $ts) . ' Uhr';
}

function tm_day(array $t): string
{
    $ts = tm_ts($t, '12:00');
    $wd = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    return $wd[(int)date('N', $ts)] . ', ' . mv_date($ts);
}

/** Freie Uhrzeiten: nicht vorbei, nicht schon woanders belegt (gemeinsamer Kalender). */
function tm_free_times(PDO $pdo, array $t): array
{
    $busy = [];
    try { require_once __DIR__ . '/belegt.inc.php'; $busy = vb_busy($pdo, $t['date'], $t['date'], ['termin']); } catch (Throwable $e) {}
    $out = [];
    foreach (explode(',', (string)$t['times']) as $s) {
        if ($s === '' || tm_ts($t, $s) < time() + 30 * 60) continue;
        if (function_exists('vb_free') && $busy && !vb_free($busy, $t['date'], vb_min($s), (int)$t['minutes'])) continue;
        $out[] = $s;
    }
    return $out;
}

function tm_ics(array $t, bool $host = false): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $f = fn(DateTime $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $esc = fn(string $v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $v);
    $from = new DateTime($t['date'] . ' ' . $t['slot'], $tz);
    $to = (clone $from)->modify('+' . (int)$t['minutes'] . ' minutes');
    $where = $host ? (tm_host_link($t) ?: tm_link($t)) : tm_link($t);
    $sum = $host ? 'Videogespräch: ' . $t['title'] . ' · ' . ($t['name'] ?: $t['guest']) : 'Videogespräch mit Vivien (Movecia): ' . $t['title'];
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Movecia//Termin//DE\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:termin-{$t['id']}@moveciamovement.com\r\nDTSTAMP:" . $f(new DateTime('now', $tz))
        . "\r\nDTSTART:" . $f($from) . "\r\nDTEND:" . $f($to) . "\r\nSUMMARY:" . $esc($sum) . "\r\nLOCATION:" . $esc($where)
        . "\r\nDESCRIPTION:" . $esc('Online per Video. Zum Videoraum: ' . $where) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}

/** Uhrzeit wählen → sofort fix (in einer Transaktion, damit nicht zweimal gebucht wird). */
function tm_book(PDO $pdo, array $t, string $slot, string $name, string $email, string $note): array
{
    if ($t['status'] !== 'offen') return ['ok' => false, 'error' => 'Dieser Termin ist schon vereinbart.'];
    if (!in_array($slot, tm_free_times($pdo, $t), true)) return ['ok' => false, 'error' => 'Diese Uhrzeit ist leider nicht mehr frei. Bitte wähl eine andere.'];
    $name = forma_clean($name, 80);
    $email = strtolower(trim($email));
    $note = trim(mb_substr($note, 0, 600));
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Bitte gib deinen Namen und deine E-Mail an, damit ich dir die Bestätigung schicken kann.'];
    $pdo->beginTransaction();
    $q = $pdo->prepare("UPDATE mm_termine SET status = 'fix', slot = ?, name = ?, email = ?, note = ?, booked_at = ? WHERE id = ? AND status = 'offen'");
    $q->execute([$slot, $name, $email, $note, date('c'), $t['id']]);
    if ($q->rowCount() !== 1) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Dieser Termin ist schon vereinbart.']; }
    $pdo->commit();
    $t = tm_get($pdo, (int)$t['id']);
    // Unsichtbares Gathering = Gastgeberinnen-Fenster mit Teleprompter + Whereby-Raum
    $title = $t['title'] . ' · ' . $name;
    $pdo->prepare("INSERT INTO mm_gatherings (title, starts_at, minutes, description, visible, created_at, script) VALUES (?,?,?,?,0,?,?)")
        ->execute([$title, date('c', tm_ts($t)), (int)$t['minutes'], 'Persönliches Videogespräch (Termin-Link)', date('c'), '']);
    $gid = (int)$pdo->lastInsertId();
    if (trim((string)$t['script']) !== '') {
        $p = mm_cfg('prompter') ?? [];
        $key = mb_substr('Gespräch · ' . $t['title'], 0, 80);
        $p['library'][$key] = (string)$t['script'];
        $p['hidden'] = array_values(array_diff($p['hidden'] ?? [], [$key]));
        mm_save_cfg('prompter', $p);
        $pdo->prepare('UPDATE mm_gatherings SET script = ? WHERE id = ?')->execute([$key, $gid]);
    }
    try { mm_whereby_room($pdo, $gid); } catch (Throwable $e) { error_log('TM room: ' . $e->getMessage()); }
    $pdo->prepare('UPDATE mm_termine SET gid = ? WHERE id = ?')->execute([$gid, $t['id']]);
    $t = tm_get($pdo, (int)$t['id']);
    tm_mails($pdo, $t);
    return ['ok' => true, 'when' => tm_when($t)];
}

/** Gesprächspunkte für die Gästin = [Überschriften] aus dem Leitfaden (ohne Begrüßung/Abschluss, nie der Sprechtext). */
function tm_agenda(array $t): array
{
    preg_match_all('/^\[([^\]]+)\]/m', (string)$t['script'], $m);
    return array_values(array_filter($m[1] ?? [], fn($x) => !preg_match('/^(Begrüßung|Abschluss|Sonstiges)$/u', trim($x))));
}

/** Schöne Bestätigung im Movecia-Academy-Design (Vivien, 29.10.2026: „um zu zeigen, wie organisiert wir sind“). */
function tm_guest_mail(array $t, bool $hasRoom = true): array
{
    $h = 'forma_h';
    $hello = $t['guest'] !== '' ? $t['guest'] : forma_first_name((string)$t['name']);
    $link = tm_link($t);
    $ts = tm_ts($t);
    $wd = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'][(int)date('N', $ts)];
    $end = date('G:i', $ts + (int)$t['minutes'] * 60);
    $ag = tm_agenda($t);
    $html = '<p style="margin:0 0 18px;"><img src="' . $h(mm_site()) . '/img/movecia/mail-academy-kopf.png?v=4" width="500" alt="Movecia Academy" style="display:block;width:100%;max-width:500px;height:auto;border:0;border-radius:18px;"></p>'
        . '<p style="margin:0 0 4px;font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:#6E6168;">Terminbestätigung</p>'
        . '<p style="margin:0 0 14px;font-family:Georgia,\'Times New Roman\',serif;font-size:28px;line-height:1.15;color:#2A2026;">Hallo ' . $h($hello) . ', unser Termin steht.</p>'
        . '<p style="margin:0 0 18px;">Danke, dass ihr euch Zeit nehmt! Hier ist alles auf einen Blick, die Kalenderdatei hängt an dieser Mail.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FBF9F6;border:1px solid #D3C9BC;border-radius:18px;"><tr>'
        . '<td width="92" align="center" valign="middle" style="padding:16px 0 16px 16px;"><table role="presentation" cellpadding="0" cellspacing="0" style="background:#4A3040;border-radius:14px;"><tr><td align="center" style="padding:8px 14px;color:#F7F1EE;font-family:Helvetica,Arial,sans-serif;">'
        . '<div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;">' . $h(mb_substr($wd, 0, 2)) . '</div><div style="font-family:Georgia,serif;font-size:30px;line-height:1.05;">' . date('j', $ts) . '</div><div style="font-size:11px;letter-spacing:.1em;">' . $h(['', 'Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'][(int)date('n', $ts)]) . '</div></td></tr></table></td>'
        . '<td valign="middle" style="padding:16px 18px;font-size:15px;line-height:1.55;"><b style="font-size:17px;">' . $h($t['title']) . '</b><br>' . $h($wd . ', ' . mv_date($ts)) . '<br>' . date('G:i', $ts) . ' bis ' . $end . ' Uhr · online per Video</td></tr></table>'
        . natal_btn($link, 'Zum Videogespräch')
        . '<p style="margin:0 0 6px;font-size:13px;color:#6E6168;text-align:center;">Der Raum öffnet sich 15 Minuten vorher, direkt im Browser, ganz ohne App und Konto.</p>';
    if ($ag) {
        $html .= '<p style="margin:24px 0 8px;font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:#6E6168;">Darüber sprechen wir</p><table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
        foreach ($ag as $i => $x) $html .= '<tr><td width="34" valign="top" style="padding:6px 0;"><span style="display:inline-block;width:24px;height:24px;line-height:24px;border-radius:50%;background:#E9E2D8;color:#4A3040;font-size:12px;text-align:center;">' . ($i + 1) . '</span></td><td style="padding:6px 0;font-size:15px;border-bottom:1px solid #E6DED3;">' . $h($x) . '</td></tr>';
        $html .= '</table><p style="margin:10px 0 0;font-size:14px;color:#6E6168;">Fehlt euch noch ein Punkt? Antwortet einfach auf diese Mail.</p>';
    }
    $html .= '<p style="margin:24px 0 8px;font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:#6E6168;">Gut zu wissen</p>'
        . '<p style="margin:0;font-size:15px;">Am besten am Laptop oder Handy mit Kopfhörern, Kamera und Mikrofon im Browser erlauben. Klappt etwas nicht, schreibt mir einfach kurz.</p>'
        . ($t['note'] !== '' ? '<p style="margin:16px 0 0;font-size:14px;color:#6E6168;">Eure Nachricht: „' . $h($t['note']) . '“</p>' : '')
        . '<p style="margin:20px 0 0;">Ich freu mich auf euch!</p>';
    $text = 'Hallo ' . $hello . ",\n\nunser Termin steht: " . $t['title'] . "\n" . $wd . ', ' . mv_date($ts) . ', ' . date('G:i', $ts) . ' bis ' . $end . " Uhr, online per Video.\n\nZum Videogespräch (öffnet sich 15 Minuten vorher, keine App nötig):\n" . $link . "\n"
        . ($ag ? "\nDarüber sprechen wir:\n- " . implode("\n- ", $ag) . "\n" : '') . "\nIch freu mich auf euch!\n\nAlles Liebe, Viv";
    return [$html, $text];
}

function tm_mails(PDO $pdo, array $t): void
{
    $g = mm_gathering($pdo, (int)$t['gid']);
    $hasRoom = $g && (string)$g['room_url'] !== '';
    [$html, $text] = tm_guest_mail($t, $hasRoom);
    try { mm_mail((string)$t['email'], 'Terminbestätigung: ' . $t['title'] . ' · ' . tm_when($t), $text, mm_mail_html('Unser Videogespräch', $html, 'de'), [['termin.ics', tm_ics($t), 'text/calendar']], 'Vivien · Movecia'); } catch (Throwable $e) { error_log('TM mail guest: ' . $e->getMessage()); }
    $host = tm_host_link($t);
    $v = "Hallo Viv,\n\n" . $t['name'] . ' (' . $t['email'] . ') hat sich eine Uhrzeit ausgesucht:' . "\n\n" . $t['title'] . "\n" . tm_when($t) . ', ' . (int)$t['minutes'] . " Minuten\n"
        . ($t['note'] !== '' ? "\nNachricht: " . $t['note'] . "\n" : '')
        . "\nDein Gastgeberinnen-Fenster mit Teleprompter (dein Gesprächsleitfaden ist schon geladen):\n" . ($host ?: 'noch kein Whereby-Schlüssel hinterlegt, bitte in Movecia → Einstellungen eintragen') . "\n"
        . ($hasRoom ? '' : "\nAchtung: Der Videoraum konnte nicht angelegt werden. Bitte prüf in der Verwaltung den Whereby-Schlüssel.\n")
        . "\nAlles auch in der Verwaltung unter Movecia → Übersicht → Termin-Links.\n";
    try { mm_mail(natal_notify_email_tm(), 'Termin fix: ' . $t['title'] . ' · ' . tm_when($t), $v, mm_mail_html('Termin fix', nl2br(forma_h($v)), 'de'), [['termin.ics', tm_ics($t, true), 'text/calendar']], 'Movecia · Termine'); } catch (Throwable $e) { error_log('TM mail viv: ' . $e->getMessage()); }
}

function natal_notify_email_tm(): string
{
    if (!function_exists('natal_notify_email')) require_once __DIR__ . '/natal-lib.php';
    return natal_notify_email();
}

/** Vivien sagt ab oder gibt den Termin wieder frei (Gästin wählt neu). */
function tm_reset(PDO $pdo, int $id): void
{
    $t = tm_get($pdo, $id);
    if (!$t) return;
    if ((int)$t['gid'] > 0) $pdo->prepare('DELETE FROM mm_gatherings WHERE id = ?')->execute([(int)$t['gid']]);
    $pdo->prepare("UPDATE mm_termine SET status = 'offen', slot = '', gid = 0, booked_at = '' WHERE id = ?")->execute([$id]);
}

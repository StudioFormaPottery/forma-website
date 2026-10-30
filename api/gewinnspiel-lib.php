<?php
// Movecia Weihnachts-Gewinnspiel (Vivien, 30.10.2026).
// Teilnahme über moveciamovement.com/gewinnspiel: Vorname + E-Mail + Wunschgewinn, Double-Opt-in.
// Gewinn nach Wahl: 3 Monate The Mindful Movement Method (regulär € 111) oder 2 Online-Einheiten
// Prä- oder Postnatal (2 × € 54 = € 108, Postnatal-Paketpreis). Auslosung in der Verwaltung → Movecia → Übersicht.
declare(strict_types=1);
require_once __DIR__ . '/movecia-lib.php';

const GW_END = '2026-12-20 23:59:59';   // Teilnahmeschluss (Wien)
const GW_DRAW = '2026-12-21';          // Auslosung
const GW_SHOW_UNTIL = '2027-01-31';    // so lange steht der Bereich in der Verwaltung; danach werden Nicht-Gewinnerinnen gelöscht
const GW_PRIZES = [
    'method' => ['3 Monate The Mindful Movement Method', 'Meine Videobibliothek für zu Hause: Flows für volle Tage, kurze Einheiten für müde Tage, Atem und Meditation.'],
    'prae'   => ['2 Online-Einheiten Pränatal', 'Zwei Einheiten 1:1 mit mir per Video, für dich und dein Baby in der Schwangerschaft.'],
    'post'   => ['2 Online-Einheiten Postnatal', 'Zwei Einheiten 1:1 mit mir per Video, für deinen Weg zurück in deinen Körper nach der Geburt.'],
];

function gw_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS mm_gewinnspiel (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        prize TEXT NOT NULL,
        news INTEGER NOT NULL DEFAULT 0,
        token TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'offen',
        ip_hash TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        confirmed_at TEXT NOT NULL DEFAULT '',
        winner INTEGER NOT NULL DEFAULT 0,
        drawn_at TEXT NOT NULL DEFAULT '',
        granted_at TEXT NOT NULL DEFAULT ''
    )");
    // Datensparsam: nach dem Gewinnspiel bleiben nur die Gewinnerinnen (Newsletter-Anmeldungen liegen ohnehin in mm_subscribers)
    if (date('Y-m-d') > GW_SHOW_UNTIL) $pdo->exec('DELETE FROM mm_gewinnspiel WHERE winner = 0');
}

function gw_open(): bool { return time() <= strtotime(GW_END); }

function gw_link(array $r): string { return mm_site() . '/api/gewinnspiel.php?t=' . $r['token']; }

/** Teilnahme eintragen und Bestätigungsmail schicken. */
function gw_enter(PDO $pdo, array $in): array
{
    gw_schema($pdo);
    $name = trim((string)($in['name'] ?? ''));
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $prize = (string)($in['prize'] ?? '');
    if (!empty($in['website'])) return ['ok' => true];
    if (!gw_open()) return ['ok' => false, 'error' => 'Das Gewinnspiel ist leider schon vorbei.'];
    if ((int)($in['elapsed'] ?? 0) < 2000) return ['ok' => false, 'error' => 'Einen Moment bitte, dann versuch es noch einmal.'];
    if (mb_strlen($name) < 2) return ['ok' => false, 'error' => 'Bitte gib deinen Vornamen ein.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Bitte prüf deine E-Mail-Adresse.'];
    if (!isset(GW_PRIZES[$prize])) return ['ok' => false, 'error' => 'Bitte such dir aus, was du gewinnen möchtest.'];
    if (empty($in['terms'])) return ['ok' => false, 'error' => 'Bitte bestätige die Teilnahmebedingungen.'];
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
    $c = $pdo->prepare('SELECT COUNT(*) FROM mm_gewinnspiel WHERE ip_hash = ? AND created_at >= ?');
    $c->execute([$ip, date('c', time() - 3600)]);
    if ((int)$c->fetchColumn() >= 6) return ['ok' => false, 'error' => 'Zu viele Anmeldungen von hier. Bitte versuch es später noch einmal.'];
    $news = !empty($in['news']) ? 1 : 0;
    $q = $pdo->prepare('SELECT * FROM mm_gewinnspiel WHERE email = ?');
    $q->execute([$email]);
    $r = $q->fetch();
    if ($r && $r['status'] === 'bestaetigt') {
        // schon im Lostopf: nur den Wunschgewinn aktualisieren, keine zweite Chance
        $pdo->prepare('UPDATE mm_gewinnspiel SET prize = ?, news = MAX(news, ?) WHERE id = ?')->execute([$prize, $news, $r['id']]);
        if ($news && !(int)$r['news']) { $r['news'] = 1; gw_newsletter($pdo, $r); }
        return ['ok' => true, 'already' => true];
    }
    if ($r) {
        $pdo->prepare('UPDATE mm_gewinnspiel SET name = ?, prize = ?, news = ?, ip_hash = ? WHERE id = ?')->execute([mb_substr($name, 0, 80), $prize, $news, $ip, $r['id']]);
        $r = array_merge($r, ['name' => mb_substr($name, 0, 80), 'prize' => $prize, 'news' => $news]);
    } else {
        $tok = bin2hex(random_bytes(16));
        $pdo->prepare('INSERT INTO mm_gewinnspiel (name, email, prize, news, token, ip_hash, created_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([mb_substr($name, 0, 80), mb_substr($email, 0, 160), $prize, $news, $tok, $ip, date('c')]);
        $r = ['id' => (int)$pdo->lastInsertId(), 'name' => mb_substr($name, 0, 80), 'email' => $email, 'prize' => $prize, 'news' => $news, 'token' => $tok];
    }
    return ['ok' => gw_mail_confirm($r)];
}

function gw_mail_confirm(array $r): bool
{
    $first = forma_first_name((string)$r['name']);
    $link = gw_link($r);
    $hand = 'margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#4A3040;';
    $text = "Hallo $first,\n\nschön, dass du beim Weihnachts-Gewinnspiel von Movecia mitmachst!\n\nBitte bestätige noch deine E-Mail-Adresse, erst dann bist du im Lostopf:\n$link\n\nFalls du dich nicht eingetragen hast, ignoriere diese Mail einfach.\n\nAlles Liebe,\nVivien";
    $html = '<p style="' . $hand . '">Hallo ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;">schön, dass du beim <strong>Weihnachts-Gewinnspiel</strong> von Movecia mitmachst! 🎁</p>'
        . '<p style="margin:14px 0 0;">Bitte bestätige noch deine E-Mail-Adresse, erst dann bist du im Lostopf.</p>'
        . mm_button($link, 'Ja, ich bin dabei')
        . '<p style="margin:16px 0 0;font-size:14px;color:#6E6168;">Falls du dich nicht eingetragen hast, ignoriere diese Mail einfach.</p>';
    return mm_mail((string)$r['email'], 'Bitte bestätige deine Teilnahme am Gewinnspiel 🎁', $text, mm_mail_html('Teilnahme bestätigen', $html, 'de'), [], 'Vivien · Movecia');
}

/** Bestätigungslink: in den Lostopf, „Du bist dabei“-Mail, Newsletter nur mit eigenem Häkchen. */
function gw_confirm(PDO $pdo, string $token): ?array
{
    gw_schema($pdo);
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $q = $pdo->prepare('SELECT * FROM mm_gewinnspiel WHERE token = ?');
    $q->execute([$token]);
    $r = $q->fetch();
    if (!$r) return null;
    if ($r['status'] !== 'bestaetigt') {
        $u = $pdo->prepare("UPDATE mm_gewinnspiel SET status = 'bestaetigt', confirmed_at = ? WHERE id = ? AND status <> 'bestaetigt'");
        $u->execute([date('c'), $r['id']]);
        if ($u->rowCount() === 1) {
            $r['status'] = 'bestaetigt';
            gw_mail_in($r);
            if ((int)$r['news']) gw_newsletter($pdo, $r);
        }
    }
    return $r;
}

function gw_mail_in(array $r): void
{
    $first = forma_first_name((string)$r['name']);
    $p = GW_PRIZES[$r['prize']] ?? GW_PRIZES['method'];
    $hand = 'margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#4A3040;';
    $draw = '21. Dezember';
    $text = "Hallo $first,\n\ndu bist im Lostopf! 🎁\n\nDein Wunschgewinn: {$p[0]}.\n\nAm $draw lose ich die Gewinnerin aus. Wenn du es bist, schreibe ich dir hier per E-Mail, und auf Instagram gebe ich sie bei @moveciamovement bekannt. Ich frage dich dabei nie nach Bankdaten oder Passwörtern.\n\nIch drücke dir die Daumen,\nVivien";
    $html = '<p style="' . $hand . '">Hallo ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;font-size:20px;">du bist im Lostopf! 🎁</p>'
        . '<p style="margin:14px 0 0;">Dein Wunschgewinn: <strong>' . forma_h($p[0]) . '</strong>.</p>'
        . '<p style="margin:14px 0 0;">Am ' . $draw . ' lose ich die Gewinnerin aus. Wenn du es bist, schreibe ich dir hier per E-Mail, und auf Instagram gebe ich sie bei @moveciamovement bekannt. Ich frage dich dabei nie nach Bankdaten oder Passwörtern.</p>'
        . '<p style="margin:14px 0 0;">Ich drücke dir die Daumen,<br>Vivien</p>';
    mm_mail((string)$r['email'], 'Du bist im Lostopf 🎁', $text, mm_mail_html('Du bist dabei', $html, 'de'), [], 'Vivien · Movecia');
}

/** Newsletter-Häkchen: in die Movecia-Liste (bereits bestätigt über das Gewinnspiel) und zu MailerLite. */
function gw_newsletter(PDO $pdo, array $r): void
{
    try {
        $s = $pdo->prepare('SELECT * FROM mm_subscribers WHERE email = ?');
        $s->execute([strtolower((string)$r['email'])]);
        $sub = $s->fetch();
        if (!$sub) {
            $pdo->prepare("INSERT INTO mm_subscribers (name, email, source, lang, token, ip_hash, created_at, status, confirmed_at) VALUES (?,?,'gewinnspiel','de',?,'',?,'bestaetigt',?)")
                ->execute([$r['name'], strtolower((string)$r['email']), bin2hex(random_bytes(16)), date('c'), date('c')]);
            $sub = ['id' => (int)$pdo->lastInsertId(), 'name' => $r['name'], 'email' => strtolower((string)$r['email']), 'source' => 'gewinnspiel'];
        } elseif ($sub['status'] !== 'bestaetigt') {
            $pdo->prepare("UPDATE mm_subscribers SET status = 'bestaetigt', confirmed_at = ? WHERE id = ?")->execute([date('c'), $sub['id']]);
        }
        mm_ml_push($pdo, $sub);
    } catch (Throwable $e) {
        error_log('GW newsletter: ' . $e->getMessage());
    }
}

function gw_list(PDO $pdo): array
{
    gw_schema($pdo);
    return $pdo->query('SELECT * FROM mm_gewinnspiel ORDER BY winner DESC, id DESC')->fetchAll();
}

function gw_winner(PDO $pdo): ?array
{
    gw_schema($pdo);
    $r = $pdo->query('SELECT * FROM mm_gewinnspiel WHERE winner = 1 ORDER BY id DESC LIMIT 1')->fetch();
    return $r ?: null;
}

/** Zufällig aus allen bestätigten Teilnahmen bis Teilnahmeschluss. Eine bisherige Gewinnerin ohne Rückmeldung zählt als verfallen (winner = 2). */
function gw_draw(PDO $pdo): ?array
{
    gw_schema($pdo);
    $pdo->exec('UPDATE mm_gewinnspiel SET winner = 2 WHERE winner = 1 AND granted_at = \'\'');
    $q = $pdo->prepare("SELECT * FROM mm_gewinnspiel WHERE status = 'bestaetigt' AND winner = 0 AND confirmed_at <= ?");
    $q->execute([date('c', strtotime(GW_END) + 2 * 86400)]); // bestätigt kurz nach Schluss zählt noch, eingetragen war sie rechtzeitig
    $all = array_values(array_filter($q->fetchAll(), fn($r) => strtotime((string)$r['created_at']) <= strtotime(GW_END)));
    if (!$all) return null;
    $w = $all[random_int(0, count($all) - 1)];
    $pdo->prepare('UPDATE mm_gewinnspiel SET winner = 1, drawn_at = ? WHERE id = ?')->execute([date('c'), $w['id']]);
    $w['winner'] = 1;
    return $w;
}

/** Gewinn freischalten und Gewinnerin per Mail benachrichtigen. */
function gw_grant(PDO $pdo, array $r): bool
{
    if ($r['granted_at'] !== '' || (int)$r['winner'] !== 1) return false;
    $first = forma_first_name((string)$r['name']);
    $email = strtolower((string)$r['email']);
    $p = GW_PRIZES[$r['prize']] ?? GW_PRIZES['method'];
    if ($r['prize'] === 'method') {
        require_once __DIR__ . '/method-lib.php';
        mv_schema($pdo);
        $m = mv_member_ensure($pdo, $email, (string)$r['name'], 'de');
        $pdo->prepare('UPDATE mv_members SET email_ok = 1 WHERE id = ?')->execute([$m['id']]);
        $m = mv_member($pdo, (int)$m['id']);
        $open = max(mv_launch(), time());
        $pdo->prepare("INSERT INTO mv_subs (kind, plan, email, name, lang, call_lang, amount_cents, first_cents, status, token, consent_digital, consent_live, source, test, created_at, paid_at, period_end, bill_start, open_from, open_mailed, review_sent, mailed) VALUES ('method','m3',?,?,'de','de',0,0,'aktiv',?,1,1,'gewinnspiel',0,?,?,?,?,?,1,1,1)")
            ->execute([$email, $r['name'], bin2hex(random_bytes(16)), date('c'), date('c'), $open + 92 * 86400, $open, $open]);
        $hasPw = (string)$m['pass_hash'] !== '';
        $link = $hasPw ? mv_area() : mv_pw_link($pdo, $m, 24 * 14);
        $btn = $hasPw ? 'Zum Mitgliederbereich' : 'Passwort festlegen & loslegen';
        $how = 'Dein Zugang ist schon freigeschaltet und läuft drei Monate, bis ' . date('j.n.Y', $open + 92 * 86400) . '. ' . ($hasPw ? 'Melde dich einfach mit deiner E-Mail und deinem Passwort an.' : 'Leg einmal dein Passwort fest, danach meldest du dich auf jedem Gerät mit deiner E-Mail an.') . ' Du startest mit dem Ankommen-Modul, danach öffnet sich dein erster Monat. Es verlängert sich nichts und es wird nichts abgebucht.';
    } else {
        require_once __DIR__ . '/natal-lib.php';
        natal_schema($pdo);
        if (function_exists('natal_member_schema')) natal_member_schema($pdo);
        $key = $r['prize'] === 'prae' ? 'o_prae' : 'o_post';
        $title = ($r['prize'] === 'prae' ? 'Pränatal' : 'Postnatal') . ' online · Gewinn Weihnachts-Gewinnspiel';
        $now = date('c');
        $pdo->prepare("INSERT INTO mm_natal_orders (package, title, units, unit_cents, amount_cents, name, email, phone, due_date, note, wishes, status, token, consent_start, created_at, paid_at, is_test) VALUES (?,?,2,0,0,?,?,'','','Gewinn aus dem Weihnachts-Gewinnspiel 2026','[]','bezahlt',?,1,?,?,0)")
            ->execute([$key, $title, $r['name'], $email, bin2hex(random_bytes(16)), $now, $now]);
        $o = natal_order($pdo, (int)$pdo->lastInsertId());
        $link = natal_link($o);
        $btn = 'Meine Termine wählen';
        $how = 'Deine zwei Einheiten sind schon für dich angelegt. Über den Knopf kommst du auf deine persönliche Seite: Dort füllst du einmal kurz den Gesundheitsfragebogen aus und wählst deine Wunschtermine, ich bestätige dir dann jeden Termin mit Uhrzeit. Wir treffen uns live per Video, du brauchst nur eine Matte, etwas Platz und dein Handy oder Laptop. Bitte sprich das Training vorher mit deiner Ärztin oder Hebamme ab.';
    }
    $hand = 'margin:0;font-family:\'Brush Script MT\',\'Segoe Script\',cursive;font-size:26px;color:#4A3040;';
    $text = "Hallo $first,\n\ndu hast gewonnen! 🎁\n\nBeim Weihnachts-Gewinnspiel von Movecia bist du die Gewinnerin, und ich freue mich so für dich. Dein Gewinn: {$p[0]}.\n\n$how\n\n$btn: $link\n\nFrohe Weihnachten und alles Liebe,\nVivien";
    $html = '<p style="' . $hand . '">Hallo ' . forma_h($first) . ',</p>'
        . '<p style="margin:14px 0 0;font-size:22px;">du hast gewonnen! 🎁</p>'
        . '<p style="margin:14px 0 0;">Beim Weihnachts-Gewinnspiel von Movecia bist du die Gewinnerin, und ich freue mich so für dich. Dein Gewinn: <strong>' . forma_h($p[0]) . '</strong>.</p>'
        . '<p style="margin:14px 0 0;">' . forma_h($how) . '</p>'
        . mm_button($link, $btn)
        . '<p style="margin:16px 0 0;">Frohe Weihnachten und alles Liebe,<br>Vivien</p>';
    $ok = mm_mail($email, 'Du hast gewonnen! 🎁', $text, mm_mail_html('Du hast gewonnen', $html, 'de'), [], 'Vivien · Movecia');
    $pdo->prepare('UPDATE mm_gewinnspiel SET granted_at = ? WHERE id = ?')->execute([date('c'), $r['id']]);
    return $ok;
}

<?php
// Mitgliederbereich Prä- & Postnatal (Vivien, 24.10.2026): moveciamovement.com/natal/mitglied/
// Wer ein Paket (mehr als 1 Einheit) bucht, legt nach der Zahlung mit E-Mail + Passwort ein Konto an und sieht dort
// pro Einheit Aufnahme (nur Viviens Kamera), Workbook, Übungsabfolge, eine Affirmation und Musik.
// Einzelstunde: kein Mitgliederbereich, nur die persönliche Terminseite mit dem Videoraum.
// Zugang endet einen Monat nach der letzten Einheit, dann werden Konto, Aufnahmen und Gesundheitsangaben gelöscht.
declare(strict_types=1);

const NATAL_MEMBER_COOKIE = 'mvm';
const NATAL_MEMBER_GRACE_DAYS = 30;

function natal_member_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $oc = array_column($pdo->query('PRAGMA table_info(mm_natal_orders)')->fetchAll(), 'name');
    foreach (['is_test' => 'INTEGER NOT NULL DEFAULT 0', 'health' => "TEXT NOT NULL DEFAULT ''", 'health_at' => 'TEXT', 'purged_at' => 'TEXT', 'warned_at' => 'TEXT', 'birth_date' => "TEXT NOT NULL DEFAULT ''", 'birth_mailed_at' => 'TEXT', 'review_sent_at' => 'TEXT'] as $c => $t) {
        if ($oc && !in_array($c, $oc, true)) $pdo->exec("ALTER TABLE mm_natal_orders ADD COLUMN $c $t");
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_natal_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL DEFAULT \'\',
        pass_hash TEXT NOT NULL,
        reset_token TEXT NOT NULL DEFAULT \'\',
        reset_until TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        last_login TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_natal_recs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        order_id INTEGER NOT NULL,
        file TEXT NOT NULL,
        mime TEXT NOT NULL DEFAULT \'video/webm\',
        bytes INTEGER NOT NULL DEFAULT 0,
        seconds INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT \'upload\',
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_natal_login_fail (ip_hash TEXT NOT NULL, at TEXT NOT NULL)');
}

// --- Einheiten je Paket ------------------------------------------------------------------------

const NATAL_PRAE_KEYS = ['prae1', 'prae2', 'prae3', 'prae4', 'prae5', 'prae6'];
const NATAL_POST_KEYS = ['post1', 'post2', 'post3', 'post4', 'post5', 'post6', 'post7', 'post8'];

/** Paket mit Mitgliederbereich? (alles außer Einzelstunde) */
function natal_order_is_member(array $o): bool
{
    return (int)$o['units'] > 1;
}

/** Termine des Pakets mit ihrer Einheit: [[key, session|null], …] in Reihenfolge.
 *  Ohne Geburt: Pränatal 1–6, Postnatal 1–8, Kombi erst 6 prä, dann post.
 *  „Baby ist da“ (birth_date, Vivien 24.10.2026): alle Termine ab dem Geburtstag rutschen ins Postnatal (ab Postnatal 1). */
function natal_order_plan(PDO $pdo, array $o): array
{
    $pk = preg_replace('/^o_/', '', (string)$o['package']);
    $ss = array_values(array_filter(natal_sessions($pdo, (int)$o['id']), fn($s) => $s['status'] !== 'abgesagt'));
    $birth = (string)($o['birth_date'] ?? '');
    $units = max(1, (int)$o['units']);
    $out = [];
    $pi = 0; $qi = 0;
    for ($i = 0; $i < $units; $i++) {
        $s = $ss[$i] ?? null;
        if ($pk === 'post') $isPost = true;
        elseif ($birth !== '') $isPost = !$s || (string)$s['date'] >= $birth || $pi >= 6;
        elseif ($pk === 'einzel') $isPost = (string)$o['due_date'] !== '' && ($s ? (string)$s['date'] : date('Y-m-d')) > (string)$o['due_date'];
        else $isPost = $pi >= 6;
        if ($isPost) $out[] = [NATAL_POST_KEYS[min($qi++, 7)], $s];
        else $out[] = [NATAL_PRAE_KEYS[min($pi++, 5)], $s];
    }
    return $out;
}

/** Einheit eines bestimmten Termins (für Teleprompter und Workbook). */
function natal_session_key(PDO $pdo, array $o, int $sid): string
{
    foreach (natal_order_plan($pdo, $o) as [$k, $s]) if ($s && (int)$s['id'] === $sid) return $k;
    return 'frei';
}

/** Ist dieser Termin schon gewesen? */
function natal_session_done(?array $s): bool
{
    if (!$s) return false;
    if ($s['status'] === 'erledigt') return true;
    if ($s['status'] !== 'bestaetigt' || (string)$s['start'] === '') return false;
    return strtotime($s['date'] . ' ' . $s['start']) + (int)$s['minutes'] * 60 <= time();
}

/** Bis wann der Mitgliederbereich gilt (Y-m-d) oder '' solange noch Einheiten offen sind.
 *  Alle Einheiten vorbei → letzter Termin + 30 Tage. Spätestens Geburtstermin + 6 Monate + 30 Tage. */
function natal_order_until(PDO $pdo, array $o): string
{
    $plan = natal_order_plan($pdo, $o);
    $last = '';
    $all = true;
    foreach ($plan as [, $s]) {
        if (!natal_session_done($s)) { $all = false; continue; }
        $last = max($last, (string)$s['date']);
    }
    $grace = '+' . NATAL_MEMBER_GRACE_DAYS . ' days';
    $hard = (string)$o['due_date'] !== '' ? date('Y-m-d', strtotime($o['due_date'] . ' +6 months ' . $grace)) : date('Y-m-d', strtotime(($o['paid_at'] ?: $o['created_at']) . ' +12 months ' . $grace));
    if ($all && $last !== '') return min($hard, date('Y-m-d', strtotime($last . ' ' . $grace)));
    return $hard;
}

/** Läuft der Bereich schon im „letzten Monat“ (alle Einheiten vorbei)? */
/** Ende der letzten erledigten Einheit (Zeitstempel), 0 wenn keine. */
function natal_order_last_end(PDO $pdo, array $o): int
{
    $end = 0;
    foreach (natal_order_plan($pdo, $o) as [, $s]) if ($s && (string)$s['start'] !== '') $end = max($end, strtotime($s['date'] . ' ' . $s['start']) + (int)$s['minutes'] * 60);
    return $end;
}

function natal_order_finished(PDO $pdo, array $o): bool
{
    foreach (natal_order_plan($pdo, $o) as [, $s]) if (!natal_session_done($s)) return false;
    return true;
}

/** Prä oder post für Icons und Affirmationen. */
function natal_member_phase(array $o): string
{
    $hd = function_exists('natal_health_get') ? natal_health_get($o) : null;
    if ((string)($o['birth_date'] ?? '') !== '') return 'post';
    if ((string)$o['due_date'] !== '' && date('Y-m-d') >= (string)$o['due_date']) return 'post';
    if ($hd && $hd['phase'] === 'post') return 'post';
    return preg_replace('/^o_/', '', (string)$o['package']) === 'post' ? 'post' : 'prae';
}

// --- Konten & Anmeldung ------------------------------------------------------------------------

function natal_member_link(): string
{
    return mm_site() . '/natal/mitglied/';
}

/** Link aus der Buchungsmail: Passwort wählen (Konto anlegen) für genau diese Buchung. */
function natal_member_start_link(array $o): string
{
    return natal_member_link() . '?start=' . (int)$o['id'] . '&k=' . $o['token'];
}

function natal_member_by_email(PDO $pdo, string $email): ?array
{
    $q = $pdo->prepare('SELECT * FROM mm_natal_members WHERE email = ?');
    $q->execute([strtolower(trim($email))]);
    return $q->fetch() ?: null;
}

/** Bezahlte Pakete dieser E-Mail, die noch nicht gelöscht sind. */
function natal_member_orders(PDO $pdo, string $email): array
{
    $q = $pdo->prepare("SELECT * FROM mm_natal_orders WHERE lower(email) = ? AND status = 'bezahlt' AND units > 1 AND purged_at IS NULL ORDER BY id");
    $q->execute([strtolower(trim($email))]);
    return $q->fetchAll();
}

function natal_member_sig(int $id, int $exp, string $hash): string
{
    // Passwort-Hash fließt mit ein: Passwort ändern meldet alle anderen Geräte ab
    return hash_hmac('sha256', "member:$id:$exp:" . substr($hash, -16), natal_secret());
}

function natal_member_login(array $m, PDO $pdo): void
{
    $exp = time() + 60 * 86400;
    $v = $m['id'] . '.' . $exp . '.' . natal_member_sig((int)$m['id'], $exp, (string)$m['pass_hash']);
    setcookie(NATAL_MEMBER_COOKIE, $v, ['expires' => $exp, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) || str_starts_with(mm_site(), 'https'), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[NATAL_MEMBER_COOKIE] = $v;
    mm_dev_login($pdo, 'natal', (int)$m['id']);
    $pdo->prepare('UPDATE mm_natal_members SET last_login = ? WHERE id = ?')->execute([date('c'), $m['id']]);
}

function natal_member_logout(): void
{
    setcookie(NATAL_MEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE[NATAL_MEMBER_COOKIE]);
}

function natal_member_current(PDO $pdo): ?array
{
    $c = (string)($_COOKIE[NATAL_MEMBER_COOKIE] ?? '');
    if (!preg_match('/^(\d+)\.(\d+)\.([0-9a-f]{64})$/', $c, $m) || (int)$m[2] < time()) return null;
    $q = $pdo->prepare('SELECT * FROM mm_natal_members WHERE id = ?');
    $q->execute([(int)$m[1]]);
    $row = $q->fetch();
    if (!$row || !hash_equals(natal_member_sig((int)$row['id'], (int)$m[2], (string)$row['pass_hash']), $m[3])) return null;
    if (!mm_dev_check($pdo, 'natal', (int)$row['id'])) { natal_member_logout(); return null; } // ein Zugang = eine Person
    return $row;
}

function natal_member_set_password(PDO $pdo, string $email, string $name, string $pw): array
{
    $email = strtolower(trim($email));
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $m = natal_member_by_email($pdo, $email);
    if ($m) $pdo->prepare("UPDATE mm_natal_members SET pass_hash = ?, reset_token = '', reset_until = '' WHERE id = ?")->execute([$hash, $m['id']]);
    else $pdo->prepare('INSERT INTO mm_natal_members (email, name, pass_hash, created_at) VALUES (?,?,?,?)')->execute([$email, $name, $hash, date('c')]);
    return natal_member_by_email($pdo, $email);
}

/** Zu viele Fehlversuche von hier? (10 in 15 Minuten) */
function natal_member_throttled(PDO $pdo, string $ipHash): bool
{
    $pdo->prepare('DELETE FROM mm_natal_login_fail WHERE at < ?')->execute([date('c', time() - 86400)]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM mm_natal_login_fail WHERE ip_hash = ? AND at >= ?');
    $q->execute([$ipHash, date('c', time() - 900)]);
    return (int)$q->fetchColumn() >= 10;
}

function natal_member_fail(PDO $pdo, string $ipHash): void
{
    $pdo->prepare('INSERT INTO mm_natal_login_fail (ip_hash, at) VALUES (?,?)')->execute([$ipHash, date('c')]);
}

/** Mail „Passwort neu setzen“ (nur wenn es ein aktives Konto gibt; Antwort an die Seite ist immer gleich). */
function natal_member_send_reset(PDO $pdo, string $email): void
{
    $m = natal_member_by_email($pdo, $email);
    if (!$m || !natal_member_orders($pdo, $email)) return;
    $tok = bin2hex(random_bytes(20));
    $pdo->prepare('UPDATE mm_natal_members SET reset_token = ?, reset_until = ? WHERE id = ?')->execute([hash('sha256', $tok), date('c', time() + 3600), $m['id']]);
    natal_mail((string)$m['email'], 'Dein neues Passwort für Movecia', 'Neues Passwort', [
        'Hallo ' . forma_first_name((string)$m['name']) . ',',
        'hier kannst du dir ein neues Passwort für deinen Mitgliederbereich aussuchen. Der Link gilt eine Stunde.',
        'Wenn du das nicht angefordert hast, ignorier diese E-Mail einfach.',
    ], [natal_member_link() . '?reset=' . $tok, 'Neues Passwort wählen']);
}

function natal_member_by_reset(PDO $pdo, string $tok): ?array
{
    if (!preg_match('/^[0-9a-f]{40}$/', $tok)) return null;
    $q = $pdo->prepare('SELECT * FROM mm_natal_members WHERE reset_token = ? AND reset_until >= ?');
    $q->execute([hash('sha256', $tok), date('c')]);
    return $q->fetch() ?: null;
}

// --- Aufnahmen (nur Viviens Kamera und Mikro) ---------------------------------------------------

function natal_rec_dir(): string
{
    $d = forma_data_dir() . '/natal-aufnahmen';
    if (!is_dir($d)) { @mkdir($d, 0700, true); @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
    return $d;
}

/** Fertige Aufnahme eines Termins (neueste) oder null. */
function natal_rec_for(PDO $pdo, int $sid): ?array
{
    $q = $pdo->prepare("SELECT * FROM mm_natal_recs WHERE session_id = ? AND status = 'fertig' ORDER BY id DESC LIMIT 1");
    $q->execute([$sid]);
    return $q->fetch() ?: null;
}

function natal_rec_delete(PDO $pdo, array $r): void
{
    @unlink(natal_rec_dir() . '/' . basename((string)$r['file']));
    $pdo->prepare('DELETE FROM mm_natal_recs WHERE id = ?')->execute([$r['id']]);
}

/** Signierter Link zum Ansehen/Herunterladen einer Aufnahme (für die Verwaltung). */
function natal_rec_admin_key(int $rid): string
{
    return substr(hash_hmac('sha256', 'rec:' . $rid, natal_secret()), 0, 24);
}

// --- Aufräumen: einen Monat nach der letzten Einheit alles löschen --------------------------------

function natal_member_run(PDO $pdo): void
{
    natal_member_schema($pdo);
    $today = date('Y-m-d');
    $rows = $pdo->query("SELECT * FROM mm_natal_orders WHERE status = 'bezahlt' AND purged_at IS NULL")->fetchAll();
    foreach ($rows as $o) {
        $until = natal_order_until($pdo, $o);
        // Erinnerung 7 Tage vorher (nur Pakete mit Mitgliederbereich, wenn alle Einheiten vorbei sind)
        if (natal_order_is_member($o) && !$o['warned_at'] && natal_order_finished($pdo, $o) && $today >= date('Y-m-d', strtotime($until . ' -7 days')) && $today < $until) {
            $pdo->prepare('UPDATE mm_natal_orders SET warned_at = ? WHERE id = ?')->execute([date('c'), $o['id']]);
            natal_mail((string)$o['email'], 'Dein Movecia-Bereich ist noch bis ' . date('j. n.', strtotime($until)) . ' für dich da', 'Noch eine Woche', [
                'Hallo ' . forma_first_name((string)$o['name']) . ',',
                'dein Mitgliederbereich mit allen Aufnahmen und Workbooks ist noch bis ' . natal_date_de($until) . ' für dich da. Danach lösche ich ihn mit allen Aufnahmen und deinen Gesundheitsangaben, so wie besprochen.',
                'Wenn du magst, lade dir deine Workbooks vorher noch herunter. Sie gehören dir.',
            ], [natal_member_link(), 'Zu meinem Bereich']);
        }
        // Google-Bewertung (Vivien, 25.10.2026): eine Stunde nach der letzten Einheit, genau einmal
        if (!$o['review_sent_at'] && mm_review_url() !== '' && natal_order_finished($pdo, $o) && natal_order_last_end($pdo, $o) + 3600 <= time()) {
            $u = $pdo->prepare('UPDATE mm_natal_orders SET review_sent_at = ? WHERE id = ? AND review_sent_at IS NULL');
            $u->execute([date('c'), $o['id']]);
            if ($u->rowCount() === 1) { try { mm_review_mail((string)$o['email'], (string)$o['name'], 'de', (int)$o['units'] > 1 ? 'natal' : 'natal1', (int)($o['is_test'] ?? 0) === 1); } catch (Throwable $e) { error_log('Natal review: ' . $e->getMessage()); } }
        }
        if ($today < $until) continue;
        $rs = $pdo->prepare('SELECT * FROM mm_natal_recs WHERE order_id = ?');
        $rs->execute([$o['id']]);
        foreach ($rs->fetchAll() as $r) natal_rec_delete($pdo, $r);
        $pdo->prepare("UPDATE mm_natal_orders SET purged_at = ?, health = '' WHERE id = ?")->execute([date('c'), $o['id']]);
        if (!natal_member_orders($pdo, (string)$o['email'])) $pdo->prepare('DELETE FROM mm_natal_members WHERE email = ?')->execute([strtolower((string)$o['email'])]);
    }
    // halbe Uploads älter als 1 Tag weg
    $old = $pdo->prepare("SELECT * FROM mm_natal_recs WHERE status = 'upload' AND created_at < ?");
    $old->execute([date('c', time() - 86400)]);
    foreach ($old->fetchAll() as $r) natal_rec_delete($pdo, $r);
}

/** Musik: Spotify-Playlists aus der Verwaltung ([[Titel, Embed-URL]]). */
function natal_spotify_lists(): array
{
    $out = [];
    foreach ((array)(natal_cfg()['spotify'] ?? []) as $p) {
        $u = natal_spotify_embed((string)($p['url'] ?? ''));
        if ($u !== '') $out[] = ['title' => (string)($p['title'] ?? 'Musik'), 'url' => $u];
    }
    return $out;
}

/** Spotify-Link (Playlist/Album) → Embed-URL, sonst ''. */
function natal_spotify_embed(string $url): string
{
    if (!preg_match('~open\.spotify\.com/(?:intl-[a-z]+/)?(?:embed/)?(playlist|album)/([A-Za-z0-9]{10,40})~', $url, $m)) return '';
    return 'https://open.spotify.com/embed/' . $m[1] . '/' . $m[2] . '?utm_source=generator&theme=0';
}

/** Widerrufsfrist (Vivien, 24.10.2026): in den ersten 14 Tagen nach der Buchung nur Begrüßung und Workbook-Vorschau
 *  (erstes Drittel, am Bildschirm, kein Download). Danach ist alles offen. */
function natal_member_full_from(array $o): int
{
    return strtotime((string)($o['paid_at'] ?: $o['created_at'])) + 14 * 86400;
}

function natal_member_full(array $o): bool
{
    return time() >= natal_member_full_from($o);
}

// --- „Baby ist da“ (Vivien, 24.10.2026) ------------------------------------------------------------

/** Glückwunsch-Mail mit Icons. Liefert ['subject','text','html']. */
function natal_birth_mail(array $o): array
{
    $first = forma_first_name((string)$o['name']);
    $site = mm_site();
    $icons = '';
    foreach (['wolke', 'schuh', 'herz', 'rassel', 'mobile'] as $i) $icons .= '<img src="' . $site . '/img/movecia/icons/' . $i . '.png" width="46" height="46" alt="" style="display:inline-block;width:46px;height:46px;margin:0 5px;border:0;">';
    $paras = [
        "Hallo $first,",
        'ich freue mich so sehr für dich und deine ganze Familie.',
        'Ich wünsche dir ganz, ganz viel Glück und eine wunderschöne, sanfte Kennenlernzeit. Jetzt zählt nur das Ankommen: du, dein Kind und deine Familie. Gönn dir Ruhe, lass dich umsorgen und nimm dir alle Zeit, die ihr braucht.',
        'Um unsere Einheiten musst du dich gerade gar nicht kümmern. Deine restlichen Einheiten sind jetzt Rückbildungseinheiten. Wenn du so weit bist, meist nach der Nachuntersuchung etwa sechs bis acht Wochen nach der Geburt, suchst du dir einfach neue Wunschtermine aus, und wir beginnen ganz behutsam wieder.',
        'Und wenn du in der Zwischenzeit etwas brauchst, eine Frage hast oder einfach erzählen magst, melde dich jederzeit bei mir. Ich bin für dich da.',
    ];
    $html = '<p style="margin:6px 0 18px;text-align:center;">' . $icons . '</p>'
        . '<h1 style="font-family:Georgia,\'Times New Roman\',serif;font-weight:400;font-size:34px;line-height:1.15;color:#2E2529;margin:0 0 6px;text-align:center;">Hurra, dein Baby ist da!</h1>'
        . '<p style="margin:0 0 22px;text-align:center;font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:19px;color:#7D5E68;">Willkommen auf der Welt, kleines Wunder.</p>';
    foreach (array_slice($paras, 1) as $i => $p) $html .= '<p style="margin:0 0 14px;">' . ($i === 0 ? '<strong>' . forma_h('Liebe ' . $first . ',') . '</strong><br>' : '') . forma_h($p) . '</p>';
    $html .= natal_btn(natal_link($o), 'Meine Termine');
    $text = "Hurra, dein Baby ist da!\n\n" . implode("\n\n", $paras) . "\n\nMeine Termine: " . natal_link($o) . "\n\nBis bald,\nVivien";
    return ['subject' => 'Hurra, dein Baby ist da! 🤍', 'text' => $text, 'html' => natal_mail_html('Hurra, dein Baby ist da!', $html)];
}

/** Geburt eintragen: alle Termine ab dem Geburtstag gelten als Postnatal. Optional Glückwunsch-Mail. Liefert Hinweise für die Verwaltung. */
function natal_birth_set(PDO $pdo, array $o, string $date, bool $mail): array
{
    $pdo->prepare('UPDATE mm_natal_orders SET birth_date = ? WHERE id = ?')->execute([$date, $o['id']]);
    $o['birth_date'] = $date;
    $notes = [];
    if ($mail && !$o['birth_mailed_at']) {
        $m = natal_birth_mail($o);
        if (mm_mail((string)$o['email'], $m['subject'], $m['text'], $m['html'], [], NATAL_FROM_NAME)) {
            $pdo->prepare('UPDATE mm_natal_orders SET birth_mailed_at = ? WHERE id = ?')->execute([date('c'), $o['id']]);
            $notes[] = 'Glückwunsch-Mail ist verschickt.';
        } else $notes[] = 'Die Mail konnte nicht verschickt werden.';
    }
    $soon = $pdo->prepare("SELECT COUNT(*) FROM mm_natal_sessions WHERE order_id = ? AND status IN ('angefragt','bestaetigt') AND date >= ? AND date < ?");
    $soon->execute([$o['id'], $date, date('Y-m-d', strtotime($date . ' +6 weeks'))]);
    if (($n = (int)$soon->fetchColumn()) > 0) $notes[] = $n . ($n === 1 ? ' Termin liegt' : ' Termine liegen') . ' in den ersten 6 Wochen nach der Geburt. Bitte verschieben oder absagen.';
    return $notes;
}

// --- Testkundin (Vivien, 24.10.2026: Ablauf mit eigener zweiter E-Mail-Adresse durchspielen) ------
/** Legt eine bezahlte Test-Buchung (€ 0, keine Rechnung, nicht im Umsatz) an und schickt die echte Buchungsmail mit Konto-Link. */
function natal_test_order(PDO $pdo, array $in): array
{
    natal_member_schema($pdo);
    $packs = natal_packages();
    $key = (string)($in['package'] ?? 'kombi');
    if (!isset($packs[$key]) || (int)$packs[$key]['units'] < 2) $key = 'kombi';
    $p = $packs[$key];
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $name = trim((string)($in['name'] ?? '')) ?: 'Test Kundin';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Bitte eine gültige E-Mail-Adresse eingeben.'];
    $full = !empty($in['full']);
    $paid = $full ? date('c', time() - 15 * 86400) : date('c');
    $due = date('Y-m-d', strtotime(str_starts_with(preg_replace('/^o_/', '', $key), 'post') ? '-6 weeks' : '+8 weeks'));
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO mm_natal_orders (package, title, units, unit_cents, amount_cents, name, email, phone, due_date, note, wishes, status, token, consent_start, created_at, paid_at, is_test) VALUES (?,?,?,0,0,?,?,'',?,'Testbuchung aus der Verwaltung','[]','bezahlt',?,1,?,?,1)")
        ->execute([$key, $p['title'] . ' · TEST', $p['units'], $name, $email, $due, $tok, $paid, $paid]);
    $o = natal_order($pdo, (int)$pdo->lastInsertId());
    $ins = $pdo->prepare('INSERT INTO mm_natal_sessions (order_id, date, start, status, created_at, confirmed_at) VALUES (?,?,?,?,?,?)');
    if (!empty($in['done'])) $ins->execute([$o['id'], date('Y-m-d', strtotime('-1 day')), '10:00', 'erledigt', date('c'), date('c')]);
    if (!empty($in['room'])) {
        $t = time() + 20 * 60;
        $ins->execute([$o['id'], date('Y-m-d', $t), date('H:i', $t), 'bestaetigt', date('c'), date('c')]);
    }
    natal_paid_mails($pdo, $o, []);
    return ['ok' => true, 'order' => $o];
}

/** Test-Buchung samt Terminen, Aufnahmen und (wenn sonst nichts mehr dranhängt) Konto löschen. */
function natal_test_delete(PDO $pdo, int $oid): bool
{
    natal_member_schema($pdo);
    $o = natal_order($pdo, $oid);
    if (!$o || !(int)$o['is_test']) return false;
    foreach ($pdo->query('SELECT id FROM mm_natal_sessions WHERE order_id = ' . $oid)->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        $r = natal_rec_for($pdo, (int)$sid);
        if ($r) natal_rec_delete($pdo, $r);
    }
    $pdo->prepare('DELETE FROM mm_natal_sessions WHERE order_id = ?')->execute([$oid]);
    $pdo->prepare('DELETE FROM mm_natal_orders WHERE id = ?')->execute([$oid]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM mm_natal_orders WHERE email = ?');
    $q->execute([$o['email']]);
    if ((int)$q->fetchColumn() === 0) $pdo->prepare('DELETE FROM mm_natal_members WHERE email = ?')->execute([$o['email']]);
    return true;
}


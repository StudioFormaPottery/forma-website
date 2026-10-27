<?php
// Movecia · Ein Zugang = eine Person (Vivien, 26.10.2026)
// Jede Anmeldung bekommt eine eigene Geräte-Kennung (Cookie mvdev). Regeln:
//  - höchstens MM_DEV_MAX Geräte gleichzeitig angemeldet (z. B. Handy + Computer + Tablet), das älteste fliegt raus
//  - nur ein Gerät gleichzeitig aktiv: läuft der Zugang gerade woanders, sieht man „wird gerade auf einem anderen Gerät genutzt“
//    und kann mit „Hier weitermachen“ übernehmen; das andere Gerät pausiert dann mit Hinweis
//  - Verdacht (viele verschiedene Geräte/Orte in 30 Tagen) sieht Vivien in der Verwaltung
declare(strict_types=1);

const MM_DEV_COOKIE = 'mvdev';
const MM_DEV_MAX = 3;
const MM_DEV_LIVE = 75; // Sekunden: so lange gilt ein Gerät nach dem letzten Lebenszeichen als aktiv

function mm_dev_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_devices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL,
        member_id INTEGER NOT NULL,
        sid TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL DEFAULT \'\',
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        last_seen TEXT NOT NULL,
        active_at TEXT NOT NULL DEFAULT \'\',
        paused_at TEXT NOT NULL DEFAULT \'\',
        revoked_at TEXT NOT NULL DEFAULT \'\',
        reason TEXT NOT NULL DEFAULT \'\'
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS mm_devices_m ON mm_devices (kind, member_id)');
    // Datensparsam: Geräte-Angaben spätestens 90 Tage nach der letzten Nutzung löschen
    if (random_int(1, 50) === 1) $pdo->prepare('DELETE FROM mm_devices WHERE last_seen < ?')->execute([date('c', time() - 90 * 86400)]);
}

function mm_dev_label(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $os = preg_match('/iPhone/i', $ua) ? 'iPhone' : (preg_match('/iPad/i', $ua) ? 'iPad' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Mac OS X|Macintosh/i', $ua) ? 'Mac' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Linux/i', $ua) ? 'Linux' : 'Gerät')))));
    $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/CriOS|Chrome\//', $ua) ? 'Chrome' : (preg_match('/FxiOS|Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Browser')));
    return "$os · $br";
}

function mm_dev_ip(): string
{
    // grob (Netz-Bereich), damit nicht jeder Mobilfunk-Wechsel als neuer Ort zählt; nur als Hash gespeichert
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $net = str_contains($ip, ':') ? implode(':', array_slice(explode(':', $ip), 0, 3)) : implode('.', array_slice(explode('.', $ip), 0, 2));
    return substr(hash('sha256', $net . 'mvdev'), 0, 16);
}

function mm_dev_cookie_set(string $sid): void
{
    setcookie(MM_DEV_COOKIE, $sid, ['expires' => time() + 400 * 86400, 'path' => '/', 'secure' => str_starts_with(mm_site(), 'https'), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[MM_DEV_COOKIE] = $sid;
}

function mm_dev_row(PDO $pdo, string $kind, int $mid): ?array
{
    $sid = (string)($_COOKIE[MM_DEV_COOKIE] ?? '');
    if (!preg_match('/^[0-9a-f]{32}$/', $sid)) return null;
    $q = $pdo->prepare('SELECT * FROM mm_devices WHERE sid = ? AND kind = ? AND member_id = ?');
    $q->execute([$sid, $kind, $mid]);
    return $q->fetch() ?: null;
}

/** Bei der Anmeldung: neues Gerät eintragen (vorherige Kennung dieses Browsers wird ersetzt). */
function mm_dev_login(PDO $pdo, string $kind, int $mid): void
{
    mm_dev_schema($pdo);
    $old = (string)($_COOKIE[MM_DEV_COOKIE] ?? '');
    if (preg_match('/^[0-9a-f]{32}$/', $old)) $pdo->prepare("UPDATE mm_devices SET revoked_at = ?, reason = 'neu' WHERE sid = ? AND kind = ? AND member_id = ? AND revoked_at = ''")->execute([date('c'), $old, $kind, $mid]);
    $sid = bin2hex(random_bytes(16));
    $now = date('c');
    $pdo->prepare('INSERT INTO mm_devices (kind, member_id, sid, label, ip_hash, created_at, last_seen, active_at) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$kind, $mid, $sid, mm_dev_label(), mm_dev_ip(), $now, $now, $now]);
    mm_dev_cookie_set($sid);
    // Gerätegrenze: älteste abmelden
    $q = $pdo->prepare("SELECT id FROM mm_devices WHERE kind = ? AND member_id = ? AND revoked_at = '' AND sid <> ? ORDER BY last_seen DESC, id DESC");
    $q->execute([$kind, $mid, $sid]);
    $ids = $q->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_slice($ids, MM_DEV_MAX - 1) as $id) $pdo->prepare("UPDATE mm_devices SET revoked_at = ?, reason = 'limit' WHERE id = ?")->execute([$now, $id]);
    // wer sich neu anmeldet, ist ab jetzt das aktive Gerät
    $pdo->prepare("UPDATE mm_devices SET paused_at = ? WHERE kind = ? AND member_id = ? AND sid <> ? AND revoked_at = '' AND last_seen >= ?")
        ->execute([$now, $kind, $mid, $sid, date('c', time() - MM_DEV_LIVE)]);
}

/** Prüft beim Seitenaufruf, ob dieses Gerät noch angemeldet sein darf. false = abmelden. */
function mm_dev_check(PDO $pdo, string $kind, int $mid): bool
{
    mm_dev_schema($pdo);
    $row = mm_dev_row($pdo, $kind, $mid);
    if (!$row) {
        // ältere Anmeldung von vor dieser Regel: einmal als Gerät eintragen
        $sid = (string)($_COOKIE[MM_DEV_COOKIE] ?? '');
        if (preg_match('/^[0-9a-f]{32}$/', $sid)) {
            $q = $pdo->prepare('SELECT COUNT(*) FROM mm_devices WHERE sid = ?');
            $q->execute([$sid]);
            if ((int)$q->fetchColumn() > 0) { $GLOBALS['mm_dev_kicked'] = 'limit'; return false; } // Kennung gehört zu einem anderen Konto
        }
        mm_dev_login($pdo, $kind, $mid);
        return true;
    }
    if ($row['revoked_at'] !== '') { $GLOBALS['mm_dev_kicked'] = $row['reason'] === 'neu' ? '' : 'limit'; return false; }
    $pdo->prepare('UPDATE mm_devices SET last_seen = ?, ip_hash = ? WHERE id = ?')->execute([date('c'), mm_dev_ip(), $row['id']]);
    return true;
}

/** Lebenszeichen der offenen Seite. Antwort: ok | busy (anderes Gerät aktiv) | paused (hier übernommen von anderem Gerät) | out */
function mm_dev_ping(PDO $pdo, string $kind, int $mid, bool $take): array
{
    mm_dev_schema($pdo);
    $row = mm_dev_row($pdo, $kind, $mid);
    if (!$row || $row['revoked_at'] !== '') return ['state' => 'out'];
    $now = date('c');
    $live = date('c', time() - MM_DEV_LIVE);
    if ($take) {
        $pdo->prepare("UPDATE mm_devices SET paused_at = ? WHERE kind = ? AND member_id = ? AND id <> ? AND revoked_at = ''")->execute([$now, $kind, $mid, $row['id']]);
        $pdo->prepare("UPDATE mm_devices SET paused_at = '', active_at = ?, last_seen = ? WHERE id = ?")->execute([$now, $now, $row['id']]);
        return ['state' => 'ok'];
    }
    if ($row['paused_at'] !== '') {
        $pdo->prepare('UPDATE mm_devices SET last_seen = ? WHERE id = ?')->execute([$now, $row['id']]);
        return ['state' => 'paused', 'other' => mm_dev_other_label($pdo, $kind, $mid, (int)$row['id'])];
    }
    // läuft gerade ein anderes, nicht pausiertes Gerät?
    $q = $pdo->prepare("SELECT label FROM mm_devices WHERE kind = ? AND member_id = ? AND id <> ? AND revoked_at = '' AND paused_at = '' AND active_at <> '' AND last_seen >= ? ORDER BY active_at DESC LIMIT 1");
    $q->execute([$kind, $mid, $row['id'], $live]);
    $other = $q->fetchColumn();
    if ($other !== false && $row['active_at'] === '') {
        $pdo->prepare('UPDATE mm_devices SET last_seen = ? WHERE id = ?')->execute([$now, $row['id']]);
        return ['state' => 'busy', 'other' => (string)$other];
    }
    if ($other !== false) {
        // beide aktiv: wer zuletzt übernommen hat, gewinnt
        $q2 = $pdo->prepare("SELECT MAX(active_at) FROM mm_devices WHERE kind = ? AND member_id = ? AND id <> ? AND revoked_at = '' AND paused_at = '' AND last_seen >= ?");
        $q2->execute([$kind, $mid, $row['id'], $live]);
        if ((string)$q2->fetchColumn() > (string)$row['active_at']) {
            $pdo->prepare('UPDATE mm_devices SET paused_at = ?, last_seen = ? WHERE id = ?')->execute([$now, $now, $row['id']]);
            return ['state' => 'paused', 'other' => (string)$other];
        }
    }
    $pdo->prepare('UPDATE mm_devices SET last_seen = ?, active_at = CASE WHEN active_at = \'\' THEN ? ELSE active_at END WHERE id = ?')->execute([$now, $now, $row['id']]);
    return ['state' => 'ok'];
}

function mm_dev_other_label(PDO $pdo, string $kind, int $mid, int $not): string
{
    $q = $pdo->prepare("SELECT label FROM mm_devices WHERE kind = ? AND member_id = ? AND id <> ? AND revoked_at = '' ORDER BY active_at DESC LIMIT 1");
    $q->execute([$kind, $mid, $not]);
    return (string)($q->fetchColumn() ?: '');
}

function mm_dev_logout(PDO $pdo): void
{
    mm_dev_schema($pdo);
    $sid = (string)($_COOKIE[MM_DEV_COOKIE] ?? '');
    if (preg_match('/^[0-9a-f]{32}$/', $sid)) $pdo->prepare("UPDATE mm_devices SET revoked_at = ?, reason = 'abgemeldet' WHERE sid = ? AND revoked_at = ''")->execute([date('c'), $sid]);
}

/** Verdacht auf geteilten Zugang: viele verschiedene Geräte oder Netze in 30 Tagen, oder oft gegenseitig verdrängt. */
function mm_dev_suspects(PDO $pdo): array
{
    mm_dev_schema($pdo);
    $since = date('c', time() - 30 * 86400);
    $q = $pdo->prepare("SELECT kind, member_id, COUNT(*) AS devices, COUNT(DISTINCT ip_hash) AS nets,
            SUM(CASE WHEN paused_at <> '' THEN 1 ELSE 0 END) AS pauses, MAX(last_seen) AS last
        FROM mm_devices WHERE created_at >= ? OR last_seen >= ? GROUP BY kind, member_id
        HAVING devices >= 5 OR nets >= 4 OR pauses >= 4 ORDER BY last DESC");
    $q->execute([$since, $since]);
    $out = [];
    foreach ($q->fetchAll() as $r) {
        $email = '';
        try {
            $t = $r['kind'] === 'natal' ? 'mm_natal_members' : 'mv_members';
            $e = $pdo->prepare("SELECT email FROM $t WHERE id = ?");
            $e->execute([(int)$r['member_id']]);
            $email = (string)$e->fetchColumn();
        } catch (Throwable $e) {}
        $r['email'] = $email;
        $out[] = $r;
    }
    return $out;
}

/** Text für die Anmeldeseite, wenn dieses Gerät abgemeldet wurde. */
function mm_dev_kicked_text(string $lang = 'de'): string
{
    if (($GLOBALS['mm_dev_kicked'] ?? '') === '') return '';
    return $lang === 'en'
        ? 'You have been signed out on this device because your access was used on too many devices. Your access is just for you and cannot be shared with other people. Simply sign in again.'
        : 'Du wurdest auf diesem Gerät abgemeldet, weil dein Zugang auf zu vielen Geräten genutzt wurde. Dein Zugang gehört nur dir und kann nicht mit anderen Personen geteilt werden. Melde dich einfach wieder an.';
}

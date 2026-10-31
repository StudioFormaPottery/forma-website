<?php
// Movecia Movement in der Verwaltung: eigener Bereich, eigene Farbe, nichts mischt sich mit FORMA.
// Wird von verwaltung/index.php eingebunden (nur angemeldet, CSRF schon geprüft).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/movecia-lib.php';
mm_schema($pdo);

/** Ein gemeinsames Movecia-Menü für alle Movecia-Bereiche der Verwaltung (Vivien, 25.10.2026: es gibt FORMA und Movecia). */
function mm_subnav(string $active): void
{
    global $pdo;
    $self = strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?');
    $req = 0; try { $req = (int)$pdo->query("SELECT COUNT(*) FROM mm_natal_sessions WHERE status = 'angefragt'")->fetchColumn(); } catch (Throwable $e) {}
    $acReq = 0; try { $acReq = (int)$pdo->query("SELECT COUNT(*) FROM ac_calls WHERE status = 'angefragt'")->fetchColumn(); require_once dirname(__DIR__) . '/api/academy-lib.php'; $acReq += ac_unanswered($pdo); } catch (Throwable $e) {}
    $ptReq = 0; try { $ptReq = (int)$pdo->query("SELECT COUNT(*) FROM mm_ptb_orders WHERE status = 'anfrage'")->fetchColumn(); } catch (Throwable $e) {}
    // Fünf Hauptreiter (Vivien, 31.10.2026: „weniger Text, übersichtlicher“): Heute · Videos · Kurse · Ansehen · Einstellungen.
    $groups = ['videos' => 'content', 'studio' => 'content', 'schnitt' => 'content', 'insta' => 'content', 'mediathek' => 'content', 'musik' => 'content', 'texte' => 'content',
        'method' => 'kurse', 'natal' => 'kurse', 'training' => 'kurse', 'academy' => 'kurse', 'gatherings' => 'kurse', 'presse' => 'einst', 'liste' => 'einst'];
    $main = $groups[$active] ?? $active;
    $bd = fn(int $n) => $n ? ' <i>' . $n . '</i>' : '';
    $tabs = [['movecia', '?view=movecia', 'Heute'], ['content', '?view=studio', 'Videos'], ['kurse', '?view=method', 'Kurse' . $bd($req + $ptReq + $acReq)], ['vorschau', '?view=vorschau', 'Ansehen'], ['einst', '?view=movecia&amp;p=einst', 'Einstellungen']];
    $subs = [
        'content' => [['studio', '?view=studio', 'Aufnehmen'], ['schnitt', '?view=schnitt', 'Schneiden'], ['insta', '?view=insta', 'Instagram'], ['videos', '?view=videos', 'Zu filmen'], ['mediathek', '?view=mediathek', 'Mediathek'], ['musik', '?view=musik', 'Musik'], ['texte', '?view=texte', 'Live-Texte']],
        'kurse' => [['method', '?view=method', 'Method &amp; Unfold'], ['natal', '?view=natal', 'Prä- &amp; Postnatal' . $bd($req)], ['training', '?view=training', 'Personal Training' . $bd($ptReq)], ['academy', '?view=academy', 'Academy' . $bd($acReq)], ['gatherings', '?view=movecia&amp;p=gatherings', 'Gatherings']],
        'einst' => [['einst', '?view=movecia&amp;p=einst', 'Verbindungen &amp; Mails'], ['liste', '?view=movecia&amp;p=einst#liste', 'E-Mail-Liste'], ['presse', '?view=presse', 'Presse']],
][$main] ?? []; ?><style>
.mvsub{position:sticky;top:0;z-index:40;margin:-8px -16px 8px;padding:10px 16px;background:rgba(242,236,228,.96);border-bottom:1px solid #DDD2C6;display:flex;gap:4px;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.mvsub::-webkit-scrollbar{display:none}
.mvsub a{flex:0 0 auto;padding:10px 20px;border-radius:999px;border:0;background:transparent;color:#2E2529;text-decoration:none;font-size:17px;white-space:nowrap}
<?= !in_array($active, ['videos', 'mediathek', 'studio', 'schnitt'], true) ? '@media(min-width:900px){main{zoom:1.12}}' : '' ?>
.mvsub a.on{background:#6B5658;color:#F7F1EE}
.mvsub2{display:flex;gap:4px;overflow-x:auto;scrollbar-width:none;margin:0 0 18px;padding:2px 0;border-bottom:1px solid #E6DCD2}
.mvsub2::-webkit-scrollbar{display:none}
.mvsub2 a{flex:0 0 auto;padding:9px 14px 10px;background:transparent;border-bottom:3px solid transparent;color:#7D7076;text-decoration:none;font-size:16px;white-space:nowrap}
.mvsub2 a.on{color:#2E2529;font-weight:600;border-bottom-color:#6B5658}
.mvsub2 i{font-style:normal;display:inline-block;min-width:20px;padding:0 6px;margin-left:4px;border-radius:999px;background:#B5544A;color:#fff;font-size:12px;text-align:center;font-weight:400}
.mvsub a.fo{background:transparent;border-color:transparent;color:#8C2C1D;padding-left:4px}
.mvsub i{font-style:normal;display:inline-block;min-width:20px;padding:0 6px;margin-left:4px;border-radius:999px;background:#B5544A;color:#fff;font-size:12px;text-align:center}
/* Movecia-Verwaltung einheitlich und ruhiger (Vivien, 28.10.2026): Überschriften in Cormorant statt fetter Grotesk,
   weiche Karten, sanfte Schatten, Knöpfe als Pillen */
body{background:#F2ECE4}
.mvsub{background:rgba(242,236,228,.94);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)}
.mvsub a{transition:background .15s,border-color .15s,color .15s,box-shadow .15s}
.mvsub a:not(.on):hover{background:#EADFDE}
main h1,main h2,main h3{font-family:"Cormorant Garamond",Georgia,serif!important;font-weight:500!important;color:#4A3A41;letter-spacing:0}
main h1{font-size:clamp(34px,3.6vw,46px)}
main h2{font-size:clamp(26px,2.6vw,32px)}
main h3{font-size:24px}
main .card{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:24px;box-shadow:0 14px 30px -26px rgba(74,58,65,.55)}
main input[type=text],main input[type=email],main input[type=number],main input[type=date],main input[type=time],main input[type=url],main input[type=password],main input:not([type]),main select,main textarea{border-radius:14px;border:1.5px solid #DDD2C6;background:#fff}
main input:focus,main select:focus,main textarea:focus{outline:2px solid #C9B0B8;outline-offset:1px;border-color:#7D5E68}
main b,main strong{font-weight:500}
/* Weniger Text (Vivien, 31.10.2026): Erklärsätze unter den Seitentiteln weg, das Menü zeigt schon, wo du bist */
.nh>p,.sh>div>p,.vh>p,.mh>p,.sh>a{display:none!important}
.nh h1,.sh h1,.vh h1,.mh h1{margin-bottom:10px}
</style>
<nav class="mvsub" aria-label="Movecia"><?php foreach ($tabs as [$k, $q, $t]): ?><a href="<?= forma_h($self) . $q ?>"<?= $k === $main ? ' class="on" aria-current="page"' : '' ?>><?= $t ?></a><?php endforeach; ?></nav>
<?php if ($subs): ?><nav class="mvsub2" aria-label="Unterbereich"><?php foreach ($subs as [$k, $q, $t]): ?><a href="<?= forma_h($self) . $q ?>"<?= $k === $active ? ' class="on"' : '' ?>><?= $t ?></a><?php endforeach; ?></nav><?php endif; ?><script>document.querySelectorAll('.mvsub a.on,.mvsub2 a.on').forEach(function(a){var n=a.parentNode;n.scrollLeft=a.offsetLeft-(n.clientWidth-a.offsetWidth)/2;});</script>
<?php
}
try { mm_auto_setup($pdo); } catch (Throwable $e) { error_log('MM setup: ' . $e->getMessage()); }
// Einmalig (Vivien, 28.10.2026: „Samhain Gathering · The Old New Year kannst du löschen“): Gathering samt Anmeldungen entfernen.
try {
    if (forma_setting($pdo, 'mm_del_samhain_2026') === null) {
        $ids = $pdo->query("SELECT id FROM mm_gatherings WHERE title LIKE '%Samhain%' AND starts_at LIKE '2026-10-31%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $gid) {
            $pdo->prepare('DELETE FROM mm_signups WHERE gathering_id = ?')->execute([(int)$gid]);
            $pdo->prepare('DELETE FROM mm_gatherings WHERE id = ?')->execute([(int)$gid]);
        }
        forma_set_setting($pdo, 'mm_del_samhain_2026', (string)count($ids));
    }
} catch (Throwable $e) { error_log('MM Samhain löschen: ' . $e->getMessage()); }
// Prä- & Postnatal Training (eigener Reiter ?view=natal)
require __DIR__ . '/natal.inc.php';
// Method & Unfold (eigener Reiter ?view=method)
require __DIR__ . '/method.inc.php';
// Teleprompter-Texte (?view=texte)
require __DIR__ . '/texte.inc.php';
// Presse & Gemeinde (?view=presse)
require __DIR__ . '/presse.inc.php';
// Videos hochladen (?view=videos)
require __DIR__ . '/videos.inc.php';
// Talking-Head-Studio mit Kamera-Look (?view=studio, Vivien 28.10.2026)
// Instagram täglich: Storys & Reels planen, vorab aufnehmen, am Tag teilen (?view=insta, Vivien 28.10.2026)
require __DIR__ . '/insta.inc.php';
// Eigener Musik-Ordner für Studio-Videos (?view=musik, Vivien 28.10.2026)
require __DIR__ . '/musik.inc.php';
require __DIR__ . '/studio.inc.php';
require __DIR__ . '/schnitt.inc.php';
require __DIR__ . '/mediathek.inc.php';
// Als Teilnehmerin ansehen (?view=vorschau)
require __DIR__ . '/vorschau.inc.php';
// Personal Training für Hotelgäste (?view=training, Vivien 27.10.2026)
require __DIR__ . '/ptb.inc.php';
// Movecia Academy · Teacher Training (?view=academy, Vivien 28.10.2026)
require __DIR__ . '/academy.inc.php';

$mmBack = $self . '?view=movecia';

// Personal Training Anfragen (Vivien, 26.10.2026)
require_once dirname(__DIR__) . '/api/mm-pt.inc.php';
if (isset($_GET['tmvorschau'])) { // Bestätigungsmail an die Gästin ansehen (Vivien will Mails immer zuerst sehen)
    require_once dirname(__DIR__) . '/api/termin-lib.php';
    $tmv = tm_get($pdo, (int)$_GET['tmvorschau']);
    if ($tmv) {
        if ($tmv['slot'] === '') { $tms = array_values(array_filter(explode(',', $tmv['times']))); $tmv['slot'] = $tms[1] ?? ($tms[0] ?? '09:00'); }
        if ($tmv['name'] === '') $tmv['name'] = $tmv['guest'];
        header('Content-Type: text/html; charset=utf-8');
        echo mm_mail_html('Vorschau', tm_guest_mail($tmv)[0], 'de');
        exit;
    }
}
// Weihnachts-Gewinnspiel (Vivien, 30.10.2026): auslosen und Gewinn freischalten
if ($action === 'mm_gw_draw' || $action === 'mm_gw_grant') {
    require_once dirname(__DIR__) . '/api/gewinnspiel-lib.php';
    if ($action === 'mm_gw_draw') {
        $w = gw_draw($pdo);
        go($mmBack . '#gewinnspiel', $w ? 'Gewonnen hat ' . $w['name'] . '! Prüf kurz, dann „Gewinn freischalten & Mail schicken“.' : 'Es gibt noch keine bestätigten Teilnahmen.');
    }
    $w = gw_winner($pdo);
    $ok = $w ? gw_grant($pdo, $w) : false;
    go($mmBack . '#gewinnspiel', $ok ? 'Gewinn freigeschaltet, die Mail ist unterwegs.' : 'Das hat nicht geklappt (schon freigeschaltet oder keine Gewinnerin).');
}
if ($action === 'mm_tm_new' || $action === 'mm_tm_reset' || $action === 'mm_tm_del') {
    require_once dirname(__DIR__) . '/api/termin-lib.php';
    tm_schema($pdo);
    $tid = (int)($_POST['tid'] ?? 0);
    if ($action === 'mm_tm_new') {
        $d = (string)($_POST['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || trim((string)($_POST['title'] ?? '')) === '') go($mmBack . '#termine', 'Bitte Titel und Tag eintragen.');
        tm_create($pdo, ['title' => trim((string)$_POST['title']), 'guest' => trim((string)($_POST['guest'] ?? '')), 'intro' => trim((string)($_POST['intro'] ?? '')), 'date' => $d,
            'times' => str_replace([' ', ';', '.'], [',', ',', ':'], trim((string)($_POST['times'] ?? ''))), 'minutes' => (int)($_POST['minutes'] ?? 45), 'script' => trim((string)($_POST['script'] ?? ''))]);
        go($mmBack . '#termine', 'Termin-Link angelegt.');
    }
    if ($action === 'mm_tm_reset') { tm_reset($pdo, $tid); go($mmBack . '#termine', 'Die Uhrzeit ist wieder frei, der Link funktioniert weiter.'); }
    tm_reset($pdo, $tid); $pdo->prepare("UPDATE mm_termine SET status = 'geloescht' WHERE id = ?")->execute([$tid]);
    go($mmBack . '#termine', 'Termin-Link gelöscht.');
}
if ($action === 'mm_pt_done' || $action === 'mm_pt_open' || $action === 'mm_pt_delete') {
    mm_pt_schema($pdo);
    $pid = (int)($_POST['pid'] ?? 0);
    if ($action === 'mm_pt_delete') $pdo->prepare('DELETE FROM mm_pt_requests WHERE id = ?')->execute([$pid]);
    else $pdo->prepare('UPDATE mm_pt_requests SET status = ?, done_at = ? WHERE id = ?')->execute([$action === 'mm_pt_done' ? 'erledigt' : 'neu', $action === 'mm_pt_done' ? date('c') : '', $pid]);
    go($mmBack . '#pt', $action === 'mm_pt_delete' ? 'Anfrage gelöscht.' : 'Gespeichert.');
}

if ($action === 'mm_save_gathering') {
    $gid = (int)($_POST['gid'] ?? 0);
    $title = forma_clean((string)($_POST['title'] ?? ''), 120);
    $date = (string)($_POST['date'] ?? '');
    $time = (string)($_POST['time'] ?? '');
    $min = max(10, min(240, (int)($_POST['minutes'] ?? 60)));
    $desc = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 1200);
    $script = mb_substr(trim((string)($_POST['script'] ?? '')), 0, 120);
    if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        go($mmBack . '&p=gatherings' . ($gid ? '&edit=' . $gid : '') . '#neu', 'Bitte gib Titel, Datum und Uhrzeit an.');
    }
    $starts = $date . 'T' . $time;
    if ($gid) {
        $old = mm_gathering($pdo, $gid);
        $pdo->prepare('UPDATE mm_gatherings SET script = ? WHERE id = ?')->execute([$script, $gid]);
        $pdo->prepare('UPDATE mm_gatherings SET title = ?, starts_at = ?, minutes = ?, description = ?, reminded_day = CASE WHEN starts_at = ? THEN reminded_day ELSE 0 END, reminded_hour = CASE WHEN starts_at = ? THEN reminded_hour ELSE 0 END WHERE id = ?')
            ->execute([$title, $starts, $min, $desc, $starts, $starts, $gid]);
        $msg = 'Gespeichert.';
        // Neue Zeit nach hinten: Raum muss länger offen bleiben, deshalb neu anlegen.
        if ($old && $old['room_url'] !== '' && strtotime($starts) + $min * 60 > strtotime($old['starts_at']) + (int)$old['minutes'] * 60 && mm_whereby_key() !== '') {
            try { mm_whereby_room($pdo, $gid); $msg .= ' Der Videoraum ist für die neue Zeit neu angelegt.'; } catch (Throwable $e) { $msg .= ' Den Videoraum bitte unten neu anlegen.'; }
        }
    } else {
        $pdo->prepare('INSERT INTO mm_gatherings (title, starts_at, minutes, description, script, created_at) VALUES (?,?,?,?,?,?)')->execute([$title, $starts, $min, $desc, $script, date('c')]);
        $gid = (int)$pdo->lastInsertId();
        $msg = 'Das Gathering ist angelegt und auf moveciamovement.com/gathering zu sehen.';
        if (mm_whereby_key() !== '') {
            try { $msg .= mm_whereby_room($pdo, $gid) ? ' Der Videoraum ist auch schon fertig.' : ''; } catch (Throwable $e) { $msg .= ' Der Videoraum konnte noch nicht angelegt werden: ' . $e->getMessage(); }
        } else {
            $msg .= ' Trag unten noch deinen Whereby-Schlüssel ein, dann entsteht der Videoraum.';
        }
    }
    go($mmBack . '&p=gatherings&g=' . $gid . '#g' . $gid, $msg);
}

if ($action === 'mm_room') {
    $gid = (int)($_POST['gid'] ?? 0);
    if (mm_whereby_key() === '') go($mmBack . '&p=einst#einstellungen', 'Bitte trag zuerst deinen Whereby-Schlüssel ein.');
    try {
        go($mmBack . '&p=gatherings&g=' . $gid . '#g' . $gid, mm_whereby_room($pdo, $gid) ? 'Der Videoraum ist fertig.' : 'Whereby hat keinen Raum zurückgegeben. Bitte versuch es noch einmal.');
    } catch (Throwable $e) {
        go($mmBack . '&p=gatherings&g=' . $gid . '#g' . $gid, 'Der Videoraum konnte nicht angelegt werden: ' . $e->getMessage());
    }
}

if ($action === 'mm_testroom') {
    try {
        $t = mm_testroom(!empty($_POST['renew']));
    } catch (Throwable $e) {
        go($mmBack, 'Der Testraum konnte nicht angelegt werden: ' . $e->getMessage());
    }
    header('Location: ' . mm_testroom_link($t));
    exit;
}

if ($action === 'mm_save_offers') {
    $cfg = mm_cfg('offers') ?? [];
    foreach (MM_OFFER_KEYS as $k) {
        if (in_array($k, ['method', 'unfold'], true) || in_array($k, MM_OFFER_RETIRED, true)) continue;
        $c = is_array($cfg[$k] ?? null) ? $cfg[$k] : [];
        $c['title'] = forma_clean((string)($_POST['title'][$k] ?? ''), 80) ?: (mm_offers()[$k]['title']);
        $c['text'] = mb_substr(trim((string)($_POST['text'][$k] ?? '')), 0, 600);
        $c['price_cents'] = mm_euro_in((string)($_POST['price'][$k] ?? ''));
        $c['billing'] = ($_POST['billing'][$k] ?? '') === 'monthly' ? 'monthly' : 'once';
        if (isset($_POST['special'][$k])) $c['special_cents'] = mm_euro_in((string)$_POST['special'][$k]);
        if ($k === 'circle') foreach (['intro_months', 'ref_months', 'max_months'] as $f) if (isset($_POST[$f])) $c[$f] = max(1, min(24, (int)$_POST[$f]));
        if ($k === 'circle' && isset($_POST['start'])) $c['start'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_POST['start']) ? (string)$_POST['start'] : '2000-01-01';
        if ($k === 'circle' && isset($_POST['period'])) { $c['periods'] = []; foreach ([6, 12] as $n) $c['periods'][] = [$n, mm_euro_in((string)($_POST['period'][$n] ?? ''))]; }
        if ($k === 'year') {
            $c['plans'] = [];
            foreach ([3, 6] as $n) $c['plans'][] = [$n, mm_euro_in((string)($_POST['plan_reg'][$n] ?? '')), mm_euro_in((string)($_POST['plan_sp'][$n] ?? ''))];
        }
        $cfg[$k] = $c;
    }
    mm_save_cfg('offers', $cfg);
    go($mmBack . '&p=gatherings#angebote', 'Die Angebote sind gespeichert.');
}

if ($action === 'mm_order_recheck') {
    mm_offer_schema($pdo);
    $n = 0;
    foreach ($pdo->query("SELECT * FROM mm_orders WHERE status = 'offen'")->fetchAll() as $o) {
        try { if (mm_order_check($pdo, $o) === 'bezahlt') $n++; } catch (Throwable $e) { error_log('MM order recheck: ' . $e->getMessage()); }
    }
    go($mmBack . '&p=gatherings#angebote', $n ? "$n Zahlung(en) nachträglich bestätigt." : 'Keine neuen Zahlungen gefunden.');
}

if ($action === 'mm_toggle') {
    $gid = (int)($_POST['gid'] ?? 0);
    $pdo->prepare('UPDATE mm_gatherings SET visible = 1 - visible WHERE id = ?')->execute([$gid]);
    go($mmBack . '&p=gatherings&g=' . $gid . '#g' . $gid, 'Gespeichert.');
}

if ($action === 'mm_delete') {
    $gid = (int)($_POST['gid'] ?? 0);
    if (empty($_POST['sure'])) go($mmBack . '&p=gatherings&g=' . $gid . '#g' . $gid, 'Bitte setze das Häkchen, um das Gathering wirklich zu löschen.');
    $pdo->prepare('DELETE FROM mm_signups WHERE gathering_id = ?')->execute([$gid]);
    $pdo->prepare('DELETE FROM mm_gatherings WHERE id = ?')->execute([$gid]);
    go($mmBack . '&p=gatherings', 'Das Gathering und seine Anmeldungen sind gelöscht.');
}

if ($action === 'mm_signup_add') {
    $gid = (int)($_POST['gid'] ?? 0);
    $g = mm_gathering($pdo, $gid);
    $name = forma_clean((string)($_POST['name'] ?? ''), 80);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!$g || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) go($mmBack . '&p=gatherings&g=' . $gid . '#g' . $gid, 'Bitte gib Namen und eine gültige E-Mail-Adresse an.');
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO mm_signups (gathering_id, name, email, token, created_at) VALUES (?,?,?,?,?) ON CONFLICT(gathering_id, email) DO UPDATE SET status = 'angemeldet'")
        ->execute([$gid, $name, $email, $tok, date('c')]);
    $s = $pdo->prepare('SELECT * FROM mm_signups WHERE gathering_id = ? AND email = ?');
    $s->execute([$gid, $email]);
    $row = $s->fetch();
    $sent = !empty($_POST['mail']) && mm_mail_signup($g, $row);
    go($mmBack . '&p=gatherings&g=' . $gid . '#g' . $gid, forma_first_name($name) . ' ist eingetragen.' . ($sent ? ' Der Raum-Link ist per E-Mail unterwegs.' : ''));
}

if ($action === 'mm_signup_resend' || $action === 'mm_signup_cancel') {
    $sid = (int)($_POST['sid'] ?? 0);
    $s = $pdo->prepare('SELECT * FROM mm_signups WHERE id = ?');
    $s->execute([$sid]);
    $row = $s->fetch();
    if (!$row) go($mmBack . '&p=gatherings', 'Diese Anmeldung gibt es nicht mehr.');
    $back = $mmBack . '&p=gatherings&g=' . (int)$row['gathering_id'] . '#g' . (int)$row['gathering_id'];
    if ($action === 'mm_signup_cancel') {
        $new = $row['status'] === 'angemeldet' ? 'storniert' : 'angemeldet';
        $pdo->prepare('UPDATE mm_signups SET status = ? WHERE id = ?')->execute([$new, $sid]);
        go($back, $new === 'storniert' ? 'Abgemeldet. Ihr Link funktioniert nicht mehr.' : 'Wieder angemeldet.');
    }
    $g = mm_gathering($pdo, (int)$row['gathering_id']);
    go($back, $g && mm_mail_signup($g, $row) ? 'Der Raum-Link ist noch einmal an ' . $row['email'] . ' unterwegs.' : 'Die Mail konnte nicht verschickt werden.');
}

if ($action === 'mm_save_whereby') {
    $key = trim((string)($_POST['whereby_key'] ?? ''));
    if (strlen($key) < 20) go($mmBack . '&p=einst#einstellungen', 'Bitte füge den vollständigen API-Schlüssel aus Whereby ein.');
    if (!mm_whereby_check($key)) go($mmBack . '&p=einst#einstellungen', 'Whereby hat den Schlüssel nicht angenommen. Bitte kopiere ihn noch einmal vollständig aus deinem Whereby-Dashboard (Configure → API).');
    mm_save_cfg('whereby', ['api_key' => $key]);
    $n = 0;
    foreach ($pdo->query("SELECT id, starts_at, minutes FROM mm_gatherings WHERE room_url = ''")->fetchAll() as $g) {
        if (strtotime($g['starts_at']) + (int)$g['minutes'] * 60 < time()) continue;
        try { if (mm_whereby_room($pdo, (int)$g['id'])) $n++; } catch (Throwable $e) { error_log('MM room: ' . $e->getMessage()); }
    }
    go($mmBack . '&p=einst#einstellungen', 'Whereby ist verbunden.' . ($n ? ($n === 1 ? ' Der Videoraum für dein Gathering ist angelegt.' : " $n Videoräume sind angelegt.") : ''));
}

if ($action === 'mm_save_review') {
    $u = trim((string)($_POST['review_url'] ?? ''));
    if ($u !== '' && !preg_match('#^https://#', $u)) go($mmBack . '&p=einst#einstellungen', 'Bitte füge den ganzen Link ein, er beginnt mit https://');
    mm_save_cfg('review', $u === '' ? null : ['url' => $u]);
    go($mmBack . '&p=einst#einstellungen', $u === '' ? 'Bewertungslink entfernt, Bewertungs-Mails warten.' : 'Bewertungslink gespeichert. Ab jetzt gehen die Bewertungs-Mails nach Unfold und nach Prä- & Postnatal automatisch raus.');
}

if ($action === 'mm_remove_whereby') {
    mm_save_cfg('whereby', null);
    go($mmBack . '&p=einst#einstellungen', 'Whereby ist getrennt. Bereits angelegte Räume funktionieren weiter.');
}

if ($action === 'mm_save_mail') {
    $pw = (string)($_POST['mail_password'] ?? '');
    if ($pw === '') go($mmBack . '&p=einst#einstellungen', 'Bitte gib das Passwort des Postfachs hello@moveciamovement.com ein.');
    try {
        mm_smtp($pw, MM_FROM_EMAIL, 'Test: Mails von Movecia', 'text/plain; charset=UTF-8', "Hallo Viv,\n\ndas Postfach hello@moveciamovement.com ist mit deiner Verwaltung verbunden. Gathering-Mails gehen ab jetzt von hier raus.\n", mb_encode_mimeheader(MM_FROM_NAME, 'UTF-8') . ' <' . MM_FROM_EMAIL . '>');
    } catch (Throwable $e) {
        go($mmBack . '&p=einst#einstellungen', 'Die Anmeldung beim Postfach hat nicht geklappt. Bitte prüfe das Passwort von hello@moveciamovement.com und versuch es noch einmal.');
    }
    mm_save_cfg('mail', ['password' => $pw]);
    go($mmBack . '&p=einst#einstellungen', 'Geschafft! Gathering-Mails gehen jetzt von hello@moveciamovement.com raus. Eine Test-Mail liegt in diesem Postfach.');
}

if ($action === 'mm_test_mail') {
    $to = strtolower(trim((string)($_POST['to'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) go($mmBack . '&p=einst#mails', 'Bitte gib eine gültige E-Mail-Adresse ein.');
    $ok = mm_mail($to, 'Test from Movecia 🌙', "Hey Viv,\n\nthis is a test. If you can read this, emails from hello@moveciamovement.com arrive.\n", mm_mail_html('Test', '<p style="margin:0;">Hey Viv,</p><p>this is a test. If you can read this, emails from hello@moveciamovement.com arrive. 🤍</p>'));
    go($mmBack . '&p=einst#mails', $ok ? 'Die Test-Mail ist raus an ' . $to . '. Schau auch im Spam nach. Unten siehst du, auf welchem Weg sie verschickt wurde.' : 'Die Test-Mail konnte nicht verschickt werden. Unten steht der Grund.');
}

if ($action === 'mm_resend_confirm') {
    $sid = (int)($_POST['sid'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM mm_subscribers WHERE id = ?');
    $st->execute([$sid]);
    $x = $st->fetch();
    if (!$x) go($mmBack . '&p=einst#liste', 'Diesen Kontakt gibt es nicht mehr.');
    $ok = $x['status'] === 'bestaetigt' ? mm_mail_welcome($x) : mm_mail_confirm($x);
    go($mmBack . '&p=einst#liste', $ok ? 'Die Mail ist noch einmal an ' . $x['email'] . ' unterwegs.' : 'Die Mail konnte nicht verschickt werden, siehe „Letzte Mails“.');
}

if ($action === 'mm_remove_mail') {
    mm_save_cfg('mail', null);
    go($mmBack . '&p=einst#einstellungen', 'Das Postfach ist getrennt.');
}

if ($view === 'movecia') {
    try { mm_send_reminders($pdo); } catch (Throwable $e) { error_log('MM reminders: ' . $e->getMessage()); }
    try { mm_ml_retry($pdo); } catch (Throwable $e) { error_log('MM ML retry: ' . $e->getMessage()); }
    $ml = mm_ml_stats();
    $gatherings = $pdo->query('SELECT * FROM mm_gatherings ORDER BY starts_at DESC')->fetchAll();
    $next = mm_next_gathering($pdo);
    $editId = (int)($_GET['edit'] ?? 0);
    $edit = $editId ? mm_gathering($pdo, $editId) : null;
    $openG = (int)($_GET['g'] ?? ($next['id'] ?? 0));
    $hasWhereby = mm_whereby_key() !== '';
    mm_testroom_auth('');
    $trTok = (string)((mm_cfg('testroom') ?? [])['token'] ?? '');
    if ($trTok === '') { $tmp = mm_cfg('testroom') ?? []; $tmp['token'] = $trTok = bin2hex(random_bytes(12)); mm_save_cfg('testroom', $tmp); }
    $GLOBALS['mmHostLink'] = fn(int $gid) => mm_site() . '/gathering/?test=' . rawurlencode($trTok) . '&g=' . $gid;
    $hasMail = !empty(mm_cfg('mail')['password']);
    $part = in_array($_GET['p'] ?? '', ['gatherings', 'einst'], true) ? (string)$_GET['p'] : (isset($_GET['edit']) || isset($_GET['g']) ? 'gatherings' : '');
    render_page('Movecia Movement', function () use ($part, $self, $flash, $pdo, $ml, $gatherings, $next, $edit, $openG, $hasWhereby, $hasMail) {
        $nextSign = $next ? mm_signup_count($pdo, (int)$next['id']) : 0;
        $subs = $pdo->query("SELECT * FROM mm_subscribers ORDER BY created_at DESC")->fetchAll();
        $subOk = count(array_filter($subs, fn($x) => $x['status'] === 'bestaetigt'));
        $subOpen = count(array_filter($subs, fn($x) => $x['status'] === 'offen'));
        $srcName = ['reset' => 'Reset Routine', 'circle' => 'Interesse Inner Circle', 'mentoring' => 'Interesse Mentoring', 'year' => 'Interesse Jahreskreis', 'retreat' => 'Interesse Alpine Retreat', 'unfold' => 'Interesse Unfold', 'teacher' => 'Interesse Teacher Training', 'academy' => 'Interesse Teacher Training', 'link' => 'Link in Bio'];
        $live = $next ? mm_live_count($pdo, (int)$next['id']) : 0;
        $joinedAll = (int)$pdo->query('SELECT COUNT(*) FROM mm_signups WHERE joined_at IS NOT NULL')->fetchColumn();
        $d = $edit ?: ['id' => 0, 'title' => 'Wild Hearts Gathering', 'starts_at' => '', 'minutes' => 30, 'description' => '', 'script' => ''];
        $dDate = $d['starts_at'] ? substr($d['starts_at'], 0, 10) : '';
        $dTime = $d['starts_at'] ? substr($d['starts_at'], 11, 5) : '19:00'; ?>
<style>
:root{--paper:#F2ECE4;--card:#FBF8F4;--clay:#7D5E68;--soft:#7D7076;--line:#DDD2C6}
body{background:var(--paper)}
.head .brand span{color:#7D5E68}
.mm-hero{position:relative;overflow:hidden;border-radius:26px;background:#4A3A41;color:#FBF5F7;padding:24px 24px 22px}
.mm-hero::after{content:"";position:absolute;width:220px;height:220px;right:-80px;top:-90px;border-radius:58% 42% 47% 53%/52% 46% 54% 48%;background:rgba(232,214,202,.3)}
.mm-hero h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:34px;margin:2px 0 4px;position:relative}
.mm-hero p{position:relative;margin:0;color:#EADFDE}
.mm-hero .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
.mm-hero{margin:14px 0 4px}
.mm-hero-sign{position:absolute;right:22px;top:20px;width:74px;height:74px;opacity:.9;z-index:1}
.mm-hero-text{position:relative;z-index:1;padding-right:96px;max-width:620px}
.mm-hero-stats{position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px}
.mm-hero-stats a{display:flex;flex-direction:column;background:rgba(251,245,247,.12);border:1px solid rgba(251,245,247,.25);border-radius:18px;padding:12px 14px;text-decoration:none;color:#FBF5F7}
.mm-hero-stats a:hover{background:rgba(251,245,247,.2)}
.mm-hero-stats b{font-family:"Bricolage Grotesque","Outfit",sans-serif;font-size:30px;line-height:1.1;color:#FBF5F7;font-weight:600}
.mm-hero-stats span{font-size:14px;color:#EADFDE;line-height:1.3}
@media (max-width:600px){main{zoom:1!important}}
.mm-hero-stats span{overflow-wrap:anywhere;hyphens:auto}
@media (max-width:520px){.mm-hero-stats{gap:8px}.mm-hero-stats a{padding:10px 11px}.mm-hero-sign{width:54px;height:54px}.mm-hero-text{padding-right:64px}.mm-hero h1{font-size:30px}}
.mm-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}
.mm-stat{background:#FBF8F4;border:1px solid var(--line);border-radius:18px;padding:12px 14px;color:var(--ink);text-decoration:none;display:flex;flex-direction:column}
.mm-stat b{font-family:"Bricolage Grotesque","Outfit",sans-serif;font-size:32px;line-height:1.1;color:#7D5E68}
.mm-stat span{font-size:14px;color:var(--soft);line-height:1.3}
.mm-stat.live b{color:#A0463A}
.mm-links{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.mm-links a{display:inline-block;padding:7px 14px;border-radius:999px;background:#FBF8F4;border:1px solid var(--line);text-decoration:none;color:#7D5E68;font-size:15px}
.mm-g summary{cursor:pointer;list-style:none}
.mm-g summary::-webkit-details-marker{display:none}
.mm-g .row3{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.mm-g .btn{padding:9px 16px;min-height:0;font-size:16px}
.mm-signup{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;border-top:1px solid var(--line);padding:8px 0}
.mm-badge{display:inline-block;border-radius:999px;padding:1px 9px;font-size:13px;background:#EADFDE;color:#4A3A41}
.mm-badge.on{background:#A0463A;color:#FBF1EA}
select{font:inherit;padding:11px 12px;border:1px solid var(--line);border-radius:12px;background:#fff}
@media (min-width:700px){.mm-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
</style>
        <?php $h = (int)date('G'); $hello = $h < 11 ? 'Guten Morgen' : ($h < 17 ? 'Hallo' : 'Guten Abend'); $natalReq = 0; try { $natalReq = (int)$pdo->query("SELECT COUNT(*) FROM mm_natal_sessions WHERE status = 'angefragt'")->fetchColumn(); } catch (Throwable $e) {} ?>
        <?php if ($part === ''): ?>
        <?php $ptAll = 0; try { $ptAll = (int)$pdo->query("SELECT COUNT(*) FROM mm_ptb_orders WHERE status = 'anfrage'")->fetchColumn(); $ptAll += count(array_filter(mm_pt_list($pdo), fn($r) => $r['status'] === 'neu')); } catch (Throwable $e) {}
        $acReq = 0; try { require_once dirname(__DIR__) . '/api/academy-lib.php'; $acReq = (int)$pdo->query("SELECT COUNT(*) FROM ac_calls WHERE status = 'angefragt'")->fetchColumn() + ac_unanswered($pdo); } catch (Throwable $e) {} ?>
        <?php $igN = 0; try { $igN = (int)$pdo->query("SELECT COUNT(*) FROM mm_insta WHERE day = '" . date('Y-m-d') . "' AND status <> 'gepostet'")->fetchColumn(); } catch (Throwable $e) {} ?>
        <h1 class="hi"><?= $hello ?>, Vivien</h1>
        <?php else: ?>
        <h1 style="margin:14px 0 10px"><?= $part === 'gatherings' ? '✨ Gatherings' : '⚙️ Einstellungen' ?></h1>
        <?php endif; ?>
        <?php mm_subnav($part ?: 'movecia'); ?>
        <?php if ($part === ''): ?>
<style>
h1.hi{margin:18px 0 6px}
.big3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:8px 0 22px}
.big3 a{display:flex;flex-direction:column;gap:10px;min-height:150px;padding:24px;border-radius:24px;text-decoration:none;color:#2E2529;box-sizing:border-box}
.big3 a b{font-size:24px;font-weight:500}.big3 a span{font-size:16px;color:#6B5658}
.big3 a:nth-child(1){background:#6B5658;color:#F7F1EE}.big3 a:nth-child(1) span{color:#EADFDE}
.big3 a:nth-child(2){background:#EADFDE}.big3 a:nth-child(3){background:#E8D6CA}
.big3 svg{width:32px;height:32px}
.krs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:10px 0 22px}
.krs a{display:block;padding:16px 18px;border-radius:18px;background:#FBF8F4;border:1px solid #DDD2C6;color:#2E2529;text-decoration:none;font-size:18px}
.krs a span{display:block;font-size:15px;color:#7D7076;margin-top:2px}.krs a span.on{color:#A0463A;font-weight:600}
.more{margin:12px 0;border-radius:20px;background:#FBF8F4;border:1px solid #DDD2C6}
.more>summary{cursor:pointer;list-style:none;padding:16px 20px;font-size:18px;color:#2E2529;display:flex;justify-content:space-between}
.more>summary::-webkit-details-marker{display:none}.more>summary::after{content:"▾";color:#9A8C93}.more[open]>summary::after{content:"▴"}
.more>section{margin:0!important;border:0!important;background:none!important;padding-top:0!important}
@media(max-width:760px){.big3,.krs{grid-template-columns:1fr}.big3 a{min-height:0;padding:18px 20px;flex-direction:row;align-items:center;gap:14px}.big3 a b{font-size:20px}.big3 a span{display:none}}
</style>
        <div class="big3">
            <a href="<?= forma_h($self) ?>?view=studio&amp;kal=1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg><b>Aufnehmen</b><span>Was als Nächstes dran ist</span></a>
            <a href="<?= forma_h($self) ?>?view=schnitt"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.1 15.9M14.5 14.5L20 20M8.1 8.1L12 12"/></svg><b>Schneiden</b><span>Deine Entwürfe</span></a>
            <a href="<?= forma_h($self) ?>?view=insta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg><b>Instagram heute</b><span><?= $igN ? $igN . ($igN > 1 ? ' Beiträge offen' : ' Beitrag offen') : 'Alles gepostet' ?></span></a>
        </div>
        <?php endif; ?>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <?php if ($part === '') { require_once __DIR__ . '/woche.inc.php'; mm_week_box($pdo, $self); } // Diese Woche (Vivien, 29.10.2026) ?>
        <?php if ($part === ''): ?>
        <script>document.addEventListener('DOMContentLoaded',function(){var t=location.hash&&document.querySelector(location.hash);var d=t&&t.closest('details');if(d)d.open=true});</script>
        <h2>Kurse</h2>
        <div class="krs">
            <a href="<?= forma_h($self) ?>?view=method">Method &amp; Unfold<span>Programme, Mitglieder</span></a>
            <a href="<?= forma_h($self) ?>?view=natal">Prä- &amp; Postnatal<?= $natalReq ? '<span class="on">' . $natalReq . ' Anfrage' . ($natalReq > 1 ? 'n' : '') . ' offen</span>' : '<span>nichts offen</span>' ?></a>
            <a href="<?= forma_h($self) ?>?view=training">Personal Training<?= $ptAll ? '<span class="on">' . $ptAll . ' Anfrage' . ($ptAll > 1 ? 'n' : '') . ' offen</span>' : '<span>nichts offen</span>' ?></a>
            <a href="<?= forma_h($self) ?>?view=academy">Academy<?= ($acReq ?? 0) ? '<span class="on">' . (int)$acReq . ' neu</span>' : '<span>nichts offen</span>' ?></a>
            <a href="<?= forma_h($self) ?>?view=movecia&amp;p=gatherings">Gatherings<span><?= $next ? 'nächstes ' . forma_h(date('j.n.', strtotime((string)$next['starts_at']))) : 'keins geplant' ?></span></a>
        </div>
        <?php endif; ?>
        <?php if ($part === '' && ($mnN = count(admin_news_list($pdo, 'movecia')))): ?><details class="more"><summary>Neu in deiner Verwaltung · <?= $mnN ?></summary><div style="padding:0 14px 6px"><?php admin_news_render($pdo, 'movecia', $self, '?view=movecia'); ?></div></details><?php endif; ?>
        <?php if ($part === '') { require_once __DIR__ . '/ac-wunsch.inc.php'; ac_wish_popup($pdo, '?view=movecia'); } ?>
        <?php $acOpenM = 0; try { require_once dirname(__DIR__) . '/api/academy-lib.php'; $acOpenM = ac_unanswered($pdo); } catch (Throwable $e) {} ?>
        <?php if ($part === 'einst'): require_once dirname(__DIR__) . '/api/method-lib.php'; require_once dirname(__DIR__) . '/wildhearts/assets/next-popup.inc.php'; $puMode = (string)((mm_cfg('popup') ?? [])['mode'] ?? 'auto'); $puNow = implode(' → ', array_map(fn($x) => ['method' => 'The Mindful Movement Method', 'unfold' => 'Unfold', 'teacher' => 'Teacher Training'][$x] ?? $x, mv_next_popup_phases())) ?: 'nichts'; ?>
        <form method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:12px 0;padding:14px 18px;border-radius:20px;background:#FBF8F4;border:1px solid #E6DCD2;font-size:17px"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_popup_mode">
          <span>🪟 <b>Pop-up auf der Startseite</b>: <?= $puMode === 'off' ? 'aus' : 'automatisch im Wechsel (bei jedem Besuch das nächste): <b>' . forma_h($puNow) . '</b>' ?></span>
          <button class="btn btn-ghost" name="mode" value="<?= $puMode === 'off' ? 'auto' : 'off' ?>" type="submit"><?= $puMode === 'off' ? 'Wieder automatisch' : 'Ausschalten' ?></button>
          <a href="<?= forma_h(mm_site()) ?>/?popup=method" target="_blank" rel="noopener">Method</a> · <a href="<?= forma_h(mm_site()) ?>/?popup=unfold" target="_blank" rel="noopener">Unfold</a> · <a href="<?= forma_h(mm_site()) ?>/?popup=teacher" target="_blank" rel="noopener">Teacher Training</a> ansehen
        </form>
        <?php endif; ?>
        <?php if ($acOpenM): ?><a class="card notice" style="display:flex;gap:12px;align-items:center;font-size:18px;text-decoration:none;color:inherit;background:#EADFDE;border-radius:20px;padding:16px 18px;margin:12px 0" href="<?= forma_h($self) ?>?view=academy#chat"><span style="font-size:28px">💬</span><span><strong><?= $acOpenM === 1 ? '1 neue Nachricht' : $acOpenM . ' neue Nachrichten' ?> im Teacher Training</strong><br><span class="muted small">Antworten →</span></span></a><?php endif; ?>
<style>
.qa{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:14px 0 4px}
@media(min-width:900px){.qa{grid-template-columns:repeat(4,minmax(0,1fr))}}
.qa form{margin:0}
.qa a,.qa button{display:flex;flex-direction:column;gap:4px;width:100%;height:100%;box-sizing:border-box;text-align:left;padding:16px;border-radius:20px;border:1px solid #DDD2C6;background:#FBF8F4;color:#2E2529;text-decoration:none;font:inherit;cursor:pointer}
.qa b{font-size:17px;font-weight:600;color:#4A3A41}
.qa span{overflow-wrap:anywhere}
.qa span{font-size:14px;color:#7D7076;line-height:1.35}
.qa .ic{font-size:26px;line-height:1}
.qa .main{background:#7D5E68;border-color:#7D5E68}.qa .main b,.qa .main span{color:#FBF5F7}
</style>
        <?php if ($part === ''): require_once dirname(__DIR__) . '/api/termin-lib.php'; $tmList = []; try { $tmList = tm_list($pdo); } catch (Throwable $e) {} ?>
        <details class="more" id="termine-w"><summary>Termin-Links für Videogespräche</summary>
        <section id="termine" style="margin:18px 0;padding:18px 20px;border-radius:22px;background:#FBF8F4;border:1px solid #DDD2C6">
            <h2 style="margin:0 0 .3rem">📅 Termin-Links für Videogespräche</h2>
            <p style="margin:0 0 .8rem;color:#7D7076;font-size:16px">Link schicken, die Person tippt eine Uhrzeit an, der Termin ist sofort fix. Du bekommst eine Mail mit deinem Gastgeberinnen-Fenster (Teleprompter mit Gesprächsleitfaden).</p>
            <?php foreach ($tmList as $tm): $tl = tm_link($tm); $hl = tm_host_link($tm); ?>
            <div style="background:#fff;border-radius:18px;padding:14px 16px;margin:0 0 10px;border:1px solid #EADFDE">
                <p style="margin:0;font-size:17px"><b><?= forma_h($tm['title']) ?></b><?= $tm['guest'] !== '' ? ' · ' . forma_h($tm['guest']) : '' ?></p>
                <p style="margin:.25rem 0;font-size:16px;color:#4A3A41"><?php if ($tm['status'] === 'fix'): ?>✓ Fix: <?= forma_h(tm_when($tm)) ?> · <?= forma_h($tm['name']) ?> (<?= forma_h($tm['email']) ?>)<?= $tm['note'] !== '' ? '<br><span style="color:#7D7076">„' . forma_h($tm['note']) . '“</span>' : '' ?><?php else: ?>⏳ Noch keine Uhrzeit gewählt · <?= forma_h(tm_day($tm)) ?>, zur Wahl: <?= forma_h(str_replace(',', ', ', $tm['times'])) ?> Uhr<?php endif; ?></p>
                <p style="margin:.4rem 0;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                    <input readonly value="<?= forma_h($tl) ?>" onclick="this.select()" style="flex:1;min-width:220px;font-size:15px;padding:.5em .8em;border-radius:12px;border:1px solid #DDD2C6">
                    <button type="button" onclick="navigator.clipboard.writeText('<?= forma_h($tl) ?>');this.textContent='Kopiert ✓'" style="font-size:15px;padding:.5em 1em;border-radius:999px;border:1px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer">Link kopieren</button>
                    <a href="<?= forma_h($tl) ?>&amp;vorschau=1" target="_blank" style="font-size:15px">Vorschau (bucht nichts)</a>
                    <a href="?view=movecia&amp;tmvorschau=<?= (int)$tm['id'] ?>" target="_blank" style="font-size:15px">Bestätigungsmail ansehen</a>
                    <?php if ($hl): ?><a href="<?= forma_h($hl) ?>" target="_blank" style="font-size:15px;padding:.5em 1em;border-radius:999px;background:#7D5E68;color:#FBF5F7;text-decoration:none">Als Gastgeberin öffnen</a><?php endif; ?><?php if (!$hl && $tm['status'] === 'offen'): ?><span style="font-size:14px;color:#7D7076">Dein Zugang zum Videoraum (mit Teleprompter) erscheint hier, sobald sie eine Uhrzeit gewählt hat.</span><?php endif; ?>
                </p>
                <?php $tmMsg = tm_message($tm); ?>
                <details style="margin:.3rem 0 .5rem"<?= $tm['status'] === 'offen' ? ' open' : '' ?>><summary style="cursor:pointer;font-size:15px;color:#7D5E68">✉️ Fertige Nachricht mit Link</summary>
                    <textarea readonly rows="9" style="width:100%;margin-top:6px;font:inherit;font-size:15px;padding:.7em .9em;border-radius:12px;border:1px solid #DDD2C6;background:#FBF8F4"><?= forma_h($tmMsg) ?></textarea>
                    <button type="button" data-msg="<?= forma_h($tmMsg) ?>" onclick="navigator.clipboard.writeText(this.dataset.msg);this.textContent='Kopiert ✓'" style="font-size:15px;padding:.5em 1.1em;border-radius:999px;border:0;background:#7D5E68;color:#FBF5F7;cursor:pointer">Nachricht kopieren</button>
                </details>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php if ($tm['status'] === 'fix'): ?><form method="post" style="margin:0" onsubmit="return confirm('Uhrzeit wieder freigeben? Sie kann dann neu wählen (bitte selbst Bescheid geben).')"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="tid" value="<?= (int)$tm['id'] ?>"><input type="hidden" name="action" value="mm_tm_reset"><button style="font-size:14px;padding:.45em .9em;border-radius:999px;border:1px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer">Uhrzeit wieder freigeben</button></form><?php endif; ?>
                    <form method="post" style="margin:0" onsubmit="return confirm('Termin-Link löschen?')"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="tid" value="<?= (int)$tm['id'] ?>"><input type="hidden" name="action" value="mm_tm_del"><button style="font-size:14px;padding:.45em .9em;border:0;background:none;color:#7D7076;cursor:pointer">löschen</button></form>
                </div>
            </div>
            <?php endforeach; ?>
            <details><summary style="cursor:pointer;font-size:16px;color:#7D5E68">＋ Neuen Termin-Link anlegen</summary>
                <form method="post" style="display:grid;gap:8px;margin-top:10px;max-width:560px"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_tm_new">
                    <label>Worum geht es?<input name="title" required placeholder="z. B. Fotoshooting im Atelier" style="width:100%"></label>
                    <label>Anrede auf der Seite („Hallo, …!“)<input name="guest" placeholder="z. B. Maria" style="width:100%"></label>
                    <label>Kurzer Text für sie (freiwillig)<textarea name="intro" rows="2" style="width:100%"></textarea></label>
                    <label>Tag<input type="date" name="date" required></label>
                    <label>Uhrzeiten zur Wahl<input name="times" value="08:00, 08:30, 09:00, 09:30, 10:00" style="width:100%"></label>
                    <label>Dauer (Minuten)<input type="number" name="minutes" value="45" min="15" max="180"></label>
                    <label>Gesprächsleitfaden für deinen Teleprompter (freiwillig, [Überschriften] erscheinen klein)<textarea name="script" rows="5" style="width:100%"></textarea></label>
                    <button style="justify-self:start;font-size:16px;padding:.6em 1.2em;border-radius:999px;border:0;background:#7D5E68;color:#FBF5F7;cursor:pointer">Anlegen</button>
                </form>
            </details>
        </section>
        </details>
        <?php endif; ?>
        <?php if ($part === '' && date('Y-m-d') <= '2027-01-31'): require_once dirname(__DIR__) . '/api/gewinnspiel-lib.php'; $gwList = gw_list($pdo); $gwOk = array_values(array_filter($gwList, fn($r) => $r['status'] === 'bestaetigt')); $gwWin = gw_winner($pdo); $gwCnt = []; foreach ($gwOk as $r) $gwCnt[$r['prize']] = ($gwCnt[$r['prize']] ?? 0) + 1; ?>
        <details class="more"><summary>Weihnachts-Gewinnspiel</summary>
        <section id="gewinnspiel" style="margin:18px 0;padding:18px 20px;border-radius:22px;background:#FBF8F4;border:1px solid #DDD2C6">
            <h2 style="margin:0 0 .3rem">🎁 Weihnachts-Gewinnspiel</h2>
            <p style="margin:0 0 .8rem;color:#7D7076;font-size:16px">Teilnahme über <a href="https://moveciamovement.com/gewinnspiel/" target="_blank">moveciamovement.com/gewinnspiel</a> bis 20. Dezember, 23:59 Uhr. Am 21. Dezember hier auslosen.</p>
            <p style="margin:0 0 .6rem;font-size:20px"><b><?= count($gwOk) ?></b> im Lostopf<?= count($gwList) > count($gwOk) ? ' · ' . (count($gwList) - count($gwOk)) . ' noch nicht bestätigt' : '' ?></p>
            <?php if ($gwCnt): ?><p style="margin:0 0 .8rem;font-size:16px;color:#4A3A41"><?php foreach (GW_PRIZES as $k => $p) if (!empty($gwCnt[$k])) echo forma_h($p[0]) . ': ' . (int)$gwCnt[$k] . '<br>'; ?></p><?php endif; ?>
            <?php if ($gwWin): ?>
            <div style="background:#fff;border-radius:18px;padding:14px 16px;margin:0 0 10px;border:2px solid #7D5E68">
                <p style="margin:0;font-size:19px">🏆 <b><?= forma_h($gwWin['name']) ?></b> · <?= forma_h($gwWin['email']) ?></p>
                <p style="margin:.3rem 0;font-size:16px">Wunschgewinn: <?= forma_h(GW_PRIZES[$gwWin['prize']][0] ?? '') ?></p>
                <?php if ($gwWin['granted_at'] !== ''): ?><p style="margin:.3rem 0 0;color:#4A6B4A;font-size:16px">✓ Freigeschaltet und benachrichtigt am <?= forma_h(date('j.n.Y', strtotime($gwWin['granted_at']))) ?></p>
                <?php else: ?><form method="post" style="margin:.5rem 0 0" onsubmit="return confirm('Gewinn jetzt freischalten und die Mail an <?= forma_h($gwWin['name']) ?> schicken?')"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_gw_grant"><button style="font-size:16px;padding:.6em 1.2em;border-radius:999px;border:0;background:#7D5E68;color:#FBF5F7;cursor:pointer">Gewinn freischalten &amp; Mail schicken</button></form><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!gw_open() && (!$gwWin || $gwWin['granted_at'] === '')): ?>
            <form method="post" style="margin:0 0 .6rem" onsubmit="return confirm('<?= $gwWin ? 'Neu auslosen? Die bisherige Gewinnerin verfällt.' : 'Jetzt die Gewinnerin auslosen?' ?>')"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_gw_draw"><button style="font-size:17px;padding:.7em 1.4em;border-radius:999px;border:0;background:#4A3A41;color:#FBF5F7;cursor:pointer"><?= $gwWin ? '🎲 Neu auslosen (keine Rückmeldung)' : '🎲 Jetzt auslosen' ?></button></form>
            <?php elseif (gw_open()): ?><p style="margin:0 0 .6rem;font-size:15px;color:#7D7076">Der Knopf zum Auslosen erscheint nach dem Teilnahmeschluss.</p><?php endif; ?>
            <?php if ($gwList): ?><details><summary style="cursor:pointer;font-size:16px;color:#7D5E68">Alle Teilnahmen ansehen</summary>
                <div style="margin-top:8px;font-size:15px;line-height:1.6"><?php foreach ($gwList as $r): ?><?= forma_h($r['name']) ?> · <?= forma_h($r['email']) ?> · <?= forma_h(GW_PRIZES[$r['prize']][0] ?? '') ?> · <?= $r['status'] === 'bestaetigt' ? '✓' : 'noch nicht bestätigt' ?><?= (int)$r['news'] ? ' · Post ✓' : '' ?><br><?php endforeach; ?></div>
            </details><?php endif; ?>
        </section>
        </details>
        <?php endif; ?>
        <?php $ptList = []; try { $ptList = mm_pt_list($pdo); } catch (Throwable $e) {} ?>
        <?php if ($part === '' && $ptList): ?>
        <section id="pt" style="margin:18px 0;padding:18px 20px;border-radius:22px;background:#FBF8F4;border:1px solid #DDD2C6">
            <h2 style="margin:0 0 .3rem">💬 Personal Training Anfragen<?php $ptOpen = count(array_filter($ptList, fn($r) => $r['status'] === 'neu')); if ($ptOpen): ?> <span style="display:inline-block;background:#7D5E68;color:#FBF5F7;border-radius:999px;font-size:15px;padding:.1em .7em;vertical-align:middle"><?= $ptOpen ?> neu</span><?php endif; ?></h2>
            <?php if (!$ptList): ?><p style="margin:.3rem 0 0;color:#7D7076;font-size:16px">Noch keine Anfragen. Sie kommen über „Anfrage schicken“ beim 1:1 Personal Training auf moveciamovement.com.</p><?php endif; ?>
            <?php foreach ($ptList as $r): $done = $r['status'] !== 'neu'; $wa = preg_replace('/[^0-9]/', '', preg_replace('/^\+/', '', preg_replace('/^0(?!0)/', '43', (string)$r['phone']))); ?>
            <div style="margin-top:12px;padding:14px 16px;border-radius:18px;background:<?= $done ? '#F2ECE4' : '#fff' ?>;border:1px solid #E6DCD2;<?= $done ? 'opacity:.7' : '' ?>">
                <p style="margin:0;font-size:18px"><b><?= forma_h($r['name']) ?></b> <span style="color:#7D7076;font-size:15px">· <?= forma_h(date('d.m. H:i', strtotime((string)$r['created_at']))) ?><?= $r['lang'] === 'en' ? ' · Englisch' : '' ?><?= $done ? ' · erledigt' : '' ?></span></p>
                <p style="margin:.3rem 0;font-size:16px"><?= forma_h($r['email']) ?><?= $r['phone'] !== '' ? ' · ' . forma_h($r['phone']) : '' ?></p>
                <?php foreach ([['Wünscht sich', $r['goals'], MM_PT_GOALS], ['Wo', $r['places'], MM_PT_PLACES], ['Wann', $r['times'], MM_PT_TIMES]] as [$lbl, $csv, $map]): $vals = mm_pt_labels((string)$csv, $map); if ($vals): ?><p style="margin:.2rem 0;font-size:16px"><span style="color:#7D7076"><?= $lbl ?>:</span> <?= forma_h(implode(', ', $vals)) ?></p><?php endif; endforeach; ?>
                <?php if ($r['message'] !== ''): ?><p style="margin:.5rem 0;font-size:16px;white-space:pre-wrap;background:#FBF8F4;border-radius:12px;padding:.6em .8em"><?= forma_h($r['message']) ?></p><?php endif; ?>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:.6rem">
                    <a href="mailto:<?= forma_h($r['email']) ?>?subject=<?= rawurlencode($r['lang'] === 'en' ? 'Your personal training request' : 'Deine Anfrage für Personal Training') ?>" style="font-size:15px;padding:.5em 1em;border-radius:999px;background:#7D5E68;color:#FBF5F7;text-decoration:none">✉️ Antworten</a>
                    <?php if (strlen($wa) >= 8): ?><a href="https://wa.me/<?= forma_h($wa) ?>" target="_blank" rel="noopener" style="font-size:15px;padding:.5em 1em;border-radius:999px;background:#EADFDE;color:#4A3A41;text-decoration:none">WhatsApp</a><?php endif; ?>
                    <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="pid" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="<?= $done ? 'mm_pt_open' : 'mm_pt_done' ?>"><button type="submit" style="font-size:15px;padding:.5em 1em;border-radius:999px;border:1px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer"><?= $done ? 'Wieder offen' : '✓ Erledigt' ?></button></form>
                    <?php if ($done): ?><form method="post" style="margin:0" onsubmit="return confirm('Anfrage löschen?')"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="pid" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="mm_pt_delete"><button type="submit" style="font-size:15px;padding:.5em 1em;border-radius:999px;border:0;background:none;color:#7D7076;cursor:pointer">löschen</button></form><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
        <?php $devSus = []; try { $devSus = mm_dev_suspects($pdo); } catch (Throwable $e) {} if ($part === '' && $devSus): ?>
        <div style="margin:16px 0;padding:16px 18px;border-radius:20px;background:#F6E9E4;border:1px solid #E2C7BD">
            <b style="font-size:17px;color:#4A3A41">⚠ Vielleicht geteilte Zugänge</b>
            <p style="margin:.4rem 0 .6rem;color:#7D7076;font-size:15px">Diese Zugänge liefen in den letzten 30 Tagen auf auffällig vielen Geräten oder an vielen Orten, oder haben sich oft gegenseitig verdrängt. Es muss nicht heißen, dass jemand teilt, aber du kannst nachfragen.</p>
            <?php foreach ($devSus as $d): ?><p style="margin:.2rem 0;font-size:15px"><b><?= forma_h($d['email'] ?: ('#' . $d['member_id'])) ?></b> · <?= $d['kind'] === 'natal' ? 'Prä-/Postnatal' : 'Method/Unfold' ?> · <?= (int)$d['devices'] ?> Geräte · <?= (int)$d['nets'] ?> Orte · <?= (int)$d['pauses'] ?>× verdrängt · zuletzt <?= forma_h(date('d.m. H:i', strtotime((string)$d['last']))) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($part === ''): ?>
<style>
.qg{margin:22px 0 6px}
.qg h2{display:flex;align-items:center;gap:10px;margin:0 0 10px}
.qg h2 small{font-family:"Outfit",sans-serif;font-size:15px;color:#7D7076}
.qg .dot{width:14px;height:14px;border-radius:50%;display:inline-block}
.qg .qa{margin-top:0}
@media(min-width:900px){.qg .qa{grid-template-columns:repeat(3,minmax(0,1fr))}}
.qg.tag .qa a,.qg.tag .qa button{background:#E4E8DC;border-color:#C9D0BB}.qg.tag b{color:#3F4A26}.qg.tag .qa span{color:#5E6450}.qg.tag .dot{background:#8E9A72}
.qg.kurs .qa a{background:#7D5E68;border-color:#7D5E68}.qg.kurs b{color:#FBF5F7}.qg.kurs .qa span{color:#EADFDE}.qg.kurs .dot{background:#7D5E68}
.qg.test .qa a,.qg.test .qa button{background:#F3E7DA;border-color:#E2CDB7}.qg.test b{color:#5A4434}.qg.test .qa span{color:#86705E}.qg.test .dot{background:#C9A27E}
.qg .qa .soon{opacity:.75}
</style>
        <?php endif; ?>
        <?php if ($part === 'gatherings'): ?>
        <div class="mm-stats" style="margin-top:0">
            <a class="mm-stat live" href="#g<?= (int)($next['id'] ?? 0) ?>"><b><?= $live ?></b><span>gerade im Videochat</span></a>
            <span class="mm-stat"><b><?= $next ? $nextSign : 0 ?></b><span>Anmeldungen nächstes Gathering</span></span>
            <span class="mm-stat"><b><?= $joinedAll ?></b><span>waren schon bei einem Gathering dabei</span></span>
        </div>
        <div class="mm-links">
            <a href="https://moveciamovement.com/gathering/" target="_blank" rel="noopener">Gathering-Seite</a>
            <a href="https://whereby.com/information/embedded/" target="_blank" rel="noopener">Whereby</a>
        </div>
        <h2>Gatherings</h2>
        <?php if ($next && $next['room_url'] !== ''): ?>
            <a class="card notice" href="<?= forma_h($GLOBALS['mmHostLink']((int)$next['id'])) ?>" target="_blank" rel="noopener" style="border-color:#7D5E68;background:#FBF8F4"><span class="ic" style="background:#EADFDE;color:#7D5E68"><?= admin_icon('kalender') ?></span><span><strong>Als Gastgeberin in den Raum: <?= forma_h($next['title']) ?></strong><br><span class="muted small"><?= forma_h(mm_when_de($next)) ?> · dein Fenster mit Teleprompter, Aufnahme und Video-Bühne →</span></span></a>
            <a class="card notice" href="<?= forma_h(preg_replace('/&g=\d+$/', '', $GLOBALS['mmHostLink']((int)$next['id'])) . '&probe=' . (int)$next['id']) ?>" target="_blank" rel="noopener"><span class="ic" style="background:#EADFDE;color:#7D5E68"><?= admin_icon('kalender') ?></span><span><strong>Probelauf: <?= forma_h($next['title']) ?></strong><br><span class="muted small">im Testraum mit Übungsfenstern, Skript und Call-Name dieses Gatherings, mit Aufnahme zum Anschauen →</span></span></a>
        <?php endif; ?>
        <?php if (!$gatherings): ?><p class="muted">Noch kein Gathering geplant. Leg unten das erste an, zum Beispiel Anfang Dezember.</p><?php endif; ?>
        <div class="list">
        <?php foreach ($gatherings as $g):
            $gid = (int)$g['id'];
            $past = strtotime($g['starts_at']) + (int)$g['minutes'] * 60 < time();
            $st = $pdo->prepare('SELECT * FROM mm_signups WHERE gathering_id = ? ORDER BY created_at');
            $st->execute([$gid]);
            $signs = $st->fetchAll();
            $act = count(array_filter($signs, fn($s) => $s['status'] === 'angemeldet'));
            $joined = count(array_filter($signs, fn($s) => $s['joined_at'])); ?>
            <details class="card mm-g" id="g<?= $gid ?>" <?= $gid === $openG ? 'open' : '' ?>>
                <summary><strong><?= forma_h($g['title']) ?></strong><br><span class="muted small"><?= forma_h(mm_when_de($g)) ?> · <?= (int)$g['minutes'] ?> Min. · <?= $act ?> angemeldet<?= $past ? ' · ' . $joined . ' waren dabei' : '' ?><?= $g['visible'] ? '' : ' · ausgeblendet' ?></span></summary>
                <div class="stack" style="margin-top:10px">
                    <?php if ($g['room_url'] !== ''): ?>
                        <p class="small">Videoraum: <span class="mm-badge">bereit</span><?= mm_room_open($g) ? ' <span class="mm-badge on">offen · ' . mm_live_count($pdo, $gid) . ' im Raum</span>' : ' · öffnet für die Gäste 15 Min. vor Beginn' ?></p>
                    <?php elseif (!$past): ?>
                        <p class="small">Videoraum: noch nicht angelegt.</p>
                    <?php endif; ?>
                    <div class="row3">
                        <?php if ($g['host_url'] !== '' && !$past): ?><a class="btn" href="<?= forma_h($GLOBALS['mmHostLink']($gid)) ?>" target="_blank" rel="noopener">Als Gastgeberin öffnen</a><?php endif; ?><?php if (!$past): ?><a class="btn ghost" href="<?= forma_h(preg_replace('/&g=\d+$/', '', $GLOBALS['mmHostLink']($gid)) . '&probe=' . $gid) ?>" target="_blank" rel="noopener">Probelauf mit Übungsfenstern</a><?php endif; ?>
                        <?php if (!$past): ?><form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_room"><input type="hidden" name="gid" value="<?= $gid ?>"><button class="btn btn-ghost" type="submit"><?= $g['room_url'] !== '' ? 'Raum neu anlegen' : 'Videoraum anlegen' ?></button></form><?php endif; ?>
                        <a class="btn btn-ghost" href="<?= forma_h($self) ?>?view=movecia&amp;p=gatherings&amp;edit=<?= $gid ?>#neu">Bearbeiten</a>
                        <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_toggle"><input type="hidden" name="gid" value="<?= $gid ?>"><button class="link" type="submit"><?= $g['visible'] ? 'Auf der Website ausblenden' : 'Auf der Website zeigen' ?></button></form>
                    </div>
                    <p class="muted small"><?= $g['reminded_day'] ? 'Erinnerung am Vortag ist raus. ' : 'Erinnerung am Vortag geht automatisch raus. ' ?><?= $g['reminded_hour'] ? 'Erinnerung 1 Std. vorher ist raus.' : 'Erinnerung 1 Std. vorher geht automatisch raus.' ?></p>
                    <h3 style="margin:10px 0 0;font-size:18px">Anmeldungen (<?= $act ?>)</h3>
                    <?php if (!$signs): ?><p class="muted small">Noch niemand. Teile den Link moveciamovement.com/gathering im Channel.</p><?php endif; ?>
                    <?php foreach ($signs as $s): $on = $s['last_seen'] && strtotime($s['last_seen']) > time() - 120; ?>
                        <div class="mm-signup<?= $s['status'] === 'angemeldet' ? '' : ' is-cancelled' ?>">
                            <span><strong><?= forma_h($s['name']) ?></strong> <span class="muted small sel"><?= forma_h($s['email']) ?></span><br>
                            <span class="muted small">angemeldet am <?= forma_h(date('d. m. Y', strtotime($s['created_at']))) ?></span>
                            <?php if ($on): ?> <span class="mm-badge on">gerade im Raum</span><?php elseif ($s['joined_at']): ?> <span class="mm-badge">war dabei</span><?php endif; ?>
                            <?php if ($s['status'] !== 'angemeldet'): ?> <span class="mm-badge">abgemeldet</span><?php endif; ?></span>
                            <span style="display:flex;gap:12px">
                                <?php if ($s['status'] === 'angemeldet' && !$past): ?><form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_signup_resend"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="link small" type="submit">Link nochmal schicken</button></form><?php endif; ?>
                                <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_signup_cancel"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="link small" type="submit"><?= $s['status'] === 'angemeldet' ? 'Abmelden' : 'Wieder anmelden' ?></button></form>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$past): ?>
                    <details><summary class="link small">+ Person von Hand eintragen</summary>
                        <form method="post" class="stack" style="margin-top:8px">
                            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_signup_add"><input type="hidden" name="gid" value="<?= $gid ?>">
                            <div class="row"><input name="name" placeholder="Vorname" required><input name="email" type="email" placeholder="E-Mail" required></div>
                            <label class="check"><input type="checkbox" name="mail" value="1" checked> Raum-Link per E-Mail schicken</label>
                            <button class="btn" type="submit">Eintragen</button>
                        </form>
                    </details>
                    <?php endif; ?>
                    <details><summary class="link small">Gathering löschen</summary>
                        <form method="post" class="stack" style="margin-top:8px">
                            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_delete"><input type="hidden" name="gid" value="<?= $gid ?>">
                            <label class="check"><input type="checkbox" name="sure" value="1"> Ja, mit allen Anmeldungen löschen</label>
                            <button class="btn btn-danger" type="submit">Löschen</button>
                        </form>
                    </details>
                </div>
            </details>
        <?php endforeach; ?>
        </div>

        <h2 id="neu"><?= $edit ? 'Gathering bearbeiten' : 'Neues Gathering' ?></h2>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_save_gathering"><input type="hidden" name="gid" value="<?= (int)$d['id'] ?>">
            <?php if (!$edit): ?>
            <p class="small"><strong>Vorlage wählen</strong> · ein Tipp füllt Titel, Dauer, Uhrzeit, Text und das passende Teleprompter-Skript aus. Danach nur noch das Datum prüfen.</p>
            <div class="mm-links" style="margin-top:0"><?php foreach (mm_gathering_presets() as $i => $pr): if (($pr['date'] ?? '9999') < date('Y-m-d')) continue; ?><a href="#neu" data-preset="<?= $i ?>"><?= forma_h($pr['label']) ?></a><?php endforeach; ?></div>
            <script>
            (() => {
              const P = <?= json_encode(mm_gathering_presets(), JSON_UNESCAPED_UNICODE) ?>;
              document.querySelectorAll('[data-preset]').forEach(a => a.addEventListener('click', e => {
                e.preventDefault();
                const p = P[+a.dataset.preset], f = a.closest('form');
                const E = f.elements; E.title.value = p.title; E.minutes.value = p.minutes; E.time.value = p.time; E.description.value = p.text; E.script.value = p.script;
                if (p.date) E.date.value = p.date;
                else if (!E.date.value && p.time === '08:00') { const d = new Date(); d.setDate(d.getDate() + ((7 - d.getDay()) % 7 || 7)); E.date.value = d.toISOString().slice(0, 10); }
                document.querySelectorAll('[data-preset]').forEach(x => x.style.background = x === a ? '#EADFDE' : '');
              }));
            })();
            </script>
            <?php endif; ?>
            <label for="mt">Titel (Englisch, so sehen ihn die Frauen)</label>
            <input id="mt" name="title" value="<?= forma_h($d['title']) ?>" required maxlength="120">
            <div class="row">
                <div class="stack"><label for="md">Datum</label><input id="md" type="date" name="date" value="<?= forma_h($dDate) ?>" required></div>
                <div class="stack"><label for="mz">Uhrzeit (Wien)</label><input id="mz" type="time" name="time" value="<?= forma_h($dTime) ?>" required></div>
            </div>
            <label for="mm">Dauer</label>
            <select id="mm" name="minutes"><?php foreach (array_unique(array_merge([15, 20, 30, 35, 45, 60, 75, 90], [(int)$d['minutes']])) as $m): ?><option value="<?= $m ?>" <?= (int)$d['minutes'] === $m ? 'selected' : '' ?>><?= $m ?> Minuten</option><?php endforeach; ?></select>
            <label for="mx">Kurzer Text für die Anmeldeseite (Englisch, darf leer bleiben)</label>
            <textarea id="mx" name="description" rows="4" maxlength="1200"><?= forma_h($d['description']) ?></textarea>
            <label for="msc">Teleprompter-Skript (öffnet sich automatisch in deinem Gastgeberinnen-Fenster)</label>
            <select id="msc" name="script"><option value="">kein Skript, den letzten Text behalten</option><?php foreach (mm_script_titles() as $st): ?><option value="<?= forma_h($st) ?>" <?= ($d['script'] ?? '') === $st ? 'selected' : '' ?>><?= forma_h($st) ?></option><?php endforeach; ?></select>
            <p class="muted small">Gäste melden sich auf moveciamovement.com/gathering an, bekommen ihren persönlichen Raum-Link per Mail und eine Erinnerung am Vortag und eine Stunde vorher. Der Raum öffnet 15 Minuten vor Beginn direkt auf deiner Website, ohne Zeitlimit.</p>
            <button class="btn" type="submit"><?= $edit ? 'Speichern' : 'Gathering anlegen' ?></button>
            <?php if ($edit): ?><a class="link" href="<?= forma_h($self) ?>?view=movecia">Abbrechen</a><?php endif; ?>
        </form>

        <?php mm_offer_schema($pdo); $offs = mm_offers(); $orders = $pdo->query('SELECT * FROM mm_orders ORDER BY id DESC LIMIT 60')->fetchAll(); $openOrd = count(array_filter($orders, fn($o) => $o['status'] === 'offen')); ?>
        <details class="card stack mm-offer" id="angebote" style="margin-top:16px">
        <summary><span style="font-size:22px">🎁</span> <strong style="font-size:19px">Angebot fürs Gathering</strong> <span class="muted small">· was du am Ende eines Gatherings einblenden kannst, mit Gathering-Preis<?= $openOrd ? ' · <b style="color:#A0463A">' . $openOrd . ' Zahlung offen</b>' : '' ?></span></summary>
        <form method="post" class="stack" style="margin-top:10px">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_save_offers">
            <p class="small">Am Ende eines Gatherings kannst du im Gastgeberinnen-Fenster („✦ Angebot“) Unfold, die Method und das Alpine Retreat einblenden. Hier trägst du den regulären Preis fürs Retreat ein. Den günstigeren Gathering-Preis und wie viele Minuten er gilt, gibst du beim Einblenden ein. Läuft der Countdown ab, gilt automatisch wieder der reguläre Preis, auch beim Bezahlen. Titel und Text sehen die Frauen, deshalb auf Englisch.</p>
            <p class="small">Method und Unfold kannst du ebenfalls einblenden. Ihre Preise (auch die Gathering-Preise) stellst du unter <a href="<?= forma_h($self) ?>?view=method#preise">Method &amp; Unfold → Preise</a> ein.</p>
            <?php foreach ($offs as $k => $o): if (in_array($k, ['method', 'unfold'], true) || in_array($k, MM_OFFER_RETIRED, true)) continue; ?>
                <div class="stack" style="border-top:1px solid var(--line);padding-top:12px">
                    <label for="ot<?= $k ?>"><strong><?= $k === 'retreat' ? 'Alpine Retreat' : forma_h($k) ?></strong> · Titel</label>
                    <input id="ot<?= $k ?>" name="title[<?= $k ?>]" value="<?= forma_h($o['title']) ?>" maxlength="80">
                    <label for="ox<?= $k ?>">Kurzer Text</label>
                    <textarea id="ox<?= $k ?>" name="text[<?= $k ?>]" rows="3" maxlength="600"><?= forma_h($o['text']) ?></textarea>
                    <div class="row">
                        <div class="stack"><label for="op<?= $k ?>">Regulärer Preis in €</label><input id="op<?= $k ?>" name="price[<?= $k ?>]" inputmode="decimal" value="<?= $o['price_cents'] ? forma_h(rtrim(rtrim(number_format($o['price_cents'] / 100, 2, ',', ''), '0'), ',')) : '' ?>" placeholder="noch offen"></div>
                        <div class="stack"><label for="os<?= $k ?>">Gathering-Preis in €</label><input id="os<?= $k ?>" name="special[<?= $k ?>]" inputmode="decimal" value="<?= $o['special_cents'] ? forma_h(rtrim(rtrim(number_format($o['special_cents'] / 100, 2, ',', ''), '0'), ',')) : '' ?>"></div>
                        <div class="stack"><label for="ob<?= $k ?>">Zahlung</label><select id="ob<?= $k ?>" name="billing[<?= $k ?>]"><option value="once"<?= $o['billing'] === 'once' ? ' selected' : '' ?>>einmalig</option><option value="monthly"<?= $o['billing'] === 'monthly' ? ' selected' : '' ?>>monatlich (Abo)</option></select></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <button class="btn" type="submit">Angebote speichern</button>
        </form>
        <?php try { $tp = mm_test_promo(); } catch (Throwable $e) { $tp = null; } if ($tp && (int)$tp['until'] > time()): ?>
        <div class="stack" style="margin-top:12px">
            <p><strong>Gutscheincode für deine Testbuchung</strong></p>
            <p class="small"><span class="sel" style="font-size:20px;letter-spacing:.06em"><?= forma_h($tp['code']) ?></span> · 100 % · höchstens 3 Mal · bis <?= forma_h(date('d. m. Y', (int)$tp['until'])) ?><?= $tp['live'] ? '' : ' · Stripe-Testmodus' ?></p>
            <p class="muted small">Im Stripe-Bezahlfenster bei „Gutscheincode hinzufügen“ eingeben, dann zahlst du € 0. Das Feld erscheint nur beim regulären Preis. Test-Abo danach in Stripe kündigen. Bitte nicht weitergeben.</p>
        </div>
        <?php endif; ?>
        <details class="stack" style="margin-top:12px;border-top:1px solid var(--line);padding-top:10px">
            <summary><strong>Buchungen aus Gathering-Angeboten (<?= count($orders) ?>)</strong> <span class="muted small">· frühere Inner-Circle- und Jahreskreis-Buchungen stehen auch hier</span></summary>
            <?php if (!$orders): ?><p class="muted small">Noch keine. Probier es im Testraum aus: Angebot einblenden und mit dem Gast-Link (zweites Gerät) „buchen“. Im Stripe-Testmodus geht das mit der Testkarte 4242 4242 4242 4242.</p><?php endif; ?>
            <?php foreach ($orders as $o): ?>
                <div class="mm-signup<?= $o['status'] === 'abgebrochen' ? ' is-cancelled' : '' ?>">
                    <span><strong><?= forma_h($o['name'] ?: '–') ?></strong> <span class="muted small sel"><?= forma_h($o['email']) ?></span><br>
                    <span class="muted small"><?= forma_h(date('d. m. Y, H:i', strtotime($o['created_at']))) ?> · <?= forma_h($o['title']) ?> · <?= forma_h(forma_euro((int)$o['amount_cents'])) ?><?= $o['billing'] === 'monthly' ? '/Monat' : '' ?><?= str_starts_with((string)($o['plan'] ?? ''), 'inst') ? ' · ' . (int)substr((string)$o['plan'], 4) . ' Raten' : '' ?><?= (int)($o['bill_start'] ?? 0) > time() ? ' · Abbuchung ab ' . date('d.m.Y', (int)$o['bill_start']) : '' ?><?= ($o['plan'] ?? '') === 'p12' ? ' · Jahresmitgliedschaft' : (($o['plan'] ?? '') === 'p6' ? ' · 6 Monate im Voraus' : '') ?><?= ($o['consent_digital'] ?? 0) ? ' · Kursbereich sofort (Verzicht bestätigt)' : '' ?> · <?= $o['special'] ? 'Gathering-Preis' : 'regulär' ?><?= ($o['consent'] ?? 0) ? ' · Sofortstart bestätigt' : '' ?><?= $o['test'] ? ' · Test' : '' ?><?= ($o['referred_by'] ?? 0) ? ' · eingeladen (Bestellung #' . (int)$o['referred_by'] . ')' : '' ?><?= ($o['ref_bonus'] ?? 0) ? ' · hat ' . (int)$o['ref_bonus'] . ' Freundin(nen) eingeladen' : '' ?><?= ($o['ref_code'] ?? '') !== '' ? ' · Einladungslink: <span class="sel">' . forma_h(mm_site() . '/circle/?ref=' . $o['ref_code']) . '</span>' : '' ?></span></span>
                    <span class="mm-badge<?= $o['status'] === 'offen' ? ' on' : '' ?>"><?= ['bezahlt' => 'bezahlt', 'offen' => 'Zahlung offen', 'abgebrochen' => 'abgebrochen'][$o['status']] ?? forma_h($o['status']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($openOrd): ?><form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_order_recheck"><button class="link small" type="submit">Offene Zahlungen bei Stripe prüfen</button></form><?php endif; ?>
        </details>
        </details>
        <script>(function(){var h=location.hash.slice(1);if(!h)return;var e=document.getElementById(h);if(e&&e.tagName==='DETAILS')e.open=true;})();</script>

        <?php endif; ?>
        <?php if ($part === 'einst'): ?>
        <div class="mm-links" style="margin:0 0 16px">
            <a href="https://moveciamovement.com/" target="_blank" rel="noopener">Website</a>
            <a href="https://moveciamovement.com/links/" target="_blank" rel="noopener">Link in Bio</a>
            <a href="https://moveciamovement.com/reset/" target="_blank" rel="noopener">Reset Routine</a>
            <a href="https://dashboard.mailerlite.com/automations" target="_blank" rel="noopener">MailerLite</a>
        </div>
        <div class="card stack" style="margin-bottom:12px"><p class="small" style="margin:0">Hier ist alles an einem Ort: Videoraum (Whereby), Google-Bewertungslink, Postfach, E-Mail-Liste und die letzten Mails. <b>Bunny (Videos)</b> verbindest du unter <a href="<?= forma_h($self) ?>?view=method#bunny">Method &amp; Unfold → Bunny</a> <?= function_exists('mv_bunny_ok') && mv_bunny_ok() ? '(✓ verbunden)' : '(noch nicht verbunden)' ?>.</p></div>
        <h2 id="einstellungen">Einstellungen für Movecia</h2>
        <div class="card stack">
            <p><strong>Whereby (Videoraum)</strong> <?= $hasWhereby ? '<span class="mm-badge">verbunden</span>' : '<span class="mm-badge on">noch nicht verbunden</span>' ?></p>
            <p class="muted small">Im Whereby-Dashboard einen API-Schlüssel erstellen und hier einfügen. Falls Whereby nach erlaubten Domains fragt, trag dort <strong>moveciamovement.com</strong> ein, damit der Raum auf deiner Website angezeigt werden darf. Der Schlüssel liegt nur in deinem Datenordner, nie auf GitHub.</p>
            <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_save_whereby">
                <input name="whereby_key" type="password" autocomplete="off" placeholder="<?= $hasWhereby ? 'Neuen Schlüssel einfügen, um ihn zu ersetzen' : 'Whereby API-Schlüssel' ?>">
                <button class="btn" type="submit">Whereby verbinden</button></form>
            <?php if ($hasWhereby): ?><form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_remove_whereby"><button class="link small" type="submit">Whereby trennen</button></form><?php endif; ?>
        </div>
        <div class="card stack" style="margin-top:12px">
            <p><strong>Google-Bewertungslink</strong> <?= mm_review_url() !== '' ? '<span class="mm-badge">hinterlegt</span>' : '<span class="mm-badge on">fehlt noch</span>' ?></p>
            <p class="muted small">Für die Danke-Mail mit Bitte um eine Google-Bewertung: am Morgen nach dem Ende von Unfold und eine Stunde nach der letzten Prä-/Postnatal-Einheit. Den Link findest du im Google-Unternehmensprofil von Movecia unter „Nach Rezensionen fragen“. Solange er fehlt, warten die Mails und gehen raus, sobald du ihn einträgst.</p>
            <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_save_review">
                <input name="review_url" type="url" autocomplete="off" value="<?= forma_h(mm_review_url()) ?>" placeholder="https://g.page/r/…/review">
                <button class="btn" type="submit">Link speichern</button></form>
        </div>
        <div class="card stack" style="margin-top:12px">
            <p><strong>Absender hello@moveciamovement.com</strong> <?= $hasMail ? '<span class="mm-badge">verbunden</span>' : '<span class="mm-badge on">noch nicht verbunden</span>' ?></p>
            <p class="muted small">Damit Raum-Links und Erinnerungen sicher ankommen und nicht im Spam landen, gehen sie über dein Hostinger-Postfach hello@moveciamovement.com raus. Trag dafür einmal das Passwort dieses Postfachs ein.</p>
            <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_save_mail">
                <input name="mail_password" type="password" autocomplete="off" placeholder="Passwort von hello@moveciamovement.com">
                <button class="btn" type="submit">Postfach verbinden</button></form>
            <?php if ($hasMail): ?><form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_remove_mail"><button class="link small" type="submit">Postfach trennen</button></form><?php endif; ?>
        </div>
        <h2 id="liste">E-Mail-Liste</h2>
        <div class="card stack">
            <p class="small muted">Die Frauen melden sich auf moveciamovement.com an und bekommen eine Bestätigungsmail von hello@moveciamovement.com. Erst nach dem Klick sind sie auf der Liste, bekommen die Reset Routine und landen automatisch in MailerLite in der Gruppe „Wild Hearts“ (getrennt von der FORMA Post).<?= $ml && empty($ml['error']) ? ' MailerLite zählt dort gerade ' . (int)$ml['active'] . ' aktive Kontakte.' : '' ?></p>
            <?php if (!$subs): ?><p class="muted small">Noch niemand. Teste es einmal selbst mit deiner eigenen Adresse.</p><?php endif; ?>
            <?php foreach (array_slice($subs, 0, 60) as $x): ?>
                <div class="mm-signup<?= $x['status'] === 'abgemeldet' ? ' is-cancelled' : '' ?>">
                    <span><strong><?= forma_h($x['name']) ?></strong> <span class="muted small sel"><?= forma_h($x['email']) ?></span><br>
                    <span class="muted small"><?= forma_h(date('d. m. Y', strtotime($x['created_at']))) ?> · über <?= forma_h($srcName[$x['source']] ?? $x['source']) ?> · <?= ($x['lang'] ?? 'en') === 'de' ? 'Deutsch' : 'Englisch' ?></span></span>
                    <span style="display:flex;gap:10px;align-items:center"><span class="mm-badge<?= $x['status'] === 'offen' ? ' on' : '' ?>"><?= ['bestaetigt' => 'bestätigt', 'offen' => 'noch nicht bestätigt', 'abgemeldet' => 'abgemeldet'][$x['status']] ?? forma_h($x['status']) ?></span>
                    <?php if ($x['status'] !== 'abgemeldet'): ?><form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_resend_confirm"><input type="hidden" name="sid" value="<?= (int)$x['id'] ?>"><button class="link small" type="submit">Mail nochmal schicken</button></form><?php endif; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 id="mails">Letzte Mails</h2>
        <div class="card stack">
            <form method="post" class="row" style="align-items:end"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_test_mail">
                <div class="stack"><label for="mtm">Test-Mail an</label><input id="mtm" name="to" type="email" required placeholder="deine@adresse.at"></div>
                <button class="btn" type="submit">Test-Mail schicken</button></form>
            <?php
            $pdo->exec('CREATE TABLE IF NOT EXISTS mm_mail_log (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT NOT NULL, to_addr TEXT NOT NULL, subject TEXT NOT NULL, method TEXT NOT NULL, ok INTEGER NOT NULL, error TEXT NOT NULL DEFAULT \'\')');
            $logs = $pdo->query('SELECT * FROM mm_mail_log ORDER BY id DESC LIMIT 12')->fetchAll(); ?>
            <?php if (!$logs): ?><p class="muted small">Noch keine Mail verschickt.</p><?php endif; ?>
            <?php foreach ($logs as $l): ?>
                <div class="mm-signup"><span><strong><?= forma_h($l['subject']) ?></strong><br><span class="muted small"><?= forma_h(date('d. m., H:i', strtotime($l['at']))) ?> · an <?= forma_h($l['to_addr']) ?> · <?= forma_h($l['method']) ?></span>
                <?php if ($l['error'] !== ''): ?><br><span class="small" style="color:#A0463A"><?= forma_h($l['error']) ?></span><?php endif; ?></span>
                <span><span class="mm-badge<?= $l['ok'] ? '' : ' on' ?>"><?= $l['ok'] ? 'verschickt' : 'fehlgeschlagen' ?></span></span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php });
}

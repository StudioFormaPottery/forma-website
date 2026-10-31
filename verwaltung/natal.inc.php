<?php
// Verwaltung → Movecia → „Prä- & Postnatal“ (?view=natal): Angebote verschicken, Terminanfragen bestätigen,
// Kundinnen mit Paketen, Einstellungen. Wird von verwaltung/movecia.php eingebunden (angemeldet, CSRF geprüft).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/natal-lib.php';
natal_schema($pdo);
natal_bonus_tick($pdo);
$nBack = $self . '?view=natal';

function natal_wa(string $phone): string
{
    $d = preg_replace('/[^\d+]/', '', $phone);
    if (str_starts_with($d, '+')) $d = substr($d, 1);
    elseif (str_starts_with($d, '00')) $d = substr($d, 2);
    elseif (str_starts_with($d, '0')) $d = '43' . substr($d, 1);
    return 'https://wa.me/' . $d;
}

function natal_daypart_time(string $p): string
{
    return ['vormittag' => '09:00', 'nachmittag' => '14:00', 'abend' => '18:30'][$p] ?? '09:00';
}

if ($action === 'mm_natal_offer') {
    $sal = in_array($_POST['salutation'] ?? '', ['Liebe', 'Lieber', 'Hallo'], true) ? (string)$_POST['salutation'] : 'Liebe';
    $name = forma_clean((string)($_POST['name'] ?? ''), 80);
    $note = trim(mb_substr((string)($_POST['note'] ?? ''), 0, 600));
    if (mb_strlen($name) < 2) go($nBack . '#angebot', 'Bitte gib den Namen ein.');
    $mode = in_array($_POST['mode'] ?? '', ['online', 'mix'], true) ? (string)$_POST['mode'] : 'studio';
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) go($nBack . '#angebot', 'Die E-Mail-Adresse stimmt nicht ganz. Bitte nochmal prüfen.');
    // Mix & Match (Vivien, 30.10.2026): z. B. Pränatal 4 × im Studio + 2 × online, Preise je Art aus dem jeweiligen Paket
    $pack = ''; $ms = 0; $mo = 0; $packs0 = natal_packages();
    if ($mode === 'mix') {
        // Gesamtzahl = Einheiten des Pakets, die Aufteilung ist nur ein Vorschlag: sie wählt selbst auf der Seite
        $bk = in_array($_POST['pack'] ?? '', ['prae', 'post', 'kombi'], true) ? (string)$_POST['pack'] : 'prae';
        $tot = (int)$packs0[$bk]['units'];
        $on = max(0, min($tot, (int)($_POST['mix_online'] ?? 0)));
        $mx = natal_mix_calc($bk, $tot - $on, $on);
        if (!$mx) go($nBack . '#angebot', 'Bitte gib bei Mix & Match mindestens eine Einheit an.');
        [$pack, $ms, $mo] = [$mx['base'], $mx['studio'], $mx['online']];
        $mode = $ms ? 'studio' : 'online';
    }
    $grp = '';
    $ins = $pdo->prepare('INSERT INTO mm_natal_offers (salutation, name, note, token, mode, email, grp, created_at, pack, mix_studio, mix_online) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([$sal, $name, $note, bin2hex(random_bytes(10)), $mode, $email, $grp, date('c'), $pack, $ms, $mo]);
    $first = (int)$pdo->lastInsertId();
    go($nBack . '&neu=' . $first . '#angebot', 'Das Angebot für ' . $name . ' ist fertig. ' . ($email !== '' ? 'Schau dir die Mail in der Vorschau an und schick sie dann ab.' : 'Kopier die Nachricht oder schick sie direkt per WhatsApp.'));
}
if ($action === 'mm_natal_offer_mail') {
    $q = $pdo->prepare('SELECT * FROM mm_natal_offers WHERE id = ?');
    $q->execute([(int)($_POST['aid'] ?? 0)]);
    $a = $q->fetch();
    if (!$a) go($nBack . '#angebot', 'Das Angebot gibt es nicht mehr.');
    $test = ($_POST['test'] ?? '') === '1';
    $ok = natal_offer_send($pdo, $a, $test);
    go($nBack . '#angebot', $ok ? ($test ? 'Die Vorschau ist an ' . natal_notify_email() . ' unterwegs.' : 'Die Mail an ' . $a['name'] . ' (' . $a['email'] . ') ist verschickt.') : 'Die Mail konnte nicht verschickt werden. Ist das Postfach-Passwort unter Movecia → Einstellungen hinterlegt?');
}
if ($action === 'mm_natal_offer_del') {
    $q = $pdo->prepare('SELECT grp FROM mm_natal_offers WHERE id = ?');
    $q->execute([(int)($_POST['aid'] ?? 0)]);
    $g = (string)$q->fetchColumn();
    if ($g !== '') $pdo->prepare('DELETE FROM mm_natal_offers WHERE grp = ?')->execute([$g]);
    else $pdo->prepare('DELETE FROM mm_natal_offers WHERE id = ?')->execute([(int)($_POST['aid'] ?? 0)]);
    go($nBack . '#angebot', 'Der Link ist gelöscht und funktioniert nicht mehr als persönliches Angebot.');
}
if ($action === 'mm_natal_confirm') {
    $s = natal_confirm($pdo, (int)($_POST['sid'] ?? 0), (string)($_POST['date'] ?? ''), (string)($_POST['time'] ?? ''), !empty($_POST['mail']));
    go($nBack . '#anfragen', $s ? 'Bestätigt: ' . natal_when($s) . (!empty($_POST['mail']) ? '. Die Bestätigung mit Kalenderdatei ist unterwegs.' : ' (ohne Mail).') : 'Bitte Datum und Uhrzeit angeben.');
}
if ($action === 'mm_natal_cancel') {
    natal_cancel($pdo, (int)($_POST['sid'] ?? 0), trim(mb_substr((string)($_POST['reason'] ?? ''), 0, 400)), !empty($_POST['mail']));
    go($nBack . '#anfragen', 'Abgesagt. Die Einheit ist wieder frei' . (!empty($_POST['mail']) ? ' und sie hat eine Mail mit dem Link für einen neuen Wunschtermin bekommen.' : '.'));
}
if ($action === 'mm_natal_call_cancel') {
    natal_cancel_call($pdo, (int)($_POST['cid'] ?? 0), trim(mb_substr((string)($_POST['reason'] ?? ''), 0, 400)), !empty($_POST['mail']));
    go($nBack . '#gespraeche', 'Das Kennenlerngespräch ist abgesagt' . (!empty($_POST['mail']) ? ', sie hat eine Mail mit Link für einen neuen Termin bekommen.' : '.'));
}
if ($action === 'mm_natal_done') {
    $pdo->prepare("UPDATE mm_natal_sessions SET status = 'erledigt' WHERE id = ? AND status = 'bestaetigt'")->execute([(int)($_POST['sid'] ?? 0)]);
    go($nBack . '#termine', 'Abgehakt.');
}
if ($action === 'mm_natal_add') {
    $o = natal_order($pdo, (int)($_POST['oid'] ?? 0));
    $date = (string)($_POST['date'] ?? '');
    if (!$o || $o['status'] !== 'bezahlt' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) go($nBack . '#kundinnen', 'Bitte ein Datum wählen.');
    if (natal_left($pdo, $o) <= 0) go($nBack . '&k=' . $o['id'] . '#k' . $o['id'], 'Alle Einheiten dieses Pakets sind schon verplant.');
    $sm = '';
    if (natal_is_mix($o)) {
        $ml = natal_mix_left($pdo, $o);
        $sm = ($_POST['smode'] ?? '') === 'online' ? 'online' : 'studio';
        if ($ml[$sm] <= 0) go($nBack . '&k=' . $o['id'] . '#k' . $o['id'], 'Für ' . ($sm === 'online' ? 'online' : 'das Studio') . ' sind keine Einheiten mehr frei.');
    }
    $pdo->prepare("INSERT INTO mm_natal_sessions (order_id, date, status, created_at, mode) VALUES (?,?,'angefragt',?,?)")->execute([$o['id'], $date, date('c'), $sm]);
    $s = natal_confirm($pdo, (int)$pdo->lastInsertId(), $date, (string)($_POST['time'] ?? ''), !empty($_POST['mail']));
    if (!$s) { $pdo->prepare("DELETE FROM mm_natal_sessions WHERE order_id = ? AND start = '' AND date = ? AND status = 'angefragt'")->execute([$o['id'], $date]); go($nBack . '&k=' . $o['id'] . '#k' . $o['id'], 'Bitte auch eine Uhrzeit angeben.'); }
    go($nBack . '&k=' . $o['id'] . '#k' . $o['id'], 'Eingetragen: ' . natal_when($s) . '.');
}
// Rücktritt abwickeln (Vivien, 31.10.2026): z. B. nach dem allgemeinen Widerrufsformular oder bei einer Einzelstunde
if ($action === 'mm_natal_wd') {
    $oid = (int)($_POST['oid'] ?? 0);
    $r = natal_wd_execute($pdo, $oid, (string)($_POST['choice'] ?? ''), (int)($_POST['count'] ?? 1), (int)($_POST['design'] ?? 1), 'verwaltung');
    $w = $r['ok'] ? $r['wd'] : null;
    go($nBack . '&k=' . $oid . '#k' . $oid, $r['ok'] ? 'Rücktritt abgewickelt: ' . forma_euro((int)$w['back_cents']) . ($w['choice'] === 'gutschein' ? ' als Gutschein (' . implode(', ', array_column($r['vouchers'], 'nr')) . ')' : ' zurück') . '. Sie hat die Bestätigung bekommen' . ($w['status'] === 'fehler' ? '. ⚠ Die Erstattung über Stripe ging nicht, bitte unten erneut versuchen.' : '.') : $r['error']);
}
if ($action === 'mm_natal_wd_retry') {
    $w = natal_wd_row($pdo, (int)($_POST['oid'] ?? 0));
    if ($w && $w['status'] === 'fehler') $w = natal_wd_refund($pdo, $w);
    go($nBack . '&k=' . (int)($_POST['oid'] ?? 0) . '#k' . (int)($_POST['oid'] ?? 0), $w && $w['status'] === 'erledigt' ? 'Erstattet über Stripe.' : 'Hat wieder nicht geklappt. Bitte in Stripe von Hand erstatten und dann „erledigt“ tippen.');
}
if ($action === 'mm_natal_wd_done') {
    $pdo->prepare("UPDATE mm_natal_wd SET status = 'erledigt', done_at = ?, note = 'von Hand in Stripe erstattet' WHERE order_id = ? AND status = 'fehler'")->execute([date('c'), (int)($_POST['oid'] ?? 0)]);
    go($nBack . '&k=' . (int)($_POST['oid'] ?? 0) . '#k' . (int)($_POST['oid'] ?? 0), 'Als erledigt markiert.');
}
if ($action === 'mm_natal_recheck') {
    $o = natal_order($pdo, (int)($_POST['oid'] ?? 0));
    $st = $o ? natal_check_paid($pdo, $o) : 'offen';
    go($nBack . '#kundinnen', $st === 'bezahlt' ? 'Die Zahlung ist da. Bestätigung und Rechnung sind verschickt.' : 'Noch keine Zahlung bei Stripe.');
}
if ($action === 'mm_natal_settings') {
    $cfg = natal_cfg();
    foreach (natal_packages() as $k => $p) {
        $v = mm_euro_in((string)($_POST['price'][$k] ?? ''));
        if ($v > 0) $cfg['prices'][$k] = $v;
    }
    $cfg['weekdays'] = array_values(array_map('intval', array_intersect(array_keys(NATAL_WEEKDAYS), array_map('intval', (array)($_POST['weekdays'] ?? [])))));
    if (!$cfg['weekdays']) $cfg['weekdays'] = [1, 2, 3, 4, 5];
    $n = trim((string)($_POST['notify'] ?? ''));
    $cfg['notify'] = filter_var($n, FILTER_VALIDATE_EMAIL) ? $n : '';
    // Spotify-Playlists für den Mitgliederbereich: je Zeile „Titel | Link“
    $sp = [];
    foreach (preg_split('/\R/', (string)($_POST['spotify'] ?? '')) as $ln) {
        $parts = array_map('trim', explode('|', $ln, 2));
        $url = $parts[1] ?? $parts[0];
        if (natal_spotify_embed($url) !== '') $sp[] = ['title' => isset($parts[1]) && $parts[0] !== '' ? mb_substr($parts[0], 0, 40) : 'Musik', 'url' => $url];
    }
    $cfg['spotify'] = array_slice($sp, 0, 6);
    $cfg['bonus'] = ['on' => !empty($_POST['bonus_on']), 'percent' => max(1, min(30, (int)($_POST['bonus_percent'] ?? 7))), 'days' => max(1, min(30, (int)($_POST['bonus_days'] ?? 7)))];
    mm_save_cfg('natal', $cfg);
    go($nBack . '#einstellungen', 'Gespeichert.');
}
if ($action === 'mm_natal_bonus_send') {
    natal_bonus_schema($pdo);
    $c = $pdo->query('SELECT * FROM mm_natal_calls WHERE id = ' . (int)($_POST['cid'] ?? 0))->fetch();
    $ok = $c && natal_bonus_send($pdo, $c);
    go($nBack . '#bonus', $ok ? 'Der Kennenlern-Bonus ist an ' . $c['name'] . ' unterwegs.' : 'Die Mail konnte nicht verschickt werden.');
}
if ($action === 'mm_natal_testkundin') {
    $r = natal_test_order($pdo, $_POST);
    go($nBack . ($r['ok'] ? '&k=' . (int)$r['order']['id'] . '#testkundin' : '#testkundin'), $r['ok'] ? 'Die Testkundin ist angelegt. Schau jetzt in das Postfach von ' . $r['order']['email'] . ': Dort ist die Buchungsmail mit dem Knopf „Zu meinem Mitgliederbereich“. Am besten in einem privaten Browserfenster oder auf dem Handy öffnen.' : $r['error']);
}
if ($action === 'mm_natal_testdel') {
    $ok = natal_test_delete($pdo, (int)($_POST['oid'] ?? 0));
    go($nBack . '#testkundin', $ok ? 'Die Testkundin ist gelöscht (Buchung, Termine, Aufnahmen und Konto).' : 'Das war keine Testbuchung.');
}
if ($action === 'mm_natal_sample') {
    $inv = ['kind' => 'natal', 'nr' => 'MUSTER-' . date('Y') . '-000', 'issued_at' => date('Y-m-d'), 'service_date' => 'nach Vereinbarung', 'name' => 'Lena Muster', 'email' => natal_notify_email(),
        'address' => "Musterweg 1\n6561 Ischgl", 'country' => 'AT', 'vat_rate' => 20, 'vat_note' => 'MUSTER – keine gültige Rechnung.',
        'items' => json_encode([['text' => 'Prä-/Postnatal Training · Kombi-Paket', 'detail' => '14 Einheiten à 60 Min., 1:1 im Movecia Studio, Mathon · Termine nach Vereinbarung, gültig bis 6 Monate nach der Geburt', 'qty' => 14, 'unit' => 5200]], JSON_UNESCAPED_UNICODE),
        'gross_cents' => 72800, 'net_cents' => 60667, 'vat_cents' => 12133,
        'pay_note' => 'Der Betrag wurde am ' . date('d.m.Y') . ' online bezahlt (Kartenzahlung über Stripe, gemeinsames Konto FORMA & Movecia). Buchungsvermerk: Leistung von Movecia – Personal Fitness.'];
    natal_mail(natal_notify_email(), '[Muster] Rechnung Movecia', 'Muster-Rechnung', ['So sieht die Rechnung aus, die deine Kundinnen nach der Zahlung bekommen.'], null, [['Muster_Rechnung_Movecia.pdf', natal_invoice_pdf($inv), 'application/pdf']]);
    go($nBack . '#einstellungen', 'Die Muster-Rechnung ist an ' . natal_notify_email() . ' unterwegs.');
}

if ($action === 'mm_natal_birth') {
    $o = natal_order($pdo, (int)($_POST['oid'] ?? 0));
    $d = (string)($_POST['date'] ?? '');
    if (!$o || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d > date('Y-m-d')) go($nBack . '#kundinnen', 'Bitte gib das Geburtsdatum ein.');
    $notes = natal_birth_set($pdo, $o, $d, !empty($_POST['mail']));
    go($nBack . '&k=' . (int)$o['id'] . '#k' . (int)$o['id'], 'Eingetragen: Alle Einheiten ab ' . date('j. n.', strtotime($d)) . ' sind jetzt Postnatal. ' . implode(' ', $notes));
}
if ($action === 'mm_natal_birth_undo') {
    $pdo->prepare("UPDATE mm_natal_orders SET birth_date = '' WHERE id = ?")->execute([(int)($_POST['oid'] ?? 0)]);
    go($nBack . '&k=' . (int)$_POST['oid'] . '#k' . (int)$_POST['oid'], 'Geburtsdatum entfernt.');
}
if ($action === 'mm_natal_rec_del') {
    $q = $pdo->prepare('SELECT * FROM mm_natal_recs WHERE id = ?');
    $q->execute([(int)($_POST['rid'] ?? 0)]);
    $r = $q->fetch();
    if ($r) natal_rec_delete($pdo, $r);
    go($nBack . '&k=' . (int)($r['order_id'] ?? 0) . '#k' . (int)($r['order_id'] ?? 0), 'Aufnahme gelöscht.');
}
if ($action === 'mm_natal_prompter_save') {
    require_once dirname(__DIR__) . '/api/natal-prompter.inc.php';
    natal_prompter_save(json_decode((string)($_POST['json'] ?? '{}'), true) ?: []);
    header('Content-Type: application/json');
    exit('{"ok":true}');
}
if ($view === 'natal' && isset($_GET['babyvorschau'])) {
    $o = natal_order($pdo, (int)$_GET['babyvorschau']) ?: ['name' => 'Anna Beispiel', 'id' => 0, 'token' => 'vorschau'];
    header('Content-Type: text/html; charset=utf-8');
    echo natal_birth_mail($o)['html'];
    exit;
}
if ($view === 'natal' && isset($_GET['workbook'])) {
    require_once dirname(__DIR__) . '/api/natal-workbook.inc.php';
    $k = (string)$_GET['workbook'];
    if (!isset(natal_units()[$k])) { http_response_code(404); exit('Unbekannte Einheit.'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Workbook-' . $k . '.pdf"');
    echo natal_workbook_pdf($k, (string)($_GET['name'] ?? ''), '');
    exit;
}
if ($view === 'natal' && isset($_GET['prompter'])) {
    require __DIR__ . '/natal-prompter.inc.php';
    exit;
}
if ($view === 'natal' && isset($_GET['newsvorschau'])) {
    // Vorschau der Willkommensmail (nichts wird verschickt)
    $sub = ['name' => 'Anna', 'email' => 'vorschau@example.com', 'token' => str_repeat('0', 32), 'mode' => ''];
    header('Content-Type: text/html; charset=utf-8');
    echo natal_news_welcome_html($sub);
    exit;
}

if ($view === 'natal' && isset($_GET['bonusvorschau'])) {
    $m = natal_bonus_mail(['name' => 'Anna', 'mode' => 'studio'], 'vorschau', date('Y-m-d', strtotime('+' . natal_bonus_cfg()['days'] . ' days')));
    header('Content-Type: text/html; charset=utf-8');
    echo $m['html'];
    exit;
}
if ($view === 'natal' && isset($_GET['mailvorschau'])) {
    $q = $pdo->prepare('SELECT * FROM mm_natal_offers WHERE id = ?');
    $q->execute([(int)$_GET['mailvorschau']]);
    $a = $q->fetch();
    if (!$a) { http_response_code(404); exit('Angebot nicht gefunden.'); }
    $m = natal_offer_mail(natal_offer_group($pdo, $a));
    header('Content-Type: text/html; charset=utf-8');
    echo $m['html'];
    exit;
}

if ($view === 'natal') {
    $orders = $pdo->query("SELECT * FROM mm_natal_orders WHERE status IN ('bezahlt','offen','widerrufen') ORDER BY CASE status WHEN 'bezahlt' THEN 0 WHEN 'offen' THEN 1 ELSE 2 END, id DESC LIMIT 80")->fetchAll();
    $byId = [];
    foreach ($orders as $o) $byId[(int)$o['id']] = $o;
    $reqs = $pdo->query("SELECT s.*, o.name, o.title, o.phone, o.email, o.due_date, o.units FROM mm_natal_sessions s JOIN mm_natal_orders o ON o.id = s.order_id WHERE s.status = 'angefragt' ORDER BY s.date, s.id")->fetchAll();
    $upcoming = $pdo->query("SELECT s.*, o.name, o.title, o.phone, o.email, o.package FROM mm_natal_sessions s JOIN mm_natal_orders o ON o.id = s.order_id WHERE s.status = 'bestaetigt' ORDER BY s.date, s.start")->fetchAll();
    $offers = $pdo->query('SELECT a.*, (SELECT COUNT(*) FROM mm_natal_orders o WHERE o.offer_id = a.id AND o.status = \'bezahlt\') AS booked FROM mm_natal_offers a ORDER BY a.id DESC LIMIT 30')->fetchAll();
    natal_member_schema($pdo);
    $revenue = (int)$pdo->query("SELECT COALESCE(SUM(amount_cents),0) FROM mm_natal_orders WHERE status = 'bezahlt' AND is_test = 0")->fetchColumn();
    $GLOBALS['natalSubs'] = $pdo->query("SELECT * FROM mm_natal_subs ORDER BY CASE status WHEN 'bestaetigt' THEN 0 WHEN 'offen' THEN 1 ELSE 2 END, id DESC LIMIT 200")->fetchAll();
    natal_call_schema($pdo);
    $calls = $pdo->prepare("SELECT * FROM mm_natal_calls WHERE status = 'gebucht' AND date >= ? ORDER BY date, slot");
    $calls->execute([date('Y-m-d')]);
    $GLOBALS['natalCalls'] = $calls->fetchAll();
    $today = date('Y-m-d');
    render_page('Prä- & Postnatal', function () use ($self, $flash, $pdo, $orders, $reqs, $upcoming, $offers, $revenue, $today) {
        $packs = natal_packages();
        $cfg = natal_cfg();
        $newId = (int)($_GET['neu'] ?? 0);
        $openK = (int)($_GET['k'] ?? 0);
        $future = array_values(array_filter($upcoming, fn($s) => $s['date'] >= $today));
        $past = array_values(array_filter($upcoming, fn($s) => $s['date'] < $today));
        $paidCount = count(array_filter($orders, fn($o) => $o['status'] === 'bezahlt'));
        $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '">';
        $eu = fn(int $c) => $c ? rtrim(rtrim(number_format($c / 100, 2, ',', ''), '0'), ',') : ''; ?>
<style>
:root{--paper:#F2ECE4;--card:#FBF8F4;--clay:#7D5E68;--soft:#7D7076;--line:#DDD2C6;--ink:#2E2529}
body{background:var(--paper)}
h1,h2{color:#6B5658}
.nh{position:relative;overflow:hidden;border-radius:26px;background:#6B5658;color:#FBF5F7;padding:24px 24px 22px}
.nh::after{content:"";position:absolute;width:220px;height:210px;right:-80px;top:-90px;border-radius:62% 38% 46% 54%/55% 45% 55% 45%;background:rgba(232,214,202,.35)}
.nh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:34px;margin:2px 0 4px;position:relative}
.nh p{position:relative;margin:0;color:#EADFDE}.nh .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
.ns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}
@media(min-width:700px){.ns{grid-template-columns:repeat(4,minmax(0,1fr))}}
.ns a,.ns span.st{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:12px 14px;color:var(--ink);text-decoration:none;display:flex;flex-direction:column}
.ns b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-variant-numeric:lining-nums;font-size:32px;line-height:1.1;color:#7D5E68}.ns span span{font-size:14px;color:var(--soft);line-height:1.3}
.ns .hot b{color:#A0463A}
.nl{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.nl a{display:inline-block;padding:7px 14px;border-radius:999px;background:var(--card);border:1px solid var(--line);text-decoration:none;color:#6B5658;font-size:15px}
.rq{display:flex;flex-direction:column;gap:10px}
.rq .when{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:22px;line-height:1.2;color:var(--ink)}
.rq .tm{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.chip{display:inline-block;border-radius:999px;padding:1px 10px;font-size:13px;background:#EADFDE;color:#6B5658}
.chip.ok{background:#7D5E68;color:#FBF5F7}.chip.wait{background:#E8D6CA}
.ln{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;border-top:1px solid var(--line);padding:8px 0}
.acts{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.btn{background:#7D5E68;border-color:#7D5E68;color:#FBF5F7}.btn-ghost{background:transparent;color:#7D5E68}
.link{color:#7D5E68}
details.more summary{cursor:pointer;color:#7D5E68;font-size:15px}
.msg{width:100%;min-height:150px;font-size:15px;line-height:1.5}
.is-new{outline:2px solid #7D5E68}
.bar{height:8px;border-radius:9px;background:#EADFDE;overflow:hidden}.bar i{display:block;height:100%;background:#7D5E68}
.wdays{display:flex;flex-wrap:wrap;gap:6px 14px}
</style>
        <p><a href="<?= forma_h($self) ?>?view=movecia">← Movecia</a> · <a href="<?= forma_h($self) ?>">FORMA</a></p>
        <?php mm_subnav('natal'); ?>
        <section class="nh"><p class="eyebrow">Movecia · 1:1 im Studio</p><h1>Prä- &amp; Postnatal</h1><p>Angebote verschicken, Wunschtermine bestätigen und alle Pakete im Blick.</p></section>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="ns">
            <a href="#anfragen" class="<?= $reqs ? 'hot' : '' ?>"><b><?= count($reqs) ?></b><span>Terminanfragen zu bestätigen</span></a>
            <a href="#termine"><b><?= count($future) ?></b><span>bestätigte Termine</span></a>
            <a href="#kundinnen"><b><?= $paidCount ?></b><span>gebuchte Pakete</span></a>
            <span class="st"><b><?= forma_h(forma_euro($revenue)) ?></b><span>Umsatz brutto</span></span>
        </div>
        <div class="nl">
            <a href="<?= forma_h(mm_site()) ?>/natal/" target="_blank" rel="noopener">Seite ansehen</a>
            <a href="#testkundin">Als Kundin testen</a>
            <a href="#skripte">Skripte &amp; Workbooks</a>
            <a href="#angebot">Angebot verschicken</a>
            <a href="#gutscheine">Gutscheine</a>
            <a href="<?= forma_h($self) ?>?view=rechnungen">Rechnungen</a>
            <a href="#einstellungen">Preise &amp; Einstellungen</a>
        </div>
<style>
.qa{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:14px 0 4px}
@media(min-width:900px){.qa{grid-template-columns:repeat(4,minmax(0,1fr))}}
.qa form{margin:0}
.qa a,.qa button{display:flex;flex-direction:column;gap:4px;width:100%;height:100%;box-sizing:border-box;text-align:left;padding:16px;border-radius:20px;border:1px solid #DDD2C6;background:#FBF8F4;color:#2E2529;text-decoration:none;font:inherit;cursor:pointer}
.qa b{font-size:17px;font-weight:600;color:#4A3A41}
.qa span{font-size:14px;color:#7D7076;line-height:1.35}
.qa span{overflow-wrap:anywhere}
.qa .ic{font-size:26px;line-height:1}
.qa .main{background:#7D5E68;border-color:#7D5E68}.qa .main b,.qa .main span{color:#FBF5F7}
</style>
        <h2 style="margin-top:18px">Vor dem Termin</h2>
        <div class="qa">
            <form method="post" target="_blank"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_testroom"><button class="main" type="submit"><span class="ic">🎥</span><b>Video &amp; Technik testen</b><span>Kamera, Mikro, Teleprompter, Aufnahme – danach direkt in deinen Testraum</span></button></form>
            <a href="#termine"><span class="ic">📅</span><b>Nächste Online-Termine</b><span>Bei jedem Termin „Zum Videoraum“ – öffnet 60 Min. vorher, mit Teleprompter</span></a>
        </div>

        <?php $calls = $GLOBALS['natalCalls'] ?? []; ?>
        <h2 id="gespraeche">Kennenlerngespräche</h2>
        <?php if (!$calls): ?><p class="card muted small">Noch keine Kennenlerngespräche gebucht. Kundinnen buchen sie selbst auf der Angebotsseite (Mo–Fr 8:00 oder 19:00, je 30 Min. online). Du bekommst jedes Mal eine Mail mit Kalenderdatei.</p><?php endif; ?>
        <?php if ($calls): ?><div class="card">
            <?php foreach ($calls as $c): ?>
                <div class="ln"><span><strong><?= forma_h(natal_call_when($c)) ?></strong> <span class="chip"><?= $c['mode'] === 'online' ? 'Interesse online' : 'Interesse Studio' ?></span><br>
                    <span class="small"><?= forma_h($c['name']) ?> · <a href="mailto:<?= forma_h($c['email']) ?>"><?= forma_h($c['email']) ?></a><?php if ($c['phone'] !== ''): ?> · <a href="<?= forma_h(natal_wa((string)$c['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></span>
                    <?php if ($c['note'] !== ''): ?><br><span class="small muted">„<?= forma_h($c['note']) ?>“</span><?php endif; ?></span>
                    <span class="acts small"><a class="btn btn-sm" href="<?= forma_h(natal_room_link('c', (int)$c['id'], true)) ?>" target="_blank" rel="noopener">Zum Gespräch</a>
                    <details class="more"><summary>absagen</summary>
                        <form method="post" class="stack" style="margin-top:6px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_call_cancel"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><textarea name="reason" rows="2" placeholder="Nachricht (optional)"></textarea>
                            <label class="check"><input type="checkbox" name="mail" value="1" checked> Mail mit Link für neuen Termin</label><button class="btn btn-ghost btn-sm" type="submit">Gespräch absagen</button></form></details></span></div>
            <?php endforeach; ?></div><?php endif; ?>
        <?php natal_bonus_schema($pdo); $bc = natal_bonus_cfg();
        $sent = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM mm_natal_orders o WHERE o.bonus_call_id = c.id AND o.status = 'bezahlt') AS used FROM mm_natal_calls c WHERE c.bonus_state <> '' ORDER BY c.bonus_sent_at DESC LIMIT 20")->fetchAll();
        $open = $pdo->query("SELECT * FROM mm_natal_calls WHERE status = 'gebucht' AND bonus_state = '' AND date < '" . date('Y-m-d') . "' ORDER BY date DESC LIMIT 10")->fetchAll(); ?>
        <div class="card stack" id="bonus" style="margin-top:12px">
            <p class="small"><b>Kennenlern-Bonus</b> · <?= $bc['on'] ? 'an' : '<b>aus</b>' ?>: 15 Minuten nach jedem Gespräch bekommt sie automatisch eine Mail mit <?= (int)$bc['percent'] ?> % auf alle Pakete, gültig <?= (int)$bc['days'] ?> Tage. <a href="<?= forma_h($self) ?>?view=natal&amp;bonusvorschau=1" target="_blank" rel="noopener">Mail ansehen</a> · <a href="#einstellungen">ändern</a></p>
            <?php foreach ($open as $c): ?><div class="ln"><span class="small"><?= forma_h($c['name']) ?> · Gespräch am <?= forma_h(natal_call_when($c)) ?> · noch kein Bonus</span>
                <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_bonus_send"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Bonus jetzt schicken</button></form></div><?php endforeach; ?>
            <?php foreach ($sent as $c): ?><div class="ln"><span class="small"><?= forma_h($c['name']) ?> · geschickt <?= $c['bonus_sent_at'] ? forma_h(date('d. m.', strtotime((string)$c['bonus_sent_at']))) : '' ?> · gültig bis <?= forma_h(natal_date_de((string)$c['bonus_until'], false)) ?></span>
                <span class="chip<?= (int)$c['used'] ? ' ok' : '' ?>"><?= (int)$c['used'] ? 'gebucht' : ((string)$c['bonus_until'] < date('Y-m-d') ? 'abgelaufen' : 'offen') ?></span></div><?php endforeach; ?>
        </div>

        <h2 id="anfragen">Terminanfragen</h2>
        <?php if (!$reqs): ?><p class="card muted small">Gerade keine offenen Anfragen. Sobald eine Kundin Wunschtermine schickt, bekommst du eine Mail an <?= forma_h(natal_notify_email()) ?> und sie stehen hier.</p><?php endif; ?>
        <div class="list">
        <?php foreach ($reqs as $r): $o = natal_order($pdo, (int)$r['order_id']); $ph = $o ? natal_phase($o, (string)$r['date']) : ''; ?>
            <div class="card rq">
                <div class="booking-head"><span><span class="chip wait">Wunsch</span> <strong><?= forma_h($r['name']) ?></strong> <span class="muted small">· <?= forma_h($r['title']) ?></span></span>
                    <span class="acts small"><?php if ($r['phone'] !== ''): ?><a href="tel:<?= forma_h($r['phone']) ?>">Anrufen</a> · <a href="<?= forma_h(natal_wa((string)$r['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></span></div>
                <p class="when"><?= forma_h(natal_when($r)) ?><?php if ($o && natal_is_mix($o)): ?> <span class="chip<?= ($r['mode'] ?? '') === 'online' ? ' ok' : '' ?>"><?= ($r['mode'] ?? '') === 'online' ? '💻 online' : '🏡 im Studio' ?></span><?php endif; ?></p>
                <?php if ($ph || $r['note'] !== ''): ?><p class="muted small"><?= forma_h(trim($ph . ($r['note'] !== '' ? ' · „' . $r['note'] . '“' : ''), ' ·')) ?></p><?php endif; ?>
                <form method="post" class="stack"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_confirm"><input type="hidden" name="sid" value="<?= (int)$r['id'] ?>">
                    <div class="tm"><div class="stack"><label>Datum</label><input type="date" name="date" value="<?= forma_h($r['date']) ?>" required></div><div class="stack"><label>Uhrzeit</label><input type="time" name="time" value="<?= forma_h((string)($r['wish_time'] ?? '') !== '' ? (string)$r['wish_time'] : natal_daypart_time((string)$r['daypart'])) ?>" step="900" required></div></div>
                    <label class="check"><input type="checkbox" name="mail" value="1" checked> Bestätigung mit Kalenderdatei an sie schicken</label>
                    <button class="btn" type="submit">Termin bestätigen</button>
                </form>
                <details class="more"><summary>Geht nicht? Absagen</summary>
                    <form method="post" class="stack" style="margin-top:8px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_cancel"><input type="hidden" name="sid" value="<?= (int)$r['id'] ?>">
                        <label>Kurze Nachricht an sie (optional)</label><textarea name="reason" rows="2" placeholder="z. B. An dem Tag bin ich leider nicht im Studio. Wie wäre der Tag danach?"></textarea>
                        <label class="check"><input type="checkbox" name="mail" value="1" checked> Mail mit Link für einen neuen Wunschtermin schicken</label>
                        <button class="btn btn-ghost" type="submit">Wunschtermin absagen</button></form></details>
            </div>
        <?php endforeach; ?>
        </div>

        <h2 id="termine">Nächste Termine</h2>
        <?php if (!$future && !$past): ?><p class="card muted small">Noch keine bestätigten Termine.</p><?php endif; ?>
        <?php if ($future): ?><div class="card">
            <?php foreach ($future as $s): ?>
                <?php $so = natal_order($pdo, (int)$s['order_id']); $uk = $so ? natal_session_key($pdo, $so, (int)$s['id']) : 'frei'; ?>
                <div class="ln"><span><strong><?= forma_h(natal_when($s)) ?></strong><br><span class="muted small"><?= forma_h($s['name']) ?> · <?= forma_h($s['title']) ?></span><br><span class="chip"><?= forma_h(natal_unit_label($uk)) ?></span><?php if ($so && natal_is_mix($so)): ?> <span class="chip"><?= natal_sess_online($so, $s) ? '💻 online' : '🏡 im Studio' ?></span><?php endif; ?></span>
                    <span class="acts small"><a class="btn btn-ghost btn-sm" href="<?= forma_h($self) ?>?view=natal&amp;prompter=s<?= (int)$s['id'] ?>" target="_blank" rel="noopener">Teleprompter</a><?php if (natal_is_online($s)): ?><a class="btn btn-sm" href="<?= forma_h(natal_room_link('s', (int)$s['id'], true)) ?>" target="_blank" rel="noopener">Zum Videoraum</a><?php endif; ?><?php if ($s['phone'] !== ''): ?><a href="<?= forma_h(natal_wa((string)$s['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                    <details class="more"><summary>ändern / absagen</summary>
                        <form method="post" class="stack" style="margin-top:6px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_confirm"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>">
                            <div class="tm" style="display:grid;grid-template-columns:1fr 1fr;gap:6px"><input type="date" name="date" value="<?= forma_h($s['date']) ?>"><input type="time" name="time" value="<?= forma_h($s['start']) ?>" step="900"></div>
                            <label class="check"><input type="checkbox" name="mail" value="1" checked> neue Bestätigung schicken</label><button class="btn btn-sm" type="submit">Verschieben</button></form>
                        <form method="post" class="stack" style="margin-top:6px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_cancel"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><textarea name="reason" rows="2" placeholder="Nachricht (optional)"></textarea>
                            <label class="check"><input type="checkbox" name="mail" value="1" checked> Mail an sie</label><button class="btn btn-ghost btn-sm" type="submit">Termin absagen</button></form>
                    </details></span></div>
            <?php endforeach; ?></div><?php endif; ?>
        <?php if ($past): ?><p class="muted small" style="margin-top:12px">Vorbei, noch nicht abgehakt:</p><div class="card">
            <?php foreach ($past as $s): ?><div class="ln"><span><?= forma_h(natal_when($s)) ?> · <?= forma_h($s['name']) ?></span>
                <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_done"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="link small" type="submit">War da ✓</button></form></div><?php endforeach; ?></div><?php endif; ?>

        <h2 id="kundinnen">Kundinnen &amp; Pakete</h2>
        <?php if (!$orders): ?><p class="card muted small">Noch keine Buchungen. Schick deiner Kundin unten ihr persönliches Angebot.</p><?php endif; ?>
        <div class="list">
        <?php foreach ($orders as $o): $ss = natal_sessions($pdo, (int)$o['id']); $used = natal_used($pdo, (int)$o['id']); $done = count(array_filter($ss, fn($s) => in_array($s['status'], ['bestaetigt', 'erledigt'], true))); ?>
            <details class="card" id="k<?= (int)$o['id'] ?>" <?= $openK === (int)$o['id'] ? 'open' : '' ?>>
                <summary style="cursor:pointer;list-style:none"><div class="booking-head"><span><strong><?= forma_h($o['name']) ?></strong> <span class="muted small">· <?= forma_h($o['title']) ?></span><?php if (!empty($o['is_test'])): ?> <span class="chip" style="background:#EADFDE;color:#6B5658">TEST</span><?php endif; ?></span>
                    <?php $hd0 = natal_health_get($o); if ($hd0 && array_filter(natal_health_flags($hd0), fn($x) => $x[0] === 'rot')): ?><span class="chip" style="background:#F6E7E4;color:#A0463A">⚠ Freigabe</span> <?php endif; ?><?php if ($o['status'] === 'widerrufen'): ?><span class="chip" style="background:#F6E7E4;color:#A0463A">zurückgetreten</span> <?php elseif ($o['status'] === 'bezahlt' && empty($o['confirm_at'])): ?><span class="chip wait" title="Start-Häkchen aus der Mail noch nicht gesetzt (beim Buchen hat sie es schon bestätigt)">Start offen</span> <?php endif; ?><span class="chip <?= $o['status'] === 'bezahlt' ? 'ok' : 'wait' ?>"><?= $o['status'] === 'bezahlt' ? 'bezahlt ' . forma_h(forma_euro((int)$o['amount_cents'])) : 'Zahlung offen' ?></span></div>
                    <?php if ($o['status'] === 'bezahlt'): ?><p class="small muted" style="margin:6px 0 4px"><?= $done ?> bestätigt · <?= $used - $done ?> angefragt · <?= (int)$o['units'] - $used ?> noch frei von <?= (int)$o['units'] ?></p><div class="bar"><i style="width:<?= (int)round($used / max(1, (int)$o['units']) * 100) ?>%"></i></div><?php endif; ?></summary>
                <div class="stack" style="margin-top:12px">
                    <p class="small"><a href="mailto:<?= forma_h($o['email']) ?>"><?= forma_h($o['email']) ?></a><?php if ($o['phone'] !== ''): ?> · <a href="tel:<?= forma_h($o['phone']) ?>"><?= forma_h($o['phone']) ?></a> · <a href="<?= forma_h(natal_wa((string)$o['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?><br>
                    Geburtstermin: <?= $o['due_date'] !== '' ? forma_h(natal_date_de((string)$o['due_date'])) : '–' ?> · gebucht am <?= forma_h(date('d. m. Y', strtotime((string)($o['paid_at'] ?: $o['created_at'])))) ?></p>
                    <?php if ($o['note'] !== ''): ?><p class="small" style="background:#F2ECE4;border-radius:12px;padding:8px 12px">„<?= forma_h($o['note']) ?>“</p><?php endif; ?>
                    <?php $hd = natal_health_get($o); if ($hd): $fl = natal_health_flags($hd); ?>
                        <div class="small" style="border:1px solid var(--line);border-radius:12px;padding:8px 12px"><strong>Gesundheit</strong><?php foreach ($fl as [$lv, $tx]): ?><br><span style="color:<?= $lv === 'rot' ? '#A0463A' : '#8a6a2a' ?>">⚠ <?= forma_h($tx) ?></span><?php endforeach; ?>
                            <?php foreach (natal_health_lines($hd) as $ln): ?><br><span class="muted"><?= forma_h($ln) ?></span><?php endforeach; ?></div>
                    <?php elseif ($o['status'] === 'bezahlt' && !$o['purged_at']): ?><p class="small" style="color:#A0463A">Gesundheitsfragebogen fehlt noch. Sie kann ihn auf ihrer persönlichen Seite ausfüllen.</p><?php endif; ?>
                    <?php foreach ($ss as $s): $uk = natal_session_key($pdo, $o, (int)$s['id']); $rec = natal_rec_for($pdo, (int)$s['id']); $past = natal_session_done($s); ?>
                        <div class="ln small"><span><?= forma_h(natal_when($s)) ?><?= natal_is_mix($o) ? ' · ' . natal_mode_label($o, $s) : '' ?><?php if ($s['status'] !== 'abgesagt'): ?><br><span class="muted"><?= forma_h(natal_unit_label($uk)) ?></span><?php endif; ?></span>
                            <span class="acts"><span class="chip<?= $s['status'] === 'bestaetigt' ? ' ok' : ($s['status'] === 'angefragt' ? ' wait' : '') ?>"><?= forma_h(natal_status_label((string)$s['status'])) ?></span>
                            <?php if ($s['status'] !== 'abgesagt' && natal_order_is_member($o)): ?>
                                <?php if ($rec): ?><a href="/api/natal-rec.php?id=<?= (int)$rec['id'] ?>&amp;k=<?= forma_h(natal_rec_admin_key((int)$rec['id'])) ?>" target="_blank" rel="noopener">▶ Aufnahme (<?= (int)round($rec['bytes'] / 1e6) ?> MB)</a>
                                    <form method="post" onsubmit="return confirm('Aufnahme wirklich löschen?')"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_rec_del"><input type="hidden" name="rid" value="<?= (int)$rec['id'] ?>"><button class="link small" type="submit">löschen</button></form>
                                <?php elseif ($past || $s['status'] === 'erledigt'): ?><label class="link small" style="cursor:pointer">Aufnahme hochladen<input type="file" accept="video/*" hidden data-up="<?= forma_h('/api/natal-rec.php?r=s' . (int)$s['id'] . '&k=' . natal_room_key('s', (int)$s['id'], true)) ?>"></label><span class="upst muted"></span><?php endif; ?>
                            <?php endif; ?></span></div>
                    <?php endforeach; ?>
                    <?php if ($o['status'] === 'bezahlt'): $hasM = natal_member_by_email($pdo, (string)$o['email']); ?>
                        <div class="small" style="border-top:1px solid var(--line);padding-top:10px">
                            <?php if (natal_order_is_member($o)): ?>
                                <strong>Mitgliederbereich:</strong> <?= $hasM ? 'Konto angelegt' . ($hasM['last_login'] ? ', zuletzt da am ' . forma_h(date('d. m.', strtotime((string)$hasM['last_login']))) : '') : 'noch kein Passwort gewählt' ?>
                                · <?= natal_member_full($o) ? 'alles offen' : 'Vorschau bis ' . forma_h(date('d. m.', natal_member_full_from($o))) . ' (Widerrufsfrist)' ?>
                                · Zugang bis <?= forma_h(date('d. m. Y', strtotime(natal_order_until($pdo, $o)))) ?>
                                <?php if (!$hasM): ?><br>Link zum Passwort wählen: <button type="button" class="link small" data-copy="<?= forma_h(natal_member_start_link($o)) ?>">kopieren</button><?php endif; ?>
                            <?php else: ?><strong>Einzelstunde:</strong> kein Mitgliederbereich, nur die persönliche Terminseite.<?php endif; ?>
                        </div>
                        <?php if ((string)$o['birth_date'] !== ''): ?>
                            <form method="post" class="small" style="background:#F2ECE4;border-radius:12px;padding:8px 12px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_birth_undo"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">👶 Baby geboren am <?= forma_h(date('d. m. Y', strtotime((string)$o['birth_date']))) ?><?= $o['birth_mailed_at'] ? ' · Glückwunsch-Mail verschickt' : '' ?> · <button class="link small" type="submit">entfernen</button></form>
                        <?php elseif (preg_replace('/^o_/', '', (string)$o['package']) !== 'post'): ?>
                            <details class="more"><summary>👶 Baby ist da</summary>
                                <form method="post" class="stack" style="margin-top:8px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_birth"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
                                    <label>Geburtsdatum</label><input type="date" name="date" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                                    <p class="small muted">Alle Einheiten ab diesem Tag werden zu Postnatal-Einheiten (ab Postnatal 1).</p>
                                    <label class="check"><input type="checkbox" name="mail" value="1" checked> Glückwunsch-Mail an sie schicken (<a href="<?= forma_h($self) ?>?view=natal&amp;babyvorschau=<?= (int)$o['id'] ?>" target="_blank" rel="noopener">Mail ansehen</a>)</label>
                                    <button class="btn btn-sm" type="submit">Eintragen</button></form></details>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php $wdr = natal_wd_row($pdo, (int)$o['id']); if ($wdr): $wvs = $pdo->query('SELECT * FROM mm_vouchers WHERE wd_id = ' . (int)$wdr['id'])->fetchAll(); ?>
                        <div class="small" style="background:#F6E7E4;border-radius:12px;padding:8px 12px"><strong>Rücktritt am <?= forma_h(date('d. m. Y, H:i', strtotime((string)$wdr['created_at']))) ?></strong> (<?= $wdr['source'] === 'verwaltung' ? 'von dir' : 'von ihr im Mitgliederbereich' ?>)<br>
                            Gesamt <?= forma_h(forma_euro((int)$wdr['total_cents'])) ?> · stattgefunden <?= forma_h($wdr['used_text']) ?> · zurück <?= forma_h(forma_euro((int)$wdr['back_cents'])) ?><br>
                            <?php if ((int)$wdr['refund_cents'] > 0): ?>Erstattung <?= forma_h(forma_euro((int)$wdr['refund_cents'])) ?>: <?= $wdr['status'] === 'erledigt' ? '✓ ' . ($wdr['refund_id'] !== '' ? 'über Stripe' : forma_h($wdr['note'])) : '<b style="color:#A0463A">⚠ ' . forma_h($wdr['note']) . '</b>' ?><br><?php endif; ?>
                            <?php foreach ($wvs as $gv): ?>Gutschein <?= forma_h($gv['nr']) ?> · <?= forma_h(forma_euro((int)$gv['amount_cents'])) ?> · <a href="<?= forma_h(mv_voucher_pdf_link($gv)) ?>" target="_blank" rel="noopener">PDF</a><br><?php endforeach; ?>
                            <?php if ($wdr['status'] === 'fehler'): ?><span class="acts" style="margin-top:6px"><form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_wd_retry"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm" type="submit">Erstattung erneut versuchen</button></form>
                                <form method="post" onsubmit="return confirm('Hast du in Stripe schon von Hand erstattet?')"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_wd_done"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="link small" type="submit">von Hand erstattet ✓</button></form></span><?php endif; ?>
                        </div>
                    <?php elseif ($o['status'] === 'bezahlt'): $wc = natal_wd_calc($pdo, $o); ?>
                        <details class="more"><summary>↩︎ Rücktritt abwickeln</summary>
                            <form method="post" class="stack" style="margin-top:8px" onsubmit="return confirm('Rücktritt jetzt verbindlich abwickeln? Termine werden abgesagt und sie bekommt die Bestätigung.')"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_wd"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
                                <p class="small muted">Zum Beispiel, wenn sie über das allgemeine Widerrufsformular oder per Nachricht zurückgetreten ist. Gesamt <?= forma_h(forma_euro($wc['total'])) ?> · stattgefunden <?= forma_h($wc['used_text']) ?> · <b>zurück <?= forma_h(forma_euro($wc['back'])) ?></b><?= time() > $wc['until'] ? ' · Frist war bis ' . forma_h(date('d. m.', $wc['until'])) . ' (du kannst trotzdem kulant sein)' : '' ?><?= $wc['test'] ? ' · TEST: keine echte Erstattung' : '' ?></p>
                                <div class="wdays"><label class="check"><input type="radio" name="choice" value="erstattung" checked> Rückerstattung über Stripe</label><label class="check"><input type="radio" name="choice" value="gutschein"> Gutschein</label></div>
                                <div class="wdays"><label class="check"><input type="radio" name="count" value="1" checked> 1 Gutschein</label><label class="check"><input type="radio" name="count" value="2"> 2 Gutscheine (50/50)</label>
                                    <?php foreach (MV_VOUCHER_DESIGNS as $dn => $dl): ?><label class="check"><input type="radio" name="design" value="<?= $dn ?>"<?= $dn === 1 ? ' checked' : '' ?>> Design <?= forma_h($dl) ?></label><?php endforeach; ?></div>
                                <button class="btn btn-ghost btn-sm" type="submit">Rücktritt abwickeln</button></form></details>
                    <?php endif; ?>
                    <p class="small">Ihre persönliche Seite: <span class="sel"><?= forma_h(natal_link($o)) ?></span> <button type="button" class="link small" data-copy="<?= forma_h(natal_link($o)) ?>">kopieren</button></p>
                    <?php if ($o['status'] === 'offen'): ?>
                        <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_recheck"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Zahlung bei Stripe prüfen</button></form>
                    <?php elseif (natal_left($pdo, $o) > 0): ?>
                        <form method="post" class="stack" style="border-top:1px solid var(--line);padding-top:10px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_add"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
                            <label>Termin selbst eintragen (z. B. am Telefon ausgemacht)</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input type="date" name="date" required><input type="time" name="time" step="900" required></div>
                            <?php if (natal_is_mix($o)): $ml = natal_mix_left($pdo, $o); ?><div class="wdays"><label class="check"><input type="radio" name="smode" value="studio"<?= $ml['studio'] ? ' checked' : ' disabled' ?>> im Studio (noch <?= $ml['studio'] ?>)</label><label class="check"><input type="radio" name="smode" value="online"<?= !$ml['studio'] && $ml['online'] ? ' checked' : '' ?><?= $ml['online'] ? '' : ' disabled' ?>> online (noch <?= $ml['online'] ?>)</label></div><?php endif; ?>
                            <label class="check"><input type="checkbox" name="mail" value="1" checked> Bestätigung mit Kalenderdatei schicken</label>
                            <button class="btn btn-sm" type="submit">Eintragen</button></form>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
        </div>

        <?php $mvv = $pdo->query('SELECT * FROM mm_vouchers ORDER BY id DESC LIMIT 60')->fetchAll(); ?>
        <h2 id="gutscheine">Gutscheine</h2>
        <?php if (!$mvv): ?><p class="card muted small">Noch keine Movecia-Gutscheine. Sie entstehen automatisch, wenn eine Kundin beim Rücktritt „Als Gutschein“ wählt (oder du es in der Verwaltung so abwickelst). Eingelöst werden sie auf der Buchungsseite mit dem Code, der Restwert bleibt.</p><?php else: ?>
        <div class="card">
            <?php foreach ($mvv as $gv): $bal = mv_voucher_balance($pdo, $gv); ?>
            <div class="ln"><span><strong><?= forma_h($gv['nr']) ?></strong> · <?= forma_h(forma_euro((int)$gv['amount_cents'])) ?><?= $gv['status'] === 'aktiv' && $bal < (int)$gv['amount_cents'] ? ' · Rest ' . forma_h(forma_euro($bal)) : '' ?><br>
                <span class="small muted"><?= forma_h($gv['note']) ?> · <?= forma_h(date('d. m. Y', strtotime((string)$gv['created_at']))) ?> · Code <?= forma_h($gv['code']) ?></span></span>
                <span class="acts small"><span class="chip<?= $gv['status'] === 'aktiv' ? ' ok' : '' ?>"><?= $gv['status'] === 'aktiv' ? 'gültig' : ($gv['status'] === 'eingeloest' ? 'eingelöst' : 'Test') ?></span><a href="<?= forma_h(mv_voucher_pdf_link($gv)) ?>" target="_blank" rel="noopener">PDF</a><a href="<?= forma_h($self) ?>?view=natal&amp;k=<?= (int)$gv['order_id'] ?>#k<?= (int)$gv['order_id'] ?>">Buchung</a></span></div>
            <?php endforeach; ?>
        </div><?php endif; ?>

        <h2 id="testkundin">Als Kundin testen</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_testkundin">
            <p class="small muted">Leg dir eine Testkundin mit einer zweiten E-Mail-Adresse an (z. B. deine private). Du bekommst dort die echte Buchungsmail, legst dein Passwort an und siehst den Mitgliederbereich genau wie eine Kundin. Kostet nichts, es gibt keine Rechnung und sie zählt nicht zum Umsatz. Öffne die Mail am besten auf dem Handy oder in einem privaten Browserfenster.</p>
            <div class="row"><div class="stack"><label for="tkn">Name</label><input id="tkn" name="name" value="Test Kundin" maxlength="80"></div>
                <div class="stack"><label for="tke">E-Mail für den Test</label><input id="tke" name="email" type="email" required placeholder="deine zweite Adresse"></div></div>
            <div class="stack"><label for="tkp">Paket</label><select id="tkp" name="package"><?php foreach (natal_packages() as $pk => $pp): if ((int)$pp['units'] < 2) continue; ?><option value="<?= forma_h($pk) ?>"<?= $pk === 'o_kombi' ? ' selected' : '' ?>><?= forma_h($pp['title']) ?></option><?php endforeach; ?></select></div>
            <div class="stack"><label for="tkm">Mix &amp; Match: davon online (0 = kein Mix, nur bei Studio-Paketen)</label><input id="tkm" name="mix_online" type="number" min="0" max="13" value="0" style="max-width:140px"></div>
            <label class="check"><input type="checkbox" name="full" value="1" checked> Mitgliederbereich sofort komplett zeigen (die 14 Tage Widerrufsfrist überspringen)</label>
            <label class="check"><input type="checkbox" name="done" value="1" checked> Eine Einheit gestern als erledigt eintragen (damit das erste Workbook freigeschaltet ist)</label>
            <label class="check"><input type="checkbox" name="room" value="1" checked> Online-Termin in 20 Minuten (Videoraum öffnet sofort, du kannst dich als Kundin und hier als Gastgeberin einwählen)</label>
            <button class="btn" type="submit">Testkundin anlegen und Mail schicken</button>
        </form>
        <?php $tests = $pdo->query('SELECT * FROM mm_natal_orders WHERE is_test = 1 ORDER BY id DESC')->fetchAll(); if ($tests): ?><div class="card stack" style="margin-top:10px">
            <?php foreach ($tests as $t): $ts = $pdo->query("SELECT * FROM mm_natal_sessions WHERE order_id = " . (int)$t['id'] . " AND status = 'bestaetigt' ORDER BY date, start LIMIT 1")->fetch(); ?>
            <div class="ln"><span class="small"><strong><?= forma_h($t['name']) ?></strong> · <?= forma_h($t['email']) ?> · <?= forma_h($t['title']) ?></span>
                <span class="acts small"><a href="<?= forma_h(mm_site()) ?>/natal/mitglied/" target="_blank" rel="noopener">Login</a> · <a href="<?= forma_h(natal_link($t)) ?>" target="_blank" rel="noopener">Ihre Terminseite</a><?php if ($ts): ?> · <a href="<?= forma_h(natal_room_link('s', (int)$ts['id'])) ?>" target="_blank" rel="noopener">Videoraum als Kundin</a> · <a href="<?= forma_h(natal_room_link('s', (int)$ts['id'], true)) ?>" target="_blank" rel="noopener">als Gastgeberin</a><?php endif; ?>
                <form method="post" onsubmit="return confirm('Testkundin mit Konto und Aufnahmen löschen?')"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_testdel"><input type="hidden" name="oid" value="<?= (int)$t['id'] ?>"><button class="link small" type="submit">löschen</button></form></span></div>
            <?php endforeach; ?></div><?php endif; ?>

        <h2 id="skripte">Skripte &amp; Workbooks</h2>
        <div class="card stack">
            <p class="small muted">Für jede Einheit gibt es ein fertiges Teleprompter-Skript (Impuls, Übungen, Breathwork, Meditation, Abschluss) und ein Workbook. Im Videoraum wählt der Teleprompter die Einheit automatisch, bei Studio-Terminen öffnest du ihn unter „Nächste Termine“. Hier kannst du alles testen und deine Texte anpassen.</p>
            <div class="acts"><a class="btn btn-sm" href="<?= forma_h($self) ?>?view=natal&amp;prompter=kennenlernen" target="_blank" rel="noopener">Teleprompter · Kennenlerngespräch</a>
                <a class="btn btn-ghost btn-sm" href="<?= forma_h(mm_site()) ?>/natal/mitglied/?demo=prae" target="_blank" rel="noopener">Mitgliederbereich (Beispiel prä)</a>
                <a class="btn btn-ghost btn-sm" href="<?= forma_h(mm_site()) ?>/natal/mitglied/?demo=post" target="_blank" rel="noopener">… post</a>
                <a class="btn btn-ghost btn-sm" href="<?= forma_h(mm_site()) ?>/natal/mitglied/?demo=vorschau" target="_blank" rel="noopener">… erste 14 Tage</a>
                <a class="btn btn-ghost btn-sm" href="<?= forma_h($self) ?>?view=natal&amp;babyvorschau=0" target="_blank" rel="noopener">„Baby ist da“-Mail</a></div>
            <?php foreach (NATAL_UNIT_KEYS as $uk): ?><div class="ln"><span><?= forma_h(natal_unit_label($uk)) ?></span><span class="acts small"><a href="<?= forma_h($self) ?>?view=natal&amp;prompter=<?= $uk ?>" target="_blank" rel="noopener">Teleprompter</a><a href="<?= forma_h($self) ?>?view=natal&amp;workbook=<?= $uk ?>" target="_blank" rel="noopener">Workbook (PDF)</a></span></div><?php endforeach; ?>
        </div>

        <?php $subs = $GLOBALS['natalSubs'] ?? []; $subOk = count(array_filter($subs, fn($x) => $x['status'] === 'bestaetigt')); ?>
        <h2 id="newsletter">Newsletter Prä- &amp; Postnatal <span class="chip"><?= $subOk ?> bestätigt</span></h2>
        <div class="card stack">
            <p class="small muted">Anmeldung auf der Prä- &amp; Postnatal-Seite und im Link in Bio. Nach der Bestätigung bekommt jede automatisch die Mail mit allen Paketen, Preisen, dem Ablauf und beiden Flyern, und sie kommt in die MailerLite-Gruppe „Prä- &amp; Postnatal“. Du bekommst jedes Mal eine kurze Info.</p>
            <div class="acts"><a class="btn btn-ghost btn-sm" href="<?= forma_h($self) ?>?view=natal&amp;newsvorschau=1" target="_blank" rel="noopener">Willkommensmail ansehen</a></div>
            <?php foreach (array_slice($subs, 0, 60) as $x): ?>
                <div class="ln"><span><strong><?= forma_h($x['name']) ?></strong> <span class="muted small"><?= forma_h($x['email']) ?></span></span>
                    <span class="small muted"><?= $x['status'] === 'bestaetigt' ? 'bestätigt ' . forma_h(date('d. m.', strtotime((string)$x['confirmed_at']))) : ($x['status'] === 'offen' ? 'noch nicht bestätigt' : 'abgemeldet') ?><?= $x['source'] === 'bio' ? ' · Link in Bio' : '' ?></span></div>
            <?php endforeach; ?>
            <?php if (!$subs): ?><p class="small muted">Noch keine Anmeldungen.</p><?php endif; ?>
        </div>

        <h2 id="angebot">Angebot verschicken</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_offer">
            <p class="small muted">Du bekommst einen persönlichen Link zu einer Seite mit Studio und Online: Die Seite begrüßt sie mit Namen, ihr Name ist beim Buchen schon eingetragen. Die fertige Nachricht kannst du kopieren oder direkt per WhatsApp schicken.</p>
            <div class="stack"><label>Was soll sie sehen?</label><div class="wdays"><label class="check"><input type="radio" name="mode" value="studio" checked> Alle Pakete, zuerst Studio</label><label class="check"><input type="radio" name="mode" value="online"> Alle Pakete, zuerst Online</label><label class="check"><input type="radio" name="mode" value="mix"> <b>Mix &amp; Match</b> (Studio + Online gemischt)</label></div><span class="small muted" id="mixhint">Sie kann oben jederzeit zwischen Studio und Online wechseln und sieht beide Preise.</span></div>
            <div class="card stack" id="mixbox" hidden style="background:#F2ECE4;border:0">
                <p class="small muted" style="margin:0">Sie wählt auf ihrer Seite selbst, wie viele Einheiten im Studio und wie viele online sind. Studio-Einheiten kosten den Studio-Paketpreis, Online-Einheiten den Online-Paketpreis, der Preis passt sich live an. Dann bestätigt sie, wählt Wunschtag + Wunschuhrzeit, zahlt und kommt direkt in ihren Mitgliederbereich.</p>
                <div class="row"><div class="stack"><label for="mxp">Paket</label><select id="mxp" name="pack"><?php foreach (['prae', 'post', 'kombi'] as $bk): $bs = $packs[$bk]; $bo = $packs['o_' . $bk]; ?><option value="<?= $bk ?>" data-s="<?= (int)$bs['unit_cents'] ?>" data-o="<?= (int)$bo['unit_cents'] ?>" data-u="<?= (int)$bs['units'] ?>"><?= forma_h(preg_replace('/-Paket$/', '', $bs['title'])) ?> · <?= (int)$bs['units'] ?> Einheiten</option><?php endforeach; ?></select></div></div>
                <div class="row"><div class="stack"><label for="mxo">Vorschlag: davon online (sie kann es ändern)</label><input id="mxo" name="mix_online" type="number" min="0" max="14" value="2" inputmode="numeric"></div></div>
                <p id="mxsum" style="margin:0;font-size:17px"></p>
            </div>
            <div class="row"><div class="stack"><label for="nsal">Anrede</label><select id="nsal" name="salutation"><option>Liebe</option><option>Lieber</option><option>Hallo</option></select></div>
                <div class="stack"><label for="nnm">Vorname</label><input id="nnm" name="name" required maxlength="80" placeholder="z. B. Anna"></div></div>
            <label for="nem">E-Mail (optional, dann kannst du das Angebot auch als schöne Mail mit den Flyern schicken)</label><input id="nem" name="email" type="email" maxlength="160" placeholder="name@beispiel.at">
            <label for="nnt">Persönliche Zeile auf der Seite (optional)</label><textarea id="nnt" name="note" rows="2" maxlength="600" placeholder="z. B. Wie besprochen starten wir mit einer Einheit pro Woche bis zur Geburt."></textarea>
            <button class="btn" type="submit">Angebot erstellen</button>
        </form>
        <p class="small muted" style="margin:10px 0">Allgemeiner Link ohne Namen (Studio und Online auf einer Seite, nicht verlinkt, nur wer den Link hat, sieht die Seite): <span class="sel"><?= forma_h(mm_site()) ?>/natal/</span> <button type="button" class="link small" data-copy="<?= forma_h(mm_site()) ?>/natal/">kopieren</button></p>
        <div class="list">
        <?php $seenGrp = []; foreach ($offers as $a):
            if ((string)($a['grp'] ?? '') !== '') { if (isset($seenGrp[$a['grp']])) continue; $seenGrp[$a['grp']] = 1; }
            $grpRows = natal_offer_group($pdo, $a); $a = $grpRows[0];
            $a['booked'] = 0; foreach ($grpRows as $gr) { $bq = $pdo->prepare("SELECT COUNT(*) FROM mm_natal_orders WHERE offer_id = ? AND status = 'bezahlt'"); $bq->execute([$gr['id']]); $a['booked'] += (int)$bq->fetchColumn(); if ($gr['opened_at'] && (!$a['opened_at'] || $gr['opened_at'] > $a['opened_at'])) $a['opened_at'] = $gr['opened_at']; }
            $isNew = in_array($newId, array_map(fn($r) => (int)$r['id'], $grpRows), true);
            $msg = natal_offer_message($a, $grpRows); ?>
            <div class="card stack<?= $isNew ? ' is-new' : '' ?>">
                <?php $amx = natal_offer_mix($a); ?>
                <div class="booking-head"><strong><?= forma_h($a['salutation'] . ' ' . $a['name']) ?> <span class="chip"><?= $amx ? 'Mix &amp; Match · ' . forma_h($amx['title']) . ' · ' . forma_h(forma_euro($amx['cents'])) : 'Studio + online' ?></span></strong><span class="muted small">erstellt <?= forma_h(date('d. m.', strtotime((string)$a['created_at']))) ?> · <?= (int)$a['booked'] ? '<span class="chip ok">gebucht</span>' : ($a['opened_at'] ? 'angesehen am ' . forma_h(date('d. m., H:i', strtotime((string)$a['opened_at']))) : 'noch nicht geöffnet') ?></span></div>
                <?php if ($isNew): ?><textarea class="msg" readonly><?= forma_h($msg) ?></textarea><?php endif; ?>
                <?php if ((string)($a['email'] ?? '') !== ''): ?>
                <div class="acts" style="padding:10px 12px;border-radius:12px;background:#EEF0E8"><span class="small">✉ <?= forma_h($a['email']) ?><?= $a['mailed_at'] ? ' · <b>Mail verschickt am ' . forma_h(date('d. m., H:i', strtotime((string)$a['mailed_at']))) . '</b>' : '' ?></span>
                    <a class="btn btn-ghost btn-sm" href="<?= forma_h($self) ?>?view=natal&amp;mailvorschau=<?= (int)$a['id'] ?>" target="_blank" rel="noopener">Mail ansehen</a>
                    <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_offer_mail"><input type="hidden" name="aid" value="<?= (int)$a['id'] ?>"><input type="hidden" name="test" value="1"><button class="btn btn-ghost btn-sm" type="submit">Vorschau an mich</button></form>
                    <form method="post" onsubmit="return confirm('Mail jetzt an <?= forma_h($a['email']) ?> schicken?')"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_offer_mail"><input type="hidden" name="aid" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm" type="submit"><?= $a['mailed_at'] ? 'Nochmal an ' : 'Jetzt an ' ?><?= forma_h($a['name']) ?> senden</button></form></div>
                <?php endif; ?>
                <div class="acts"><button type="button" class="btn btn-sm" data-copy="<?= forma_h($msg) ?>">Nachricht kopieren</button>
                    <a class="btn btn-ghost btn-sm" href="https://wa.me/?text=<?= rawurlencode($msg) ?>" target="_blank" rel="noopener">Per WhatsApp schicken</a>
                    <a class="link small" href="<?= forma_h(natal_offer_link($a)) ?>" target="_blank" rel="noopener">Seite ansehen</a>
                    <button type="button" class="link small" data-copy="<?= forma_h(natal_offer_link($a)) ?>">nur Link kopieren</button>
                    <form method="post" onsubmit="return confirm('Link wirklich löschen?')"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_offer_del"><input type="hidden" name="aid" value="<?= (int)$a['id'] ?>"><button class="link small" type="submit">löschen</button></form></div>
            </div>
        <?php endforeach; ?>
        </div>

        <h2 id="einstellungen">Preise &amp; Einstellungen</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_settings">
            <p class="small muted">Preise brutto inkl. 20 % USt. Gilt für neue Buchungen, bereits bezahlte Pakete bleiben, wie sie sind.</p>
            <div class="row"><?php foreach ($packs as $k => $p): ?><div class="stack"><label for="np<?= $k ?>"><?= forma_h($p['title']) ?> (<?= (int)$p['units'] ?> × 60 Min.) in €</label><input id="np<?= $k ?>" name="price[<?= $k ?>]" inputmode="decimal" value="<?= $eu($p['cents']) ?>"><span class="small muted">= <?= forma_h(forma_euro($p['unit_cents'])) ?> pro Einheit</span></div><?php endforeach; ?></div>
            <fieldset class="days"><legend>An diesen Tagen dürfen Wunschtermine gewählt werden</legend><div class="wdays"><?php foreach (NATAL_WEEKDAYS as $n => $l): ?><label class="check"><input type="checkbox" name="weekdays[]" value="<?= $n ?>"<?= in_array($n, natal_weekdays(), true) ? ' checked' : '' ?>> <?= $l ?></label><?php endforeach; ?></div></fieldset>
            <label for="nsp">Musik im Mitgliederbereich (Spotify-Playlists, je Zeile „Titel | Link“)</label>
            <textarea id="nsp" name="spotify" rows="3" placeholder="Ruhig ankommen | https://open.spotify.com/playlist/…"><?= forma_h(implode("\n", array_map(fn($x) => $x['title'] . ' | ' . $x['url'], (array)($cfg['spotify'] ?? [])))) ?></textarea>
            <span class="small muted">Link in Spotify über „Teilen → Link kopieren“. Ohne Eintrag zeigt der Bereich ruhige Klaviermusik von Spotify.</span>
            <label for="nno">Benachrichtigungen an</label><input id="nno" name="notify" type="email" value="<?= forma_h((string)($cfg['notify'] ?? '')) ?>" placeholder="<?= forma_h(MM_FROM_EMAIL) ?>">
            <fieldset class="days"><legend>Kennenlern-Bonus nach dem Gespräch</legend>
                <label class="check"><input type="checkbox" name="bonus_on" value="1"<?= natal_bonus_cfg()['on'] ? ' checked' : '' ?>> automatisch verschicken</label>
                <div class="row"><div class="stack"><label for="nbp">Rabatt auf Pakete in %</label><input id="nbp" name="bonus_percent" type="number" min="1" max="30" value="<?= (int)natal_bonus_cfg()['percent'] ?>"></div>
                <div class="stack"><label for="nbd">gültig (Tage)</label><input id="nbd" name="bonus_days" type="number" min="1" max="30" value="<?= (int)natal_bonus_cfg()['days'] ?>"></div></div>
                <span class="small muted">Gilt nur für Pakete, nicht für die Einzelstunde. Preise pro Einheit werden auf ganze Euro gerundet.</span></fieldset>
            <button class="btn" type="submit">Speichern</button>
        </form>
        <form method="post" style="margin-top:10px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_sample"><button class="link" type="submit">Muster-Rechnung an mich schicken</button></form>
<script>
// Aufnahme hochladen (z. B. vom iPhone): in Stücken direkt zum Termin
document.querySelectorAll('input[data-up]').forEach(inp => inp.addEventListener('change', async () => {
  const f = inp.files[0]; if (!f) return;
  const st = inp.closest('.acts').querySelector('.upst'), base = inp.dataset.up, CH = 8 * 1024 * 1024;
  const j0 = await (await fetch(base + '&a=start&mime=' + encodeURIComponent(f.type || 'video/mp4'), {method: 'POST'})).json().catch(() => ({}));
  if (!j0.ok) { st.textContent = 'Upload geht gerade nicht.'; return; }
  let off = 0;
  while (off < f.size) {
    st.textContent = Math.round(off / f.size * 100) + ' % …';
    try { const j = await (await fetch(base + '&a=chunk&id=' + j0.id + '&off=' + off, {method: 'POST', headers: {'Content-Type': 'application/octet-stream'}, body: f.slice(off, off + CH)})).json(); if (typeof j.size === 'number') off = j.size; else throw 0; }
    catch (e) { st.textContent = 'Verbindung hakt, versuche weiter …'; await new Promise(r => setTimeout(r, 3000)); }
  }
  const j = await (await fetch(base + '&a=finish&id=' + j0.id, {method: 'POST'})).json().catch(() => ({}));
  st.textContent = j.ok ? '✓ gespeichert' : 'Speichern hat nicht geklappt.';
  if (j.ok) setTimeout(() => location.reload(), 900);
}));
// Angebot: Mix & Match ein-/ausblenden und Summe live rechnen (verbindlich rechnet der Server)
(() => {
  const box = document.getElementById('mixbox'); if (!box) return;
  const p = document.getElementById('mxp'), on = document.getElementById('mxo'), out = document.getElementById('mxsum'), hint = document.getElementById('mixhint');
  const eur = c => '€ ' + (c % 100 ? (c / 100).toFixed(2).replace('.', ',') : String(c / 100));
  function upd() {
    const mix = document.querySelector('input[name=mode][value=mix]').checked;
    box.hidden = !mix; hint.hidden = mix;
    const o = p.selectedOptions[0], u = +o.dataset.u; on.max = u;
    const n = Math.min(u, Math.max(0, +on.value || 0)), s = u - n;
    out.innerHTML = '<b>Vorschlag: ' + s + ' im Studio + ' + n + ' online = ' + eur(s * +o.dataset.s + n * +o.dataset.o) + '</b> <span class="small muted">(alles im Studio ' + eur(u * +o.dataset.s) + ', alles online ' + eur(u * +o.dataset.o) + ')</span>';
  }
  document.querySelectorAll('input[name=mode]').forEach(r => r.addEventListener('change', upd));
  [p, on].forEach(x => x.addEventListener('input', upd));
  upd();
})();
document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
  const t = b.dataset.copy, old = b.textContent;
  try { await navigator.clipboard.writeText(t); } catch (e) { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); document.execCommand('copy'); a.remove(); }
  b.textContent = 'kopiert ✓'; setTimeout(() => b.textContent = old, 1800);
}));
</script>
    <?php });
    exit;
}

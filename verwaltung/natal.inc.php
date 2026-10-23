<?php
// Verwaltung → Movecia → „Prä- & Postnatal“ (?view=natal): Angebote verschicken, Terminanfragen bestätigen,
// Kundinnen mit Paketen, Einstellungen. Wird von verwaltung/movecia.php eingebunden (angemeldet, CSRF geprüft).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/natal-lib.php';
natal_schema($pdo);
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
    $mode = in_array($_POST['mode'] ?? '', ['studio', 'online', 'beide'], true) ? (string)$_POST['mode'] : 'studio';
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) go($nBack . '#angebot', 'Die E-Mail-Adresse stimmt nicht ganz. Bitte nochmal prüfen.');
    $grp = $mode === 'beide' ? bin2hex(random_bytes(6)) : '';
    $ins = $pdo->prepare('INSERT INTO mm_natal_offers (salutation, name, note, token, mode, email, grp, created_at) VALUES (?,?,?,?,?,?,?,?)');
    $first = 0;
    foreach ($mode === 'beide' ? ['studio', 'online'] : [$mode] as $m) {
        $ins->execute([$sal, $name, $note, bin2hex(random_bytes(10)), $m, $email, $grp, date('c')]);
        $first = $first ?: (int)$pdo->lastInsertId();
    }
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
    $pdo->prepare("INSERT INTO mm_natal_sessions (order_id, date, status, created_at) VALUES (?,?,'angefragt',?)")->execute([$o['id'], $date, date('c')]);
    $s = natal_confirm($pdo, (int)$pdo->lastInsertId(), $date, (string)($_POST['time'] ?? ''), !empty($_POST['mail']));
    if (!$s) { $pdo->prepare("DELETE FROM mm_natal_sessions WHERE order_id = ? AND start = '' AND date = ? AND status = 'angefragt'")->execute([$o['id'], $date]); go($nBack . '&k=' . $o['id'] . '#k' . $o['id'], 'Bitte auch eine Uhrzeit angeben.'); }
    go($nBack . '&k=' . $o['id'] . '#k' . $o['id'], 'Eingetragen: ' . natal_when($s) . '.');
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
    mm_save_cfg('natal', $cfg);
    go($nBack . '#einstellungen', 'Gespeichert.');
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
    $orders = $pdo->query("SELECT * FROM mm_natal_orders WHERE status IN ('bezahlt','offen') ORDER BY CASE status WHEN 'bezahlt' THEN 0 ELSE 1 END, id DESC LIMIT 80")->fetchAll();
    $byId = [];
    foreach ($orders as $o) $byId[(int)$o['id']] = $o;
    $reqs = $pdo->query("SELECT s.*, o.name, o.title, o.phone, o.email, o.due_date, o.units FROM mm_natal_sessions s JOIN mm_natal_orders o ON o.id = s.order_id WHERE s.status = 'angefragt' ORDER BY s.date, s.id")->fetchAll();
    $upcoming = $pdo->query("SELECT s.*, o.name, o.title, o.phone, o.email, o.package FROM mm_natal_sessions s JOIN mm_natal_orders o ON o.id = s.order_id WHERE s.status = 'bestaetigt' ORDER BY s.date, s.start")->fetchAll();
    $offers = $pdo->query('SELECT a.*, (SELECT COUNT(*) FROM mm_natal_orders o WHERE o.offer_id = a.id AND o.status = \'bezahlt\') AS booked FROM mm_natal_offers a ORDER BY a.id DESC LIMIT 30')->fetchAll();
    $revenue = (int)$pdo->query("SELECT COALESCE(SUM(amount_cents),0) FROM mm_natal_orders WHERE status = 'bezahlt'")->fetchColumn();
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
.nh h1{color:#FBF5F7;font-family:Georgia,"Times New Roman",serif;font-weight:400;font-size:34px;margin:2px 0 4px;position:relative}
.nh p{position:relative;margin:0;color:#EADFDE}.nh .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
.ns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}
@media(min-width:700px){.ns{grid-template-columns:repeat(4,minmax(0,1fr))}}
.ns a,.ns span.st{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:12px 14px;color:var(--ink);text-decoration:none;display:flex;flex-direction:column}
.ns b{font-family:Georgia,serif;font-weight:400;font-size:32px;line-height:1.1;color:#7D5E68}.ns span span{font-size:14px;color:var(--soft);line-height:1.3}
.ns .hot b{color:#A0463A}
.nl{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.nl a{display:inline-block;padding:7px 14px;border-radius:999px;background:var(--card);border:1px solid var(--line);text-decoration:none;color:#6B5658;font-size:15px}
.rq{display:flex;flex-direction:column;gap:10px}
.rq .when{font-family:Georgia,serif;font-size:22px;line-height:1.2;color:var(--ink)}
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
        <section class="nh"><p class="eyebrow">Movecia · 1:1 im Studio</p><h1>Prä- &amp; Postnatal</h1><p>Angebote verschicken, Wunschtermine bestätigen und alle Pakete im Blick.</p></section>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="ns">
            <a href="#anfragen" class="<?= $reqs ? 'hot' : '' ?>"><b><?= count($reqs) ?></b><span>Terminanfragen zu bestätigen</span></a>
            <a href="#termine"><b><?= count($future) ?></b><span>bestätigte Termine</span></a>
            <a href="#kundinnen"><b><?= $paidCount ?></b><span>gebuchte Pakete</span></a>
            <span class="st"><b><?= forma_h(forma_euro($revenue)) ?></b><span>Umsatz brutto</span></span>
        </div>
        <div class="nl">
            <a href="<?= forma_h(mm_site()) ?>/natal/" target="_blank" rel="noopener">Seite Studio</a>
            <a href="<?= forma_h(mm_site()) ?>/natal/online/" target="_blank" rel="noopener">Seite Online</a>
            <a href="#angebot">Angebot verschicken</a>
            <a href="<?= forma_h($self) ?>?view=rechnungen">Rechnungen</a>
            <a href="#einstellungen">Preise &amp; Einstellungen</a>
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

        <h2 id="anfragen">Terminanfragen</h2>
        <?php if (!$reqs): ?><p class="card muted small">Gerade keine offenen Anfragen. Sobald eine Kundin Wunschtermine schickt, bekommst du eine Mail an <?= forma_h(natal_notify_email()) ?> und sie stehen hier.</p><?php endif; ?>
        <div class="list">
        <?php foreach ($reqs as $r): $o = natal_order($pdo, (int)$r['order_id']); $ph = $o ? natal_phase($o, (string)$r['date']) : ''; ?>
            <div class="card rq">
                <div class="booking-head"><span><span class="chip wait">Wunsch</span> <strong><?= forma_h($r['name']) ?></strong> <span class="muted small">· <?= forma_h($r['title']) ?></span></span>
                    <span class="acts small"><?php if ($r['phone'] !== ''): ?><a href="tel:<?= forma_h($r['phone']) ?>">Anrufen</a> · <a href="<?= forma_h(natal_wa((string)$r['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></span></div>
                <p class="when"><?= forma_h(natal_when($r)) ?></p>
                <?php if ($ph || $r['note'] !== ''): ?><p class="muted small"><?= forma_h(trim($ph . ($r['note'] !== '' ? ' · „' . $r['note'] . '“' : ''), ' ·')) ?></p><?php endif; ?>
                <form method="post" class="stack"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_confirm"><input type="hidden" name="sid" value="<?= (int)$r['id'] ?>">
                    <div class="tm"><div class="stack"><label>Datum</label><input type="date" name="date" value="<?= forma_h($r['date']) ?>" required></div><div class="stack"><label>Uhrzeit</label><input type="time" name="time" value="<?= natal_daypart_time((string)$r['daypart']) ?>" step="900" required></div></div>
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
                <div class="ln"><span><strong><?= forma_h(natal_when($s)) ?></strong><br><span class="muted small"><?= forma_h($s['name']) ?> · <?= forma_h($s['title']) ?></span></span>
                    <span class="acts small"><?php if (natal_is_online($s)): ?><a class="btn btn-sm" href="<?= forma_h(natal_room_link('s', (int)$s['id'], true)) ?>" target="_blank" rel="noopener">Zum Videoraum</a><?php endif; ?><?php if ($s['phone'] !== ''): ?><a href="<?= forma_h(natal_wa((string)$s['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
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
                <summary style="cursor:pointer;list-style:none"><div class="booking-head"><span><strong><?= forma_h($o['name']) ?></strong> <span class="muted small">· <?= forma_h($o['title']) ?></span></span>
                    <span class="chip <?= $o['status'] === 'bezahlt' ? 'ok' : 'wait' ?>"><?= $o['status'] === 'bezahlt' ? 'bezahlt ' . forma_h(forma_euro((int)$o['amount_cents'])) : 'Zahlung offen' ?></span></div>
                    <?php if ($o['status'] === 'bezahlt'): ?><p class="small muted" style="margin:6px 0 4px"><?= $done ?> bestätigt · <?= $used - $done ?> angefragt · <?= (int)$o['units'] - $used ?> noch frei von <?= (int)$o['units'] ?></p><div class="bar"><i style="width:<?= (int)round($used / max(1, (int)$o['units']) * 100) ?>%"></i></div><?php endif; ?></summary>
                <div class="stack" style="margin-top:12px">
                    <p class="small"><a href="mailto:<?= forma_h($o['email']) ?>"><?= forma_h($o['email']) ?></a><?php if ($o['phone'] !== ''): ?> · <a href="tel:<?= forma_h($o['phone']) ?>"><?= forma_h($o['phone']) ?></a> · <a href="<?= forma_h(natal_wa((string)$o['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?><br>
                    Geburtstermin: <?= $o['due_date'] !== '' ? forma_h(natal_date_de((string)$o['due_date'])) : '–' ?> · gebucht am <?= forma_h(date('d. m. Y', strtotime((string)($o['paid_at'] ?: $o['created_at'])))) ?></p>
                    <?php if ($o['note'] !== ''): ?><p class="small" style="background:#F2ECE4;border-radius:12px;padding:8px 12px">„<?= forma_h($o['note']) ?>“</p><?php endif; ?>
                    <?php foreach ($ss as $s): ?><div class="ln small"><span><?= forma_h(natal_when($s)) ?></span><span class="chip<?= $s['status'] === 'bestaetigt' ? ' ok' : ($s['status'] === 'angefragt' ? ' wait' : '') ?>"><?= forma_h(natal_status_label((string)$s['status'])) ?></span></div><?php endforeach; ?>
                    <p class="small">Ihre persönliche Seite: <span class="sel"><?= forma_h(natal_link($o)) ?></span> <button type="button" class="link small" data-copy="<?= forma_h(natal_link($o)) ?>">kopieren</button></p>
                    <?php if ($o['status'] === 'offen'): ?>
                        <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_recheck"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Zahlung bei Stripe prüfen</button></form>
                    <?php elseif (natal_left($pdo, $o) > 0): ?>
                        <form method="post" class="stack" style="border-top:1px solid var(--line);padding-top:10px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_add"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
                            <label>Termin selbst eintragen (z. B. am Telefon ausgemacht)</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input type="date" name="date" required><input type="time" name="time" step="900" required></div>
                            <label class="check"><input type="checkbox" name="mail" value="1" checked> Bestätigung mit Kalenderdatei schicken</label>
                            <button class="btn btn-sm" type="submit">Eintragen</button></form>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
        </div>

        <h2 id="angebot">Angebot verschicken</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_offer">
            <p class="small muted">Du bekommst einen persönlichen Link: Die Seite begrüßt sie mit Namen, ihr Name ist beim Buchen schon eingetragen. Die fertige Nachricht kannst du kopieren oder direkt per WhatsApp schicken.</p>
            <div class="stack"><label>Variante</label><div class="wdays"><label class="check"><input type="radio" name="mode" value="studio" checked> 1:1 im Studio</label><label class="check"><input type="radio" name="mode" value="online"> Online per Video</label><label class="check"><input type="radio" name="mode" value="beide"> Beides</label></div></div>
            <div class="row"><div class="stack"><label for="nsal">Anrede</label><select id="nsal" name="salutation"><option>Liebe</option><option>Lieber</option><option>Hallo</option></select></div>
                <div class="stack"><label for="nnm">Vorname</label><input id="nnm" name="name" required maxlength="80" placeholder="z. B. Anna"></div></div>
            <label for="nem">E-Mail (optional, dann kannst du das Angebot auch als schöne Mail mit den Flyern schicken)</label><input id="nem" name="email" type="email" maxlength="160" placeholder="name@beispiel.at">
            <label for="nnt">Persönliche Zeile auf der Seite (optional)</label><textarea id="nnt" name="note" rows="2" maxlength="600" placeholder="z. B. Wie besprochen starten wir mit einer Einheit pro Woche bis zur Geburt."></textarea>
            <button class="btn" type="submit">Angebot erstellen</button>
        </form>
        <p class="small muted" style="margin:10px 0">Allgemeine Links ohne Namen (nicht verlinkt, nur wer den Link hat, sieht die Seite):<br>Studio: <span class="sel"><?= forma_h(mm_site()) ?>/natal/</span> <button type="button" class="link small" data-copy="<?= forma_h(mm_site()) ?>/natal/">kopieren</button><br>Online: <span class="sel"><?= forma_h(mm_site()) ?>/natal/online/</span> <button type="button" class="link small" data-copy="<?= forma_h(mm_site()) ?>/natal/online/">kopieren</button></p>
        <div class="list">
        <?php $seenGrp = []; foreach ($offers as $a):
            if ((string)($a['grp'] ?? '') !== '') { if (isset($seenGrp[$a['grp']])) continue; $seenGrp[$a['grp']] = 1; }
            $grpRows = natal_offer_group($pdo, $a); $a = $grpRows[0];
            $a['booked'] = 0; foreach ($grpRows as $gr) { $bq = $pdo->prepare("SELECT COUNT(*) FROM mm_natal_orders WHERE offer_id = ? AND status = 'bezahlt'"); $bq->execute([$gr['id']]); $a['booked'] += (int)$bq->fetchColumn(); if ($gr['opened_at'] && (!$a['opened_at'] || $gr['opened_at'] > $a['opened_at'])) $a['opened_at'] = $gr['opened_at']; }
            $isNew = in_array($newId, array_map(fn($r) => (int)$r['id'], $grpRows), true);
            $msg = natal_offer_message($a, $grpRows); ?>
            <div class="card stack<?= $isNew ? ' is-new' : '' ?>">
                <div class="booking-head"><strong><?= forma_h($a['salutation'] . ' ' . $a['name']) ?> <span class="chip"><?= count($grpRows) > 1 ? 'Studio + online' : (($a['mode'] ?? 'studio') === 'online' ? 'online' : 'Studio') ?></span></strong><span class="muted small">erstellt <?= forma_h(date('d. m.', strtotime((string)$a['created_at']))) ?> · <?= (int)$a['booked'] ? '<span class="chip ok">gebucht</span>' : ($a['opened_at'] ? 'angesehen am ' . forma_h(date('d. m., H:i', strtotime((string)$a['opened_at']))) : 'noch nicht geöffnet') ?></span></div>
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
            <label for="nno">Benachrichtigungen an</label><input id="nno" name="notify" type="email" value="<?= forma_h((string)($cfg['notify'] ?? '')) ?>" placeholder="<?= forma_h(MM_FROM_EMAIL) ?>">
            <button class="btn" type="submit">Speichern</button>
        </form>
        <form method="post" style="margin-top:10px"><?= $csrf ?><input type="hidden" name="action" value="mm_natal_sample"><button class="link" type="submit">Muster-Rechnung an mich schicken</button></form>
<script>
document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
  const t = b.dataset.copy, old = b.textContent;
  try { await navigator.clipboard.writeText(t); } catch (e) { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); document.execCommand('copy'); a.remove(); }
  b.textContent = 'kopiert ✓'; setTimeout(() => b.textContent = old, 1800);
}));
</script>
    <?php });
    exit;
}

<?php
// Verwaltung → Movecia → „Personal Training“ (?view=training): Anfragen von Hotelgästen prüfen (passt / anderer Termin),
// an den Gast schicken (er bezahlt dann online), bezahlte Termine verschieben/absagen, Kalender, Preise.
// Wird von verwaltung/movecia.php eingebunden (angemeldet, CSRF geprüft). Vivien, 27.10.2026.
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/ptb-lib.php';
ptb_schema($pdo);
$tBack = $self . '?view=training';

function ptb_sess_row(PDO $pdo, int $sid): ?array
{
    $st = $pdo->prepare('SELECT * FROM mm_ptb_sessions WHERE id = ?');
    $st->execute([$sid]);
    return $st->fetch() ?: null;
}

if (str_starts_with((string)$action, 'mm_ptb_')) {
    $sid = (int)($_POST['sid'] ?? 0);
    $oid = (int)($_POST['oid'] ?? 0);
    $s = $sid ? ptb_sess_row($pdo, $sid) : null;
    $o = $oid ? ptb_order($pdo, $oid) : ($s ? ptb_order($pdo, (int)$s['order_id']) : null);
    $anchor = $o ? '#t' . (int)$o['id'] : '';
    $openReq = $o && in_array($o['status'], ['anfrage', 'angebot'], true);

    if ($action === 'mm_ptb_ok' && $s && $openReq) {
        $pdo->prepare("UPDATE mm_ptb_sessions SET status = 'passt', date = wish_date, start = wish_time WHERE id = ?")->execute([$sid]);
        go($tBack . $anchor, 'Passt ✓');
    }
    if ($action === 'mm_ptb_alt' && $s && $openReq) {
        $d = (string)($_POST['date'] ?? ''); $t = (string)($_POST['time'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !preg_match('/^\d{1,2}:\d{2}$/', $t)) go($tBack . $anchor, 'Bitte Datum und Uhrzeit angeben.');
        $t = str_pad($t, 5, '0', STR_PAD_LEFT);
        $same = $d === $s['wish_date'] && $t === substr((string)$s['wish_time'], 0, 5);
        $pdo->prepare('UPDATE mm_ptb_sessions SET status = ?, date = ?, start = ? WHERE id = ?')->execute([$same ? 'passt' : 'vorschlag', $d, $t, $sid]);
        go($tBack . $anchor, $same ? 'Passt ✓' : 'Neuer Vorschlag eingetragen: ' . ptb_when($d, $t) . '.');
    }
    if ($action === 'mm_ptb_drop' && $s && $openReq) {
        $pdo->prepare("UPDATE mm_ptb_sessions SET status = 'gestrichen' WHERE id = ?")->execute([$sid]);
        go($tBack . $anchor, 'Diese Einheit ist gestrichen. Der Preis wird beim Schicken neu berechnet.');
    }
    if ($action === 'mm_ptb_undo' && $s && $openReq) {
        $pdo->prepare("UPDATE mm_ptb_sessions SET status = 'offen', date = wish_date, start = wish_time WHERE id = ?")->execute([$sid]);
        go($tBack . $anchor, 'Zurückgesetzt.');
    }
    if ($action === 'mm_ptb_send' && $o) {
        $r = ptb_offer_send($pdo, $o, trim((string)($_POST['note'] ?? '')));
        go($tBack . $anchor, $r['ok'] ? ($r['mailed'] ? 'Geschickt! ' . $o['name'] . ' bekommt jetzt die Mail mit den Terminen und dem Bezahl-Knopf. Sobald bezahlt ist, bekommst du eine Mail.' : 'Gespeichert, aber die Mail ging nicht raus. Ist das Postfach-Passwort unter Movecia → Einstellungen hinterlegt?') : $r['error']);
    }
    if ($action === 'mm_ptb_decline' && $o) {
        ptb_decline($pdo, $o, trim((string)($_POST['note'] ?? '')), !empty($_POST['mail']));
        go($tBack, 'Die Anfrage ist abgesagt' . (!empty($_POST['mail']) ? ', ' . $o['name'] . ' hat eine freundliche Mail bekommen.' : '.'));
    }
    if ($action === 'mm_ptb_recheck' && $o) {
        $st = ptb_check_paid($pdo, $o);
        go($tBack . $anchor, $st === 'bezahlt' ? 'Die Zahlung ist da. Bestätigung und Rechnung sind verschickt.' : 'Noch keine Zahlung bei Stripe.');
    }
    if ($action === 'mm_ptb_reopen' && $o && $o['status'] === 'angebot') {
        $pdo->prepare("UPDATE mm_ptb_orders SET status = 'anfrage', stripe_session = '' WHERE id = ?")->execute([$o['id']]);
        go($tBack . $anchor, 'Die Anfrage ist wieder offen. Ändere die Termine und schick sie neu.');
    }
    if ($action === 'mm_ptb_move' && $s && $o && $o['status'] === 'bezahlt') {
        $r = ptb_move($pdo, $sid, (string)($_POST['date'] ?? ''), (string)($_POST['time'] ?? ''), !empty($_POST['mail']));
        go($tBack . $anchor, $r ? 'Verschoben auf ' . ptb_when((string)$r['date'], (string)$r['start']) . (!empty($_POST['mail']) ? '. Die Mail mit dem neuen Termin ist unterwegs.' : '.') : 'Bitte Datum und Uhrzeit angeben.');
    }
    if ($action === 'mm_ptb_cancel' && $s && $o && $o['status'] === 'bezahlt' && $s['status'] === 'bestaetigt') {
        $refund = !empty($_POST['refund']);
        $msg = 'Die Einheit ist abgesagt.';
        if ($refund) {
            try {
                $cs = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode((string)$o['stripe_session']));
                $pi = (string)($cs['payment_intent'] ?? '');
                if ($pi === '') throw new RuntimeException('Keine Zahlung gefunden.');
                forma_stripe('POST', '/v1/refunds', ['payment_intent' => $pi, 'amount' => (int)$o['unit_cents'], 'metadata' => ['ptb_session_id' => (string)$sid]]);
                if (function_exists('forma_invoice_storno')) forma_invoice_storno($pdo, 'ptb', (int)$o['id'], (int)$o['unit_cents']);
                $msg .= ' ' . forma_euro((int)$o['unit_cents']) . ' werden über Stripe zurückerstattet, die Stornorechnung ist verschickt.';
            } catch (Throwable $e) {
                error_log('PTB refund: ' . $e->getMessage());
                go($tBack . $anchor, 'Die Rückerstattung über Stripe hat nicht geklappt (' . $e->getMessage() . '). Die Einheit ist noch nicht abgesagt.');
            }
        }
        $pdo->prepare("UPDATE mm_ptb_sessions SET status = 'abgesagt' WHERE id = ?")->execute([$sid]);
        if (!empty($_POST['mail'])) {
            $en = ptb_en($o); $first = forma_first_name((string)$o['name']);
            $en ? ptb_mail_guest($o, 'Session cancelled: ' . ptb_when((string)$s['date'], (string)$s['start'], true), 'Session cancelled', ["Hi $first,", 'as agreed, your session on ' . ptb_when((string)$s['date'], (string)$s['start'], true) . ' is cancelled.' . ($refund ? ' The ' . forma_euro((int)$o['unit_cents']) . ' for it will be refunded to your card within a few days.' : ''), 'Your other sessions stay as booked.'], [ptb_link($o), 'My sessions'])
                : ptb_mail_guest($o, 'Termin abgesagt: ' . ptb_when((string)$s['date'], (string)$s['start']), 'Termin abgesagt', ["Hallo $first,", 'wie besprochen ist deine Einheit am ' . ptb_when((string)$s['date'], (string)$s['start']) . ' abgesagt.' . ($refund ? ' Die ' . forma_euro((int)$o['unit_cents']) . ' dafür bekommst du in den nächsten Tagen auf deine Karte zurück.' : ''), 'Deine anderen Termine bleiben wie gebucht.'], [ptb_link($o), 'Meine Termine']);
            $msg .= ' Der Gast hat eine Mail bekommen.';
        }
        go($tBack . $anchor, $msg);
    }
    if ($action === 'mm_ptb_settings') {
        $c = mm_cfg('ptb') ?? [];
        $u = mm_euro_in((string)($_POST['unit'] ?? '')); if ($u > 0) $c['unit'] = $u;
        $u2 = mm_euro_in((string)($_POST['unit2'] ?? '')); if ($u2 > 0) $c['unit2'] = $u2;
        $c['disc_unit'] = max(0, mm_euro_in((string)($_POST['disc_unit'] ?? '')));
        $c['disc_from'] = max(2, min(PTB_MAX_UNITS, (int)($_POST['disc_from'] ?? 4)));
        $c['weekdays'] = array_values(array_map('intval', array_intersect(array_keys(PTB_WD_DE), array_map('intval', (array)($_POST['weekdays'] ?? []))))) ?: [1, 2, 3, 4, 5, 6];
        foreach (['from', 'to'] as $k) if (preg_match('/^\d{1,2}:\d{2}$/', (string)($_POST[$k] ?? ''))) $c[$k] = str_pad((string)$_POST[$k], 5, '0', STR_PAD_LEFT);
        mm_save_cfg('ptb', $c);
        go($tBack . '#einstellungen', 'Gespeichert. Die Seite und der Flyer-Link zeigen ab jetzt diese Preise und Zeiten.');
    }
    if ($action === 'mm_ptb_block' || $action === 'mm_ptb_unblock') {
        $c = mm_cfg('ptb') ?? [];
        $d = (string)($_POST['date'] ?? '');
        $bl = array_values(array_filter((array)($c['blocked'] ?? []), fn($x) => $x !== $d && $x >= date('Y-m-d')));
        if ($action === 'mm_ptb_block' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $bl[] = $d;
        sort($bl);
        $c['blocked'] = $bl;
        mm_save_cfg('ptb', $c);
        go($tBack . '#einstellungen', $action === 'mm_ptb_block' ? 'Der Tag ist gesperrt, Gäste können ihn nicht mehr wählen.' : 'Der Tag ist wieder frei.');
    }
    if ($action === 'mm_ptb_test') {
        $o2 = ptb_test_create($pdo, trim((string)($_POST['email'] ?? '')), (string)($_POST['lang'] ?? 'de'), (int)($_POST['n'] ?? 2), (int)($_POST['persons'] ?? 1));
        go($tBack . ($o2 ? '#t' . (int)$o2['id'] : '#test'), $o2 ? 'Die Test-Anfrage ist angelegt (E-Mail gilt als bestätigt). Probier jetzt „passt“ / anderer Termin und „An Gast schicken“ – die Mails gehen an ' . $o2['email'] . '.' : 'Bitte eine gültige E-Mail-Adresse angeben.');
    }
    if ($action === 'mm_ptb_delete' && $o && ((int)$o['is_test'] === 1 || in_array($o['status'], ['abgesagt', 'abgelaufen', 'email'], true))) {
        $pdo->prepare('DELETE FROM mm_ptb_sessions WHERE order_id = ?')->execute([$o['id']]);
        $pdo->prepare('DELETE FROM mm_ptb_orders WHERE id = ?')->execute([$o['id']]);
        go($tBack, 'Gelöscht.');
    }
    go($tBack, 'Das ging gerade nicht. Bitte lade die Seite neu.');
}

if ($view === 'training') {
    ptb_tick($pdo);
    $reqs = $pdo->query("SELECT * FROM mm_ptb_orders WHERE status = 'anfrage' ORDER BY id")->fetchAll();
    $waits = $pdo->query("SELECT * FROM mm_ptb_orders WHERE status = 'angebot' ORDER BY offered_at")->fetchAll();
    $unconf = $pdo->query("SELECT * FROM mm_ptb_orders WHERE status = 'email' ORDER BY id DESC LIMIT 20")->fetchAll();
    $up = $pdo->prepare("SELECT s.*, o.name, o.phone, o.email, o.lang, o.hotel, o.id AS oid FROM mm_ptb_sessions s JOIN mm_ptb_orders o ON o.id = s.order_id WHERE o.status = 'bezahlt' AND s.status = 'bestaetigt' AND s.date >= ? ORDER BY s.date, s.start");
    $up->execute([date('Y-m-d')]);
    $upcoming = $up->fetchAll();
    $paid = $pdo->query("SELECT * FROM mm_ptb_orders WHERE status = 'bezahlt' ORDER BY paid_at DESC LIMIT 40")->fetchAll();
    $closed = $pdo->query("SELECT * FROM mm_ptb_orders WHERE status IN ('abgesagt','abgelaufen') ORDER BY id DESC LIMIT 15")->fetchAll();
    $revenue = (int)$pdo->query("SELECT COALESCE(SUM(amount_cents),0) FROM mm_ptb_orders WHERE status = 'bezahlt' AND is_test = 0")->fetchColumn();
    $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? (string)$_GET['m'] : date('Y-m');
    $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['d'] ?? '')) ? (string)$_GET['d'] : '';
    render_page('Personal Training', function () use ($self, $flash, $pdo, $reqs, $waits, $unconf, $upcoming, $paid, $closed, $revenue, $month, $day) {
        $cfg = ptb_cfg();
        $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '">';
        $wa = fn(string $p) => 'https://wa.me/' . ltrim(preg_replace('/^0(?!0)/', '43', preg_replace('/^00/', '', preg_replace('/[^\d]/', '', $p))), '+');
        $eu = fn(int $c) => rtrim(rtrim(number_format($c / 100, 2, ',', ''), '0'), ',');
        $chip = fn(string $k) => ['pt' => 'background:#7D5E68;color:#FBF5F7', 'pt-wait' => 'background:#E8D6CA;color:#4A3A41', 'pt-wish' => 'background:transparent;border:1.5px dashed #7D5E68;color:#6B5658', 'natal' => 'background:#B58C99;color:#fff', 'natal-wish' => 'background:transparent;border:1.5px dashed #B58C99;color:#6B5658', 'forma' => 'background:#8C2C1D;color:#fff', 'forma-leer' => 'background:#EEE2D4;color:#7D7076'][$k] ?? ''; ?>
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
.ns b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:32px;line-height:1.1;color:#7D5E68}.ns span span{font-size:14px;color:var(--soft);line-height:1.3}
.ns .hot b{color:#A0463A}
.nl{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.nl a{display:inline-block;padding:7px 14px;border-radius:999px;background:var(--card);border:1px solid var(--line);text-decoration:none;color:#6B5658;font-size:15px}
.btn{background:#7D5E68;border-color:#7D5E68;color:#FBF5F7}.btn-ghost{background:transparent;color:#7D5E68}
.rq{display:flex;flex-direction:column;gap:12px}
.rq .who{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:baseline}
.rq .who b{font-size:20px}
.wish{border:1px solid var(--line);border-radius:18px;padding:12px 14px;display:flex;flex-direction:column;gap:8px;background:#fff}
.wish.ok{border-color:#7D5E68;background:#FBF5F7}.wish.alt{border-color:#E8D6CA;background:#FDF6F1}.wish.drop{opacity:.55}
.wish .when{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:24px;line-height:1.15;color:var(--ink)}
.wish .day{font-size:14px;color:var(--soft);display:flex;flex-direction:column;gap:3px}
.it{display:inline-block;border-radius:999px;padding:1px 10px;font-size:13px;margin-right:6px}
.acts{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.acts form{margin:0}
.big{font-size:17px;padding:.7em 1.3em}
.alt-f{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.alt-f input{width:auto}
.chip{display:inline-block;border-radius:999px;padding:1px 10px;font-size:13px;background:#EADFDE;color:#6B5658}
.ln{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;border-top:1px solid var(--line);padding:9px 0}
details.more summary{cursor:pointer;color:#7D5E68;font-size:15px}
.mc{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.mc .dw{font-size:12px;color:var(--soft);text-align:center}
.mc a{min-height:62px;border-radius:12px;background:var(--card);border:1px solid var(--line);padding:4px 5px;text-decoration:none;color:var(--ink);font-size:13px;display:flex;flex-direction:column;gap:2px;overflow:hidden}
.mc a.today{border-color:#7D5E68}.mc a.sel{outline:2px solid #7D5E68}.mc a.off{opacity:.45}
.mc a i{display:block;height:6px;border-radius:4px}
.mc a small{font-size:11px;line-height:1.2;color:#4A3A41}
@media(max-width:600px){.mc a small{display:none}.mc a{min-height:46px}}
.legend{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}
</style>
        <?php mm_subnav('training'); ?>
        <section class="nh"><p class="eyebrow">Movecia · allein oder zu zweit im Studio in Mathon</p><h1>Personal Training</h1><p>Anfragen von Hotelgästen bestätigen, Ersatztermine vorschlagen, alles im Kalender.</p></section>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="ns">
            <a href="#anfragen" class="<?= $reqs ? 'hot' : '' ?>"><b><?= count($reqs) ?></b><span>neue Anfragen</span></a>
            <a href="#zahlung"><b><?= count($waits) ?></b><span>warten auf Zahlung</span></a>
            <a href="#termine"><b><?= count($upcoming) ?></b><span>fixe Termine</span></a>
            <span class="st"><b><?= forma_h(forma_euro($revenue)) ?></b><span>Umsatz brutto</span></span>
        </div>
        <div class="nl">
            <a href="<?= forma_h($self) ?>?view=winter_mm" style="background:#7D5E68;color:#FBF5F7;border-color:#7D5E68">❄️ Flyer, Link &amp; QR-Code</a>
            <a href="<?= forma_h(mm_site()) ?>/personal-training/" target="_blank" rel="noopener">Seite ansehen (wie der QR-Code)</a>
            <a href="#kalender">Kalender</a>
            <a href="#einstellungen">Preise &amp; Zeiten</a>
            <a href="#test">Als Gast testen</a>
            <a href="<?= forma_h($self) ?>?view=rechnungen">Rechnungen</a>
        </div>

        <h2 id="anfragen" style="margin-top:22px">Neue Anfragen</h2>
        <?php if (!$reqs): ?><p class="card muted small">Gerade keine offenen Anfragen. Sobald ein Gast seine E-Mail-Adresse bestätigt hat, bekommst du eine Mail und die Anfrage steht hier.</p><?php endif; ?>
        <div class="list">
        <?php foreach ($reqs as $o): $ss = ptb_sessions($pdo, (int)$o['id']); $act = array_filter($ss, fn($s) => $s['status'] !== 'gestrichen'); $open = array_filter($ss, fn($s) => $s['status'] === 'offen'); $n = count($act); ?>
            <div class="card rq" id="t<?= (int)$o['id'] ?>">
                <div class="who"><span><b><?= forma_h($o['name']) ?></b><?php if ((int)$o['is_test']): ?> <span class="chip">TEST</span><?php endif; ?> <span class="chip"><?= ptb_en($o) ? 'Englisch' : 'Deutsch' ?></span><?php if (ptb_persons($o) === 2): ?> <span class="chip" style="background:#E8D6CA;color:#4A3A41">zu zweit</span><?php endif; ?><br>
                    <span class="small muted"><?= $o['hotel'] !== '' ? forma_h($o['hotel']) . ' · ' : '' ?><a href="mailto:<?= forma_h($o['email']) ?>"><?= forma_h($o['email']) ?></a><?php if ($o['phone'] !== ''): ?> · <a href="tel:<?= forma_h($o['phone']) ?>"><?= forma_h($o['phone']) ?></a> · <a href="<?= forma_h($wa((string)$o['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></span></span>
                    <span class="small muted">angefragt am <?= forma_h(date('d. m., H:i', strtotime((string)($o['email_ok_at'] ?: $o['created_at'])))) ?></span></div>
                <?php if ($o['note'] !== ''): ?><p class="small" style="background:#F2ECE4;border-radius:12px;padding:8px 12px;margin:0">„<?= forma_h($o['note']) ?>“</p><?php endif; ?>
                <?php foreach ($ss as $i => $s): $items = ptb_day_items($pdo, (string)$s['wish_date'], (int)$o['id']); $cls = ['passt' => 'ok', 'vorschlag' => 'alt', 'gestrichen' => 'drop'][$s['status']] ?? ''; ?>
                    <div class="wish <?= $cls ?>">
                        <div class="acts" style="justify-content:space-between"><span><span class="small muted">Einheit <?= $i + 1 ?> · Wunsch</span><br><span class="when"><?= forma_h(ptb_when((string)$s['wish_date'], (string)$s['wish_time'])) ?></span></span>
                            <span><?php if ($s['status'] === 'passt'): ?><span class="chip" style="background:#7D5E68;color:#FBF5F7">✓ passt</span><?php elseif ($s['status'] === 'vorschlag'): ?><span class="chip" style="background:#E8D6CA;color:#4A3A41">Vorschlag: <?= forma_h(ptb_when((string)$s['date'], (string)$s['start'])) ?></span><?php elseif ($s['status'] === 'gestrichen'): ?><span class="chip">gestrichen</span><?php endif; ?></span></div>
                        <div class="day"><?php if (!$items): ?><span>An diesem Tag steht sonst nichts im Kalender.</span><?php else: ?><span>An diesem Tag:</span><?php foreach ($items as [$a, $b, $txt, $k]): ?><span><span class="it" style="<?= $chip($k) ?>"><?= $a !== '' ? forma_h($a . '–' . $b) : '–' ?></span><?= forma_h($txt) ?></span><?php endforeach; ?><?php endif; ?></div>
                        <?php if ($s['status'] === 'gestrichen'): ?>
                            <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_undo"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="link small" type="submit">doch wieder rein</button></form>
                        <?php else: ?>
                        <div class="acts">
                            <?php if ($s['status'] !== 'passt'): ?><form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_ok"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="btn big" type="submit">✓ Passt</button></form><?php endif; ?>
                            <details class="more"<?= $s['status'] === 'vorschlag' ? ' open' : '' ?>><summary>Andere Zeit vorschlagen</summary>
                                <form method="post" class="alt-f" style="margin-top:6px"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_alt"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>">
                                    <input type="date" name="date" value="<?= forma_h((string)$s['date']) ?>" min="<?= date('Y-m-d') ?>" required><input type="time" name="time" value="<?= forma_h(substr((string)$s['start'], 0, 5)) ?>" step="900" required><button class="btn btn-sm" type="submit">Vorschlagen</button></form></details>
                            <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_drop"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="link small" type="submit"<?= $n <= 1 ? ' disabled title="Mindestens eine Einheit muss bleiben"' : '' ?>>diese Einheit streichen</button></form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <form method="post" class="stack"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_send"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
                    <label>Persönliche Zeile für die Mail (optional)</label><textarea name="note" rows="2" placeholder="<?= forma_h(ptb_en($o) ? 'e.g. I look forward to meeting you! Parking is right in front of the house.' : 'z. B. Ich freu mich auf dich! Parken kannst du direkt vor dem Haus.') ?>"></textarea>
                    <button class="btn big" type="submit"<?= $open ? ' disabled' : '' ?>><?= $open ? 'Erst bei jeder Einheit „passt“ oder eine andere Zeit wählen' : 'An Gast schicken · ' . $n . ' × ' . forma_h(forma_euro(ptb_unit_cents($n, ptb_persons($o)))) . ' = ' . forma_h(forma_euro(ptb_unit_cents($n, ptb_persons($o)) * $n)) ?></button>
                    <span class="small muted">Der Gast bekommt eine Mail mit allen Terminen (Vorschläge sind markiert) und dem Bezahl-Knopf. Erst nach der Zahlung sind die Termine fix.</span></form>
                <details class="more"><summary>Geht gar nicht? Anfrage absagen</summary>
                    <form method="post" class="stack" style="margin-top:8px"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_decline"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
                        <textarea name="note" rows="2" placeholder="Kurze Nachricht (optional)"></textarea>
                        <label class="check"><input type="checkbox" name="mail" value="1" checked> freundliche Mail an den Gast</label><button class="btn btn-ghost" type="submit">Anfrage absagen</button></form></details>
            </div>
        <?php endforeach; ?>
        </div>

        <h2 id="zahlung">Wartet auf Zahlung</h2>
        <?php if (!$waits): ?><p class="card muted small">Nichts offen.</p><?php else: ?><div class="card">
            <?php foreach ($waits as $o): $ss = ptb_sessions($pdo, (int)$o['id'], true); ?>
                <div class="ln" id="t<?= (int)$o['id'] ?>"><span><strong><?= forma_h($o['name']) ?></strong><?php if ((int)$o['is_test']): ?> <span class="chip">TEST</span><?php endif; ?> · <?= forma_h(forma_euro((int)$o['amount_cents'])) ?><br>
                    <span class="small muted"><?= forma_h(implode(' · ', array_map(fn($s) => ptb_when((string)$s['date'], (string)$s['start']), $ss))) ?></span><br>
                    <span class="small muted">geschickt am <?= forma_h(date('d. m., H:i', strtotime((string)$o['offered_at']))) ?><?php if ($o['phone'] !== ''): ?> · <a href="<?= forma_h($wa((string)$o['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?> · <button type="button" class="link small" data-copy="<?= forma_h(ptb_link($o)) ?>">Bezahl-Link kopieren</button></span></span>
                    <span class="acts small">
                        <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_recheck"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Zahlung prüfen</button></form>
                        <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_send"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="link small" type="submit">Mail nochmal schicken</button></form>
                        <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_reopen"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="link small" type="submit">Termine ändern</button></form>
                        <details class="more"><summary>absagen</summary><form method="post" class="stack" style="margin-top:6px"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_decline"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><textarea name="note" rows="2" placeholder="Nachricht (optional)"></textarea><label class="check"><input type="checkbox" name="mail" value="1" checked> Mail an den Gast</label><button class="btn btn-ghost btn-sm" type="submit">Absagen</button></form></details>
                    </span></div>
            <?php endforeach; ?></div><?php endif; ?>

        <h2 id="kalender">Kalender</h2>
        <?php
        $first = strtotime($month . '-01');
        $prev = date('Y-m', strtotime('-1 month', $first)); $next = date('Y-m', strtotime('+1 month', $first));
        $mFrom = date('Y-m-01', $first); $mTo = date('Y-m-t', $first);
        $marks = [];
        $q = $pdo->prepare("SELECT s.date, s.start, o.name, o.status AS ost, s.status FROM mm_ptb_sessions s JOIN mm_ptb_orders o ON o.id = s.order_id WHERE s.date BETWEEN ? AND ? AND ((o.status = 'bezahlt' AND s.status IN ('bestaetigt','erledigt')) OR (o.status = 'angebot' AND s.status IN ('passt','vorschlag')) OR (o.status = 'anfrage' AND s.status <> 'gestrichen')) ORDER BY s.start");
        $q->execute([$mFrom, $mTo]);
        foreach ($q->fetchAll() as $r) $marks[$r['date']][] = [$r['ost'] === 'bezahlt' ? 'pt' : ($r['ost'] === 'angebot' ? 'pt-wait' : 'pt-wish'), substr((string)$r['start'], 0, 5) . ' ' . forma_first_name((string)$r['name'])];
        try { $q = $pdo->prepare("SELECT s.date, s.start, o.name FROM mm_natal_sessions s JOIN mm_natal_orders o ON o.id = s.order_id WHERE s.date BETWEEN ? AND ? AND s.status IN ('bestaetigt','erledigt')"); $q->execute([$mFrom, $mTo]); foreach ($q->fetchAll() as $r) $marks[$r['date']][] = ['natal', substr((string)$r['start'], 0, 5) . ' ' . forma_first_name((string)$r['name'])]; } catch (Throwable $e) {}
        foreach (ptb_forma_courses($pdo, $mFrom, $mTo, true) as $c) $marks[$c['date']][] = ['forma', sprintf('%d', intdiv($c['a'], 60)) . ' FORMA'];
        ?>
        <div class="card">
            <div class="acts" style="justify-content:space-between"><a class="btn btn-ghost btn-sm" href="<?= forma_h($self) ?>?view=training&amp;m=<?= $prev ?>#kalender">‹</a><b style="font-size:20px"><?= forma_h(['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'][(int)date('n', $first)] . ' ' . date('Y', $first)) ?></b><a class="btn btn-ghost btn-sm" href="<?= forma_h($self) ?>?view=training&amp;m=<?= $next ?>#kalender">›</a></div>
            <div class="legend small"><span><span class="it" style="<?= $chip('pt') ?>">PT</span>Personal Training fix</span><span><span class="it" style="<?= $chip('pt-wait') ?>">PT</span>wartet auf Zahlung</span><span><span class="it" style="<?= $chip('pt-wish') ?>">PT</span>Anfrage</span><span><span class="it" style="<?= $chip('natal') ?>">Natal</span>Prä-/Postnatal</span><span><span class="it" style="<?= $chip('forma') ?>">FORMA</span>Kurs mit Anmeldungen</span></div>
            <div class="mc"><?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $w): ?><span class="dw"><?= $w ?></span><?php endforeach; ?>
                <?php for ($i = 1; $i < (int)date('N', $first); $i++): ?><span></span><?php endfor; ?>
                <?php for ($d = 1; $d <= (int)date('t', $first); $d++): $ds = date('Y-m-', $first) . sprintf('%02d', $d); $off = !in_array((int)date('N', strtotime($ds)), $cfg['weekdays'], true) || in_array($ds, $cfg['blocked'], true); ?>
                    <a href="<?= forma_h($self) ?>?view=training&amp;m=<?= $month ?>&amp;d=<?= $ds ?>#tag" class="<?= $ds === date('Y-m-d') ? 'today ' : '' ?><?= $ds === $day ? 'sel ' : '' ?><?= $off ? 'off' : '' ?>"><span><?= $d ?></span>
                        <?php foreach (array_slice($marks[$ds] ?? [], 0, 3) as [$k, $txt]): ?><i style="<?= $chip($k) ?>"></i><small><?= forma_h($txt) ?></small><?php endforeach; ?>
                        <?php if (count($marks[$ds] ?? []) > 3): ?><small>+<?= count($marks[$ds]) - 3 ?></small><?php endif; ?></a>
                <?php endfor; ?></div>
            <?php if ($day): $items = ptb_day_items($pdo, $day); ?>
                <div id="tag" style="margin-top:14px"><b style="font-size:18px"><?= forma_h(ptb_date($day, false, true)) ?></b><?= in_array($day, $cfg['blocked'], true) ? ' <span class="chip">für Gäste gesperrt</span>' : '' ?>
                    <?php if (!$items): ?><p class="small muted">Nichts eingetragen.</p><?php endif; ?>
                    <?php foreach ($items as [$a, $b, $txt, $k]): ?><div class="ln small"><span><span class="it" style="<?= $chip($k) ?>"><?= $a !== '' ? forma_h($a . '–' . $b) : '–' ?></span><?= forma_h($txt) ?></span></div><?php endforeach; ?>
                    <form method="post" style="margin-top:8px"><?= $csrf ?><input type="hidden" name="action" value="<?= in_array($day, $cfg['blocked'], true) ? 'mm_ptb_unblock' : 'mm_ptb_block' ?>"><input type="hidden" name="date" value="<?= forma_h($day) ?>"><button class="link small" type="submit"><?= in_array($day, $cfg['blocked'], true) ? 'Tag für Gäste wieder freigeben' : 'Diesen Tag für Gäste sperren' ?></button></form>
                </div>
            <?php else: ?><p class="small muted" style="margin-top:8px">Tipp auf einen Tag, dann siehst du alles, was an diesem Tag ansteht.</p><?php endif; ?>
        </div>

        <h2 id="termine">Fixe Termine</h2>
        <?php if (!$upcoming): ?><p class="card muted small">Noch keine bezahlten Termine.</p><?php else: ?><div class="card">
            <?php foreach ($upcoming as $s): ?>
                <div class="ln" id="t<?= (int)$s['oid'] ?>"><span><strong><?= forma_h(ptb_when((string)$s['date'], (string)$s['start'])) ?></strong><br><span class="small muted"><?= forma_h($s['name']) ?><?= $s['hotel'] !== '' ? ' · ' . forma_h($s['hotel']) : '' ?><?php if ($s['phone'] !== ''): ?> · <a href="<?= forma_h($wa((string)$s['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></span></span>
                    <span class="acts small"><details class="more"><summary>verschieben / absagen</summary>
                        <form method="post" class="stack" style="margin-top:6px"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_move"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>">
                            <div class="alt-f"><input type="date" name="date" value="<?= forma_h((string)$s['date']) ?>"><input type="time" name="time" value="<?= forma_h(substr((string)$s['start'], 0, 5)) ?>" step="900"></div>
                            <label class="check"><input type="checkbox" name="mail" value="1" checked> Mail mit neuem Termin</label><button class="btn btn-sm" type="submit">Verschieben</button></form>
                        <form method="post" class="stack" style="margin-top:10px"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_cancel"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>">
                            <label class="check"><input type="checkbox" name="refund" value="1" checked> Geld für diese Einheit über Stripe zurückerstatten (mit Stornorechnung)</label>
                            <label class="check"><input type="checkbox" name="mail" value="1" checked> Mail an den Gast</label><button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Einheit wirklich absagen?')">Einheit absagen</button></form>
                    </details></span></div>
            <?php endforeach; ?></div><?php endif; ?>

        <details class="card" style="margin-top:14px"><summary style="cursor:pointer"><b>Alle bezahlten Buchungen</b> <span class="small muted">(<?= count($paid) ?>)</span></summary>
            <?php foreach ($paid as $o): $ss = ptb_sessions($pdo, (int)$o['id'], true); ?>
                <div class="ln small"><span><strong><?= forma_h($o['name']) ?></strong><?php if ((int)$o['is_test']): ?> <span class="chip">TEST</span><?php endif; ?> · <?= forma_h(forma_euro((int)$o['amount_cents'])) ?> · bezahlt <?= forma_h(date('d. m.', strtotime((string)$o['paid_at']))) ?><?= $o['review_sent_at'] ? ' · Bewertungs-Mail ✓' : '' ?><br><span class="muted"><?= forma_h(implode(' · ', array_map(fn($s) => ptb_when((string)$s['date'], (string)$s['start']) . ($s['status'] === 'abgesagt' ? ' (abgesagt)' : ($s['status'] === 'erledigt' ? ' ✓' : '')), $ss))) ?></span></span>
                    <?php if ((int)$o['is_test']): ?><form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_delete"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="link small" type="submit">Test löschen</button></form><?php endif; ?></div>
            <?php endforeach; ?></details>
        <?php if ($unconf || $closed): ?><details class="card" style="margin-top:10px"><summary style="cursor:pointer"><b>E-Mail nicht bestätigt, abgesagt, abgelaufen</b> <span class="small muted">(<?= count($unconf) + count($closed) ?>)</span></summary>
            <p class="small muted">Anfragen, deren E-Mail-Adresse noch nicht bestätigt ist, musst du nicht beachten. Nach 3 Tagen schließen sie sich von selbst.</p>
            <?php foreach (array_merge($unconf, $closed) as $o): ?><div class="ln small"><span><?= forma_h($o['name']) ?> · <?= forma_h($o['email']) ?> · <?= forma_h(ptb_status_label((string)$o['status'])) ?> · <?= forma_h(date('d. m.', strtotime((string)$o['created_at']))) ?></span>
                <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_delete"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="link small" type="submit">löschen</button></form></div><?php endforeach; ?>
        </details><?php endif; ?>

        <h2 id="einstellungen">Preise &amp; Zeiten</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_settings">
            <div class="grid-2" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
                <div class="stack"><label>Preis pro Einheit allein (€)</label><input type="text" inputmode="decimal" name="unit" value="<?= $eu($cfg['unit']) ?>"></div>
                <div class="stack"><label>Preis pro Einheit zu zweit (€)</label><input type="text" inputmode="decimal" name="unit2" value="<?= $eu($cfg['unit2']) ?>"></div>
            </div>
            <p class="small muted">Eine Einheit dauert <?= PTB_MINUTES ?> Minuten.</p>
            <details class="more"><summary>Mengenpreis (gerade <?= $cfg['disc_unit'] > 0 && $cfg['disc_unit'] < $cfg['unit'] ? 'an: ab ' . (int)$cfg['disc_from'] . ' Einheiten ' . forma_h(forma_euro($cfg['disc_unit'])) : 'aus' ?>)</summary>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-top:8px">
                <div class="stack"><label>Günstiger ab … Einheiten</label><input type="number" name="disc_from" min="2" max="<?= PTB_MAX_UNITS ?>" value="<?= (int)$cfg['disc_from'] ?>"></div>
                <div class="stack"><label>dann pro Einheit allein (€, leer = aus)</label><input type="text" inputmode="decimal" name="disc_unit" value="<?= $cfg['disc_unit'] > 0 ? $eu($cfg['disc_unit']) : '' ?>"></div>
            </div></details>
            <label>An diesen Tagen können Gäste Termine wünschen</label>
            <div style="display:flex;flex-wrap:wrap;gap:6px 14px"><?php foreach (PTB_WD_DE as $k => $w): ?><label class="check"><input type="checkbox" name="weekdays[]" value="<?= $k ?>"<?= in_array($k, $cfg['weekdays'], true) ? ' checked' : '' ?>> <?= $w ?></label><?php endforeach; ?></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:380px">
                <div class="stack"><label>Erste Startzeit</label><input type="time" name="from" value="<?= forma_h($cfg['from']) ?>" step="1800"></div>
                <div class="stack"><label>Letzte Startzeit</label><input type="time" name="to" value="<?= forma_h($cfg['to']) ?>" step="1800"></div>
            </div>
            <p class="small muted">Gäste wählen halbstündlich zwischen diesen Zeiten. Was schon gebucht ist (Personal Training, Prä-/Postnatal und FORMA-Kurse mit Anmeldungen), ist automatisch ausgegraut, mit 15 Minuten Puffer davor und danach. Einzelne Tage sperrst du im Kalender oben (Tag antippen).</p>
            <?php if ($cfg['blocked']): ?><p class="small">Gesperrte Tage: <?= forma_h(implode(', ', array_map(fn($d) => ptb_date($d), $cfg['blocked']))) ?></p><?php endif; ?>
            <button class="btn" type="submit">Speichern</button>
        </form>

        <h2 id="test">Als Gast testen</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_ptb_test">
            <p class="small muted">Legt eine Test-Anfrage mit Wunschterminen in den nächsten Tagen an (E-Mail gilt gleich als bestätigt). Dann probierst du hier „passt“, „Andere Zeit vorschlagen“ und „An Gast schicken“ aus und bekommst die Mails, die ein Gast bekommt. Testbuchungen zählen nicht zum Umsatz und bekommen keine Bewertungs-Mail.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px">
                <div class="stack"><label>Mails an</label><input type="email" name="email" value="<?= forma_h(natal_notify_email()) ?>" required></div>
                <div class="stack"><label>Sprache</label><select name="lang"><option value="de">Deutsch</option><option value="en">Englisch</option></select></div>
                <div class="stack"><label>Personen</label><select name="persons"><option value="1">allein</option><option value="2">zu zweit</option></select></div>
                <div class="stack"><label>Einheiten</label><select name="n"><?php for ($i = 1; $i <= PTB_MAX_UNITS; $i++): ?><option<?= $i === 2 ? ' selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></div>
            </div>
            <button class="btn btn-ghost" type="submit">Test-Anfrage anlegen</button>
        </form>
<script>document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', () => { navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(() => { b.textContent = 'kopiert ✓'; }); }));</script>
<?php
    });
    exit;
}

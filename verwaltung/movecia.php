<?php
// Movecia Movement in der Verwaltung: eigener Bereich, eigene Farbe, nichts mischt sich mit FORMA.
// Wird von verwaltung/index.php eingebunden (nur angemeldet, CSRF schon geprüft).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/movecia-lib.php';
mm_schema($pdo);
try { mm_auto_setup($pdo); } catch (Throwable $e) { error_log('MM setup: ' . $e->getMessage()); }
// Prä- & Postnatal Training (eigener Reiter ?view=natal)
require __DIR__ . '/natal.inc.php';
// Method & Unfold (eigener Reiter ?view=method)
require __DIR__ . '/method.inc.php';

$mmBack = $self . '?view=movecia';

if ($action === 'mm_save_gathering') {
    $gid = (int)($_POST['gid'] ?? 0);
    $title = forma_clean((string)($_POST['title'] ?? ''), 120);
    $date = (string)($_POST['date'] ?? '');
    $time = (string)($_POST['time'] ?? '');
    $min = max(10, min(240, (int)($_POST['minutes'] ?? 60)));
    $desc = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 1200);
    $script = mb_substr(trim((string)($_POST['script'] ?? '')), 0, 120);
    if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        go($mmBack . ($gid ? '&edit=' . $gid : '') . '#neu', 'Bitte gib Titel, Datum und Uhrzeit an.');
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
    go($mmBack . '&g=' . $gid . '#g' . $gid, $msg);
}

if ($action === 'mm_room') {
    $gid = (int)($_POST['gid'] ?? 0);
    if (mm_whereby_key() === '') go($mmBack . '#einstellungen', 'Bitte trag zuerst deinen Whereby-Schlüssel ein.');
    try {
        go($mmBack . '&g=' . $gid . '#g' . $gid, mm_whereby_room($pdo, $gid) ? 'Der Videoraum ist fertig.' : 'Whereby hat keinen Raum zurückgegeben. Bitte versuch es noch einmal.');
    } catch (Throwable $e) {
        go($mmBack . '&g=' . $gid . '#g' . $gid, 'Der Videoraum konnte nicht angelegt werden: ' . $e->getMessage());
    }
}

if ($action === 'mm_testroom') {
    try {
        $t = mm_testroom(!empty($_POST['renew']));
    } catch (Throwable $e) {
        go($mmBack . '#technik', 'Der Testraum konnte nicht angelegt werden: ' . $e->getMessage());
    }
    header('Location: ' . mm_testroom_link($t));
    exit;
}

if ($action === 'mm_save_offers') {
    $cfg = mm_cfg('offers') ?? [];
    foreach (MM_OFFER_KEYS as $k) {
        if (in_array($k, ['method', 'unfold'], true)) continue;
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
    go($mmBack . '#angebote', 'Die Angebote sind gespeichert.');
}

if ($action === 'mm_order_recheck') {
    mm_offer_schema($pdo);
    $n = 0;
    foreach ($pdo->query("SELECT * FROM mm_orders WHERE status = 'offen'")->fetchAll() as $o) {
        try { if (mm_order_check($pdo, $o) === 'bezahlt') $n++; } catch (Throwable $e) { error_log('MM order recheck: ' . $e->getMessage()); }
    }
    go($mmBack . '#angebote', $n ? "$n Zahlung(en) nachträglich bestätigt." : 'Keine neuen Zahlungen gefunden.');
}

if ($action === 'mm_toggle') {
    $gid = (int)($_POST['gid'] ?? 0);
    $pdo->prepare('UPDATE mm_gatherings SET visible = 1 - visible WHERE id = ?')->execute([$gid]);
    go($mmBack . '&g=' . $gid . '#g' . $gid, 'Gespeichert.');
}

if ($action === 'mm_delete') {
    $gid = (int)($_POST['gid'] ?? 0);
    if (empty($_POST['sure'])) go($mmBack . '&g=' . $gid . '#g' . $gid, 'Bitte setze das Häkchen, um das Gathering wirklich zu löschen.');
    $pdo->prepare('DELETE FROM mm_signups WHERE gathering_id = ?')->execute([$gid]);
    $pdo->prepare('DELETE FROM mm_gatherings WHERE id = ?')->execute([$gid]);
    go($mmBack, 'Das Gathering und seine Anmeldungen sind gelöscht.');
}

if ($action === 'mm_signup_add') {
    $gid = (int)($_POST['gid'] ?? 0);
    $g = mm_gathering($pdo, $gid);
    $name = forma_clean((string)($_POST['name'] ?? ''), 80);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!$g || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) go($mmBack . '&g=' . $gid . '#g' . $gid, 'Bitte gib Namen und eine gültige E-Mail-Adresse an.');
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO mm_signups (gathering_id, name, email, token, created_at) VALUES (?,?,?,?,?) ON CONFLICT(gathering_id, email) DO UPDATE SET status = 'angemeldet'")
        ->execute([$gid, $name, $email, $tok, date('c')]);
    $s = $pdo->prepare('SELECT * FROM mm_signups WHERE gathering_id = ? AND email = ?');
    $s->execute([$gid, $email]);
    $row = $s->fetch();
    $sent = !empty($_POST['mail']) && mm_mail_signup($g, $row);
    go($mmBack . '&g=' . $gid . '#g' . $gid, forma_first_name($name) . ' ist eingetragen.' . ($sent ? ' Der Raum-Link ist per E-Mail unterwegs.' : ''));
}

if ($action === 'mm_signup_resend' || $action === 'mm_signup_cancel') {
    $sid = (int)($_POST['sid'] ?? 0);
    $s = $pdo->prepare('SELECT * FROM mm_signups WHERE id = ?');
    $s->execute([$sid]);
    $row = $s->fetch();
    if (!$row) go($mmBack, 'Diese Anmeldung gibt es nicht mehr.');
    $back = $mmBack . '&g=' . (int)$row['gathering_id'] . '#g' . (int)$row['gathering_id'];
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
    if (strlen($key) < 20) go($mmBack . '#einstellungen', 'Bitte füge den vollständigen API-Schlüssel aus Whereby ein.');
    if (!mm_whereby_check($key)) go($mmBack . '#einstellungen', 'Whereby hat den Schlüssel nicht angenommen. Bitte kopiere ihn noch einmal vollständig aus deinem Whereby-Dashboard (Configure → API).');
    mm_save_cfg('whereby', ['api_key' => $key]);
    $n = 0;
    foreach ($pdo->query("SELECT id, starts_at, minutes FROM mm_gatherings WHERE room_url = ''")->fetchAll() as $g) {
        if (strtotime($g['starts_at']) + (int)$g['minutes'] * 60 < time()) continue;
        try { if (mm_whereby_room($pdo, (int)$g['id'])) $n++; } catch (Throwable $e) { error_log('MM room: ' . $e->getMessage()); }
    }
    go($mmBack . '#einstellungen', 'Whereby ist verbunden.' . ($n ? ($n === 1 ? ' Der Videoraum für dein Gathering ist angelegt.' : " $n Videoräume sind angelegt.") : ''));
}

if ($action === 'mm_remove_whereby') {
    mm_save_cfg('whereby', null);
    go($mmBack . '#einstellungen', 'Whereby ist getrennt. Bereits angelegte Räume funktionieren weiter.');
}

if ($action === 'mm_save_mail') {
    $pw = (string)($_POST['mail_password'] ?? '');
    if ($pw === '') go($mmBack . '#einstellungen', 'Bitte gib das Passwort des Postfachs hello@moveciamovement.com ein.');
    try {
        mm_smtp($pw, MM_FROM_EMAIL, 'Test: Mails vom Wild Hearts Collective', 'text/plain; charset=UTF-8', "Hallo Viv,\n\ndas Postfach hello@moveciamovement.com ist mit deiner Verwaltung verbunden. Gathering-Mails gehen ab jetzt von hier raus.\n", mb_encode_mimeheader(MM_FROM_NAME, 'UTF-8') . ' <' . MM_FROM_EMAIL . '>');
    } catch (Throwable $e) {
        go($mmBack . '#einstellungen', 'Die Anmeldung beim Postfach hat nicht geklappt. Bitte prüfe das Passwort von hello@moveciamovement.com und versuch es noch einmal.');
    }
    mm_save_cfg('mail', ['password' => $pw]);
    go($mmBack . '#einstellungen', 'Geschafft! Gathering-Mails gehen jetzt von hello@moveciamovement.com raus. Eine Test-Mail liegt in diesem Postfach.');
}

if ($action === 'mm_test_mail') {
    $to = strtolower(trim((string)($_POST['to'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) go($mmBack . '#mails', 'Bitte gib eine gültige E-Mail-Adresse ein.');
    $ok = mm_mail($to, 'Test from the Wild Hearts Collective 🌙', "Hey Viv,\n\nthis is a test. If you can read this, emails from hello@moveciamovement.com arrive.\n", mm_mail_html('Test', '<p style="margin:0;">Hey Viv,</p><p>this is a test. If you can read this, emails from hello@moveciamovement.com arrive. 🤍</p>'));
    go($mmBack . '#mails', $ok ? 'Die Test-Mail ist raus an ' . $to . '. Schau auch im Spam nach. Unten siehst du, auf welchem Weg sie verschickt wurde.' : 'Die Test-Mail konnte nicht verschickt werden. Unten steht der Grund.');
}

if ($action === 'mm_resend_confirm') {
    $sid = (int)($_POST['sid'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM mm_subscribers WHERE id = ?');
    $st->execute([$sid]);
    $x = $st->fetch();
    if (!$x) go($mmBack . '#liste', 'Diesen Kontakt gibt es nicht mehr.');
    $ok = $x['status'] === 'bestaetigt' ? mm_mail_welcome($x) : mm_mail_confirm($x);
    go($mmBack . '#liste', $ok ? 'Die Mail ist noch einmal an ' . $x['email'] . ' unterwegs.' : 'Die Mail konnte nicht verschickt werden, siehe „Letzte Mails“.');
}

if ($action === 'mm_remove_mail') {
    mm_save_cfg('mail', null);
    go($mmBack . '#einstellungen', 'Das Postfach ist getrennt.');
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
    $trTok = (string)((mm_cfg('testroom') ?? [])['token'] ?? '');
    if ($trTok === '') { $tmp = mm_cfg('testroom') ?? []; $tmp['token'] = $trTok = bin2hex(random_bytes(12)); mm_save_cfg('testroom', $tmp); }
    $GLOBALS['mmHostLink'] = fn(int $gid) => mm_site() . '/gathering/?test=' . rawurlencode($trTok) . '&g=' . $gid;
    $hasMail = !empty(mm_cfg('mail')['password']);
    render_page('Movecia Movement', function () use ($self, $flash, $pdo, $ml, $gatherings, $next, $edit, $openG, $hasWhereby, $hasMail) {
        $nextSign = $next ? mm_signup_count($pdo, (int)$next['id']) : 0;
        $subs = $pdo->query("SELECT * FROM mm_subscribers ORDER BY created_at DESC")->fetchAll();
        $subOk = count(array_filter($subs, fn($x) => $x['status'] === 'bestaetigt'));
        $subOpen = count(array_filter($subs, fn($x) => $x['status'] === 'offen'));
        $srcName = ['reset' => 'Reset Routine', 'circle' => 'Interesse Inner Circle', 'mentoring' => 'Interesse Mentoring', 'year' => 'Interesse Jahreskreis', 'retreat' => 'Interesse Alpine Retreat', 'unfold' => 'Interesse Unfold', 'link' => 'Link in Bio'];
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
.mm-hero h1{color:#FBF5F7;font-family:Georgia,"Times New Roman",serif;font-weight:400;font-size:34px;margin:2px 0 4px;position:relative}
.mm-hero p{position:relative;margin:0;color:#EADFDE}
.mm-hero .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
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
        <p><a href="<?= forma_h($self) ?>">← Zurück zu FORMA</a></p>
        <section class="mm-hero">
            <p class="eyebrow">Movecia Movement</p>
            <h1>Movecia</h1>
            <p>Dein eigener Bereich, getrennt von FORMA: Method &amp; Unfold, Prä- &amp; Postnatal, Gatherings, E-Mail-Liste und Videoraum.</p>
        </section>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
<style>
.qa{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:14px 0 4px}
@media(min-width:900px){.qa{grid-template-columns:repeat(4,minmax(0,1fr))}}
.qa form{margin:0}
.qa a,.qa button{display:flex;flex-direction:column;gap:4px;width:100%;height:100%;box-sizing:border-box;text-align:left;padding:16px;border-radius:20px;border:1px solid #DDD2C6;background:#FBF8F4;color:#2E2529;text-decoration:none;font:inherit;cursor:pointer}
.qa b{font-size:17px;font-weight:600;color:#4A3A41}
.qa span{font-size:14px;color:#7D7076;line-height:1.35}
.qa .ic{font-size:26px;line-height:1}
.qa .main{background:#7D5E68;border-color:#7D5E68}.qa .main b,.qa .main span{color:#FBF5F7}
</style>
        <h2 style="margin-top:18px">Schnellzugriff</h2>
        <div class="qa">
            <form method="post" target="_blank"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_testroom"><button class="main" type="submit"><span class="ic">🎥</span><b>Video &amp; Technik testen</b><span>Kamera, Mikro, Teleprompter, Aufnahme – danach direkt in deinen Testraum</span></button></form>
            <?php if ($next): ?><a href="<?= forma_h($GLOBALS['mmHostLink']((int)$next['id'])) ?>" target="_blank" rel="noopener"><span class="ic">✨</span><b>Nächstes Gathering öffnen</b><span>Als Gastgeberin: <?= forma_h($next['title']) ?></span></a><?php else: ?><a href="#neu"><span class="ic">✨</span><b>Gathering anlegen</b><span>Noch keines geplant</span></a><?php endif; ?>
            <a href="<?= forma_h(mm_site()) ?>/natal/mitglied/?demo=prae" target="_blank" rel="noopener"><span class="ic">🌿</span><b>Mitgliederbereich ansehen</b><span>So sieht ihn eine Kundin mit Paket (Beispiel Pränatal, auch <u>post</u> unter Prä- &amp; Postnatal)</span></a>
            <a href="<?= forma_h(mm_site()) ?>/natal/mitglied/" target="_blank" rel="noopener"><span class="ic">🔑</span><b>Login für Kundinnen</b><span>moveciamovement.com/natal/mitglied – diesen Link bekommen sie auch per Mail</span></a>
        </div>
        <div class="mm-stats">
            <a class="mm-stat" href="#liste"><b><?= $subOk ?></b><span>auf der E-Mail-Liste<?= $subOpen ? ' · ' . $subOpen . ' noch nicht bestätigt' : '' ?></span></a>
            <a class="mm-stat" href="#g<?= (int)($next['id'] ?? 0) ?>"><b><?= $nextSign ?></b><span>fürs nächste Gathering angemeldet</span></a>
            <a class="mm-stat live" href="#g<?= (int)($next['id'] ?? 0) ?>"><b><?= $live ?></b><span>gerade im Videochat</span></a>
            <span class="mm-stat"><b><?= $joinedAll ?></b><span>waren schon bei einem Gathering dabei</span></span>
        </div>
        <div class="mm-links">
            <a href="<?= forma_h($self) ?>?view=method" style="background:#4A3A41;color:#FBF5F7;border-color:#4A3A41">Method &amp; Unfold →</a>
            <a href="<?= forma_h($self) ?>?view=natal" style="background:#7D5E68;color:#FBF5F7;border-color:#7D5E68">Prä- &amp; Postnatal →</a>
            <a href="https://moveciamovement.com/" target="_blank" rel="noopener">Website</a>
            <a href="https://moveciamovement.com/links/" target="_blank" rel="noopener">Link in Bio</a>
            <a href="https://moveciamovement.com/gathering/" target="_blank" rel="noopener">Gathering-Seite</a>
            <a href="https://moveciamovement.com/reset/" target="_blank" rel="noopener">Reset Routine Download</a>
            <a href="https://dashboard.mailerlite.com/automations" target="_blank" rel="noopener">MailerLite</a>
            <a href="https://whereby.com/information/embedded/" target="_blank" rel="noopener">Whereby</a>
        </div>

        <h2 id="liste">E-Mail-Liste Wild Hearts</h2>
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

        <?php mm_offer_schema($pdo); $offs = mm_offers(); $orders = $pdo->query('SELECT * FROM mm_orders ORDER BY id DESC LIMIT 60')->fetchAll(); $openOrd = count(array_filter($orders, fn($o) => $o['status'] === 'offen')); ?>
        <h2 id="angebote">Angebote</h2>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_save_offers">
            <p class="small">Diese Angebote kannst du am Ende eines Gatherings aus deinem Gastgeberinnen-Fenster einblenden („✦ Angebot“). Hier trägst du den regulären Preis ein. Den günstigeren Gathering-Preis und wie viele Minuten er gilt, gibst du beim Einblenden ein. Läuft der Countdown ab, gilt automatisch wieder der reguläre Preis, auch beim Bezahlen. Titel und Text sehen die Frauen, deshalb auf Englisch.</p>
            <p class="small">Method und Unfold kannst du ebenfalls einblenden. Ihre Preise (auch die Gathering-Preise) stellst du unter <a href="<?= forma_h($self) ?>?view=method#preise">Method &amp; Unfold → Preise</a> ein.</p>
            <?php foreach ($offs as $k => $o): if (in_array($k, ['method', 'unfold'], true)) continue; ?>
                <div class="stack" style="border-top:1px solid var(--line);padding-top:12px">
                    <label for="ot<?= $k ?>"><strong><?= ['circle' => 'Inner Circle', 'year' => 'Jahreskreis', 'retreat' => 'Alpine Retreat'][$k] ?></strong> · Titel</label>
                    <input id="ot<?= $k ?>" name="title[<?= $k ?>]" value="<?= forma_h($o['title']) ?>" maxlength="80">
                    <label for="ox<?= $k ?>">Kurzer Text</label>
                    <textarea id="ox<?= $k ?>" name="text[<?= $k ?>]" rows="3" maxlength="600"><?= forma_h($o['text']) ?></textarea>
                    <div class="row">
                        <div class="stack"><label for="op<?= $k ?>">Regulärer Preis in €</label><input id="op<?= $k ?>" name="price[<?= $k ?>]" inputmode="decimal" value="<?= $o['price_cents'] ? forma_h(rtrim(rtrim(number_format($o['price_cents'] / 100, 2, ',', ''), '0'), ',')) : '' ?>" placeholder="noch offen"></div>
                        <div class="stack"><label for="os<?= $k ?>">Gathering-Preis in €</label><input id="os<?= $k ?>" name="special[<?= $k ?>]" inputmode="decimal" value="<?= $o['special_cents'] ? forma_h(rtrim(rtrim(number_format($o['special_cents'] / 100, 2, ',', ''), '0'), ',')) : '' ?>"></div>
                        <div class="stack"><label for="ob<?= $k ?>">Zahlung</label><select id="ob<?= $k ?>" name="billing[<?= $k ?>]"><option value="once"<?= $o['billing'] === 'once' ? ' selected' : '' ?>>einmalig</option><option value="monthly"<?= $o['billing'] === 'monthly' ? ' selected' : '' ?>>monatlich (Abo)</option></select></div>
                    </div>
                </div>
                <?php if ($k === 'circle'): ?>
                    <div class="row">
                        <div class="stack"><label for="cim">Gathering-Preis gilt für die ersten … Monate</label><input id="cim" name="intro_months" type="number" min="1" max="24" value="<?= (int)$o['intro_months'] ?>"></div>
                        <div class="stack"><label for="crm">Pro eingeladener Freundin … Monate mehr</label><input id="crm" name="ref_months" type="number" min="1" max="24" value="<?= (int)$o['ref_months'] ?>"></div>
                        <div class="stack"><label for="cmm">Höchstens … Monate insgesamt pro Frau</label><input id="cmm" name="max_months" type="number" min="1" max="24" value="<?= (int)$o['max_months'] ?>"></div>
                    </div>
                    <?php $pe = []; foreach ($o['periods'] as $pp) $pe[$pp[0]] = $pp[1]; $eu2 = fn($c) => $c ? forma_h(rtrim(rtrim(number_format($c / 100, 2, ',', ''), '0'), ',')) : ''; ?>
                    <div class="row">
                        <div class="stack"><label for="cp6">6 Monate im Voraus in €</label><input id="cp6" name="period[6]" inputmode="decimal" value="<?= $eu2($pe[6] ?? 0) ?>" placeholder="leer = aus"></div>
                        <div class="stack"><label for="cp12">1 Jahr im Voraus in €</label><input id="cp12" name="period[12]" inputmode="decimal" value="<?= $eu2($pe[12] ?? 0) ?>" placeholder="leer = aus"></div>
                    </div>
                    <div class="row"><div class="stack"><label for="cst">Start der Mitgliedschaft (erste Abbuchung)</label><input id="cst" name="start" type="date" value="<?= forma_h($o['start']) ?>"></div></div>
                    <p class="muted small">Bis zu diesem Tag können die Frauen schon buchen und ihre Karte hinterlegen, abgebucht wird erst am Starttag um 9 Uhr. Der Gathering-Preis gilt dann für die ersten Abbuchungen ab dem Start. Ein Datum in der Vergangenheit = sofort abbuchen.</p>
                    <p class="muted small">Im Voraus zahlen: fester Preis, kein zusätzlicher Gathering-Rabatt, verlängert sich automatisch um denselben Zeitraum und ist jederzeit zum Ende des bezahlten Zeitraums kündbar.</p>
                    <p class="muted small">Danach gilt der reguläre Preis. Zu Beginn des 3. Monats bekommt jede Frau automatisch eine Mail mit ihrem persönlichen Einladungslink (moveciamovement.com/circle/?ref=…). Die Freundin zahlt damit ihre ersten Monate den Gathering-Preis, die Einladende bekommt Monate dazu, bis zur Obergrenze. Die gilt auch für die Freundin: Ihre ersten Monate zählen mit.</p>
                <?php endif; ?>
                <?php if ($k === 'year'): $eu = fn($c) => $c ? forma_h(rtrim(rtrim(number_format($c / 100, 2, ',', ''), '0'), ',')) : ''; ?>
                    <?php $pl = []; foreach ($o['plans'] as $pp) $pl[$pp[0]] = $pp; ?>
                    <?php foreach ([3, 6] as $n): ?>
                    <div class="row">
                        <div class="stack"><label for="pr<?= $n ?>"><?= $n ?> Raten: Rate regulär in €</label><input id="pr<?= $n ?>" name="plan_reg[<?= $n ?>]" inputmode="decimal" value="<?= $eu($pl[$n][1] ?? 0) ?>" placeholder="leer = keine <?= $n ?> Raten"></div>
                        <div class="stack"><label for="ps<?= $n ?>"><?= $n ?> Raten: Rate zum Gathering-Preis in €</label><input id="ps<?= $n ?>" name="plan_sp[<?= $n ?>]" inputmode="decimal" value="<?= $eu($pl[$n][2] ?? 0) ?>"></div>
                    </div>
                    <?php endforeach; ?>
                    <p class="muted small">Die Frauen können dann auf einmal, in 3 oder in 6 Monatsraten zahlen. Stripe bucht monatlich ab und hört nach der letzten Rate von selbst auf. Ein leeres Feld schaltet diese Variante aus.</p>
                <?php endif; ?>
            <?php endforeach; ?>
            <p class="muted small">Bei „monatlich“ wird ein Abo über Stripe abgeschlossen. Beim Inner Circle gilt der Gathering-Preis nur für die oben eingestellten Monate, danach automatisch der reguläre Preis.</p>
            <button class="btn" type="submit">Angebote speichern</button>
        </form>
        <?php try { $tp = mm_test_promo(); } catch (Throwable $e) { $tp = null; } if ($tp && (int)$tp['until'] > time()): ?>
        <div class="card stack" style="margin-top:12px">
            <p><strong>Gutscheincode für deine Testbuchung</strong></p>
            <p class="small"><span class="sel" style="font-size:20px;letter-spacing:.06em"><?= forma_h($tp['code']) ?></span> · 100 % · höchstens 3 Mal · bis <?= forma_h(date('d. m. Y', (int)$tp['until'])) ?><?= $tp['live'] ? '' : ' · Stripe-Testmodus' ?></p>
            <p class="muted small">Im Stripe-Bezahlfenster bei „Gutscheincode hinzufügen“ eingeben, dann zahlst du € 0. Das Feld erscheint nur beim regulären Preis (z. B. moveciamovement.com/circle ohne Einladungslink). Test-Abo danach in Stripe kündigen. Bitte nicht weitergeben.</p>
        </div>
        <?php endif; ?>
        <div class="card stack" style="margin-top:12px">
            <p><strong>Buchungen</strong></p>
            <?php if (!$orders): ?><p class="muted small">Noch keine. Probier es im Testraum aus: Angebot einblenden und mit dem Gast-Link (zweites Gerät) „buchen“. Im Stripe-Testmodus geht das mit der Testkarte 4242 4242 4242 4242.</p><?php endif; ?>
            <?php foreach ($orders as $o): ?>
                <div class="mm-signup<?= $o['status'] === 'abgebrochen' ? ' is-cancelled' : '' ?>">
                    <span><strong><?= forma_h($o['name'] ?: '–') ?></strong> <span class="muted small sel"><?= forma_h($o['email']) ?></span><br>
                    <span class="muted small"><?= forma_h(date('d. m. Y, H:i', strtotime($o['created_at']))) ?> · <?= forma_h($o['title']) ?> · <?= forma_h(forma_euro((int)$o['amount_cents'])) ?><?= $o['billing'] === 'monthly' ? '/Monat' : '' ?><?= str_starts_with((string)($o['plan'] ?? ''), 'inst') ? ' · ' . (int)substr((string)$o['plan'], 4) . ' Raten' : '' ?><?= (int)($o['bill_start'] ?? 0) > time() ? ' · Abbuchung ab ' . date('d.m.Y', (int)$o['bill_start']) : '' ?><?= ($o['plan'] ?? '') === 'p12' ? ' · Jahresmitgliedschaft' : (($o['plan'] ?? '') === 'p6' ? ' · 6 Monate im Voraus' : '') ?><?= ($o['consent_digital'] ?? 0) ? ' · Kursbereich sofort (Verzicht bestätigt)' : '' ?> · <?= $o['special'] ? 'Gathering-Preis' : 'regulär' ?><?= ($o['consent'] ?? 0) ? ' · Sofortstart bestätigt' : '' ?><?= $o['test'] ? ' · Test' : '' ?><?= ($o['referred_by'] ?? 0) ? ' · eingeladen (Bestellung #' . (int)$o['referred_by'] . ')' : '' ?><?= ($o['ref_bonus'] ?? 0) ? ' · hat ' . (int)$o['ref_bonus'] . ' Freundin(nen) eingeladen' : '' ?><?= ($o['ref_code'] ?? '') !== '' ? ' · Einladungslink: <span class="sel">' . forma_h(mm_site() . '/circle/?ref=' . $o['ref_code']) . '</span>' : '' ?></span></span>
                    <span class="mm-badge<?= $o['status'] === 'offen' ? ' on' : '' ?>"><?= ['bezahlt' => 'bezahlt', 'offen' => 'Zahlung offen', 'abgebrochen' => 'abgebrochen'][$o['status']] ?? forma_h($o['status']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($openOrd): ?><form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_order_recheck"><button class="link small" type="submit">Offene Zahlungen bei Stripe prüfen</button></form><?php endif; ?>
        </div>

        <h2 id="technik">Technik-Test</h2>
        <?php $tr = mm_cfg('testroom') ?? []; $trReady = !empty($tr['room_url']) && (int)($tr['ends'] ?? 0) > time(); ?>
        <div class="card stack">
            <p class="small">Probier Mikro, Kamera, Lautsprecher und Teleprompter in Ruhe aus, bevor die Frauen kommen. Erst siehst du dein Kamerabild und einen Pegel, der beim Sprechen ausschlägt, dann kannst du direkt in einen eigenen Whereby-Testraum auf moveciamovement.com gehen, genau so, wie die Gäste ihn später sehen.<?= $hasWhereby ? '' : ' Ohne Whereby-Schlüssel klappt erst einmal nur der Geräte-Check.' ?></p>
            <form method="post" target="_blank"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_testroom"><button class="btn" type="submit">Testraum öffnen</button></form>
            <?php if ($trReady): ?>
                <p class="muted small">Zum Testen mit einem zweiten Gerät oder einer Freundin (sie sieht und hört dich dann, wie eine Teilnehmerin): <span class="sel"><?= forma_h(mm_testroom_link($tr, true)) ?></span><br>Der Testraum gilt bis <?= forma_h(date('d. m. Y', (int)$tr['ends'])) ?> und entsteht danach automatisch neu.</p>
                <form method="post" target="_blank"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_testroom"><input type="hidden" name="renew" value="1"><button class="link small" type="submit">Neuen Testraum anlegen</button></form>
            <?php endif; ?>
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
                        <a class="btn btn-ghost" href="<?= forma_h($self) ?>?view=movecia&amp;edit=<?= $gid ?>#neu">Bearbeiten</a>
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
            <div class="mm-links" style="margin-top:0"><?php foreach (mm_gathering_presets() as $i => $pr): ?><a href="#neu" data-preset="<?= $i ?>"><?= forma_h($pr['label']) ?></a><?php endforeach; ?></div>
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
            <p><strong>Absender hello@moveciamovement.com</strong> <?= $hasMail ? '<span class="mm-badge">verbunden</span>' : '<span class="mm-badge on">noch nicht verbunden</span>' ?></p>
            <p class="muted small">Damit Raum-Links und Erinnerungen sicher ankommen und nicht im Spam landen, gehen sie über dein Hostinger-Postfach hello@moveciamovement.com raus. Trag dafür einmal das Passwort dieses Postfachs ein.</p>
            <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_save_mail">
                <input name="mail_password" type="password" autocomplete="off" placeholder="Passwort von hello@moveciamovement.com">
                <button class="btn" type="submit">Postfach verbinden</button></form>
            <?php if ($hasMail): ?><form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="mm_remove_mail"><button class="link small" type="submit">Postfach trennen</button></form><?php endif; ?>
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
    <?php });
}

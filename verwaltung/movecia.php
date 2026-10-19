<?php
// Movecia Movement in der Verwaltung: eigener Bereich, eigene Farbe, nichts mischt sich mit FORMA.
// Wird von verwaltung/index.php eingebunden (nur angemeldet, CSRF schon geprüft).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/movecia-lib.php';
mm_schema($pdo);

$mmBack = $self . '?view=movecia';

if ($action === 'mm_save_gathering') {
    $gid = (int)($_POST['gid'] ?? 0);
    $title = forma_clean((string)($_POST['title'] ?? ''), 120);
    $date = (string)($_POST['date'] ?? '');
    $time = (string)($_POST['time'] ?? '');
    $min = max(15, min(240, (int)($_POST['minutes'] ?? 60)));
    $desc = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 1200);
    if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        go($mmBack . ($gid ? '&edit=' . $gid : '') . '#neu', 'Bitte gib Titel, Datum und Uhrzeit an.');
    }
    $starts = $date . 'T' . $time;
    if ($gid) {
        $old = mm_gathering($pdo, $gid);
        $pdo->prepare('UPDATE mm_gatherings SET title = ?, starts_at = ?, minutes = ?, description = ?, reminded_day = CASE WHEN starts_at = ? THEN reminded_day ELSE 0 END, reminded_hour = CASE WHEN starts_at = ? THEN reminded_hour ELSE 0 END WHERE id = ?')
            ->execute([$title, $starts, $min, $desc, $starts, $starts, $gid]);
        $msg = 'Gespeichert.';
        // Neue Zeit nach hinten: Raum muss länger offen bleiben, deshalb neu anlegen.
        if ($old && $old['room_url'] !== '' && strtotime($starts) + $min * 60 > strtotime($old['starts_at']) + (int)$old['minutes'] * 60 && mm_whereby_key() !== '') {
            try { mm_whereby_room($pdo, $gid); $msg .= ' Der Videoraum ist für die neue Zeit neu angelegt.'; } catch (Throwable $e) { $msg .= ' Den Videoraum bitte unten neu anlegen.'; }
        }
    } else {
        $pdo->prepare('INSERT INTO mm_gatherings (title, starts_at, minutes, description, created_at) VALUES (?,?,?,?,?)')->execute([$title, $starts, $min, $desc, date('c')]);
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
    $hasMail = !empty(mm_cfg('mail')['password']);
    render_page('Movecia Movement', function () use ($self, $flash, $pdo, $ml, $gatherings, $next, $edit, $openG, $hasWhereby, $hasMail) {
        $nextSign = $next ? mm_signup_count($pdo, (int)$next['id']) : 0;
        $subs = $pdo->query("SELECT * FROM mm_subscribers ORDER BY created_at DESC")->fetchAll();
        $subOk = count(array_filter($subs, fn($x) => $x['status'] === 'bestaetigt'));
        $subOpen = count(array_filter($subs, fn($x) => $x['status'] === 'offen'));
        $srcName = ['reset' => 'Reset Routine', 'circle' => 'Interesse Inner Circle', 'mentoring' => 'Interesse Mentoring', 'year' => 'Interesse Jahreskreis', 'retreat' => 'Interesse Alpine Retreat', 'link' => 'Link in Bio'];
        $live = $next ? mm_live_count($pdo, (int)$next['id']) : 0;
        $joinedAll = (int)$pdo->query('SELECT COUNT(*) FROM mm_signups WHERE joined_at IS NOT NULL')->fetchColumn();
        $d = $edit ?: ['id' => 0, 'title' => 'Wild Hearts Gathering', 'starts_at' => '', 'minutes' => 60, 'description' => ''];
        $dDate = $d['starts_at'] ? substr($d['starts_at'], 0, 10) : '';
        $dTime = $d['starts_at'] ? substr($d['starts_at'], 11, 5) : '19:00'; ?>
<style>
:root{--paper:#E4E7DC;--card:#F7F7F1;--clay:#4E5E44;--soft:#5E5A4E;--line:#C7CEBB}
body{background:var(--paper)}
.head .brand span{color:#4E5E44}
.mm-hero{position:relative;overflow:hidden;border-radius:26px;background:#3E4A36;color:#F3F1E7;padding:24px 24px 22px}
.mm-hero::after{content:"";position:absolute;width:220px;height:220px;right:-80px;top:-90px;border-radius:58% 42% 47% 53%/52% 46% 54% 48%;background:rgba(201,139,120,.35)}
.mm-hero h1{color:#F3F1E7;font-family:Georgia,"Times New Roman",serif;font-weight:400;font-size:34px;margin:2px 0 4px;position:relative}
.mm-hero p{position:relative;margin:0;color:#DCE0D2}
.mm-hero .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E7C4AE}
.mm-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}
.mm-stat{background:#FBFAF4;border:1px solid var(--line);border-radius:18px;padding:12px 14px;color:var(--ink);text-decoration:none;display:flex;flex-direction:column}
.mm-stat b{font-family:"Bricolage Grotesque","Outfit",sans-serif;font-size:32px;line-height:1.1;color:#4E5E44}
.mm-stat span{font-size:14px;color:var(--soft);line-height:1.3}
.mm-stat.live b{color:#93402F}
.mm-links{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.mm-links a{display:inline-block;padding:7px 14px;border-radius:999px;background:#F7F7F1;border:1px solid var(--line);text-decoration:none;color:#4E5E44;font-size:15px}
.mm-g summary{cursor:pointer;list-style:none}
.mm-g summary::-webkit-details-marker{display:none}
.mm-g .row3{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.mm-g .btn{padding:9px 16px;min-height:0;font-size:16px}
.mm-signup{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;border-top:1px solid var(--line);padding:8px 0}
.mm-badge{display:inline-block;border-radius:999px;padding:1px 9px;font-size:13px;background:#DCE0D2;color:#3E4A36}
.mm-badge.on{background:#93402F;color:#FBF1EA}
select{font:inherit;padding:11px 12px;border:1px solid var(--line);border-radius:12px;background:#fff}
@media (min-width:700px){.mm-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
</style>
        <p><a href="<?= forma_h($self) ?>">← Zurück zu FORMA</a></p>
        <section class="mm-hero">
            <p class="eyebrow">Movecia Movement</p>
            <h1>Wild Hearts Collective</h1>
            <p>Dein eigener Bereich, getrennt von FORMA: E-Mail-Liste, Gatherings und Videoraum.</p>
        </section>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="mm-stats">
            <a class="mm-stat" href="#liste"><b><?= $subOk ?></b><span>auf der E-Mail-Liste<?= $subOpen ? ' · ' . $subOpen . ' noch nicht bestätigt' : '' ?></span></a>
            <a class="mm-stat" href="#g<?= (int)($next['id'] ?? 0) ?>"><b><?= $nextSign ?></b><span>fürs nächste Gathering angemeldet</span></a>
            <a class="mm-stat live" href="#g<?= (int)($next['id'] ?? 0) ?>"><b><?= $live ?></b><span>gerade im Videochat</span></a>
            <span class="mm-stat"><b><?= $joinedAll ?></b><span>waren schon bei einem Gathering dabei</span></span>
        </div>
        <div class="mm-links">
            <a href="https://moveciamovement.com/" target="_blank" rel="noopener">Link in Bio</a>
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

        <h2>Gatherings</h2>
        <?php if ($next && $next['room_url'] !== ''): ?>
            <a class="card notice" href="<?= forma_h($next['host_url'] ?: $next['room_url']) ?>" target="_blank" rel="noopener" style="border-color:#4E5E44;background:#F7F7F1"><span class="ic" style="background:#DCE0D2;color:#4E5E44"><?= admin_icon('kalender') ?></span><span><strong>Als Gastgeberin in den Raum: <?= forma_h($next['title']) ?></strong><br><span class="muted small"><?= forma_h(mm_when_de($next)) ?> · öffnet deinen Raum mit Gastgeber-Rechten →</span></span></a>
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
                        <?php if ($g['host_url'] !== '' && !$past): ?><a class="btn" href="<?= forma_h($g['host_url']) ?>" target="_blank" rel="noopener">Als Gastgeberin öffnen</a><?php endif; ?>
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
            <label for="mt">Titel (Englisch, so sehen ihn die Frauen)</label>
            <input id="mt" name="title" value="<?= forma_h($d['title']) ?>" required maxlength="120">
            <div class="row">
                <div class="stack"><label for="md">Datum</label><input id="md" type="date" name="date" value="<?= forma_h($dDate) ?>" required></div>
                <div class="stack"><label for="mz">Uhrzeit (Wien)</label><input id="mz" type="time" name="time" value="<?= forma_h($dTime) ?>" required></div>
            </div>
            <label for="mm">Dauer</label>
            <select id="mm" name="minutes"><?php foreach ([35, 45, 60, 75, 90] as $m): ?><option value="<?= $m ?>" <?= (int)$d['minutes'] === $m ? 'selected' : '' ?>><?= $m ?> Minuten</option><?php endforeach; ?></select>
            <label for="mx">Kurzer Text für die Anmeldeseite (Englisch, darf leer bleiben)</label>
            <textarea id="mx" name="description" rows="4" maxlength="1200"><?= forma_h($d['description']) ?></textarea>
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
                <?php if ($l['error'] !== ''): ?><br><span class="small" style="color:#93402F"><?= forma_h($l['error']) ?></span><?php endif; ?></span>
                <span><span class="mm-badge<?= $l['ok'] ? '' : ' on' ?>"><?= $l['ok'] ? 'verschickt' : 'fehlgeschlagen' ?></span></span></div>
            <?php endforeach; ?>
        </div>
    <?php });
}

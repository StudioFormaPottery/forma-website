<?php
// moveciamovement.com/mitglieder/ – Mitgliederbereich Movecia Mindful Movement Method + Unfold (Vivien, 25.10.2026)
// Anmeldung mit E-Mail + Passwort, Bibliothek mit Monatsprogrammen, Filter nach Dauer/Energie/Art, sanfter Fortschritt,
// privates Journal, Workbooks, Unfold-Bereich mit Live-Calls, Konto mit Kündigung. Deutsch und Englisch.
// ?demo=1 zeigt eine Beispielansicht ohne Konto (für Vivien und Screenshots).
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/method-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$pdo = forma_db();
mv_schema($pdo);
mv_seed($pdo);
try { mv_tick($pdo); } catch (Throwable $e) { error_log('MV tick: ' . $e->getMessage()); }
$h = 'forma_h';
$self = '/mitglieder/';
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'mv');
$go = function (string $q = '') use ($self) { header('Location: ' . $self . $q, true, 303); exit; };
// Testteilnehmerin aus der Verwaltung (Vivien, 25.10.2026)
if (isset($_GET['testlogin'])) {
    $tm = mv_test_login_ok($pdo, (int)$_GET['testlogin'], (int)($_GET['e'] ?? 0), (string)($_GET['k'] ?? ''));
    if ($tm) { mv_login($pdo, $tm); $go(); }
}
$me = mv_current($pdo);
$demo = !$me && isset($_GET['demo']);
$lang = $me ? (string)$me['lang'] : ((($_GET['lang'] ?? '') === 'en') ? 'en' : (((($_GET['lang'] ?? '') === 'de')) ? 'de' : ''));
$L = fn(string $de, string $en) => ($lang === 'en' ? $en : $de);

// --- Aktionen ---------------------------------------------------------------------------------
$err = ''; $note = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $a = (string)($_POST['a'] ?? '');
    $pl = ($_POST['lang'] ?? '') === 'en' ? 'en' : 'de';
    if (!empty($_POST['website'])) $go();
    if ($a === 'login') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (mv_throttled($pdo, $ip)) $err = $pl === 'en' ? 'Too many attempts. Please wait a quarter of an hour or reset your password.' : 'Zu viele Versuche. Bitte warte eine Viertelstunde oder setz dein Passwort neu.';
        else {
            $m = mv_member_by_email($pdo, $email);
            if ($m && $m['pass_hash'] !== '' && password_verify((string)($_POST['pw'] ?? ''), (string)$m['pass_hash'])) { mv_login($pdo, $m); $go(); }
            mv_fail($pdo, $ip);
            $err = $pl === 'en' ? 'Email or password are not right. Try again or set a new password.' : 'E-Mail oder Passwort stimmen nicht. Versuch es noch einmal oder setz dein Passwort neu.';
        }
    }
    if ($a === 'setpw') {
        $pw = (string)($_POST['pw'] ?? '');
        $m = null;
        if (!empty($_POST['t'])) $m = mv_by_reset($pdo, (string)$_POST['t']);
        elseif (!empty($_POST['s'])) {
            $r = mv_sub($pdo, (int)$_POST['s']);
            if ($r && hash_equals((string)$r['token'], (string)($_POST['k'] ?? '')) && $r['status'] === 'aktiv') {
                $m = mv_member_ensure($pdo, (string)$r['email'], (string)$r['name'], (string)$r['lang']);
                if ($m['pass_hash'] !== '') { $m = null; $err = $pl === 'en' ? 'You already have a password. Simply log in.' : 'Du hast schon ein Passwort. Melde dich einfach an.'; }
            }
        }
        if ($err === '') {
            if (!$m) $err = $pl === 'en' ? 'This link no longer works. Request a new one below.' : 'Dieser Link funktioniert nicht mehr. Fordere unten einfach einen neuen an.';
            elseif (mb_strlen($pw) < 8) $err = $pl === 'en' ? 'Please choose a password with at least 8 characters.' : 'Bitte wähl ein Passwort mit mindestens 8 Zeichen.';
            elseif ($pw !== (string)($_POST['pw2'] ?? '')) $err = $pl === 'en' ? 'The two passwords are not the same.' : 'Die beiden Passwörter sind nicht gleich.';
            else { mv_login($pdo, mv_set_password($pdo, $m, $pw)); $go('?willkommen=1'); }
        }
    }
    if ($a === 'reset') {
        if (!mv_throttled($pdo, $ip)) { mv_fail($pdo, $ip); mv_send_reset($pdo, (string)($_POST['email'] ?? '')); }
        $note = $pl === 'en' ? 'If there is an account for this email, the link is on its way. Please also check your spam folder.' : 'Wenn es für diese E-Mail ein Konto gibt, ist der Link jetzt unterwegs. Schau auch im Spam-Ordner nach.';
    }
    if ($me) {
        $mid = (int)$me['id'];
        $ajax = !empty($_POST['ajax']);
        if ($a === 'logout') { mv_logout(); $go('?bye=1'); }
        if ($a === 'lang') { $pdo->prepare('UPDATE mv_members SET lang = ? WHERE id = ?')->execute([$pl, $mid]); $go((string)($_POST['back'] ?? '')); }
        if ($a === 'done') {
            $lid = (int)($_POST['l'] ?? 0);
            $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
            $q = $pdo->prepare('SELECT COUNT(*) FROM mv_progress WHERE member_id = ? AND lesson_id = ? AND substr(done_at,1,10) = ?');
            $q->execute([$mid, $lid, $today]);
            if (!$q->fetchColumn() && mv_lesson($pdo, $lid)) $pdo->prepare('INSERT INTO mv_progress (member_id, lesson_id, done_at) VALUES (?,?,?)')->execute([$mid, $lid, (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('c')]);
            if ($ajax) { header('Content-Type: application/json'); echo json_encode(['week' => mv_week_days($pdo, $mid)]); exit; }
            $go('?l=' . $lid . '&ok=1');
        }
        if ($a === 'fav') {
            $lid = (int)($_POST['l'] ?? 0);
            $q = $pdo->prepare('DELETE FROM mv_favs WHERE member_id = ? AND lesson_id = ?');
            $q->execute([$mid, $lid]);
            $on = false;
            if ($q->rowCount() === 0 && mv_lesson($pdo, $lid)) { $pdo->prepare('INSERT INTO mv_favs (member_id, lesson_id) VALUES (?,?)')->execute([$mid, $lid]); $on = true; }
            if ($ajax) { header('Content-Type: application/json'); echo json_encode(['on' => $on]); exit; }
            $go('?l=' . $lid);
        }
        if ($a === 'journal') {
            $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['day'] ?? '')) ? (string)$_POST['day'] : date('Y-m-d');
            mv_journal_set($pdo, $mid, $day, (string)($_POST['text'] ?? ''));
            if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => 1]); exit; }
            $go('?tab=journal&day=' . $day);
        }
        if ($a === 'cancel' || $a === 'resume') {
            $acc = mv_access($pdo, (string)$me['email']);
            try { if ($acc['sub']) mv_cancel($pdo, $acc['sub'], $a === 'cancel'); } catch (Throwable $e) { error_log('MV cancel: ' . $e->getMessage()); $go('?tab=konto&fehler=1'); }
            $go('?tab=konto&' . ($a === 'cancel' ? 'gekuendigt=1' : 'weiter=1'));
        }
        if ($a === 'chpw') {
            $pw = (string)($_POST['pw'] ?? '');
            if (!password_verify((string)($_POST['old'] ?? ''), (string)$me['pass_hash'])) $go('?tab=konto&pwfehler=1');
            if (mb_strlen($pw) < 8 || $pw !== (string)($_POST['pw2'] ?? '')) $go('?tab=konto&pwfehler=2');
            mv_login($pdo, mv_set_password($pdo, $me, $pw));
            $go('?tab=konto&pwok=1');
        }
    }
}

// --- Gemeinsamer Kopf ----------------------------------------------------------------------------
function mv_head(string $title, string $lang): void
{
    ?><!doctype html>
<html lang="<?= $lang === 'en' ? 'en' : 'de' ?>" data-l="<?= $lang === 'en' ? 'en' : 'de' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= forma_h($title) ?> · Movecia</title>
<meta name="theme-color" content="#F2ECE4">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="manifest" href="/app.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Movecia"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="/wildhearts/assets/app.js?v=1" defer></script>
<link rel="manifest" href="/wildhearts/mitglieder/manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Movecia">
<link rel="stylesheet" href="/wildhearts/assets/mv.css?v=1">
<link rel="stylesheet" href="/wildhearts/mitglieder/app.css?v=1">
</head>
<?php
}

function mv_icon(string $cat): string
{
    $p = [
        'flow' => '<path d="M6 34c6-10 12-10 18 0s12 10 18 0"/><circle cx="24" cy="13" r="4"/>',
        'somatic' => '<path d="M24 8c-7 6-7 14 0 20s7 14 0 20"/><path d="M14 18c4 3 4 9 0 12M34 18c-4 3-4 9 0 12"/>',
        'breath' => '<path d="M10 20h20a5 5 0 1 0-5-5"/><path d="M8 28h26a5 5 0 1 1-5 5"/>',
        'meditation' => '<circle cx="24" cy="13" r="4"/><path d="M10 38c4-6 9-9 14-9s10 3 14 9"/><path d="M14 30c3 2 6 3 10 3s7-1 10-3"/>',
        'body' => '<path d="M24 8v6M16 18h16M24 14v12l-6 14M24 26l6 14"/><circle cx="24" cy="6" r="0.5"/>',
        'talk' => '<path d="M10 12h28v18H22l-8 7v-7h-4z"/><path d="M17 20h14M17 25h9"/>',
    ][$cat] ?? '<circle cx="24" cy="24" r="10"/>';
    return '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function mv_lang_switch(string $lang, string $back, bool $member): string
{
    if ($member) {
        return '<form method="post" class="lang"><input type="hidden" name="a" value="lang"><input type="hidden" name="back" value="' . forma_h($back) . '">'
            . '<button name="lang" value="de" aria-pressed="' . ($lang !== 'en' ? 'true' : 'false') . '">DE</button><button name="lang" value="en" aria-pressed="' . ($lang === 'en' ? 'true' : 'false') . '">EN</button></form>';
    }
    return '<div class="lang"><a class="lb" href="?lang=de" aria-pressed="' . ($lang !== 'en' ? 'true' : 'false') . '">DE</a><a class="lb" href="?lang=en" aria-pressed="' . ($lang === 'en' ? 'true' : 'false') . '">EN</a></div>';
}

// --- Nicht angemeldet: Anmeldung / Passwort festlegen ----------------------------------------------
if (!$me && !$demo) {
    $start = null; $startMember = null;
    if (isset($_GET['start'])) {
        $start = mv_sub($pdo, (int)$_GET['start']);
        if ($start && hash_equals((string)$start['token'], (string)($_GET['k'] ?? ''))) {
            if ($start['status'] === 'offen') { try { mv_check_paid($pdo, $start); $start = mv_sub($pdo, (int)$start['id']); } catch (Throwable $e) {} }
            if ($lang === '') $lang = (string)$start['lang'];
            $startMember = $start['status'] === 'aktiv' ? mv_member_by_email($pdo, (string)$start['email']) : null;
        } else $start = null;
    }
    $resetTok = (string)($_GET['pw'] ?? '');
    $resetM = $resetTok !== '' ? mv_by_reset($pdo, $resetTok) : null;
    if ($resetM && $lang === '') $lang = (string)$resetM['lang'];
    if ($lang === '') $lang = str_starts_with(strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'de')), 'en') ? 'en' : 'de';
    $L = fn(string $de, string $en) => ($lang === 'en' ? $en : $de);
    $mode = $resetTok !== '' ? 'reset' : ($start ? ($start['status'] === 'aktiv' ? ($startMember && $startMember['pass_hash'] !== '' ? 'login' : 'new') : 'wait') : (isset($_GET['vergessen']) ? 'forgot' : 'login'));
    mv_head($L('Mitgliederbereich', 'Members area'), $lang);
    ?>
<body class="auth">
<header class="w"><div class="in top"><a class="brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia<i></i></span></a><?= mv_lang_switch($lang, '', false) ?></div></header>
<main class="w"><div class="in authgrid">
  <div class="authpic"><img src="/img/wildhearts/cover.jpg" alt=""><span class="blob" style="width:120px;height:120px;right:-30px;top:-30px;background:var(--peach)"></span></div>
  <div class="authbox">
    <?php if ($mode === 'new'): ?>
      <p class="k"><?= $L('Willkommen', 'Welcome') ?></p>
      <h1 class="h"><?= $L('Schön, dass du da bist', 'So lovely that you are here') ?>, <em><?= $h(forma_first_name((string)$start['name'])) ?></em>.</h1>
      <p class="mut"><?= $L('Deine Zahlung ist angekommen. Leg jetzt einmal dein Passwort fest, dann bist du sofort drin. Deine E-Mail:', 'Your payment arrived. Choose your password once and you are right in. Your email:') ?> <b><?= $h((string)$start['email']) ?></b></p>
      <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
      <form method="post" class="stack"><input type="hidden" name="a" value="setpw"><input type="hidden" name="lang" value="<?= $lang ?>"><input type="hidden" name="s" value="<?= (int)$start['id'] ?>"><input type="hidden" name="k" value="<?= $h((string)$start['token']) ?>">
        <div class="field"><label for="pw"><?= $L('Passwort (mind. 8 Zeichen)', 'Password (at least 8 characters)') ?></label><input id="pw" type="password" name="pw" autocomplete="new-password" required minlength="8"></div>
        <div class="field"><label for="pw2"><?= $L('Passwort wiederholen', 'Repeat password') ?></label><input id="pw2" type="password" name="pw2" autocomplete="new-password" required minlength="8"></div>
        <button class="btn" type="submit"><?= $L('Los geht’s', 'Let’s begin') ?></button>
      </form>
    <?php elseif ($mode === 'wait'): ?>
      <p class="k"><?= $L('Fast geschafft', 'Almost there') ?></p>
      <h1 class="h"><?= $L('Deine Zahlung wird gerade bestätigt', 'Your payment is being confirmed') ?></h1>
      <p class="mut"><?= $L('Das dauert meist nur einen Moment. Diese Seite lädt sich gleich neu. Du bekommst außerdem eine E-Mail mit deinem Zugang.', 'This usually takes just a moment. This page reloads itself. You will also get an email with your access.') ?></p>
      <script>setTimeout(()=>location.reload(),4000)</script>
    <?php elseif ($mode === 'reset'): ?>
      <p class="k"><?= $L('Passwort', 'Password') ?></p>
      <h1 class="h"><?= $resetM ? $L('Wähl dein Passwort', 'Choose your password') : $L('Link abgelaufen', 'Link expired') ?></h1>
      <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
      <?php if ($resetM): ?>
      <p class="mut"><?= $L('Für', 'For') ?> <b><?= $h((string)$resetM['email']) ?></b></p>
      <form method="post" class="stack"><input type="hidden" name="a" value="setpw"><input type="hidden" name="lang" value="<?= $lang ?>"><input type="hidden" name="t" value="<?= $h($resetTok) ?>">
        <div class="field"><label for="pw"><?= $L('Passwort (mind. 8 Zeichen)', 'Password (at least 8 characters)') ?></label><input id="pw" type="password" name="pw" autocomplete="new-password" required minlength="8"></div>
        <div class="field"><label for="pw2"><?= $L('Passwort wiederholen', 'Repeat password') ?></label><input id="pw2" type="password" name="pw2" autocomplete="new-password" required minlength="8"></div>
        <button class="btn" type="submit"><?= $L('Speichern und anmelden', 'Save and log in') ?></button>
      </form>
      <?php else: ?><p class="mut"><?= $L('Fordere einfach einen neuen Link an.', 'Simply request a new link.') ?></p><p><a class="btn line" href="?vergessen=1&amp;lang=<?= $lang ?>"><?= $L('Neuen Link anfordern', 'Request a new link') ?></a></p><?php endif; ?>
    <?php elseif ($mode === 'forgot'): ?>
      <p class="k"><?= $L('Passwort vergessen', 'Forgot password') ?></p>
      <h1 class="h"><?= $L('Kein Problem', 'No problem') ?></h1>
      <?php if ($note): ?><p class="note"><?= $h($note) ?></p><?php endif; ?>
      <p class="mut"><?= $L('Gib deine E-Mail ein, du bekommst einen Link für ein neues Passwort.', 'Enter your email and you will get a link to set a new password.') ?></p>
      <form method="post" class="stack"><input type="hidden" name="a" value="reset"><input type="hidden" name="lang" value="<?= $lang ?>"><input class="sr" name="website" tabindex="-1" autocomplete="off">
        <div class="field"><label for="em">E-Mail</label><input id="em" type="email" name="email" autocomplete="email" required></div>
        <button class="btn" type="submit"><?= $L('Link schicken', 'Send link') ?></button>
      </form>
      <p><a href="?lang=<?= $lang ?>"><?= $L('Zurück zur Anmeldung', 'Back to login') ?></a></p>
    <?php else: ?>
      <p class="k"><?= $L('Mitgliederbereich', 'Members area') ?></p>
      <h1 class="h"><?= isset($_GET['bye']) ? $L('Bis bald', 'See you soon') : ($start ? $L('Willkommen zurück', 'Welcome back') : $L('Schön, dass du da bist', 'Lovely to see you')) ?></h1>
      <p class="mut"><?= $start ? $L('Deine neue Buchung ist in deinem Konto. Melde dich einfach an.', 'Your new booking is in your account. Simply log in.') : $L('Melde dich mit deiner E-Mail und deinem Passwort an.', 'Log in with your email and password.') ?></p>
      <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
      <form method="post" class="stack"><input type="hidden" name="a" value="login"><input type="hidden" name="lang" value="<?= $lang ?>"><input class="sr" name="website" tabindex="-1" autocomplete="off">
        <div class="field"><label for="em">E-Mail</label><input id="em" type="email" name="email" autocomplete="email" required value="<?= $h($startMember['email'] ?? '') ?>"></div>
        <div class="field"><label for="pw"><?= $L('Passwort', 'Password') ?></label><input id="pw" type="password" name="pw" autocomplete="current-password" required></div>
        <button class="btn" type="submit"><?= $L('Anmelden', 'Log in') ?></button>
      </form>
      <p class="small"><a href="?vergessen=1&amp;lang=<?= $lang ?>"><?= $L('Passwort vergessen?', 'Forgot your password?') ?></a> · <a href="/method/<?= $lang === 'en' ? '?lang=en' : '' ?>"><?= $L('Noch kein Mitglied? Zur Method', 'Not a member yet? See the Method') ?></a></p>
    <?php endif; ?>
  </div>
</div></main>
</body></html>
<?php
    exit;
}

// --- Daten (echtes Konto oder Beispielansicht) -------------------------------------------------------
if ($demo) {
    if ($lang === '') $lang = 'de';
    $me = ['id' => 0, 'name' => 'Anna Beispiel', 'email' => 'anna@example.com', 'lang' => $lang];
    $acc = ['method' => !isset($_GET['nurunfold']), 'ulock' => false, 'starter' => isset($_GET['starter']), 'unfold' => isset($_GET['unfold']) || isset($_GET['nurunfold']) ? ['call_lang' => 'de', 'plan' => 'u1'] : null, 'until' => strtotime('+1 month'), 'sub' => ['plan' => 'm1', 'period_end' => strtotime('+20 days'), 'bill_start' => 0, 'cancel_end' => 0, 'amount_cents' => 2900, 'kind' => 'method'], 'subs' => []];
    $done = []; $favs = []; $week = 3;
    if (isset($_GET['warte'])) { $ps = ['kind' => 'unfold', 'paid_at' => date('c'), 'bill_start' => 0]; $acc = ['method' => false, 'starter' => false, 'unfold' => null, 'until' => 0, 'sub' => null, 'subs' => [], 'pending' => [$ps], 'open_at' => mv_open_at($ps)]; }
} else {
    $acc = mv_access($pdo, (string)$me['email']);
    $done = mv_done_ids($pdo, (int)$me['id']);
    $favs = mv_fav_ids($pdo, (int)$me['id']);
    $week = mv_week_days($pdo, (int)$me['id']);
}
$L = fn(string $de, string $en) => ($lang === 'en' ? $en : $de);
$first = forma_first_name((string)$me['name'] ?: 'du');
$tz = new DateTimeZone('Europe/Vienna');
$now = new DateTimeImmutable('now', $tz);
$hour = (int)$now->format('G');
$hello = $hour < 4 ? $L('Guten Abend', 'Good evening') : ($hour < 11 ? $L('Guten Morgen', 'Good morning') : ($hour < 17 ? $L('Hallo', 'Hello') : $L('Guten Abend', 'Good evening')));
$tab = in_array($_GET['tab'] ?? '', ['bibliothek', 'journal', 'unfold', 'konto'], true) ? $_GET['tab'] : 'bibliothek';
// Unfold ist ein eigenes Programm: ohne Method gibt es keine Bibliothek, dann startet die Seite bei Unfold
if (!$acc['method'] && $acc['unfold'] && $tab === 'bibliothek') $tab = 'unfold';
$launch = mv_launch();
$prelaunch = !mv_launched();
$c = mv_cfg();
$q = $demo ? '&demo=1' . (isset($_GET['unfold']) ? '&unfold=1' : '') . (isset($_GET['nurunfold']) ? '&nurunfold=1' : '') . (isset($_GET['starter']) ? '&starter=1' : '') . ($lang === 'en' ? '&lang=en' : '') : '';

// Programme + Lektionen, die sie sehen darf (Beispielansicht mit Unfold zeigt die zweite Unfold-Woche)
$today = $demo && (isset($_GET['unfold']) || isset($_GET['nurunfold'])) ? date('Y-m-d', mv_ts($c['u_start']) + 11 * 86400) : $now->format('Y-m-d');
// Testzugang vor dem echten Unfold-Start: zeigt Woche 2, damit Vivien Videos und Workbooks sieht
$isTest = !$demo && $me && array_filter($acc['subs'] ?? [], fn($s) => $s['source'] === 'test');
if ($isTest && $acc['unfold'] && $today < $c['u_start']) $today = date('Y-m-d', mv_ts($c['u_start']) + 11 * 86400);
$progs = [];
$all = [];
if ($acc['method']) {
    foreach (mv_programs($pdo, 'method', true) as $p) {
        $lock = $acc['starter'] && $p['theme'] !== 'ankommen';
        $ls = array_values(array_filter(mv_lessons($pdo, (int)$p['id'], true), fn($l) => $l['unlock'] === '' || $l['unlock'] <= $today));
        $progs[] = ['p' => $p, 'lessons' => $ls, 'lock' => $lock, 'files' => mv_files($pdo, (int)$p['id'])];
        foreach ($ls as $l) $all[(int)$l['id']] = ['l' => $l, 'p' => $p, 'lock' => $lock];
    }
}
$uProgs = [];
if ($acc['unfold']) {
    foreach (mv_programs($pdo, 'unfold', false) as $p) {
        if (!(int)$p['visible']) continue;
        $open = $p['unlock'] === '' || $p['unlock'] <= $today;
        // alle Videos des Monats sichtbar, noch nicht erschienene mit Datum und gesperrt
        $ls = mv_lessons($pdo, (int)$p['id'], true);
        $uProgs[] = ['p' => $p, 'lessons' => $ls, 'open' => $open, 'files' => $open ? mv_files($pdo, (int)$p['id']) : []];
        foreach ($ls as $l) if ($l['unlock'] === '' || $l['unlock'] <= $today) $all[(int)$l['id']] = ['l' => $l, 'p' => $p, 'lock' => false];
    }
}

// Einzelne Lektion
$lesson = null;
if (isset($_GET['l']) && isset($all[(int)$_GET['l']])) $lesson = $all[(int)$_GET['l']];

$fmtMin = fn(int $m) => $m > 0 ? $m . ' Min.' : '';
$card = function (array $l, array $p, bool $lock) use ($lang, $done, $favs, $q, $h, $L, $today): string {
    $future = $l['unlock'] !== '' && $l['unlock'] > $today;
    $lockLabel = $future ? $L('ab ', 'from ') . mv_date(strtotime($l['unlock'] . ' 12:00'), $lang) : $L('ab Tag 15', 'from day 15');
    $lock = $lock || $future;
    $vid = mv_video_for($l, $lang);
    $th = mv_thumb($vid);
    $cat = MV_CATS[$l['cat']] ?? MV_CATS['flow'];
    $en = MV_ENERGY[$l['energy']] ?? MV_ENERGY['any'];
    $isDone = in_array((int)$l['id'], $done, true);
    $min = (int)$l['minutes'];
    $bucket = $min === 0 ? '' : ($min <= 7 ? 's' : ($min <= 20 ? 'm' : 'l'));
    return '<a class="lc c-' . $h($l['cat']) . ($lock ? ' locked' : '') . '" href="' . ($lock ? '#' : '?l=' . (int)$l['id'] . $q) . '" data-cat="' . $h($l['cat']) . '" data-energy="' . $h($l['energy']) . '" data-len="' . $bucket . '" data-fav="' . (in_array((int)$l['id'], $favs, true) ? 1 : 0) . '">'
        . '<span class="th">' . ($th ? '<img src="' . $h($th) . '" alt="" loading="lazy">' : '<span class="ic">' . mv_icon($l['cat']) . '</span>')
        . ($min ? '<span class="min">' . $min . ' Min.</span>' : '') . ($isDone ? '<span class="dn" title="' . $L('schon gemacht', 'done') . '">✓</span>' : '') . ($lock ? '<span class="lk">' . $h($lockLabel) . '</span>' : '') . (!$vid && !$lock ? '<span class="soon">' . $L('Video folgt', 'Video coming') . '</span>' : '') . '</span>'
        . '<span class="tx"><span class="cat">' . $h($cat[$lang === 'en' ? 1 : 0]) . ($l['energy'] !== 'any' ? ' · ' . $h($en[$lang === 'en' ? 1 : 0]) : '') . '</span><b>' . $h(mv_t($l, 'title', $lang)) . '</b></span></a>';
};

mv_head($L('Mitgliederbereich', 'Members area'), $lang);
?>
<body class="app">
<header class="w bar"><div class="in top">
  <a class="brand" href="<?= $self . ($demo ? '?demo=1' : '') ?>"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia<i></i></span></a>
  <nav class="tabs" aria-label="<?= $L('Bereiche', 'Sections') ?>">
    <?php if ($acc['method']): ?><a href="?tab=bibliothek<?= $q ?>" <?= $tab === 'bibliothek' && !$lesson ? 'aria-current="page"' : '' ?>><?= $L('Bibliothek', 'Library') ?></a><?php endif; ?>
    <?php if ($acc['unfold']): ?><a href="?tab=unfold<?= $q ?>" <?= $tab === 'unfold' ? 'aria-current="page"' : '' ?>>Unfold</a><?php endif; ?>
    <a href="?tab=journal<?= $q ?>" <?= $tab === 'journal' ? 'aria-current="page"' : '' ?>>Journal</a>
    <a href="?tab=konto<?= $q ?>" <?= $tab === 'konto' ? 'aria-current="page"' : '' ?>><?= $L('Konto', 'Account') ?></a>
  </nav>
  <?= $demo ? '<div class="lang"><a class="lb" href="?demo=1&amp;lang=de" aria-pressed="' . ($lang !== 'en' ? 'true' : 'false') . '">DE</a><a class="lb" href="?demo=1&amp;lang=en" aria-pressed="' . ($lang === 'en' ? 'true' : 'false') . '">EN</a></div>' : mv_lang_switch($lang, '?' . http_build_query(array_intersect_key($_GET, ['tab' => 1, 'l' => 1])), true) ?>
</div></header>
<?php if (!empty($isTest)): ?><p class="demo w"><?= $L('Testzugang · so sieht es eine Teilnehmerin', 'Test access · this is what a participant sees') ?><?= $acc['unfold'] && $now->format('Y-m-d') < $c['u_start'] ? $L(' (Unfold als Woche 2)', ' (Unfold shown as week 2)') : '' ?></p><?php endif; ?>
<?php if ($demo): ?><p class="demo w"><?= $L('Beispielansicht ohne echtes Konto.', 'Example view without a real account.') ?> <a href="?demo=1&amp;starter=1<?= $lang === 'en' ? '&amp;lang=en' : '' ?>"><?= $L('Widerrufsfrist', 'Withdrawal period') ?></a> · <a href="?demo=1&amp;unfold=1<?= $lang === 'en' ? '&amp;lang=en' : '' ?>"><?= $L('Method + Unfold', 'Method + Unfold') ?></a> · <a href="?demo=1&amp;nurunfold=1<?= $lang === 'en' ? '&amp;lang=en' : '' ?>"><?= $L('nur Unfold', 'Unfold only') ?></a> · <a href="?demo=1&amp;warte=1<?= $lang === 'en' ? '&amp;lang=en' : '' ?>"><?= $L('vor dem Start', 'before the start') ?></a></p><?php endif; ?>

<main class="w"><div class="in">
<?php if (!$acc['method'] && !$acc['unfold'] && !empty($acc['pending'])): $oa = (int)$acc['open_at']; ?>
  <section class="hello"><div>
    <p class="k"><?= $h($hello) ?>, <?= $h($first) ?></p>
    <h1 class="h"><?= $L('Schön, dass du da bist.', 'Lovely that you are here.') ?></h1>
    <p class="mut lead"><?= $L('Gerade bereite ich noch alles für dich vor. Dein Zugang schaltet sich am ', 'I am still preparing everything for you. Your access unlocks on ') . '<strong>' . $h(mv_date($oa, $lang)) . '</strong>' . $L(' frei, dann bekommst du eine E-Mail und alles ist für dich offen.', '. You will get an email that day, and everything will be open for you.') ?></p>
    <ul class="mut" style="margin-top:1rem;padding-left:1.1rem"><?php foreach ($acc['pending'] as $ps): ?><li><?= $ps['kind'] === 'unfold' ? 'Unfold – The Mindful Becoming' : 'Mindful Movement Method' ?> · <?= $L('Start', 'Start') ?> <?= $h(mv_date(mv_open_at($ps), $lang)) ?> · <?= $L('Rücktritt möglich bis', 'Withdrawal possible until') ?> <?= $h(mv_date(mv_withdraw_until($ps), $lang)) ?></li><?php endforeach; ?></ul>
  </div>
  <div class="soon card"><p class="k"><?= $L('Dein Start', 'Your start') ?></p><h3><?= $h(mv_date($oa, $lang)) ?></h3><p class="count" data-t="<?= $oa ?>"></p></div></section>

<?php elseif (!$acc['method'] && !$acc['unfold']): ?>
  <section class="hello"><p class="k"><?= $h($hello) ?>, <?= $h($first) ?></p><h1 class="h"><?= $L('Deine Mitgliedschaft ist beendet', 'Your membership has ended') ?></h1>
  <p class="mut lead"><?= $L('Schön, dass du da warst. Wenn du wieder einsteigen magst, ist die Bibliothek mit allen Monaten für dich bereit.', 'Lovely that you were here. If you would like to come back, the library with all months is ready for you.') ?></p>
  <p style="margin-top:1.4rem"><a class="btn" href="/method/<?= $lang === 'en' ? '?lang=en' : '' ?>"><?= $L('Wieder einsteigen', 'Come back') ?></a></p></section>

<?php elseif ($lesson): $l = $lesson['l']; $p = $lesson['p']; $vid = mv_video_for($l, $lang); $isFav = in_array((int)$l['id'], $favs, true);
    $sib = array_values(array_filter($all, fn($x) => (int)$x['p']['id'] === (int)$p['id'])); $next = null;
    foreach ($sib as $i => $x) if ((int)$x['l']['id'] === (int)$l['id']) $next = $sib[$i + 1] ?? null; ?>
  <p class="back"><a href="?tab=<?= $p['audience'] === 'unfold' ? 'unfold' : 'bibliothek' ?><?= $q ?>">← <?= $h(mv_t($p, 'title', $lang)) ?></a></p>
  <section class="lesson">
    <div class="player"><?php if ($lesson['lock']): ?><div class="ph"><p><?= $L('Dieses Video öffnet sich 14 Tage nach deiner Buchung.', 'This video opens 14 days after your booking.') ?></p></div>
      <?php elseif ($vid && mv_bunny()['lib'] !== ''): ?><iframe src="<?= $h(mv_embed($vid)) ?>" loading="lazy" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;fullscreen" allowfullscreen title="<?= $h(mv_t($l, 'title', $lang)) ?>"></iframe>
      <?php else: ?><div class="ph"><span class="ic"><?= mv_icon($l['cat']) ?></span><p><?= $L('Dieses Video kommt bald. Schau gern bald wieder vorbei.', 'This video is coming soon. Come back soon.') ?></p></div><?php endif; ?></div>
    <div class="ltext">
      <p class="k"><?= $h((MV_CATS[$l['cat']] ?? MV_CATS['flow'])[$lang === 'en' ? 1 : 0]) ?><?= (int)$l['minutes'] ? ' · ' . (int)$l['minutes'] . ' Min.' : '' ?><?= $l['energy'] !== 'any' ? ' · ' . $h(MV_ENERGY[$l['energy']][$lang === 'en' ? 1 : 0]) : '' ?></p>
      <h1 class="h2"><?= $h(mv_t($l, 'title', $lang)) ?></h1>
      <?php if (mv_t($l, 'text', $lang) !== ''): ?><p class="lead"><?= nl2br($h(mv_t($l, 'text', $lang))) ?></p><?php endif; ?>
      <?php if ($lang === 'en' && $l['video_en'] === '' && $l['video_de'] !== ''): ?><p class="mut small">This video is in German for now, the English version is coming.</p><?php elseif ($lang === 'de' && $l['video_de'] === '' && $l['video_en'] !== ''): ?><p class="mut small">Dieses Video gibt es gerade nur auf Englisch, die deutsche Fassung kommt.</p><?php endif; ?>
      <?php if (!$lesson['lock']): ?>
      <div class="acts">
        <form method="post" data-ajax="done"><input type="hidden" name="a" value="done"><input type="hidden" name="l" value="<?= (int)$l['id'] ?>"><button class="btn" type="submit" <?= $demo ? 'disabled' : '' ?>><?= isset($_GET['ok']) ? $L('Schön, du warst bei dir ✓', 'Lovely, you showed up ✓') : $L('Ich war heute bei mir ✓', 'I showed up today ✓') ?></button></form>
        <form method="post" data-ajax="fav"><input type="hidden" name="a" value="fav"><input type="hidden" name="l" value="<?= (int)$l['id'] ?>"><button class="btn line fav" type="submit" aria-pressed="<?= $isFav ? 'true' : 'false' ?>" <?= $demo ? 'disabled' : '' ?>>♡ <span><?= $isFav ? $L('Gemerkt', 'Saved') : $L('Merken', 'Save') ?></span></button></form>
      </div>
      <?php endif; ?>
      <?php if ($next): ?><a class="nextl" href="?l=<?= (int)$next['l']['id'] . $q ?>"><span class="mut small"><?= $L('Als Nächstes', 'Up next') ?></span><b><?= $h(mv_t($next['l'], 'title', $lang)) ?></b></a><?php endif; ?>
    </div>
  </section>

<?php elseif ($tab === 'journal'):
    $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['day'] ?? '')) && $_GET['day'] <= $now->format('Y-m-d') ? (string)$_GET['day'] : $now->format('Y-m-d');
    $txt = $demo ? '' : mv_journal_get($pdo, (int)$me['id'], $day);
    $days = $demo ? [] : mv_journal_days($pdo, (int)$me['id']); ?>
  <section class="journal">
    <div>
      <p class="k">Journal · <?= $h(mv_date(strtotime($day . ' 12:00'), $lang)) ?></p>
      <h1 class="h2"><?= $h(mv_prompt($day, $lang)) ?></h1>
      <form method="post" class="jform" data-ajax="journal"><input type="hidden" name="a" value="journal"><input type="hidden" name="day" value="<?= $h($day) ?>">
        <textarea name="text" rows="12" placeholder="<?= $h($L('Schreib einfach los. Niemand außer dir liest das, auch ich nicht.', 'Simply start writing. Nobody but you reads this, not even me.')) ?>" <?= $demo ? 'disabled' : '' ?>><?= $h($txt) ?></textarea>
        <div class="jrow"><span class="mut small saved" aria-live="polite"></span><button class="btn sm" type="submit" <?= $demo ? 'disabled' : '' ?>><?= $L('Speichern', 'Save') ?></button></div>
      </form>
      <p class="mut small">🔒 <?= $L('Dein Journal ist privat und verschlüsselt gespeichert. Es erscheint nirgends in meiner Verwaltung.', 'Your journal is private and stored encrypted. It never shows up in my admin area.') ?></p>
    </div>
    <aside class="jdays card"><h3><?= $L('Deine Tage', 'Your days') ?></h3>
      <?php if (!$days): ?><p class="mut small"><?= $L('Hier sammeln sich deine Einträge.', 'Your entries will gather here.') ?></p><?php endif; ?>
      <ul><?php if ($day !== $now->format('Y-m-d')): ?><li><a href="?tab=journal<?= $q ?>"><?= $L('Heute', 'Today') ?></a></li><?php endif; ?>
      <?php foreach ($days as $d): ?><li><a href="?tab=journal&amp;day=<?= $h($d) . $q ?>" <?= $d === $day ? 'aria-current="true"' : '' ?>><?= $h(mv_date(strtotime($d . ' 12:00'), $lang)) ?></a></li><?php endforeach; ?></ul>
    </aside>
  </section>

<?php elseif ($tab === 'konto'):
    $s = $acc['sub']; $u = $acc['unfold']; ?>
  <section class="konto">
    <p class="k"><?= $L('Konto', 'Account') ?></p><h1 class="h2"><?= $h((string)$me['name']) ?></h1><p class="mut"><?= $h((string)$me['email']) ?></p>
    <?php if (isset($_GET['gekuendigt'])): ?><p class="note"><?= $L('Deine Mitgliedschaft ist gekündigt. Du hast bis zum Ende des bezahlten Zeitraums vollen Zugang. Eine Bestätigung ist per E-Mail unterwegs.', 'Your membership is cancelled. You keep full access until the end of the paid period. A confirmation is on its way by email.') ?></p><?php endif; ?>
    <?php if (isset($_GET['weiter'])): ?><p class="note"><?= $L('Wie schön, du bleibst! Deine Mitgliedschaft läuft weiter.', 'How lovely, you are staying! Your membership continues.') ?></p><?php endif; ?>
    <?php if (isset($_GET['fehler'])): ?><p class="err"><?= $L('Das hat gerade nicht geklappt. Bitte versuch es noch einmal oder schreib mir.', 'That did not work just now. Please try again or write to me.') ?></p><?php endif; ?>
    <div class="kgrid">
    <?php if ($s): $pl = mv_plans()[$s['plan']] ?? null; $end = max((int)$s['period_end'], (int)$s['bill_start']); ?>
      <div class="card"><h3>Mindful Movement Method</h3>
        <p><?= $h($pl ? ($lang === 'en' ? $pl['en'] : $pl['de']) : '') ?> · <?= mv_eur((int)$s['amount_cents']) ?> <?= $h($pl ? ($lang === 'en' ? $pl['per_en'] : $pl['per_de']) : '') ?></p>
        <?php if ((int)$s['bill_start'] > time()): ?><p class="mut"><?= $L('Start und erste Zahlung am', 'Start and first payment on') ?> <?= $h(mv_date((int)$s['bill_start'], $lang)) ?></p>
        <?php elseif ((int)$s['cancel_end']): ?><p class="mut"><?= $L('Gekündigt, Zugang bis', 'Cancelled, access until') ?> <?= $h(mv_date((int)$s['cancel_end'], $lang)) ?></p>
        <?php elseif ($end): ?><p class="mut"><?= $L('Verlängert sich am', 'Renews on') ?> <?= $h(mv_date($end, $lang)) ?></p><?php endif; ?>
        <?php if ((int)$s['cancel_end']): ?>
          <form method="post"><input type="hidden" name="a" value="resume"><button class="btn sm" type="submit" <?= $demo ? 'disabled' : '' ?>><?= $L('Doch weitermachen', 'Keep my membership') ?></button></form>
        <?php else: ?>
          <details class="cancel"><summary><?= $L('Mitgliedschaft kündigen', 'Cancel membership') ?></summary>
            <p class="mut small"><?= $L('Du behältst den Zugang bis zum Ende des bezahlten Zeitraums, danach wird nichts mehr abgebucht.', 'You keep access until the end of the paid period, nothing more will be charged.') ?></p>
            <form method="post"><input type="hidden" name="a" value="cancel"><button class="btn line sm" type="submit" <?= $demo ? 'disabled' : '' ?>><?= $L('Ja, kündigen', 'Yes, cancel') ?></button></form></details>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($u): ?>
      <div class="card"><h3>Unfold – The Mindful Becoming</h3><p><?= $L('Start', 'Start') ?>: <?= $h(mv_date(mv_ts($c['u_start']), $lang)) ?> · <?= $L('Calls auf', 'Calls in') ?> <?= $u['call_lang'] === 'en' ? $L('Englisch', 'English') : $L('Deutsch', 'German') ?></p><p class="mut"><?= $L('Begleitung bis', 'Programme until') ?> <?= $h(mv_date(mv_unfold_end(), $lang)) ?></p></div>
    <?php endif; ?>
      <div class="card"><h3><?= $L('Passwort ändern', 'Change password') ?></h3>
        <?php if (isset($_GET['pwok'])): ?><p class="note"><?= $L('Neues Passwort gespeichert.', 'New password saved.') ?></p><?php endif; ?>
        <?php if (isset($_GET['pwfehler'])): ?><p class="err"><?= $_GET['pwfehler'] === '1' ? $L('Das bisherige Passwort stimmt nicht.', 'Your current password is not right.') : $L('Bitte mindestens 8 Zeichen, beide gleich.', 'At least 8 characters, both the same please.') ?></p><?php endif; ?>
        <form method="post" class="stack"><input type="hidden" name="a" value="chpw">
          <input type="password" name="old" placeholder="<?= $h($L('Bisheriges Passwort', 'Current password')) ?>" autocomplete="current-password" required>
          <input type="password" name="pw" placeholder="<?= $h($L('Neues Passwort', 'New password')) ?>" autocomplete="new-password" minlength="8" required>
          <input type="password" name="pw2" placeholder="<?= $h($L('Neues Passwort wiederholen', 'Repeat new password')) ?>" autocomplete="new-password" minlength="8" required>
          <button class="btn sm" type="submit" <?= $demo ? 'disabled' : '' ?>><?= $L('Speichern', 'Save') ?></button></form>
      </div>
    </div>
    <p class="small mut" style="margin-top:1.4rem"><?= $L('Rechnungen bekommst du nach jeder Zahlung per E-Mail.', 'You receive an invoice by email after every payment.') ?> <a href="/terms/<?= $lang === 'en' ? '?lang=en' : '' ?>"><?= $L('Bedingungen', 'Terms') ?></a> · <a href="/withdraw/"><?= $L('Vertrag widerrufen', 'Withdraw from contract') ?></a></p>
    <form method="post" style="margin-top:1rem"><input type="hidden" name="a" value="logout"><button class="btn line sm" type="submit"><?= $L('Abmelden', 'Log out') ?></button></form>
  </section>

<?php elseif ($tab === 'unfold' && $acc['unfold']):
    $u = $acc['unfold']; $room = (string)((mm_cfg('method') ?? [])['u_room_' . ($u['call_lang'] === 'en' ? 'en' : 'de')] ?? '');
    $ustart = mv_ts($c['u_start'], '00:00'); $tnow = strtotime($today . ' 12:00');
    $wk = $tnow >= $ustart ? min(13, intdiv($tnow - $ustart, 7 * 86400) + 1) : 0; ?>
  <section class="uhero">
    <div><p class="k">Unfold · The Mindful Becoming</p><h1 class="h2"><?= $wk ? $L('Woche ', 'Week ') . $wk . $L(' von 13', ' of 13') : (time() < $ustart ? $L('Bald geht es los', 'We begin soon') : $L('Dein Weg in drei Monaten', 'Your path in three months')) ?></h1>
      <p class="lead"><?= !$wk ? $L('Wir starten am ', 'We begin on ') . $h(mv_date($ustart, $lang)) . '. ' . $L('Bis dahin kannst du dich hier schon in Ruhe umsehen.', 'Until then, feel free to look around.') : $L('Jede Woche treffen wir uns live, und jeden Montag und Donnerstag kommt ein neues Bewegungsvideo dazu. Hier findest du außerdem deine Workbooks und den Link zum Call.', 'Every week we meet live, and every Monday and Thursday a new movement video arrives. Here you also find your workbooks and the link to the call.') ?></p></div>
    <div class="ucall card"><p class="k"><?= $L('Dein Live-Call', 'Your live call') ?></p><h3><?= $h($u['call_lang'] === 'en' ? $c['u_call_en'] : $c['u_call_de']) ?></h3><p class="mut small"><?= $u['call_lang'] === 'en' ? $L('auf Englisch', 'in English') : $L('auf Deutsch', 'in German') ?></p>
      <?php if ($room !== '' && $tnow >= $ustart - 86400): ?><a class="btn" href="/unfold/call/" target="_blank" rel="noopener"><?= $L('Zum Call', 'Join the call') ?></a><?php else: ?><p class="mut small"><?= $L('Der Link zum Call erscheint hier vor dem Start.', 'The link to the call appears here before we start.') ?></p><?php endif; ?></div>
  </section>
  <?php foreach ($uProgs as $x): $p = $x['p']; ?>
  <section class="prog">
    <div class="phead"><div><p class="k"><?= $x['open'] ? $L('Offen', 'Open') : $L('Ab', 'From') . ' ' . $h(mv_date(strtotime($p['unlock'] . ' 12:00'), $lang)) ?></p><h2 class="h3"><?= $h(mv_t($p, 'title', $lang)) ?></h2><p class="mut"><?= $h(mv_t($p, 'intro', $lang)) ?></p></div>
      <?php if ($x['files'] && !empty($acc['ulock'])): ?><div class="files"><span class="chip" style="opacity:.6">🔒 <?= $L('Workbook ab ', 'Workbook from ') . $h(mv_date(mv_withdraw_until($acc['unfold']) + 60, $lang)) ?></span></div>
      <?php elseif ($x['files']): ?><div class="files"><?php foreach ($x['files'] as $f) if ($f['lang'] === 'both' || $f['lang'] === $lang): ?><a class="chip" href="/api/method.php?file=<?= (int)$f['id'] ?>" target="_blank">⤓ <?= $h(mv_t($f, 'label', $lang)) ?></a><?php endif; ?></div><?php endif; ?></div>
    <?php if ($x['lessons']): ?><div class="row"><?php foreach ($x['lessons'] as $l) echo $card($l, $p, false); ?></div><?php endif; ?>
  </section>
  <?php endforeach; ?>

<?php else: /* Bibliothek */
    $cur = null; foreach ($progs as $x) if ($x['p']['unlock'] !== '' && !$x['lock']) { $cur = $x; break; } ?>
  <section class="hello">
    <div>
      <p class="k"><?= $h($hello) ?>, <?= $h($first) ?></p>
      <h1 class="h"><?= $week > 0 ? sprintf($L('Diese Woche warst du %s bei dir.', 'This week you showed up for yourself %s.'), '<em>' . $week . '×</em>') : $L('Schön, dass du da bist.', 'Lovely that you are here.') ?></h1>
      <div class="week" aria-label="<?= $h($L('Tage diese Woche', 'Days this week')) ?>"><?php $dow = (int)$now->format('N'); for ($i = 1; $i <= 7; $i++): ?><span class="<?= $i <= $week ? 'on' : '' ?><?= $i === $dow ? ' today' : '' ?>"><?= ($lang === 'en' ? ['', 'M', 'T', 'W', 'T', 'F', 'S', 'S'] : ['', 'M', 'D', 'M', 'D', 'F', 'S', 'S'])[$i] ?></span><?php endfor; ?></div>
      <p class="mut small"><?= $L('Kein Streak, kein Druck. Jeder Tag, an dem du dir Zeit schenkst, zählt.', 'No streaks, no pressure. Every day you give yourself time counts.') ?></p>
    </div>
    <?php if ($prelaunch): ?><div class="soon card"><p class="k"><?= $L('Bald', 'Soon') ?></p><h3><?= $L('Die Method öffnet am', 'The Method opens on') ?> <?= $h(mv_date($launch, $lang)) ?></h3><p class="mut small"><?= $L('Bis dahin kannst du mit dem Ankommen-Modul beginnen.', 'Until then you can begin with the welcome module.') ?></p><p class="count" data-t="<?= $launch ?>"></p></div>
    <?php elseif ($cur): ?><a class="soon card cur" href="#p<?= (int)$cur['p']['id'] ?>"><p class="k"><?= $L('Programm des Monats', 'Programme of the month') ?></p><h3><?= $h(mv_t($cur['p'], 'title', $lang)) ?></h3><p class="mut small"><?= $h(mb_strimwidth(mv_t($cur['p'], 'intro', $lang), 0, 150, '…')) ?></p></a><?php endif; ?>
  </section>
  <?php if ($acc['starter']): ?><p class="note"><?= $L('Du bist in den ersten 14 Tagen nach deiner Buchung. Bis dahin ist das Ankommen-Modul für dich offen, danach öffnet sich die ganze Bibliothek von selbst.', 'You are in the first 14 days after your booking. Until then the welcome module is open for you, after that the whole library opens by itself.') ?></p><?php endif; ?>

  <div class="filters" role="toolbar" aria-label="Filter">
    <div class="fg"><span class="fl"><?= $L('Wie viel Zeit hast du?', 'How much time do you have?') ?></span>
      <button class="chip" data-f="len" data-v="" aria-pressed="true"><?= $L('egal', 'any') ?></button><button class="chip" data-f="len" data-v="s" aria-pressed="false">≤ 5–7 Min.</button><button class="chip" data-f="len" data-v="m" aria-pressed="false">~15 Min.</button><button class="chip" data-f="len" data-v="l" aria-pressed="false">30 Min.</button></div>
    <div class="fg"><span class="fl"><?= $L('Wie geht es dir heute?', 'How do you feel today?') ?></span>
      <button class="chip" data-f="energy" data-v="" aria-pressed="true"><?= $L('alles', 'all') ?></button><button class="chip" data-f="energy" data-v="tired" aria-pressed="false">Tired Day</button><button class="chip" data-f="energy" data-v="full" aria-pressed="false">Full Day</button></div>
    <div class="fg"><span class="fl"><?= $L('Was magst du?', 'What would you like?') ?></span>
      <button class="chip" data-f="cat" data-v="" aria-pressed="true"><?= $L('alles', 'all') ?></button><?php foreach (MV_CATS as $k => $n): ?><button class="chip" data-f="cat" data-v="<?= $k ?>" aria-pressed="false"><?= $h($n[$lang === 'en' ? 1 : 0]) ?></button><?php endforeach; ?><button class="chip" data-f="fav" data-v="1" aria-pressed="false">♡ <?= $L('Gemerkt', 'Saved') ?></button></div>
  </div>
  <p class="empty mut" hidden><?= $L('Dazu gibt es gerade nichts. Probier einen anderen Filter.', 'Nothing here right now. Try another filter.') ?></p>

  <?php foreach ($progs as $x): $p = $x['p']; ?>
  <section class="prog" id="p<?= (int)$p['id'] ?>">
    <div class="phead"><div><p class="k"><?= $p['unlock'] === '' ? $L('Zum Start', 'To begin') : $h(mv_date(strtotime($p['unlock'] . ' 12:00'), $lang)) ?><?= $x['lock'] ? ' · ' . $L('öffnet sich nach 14 Tagen', 'opens after 14 days') : '' ?></p><h2 class="h3"><?= $h(mv_t($p, 'title', $lang)) ?></h2><?php if (mv_t($p, 'intro', $lang) !== ''): ?><p class="mut pintro"><?= $h(mv_t($p, 'intro', $lang)) ?></p><?php endif; ?></div>
      <?php if ($x['files'] && !$x['lock']): ?><div class="files"><?php foreach ($x['files'] as $f) if ($f['lang'] === 'both' || $f['lang'] === $lang): ?><a class="chip" href="/api/method.php?file=<?= (int)$f['id'] ?>" target="_blank">⤓ <?= $h(mv_t($f, 'label', $lang)) ?></a><?php endif; ?></div><?php endif; ?></div>
    <div class="row"><?php foreach ($x['lessons'] as $l) echo $card($l, $p, $x['lock']); ?></div>
  </section>
  <?php endforeach; ?>
<?php endif; ?>
</div></main>
<footer class="f w"><div class="in"><span>Movecia · Vivien Malonga</span><span><a href="/"><?= $L('Startseite', 'Home') ?></a> · <a href="/terms/"><?= $L('Bedingungen', 'Terms') ?></a> · <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a></span></div></footer>
<script>
(function(){
  // Filter
  var st={len:'',energy:'',cat:'',fav:''};
  document.querySelectorAll('.filters .chip').forEach(function(b){b.addEventListener('click',function(){
    var f=b.dataset.f;if(f==='fav'){st.fav=st.fav?'':'1';b.setAttribute('aria-pressed',st.fav?'true':'false');}
    else{st[f]=b.dataset.v;document.querySelectorAll('.filters .chip[data-f="'+f+'"]').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false')});}
    var any=0;document.querySelectorAll('.prog').forEach(function(p){var n=0;p.querySelectorAll('.lc').forEach(function(c){
      var ok=(!st.len||c.dataset.len===st.len)&&(!st.energy||c.dataset.energy===st.energy||c.dataset.energy==='any')&&(!st.cat||c.dataset.cat===st.cat)&&(!st.fav||c.dataset.fav==='1');
      c.hidden=!ok;if(ok)n++;});p.hidden=n===0;any+=n;});
    var e=document.querySelector('.empty');if(e)e.hidden=any>0;
  });});
  // Kleine Formulare ohne Neuladen
  document.querySelectorAll('form[data-ajax]').forEach(function(f){f.addEventListener('submit',function(ev){ev.preventDefault();
    var d=new FormData(f);d.append('ajax','1');var k=f.dataset.ajax,b=f.querySelector('button');
    fetch(location.pathname,{method:'POST',body:d,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
      if(k==='done'){b.textContent=document.documentElement.dataset.l==='en'?'Lovely, you showed up ✓':'Schön, du warst bei dir ✓';}
      if(k==='fav'){b.setAttribute('aria-pressed',j.on?'true':'false');b.querySelector('span').textContent=j.on?(document.documentElement.dataset.l==='en'?'Saved':'Gemerkt'):(document.documentElement.dataset.l==='en'?'Save':'Merken');}
      if(k==='journal'){var s=f.querySelector('.saved');s.textContent=document.documentElement.dataset.l==='en'?'Saved':'Gespeichert';setTimeout(function(){s.textContent=''},2500);}
    }).catch(function(){f.submit();});});});
  // Journal speichert von selbst
  var ta=document.querySelector('.jform textarea'),tm;if(ta)ta.addEventListener('input',function(){clearTimeout(tm);tm=setTimeout(function(){ta.form.requestSubmit()},1500)});
  // Countdown bis zum Start
  var c=document.querySelector('.count');if(c){var t=+c.dataset.t*1000;var tick=function(){var s=Math.max(0,Math.floor((t-Date.now())/1000)),d=Math.floor(s/86400),h=Math.floor(s%86400/3600);c.textContent=(document.documentElement.dataset.l==='en'?d+' days · '+h+' hours':'noch '+d+' Tage · '+h+' Std.');};tick();setInterval(tick,60000);}
  document.querySelectorAll('.lc.locked').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault()})});
})();
</script>
</body>
</html>

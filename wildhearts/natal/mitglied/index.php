<?php
// moveciamovement.com/natal/mitglied/ – Mitgliederbereich Prä- & Postnatal (Vivien, 24.10.2026)
// Anmeldung mit E-Mail + Passwort. Pro Einheit: Workbook (Bildschirm + PDF), Aufnahme (nur Viviens Kamera), Übungsabfolge.
// Affirmation wechselt morgens/tagsüber/abends, Icons passend zu prä/post und Jahreszeit, Musik über Spotify.
// Erste 14 Tage (Widerrufsfrist): Begrüßung + Workbook-Vorschau (erstes Drittel), kein Download, keine Aufnahmen.
// ?demo=prae|post|vorschau zeigt eine Beispielseite ohne echte Daten (für Vivien).
declare(strict_types=1);
require dirname(__DIR__, 3) . '/api/natal-lib.php';
require_once dirname(__DIR__, 3) . '/api/natal-workbook.inc.php';
require_once dirname(__DIR__, 3) . '/api/method-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$pdo = forma_db();
natal_schema($pdo);
natal_bonus_tick($pdo);
$h = 'forma_h';
$self = '/natal/mitglied/';
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'member');
$go = function (string $q = '') use ($self) { header('Location: ' . $self . $q, true, 303); exit; };
$pwOk = fn(string $p) => mb_strlen($p) >= 8;

// --- Aktionen ---------------------------------------------------------------------------------
$err = '';
$note = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $a = (string)($_POST['a'] ?? '');
    if (!empty($_POST['website'])) $go();
    if ($a === 'logout') { mm_dev_logout($pdo); natal_member_logout(); $go('?bye=1'); }
    if ($a === 'login') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (natal_member_throttled($pdo, $ipHash)) $err = 'Zu viele Versuche. Bitte warte eine Viertelstunde oder setz dein Passwort neu.';
        else {
            $m = natal_member_by_email($pdo, $email);
            if ($m && password_verify((string)($_POST['pw'] ?? ''), (string)$m['pass_hash']) && natal_member_orders($pdo, $email)) { natal_member_login($m, $pdo); $go(); }
            natal_member_fail($pdo, $ipHash);
            $err = 'E-Mail oder Passwort stimmen nicht. Versuch es noch einmal oder setz dein Passwort neu.';
        }
    }
    if ($a === 'setpw') {
        $o = natal_order_auth($pdo, (int)($_POST['o'] ?? 0), (string)($_POST['k'] ?? ''));
        $pw = (string)($_POST['pw'] ?? '');
        if (!$o || $o['status'] !== 'bezahlt' || !natal_order_is_member($o) || $o['purged_at']) $err = 'Dieser Link funktioniert leider nicht mehr. Schreib mir einfach, dann helfe ich dir.';
        elseif (natal_member_by_email($pdo, (string)$o['email'])) $err = 'Für deine E-Mail gibt es schon ein Konto. Melde dich einfach an.';
        elseif (!$pwOk($pw)) $err = 'Bitte wähl ein Passwort mit mindestens 8 Zeichen.';
        elseif ($pw !== (string)($_POST['pw2'] ?? '')) $err = 'Die beiden Passwörter sind nicht gleich.';
        else { natal_member_login(natal_member_set_password($pdo, (string)$o['email'], (string)$o['name'], $pw), $pdo); $go('?willkommen=1'); }
    }
    if ($a === 'reset_req') {
        if (!natal_member_throttled($pdo, $ipHash)) { natal_member_fail($pdo, $ipHash); natal_member_send_reset($pdo, (string)($_POST['email'] ?? '')); }
        $note = 'Wenn es für diese E-Mail ein Konto gibt, ist der Link zum neuen Passwort jetzt unterwegs. Schau auch im Spam-Ordner nach.';
    }
    if ($a === 'reset_set') {
        $m = natal_member_by_reset($pdo, (string)($_POST['t'] ?? ''));
        $pw = (string)($_POST['pw'] ?? '');
        if (!$m) $err = 'Dieser Link ist abgelaufen. Fordere einfach einen neuen an.';
        elseif (!$pwOk($pw)) $err = 'Bitte wähl ein Passwort mit mindestens 8 Zeichen.';
        elseif ($pw !== (string)($_POST['pw2'] ?? '')) $err = 'Die beiden Passwörter sind nicht gleich.';
        else { natal_member_login(natal_member_set_password($pdo, (string)$m['email'], (string)$m['name'], $pw), $pdo); $go(); }
    }
}

// --- Daten für die Seite ----------------------------------------------------------------------
/** Videos einer Einheit für den Mitgliederbereich: Bunny-Embed (Deutsch, sonst Englisch) oder ''. */
function natal_member_videos(PDO $pdo, string $unit): array
{
    $out = ['talk' => '', 'move' => ''];
    try {
        mv_schema($pdo);
        foreach (mv_natal_videos($pdo, $unit) as $k => $l) {
            if (!$l) continue;
            // Bewegungsvideo aus Übungs-Clips: Player mit 30/45-Sek.-Timer
            if ($k === 'move' && mv_lesson_is_clips($l)) { $fd = mv_flow_data($l, 'de'); if ($fd) $out['move'] = 'flow:' . json_encode($fd, JSON_UNESCAPED_UNICODE); continue; }
            $g = (string)$l['video_de'] !== '' ? (string)$l['video_de'] : (string)$l['video_en'];
            if ($g !== '') $out[$k] = mv_embed($g);
        }
    } catch (Throwable $e) { error_log('natal videos: ' . $e->getMessage()); }
    return $out;
}
/** Ansicht einer Buchung für die Seite (echte Daten). */
function natal_member_view(PDO $pdo, array $o): array
{
    $full = natal_member_full($o);
    $plan = natal_order_plan($pdo, $o);
    $units = [];
    $done = 0; $next = null; $lastKey = '';
    $prev = natal_workbook_preview_count(count(natal_workbook_pages($plan[0][0] ?? 'prae1')));
    foreach ($plan as $i => [$k, $s]) {
        $u = natal_units()[$k];
        $isDone = natal_session_done($s);
        if ($isDone) { $done++; $lastKey = $k; }
        elseif ($s && $s['status'] === 'bestaetigt' && !$next) $next = $s;
        $state = $isDone ? 'done' : ($s ? ($s['status'] === 'bestaetigt' ? 'next' : 'planned') : 'later');
        $wb = 0; $pdf = ''; $rec = ''; $talk = ''; $move = '';
        if ($full && $isDone) {
            $wb = 99;
            // Talking Head („Vertiefung“) und Bewegungsvideo der Einheit (Vivien, 26.10.2026): erst nach dem Termin
            $vv = natal_member_videos($pdo, $k);
            $talk = $vv['talk']; $move = $vv['move'];
            $pdf = '/api/natal-workbook.php?o=' . (int)$o['id'] . '&u=' . $k;
            $r = $s ? natal_rec_for($pdo, (int)$s['id']) : null;
            if ($r) $rec = '/api/natal-rec.php?id=' . (int)$r['id'];
        } elseif (!$full && $i === 0) $wb = $prev;
        $units[] = ['key' => $k, 'nr' => $i + 1, 'label' => natal_unit_label($k), 'title' => $u['title'], 'theme' => $u['theme'], 'state' => $state,
            'when' => $s ? natal_when($s) : '', 'wb' => $wb, 'pdf' => $pdf, 'rec' => $rec, 'talk' => $talk, 'move' => $move];
    }
    $online = natal_is_online($o) || natal_is_mix($o);
    $room = $next && natal_sess_online($o, $next) ? natal_room_link('s', (int)$next['id']) : '';
    return ['id' => (int)$o['id'], 'title' => (string)$o['title'], 'phase' => natal_member_phase($o), 'full' => $full, 'fullFrom' => natal_member_full_from($o),
        'until' => natal_order_until($pdo, $o), 'finished' => natal_order_finished($pdo, $o), 'units' => $units, 'done' => $done, 'total' => count($units),
        'next' => $next ? natal_when($next) : '', 'room' => $room, 'online' => $online, 'link' => natal_link($o), 'home' => $lastKey];
}

/** Beispieldaten für Viviens Vorschau. */
function natal_member_demo(string $v): array
{
    $post = $v === 'post';
    $keys = $post ? NATAL_POST_KEYS : NATAL_PRAE_KEYS;
    $doneN = $v === 'vorschau' ? 0 : 2;
    $units = [];
    foreach ($keys as $i => $k) {
        $u = natal_units()[$k];
        $d = strtotime('-' . (14 - $i * 7) . ' days');
        $state = $i < $doneN ? 'done' : ($i === $doneN ? 'next' : ($i === $doneN + 1 ? 'planned' : 'later'));
        $when = $state === 'later' ? '' : natal_date_de(date('Y-m-d', $state === 'done' ? $d : strtotime('+' . (2 + ($i - $doneN) * 7) . ' days'))) . ($state === 'planned' ? ', Vormittag' : ' um 9:30 Uhr');
        $wb = $v === 'vorschau' ? ($i === 0 ? natal_workbook_preview_count(count(natal_workbook_pages($k))) : 0) : ($state === 'done' ? 99 : 0);
        $units[] = ['key' => $k, 'nr' => $i + 1, 'label' => natal_unit_label($k), 'title' => $u['title'], 'theme' => $u['theme'], 'state' => $state, 'when' => $when,
            'wb' => $wb, 'pdf' => $state === 'done' && $v !== 'vorschau' ? '#demo' : '', 'rec' => $state === 'done' && $v !== 'vorschau' ? '#demo' : '',
            'talk' => $state === 'done' && $v !== 'vorschau' ? '#demo' : '', 'move' => $state === 'done' && $v !== 'vorschau' ? '#demo' : ''];
    }
    return ['id' => 0, 'title' => $post ? 'Postnatal-Paket' : 'Pränatal-Paket', 'phase' => $post ? 'post' : 'prae', 'full' => $v !== 'vorschau', 'fullFrom' => strtotime('+12 days'),
        'until' => date('Y-m-d', strtotime('+5 months')), 'finished' => false, 'units' => $units, 'done' => $doneN, 'total' => count($units),
        'next' => $units[$doneN]['when'], 'room' => '#demo', 'online' => true, 'link' => '#demo', 'home' => $doneN ? $keys[$doneN - 1] : ''];
}

$demo = in_array($_GET['demo'] ?? '', ['prae', 'post', 'vorschau'], true) ? (string)$_GET['demo'] : '';
$me = $demo ? ['name' => 'Anna Beispiel', 'email' => 'anna@example.com'] : natal_member_current($pdo);
$views = [];
if ($demo) $views = [natal_member_demo($demo)];
elseif ($me) foreach (natal_member_orders($pdo, (string)$me['email']) as $o) $views[] = natal_member_view($pdo, $o);
if (!$me && !$demo && ($kt = mm_dev_kicked_text('de')) !== '') $err = $kt; // Zugang auf zu vielen Geräten
if ($me && !$demo && !$views) { natal_member_logout(); $me = null; $note = 'Dein Bereich ist abgelaufen. Danke, dass ich dich begleiten durfte!'; }

// Workbook-Fragment (per fetch)
if (isset($_GET['wb'])) {
    $key = (string)$_GET['wb'];
    $oid = (int)($_GET['o'] ?? 0);
    foreach ($views as $vw) {
        if ($vw['id'] !== $oid) continue;
        foreach ($vw['units'] as $u) {
            if ($u['key'] !== $key || $u['wb'] < 1) continue;
            $lock = $vw['full'] ? '' : 'Das ist eine Vorschau. Das ganze Workbook mit allen Übungen und dem Download ist ab ' . $h(natal_date_de(date('Y-m-d', $vw['fullFrom']), false)) . ' für dich da, nach deinen ersten 14 Tagen.';
            header('Content-Type: text/html; charset=utf-8');
            echo natal_workbook_html($key, $u['wb'], $lock);
            exit;
        }
    }
    http_response_code(404); exit('Nicht verfügbar.');
}

$startO = null;
if (!$me && isset($_GET['start'])) {
    $startO = natal_order_auth($pdo, (int)$_GET['start'], (string)($_GET['k'] ?? ''));
    if ($startO && ($startO['status'] !== 'bezahlt' || !natal_order_is_member($startO) || $startO['purged_at'])) $startO = null;
}
$resetM = !$me && isset($_GET['reset']) ? natal_member_by_reset($pdo, (string)$_GET['reset']) : null;
$v = $views[0] ?? null;
$phase = $v['phase'] ?? 'prae';
$hour = (int)date('G');
$greet = $hour < 11 ? 'Guten Morgen' : ($hour < 17 ? 'Hallo' : 'Guten Abend');
[$affLabel, $aff] = natal_affirmation($phase);
$icons = natal_icon_set($phase);
$lists = natal_spotify_lists() ?: [['title' => 'Ruhige Klaviermusik', 'url' => natal_spotify_embed('https://open.spotify.com/playlist/37i9dQZF1DX4sWSpwq3LiO')]];
$wa = 'https://wa.me/' . preg_replace('/\D/', '', NATAL_COMPANY['phone']);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Mein Bereich · Movecia</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#F5F1EB">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="manifest" href="/app.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Movecia"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="/wildhearts/assets/app.js?v=2" defer></script>
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--bg:#F5F1EB;--card:#FBF9F6;--ink:#2A2026;--muted:#6E6168;--line:#D3C9BC;--plum:#4A3040;--plumdeep:#3A2631;--deep:#2E1D27;--blush:#E4DCD1;--peach:#E6DBCF;--on:#F7F1EE;--r:24px;--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
*{box-sizing:border-box}[hidden]{display:none!important}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
@media(min-width:900px){body{font-size:19px}}
.w{padding-left:max(16px,env(safe-area-inset-left));padding-right:max(16px,env(safe-area-inset-right))}
@media(min-width:900px){.w{padding-left:56px;padding-right:56px}}
.in{max-width:1500px;margin:0 auto}
h1,h2,h3{font-family:var(--serif);font-weight:500;line-height:1.05;margin:0}
h1{font-size:clamp(2.6rem,7vw,5.4rem)}h2{font-size:clamp(2rem,4vw,3rem)}h3{font-size:1.5rem}
em{font-style:italic;color:var(--plum)}p{margin:0}a{color:var(--plum)}
.k{font-size:.72rem;letter-spacing:.2em;text-transform:uppercase;color:var(--plum);font-weight:500}
.mut{color:var(--muted)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5em;padding:.8em 1.4em;border-radius:999px;background:var(--plum);color:var(--on);font:inherit;font-size:.92rem;font-weight:500;text-decoration:none;border:1px solid var(--plum);cursor:pointer}
.btn.line{background:transparent;color:var(--plum)}.btn.sm{padding:.5em 1em;font-size:.84rem}
.top{display:flex;justify-content:space-between;align-items:center;padding-top:14px;padding-bottom:14px}
.brand{font-family:var(--serif);font-size:1.7rem;color:var(--ink);text-decoration:none;display:flex;align-items:center;gap:.4rem}
.brand img{width:30px;height:30px}.brand i{display:inline-block;width:.3em;height:.3em;border-radius:50%;background:var(--plum);margin-left:.05em}
.top form button{font:inherit;font-size:.85rem;background:none;border:0;color:var(--muted);cursor:pointer;text-decoration:underline}
/* Kopf mit Affirmation und schwebenden Icons */
.hero{display:grid;gap:1rem;align-items:center;padding-top:clamp(1rem,3vw,2.4rem);padding-bottom:clamp(1.6rem,4vw,3rem)}
@media(min-width:980px){.hero{grid-template-columns:1.3fr .7fr;gap:3rem}}
.icw{position:relative;height:clamp(200px,24vw,360px);order:-1}
@media(min-width:980px){.icw{order:0}}
.icw .bl{position:absolute;inset:8% 10%;background:var(--blush);border-radius:62% 38% 46% 54%/55% 45% 55% 45%;opacity:.7}
.icw svg{width:100%;height:auto;display:block}
@media(max-width:979px){.icw .fl{transform:scale(.7)}}
.hero .fl{position:absolute;pointer-events:none;animation:float 9s ease-in-out infinite}
@keyframes float{50%{transform:translateY(-12px) rotate(4deg)}}
@media(prefers-reduced-motion:reduce){.hero .fl{animation:none}}
.aff{position:relative;margin-top:1.4rem;background:var(--card);border-radius:var(--r);padding:clamp(1.2rem,3vw,2rem);max-width:760px;box-shadow:0 20px 40px -34px var(--deep)}
.aff p.t{font-family:var(--serif);font-style:italic;font-size:clamp(1.6rem,3.4vw,2.4rem);line-height:1.2;color:var(--plum);margin-top:.4rem}
.grid{display:grid;gap:16px}
@media(min-width:980px){.grid.two{grid-template-columns:1.15fr .85fr}}
.card{background:var(--card);border-radius:var(--r);padding:clamp(1.2rem,3vw,1.8rem)}
.card.dark{background:var(--deep);color:var(--on)}.card.dark .k{color:var(--peach)}.card.dark .mut{color:#D9C9CE}
.when{font-family:var(--serif);font-size:clamp(1.6rem,3vw,2.2rem);line-height:1.15;margin:.4rem 0 1rem}
.dots{display:flex;flex-wrap:wrap;gap:6px;margin:.8rem 0}.dots i{width:22px;height:22px;border-radius:50%;background:var(--line)}.dots i.d{background:var(--plum)}.dots i.n{background:var(--peach);box-shadow:inset 0 0 0 2px var(--plum)}
.welcome{background:var(--plum);color:var(--on);border-radius:calc(var(--r) + 6px);padding:clamp(1.4rem,4vw,2.4rem);position:relative;overflow:hidden}
.welcome .k{color:var(--peach)}.welcome h2{margin:.5rem 0 .8rem}.welcome p+p{margin-top:.8rem}
.welcome .hand{font-family:var(--serif);font-style:italic;font-size:1.6rem;margin-top:1rem}
.units{display:grid;gap:12px;margin-top:1.2rem}
@media(min-width:760px){.units{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1200px){.units{grid-template-columns:repeat(3,1fr)}}
.unit{background:var(--card);border-radius:20px;padding:1.2rem 1.3rem;display:flex;flex-direction:column;gap:.5rem;position:relative;overflow:hidden}
.unit .nr{font-family:var(--serif);font-size:3.4rem;line-height:.8;color:var(--blush);position:absolute;right:1rem;top:.8rem}
.unit h3{padding-right:3rem}.unit p.th{color:var(--muted);font-size:.92rem}
.unit .st{font-size:.82rem;color:var(--plum)}
.unit .acts{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:auto;padding-top:.6rem}
.unit.later,.unit.planned{opacity:.66}.unit.later h3::after{content:" 🔒";font-size:.8em}
.unit.done{box-shadow:inset 0 0 0 1.5px var(--peach)}
.homeex li{margin:.35rem 0}.homeex{padding-left:1.2rem;margin:.6rem 0 0}
.music iframe{width:100%;height:352px;border:0;border-radius:16px;display:block;margin-top:.8rem}
.tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.6rem}.tabs button{font:inherit;font-size:.84rem;border:1px solid var(--line);background:var(--bg);border-radius:999px;padding:.35em .9em;cursor:pointer;color:var(--ink)}.tabs button[aria-pressed=true]{background:var(--plum);color:var(--on);border-color:var(--plum)}
/* Fenster für Workbook und Aufnahme */
.sheet{position:fixed;inset:0;background:rgba(46,37,41,.55);z-index:50;display:flex;align-items:flex-end;justify-content:center}
@media(min-width:800px){.sheet{align-items:center}}
.sheet .box{background:var(--bg);width:min(820px,100%);max-height:94vh;overflow:auto;border-radius:26px 26px 0 0;padding:1rem clamp(1rem,3vw,1.6rem) 1.6rem}
@media(min-width:800px){.sheet .box{border-radius:26px}}
.sheet .bar{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:.8rem}
.sheet .x{font:inherit;font-size:1.6rem;background:none;border:0;color:var(--plum);cursor:pointer;line-height:1}
.sheet video{width:100%;border-radius:18px;background:var(--deep);display:block}
/* Anmeldung */
.auth{min-height:70vh;display:grid;place-items:center;padding-top:2rem;padding-bottom:3rem}
.auth .card{width:min(460px,100%);position:relative}
.f{display:flex;flex-direction:column;gap:.35rem;font-size:.86rem;color:var(--muted);margin-top:.8rem}
input[type=email],input[type=password]{font:inherit;font-size:1rem;color:var(--ink);background:var(--bg);border:1px solid transparent;border-radius:14px;padding:.8em 1em;width:100%}
input:focus{outline:2px solid var(--plum);outline-offset:1px}
.err{color:#A0463A;font-size:.92rem;margin-top:.8rem}.ok{background:var(--blush);border-radius:14px;padding:.8rem 1rem;margin-top:.8rem;font-size:.95rem}
.demo{background:var(--deep);color:var(--on);text-align:center;font-size:.85rem;padding:.5rem}
footer{padding-top:3rem;padding-bottom:2rem;font-size:.85rem;color:var(--muted);text-align:center}
</style>
<?= natal_workbook_assets() ?>
<script src="/wildhearts/assets/schutz.js?v=3"></script>
</head>
<body>
<?php if ($demo): ?><p class="demo">Vorschau mit Beispieldaten · <a style="color:var(--peach)" href="?demo=prae">pränatal</a> · <a style="color:var(--peach)" href="?demo=post">postnatal</a> · <a style="color:var(--peach)" href="?demo=vorschau">in den ersten 14 Tagen</a></p><?php endif; ?>
<header class="w"><div class="in top">
  <a class="brand" href="<?= $h($self) ?>"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia</span></a>
  <?php if ($me && !$demo): ?><form method="post"><input type="hidden" name="a" value="logout"><button type="submit">Abmelden</button></form><?php endif; ?>
</div></header>

<?php if (!$me): /* ---------------- Anmeldung ---------------- */ ?>
<main class="w"><div class="in auth">
  <div class="card">
    <span style="position:absolute;right:18px;top:-26px"><?= natal_icon('mond', 64) ?></span>
    <?php if ($startO): ?>
      <?php if (isset($_GET['danke'])): ?><p class="ok" style="margin:0 0 1rem">Danke! Deine Zahlung ist angekommen. Bestätigung und Rechnung kommen per E-Mail.</p><?php endif; ?>
      <p class="k">Dein Mitgliederbereich</p>
      <h2 style="margin-top:.5rem">Willkommen, <em><?= $h(forma_first_name((string)$startO['name'])) ?></em></h2>
      <p class="mut" style="margin-top:.6rem">Wähl dir jetzt ein Passwort. Damit meldest du dich ab heute mit deiner E-Mail-Adresse an und findest alles zu unseren Einheiten an einem Ort.</p>
      <form method="post">
        <input type="hidden" name="a" value="setpw"><input type="hidden" name="o" value="<?= (int)$startO['id'] ?>"><input type="hidden" name="k" value="<?= $h((string)$startO['token']) ?>">
        <label class="f">E-Mail<input type="email" value="<?= $h((string)$startO['email']) ?>" disabled></label>
        <label class="f">Passwort (mindestens 8 Zeichen)<input type="password" name="pw" autocomplete="new-password" minlength="8" required></label>
        <label class="f">Passwort wiederholen<input type="password" name="pw2" autocomplete="new-password" minlength="8" required></label>
        <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
        <p style="margin-top:1.2rem"><button class="btn" type="submit">Zu meinem Bereich</button></p>
      </form>
    <?php elseif ($resetM || isset($_GET['reset'])): ?>
      <p class="k">Neues Passwort</p>
      <?php if ($resetM): ?>
      <h2 style="margin-top:.5rem">Such dir ein <em>neues</em> aus</h2>
      <form method="post"><input type="hidden" name="a" value="reset_set"><input type="hidden" name="t" value="<?= $h((string)$_GET['reset']) ?>">
        <label class="f">Neues Passwort (mindestens 8 Zeichen)<input type="password" name="pw" autocomplete="new-password" minlength="8" required></label>
        <label class="f">Passwort wiederholen<input type="password" name="pw2" autocomplete="new-password" minlength="8" required></label>
        <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
        <p style="margin-top:1.2rem"><button class="btn" type="submit">Speichern und anmelden</button></p></form>
      <?php else: ?><p class="err">Dieser Link ist abgelaufen. Fordere unten einfach einen neuen an.</p><p style="margin-top:1rem"><a href="<?= $h($self) ?>?vergessen=1">Neuen Link anfordern</a></p><?php endif; ?>
    <?php elseif (isset($_GET['vergessen']) || ($_POST['a'] ?? '') === 'reset_req'): ?>
      <p class="k">Passwort vergessen</p>
      <h2 style="margin-top:.5rem">Kein <em>Problem</em></h2>
      <p class="mut" style="margin-top:.6rem">Gib deine E-Mail-Adresse ein, dann schicke ich dir einen Link für ein neues Passwort.</p>
      <?php if ($note): ?><p class="ok"><?= $h($note) ?></p><?php else: ?>
      <form method="post"><input type="hidden" name="a" value="reset_req"><label class="f">E-Mail<input type="email" name="email" autocomplete="email" required></label>
        <p style="margin-top:1.2rem"><button class="btn" type="submit">Link schicken</button></p></form><?php endif; ?>
      <p style="margin-top:1rem;font-size:.9rem"><a href="<?= $h($self) ?>">Zurück zur Anmeldung</a></p>
    <?php else: ?>
      <p class="k">Prä- &amp; Postnatal Training</p>
      <h2 style="margin-top:.5rem">Dein <em>Bereich</em></h2>
      <p class="mut" style="margin-top:.6rem">Melde dich mit deiner E-Mail-Adresse und deinem Passwort an.</p>
      <?php if ($note): ?><p class="ok"><?= $h($note) ?></p><?php endif; ?>
      <?php if (isset($_GET['bye'])): ?><p class="ok">Du bist abgemeldet. Bis bald!</p><?php endif; ?>
      <form method="post"><input type="hidden" name="a" value="login"><input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
        <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required value="<?= $h((string)($_POST['email'] ?? '')) ?>"></label>
        <label class="f">Passwort<input type="password" name="pw" autocomplete="current-password" required></label>
        <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
        <p style="margin-top:1.2rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:center"><button class="btn" type="submit">Anmelden</button><a href="<?= $h($self) ?>?vergessen=1" style="font-size:.9rem">Passwort vergessen?</a></p>
      </form>
    <?php endif; ?>
  </div>
</div></main>

<?php else: /* ---------------- Mitgliederseite ---------------- */ $first = forma_first_name((string)$me['name']); ?>
<main>
<section class="w"><div class="in hero">
  <div>
    <p class="k"><?= $h($v['title']) ?></p>
    <h1 style="margin-top:.5rem"><?= $h($greet) ?>, <em><?= $h($first) ?></em></h1>
    <?php if (isset($_GET['willkommen']) || isset($_GET['danke'])): ?><p class="ok" style="max-width:760px;margin-top:1rem"><?= isset($_GET['danke']) ? 'Danke! Deine Zahlung ist angekommen. Bestätigung und Rechnung kommen per E-Mail.' : 'Willkommen! Dein Konto ist angelegt. Ab jetzt meldest du dich mit deiner E-Mail-Adresse und deinem Passwort an.' ?></p><?php endif; ?>
  <div class="aff"><p class="k"><?= $h($affLabel) ?></p><p class="t">„<?= $h($aff) ?>“</p></div>
  </div>
  <div class="icw" aria-hidden="true"><span class="bl"></span>
  <?php $pos = [[8, 10, 110], [58, 0, 92], [70, 52, 120], [22, 58, 84], [44, 34, 64], [86, 22, 56], [0, 70, 50], [40, 80, 44]];
  foreach ($pos as $i => [$x, $y, $s]) if (isset($icons[$i])) echo '<span class="fl" style="left:' . $x . '%;top:' . $y . '%;width:' . $s . 'px;animation-delay:-' . ($i * 1.3) . 's">' . natal_icon($icons[$i], $s, ['peach', 'blush', 'rose', 'sand', 'peach', 'blush', 'rose', 'sand'][$i]) . '</span>'; ?>
  </div>
</div></section>

<?php if (!$v['full']): ?>
<section class="w"><div class="in"><div class="welcome">
  <span style="position:absolute;right:-30px;top:-30px;opacity:.25"><?= natal_icon($phase === 'post' ? 'wolke' : 'mond', 180, 'peach', 'peach') ?></span>
  <p class="k">Schön, dass du da bist</p>
  <h2>Willkommen in deinem <em style="color:var(--peach)">Bereich</em></h2>
  <p>Hier findest du nach jeder Einheit dein Workbook mit allem, was wir gemacht haben: den Impuls, deine Übungsabfolge, die Atemübung, die Meditation und ein paar Fragen für dein Journal. Bei Online-Einheiten kommt die Aufnahme dazu, damit du alles in Ruhe nachmachen kannst.</p>
  <p>In deinen ersten 14 Tagen kannst du schon in dein erstes Workbook hineinblättern. Ab <?= $h(natal_date_de(date('Y-m-d', $v['fullFrom']), false)) ?> ist dann alles für dich offen, auch zum Herunterladen.</p>
  <p class="hand">Ich freue mich so auf unsere Zeit. Vivien</p>
</div></div></section>
<?php endif; ?>

<section class="w" style="margin-top:16px"><div class="in grid two">
  <div class="card dark">
    <p class="k"><?= $v['next'] ? 'Dein nächster Termin' : 'Deine Termine' ?></p>
    <?php if ($v['next']): ?><p class="when"><?= $h($v['next']) ?></p><?php else: ?><p class="when" style="font-size:1.4rem">Such dir deine nächsten Wunschtermine aus.</p><?php endif; ?>
    <p style="display:flex;flex-wrap:wrap;gap:.6rem"><?php if ($v['room']): ?><a class="btn" style="background:var(--peach);color:var(--deep);border-color:var(--peach)" href="<?= $h($v['room']) ?>">Zum Videoraum</a><?php endif; ?><a class="btn line" style="color:var(--on);border-color:var(--on)" href="<?= $h($v['link']) ?>">Termine &amp; Wunschtermine</a></p>
  </div>
  <div class="card">
    <p class="k">Dein Weg</p>
    <p class="when" style="margin-bottom:0"><?= (int)$v['done'] ?> <span class="mut" style="font-size:.6em">von <?= (int)$v['total'] ?> Einheiten</span></p>
    <div class="dots" aria-hidden="true"><?php foreach ($v['units'] as $u) echo '<i class="' . ($u['state'] === 'done' ? 'd' : ($u['state'] === 'next' ? 'n' : '')) . '"></i>'; ?></div>
    <p class="mut" style="font-size:.9rem">Dein Bereich ist bis <?= $h(natal_date_de($v['until'])) ?> für dich da<?= $v['finished'] ? '' : ' (einen Monat nach deiner letzten Einheit)' ?>.</p>
  </div>
</div></section>

<?php if ($v['home'] !== '' && $v['full']): $hu = natal_units()[$v['home']]; ?>
<section class="w" style="margin-top:16px"><div class="in"><div class="card">
  <p class="k">Für zu Hause · aus „<?= $h($hu['title']) ?>“</p>
  <p style="margin-top:.5rem;font-family:var(--serif);font-size:1.5rem;line-height:1.25"><?= $h($hu['home']) ?></p>
  <details style="margin-top:.8rem"><summary style="cursor:pointer;color:var(--plum)">Deine Übungsabfolge</summary>
    <ol class="homeex"><?php foreach ($hu['ex'] as [$n, $d]) echo '<li><b>' . $h($n) . '</b> · ' . $h($d) . '</li>'; ?></ol></details>
</div></div></section>
<?php endif; ?>

<section class="w" style="margin-top:clamp(2rem,5vw,3.5rem)"><div class="in">
  <p class="k">Deine Einheiten</p>
  <h2 style="margin-top:.4rem">Workbooks, Videos <em>&amp;</em> Aufnahmen</h2>
  <p class="mut" style="max-width:46rem">Nach jeder Einheit findest du hier ein Video, in dem ich das Thema noch ausführlicher erkläre, und ein Bewegungsvideo mit allen Übungen zum Mitmachen, dazu dein Workbook und die Aufnahme unserer Stunde.</p>
  <div class="units">
  <?php foreach ($v['units'] as $u): ?>
    <div class="unit <?= $h($u['state']) ?>">
      <span class="nr"><?= (int)$u['nr'] ?></span>
      <?= natal_icon(natal_unit_icon($u['key']), 34, $u['state'] === 'done' ? 'peach' : 'blush') ?>
      <h3><?= $h($u['title']) ?></h3>
      <p class="th"><?= $h($u['theme']) ?></p>
      <p class="st"><?= $u['state'] === 'done' ? 'War am ' . $h($u['when']) : ($u['state'] === 'next' ? 'Am ' . $h($u['when']) : ($u['state'] === 'planned' ? 'Angefragt: ' . $h($u['when']) : 'Kommt noch')) ?></p>
      <?php if ($u['wb'] || $u['pdf'] || $u['rec'] || $u['talk'] || $u['move']): ?><div class="acts">
        <?php if ($u['talk']): ?><button class="btn sm" type="button" data-vid="<?= $h($u['talk']) ?>" data-t="<?= $h($u['title']) ?> · Vertiefung">▶ Vertiefung</button><?php endif; ?>
        <?php if ($u['move']): ?><button class="btn sm" type="button" data-vid="<?= $h($u['move']) ?>" data-t="<?= $h($u['title']) ?> · Bewegungsvideo">▶ Bewegungsvideo</button><?php endif; ?>
        <?php if ($u['wb']): ?><button class="btn sm" type="button" data-wb="<?= $h($u['key']) ?>" data-o="<?= (int)$v['id'] ?>" data-t="<?= $h($u['label']) ?>"><?= $u['wb'] < 99 ? 'Workbook-Vorschau' : 'Workbook' ?></button><?php endif; ?>
        <?php if ($u['rec']): ?><button class="btn sm line" type="button" data-rec="<?= $h($u['rec']) ?>" data-t="<?= $h($u['label']) ?>">▶ Aufnahme</button><?php endif; ?>
        <?php if ($u['pdf']): ?><a class="btn sm line" href="<?= $h($u['pdf']) ?>">PDF</a><?php endif; ?>
      </div><?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
</div></section>

<section class="w music" style="margin-top:clamp(2rem,5vw,3.5rem)"><div class="in grid two">
  <div class="card">
    <p class="k">Musik für dich</p>
    <h3 style="margin-top:.4rem">Zum Bewegen, Atmen und Ausruhen</h3>
    <?php if (count($lists) > 1): ?><div class="tabs"><?php foreach ($lists as $i => $l): ?><button type="button" data-sp="<?= $h($l['url']) ?>" aria-pressed="<?= $i ? 'false' : 'true' ?>"><?= $h($l['title']) ?></button><?php endforeach; ?></div><?php endif; ?>
    <iframe id="spot" src="<?= $h($lists[0]['url']) ?>" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" title="Spotify"></iframe>
    <p class="mut" style="font-size:.84rem;margin-top:.6rem">Bist du in diesem Browser bei Spotify angemeldet, hörst du die ganzen Lieder, sonst spielt Spotify kurze Ausschnitte.</p>
  </div>
  <div class="card" style="display:flex;flex-direction:column;gap:.8rem">
    <p class="k">Brauchst du etwas?</p>
    <p style="font-family:var(--serif);font-size:1.5rem;line-height:1.25">Schreib mir jederzeit, wenn du eine Frage hast, dich etwas zwickt oder du einfach reden magst.</p>
    <p style="display:flex;flex-wrap:wrap;gap:.6rem;margin-top:auto"><a class="btn" href="<?= $h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><a class="btn line" href="mailto:<?= $h(NATAL_COMPANY['email']) ?>">E-Mail</a></p>
  </div>
</div></section>
</main>

<div class="sheet" id="sheet" hidden><div class="box"><div class="bar"><p class="k" id="sht"></p><button class="x" type="button" id="shx" aria-label="Schließen">×</button></div><div id="shb"></div></div></div>
<?php endif; ?>

<footer class="w"><div class="in">
  <p>Movecia · Vivien Malonga · Deine Aufnahmen und Workbooks sind nur für dich. Einen Monat nach deiner letzten Einheit wird dein Bereich mit allen Aufnahmen und Gesundheitsangaben gelöscht. · <a href="/terms">Bedingungen</a></p>
</div></footer>

<?php if ($me): ?>
<script src="/wildhearts/assets/flow-player.js?v=12"></script>
<script src="/wildhearts/assets/titelkarte.js?v=1"></script>
<script>
(() => {
  const DEMO = <?= json_encode($demo) ?>;
  const sh = document.getElementById('sheet'), sb = document.getElementById('shb'), st = document.getElementById('sht');
  const open = (t, html) => { st.textContent = t; sb.innerHTML = html; sh.hidden = false; document.body.style.overflow = 'hidden'; };
  const close = () => { if (typeof flowP !== 'undefined' && flowP) { flowP.stop(); flowP = null; } sh.hidden = true; sb.innerHTML = ''; document.body.style.overflow = ''; };
  document.getElementById('shx').onclick = close; sh.onclick = e => { if (e.target === sh) close(); };
  addEventListener('keydown', e => { if (e.key === 'Escape' && !sh.hidden) close(); });
  document.querySelectorAll('[data-wb]').forEach(b => b.addEventListener('click', async () => {
    const u = new URL(location.href); u.searchParams.set('wb', b.dataset.wb); u.searchParams.set('o', b.dataset.o);
    open(b.dataset.t, '<p class="mut">Einen Moment …</p>');
    const r = await fetch(u); sb.innerHTML = r.ok ? await r.text() : '<p class="err">Das Workbook ist gerade nicht verfügbar.</p>';
    const w = sb.querySelector('.wb'); if (w) Workbook(w);
  }));
  document.querySelectorAll('[data-rec]').forEach(b => b.addEventListener('click', () => {
    if (DEMO) { open(b.dataset.t, '<div style="aspect-ratio:16/9;border-radius:18px;background:var(--deep);color:var(--on);display:flex;align-items:center;justify-content:center;text-align:center;padding:1rem">Hier läuft die Aufnahme der Einheit.<br>Man sieht nur Vivien.</div>'); return; }
    open(b.dataset.t, '<video controls playsinline preload="metadata" controlslist="nodownload" src="' + b.dataset.rec + '"></video><p class="mut" style="font-size:.85rem;margin-top:.6rem">Diese Aufnahme ist nur für dich.</p>');
  }));
  let flowP = null;
  document.querySelectorAll('[data-vid]').forEach(b => b.addEventListener('click', () => {
    if (b.dataset.vid.indexOf('flow:') === 0 && window.MvFlow) { open(b.dataset.t, ''); const d = document.createElement('div'); sb.appendChild(d); flowP = MvFlow(d, JSON.parse(b.dataset.vid.slice(5))); return; }
    if (DEMO) { open(b.dataset.t, '<div style="aspect-ratio:16/9;border-radius:18px;background:var(--deep);color:var(--on);display:flex;align-items:center;justify-content:center;text-align:center;padding:1rem">Hier läuft das Video zur Einheit.</div>'); return; }
    const f = document.createElement('iframe');
    f.src = b.dataset.vid; f.allow = 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen'; f.allowFullscreen = true;
    f.style.cssText = 'width:100%;aspect-ratio:16/9;border:0;border-radius:18px;background:var(--deep)';
    open(b.dataset.t, ''); const fw = document.createElement('div'); fw.style.borderRadius = '18px'; fw.appendChild(f); sb.appendChild(fw);
    if (window.MvTitle) MvTitle(f, { t: b.dataset.t.replace(/ · Vertiefung$/, ''), r: 'natal', l: 'de' });
  }));
  document.querySelectorAll('.tabs button').forEach(b => b.addEventListener('click', () => {
    document.getElementById('spot').src = b.dataset.sp;
    document.querySelectorAll('.tabs button').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
  }));
  if (DEMO) document.querySelectorAll('a[href="#demo"]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); alert('In der Vorschau ohne Funktion.'); }));
})();
</script>
<?php endif; ?>
<?php if ($me && !$demo) { ?><script src="/wildhearts/assets/geraet.js?v=1" data-kind="natal" defer></script><?php } ?>
<?php if ($me && !$demo && !empty($me['id'])) echo mm_wm_script('natal', (int)$me['id'], 'de', (string)($me['name'] ?? '')), "\n"; ?>
</body>
</html>

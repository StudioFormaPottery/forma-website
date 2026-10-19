<?php
// Wild Hearts Gathering: Anmeldung und Videoraum direkt auf moveciamovement.com/gathering
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/movecia-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
mm_schema($pdo);
try { mm_send_reminders($pdo); } catch (Throwable $e) { error_log('MM reminders: ' . $e->getMessage()); }

// Technik-Test: nur mit dem geheimen Token aus der Verwaltung
$testTok = (string)($_GET['test'] ?? '');
if ($testTok !== '') {
    $tr = mm_cfg('testroom') ?? [];
    if (empty($tr['token']) || !hash_equals((string)$tr['token'], $testTok)) { http_response_code(404); exit('Not found'); }
    $guest = !empty($_GET['gast']);
    // Teleprompter-Text speichern (nur Gastgeberin, liegt im Datenordner)
    if (!$guest && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode((string)file_get_contents('php://input'), true);
        $p = mm_cfg('prompter') ?? [];
        if (isset($in['text'])) $p['text'] = mb_substr((string)$in['text'], 0, 20000);
        foreach (['speed' => [5, 200], 'size' => [18, 72]] as $k => [$lo, $hi]) if (isset($in[$k])) $p[$k] = max($lo, min($hi, (int)$in[$k]));
        mm_save_cfg('prompter', $p);
        header('Content-Type: application/json');
        exit('{"ok":true}');
    }
    $prompter = $guest ? [] : (mm_cfg('prompter') ?? []);
    // Video-Bühne: eigener Tab mit einem Video vom eigenen Gerät, wird in Whereby als Tab geteilt
    if (!$guest && !empty($_GET['buehne'])) { require __DIR__ . '/buehne.inc.php'; exit; }
    // Echtes Gathering als Gastgeberin mit Teleprompter: ?test=TOKEN&g=ID
    $gath = !$guest && !empty($_GET['g']) ? mm_gathering($pdo, (int)$_GET['g']) : null;
    if ($gath && $gath['host_url'] !== '') {
        $ready = true;
        $src = $gath['host_url'] . (str_contains($gath['host_url'], '?') ? '&' : '?') . 'displayName=Viv';
        require __DIR__ . '/test.inc.php';
        exit;
    }
    $gath = null;
    if (!$guest && (empty($tr['room_url']) || (int)($tr['ends'] ?? 0) < time() + 3600)) {
        try { $tr = mm_testroom(); } catch (Throwable $e) { error_log('MM testroom: ' . $e->getMessage()); }
    }
    $ready = !empty($tr['room_url']) && (int)($tr['ends'] ?? 0) > time();
    $src = '';
    if ($ready) {
        $base = $guest ? $tr['room_url'] : ($tr['host_url'] ?: $tr['room_url']);
        $src = $base . (str_contains($base, '?') ? '&' : '?') . 'displayName=' . rawurlencode($guest ? 'Testgast' : 'Viv');
    }
    require __DIR__ . '/test.inc.php';
    exit;
}

$tok = (string)($_GET['k'] ?? '');
$me = null;
$g = null;
if ($tok !== '') {
    $s = $pdo->prepare("SELECT * FROM mm_signups WHERE token = ? AND status = 'angemeldet'");
    $s->execute([$tok]);
    $me = $s->fetch() ?: null;
    if ($me) $g = mm_gathering($pdo, (int)$me['gathering_id']);
}
if (!$g) $g = mm_next_gathering($pdo);
$open = $me && $g && mm_room_open($g) && $g['room_url'] !== '';
$over = $g && time() > strtotime($g['starts_at']) + ((int)$g['minutes'] + 60) * 60;
$roomSrc = $open ? $g['room_url'] . (str_contains($g['room_url'], '?') ? '&' : '?') . 'displayName=' . rawurlencode(forma_first_name($me['name'])) : '';
$h = 'forma_h';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $open ? 'Gathering room' : 'Wild Hearts Gathering' ?> · Wild Hearts</title>
<meta name="description" content="A free live circle for women who want more than just functioning. With Viv, from the Austrian mountains.">
<?php if ($me): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<meta name="theme-color" content="#F3F1E7">
<link rel="icon" href="/img/wildhearts/viv.jpg">
<meta property="og:title" content="Wild Hearts Gathering">
<meta property="og:image" content="https://moveciamovement.com/img/wildhearts/og.jpg">
<style>
@font-face{font-family:"Italiana";font-weight:400;font-display:swap;src:url(/fonts/Italiana-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:300 500;font-display:swap;src:url(/fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:600 800;font-display:swap;src:url(/fonts/Outfit-Bold.woff) format("woff")}
@font-face{font-family:"Hand";font-weight:400;font-display:swap;src:url(/fonts/HomemadeApple-Regular.woff) format("woff")}
:root{--cream:#F6F3EA;--oat:#E3DED0;--blush:#D8DCC6;--rose:#B3A78F;--wine:#5E6B35;--wine-deep:#3F4A26;--earth:#3E372C;--brown:#6B5D4C;--ink:#2F2B22;--sage-soft:#DCE0D2;--line:#CFC9B5;--blob:58% 42% 47% 53% / 52% 46% 54% 48%;--hand:"Hand","Bradley Hand",cursive}
*{box-sizing:border-box;margin:0}
[hidden]{display:none!important}
html,body{background:var(--cream)}
body{min-height:100dvh;font-family:"Outfit",system-ui,sans-serif;color:var(--ink);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;position:relative}
.bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}
.bg span{position:absolute;border-radius:var(--blob)}
.bg .a{width:420px;height:420px;right:-200px;top:-150px;background:var(--oat);opacity:.8}
.bg .b{width:300px;height:300px;left:-190px;bottom:-60px;background:var(--blush);opacity:.5}
main{position:relative;z-index:1;max-width:680px;margin:0 auto;padding:max(36px,env(safe-area-inset-top)) 16px 40px;text-align:center}
.hand{font-family:var(--hand);font-weight:400;line-height:1.5}
.hi{font-size:24px;color:var(--wine);transform:rotate(-2deg)}
h1{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(38px,9vw,56px);line-height:1.05;color:var(--wine-deep);margin-top:8px}
.when{display:inline-block;margin-top:18px;padding:14px 22px;border-radius:22px;background:var(--sage-soft);color:var(--earth);font-weight:500}
.lead{color:var(--brown);margin:16px auto 0;max-width:38ch}
.card{position:relative;margin-top:30px;padding:32px 22px 24px;border-radius:30px;background:#FCFBF6;border:1.5px solid var(--line);box-shadow:0 18px 40px rgba(62,58,40,.1);text-align:left}
.tape{position:absolute;width:86px;height:26px;background:rgba(216,220,198,.82);top:-13px;left:50%;margin-left:-43px;transform:rotate(-6deg)}
form{display:flex;flex-direction:column;gap:12px}
label.f{display:flex;flex-direction:column;gap:6px;font-size:14px;color:var(--brown)}
input[type=text],input[type=email]{font:inherit;font-size:17px;padding:14px 18px;border-radius:18px;border:1.5px solid var(--line);background:var(--cream);color:var(--ink)}
input:focus{outline:2px solid var(--blush);outline-offset:1px;border-color:var(--wine)}
.consent{display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.45;color:var(--brown)}
.consent input{margin-top:3px;width:18px;height:18px;accent-color:var(--wine);flex:0 0 auto}
button,.btn{font:inherit;font-weight:500;font-size:17px;padding:15px 24px;border-radius:999px;border:0;background:var(--wine);color:#F6F3EA;cursor:pointer;text-decoration:none;display:inline-block;text-align:center}
button:disabled{opacity:.6;cursor:wait}
.msg{text-align:center;font-size:15.5px;line-height:1.5;padding:6px 0 0}
.msg.err{color:var(--wine)}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.ghost{background:var(--cream);color:var(--wine-deep);border:1.5px solid var(--line)}
.sig{margin-top:26px;font-size:20px;color:var(--wine)}
.foot{margin-top:26px;font-size:13px;color:var(--brown)}
.foot a{color:var(--brown)}
/* Raum */
body.room{background:var(--earth)}
.roomwrap{position:fixed;inset:0;display:flex;flex-direction:column;background:var(--earth)}
.roombar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 16px;color:var(--cream);font-size:14px}
.roombar .hand{color:var(--blush);font-size:16px}
.roombar a{color:var(--blush)}
.roomwrap iframe{flex:1;width:100%;border:0;background:#2a1b16}
</style>
</head>
<?php if ($open): ?>
<body class="room">
<div class="roomwrap">
  <div class="roombar"><span class="hand">welcome, wild heart ♡</span><span><?= $h($g['title']) ?> · <a href="/">moveciamovement.com</a></span></div>
  <iframe src="<?= $h($roomSrc) ?>" allow="camera; microphone; fullscreen; speaker; display-capture; autoplay; compute-pressure" title="Wild Hearts Gathering video room"></iframe>
</div>
<script>
(() => {
  const ping = () => fetch('/api/gathering.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'ping', k: <?= json_encode($tok) ?>}), keepalive: true}).catch(() => {});
  ping(); setInterval(ping, 60000);
})();
</script>
<script src="/wildhearts/gathering/offer.js?v=1"></script>
<script>whOffer({k: <?= json_encode($tok) ?>});</script>
</body>
<?php else: ?>
<body>
<div class="bg" aria-hidden="true"><span class="a"></span><span class="b"></span></div>
<main>
<?php if ($me && $g && !$over): ?>
  <p class="hand hi">your seat is saved, <?= $h(forma_first_name($me['name'])) ?> ♡</p>
  <h1><?= $h($g['title']) ?></h1>
  <p class="when"><?= $h(mm_when($g)) ?></p>
  <p class="lead">The room opens right here, 15 minutes before we start. Keep this page or the link in your email, and simply come back then. No app, no Zoom.</p>
  <p class="lead" id="count" style="font-weight:500;color:var(--wine-deep)"></p>
  <script>
  (() => {
    const start = <?= (int)strtotime($g['starts_at']) ?> * 1000, opens = start - 15 * 60000, el = document.getElementById('count');
    const tick = () => {
      const ms = opens - Date.now();
      if (ms <= 0) { location.reload(); return; }
      const d = Math.floor(ms / 86400000), h = Math.floor(ms / 3600000) % 24, m = Math.floor(ms / 60000) % 60;
      el.textContent = 'The room opens in ' + (d ? d + (d === 1 ? ' day, ' : ' days, ') : '') + h + ' h ' + m + ' min.';
    };
    tick(); setInterval(tick, 30000);
  })();
  </script>
  <?php if (mm_room_open($g) && $g['room_url'] === ''): ?><p class="lead">The room is being prepared, please reload in a moment.</p><?php endif; ?>
  <p style="margin-top:24px"><a class="btn ghost" href="<?= $h(MM_CHANNEL) ?>" target="_blank" rel="noopener">Say hi in the community channel →</a></p>
<?php elseif ($g && !$over): ?>
  <p class="hand hi">come as you are ♡</p>
  <h1><?= $h($g['title']) ?></h1>
  <p class="when"><?= $h(mm_when($g)) ?> · <?= (int)$g['minutes'] ?> min · free</p>
  <p class="lead"><?= $g['description'] !== '' ? nl2br($h($g['description'])) : 'A live circle for women who want more than just functioning. We talk honestly, we breathe, and we remember who we are. Camera on or off, just as you like.' ?></p>
  <section class="card">
    <span class="tape" aria-hidden="true"></span>
    <form id="f" novalidate>
      <label class="f">First name<input type="text" name="name" autocomplete="given-name" required maxlength="80"></label>
      <label class="f">Email<input type="email" name="email" autocomplete="email" required maxlength="160"></label>
      <div class="hp" aria-hidden="true"><label>Leave empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <label class="consent"><input type="checkbox" name="consent" required><span>Yes, send me my room link and reminders for this Gathering. My data is only used for this.</span></label>
      <button type="submit">Save my seat</button>
      <p class="msg" id="m" role="status" aria-live="polite" hidden></p>
    </form>
  </section>
  <script>
  (() => {
    const f = document.getElementById('f'), m = document.getElementById('m'), t0 = Date.now();
    const show = (t, e) => { m.hidden = false; m.textContent = t; m.classList.toggle('err', !!e); };
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const b = f.querySelector('button'); b.disabled = true;
      try {
        const r = await fetch('/api/gathering.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({name: f.name.value.trim(), email: f.email.value.trim(), consent: f.consent.checked, website: f.website.value, elapsed: Date.now() - t0})});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'error');
        f.querySelectorAll('label, button').forEach(x => x.hidden = true);
        show('Your seat is saved! 🤍 Your personal room link is on its way to your inbox. See you there.');
      } catch (err) {
        b.disabled = false;
        show(err.message && err.message !== 'error' ? err.message : 'That did not work just now. Please try again or write to hello@moveciamovement.com.', true);
      }
    });
  })();
  </script>
<?php else: ?>
  <p class="hand hi">the next circle is coming ♡</p>
  <h1>Wild Hearts Gathering</h1>
  <p class="lead">Our next live Gathering is being planned. Join the community channel and you'll hear about it first.</p>
  <p style="margin-top:24px"><a class="btn" href="<?= $h(MM_CHANNEL) ?>" target="_blank" rel="noopener">Join the Wild Hearts Collective →</a></p>
<?php endif; ?>
  <p class="hand sig">with love, Viv</p>
  <p class="foot"><a href="/">moveciamovement.com</a> · Vivien Malonga, Movecia, Silvrettastraße 58, 6562 Mathon, Austria · <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a><br>Your name and email are stored only on this website to send you the room link and reminders, and you can ask me to delete them at any time. The video room is provided by Whereby (Norway, EU/EEA).</p>
</main>
</body>
<?php endif; ?>
</html>

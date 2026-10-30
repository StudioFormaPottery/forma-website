<?php
// moveciamovement.com/termin/?t=TOKEN – persönlicher Termin-Link für ein Videogespräch (Vivien, 29.10.2026).
// Uhrzeit antippen + Name/E-Mail → sofort fix (api/termin-lib.php). Danach ist dieselbe Seite der Videoraum (öffnet 15 Min. vorher).
// Nur Deutsch, noindex, nirgends verlinkt.
declare(strict_types=1);
require_once __DIR__ . '/../assets/icons.inc.php';
require dirname(__DIR__, 2) . '/api/termin-lib.php';
require __DIR__ . '/../assets/layout.inc.php';
$GLOBALS['mvl_de_only'] = true;
$GLOBALS['mvl_noindex'] = true;
ob_start('mv_strip_en');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$pdo = forma_db();
tm_schema($pdo);
$h = 'forma_h';
$tok = (string)($_GET['t'] ?? '');
$t = tm_by_token($pdo, $tok);
$err = '';
$preview = !empty($_GET['vorschau']); // Vivien schaut sich die Seite an, ohne zu buchen
if ($t && !$preview && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim((string)($_POST['website'] ?? '')) !== '' || time() - (int)($_POST['ts'] ?? 0) < 3) $err = 'Bitte versuch es noch einmal.';
    else {
        $r = tm_book($pdo, $t, (string)($_POST['slot'] ?? ''), (string)($_POST['name'] ?? ''), (string)($_POST['email'] ?? ''), (string)($_POST['note'] ?? ''));
        if ($r['ok']) { header('Location: /termin/?t=' . rawurlencode($tok) . '&ok=1'); exit; }
        $err = $r['error'];
    }
    $t = tm_by_token($pdo, $tok);
}
$now = time();
$state = 'invalid'; $src = ''; $start = 0; $free = [];
if ($t) {
    if ($t['status'] === 'offen') {
        $free = tm_free_times($pdo, $t);
        $state = $free ? 'choose' : 'gone';
    } else {
        $start = tm_ts($t);
        $g = mm_gathering($pdo, (int)$t['gid']);
        if ($now < $start - 15 * 60) $state = 'soon';
        elseif ($now > $start + ((int)$t['minutes'] + 60) * 60) $state = 'over';
        elseif (!$g || (string)$g['room_url'] === '') $state = 'noroom';
        else {
            $state = 'open';
            $base = (string)$g['room_url'];
            $src = $base . (str_contains($base, '?') ? '&' : '?') . http_build_query(['displayName' => forma_first_name((string)$t['name']), 'background' => 'off', 'logo' => 'off', 'precallReview' => 'on', 'chat' => 'on', 'people' => 'off', 'locking' => 'off']);
        }
    }
}
$css = '<style>
body.tm{min-height:100dvh;display:flex;flex-direction:column}
.tm main{flex:1;display:flex;flex-direction:column}
.tm-wrap{width:100%;max-width:600px;margin:clamp(1.2rem,5vh,3.5rem) auto;padding:0 16px;box-sizing:border-box}
.tm-card{position:relative;overflow:hidden;background:rgba(251,248,244,.96);border-radius:32px;padding:clamp(1.5rem,4vw,2.6rem);box-shadow:0 22px 50px -34px rgba(74,58,65,.5);text-align:center}
.tm-card::before{content:"";position:absolute;right:-60px;top:-70px;width:210px;height:190px;border-radius:58% 42% 47% 53%/52% 46% 54% 48%;background:#E4DCD1;opacity:.7;z-index:0}
.tm-card>*{position:relative;z-index:1}
.tm-mark{width:64px;height:auto;margin:0 auto .6rem;display:block}
.tm-k{font-size:.72rem;letter-spacing:.2em;text-transform:uppercase;color:#6E6168;margin:0}
.tm-card h1{font-family:var(--serif);font-weight:500;font-size:clamp(1.9rem,6vw,2.6rem);line-height:1.1;margin:.3rem 0 .7rem;color:#2A2026}
.tm-card h1 em{color:#4A3040}
.tm-card p{color:#6E6168;line-height:1.6;margin:.4rem 0}
.tm-day{display:inline-block;margin:.9rem 0 .3rem;background:#E6DBCF;color:#2E1D27;border-radius:99px;padding:.45em 1.15em;font-size:.98rem}
.tm-times{display:flex;flex-wrap:wrap;justify-content:center;gap:.55rem;margin:1rem 0 1.2rem;padding:0;border:0}
.tm-times legend{width:100%;margin-bottom:.5rem;color:#2A2026;font-size:.98rem}
.tm-times label{cursor:pointer}
.tm-times input{position:absolute;opacity:0;pointer-events:none}
.tm-times span{display:inline-block;min-width:4.6em;padding:.7em 1em;border-radius:99px;border:1px solid #D3C9BC;background:#FBF9F6;color:#2E1D27;font-size:1.02rem;transition:background .2s,color .2s,border-color .2s}
.tm-times input:checked+span{background:#4A3040;border-color:#4A3040;color:#F7F1EE}
.tm-times input:focus-visible+span{outline:2px solid #4A3040;outline-offset:2px}
.tm-f{display:grid;gap:.65rem;text-align:left;margin-top:.4rem}
.tm-f label{display:grid;gap:.25rem;font-size:.9rem;color:#2E1D27}
.tm-f input,.tm-f textarea{font:inherit;font-size:1rem;border:1px solid #D3C9BC;border-radius:16px;padding:.75em .95em;background:#fff;color:#2A2026;width:100%;box-sizing:border-box}
.tm-f textarea{min-height:5em;resize:vertical}
.tm-go{margin-top:.6rem;border:0;border-radius:99px;background:#4A3040;color:#F7F1EE;font:inherit;font-size:1.05rem;padding:.9em 1.6em;cursor:pointer;justify-self:center}
.tm-go:disabled{opacity:.5;cursor:default}
.tm-err{background:#F4E3E3;color:#6B3B3B!important;border-radius:14px;padding:.7rem 1rem}
.tm-hp{position:absolute!important;left:-9999px}
.tm-cd{font-family:var(--serif);font-size:2.3rem;color:#4A3040!important;margin:.5rem 0 0!important}
.tm-tips{list-style:none;padding:0;margin:1.1rem 0 0;display:grid;gap:.5rem;text-align:left}
.tm-tips li{background:#F7F1EE;border-radius:16px;padding:.7rem 1rem;font-size:.95rem;color:#2A2026}
.tm-room{flex:1;display:flex;flex-direction:column;padding:0 clamp(0px,1.4vw,18px) clamp(0px,1.4vw,18px)}
.tm-room iframe{flex:1;width:100%;min-height:calc(100dvh - 90px);border:0;border-radius:clamp(0px,1.4vw,20px);background:#2E1D27;display:block}
</style>';
mvl_head('Videogespräch · Movecia', '', mm_site() . '/termin/', '/img/movecia/og-start.jpg', $css);
?>
<body class="tm">
<header class="w site-top"><div class="in top"><a class="brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia</span></a><div class="top-r"><span class="muted" style="font-size:.9rem">Videogespräch</span></div></div></header>
<main>
<?php if ($state === 'open'): ?>
  <div class="tm-room"><iframe src="<?= $h($src) ?>" allow="camera; microphone; fullscreen; speaker; display-capture; autoplay; compute-pressure" title="Movecia Videoraum"></iframe></div>
<?php else: ?>
  <div class="tm-wrap"><div class="tm-card">
    <img class="tm-mark" src="/img/movecia/movecia-zeichen.svg" alt="">
    <?php if ($state === 'invalid'): ?>
      <h1>Dieser Link <em>passt nicht</em></h1><p>Bitte nimm den Link aus meiner E-Mail. Wenn es trotzdem nicht klappt, schreib mir einfach an hello@moveciamovement.com.</p>
    <?php elseif ($state === 'gone'): ?>
      <h1>Für diesen Tag ist <em>nichts mehr frei</em></h1><p>Schreib mir einfach an hello@moveciamovement.com, dann finden wir gemeinsam einen neuen Termin.</p>
    <?php elseif ($state === 'choose'): ?>
      <?php if ($preview): ?><p class="tm-err" style="background:#E9E2D8;color:#2E1D27!important">Vorschau: So sieht es die Gästin. Hier wird nichts gebucht.</p><?php endif; ?>
      <p class="tm-k">Videogespräch · <?= $h($t['title']) ?></p>
      <h1>Hallo<?= $t['guest'] !== '' ? ', <em>' . $h($t['guest']) . '</em>' : '' ?>!</h1>
      <?php if ($t['intro'] !== ''): ?><p><?= $h($t['intro']) ?></p><?php endif; ?>
      <span class="tm-day"><?= $h(tm_day($t)) ?> · etwa <?= (int)$t['minutes'] ?> Minuten · online</span>
      <?php if ($err !== ''): ?><p class="tm-err"><?= $h($err) ?></p><?php endif; ?>
      <form method="post" class="tm-f" id="tmf">
        <fieldset class="tm-times"><legend>Welche Uhrzeit passt euch?</legend>
          <?php foreach ($free as $s): ?><label><input type="radio" name="slot" value="<?= $h($s) ?>" required<?= (($_POST['slot'] ?? '') === $s) ? ' checked' : '' ?>><span><?= $h(ltrim(substr($s, 0, 2), '0') . substr($s, 2)) ?> Uhr</span></label><?php endforeach; ?>
        </fieldset>
        <label>Dein Name<input name="name" autocomplete="name" required value="<?= $h((string)($_POST['name'] ?? '')) ?>"></label>
        <label>Deine E-Mail (dorthin schicke ich die Bestätigung)<input type="email" name="email" autocomplete="email" required value="<?= $h((string)($_POST['email'] ?? '')) ?>"></label>
        <label>Möchtest du mir vorab etwas sagen? (freiwillig)<textarea name="note"><?= $h((string)($_POST['note'] ?? '')) ?></textarea></label>
        <input class="tm-hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><input type="hidden" name="ts" value="<?= time() ?>">
        <button class="tm-go" type="<?= $preview ? 'button' : 'submit' ?>"<?= $preview ? ' onclick="alert(\'Vorschau: hier wird nichts gebucht.\')"' : '' ?>>Termin fix machen</button>
      </form>
      <p style="font-size:.88rem;margin-top:1rem">Du bekommst sofort eine Bestätigung mit Kalenderdatei. Über diesen Link kommst du dann auch in unseren Videoraum, ganz ohne App.</p>
    <?php elseif ($state === 'over'): ?>
      <h1>Danke für <em>unser Gespräch</em> ♡</h1><p>Der Raum ist jetzt geschlossen. Alles Weitere schicke ich dir per E-Mail.</p>
    <?php else: ?>
      <?php if (!empty($_GET['ok'])): ?><p class="tm-k">Danke, dein Termin ist fix</p><?php endif; ?>
      <h1>Schön, dass du da bist, <em><?= $h(forma_first_name((string)$t['name'])) ?></em></h1>
      <span class="tm-day"><?= $h(tm_when($t)) ?></span>
      <?php if ($state === 'soon'): ?><p class="tm-cd" id="tmcd" data-open="<?= $start - 15 * 60 ?>" data-now="<?= $now ?>">–</p><?php endif; ?>
      <p><?= $state === 'noroom' ? 'Den Link zu unserem Videoraum schicke ich dir vor dem Gespräch per E-Mail.' : 'Unser Videoraum öffnet sich hier 15 Minuten vor Beginn von selbst. Die Bestätigung mit Kalenderdatei ist schon in deinem Postfach. Komm einfach über diesen Link wieder.' ?></p>
      <ul class="tm-tips"><li>Am Handy oder Laptop: Erlaube Kamera und Mikrofon, wenn dein Browser fragt.</li><li>Du brauchst keine App und kein Konto.</li></ul>
    <?php endif; ?>
  </div></div>
<?php endif; ?>
</main>
<script>
(function(){var cd=document.getElementById('tmcd');if(!cd)return;var open=+cd.dataset.open*1000,skew=Date.now()-(+cd.dataset.now*1000);
  var t=function(){var ms=open-(Date.now()-skew);if(ms<=0){location.reload();return}var s=Math.ceil(ms/1000),d=Math.floor(s/86400),hh=Math.floor(s%86400/3600),m=Math.floor(s%3600/60);
    cd.textContent=d>0?('noch '+d+(d===1?' Tag ':' Tage ')+hh+' Std.'):(hh>0?('noch '+hh+' Std. '+m+' Min.'):('noch '+m+':'+String(s%60).padStart(2,'0')));setTimeout(t,1000)};t();})();
</script>
</body></html>

<?php
// Einladungsseite für The Inner Circle: moveciamovement.com/circle/?ref=CODE
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/movecia-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
mm_schema($pdo);
mm_offer_schema($pdo);
try { mm_send_ref_mails($pdo); mm_send_stay_mails($pdo); } catch (Throwable $e) { error_log('MM ref mails: ' . $e->getMessage()); }
$o = mm_offers()['circle'];
$code = (string)($_GET['ref'] ?? '');
$ref = mm_ref_order($pdo, $code);
$stayCode = strtoupper((string)($_GET['stay'] ?? ''));
$stay = $stayCode !== '' ? mm_stay_order($pdo, $stayCode) : null;
if ($stay) $ref = null;
$h = 'forma_h';
$reg = mm_price_en($o['price_cents'], 'monthly');
$sp = mm_price_en($o['special_cents'], 'monthly');
$startTs = mm_circle_start();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>The Inner Circle · Wild Hearts</title>
<meta name="description" content="Your monthly circle with Viv: live gatherings, breathwork, gentle movement and a members area with rituals.">
<?php if ($ref || $stay || isset($_GET['stay'])): ?><meta name="robots" content="noindex"><?php endif; ?>
<link rel="icon" href="/img/wildhearts/viv.jpg">
<meta property="og:image" content="https://moveciamovement.com/img/wildhearts/og.jpg">
<style>
@font-face{font-family:"Italiana";src:url(/fonts/Italiana-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:300 500;src:url(/fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:600 800;src:url(/fonts/Outfit-Bold.woff) format("woff")}
@font-face{font-family:"Hand";src:url(/fonts/HomemadeApple-Regular.woff) format("woff")}
:root{--cream:#F6F3EA;--wine:#5E6B35;--wine-deep:#3F4A26;--brown:#6B5D4C;--ink:#2F2B22;--line:#CFC9B5;--sage:#DCE0D2}
*{box-sizing:border-box;margin:0}
body{background:var(--cream);font-family:"Outfit",system-ui,sans-serif;color:var(--ink);font-size:18px;line-height:1.6;overflow-x:hidden}
.bg{position:fixed;inset:0;pointer-events:none}.bg span{position:absolute;border-radius:58% 42% 47% 53%/52% 46% 54% 48%}
main{position:relative;max-width:760px;margin:0 auto;padding:44px 16px 60px;text-align:center}
.pol{display:inline-block;background:#FCFBF6;padding:12px 12px 44px;box-shadow:0 16px 36px rgba(62,58,40,.18);transform:rotate(-3deg);position:relative}
.pol img{display:block;width:260px;height:260px;object-fit:cover}
.pol span{position:absolute;left:0;right:0;bottom:10px;font-family:"Hand",cursive;color:var(--wine);font-size:17px}
.hand{font-family:"Hand",cursive;color:var(--wine);font-size:22px;transform:rotate(-2deg);display:inline-block;margin-top:24px}
h1{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:clamp(42px,9vw,64px);line-height:1.05;color:var(--wine-deep);margin-top:6px}
.lead{color:var(--brown);margin:12px auto 0;max-width:40ch}
.price{margin-top:22px}.price .reg{color:#9A9282;text-decoration:line-through;margin-right:10px}.price b{font-size:36px;color:var(--wine-deep);font-weight:500}
.price small{display:block;color:var(--brown);font-size:15px}
ul{list-style:none;text-align:left;max-width:520px;margin:24px auto 0}li{margin:6px 0;color:#3E372C}li::before{content:"✦  ";color:#9DA58F}
.card{text-align:left;margin:28px auto 0;max-width:560px;padding:24px 22px;border-radius:28px;background:#FCFBF6;border:1.5px solid var(--line);box-shadow:0 18px 40px rgba(62,58,40,.08)}
label.f{display:flex;flex-direction:column;gap:5px;font-size:14px;color:var(--brown);margin-top:12px}
input[type=text],input[type=email]{font:inherit;font-size:17px;padding:13px 16px;border-radius:16px;border:1.5px solid var(--line);background:var(--cream)}
.cons{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;line-height:1.45;color:var(--brown);margin-top:14px}
.cons input{margin-top:3px;width:17px;height:17px;flex:0 0 auto;accent-color:var(--wine)}
button{display:block;width:100%;margin-top:18px;font:inherit;font-weight:500;font-size:18px;padding:15px 22px;border-radius:999px;border:0;background:var(--wine);color:var(--cream);cursor:pointer}
button:disabled{opacity:.6}
.msg{margin-top:10px;font-size:15px;color:#8A3B2E}
.hp{position:absolute;left:-9999px}
.small{font-size:13.5px;color:var(--brown);margin-top:14px}
a{color:var(--wine)}
.plans{display:flex;flex-direction:column;gap:8px;margin-bottom:6px}.pl{display:flex;gap:10px;align-items:center;padding:11px 14px;border:1.5px solid var(--line);border-radius:16px;background:var(--cream);cursor:pointer;font-size:16px}.pl input{accent-color:var(--wine);width:17px;height:17px}.pl em{font-style:normal;font-size:12.5px;background:var(--sage);color:var(--wine-deep);border-radius:999px;padding:2px 9px;margin-left:4px}
.invite{display:inline-block;margin-top:16px;padding:8px 18px;border-radius:999px;background:var(--sage);color:var(--wine-deep);font-size:15px}
</style>
</head>
<body>
<div class="bg" aria-hidden="true"><span style="width:420px;height:420px;right:-200px;top:-150px;background:#E3DED0"></span><span style="width:300px;height:300px;left:-190px;bottom:-60px;background:#D8DCC6;opacity:.6"></span></div>
<main>
  <div class="pol"><img src="/img/wildhearts/viv-stretch.jpg" alt="Viv stretching"><span>back to you ♡</span></div>
  <?php if ($stay): ?><br><span class="invite">your gift: <?= $h($stayCode) ?> ♡</span><?php elseif ($ref): ?><br><span class="invite"><?= $h(forma_first_name((string)$ref['name'] ?: 'A friend')) ?> invited you ♡</span><?php endif; ?>
  <p class="hand"><?= $stay ? 'so happy you are staying' : ($ref ? 'a gift from a friend' : 'your monthly circle') ?></p>
  <h1><?= $h($o['title']) ?></h1>
  <p class="lead"><?= $h($o['text']) ?></p>
  <div class="price">
    <?php if ($stay): $sm = max(1, min($o['ref_months'], $o['max_months'] - (int)$stay['special_months'])); ?><span class="reg"><?= $h($reg) ?></span><b><?= $h($sp) ?></b><small>for <?= $sm ?> more months, then <?= $h($reg) ?> · code valid until <?= $h(date('j F Y', (int)$stay['stay_until'])) ?></small>
    <?php elseif ($ref): ?><span class="reg"><?= $h($reg) ?></span><b><?= $h($sp) ?></b><small>for your first <?= (int)$o['intro_months'] ?> months, then <?= $h($reg) ?> · cancel anytime</small>
    <?php else: ?><b><?= $h($reg) ?></b><small>cancel anytime</small><?php endif; ?>
  </div>
  <?php if ($startTs): ?><p class="invite" style="margin-top:18px">Opens on <?= $h(mm_start_en($startTs)) ?> · book now, nothing is charged before then</p><?php endif; ?>
  <ul>
    <li>a live circle every month with Viv and the other women</li>
    <li>breathwork and gentle movement for tired days</li>
    <li>a members area with rituals, affirmations and short sessions</li>
    <li>women who hold each other up, wherever you are right now</li>
  </ul>
  <?php if (isset($_GET['stay']) && !$stay): ?><p class="small" style="margin-top:22px">This code is no longer valid, so the regular price applies. You are always welcome to join again ♡</p><?php endif; ?>
  <?php if ($stay): ?>
  <form class="card" id="fs" novalidate>
    <p>Dear <?= $h(forma_first_name((string)$stay['name'] ?: 'love')) ?>, tap the button and your circle price continues right after your first months. Nothing else changes, and you can still cancel at any time.</p>
    <label class="cons"><input type="checkbox" name="consent" checked><span>If my membership has already ended: I would like my access to start right away. I know that if I withdraw within 14 days, I pay only for the part already used. <a href="/terms" target="_blank" rel="noopener">Terms &amp; cancellation</a></span></label>
    <button type="submit">Keep my circle price · <?= $h($sp) ?></button>
    <p class="msg" id="ms" hidden></p>
  </form>
  <?php else: ?>
  <form class="card" id="f" novalidate>
    <?php if (!$ref && $o['periods']): ?>
    <div class="plans">
      <label class="pl"><input type="radio" name="plan" value="full" checked><span><b><?= $h($reg) ?></b> monthly, cancel anytime</span></label>
      <?php foreach ($o['periods'] as $pp): ?><label class="pl"><input type="radio" name="plan" value="p<?= (int)$pp[0] ?>"><span><b><?= $h(mm_price_en($pp[1], 'once')) ?></b> <?= $pp[0] === 12 ? 'per year' : 'for 6 months' ?> · just <?= $h(mm_price_en((int)round($pp[1] / $pp[0]), 'monthly')) ?><?= $pp[0] === 12 ? ' <em>best value</em>' : '' ?></span></label><?php endforeach; ?>
      <p class="small" style="margin-top:6px">The Inner Circle grows with the Wheel of the Year: new rituals, sessions and downloads every month, and you always have the latest version.</p>
    </div>
    <?php endif; ?>
    <label class="f">First name<input type="text" name="name" autocomplete="given-name" required maxlength="80"></label>
    <label class="f">Email<input type="email" name="email" autocomplete="email" required maxlength="160"></label>
    <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
    <?php if ($startTs): ?>
    <label class="cons"><input type="checkbox" name="consent"><span>I book The Inner Circle with a binding commitment. It opens on <?= $h(mm_start_en($startTs)) ?>, and my first payment is taken then. <a href="/terms" target="_blank" rel="noopener">Terms &amp; cancellation</a></span></label>
    <?php else: ?>
    <label class="cons"><input type="checkbox" name="consent"><span>I would like my access to start right away. I know that if I withdraw within 14 days, I pay only for the part already used. <a href="/terms" target="_blank" rel="noopener">Terms &amp; cancellation</a></span></label>
    <?php endif; ?>
    <label class="cons"<?= $startTs ? ' hidden' : '' ?>><input type="checkbox" name="digital"><span>Optional: please unlock the course area and downloads right away. I understand that I lose my right of withdrawal for this digital content. Without this tick, I start with the starter section and everything unlocks after 14 days.</span></label>
    <button type="submit" id="jb">Join The Inner Circle · <?= $h($ref ? $sp : $reg) ?></button>
    <p class="msg" id="m" hidden></p>
    <p class="small">Secure payment with Stripe.<?= $startTs ? ' Today you only save your card. Your first payment is taken on ' . $h(mm_start_en($startTs)) . ', when The Inner Circle opens.' : '' ?> You can cancel at any time, effective at the end of the period you have paid.</p>
  </form>
  <?php endif; ?>
  <p class="small" style="margin-top:26px"><a href="/">moveciamovement.com</a> · <a href="/terms">Terms</a> · <a href="/withdraw">Withdraw from the contract</a></p>
</main>
<script>
(() => {
  const fs = document.getElementById('fs');
  if (fs) fs.addEventListener('submit', async e => {
    e.preventDefault();
    const b = fs.querySelector('button'), ms = document.getElementById('ms'); b.disabled = true; ms.hidden = true;
    try {
      const r = await fetch('/api/wh-circle.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'stay', stay: <?= json_encode($stay ? $stayCode : '') ?>, consent: fs.consent.checked ? 1 : 0})});
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || '');
      if (j.redirect) { location.href = j.redirect; return; }
      fs.innerHTML = '<p style="font-family:Hand,cursive;color:#5E6B35;font-size:22px">thank you ♡</p><p>You are staying! Your circle price continues. A confirmation is on its way to your inbox.</p>';
    } catch (err) { ms.hidden = false; ms.textContent = err.message || 'That did not work just now. Please try again.'; b.disabled = false; }
  });
  const f = document.getElementById('f'), m = document.getElementById('m'), t0 = Date.now();
  const PL = <?= json_encode(array_column(array_map(fn($pp) => ['p' . $pp[0], mm_price_en($pp[1], 'once') . ($pp[0] === 12 ? ' / year' : ' / 6 months')], $o['periods']), 1, 0) + ['full' => $ref ? $sp : $reg], JSON_UNESCAPED_UNICODE) ?>;
  if (f) f.querySelectorAll('[name=plan]').forEach(r => r.onchange = () => { document.getElementById('jb').textContent = 'Join The Inner Circle · ' + PL[r.value]; });
  if (f) f.addEventListener('submit', async e => {
    e.preventDefault();
    const b = f.querySelector('button'); b.disabled = true; m.hidden = true;
    try {
      const r = await fetch('/api/wh-circle.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({plan: (f.querySelector('[name=plan]:checked') || {}).value || 'full', name: f.name.value.trim(), email: f.email.value.trim(), consent: f.consent.checked ? 1 : 0, digital: f.digital.checked ? 1 : 0, website: f.website.value, ref: <?= json_encode($ref ? $code : '') ?>, elapsed: Date.now() - t0})});
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || '');
      location.href = j.redirect;
    } catch (err) { m.hidden = false; m.textContent = err.message || 'That did not work just now. Please try again.'; b.disabled = false; }
  });
})();
</script>
</body>
</html>

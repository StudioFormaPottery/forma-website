<?php
// moveciamovement.com/personal-training – Personal Training (allein oder zu zweit, 75 Min.) im Studio in Mathon (Vivien, 27.10.2026)
// Versteckt: nur per Link/QR vom Hotel-Flyer, nirgends verlinkt, noindex. Deutsch + Englisch (Umschalter oben).
//   ohne Parameter      Angebot + Anfrage (1–4 Einheiten, je Wunschtag + Uhrzeit)
//   ?o=ID&k=TOKEN       persönliche Seite des Gasts (Status, Termine, Bezahlen, Kalender)
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/ptb-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
ptb_schema($pdo);
ptb_tick($pdo);
$h = 'forma_h';

$order = null;
if (isset($_GET['o'])) {
    $order = ptb_order_auth($pdo, (int)$_GET['o'], (string)($_GET['k'] ?? ''));
    if ($order && $order['status'] === 'angebot' && $order['stripe_session'] !== '') { ptb_check_paid($pdo, $order); $order = ptb_order($pdo, (int)$order['id']); }
}
$badLink = isset($_GET['o']) && !$order;
$cfg = ptb_cfg();
$prices = ptb_price_table(1);
$prices2 = ptb_price_table(2);
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : (($_GET['lang'] ?? '') === 'de' ? 'de' : ($order ? (string)$order['lang'] : ''));
$cal = [
    'min' => date('Y-m-d', strtotime('+1 day')),
    'max' => date('Y-m-d', strtotime('+6 months')),
    'days' => $cfg['weekdays'],
    'blocked' => $cfg['blocked'],
    'times' => ptb_times(),
    'busy' => ptb_busy($pdo, date('Y-m-d'), date('Y-m-d', strtotime('+6 months'))),
    'prices' => [1 => $prices, 2 => $prices2],
    'minutes' => PTB_MINUTES,
    'max_units' => PTB_MAX_UNITS,
];
$sessions = $order ? ptb_sessions($pdo, (int)$order['id']) : [];
$wa = 'https://wa.me/' . preg_replace('/\D/', '', NATAL_COMPANY['phone']);
$L = fn(string $de, string $en) => '<span class="de">' . $de . '</span><span class="en">' . $en . '</span>';
$single = $prices[1]['unit'];
$two = $prices2[1]['unit'];
$disc = $prices[PTB_MAX_UNITS]['unit'];
?><!doctype html>
<html lang="de" data-l="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $order ? 'Deine Termine · Your sessions · Movecia' : 'Personal Training in Mathon · Movecia' ?></title>
<meta name="description" content="Personal Training 1:1 mit Vivien im kleinen Studio in Mathon, wenige Minuten von Ischgl. 75 Minuten, ganz auf dich abgestimmt, allein oder zu zweit. Personal training in Mathon near Ischgl, one-to-one or for two.">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#F2ECE4">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="manifest" href="/app.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Movecia"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="/wildhearts/assets/app.js?v=2" defer></script>
<script>(function(){var l=<?= json_encode($lang) ?>;try{if(!l)l=localStorage.getItem('wh-lang')||''}catch(e){}if(!l)l=(navigator.language||'').toLowerCase().indexOf('de')===0?'de':'en';document.documentElement.dataset.l=l;document.documentElement.lang=l;})();</script>
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--bg:#F2ECE4;--sand:#E8DED2;--card:#FBF8F4;--ink:#2E2529;--muted:#7D7076;--line:#DDD2C6;--plum:#7D5E68;--plumdeep:#6B5658;--dark:#4A3A41;--blush:#EADFDE;--peach:#E8D6CA;--on:#FBF5F7;--r:24px;
--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
html[data-l=de] .en,html[data-l=en] .de{display:none!important}
*{box-sizing:border-box}
[hidden]{display:none!important}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
@media(min-width:900px){body{font-size:19px}}
.w{padding-left:max(16px,env(safe-area-inset-left));padding-right:max(16px,env(safe-area-inset-right))}
@media(min-width:900px){.w{padding-left:64px;padding-right:64px}}
h1,h2,h3{font-family:var(--serif);font-weight:500;line-height:1.04;margin:0;text-wrap:balance}
h1{font-size:clamp(2.9rem,7vw,6.2rem);letter-spacing:-.01em}
h2{font-size:clamp(2.2rem,4.4vw,3.8rem)}
h3{font-size:1.7rem;line-height:1.1}
em{font-style:italic;font-weight:400;color:var(--plum)}
p{margin:0}
a{color:var(--plum)}
.k{font-size:.74rem;letter-spacing:.2em;text-transform:uppercase;font-weight:500;color:var(--plum)}
.mut{color:var(--muted)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.6em;padding:1em 1.7em;border-radius:999px;background:var(--plum);color:var(--on);font:inherit;font-size:.95rem;font-weight:500;text-decoration:none;border:1px solid var(--plum);cursor:pointer;transition:transform .25s,box-shadow .25s}
.btn:hover{transform:translateY(-2px);box-shadow:0 12px 24px -12px var(--plum)}
.btn.line{background:transparent;color:var(--ink);border-color:var(--ink)}
.btn[disabled]{opacity:.55;cursor:wait;transform:none}
:focus-visible{outline:2px solid var(--plum);outline-offset:3px}
section{padding-block:clamp(3.2rem,7vw,6rem)}
.top{position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:1rem;padding-block:1rem;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
.brand{font-family:var(--serif);font-size:1.9rem;font-weight:500;color:var(--ink);text-decoration:none;line-height:1;display:flex;align-items:baseline}
.brand i{width:.3em;height:.3em;border-radius:50%;background:var(--plum);display:inline-block;margin-left:.08em}
.brand .mk{width:1.35em;height:1.35em;margin-right:.32em;align-self:center;flex:none}
.top-r{display:flex;align-items:center;gap:.7rem}
.lang{display:flex;background:var(--card);border:1px solid var(--line);border-radius:999px;padding:3px}
.lang button{font:inherit;font-size:.82rem;border:0;background:none;border-radius:999px;padding:.35em .85em;cursor:pointer;color:var(--muted)}
.lang button[aria-pressed=true]{background:var(--plum);color:var(--on)}
.blob{border-radius:62% 38% 46% 54%/55% 45% 55% 45%;animation:morph 14s ease-in-out infinite alternate}
@keyframes morph{50%{border-radius:40% 60% 58% 42%/44% 58% 42% 56%}100%{border-radius:45% 55% 60% 40%/40% 60% 45% 55%}}
@media(prefers-reduced-motion:reduce){.blob{animation:none!important}}
.hero{display:grid;gap:2.5rem;align-items:center;padding-block:clamp(1.6rem,4vw,4rem) clamp(3rem,6vw,5rem)}
@media(min-width:1000px){.hero{grid-template-columns:1.2fr 1fr;gap:4rem}}
.script{font-family:var(--serif);font-style:italic;font-size:clamp(1.5rem,2.4vw,2.1rem);color:var(--plum);margin-top:.6rem}
.lead{font-size:clamp(1.05rem,1.4vw,1.3rem);font-weight:300;color:var(--muted);max-width:32em;margin-top:1.4rem}
.facts{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.6rem}
.facts span{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem 1rem;border-radius:999px;background:var(--card);font-size:.92rem}
.facts span b{font-family:var(--serif);font-weight:500;font-size:1.15rem;color:var(--plum)}
.cta{display:flex;flex-wrap:wrap;gap:.8rem;margin-top:2rem}
.hv{position:relative;justify-self:center;width:min(100%,460px);margin:0;padding:0 0 16% 0}
.hv img{position:relative;display:block;width:82%;height:auto;margin-left:12%;aspect-ratio:3/4;object-fit:cover;object-position:50% 85%;border-radius:240px 240px 24px 24px}
.hv .b1{position:absolute;inset:6% 0 20% 2%;background:var(--blush)}
.hv .b3{position:absolute;width:22%;aspect-ratio:1;left:0;top:4%;background:var(--peach);border-radius:50%}
.hv .q{position:absolute;left:0;right:12%;bottom:0;background:rgba(251,248,244,.96);border-radius:20px;padding:1rem 1.2rem;font-family:var(--serif);font-size:clamp(1.2rem,2vw,1.5rem);line-height:1.25;color:var(--ink);box-shadow:0 14px 30px -20px rgba(74,58,65,.5)}
.hv .q small{display:block;font-family:var(--sans);font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--plum);margin-top:.5rem}
@media(max-width:599px){.hv{width:min(100%,330px)}}
.mq{overflow:hidden;background:var(--plum);color:var(--on);padding-block:1rem}
.mq-in{display:flex;gap:2.4rem;width:max-content;animation:mq 40s linear infinite;font-family:var(--serif);font-style:italic;font-size:clamp(1.4rem,2.4vw,2rem)}
.mq-in span::after{content:"✦";font-style:normal;font-size:.55em;margin-left:2.4rem;opacity:.6;vertical-align:middle}
@keyframes mq{to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.mq-in{animation:none}}
.four{display:grid;gap:14px;margin-top:2.4rem}
@media(min-width:700px){.four{grid-template-columns:1fr 1fr}}
@media(min-width:1200px){.four{grid-template-columns:repeat(4,1fr)}}
.card{background:var(--card);border-radius:var(--r);padding:clamp(1.4rem,2.6vw,2rem)}
.card .ic{width:44px;height:44px;border-radius:50%;background:var(--blush);display:grid;place-items:center;margin-bottom:1rem}
.card .ic svg{width:22px;height:22px;stroke:var(--plum)}
.card h3{font-size:1.5rem;margin-bottom:.5rem}
.card p{color:var(--muted);font-size:.95rem}
.pics{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}.pics figure{margin:0;border-radius:24px;overflow:hidden;aspect-ratio:4/5;background:var(--peach)}.pics img{display:block;width:100%;height:100%;object-fit:cover}@media(min-width:1000px){.pics figure{aspect-ratio:16/11}.pics figure:first-child img{object-position:50% 60%}.pics figure:last-child img{object-position:50% 72%}}
.wide-pic{margin:16px 0 0;border-radius:24px;overflow:hidden;aspect-ratio:21/8;background:var(--peach)}.wide-pic img{display:block;width:100%;height:100%;object-fit:cover;object-position:50% 60%}@media(max-width:700px){.wide-pic{aspect-ratio:4/3}}
.steps{display:grid;gap:14px;margin-top:2.4rem;counter-reset:s}
@media(min-width:900px){.steps{grid-template-columns:repeat(4,1fr)}}
.step{background:var(--card);border-radius:var(--r);padding:1.5rem;position:relative;overflow:hidden}
.step::before{counter-increment:s;content:counter(s);position:absolute;right:1.1rem;top:.3rem;font-family:var(--serif);font-size:4.2rem;color:var(--blush);line-height:1}
.step b{display:block;font-family:var(--serif);font-size:1.5rem;font-weight:500;line-height:1.15;margin-bottom:.4rem;position:relative;padding-right:2.4rem}
.step p{color:var(--muted);position:relative;font-size:.95rem}
/* Buchen */
.packs{display:grid;gap:10px;margin-top:1.8rem;grid-template-columns:repeat(2,minmax(0,1fr))}
@media(min-width:1000px){.packs{grid-template-columns:repeat(4,1fr);gap:14px}}
@media(min-width:1000px){.packs.who{grid-template-columns:repeat(2,1fr);max-width:760px}}
.pack [hidden]{display:none}
.pack{position:relative;display:flex;flex-direction:column;gap:.3rem;background:var(--card);border-radius:var(--r);padding:1.3rem 1.3rem 1.2rem;cursor:pointer;border:2px solid transparent;transition:border-color .2s,transform .25s}
.pack:hover{transform:translateY(-3px)}
.pack input{position:absolute;opacity:0;pointer-events:none}
.pack:has(input:checked){border-color:var(--plum);background:#fff}
.pack:has(input:focus-visible){outline:2px solid var(--plum);outline-offset:3px}
.pack .t{font-family:var(--serif);font-size:1.5rem;font-weight:500;line-height:1.1}
.pack .p{font-family:var(--serif);font-size:2.5rem;font-weight:500;line-height:1;margin-top:auto;padding-top:.7rem}
.pack .u{font-size:.85rem;color:var(--muted)}
.pack .was{font-size:.9rem;color:var(--muted);text-decoration:line-through;margin-top:auto;padding-top:.7rem}
.pack .was + .p{margin-top:0;padding-top:.1rem}
.pack .badge{align-self:flex-start;background:var(--peach);color:var(--plumdeep);font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;font-weight:600;padding:.35em .8em;border-radius:99px;margin-bottom:.2rem}
.book{display:grid;gap:16px;margin-top:1.6rem}
@media(min-width:1000px){.book{grid-template-columns:1.6fr 1fr;align-items:start}}
.panel{background:var(--card);border-radius:28px;padding:clamp(1.3rem,3vw,2.3rem);display:flex;flex-direction:column;gap:1.2rem;min-width:0}
.panel h3{font-size:1.55rem}
.grid2{display:grid;gap:12px}
@media(min-width:640px){.grid2{grid-template-columns:1fr 1fr}}
label.f{display:flex;flex-direction:column;gap:.35rem;font-size:.86rem;color:var(--muted)}
input[type=text],input[type=email],input[type=tel],textarea{font:inherit;font-size:1rem;color:var(--ink);background:var(--bg);border:1px solid transparent;border-radius:14px;padding:.8em 1em;width:100%;min-width:0}
input:focus,textarea:focus{border-color:var(--plum);outline:none}
textarea{min-height:84px;resize:vertical}
.chk{display:flex;gap:.7rem;font-size:.88rem;color:var(--muted);align-items:flex-start}
.chk input{margin-top:.3em;accent-color:var(--plum);width:18px;height:18px;flex:none}
.side{background:var(--plumdeep);color:var(--on);border-radius:28px;padding:clamp(1.4rem,3vw,2.2rem);display:flex;flex-direction:column;gap:1rem;position:relative;overflow:hidden}
@media(min-width:1000px){.side{position:sticky;top:90px}}
.side .bl{position:absolute;width:200px;height:190px;right:-70px;bottom:-80px;background:color-mix(in srgb,var(--plum) 80%,transparent)}
.side>*{position:relative}
.side .k{color:var(--peach)}
.side .amt{font-family:var(--serif);font-size:clamp(3rem,6vw,4.4rem);font-weight:500;line-height:1}
.side .ln{display:flex;justify-content:space-between;gap:1rem;font-size:.92rem;border-top:1px solid rgba(251,245,247,.18);padding-top:.7rem}
.side .ln span:first-child{color:rgba(251,245,247,.75)}
.side .btn{background:var(--on);color:var(--plumdeep);border-color:var(--on)}
.side .small{font-size:.8rem;color:rgba(251,245,247,.78);line-height:1.5}
.err{background:#F6E1DC;color:#7A2E22;border-radius:14px;padding:.8rem 1rem;font-size:.92rem}
.ok{background:var(--blush);border-radius:18px;padding:1rem 1.2rem}
/* Kalender */
.calw{display:grid;gap:1.2rem}
@media(min-width:800px){.calw{grid-template-columns:minmax(0,380px) minmax(0,1fr);gap:1.6rem}}
.calh{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}
.calh b{font-family:var(--serif);font-size:1.4rem;font-weight:500}
.calh button{border:0;background:var(--bg);color:var(--ink);border-radius:50%;width:40px;height:40px;cursor:pointer;font:inherit;font-size:1.1rem}
.calh button[disabled]{opacity:.3;cursor:default}
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;font-variant-numeric:tabular-nums}
.cal .dw{font-size:.72rem;color:var(--muted);padding-bottom:.2rem}
.cal button{aspect-ratio:1;border:0;border-radius:50%;background:transparent;color:#C9BFB4;font:inherit;font-size:.92rem;padding:0;position:relative}
.cal button.ok{color:var(--ink);background:var(--bg);cursor:pointer;font-weight:500}
.cal button.ok:hover{background:var(--blush)}
.cal button.cur{outline:2px solid var(--plum);outline-offset:-2px}
.cal button.sel{background:var(--plum);color:var(--on)}
.units{display:flex;flex-direction:column;gap:8px}
.ur{background:var(--bg);border-radius:16px;padding:.75rem .9rem;display:flex;flex-direction:column;gap:.55rem;border:2px solid transparent;cursor:pointer}
.ur.act{border-color:var(--plum);background:#fff}
.ur .row{display:flex;justify-content:space-between;align-items:center;gap:.6rem}
.ur .n{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;color:var(--plum);font-weight:600}
.ur .v{font-weight:500}
.ur .v.mut{font-weight:400}
.tm{display:flex;flex-wrap:wrap;gap:6px}
.tm button{border:0;background:var(--card);color:var(--ink);font:inherit;font-size:.86rem;padding:.45em .85em;border-radius:999px;cursor:pointer;font-variant-numeric:tabular-nums}
.tm button[aria-pressed=true]{background:var(--plum);color:var(--on)}
.tm button:disabled{opacity:.35;cursor:default;text-decoration:line-through}
.hint{font-size:.85rem;color:var(--muted)}
/* persönliche Seite */
.state{display:grid;gap:16px}
@media(min-width:1000px){.state{grid-template-columns:1.5fr 1fr;align-items:start}}
.sl{display:flex;flex-direction:column;gap:8px}
.si{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;background:var(--bg);border-radius:16px;padding:.85rem 1rem}
.si b{font-weight:500}
.si small{display:block;color:var(--muted);font-size:.82rem}
.chip{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;padding:.35em .8em;border-radius:999px;background:var(--line);color:var(--ink);font-weight:500;white-space:nowrap}
.chip.fix{background:var(--plum);color:var(--on)}.chip.neu{background:var(--peach);color:var(--plumdeep)}
.faq details{border-top:1px solid var(--line);padding-block:1.1rem}
.faq details:last-child{border-bottom:1px solid var(--line)}
.faq summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:1rem;font-family:var(--serif);font-size:1.4rem;line-height:1.25}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"+";font-family:var(--sans);font-weight:300;color:var(--plum)}
.faq details[open] summary::after{content:"–"}
.faq p{margin-top:.6rem;color:var(--muted);max-width:62ch}
.two{display:grid;gap:2rem}
@media(min-width:1000px){.two{grid-template-columns:1fr 1.4fr;gap:4rem}}
footer{background:var(--sand);color:var(--muted);padding-block:2.6rem;font-size:.88rem}
footer a{color:inherit}
footer details{margin-top:.8rem}
footer summary{cursor:pointer}
.hp{position:absolute;left:-5000px}
@media(max-width:560px){.top .tag{display:none}}
.top .tag{font-size:.8rem;color:var(--muted);letter-spacing:.06em}
</style>
<script src="/wildhearts/assets/schutz.js?v=3"></script>
</head>
<body>
<header class="top w"><a class="brand" href="<?= $order ? $h(ptb_link($order)) : '/personal-training/' ?>"><img class="mk" src="/img/movecia/movecia-zeichen-klein.svg" alt="">Movecia</a>
  <span class="top-r"><span class="tag">Personal Training · Mathon</span>
    <span class="lang" role="group" aria-label="Sprache / Language"><button type="button" data-set="de">DE</button><button type="button" data-set="en">EN</button></span>
    <?php require_once dirname(__DIR__) . '/assets/menu.inc.php'; mv_menu('', ['links' => false]); ?></span></header>

<?php if ($badLink): ?>
<main class="w"><section style="max-width:720px">
  <h1><?= $L('Hoppla.', 'Oops.') ?></h1>
  <p class="lead"><?= $L('Dieser Link funktioniert leider nicht. Schreib mir einfach kurz, dann schicke ich dir deinen persönlichen Link noch einmal.', "Sorry, this link doesn't work. Just send me a quick message and I'll send you your personal link again.") ?></p>
  <div class="cta"><a class="btn" href="<?= $h($wa) ?>">WhatsApp</a><a class="btn line" href="/personal-training/"><?= $L('Neue Anfrage', 'New request') ?></a></div>
</section></main>

<?php elseif ($order): $st = (string)$order['status']; $en = ptb_en($order); $act = array_values(array_filter($sessions, fn($s) => $s['status'] !== 'gestrichen')); ?>
<main class="w"><section>
  <p class="k"><?= $L('Personal Training · deine Seite', 'Personal training · your page') ?></p>
  <h1 style="margin-top:.6rem;font-size:clamp(2.6rem,6vw,5rem)"><?php if ($st === 'email'): ?><?= $L('Fast geschafft', 'Almost there') ?><?php elseif ($st === 'anfrage'): ?><?= $L('Danke, ', 'Thank you, ') . $h(forma_first_name((string)$order['name'])) ?><?php elseif ($st === 'angebot'): ?><?= $L('Deine Termine', 'Your times') ?><?php elseif ($st === 'bezahlt'): ?><?= $L('Du bist gebucht', "You're booked") ?><?php else: ?><?= $L('Diese Anfrage ist geschlossen', 'This request is closed') ?><?php endif; ?></h1>

  <div class="state" style="margin-top:2rem">
    <div class="panel">
      <?php if ($st === 'email'): ?>
        <?php if (isset($_GET['neu'])): ?><p class="ok"><?= $L('Deine Anfrage ist gespeichert.', 'Your request has been saved.') ?></p><?php endif; ?>
        <h3><?= $L('Bitte bestätige deine E-Mail-Adresse', 'Please confirm your email address') ?></h3>
        <p><?= $L('Ich habe dir gerade eine Mail an <b>' . $h($order['email']) . '</b> geschickt. Tipp dort auf „E-Mail-Adresse bestätigen“, dann landet deine Anfrage bei mir und ich schaue mir deine Wunschtermine an.', 'I\'ve just sent an email to <b>' . $h($order['email']) . '</b>. Tap "Confirm my email" in it and your request will reach me, then I\'ll check your preferred times.') ?></p>
        <p class="hint"><?= $L('Nichts angekommen? Schau bitte auch im Spam-Ordner nach.', 'Nothing there? Please check your spam folder too.') ?></p>
        <div><button class="btn line" type="button" id="resend"><?= $L('Mail noch einmal schicken', 'Send the email again') ?></button></div>
        <p class="err" id="rserr" hidden></p>
      <?php elseif ($st === 'anfrage'): ?>
        <?php if (isset($_GET['ok'])): ?><p class="ok"><?= $L('Deine E-Mail-Adresse ist bestätigt.', 'Your email address is confirmed.') ?></p><?php endif; ?>
        <h3><?= $L('Deine Wunschtermine sind bei mir', 'Your preferred times are with me') ?></h3>
        <div class="sl"><?php foreach ($act as $s): ?><div class="si"><b><span class="de"><?= $h(ptb_when((string)$s['wish_date'], (string)$s['wish_time'])) ?></span><span class="en"><?= $h(ptb_when((string)$s['wish_date'], (string)$s['wish_time'], true)) ?></span></b><span class="chip neu"><?= $L('Wunsch', 'requested') ?></span></div><?php endforeach; ?></div>
        <p><?= $L('Ich schaue in meinen Kalender und melde mich per E-Mail, meistens innerhalb eines Tages. Passt ein Termin nicht, schlage ich dir einen anderen vor. Bezahlen musst du erst, wenn deine Termine bestätigt sind.', "I'll check my calendar and get back to you by email, usually within a day. If a time doesn't work, I'll suggest another. You only pay once your times are confirmed.") ?></p>
      <?php elseif ($st === 'angebot'): ?>
        <?php if (isset($_GET['abgebrochen'])): ?><p class="err"><?= $L('Die Zahlung wurde abgebrochen. Du kannst es jederzeit noch einmal versuchen.', 'The payment was cancelled. You can try again any time.') ?></p><?php endif; ?>
        <h3><?= $L('Diese Termine halte ich für dich frei', "I'm holding these times for you") ?></h3>
        <div class="sl"><?php foreach ($act as $s): ?><div class="si"><span><b><span class="de"><?= $h(ptb_when((string)$s['date'], (string)$s['start'])) ?></span><span class="en"><?= $h(ptb_when((string)$s['date'], (string)$s['start'], true)) ?></span></b>
          <?php if ($s['status'] === 'vorschlag'): ?><small><span class="de">statt <?= $h(ptb_when((string)$s['wish_date'], (string)$s['wish_time'])) ?></span><span class="en">instead of <?= $h(ptb_when((string)$s['wish_date'], (string)$s['wish_time'], true)) ?></span></small><?php endif; ?></span>
          <span class="chip <?= $s['status'] === 'vorschlag' ? 'neu' : 'fix' ?>"><?= $s['status'] === 'vorschlag' ? $L('neuer Vorschlag', 'new suggestion') : $L('dein Wunsch', 'as requested') ?></span></div><?php endforeach; ?></div>
        <?php if ((string)$order['offer_note'] !== ''): ?><p class="ok"><?= nl2br($h((string)$order['offer_note'])) ?></p><?php endif; ?>
        <?php if (count($sessions) > count($act)): ?><p class="hint"><?= $L('Leider kann ich nicht alle Einheiten unterbringen, deshalb stehen hier nur die Termine, die sich ausgehen.', "Unfortunately I couldn't fit in every session, so only the times that work are listed here.") ?></p><?php endif; ?>
        <p><?= $L('Passt dir eine Zeit nicht? Schreib mir einfach, dann finden wir eine andere.', "Doesn't a time suit you? Just message me and we'll find another.") ?> <a href="<?= $h($wa) ?>">WhatsApp</a> · <a href="mailto:<?= $h(NATAL_COMPANY['email']) ?>"><?= $h(NATAL_COMPANY['email']) ?></a></p>
      <?php elseif ($st === 'bezahlt'): ?>
        <?php if (isset($_GET['danke'])): ?><p class="ok"><?= $L('Danke! Deine Zahlung ist angekommen, die Bestätigung ist per E-Mail unterwegs.', 'Thank you! Your payment has arrived and the confirmation is on its way by email.') ?></p><?php endif; ?>
        <h3><?= $L('Deine Termine', 'Your sessions') ?></h3>
        <div class="sl"><?php foreach ($act as $s): $past = $s['status'] === 'erledigt'; ?><div class="si"><span><b><span class="de"><?= $h(ptb_when((string)$s['date'], (string)$s['start'])) ?></span><span class="en"><?= $h(ptb_when((string)$s['date'], (string)$s['start'], true)) ?></span></b><small><?= $L($h(ptb_kind($order, false, true)), $h(ptb_kind($order, true, true))) ?> · Movecia Studio, <?= $h(NATAL_COMPANY['street'] . ', ' . NATAL_COMPANY['city']) ?></small></span>
          <?php if ($past): ?><span class="chip"><?= $L('war schön', 'done') ?></span><?php else: ?><a class="chip fix" style="text-decoration:none" href="<?= $h(ptb_gcal($s, $en)) ?>" target="_blank" rel="noopener">+ Google</a><?php endif; ?></div><?php endforeach; ?></div>
        <div class="cta" style="margin-top:.4rem"><a class="btn" href="/api/ptb.php?ics=1&amp;o=<?= (int)$order['id'] ?>&amp;k=<?= $h($order['token']) ?>"><?= $L('In meinen Kalender', 'Add to my calendar') ?></a><a class="btn line" href="<?= $h($wa) ?>">WhatsApp</a></div>
        <p class="hint"><?= $L('Komm in bequemer Kleidung und bring eine Flasche Wasser mit. Verschieben oder absagen kannst du bis 24 Stunden vorher kostenlos, schreib mir dafür einfach.', 'Wear something comfortable and bring a bottle of water. You can reschedule or cancel free of charge up to 24 hours before, just send me a message.') ?></p>
      <?php else: ?>
        <p><?php if ((string)$order['decline_note'] !== ''): ?><?= nl2br($h((string)$order['decline_note'])) ?><br><br><?php endif; ?><?= $L('Wenn du andere Tage ausprobieren möchtest, schick mir gern eine neue Anfrage.', "If you'd like to try other days, you're very welcome to send a new request.") ?></p>
        <div><a class="btn" href="/personal-training/"><?= $L('Neue Anfrage', 'New request') ?></a></div>
      <?php endif; ?>
    </div>
    <aside class="side"><div class="bl blob"></div>
      <p class="k">Personal Training <?= ptb_persons($order) === 2 ? $L('zu zweit', 'for two') : '1:1' ?></p>
      <?php $n = count($act); $unit = $st === 'angebot' || $st === 'bezahlt' ? (int)$order['unit_cents'] : ptb_unit_cents(max(1, $n), ptb_persons($order)); ?>
      <div class="amt"><?= $h(forma_euro($unit * max(1, $n))) ?></div>
      <div class="ln"><span><?= $L('Einheiten', 'Sessions') ?></span><span><?= $n ?> × <?= PTB_MINUTES ?> <?= $L('Min.', 'min') ?></span></div>
      <div class="ln"><span><?= $L('pro Einheit', 'per session') ?></span><span><?= $h(forma_euro($unit)) ?></span></div>
      <div class="ln"><span><?= $L('Status', 'Status') ?></span><span><?php if ($st === 'bezahlt'): ?><?= $L('bezahlt', 'paid') ?><?php elseif ($st === 'angebot'): ?><?= $L('bereit zum Buchen', 'ready to book') ?><?php elseif ($st === 'anfrage'): ?><?= $L('ich prüfe gerade', 'being checked') ?><?php elseif ($st === 'email'): ?><?= $L('E-Mail bestätigen', 'confirm email') ?><?php else: ?><?= $L('geschlossen', 'closed') ?><?php endif; ?></span></div>
      <?php if ($st === 'angebot'): ?>
        <button class="btn" type="button" id="pay"><?= $L('Jetzt bezahlen', 'Pay now') ?> <?= $h(forma_euro((int)$order['amount_cents'])) ?></button>
        <p class="err" id="payerr" hidden></p>
        <p class="small"><?= $L('Sichere Zahlung mit Karte, Apple Pay oder Google Pay über Stripe. Erst mit der Zahlung sind die Termine fix für dich reserviert. Du bekommst eine Rechnung per E-Mail.', 'Secure payment by card, Apple Pay or Google Pay via Stripe. Your times are only fixed once you have paid. You will receive an invoice by email.') ?></p>
      <?php elseif ($st === 'anfrage' || $st === 'email'): ?>
        <p class="small"><?= $L('Du bezahlst erst, wenn ich deine Termine bestätigt habe.', 'You only pay once I have confirmed your times.') ?></p>
      <?php endif; ?>
    </aside>
  </div>
</section></main>

<?php else: ?>
<main>
  <section class="hero w">
    <div>
      <p class="k">Personal Training · Mathon · Paznaun</p>
      <h1 style="margin-top:.8rem"><?= $L('Zeit, die nur<br>dir <em>gehört.</em>', 'Time that is<br>all <em>yours.</em>') ?></h1>
      <p class="script"><?= $L('Bewegung, die sich gut anfühlt, mitten in deinem Urlaub.', 'Movement that feels good, right in the middle of your holiday.') ?></p>
      <p class="lead"><?= $L('Ganz persönlich auf deinen Körper und deinen Tag abgestimmt, ob du gerade von der Piste kommst oder einfach in Bewegung bleiben möchtest. In meinem kleinen, privaten Studio in Mathon, ruhig und ganz ohne Fitnessstudio-Trubel.', "Tailored to your body and your day, whether you've just come off the slopes or simply want to keep moving while you're away. In my small private studio in Mathon, calm and crowd-free.") ?></p>
      <div class="facts"><span><b><?= PTB_MINUTES ?></b> <?= $L('Minuten', 'minutes') ?></span><span><b><?= $h(forma_euro($single)) ?></b> <?= $L('pro Einheit', 'per session') ?></span><span><b><?= $h(forma_euro($two)) ?></b> <?= $L('zu zweit', 'for two') ?></span></div>
      <div class="cta"><a class="btn" href="#buchen"><?= $L('Wunschtermin wählen', 'Choose your time') ?></a><a class="btn line" href="#so"><?= $L('So läuft es ab', 'How it works') ?></a></div>
    </div>
    <figure class="hv"><div class="b1 blob"></div><div class="b3"></div><img src="/img/movecia/fotos/pt-baum.jpg" alt="<?= $h('Vivien im Movecia Studio') ?>" width="940" height="1400" fetchpriority="high"><div class="q"><span class="de">„Du musst nichts können. Du darfst einfach ankommen.“</span><span class="en">“You don't need to be good at anything. Just come as you are.”</span><small>Vivien · Movecia</small></div></figure>
  </section>

  <div class="mq" aria-hidden="true"><div class="mq-in"><?php for ($i = 0; $i < 2; $i++): ?><span class="de">Mobilität</span><span class="en">Mobility</span><span class="de">Kraft</span><span class="en">Strength</span><span class="de">Atem</span><span class="en">Breath</span><span class="de">Entspannung</span><span class="en">Relaxation</span><span class="de">Bewegung beginnt bei dir</span><span class="en">Movement begins with you</span><?php endfor; ?></div></div>

  <section class="w" id="was">
    <p class="k"><?= $L('Was wir machen', 'What we do') ?></p>
    <h2 style="margin-top:.6rem"><?= $L('Genau das, was dir <em>heute</em> guttut', 'Exactly what feels good <em>today</em>') ?></h2>
    <div class="four">
      <div class="card"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round"><path d="M4 18c3-8 13-8 16 0"/><path d="M8 9c1-3 7-3 8 0"/></svg></div><h3><?= $L('Mobilität nach dem Skitag', 'Mobility after skiing') ?></h3><p><?= $L('Hüften, Beine und Rücken lösen, damit du morgen wieder gern auf die Piste gehst.', 'Loosen tight hips, legs and back so you are ready for the slopes again tomorrow.') ?></p></div>
      <div class="card"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round"><path d="M6 12h12M4 9v6M20 9v6M2 11v2M22 11v2"/></svg></div><h3><?= $L('Kraft und Stabilität', 'Strength and stability') ?></h3><p><?= $L('Eine starke Mitte und ein stabiler Rücken, mit dem eigenen Körpergewicht oder kleinen Gewichten.', 'A strong core and a stable back, with your own bodyweight or light weights.') ?></p></div>
      <div class="card"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round"><path d="M3 12c3-4 6-4 9 0s6 4 9 0"/><path d="M3 17c3-4 6-4 9 0s6 4 9 0" opacity=".5"/></svg></div><h3><?= $L('Atem und Entspannung', 'Breath and relaxation') ?></h3><p><?= $L('Runterkommen, durchatmen und den Kopf frei bekommen. Auch im Urlaub braucht das Nervensystem Pausen.', 'Slow down, breathe deeply and clear your head. Your nervous system needs a break on holiday too.') ?></p></div>
      <div class="card"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round"><circle cx="12" cy="7" r="3"/><path d="M5 20c1-4 4-6 7-6s6 2 7 6"/></svg></div><h3><?= $L('Für jedes Level', 'For every level') ?></h3><p><?= $L('Egal, ob du lange nichts gemacht hast oder regelmäßig trainierst. Ich passe jede Übung an dich an.', "Whether you haven't exercised in a while or train regularly, I adapt every exercise to you.") ?></p></div>
    </div>
    <div class="pics"><figure><img src="/img/movecia/fotos/pt-kamel.jpg" alt="" width="940" height="1400" loading="lazy"></figure><figure><img src="/img/movecia/fotos/pt-kind.jpg" alt="" width="940" height="1400" loading="lazy"></figure></div>
  </section>

  <section class="w" id="so" style="padding-top:0">
    <p class="k"><?= $L('So läuft es ab', 'How it works') ?></p>
    <h2 style="margin-top:.6rem"><?= $L('In vier kleinen Schritten', 'Four simple steps') ?></h2>
    <div class="steps">
      <div class="step"><b><?= $L('Wunschtermine wählen', 'Pick your times') ?></b><p><?= $L('Allein oder zu zweit? Wähl, wie viele Einheiten du möchtest, und für jede einen Tag und eine Uhrzeit.', 'On your own or for two? Choose how many sessions you would like, and a day and time for each.') ?></p></div>
      <div class="step"><b><?= $L('E-Mail bestätigen', 'Confirm your email') ?></b><p><?= $L('Ein Tipp in der Mail, die ich dir schicke, damit ich weiß, dass sie bei dir ankommt.', 'One tap in the email I send you, so I know it reaches you.') ?></p></div>
      <div class="step"><b><?= $L('Ich bestätige', 'I confirm') ?></b><p><?= $L('Ich schaue in meinen Kalender. Passt eine Zeit nicht, schlage ich dir eine andere vor.', "I check my calendar. If a time doesn't work, I suggest another.") ?></p></div>
      <div class="step"><b><?= $L('Bezahlen, fertig', 'Pay and you are set') ?></b><p><?= $L('Erst jetzt bezahlst du online. Danach sind deine Termine fix und kommen direkt in deinen Kalender.', 'Only now do you pay online. Then your times are fixed and go straight into your calendar.') ?></p></div>
    </div>
  </section>

  <section class="w" id="buchen" style="padding-top:0">
    <p class="k"><?= $L('Termin anfragen', 'Request your sessions') ?></p>
    <h2 style="margin-top:.6rem"><?= $L('Allein oder zu zweit?', 'On your own or for two?') ?></h2>
    <div class="packs who" role="radiogroup">
      <label class="pack"><input type="radio" name="persons" value="1" checked><span class="t"><?= $L('Nur ich', 'Just me') ?></span><span class="u"><?= $L('1:1, ganz auf dich abgestimmt', 'One-to-one, tailored to you') ?></span><span class="p"><?= $h(forma_euro($single)) ?></span><span class="u"><?= $L('pro Einheit · ' . PTB_MINUTES . ' Min.', 'per session · ' . PTB_MINUTES . ' min') ?></span></label>
      <label class="pack"><input type="radio" name="persons" value="2"><span class="t"><?= $L('Zu zweit', 'For two') ?></span><span class="u"><?= $L('mit Partner, Freundin oder Familie', 'with your partner, a friend or family') ?></span><span class="p"><?= $h(forma_euro($two)) ?></span><span class="u"><?= $L('pro Einheit für euch beide · ' . PTB_MINUTES . ' Min.', 'per session for both of you · ' . PTB_MINUTES . ' min') ?></span></label>
    </div>
    <h2 style="margin-top:2.4rem"><?= $L('Wie viele Einheiten möchtest du?', 'How many sessions would you like?') ?></h2>
    <div class="packs" id="packs" role="radiogroup">
      <?php foreach ($prices as $n => $pr): ?>
        <label class="pack"><input type="radio" name="units" value="<?= $n ?>" <?= $n === 1 ? 'checked' : '' ?>>
          <?php if ($pr['unit'] < $single): ?><span class="badge" data-solo><?= $L('Bestpreis', 'Best value') ?></span><?php endif; ?>
          <span class="t"><?= $n ?> <?= $n === 1 ? $L('Einheit', 'session') : $L('Einheiten', 'sessions') ?></span>
          <span class="u"><?= $n ?> × <?= PTB_MINUTES ?> <?= $L('Minuten', 'minutes') ?><?php if ($n > 1): ?>, <?= $L('gern an verschiedenen Tagen', 'on different days if you like') ?><?php endif; ?></span>
          <?php if ($pr['unit'] < $single): ?><span class="was" data-solo><?= $h(forma_euro($single * $n)) ?></span><?php endif; ?>
          <span class="p" data-total="<?= $n ?>"><?= $h(forma_euro($pr['total'])) ?></span>
          <span class="u" data-each="<?= $n ?>"><?= $n > 1 ? $L('je ', '') . $h(forma_euro($pr['unit'])) . $L('', ' each') : '&nbsp;' ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="book">
      <div class="panel">
        <h3><?= $L('Wann passt es dir?', 'When suits you?') ?></h3>
        <p class="hint"><?= $L('Tipp zuerst einen Tag an und wähl dann die Uhrzeit. Bei mehreren Einheiten geht es danach automatisch mit der nächsten weiter.', 'Tap a day first, then choose a time. With more than one session, it moves on to the next one automatically.') ?></p>
        <div class="calw">
          <div><div class="calh"><button type="button" data-m="-1" aria-label="‹">‹</button><b id="calt"></b><button type="button" data-m="1" aria-label="›">›</button></div><div class="cal" id="cal" role="grid"></div></div>
          <div class="units" id="units" aria-live="polite"></div>
        </div>
        <h3 style="margin-top:.8rem"><?= $L('Deine Angaben', 'Your details') ?></h3>
        <div class="grid2">
          <label class="f"><?= $L('Vor- und Nachname', 'Full name') ?><input type="text" id="name" autocomplete="name" required></label>
          <label class="f"><?= $L('E-Mail', 'Email') ?><input type="email" id="email" autocomplete="email" required></label>
          <label class="f"><?= $L('Handynummer (für WhatsApp)', 'Mobile (for WhatsApp)') ?><input type="tel" id="phone" autocomplete="tel" required></label>
          <label class="f"><?= $L('Unterkunft (optional)', 'Where you are staying (optional)') ?><input type="text" id="hotel" placeholder="<?= $h('Hotel, Ort · hotel, village') ?>"></label>
        </div>
        <label class="f"><?= $L('Gibt es etwas, das ich wissen sollte? (optional)', 'Anything I should know? (optional)') ?><textarea id="note" placeholder=""></textarea></label>
        <p class="hint"><?= $L('Zum Beispiel, was du dir wünschst, oder Verletzungen und Beschwerden, auf die ich achten soll.', 'For example what you would like to work on, or any injuries or health concerns I should keep in mind.') ?></p>
        <label class="chk"><input type="checkbox" id="consent"><span><?= $L('Ich bin einverstanden, dass meine Angaben für diese Anfrage gespeichert werden (<a href="/terms?lang=de#datenschutz" target="_blank" rel="noopener">Datenschutz</a>), und habe die <a href="/terms?lang=de#training" target="_blank" rel="noopener">Bedingungen</a> gelesen: Bezahlt wird erst nach meiner Bestätigung, verschieben oder absagen geht bis 24 Stunden vorher kostenlos. Für fix vereinbarte Freizeit-Termine gibt es kein 14-tägiges Rücktrittsrecht.', 'I agree that my details are stored for this request (<a href="/terms?lang=en#datenschutz" target="_blank" rel="noopener">privacy</a>) and I have read the <a href="/terms?lang=en#training" target="_blank" rel="noopener">terms</a>: payment only after confirmation, free rescheduling or cancellation up to 24 hours before. As these are leisure services on fixed dates, there is no 14-day right of withdrawal.') ?></span></label>
        <input class="hp" type="text" id="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      </div>
      <aside class="side"><div class="bl blob"></div>
        <p class="k"><?= $L('Deine Anfrage', 'Your request') ?></p>
        <div class="amt" id="sumamt"><?= $h(forma_euro($single)) ?></div>
        <div class="ln"><span><?= $L('Einheiten', 'Sessions') ?></span><span id="sumn">1 × <?= PTB_MINUTES ?> <?= $L('Min.', 'min') ?></span></div>
        <div class="ln"><span><?= $L('pro Einheit', 'per session') ?></span><span id="sumu"><?= $h(forma_euro($single)) ?></span></div>
        <div class="ln"><span><?= $L('Personen', 'People') ?></span><span id="sump">1</span></div>
        <div class="ln"><span><?= $L('Termine gewählt', 'Times chosen') ?></span><span id="sumt">0 / 1</span></div>
        <button class="btn" type="button" id="send"><?= $L('Anfrage senden', 'Send request') ?></button>
        <p class="err" id="err" hidden></p>
        <p class="small"><?= $L('Du bezahlst jetzt noch nichts. Erst wenn ich deine Termine bestätigt habe, bekommst du den Link zum Bezahlen.', "You don't pay anything yet. Once I have confirmed your times, you'll get the link to pay.") ?></p>
      </aside>
    </div>
  </section>

  <section class="w" style="padding-top:0">
    <div class="two">
      <div><p class="k">FAQ</p><h2 style="margin-top:.6rem"><?= $L('Gut zu wissen', 'Good to know') ?></h2></div>
      <div class="faq">
        <details><summary><?= $L('Wo ist das Studio?', 'Where is the studio?') ?></summary><p><?= $L('In Mathon im Paznaun, nur wenige Autominuten von Ischgl und Galtür. Die genaue Adresse bekommst du mit deiner Buchungsbestätigung.', 'In Mathon in the Paznaun valley, just a few minutes by car from Ischgl and Galtür. You will get the exact address with your booking confirmation.') ?></p></details>
        <details><summary><?= $L('Muss ich fit sein?', 'Do I need to be fit?') ?></summary><p><?= $L('Nein. Wir starten genau dort, wo du gerade stehst. Jede Übung passe ich an deinen Körper und deine Tagesform an.', 'No. We start exactly where you are. I adapt every exercise to your body and how you feel that day.') ?></p></details>
        <details><summary><?= $L('Was soll ich mitbringen?', 'What should I bring?') ?></summary><p><?= $L('Bequeme Kleidung und eine Flasche Wasser. Matten, Gewichte und alles andere liegen im Studio bereit.', 'Comfortable clothes and a bottle of water. Mats, weights and everything else are ready in the studio.') ?></p></details>
        <details><summary><?= $L('Geht das Training auch auf Englisch?', 'Can we train in English?') ?></summary><p><?= $L('Ja, sehr gern. Ich unterrichte auf Deutsch und auf Englisch.', 'Of course. I teach in both English and German.') ?></p></details>
        <details><summary><?= $L('Können wir auch zu zweit kommen?', 'Can two of us come together?') ?></summary><p><?= $L('Ja, gern. Zu zweit kostet eine Einheit ' . $h(forma_euro($two)) . ' für euch beide. Ich stimme das Training auf euch beide ab, ihr müsst also nicht gleich fit sein.', 'Yes, of course. For two, a session costs ' . $h(forma_euro($two)) . ' for both of you. I tailor the training to each of you, so you don\'t need to be at the same level.') ?></p></details>
        <details><summary><?= $L('Wann bezahle ich?', 'When do I pay?') ?></summary><p><?= $L('Erst, wenn ich deine Termine bestätigt habe. Dann bezahlst du alle Einheiten auf einmal online, mit Karte, Apple Pay oder Google Pay. Die Rechnung kommt per E-Mail.', 'Only once I have confirmed your times. Then you pay for all sessions at once online, by card, Apple Pay or Google Pay. Your invoice arrives by email.') ?></p></details>
        <details><summary><?= $L('Und wenn mir etwas dazwischenkommt?', 'What if something comes up?') ?></summary><p><?= $L('Bis 24 Stunden vor einem Termin kannst du kostenlos verschieben oder absagen, dann bekommst du das Geld für diese Einheit zurück. Schreib mir einfach per WhatsApp oder E-Mail.', 'You can reschedule or cancel free of charge up to 24 hours before a session, and you will get the money for that session back. Just message me on WhatsApp or by email.') ?></p></details>
      </div>
    </div>
  </section>
</main>
<?php endif; ?>

<footer class="w">
  <p>Movecia – Personal Fitness · Vivien Malonga · <a href="mailto:<?= $h(NATAL_COMPANY['email']) ?>"><?= $h(NATAL_COMPANY['email']) ?></a> · <a href="<?= $h($wa) ?>">WhatsApp</a></p>
  <p style="margin-top:6px"><a href="/terms#training"><?= $L('Bedingungen', 'Terms') ?></a> · <a href="/terms#datenschutz"><?= $L('Datenschutz', 'Privacy') ?></a></p>
  <details><summary><?= $L('Impressum &amp; Datenschutz', 'Legal notice &amp; privacy') ?></summary>
    <p style="margin-top:.6rem"><strong><?= $L('Impressum', 'Legal notice') ?>:</strong> Vivien Malonga, Movecia – Personal Fitness. E-Mail: hello@moveciamovement.com. <a href="/terms#impressum"><?= $L('Zum vollständigen Impressum', 'Full legal notice') ?></a>.</p>
    <p style="margin-top:.6rem"><strong><?= $L('Datenschutz', 'Privacy') ?>:</strong> <?= $L('Wenn du eine Anfrage schickst, speichere ich deinen Namen, deine E-Mail-Adresse, deine Handynummer, deine Unterkunft, deine Nachricht und deine Wunschtermine, um die Termine mit dir zu planen. Die Zahlung läuft über Stripe (Stripe Payments Europe Ltd., Irland). Für die Buchhaltung bewahre ich die Rechnung so lange auf, wie es das Gesetz verlangt. Diese Seite verwendet keine Tracking-Cookies. Fragen oder Löschwünsche an hello@moveciamovement.com.', 'When you send a request, I store your name, email address, mobile number, where you are staying, your message and your preferred times in order to plan your sessions with you. Payments are handled by Stripe (Stripe Payments Europe Ltd., Ireland). I keep invoices for as long as the law requires for my accounts. This page uses no tracking cookies. Questions or deletion requests: hello@moveciamovement.com.') ?></p>
  </details>
</footer>

<script>
(() => {
  const root = document.documentElement;
  function setL(v){ root.dataset.l = v; root.lang = v; document.querySelectorAll('.lang [data-set]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.set === v))); try { localStorage.setItem('wh-lang', v) } catch (e) {} document.dispatchEvent(new CustomEvent('mvlang', {detail: v})); }
  setL(root.dataset.l);
  document.querySelectorAll('.lang [data-set]').forEach(b => b.addEventListener('click', () => setL(b.dataset.set)));
  const en = () => root.dataset.l === 'en';
  const L = (de, e) => '<span class="de">' + de + '</span><span class="en">' + e + '</span>';
  async function post(d) {
    const fd = new FormData(); Object.entries(d).forEach(([k, v]) => fd.append(k, v)); fd.append('lang', root.dataset.l);
    try { const r = await fetch('/api/ptb.php', {method: 'POST', body: fd}); return await r.json(); } catch (e) { return {ok: false, error: en() ? 'No connection. Please try again.' : 'Keine Verbindung. Bitte versuch es noch einmal.'}; }
  }
  const ORDER = <?= json_encode($order ? ['o' => (int)$order['id'], 'k' => $order['token']] : null) ?>;
  if (ORDER) {
    const pay = document.getElementById('pay');
    if (pay) pay.addEventListener('click', async () => {
      pay.disabled = true;
      const j = await post({action: 'pay', o: ORDER.o, k: ORDER.k});
      if (j.ok && j.redirect) { location.href = j.redirect; return; }
      const e = document.getElementById('payerr'); e.textContent = j.error || '…'; e.hidden = false; pay.disabled = false;
    });
    const rs = document.getElementById('resend');
    if (rs) rs.addEventListener('click', async () => {
      rs.disabled = true;
      const j = await post({action: 'resend', o: ORDER.o, k: ORDER.k});
      const e = document.getElementById('rserr');
      if (j.ok) { rs.innerHTML = L('Ist unterwegs ✓', 'On its way ✓'); return; }
      e.textContent = j.error || '…'; e.hidden = false; rs.disabled = false;
    });
    return;
  }
  const CAL = <?= json_encode($cal, JSON_UNESCAPED_UNICODE) ?>;
  if (!document.getElementById('cal')) return;
  const loaded = Date.now();
  const MON_DE = ['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  const MON_EN = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const WD_DE = ['So','Mo','Di','Mi','Do','Fr','Sa'], WD_EN = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const niceDe = s => { const d = new Date(s + 'T12:00:00'); return WD_DE[d.getDay()] + ', ' + d.getDate() + '. ' + MON_DE[d.getMonth()]; };
  const niceEn = s => { const d = new Date(s + 'T12:00:00'); return WD_EN[d.getDay()] + ' ' + d.getDate() + ' ' + MON_EN[d.getMonth()]; };
  const tDe = t => t.replace(/^0/, '') + ' Uhr';
  const tEn = t => { let [h, m] = t.split(':').map(Number); const ap = h < 12 ? 'am' : 'pm'; h = h % 12 || 12; return h + (m ? ':' + String(m).padStart(2, '0') : '') + ap; };
  const euro = c => '€ ' + (c % 100 ? (c / 100).toFixed(2).replace('.', ',') : String(c / 100));
  const mins = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  let N = 1, P = 1, picks = [{}], act = 0;
  let view = new Date(CAL.min + 'T12:00:00'); view.setDate(1);
  const grid = document.getElementById('cal'), title = document.getElementById('calt'), box = document.getElementById('units');

  function dayOk(s) {
    if (s < CAL.min || s > CAL.max || CAL.blocked.includes(s)) return false;
    const wd = new Date(s + 'T12:00:00').getDay() || 7;
    return CAL.days.includes(wd) && freeTimes(s, -1).length > 0;
  }
  function timeFree(s, t, except) {
    const a = mins(t), b = a + CAL.minutes;
    for (const [x, y] of (CAL.busy[s] || [])) if (a < y + 15 && b > x - 15) return false;
    for (let i = 0; i < picks.length; i++) { if (i === except || picks[i].date !== s || !picks[i].time) continue; const x = mins(picks[i].time); if (a < x + CAL.minutes && b > x) return false; }
    return true;
  }
  function freeTimes(s, except) { return CAL.times.filter(t => timeFree(s, t, except)); }

  function drawCal() {
    title.innerHTML = L(MON_DE[view.getMonth()] + ' ' + view.getFullYear(), MON_EN[view.getMonth()] + ' ' + view.getFullYear());
    let html = '';
    ['Mo','Di','Mi','Do','Fr','Sa','So'].forEach((d, i) => html += '<span class="dw">' + L(d, ['M','T','W','T','F','S','S'][i]) + '</span>');
    const first = (view.getDay() + 6) % 7;
    for (let i = 0; i < first; i++) html += '<span></span>';
    const days = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
    const sel = picks.map(p => p.date).filter(Boolean);
    for (let d = 1; d <= days; d++) {
      const s = ymd(new Date(view.getFullYear(), view.getMonth(), d));
      const ok = dayOk(s);
      const cls = [ok ? 'ok' : '', sel.includes(s) ? 'sel' : '', picks[act] && picks[act].date === s ? 'cur' : ''].join(' ');
      html += '<button type="button" class="' + cls + '" data-d="' + s + '"' + (ok ? '' : ' disabled') + '>' + d + '</button>';
    }
    grid.innerHTML = html;
    const minM = new Date(CAL.min + 'T12:00:00'), maxM = new Date(CAL.max + 'T12:00:00');
    document.querySelector('.calh [data-m="-1"]').disabled = view.getFullYear() * 12 + view.getMonth() <= minM.getFullYear() * 12 + minM.getMonth();
    document.querySelector('.calh [data-m="1"]').disabled = view.getFullYear() * 12 + view.getMonth() >= maxM.getFullYear() * 12 + maxM.getMonth();
  }
  function drawUnits() {
    let html = '';
    picks.forEach((p, i) => {
      const lab = N > 1 ? L('Einheit ' + (i + 1), 'Session ' + (i + 1)) : L('Deine Einheit', 'Your session');
      let val = p.date ? L(niceDe(p.date) + (p.time ? ' · ' + tDe(p.time) : ''), niceEn(p.date) + (p.time ? ' · ' + tEn(p.time) : '')) : L('Tag im Kalender antippen', 'Tap a day in the calendar');
      html += '<div class="ur' + (i === act ? ' act' : '') + '" data-i="' + i + '"><div class="row"><span><span class="n">' + lab + '</span><br><span class="v' + (p.date ? '' : ' mut') + '">' + val + '</span></span>' + (p.time ? '<span class="chip fix">✓</span>' : '') + '</div>';
      if (i === act && p.date) {
        html += '<div class="tm">' + CAL.times.map(t => '<button type="button" data-t="' + t + '" aria-pressed="' + (p.time === t) + '"' + (timeFree(p.date, t, i) ? '' : ' disabled') + '>' + L(t, tEn(t)) + '</button>').join('') + '</div>';
      }
      html += '</div>';
    });
    box.innerHTML = html;
    const done = picks.filter(p => p.date && p.time).length;
    const pr = CAL.prices[P][N];
    document.getElementById('sumamt').textContent = euro(pr.total);
    document.getElementById('sumu').textContent = euro(pr.unit);
    document.getElementById('sumn').innerHTML = N + ' × ' + CAL.minutes + ' ' + L('Min.', 'min');
    document.getElementById('sump').innerHTML = P === 2 ? L('2 · zu zweit', '2 · for two') : '1';
    document.getElementById('sumt').textContent = done + ' / ' + N;
  }
  function draw() { drawCal(); drawUnits(); }
  document.querySelectorAll('.calh [data-m]').forEach(b => b.addEventListener('click', () => { view.setMonth(view.getMonth() + Number(b.dataset.m)); drawCal(); }));
  grid.addEventListener('click', e => {
    const b = e.target.closest('button[data-d]'); if (!b || b.disabled) return;
    picks[act] = {date: b.dataset.d, time: picks[act].date === b.dataset.d ? picks[act].time : ''};
    draw();
    const first = box.querySelector('.ur.act .tm button:not(:disabled)');
    if (first && innerWidth < 800) box.querySelector('.ur.act').scrollIntoView({behavior: 'smooth', block: 'center'});
  });
  box.addEventListener('click', e => {
    const tb = e.target.closest('button[data-t]');
    if (tb && !tb.disabled) {
      picks[act].time = tb.dataset.t;
      const next = picks.findIndex(p => !p.time);
      if (next >= 0) { act = next; if (!picks[act].date) document.getElementById('cal').scrollIntoView({behavior: 'smooth', block: 'center'}); }
      draw(); return;
    }
    const ur = e.target.closest('.ur');
    if (ur && !e.target.closest('.tm')) { act = Number(ur.dataset.i); if (picks[act].date) { const d = new Date(picks[act].date + 'T12:00:00'); view = new Date(d.getFullYear(), d.getMonth(), 1); } draw(); }
  });
  document.querySelectorAll('input[name=units]').forEach(r => r.addEventListener('change', () => {
    N = Number(r.value);
    while (picks.length < N) picks.push({});
    picks = picks.slice(0, N);
    const open = picks.findIndex(p => !p.time); act = open >= 0 ? open : Math.min(act, N - 1);
    draw();
  }));
  function drawPacks() {
    document.querySelectorAll('#packs [data-solo]').forEach(e => e.hidden = P === 2);
    for (let n = 1; n <= CAL.max_units; n++) {
      const pr = CAL.prices[P][n];
      const t = document.querySelector('#packs [data-total="' + n + '"]'); if (t) t.textContent = euro(pr.total);
      const e = document.querySelector('#packs [data-each="' + n + '"]'); if (e && n > 1) e.innerHTML = L('je ' + euro(pr.unit), euro(pr.unit) + ' each');
    }
  }
  document.querySelectorAll('input[name=persons]').forEach(r => r.addEventListener('change', () => { P = Number(r.value); drawPacks(); draw(); }));
  document.addEventListener('mvlang', draw);
  drawPacks();
  draw();

  const send = document.getElementById('send'), err = document.getElementById('err');
  send.addEventListener('click', async () => {
    err.hidden = true;
    const v = id => document.getElementById(id).value.trim();
    const done = picks.filter(p => p.date && p.time);
    let msg = '';
    if (done.length < N) msg = en() ? 'Please choose a day and a time for each session.' : 'Bitte wähl für jede Einheit einen Tag und eine Uhrzeit.';
    else if (v('name').length < 2) msg = en() ? 'Please enter your name.' : 'Bitte gib deinen Namen an.';
    else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v('email'))) msg = en() ? 'Please enter a valid email address.' : 'Bitte gib eine gültige E-Mail-Adresse an.';
    else if (v('phone').replace(/\D/g, '').length < 6) msg = en() ? 'Please enter your mobile number.' : 'Bitte gib deine Handynummer an.';
    else if (!document.getElementById('consent').checked) msg = en() ? 'Please confirm the terms and privacy notice.' : 'Bitte bestätige die Bedingungen und den Datenschutz.';
    if (msg) { err.textContent = msg; err.hidden = false; return; }
    send.disabled = true; send.innerHTML = L('Wird gesendet …', 'Sending …');
    const j = await post({action: 'request', units: N, persons: P, wishes: JSON.stringify(done), name: v('name'), email: v('email'), phone: v('phone'), hotel: v('hotel'), note: v('note'), consent: '1', website: v('website'), t: loaded});
    if (j.ok && j.redirect) { location.href = j.redirect; return; }
    err.textContent = j.error || '…'; err.hidden = false; send.disabled = false; send.innerHTML = L('Anfrage senden', 'Send request');
  });
})();
</script>
</body>
</html>

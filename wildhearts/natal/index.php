<?php
// moveciamovement.com/natal – Prä- & Postnatal Training: Angebot, Buchung (Stripe) und persönliche Terminseite.
// ?a=TOKEN  persönliches Angebot (Begrüßung mit Namen)
// ?o=ID&k=TOKEN  persönliche Seite nach der Buchung (Einheiten, Termine, neue Wunschtermine)
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/natal-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
natal_schema($pdo);
$h = 'forma_h';

$order = null;
if (isset($_GET['o'])) {
    $order = natal_order_auth($pdo, (int)$_GET['o'], (string)($_GET['k'] ?? ''));
    if ($order && $order['status'] === 'offen') { natal_check_paid($pdo, $order); $order = natal_order($pdo, (int)$order['id']); }
}
$offer = null;
if (!$order && ($_GET['a'] ?? '') !== '') {
    $q = $pdo->prepare('SELECT * FROM mm_natal_offers WHERE token = ?');
    $q->execute([(string)$_GET['a']]);
    $offer = $q->fetch() ?: null;
    if ($offer && !$offer['opened_at']) $pdo->prepare('UPDATE mm_natal_offers SET opened_at = ? WHERE id = ?')->execute([date('c'), $offer['id']]);
}
$online = $order ? natal_is_online($order) : ($offer ? ($offer['mode'] ?? 'studio') === 'online' : (($_GET['m'] ?? '') === 'online'));
$packs = natal_packages_for($online ? 'online' : 'studio');
$single = $packs[$online ? 'o_einzel' : 'einzel']['cents'];
$where = $online ? 'live per Video' : 'im Studio';
$sessions = $order ? natal_sessions($pdo, (int)$order['id']) : [];
$left = $order ? natal_left($pdo, $order) : 0;
$taken = array_values(array_map(fn($s) => $s['date'], array_filter($sessions, fn($s) => in_array($s['status'], ['angefragt', 'bestaetigt'], true))));
$cal = ['weekdays' => natal_weekdays(), 'dayparts' => NATAL_DAYPARTS, 'min' => date('Y-m-d', strtotime('+1 day')), 'max' => date('Y-m-d', strtotime('+9 months')), 'taken' => $taken, 'due' => $order['due_date'] ?? ''];
$euro = fn(int $c) => forma_euro($c);
$callFree = [];
if (!$order) {
    $taken = array_flip(natal_call_taken($pdo));
    for ($d = 0; $d <= 42; $d++) {
        $day = date('Y-m-d', strtotime("+$d day"));
        foreach (NATAL_CALL_SLOTS as $sl) if (natal_call_bookable($day, $sl) && !isset($taken["$day|$sl"])) $callFree[] = "$day|$sl";
    }
}
$wa = 'https://wa.me/' . preg_replace('/\D/', '', NATAL_COMPANY['phone']);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $order ? 'Deine Termine · Movecia' : 'Prä- & Postnatal Training' . ($online ? ' online' : '') . ' · Movecia' ?></title>
<meta name="description" content="Prä- und postnatales Training 1:1 bei Vivien<?= $online ? ', live per Video' : ' im Movecia Studio' ?>: sanft, kräftigend und ganz auf dich abgestimmt.">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#F2ECE4">
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--bg:#F2ECE4;--sand:#E8DED2;--card:#FBF8F4;--ink:#2E2529;--muted:#7D7076;--line:#DDD2C6;--plum:#7D5E68;--plumdeep:#6B5658;--blush:#EADFDE;--peach:#E8D6CA;--on:#FBF5F7;--r:24px;
--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
*{box-sizing:border-box}
[hidden]{display:none!important}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
@media(min-width:900px){body{font-size:19px}}
.w{padding-left:max(16px,env(safe-area-inset-left));padding-right:max(16px,env(safe-area-inset-right))}
@media(min-width:900px){.w{padding-left:64px;padding-right:64px}}
h1,h2,h3{font-family:var(--serif);font-weight:500;line-height:1.04;margin:0;text-wrap:balance}
h1{font-size:clamp(2.8rem,7.4vw,6.6rem);letter-spacing:-.01em}
h2{font-size:clamp(2.2rem,4.6vw,4rem)}
h3{font-size:1.8rem;line-height:1.1}
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
section{padding-block:clamp(3.5rem,8vw,7rem)}
.top{position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;align-items:center;padding-block:1rem;background:color-mix(in srgb,var(--bg) 85%,transparent);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
.brand{font-family:var(--serif);font-size:1.9rem;font-weight:500;color:var(--ink);text-decoration:none;line-height:1;display:flex;align-items:baseline}
.brand i{width:.3em;height:.3em;border-radius:50%;background:var(--plum);display:inline-block;margin-left:.08em}
.top .tag{font-size:.8rem;color:var(--muted);letter-spacing:.06em}
.blob{border-radius:62% 38% 46% 54%/55% 45% 55% 45%;animation:morph 14s ease-in-out infinite alternate}
@keyframes morph{50%{border-radius:40% 60% 58% 42%/44% 58% 42% 56%}100%{border-radius:45% 55% 60% 40%/40% 60% 45% 55%}}
@media(prefers-reduced-motion:reduce){.blob,.mq-in{animation:none!important}}
.hero{display:grid;gap:2.5rem;align-items:center;padding-block:clamp(2rem,5vw,4.5rem) clamp(3rem,6vw,5rem)}
@media(min-width:1000px){.hero{grid-template-columns:1.25fr 1fr;gap:4rem}}
.script{font-family:var(--serif);font-style:italic;font-size:clamp(1.5rem,2.4vw,2.1rem);color:var(--plum);margin-top:.6rem}
.hello{display:inline-block;background:var(--card);border-radius:18px;padding:.9rem 1.2rem;margin-top:1.4rem;max-width:34em;font-size:1rem}
.hello b{font-family:var(--serif);font-size:1.35rem;font-weight:500;display:block;color:var(--ink)}
.lead{font-size:clamp(1.05rem,1.4vw,1.3rem);font-weight:300;color:var(--muted);max-width:32em;margin-top:1.4rem}
.cta{display:flex;flex-wrap:wrap;gap:.8rem;margin-top:2rem}
.hv{position:relative;justify-self:center;width:min(100%,480px);aspect-ratio:1/1}
@media(max-width:599px){.hv{width:min(100%,300px)}.hv .q{font-size:1.25rem}}
.hv .b1{position:absolute;inset:4% 2% 6% 10%;background:var(--blush)}
.hv .b2{position:absolute;width:30%;aspect-ratio:1;left:2%;top:6%;background:var(--plum);opacity:.92}
.hv .b3{position:absolute;width:20%;aspect-ratio:1;right:4%;bottom:6%;background:var(--peach);border-radius:50%}
.hv .q{position:absolute;inset:26% 16% 24% 26%;display:flex;flex-direction:column;justify-content:center;font-family:var(--serif);font-size:clamp(1.5rem,2.6vw,2.2rem);line-height:1.2;color:var(--ink)}
.hv .q small{font-family:var(--sans);font-size:.8rem;letter-spacing:.16em;text-transform:uppercase;color:var(--plum);margin-top:1rem}
.mq{overflow:hidden;background:var(--plum);color:var(--on);padding-block:1rem}
.mq-in{display:flex;gap:2.4rem;width:max-content;animation:mq 40s linear infinite;font-family:var(--serif);font-style:italic;font-size:clamp(1.4rem,2.4vw,2rem)}
.mq-in span::after{content:"✦";font-style:normal;font-size:.55em;margin-left:2.4rem;opacity:.6;vertical-align:middle}
@keyframes mq{to{transform:translateX(-50%)}}
.two{display:grid;gap:16px;margin-top:2.6rem}
@media(min-width:900px){.two{grid-template-columns:1fr 1fr}}
.card{background:var(--card);border-radius:var(--r);padding:clamp(1.5rem,3vw,2.4rem)}
.card.pl{background:var(--plum);color:var(--on)}
.card .when{font-size:.75rem;letter-spacing:.16em;text-transform:uppercase;font-weight:500;color:var(--plum);margin:.5rem 0 1.1rem}
.card.pl .when{color:var(--peach)}
.dots{list-style:none;padding:0;margin:0;display:grid;gap:.55rem}
.dots li{display:flex;gap:.8rem}
.dots li::before{content:"";flex:none;width:8px;height:8px;border-radius:50%;background:var(--plum);margin-top:.68em}
.card.pl .dots li::before{background:var(--peach)}
.steps{display:grid;gap:16px;margin-top:2.6rem;counter-reset:s}
@media(min-width:900px){.steps{grid-template-columns:repeat(3,1fr)}}
.step{background:var(--card);border-radius:var(--r);padding:1.6rem;position:relative;overflow:hidden}
.step::before{counter-increment:s;content:counter(s);position:absolute;right:1.2rem;top:.4rem;font-family:var(--serif);font-size:4.4rem;color:var(--blush);line-height:1}
.step b{display:block;font-family:var(--serif);font-size:1.6rem;font-weight:500;line-height:1.15;margin-bottom:.4rem;position:relative}
.step p{color:var(--muted);position:relative}
.packs{display:grid;gap:10px;margin-top:2rem;grid-template-columns:repeat(2,minmax(0,1fr))}
@media(max-width:599px){.pack{padding:1.1rem 1rem 1rem}.pack .t{font-size:1.35rem}.pack .p{font-size:2.1rem}.pack .s{font-size:.82rem}.pack .badge{position:static;align-self:flex-start;order:-1}}
@media(min-width:700px){.packs{gap:14px;margin-top:2.6rem}}
@media(min-width:1200px){.packs{grid-template-columns:repeat(4,1fr)}}
.pack{position:relative;display:flex;flex-direction:column;gap:.5rem;background:var(--card);border-radius:var(--r);padding:1.5rem 1.5rem 1.4rem;cursor:pointer;border:2px solid transparent;transition:border-color .2s,transform .25s}
.pack:hover{transform:translateY(-3px)}
.pack input{position:absolute;opacity:0;pointer-events:none}
.pack:has(input:checked){border-color:var(--plum);background:#fff}
.pack:has(input:focus-visible){outline:2px solid var(--plum);outline-offset:3px}
.pack .t{font-family:var(--serif);font-size:1.7rem;font-weight:500;line-height:1.1}
.pack .s{color:var(--muted);font-size:.92rem}
.pack .p{font-family:var(--serif);font-size:2.8rem;font-weight:500;line-height:1;margin-top:auto;padding-top:.8rem}
.pack .u{font-size:.85rem;color:var(--muted)}
.pack .save{font-size:.8rem;color:var(--plum);font-weight:500}
.pack .badge{position:absolute;top:1rem;right:1rem;background:var(--peach);color:var(--plumdeep);font-size:.68rem;letter-spacing:.14em;text-transform:uppercase;font-weight:600;padding:.35em .8em;border-radius:99px}
.pack .ck{position:absolute;top:1rem;right:1rem;width:26px;height:26px;border-radius:50%;border:2px solid var(--line);display:grid;place-items:center}
.pack .badge~.ck{top:auto;bottom:1.2rem}
.pack:has(input:checked) .ck{background:var(--plum);border-color:var(--plum)}
.pack:has(input:checked) .ck::after{content:"";width:8px;height:8px;border-radius:50%;background:#fff}
.book{display:grid;gap:16px;margin-top:2.2rem}
@media(min-width:1000px){.book{grid-template-columns:1.5fr 1fr;align-items:start}}
.panel{background:var(--card);border-radius:28px;padding:clamp(1.4rem,3vw,2.4rem);display:flex;flex-direction:column;gap:1.2rem;min-width:0}
.panel h3{font-size:1.6rem}
.grid2{display:grid;gap:12px}
@media(min-width:640px){.grid2{grid-template-columns:1fr 1fr}}
label.f{display:flex;flex-direction:column;gap:.35rem;font-size:.86rem;color:var(--muted)}
input[type=text],input[type=email],input[type=tel],input[type=date],textarea,select{font:inherit;font-size:1rem;color:var(--ink);background:var(--bg);border:1px solid transparent;border-radius:14px;padding:.8em 1em;width:100%;min-width:0}
input:focus,textarea:focus,select:focus{border-color:var(--plum);outline:none}
textarea{min-height:84px;resize:vertical}
.chk{display:flex;gap:.7rem;font-size:.88rem;color:var(--muted);align-items:flex-start}
.chk input{margin-top:.3em;accent-color:var(--plum);width:18px;height:18px;flex:none}
.side{background:var(--plumdeep);color:var(--on);border-radius:28px;padding:clamp(1.4rem,3vw,2.2rem);display:flex;flex-direction:column;gap:1rem;position:relative;overflow:hidden}
@media(min-width:1000px){.side{position:sticky;top:90px}}
.side .bl{position:absolute;width:200px;height:190px;right:-70px;bottom:-80px;background:color-mix(in srgb,var(--plum) 80%,transparent)}
.side>*{position:relative}
.side .k{color:var(--peach)}
.side .amt{font-family:var(--serif);font-size:clamp(3rem,6vw,4.6rem);font-weight:500;line-height:1}
.side .ln{display:flex;justify-content:space-between;gap:1rem;font-size:.92rem;border-top:1px solid rgba(251,245,247,.18);padding-top:.7rem}
.side .ln span:first-child{color:rgba(251,245,247,.75)}
.side .btn{background:var(--on);color:var(--plumdeep);border-color:var(--on)}
.side .small{font-size:.78rem;color:rgba(251,245,247,.72);line-height:1.5}
.err{background:#F6E1DC;color:#7A2E22;border-radius:14px;padding:.8rem 1rem;font-size:.92rem}
.ok{background:var(--blush);border-radius:18px;padding:1rem 1.2rem}
/* Kalender */
.calw{display:grid;gap:1rem}
@media(min-width:760px){.calw{grid-template-columns:minmax(0,400px) minmax(0,1fr);gap:1.4rem}}
.calh{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}
.calh b{font-family:var(--serif);font-size:1.4rem;font-weight:500}
.calh button{border:0;background:var(--bg);color:var(--ink);border-radius:50%;width:40px;height:40px;cursor:pointer;font:inherit;font-size:1.1rem}
.calh button[disabled]{opacity:.3;cursor:default}
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;font-variant-numeric:tabular-nums}
.cal .dw{font-size:.72rem;color:var(--muted);padding-bottom:.2rem}
.cal button{aspect-ratio:1;border:0;border-radius:50%;background:transparent;color:#C9BFB4;font:inherit;font-size:.92rem;padding:0;position:relative}
.cal button.ok{color:var(--ink);background:var(--bg);cursor:pointer;font-weight:500}
.cal button.ok:hover{background:var(--blush)}
.cal button.sel{background:var(--plum);color:var(--on)}
.cal button.tk{background:transparent;color:var(--plum);border:1.5px dashed var(--plum);cursor:default}
.cal button.due::after{content:"";position:absolute;left:50%;bottom:12%;width:5px;height:5px;margin-left:-2.5px;border-radius:50%;background:var(--peach)}
.picked{display:flex;flex-direction:column;gap:8px}
.pk{background:var(--bg);border-radius:16px;padding:.7rem .8rem;display:flex;flex-direction:column;gap:.5rem}
.pk .row{display:flex;justify-content:space-between;align-items:center;gap:.6rem}
.pk b{font-weight:500}
.pk .x{border:0;background:transparent;color:var(--muted);cursor:pointer;font-size:1.2rem;line-height:1}
.dp{display:flex;flex-wrap:wrap;gap:6px}
.dp button{border:0;background:var(--card);color:var(--muted);font:inherit;font-size:.8rem;padding:.4em .8em;border-radius:999px;cursor:pointer}
.dp button[aria-pressed=true]{background:var(--plum);color:var(--on)}
.legend{font-size:.78rem;color:var(--muted);display:flex;flex-wrap:wrap;gap:.4rem 1rem;margin-top:.6rem}
.legend i{display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--plum);margin-right:.35rem;vertical-align:middle}
.legend i.d{background:var(--peach)}
.legend i.t{background:transparent;border:1.5px dashed var(--plum)}
/* Persönliche Seite */
.units{display:flex;align-items:flex-end;gap:1.2rem;flex-wrap:wrap}
.units .big{font-family:var(--serif);font-size:clamp(4rem,9vw,6rem);line-height:.9;color:var(--plum)}
.ud{display:flex;flex-wrap:wrap;gap:6px;margin-top:1rem}
.ud i{width:18px;height:18px;border-radius:50%;background:var(--line)}
.ud i.c{background:var(--plum)}.ud i.a{background:var(--peach)}.ud i.e{background:var(--plumdeep);opacity:.45}
.sl{display:flex;flex-direction:column;gap:8px}
.si{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;background:var(--bg);border-radius:16px;padding:.8rem 1rem}
.si b{font-weight:500}
.chip{font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;padding:.35em .8em;border-radius:999px;background:var(--line);color:var(--ink);font-weight:500;white-space:nowrap}
.chip.bestaetigt{background:var(--plum);color:var(--on)}.chip.angefragt{background:var(--peach);color:var(--plumdeep)}.chip.abgesagt{background:transparent;border:1px solid var(--line);color:var(--muted)}
.faq details{border-top:1px solid var(--line);padding-block:1.2rem}
.faq details:last-child{border-bottom:1px solid var(--line)}
.faq summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:1rem;font-family:var(--serif);font-size:1.45rem;line-height:1.25}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"+";font-family:var(--sans);font-weight:300;color:var(--plum)}
.faq details[open] summary::after{content:"–"}
.faq p{margin-top:.6rem;color:var(--muted);max-width:62ch}
footer{background:var(--sand);color:var(--muted);padding-block:2.6rem;font-size:.88rem}
footer a{color:inherit}
footer details{margin-top:.8rem}
footer summary{cursor:pointer}
.hp{position:absolute;left:-5000px}
.kl{gap:1.4rem}
.klh{display:flex;flex-wrap:wrap;justify-content:space-between;gap:1rem 2rem;align-items:flex-start}
.kltimes{background:var(--blush);border-radius:18px;padding:.9rem 1.2rem;font-size:.95rem;line-height:1.5}
.kltimes b{display:block;font-family:var(--serif);font-size:1.4rem;font-weight:500}
.slotb{display:flex;gap:8px;flex-wrap:wrap}
.slotb button{border:0;background:var(--bg);color:var(--ink);font:inherit;font-size:.95rem;padding:.75em 1.3em;border-radius:999px;cursor:pointer}
.slotb button[aria-pressed=true]{background:var(--plum);color:var(--on)}
.slotb button:disabled{opacity:.4;cursor:default;text-decoration:line-through}
</style>
</head>
<body>
<header class="top w"><a class="brand" href="<?= $order ? $h(natal_link($order)) : '#' ?>">Movecia<i></i></a><span class="tag">1:1 <?= $online ? 'online' : 'im Studio' ?> · Prä- &amp; Postnatal</span></header>

<?php if ($order):
    $first = forma_first_name((string)$order['name']);
    $conf = array_values(array_filter($sessions, fn($s) => $s['status'] === 'bestaetigt' && $s['date'] >= date('Y-m-d')));
?>
<main>
<section class="w" style="padding-top:clamp(1.5rem,4vw,3rem)">
  <p class="k">Deine persönliche Seite</p>
  <h1 style="font-size:clamp(2.6rem,6vw,5rem);margin-top:.4rem">Hallo <em><?= $h($first) ?></em></h1>
  <p class="lead" style="margin-top:.8rem"><?= $h($order['title']) ?> · Prä- &amp; Postnatal Training bei Vivien<?= $online ? ', live per Video' : '' ?></p>
  <?php if (isset($_GET['danke']) && $order['status'] === 'bezahlt'): ?><p class="ok" style="margin-top:1.4rem;max-width:44em">Danke! Deine Zahlung ist angekommen. Du bekommst gleich eine Bestätigung und deine Rechnung per E-Mail. <?= $sessions ? 'Deine Wunschtermine sind bei mir, ich bestätige sie dir mit Uhrzeit.' : 'Wähl dir jetzt einfach unten deine Wunschtermine aus.' ?></p><?php endif; ?>
  <?php if ($order['status'] === 'offen'): ?>
    <div class="card" style="margin-top:1.6rem;max-width:44em">
      <h3><?= isset($_GET['abgebrochen']) ? 'Die Zahlung wurde abgebrochen' : 'Deine Zahlung ist noch offen' ?></h3>
      <p class="mut" style="margin:.5rem 0 1rem">Es ist nichts abgebucht worden. Wenn du magst, kannst du jetzt bezahlen. Erst dann ist dein Paket fix.</p>
      <button class="btn" type="button" id="payagain">Jetzt bezahlen · <?= $h($euro((int)$order['amount_cents'])) ?></button>
      <p class="err" id="payerr" hidden></p>
    </div>
  <?php endif; ?>
</section>
<?php if ($order['status'] === 'bezahlt'): ?>
<section class="w" style="padding-top:0">
  <div class="two" style="margin-top:0">
    <div class="card">
      <p class="k">Deine Einheiten</p>
      <div class="units" style="margin-top:.6rem"><span class="big"><?= $left ?></span><span class="mut" style="padding-bottom:.6rem">von <?= (int)$order['units'] ?> noch frei zum Anfragen</span></div>
      <div class="ud" aria-hidden="true"><?php foreach ($sessions as $s) if ($s['status'] !== 'abgesagt') echo '<i class="' . ($s['status'] === 'bestaetigt' ? 'c' : ($s['status'] === 'erledigt' ? 'e' : 'a')) . '"></i>'; for ($i = 0; $i < $left; $i++) echo '<i></i>'; ?></div>
      <p class="legend"><span><i></i>bestätigt</span><span><i class="d"></i>angefragt</span><span><i style="background:var(--line)"></i>noch frei</span></p>
      <?php if ($order['due_date']): ?><p class="mut" style="margin-top:1rem;font-size:.9rem">Geburtstermin: <?= $h(natal_date_de((string)$order['due_date'])) ?> · gültig bis 6 Monate nach der Geburt</p><?php endif; ?>
    </div>
    <div class="card">
      <p class="k">Deine Termine</p>
      <?php if (!$sessions): ?><p class="mut" style="margin-top:.6rem">Noch keine Termine. Such dir unten deine Wunschtage aus.</p><?php endif; ?>
      <div class="sl" style="margin-top:.8rem">
        <?php foreach ($sessions as $s): ?>
          <div class="si"><span><b><?= $h(natal_when($s)) ?></b><?php $ph = natal_phase($order, (string)$s['date']); if ($ph): ?><br><span class="mut" style="font-size:.84rem"><?= $h($ph) ?></span><?php endif; ?></span>
            <span style="display:flex;gap:.6rem;align-items:center"><?php if ($online && $s['status'] === 'bestaetigt' && $s['start'] !== '' && $s['date'] >= date('Y-m-d')): ?><a class="btn" style="padding:.45em 1em;font-size:.82rem" href="<?= $h(natal_room_link('s', (int)$s['id'])) ?>">Videoraum</a><?php endif; ?><?php if ($s['status'] === 'bestaetigt' && $s['start'] !== ''): ?><a href="<?= $h(natal_gcal($s, $online)) ?>" target="_blank" rel="noopener" style="font-size:.84rem">Google Kalender</a><?php endif; ?><span class="chip <?= $h($s['status']) ?>"><?= $h(natal_status_label((string)$s['status'])) ?></span></span></div>
        <?php endforeach; ?>
      </div>
      <?php if ($conf): ?><p style="margin-top:1rem"><a class="btn line" href="/api/natal.php?ics=1&amp;o=<?= (int)$order['id'] ?>&amp;k=<?= $h($order['token']) ?>">Alle Termine in meinen Kalender</a></p><?php endif; ?>
    </div>
  </div>
</section>
<?php if ($left > 0): ?>
<section class="w" style="padding-top:0" id="anfragen">
  <div class="panel">
    <p class="k">Neue Wunschtermine</p>
    <h3>Wann passt es dir?</h3>
    <p class="mut">Tipp auf die Tage, die dir passen, und wähl die Tageszeit. Du kannst bis zu <?= $left === 1 ? '1 Termin' : $left . ' Termine' ?> auf einmal anfragen. Ich bestätige dir jeden Termin mit Uhrzeit per E-Mail.</p>
    <div id="calbox"></div>
    <label class="f">Nachricht an Vivien (optional)<textarea id="wnote" maxlength="400" placeholder="z. B. lieber früh am Vormittag, oder: das Baby ist da!"></textarea></label>
    <div><button class="btn" type="button" id="sendw">Termine anfragen</button></div>
    <p class="err" id="werr" hidden></p>
  </div>
</section>
<?php endif; ?>
<section class="w" style="padding-top:0">
  <div class="card" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:1rem;align-items:center">
    <p><b style="font-family:var(--serif);font-size:1.5rem;font-weight:500">Fragen oder Termin verschieben?</b><br><span class="mut">Schreib mir einfach, am schnellsten per WhatsApp.</span></p>
    <p style="display:flex;gap:.6rem;flex-wrap:wrap"><a class="btn" href="<?= $h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><a class="btn line" href="mailto:<?= $h(NATAL_COMPANY['email']) ?>">E-Mail</a></p>
  </div>
</section>
<?php endif; ?>
</main>

<?php else: /* Angebot & Buchung */ ?>
<main>
<section class="hero w">
  <div>
    <p class="k">Prä- &amp; Postnatal Training · 1:1 <?= $online ? 'online' : 'im Studio' ?></p>
    <h1 style="margin-top:.8rem">Bewegt durch die <em>Schwangerschaft</em> &amp; das Wochenbett</h1>
    <p class="script">für dich und dein Baby</p>
    <?php if ($offer): ?><p class="hello"><b><?= $h(($offer['salutation'] ?: 'Liebe') . ' ' . $offer['name']) ?>,</b>schön, dass du da bist. Hier ist mein Angebot für dich, mit allem, was wir bis zur Geburt und danach gemeinsam machen können.<?= $offer['note'] !== '' ? '<br><br>' . nl2br($h((string)$offer['note'])) : '' ?></p><?php endif; ?>
    <p class="lead">Prä- und postnatales Training, ganz auf dich abgestimmt: ruhig, kräftigend und mit viel Raum für das, was dein Körper gerade braucht. <?= $online ? 'Live per Video, bequem von zu Hause aus: nur du und ich, ich sehe dich und korrigiere dich wie im Studio.' : 'Bei mir im Studio, nur du und ich.' ?></p>
    <div class="cta"><a class="btn" href="#pakete">Paket wählen <span aria-hidden="true">→</span></a><a class="btn line" href="#kennenlernen">Kostenlos kennenlernen</a></div>
  </div>
  <div class="hv" aria-hidden="true"><div class="b1 blob"></div><div class="b2 blob"></div><div class="b3"></div><p class="q">„Dein Körper leistet gerade Großes. Lass uns ihn gut begleiten.“<small>Vivien</small></p></div>
</section>
<div class="mq" aria-hidden="true"><div class="mq-in"><?php for ($i = 0; $i < 2; $i++) foreach (['ankommen', 'atmen', 'spüren', 'stärken', 'loslassen', 'vertrauen', 'zurückfinden'] as $wd) echo '<span>' . $wd . '</span>'; ?></div></div>

<section class="w">
  <p class="k">Was wir gemeinsam machen</p>
  <h2 style="margin-top:.6rem">Vor und nach der <em>Geburt</em></h2>
  <div class="two">
    <div class="card"><h3>Vor der Geburt</h3><p class="when">ab jetzt bis zur Geburt</p><ul class="dots">
      <li>Sanfte Mobilisation für Becken, Hüfte und Rücken</li>
      <li>Atemarbeit und Beckenboden-Wahrnehmung als Geburtsvorbereitung</li>
      <li>Kräftigung, die sich gut anfühlt und entlastet</li>
      <li>Entspannung und Positionen für die Geburt</li></ul></div>
    <div class="card pl"><h3>Nach der Geburt</h3><p class="when">nach ärztlicher Freigabe · meist 6–8 Wochen</p><ul class="dots">
      <li>Beckenboden- und Rückbildungstraining</li>
      <li>Tiefe Bauchmuskulatur schonend aufbauen</li>
      <li>Haltung und Rücken für Tragen und Stillen</li>
      <li>Schritt für Schritt zurück zu Kraft und Energie, dein Baby darf mitkommen</li></ul></div>
  </div>
</section>

<section class="w" id="ablauf" style="padding-top:0">
  <p class="k">So läuft es ab</p>
  <h2 style="margin-top:.6rem">In drei <em>Schritten</em></h2>
  <div class="steps">
    <div class="step"><b>Paket wählen und bezahlen</b><p>Sicher online über Stripe. Danach bekommst du eine Bestätigung und deine Rechnung per E-Mail.</p></div>
    <div class="step"><b>Wunschtermine aussuchen</b><p>Im Kalender tippst du deine Wunschtage an und wählst Vormittag, Nachmittag oder Abend. Gleich beim Buchen oder später auf deiner persönlichen Seite.</p></div>
    <div class="step"><b>Ich bestätige mit Uhrzeit</b><p>Du bekommst jeden Termin per E-Mail, mit Kalenderdatei für dein Handy<?= $online ? ' und dem Link zum Videoraum' : '' ?>. Weitere Termine fragst du jederzeit über deine Seite an.</p></div>
  </div>
</section>

<section class="w" id="kennenlernen" style="padding-top:0">
  <div class="panel kl">
    <div class="klh">
      <div>
        <p class="k">Vor der Buchung · freiwillig</p>
        <h2 style="margin-top:.5rem;font-size:clamp(2rem,4vw,3.2rem)">Erst einmal <em>kennenlernen</em>?</h2>
        <p class="mut" style="margin-top:.8rem;max-width:34em">Buch dir ein kostenloses Gespräch von 30 Minuten, online per Video. Wir sprechen darüber, wie es dir geht, was du dir wünschst und welches Paket zu dir passt. Ganz unverbindlich. Du bekommst den Termin mit Kalenderdatei und Link per E-Mail.</p>
      </div>
      <p class="kltimes"><b>Mo–Fr</b>8:00–8:30 Uhr<br>oder 19:00–19:30 Uhr</p>
    </div>
    <div class="calw">
      <div id="klcal"></div>
      <div id="klslots" class="picked"><p class="mut" style="font-size:.9rem;padding:.6rem 0">Tipp zuerst auf einen Tag.</p></div>
    </div>
    <form id="klform" class="grid2" hidden novalidate>
      <label class="f">Vor- und Nachname<input type="text" name="name" autocomplete="name" required maxlength="120" value="<?= $offer ? $h((string)$offer['name']) : '' ?>"></label>
      <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required maxlength="160"></label>
      <label class="f">Telefon (optional)<input type="tel" name="phone" autocomplete="tel" maxlength="40"></label>
      <label class="f">Was möchtest du besprechen? (optional)<input type="text" name="note" maxlength="600" placeholder="z. B. SSW, Fragen, Wünsche"></label>
      <label class="chk" style="grid-column:1/-1"><input type="checkbox" name="consent" value="1" required><span>Meine Angaben werden nur für dieses Gespräch verwendet (Datenschutz siehe unten).</span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div style="grid-column:1/-1"><button class="btn" type="submit" id="klbtn">Gespräch buchen</button></div>
      <p class="err" id="klerr" hidden style="grid-column:1/-1"></p>
    </form>
    <p class="ok" id="klok" hidden></p>
  </div>
</section>

<section class="w" id="pakete" style="padding-top:0">
  <p class="k">Preise &amp; Pakete</p>
  <h2 style="margin-top:.6rem">Je mehr Einheiten, desto <em>günstiger</em></h2>
  <p class="mut" style="margin-top:.8rem">Jede Einheit dauert 60 Minuten, 1:1 <?= $where ?>. Alle Preise inkl. 20 % USt.</p>
  <div class="packs" role="radiogroup" aria-label="Paket">
    <?php foreach ($packs as $k => $p): $save = $single * $p['units'] - $p['cents']; ?>
      <label class="pack"><input type="radio" name="package" value="<?= $h($k) ?>" data-units="<?= (int)$p['units'] ?>" data-cents="<?= (int)$p['cents'] ?>" data-title="<?= $h($p['title']) ?>" data-unit="<?= (int)$p['unit_cents'] ?>"<?= $p['tag'] !== '' ? ' checked' : '' ?>>
        <?php if ($p['tag']): ?><span class="badge"><?= $h($p['tag']) ?></span><?php endif; ?><span class="ck" aria-hidden="true"></span>
        <span class="t"><?= $h(preg_replace('/ online$/', '', $p['title'])) ?></span><span class="s"><?= $h($p['sub']) ?></span>
        <span class="p"><?= $h($euro($p['cents'])) ?></span>
        <span class="u"><?= $p['units'] > 1 ? $h($euro($p['unit_cents'])) . ' pro Einheit' : 'pro Einheit' ?></span>
        <?php if ($save > 0): ?><span class="save">du sparst <?= $h($euro($save)) ?></span><?php endif; ?>
      </label>
    <?php endforeach; ?>
  </div>
  <p class="mut" style="margin-top:1rem;font-size:.9rem">Kommt dein Baby früher, nutzt du offene Einheiten einfach nach der Geburt. Alle Pakete gelten bis 6 Monate nach der Geburt.</p>
</section>

<section class="w" id="buchen" style="padding-top:0">
  <form class="book" id="bookf" novalidate>
    <div class="panel">
      <p class="k">Buchen</p>
      <h3>Deine Angaben</h3>
      <div class="grid2">
        <label class="f">Vor- und Nachname<input type="text" name="name" autocomplete="name" required maxlength="120" value="<?= $offer ? $h((string)$offer['name']) : '' ?>"></label>
        <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required maxlength="160"></label>
        <label class="f">Telefon (für Terminfragen)<input type="tel" name="phone" autocomplete="tel" required maxlength="40"></label>
        <label class="f">Voraussichtlicher Geburtstermin<input type="date" name="due_date" id="due"></label>
      </div>
      <label class="f">Möchtest du mir vorab etwas sagen? (optional)<textarea name="note" maxlength="1000" placeholder="z. B. wie es dir gerade geht oder was dir wichtig ist"></textarea></label>
      <h3 style="margin-top:.6rem">Deine Wunschtermine <span class="mut" style="font-family:var(--sans);font-size:.9rem">(optional)</span></h3>
      <p class="mut" style="font-size:.92rem">Tipp auf die Tage, die dir passen, und wähl die Tageszeit. Du kannst die Termine auch erst nach der Zahlung auf deiner persönlichen Seite aussuchen.</p>
      <div id="calbox"></div>
      <label class="chk"><input type="checkbox" name="health" value="1" required><span>Ich spreche das Training mit meiner Ärztin oder Hebamme ab.</span></label>
      <label class="chk"><input type="checkbox" name="start_now" value="1" required><span>Ich möchte, dass die Termine schon innerhalb der 14-tägigen Rücktrittsfrist stattfinden können. Trete ich zurück, zahle ich nur die Einheiten, die bereits stattgefunden haben (<a href="/withdraw?v=natal" target="_blank" rel="noopener">Rücktritts-Formular</a>).</span></label>
      <label class="chk"><input type="checkbox" name="consent" value="1" required><span>Meine Angaben werden nur für meine Buchung und die Terminplanung verwendet (Datenschutz siehe unten).</span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    </div>
    <aside class="side">
      <div class="bl blob" aria-hidden="true"></div>
      <p class="k">Deine Auswahl</p>
      <p style="font-family:var(--serif);font-size:1.8rem;line-height:1.1" id="sumtitle"></p>
      <p class="amt" id="sumamt"></p>
      <div class="ln"><span>Einheiten à 60 Min.</span><span id="sumunits"></span></div>
      <div class="ln"><span>pro Einheit</span><span id="sumunit"></span></div>
      <div class="ln"><span>Wunschtermine</span><span id="sumwish">später</span></div>
      <button class="btn" type="submit" id="paybtn">Weiter zur Zahlung</button>
      <p class="err" id="err" hidden></p>
      <p class="small">Sichere Zahlung über Stripe (Karte, Apple Pay, Google Pay, EPS). Auf der Bezahlseite steht Movecia. Abgerechnet wird über das gemeinsame Konto von FORMA &amp; Movecia, deine Rechnung kommt im Movecia-Design per E-Mail.</p>
    </aside>
  </form>
</section>

<section class="w faq" style="padding-top:0">
  <p class="k">Gut zu wissen</p>
  <h2 style="margin-top:.6rem;margin-bottom:1.6rem">Häufige <em>Fragen</em></h2>
  <details><summary>Ab wann kann ich nach der Geburt wieder trainieren?</summary><p>Sobald deine Ärztin dich freigibt, meistens nach 6 bis 8 Wochen, nach einem Kaiserschnitt oft etwas später. Bis dahin ruhen deine Einheiten einfach.</p></details>
  <details><summary>Was ist, wenn mein Baby früher kommt?</summary><p>Dann nutzt du die offenen Einheiten einfach nach der Geburt. Nichts verfällt, die Pakete gelten bis 6 Monate nach der Geburt.</p></details>
<?php if ($online): ?>
  <details><summary>Wie läuft eine Online-Einheit ab?</summary><p>Wir treffen uns live per Video: Du tippst einfach auf den Link aus deiner Terminbestätigung. Ich sehe dich, leite dich an und korrigiere dich, genau wie im Studio.</p></details>
  <details><summary>Was brauche ich dafür?</summary><p>Eine Matte, etwas Platz zum Liegen und dein Handy, Tablet oder Laptop. Stell es am besten so auf, dass ich dich ganz sehe. Ein Kissen und ein Handtuch sind praktisch.</p></details>
  <details><summary>Darf mein Baby dabei sein?</summary><p>Ja, sehr gern. Nach der Geburt bleibt dein Baby einfach bei dir, wir passen die Einheit an euren Rhythmus an.</p></details>
<?php else: ?>
  <details><summary>Darf mein Baby mitkommen?</summary><p>Ja, sehr gern. Nach der Geburt kommt dein Baby einfach mit, wir passen die Einheit an euren Rhythmus an.</p></details>
  <details><summary>Was soll ich mitbringen?</summary><p>Bequeme Kleidung, eine Flasche Wasser und gern ein Handtuch. Matten und alles andere habe ich im Studio.</p></details>
<?php endif; ?>
  <details><summary>Ich muss einen Termin verschieben. Was nun?</summary><p>Gib mir bitte so bald wie möglich Bescheid, am besten bis 24 Stunden vorher. Wenn das Baby oder die Gesundheit dazwischenkommt, finden wir immer eine Lösung.</p></details>
</section>
</main>
<?php endif; ?>

<footer class="w">
  <p>Movecia – Personal Fitness · Vivien Malonga · <a href="mailto:<?= $h(NATAL_COMPANY['email']) ?>"><?= $h(NATAL_COMPANY['email']) ?></a></p>
  <p style="margin-top:6px"><a href="/withdraw?v=natal">Vertrag widerrufen</a></p>
  <details><summary>Impressum &amp; Datenschutz</summary>
    <p style="margin-top:.6rem"><strong>Impressum:</strong> Vivien Malonga, Movecia – Personal Fitness, Silvrettastraße 58, 6562 Mathon, Österreich. E-Mail: hello@moveciamovement.com. UID: ATU83602825.</p>
    <p style="margin-top:.6rem"><strong>Datenschutz:</strong> Wenn du buchst, speichere ich deinen Namen, deine E-Mail-Adresse, deine Telefonnummer, deinen Geburtstermin, deine Nachricht und deine Wunschtermine, um deine Buchung abzuwickeln und die Termine mit dir zu planen. Die Zahlung läuft über Stripe (Stripe Payments Europe Ltd., Irland). Für die Buchhaltung bewahre ich die Rechnung so lange auf, wie es das Gesetz verlangt. Diese Seite verwendet keine Tracking-Cookies. Fragen oder Löschwünsche an hello@moveciamovement.com.</p>
  </details>
</footer>

<script>
(() => {
  const CAL = <?= json_encode($cal, JSON_UNESCAPED_UNICODE) ?>;
  const MODE = <?= json_encode($order ? 'order' : 'offer') ?>;
  const ORDER = <?= json_encode($order ? ['o' => (int)$order['id'], 'k' => $order['token'], 'left' => $left] : null) ?>;
  const OFFER = <?= json_encode($offer ? (string)$offer['token'] : '') ?>;
  const euro = c => '€ ' + (c % 100 ? (c / 100).toFixed(2).replace('.', ',') : String(c / 100));
  const MON = ['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  const WD = ['So','Mo','Di','Mi','Do','Fr','Sa'];
  const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const nice = s => { const d = new Date(s + 'T12:00:00'); return WD[d.getDay()] + ', ' + d.getDate() + '. ' + MON[d.getMonth()]; };

  // --- Kalender mit Mehrfachauswahl und Tageszeit -------------------------------------------
  function Calendar(box, getMax, onChange) {
    let picked = [];
    const now = new Date(); let view = new Date(now.getFullYear(), now.getMonth(), 1);
    const minD = CAL.min, maxD = CAL.max;
    box.innerHTML = '<div class="calw"><div><div class="calh"><button type="button" data-m="-1" aria-label="Vorheriger Monat">‹</button><b></b><button type="button" data-m="1" aria-label="Nächster Monat">›</button></div><div class="cal" role="grid"></div>'
      + '<p class="legend"><span><i></i>ausgewählt</span>' + (MODE === 'order' ? '<span><i class="t"></i>schon angefragt</span>' : '') + '<span><i class="d"></i>Geburtstermin</span></p></div><div class="picked" aria-live="polite"></div></div>';
    const grid = box.querySelector('.cal'), head = box.querySelector('.calh b'), list = box.querySelector('.picked');
    box.querySelectorAll('.calh button').forEach(b => b.addEventListener('click', () => { view.setMonth(view.getMonth() + +b.dataset.m); draw(); }));
    function draw() {
      head.textContent = MON[view.getMonth()] + ' ' + view.getFullYear();
      const first = new Date(view), lead = (first.getDay() + 6) % 7;
      let html = ['Mo','Di','Mi','Do','Fr','Sa','So'].map(d => '<span class="dw">' + d + '</span>').join('');
      for (let i = 0; i < lead; i++) html += '<span></span>';
      const days = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
      for (let d = 1; d <= days; d++) {
        const dt = new Date(view.getFullYear(), view.getMonth(), d), s = ymd(dt), wd = ((dt.getDay() + 6) % 7) + 1;
        const ok = s >= minD && s <= maxD && CAL.weekdays.includes(wd);
        const tk = CAL.taken.includes(s), sel = picked.some(p => p.date === s);
        const cls = [tk ? 'tk' : (sel ? 'sel' : (ok ? 'ok' : '')), s === CAL.due ? 'due' : ''].join(' ');
        html += '<button type="button" class="' + cls + '" data-d="' + s + '"' + (!ok || tk ? ' disabled' : '') + ' aria-pressed="' + sel + '" aria-label="' + nice(s) + '">' + d + '</button>';
      }
      grid.innerHTML = html;
      const prev = box.querySelector('[data-m="-1"]'), next = box.querySelector('[data-m="1"]');
      prev.disabled = view <= new Date(now.getFullYear(), now.getMonth(), 1);
      next.disabled = ymd(new Date(view.getFullYear(), view.getMonth() + 1, 1)) > maxD;
      grid.querySelectorAll('button.ok, button.sel').forEach(b => b.addEventListener('click', () => toggle(b.dataset.d)));
      drawList();
    }
    function toggle(s) {
      const i = picked.findIndex(p => p.date === s);
      if (i >= 0) picked.splice(i, 1);
      else { if (picked.length >= getMax()) { flash(getMax() === 1 ? 'Du kannst 1 Termin auswählen.' : 'Du kannst bis zu ' + getMax() + ' Termine auswählen.'); return; } picked.push({date: s, daypart: 'vormittag'}); picked.sort((a, b) => a.date < b.date ? -1 : 1); }
      draw(); onChange && onChange(picked);
    }
    function drawList() {
      if (!picked.length) { list.innerHTML = '<p class="mut" style="font-size:.9rem;padding:.6rem 0">Noch keine Tage ausgewählt.</p>'; return; }
      list.innerHTML = picked.map((p, i) => '<div class="pk"><div class="row"><b>' + nice(p.date) + '</b><button type="button" class="x" data-i="' + i + '" aria-label="Entfernen">×</button></div><div class="dp">'
        + Object.entries(CAL.dayparts).map(([k, l]) => '<button type="button" data-i="' + i + '" data-p="' + k + '" aria-pressed="' + (p.daypart === k) + '">' + l + '</button>').join('') + '</div></div>').join('');
      list.querySelectorAll('.x').forEach(b => b.addEventListener('click', () => { picked.splice(+b.dataset.i, 1); draw(); onChange && onChange(picked); }));
      list.querySelectorAll('.dp button').forEach(b => b.addEventListener('click', () => { picked[+b.dataset.i].daypart = b.dataset.p; drawList(); }));
    }
    function flash(t) { const p = document.createElement('p'); p.className = 'err'; p.textContent = t; list.prepend(p); setTimeout(() => p.remove(), 2600); }
    draw();
    return { get: () => picked.slice(), trim: () => { const m = getMax(); if (picked.length > m) { picked = picked.slice(0, m); draw(); onChange && onChange(picked); } } };
  }

  async function post(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    const r = await fetch('/api/natal.php', {method: 'POST', body: fd});
    let j = {}; try { j = await r.json(); } catch (e) {}
    return j;
  }

  // --- Kennenlerngespräch ---------------------------------------------------------------------
  const FREE = <?= json_encode($callFree) ?>;
  const klcal = document.getElementById('klcal');
  if (klcal) {
    const loadedK = Date.now();
    const freeDays = {};
    FREE.forEach(x => { const [d, s] = x.split('|'); (freeDays[d] = freeDays[d] || []).push(s); });
    const now = new Date(); let view = new Date(now.getFullYear(), now.getMonth(), 1);
    let pick = null, slot = null;
    const lastFree = FREE.length ? FREE[FREE.length - 1].split('|')[0] : ymd(now);
    klcal.innerHTML = '<div class="calh"><button type="button" data-m="-1" aria-label="Vorheriger Monat">‹</button><b></b><button type="button" data-m="1" aria-label="Nächster Monat">›</button></div><div class="cal" role="grid"></div>';
    const grid = klcal.querySelector('.cal'), head = klcal.querySelector('.calh b'), slots = document.getElementById('klslots'), form = document.getElementById('klform');
    klcal.querySelectorAll('.calh button').forEach(b => b.addEventListener('click', () => { view.setMonth(view.getMonth() + +b.dataset.m); draw(); }));
    function draw() {
      head.textContent = MON[view.getMonth()] + ' ' + view.getFullYear();
      const lead = (new Date(view).getDay() + 6) % 7, days = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
      let html = ['Mo','Di','Mi','Do','Fr','Sa','So'].map(d => '<span class="dw">' + d + '</span>').join('');
      for (let i = 0; i < lead; i++) html += '<span></span>';
      for (let d = 1; d <= days; d++) {
        const s = ymd(new Date(view.getFullYear(), view.getMonth(), d)), ok = !!freeDays[s];
        html += '<button type="button" class="' + (s === pick ? 'sel' : (ok ? 'ok' : '')) + '" data-d="' + s + '"' + (ok ? '' : ' disabled') + ' aria-label="' + nice(s) + '">' + d + '</button>';
      }
      grid.innerHTML = html;
      klcal.querySelector('[data-m="-1"]').disabled = view <= new Date(now.getFullYear(), now.getMonth(), 1);
      klcal.querySelector('[data-m="1"]').disabled = ymd(new Date(view.getFullYear(), view.getMonth() + 1, 1)) > lastFree;
      grid.querySelectorAll('button.ok, button.sel').forEach(b => b.addEventListener('click', () => { pick = b.dataset.d; slot = null; draw(); drawSlots(); }));
    }
    function drawSlots() {
      if (!pick) return;
      const fr = freeDays[pick] || [];
      slots.innerHTML = '<div class="pk"><b>' + nice(pick) + '</b><div class="slotb">' + ['08:00', '19:00'].map(s => '<button type="button" data-s="' + s + '" aria-pressed="' + (slot === s) + '"' + (fr.includes(s) ? '' : ' disabled') + '>' + (s === '08:00' ? '8:00–8:30 Uhr' : '19:00–19:30 Uhr') + '</button>').join('') + '</div></div>';
      slots.querySelectorAll('[data-s]').forEach(b => b.addEventListener('click', () => { slot = b.dataset.s; drawSlots(); form.hidden = false; }));
    }
    draw();
    if (!FREE.length) slots.innerHTML = '<p class="mut">Gerade sind alle Termine vergeben. Schreib mir gern an <?= $h(NATAL_COMPANY['email']) ?>.</p>';
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const err = document.getElementById('klerr'), btn = document.getElementById('klbtn'); err.hidden = true;
      const d = Object.fromEntries(new FormData(form));
      if (!pick || !slot) { err.textContent = 'Bitte wähl Tag und Uhrzeit.'; err.hidden = false; return; }
      if (!d.name || !d.email || !d.consent) { err.textContent = 'Bitte gib Name und E-Mail an und bestätige das Häkchen.'; err.hidden = false; return; }
      Object.assign(d, {action: 'call', date: pick, slot: slot, t: loadedK, a: OFFER, mode: <?= json_encode($online ? 'online' : 'studio') ?>});
      btn.disabled = true; btn.textContent = 'Einen Moment …';
      const j = await post(d);
      if (j.ok) {
        form.hidden = true; slots.hidden = true; klcal.hidden = true;
        const ok = document.getElementById('klok'); ok.hidden = false;
        ok.textContent = 'Danke! Dein Kennenlerngespräch ist eingetragen: ' + j.when + '. Die Bestätigung mit Kalenderdatei und Link ist per E-Mail unterwegs.';
        return;
      }
      err.textContent = j.error || 'Das hat gerade nicht geklappt.'; err.hidden = false; btn.disabled = false; btn.textContent = 'Gespräch buchen';
    });
  }

  if (MODE === 'offer') {
    const loaded = Date.now();
    const f = document.getElementById('bookf'), err = document.getElementById('err'), btn = document.getElementById('paybtn');
    const cur = () => document.querySelector('input[name=package]:checked');
    const cal = Calendar(document.getElementById('calbox'), () => +cur().dataset.units, sum);
    const due = document.getElementById('due');
    due.min = CAL.min.slice(0, 8) + '01';
    due.addEventListener('change', () => { CAL.due = due.value; });
    function sum() {
      const p = cur(), w = cal.get().length;
      document.getElementById('sumtitle').textContent = p.dataset.title;
      document.getElementById('sumamt').textContent = euro(+p.dataset.cents);
      document.getElementById('sumunits').textContent = p.dataset.units;
      document.getElementById('sumunit').textContent = euro(+p.dataset.unit);
      document.getElementById('sumwish').textContent = w ? w + (w === 1 ? ' Tag' : ' Tage') : 'später';
      btn.textContent = 'Weiter zur Zahlung · ' + euro(+p.dataset.cents);
    }
    document.querySelectorAll('input[name=package]').forEach(r => r.addEventListener('change', () => { cal.trim(); sum(); }));
    sum();
    f.addEventListener('submit', async e => {
      e.preventDefault(); err.hidden = true;
      const d = Object.fromEntries(new FormData(f));
      d.package = cur().value; d.action = 'buy'; d.t = loaded; d.a = OFFER;
      d.wishes = JSON.stringify(cal.get());
      if (!d.name || !d.email || !d.phone) { err.textContent = 'Bitte füll Name, E-Mail und Telefon aus.'; err.hidden = false; return; }
      if (!d.health || !d.start_now || !d.consent) { err.textContent = 'Bitte bestätige noch die drei Häkchen.'; err.hidden = false; return; }
      btn.disabled = true; btn.textContent = 'Einen Moment …';
      const j = await post(d);
      if (j.ok && j.redirect) { location.href = j.redirect; return; }
      err.textContent = j.error || 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.'; err.hidden = false; btn.disabled = false; sum();
    });
  } else if (ORDER) {
    const pay = document.getElementById('payagain');
    if (pay) pay.addEventListener('click', async () => {
      pay.disabled = true;
      const j = await post({action: 'pay', o: ORDER.o, k: ORDER.k});
      if (j.ok && j.redirect) { location.href = j.redirect; return; }
      const e = document.getElementById('payerr'); e.textContent = j.error || 'Das hat nicht geklappt.'; e.hidden = false; pay.disabled = false;
    });
    const box = document.getElementById('calbox');
    if (box) {
      const cal = Calendar(box, () => ORDER.left);
      const b = document.getElementById('sendw'), e = document.getElementById('werr');
      b.addEventListener('click', async () => {
        e.hidden = true;
        const w = cal.get();
        if (!w.length) { e.textContent = 'Bitte tipp im Kalender mindestens einen Tag an.'; e.hidden = false; return; }
        b.disabled = true; b.textContent = 'Wird gesendet …';
        const j = await post({action: 'wish', o: ORDER.o, k: ORDER.k, wishes: JSON.stringify(w), note: document.getElementById('wnote').value});
        if (j.ok) { location.reload(); return; }
        e.textContent = j.error || 'Das hat gerade nicht geklappt.'; e.hidden = false; b.disabled = false; b.textContent = 'Termine anfragen';
      });
    }
  }
})();
</script>
</body>
</html>

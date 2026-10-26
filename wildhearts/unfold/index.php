<?php
// moveciamovement.com/unfold – Unfold · The Mindful Becoming (Vivien, 24.10.2026)
// 3 Monate Begleitung ab 10. Jänner 2027 (erster Live-Call), live online, Deutsch und Englisch. Seit 25.10.2026 sofort buchbar.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/method-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
mv_schema($pdo);
try { mv_tick($pdo); } catch (Throwable $e) { error_log('MV tick: ' . $e->getMessage()); }
$c = mv_cfg();
$free = mv_unfold_free($pdo);
$open = $c['u_open'] && $free > 0;
$eur = fn(int $x) => '€ ' . number_format($x / 100, 0, ',', '.');
$st = mv_ts($c['u_start']);
$stDe = mv_date($st); $stEn = mv_date($st, 'en');
// Vorab-Buchung (Vivien, 25.10.2026): Zugang erst ab Start, Rücktrittsfrist 14 Tage ab Start
$wu = mv_withdraw_until(['kind' => 'unfold', 'paid_at' => date('c'), 'bill_start' => 0]); $wuDe = mv_date($wu); $wuEn = mv_date($wu, 'en');
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : (($_GET['lang'] ?? '') === 'de' ? 'de' : '');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Unfold – 3-Monats-Begleitung für Frauen: Mindful Movement, Mental Load & Selbstwert | Movecia</title>
<meta name="description" content="Unfold – The Mindful Becoming: dein 3-Monats-Begleiter für Mindful Movement mit Vivien Malonga. Wöchentlicher Live-Call, zwei Bewegungsvideos pro Woche (Yoga, Somatic, Bodyweight), Workbooks für Mental Load und Selbstwert. Online, Deutsch &amp; Englisch, Start 10. Jänner 2027.">
<link rel="canonical" href="https://moveciamovement.com/unfold/">
<meta name="robots" content="index, follow, max-image-preview:large">
<meta property="og:type" content="website"><meta property="og:url" content="https://moveciamovement.com/unfold/"><meta name="twitter:card" content="summary_large_image">
<meta property="og:title" content="Unfold – The Mindful Becoming · 3 Monate Begleitung mit Vivien">
<meta property="og:description" content="3 months to come back to yourself and begin. Live online with Vivien, in English and German. Starts 10 January 2027.">
<meta property="og:image" content="https://moveciamovement.com/img/movecia/og-unfold.jpg"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta name="theme-color" content="#F2ECE4">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="manifest" href="/app.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Movecia"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="/wildhearts/assets/app.js?v=2" defer></script>
<?php
$site = mm_site();
$faq = [
 ['Was ist Unfold?', 'Unfold – The Mindful Becoming ist eine 3-monatige Online-Begleitung für Frauen mit Vivien Malonga: jede Woche ein Live-Call mit Impulsvortrag, zwei Bewegungsvideos pro Woche (Yoga, Bodyweight, Somatic und freie Bewegung), Workbooks für Mental Load, Organisation und Selbstwert sowie Journaling, Meditation und Breathwork.'],
 ['Wann startet Unfold?', 'Der erste Live-Call ist am ' . mv_date($st) . '. Unfold dauert 13 Wochen, es gibt höchstens ' . (int)$c['u_seats'] . ' Plätze.'],
 ['Was kostet Unfold?', ($c['eb_u'] ? 'Early-Bird bis ' . mv_date($c['eb_u_end']) . ': ' . $eur($c['u_once']) . ' auf einmal oder ' . (int)$c['u_n'] . ' × ' . $eur($c['u_inst']) . '. Danach ' . $eur($c['r_u_once']) . ' auf einmal oder ' . (int)$c['u_n'] . ' × ' . $eur($c['r_u_inst']) . '.' : $eur($c['u_once']) . ' auf einmal oder ' . (int)$c['u_n'] . ' × ' . $eur($c['u_inst']) . '.')],
 ['Muss ich bei Ratenzahlung jeden Monat neu auf mein Rücktrittsrecht verzichten?', 'Nein. Du entscheidest das einmal bei der Buchung, und es gilt für die ganzen drei Monate. Die Raten sind kein neuer Vertrag.'],
 ['Muss ich sportlich sein?', 'Nein. Die Bewegung ist sanft und achtsam, jede Übung hat leichtere Varianten und es gibt eigene Videos für müde Tage.'],
 ['Gibt es Unfold auf Englisch?', 'Ja. Es gibt immer einen Live-Call auf Deutsch und einen auf Englisch, die Videos gibt es in beiden Sprachen.'],
];
$ld = [
 ['@context' => 'https://schema.org', '@type' => 'Course', '@id' => $site . '/unfold/#course', 'name' => 'Unfold – The Mindful Becoming', 'description' => '3-Monats-Begleitung für Frauen: Mindful Movement, Live-Calls mit Impulsvortrag, Workbooks für Mental Load und Selbstwert, Journaling, Meditation und Breathwork.',
  'provider' => ['@type' => 'Organization', 'name' => 'Movecia', 'sameAs' => $site . '/'], 'inLanguage' => ['de', 'en'], 'image' => $site . '/img/movecia/og-unfold.jpg',
  'offers' => [['@type' => 'Offer', 'category' => 'Paid', 'price' => number_format($c['u_once'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR'] + ($c['eb_u'] ? ['priceValidUntil' => date('Y-m-d', $c['eb_u_end'])] : []) + ['availability' => $free > 0 ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/SoldOut', 'url' => $site . '/unfold/#buchen']],
  'hasCourseInstance' => [['@type' => 'CourseInstance', 'courseMode' => 'Online', 'startDate' => $c['u_start'], 'endDate' => date('Y-m-d', mv_unfold_end()), 'courseSchedule' => ['@type' => 'Schedule', 'repeatFrequency' => 'P1W', 'repeatCount' => 13], 'instructor' => ['@type' => 'Person', 'name' => 'Vivien Malonga'], 'location' => ['@type' => 'VirtualLocation', 'url' => $site . '/unfold/']]]],
 ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)],
 ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Movecia', 'item' => $site . '/'], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Unfold', 'item' => $site . '/unfold/']]],
];
foreach ($ld as $j) echo '<script type="application/ld+json">' . json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
?>
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--bg:#F2ECE4;--card:#FBF8F4;--ink:#2E2529;--muted:#7D7076;--line:#DDD2C6;--plum:#7D5E68;--plumdeep:#6B5658;--deep:#4A3A41;--blush:#EADFDE;--peach:#E8D6CA;--on:#FBF5F7;--r:24px;--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
*{box-sizing:border-box}[hidden]{display:none!important}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
@media(min-width:900px){body{font-size:19px}}
html[data-l=de] .en,html[data-l=en] .de{display:none!important}
.w{padding-left:max(16px,env(safe-area-inset-left));padding-right:max(16px,env(safe-area-inset-right))}
.in{max-width:1440px;margin:0 auto}
h1,h2,h3{font-family:var(--serif);font-weight:500;line-height:1.05;margin:0;letter-spacing:-.01em}
h1{font-size:clamp(3.2rem,9vw,7rem);letter-spacing:.01em}h2{font-size:clamp(2.2rem,5vw,3.6rem)}h3{font-size:1.6rem}
em{font-style:italic;color:var(--plum)}
p{margin:0}
.k{font-size:.74rem;letter-spacing:.22em;text-transform:uppercase;color:var(--plum);font-weight:500}
.mut{color:var(--muted)}
a{color:var(--plum)}
.btn{display:inline-flex;align-items:center;gap:.5em;background:var(--plum);color:var(--on);border:0;border-radius:999px;padding:.85em 1.6em;font:inherit;font-weight:500;text-decoration:none;cursor:pointer;transition:background .2s}
.btn:hover{background:var(--plumdeep)}.btn.line{background:transparent;color:var(--plum);box-shadow:inset 0 0 0 1px var(--plum)}
/* Kopf */
.top{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding-top:14px;padding-bottom:14px}
.brand{display:flex;align-items:center;gap:.5rem;font-family:var(--serif);font-size:1.7rem;color:var(--ink);text-decoration:none}
.brand img{width:30px;height:30px}.brand i{display:inline-block;width:.32em;height:.32em;border-radius:50%;background:var(--plum);margin-left:.05em;vertical-align:baseline}
.lang{display:flex;background:var(--card);border:1px solid var(--line);border-radius:999px;padding:3px}
.lang button{font:inherit;font-size:.85rem;border:0;background:none;border-radius:999px;padding:.35em .9em;cursor:pointer;color:var(--muted)}
.lang button[aria-pressed=true]{background:var(--plum);color:var(--on)}
/* Hero */
.hero{display:grid;gap:2rem;align-items:center;padding-top:clamp(1rem,4vw,3rem);padding-bottom:clamp(2rem,6vw,5rem)}
@media(min-width:960px){.hero{grid-template-columns:1.15fr .85fr;gap:4rem}}
.hero .sub{font-family:var(--serif);font-style:italic;font-size:clamp(1.6rem,3.6vw,2.6rem);color:var(--plum);margin-top:.2rem}
.hero .lead{font-size:1.1em;max-width:34em;margin-top:1.4rem}
.facts{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.6rem}
.facts>span{background:var(--card);border:1px solid var(--line);border-radius:999px;padding:.4em 1em;font-size:.88rem}
.cta{display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1.8rem}
.pic{position:relative;aspect-ratio:4/5;border-radius:220px 220px var(--r) var(--r);overflow:hidden;background:var(--peach)}
video::-webkit-media-controls-start-playback-button{display:none!important;-webkit-appearance:none}
.pic img,.pic video{width:100%;height:100%;object-fit:cover;display:block;filter:saturate(.95) sepia(.06)}
.pic::after{content:"";position:absolute;inset:0;background:rgba(214,160,122,.13);mix-blend-mode:multiply;pointer-events:none}.pic .stamp{z-index:1}
.pic .stamp{position:absolute;left:14px;bottom:14px;right:14px;background:rgba(251,248,244,.92);backdrop-filter:blur(6px);border-radius:18px;padding:.9rem 1.1rem;font-family:var(--serif);font-size:1.25rem;line-height:1.25}
.pic .stamp small{display:block;font-family:var(--sans);font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:var(--plum);margin-bottom:.25rem}
.blob{position:absolute;border-radius:62% 38% 46% 54%/55% 45% 55% 45%;pointer-events:none}
/* Bereiche */
section.s{padding-top:clamp(3rem,7vw,6rem);padding-bottom:0}
.intro{display:grid;gap:1.6rem}
@media(min-width:900px){.intro{grid-template-columns:.9fr 1.1fr;gap:4rem}}
.intro p+p{margin-top:1rem}
.who{display:grid;gap:.8rem;margin-top:1.4rem}
.who li{list-style:none;background:var(--card);border-radius:18px;padding:.9rem 1.1rem;display:flex;gap:.8rem;align-items:flex-start}
.who li::before{content:"";flex:0 0 10px;height:10px;border-radius:50%;background:var(--peach);box-shadow:0 0 0 4px var(--blush);margin-top:.55em}
.who{padding:0;margin-bottom:0}
.months{display:grid;gap:14px;margin-top:2.2rem}
@media(min-width:900px){.months{grid-template-columns:repeat(3,1fr)}}
.mo{background:var(--card);border-radius:var(--r);padding:1.8rem 1.6rem;position:relative;overflow:hidden}
.mo .n{font-family:var(--serif);font-size:5rem;line-height:.8;color:var(--blush);position:absolute;right:1.2rem;top:1rem}
.mo .k{position:relative}.mo h3{margin:.6rem 0 .5rem;position:relative;padding-right:2.6rem}.mo p{color:var(--muted);position:relative}
.mo.dark{background:var(--deep);color:var(--on)}.mo.dark p{color:#D9C9CE}.mo.dark .n{color:#5d4a53}.mo.dark .k{color:var(--peach)}
.grid{display:grid;gap:14px;margin-top:2.2rem}
@media(min-width:700px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1100px){.grid{grid-template-columns:repeat(3,1fr)}}
.it{background:var(--card);border-radius:var(--r);padding:1.5rem}
.it svg{width:42px;height:42px;display:block;margin-bottom:.8rem}
.it b{display:block;font-family:var(--serif);font-weight:500;font-size:1.45rem;line-height:1.15;margin-bottom:.35rem}
.it p{color:var(--muted);font-size:.95em}
.price{display:grid;gap:1.6rem;background:var(--plum);color:var(--on);border-radius:calc(var(--r) + 8px);padding:clamp(1.6rem,4vw,3rem);margin-top:2.2rem;position:relative;overflow:hidden}
@media(min-width:900px){.price{grid-template-columns:1.1fr .9fr;align-items:center}}
.price .k{color:var(--peach)}
.price h2{color:var(--on)}
.price .amt{font-family:var(--serif);font-size:clamp(3rem,7vw,4.6rem);line-height:1}
.price .row{display:flex;justify-content:space-between;gap:1rem;border-top:1px solid rgba(251,245,247,.25);padding:.7rem 0}
.price .gp{margin-top:1.2rem;background:rgba(251,245,247,.12);border-radius:18px;padding:1rem 1.2rem;font-size:.95rem}.price .gp b{display:block;font-family:var(--serif);font-weight:500;font-size:1.35rem;margin-bottom:.2rem}.price .note{font-size:.92rem;color:#EADFDE;margin-top:.8rem}
.join{background:var(--card);border-radius:calc(var(--r) + 8px);padding:clamp(1.4rem,4vw,2.6rem);display:grid;gap:1.4rem;margin-top:2.2rem}
@media(min-width:900px){.join{grid-template-columns:1fr 1fr;gap:3rem;align-items:center}}
.join form{display:grid;gap:.8rem}
.f{display:flex;flex-direction:column;gap:.35rem;font-size:.86rem;color:var(--muted)}
input[type=text],input[type=email]{font:inherit;font-size:1rem;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:14px;padding:.8em 1em;width:100%}
input:focus{outline:2px solid var(--plum);outline-offset:1px}
.chk{display:flex;gap:.6rem;align-items:flex-start;font-size:.88rem;color:var(--muted)}.chk input{margin-top:.3em;accent-color:var(--plum)}
.hp{position:absolute;left:-9999px}
.ps{display:grid;gap:14px}@media(min-width:960px){.ps{grid-template-columns:1fr 1fr}}
.pb{background:var(--card);border-radius:var(--r);padding:clamp(1.4rem,3vw,2.4rem)}.pb .who{margin-top:1.2rem}.pb .who li{background:var(--bg)}
.sol{background:var(--deep);color:var(--on);border-radius:var(--r);padding:clamp(1.4rem,3vw,2.4rem)}.sol .k{color:var(--peach)}.sol h2{color:var(--on)}.sol em{color:var(--peach)}.sol p{color:#E3D6DA;margin-top:1rem}
.sol .big{font-family:var(--serif);font-style:italic;font-size:clamp(1.6rem,3vw,2.2rem);color:var(--peach);line-height:1.2}
.pillars{display:grid;gap:14px;margin-top:14px}@media(min-width:700px){.pillars{grid-template-columns:repeat(2,1fr)}}@media(min-width:1100px){.pillars{grid-template-columns:repeat(4,1fr)}}
.pl{background:var(--card);border-radius:var(--r);padding:1.4rem}.pl b{display:block;font-family:var(--serif);font-weight:500;font-size:1.45rem;margin:.4rem 0 .3rem}.pl p{color:var(--muted);font-size:.95em}
.pn{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:50%;background:var(--peach);color:var(--deep);font-family:var(--serif);font-size:1.2rem}
ol.wk{list-style:none;padding:0;margin:1.2rem 0 0;display:grid;gap:.7rem;position:relative}
ol.wk li{display:flex;gap:.8rem;align-items:flex-start;font-size:.9em;color:var(--muted)}ol.wk li b{display:block;color:var(--ink);font-weight:500}
.mo.dark ol.wk li{color:#D9C9CE}.mo.dark ol.wk li b{color:var(--on)}
.wn{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.78rem;background:var(--blush);color:var(--deep)}.mo.dark .wn{background:#5d4a53;color:var(--peach)}
.join.soft{background:transparent;border:1px solid var(--line)}
fieldset.opt{border:0;padding:0;margin:.2rem 0 0;display:grid;gap:.45rem}
fieldset.opt legend{font-size:.86rem;color:var(--muted);margin-bottom:.35rem;padding:0}
.pick{display:flex;gap:.7rem;align-items:center;background:var(--bg);border:1px solid var(--line);border-radius:16px;padding:.75rem 1rem;cursor:pointer;font-size:.95rem}
.pick input{accent-color:var(--plum);width:18px;height:18px;flex:none}
.pick:has(input:checked){border-color:var(--plum);background:#fff}
.pick.wr span{display:grid;gap:.1rem}.pick.wr small{color:var(--muted);font-size:.84rem}
.plans{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
.plan{position:relative;display:grid;gap:.15rem;align-content:start;background:var(--bg);border:1.5px solid var(--line);border-radius:20px;padding:1.5rem 1rem 1rem;cursor:pointer;transition:border-color .2s,background .2s,box-shadow .2s}
.plan input{position:absolute;opacity:0;pointer-events:none}
.plan .pt{font-size:.85rem;color:var(--muted)}
.plan .pa{font-family:var(--serif);font-size:1.7rem;line-height:1.1;color:var(--ink)}
.plan .ps{font-size:.8rem;color:var(--muted)}
.plan.best .ps{color:var(--plum);font-weight:600}
.plan .badge{position:absolute;top:-.7rem;left:1rem;background:var(--plum);color:var(--on);font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;padding:.28rem .7rem;border-radius:99px}
.plan:has(input:checked){border-color:var(--plum);background:#fff;box-shadow:0 8px 24px -14px rgba(74,58,65,.45)}
.plan:has(input:checked)::after{content:"✓";position:absolute;top:.7rem;right:.8rem;width:1.35rem;height:1.35rem;border-radius:50%;background:var(--plum);color:#fff;font-size:.75rem;display:grid;place-items:center}
.plan:has(input:focus-visible){outline:2px solid var(--plum);outline-offset:2px}
.tag{font-style:normal;font-size:.7rem;letter-spacing:.06em;text-transform:uppercase;background:rgba(232,214,202,.25);padding:.15rem .5rem;border-radius:99px;margin-left:.3rem}
@media(max-width:420px){.plans{grid-template-columns:1fr}}
.ok{background:var(--blush);border-radius:16px;padding:1rem 1.2rem}
.err{color:#A0463A;font-size:.9rem}
.me{display:grid;gap:1.6rem;align-items:center}
@media(min-width:800px){.me{grid-template-columns:240px 1fr;gap:3rem}}
.me img{width:100%;max-width:240px;aspect-ratio:1;object-fit:cover;border-radius:50%;display:block}
.hand{font-family:var(--serif);font-style:italic;font-size:1.6rem;color:var(--plum);margin-top:1rem}
details.q{background:var(--card);border-radius:18px;padding:1rem 1.2rem;margin-top:.6rem}
details.q summary{cursor:pointer;font-family:var(--serif);font-size:1.3rem;list-style:none;display:flex;justify-content:space-between;gap:1rem}
details.q summary::after{content:"+";color:var(--plum)}details.q[open] summary::after{content:"–"}
details.q p{margin-top:.6rem;color:var(--muted)}
footer{padding-top:4rem;padding-bottom:2.5rem;font-size:.85rem;color:var(--muted);text-align:center}
footer a{color:var(--muted)}
.mq{overflow:hidden;margin-top:clamp(2rem,5vw,4rem);border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:.9rem 0;white-space:nowrap}
.mq-in{display:inline-flex;gap:2.6rem;animation:mq 38s linear infinite;font-family:var(--serif);font-style:italic;font-size:1.6rem;color:var(--plum)}
@keyframes mq{to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.mq-in{animation:none}}
body>header.w{position:sticky;top:0;z-index:30;background:rgba(242,236,228,.94);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.top-r{display:flex;align-items:center;gap:.5rem;margin-left:auto}@media(max-width:899px){header .lang{display:none}}
</style>
<script src="/wildhearts/assets/schutz.js?v=2"></script>
</head>
<body>
<header class="w"><div class="in top">
  <a class="brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia<i></i></span></a>
  <div class="top-r"><?php require_once dirname(__DIR__) . '/assets/menu.inc.php'; mv_menu('unfold'); ?>
  <div class="lang" role="group" aria-label="Sprache / Language"><button type="button" data-l="de">Deutsch</button><button type="button" data-l="en">English</button></div></div>
</div></header>

<main>
<section class="w"><div class="in hero">
  <div>
    <p class="k"><span class="de">3 Monate Begleitung · live online</span><span class="en">3 months of guidance · live online</span></p>
    <h1 style="margin-top:.8rem">Unfold</h1>
    <p class="sub">The Mindful Becoming</p>
    <p class="lead"><span class="de">Für dich, wenn du spürst, dass mehr in dir steckt, und einfach nicht weißt, wo du anfangen sollst. Drei Monate, in denen du zu dir zurückfindest, deinen Wert wieder spürst und Schritt für Schritt ins Tun kommst. In deinem Tempo und mit Frauen an deiner Seite, denen es genauso geht.</span><span class="en">For you, if you can feel there is more inside you and simply don't know where to begin. Three months to come back to yourself, feel your worth again and step by step start doing what matters to you. At your own pace, with women by your side who feel the same.</span></p>
    <div class="facts"><span><span class="de">Start <?= $stDe ?></span><span class="en">Starts <?= $stEn ?></span></span><span><span class="de">13 Wochen</span><span class="en">13 weeks</span></span><span><span class="de">wöchentlicher Live-Call</span><span class="en">weekly live call</span></span><span><span class="de">2 Bewegungsvideos pro Woche</span><span class="en">2 movement videos a week</span></span><span><span class="de">max. <?= (int)$c['u_seats'] ?> Plätze</span><span class="en">max. <?= (int)$c['u_seats'] ?> spots</span></span><span>Deutsch &amp; English</span></div>
    <div class="cta"><a class="btn" href="#buchen"><?= $open ? '<span class="de">Platz sichern</span><span class="en">Book my spot</span>' : '<span class="de">Auf die Warteliste</span><span class="en">Join the waitlist</span>' ?> →</a><a class="btn line" href="#inhalt"><span class="de">Was dich erwartet</span><span class="en">What's inside</span></a></div>
  </div>
  <div style="position:relative">
    <span class="blob" style="width:200px;height:190px;background:var(--blush);left:-50px;top:-30px"></span>
    <div class="pic"><video src="/video/movecia/unfold.mp4?v=10" poster="/img/movecia/fotos/ev-unfold.jpg?v=10" autoplay muted loop playsinline preload="metadata" aria-label="Vivien bewegt sich langsam und achtsam vor hellen Leinenvorhängen"></video>
      <p class="stamp"><small><span class="de">Nur zweimal im Jahr</span><span class="en">Only twice a year</span></small><span class="de">Jänner bis April · höchstens <?= (int)$c['u_seats'] ?> Plätze</span><span class="en">January to April · max. <?= (int)$c['u_seats'] ?> spots</span></p></div>
  </div>
</div></section>

<div class="mq" aria-hidden="true"><div class="mq-in"><?php for ($i = 0; $i < 2; $i++) foreach (['ankommen · arrive', 'aufräumen · clear', 'spüren · feel', 'wachsen · grow', 'entfalten · unfold', 'beginnen · begin'] as $wd) echo '<span>' . $wd . '</span>'; ?></div></div>

<section class="w s"><div class="in ps">
  <div class="pb">
    <p class="k"><span class="de">Kennst du das?</span><span class="en">Sound familiar?</span></p>
    <h2 style="margin-top:.6rem"><span class="de">Du funktionierst. <em>Aber wo bist du?</em></span><span class="en">You keep going. <em>But where are you?</em></span></h2>
    <ul class="who">
      <li><span class="de">Dein Kopf ist voll mit Listen, Terminen und den Bedürfnissen aller anderen, und abends bist du zu müde für dich selbst.</span><span class="en">Your head is full of lists, appointments and everyone else's needs, and in the evening you are too tired for yourself.</span></li>
      <li><span class="de">Du weißt, dass dir Bewegung guttun würde, aber jedes Programm fühlt sich nach noch mehr Leistung an.</span><span class="en">You know movement would do you good, but every programme feels like even more pressure.</span></li>
      <li><span class="de">Du spürst, dass mehr in dir steckt, und weißt nicht, wo du anfangen sollst.</span><span class="en">You feel there is more inside you and don't know where to begin.</span></li>
    </ul>
  </div>
  <div class="sol">
    <p class="k"><span class="de">Deine Antwort darauf</span><span class="en">Your answer to that</span></p>
    <h2 style="margin-top:.6rem"><span class="de">Unfold ist dein <em>3-Monats-Begleiter</em> für Mindful Movement.</span><span class="en">Unfold is your <em>3-month companion</em> for mindful movement.</span></h2>
    <p class="big"><span class="de">Denn Bewegung beginnt bei dir.</span><span class="en">Because movement begins with you.</span></p>
    <p><span class="de">Unfold verbindet sanfte Bewegung für deinen Körper mit Impulsen und Werkzeugen für deinen Kopf. Wir bewegen uns gemeinsam, ich motiviere dich, wir sprechen über das, was dich wirklich beschäftigt, und Schritt für Schritt wird es leichter.</span><span class="en">Unfold combines gentle movement for your body with talks and tools for your mind. We move together, I motivate you, we talk about what really occupies you, and step by step it gets lighter.</span></p>
  </div>
</div>
<div class="in pillars">
  <div class="pl"><span class="pn">1</span><b><span class="de">Bewegung</span><span class="en">Movement</span></b><p><span class="de">Zwei Videos pro Woche: Yoga, Bodyweight, Somatic und freie Bewegung, für volle und für müde Tage.</span><span class="en">Two videos a week: yoga, bodyweight, somatic and free movement, for full days and tired days.</span></p></div>
  <div class="pl"><span class="pn">2</span><b><span class="de">Live-Call &amp; Impulsvortrag</span><span class="en">Live call &amp; talk</span></b><p><span class="de">Einmal pro Woche live: ein Thema, ein kurzer Vortrag, Austausch und gemeinsames Arbeiten wie in einem kleinen Seminar.</span><span class="en">Once a week live: one topic, a short talk, sharing and working together like in a small seminar.</span></p></div>
  <div class="pl"><span class="pn">3</span><b><span class="de">Workbooks</span><span class="en">Workbooks</span></b><p><span class="de">Zum Aufarbeiten für deine Mental Health: Mental Load, bessere Organisation, Selbstwert, Grenzen und deine Ideen.</span><span class="en">To work through for your mental health: mental load, better organisation, self-worth, boundaries and your ideas.</span></p></div>
  <div class="pl"><span class="pn">4</span><b><span class="de">Rituale</span><span class="en">Rituals</span></b><p><span class="de">Journaling, Meditation und Breathwork, kleine Werkzeuge, die dich durch den Alltag tragen.</span><span class="en">Journaling, meditation and breathwork, small tools that carry you through everyday life.</span></p></div>
</div></section>

<section class="w s"><div class="in intro">
  <div><p class="k"><span class="de">Für wen</span><span class="en">Who it's for</span></p><h2 style="margin-top:.6rem"><span class="de">Wenn du ins <em>Tun</em> kommen willst</span><span class="en">When you are ready to <em>begin</em></span></h2></div>
  <div>
    <p><span class="de">Vielleicht trägst du schon lange eine Idee mit dir herum. Vielleicht willst du dich einfach wieder spüren, nach Jahren, in denen alle anderen zuerst kamen. Und vielleicht fehlt dir nicht die Kraft, sondern ein Anfang und jemand, der mit dir geht.</span><span class="en">Maybe you have been carrying an idea for a long time. Maybe you simply want to feel yourself again, after years in which everyone else came first. And maybe what you lack is not strength, but a beginning and someone who walks with you.</span></p>
    <ul class="who">
      <li><span class="de">Du bist erschöpft vom Funktionieren und willst wieder bei dir ankommen.</span><span class="en">You are tired of just functioning and want to come home to yourself.</span></li>
      <li><span class="de">In dir sprudeln Ideen, aber du weißt nicht, welche du zuerst angehen sollst.</span><span class="en">Ideas are bubbling inside you, but you don't know which one to start with.</span></li>
      <li><span class="de">Du möchtest deinen Selbstwert stärken und dir selbst wieder etwas zutrauen.</span><span class="en">You want to grow your self-worth and trust yourself again.</span></li>
      <li><span class="de">Du wünschst dir Bewegung, die guttut, statt noch mehr Leistungsdruck.</span><span class="en">You long for movement that feels good instead of more pressure to perform.</span></li>
    </ul>
  </div>
</div></section>

<section class="w s" id="inhalt"><div class="in">
  <p class="k"><span class="de">Drei Monate, drei Kapitel, 13 Themen</span><span class="en">Three months, three chapters, 13 topics</span></p>
  <h2 style="margin-top:.6rem"><span class="de">So <em>entfaltest</em> du dich</span><span class="en">How you <em>unfold</em></span></h2>
  <p class="mut" style="margin-top:.8rem;max-width:44em"><span class="de">Jede Woche hat ein eigenes Thema. Im Live-Call bekommst du dazu einen Impulsvortrag von mir, wir arbeiten gemeinsam daran wie in einem kleinen Seminar, und im Workbook vertiefst du es in deinem Tempo.</span><span class="en">Every week has its own topic. In the live call I give you a short talk on it, we work on it together like in a small seminar, and in your workbook you go deeper at your own pace.</span></p>
  <div class="months">
    <div class="mo"><span class="n">1</span><p class="k"><span class="de">Woche 1–4 · Wurzeln</span><span class="en">Weeks 1–4 · Roots</span></p><h3><span class="de">Ankommen &amp; Raum schaffen</span><span class="en">Arrive &amp; make space</span></h3><p><span class="de">Wir schauen, was dich müde macht, räumen auf, was nicht mehr zu dir passt, und finden einen Rhythmus, der dich trägt.</span><span class="en">We look at what drains you, clear out what no longer fits and find a rhythm that carries you.</span></p><ol class="wk"><li><span class="wn">1</span><span><b><span class="de">Wo stehst du gerade?</span><span class="en">Where are you right now?</span></b><span class="de">Ankommen, ehrliche Standortbestimmung und deine Wünsche für die drei Monate.</span><span class="en">Arriving, an honest look at where you are and your wishes for the three months.</span></span></li><li><span class="wn">2</span><span><b><span class="de">Mental Load sichtbar machen</span><span class="en">Making your mental load visible</span></b><span class="de">Was du alles im Kopf trägst, wird sichtbar, und du entscheidest, was du abgeben darfst.</span><span class="en">Everything you carry in your head becomes visible, and you decide what you may hand over.</span></span></li><li><span class="wn">3</span><span><b><span class="de">Erschöpfung verstehen</span><span class="en">Understanding exhaustion</span></b><span class="de">Wie dein Nervensystem funktioniert und was dich wirklich wieder auftanken lässt.</span><span class="en">How your nervous system works and what truly refuels you.</span></span></li><li><span class="wn">4</span><span><b><span class="de">Organisation, die entlastet</span><span class="en">Organisation that lightens the load</span></b><span class="de">Einfache Routinen, Wochenplanung und ein Alltag mit mehr Luft.</span><span class="en">Simple routines, weekly planning and everyday life with more room to breathe.</span></span></li></ol></div>
    <div class="mo"><span class="n">2</span><p class="k"><span class="de">Woche 5–8 · Knospen</span><span class="en">Weeks 5–8 · Buds</span></p><h3><span class="de">Selbstwert &amp; Ideen</span><span class="en">Self-worth &amp; ideas</span></h3><p><span class="de">Du entdeckst, was dich wirklich ausmacht, lässt deine Ideen sprudeln und findest die eine, die jetzt dran ist.</span><span class="en">You discover what truly makes you you, let your ideas flow and find the one that wants to come first.</span></p><ol class="wk"><li><span class="wn">5</span><span><b><span class="de">Grenzen ohne schlechtes Gewissen</span><span class="en">Boundaries without guilt</span></b><span class="de">Nein sagen, ohne dich zu erklären, und Ja sagen zu dir.</span><span class="en">Saying no without explaining yourself, and saying yes to you.</span></span></li><li><span class="wn">6</span><span><b><span class="de">Was dich ausmacht</span><span class="en">What makes you you</span></b><span class="de">Deine Stärken, Werte und das, was dich lebendig macht.</span><span class="en">Your strengths, values and what makes you feel alive.</span></span></li><li><span class="wn">7</span><span><b><span class="de">Die innere Kritikerin</span><span class="en">Your inner critic</span></b><span class="de">Die Stimme erkennen, die dich klein hält, und ihr freundlich antworten.</span><span class="en">Recognising the voice that keeps you small and answering it kindly.</span></span></li><li><span class="wn">8</span><span><b><span class="de">Ideen dürfen sprudeln</span><span class="en">Let your ideas flow</span></b><span class="de">Kreative Übungen, damit deine Ideen endlich Platz bekommen.</span><span class="en">Creative exercises so your ideas finally get space.</span></span></li></ol></div>
    <div class="mo dark"><span class="n">3</span><p class="k"><span class="de">Woche 9–13 · Entfalten</span><span class="en">Weeks 9–13 · Unfold</span></p><h3><span class="de">Ins Tun kommen</span><span class="en">Begin</span></h3><p><span class="de">Kleine, mutige Schritte. Du setzt deine Idee in Bewegung und gehst mit einem Plan und viel Vertrauen in den Frühling.</span><span class="en">Small, brave steps. You set your idea in motion and walk into spring with a plan and a lot of trust.</span></p><ol class="wk"><li><span class="wn">9</span><span><b><span class="de">Klarheit für deine eine Idee</span><span class="en">Clarity for your one idea</span></b><span class="de">Aus vielen Ideen die eine wählen, die jetzt dran ist.</span><span class="en">Choosing the one idea that wants to come first.</span></span></li><li><span class="wn">10</span><span><b><span class="de">Kleine Schritte, echte Gewohnheiten</span><span class="en">Small steps, real habits</span></b><span class="de">Wie aus Vorsätzen Gewohnheiten werden, die bleiben.</span><span class="en">How intentions become habits that stay.</span></span></li><li><span class="wn">11</span><span><b><span class="de">Mut und Sichtbarkeit</span><span class="en">Courage and visibility</span></b><span class="de">Dich zeigen, um Unterstützung bitten und den ersten Schritt nach außen wagen.</span><span class="en">Showing yourself, asking for support and taking the first step out into the world.</span></span></li><li><span class="wn">12</span><span><b><span class="de">Selbstmitgefühl</span><span class="en">Self-compassion</span></b><span class="de">Freundlich mit dir bleiben, wenn etwas nicht klappt.</span><span class="en">Staying kind to yourself when something does not work out.</span></span></li><li><span class="wn">13</span><span><b><span class="de">Feiern und weitergehen</span><span class="en">Celebrate and keep going</span></b><span class="de">Wir feiern, was gewachsen ist, und du gehst mit deinem Plan in den Frühling.</span><span class="en">We celebrate what has grown, and you walk into spring with your plan.</span></span></li></ol></div>
  </div>

  <h2 style="margin-top:clamp(3rem,6vw,5rem)"><span class="de">Das ist <em>dabei</em></span><span class="en">What's <em>included</em></span></h2>
  <div class="grid">
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><circle cx="17" cy="18" r="6"/><circle cx="31" cy="18" r="6"/><path d="M6 38c1.5-7 6-10 11-10s9.5 3 11 10M24 30c2-1.4 4.4-2 7-2 5 0 9.5 3 11 10"/></svg><b><span class="de">Wöchentlicher Live-Call</span><span class="en">Weekly live call</span></b><p><span class="de">Jede Woche treffen wir uns live in einer kleinen Runde, es gibt höchstens 10 Plätze. Es gibt immer einen Termin auf Deutsch und einen auf Englisch, du wählst einfach.</span><span class="en">Every week we meet live in a small circle, with no more than 10 spots. There is always one call in English and one in German, you simply choose.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><path d="M6 34c6-10 12-10 18 0s12 10 18 0"/><circle cx="24" cy="13" r="4"/></svg><b><span class="de">Zwei Bewegungsvideos pro Woche</span><span class="en">Two movement videos a week</span></b><p><span class="de">Jeden Montag ein Full-Day-Flow (ca. 30 Min.) und jeden Donnerstag ein sanfter Tired-Day-Flow (ca. 15 Min.), immer ein Mix aus Yoga, Bodyweight, Somatic und freier Bewegung. Du machst sie, wann es dir passt.</span><span class="en">Every Monday a Full Day flow (about 30 min.) and every Thursday a gentle Tired Day flow (about 15 min.), always a mix of yoga, bodyweight, somatic and free movement. You do them whenever it suits you.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><rect x="9" y="7" width="30" height="34" rx="5"/><path d="M16 16h16M16 23h16M16 30h10"/></svg><b><span class="de">Monatliches Workbook</span><span class="en">Monthly workbook</span></b><p><span class="de">Zum Ausdrucken oder am iPad: Übungen zu Mindset, Selbstwert und deinen Ideen, mit Journaling-Fragen für jede Woche.</span><span class="en">Print it or use it on your tablet: exercises for mindset, self-worth and your ideas, with journaling prompts for every week.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><circle cx="24" cy="24" r="16"/><path d="M20 17l11 7-11 7z"/></svg><b><span class="de">Impulse von mir</span><span class="en">Talks from me</span></b><p><span class="de">Kurze Videos, in denen ich dir erzähle, was mir selbst geholfen hat, als ich mein Atelier und meine Arbeit mit Frauen aufgebaut habe.</span><span class="en">Short videos in which I share what helped me when I built my studio and my work with women.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><path d="M10 34c6-2 8-10 14-10s8 8 14 10"/><circle cx="24" cy="14" r="4"/><path d="M8 40h32"/></svg><b><span class="de">Rituale für deinen Alltag</span><span class="en">Rituals for your everyday life</span></b><p><span class="de">Journaling, Meditation und Breathwork, die dich durch die Woche tragen und dir helfen, bei dir anzukommen.</span><span class="en">Journaling, meditation and breathwork that carry you through the week and help you come home to yourself.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><path d="M24 40c-9-6-14-11-14-18a8 8 0 0 1 14-5 8 8 0 0 1 14 5c0 7-5 12-14 18z"/></svg><b><span class="de">Frauen an deiner Seite</span><span class="en">Women by your side</span></b><p><span class="de">Ihr unterstützt euch gegenseitig, teilt eure Schritte und feiert jeden kleinen Erfolg miteinander.</span><span class="en">You support each other, share your steps and celebrate every small win together.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><rect x="7" y="11" width="34" height="26" rx="5"/><circle cx="24" cy="24" r="5"/><path d="M33 16h2"/></svg><b><span class="de">Alle Aufnahmen</span><span class="en">All recordings</span></b><p><span class="de">Einen Call verpasst? Kein Problem, du findest jede Aufnahme danach in deinem Mitgliederbereich.</span><span class="en">Missed a call? No problem, you will find every recording in your members area afterwards.</span></p></div>
  </div>
</div></section>

<section class="w s"><div class="in">
  <div class="price">
    <span class="blob" style="width:260px;height:240px;background:rgba(232,214,202,.18);right:-80px;top:-90px"></span>
    <div style="position:relative">
      <p class="k"><span class="de">Deine Investition</span><span class="en">Your investment</span></p>
      <h2 style="margin-top:.6rem"><span class="de">Drei Monate <em style="color:var(--peach)">für dich</em></span><span class="en">Three months <em style="color:var(--peach)">for you</em></span></h2>
      <p class="note"><span class="de">Dabei sind die wöchentlichen Live-Calls, 26 Bewegungsvideos, alle Workbooks, alle Aufnahmen und deine Rituale für Journaling, Meditation und Breathwork. Unfold startet nur zweimal im Jahr und es gibt höchstens 10 Plätze, damit jede Frau wirklich gesehen wird.</span><span class="en">Included are the weekly live calls, 26 movement videos, all workbooks, all recordings and your rituals for journaling, meditation and breathwork. Unfold only starts twice a year and there are no more than 10 spots, so every woman is truly seen.</span></p>
      <p class="gp"><b><span class="de">Info-Gathering · 30 Minuten · kostenlos</span><span class="en">Info Gathering · 30 minutes · free</span></b><span class="de">Vor dem Start erzähle ich dir live alles über Unfold und beantworte deine Fragen.</span><span class="en">Before the start I'll tell you everything about Unfold live and answer your questions.</span></p>
    </div>
    <div style="position:relative">
      <?php if ($c['eb_u']): ?><p class="k" style="color:var(--peach)"><span class="de">Early-Bird bis <?= mv_date($c['eb_u_end']) ?></span><span class="en">Early bird until <?= mv_date($c['eb_u_end'], 'en') ?></span></p><?php endif; ?>
      <p class="amt"><?php if ($c['eb_u']): ?><s style="opacity:.5;font-size:.55em;font-weight:400;margin-right:.2em"><?= $eur($c['r_u_once']) ?></s> <?php endif; ?><?= $eur($c['u_once']) ?></p>
      <?php if ($c['eb_u']): ?><p class="mut" style="color:inherit;opacity:.85;margin:.3rem 0 0"><span class="de">Bis <?= mv_date($c['eb_u_end']) ?> buchen und <?= $eur($c['r_u_once'] - $c['u_once']) ?> sparen. Danach kostet Unfold <?= $eur($c['r_u_once']) ?> oder <?= (int)$c['u_n'] ?> × <?= $eur($c['r_u_inst']) ?>.</span><span class="en">Book by <?= mv_date($c['eb_u_end'], 'en') ?> and save <?= $eur($c['r_u_once'] - $c['u_once']) ?>. After that Unfold costs <?= $eur($c['r_u_once']) ?> or <?= (int)$c['u_n'] ?> × <?= $eur($c['r_u_inst']) ?>.</span></p><?php endif; ?>
      <div class="row" style="margin-top:.8rem"><span><span class="de">auf einmal</span><span class="en">in full</span> <em class="tag"><span class="de">beliebteste Wahl</span><span class="en">most popular</span></em></span><span><?= $eur($c['u_once']) ?></span></div>
      <div class="row"><span><span class="de">oder in <?= (int)$c['u_n'] ?> Raten</span><span class="en">or <?= (int)$c['u_n'] ?> instalments</span></span><span><?= (int)$c['u_n'] ?> × <?= $eur($c['u_inst']) ?></span></div>
      <div class="row"><span><span class="de">Erster Live-Call</span><span class="en">First live call</span></span><span><span class="de"><?= $stDe ?></span><span class="en"><?= $stEn ?></span></span></div>
      <a class="btn light" href="#buchen" style="margin-top:1.2rem"><span class="de">Platz sichern</span><span class="en">Book my spot</span> →</a>
    </div>
  </div>
</div></section>

<section class="w s" id="buchen"><div class="in">
  <div class="join">
    <div>
      <p class="k"><?= $open ? '<span class="de">Jetzt buchen</span><span class="en">Book now</span>' : '<span class="de">Ausgebucht</span><span class="en">Fully booked</span>' ?></p>
      <h2 style="margin-top:.6rem"><?= $open ? '<span class="de">Sichere dir <em>deinen Platz</em></span><span class="en">Book <em>your spot</em></span>' : '<span class="de">Diesmal sind <em>alle Plätze</em> vergeben</span><span class="en">All spots are <em>taken</em> this time</span>' ?></h2>
      <?php if ($open): ?>
      <p class="mut" style="margin-top:.8rem"><span class="de">Du zahlst sicher über Stripe und sicherst dir damit deinen Platz. Danach bestätigst du kurz deine E-Mail-Adresse und bekommst deine Bestätigung mit allen Infos. Am <?= $stDe ?> schaltet sich dein Zugang zum Mitgliederbereich frei, am selben Tag treffen wir uns zum ersten Live-Call. Dein 14-tägiges Rücktrittsrecht beginnt erst mit dem Start, du kannst also bis <?= $wuDe ?> zurücktreten.</span><span class="en">You pay securely via Stripe and your spot is saved. Then you quickly confirm your email address and get your confirmation with all the details. On <?= $stEn ?> your access to the members area unlocks; that same day we meet for the first live call. Your 14-day right of withdrawal only begins with the start, so you can withdraw until <?= $wuEn ?>.</span></p>
      <?php if ($free <= 4): ?><p class="ok" style="margin-top:1rem"><span class="de">Noch <?= $free ?> <?= $free === 1 ? 'Platz' : 'Plätze' ?> frei.</span><span class="en"><?= $free ?> <?= $free === 1 ? 'spot' : 'spots' ?> left.</span></p><?php endif; ?>
      <p class="mut small" style="margin-top:1rem"><span class="de">Noch unsicher? Trag dich unten für das kostenlose Info-Gathering ein.</span><span class="en">Not sure yet? Join the free Info Gathering below.</span></p>
      <?php else: ?><p class="mut" style="margin-top:.8rem"><span class="de">Trag dich ein, dann bist du die Erste, die von der nächsten Runde erfährt.</span><span class="en">Leave your details and you'll be the first to hear about the next round.</span></p><?php endif; ?>
    </div>
    <?php if ($open): ?>
    <form id="bf" novalidate>
      <label class="f"><span class="de">Vor- und Nachname</span><span class="en">Full name</span><input type="text" name="name" autocomplete="name" required maxlength="80"></label>
      <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required maxlength="160"></label>
      <fieldset class="opt"><legend><span class="de">Zahlung</span><span class="en">Payment</span></legend>
        <?php $instTotal = (int)$c['u_n'] * (int)$c['u_inst']; $save = $instTotal - (int)$c['u_once']; ?>
        <div class="plans">
          <label class="plan best"><input type="radio" name="plan" value="u1" checked><span class="badge"><span class="de">Beliebteste Wahl</span><span class="en">Most popular</span></span>
            <span class="pt"><span class="de">Auf einmal</span><span class="en">Pay in full</span></span>
            <span class="pa"><?= $eur($c['u_once']) ?></span>
            <span class="ps"><?= $save > 0 ? '<span class="de">Du sparst ' . $eur($save) . '</span><span class="en">You save ' . $eur($save) . '</span>' : '<span class="de">einmalig</span><span class="en">one payment</span>' ?></span></label>
          <label class="plan"><input type="radio" name="plan" value="u3">
            <span class="pt"><span class="de">In <?= (int)$c['u_n'] ?> Raten</span><span class="en"><?= (int)$c['u_n'] ?> instalments</span></span>
            <span class="pa"><?= (int)$c['u_n'] ?> × <?= $eur($c['u_inst']) ?></span>
            <span class="ps"><span class="de">monatlich · gesamt <?= $eur($instTotal) ?></span><span class="en">monthly · <?= $eur($instTotal) ?> in total</span></span></label>
        </div>
      </fieldset>
      <fieldset class="opt"><legend><span class="de">Dein Live-Call</span><span class="en">Your live call</span></legend>
        <label class="pick"><input type="radio" name="call_lang" value="de"><span><b>Deutsch</b> · <?= forma_h($c['u_call_de']) ?></span></label>
        <label class="pick"><input type="radio" name="call_lang" value="en"><span><b>English</b> · <?= forma_h($c['u_call_en']) ?></span></label>
      </fieldset>
      <fieldset class="opt"><legend><span class="de">Dein Rücktrittsrecht</span><span class="en">Your right of withdrawal</span></legend>
        <label class="pick wr"><input type="radio" name="wr" value="verzicht"><span><b><span class="de">Gleich alles nutzen</span><span class="en">Use everything right away</span></b><small><span class="de">Ich verzichte auf mein Rücktrittsrecht, einmal für die ganze Begleitung, auch bei Ratenzahlung nicht jeden Monat neu.</span><span class="en">I waive my right of withdrawal, once for the whole programme, also with instalments not again every month.</span></small></span></label>
        <label class="pick wr"><input type="radio" name="wr" value="behalten"><span><b><span class="de">Rücktrittsrecht behalten</span><span class="en">Keep my right of withdrawal</span></b><small><span class="de">bis <?= $wuDe ?>, die Workbooks öffnen sich danach.</span><span class="en">until <?= $wuEn ?>, the workbooks open after that.</span></small></span></label>
        <p class="mut" style="margin:.2rem 0 0;font-size:.84rem"><span class="de">Alle Details stehen in den</span><span class="en">All details are in the</span> <a href="/terms/#unfold" target="_blank"><span class="de">Bedingungen</span><span class="en">terms</span></a>.</p>
      </fieldset>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div><button class="btn" type="submit" id="bb"><span class="de">Weiter zur Zahlung</span><span class="en">Continue to payment</span> →</button></div>
      <p class="err" id="be" hidden></p>
    </form>
    <?php endif; ?>
  </div>
  <?php if (isset($_GET['abbruch'])): ?><p class="ok" style="margin-top:1rem"><span class="de">Die Zahlung wurde abgebrochen, es wurde nichts abgebucht. Du kannst es jederzeit noch einmal versuchen.</span><span class="en">The payment was cancelled, nothing was charged. You can try again any time.</span></p><?php endif; ?>
</div></section>

<section class="w s" id="infos"><div class="in">
  <div class="join soft">
    <div>
      <p class="k"><span class="de">Info-Gathering · kostenlos</span><span class="en">Info Gathering · free</span></p>
      <h2 style="margin-top:.6rem"><span class="de">Erst mal <em>reinspüren</em></span><span class="en">Get a <em>feel</em> first</span></h2>
      <p class="mut" style="margin-top:.8rem"><span class="de">Trag dich ein und bestätige deine E-Mail-Adresse. Dann schicke ich dir alle Infos und den Termin für das 30-minütige Info-Gathering vor dem Start.</span><span class="en">Leave your details and confirm your email. I'll send you all the details and the date of the 30-minute Info Gathering before the start.</span></p>
    </div>
    <form id="jf" novalidate>
      <label class="f"><span class="de">Vorname</span><span class="en">First name</span><input type="text" name="name" autocomplete="given-name" required maxlength="80"></label>
      <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required maxlength="160"></label>
      <label class="chk"><input type="checkbox" name="consent" value="1" required><span><span class="de">Ja, Vivien darf mir zu Unfold und dem Wild Hearts Collective schreiben. Ich kann mich jederzeit abmelden.</span><span class="en">Yes, Vivien may email me about Unfold and the Wild Hearts Collective. I can unsubscribe at any time.</span></span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div><button class="btn line" type="submit" id="jb"><span class="de">Infos bekommen</span><span class="en">Send me the details</span></button></div>
      <p class="err" id="je" hidden></p>
      <p class="ok" id="jo" hidden><span class="de">Fast geschafft! Schau in dein Postfach und bestätige deine E-Mail-Adresse. 🤍</span><span class="en">Almost there! Check your inbox and confirm your email address. 🤍</span></p>
    </form>
  </div>
  <?php if (isset($_GET['ok'])): ?><p class="ok" style="margin-top:1rem"><span class="de">Danke, du bist auf der Liste! Ich melde mich mit dem Termin fürs Info-Gathering.</span><span class="en">Thank you, you are on the list! I'll be in touch with the date of the Info Gathering.</span></p><?php endif; ?>
</div></section>

<section class="w s"><div class="in me">
  <img src="/img/wildhearts/viv.jpg" alt="Vivien">
  <div>
    <p class="k"><span class="de">Hallo, ich bin Vivien</span><span class="en">Hi, I'm Vivien</span></p>
    <p style="margin-top:.8rem"><span class="de">Ich bin Mama von vier Kindern, Bewegungstrainerin und habe mir in den Tiroler Bergen mein eigenes Atelier aufgebaut, mit vielen Umwegen, Zweifeln und Tagen, an denen ich nicht wusste, wo ich anfangen soll. Genau deshalb gibt es Unfold. Ich möchte dich auf dem Weg begleiten, den ich selbst gegangen bin: zurück zu dir und hinein in das, was du dir wirklich wünschst.</span><span class="en">I am a mother of four, a movement teacher, and I built my own studio in the Tyrolean mountains, with many detours, doubts and days when I didn't know where to begin. That is exactly why Unfold exists. I want to walk the path with you that I walked myself: back to yourself and into what you truly want.</span></p>
    <p class="hand"><span class="de">Ich freue mich so auf dich.</span><span class="en">I can't wait to meet you.</span></p>
  </div>
</div></section>

<section class="w s"><div class="in" style="max-width:860px">
  <p class="k"><span class="de">Fragen</span><span class="en">Questions</span></p>
  <h2 style="margin-top:.6rem;margin-bottom:1rem"><span class="de">Gut zu <em>wissen</em></span><span class="en">Good to <em>know</em></span></h2>
  <details class="q"><summary><span class="de">Muss ich schon eine Idee haben?</span><span class="en">Do I need to have an idea already?</span></summary><p><span class="de">Nein. Manche Frauen kommen mit einer klaren Idee, andere nur mit dem Gefühl, dass sich etwas ändern soll. Beides ist ein wunderbarer Anfang.</span><span class="en">No. Some women come with a clear idea, others just with the feeling that something needs to change. Both are a beautiful beginning.</span></p></details>
  <details class="q"><summary><span class="de">Wie viel Zeit brauche ich pro Woche?</span><span class="en">How much time do I need per week?</span></summary><p><span class="de">Rechne mit etwa zwei Stunden: der Live-Call, ein Impuls-Video und ein wenig Zeit mit deinem Workbook. Die zwei Bewegungsvideos pro Woche machst du, wann es dir passt.</span><span class="en">Plan for about two hours: the live call, a short talk video and some time with your workbook. You do the two weekly movement videos whenever it suits you.</span></p></details>
  <details class="q"><summary><span class="de">Was ist, wenn ich einen Call verpasse?</span><span class="en">What if I miss a call?</span></summary><p><span class="de">Jeder Call wird aufgenommen und steht danach in deinem Mitgliederbereich. Du kannst auch einmal in die andere Sprachgruppe wechseln.</span><span class="en">Every call is recorded and waits for you in your members area. You can also join the other language group now and then.</span></p></details>
  <details class="q"><summary><span class="de">Muss ich sportlich sein?</span><span class="en">Do I need to be fit?</span></summary><p><span class="de">Überhaupt nicht. Die Bewegung ist sanft und achtsam, ganz ohne Leistungsdruck. Du machst nur, was sich für dich gut anfühlt.</span><span class="en">Not at all. The movement is gentle and mindful, without any pressure to perform. You only do what feels good to you.</span></p></details>
  <details class="q"><summary><span class="de">Wann beginnt es und wann bekomme ich Zugang?</span><span class="en">When does it start and when do I get access?</span></summary><p><span class="de">Du kannst schon jetzt buchen und dir deinen Platz sichern. Dein Zugang zum Mitgliederbereich schaltet sich am <?= $stDe ?> frei, am selben Tag ist der erste Live-Call, und ab dann kommen jeden Montag und Donnerstag neue Bewegungsvideos. Dein Rücktrittsrecht beginnt erst mit dem Start und läuft bis <?= $wuDe ?>.</span><span class="en">You can book now and save your spot. Your access to the members area unlocks on <?= $stEn ?>, the first live call is that same day, and from then on new movement videos arrive every Monday and Thursday. Your right of withdrawal only begins with the start and runs until <?= $wuEn ?>.</span></p></details>
  <details class="q"><summary><span class="de">Kann ich in Raten zahlen?</span><span class="en">Can I pay in instalments?</span></summary><p><span class="de">Ja. Du zahlst entweder einmal <?= $eur($c['u_once']) ?> oder <?= (int)$c['u_n'] ?> × <?= $eur($c['u_inst']) ?>, jeweils monatlich.</span><span class="en">Yes. You pay either <?= $eur($c['u_once']) ?> at once or <?= (int)$c['u_n'] ?> monthly instalments of <?= $eur($c['u_inst']) ?>.</span></p></details>
</div></section>
</main>

<footer class="w"><div class="in">
  <p>Movecia · Vivien Malonga · <a href="/">moveciamovement.com</a> · <a href="/terms">Impressum &amp; Bedingungen</a></p>
</div></footer>

<script>
(() => {
  const root = document.documentElement;
  let l = <?= json_encode($lang) ?>;
  try { if (!l) l = localStorage.getItem('wh-lang') || ''; } catch (e) {}
  if (!l) l = (navigator.language || '').toLowerCase().startsWith('de') ? 'de' : 'en';
  const set = v => { root.dataset.l = v; root.lang = v; document.querySelectorAll('.lang button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.l === v))); try { localStorage.setItem('wh-lang', v); } catch (e) {} };
  set(l);
  document.querySelectorAll('.lang button').forEach(b => b.addEventListener('click', () => set(b.dataset.l)));
  const bf = document.getElementById('bf');
  if (bf) {
    const cl = bf.querySelector('input[name=call_lang][value="' + root.dataset.l + '"]'); if (cl) cl.checked = true;
    document.querySelectorAll('.lang button').forEach(b => b.addEventListener('click', () => { const x = bf.querySelector('input[name=call_lang][value="' + b.dataset.l + '"]'); if (x && !bf.dataset.touched) x.checked = true; }));
    bf.querySelectorAll('input[name=call_lang]').forEach(x => x.addEventListener('change', () => bf.dataset.touched = 1));
    const be = document.getElementById('be'), bb = document.getElementById('bb');
    bf.addEventListener('submit', async ev => {
      ev.preventDefault(); be.hidden = true;
      const de = root.dataset.l === 'de';
      if (!bf.name.value.trim() || !bf.email.value.includes('@')) { be.textContent = de ? 'Bitte gib deinen Namen und deine E-Mail an.' : 'Please enter your name and email.'; be.hidden = false; return; }
      if (!bf.querySelector('input[name=wr]:checked')) { be.textContent = de ? 'Bitte wähle, ob du dein Rücktrittsrecht behältst.' : 'Please choose whether you keep your right of withdrawal.'; be.hidden = false; return; }
      const d = Object.fromEntries(new FormData(bf)); d.lang = root.dataset.l;
      bb.disabled = true;
      try {
        const r = await fetch('/api/method.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(d)});
        const j = await r.json();
        if (j.url) { location.href = j.url; return; }
        be.textContent = j.error || '…'; be.hidden = false;
      } catch (x) { be.textContent = de ? 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.' : 'That did not work. Please try again.'; be.hidden = false; }
      bb.disabled = false;
    });
  }
  const t0 = Date.now(), f = document.getElementById('jf'), e = document.getElementById('je'), b = document.getElementById('jb');
  f.addEventListener('submit', async ev => {
    ev.preventDefault(); e.hidden = true;
    const d = Object.fromEntries(new FormData(f)); d.source = 'unfold'; d.lang = root.dataset.l; d.elapsed = Date.now() - t0;
    b.disabled = true;
    try {
      const r = await fetch('/api/wh-subscribe.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(d)});
      const j = await r.json();
      if (j.ok) { f.querySelectorAll('label,div').forEach(x => x.hidden = true); document.getElementById('jo').hidden = false; return; }
      e.textContent = j.error || '…'; e.hidden = false;
    } catch (x) { e.textContent = root.dataset.l === 'de' ? 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.' : 'That did not work. Please try again.'; e.hidden = false; }
    b.disabled = false;
  });
})();
</script>
</body>
</html>

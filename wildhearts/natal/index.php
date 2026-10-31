<?php
// moveciamovement.com/natal – Prä- & Postnatal Training: Angebot, Buchung (Stripe) und persönliche Terminseite.
// ?a=TOKEN  persönliches Angebot (Begrüßung mit Namen)
// ?o=ID&k=TOKEN  persönliche Seite nach der Buchung (Einheiten, Termine, neue Wunschtermine)
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/natal-lib.php';
require_once dirname(__DIR__) . '/assets/waves.inc.php';
header('Cache-Control: no-store');
$pdo = forma_db();
natal_schema($pdo);
natal_bonus_tick($pdo);
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
// Kennenlern-Bonus aus der Mail nach dem Gespräch (?bonus=TOKEN): Pakete günstiger, Preis bestimmt der Server
$bonus = null; $bonusP = [];
if (!$order && ($_GET['bonus'] ?? '') !== '') {
    $bonus = natal_bonus_valid($pdo, (string)$_GET['bonus']);
    if ($bonus) $bonusP = natal_bonus_prices(natal_bonus_cfg()['percent']);
}
$bonusGone = !$order && ($_GET['bonus'] ?? '') !== '' && !$bonus;
$online = $order ? natal_is_online($order) : (in_array($_GET['m'] ?? '', ['studio', 'online'], true) ? $_GET['m'] === 'online' : ($offer && ($offer['mode'] ?? 'studio') === 'online'));
// Mix & Match (Vivien, 30.10.2026): persönliches Paket mit Einheiten im Studio UND online, nur per Angebots-Link
$mix = $offer ? natal_offer_mix($offer) : null;
if ($mix) { $bonus = null; $bonusP = []; }
$orderMix = $order && natal_is_mix($order);
$packs = natal_packages_for($online ? 'online' : 'studio');
// Was in den einzelnen Einheiten passiert (aus dem Curriculum, Vivien 23.10.2026)
$units = [
    'prae' => [
        ['Ankommen im Körper', 'Was sich gerade in dir verändert, eine gute Haltung und die 360°-Atmung.', ['Mobilisation', 'Atmung', 'Body-Scan']],
        ['Beckenboden: anspannen und loslassen', 'Kraft aufbauen und weich werden lernen, beides brauchst du für die Geburt.', ['Beckenboden', 'Atmung', 'Visualisierung']],
        ['Rücken entlasten, Kraft für den Alltag', 'Gegen Rücken- und Ischiasschmerz, richtig heben, tragen und gut schlafen.', ['Kräftigung', 'Haltung', 'Entspannung']],
        ['Der Atem als Werkzeug für die Wehen', 'Die Phasen der Geburt einfach erklärt, Wellenatmung und Tönen.', ['Geburtsvorbereitung', 'Breathwork']],
        ['Geburtspositionen und Loslassen', 'Positionen und Griffe üben, gern gemeinsam mit deiner Begleitperson.', ['Geburtsvorbereitung', 'Affirmationen']],
        ['Gut ins Wochenbett', 'Ruhe, Hilfe organisieren und dein eigenes kleines Programm zum Mitnehmen.', ['Vorbereitung', 'Yoga Nidra']],
    ],
    'post' => [
        ['Ankommen nach der Geburt', 'Raum für deine Geburtsgeschichte und ein ganz sanfter Start.', ['Heilung', 'Atmung', 'Body-Scan']],
        ['Den Beckenboden neu spüren', 'Den Beckenboden wieder aktivieren und im Alltag schützen.', ['Beckenboden', 'Rückbildung']],
        ['Tiefe Bauchmuskeln und Rektusdiastase', 'Die Bauchmitte sanft und Schritt für Schritt wieder schließen.', ['Rückbildung', 'Kräftigung']],
        ['Haltung, Tragen und Stillen', 'Nacken und Schultern entlasten und ergonomisch heben.', ['Haltung', 'Mobilisation']],
        ['Kraft für den Alltag mit Baby', 'Die Belastung behutsam steigern und spüren, was dir guttut.', ['Kräftigung', 'Ausdauer']],
        ['Nervensystem, Schlafmangel und Mental Load', 'Eine bewusst ruhige Einheit mit viel Zeit für Entspannung.', ['Breathwork', 'Yoga Nidra']],
        ['Zurück zur Lieblingsbewegung', 'Wann Laufen und Sport wieder möglich sind und wie du gut zurückfindest.', ['Belastungstest', 'Ganzkörper']],
        ['Dein Programm für zu Hause', 'Ein Rückblick und dein persönliches 15-Minuten-Programm.', ['Abschluss', 'Ritual']],
    ],
];
$modeInfo = [];
foreach (['studio', 'online'] as $md) {
    $ps = natal_packages_for($md);
    $mins = [];
    foreach ($ps as $k => $x) $mins[] = $bonusP[$k]['unit'] ?? $x['unit_cents'];
    $modeInfo[$md] = ['packs' => $ps, 'single' => $ps[$md === 'online' ? 'o_einzel' : 'einzel']['cents'], 'min' => min($mins)];
}
$single = $packs[$online ? 'o_einzel' : 'einzel']['cents'];
$where = $online ? 'live per Video' : 'im Studio';
$sessions = $order ? natal_sessions($pdo, (int)$order['id']) : [];
$left = $order ? natal_left($pdo, $order) : 0;
$taken = array_values(array_map(fn($s) => $s['date'], array_filter($sessions, fn($s) => in_array($s['status'], ['angefragt', 'bestaetigt'], true))));
$cal = ['weekdays' => natal_weekdays(), 'dayparts' => NATAL_DAYPARTS, 'min' => date('Y-m-d', strtotime('+1 day')), 'max' => date('Y-m-d', strtotime('+9 months')), 'taken' => $taken, 'due' => $order['due_date'] ?? '',
    'mix' => $mix ? ['studio' => $mix['studio'], 'online' => $mix['online']] : ($orderMix ? natal_mix_left($pdo, $order) : null)];
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
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="manifest" href="/app.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Movecia"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="/wildhearts/assets/app.js?v=2" defer></script>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php
// Google & Co. (Vivien, 24.10.2026): nur die öffentliche Angebotsseite wird gefunden, persönliche Links (?o, ?a, ?bonus) nie
$public = !$order && !$offer && !$bonus && !$bonusGone && !array_intersect(['o', 'a', 'k', 'bonus', 'news', 'danke', 'start'], array_keys($_GET));
$seoTitle = 'Schwangerschafts- & Rückbildungstraining 1:1 · Prä- & Postnatal Training in Tirol & online | Movecia';
$seoDesc = 'Prä- und postnatales Training 1:1 mit Vivien Malonga: Schwangerschaftstraining, Geburtsvorbereitung, Beckenboden und Rückbildung. Im Studio in Mathon im Paznaun (nahe Ischgl, Galtür, Kappl, Landeck) oder live online. Einzelstunde ab € ' . (int)round(min(array_column(natal_packages(), 'cents')) / 100) . ', kostenloses Kennenlerngespräch.';
?>
<title><?= $order ? 'Deine Termine · Movecia' : ($public ? $h($seoTitle) : 'Prä- & Postnatal Training · Movecia') ?></title>
<meta name="description" content="<?= $h($seoDesc) ?>">
<?php if ($public): ?>
<meta name="robots" content="index, follow, max-image-preview:large">
<link rel="canonical" href="https://moveciamovement.com/natal/">
<meta property="og:type" content="website">
<meta property="og:locale" content="de_AT">
<meta property="og:site_name" content="Movecia">
<meta property="og:url" content="https://moveciamovement.com/natal/">
<meta property="og:title" content="Prä- & Postnatal Training 1:1 · im Studio in Tirol oder online">
<meta property="og:description" content="Sanft, kräftigend und ganz auf dich abgestimmt: Schwangerschaftstraining, Beckenboden und Rückbildung mit Vivien. Kostenloses Kennenlerngespräch.">
<meta property="og:image" content="https://moveciamovement.com/img/movecia/natal-teilen.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Movecia · Prä- & Postnatal Training mit Vivien">
<meta name="twitter:card" content="summary_large_image">
<meta name="geo.region" content="AT-7">
<meta name="geo.placename" content="Mathon, Paznaun, Tirol">
<script type="application/ld+json"><?= natal_seo_jsonld() ?></script>
<?php else: ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="theme-color" content="#F5F1EB">
<link rel="stylesheet" href="/wildhearts/assets/cute.css?v=7">
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--bg:#F5F1EB;--sand:#E8DED2;--card:#FBF9F6;--ink:#2A2026;--muted:#6E6168;--line:#D3C9BC;--plum:#4A3040;--plumdeep:#3A2631;--blush:#E4DCD1;--peach:#E6DBCF;--on:#F7F1EE;--r:24px;
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
.brand .mk{width:1.35em;height:1.35em;margin-right:.32em;align-self:center;flex:none}
.top-r{display:flex;align-items:center;gap:.8rem}.top .tag{font-size:.8rem;color:var(--muted);letter-spacing:.06em}
.blob{border-radius:62% 38% 46% 54%/55% 45% 55% 45%;animation:morph 14s ease-in-out infinite alternate}
@keyframes morph{50%{border-radius:40% 60% 58% 42%/44% 58% 42% 56%}100%{border-radius:45% 55% 60% 40%/40% 60% 45% 55%}}
@media(prefers-reduced-motion:reduce){.blob,.mq-in{animation:none!important}}
.hero{display:grid;gap:2.5rem;align-items:center;padding-block:clamp(2rem,5vw,4.5rem) clamp(3rem,6vw,5rem)}
@media(min-width:1000px){.hero{grid-template-columns:1.25fr 1fr;gap:4rem}}
.script{font-family:var(--serif);font-style:italic;font-size:clamp(1.5rem,2.4vw,2.1rem);color:var(--plum);margin-top:.6rem}
.vcbox{font-size:.9rem}.vcbox summary{cursor:pointer;color:var(--peach)}
.vcrow{display:flex;gap:.5rem;margin-top:.5rem}.vcrow input{flex:1;min-width:0;font:inherit;font-size:16px;letter-spacing:.03em;text-transform:uppercase;padding:.6em .8em;border-radius:12px;border:1px solid rgba(251,245,247,.3);background:rgba(251,245,247,.08);color:var(--on)}
.vcrow input::placeholder{color:rgba(251,245,247,.45)}
.side .vcrow .btn{padding:.55em 1em;font-size:.88rem;background:transparent;color:var(--on);border-color:rgba(251,245,247,.6)}
#vcmsg{margin-top:.5rem}
.bonusbox{display:block;margin-left:0;margin-right:0;max-width:none!important;background:var(--plum);color:var(--on);border-radius:18px;padding:1rem 1.25rem;margin-top:1.4rem;max-width:34em;font-size:1rem;box-shadow:0 20px 40px -28px var(--plum)}
.bonusbox b{font-family:var(--serif);font-size:1.45rem;font-weight:500;display:block}
.bonusbox.gone{background:var(--card);color:var(--ink);box-shadow:none}
.pack .was{font-size:.95rem;color:var(--muted);text-decoration:line-through;margin-top:auto;padding-top:.8rem}
.pack .was + .p{margin-top:0;padding-top:.15rem}
.pack .bon{align-self:flex-start;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;font-weight:500;padding:.25rem .6rem;border-radius:999px;background:var(--plum);color:var(--on)}
.hello{display:inline-block;background:var(--card);border-radius:18px;padding:.9rem 1.2rem;margin-top:1.4rem;max-width:34em;font-size:1rem}
.hello b{font-family:var(--serif);font-size:1.35rem;font-weight:500;display:block;color:var(--ink)}
.lead{font-size:clamp(1.05rem,1.4vw,1.3rem);font-weight:300;color:var(--muted);max-width:32em;margin-top:1.4rem}
.modes{display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:6px;background:var(--card);border-radius:22px;margin-top:1.8rem;max-width:36rem;box-shadow:0 18px 40px -30px rgba(74,58,65,.5)}
.modes button{font:inherit;text-align:left;border:0;border-radius:17px;padding:.85rem 1rem;background:transparent;color:var(--ink);cursor:pointer;display:flex;flex-direction:column;gap:.15rem;transition:background .2s,color .2s}
.modes button b{font-family:var(--serif);font-weight:500;font-size:1.45rem;line-height:1.1}
.modes button span{font-size:.84rem;color:var(--muted);line-height:1.35}
.modes button[aria-pressed=true]{background:var(--plum);color:var(--on)}
.modes button[aria-pressed=true] span{color:var(--on);opacity:.85}
.modes.sm{margin-top:1.4rem}
.nl{display:grid;gap:1.6rem;align-items:start}
@media(min-width:900px){.nl{grid-template-columns:1.1fr 1fr;gap:3rem}}
.nlf{display:grid;gap:.9rem}
.newsbar{margin:0 auto;max-width:900px;padding:1rem 1.3rem;border-radius:18px;background:var(--plum);color:var(--on);text-align:center}
.flow{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.4rem}
.flow span{display:inline-flex;align-items:center;gap:.45rem;padding:.5rem .95rem;border-radius:999px;background:var(--card);font-size:.9rem}
.flow span i{font-style:normal;font-family:var(--serif);font-size:1.05rem;color:var(--plum)}
.etabs{display:flex;gap:6px;margin-top:2rem;padding:6px;background:var(--card);border-radius:999px;width:max-content;max-width:100%;overflow-x:auto}
.etabs button{font:inherit;font-size:.95rem;border:0;border-radius:999px;padding:.6rem 1.2rem;background:transparent;color:var(--ink);cursor:pointer;white-space:nowrap}
@media(max-width:420px){.etabs button{padding:.55rem .85rem;font-size:.9rem}}
.etabs button[aria-selected=true]{background:var(--plum);color:var(--on)}
.epanel{margin-top:1.4rem}
.epanel .intro{color:var(--muted);max-width:44em;margin:0 0 1.2rem}
.ulist{display:grid;gap:10px}
@media(min-width:700px){.ulist{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}}
@media(min-width:1200px){.ulist{grid-template-columns:repeat(3,minmax(0,1fr))}}
.unit{background:var(--card);border-radius:20px;padding:1.1rem 1.25rem}
.unit summary{list-style:none;cursor:pointer;display:flex;gap:.9rem;align-items:baseline}
.unit summary::-webkit-details-marker{display:none}
.unit .n{font-family:var(--serif);font-size:2rem;line-height:1;color:var(--plum);width:2.2rem;flex:none}
.unit h4{margin:0;font-family:var(--serif);font-weight:500;font-size:1.35rem;line-height:1.15}
.unit p{margin:.6rem 0 0 3.1rem;color:var(--muted);font-size:.95rem}
.unit .tags{display:flex;flex-wrap:wrap;gap:.35rem;margin:.7rem 0 0 3.1rem}
.unit .tags span{font-size:.74rem;letter-spacing:.04em;padding:.25rem .65rem;border-radius:999px;background:var(--blush);color:var(--plumdeep)}
@media(max-width:699px){.unit summary:after{content:"+";margin-left:auto;font-size:1.3rem;color:var(--plum)}.unit[open] summary:after{content:"–"}.unit p,.unit .tags{margin-left:0}}
.ktl{display:grid;gap:10px}
@media(min-width:800px){.ktl{grid-template-columns:1fr auto 1fr;align-items:stretch}}
.ktl .seg{background:var(--card);border-radius:20px;padding:1.2rem 1.3rem}
.ktl .seg h4{margin:0 0 .5rem;font-family:var(--serif);font-weight:500;font-size:1.4rem}
.ktl .seg ol{margin:0;padding-left:1.2rem;color:var(--muted);font-size:.93rem;display:grid;gap:.15rem}
.ktl .gap{display:grid;place-items:center;text-align:center;padding:1rem;border-radius:20px;background:var(--blush);font-size:.88rem;color:var(--plumdeep);max-width:none}
@media(min-width:800px){.ktl .gap{max-width:11rem}}
.modes button .pr{margin-top:.35rem;color:var(--ink);font-size:.88rem}
.modes button[aria-pressed=true] .pr{color:var(--on);opacity:1}
@media(max-width:560px){.top .tag{display:none}.modes button{padding:.75rem .8rem}.modes button b{font-size:1.3rem}}
body[data-mode=online] .m-studio,body[data-mode=studio] .m-online{display:none!important}
.cta{display:flex;flex-wrap:wrap;gap:.8rem;margin-top:2rem}
.hv{position:relative;justify-self:center;width:min(100%,460px);margin:0;padding:0 0 18% 0}
.hv img{position:relative;display:block;width:82%;margin-left:12%;aspect-ratio:3/4;object-fit:cover;object-position:50% 20%;border-radius:240px 240px 24px 24px}
.hv .b1{position:absolute;inset:6% 0 20% 2%;background:var(--blush)}
.hv .b3{position:absolute;width:22%;aspect-ratio:1;left:0;top:4%;background:var(--peach);border-radius:50%}
.hv .q{position:absolute;left:0;right:14%;bottom:0;background:rgba(251,248,244,.95);border-radius:20px;padding:1rem 1.2rem;font-family:var(--serif);font-size:clamp(1.2rem,2vw,1.55rem);line-height:1.25;color:var(--ink);box-shadow:0 14px 30px -20px rgba(74,58,65,.5)}
.hv .q small{display:block;font-family:var(--sans);font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--plum);margin-top:.5rem}
@media(max-width:599px){.hv{width:min(100%,320px)}}
.mq{overflow:hidden;background:var(--plum);color:var(--on);padding-block:1rem}
.mq-in{display:flex;gap:2.4rem;width:max-content;animation:mq 40s linear infinite;font-family:var(--serif);font-style:italic;font-size:clamp(1.4rem,2.4vw,2rem)}
.mq-in span::after{content:"✦";font-style:normal;font-size:.55em;margin-left:2.4rem;opacity:.6;vertical-align:middle}
@keyframes mq{to{transform:translateX(-50%)}}
.two{display:grid;gap:16px;margin-top:2.6rem}
.wide-pic{margin:16px 0 0;border-radius:24px;overflow:hidden;aspect-ratio:21/9;background:#E6DBCF}.wide-pic img{display:block;width:100%;height:100%;object-fit:cover;object-position:50% 55%}@media(max-width:700px){.wide-pic{aspect-ratio:4/3}}
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
input[type=text],input[type=email],input[type=tel],input[type=date],input[type=number],textarea,select{font:inherit;font-size:1rem;color:var(--ink);background:var(--bg);border:1px solid transparent;border-radius:14px;padding:.8em 1em;width:100%;min-width:0}
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
/* Mix & Match */
.mixcard{background:var(--card);border-radius:26px;padding:1.4rem 1.5rem 1.3rem;margin-top:1.6rem;max-width:36rem;box-shadow:0 22px 44px -32px rgba(74,58,65,.55);position:relative;overflow:hidden}
.mixcard::after{content:"";position:absolute;width:150px;height:140px;right:-55px;top:-60px;border-radius:62% 38% 46% 54%/55% 45% 55% 45%;background:var(--blush);opacity:.8}
.mixcard>*{position:relative;z-index:1}
.mixcard .mt{font-family:var(--serif);font-size:1.75rem;font-weight:500;line-height:1.1;margin-top:.35rem}
.mixcard .mr{display:flex;justify-content:space-between;gap:1rem;align-items:baseline;border-top:1px solid var(--line);padding:.75rem 0}
.mixcard .mr:first-of-type{margin-top:.9rem}
.mixcard .mr small{display:block;color:var(--muted);font-size:.85rem}
.mixcard .mr b{font-weight:500;white-space:nowrap}
.mixcard .tot{display:flex;justify-content:space-between;align-items:baseline;border-top:2px solid var(--plum);padding-top:.8rem}
.mixcard .tot b{font-family:var(--serif);font-size:2.4rem;font-weight:500;line-height:1}
.mixcard .fine{font-size:.82rem;color:var(--muted);margin-top:.6rem}
.mm{display:flex;gap:6px;flex-wrap:wrap}
.mm button{border:1px solid var(--line);background:var(--card);color:var(--ink);font:inherit;font-size:.82rem;padding:.4em .9em;border-radius:999px;cursor:pointer}
.mm button[aria-pressed=true]{background:var(--plumdeep);border-color:var(--plumdeep);color:var(--on)}
.mm button:disabled{opacity:.4;cursor:default}
.wt{display:flex;align-items:center;gap:.6rem;font-size:.82rem;color:var(--muted)}
.wt input{width:auto;padding:.45em .7em;font-size:.9rem;background:var(--card)}
.mixleft{font-size:.84rem;color:var(--muted);margin-top:.3rem}
.mixcard .mr{align-items:center}
.stp{display:inline-flex;align-items:center;gap:.5rem;background:var(--bg);border-radius:999px;padding:4px}
.stp button{width:38px;height:38px;border-radius:50%;border:0;background:var(--card);color:var(--plum);font:inherit;font-size:1.3rem;line-height:1;cursor:pointer}
.stp button:disabled{opacity:.35;cursor:default}
.stp b{min-width:1.6em;text-align:center;font-family:var(--serif);font-size:1.6rem;font-weight:500}
<?= NATAL_HEALTH_CSS ?>
</style>
<script src="/wildhearts/assets/schutz.js?v=3"></script>
</head>
<body data-mode="<?= $mix || $orderMix ? 'mix' : ($online ? 'online' : 'studio') ?>">
<header class="top w"><a class="brand" href="<?= $order ? $h(natal_link($order)) : '/' ?>"><img class="mk" src="/img/movecia/movecia-zeichen-klein.svg" alt="">Movecia</a><span class="top-r"><span class="tag">1:1 <?= $order && !$orderMix ? ($online ? 'online' : 'im Studio') : 'im Studio &amp; online' ?> · Prä- &amp; Postnatal</span><?php require_once dirname(__DIR__) . '/assets/menu.inc.php'; mv_menu('natal', ['lang' => false]); ?></span></header>
<div class="w"><?php if ($bonus): $bc = natal_bonus_cfg(); ?><p class="bonusbox"><b>Dein Kennenlern-Bonus, <?= $h(forma_first_name((string)$bonus['name'])) ?></b>Danke für unser Gespräch! Jedes Paket ist für dich <?= (int)$bc['percent'] ?> % günstiger, im Studio und online. Der Bonus gilt bis <?= $h(natal_date_de((string)$bonus['bonus_until'])) ?> und ist unten schon eingerechnet.</p>
    <?php elseif ($bonusGone): ?><p class="bonusbox gone"><b>Dieser Bonus ist leider nicht mehr gültig</b>Er ist abgelaufen oder wurde schon eingelöst. Die Pakete kannst du unten natürlich trotzdem buchen, und bei Fragen schreib mir einfach.</p><?php endif; ?></div>

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
      <div class="units" style="margin-top:.6rem"><span class="big"><?= $left ?></span><span class="mut" style="padding-bottom:.6rem">von <?= (int)$order['units'] ?> noch frei zum Anfragen<?php if ($orderMix): $ml = natal_mix_left($pdo, $order); ?><br><?= (int)$ml['studio'] ?> im Studio · <?= (int)$ml['online'] ?> online<?php endif; ?></span></div>
      <div class="ud" aria-hidden="true"><?php foreach ($sessions as $s) if ($s['status'] !== 'abgesagt') echo '<i class="' . ($s['status'] === 'bestaetigt' ? 'c' : ($s['status'] === 'erledigt' ? 'e' : 'a')) . '"></i>'; for ($i = 0; $i < $left; $i++) echo '<i></i>'; ?></div>
      <p class="legend"><span><i></i>bestätigt</span><span><i class="d"></i>angefragt</span><span><i style="background:var(--line)"></i>noch frei</span></p>
      <?php if ($order['due_date']): ?><p class="mut" style="margin-top:1rem;font-size:.9rem">Geburtstermin: <?= $h(natal_date_de((string)$order['due_date'])) ?> · gültig bis 6 Monate nach der Geburt</p><?php endif; ?>
    </div>
    <div class="card">
      <p class="k">Deine Termine</p>
      <?php if (!$sessions): ?><p class="mut" style="margin-top:.6rem">Noch keine Termine. Such dir unten deine Wunschtage aus.</p><?php endif; ?>
      <div class="sl" style="margin-top:.8rem">
        <?php foreach ($sessions as $s): ?>
          <?php $sOn = natal_sess_online($order, $s); ?>
          <div class="si"><span><b><?= $h(natal_when($s)) ?></b><?= $orderMix ? ' <span class="mut" style="font-size:.84rem">· ' . ($sOn ? 'online' : 'im Studio') . '</span>' : '' ?><?php $ph = natal_phase($order, (string)$s['date']); if ($ph): ?><br><span class="mut" style="font-size:.84rem"><?= $h($ph) ?></span><?php endif; ?></span>
            <span style="display:flex;gap:.6rem;align-items:center"><?php if ($sOn && $s['status'] === 'bestaetigt' && $s['start'] !== '' && $s['date'] >= date('Y-m-d')): ?><a class="btn" style="padding:.45em 1em;font-size:.82rem" href="<?= $h(natal_room_link('s', (int)$s['id'])) ?>">Videoraum</a><?php endif; ?><?php if ($s['status'] === 'bestaetigt' && $s['start'] !== ''): ?><a href="<?= $h(natal_gcal($s, $sOn)) ?>" target="_blank" rel="noopener" style="font-size:.84rem">Google Kalender</a><?php endif; ?><span class="chip <?= $h($s['status']) ?>"><?= $h(natal_status_label((string)$s['status'])) ?></span></span></div>
        <?php endforeach; ?>
      </div>
      <?php if ($conf): ?><p style="margin-top:1rem"><a class="btn line" href="/api/natal.php?ics=1&amp;o=<?= (int)$order['id'] ?>&amp;k=<?= $h($order['token']) ?>">Alle Termine in meinen Kalender</a></p><?php endif; ?>
    </div>
  </div>
</section>
<?php if (natal_order_is_member($order)): $hasM = natal_member_by_email($pdo, (string)$order['email']); ?>
<section class="w" style="padding-top:0"><div class="card" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:1rem;align-items:center;background:var(--plum);color:var(--on)">
  <p><b style="font-family:var(--serif);font-size:1.6rem;font-weight:500">Dein Mitgliederbereich</b><br><span style="opacity:.85">Workbooks, Übungsabfolgen<?= $online || $orderMix ? ', Aufnahmen' : '' ?> und Musik nach jeder Einheit.</span></p>
  <a class="btn" style="background:var(--peach);color:var(--ink);border-color:var(--peach)" href="<?= $h($hasM ? natal_member_link() : natal_member_start_link($order)) ?>"><?= $hasM ? 'Zu meinem Bereich' : 'Passwort wählen und los' ?></a>
</div></section>
<?php endif; ?>
<?php $hd = natal_health_get($order); ?>
<section class="w" style="padding-top:0" id="gesundheit">
  <?php if (!$hd): ?><div class="panel" id="hqbox">
    <p class="k">Bitte noch ausfüllen</p>
    <?= natal_health_form(null, (string)$order['due_date']) ?>
    <div><button class="btn" type="button" id="hqsave">Angaben speichern</button></div>
    <p class="err" id="hqerr" hidden></p>
  </div><?php else: ?>
  <details class="card" id="hqbox"><summary style="cursor:pointer;list-style:none"><b style="font-family:var(--serif);font-size:1.3rem;font-weight:500">Deine Gesundheitsangaben</b> <span class="mut" style="font-size:.9rem">· ausgefüllt am <?= $h(date('j. n. Y', strtotime((string)$hd['at']))) ?> · ändern</span></summary>
    <div style="margin-top:1rem"><?= natal_health_form($hd) ?><div style="margin-top:.8rem"><button class="btn" type="button" id="hqsave">Änderungen speichern</button></div><p class="err" id="hqerr" hidden></p></div>
  </details><?php endif; ?>
</section>
<?php if ($left > 0): ?>
<section class="w" style="padding-top:0" id="anfragen">
  <div class="panel">
    <p class="k">Neue Wunschtermine</p>
    <h3>Wann passt es dir?</h3>
    <p class="mut">Tipp auf die Tage, die dir passen, wähl die Tageszeit und gern deine Wunschuhrzeit<?= $orderMix ? ', und sag mir, ob du ins Studio kommst oder wir uns online sehen' : '' ?>. Du kannst bis zu <?= $left === 1 ? '1 Termin' : $left . ' Termine' ?> auf einmal anfragen. Ich bestätige dir jeden Termin mit Uhrzeit per E-Mail.</p>
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
<?php $news = (string)($_GET['news'] ?? ''); if (in_array($news, ['ok', 'ab', 'alt'], true)): ?>
<div class="w" style="padding-top:1rem"><p class="newsbar"><?= $news === 'ok' ? 'Danke, deine Adresse ist bestätigt! Die Mail mit allen Paketen ist schon unterwegs zu dir. 🤍' : ($news === 'ab' ? 'Du bist abgemeldet und bekommst keine Mails mehr zum Prä- und Postnatal-Training.' : 'Dieser Link funktioniert nicht mehr. Trag dich unten einfach noch einmal ein.') ?></p></div>
<?php endif; ?>
<section class="hero w has-hw"><?= mv_waves(false) ?>
  <div>
    <p class="k">Prä- &amp; Postnatal Training · 1:1 im Studio oder online</p>
    <h1 style="margin-top:.8rem">Bewegt durch die <em>Schwangerschaft</em> &amp; das Wochenbett</h1>
    <p class="script">für dich und dein Baby</p>
    <?php if ($mix): ?>
    <p class="hello"><b><?= $h(($offer['salutation'] ?: 'Liebe') . ' ' . $offer['name']) ?>,</b>schön, dass du da bist. Wie besprochen kannst du dein Paket ganz frei mischen: Du entscheidest selbst, wie viele Einheiten du bei mir im Studio machst und wie viele online, live per Video. Unten bestätigst du es, suchst dir deine Wunschtermine aus und bezahlst direkt.<?= $offer['note'] !== '' ? '<br><br>' . nl2br($h((string)$offer['note'])) : '' ?></p>
    <div class="mixcard">
      <p class="k">Dein persönliches Angebot · Mix &amp; Match</p>
      <p class="mt"><?= $h($mix['name']) ?>-Paket · <?= (int)$mix['units'] ?> <?= $mix['units'] === 1 ? 'Einheit' : 'Einheiten' ?></p>
      <p class="mut" style="font-size:.9rem;margin-top:.3rem">Wie viele davon möchtest du im Studio und wie viele online?</p>
      <?php foreach ([['studio', '🏡 Im Studio in Mathon', $mix['unit_studio']], ['online', '💻 Online, live per Video', $mix['unit_online']]] as [$mk, $ml, $mu]): ?>
      <div class="mr mxr" data-k="<?= $mk ?>" data-u="<?= (int)$mu ?>"><span><?= $ml ?><small class="mxsum"><?= $h($euro($mu)) ?> pro Einheit</small></span>
        <span class="stp"><button type="button" data-d="-1" aria-label="weniger <?= $mk === 'online' ? 'online' : 'im Studio' ?>">−</button><b class="mxn"><?= (int)$mix[$mk] ?></b><button type="button" data-d="1" aria-label="mehr <?= $mk === 'online' ? 'online' : 'im Studio' ?>">+</button></span></div>
      <?php endforeach; ?>
      <div class="tot"><span>Zusammen</span><b><?= $h($euro($mix['cents'])) ?></b></div>
      <p class="fine">Jede Einheit 60 Minuten · gültig bis 6 Monate nach der Geburt · inkl. 20 % USt.</p>
      <input type="radio" name="package" value="<?= $h($mix['package']) ?>" checked hidden data-units="<?= (int)$mix['units'] ?>" data-cents="<?= (int)$mix['cents'] ?>" data-title="<?= $h($mix['title']) ?>" data-unit="<?= (int)($mix['studio'] ? $mix['unit_studio'] : $mix['unit_online']) ?>" data-unitlabel="<?= $h(trim(($mix['studio'] ? $euro($mix['unit_studio']) . ' Studio' : '') . ($mix['studio'] && $mix['online'] ? ' · ' : '') . ($mix['online'] ? $euro($mix['unit_online']) . ' online' : ''))) ?>">
    </div>
    <div class="cta"><a class="btn" href="#buchen">Angebot bestätigen &amp; buchen <span aria-hidden="true">→</span></a></div>
    <?php else: ?>
    <div class="modes" role="group" aria-label="Im Studio oder online">
      <?php foreach (['studio' => ['Im Studio', 'in Mathon'], 'online' => ['Online', 'live per Video']] as $md => [$lb, $sb]): ?><button type="button" data-mode="<?= $md ?>" aria-pressed="<?= ($online ? 'online' : 'studio') === $md ? 'true' : 'false' ?>"><b><?= $lb ?></b><span><?= $sb ?></span><span class="pr">Einzelstunde <?= $h($euro($modeInfo[$md]['single'])) ?><br>Paket ab <?= $h($euro($modeInfo[$md]['min'])) ?> pro Einheit</span></button><?php endforeach; ?>
    </div>
    <?php if ($offer): ?><p class="hello"><b><?= $h(($offer['salutation'] ?: 'Liebe') . ' ' . $offer['name']) ?>,</b>schön, dass du da bist. Hier ist mein Angebot für dich, mit allem, was wir bis zur Geburt und danach gemeinsam machen können.<?= $offer['note'] !== '' ? '<br><br>' . nl2br($h((string)$offer['note'])) : '' ?></p><?php endif; ?>
    <p class="lead">Prä- und postnatales Training, ganz auf dich abgestimmt: ruhig, kräftigend und mit viel Raum für das, was dein Körper gerade braucht. Nur du und ich, bei mir im Studio oder live per Video bequem von zu Hause aus. Wähl einfach, was besser zu dir passt.</p>

    <div class="cta"><a class="btn" href="#pakete">Paket wählen <span aria-hidden="true">→</span></a><a class="btn line" href="#kennenlernen">Kostenlos kennenlernen</a></div>
    <?php endif; ?>
  </div>
  <figure class="hv"><div class="b1 blob" aria-hidden="true"></div><div class="b3" aria-hidden="true"></div><img src="/img/movecia/fotos/ev-natal.jpg?v=10" alt="Vivien in ihrer Schwangerschaft im lichten Studio" width="640" height="794"><figcaption class="q">„Dein Körper leistet gerade Großes. Lass uns ihn gut begleiten.“<small>Vivien</small></figcaption></figure>
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
  <figure class="wide-pic"><img src="/img/movecia/fotos/ev-natal-sitzend.jpg?v=10" alt="Schwangere Frau sitzt ruhig atmend auf der Matte vor großen Fenstern mit Bergblick" width="1280" height="858" loading="lazy"></figure>
</section>

<section class="w" id="ablauf" style="padding-top:0">
  <p class="k">So läuft es ab</p>
  <h2 style="margin-top:.6rem">In drei <em>Schritten</em></h2>
  <div class="steps cute-cards">
    <div class="step"><b>Paket wählen und bezahlen</b><p>Sicher online über Stripe. Danach bekommst du eine Bestätigung und deine Rechnung per E-Mail.</p></div>
    <div class="step"><b>Wunschtermine aussuchen</b><p>Im Kalender tippst du deine Wunschtage an und wählst Vormittag, Nachmittag oder Abend. Das geht gleich beim Buchen oder später auf deiner persönlichen Seite.</p></div>
    <div class="step"><b>Ich bestätige mit Uhrzeit</b><p>Du bekommst jeden Termin per E-Mail, mit Kalenderdatei für dein Handy<span class="m-online"> und dem Link zum Videoraum</span>. Weitere Termine fragst du jederzeit über deine Seite an.</p></div>
  </div>
</section>

<?php if (!$mix): ?>
<section class="w" id="kennenlernen" style="padding-top:0">
  <div class="panel kl">
    <div class="klh">
      <div>
        <p class="k">Vor der Buchung · freiwillig</p>
        <h2 style="margin-top:.5rem;font-size:clamp(2rem,4vw,3.2rem)">Erst einmal <em>kennenlernen</em>?</h2>
        <p class="mut" style="margin-top:.8rem;max-width:34em">Buch dir ein kostenloses Gespräch von 30 Minuten, online per Video. Wir sprechen darüber, wie es dir geht, was du dir wünschst und welches Paket zu dir passt, ganz unverbindlich. Du bekommst den Termin mit Kalenderdatei und Link per E-Mail.</p>
      </div>
      <p class="kltimes"><b>Mo–Fr</b>8:00–8:30 Uhr<br>oder 19:00–19:30 Uhr</p>
    </div>
    <div class="calw">
      <div id="klcal"></div>
      <div id="klslots" class="picked"><p class="mut" style="font-size:.9rem;padding:.6rem 0">Tipp zuerst auf einen Tag.</p></div>
    </div>
    <form id="klform" class="grid2" hidden novalidate>
      <label class="f">Vor- und Nachname<input type="text" name="name" autocomplete="name" required maxlength="120" value="<?= $offer ? $h((string)$offer['name']) : ($bonus ? $h((string)$bonus['name']) : '') ?>"></label>
      <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required maxlength="160" value="<?= $bonus ? $h((string)$bonus['email']) : '' ?>"></label>
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
<?php endif; ?>

<section class="w" id="einheiten" style="padding-top:0">
  <p class="k">Deine Einheiten</p>
  <h2 style="margin-top:.6rem">Was wir in den <em>einzelnen Terminen</em> machen</h2>
  <p class="mut" style="margin-top:.8rem;max-width:44em">Jede Einheit dauert 60 Minuten und hat ein eigenes Thema. Sie folgt immer demselben ruhigen Rhythmus, im Studio genauso wie online.</p>
  <div class="flow" aria-label="Ablauf einer Einheit"><span><i>1</i>Ankommen</span><span><i>2</i>Impuls zum Thema</span><span><i>3</i>Bewegung</span><span><i>4</i>Breathwork</span><span><i>5</i>Meditation &amp; Entspannung</span><span><i>6</i>Übung für zu Hause</span></div>
  <div class="etabs" role="tablist" aria-label="Pakete">
    <button type="button" role="tab" aria-selected="true" data-tab="prae">Pränatal · 6</button>
    <button type="button" role="tab" aria-selected="false" data-tab="post">Postnatal · 8</button>
    <button type="button" role="tab" aria-selected="false" data-tab="kombi">Kombi · 14</button>
  </div>
  <?php foreach (['prae' => 'Ideal ist einmal pro Woche, am besten mit Beginn zwischen der 28. und 32. Schwangerschaftswoche.', 'post' => 'Nach der ärztlichen Freigabe, meist 6 bis 8 Wochen nach der Geburt. Die ersten vier Einheiten wöchentlich, danach alle ein bis zwei Wochen. Dein Baby darf immer dabei sein.'] as $tk => $intro): ?>
  <div class="epanel" role="tabpanel" data-panel="<?= $tk ?>"<?= $tk === 'prae' ? '' : ' hidden' ?>>
    <p class="intro"><?= $h($intro) ?></p>
    <div class="ulist">
      <?php foreach ($units[$tk] as $i => [$tt, $tx, $tags]): ?>
      <details class="unit" open><summary><span class="n"><?= $i + 1 ?></span><h4><?= $h($tt) ?></h4></summary>
        <p><?= $h($tx) ?></p><div class="tags"><?php foreach ($tags as $tg) echo '<span>' . $h($tg) . '</span>'; ?></div></details>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="epanel" role="tabpanel" data-panel="kombi" hidden>
    <p class="intro">Die 6 Pränatal- und die 8 Postnatal-Einheiten in dieser Reihenfolge, mit einer Pause rund um die Geburt. Der Vorteil: Ich kenne deinen Körper und deine Geschichte schon, und du kennst die Atemübungen, bevor dein Baby kommt. Kommt dein Baby früher, rutschen offene Einheiten einfach nach hinten.</p>
    <div class="ktl">
      <div class="seg"><p class="k">Vor der Geburt · wöchentlich</p><h4>6 Einheiten Pränatal</h4><ol><?php foreach ($units['prae'] as [$tt]) echo '<li>' . $h($tt) . '</li>'; ?></ol></div>
      <div class="gap">Geburt &amp; Wochenbett<br>Pause bis zur Freigabe,<br>wir bleiben in Kontakt</div>
      <div class="seg"><p class="k">Nach der Geburt</p><h4>8 Einheiten Postnatal</h4><ol><?php foreach ($units['post'] as [$tt]) echo '<li>' . $h($tt) . '</li>'; ?></ol></div>
    </div>
  </div>
  <p class="mut" style="margin-top:1.2rem;font-size:.92rem"><b style="font-weight:500;color:var(--ink)">Einzelstunde:</b> Wir wählen gemeinsam das Thema, das du gerade am meisten brauchst.</p>
</section>

<?php if (!$mix): ?>
<section class="w" id="pakete" style="padding-top:0">
  <p class="k">Preise &amp; Pakete</p>
  <h2 style="margin-top:.6rem">Je mehr Einheiten, desto <em>günstiger</em></h2>
  <div class="modes sm" role="group" aria-label="Im Studio oder online">
      <?php foreach (['studio' => ['Im Studio', 'in Mathon'], 'online' => ['Online', 'live per Video']] as $md => [$lb, $sb]): ?><button type="button" data-mode="<?= $md ?>" aria-pressed="<?= ($online ? 'online' : 'studio') === $md ? 'true' : 'false' ?>"><b><?= $lb ?></b><span><?= $sb ?></span><span class="pr">Einzelstunde <?= $h($euro($modeInfo[$md]['single'])) ?><br>Paket ab <?= $h($euro($modeInfo[$md]['min'])) ?> pro Einheit</span></button><?php endforeach; ?>
    </div>
  <p class="mut" style="margin-top:1rem">Jede Einheit dauert 60 Minuten, 1:1 <span class="m-studio">bei mir im Studio</span><span class="m-online">live per Video</span>. Alle Preise inkl. 20 % USt.</p>
  <?php foreach ($modeInfo as $md => $mi): ?>
  <div class="packs m-<?= $md ?>" role="radiogroup" aria-label="Paket <?= $md === 'online' ? 'online' : 'im Studio' ?>">
    <?php foreach ($mi['packs'] as $k => $p): $bp = $bonusP[$k] ?? null; $was = $bp ? $p['cents'] : 0; if ($bp) { $p['cents'] = $bp['total']; $p['unit_cents'] = $bp['unit']; } $save = $mi['single'] * $p['units'] - $p['cents']; ?>
      <label class="pack"><input type="radio" name="package" value="<?= $h($k) ?>" data-units="<?= (int)$p['units'] ?>" data-cents="<?= (int)$p['cents'] ?>" data-title="<?= $h($p['title'] . ($bp ? ' · Kennenlern-Bonus' : '')) ?>" data-unit="<?= (int)$p['unit_cents'] ?>"<?= $p['tag'] !== '' && ($online ? 'online' : 'studio') === $md ? ' checked' : '' ?>>
        <?php if ($p['tag']): ?><span class="badge"><?= $h($p['tag']) ?></span><?php endif; ?><span class="ck" aria-hidden="true"></span>
        <span class="t"><?= $h(preg_replace('/ online$/', '', $p['title'])) ?></span><span class="s"><?= $h($p['sub']) ?></span>
        <?php if ($bp): ?><span class="bon">Kennenlern-Bonus</span><span class="was"><?= $h($euro($was)) ?></span><?php endif; ?>
        <span class="p"><?= $h($euro($p['cents'])) ?></span>
        <span class="u"><?= $p['units'] > 1 ? $h($euro($p['unit_cents'])) . ' pro Einheit' : 'pro Einheit' ?></span>
        <?php if ($save > 0): ?><span class="save">du sparst <?= $h($euro($save)) ?></span><?php endif; ?>
      </label>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <p class="mut" style="margin-top:1rem;font-size:.9rem">Kommt dein Baby früher, nutzt du offene Einheiten einfach nach der Geburt. Alle Pakete gelten bis 6 Monate nach der Geburt.</p>
</section>
<?php endif; ?>

<section class="w" id="buchen" style="padding-top:0">
  <form class="book" id="bookf" novalidate>
    <div class="panel">
      <p class="k"><?= $mix ? 'Angebot bestätigen' : 'Buchen' ?></p>
      <h3>Deine Angaben</h3>
      <div class="grid2">
        <label class="f">Vor- und Nachname<input type="text" name="name" autocomplete="name" required maxlength="120" value="<?= $offer ? $h((string)$offer['name']) : ($bonus ? $h((string)$bonus['name']) : '') ?>"></label>
        <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required maxlength="160" value="<?= $bonus ? $h((string)$bonus['email']) : '' ?>"></label>
        <label class="f">Telefon (für Terminfragen)<input type="tel" name="phone" autocomplete="tel" required maxlength="40"></label>
        <label class="f">Voraussichtlicher Geburtstermin<input type="date" name="due_date" id="due"></label>
      </div>
      <label class="f">Möchtest du mir vorab etwas sagen? (optional)<textarea name="note" maxlength="1000" placeholder="z. B. wie es dir gerade geht oder was dir wichtig ist"></textarea></label>
      <?= natal_health_form() ?>
      <h3 style="margin-top:.6rem">Deine Wunschtermine <span class="mut" style="font-family:var(--sans);font-size:.9rem">(optional)</span></h3>
      <p class="mut" style="font-size:.92rem"><?= $mix ? 'Tipp auf die Tage, die dir passen, wähl die Tageszeit und gern deine Wunschuhrzeit. Bei jedem Termin sagst du mir, ob du ins Studio kommst oder wir uns online sehen.' : 'Tipp auf die Tage, die dir passen, wähl die Tageszeit und gern deine Wunschuhrzeit.' ?> Du kannst die Termine auch erst nach der Zahlung auf deiner persönlichen Seite aussuchen. Ich bestätige dir jeden Termin persönlich.</p>
      <div id="calbox"></div>
      <label class="chk"><input type="checkbox" name="start_now" value="1" required><span>Ich möchte ausdrücklich, dass die Einheiten schon innerhalb der 14-tägigen Rücktrittsfrist stattfinden können. Trete ich zurück, zahle ich nur die Einheiten, die bereits stattgefunden haben. Mir ist bewusst, dass mein Rücktrittsrecht erlischt, sobald alle gebuchten Einheiten stattgefunden haben (<a href="/terms?lang=de#withdrawal" target="_blank" rel="noopener">Rücktrittsrecht &amp; Bedingungen</a>).</span></label>
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
      <div class="ln" id="vcline" hidden><span id="vclabel">Gutschein</span><span id="vcamt"></span></div>
      <details class="vcbox" id="vcbox"><summary>Hast du einen Gutschein?</summary>
        <div class="vcrow"><input type="text" id="vccode" placeholder="MOVECIA-XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40" aria-label="Gutscheincode"><button type="button" class="btn line" id="vcgo">Einlösen</button></div>
        <p class="small" id="vcmsg" hidden></p></details>
      <button class="btn" type="submit" id="paybtn">Weiter zur Zahlung</button>
      <p class="err" id="err" hidden></p>
      <p class="small">Sichere Zahlung über Stripe (Karte, Apple Pay, Google Pay, EPS). Auf der Bezahlseite steht Movecia. Abgerechnet wird über das gemeinsame Konto von FORMA &amp; Movecia, deine Rechnung kommt im Movecia-Design per E-Mail.</p>
    </aside>
  </form>
</section>

<?php if (!$mix): ?>
<section class="w" id="newsletter" style="padding-top:0">
  <div class="panel nl">
    <div>
      <p class="k">Noch nicht so weit?</p>
      <h2 style="margin-top:.5rem;font-size:clamp(2rem,4vw,3rem)">Alle Infos <em>per E-Mail</em></h2>
      <p class="mut" style="margin-top:.8rem;max-width:32em">Trag dich ein, dann schicke ich dir alle Pakete, Preise und den Ablauf der Einheiten in Ruhe zum Nachlesen, dazu ab und zu einen Tipp für deine Schwangerschaft und die Zeit danach.</p>
    </div>
    <form id="nlform" class="nlf" novalidate>
      <label class="f">Vorname<input type="text" name="name" autocomplete="given-name" required maxlength="80" value="<?= $offer ? $h((string)$offer['name']) : ($bonus ? $h((string)$bonus['name']) : '') ?>"></label>
      <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required maxlength="160" value="<?= $bonus ? $h((string)$bonus['email']) : '' ?>"></label>
      <label class="chk"><input type="checkbox" name="consent" value="1" required><span>Ja, Vivien darf mir E-Mails zum Prä- und Postnatal-Training schicken. Ich kann mich jederzeit abmelden.</span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div><button class="btn" type="submit" id="nlbtn">Infos schicken</button></div>
      <p class="err" id="nlerr" hidden></p>
    </form>
    <p class="ok" id="nlok" hidden></p>
  </div>
</section>

<section class="w" id="wo" style="padding-top:0">
  <p class="k">Für wen &amp; wo</p>
  <h2 style="margin-top:.6rem">Im Paznaun oder <em>überall</em> online</h2>
  <div class="two">
    <div class="card"><h3>Im Studio in Mathon</h3><p style="margin-top:.6rem">Mein Studio liegt in Mathon im Paznaun, mitten in Tirol. Hierher kommen Schwangere und junge Mamas aus Ischgl, Galtür, Kappl, See und dem ganzen Paznaun, aber auch aus Landeck und Zams. Schwangerschaftstraining, Geburtsvorbereitung, Beckenboden- und Rückbildungstraining finden hier immer 1:1 statt, ohne Gruppe und ohne Vergleich.</p></div>
    <div class="card pl"><h3>Online, wo immer du bist</h3><p style="margin-top:.6rem">Du wohnst weiter weg, in Innsbruck, Vorarlberg, Deutschland oder der Schweiz, oder willst nach der Geburt einfach nicht aus dem Haus? Dann trainieren wir live per Video. Ich sehe dich, leite dich an und korrigiere dich wie im Studio. Dein Baby kann einfach neben dir liegen.</p></div>
  </div>
  <?php require_once dirname(__DIR__) . '/assets/raum.inc.php'; ?>
  <h3 style="margin-top:2.2rem">So sieht mein Studio aus</h3>
  <p class="mut" style="margin-top:.4rem;max-width:44em">Ein kleiner, heller Raum nur für dich und mich: Matten, Polster, Sprossenwand und alles, was wir für dein Training brauchen.</p>
  <?= mv_raum_gallery(false, 'raum-natal') ?>
</section>
<?php endif; ?>

<section class="w faq cute-faq" style="padding-top:0">
  <p class="k">Gut zu wissen</p>
  <h2 style="margin-top:.6rem;margin-bottom:1.6rem">Häufige <em>Fragen</em></h2>
<?php foreach (array_slice(natal_seo_faq(), 0, 3) as [$fq, $fa]): ?>  <details><summary><?= $h($fq) ?></summary><p><?= $h($fa) ?></p></details>
<?php endforeach; ?>  <details><summary>Ab wann kann ich nach der Geburt wieder trainieren?</summary><p>Sobald deine Ärztin dich freigibt, meistens nach 6 bis 8 Wochen, nach einem Kaiserschnitt oft etwas später. Bis dahin ruhen deine Einheiten einfach.</p></details>
  <details><summary>Was ist, wenn mein Baby früher kommt?</summary><p>Dann nutzt du die offenen Einheiten einfach nach der Geburt. Nichts verfällt, die Pakete gelten bis 6 Monate nach der Geburt.</p></details>
<div class="m-online">
  <details><summary>Wie läuft eine Online-Einheit ab?</summary><p>Wir treffen uns live per Video: Du tippst einfach auf den Link aus deiner Terminbestätigung. Ich sehe dich, leite dich an und korrigiere dich, genau wie im Studio.</p></details>
  <details><summary>Was brauche ich dafür?</summary><p>Eine Matte, etwas Platz zum Liegen und dein Handy, Tablet oder Laptop. Stell es am besten so auf, dass ich dich ganz sehe. Ein Kissen und ein Handtuch sind praktisch.</p></details>
  <details><summary>Darf mein Baby dabei sein?</summary><p>Ja, sehr gern. Nach der Geburt bleibt dein Baby einfach bei dir, wir passen die Einheit an euren Rhythmus an.</p></details>
</div><div class="m-studio">
  <details><summary>Darf mein Baby mitkommen?</summary><p>Ja, sehr gern. Nach der Geburt kommt dein Baby einfach mit, wir passen die Einheit an euren Rhythmus an.</p></details>
  <details><summary>Was soll ich mitbringen?</summary><p>Bequeme Kleidung, eine Flasche Wasser und gern ein Handtuch. Matten und alles andere habe ich im Studio.</p></details>
</div>
  <details><summary>Ich muss einen Termin verschieben. Was nun?</summary><p>Gib mir bitte so bald wie möglich Bescheid, am besten bis 24 Stunden vorher. Wenn das Baby oder die Gesundheit dazwischenkommt, finden wir immer eine Lösung.</p></details>
  <details><summary>Kann ich nach der Buchung zurücktreten?</summary><p>Ja, binnen 14 Tagen ohne Angabe von Gründen, ganz einfach über <a href="/withdraw?v=natal&amp;lang=de">„Vertrag widerrufen“</a>. Du bekommst dein Geld zurück, abgezogen werden nur Einheiten, die bereits stattgefunden haben. Alle Einzelheiten stehen im <a href="/terms?lang=de#withdrawal">Rücktrittsrecht</a>.</p></details>
</section>
</main>
<?php endif; ?>

<footer class="w">
  <p>Movecia – Personal Fitness · Vivien Malonga · <a href="mailto:<?= $h(NATAL_COMPANY['email']) ?>"><?= $h(NATAL_COMPANY['email']) ?></a></p>
  <p style="margin-top:6px"><a href="/terms?lang=de#natal">Bedingungen &amp; Rücktrittsrecht</a> · <a href="/withdraw?v=natal&amp;lang=de" style="font-weight:600">Vertrag widerrufen</a></p>
  <details><summary>Impressum &amp; Datenschutz</summary>
    <p style="margin-top:.6rem"><strong>Impressum:</strong> Vivien Malonga, Movecia – Personal Fitness. E-Mail: hello@moveciamovement.com. <a href="/terms?lang=de#impressum">Zum vollständigen Impressum</a>.</p>
    <p style="margin-top:.6rem"><strong>Datenschutz:</strong> Wenn du buchst, speichere ich deinen Namen, deine E-Mail-Adresse, deine Telefonnummer, deinen Geburtstermin, deine Nachricht und deine Wunschtermine, um deine Buchung abzuwickeln und die Termine mit dir zu planen. Deine Angaben im Gesundheitsfragebogen verwende ich nur mit deiner ausdrücklichen Einwilligung, um das Training sicher für dich zu gestalten. Bei Paketen bekommst du einen Mitgliederbereich (E-Mail und Passwort), mit Workbooks und bei Online-Einheiten Aufnahmen, auf denen nur ich zu sehen bin. Einen Monat nach deiner letzten Einheit werden Konto, Aufnahmen und Gesundheitsangaben automatisch gelöscht. Die Musik im Mitgliederbereich kommt von Spotify (Spotify AB, Schweden) und lädt erst, wenn du den Bereich öffnest. Die Zahlung läuft über Stripe (Stripe Payments Europe Ltd., Irland). Für die Buchhaltung bewahre ich die Rechnung so lange auf, wie es das Gesetz verlangt. Diese Seite verwendet keine Tracking-Cookies. Fragen oder Löschwünsche an hello@moveciamovement.com.</p>
  </details>
</footer>

<script><?= NATAL_HEALTH_JS ?></script>
<script>
(() => {
  const CAL = <?= json_encode($cal, JSON_UNESCAPED_UNICODE) ?>;
  const MODE = <?= json_encode($order ? 'order' : 'offer') ?>;
  const ORDER = <?= json_encode($order ? ['o' => (int)$order['id'], 'k' => $order['token'], 'left' => $left] : null) ?>;
  const OFFER = <?= json_encode($offer ? (string)$offer['token'] : '') ?>;
  const BONUS = <?= json_encode($bonus ? (string)$bonus['bonus_token'] : '') ?>;
  const euro = c => '€ ' + (c % 100 ? (c / 100).toFixed(2).replace('.', ',') : String(c / 100));
  const MON = ['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  const WD = ['So','Mo','Di','Mi','Do','Fr','Sa'];
  const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const nice = s => { const d = new Date(s + 'T12:00:00'); return WD[d.getDay()] + ', ' + d.getDate() + '. ' + MON[d.getMonth()]; };
  // Einheiten: Reiter Pränatal / Postnatal / Kombi, am Handy zugeklappt
  document.querySelectorAll('.etabs button').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('.etabs button').forEach(x => x.setAttribute('aria-selected', String(x === b)));
    document.querySelectorAll('.epanel').forEach(p => { p.hidden = p.dataset.panel !== b.dataset.tab; });
  }));
  if (innerWidth < 700) document.querySelectorAll('details.unit').forEach(d => { d.open = false; });

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
    // Mix & Match: wie viele Termine je Art noch frei sind (CAL.mix = {studio, online})
    const freeOf = (m, skip) => CAL.mix[m] - picked.filter((p, j) => j !== skip && p.mode === m).length;
    function toggle(s) {
      const i = picked.findIndex(p => p.date === s);
      if (i >= 0) picked.splice(i, 1);
      else {
        if (picked.length >= getMax()) { flash(getMax() === 1 ? 'Du kannst 1 Termin auswählen.' : 'Du kannst bis zu ' + getMax() + ' Termine auswählen.'); return; }
        const p = {date: s, daypart: 'vormittag', time: ''};
        if (CAL.mix) p.mode = freeOf('studio') > 0 ? 'studio' : 'online';
        picked.push(p); picked.sort((a, b) => a.date < b.date ? -1 : 1);
      }
      draw(); onChange && onChange(picked);
    }
    function drawList() {
      if (!picked.length) { list.innerHTML = '<p class="mut" style="font-size:.9rem;padding:.6rem 0">Noch keine Tage ausgewählt.</p>'; return; }
      list.innerHTML = picked.map((p, i) => '<div class="pk"><div class="row"><b>' + nice(p.date) + '</b><button type="button" class="x" data-i="' + i + '" aria-label="Entfernen">×</button></div>'
        + (CAL.mix ? '<div class="mm">' + [['studio', '🏡 im Studio'], ['online', '💻 online']].map(([k, l]) => '<button type="button" data-i="' + i + '" data-m="' + k + '" aria-pressed="' + (p.mode === k) + '"' + (p.mode !== k && freeOf(k, i) <= 0 ? ' disabled' : '') + '>' + l + '</button>').join('') + '</div>' : '')
        + '<div class="dp">' + Object.entries(CAL.dayparts).map(([k, l]) => '<button type="button" data-i="' + i + '" data-p="' + k + '" aria-pressed="' + (p.daypart === k) + '">' + l + '</button>').join('') + '</div>'
        + '<label class="wt">Wunschuhrzeit (optional) <input type="time" step="900" min="07:00" max="20:00" data-i="' + i + '" value="' + (p.time || '') + '"></label></div>').join('')
        + (CAL.mix ? '<p class="mixleft">Noch frei: ' + freeOf('studio') + ' im Studio · ' + freeOf('online') + ' online</p>' : '');
      list.querySelectorAll('.x').forEach(b => b.addEventListener('click', () => { picked.splice(+b.dataset.i, 1); draw(); onChange && onChange(picked); }));
      list.querySelectorAll('.dp button').forEach(b => b.addEventListener('click', () => { picked[+b.dataset.i].daypart = b.dataset.p; drawList(); }));
      list.querySelectorAll('.mm button').forEach(b => b.addEventListener('click', () => { picked[+b.dataset.i].mode = b.dataset.m; drawList(); onChange && onChange(picked); }));
      list.querySelectorAll('.wt input').forEach(t => t.addEventListener('input', () => {
        const p = picked[+t.dataset.i]; p.time = t.value;
        const hh = parseInt(t.value, 10);
        if (!isNaN(hh)) { p.daypart = hh < 12 ? 'vormittag' : (hh < 17 ? 'nachmittag' : 'abend'); t.closest('.pk').querySelectorAll('.dp button').forEach(x => x.setAttribute('aria-pressed', String(x.dataset.p === p.daypart))); }
      }));
    }
    function flash(t) { const p = document.createElement('p'); p.className = 'err'; p.textContent = t; list.prepend(p); setTimeout(() => p.remove(), 2600); }
    draw();
    return { get: () => picked.slice(), trim: () => {
      const m = getMax(); if (picked.length > m) picked = picked.slice(0, m);
      if (CAL.mix) { // Aufteilung geändert: Termine einer vollen Art auf die andere umstellen
        ['studio', 'online'].forEach(k => { let over = -freeOf(k); for (let j = picked.length - 1; j >= 0 && over > 0; j--) if (picked[j].mode === k) { picked[j].mode = k === 'studio' ? 'online' : 'studio'; over--; } });
      }
      draw(); onChange && onChange(picked);
    } };
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
      Object.assign(d, {action: 'call', date: pick, slot: slot, t: loadedK, a: OFFER, mode: document.body.dataset.mode || 'studio'});
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

  const nlf = document.getElementById('nlform');
  if (nlf) {
    const nlLoaded = Date.now();
    nlf.addEventListener('submit', async e => {
      e.preventDefault();
      const err = document.getElementById('nlerr'), btn = document.getElementById('nlbtn'); err.hidden = true;
      const d = Object.fromEntries(new FormData(nlf));
      if (!d.name || !d.email) { err.textContent = 'Bitte gib Vorname und E-Mail ein.'; err.hidden = false; return; }
      if (!d.consent) { err.textContent = 'Bitte setz das Häkchen, damit ich dir schreiben darf.'; err.hidden = false; return; }
      btn.disabled = true; btn.textContent = 'Einen Moment …';
      const j = await post(Object.assign(d, {action: 'subscribe', t: nlLoaded, mode: document.body.dataset.mode || 'studio', source: 'natal'}));
      if (j.ok) { nlf.hidden = true; const ok = document.getElementById('nlok'); ok.textContent = j.already ? 'Du bist schon eingetragen. Ich habe dir die Mail mit allen Paketen gerade noch einmal geschickt. 🤍' : 'Danke, ' + d.name + '! Schau jetzt in dein Postfach und bestätige deine Adresse mit einem Klick. Gleich danach bekommst du alle Infos. 🤍'; ok.hidden = false; return; }
      err.textContent = j.error || 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.'; err.hidden = false; btn.disabled = false; btn.textContent = 'Infos schicken';
    });
  }

  if (MODE === 'offer') {
    const loaded = Date.now();
    const f = document.getElementById('bookf'), err = document.getElementById('err'), btn = document.getElementById('paybtn');
    const HQ = HealthQ(f);
    const cur = () => document.querySelector('input[name=package]:checked');
    const cal = Calendar(document.getElementById('calbox'), () => +cur().dataset.units, sum);
    const due = document.getElementById('due');
    due.min = CAL.min.slice(0, 8) + '01';
    due.addEventListener('change', () => { CAL.due = due.value; });
    let VC = null; // eingelöster Movecia-Gutschein {code, nr, balance}
    function sum() {
      const p = cur(), w = cal.get().length, cents = +p.dataset.cents, vc = VC ? Math.min(VC.balance, cents) : 0, pay = cents - vc;
      document.getElementById('vcline').hidden = !vc;
      if (vc) { document.getElementById('vclabel').textContent = 'Gutschein ' + VC.nr; document.getElementById('vcamt').textContent = '− ' + euro(vc); }
      document.getElementById('sumtitle').textContent = p.dataset.title;
      document.getElementById('sumamt').textContent = euro(pay);
      document.getElementById('sumunits').textContent = p.dataset.units;
      document.getElementById('sumunit').textContent = p.dataset.unitlabel || euro(+p.dataset.unit);
      document.getElementById('sumwish').textContent = w ? w + (w === 1 ? ' Tag' : ' Tage') : 'später';
      btn.textContent = pay > 0 ? 'Weiter zur Zahlung · ' + euro(pay) : 'Mit Gutschein verbindlich buchen';
    }
    const vcm = document.getElementById('vcmsg'), vci = document.getElementById('vccode');
    const vcCheck = async () => {
      const code = vci.value.trim(); if (!code) return;
      vcm.hidden = false; vcm.textContent = 'Einen Moment …';
      const j = await post({action: 'voucher', code});
      if (j.ok) { VC = j; vcm.textContent = '✓ Gutschein ' + j.nr + ' mit ' + euro(j.balance) + ' wird abgezogen.'; }
      else { VC = null; vcm.textContent = j.error || 'Der Code hat nicht geklappt.'; }
      sum();
    };
    document.getElementById('vcgo').addEventListener('click', vcCheck);
    vci.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); vcCheck(); } });
    try { const pc = new URLSearchParams(location.search).get('gutschein'); if (pc) { vci.value = pc; document.getElementById('vcbox').open = true; vcCheck(); } } catch (e) {}
    document.querySelectorAll('input[name=package]').forEach(r => r.addEventListener('change', () => { cal.trim(); sum(); }));
    // Umschalter Studio / Online: gleiches Paket in der anderen Variante wählen, Preise sofort sichtbar
    function setMode(m) {
      if (document.body.dataset.mode === m) return;
      const base = cur().value.replace(/^o_/, '');
      document.body.dataset.mode = m;
      document.querySelectorAll('.modes button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.mode === m)));
      const next = document.querySelector('input[name=package][value="' + (m === 'online' ? 'o_' : '') + base + '"]');
      if (next) next.checked = true;
      document.querySelector('.tag-mode') && (document.querySelector('.tag-mode').textContent = m === 'online' ? 'online' : 'im Studio');
      try { const u = new URL(location.href); u.searchParams.set('m', m); history.replaceState(null, '', u); } catch (e) {}
      cal.trim(); sum();
    }
    document.querySelectorAll('.modes button').forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));
    // Mix & Match: sie wählt die Aufteilung, Preis rechnet live (verbindlich rechnet der Server)
    const rows = document.querySelectorAll('.mxr');
    if (rows.length && CAL.mix) {
      const tot = CAL.mix.studio + CAL.mix.online, radio = cur(), base = radio.dataset.title.split(' · ')[0];
      function mixDraw() {
        let cents = 0, parts = [], ul = [];
        rows.forEach(r => {
          const k = r.dataset.k, n = CAL.mix[k], u = +r.dataset.u; cents += n * u;
          r.querySelector('.mxn').textContent = n;
          r.querySelector('.mxsum').textContent = n ? n + ' × ' + euro(u) + ' = ' + euro(n * u) : euro(u) + ' pro Einheit · keine';
          r.querySelector('[data-d="-1"]').disabled = n <= 0; r.querySelector('[data-d="1"]').disabled = n >= tot;
          if (n) { parts.push(n + ' × ' + (k === 'online' ? 'online' : 'im Studio')); ul.push(euro(u) + (k === 'online' ? ' online' : ' Studio')); }
        });
        document.querySelector('.mixcard .tot b').textContent = euro(cents);
        radio.dataset.cents = cents; radio.dataset.title = base + ' · ' + parts.join(' + '); radio.dataset.unitlabel = ul.join(' · ');
        cal.trim(); sum();
      }
      rows.forEach(r => r.querySelectorAll('.stp button').forEach(b => b.addEventListener('click', () => {
        const k = r.dataset.k, o = k === 'studio' ? 'online' : 'studio', d = +b.dataset.d;
        if (CAL.mix[k] + d < 0 || CAL.mix[o] - d < 0) return;
        CAL.mix[k] += d; CAL.mix[o] -= d; mixDraw();
      })));
      mixDraw();
    }
    sum();
    f.addEventListener('submit', async e => {
      e.preventDefault(); err.hidden = true;
      const d = Object.fromEntries(new FormData(f));
      d.package = cur().value; d.action = 'buy'; d.t = loaded; d.a = OFFER; d.bonus = BONUS; if (VC) d.code = VC.code;
      if (CAL.mix) d.mix_online = CAL.mix.online;
      d.wishes = JSON.stringify(cal.get());
      if (!d.name || !d.email || !d.phone) { err.textContent = 'Bitte füll Name, E-Mail und Telefon aus.'; err.hidden = false; return; }
      const hqe = HQ ? HQ.check() : ''; if (hqe) { err.textContent = hqe; err.hidden = false; return; }
      HQ && HQ.collect(d);
      if (!d.start_now || !d.consent) { err.textContent = 'Bitte bestätige noch die Häkchen zum Start und zum Datenschutz.'; err.hidden = false; return; }
      btn.disabled = true; btn.textContent = 'Einen Moment …';
      const j = await post(d);
      if (j.ok && j.redirect) { location.href = j.redirect; return; }
      err.textContent = j.error || 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.'; err.hidden = false; btn.disabled = false; sum();
    });
  } else if (ORDER) {
    const hb = document.getElementById('hqbox');
    if (hb) { const HQ = HealthQ(hb), sb = document.getElementById('hqsave'), he = document.getElementById('hqerr');
      sb.addEventListener('click', async () => {
        he.hidden = true; const m = HQ.check(); if (m) { he.textContent = m; he.hidden = false; return; }
        const d = {action: 'health', o: ORDER.o, k: ORDER.k};
        hb.querySelectorAll('input,select,textarea').forEach(el => { if (!el.name || el.name === 'h_iss') return; if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return; d[el.name] = el.value; });
        HQ.collect(d); sb.disabled = true;
        const j = await post(d);
        if (j.ok) { location.reload(); return; }
        he.textContent = j.error || 'Das hat nicht geklappt.'; he.hidden = false; sb.disabled = false;
      });
    }
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
<?php if ($offer): // Willkommens-Karte beim Öffnen des persönlichen Angebots (Vivien, 30.10.2026: „zuerst eine süße Nachricht im Movecia-Stil“)
  $wName = trim(($offer['salutation'] ?: 'Liebe') . ' ' . $offer['name']);
  $wTxt = $offer['note'] !== '' ? $offer['note'] : 'Wie schön, dass du dich für mein Prä- & Postnatal Training interessierst. Ich habe dir hier alles zusammengestellt und freu mich so auf dich und dein Baby!';
  $wSub = $mix ? $mix['name'] . '-Paket · ' . (int)$mix['studio'] . ' im Studio · ' . (int)$mix['online'] . ' online' : 'Dein persönliches Angebot'; ?>
<style>
.wlc{position:fixed;inset:0;z-index:300;display:flex;align-items:center;justify-content:center;padding:18px;background:rgba(46,29,39,.42);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);opacity:0;transition:opacity .5s ease}
.wlc.on{opacity:1}.wlc[hidden]{display:none}
.wlc-c{position:relative;width:min(460px,100%);max-height:calc(100vh - 36px);overflow:auto;border-radius:34px;background:radial-gradient(120% 80% at 100% 0%,#F6E3D6 0%,rgba(246,227,214,0) 60%),radial-gradient(90% 70% at 0% 100%,#F1E3E2 0%,rgba(241,227,226,0) 60%),#FFFCF8;box-shadow:0 30px 70px -30px rgba(46,29,39,.6);text-align:center;padding:0 0 26px;transform:translateY(16px) scale(.98);transition:transform .6s cubic-bezier(.2,.8,.2,1)}
.wlc.on .wlc-c{transform:none}
.wlc-top{position:relative;width:150px;margin:24px auto 0}
.wlc-ph{width:150px;height:180px;border-radius:120px 120px 22px 22px;overflow:hidden;background:#E8D6CA}
.wlc-ph img{width:100%;height:100%;object-fit:cover;object-position:center 30%;display:block}
.wlc-mk{position:absolute;right:-22px;bottom:-14px;width:52px;height:52px;border-radius:50%;background:#FFFCF8;display:grid;place-items:center;box-shadow:0 8px 18px -10px rgba(46,29,39,.5)}
.wlc-mk img{width:34px;height:34px}
.wlc-h{font-family:"Cormorant Garamond","Cormorant",Georgia,serif;font-weight:500;font-style:italic;font-size:2.2rem;line-height:1.05;color:#2E1D27;margin:6px 22px 4px}
.wlc-s{font-size:.72rem;letter-spacing:.2em;text-transform:uppercase;color:#7D5E68;margin:0 22px 12px}
.wlc-t{margin:0 26px;color:#4A3A41;line-height:1.65;font-size:1rem}
.wlc-b{display:inline-flex;align-items:center;gap:10px;margin-top:20px;border:0;border-radius:999px;background:#4A3040;color:#FBF5F7;padding:13px 24px;font:inherit;font-size:1rem;cursor:pointer;box-shadow:0 14px 26px -16px rgba(74,48,64,.8)}
.wlc-b:hover{background:#7D5E68}
.wlc-x{position:absolute;right:14px;top:14px;width:38px;height:38px;border-radius:50%;border:0;background:rgba(255,255,255,.8);color:#4A3A41;font-size:18px;cursor:pointer;z-index:2}
.wlc-wave{display:block;width:100%;height:40px;margin-top:4px}
.wlc-sig{font-family:"Cormorant Garamond","Cormorant",Georgia,serif;font-style:italic;font-size:1.25rem;color:#7D5E68;margin:14px 0 0}
@media(prefers-reduced-motion:reduce){.wlc,.wlc-c{transition:none}}
</style>
<div class="wlc" id="wlc" hidden role="dialog" aria-modal="true" aria-labelledby="wlcH">
  <div class="wlc-c">
    <button type="button" class="wlc-x" data-wlcx aria-label="Schließen">✕</button>
    <div class="wlc-top"><div class="wlc-ph"><img src="/img/movecia/fotos/ev-natal.jpg" alt="" draggable="false"></div><span class="wlc-mk"><img src="/img/movecia/movecia-zeichen.svg" alt=""></span></div>
    <svg class="wlc-wave" viewBox="0 0 460 40" preserveAspectRatio="none" aria-hidden="true"><path d="M0 24 C80 4 150 38 240 22 S380 6 460 20" fill="none" stroke="#D9C3C6" stroke-width="1.4"/><path d="M0 32 C90 16 170 42 260 28 S390 14 460 28" fill="none" stroke="#E8D6CA" stroke-width="1.2"/></svg>
    <h2 class="wlc-h" id="wlcH"><?= $h($wName) ?> 🤍</h2>
    <p class="wlc-s"><?= $h($wSub) ?></p>
    <p class="wlc-t"><?= nl2br($h($wTxt)) ?></p>
    <button type="button" class="wlc-b" data-wlcgo>Mein Paket zusammenstellen <span aria-hidden="true">→</span></button>
    <p class="wlc-sig">Bis bald, Vivien</p>
  </div>
</div>
<script>
(function(){var w=document.getElementById('wlc');if(!w)return;var k='mv-wlc-<?= $h(substr((string)$offer['token'], 0, 12)) ?>';
  try{if(sessionStorage.getItem(k))return}catch(e){}
  function close(go){w.classList.remove('on');setTimeout(function(){w.hidden=true},450);try{sessionStorage.setItem(k,'1')}catch(e){}
    if(go){var t=document.querySelector('.mixcard')||document.getElementById('buchen');if(t)t.scrollIntoView({behavior:'smooth',block:'start'})}}
  setTimeout(function(){w.hidden=false;requestAnimationFrame(function(){w.classList.add('on')})},450);
  w.querySelector('[data-wlcgo]').onclick=function(){close(true)};w.querySelector('[data-wlcx]').onclick=function(){close(false)};
  w.addEventListener('click',function(e){if(e.target===w)close(false)});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!w.hidden)close(false)});
})();
</script>
<?php endif; ?>
</body>
</html>

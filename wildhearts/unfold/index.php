<?php
// moveciamovement.com/unfold – Unfold · The Mindful Becoming (Vivien, 24.10.2026)
// 3 Monate Begleitung ab 1. Februar 2027, live online, Deutsch und Englisch. Bis zur Buchung: Infoliste (Wild Hearts, source unfold).
declare(strict_types=1);
header('Cache-Control: no-store');
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : (($_GET['lang'] ?? '') === 'de' ? 'de' : '');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Unfold · The Mindful Becoming · Movecia</title>
<meta name="description" content="Unfold – The Mindful Becoming: 3 Monate Begleitung mit Vivien, live online, auf Deutsch und Englisch. Start am 1. Februar 2027.">
<meta property="og:title" content="Unfold · The Mindful Becoming">
<meta property="og:description" content="3 months to come back to yourself and begin. Live online with Vivien, in English and German. Starts 1 February 2027.">
<meta property="og:image" content="/img/wildhearts/og.jpg">
<meta name="theme-color" content="#F2ECE4">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
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
h1{font-size:clamp(3.2rem,9vw,7rem)}h2{font-size:clamp(2.2rem,5vw,3.6rem)}h3{font-size:1.6rem}
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
.pic img{width:100%;height:100%;object-fit:cover;display:block;filter:saturate(.92)}
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
input[type=text],input[type=email]{font:inherit;font-size:1rem;color:var(--ink);background:var(--bg);border:1px solid transparent;border-radius:14px;padding:.8em 1em;width:100%}
input:focus{outline:2px solid var(--plum);outline-offset:1px}
.chk{display:flex;gap:.6rem;align-items:flex-start;font-size:.88rem;color:var(--muted)}.chk input{margin-top:.3em;accent-color:var(--plum)}
.hp{position:absolute;left:-9999px}
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
</style>
</head>
<body>
<header class="w"><div class="in top">
  <a class="brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia<i></i></span></a>
  <div class="lang" role="group" aria-label="Sprache / Language"><button type="button" data-l="de">Deutsch</button><button type="button" data-l="en">English</button></div>
</div></header>

<main>
<section class="w"><div class="in hero">
  <div>
    <p class="k"><span class="de">3 Monate Begleitung · live online</span><span class="en">3 months of guidance · live online</span></p>
    <h1 style="margin-top:.8rem">Unfold</h1>
    <p class="sub">The Mindful Becoming</p>
    <p class="lead"><span class="de">Für dich, wenn du spürst, dass mehr in dir steckt, und einfach nicht weißt, wo du anfangen sollst. Drei Monate, in denen du zu dir zurückfindest, deinen Wert wieder spürst und Schritt für Schritt ins Tun kommst. In deinem Tempo und mit Frauen an deiner Seite, denen es genauso geht.</span><span class="en">For you, if you can feel there is more inside you and simply don't know where to begin. Three months to come back to yourself, feel your worth again and step by step start doing what matters to you. At your own pace, with women by your side who feel the same.</span></p>
    <div class="facts"><span><span class="de">Start 1. Februar 2027</span><span class="en">Starts 1 February 2027</span></span><span><span class="de">3 Monate</span><span class="en">3 months</span></span><span><span class="de">wöchentlicher Live-Call</span><span class="en">weekly live call</span></span><span><span class="de">max. 10 Plätze</span><span class="en">max. 10 spots</span></span><span>Deutsch &amp; English</span></div>
    <div class="cta"><a class="btn" href="#infos"><span class="de">Platz vormerken</span><span class="en">Save my spot</span> →</a><a class="btn line" href="#inhalt"><span class="de">Was dich erwartet</span><span class="en">What's inside</span></a></div>
  </div>
  <div style="position:relative">
    <span class="blob" style="width:200px;height:190px;background:var(--blush);left:-50px;top:-30px"></span>
    <div class="pic"><img src="/img/wildhearts/cover.jpg" alt="Vivien sitzt ruhig am Fenster">
      <p class="stamp"><small><span class="de">Nur zweimal im Jahr</span><span class="en">Only twice a year</span></small><span class="de">Februar bis April · höchstens 10 Plätze</span><span class="en">February to April · max. 10 spots</span></p></div>
  </div>
</div></section>

<div class="mq" aria-hidden="true"><div class="mq-in"><?php for ($i = 0; $i < 2; $i++) foreach (['ankommen · arrive', 'aufräumen · clear', 'spüren · feel', 'wachsen · grow', 'entfalten · unfold', 'beginnen · begin'] as $wd) echo '<span>' . $wd . '</span>'; ?></div></div>

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
  <p class="k"><span class="de">Drei Monate, drei Kapitel</span><span class="en">Three months, three chapters</span></p>
  <h2 style="margin-top:.6rem"><span class="de">So <em>entfaltest</em> du dich</span><span class="en">How you <em>unfold</em></span></h2>
  <div class="months">
    <div class="mo"><span class="n">1</span><p class="k"><span class="de">Februar · Wurzeln</span><span class="en">February · Roots</span></p><h3><span class="de">Ankommen &amp; Raum schaffen</span><span class="en">Arrive &amp; make space</span></h3><p><span class="de">Wir schauen, was dich müde macht, räumen auf, was nicht mehr zu dir passt, und finden einen Rhythmus, der dich trägt.</span><span class="en">We look at what drains you, clear out what no longer fits and find a rhythm that carries you.</span></p></div>
    <div class="mo"><span class="n">2</span><p class="k"><span class="de">März · Knospen</span><span class="en">March · Buds</span></p><h3><span class="de">Selbstwert &amp; Ideen</span><span class="en">Self-worth &amp; ideas</span></h3><p><span class="de">Du entdeckst, was dich wirklich ausmacht, lässt deine Ideen sprudeln und findest die eine, die jetzt dran ist.</span><span class="en">You discover what truly makes you you, let your ideas flow and find the one that wants to come first.</span></p></div>
    <div class="mo dark"><span class="n">3</span><p class="k"><span class="de">April · Entfalten</span><span class="en">April · Unfold</span></p><h3><span class="de">Ins Tun kommen</span><span class="en">Begin</span></h3><p><span class="de">Kleine, mutige Schritte. Du setzt deine Idee in Bewegung und gehst mit einem Plan und viel Vertrauen in den Frühling.</span><span class="en">Small, brave steps. You set your idea in motion and walk into spring with a plan and a lot of trust.</span></p></div>
  </div>

  <h2 style="margin-top:clamp(3rem,6vw,5rem)"><span class="de">Das ist <em>dabei</em></span><span class="en">What's <em>included</em></span></h2>
  <div class="grid">
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><circle cx="17" cy="18" r="6"/><circle cx="31" cy="18" r="6"/><path d="M6 38c1.5-7 6-10 11-10s9.5 3 11 10M24 30c2-1.4 4.4-2 7-2 5 0 9.5 3 11 10"/></svg><b><span class="de">Wöchentlicher Live-Call</span><span class="en">Weekly live call</span></b><p><span class="de">Jede Woche treffen wir uns live in einer kleinen Runde, es gibt höchstens 10 Plätze. Es gibt immer einen Termin auf Deutsch und einen auf Englisch, du wählst einfach.</span><span class="en">Every week we meet live in a small circle, with no more than 10 spots. There is always one call in English and one in German, you simply choose.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><rect x="9" y="7" width="30" height="34" rx="5"/><path d="M16 16h16M16 23h16M16 30h10"/></svg><b><span class="de">Monatliches Workbook</span><span class="en">Monthly workbook</span></b><p><span class="de">Zum Ausdrucken oder am iPad: Übungen zu Mindset, Selbstwert und deinen Ideen, mit Journaling-Fragen für jede Woche.</span><span class="en">Print it or use it on your tablet: exercises for mindset, self-worth and your ideas, with journaling prompts for every week.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><circle cx="24" cy="24" r="16"/><path d="M20 17l11 7-11 7z"/></svg><b><span class="de">Impulse von mir</span><span class="en">Talks from me</span></b><p><span class="de">Kurze Videos, in denen ich dir erzähle, was mir selbst geholfen hat, als ich mein Atelier und meine Arbeit mit Frauen aufgebaut habe.</span><span class="en">Short videos in which I share what helped me when I built my studio and my work with women.</span></p></div>
    <div class="it"><svg viewBox="0 0 48 48" fill="none" stroke="#7D5E68" stroke-width="1.6" stroke-linecap="round"><path d="M10 34c6-2 8-10 14-10s8 8 14 10"/><circle cx="24" cy="14" r="4"/><path d="M8 40h32"/></svg><b><span class="de">Die Mindful Movement Method</span><span class="en">The Mindful Movement Method</span></b><p><span class="de">Drei Monate voller Zugang zu meiner On-Demand-Bibliothek: sanfte Flows, Breathwork, Somatic Movement und Meditationen.</span><span class="en">Three months of full access to my on-demand library: gentle flows, breathwork, somatic movement and meditations.</span></p></div>
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
      <p class="note"><span class="de">Dabei sind die wöchentlichen Live-Calls, alle Workbooks, alle Aufnahmen und drei Monate Mindful Movement Method. Unfold startet nur zweimal im Jahr und es gibt höchstens 10 Plätze, damit jede Frau wirklich gesehen wird.</span><span class="en">Included are the weekly live calls, all workbooks, all recordings and three months of the Mindful Movement Method. Unfold only starts twice a year and there are no more than 10 spots, so every woman is truly seen.</span></p>
      <p class="gp"><b><span class="de">Info-Gathering · 30 Minuten · kostenlos</span><span class="en">Info Gathering · 30 minutes · free</span></b><span class="de">Vor dem Start erzähle ich dir live alles über Unfold und beantworte deine Fragen. Nur dort gibt es Unfold zum Gathering-Preis von € 390.</span><span class="en">Before the start I'll tell you everything about Unfold live and answer your questions. Only there you get Unfold for the Gathering price of € 390.</span></p>
    </div>
    <div style="position:relative">
      <p class="amt">€ 450</p>
      <div class="row" style="margin-top:.8rem"><span><span class="de">einmalig</span><span class="en">one payment</span></span><span>€ 450</span></div>
      <div class="row"><span><span class="de">oder in 3 Raten</span><span class="en">or 3 instalments</span></span><span>3 × € 160</span></div>
      <div class="row"><span><span class="de">Start</span><span class="en">Start</span></span><span><span class="de">1. Februar 2027</span><span class="en">1 February 2027</span></span></div>
    </div>
  </div>
</div></section>

<section class="w s" id="infos"><div class="in">
  <div class="join">
    <div>
      <p class="k"><span class="de">Platz vormerken</span><span class="en">Save my spot</span></p>
      <h2 style="margin-top:.6rem"><span class="de">Sei <em>als Erste</em> dabei</span><span class="en">Be the <em>first</em> to know</span></h2>
      <p class="mut" style="margin-top:.8rem"><span class="de">Trag dich ein und bestätige deine E-Mail-Adresse. Dann schicke ich dir alle Infos und den Termin für das Info-Gathering, noch bevor ich Unfold offiziell poste. Buchen kannst du noch nicht, die 10 Plätze öffnen vor dem Start.</span><span class="en">Leave your details and confirm your email. I'll then send you all the details and the date of the Info Gathering, before I officially share Unfold. You can't book yet, the 10 spots open before the start.</span></p>
    </div>
    <form id="jf" novalidate>
      <label class="f"><span class="de">Vorname</span><span class="en">First name</span><input type="text" name="name" autocomplete="given-name" required maxlength="80"></label>
      <label class="f">E-Mail<input type="email" name="email" autocomplete="email" required maxlength="160"></label>
      <label class="chk"><input type="checkbox" name="consent" value="1" required><span><span class="de">Ja, Vivien darf mir zu Unfold und dem Wild Hearts Collective schreiben. Ich kann mich jederzeit abmelden.</span><span class="en">Yes, Vivien may email me about Unfold and the Wild Hearts Collective. I can unsubscribe at any time.</span></span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div><button class="btn" type="submit" id="jb"><span class="de">Platz vormerken</span><span class="en">Save my spot</span></button></div>
      <p class="err" id="je" hidden></p>
      <p class="ok" id="jo" hidden><span class="de">Fast geschafft! Schau in dein Postfach und bestätige deine E-Mail-Adresse. 🤍</span><span class="en">Almost there! Check your inbox and confirm your email address. 🤍</span></p>
    </form>
  </div>
  <?php if (isset($_GET['ok'])): ?><p class="ok" style="margin-top:1rem"><span class="de">Danke, du bist auf der Liste! Ich melde mich, sobald die Plätze öffnen.</span><span class="en">Thank you, you are on the list! I'll be in touch as soon as spots open.</span></p><?php endif; ?>
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
  <details class="q"><summary><span class="de">Wie viel Zeit brauche ich pro Woche?</span><span class="en">How much time do I need per week?</span></summary><p><span class="de">Rechne mit etwa zwei Stunden: der Live-Call, ein Impuls-Video und ein wenig Zeit mit deinem Workbook. Die Bewegungseinheiten machst du, wann es dir passt.</span><span class="en">Plan for about two hours: the live call, a short talk video and some time with your workbook. You do the movement sessions whenever it suits you.</span></p></details>
  <details class="q"><summary><span class="de">Was ist, wenn ich einen Call verpasse?</span><span class="en">What if I miss a call?</span></summary><p><span class="de">Jeder Call wird aufgenommen und steht danach in deinem Mitgliederbereich. Du kannst auch einmal in die andere Sprachgruppe wechseln.</span><span class="en">Every call is recorded and waits for you in your members area. You can also join the other language group now and then.</span></p></details>
  <details class="q"><summary><span class="de">Muss ich sportlich sein?</span><span class="en">Do I need to be fit?</span></summary><p><span class="de">Überhaupt nicht. Die Bewegung ist sanft und achtsam, ganz ohne Leistungsdruck. Du machst nur, was sich für dich gut anfühlt.</span><span class="en">Not at all. The movement is gentle and mindful, without any pressure to perform. You only do what feels good to you.</span></p></details>
  <details class="q"><summary><span class="de">Wann kann ich buchen?</span><span class="en">When can I book?</span></summary><p><span class="de">Die Buchung öffnet vor dem Start. Wer sich hier einträgt, erfährt es zuerst. Im kostenlosen 30-minütigen Info-Gathering kostet Unfold € 390 statt € 450.</span><span class="en">Booking opens before the start. If you join the list you'll hear first. In the free 30-minute Info Gathering, Unfold is € 390 instead of € 450.</span></p></details>
  <details class="q"><summary><span class="de">Kann ich in Raten zahlen?</span><span class="en">Can I pay in instalments?</span></summary><p><span class="de">Ja. Du zahlst entweder einmal € 450 oder dreimal € 160, jeweils monatlich.</span><span class="en">Yes. You pay either € 450 at once or three monthly instalments of € 160.</span></p></details>
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

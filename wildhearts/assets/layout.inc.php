<?php
// Gemeinsamer Rahmen für die öffentlichen Movecia-Seiten (Hauptseite, Method): Kopf mit Menü, DE/EN, Fuß.
// Texte zweisprachig als <span class="de">/<span class="en">, Sprache aus ?lang=, sonst gemerkt (localStorage wh-lang), sonst Browser.

function mvl_head(string $title, string $desc, string $canon, string $og = '/img/movecia/og-start.jpg', string $extraCss = '', array $jsonld = []): void
{
    $l = ($_GET['lang'] ?? '') === 'en' ? 'en' : (($_GET['lang'] ?? '') === 'de' ? 'de' : '');
    ?><!doctype html>
<html lang="de" data-l="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= forma_h($title) ?></title>
<meta name="description" content="<?= forma_h($desc) ?>">
<link rel="canonical" href="<?= forma_h($canon) ?>">
<meta property="og:type" content="website"><meta property="og:url" content="<?= forma_h($canon) ?>"><meta property="og:site_name" content="Movecia"><meta property="og:locale" content="de_AT"><meta property="og:locale:alternate" content="en_GB">
<meta property="og:title" content="<?= forma_h($title) ?>"><meta property="og:description" content="<?= forma_h($desc) ?>"><meta property="og:image" content="<?= forma_h(mm_site() . $og) ?>"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta name="twitter:card" content="summary_large_image">
<meta name="robots" content="index, follow, max-image-preview:large">
<?php foreach ($jsonld as $j): ?><script type="application/ld+json"><?= json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>
<meta name="theme-color" content="#F2ECE4">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="manifest" href="/app.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Movecia"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="/wildhearts/assets/app.js?v=2" defer></script>
<link rel="stylesheet" href="/wildhearts/assets/mv.css?v=2">
<link rel="stylesheet" href="/wildhearts/assets/site.css?v=5">
<?= $extraCss ?>
<script>(function(){var l=<?= json_encode($l) ?>;try{if(!l)l=localStorage.getItem('wh-lang')||''}catch(e){}if(!l)l=(navigator.language||'').toLowerCase().indexOf('en')===0?'en':'de';document.documentElement.dataset.l=l;document.documentElement.lang=l;})();</script>
</head>
<?php
}

function mvl_top(string $active = ''): void
{
    require_once __DIR__ . '/menu.inc.php';
    ?>
<header class="w site-top"><div class="in top">
  <a class="brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia<i></i></span></a>
  <div class="top-r">
    <?php mv_menu($active === '' ? 'start' : $active); ?>
    <div class="lang" role="group" aria-label="Sprache / Language"><button type="button" data-set="de">DE</button><button type="button" data-set="en">EN</button></div>
  </div>
</div></header>
<?php
}

function mvl_foot(): void
{
    ?>
<footer class="f w"><div class="in">
  <span>Movecia · Vivien Malonga · <span class="de">Mindful Movement aus den Tiroler Bergen</span><span class="en">Mindful movement from the Tyrolean mountains</span></span>
  <span><a href="/mitglieder/"><span class="de">Mitgliederbereich</span><span class="en">Members area</span></a> · <a href="/terms/"><span class="de">Impressum &amp; Bedingungen</span><span class="en">Imprint &amp; terms</span></a> · <a href="/terms/#datenschutz"><span class="de">Datenschutz</span><span class="en">Privacy</span></a> · <a href="/withdraw/"><span class="de">Vertrag widerrufen</span><span class="en">Withdraw</span></a> · <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a></span>
</div></footer>
<script>
(function(){
  var root=document.documentElement;
  function set(v){root.dataset.l=v;root.lang=v;document.querySelectorAll('.lang [data-set]').forEach(function(b){b.setAttribute('aria-pressed',String(b.dataset.set===v))});try{localStorage.setItem('wh-lang',v)}catch(e){}document.dispatchEvent(new CustomEvent('mvlang',{detail:v}));}
  set(root.dataset.l);
  document.querySelectorAll('.lang [data-set]').forEach(function(b){b.addEventListener('click',function(){set(b.dataset.set)})});
  // sanftes Einblenden beim Scrollen
  if('IntersectionObserver' in window&&!matchMedia('(prefers-reduced-motion: reduce)').matches){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in-view');io.unobserve(e.target)}})},{rootMargin:'0px 0px -8% 0px'});document.querySelectorAll('.rv').forEach(function(x){io.observe(x)});}else document.querySelectorAll('.rv').forEach(function(x){x.classList.add('in-view')});
})();
</script>
<?php
}

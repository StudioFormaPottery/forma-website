<?php
// Gemeinsamer Rahmen für die öffentlichen Movecia-Seiten (Hauptseite, Method): Kopf mit Menü, DE/EN, Fuß.
// Texte zweisprachig als <span class="de">/<span class="en">, Sprache aus ?lang=, sonst gemerkt (localStorage wh-lang), sonst Browser.

function mvl_head(string $title, string $desc, string $canon, string $og = '/img/movecia/natal-teilen.jpg', string $extraCss = ''): void
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
<meta property="og:title" content="<?= forma_h($title) ?>"><meta property="og:description" content="<?= forma_h($desc) ?>"><meta property="og:image" content="<?= forma_h($og) ?>">
<meta name="theme-color" content="#F2ECE4">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="stylesheet" href="/wildhearts/assets/mv.css?v=2">
<link rel="stylesheet" href="/wildhearts/assets/site.css?v=1">
<?= $extraCss ?>
<script>(function(){var l=<?= json_encode($l) ?>;try{if(!l)l=localStorage.getItem('wh-lang')||''}catch(e){}if(!l)l=(navigator.language||'').toLowerCase().indexOf('en')===0?'en':'de';document.documentElement.dataset.l=l;document.documentElement.lang=l;})();</script>
</head>
<?php
}

function mvl_top(string $active = ''): void
{
    $nav = [['/method/', 'Method', 'Method', 'method'], ['/unfold/', 'Unfold', 'Unfold', 'unfold'], ['/natal/', 'Prä- &amp; Postnatal', 'Pre- &amp; postnatal', 'natal'], ['/gathering/', 'Gatherings', 'Gatherings', 'gathering'], ['/#vivien', 'Über mich', 'About me', 'about']];
    ?>
<header class="w site-top"><div class="in top">
  <a class="brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia<i></i></span></a>
  <nav class="nav" id="nav" aria-label="Menü">
    <?php foreach ($nav as [$href, $de, $en, $k]): ?><a class="l<?= $k === $active ? ' on' : '' ?>" href="<?= $href ?>"><span class="de"><?= $de ?></span><span class="en"><?= $en ?></span></a><?php endforeach; ?>
    <a class="l login" href="/mitglieder/"><span class="de">Login</span><span class="en">Log in</span></a>
    <div class="lang" role="group" aria-label="Sprache / Language"><button type="button" data-set="de">DE</button><button type="button" data-set="en">EN</button></div>
    <button class="menu-t" type="button" aria-label="Menü" aria-controls="nav" aria-expanded="false"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 6h14M3 10h14M3 14h14"/></svg></button>
  </nav>
  <button class="menu-t outer" type="button" aria-label="Menü" aria-controls="nav" aria-expanded="false"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 6h14M3 10h14M3 14h14"/></svg></button>
</div></header>
<?php
}

function mvl_foot(): void
{
    ?>
<footer class="f w"><div class="in">
  <span>Movecia · Vivien Malonga · <span class="de">Mindful Movement aus den Tiroler Bergen</span><span class="en">Mindful movement from the Tyrolean mountains</span></span>
  <span><a href="/mitglieder/"><span class="de">Mitgliederbereich</span><span class="en">Members area</span></a> · <a href="/terms/"><span class="de">Impressum &amp; Bedingungen</span><span class="en">Imprint &amp; terms</span></a> · <a href="/withdraw/"><span class="de">Vertrag widerrufen</span><span class="en">Withdraw</span></a> · <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a></span>
</div></footer>
<script>
(function(){
  var root=document.documentElement;
  function set(v){root.dataset.l=v;root.lang=v;document.querySelectorAll('.lang [data-set]').forEach(function(b){b.setAttribute('aria-pressed',String(b.dataset.set===v))});try{localStorage.setItem('wh-lang',v)}catch(e){}document.dispatchEvent(new CustomEvent('mvlang',{detail:v}));}
  set(root.dataset.l);
  document.querySelectorAll('.lang [data-set]').forEach(function(b){b.addEventListener('click',function(){set(b.dataset.set)})});
  var nav=document.getElementById('nav');
  document.querySelectorAll('.menu-t').forEach(function(t){t.addEventListener('click',function(){var o=!nav.classList.contains('open');nav.classList.toggle('open',o);document.body.style.overflow=o?'hidden':'';});});
  nav.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){nav.classList.remove('open');document.body.style.overflow='';})});
  // sanftes Einblenden beim Scrollen
  if('IntersectionObserver' in window&&!matchMedia('(prefers-reduced-motion: reduce)').matches){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in-view');io.unobserve(e.target)}})},{rootMargin:'0px 0px -8% 0px'});document.querySelectorAll('.rv').forEach(function(x){io.observe(x)});}else document.querySelectorAll('.rv').forEach(function(x){x.classList.add('in-view')});
})();
</script>
<?php
}

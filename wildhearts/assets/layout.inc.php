<?php
// Gemeinsamer Rahmen für die öffentlichen Movecia-Seiten (Hauptseite, Method): Kopf mit Menü, DE/EN, Fuß.
// Texte zweisprachig als <span class="de">/<span class="en">, Sprache aus ?lang=, sonst gemerkt (localStorage wh-lang), sonst Browser.

require_once __DIR__ . '/waves.inc.php';

function mvl_head(string $title, string $desc, string $canon, string $og = '/img/movecia/og-start.jpg', string $extraCss = '', array $jsonld = []): void
{
    $l = ($_GET['lang'] ?? '') === 'en' ? 'en' : (($_GET['lang'] ?? '') === 'de' ? 'de' : '');
    $deOnly = !empty($GLOBALS['mvl_de_only']); // Seiten nur auf Deutsch (Academy, Vivien 28.10.2026)
    ?><!doctype html>
<html lang="de" data-l="de"<?= $deOnly ? ' class="de-only"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= forma_h($title) ?></title>
<meta name="description" content="<?= forma_h($desc) ?>">
<link rel="canonical" href="<?= forma_h($canon) ?>">
<?php if ($deOnly && empty($GLOBALS['mvl_noindex'])): foreach (['de', 'de-AT', 'de-DE', 'de-CH', 'x-default'] as $hl): ?><link rel="alternate" hreflang="<?= $hl ?>" href="<?= forma_h($canon) ?>">
<?php endforeach; endif; ?>
<meta property="og:type" content="website"><meta property="og:url" content="<?= forma_h($canon) ?>"><meta property="og:site_name" content="Movecia"><meta property="og:locale" content="de_AT"><meta property="og:locale:alternate" content="en_GB">
<meta property="og:title" content="<?= forma_h($title) ?>"><meta property="og:description" content="<?= forma_h($desc) ?>"><meta property="og:image" content="<?= forma_h(mm_site() . $og) ?>"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta name="twitter:card" content="summary_large_image">
<meta name="robots" content="<?= empty($GLOBALS['mvl_noindex']) ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' ?>">
<?php foreach ($jsonld as $j): ?><script type="application/ld+json"><?= json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>
<meta name="theme-color" content="#F2ECE4">
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="apple-touch-icon" href="/img/movecia/movecia-app-180.png">
<link rel="manifest" href="/app.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Movecia"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="/wildhearts/assets/app.js?v=2" defer></script>
<link rel="stylesheet" href="/wildhearts/assets/mv.css?v=2">
<link rel="stylesheet" href="/wildhearts/assets/site.css?v=17">
<link rel="stylesheet" href="/wildhearts/assets/cute.css?v=4">
<?= $extraCss ?>
<?php if ($deOnly): ?><script>(function(){var l=<?= json_encode($l) ?>;try{if(!l)l=localStorage.getItem('wh-lang')||''}catch(e){}if(!l)l=(navigator.language||'').toLowerCase().indexOf('en')===0?'en':'de';if(l==='en')document.documentElement.classList.add('want-en');})();</script>
<style>.en-note{display:none}html.want-en .en-note{display:block;background:#4A3A41;color:#FBF5F7;text-align:center;font-size:.92rem;padding:.55rem 16px;margin:0}html.de-only .site-top .lang{display:none!important}</style>
<?php else: ?><script>(function(){var l=<?= json_encode($l) ?>;try{if(!l)l=localStorage.getItem('wh-lang')||''}catch(e){}if(!l)l=(navigator.language||'').toLowerCase().indexOf('en')===0?'en':'de';document.documentElement.dataset.l=l;document.documentElement.lang=l;})();</script>
<?php endif; ?>
<script src="/wildhearts/assets/schutz.js?v=3"></script>
</head>
<?php
}

function mvl_top(string $active = ''): void
{
    require_once __DIR__ . '/menu.inc.php';
    ?>
<header class="w site-top"><div class="in top">
  <a class="brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia</span></a>
  <div class="top-r">
    <?php mv_menu($active === '' ? 'start' : $active, empty($GLOBALS['mvl_de_only']) ? [] : ['lang' => false]); ?>
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
  <span><a href="/mitglieder/"><span class="de">Mitgliederbereich</span><span class="en">Members area</span></a> · <a href="/terms/#impressum"><span class="de">Impressum</span><span class="en">Legal notice</span></a> · <a href="/terms/"><span class="de">Bedingungen</span><span class="en">Terms</span></a> · <a href="/terms/#datenschutz"><span class="de">Datenschutz</span><span class="en">Privacy</span></a> · <a href="/withdraw/"><span class="de">Vertrag widerrufen</span><span class="en">Withdraw from contract</span></a> · <a href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a></span>
</div></footer>
<script>
(function(){
  var root=document.documentElement;
  if(root.classList.contains('de-only')){window.mvDeOnly=1}
  function set(v){if(window.mvDeOnly){v='de';root.dataset.l='de';root.lang='de';return}root.dataset.l=v;root.lang=v;document.querySelectorAll('.lang [data-set]').forEach(function(b){b.setAttribute('aria-pressed',String(b.dataset.set===v))});try{localStorage.setItem('wh-lang',v)}catch(e){}document.dispatchEvent(new CustomEvent('mvlang',{detail:v}));}
  set(root.dataset.l);
  document.querySelectorAll('.lang [data-set]').forEach(function(b){b.addEventListener('click',function(){set(b.dataset.set)})});
  // sanftes Einblenden beim Scrollen
  /* Hintergrund-Videos laufen immer in Schleife weiter, auch wenn iPhone/Safari sie nach dem ersten Durchlauf anhält (Vivien, 26.10.2026) */
  document.querySelectorAll('video[autoplay][loop]').forEach(function(v){var go=function(){var p=v.play();if(p&&p.catch)p.catch(function(){})};v.addEventListener('ended',function(){v.currentTime=0;go()});v.addEventListener('pause',function(){if(!document.hidden)setTimeout(function(){if(v.paused&&!document.hidden)go()},400)});if('IntersectionObserver' in window){new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting&&v.paused)go()})}).observe(v)}document.addEventListener('visibilitychange',function(){if(!document.hidden&&v.paused)go()})});
  if('IntersectionObserver' in window&&!matchMedia('(prefers-reduced-motion: reduce)').matches){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in-view');io.unobserve(e.target)}})},{rootMargin:'0px 0px -8% 0px'});document.querySelectorAll('.rv').forEach(function(x){io.observe(x)});}else document.querySelectorAll('.rv').forEach(function(x){x.classList.add('in-view')});
})();
</script>
<?php
}

/** Entfernt alle Elemente mit Klasse „en“ aus fertigem HTML (Seiten nur auf Deutsch, z. B. Academy; per ob_start('mv_strip_en')). */
function mv_strip_en(string $html): string
{
    $out = ''; $pos = 0;
    while (preg_match('/<([a-z][a-z0-9]*)\b[^>]*\bclass="(?:[^"]*\s)?en(?:\s[^"]*)?"[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $start = $m[0][1]; $tag = strtolower($m[1][0]);
        $out .= substr($html, $pos, $start - $pos);
        $i = $start + strlen($m[0][0]); $depth = 1;
        while ($depth > 0 && preg_match('#<(/?)' . $tag . '\b[^>]*>#i', $html, $t, PREG_OFFSET_CAPTURE, $i)) {
            $depth += $t[1][0] === '/' ? -1 : 1;
            $i = $t[0][1] + strlen($t[0][0]);
        }
        if ($depth > 0) { $out .= substr($html, $start); return $out; } // unvollständig: nichts mehr anfassen
        $pos = $i;
    }
    return $out . substr($html, $pos);
}

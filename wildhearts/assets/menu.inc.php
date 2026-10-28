<?php
// Gemeinsames Movecia-Menü für alle öffentlichen Seiten (Vivien, 25.10.2026):
// am Desktop eine Linkleiste, am Handy ein Menü-Knopf mit deckender Seitenleiste.
// Die Seitenleiste wird per Skript direkt an <body> gehängt, damit sie nie in einem Kopf mit
// Unschärfe-Effekt (backdrop-filter) gefangen ist – genau das machte sie vorher durchsichtig.
//
// mv_menu('unfold')                     Linkleiste (ab 900 px) + Menü-Knopf
// mv_menu('natal', ['lang' => false])   ohne DE/EN-Umschalter (Seite nur auf Deutsch)
// mv_menu('', ['links' => false])       nur der Knopf (wenn die Seite schon eine eigene Leiste hat)

function mv_menu_items(): array
{
    $u = '';
    if (function_exists('mv_cfg') && function_exists('mv_ts')) {
        $t = mv_ts(mv_cfg()['u_start']);
        $m = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $me = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        if ($t > time()) $u = [' · Start ' . date('j', $t) . '. ' . $m[(int)date('n', $t)], ' · starts ' . date('j', $t) . ' ' . $me[(int)date('n', $t)]];
    }
    return [
        ['/', 'Start', 'Home', 'start', 'Alles über Movecia', 'All about Movecia'],
        ['/method/', 'Method', 'Method', 'method', 'Mindful Movement on demand', 'Mindful movement on demand'],
        ['/unfold/', 'Unfold', 'Unfold', 'unfold', '3 Monate Begleitung' . ($u[0] ?? ''), '3 months of guidance' . ($u[1] ?? '')],
        ['/natal/', 'Prä- &amp; Postnatal', 'Pre- &amp; postnatal', 'natal', '1:1 im Studio &amp; online', '1:1 in the studio &amp; online'],
        ['/teacher-training/', 'Teacher Training', 'Teacher Training', 'teacher', 'Ab November 2027 · Warteliste', 'From November 2027 · waiting list'],
        ['/gathering/', 'Gatherings', 'Gatherings', 'gathering', 'Live und kostenlos, einmal im Monat', 'Free and live, once a month'],
        ['/#vivien', 'Über mich', 'About me', 'about', 'Ich bin Vivien', "Hi, I'm Vivien"],
    ];
}

function mv_menu(string $active = '', array $o = []): void
{
    static $assets = false;
    $lang = $o['lang'] ?? true;
    $links = $o['links'] ?? true;
    $items = mv_menu_items();
    if ($links): ?>
<nav class="mvm-bar" aria-label="Menü"><?php foreach ($items as [$href, $de, $en, $k]) if ($k !== 'start'): ?><a href="<?= $href ?>"<?= $k === $active ? ' class="on" aria-current="page"' : '' ?>><span class="de"><?= $de ?></span><span class="en"><?= $en ?></span></a><?php endif; ?><a class="lg" href="/mitglieder/"><span class="de">Login</span><span class="en">Log in</span></a></nav>
<?php endif; ?>
<button class="mvm-btn" type="button" aria-label="Menü öffnen" aria-expanded="false" aria-controls="mvm-panel"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M3 6h14M3 10h14M3 14h14"/></svg></button>
<?php if ($assets) return; $assets = true; ?>
<div class="mvm" id="mvm-panel" role="dialog" aria-modal="true" aria-label="Menü" hidden>
  <div class="mvm-top">
    <a class="mvm-brand" href="/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt="" width="28" height="28">Movecia<i></i></a>
    <button class="mvm-x" type="button" aria-label="Menü schließen"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg></button>
  </div>
  <nav class="mvm-list" aria-label="Seiten">
    <?php foreach ($items as [$href, $de, $en, $k, $sde, $sen]): ?><a href="<?= $href ?>"<?= $k === $active ? ' class="on" aria-current="page"' : '' ?>><b><span class="de"><?= $de ?></span><span class="en"><?= $en ?></span></b><small><span class="de"><?= $sde ?></span><span class="en"><?= $sen ?></span></small><svg class="ar" width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg></a>
    <?php endforeach; ?>
  </nav>
  <div class="mvm-foot">
    <a class="mvm-login" href="/mitglieder/"><span class="de">Login Mitgliederbereich</span><span class="en">Log in to members area</span></a>
    <a class="mvm-app" href="/?install=1" data-mv-app><img src="/img/movecia/movecia-app-192.png" alt="" width="34" height="34"><span><b><span class="de">Movecia als App</span><span class="en">Movecia as an app</span></b><small><span class="de">Aufs Handy holen, ohne App Store</span><span class="en">Add it to your phone, no app store needed</span></small></span></a>
    <?php if ($lang): ?><div class="mvm-lang" role="group" aria-label="Sprache / Language"><button type="button" data-mvm-l="de">Deutsch</button><button type="button" data-mvm-l="en">English</button></div><?php endif; ?>
    <a class="mvm-mail" href="mailto:hello@moveciamovement.com">hello@moveciamovement.com</a>
  </div>
</div>
<style>
.mvm-bar{display:none;align-items:center;gap:.25rem;margin-left:auto}
.mvm-bar a{color:var(--ink,#2E2529);text-decoration:none;padding:.45em .8em;border-radius:999px;font-size:.95rem;white-space:nowrap}
.mvm-bar a:hover,.mvm-bar a.on{background:var(--card,#FBF8F4)}
.mvm-bar a.lg{border:1px solid var(--line,#DDD2C6)}
.mvm-btn{display:grid;place-items:center;flex:0 0 44px;width:44px;height:44px;border:1px solid var(--line,#DDD2C6);background:var(--card,#FBF8F4);color:var(--ink,#2E2529);border-radius:999px;cursor:pointer;padding:0}
@media(min-width:900px){.mvm-bar{display:flex}.mvm-bar~.mvm-btn{display:none}}
html:not([data-l=en]):not([lang=en]) .mvm .en,html:not([data-l=en]):not([lang=en]) .mvm-bar .en,html[data-l=en] .mvm .de,html[data-l=en] .mvm-bar .de,html[lang=en]:not([data-l=de]) .mvm .de,html[lang=en]:not([data-l=de]) .mvm-bar .de{display:none!important}
.mvm{position:fixed;inset:0;z-index:1000;background:#F2ECE4;color:#2E2529;display:flex;flex-direction:column;overflow-y:auto;overscroll-behavior:contain;padding:max(14px,env(safe-area-inset-top)) max(18px,env(safe-area-inset-right)) max(22px,env(safe-area-inset-bottom)) max(18px,env(safe-area-inset-left));font-family:"Hanken",system-ui,sans-serif;font-size:17px;line-height:1.5;opacity:0;transform:translateX(24px);transition:opacity .25s ease,transform .25s ease}
.mvm.open{opacity:1;transform:none}
.mvm[hidden]{display:none}
.mvm-top{display:flex;align-items:center;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid #DDD2C6}
.mvm-brand{display:flex;align-items:center;gap:.5rem;font-family:"Cormorant",Georgia,serif;font-size:1.75rem;color:#2E2529;text-decoration:none;line-height:1}
.mvm-brand i{display:inline-block;width:.3em;height:.3em;border-radius:50%;background:#7D5E68;margin-left:.04em}
.mvm-x{display:grid;place-items:center;width:44px;height:44px;border:1px solid #DDD2C6;background:#FBF8F4;color:#2E2529;border-radius:999px;cursor:pointer;padding:0}
.mvm-list{display:flex;flex-direction:column;margin-top:6px}
.mvm-list a{display:grid;grid-template-columns:1fr auto;align-items:center;column-gap:1rem;padding:15px 4px;border-bottom:1px solid #DDD2C6;color:#2E2529;text-decoration:none}
.mvm-list b{font-family:"Cormorant",Georgia,serif;font-weight:500;font-size:1.85rem;line-height:1.1}
.mvm-list small{grid-column:1;font-size:.88rem;color:#7D7076;margin-top:2px}
.mvm-list .ar{grid-column:2;grid-row:1/span 2;color:#7D5E68}
.mvm-list a.on b{color:#7D5E68}
.mvm-list a.on b::after{content:"";display:inline-block;width:7px;height:7px;border-radius:50%;background:#7D5E68;margin-left:.5rem;vertical-align:middle}
.mvm-list a:active{background:#FBF8F4}
.mvm-foot{margin-top:auto;padding-top:24px;display:flex;flex-direction:column;gap:14px;align-items:flex-start}
.mvm-login{display:inline-flex;align-items:center;justify-content:center;width:100%;background:#7D5E68;color:#FBF5F7;border-radius:999px;padding:.9em 1.4em;text-decoration:none;font-weight:500}
.mvm-app{display:flex;align-items:center;gap:12px;width:100%;box-sizing:border-box;padding:10px 14px;border:1px solid #DDD2C6;border-radius:18px;background:#FBF8F4;color:#2E2529;text-decoration:none}
.mvm-app img{border-radius:9px;flex:0 0 34px}
.mvm-app b{display:block;font-weight:500;font-size:.98rem}
.mvm-app small{display:block;font-size:.82rem;color:#7D7076}
.mv-standalone .mvm-app{display:none}
.mvm-lang{display:flex;background:#FBF8F4;border:1px solid #DDD2C6;border-radius:999px;padding:3px}
.mvm-lang button{font:inherit;font-size:.9rem;border:0;background:none;border-radius:999px;padding:.4em 1em;cursor:pointer;color:#7D7076}
.mvm-lang button[aria-pressed=true]{background:#7D5E68;color:#FBF5F7}
.mvm-mail{font-size:.9rem;color:#7D7076}
html.mvm-lock,html.mvm-lock body{overflow:hidden}
@media(prefers-reduced-motion:reduce){.mvm{transition:none}}
</style>
<script>
(function(){
  var p=document.getElementById('mvm-panel');if(!p)return;
  document.body.appendChild(p); // raus aus jedem Kopf, damit „fixed“ wirklich den ganzen Bildschirm füllt
  var root=document.documentElement,last=null;
  function btns(){return document.querySelectorAll('.mvm-btn')}
  function open(){last=document.activeElement;p.hidden=false;root.classList.add('mvm-lock');requestAnimationFrame(function(){p.classList.add('open')});btns().forEach(function(b){b.setAttribute('aria-expanded','true')});var x=p.querySelector('.mvm-x');if(x)x.focus();sync();}
  function close(){p.classList.remove('open');root.classList.remove('mvm-lock');btns().forEach(function(b){b.setAttribute('aria-expanded','false')});setTimeout(function(){if(!p.classList.contains('open'))p.hidden=true},260);if(last&&last.focus)last.focus();}
  btns().forEach(function(b){b.addEventListener('click',open)});
  p.querySelector('.mvm-x').addEventListener('click',close);
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!p.hidden)close()});
  p.querySelectorAll('.mvm-list a,.mvm-login,.mvm-brand').forEach(function(a){a.addEventListener('click',function(){var h=a.getAttribute('href')||'';if(h.indexOf('#')>-1&&location.pathname==='/'&&h.charAt(0)==='/')close();else{root.classList.remove('mvm-lock');}})});
  // Zurück-Taste: Seite kommt aus dem Browser-Speicher zurück, Menü muss zu und Scrollen frei sein
  window.addEventListener('pageshow',function(){p.classList.remove('open');p.hidden=true;root.classList.remove('mvm-lock');document.body.style.overflow='';btns().forEach(function(b){b.setAttribute('aria-expanded','false')})});
  function sync(){var l=root.dataset.l==='en'?'en':'de';p.querySelectorAll('[data-mvm-l]').forEach(function(b){b.setAttribute('aria-pressed',String(b.dataset.mvmL===l))})}
  p.querySelectorAll('[data-mvm-l]').forEach(function(b){b.addEventListener('click',function(){
    var v=b.dataset.mvmL;
    // den Umschalter der Seite mitbenutzen, damit alles auf der Seite gleich mitwechselt
    var own=document.querySelector('.lang [data-set="'+v+'"],.lang button[data-l="'+v+'"]');
    if(own)own.click();else{root.dataset.l=v;root.lang=v;try{localStorage.setItem('wh-lang',v)}catch(e){}}
    sync();
  })});
})();
</script>
<?php
}

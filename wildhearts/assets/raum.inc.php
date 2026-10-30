<?php
// Kleine Galerie des Movecia Studios in Mathon (Vivien, 30.10.2026): echte Fotos vom Raum, nur gerade gerichtet
// und farblich angeglichen (nichts dazu- oder weggerechnet). Bilder wechseln ruhig von selbst, Wischen/Punkte möglich.
// Eingebaut in /natal (#wo) und /personal-training. mv_raum_gallery($en)
function mv_raum_gallery(bool $en = false, string $id = 'raum'): string
{
    $pics = [
        ['raum-1.jpg', 'Der Raum mit Sprossenwand, Matte und Monstera', 'The room with wall bars, mat and monstera', '50% 60%'],
        ['raum-3.jpg', 'Licht vom Fenster, Bike, Box und Gewichte', 'Window light, bike, box and weights', '50% 55%'],
        ['raum-2.jpg', 'Sprossenwand, Parallettes und viel Platz zum Bewegen', 'Wall bars, parallettes and plenty of space to move', '50% 60%'],
        ['raum-4.jpg', 'Kleine Helfer: Ring, Hanteln und Gewichte', 'Little helpers: ring, dumbbells and weights', '50% 62%'],
        ['raum-5.jpg', 'Kurzhanteln auf der Box', 'Dumbbells on the box', '50% 50%'],
    ];
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    ob_start(); ?>
<style>
.raum{position:relative;margin-top:1.4rem;border-radius:28px;overflow:hidden;aspect-ratio:16/10;background:#E4DCD1;max-width:980px}
.raum img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 1.2s ease;-webkit-user-drag:none;user-select:none;-webkit-touch-callout:none}
.raum img.on{opacity:1}
.raum .cap{position:absolute;left:0;right:0;bottom:0;padding:2.6rem 1.2rem .9rem;background:linear-gradient(transparent,rgba(42,32,38,.62));color:#F7F1EE;font-size:.92rem;display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;pointer-events:none}
.raum .dots{display:flex;gap:6px;pointer-events:auto}
.raum .dots button{width:9px;height:9px;padding:0;border:0;border-radius:99px;background:rgba(247,241,238,.55);cursor:pointer;transition:width .3s}
.raum .dots button.on{width:22px;background:#F7F1EE}
.raum .nav{position:absolute;top:50%;transform:translateY(-50%);width:42px;height:42px;border-radius:50%;border:0;background:rgba(251,249,246,.88);color:#4A3040;font-size:22px;line-height:1;cursor:pointer;display:grid;place-items:center;opacity:0;transition:opacity .2s}
.raum:hover .nav,.raum .nav:focus-visible{opacity:1}.raum .nav.p{left:12px}.raum .nav.n{right:12px}
@media(hover:none){.raum .nav{display:none}}
@media(max-width:640px){.raum{aspect-ratio:4/5;border-radius:22px}.raum .cap{font-size:.84rem}}
</style>
<div class="raum" id="<?= $h($id) ?>" aria-roledescription="<?= $en ? 'gallery' : 'Galerie' ?>">
<?php foreach ($pics as $i => [$f, $de, $enT, $pos]): ?>  <img src="/img/movecia/fotos/<?= $h($f) ?>" alt="<?= $h($en ? $enT : $de) ?>" data-de="<?= $h($de) ?>" data-en="<?= $h($enT) ?>" style="object-position:<?= $h($pos) ?>" draggable="false"<?= $i ? ' loading="lazy"' : ' class="on"' ?>>
<?php endforeach; ?>
  <button class="nav p" type="button" aria-label="<?= $en ? 'Previous photo' : 'Vorheriges Foto' ?>">‹</button><button class="nav n" type="button" aria-label="<?= $en ? 'Next photo' : 'Nächstes Foto' ?>">›</button>
  <div class="cap"><span data-cap><?= $h($en ? $pics[0][2] : $pics[0][1]) ?></span><span class="dots"><?php foreach ($pics as $i => $_): ?><button type="button" aria-label="<?= ($en ? 'Photo ' : 'Foto ') . ($i + 1) ?>"<?= $i ? '' : ' class="on"' ?>></button><?php endforeach; ?></span></div>
</div>
<script>
(function(){var g=document.getElementById(<?= json_encode($id) ?>);if(!g)return;var im=g.querySelectorAll('img'),d=g.querySelectorAll('.dots button'),cap=g.querySelector('[data-cap]'),i=0,t=null,x0=null;
function go(n){i=(n+im.length)%im.length;im.forEach(function(e,k){e.classList.toggle('on',k===i)});d.forEach(function(e,k){e.classList.toggle('on',k===i)});cap.textContent=(document.documentElement.dataset.l==='en'?im[i].dataset.en:im[i].dataset.de)||im[i].alt;}
function auto(){clearInterval(t);if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;t=setInterval(function(){if(!document.hidden)go(i+1)},4500);}
d.forEach(function(b,k){b.onclick=function(){go(k);auto()}});g.querySelector('.p').onclick=function(){go(i-1);auto()};g.querySelector('.n').onclick=function(){go(i+1);auto()};
g.addEventListener('touchstart',function(e){x0=e.touches[0].clientX},{passive:true});g.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(Math.abs(dx)>40){go(i+(dx<0?1:-1));auto()}x0=null});
g.addEventListener('contextmenu',function(e){e.preventDefault()});
go(0);if('IntersectionObserver' in window){new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting)auto();else clearInterval(t)})}).observe(g)}else auto();})();
</script>
<?php return ob_get_clean();
}

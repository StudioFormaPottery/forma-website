<?php
// Startseiten-Pop-up moveciamovement.com (Vivien, 28.10.2026): zeigt automatisch das nächste Angebot nach Datum, entschieden am Server.
// Bis Method-Start (mv_cfg launch) → Method, bis Unfold-Start (u_start) → Unfold, danach → Teacher Training (Academy).
// Preise immer aus mv_cfg()/ac_cfg(). Einmal pro Browser-Sitzung, nach ~6 s oder beim Scrollen, am Handy als Blatt von unten.
// Vorschau: /?popup=method|unfold|academy. Verwaltung → Movecia → Übersicht: automatisch / aus (mm_cfg('popup')).
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/api/method-lib.php';
require_once dirname(__DIR__, 2) . '/api/academy-lib.php';

function mv_popup_state(?string $force = null): string
{
    if (in_array($force, ['method', 'unfold', 'academy'], true)) return $force;
    $mode = (string)((mm_cfg('popup') ?? [])['mode'] ?? 'auto');
    if ($mode === 'off') return '';
    $c = mv_cfg();
    if (time() < mv_ts((string)$c['launch'], '00:00')) return 'method';
    if (time() < mv_ts((string)$c['u_start'], '00:00')) return 'unfold';
    $a = ac_cfg();
    return $a['open'] && time() < $a['start_ts'] ? 'academy' : '';
}

function mv_popup(PDO $pdo, ?string $force = null): string
{
    try { ac_schema($pdo); ac_tick($pdo); } catch (Throwable $e) {} // Academy-Automatik auch über Startseiten-Aufrufe
    $st = mv_popup_state($force);
    if ($st === '') return '';
    $c = mv_cfg();
    $eu = fn(int $x) => '€&nbsp;' . number_format($x / 100, $x % 100 ? 2 : 0, ',', '.');
    $eue = fn(int $x) => '€' . number_format($x / 100, $x % 100 ? 2 : 0, '.', ',');
    $d = fn(int $ts, string $l = 'de') => forma_h(mv_date($ts, $l));
    if ($st === 'method') {
        $eb = (bool)$c['eb_m'];
        $kick = $eb ? '<span class="de">Early Bird bis ' . $d($c['eb_m_end']) . '</span><span class="en">Early bird until ' . $d($c['eb_m_end'], 'en') . '</span>' : '<span class="de">Jetzt starten</span><span class="en">Start now</span>';
        $title = 'The Mindful Movement <em>Method</em>';
        $text = '<span class="de">Jeden Monat neue Flows, Bewegungsvideos und Impulse, die zu deinem Leben passen. Schritt für Schritt, in deinem Tempo. Start am ' . $d(mv_ts((string)$c['launch'])) . '.</span><span class="en">New flows, movement videos and talks every month, made to fit your life. Step by step, at your own pace. Starts ' . $d(mv_ts((string)$c['launch']), 'en') . '.</span>';
        $price = '<span class="de">' . $eu((int)$c['m1']) . ' im Monat' . ($eb && $c['r_m1'] > $c['m1'] ? ' <s>' . $eu((int)$c['r_m1']) . '</s>' : '') . '</span><span class="en">' . $eue((int)$c['m1']) . ' a month' . ($eb && $c['r_m1'] > $c['m1'] ? ' <s>' . $eue((int)$c['r_m1']) . '</s>' : '') . '</span>';
        $href = '/method/'; $btn = '<span class="de">Zur Method</span><span class="en">See the Method</span>'; $img = 'ev-method-foto.jpg';
    } elseif ($st === 'unfold') {
        $eb = (bool)$c['eb_u'];
        $kick = $eb ? '<span class="de">Early Bird bis ' . $d($c['eb_u_end']) . '</span><span class="en">Early bird until ' . $d($c['eb_u_end'], 'en') . '</span>' : '<span class="de">Nur ' . (int)$c['u_seats'] . ' Plätze</span><span class="en">Only ' . (int)$c['u_seats'] . ' spots</span>';
        $title = 'Unfold · <em>The Mindful Becoming</em>';
        $text = '<span class="de">Dein 3-Monats-Begleiter für Mindful Movement: wöchentlicher Live-Call, zwei Bewegungsvideos pro Woche, Workbooks und Rituale. Erster Call am ' . $d(mv_ts((string)$c['u_start'])) . '.</span><span class="en">Your 3-month companion for mindful movement: a weekly live call, two movement videos a week, workbooks and rituals. First call on ' . $d(mv_ts((string)$c['u_start']), 'en') . '.</span>';
        $price = '<span class="de">' . $eu((int)$c['u_once']) . ' oder ' . (int)$c['u_n'] . ' × ' . $eu((int)$c['u_inst']) . '</span><span class="en">' . $eue((int)$c['u_once']) . ' or ' . (int)$c['u_n'] . ' × ' . $eue((int)$c['u_inst']) . '</span>';
        $href = '/unfold/#buchen'; $btn = '<span class="de">Platz sichern</span><span class="en">Book my spot</span>'; $img = 'ev-unfold-karte.jpg';
    } else {
        ac_schema($pdo);
        $a = ac_cfg();
        $full = ac_free($pdo) <= 0;
        $kick = $full ? 'Warteliste' : ($a['eb'] ? 'Early Bird bis ' . $d($a['eb_end']) : 'Nur 10 Plätze · nur alle 2 Jahre');
        $title = 'Mindful Movement <em>Teacher Training</em>';
        $text = 'Andere begleiten und dabei bei dir selbst bleiben: ' . AC_HOURS . ' Stunden inklusive vollständiger 200-Stunden-Yogalehrerausbildung, 11 Monate hybrid ab ' . $d($a['start_ts']) . '.<span class="en"> (Held in German.)</span>';
        $price = $full ? 'Alle Plätze sind vergeben' : ($a['eb'] ? $eu((int)$a['eb_total']) . ' <s>' . $eu((int)$a['r_total']) . '</s>' : $eu((int)$a['r_total']));
        $href = $full ? '/academy/teacher-training/#liste' : '/academy/teacher-training/'; $btn = $full ? 'Auf die Warteliste' : 'Zur Ausbildung'; $img = 'ev-lunge.jpg';
    }
    $mark = $st === 'academy' && function_exists('ac_seal') ? ac_seal('pu-mark') : '';
    ob_start(); ?>
<div class="pu pu-<?= $st ?>" id="mvpu" hidden data-force="<?= $force ? '1' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="mvpu-t">
  <div class="pu-bg" data-pu-close></div>
  <div class="pu-card" tabindex="-1">
    <button type="button" class="pu-x" data-pu-close aria-label="Schließen">×</button>
    <div class="pu-pic"><img src="/img/movecia/fotos/<?= $img ?>" alt="" loading="lazy"><?= $mark ?></div>
    <div class="pu-tx">
      <p class="pu-k"><?= $kick ?></p>
      <h2 id="mvpu-t"><?= $title ?></h2>
      <p class="pu-p"><?= $text ?></p>
      <p class="pu-price"><?= $price ?></p>
      <a class="btn pu-go" href="<?= $href ?>"><?= $btn ?> →</a>
      <button type="button" class="pu-later" data-pu-close><span class="de">Vielleicht später</span><span class="en">Maybe later</span></button>
    </div>
  </div>
</div>
<style>
.pu{position:fixed;inset:0;z-index:80;display:flex;align-items:center;justify-content:center;padding:16px}
.pu[hidden]{display:none}
.pu-bg{position:absolute;inset:0;background:rgba(46,37,41,.38);opacity:0;transition:opacity .35s}
.pu.on .pu-bg{opacity:1}
.pu-card{position:relative;display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);width:min(860px,100%);max-height:calc(100vh - 32px);overflow:auto;background:linear-gradient(140deg,#FBF8F4,#F6ECE6 60%,#F3E2D8);border-radius:32px;box-shadow:0 30px 70px -30px rgba(46,37,41,.55);transform:translateY(16px) scale(.98);opacity:0;transition:transform .45s cubic-bezier(.2,.8,.2,1),opacity .35s;outline:0}
.pu.on .pu-card{transform:none;opacity:1}
.pu-academy .pu-card{background:linear-gradient(140deg,#F6E9E5,#F0DCD5 60%,#EBD3CB)}
.pu-x{position:absolute;right:12px;top:10px;z-index:3;width:42px;height:42px;border-radius:50%;border:0;background:rgba(251,248,244,.9);color:#4A3A41;font-size:26px;line-height:1;cursor:pointer}
.pu-pic{position:relative;padding:22px 0 22px 22px}
.pu-pic img{display:block;width:100%;height:100%;min-height:320px;object-fit:cover;border-radius:999px 999px 26px 26px}
.pu-mark{position:absolute;left:8px;bottom:12px;width:70px;height:auto;filter:drop-shadow(0 8px 16px rgba(74,58,65,.25))}
.pu-tx{padding:clamp(1.4rem,3vw,2.4rem);display:flex;flex-direction:column;justify-content:center;gap:.7rem}
.pu-k{margin:0;font-size:.74rem;letter-spacing:.2em;text-transform:uppercase;color:#7D5E68;font-weight:500}
.pu-tx h2{margin:0;font-family:var(--serif);font-weight:500;font-size:clamp(1.9rem,3.6vw,2.7rem);line-height:1.05}
.pu-p{margin:0;color:#7D7076;font-size:1rem;line-height:1.55}
.pu-price{margin:.2rem 0 0;font-family:var(--serif);font-size:1.6rem;color:#4A3A41}
.pu-price s{font-size:.7em;color:#9C8C92;margin-left:.3em}
.pu-go{align-self:flex-start;margin-top:.4rem}
.pu-later{align-self:flex-start;background:none;border:0;color:#7D7076;font:inherit;font-size:.9rem;text-decoration:underline;cursor:pointer;padding:.3rem 0}
@media(max-width:700px){
  .pu{align-items:flex-end;padding:0}
  .pu-card{grid-template-columns:96px minmax(0,1fr);border-radius:28px 28px 0 0;max-height:78vh;transform:translateY(100%)}
  .pu.on .pu-card{transform:none}
  .pu-pic{padding:22px 0 0 18px}.pu-pic img{min-height:0;height:132px;border-radius:999px 999px 18px 18px}
  .pu-mark{display:none}
  .pu-k{padding-right:46px}
  .pu-tx{padding:20px 18px calc(20px + env(safe-area-inset-bottom))}
  .pu-tx h2{font-size:1.75rem;padding-right:34px}
  .pu-p{font-size:.95rem}
}
@media(prefers-reduced-motion:reduce){.pu-card,.pu-bg{transition:none}}
</style>
<script>
(function(){
  var pu=document.getElementById('mvpu');if(!pu)return;
  var force=pu.dataset.force==='1',key='mvpu-<?= $st ?>',shown=false,last=null,timer;
  try{if(!force&&sessionStorage.getItem(key))return}catch(e){}
  function focusables(){return pu.querySelectorAll('a[href],button')}
  function open(){if(shown)return;shown=true;clearTimeout(timer);window.removeEventListener('scroll',onScroll);
    try{sessionStorage.setItem(key,'1')}catch(e){}
    last=document.activeElement;pu.hidden=false;requestAnimationFrame(function(){pu.classList.add('on');var f=pu.querySelector('.pu-go');if(f)f.focus({preventScroll:true})});
    document.addEventListener('keydown',onKey)}
  function close(){pu.classList.remove('on');document.removeEventListener('keydown',onKey);setTimeout(function(){pu.hidden=true;if(last&&last.focus)last.focus({preventScroll:true})},350)}
  function onKey(e){if(e.key==='Escape'){close();return}
    if(e.key==='Tab'){var f=focusables(),a=f[0],z=f[f.length-1];if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus()}else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus()}}}
  function onScroll(){if(window.scrollY>window.innerHeight*.9)open()}
  pu.querySelectorAll('[data-pu-close]').forEach(function(b){b.addEventListener('click',close)});
  if(force){setTimeout(open,300);return}
  timer=setTimeout(open,6000);window.addEventListener('scroll',onScroll,{passive:true});
})();
</script>
<?php
    return (string)ob_get_clean();
}

<?php
// Pop-up auf der Movecia-Hauptseite für das Angebot, das als Nächstes startet (Vivien, 28.10.2026):
// bis zum Start der Mindful Movement Method (launch) → Method, danach bis zum Unfold-Start (u_start) → Unfold,
// danach → Teacher Training (Warteliste). Einmal pro Browser-Sitzung, nach ein paar Sekunden.
// Vorschau: /?popup=method|unfold|teacher (zeigt es sofort, auch wenn es schon gesehen wurde).

function mv_next_popup_phase(): string
{
    $c = mv_cfg();
    $now = time();
    if ($now < mv_ts($c['launch'])) return 'method';
    // Unfold nur, solange es noch offen ist und Plätze frei sind – sonst gleich das nächste Angebot (Vivien, 28.10.2026)
    if ($now < mv_ts($c['u_start'], '00:00') && (int)$c['u_open'] === 1) {
        $free = 1;
        try { $free = mv_unfold_free(forma_db()); } catch (Throwable $e) {}
        if ($free > 0) return 'unfold';
    }
    return 'teacher';
}

function mv_next_popup(): void
{
    $c = mv_cfg();
    $force = in_array($_GET['popup'] ?? '', ['method', 'unfold', 'teacher'], true) ? $_GET['popup'] : '';
    $ph = $force ?: mv_next_popup_phase();
    $eu = fn(int $x) => '€ ' . number_format($x / 100, 0, ',', '.');
    $ml = mv_ts($c['launch']);
    $us = mv_ts($c['u_start']);
    $short = function (int $t, string $l): string {
        $d = (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Vienna'));
        $m = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        return $l === 'en' ? $d->format('j F') : $d->format('j') . '. ' . $m[(int)$d->format('n')];
    };
    if ($ph === 'method') {
        $p = ['img' => '/img/movecia/fotos/ev-method-karte.jpg', 'pos' => '50% 30%', 'href' => '/method/',
            'k' => ['Mindful Movement Method · Start ' . $short($ml, 'de'), 'Mindful Movement Method · starts ' . $short($ml, 'en')],
            'h' => ['Jeden Tag ein paar <em>ruhige Minuten</em> für dich', 'A few <em>quiet minutes</em> for you, every day'],
            't' => ['Kurze Videos aus Yoga, Somatic Movement und Bodyweight, dazu Breathwork und Meditation. Du beginnst bei Monat 1 und gehst Schritt für Schritt weiter.', 'Short videos of yoga, somatic movement and bodyweight training, plus breathwork and meditation. You begin with month 1 and go step by step.'],
            'price' => $c['eb_m'] ? ['Early-Bird bis ' . $short($c['eb_m_end'], 'de') . ': ' . $eu($c['m1']) . ' statt ' . $eu($c['r_m1']) . ' im Monat', 'Early bird until ' . $short($c['eb_m_end'], 'en') . ': ' . $eu($c['m1']) . ' instead of ' . $eu($c['r_m1']) . ' a month'] : [$eu($c['m1']) . ' im Monat', $eu($c['m1']) . ' a month'],
            'btn' => ['Mehr erfahren', 'Find out more']];
    } elseif ($ph === 'unfold') {
        $p = ['img' => '/img/movecia/fotos/ev-unfold-karte.jpg', 'pos' => '50% 35%', 'href' => '/unfold/',
            'k' => ['Unfold · Start ' . $short($us, 'de') . ' · nur ' . (int)$c['u_seats'] . ' Plätze', 'Unfold · starts ' . $short($us, 'en') . ' · only ' . (int)$c['u_seats'] . ' spots'],
            'h' => ['Unfold · <em>The Mindful Becoming</em>', 'Unfold · <em>The Mindful Becoming</em>'],
            't' => ['Drei Monate, in denen du zu dir zurückfindest: wöchentliche Live-Calls, Bewegungsvideos und Workbooks gegen den Mental Load.', 'Three months to come back to yourself: weekly live calls, movement videos and workbooks for your mental load.'],
            'price' => $c['eb_u'] ? ['Early-Bird bis ' . $short($c['eb_u_end'], 'de') . ': ' . $eu($c['u_once']) . ' statt ' . $eu($c['r_u_once']), 'Early bird until ' . $short($c['eb_u_end'], 'en') . ': ' . $eu($c['u_once']) . ' instead of ' . $eu($c['r_u_once'])] : [$eu($c['u_once']) . ' oder ' . (int)$c['u_n'] . ' × ' . $eu($c['u_inst']), $eu($c['u_once']) . ' or ' . (int)$c['u_n'] . ' × ' . $eu($c['u_inst'])],
            'btn' => ['Platz sichern', 'Save my spot']];
    } else {
        $p = ['img' => '/img/movecia/fotos/ev-ueber-mich-weit.jpg', 'pos' => '50% 60%', 'href' => '/teacher-training/#liste',
            'k' => ['Bald bei Movecia · Start November 2027', 'Coming soon · starts November 2027'],
            'h' => ['Mindful Movement <em>Teacher Training</em>', 'Mindful Movement <em>Teacher Training</em>'],
            't' => ['Eine Ausbildung in Yoga, Somatic Movement und Bodyweight mit insgesamt 550 Stunden. Setz deinen Namen auf die Liste, dann erfährst du alle Infos als Erste.', 'A training in yoga, somatic movement and bodyweight with 550 hours in total. Put your name on the list and you\'ll be the first to hear all the details.'],
            'price' => null,
            'btn' => ['Auf die Liste', 'Put me on the list']];
    }
    $startTs = $ph === 'method' ? $ml : ($ph === 'unfold' ? $us : 0);
    // Schlüssel fürs „schon gesehen“: ändert sich mit Angebot und Preisstufe → neues Angebot oder Ende des Early-Bird zeigt das Pop-up wieder
    $ver = $ph . '-' . ($ph === 'method' ? (int)$c['eb_m'] : ($ph === 'unfold' ? (int)$c['eb_u'] : 0));
    ?>
<!-- Kiesel-Pop-up (Vivien, 28.10.2026: „voll langweilig, lieber rund, in der Form vom Logo“): weiche Kieselform mit versetztem Pflaume-Umriss wie im Logo,
     Foto als Kiesel, Pflaume-Punkt mit Countdown bis zum Start. Bewegt sich ganz leicht mit der Maus, atmet langsam. -->
<div class="npop" id="npop" data-ph="<?= forma_h($ver) ?>" data-force="<?= $force ? '1' : '' ?>" data-start="<?= (int)$startTs ?>" role="dialog" aria-modal="true" aria-labelledby="npop-h" hidden>
  <div class="npop-stage">
    <span class="npop-ring" aria-hidden="true"></span>
    <div class="npop-card">
      <button class="npop-x" type="button" aria-label="Schließen / Close">✕</button>
      <div class="npop-pic">
        <div class="npop-ph" style="background-image:url(<?= forma_h($p['img']) ?>);background-position:<?= forma_h($p['pos']) ?>"></div>
        <span class="npop-dot" aria-hidden="true"><?php if ($startTs): ?><small class="t1"></small><b></b><small class="t2"></small><?php else: ?><small>Nov</small><b>2027</b><?php endif; ?></span>
      </div>
      <div class="npop-tx">
        <p class="k"><span class="de"><?= forma_h($p['k'][0]) ?></span><span class="en"><?= forma_h($p['k'][1]) ?></span></p>
        <h2 id="npop-h"><span class="de"><?= $p['h'][0] ?></span><span class="en"><?= $p['h'][1] ?></span></h2>
        <p class="npop-t"><span class="de"><?= forma_h($p['t'][0]) ?></span><span class="en"><?= forma_h($p['t'][1]) ?></span></p>
        <?php if ($p['price']): ?><p class="npop-pr"><span class="de"><?= forma_h($p['price'][0]) ?></span><span class="en"><?= forma_h($p['price'][1]) ?></span></p><?php endif; ?>
        <div class="npop-cta"><a class="btn" href="<?= forma_h($p['href']) ?>"><span class="de"><?= forma_h($p['btn'][0]) ?></span><span class="en"><?= forma_h($p['btn'][1]) ?></span> →</a><button class="npop-later" type="button"><span class="de">Später</span><span class="en">Maybe later</span></button></div>
      </div>
    </div>
  </div>
</div>
<style>
.npop{position:fixed;inset:0;z-index:90;display:flex;overflow-y:auto;padding:28px 18px;background:rgba(46,37,41,.38);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);opacity:0;transition:opacity .45s}
.npop.on{opacity:1}
.npop[hidden]{display:none}
.npop-stage{--mx:0;--my:0;position:relative;margin:auto;width:min(700px,100%);transform:translateY(24px) scale(.94) rotate(-1.5deg);transition:transform .9s cubic-bezier(.2,1.25,.4,1)}
.npop.on .npop-stage{transform:none}
.npop-card{position:relative;z-index:1;display:grid;grid-template-columns:210px 1fr;gap:2rem;align-items:center;padding:3rem 3.4rem 3rem 3rem;background:radial-gradient(120% 90% at 20% 15%,#FBF6F1 0%,#F4EAE2 55%,#EEDFD4 100%);border-radius:44% 56% 48% 52% / 24% 30% 28% 22%;box-shadow:0 34px 80px rgba(46,37,41,.26);translate:calc(var(--mx)*4px) calc(var(--my)*4px);animation:npMorph 14s ease-in-out infinite alternate}
.npop-ring{position:absolute;inset:0;border:1.5px solid var(--plum,#7D5E68);border-radius:52% 48% 40% 60% / 30% 22% 30% 26%;translate:calc(16px - var(--mx)*10px) calc(-14px - var(--my)*10px);rotate:-1.2deg;opacity:.75;pointer-events:none;animation:npRing 17s ease-in-out infinite alternate}
@keyframes npMorph{to{border-radius:52% 48% 44% 56% / 30% 24% 22% 28%}}
@keyframes npRing{to{border-radius:46% 54% 52% 48% / 24% 30% 26% 30%}}
.npop-pic{position:relative;translate:calc(var(--mx)*-7px) calc(var(--my)*-7px)}
.npop-ph{aspect-ratio:4/5;background-size:cover;border-radius:52% 48% 44% 56% / 48% 44% 56% 52%;rotate:-2deg;box-shadow:0 14px 34px rgba(74,58,65,.22);animation:npFloat 7s ease-in-out infinite alternate}
@keyframes npFloat{to{rotate:1deg;translate:0 -5px}}
.npop-dot{position:absolute;right:-14px;top:6px;width:78px;height:78px;border-radius:50%;background:var(--plum,#7D5E68);color:#FBF5F7;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1;box-shadow:0 8px 20px rgba(74,58,65,.3);animation:npBreathe 4.5s ease-in-out infinite}
.npop-dot b{font-family:var(--serif);font-weight:500;font-size:1.6rem;line-height:.95;margin:.12em 0 .16em}
.npop-dot b.w{font-size:1.05rem}
.npop-dot small{font-size:.58rem;letter-spacing:.14em;text-transform:uppercase;opacity:.9}
@keyframes npBreathe{50%{transform:scale(1.07)}}
.npop-tx{display:flex;flex-direction:column;gap:.5rem;min-width:0}
.npop-tx .k{font-size:.62rem;letter-spacing:.16em;padding-right:1.8rem}
.npop-tx h2{font-size:clamp(1.65rem,3.2vw,2.15rem);line-height:1.08;margin:.05rem 0 .15rem}
.npop-t{color:var(--muted,#7D7076);margin:0;font-size:.92rem;line-height:1.55}
.npop-pr{margin:.25rem 0 0;align-self:flex-start;background:rgba(251,248,244,.85);border:1px solid var(--line,#DDD2C6);border-radius:18px;padding:.5em 1.05em;color:var(--deep,#4A3A41);font-size:.86rem}
.npop-cta{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1rem;margin-top:.6rem}
.npop-cta .btn{font-size:.92rem}
.npop-later{border:0;background:none;color:var(--muted,#7D7076);font:inherit;font-size:.88rem;cursor:pointer;text-decoration:underline;text-underline-offset:3px;padding:.4em 0}
.npop-x{position:absolute;right:9%;top:9%;z-index:2;width:34px;height:34px;border-radius:50%;border:0;background:rgba(251,248,244,.95);color:var(--deep,#4A3A41);font-size:.9rem;cursor:pointer;box-shadow:0 2px 8px rgba(46,37,41,.1)}
@media(max-width:719px){
  .npop-stage{width:min(420px,100%)}
  .npop-card{grid-template-columns:1fr;gap:1rem;padding:2rem 1.5rem 1.8rem;text-align:center;border-radius:46% 54% 42% 58% / 14% 16% 13% 12%}
  .npop-ring{translate:calc(9px - var(--mx)*6px) calc(-9px - var(--my)*6px);border-radius:52% 48% 50% 50% / 15% 12% 14% 14%}
  @keyframes npMorph{to{border-radius:52% 48% 46% 54% / 16% 13% 12% 15%}}
  @keyframes npRing{to{border-radius:46% 54% 52% 48% / 12% 15% 13% 14%}}
  .npop-pic{width:min(46vw,175px);margin:0 auto}
  .npop-ph{aspect-ratio:1/1}
  .npop-dot{width:66px;height:66px;right:-18px;top:-4px}
  .npop-dot b{font-size:1.4rem}
  .npop-tx{align-items:center}
  .npop-tx .k{padding-right:0}
  .npop-pr{align-self:center}
  .npop-cta{justify-content:center}
  .npop-x{right:2px;top:-10px}
}
@media(prefers-reduced-motion:reduce){.npop-card,.npop-ring,.npop-ph,.npop-dot{animation:none}.npop-stage{transition:none}}
</style>
<script>
(function(){
  var p=document.getElementById('npop');if(!p)return;
  var key='mv-npop-'+p.dataset.ph,force=!!p.dataset.force,seen=false;
  // Countdown im Punkt, immer aus der echten Startzeit gerechnet
  var st=+p.dataset.start||0,dot=p.querySelector('.npop-dot');
  function fill(){if(!st||!dot)return;var en=document.documentElement.dataset.l==='en';
    var d0=new Date();d0.setHours(0,0,0,0);var s=new Date(st*1000);s.setHours(0,0,0,0);var n=Math.round((s-d0)/864e5);
    var b=dot.querySelector('b'),t1=dot.querySelector('.t1'),t2=dot.querySelector('.t2');
    if(n<=0){b.textContent=en?'Today':'Heute';b.className='w';t1.textContent=en?'starts':'Start';t2.textContent='';}
    else if(n===1){b.textContent=en?'Tomorrow':'Morgen';b.className='w';t1.textContent='Start';t2.textContent='';}
    else{b.textContent=n;b.className='';t1.textContent=en?'in':'in';t2.textContent=en?'days':'Tagen';}}
  fill();document.addEventListener('mvlang',fill);
  try{seen=sessionStorage.getItem(key)==='1'}catch(e){}
  if(seen&&!force)return;
  if(/[#&]anfrage/.test(location.hash)&&!force)return;
  var stage=p.querySelector('.npop-stage');
  function move(e){var r=stage.getBoundingClientRect();stage.style.setProperty('--mx',((e.clientX-r.left)/r.width*2-1).toFixed(3));stage.style.setProperty('--my',((e.clientY-r.top)/r.height*2-1).toFixed(3))}
  if(matchMedia('(hover:hover)').matches&&!matchMedia('(prefers-reduced-motion: reduce)').matches)p.addEventListener('pointermove',move);
  function close(){p.classList.remove('on');setTimeout(function(){p.hidden=true},450);try{sessionStorage.setItem(key,'1')}catch(e){}document.removeEventListener('keydown',esc)}
  function esc(e){if(e.key==='Escape')close()}
  p.addEventListener('click',function(e){if(e.target===p||e.target.closest('.npop-x,.npop-later'))close()});
  p.querySelector('.npop-cta .btn').addEventListener('click',function(){try{sessionStorage.setItem(key,'1')}catch(e){}});
  setTimeout(function(){p.hidden=false;requestAnimationFrame(function(){p.classList.add('on')});document.addEventListener('keydown',esc)},force?300:3500);
})();
</script>
<?php
}

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
    if ($now < mv_ts($c['u_start'], '00:00')) return 'unfold';
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
    ?>
<div class="npop" id="npop" data-ph="<?= forma_h($ph) ?>" data-force="<?= $force ? '1' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="npop-h" hidden>
  <div class="npop-card">
    <button class="npop-x" type="button" aria-label="Schließen / Close">✕</button>
    <div class="npop-ph" style="background-image:url(<?= forma_h($p['img']) ?>);background-position:<?= forma_h($p['pos']) ?>"></div>
    <div class="npop-tx">
      <p class="k"><span class="de"><?= forma_h($p['k'][0]) ?></span><span class="en"><?= forma_h($p['k'][1]) ?></span></p>
      <h2 id="npop-h"><span class="de"><?= $p['h'][0] ?></span><span class="en"><?= $p['h'][1] ?></span></h2>
      <p class="npop-t"><span class="de"><?= forma_h($p['t'][0]) ?></span><span class="en"><?= forma_h($p['t'][1]) ?></span></p>
      <?php if ($p['price']): ?><p class="npop-pr"><span class="de"><?= forma_h($p['price'][0]) ?></span><span class="en"><?= forma_h($p['price'][1]) ?></span></p><?php endif; ?>
      <div class="npop-cta"><a class="btn" href="<?= forma_h($p['href']) ?>"><span class="de"><?= forma_h($p['btn'][0]) ?></span><span class="en"><?= forma_h($p['btn'][1]) ?></span> →</a><button class="npop-later" type="button"><span class="de">Später</span><span class="en">Maybe later</span></button></div>
    </div>
  </div>
</div>
<style>
.npop{position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(46,37,41,.42);backdrop-filter:blur(3px);opacity:0;transition:opacity .4s}
.npop.on{opacity:1}
.npop[hidden]{display:none}
.npop-card{position:relative;width:min(760px,100%);max-height:calc(100dvh - 32px);overflow:auto;background:var(--bg,#F2ECE4);border-radius:28px;display:grid;grid-template-columns:1fr;box-shadow:0 30px 80px rgba(46,37,41,.28);transform:translateY(14px);transition:transform .4s}
.npop.on .npop-card{transform:none}
@media(min-width:720px){.npop-card{grid-template-columns:.9fr 1.1fr}}
.npop-ph{min-height:200px;background-size:cover;border-radius:28px 28px 0 0}
@media(min-width:720px){.npop-ph{min-height:100%;border-radius:28px 0 0 28px}}
@media(max-width:719px){.npop-ph{height:34vh;max-height:260px}}
.npop-tx{padding:clamp(1.3rem,4vw,2.2rem);display:flex;flex-direction:column;gap:.55rem}
.npop-tx .k{color:var(--plum,#7D5E68);padding-right:2.6rem}
.npop-tx h2{font-size:clamp(1.7rem,4.4vw,2.4rem);line-height:1.08;margin:.1rem 0 .2rem}
.npop-t{color:var(--muted,#7D7076);margin:0}
.npop-pr{margin:.3rem 0 0;background:var(--card,#FBF8F4);border-radius:14px;padding:.7em 1em;color:var(--deep,#4A3A41)}
.npop-cta{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem 1rem;margin-top:.8rem}
.npop-later{border:0;background:none;color:var(--muted,#7D7076);font:inherit;cursor:pointer;text-decoration:underline;text-underline-offset:3px;padding:.4em 0}
.npop-x{position:absolute;right:12px;top:12px;z-index:2;width:38px;height:38px;border-radius:50%;border:0;background:rgba(251,248,244,.92);color:var(--deep,#4A3A41);font-size:1rem;cursor:pointer}
</style>
<script>
(function(){
  var p=document.getElementById('npop');if(!p)return;
  var key='mv-npop-'+p.dataset.ph,force=!!p.dataset.force,seen=false;
  try{seen=sessionStorage.getItem(key)==='1'}catch(e){}
  if(seen&&!force)return;
  if(/[#&]anfrage/.test(location.hash)&&!force)return;
  function close(){p.classList.remove('on');setTimeout(function(){p.hidden=true},400);try{sessionStorage.setItem(key,'1')}catch(e){}document.removeEventListener('keydown',esc)}
  function esc(e){if(e.key==='Escape')close()}
  p.addEventListener('click',function(e){if(e.target===p||e.target.closest('.npop-x,.npop-later'))close()});
  p.querySelector('.npop-cta .btn').addEventListener('click',function(){try{sessionStorage.setItem(key,'1')}catch(e){}});
  setTimeout(function(){p.hidden=false;requestAnimationFrame(function(){p.classList.add('on')});document.addEventListener('keydown',esc)},force?300:3500);
})();
</script>
<?php
}

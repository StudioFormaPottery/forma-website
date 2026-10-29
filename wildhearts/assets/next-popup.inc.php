<?php
// Pop-up auf der Movecia-Hauptseite für das Angebot, das als Nächstes startet (Vivien, 28.10.2026):
// bis zum Start der Mindful Movement Method (launch) → Method, danach bis zum Unfold-Start (u_start) → Unfold,
// danach → Teacher Training (Warteliste). Einmal pro Browser-Sitzung, nach ein paar Sekunden.
// Vorschau: /?popup=method|unfold|teacher (zeigt es sofort, auch wenn es schon gesehen wurde).

function mv_next_popup_phase(): string
{
    if ((string)((mm_cfg('popup') ?? [])['mode'] ?? 'auto') === 'off') return ''; // Verwaltung → Movecia: Pop-up aus
    $c = mv_cfg();
    $now = time();
    if ($now < mv_ts($c['launch'])) return 'method';
    // Unfold nur, solange es noch offen ist und Plätze frei sind – sonst gleich das nächste Angebot (Vivien, 28.10.2026)
    if ($now < mv_ts($c['u_start'], '00:00') && (int)$c['u_open'] === 1) {
        $free = 1;
        try { $free = mv_unfold_free(forma_db()); } catch (Throwable $e) {}
        if ($free > 0) return 'unfold';
    }
    // Teacher Training (Movecia Academy) bis zum Start, danach nichts
    require_once dirname(__DIR__, 2) . '/api/academy-lib.php';
    $a = ac_cfg();
    return $a['open'] && $now < $a['start_ts'] ? 'teacher' : '';
}

function mv_next_popup(): void
{
    $c = mv_cfg();
    $gp = (string)($_GET['popup'] ?? ''); if ($gp === 'academy') $gp = 'teacher';
    $force = in_array($gp, ['method', 'unfold', 'teacher'], true) ? $gp : '';
    require_once dirname(__DIR__, 2) . '/api/academy-lib.php';
    try { $pdoA = forma_db(); ac_schema($pdoA); ac_tick($pdoA); } catch (Throwable $e) {} // Academy-Automatik auch über Startseiten-Aufrufe
    $ph = $force ?: mv_next_popup_phase();
    if ($ph === '') return;
    $eu = fn(int $x) => '€ ' . number_format($x / 100, 0, ',', '.');
    $ml = mv_ts($c['launch']);
    $us = mv_ts($c['u_start']);
    $short = function (int $t, string $l): string {
        $d = (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Vienna'));
        $m = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        return $l === 'en' ? $d->format('j F') : $d->format('j') . '. ' . $m[(int)$d->format('n')];
    };
    if ($ph === 'method') {
        $p = ['img' => '/img/movecia/fotos/ev-katze-kuh.jpg', 'pos' => '62% 55%', 'href' => '/method/',
            'k' => ['Mindful Movement Method · Start ' . $short($ml, 'de'), 'Mindful Movement Method · starts ' . $short($ml, 'en')],
            'h' => ['Jeden Tag ein paar <em>ruhige Minuten</em> für dich', 'A few <em>quiet minutes</em> for you, every day'],
            't' => ['Kurze Videos aus Yoga, Somatic Movement und Bodyweight, dazu Breathwork und Meditation. Du beginnst bei Monat 1 und gehst Schritt für Schritt weiter.', 'Short videos of yoga, somatic movement and bodyweight training, plus breathwork and meditation. You begin with month 1 and go step by step.'],
            'price' => $c['eb_m'] ? ['Early-Bird bis ' . $short($c['eb_m_end'], 'de') . ': ' . $eu($c['m1']) . ' statt ' . $eu($c['r_m1']) . ' im Monat', 'Early bird until ' . $short($c['eb_m_end'], 'en') . ': ' . $eu($c['m1']) . ' instead of ' . $eu($c['r_m1']) . ' a month'] : [$eu($c['m1']) . ' im Monat', $eu($c['m1']) . ' a month'],
            'btn' => ['Mehr erfahren', 'Find out more']];
    } elseif ($ph === 'unfold') {
        $p = ['img' => '/img/movecia/fotos/ev-unfold.jpg', 'pos' => '50% 22%', 'href' => '/unfold/',
            'k' => ['Unfold · Start ' . $short($us, 'de') . ' · nur ' . (int)$c['u_seats'] . ' Plätze', 'Unfold · starts ' . $short($us, 'en') . ' · only ' . (int)$c['u_seats'] . ' spots'],
            'h' => ['Unfold · <em>The Mindful Becoming</em>', 'Unfold · <em>The Mindful Becoming</em>'],
            't' => ['Drei Monate, in denen du zu dir zurückfindest: wöchentliche Live-Calls, Bewegungsvideos und Workbooks gegen den Mental Load.', 'Three months to come back to yourself: weekly live calls, movement videos and workbooks for your mental load.'],
            'price' => $c['eb_u'] ? ['Early-Bird bis ' . $short($c['eb_u_end'], 'de') . ': ' . $eu($c['u_once']) . ' statt ' . $eu($c['r_u_once']), 'Early bird until ' . $short($c['eb_u_end'], 'en') . ': ' . $eu($c['u_once']) . ' instead of ' . $eu($c['r_u_once'])] : [$eu($c['u_once']) . ' oder ' . (int)$c['u_n'] . ' × ' . $eu($c['u_inst']), $eu($c['u_once']) . ' or ' . (int)$c['u_n'] . ' × ' . $eu($c['u_inst'])],
            'btn' => ['Platz sichern', 'Save my spot']];
    } else {
        // Movecia Academy (Vivien, 28.10.2026): echte Seite /academy/teacher-training/, Preise aus ac_cfg(), nur Deutsch; ausgebucht → Warteliste
        $a = ac_cfg();
        $full = true; try { $full = ac_free(forma_db()) <= 0; } catch (Throwable $e) {}
        $eua = fn(int $x) => '€ ' . number_format($x / 100, 0, ',', '.');
        $kde = $full ? 'Movecia Academy · Warteliste' : ($a['eb'] ? 'Movecia Academy · Early Bird bis ' . $short($a['eb_end'], 'de') : 'Movecia Academy · nur 14 Plätze');
        $p = ['img' => '/img/movecia/fotos/ev-lunge.jpg', 'pos' => '42% 55%', 'href' => $full ? '/academy/teacher-training/#liste' : '/academy/teacher-training/',
            'k' => [$kde, $kde . ' · in German'],
            'h' => ['The Mindful Becoming <em>Teacher Training</em>', 'The Mindful Becoming <em>Teacher Training</em>'],
            't' => ['Andere begleiten und dabei bei dir selbst bleiben: ' . AC_HOURS . ' Stunden inklusive vollständiger 200-Stunden-Yogalehrerausbildung, 11 Monate hybrid, nur vierzehn Plätze.', 'A ' . AC_HOURS . '-hour Mindful Becoming Teacher Training including a full 200-hour yoga teacher training. Held in German only.'],
            'price' => $full ? null : ($a['eb'] ? ['Early Bird ' . $eua($a['eb_total']) . ' statt ' . $eua($a['r_total']), 'Early bird ' . $eua($a['eb_total']) . ' instead of ' . $eua($a['r_total'])] : [$eua($a['r_total']), $eua($a['r_total'])]),
            'btn' => $full ? ['Auf die Warteliste', 'Join the waiting list'] : ['Zur Ausbildung', 'See the training']];
    }
    $startTs = $ph === 'method' ? $ml : ($ph === 'unfold' ? $us : (int)ac_cfg()['start_ts']);
    // Schlüssel fürs „schon gesehen“: ändert sich mit Angebot und Preisstufe → neues Angebot oder Ende des Early-Bird zeigt das Pop-up wieder
    $ver = $ph . '-' . ($ph === 'method' ? (int)$c['eb_m'] : ($ph === 'unfold' ? (int)$c['eb_u'] : (int)ac_cfg()['eb']));
    // Drei Icons zum Angebot (Vivien, 29.10.2026: „mehr Icons, mehr Movement, mehr Weiblichkeit“)
    $ic = [
        'lotus' => '<path d="M12 19.5c-3.9 0-7-2.4-7.6-5.6 2 .1 3.9 1.1 5 2.8-.4-3.7.6-7.4 2.6-9.9 2 2.5 3 6.2 2.6 9.9 1.1-1.7 3-2.7 5-2.8-.6 3.2-3.7 5.6-7.6 5.6z"/>',
        'atem' => '<path d="M3 9h10.5a2.8 2.8 0 1 0-2.8-2.8M3 13h15a3 3 0 1 1-3 3M3 17h6"/>',
        'herz' => '<path d="M12 19.5s-7-4.3-7-9.6A3.9 3.9 0 0 1 12 7.6a3.9 3.9 0 0 1 7 2.3c0 5.3-7 9.6-7 9.6z"/>',
        'video' => '<rect x="3" y="6.5" width="12" height="11" rx="3"/><path d="M15 11l5.5-3v8L15 13"/>',
        'welle' => '<path d="M2.5 10c2.3-3 4.4-3 6.4 0s4.2 3 6.3 0 4.2-3 6.3 0M2.5 15.5c2.3-3 4.4-3 6.4 0s4.2 3 6.3 0 4.2-3 6.3 0"/>',
        'buch' => '<path d="M12 6.5C10 5 7 4.5 4 5.3v13c3-.8 6-.3 8 1.2 2-1.5 5-2 8-1.2v-13C17 4.5 14 5 12 6.5zM12 6.5v13"/>',
        'uhr' => '<circle cx="12" cy="12" r="8"/><path d="M12 7.5V12l3 2"/>',
        'laptop' => '<rect x="5" y="5.5" width="14" height="9.5" rx="2"/><path d="M3 18.5h18"/>',
        'kreis' => '<circle cx="8" cy="9" r="2.6"/><circle cx="16" cy="9" r="2.6"/><path d="M3.5 18.5c.8-3 3-4.4 4.5-4.4s3.7 1.4 4.5 4.4M11.5 18.5c.8-3 3-4.4 4.5-4.4s3.7 1.4 4.5 4.4"/>',
    ];
    $feat = $ph === 'method' ? [['lotus', 'Yoga & Somatic', 'Yoga & somatics'], ['atem', 'Breathwork', 'Breathwork'], ['herz', 'Schritt für Schritt', 'Step by step']]
        : ($ph === 'unfold' ? [['video', 'Live-Calls', 'Live calls'], ['welle', 'Bewegung', 'Movement'], ['buch', 'Workbooks', 'Workbooks']]
        : [['uhr', AC_HOURS . ' Stunden', AC_HOURS . ' hours'], ['laptop', '11 Monate hybrid', '11 months, hybrid'], ['kreis', 'Nur ' . (int)ac_cfg()['seats'] . ' Plätze', 'Only ' . (int)ac_cfg()['seats'] . ' spots']]);
    $svg = fn(string $k) => '<svg viewBox="0 0 24 24" aria-hidden="true">' . $ic[$k] . '</svg>';
    ?>
<!-- Pop-up „mit Bäm“ (Vivien, 29.10.2026: Box war „mega langweilig“, mehr Movement, Icons, Weiblichkeit, Zyklus, Jahreskreis):
     Pflaume-Seite mit treibenden Wellen, Foto im Torbogen, ringsum ein langsam kreisender Ring aus Mondphasen (heutige Phase leuchtet),
     rechts die heutige Mondphase und das Jahreskreisfest als kleine Chips, gezeichnete Welle unter dem Titel, drei Icons zum Angebot. -->
<div class="npop" id="npop" data-ph="<?= forma_h($ver) ?>" data-force="<?= $force ? '1' : '' ?>" data-start="<?= (int)$startTs ?>" role="dialog" aria-modal="true" aria-labelledby="npop-h" hidden>
  <div class="npop-stage">
    <div class="npop-card" tabindex="-1">
      <button class="npop-x" type="button" aria-label="Schließen / Close">✕</button>
      <div class="npop-side">
        <svg class="npop-waves" viewBox="0 0 1200 200" preserveAspectRatio="none" aria-hidden="true">
          <path class="w1" d="M0 120 C150 70 300 170 450 120 S750 70 900 120 S1150 170 1200 120 V200 H0Z"/>
          <path class="w2" d="M0 140 C200 100 400 180 600 140 S1000 100 1200 140 V200 H0Z"/>
          <path class="w3" d="M0 165 C150 140 300 190 450 165 S750 140 900 165 S1150 190 1200 165 V200 H0Z"/>
        </svg>
        <span class="npop-blob b1" aria-hidden="true"></span><span class="npop-blob b2" aria-hidden="true"></span>
        <span class="npop-spk s1" aria-hidden="true"></span><span class="npop-spk s2" aria-hidden="true"></span><span class="npop-spk s3" aria-hidden="true"></span>
        <div class="npop-orb">
          <div class="npop-ring" aria-hidden="true"></div>
          <div class="npop-pic">
            <div class="npop-ph" style="background-image:url(<?= forma_h($p['img']) ?>);background-position:<?= forma_h($p['pos']) ?>"></div>
          </div>
          <span class="npop-dot" aria-hidden="true"><?php if ($startTs): ?><small class="t1"></small> <b></b> <small class="t2"></small><?php else: ?><small>Nov</small> <b>2027</b><?php endif; ?></span>
        </div>
      </div>
      <div class="npop-tx">
        <div class="npop-now" aria-hidden="true"><span class="nm"></span><span class="ns"></span></div>
        <p class="k"><span class="de"><?= forma_h($p['k'][0]) ?></span><span class="en"><?= forma_h($p['k'][1]) ?></span></p>
        <h2 id="npop-h"><span class="de"><?= $p['h'][0] ?></span><span class="en"><?= $p['h'][1] ?></span></h2>
        <svg class="npop-line" viewBox="0 0 220 14" aria-hidden="true"><path d="M2 8 C 20 1, 38 1, 56 8 S 92 15, 110 8 S 146 1, 164 8 S 200 15, 218 7"/></svg>
        <p class="npop-t"><span class="de"><?= forma_h($p['t'][0]) ?></span><span class="en"><?= forma_h($p['t'][1]) ?></span></p>
        <ul class="npop-f">
          <?php foreach ($feat as $i => $f): ?><li style="--i:<?= $i ?>"><span class="fi"><?= $svg($f[0]) ?></span><span class="de"><?= forma_h($f[1]) ?></span><span class="en"><?= forma_h($f[2]) ?></span></li><?php endforeach; ?>
        </ul>
        <?php if ($p['price']): ?><p class="npop-pr"><span class="star" aria-hidden="true">✦</span><span class="de"><?= forma_h($p['price'][0]) ?></span><span class="en"><?= forma_h($p['price'][1]) ?></span></p><?php endif; ?>
        <div class="npop-cta"><a class="btn npop-go" href="<?= forma_h($p['href']) ?>"><span class="de"><?= forma_h($p['btn'][0]) ?></span><span class="en"><?= forma_h($p['btn'][1]) ?></span> <span class="ar">→</span></a><button class="npop-later" type="button"><span class="de">Später</span><span class="en">Maybe later</span></button></div>
      </div>
    </div>
  </div>
</div>
<style>
.npop{--pl:#7D5E68;--dp:#4A3A41;--cr:#FBF8F4;--pf:#E8D6CA;--bl:#EADFDE;position:fixed;inset:0;z-index:90;display:flex;overflow-y:auto;padding:24px 16px;background:rgba(46,37,41,.42);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);opacity:0;transition:opacity .45s}
.npop.on{opacity:1}
.npop[hidden]{display:none}
.npop-stage{position:relative;margin:auto;width:min(860px,100%);transform:translateY(26px) scale(.96);transition:transform .8s cubic-bezier(.2,1.2,.3,1)}
.npop.on .npop-stage{transform:none}
.npop-card{outline:0;position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);background:var(--cr);border-radius:32px;overflow:hidden;box-shadow:0 34px 80px rgba(46,37,41,.3)}
/* linke Seite: Pflaume, Wellen, Mondring */
.npop-side{position:relative;min-height:440px;display:grid;place-items:center;padding:42px 24px 64px;background:radial-gradient(120% 90% at 20% 0%,#9A7884 0%,var(--pl) 42%,var(--dp) 100%);overflow:hidden;isolation:isolate}
.npop-waves{position:absolute;left:0;bottom:0;width:200%;height:42%;z-index:-1}
.npop-waves path{fill:var(--cr)}
.npop-waves .w1{opacity:.08;animation:npw 16s linear infinite}
.npop-waves .w2{opacity:.12;animation:npw 11s linear infinite reverse}
.npop-waves .w3{opacity:.2;animation:npw 8s linear infinite}
@keyframes npw{to{transform:translateX(-50%)}}
.npop-blob{position:absolute;z-index:-1;border-radius:46% 54% 60% 40%/50% 42% 58% 50%;filter:blur(2px)}
.npop-blob.b1{width:220px;height:200px;left:-70px;top:-60px;background:rgba(232,214,202,.18);animation:npb 14s ease-in-out infinite alternate}
.npop-blob.b2{width:160px;height:150px;right:-50px;top:38%;background:rgba(234,223,222,.14);animation:npb 11s ease-in-out infinite alternate-reverse}
@keyframes npb{to{transform:translate(18px,24px) rotate(40deg);border-radius:58% 42% 38% 62%/44% 60% 40% 56%}}
.npop-spk{position:absolute;width:12px;height:12px;background:var(--pf);clip-path:polygon(50% 0,62% 38%,100% 50%,62% 62%,50% 100%,38% 62%,0 50%,38% 38%);opacity:0;animation:nps 3.6s ease-in-out infinite}
.npop-spk.s1{left:14%;top:16%}.npop-spk.s2{right:13%;top:24%;width:9px;height:9px;animation-delay:1.2s}.npop-spk.s3{left:20%;bottom:30%;width:8px;height:8px;animation-delay:2.3s}
@keyframes nps{0%,100%{opacity:0;transform:scale(.5) rotate(0)}50%{opacity:.9;transform:scale(1) rotate(45deg)}}
.npop-orb{--r:150px;position:relative;width:calc(var(--r)*2);height:calc(var(--r)*2);display:grid;place-items:center}
.npop-ring{position:absolute;inset:0;border-radius:50%;border:1px dashed rgba(251,248,244,.35);animation:npr 70s linear infinite}
.npop-ring .mp{position:absolute;left:50%;top:50%;width:26px;height:26px;margin:-13px;opacity:0;transform:rotate(var(--a)) translateY(calc(var(--r) * -1)) rotate(calc(var(--a) * -1));transition:opacity .5s calc(.5s + var(--n) * .08s)}
.npop.on .npop-ring .mp{opacity:1}
.npop-ring .mp svg{width:100%;height:100%;animation:npr 70s linear infinite reverse;overflow:visible}
.npop-ring .mp .o{fill:none;stroke:rgba(251,248,244,.55);stroke-width:1.2}
.npop-ring .mp .l{fill:rgba(251,248,244,.85)}
.npop-ring .mp.cur{width:38px;height:38px;margin:-19px}
.npop-ring .mp.cur .o{stroke:var(--pf);stroke-width:1.4}
.npop-ring .mp.cur .l{fill:var(--pf)}
.npop-ring .mp.cur svg{filter:drop-shadow(0 0 8px rgba(232,214,202,.9))}
@keyframes npr{to{rotate:360deg}}
.npop-pic{position:relative;width:62%;aspect-ratio:3/4.3}
.npop-pic::after{content:"";position:absolute;inset:-9px 9px 9px -9px;border:1.3px solid rgba(232,214,202,.75);border-radius:999px 999px 20px 20px;pointer-events:none}
.npop-ph{width:100%;height:100%;background-size:cover;border-radius:999px 999px 18px 18px;clip-path:inset(100% 0 0 0 round 999px 999px 18px 18px);transition:clip-path 1.1s cubic-bezier(.6,0,.2,1) .25s}
.npop.on .npop-ph{clip-path:inset(0 0 0 0 round 999px 999px 18px 18px)}
.npop-dot{position:absolute;left:50%;bottom:4px;transform:translate(-50%,50%);white-space:nowrap;display:inline-flex;align-items:baseline;gap:.3em;background:var(--cr);color:var(--dp);border-radius:999px;padding:.5em 1.1em;line-height:1;box-shadow:0 8px 20px rgba(46,37,41,.28)}
.npop-dot b{font-family:var(--serif);font-weight:500;font-size:1.25rem;color:var(--pl)}
.npop-dot small{font-size:.62rem;letter-spacing:.12em;text-transform:uppercase}
/* rechte Seite */
.npop-tx{position:relative;display:flex;flex-direction:column;gap:.6rem;min-width:0;padding:2.6rem 2.6rem 2.3rem 2.4rem}
.npop-now{display:flex;flex-wrap:wrap;gap:.4rem;padding-right:2.4rem}
.npop-now>span{display:inline-flex;align-items:center;gap:.4em;font-size:.72rem;letter-spacing:.04em;color:var(--dp);background:var(--bl);border-radius:999px;padding:.32em .8em .32em .45em}
.npop-now>span:empty{display:none}
.npop-now svg{width:18px;height:18px;flex:none}
.npop-now .ns svg{fill:none;stroke:var(--pl);stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
.npop-now .ns{background:#F2E6DA}
.npop-tx .k{margin:.2rem 0 0;font-size:.64rem;letter-spacing:.16em;text-transform:uppercase;color:var(--pl)}
.npop-tx h2{font-size:clamp(1.65rem,3.2vw,2.2rem);line-height:1.08;margin:0}
.npop-tx h2 em{color:var(--pl)}
.npop-line{width:150px;height:14px;margin:-.1rem 0 .1rem}
.npop-line path{fill:none;stroke:var(--pl);stroke-width:2;stroke-linecap:round;stroke-dasharray:260;stroke-dashoffset:260;transition:stroke-dashoffset 1.4s ease .9s}
.npop.on .npop-line path{stroke-dashoffset:0}
.npop-t{color:var(--muted,#7D7076);margin:0;font-size:.92rem;line-height:1.6}
.npop-f{list-style:none;margin:.35rem 0 0;padding:0;display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem}
.npop-f li{display:flex;flex-direction:column;align-items:center;gap:.4rem;text-align:center;font-size:.76rem;line-height:1.25;color:var(--dp);opacity:0;transform:translateY(10px);transition:opacity .5s calc(1s + var(--i) * .15s),transform .6s cubic-bezier(.2,1.4,.3,1) calc(1s + var(--i) * .15s)}
.npop.on .npop-f li{opacity:1;transform:none}
.npop-f .fi{width:48px;height:48px;display:grid;place-items:center;border-radius:46% 54% 58% 42%/52% 44% 56% 48%;background:var(--bl);animation:npi 6s ease-in-out infinite alternate}
.npop-f li:nth-child(2) .fi{background:var(--pf);animation-delay:-2s}
.npop-f li:nth-child(3) .fi{background:#E4D6C8;animation-delay:-4s}
@keyframes npi{to{border-radius:58% 42% 44% 56%/42% 58% 42% 58%;transform:translateY(-3px)}}
.npop-f svg{width:24px;height:24px;fill:none;stroke:var(--dp);stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
.npop-pr{margin:.4rem 0 0;align-self:flex-start;display:inline-flex;gap:.5em;align-items:center;background:var(--dp);border-radius:999px;padding:.5em 1.15em;color:var(--cr);font-size:.85rem}
.npop-pr .star{color:var(--pf);animation:nps 3s ease-in-out infinite;opacity:1}
.npop-cta{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1.1rem;margin-top:.6rem}
.npop-go{position:relative;overflow:hidden;font-size:.95rem}
.npop-go::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:40%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);transform:skewX(-18deg);animation:npsh 4.5s ease-in-out 2s infinite}
@keyframes npsh{0%{left:-60%}35%,100%{left:130%}}
.npop-go .ar{display:inline-block;transition:transform .3s}
.npop-go:hover .ar{transform:translateX(5px)}
.npop-later{border:0;background:none;color:var(--muted,#7D7076);font:inherit;font-size:.88rem;cursor:pointer;text-decoration:underline;text-underline-offset:3px;padding:.4em 0}
.npop-x{position:absolute;right:16px;top:16px;z-index:3;width:36px;height:36px;border-radius:50%;border:1px solid #E6DCD2;background:#fff;color:var(--dp);font-size:.85rem;cursor:pointer;transition:transform .3s}
.npop-x:hover{transform:rotate(90deg)}
@media(max-width:759px){
  .npop{padding:14px 12px}
  .npop-stage{width:min(420px,100%)}
  .npop-card{grid-template-columns:1fr}
  .npop-side{min-height:0;padding:40px 16px 46px}
  .npop-orb{--r:min(118px,32vw)}
  .npop-ring .mp{width:20px;height:20px;margin:-10px}
  .npop-ring .mp.cur{width:30px;height:30px;margin:-15px}
  .npop-tx{padding:1.6rem 1.3rem 1.5rem;align-items:center;text-align:center;gap:.5rem}
  .npop-now{justify-content:center;padding-right:0}
  .npop-line{align-self:center}
  .npop-pr{align-self:center}
  .npop-cta{justify-content:center}
  .npop-x{right:12px;top:12px;background:rgba(255,255,255,.92)}
}
@media(prefers-reduced-motion:reduce){
  .npop-stage,.npop-ph,.npop-line path,.npop-f li,.npop-ring .mp{transition:none}
  .npop-waves path,.npop-blob,.npop-spk,.npop-ring,.npop-ring .mp svg,.npop-f .fi,.npop-go::after,.npop-pr .star{animation:none}
}
</style>
<script>
(function(){
  var p=document.getElementById('npop');if(!p)return;
  var key='mv-npop-'+p.dataset.ph,force=!!p.dataset.force,seen=false;
  // Countdown, immer aus der echten Startzeit gerechnet
  var st=+p.dataset.start||0,dot=p.querySelector('.npop-dot');
  function en(){return document.documentElement.dataset.l==='en'}
  function fill(){if(!st||!dot)return;var E=en();
    var d0=new Date();d0.setHours(0,0,0,0);var s=new Date(st*1000);s.setHours(0,0,0,0);var n=Math.round((s-d0)/864e5);
    var b=dot.querySelector('b'),t1=dot.querySelector('.t1'),t2=dot.querySelector('.t2');
    if(n<=0){b.textContent=E?'Today':'Heute';t1.textContent=E?'starts':'Start';t2.textContent='';}
    else if(n===1){b.textContent=E?'Tomorrow':'Morgen';t1.textContent='Start';t2.textContent='';}
    else{b.textContent=n;t1.textContent=E?'in':'in';t2.textContent=E?'days':'Tagen';}}
  // Mondphase (0 = Neumond … 0.5 = Vollmond), aus einem bekannten Neumond gerechnet
  var age=((Date.now()-Date.UTC(2000,0,6,18,14))/864e5)%29.530588853;if(age<0)age+=29.530588853;var ph=age/29.530588853;
  function moon(f){var r=8,c=Math.cos(2*Math.PI*f),rx=Math.abs(r*c).toFixed(2),d;
    if(f<.5)d='M12 4A8 8 0 0 1 12 20A'+rx+' 8 0 0 '+(f<.25?0:1)+' 12 4Z';
    else d='M12 4A8 8 0 0 0 12 20A'+rx+' 8 0 0 '+(f<.75?0:1)+' 12 4Z';
    if(f<.02||f>.98)d='';
    return '<svg viewBox="0 0 24 24"><circle class="o" cx="12" cy="12" r="8"/>'+(d?'<path class="l" d="'+d+'"/>':'')+'</svg>';}
  var ring=p.querySelector('.npop-ring'),cur=Math.round(ph*8)%8,h='';
  for(var i=0;i<8;i++)h+='<span class="mp'+(i===cur?' cur':'')+'" style="--a:'+(i*45)+'deg;--n:'+i+'">'+moon(i===cur?ph:i/8)+'</span>';
  ring.innerHTML=h;
  var MD=['Neumond','zunehmende Sichel','erstes Viertel','zunehmender Mond','Vollmond','abnehmender Mond','letztes Viertel','abnehmende Sichel'],
      ME=['New moon','Waxing crescent','First quarter','Waxing gibbous','Full moon','Waning gibbous','Last quarter','Waning crescent'];
  // Jahreskreis: aktuelles bzw. nächstes Fest (±10 Tage = „Zeit von …“), sonst die Jahreszeit auf dem Weg dorthin
  var I={kerze:'<path d="M12 3c1.6 2 1.6 3.4 0 4.6-1.6-1.2-1.6-2.6 0-4.6zM9.5 10h5v10h-5zM7 20h10"/>',keim:'<path d="M12 20v-8M12 12c0-3.5-2.5-6-6-6 0 3.5 2.5 6 6 6zM12 14c0-3 2.2-5.5 5.5-5.5 0 3-2.4 5.5-5.5 5.5zM8 20h8"/>',
    blume:'<circle cx="12" cy="9" r="2"/><path d="M12 5a2.4 2.4 0 1 1 3.2 2.8M15.2 10.2A2.4 2.4 0 1 1 12 13M8.8 10.2A2.4 2.4 0 1 1 8.8 6.6M12 13v7M12 17c2-2 4-2 5-1"/>',
    sonne:'<circle cx="12" cy="12" r="3.6"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/>',
    aehre:'<path d="M12 21V8M12 8c-1.5-1-1.5-3 0-4.5 1.5 1.5 1.5 3.5 0 4.5zM12 12c-2 0-3.5-1.2-4-3 2 0 3.5 1.2 4 3zM12 12c2 0 3.5-1.2 4-3-2 0-3.5 1.2-4 3zM12 16c-2 0-3.5-1.2-4-3 2 0 3.5 1.2 4 3zM12 16c2 0 3.5-1.2 4-3-2 0-3.5 1.2-4 3z"/>',
    blatt:'<path d="M5 19c0-8 5-13 14-14 0 9-5 14-13 14zM5 19l7-7"/>',
    laterne:'<path d="M10 4h4M9 6.5h6l1 3v7l-1 2.5H9L8 16.5v-7zM12 10c1 1 1 2.5 0 3.5-1-1-1-2.5 0-3.5zM12 4V2.5"/>',
    stern:'<path d="M12 3l1.9 5.6H20l-4.9 3.6 1.9 5.8L12 14.4 7 18l1.9-5.8L4 8.6h6.1z"/>'};
  var F=[[2,1,'Imbolc','Imbolc','kerze'],[3,20,'Ostara','Ostara','keim'],[5,1,'Beltane','Beltane','blume'],[6,21,'Litha','Litha','sonne'],[8,1,'Lughnasadh','Lughnasadh','aehre'],[9,22,'Mabon','Mabon','blatt'],[10,31,'Samhain','Samhain','laterne'],[12,21,'Yule','Yule','stern']];
  var now=new Date(),y=now.getFullYear(),best=null,next=null;
  [y-1,y,y+1].forEach(function(yy){F.forEach(function(f){var d=new Date(yy,f[0]-1,f[1]),dd=(d-now)/864e5;
    if(Math.abs(dd)<=10&&(!best||Math.abs(dd)<Math.abs(best.dd)))best={f:f,dd:dd};
    if(dd>0&&(!next||dd<next.dd))next={f:f,dd:dd};});});
  var seas=['Winter','Winter','Frühling','Frühling','Frühling','Sommer','Sommer','Sommer','Herbst','Herbst','Herbst','Winter'],
      seasE=['Winter','Winter','Spring','Spring','Spring','Summer','Summer','Summer','Autumn','Autumn','Autumn','Winter'];
  function chips(){var E=en(),m=p.querySelector('.npop-now .nm'),s=p.querySelector('.npop-now .ns');
    m.innerHTML=moon(ph).replace('<svg','<svg style="--x:0"').replace('class="o"','class="o" style="fill:none;stroke:#7D5E68;stroke-width:1.4"').replace('class="l"','class="l" style="fill:#7D5E68"')+(E?ME[cur]:MD[cur]);
    var f=best?best.f:next.f,t=best?(E?'Season of '+f[3]:'Zeit von '+f[2]):(E?seasE[now.getMonth()]+' · on the way to '+f[3]:seas[now.getMonth()]+' · auf dem Weg zu '+f[2]);
    s.innerHTML='<svg viewBox="0 0 24 24">'+I[f[4]]+'</svg>'+t;}
  fill();chips();document.addEventListener('mvlang',function(){fill();chips()});
  try{seen=sessionStorage.getItem(key)==='1'}catch(e){}
  if(seen&&!force)return;
  if(/[#&]anfrage/.test(location.hash)&&!force)return;
  function close(){p.classList.remove('on');setTimeout(function(){p.hidden=true},450);try{sessionStorage.setItem(key,'1')}catch(e){}document.removeEventListener('keydown',esc)}
  function esc(e){if(e.key==='Escape')close()}
  p.addEventListener('click',function(e){if(e.target===p||e.target.closest('.npop-x,.npop-later'))close()});
  p.querySelector('.npop-cta .btn').addEventListener('click',function(){try{sessionStorage.setItem(key,'1')}catch(e){}});
  function trap(e){if(e.key!=='Tab')return;var f=p.querySelectorAll('a[href],button'),a=f[0],z=f[f.length-1];if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus()}else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus()}}
  document.addEventListener('keydown',function(e){if(!p.hidden)trap(e)});
  setTimeout(function(){p.hidden=false;requestAnimationFrame(function(){requestAnimationFrame(function(){p.classList.add('on');p.querySelector('.npop-card').focus({preventScroll:true})})});document.addEventListener('keydown',esc)},force?300:3500);
})();
</script>
<?php
}

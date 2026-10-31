<?php
// Pop-up auf der Movecia-Hauptseite (Vivien, 28.10.2026, Wechsel seit 30.10.2026):
// alle Angebote, die gerade buchbar sind, in fester Reihenfolge: The Mindful Movement Method (bis zum Start),
// Unfold (bis zum Start, solange offen und Plätze frei), Teacher Training (Movecia Academy, bis zum Start).
// Wer öfter kommt, sieht bei jedem Besuch (Browser-Sitzung) das nächste: Zähler im Cookie mvpop (1 Jahr).
// Einmal pro Browser-Sitzung, nach ein paar Sekunden. Vorschau: /?popup=method|unfold|teacher.

/** Alle gerade aktiven Pop-up-Angebote in Reihenfolge (leer = Pop-up aus). */
function mv_next_popup_phases(): array
{
    if ((string)((mm_cfg('popup') ?? [])['mode'] ?? 'auto') === 'off') return []; // Verwaltung → Movecia: Pop-up aus
    $c = mv_cfg();
    $now = time();
    $out = [];
    if ($now < mv_ts($c['launch'])) $out[] = 'method';
    if ($now < mv_ts($c['u_start'], '00:00') && (int)$c['u_open'] === 1) {
        $free = 1;
        try { $free = mv_unfold_free(forma_db()); } catch (Throwable $e) {}
        if ($free > 0) $out[] = 'unfold';
    }
    require_once dirname(__DIR__, 2) . '/api/academy-lib.php';
    $a = ac_cfg();
    if ($a['open'] && $now < $a['start_ts']) $out[] = 'teacher';
    return $out;
}

/** Das Angebot, das dieser Besuch zeigt (Verwaltung zeigt das erste). */
function mv_next_popup_phase(bool $rotate = false): string
{
    $l = mv_next_popup_phases();
    if (!$l) return '';
    if (!$rotate) return $l[0];
    $n = max(0, (int)($_COOKIE['mvpop'] ?? 0));
    return $l[$n % count($l)];
}

function mv_next_popup(): void
{
    $c = mv_cfg();
    $gp = (string)($_GET['popup'] ?? ''); if ($gp === 'academy') $gp = 'teacher';
    $force = in_array($gp, ['method', 'unfold', 'teacher'], true) ? $gp : '';
    require_once dirname(__DIR__, 2) . '/api/academy-lib.php';
    try { $pdoA = forma_db(); ac_schema($pdoA); ac_tick($pdoA); } catch (Throwable $e) {} // Academy-Automatik auch über Startseiten-Aufrufe
    $ph = $force ?: mv_next_popup_phase(true);
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
        $p = ['img' => '/img/movecia/fotos/ev-ueber-mich.jpg', 'pos' => '45% 55%', 'href' => '/method/',
            'k' => ['The Mindful Movement Method · Start ' . $short($ml, 'de'), 'The Mindful Movement Method · starts ' . $short($ml, 'en')],
            'h' => ['Jeden Tag ein paar <em>ruhige Minuten</em> für dich', 'A few <em>quiet minutes</em> for you, every day'],
            't' => ['Kurze Videos aus Yoga, Somatic Movement und Bodyweight, dazu Breathwork und Meditation. Du beginnst bei Monat 1 und gehst Schritt für Schritt weiter.', 'Short videos of yoga, somatic movement and bodyweight training, plus breathwork and meditation. You begin with month 1 and go step by step.'],
            'price' => $c['eb_m'] ? ['Early-Bird bis ' . $short($c['eb_m_end'], 'de') . ': ' . $eu($c['m1']) . ' statt ' . $eu($c['r_m1']) . ' im Monat', 'Early bird until ' . $short($c['eb_m_end'], 'en') . ': ' . $eu($c['m1']) . ' instead of ' . $eu($c['r_m1']) . ' a month'] : [$eu($c['m1']) . ' im Monat', $eu($c['m1']) . ' a month'],
            'btn' => ['Mehr erfahren', 'Find out more'],
            'tag' => ['Mitgliedschaft · On-Demand · Video-Mediathek', 'Membership · on demand · video library']];
    } elseif ($ph === 'unfold') {
        $p = ['img' => '/img/movecia/fotos/ev-unfold.jpg', 'pos' => '50% 22%', 'href' => '/unfold/',
            'k' => ['Unfold · Start ' . $short($us, 'de') . ' · nur ' . (int)$c['u_seats'] . ' Plätze', 'Unfold · starts ' . $short($us, 'en') . ' · only ' . (int)$c['u_seats'] . ' spots'],
            'h' => ['Unfold · <em>The Mindful Becoming</em>', 'Unfold · <em>The Mindful Becoming</em>'],
            't' => ['Drei Monate, in denen du zu dir zurückfindest: wöchentliche Live-Calls, Bewegungsvideos und Workbooks gegen den Mental Load.', 'Three months to come back to yourself: weekly live calls, movement videos and workbooks for your mental load.'],
            'price' => $c['eb_u'] ? ['Early-Bird bis ' . $short($c['eb_u_end'], 'de') . ': ' . $eu($c['u_once']) . ' statt ' . $eu($c['r_u_once']), 'Early bird until ' . $short($c['eb_u_end'], 'en') . ': ' . $eu($c['u_once']) . ' instead of ' . $eu($c['r_u_once'])] : [$eu($c['u_once']) . ' oder ' . (int)$c['u_n'] . ' × ' . $eu($c['u_inst']), $eu($c['u_once']) . ' or ' . (int)$c['u_n'] . ' × ' . $eu($c['u_inst'])],
            'btn' => ['Platz sichern', 'Save my spot'],
            'tag' => ['3 Monate Begleitung · live & online', '3 months of guidance · live & online']];
    } else {
        // Movecia Academy (Vivien, 28.10.2026): echte Seite /academy/teacher-training/, Preise aus ac_cfg(), nur Deutsch; ausgebucht → Warteliste
        $a = ac_cfg();
        $full = true; try { $full = ac_free(forma_db()) <= 0; } catch (Throwable $e) {}
        $eua = fn(int $x) => '€ ' . number_format($x / 100, 0, ',', '.');
        $kde = $full ? 'Movecia Academy · Warteliste' : ($a['eb'] ? 'Movecia Academy · Early Bird bis ' . $short($a['eb_end'], 'de') : 'Movecia Academy · nur 14 Plätze');
        $p = ['img' => '/img/movecia/fotos/ev-lunge.jpg', 'pos' => '42% 55%', 'href' => $full ? '/academy/teacher-training/#liste' : '/academy/teacher-training/',
            'k' => [$kde, $kde . ' · in German'],
            'h' => ['The Mindful Becoming <em>Teacher Training</em>', 'The Mindful Becoming <em>Teacher Training</em>'],
            'tag' => ['Ausbildung · ' . AC_HOURS . ' Stunden · 11 Monate', 'Teacher training · ' . AC_HOURS . ' hours · 11 months'],
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
    $ebNow = $ph === 'method' ? (bool)$c['eb_m'] : ($ph === 'unfold' ? (bool)$c['eb_u'] : (bool)ac_cfg()['eb']);
    $birds = $ebNow ? mv_perch_birds('l s') : '';
    ?>
<!-- Pop-up „mit Bäm“ (Vivien, 29.10.2026: Box war „mega langweilig“, mehr Movement, Icons, Weiblichkeit, Zyklus, Jahreskreis):
     Pflaume-Seite mit treibenden Wellen, Foto im Torbogen, ringsum ein langsam kreisender Ring aus Mondphasen (heutige Phase leuchtet),
     gezeichnete Welle unter dem Titel, drei Icons zum Angebot. -->
<div class="npop npop-<?= forma_h($ph) ?>" id="npop" data-ph="<?= forma_h($ver) ?>" data-force="<?= $force ? '1' : '' ?>" data-start="<?= (int)$startTs ?>" role="dialog" aria-modal="true" aria-labelledby="npop-h" hidden>
  <div class="npop-stage">
    <div class="npop-card" tabindex="-1">
      <button class="npop-x" type="button" aria-label="Schließen / Close">✕</button>
      <div class="npop-side">
        <div class="npop-orb">
          <?php if ($ph === 'teacher'): ?><span class="npop-stamp" aria-hidden="true"><i>Movecia<br>Academy</i><b>2027</b></span><?php endif; ?>
          <div class="npop-pic">
            <div class="npop-ph" style="background-image:url(<?= forma_h($p['img']) ?>);background-position:<?= forma_h($p['pos']) ?>"></div>
          </div>
          <span class="npop-dot" aria-hidden="true"><?php if ($startTs): ?><small class="t1"></small> <b></b> <small class="t2"></small><?php else: ?><small>Nov</small> <b>2027</b><?php endif; ?></span>
        </div>
      </div>
      <div class="npop-tx">
        <p class="npop-tag"><span class="de"><?= forma_h($p['tag'][0]) ?></span><span class="en"><?= forma_h($p['tag'][1]) ?></span></p>
        <p class="k"><span class="de"><?= forma_h($p['k'][0]) ?></span><span class="en"><?= forma_h($p['k'][1]) ?></span></p>
        <h2 id="npop-h"><span class="de"><?= $p['h'][0] ?></span><span class="en"><?= $p['h'][1] ?></span></h2>
        <svg class="npop-line" viewBox="0 0 220 14" aria-hidden="true"><path d="M2 8 C 20 1, 38 1, 56 8 S 92 15, 110 8 S 146 1, 164 8 S 200 15, 218 7"/></svg>
        <p class="npop-t"><span class="de"><?= forma_h($p['t'][0]) ?></span><span class="en"><?= forma_h($p['t'][1]) ?></span></p>
        <ul class="npop-f">
          <?php foreach ($feat as $i => $f): ?><li style="--i:<?= $i ?>"><span class="fi"><?= $svg($f[0]) ?></span><span class="de"><?= forma_h($f[1]) ?></span><span class="en"><?= forma_h($f[2]) ?></span></li><?php endforeach; ?>
        </ul>
        <?php if ($p['price']): ?><p class="npop-pr<?= $birds ? ' has-b' : '' ?>"><?= $birds ?><span class="star" aria-hidden="true">✦</span><span class="de"><?= forma_h($p['price'][0]) ?></span><span class="en"><?= forma_h($p['price'][1]) ?></span></p><?php endif; ?>
        <div class="npop-cta"><a class="btn npop-go" href="<?= forma_h($p['href']) ?>"><span class="de"><?= forma_h($p['btn'][0]) ?></span><span class="en"><?= forma_h($p['btn'][1]) ?></span> <span class="ar">→</span></a><button class="npop-later" type="button"><span class="de">Später</span><span class="en">Maybe later</span></button></div>
      </div>
    </div>
  </div>
</div>
<style>
.npop{--pl:#4A3040;--dp:#2E1D27;--cr:#FBF9F6;--pf:#E6DBCF;--bl:#E4DCD1;position:fixed;inset:0;z-index:90;display:flex;overflow-y:auto;padding:24px 16px;background:rgba(46,37,41,.42);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);opacity:0;transition:opacity .45s}
.npop.on{opacity:1}
.npop[hidden]{display:none}
.npop-stage{position:relative;margin:auto;width:min(860px,100%);transform:translateY(26px) scale(.96);transition:transform .8s cubic-bezier(.2,1.2,.3,1)}
.npop.on .npop-stage{transform:none}
.npop-card{outline:0;position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);background:var(--cr);border-radius:32px;overflow:hidden;box-shadow:0 34px 80px rgba(46,37,41,.3)}
/* linke Seite: Pflaume mit Foto im Bogen (Vivien, 29.10.2026: Wellen + Mondring waren zu viel) */
.npop-side{position:relative;min-height:520px;display:block;padding:0;background:radial-gradient(120% 90% at 20% 0%,#9A7884 0%,var(--pl) 42%,var(--dp) 100%);overflow:hidden;isolation:isolate}
.npop-orb{position:absolute;inset:0}
.npop-pic{position:absolute;inset:0}
.npop-pic::after{content:"";position:absolute;inset:auto 0 0 0;height:30%;background:linear-gradient(to top,rgba(46,37,41,.38),transparent);pointer-events:none}
.npop-ph{width:100%;height:100%;background-size:cover;transform:scale(1.06);transition:transform 1.6s cubic-bezier(.2,.8,.2,1)}
.npop.on .npop-ph{transform:none}
.npop-dot{position:absolute;left:50%;bottom:22px;transform:translateX(-50%);white-space:nowrap;display:inline-flex;align-items:baseline;gap:.3em;background:var(--cr);color:var(--dp);border-radius:999px;padding:.5em 1.1em;line-height:1;box-shadow:0 8px 20px rgba(46,37,41,.28)}
.npop-dot b{font-family:var(--serif);font-weight:500;font-size:1.25rem;color:var(--pl)}
.npop-dot small{font-size:.62rem;letter-spacing:.12em;text-transform:uppercase}
/* rechte Seite */
.npop-tx{position:relative;display:flex;flex-direction:column;gap:.6rem;min-width:0;padding:2.6rem 2.6rem 2.3rem 2.4rem}
.npop-tx .k{margin:.2rem 0 0;font-size:.64rem;letter-spacing:.16em;text-transform:uppercase;color:var(--pl)}
.npop-tx h2{font-size:clamp(1.65rem,3.2vw,2.2rem);line-height:1.08;margin:0}
.npop-tx h2 em{color:var(--pl)}
.npop-line{width:150px;height:14px;margin:-.1rem 0 .1rem}
.npop-line path{fill:none;stroke:var(--pl);stroke-width:2;stroke-linecap:round;stroke-dasharray:260;stroke-dashoffset:260;transition:stroke-dashoffset 1.4s ease .9s}
.npop.on .npop-line path{stroke-dashoffset:0}
.npop-t{color:var(--muted,#6E6168);margin:0;font-size:.92rem;line-height:1.6}
.npop-f{list-style:none;margin:.35rem 0 0;padding:0;display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem}
.npop-f li{display:flex;flex-direction:column;align-items:center;gap:.4rem;text-align:center;font-size:.76rem;line-height:1.25;color:var(--dp);opacity:0;transform:translateY(10px);transition:opacity .5s calc(1s + var(--i) * .15s),transform .6s cubic-bezier(.2,1.4,.3,1) calc(1s + var(--i) * .15s)}
.npop.on .npop-f li{opacity:1;transform:none}
.npop-f .fi{width:48px;height:48px;display:grid;place-items:center;border-radius:46% 54% 58% 42%/52% 44% 56% 48%;background:var(--bl);animation:npi 6s ease-in-out infinite alternate}
.npop-f li:nth-child(2) .fi{background:var(--pf);animation-delay:-2s}
.npop-f li:nth-child(3) .fi{background:#E4D6C8;animation-delay:-4s}
@keyframes npi{to{border-radius:58% 42% 44% 56%/42% 58% 42% 58%;transform:translateY(-3px)}}
.npop-f svg{width:24px;height:24px;fill:none;stroke:var(--dp);stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
.npop-pr{margin:.4rem 0 0;align-self:flex-start;display:inline-flex;gap:.5em;align-items:center;background:var(--dp);border-radius:999px;padding:.5em 1.15em;color:var(--cr);font-size:.85rem}
.npop-pr .star{color:var(--pf)}
.npop-pr{position:relative}.npop-pr.has-b{margin-top:1.6rem}
.npop-cta{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1.1rem;margin-top:.6rem}
.npop-go{position:relative;overflow:hidden;font-size:.95rem}
.npop-go::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:40%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);transform:skewX(-18deg);animation:npsh 4.5s ease-in-out 2s infinite}
.npop-go .ar{display:inline-block;transition:transform .3s}
.npop-go:hover .ar{transform:translateX(5px)}
.npop-later{border:0;background:none;color:var(--muted,#6E6168);font:inherit;font-size:.88rem;cursor:pointer;text-decoration:underline;text-underline-offset:3px;padding:.4em 0}
.npop-x{position:absolute;right:16px;top:16px;z-index:3;width:36px;height:36px;border-radius:50%;border:1px solid #DED5C9;background:#fff;color:var(--dp);font-size:.85rem;cursor:pointer;transition:transform .3s}
.npop-x:hover{transform:rotate(90deg)}
@media(max-width:759px){
  .npop{padding:14px 12px}
  .npop-stage{width:min(420px,100%)}
  .npop-card{grid-template-columns:1fr}
  .npop-side{min-height:0;height:min(44vh,340px)}
  .npop-dot{left:14px;top:14px;bottom:auto;transform:none}
  .npop-tx{padding:1.6rem 1.3rem 1.5rem;align-items:center;text-align:center;gap:.5rem}
  .npop-line{align-self:center}
  .npop-pr{align-self:center}
  .npop-pr .mvb.l{left:50%;transform:translateX(-50%)}
  .npop-cta{justify-content:center}
  .npop-x{right:12px;top:12px;background:rgba(255,255,255,.92)}
}
@media(prefers-reduced-motion:reduce){
  .npop-stage,.npop-ph,.npop-line path,.npop-f li{transition:none}
}
/* Jedes Angebot eine eigene Form (Vivien, 31.10.2026: „man kann nicht erkennen, dass das ein Unterschied ist“):
   The Method = klares Quadrat, Unfold = Kiesel wie im Logo (mit versetzter Linie), Teacher Training = alte Postkarte
   mit gewelltem Rand in Dunkellila. */
.npop-tag{margin:0;align-self:flex-start;font-size:.78rem;letter-spacing:.06em;font-weight:600;padding:.45em 1em;border-radius:999px;background:var(--bl);color:var(--dp)}
/* The Method: Quadrat */
.npop-method .npop-card{border-radius:6px}
.npop-method .npop-tag{border-radius:4px;background:var(--dp);color:var(--cr)}
/* Unfold: Kiesel */
.npop-unfold .npop-stage{padding:14px 18px 0 0}
.npop-unfold .npop-card{border-radius:200px 90px 220px 110px/150px 180px 110px 200px;overflow:visible;background:#F3ECE4}
.npop-unfold .npop-card::before{content:"";position:absolute;inset:0;border:2px solid var(--pl);border-radius:inherit;transform:translate(14px,-12px);pointer-events:none}
.npop-unfold .npop-side{border-radius:200px 0 0 110px/150px 0 0 200px;background:var(--pf)}
.npop-unfold .npop-tx{padding:3rem 3.6rem 2.8rem 2.4rem}
.npop-unfold .npop-pr,.npop-method .npop-pr{max-width:100%}
.npop-unfold .npop-tag{background:var(--pl);color:var(--cr);font-size:.86rem}
.npop-unfold .npop-x{right:46px;top:30px}
/* Teacher Training: Postkarte */
.npop-teacher .npop-stage{filter:drop-shadow(0 30px 50px rgba(20,8,16,.45))}
.npop-teacher .npop-card{--r:11px;border-radius:0;box-shadow:none;background:radial-gradient(130% 120% at 0% 0%,#5E3B50 0%,var(--pl) 45%,var(--dp) 100%);color:var(--cr);
  -webkit-mask:radial-gradient(var(--r) at var(--r) var(--r),#000 calc(100% - 1px),#0000) 0 0/calc(2*var(--r)) calc(2*var(--r)) round,linear-gradient(#000 0 0) 50%/calc(100% - 2*var(--r)) calc(100% - 2*var(--r)) no-repeat;
  mask:radial-gradient(var(--r) at var(--r) var(--r),#000 calc(100% - 1px),#0000) 0 0/calc(2*var(--r)) calc(2*var(--r)) round,linear-gradient(#000 0 0) 50%/calc(100% - 2*var(--r)) calc(100% - 2*var(--r)) no-repeat}
.npop-teacher .npop-card::after{content:"";position:absolute;inset:22px;border:1px dashed rgba(247,241,238,.35);pointer-events:none}
.npop-teacher .npop-side{background:none;padding:40px 10px 40px 40px;min-height:540px;overflow:visible}
.npop-teacher .npop-orb{position:relative;inset:auto;height:100%}
.npop-teacher .npop-pic{inset:0;background:var(--cr);padding:12px 12px 46px;transform:rotate(-2.5deg);box-shadow:0 14px 30px rgba(0,0,0,.35)}
.npop-teacher .npop-pic::after{display:none}
.npop-teacher .npop-ph{height:100%}
.npop-teacher .npop-dot{bottom:12px;background:none;box-shadow:none;color:var(--dp);transform:translateX(-50%) rotate(-2.5deg)}
.npop-teacher .npop-tx{padding:3.2rem 3rem 2.6rem 2.2rem;color:var(--cr)}
.npop-teacher .npop-tx h2{font-size:clamp(2.3rem,4.4vw,3.3rem);line-height:1.02;color:var(--cr)}
.npop-teacher .npop-tx h2 em{color:#F0C9B8;font-style:italic}
.npop-teacher .npop-tx .k{color:#E9C4B4}
.npop-teacher .npop-tag{background:#F0C9B8;color:var(--dp)}
.npop-teacher .npop-t{color:rgba(247,241,238,.86)}
.npop-teacher .npop-line path{stroke:#F0C9B8}
.npop-teacher .npop-f li{color:var(--cr)}
.npop-teacher .npop-f .fi,.npop-teacher .npop-f li:nth-child(2) .fi,.npop-teacher .npop-f li:nth-child(3) .fi{background:rgba(247,241,238,.14)}
.npop-teacher .npop-f svg{stroke:var(--cr)}
.npop-teacher .npop-pr{background:var(--cr);color:var(--dp)}
.npop-teacher .npop-pr .star{color:var(--pl)}
.npop-teacher .npop-go{background:#F0C9B8;color:var(--dp);border-color:#F0C9B8}
.npop-teacher .npop-later{color:rgba(247,241,238,.75)}
.npop-teacher .npop-x{background:rgba(247,241,238,.14);border-color:rgba(247,241,238,.35);color:var(--cr);right:34px;top:30px}
.npop-stamp{position:absolute;z-index:4;right:-16px;top:-14px;width:84px;height:100px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;text-align:center;background:#F0C9B8;color:var(--dp);transform:rotate(6deg);
  --q:5px;-webkit-mask:radial-gradient(var(--q) at var(--q) var(--q),#0000 97%,#000) calc(-1*var(--q)) calc(-1*var(--q))/calc(2*var(--q)) calc(2*var(--q)) round,linear-gradient(#000 0 0) 50%/calc(100% - 2*var(--q)) calc(100% - 2*var(--q)) no-repeat;mask:radial-gradient(var(--q) at var(--q) var(--q),#0000 97%,#000) calc(-1*var(--q)) calc(-1*var(--q))/calc(2*var(--q)) calc(2*var(--q)) round,linear-gradient(#000 0 0) 50%/calc(100% - 2*var(--q)) calc(100% - 2*var(--q)) no-repeat}
.npop-stamp::before{content:"";position:absolute;inset:9px;border:1px solid rgba(46,29,39,.35)}
.npop-stamp i{font-style:normal;font-size:.6rem;letter-spacing:.14em;text-transform:uppercase;line-height:1.3}
.npop-stamp b{font-family:var(--serif);font-weight:500;font-size:1.5rem;line-height:1}
@media(max-width:759px){
  .npop-tag{align-self:center}
  .npop-unfold .npop-stage{padding:12px 12px 0 0}
  .npop-unfold .npop-card{border-radius:150px 70px 160px 80px/110px 130px 80px 140px}
  .npop-unfold .npop-card::before{transform:translate(10px,-10px)}
  .npop-unfold .npop-side{border-radius:150px 70px 0 0/110px 130px 0 0}
  .npop-unfold .npop-tx{padding:1.6rem 1.8rem 2.6rem}
  .npop-unfold .npop-x{right:24px;top:22px}
  .npop-unfold .npop-dot{left:50%;top:auto;bottom:14px;transform:translateX(-50%)}
  .npop-teacher .npop-side{padding:30px 30px 6px;height:auto;min-height:0}
  .npop-teacher .npop-orb{height:min(38vh,300px)}
  .npop-teacher .npop-tx{padding:1.4rem 1.6rem 2rem}
  .npop-teacher .npop-tx h2{font-size:2.2rem}
  .npop-teacher .npop-dot{left:50%;top:auto;bottom:12px;transform:translateX(-50%) rotate(-2.5deg)}
  .npop-teacher .npop-x{right:auto;left:26px;top:26px;z-index:6;background:var(--cr);color:var(--dp);border-color:var(--cr)}
  .npop-stamp{right:-10px;top:-12px;width:64px;height:76px}
  .npop-stamp b{font-size:1.15rem}
}
</style>
<script>
(function(){
  var p=document.getElementById('npop');if(!p)return;
  var key='mv-npop-s',force=!!p.dataset.force,seen=false;
  // Countdown, immer aus der echten Startzeit gerechnet
  var st=+p.dataset.start||0,dot=p.querySelector('.npop-dot');
  function en(){return document.documentElement.dataset.l==='en'}
  function fill(){if(!st||!dot)return;var E=en();
    var d0=new Date();d0.setHours(0,0,0,0);var s=new Date(st*1000);s.setHours(0,0,0,0);var n=Math.round((s-d0)/864e5);
    var b=dot.querySelector('b'),t1=dot.querySelector('.t1'),t2=dot.querySelector('.t2');
    if(n<=0){b.textContent=E?'Today':'Heute';t1.textContent=E?'starts':'Start';t2.textContent='';}
    else if(n===1){b.textContent=E?'Tomorrow':'Morgen';t1.textContent='Start';t2.textContent='';}
    else{b.textContent=n;t1.textContent=E?'in':'in';t2.textContent=E?'days':'Tagen';}}
  fill();document.addEventListener('mvlang',fill);
  try{seen=sessionStorage.getItem(key)==='1'}catch(e){}
  if(seen&&!force)return;
  if(/[#&]anfrage/.test(location.hash)&&!force)return;
  function close(){p.classList.remove('on');setTimeout(function(){p.hidden=true},450);try{sessionStorage.setItem(key,'1')}catch(e){}document.removeEventListener('keydown',esc)}
  function esc(e){if(e.key==='Escape')close()}
  p.addEventListener('click',function(e){if(e.target===p||e.target.closest('.npop-x,.npop-later'))close()});
  p.querySelector('.npop-cta .btn').addEventListener('click',function(){try{sessionStorage.setItem(key,'1')}catch(e){}});
  function trap(e){if(e.key!=='Tab')return;var f=p.querySelectorAll('a[href],button'),a=f[0],z=f[f.length-1];if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus()}else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus()}}
  document.addEventListener('keydown',function(e){if(!p.hidden)trap(e)});
  setTimeout(function(){try{var m=document.cookie.match(/(?:^|; )mvpop=(\d+)/),n=m?+m[1]:0;if(!force){document.cookie='mvpop='+(n+1)+';path=/;max-age=31536000;SameSite=Lax';sessionStorage.setItem(key,'1')}}catch(e){}p.hidden=false;requestAnimationFrame(function(){requestAnimationFrame(function(){p.classList.add('on');p.querySelector('.npop-card').focus({preventScroll:true})})});document.addEventListener('keydown',esc)},force?300:3500);
})();
</script>
<?php
}

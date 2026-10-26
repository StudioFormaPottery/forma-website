<?php
// Teleprompter-Texte (Vivien, 25.10.2026): alle Sprechtexte an einem Ort, nach Bereich aufgeklappt,
// ein Tipp öffnet den Teleprompter. Seit 26.10.2026 zusätzlich Produktion: je Video Übungsliste mit Häkchen
// (gefilmt, Deutsch, Englisch eingesprochen), „fertig bis“, Zeitplan nach Wochen und Upload direkt in die Lektion.
// Eingebunden aus verwaltung/movecia.php (?view=texte), nur angemeldet.
declare(strict_types=1);

/** Gathering-Skript nach Titel und Sprache (eigene Änderungen aus der Bibliothek gehen vor). */
function mm_texte_gathering(string $title, string $lang): string
{
    if ($lang === 'de') {
        $de = require dirname(__DIR__) . '/wildhearts/gathering/skripte-de.inc.php';
        return (string)($de[$title] ?? '');
    }
    $p = mm_cfg('prompter') ?? [];
    if (isset($p['library'][$title])) return (string)$p['library'][$title];
    foreach ((require dirname(__DIR__) . '/wildhearts/gathering/skripte.inc.php') as [$t, $x]) if ($t === $title) return (string)$x;
    return '';
}

if ($view === 'texte') {
    // Teleprompter für ein Gathering-Skript
    if (isset($_GET['g'])) {
        $lg = ($_GET['lang'] ?? '') === 'de' ? 'de' : 'en';
        $title = (string)$_GET['g'];
        $txt = mm_texte_gathering($title, $lg);
        header('Content-Type: text/html; charset=utf-8'); ?><!doctype html><html lang="<?= $lg ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Teleprompter · <?= forma_h($title) ?></title>
<style>body{margin:0;background:#1E1719;color:#F6EFEA;font:400 44px/1.5 Georgia,serif}#t{padding:40vh 8vw 80vh;white-space:pre-wrap;max-width:1100px;margin:0 auto}.ui{position:fixed;left:0;right:0;bottom:0;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;padding:12px;background:rgba(30,23,25,.9);font:15px system-ui}.ui button,.ui a{font:inherit;padding:9px 16px;border-radius:999px;border:1px solid #6B5658;background:#4A3A41;color:#F6EFEA;cursor:pointer;text-decoration:none}.mir #t{transform:scaleX(-1)}.hint{color:#A8959B;font-size:.55em}body::before{content:"";position:fixed;left:0;right:0;top:38vh;height:2px;background:rgba(232,214,202,.25)}</style></head><body>
<div id="t"><?= preg_replace('/\[([^\]]+)\]/', '<span class="hint">[$1]</span>', forma_h($txt !== '' ? str_replace(['{names}', '{title}'], [$lg === 'de' ? '[Namen der Frauen vorlesen]' : '[read the names of the women]', ''], $txt) : ($lg === 'de' ? 'Dieses Skript gibt es noch nicht auf Deutsch.' : 'This script does not exist in English yet.'))) ?></div>
<div class="ui"><a href="?view=texte#gatherings">← Texte</a><button id="go">▶ Start</button><button data-s="-1">langsamer</button><button data-s="1">schneller</button><button data-z="-4">A−</button><button data-z="4">A+</button><button id="top">⟲</button><button id="mir">Spiegeln</button></div>
<script>let run=false,sp=1.2,fs=44;const go=document.getElementById('go');function loop(){if(run){window.scrollBy(0,sp);}requestAnimationFrame(loop)}loop();
function t(){run=!run;go.textContent=run?'❚❚ Pause':'▶ Start'}go.onclick=t;document.getElementById('t').onclick=t;document.addEventListener('keydown',e=>{if(e.code==='Space'){e.preventDefault();t()}});
document.querySelectorAll('[data-s]').forEach(b=>b.onclick=()=>{sp=Math.max(.2,sp+(+b.dataset.s)*.3)});document.querySelectorAll('[data-z]').forEach(b=>b.onclick=()=>{fs=Math.max(24,fs+(+b.dataset.z));document.body.style.fontSize=fs+'px'});
document.getElementById('top').onclick=()=>scrollTo(0,0);document.getElementById('mir').onclick=()=>document.body.classList.toggle('mir');</script></body></html><?php exit;
    }

    render_page('Teleprompter-Texte', function () use ($self, $flash, $pdo) {
        require_once dirname(__DIR__) . '/api/method-lib.php';
        mv_schema($pdo);
        $deKeys = array_keys(require dirname(__DIR__) . '/wildhearts/gathering/skripte-de.inc.php');
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
        $WD = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $MO = ['', 'Jän.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.'];
        $dd = function (string $ymd) use ($WD, $MO): string { $t = strtotime($ymd); return $WD[(int)date('w', $t)] . ', ' . date('j', $t) . '. ' . $MO[(int)date('n', $t)]; };
        // Videos in Produktion: Method, Unfold, Prä-/Postnatal (Mitgliederbereich)
        $groups = [];
        foreach (['method' => 'Method', 'unfold' => 'Unfold', 'natal' => 'Prä- &amp; Postnatal · Videos für den Mitgliederbereich'] as $aud => $label) {
            $items = [];
            $ps = mv_programs($pdo, $aud);
            if ($aud === 'natal') usort($ps, fn($a, $b) => $a['sort'] <=> $b['sort']);
            foreach ($ps as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) $items[] = [$p, $l];
            $groups[$aud] = [$label, $items];
        }
        $all = [];
        foreach ($groups as $aud => [, $items]) foreach ($items as [$p, $l]) $all[] = [$aud, $p, $l];
        $isTalk = fn(array $l) => $l['cat'] === 'talk';
        $state = function (array $l) use ($today): string {
            if (mv_lesson_done($l)) return 'done';
            if ((string)$l['due'] !== '' && $l['due'] < $today) return 'late';
            return 'open';
        };
        $nDone = count(array_filter($all, fn($x) => mv_lesson_done($x[2])));
        $nLate = count(array_filter($all, fn($x) => $state($x[2]) === 'late'));
        $weekEnd = date('Y-m-d', strtotime($today . ' +' . ((7 - (int)date('w', strtotime($today))) % 7) . ' days'));
        $nWeek = count(array_filter($all, fn($x) => !mv_lesson_done($x[2]) && (string)$x[2]['due'] !== '' && $x[2]['due'] >= $today && $x[2]['due'] <= $weekEnd));
        // Zeitplan: nach „fertig bis“ gruppiert
        $plan = [];
        foreach ($all as $x) $plan[(string)$x[2]['due'] ?: '9999'][] = $x;
        ksort($plan);
        $bunny = mv_bunny_ok();
        $u = fn(string $q) => forma_h($self) . '?' . $q;
        $audName = ['method' => 'Method', 'unfold' => 'Unfold', 'natal' => 'Prä-/Postnatal'];

        $row = function (string $aud, array $p, array $l) use ($u, $dd, $isTalk, $state, $audName, $bunny): void {
            $id = (int)$l['id'];
            $talk = $isTalk($l);
            $ex = mv_lesson_ex($l);
            $pr = mv_lesson_prod($l);
            $st = $state($l);
            $hasDe = trim((string)$l['script_de']) !== ''; $hasEn = trim((string)$l['script_en']) !== '';
            if ($talk) { $tot = 2; $got = (int)!empty($pr['td']) + (int)!empty($pr['te']); }
            else { $tot = count($ex) * 3; $got = 0; foreach ($ex as $i => $_) foreach (['f', 'd', 'e'] as $s) $got += (int)!empty($pr['x' . $i . $s]); }
            ?>
    <div class="ti <?= $st ?>" id="l<?= $id ?>" data-q="<?= forma_h(mb_strtolower($p['title_de'] . ' ' . $l['title_de'] . ' ' . $l['title_en'] . ' ' . implode(' ', array_map(fn($e) => $e[0] . ' ' . ($e[1] ?? ''), $ex)))) ?>">
      <span class="t"><span class="kind <?= $talk ? 'k-talk' : 'k-move' ?>"><?= $talk ? 'Talking Head' : 'Bewegungsvideo' ?></span> <?= forma_h($l['title_de']) ?>
        <span class="m"><?= (int)$l['minutes'] ? (int)$l['minutes'] . ' Min.' : '' ?><?= $l['unlock'] !== '' ? ' · erscheint ' . forma_h(date('d.m.Y', strtotime($l['unlock']))) : '' ?></span>
        <span class="sts">
          <span class="due" data-due-label="<?= $id ?>"><?= $st === 'done' ? '✓ fertig' : ((string)$l['due'] !== '' ? ($st === 'late' ? '⚠ war fällig ' : 'fertig bis ') . forma_h($dd((string)$l['due'])) : 'kein Datum') ?></span>
          <span class="up <?= $l['video_de'] !== '' ? 'y' : '' ?>" data-up="<?= $id ?>-de">DE <?= $l['video_de'] !== '' ? '✓' : '–' ?></span><span class="up <?= $l['video_en'] !== '' ? 'y' : '' ?>" data-up="<?= $id ?>-en">EN <?= $l['video_en'] !== '' ? '✓' : '–' ?></span>
          <?php if ($tot): ?><span class="cnt" data-cnt="<?= $id ?>"><?= $got ?>/<?= $tot ?> abgehakt</span><?php endif; ?>
        </span></span>
      <span class="b"><?php if ($hasDe): ?><a href="<?= $u('view=method&lang=de&prompter=' . $id) ?>" target="_blank" rel="noopener">Deutsch</a><?php endif; ?><?php if ($hasEn): ?><a href="<?= $u('view=method&lang=en&prompter=' . $id) ?>" target="_blank" rel="noopener">Englisch</a><?php endif; ?><?php if (!$hasDe && !$hasEn): ?><span class="no">Text fehlt noch</span><?php endif; ?><button type="button" class="g" data-open="<?= $id ?>" aria-expanded="false"><?= $talk ? 'Checkliste' : 'Übungen' ?></button></span>
    </div>
    <div class="pp" id="pp<?= $id ?>" hidden data-id="<?= $id ?>">
      <p class="how"><?= $talk ? '🗣 <b>Talking Head:</b> Du sprichst direkt in die Kamera, der Teleprompter läuft dabei mit.' : '🎬 <b>Bewegungsvideo:</b> Du filmst die Übungen und sprichst den Text danach als Voice-over mit dem Teleprompter ein.' ?> <span class="muted"><?= $audName[$aud] ?> · <?= forma_h($p['title_de']) ?></span></p>
      <label class="duein">Fertig bis <input type="date" value="<?= forma_h((string)$l['due']) ?>" data-due="<?= $id ?>"></label>
      <?php if ($talk): ?>
      <div class="exl">
        <div class="ex"><span class="n">Talking Head aufgenommen<?php if (trim((string)$l['text_de']) !== ''): ?><small><?= forma_h($l['text_de']) ?></small><?php endif; ?></span><span class="ch">
          <button type="button" data-k="td" aria-pressed="<?= !empty($pr['td']) ? 'true' : 'false' ?>">🎙 Deutsch</button><button type="button" data-k="te" aria-pressed="<?= !empty($pr['te']) ? 'true' : 'false' ?>">🎙 Englisch</button></span></div>
      </div>
      <?php elseif ($ex): ?>
      <div class="exl">
        <div class="exh"><span>Übung</span><span>🎬 gefilmt · 🎙 Deutsch · 🎙 Englisch</span></div>
        <?php foreach ($ex as $i => $e): ?>
        <div class="ex"><span class="n"><?= $i + 1 ?>. <?= forma_h($e[0]) ?><?php if (!empty($e[1]) && $e[1] !== $e[0]): ?><small><?= forma_h($e[1]) ?></small><?php endif; ?></span><span class="ch">
          <button type="button" data-k="x<?= $i ?>f" aria-pressed="<?= !empty($pr['x' . $i . 'f']) ? 'true' : 'false' ?>" aria-label="gefilmt">🎬 Film</button><button type="button" data-k="x<?= $i ?>d" aria-pressed="<?= !empty($pr['x' . $i . 'd']) ? 'true' : 'false' ?>">DE</button><button type="button" data-k="x<?= $i ?>e" aria-pressed="<?= !empty($pr['x' . $i . 'e']) ? 'true' : 'false' ?>">EN</button></span></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><p class="muted small">Für dieses Video ist noch keine Übungsliste hinterlegt.</p><?php endif; ?>
      <div class="ups">
      <?php foreach (['de' => 'Deutsch', 'en' => 'Englisch'] as $lg => $ln): ?>
        <div class="vbox" data-id="<?= $id ?>" data-lang="<?= $lg ?>">
          <b>Fertiges Video · <?= $ln ?></b>
          <span class="small vmsg"><?= $l['video_' . $lg] !== '' ? '✓ hochgeladen' : 'noch nicht hochgeladen' ?></span>
          <?php if ($bunny): ?>
          <label class="upbtn"><input type="file" accept="video/*" class="vfile"><?= $l['video_' . $lg] !== '' ? 'Video ersetzen' : '⬆ Video hochladen' ?></label>
          <div class="vbar" hidden><i></i></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
      <?php if (!$bunny): ?><p class="small muted">Zum Hochladen zuerst unter <a href="<?= $u('view=method') ?>#bunny">Method &amp; Unfold → Bunny</a> verbinden.</p><?php endif; ?>
      <p class="small muted">Das Video landet direkt in „<?= forma_h($p['title_de']) ?>“ bei dieser Lektion. <a href="<?= $u('view=method&l=' . $id) ?>">Lektion ansehen oder bearbeiten</a></p>
    </div>
<?php
        }; ?>
<style>
:root{--paper:#F2ECE4;--card:#FBF8F4;--line:#DDD2C6;--ink:#2E2529;--soft:#7D7076}
body{background:var(--paper)}h1,h2{color:#6B5658}
@media(min-width:900px){main{max-width:1240px;padding-left:32px;padding-right:32px}body{font-size:19.5px}}
.nh{border-radius:26px;background:#4A3A41;color:#FBF5F7;padding:22px 24px}.nh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:34px;margin:2px 0 4px}.nh p{margin:0;color:#EADFDE}.nh .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:14px}.stats div{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:12px 14px}.stats b{display:block;font-family:"Cormorant Garamond",Georgia,serif;font-size:32px;font-weight:500;color:#6B5658;line-height:1}.stats span{font-size:14px;color:var(--soft)}.stats .bad b{color:#A04A4A}
.tx-search{width:100%;margin:16px 0 4px;font:inherit;padding:12px 16px;border:1px solid var(--line);border-radius:999px;background:#fff}
.filt{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 0;font-size:15px}.filt label{display:flex;gap:6px;align-items:center;font-weight:400}.filt input{width:auto}
details.tx{background:var(--card);border:1px solid var(--line);border-radius:20px;margin-top:12px;padding:4px 16px}
details.tx>summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 0;font-family:"Cormorant Garamond",Georgia,serif;font-size:24px;color:#6B5658}
details.tx>summary::-webkit-details-marker{display:none}details.tx>summary::after{content:"+";color:#7D5E68}details.tx[open]>summary::after{content:"–"}
details.tx>summary small{font-family:"Outfit",sans-serif;font-size:14px;color:var(--soft);margin-left:auto}
.ti{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;border-top:1px solid var(--line);padding:12px 0}
.ti span.t{flex:1 1 280px}.ti .m{display:block;font-size:13px;color:var(--soft)}
.kind{display:inline-block;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;padding:2px 9px;border-radius:999px;vertical-align:2px;margin-right:4px}.k-move{background:#E8D6CA;color:#6B4A3E}.k-talk{background:#E3DCEA;color:#5B4A6E}
.sts{display:flex;flex-wrap:wrap;gap:6px;margin-top:5px;font-size:13px}.sts>span{padding:2px 9px;border-radius:999px;background:#F2ECE4;color:var(--soft)}
.sts .up.y{background:#E2EADB;color:#4E6B3E}.ti.late .due{background:#F6DEDA;color:#A04A4A}.ti.done .due{background:#E2EADB;color:#4E6B3E}
.ti .b{display:flex;gap:6px;flex-wrap:wrap}.ti .b a,.ti .b button{display:inline-block;padding:7px 15px;border-radius:999px;background:#7D5E68;color:#FBF5F7;text-decoration:none;font:inherit;font-size:15px;border:1px solid #7D5E68;cursor:pointer}.ti .b a+a{background:transparent;color:#7D5E68}.ti .b button.g{background:#4A3A41;border-color:#4A3A41}.ti .b button[aria-expanded=true]{background:#E8D6CA;color:#4A3A41;border-color:#E8D6CA}
.ti .no{font-size:14px;color:#A06B6B;align-self:center}
.pp{border:1px solid var(--line);background:#fff;border-radius:18px;padding:14px 16px;margin:0 0 14px}
.pp .how{margin:0 0 10px;font-size:15.5px}.pp .muted{color:var(--soft)}
.duein{display:flex;gap:10px;align-items:center;font-weight:500;font-size:15px;margin-bottom:10px}.duein input{width:auto;padding:7px 10px}
.exl{border-top:1px solid var(--line)}.exh{display:flex;justify-content:space-between;font-size:12.5px;color:var(--soft);padding:6px 0;text-transform:uppercase;letter-spacing:.06em}
.ex{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:8px 0;border-top:1px dashed var(--line)}.ex .n{flex:1;font-size:16px}.ex small{display:block;color:var(--soft);font-size:13px}
.ch{display:flex;gap:5px;flex:0 0 auto}.ch button{font:inherit;font-size:14px;min-width:44px;min-height:40px;padding:6px 10px;border-radius:12px;border:1px solid var(--line);background:#FBF8F4;color:var(--soft);cursor:pointer}
.ch button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
@media(max-width:640px){.ex{flex-wrap:wrap}.ex .n{flex:1 1 100%}.ch{width:100%}.ch button{flex:1}}
.ups{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;margin:14px 0 6px}.vbox{display:flex;flex-direction:column;gap:6px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px}
.upbtn{display:inline-flex;justify-content:center;padding:10px 14px;border-radius:999px;background:#4A3A41;color:#FBF5F7;cursor:pointer;font-weight:500;font-size:15px}.upbtn input{display:none}
.vbar{height:8px;border-radius:9px;background:#EADFDE;overflow:hidden}.vbar i{display:block;height:100%;width:0;background:#7D5E68;transition:width .3s}
.wk{margin:0;padding:10px 0;border-top:1px solid var(--line)}.wk h3{margin:0 0 6px;font-size:16px;color:#4A3A41}.wk h3 small{font-weight:400;color:var(--soft)}
.wk a{display:flex;gap:8px;align-items:baseline;padding:4px 0;color:var(--ink);text-decoration:none;font-size:15.5px}.wk a i{font-style:normal;font-size:12px;color:var(--soft);flex:0 0 auto;min-width:190px}@media(max-width:640px){.wk a{flex-direction:column;gap:0}.wk a i{min-width:0}}.wk a.done{color:var(--soft);text-decoration:line-through}.wk.late h3{color:#A04A4A}
.hide-done .ti.done,.hide-done .ti.done+.pp,.hide-done .wk.alldone,.hide-done .wk a.done{display:none}
</style>
<?php mm_subnav('texte'); ?>
<section class="nh"><p class="eyebrow">Movecia · Einsprechen &amp; Produktion</p><h1>Teleprompter-Texte</h1><p>Alle Sprechtexte an einem Ort. „Deutsch“ oder „Englisch“ öffnet den Teleprompter, „Übungen“ zeigt dir, was zu diesem Video gehört: Du hakst ab, was gefilmt und eingesprochen ist, und lädst das fertige Video direkt hoch. Ein Video gilt als fertig, wenn Deutsch und Englisch hochgeladen sind.</p></section>
<?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
<div class="stats"><div><b><?= $nDone ?>/<?= count($all) ?></b><span>Videos fertig</span></div><div><b><?= $nWeek ?></b><span>bis Sonntag fällig</span></div><div class="<?= $nLate ? 'bad' : '' ?>"><b><?= $nLate ?></b><span>überfällig</span></div></div>
<input class="tx-search" type="search" id="txs" placeholder="Suchen, z. B. Beckenboden, Woche 3, Clamshell …" aria-label="Text suchen">
<div class="filt"><label><input type="checkbox" id="hd" checked> Erledigtes ausblenden</label></div>

<details class="tx" id="zeitplan" open><summary>Dein Zeitplan <small>nach „fertig bis“</small></summary>
<?php foreach ($plan as $d => $xs):
        $doneAll = !array_filter($xs, fn($x) => !mv_lesson_done($x[2]));
        $late = $d !== '9999' && $d < $today && !$doneAll; ?>
  <div class="wk <?= $doneAll ? 'alldone' : '' ?> <?= $late ? 'late' : '' ?>"><h3><?= $d === '9999' ? 'Ohne Datum' : 'Fertig bis ' . forma_h($dd($d)) . ' ' . date('Y', strtotime($d)) ?> <small>· <?= count($xs) ?> Video<?= count($xs) === 1 ? '' : 's' ?><?= $late ? ' · überfällig' : '' ?></small></h3>
  <?php foreach ($xs as [$aud, $p, $l]): ?><a href="#l<?= (int)$l['id'] ?>" data-go="<?= (int)$l['id'] ?>" class="<?= mv_lesson_done($l) ? 'done' : '' ?>"><i><?= $isTalk($l) ? '🗣 Talking Head' : '🎬 Bewegung' ?> · <?= $audName[$aud] ?></i><?= forma_h($l['title_de']) ?></a><?php endforeach; ?>
  </div>
<?php endforeach; ?>
</details>

<?php foreach ($groups as $aud => [$label, $items]): $done = count(array_filter($items, fn($x) => mv_lesson_done($x[1]))); ?>
<details class="tx" id="g-<?= $aud ?>"><summary><?= $label ?> <small><?= $done ?> von <?= count($items) ?> fertig</small></summary>
<?php if (!$items): ?><p class="muted small">Noch keine Lektionen angelegt.</p><?php endif; ?>
<?php $lastP = 0; foreach ($items as [$p, $l]): if ((int)$p['id'] !== $lastP): $lastP = (int)$p['id']; ?><p class="small" style="margin:14px 0 2px;color:#7D5E68;font-weight:600"><?= forma_h($p['title_de']) ?></p><?php endif; ?>
<?php $row($aud, $p, $l); endforeach; ?>
</details>
<?php endforeach; ?>

<details class="tx" id="natal"><summary>Live · Prä- &amp; Postnatal-Einheiten <small>Teleprompter im Videoraum · DE + EN</small></summary>
    <div class="ti" data-q="kennenlerngespräch"><span class="t">Kennenlerngespräch<span class="m">30 Min. online, mit Fragen</span></span><span class="b"><a href="<?= $u('view=natal&prompter=kennenlernen') ?>" target="_blank" rel="noopener">Deutsch</a><a href="<?= $u('view=natal&prompter=kennenlernen_en') ?>" target="_blank" rel="noopener">Englisch</a></span></div>
<?php foreach (NATAL_UNIT_KEYS as $k): ?>
    <div class="ti" data-q="<?= forma_h(mb_strtolower(natal_unit_label($k))) ?>"><span class="t"><?= forma_h(natal_unit_label($k)) ?><span class="m">Live-Einheit: Impuls, Übungen, Breathwork, Meditation</span></span><span class="b"><a href="<?= $u('view=natal&prompter=' . $k) ?>" target="_blank" rel="noopener">Deutsch</a><a href="<?= $u('view=natal&prompter=' . $k . '_en') ?>" target="_blank" rel="noopener">Englisch</a><a href="<?= $u('view=natal&workbook=' . $k) ?>" target="_blank" rel="noopener">Workbook</a></span></div>
<?php endforeach; ?>
</details>

<details class="tx" id="gatherings"><summary>Live · Gatherings <small><?= count(mm_script_titles()) ?> Skripte</small></summary>
<?php foreach (mm_script_titles() as $t): ?>
    <div class="ti" data-q="<?= forma_h(mb_strtolower($t)) ?>"><span class="t"><?= forma_h($t) ?><span class="m">Live-Gathering als Talking Head</span></span>
    <span class="b"><a href="<?= $u('view=texte&lang=en&g=' . rawurlencode($t)) ?>" target="_blank" rel="noopener">Englisch</a><?php if (in_array($t, $deKeys, true)): ?><a href="<?= $u('view=texte&lang=de&g=' . rawurlencode($t)) ?>" target="_blank" rel="noopener">Deutsch</a><?php endif; ?></span></div>
<?php endforeach; ?>
</details>

<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>;
  const MO=['Jän.','Feb.','März','Apr.','Mai','Juni','Juli','Aug.','Sep.','Okt.','Nov.','Dez.'],WD=['So','Mo','Di','Mi','Do','Fr','Sa'];
  function post(a,data){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in data)f.append(k,data[k]);return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
  // Erledigtes ausblenden (gemerkt)
  const hd=document.getElementById('hd');let keep=null;try{keep=localStorage.getItem('mv-hd')}catch(e){}
  if(keep==='0')hd.checked=false;function applyHd(){document.body.classList.toggle('hide-done',hd.checked);try{localStorage.setItem('mv-hd',hd.checked?'1':'0')}catch(e){}}
  hd.addEventListener('change',applyHd);applyHd();
  // Suche
  const s=document.getElementById('txs');s.addEventListener('input',function(){const q=s.value.trim().toLowerCase();document.querySelectorAll('.ti').forEach(function(r){r.hidden=q!==''&&r.dataset.q.indexOf(q)<0;const pp=r.nextElementSibling;if(pp&&pp.classList.contains('pp')&&r.hidden)pp.hidden=true});if(q)document.querySelectorAll('details.tx').forEach(function(d){d.open=!!d.querySelector('.ti:not([hidden])')});});
  // Übungen auf- und zuklappen
  function toggle(id,force){const b=document.querySelector('[data-open="'+id+'"]'),pp=document.getElementById('pp'+id);if(!pp)return;const open=force!==undefined?force:pp.hidden;pp.hidden=!open;if(b)b.setAttribute('aria-expanded',String(open))}
  document.querySelectorAll('[data-open]').forEach(b=>b.addEventListener('click',()=>toggle(b.dataset.open)));
  // Zeitplan → Video
  document.querySelectorAll('[data-go]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();const id=a.dataset.go,row=document.getElementById('l'+id);if(!row)return;
    row.closest('details').open=true;hd.checked=false;applyHd();toggle(id,true);row.scrollIntoView({behavior:'smooth',block:'start'});}));
  if(location.hash.match(/^#l\d+$/)){const id=location.hash.slice(2),row=document.getElementById('l'+id);if(row){row.closest('details').open=true;toggle(id,true);setTimeout(()=>row.scrollIntoView({block:'start'}),50)}}
  // Häkchen
  function recount(pp){const id=pp.dataset.id,c=document.querySelector('[data-cnt="'+id+'"]');if(!c)return;const all=pp.querySelectorAll('.ch button'),on=pp.querySelectorAll('.ch button[aria-pressed=true]');c.textContent=on.length+'/'+all.length+' abgehakt'}
  document.querySelectorAll('.pp .ch button').forEach(b=>b.addEventListener('click',()=>{const pp=b.closest('.pp'),on=b.getAttribute('aria-pressed')!=='true';b.setAttribute('aria-pressed',String(on));recount(pp);
    post('mm_mv_prod',{id:pp.dataset.id,k:b.dataset.k,on:on?1:''}).then(j=>{if(j.error){b.setAttribute('aria-pressed',String(!on));recount(pp);alert('Konnte nicht speichern, bitte Seite neu laden.')}}).catch(()=>{b.setAttribute('aria-pressed',String(!on));recount(pp)});}));
  // Fertig bis
  document.querySelectorAll('[data-due]').forEach(inp=>inp.addEventListener('change',()=>{post('mm_mv_due',{id:inp.dataset.due,due:inp.value}).then(j=>{if(j.error)return alert('Konnte nicht speichern.');
    const lab=document.querySelector('[data-due-label="'+inp.dataset.due+'"]');if(lab&&inp.value){const d=new Date(inp.value+'T12:00:00');lab.textContent='fertig bis '+WD[d.getDay()]+', '+d.getDate()+'. '+MO[d.getMonth()]}else if(lab)lab.textContent='kein Datum';})}));
  // Upload direkt zu Bunny (TUS), dann in die Lektion eintragen
  document.querySelectorAll('.vbox').forEach(box=>{const inp=box.querySelector('.vfile');if(!inp)return;
    inp.addEventListener('change',()=>{const file=inp.files[0];if(!file)return;const id=box.dataset.id,lg=box.dataset.lang,bar=box.querySelector('.vbar'),fill=bar.querySelector('i'),msg=box.querySelector('.vmsg');
      if(typeof tus==='undefined'){msg.textContent='Der Upload konnte nicht geladen werden. Bitte Seite neu laden.';return}
      msg.textContent='Wird vorbereitet …';inp.disabled=true;
      post('mm_mv_video_new',{id:id,lang:lg}).then(j=>{if(j.error){msg.textContent=j.error;inp.disabled=false;return;}
        bar.hidden=false;const up=new tus.Upload(file,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
          headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},
          metadata:{filetype:file.type,title:file.name},
          onError:e=>{msg.textContent='Upload unterbrochen: '+e.message+' – wähl die Datei einfach nochmal aus.';inp.disabled=false},
          onProgress:(a,t)=>{const p=Math.round(a/t*100);fill.style.width=p+'%';msg.textContent='Lädt hoch … '+p+' % (Seite offen lassen)'},
          onSuccess:()=>{post('mm_mv_video_set',{id:id,lang:lg,guid:j.guid}).then(()=>{msg.textContent='✓ Fertig hochgeladen. Bunny wandelt es jetzt in ein paar Minuten um.';inp.disabled=false;
            const t=document.querySelector('[data-up="'+id+'-'+lg+'"]');if(t){t.classList.add('y');t.textContent=lg.toUpperCase()+' ✓'}
            if(document.querySelector('[data-up="'+id+'-de"]').classList.contains('y')&&document.querySelector('[data-up="'+id+'-en"]').classList.contains('y')){const r=document.getElementById('l'+id);r.classList.remove('late','open');r.classList.add('done');const lab=document.querySelector('[data-due-label="'+id+'"]');if(lab)lab.textContent='✓ fertig'}})}});
        up.start();}).catch(()=>{msg.textContent='Das hat nicht geklappt. Bitte lade die Seite neu.';inp.disabled=false});});});
})();
</script>
<?php
    });
    exit;
}

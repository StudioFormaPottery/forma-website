<?php
// „🎬 Videos hochladen“ (Vivien, 26.10.2026): eine große, einfache Seite für alle Videos. Oben, was als Nächstes dran ist.
// Aus Clips gebaute Videos (alles außer Talking Heads): pro Übung ein kurzer Clip (kommt in die Mediathek und steckt
// danach in jedem Video mit dieser Übung). Flow: dazu pro Übung das Voice-over auf Deutsch und Englisch, direkt im
// Browser eingesprochen, der Text steht dabei groß da. Bewegungsvideo: nur Clips, die Teilnehmerin wählt 30/45 Sek.
// Talking Head: Teleprompter, dann das ganze Video DE/EN hochladen.
// ?view=videos&play=ID&lang=de|en zeigt das Video so, wie es die Teilnehmerin sieht.
// Eingebunden aus verwaltung/movecia.php (?view=videos), nur angemeldet.
declare(strict_types=1);

// Vorschau: so sieht es die Teilnehmerin (mit Überspringen, fehlende Clips als Platzhalter, Text statt Voice-over)
if ($view === 'videos' && isset($_GET['play'])) {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    $l = mv_lesson($pdo, (int)$_GET['play']);
    if (!$l) go($self . '?view=videos', 'Video nicht gefunden.');
    $lg = ($_GET['lang'] ?? '') === 'en' && !mv_lesson_de_only($pdo, $l) ? 'en' : 'de';
    $fd = mv_flow_data($l, $lg, true);
    // gleich wie im Mitgliederbereich: Titelbild + Beschreibung für die Einleitung
    if ($fd) { $fd['intro'] = mv_t($l, 'text', $lg); $fd['photo'] = '/img/movecia/fotos/' . ($fd['mode'] === 'move' ? 'ev-method-karte.jpg' : 'ev-ueber-mich.jpg'); }
    $st = mv_lesson_status($pdo, $l);
    render_page('Vorschau · ' . $l['title_de'], function () use ($self, $l, $lg, $fd, $st) {
        $h = 'forma_h'; $id = (int)$l['id'];
        $vid = (string)$l['video_' . $lg] ?: (string)$l['video_' . ($lg === 'en' ? 'de' : 'en')]; ?>
<style>
body{background:#F2ECE4}main{max-width:900px}
.pv-h{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px}
.pv-h a{font-size:18px;color:#6B5658}
.pv-l{display:flex;gap:8px}.pv-l a{padding:10px 18px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;text-decoration:none;font-size:18px}.pv-l a.on{background:#7D5E68;border-color:#7D5E68;color:#fff}
.pv-t{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:38px;color:#4A3A41;margin:0 0 4px;line-height:1.1}
.pv-n{font-size:18px;color:#6B5658;margin:0 0 16px;line-height:1.5}
.pv-w{background:#fff;border:1px solid #DDD2C6;border-radius:28px;padding:18px;font-size:17px}
.pv-v{position:relative;aspect-ratio:16/9;border-radius:22px;overflow:hidden;background:#4A3A41}.pv-v iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.pv-e{display:flex;align-items:center;justify-content:center;height:100%;color:#EADFDE;font-size:19px;text-align:center;padding:20px}
</style>
<?php mm_subnav('videos'); ?>
<div class="pv-h"><a href="<?= $h($self) ?>?view=videos#v<?= $id ?>">← zurück zu „Videos hochladen“</a>
  <?php if (empty($st['deonly'])): ?><span class="pv-l"><a href="?view=videos&amp;play=<?= $id ?>&amp;lang=de"<?= $lg === 'de' ? ' class="on"' : '' ?>>Deutsch</a><a href="?view=videos&amp;play=<?= $id ?>&amp;lang=en"<?= $lg === 'en' ? ' class="on"' : '' ?>>Englisch</a></span><?php endif; ?></div>
<h1 class="pv-t"><?= $h(mv_t($l, 'title', $lg)) ?></h1>
<p class="pv-n">👁 So sieht es die Teilnehmerin im Mitgliederbereich. Nur hier in der Vorschau kannst du mit „Überspringen“ weiterklicken, die Teilnehmerinnen nicht.
<?php if ($fd && !$st['done']): ?> Noch nicht alles da: Wo ein Clip fehlt, steht „Clip folgt“, wo dein Voice-over fehlt, steht oben im Bild ein kleiner Hinweis. Den Sprechtext sehen Teilnehmerinnen nie.<?php endif; ?></p>
<div class="pv-w">
<?php if ($fd): ?>
  <div data-mv-flow="<?= $h(json_encode($fd, JSON_UNESCAPED_UNICODE)) ?>"></div>
  <script src="/wildhearts/assets/flow-player.js?v=20"></script>
<?php else: ?>
  <div class="pv-v"><?php if ($vid !== '' && mv_bunny()['lib'] !== ''): ?><iframe src="<?= $h(mv_embed($vid)) ?>" allow="autoplay;encrypted-media;fullscreen" allowfullscreen></iframe><?php else: ?><div class="pv-e">Das Video ist noch nicht hochgeladen. Sobald es da ist, läuft es hier.</div><?php endif; ?></div>
  <script src="/wildhearts/assets/bunny-ton.js?v=1"></script>
<?php endif; ?>
</div>
<?php
    });
    exit;
}

// ✓ Fertig & ansehen (Vivien, 30.10.2026: „Ankommen habe ich schon fertig – ich will sehen, wie es die Teilnehmerin sieht“)
if ($view === 'videos' && isset($_GET['fertig'])) {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    $groups = [];
    foreach (['method' => 'The Method', 'unfold' => 'Unfold', 'natal' => 'Prä- & Postnatal', 'academy' => 'Academy'] as $aud => $an) {
        foreach (mv_programs($pdo, $aud) as $p) {
            $ls = mv_lessons($pdo, (int)$p['id']); if (!$ls) continue;
            $rows = []; $n = 0;
            foreach ($ls as $l) { $s = mv_lesson_status($pdo, $l); if ($s['done']) { $n++; $rows[] = ['l' => $l, 's' => $s]; } }
            if ($rows) $groups[] = ['an' => $an, 'aud' => $aud, 'p' => $p, 'rows' => $rows, 'n' => $n, 'all' => count($ls)];
        }
    }
    render_page('Fertige Videos', function () use ($self, $groups) {
        $h = 'forma_h'; $site = mm_site(); ?>
<style>
body{background:#F2ECE4}main{max-width:1100px}
.fv-h h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;color:#4A3A41;font-size:clamp(34px,4vw,46px);margin:6px 0 4px}
.fv-h p{font-size:19px;color:#6B5658;margin:0 0 16px;max-width:60ch}
.fv-g{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:24px;padding:16px 18px;margin:0 0 14px}
.fv-g h2{display:flex;flex-wrap:wrap;align-items:baseline;gap:6px 12px;margin:0 0 10px;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:28px;color:#4A3A41}
.fv-g h2 small{font-family:"Hanken Grotesk","Outfit",sans-serif;font-size:15px;color:#7D7076}
.fv-g h2 .all{font-family:"Hanken Grotesk","Outfit",sans-serif;font-size:14px;background:#DDE6D6;color:#2F4A2E;border-radius:999px;padding:3px 12px}
.fv-l{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px}
.fv-i{display:flex;flex-direction:column;gap:8px;background:#fff;border:1.5px solid #E6DCD2;border-radius:18px;padding:12px 14px}
.fv-i b{font-weight:500;font-size:18px;color:#2E2529;line-height:1.3}.fv-i small{font-size:14px;color:#7D7076}
.fv-a{display:flex;flex-wrap:wrap;gap:6px}
.fv-a a{display:inline-flex;align-items:center;min-height:44px;padding:8px 16px;border-radius:999px;text-decoration:none;font-size:16px;background:#7D5E68;color:#fff}
.fv-a a.l{background:#F2ECE4;color:#4A3A41}
.fv-e{font-size:18px;color:#7D7076}
</style>
<?php mm_subnav('videos'); ?>
<div class="fv-h"><h1>✓ Fertig &amp; ansehen</h1><p>Alles, was schon ganz fertig ist. Tipp auf „👁 Ansehen“, dann siehst du es genau so wie die Teilnehmerin, mit Einleitung, Timer und Musik.</p>
  <p><a href="<?= $h($self) ?>?view=videos" style="font-size:18px;color:#6B5658">← zu „Videos hochladen“</a></p></div>
<?php if (!$groups): ?><p class="fv-e">Noch ist kein Video ganz fertig.</p><?php endif; ?>
<?php foreach ($groups as $g): $pt = (string)$g['p']['title_de']; ?>
<section class="fv-g"><h2><?= $h($pt) ?> <small><?= $h($g['an']) ?> · <?= (int)$g['n'] ?> von <?= (int)$g['all'] ?> fertig</small><?= $g['n'] === $g['all'] ? '<span class="all">✓ alles fertig</span>' : '' ?></h2>
  <div class="fv-l">
  <?php foreach ($g['rows'] as $r): $l = $r['l']; $id = (int)$l['id']; $clips = mv_lesson_is_clips($l);
      $kind = !$clips ? 'Talking Head' : (mv_lesson_mode($l) === 'flow' ? 'Flow mit Voice-over' : 'Bewegungsvideo'); $de = !empty($r['s']['deonly']); ?>
    <div class="fv-i"><span><small><?= $h($kind) ?></small><br><b><?= $h((string)$l['title_de']) ?></b></span>
      <div class="fv-a"><a href="<?= $h($self) ?>?view=videos&amp;play=<?= $id ?>&amp;lang=de">👁 Ansehen<?= $de ? '' : ' · Deutsch' ?></a><?php if (!$de): ?><a class="l" href="<?= $h($self) ?>?view=videos&amp;play=<?= $id ?>&amp;lang=en">Englisch</a><?php endif; ?>
      <?php if ($g['aud'] === 'method' || $g['aud'] === 'unfold'): ?><a class="l" href="<?= $h($site) ?>/mitglieder/?demo=1<?= $g['aud'] === 'unfold' ? '&amp;unfold=1' : '' ?>&amp;l=<?= $id ?>" target="_blank" rel="noopener">Im Mitgliederbereich</a><?php endif; ?></div></div>
  <?php endforeach; ?>
  </div></section>
<?php endforeach;
    });
    exit;
}
if ($view === 'videos') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    mv_seed($pdo);
    $all = [];
    foreach (['method' => 'Method', 'unfold' => 'Unfold', 'natal' => 'Prä- & Postnatal'] as $aud => $an) {
        foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) $all[] = ['aud' => $aud, 'an' => $an, 'p' => $p, 'l' => $l, 's' => mv_lesson_status($pdo, $l)];
    }
    usort($all, fn($a, $b) => [(string)$a['l']['due'] ?: '9999', (int)$a['l']['id']] <=> [(string)$b['l']['due'] ?: '9999', (int)$b['l']['id']]);
    $usage = mv_clip_usage($pdo);
    $vcount = mv_voice_counts($pdo);

    render_page('Videos hochladen', function () use ($self, $flash, $all, $pdo, $usage, $vcount) {
        $h = 'forma_h';
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
        $WD = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $MO = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $dd = fn(string $d) => $WD[(int)date('w', strtotime($d))] . ', ' . date('j', strtotime($d)) . '. ' . $MO[(int)date('n', strtotime($d))];
        $bunny = mv_bunny_ok();
        $open = array_values(array_filter($all, fn($x) => !$x['s']['done']));
        $done = array_values(array_filter($all, fn($x) => $x['s']['done']));
        $u = fn(string $q) => $h($self) . '?' . $q;
        $mmss = fn(int $s) => intdiv($s, 60) . ':' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT);
        $stText = function (array $s, array $l): string {
            $do = !empty($s['deonly']);
            if ($s['n'] === 0) return ($l['video_de'] !== '' ? 'Deutsch ✓' : 'Deutsch fehlt') . ($do ? ' · nur Deutsch' : ' · ' . ($l['video_en'] !== '' ? 'Englisch ✓' : 'Englisch fehlt'));
            return 'Clips ' . $s['clips'] . '/' . $s['n'] . ($s['flow'] ? ' · Deutsch ' . $s['de'] . '/' . $s['n'] . ($do ? '' : ' · Englisch ' . $s['en'] . '/' . $s['n']) : '');
        };

        $card = function (array $x, bool $isOpen) use ($h, $dd, $today, $bunny, $u, $pdo, $usage, $vcount, $mmss, $stText): void {
            $l = $x['l']; $id = (int)$l['id']; $s = $x['s'];
            $deOnly = !empty($s['deonly']); // Unfold, Prä-/Postnatal: nur Deutsch (Vivien, 30.10.2026)
            $LANGS = $deOnly ? ['de' => 'Deutsch'] : ['de' => 'Deutsch', 'en' => 'Englisch'];
            $clips = mv_lesson_is_clips($l);
            $flow = $clips && mv_lesson_mode($l) === 'flow';
            $fin = $s['done'];
            $late = !$fin && (string)$l['due'] !== '' && $l['due'] < $today;
            $kind = !$clips ? 'Talking Head' . (['breath' => ' · Breathwork', 'meditation' => ' · Meditation'][$l['cat']] ?? '') : ($flow ? 'Flow · mit Voice-over' : 'Bewegungsvideo · 30/45 Sek.'); ?>
  <details class="vc<?= $fin ? ' fin' : '' ?><?= $late ? ' late' : '' ?>" id="v<?= $id ?>" data-aud="<?= $h($x['aud']) ?>"<?= $deOnly ? ' data-deonly="1"' : '' ?><?= $isOpen ? ' open' : '' ?>>
    <summary>
      <span class="kind <?= !$clips ? 'k-talk' : ($flow ? 'k-flow' : 'k-move') ?>" data-kind="<?= $id ?>"><?= $kind ?></span>
      <span class="ttl"><?= $h($l['title_de']) ?></span>
      <span class="meta"><?= $h($x['an']) ?> · <?= $fin ? '<b class="ok">✓ fertig</b>' : ((string)$l['due'] !== '' ? ($late ? '<b class="bad">war fällig am ' . $h($dd((string)$l['due'])) . '</b>' : 'fertig bis <b>' . $h($dd((string)$l['due'])) . '</b>') : 'ohne Datum') ?> · <span class="st" data-st="<?= $id ?>"><?= $h($stText($s, $l)) ?></span></span>
    </summary>
    <div class="body">
      <div class="top">
        <?php if ($clips): ?>
        <div class="seg" role="group" aria-label="Art des Videos" data-mode="<?= $id ?>">
          <button type="button" data-m="flow" aria-pressed="<?= $flow ? 'true' : 'false' ?>"><b>Flow</b><span>mit deinem Voice-over</span></button>
          <button type="button" data-m="move" aria-pressed="<?= $flow ? 'false' : 'true' ?>"><b>Bewegungsvideo</b><span>30/45 Sek., ohne Sprechen</span></button>
        </div>
        <?php endif; ?>
        <a class="see" href="<?= $u('view=videos&play=' . $id) ?>">👁 So sieht es die Teilnehmerin</a>
      </div>
    <?php if ($clips): $rows = mv_lesson_clip_rows($pdo, $l); ?>
      <p class="how" data-how-flow<?= $flow ? '' : ' hidden' ?>><b>So geht's:</b> 1. Jede Übung kurz filmen und bei <b>„Clip“</b> hochladen. 2. Danach pro Übung auf <b>„Deutsch einsprechen“</b><?= $deOnly ? '' : ' und <b>„Englisch einsprechen“</b>' ?> tippen. Der Text steht dabei groß da, dein Clip läuft daneben. Jede Übung läuft bei den Frauen so lange wie unten angegeben (meist 2 Minuten).</p>
      <p class="how" data-how-move<?= $flow ? ' hidden' : '' ?>><b>So geht's:</b> Jede Übung kurz filmen (10 Sekunden bis 1 Minute) und bei <b>„Clip“</b> hochladen. Kein Sprechen. Die Frauen wählen 30 oder 45 Sekunden pro Übung. Deine Voice-overs werden hier nicht abgespielt. Tippst du oben auf „Flow“, wird daraus ein Flow mit deiner Stimme, und jede Übung, die du schon in einem anderen Flow eingesprochen hast, ist dann sofort fertig.</p>
      <?php $bgR = mv_bgm_rows($pdo, $l); $bgM = trim((string)$l['bgm']) === '' ? 'auto' : ((string)$l['bgm'] === 'off' ? 'off' : 'set'); ?>
      <div class="bgm" data-bgm="<?= $id ?>" data-bgmode="<?= $bgM ?>" data-list="<?= $h(json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 't' => (string)$r['title'], 'dur' => (float)$r['dur']], $bgR), JSON_UNESCAPED_UNICODE)) ?>" data-need="<?= mv_bgm_need($l) ?>">
        <div class="bgm-h"><b>🎵 Musik darunter</b><small data-bgn></small></div>
        <div class="bgm-c" data-bgc></div>
        <div class="bgm-a"><button type="button" data-bgdo="next">🎲 Anderer Vorschlag</button><select data-bgadd aria-label="Lied selbst wählen"><option value="">＋ Lied selbst wählen …</option></select><button type="button" data-bgdo="off">Keine Musik</button><button type="button" data-bgdo="auto" hidden>Wieder automatisch</button></div>
      </div>
      <ol class="exl">
      <?php foreach (mv_lesson_steps($l) as $i => $e): $c = $rows[$i]; $g = (string)$c['guid']; $used = count($usage[$c['ckey']] ?? []);
          $vd = mv_voice_url($id, $i, 'de'); $ve = mv_voice_url($id, $i, 'en'); ?>
        <li class="ex" data-id="<?= $id ?>" data-i="<?= $i ?>" data-c="<?= (int)$c['id'] ?>" data-name="<?= $h(mv_step_name($e)) ?>" data-name-en="<?= $h(mv_step_name($e, 'en')) ?>" data-sec="<?= (int)$e['sec'] ?>"<?= !empty($c['mirror']) ? ' data-mirror="1"' : '' ?> data-view="<?= $g !== '' && mv_bunny()['lib'] !== '' ? $h(mv_embed($g, true) . '&autoplay=true&loop=true&muted=true') : '' ?>">
          <div class="exh"><span class="n"><b><?= $i + 1 ?>.</b> <?= $h($e['de']) ?><?php if ($e['side'] !== ''): ?> <span class="side side-<?= $e['side'] ?>"><?= $e['side'] === 'r' ? 'rechte Seite' : 'linke Seite' ?></span><?php endif; ?><?php if (!$deOnly && $e['en'] !== $e['de']): ?> <small><?= $h($e['en']) ?></small><?php endif; ?></span>
            <span class="dur" data-flow-only<?= $flow ? '' : ' hidden' ?>>10 Sek. Vorbereitung + <?= (int)$e['sec'] % 60 === 0 ? ((int)$e['sec'] / 60) . ' Min.' : (int)$e['sec'] . ' Sek.' ?></span></div>
          <textarea hidden data-tde><?= $h($e['tde']) ?></textarea><textarea hidden data-ten><?= $h($e['ten']) ?></textarea>
          <div class="cols">
            <?php if ($e['side'] === 'l'): $mir = !empty($c['mirror']); ?>
            <div class="cell clip<?= $g ? ' ok' : '' ?>" data-left="1">
              <span class="lab">📹 Clip</span>
              <span class="msg"><?= !$g ? 'kommt vom Clip der rechten Seite' : ($mir ? '✓ gespiegelt vom rechten Clip' : '✓ eigener Clip für links') ?></span>
              <?php if ($bunny): ?><label class="up again"><input type="file" accept="video/*" class="vfile"><span><?= $mir || !$g ? 'Eigenen Clip für links' : 'Neu hochladen' ?></span></label><?php endif; ?>
            <?php else: ?>
            <div class="cell clip<?= $g ? ' ok' : '' ?>">
              <span class="lab">📹 Clip</span>
              <span class="msg"><?= $g ? '✓ da' . ($used > 1 ? ' · steckt in ' . $used . ' Videos' : '') : 'fehlt noch' ?></span>
              <?php if ($bunny): ?><label class="up<?= $g ? ' again' : '' ?>"><input type="file" accept="video/*" class="vfile"><span><?= $g ? 'Neu hochladen' : 'Clip hochladen' ?></span></label><?php endif; ?>
              <a class="up again" style="text-decoration:none" href="<?= $u('view=studio&c=' . (int)$c['id']) ?>">🧘 Im Studio filmen</a>
            <?php endif; ?>
              <span class="bar" hidden><i></i></span>
            </div>
            <?php foreach (array_intersect_key(['de' => [$vd, 'Deutsch'], 'en' => [$ve, 'Englisch']], $LANGS) as $vg => [$vu, $vn]): $own = (bool)glob(mv_voice_dir() . '/L' . $id . '-' . $i . '-' . $vg . '.*'); $nt = (int)($vcount[$c['ckey']][$vg] ?? 0); ?>
            <div class="cell voice<?= $vu ? ' ok' : '' ?>" data-flow-only data-g="<?= $vg ?>" data-takes="<?= $nt ?>"<?= $flow ? '' : ' hidden' ?>>
              <span class="lab">🎙 <?= $vn ?></span>
              <span class="msg"><?= $vu ? ($own ? '✓ eingesprochen' : '✓ aus deiner Bibliothek') : ($nt ? 'fehlt · ♻ ' . $nt . ' gespeicherte Aufnahme' . ($nt > 1 ? 'n' : '') : 'fehlt noch') ?></span>
              <?php if ($vu): ?><audio controls preload="none" src="<?= $h($vu) ?>"></audio><?php endif; ?>
              <button type="button" class="rec<?= $vu ? ' again' : '' ?>" data-rec="<?= $vg ?>"><?= $vu ? 'Nochmal einsprechen' : $vn . ' einsprechen' ?></button>
            </div>
            <?php endforeach; ?>
          </div>
        </li>
      <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <div class="steps">
        <div class="step"><span class="no">1</span><div><b>Im Studio aufnehmen</b><p>Du sprichst einmal am Stück in die Kamera, der Text läuft oben im Bild mit. Mit Kamera-Look und Hilfe für deine Position, danach direkt hier hochladen.</p>
          <p class="btns"><a class="see" href="<?= $u('view=studio&l=' . $id . '&lang=de') ?>">🎥 Im Studio aufnehmen<?= $deOnly ? '' : ' · Deutsch' ?></a><?php if (!$deOnly): ?><a class="see" href="<?= $u('view=studio&l=' . $id . '&lang=en') ?>">🎥 Im Studio aufnehmen · Englisch</a><?php endif; ?></p>
          <p class="btns" style="margin-top:4px!important"><span style="font-size:16px;color:#7D7076;align-self:center">Oder nur der Text, z. B. fürs Handy:</span><?php if (trim((string)$l['script_de']) !== ''): ?><a class="tp" href="<?= $u('view=method&lang=de&prompter=' . $id) ?>" target="_blank" rel="noopener">Teleprompter Deutsch</a><?php endif; ?><?php if (!$deOnly && trim((string)$l['script_en']) !== ''): ?><a class="tp" href="<?= $u('view=method&lang=en&prompter=' . $id) ?>" target="_blank" rel="noopener">Teleprompter Englisch</a><?php endif; ?></p></div></div>
        <div class="step"><span class="no">2</span><div><b>Oder ein fertiges Video hochladen</b><p>Wenn du woanders aufgenommen hast: <?= $deOnly ? 'einfach auf Deutsch' : 'einmal auf Deutsch und einmal auf Englisch' ?>. Schneiden musst du nichts.</p>
          <div class="two">
          <?php foreach ($LANGS as $lg => $ln): $g = (string)$l['video_' . $lg]; ?>
            <div class="vbox" data-id="<?= $id ?>" data-lang="<?= $lg ?>">
              <span class="msg <?= $g ? 'ok' : '' ?>"><?= $g ? '✓ ' . $ln . ' ist hochgeladen' : $ln . ': noch kein Video' ?></span>
              <?php if ($bunny): ?><label class="up<?= $g ? ' again' : '' ?>"><input type="file" accept="video/*" class="vfile"><span><?= $g ? 'Neu hochladen' : '📹 ' . $ln . ' hochladen' ?></span></label><?php endif; ?>
              <span class="bar" hidden><i></i></span>
            </div>
          <?php endforeach; ?>
          </div></div></div>
      </div>
    <?php endif; ?>
      <p class="foot">Fertig bis <input type="date" value="<?= $h((string)$l['due']) ?>" data-due="<?= $id ?>" aria-label="Fertig bis"> · <a href="<?= $u('view=method&l=' . $id) ?>">Titel oder Beschreibung ändern</a></p>
    </div>
  </details>
<?php
        }; ?>
<style>
body{background:#F2ECE4}
main{max-width:1100px}
.vh{border-radius:28px;background:#4A3A41;color:#FBF5F7;padding:26px 26px}
.vh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:clamp(34px,6vw,46px);margin:0 0 8px;line-height:1.05}
.vh p{margin:0;color:#EADFDE;font-size:19px;line-height:1.5;max-width:62ch}
.vh .lk{display:inline-flex;margin-top:14px;padding:12px 20px;border-radius:999px;background:#E8D6CA;color:#4A3A41;text-decoration:none;font-size:18px;font-weight:500}
.warn{background:#F6DEDA;color:#7A2E2E;border-radius:20px;padding:16px 20px;font-size:19px;margin-top:14px}
.filt{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0 6px}
.filt button{font:inherit;font-size:18px;padding:10px 18px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer}
.filt button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
h2.sec{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:32px;color:#4A3A41;margin:30px 0 10px}
.vc{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:24px;margin:0 0 14px;overflow:hidden}
.vc[open]{border-color:#7D5E68;background:#fff}
.vc.late{border-color:#C98A8A}
.vc>summary{list-style:none;cursor:pointer;padding:18px 20px;display:flex;flex-direction:column;gap:6px}
.vc>summary::-webkit-details-marker{display:none}
.vc>summary::after{content:"Öffnen ▾";align-self:flex-start;font-size:16px;color:#7D5E68;margin-top:2px}
.vc[open]>summary::after{content:"Schließen ▴"}
.kind{align-self:flex-start;font-size:14px;letter-spacing:.06em;text-transform:uppercase;padding:4px 12px;border-radius:999px}
.k-move{background:#E8D6CA;color:#6B4A3E}.k-flow{background:#DCE6DE;color:#3F5B48}.k-talk{background:#E3DCEA;color:#5B4A6E}
.ttl{font-size:24px;font-weight:500;color:#2E2529;line-height:1.25}
.meta{font-size:18px;color:#6B5658}.meta b{font-weight:500}.meta b.ok{color:#3F6B3E}.meta b.bad{color:#A04040}
.body{padding:0 20px 20px;border-top:1px solid #EADFDE}
.top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-top:16px}
.seg{display:flex;gap:8px;flex-wrap:wrap}
.seg button{font:inherit;display:flex;flex-direction:column;align-items:flex-start;gap:0;padding:10px 18px;border-radius:18px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer;text-align:left}
.seg button b{font-size:18px;font-weight:500}.seg button span{font-size:15px;color:#7D7076}
.seg button[aria-pressed=true]{border-color:#7D5E68;background:#EADFDE}
.bgm{background:#FBF5F7;border:1.5px solid #E2CBD2;border-radius:18px;padding:12px 14px;margin:10px 0;display:flex;flex-direction:column;gap:8px}.bgm.busy{opacity:.55;pointer-events:none}
.bgm-h{display:flex;flex-wrap:wrap;gap:4px 12px;align-items:baseline}.bgm-h b{font-weight:500;font-size:19px;color:#4A3A41}.bgm-h small{font-size:15px;color:#7D7076}
.bgm-c{display:flex;flex-wrap:wrap;gap:6px}.bgm-s{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1.5px solid #E6DCD2;border-radius:999px;padding:6px 6px 6px 14px;font-size:16px}.bgm-s small{color:#7D7076}
.bgm-s button{width:30px;height:30px;border-radius:50%;border:0;background:#F2ECE4;color:#8C2C1D;cursor:pointer}.bgm-o{font-size:16px;color:#7D7076}
.bgm-a{display:flex;flex-wrap:wrap;gap:8px}.bgm-a button,.bgm-a select{font:inherit;font-size:16px;min-height:44px;padding:8px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;max-width:100%}
.see{display:inline-flex;align-items:center;min-height:50px;padding:10px 20px;border-radius:999px;background:#7D5E68;color:#fff;text-decoration:none;font-size:18px;font-weight:500}
.how{font-size:19px;line-height:1.5;margin:16px 0 8px;color:#2E2529}
.exl{list-style:none;margin:0;padding:0}
.ex{padding:16px 0;border-top:1px dashed #DDD2C6}
.exh{display:flex;flex-wrap:wrap;gap:10px;align-items:baseline;justify-content:space-between}
.exh .n{font-size:22px;line-height:1.35;color:#2E2529}.side{display:inline-block;font-size:15px;padding:2px 12px;border-radius:999px;vertical-align:2px;margin-left:4px}.side-r{background:#EADFDE;color:#4A3A41}.side-l{background:#E4DCD1;color:#4A3A41}.exh .n small{font-size:16px;color:#7D7076}
.dur{font-size:16px;color:#6B5658;display:flex;gap:8px;align-items:center}.dur select{font:inherit;font-size:16px;padding:6px 10px;width:auto}
.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:10px}
.cell{display:flex;flex-direction:column;gap:8px;background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:18px;padding:14px}
.cell.ok{border-color:#B9CDB2;background:#F4F8F1}
.cell .lab{font-size:18px;font-weight:500;color:#4A3A41}
.cell audio{width:100%;height:40px}
.vc [hidden],.rm[hidden],.rm [hidden]{display:none!important}
.msg{font-size:17px;color:#6B5658}.ok>.msg,.msg.ok{color:#3F6B3E;font-weight:500}.msg a{color:#3F6B3E}
.up,.rec{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 20px;border-radius:999px;background:#4A3A41;color:#FBF5F7;font:inherit;font-size:18px;font-weight:500;cursor:pointer;border:0}
.up input{display:none}
.up.again,.rec.again{background:transparent;color:#4A3A41;border:1.5px solid #4A3A41;min-height:46px;font-size:16px}
.bar{display:block;width:100%;height:10px;border-radius:9px;background:#EADFDE;overflow:hidden}.bar i{display:block;height:100%;width:0;background:#7D5E68;transition:width .3s}
.steps{display:flex;flex-direction:column;gap:18px;margin-top:16px}
.step{display:flex;gap:14px;align-items:flex-start}
.step .no{flex:0 0 auto;width:42px;height:42px;border-radius:50%;background:#7D5E68;color:#fff;font-size:21px;display:flex;align-items:center;justify-content:center}
.step b{font-size:21px;font-weight:500;color:#2E2529}.step p{margin:4px 0 0;font-size:18px;color:#6B5658}
.btns{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px!important}
.tp{display:inline-flex;align-items:center;min-height:50px;padding:10px 20px;border-radius:999px;background:#E8D6CA;color:#4A3A41;text-decoration:none;font-size:18px;font-weight:500}
.two{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-top:12px}
.vbox{display:flex;flex-direction:column;gap:10px;background:#FBF8F4;border:1px solid #DDD2C6;border-radius:18px;padding:14px}
.foot{margin:18px 0 0;font-size:16px;color:#7D7076}.foot input{width:auto;padding:6px 10px;font-size:16px}
.showdone{font:inherit;font-size:18px;margin-top:10px;padding:12px 20px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer}
.empty{font-size:19px;color:#6B5658}
/* Einsprechen */
.rm{position:fixed;inset:0;z-index:100;background:rgba(46,37,41,.6);display:flex;align-items:flex-start;justify-content:center;overflow:auto;padding:16px}
.rm-in{background:#FBF8F4;border-radius:28px;max-width:980px;width:100%;padding:20px;display:flex;flex-direction:column;gap:14px;margin:auto 0}
.rm-h{display:flex;gap:10px;align-items:flex-start;justify-content:space-between}
.rm-h small{display:block;font-size:14px;letter-spacing:.12em;text-transform:uppercase;color:#7D7076}
.rm-h b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:32px;color:#4A3A41;line-height:1.1}
.rm-x{font:inherit;font-size:18px;border:1.5px solid #DDD2C6;background:#fff;border-radius:999px;padding:8px 16px;cursor:pointer}
.rm-g{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.4fr);gap:14px}
@media(max-width:760px){.rm-g{grid-template-columns:1fr}}
.rm-v{position:relative;aspect-ratio:16/9;border-radius:20px;overflow:hidden;background:#4A3A41}.rm-v iframe{position:absolute;inset:0;width:100%;height:100%;border:0;pointer-events:none}
.rm-v .e{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#EADFDE;font-size:17px;text-align:center;padding:12px}
.rm-clock{font-family:"Cormorant Garamond",Georgia,serif;font-size:44px;font-weight:600;color:#4A3A41;font-variant-numeric:tabular-nums;line-height:1;margin:12px 0 8px}
.rm-clock small{font-family:"Outfit",sans-serif;font-size:16px;font-weight:400;color:#7D7076}
.rm-txt{position:relative;font-size:27px;line-height:1.55;color:#2E2529;background:#fff;border:1px solid #DDD2C6;border-radius:20px;padding:18px 20px;max-height:46vh;overflow:auto;white-space:pre-wrap}
.rm-txt .hint{font-size:.62em;color:#9A8A90}
.rm-txt .w{transition:color .25s}.rm-txt.go .w{color:#B8AAB0}.rm-txt.go .w.now,.rm-txt.go .w.now~.w{color:#2E2529}.rm-txt.go .w.now{background:#F1E7DF;border-radius:6px;box-shadow:0 0 0 3px #F1E7DF}
.rm-txt .ph{display:inline-block;font-size:.5em;letter-spacing:.06em;text-transform:uppercase;color:#7D5E68;margin-right:4px}
.rm-ed{width:100%;font:inherit;font-size:19px;line-height:1.5;min-height:220px;border:1.5px solid #7D5E68;border-radius:16px;padding:12px}
.rm-b{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.rm-b button,.rm-b label{display:inline-flex;align-items:center;justify-content:center;min-height:54px;padding:12px 22px;border-radius:999px;font:inherit;font-size:19px;font-weight:500;cursor:pointer;border:1.5px solid #4A3A41;background:transparent;color:#4A3A41}
.rm-b .go{background:#A0413B;border-color:#A0413B;color:#fff}.rm-b .save{background:#3F6B3E;border-color:#3F6B3E;color:#fff}.rm-b .stop{background:#4A3A41;color:#fff}
.rm-b label input{display:none}
.rm-b .sm{min-height:44px;font-size:16px;padding:8px 16px;border-color:#DDD2C6}
.rm-m{font-size:17px;color:#6B5658;margin:0}
.rm-tl{display:flex;flex-direction:column;gap:10px}.rm-tl .tk{background:#fff;border:1.5px solid #DDD2C6;border-radius:18px;padding:12px 14px;display:flex;flex-direction:column;gap:8px}
.rm-tl .tk p{margin:0;font-size:16px;line-height:1.5;color:#2E2529;max-height:7.5em;overflow:auto;white-space:pre-wrap}.rm-tl .tk small{font-size:14px;color:#7D7076}
.rm-tl .tk button{align-self:flex-start;font:inherit;font-size:17px;font-weight:500;min-height:48px;padding:10px 20px;border-radius:999px;border:0;background:#3F6B3E;color:#fff;cursor:pointer}
.rm-lvl{height:10px;border-radius:9px;background:#EADFDE;overflow:hidden;max-width:320px}.rm-lvl i{display:block;height:100%;width:0;background:#7D5E68}
</style>
<?php mm_subnav('videos'); ?>
<section class="vh"><h1>🎬 Videos hochladen</h1><p>Oben steht immer, was als Nächstes dran ist. Tipp auf ein Video, dann siehst du jede Übung mit „Clip“, „Deutsch einsprechen“ und bei The Method zusätzlich „Englisch einsprechen“. Unfold und Prä- &amp; Postnatal gibt es nur noch auf Deutsch. Eine Übung, die du einmal gefilmt hast, steckt automatisch in allen Videos mit dieser Übung.</p>
</section>
<?php if ($flash): ?><p class="alert"><?= $h($flash) ?></p><?php endif; ?>
<?php if (!$bunny): ?><p class="warn">Bevor du Clips hochladen kannst, muss einmal Bunny verbunden werden: <a href="<?= $u('view=method') ?>#bunny">hier verbinden</a>. Einsprechen geht schon jetzt.</p><?php endif; ?>

<div class="filt" role="group" aria-label="Bereich">
  <button type="button" data-f="" aria-pressed="true">Alle</button><button type="button" data-f="method" aria-pressed="false">Method</button><button type="button" data-f="unfold" aria-pressed="false">Unfold</button><button type="button" data-f="natal" aria-pressed="false">Prä- &amp; Postnatal</button>
</div>

<p><a href="<?= $u('view=videos&fertig=1') ?>" style="display:inline-flex;align-items:center;min-height:50px;padding:10px 20px;border-radius:999px;background:#DDE6D6;color:#2F4A2E;text-decoration:none;font-size:18px">✓ Fertige Videos ansehen · <?= count($done) ?></a></p>
<h2 class="sec">Als Nächstes dran</h2>
<?php if (!$open): ?><p class="empty">Alles fertig. Wunderbar!</p><?php endif; ?>
<div id="lst">
<?php foreach ($open as $i => $x) $card($x, $i === 0); ?>
</div>

<?php if ($done): ?>
<button type="button" class="showdone" id="sd">Fertige Videos anzeigen (<?= count($done) ?>)</button>
<div id="donel" hidden><h2 class="sec">Fertig</h2><?php foreach ($done as $x) $card($x, false); ?></div>
<?php endif; ?>

<div class="rm" id="rm" hidden role="dialog" aria-modal="true" aria-labelledby="rmT">
  <div class="rm-in">
    <div class="rm-h"><div><small id="rmK"></small><b id="rmT"></b></div><button type="button" class="rm-x" id="rmX">Schließen</button></div>
    <div class="rm-g">
      <div><div class="rm-v" id="rmV"></div>
        <p class="rm-clock" id="rmC">0:00 <small></small></p>
        <div class="rm-lvl" aria-hidden="true"><i id="rmL"></i></div></div>
      <div><div class="rm-txt" id="rmTx"></div><textarea class="rm-ed" id="rmEd" hidden></textarea></div>
    </div>
    <div class="rm-b" id="rmB">
      <button type="button" class="go" id="rmGo">● Aufnahme starten</button>
      <button type="button" class="stop" id="rmStop" hidden>■ Stopp</button>
      <button type="button" class="save" id="rmSave" hidden>✓ Speichern</button>
      <button type="button" id="rmAgain" hidden>↺ Nochmal</button>
      <button type="button" class="sm" id="rmTk" hidden>♻ Gespeicherte Aufnahme nehmen</button>
      <button type="button" class="sm" id="rmEdit">Text bearbeiten</button>
      <label class="sm">Datei hochladen<input type="file" accept="audio/*,video/mp4,.m4a,.mp3,.wav" id="rmFile"></label>
    </div>
    <div id="rmTl" class="rm-tl" hidden></div>
    <audio id="rmA" controls hidden></audio>
    <p class="rm-m" id="rmM"></p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>;
  function post(a,d,file){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);if(file)f.append('audio',file[0],file[1]);return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
  const esc=t=>String(t).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  // Filter
  document.querySelectorAll('.filt button').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.filt button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));
    document.querySelectorAll('.vc').forEach(c=>c.hidden=b.dataset.f!==''&&c.dataset.aud!==b.dataset.f);}));
  const sd=document.getElementById('sd');if(sd)sd.addEventListener('click',()=>{const d=document.getElementById('donel');d.hidden=!d.hidden;sd.textContent=d.hidden?sd.textContent.replace('ausblenden','anzeigen'):sd.textContent.replace('anzeigen','ausblenden')});
  if(location.hash.match(/^#v\d+$/)){const c=document.querySelector(location.hash);if(c){c.open=true;if(c.closest('#donel'))c.closest('#donel').hidden=false;setTimeout(()=>c.scrollIntoView({block:'start'}),50)}}
  document.querySelectorAll('[data-due]').forEach(inp=>inp.addEventListener('change',()=>post('mm_mv_due',{id:inp.dataset.due,due:inp.value})));
  function status(id,s){const el=document.querySelector('[data-st="'+id+'"]');if(!el||!s)return;el.textContent='Clips '+s.clips+'/'+s.n+(s.flow?' · Deutsch '+s.de+'/'+s.n+(s.deonly?'':' · Englisch '+s.en+'/'+s.n):'');
    const c=document.getElementById('v'+id);if(c&&s.done){c.classList.add('fin');c.classList.remove('late')}}
  // ---- Musik darunter je Video (Vivien, 30.10.2026) ----
  const SONGS=<?= json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 't' => (string)$r['title'], 'dur' => (float)$r['dur']], mv_bgm_all($pdo)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const fmm=s=>Math.floor(s/60)+':'+String(Math.round(s%60)).padStart(2,'0');
  function bgmDraw(box){const list=JSON.parse(box.dataset.list||'[]'),m=box.dataset.bgmode,need=+box.dataset.need||0,sum=list.reduce((a,x)=>a+(x.dur||150),0);
    box.querySelector('[data-bgc]').innerHTML=m==='off'?'<span class="bgm-o">Keine Musik – nur '+(box.closest('.vc').querySelector('[data-m=flow][aria-pressed=true]')?'deine Stimme':'der Timer-Klang')+'.</span>':
      (list.length?list.map(x=>'<span class="bgm-s">'+esc(x.t)+' <small>'+fmm(x.dur||0)+'</small><button type="button" data-bgx="'+x.id+'" aria-label="entfernen">✕</button></span>').join(''):'<span class="bgm-o">Noch keine Lieder im Musik-Ordner.</span>');
    box.querySelector('[data-bgn]').textContent=m==='off'?'aus – im Video läuft keine Musik':'✓ läuft so im Video, nichts zu bestätigen · '+(m==='auto'?'automatisch passend gewählt':'von dir gewählt')+(list.length?' · reicht für '+(sum+5>=need?'das ganze Video':fmm(sum)+' von '+fmm(need)+', danach von vorne'):'');
    box.querySelector('[data-bgdo=auto]').hidden=m==='auto';box.querySelector('[data-bgdo=off]').hidden=m==='off';
    box.querySelectorAll('[data-bgx]').forEach(b=>b.onclick=()=>{const ids=list.map(x=>x.id).filter(i=>i!==+b.dataset.bgx);bgmSet(box,ids.length?{do:'set',ids:ids.join(',')}:{do:'off'})});}
  function bgmSet(box,d){box.classList.add('busy');post('mm_mv_bgm',Object.assign({id:box.dataset.bgm},d)).then(r=>{box.classList.remove('busy');if(r.error){alert(r.error);return}box.dataset.bgmode=r.mode;box.dataset.list=JSON.stringify(r.list);bgmDraw(box)}).catch(()=>box.classList.remove('busy'))}
  document.querySelectorAll('.bgm').forEach(box=>{const sel=box.querySelector('[data-bgadd]');sel.innerHTML+=SONGS.map(x=>'<option value="'+x.id+'">'+esc(x.t)+' · '+fmm(x.dur||0)+'</option>').join('');
    sel.onchange=()=>{const id=+sel.value;sel.value='';if(!id)return;const cur=box.dataset.bgmode==='set'?JSON.parse(box.dataset.list||'[]').map(x=>x.id):[];if(!cur.includes(id))cur.push(id);bgmSet(box,{do:'set',ids:cur.join(',')})};
    box.querySelectorAll('[data-bgdo]').forEach(b=>b.onclick=()=>bgmSet(box,{do:b.dataset.bgdo}));bgmDraw(box)});
  // Flow oder Bewegungsvideo
  document.querySelectorAll('[data-mode]').forEach(g=>g.querySelectorAll('button').forEach(b=>b.addEventListener('click',()=>{const id=g.dataset.mode,m=b.dataset.m;
    g.querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));const c=document.getElementById('v'+id);
    c.querySelectorAll('[data-flow-only]').forEach(e=>e.hidden=m!=='flow');c.querySelector('[data-how-flow]').hidden=m!=='flow';c.querySelector('[data-how-move]').hidden=m==='flow';
    const k=c.querySelector('[data-kind="'+id+'"]');k.textContent=m==='flow'?'Flow · mit Voice-over':'Bewegungsvideo · 30/45 Sek.';k.className='kind '+(m==='flow'?'k-flow':'k-move');
    post('mm_mv_mode',{id:id,mode:m}).then(r=>status(id,r.status));})));
  // Dauer je Übung
  document.querySelectorAll('[data-secsel]').forEach(s=>s.addEventListener('change',()=>{const li=s.closest('.ex');li.dataset.sec=s.value;post('mm_mv_step',{id:li.dataset.id,i:li.dataset.i,k:'sec',v:s.value})}));
  // Hochladen (Clip oder ganzes Video) über Bunny
  function upload(box,file,newAct,newData,setAct,setData,done){
    const msg=box.querySelector('.msg'),bar=box.querySelector('.bar'),fill=bar.querySelector('i'),inp=box.querySelector('.vfile'),lab=box.querySelector('.up span');
    if(typeof tus==='undefined'){msg.textContent='Der Upload konnte nicht starten. Bitte Seite neu laden.';return}
    msg.textContent='Wird vorbereitet …';inp.disabled=true;
    post(newAct,newData).then(j=>{if(j.error){msg.textContent=j.error;inp.disabled=false;return}
      bar.hidden=false;const up=new tus.Upload(file,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
        headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},metadata:{filetype:file.type,title:file.name},
        onError:()=>{msg.textContent='Upload unterbrochen. Bitte einfach nochmal auswählen.';inp.disabled=false},
        onProgress:(a,t)=>{const p=Math.round(a/t*100);fill.style.width=p+'%';msg.textContent='Lädt hoch … '+p+' % (Seite offen lassen)'},
        onSuccess:()=>{post(setAct,Object.assign({guid:j.guid},setData)).then(r=>{inp.disabled=false;bar.hidden=true;lab.textContent='Neu hochladen';lab.parentNode.classList.add('again');done(r,msg)})}});
      up.start()}).catch(()=>{msg.textContent='Das hat nicht geklappt. Bitte Seite neu laden.';inp.disabled=false});
  }
  document.querySelectorAll('.cell.clip').forEach(box=>{const inp=box.querySelector('.vfile');if(!inp)return;inp.addEventListener('change',()=>{const f=inp.files[0];if(!f)return;const li=box.closest('.ex');
    if(box.dataset.left){upload(box,f,'mm_mv_clip_new',{id:li.dataset.id,i:li.dataset.i,side:'l'},'mm_mv_clip_set',{id:li.dataset.id,i:li.dataset.i,side:'l'},(r,msg)=>{msg.textContent='✓ eigener Clip für links';box.classList.add('ok');li.dataset.view=r.view+'&autoplay=true&loop=true&muted=true';});return}
    upload(box,f,'mm_mv_clip_new',{c:li.dataset.c},'mm_mv_clip_set',{c:li.dataset.c},(r,msg)=>{
      // gleiche Übung in anderen Videos auf dieser Seite gleich mit als „da“ zeigen
      document.querySelectorAll('.ex[data-c="'+li.dataset.c+'"]').forEach(x=>{const cb=x.querySelector('.cell.clip');cb.classList.add('ok');cb.querySelector('.msg').textContent=cb.dataset.left?'✓ gespiegelt vom rechten Clip':'✓ da'+(r.used>1?' · steckt in '+r.used+' Videos':'');x.dataset.view=r.view+'&autoplay=true&loop=true&muted=true';});
      const id=li.dataset.id;const cnt=document.querySelectorAll('#v'+id+' .cell.clip.ok').length,n=document.querySelectorAll('#v'+id+' .ex').length;
      const s=document.querySelector('[data-st="'+id+'"]');if(s)s.textContent=s.textContent.replace(/Clips \d+\/\d+/,'Clips '+cnt+'/'+n);
      msg.insertAdjacentHTML('beforeend',' <a class="see1" href="?view=videos&amp;play='+id+'" style="display:inline-flex;margin:6px 0 0 6px;padding:8px 16px;border-radius:999px;background:#4A3A41;color:#fff;text-decoration:none;font-size:16px">👁 So sieht es die Teilnehmerin</a>');});});});
  document.querySelectorAll('.vbox').forEach(box=>{const inp=box.querySelector('.vfile');if(!inp)return;inp.addEventListener('change',()=>{const f=inp.files[0];if(!f)return;const id=box.dataset.id,lg=box.dataset.lang;
    upload(box,f,'mm_mv_video_new',{id:id,lang:lg},'mm_mv_video_set',{id:id,lang:lg},(r,msg)=>{msg.className='msg ok';msg.textContent='✓ '+(lg==='de'?'Deutsch':'Englisch')+' ist hochgeladen';msg.insertAdjacentHTML('beforeend',' <a class="see1" href="?view=videos&amp;play='+id+'&amp;lang='+lg+'" style="display:inline-flex;margin:6px 0 0 6px;padding:8px 16px;border-radius:999px;background:#4A3A41;color:#fff;text-decoration:none;font-size:16px">👁 So sieht es die Teilnehmerin</a>');});});});

  // --- Einsprechen: Text groß, Clip läuft daneben, Uhr zählt bis zur Übungsdauer ---------------------------
  const rm=document.getElementById('rm'),$=id=>document.getElementById(id);
  let cur=null,rec=null,chunks=[],blob=null,t0=0,tick=0,stream=null,an=null,actx=null;
  const fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
  const TIP='Lies einfach dort, wo der Text hinterlegt ist. Die Markierung wandert so, dass dein Text genau mit dem Video endet.';
  function textHtml(t){return esc(t||'').replace(/\[([^\]]+)\]/g,'<span class="hint">[$1]</span>')}
  // Lesehilfe (Vivien, 31.10.2026: „ich lese einfach den Text, und wenn das Video vorbei ist, ist auch mein Text vorbei“):
  // die Stelle, an der sie gerade lesen soll, ist hinterlegt und wandert genau so, dass der Text mit dem Video endet.
  // Vorbereitung = erste 10 s, Übung + Ausklang = 10 s bis 2:10, eine [Pause] zählt wie etwa 3 Sekunden.
  let plan=null;
  function readHtml(t){
    const parts=String(t||'').split(/(\[[^\]]+\])/);let h='',k=0;const w=[];let seg=0;
    for(const p of parts){const m=p.match(/^\[([^\]]+)\]$/);
      if(m){const lab=m[1].toLowerCase();if(/^(pause)$/.test(lab)){w.push({seg,wt:5,pause:1});h+='<span class="hint w" data-w="'+(w.length-1)+'">[Pause]</span>';}
        else{if(/übung|exercise/.test(lab))seg=1;h+='<span class="ph">'+esc(m[1])+'</span>';}continue}
      p.split(/(\s+)/).forEach(x=>{if(!x)return;if(/^\s+$/.test(x)){h+=x;return}w.push({seg,wt:1+(/[.!?]$/.test(x)?.8:/[,;:]$/.test(x)?.3:0)});h+='<span class="w" data-w="'+(w.length-1)+'">'+esc(x)+'</span>'})}
    // Zeitplan je Wort
    const has1=w.some(x=>x.seg===1),PREP=has1?10:0,END=cur.sec-2;let a0=0,a1=0;w.forEach(x=>x.seg?a1+=x.wt:a0+=x.wt);let c0=0,c1=0;
    w.forEach(x=>{if(!x.seg&&has1){x.t=PREP*c0/Math.max(1,a0);c0+=x.wt}else{x.t=PREP+(END-PREP)*c1/Math.max(1,a1);c1+=x.wt}});
    plan=w;return h}
  function openRec(li,g){
    cur={li:li,g:g,id:li.dataset.id,i:li.dataset.i,sec:(+li.dataset.sec||120)+10}; // Flow: Stimme beginnt in den 10 s Vorbereitung, Übung 2 Min., eine Seite 1 Min. (Vivien, 31.10.2026)
    $('rmK').textContent='Übung '+(+li.dataset.i+1)+' · '+(g==='de'?'Deutsch':'Englisch')+' einsprechen';
    $('rmT').textContent=g==='de'?li.dataset.name:li.dataset.nameEn;
    $('rmV').innerHTML=li.dataset.view?'<iframe src="'+esc(li.dataset.view)+'" allow="autoplay"'+(li.dataset.mirror?' style="transform:scaleX(-1)"':'')+'></iframe>':'<div class="e">Noch kein Clip. Du kannst trotzdem schon einsprechen.</div>';
    const tx=li.querySelector(g==='de'?'[data-tde]':'[data-ten]').value;
    $('rmTx').innerHTML=tx?readHtml(tx):'<span class="hint">Für diese Übung gibt es noch keinen Text. Tipp auf „Text bearbeiten“, um einen zu schreiben.</span>';
    $('rmEd').value=tx;$('rmEd').hidden=true;$('rmTx').hidden=false;$('rmEdit').textContent='Text bearbeiten';
    $('rmTk').hidden=!(+li.querySelector('.cell.voice[data-g="'+g+'"]').dataset.takes);$('rmTl').hidden=true;$('rmTl').innerHTML='';
    reset();rm.hidden=false;document.body.style.overflow='hidden';
  }
  // Gespeicherte Aufnahme derselben Übung übernehmen: der Text der Übung wird zum Text dieser Aufnahme
  $('rmTk').onclick=()=>{const tl=$('rmTl');tl.hidden=false;tl.innerHTML='<p class="rm-m">Lädt …</p>';
    post('mm_mv_voice_takes',{id:cur.id,i:cur.i,g:cur.g}).then(r=>{if(!r.takes||!r.takes.length){tl.innerHTML='<p class="rm-m">Keine gespeicherte Aufnahme gefunden.</p>';return}
      tl.innerHTML=r.takes.map(t=>'<div class="tk"><small>aus „'+esc(t.from)+'“ · '+esc(t.at)+'</small><audio controls preload="none" src="'+esc(t.url)+'"></audio><p>'+esc(t.text)+'</p><button type="button" data-take="'+t.id+'">Diese Aufnahme nehmen</button></div>').join('');
      tl.querySelectorAll('[data-take]').forEach(b=>b.onclick=()=>post('mm_mv_voice_use',{id:cur.id,i:cur.i,g:cur.g,take:b.dataset.take}).then(x=>{if(x.error){$('rmM').textContent=x.error;return}
        cur.li.querySelector(cur.g==='de'?'[data-tde]':'[data-ten]').value=x.text;saved(x);}));});};
  function reset(){blob=null;chunks=[];$('rmGo').hidden=false;$('rmStop').hidden=true;$('rmSave').hidden=true;$('rmAgain').hidden=true;$('rmA').hidden=true;$('rmA').removeAttribute('src');
    $('rmC').innerHTML='0:00 <small>von '+fmt(cur.sec)+' Min.</small>';$('rmL').style.width='0';$('rmM').textContent=TIP;$('rmTx').scrollTop=0;$('rmTx').classList.remove('go');lastW=-1;$('rmTx').querySelectorAll('.w.now').forEach(e=>e.classList.remove('now'));}
  let lastW=-1;
  function stopStream(){if(stream){stream.getTracks().forEach(t=>t.stop());stream=null}cancelAnimationFrame(tick);}
  function close(){if(rec&&rec.state==='recording')rec.stop();stopStream();rm.hidden=true;document.body.style.overflow='';$('rmV').innerHTML='';}
  $('rmX').onclick=close;rm.addEventListener('click',e=>{if(e.target===rm)close()});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!rm.hidden)close()});
  document.querySelectorAll('[data-rec]').forEach(b=>b.addEventListener('click',()=>openRec(b.closest('.ex'),b.dataset.rec)));
  $('rmGo').onclick=async()=>{
    try{stream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}})}catch(e){$('rmM').textContent='Das Mikrofon ist gesperrt. Bitte im Browser das Mikrofon für diese Seite erlauben.';return}
    const types=['audio/webm;codecs=opus','audio/mp4','audio/webm','audio/ogg'];const mt=types.find(t=>window.MediaRecorder&&MediaRecorder.isTypeSupported(t))||'';
    rec=new MediaRecorder(stream,mt?{mimeType:mt,audioBitsPerSecond:96000}:undefined);chunks=[];
    rec.ondataavailable=e=>{if(e.data.size)chunks.push(e.data)};
    rec.onstop=()=>{stopStream();blob=new Blob(chunks,{type:rec.mimeType||'audio/webm'});$('rmA').src=URL.createObjectURL(blob);$('rmA').hidden=false;$('rmSave').hidden=false;$('rmAgain').hidden=false;$('rmStop').hidden=true;$('rmM').textContent='Hör kurz rein. Passt es, tipp auf „Speichern“.';};
    try{actx=actx||new (window.AudioContext||window.webkitAudioContext)();actx.resume();const src=actx.createMediaStreamSource(stream);an=actx.createAnalyser();an.fftSize=512;src.connect(an);}catch(e){an=null}
    rec.start(1000);t0=performance.now();$('rmGo').hidden=true;$('rmStop').hidden=false;$('rmSave').hidden=true;$('rmAgain').hidden=true;$('rmA').hidden=true;$('rmM').textContent='Aufnahme läuft …';
    const buf=new Uint8Array(512);const sc=$('rmTx');
    (function loop(){const s=(performance.now()-t0)/1000;$('rmC').innerHTML=fmt(s)+' <small>von '+fmt(cur.sec)+' Min. · '+(s<10?'Vorbereitung':s<cur.sec?'Übung':'etwas länger, das passt sich an')+'</small>';
      if(an){an.getByteTimeDomainData(buf);let m=0;for(const v of buf)m=Math.max(m,Math.abs(v-128));$('rmL').style.width=Math.min(100,m*1.6)+'%'}
      // Text läuft sanft mit, damit du nicht scrollen musst
      if(plan&&plan.length){sc.classList.add('go');let k=0;while(k+1<plan.length&&plan[k+1].t<=s)k++;
        if(k!==lastW){const o=sc.querySelector('.w.now');if(o)o.classList.remove('now');const e=sc.querySelector('[data-w="'+k+'"]');if(e){e.classList.add('now');const top=e.offsetTop-sc.clientHeight*.35;sc.scrollTo({top:Math.max(0,top),behavior:'smooth'})}lastW=k}}
      else sc.scrollTop=Math.max(0,(sc.scrollHeight-sc.clientHeight)*Math.min(1,s/Math.max(20,cur.sec*.9)));
      tick=requestAnimationFrame(loop)})();
  };
  $('rmStop').onclick=()=>{if(rec&&rec.state==='recording')rec.stop()};
  $('rmAgain').onclick=reset;
  function saved(r){if(r.error){$('rmM').textContent=r.error;return}
    const cell=cur.li.querySelector('.cell.voice[data-g="'+cur.g+'"]');cell.classList.add('ok');cell.querySelector('.msg').textContent=r.text!==undefined?'✓ aus deiner Bibliothek':'✓ eingesprochen';
    let a=cell.querySelector('audio');if(!a){a=document.createElement('audio');a.controls=true;cell.querySelector('.msg').after(a)}a.src=r.url;
    const b=cell.querySelector('.rec');b.textContent='Nochmal einsprechen';b.classList.add('again');status(cur.id,r.status);
    $('rmM').textContent='✓ Gespeichert.';setTimeout(close,700);}
  $('rmSave').onclick=()=>{if(!blob)return;$('rmM').textContent='Wird gespeichert …';const ext=(blob.type.includes('mp4')?'m4a':blob.type.includes('ogg')?'ogg':'webm');
    post('mm_mv_voice',{id:cur.id,i:cur.i,g:cur.g},[blob,'voice.'+ext]).then(saved).catch(()=>$('rmM').textContent='Speichern hat nicht geklappt. Bitte nochmal.');};
  $('rmFile').onchange=e=>{const f=e.target.files[0];if(!f)return;$('rmM').textContent='Wird hochgeladen …';post('mm_mv_voice',{id:cur.id,i:cur.i,g:cur.g},[f,f.name]).then(saved).catch(()=>$('rmM').textContent='Das hat nicht geklappt.');e.target.value='';};
  $('rmEdit').onclick=()=>{const ed=$('rmEd');if(ed.hidden){ed.hidden=false;$('rmTx').hidden=true;$('rmEdit').textContent='Text speichern';ed.focus();return}
    const k=cur.g==='de'?'tde':'ten';post('mm_mv_step',{id:cur.id,i:cur.i,k:k,v:ed.value}).then(()=>{cur.li.querySelector('[data-'+k+']').value=ed.value;$('rmTx').innerHTML=readHtml(ed.value);ed.hidden=true;$('rmTx').hidden=false;$('rmEdit').textContent='Text bearbeiten';});};
})();
</script>
<?php
    });
    exit;
}

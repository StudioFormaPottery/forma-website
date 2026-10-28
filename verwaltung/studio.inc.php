<?php
// „🎥 Talking-Head-Studio“ (Vivien, 28.10.2026): Talking Heads direkt im Browser aufnehmen, mit Kamera-Look
// (Haut weich, Augenringe aufhellen, Hintergrund unscharf, Licht), Hilfe für die Kopfposition und Teleprompter
// oben im Bild (nahe an der Kamera, damit der Blick in die Linse geht). Hilfslinien und Text sind nie in der Aufnahme.
// Danach herunterladen oder direkt in die Lektion hochladen (Bunny, gleiche Aktionen wie „Videos hochladen“).
// ?view=studio[&l=ID][&lang=de|en]. Ohne Lektion: eigener Text (z. B. tägliche Story), Format 9:16 wählbar.
declare(strict_types=1);

if ($view === 'studio') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    $talks = [];
    foreach (['method' => 'Method', 'unfold' => 'Unfold', 'natal' => 'Prä- & Postnatal'] as $aud => $an) {
        foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) {
            if (mv_lesson_is_clips($l)) continue;
            $talks[] = ['id' => (int)$l['id'], 'g' => $an, 't' => (string)$l['title_de'], 'te' => (string)($l['title_en'] ?: $l['title_de']),
                'de' => (string)$l['script_de'], 'en' => (string)$l['script_en'], 'vde' => $l['video_de'] !== '', 'ven' => $l['video_en'] !== '', 'due' => (string)$l['due'],
                'k' => ['breath' => 'Atem-Video', 'meditation' => 'Meditation'][$l['cat']] ?? 'Talking Head'];
        }
    }
    $sel = (int)($_GET['l'] ?? 0);
    // Übungs-Clips für Flows und Bewegungsvideos (Vivien, 28.10.2026: dort nur Hintergrund weich + Belichtung, keine Gesichtsfilter)
    $moves = [];
    // Wofür wird die Übung gebraucht (Flow / Bewegungsvideo) und bis wann? (Vivien, 28.10.2026: „damit ich genau weiß, wann ich was aufnehmen soll“)
    $use = [];
    foreach ($pdo->query("SELECT * FROM mv_lessons WHERE visible = 1 AND cat NOT IN ('talk','breath','meditation')")->fetchAll() as $l) {
        if (!mv_lesson_is_clips($l)) continue;
        foreach (mv_lesson_steps($l) as $x) $use[mv_clip_key($x['de'])][] = ['t' => (string)$l['title_de'], 'm' => mv_lesson_mode($l), 'due' => (string)$l['due']];
    }
    foreach (mv_clips_all($pdo, true) as $k => $c) {
        $u = $use[$k] ?? [];
        usort($u, fn($a, $b) => [$a['due'] === '' ? '9' : $a['due'], $a['t']] <=> [$b['due'] === '' ? '9' : $b['due'], $b['t']]);
        $ms = array_unique(array_column($u, 'm'));
        $moves[] = ['id' => (int)$c['id'], 'n' => (string)$c['name_de'], 'ok' => (string)$c['guid'] !== '', 'due' => $u[0]['due'] ?? '',
            'k' => count($ms) > 1 ? 'Übung für Flow & Bewegungsvideo' : (($ms[0] ?? '') === 'move' ? 'Übung für Bewegungsvideo' : (($ms[0] ?? '') === 'flow' ? 'Übung für Flow' : 'Übung')),
            'for' => array_map(fn($x) => ($x['m'] === 'move' ? 'Bewegungsvideo' : 'Flow') . ' „' . $x['t'] . '“', $u)];
    }
    usort($moves, fn($a, $b) => [$a['ok'], $a['n']] <=> [$b['ok'], $b['n']]);
    // Eine Liste nach Datum: was als Nächstes dran ist, oben
    $today = date('Y-m-d');
    $wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    $dShort = fn(string $d) => $d === '' ? '' : $wd[(int)date('w', strtotime($d))] . ' ' . date('j.n.', strtotime($d));
    $queue = [];
    foreach ($talks as $t) $queue[] = ['v' => (string)$t['id'], 'ok' => $t['vde'] && $t['ven'], 'due' => $t['due'], 'ico' => '🗣', 'k' => $t['k'], 'n' => $t['t'],
        'x' => $t['vde'] && $t['ven'] ? '' : ($t['vde'] ? ' (Englisch fehlt)' : ($t['ven'] ? ' (Deutsch fehlt)' : ' (DE + EN)')), 'g' => $t['g']];
    foreach ($moves as $m) $queue[] = ['v' => 'c' . $m['id'], 'ok' => $m['ok'], 'due' => $m['due'], 'ico' => '🧘', 'k' => $m['k'], 'n' => $m['n'],
        'x' => $m['for'] ? ' · für ' . $m['for'][0] . (count($m['for']) > 1 ? ' +' . (count($m['for']) - 1) : '') : '', 'g' => ''];
    usort($queue, fn($a, $b) => [$a['due'] === '' ? '9' : $a['due'], $a['ico'], $a['n']] <=> [$b['due'] === '' ? '9' : $b['due'], $b['ico'], $b['n']]);
    foreach ($queue as &$q) $q['l'] = ($q['due'] === '' ? 'ohne Datum' : (!$q['ok'] && $q['due'] < $today ? '⚠ überfällig · ' . $dShort($q['due']) : 'bis ' . $dShort($q['due'])))
        . ' · ' . $q['ico'] . ' ' . $q['k'] . ' · ' . $q['n'] . ($q['g'] !== '' ? ' (' . $q['g'] . ')' : '') . $q['x'];
    unset($q);
    $selC = (int)($_GET['c'] ?? 0);
    $lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'de';
    $bunny = mv_bunny_ok();
    // Instagram täglich (Storys & Reels, ?view=insta): alles ab gestern, was noch nicht gepostet ist
    ig_schema($pdo);
    $igs = [];
    $s = $pdo->prepare("SELECT * FROM mm_insta WHERE day >= ? AND status <> 'gepostet' ORDER BY day, kind DESC, id LIMIT 90"); $s->execute([date('Y-m-d', strtotime('-1 day'))]);
    foreach ($s->fetchAll() as $r) $igs[] = ['id' => (int)$r['id'], 'day' => $r['day'], 'kind' => $r['kind'], 'acc' => IG_ACC[$r['acc']] ?? 'Privat', 't' => ig_title($r),
        'text' => (string)$r['text'], 'music' => (string)$r['music'], 'has' => $r['file'] !== '', 'l' => ig_wd($r['day']) . ' · ' . ($r['kind'] === 'reel' ? '🎬 Reel' : '⭕ Story') . ' · ' . ig_title($r) . ($r['file'] !== '' ? ' ✓' : '')];
    $selI = (int)($_GET['ig'] ?? 0);
    // Kachel-Auswahl (Vivien, 28.10.2026: „nach Tagen zusammengefasst, hübsche Knöpfe statt Liste“)
    $tiles = [];
    $mvBy = []; foreach ($moves as $m) $mvBy['c' . $m['id']] = $m;
    $shortT = fn(string $t) => preg_replace('/\s*·\s*(Bewegungsvideo|Flow|Vertiefung)$/u', '', $t);
    foreach ($queue as $q) {
        $isT = $q['ico'] === '🗣';
        $tiles[] = ['v' => $q['v'], 'ty' => $isT ? 'talk' : 'clip', 'ok' => $q['ok'], 'due' => $q['due'], 'n' => $q['n'],
            'k' => $isT ? $q['k'] . ($q['g'] !== '' ? ' · ' . $q['g'] : '') : $q['k'],
            'x' => $isT ? ($q['ok'] ? 'Deutsch und Englisch ✓' : (trim($q['x'], ' ()') === 'DE + EN' ? 'Deutsch und Englisch fehlen' : trim($q['x'], ' ()'))) : ltrim($q['x'], ' ·')];
        if (!$isT && isset($mvBy[$q['v']])) {
            $m = $mvBy[$q['v']]; $k = &$tiles[count($tiles) - 1];
            $k['k'] = str_contains($m['k'], '&') ? 'Übung · Flow & Bewegung' : (str_contains($m['k'], 'Bewegungsvideo') ? 'Übung · Bewegungsvideo' : (str_contains($m['k'], 'Flow') ? 'Übung · Flow' : 'Übung'));
            $names = array_values(array_unique(array_map(fn($f) => $shortT(preg_replace('/^(Flow|Bewegungsvideo) „(.*)“$/u', '$2', $f)), $m['for'])));
            $k['x'] = $q['ok'] ? 'schon gefilmt ✓' : ($names ? 'für „' . $names[0] . '“' . (count($names) > 1 ? ' und ' . (count($names) - 1) . ' weitere' : '') : '');
            unset($k);
        } elseif ($isT) { $tiles[count($tiles) - 1]['k'] = $q['k']; $tiles[count($tiles) - 1]['g'] = $q['g']; }
    }
    foreach ($igs as $g) $tiles[] = ['v' => 'i' . $g['id'], 'ty' => 'ig', 'ok' => $g['has'], 'due' => $g['day'], 'n' => $g['t'],
        'k' => ($g['kind'] === 'reel' ? 'Reel' : 'Story') . ' · ' . $g['acc'], 'x' => $g['has'] ? 'schon aufgenommen ✓' : ($g['music'] !== '' ? '🎵 ' . $g['music'] : '')];

    render_page('Talking-Head-Studio', function () use ($self, $talks, $sel, $lang, $bunny, $moves, $selC, $queue, $igs, $selI, $tiles) {
        $h = 'forma_h'; ?>
<style>
body{background:#F2ECE4}
main{max-width:none;padding-left:clamp(12px,2.4vw,32px);padding-right:clamp(12px,2.4vw,32px)}
.sh{display:flex;flex-wrap:wrap;gap:12px 20px;align-items:flex-end;justify-content:space-between;margin:6px 0 16px}
.sh h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;color:#4A3A41;font-size:clamp(34px,4vw,46px);margin:0;line-height:1.05}
.sh p{margin:4px 0 0;color:#6B5658;font-size:19px;max-width:70ch}
.pick{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:22px;padding:12px 14px;margin-bottom:16px}
.pick label{font-size:17px;color:#6B5658;font-weight:400}
.pick select{font:inherit;font-size:18px;padding:10px 14px;border:1.5px solid #DDD2C6;border-radius:14px;background:#fff;color:#2E2529;max-width:100%;min-width:min(520px,100%)}
.seg{display:flex;gap:6px}.seg button{font:inherit;font-size:18px;padding:10px 18px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:48px}
.seg button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,400px);gap:20px;align-items:start}
.view{position:sticky;top:72px;z-index:6;align-self:start;background:#F2ECE4;padding-bottom:6px}
@media(max-width:980px){.grid{grid-template-columns:1fr}.view{top:60px;margin:0 -12px;padding:6px 12px 8px;box-shadow:0 10px 18px -14px rgba(46,37,41,.45)}
 .view .bar{margin-top:8px}.view .bar button,.view .bar a.b{min-height:48px;font-size:17px;padding:10px 18px}.view .msg{font-size:15px;margin-top:6px}}
.side{display:flex;flex-direction:column;gap:16px}
.box{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:24px;padding:16px}
.box>h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:26px;color:#4A3A41;margin:0 0 12px}
#stage{position:relative}
.pr{position:absolute;left:0;right:0;top:0;height:36%;overflow:hidden;background:linear-gradient(rgba(46,37,41,.72),rgba(46,37,41,.55) 80%,rgba(46,37,41,0));color:#FBF5F7;z-index:3;pointer-events:auto;cursor:pointer;-webkit-mask-image:linear-gradient(#000 70%,transparent);mask-image:linear-gradient(#000 70%,transparent)}
.pr[hidden]{display:none}
.pr-t{padding:14px 6% 60%;white-space:pre-wrap;font:400 var(--fs,30px)/1.45 "Hanken Grotesk","Outfit",system-ui,sans-serif;text-align:center}
.pr-t .hint{color:#C9B7BD;font-size:.6em}
.cd{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:500 min(30vw,180px)/1 "Cormorant Garamond",Georgia,serif;color:#fff;text-shadow:0 2px 30px rgba(0,0,0,.5);z-index:5;pointer-events:none}
.cd:empty{display:none}
.recdot{position:absolute;right:14px;top:14px;z-index:4;display:flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(160,65,59,.92);color:#fff;font:500 18px/1.2 "Hanken Grotesk","Outfit",system-ui,sans-serif;font-variant-numeric:tabular-nums}
.recdot[hidden]{display:none}.recdot i{width:10px;height:10px;border-radius:50%;background:#fff;animation:bl 1s infinite}
@keyframes bl{50%{opacity:.2}}
.bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:14px}
.bar button,.bar a.b{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:58px;padding:12px 26px;border-radius:999px;font:inherit;font-size:20px;font-weight:500;cursor:pointer;border:1.5px solid #4A3A41;background:transparent;color:#4A3A41;text-decoration:none}
.bar .go{background:#A0413B;border-color:#A0413B;color:#fff}
.bar .stop{background:#4A3A41;color:#fff}
.bar .up{background:#3F6B3E;border-color:#3F6B3E;color:#fff}
.bar .sm{min-height:48px;font-size:17px;padding:10px 18px;border-color:#DDD2C6}
.bar [hidden]{display:none!important}
.msg{font-size:18px;color:#6B5658;margin:10px 2px 0}
.msg.ok{color:#3F6B3E;font-weight:500}
.prog{height:10px;border-radius:9px;background:#EADFDE;overflow:hidden;max-width:520px;margin-top:10px}.prog i{display:block;height:100%;width:0;background:#3F6B3E;transition:width .3s}
.prog[hidden]{display:none}
#play{display:block;width:100%;max-height:74vh;border-radius:22px;background:#2E2529;margin:0 auto}
#play[hidden]{display:none}
.own textarea{width:100%;min-height:200px;font:inherit;font-size:18px;line-height:1.5;border:1.5px solid #DDD2C6;border-radius:16px;padding:12px;background:#fff}
.own[hidden]{display:none}
.prc{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.prc button{font:inherit;font-size:17px;padding:10px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:46px}
.prc button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.chk{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:400;margin-top:10px}.chk input{width:24px;height:24px;padding:0;accent-color:#7D5E68}
.dev{display:flex;flex-direction:column;gap:10px}.dev label{font-size:16px;color:#6B5658;font-weight:400;display:flex;flex-direction:column;gap:4px}
.dev select{font:inherit;font-size:17px;padding:10px 12px;border:1.5px solid #DDD2C6;border-radius:14px;background:#fff}
.tip{font-size:16px;color:#7D7076;line-height:1.5;margin:0}
.done-l{font-size:15px;color:#3F6B3E}
.slab{position:absolute;left:14px;bottom:14px;z-index:4;padding:6px 14px;border-radius:999px;background:rgba(251,245,247,.92);color:#4A3A41;font:500 16px/1.2 "Hanken Grotesk","Outfit",system-ui,sans-serif}
.slab[hidden]{display:none}

body.clipm .pr-t{padding:14px 6%;width:100%}
.bar .next{background:#7D5E68;border-color:#7D5E68;color:#fff;min-width:min(100%,320px)}
.clips{display:flex;flex-direction:column;gap:12px}
.clip{display:grid;grid-template-columns:120px minmax(0,1fr);gap:12px;align-items:start;background:#fff;border:1.5px solid #DDD2C6;border-radius:18px;padding:10px}
.clip video{width:120px;max-height:160px;border-radius:12px;background:#2E2529;object-fit:cover}
.clip b{font-size:16px;font-weight:500;color:#4A3A41}.clip p{margin:2px 0 8px;font-size:15px;line-height:1.4;color:#6B5658;max-height:4.2em;overflow:hidden}
.clip .cb{display:flex;flex-wrap:wrap;gap:6px}
.clip .cb a,.clip .cb button,.cl-all button,.cl-all a{font:inherit;font-size:15px;padding:8px 12px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.clip .cb .sub{border-color:#7D5E68;color:#7D5E68}
.cl-all{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.cl-all .main{background:#7D5E68;border-color:#7D5E68;color:#fff}
.clip.miss{opacity:.6}
.subs .row2{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
.subs .lab{font-size:15px;color:#7D7076;min-width:70px}
.subs .chips{display:flex;flex-wrap:wrap;gap:6px}.subs .chips button{font:inherit;font-size:16px;padding:8px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:42px}
.subs .chips button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.subprev{margin-top:8px;border-radius:14px;overflow:hidden;background:#2E2529}
.subprev canvas{display:block;width:100%}
[hidden]{display:none!important}
.pk{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:28px;padding:14px;margin-bottom:12px;box-shadow:0 14px 30px -24px rgba(74,58,65,.45)}
.pk-now{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.pk-now>i{width:60px;height:60px;border-radius:22px;display:flex;align-items:center;justify-content:center;font-size:30px;font-style:normal;background:#EADFDE;flex:0 0 auto}
.pk-nt{flex:1 1 260px;min-width:0;display:flex;flex-direction:column;gap:2px}
.pk-nt>small{font-size:14px;letter-spacing:.08em;text-transform:uppercase;color:#7D7076}
.pk-nt b{font:500 clamp(24px,2.6vw,30px)/1.15 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.pk-nt span{font-size:17px;color:#6B5658}
.pk-btn{font:inherit;font-size:18px;min-height:52px;padding:10px 22px;border-radius:999px;border:1.5px solid #7D5E68;background:#7D5E68;color:#fff;cursor:pointer}
.pk-btn[aria-expanded=true]{background:#fff;color:#7D5E68}
.pk-body{margin-top:14px;border-top:1px solid #EADFDE;padding-top:12px}
.pk-tabs{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;padding-bottom:4px}.pk-tabs::-webkit-scrollbar{display:none}
.pk-tabs button{flex:0 0 auto;font:inherit;font-size:17px;padding:10px 18px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:48px}
.pk-tabs button span{display:inline-block;min-width:24px;margin-left:6px;padding:1px 8px;border-radius:999px;background:#F2ECE4;font-size:14px}
.pk-tabs button[aria-selected=true]{background:#4A3A41;border-color:#4A3A41;color:#fff}.pk-tabs button[aria-selected=true] span{background:rgba(255,255,255,.18)}
.pk-pane{max-height:min(62vh,640px);overflow:auto;padding:4px 2px 6px;margin-top:8px;overscroll-behavior:contain}
.pk-day{margin:6px 0 18px}
.pk-dh{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 2px 10px;position:sticky;top:0;background:#FBF8F4;padding:6px 0;z-index:1}
.pk-dh b{font:500 25px/1.1 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.pill{font-size:14px;padding:3px 12px;border-radius:999px;background:#EADFDE;color:#6B5658}.pill.soon{background:#E8D6CA;color:#4A3A41}.pill.late{background:#F3D9D5;color:#A0413B}
.pk-dh .cnt{margin-left:auto;font-size:15px;color:#7D7076}
.pk-sub{font-size:15px;color:#7D7076;margin:12px 2px 8px}.pk-sub a{color:#7D5E68}
.pk-empty{font-size:19px;color:#6B5658;padding:12px}
.pk-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px}
.pk-t{display:flex;gap:12px;align-items:flex-start;text-align:left;font:inherit;padding:12px;border-radius:20px;border:1.5px solid #E6DCD2;background:#fff;color:#2E2529;cursor:pointer;transition:transform .15s,box-shadow .15s,border-color .15s;min-height:84px}
.pk-t:hover{transform:translateY(-2px);box-shadow:0 10px 22px -16px rgba(74,58,65,.6);border-color:#C9B0B8}
.pk-t[aria-current=true]{border-color:#7D5E68;box-shadow:0 0 0 2px #7D5E68 inset}
.pk-t>i{flex:0 0 auto;width:44px;height:44px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:22px;font-style:normal}
.ty-talk>i,.ty-own>i{background:#EADFDE}.ty-clip>i,.ty-free>i{background:#E8D6CA}.ty-ig>i{background:#E3D3DB}
.pk-t span{display:flex;flex-direction:column;gap:2px;min-width:0}
.pk-t em{font-style:normal;font-size:13px;color:#6B5658;background:#F2ECE4;border-radius:999px;padding:2px 10px;align-self:flex-start;margin-bottom:3px;display:inline-block}
.pk-t em.g{background:none;padding:0 2px;color:#7D5E68;margin:-2px 0 2px}
.ty-talk em:not(.g),.ty-own em{background:#EADFDE}.ty-clip em,.ty-free em{background:#F1E4DA}.ty-ig em{background:#EBDDE4}
.pk-t b{font-weight:500;font-size:17px;line-height:1.3;color:#2E2529}
.pk-t small{font-size:14px;color:#7D7076;line-height:1.35}
.pk-t.ok{background:#F6F8F3}.pk-t.ok>i{background:#DDE6D6}
@media(max-width:640px){.pk-now{align-items:flex-start}.pk{padding:12px;border-radius:22px}.pk-now>i{width:48px;height:48px;font-size:24px;border-radius:18px}.pk-btn{width:100%}.pk-tiles{grid-template-columns:1fr}.pk-pane{max-height:58vh}}
.what-i{flex-basis:100%;margin:2px 4px 0;font-size:18px;line-height:1.45;color:#4A3A41}.what-i b{font-weight:500}.what-i .late{color:#A0413B}.what-i small{display:block;font-size:16px;color:#7D7076}
.what-i:empty{display:none}
.pk-nt span b{font:inherit;font-weight:500;color:#4A3A41}.pk-nt span small{display:block;font-size:15px;color:#7D7076;margin-top:2px;line-height:1.45}.pk-nt .late{color:#A0413B}
.seg-f{font-size:17px!important;padding:10px 16px;border-radius:999px;border:1.5px solid #7D5E68;background:#FBF5F7;color:#7D5E68!important;cursor:pointer;min-height:48px;display:inline-flex;align-items:center}
#edWrap .ed-h{margin-bottom:12px}#edWrap h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:32px;color:#4A3A41;margin:0}
body.editing .grid{display:none}
.clip .cb .ed{border-color:#7D5E68;background:#7D5E68;color:#fff}
</style>
<?php mm_subnav('studio'); ?>
<div class="sh"><div><h1>🎥 Talking-Head-Studio</h1>
  <p>Kamera an, Look wählen, ins Oval setzen, aufnehmen. Der Text läuft oben im Bild, ganz nah an der Kamera, so schaust du automatisch in die Linse. Hilfslinien und Text sind nie in der Aufnahme.</p></div>
  <a href="<?= $h($self) ?>?view=videos" style="font-size:18px;color:#6B5658">← zu „Videos hochladen“</a></div>

<?php
$dayLong = function (string $d): string { $t = strtotime($d); return ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][(int)date('w', $t)] . ', ' . date('j', $t) . '. ' . ['Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'][(int)date('n', $t) - 1]; };
$pill = function (string $d, bool $ok): array { if ($d === '') return ['', '']; $n = (int)round((strtotime($d . ' 12:00') - strtotime(date('Y-m-d') . ' 12:00')) / 86400);
    if ($n < 0) return [$ok ? '' : 'late', $n === -1 ? 'seit gestern fällig' : 'seit ' . -$n . ' Tagen fällig']; return [$n <= 3 ? 'soon' : '', $n === 0 ? 'heute' : ($n === 1 ? 'morgen' : 'in ' . $n . ' Tagen')]; };
$tile = function (array $t) use ($h) { $ico = ['talk' => '🗣', 'clip' => '🧘', 'ig' => '📱', 'own' => '✍️', 'free' => '🎞'][$t['ty']];
    return '<button type="button" class="pk-t ty-' . $t['ty'] . ($t['ok'] ? ' ok' : '') . '" data-v="' . $h($t['v']) . '"><i>' . $ico . '</i><span><em>' . $h($t['k']) . (($t['g'] ?? '') !== '' ? '</em><em class="g">' . $h($t['g']) : '') . '</em><b>' . $h($t['n']) . '</b>' . ($t['x'] !== '' ? '<small>' . $h($t['x']) . '</small>' : '') . '</span></button>'; };
$groups = function (array $list, bool $withDone = false) use ($dayLong, $pill, $tile, $h) {
    $by = []; foreach ($list as $t) $by[$t['due']][] = $t; uksort($by, fn($a, $b) => ($a === '' ? '9' : $a) <=> ($b === '' ? '9' : $b));
    $o = '';
    foreach ($by as $d => $l) { [$pc, $pt] = $pill((string)$d, false);
        $tl = array_filter($l, fn($t) => $t['ty'] !== 'clip'); $cl = array_filter($l, fn($t) => $t['ty'] === 'clip');
        $o .= '<div class="pk-day"><div class="pk-dh"><b>' . ($d === '' ? 'Noch ohne Datum' : $h($dayLong((string)$d))) . '</b>' . ($pt !== '' ? '<span class="pill ' . $pc . '">' . $h($pt) . '</span>' : '') . '<span class="cnt">' . count($l) . ' ' . (count($l) === 1 ? 'Video' : 'Videos') . '</span></div>';
        if ($tl) $o .= '<div class="pk-tiles">' . implode('', array_map($tile, $tl)) . '</div>';
        if ($cl) $o .= ($tl ? '<p class="pk-sub">🧘 Übungen filmen · ' . count($cl) . '</p>' : '') . '<div class="pk-tiles">' . implode('', array_map($tile, $cl)) . '</div>';
        $o .= '</div>'; }
    return $o; };
$open = array_values(array_filter($tiles, fn($t) => !$t['ok'] && $t['ty'] !== 'ig'));
$igT = array_values(array_filter($tiles, fn($t) => $t['ty'] === 'ig'));
$done = array_values(array_filter($tiles, fn($t) => $t['ok'] && $t['ty'] !== 'ig'));
?>
<section class="pk" id="pk">
  <div class="pk-now"><i id="pkI">🗣</i><div class="pk-nt"><small>Du nimmst gerade auf</small><b id="pkT">…</b><span id="pkS"></span></div>
    <button type="button" class="pk-btn" id="pkToggle" aria-expanded="false">Anderes Video wählen</button></div>
  <div class="pk-body" id="pkBody" hidden>
    <div class="pk-tabs" role="tablist">
      <button type="button" data-tab="next" aria-selected="true">📅 Als Nächstes <span><?= count($open) ?></span></button>
      <?php if ($igT): ?><button type="button" data-tab="ig" aria-selected="false">📱 Instagram <span><?= count($igT) ?></span></button><?php endif; ?>
      <button type="button" data-tab="free" aria-selected="false">✍️ Frei aufnehmen</button>
      <?php if ($done): ?><button type="button" data-tab="done" aria-selected="false">✓ Schon fertig <span><?= count($done) ?></span></button><?php endif; ?>
    </div>
    <div class="pk-pane" data-pane="next"><?= $open ? $groups($open) : '<p class="pk-empty">Alles aufgenommen. Wunderbar ♡</p>' ?></div>
    <?php if ($igT): ?><div class="pk-pane" data-pane="ig" hidden><?= $groups($igT) ?><p class="pk-sub"><a href="<?= $h($self) ?>?view=insta">Alle Storys &amp; Reels planen →</a></p></div><?php endif; ?>
    <div class="pk-pane" data-pane="free" hidden><div class="pk-tiles">
      <?= $tile(['v' => '0', 'ty' => 'own', 'ok' => false, 'n' => 'Eigener Text', 'k' => 'Talking Head', 'x' => 'z. B. Story oder Reel, nur herunterladen']) ?>
      <?= $tile(['v' => 'm', 'ty' => 'free', 'ok' => false, 'n' => 'Bewegungs-Clip', 'k' => 'Bewegung, ohne Gesichtsfilter', 'x' => 'nur herunterladen']) ?>
    </div></div>
    <?php if ($done): ?><div class="pk-pane" data-pane="done" hidden><p class="pk-sub">Eine neue Aufnahme ersetzt die alte.</p><div class="pk-tiles"><?= implode('', array_map($tile, $done)) ?></div></div><?php endif; ?>
  </div>
</section>

<div class="pick">
  <select id="what" hidden>
    <option value="0">✍️ Eigener Text (z. B. Story, Reel) · Talking Head</option>
    <option value="m"<?= isset($_GET['move']) ? ' selected' : '' ?>>🧘 Bewegungs-Clip frei (nur herunterladen)</option>
    <?php if ($igs): ?><optgroup label="📱 Instagram täglich · Storys &amp; Reels">
      <?php foreach ($igs as $g): ?><option value="i<?= $g['id'] ?>"<?= $g['id'] === $selI ? ' selected' : '' ?>><?= $h($g['l']) ?></option><?php endforeach; ?>
    </optgroup><?php endif; ?>
    <?php $isSel = fn($q) => $q['v'] === (string)$sel || $q['v'] === 'c' . $selC;
    foreach ([['📅 Als Nächstes dran · nach Datum', false], ['✓ Schon fertig (neu aufnehmen ersetzt es)', true]] as [$gl, $ok]): if (!array_filter($queue, fn($q) => $q['ok'] === $ok)) continue; ?>
    <optgroup label="<?= $h($gl) ?>">
      <?php foreach ($queue as $q): if ($q['ok'] !== $ok) continue; ?><option value="<?= $h($q['v']) ?>"<?= $isSel($q) ? ' selected' : '' ?>><?= $h($q['l']) ?></option><?php endforeach; ?>
    </optgroup>
    <?php endforeach; ?>
  </select>
  <div class="seg" id="langSeg" role="group" aria-label="Sprache"><button type="button" data-lang="de" aria-pressed="<?= $lang === 'de' ? 'true' : 'false' ?>">Deutsch</button><button type="button" data-lang="en" aria-pressed="<?= $lang === 'en' ? 'true' : 'false' ?>">Englisch</button></div>
  <div class="seg" id="modeSeg" role="group" aria-label="Aufnahme-Art"><button type="button" data-mode="ganz">Am Stück</button><button type="button" data-mode="clips">Absatz für Absatz</button></div>
  <label class="seg-f" title="Ein Video vom Handy oder Computer öffnen und bearbeiten">🎞 Video vom Gerät bearbeiten<input type="file" id="openF" accept="video/*" hidden></label>
  <span class="done-l" id="doneL"></span>
</div>
<p class="what-i" id="whatI" hidden></p>

<div id="edWrap" hidden><div class="ed-h"><h2>✂️ Video bearbeiten</h2><p class="tip" id="edInfo"></p></div><div id="ed"></div></div>

<div class="grid">
  <div class="view">
    <div id="stage" class="tc-stage" style="aspect-ratio:16/9"><div class="tc-st">Kamera startet … Bitte erlaube Kamera und Mikrofon.</div></div>
    <video id="play" controls playsinline hidden></video>
    <div class="bar">
      <button type="button" class="go" id="go">● Aufnahme starten</button>
      <button type="button" class="stop" id="stop" hidden>■ Stopp</button>
      <button type="button" class="up" id="up" hidden>⬆ In die Lektion hochladen</button>
      <button type="button" class="next" id="edit" hidden>✂️ Bearbeiten · Licht, Ausschnitt, Musik</button>
      <a class="b" id="dl" hidden download>⬇ Herunterladen</a>
      <button type="button" class="sm" id="again" hidden>↺ Nochmal aufnehmen</button>
      <button type="button" class="go" id="cgo" hidden>● Absatz 1 aufnehmen</button>
      <button type="button" class="next" id="cnext" hidden>✓ Fertig · nächster Absatz</button>
      <button type="button" class="sm" id="credo" hidden>↺ Absatz nochmal</button>
      <button type="button" class="sm" id="cend" hidden>■ Beenden</button>
    </div>
    <div class="prog" id="prog" hidden><i></i></div>
    <p class="msg" id="msg"></p>
  </div>
  <div class="side">
    <div class="box" id="clipBox" hidden><h2>🎬 Deine Clips</h2>
      <p class="tip" id="clipInfo"></p>
      <div class="clips" id="clips"></div>
      <div class="cl-all" id="clAll" hidden><button type="button" class="main" id="shareAll" hidden>📲 Alle in Fotos sichern</button><button type="button" id="zipAll">⬇ Alle als ZIP</button><button type="button" id="zipSub" hidden>⬇ Alle mit Untertitel (ZIP)</button></div>
    </div>
    <div class="box subs" id="subBox" hidden><h2>💬 Untertitel</h2>
      <p class="tip">Für Social Media: Der Text wird passend zu deiner Stimme ins Video eingebrannt. Die Clips ohne Untertitel bleiben daneben erhalten.</p>
      <div class="row2"><span class="lab">Sprache</span><div class="chips" id="subLang"><button type="button" data-sl="de">Deutsch</button><button type="button" data-sl="en">Englisch</button></div></div>
      <div class="row2"><span class="lab">Stil</span><div class="chips" id="subStyle"><button type="button" data-ss="weiss">Weiß</button><button type="button" data-ss="band">Pflaume</button><button type="button" data-ss="hell">Hell</button></div></div>
      <div class="row2"><span class="lab">Position</span><div class="chips" id="subPos"><button type="button" data-sp2="unten">Unten</button><button type="button" data-sp2="mitte">Mitte</button></div></div>
      <div class="subprev"><canvas id="subPrev" width="640" height="360"></canvas></div>
      <p class="tip" id="subHint" style="margin-top:8px"></p>
      <div class="cl-all"><button type="button" class="main" id="subAll">💬 Untertitel für alle Clips erstellen</button></div>
      <div class="prog" id="subProg" hidden><i></i></div>
    </div>
    <div class="box" id="prBox"><h2>Teleprompter</h2>
      <div class="own" id="own" hidden><textarea id="ownT" placeholder="Hier deinen Text einfügen, z. B. die Story von heute."></textarea>
        <p class="tip" id="ownHint" style="margin:6px 0 10px">Eine Leerzeile trennt die Absätze. Bei „Absatz für Absatz“ wird jeder Absatz ein eigener Clip. Oben bei Deutsch/Englisch kannst du den Text in beiden Sprachen hinterlegen (für Untertitel in der anderen Sprache).</p></div>
      <div class="prc">
        <button type="button" id="prOn" aria-pressed="true">Text an</button>
        <button type="button" data-sp="-1" aria-label="langsamer">langsamer</button><button type="button" data-sp="1" aria-label="schneller">schneller</button>
        <button type="button" data-fs="-3" aria-label="Schrift kleiner">A−</button><button type="button" data-fs="3" aria-label="Schrift größer">A+</button>
        <button type="button" id="prTop" aria-label="zum Anfang">⟲ Anfang</button>
      </div>
      <label class="chk"><input type="checkbox" id="prAuto" checked> Text läuft mit der Aufnahme los</label>
      <p class="tip" style="margin-top:8px">Tippen auf den Text oder Leertaste: Pause und weiter.</p>
    </div>
    <div class="box"><h2>✨ Kamera-Look</h2><div id="panel"></div></div>
    <div class="box"><h2>Kamera &amp; Mikrofon</h2>
      <div class="dev"><label>Kamera<select id="cam"></select></label><label>Mikrofon<select id="mic"></select></label></div>
      <p class="tip" style="margin-top:10px">Tipp: Licht von vorne (Fenster vor dir, nicht hinter dir), Kamera auf Augenhöhe, Handy quer für Videos und hoch für Storys.</p>
    </div>
  </div>
</div>

<script src="/wildhearts/assets/talkcam.js?v=2"></script>
<script src="studio-edit.js?v=1"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
<script>
(function(){
  const MOVES=<?= json_encode($moves, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  let mv=null, mvFree=false;
  const IGS=<?= json_encode($igs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;let ig=null;
  const TALKS=<?= json_encode(array_values($talks), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const csrf=<?= json_encode(csrf()) ?>, BUNNY=<?= $bunny ? 'true' : 'false' ?>;
  const $=id=>document.getElementById(id), esc=t=>String(t).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  function post(a,d){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
  let clipm=false, cs=null, clips=[];
  let lang=<?= json_encode($lang) ?>, cur=null, tgt=null, tc=null, raw=null, rec=null, chunks=[], blob=null, saved=true, t0=0, tick=0, lock=null;
  const ls={get:(k,d)=>{try{const v=localStorage.getItem(k);return v===null?d:v}catch(e){return d}},set:(k,v)=>{try{localStorage.setItem(k,v)}catch(e){}}};

  // ---- Teleprompter ----
  const pr=document.createElement('div');pr.className='pr';pr.innerHTML='<div class="pr-t" id="prT"></div>';
  const slab=document.createElement('div');slab.className='slab';slab.hidden=true;
  let sp=+ls.get('st-sp',40), fs=+ls.get('st-fs',innerWidth<700?20:30), run=false, last=0;
  pr.style.setProperty('--fs',fs+'px');
  function textHtml(t){return esc(t||'').replace(/\[([^\]]+)\]/g,'<span class="hint">[$1]</span>')}
  function textOf(lg){return cur?(lg==='en'?cur.en:cur.de):ownOf(lg)}
  function paras(lg){return String(textOf(lg)||'').split(/\n\s*\n/).map(x=>x.trim()).filter(Boolean)}
  function setText(){if(clipm){showSlide();return}const t=cur?(lang==='en'?cur.en:cur.de):$('ownT').value;
    pr.querySelector('.pr-t').innerHTML=t.trim()?textHtml(t):'<span class="hint">'+(cur?'Für diese Lektion gibt es noch keinen '+(lang==='en'?'englischen':'deutschen')+' Text.':'Füge deinen Text beim Teleprompter ein.')+'</span>';pr.scrollTop=0;}
  function prLoop(ts){if(run){pr.scrollTop+=sp*(ts-last)/1000}last=ts;requestAnimationFrame(prLoop)}requestAnimationFrame(prLoop);
  function toggle(){run=!run}
  pr.addEventListener('click',toggle);
  document.addEventListener('keydown',e=>{if(e.code==='Space'&&!/INPUT|TEXTAREA|SELECT|BUTTON/.test(document.activeElement.tagName)){e.preventDefault();toggle()}});
  document.querySelectorAll('[data-sp]').forEach(b=>b.onclick=()=>{sp=Math.max(10,Math.min(160,sp+(+b.dataset.sp)*8));ls.set('st-sp',sp)});
  document.querySelectorAll('[data-fs]').forEach(b=>b.onclick=()=>{fs=Math.max(18,Math.min(60,fs+(+b.dataset.fs)));pr.style.setProperty('--fs',fs+'px');ls.set('st-fs',fs)});
  $('prTop').onclick=()=>{pr.scrollTop=0;run=false};
  $('prOn').onclick=()=>{pr.hidden=!pr.hidden;$('prOn').setAttribute('aria-pressed',String(!pr.hidden));$('prOn').textContent=pr.hidden?'Text aus':'Text an'};
  // eigener Text je Sprache (für Untertitel in der anderen Sprache)
  if(ls.get('st-own-de',null)===null&&ls.get('st-own',''))ls.set('st-own-de',ls.get('st-own',''));
  const ownOf=lg=>ls.get('st-own-'+lg,'');
  $('ownT').addEventListener('input',()=>{ls.set('st-own-'+lang,$('ownT').value);setText();subPreview()});

  // ---- Auswahl ----
  const profile=()=>(mv||mvFree)?'move':'talk';
  function choose(){const val=$('what').value;mv=val[0]==='c'?(MOVES.find(m=>m.id===+val.slice(1))||null):null;mvFree=val==='m';
    const wasIg=!!ig;ig=val[0]==='i'?(IGS.find(x=>x.id===+val.slice(1))||null):null;
    const id=+val;cur=(mv||mvFree)?null:(ig?{ig:ig,id:'i'+ig.id,t:ig.l,de:ig.text,en:ig.text,vde:ig.has,ven:ig.has,g:'Instagram',k:ig.kind==='reel'?'Reel':'Story',due:ig.day}:(TALKS.find(t=>t.id===id)||null));$('own').hidden=!!cur;$('ownT').value=ownOf(lang);
    const isMove=!!(mv||mvFree);$('prBox').hidden=isMove;$('langSeg').hidden=isMove||!!ig;
    if(tc&&!isMove&&ig!==null!==wasIg){tc.set({aspect:ig?'9:16':'16:9'});TalkCam.panel(tc,$('panel'))}$('modeSeg').hidden=isMove;pr.hidden=isMove||$('prOn').getAttribute('aria-pressed')==='false';
    if(isMove&&clipm)setMode('ganz');
    document.querySelector('.sh h1').textContent=isMove?'🧘 Studio · Übung filmen':'🎥 Talking-Head-Studio';
    const sp=document.querySelector('.sh p');if(!sp.dataset.t)sp.dataset.t=sp.textContent;sp.textContent=isMove?'Für Flows und Bewegungsvideos: Kamera aufstellen, Raster nutzen, aufnehmen. Hier gibt es nur Hintergrund weich und Belichtung, keine Gesichtsfilter.':sp.dataset.t;
    const lh=$('panel').closest('.box').querySelector('h2');lh.textContent=isMove?'☀️ Hintergrund & Licht':'✨ Kamera-Look';
    if(tc&&tc.profile!==profile())startCam();
    document.querySelectorAll('[data-lang]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.lang===lang)));
    whatInfo();pkNow();
    $('doneL').textContent=ig?(ig.has?'✓ Schon aufgenommen (eine neue Aufnahme ersetzt es)':''):mv?(mv.ok?'✓ Diese Übung ist schon gefilmt (eine neue Aufnahme ersetzt den Clip überall)':'Nur Hintergrund weich und Licht, keine Gesichtsfilter.'):(mvFree?'Nur Hintergrund weich und Licht, keine Gesichtsfilter.':(cur?((lang==='de'?cur.vde:cur.ven)?'✓ '+(lang==='de'?'Deutsch':'Englisch')+' ist schon hochgeladen (eine neue Aufnahme ersetzt es)':''):''));
    const u=new URL(location.href);['l','c','move','ig'].forEach(k=>u.searchParams.delete(k));if(ig)u.searchParams.set('ig',ig.id);else if(cur)u.searchParams.set('l',cur.id);if(mv)u.searchParams.set('c',mv.id);if(mvFree)u.searchParams.set('move','1');u.searchParams.set('lang',lang);history.replaceState(null,'',u);
    if(fromFile&&blob)tgt=mv?{clip:mv}:(ig?{ig:ig}:(cur?{t:cur,lang:lang}:null));
    setText();showAfter();clipUi();subPreview();}
  $('what').onchange=choose;
  // ---- Kachel-Auswahl ----
  const pkBody=$('pkBody'),pkTg=$('pkToggle');
  function pkOpen(o){pkBody.hidden=!o;pkTg.setAttribute('aria-expanded',String(o));pkTg.textContent=o?'Schließen':'Anderes Video wählen'}
  pkTg.onclick=()=>pkOpen(pkBody.hidden);
  document.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.setAttribute('aria-selected',String(x===b)));
    document.querySelectorAll('[data-pane]').forEach(p=>p.hidden=p.dataset.pane!==b.dataset.tab)});
  document.querySelectorAll('.pk-t').forEach(t=>t.onclick=()=>{if((rec&&rec.state==='recording')||(cs&&cs.rec))return;
    if(blob&&!saved&&!confirm('Die letzte Aufnahme ist noch nicht gespeichert. Trotzdem wechseln?'))return;
    $('what').value=t.dataset.v;if($('what').value!==t.dataset.v)return;choose();pkOpen(false);$('pk').scrollIntoView({behavior:'smooth',block:'start'})});
  function pkNow(){const v=$('what').value,t=document.querySelector('.pk-t[data-v="'+CSS.escape(v)+'"]');
    document.querySelectorAll('.pk-t').forEach(x=>x.setAttribute('aria-current',String(x.dataset.v===v)));
    $('pkI').textContent=t?t.querySelector('i').textContent:'🗣';$('pkI').style.background=t?getComputedStyle(t.querySelector('i')).backgroundColor:'';
    $('pkT').textContent=t?t.querySelector('b').textContent:'Eigener Text';
    $('pkS').innerHTML=$('whatI').innerHTML.replace(/^\S+\s/,'');}
  const WD=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'],MO=['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  function dLong(d){if(!d)return '';const x=new Date(d+'T12:00:00');return WD[x.getDay()]+', '+x.getDate()+'. '+MO[x.getMonth()]+' '+x.getFullYear()}
  function dueTxt(d,ok){if(!d)return 'noch kein Datum';const days=Math.round((new Date(d+'T12:00:00')-new Date(new Date().toDateString()+' 12:00'))/864e5);
    return (!ok&&days<0?'<span class="late">⚠ überfällig, war fällig am ':'fertig bis ')+'<b>'+dLong(d)+'</b>'+(ok?'':days>1?' (in '+days+' Tagen)':days===1?' (morgen)':days===0?' (heute)':'')+(!ok&&days<0?'</span>':'')}
  function whatInfo(){const el=$('whatI');
    if(mv){el.innerHTML='🧘 <b>'+esc(mv.k)+'</b> · '+dueTxt(mv.due,mv.ok)+'<small>'+(mv.for.length?'Kommt vor in: '+esc(mv.for.join(', '))+'. ':'')+'Einmal filmen, der Clip steckt dann automatisch in allen diesen Videos. '+(/Flow/.test(mv.k)?'Ohne Sprechen filmen, die Stimme sprichst du beim Flow später extra ein.':'Ohne Sprechen filmen, Bewegungsvideos laufen nur mit Timer und Musik.')+'</small>';return}
    if(mvFree){el.innerHTML='🧘 <b>Bewegungs-Clip frei</b><small>Nur Hintergrund weich und Licht. Wird nicht hochgeladen, nur heruntergeladen.</small>';return}
    if(ig){el.innerHTML='📱 <b>'+(ig.kind==='reel'?'🎬 Reel':'⭕ Story')+' für Instagram</b> · '+esc(ig.acc)+' · posten am <b>'+dLong(ig.day)+'</b>'+(ig.music?'<small>🎵 Musik: '+esc(ig.music)+' (in Instagram dazu wählen, oder unter „Bearbeiten“ eine eigene Datei darunterlegen)</small>':'<small>Hochformat 9:16. Nach der Aufnahme auf „💾 Speichern“ tippen, dann am Tag unter „Instagram täglich“ nur noch teilen.</small>');return}
    if(cur){const ok=cur.vde&&cur.ven;el.innerHTML='🗣 <b>'+esc(cur.k)+'</b> · '+esc(cur.g)+' · '+dueTxt(cur.due,ok)+'<small>Ein ganzes Video je Sprache mit Teleprompter. Deutsch '+(cur.vde?'✓ hochgeladen':'fehlt noch')+' · Englisch '+(cur.ven?'✓ hochgeladen':'fehlt noch')+'.</small>';return}
    el.innerHTML='🗣 <b>Talking Head mit eigenem Text</b><small>Für Storys und Reels. Wird nicht hochgeladen, nur heruntergeladen.</small>';}
  document.querySelectorAll('[data-lang]').forEach(b=>b.onclick=()=>{lang=b.dataset.lang;choose()});

  // ---- Kamera ----
  async function startCam(){
    const cid=ls.get('st-cam',''), mid=ls.get('st-mic','');
    if(raw)raw.getTracks().forEach(t=>t.stop());if(tc)tc.destroy(false);
    const v={width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}};if(cid)v.deviceId={exact:cid};
    const a={echoCancellation:true,noiseSuppression:true,autoGainControl:true};if(mid)a.deviceId={exact:mid};
    try{raw=await navigator.mediaDevices.getUserMedia({video:v,audio:a})}
    catch(e){try{raw=await navigator.mediaDevices.getUserMedia({video:true,audio:true});ls.set('st-cam','');ls.set('st-mic','')}catch(e2){$('stage').innerHTML='<div class="tc-st">Kamera oder Mikrofon sind gesperrt. Bitte im Browser oben bei der Adresse erlauben und die Seite neu laden.</div>';return}}
    tc=await TalkCam.create(raw,{mount:$('stage'),maxVh:vh(),profile:profile()});
    $('stage').appendChild(pr);$('stage').appendChild(Object.assign(document.createElement('div'),{className:'cd',id:'cd'}));
    const rd=document.createElement('div');rd.className='recdot';rd.id='rd';rd.hidden=true;rd.innerHTML='<i></i><span id="rt">0:00</span>';$('stage').appendChild(rd);
    $('stage').appendChild(slab);
    tc.on(t=>{if(t==='layout')subPreview()});
    if(ig&&tc.s&&tc.s.aspect!=='9:16'){tc.set({aspect:'9:16'})}
    TalkCam.panel(tc,$('panel'));
    devices();
  }
  async function devices(){try{const ds=await navigator.mediaDevices.enumerateDevices();
    const fill=(sel,kind,key,label)=>{const curId=(raw.getTracks().find(t=>t.kind===kind.replace('input',''))||{getSettings:()=>({})}).getSettings().deviceId;
      sel.innerHTML=ds.filter(d=>d.kind===kind).map((d,i)=>'<option value="'+esc(d.deviceId)+'"'+(d.deviceId===curId?' selected':'')+'>'+esc(d.label||label+' '+(i+1))+'</option>').join('');
      sel.onchange=()=>{ls.set(key,sel.value);startCam()}};
    fill($('cam'),'videoinput','st-cam','Kamera');fill($('mic'),'audioinput','st-mic','Mikrofon');}catch(e){}}

  // ---- Aufnahme ----
  const fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
  function mime(){const ts=['video/mp4;codecs=avc1.640028,mp4a.40.2','video/mp4;codecs=avc1,mp4a','video/mp4','video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'];return ts.find(t=>window.MediaRecorder&&MediaRecorder.isTypeSupported(t))||''}
  function showAfter(){if(clipm){clipUi();return}const has=!!blob;if(tgt)$('up').textContent=tgt.ig?'💾 Für '+tgt.ig.l.split(' · ')[0]+' speichern ('+(tgt.ig.kind==='reel'?'Reel':'Story')+')':tgt.clip?'⬆ Als Clip „'+tgt.clip.n+'“ hochladen':'⬆ In „'+tgt.t.t+'“ hochladen ('+(tgt.lang==='de'?'Deutsch':'Englisch')+')';$('go').hidden=has||(rec&&rec.state==='recording');$('again').hidden=!has;$('dl').hidden=!has;$('edit').hidden=!has;$('up').hidden=!has||!tgt||(!BUNNY&&!tgt.ig);
    $('play').hidden=!has;$('stage').style.display=has?'none':'';}
  $('go').onclick=async()=>{
    if(!tc){$('msg').textContent='Die Kamera läuft noch nicht.';return}
    if(!window.MediaRecorder){$('msg').textContent='Dieser Browser kann nicht aufnehmen. Bitte Chrome oder Safari nehmen.';return}
    $('go').hidden=true;$('msg').textContent='';
    for(const n of [3,2,1]){$('cd').textContent=n;await new Promise(r=>setTimeout(r,800))}$('cd').textContent='';
    const mt=mime(),big=tc.canvas.width*tc.canvas.height>1.5e6;
    rec=new MediaRecorder(tc.stream(30),Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:big?8000000:5000000,audioBitsPerSecond:160000}));
    chunks=[];rec.ondataavailable=e=>{if(e.data&&e.data.size)chunks.push(e.data)};
    rec.onstop=()=>{blob=new Blob(chunks,{type:(rec.mimeType||mt||'video/webm').split(';')[0]});saved=false;
      const url=URL.createObjectURL(blob);$('play').src=url;const ext=blob.type.includes('mp4')?'mp4':'webm';
      const d=new Date(),stamp=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+'_'+String(d.getHours()).padStart(2,'0')+'-'+String(d.getMinutes()).padStart(2,'0');
      $('dl').href=url;$('dl').download=(tgt&&tgt.ig?tgt.ig.day+'_'+(tgt.ig.kind==='reel'?'Reel':'Story')+'_'+tgt.ig.t.slice(0,30):tgt&&tgt.clip?'Uebung_'+tgt.clip.n+'_'+stamp:(mvFree?'Bewegung_'+stamp:'TalkingHead_'+(tgt?tgt.t.t:'Eigener-Text')+'_'+(tgt?tgt.lang:lang).toUpperCase()+'_'+stamp)).replace(/[^\wäöüÄÖÜß-]+/g,'-')+'.'+ext;
      $('rd').hidden=true;$('stop').hidden=true;run=false;showAfter();
      $('msg').className='msg';$('msg').textContent='Schau es dir kurz an. Passt es, '+(tgt&&BUNNY?'tippe auf „Hochladen“ oder lade es herunter.':'lade es herunter.');
      if(lock){lock.release().catch(()=>{});lock=null}};
    fromFile=false;tgt=mv?{clip:mv}:(ig?{ig:ig}:(cur?{t:cur,lang:lang}:null));rec.start(1000);t0=performance.now();$('stop').hidden=false;$('rd').hidden=false;
    if($('prAuto').checked&&!pr.hidden){run=true}
    try{lock=await navigator.wakeLock.request('screen')}catch(e){}
    (function loop(){if(!rec||rec.state!=='recording')return;$('rt').textContent=fmt((performance.now()-t0)/1000);tick=requestAnimationFrame(loop)})();
  };
  $('stop').onclick=()=>{if(rec&&rec.state==='recording')rec.stop()};
  $('again').onclick=()=>{if(!saved&&!confirm('Die Aufnahme ist noch nicht gespeichert. Trotzdem neu aufnehmen?'))return;blob=null;saved=true;$('play').removeAttribute('src');$('msg').textContent='';$('prog').hidden=true;pr.scrollTop=0;showAfter();};
  $('dl').addEventListener('click',()=>{saved=true});
  window.addEventListener('beforeunload',e=>{if(blob&&!saved){e.preventDefault();e.returnValue=''}});
  $('up').onclick=()=>{
    if(!blob||!tgt)return;if(typeof tus==='undefined'&&!tgt.ig){$('msg').textContent='Der Upload konnte nicht laden. Bitte Seite neu laden (vorher herunterladen!).';return}
    if(tgt.ig){igSave(tgt.ig);return}
    $('up').disabled=true;$('msg').className='msg';$('msg').textContent='Wird vorbereitet …';
    if(tgt.clip){const C=tgt.clip,fill2=$('prog').querySelector('i');
      post('mm_mv_clip_new',{c:C.id}).then(j=>{if(j.error){$('msg').textContent=j.error;$('up').disabled=false;return}
        $('prog').hidden=false;new tus.Upload(blob,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
          headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},metadata:{filetype:blob.type,title:$('dl').download},
          onError:()=>{$('msg').textContent='Upload unterbrochen. Tipp nochmal auf „Hochladen“ (oder lade es herunter).';$('up').disabled=false},
          onProgress:(a,t)=>{const p=Math.round(a/t*100);fill2.style.width=p+'%';$('msg').textContent='Lädt hoch … '+p+' % (Seite offen lassen)'},
          onSuccess:()=>{post('mm_mv_clip_set',{c:C.id,guid:j.guid}).then(r=>{saved=true;C.ok=true;$('up').disabled=false;$('up').hidden=true;$('prog').hidden=true;
            $('msg').className='msg ok';$('msg').textContent='✓ Clip hochgeladen. Er steckt jetzt in '+(r.used||1)+(r.used===1?' Video':' Videos')+'. Nächste Übung oben auswählen.';})}}).start()})
        .catch(()=>{$('msg').textContent='Das hat nicht geklappt. Bitte herunterladen und später hochladen.';$('up').disabled=false});return}
    const T=tgt.t,id=T.id,lg=tgt.lang,fill=$('prog').querySelector('i');
    post('mm_mv_video_new',{id:id,lang:lg}).then(j=>{if(j.error){$('msg').textContent=j.error;$('up').disabled=false;return}
      $('prog').hidden=false;const up=new tus.Upload(blob,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
        headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},metadata:{filetype:blob.type,title:$('dl').download},
        onError:()=>{$('msg').textContent='Upload unterbrochen. Tipp nochmal auf „Hochladen“ (oder lade es herunter).';$('up').disabled=false},
        onProgress:(a,t)=>{const p=Math.round(a/t*100);fill.style.width=p+'%';$('msg').textContent='Lädt hoch … '+p+' % (Seite offen lassen)'},
        onSuccess:()=>{post('mm_mv_video_set',{id:id,lang:lg,guid:j.guid}).then(()=>{saved=true;$('up').disabled=false;$('up').hidden=true;
          if(lg==='de')T.vde=true;else T.ven=true;
          $('msg').className='msg ok';$('msg').textContent='✓ Hochgeladen. Bunny wandelt es in ein paar Minuten um.'+((T.vde&&T.ven)?' Beide Sprachen sind da, das Video ist fertig.':' Jetzt noch '+(lg==='de'?'Englisch':'Deutsch')+'?');})}});
      up.start()}).catch(()=>{$('msg').textContent='Das hat nicht geklappt. Bitte herunterladen und später hochladen.';$('up').disabled=false});
  };

  // =================== Bearbeiten ===================
  let edOpen=null,fromFile=false;
  function editor(b,dur,info,done){
    if(typeof StudioEdit==='undefined'){$('msg').textContent='Der Bearbeiten-Baustein konnte nicht laden. Bitte Seite neu laden (vorher herunterladen!).';return}
    document.body.classList.add('editing');$('edWrap').hidden=false;$('edInfo').textContent=info||'';window.scrollTo({top:0,behavior:'smooth'});
    const back=()=>{document.body.classList.remove('editing');$('edWrap').hidden=true;edOpen=null};
    edOpen=StudioEdit.open({blob:b,dur:dur,mount:$('ed'),onDone:(nb,inf)=>{back();done(nb,inf)},onCancel:back});}
  function setBlob(nb,suffix){blob=nb;saved=false;const url=URL.createObjectURL(nb);$('play').src=url;const ext=nb.type.includes('mp4')?'mp4':'webm';
    $('dl').href=url;$('dl').download=String($('dl').download||'Video').replace(/(_bearbeitet)?\.\w+$/,'')+(suffix||'')+'.'+ext;$('prog').hidden=true;showAfter();}
  $('edit').onclick=()=>{if(!blob)return;const d=$('play').duration;
    editor(blob,isFinite(d)?d:0,tgt?(tgt.ig?'Instagram '+(tgt.ig.kind==='reel'?'Reel':'Story')+' für '+tgt.ig.l.split(' · ')[0]+'. Tipp: Format „Hoch 9:16“.':tgt.clip?'Übung „'+tgt.clip.n+'“':'„'+tgt.t.t+'“ · '+(tgt.lang==='de'?'Deutsch':'Englisch'))+'. Danach kannst du direkt hochladen.':'Danach herunterladen.',(nb,inf)=>{
      setBlob(nb,'_bearbeitet');$('msg').className='msg ok';$('msg').textContent='✓ Bearbeitet ('+fmt(inf.dur)+(inf.music?', mit Musik':'')+'). Schau es dir an, dann '+(tgt&&BUNNY?'hochladen oder herunterladen.':'herunterladen.');});};
  $('openF').onchange=e=>{const f=e.target.files[0];e.target.value='';if(!f)return;
    if(blob&&!saved&&!confirm('Die letzte Aufnahme ist noch nicht gespeichert. Trotzdem ein anderes Video öffnen?'))return;
    if(clipm)setMode('ganz');fromFile=true;tgt=mv?{clip:mv}:(ig?{ig:ig}:(cur?{t:cur,lang:lang}:null));
    $('dl').download=f.name.replace(/\.\w+$/,'')+'.mp4';setBlob(f,'');saved=true;
    $('msg').className='msg';$('msg').textContent=tgt?'Das Video gehört dann zu: '+(tgt.ig?'Instagram '+tgt.ig.l:tgt.clip?'Übung „'+tgt.clip.n+'“':'„'+tgt.t.t+'“ ('+(lang==='de'?'Deutsch':'Englisch')+')')+'. Oben bei „Was nimmst du auf?“ kannst du das noch ändern.':'';
    $('edit').click();};
  window.addEventListener('beforeunload',e=>{if(edOpen){e.preventDefault();e.returnValue=''}});

  // Instagram: Video in 8-MB-Stücken auf den eigenen Server, am Tag dann unter „Instagram täglich“ teilen
  async function igSave(G){const b=blob,CH=8*1024*1024,n=Math.max(1,Math.ceil(b.size/CH)),fill=$('prog').querySelector('i'),ext=b.type.includes('webm')?'webm':'mp4';
    $('up').disabled=true;$('prog').hidden=false;$('msg').className='msg';
    try{for(let i=0;i<n;i++){const f=new FormData();f.append('csrf',csrf);f.append('action','mm_ig_up');f.append('id',G.id);f.append('n',i);f.append('ext',ext);if(i===n-1)f.append('last','1');
        f.append('b',b.slice(i*CH,(i+1)*CH),'teil');
        let j=null;for(let t=0;t<3&&!j;t++){try{j=await fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}catch(e){await new Promise(r=>setTimeout(r,2000))}}
        if(!j||j.error)throw new Error(j?j.error:'Netz');
        fill.style.width=Math.round((i+1)/n*100)+'%';$('msg').textContent='Wird gespeichert … '+Math.round((i+1)/n*100)+' %';}
      saved=true;G.has=true;$('up').hidden=true;$('prog').hidden=true;$('msg').className='msg ok';
      $('msg').innerHTML='✓ Gespeichert für '+esc(G.l.split(' · ')[0])+' Am Tag selbst unter <a href="?view=insta#e'+G.id+'">📱 Instagram täglich</a> auf „Teilen“ tippen. Nächsten Tag oben auswählen.';
      const o=document.querySelector('#what option[value="i'+G.id+'"]');if(o&&!/✓$/.test(o.textContent))o.textContent+=' ✓';}
    catch(e){$('msg').textContent='Speichern hat nicht geklappt'+(e.message&&e.message!=='Netz'?' ('+e.message+')':'')+'. Bitte nochmal tippen oder herunterladen.';}
    $('up').disabled=false;}

  // Vorschau bleibt beim Einstellen immer sichtbar (oben fixiert), am Handy kleiner
  function vh(){return matchMedia('(max-width:980px)').matches?36:66}
  addEventListener('resize',()=>{if(tc){tc.opts.maxVh=vh();tc.emit('layout')}});
  $('play').style.maxHeight=vh()+'vh';
  // =================== Absatz für Absatz ===================
  // Text an Leerzeilen in Absätze teilen, je Absatz ein kurzer Clip. Die Stimme wird mitgemessen
  // (wann du anfängst und aufhörst zu sprechen), damit die Untertitel später genau passen.
  clipm=ls.get('st-mode','ganz')==='clips';
  function setMode(m){clipm=m==='clips';ls.set('st-mode',m);document.querySelectorAll('[data-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.mode===m)));
    document.body.classList.toggle('clipm',clipm);if(!clipm){cs=null;slab.hidden=true}blob=null;showAfter();clipUi();setText();}
  document.querySelectorAll('[data-mode]').forEach(b=>b.onclick=()=>{if(cs&&cs.rec){return}if(clips.some(c=>c&&!c.saved)&&!confirm('Deine Clips sind noch nicht gesichert. Trotzdem wechseln?'))return;clips=[];setMode(b.dataset.mode)});
  const stamp=()=>{const d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+'_'+String(d.getHours()).padStart(2,'0')+'-'+String(d.getMinutes()).padStart(2,'0')};
  const slug=t=>String(t).normalize('NFKD').replace(/[̀-ͯ]/g,'').replace(/ß/g,'ss').replace(/[^A-Za-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,40)||'Clip';
  let baseName='';
  function showSlide(){const P=paras(lang),i=cs?cs.i:0,t=P[i]||'';
    pr.querySelector('.pr-t').innerHTML=t?textHtml(t):'<span class="hint">'+(P.length?'Fertig ✓':'Füge deinen Text beim Teleprompter ein. Eine Leerzeile trennt die Absätze.')+'</span>';pr.scrollTop=0;
    slab.hidden=!P.length;slab.textContent='Absatz '+Math.min(i+1,P.length)+' von '+P.length;}
  function clipUi(){
    const P=paras(lang),n=P.length,recIng=!!(cs&&cs.rec);
    ['go','stop','up','dl','again','edit'].forEach(id=>{if(clipm)$(id).hidden=true});if(clipm){$('play').hidden=true;$('stage').style.display=''}
    $('cgo').hidden=!clipm||recIng;$('cnext').hidden=!clipm||!recIng;$('credo').hidden=!clipm||!recIng;$('cend').hidden=!clipm||!recIng;
    if(clipm&&!recIng){const first=clips.findIndex((c,i)=>i<n&&!c);const start=clips.length&&first<0&&clips.filter(Boolean).length>=n?-1:(first<0?Math.min(clips.length,n):first);
      $('cgo').dataset.start=Math.max(0,start);$('cgo').textContent=n?(start<0?'● Alles nochmal ab Absatz 1':'● Absatz '+(start+1)+' von '+n+' aufnehmen'):'● Aufnehmen';if(start<0)$('cgo').dataset.start=0;}
    if(clipm&&recIng)$('cnext').textContent=cs.i+1<n?'✓ Fertig · Absatz '+(cs.i+2)+' ›':'✓ Fertig · letzter Absatz';
    $('clipBox').hidden=!clipm;$('subBox').hidden=!clipm||!clips.some(Boolean);
    $('clipInfo').textContent=clipm?(n?n+' Absätze im Text. Du liest einen Absatz, tippst auf „Fertig“ und der nächste startet nach 3-2-1 von selbst. Leertaste oder → geht auch.':'Noch kein Text. Füge ihn beim Teleprompter ein, eine Leerzeile trennt die Absätze.'):'';
    renderClips();}
  // Stimme messen
  let ac=null,an=null,abuf=null;
  function meter(){try{if(!ac){ac=new (window.AudioContext||window.webkitAudioContext)();}ac.resume();if(!an&&raw&&raw.getAudioTracks().length){const src=ac.createMediaStreamSource(new MediaStream(raw.getAudioTracks()));an=ac.createAnalyser();an.fftSize=1024;src.connect(an);abuf=new Float32Array(an.fftSize)}}catch(e){an=null}}
  async function startClip(i,single){
    const P=paras(lang);if(!P.length){$('msg').textContent='Füge zuerst deinen Text ein.';return}
    if(!tc){$('msg').textContent='Die Kamera läuft noch nicht.';return}
    if(!baseName)baseName=slug(cur?cur.t:'Story')+'_'+lang.toUpperCase()+'_'+stamp();
    meter();cs={i:i,single:!!single,rec:null,discard:false};setText();clipUi();
    $('cgo').hidden=true;$('msg').textContent='';
    for(const n of [3,2,1]){$('cd').textContent=n;await new Promise(r=>setTimeout(r,650))}$('cd').textContent='';
    if(!cs)return;
    const mt=mime(),big=tc.canvas.width*tc.canvas.height>1.5e6,ch=[];
    const r=new MediaRecorder(tc.stream(30),Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:big?8000000:5000000,audioBitsPerSecond:160000}));
    const st={t0:0,s:null,e:null};
    r.ondataavailable=e=>{if(e.data&&e.data.size)ch.push(e.data)};
    r.onstop=()=>{const dur=(performance.now()-st.t0)/1000;const me=r._cs;r.stream.getVideoTracks().forEach(t=>t.stop());
      if(me.discard)return;
      const type=(r.mimeType||mt||'video/webm').split(';')[0],b=new Blob(ch,{type}),ext=type.includes('mp4')?'mp4':'webm';
      const old=clips[me.i];if(old){URL.revokeObjectURL(old.url);if(old.sub)URL.revokeObjectURL(old.sub.url)}
      clips[me.i]={i:me.i,blob:b,url:URL.createObjectURL(b),ext:ext,dur:dur,s:st.s==null?.2:Math.max(0,st.s-.15),e:st.e==null?dur-.2:Math.min(dur,st.e+.25),lang:lang,text:paras(lang)[me.i]||'',sub:null,saved:false};
      renderClips();clipUi();};
    r._cs=cs;cs.rec=r;r.start(1000);st.t0=performance.now();$('rd').hidden=false;clipUi();
    try{if(!lock)lock=await navigator.wakeLock.request('screen')}catch(e){}
    (function loop(){if(!cs||cs.rec!==r||r.state!=='recording')return;const t=(performance.now()-st.t0)/1000;$('rt').textContent=fmt(t);
      if(an){an.getFloatTimeDomainData(abuf);let q=0;for(const v of abuf)q+=v*v;const rms=Math.sqrt(q/abuf.length);if(rms>.02){if(st.s==null)st.s=t;st.e=t}}
      // langer Absatz: Text läuft sanft mit
      if(pr.scrollHeight>pr.clientHeight+4&&!pr.hidden)pr.scrollTop+=sp/60;
      requestAnimationFrame(loop)})();
  }
  function stopRec(discard){if(cs&&cs.rec&&cs.rec.state==='recording'){cs.rec._cs.discard=!!discard;cs.rec.stop()}$('rd').hidden=true}
  function finish(){stopRec(false);cs=null;slab.hidden=false;showSlide();slab.textContent='Fertig ✓';if(lock){lock.release().catch(()=>{});lock=null}clipUi();
    const n=clips.filter(Boolean).length;$('msg').className='msg ok';$('msg').textContent=n?'✓ '+n+' Clip'+(n>1?'s':'')+' fertig. Rechts kannst du sie ansehen, neu aufnehmen, sichern oder Untertitel dazu machen.':'';}
  $('cgo').onclick=()=>startClip(+($('cgo').dataset.start||0));
  $('cnext').onclick=()=>{if(!cs)return;const i=cs.i,single=cs.single;stopRec(false);const n=paras(lang).length;
    if(single||i+1>=n){finish();return}startClip(i+1)};
  $('credo').onclick=()=>{if(!cs)return;const i=cs.i,single=cs.single;stopRec(true);startClip(i,single)};
  $('cend').onclick=()=>finish();
  document.addEventListener('keydown',e=>{if(!clipm||!cs||!cs.rec)return;if(/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName))return;
    if(e.code==='Space'||e.code==='ArrowRight'||e.code==='Enter'){e.preventDefault();e.stopImmediatePropagation();$('cnext').click()}},true);
  function fileName(c,sub){return baseName+'_'+String(c.i+1).padStart(2,'0')+(sub?'_Untertitel-'+sub.toUpperCase():'')+'.'+c.ext}
  function renderClips(){const box=$('clips');if(!clipm){box.innerHTML='';return}const P=paras(lang),n=Math.max(P.length,clips.length);let h='';
    for(let i=0;i<n;i++){const c=clips[i];
      h+='<div class="clip'+(c?'':' miss')+'">'+(c?'<video src="'+c.url+'#t=0.1" controls playsinline preload="metadata"></video>':'<div style="width:120px;height:90px;border-radius:12px;background:#EADFDE;display:flex;align-items:center;justify-content:center;color:#7D7076;font-size:14px">noch nicht</div>')
        +'<div><b>Absatz '+(i+1)+(c?' · '+fmt(c.dur):'')+'</b><p>'+esc((c?c.text:P[i])||'')+'</p><div class="cb">'
        +(c?'<a href="'+c.url+'" download="'+esc(fileName(c))+'" data-saved="'+i+'">⬇ Laden</a>':'')
        +(c&&c.sub?'<a class="sub" href="'+c.sub.url+'" download="'+esc(fileName(c,c.sub.lang))+'" data-saved="'+i+'">⬇ mit Untertitel</a>':'')
        +(c?'<button type="button" class="ed" data-edit="'+i+'">✂️ Bearbeiten</button>':'')
        +(c&&canShare([c.blob])?'<button type="button" data-share="'+i+'">📲 Sichern</button>':'')
        +'<button type="button" data-retake="'+i+'">'+(c?'↺ Neu aufnehmen':'● Aufnehmen')+'</button></div></div></div>';}
    box.innerHTML=h;const any=clips.some(Boolean);$('clAll').hidden=!any;$('shareAll').hidden=!any||!canShare(clips.filter(Boolean).map(c=>c.blob));$('zipSub').hidden=!clips.some(c=>c&&c.sub);
    box.querySelectorAll('[data-retake]').forEach(b=>b.onclick=()=>{if(cs&&cs.rec)return;startClip(+b.dataset.retake,true);window.scrollTo({top:0,behavior:'smooth'})});
    box.querySelectorAll('[data-saved]').forEach(a=>a.addEventListener('click',()=>{const c=clips[+a.dataset.saved];if(c)c.saved=true}));
    box.querySelectorAll('[data-share]').forEach(b=>b.onclick=()=>shareFiles([clips[+b.dataset.share]]));
    box.querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>{const c=clips[+b.dataset.edit];if(!c||(cs&&cs.rec))return;
      editor(c.blob,c.dur,'Absatz '+(c.i+1)+': Licht, Ausschnitt, Musik. Danach ersetzt die bearbeitete Fassung den Clip.',(nb,inf)=>{
        URL.revokeObjectURL(c.url);if(c.sub){URL.revokeObjectURL(c.sub.url);c.sub=null}
        c.blob=nb;c.url=URL.createObjectURL(nb);c.ext=nb.type.includes('mp4')?'mp4':'webm';c.s=Math.max(0,c.s-inf.a);c.e=Math.max(c.s+.3,Math.min(inf.dur,c.e-inf.a));c.dur=inf.dur;c.saved=false;
        renderClips();clipUi();$('msg').className='msg ok';$('msg').textContent='✓ Absatz '+(c.i+1)+' ist bearbeitet.'+(clips.some(x=>x&&x.sub===null)&&!$('subBox').hidden?' Untertitel bei Bedarf neu erstellen.':'');});});}
  function asFile(c,sub){const b=sub?c.sub.blob:c.blob;return new File([b],fileName(c,sub?c.sub.lang:''),{type:b.type})}
  function canShare(blobs){try{return !!(navigator.canShare&&navigator.canShare({files:blobs.map((b,i)=>new File([b],'c'+i+'.mp4',{type:b.type}))}))}catch(e){return false}}
  async function shareFiles(list){const files=list.map(c=>asFile(c,!!c.sub));try{await navigator.share({files:files});list.forEach(c=>c.saved=true)}catch(e){}}
  $('shareAll').onclick=()=>shareFiles(clips.filter(Boolean));
  async function zip(sub){if(typeof JSZip==='undefined'){$('msg').textContent='Der ZIP-Baustein konnte nicht laden. Lade die Clips bitte einzeln.';return}
    const z=new JSZip();clips.filter(Boolean).forEach(c=>{if(sub&&!c.sub)return;z.file(fileName(c,sub?c.sub.lang:''),sub?c.sub.blob:c.blob)});
    $('msg').className='msg';$('msg').textContent='ZIP wird gepackt …';const b=await z.generateAsync({type:'blob',compression:'STORE'});
    const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download=baseName+(sub?'_Untertitel':'')+'.zip';document.body.appendChild(a);a.click();a.remove();
    clips.forEach(c=>{if(c)c.saved=true});$('msg').textContent='✓ ZIP ist in deinen Downloads.';}
  $('zipAll').onclick=()=>zip(false);$('zipSub').onclick=()=>zip(true);
  window.addEventListener('beforeunload',e=>{if(clips.some(c=>c&&!c.saved)){e.preventDefault();e.returnValue=''}});

  // =================== Untertitel ===================
  let subLang=ls.get('st-sublang','')||lang, subStyle=ls.get('st-substyle','band'), subPos=ls.get('st-subpos','unten');
  const fontReady=(()=>{try{const f=new FontFace('Hanken Grotesk','url(/fonts/HankenGrotesk-VF.woff)',{weight:'100 900'});document.fonts.add(f);return f.load().catch(()=>{})}catch(e){return Promise.resolve()}})();
  function subChips(){document.querySelectorAll('[data-sl]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.sl===subLang)));
    document.querySelectorAll('[data-ss]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.ss===subStyle)));
    document.querySelectorAll('[data-sp2]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.sp2===subPos)));}
  document.querySelectorAll('[data-sl]').forEach(b=>b.onclick=()=>{subLang=b.dataset.sl;ls.set('st-sublang',subLang);subChips();subPreview()});
  document.querySelectorAll('[data-ss]').forEach(b=>b.onclick=()=>{subStyle=b.dataset.ss;ls.set('st-substyle',subStyle);subChips();subPreview()});
  document.querySelectorAll('[data-sp2]').forEach(b=>b.onclick=()=>{subPos=b.dataset.sp2;ls.set('st-subpos',subPos);subChips();subPreview()});
  const clean=t=>String(t||'').replace(/\[[^\]]*\]/g,' ').replace(/^\s*\d{1,2}:\d{2}\s*/gm,'').replace(/\s+/g,' ').trim();
  // Text in kurze Untertitel teilen und über die Sprechzeit verteilen
  function cues(text,s,e,maxC){const w=clean(text).split(' ').filter(Boolean),out=[];let cur=[];
    const push=()=>{if(cur.length)out.push(cur.join(' '));cur=[]};
    for(const x of w){const nx=cur.concat(x).join(' ');if(nx.length>maxC&&cur.length)push();cur.push(x);if(/[.!?…:;]$/.test(x)&&cur.join(' ').length>maxC*.45)push();}
    push();const wt=out.map(t=>t.length+6),tot=wt.reduce((a,b)=>a+b,0)||1;let t=s;
    return out.map((tx,i)=>{const d=(e-s)*wt[i]/tot,c={t:tx,a:t,b:t+d};t+=d;return c})}
  function wrap(g,t,maxW){const w=t.split(' '),L=[];let l='';for(const x of w){const n=l?l+' '+x:x;if(g.measureText(n).width>maxW&&l){L.push(l);l=x}else l=n}if(l)L.push(l);return L}
  function drawCue(g,W,H,text){if(!text)return;const port=H>W,fs=Math.round(Math.min(W,H)*(port?.064:.058));
    g.font='600 '+fs+'px "Hanken Grotesk","Outfit",system-ui,sans-serif';g.textAlign='center';g.textBaseline='middle';
    const L=wrap(g,text,W*(port?.8:.7)),lh=fs*1.28,cy=subPos==='mitte'?H*.62:H*(port?.74:.84),y0=cy-(L.length-1)*lh/2;
    L.forEach((l,i)=>{const y=y0+i*lh,tw=g.measureText(l).width;
      if(subStyle!=='weiss'){const px=fs*.45,py=fs*.24,r=fs*.38;g.fillStyle=subStyle==='band'?'rgba(74,58,65,.88)':'rgba(251,245,247,.94)';
        g.beginPath();if(g.roundRect)g.roundRect(W/2-tw/2-px,y-lh/2+py*.3,tw+px*2,lh-py*.1,r);else g.rect(W/2-tw/2-px,y-lh/2+py*.3,tw+px*2,lh-py*.1);g.fill()}
      if(subStyle==='weiss'){g.shadowColor='rgba(0,0,0,.6)';g.shadowBlur=fs*.35;g.lineWidth=fs*.08;g.strokeStyle='rgba(46,37,41,.55)';g.strokeText(l,W/2,y)}
      g.fillStyle=subStyle==='hell'?'#4A3A41':'#FFFFFF';g.fillText(l,W/2,y);g.shadowBlur=0;g.shadowColor='transparent';});}
  function subText(i){const P=paras(subLang);return P[i]||''}
  function subPreview(){const cv=$('subPrev');if(!cv||!tc)return;const W=tc.canvas.width||1280,H=tc.canvas.height||720,k=Math.min(1,640/Math.max(W,H));
    cv.width=Math.round(W*k);cv.height=Math.round(H*k);const g=cv.getContext('2d');g.fillStyle='#6B5658';g.fillRect(0,0,cv.width,cv.height);
    try{g.drawImage(tc.canvas,0,0,cv.width,cv.height)}catch(e){}
    const t=clean(subText(0))||(subLang==='en'?'This is how your subtitles will look.':'So sehen deine Untertitel aus.');
    fontReady.then(()=>drawCue(g,cv.width,cv.height,cues(t,0,1,port()?24:38)[0].t));
    const a=paras(lang).length,b=paras(subLang).length;
    $('subHint').textContent=subLang!==lang&&a!==b?'Achtung: Der '+(subLang==='en'?'englische':'deutsche')+' Text hat '+b+' Absätze, aufgenommen sind '+a+'. Bitte die Absätze gleich aufteilen (oben bei '+(subLang==='en'?'Englisch':'Deutsch')+').':(subLang!==lang?'Die Untertitel nehmen den Absatz mit derselben Nummer aus dem '+(subLang==='en'?'englischen':'deutschen')+' Text.':'');}
  const port=()=>tc&&tc.canvas.height>tc.canvas.width;
  // Clip abspielen, Bild + Untertitel neu zeichnen und mit Ton aufnehmen (läuft in Echtzeit)
  async function burn(c,lang2){
    await fontReady;const text=subText(c.i);if(!clean(text))return false;
    const v=document.createElement('video');v.src=c.url;v.muted=true;v.playsInline=true;v.setAttribute('playsinline','');
    await new Promise((ok,no)=>{v.onloadedmetadata=ok;v.onerror=no});
    const W=v.videoWidth,H=v.videoHeight,cv=document.createElement('canvas');cv.width=W;cv.height=H;const g=cv.getContext('2d');
    const list=cues(text,c.s,Math.max(c.s+.5,c.e),H>W?24:38);
    ac=ac||new (window.AudioContext||window.webkitAudioContext)();await ac.resume();
    let buf=null;try{buf=await ac.decodeAudioData(await c.blob.arrayBuffer())}catch(e){buf=null}
    const dest=ac.createMediaStreamDestination(),out=cv.captureStream(30);if(buf)dest.stream.getAudioTracks().forEach(t=>out.addTrack(t));
    const mt=mime(),ch=[],r=new MediaRecorder(out,Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:W*H>1.5e6?8000000:5000000,audioBitsPerSecond:160000}));
    r.ondataavailable=e=>{if(e.data&&e.data.size)ch.push(e.data)};
    const done=new Promise(ok=>r.onstop=ok);
    const draw=()=>{g.drawImage(v,0,0,W,H);const t=v.currentTime,q=list.find(x=>t>=x.a&&t<x.b);if(q)drawCue(g,W,H,q.t)};
    draw();r.start(500);
    let src=null;if(buf){src=ac.createBufferSource();src.buffer=buf;src.connect(dest)}
    await v.play();if(src)src.start();
    await new Promise(ok=>{const step=()=>{draw();if(v.ended||v.currentTime>=c.dur-.03){ok();return}if(v.requestVideoFrameCallback)v.requestVideoFrameCallback(step);else requestAnimationFrame(step)};step();v.onended=ok});
    draw();await new Promise(r2=>setTimeout(r2,120));r.stop();await done;try{if(src)src.stop()}catch(e){}
    const type=(r.mimeType||mt||'video/webm').split(';')[0],b=new Blob(ch,{type});
    if(c.sub)URL.revokeObjectURL(c.sub.url);c.sub={blob:b,url:URL.createObjectURL(b),lang:lang2};return true;}
  $('subAll').onclick=async()=>{const list=clips.filter(Boolean);if(!list.length)return;$('subAll').disabled=true;const bar=$('subProg'),fill=bar.querySelector('i');bar.hidden=false;
    let ok=0;for(let k=0;k<list.length;k++){$('msg').className='msg';$('msg').textContent='Untertitel für Clip '+(k+1)+' von '+list.length+' … (läuft so lang wie der Clip, Seite offen lassen)';
      try{if(await burn(list[k],subLang))ok++}catch(e){console.warn(e)}fill.style.width=Math.round((k+1)/list.length*100)+'%';}
    bar.hidden=true;$('subAll').disabled=false;renderClips();$('msg').className='msg ok';$('msg').textContent='✓ Untertitel fertig ('+ok+' Clips). Bei jedem Clip gibt es jetzt „⬇ mit Untertitel“.';};
  subChips();document.body.classList.toggle('clipm',clipm);setMode(clipm?'clips':'ganz');
  window.__studio=()=>tc;
  choose();startCam();
  if(!/[?&](l|c|ig|move)=/.test(location.search))pkOpen(true);
})();
</script>
<?php
    });
    exit;
}

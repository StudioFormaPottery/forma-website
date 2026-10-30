<?php
// „🎥 Talking-Head-Studio“ (Vivien, 28.10.2026): Talking Heads direkt im Browser aufnehmen, mit Kamera-Look
// (Haut weich, Augenringe aufhellen, Hintergrund unscharf, Licht), Hilfe für die Kopfposition und Teleprompter
// oben im Bild (nahe an der Kamera, damit der Blick in die Linse geht). Hilfslinien und Text sind nie in der Aufnahme.
// Danach herunterladen oder direkt in die Lektion hochladen (Bunny, gleiche Aktionen wie „Videos hochladen“).
// ?view=studio[&l=ID][&lang=de|en]. Ohne Lektion: eigener Text (z. B. tägliche Story), Format 9:16 wählbar.
declare(strict_types=1);

// „💛 Meine Looks“ (Vivien, 28.10.2026: „Filter speichern“): eigene Kamera-Looks / Bearbeiten-Filter auf dem Server,
// damit sie am Handy und am Computer (beide Domains) gleich da sind. slot: cam (Talking Head), move (Übungen), edit (Bearbeiten).
if ($action === 'mm_looks_get' || $action === 'mm_looks_set') {
    header('Content-Type: application/json; charset=utf-8');
    $slot = in_array($_POST['slot'] ?? '', ['cam', 'move', 'edit'], true) ? (string)$_POST['slot'] : 'cam';
    $key = 'studio_looks_' . $slot;
    if ($action === 'mm_looks_set') {
        $in = json_decode((string)($_POST['list'] ?? '[]'), true);
        $clean = [];
        foreach (is_array($in) ? array_slice($in, 0, 40) : [] as $x) {
            if (!is_array($x) || !is_array($x['s'] ?? null)) continue;
            $n = trim(mb_substr(strip_tags((string)($x['n'] ?? '')), 0, 40));
            if ($n === '') continue;
            $v = [];
            foreach ($x['s'] as $k => $val) {
                if (!preg_match('/^[a-zA-Z]{1,20}$/', (string)$k)) continue;
                if (is_int($val) || is_float($val) || is_bool($val) || (is_string($val) && preg_match('/^[a-z0-9:.]{0,16}$/', $val))) $v[$k] = $val;
            }
            $clean[] = ['n' => $n, 's' => $v];
        }
        forma_set_setting($pdo, $key, json_encode($clean, JSON_UNESCAPED_UNICODE));
        echo json_encode(['ok' => 1, 'list' => $clean], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['list' => json_decode(forma_setting($pdo, $key) ?? '[]', true) ?: []], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($view === 'studio') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    try { require_once dirname(__DIR__) . '/api/academy-lib.php'; ac_schema($pdo); } catch (Throwable $e) {} // legt das Academy-Willkommensvideo an
    $talks = [];
    foreach (['academy' => 'Academy', 'method' => 'Method', 'unfold' => 'Unfold', 'natal' => 'Prä- & Postnatal'] as $aud => $an) {
        foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) {
            if (mv_lesson_is_clips($l)) continue;
            $talks[] = ['id' => (int)$l['id'], 'g' => $an, 't' => (string)$l['title_de'], 'te' => (string)($l['title_en'] ?: $l['title_de']),
                'de' => (string)$l['script_de'], 'en' => (string)$l['script_en'], 'vde' => $l['video_de'] !== '', 'ven' => $aud !== 'method' ? $l['video_de'] !== '' : $l['video_en'] !== '', 'do' => $aud !== 'method', 'due' => (string)$l['due'], // nur Method auf Englisch (Vivien, 30.10.2026)
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
        'x' => $t['vde'] && $t['ven'] ? '' : (!empty($t['do']) ? ' (nur Deutsch)' : ($t['vde'] ? ' (Englisch fehlt)' : ($t['ven'] ? ' (Deutsch fehlt)' : ' (DE + EN)'))), 'g' => $t['g']];
    foreach ($moves as $m) $queue[] = ['v' => 'c' . $m['id'], 'ok' => $m['ok'], 'due' => $m['due'], 'ico' => '🧘', 'k' => $m['k'], 'n' => $m['n'],
        'x' => $m['for'] ? ' · für ' . $m['for'][0] . (count($m['for']) > 1 ? ' +' . (count($m['for']) - 1) : '') : '', 'g' => ''];
    // Academy-Willkommensvideo ganz oben, solange es fehlt (Vivien, 28.10.2026)
    usort($queue, fn($a, $b) => [$a['g'] === 'Academy' && !$a['ok'] ? 0 : 1, $a['due'] === '' ? '9' : $a['due'], $a['ico'], $a['n']] <=> [$b['g'] === 'Academy' && !$b['ok'] ? 0 : 1, $b['due'] === '' ? '9' : $b['due'], $b['ico'], $b['n']]);
    foreach ($queue as &$q) $q['l'] = ($q['due'] === '' ? 'ohne Datum' : (!$q['ok'] && $q['due'] < $today ? '⚠ überfällig · ' . $dShort($q['due']) : 'bis ' . $dShort($q['due'])))
        . ' · ' . $q['ico'] . ' ' . $q['k'] . ' · ' . $q['n'] . ($q['g'] !== '' ? ' (' . $q['g'] . ')' : '') . $q['x'];
    unset($q);
    $selC = (int)($_GET['c'] ?? 0);
    $lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'de';
    $bunny = mv_bunny_ok();
    // Instagram täglich (Storys & Reels, ?view=insta): alles ab gestern, was noch nicht gepostet ist
    ig_schema($pdo);
    $igs = [];
    $s = $pdo->prepare("SELECT * FROM mm_insta WHERE day >= ? AND status <> 'gepostet' AND kind IN ('reel','story') ORDER BY day, kind DESC, id LIMIT 90"); $s->execute([date('Y-m-d', strtotime('-1 day'))]);
    foreach ($s->fetchAll() as $r) $igs[] = ['id' => (int)$r['id'], 'day' => $r['day'], 'kind' => $r['kind'], 'acc' => IG_ACC[$r['acc']] ?? 'Privat', 't' => ig_title($r),
        'text' => trim(preg_split('/\n\s*Caption:/u', (string)$r['text'])[0]), 'music' => (string)$r['music'], 'has' => $r['file'] !== '', 'l' => ig_wd($r['day']) . ' · ' . ($r['kind'] === 'reel' ? '🎬 Reel' : '⭕ Story') . ' · ' . ig_title($r) . ($r['file'] !== '' ? ' ✓' : '')];
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
.pr{position:absolute;left:0;right:0;top:0;height:42%;min-height:120px;overflow:hidden;background:rgba(36,28,32,.8);color:#FBF5F7;z-index:7;pointer-events:auto;cursor:pointer;border-radius:22px 22px 0 0}
.pr::after{content:'';position:absolute;left:0;right:0;bottom:0;height:30%;background:linear-gradient(rgba(36,28,32,0),rgba(36,28,32,.95));pointer-events:none}
.pr[hidden]{display:none}
/* Nur Teleprompter (Vivien, 28.10.2026: filmt mit der iPhone-Kamera-App, liest am MacBook) */
#tpo{position:fixed;inset:0;z-index:190;background:#1F191C;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;padding:20px 16px calc(24px + env(safe-area-inset-bottom))}
#tpo[hidden]{display:none}
.tpo-cd{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:500 min(30vw,200px)/1 "Cormorant Garamond",Georgia,serif;color:#fff;pointer-events:none}
.tpo-cd:empty{display:none}
.tpo-bar{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;align-items:center}
.tpo-bar button{font:inherit;font-size:17px;min-height:48px;padding:10px 18px;border-radius:999px;border:1.5px solid rgba(251,248,244,.4);background:rgba(251,248,244,.1);color:#fff;cursor:pointer}
.tpo-bar button.main{background:#FBF5F7;color:#4A3A41;border-color:#FBF5F7;min-width:150px}
.tpo-bar span{color:#E8D6CA;font-size:16px}
.tpo-tip{margin:12px 0 0;color:rgba(251,248,244,.6);font-size:15px;text-align:center}
.tpo-hint{position:absolute;top:26%;left:50%;transform:translate(-50%,0);max-width:520px;width:90%;text-align:center;color:rgba(251,248,244,.75);font-size:18px;line-height:1.5}
body.tponly .pr.top{width:min(680px,94vw)}
/* iPhone als Kamera (Continuity-Kamera, Vivien 28.10.2026) */
.iph{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:10px}
.iph button{font:inherit;font-size:17px;padding:10px 18px;border-radius:999px;border:1.5px solid #7D5E68;background:#FBF5F7;color:#7D5E68;cursor:pointer;min-height:46px}
.iph button.on{background:#7D5E68;color:#fff}
.iph-help{margin-top:10px;padding:14px 16px;border-radius:18px;background:#FBF1F0;border:1.5px solid #F0DCDA;font-size:16px;line-height:1.5;color:#4A3A41}
.iph-help ol{margin:8px 0 0 20px;padding:0}.iph-help li{margin:4px 0}
body.recording #wide,body.recording .iph,body.recording .iph-help,body.rec-ui .iph,body.rec-ui .iph-help{display:none}
/* An der Kamera (Vivien, 28.10.2026: „sieht aus, als würde ich nach unten schauen“): während der Aufnahme ganz oben, mittig unter der Linse, schmal, 3 Zeilen */
body.prtop .pr.top{display:block;position:fixed;top:0;left:50%;right:auto;transform:translateX(-50%);width:min(560px,94vw);height:calc(var(--fs,30px) * 1.4 * 3 + 22px + env(safe-area-inset-top,0px));min-height:0;padding-top:env(safe-area-inset-top,0px);border-radius:0 0 22px 22px;z-index:200;background:rgba(36,28,32,.94);box-shadow:0 8px 24px -10px rgba(0,0,0,.5)}
body.prtop .pr.top .pr-t{padding:10px 3% 60%;line-height:1.4}
body.prtop .pr.top::after{height:34%}
body.prtop .mtools{visibility:hidden}
body.recording .tc-st,body.recording .tc-hint,body.recording .tc-guide{display:none!important}
.pr-t{position:relative;padding:14px 6% 60%;white-space:pre-wrap;font:400 var(--fs,30px)/1.45 "Hanken Grotesk","Outfit",system-ui,sans-serif;text-align:center}
.pr-t .hint{color:#C9B7BD;font-size:.6em}
.cd{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:500 min(30vw,180px)/1 "Cormorant Garamond",Georgia,serif;color:#fff;text-shadow:0 2px 30px rgba(0,0,0,.5);z-index:8;pointer-events:none}
.cd:empty{display:none}
.recdot{position:absolute;right:14px;bottom:14px;z-index:8;display:flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(160,65,59,.92);color:#fff;font:500 18px/1.2 "Hanken Grotesk","Outfit",system-ui,sans-serif;font-variant-numeric:tabular-nums}
.recdot[hidden]{display:none}.recdot i{width:10px;height:10px;border-radius:50%;background:#fff;animation:bl 1s infinite}
@keyframes bl{50%{opacity:.2}}
.bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:14px}
.bar button,.bar a.b{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:58px;padding:12px 26px;border-radius:999px;font:inherit;font-size:20px;font-weight:500;cursor:pointer;border:1.5px solid #4A3A41;background:transparent;color:#4A3A41;text-decoration:none}
.bar .go{background:#A0413B;border-color:#A0413B;color:#fff}
.bar .stop{background:#4A3A41;color:#fff}
.bar .up{background:#3F6B3E;border-color:#3F6B3E;color:#fff}
.bar .sm{min-height:48px;font-size:17px;padding:10px 18px;border-color:#DDD2C6}
.bar .del,.clip .cb button.del{color:#A0413B;border-color:#E6C3BF}
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
.side .box>h2{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0;user-select:none}
.side .box>h2::after{content:"";flex:0 0 auto;width:9px;height:9px;border-right:2px solid #7D5E68;border-bottom:2px solid #7D5E68;transform:rotate(-135deg) translate(-2px,-2px);transition:transform .2s}
.side .box.shut>h2::after{transform:rotate(45deg) translate(-2px,-2px)}
.side .box:not(.shut)>h2{margin-bottom:12px}
.side .box.shut{padding-top:12px;padding-bottom:12px}
.side .box.shut>:not(h2){display:none!important}
.sp-l{font-size:16px;color:#4A3A41;min-width:110px;text-align:center}
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
/* Handy: fester Aufnahme-Bildschirm (Vivien, 28.10.2026: Bild soll fix sein, nichts verschieben, alles auf einem Bildschirm) */
.mtools,.msheet{display:none}
@media(max-width:760px),(max-height:520px) and (pointer:coarse){
 html,body{overflow-x:hidden;max-width:100%}
 body.rec-ui{overflow:hidden;overscroll-behavior:none}
 body.rec-ui .view{position:fixed;inset:0;height:100vh;height:100dvh;max-height:none;z-index:80;margin:0;padding:calc(64px + env(safe-area-inset-top)) 0 calc(96px + env(safe-area-inset-bottom));background:#1F191C;box-shadow:none;overflow:hidden;touch-action:manipulation;display:flex;flex-direction:column;justify-content:flex-start}
 body.rec-ui.sheet-open .view{justify-content:flex-start;padding-bottom:0}
 body.rec-ui #stage{margin:0 auto}
 body.rec-ui #play{max-height:calc(100dvh - 200px)!important;width:auto;max-width:100%;border-radius:0}
 body.rec-ui.sheet-open #play{max-height:calc(44dvh - 72px)!important}
 body.rec-ui .view .bar{position:fixed;left:0;right:0;bottom:0;z-index:84;margin:0;padding:14px 12px calc(14px + env(safe-area-inset-bottom));justify-content:center;background:linear-gradient(rgba(31,25,28,0),rgba(31,25,28,.94) 30%)}
 body.rec-ui .view .bar .sm,body.rec-ui .view .bar a.b{background:rgba(251,248,244,.12);border-color:rgba(251,248,244,.4);color:#fff}
 body.rec-ui .view .bar{gap:8px}
 body.rec-ui .view .bar button,body.rec-ui .view .bar a.b{min-height:46px;font-size:16px;padding:8px 16px}
 body.rec-ui .view .bar .go,body.rec-ui .view .bar .next{min-width:62%;min-height:60px;font-size:19px}
 body.rec-ui .view .msg{position:fixed;left:10px;right:10px;top:calc(70px + env(safe-area-inset-top));z-index:85;color:#fff;font-size:15px;line-height:1.4;margin:0;text-align:center;background:rgba(31,25,28,.82);border-radius:16px;padding:8px 12px}
 body.rec-ui .view .msg:empty{display:none}
 body.rec-ui .view .msg a{color:#fff}
 body.rec-ui .view .prog{position:fixed;left:18px;right:18px;top:calc(62px + env(safe-area-inset-top));z-index:86;max-width:none;margin:0}
 body.rec-ui.sheet-open .view .bar,body.rec-ui.sheet-open .view .msg,body.rec-ui.sheet-open .view .prog{display:none}
 body.rec-ui .mtools{display:flex;position:fixed;top:0;left:0;right:0;z-index:86;gap:6px;padding:calc(10px + env(safe-area-inset-top)) 10px 10px;overflow-x:auto;scrollbar-width:none;background:#1F191C}
 body.rec-ui .mtools::-webkit-scrollbar{display:none}
 .mtools button,.mtools a{flex:0 0 auto;font:inherit;font-size:15px;min-height:44px;padding:8px 13px;border-radius:999px;border:1px solid rgba(251,248,244,.3);background:rgba(251,248,244,.1);color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:5px;cursor:pointer}
 .mtools [aria-pressed=true]{background:#FBF8F4;border-color:#FBF8F4;color:#4A3A41}
 body.rec-ui .msheet{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:90;height:56dvh;background:#F2ECE4;border-radius:24px 24px 0 0;box-shadow:0 -14px 30px -10px rgba(0,0,0,.45);flex-direction:column}
 body.rec-ui .msheet[hidden]{display:none!important}
 .msh-h{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;border-bottom:1px solid #DDD2C6}
 .msh-h b{font:500 24px/1.1 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
 .msh-h button{font:inherit;font-size:16px;min-height:44px;padding:8px 18px;border-radius:999px;border:0;background:#7D5E68;color:#fff;cursor:pointer}
 .msh-b{overflow:auto;padding:12px;flex:1;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
 .msh-b>*{margin:0 0 12px!important}
 .msh-b .box>h2{display:none}
 .msh-b .pk-pane{max-height:none}
 .msh-b .pk-btn{display:none}
 body.editing .mtools,body.editing .msheet{display:none!important}
 body.rec-ui.editing{overflow:auto}
 body.rec-ui .pr-t{padding-left:5%;padding-right:5%}
 body.rec-ui .pr{height:50%;min-height:150px;border-radius:0}
}
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
.pk-days{display:flex;flex-direction:column;gap:8px}
/* Kalender in der Auswahl */
.pkc-pane{max-height:none!important;overflow:visible!important}
.pkc-wrap{display:grid;grid-template-columns:minmax(260px,330px) minmax(0,1fr);gap:16px;align-items:start}
@media(max-width:760px){.pkc-wrap{grid-template-columns:1fr}}
.pkc{background:#fff;border:1.5px solid #E6DCD2;border-radius:22px;padding:10px 10px 8px}
.pkc-h{display:flex;align-items:center;justify-content:space-between;margin:0 2px 6px}
.pkc-h b{font:500 22px/1 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.pkc-nav{width:36px;height:36px;border-radius:50%;border:1.5px solid #E6DCD2;background:#FBF8F4;color:#4A3A41;font-size:18px;cursor:pointer}
.pkc-g{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:3px}
.pkc-g .wd{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#7D7076;text-align:center;padding:2px 0 4px}
.pkc-g .d{position:relative;aspect-ratio:1;border-radius:12px;border:0;background:none;font:inherit;font-size:15px;color:#4A3A41;cursor:default;display:flex;align-items:center;justify-content:center;padding:0}
.pkc-g .d.out{color:#C9BEC1}
.pkc-g .d.today{box-shadow:0 0 0 1.5px #7D5E68 inset}
.pkc-g .d.has{cursor:pointer;font-weight:500}
.pkc-g .d.u0{background:#7D5E68;color:#fff}.pkc-g .d.u1{background:#9B7B87;color:#fff}.pkc-g .d.u2{background:#E3CFD7;color:#4A3A41}.pkc-g .d.u3{background:#F3EAEE;color:#4A3A41}
.pkc-g .d.late{background:#6B3F4E;color:#fff}
.pkc-g .d.fin{background:#E3ECDF;color:#2F4A2E}
/* 📤 Direkt aus dem Kalender hochladen (Vivien, 30.10.2026) */
.pk-tw{display:flex;flex-direction:column;gap:6px;min-width:0}
.pk-up{display:flex;flex-wrap:wrap;gap:6px}
.pk-ub{display:inline-flex;align-items:center;gap:6px;font-size:16px;min-height:44px;padding:8px 16px;border-radius:999px;background:#3F6B3E;color:#fff;cursor:pointer;font-weight:400}
.pk-ub.re{background:#E7EFE3;color:#2F4A2E;font-size:15px;min-height:40px;padding:6px 14px}
.pk-ub.busy{opacity:.6;pointer-events:none}
.pkc-g .d.sel{outline:2.5px solid #4A3A41;outline-offset:1px}
.pkc-g .d i{position:absolute;top:2px;right:3px;font-style:normal;font-size:10px;line-height:15px;min-width:15px;padding:0 3px;border-radius:999px;background:rgba(255,255,255,.9);color:#4A3A41}
.pkc-leg{display:flex;flex-wrap:wrap;gap:4px 12px;margin:8px 2px 0;font-size:12px;color:#7D7076}.pkc-leg i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:4px;vertipkc-align:-1px}
.pkc-leg .u0{background:#7D5E68}.pkc-leg .u2{background:#E3CFD7}.pkc-leg .u3{background:#F3EAEE;border:1px solid #E6DCD2}
.pkc-list{min-width:0}
.pkc-dt{font:500 22px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41;margin:2px 2px 10px}
.pkc-tiles{grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px}
.pkc-tiles .pk-t{min-height:0;padding:9px 10px}
.pkc-tiles .pk-t>i{width:36px;height:36px;font-size:18px;border-radius:12px}
.pkc-tiles .pk-t b{font-size:16px}
.pkc-cl{margin-top:10px}.pkc-cl>summary{cursor:pointer;font-size:16px;color:#6B5658;padding:6px 2px;list-style:none}.pkc-cl>summary::-webkit-details-marker{display:none}
.pkc-cl[open]>summary{margin-bottom:8px}
.pkc-hint{font-size:16px;color:#7D7076;margin:6px 2px}
/* Tage als kleine zugeklappte Boxen in Movecia-Farben (Vivien, 28.10.2026) */
.pk-day{border-radius:20px;border:1.5px solid transparent;background:#F6EEEC;transition:box-shadow .2s}
.pk-day.c1{background:#F4E9E1}.pk-day.c2{background:#F1ECE4}.pk-day.c3{background:#EFE6EB}
.pk-day[open]{background:#FBF8F4;border-color:#E6DCD2;box-shadow:0 12px 26px -22px rgba(74,58,65,.6)}
.pk-day>summary{list-style:none;display:flex;align-items:center;gap:12px;padding:10px 12px;cursor:pointer;-webkit-tap-highlight-color:transparent}
.pk-day>summary::-webkit-details-marker{display:none}
.pk-cal{flex:0 0 auto;width:48px;height:52px;border-radius:15px;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 4px 10px -8px rgba(74,58,65,.6);line-height:1}
.pk-cal small{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#7D5E68;margin-bottom:3px}
.pk-cal b{font:500 24px/1 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.pk-day.late .pk-cal small{color:#A0413B}
.pk-dt{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px}
.pk-dt>b{font:500 21px/1.15 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.pk-mini{display:flex;flex-wrap:wrap;gap:4px 10px}.pk-mini em{font-style:normal;font-size:14px;color:#6B5658}
.pk-chev{flex:0 0 auto;width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.75);position:relative}
.pk-chev::after{content:"";position:absolute;left:10px;top:9px;width:8px;height:8px;border-right:2px solid #7D5E68;border-bottom:2px solid #7D5E68;transform:rotate(45deg);transition:transform .2s}
.pk-day[open] .pk-chev::after{transform:translateY(4px) rotate(-135deg)}
.pk-dayb{padding:2px 10px 12px}
@media(max-width:640px){.pk-day>summary .pill:not(.late):not(.soon){display:none}.pk-dt>b{font-size:19px}}
.pill{font-size:14px;padding:3px 12px;border-radius:999px;background:#EADFDE;color:#6B5658}.pill.soon{background:#E8D6CA;color:#4A3A41}.pill.late{background:#F3D9D5;color:#A0413B}
.pk-dh .cnt{margin-left:auto;font-size:15px;color:#7D7076}
.pk-sub{font-size:15px;color:#7D7076;margin:12px 2px 8px}
.pk-first{background:linear-gradient(135deg,#F3E2D8,#EADFDE);border-radius:20px;padding:4px 12px 12px;margin-bottom:12px}.pk-sub a{color:#7D5E68}
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
    $o = '<div class="pk-days">'; $i = 0;
    foreach ($by as $d => $l) { [$pc, $pt] = $pill((string)$d, false);
        $tl = array_filter($l, fn($t) => $t['ty'] !== 'clip'); $cl = array_filter($l, fn($t) => $t['ty'] === 'clip');
        $nT = count(array_filter($l, fn($t) => $t['ty'] === 'talk' || $t['ty'] === 'own')); $nC = count($cl); $nI = count(array_filter($l, fn($t) => $t['ty'] === 'ig'));
        $ts = $d === '' ? 0 : strtotime((string)$d);
        $o .= '<details class="pk-day c' . ($i++ % 4) . ($pc === 'late' ? ' late' : '') . '"><summary>'
            . '<span class="pk-cal">' . ($ts ? '<small>' . ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)date('w', $ts)] . '</small><b>' . date('j', $ts) . '</b>' : '<b>?</b>') . '</span>'
            . '<span class="pk-dt"><b>' . ($d === '' ? 'Noch ohne Datum' : $h($dayLong((string)$d))) . '</b><span class="pk-mini">'
            . ($nT ? '<em>🗣 ' . $nT . ' Talking Head' . ($nT > 1 ? 's' : '') . '</em>' : '') . ($nC ? '<em>🧘 ' . $nC . ' Übung' . ($nC > 1 ? 'en' : '') . '</em>' : '') . ($nI ? '<em>📱 ' . $nI . '</em>' : '') . '</span></span>'
            . ($pt !== '' ? '<span class="pill ' . $pc . '">' . $h($pt) . '</span>' : '') . '<span class="pk-chev" aria-hidden="true"></span></summary><div class="pk-dayb">';
        if ($tl) $o .= '<div class="pk-tiles">' . implode('', array_map($tile, $tl)) . '</div>';
        if ($cl) $o .= ($tl ? '<p class="pk-sub">🧘 Übungen filmen · ' . count($cl) . '</p>' : '') . '<div class="pk-tiles">' . implode('', array_map($tile, $cl)) . '</div>';
        $o .= '</div></details>'; }
    return $o . '</div>'; };
$open = array_values(array_filter($tiles, fn($t) => !$t['ok'] && $t['ty'] !== 'ig'));
$igT = array_values(array_filter($tiles, fn($t) => $t['ty'] === 'ig'));
$done = array_values(array_filter($tiles, fn($t) => $t['ok'] && $t['ty'] !== 'ig'));
// Kalender (Vivien, 28.10.2026: „Kalendertool statt großer Liste, dringend = kräftiges Pflaume“): je Fälligkeitstag die offenen Videos
$calOpen = array_merge($open, array_values(array_filter($igT, fn($t) => !$t['ok'])));
// Schon Hochgeladenes bleibt mit ✓ am Tag stehen (Vivien, 30.10.2026: „ich weiß nicht genau, ob es oben ist“)
$calAll = array_merge($calOpen, array_values(array_filter($done, fn($t) => $t['due'] !== '')), array_values(array_filter($igT, fn($t) => $t['ok'])));
$calBy = []; foreach ($calAll as $t) $calBy[$t['due'] === '' ? 'ohne' : $t['due']][] = $t;
ksort($calBy);
foreach ($calBy as &$l) usort($l, fn($a, $b) => $a['ok'] <=> $b['ok']); unset($l);
$calMeta = []; foreach ($calBy as $d => $l) $calMeta[$d] = ['n' => count(array_filter($l, fn($t) => !$t['ok'])), 'all' => count($l), 'ig' => count(array_filter($l, fn($t) => $t['ty'] === 'ig')), 'th' => count(array_filter($l, fn($t) => $t['ty'] === 'talk')), 'cl' => count(array_filter($l, fn($t) => $t['ty'] === 'clip'))];
?>
<section class="pk" id="pk">
  <div class="pk-now"><i id="pkI">🗣</i><div class="pk-nt"><small>Du nimmst gerade auf</small><b id="pkT">…</b><span id="pkS"></span></div>
    <button type="button" class="pk-btn" id="pkToggle" aria-expanded="false">Anderes Video wählen</button></div>
  <div class="pk-body" id="pkBody" hidden>
    <div class="pk-tabs" role="tablist">
      <button type="button" data-tab="next" aria-selected="true">📅 Kalender <span><?= count($calOpen) ?></span></button>
      <?php if ($igT): ?><button type="button" data-tab="ig" aria-selected="false">📱 Instagram <span><?= count($igT) ?></span></button><?php endif; ?>
      <button type="button" data-tab="free" aria-selected="false">✍️ Frei aufnehmen</button>
      <?php if ($done): ?><button type="button" data-tab="done" aria-selected="false">✓ Schon fertig <span><?= count($done) ?></span></button><?php endif; ?>
    </div>
    <div class="pk-pane pkc-pane" data-pane="next"><?php $acT = array_values(array_filter($open, fn($t) => ($t['g'] ?? '') === 'Academy')); if ($acT): ?><div class="pk-first"><p class="pk-sub">🎓 Zuerst · für dein Mindful Becoming Teacher Training</p><div class="pk-tiles"><?= implode('', array_map($tile, $acT)) ?></div></div><?php endif; ?><?php if (!$calOpen): ?><p class="pk-empty">Alles aufgenommen. Wunderbar ♡</p><?php else: ?>
      <div class="pkc-wrap">
        <div class="pkc">
          <div class="pkc-h"><button type="button" class="pkc-nav" data-cm="-1" aria-label="Monat davor">‹</button><b id="calM"></b><button type="button" class="pkc-nav" data-cm="1" aria-label="nächster Monat">›</button></div>
          <div class="pkc-g" id="calG"></div>
          <div class="pkc-leg"><span><i class="u0"></i>überfällig / diese Woche</span><span><i class="u2"></i>bis 3 Wochen</span><span><i class="u3"></i>später</span></div>
        </div>
        <div class="pkc-list">
          <?php foreach ($calBy as $d => $l): $tl = array_filter($l, fn($t) => $t['ty'] !== 'clip'); $cl = array_filter($l, fn($t) => $t['ty'] === 'clip'); ?>
          <div class="pkc-day" data-d="<?= $h($d) ?>" hidden>
            <p class="pkc-dt"><?= $d === 'ohne' ? 'Noch ohne Datum' : 'Fertig bis ' . $h($dayLong($d)) ?></p>
            <?php if ($tl): ?><div class="pk-tiles pkc-tiles"><?= implode('', array_map($tile, $tl)) ?></div><?php endif; ?>
            <?php if ($cl): ?><details class="pkc-cl"<?= $tl ? '' : ' open' ?>><summary>🧘 <?= count($cl) ?> Übung<?= count($cl) > 1 ? 'en' : '' ?> filmen</summary><div class="pk-tiles pkc-tiles"><?= implode('', array_map($tile, $cl)) ?></div></details><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <p class="pkc-hint" id="calHint">Tippe auf einen farbigen Tag.</p>
        </div>
      </div>
    <?php endif; ?></div>
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
  <a class="seg-f" href="?view=schnitt" title="Videos vom Handy oder Computer schneiden und bearbeiten">✂️ Video vom Gerät schneiden</a><input type="file" id="openF" accept="video/*" hidden>
  <span class="done-l" id="doneL"></span>
</div>
<p class="what-i" id="whatI" hidden></p>

<div id="edWrap" hidden><div class="ed-h"><h2>✂️ Video bearbeiten</h2><p class="tip" id="edInfo"></p></div><div id="ed"></div></div>

<div class="grid">
  <div class="view">
    <div id="stage" class="tc-stage" style="aspect-ratio:16/9"><div class="tc-st">Kamera startet … Bitte erlaube Kamera und Mikrofon.</div></div>
    <video id="play" controls playsinline hidden></video>
    <div class="iph" id="iph" hidden><button type="button" id="iphBtn">📱 iPhone als Kamera</button><span class="hint" id="iphInfo" style="font-size:15px;color:#7D7076"></span></div>
    <div class="iph-help" id="iphHelp" hidden></div>
    <div class="iph-help" id="wideHelp" hidden></div>
    <div class="bar">
      <button type="button" class="go" id="go">● Aufnahme starten</button>
      <button type="button" class="sm" id="tpOnly" title="Du filmst mit der Kamera-App am Handy, hier läuft nur der Text">📜 Nur Teleprompter</button>
      <button type="button" class="sm" id="wide" title="Die Kamera soll den ganzen Raum zeigen und nicht auf dein Gesicht zoomen">🧍 Ganzer Raum</button>
      <button type="button" class="stop" id="stop" hidden>■ Stopp</button>
      <button type="button" class="up" id="up" hidden>⬆ In die Lektion hochladen</button>
      <button type="button" class="next" id="edit" hidden>✂️ Schneiden &amp; bearbeiten</button>
      <button type="button" class="sm" id="take" hidden title="Diese Aufnahme in den Schnitt legen und gleich die nächste aufnehmen">＋ Noch ein Take</button>
      <button type="button" class="up" id="share1" hidden>📲 In Fotos sichern</button>
      <a class="b" id="dl" hidden download>⬇ Als Datei herunterladen</a>
      <button type="button" class="sm" id="again" hidden>↺ Nochmal aufnehmen</button>
      <span id="kept" hidden style="font-size:15px;color:#3F6B3E;align-self:center">✓ sicher im <a href="?view=schnitt" style="color:inherit">Schnitt</a></span>
      <button type="button" class="sm" id="editOld" hidden>Licht &amp; Musik (alt)</button>
      <button type="button" class="sm del" id="del" hidden>🗑 Löschen</button>
      <button type="button" class="sm del" id="cdel" hidden>🗑 Letzten Absatz löschen</button>
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
    <div class="box" id="prBox"><h2>📝 Teleprompter</h2>
      <div class="own" id="own" hidden><textarea id="ownT" placeholder="Hier deinen Text einfügen, z. B. die Story von heute."></textarea>
        <p class="tip" id="ownHint" style="margin:6px 0 10px">Eine Leerzeile trennt die Absätze. Bei „Absatz für Absatz“ wird jeder Absatz ein eigener Clip. Oben bei Deutsch/Englisch kannst du den Text in beiden Sprachen hinterlegen (für Untertitel in der anderen Sprache).</p></div>
      <div class="prc">
        <button type="button" id="prOn" aria-pressed="true">Text an</button>
        <button type="button" data-sp="-1" aria-label="langsamer">− langsamer</button><span class="sp-l" id="spL"></span><button type="button" data-sp="1" aria-label="schneller">schneller +</button>
        <button type="button" data-fs="-3" aria-label="Schrift kleiner">A−</button><button type="button" data-fs="3" aria-label="Schrift größer">A+</button>
        <button type="button" id="prTop" aria-label="zum Anfang">⟲ Anfang</button>
      </div>
      <label class="chk"><input type="checkbox" id="prAuto" checked> Text läuft mit der Aufnahme los</label>
      <label class="chk"><input type="checkbox" id="prCam" checked> Beim Aufnehmen ganz oben an der Kamera (Blick in die Linse)</label>
      <p class="tip" style="margin-top:8px">Tippen auf den Text oder Leertaste: Pause und weiter.</p>
    </div>
    <div class="box" id="lookBox"><h2>✨ Kamera-Look</h2><div id="panel"></div></div>
    <div class="box" id="devBox"><h2>Kamera &amp; Mikrofon</h2>
      <div class="dev"><label>Kamera<select id="cam"></select></label><label>Mikrofon<select id="mic"></select></label></div>
      <div class="dev"><label>Bildqualität<select id="qual"><option value="4k">4K (3840 × 2160)</option><option value="2k">2K (2560 × 1440)</option><option value="hd">Full HD (1920 × 1080)</option></select></label></div>
      <p class="hint" id="qInfo" style="margin:4px 0 0;font-size:15px;color:#7D7076"></p>
      <p class="tip" style="margin-top:10px">Tipp: Licht von vorne (Fenster vor dir, nicht hinter dir), Kamera auf Augenhöhe, Handy quer für Videos und hoch für Storys.</p>
    </div>
  </div>
</div>

<nav class="mtools" id="mtools" aria-label="Studio">
  <a href="?view=movecia" aria-label="Zurück zur Übersicht">⌂</a>
  <button type="button" data-sheet="pk">☰ Video</button>
  <button type="button" data-sheet="pr" id="mtPr">📝 Text</button>
  <button type="button" data-sheet="look" id="mtLook">✨ Look</button>
  <button type="button" data-sheet="clips" id="mtClips" hidden>🎬 Clips</button>
  <button type="button" data-sheet="more">⚙️ Mehr</button>
</nav>
<div class="msheet" id="msheet" hidden><div class="msh-h"><b id="mshT"></b><button type="button" id="mshX">Fertig ✓</button></div><div class="msh-b" id="mshB"></div></div>
<script src="/wildhearts/assets/talkcam.js?v=6"></script>
<script src="studio-edit.js?v=7"></script>
<script src="schnitt.js?v=16"></script>
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
  // „💛 Meine Looks“ auf dem Server (gleich auf Handy und Computer), auch für „Bearbeiten“ (Slot edit)
  const looksStore={get:slot=>post('mm_looks_get',{slot}).then(j=>j.list||[]),set:(slot,list)=>post('mm_looks_set',{slot,list:JSON.stringify(list)}).then(j=>{if(!j.ok)throw 0;return j.list})};
  if(window.TalkCam)TalkCam.store=looksStore;
  let clipm=false, cs=null, clips=[];
  let camLater=/[?&]kal=1/.test(location.search); // vom Kalender zum Hochladen: Kamera erst, wenn aufgenommen wird
  let lang=<?= json_encode($lang) ?>, cur=null, tgt=null, tc=null, raw=null, rec=null, chunks=[], blob=null, saved=true, t0=0, tick=0, lock=null;
  const ls={get:(k,d)=>{try{const v=localStorage.getItem(k);return v===null?d:v}catch(e){return d}},set:(k,v)=>{try{localStorage.setItem(k,v)}catch(e){}}};

  // ---- Teleprompter ----
  const pr=document.createElement('div');pr.className='pr';pr.innerHTML='<div class="pr-t" id="prT"></div>';
  const slab=document.createElement('div');slab.className='slab';slab.hidden=true;
  // Am iPhone geht ein Bruchteil-Pixel beim Scrollen verloren (scrollTop wird abgerundet) → eigener Zähler (Vivien, 28.10.2026: Teleprompter lief am Handy nicht)
  // Weich gleitend statt in ganzen Pixel-Sprüngen (Vivien, 28.10.2026: Text ruckelte am Handy und MacBook): Verschiebung per GPU-transform mit Kommastellen
  let prPos=0;const prT=()=>pr.querySelector('.pr-t');const prMax=()=>Math.max(0,(prT()?prT().offsetHeight:0)-pr.clientHeight*.6);
  // Der Lauf selbst ist eine CSS-Animation (läuft auf der Grafikkarte weiter, auch wenn die Kamera-Filter viel rechnen)
  // Neu (Vivien, 28.10.2026: „der Teleprompter funktioniert gar nicht“): kein CSS-Übergang und keine Maske mehr
  // (iPhone-Safari zeichnete den Text über dem Kamerabild teils nicht), sondern jedes Bild per transform mit Kommastellen.
  function prSet(){const t=prT();if(t)t.style.transform='translate3d(0,'+(-prPos).toFixed(2)+'px,0)'}
  function prPlay(){} function prPause(){} function prNow(){return prPos}
  function prMove(d,reset){prPos=reset?0:Math.max(0,Math.min(prPos+d,prMax()));pr.scrollTop=0;prSet()}
  let sp=+ls.get('st-sp',40), fs=+ls.get('st-fs',innerWidth<700?20:30), run=false, last=0;
  pr.style.setProperty('--fs',fs+'px');
  function textHtml(t){return esc(t||'').replace(/\[([^\]]+)\]/g,'<span class="hint">[$1]</span>')}
  function textOf(lg){return cur?(lg==='en'?cur.en:cur.de):ownOf(lg)}
  function paras(lg){return String(textOf(lg)||'').split(/\n\s*\n/).map(x=>x.trim()).filter(Boolean)}
  function setText(){if(clipm){showSlide();return}const t=cur?(lang==='en'?cur.en:cur.de):$('ownT').value;
    pr.querySelector('.pr-t').innerHTML=t.trim()?textHtml(t):'<span class="hint">'+(cur?'Für diese Lektion gibt es noch keinen '+(lang==='en'?'englischen':'deutschen')+' Text.':'Füge deinen Text beim Teleprompter ein.')+'</span>';prMove(0,true);}
  let prLast=0;function prLoop(ts){if(run&&!pr.hidden){const dt=prLast?Math.min(.1,(ts-prLast)/1000):0,m=prMax();if(prPos<m){prPos=Math.min(m,prPos+sp*dt);prSet()}}prLast=ts;requestAnimationFrame(prLoop)}requestAnimationFrame(prLoop);
  function toggle(){run=!run}
  pr.addEventListener('click',toggle);
  document.addEventListener('keydown',e=>{if(e.code==='Space'&&!/INPUT|TEXTAREA|SELECT|BUTTON/.test(document.activeElement.tagName)){e.preventDefault();toggle()}});
  // Tempo in 10 deutlich spürbaren Stufen (Vivien, 28.10.2026: „langsamer/schneller funktioniert nicht“ – die alten Schritte waren zu klein)
  const SPS=[10,15,21,28,36,45,56,70,88,110];let spLv=SPS.reduce((b,v,i)=>Math.abs(v-sp)<Math.abs(SPS[b]-sp)?i:b,0);sp=SPS[spLv];
  document.addEventListener('keydown',e=>{if(/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName))return;if(e.code==='ArrowUp'||e.code==='ArrowDown'){e.preventDefault();const b=document.querySelector('[data-sp="'+(e.code==='ArrowUp'?1:-1)+'"]');if(b)b.click()}});
  function spUi(){const l=$('spL');if(l)l.textContent='Tempo '+(spLv+1)+' von 10'}spUi();
  document.querySelectorAll('[data-sp]').forEach(b=>b.onclick=()=>{spLv=Math.max(0,Math.min(9,spLv+(+b.dataset.sp)));sp=SPS[spLv];ls.set('st-sp',sp);spUi()});
  document.querySelectorAll('[data-fs]').forEach(b=>b.onclick=()=>{fs=Math.max(18,Math.min(60,fs+(+b.dataset.fs)));pr.style.setProperty('--fs',fs+'px');ls.set('st-fs',fs)});
  $('prTop').onclick=()=>{prMove(0,true);run=false};
  $('prOn').onclick=()=>{pr.hidden=!pr.hidden;$('prOn').setAttribute('aria-pressed',String(!pr.hidden));$('prOn').textContent=pr.hidden?'Text aus':'Text an'};
  // Text beim Aufnehmen ganz oben an der Kamera (eigenes Element am <body>, damit nichts verschiebt)
  try{$('prCam').checked=ls.get('st-prcam','1')!=='0'}catch(e){}
  $('prCam').onchange=()=>ls.set('st-prcam',$('prCam').checked?'1':'0');
  function prTop(on){on=on&&$('prCam').checked&&!pr.hidden;if(on===pr.classList.contains('top'))return;
    if(on){document.body.appendChild(pr);pr.classList.add('top')}else{pr.classList.remove('top');const st=$('stage');st&&st.insertBefore(pr,st.querySelector('.cd'))}
    document.body.classList.toggle('prtop',on);prMove(0,true)}
  // ---- Nur Teleprompter: nichts aufnehmen, Text ganz oben unter der Handy-Linse, Rest dunkel ----
  const tpo=document.createElement('div');tpo.id='tpo';tpo.hidden=true;
  tpo.innerHTML='<div class="tpo-cd" id="tpoCd"></div><p class="tpo-hint" id="tpoHint">Starte jetzt die Aufnahme am Handy.<br>Der Text läuft gleich los.</p>'
    +'<div class="tpo-bar"><button type="button" class="main" data-t="pp">⏸ Pause</button><button type="button" data-t="slow">− langsamer</button><span id="tpoSp"></span><button type="button" data-t="fast">schneller +</button>'
    +'<button type="button" data-t="sm">A−</button><button type="button" data-t="lg">A+</button><button type="button" data-t="top">⟲ Anfang</button><button type="button" data-t="x">✕ Beenden</button></div>'
    +'<p class="tpo-tip">Leertaste oder Tippen auf den Text: Pause und weiter · ↑ ↓ Tempo · Esc: beenden</p>';
  document.body.appendChild(tpo);let tpOn=false;
  const tpUi=()=>{$('tpoSp').textContent='Tempo '+(spLv+1)+' von 10';tpo.querySelector('[data-t="pp"]').textContent=run?'⏸ Pause':'▶ Weiter'};
  async function tpStart(){const t=String(textOf(lang)||'').trim();if(!t){$('msg').className='msg';$('msg').textContent='Füge zuerst deinen Text beim Teleprompter ein.';return}
    tpOn=true;run=false;pr.querySelector('.pr-t').innerHTML=textHtml(t);pr.hidden=false;tpo.hidden=false;document.body.classList.add('tponly','prtop');
    document.body.appendChild(pr);pr.classList.add('top');prMove(0,true);$('tpoHint').hidden=false;tpUi();
    try{if(!lock)lock=await navigator.wakeLock.request('screen')}catch(e){}
    for(const n of [3,2,1]){if(!tpOn)return;$('tpoCd').textContent=n;await new Promise(r=>setTimeout(r,900))}$('tpoCd').textContent='';
    if(!tpOn)return;$('tpoHint').hidden=true;run=true;tpUi()}
  function tpEnd(){if(!tpOn)return;tpOn=false;run=false;$('tpoCd').textContent='';tpo.hidden=true;document.body.classList.remove('tponly');
    pr.classList.add('top');prTop(false);pr.hidden=!!(mv||mvFree)||$('prOn').getAttribute('aria-pressed')==='false';setText();if(lock){lock.release().catch(()=>{});lock=null}}
  tpo.addEventListener('click',e=>{const b=e.target.closest('[data-t]');if(!b)return;b.blur();const k=b.dataset.t;
    if(k==='pp'){$('tpoCd').textContent='';$('tpoHint').hidden=true;toggle()}
    else if(k==='slow'||k==='fast'){const x=document.querySelector('[data-sp="'+(k==='fast'?1:-1)+'"]');x&&x.click()}
    else if(k==='sm'||k==='lg'){const x=document.querySelector('[data-fs="'+(k==='lg'?3:-3)+'"]');x&&x.click()}
    else if(k==='top'){prMove(0,true)}
    else if(k==='x'){tpEnd();return}
    tpUi()});
  document.addEventListener('keydown',e=>{if(!tpOn)return;if(e.key==='Escape'){e.preventDefault();tpEnd()}else setTimeout(tpUi,0)});
  pr.addEventListener('click',()=>{if(tpOn){$('tpoHint').hidden=true;setTimeout(tpUi,0)}});
  $('tpOnly').onclick=()=>{if((rec&&rec.state==='recording')||(cs&&cs.rec))return;tpStart()};
  // eigener Text je Sprache (für Untertitel in der anderen Sprache)
  if(ls.get('st-own-de',null)===null&&ls.get('st-own',''))ls.set('st-own-de',ls.get('st-own',''));
  const ownOf=lg=>ls.get('st-own-'+lg,'');
  $('ownT').addEventListener('input',()=>{ls.set('st-own-'+lang,$('ownT').value);setText();subPreview()});

  // ---- Auswahl ----
  const profile=()=>(mv||mvFree)?'move':'talk';
  function choose(){const val=$('what').value;mv=val[0]==='c'?(MOVES.find(m=>m.id===+val.slice(1))||null):null;mvFree=val==='m';
    const wasIg=!!ig;ig=val[0]==='i'?(IGS.find(x=>x.id===+val.slice(1))||null):null;
    const id=+val;cur=(mv||mvFree)?null:(ig?{ig:ig,id:'i'+ig.id,t:ig.l,de:ig.text,en:ig.text,vde:ig.has,ven:ig.has,g:'Instagram',k:ig.kind==='reel'?'Reel':'Story',due:ig.day}:(TALKS.find(t=>t.id===id)||null));$('own').hidden=!!cur;$('ownT').value=ownOf(lang);
    const isMove=!!(mv||mvFree);if(cur&&cur.do&&lang!=='de'){lang='de';$('ownT').value=ownOf(lang)}$('prBox').hidden=isMove;$('mtPr').hidden=isMove;$('langSeg').hidden=isMove||!!ig||!!(cur&&cur.do);
    if(tc&&!isMove&&ig!==null!==wasIg){tc.set({aspect:ig?'9:16':'16:9'});TalkCam.panel(tc,$('panel'))}$('modeSeg').hidden=isMove;if($('tpOnly'))$('tpOnly').hidden=isMove;pr.hidden=isMove||$('prOn').getAttribute('aria-pressed')==='false';
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
  // ---- Kästen rechts einklappbar, Zustand wird gemerkt (Vivien, 28.10.2026) ----
  const SHUT_DEF={lookBox:1,devBox:1};
  document.querySelectorAll('.side .box').forEach(bx=>{const h=bx.querySelector(':scope>h2');if(!h||!bx.id)return;const k='st-shut-'+bx.id,v=ls.get(k,null);
    bx.classList.toggle('shut',v===null?!!SHUT_DEF[bx.id]:v==='1');h.setAttribute('role','button');h.tabIndex=0;
    const t=()=>{bx.classList.toggle('shut');ls.set(k,bx.classList.contains('shut')?'1':'0')};h.onclick=t;h.onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();t()}}});
  // ---- Kalender ----
  const CAL=<?= json_encode($calMeta) ?>;let calY,calMo,calSel='';
  (function(){const ks=Object.keys(CAL).filter(k=>k!=='ohne'&&CAL[k].n>0).sort(),td=new Date().toISOString().slice(0,10),first=ks.find(k=>k>=td)||ks[ks.length-1]||td;const d=new Date(first+'T12:00:00');calY=d.getFullYear();calMo=d.getMonth();calSel=first&&CAL[first]?first:''})();
  function urg(d){const n=Math.round((new Date(d+'T12:00:00')-new Date(new Date().toDateString()+' 12:00'))/864e5);return n<0?'late':n<=7?'u0':n<=14?'u1':n<=21?'u2':'u3'}
  function calDraw(){const g=$('calG');if(!g)return;const MO2=['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];$('calM').textContent=MO2[calMo]+' '+calY;
    const f=new Date(calY,calMo,1),off=(f.getDay()+6)%7,days=new Date(calY,calMo+1,0).getDate(),td=new Date().toISOString().slice(0,10);let h=['Mo','Di','Mi','Do','Fr','Sa','So'].map(x=>'<span class="wd">'+x+'</span>').join('');
    for(let i=0;i<off;i++)h+='<span></span>';
    for(let d=1;d<=days;d++){const k=calY+'-'+String(calMo+1).padStart(2,'0')+'-'+String(d).padStart(2,'0'),m=CAL[k];
      h+='<button type="button" class="d'+(m?' has '+(m.n?urg(k):'fin'):'')+(k===td?' today':'')+(k===calSel?' sel':'')+'" data-cd="'+k+'"'+(m?' aria-label="'+d+'.: '+(m.n?m.n+' Videos offen':'alles hochgeladen')+'"':' tabindex="-1"')+'>'+d+(m?'<i>'+(m.n||'✓')+'</i>':'')+'</button>'}
    g.innerHTML=h;g.querySelectorAll('.d.has').forEach(b=>b.onclick=()=>{calSel=b.dataset.cd;calDraw();calShow()})}
  function calShow(){document.querySelectorAll('.pkc-day').forEach(x=>x.hidden=x.dataset.d!==calSel);const hint=$('calHint');if(hint)hint.hidden=!!document.querySelector('.pkc-day:not([hidden])')}
  document.querySelectorAll('[data-cm]').forEach(b=>b.onclick=()=>{calMo+=+b.dataset.cm;if(calMo<0){calMo=11;calY--}if(calMo>11){calMo=0;calY++}calDraw()});
  calDraw();calShow();
  // ---- 📤 Direkt aus dem Kalender hochladen (Vivien, 30.10.2026: „im Kalender draufdrücken, Video hochladen, schneiden, und es ist direkt drin“) ----
  // Das Video landet im ✂️ Schnitt im Entwurf dieses Videos, mit Ziel. „Fertig stellen & hochladen“ lädt es dann genau hierhin.
  function upTargets(v){const out=[];
    if(/^\d+$/.test(v)&&v!=='0'){const T=TALKS.find(x=>x.id===+v);if(!T)return out;(T.do?['de']:['de','en']).forEach(lg=>out.push({done:lg==='de'?T.vde:T.ven,lab:lg==='de'?'Deutsch':'Englisch',name:T.t+(lg==='en'?' (EN)':''),tg:{k:'talk',id:T.id,lang:lg,n:T.t,g:T.g,due:T.due||''}}))}
    else if(v[0]==='c'){const M=MOVES.find(x=>x.id===+v.slice(1));if(M)out.push({done:M.ok,lab:'Clip',name:'Übung · '+M.n,tg:{k:'clip',c:M.id,n:M.n,due:M.due||''}})}
    else if(v[0]==='i'){const G=IGS.find(x=>x.id===+v.slice(1));if(G)out.push({done:G.has,lab:'Video',name:(G.kind==='reel'?'Reel ':'Story ')+G.day,tg:{k:'ig',id:G.id,n:G.t,due:G.day,kind:G.kind}})}
    return out}
  document.querySelectorAll('.pkc-day .pk-t,[data-pane=ig] .pk-t,.pk-first .pk-t').forEach(t=>{const list=upTargets(t.dataset.v);if(!list.length)return;
    const w=document.createElement('div');w.className='pk-tw';t.parentNode.insertBefore(w,t);w.appendChild(t);
    const row=document.createElement('div');row.className='pk-up';
    row.innerHTML=list.map((x,i)=>'<label class="pk-ub'+(x.done?' re':'')+'">'+(x.done?'✓ '+(list.length>1?esc(x.lab)+' ':'')+'ist drin · neu hochladen':'📤 '+(list.length>1?esc(x.lab)+' ':'')+'hochladen')+'<input type="file" accept="video/*,.mov,.mp4,.m4v" multiple hidden data-ui="'+i+'"></label>').join('');
    row.querySelectorAll('input').forEach(inp=>inp.onchange=async()=>{const x=list[+inp.dataset.ui],fs=[...inp.files];inp.value='';if(!fs.length||typeof Schnitt==='undefined')return;
      const lb=inp.parentNode,old=lb.firstChild.textContent;lb.classList.add('busy');lb.firstChild.textContent='Wird geöffnet … ';
      try{for(let i=0;i<fs.length;i++)await Schnitt.addTake(fs[i],fs[i].name.replace(/\.\w+$/,''),{draft:x.name,target:x.tg,open:true,renew:i===0});saved=true;location.href='?view=schnitt'}
      catch(e){lb.classList.remove('busy');lb.firstChild.textContent=old;alert('Das Video konnte nicht geöffnet werden. Bitte nochmal versuchen.')}});
    w.appendChild(row)});
  // ---- Kachel-Auswahl ----
  const pkBody=$('pkBody'),pkTg=$('pkToggle');
  function camGo(){if(camLater){camLater=false;startCam()}}
  function pkOpen(o){if(!o)camGo();pkBody.hidden=!o;pkTg.setAttribute('aria-expanded',String(o));pkTg.textContent=o?'Schließen':'Anderes Video wählen'}
  pkTg.onclick=()=>pkOpen(pkBody.hidden);
  document.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.setAttribute('aria-selected',String(x===b)));
    document.querySelectorAll('[data-pane]').forEach(p=>p.hidden=p.dataset.pane!==b.dataset.tab)});
  document.querySelectorAll('.pk-t').forEach(t=>t.onclick=()=>{if((rec&&rec.state==='recording')||(cs&&cs.rec))return;
    if(blob&&!saved&&!confirm('Die letzte Aufnahme ist noch nicht gespeichert. Trotzdem wechseln?'))return;
    $('what').value=t.dataset.v;if($('what').value!==t.dataset.v)return;choose();if(sheetK){closeSheet();return}pkOpen(false);$('pk').scrollIntoView({behavior:'smooth',block:'start'})});
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
    if(cur){const ok=cur.vde&&cur.ven;el.innerHTML='🗣 <b>'+esc(cur.k)+'</b> · '+esc(cur.g)+' · '+dueTxt(cur.due,ok)+(cur.do?'<small>Nur auf Deutsch, mit Teleprompter. Deutsch '+(cur.vde?'✓ hochgeladen':'fehlt noch')+'.</small>':'<small>Ein ganzes Video je Sprache mit Teleprompter. Deutsch '+(cur.vde?'✓ hochgeladen':'fehlt noch')+' · Englisch '+(cur.ven?'✓ hochgeladen':'fehlt noch')+'.</small>');return}
    el.innerHTML='🗣 <b>Talking Head mit eigenem Text</b><small>Für Storys und Reels. Wird nicht hochgeladen, nur heruntergeladen.</small>';}
  document.querySelectorAll('[data-lang]').forEach(b=>b.onclick=()=>{lang=b.dataset.lang;choose()});

  // ---- Kamera ----
  // Bildqualität (Vivien, 28.10.2026: „Kamera-Qualität sollte 4K sein oder 2K“): die Kamera liefert höchstens, was sie kann
  const QUAL={'4k':[3840,2160,'4K'],'2k':[2560,1440,'2K'],'hd':[1920,1080,'Full HD']};
  // Standard Full HD (Vivien, 30.10.2026: „Video stockt in der Mitte“) – 4K bremst mit den Kamera-Filtern jedes Gerät aus
  const qual=()=>{const q=ls.get('st-qual','hd');return QUAL[q]?q:'hd'};
  // Datenrate passend zur Größe, damit 4K auch wie 4K aussieht
  const vbr=(w,h)=>{const p=w*h;return p>=7e6?40000000:p>=3.2e6?20000000:p>=1.8e6?12000000:p>=.8e6?8000000:5000000};
  function qShow(){const el=$('qInfo');if(!el||!raw)return;const st=(raw.getVideoTracks()[0]||{getSettings:()=>({})}).getSettings(),cw=st.width||0,chh=st.height||0;
    const cv=tc&&tc.canvas;const L=Math.max(cw,chh),lbl=L>=3800?'4K':L>=2500?'2K':L>=1900?'Full HD':L>=1260?'HD':'';
    el.textContent=cw?'Deine Kamera liefert '+cw+' × '+chh+(lbl?' ('+lbl+')':'')+(cv?', aufgenommen wird in '+cv.width+' × '+cv.height+'.':'.')+(L<Math.max(QUAL[qual()][0],QUAL[qual()][1])-40?' Mehr kann diese Kamera nicht, bei der Handykamera ist das oft höher als bei der Laptop-Kamera.':''):''}
  async function startCam(){
    const cid=ls.get('st-cam',''), mid=ls.get('st-mic','');
    if(raw)raw.getTracks().forEach(t=>t.stop());if(tc)tc.destroy(false);
    const Q=QUAL[qual()]||QUAL['4k'];const v={width:{ideal:Q[0]},height:{ideal:Q[1]},frameRate:{ideal:30}};if(cid)v.deviceId={exact:cid};
    const a={echoCancellation:true,noiseSuppression:true,autoGainControl:true};if(mid)a.deviceId={exact:mid};
    try{raw=await navigator.mediaDevices.getUserMedia({video:v,audio:a})}
    catch(e){try{raw=await navigator.mediaDevices.getUserMedia({video:true,audio:true});ls.set('st-cam','');ls.set('st-mic','')}catch(e2){$('stage').innerHTML='<div class="tc-st">Kamera oder Mikrofon sind gesperrt. Bitte im Browser oben bei der Adresse erlauben und die Seite neu laden.</div>';return}}
    if(ls.get('st-wide',''))await wideZoom();
    tc=await TalkCam.create(raw,{mount:$('stage'),maxVh:vh(),profile:profile(),maxLong:Q[0]});qShow();
    $('stage').appendChild(pr);$('stage').appendChild(Object.assign(document.createElement('div'),{className:'cd',id:'cd'}));
    const rd=document.createElement('div');rd.className='recdot';rd.id='rd';rd.hidden=true;rd.innerHTML='<i></i><span id="rt">0:00</span>';$('stage').appendChild(rd);
    $('stage').appendChild(slab);
    tc.on(t=>{if(t==='layout')subPreview()});
    if(ig&&tc.s&&tc.s.aspect!=='9:16'){tc.set({aspect:'9:16'})}
    TalkCam.panel(tc,$('panel'));
    devices();
    try{$('qual').value=qual();$('qual').onchange=()=>{ls.set('st-qual',$('qual').value);startCam()};tc.on(t=>{if(t==='layout')qShow()})}catch(e){}
  }
  // ---- iPhone als Kamera (Apples Continuity-Kamera): das iPhone filmt, Teleprompter und alles andere bleiben am MacBook ----
  const isMacDesk=/Macintosh/.test(navigator.userAgent)&&!(navigator.maxTouchPoints>1);
  const isIph=l=>/iphone/i.test(l||'');
  function iphUi(ds){const box=$('iph');if(!box)return;box.hidden=!isMacDesk;if(!isMacDesk)return;
    const cur=(raw&&raw.getVideoTracks()[0])||null,on=!!(cur&&isIph(cur.label));
    $('iphBtn').classList.toggle('on',on);$('iphBtn').textContent=on?'📱 iPhone filmt · zurück zur Mac-Kamera':'📱 iPhone als Kamera';
    $('iphInfo').textContent=on?'Der Teleprompter steht oben am Bildschirm, direkt unter deinem iPhone.':(ds&&ds.some(d=>d.kind==='videoinput'&&isIph(d.label))?'Dein iPhone ist bereit.':'')}
  // ---- Ganzer Raum: Mac-Folgemodus (Center Stage) kann eine Website nicht selbst ausschalten; wir zoomen ganz heraus, wo die Kamera das erlaubt, und zeigen den Weg im Kontrollzentrum ----
  async function wideZoom(){try{const t=raw&&raw.getVideoTracks()[0];const c=t&&t.getCapabilities?t.getCapabilities():{};if(c.zoom&&typeof c.zoom.min==='number'){await t.applyConstraints({advanced:[{zoom:c.zoom.min}]});return true}}catch(e){}return false}
  async function wideShow(){const h=$('wideHelp');if(!h.hidden){h.hidden=true;return}
    const z=await wideZoom();ls.set('st-wide','1');
    const fmt=tc&&tc.s&&tc.s.aspect==='9:16';
    h.innerHTML='<b>Damit die Kamera den ganzen Raum zeigt</b>'+(z?' <span style="color:#5E7A4A">✓ Kamera ist ganz herausgezoomt</span>':'')
      +'<ol style="margin:8px 0 6px;padding-left:22px"><li>Lass die Kamera hier an.</li><li>Oben in der Mac-Menüleiste erscheint dann ein <b>grünes Kamera-Symbol</b> (nur solange die Kamera läuft). Klick darauf – bei älteren macOS-Versionen steht es im <b>Kontrollzentrum</b> unter „Videoeffekte“.</li><li>Schalte dort <b>Folgemodus</b> aus.</li></ol>'
      +'Steht dort kein Folgemodus oder lässt er sich nicht ausschalten, kann man ihn an diesem Mac leider nicht abstellen – eine Website kommt da nicht ran.'
      +(fmt?'<br><br>💡 Du nimmst gerade im Hochformat 9:16 auf. Dabei wird vom breiten Kamerabild nur die Mitte genommen, das wirkt auch gezoomt. Für den ganzen Körper ist 16:9 (Kamera-Look → Format) besser.':'')
      +'<br><br><b>Sicherster Weg für den ganzen Körper:</b> iPhone mit der normalen Kamera-App auf <b>0,5×</b> (Weitwinkel) filmen und hier <b>📜 Nur Teleprompter</b> laufen lassen. Danach das Video über „Video vom Gerät bearbeiten“ hochladen – dort zoomt nichts mit.'
      +'<br><button type="button" style="margin-top:10px;padding:8px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;font:inherit;cursor:pointer" onclick="this.parentNode.hidden=true">Alles klar</button>';
    h.hidden=false}
  $('wide').onclick=wideShow;
  function iphHelp(){const h=$('iphHelp');h.hidden=false;h.innerHTML='<b>Dein iPhone wird gerade nicht gefunden.</b> So klappt es (einmal einrichten, danach geht es mit einem Klick):'
    +'<ol><li>iPhone und MacBook sind mit derselben Apple-ID angemeldet, WLAN und Bluetooth sind an beiden an.</li>'
    +'<li>Am iPhone: Einstellungen → Allgemein → AirPlay &amp; Continuity → <b>Continuity-Kamera</b> einschalten.</li>'
    +'<li>iPhone sperren und ruhig hinstellen, am besten quer mit einer Halterung oben mittig am MacBook-Bildschirm, Rückkamera zu dir.</li>'
    +'<li>Hier nochmal auf „📱 iPhone als Kamera“ tippen. Beim ersten Mal fragt das iPhone kurz nach, das bestätigst du einmal.</li></ol>'
    +'<span style="color:#7D7076">Braucht macOS Ventura und iOS 16 oder neuer.</span> <button type="button" id="iphX" style="font:inherit;border:0;background:none;color:#7D5E68;text-decoration:underline;cursor:pointer">schließen</button>';
    $('iphX').onclick=()=>{h.hidden=true}}
  async function useIphone(){let ds=[];try{ds=await navigator.mediaDevices.enumerateDevices()}catch(e){}
    const d=ds.find(x=>x.kind==='videoinput'&&isIph(x.label));
    if(!d){iphHelp();return}
    $('iphHelp').hidden=true;ls.set('st-cam',d.deviceId);
    // auch das iPhone-Mikrofon nehmen, wenn vorhanden (näher am Gesicht als das MacBook)
    const m=ds.find(x=>x.kind==='audioinput'&&isIph(x.label));if(m)ls.set('st-mic',m.deviceId);
    $('msg').className='msg';$('msg').textContent='📱 iPhone-Kamera wird verbunden …';await startCam();
    const on=raw&&raw.getVideoTracks()[0]&&isIph(raw.getVideoTracks()[0].label);
    $('msg').className='msg'+(on?' ok':'');$('msg').textContent=on?'✓ Dein iPhone filmt jetzt. Schau beim Sprechen in die iPhone-Linse, der Text steht direkt darunter.':'Das iPhone hat sich nicht gemeldet. Ist es gesperrt und in der Nähe? Dann nochmal tippen.';}
  $('iphBtn').onclick=()=>{const cur=raw&&raw.getVideoTracks()[0];if(cur&&isIph(cur.label)){ls.set('st-cam','');ls.set('st-mic','');startCam();return}useIphone()};
  async function devices(){try{const ds=await navigator.mediaDevices.enumerateDevices();iphUi(ds);
    const fill=(sel,kind,key,label)=>{const curId=(raw.getTracks().find(t=>t.kind===kind.replace('input',''))||{getSettings:()=>({})}).getSettings().deviceId;
      sel.innerHTML=ds.filter(d=>d.kind===kind).map((d,i)=>'<option value="'+esc(d.deviceId)+'"'+(d.deviceId===curId?' selected':'')+'>'+esc(d.label||label+' '+(i+1))+'</option>').join('');
      sel.onchange=()=>{ls.set(key,sel.value);startCam()}};
    fill($('cam'),'videoinput','st-cam','Kamera');fill($('mic'),'audioinput','st-mic','Mikrofon');}catch(e){}}

  // ---- Aufnahme ----
  const fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
  function mime(){const ts=['video/mp4;codecs=avc1.640028,mp4a.40.2','video/mp4;codecs=avc1,mp4a','video/mp4','video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'];return ts.find(t=>window.MediaRecorder&&MediaRecorder.isTypeSupported(t))||''}
  function showAfter(){if(clipm){clipUi();return}const has=!!blob;$('take').hidden=!has;$('share1').hidden=!has||!(blob&&canShare([blob]));if(tgt)$('up').textContent=tgt.ig?'💾 Für '+tgt.ig.l.split(' · ')[0]+' speichern ('+(tgt.ig.kind==='reel'?'Reel':'Story')+')':tgt.clip?'⬆ Als Clip „'+tgt.clip.n+'“ hochladen':'⬆ In „'+tgt.t.t+'“ hochladen ('+(tgt.lang==='de'?'Deutsch':'Englisch')+')';$('go').hidden=has||(rec&&rec.state==='recording');$('again').hidden=!has;$('del').hidden=!has;$('dl').hidden=!has;$('edit').hidden=!has;$('up').hidden=!has||!tgt||(!BUNNY&&!tgt.ig);
    $('play').hidden=!has;$('stage').style.display=has?'none':'';}
  $('go').onclick=async()=>{
    if(!tc){$('msg').textContent='Die Kamera läuft noch nicht.';return}
    if(!window.MediaRecorder){$('msg').textContent='Dieser Browser kann nicht aufnehmen. Bitte Chrome oder Safari nehmen.';return}
    $('go').hidden=true;$('msg').textContent='';prTop(true);
    for(const n of [3,2,1]){$('cd').textContent=n;await new Promise(r=>setTimeout(r,800))}$('cd').textContent='';
    const mt=mime(),big=tc.canvas.width*tc.canvas.height>1.5e6;
    rec=new MediaRecorder(tc.stream(30),Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:vbr(tc.canvas.width,tc.canvas.height),audioBitsPerSecond:160000}));
    chunks=[];rec.ondataavailable=e=>{if(e.data&&e.data.size)chunks.push(e.data)};
    rec.onstop=()=>{blob=new Blob(chunks,{type:(rec.mimeType||mt||'video/webm').split(';')[0]});saved=false;
      const url=URL.createObjectURL(blob);$('play').src=url;const ext=blob.type.includes('mp4')?'mp4':'webm';
      const d=new Date(),stamp=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+'_'+String(d.getHours()).padStart(2,'0')+'-'+String(d.getMinutes()).padStart(2,'0');
      $('dl').href=url;$('dl').download=(tgt&&tgt.ig?tgt.ig.day+'_'+(tgt.ig.kind==='reel'?'Reel':'Story')+'_'+tgt.ig.t.slice(0,30):tgt&&tgt.clip?'Uebung_'+tgt.clip.n+'_'+stamp:(mvFree?'Bewegung_'+stamp:'TalkingHead_'+(tgt?tgt.t.t:'Eigener-Text')+'_'+(tgt?tgt.lang:lang).toUpperCase()+'_'+stamp)).replace(/[^\wäöüÄÖÜß-]+/g,'-')+'.'+ext;
      $('rd').hidden=true;$('stop').hidden=true;run=false;document.body.classList.remove('recording');prTop(false);showAfter();
      $('msg').className='msg';$('msg').textContent='Schau es dir kurz an. Passt es, '+(tgt&&BUNNY?'tippe auf „Hochladen“ oder lade es herunter.':'lade es herunter.');
      keepTake(blob);
      if(lock){lock.release().catch(()=>{});lock=null}};
    fromFile=false;tgt=mv?{clip:mv}:(ig?{ig:ig}:(cur?{t:cur,lang:lang}:null));rec.start(1000);t0=performance.now();$('stop').hidden=false;$('rd').hidden=false;
    document.body.classList.add('recording');prMove(0,true);
    if($('prAuto').checked&&!pr.hidden){const R=rec;setTimeout(()=>{if(rec===R&&R.state==='recording')run=true},1200)}
    try{lock=await navigator.wakeLock.request('screen')}catch(e){}
    (function loop(){if(!rec||rec.state!=='recording')return;const s2=(performance.now()-t0)/1000;if(s2-lastRt>.25){lastRt=s2;$('rt').textContent=fmt(s2)}tick=requestAnimationFrame(loop)})();
  };
  $('stop').onclick=()=>{if(rec&&rec.state==='recording')rec.stop()};
  // Jede Aufnahme wandert sofort in den Schnitt (Vivien, 30.10.2026: „dann ist es einfach weg“) – nichts geht mehr verloren
  async function keepTake(b){ if(!b||typeof Schnitt==='undefined')return;
    try{ await Schnitt.addTake(b, takeName(), {draft:draftName(),target:snTarget()}); const m=$('kept'); if(m){m.hidden=false;m.innerHTML='✓ sicher im <a href="?view=schnitt" style="color:inherit">Entwurf „'+draftName().replace(/[<>&]/g,'')+'“</a>'} }catch(e){} }
  $('again').onclick=()=>{blob=null;saved=true;$('kept').hidden=true;$('play').removeAttribute('src');$('msg').textContent='';$('prog').hidden=true;prMove(0,true);showAfter();};
  // Sofort löschen (Vivien, 28.10.2026): ohne Nachfrage, kurz rückgängig machbar
  let undo=null,lastRt=-9;
  $('del').onclick=()=>{if(!blob)return;undo={b:blob,src:$('play').src,href:$('dl').href,name:$('dl').download,saved:saved,tgt:tgt};
    blob=null;saved=true;$('play').removeAttribute('src');$('prog').hidden=true;prMove(0,true);showAfter();
    $('msg').className='msg';$('msg').innerHTML='🗑 Aufnahme gelöscht. <button type="button" class="sm" id="undo" style="margin-left:6px">Rückgängig</button>';
    $('undo').onclick=()=>{if(!undo)return;blob=undo.b;$('play').src=undo.src;$('dl').href=undo.href;$('dl').download=undo.name;saved=undo.saved;tgt=undo.tgt;undo=null;$('msg').textContent='Aufnahme ist wieder da.';showAfter()};
    const U=undo;setTimeout(()=>{if(undo===U){undo=null;if($('undo'))$('msg').textContent=''}},8000)};
  $('dl').addEventListener('click',()=>{saved=true});
  // Direkt in die Fotos (Vivien, 30.10.2026: „nicht als Datei, sondern direkt in der Galerie“): Web Share → Fotos am iPhone
  $('share1').onclick=async()=>{if(!blob)return;try{await navigator.share({files:[new File([blob],$('dl').download||'Video.mp4',{type:blob.type})]});saved=true;$('msg').className='msg ok';$('msg').textContent='✓ Gesichert. Das Video liegt jetzt in deinen Fotos.'}catch(e){}};
  window.addEventListener('beforeunload',e=>{if(blob&&!saved&&$('kept').hidden){e.preventDefault();e.returnValue=''}});
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
            $('msg').className='msg ok';$('msg').textContent='✓ Clip hochgeladen. Er steckt jetzt in '+(r.used||1)+(r.used===1?' Video':' Videos')+'. Nächste Übung oben auswählen.';if(r.see)$('msg').insertAdjacentHTML('beforeend',' <a class="see1" href="?view=videos&amp;play='+r.see+'" style="display:inline-flex;margin:6px 0 0 6px;padding:8px 16px;border-radius:999px;background:#4A3A41;color:#fff;text-decoration:none;font-size:16px">👁 So sieht es die Teilnehmerin</a>');})}}).start()})
        .catch(()=>{$('msg').textContent='Das hat nicht geklappt. Bitte herunterladen und später hochladen.';$('up').disabled=false});return}
    const T=tgt.t,id=T.id,lg=tgt.lang,fill=$('prog').querySelector('i');
    post('mm_mv_video_new',{id:id,lang:lg}).then(j=>{if(j.error){$('msg').textContent=j.error;$('up').disabled=false;return}
      $('prog').hidden=false;const up=new tus.Upload(blob,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
        headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},metadata:{filetype:blob.type,title:$('dl').download},
        onError:()=>{$('msg').textContent='Upload unterbrochen. Tipp nochmal auf „Hochladen“ (oder lade es herunter).';$('up').disabled=false},
        onProgress:(a,t)=>{const p=Math.round(a/t*100);fill.style.width=p+'%';$('msg').textContent='Lädt hoch … '+p+' % (Seite offen lassen)'},
        onSuccess:()=>{post('mm_mv_video_set',{id:id,lang:lg,guid:j.guid}).then(()=>{saved=true;$('up').disabled=false;$('up').hidden=true;
          if(lg==='de'){T.vde=true;if(T.do)T.ven=true}else T.ven=true;
          $('msg').className='msg ok';$('msg').textContent='✓ Hochgeladen. Bunny wandelt es in ein paar Minuten um.'+(T.do?' Das Video ist fertig.':(T.vde&&T.ven)?' Beide Sprachen sind da, das Video ist fertig.':' Jetzt noch '+(lg==='de'?'Englisch':'Deutsch')+'?');$('msg').insertAdjacentHTML('beforeend',' <a class="see1" href="?view=videos&amp;play='+id+'&amp;lang='+lg+'" style="display:inline-flex;margin:6px 0 0 6px;padding:8px 16px;border-radius:999px;background:#4A3A41;color:#fff;text-decoration:none;font-size:16px">👁 So sieht es die Teilnehmerin</a>');})}});
      up.start()}).catch(()=>{$('msg').textContent='Das hat nicht geklappt. Bitte herunterladen und später hochladen.';$('up').disabled=false});
  };

  // =================== Bearbeiten ===================
  let edOpen=null,fromFile=false;
  // Welche Musik passt? (Stichwörter aus dem Musik-Ordner, nach Titel/Text des Videos)
  function musicHint(){if(mv||mvFree)return [];const wi=[10,11].includes(new Date().getMonth())&&new Date().getDate()>=(new Date().getMonth()===10?15:1);if(ig)return wi?['winter','reel']:(ig.kind==='reel'?['reel','lebendig','morgen']:['reel','morgen']);
    const t=((cur&&cur.t)||'')+' '+String((cur&&(lang==='en'?cur.en:cur.de))||$('ownT').value||'').slice(0,300);
    if(/abend|nacht|schlaf|meditation|atem|breath|ruhe|loslassen|savasana|evening|sleep/i.test(t))return ['meditation','abend'];
    if(/morgen|morning|willkommen|welcome|start|ankommen/i.test(t))return ['morgen','reel'];
    return ['morgen','reel','flow']}
  function editor(b,dur,info,done){
    if(typeof StudioEdit==='undefined'){$('msg').textContent='Der Bearbeiten-Baustein konnte nicht laden. Bitte Seite neu laden (vorher herunterladen!).';return}
    document.body.classList.add('editing');$('edWrap').hidden=false;$('edInfo').textContent=info||'';window.scrollTo({top:0,behavior:'smooth'});
    const back=()=>{document.body.classList.remove('editing');$('edWrap').hidden=true;edOpen=null};
    edOpen=StudioEdit.open({store:looksStore,blob:b,dur:dur,mount:$('ed'),musicHint:musicHint(),seed:(cur&&cur.id)||(ig&&ig.id)||Date.now(),onDone:(nb,inf)=>{back();done(nb,inf)},onCancel:back});}
  function setBlob(nb,suffix){blob=nb;saved=false;const url=URL.createObjectURL(nb);$('play').src=url;const ext=nb.type.includes('mp4')?'mp4':'webm';
    $('dl').href=url;$('dl').download=String($('dl').download||'Video').replace(/(_bearbeitet)?\.\w+$/,'')+(suffix||'')+'.'+ext;$('prog').hidden=true;showAfter();}
  // ✂️ Schnitt (Vivien, 30.10.2026): Aufnahme in den Schnitt legen (bleibt auf dem Gerät), dort mehrere Takes schneiden
  function takeName(){return (tgt&&tgt.ig?tgt.ig.t.slice(0,30):tgt&&tgt.clip?tgt.clip.n:tgt?tgt.t.t+' ('+tgt.lang.toUpperCase()+')':'Aufnahme')}
  // Entwurf je Video (Vivien, 30.10.2026: „erst alle aufnehmen, dann die Entwürfe weiterbearbeiten“)
  // Ziel für den Schnitt (Vivien, 30.10.2026: „hochladen und es ist direkt drin“): „Fertig stellen & hochladen“ lädt genau hierhin
  function snTarget(){ if(!tgt)return null;
    if(tgt.ig)return {k:'ig',id:tgt.ig.id,n:tgt.ig.t,due:tgt.ig.day,kind:tgt.ig.kind};
    if(tgt.clip)return {k:'clip',c:tgt.clip.id,n:tgt.clip.n,due:tgt.clip.due||''};
    return {k:'talk',id:tgt.t.id,lang:tgt.lang,n:tgt.t.t,g:tgt.t.g,due:tgt.t.due||''}; }
  function draftName(){ if(tgt&&tgt.ig)return (tgt.ig.kind==='reel'?'Reel ':'Story ')+tgt.ig.day;
    if(tgt&&tgt.clip)return 'Übung · '+tgt.clip.n;
    if(tgt)return tgt.t.t+(tgt.lang==='en'?' (EN)':'');
    return mvFree?'Bewegungs-Clips':'Eigener Text'; }
  $('edit').onclick=async()=>{if(!blob||typeof Schnitt==='undefined')return;$('edit').disabled=true;
    try{ if($('kept').hidden) await Schnitt.addTake(blob,takeName(),{draft:draftName(),target:snTarget(),open:true}); else await Schnitt.setDraft(draftName(),snTarget()); saved=true;location.href='?view=schnitt'}catch(e){$('edit').disabled=false;$('msg').textContent='Konnte die Aufnahme nicht in den Schnitt legen.'}};
  let takeN=0;
  $('take').onclick=async()=>{if(!blob||typeof Schnitt==='undefined')return;$('take').disabled=true;try{if($('kept').hidden)await Schnitt.addTake(blob,takeName()+' · Take '+(++takeN),{draft:draftName(),target:snTarget()});saved=true;$('kept').hidden=true;blob=null;$('play').removeAttribute('src');$('prog').hidden=true;prMove(0,true);showAfter();
    $('msg').className='msg ok';$('msg').innerHTML='✓ Der Take liegt im Entwurf „'+draftName().replace(/[<>&]/g,'')+'“ (<a href="?view=schnitt">✂️ Schnitt</a>). Jetzt den nächsten aufnehmen, am Schluss alles zusammenschneiden.'}catch(e){$('msg').textContent='Konnte den Take nicht sichern.'}$('take').disabled=false};
  $('editOld').onclick=()=>{if(!blob)return;const d=$('play').duration;
    editor(blob,isFinite(d)?d:0,tgt?(tgt.ig?'Instagram '+(tgt.ig.kind==='reel'?'Reel':'Story')+' für '+tgt.ig.l.split(' · ')[0]+'. Tipp: Format „Hoch 9:16“.':tgt.clip?'Übung „'+tgt.clip.n+'“':'„'+tgt.t.t+'“ · '+(tgt.lang==='de'?'Deutsch':'Englisch'))+'. Danach kannst du direkt hochladen.':'Danach herunterladen.',(nb,inf)=>{
      setBlob(nb,'_bearbeitet');$('msg').className='msg ok';$('msg').textContent='✓ Bearbeitet ('+fmt(inf.dur)+(inf.music?', mit Musik':'')+'). Schau es dir an, dann '+(tgt&&BUNNY?'hochladen oder herunterladen.':'herunterladen.');});};
  $('openF').onchange=e=>{const f=e.target.files[0];e.target.value='';if(!f)return;
    if(blob&&!saved&&!confirm('Die letzte Aufnahme ist noch nicht gespeichert. Trotzdem ein anderes Video öffnen?'))return;
    if(clipm)setMode('ganz');fromFile=true;tgt=mv?{clip:mv}:(ig?{ig:ig}:(cur?{t:cur,lang:lang}:null));
    $('dl').download=f.name.replace(/\.\w+$/,'')+'.mp4';setBlob(f,'');saved=true;
    $('msg').className='msg';$('msg').textContent=tgt?'Das Video gehört dann zu: '+(tgt.ig?'Instagram '+tgt.ig.l:tgt.clip?'Übung „'+tgt.clip.n+'“':'„'+tgt.t.t+'“ ('+(lang==='de'?'Deutsch':'Englisch')+')')+'. Oben bei „Was nimmst du auf?“ kannst du das noch ändern.':'';
    $('edit').click();};
  window.addEventListener('beforeunload',e=>{if(edOpen){e.preventDefault();e.returnValue=''}});
  if(/[?&]cut=1/.test(location.search)&&typeof Schnitt!=='undefined'){Schnitt.takeResult().then(b=>{if(!b)return;fromFile=true;tgt=mv?{clip:mv}:(ig?{ig:ig}:(cur?{t:cur,lang:lang}:null));
    const d=new Date(),stamp=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');$('dl').download='Movecia_Schnitt_'+stamp+'.mp4';setBlob(b,'');saved=false;
    $('msg').className='msg ok';$('msg').textContent='✓ Dein fertig geschnittenes Video ist da. '+(tgt?'Es gehört zu: '+(tgt.ig?'Instagram '+tgt.ig.l:tgt.clip?'Übung „'+tgt.clip.n+'“':'„'+tgt.t.t+'“ ('+(lang==='de'?'Deutsch':'Englisch')+')')+'. Passt das nicht, oben unter „Was nimmst du auf?“ ändern, dann hochladen.':'Oben auswählen, wozu es gehört, dann hochladen.');
    if(PH.matches)closeSheet();else{const pk=document.getElementById('pk');if(pk)pk.open=false}})}

  // Instagram: Video in 8-MB-Stücken auf den eigenen Server, am Tag dann unter „Instagram täglich“ teilen
  async function igSave(G){const b=blob,CH=1536*1024,n=Math.max(1,Math.ceil(b.size/CH)),fill=$('prog').querySelector('i'),ext=b.type.includes('webm')?'webm':'mp4';
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
  const PH=matchMedia('(max-width:760px),(max-height:520px) and (pointer:coarse)');
  function vh(){if(PH.matches){const H=innerHeight||700;return document.body.classList.contains('sheet-open')?Math.max(20,Math.floor((H*.44-72)/H*100)):Math.max(30,Math.floor((H-180)/H*100))}return matchMedia('(max-width:980px)').matches?36:66}
  // ---- Handy: Einstellungen als Fenster von unten, das Kamerabild bleibt stehen ----
  const SHEETS={pk:['Welches Video?',()=>[$('pk')]],pr:['Teleprompter',()=>[$('prBox')]],look:['Kamera-Look',()=>[$('lookBox')]],clips:['Deine Clips',()=>[$('clipBox'),$('subBox')]],more:['Mehr',()=>[document.querySelector('.pick'),$('devBox')]]};
  let sheetK='',sheetNodes=[];
  function relayout(){if(tc){tc.opts.maxVh=vh();tc.emit('layout')}$('play').style.maxHeight=PH.matches?'':vh()+'vh'}
  function closeSheet(){if(!sheetK)return;camGo();sheetNodes.forEach(([n,ph])=>{ph.parentNode.insertBefore(n,ph);ph.remove()});sheetNodes=[];sheetK='';$('msheet').hidden=true;document.body.classList.remove('sheet-open');
    document.querySelectorAll('[data-sheet]').forEach(b=>b.setAttribute('aria-pressed','false'));relayout()}
  function openSheet(k){if(sheetK===k){closeSheet();return}closeSheet();const d=SHEETS[k];if(!d)return;sheetK=k;$('mshT').textContent=d[0];
    d[1]().forEach(n=>{if(!n)return;const ph=document.createComment('sheet');n.parentNode.insertBefore(ph,n);$('mshB').appendChild(n);sheetNodes.push([n,ph])});
    if(k==='pk')pkOpen(true);$('msheet').hidden=false;document.body.classList.add('sheet-open');$('mshB').scrollTop=0;
    document.querySelectorAll('[data-sheet]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.sheet===k)));relayout()}
  document.querySelectorAll('[data-sheet]').forEach(b=>b.onclick=()=>openSheet(b.dataset.sheet));
  $('mshX').onclick=closeSheet;
  function applyPhone(){const on=PH.matches;document.body.classList.toggle('rec-ui',on);if(!on)closeSheet();$('mtClips').hidden=!clipm;$('mtPr').hidden=!!(mv||mvFree);relayout()}
  PH.addEventListener?PH.addEventListener('change',applyPhone):PH.addListener(applyPhone);
  addEventListener('resize',relayout);
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
    pr.querySelector('.pr-t').innerHTML=t?textHtml(t):'<span class="hint">'+(P.length?'Fertig ✓':'Füge deinen Text beim Teleprompter ein. Eine Leerzeile trennt die Absätze.')+'</span>';prMove(0,true);
    slab.hidden=!P.length;slab.textContent='Absatz '+Math.min(i+1,P.length)+' von '+P.length;}
  function clipUi(){
    const P=paras(lang),n=P.length,recIng=!!(cs&&cs.rec);
    ['go','stop','up','dl','again','edit','del'].forEach(id=>{if(clipm)$(id).hidden=true});$('cdel').hidden=!clipm||recIng||lastClip<0||!clips[lastClip];if(!$('cdel').hidden)$('cdel').textContent='🗑 Absatz '+(lastClip+1)+' löschen';if(clipm){$('play').hidden=true;$('stage').style.display=''}
    $('cgo').hidden=!clipm||recIng;$('cnext').hidden=!clipm||!recIng;$('credo').hidden=!clipm||!recIng;$('cend').hidden=!clipm||!recIng;
    if(clipm&&!recIng){const first=clips.findIndex((c,i)=>i<n&&!c);const start=clips.length&&first<0&&clips.filter(Boolean).length>=n?-1:(first<0?Math.min(clips.length,n):first);
      $('cgo').dataset.start=Math.max(0,start);$('cgo').textContent=n?(start<0?'● Alles nochmal ab Absatz 1':'● Absatz '+(start+1)+' von '+n+' aufnehmen'):'● Aufnehmen';if(start<0)$('cgo').dataset.start=0;}
    if(clipm&&recIng)$('cnext').textContent=cs.i+1<n?'✓ Fertig · Absatz '+(cs.i+2)+' ›':'✓ Fertig · letzter Absatz';
    $('clipBox').hidden=!clipm;$('subBox').hidden=!clipm||!clips.some(Boolean);
    $('clipInfo').textContent=clipm?(n?n+' Absätze im Text. Du liest einen Absatz, tippst auf „Fertig“ und der nächste startet nach 3-2-1 von selbst. Leertaste oder → geht auch.':'Noch kein Text. Füge ihn beim Teleprompter ein, eine Leerzeile trennt die Absätze.'):'';
    renderClips();$('mtClips').hidden=!clipm;}
  // Stimme messen
  let ac=null,an=null,abuf=null;
  function meter(){try{if(!ac){ac=new (window.AudioContext||window.webkitAudioContext)();}ac.resume();if(!an&&raw&&raw.getAudioTracks().length){const src=ac.createMediaStreamSource(new MediaStream(raw.getAudioTracks()));an=ac.createAnalyser();an.fftSize=1024;src.connect(an);abuf=new Float32Array(an.fftSize)}}catch(e){an=null}}
  async function startClip(i,single){
    const P=paras(lang);if(!P.length){$('msg').textContent='Füge zuerst deinen Text ein.';return}
    if(!tc){$('msg').textContent='Die Kamera läuft noch nicht.';return}
    if(!baseName)baseName=slug(cur?cur.t:'Story')+'_'+lang.toUpperCase()+'_'+stamp();
    meter();cs={i:i,single:!!single,rec:null,discard:false};setText();clipUi();
    $('cgo').hidden=true;$('msg').textContent='';prTop(true);
    for(const n of [3,2,1]){$('cd').textContent=n;await new Promise(r=>setTimeout(r,650))}$('cd').textContent='';
    if(!cs)return;
    const mt=mime(),big=tc.canvas.width*tc.canvas.height>1.5e6,ch=[];
    const r=new MediaRecorder(tc.stream(30),Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:vbr(tc.canvas.width,tc.canvas.height),audioBitsPerSecond:160000}));
    const st={t0:0,s:null,e:null};
    r.ondataavailable=e=>{if(e.data&&e.data.size)ch.push(e.data)};
    r.onstop=()=>{const dur=(performance.now()-st.t0)/1000;const me=r._cs;r.stream.getVideoTracks().forEach(t=>t.stop());
      if(me.discard)return;
      const type=(r.mimeType||mt||'video/webm').split(';')[0],b=new Blob(ch,{type}),ext=type.includes('mp4')?'mp4':'webm';
      const old=clips[me.i];if(old){URL.revokeObjectURL(old.url);if(old.sub)URL.revokeObjectURL(old.sub.url)}
      clips[me.i]={i:me.i,blob:b,url:URL.createObjectURL(b),ext:ext,dur:dur,s:st.s==null?.2:Math.max(0,st.s-.15),e:st.e==null?dur-.2:Math.min(dur,st.e+.25),lang:lang,text:paras(lang)[me.i]||'',sub:null,saved:false};
      lastClip=me.i;renderClips();clipUi();};
    r._cs=cs;cs.rec=r;r.start(1000);st.t0=performance.now();$('rd').hidden=false;document.body.classList.add('recording');clipUi();
    try{if(!lock)lock=await navigator.wakeLock.request('screen')}catch(e){}
    (function loop(){if(!cs||cs.rec!==r||r.state!=='recording')return;const t=(performance.now()-st.t0)/1000;$('rt').textContent=fmt(t);
      if(an){an.getFloatTimeDomainData(abuf);let q=0;for(const v of abuf)q+=v*v;const rms=Math.sqrt(q/abuf.length);if(rms>.02){if(st.s==null)st.s=t;st.e=t}}
      // langer Absatz: Text läuft sanft mit
      if(t>1.2&&prMax()>4&&!pr.hidden&&!run)run=true;
      requestAnimationFrame(loop)})();
  }
  let lastClip=-1;
  function stopRec(discard){if(cs&&cs.rec&&cs.rec.state==='recording'){cs.rec._cs.discard=!!discard;cs.rec.stop()}$('rd').hidden=true;run=false;document.body.classList.remove('recording')}
  function delClip(i){const c=clips[i];if(!c)return;URL.revokeObjectURL(c.url);if(c.sub)URL.revokeObjectURL(c.sub.url);clips[i]=null;if(lastClip===i)lastClip=-1;renderClips();clipUi();$('msg').className='msg';$('msg').textContent='🗑 Absatz '+(i+1)+' gelöscht. Nimm ihn einfach neu auf.'}
  $('cdel').onclick=()=>{if(cs&&cs.rec)return;delClip(lastClip)};
  function finish(){stopRec(false);prTop(false);cs=null;slab.hidden=false;showSlide();slab.textContent='Fertig ✓';if(lock){lock.release().catch(()=>{});lock=null}clipUi();
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
        +'<button type="button" data-retake="'+i+'">'+(c?'↺ Neu aufnehmen':'● Aufnehmen')+'</button>'+(c?'<button type="button" class="del" data-del="'+i+'">🗑 Löschen</button>':'')+'</div></div></div>';}
    box.innerHTML=h;const any=clips.some(Boolean);$('clAll').hidden=!any;$('shareAll').hidden=!any||!canShare(clips.filter(Boolean).map(c=>c.blob));$('zipSub').hidden=!clips.some(c=>c&&c.sub);
    box.querySelectorAll('[data-del]').forEach(b=>b.onclick=()=>{if(cs&&cs.rec)return;delClip(+b.dataset.del)});
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
    const mt=mime(),ch=[],r=new MediaRecorder(out,Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:vbr(W,H),audioBitsPerSecond:160000}));
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
  // Vom Kalender aus zum Hochladen (?kal=1) erst einmal ohne Kamera: die startet, sobald ein Video zum Aufnehmen gewählt wird
  choose();if(!camLater)startCam();
  applyPhone();
  if(!/[?&](l|c|ig|move)=/.test(location.search)){if(PH.matches)openSheet('pk');else pkOpen(true)}
})();
</script>
<?php
    });
    exit;
}

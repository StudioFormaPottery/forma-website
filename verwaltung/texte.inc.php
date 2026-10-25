<?php
// Teleprompter-Texte (Vivien, 25.10.2026): alle Sprechtexte an einem Ort, nach Bereich aufgeklappt,
// ein Tipp öffnet den Teleprompter. Gatherings hier, Lektionen und Prä-/Postnatal über ihre eigenen Teleprompter.
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
        $deKeys = array_keys(require dirname(__DIR__) . '/wildhearts/gathering/skripte-de.inc.php');
        $groups = [];
        foreach (['unfold' => 'Unfold-Videos', 'method' => 'Method-Lektionen'] as $aud => $label) {
            $items = [];
            foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) $items[] = [$p, $l];
            $groups[$aud] = [$label, $items];
        }
        $u = fn(string $q) => forma_h($self) . '?' . $q; ?>
<style>
:root{--paper:#F2ECE4;--card:#FBF8F4;--line:#DDD2C6;--ink:#2E2529;--soft:#7D7076}
body{background:var(--paper)}h1,h2{color:#6B5658}
.nh{border-radius:26px;background:#4A3A41;color:#FBF5F7;padding:22px 24px}.nh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:34px;margin:2px 0 4px}.nh p{margin:0;color:#EADFDE}.nh .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
.tx-search{width:100%;margin:16px 0 4px;font:inherit;padding:12px 16px;border:1px solid var(--line);border-radius:999px;background:#fff}
details.tx{background:var(--card);border:1px solid var(--line);border-radius:20px;margin-top:12px;padding:4px 16px}
details.tx>summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;padding:12px 0;font-family:"Cormorant Garamond",Georgia,serif;font-size:24px;color:#6B5658}
details.tx>summary::-webkit-details-marker{display:none}details.tx>summary::after{content:"+";color:#7D5E68}details.tx[open]>summary::after{content:"–"}
details.tx>summary small{font-family:"Outfit",sans-serif;font-size:14px;color:var(--soft);margin-left:auto;margin-right:12px}
.ti{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;border-top:1px solid var(--line);padding:10px 0}
.ti span.t{flex:1 1 220px}.ti .m{display:block;font-size:13px;color:var(--soft)}
.ti .b{display:flex;gap:6px;flex-wrap:wrap}.ti .b a{display:inline-block;padding:6px 14px;border-radius:999px;background:#7D5E68;color:#FBF5F7;text-decoration:none;font-size:15px}.ti .b a.g{background:transparent;color:#7D5E68;border:1px solid #7D5E68}
.ti .no{font-size:14px;color:#A06B6B}
</style>
<?php mm_subnav('texte'); ?>
<section class="nh"><p class="eyebrow">Movecia · Einsprechen</p><h1>Teleprompter-Texte</h1><p>Alle Sprechtexte an einem Ort. Bereich aufklappen, Text antippen, dann läuft er im Teleprompter. Leertaste oder Tippen auf den Text startet und pausiert.</p></section>
<?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
<input class="tx-search" type="search" id="txs" placeholder="Text suchen, z. B. Atem, Beckenboden, Samhain …" aria-label="Text suchen">

<details class="tx" id="gatherings"><summary>Gatherings <small><?= count(mm_script_titles()) ?> Skripte</small></summary>
<?php foreach (mm_script_titles() as $t): ?>
    <div class="ti" data-q="<?= forma_h(mb_strtolower($t)) ?>"><span class="t"><?= forma_h($t) ?><span class="m">Live-Gathering als Talking Head</span></span>
    <span class="b"><a href="<?= $u('view=texte&lang=en&g=' . rawurlencode($t)) ?>" target="_blank" rel="noopener">Englisch</a><?php if (in_array($t, $deKeys, true)): ?><a class="g" href="<?= $u('view=texte&lang=de&g=' . rawurlencode($t)) ?>" target="_blank" rel="noopener">Deutsch</a><?php endif; ?></span></div>
<?php endforeach; ?>
</details>

<details class="tx" id="natal"><summary>Prä- &amp; Postnatal <small><?= count(NATAL_UNIT_KEYS) + 1 ?> Texte</small></summary>
    <div class="ti" data-q="kennenlerngespräch"><span class="t">Kennenlerngespräch<span class="m">30 Min. online, mit Fragen</span></span><span class="b"><a href="<?= $u('view=natal&prompter=kennenlernen') ?>" target="_blank" rel="noopener">Teleprompter</a></span></div>
<?php foreach (NATAL_UNIT_KEYS as $k): ?>
    <div class="ti" data-q="<?= forma_h(mb_strtolower(natal_unit_label($k))) ?>"><span class="t"><?= forma_h(natal_unit_label($k)) ?><span class="m">Impuls, Übungen, Breathwork, Meditation</span></span><span class="b"><a href="<?= $u('view=natal&prompter=' . $k) ?>" target="_blank" rel="noopener">Teleprompter</a><a class="g" href="<?= $u('view=natal&workbook=' . $k) ?>" target="_blank" rel="noopener">Workbook</a></span></div>
<?php endforeach; ?>
</details>

<?php foreach ($groups as $aud => [$label, $items]): $with = count(array_filter($items, fn($x) => trim($x[1]['script_de'] . $x[1]['script_en']) !== '')); ?>
<details class="tx" id="<?= $aud ?>"><summary><?= forma_h($label) ?> <small><?= $with ?> von <?= count($items) ?> mit Text</small></summary>
<?php if (!$items): ?><p class="muted small">Noch keine Lektionen angelegt.</p><?php endif; ?>
<?php $lastP = 0; foreach ($items as [$p, $l]): if ((int)$p['id'] !== $lastP): $lastP = (int)$p['id']; ?><p class="small" style="margin:14px 0 2px;color:#7D5E68;font-weight:600"><?= forma_h($p['title_de']) ?></p><?php endif; ?>
    <div class="ti" data-q="<?= forma_h(mb_strtolower($p['title_de'] . ' ' . $l['title_de'] . ' ' . $l['title_en'])) ?>"><span class="t"><?= forma_h($l['title_de']) ?><span class="m"><?= (int)$l['minutes'] ? (int)$l['minutes'] . ' Min.' : '' ?><?= $l['unlock'] !== '' ? ' · erscheint ' . forma_h(date('d.m.Y', strtotime($l['unlock']))) : '' ?></span></span>
    <span class="b"><?php if (trim($l['script_de']) !== ''): ?><a href="<?= $u('view=method&lang=de&prompter=' . (int)$l['id']) ?>" target="_blank" rel="noopener">Deutsch</a><?php endif; ?><?php if (trim($l['script_en']) !== ''): ?><a class="g" href="<?= $u('view=method&lang=en&prompter=' . (int)$l['id']) ?>" target="_blank" rel="noopener">Englisch</a><?php endif; ?><?php if (trim($l['script_de'] . $l['script_en']) === ''): ?><span class="no">Text fehlt noch</span><?php endif; ?><a class="g" href="<?= $u('view=method&l=' . (int)$l['id']) ?>">bearbeiten</a></span></div>
<?php endforeach; ?>
</details>
<?php endforeach; ?>
<script>
(function(){var s=document.getElementById('txs');s.addEventListener('input',function(){var q=s.value.trim().toLowerCase();document.querySelectorAll('.ti').forEach(function(r){r.hidden=q!==''&&r.dataset.q.indexOf(q)<0});if(q)document.querySelectorAll('details.tx').forEach(function(d){d.open=!!d.querySelector('.ti:not([hidden])')});});})();
</script>
<?php
    });
    exit;
}

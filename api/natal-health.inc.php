<?php
// Gesundheitsfragebogen Prä- & Postnatal (Vivien, 24.10.2026): beim Buchen und auf der persönlichen Seite.
// Gespeichert als JSON in mm_natal_orders.health (nur Datenordner, nie im Repo), sichtbar nur in der Verwaltung.
// Gesundheitsdaten = besondere Kategorie (Art. 9 DSGVO): eigene, ausdrückliche Einwilligung (health_ok).
declare(strict_types=1);

/** Mögliche Beschwerden: key => [Text, Phase (prae|post|''), Stufe (rot = ärztliche Freigabe nötig, gelb = anpassen)] */
const NATAL_HEALTH_ISSUES = [
    'blutung' => ['Blutungen in dieser Schwangerschaft', 'prae', 'rot'],
    'wehen' => ['Vorzeitige Wehen oder verkürzter Gebärmutterhals', 'prae', 'rot'],
    'plazenta' => ['Tiefliegende Plazenta (Placenta praevia)', 'prae', 'rot'],
    'blutdruck' => ['Bluthochdruck oder Präeklampsie', '', 'rot'],
    'herz' => ['Herz-, Kreislauf- oder Lungenerkrankung', '', 'rot'],
    'diabetes' => ['Schwangerschaftsdiabetes', 'prae', 'gelb'],
    'symphyse' => ['Schmerzen am Schambein oder im Becken', '', 'gelb'],
    'ruecken' => ['Rückenschmerzen oder Ischias', '', 'gelb'],
    'beckenboden' => ['Urinverlust oder Druckgefühl nach unten', '', 'gelb'],
    'diastase' => ['Rektusdiastase (Bauchmuskeln weichen auseinander)', 'post', 'gelb'],
    'narbe' => ['Schmerzen an Kaiserschnittnarbe oder Dammnaht', 'post', 'gelb'],
    'sonst' => ['Andere Erkrankung, Verletzung oder Operation', '', 'gelb'],
];
const NATAL_BIRTH_MODES = ['spontan' => 'spontan / vaginal', 'hilfe' => 'vaginal mit Saugglocke oder Zange', 'kaiserschnitt' => 'Kaiserschnitt'];

/** Eingaben prüfen. Liefert [Daten, Fehlerliste]. */
function natal_health_clean(array $in): array
{
    $err = [];
    $phase = ($in['h_phase'] ?? '') === 'post' ? 'post' : (($in['h_phase'] ?? '') === 'prae' ? 'prae' : '');
    if ($phase === '') $err[] = 'Bitte gib im Gesundheitsfragebogen an, ob du schwanger bist oder dein Baby schon da ist.';
    $issues = $in['h_issues'] ?? [];
    if (is_string($issues)) $issues = json_decode($issues, true) ?: [];
    $issues = array_values(array_intersect(array_keys(NATAL_HEALTH_ISSUES), (array)$issues));
    $d = [
        'phase' => $phase,
        'ssw' => $phase === 'prae' ? max(0, min(42, (int)($in['h_ssw'] ?? 0))) : 0,
        'multi' => $phase === 'prae' && !empty($in['h_multi']),
        'birth' => '', 'mode' => '', 'check' => '',
        'issues' => $issues,
        'details' => trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($in['h_details'] ?? '')) ?? '', 0, 800)),
        'clear' => in_array($in['h_clear'] ?? '', ['ja', 'bespreche'], true) ? (string)$in['h_clear'] : '',
        'at' => date('c'),
    ];
    if ($phase === 'prae' && ($d['ssw'] < 4)) $err[] = 'Bitte gib deine Schwangerschaftswoche an.';
    if ($phase === 'post') {
        $b = (string)($in['h_birth'] ?? '');
        $d['birth'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $b) && strtotime($b) && $b <= date('Y-m-d') ? $b : '';
        $d['mode'] = isset(NATAL_BIRTH_MODES[$in['h_mode'] ?? '']) ? (string)$in['h_mode'] : '';
        $d['check'] = in_array($in['h_check'] ?? '', ['ja', 'nein'], true) ? (string)$in['h_check'] : '';
        if ($d['birth'] === '') $err[] = 'Bitte gib das Geburtsdatum deines Babys an.';
        if ($d['mode'] === '') $err[] = 'Bitte gib an, wie dein Baby geboren wurde.';
        if ($d['check'] === '') $err[] = 'Bitte gib an, ob die Nachuntersuchung schon war.';
    }
    if ($d['clear'] === '') $err[] = 'Bitte gib an, ob du das Training mit deiner Hebamme oder Gynäkologin besprochen hast.';
    if (empty($in['h_ok'])) $err[] = 'Bitte willige ein, dass ich deine Gesundheitsangaben für dein Training verwenden darf.';
    return [$d, $err];
}

/** Hinweise für Vivien: [[Stufe, Text]] */
function natal_health_flags(array $d): array
{
    $f = [];
    foreach ($d['issues'] ?? [] as $k) {
        if (!isset(NATAL_HEALTH_ISSUES[$k])) continue;
        [$t, , $lvl] = NATAL_HEALTH_ISSUES[$k];
        $f[] = [$lvl, $t . ($lvl === 'rot' ? ': nur mit ärztlicher Freigabe trainieren' : '')];
    }
    if (($d['phase'] ?? '') === 'prae' && !empty($d['multi'])) $f[] = ['gelb', 'Mehrlingsschwangerschaft'];
    if (($d['phase'] ?? '') === 'post' && ($d['check'] ?? '') === 'nein') $f[] = ['rot', 'Nachuntersuchung noch nicht gemacht: erst danach mit Rückbildung starten'];
    if (($d['phase'] ?? '') === 'post' && ($d['mode'] ?? '') === 'kaiserschnitt') $f[] = ['gelb', 'Kaiserschnitt: Narbe schonen, meist erst ab 8 Wochen'];
    if (($d['clear'] ?? '') === 'bespreche') $f[] = ['gelb', 'Freigabe von Hebamme/Gynäkologin noch offen'];
    usort($f, fn($a, $b) => strcmp($b[0], $a[0]));
    return $f;
}

function natal_health_get(array $o): ?array
{
    $d = json_decode((string)($o['health'] ?? ''), true);
    return is_array($d) && ($d['phase'] ?? '') !== '' ? $d : null;
}

/** Kurzfassung in Zeilen (Verwaltung). */
function natal_health_lines(array $d): array
{
    $l = [];
    if ($d['phase'] === 'prae') $l[] = 'Schwanger, ' . (int)$d['ssw'] . '. SSW (am ' . date('d.m.', strtotime($d['at'])) . ')' . (!empty($d['multi']) ? ', Mehrlinge' : '');
    else $l[] = 'Baby geboren am ' . ($d['birth'] ? date('d.m.Y', strtotime($d['birth'])) : '?') . ', ' . (NATAL_BIRTH_MODES[$d['mode']] ?? '') . ', Nachuntersuchung ' . ($d['check'] === 'ja' ? 'war schon' : 'noch nicht');
    $l[] = $d['issues'] ? 'Angegeben: ' . implode(', ', array_map(fn($k) => NATAL_HEALTH_ISSUES[$k][0], $d['issues'])) : 'Keine Beschwerden angegeben';
    if ($d['details'] !== '') $l[] = 'Details: ' . $d['details'];
    $l[] = $d['clear'] === 'ja' ? 'Mit Hebamme/Gynäkologin besprochen ✓' : 'Bespricht es noch vor der ersten Einheit';
    return $l;
}

function natal_health_save(PDO $pdo, int $oid, array $d): void
{
    $pdo->prepare('UPDATE mm_natal_orders SET health = ?, health_at = ? WHERE id = ?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), date('c'), $oid]);
}

/** Formular (gleiche Klassen wie die Buchungsseite). $d = vorhandene Angaben zum Vorbefüllen. */
function natal_health_form(?array $d = null, string $due = ''): string
{
    $h = 'forma_h';
    $d ??= [];
    $ph = $d['phase'] ?? ($due !== '' && $due < date('Y-m-d') ? 'post' : '');
    $chk = fn(bool $b) => $b ? ' checked' : '';
    $o = '<div class="hq" data-phase="' . $h($ph) . '">';
    $o .= '<h3 style="margin-top:.6rem">Deine Gesundheit</h3><p class="mut" style="font-size:.92rem">Damit ich jede Einheit sicher und passend für dich gestalten kann. Deine Angaben sieht nur Vivien.</p>';
    $o .= '<div class="seg" role="radiogroup" aria-label="Schwanger oder Baby schon da">'
        . '<label><input type="radio" name="h_phase" value="prae"' . $chk($ph === 'prae') . '><span>Ich bin schwanger</span></label>'
        . '<label><input type="radio" name="h_phase" value="post"' . $chk($ph === 'post') . '><span>Mein Baby ist schon da</span></label></div>';
    $o .= '<div class="hq-prae grid2"><label class="f">Schwangerschaftswoche heute<input type="number" name="h_ssw" min="4" max="42" inputmode="numeric" value="' . ((int)($d['ssw'] ?? 0) ?: '') . '" placeholder="z. B. 24"></label>'
        . '<label class="chk" style="align-self:end"><input type="checkbox" name="h_multi" value="1"' . $chk(!empty($d['multi'])) . '><span>Ich erwarte Zwillinge oder Mehrlinge</span></label></div>';
    $o .= '<div class="hq-post grid2"><label class="f">Geburtsdatum deines Babys<input type="date" name="h_birth" max="' . date('Y-m-d') . '" value="' . $h((string)($d['birth'] ?? '')) . '"></label>'
        . '<label class="f">Wie kam dein Baby zur Welt?<select name="h_mode"><option value="">Bitte wählen</option>';
    foreach (NATAL_BIRTH_MODES as $k => $t) $o .= '<option value="' . $k . '"' . (($d['mode'] ?? '') === $k ? ' selected' : '') . '>' . $h($t) . '</option>';
    $o .= '</select></label><label class="f">War die Nachuntersuchung (meist 6–8 Wochen nach der Geburt) schon?<select name="h_check"><option value="">Bitte wählen</option><option value="ja"' . (($d['check'] ?? '') === 'ja' ? ' selected' : '') . '>Ja, schon gemacht</option><option value="nein"' . (($d['check'] ?? '') === 'nein' ? ' selected' : '') . '>Nein, noch nicht</option></select></label></div>';
    $o .= '<p class="hq-q">Trifft etwas davon auf dich zu? <span>(Mehrfachauswahl)</span></p><div class="hq-iss">';
    foreach (NATAL_HEALTH_ISSUES as $k => [$t, $p]) {
        $o .= '<label class="pill' . ($p !== '' ? ' only-' . $p : '') . '"><input type="checkbox" name="h_iss" value="' . $k . '"' . $chk(in_array($k, $d['issues'] ?? [], true)) . '><span>' . $h($t) . '</span></label>';
    }
    $o .= '</div><p class="hq-red" hidden>Danke, dass du das angibst. Bitte hol dir dafür vor der ersten Einheit unbedingt das Okay deiner Ärztin oder Hebamme. Ich melde mich vor unserem ersten Termin bei dir.</p>';
    $o .= '<label class="f">Gibt es sonst etwas, das ich wissen sollte? <span class="mut">(z. B. Medikamente, frühere Verletzungen, was dir Sorgen macht)</span><textarea name="h_details" maxlength="800">' . $h((string)($d['details'] ?? '')) . '</textarea></label>';
    $o .= '<p class="hq-q">Hast du das Training mit deiner Hebamme oder Gynäkologin besprochen?</p><div class="seg">'
        . '<label><input type="radio" name="h_clear" value="ja"' . $chk(($d['clear'] ?? '') === 'ja') . '><span>Ja, sie hat es freigegeben</span></label>'
        . '<label><input type="radio" name="h_clear" value="bespreche"' . $chk(($d['clear'] ?? '') === 'bespreche') . '><span>Ich bespreche es vor der ersten Einheit</span></label></div>';
    $o .= '<p class="mut" style="font-size:.86rem;margin:.2rem 0 0">Bitte sprich das Training kurz mit deiner Hebamme oder deinem Gynäkologen ab, besonders wenn du oben etwas angekreuzt hast. Hör während des Trainings immer auf deinen Körper: Bei Blutungen, Schmerzen, Schwindel, Atemnot, Fruchtwasserabgang oder Wehen hörst du sofort auf und meldest dich bei deiner Ärztin oder Hebamme.</p>';
    $o .= '<label class="chk"><input type="checkbox" name="h_ok" value="1"' . $chk(!empty($d)) . '><span>Ich willige ausdrücklich ein, dass Vivien meine Gesundheitsangaben nur zur sicheren Gestaltung meines Trainings verwendet. Sie werden nicht weitergegeben und spätestens einen Monat nach meiner letzten Einheit gelöscht. Ich kann die Einwilligung jederzeit per E-Mail widerrufen.</span></label>';
    $o .= '</div>';
    return $o;
}

/** CSS für das Formular (auf der Natal-Seite eingebunden). */
const NATAL_HEALTH_CSS = '.hq{display:flex;flex-direction:column;gap:.8rem}.hq .seg{display:flex;flex-wrap:wrap;gap:.5rem}.hq .seg label,.hq .pill{position:relative;cursor:pointer}.hq .seg input,.hq .pill input{position:absolute;opacity:0;pointer-events:none}.hq .seg span,.hq .pill span{display:inline-block;border:1px solid var(--line);border-radius:999px;padding:.55em 1.1em;background:#fff;font-size:.93rem;line-height:1.3;transition:.2s}.hq .seg input:checked+span,.hq .pill input:checked+span{background:var(--plum);border-color:var(--plum);color:#FBF5F7}.hq .seg input:focus-visible+span,.hq .pill input:focus-visible+span{outline:2px solid var(--plum);outline-offset:2px}.hq-iss{display:flex;flex-wrap:wrap;gap:.45rem}.hq-iss .pill span{font-size:.86rem;padding:.45em .95em}.hq .hq-prae,.hq .hq-post{display:none}.hq[data-phase=prae] .hq-prae,.hq[data-phase=post] .hq-post{display:grid}.hq[data-phase=prae] .only-post,.hq[data-phase=post] .only-prae{display:none}.hq-q{font-size:.86rem;color:var(--muted);margin:.3rem 0 0}.hq-q span{opacity:.8}.hq-red{background:#F6E7E4;border-radius:14px;padding:.8rem 1rem;font-size:.92rem;color:#7a3a33}';

/** JS: Phase umschalten, roten Hinweis zeigen, Werte einsammeln. */
const NATAL_HEALTH_JS = <<<'JS'
function HealthQ(root){const q=root.querySelector('.hq');if(!q)return null;const red=['blutung','wehen','plazenta','blutdruck','herz'];
const upd=()=>{const p=q.querySelector('input[name=h_phase]:checked');q.dataset.phase=p?p.value:'';const any=[...q.querySelectorAll('input[name=h_iss]:checked')].some(i=>red.includes(i.value)&&getComputedStyle(i.parentNode).display!=='none');q.querySelector('.hq-red').hidden=!any;};
q.addEventListener('change',upd);upd();
return{collect(d){d.h_issues=JSON.stringify([...q.querySelectorAll('input[name=h_iss]:checked')].filter(i=>getComputedStyle(i.parentNode).display!=='none').map(i=>i.value));delete d.h_iss;return d;},
check(){const f=n=>q.querySelector('[name='+n+']');const ph=q.querySelector('input[name=h_phase]:checked');if(!ph)return'Bitte gib im Gesundheitsfragebogen an, ob du schwanger bist oder dein Baby schon da ist.';
if(ph.value==='prae'&&!(+f('h_ssw').value>=4))return'Bitte gib deine Schwangerschaftswoche an.';
if(ph.value==='post'&&(!f('h_birth').value||!f('h_mode').value||!f('h_check').value))return'Bitte ergänze Geburtsdatum, Geburtsart und Nachuntersuchung.';
if(!q.querySelector('input[name=h_clear]:checked'))return'Bitte gib an, ob du das Training mit Hebamme oder Gynäkologin besprochen hast.';
if(!f('h_ok').checked)return'Bitte setz das Häkchen zur Einwilligung für deine Gesundheitsangaben.';return'';}};}
JS;

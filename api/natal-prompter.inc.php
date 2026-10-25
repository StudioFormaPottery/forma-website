<?php
// Teleprompter für Vivien im Movecia-Videoraum (nur Gastgeberinnen-Link).
// Skripte je Einheit, vorbefüllt mit dem Ablauf aus dem Curriculum. Gespeichert in ../forma-data/movecia-natal-prompter.json.
declare(strict_types=1);
require_once __DIR__ . '/natal-units.inc.php';

const NATAL_SCRIPT_TITLES = [
    'kennenlernen' => 'Kennenlerngespräch',
    'prae1' => 'Pränatal 1 · Ankommen im Körper', 'prae2' => 'Pränatal 2 · Beckenboden: anspannen und loslassen',
    'prae3' => 'Pränatal 3 · Rücken entlasten, Kraft für den Alltag', 'prae4' => 'Pränatal 4 · Der Atem als Werkzeug für die Wehen',
    'prae5' => 'Pränatal 5 · Geburtspositionen und Loslassen', 'prae6' => 'Pränatal 6 · Gut ins Wochenbett',
    'post1' => 'Postnatal 1 · Ankommen nach der Geburt', 'post2' => 'Postnatal 2 · Den Beckenboden neu spüren',
    'post3' => 'Postnatal 3 · Tiefe Bauchmuskeln und Rektusdiastase', 'post4' => 'Postnatal 4 · Haltung, Tragen und Stillen',
    'post5' => 'Postnatal 5 · Kraft für den Alltag mit Baby', 'post6' => 'Postnatal 6 · Nervensystem, Schlafmangel und Mental Load',
    'post7' => 'Postnatal 7 · Zurück zur Lieblingsbewegung', 'post8' => 'Postnatal 8 · Dein Programm für zu Hause',
    'frei' => 'Eigenes Skript',
    'kennenlernen_en' => 'English · Discovery call',
    'prae1_en' => 'English · Prenatal 1 · Arriving in your body',
    'prae2_en' => 'English · Prenatal 2 · Pelvic floor: engaging and letting go',
    'prae3_en' => 'English · Prenatal 3 · Relieving your back, strength for everyday life',
    'prae4_en' => 'English · Prenatal 4 · Your breath as a tool for labour',
    'prae5_en' => 'English · Prenatal 5 · Birth positions and letting go',
    'prae6_en' => 'English · Prenatal 6 · Preparing for the postpartum weeks',
    'post1_en' => 'English · Postnatal 1 · Arriving after the birth',
    'post2_en' => 'English · Postnatal 2 · Feeling your pelvic floor again',
    'post3_en' => 'English · Postnatal 3 · Deep core muscles and diastasis recti',
    'post4_en' => 'English · Postnatal 4 · Posture, carrying and feeding',
    'post5_en' => 'English · Postnatal 5 · Strength for everyday life with your baby',
    'post6_en' => 'English · Postnatal 6 · Nervous system, sleep deprivation and mental load',
    'post7_en' => 'English · Postnatal 7 · Back to the movement you love',
    'post8_en' => 'English · Postnatal 8 · Your programme for home',
];

/** Fertige Sprechtexte je Einheit (api/natal-units.inc.php, Vivien 24.10.2026). */
function natal_script_defaults(): array
{
    $out = ['kennenlernen' => natal_call_script(), 'frei' => ''];
    foreach (NATAL_UNIT_KEYS as $k) $out[$k] = natal_unit_script($k);
    $out['kennenlernen_en'] = natal_call_script_en();
    foreach (NATAL_UNIT_KEYS as $k) $out[$k . '_en'] = natal_unit_script_en($k);
    return $out;
}

function natal_prompter_cfg(): array
{
    $f = forma_data_dir() . '/movecia-natal-prompter.json';
    $c = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    $c = is_array($c) ? $c : [];
    return ['texts' => (array)($c['texts'] ?? []), 'speed' => (float)($c['speed'] ?? 1.2), 'size' => (int)($c['size'] ?? 30)];
}

function natal_prompter_save(array $in): void
{
    $c = natal_prompter_cfg();
    if (isset($in['key'], $in['text']) && isset(NATAL_SCRIPT_TITLES[$in['key']])) $c['texts'][$in['key']] = mb_substr((string)$in['text'], 0, 40000);
    if (isset($in['reset']) && isset(NATAL_SCRIPT_TITLES[$in['reset']])) unset($c['texts'][$in['reset']]);
    if (isset($in['speed'])) $c['speed'] = max(0.2, min(6, (float)$in['speed']));
    if (isset($in['size'])) $c['size'] = max(16, min(64, (int)$in['size']));
    $f = forma_data_dir() . '/movecia-natal-prompter.json';
    file_put_contents($f, json_encode($c, JSON_UNESCAPED_UNICODE));
    @chmod($f, 0600);
}

/** Welche Einheit ist dieser Termin? Kennenlerngespräch → kennenlernen, sonst nach dem Paketplan (natal_order_plan, inkl. „Baby ist da“). */
function natal_script_key(PDO $pdo, array $room): string
{
    if ($room['kind'] === 'c') return 'kennenlernen';
    $o = natal_order($pdo, (int)$room['row']['order_id']);
    return $o ? natal_session_key($pdo, $o, (int)$room['row']['id']) : 'frei';
}

<?php
// Movecia · 1:1 Personal Training – Anfragen (Vivien, 26.10.2026)
// Statt mailto: kurzes Formular mit Ankreuzen + Text; landet in der Verwaltung (Movecia → Übersicht → „Personal Training Anfragen“),
// die Interessentin bekommt eine Eingangsbestätigung, Vivien eine Info-Mail.
declare(strict_types=1);

const MM_PT_GOALS = [
    'kraft' => ['Kraft & Stabilität', 'Strength & stability'],
    'ruecken' => ['Beweglichkeit & Rücken', 'Mobility & back'],
    'ruhe' => ['Ruhe & weniger Stress', 'Calm & less stress'],
    'neustart' => ['Wieder fit nach einer Pause', 'Getting fit again after a break'],
    'natal' => ['Schwangerschaft & Rückbildung', 'Pregnancy & postnatal recovery'],
    'urlaub' => ['Training im Urlaub', 'Training on holiday'],
];
const MM_PT_PLACES = [
    'studio' => ['Im Studio in Mathon', 'In the studio in Mathon'],
    'natur' => ['Draußen in der Natur', 'Outdoors in nature'],
    'online' => ['Online per Video', 'Online via video'],
];
const MM_PT_TIMES = [
    'vormittag' => ['Vormittag', 'Morning'],
    'nachmittag' => ['Nachmittag', 'Afternoon'],
    'abend' => ['Abend', 'Evening'],
];

function mm_pt_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS mm_pt_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL DEFAULT \'\',
        goals TEXT NOT NULL DEFAULT \'\', places TEXT NOT NULL DEFAULT \'\', times TEXT NOT NULL DEFAULT \'\',
        message TEXT NOT NULL DEFAULT \'\', lang TEXT NOT NULL DEFAULT \'de\',
        ip_hash TEXT NOT NULL DEFAULT \'\', status TEXT NOT NULL DEFAULT \'neu\', created_at TEXT NOT NULL, done_at TEXT NOT NULL DEFAULT \'\'
    )');
}

function mm_pt_labels(string $csv, array $map, string $lang = 'de'): array
{
    $out = [];
    foreach (array_filter(explode(',', $csv)) as $k) if (isset($map[$k])) $out[] = $map[$k][$lang === 'en' ? 1 : 0];
    return $out;
}

function mm_pt_open_count(PDO $pdo): int
{
    mm_pt_schema($pdo);
    return (int)$pdo->query("SELECT COUNT(*) FROM mm_pt_requests WHERE status = 'neu'")->fetchColumn();
}

function mm_pt_list(PDO $pdo, bool $all = false): array
{
    mm_pt_schema($pdo);
    return $pdo->query('SELECT * FROM mm_pt_requests ' . ($all ? '' : "WHERE status = 'neu' OR created_at >= '" . date('c', time() - 30 * 86400) . "' ") . 'ORDER BY id DESC LIMIT 60')->fetchAll();
}

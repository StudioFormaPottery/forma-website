<?php
// Ein gemeinsamer Kalender für Vivien (Vivien, 27.10.2026: „dass ich mich selber nicht überbuche“):
// alles, was bei FORMA und Movecia stattfindet. Daraus kommen
//   – die Übersicht in der Verwaltung (Wintersaison 2026/27),
//   – die gesperrten Zeiten auf den Hotel-Buchungsseiten (/urlaub/ und moveciamovement.com/personal-training),
//   – die Prüfung in api/book.php (Kurs ohne Anmeldung, während Vivien schon anderswo gebucht ist).
// Braucht nur api/lib.php. Movecia-Tabellen werden nur gelesen, wenn es sie gibt.
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

const VB_BUFFER = 15; // Minuten Puffer vor und nach jedem Termin

/** Uhrzeit „9–11 Uhr“ / „15:00–18:00 Uhr“ aus dem Termintext eines FORMA-Kurses → [von, bis] in Minuten oder null. */
function vb_course_span(string $dateText): ?array
{
    if (!preg_match('/(\d{1,2})(?:[:.](\d{2}))?\s*[–-]\s*(\d{1,2})(?:[:.](\d{2}))?\s*Uhr/u', $dateText, $m)) return null;
    return [(int)$m[1] * 60 + (int)($m[2] ?? 0), (int)$m[3] * 60 + (int)($m[4] ?? 0)];
}

function vb_min(string $hm): int
{
    [$h, $i] = array_map('intval', explode(':', substr($hm, 0, 5)) + [0, 0]);
    return $h * 60 + $i;
}

/**
 * Alle Termine im Zeitraum, sortiert: ['date','a','b','kind','title','who','n','block','id'].
 * kind: forma (Kurs), private (Private Pottery), pt (Personal Training), pt-wait (wartet auf Zahlung),
 *       natal (Prä-/Postnatal), call (Kennenlerngespräch).
 * block = Vivien ist dann wirklich beschäftigt (Kurse nur mit Anmeldungen).
 */
function vb_items(PDO $pdo, string $from, string $to): array
{
    $out = [];
    try {
        $st = $pdo->prepare("SELECT c.id, c.title, c.date_text, c.start_date,
            (SELECT COALESCE(SUM(b.persons),0) FROM bookings b WHERE b.course_id = c.id AND (b.status = 'bestaetigt' OR (b.status = 'offen' AND b.created_at >= ?))) AS n
            FROM courses c WHERE substr(c.start_date,1,10) BETWEEN ? AND ? ORDER BY c.start_date");
        $st->execute([date('c', time() - FORMA_HOLD_SECONDS), date('Y-m-d', strtotime($from . ' -26 weeks')), $to]);
        foreach ($st->fetchAll() as $c) {
            $sp = vb_course_span((string)$c['date_text']);
            if (!$sp) continue;
            // Mehrwochen-Kurse (z. B. „Pottery Pause · 5-Wochen-Kurs“): jede Woche am selben Tag
            $weeks = preg_match('/(\d+)\s*-?\s*Wochen/iu', (string)$c['title'] . ' ' . (string)$c['date_text'], $wm) ? max(1, min(26, (int)$wm[1])) : 1;
            for ($w = 0; $w < $weeks; $w++) {
                $d = date('Y-m-d', strtotime(substr((string)$c['start_date'], 0, 10) . ' +' . $w . ' weeks'));
                $out[] = ['date' => $d, 'a' => $sp[0], 'b' => $sp[1], 'kind' => 'forma', 'title' => (string)$c['title'] . ($weeks > 1 ? ' (' . ($w + 1) . '/' . $weeks . ')' : ''), 'who' => '', 'n' => (int)$c['n'], 'block' => (int)$c['n'] > 0, 'id' => (int)$c['id']];
            }
        }
    } catch (Throwable $e) {}
    try {
        $st = $pdo->prepare("SELECT s.id, s.date, s.start, s.hours, p.name FROM private_sessions s JOIN private_bookings p ON p.id = s.private_id WHERE s.date BETWEEN ? AND ? AND s.start <> ''");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $s) {
            $a = vb_min((string)$s['start']);
            $out[] = ['date' => (string)$s['date'], 'a' => $a, 'b' => $a + (int)round((float)$s['hours'] * 60), 'kind' => 'private', 'title' => 'Private Pottery', 'who' => (string)$s['name'], 'n' => 0, 'block' => true, 'id' => (int)$s['id']];
        }
    } catch (Throwable $e) {}
    try {
        $st = $pdo->prepare("SELECT s.id, s.date, s.start, s.minutes, o.name, o.status AS ost, o.id AS oid FROM mm_ptb_sessions s JOIN mm_ptb_orders o ON o.id = s.order_id
            WHERE s.date BETWEEN ? AND ? AND ((o.status = 'bezahlt' AND s.status IN ('bestaetigt','erledigt')) OR (o.status = 'angebot' AND s.status IN ('passt','vorschlag')))");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $s) {
            $a = vb_min((string)$s['start']);
            $out[] = ['date' => (string)$s['date'], 'a' => $a, 'b' => $a + (int)$s['minutes'], 'kind' => $s['ost'] === 'bezahlt' ? 'pt' : 'pt-wait', 'title' => 'Personal Training', 'who' => (string)$s['name'], 'n' => 0, 'block' => true, 'id' => (int)$s['id'], 'order' => (int)$s['oid']];
        }
    } catch (Throwable $e) {}
    try {
        $st = $pdo->prepare("SELECT s.id, s.date, s.start, s.minutes, o.name FROM mm_natal_sessions s JOIN mm_natal_orders o ON o.id = s.order_id WHERE s.date BETWEEN ? AND ? AND s.status IN ('bestaetigt','erledigt') AND s.start <> ''");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $s) {
            $a = vb_min((string)$s['start']);
            $out[] = ['date' => (string)$s['date'], 'a' => $a, 'b' => $a + (int)$s['minutes'], 'kind' => 'natal', 'title' => 'Prä-/Postnatal', 'who' => (string)$s['name'], 'n' => 0, 'block' => true, 'id' => (int)$s['id']];
        }
    } catch (Throwable $e) {}
    try {
        $st = $pdo->prepare("SELECT id, date, slot, name FROM mm_natal_calls WHERE date BETWEEN ? AND ? AND status = 'gebucht'");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $c) {
            $a = vb_min((string)$c['slot']);
            $out[] = ['date' => (string)$c['date'], 'a' => $a, 'b' => $a + 30, 'kind' => 'call', 'title' => 'Kennenlerngespräch (online)', 'who' => (string)$c['name'], 'n' => 0, 'block' => true, 'id' => (int)$c['id']];
        }
    } catch (Throwable $e) {}
    $out = array_values(array_filter($out, fn($it) => $it['date'] >= $from && $it['date'] <= $to));
    usort($out, fn($x, $y) => strcmp($x['date'] . sprintf('%04d', $x['a']), $y['date'] . sprintf('%04d', $y['a'])));
    return $out;
}

/**
 * Belegte Zeiten [datum => [[von, bis], …]] (Minuten, ohne Puffer).
 * $skip: Arten, die nicht zählen sollen (z. B. ['pt','pt-wait'] wenn Personal Training selbst rechnet).
 * $exceptOrder: eine Personal-Training-Buchung nicht gegen sich selbst prüfen.
 */
function vb_busy(PDO $pdo, string $from, string $to, array $skip = [], int $exceptOrder = 0): array
{
    $busy = [];
    foreach (vb_items($pdo, $from, $to) as $it) {
        if (!$it['block'] || in_array($it['kind'], $skip, true)) continue;
        if ($exceptOrder && ($it['order'] ?? 0) === $exceptOrder) continue;
        $busy[$it['date']][] = [$it['a'], $it['b']];
    }
    return $busy;
}

/** Ist [a, a+min) an diesem Tag frei (mit Puffer)? */
function vb_free(array $busy, string $date, int $a, int $min): bool
{
    $b = $a + $min;
    foreach ($busy[$date] ?? [] as [$x, $y]) if ($a < $y + VB_BUFFER && $b > $x - VB_BUFFER) return false;
    return true;
}

/** Überschneidungen zwischen belegten Terminen (für die Warnung in der Verwaltung): [datum => true]. */
function vb_conflicts(array $items): array
{
    $by = [];
    foreach ($items as $it) if ($it['block']) $by[$it['date']][] = $it;
    $out = [];
    foreach ($by as $d => $list) {
        for ($i = 0; $i < count($list); $i++) for ($j = $i + 1; $j < count($list); $j++) {
            if ($list[$i]['a'] < $list[$j]['b'] && $list[$j]['a'] < $list[$i]['b']) $out[$d] = true;
        }
    }
    return $out;
}

// --- Private Pottery für Hotelgäste (/urlaub/) ----------------------------------------------

const VB_PRIVATE_FROM = '08:00';
const VB_PRIVATE_TO = '18:00';   // letzter Beginn
const VB_PRIVATE_MIN = 120;

/** Startzeiten halbstündlich. */
function vb_private_times(): array
{
    $out = [];
    for ($m = vb_min(VB_PRIVATE_FROM); $m <= vb_min(VB_PRIVATE_TO); $m += 30) $out[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    return $out;
}

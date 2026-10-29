<?php
// „Diese Woche“ für die Movecia-Übersicht (Vivien, 29.10.2026): ganz oben alles, was in der laufenden Woche (Mo–So) ansteht –
// Kennenlerngespräche, Gatherings/Videogespräche, Unfold-Live-Calls, Personal Training, Prä-/Postnatal – plus neue Buchungen der Woche.
declare(strict_types=1);

function mm_week_items(PDO $pdo): array
{
    require_once dirname(__DIR__) . '/api/belegt.inc.php';
    $from = date('Y-m-d', strtotime('monday this week'));
    $to = date('Y-m-d', strtotime('sunday this week'));
    $out = [];
    $add = function (string $date, int $min, string $ico, string $title, string $sub, string $href) use (&$out) {
        $out[] = ['date' => $date, 'a' => $min, 'ico' => $ico, 'title' => $title, 'sub' => $sub, 'href' => $href];
    };
    foreach (vb_items($pdo, $from, $to) as $it) {
        if ($it['date'] < $from || $it['date'] > $to) continue;
        if ($it['kind'] === 'pt') $add($it['date'], $it['a'], '🏋️', 'Personal Training', (string)$it['who'], '?view=training');
        elseif ($it['kind'] === 'pt-wait') $add($it['date'], $it['a'], '🏋️', 'Personal Training (noch nicht bezahlt)', (string)$it['who'], '?view=training');
        elseif ($it['kind'] === 'natal') $add($it['date'], $it['a'], '🤰', 'Prä-/Postnatal', (string)$it['who'], '?view=natal');
        elseif ($it['kind'] === 'call') $add($it['date'], $it['a'], '💬', (string)$it['title'], (string)$it['who'], str_contains((string)$it['title'], 'Teacher') ? '?view=academy#gespraeche' : '?view=natal');
    }
    try {
        $st = $pdo->prepare('SELECT id, title, starts_at, visible FROM mm_gatherings WHERE substr(starts_at,1,10) BETWEEN ? AND ? ORDER BY starts_at');
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $g) {
            $t = strtotime((string)$g['starts_at']);
            $add(date('Y-m-d', $t), (int)date('G', $t) * 60 + (int)date('i', $t), (int)$g['visible'] ? '✨' : '📅', ((int)$g['visible'] ? 'Gathering · ' : 'Videogespräch · ') . $g['title'], '', '?view=movecia&p=gatherings');
        }
    } catch (Throwable $e) {}
    // Unfold-Live-Calls: jeden Sonntag zwischen Start und Ende, Uhrzeiten aus den Einstellungen
    try {
        require_once dirname(__DIR__) . '/api/method-lib.php';
        $c = mv_cfg();
        $sun = $to;
        if ($sun >= (string)$c['u_start'] && $sun <= (string)$c['u_end']) {
            $n = 0; try { $n = (int)$pdo->query("SELECT COUNT(*) FROM mv_subs WHERE kind = 'unfold' AND status = 'aktiv'")->fetchColumn(); } catch (Throwable $e) {}
            foreach (['de' => 'Deutsch', 'en' => 'Englisch'] as $l => $lbl) {
                if (!preg_match('/(\d{1,2})[:.](\d{2})\s*(am|pm)?/i', (string)$c['u_call_' . $l], $m)) continue;
                $h = (int)$m[1] + (strtolower($m[3] ?? '') === 'pm' && (int)$m[1] < 12 ? 12 : 0);
                $add($sun, $h * 60 + (int)$m[2], '🌱', 'Unfold Live-Call · ' . $lbl, $n ? $n . ' Teilnehmerinnen' : '', '?view=method');
            }
        }
    } catch (Throwable $e) {}
    // Neue Buchungen dieser Woche
    $lab = ['unfold' => 'Unfold', 'method' => 'Mindful Movement Method'];
    try {
        $st = $pdo->prepare("SELECT kind, name, COALESCE(NULLIF(paid_at,''), created_at) AS d FROM mv_subs WHERE status IN ('aktiv','beendet') AND substr(COALESCE(NULLIF(paid_at,''), created_at),1,10) BETWEEN ? AND ? AND (source IS NULL OR source <> 'test')");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $b) { $t = strtotime((string)$b['d']); $add(date('Y-m-d', $t), (int)date('G', $t) * 60 + (int)date('i', $t), '🎉', 'Neue Buchung · ' . ($lab[$b['kind']] ?? $b['kind']), (string)$b['name'], '?view=method'); }
    } catch (Throwable $e) {
        try {
            $st = $pdo->prepare("SELECT kind, name, created_at AS d FROM mv_subs WHERE status IN ('aktiv','beendet') AND substr(created_at,1,10) BETWEEN ? AND ?");
            $st->execute([$from, $to]);
            foreach ($st->fetchAll() as $b) { $t = strtotime((string)$b['d']); $add(date('Y-m-d', $t), (int)date('G', $t) * 60 + (int)date('i', $t), '🎉', 'Neue Buchung · ' . ($lab[$b['kind']] ?? $b['kind']), (string)$b['name'], '?view=method'); }
        } catch (Throwable $e2) {}
    }
    try {
        $st = $pdo->prepare("SELECT name, paid_at FROM ac_bookings WHERE status = 'bezahlt' AND substr(paid_at,1,10) BETWEEN ? AND ?");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $b) { $t = strtotime((string)$b['paid_at']); $add(date('Y-m-d', $t), (int)date('G', $t) * 60 + (int)date('i', $t), '🎓', 'Neue Buchung · Teacher Training', (string)$b['name'], '?view=academy'); }
    } catch (Throwable $e) {}
    usort($out, fn($a, $b) => [$a['date'], $a['a']] <=> [$b['date'], $b['a']]);
    return [$from, $to, $out];
}

function mm_week_box(PDO $pdo, string $self): void
{
    [$from, $to, $items] = mm_week_items($pdo);
    $wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    $today = date('Y-m-d'); ?>
<section class="mwk">
  <h2>Diese Woche <small><?= date('j.n.', strtotime($from)) ?> – <?= date('j.n.', strtotime($to)) ?></small></h2>
  <?php if (!$items): ?><p class="mwk-none">Diese Woche steht noch nichts an.</p><?php else: ?>
  <div class="mwk-list">
  <?php foreach ($items as $it): $t = strtotime($it['date']); ?>
    <a class="mwk-it<?= $it['date'] < $today ? ' past' : '' ?><?= $it['date'] === $today ? ' today' : '' ?>" href="<?= forma_h($self . $it['href']) ?>">
      <span class="mwk-d"><b><?= $wd[(int)date('w', $t)] ?></b><small><?= date('j.n.', $t) ?></small></span>
      <span class="mwk-t"><strong><?= $it['ico'] ?> <?= forma_h($it['title']) ?></strong><span><?= sprintf('%d:%02d', intdiv($it['a'], 60), $it['a'] % 60) ?> Uhr<?= $it['sub'] !== '' ? ' · ' . forma_h($it['sub']) : '' ?></span></span>
    </a>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<style>
.mwk{margin:12px 0;padding:16px 18px;border-radius:22px;background:#FBF8F4;border:1px solid #E6DCD2}
.mwk h2{margin:0 0 10px;font-size:24px}.mwk h2 small{font-size:15px;color:#7D7076;font-family:inherit}
.mwk-none{margin:0;color:#7D7076;font-size:17px}
.mwk-list{display:grid;gap:8px;grid-template-columns:repeat(auto-fill,minmax(270px,1fr))}
.mwk-it{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:16px;background:#F2ECE4;color:#2E2529;text-decoration:none;border:1px solid transparent}
.mwk-it:hover{border-color:#7D5E68}
.mwk-it.today{background:#fff;border-color:#7D5E68}
.mwk-it.past{opacity:.5}
.mwk-d{display:flex;flex-direction:column;align-items:center;min-width:46px;padding:4px 0;border-radius:12px;background:#7D5E68;color:#FBF5F7;line-height:1.1}
.mwk-d b{font-size:17px}.mwk-d small{font-size:12px;opacity:.9}
.mwk-t{display:flex;flex-direction:column;min-width:0;font-size:15px;color:#7D7076}
.mwk-t strong{color:#2E2529;font-size:16px;font-weight:600;line-height:1.25}
</style>
<?php
}

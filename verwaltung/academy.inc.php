<?php
// Verwaltung → Movecia → „🎓 Academy“ (?view=academy, Vivien 28.10.2026): ganz einfache Liste der Teacher-Training-Buchungen
// (Name, Datum, bezahlt/offen, Early-Bird ja/nein, freie Plätze) und die Kennenlerngespräche (bestätigen, absagen,
// im Gespräch das Angebot mit Countdown starten). Eingebunden aus verwaltung/movecia.php (angemeldet, CSRF geprüft).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/academy-lib.php';
ac_schema($pdo);
$acBack = $self . '?view=academy';

if ($action === 'mm_ac_call_ok') { go($acBack . '#gespraeche', ac_call_confirm($pdo, (int)($_POST['id'] ?? 0))); }
if ($action === 'mm_ac_call_no') { ac_call_cancel($pdo, (int)($_POST['id'] ?? 0)); go($acBack . '#gespraeche', 'Abgesagt, sie hat eine Mail mit einem Link für einen neuen Termin bekommen.'); }
if ($action === 'mm_ac_offer') {
    try { $link = ac_offer_start($pdo, (int)($_POST['id'] ?? 0)); $_SESSION['ac_offer_link'] = $link; go($acBack . '#gespraeche', 'Das Angebot läuft jetzt ' . AC_OFFER_MINUTES . ' Minuten. Schick ihr den Link unten im Chat des Videoraums.'); }
    catch (Throwable $e) { go($acBack . '#gespraeche', $e->getMessage()); }
}
if ($action === 'mm_ac_check') {
    foreach ($pdo->query("SELECT * FROM ac_bookings WHERE status = 'offen' AND stripe_session <> ''")->fetchAll() as $r) { try { ac_check_paid($pdo, $r); } catch (Throwable $e) {} }
    go($acBack, 'Offene Zahlungen sind geprüft.');
}

if ($view === 'academy') {
    try { ac_tick($pdo); } catch (Throwable $e) {}
    $c = ac_cfg();
    $rows = $pdo->query("SELECT * FROM ac_bookings WHERE status IN ('bezahlt','offen') ORDER BY CASE status WHEN 'bezahlt' THEN 0 ELSE 1 END, id DESC")->fetchAll();
    $rows = array_values(array_filter($rows, fn($r) => $r['status'] === 'bezahlt' || strtotime((string)$r['created_at']) >= time() - 2 * 86400));
    $paidN = count(array_filter($rows, fn($r) => $r['status'] === 'bezahlt' && (int)$r['test'] === 0));
    $free = ac_free($pdo);
    $calls = $pdo->query("SELECT * FROM ac_calls WHERE status IN ('angefragt','bestaetigt') AND date >= '" . date('Y-m-d', time() - 86400) . "' ORDER BY date, slot")->fetchAll();
    $offerLink = (string)($_SESSION['ac_offer_link'] ?? ''); unset($_SESSION['ac_offer_link']);
    render_page('Academy · Teacher Training', function () use ($self, $flash, $c, $rows, $paidN, $free, $calls, $offerLink) {
        $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '">';
        ?>
<style>
.ah{border-radius:26px;background:#6B5658;color:#FBF5F7;padding:24px}
.ah h1{color:#FBF5F7!important;margin:0 0 6px}.ah p{margin:0;color:#EADFDE;font-size:18px}
.big3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px}
.big3 div{background:#FBF8F4;border-radius:18px;padding:14px;color:#2E2529;text-align:center}
.big3 b{display:block;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:40px;line-height:1;color:#7D5E68}
.big3 span{font-size:16px;color:#7D7076}
.ac-row{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 16px;align-items:center;padding:14px 0;border-top:1px solid #DDD2C6;font-size:19px}
.ac-row:first-child{border-top:0}
.ac-row .nm{font-size:21px}.ac-row .sm{display:block;font-size:15px;color:#7D7076}
.tag{display:inline-block;border-radius:999px;padding:4px 14px;font-size:16px;margin-left:6px}
.tag.ok{background:#7D5E68;color:#FBF5F7}.tag.open{background:#F4DCD6;color:#6E2E25}.tag.eb{background:#E8D6CA;color:#4A3A41}.tag.test{background:#EEE;color:#555}
.btn{background:#7D5E68;border-color:#7D5E68;color:#FBF5F7}.btn-ghost{background:transparent;color:#7D5E68}
.ac-row form{display:inline}
.ac-row .btn{font-size:16px}
.olink{background:#EADFDE;border-radius:16px;padding:14px;font-size:16px;word-break:break-all}
details.why{margin-top:6px;font-size:16px;color:#4A3A41}details.why summary{cursor:pointer;color:#7D5E68}
</style>
<?php mm_subnav('academy'); ?>
<div class="ah">
  <h1>Teacher Training</h1>
  <p>Start <?= mv_date($c['start_ts']) ?> · <?= $c['eb'] ? 'Early-Bird ' . mv_eur($c['eb_once']) . ' bis ' . mv_date($c['eb_end']) . ', danach ' . mv_eur($c['r_once']) : mv_eur($c['r_once']) ?></p>
  <div class="big3">
    <div><b><?= $paidN ?></b><span>gebucht</span></div>
    <div><b><?= $free ?></b><span>Plätze frei</span></div>
    <div><b><?= count(array_filter($calls, fn($x) => $x['status'] === 'angefragt')) ?></b><span>Gespräche zu bestätigen</span></div>
  </div>
</div>
<?php if ($flash): ?><p class="card" style="margin-top:12px"><?= forma_h($flash) ?></p><?php endif; ?>
<p style="margin-top:12px"><a class="btn btn-ghost" href="<?= forma_h(mm_site()) ?>/academy/teacher-training/" target="_blank" rel="noopener">Seite ansehen</a></p>

<h2>Buchungen</h2>
<div class="card">
  <?php if (!$rows): ?><p style="font-size:19px">Noch keine Buchungen.</p><?php endif; ?>
  <?php foreach ($rows as $r): ?>
  <div class="ac-row">
    <span><span class="nm"><?= forma_h($r['name']) ?></span><span class="sm"><?= date('d.m.Y', strtotime((string)($r['paid_at'] ?: $r['created_at']))) ?> · <?= forma_h($r['email']) ?> · <?= forma_h($r['phone']) ?></span>
      <details class="why"><summary>Fragebogen</summary>Praxis: <?= forma_h(ac_practice_label((string)$r['practice'])) ?><?= $r['practice_note'] !== '' ? '<br>' . nl2br(forma_h($r['practice_note'])) : '' ?><br><br>Warum: <?= nl2br(forma_h($r['why'])) ?></details></span>
    <span><?= $r['status'] === 'bezahlt' ? '<span class="tag ok">bezahlt</span>' : '<span class="tag open">offen</span>' ?><?= (int)$r['eb'] ? '<span class="tag eb">Early-Bird</span>' : '' ?><?= $r['plan'] === 'inst' ? '<span class="tag eb">' . (int)$c['n'] . ' Raten</span>' : '' ?><?= (int)$r['test'] ? '<span class="tag test">Test</span>' : '' ?></span>
  </div>
  <?php endforeach; ?>
  <?php if (array_filter($rows, fn($r) => $r['status'] === 'offen')): ?><form method="post" style="margin-top:10px"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_check"><button class="btn btn-ghost" type="submit">Offene Zahlungen prüfen</button></form><?php endif; ?>
</div>

<h2 id="gespraeche">Kennenlerngespräche</h2>
<?php if ($offerLink !== ''): ?><div class="olink"><b>Link fürs Angebot (30 Min.):</b><br><span class="sel"><?= forma_h($offerLink) ?></span><br><button class="btn" type="button" style="margin-top:8px" onclick="navigator.clipboard.writeText(<?= forma_h(json_encode($offerLink)) ?>);this.textContent='Kopiert ✓'">Link kopieren</button></div><?php endif; ?>
<div class="card">
  <?php if (!$calls): ?><p style="font-size:19px">Gerade keine Gespräche.</p><?php endif; ?>
  <?php foreach ($calls as $x): ?>
  <div class="ac-row">
    <span><span class="nm"><?= forma_h(ac_call_when($x)) ?></span><span class="sm"><?= forma_h($x['name']) ?> · <?= forma_h($x['email']) ?><?= $x['phone'] !== '' ? ' · ' . forma_h($x['phone']) : '' ?><?= $x['note'] !== '' ? '<br>„' . forma_h($x['note']) . '“' : '' ?></span></span>
    <span>
      <?php if ($x['status'] === 'angefragt'): ?>
        <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_call_ok"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn" type="submit">✓ Passt</button></form>
      <?php else: ?>
        <?php if ($x['host_url'] !== ''): ?><a class="btn" href="<?= forma_h($x['host_url']) ?>" target="_blank" rel="noopener">Zum Gespräch</a><?php endif; ?>
        <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_offer"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn-ghost" type="submit">✦ Angebot zeigen</button></form>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Gespräch absagen? Sie bekommt eine Mail mit einem Link für einen neuen Termin.')"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_call_no"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn-ghost" type="submit">absagen</button></form>
    </span>
  </div>
  <?php endforeach; ?>
  <p class="muted small" style="margin-top:10px">„✦ Angebot zeigen“ im Gespräch: Für 30 Minuten bekommt sie über ihren Link den Early-Bird-Preis (<?= mv_eur($c['eb_once']) ?>) mit Countdown, auch wenn der Early-Bird schon vorbei ist. Den Link schickst du ihr im Chat des Videoraums.</p>
</div>
<?php });
    exit;
}

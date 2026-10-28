<?php
// Verwaltung → Movecia → „🎓 Academy“ (?view=academy, Vivien 28.10.2026): ganz einfach und groß.
// Oben: gebucht / Plätze frei / neue Nachrichten / Gespräche. Dann Chat (unbeantwortet zuerst, Antwort-Feld),
// Buchungen mit bezahlt / offen / nächste Zahlung, Kennenlerngespräche (bestätigen, Angebot mit Countdown, absagen).
// Eingebunden aus verwaltung/movecia.php (angemeldet, CSRF geprüft).
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
    foreach ($pdo->query("SELECT * FROM ac_bookings WHERE status IN ('offen','bezahlt')")->fetchAll() as $r) {
        foreach (ac_parts($r) as $pt) if ($pt['paid'] === '' && $pt['session'] !== '') { try { ac_check_part($pdo, $r, (int)$pt['n']); } catch (Throwable $e) {} }
    }
    go($acBack, 'Offene Zahlungen sind geprüft.');
}
if ($action === 'mm_ac_reply') {
    $r = ac_booking($pdo, (int)($_POST['id'] ?? 0));
    if ($r) { ac_msg_reply($pdo, $r, (string)($_POST['text'] ?? '')); go($acBack . '#chat', 'Deine Antwort ist im Chat und sie hat eine Mail bekommen.'); }
    go($acBack . '#chat');
}
if ($action === 'mm_ac_seen') {
    $pdo->prepare("UPDATE ac_msgs SET seen = 1 WHERE booking_id = ? AND who = 'tn'")->execute([(int)($_POST['id'] ?? 0)]);
    $pdo->prepare("INSERT INTO ac_msgs (booking_id, who, text, seen, created_at) VALUES (?, 'viv', '', 1, ?)")->execute([(int)($_POST['id'] ?? 0), date('c')]);
    go($acBack . '#chat', 'Als erledigt markiert.');
}

if ($view === 'academy') {
    try { ac_tick($pdo); } catch (Throwable $e) {}
    $c = ac_cfg();
    $rows = $pdo->query("SELECT * FROM ac_bookings WHERE status IN ('bezahlt','offen') ORDER BY CASE status WHEN 'bezahlt' THEN 0 ELSE 1 END, id DESC")->fetchAll();
    $rows = array_values(array_filter($rows, fn($r) => $r['status'] === 'bezahlt' || strtotime((string)$r['created_at']) >= time() - 2 * 86400));
    $paidN = count(array_filter($rows, fn($r) => $r['status'] === 'bezahlt' && (int)$r['test'] === 0));
    $free = ac_free($pdo);
    $calls = $pdo->query("SELECT * FROM ac_calls WHERE status IN ('angefragt','bestaetigt') AND date >= '" . date('Y-m-d', time() - 86400) . "' ORDER BY date, slot")->fetchAll();
    // Chats: je Teilnehmerin der Verlauf, unbeantwortete zuerst
    $chats = [];
    foreach ($rows as $r) if ($r['status'] === 'bezahlt') {
        $ms = ac_msgs($pdo, (int)$r['id']);
        $lq = $pdo->prepare('SELECT who, created_at FROM ac_msgs WHERE booking_id = ? ORDER BY id DESC LIMIT 1'); $lq->execute([(int)$r['id']]);
        $last = $lq->fetch();
        if (!$last) continue;
        $chats[] = ['r' => $r, 'ms' => $ms, 'open' => $last['who'] === 'tn', 'at' => $last['created_at']];
    }
    usort($chats, fn($a, $b) => [$b['open'], $b['at']] <=> [$a['open'], $a['at']]);
    $openChats = count(array_filter($chats, fn($x) => $x['open']));
    $offerLink = (string)($_SESSION['ac_offer_link'] ?? ''); unset($_SESSION['ac_offer_link']);
    render_page('Academy · Teacher Training', function () use ($self, $flash, $c, $rows, $paidN, $free, $calls, $offerLink, $chats, $openChats) {
        $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '">';
        $d = fn(string $ymd) => date('j.n.Y', strtotime($ymd)); ?>
<style>
.ah{border-radius:26px;background:#6B5658;color:#FBF5F7;padding:24px;position:relative;overflow:hidden}
.ah::after{content:"";position:absolute;width:200px;height:200px;right:-70px;top:-80px;border-radius:58% 42% 47% 53%/52% 46% 54% 48%;background:rgba(232,214,202,.3)}
.ah h1{color:#FBF5F7!important;margin:0 0 6px;position:relative}.ah p{margin:0;color:#EADFDE;font-size:18px;position:relative}
.big4{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px;position:relative}
@media(min-width:760px){.big4{grid-template-columns:repeat(4,minmax(0,1fr))}}
.big4 a,.big4 div{background:#FBF8F4;border-radius:18px;padding:14px;color:#2E2529;text-align:center;text-decoration:none}
.big4 b{display:block;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:40px;line-height:1;color:#7D5E68}
.big4 .hot b{color:#A0463A}
.big4 span{font-size:16px;color:#7D7076}
.ac-row{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 16px;align-items:center;padding:16px 0;border-top:1px solid #DDD2C6;font-size:19px}
.ac-row:first-child{border-top:0}
.ac-row .nm{font-size:22px}.ac-row .sm{display:block;font-size:16px;color:#7D7076;margin-top:2px}
.tag{display:inline-block;border-radius:999px;padding:4px 14px;font-size:16px;margin:2px 0 2px 6px}
.tag.ok{background:#7D5E68;color:#FBF5F7}.tag.open{background:#F4DCD6;color:#6E2E25}.tag.eb{background:#E8D6CA;color:#4A3A41}.tag.test{background:#EEE;color:#555}
.money{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:8px;width:100%}
.money div{background:#F2ECE4;border-radius:14px;padding:10px 12px;font-size:15px;color:#7D7076}.money b{display:block;font-size:20px;color:#2E2529;font-weight:500}
@media(max-width:600px){.money{grid-template-columns:1fr}}
.ac-row form{display:inline}.ac-row .btn{font-size:16px}
.btn{background:#7D5E68;border-color:#7D5E68;color:#FBF5F7}.btn-ghost{background:transparent;color:#7D5E68}
.olink{background:#EADFDE;border-radius:16px;padding:14px;font-size:16px;word-break:break-all}
details.why{margin-top:6px;font-size:16px;color:#4A3A41}details.why summary{cursor:pointer;color:#7D5E68}
.chatc{border-top:1px solid #DDD2C6;padding:16px 0}.chatc:first-child{border-top:0}
.chatc h3{margin:0;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.bub{max-width:640px;border-radius:18px;padding:10px 14px;margin:8px 0;font-size:18px;line-height:1.5;white-space:pre-wrap}
.bub small{display:block;font-size:13px;color:#7D7076;margin-top:4px}
.bub.tn{background:#F2ECE4}.bub.viv{background:#7D5E68;color:#FBF5F7;margin-left:auto}.bub.viv small{color:#EADFDE}
.reply{display:flex;flex-direction:column;gap:8px;margin-top:8px}.reply textarea{min-height:90px;font-size:18px}
</style>
<?php mm_subnav('academy'); ?>
<div class="ah">
  <h1>Teacher Training 2027</h1>
  <p>Start <?= mv_date($c['start_ts']) ?> · <?= $c['eb'] ? 'Early-Bird ' . mv_eur($c['eb_total']) . ' bis ' . mv_date($c['eb_end']) . ', danach ' . mv_eur($c['r_total']) : mv_eur($c['r_total']) ?></p>
  <div class="big4">
    <div><b><?= $paidN ?></b><span>gebucht</span></div>
    <div><b><?= $free ?></b><span>Plätze frei</span></div>
    <a href="#chat"<?= $openChats ? ' class="hot"' : '' ?>><b><?= $openChats ?></b><span>neue Nachrichten</span></a>
    <a href="#gespraeche"><b><?= count(array_filter($calls, fn($x) => $x['status'] === 'angefragt')) ?></b><span>Gespräche bestätigen</span></a>
  </div>
</div>
<?php if ($flash): ?><p class="card" style="margin-top:12px;font-size:18px"><?= forma_h($flash) ?></p><?php endif; ?>
<p style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px"><a class="btn btn-ghost" href="<?= forma_h(mm_site()) ?>/academy/teacher-training/" target="_blank" rel="noopener">Seite ansehen</a><a class="btn btn-ghost" href="<?= forma_h(mm_site()) ?>/academy/mitglieder/?demo=1" target="_blank" rel="noopener">Mitgliederbereich ansehen (Beispiel)</a><a class="btn btn-ghost" href="<?= forma_h($self) ?>?view=studio">🎥 Willkommensvideo aufnehmen</a></p>

<h2 id="chat">💬 Nachrichten</h2>
<div class="card">
  <?php if (!$chats): ?><p style="font-size:19px">Noch keine Nachrichten. Wenn eine Teilnehmerin dir im Mitgliederbereich schreibt, steht es hier und du bekommst eine Mail.</p><?php endif; ?>
  <?php foreach ($chats as $ch): $r = $ch['r']; ?>
  <div class="chatc">
    <h3><?= forma_h($r['name']) ?><?= $ch['open'] ? '<span class="tag open">wartet auf dich</span>' : '<span class="tag eb">beantwortet</span>' ?></h3>
    <?php foreach (array_slice($ch['ms'], -6) as $m): ?><div class="bub <?= $m['who'] ?>"><?= forma_h($m['text']) ?><small><?= $m['who'] === 'viv' ? 'Du' : forma_h(forma_first_name((string)$r['name'])) ?> · <?= date('j.n. H:i', strtotime($m['created_at'])) ?></small></div><?php endforeach; ?>
    <form method="post" class="reply"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_reply"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><textarea name="text" placeholder="Deine Antwort an <?= forma_h(forma_first_name((string)$r['name'])) ?> …" required></textarea><span style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn" type="submit">Antwort schicken</button></span></form>
    <?php if ($ch['open']): ?><form method="post" style="margin-top:6px"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_seen"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link small" type="submit">Ohne Antwort als erledigt markieren</button></form><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<h2>Buchungen</h2>
<div class="card">
  <?php if (!$rows): ?><p style="font-size:19px">Noch keine Buchungen.</p><?php endif; ?>
  <?php foreach ($rows as $r): $paid = ac_paid_cents($r); $next = ac_next_part($r); ?>
  <div class="ac-row">
    <span><span class="nm"><?= forma_h($r['name']) ?></span><span class="sm"><?= date('d.m.Y', strtotime((string)($r['paid_at'] ?: $r['created_at']))) ?> · <?= forma_h($r['email']) ?> · <?= forma_h($r['phone']) ?> · <?= forma_h(ac_plan_label((string)$r['plan'])) ?></span></span>
    <span><?= $r['status'] === 'bezahlt' ? '<span class="tag ok">Platz sicher</span>' : '<span class="tag open">Zahlung offen</span>' ?><?= (int)$r['eb'] ? '<span class="tag eb">Early-Bird</span>' : '' ?><?= (int)$r['test'] ? '<span class="tag test">Test</span>' : '' ?></span>
    <div class="money"><div>bezahlt<b><?= mv_eur($paid) ?></b></div><div>offen<b><?= mv_eur((int)$r['total_cents'] - $paid) ?></b></div><div>nächste Zahlung<b><?= $next ? ($next['due'] === '' ? 'jetzt' : $d($next['due'])) . ' · ' . mv_eur((int)$next['cents']) : '✓ alles bezahlt' ?></b></div></div>
    <details class="why"><summary>Fragebogen &amp; Zahlungsplan</summary>Praxis: <?= forma_h(ac_practice_label((string)$r['practice'])) ?><?= $r['practice_note'] !== '' ? '<br>' . nl2br(forma_h($r['practice_note'])) : '' ?><br><br>Warum: <?= nl2br(forma_h($r['why'])) ?><br><br><?= implode('<br>', array_map('forma_h', ac_parts_lines($r))) ?><?php if ($r['status'] === 'bezahlt' && $next): ?><br><br>Bezahl-Link für sie: <span class="sel"><?= forma_h(ac_pay_link($r)) ?></span><?php endif; ?></details>
  </div>
  <?php endforeach; ?>
  <form method="post" style="margin-top:10px"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_check"><button class="btn btn-ghost" type="submit">Zahlungen bei Stripe prüfen</button></form>
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
  <p class="muted small" style="margin-top:10px">„✦ Angebot zeigen“ im Gespräch: Für 30 Minuten bekommt sie über ihren Link den Early-Bird-Preis (<?= mv_eur($c['eb_total']) ?>) mit Countdown, auch wenn der Early-Bird schon vorbei ist. Den Link schickst du ihr im Chat des Videoraums.</p>
</div>
<?php });
    exit;
}

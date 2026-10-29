<?php
// Pop-up „Wunschtermin Kennenlerngespräch“ (Vivien, 29.10.2026): Interessentinnen schicken Tag (Mo–Fr) + Vormittag/Mittag/Abend
// + freiwillige Uhrzeit. Auf der FORMA-Hauptseite und der Movecia-Übersicht erscheint ein Pop-up: Datum/Uhrzeit prüfen,
// „✓ Einbuchen“ (Mail mit Videoraum an sie) oder „Passt leider nicht“ (Mail mit Bitte um anderen Wunsch). „Später“ blendet es
// für diese Sitzung aus, die kleine Box oben öffnet es wieder. Aktionen laufen über verwaltung/academy.inc.php.
declare(strict_types=1);

function ac_wish_popup(PDO $pdo, string $back): void
{
    try {
        require_once dirname(__DIR__) . '/api/academy-lib.php';
        require_once dirname(__DIR__) . '/api/belegt.inc.php';
        ac_schema($pdo);
        $rows = $pdo->query("SELECT * FROM ac_calls WHERE status = 'angefragt' AND slot = '' ORDER BY created_at")->fetchAll();
    } catch (Throwable $e) { return; }
    if (!$rows) return;
    $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '"><input type="hidden" name="back" value="' . forma_h($back) . '">';
    $ids = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
    $n = count($rows);
    ?>
<button type="button" class="card notice acw-open" onclick="acwShow()" style="width:100%;text-align:left;cursor:pointer;font:inherit"><span class="ic">📅</span><span><strong><?= $n === 1 ? '1 Wunschtermin' : $n . ' Wunschtermine' ?> für ein Kennenlerngespräch</strong><br><span class="muted small">Teacher Training · jetzt einbuchen oder ablehnen →</span></span></button>
<div class="acw" id="acw" hidden role="dialog" aria-modal="true" aria-labelledby="acw-h">
  <div class="acw-c">
    <p class="acw-k">📅 Kennenlerngespräch · Teacher Training</p>
    <h2 id="acw-h"><?= $n === 1 ? 'Neuer Wunschtermin' : $n . ' neue Wunschtermine' ?></h2>
    <?php foreach ($rows as $r):
        $sug = ac_call_suggest_time($r);
        $day = array_filter(vb_items($pdo, (string)$r['date'], (string)$r['date']), fn($it) => !empty($it['block']));
        usort($day, fn($a, $b) => $a['a'] <=> $b['a']); ?>
    <div class="acw-r">
      <p class="acw-n"><?= forma_h((string)$r['name']) ?></p>
      <p class="acw-w"><?= forma_h(ac_call_wish($r)) ?></p>
      <?php if ((string)$r['note'] !== ''): ?><p class="acw-note">„<?= forma_h((string)$r['note']) ?>“</p><?php endif; ?>
      <p class="acw-s"><?= forma_h((string)$r['email']) ?><?= (string)$r['phone'] !== '' ? ' · ' . forma_h((string)$r['phone']) : '' ?></p>
      <p class="acw-day"><?php if ($day): ?>An dem Tag hast du schon: <?= forma_h(implode(' · ', array_map(fn($it) => sprintf('%d:%02d–%d:%02d %s', intdiv($it['a'], 60), $it['a'] % 60, intdiv($it['b'], 60), $it['b'] % 60, $it['title']), $day))) ?><?php else: ?>An dem Tag ist dein Kalender frei.<?php endif; ?></p>
      <form method="post" class="acw-f"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_call_book"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <label>Tag<input type="date" name="date" value="<?= forma_h((string)$r['date']) ?>" required></label>
        <label>Uhrzeit<input type="time" name="time" value="<?= forma_h($sug) ?>" step="300" required></label>
        <button class="btn" type="submit">✓ Einbuchen</button>
      </form>
      <form method="post" onsubmit="return confirm('Passt leider nicht? Sie bekommt eine liebe Mail mit der Bitte um einen anderen Wunschtermin.')"><?= $csrf ?><input type="hidden" name="action" value="mm_ac_call_no"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link acw-no" type="submit">Passt leider nicht</button></form>
    </div>
    <?php endforeach; ?>
    <button type="button" class="btn btn-ghost acw-later" onclick="acwHide()">Später</button>
  </div>
</div>
<style>
.acw{position:fixed;inset:0;z-index:2000;background:rgba(46,37,41,.45);display:flex;align-items:center;justify-content:center;padding:16px;overflow-y:auto}
.acw[hidden]{display:none}
.acw-c{background:#FBF8F4;color:#2E2529;border-radius:26px;max-width:520px;width:100%;padding:24px 22px 18px;box-shadow:0 18px 50px rgba(46,37,41,.25);max-height:calc(100vh - 32px);overflow-y:auto}
.acw-k{margin:0;font-size:14px;color:#7D5E68;letter-spacing:.02em}
.acw-c h2{margin:4px 0 14px;font-size:26px;font-weight:500}
.acw-r{border-top:1px solid #E6DCD2;padding:14px 0}
.acw-n{margin:0;font-size:19px;font-weight:600}
.acw-w{margin:4px 0;font-size:18px;color:#7D5E68}
.acw-note{margin:6px 0;font-size:16px;font-style:italic}
.acw-s{margin:2px 0;font-size:14px;color:#7D7076;word-break:break-word}
.acw-day{margin:8px 0;font-size:14px;color:#4A3A41;background:#EADFDE;border-radius:12px;padding:8px 10px}
.acw-f{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-top:8px}
.acw-f label{display:grid;gap:4px;font-size:14px;color:#7D7076}
.acw-f input{font:inherit;font-size:17px;padding:8px 10px;border:1px solid #DDD2C6;border-radius:12px;background:#fff;color:#2E2529}
.acw-no{margin-top:8px;font-size:15px;color:#7D7076}
.acw-later{margin-top:6px;width:100%}
</style>
<script>
(function(){var K='acw-later',ids='<?= $ids ?>',d=document.getElementById('acw');
  window.acwShow=function(){d.hidden=false;document.body.style.overflow='hidden'};
  window.acwHide=function(){d.hidden=true;document.body.style.overflow='';try{sessionStorage.setItem(K,ids)}catch(e){}};
  d.addEventListener('click',function(e){if(e.target===d)acwHide()});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!d.hidden)acwHide()});
  var seen='';try{seen=sessionStorage.getItem(K)||''}catch(e){}
  if(seen!==ids)acwShow();
})();
</script>
<?php
}

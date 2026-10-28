<?php
// „📚 Mediathek“ (Vivien, 26.10.2026): jede Übung wird einmal gefilmt (immer gleicher Raum, schwarzer Sport-BH, schwarze
// Leggings) und steckt danach in jedem Flow und Bewegungsvideo, das diese Übung enthält. Pro Übung: Clip, Jahreszeiten
// und Stile, damit neue Videos und Monate passend zur Jahreszeit aus vorhandenen Clips zusammengestellt werden können.
// Eingebunden aus verwaltung/movecia.php (?view=mediathek), nur angemeldet. Aktionen in method.inc.php (mm_mv_clip_*).
declare(strict_types=1);

if ($view === 'mediathek') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    mv_seed($pdo);
    $clips = array_values(mv_clips_all($pdo, true));
    $usage = mv_clip_usage($pdo);
    $vc = mv_voice_counts($pdo);
    // Übungen, die gerade in keinem Video stecken, ans Ende
    usort($clips, fn($a, $b) => [isset($usage[$a['ckey']]) ? 0 : 1, -count($usage[$a['ckey']] ?? []), $a['name_de']] <=> [isset($usage[$b['ckey']]) ? 0 : 1, -count($usage[$b['ckey']] ?? []), $b['name_de']]);

    render_page('Mediathek', function () use ($self, $flash, $clips, $usage, $vc) {
        $h = 'forma_h';
        $bunny = mv_bunny_ok();
        $nHave = count(array_filter($clips, fn($c) => $c['guid'] !== ''));
        $u = fn(string $q) => $h($self) . '?' . $q; ?>
<style>
body{background:#F2ECE4}
main{max-width:1200px}
.vh{border-radius:28px;background:#4A3A41;color:#FBF5F7;padding:26px}
.vh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:clamp(34px,6vw,46px);margin:0 0 8px;line-height:1.05}
.vh p{margin:0;color:#EADFDE;font-size:19px;line-height:1.5;max-width:66ch}
.vh .num{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.vh .num span{background:rgba(251,245,247,.12);border-radius:16px;padding:10px 16px;font-size:18px}.vh .num b{font-family:"Cormorant Garamond",Georgia,serif;font-size:30px;font-weight:500;margin-right:6px}
.tools{display:flex;flex-direction:column;gap:10px;margin:20px 0 10px}
.tools input[type=search]{font:inherit;font-size:19px;padding:14px 18px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;width:100%}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chips button{font:inherit;font-size:17px;padding:9px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer}
.chips button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.chips .lab{font-size:16px;color:#7D7076;align-self:center;margin-right:4px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-top:12px}
.mc{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:24px;padding:16px;display:flex;flex-direction:column;gap:10px}
.mc.ok{border-color:#B9CDB2}
.mc[hidden]{display:none}
.mc h3{margin:0;font-size:21px;font-weight:500;color:#2E2529;line-height:1.3;font-family:"Outfit",sans-serif}.mc h3 small{display:block;font-size:15px;color:#7D7076;font-weight:400}
.mc .v{position:relative;aspect-ratio:16/9;border-radius:16px;overflow:hidden;background:linear-gradient(140deg,#7D5E68,#4A3A41)}
.mc .v iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.mc .v .e{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#EADFDE;font-size:16px}
.mc .v button{position:absolute;inset:0;width:100%;border:0;background:transparent;color:#FBF5F7;font:inherit;font-size:18px;cursor:pointer}
.tg{display:flex;flex-wrap:wrap;gap:6px}
.tg button{font:inherit;font-size:15px;padding:6px 12px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#6B5658;cursor:pointer}
.tg button[aria-pressed=true]{background:#EADFDE;border-color:#7D5E68;color:#4A3A41}
.mc .msg{font-size:17px;color:#6B5658}.mc.ok .msg{color:#3F6B3E;font-weight:500}
.up{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 20px;border-radius:999px;background:#4A3A41;color:#FBF5F7;font-size:18px;font-weight:500;cursor:pointer}
.up input{display:none}.up.again{background:transparent;color:#4A3A41;border:1.5px solid #4A3A41;min-height:44px;font-size:16px}
.bar{display:block;height:10px;border-radius:9px;background:#EADFDE;overflow:hidden}.bar[hidden]{display:none}.bar i{display:block;height:100%;width:0;background:#7D5E68;transition:width .3s}
.mc details{font-size:16px;color:#6B5658}.mc details summary{cursor:pointer}.mc details a{display:block;color:#4A3A41;padding:3px 0}
.empty{font-size:19px;color:#6B5658}
.tip{font-size:17px;color:#6B5658;background:#FBF8F4;border:1px dashed #DDD2C6;border-radius:18px;padding:14px 18px;margin-top:16px;line-height:1.5}
</style>
<?php mm_subnav('mediathek'); ?>
<section class="vh"><h1>📚 Mediathek</h1>
  <p>Hier ist jede Übung genau einmal. Du filmst sie einmal (gleicher Raum, schwarzer Sport-BH, schwarze Leggings), und sie steckt automatisch in jedem Flow und Bewegungsvideo, das sie enthält. Jedes Voice-over, das du einsprichst, bleibt hier mit seinem Text gespeichert. Mit Jahreszeit und Stil stelle ich daraus neue Flows passend zur Saison zusammen, die schon fertig eingesprochen sind.</p>
  <div class="num"><span><b><?= $nHave ?></b>von <?= count($clips) ?> Übungen gefilmt</span><a href="<?= $u('view=videos') ?>" style="color:#E8D6CA;font-size:18px;align-self:center">→ Videos hochladen</a></div></section>
<?php if ($flash): ?><p class="alert"><?= $h($flash) ?></p><?php endif; ?>
<?php if (!$bunny): ?><p class="tip">Zum Hochladen muss einmal Bunny verbunden sein: <a href="<?= $u('view=method') ?>#bunny">hier verbinden</a>.</p><?php endif; ?>

<div class="tools">
  <input type="search" id="q" placeholder="Übung suchen, z. B. Katze, Brücke, Atem …" aria-label="Übung suchen">
  <div class="chips" data-grp="have"><span class="lab">Zeigen:</span><button type="button" data-v="" aria-pressed="true">alle</button><button type="button" data-v="0" aria-pressed="false">noch nicht gefilmt</button><button type="button" data-v="1" aria-pressed="false">schon gefilmt</button></div>
  <div class="chips" data-grp="season"><span class="lab">Jahreszeit:</span><button type="button" data-v="" aria-pressed="true">alle</button><?php foreach (MV_SEASONS as $k => $s): ?><button type="button" data-v="<?= $k ?>" aria-pressed="false"><?= $s[2] ?> <?= $h($s[0]) ?></button><?php endforeach; ?></div>
  <div class="chips" data-grp="style"><span class="lab">Stil:</span><button type="button" data-v="" aria-pressed="true">alle</button><?php foreach (MV_STYLES as $k => $s): ?><button type="button" data-v="<?= $k ?>" aria-pressed="false"><?= $h($s) ?></button><?php endforeach; ?></div>
</div>
<p class="empty" id="none" hidden>Dazu gibt es keine Übung.</p>
<div class="grid">
<?php foreach ($clips as $c): $g = (string)$c['guid']; $use = $usage[$c['ckey']] ?? []; $se = array_filter(explode(',', (string)$c['seasons'])); $sy = array_filter(explode(',', (string)$c['styles'])); ?>
  <div class="mc<?= $g ? ' ok' : '' ?>" data-c="<?= (int)$c['id'] ?>" data-have="<?= $g ? 1 : 0 ?>" data-q="<?= $h(mb_strtolower($c['name_de'] . ' ' . $c['name_en'])) ?>" data-se="<?= $h(implode(',', $se)) ?>" data-sy="<?= $h(implode(',', $sy)) ?>">
    <h3><?= $h($c['name_de']) ?><?php if ($c['name_en'] !== $c['name_de']): ?><small><?= $h($c['name_en']) ?></small><?php endif; ?></h3>
    <div class="v"><?php if ($g && mv_bunny()['lib'] !== ''): ?><button type="button" data-play="<?= $h(mv_embed($g) . '&autoplay=true&loop=true&muted=true') ?>">▶ Clip ansehen</button><?php else: ?><span class="e">noch kein Clip</span><?php endif; ?></div>
    <span class="msg"><?= $g ? '✓ gefilmt' : 'noch nicht gefilmt' ?><?= $use ? ' · in ' . count($use) . ' ' . (count($use) === 1 ? 'Video' : 'Videos') : ' · gerade in keinem Video' ?><?php $v = $vc[$c['ckey']] ?? []; if ($v): ?><br>🎙 Voice-over gespeichert: Deutsch <?= (int)($v['de'] ?? 0) ?> · Englisch <?= (int)($v['en'] ?? 0) ?><?php endif; ?></span>
    <?php if ($bunny): ?><label class="up<?= $g ? ' again' : '' ?>"><input type="file" accept="video/*" class="vfile"><span><?= $g ? 'Neu hochladen' : '📹 Clip hochladen' ?></span></label><span class="bar" hidden><i></i></span><?php endif; ?>
    <a class="up again" style="text-decoration:none;text-align:center" href="<?= $u('view=studio&c=' . (int)$c['id']) ?>">🧘 Im Studio filmen</a>
    <div class="tg" data-k="seasons" aria-label="Jahreszeit"><?php foreach (MV_SEASONS as $k => $s): ?><button type="button" data-v="<?= $k ?>" aria-pressed="<?= in_array($k, $se, true) ? 'true' : 'false' ?>"><?= $s[2] ?> <?= $h($s[0]) ?></button><?php endforeach; ?></div>
    <div class="tg" data-k="styles" aria-label="Stil"><?php foreach (MV_STYLES as $k => $s): ?><button type="button" data-v="<?= $k ?>" aria-pressed="<?= in_array($k, $sy, true) ? 'true' : 'false' ?>"><?= $h($s) ?></button><?php endforeach; ?></div>
    <?php if ($use): ?><details><summary>Steckt in …</summary><?php foreach ($use as $lid => $t): ?><a href="<?= $u('view=videos') ?>#v<?= (int)$lid ?>"><?= $h($t) ?></a><?php endforeach; ?></details><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<p class="tip">Stil und Jahreszeit habe ich aus dem Namen vorgeschlagen. Tipp einfach an, was passt, es speichert sich sofort. „Ganzjährig“ heißt: passt in jeden Monat.</p>

<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>;
  function post(a,d){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d){if(Array.isArray(d[k]))d[k].forEach(v=>f.append(k+'[]',v));else f.append(k,d[k])}return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
  const st={have:'',season:'',style:''};
  function filter(){const q=document.getElementById('q').value.trim().toLowerCase();let n=0;
    document.querySelectorAll('.mc').forEach(c=>{const ok=(!q||c.dataset.q.includes(q))&&(st.have===''||c.dataset.have===st.have)&&(!st.season||c.dataset.se.split(',').includes(st.season))&&(!st.style||c.dataset.sy.split(',').includes(st.style));c.hidden=!ok;if(ok)n++});
    document.getElementById('none').hidden=n>0;}
  document.getElementById('q').addEventListener('input',filter);
  document.querySelectorAll('.chips').forEach(g=>g.querySelectorAll('button').forEach(b=>b.addEventListener('click',()=>{st[g.dataset.grp]=b.dataset.v;g.querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));filter()})));
  // Jahreszeit / Stil antippen
  document.querySelectorAll('.tg button').forEach(b=>b.addEventListener('click',()=>{b.setAttribute('aria-pressed',String(b.getAttribute('aria-pressed')!=='true'));const c=b.closest('.mc');
    const pick=k=>[...c.querySelectorAll('.tg[data-k="'+k+'"] button[aria-pressed=true]')].map(x=>x.dataset.v);
    c.dataset.se=pick('seasons').join(',');c.dataset.sy=pick('styles').join(',');post('mm_mv_clip_tags',{c:c.dataset.c,seasons:pick('seasons'),styles:pick('styles')});}));
  // Clip ansehen erst auf Tipp laden
  document.querySelectorAll('[data-play]').forEach(b=>b.addEventListener('click',()=>{b.parentNode.innerHTML='<iframe src="'+b.dataset.play+'" allow="autoplay;fullscreen" allowfullscreen></iframe>'}));
  // Hochladen
  document.querySelectorAll('.mc .vfile').forEach(inp=>inp.addEventListener('change',()=>{const f=inp.files[0];if(!f)return;const c=inp.closest('.mc'),msg=c.querySelector('.msg'),bar=c.querySelector('.bar'),fill=bar.querySelector('i'),lab=c.querySelector('.up span');
    if(typeof tus==='undefined'){msg.textContent='Der Upload konnte nicht starten. Bitte Seite neu laden.';return}
    msg.textContent='Wird vorbereitet …';inp.disabled=true;
    post('mm_mv_clip_new',{c:c.dataset.c}).then(j=>{if(j.error){msg.textContent=j.error;inp.disabled=false;return}
      bar.hidden=false;new tus.Upload(f,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
        headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},metadata:{filetype:f.type,title:f.name},
        onError:()=>{msg.textContent='Upload unterbrochen. Bitte nochmal auswählen.';inp.disabled=false},
        onProgress:(a,t)=>{const p=Math.round(a/t*100);fill.style.width=p+'%';msg.textContent='Lädt hoch … '+p+' %'},
        onSuccess:()=>{post('mm_mv_clip_set',{c:c.dataset.c,guid:j.guid}).then(r=>{inp.disabled=false;bar.hidden=true;c.classList.add('ok');c.dataset.have='1';msg.textContent='✓ gefilmt · in '+r.used+(r.used===1?' Video':' Videos');lab.textContent='Neu hochladen';lab.parentNode.classList.add('again');
          c.querySelector('.v').innerHTML='<iframe src="'+r.view+'&autoplay=true&loop=true&muted=true" allow="autoplay"></iframe>'})}}).start()});}));
})();
</script>
<?php
    });
    exit;
}

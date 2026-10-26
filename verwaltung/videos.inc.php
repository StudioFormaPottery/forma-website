<?php
// „🎬 Videos hochladen“ (Vivien, 26.10.2026: „wo lade ich die Videosequenzen hoch, Schrift zu klein, alles leicht findbar“).
// Eine einzige, große und einfache Seite für alle Videos: oben was als Nächstes dran ist, darunter alle offenen Videos.
// Bewegungsvideo = pro Übung ein Clip hochladen. Talking Head / Atem / Meditation = Teleprompter öffnen, dann das ganze
// Video auf Deutsch und Englisch hochladen. Gleiche Upload-Aktionen wie bisher (mm_mv_clip_*, mm_mv_video_*).
// Eingebunden aus verwaltung/movecia.php (?view=videos), nur angemeldet.
declare(strict_types=1);

if ($view === 'videos') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    mv_seed($pdo);
    $all = [];
    foreach (['method' => 'Method', 'unfold' => 'Unfold', 'natal' => 'Prä- & Postnatal'] as $aud => $an) {
        foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) $all[] = ['aud' => $aud, 'an' => $an, 'p' => $p, 'l' => $l];
    }
    usort($all, fn($a, $b) => [(string)$a['l']['due'] ?: '9999', (int)$a['l']['id']] <=> [(string)$b['l']['due'] ?: '9999', (int)$b['l']['id']]);

    render_page('Videos hochladen', function () use ($self, $flash, $all) {
        $h = 'forma_h';
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
        $WD = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $MO = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $dd = fn(string $d) => $WD[(int)date('w', strtotime($d))] . ', ' . date('j', strtotime($d)) . '. ' . $MO[(int)date('n', strtotime($d))];
        $bunny = mv_bunny_ok();
        $open = array_values(array_filter($all, fn($x) => !mv_lesson_done($x['l'])));
        $done = array_values(array_filter($all, fn($x) => mv_lesson_done($x['l'])));
        $u = fn(string $q) => $h($self) . '?' . $q;

        $card = function (array $x, bool $isOpen) use ($h, $dd, $today, $bunny, $u): void {
            $l = $x['l']; $id = (int)$l['id'];
            $clips = mv_lesson_is_clips($l);
            $talk = $l['cat'] === 'talk';
            $ex = mv_lesson_ex($l); $have = mv_lesson_clips($l);
            $fin = mv_lesson_done($l);
            $late = !$fin && (string)$l['due'] !== '' && $l['due'] < $today;
            $kind = $clips ? 'Bewegungsvideo' : ($talk ? 'Talking Head' : 'Geführtes Video');
            if ($clips) $st = count($have) . ' von ' . count($ex) . ' Clips';
            else $st = ($l['video_de'] !== '' ? 'Deutsch ✓' : 'Deutsch fehlt') . ' · ' . ($l['video_en'] !== '' ? 'Englisch ✓' : 'Englisch fehlt'); ?>
  <details class="vc<?= $fin ? ' fin' : '' ?><?= $late ? ' late' : '' ?>" id="v<?= $id ?>" data-aud="<?= $h($x['aud']) ?>"<?= $isOpen ? ' open' : '' ?>>
    <summary>
      <span class="kind k-<?= $clips ? 'move' : 'talk' ?>"><?= $kind ?></span>
      <span class="ttl"><?= $h($l['title_de']) ?></span>
      <span class="meta"><?= $h($x['an']) ?> · <?= $fin ? '<b class="ok">✓ fertig</b>' : ((string)$l['due'] !== '' ? ($late ? '<b class="bad">war fällig am ' . $h($dd((string)$l['due'])) . '</b>' : 'fertig bis <b>' . $h($dd((string)$l['due'])) . '</b>') : 'ohne Datum') ?> · <span class="st" data-st="<?= $id ?>"><?= $h($st) ?></span></span>
    </summary>
    <div class="body">
    <?php if ($clips): ?>
      <p class="how">Film jede Übung einzeln (10 Sekunden bis 1 Minute) und tippe daneben auf <b>„Clip hochladen“</b>. Ein Clip gilt für Deutsch und Englisch.</p>
      <ol class="exl">
      <?php foreach ($ex as $i => $e): $g = $have[$i] ?? ''; ?>
        <li class="ex cbox" data-id="<?= $id ?>" data-i="<?= $i ?>" data-of="<?= count($ex) ?>">
          <span class="n"><b><?= $i + 1 ?>.</b> <?= $h($e[0]) ?></span>
          <span class="act">
            <span class="msg <?= $g ? 'ok' : '' ?>"><?php if ($g): ?>✓ hochgeladen · <a href="<?= $h(mv_embed($g)) ?>" target="_blank" rel="noopener">ansehen</a><?php endif; ?></span>
            <?php if ($bunny): ?><label class="up<?= $g ? ' again' : '' ?>"><input type="file" accept="video/*" class="vfile"><span><?= $g ? 'Neu hochladen' : '📹 Clip hochladen' ?></span></label><?php endif; ?>
            <span class="bar" hidden><i></i></span>
          </span>
        </li>
      <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <div class="steps">
        <div class="step"><span class="no">1</span><div><b>Mit dem Teleprompter aufnehmen</b><p>Du sprichst einmal am Stück in die Kamera, der Text läuft mit.</p>
          <p class="btns"><?php if (trim((string)$l['script_de']) !== ''): ?><a class="tp" href="<?= $u('view=method&lang=de&prompter=' . $id) ?>" target="_blank" rel="noopener">Teleprompter Deutsch</a><?php endif; ?><?php if (trim((string)$l['script_en']) !== ''): ?><a class="tp" href="<?= $u('view=method&lang=en&prompter=' . $id) ?>" target="_blank" rel="noopener">Teleprompter Englisch</a><?php endif; ?></p></div></div>
        <div class="step"><span class="no">2</span><div><b>Ganzes Video hochladen</b><p>Einmal auf Deutsch und einmal auf Englisch. Schneiden musst du nichts.</p>
          <div class="two">
          <?php foreach (['de' => 'Deutsch', 'en' => 'Englisch'] as $lg => $ln): $g = (string)$l['video_' . $lg]; ?>
            <div class="vbox" data-id="<?= $id ?>" data-lang="<?= $lg ?>">
              <span class="msg <?= $g ? 'ok' : '' ?>"><?= $g ? '✓ ' . $ln . ' ist hochgeladen' : $ln . ': noch kein Video' ?></span>
              <?php if ($bunny): ?><label class="up<?= $g ? ' again' : '' ?>"><input type="file" accept="video/*" class="vfile"><span><?= $g ? 'Neu hochladen' : '📹 ' . $ln . ' hochladen' ?></span></label><?php endif; ?>
              <span class="bar" hidden><i></i></span>
            </div>
          <?php endforeach; ?>
          </div></div></div>
      </div>
    <?php endif; ?>
      <p class="foot">Fertig bis <input type="date" value="<?= $h((string)$l['due']) ?>" data-due="<?= $id ?>" aria-label="Fertig bis"> · <a href="<?= $u('view=method&l=' . $id) ?>">Text oder Angaben ändern</a></p>
    </div>
  </details>
<?php
        }; ?>
<style>
body{background:#F2ECE4}
main{max-width:1100px}
.vh{border-radius:28px;background:#4A3A41;color:#FBF5F7;padding:26px 26px}
.vh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:clamp(34px,6vw,46px);margin:0 0 8px;line-height:1.05}
.vh p{margin:0;color:#EADFDE;font-size:19px;line-height:1.5;max-width:60ch}
.warn{background:#F6DEDA;color:#7A2E2E;border-radius:20px;padding:16px 20px;font-size:19px;margin-top:14px}
.filt{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0 6px}
.filt button{font:inherit;font-size:18px;padding:10px 18px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer}
.filt button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
h2.sec{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:32px;color:#4A3A41;margin:30px 0 10px}
.vc{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:24px;margin:0 0 14px;overflow:hidden}
.vc[open]{border-color:#7D5E68;background:#fff}
.vc.late{border-color:#C98A8A}
.vc>summary{list-style:none;cursor:pointer;padding:18px 20px;display:flex;flex-direction:column;gap:6px}
.vc>summary::-webkit-details-marker{display:none}
.vc>summary::after{content:"Öffnen ▾";align-self:flex-start;font-size:16px;color:#7D5E68;margin-top:2px}
.vc[open]>summary::after{content:"Schließen ▴"}
.kind{align-self:flex-start;font-size:14px;letter-spacing:.06em;text-transform:uppercase;padding:4px 12px;border-radius:999px}
.k-move{background:#E8D6CA;color:#6B4A3E}.k-talk{background:#E3DCEA;color:#5B4A6E}
.ttl{font-size:24px;font-weight:500;color:#2E2529;line-height:1.25}
.meta{font-size:18px;color:#6B5658}.meta b{font-weight:500}.meta b.ok{color:#3F6B3E}.meta b.bad{color:#A04040}
.body{padding:0 20px 20px;border-top:1px solid #EADFDE}
.how{font-size:19px;line-height:1.5;margin:16px 0 8px;color:#2E2529}
.exl{list-style:none;margin:0;padding:0}
.ex{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:14px 0;border-top:1px dashed #DDD2C6}
.ex .n{flex:1 1 260px;font-size:21px;line-height:1.35;color:#2E2529}
.act{display:flex;flex-wrap:wrap;align-items:center;gap:10px;flex:0 1 auto}
.msg{font-size:17px;color:#6B5658}.msg.ok{color:#3F6B3E;font-weight:500}.msg a{color:#3F6B3E}
.up{display:inline-flex;align-items:center;justify-content:center;min-height:54px;padding:12px 22px;border-radius:999px;background:#4A3A41;color:#FBF5F7;font-size:19px;font-weight:500;cursor:pointer}
.up input{display:none}
.up.again{background:transparent;color:#4A3A41;border:1.5px solid #4A3A41;font-weight:500;min-height:46px;font-size:17px}
.bar{display:block;width:100%;height:10px;border-radius:9px;background:#EADFDE;overflow:hidden}.bar[hidden]{display:none}.bar i{display:block;height:100%;width:0;background:#7D5E68;transition:width .3s}
.steps{display:flex;flex-direction:column;gap:18px;margin-top:16px}
.step{display:flex;gap:14px;align-items:flex-start}
.step .no{flex:0 0 auto;width:42px;height:42px;border-radius:50%;background:#7D5E68;color:#fff;font-size:21px;display:flex;align-items:center;justify-content:center}
.step b{font-size:21px;font-weight:500;color:#2E2529}.step p{margin:4px 0 0;font-size:18px;color:#6B5658}
.btns{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px!important}
.tp{display:inline-flex;align-items:center;min-height:50px;padding:10px 20px;border-radius:999px;background:#E8D6CA;color:#4A3A41;text-decoration:none;font-size:18px;font-weight:500}
.two{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-top:12px}
.vbox{display:flex;flex-direction:column;gap:10px;background:#FBF8F4;border:1px solid #DDD2C6;border-radius:18px;padding:14px}
.foot{margin:18px 0 0;font-size:16px;color:#7D7076}.foot input{width:auto;padding:6px 10px;font-size:16px}
.showdone{font:inherit;font-size:18px;margin-top:10px;padding:12px 20px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer}
.empty{font-size:19px;color:#6B5658}
</style>
<?php mm_subnav('videos'); ?>
<section class="vh"><h1>🎬 Videos hochladen</h1><p>Oben steht immer, was als Nächstes dran ist. Tipp auf ein Video, dann siehst du, was du filmen und hochladen musst.</p></section>
<?php if ($flash): ?><p class="alert"><?= $h($flash) ?></p><?php endif; ?>
<?php if (!$bunny): ?><p class="warn">Bevor du hochladen kannst, muss einmal Bunny verbunden werden: <a href="<?= $u('view=method') ?>#bunny">hier verbinden</a>.</p><?php endif; ?>

<div class="filt" role="group" aria-label="Bereich">
  <button type="button" data-f="" aria-pressed="true">Alle</button><button type="button" data-f="method" aria-pressed="false">Method</button><button type="button" data-f="unfold" aria-pressed="false">Unfold</button><button type="button" data-f="natal" aria-pressed="false">Prä- &amp; Postnatal</button>
</div>

<h2 class="sec">Als Nächstes dran</h2>
<?php if (!$open): ?><p class="empty">Alles hochgeladen. Wunderbar!</p><?php endif; ?>
<div id="lst">
<?php foreach ($open as $i => $x) $card($x, $i === 0); ?>
</div>

<?php if ($done): ?>
<button type="button" class="showdone" id="sd">Fertige Videos anzeigen (<?= count($done) ?>)</button>
<div id="donel" hidden><h2 class="sec">Fertig</h2><?php foreach ($done as $x) $card($x, false); ?></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>;
  function post(a,d){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
  // Filter
  document.querySelectorAll('.filt button').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.filt button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));
    document.querySelectorAll('.vc').forEach(c=>c.hidden=b.dataset.f!==''&&c.dataset.aud!==b.dataset.f);}));
  const sd=document.getElementById('sd');if(sd)sd.addEventListener('click',()=>{const d=document.getElementById('donel');d.hidden=!d.hidden;sd.textContent=d.hidden?sd.textContent.replace('ausblenden','anzeigen'):sd.textContent.replace('anzeigen','ausblenden')});
  if(location.hash.match(/^#v\d+$/)){const c=document.querySelector(location.hash);if(c){c.open=true;if(c.closest('#donel'))c.closest('#donel').hidden=false;setTimeout(()=>c.scrollIntoView({block:'start'}),50)}}
  // Fertig bis
  document.querySelectorAll('[data-due]').forEach(inp=>inp.addEventListener('change',()=>post('mm_mv_due',{id:inp.dataset.due,due:inp.value})));
  // Hochladen (Clip oder ganzes Video)
  function upload(box,file,newAct,newData,setAct,setData,done){
    const msg=box.querySelector('.msg'),bar=box.querySelector('.bar'),fill=bar.querySelector('i'),inp=box.querySelector('.vfile'),lab=box.querySelector('.up span');
    if(typeof tus==='undefined'){msg.textContent='Der Upload konnte nicht starten. Bitte Seite neu laden.';return}
    msg.className='msg';msg.textContent='Wird vorbereitet …';inp.disabled=true;
    post(newAct,newData).then(j=>{if(j.error){msg.textContent=j.error;inp.disabled=false;return}
      bar.hidden=false;const up=new tus.Upload(file,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
        headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},metadata:{filetype:file.type,title:file.name},
        onError:()=>{msg.textContent='Upload unterbrochen. Bitte einfach nochmal auswählen.';inp.disabled=false},
        onProgress:(a,t)=>{const p=Math.round(a/t*100);fill.style.width=p+'%';msg.textContent='Lädt hoch … '+p+' % (Seite offen lassen)'},
        onSuccess:()=>{post(setAct,Object.assign({guid:j.guid},setData)).then(r=>{inp.disabled=false;bar.hidden=true;msg.className='msg ok';lab.textContent='Neu hochladen';lab.parentNode.classList.add('again');done(r,msg)})}});
      up.start()}).catch(()=>{msg.textContent='Das hat nicht geklappt. Bitte Seite neu laden.';inp.disabled=false});
  }
  document.querySelectorAll('.cbox').forEach(box=>{const inp=box.querySelector('.vfile');if(!inp)return;inp.addEventListener('change',()=>{const f=inp.files[0];if(!f)return;const id=box.dataset.id,i=box.dataset.i;
    upload(box,f,'mm_mv_clip_new',{id:id,i:i},'mm_mv_clip_set',{id:id,i:i},(r,msg)=>{msg.innerHTML='✓ hochgeladen · <a target="_blank" rel="noopener" href="'+r.view+'">ansehen</a>';
      const s=document.querySelector('[data-st="'+id+'"]');if(s)s.textContent=r.clips+' von '+box.dataset.of+' Clips';
      if(r.done){const c=document.getElementById('v'+id);c.classList.add('fin');c.classList.remove('late')}});});});
  document.querySelectorAll('.vbox').forEach(box=>{const inp=box.querySelector('.vfile');if(!inp)return;inp.addEventListener('change',()=>{const f=inp.files[0];if(!f)return;const id=box.dataset.id,lg=box.dataset.lang;
    upload(box,f,'mm_mv_video_new',{id:id,lang:lg},'mm_mv_video_set',{id:id,lang:lg},(r,msg)=>{msg.textContent='✓ '+(lg==='de'?'Deutsch':'Englisch')+' ist hochgeladen';});});});
})();
</script>
<?php
    });
    exit;
}

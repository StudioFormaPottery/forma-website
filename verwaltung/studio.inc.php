<?php
// „🎥 Talking-Head-Studio“ (Vivien, 28.10.2026): Talking Heads direkt im Browser aufnehmen, mit Kamera-Look
// (Haut weich, Augenringe aufhellen, Hintergrund unscharf, Licht), Hilfe für die Kopfposition und Teleprompter
// oben im Bild (nahe an der Kamera, damit der Blick in die Linse geht). Hilfslinien und Text sind nie in der Aufnahme.
// Danach herunterladen oder direkt in die Lektion hochladen (Bunny, gleiche Aktionen wie „Videos hochladen“).
// ?view=studio[&l=ID][&lang=de|en]. Ohne Lektion: eigener Text (z. B. tägliche Story), Format 9:16 wählbar.
declare(strict_types=1);

if ($view === 'studio') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    $talks = [];
    foreach (['method' => 'Method', 'unfold' => 'Unfold', 'natal' => 'Prä- & Postnatal'] as $aud => $an) {
        foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) {
            if (mv_lesson_is_clips($l)) continue;
            $talks[] = ['id' => (int)$l['id'], 'g' => $an, 't' => (string)$l['title_de'], 'te' => (string)($l['title_en'] ?: $l['title_de']),
                'de' => (string)$l['script_de'], 'en' => (string)$l['script_en'], 'vde' => $l['video_de'] !== '', 'ven' => $l['video_en'] !== '', 'due' => (string)$l['due']];
        }
    }
    $sel = (int)($_GET['l'] ?? 0);
    $lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'de';
    $bunny = mv_bunny_ok();

    render_page('Talking-Head-Studio', function () use ($self, $talks, $sel, $lang, $bunny) {
        $h = 'forma_h'; ?>
<style>
body{background:#F2ECE4}
main{max-width:none;padding-left:clamp(12px,2.4vw,32px);padding-right:clamp(12px,2.4vw,32px)}
.sh{display:flex;flex-wrap:wrap;gap:12px 20px;align-items:flex-end;justify-content:space-between;margin:6px 0 16px}
.sh h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;color:#4A3A41;font-size:clamp(34px,4vw,46px);margin:0;line-height:1.05}
.sh p{margin:4px 0 0;color:#6B5658;font-size:19px;max-width:70ch}
.pick{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:22px;padding:12px 14px;margin-bottom:16px}
.pick label{font-size:17px;color:#6B5658;font-weight:400}
.pick select{font:inherit;font-size:18px;padding:10px 14px;border:1.5px solid #DDD2C6;border-radius:14px;background:#fff;color:#2E2529;max-width:100%;min-width:min(520px,100%)}
.seg{display:flex;gap:6px}.seg button{font:inherit;font-size:18px;padding:10px 18px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:48px}
.seg button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,400px);gap:20px;align-items:start}
@media(max-width:980px){.grid{grid-template-columns:1fr}}
.side{display:flex;flex-direction:column;gap:16px}
.box{background:#FBF8F4;border:1.5px solid #DDD2C6;border-radius:24px;padding:16px}
.box>h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:26px;color:#4A3A41;margin:0 0 12px}
#stage{position:relative}
.pr{position:absolute;left:0;right:0;top:0;height:36%;overflow:hidden;background:linear-gradient(rgba(46,37,41,.72),rgba(46,37,41,.55) 80%,rgba(46,37,41,0));color:#FBF5F7;z-index:3;pointer-events:auto;cursor:pointer;-webkit-mask-image:linear-gradient(#000 70%,transparent);mask-image:linear-gradient(#000 70%,transparent)}
.pr[hidden]{display:none}
.pr-t{padding:14px 6% 60%;white-space:pre-wrap;font:400 var(--fs,30px)/1.45 "Hanken Grotesk","Outfit",system-ui,sans-serif;text-align:center}
.pr-t .hint{color:#C9B7BD;font-size:.6em}
.cd{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:500 min(30vw,180px)/1 "Cormorant Garamond",Georgia,serif;color:#fff;text-shadow:0 2px 30px rgba(0,0,0,.5);z-index:5;pointer-events:none}
.cd:empty{display:none}
.recdot{position:absolute;right:14px;top:14px;z-index:4;display:flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(160,65,59,.92);color:#fff;font:500 18px/1.2 "Hanken Grotesk","Outfit",system-ui,sans-serif;font-variant-numeric:tabular-nums}
.recdot[hidden]{display:none}.recdot i{width:10px;height:10px;border-radius:50%;background:#fff;animation:bl 1s infinite}
@keyframes bl{50%{opacity:.2}}
.bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:14px}
.bar button,.bar a.b{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:58px;padding:12px 26px;border-radius:999px;font:inherit;font-size:20px;font-weight:500;cursor:pointer;border:1.5px solid #4A3A41;background:transparent;color:#4A3A41;text-decoration:none}
.bar .go{background:#A0413B;border-color:#A0413B;color:#fff}
.bar .stop{background:#4A3A41;color:#fff}
.bar .up{background:#3F6B3E;border-color:#3F6B3E;color:#fff}
.bar .sm{min-height:48px;font-size:17px;padding:10px 18px;border-color:#DDD2C6}
.bar [hidden]{display:none!important}
.msg{font-size:18px;color:#6B5658;margin:10px 2px 0}
.msg.ok{color:#3F6B3E;font-weight:500}
.prog{height:10px;border-radius:9px;background:#EADFDE;overflow:hidden;max-width:520px;margin-top:10px}.prog i{display:block;height:100%;width:0;background:#3F6B3E;transition:width .3s}
.prog[hidden]{display:none}
#play{display:block;width:100%;max-height:74vh;border-radius:22px;background:#2E2529;margin:0 auto}
#play[hidden]{display:none}
.own textarea{width:100%;min-height:200px;font:inherit;font-size:18px;line-height:1.5;border:1.5px solid #DDD2C6;border-radius:16px;padding:12px;background:#fff}
.own[hidden]{display:none}
.prc{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.prc button{font:inherit;font-size:17px;padding:10px 16px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:46px}
.prc button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.chk{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:400;margin-top:10px}.chk input{width:24px;height:24px;padding:0;accent-color:#7D5E68}
.dev{display:flex;flex-direction:column;gap:10px}.dev label{font-size:16px;color:#6B5658;font-weight:400;display:flex;flex-direction:column;gap:4px}
.dev select{font:inherit;font-size:17px;padding:10px 12px;border:1.5px solid #DDD2C6;border-radius:14px;background:#fff}
.tip{font-size:16px;color:#7D7076;line-height:1.5;margin:0}
.done-l{font-size:15px;color:#3F6B3E}
</style>
<?php mm_subnav('studio'); ?>
<div class="sh"><div><h1>🎥 Talking-Head-Studio</h1>
  <p>Kamera an, Look wählen, ins Oval setzen, aufnehmen. Der Text läuft oben im Bild, ganz nah an der Kamera, so schaust du automatisch in die Linse. Hilfslinien und Text sind nie in der Aufnahme.</p></div>
  <a href="<?= $h($self) ?>?view=videos" style="font-size:18px;color:#6B5658">← zu „Videos hochladen“</a></div>

<div class="pick">
  <label for="what">Was nimmst du auf?</label>
  <select id="what">
    <option value="0">✍️ Eigener Text (z. B. Story, Reel)</option>
    <?php $lastG = ''; foreach ($talks as $t): if ($t['g'] !== $lastG): if ($lastG !== '') echo '</optgroup>'; $lastG = $t['g']; ?><optgroup label="<?= $h($t['g']) ?>"><?php endif; ?>
    <option value="<?= $t['id'] ?>"<?= $t['id'] === $sel ? ' selected' : '' ?>><?= $h($t['t']) ?><?= $t['vde'] && $t['ven'] ? ' ✓' : '' ?></option>
    <?php endforeach; if ($lastG !== '') echo '</optgroup>'; ?>
  </select>
  <div class="seg" role="group" aria-label="Sprache"><button type="button" data-lang="de" aria-pressed="<?= $lang === 'de' ? 'true' : 'false' ?>">Deutsch</button><button type="button" data-lang="en" aria-pressed="<?= $lang === 'en' ? 'true' : 'false' ?>">Englisch</button></div>
  <span class="done-l" id="doneL"></span>
</div>

<div class="grid">
  <div>
    <div id="stage" class="tc-stage" style="aspect-ratio:16/9"><div class="tc-st">Kamera startet … Bitte erlaube Kamera und Mikrofon.</div></div>
    <video id="play" controls playsinline hidden></video>
    <div class="bar">
      <button type="button" class="go" id="go">● Aufnahme starten</button>
      <button type="button" class="stop" id="stop" hidden>■ Stopp</button>
      <button type="button" class="up" id="up" hidden>⬆ In die Lektion hochladen</button>
      <a class="b" id="dl" hidden download>⬇ Herunterladen</a>
      <button type="button" class="sm" id="again" hidden>↺ Nochmal aufnehmen</button>
    </div>
    <div class="prog" id="prog" hidden><i></i></div>
    <p class="msg" id="msg"></p>
  </div>
  <div class="side">
    <div class="box"><h2>Teleprompter</h2>
      <div class="own" id="own" hidden><textarea id="ownT" placeholder="Hier deinen Text einfügen, z. B. die Story von heute."></textarea></div>
      <div class="prc">
        <button type="button" id="prOn" aria-pressed="true">Text an</button>
        <button type="button" data-sp="-1" aria-label="langsamer">langsamer</button><button type="button" data-sp="1" aria-label="schneller">schneller</button>
        <button type="button" data-fs="-3" aria-label="Schrift kleiner">A−</button><button type="button" data-fs="3" aria-label="Schrift größer">A+</button>
        <button type="button" id="prTop" aria-label="zum Anfang">⟲ Anfang</button>
      </div>
      <label class="chk"><input type="checkbox" id="prAuto" checked> Text läuft mit der Aufnahme los</label>
      <p class="tip" style="margin-top:8px">Tippen auf den Text oder Leertaste: Pause und weiter.</p>
    </div>
    <div class="box"><h2>✨ Kamera-Look</h2><div id="panel"></div></div>
    <div class="box"><h2>Kamera &amp; Mikrofon</h2>
      <div class="dev"><label>Kamera<select id="cam"></select></label><label>Mikrofon<select id="mic"></select></label></div>
      <p class="tip" style="margin-top:10px">Tipp: Licht von vorne (Fenster vor dir, nicht hinter dir), Kamera auf Augenhöhe, Handy quer für Videos und hoch für Storys.</p>
    </div>
  </div>
</div>

<script src="/wildhearts/assets/talkcam.js?v=1"></script>
<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
<script>
(function(){
  const TALKS=<?= json_encode(array_values($talks), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const csrf=<?= json_encode(csrf()) ?>, BUNNY=<?= $bunny ? 'true' : 'false' ?>;
  const $=id=>document.getElementById(id), esc=t=>String(t).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  function post(a,d){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
  let lang=<?= json_encode($lang) ?>, cur=null, tgt=null, tc=null, raw=null, rec=null, chunks=[], blob=null, saved=true, t0=0, tick=0, lock=null;
  const ls={get:(k,d)=>{try{const v=localStorage.getItem(k);return v===null?d:v}catch(e){return d}},set:(k,v)=>{try{localStorage.setItem(k,v)}catch(e){}}};

  // ---- Teleprompter ----
  const pr=document.createElement('div');pr.className='pr';pr.innerHTML='<div class="pr-t" id="prT"></div>';
  let sp=+ls.get('st-sp',40), fs=+ls.get('st-fs',innerWidth<700?20:30), run=false, last=0;
  pr.style.setProperty('--fs',fs+'px');
  function textHtml(t){return esc(t||'').replace(/\[([^\]]+)\]/g,'<span class="hint">[$1]</span>')}
  function setText(){const t=cur?(lang==='en'?cur.en:cur.de):$('ownT').value;
    pr.querySelector('.pr-t').innerHTML=t.trim()?textHtml(t):'<span class="hint">'+(cur?'Für diese Lektion gibt es noch keinen '+(lang==='en'?'englischen':'deutschen')+' Text.':'Füge rechts deinen Text ein.')+'</span>';pr.scrollTop=0;}
  function prLoop(ts){if(run){pr.scrollTop+=sp*(ts-last)/1000}last=ts;requestAnimationFrame(prLoop)}requestAnimationFrame(prLoop);
  function toggle(){run=!run}
  pr.addEventListener('click',toggle);
  document.addEventListener('keydown',e=>{if(e.code==='Space'&&!/INPUT|TEXTAREA|SELECT|BUTTON/.test(document.activeElement.tagName)){e.preventDefault();toggle()}});
  document.querySelectorAll('[data-sp]').forEach(b=>b.onclick=()=>{sp=Math.max(10,Math.min(160,sp+(+b.dataset.sp)*8));ls.set('st-sp',sp)});
  document.querySelectorAll('[data-fs]').forEach(b=>b.onclick=()=>{fs=Math.max(18,Math.min(60,fs+(+b.dataset.fs)));pr.style.setProperty('--fs',fs+'px');ls.set('st-fs',fs)});
  $('prTop').onclick=()=>{pr.scrollTop=0;run=false};
  $('prOn').onclick=()=>{pr.hidden=!pr.hidden;$('prOn').setAttribute('aria-pressed',String(!pr.hidden));$('prOn').textContent=pr.hidden?'Text aus':'Text an'};
  $('ownT').value=ls.get('st-own','');$('ownT').addEventListener('input',()=>{ls.set('st-own',$('ownT').value);setText()});

  // ---- Auswahl ----
  function choose(){const id=+$('what').value;cur=TALKS.find(t=>t.id===id)||null;$('own').hidden=!!cur;
    document.querySelectorAll('[data-lang]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.lang===lang)));
    $('doneL').textContent=cur?((lang==='de'?cur.vde:cur.ven)?'✓ '+(lang==='de'?'Deutsch':'Englisch')+' ist schon hochgeladen (eine neue Aufnahme ersetzt es)':''):'';
    const u=new URL(location.href);if(cur)u.searchParams.set('l',cur.id);else u.searchParams.delete('l');u.searchParams.set('lang',lang);history.replaceState(null,'',u);
    setText();showAfter();}
  $('what').onchange=choose;
  document.querySelectorAll('[data-lang]').forEach(b=>b.onclick=()=>{lang=b.dataset.lang;choose()});

  // ---- Kamera ----
  async function startCam(){
    const cid=ls.get('st-cam',''), mid=ls.get('st-mic','');
    if(raw)raw.getTracks().forEach(t=>t.stop());if(tc)tc.destroy(false);
    const v={width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}};if(cid)v.deviceId={exact:cid};
    const a={echoCancellation:true,noiseSuppression:true,autoGainControl:true};if(mid)a.deviceId={exact:mid};
    try{raw=await navigator.mediaDevices.getUserMedia({video:v,audio:a})}
    catch(e){try{raw=await navigator.mediaDevices.getUserMedia({video:true,audio:true});ls.set('st-cam','');ls.set('st-mic','')}catch(e2){$('stage').innerHTML='<div class="tc-st">Kamera oder Mikrofon sind gesperrt. Bitte im Browser oben bei der Adresse erlauben und die Seite neu laden.</div>';return}}
    tc=await TalkCam.create(raw,{mount:$('stage')});
    $('stage').appendChild(pr);$('stage').appendChild(Object.assign(document.createElement('div'),{className:'cd',id:'cd'}));
    const rd=document.createElement('div');rd.className='recdot';rd.id='rd';rd.hidden=true;rd.innerHTML='<i></i><span id="rt">0:00</span>';$('stage').appendChild(rd);
    TalkCam.panel(tc,$('panel'));
    devices();
  }
  async function devices(){try{const ds=await navigator.mediaDevices.enumerateDevices();
    const fill=(sel,kind,key,label)=>{const curId=(raw.getTracks().find(t=>t.kind===kind.replace('input',''))||{getSettings:()=>({})}).getSettings().deviceId;
      sel.innerHTML=ds.filter(d=>d.kind===kind).map((d,i)=>'<option value="'+esc(d.deviceId)+'"'+(d.deviceId===curId?' selected':'')+'>'+esc(d.label||label+' '+(i+1))+'</option>').join('');
      sel.onchange=()=>{ls.set(key,sel.value);startCam()}};
    fill($('cam'),'videoinput','st-cam','Kamera');fill($('mic'),'audioinput','st-mic','Mikrofon');}catch(e){}}

  // ---- Aufnahme ----
  const fmt=s=>Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
  function mime(){const ts=['video/mp4;codecs=avc1.640028,mp4a.40.2','video/mp4;codecs=avc1,mp4a','video/mp4','video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'];return ts.find(t=>window.MediaRecorder&&MediaRecorder.isTypeSupported(t))||''}
  function showAfter(){const has=!!blob;if(tgt)$('up').textContent='⬆ In „'+tgt.t.t+'“ hochladen ('+(tgt.lang==='de'?'Deutsch':'Englisch')+')';$('go').hidden=has||(rec&&rec.state==='recording');$('again').hidden=!has;$('dl').hidden=!has;$('up').hidden=!has||!tgt||!BUNNY;
    $('play').hidden=!has;$('stage').style.display=has?'none':'';}
  $('go').onclick=async()=>{
    if(!tc){$('msg').textContent='Die Kamera läuft noch nicht.';return}
    if(!window.MediaRecorder){$('msg').textContent='Dieser Browser kann nicht aufnehmen. Bitte Chrome oder Safari nehmen.';return}
    $('go').hidden=true;$('msg').textContent='';
    for(const n of [3,2,1]){$('cd').textContent=n;await new Promise(r=>setTimeout(r,800))}$('cd').textContent='';
    const mt=mime(),big=tc.canvas.width*tc.canvas.height>1.5e6;
    rec=new MediaRecorder(tc.stream(30),Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:big?8000000:5000000,audioBitsPerSecond:160000}));
    chunks=[];rec.ondataavailable=e=>{if(e.data&&e.data.size)chunks.push(e.data)};
    rec.onstop=()=>{blob=new Blob(chunks,{type:(rec.mimeType||mt||'video/webm').split(';')[0]});saved=false;
      const url=URL.createObjectURL(blob);$('play').src=url;const ext=blob.type.includes('mp4')?'mp4':'webm';
      const d=new Date(),stamp=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+'_'+String(d.getHours()).padStart(2,'0')+'-'+String(d.getMinutes()).padStart(2,'0');
      $('dl').href=url;$('dl').download=('TalkingHead_'+(tgt?tgt.t.t:'Eigener-Text')+'_'+(tgt?tgt.lang:lang).toUpperCase()+'_'+stamp).replace(/[^\wäöüÄÖÜß-]+/g,'-')+'.'+ext;
      $('rd').hidden=true;$('stop').hidden=true;run=false;showAfter();
      $('msg').className='msg';$('msg').textContent='Schau es dir kurz an. Passt es, '+(tgt&&BUNNY?'tippe auf „Hochladen“ oder lade es herunter.':'lade es herunter.');
      if(lock){lock.release().catch(()=>{});lock=null}};
    tgt=cur?{t:cur,lang:lang}:null;rec.start(1000);t0=performance.now();$('stop').hidden=false;$('rd').hidden=false;
    if($('prAuto').checked&&!pr.hidden){run=true}
    try{lock=await navigator.wakeLock.request('screen')}catch(e){}
    (function loop(){if(!rec||rec.state!=='recording')return;$('rt').textContent=fmt((performance.now()-t0)/1000);tick=requestAnimationFrame(loop)})();
  };
  $('stop').onclick=()=>{if(rec&&rec.state==='recording')rec.stop()};
  $('again').onclick=()=>{if(!saved&&!confirm('Die Aufnahme ist noch nicht gespeichert. Trotzdem neu aufnehmen?'))return;blob=null;saved=true;$('play').removeAttribute('src');$('msg').textContent='';$('prog').hidden=true;pr.scrollTop=0;showAfter();};
  $('dl').addEventListener('click',()=>{saved=true});
  window.addEventListener('beforeunload',e=>{if(blob&&!saved){e.preventDefault();e.returnValue=''}});
  $('up').onclick=()=>{
    if(!blob||!tgt)return;if(typeof tus==='undefined'){$('msg').textContent='Der Upload konnte nicht laden. Bitte Seite neu laden (vorher herunterladen!).';return}
    $('up').disabled=true;$('msg').className='msg';$('msg').textContent='Wird vorbereitet …';const T=tgt.t,id=T.id,lg=tgt.lang,fill=$('prog').querySelector('i');
    post('mm_mv_video_new',{id:id,lang:lg}).then(j=>{if(j.error){$('msg').textContent=j.error;$('up').disabled=false;return}
      $('prog').hidden=false;const up=new tus.Upload(blob,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
        headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},metadata:{filetype:blob.type,title:$('dl').download},
        onError:()=>{$('msg').textContent='Upload unterbrochen. Tipp nochmal auf „Hochladen“ (oder lade es herunter).';$('up').disabled=false},
        onProgress:(a,t)=>{const p=Math.round(a/t*100);fill.style.width=p+'%';$('msg').textContent='Lädt hoch … '+p+' % (Seite offen lassen)'},
        onSuccess:()=>{post('mm_mv_video_set',{id:id,lang:lg,guid:j.guid}).then(()=>{saved=true;$('up').disabled=false;$('up').hidden=true;
          if(lg==='de')T.vde=true;else T.ven=true;
          $('msg').className='msg ok';$('msg').textContent='✓ Hochgeladen. Bunny wandelt es in ein paar Minuten um.'+((T.vde&&T.ven)?' Beide Sprachen sind da, das Video ist fertig.':' Jetzt noch '+(lg==='de'?'Englisch':'Deutsch')+'?');})}});
      up.start()}).catch(()=>{$('msg').textContent='Das hat nicht geklappt. Bitte herunterladen und später hochladen.';$('up').disabled=false});
  };

  choose();startCam();
})();
</script>
<?php
    });
    exit;
}

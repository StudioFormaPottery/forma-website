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
.view{position:sticky;top:72px;z-index:6;align-self:start;background:#F2ECE4;padding-bottom:6px}
@media(max-width:980px){.grid{grid-template-columns:1fr}.view{top:60px;margin:0 -12px;padding:6px 12px 8px;box-shadow:0 10px 18px -14px rgba(46,37,41,.45)}
 .view .bar{margin-top:8px}.view .bar button,.view .bar a.b{min-height:48px;font-size:17px;padding:10px 18px}.view .msg{font-size:15px;margin-top:6px}}
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
.slab{position:absolute;left:14px;bottom:14px;z-index:4;padding:6px 14px;border-radius:999px;background:rgba(251,245,247,.92);color:#4A3A41;font:500 16px/1.2 "Hanken Grotesk","Outfit",system-ui,sans-serif}
.slab[hidden]{display:none}

body.clipm .pr-t{padding:14px 6%;width:100%}
.bar .next{background:#7D5E68;border-color:#7D5E68;color:#fff;min-width:min(100%,320px)}
.clips{display:flex;flex-direction:column;gap:12px}
.clip{display:grid;grid-template-columns:120px minmax(0,1fr);gap:12px;align-items:start;background:#fff;border:1.5px solid #DDD2C6;border-radius:18px;padding:10px}
.clip video{width:120px;max-height:160px;border-radius:12px;background:#2E2529;object-fit:cover}
.clip b{font-size:16px;font-weight:500;color:#4A3A41}.clip p{margin:2px 0 8px;font-size:15px;line-height:1.4;color:#6B5658;max-height:4.2em;overflow:hidden}
.clip .cb{display:flex;flex-wrap:wrap;gap:6px}
.clip .cb a,.clip .cb button,.cl-all button,.cl-all a{font:inherit;font-size:15px;padding:8px 12px;border-radius:999px;border:1.5px solid #DDD2C6;background:#FBF8F4;color:#4A3A41;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.clip .cb .sub{border-color:#7D5E68;color:#7D5E68}
.cl-all{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.cl-all .main{background:#7D5E68;border-color:#7D5E68;color:#fff}
.clip.miss{opacity:.6}
.subs .row2{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
.subs .lab{font-size:15px;color:#7D7076;min-width:70px}
.subs .chips{display:flex;flex-wrap:wrap;gap:6px}.subs .chips button{font:inherit;font-size:16px;padding:8px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:42px}
.subs .chips button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.subprev{margin-top:8px;border-radius:14px;overflow:hidden;background:#2E2529}
.subprev canvas{display:block;width:100%}
[hidden]{display:none!important}
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
  <div class="seg" role="group" aria-label="Aufnahme-Art"><button type="button" data-mode="ganz">Am Stück</button><button type="button" data-mode="clips">Absatz für Absatz</button></div>
  <span class="done-l" id="doneL"></span>
</div>

<div class="grid">
  <div class="view">
    <div id="stage" class="tc-stage" style="aspect-ratio:16/9"><div class="tc-st">Kamera startet … Bitte erlaube Kamera und Mikrofon.</div></div>
    <video id="play" controls playsinline hidden></video>
    <div class="bar">
      <button type="button" class="go" id="go">● Aufnahme starten</button>
      <button type="button" class="stop" id="stop" hidden>■ Stopp</button>
      <button type="button" class="up" id="up" hidden>⬆ In die Lektion hochladen</button>
      <a class="b" id="dl" hidden download>⬇ Herunterladen</a>
      <button type="button" class="sm" id="again" hidden>↺ Nochmal aufnehmen</button>
      <button type="button" class="go" id="cgo" hidden>● Absatz 1 aufnehmen</button>
      <button type="button" class="next" id="cnext" hidden>✓ Fertig · nächster Absatz</button>
      <button type="button" class="sm" id="credo" hidden>↺ Absatz nochmal</button>
      <button type="button" class="sm" id="cend" hidden>■ Beenden</button>
    </div>
    <div class="prog" id="prog" hidden><i></i></div>
    <p class="msg" id="msg"></p>
  </div>
  <div class="side">
    <div class="box" id="clipBox" hidden><h2>🎬 Deine Clips</h2>
      <p class="tip" id="clipInfo"></p>
      <div class="clips" id="clips"></div>
      <div class="cl-all" id="clAll" hidden><button type="button" class="main" id="shareAll" hidden>📲 Alle in Fotos sichern</button><button type="button" id="zipAll">⬇ Alle als ZIP</button><button type="button" id="zipSub" hidden>⬇ Alle mit Untertitel (ZIP)</button></div>
    </div>
    <div class="box subs" id="subBox" hidden><h2>💬 Untertitel</h2>
      <p class="tip">Für Social Media: Der Text wird passend zu deiner Stimme ins Video eingebrannt. Die Clips ohne Untertitel bleiben daneben erhalten.</p>
      <div class="row2"><span class="lab">Sprache</span><div class="chips" id="subLang"><button type="button" data-sl="de">Deutsch</button><button type="button" data-sl="en">Englisch</button></div></div>
      <div class="row2"><span class="lab">Stil</span><div class="chips" id="subStyle"><button type="button" data-ss="weiss">Weiß</button><button type="button" data-ss="band">Pflaume</button><button type="button" data-ss="hell">Hell</button></div></div>
      <div class="row2"><span class="lab">Position</span><div class="chips" id="subPos"><button type="button" data-sp2="unten">Unten</button><button type="button" data-sp2="mitte">Mitte</button></div></div>
      <div class="subprev"><canvas id="subPrev" width="640" height="360"></canvas></div>
      <p class="tip" id="subHint" style="margin-top:8px"></p>
      <div class="cl-all"><button type="button" class="main" id="subAll">💬 Untertitel für alle Clips erstellen</button></div>
      <div class="prog" id="subProg" hidden><i></i></div>
    </div>
    <div class="box"><h2>Teleprompter</h2>
      <div class="own" id="own" hidden><textarea id="ownT" placeholder="Hier deinen Text einfügen, z. B. die Story von heute."></textarea>
        <p class="tip" id="ownHint" style="margin:6px 0 10px">Eine Leerzeile trennt die Absätze. Bei „Absatz für Absatz“ wird jeder Absatz ein eigener Clip. Oben bei Deutsch/Englisch kannst du den Text in beiden Sprachen hinterlegen (für Untertitel in der anderen Sprache).</p></div>
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

<script src="/wildhearts/assets/talkcam.js?v=2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
<script>
(function(){
  const TALKS=<?= json_encode(array_values($talks), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const csrf=<?= json_encode(csrf()) ?>, BUNNY=<?= $bunny ? 'true' : 'false' ?>;
  const $=id=>document.getElementById(id), esc=t=>String(t).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  function post(a,d){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
  let clipm=false, cs=null, clips=[];
  let lang=<?= json_encode($lang) ?>, cur=null, tgt=null, tc=null, raw=null, rec=null, chunks=[], blob=null, saved=true, t0=0, tick=0, lock=null;
  const ls={get:(k,d)=>{try{const v=localStorage.getItem(k);return v===null?d:v}catch(e){return d}},set:(k,v)=>{try{localStorage.setItem(k,v)}catch(e){}}};

  // ---- Teleprompter ----
  const pr=document.createElement('div');pr.className='pr';pr.innerHTML='<div class="pr-t" id="prT"></div>';
  const slab=document.createElement('div');slab.className='slab';slab.hidden=true;
  let sp=+ls.get('st-sp',40), fs=+ls.get('st-fs',innerWidth<700?20:30), run=false, last=0;
  pr.style.setProperty('--fs',fs+'px');
  function textHtml(t){return esc(t||'').replace(/\[([^\]]+)\]/g,'<span class="hint">[$1]</span>')}
  function textOf(lg){return cur?(lg==='en'?cur.en:cur.de):ownOf(lg)}
  function paras(lg){return String(textOf(lg)||'').split(/\n\s*\n/).map(x=>x.trim()).filter(Boolean)}
  function setText(){if(clipm){showSlide();return}const t=cur?(lang==='en'?cur.en:cur.de):$('ownT').value;
    pr.querySelector('.pr-t').innerHTML=t.trim()?textHtml(t):'<span class="hint">'+(cur?'Für diese Lektion gibt es noch keinen '+(lang==='en'?'englischen':'deutschen')+' Text.':'Füge deinen Text beim Teleprompter ein.')+'</span>';pr.scrollTop=0;}
  function prLoop(ts){if(run){pr.scrollTop+=sp*(ts-last)/1000}last=ts;requestAnimationFrame(prLoop)}requestAnimationFrame(prLoop);
  function toggle(){run=!run}
  pr.addEventListener('click',toggle);
  document.addEventListener('keydown',e=>{if(e.code==='Space'&&!/INPUT|TEXTAREA|SELECT|BUTTON/.test(document.activeElement.tagName)){e.preventDefault();toggle()}});
  document.querySelectorAll('[data-sp]').forEach(b=>b.onclick=()=>{sp=Math.max(10,Math.min(160,sp+(+b.dataset.sp)*8));ls.set('st-sp',sp)});
  document.querySelectorAll('[data-fs]').forEach(b=>b.onclick=()=>{fs=Math.max(18,Math.min(60,fs+(+b.dataset.fs)));pr.style.setProperty('--fs',fs+'px');ls.set('st-fs',fs)});
  $('prTop').onclick=()=>{pr.scrollTop=0;run=false};
  $('prOn').onclick=()=>{pr.hidden=!pr.hidden;$('prOn').setAttribute('aria-pressed',String(!pr.hidden));$('prOn').textContent=pr.hidden?'Text aus':'Text an'};
  // eigener Text je Sprache (für Untertitel in der anderen Sprache)
  if(ls.get('st-own-de',null)===null&&ls.get('st-own',''))ls.set('st-own-de',ls.get('st-own',''));
  const ownOf=lg=>ls.get('st-own-'+lg,'');
  $('ownT').addEventListener('input',()=>{ls.set('st-own-'+lang,$('ownT').value);setText();subPreview()});

  // ---- Auswahl ----
  function choose(){const id=+$('what').value;cur=TALKS.find(t=>t.id===id)||null;$('own').hidden=!!cur;$('ownT').value=ownOf(lang);
    document.querySelectorAll('[data-lang]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.lang===lang)));
    $('doneL').textContent=cur?((lang==='de'?cur.vde:cur.ven)?'✓ '+(lang==='de'?'Deutsch':'Englisch')+' ist schon hochgeladen (eine neue Aufnahme ersetzt es)':''):'';
    const u=new URL(location.href);if(cur)u.searchParams.set('l',cur.id);else u.searchParams.delete('l');u.searchParams.set('lang',lang);history.replaceState(null,'',u);
    setText();showAfter();clipUi();subPreview();}
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
    tc=await TalkCam.create(raw,{mount:$('stage'),maxVh:vh()});
    $('stage').appendChild(pr);$('stage').appendChild(Object.assign(document.createElement('div'),{className:'cd',id:'cd'}));
    const rd=document.createElement('div');rd.className='recdot';rd.id='rd';rd.hidden=true;rd.innerHTML='<i></i><span id="rt">0:00</span>';$('stage').appendChild(rd);
    $('stage').appendChild(slab);
    tc.on(t=>{if(t==='layout')subPreview()});
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
  function showAfter(){if(clipm){clipUi();return}const has=!!blob;if(tgt)$('up').textContent='⬆ In „'+tgt.t.t+'“ hochladen ('+(tgt.lang==='de'?'Deutsch':'Englisch')+')';$('go').hidden=has||(rec&&rec.state==='recording');$('again').hidden=!has;$('dl').hidden=!has;$('up').hidden=!has||!tgt||!BUNNY;
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

  // Vorschau bleibt beim Einstellen immer sichtbar (oben fixiert), am Handy kleiner
  function vh(){return matchMedia('(max-width:980px)').matches?36:66}
  addEventListener('resize',()=>{if(tc){tc.opts.maxVh=vh();tc.emit('layout')}});
  $('play').style.maxHeight=vh()+'vh';
  // =================== Absatz für Absatz ===================
  // Text an Leerzeilen in Absätze teilen, je Absatz ein kurzer Clip. Die Stimme wird mitgemessen
  // (wann du anfängst und aufhörst zu sprechen), damit die Untertitel später genau passen.
  clipm=ls.get('st-mode','ganz')==='clips';
  function setMode(m){clipm=m==='clips';ls.set('st-mode',m);document.querySelectorAll('[data-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.mode===m)));
    document.body.classList.toggle('clipm',clipm);if(!clipm){cs=null;slab.hidden=true}blob=null;showAfter();clipUi();setText();}
  document.querySelectorAll('[data-mode]').forEach(b=>b.onclick=()=>{if(cs&&cs.rec){return}if(clips.some(c=>c&&!c.saved)&&!confirm('Deine Clips sind noch nicht gesichert. Trotzdem wechseln?'))return;clips=[];setMode(b.dataset.mode)});
  const stamp=()=>{const d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+'_'+String(d.getHours()).padStart(2,'0')+'-'+String(d.getMinutes()).padStart(2,'0')};
  const slug=t=>String(t).normalize('NFKD').replace(/[̀-ͯ]/g,'').replace(/ß/g,'ss').replace(/[^A-Za-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,40)||'Clip';
  let baseName='';
  function showSlide(){const P=paras(lang),i=cs?cs.i:0,t=P[i]||'';
    pr.querySelector('.pr-t').innerHTML=t?textHtml(t):'<span class="hint">'+(P.length?'Fertig ✓':'Füge deinen Text beim Teleprompter ein. Eine Leerzeile trennt die Absätze.')+'</span>';pr.scrollTop=0;
    slab.hidden=!P.length;slab.textContent='Absatz '+Math.min(i+1,P.length)+' von '+P.length;}
  function clipUi(){
    const P=paras(lang),n=P.length,recIng=!!(cs&&cs.rec);
    ['go','stop','up','dl','again'].forEach(id=>{if(clipm)$(id).hidden=true});if(clipm){$('play').hidden=true;$('stage').style.display=''}
    $('cgo').hidden=!clipm||recIng;$('cnext').hidden=!clipm||!recIng;$('credo').hidden=!clipm||!recIng;$('cend').hidden=!clipm||!recIng;
    if(clipm&&!recIng){const first=clips.findIndex((c,i)=>i<n&&!c);const start=clips.length&&first<0&&clips.filter(Boolean).length>=n?-1:(first<0?Math.min(clips.length,n):first);
      $('cgo').dataset.start=Math.max(0,start);$('cgo').textContent=n?(start<0?'● Alles nochmal ab Absatz 1':'● Absatz '+(start+1)+' von '+n+' aufnehmen'):'● Aufnehmen';if(start<0)$('cgo').dataset.start=0;}
    if(clipm&&recIng)$('cnext').textContent=cs.i+1<n?'✓ Fertig · Absatz '+(cs.i+2)+' ›':'✓ Fertig · letzter Absatz';
    $('clipBox').hidden=!clipm;$('subBox').hidden=!clipm||!clips.some(Boolean);
    $('clipInfo').textContent=clipm?(n?n+' Absätze im Text. Du liest einen Absatz, tippst auf „Fertig“ und der nächste startet nach 3-2-1 von selbst. Leertaste oder → geht auch.':'Noch kein Text. Füge ihn beim Teleprompter ein, eine Leerzeile trennt die Absätze.'):'';
    renderClips();}
  // Stimme messen
  let ac=null,an=null,abuf=null;
  function meter(){try{if(!ac){ac=new (window.AudioContext||window.webkitAudioContext)();}ac.resume();if(!an&&raw&&raw.getAudioTracks().length){const src=ac.createMediaStreamSource(new MediaStream(raw.getAudioTracks()));an=ac.createAnalyser();an.fftSize=1024;src.connect(an);abuf=new Float32Array(an.fftSize)}}catch(e){an=null}}
  async function startClip(i,single){
    const P=paras(lang);if(!P.length){$('msg').textContent='Füge zuerst deinen Text ein.';return}
    if(!tc){$('msg').textContent='Die Kamera läuft noch nicht.';return}
    if(!baseName)baseName=slug(cur?cur.t:'Story')+'_'+lang.toUpperCase()+'_'+stamp();
    meter();cs={i:i,single:!!single,rec:null,discard:false};setText();clipUi();
    $('cgo').hidden=true;$('msg').textContent='';
    for(const n of [3,2,1]){$('cd').textContent=n;await new Promise(r=>setTimeout(r,650))}$('cd').textContent='';
    if(!cs)return;
    const mt=mime(),big=tc.canvas.width*tc.canvas.height>1.5e6,ch=[];
    const r=new MediaRecorder(tc.stream(30),Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:big?8000000:5000000,audioBitsPerSecond:160000}));
    const st={t0:0,s:null,e:null};
    r.ondataavailable=e=>{if(e.data&&e.data.size)ch.push(e.data)};
    r.onstop=()=>{const dur=(performance.now()-st.t0)/1000;const me=r._cs;r.stream.getVideoTracks().forEach(t=>t.stop());
      if(me.discard)return;
      const type=(r.mimeType||mt||'video/webm').split(';')[0],b=new Blob(ch,{type}),ext=type.includes('mp4')?'mp4':'webm';
      const old=clips[me.i];if(old){URL.revokeObjectURL(old.url);if(old.sub)URL.revokeObjectURL(old.sub.url)}
      clips[me.i]={i:me.i,blob:b,url:URL.createObjectURL(b),ext:ext,dur:dur,s:st.s==null?.2:Math.max(0,st.s-.15),e:st.e==null?dur-.2:Math.min(dur,st.e+.25),lang:lang,text:paras(lang)[me.i]||'',sub:null,saved:false};
      renderClips();clipUi();};
    r._cs=cs;cs.rec=r;r.start(1000);st.t0=performance.now();$('rd').hidden=false;clipUi();
    try{if(!lock)lock=await navigator.wakeLock.request('screen')}catch(e){}
    (function loop(){if(!cs||cs.rec!==r||r.state!=='recording')return;const t=(performance.now()-st.t0)/1000;$('rt').textContent=fmt(t);
      if(an){an.getFloatTimeDomainData(abuf);let q=0;for(const v of abuf)q+=v*v;const rms=Math.sqrt(q/abuf.length);if(rms>.02){if(st.s==null)st.s=t;st.e=t}}
      // langer Absatz: Text läuft sanft mit
      if(pr.scrollHeight>pr.clientHeight+4&&!pr.hidden)pr.scrollTop+=sp/60;
      requestAnimationFrame(loop)})();
  }
  function stopRec(discard){if(cs&&cs.rec&&cs.rec.state==='recording'){cs.rec._cs.discard=!!discard;cs.rec.stop()}$('rd').hidden=true}
  function finish(){stopRec(false);cs=null;slab.hidden=false;showSlide();slab.textContent='Fertig ✓';if(lock){lock.release().catch(()=>{});lock=null}clipUi();
    const n=clips.filter(Boolean).length;$('msg').className='msg ok';$('msg').textContent=n?'✓ '+n+' Clip'+(n>1?'s':'')+' fertig. Rechts kannst du sie ansehen, neu aufnehmen, sichern oder Untertitel dazu machen.':'';}
  $('cgo').onclick=()=>startClip(+($('cgo').dataset.start||0));
  $('cnext').onclick=()=>{if(!cs)return;const i=cs.i,single=cs.single;stopRec(false);const n=paras(lang).length;
    if(single||i+1>=n){finish();return}startClip(i+1)};
  $('credo').onclick=()=>{if(!cs)return;const i=cs.i,single=cs.single;stopRec(true);startClip(i,single)};
  $('cend').onclick=()=>finish();
  document.addEventListener('keydown',e=>{if(!clipm||!cs||!cs.rec)return;if(/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName))return;
    if(e.code==='Space'||e.code==='ArrowRight'||e.code==='Enter'){e.preventDefault();e.stopImmediatePropagation();$('cnext').click()}},true);
  function fileName(c,sub){return baseName+'_'+String(c.i+1).padStart(2,'0')+(sub?'_Untertitel-'+sub.toUpperCase():'')+'.'+c.ext}
  function renderClips(){const box=$('clips');if(!clipm){box.innerHTML='';return}const P=paras(lang),n=Math.max(P.length,clips.length);let h='';
    for(let i=0;i<n;i++){const c=clips[i];
      h+='<div class="clip'+(c?'':' miss')+'">'+(c?'<video src="'+c.url+'#t=0.1" controls playsinline preload="metadata"></video>':'<div style="width:120px;height:90px;border-radius:12px;background:#EADFDE;display:flex;align-items:center;justify-content:center;color:#7D7076;font-size:14px">noch nicht</div>')
        +'<div><b>Absatz '+(i+1)+(c?' · '+fmt(c.dur):'')+'</b><p>'+esc((c?c.text:P[i])||'')+'</p><div class="cb">'
        +(c?'<a href="'+c.url+'" download="'+esc(fileName(c))+'" data-saved="'+i+'">⬇ Laden</a>':'')
        +(c&&c.sub?'<a class="sub" href="'+c.sub.url+'" download="'+esc(fileName(c,c.sub.lang))+'" data-saved="'+i+'">⬇ mit Untertitel</a>':'')
        +(c&&canShare([c.blob])?'<button type="button" data-share="'+i+'">📲 Sichern</button>':'')
        +'<button type="button" data-retake="'+i+'">'+(c?'↺ Neu aufnehmen':'● Aufnehmen')+'</button></div></div></div>';}
    box.innerHTML=h;const any=clips.some(Boolean);$('clAll').hidden=!any;$('shareAll').hidden=!any||!canShare(clips.filter(Boolean).map(c=>c.blob));$('zipSub').hidden=!clips.some(c=>c&&c.sub);
    box.querySelectorAll('[data-retake]').forEach(b=>b.onclick=()=>{if(cs&&cs.rec)return;startClip(+b.dataset.retake,true);window.scrollTo({top:0,behavior:'smooth'})});
    box.querySelectorAll('[data-saved]').forEach(a=>a.addEventListener('click',()=>{const c=clips[+a.dataset.saved];if(c)c.saved=true}));
    box.querySelectorAll('[data-share]').forEach(b=>b.onclick=()=>shareFiles([clips[+b.dataset.share]]));}
  function asFile(c,sub){const b=sub?c.sub.blob:c.blob;return new File([b],fileName(c,sub?c.sub.lang:''),{type:b.type})}
  function canShare(blobs){try{return !!(navigator.canShare&&navigator.canShare({files:blobs.map((b,i)=>new File([b],'c'+i+'.mp4',{type:b.type}))}))}catch(e){return false}}
  async function shareFiles(list){const files=list.map(c=>asFile(c,!!c.sub));try{await navigator.share({files:files});list.forEach(c=>c.saved=true)}catch(e){}}
  $('shareAll').onclick=()=>shareFiles(clips.filter(Boolean));
  async function zip(sub){if(typeof JSZip==='undefined'){$('msg').textContent='Der ZIP-Baustein konnte nicht laden. Lade die Clips bitte einzeln.';return}
    const z=new JSZip();clips.filter(Boolean).forEach(c=>{if(sub&&!c.sub)return;z.file(fileName(c,sub?c.sub.lang:''),sub?c.sub.blob:c.blob)});
    $('msg').className='msg';$('msg').textContent='ZIP wird gepackt …';const b=await z.generateAsync({type:'blob',compression:'STORE'});
    const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download=baseName+(sub?'_Untertitel':'')+'.zip';document.body.appendChild(a);a.click();a.remove();
    clips.forEach(c=>{if(c)c.saved=true});$('msg').textContent='✓ ZIP ist in deinen Downloads.';}
  $('zipAll').onclick=()=>zip(false);$('zipSub').onclick=()=>zip(true);
  window.addEventListener('beforeunload',e=>{if(clips.some(c=>c&&!c.saved)){e.preventDefault();e.returnValue=''}});

  // =================== Untertitel ===================
  let subLang=ls.get('st-sublang','')||lang, subStyle=ls.get('st-substyle','band'), subPos=ls.get('st-subpos','unten');
  const fontReady=(()=>{try{const f=new FontFace('Hanken Grotesk','url(/fonts/HankenGrotesk-VF.woff)',{weight:'100 900'});document.fonts.add(f);return f.load().catch(()=>{})}catch(e){return Promise.resolve()}})();
  function subChips(){document.querySelectorAll('[data-sl]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.sl===subLang)));
    document.querySelectorAll('[data-ss]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.ss===subStyle)));
    document.querySelectorAll('[data-sp2]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.sp2===subPos)));}
  document.querySelectorAll('[data-sl]').forEach(b=>b.onclick=()=>{subLang=b.dataset.sl;ls.set('st-sublang',subLang);subChips();subPreview()});
  document.querySelectorAll('[data-ss]').forEach(b=>b.onclick=()=>{subStyle=b.dataset.ss;ls.set('st-substyle',subStyle);subChips();subPreview()});
  document.querySelectorAll('[data-sp2]').forEach(b=>b.onclick=()=>{subPos=b.dataset.sp2;ls.set('st-subpos',subPos);subChips();subPreview()});
  const clean=t=>String(t||'').replace(/\[[^\]]*\]/g,' ').replace(/^\s*\d{1,2}:\d{2}\s*/gm,'').replace(/\s+/g,' ').trim();
  // Text in kurze Untertitel teilen und über die Sprechzeit verteilen
  function cues(text,s,e,maxC){const w=clean(text).split(' ').filter(Boolean),out=[];let cur=[];
    const push=()=>{if(cur.length)out.push(cur.join(' '));cur=[]};
    for(const x of w){const nx=cur.concat(x).join(' ');if(nx.length>maxC&&cur.length)push();cur.push(x);if(/[.!?…:;]$/.test(x)&&cur.join(' ').length>maxC*.45)push();}
    push();const wt=out.map(t=>t.length+6),tot=wt.reduce((a,b)=>a+b,0)||1;let t=s;
    return out.map((tx,i)=>{const d=(e-s)*wt[i]/tot,c={t:tx,a:t,b:t+d};t+=d;return c})}
  function wrap(g,t,maxW){const w=t.split(' '),L=[];let l='';for(const x of w){const n=l?l+' '+x:x;if(g.measureText(n).width>maxW&&l){L.push(l);l=x}else l=n}if(l)L.push(l);return L}
  function drawCue(g,W,H,text){if(!text)return;const port=H>W,fs=Math.round(Math.min(W,H)*(port?.064:.058));
    g.font='600 '+fs+'px "Hanken Grotesk","Outfit",system-ui,sans-serif';g.textAlign='center';g.textBaseline='middle';
    const L=wrap(g,text,W*(port?.8:.7)),lh=fs*1.28,cy=subPos==='mitte'?H*.62:H*(port?.74:.84),y0=cy-(L.length-1)*lh/2;
    L.forEach((l,i)=>{const y=y0+i*lh,tw=g.measureText(l).width;
      if(subStyle!=='weiss'){const px=fs*.45,py=fs*.24,r=fs*.38;g.fillStyle=subStyle==='band'?'rgba(74,58,65,.88)':'rgba(251,245,247,.94)';
        g.beginPath();if(g.roundRect)g.roundRect(W/2-tw/2-px,y-lh/2+py*.3,tw+px*2,lh-py*.1,r);else g.rect(W/2-tw/2-px,y-lh/2+py*.3,tw+px*2,lh-py*.1);g.fill()}
      if(subStyle==='weiss'){g.shadowColor='rgba(0,0,0,.6)';g.shadowBlur=fs*.35;g.lineWidth=fs*.08;g.strokeStyle='rgba(46,37,41,.55)';g.strokeText(l,W/2,y)}
      g.fillStyle=subStyle==='hell'?'#4A3A41':'#FFFFFF';g.fillText(l,W/2,y);g.shadowBlur=0;g.shadowColor='transparent';});}
  function subText(i){const P=paras(subLang);return P[i]||''}
  function subPreview(){const cv=$('subPrev');if(!cv||!tc)return;const W=tc.canvas.width||1280,H=tc.canvas.height||720,k=Math.min(1,640/Math.max(W,H));
    cv.width=Math.round(W*k);cv.height=Math.round(H*k);const g=cv.getContext('2d');g.fillStyle='#6B5658';g.fillRect(0,0,cv.width,cv.height);
    try{g.drawImage(tc.canvas,0,0,cv.width,cv.height)}catch(e){}
    const t=clean(subText(0)||(subLang==='en'?'This is how your subtitles will look.':'So sehen deine Untertitel aus.'));
    fontReady.then(()=>drawCue(g,cv.width,cv.height,cues(t,0,1,port()?24:38)[0].t));
    const a=paras(lang).length,b=paras(subLang).length;
    $('subHint').textContent=subLang!==lang&&a!==b?'Achtung: Der '+(subLang==='en'?'englische':'deutsche')+' Text hat '+b+' Absätze, aufgenommen sind '+a+'. Bitte die Absätze gleich aufteilen (oben bei '+(subLang==='en'?'Englisch':'Deutsch')+').':(subLang!==lang?'Die Untertitel nehmen den Absatz mit derselben Nummer aus dem '+(subLang==='en'?'englischen':'deutschen')+' Text.':'');}
  const port=()=>tc&&tc.canvas.height>tc.canvas.width;
  // Clip abspielen, Bild + Untertitel neu zeichnen und mit Ton aufnehmen (läuft in Echtzeit)
  async function burn(c,lang2){
    await fontReady;const text=subText(c.i);if(!clean(text))return false;
    const v=document.createElement('video');v.src=c.url;v.muted=true;v.playsInline=true;v.setAttribute('playsinline','');
    await new Promise((ok,no)=>{v.onloadedmetadata=ok;v.onerror=no});
    const W=v.videoWidth,H=v.videoHeight,cv=document.createElement('canvas');cv.width=W;cv.height=H;const g=cv.getContext('2d');
    const list=cues(text,c.s,Math.max(c.s+.5,c.e),H>W?24:38);
    ac=ac||new (window.AudioContext||window.webkitAudioContext)();await ac.resume();
    let buf=null;try{buf=await ac.decodeAudioData(await c.blob.arrayBuffer())}catch(e){buf=null}
    const dest=ac.createMediaStreamDestination(),out=cv.captureStream(30);if(buf)dest.stream.getAudioTracks().forEach(t=>out.addTrack(t));
    const mt=mime(),ch=[],r=new MediaRecorder(out,Object.assign(mt?{mimeType:mt}:{},{videoBitsPerSecond:W*H>1.5e6?8000000:5000000,audioBitsPerSecond:160000}));
    r.ondataavailable=e=>{if(e.data&&e.data.size)ch.push(e.data)};
    const done=new Promise(ok=>r.onstop=ok);
    const draw=()=>{g.drawImage(v,0,0,W,H);const t=v.currentTime,q=list.find(x=>t>=x.a&&t<x.b);if(q)drawCue(g,W,H,q.t)};
    draw();r.start(500);
    let src=null;if(buf){src=ac.createBufferSource();src.buffer=buf;src.connect(dest)}
    await v.play();if(src)src.start();
    await new Promise(ok=>{const step=()=>{draw();if(v.ended||v.currentTime>=c.dur-.03){ok();return}if(v.requestVideoFrameCallback)v.requestVideoFrameCallback(step);else requestAnimationFrame(step)};step();v.onended=ok});
    draw();await new Promise(r2=>setTimeout(r2,120));r.stop();await done;try{if(src)src.stop()}catch(e){}
    const type=(r.mimeType||mt||'video/webm').split(';')[0],b=new Blob(ch,{type});
    if(c.sub)URL.revokeObjectURL(c.sub.url);c.sub={blob:b,url:URL.createObjectURL(b),lang:lang2};return true;}
  $('subAll').onclick=async()=>{const list=clips.filter(Boolean);if(!list.length)return;$('subAll').disabled=true;const bar=$('subProg'),fill=bar.querySelector('i');bar.hidden=false;
    let ok=0;for(let k=0;k<list.length;k++){$('msg').className='msg';$('msg').textContent='Untertitel für Clip '+(k+1)+' von '+list.length+' … (läuft so lang wie der Clip, Seite offen lassen)';
      try{if(await burn(list[k],subLang))ok++}catch(e){console.warn(e)}fill.style.width=Math.round((k+1)/list.length*100)+'%';}
    bar.hidden=true;$('subAll').disabled=false;renderClips();$('msg').className='msg ok';$('msg').textContent='✓ Untertitel fertig ('+ok+' Clips). Bei jedem Clip gibt es jetzt „⬇ mit Untertitel“.';};
  subChips();document.body.classList.toggle('clipm',clipm);setMode(clipm?'clips':'ganz');
  choose();startCam();
})();
</script>
<?php
    });
    exit;
}

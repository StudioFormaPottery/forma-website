<?php
// ✂️ Video-Schnitt (Vivien, 30.10.2026): mehrere Clips (iPhone, Computer, Studio-Takes) zu einem Video schneiden.
// Alles läuft im Browser (verwaltung/schnitt.js); die Clips liegen in IndexedDB auf dem Gerät. ?view=schnitt
// Seit 30.10.2026 abends (Vivien: „hochladen, schneiden, Musik, und dann soll es direkt drin sein“): jeder Entwurf kennt sein Ziel
// (Lektion + Sprache, Übungs-Clip oder Instagram-Tag). „Fertig stellen & hochladen“ lädt direkt dorthin (Bunny bzw. eigener Server),
// das Titelbild wird bei Bunny als Vorschaubild gesetzt (sofort und noch einmal, sobald Bunny mit dem Umwandeln fertig ist).
declare(strict_types=1);

/** Vorschaubild bei Bunny setzen (Bild als Rohdaten). */
function sn_bunny_thumb(string $guid, string $bytes): bool
{
    if ($guid === '' || $bytes === '' || !mv_bunny_ok()) return false;
    $b = mv_bunny();
    $base = rtrim(getenv('MV_BUNNY_API') ?: 'https://video.bunnycdn.com', '/');
    $ch = curl_init($base . '/library/' . rawurlencode($b['lib']) . '/videos/' . rawurlencode($guid) . '/thumbnail');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $bytes, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['AccessKey: ' . $b['key'], 'Accept: application/json', 'Content-Type: application/octet-stream']]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code >= 400) { error_log('Schnitt: Bunny-Vorschaubild ' . $code); return false; }
    return true;
}

function sn_thumb_dir(): string { $d = forma_data_dir() . '/bunny-thumbs'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }

/** Offene Vorschaubilder nachziehen: sobald Bunny fertig umgewandelt hat, noch einmal setzen (gedrosselt, höchstens alle 2 Min.). */
function sn_thumb_run(): void
{
    $dir = sn_thumb_dir();
    $mark = $dir . '/.lauf';
    if (is_file($mark) && filemtime($mark) > time() - 120) return;
    @touch($mark);
    foreach (glob($dir . '/*.jpg') ?: [] as $f) {
        $guid = basename($f, '.jpg');
        if (filemtime($f) < time() - 3 * 86400) { @unlink($f); continue; }
        $info = mv_bunny_info($guid);
        if ($info === null) continue;
        $stat = (int)($info['status'] ?? 0);
        if ($stat >= 5) { @unlink($f); continue; } // Fehler bei Bunny
        if ($stat === 4 && sn_bunny_thumb($guid, (string)file_get_contents($f))) @unlink($f);
    }
}

/** Alles, wohin ein fertiges Video kommen kann: Talking Heads je Sprache, Übungs-Clips, Instagram-Tage. */
function sn_targets(PDO $pdo): array
{
    $out = [];
    foreach (['academy' => 'Academy', 'method' => 'Method', 'unfold' => 'Unfold', 'natal' => 'Prä- & Postnatal'] as $aud => $an) {
        foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) {
            if (mv_lesson_is_clips($l)) continue;
            foreach ($aud === 'method' ? ['de', 'en'] : ['de'] as $lg) { // Englisch nur noch bei The Method
                $out[] = ['k' => 'talk', 'id' => (int)$l['id'], 'lang' => $lg, 'n' => (string)$l['title_de'], 'g' => $an, 'due' => (string)$l['due'], 'done' => (string)$l['video_' . $lg] !== ''];
            }
        }
    }
    $use = [];
    foreach ($pdo->query("SELECT * FROM mv_lessons WHERE visible = 1 AND cat NOT IN ('talk','breath','meditation')")->fetchAll() as $l) {
        if (!mv_lesson_is_clips($l)) continue;
        foreach (mv_lesson_steps($l) as $x) { $k = mv_clip_key($x['de']); if ((string)$l['due'] !== '' && (!isset($use[$k]) || $l['due'] < $use[$k])) $use[$k] = (string)$l['due']; elseif (!isset($use[$k])) $use[$k] = ''; }
    }
    foreach (mv_clips_all($pdo, true) as $k => $c) {
        $out[] = ['k' => 'clip', 'c' => (int)$c['id'], 'n' => (string)$c['name_de'], 'due' => $use[$k] ?? '', 'done' => (string)$c['guid'] !== ''];
    }
    if (function_exists('ig_schema')) {
        ig_schema($pdo);
        $s = $pdo->prepare("SELECT * FROM mm_insta WHERE day >= ? AND status <> 'gepostet' AND kind IN ('reel','story') ORDER BY day, kind DESC, id LIMIT 90");
        $s->execute([date('Y-m-d', strtotime('-1 day'))]);
        foreach ($s->fetchAll() as $r) $out[] = ['k' => 'ig', 'id' => (int)$r['id'], 'kind' => (string)$r['kind'], 'n' => ig_title($r), 'due' => (string)$r['day'], 'done' => (string)$r['file'] !== ''];
    }
    usort($out, fn($a, $b) => [$a['due'] === '' ? '9' : $a['due'], $a['k'] === 'clip' ? 1 : 0, $a['n'], $a['lang'] ?? ''] <=> [$b['due'] === '' ? '9' : $b['due'], $b['k'] === 'clip' ? 1 : 0, $b['n'], $b['lang'] ?? '']);
    return $out;
}

// Titelbild als Vorschaubild bei Bunny (nach dem Hochladen aus dem Schnitt)
if ($action === 'mm_sn_thumb') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    header('Content-Type: application/json; charset=utf-8');
    $guid = preg_replace('/[^a-f0-9-]/', '', (string)($_POST['guid'] ?? ''));
    $f = $_FILES['img'] ?? null;
    if ($guid === '' || !$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 8 * 1024 * 1024) { echo json_encode(['error' => 'Bild fehlt.']); exit; }
    $bytes = (string)file_get_contents($f['tmp_name']);
    if (@getimagesizefromstring($bytes) === false) { echo json_encode(['error' => 'Kein Bild.']); exit; }
    file_put_contents(sn_thumb_dir() . '/' . $guid . '.jpg', $bytes); // bleibt liegen, bis Bunny fertig ist, dann noch einmal setzen
    $ok = sn_bunny_thumb($guid, $bytes);
    echo json_encode(['ok' => 1, 'now' => $ok]);
    exit;
}

if ($view === 'schnitt') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    if (isset($_GET['ziele'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['list' => sn_targets($pdo)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try { sn_thumb_run(); } catch (Throwable $e) { error_log('Schnitt Vorschaubilder: ' . $e->getMessage()); }
    $bunny = mv_bunny_ok();
    render_page('Video-Schnitt', function () use ($self, $bunny) {
        $h = 'forma_h'; ?>
<style>
body{background:#F2ECE4}
main{max-width:none;padding-left:clamp(12px,2.4vw,32px);padding-right:clamp(12px,2.4vw,32px);overflow-x:hidden}
.sh{display:flex;flex-wrap:wrap;gap:12px 20px;align-items:flex-end;justify-content:space-between;margin:6px 0 16px}
.sh h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;color:#4A3A41;font-size:clamp(34px,4vw,46px);margin:0;line-height:1.05}
.sh p{margin:4px 0 0;color:#6B5658;font-size:19px;max-width:70ch}
.sh a{font-size:18px;color:#6B5658}
.how{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin:0 0 18px}
.how div{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:18px;padding:12px 14px;font-size:16px;color:#4A3A41;line-height:1.45}
.how b{display:block;font-weight:500;font-size:17px;margin-bottom:2px}
@media(max-width:760px){.how{display:none}}
</style>
<?php mm_subnav('schnitt'); ?>
<div class="sh"><div><h1>✂️ Video-Schnitt</h1><p>Video vom iPhone oder aus dem Studio holen, schneiden, Titelbild und Musik dazu, fertig stellen – und es landet direkt dort, wo es hingehört.</p></div>
  <a href="<?= $h($self) ?>?view=studio&amp;kal=1">📅 Zum Kalender</a></div>
<div class="how">
  <div><b>1 · Video holen</b>Im 📅 Kalender beim Video auf „📤 hochladen“ tippen, oder hier „＋ Clips vom Gerät“. Alles bleibt auf diesem Gerät, bis du es löschst.</div>
  <div><b>2 · Schneiden, Titelbild, Musik</b>✨ Automatisch schneiden oder selbst, 🖼 Titelbild ganz vorne, 🎵 Musik darunter.</div>
  <div><b>3 · Fertig stellen &amp; hochladen</b>Ein Tipp: das Video wird gespeichert und gleich in die richtige Lektion hochgeladen. Dann steht „✓ Ist drin“.</div>
</div>
<div id="sn"></div>
<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
<script src="schnitt.js?v=30"></script>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>, BUNNY=<?= $bunny ? 'true' : 'false' ?>;
  function post(a,d,file){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);if(file)f.append(file[0],file[1],file[2]);
    return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
  // Direkt zu Bunny (gleiche Aktionen wie „Videos hochladen“ und das Studio)
  function bunny(blob,newAct,newData,setAct,setData,title,prog){
    return new Promise((ok)=>{
      if(!BUNNY){ok({error:'Bunny Stream ist noch nicht eingerichtet (Movecia → Method & Unfold → Bunny).'});return}
      if(typeof tus==='undefined'){ok({error:'Der Upload konnte nicht laden. Bitte Seite neu laden – dein Video bleibt im Entwurf.'});return}
      post(newAct,newData).then(j=>{if(j.error){ok({error:j.error});return}
        new tus.Upload(blob,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
          headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},metadata:{filetype:blob.type,title:title},
          onError:()=>ok({error:'Die Verbindung ist abgebrochen.'}),
          onProgress:(a,t)=>prog(t?a/t:0),
          onSuccess:()=>{post(setAct,Object.assign({guid:j.guid},setData)).then(r=>ok(r&&r.error?{error:r.error}:Object.assign({guid:j.guid},r||{}))).catch(()=>ok({error:'Hochgeladen, aber nicht eingetragen. Bitte nochmal tippen.'}))}}).start();
      }).catch(()=>ok({error:'Keine Verbindung zum Server.'}));
    });
  }
  // Instagram: in Stücken auf den eigenen Server (am Tag unter „Instagram täglich“ teilen)
  async function igUp(blob,id,ext,prog){const CH=1536*1024,n=Math.max(1,Math.ceil(blob.size/CH));
    for(let i=0;i<n;i++){const f=new FormData();f.append('csrf',csrf);f.append('action','mm_ig_up');f.append('id',id);f.append('n',i);f.append('ext',ext);if(i===n-1)f.append('last','1');
      f.append('b',blob.slice(i*CH,(i+1)*CH),'teil');
      let j=null;for(let t=0;t<3&&!j;t++){try{j=await fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}catch(e){await new Promise(r=>setTimeout(r,2000))}}
      if(!j||j.error)return {error:j?j.error:'Die Verbindung ist abgebrochen.'};prog((i+1)/n)}
    return {ok:1,note:'Am Tag selbst unter „📱 Instagram täglich“ auf „Teilen“ tippen.'}}
  Schnitt.open({mount:document.getElementById('sn'),musicUrl:'?view=musik&dl=',vendor:'vendor/',studioUrl:'?view=studio&take=1',calUrl:'?view=studio&kal=1',
    targets:()=>fetch('?view=schnitt&ziele=1',{credentials:'same-origin'}).then(r=>r.json()).then(j=>j.list||[]),
    upload:(blob,tg,prog,ext)=>{
      const title=tg.n+(tg.lang?' · '+tg.lang.toUpperCase():'');
      if(tg.k==='talk')return bunny(blob,'mm_mv_video_new',{id:tg.id,lang:tg.lang},'mm_mv_video_set',{id:tg.id,lang:tg.lang},title,prog).then(r=>(r.error?r:Object.assign(r,{note:'Bunny bereitet es in ein paar Minuten fertig auf.'})));
      if(tg.k==='clip')return bunny(blob,'mm_mv_clip_new',{c:tg.c},'mm_mv_clip_set',{c:tg.c},'Übung · '+tg.n,prog).then(r=>(r.error?r:Object.assign(r,{note:'Der Clip steckt jetzt in '+(r.used||1)+(r.used===1||!r.used?' Video':' Videos')+'.'})));
      if(tg.k==='ig')return igUp(blob,tg.id,ext,prog);
      return Promise.resolve({error:'Unbekanntes Ziel.'});},
    seeUrl:(tg,r)=>tg.k==='talk'?'?view=videos&play='+tg.id+'&lang='+tg.lang:(tg.k==='clip'&&r.see?'?view=videos&play='+r.see:''),
    thumb:(guid,img)=>post('mm_sn_thumb',{guid:guid},['img',img,'titelbild.jpg'])});
})();
</script>
<?php
    });
    exit;
}

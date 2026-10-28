<?php
// „🎵 Musik“ (Vivien, 28.10.2026): eigener Musik-Ordner auf dem Server (z. B. Lieder von Epidemic Sound),
// im Studio unter „Bearbeiten → Musik“ mit einem Tipp wählbar. Dateien in ../forma-data/musik/ (nie im Repo,
// gekaufte Musik darf nicht öffentlich liegen), nur angemeldet abrufbar. ?view=musik, Aktionen mm_mu_*.
declare(strict_types=1);

const MU_TAGS = ['morgen' => '☀️ Morgen', 'abend' => '🌙 Abend', 'flow' => '🧘 Flow & Bewegung', 'meditation' => '🕯️ Meditation & Atem', 'reel' => '📱 Reel & Story', 'lebendig' => '✨ Lebendig'];

function mu_schema(PDO $pdo): void
{
    static $d = false; if ($d) return; $d = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS mm_music (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, file TEXT NOT NULL, size INTEGER NOT NULL DEFAULT 0,
        dur REAL NOT NULL DEFAULT 0, tags TEXT NOT NULL DEFAULT '', created_at TEXT)");
}
function mu_dir(): string { $d = forma_data_dir() . '/musik'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function mu_get(PDO $pdo, int $id): ?array { $s = $pdo->prepare('SELECT * FROM mm_music WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }
/** „ES_Stay Brave (Instrumental Version) - Bird Of Figment.mp3“ → „Stay Brave – Bird Of Figment“ */
function mu_title(string $fn): string
{
    $t = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $fn);
    $t = preg_replace('/^ES_/', '', $t);
    $t = preg_replace('/\s*\((Instrumental Version|Instrumental)\)/i', '', $t);
    $t = str_replace(' - ', ' – ', $t);
    return trim($t) !== '' ? trim($t) : 'Lied';
}
/** Stichwörter aus dem Titel vorschlagen */
function mu_guess(string $t): array
{
    $l = mb_strtolower($t); $g = [];
    if (preg_match('/morning|sunrise|daylight|dawn|morgen|coffee|bright|new day/u', $l)) $g[] = 'morgen';
    if (preg_match('/night|evening|moon|sleep|dusk|abend|lullaby/u', $l)) $g[] = 'abend';
    if (preg_match('/breath|meditat|calm|peace|still|om|pines|ocean|rain/u', $l)) $g[] = 'meditation';
    return $g;
}

if ($view === 'musik' || str_starts_with((string)$action, 'mm_mu_')) {
    mu_schema($pdo);
    $muJson = function (array $x): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; };

    if ($action === 'mm_mu_up') {
        $key = preg_replace('/[^a-z0-9]/', '', (string)($_POST['key'] ?? ''));
        $f = $_FILES['b'] ?? null;
        if ($key === '' || !$f || $f['error'] !== UPLOAD_ERR_OK) $muJson(['error' => 'Upload-Stück fehlt (Fehler ' . (int)($f['error'] ?? -1) . ').']);
        $tmp = mu_dir() . '/up-' . $key . '.part';
        if ((int)($_POST['n'] ?? 0) === 0) @unlink($tmp);
        file_put_contents($tmp, file_get_contents($f['tmp_name']), FILE_APPEND);
        if (empty($_POST['last'])) $muJson(['ok' => 1]);
        $name = (string)($_POST['name'] ?? 'Lied.mp3');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp3', 'm4a', 'wav', 'aac', 'ogg', 'mp4'], true)) { @unlink($tmp); $muJson(['error' => 'Bitte eine Musikdatei (MP3, M4A, WAV).']); }
        $file = 'mu-' . bin2hex(random_bytes(6)) . '.' . $ext;
        rename($tmp, mu_dir() . '/' . $file);
        $title = mu_title($name);
        $pdo->prepare('INSERT INTO mm_music (title, file, size, dur, tags, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$title, $file, filesize(mu_dir() . '/' . $file), max(0, (float)($_POST['dur'] ?? 0)), implode(',', mu_guess($title)), date('c')]);
        $muJson(['ok' => 1, 'id' => (int)$pdo->lastInsertId(), 'title' => $title]);
    }
    if ($action === 'mm_mu_tag') {
        $r = mu_get($pdo, (int)($_POST['id'] ?? 0)); $t = (string)($_POST['tag'] ?? '');
        if (!$r || !isset(MU_TAGS[$t])) $muJson(['error' => 'Fehlt.']);
        $tags = array_filter(explode(',', $r['tags']));
        $tags = in_array($t, $tags, true) ? array_values(array_diff($tags, [$t])) : [...$tags, $t];
        $pdo->prepare('UPDATE mm_music SET tags = ? WHERE id = ?')->execute([implode(',', $tags), $r['id']]);
        $muJson(['ok' => 1, 'tags' => $tags]);
    }
    if ($action === 'mm_mu_name') {
        $r = mu_get($pdo, (int)($_POST['id'] ?? 0)); $t = trim((string)($_POST['title'] ?? ''));
        if ($r && $t !== '') $pdo->prepare('UPDATE mm_music SET title = ? WHERE id = ?')->execute([mb_substr($t, 0, 120), $r['id']]);
        $muJson(['ok' => 1]);
    }
    if ($action === 'mm_mu_del') {
        $r = mu_get($pdo, (int)($_POST['id'] ?? 0));
        if ($r) { @unlink(mu_dir() . '/' . basename($r['file'])); $pdo->prepare('DELETE FROM mm_music WHERE id = ?')->execute([$r['id']]); }
        $muJson(['ok' => 1]);
    }
    if ($view === 'musik' && isset($_GET['json'])) {
        $out = [];
        foreach ($pdo->query('SELECT id, title, dur, tags FROM mm_music ORDER BY id DESC')->fetchAll() as $r)
            $out[] = ['id' => (int)$r['id'], 't' => $r['title'], 'dur' => (float)$r['dur'], 'tags' => array_values(array_filter(explode(',', $r['tags'])))];
        $muJson(['list' => $out, 'tags' => MU_TAGS]);
    }
    if ($view === 'musik' && isset($_GET['dl'])) {
        $r = mu_get($pdo, (int)$_GET['dl']);
        $p = $r ? mu_dir() . '/' . basename($r['file']) : '';
        if ($p === '' || !is_file($p)) { http_response_code(404); exit('Nicht gefunden.'); }
        $ext = pathinfo($p, PATHINFO_EXTENSION);
        $types = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg'];
        $size = filesize($p); $from = 0; $to = $size - 1;
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=86400');
        header('Content-Disposition: inline; filename="' . rawurlencode($r['title'] . '.' . $ext) . '"');
        if (preg_match('/bytes=(\d*)-(\d*)/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
            if ($m[1] !== '') $from = (int)$m[1]; if ($m[2] !== '') $to = min($to, (int)$m[2]); if ($m[1] === '' && $m[2] !== '') { $from = $size - (int)$m[2]; $to = $size - 1; }
            http_response_code(206); header("Content-Range: bytes $from-$to/$size");
        }
        header('Content-Length: ' . ($to - $from + 1));
        $fh = fopen($p, 'rb'); fseek($fh, $from); $left = $to - $from + 1;
        while ($left > 0 && !feof($fh)) { $c = fread($fh, min(1 << 20, $left)); echo $c; $left -= strlen($c); flush(); }
        exit;
    }

    if ($view === 'musik') {
        $list = $pdo->query('SELECT * FROM mm_music ORDER BY id DESC')->fetchAll();
        $self = strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?');
        render_page('Musik', function () use ($list, $self) {
            $h = 'forma_h'; $fmt = fn($s) => $s > 0 ? floor($s / 60) . ':' . str_pad((string)floor(fmod($s, 60)), 2, '0', STR_PAD_LEFT) : ''; ?>
<style>
main{max-width:1240px}
.mh p{margin:0 0 18px;color:#6B5658;font-size:19px;line-height:1.5;max-width:70ch}
.drop{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:30px 18px;border:2px dashed #C9B0B8;border-radius:28px;background:linear-gradient(135deg,#FBF8F4,#F4E9E3);cursor:pointer;transition:background .2s,border-color .2s;margin-bottom:18px}
.drop.on{border-color:#7D5E68;background:#EADFDE}
.drop b{font:500 28px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41}
.drop span{font-size:17px;color:#6B5658}
.drop i{font-style:normal;font-size:40px}
.prog{height:10px;border-radius:9px;background:#EADFDE;overflow:hidden;margin:-6px 0 14px}.prog i{display:block;height:100%;width:0;background:#7D5E68;transition:width .3s}
.prog[hidden]{display:none}
.msg{font-size:18px;color:#6B5658;margin:0 0 14px}.msg.ok{color:#3F6B3E}
.flt{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
.flt button,.tg button{font:inherit;font-size:16px;padding:8px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:42px}
.flt button[aria-pressed=true]{background:#4A3A41;border-color:#4A3A41;color:#fff}
.songs{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px}
.song{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:24px;padding:14px;display:flex;flex-direction:column;gap:10px;box-shadow:0 14px 30px -26px rgba(74,58,65,.55)}
.song .top{display:flex;gap:12px;align-items:center}
.song .top>i{flex:0 0 auto;width:48px;height:48px;border-radius:16px;background:#EADFDE;display:flex;align-items:center;justify-content:center;font-size:24px;font-style:normal}
.song .tt{flex:1;min-width:0}.song .tt b{display:block;font-weight:500;font-size:18px;color:#2E2529;line-height:1.3;cursor:text}.song .tt small{color:#7D7076;font-size:15px}
.song audio{width:100%;height:40px}
.tg{display:flex;flex-wrap:wrap;gap:6px}.tg button{font-size:15px;min-height:38px;padding:6px 12px}
.tg button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.del{align-self:flex-end;font:inherit;font-size:14px;border:0;background:none;color:#7D7076;cursor:pointer;text-decoration:underline}
.empty{font-size:19px;color:#6B5658;padding:20px;text-align:center}
.tip{font-size:16px;color:#7D7076;line-height:1.5}
@media(max-width:640px){.songs{grid-template-columns:1fr}}
.song[hidden]{display:none}
</style>
<?php mm_subnav('musik'); ?>
<div class="mh"><h1>🎵 Deine Musik</h1>
  <p>Dein eigener Musik-Ordner, z. B. für die Lieder von Epidemic Sound. Einfach hierher ziehen. Im Studio findest du sie dann unter „✂️ Bearbeiten → 🎵 Musik darunter“ und tippst nur noch auf das Lied.</p></div>
<label class="drop" id="drop"><i>🎵</i><b>Lieder hierher ziehen oder antippen</b><span>MP3, M4A oder WAV · gern mehrere auf einmal</span><input type="file" id="muF" accept="audio/*,.mp3,.m4a,.wav,.aac,.ogg" multiple hidden></label>
<div class="prog" id="prog" hidden><i></i></div>
<p class="msg" id="msg"></p>
<?php if ($list): ?>
<div class="flt" id="flt"><button type="button" data-f="" aria-pressed="true">Alle <?= count($list) ?></button>
  <?php foreach (MU_TAGS as $k => $n): ?><button type="button" data-f="<?= $k ?>" aria-pressed="false"><?= $h($n) ?></button><?php endforeach; ?></div>
<div class="songs">
  <?php foreach ($list as $r): $tags = array_filter(explode(',', $r['tags'])); ?>
  <div class="song" data-id="<?= (int)$r['id'] ?>" data-tags="<?= $h(implode(' ', $tags)) ?>">
    <div class="top"><i>🎵</i><div class="tt"><b contenteditable="true" spellcheck="false" title="Zum Umbenennen antippen"><?= $h($r['title']) ?></b><small><?= $h($fmt((float)$r['dur'])) ?></small></div></div>
    <audio src="<?= $h($self) ?>?view=musik&amp;dl=<?= (int)$r['id'] ?>" controls preload="none"></audio>
    <div class="tg"><?php foreach (MU_TAGS as $k => $n): ?><button type="button" data-tag="<?= $k ?>" aria-pressed="<?= in_array($k, $tags, true) ? 'true' : 'false' ?>"><?= $h($n) ?></button><?php endforeach; ?></div>
    <button type="button" class="del">Lied löschen</button>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?><p class="empty">Noch keine Lieder. Zieh die ersten hierher ♡</p><?php endif; ?>
<p class="tip" style="margin-top:18px">Die Lieder liegen nur auf deinem Server und sind nur hier in der Verwaltung abrufbar, nie öffentlich.</p>
<script>
(function(){
  const csrf=<?= json_encode(csrf()) ?>,$=id=>document.getElementById(id);
  const post=(a,d,file)=>{const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);if(file)f.append('b',file,'teil');return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())};
  const dur=f=>new Promise(ok=>{const a=new Audio();a.preload='metadata';a.onloadedmetadata=()=>{ok(isFinite(a.duration)?a.duration:0);URL.revokeObjectURL(a.src)};a.onerror=()=>ok(0);a.src=URL.createObjectURL(f);setTimeout(()=>ok(0),4000)});
  async function upload(files){files=[...files].filter(f=>/audio|\.(mp3|m4a|wav|aac|ogg)$/i.test(f.type+' '+f.name));if(!files.length)return;
    const bar=$('prog'),fill=bar.querySelector('i'),CH=8*1024*1024;let done=0,ok=0,total=files.reduce((s,f)=>s+f.size,0);bar.hidden=false;
    for(const f of files){$('msg').className='msg';$('msg').textContent='Lädt hoch: '+f.name+' …';const d=await dur(f),key=Math.random().toString(36).slice(2)+Date.now().toString(36),n=Math.max(1,Math.ceil(f.size/CH));
      try{for(let i=0;i<n;i++){const extra={key:key,n:i,name:f.name,dur:d};if(i===n-1)extra.last=1;let j=null;
          for(let t=0;t<3&&!j;t++){try{j=await post('mm_mu_up',extra,f.slice(i*CH,(i+1)*CH))}catch(e){await new Promise(r=>setTimeout(r,2000))}}
          if(!j||j.error)throw new Error(j?j.error:'Netz');done+=Math.min(CH,f.size-i*CH);fill.style.width=Math.round(done/total*100)+'%'}ok++}
      catch(e){$('msg').textContent='„'+f.name+'“ hat nicht geklappt'+(e.message!=='Netz'?' ('+e.message+')':'')+'.';await new Promise(r=>setTimeout(r,1500))}}
    $('msg').className='msg ok';$('msg').textContent='✓ '+ok+(ok===1?' Lied':' Lieder')+' gespeichert.';setTimeout(()=>location.reload(),700);}
  const drop=$('drop');$('muF').onchange=e=>upload(e.target.files);
  ['dragenter','dragover'].forEach(t=>drop.addEventListener(t,e=>{e.preventDefault();drop.classList.add('on')}));
  ['dragleave','drop'].forEach(t=>drop.addEventListener(t,e=>{e.preventDefault();drop.classList.remove('on')}));
  drop.addEventListener('drop',e=>upload(e.dataTransfer.files));
  document.querySelectorAll('.song').forEach(s=>{const id=s.dataset.id;
    s.querySelectorAll('[data-tag]').forEach(b=>b.onclick=()=>post('mm_mu_tag',{id:id,tag:b.dataset.tag}).then(j=>{if(j.tags){s.dataset.tags=j.tags.join(' ');s.querySelectorAll('[data-tag]').forEach(x=>x.setAttribute('aria-pressed',String(j.tags.includes(x.dataset.tag))))}}));
    const t=s.querySelector('.tt b');t.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();t.blur()}});t.addEventListener('blur',()=>post('mm_mu_name',{id:id,title:t.textContent.trim()}));
    s.querySelector('.del').onclick=()=>{if(confirm('„'+t.textContent.trim()+'“ löschen?'))post('mm_mu_del',{id:id}).then(()=>s.remove())};});
  document.querySelectorAll('[data-f]').forEach(b=>b.onclick=()=>{document.querySelectorAll('[data-f]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));
    document.querySelectorAll('.song').forEach(s=>s.hidden=!!b.dataset.f&&!s.dataset.tags.split(' ').includes(b.dataset.f))});
})();
</script>
<?php
        });
        exit;
    }
}

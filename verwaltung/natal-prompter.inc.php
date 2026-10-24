<?php
// Verwaltung → Prä- & Postnatal → Teleprompter (Vivien, 24.10.2026): zum Testen und Vorbereiten jeder Einheit,
// und für Einheiten im Studio (iPad aufstellen). ?view=natal&prompter=prae3 oder &prompter=s12 (Termin → passende Einheit + Vorname).
// Eingebunden aus verwaltung/natal.inc.php (angemeldet). Gespeichert wird dasselbe wie im Videoraum (movecia-natal-prompter.json).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/natal-prompter.inc.php';
$h = 'forma_h';
$arg = (string)$_GET['prompter'];
$key = 'prae1';
$name = 'Anna';
$sub = 'Zum Testen und Vorbereiten';
if (preg_match('/^s(\d+)$/', $arg, $m)) {
    $s = natal_session($pdo, (int)$m[1]);
    $o = $s ? natal_order($pdo, (int)$s['order_id']) : null;
    if ($o) { $key = natal_session_key($pdo, $o, (int)$s['id']); $name = forma_first_name((string)$o['name']); $sub = natal_when($s) . ' · ' . $o['name']; }
} elseif (isset(NATAL_SCRIPT_TITLES[$arg])) $key = $arg;
$pc = natal_prompter_cfg();
$defs = natal_script_defaults();
$scripts = [];
foreach (NATAL_SCRIPT_TITLES as $sk => $st) $scripts[$sk] = ['title' => natal_unit_label($sk) === 'Eigenes Skript' ? $st : ($sk === 'kennenlernen' ? $st : natal_unit_label($sk)), 'text' => (string)($pc['texts'][$sk] ?? $defs[$sk] ?? ''), 'def' => (string)($defs[$sk] ?? ''), 'own' => isset($pc['texts'][$sk])];
$P = ['scripts' => $scripts, 'key' => $key, 'speed' => $pc['speed'], 'size' => $pc['size'], 'name' => $name, 'csrf' => csrf(), 'save' => $self . '?view=natal'];
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Teleprompter · Movecia</title><meta name="robots" content="noindex">
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--deep:#4A3A41;--plum:#7D5E68;--ink:#2E2529;--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
*{box-sizing:border-box}html,body{margin:0;height:100%;background:var(--deep);color:#F6EFF1;font-family:var(--sans)}
.wrap{height:100%;display:flex;flex-direction:column}
.hd{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:10px clamp(10px,2vw,20px);background:#3d3036}
.hd a{color:#EADFDE;font-size:.85rem}.hd b{font-family:var(--serif);font-weight:500;font-size:1.2rem;margin-right:4px}
.hd select,.hd button,.hd input{font:inherit;font-size:.85rem;border-radius:999px;border:1px solid #6b5a62;background:#4A3A41;color:#F6EFF1;padding:.38rem .8rem}
.hd button{cursor:pointer}.hd button.on{background:var(--plum);border-color:var(--plum)}
.hd input{width:7em}.hd .sp{font-size:.8rem;opacity:.75;min-width:3.2em;text-align:center}.hd .rt{margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.sub{font-size:.8rem;opacity:.7;padding:4px clamp(10px,2vw,20px) 0}
.view{position:relative;flex:1;overflow:hidden;cursor:pointer}
.view:before{content:"";position:absolute;left:0;right:0;top:30%;height:2.2em;background:rgba(234,223,222,.08);border-top:1px solid rgba(234,223,222,.18);border-bottom:1px solid rgba(234,223,222,.18);pointer-events:none;z-index:1}
.view:after{content:"";position:absolute;inset:0;background:linear-gradient(var(--deep),transparent 15%,transparent 82%,var(--deep));pointer-events:none;z-index:2}
.txt{position:absolute;left:0;right:0;padding:0 clamp(16px,8vw,140px);white-space:pre-wrap;line-height:1.5;will-change:transform}
.txt .h{display:block;font-size:.5em;letter-spacing:.06em;color:#C9B4BB;margin-top:.7em}
.ed{display:none;flex:1;padding:12px clamp(10px,2vw,20px)}.ed textarea{width:100%;height:100%;min-height:300px;border-radius:14px;border:0;padding:14px;font:inherit;font-size:1rem;line-height:1.55;background:#F6EFF1;color:var(--ink)}
.wrap.editing .view{display:none}.wrap.editing .ed{display:block}
.mir .txt{transform-origin:center}.mir .view .txt{scale:-1 1}
</style></head><body>
<div class="wrap" id="w">
  <div class="hd">
    <a href="<?= $h($self) ?>?view=natal#skripte">← Verwaltung</a><b>Teleprompter</b>
    <select id="sel" aria-label="Skript"><?php foreach ($scripts as $sk => $sc): ?><option value="<?= $h($sk) ?>"<?= $sk === $key ? ' selected' : '' ?>><?= $h($sc['title']) ?><?= $sc['own'] ? ' ✎' : '' ?></option><?php endforeach; ?></select>
    <label style="font-size:.8rem;opacity:.8">Name <input id="nm" value="<?= $h($name) ?>"></label>
    <button type="button" id="play">▶ Start</button><button type="button" id="top" title="Zum Anfang">⟲</button>
    <button type="button" data-sp="-1">−</button><span class="sp" id="spv"></span><button type="button" data-sp="1">+</button>
    <button type="button" data-sz="-1">A−</button><button type="button" data-sz="1">A+</button>
    <span class="rt"><button type="button" id="mir" title="Spiegeln für einen Teleprompter-Aufsatz">⇋ Spiegeln</button><button type="button" id="edb">✎ Bearbeiten</button></span>
  </div>
  <p class="sub"><?= $h($sub) ?> · Leertaste = Start/Pause · Tippen auf den Text = Start/Pause · Zeilen in [Klammern] sind Hinweise für dich</p>
  <div class="view" id="view"><div class="txt" id="txt"></div></div>
  <div class="ed"><textarea id="area" spellcheck="true"></textarea><p style="font-size:.8rem;opacity:.75">Wird automatisch gespeichert und gilt auch im Videoraum. {name} wird durch den Vornamen ersetzt. <button type="button" id="reset" style="font:inherit;background:none;border:0;color:#EADFDE;text-decoration:underline;cursor:pointer">Vorlage wiederherstellen</button></p></div>
</div>
<script>
(function(){
const P=<?= json_encode($P, JSON_UNESCAPED_UNICODE) ?>;
const w=document.getElementById('w'),sel=document.getElementById('sel'),view=document.getElementById('view'),txt=document.getElementById('txt'),area=document.getElementById('area'),play=document.getElementById('play'),spv=document.getElementById('spv'),nm=document.getElementById('nm');
let key=P.key,speed=P.speed,size=Math.max(P.size,34),y=0,run=false,last=0,t=null;
const esc=s=>s.replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
function render(){txt.innerHTML=esc(P.scripts[key].text.replace(/\{name\}/g,nm.value||'')).replace(/\[([^\]\n]*)\]/g,'<span class="h">$1</span>');txt.style.fontSize=size+'px';spv.textContent=speed.toFixed(1)+'×';place();}
function place(){txt.style.transform='translateY('+(view.clientHeight*0.30-y)+'px)';}
function loop(ts){if(run){const dt=last?(ts-last)/1000:0;y+=dt*speed*size*0.9;if(y>txt.scrollHeight){y=txt.scrollHeight;stop();}place();}last=ts;requestAnimationFrame(loop);}
function start(){run=true;play.textContent='❚❚ Pause';play.classList.add('on');}
function stop(){run=false;play.textContent='▶ Start';play.classList.remove('on');}
function save(o){const f=new FormData();f.append('action','mm_natal_prompter_save');f.append('csrf',P.csrf);f.append('json',JSON.stringify(o));fetch(P.save,{method:'POST',body:f}).catch(()=>{});}
play.onclick=()=>run?stop():start();view.onclick=()=>run?stop():start();
document.getElementById('top').onclick=()=>{stop();y=0;place();};
document.querySelectorAll('[data-sp]').forEach(b=>b.onclick=()=>{speed=Math.max(.2,Math.min(6,Math.round((speed+(+b.dataset.sp)*.2)*10)/10));render();save({speed});});
document.querySelectorAll('[data-sz]').forEach(b=>b.onclick=()=>{size=Math.max(16,Math.min(90,size+(+b.dataset.sz)*4));render();save({size:Math.min(64,size)});});
sel.onchange=()=>{key=sel.value;stop();y=0;area.value=P.scripts[key].text;render();};
nm.oninput=render;
document.getElementById('mir').onclick=e=>{e.target.classList.toggle('on');w.classList.toggle('mir');};
document.getElementById('edb').onclick=e=>{const on=w.classList.toggle('editing');e.target.textContent=on?'✓ Fertig':'✎ Bearbeiten';if(on){stop();area.value=P.scripts[key].text;area.focus();}else render();};
area.oninput=()=>{P.scripts[key].text=area.value;clearTimeout(t);t=setTimeout(()=>save({key,text:area.value}),700);};
document.getElementById('reset').onclick=()=>{if(!confirm('Deine Änderungen an diesem Skript verwerfen und die Vorlage laden?'))return;P.scripts[key].text=P.scripts[key].def;area.value=P.scripts[key].def;save({reset:key});};
document.addEventListener('keydown',e=>{if(e.code==='Space'&&document.activeElement!==area&&!['SELECT','INPUT'].includes(document.activeElement.tagName)){e.preventDefault();run?stop():start();}});
addEventListener('resize',place);render();requestAnimationFrame(loop);
})();
</script>
</body></html>

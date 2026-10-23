<?php
// moveciamovement.com/natal/raum/?r=s12&k=KEY – Movecia-Videoraum für Online-Training (s) und Kennenlerngespräch (c).
// Jeder Termin hat automatisch einen eigenen Link (natal_room_link). Der Whereby-Raum wird beim ersten Öffnen
// angelegt und hier eingebettet, Gäste sehen nur Movecia. Gastgeberinnen-Link (Vivien) hat einen eigenen Schlüssel.
declare(strict_types=1);
require dirname(__DIR__, 3) . '/api/natal-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$pdo = forma_db();
natal_schema($pdo);
$h = 'forma_h';

$room = natal_room_resolve($pdo, (string)($_GET['r'] ?? ''), (string)($_GET['k'] ?? ''));
$now = time();
$state = 'invalid';
$src = '';
if ($room) {
    $opensAt = $room['start'] - ($room['host'] ? 60 : 15) * 60;
    $closesAt = $room['end'] + 60 * 60;
    if ($room['cancelled']) $state = 'cancelled';
    elseif (!$room['ok']) $state = 'pending';
    elseif ($now < $opensAt) $state = 'soon';
    elseif ($now > $closesAt) $state = 'over';
    else {
        [$guestUrl, $hostUrl] = natal_room_ensure($pdo, $room);
        $base = $room['host'] ? $hostUrl : $guestUrl;
        if ($base === '') $state = 'noroom';
        else {
            $state = 'open';
            $src = $base . (str_contains($base, '?') ? '&' : '?') . http_build_query([
                'displayName' => $room['host'] ? 'Vivien' : forma_first_name($room['name']),
                'background' => 'off', 'logo' => 'off', 'precallReview' => 'on', 'chat' => 'on', 'people' => 'off', 'locking' => 'off',
            ]);
        }
    }
}
$first = $room ? forma_first_name($room['name']) : '';
$when = $room && $room['start'] ? natal_date_de(date('Y-m-d', $room['start'])) . ' um ' . date('G:i', $room['start']) . ' Uhr' : '';
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title><?= $room ? $h($room['title']) . ' · ' : '' ?>Movecia Videoraum</title>
<meta name="theme-color" content="#F2ECE4">
<style>
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:normal;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
:root{--bg:#F2ECE4;--card:#FBF8F4;--ink:#2E2529;--muted:#7D7076;--line:#DDD2C6;--plum:#7D5E68;--deep:#4A3A41;--blush:#EADFDE;--peach:#E8D6CA;--on:#FBF5F7;
--serif:"Cormorant",Georgia,serif;--sans:"Hanken",system-ui,sans-serif}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:18px;line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:var(--plum)}
.brand{font-family:var(--serif);font-size:1.8rem;font-weight:500;color:var(--ink);text-decoration:none;line-height:1;display:inline-flex;align-items:baseline}
.brand i{width:.3em;height:.3em;border-radius:50%;background:var(--plum);display:inline-block;margin-left:.08em}
.k{font-size:.74rem;letter-spacing:.2em;text-transform:uppercase;font-weight:500;color:var(--plum)}
.mut{color:var(--muted)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.6em;padding:.95em 1.6em;border-radius:999px;background:var(--plum);color:var(--on);font:inherit;font-size:.95rem;font-weight:500;text-decoration:none;border:1px solid var(--plum);cursor:pointer}
.btn.line{background:transparent;color:var(--ink);border-color:var(--line)}
/* Warteseite */
.wait{min-height:100%;display:grid;place-items:center;padding:28px 16px;position:relative;overflow:hidden}
.blob{position:absolute;border-radius:50%;filter:blur(2px);opacity:.75;pointer-events:none}
.b1{width:46vmax;height:46vmax;background:var(--blush);right:-14vmax;top:-16vmax}
.b2{width:30vmax;height:30vmax;background:var(--peach);left:-12vmax;bottom:-12vmax}
.card{position:relative;background:var(--card);border-radius:28px;padding:clamp(26px,5vw,48px);max-width:640px;width:100%;box-shadow:0 30px 60px -40px rgba(74,58,65,.45)}
.card h1{font-family:var(--serif);font-weight:500;font-size:clamp(2.2rem,5vw,3.1rem);line-height:1.05;margin:.9rem 0 .5rem}
.card h1 em{font-style:italic;font-weight:400;color:var(--plum)}
.when{display:flex;gap:.8rem;flex-wrap:wrap;margin:1.2rem 0;padding:1rem 1.2rem;background:var(--bg);border-radius:18px}
.when b{font-weight:600}
.count{font-family:var(--serif);font-size:clamp(2.4rem,6vw,3.4rem);color:var(--plum);line-height:1;margin:.4rem 0 .2rem}
.tips{margin:1.2rem 0 0;padding:0;list-style:none;display:grid;gap:.5rem}
.tips li{padding-left:1.5rem;position:relative}
.tips li:before{content:"";position:absolute;left:0;top:.62em;width:.55rem;height:.55rem;border-radius:50%;background:var(--plum);opacity:.7}
.check{margin-top:1.4rem;padding-top:1.2rem;border-top:1px solid var(--line)}
.check video{width:100%;max-width:340px;aspect-ratio:4/3;object-fit:cover;border-radius:18px;background:var(--deep);display:none;transform:scaleX(-1);margin-top:.8rem}
.lvl{height:8px;border-radius:9px;background:var(--line);max-width:340px;margin-top:.6rem;overflow:hidden;display:none}
.lvl i{display:block;height:100%;width:0;background:var(--plum);transition:width .08s}
.host{margin-top:1.2rem;font-size:.9rem;padding:.8rem 1rem;border-radius:14px;background:var(--blush)}
/* Raum */
.room{height:100%;display:flex;flex-direction:column;background:var(--deep)}
.bar{display:flex;align-items:center;gap:14px;padding:10px clamp(12px,3vw,24px);background:var(--bg);border-bottom:1px solid var(--line)}
.bar .t{display:flex;flex-direction:column;line-height:1.2;min-width:0}
.bar .t b{font-family:var(--serif);font-weight:500;font-size:1.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bar .t span{font-size:.82rem;color:var(--muted)}
.bar .clock{margin-left:auto;font-size:.85rem;color:var(--plum);white-space:nowrap;font-variant-numeric:tabular-nums}
.frame{flex:1;padding:clamp(0px,1.4vw,14px);background:linear-gradient(180deg,var(--bg),var(--blush))}
.frame iframe{width:100%;height:100%;border:0;border-radius:clamp(0px,1.4vw,20px);background:var(--deep);display:block}
@media (max-width:560px){.bar .t span{display:none}.brand{font-size:1.5rem}}
</style>
</head>
<body>
<?php if ($state === 'open'): ?>
<div class="room">
  <div class="bar"><span class="brand">Movecia<i></i></span>
    <span class="t"><b><?= $h($room['title']) ?><?= $room['host'] ? ' · ' . $h($first) : '' ?></b><span><?= $h($when) ?> · <?= $h($room['sub']) ?></span></span>
    <span class="clock" id="clock"></span></div>
  <div class="frame"><iframe src="<?= $h($src) ?>" allow="camera; microphone; fullscreen; speaker; display-capture; autoplay; compute-pressure" title="Movecia Videoraum"></iframe></div>
</div>
<script>
(function(){const s=<?= (int)$room['start'] ?>*1000,e=<?= (int)$room['end'] ?>*1000,el=document.getElementById('clock');
function t(){const n=Date.now();let m;if(n<s){m=Math.ceil((s-n)/60000);el.textContent='Beginn in '+m+' Min.';}else if(n<e){m=Math.ceil((e-n)/60000);el.textContent='noch '+m+' Min.';}else el.textContent='Zeit ist um';}
t();setInterval(t,15000);})();
</script>
<?php else: ?>
<div class="wait"><span class="blob b1"></span><span class="blob b2"></span>
  <div class="card">
    <span class="brand">Movecia<i></i></span>
    <?php if ($state === 'invalid'): ?>
      <h1>Dieser Link <em>passt leider nicht</em></h1>
      <p class="mut">Bitte öffne den Link direkt aus deiner E-Mail. Wenn es trotzdem nicht klappt, antworte einfach auf die Mail.</p>
    <?php elseif ($state === 'cancelled'): ?>
      <h1>Dieser Termin ist <em>abgesagt</em></h1>
      <p class="mut">Du hast dazu eine E-Mail bekommen. <?= $room['back'] !== '' ? 'Einen neuen Termin suchst du dir auf deiner Seite aus.' : '' ?></p>
      <?php if ($room['back'] !== ''): ?><p><a class="btn" href="<?= $h($room['back']) ?>">Zu meinen Terminen</a></p><?php endif; ?>
    <?php elseif ($state === 'pending'): ?>
      <h1>Dein Termin ist <em>noch nicht bestätigt</em></h1>
      <p class="mut">Sobald ich ihn bestätige, bekommst du eine E-Mail mit Uhrzeit. Dann funktioniert auch dieser Link.</p>
    <?php elseif ($state === 'over'): ?>
      <h1>Schön, dass <em>du da warst</em></h1>
      <p class="mut">Der Termin vom <?= $h($when) ?> ist vorbei. <?= $room['back'] !== '' ? 'Deine nächsten Termine findest du auf deiner Seite.' : 'Bei Fragen schreib mir einfach.' ?></p>
      <?php if ($room['back'] !== ''): ?><p><a class="btn" href="<?= $h($room['back']) ?>">Zu meinen Terminen</a></p><?php endif; ?>
    <?php elseif ($state === 'noroom'): ?>
      <h1>Der Raum wird <em>gerade vorbereitet</em></h1>
      <p class="mut">Bitte lade die Seite in einer Minute neu.</p>
      <p><button class="btn" type="button" onclick="location.reload()">Neu laden</button></p>
      <?php if ($room['host']): ?><p class="host">Nur für dich: Es konnte kein Videoraum angelegt werden. Bitte prüf in der Verwaltung unter Movecia → Einstellungen, ob der Whereby-Schlüssel hinterlegt ist.</p><?php endif; ?>
    <?php else: /* soon */ ?>
      <p class="k" style="margin-top:1.4rem"><?= $room['host'] ? 'Dein Raum als Gastgeberin' : 'Dein Videoraum' ?></p>
      <h1><?= $room['host'] ? $h($room['title']) . ' <em>mit ' . $h($first) . '</em>' : 'Hallo ' . $h($first) . ', <em>schön, dass du da bist</em>' ?></h1>
      <div class="when"><b><?= $h($when) ?></b><span class="mut"><?= $h($room['sub']) ?></span></div>
      <p class="mut" style="margin:0">Der Raum öffnet <?= $room['host'] ? '60' : '15' ?> Minuten vor Beginn, diese Seite geht dann von selbst auf.</p>
      <p class="count" id="count"></p>
      <ul class="tips">
        <?php if ($room['kind'] === 's'): ?>
        <li>Leg dir eine Matte, ein Kissen und etwas Wasser bereit.</li>
        <li>Stell Handy oder Laptop so auf, dass ich dich von Kopf bis Fuß sehe.</li>
        <?php else: ?>
        <li>Such dir einen ruhigen Platz, gern mit einer Tasse Tee.</li>
        <?php endif; ?>
        <li>Du brauchst keine App und kein Konto, dein Browser reicht.</li>
      </ul>
      <div class="check">
        <button class="btn line" type="button" id="test">Kamera &amp; Mikro ausprobieren</button>
        <video id="cam" playsinline muted autoplay></video>
        <div class="lvl" id="lvl"><i></i></div>
        <p class="mut" id="msg" style="font-size:.9rem;margin:.6rem 0 0"></p>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($state === 'soon'): ?>
<script>
(function(){const o=<?= (int)$opensAt ?>*1000,el=document.getElementById('count');
function t(){const d=o-Date.now();if(d<=0){location.reload();return;}const m=Math.floor(d/60000),hh=Math.floor(m/60),dd=Math.floor(hh/24);
el.textContent=dd>=1?'noch '+dd+(dd===1?' Tag':' Tage'):hh>=1?'noch '+hh+' Std. '+(m%60)+' Min.':'noch '+(m+1)+' Min.';}
t();setInterval(t,20000);
document.getElementById('test').addEventListener('click',async function(){const v=document.getElementById('cam'),l=document.getElementById('lvl'),msg=document.getElementById('msg');
 try{const ac=new (window.AudioContext||window.webkitAudioContext)();ac.resume();
  const st=await navigator.mediaDevices.getUserMedia({video:true,audio:true});v.srcObject=st;v.style.display='block';l.style.display='block';
  const an=ac.createAnalyser();an.fftSize=512;ac.createMediaStreamSource(st).connect(an);const buf=new Uint8Array(an.fftSize);
  (function loop(){an.getByteTimeDomainData(buf);let s=0;for(const x of buf){const y=(x-128)/128;s+=y*y;}l.firstChild.style.width=Math.min(100,Math.sqrt(s/buf.length)*700)+'%';requestAnimationFrame(loop);})();
  msg.textContent='Wunderbar, ich sehe und höre dich. Sag etwas, dann bewegt sich der Balken.';this.style.display='none';
 }catch(e){msg.textContent='Kamera oder Mikro sind gesperrt. Erlaube den Zugriff in deinem Browser (oben in der Adresszeile) und versuch es nochmal.';}
});})();
</script>
<?php endif; ?>
<?php endif; ?>
</body>
</html>

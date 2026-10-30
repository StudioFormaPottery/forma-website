<?php
// ✂️ Video-Schnitt (Vivien, 30.10.2026): mehrere Clips (iPhone, Computer, Studio-Takes) zu einem Video schneiden.
// Alles läuft im Browser (verwaltung/schnitt.js); die Clips liegen in IndexedDB auf dem Gerät. ?view=schnitt
declare(strict_types=1);

if ($view === 'schnitt') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    render_page('Video-Schnitt', function () use ($self) {
        $h = 'forma_h'; ?>
<style>
body{background:#F2ECE4}
main{max-width:none;padding-left:clamp(12px,2.4vw,32px);padding-right:clamp(12px,2.4vw,32px);overflow-x:hidden}
.sh{display:flex;flex-wrap:wrap;gap:12px 20px;align-items:flex-end;justify-content:space-between;margin:6px 0 16px}
.sh h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;color:#4A3A41;font-size:clamp(34px,4vw,46px);margin:0;line-height:1.05}
.sh p{margin:4px 0 0;color:#6B5658;font-size:19px;max-width:70ch}
.how{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin:0 0 18px}
.how div{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:18px;padding:12px 14px;font-size:16px;color:#4A3A41;line-height:1.45}
.how b{display:block;font-weight:500;font-size:17px;margin-bottom:2px}
@media(max-width:760px){.how{display:none}}
</style>
<?php mm_subnav('schnitt'); ?>
<div class="sh"><div><h1>✂️ Video-Schnitt</h1><p>Mehrere Aufnahmen vom iPhone oder aus dem Studio zu einem Video schneiden: Anfang und Ende setzen, teilen, umsortieren, Licht und Musik dazu, fertig.</p></div></div>
<div class="how">
  <div><b>1 · Clips holen</b>Vom iPhone oder Computer, oder mehrere Takes im Studio aufnehmen. Sie bleiben auf diesem Gerät.</div>
  <div><b>2 · Schneiden</b>Clip antippen, an die Stelle gehen, „Anfang hier“ oder „Ende hier“. Versprecher? Clip löschen, nächsten nehmen.</div>
  <div><b>3 · Fertig stellen</b>Ein Video aus allen Clips, mit Licht und Musik. Dann herunterladen, in die Fotos sichern oder im Studio hochladen.</div>
</div>
<div id="sn"></div>
<script src="schnitt.js?v=1"></script>
<script>
Schnitt.open({mount:document.getElementById('sn'),musicUrl:'?view=musik&dl=',vendor:'vendor/',studioUrl:'?view=studio&take=1',
  toStudio:function(){location.href='?view=studio&cut=1'}});
</script>
<?php
    });
    exit;
}

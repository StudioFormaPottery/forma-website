<?php
// Gutscheinfeld für Buchungsformulare (Method, Unfold), Vivien 31.10.2026: Movecia-Gutscheine gelten für alle Programme.
// Ins <form> setzen: das Feld heißt „gutschein“ und wird mit dem Formular mitgeschickt. „?gutschein=CODE“ füllt es vor.
function mv_voucher_field(): string
{
    static $n = 0; $n++;
    $id = 'gsf' . $n;
    $pre = preg_replace('/[^A-Za-z0-9-]/', '', (string)($_GET['gutschein'] ?? ''));
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    ob_start(); ?>
<details class="gsf" id="<?= $id ?>"<?= $pre !== '' ? ' open' : '' ?>><summary><span class="de">Hast du einen Gutschein?</span><span class="en">Do you have a gift card?</span></summary>
  <div class="gsf-r"><input type="text" name="gutschein" value="<?= $h($pre) ?>" placeholder="MOVECIA-XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40"><button type="button" class="gsf-b"><span class="de">Prüfen</span><span class="en">Check</span></button></div>
  <p class="gsf-m" hidden></p>
</details>
<style>.gsf{font-size:.92rem}.gsf summary{cursor:pointer;color:var(--plum,#4A3040);text-decoration:underline;text-underline-offset:3px;list-style:none}.gsf summary::-webkit-details-marker{display:none}
.gsf-r{display:flex;gap:8px;margin-top:.6rem}.gsf-r input{flex:1;min-width:0;font:inherit;font-size:16px;padding:.7em .9em;border-radius:14px;border:1px solid #D3C9BC;background:#FBF9F6;color:inherit;text-transform:uppercase;letter-spacing:.04em}
.gsf-b{font:inherit;font-size:.9rem;border:1px solid #4A3040;background:transparent;color:#4A3040;border-radius:999px;padding:0 1.1em;cursor:pointer}.gsf-m{margin:.5rem 0 0;font-size:.88rem}.gsf-m.gsf-ok{color:#3F6B45}.gsf-m.gsf-no{color:#A0463A}</style>
<script>(function(){var w=document.getElementById(<?= json_encode($id) ?>),i=w.querySelector('input'),m=w.querySelector('.gsf-m'),en=function(){return document.documentElement.dataset.l==='en'};
function eur(c){return '€ '+(c%100?(c/100).toFixed(2).replace('.',','):String(c/100))}
async function chk(){var c=i.value.trim();m.hidden=true;if(!c)return;try{var r=await fetch('/api/method.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({voucher_check:1,gutschein:c})});var j=await r.json();
m.className='gsf-m '+(j.ok?'gsf-ok':'gsf-no');m.textContent=j.ok?(en()?'✓ Gift card '+j.nr+' with '+eur(j.balance)+' credit. It is taken off your first payment, any rest stays on the card.':'✓ Gutschein '+j.nr+' mit '+eur(j.balance)+' Guthaben. Er wird von deiner ersten Zahlung abgezogen, ein Rest bleibt dir erhalten.'):(j.error||'…');if(j.ok)i.value=j.code;m.hidden=false;}catch(e){}}
w.querySelector('.gsf-b').onclick=chk;i.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();chk();}});if(i.value)chk();})();</script>
<?php return (string)ob_get_clean();
}

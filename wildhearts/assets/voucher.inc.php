<?php
// Feld „Du hast einen Gutschein?“ für die Movecia-Buchungen (Vivien, 26.10.2026): Method, Unfold, Prä- & Postnatal.
// Prüft den Code sofort (api/mv-gutschein.php action=check) und sagt, was er deckt. Abgerechnet wird erst am Server beim Buchen.
// mv_voucher_field('method' | 'unfold' | 'natal', ['bilingual' => true])
// ?code=… in der Adresse trägt den Code ein und prüft ihn gleich (Link aus der Gutschein-Mail).

function mv_voucher_field(string $target, array $o = []): void
{
    static $assets = false;
    $bi = $o['bilingual'] ?? true;
    $T = fn(string $de, string $en) => $bi ? '<span class="de">' . $de . '</span><span class="en">' . $en . '</span>' : $de;
    $code = mvg_norm((string)($_GET['code'] ?? ''));
    ?>
<details class="vch" data-target="<?= forma_h($target) ?>" data-bi="<?= $bi ? 1 : 0 ?>"<?= $code !== '' ? ' open' : '' ?>>
  <summary><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2.4"/></svg><?= $T('Du hast einen Gutschein?', 'Do you have a voucher?') ?></summary>
  <div class="vch-in">
    <input type="text" name="code" value="<?= forma_h($code) ?>" placeholder="MOV-XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="24" aria-label="Gutscheincode">
    <button type="button" class="btn line sm vch-go"><?= $T('Einlösen', 'Apply') ?></button>
  </div>
  <p class="vch-msg" hidden></p>
</details>
<?php if ($assets) return; $assets = true; ?>
<style>
.vch{border:1px dashed var(--line,#DDD2C6);border-radius:16px;padding:.2rem 1rem;background:var(--card,#FBF8F4)}
.vch summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:.5rem;padding:.65rem 0;color:var(--plum,#7D5E68);font-size:.98rem}
.vch summary::-webkit-details-marker{display:none}
.vch[open] summary{padding-bottom:.3rem}
.vch-in{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;padding-bottom:.8rem}
.vch-in input{flex:1 1 13em;min-width:0;font:inherit;letter-spacing:.08em;text-transform:uppercase}
.vch-msg{margin:0 0 .9rem;padding:.7rem .9rem;border-radius:12px;background:#F2ECE4;color:var(--ink,#2E2529);font-size:.95rem;line-height:1.45}
.vch-msg.ok{background:#EADFDE}
.vch-msg.bad{background:#F6E3E0;color:#8A3B32}
.vch-msg b{font-weight:600}
</style>
<script>
(function(){
  function fmt(c){return '€ '+(c%100?(c/100).toFixed(2).replace('.',','):String(c/100))}
  document.querySelectorAll('.vch').forEach(function(box){
    var inp=box.querySelector('input[name=code]'),btn=box.querySelector('.vch-go'),msg=box.querySelector('.vch-msg'),target=box.dataset.target,form=box.closest('form');
    function de(){return box.dataset.bi!=='1'||document.documentElement.dataset.l!=='en'}
    function say(t,cls){msg.innerHTML=t;msg.className='vch-msg '+(cls||'');msg.hidden=false}
    function price(){
      // aktueller Preis der Auswahl (für die Anzeige, berechnet wird am Server)
      var r=(form&&form.querySelector('input[name=plan]:checked'))||document.querySelector('input[name=package]:checked');
      return r&&r.dataset.cents?parseInt(r.dataset.cents,10):0;
    }
    async function check(){
      var c=inp.value.trim();if(!c){say(de()?'Bitte gib deinen Gutscheincode ein.':'Please enter your voucher code.','bad');return}
      btn.disabled=true;
      try{
        var r=await fetch('/api/mv-gutschein.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'check',code:c,lang:de()?'de':'en'})});
        var j=await r.json();
        if(!j.ok){say(j.error||'…','bad');box.dataset.ok='';return}
        box.dataset.ok='1';var D=de(),t;
        if(j.program&&j.group===target&&target==='method') t=D?'Wie schön! Dein Gutschein schaltet dir <b>'+j.months+(j.months===1?' Monat':' Monate')+' Method</b> frei, ohne Abo und ohne Zahlung. Die Auswahl oben spielt keine Rolle.':'How lovely! Your voucher unlocks <b>'+j.months+(j.months===1?' month':' months')+' of the Method</b>, no subscription and no payment. The choice above does not matter.';
        else if(j.program&&j.group===target&&target==='unfold') t=D?'Wie schön! Dein <b>Platz in Unfold</b> ist mit diesem Gutschein ganz bezahlt.':'How lovely! Your <b>place in Unfold</b> is fully paid with this voucher.';
        else if(j.program&&target==='natal'&&j.natal&&document.querySelector('input[name=package][value="'+j.natal+'"]:checked')) t=D?'Wie schön! Dein <b>'+j.title.replace(/^Prä- & Postnatal Training · /,'')+'</b> ist mit diesem Gutschein ganz bezahlt.':'How lovely! Your package is fully paid with this voucher.';
        else if(j.group==='method'&&target!=='method') t=D?'Das ist ein Gutschein für die Method. Du kannst ihn auf der <a href="'+j.target+'">Method-Seite</a> einlösen, oder hier seinen Wert von '+j.balance_text+' anrechnen lassen.':'This is a Method voucher. Redeem it on the <a href="'+j.target+'">Method page</a>, or use its value of '+j.balance_text+' here.';
        else{var p=price();
          t=D?'Dein Gutschein ist gültig, Wert <b>'+j.balance_text+'</b>.'+(p?(j.balance>=p?' Er deckt deine Auswahl ganz, du zahlst nichts dazu.':' Er wird abgezogen, du zahlst nur noch <b>'+fmt(p-j.balance)+'</b> online.'):' Er wird beim Bezahlen abgezogen.')+(target==='method'?' Bei der Method wird er von deiner ersten Zahlung abgezogen.':'')
             :'Your voucher is valid, value <b>'+j.balance_text+'</b>.'+(p?(j.balance>=p?' It covers your choice in full, you pay nothing extra.':' It is deducted, you only pay <b>'+fmt(p-j.balance)+'</b> online.'):' It is deducted at checkout.')+(target==='method'?' For the Method it is deducted from your first payment.':'');}
        say(t,'ok');
      }catch(e){say(de()?'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.':'That did not work. Please try again.','bad')}
      finally{btn.disabled=false}
    }
    btn.addEventListener('click',check);
    inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();check()}});
    document.addEventListener('change',function(e){if(box.dataset.ok&&(e.target.name==='package'||e.target.name==='plan'))check()});
    document.querySelectorAll('.modes button').forEach(function(b){b.addEventListener('click',function(){if(box.dataset.ok)setTimeout(check,30)})});
    if(inp.value)check();
  });
})();
</script>
<?php
}

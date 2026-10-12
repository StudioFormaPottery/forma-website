// Saison-Icons für die Verwaltung (gleiche Icons und gleicher Modus wie auf der Website)
(function(){
  var C={r:'#8C2C1D',b:'#DDBFA8',k:'#674B40',p:'#FBF1EA',s:'#E4D2BF'};
  var ICONS={
    leaf:'<path d="M32 6c14 8 22 20 20 34-1 8-8 14-16 15-1-12 2-24 8-34-8 9-13 21-14 34C17 52 11 44 12 34 14 21 22 12 32 6z" fill="'+C.r+'"/><path d="M30 55c1-14 6-27 14-37" stroke="'+C.p+'" stroke-width="2" fill="none" stroke-linecap="round" opacity=".7"/>',
    leaf2:'<path d="M10 46C9 28 22 12 44 10c6-1 10 0 10 0s1 4 0 10C52 42 36 55 18 54l-6 6-3-3 6-6c-3-2-5-3-5-5z" fill="'+C.b+'"/><path d="M14 52L44 20" stroke="'+C.k+'" stroke-width="2" stroke-linecap="round" opacity=".55"/>',
    pumpkin:'<path d="M31 18c0-5 2-9 6-11" stroke="'+C.k+'" stroke-width="4" stroke-linecap="round" fill="none"/><ellipse cx="20" cy="38" rx="13" ry="17" fill="'+C.r+'"/><ellipse cx="44" cy="38" rx="13" ry="17" fill="'+C.r+'"/><ellipse cx="32" cy="38" rx="12" ry="19" fill="#9E3A26"/><path d="M38 14c6-3 12-1 14 3-6 2-11 1-14-3z" fill="'+C.b+'"/>',
    mug:'<path d="M24 6c-4 4 4 6 0 11M32 4c-4 4 4 6 0 11M40 6c-4 4 4 6 0 11" stroke="'+C.k+'" stroke-width="2.5" fill="none" stroke-linecap="round" opacity=".7"/><rect x="12" y="22" width="34" height="32" rx="8" fill="'+C.b+'"/><path d="M46 29h4a7 7 0 010 14h-4" stroke="'+C.b+'" stroke-width="5" fill="none"/><path d="M14 30h30" stroke="'+C.r+'" stroke-width="4"/><path d="M14 38h30" stroke="'+C.p+'" stroke-width="2" stroke-dasharray="2 4"/>',
    mushroom:'<path d="M8 32C8 18 20 10 32 10s24 8 24 22z" fill="'+C.r+'"/><circle cx="22" cy="22" r="3" fill="'+C.p+'"/><circle cx="36" cy="18" r="2.5" fill="'+C.p+'"/><circle cx="44" cy="26" r="2" fill="'+C.p+'"/><path d="M24 32h16l-2 20a4 4 0 01-4 4h-4a4 4 0 01-4-4z" fill="'+C.s+'"/>',
    acorn:'<path d="M32 6v6" stroke="'+C.k+'" stroke-width="3" stroke-linecap="round"/><path d="M14 22c0-7 8-11 18-11s18 4 18 11v3H14z" fill="'+C.k+'"/><path d="M17 25h30c0 17-7 30-15 33-8-3-15-16-15-33z" fill="'+C.b+'"/>',
    snow:'<g stroke="'+C.k+'" stroke-width="3" stroke-linecap="round"><path d="M32 6v52M9 19l46 26M9 45l46-26"/><path d="M26 10l6 6 6-6M26 54l6-6 6 6M10 28l8-2-2-8M54 36l-8 2 2 8M10 36l8 2-2 8M54 28l-8-2 2-8" fill="none"/></g>',
    tree:'<path d="M32 4l14 18h-7l11 14h-7l11 14H10l11-14h-7l11-14h-7z" fill="'+C.k+'"/><rect x="28" y="50" width="8" height="10" fill="'+C.r+'"/><circle cx="26" cy="30" r="2.5" fill="'+C.r+'"/><circle cx="38" cy="40" r="2.5" fill="'+C.b+'"/>',
    star:'<path d="M32 4c2 15 12 25 28 28-16 3-26 13-28 28-2-15-12-25-28-28 16-3 26-13 28-28z" fill="'+C.r+'"/>',
    mitten:'<path d="M20 10c8-4 18 0 20 8l4 16 6-6c3-3 8 1 5 5l-9 12-2 8H20l-4-24c-2-8-1-16 4-19z" fill="'+C.r+'"/><rect x="18" y="48" width="28" height="10" rx="3" fill="'+C.b+'"/><path d="M20 52h24" stroke="'+C.p+'" stroke-width="2" stroke-dasharray="3 3"/>',
    tulip:'<path d="M32 30v28" stroke="'+C.k+'" stroke-width="3"/><path d="M32 46c-8-2-14-8-16-16 8 1 14 6 16 16zM32 50c8-2 14-8 16-16-8 1-14 6-16 16z" fill="'+C.k+'" opacity=".75"/><path d="M20 12l6 6 6-10 6 10 6-6v10c0 8-5 14-12 14s-12-6-12-14z" fill="'+C.r+'"/>',
    daisy:'<g fill="'+C.p+'" stroke="'+C.b+'" stroke-width="1.5"><ellipse cx="32" cy="14" rx="6" ry="10"/><ellipse cx="32" cy="50" rx="6" ry="10"/><ellipse cx="14" cy="32" rx="10" ry="6"/><ellipse cx="50" cy="32" rx="10" ry="6"/><ellipse cx="19" cy="19" rx="6" ry="10" transform="rotate(-45 19 19)"/><ellipse cx="45" cy="45" rx="6" ry="10" transform="rotate(-45 45 45)"/><ellipse cx="45" cy="19" rx="6" ry="10" transform="rotate(45 45 19)"/><ellipse cx="19" cy="45" rx="6" ry="10" transform="rotate(45 19 45)"/></g><circle cx="32" cy="32" r="8" fill="'+C.r+'"/>',
    sprout:'<path d="M32 58V30" stroke="'+C.k+'" stroke-width="3" stroke-linecap="round"/><path d="M32 32C30 18 20 12 8 14c1 12 11 19 24 18zM32 26c2-12 12-18 24-16-1 12-11 18-24 16z" fill="'+C.k+'" opacity=".8"/><path d="M18 58h28" stroke="'+C.b+'" stroke-width="5" stroke-linecap="round"/>',
    butterfly:'<path d="M32 18v32" stroke="'+C.k+'" stroke-width="3" stroke-linecap="round"/><path d="M31 30C24 12 8 10 8 22c0 9 12 12 23 10zM33 30c7-18 23-20 23-8 0 9-12 12-23 10zM31 34c-12 0-18 8-14 14 4 4 12-2 14-10zM33 34c12 0 18 8 14 14-4 4-12-2-14-10z" fill="'+C.r+'"/><path d="M28 14l4 5 4-5" stroke="'+C.k+'" stroke-width="2" fill="none" stroke-linecap="round"/>',
    sun:'<circle cx="32" cy="32" r="12" fill="'+C.r+'"/><g stroke="'+C.b+'" stroke-width="4" stroke-linecap="round"><path d="M32 4v8M32 52v8M4 32h8M52 32h8M12 12l6 6M46 46l6 6M12 52l6-6M46 18l6-6"/></g>',
    mountain:'<path d="M2 54l20-32 10 14 8-10 22 28z" fill="'+C.k+'"/><path d="M22 22l6 9-4-2-4 4-4-2z" fill="'+C.p+'"/><path d="M40 26l6 8-3-1-3 3-3-2z" fill="'+C.p+'"/>',
    flower:'<path d="M32 30v28" stroke="'+C.k+'" stroke-width="3"/><g fill="'+C.b+'"><circle cx="32" cy="12" r="8"/><circle cx="22" cy="22" r="8"/><circle cx="42" cy="22" r="8"/><circle cx="26" cy="32" r="8"/><circle cx="38" cy="32" r="8"/></g><circle cx="32" cy="24" r="6" fill="'+C.r+'"/>',
    icecream:'<path d="M20 30l12 30 12-30z" fill="'+C.b+'"/><path d="M22 34l18 0M24 40h14M27 47h9" stroke="'+C.k+'" stroke-width="1.5" opacity=".5"/><circle cx="25" cy="24" r="10" fill="'+C.r+'"/><circle cx="39" cy="24" r="10" fill="'+C.p+'" stroke="'+C.b+'" stroke-width="1.5"/><circle cx="32" cy="14" r="9" fill="'+C.k+'"/>'
  };
  var SEASONS={herbst:['leaf','pumpkin','mug','leaf2','mushroom','acorn','leaf','mug'],winter:['snow','tree','mug','star','mitten','snow','tree','star'],fruehling:['tulip','daisy','sprout','butterfly','tulip','daisy','sprout','butterfly'],sommer:['sun','mountain','flower','icecream','sun','flower','mountain','daisy']};
  function season(d){var m=d.getMonth()+1;return m>=3&&m<=5?'fruehling':m>=6&&m<=8?'sommer':m>=9&&m<=11?'herbst':'winter';}
  function draw(mode){
    if(mode==='aus'){document.documentElement.classList.add('no-deco');return;}
    var list=SEASONS[SEASONS[mode]?mode:season(new Date())].filter(function(v,i,a){return a.indexOf(v)===i;});
    for(var j=list.length-1;j>0;j--){var q=Math.floor(Math.random()*(j+1)),t=list[j];list[j]=list[q];list[q]=t;}
    document.querySelectorAll('.deco-slot').forEach(function(el,i){
      el.innerHTML='<svg viewBox="0 0 64 64" aria-hidden="true">'+ICONS[list[i%list.length]]+'</svg>';
      el.style.setProperty('--r',((i*37)%40-20)+'deg');el.style.animationDelay=(-i*1.3)+'s';
    });
  }
  fetch('../api/deko.php',{cache:'no-store'}).then(function(r){return r.json();}).then(function(d){draw((d&&d.mode)||'auto');}).catch(function(){draw('auto');});
})();

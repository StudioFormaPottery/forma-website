// Verspielter Wellenrahmen um Karten (gleiche Wellenlänge auf jedem Bildschirm)
document.querySelectorAll('.wv').forEach(function(svg){var box=svg.parentElement,path=svg.querySelector('path');
 function draw(){var w=svg.clientWidth,h=svg.clientHeight;if(!w||!h)return;var A=4,L=34,r=22,d='',first=true;
  function edge(x0,y0,x1,y1,nx,ny){var len=Math.hypot(x1-x0,y1-y0),n=Math.max(2,Math.round(len/L)),st=n*8;for(var i=0;i<=st;i++){var t=i/st,o=A*Math.sin(t*n*2*Math.PI);d+=(first?'M':'L')+(x0+(x1-x0)*t+nx*o).toFixed(1)+' '+(y0+(y1-y0)*t+ny*o).toFixed(1);first=false;}}
  function corner(cx,cy,a0){for(var k=1;k<=6;k++){var a=a0+k*Math.PI/12;d+='L'+(cx+r*Math.cos(a)).toFixed(1)+' '+(cy+r*Math.sin(a)).toFixed(1);}}
  edge(r,0,w-r,0,0,-1);corner(w-r,r,-Math.PI/2);edge(w,r,w,h-r,1,0);corner(w-r,h-r,0);edge(w-r,h,r,h,0,1);corner(r,h-r,Math.PI/2);edge(0,h-r,0,r,-1,0);corner(r,r,Math.PI);path.setAttribute('d',d+'Z');}
 draw();if(window.ResizeObserver)new ResizeObserver(draw).observe(box);else addEventListener('resize',draw);});

// Gutscheincode prüfen (angerechnet wird erst beim Buchen am Server)
document.querySelectorAll('.gs-box').forEach(function(box){
 var inp=box.querySelector('.gs-code'),btn=box.querySelector('.gs-check'),msg=box.querySelector('.gs-msg');
 function show(ok,txt){msg.hidden=false;msg.className='gs-msg '+(ok?'ok':'err');msg.textContent=txt;}
 inp.addEventListener('input',function(){msg.hidden=true;});
 btn.addEventListener('click',function(){var c=inp.value.trim();if(!c)return;btn.disabled=true;var fd=new FormData();fd.append('code',c);
  fetch('../api/gutschein-check.php',{method:'POST',body:fd}).then(function(r){return r.json().catch(function(){return {ok:false};});}).then(function(d){btn.disabled=false;
   if(d&&d.ok)show(true,(btn.dataset.ok||'✓ %s').replace('%s',d.balance));else show(false,(d&&d.error)||'Error');}).catch(function(){btn.disabled=false;});});
});

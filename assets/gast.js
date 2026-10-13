// Verspielter Wellenrahmen um Karten (gleiche Wellenlänge auf jedem Bildschirm)
document.querySelectorAll('.wv').forEach(function(svg){var box=svg.parentElement,path=svg.querySelector('path');
 function draw(){var w=svg.clientWidth,h=svg.clientHeight;if(!w||!h)return;var A=4,L=34,r=22,d='',first=true;
  function edge(x0,y0,x1,y1,nx,ny){var len=Math.hypot(x1-x0,y1-y0),n=Math.max(2,Math.round(len/L)),st=n*8;for(var i=0;i<=st;i++){var t=i/st,o=A*Math.sin(t*n*2*Math.PI);d+=(first?'M':'L')+(x0+(x1-x0)*t+nx*o).toFixed(1)+' '+(y0+(y1-y0)*t+ny*o).toFixed(1);first=false;}}
  function corner(cx,cy,a0){for(var k=1;k<=6;k++){var a=a0+k*Math.PI/12;d+='L'+(cx+r*Math.cos(a)).toFixed(1)+' '+(cy+r*Math.sin(a)).toFixed(1);}}
  edge(r,0,w-r,0,0,-1);corner(w-r,r,-Math.PI/2);edge(w,r,w,h-r,1,0);corner(w-r,h-r,0);edge(w-r,h,r,h,0,1);corner(r,h-r,Math.PI/2);edge(0,h-r,0,r,-1,0);corner(r,r,Math.PI);path.setAttribute('d',d+'Z');}
 draw();if(window.ResizeObserver)new ResizeObserver(draw).observe(box);else addEventListener('resize',draw);});

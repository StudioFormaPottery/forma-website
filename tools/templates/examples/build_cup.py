import asyncio,math
from playwright.async_api import async_playwright
import os as _os
R='file://'+_os.path.abspath(_os.path.join(_os.path.dirname(_os.path.abspath(__file__)),'../../..'))+'/'
D=_os.getcwd()+'/'
r,b,k,p,s,ink='#8C2C1D','#DDBFA8','#674B40','#FBF1EA','#E4D2BF','#33211B'
def leaf_pts(L=150,W=80,n=160):
    up=[];lo=[]
    for i in range(n+1):
        t=i/n; base=math.sin(math.pi*t)**0.75*(1-0.25*t)
        wav=1+0.07*math.sin(5*math.pi*t)
        up.append((L*t,-W/2*base*wav))
        lo.append((L*t,W/2*base*0.9*(1+0.07*math.sin(5*math.pi*t+1.2))))
    return up,lo
def leaf_path(L=150,W=80,stem=True,sc=1,ox=0,oy=0):
    up,lo=leaf_pts(L,W)
    f=lambda x,y:f'{x*sc+ox:.2f} {y*sc+oy:.2f}'
    d='M'+f(0,-3.5)+' '+' '.join('L'+f(x,y) for x,y in up[1:])
    d+=' '+' '.join('L'+f(x,y) for x,y in reversed(lo[1:-1]))+' L'+f(0,3.5)
    if stem: d+=' L'+f(-14,4.5)+' Q'+f(-19,0)+' '+f(-14,-4.5)+' Z'
    else: d+=' Z'
    return d
def veins(L=150,W=80,sc=1,ox=0,oy=0):
    up,lo=leaf_pts(L,W)
    f=lambda x,y:f'{x*sc+ox:.2f} {y*sc+oy:.2f}'
    d='M'+f(4,0)+' Q'+f(L*.5,-3)+' '+f(L*.9,-2)
    for t in (.22,.42,.62,.8):
        x=L*t; yu=up[int(t*160)][1]*.7; yl=lo[int(t*160)][1]*.7
        d+=' M'+f(x,-1.5)+' Q'+f(x+10,yu*.5)+' '+f(x+18,yu)+' M'+f(x,1.5)+' Q'+f(x+10,yl*.5)+' '+f(x+18,yl)
    return d
def icon(attr='width="100%" height="100%"'):
    lf=leaf_path(150,80,True,.16,0,0)
    return f'''<svg viewBox="0 0 220 180" {attr} xmlns="http://www.w3.org/2000/svg">
<ellipse cx="104" cy="160" rx="88" ry="13" fill="{b}"/><ellipse cx="104" cy="155" rx="66" ry="8" fill="#C9A68E"/>
<path d="M60 150 L56 70 Q56 64 62 64 L146 64 Q152 64 152 70 L148 150 Q147 156 140 156 L68 156 Q61 156 60 150Z" fill="{r}"/>
<ellipse cx="104" cy="66" rx="48" ry="7" fill="#7A2618"/><ellipse cx="104" cy="67" rx="42" ry="5" fill="#6B3A22"/>
<path d="M150 82 C178 80 182 128 150 128" fill="none" stroke="{r}" stroke-width="11" stroke-linecap="round"/>
<path d="M66 92 h76" stroke="{p}" stroke-width="2.5" stroke-dasharray="3 6" stroke-linecap="round" opacity=".8"/>
<g transform="translate(80 118) rotate(-25)"><path d="{lf}" fill="{b}"/></g>
<path d="M70 66 L64 112" stroke="{p}" stroke-width="1.6"/><rect x="56" y="110" width="16" height="18" rx="2" fill="{p}"/><path d="M60 116h8M60 121h6" stroke="{r}" stroke-width="1.4"/>
<path d="M92 50c-8 -8 8 -14 0 -24M110 48c-8 -8 8 -14 0 -24M126 50c-8 -8 8 -14 0 -24" fill="none" stroke="{k}" stroke-width="3" stroke-linecap="round" opacity=".55"/>
<path d="M78 76 c10 -4 22 -5 30 -3" fill="none" stroke="{p}" stroke-width="4" stroke-linecap="round" opacity=".45"/>
<g transform="translate(176 40) rotate(20)"><path d="M32 6v6" stroke="{k}" stroke-width="4" stroke-linecap="round" transform="scale(.6)"/><g transform="scale(.6)"><path d="M12 22c0-8 9-12 20-12s20 4 20 12v4H12z" fill="{k}"/><path d="M15 26h34c0 19-8 33-17 36-9-3-17-17-17-36z" fill="{b}"/></g></g>
</svg>'''
TXT={
'de':dict(title='Herbst-Teetasse',sub='Slab-Template · Herbst',lvl='Anfängerfreundlich · ca. 1,5 Stunden',size='Tasse ca. 7,5 × 8,5 cm · Untertasse 14 cm',
 intro='Eine schlichte Teetasse aus der Platte mit Henkel, kleinem Herbstblatt und passender Untertasse. Perfekt für gemütliche Teeabende und ein schönes erstes Projekt mit der Plattentechnik.',
 youneed='Das brauchst du',needs=['Ton (ca. 700 g)','Nudelholz & 2 Leisten (5 mm)','Nadel oder Cuttermesser','Gabel oder Ritzwerkzeug & Schlicker','Ein Glas oder eine Rolle (ca. 7 cm) als Stütze','Schwamm & Rippe'],
 print='Drucke die Schablonen in Originalgröße (100 %, „Tatsächliche Größe“). Seite 3 ist im Querformat. Das Testquadrat muss genau 5 × 5 cm messen.',
 steps=['Ton 5 mm dick ausrollen. Wand A, Boden B, Henkel C, Blatt D und Untertasse E ausschneiden.','Die kurzen Enden der Wand schräg anschneiden, anrauen und mit Schlicker bestreichen.','Wand um das Glas legen und die Naht gut verstreichen, innen und außen.','Boden B anrauen, Tasse daraufsetzen und rundherum fest verstreichen.','Henkel biegen und ansetzen, das Blatt mit Adern daneben anbringen.','Untertasse leicht nach oben biegen, alles langsam trocknen lassen und brennen.'],
 stepsT='So geht’s',tA='Schablone A · Tassenwand',tA2='Gestrichelt rot: hier die kurzen Enden schräg anschneiden, dann treffen sie sich sauber zu einer Naht.',
 tB='Schablonen B–E · Boden, Henkel, Blatt & Untertasse',tB2='Gestrichelt auf der Untertasse: hier steht die Tasse.',
 test='Testquadrat 5 × 5 cm',tip='Tipp',tips=['Wickle Zeitungspapier um das Glas, dann lässt sich die Tasse später leicht abziehen.','Henkel erst ansetzen, wenn Tasse und Henkel lederhart sind, so hält er besser.','Lebensmittelechte Glasur verwenden, dann kannst du daraus trinken.'],
 share='Zeig mir deine Herbst-Teetasse! Markiere @forma.potteryartcraft',legal='© FORMA Earth & Art · Nur für den privaten Gebrauch. Weitergabe und Verkauf der Schablone sind nicht erlaubt.'),
'en':dict(title='Autumn Tea Cup',sub='Slab template · Autumn',lvl='Beginner friendly · approx. 1.5 hours',size='Cup approx. 7.5 × 8.5 cm · saucer 14 cm',
 intro='A simple slab-built tea cup with a handle, a little autumn leaf and a matching saucer. Perfect for cosy tea evenings and a lovely first project with the slab technique.',
 youneed='You will need',needs=['Clay (approx. 700 g)','Rolling pin & 2 guide sticks (5 mm)','Needle tool or craft knife','Fork or scoring tool & slip','A glass or tube (approx. 7 cm) as support','Sponge & rib'],
 print='Print the templates at full size (100 %, “Actual size”). Page 3 is landscape. The test square must measure exactly 5 × 5 cm.',
 steps=['Roll the clay 5 mm thick. Cut out wall A, base B, handle C, leaf D and saucer E.','Bevel the short ends of the wall, score them and add slip.','Wrap the wall around the glass and blend the seam well, inside and out.','Score base B, set the cup on top and blend all the way round.','Bend the handle and attach it, add the leaf with veins next to it.','Curve the saucer up slightly, let everything dry slowly and fire.'],
 stepsT='How it works',tA='Template A · Cup wall',tA2='Dashed red: bevel the short ends here so they meet neatly in one seam.',
 tB='Templates B–E · Base, handle, leaf & saucer',tB2='Dashed on the saucer: this is where the cup sits.',
 test='Test square 5 × 5 cm',tip='Tip',tips=['Wrap newspaper around the glass so the cup slides off easily later.','Attach the handle when cup and handle are leather hard, it holds better.','Use a food-safe glaze so you can drink from it.'],
 share='Show me your autumn tea cup! Tag @forma.potteryartcraft',legal='© FORMA Earth & Art · For personal use only. Sharing or selling this template is not permitted.'),
}
def step_svg(i):
    o=f'stroke="{k}" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"'
    if i==0: return f'<rect x="4" y="10" width="112" height="76" rx="6" fill="{b}"/><rect x="10" y="18" width="70" height="26" rx="2" fill="{p}" stroke="{r}" stroke-width="1.4" stroke-dasharray="4 3"/><circle cx="96" cy="31" r="12" fill="{p}" stroke="{r}" stroke-width="1.4" stroke-dasharray="4 3"/><circle cx="40" cy="66" r="16" fill="{p}" stroke="{r}" stroke-width="1.4" stroke-dasharray="4 3"/><rect x="66" y="58" width="40" height="8" rx="4" fill="{p}" stroke="{r}" stroke-width="1.4" stroke-dasharray="4 3"/>'
    if i==1: return f'<rect x="14" y="30" width="92" height="34" rx="2" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="M14 30 l10 34M106 30 l-10 34" stroke="{r}" stroke-width="2"/><path d="M20 40l6 4M20 48l6 4M100 40l-6 4M100 48l-6 4" stroke="{k}" stroke-width="1.4"/>'
    if i==2: return f'<rect x="44" y="14" width="32" height="66" rx="4" fill="#E8E1D8" stroke="{k}" stroke-width="1.2"/><path d="M38 22 h44 v58 h-44z" fill="{b}" opacity=".9" stroke="{k}" stroke-width="1.2"/><path d="M60 22v58" stroke="{r}" stroke-width="2" stroke-dasharray="3 3"/><path d="M86 40 q10 10 0 20" {o}/>'
    if i==3: return f'<ellipse cx="60" cy="72" rx="34" ry="8" fill="{p}" stroke="{k}" stroke-width="1.2"/><path d="M34 70 V30 h52 v40" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="M34 70 q26 8 52 0" fill="none" stroke="{r}" stroke-width="2.2"/>'
    if i==4: return f'<path d="M34 76 V26 h48 v50 z" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="M82 36 C104 34 106 66 82 66" fill="none" stroke="{r}" stroke-width="6" stroke-linecap="round"/><g transform="translate(42 58) rotate(-25) scale(.16)"><path d="{leaf_path()}" fill="{r}"/></g>'
    if i==5: return f'<ellipse cx="60" cy="70" rx="50" ry="10" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="M40 66 V30 h40 v36" fill="{s}" stroke="{k}" stroke-width="1.2"/><path d="M14 74 Q14 6 60 6 Q106 6 106 74" fill="none" stroke="{k}" stroke-width="1.4" stroke-dasharray="5 4"/>'
exec(open(_os.path.join(_os.path.dirname(_os.path.abspath(__file__)),'css_snip.txt')).read().replace('def build(lang):','def _css(lang):')+'    return css\n')
def foot(t,n): return f'<div class="pfoot"><img src="{R}img/forma-logo.png"><span>{t["title"]} · FORMA Earth &amp; Art</span><span>{n}</span></div>'
def page(inner,cls=''): return f'<section class="page {cls}">{inner}</section>'
def build(lang):
    t=TXT[lang]; css=_css(lang); P=[]
    css+='@page land{size:A4 landscape;margin:0}.page.land{page:land;width:297mm;height:210mm}'
    P.append(page(f'''<span class="blob" style="width:150mm;height:150mm;right:-60mm;top:-50mm;background:{b}"></span>
<span class="blob" style="width:90mm;height:90mm;left:-40mm;bottom:20mm;background:{s}"></span>
<span class="lang">{'DE' if lang=='de' else 'EN'}</span>
<img class="logo" src="{R}img/forma-logo.png"><p class="kick">{t['sub']}</p><h1>{t['title']}</h1>
<div class="ic" style="height:100mm;margin:14mm 0 8mm">{icon()}</div>
<div class="chips"><span>{t['lvl']}</span><span>{t['size']}</span></div>
<p class="intro">{t['intro']}</p>''','cover'))
    steps=''.join(f'<div class="step"><svg viewBox="0 0 120 92">{step_svg(i)}</svg><b>{i+1}</b><p style="display:inline">{st}</p></div>' for i,st in enumerate(t['steps']))
    tips=''.join(f'<div class="box"><h3>{t["tip"]}</h3><p>{x}</p></div>' for x in t['tips'])
    P.append(page(f'''<div class="two"><div class="box"><h3>{t['youneed']}</h3><ul>{''.join(f'<li>{n}</li>' for n in t['needs'])}</ul></div>
<div><h3>{'Drucken' if lang=='de' else 'Printing'}</h3><p class="printnote">{t['print']}</p></div></div>
<h2>{t['stepsT']}</h2><div class="steps">{steps}</div><div class="tips">{tips}</div>{foot(t,2)}'''))
    W,H=236,85  # Wand: Umfang für ca. 7,5 cm Durchmesser + Rand für die Naht
    wall=f'''<svg width="{W+10}mm" height="{H+10}mm" viewBox="-5 -5 {W+10} {H+10}" style="position:absolute;left:{(297-W-10)/2}mm;top:38mm">
<rect x="0" y="0" width="{W}" height="{H}" fill="none" stroke="{ink}" stroke-width=".5"/>
<path d="M4 0 V{H} M{W-4} 0 V{H}" stroke="{r}" stroke-width=".35" stroke-dasharray="2 1.5"/>
<text x="{W/2}" y="{H/2+6}" text-anchor="middle" font-family="B" font-size="18" fill="{b}">A</text></svg>'''
    P.append(f'''<section class="page land tpl"><div class="lbl"><h2 style="margin-bottom:1mm">{t['tA']}</h2><p>{t['tA2']}</p></div>{wall}
<div class="testsq" style="position:absolute;right:16mm;bottom:18mm;margin:0">{t['test']}</div>
<div style="position:absolute;left:16mm;width:190mm;bottom:28mm;text-align:left"><p class="share" style="text-align:left">{t['share']}</p><p class="legal" style="text-align:left">{t['legal']}</p></div>{foot(t,3)}</section>''')
    def circ(d,x,y,lab,inner=0):
        R=d/2
        extra=f'<circle cx="0" cy="0" r="{inner/2}" fill="none" stroke="{r}" stroke-width=".35" stroke-dasharray="2 1.5"/>' if inner else ''
        return f'''<svg width="{d+6}mm" height="{d+6}mm" viewBox="{-R-3} {-R-3} {d+6} {d+6}" style="position:absolute;left:{x}mm;top:{y}mm"><circle r="{R}" fill="none" stroke="{ink}" stroke-width=".5"/>{extra}<text y="5" text-anchor="middle" font-family="B" font-size="{min(16,d/4):.1f}" fill="{b}">{lab}</text></svg>'''
    handle=f'''<svg width="118mm" height="28mm" viewBox="-4 -4 118 28" style="position:absolute;left:94mm;top:190mm"><rect x="0" y="0" width="110" height="20" rx="10" fill="none" stroke="{ink}" stroke-width=".5"/><text x="55" y="14" text-anchor="middle" font-family="B" font-size="10" fill="{b}">C</text></svg>'''
    leaf=f'''<svg width="60mm" height="34mm" viewBox="-6 -17 60 34" style="position:absolute;left:110mm;top:226mm"><path d="{leaf_path(150,80,True,.3)}" fill="none" stroke="{ink}" stroke-width=".5"/><path d="{veins(150,80,.3)}" fill="none" stroke="{b}" stroke-width=".4" stroke-dasharray="1.5 1.2"/><text x="22" y="3" font-family="B" font-size="6" fill="{b}">D</text></svg>'''
    P.append(page(f'''<div class="lbl"><h2 style="margin-bottom:1mm">{t['tB']}</h2><p>{t['tB2']}</p></div>
{circ(140,30,30,'E',80)}{circ(77,14,182,'B')}{handle}{leaf}{foot(t,4)}''','tpl'))
    return f'<!doctype html><html lang="{lang}"><meta charset="utf-8"><style>{css}</style><body>{"".join(P)}</body></html>'
async def main():
    async with async_playwright() as pw:
        br=await pw.chromium.launch();pg=await br.new_page()
        for lang,fn in [('de','FORMA_Herbst-Teetasse_Template_DE'),('en','FORMA_Autumn_Tea_Cup_Template_EN')]:
            open(D+fn+'.html','w').write(build(lang))
            await pg.goto('file://'+D+fn+'.html');await pg.wait_for_timeout(800)
            await pg.pdf(path=D+fn+'.pdf',format='A4',print_background=True,prefer_css_page_size=True)
        html=f'<!doctype html><meta charset=utf-8><body style="margin:0;width:1200px;height:900px;background:#EEE2D4;display:grid;place-items:center;position:relative;overflow:hidden"><span style="position:absolute;width:640px;height:640px;border-radius:50%;background:{b};right:-200px;top:-180px"></span><span style="position:absolute;width:360px;height:360px;border-radius:50%;background:{s};left:-120px;bottom:-80px"></span><div style="width:640px;height:524px;position:relative">{icon()}</div></body>'
        open(D+'cup_prev.html','w').write(html)
        p2=await br.new_page(viewport={'width':1200,'height':900});await p2.goto('file://'+D+'cup_prev.html');await p2.wait_for_timeout(300);await p2.screenshot(path=D+'Teetasse_Vorschau.jpg',type='jpeg',quality=88)
        await br.close()
asyncio.run(main())

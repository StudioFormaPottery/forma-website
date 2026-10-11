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
    return f'''<svg viewBox="-30 -70 210 140" {attr} xmlns="http://www.w3.org/2000/svg">
<ellipse cx="72" cy="52" rx="70" ry="7" fill="{k}" opacity=".16"/>
<g transform="rotate(-14 75 0)"><path d="{leaf_path()}" fill="{r}"/><path d="{leaf_path(150,80,False,.86,10,0)}" fill="#A8442F"/>
<path d="{veins(150,80,.86,10,0)}" fill="none" stroke="#7A2618" stroke-width="2.6" stroke-linecap="round"/>
<path d="M-14 -4.5 Q-19 0 -14 4.5 L0 3.5 L0 -3.5Z" fill="{k}"/>
<path d="M40 -22 c14 -8 34 -10 48 -4" fill="none" stroke="{p}" stroke-width="4" stroke-linecap="round" opacity=".5"/></g>
<g transform="translate(140 6) rotate(16) scale(.7)"><path d="M32 6v6" stroke="{k}" stroke-width="4" stroke-linecap="round"/><path d="M12 22c0-8 9-12 20-12s20 4 20 12v4H12z" fill="{k}"/><path d="M15 26h34c0 19-8 33-17 36-9-3-17-17-17-36z" fill="{b}"/></g>
</svg>'''
TXT={
'de':dict(title='Blatt-Schälchen',sub='FORMA Maker Club · Oktober',lvl='Ganz einfach · ca. 30 Minuten',size='Groß ca. 16 cm · klein ca. 10 cm',
 intro='Ein kleines Schälchen in Blattform: für Ringe, Teebeutel, Nüsse oder einfach als Herbstdeko. Perfekt für den Einstieg und auch schön mit Kindern.',
 youneed='Das brauchst du',needs=['Ton (ca. 250 g)','Nudelholz & 2 Leisten (5 mm)','Nadel oder Cuttermesser','Schwamm','Eine flache Schale & Frischhaltefolie','Optional: ein echtes Blatt zum Abdrücken'],
 print='Drucke die Schablonen in Originalgröße (100 %, „Tatsächliche Größe“). Das Testquadrat auf Seite 3 muss genau 5 × 5 cm messen.',
 steps=['Ton 5 mm dick ausrollen und glatt streichen.','Schablone auflegen und mit der Nadel ausschneiden.','Adern einritzen oder ein echtes Blatt mit dem Nudelholz eindrücken.','Kanten mit feuchtem Schwamm weich abrunden.','In eine flache Schale mit Folie legen, so wölbt sich das Blatt.','Langsam trocknen lassen, dann schrühen, glasieren und brennen.'],
 stepsT='So geht’s',tA='Schablonen · Blatt groß & klein',tA2='Ausschneiden entlang der durchgezogenen Linie. Gestrichelt: Blattadern zum Einritzen.',
 test='Testquadrat 5 × 5 cm',tip='Tipp',tips=['Ein echtes Blatt mit kräftigen Adern (z. B. Ahorn oder Eiche) gibt den schönsten Abdruck.','Das kleine Blatt ist die perfekte Teebeutel-Ablage.','Glasur-Idee: Glasur etwas abwischen, dann leuchten die Adern.'],
 share='Zeig mir dein Blatt-Schälchen! Markiere @forma.potteryartcraft',legal='© FORMA Earth & Art · Gratis-Template für Mitglieder im FORMA Maker Club. Nur für den privaten Gebrauch, Weitergabe und Verkauf sind nicht erlaubt.'),
'en':dict(title='Leaf Dish',sub='FORMA Maker Club · October',lvl='Very easy · approx. 30 minutes',size='Large approx. 16 cm · small approx. 10 cm',
 intro='A little dish in the shape of a leaf: for rings, tea bags, nuts or simply as autumn decor. Perfect for beginners and lovely to make with kids.',
 youneed='You will need',needs=['Clay (approx. 250 g)','Rolling pin & 2 guide sticks (5 mm)','Needle tool or craft knife','Sponge','A shallow bowl & cling film','Optional: a real leaf to press in'],
 print='Print the templates at full size (100 %, “Actual size”). The test square on page 3 must measure exactly 5 × 5 cm.',
 steps=['Roll out the clay 5 mm thick and smooth it.','Place the template on the slab and cut around it.','Score the veins or press a real leaf in with the rolling pin.','Soften the edges with a damp sponge.','Lay it into a shallow bowl lined with cling film so it curves.','Let it dry slowly, then bisque fire, glaze and fire again.'],
 stepsT='How it works',tA='Templates · Large & small leaf',tA2='Cut along the solid line. Dashed: leaf veins to score.',
 test='Test square 5 × 5 cm',tip='Tip',tips=['A real leaf with strong veins (e.g. maple or oak) gives the most beautiful imprint.','The small leaf makes a perfect tea bag rest.','Glaze idea: wipe some glaze off so the veins shine through.'],
 share='Show me your leaf dish! Tag @forma.potteryartcraft',legal='© FORMA Earth & Art · Free template for FORMA Maker Club members. For personal use only, sharing or selling is not permitted.'),
}
def step_svg(i):
    o=f'stroke="{k}" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"'
    lp=leaf_path(150,80,True,.62,22,48); vn=veins(150,80,.62,22,48)
    if i==0: return f'<rect x="12" y="52" width="96" height="20" rx="4" fill="{b}"/><rect x="18" y="30" width="84" height="14" rx="7" fill="{s}" {o}/><path d="M8 37h10M102 37h10" {o}/><path d="M112 52v20" stroke="{r}" stroke-width="2"/><text x="114" y="66" font-size="9" fill="{r}">5</text>'
    if i==1: return f'<rect x="4" y="12" width="112" height="72" rx="6" fill="{b}"/><path d="{lp}" fill="{p}" stroke="{r}" stroke-width="1.6" stroke-dasharray="4 3"/><path d="M100 18 l12 -14" stroke="{k}" stroke-width="3" stroke-linecap="round"/>'
    if i==2: return f'<path d="{lp}" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="{vn}" fill="none" stroke="{r}" stroke-width="1.6" stroke-linecap="round"/>'
    if i==3: return f'<path d="{lp}" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="{vn}" fill="none" stroke="{k}" stroke-width="1" opacity=".5"/><rect x="78" y="58" width="30" height="20" rx="8" fill="#E9CFA0" stroke="{k}" stroke-width="1.5" transform="rotate(-15 93 68)"/>'
    if i==4: return f'<path d="M8 40 Q60 96 112 40" fill="{s}" stroke="{k}" stroke-width="1.6"/><path d="M8 40 h104" stroke="{k}" stroke-width="1.6"/><path d="M22 44 Q60 80 98 44" fill="none" stroke="{r}" stroke-width="6" stroke-linecap="round"/><path d="M60 8v20M52 20l8 8 8-8" {o}/>'
    if i==5: return f'<path d="{lp}" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="M14 80 Q14 10 60 10 Q106 10 106 80" fill="none" stroke="{k}" stroke-width="1.6" stroke-dasharray="5 4"/><path d="M96 26c3 4 3 7 0 9-3-2-3-5 0-9z" fill="{k}" opacity=".6"/>'
exec(open(_os.path.join(_os.path.dirname(_os.path.abspath(__file__)),'css_snip.txt')).read().replace('def build(lang):','def _css(lang):')+'    return css\n')
def foot(t,n): return f'<div class="pfoot"><img src="{R}img/forma-logo.png"><span>{t["title"]} · FORMA Maker Club</span><span>{n}</span></div>'
def page(inner,cls=''): return f'<section class="page {cls}">{inner}</section>'
def build(lang):
    t=TXT[lang]; css=_css(lang); P=[]
    P.append(page(f'''<span class="blob" style="width:150mm;height:150mm;right:-60mm;top:-50mm;background:{b}"></span>
<span class="blob" style="width:90mm;height:90mm;left:-40mm;bottom:20mm;background:{s}"></span>
<span class="lang">{'DE' if lang=='de' else 'EN'}</span>
<img class="logo" src="{R}img/forma-logo.png"><p class="kick">{t['sub']}</p><h1>{t['title']}</h1>
<div class="ic" style="height:90mm;margin:18mm 0 10mm">{icon()}</div>
<div class="chips"><span>{t['lvl']}</span><span>{t['size']}</span></div>
<p class="intro">{t['intro']}</p>''','cover'))
    steps=''.join(f'<div class="step"><svg viewBox="0 0 120 92">{step_svg(i)}</svg><b>{i+1}</b><p style="display:inline">{st}</p></div>' for i,st in enumerate(t['steps']))
    tips=''.join(f'<div class="box"><h3>{t["tip"]}</h3><p>{x}</p></div>' for x in t['tips'])
    P.append(page(f'''<div class="two"><div class="box"><h3>{t['youneed']}</h3><ul>{''.join(f'<li>{n}</li>' for n in t['needs'])}</ul></div>
<div><h3>{'Drucken' if lang=='de' else 'Printing'}</h3><p class="printnote">{t['print']}</p></div></div>
<h2>{t['stepsT']}</h2><div class="steps">{steps}</div><div class="tips">{tips}</div>{foot(t,2)}'''))
    big=f'''<svg width="186mm" height="96mm" viewBox="-22 -48 186 96" style="position:absolute;left:12mm;top:42mm">
<path d="{leaf_path(150,80)}" fill="none" stroke="{ink}" stroke-width=".5"/><path d="{veins(150,80)}" fill="none" stroke="{b}" stroke-width=".5" stroke-dasharray="2 1.6"/>
<text x="60" y="30" font-family="B" font-size="12" fill="{b}">A</text></svg>'''
    small=f'''<svg width="120mm" height="62mm" viewBox="-14 -31 120 62" style="position:absolute;left:12mm;top:150mm">
<path d="{leaf_path(150,80,True,.62)}" fill="none" stroke="{ink}" stroke-width=".5"/><path d="{veins(150,80,.62)}" fill="none" stroke="{b}" stroke-width=".5" stroke-dasharray="2 1.6"/>
<text x="38" y="18" font-family="B" font-size="9" fill="{b}">B</text></svg>'''
    P.append(page(f'''<div class="lbl"><h2 style="margin-bottom:1mm">{t['tA']}</h2><p>{t['tA2']}</p></div>{big}{small}
<div class="testsq" style="position:absolute;right:16mm;top:160mm;margin:0">{t['test']}</div>
<div style="position:absolute;left:16mm;right:16mm;bottom:20mm"><p class="share">{t['share']}</p><p class="legal">{t['legal']}</p></div>{foot(t,3)}''','tpl'))
    return f'<!doctype html><html lang="{lang}"><meta charset="utf-8"><style>{css}</style><body>{"".join(P)}</body></html>'
async def main():
    async with async_playwright() as pw:
        br=await pw.chromium.launch();pg=await br.new_page()
        for lang,fn in [('de','FORMA_Maker_Club_Blatt-Schaelchen_DE'),('en','FORMA_Maker_Club_Leaf_Dish_EN')]:
            open(D+fn+'.html','w').write(build(lang))
            await pg.goto('file://'+D+fn+'.html');await pg.wait_for_timeout(800)
            await pg.pdf(path=D+fn+'.pdf',format='A4',print_background=True,prefer_css_page_size=True)
        html=f'<!doctype html><meta charset=utf-8><body style="margin:0;width:960px;height:1200px;background:#EEE2D4;display:grid;place-items:center;position:relative;overflow:hidden"><span style="position:absolute;width:640px;height:640px;border-radius:50%;background:{b};right:-200px;top:-180px"></span><span style="position:absolute;width:360px;height:360px;border-radius:50%;background:{s};left:-120px;bottom:-80px"></span><div style="width:760px;height:510px;position:relative">{icon()}</div></body>'
        open(D+'leaf_prev.html','w').write(html)
        p2=await br.new_page(viewport={'width':960,'height':1200});await p2.goto('file://'+D+'leaf_prev.html');await p2.wait_for_timeout(300);await p2.screenshot(path=D+'Blatt_Vorschau.jpg',type='jpeg',quality=88)
        await br.close()
asyncio.run(main())

import asyncio,math
from playwright.async_api import async_playwright
import os as _os
R='file://'+_os.path.abspath(_os.path.join(_os.path.dirname(_os.path.abspath(__file__)),'../../..'))+'/'
D=_os.getcwd()+'/'
r,b,k,p,s,ink='#8C2C1D','#DDBFA8','#674B40','#FBF1EA','#E4D2BF','#33211B'
def star_pts(R,ri=0.52,cx=0,cy=0,rot=-90):
    pts=[]
    for i in range(10):
        a=math.radians(rot+i*36); rr=R if i%2==0 else R*ri
        pts.append((cx+rr*math.cos(a),cy+rr*math.sin(a)))
    return pts
def star_path(R,cx=0,cy=0,ri=0.52):
    P=star_pts(R,ri,cx,cy); n=len(P); d=''
    lerp=lambda a,b,t:(a[0]+(b[0]-a[0])*t,a[1]+(b[1]-a[1])*t)
    for i in range(n):
        prev,cur,nxt=P[i-1],P[i],P[(i+1)%n]
        t=0.18 if i%2==0 else 0.12
        a=lerp(cur,prev,t); b=lerp(cur,nxt,t)
        d+=('M' if i==0 else 'L')+f'{a[0]:.2f} {a[1]:.2f} Q{cur[0]:.2f} {cur[1]:.2f} {b[0]:.2f} {b[1]:.2f} '
    return d+'Z'
def hole(R,cx=0,cy=0,d=5):
    return f'M{cx-d/2:.2f} {cy-R*0.6:.2f} a{d/2} {d/2} 0 1 0 {d} 0 a{d/2} {d/2} 0 1 0 {-d} 0'
def dots(R,cx=0,cy=0):
    out=[]
    for i in range(5):
        a=math.radians(-90+i*72); out.append((cx+R*0.38*math.cos(a),cy+R*0.38*math.sin(a)))
    return out
def icon(attr='width="100%" height="100%"'):
    def st(R,cx,cy,col,inner,rot):
        dd=''.join(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="{R*0.06:.1f}" fill="{p}"/>' for x,y in dots(R,cx,cy))
        return f'<g transform="rotate({rot} {cx} {cy})"><path d="M{cx} {cy-R*0.6} L{cx} {cy-R-60}" stroke="{k}" stroke-width="2" opacity=".6"/><path d="{star_path(R,cx,cy)}" fill="{col}"/><path d="{star_path(R*0.78,cx,cy)}" fill="none" stroke="{inner}" stroke-width="2" stroke-dasharray="2 4" stroke-linecap="round"/><circle cx="{cx}" cy="{cy-R*0.6+2.5}" r="{R*0.06+2}" fill="#EEE2D4"/>{dd}</g>'
    return f'''<svg viewBox="0 0 220 170" {attr} xmlns="http://www.w3.org/2000/svg">
<path d="M10 14 Q110 44 210 14" fill="none" stroke="{k}" stroke-width="2.5" stroke-linecap="round"/>
{st(36,62,96,r,p,-8)}{st(30,128,82,b,p,6)}{st(22,180,72,k,b,-4)}
<path transform="translate(20 130) scale(.5)" fill="{r}" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/>
<path transform="translate(196 128) scale(.4)" fill="{b}" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/>
</svg>'''
TXT={
'de':dict(title='Sternen-Anhänger',sub='FORMA Maker Club · November',lvl='Ganz einfach · ca. 30 Minuten',size='3 Größen: 9 · 7 · 5 cm',
 intro='Kleine Sterne für den Adventskranz, als Geschenkanhänger oder für den Weihnachtsbaum. Schnell gemacht, wunderbar mit Kindern und eine schöne Einstimmung auf die Adventszeit.',
 youneed='Das brauchst du',needs=['Ton (ca. 200 g)','Nudelholz & 2 Leisten (4–5 mm)','Nadel oder Cuttermesser','Trinkhalm für das Loch','Schwamm','Spitzendeckchen, Gabel oder Stempel zum Verzieren','Schönes Band zum Aufhängen'],
 print='Drucke die Schablonen in Originalgröße (100 %, „Tatsächliche Größe“). Das Testquadrat auf Seite 3 muss genau 5 × 5 cm messen.',
 steps=['Ton 4–5 mm dick ausrollen und glatt streichen.','Muster eindrücken: Spitzendeckchen auflegen und mit dem Nudelholz drüberrollen.','Sterne mit Schablone und Nadel ausschneiden.','Mit dem Trinkhalm das Loch in die obere Zacke stechen.','Kanten mit feuchtem Schwamm glätten und flach trocknen lassen.','Brennen, nach Lust glasieren und mit einem Band aufhängen.'],
 stepsT='So geht’s',tA='Schablonen · Sterne in 3 Größen',tA2='Ausschneiden entlang der durchgezogenen Linie. Der kleine Kreis ist das Loch zum Aufhängen.',
 test='Testquadrat 5 × 5 cm',tip='Tipp',tips=['Das Loch mindestens 1 cm vom Rand entfernt stechen, dann bricht nichts aus.','Zwischen zwei Brettern mit Küchenpapier trocknen, so bleiben die Sterne schön flach.','Mit Namen eingeritzt werden sie zu Platzkarten oder Geschenkanhängern.'],
 share='Zeig mir deine Sterne! Markiere @forma.potteryartcraft',legal='© FORMA Earth & Art · Gratis-Template für Mitglieder im FORMA Maker Club. Nur für den privaten Gebrauch, Weitergabe und Verkauf sind nicht erlaubt.'),
'en':dict(title='Star Ornaments',sub='FORMA Maker Club · November',lvl='Very easy · approx. 30 minutes',size='3 sizes: 9 · 7 · 5 cm',
 intro='Little stars for your advent wreath, as gift tags or for the Christmas tree. Quick to make, lovely with kids and a cosy start to the festive season.',
 youneed='You will need',needs=['Clay (approx. 200 g)','Rolling pin & 2 guide sticks (4–5 mm)','Needle tool or craft knife','A drinking straw for the hole','Sponge','Lace doily, fork or stamp for texture','A pretty ribbon for hanging'],
 print='Print the templates at full size (100 %, “Actual size”). The test square on page 3 must measure exactly 5 × 5 cm.',
 steps=['Roll out the clay 4–5 mm thick and smooth it.','Add texture: place a lace doily on top and roll over it.','Cut out the stars with the template and needle tool.','Use the straw to punch the hole in the top point.','Smooth the edges with a damp sponge and let them dry flat.','Fire, glaze if you like and hang with a ribbon.'],
 stepsT='How it works',tA='Templates · Stars in 3 sizes',tA2='Cut along the solid line. The small circle is the hole for hanging.',
 test='Test square 5 × 5 cm',tip='Tip',tips=['Punch the hole at least 1 cm from the edge so it does not break out.','Dry them between two boards with kitchen paper so they stay nice and flat.','Scratch in a name and they become place cards or gift tags.'],
 share='Show me your stars! Tag @forma.potteryartcraft',legal='© FORMA Earth & Art · Free template for FORMA Maker Club members. For personal use only, sharing or selling is not permitted.'),
}
def step_svg(i):
    o=f'stroke="{k}" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"'
    st=lambda R,cx,cy,fill,stroke='',extra='': f'<path d="{star_path(R,cx,cy)}" fill="{fill}" {stroke}/>'+extra
    if i==0: return f'<rect x="12" y="52" width="96" height="20" rx="4" fill="{b}"/><rect x="18" y="30" width="84" height="14" rx="7" fill="{s}" {o}/><path d="M8 37h10M102 37h10" {o}/><path d="M112 52v20" stroke="{r}" stroke-width="2"/><text x="113" y="66" font-size="8" fill="{r}">4–5</text>'
    if i==1: return f'<rect x="8" y="40" width="104" height="40" rx="4" fill="{b}"/><circle cx="60" cy="56" r="26" fill="none" stroke="{p}" stroke-width="3" stroke-dasharray="2 3"/><circle cx="60" cy="56" r="16" fill="none" stroke="{p}" stroke-width="2" stroke-dasharray="1 3"/><rect x="18" y="18" width="84" height="14" rx="7" fill="{s}" {o}/>'
    if i==2: return f'<rect x="4" y="12" width="112" height="72" rx="6" fill="{b}"/>'+st(22,38,50,p,f'stroke="{r}" stroke-width="1.6" stroke-dasharray="4 3"')+st(17,82,46,p,f'stroke="{r}" stroke-width="1.6" stroke-dasharray="4 3"')+f'<path d="M102 20 l12 -14" stroke="{k}" stroke-width="3" stroke-linecap="round"/>'
    if i==3: return st(32,60,52,b,f'stroke="{k}" stroke-width="1.2"')+f'<circle cx="60" cy="33" r="3.5" fill="{p}" stroke="{k}" stroke-width="1"/><rect x="57" y="2" width="6" height="30" rx="3" fill="{r}"/>'
    if i==4: return st(32,60,50,b,f'stroke="{k}" stroke-width="1.2"')+f'<circle cx="60" cy="31" r="3.5" fill="#fff"/><rect x="74" y="56" width="30" height="20" rx="8" fill="#E9CFA0" stroke="{k}" stroke-width="1.5" transform="rotate(-15 89 66)"/>'
    if i==5: return f'<path d="M20 8 Q60 30 100 8" {o}/>'+st(16,36,44,r)+st(14,62,50,b)+st(12,86,42,k)+f'<path d="M36 22v10M62 26v12M86 20v12" stroke="{k}" stroke-width="1.2"/>'
exec(open(_os.path.join(_os.path.dirname(_os.path.abspath(__file__)),'css_snip.txt')).read().replace('def build(lang):','def _css(lang):')+'    return css\n')
def foot(t,n): return f'<div class="pfoot"><img src="{R}img/forma-logo.png"><span>{t["title"]} · FORMA Maker Club</span><span>{n}</span></div>'
def page(inner,cls=''): return f'<section class="page {cls}">{inner}</section>'
def build(lang):
    t=TXT[lang]; css=_css(lang); P=[]
    P.append(page(f'''<span class="blob" style="width:150mm;height:150mm;right:-60mm;top:-50mm;background:{b}"></span>
<span class="blob" style="width:90mm;height:90mm;left:-40mm;bottom:20mm;background:{s}"></span>
<span class="lang">{'DE' if lang=='de' else 'EN'}</span>
<img class="logo" src="{R}img/forma-logo.png"><p class="kick">{t['sub']}</p><h1>{t['title']}</h1>
<div class="ic" style="height:96mm;margin:16mm 0 10mm">{icon()}</div>
<div class="chips"><span>{t['lvl']}</span><span>{t['size']}</span></div>
<p class="intro">{t['intro']}</p>''','cover'))
    steps=''.join(f'<div class="step"><svg viewBox="0 0 120 92">{step_svg(i)}</svg><b>{i+1}</b><p style="display:inline">{st}</p></div>' for i,st in enumerate(t['steps']))
    tips=''.join(f'<div class="box"><h3>{t["tip"]}</h3><p>{x}</p></div>' for x in t['tips'])
    P.append(page(f'''<div class="two"><div class="box"><h3>{t['youneed']}</h3><ul>{''.join(f'<li>{n}</li>' for n in t['needs'])}</ul></div>
<div><h3>{'Drucken' if lang=='de' else 'Printing'}</h3><p class="printnote">{t['print']}</p></div></div>
<h2>{t['stepsT']}</h2><div class="steps">{steps}</div><div class="tips">{tips}</div>{foot(t,2)}'''))
    def S(R,x,y,lab):
        return f'''<svg width="{2*R+8}mm" height="{2*R+8}mm" viewBox="{-R-4} {-R-4} {2*R+8} {2*R+8}" style="position:absolute;left:{x}mm;top:{y}mm">
<path d="{star_path(R)}" fill="none" stroke="{ink}" stroke-width=".5"/><path d="{hole(R)}" fill="none" stroke="{ink}" stroke-width=".4"/>
<text x="0" y="{R*0.25}" text-anchor="middle" font-family="B" font-size="{R*0.35:.1f}" fill="{b}">{lab}</text></svg>'''
    big=S(47,12,32,'A')+S(47,108,32,'A')+S(37,18,126,'B')+S(37,98,126,'B')
    small=S(27,24,204,'C')+S(27,84,204,'C')
    P.append(page(f'''<div class="lbl"><h2 style="margin-bottom:1mm">{t['tA']}</h2><p>{t['tA2']}</p></div>{big}{small}
<div class="testsq" style="position:absolute;right:16mm;top:208mm;margin:0;width:50mm;height:50mm">{t['test']}</div>
{foot(t,3)}''','tpl'))
    return f'<!doctype html><html lang="{lang}"><meta charset="utf-8"><style>{css}</style><body>{"".join(P)}</body></html>'
async def main():
    async with async_playwright() as pw:
        br=await pw.chromium.launch();pg=await br.new_page()
        for lang,fn in [('de','FORMA_Maker_Club_Sternen-Anhaenger_DE'),('en','FORMA_Maker_Club_Star_Ornaments_EN')]:
            open(D+fn+'.html','w').write(build(lang))
            await pg.goto('file://'+D+fn+'.html');await pg.wait_for_timeout(800)
            await pg.pdf(path=D+fn+'.pdf',format='A4',print_background=True,prefer_css_page_size=True)
        html=f'<!doctype html><meta charset=utf-8><body style="margin:0;width:960px;height:1200px;background:#EEE2D4;display:grid;place-items:center;position:relative;overflow:hidden"><span style="position:absolute;width:640px;height:640px;border-radius:50%;background:{b};right:-200px;top:-180px"></span><span style="position:absolute;width:360px;height:360px;border-radius:50%;background:{s};left:-120px;bottom:-80px"></span><div style="width:780px;height:603px;position:relative">{icon()}</div></body>'
        open(D+'star_prev.html','w').write(html)
        p2=await br.new_page(viewport={'width':960,'height':1200});await p2.goto('file://'+D+'star_prev.html');await p2.wait_for_timeout(300);await p2.screenshot(path=D+'Sterne_Vorschau.jpg',type='jpeg',quality=88)
        await br.close()
asyncio.run(main())

import asyncio,math,os
from playwright.async_api import async_playwright
import os as _os
R='file://'+_os.path.abspath(_os.path.join(_os.path.dirname(_os.path.abspath(__file__)),'../../..'))+'/'
D=os.getcwd()+'/'
TPL=_os.path.dirname(_os.path.abspath(__file__))+'/'
r,b,k,p,s,ink='#8C2C1D','#DDBFA8','#674B40','#FBF1EA','#E4D2BF','#33211B'
SLIT=5.5
# ---------- shapes (mm, centred at 0,0) ----------
def star_path(R,cx=0,cy=0,ri=0.5,rot=-90):
    P=[];n=10
    for i in range(n):
        a=math.radians(rot+i*36); rr=R if i%2==0 else R*ri
        P.append((cx+rr*math.cos(a),cy+rr*math.sin(a)))
    lerp=lambda a,b,t:(a[0]+(b[0]-a[0])*t,a[1]+(b[1]-a[1])*t)
    d=''
    for i in range(n):
        prev,cur,nxt=P[i-1],P[i],P[(i+1)%n]; t=0.16 if i%2==0 else 0.1
        a=lerp(cur,prev,t); bb=lerp(cur,nxt,t)
        d+=('M' if i==0 else 'L')+f'{a[0]:.2f} {a[1]:.2f} Q{cur[0]:.2f} {cur[1]:.2f} {bb[0]:.2f} {bb[1]:.2f} '
    return d+'Z'
def ball(Rr,cap=True):
    if not cap: return f'M0 {-Rr} A{Rr} {Rr} 0 1 1 0 {Rr} A{Rr} {Rr} 0 1 1 0 {-Rr}Z'
    w=7; ang=math.asin(w/Rr); x=Rr*math.sin(ang); y=-Rr*math.cos(ang)
    return f'M{-x:.2f} {y:.2f} A{Rr} {Rr} 0 1 0 {x:.2f} {y:.2f} L{x-0.5:.2f} {y-6:.2f} Q0 {y-8.5:.2f} {-x+0.5:.2f} {y-6:.2f} Z'
def tree(H=92):
    # 3 soft tiers + trunk, centred
    h=H/2
    return (f'M0 {-h} C3 {-h+4} 10 {-h+16} 17 {-h+24} L9 {-h+24} C14 {-h+32} 22 {-h+42} 30 {-h+52} L18 {-h+52} '
            f'C24 {-h+60} 33 {-h+70} 40 {-h+78} L6 {-h+78} L6 {h} L-6 {h} L-6 {-h+78} L-40 {-h+78} C-33 {-h+70} -24 {-h+60} -18 {-h+52} '
            f'L-30 {-h+52} C-22 {-h+42} -14 {-h+32} -9 {-h+24} L-17 {-h+24} C-10 {-h+16} -3 {-h+4} 0 {-h} Z')
def heart(W=84):
    s_=W/84
    d='M0 30 C-14 18 -42 4 -42 -14 C-42 -30 -26 -40 -12 -34 C-6 -31 -2 -26 0 -22 C2 -26 6 -31 12 -34 C26 -40 42 -30 42 -14 C42 4 14 18 0 30 Z'
    return d, s_
SH={
 'ball':dict(h=86,A=lambda:ball(40,True),B=lambda:ball(40,False),top=-46,bot=40,hole=(0,-41.5),holeR=2.6),
 'star':dict(h=90,A=lambda:star_path(46),B=lambda:star_path(46),top=-46,bot=37,hole=(0,-31),holeR=2.6),
 'tree':dict(h=92,A=lambda:tree(),B=lambda:tree(),top=-46,bot=46,hole=(0,-31),holeR=2.3),
 'heart':dict(h=70,A=lambda:heart()[0],B=lambda:heart()[0],top=-36,bot=30,hole=(0,-15),holeR=2.6),
}
def slit(kind,part):
    s_=SH[kind]; mid=(s_['top']+s_['bot'])/2
    if part=='A':  # from bottom up to middle
        y0=mid-0.5; y1=s_['bot']+2
    else:
        y0=s_['top']-4; y1=mid+0.5
    return f'<rect x="{-SLIT/2}" y="{y0:.1f}" width="{SLIT}" height="{y1-y0:.1f}" fill="#F3E6DA" stroke="{ink}" stroke-width=".45" stroke-dasharray="1.6 1"/>'
def piece(kind,part,x,y,lab,name):
    s_=SH[kind]; W=96; H=s_['h']+16
    d=s_[part]()
    hole=''
    if part=='A':
        hx,hy=s_['hole'];hole=f'<circle cx="{hx}" cy="{hy}" r="{s_["holeR"]}" fill="none" stroke="{ink}" stroke-width=".4"/>'
    mid=(s_['top']+s_['bot'])/2
    return f'''<div style="position:absolute;left:{x}mm;top:{y}mm;width:{W}mm;text-align:center">
<svg width="{W}mm" height="{H}mm" viewBox="{-W/2} {s_['top']-8} {W} {H}" style="display:block;overflow:visible">
<path d="{d}" fill="#fff" stroke="{ink}" stroke-width=".55"/>{slit(kind,part)}{hole}
<text x="{-13 if kind=="tree" else -21}" y="{mid+(16 if kind=="tree" else 3)}" text-anchor="middle" font-family="B" font-size="9" fill="{b}">{lab}</text></svg>
<div style="font-size:7.5pt;color:{k};margin-top:1mm">{name}</div></div>'''
# ---------- cover illustration ----------
def icon():
    def b3(cx,cy,R,col,band):
        return f'<g><path d="M{cx} {cy-R-30} L{cx} {cy-R-4}" stroke="{k}" stroke-width="2" opacity=".6"/><rect x="{cx-7}" y="{cy-R-8}" width="14" height="9" rx="2" fill="{band}"/><circle cx="{cx}" cy="{cy}" r="{R}" fill="{col}"/><ellipse cx="{cx}" cy="{cy}" rx="{R*0.22}" ry="{R}" fill="{band}"/><circle cx="{cx-R*0.45}" cy="{cy-R*0.35}" r="{R*0.12}" fill="#fff" opacity=".35"/></g>'
    def s3(cx,cy,R,col,band):
        return f'<g><path d="M{cx} {cy-R-26} L{cx} {cy-R+6}" stroke="{k}" stroke-width="2" opacity=".6"/><path d="{star_path(R,cx,cy)}" fill="{col}"/><path d="M{cx-R*0.14} {cy-R*0.95} L{cx+R*0.14} {cy-R*0.95} L{cx+R*0.16} {cy+R*0.72} L{cx-R*0.16} {cy+R*0.72}Z" fill="{band}"/></g>'
    def t3(cx,cy,sc,col,band):
        return f'<g transform="translate({cx} {cy}) scale({sc})"><path d="M0 -70 L0 -46" stroke="{k}" stroke-width="{2/sc}" opacity=".6"/><path d="{tree()}" fill="{col}"/><path d="M-5 -42 L5 -42 L6 46 L-6 46Z" fill="{band}"/></g>'
    def h3(cx,cy,sc,col,band):
        return f'<g transform="translate({cx} {cy}) scale({sc})"><path d="M0 -60 L0 -22" stroke="{k}" stroke-width="{2/sc}" opacity=".6"/><path d="{heart()[0]}" fill="{col}"/><path d="M-6 -22 L6 -22 L4 30 L-4 30Z" fill="{band}"/></g>'
    return f'''<svg viewBox="0 0 240 180" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
<path d="M6 16 Q120 52 234 16" fill="none" stroke="{k}" stroke-width="2.5" stroke-linecap="round"/>
{b3(42,92,24,r,'#6E2216')}{t3(96,104,.62,'#9AA389','#7E8870')}{s3(150,96,28,b,'#C9A88F')}{h3(204,92,.55,k,'#4F3830')}
<path transform="translate(14 140) scale(.5)" fill="{r}" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/>
<path transform="translate(214 146) scale(.42)" fill="{b}" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/>
<path transform="translate(118 150) scale(.32)" fill="{k}" d="M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z"/>
</svg>'''
# ---------- texts ----------
COMMON={
'de':dict(title='3D-Weihnachts&shy;anhänger',sub='FORMA Adventkalender · Türchen 1',lvl='Einfach · ca. 45 Minuten',size='4 Motive · ca. 8–9 cm',
 intro='Zwei flache Teile, ineinandergesteckt, und schon wird ein Anhänger rund und räumlich: Kugel, Stern, Tanne und Herz für deinen Baum, ein Fenster oder als kleines Geschenk.',
 youneed='Das brauchst du',print='Drucke die Schablonen in Originalgröße (100 %, „Tatsächliche Größe“). Das Testquadrat muss genau 5 × 5 cm messen.',
 stepsT='So geht’s',tA='Schablonen · Kugel & Stern',tB='Schablonen · Tanne & Herz',
 tA2='Von jedem Motiv brauchst du Teil A (mit Loch, Schlitz von unten) und Teil B (Schlitz von oben). Der hell schraffierte Streifen ist der Schlitz: genau ausschneiden.',
 test='Testquadrat 5 × 5 cm',tip='Tipp',names=dict(ball='Kugel',star='Stern',tree='Tanne',heart='Herz'),
 share='Zeig mir deine Anhänger! Markiere @forma.potteryartcraft',foot='3D-Weihnachtsanhänger · FORMA Adventkalender',
 legal='© FORMA Earth & Art · Nur für den privaten Gebrauch. Weitergabe und Verkauf der Datei sind nicht erlaubt.',print_h='Drucken'),
'en':dict(title='3D Christmas Ornaments',sub='FORMA Advent Calendar · Door 1',lvl='Easy · approx. 45 minutes',size='4 designs · approx. 8–9 cm',
 intro='Two flat pieces slotted together and your ornament turns round and three-dimensional: bauble, star, tree and heart for your tree, a window or as a little gift.',
 youneed='You will need',print='Print the templates at full size (100 %, “Actual size”). The test square must measure exactly 5 × 5 cm.',
 stepsT='How it works',tA='Templates · Bauble & star',tB='Templates · Tree & heart',
 tA2='For each design you need part A (with hole, slot from the bottom) and part B (slot from the top). The shaded strip is the slot: cut it out exactly.',
 test='Test square 5 × 5 cm',tip='Tip',names=dict(ball='Bauble',star='Star',tree='Tree',heart='Heart'),
 share='Show me your ornaments! Tag @forma.potteryartcraft',foot='3D Christmas Ornaments · FORMA Advent Calendar',
 legal='© FORMA Earth & Art · For personal use only. Sharing or selling the file is not permitted.',print_h='Printing'),
}
VER={
('de','stone'):dict(chip='Steinzeugton · für den Brennofen',
 needs=['Steinzeugton (ca. 400 g)','Nudelholz & 2 Holzleisten (5 mm)','Spitzes Messer oder Cutter','Trinkhalm für das Loch','Schwamm & etwas Wasser','Band zum Aufhängen'],
 steps=['Ton 5 mm dick ausrollen und glatt streichen.','Schablonen auflegen und Teil A und B ausschneiden.','Schlitze genau ausschneiden, in Teil A mit dem Trinkhalm das Loch stechen.','Kanten mit dem feuchten Schwamm glätten. Einmal probehalber zusammenstecken, dann wieder trennen.','Teile flach trocknen lassen und getrennt brennen. Die Schlitze nicht glasieren.','Nach dem Brennen ineinanderstecken, Band durchziehen, fertig!'],
 tips=['Schneide die Schlitze lieber einen Hauch breiter: Glasur und Brand machen sie enger.','Zwischen zwei Brettern mit Küchenpapier trocknen, so bleiben die Teile schön flach.','Mit einem Spitzendeckchen oder Stempel bekommt die Oberfläche ein feines Muster.']),
('de','paper'):dict(chip='Paper Clay · ohne Brennofen',
 needs=['Paper Clay oder lufttrocknender Ton (ca. 400 g)','Nudelholz & 2 Holzleisten (5 mm)','Spitzes Messer oder Cutter','Trinkhalm für das Loch','Schwamm & etwas Wasser','Acrylfarbe, Pinsel & matter Klarlack','Band zum Aufhängen'],
 steps=['Ton 5 mm dick ausrollen und glatt streichen.','Schablonen auflegen und Teil A und B ausschneiden.','Schlitze genau ausschneiden, in Teil A mit dem Trinkhalm das Loch stechen.','Kanten mit dem feuchten Schwamm glätten. Einmal probehalber zusammenstecken, dann wieder trennen.','Teile flach trocknen lassen, 24–72 Stunden an der Luft oder nach Herstellerangabe im Backofen.','Mit Acrylfarbe bemalen, mit mattem Klarlack versiegeln, zusammenstecken und aufhängen.'],
 tips=['Wackelt der Anhänger, hilft ein Tropfen Bastelkleber im Schlitz.','Kreidefarbe oder Gouache gehen auch, danach immer versiegeln. Glasuren brauchen einen Brennofen.','Kleine Risse beim Trocknen mit etwas feuchtem Ton zustreichen und nachtrocknen.']),
('en','stone'):dict(chip='Stoneware clay · for the kiln',
 needs=['Stoneware clay (approx. 400 g)','Rolling pin & 2 guide sticks (5 mm)','Sharp knife or craft knife','A drinking straw for the hole','Sponge & a little water','Ribbon for hanging'],
 steps=['Roll out the clay 5 mm thick and smooth it.','Place the templates on top and cut out parts A and B.','Cut the slots exactly and punch the hole in part A with the straw.','Smooth the edges with a damp sponge. Test-fit the pieces once, then take them apart again.','Let the pieces dry flat and fire them separately. Keep glaze out of the slots.','After firing, slide the pieces together, add a ribbon and you are done!'],
 tips=['Cut the slots a tiny bit wider: glaze and firing make them tighter.','Dry them between two boards with kitchen paper so they stay nice and flat.','A lace doily or stamp gives the surface a delicate pattern.']),
('en','paper'):dict(chip='Paper clay · no kiln needed',
 needs=['Paper clay or air-dry clay (approx. 400 g)','Rolling pin & 2 guide sticks (5 mm)','Sharp knife or craft knife','A drinking straw for the hole','Sponge & a little water','Acrylic paint, brush & matte varnish','Ribbon for hanging'],
 steps=['Roll out the clay 5 mm thick and smooth it.','Place the templates on top and cut out parts A and B.','Cut the slots exactly and punch the hole in part A with the straw.','Smooth the edges with a damp sponge. Test-fit the pieces once, then take them apart again.','Let the pieces dry flat for 24–72 hours, or in the oven following the clay maker’s instructions.','Paint with acrylics, seal with matte varnish, slot together and hang.'],
 tips=['If the ornament wobbles, add a drop of craft glue in the slot.','Chalk paint or gouache work too, always seal afterwards. Glazes need a kiln.','Fill small drying cracks with a little damp clay and let it dry again.']),
}
def step_svg(i):
    o=f'stroke="{k}" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"'
    if i==0: return f'<rect x="12" y="52" width="96" height="20" rx="4" fill="{b}"/><rect x="18" y="30" width="84" height="14" rx="7" fill="{s}" {o}/><path d="M8 37h10M102 37h10" {o}/><path d="M112 52v20" stroke="{r}" stroke-width="2"/><text x="113" y="66" font-size="8" fill="{r}">5</text>'
    if i==1: return f'<rect x="4" y="12" width="112" height="72" rx="6" fill="{b}"/><circle cx="38" cy="48" r="22" fill="{p}" stroke="{r}" stroke-width="1.6" stroke-dasharray="4 3"/><path transform="translate(84 50) scale(.42)" d="{star_path(46)}" fill="{p}" stroke="{r}" stroke-width="3.6" stroke-dasharray="9 7"/><path d="M104 18 l12 -14" stroke="{k}" stroke-width="3" stroke-linecap="round"/>'
    if i==2: return f'<circle cx="40" cy="48" r="30" fill="{b}"/><rect x="37" y="48" width="6" height="32" fill="#fff"/><circle cx="40" cy="26" r="3.5" fill="#fff"/><circle cx="88" cy="48" r="26" fill="{b}"/><rect x="85" y="20" width="6" height="28" fill="#fff"/>'
    if i==3: return f'<circle cx="60" cy="48" r="32" fill="{b}"/><ellipse cx="60" cy="48" rx="7" ry="32" fill="#C9A88F"/><path d="M98 24 a14 14 0 0 1 0 28" {o}/><path d="M94 52 l5 1 l-1 -5" {o}/>'
    if i==4: return f'<rect x="10" y="66" width="100" height="6" rx="3" fill="{k}"/><circle cx="36" cy="50" r="15" fill="{b}"/><circle cx="84" cy="50" r="15" fill="{b}"/><rect x="10" y="26" width="100" height="6" rx="3" fill="{k}" opacity=".5"/>'
    if i==5: return f'<path d="M16 10 Q60 30 104 10" {o}/><path d="M60 20 L60 32" stroke="{k}" stroke-width="1.4"/><circle cx="60" cy="56" r="24" fill="{r}"/><ellipse cx="60" cy="56" rx="5" ry="24" fill="#6E2216"/><rect x="55" y="29" width="10" height="6" rx="2" fill="#6E2216"/>'
exec(open(TPL+'css_snip.txt').read().replace('def build(lang):','def _css(lang):')+'    return css\n')
def foot(t,n): return f'<div class="pfoot"><img src="{R}img/forma-logo.png"><span>{t["foot"]}</span><span>{n}</span></div>'
def page(inner,cls=''): return f'<section class="page {cls}">{inner}</section>'
def build(lang,ver):
    TXT={lang:COMMON[lang]}; globals()['TXT']=TXT
    t=COMMON[lang]; v=VER[(lang,ver)]; css=_css(lang)+'.step svg{height:20mm!important;margin-bottom:2mm!important}.step p{font-size:9.6pt!important}.tips{margin-top:4mm!important}.tips .box{padding:4.5mm!important}.ver{background:'+r+'!important;color:'+p+'!important;border-color:'+r+'!important}'
    P=[]
    P.append(page(f'''<span class="blob" style="width:150mm;height:150mm;right:-60mm;top:-50mm;background:{b}"></span>
<span class="blob" style="width:90mm;height:90mm;left:-40mm;bottom:20mm;background:{s}"></span>
<span class="lang">{lang.upper()}</span>
<img class="logo" src="{R}img/forma-logo.png"><p class="kick">{t['sub']}</p><h1>{t['title']}</h1>
<div class="ic" style="height:92mm;width:130mm;margin:12mm 0 8mm">{icon()}</div>
<div class="chips"><span class="ver">{v['chip']}</span><span>{t['lvl']}</span><span>{t['size']}</span></div>
<p class="intro">{t['intro']}</p>''','cover'))
    steps=''.join(f'<div class="step"><svg viewBox="0 0 120 92">{step_svg(i)}</svg><b>{i+1}</b><p style="display:inline">{st}</p></div>' for i,st in enumerate(v['steps']))
    tips=''.join(f'<div class="box"><h3>{t["tip"]}</h3><p>{x}</p></div>' for x in v['tips'])
    P.append(page(f'''<div class="two"><div class="box"><h3>{t['youneed']}</h3><ul>{''.join(f'<li>{n}</li>' for n in v['needs'])}</ul></div>
<div><h3>{t['print_h']}</h3><p class="printnote">{t['print']}</p><div class="testsq" style="margin-top:6mm">{t['test']}</div></div></div>
<h2>{t['stepsT']}</h2><div class="steps">{steps}</div><div class="tips">{tips}</div>{foot(t,2)}'''))
    N=t['names']
    pA=piece('ball','A',6,30,'A',N['ball']+' A')+piece('ball','B',106,30,'B',N['ball']+' B')+piece('star','A',6,150,'A',N['star']+' A')+piece('star','B',106,150,'B',N['star']+' B')
    P.append(page(f'<div class="lbl"><h2 style="margin-bottom:1mm">{t["tA"]}</h2><p>{t["tA2"]}</p></div>{pA}{foot(t,3)}','tpl'))
    pB=piece('tree','A',6,30,'A',N['tree']+' A')+piece('tree','B',106,30,'B',N['tree']+' B')+piece('heart','A',6,158,'A',N['heart']+' A')+piece('heart','B',106,158,'B',N['heart']+' B')
    P.append(page(f'<div class="lbl"><h2 style="margin-bottom:1mm">{t["tB"]}</h2><p>{t["tA2"]}</p></div>{pB}<div style="position:absolute;left:0;right:0;bottom:16mm"><p class="share">{t["share"]}</p><p class="legal">{t["legal"]}</p></div>{foot(t,4)}','tpl'))
    return f'<!doctype html><html lang="{lang}"><meta charset="utf-8"><style>{css}</style><body>{"".join(P)}</body></html>'
FN={('de','stone'):'FORMA_Advent_01_3D-Anhaenger_Steinzeug_DE',('de','paper'):'FORMA_Advent_01_3D-Anhaenger_PaperClay_DE',
    ('en','stone'):'FORMA_Advent_01_3D-Ornaments_Stoneware_EN',('en','paper'):'FORMA_Advent_01_3D-Ornaments_PaperClay_EN'}
async def main():
    async with async_playwright() as pw:
        br=await pw.chromium.launch();pg=await br.new_page()
        for key,fn in FN.items():
            open(D+fn+'.html','w').write(build(*key))
            await pg.goto('file://'+D+fn+'.html');await pg.wait_for_timeout(800)
            await pg.pdf(path=D+fn+'.pdf',format='A4',print_background=True,prefer_css_page_size=True)
        await br.close()
asyncio.run(main())

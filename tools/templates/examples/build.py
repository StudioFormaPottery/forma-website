import asyncio,math,sys
from playwright.async_api import async_playwright
from geom import *
import os as _os
R='file://'+_os.path.abspath(_os.path.join(_os.path.dirname(_os.path.abspath(__file__)),'../../..'))+'/'
D=_os.getcwd()+'/'
r,b,k,p,s,ink='#8C2C1D','#DDBFA8','#674B40','#FBF1EA','#E4D2BF','#33211B'
RIBS=[(-24,-60,-36,0,-21,62),(24,-60,36,0,21,62),(-50,-44,-64,4,-48,52),(50,-44,64,4,48,52)]
def ribs(col,w,sc=1,dash=''):
    return ''.join(f'<path d="M{a*sc} {b_*sc} Q{c*sc} {d*sc} {e*sc} {f*sc}" fill="none" stroke="{col}" stroke-width="{w}" stroke-linecap="round" {dash}/>' for a,b_,c,d,e,f in RIBS)
# ---- Icon (Titelbild): Kürbisteller von oben
def icon(size_attr='width="100%" height="100%"'):
    return f'''<svg viewBox="-110 -112 220 205" {size_attr} xmlns="http://www.w3.org/2000/svg">
<ellipse cx="4" cy="86" rx="92" ry="8" fill="{k}" opacity=".18"/>
<path d="{path(OUT)}" fill="{r}"/>
<path d="{path(INNER)}" fill="#A8442F"/>
<g clip-path="url(#ci)">{ribs('#7A2618',3.2)}</g>
<clipPath id="ci"><path d="{path(INNER)}"/></clipPath>
<path d="{path(STEM_PTS)}" fill="{k}"/>
<g transform="translate(6 -84) rotate(-18) scale(.78)"><path d="{LEAF}" fill="{b}"/><path d="{LEAF_VEIN}" fill="none" stroke="{k}" stroke-width="2.2" stroke-linecap="round"/></g>
<path d="M-6 -86 c-14 -6 -22 4 -14 12 c6 6 14 -2 6 -6" fill="none" stroke="{k}" stroke-width="2.6" stroke-linecap="round"/>
<path d="M-40 -18 c10 -8 26 -10 36 -6" fill="none" stroke="{p}" stroke-width="5" stroke-linecap="round" opacity=".55"/>
</svg>'''
STEM_PTS=STEM
TXT={
'de':dict(title='Kürbisteller',sub='Slab-Template · Herbst',lvl='Leicht · ca. 1–2 Stunden',size='Größe ca. 18 × 16 cm (vor dem Brennen)',
 intro='Ein flacher Teller in Kürbisform mit sanft hochgebogenem Rand, kleinem Stiel und Blatt. Perfekt für Kekse, Herbstdeko oder als Frühstücksteller.',
 youneed='Das brauchst du',needs=['Ton (ca. 600 g)','Nudelholz & 2 Leisten (5–6 mm)','Nadel oder Cuttermesser','Schwamm & Rippe','Schaumstoff oder ein gefaltetes Handtuch','Schlicker & Modellierholz'],
 print='Drucke die Schablonen in Originalgröße (100 %, „Tatsächliche Größe“). Das Testquadrat auf Seite 3 muss genau 5 × 5 cm messen.',
 steps=['Ton 5–6 mm dick ausrollen und mit der Rippe glätten.','Schablone A auflegen und mit der Nadel rundherum ausschneiden.','Kanten mit feuchtem Schwamm oder Finger weich abrunden.','Platte auf Schaumstoff legen, Schablone B in die Mitte legen und die Mitte sanft flach drücken. Der Rand hebt sich nach oben.','Rillen von Einbuchtung zu Einbuchtung ziehen. Blatt C ausschneiden, Adern ritzen und mit Schlicker am Stiel anbringen.','Abgedeckt langsam trocknen lassen, dann schrühen, glasieren und brennen.'],
 stepsT='So geht’s',tA='Schablone A · Tellerform',tA2='Ausschneiden entlang der durchgezogenen Linie. Gestrichelt: hier beginnt der hochgebogene Rand.',
 tB='Schablone B · Tellermitte',tB2='Zum Flachdrücken der Mitte, so hebt sich der Rand gleichmäßig.',tC='Schablone C · Blatt',tC2='Für den Stiel. Gern zwei Blätter, eins größer, eins kleiner.',
 test='Testquadrat 5 × 5 cm',tip='Tipp',tips=['Der Rand bleibt schön gleichmäßig, wenn du ihn beim Antrocknen mit kleinen Röllchen aus Küchenpapier stützt.','Ton schrumpft beim Trocknen und Brennen um etwa 10–12 %.','Glasur-Idee: Rand und Stiel dunkel, Mitte hell, die Rillen werden dann besonders schön sichtbar.'],
 share='Zeig mir deinen Kürbisteller! Markiere @forma.potteryartcraft',legal='© FORMA Earth & Art · Nur für den privaten Gebrauch. Weitergabe und Verkauf der Schablone sind nicht erlaubt. Selbstgemachte Stücke darfst du natürlich verschenken.'),
'en':dict(title='Pumpkin Plate',sub='Slab template · Autumn',lvl='Easy · approx. 1–2 hours',size='Size approx. 18 × 16 cm (before firing)',
 intro='A flat pumpkin-shaped plate with a gently raised rim, a little stem and a leaf. Perfect for cookies, autumn decor or as a breakfast plate.',
 youneed='You will need',needs=['Clay (approx. 600 g)','Rolling pin & 2 guide sticks (5–6 mm)','Needle tool or craft knife','Sponge & rib','Foam or a folded towel','Slip & wooden modelling tool'],
 print='Print the templates at full size (100 %, “Actual size”). The test square on page 3 must measure exactly 5 × 5 cm.',
 steps=['Roll out the clay 5–6 mm thick and smooth it with the rib.','Place template A on the slab and cut around it with the needle tool.','Soften the edges with a damp sponge or your finger.','Lay the slab on foam, place template B in the centre and gently press the middle flat. The rim rises up.','Draw grooves from dip to dip. Cut out leaf C, score the veins and attach it to the stem with slip.','Cover loosely and let it dry slowly, then bisque fire, glaze and fire again.'],
 stepsT='How it works',tA='Template A · Plate shape',tA2='Cut along the solid line. Dashed: this is where the raised rim begins.',
 tB='Template B · Plate centre',tB2='For pressing the middle flat so the rim rises evenly.',tC='Template C · Leaf',tC2='For the stem. Try two leaves, one larger and one smaller.',
 test='Test square 5 × 5 cm',tip='Tip',tips=['The rim stays nice and even if you support it with small rolls of kitchen paper while it firms up.','Clay shrinks by about 10–12 % when drying and firing.','Glaze idea: dark rim and stem, light centre, so the grooves really stand out.'],
 share='Show me your pumpkin plate! Tag @forma.potteryartcraft',legal='© FORMA Earth & Art · For personal use only. Sharing or selling this template is not permitted. You are of course welcome to gift the pieces you make.'),
}
def step_svg(i):
    o=f'stroke="{k}" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"'
    sm=path(OUT,.32,.32,60,48); si=path(INNER,.32,.32,60,48)
    if i==0: return f'<rect x="12" y="52" width="96" height="20" rx="4" fill="{b}"/><rect x="18" y="30" width="84" height="14" rx="7" fill="{s}" {o}/><path d="M8 37h10M102 37h10" {o}/><path d="M112 52v20" stroke="{r}" stroke-width="2"/><text x="116" y="66" font-size="9" fill="{r}">5–6</text>'
    if i==1: return f'<rect x="4" y="12" width="112" height="78" rx="6" fill="{b}"/><path d="{sm}" fill="{p}" stroke="{r}" stroke-width="1.6" stroke-dasharray="4 3"/><path d="M98 20 l12 -14" stroke="{k}" stroke-width="3" stroke-linecap="round"/>'
    if i==2: return f'<path d="{sm}" fill="{b}" stroke="{k}" stroke-width="1.2"/><rect x="74" y="58" width="30" height="20" rx="8" fill="#E9CFA0" stroke="{k}" stroke-width="1.5" transform="rotate(-15 89 68)"/><path d="M84 52c0-6 6-6 6 0" {o}/>'
    if i==3: return f'<rect x="4" y="66" width="112" height="16" rx="5" fill="{s}" stroke="{k}" stroke-width="1.2"/><path d="M10 50 Q14 64 30 64 L90 64 Q106 64 110 50" fill="none" stroke="{r}" stroke-width="5" stroke-linecap="round"/><rect x="34" y="56" width="52" height="6" rx="2" fill="{k}" opacity=".5"/><path d="M60 20v26M52 38l8 8 8-8" {o}/><path d="M14 44l-4-8M106 44l4-8" stroke="{r}" stroke-width="2" stroke-linecap="round"/>'
    if i==4: return f'<path d="{sm}" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="{si}" fill="none" stroke="{k}" stroke-width=".8" stroke-dasharray="2 2"/><g>{"".join(f"<path d=\"M{a*.32+60} {b_*.32+48} Q{c*.32+60} {d*.32+48} {e*.32+60} {f*.32+48}\" fill=\"none\" stroke=\"{r}\" stroke-width=\"1.6\" stroke-linecap=\"round\"/>" for a,b_,c,d,e,f in RIBS)}</g><g transform="translate(64 20) rotate(-20) scale(.42)"><path d="{LEAF}" fill="{p}" stroke="{k}" stroke-width="3"/><path d="{LEAF_VEIN}" {o} stroke-width="3"/></g>'
    if i==5: return f'<path d="{sm}" fill="{b}" stroke="{k}" stroke-width="1.2"/><path d="M14 74 Q14 6 60 6 Q106 6 106 74" fill="none" stroke="{k}" stroke-width="1.6" stroke-dasharray="5 4"/><path d="M96 24c3 4 3 7 0 9-3-2-3-5 0-9z" fill="{k}" opacity=".6"/><path d="M26 30c3 4 3 7 0 9-3-2-3-5 0-9z" fill="{k}" opacity=".6"/>'
def page_wrap(inner,cls=''): return f'<section class="page {cls}">{inner}</section>'
def foot(t,n): return f'<div class="pfoot"><img src="{R}img/forma-logo.png"><span>{t["title"]} · FORMA Earth &amp; Art</span><span>{n}</span></div>'
def build(lang):
    t=TXT[lang]
    css=f'''@font-face{{font-family:B;font-weight:600 800;src:url({R}fonts/BricolageGrotesque-Bold.woff)}}
@font-face{{font-family:I;src:url({R}fonts/Italiana-Regular.woff)}}
@font-face{{font-family:O;font-weight:300 500;src:url({R}fonts/Outfit-Regular.woff)}}
@font-face{{font-family:O;font-weight:600 800;src:url({R}fonts/Outfit-Bold.woff)}}
@page{{size:A4;margin:0}}*{{box-sizing:border-box;margin:0}}
body{{font-family:O;color:{ink};-webkit-print-color-adjust:exact;print-color-adjust:exact}}
.page{{width:210mm;height:297mm;position:relative;overflow:hidden;page-break-after:always;padding:16mm 16mm 20mm;background:#fff}}
.pfoot{{position:absolute;left:16mm;right:16mm;bottom:8mm;display:flex;align-items:center;gap:3mm;font-size:8pt;color:{k}}}
.pfoot img{{width:7mm}}.pfoot span:last-child{{margin-left:auto}}
h2{{font-family:B;font-weight:650;font-size:22pt;color:{r};letter-spacing:-.01em;margin-bottom:4mm}}
h3{{font-family:B;font-weight:600;font-size:13pt;color:{r};margin-bottom:1.5mm}}
.cover{{background:#EEE2D4;display:flex;flex-direction:column;align-items:center;text-align:center;padding-top:22mm}}
.cover .blob{{position:absolute;border-radius:50%}}
.cover .logo{{width:26mm;position:relative}}
.cover .kick{{font-family:I;font-size:18pt;color:{k};margin-top:8mm;position:relative}}
.cover h1{{font-family:B;font-weight:650;font-size:46pt;line-height:1;letter-spacing:-.02em;color:{r};margin-top:2mm;position:relative}}
.cover .ic{{width:120mm;height:112mm;margin:12mm 0 6mm;position:relative}}
.cover .chips{{display:flex;gap:3mm;flex-wrap:wrap;justify-content:center;position:relative}}
.cover .chips span{{background:{p};border:1px solid {b};border-radius:99px;padding:2mm 5mm;font-size:10.5pt;color:{k}}}
.cover .intro{{max-width:140mm;font-size:11.5pt;line-height:1.55;color:{k};margin-top:8mm;position:relative}}
.cover .lang{{position:absolute;right:16mm;top:16mm;font-family:B;font-weight:600;font-size:10pt;color:{p};background:{r};border-radius:99px;padding:1.5mm 4mm}}
.two{{display:grid;grid-template-columns:1fr 1.15fr;gap:10mm;margin-bottom:5mm}}
.box{{background:#F6EEE6;border-radius:5mm;padding:6mm}}
.box ul{{padding-left:5mm;font-size:10.5pt;line-height:1.7}}
.printnote{{font-size:10.5pt;line-height:1.55;color:{k}}}
.testsq{{width:50mm;height:50mm;border:.4mm solid {r};display:grid;place-items:center;font-size:9pt;color:{r};text-align:center;margin-top:5mm}}
.steps{{display:grid;grid-template-columns:repeat(3,1fr);gap:6mm}}
.step{{background:#F6EEE6;border-radius:5mm;padding:4mm 4mm 5mm}}
.step svg{{width:100%;height:26mm;display:block;margin-bottom:3mm}}
.step b{{display:inline-grid;place-items:center;width:7mm;height:7mm;border-radius:50%;background:{r};color:{p};font-family:B;font-size:10pt;margin-right:2mm;vertical-align:middle}}
.step p{{font-size:10pt;line-height:1.5;margin-top:2mm}}
.tips{{display:grid;grid-template-columns:repeat(3,1fr);gap:6mm;margin-top:6mm}}
.tips p{{font-size:9.5pt;line-height:1.5;color:{k}}}
.tpl{{padding:10mm 10mm 14mm}}
.tpl .lbl{{position:absolute;left:10mm;top:8mm;right:10mm}}
.tpl .lbl p{{font-size:9pt;color:{k}}}
.share{{margin-top:10mm;text-align:center;font-family:B;font-weight:600;font-size:12pt;color:{r}}}
.legal{{margin-top:3mm;text-align:center;font-size:8pt;color:{k}}}
'''
    pages=[]
    pages.append(page_wrap(f'''<span class="blob" style="width:150mm;height:150mm;right:-60mm;top:-50mm;background:{b}"></span>
<span class="blob" style="width:90mm;height:90mm;left:-40mm;bottom:20mm;background:{s}"></span>
<span class="lang">{'DE' if lang=='de' else 'EN'}</span>
<img class="logo" src="{R}img/forma-logo.png"><p class="kick">{t['sub']}</p><h1>{t['title']}</h1>
<div class="ic">{icon()}</div>
<div class="chips"><span>{t['lvl']}</span><span>{t['size']}</span></div>
<p class="intro">{t['intro']}</p>''','cover'))
    steps=''.join(f'<div class="step"><svg viewBox="0 0 120 92">{step_svg(i)}</svg><b>{i+1}</b><p style="display:inline">{st}</p></div>' for i,st in enumerate(t['steps']))
    tips=''.join(f'<div class="box"><h3>{t["tip"]}</h3><p>{x}</p></div>' for x in t['tips'])
    pages.append(page_wrap(f'''<div class="two"><div class="box"><h3>{t['youneed']}</h3><ul>{''.join(f'<li>{n}</li>' for n in t['needs'])}</ul></div>
<div><h3>{'Drucken' if lang=='de' else 'Printing'}</h3><p class="printnote">{t['print']}</p></div></div>
<h2>{t['stepsT']}</h2><div class="steps">{steps}</div><div class="tips">{tips}</div>{foot(t,2)}'''))
    # Schablone A: Originalgröße, mittig
    x0,y0,x1,y1=bbox(OUT); w=x1-x0; h=y1-y0
    A=f'''<svg width="{w+6}mm" height="{h+6}mm" viewBox="{x0-3} {y0-3} {w+6} {h+6}" style="position:absolute;left:{(210-w-6)/2}mm;top:46mm">
<path d="{path(OUT)}" fill="none" stroke="{ink}" stroke-width=".5"/>
<path d="{path(INNER)}" fill="none" stroke="{r}" stroke-width=".35" stroke-dasharray="2.5 1.8"/>
<text x="0" y="8" text-anchor="middle" font-family="B" font-size="16" fill="{b}" font-weight="700">A</text>
<g opacity=".7">{ribs(b,.5)}</g></svg>'''
    pages.append(page_wrap(f'''<div class="lbl"><h2 style="margin-bottom:1mm">{t['tA']}</h2><p>{t['tA2']}</p></div>{A}<div class="testsq" style="position:absolute;right:16mm;bottom:22mm;margin:0">{t['test']}</div>{foot(t,3)}''','tpl'))
    ix0,iy0,ix1,iy1=bbox(INNER);iw=ix1-ix0;ih=iy1-iy0
    B=f'''<svg width="{iw+6}mm" height="{ih+6}mm" viewBox="{ix0-3} {iy0-3} {iw+6} {ih+6}" style="position:absolute;left:{(210-iw-6)/2}mm;top:40mm">
<path d="{path(INNER)}" fill="none" stroke="{ink}" stroke-width=".5"/><text x="0" y="6" text-anchor="middle" font-family="B" font-size="16" fill="{b}">B</text></svg>'''
    C=f'''<svg width="66mm" height="34mm" viewBox="-4 -24 66 34" style="position:absolute;left:30mm;top:{40+ih+22}mm">
<path d="{LEAF}" fill="none" stroke="{ink}" stroke-width=".5"/><path d="{LEAF_VEIN}" fill="none" stroke="{b}" stroke-width=".5" stroke-dasharray="1.5 1.2"/><text x="26" y="-1" font-family="B" font-size="7" fill="{b}">C</text></svg>
<svg width="48mm" height="26mm" viewBox="-4 -18 48 26" style="position:absolute;left:110mm;top:{40+ih+26}mm">
<g transform="scale(.7)"><path d="{LEAF}" fill="none" stroke="{ink}" stroke-width=".7"/><path d="{LEAF_VEIN}" fill="none" stroke="{b}" stroke-width=".7" stroke-dasharray="2 1.6"/></g></svg>'''
    pages.append(page_wrap(f'''<div class="lbl"><h2 style="margin-bottom:1mm">{t['tB']}</h2><p>{t['tB2']}</p></div>{B}
<div style="position:absolute;left:10mm;right:10mm;top:{40+ih+10}mm"><h2 style="margin-bottom:1mm">{t['tC']}</h2><p style="font-size:9pt;color:{k}">{t['tC2']}</p></div>{C}
<div style="position:absolute;left:16mm;right:16mm;bottom:20mm"><p class="share">{t['share']}</p><p class="legal">{t['legal']}</p></div>{foot(t,4)}''','tpl'))
    return f'<!doctype html><html lang="{lang}"><meta charset="utf-8"><style>{css}</style><body>{"".join(pages)}</body></html>'
async def main():
    async with async_playwright() as pw:
        br=await pw.chromium.launch();pg=await br.new_page()
        for lang,fn in [('de','FORMA_Kuerbisteller_Template_DE'),('en','FORMA_Pumpkin_Plate_Template_EN')]:
            open(D+fn+'.html','w').write(build(lang))
            await pg.goto('file://'+D+fn+'.html');await pg.wait_for_timeout(800)
            await pg.pdf(path=D+fn+'.pdf',format='A4',print_background=True,prefer_css_page_size=True)
        # Vorschaubild für den Shop (4:3) – nur das kleine Icon
        html=f'<!doctype html><meta charset=utf-8><body style="margin:0;width:1200px;height:900px;background:#EEE2D4;display:grid;place-items:center;position:relative;overflow:hidden"><span style="position:absolute;width:620px;height:620px;border-radius:50%;background:{b};right:-160px;top:-200px"></span><span style="position:absolute;width:300px;height:300px;border-radius:50%;background:{s};left:-90px;bottom:-60px"></span><div style="width:620px;height:580px;position:relative">{icon()}</div></body>'
        open(D+'preview.html','w').write(html)
        p2=await br.new_page(viewport={'width':1200,'height':900});await p2.goto('file://'+D+'preview.html');await p2.wait_for_timeout(300);await p2.screenshot(path=D+'Kuerbisteller_Vorschau.jpg',type='jpeg',quality=88)
        await br.close()
asyncio.run(main())

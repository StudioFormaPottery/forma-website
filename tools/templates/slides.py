import asyncio, os, subprocess, base64, sys
from playwright.async_api import async_playwright
REPO='/home/claude/repo-forma-website/'
OUT=REPO+'img/shop/'
os.makedirs(OUT,exist_ok=True)
SRC={'kuerbisteller':REPO+'api/seed/kuerbisteller-de.pdf','teetasse':REPO+'api/seed/teetasse-de.pdf'}
for k in ['fledermaus-wandteelicht','hexenhut-windlicht','gespenst-windlicht','kids-anhaenger','kids-3d-anhaenger']:
    SRC[k]=REPO+f'api/seed/weekly/{k}-de.pdf'
if len(sys.argv)>1: SRC={k:v for k,v in SRC.items() if k in sys.argv[1:]}
r,b,k_,p,s='#8C2C1D','#DDBFA8','#674B40','#FBF1EA','#E4D2BF'
f64=lambda n:'data:font/woff;base64,'+base64.b64encode(open(REPO+'fonts/'+n,'rb').read()).decode()
CSS=f"@font-face{{font-family:B;src:url({f64('BricolageGrotesque-Bold.woff')})}}@font-face{{font-family:O;src:url({f64('Outfit-Regular.woff')})}}@font-face{{font-family:I;src:url({f64('Italiana-Regular.woff')})}}"
LAB={2:('Bild-Anleitung','Schritt für Schritt mit kleinen Skizzen, Materialliste und Tipps'),3:('Schablonen','in Originalgröße zum Ausdrucken, mit Testquadrat')}
def slide(img,n):
    h,t=LAB[n]
    return f'''<html><head><style>{CSS}*{{margin:0}}body{{width:1200px;height:900px;background:#EEE2D4;position:relative;overflow:hidden;font-family:O;color:#33211B}}
.b1{{position:absolute;width:560px;height:560px;border-radius:50%;background:{b};right:-200px;top:-240px}}.b2{{position:absolute;width:320px;height:320px;border-radius:50%;background:{s};left:-110px;bottom:-110px}}
.pg{{position:absolute;left:110px;top:60px;width:552px;height:780px;transform:rotate(-3deg);border-radius:10px;overflow:hidden;background:#fff;outline:10px solid #FBF1EA}}
.pg img{{width:100%;height:100%;object-fit:cover;object-position:top}}
.tx{{position:absolute;left:730px;top:300px;width:400px}}.k{{font-family:I;font-size:40px;color:{k_}}}.h{{font-family:B;font-size:54px;white-space:nowrap;line-height:1;color:{r};margin-top:8px}}.t{{font-size:28px;line-height:1.4;color:{k_};margin-top:18px}}
.n{{display:inline-block;margin-top:26px;font-family:B;font-size:22px;color:{p};background:{r};border-radius:99px;padding:8px 20px}}</style></head>
<body><span class="b1"></span><span class="b2"></span><div class="pg"><img src="data:image/png;base64,{img}"></div>
<div class="tx"><p class="k">Ein Blick ins Template</p><p class="h">{h}</p><p class="t">{t}</p><span class="n">Deutsch &amp; English</span></div></body></html>'''
async def main():
    async with async_playwright() as pw:
        br=await pw.chromium.launch();pg=await br.new_page(viewport={'width':1200,'height':900})
        for key,pdf in SRC.items():
            for n in (2,3):
                subprocess.run(['pdftoppm','-r','110','-f',str(n),'-l',str(n),'-png','-singlefile',pdf,f'/tmp/sl_{key}_{n}'],check=True)
                img=base64.b64encode(open(f'/tmp/sl_{key}_{n}.png','rb').read()).decode()
                await pg.set_content(slide(img,n));await pg.evaluate('document.fonts.ready');await pg.wait_for_timeout(250)
                await pg.screenshot(path=f'{OUT}{key}-{n}.jpg',type='jpeg',quality=84)
        await br.close()
asyncio.run(main())

"""Story-/Status-Bilder (1080 × 1920) im FORMA-Stil für Instagram-Story und WhatsApp-Status.
Aufruf (im Repo-Ordner):  python3 tools/social/make_story.py stories.json <ausgabeordner>
stories.json = Liste von {"file", "photo", "pos", "pill", "title", "text", "cta", "seal": {...} | null}
Oben 220 px und unten 300 px bleiben frei (Instagram-Leiste, Link-Sticker, WhatsApp-Antwortfeld)."""
import asyncio, json, re, sys
from pathlib import Path
from playwright.async_api import async_playwright

REPO = Path(__file__).resolve().parents[2]
F = REPO.as_uri() + '/fonts/'
IMG = REPO.as_uri() + '/img/'
STAR = 'M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z'
HEART = 'M12 20.5c-1.2-1.1-8.5-6.2-8.5-11.4C3.5 6 5.6 4 8 4c1.9 0 3.2 1.1 4 2.6C12.8 5.1 14.1 4 16 4c2.4 0 4.5 2 4.5 5.1 0 5.2-7.3 10.3-8.5 11.4z'
VERBOTEN = {'vivien-toepfern.jpg', 'drehen.jpg', 'vivien.jpg', 'vivien-mit-tochter.jpg'}
_src = (REPO / 'index.html').read_text(encoding='utf-8')
HOUSE = re.search(r'<svg class="adv-house".*?</svg>', _src, re.S).group(0)

CSS = f'''
@font-face{{font-family:"Bricolage Grotesque";font-weight:600 800;src:url({F}BricolageGrotesque-Bold.woff)}}
@font-face{{font-family:"Bricolage Grotesque";font-weight:300 599;src:url({F}BricolageGrotesque-Regular.woff)}}
@font-face{{font-family:"Outfit";font-weight:300 500;src:url({F}Outfit-Regular.woff)}}
@font-face{{font-family:"Italiana";src:url({F}Italiana-Regular.woff)}}
*{{box-sizing:border-box;margin:0}}
html,body{{width:1080px;height:1920px}}
body{{position:relative;overflow:hidden;font-family:Outfit,sans-serif;color:#33211B;
 background:radial-gradient(circle at 20% 40%,rgba(251,241,234,.55) 0 3px,transparent 4px) 0 0/64px 64px,#DDBFA8}}
.wrap{{position:absolute;left:0;right:0;top:220px;bottom:300px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:30px;text-align:center;padding:0 90px}}
.arch{{position:relative;width:640px;height:820px;flex:none}}
.arch.wide{{width:760px;height:760px}}
.pic{{width:100%;height:100%;border-radius:999px 999px 36px 36px;overflow:hidden;border:12px solid #FBF1EA;box-shadow:0 24px 50px rgba(51,33,27,.22)}}
.wide .pic{{border-radius:44px}}
.pic img{{width:100%;height:100%;object-fit:cover;display:block}}
.pill{{display:inline-flex;align-items:center;gap:12px;padding:13px 30px 13px 24px;border-radius:999px;background:#8C2C1D;color:#FBF1EA;font-size:27px;letter-spacing:.16em;text-transform:uppercase}}
.pill svg{{width:22px;height:22px;fill:#DDBFA8}}
h1{{font-family:"Bricolage Grotesque";font-weight:600;font-size:86px;line-height:1.02;letter-spacing:-.02em;color:#8C2C1D}}
p{{font-size:38px;line-height:1.35;color:#674B40;max-width:880px}}
.cta{{display:inline-flex;padding:20px 44px;border-radius:999px;background:#FBF1EA;color:#8C2C1D;font-size:34px;border:2.5px dashed rgba(140,44,29,.45)}}
.seal{{position:absolute;right:-60px;bottom:-40px;width:200px;height:200px;border-radius:50%;background:#8C2C1D;color:#FBF1EA;display:flex;flex-direction:column;align-items:center;justify-content:center;transform:rotate(-10deg);box-shadow:0 10px 24px rgba(51,33,27,.3)}}
.seal svg{{position:absolute;inset:0;width:100%;height:100%}}
.seal text{{font-family:Outfit;font-size:11px;letter-spacing:.12em;text-transform:uppercase;fill:#FBF1EA}}
.seal b{{font-family:"Bricolage Grotesque";font-weight:600;font-size:62px;line-height:1}}
.seal i{{font-style:normal;font-size:24px}}
.logo{{position:absolute;right:56px;top:150px;width:110px}}
.d{{position:absolute;width:34px;height:34px}}
.house svg{{width:560px;height:auto;overflow:visible;filter:drop-shadow(0 18px 26px rgba(51,33,27,.2))}}
.adv-n{{font-family:"Bricolage Grotesque";font-weight:600;font-size:15px;fill:#8C2C1D}}
'''


def esc(t):
    return (t or '').replace('&', '&amp;').replace('<', '&lt;')


def page(s):
    photo = s.get('photo', '')
    if Path(photo).name in VERBOTEN:
        raise SystemExit(f'{photo} nicht für Werbung verwenden.')
    if 'keramik' in json.dumps(s, ensure_ascii=False).lower():
        raise SystemExit('Das Wort „Keramik“ darf nicht vorkommen.')
    seal = ''
    if s.get('seal'):
        z = s['seal']
        seal = (f'<span class="seal"><svg viewBox="0 0 120 120"><defs><path id="c" d="M60 60m-42 0a42 42 0 1 1 84 0a42 42 0 1 1-84 0"/></defs>'
                f'<text><textPath href="#c" textLength="258" lengthAdjust="spacingAndGlyphs">{esc(z.get("ring", ""))}</textPath></text></svg>'
                f'<b>{esc(z.get("big", ""))}</b><i>{esc(z.get("small", ""))}</i></span>')
    if s.get('house'):
        art = f'<div class="house">{HOUSE}</div>'
    else:
        art = (f'<div class="arch{" wide" if s.get("wide") else ""}"><div class="pic"><img src="{(REPO / photo).as_uri()}" style="object-position:{s.get("pos", "50% 50%")}"></div>{seal}</div>')
    deco = (f'<svg class="d" viewBox="0 0 24 24" style="left:70px;top:260px;fill:#8C2C1D"><path d="{HEART}"/></svg>'
            f'<svg class="d" viewBox="0 0 24 24" style="right:90px;top:640px;fill:#FBF1EA;width:44px;height:44px"><path d="{STAR}"/></svg>'
            f'<svg class="d" viewBox="0 0 24 24" style="left:96px;bottom:430px;fill:#FBF1EA;width:26px;height:26px"><path d="{STAR}"/></svg>')
    star = f'<svg viewBox="0 0 24 24"><path d="{STAR}"/></svg>'
    body = (f'<div class="wrap">{art}'
            + ((f'<span class="pill" style="margin-top:34px">' if s.get('seal') else '<span class="pill">') + f'{star}{esc(s["pill"])}</span>' if s.get('pill') else '')
            + (f'<h1>{esc(s["title"]).replace("|", "<br>")}</h1>' if s.get('title') else '')
            + (f'<p>{esc(s["text"])}</p>' if s.get('text') else '')
            + (f'<span class="cta">{esc(s["cta"])}</span>' if s.get('cta') else '')
            + '</div>')
    return f'<!doctype html><html><head><meta charset="utf-8"><style>{CSS}</style></head><body><img class="logo" src="{IMG}forma-logo.png">{deco}{body}</body></html>'


async def main():
    stories = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
    out = Path(sys.argv[2]).resolve()
    out.mkdir(parents=True, exist_ok=True)
    async with async_playwright() as p:
        b = await p.chromium.launch()
        for s in stories:
            tmp = out / (s['file'] + '.html')
            tmp.write_text(page(s), encoding='utf-8')
            pg = await b.new_page(viewport={'width': 1080, 'height': 1920})
            await pg.goto(tmp.as_uri(), wait_until='networkidle')
            await pg.evaluate('document.fonts.ready')
            await pg.wait_for_timeout(250)
            await pg.screenshot(path=str(out / s['file']))
            await pg.close()
            tmp.unlink()
            print('ok', s['file'])
        await b.close()

asyncio.run(main())

"""Pinterest-Pin (1000 × 1500) im FORMA-Stil aus einer JSON-Beschreibung bauen.
Aufruf (im Repo-Ordner):  python3 tools/pinterest/make_pin.py pin.json
Felder siehe tools/pinterest/README.md. Ergebnis: JPG unter dem Pfad aus "file"."""
import asyncio, math, re, sys
from pathlib import Path
from playwright.async_api import async_playwright

import json
REPO = Path(__file__).resolve().parents[2]
html_src = (REPO / 'index.html').read_text(encoding='utf-8')
HOUSE = re.search(r'<svg class="adv-house".*?</svg>', html_src, re.S).group(0)
GARLAND = re.search(r'<svg class="xm-garland".*?</svg>', html_src, re.S).group(0)
STAR = 'M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z'
F = REPO.as_uri() + '/fonts/'
IMG = REPO.as_uri() + '/img/'


def wave_rect(w, h, inset=22, r=36, amp=4, step=22):
    """Welliger Rahmen wie auf der Website (Maker-Welle)."""
    x0, y0, x1, y1 = inset, inset, w - inset, h - inset
    pts = []
    def edge(ax, ay, bx, by):
        L = math.hypot(bx - ax, by - ay); n = max(2, int(L / step))
        nx, ny = (by - ay) / L, -(bx - ax) / L
        for i in range(n):
            t = i / n; off = math.sin(t * n * math.pi) * amp
            pts.append((ax + (bx - ax) * t + nx * off, ay + (by - ay) * t + ny * off))
    edge(x0 + r, y0, x1 - r, y0); pts.append((x1, y0 + r)) if False else None
    d = f'M{x0 + r} {y0}'
    segs = [((x0 + r, y0), (x1 - r, y0), (x1, y0), (x1, y0 + r)),
            ((x1, y0 + r), (x1, y1 - r), (x1, y1), (x1 - r, y1)),
            ((x1 - r, y1), (x0 + r, y1), (x0, y1), (x0, y1 - r)),
            ((x0, y1 - r), (x0, y0 + r), (x0, y0), (x0 + r, y0))]
    for (a, b, c, e) in segs:
        L = math.hypot(b[0] - a[0], b[1] - a[1]); n = max(2, int(L / step))
        nx, ny = (b[1] - a[1]) / L, -(b[0] - a[0]) / L
        for i in range(1, n + 1):
            t = i / n; off = math.sin(t * n * math.pi) * amp
            d += f' L{a[0] + (b[0] - a[0]) * t + nx * off:.1f} {a[1] + (b[1] - a[1]) * t + ny * off:.1f}'
        d += f' Q{c[0]} {c[1]} {e[0]} {e[1]}'
    return f'<svg class="wave" viewBox="0 0 {w} {h}" aria-hidden="true"><path d="{d} Z"/></svg>'


BASE_CSS = f'''
@font-face{{font-family:"Bricolage Grotesque";font-weight:600 800;src:url({F}BricolageGrotesque-Bold.woff) format("woff")}}
@font-face{{font-family:"Bricolage Grotesque";font-weight:300 599;src:url({F}BricolageGrotesque-Regular.woff) format("woff")}}
@font-face{{font-family:"Outfit";font-weight:300 500;src:url({F}Outfit-Regular.woff) format("woff")}}
@font-face{{font-family:"Outfit";font-weight:600 800;src:url({F}Outfit-Bold.woff) format("woff")}}
*{{box-sizing:border-box;margin:0}}
:root{{--paper:#EEE2D4;--blush:#DDBFA8;--clay:#8C2C1D;--ink:#33211B;--soft:#674B40;--cream:#FBF1EA}}
html,body{{width:var(--w);height:var(--h)}}
body{{position:relative;overflow:hidden;font-family:Outfit,sans-serif;color:var(--ink);
  background:radial-gradient(circle at 20% 40%,rgba(251,241,234,.55) 0 3px,transparent 4px) 0 0/64px 64px,radial-gradient(circle at 70% 75%,rgba(251,241,234,.4) 0 2px,transparent 3px) 0 0/50px 50px,var(--blush)}}
.wave{{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}}
.wave path{{fill:none;stroke:var(--clay);stroke-width:3;opacity:.45}}
.xm-garland{{position:absolute;left:44px;right:44px;top:30px;width:calc(100% - 88px);height:calc(var(--gh,110px))}}
.logo{{position:absolute;right:52px;top:calc(var(--gh,110px) + 22px);width:96px;height:96px}}
.stack{{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:var(--pad-top,170px) 80px 90px}}
.big .txt{{font-size:36px}}.big .chips span{{font-size:29px;padding:12px 26px}}.big .cta{{font-size:36px;padding:24px 48px}}.big .url{{font-size:30px}}.big .pill{{font-size:27px}}
.pill{{display:inline-flex;align-items:center;gap:12px;padding:12px 28px 12px 22px;border-radius:999px;background:var(--clay);color:var(--cream);font-size:24px;font-weight:500;letter-spacing:.16em;text-transform:uppercase}}
.pill svg{{width:22px;height:22px;fill:var(--blush)}}
h1{{font-family:"Bricolage Grotesque",sans-serif;font-weight:600;color:var(--clay);letter-spacing:-.02em;line-height:1}}
.sub{{font-family:"Bricolage Grotesque",sans-serif;font-weight:500;color:var(--soft);line-height:1.2}}
.txt{{font-size:30px;line-height:1.45;max-width:820px}}
.cta{{display:inline-flex;align-items:center;gap:14px;padding:20px 40px;border-radius:999px;background:var(--clay);color:var(--cream);font-size:30px;font-weight:500;box-shadow:0 10px 24px rgba(51,33,27,.18)}}
.url{{font-size:26px;color:var(--clay);font-weight:500;letter-spacing:.04em}}
.arch{{position:relative}}
.arch .pic{{width:100%;height:100%;border-radius:999px 999px 34px 34px;overflow:hidden;border:10px solid var(--cream);box-shadow:0 22px 44px rgba(51,33,27,.22)}}
.arch .pic img{{width:100%;height:100%;object-fit:cover;display:block}}
.seal{{position:absolute;border-radius:50%;background:var(--clay);color:var(--cream);display:flex;flex-direction:column;align-items:center;justify-content:center;transform:rotate(-10deg);box-shadow:0 10px 24px rgba(51,33,27,.3)}}
.seal svg{{position:absolute;inset:0;width:100%;height:100%}}
.seal text{{font-family:Outfit;font-size:11px;letter-spacing:.12em;text-transform:uppercase;fill:var(--cream);font-weight:500}}
.seal b{{font-family:"Bricolage Grotesque";font-weight:600;line-height:1}}
.seal i{{font-style:normal;letter-spacing:.06em}}
.dates{{display:flex;gap:16px;justify-content:center}}
.dates span{{border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--cream);border:2.5px dashed rgba(140,44,29,.45);color:var(--clay);line-height:1.05}}
.dates span:nth-child(odd){{transform:rotate(-4deg)}}.dates span:nth-child(even){{transform:rotate(3deg)}}
.dates b{{font-family:"Bricolage Grotesque";font-weight:600}}
.dates i{{font-style:normal;letter-spacing:.08em;text-transform:uppercase;color:var(--soft)}}
.chips{{display:flex;flex-wrap:wrap;gap:12px;justify-content:center}}
.chips span{{display:inline-flex;align-items:center;gap:10px;padding:10px 22px;border-radius:999px;background:rgba(238,226,212,.75);border:1.5px dashed rgba(140,44,29,.4);color:var(--clay);font-size:25px}}
.chips span::before{{content:"";width:10px;height:10px;border-radius:50%;background:var(--clay)}}
.moon{{position:absolute;fill:var(--clay)}}
.tw{{position:absolute;fill:var(--cream)}}
.house{{filter:drop-shadow(0 18px 26px rgba(51,33,27,.2))}}
.house svg{{width:100%;height:auto;overflow:visible}}
.adv-n{{font-family:"Bricolage Grotesque";font-weight:600;font-size:15px;fill:#8C2C1D}}
'''


def seal(size, label='Pottery Pause · 5 Freitagabende ·', big='5', small='Wochen'):
    return (f'<span class="seal" style="width:{size}px;height:{size}px;{{pos}}"><svg viewBox="0 0 120 120"><defs><path id="sc" d="M60 60m-42 0a42 42 0 1 1 84 0a42 42 0 1 1-84 0"/></defs>'
            f'<text><textPath href="#sc" textLength="258" lengthAdjust="spacingAndGlyphs">{label}</textPath></text></svg>'
            f'<b style="font-size:{size*.32:.0f}px">{big}</b><i style="font-size:{size*.12:.0f}px">{small}</i></span>')



def page(w, h, body):
    return (f'<!doctype html><html><head><meta charset="utf-8"><style>:root{{--w:{w}px;--h:{h}px;--gh:110px;--pad-top:150px}}{BASE_CSS}</style></head>'
            f'<body>{wave_rect(w, h)}{GARLAND}<img class="logo" src="{IMG}forma-logo.png" alt="">{body}</body></html>')


STARPATH = f'<svg viewBox="0 0 24 24"><path d="{STAR}"/></svg>'
VERBOTEN = {'vivien-toepfern.jpg', 'drehen.jpg', 'vivien.jpg', 'vivien-mit-tochter.jpg'}


def build(spec):
    esc = lambda t: (t or '').replace('&', '&amp;').replace('<', '&lt;')
    photo = spec.get('photo', '')
    if Path(photo).name in VERBOTEN:
        raise SystemExit(f'Foto {photo} nicht für Werbung verwenden (Vivien ist darauf zu sehen).')
    if 'keramik' in json.dumps(spec, ensure_ascii=False).lower():
        raise SystemExit('Das Wort „Keramik“ darf nicht vorkommen.')
    art = ''
    if photo:
        s = spec.get('seal')
        sealhtml = seal(170, esc(s.get('ring', 'FORMA · Töpferstudio im Paznaun ·')), esc(s.get('big', '')), esc(s.get('small', ''))).replace('{pos}', 'left:-70px;bottom:-30px') if s else ''
        house = f'<div class="house" style="position:absolute;width:200px;right:-80px;bottom:-60px">{HOUSE}</div>' if spec.get('house') else ''
        art = (f'<div class="arch" style="width:540px;height:600px;flex:none"><div class="pic"><img src="{(REPO / photo).as_uri()}" style="object-position:{spec.get("photo_pos", "50% 50%")}"></div>'
               f'{sealhtml}{house}</div>')
    elif spec.get('house'):
        art = f'<div class="house" style="width:380px;flex:none">{HOUSE}</div>'
    chips = ''.join(f'<span>{esc(c)}</span>' for c in spec.get('chips', []))
    body = f'''<div class="stack" style="gap:22px">
      {art}
      {f'<span class="pill" style="margin-top:24px">{STARPATH}{esc(spec["pill"])}</span>' if spec.get('pill') else ''}
      <h1 style="font-size:{spec.get('title_size', 96)}px">{esc(spec['title']).replace('|', '<br>')}</h1>
      {f'<p class="sub" style="font-size:34px;max-width:780px">{esc(spec["sub"])}</p>' if spec.get('sub') else ''}
      {f'<p class="txt" style="font-size:28px;max-width:780px">{esc(spec["text"])}</p>' if spec.get('text') else ''}
      {f'<div class="chips">{chips}</div>' if chips else ''}
      {f'<span class="cta">{esc(spec["cta"])}</span>' if spec.get('cta') else ''}
      <span class="url">forma-pottery.com</span>
    </div>'''
    return page(1000, 1500, body)


async def main():
    spec = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
    out = REPO / spec['file']
    tmp = out.with_suffix('.html')
    tmp.write_text(build(spec), encoding='utf-8')
    async with async_playwright() as p:
        b = await p.chromium.launch()
        pg = await b.new_page(viewport={'width': 1000, 'height': 1500})
        await pg.goto(tmp.as_uri(), wait_until='networkidle')
        await pg.evaluate('document.fonts.ready')
        await pg.wait_for_timeout(300)
        png = out.with_suffix('.png')
        await pg.screenshot(path=str(png))
        await b.close()
    tmp.unlink()
    from PIL import Image
    Image.open(png).convert('RGB').save(out, quality=88, optimize=True)
    png.unlink()
    print('fertig:', out)

asyncio.run(main())

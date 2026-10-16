"""Story-/Status-Bilder (1080 × 1920) im FORMA-Scrapbook-Stil für Instagram-Story und WhatsApp-Status.
Aufruf (im Repo-Ordner):  python3 tools/social/make_story.py stories.json <ausgabeordner>

Felder pro Bild:
  file      Dateiname (PNG)
  theme     "rot" | "ton" | "creme"  (abwechseln, damit die Story beim Durchtippen knallt)
  photo     Foto aus img/ (oder "house": true für das Adventkalender-Haus)
  pos       object-position des Fotos, z. B. "50% 60%"
  note      handgeschriebene Bildunterschrift auf dem Polaroid
  hand      kleiner handgeschriebener Einstieg oben links (z. B. "psst …")
  burst     Text im Zacken-Sticker, "|" = zweite, kleinere Zeile (z. B. "NEU|im Atelier")
  title     große Überschrift, "|" = Zeilenumbruch
  script    handgeschriebenes Akzentwort unter der Überschrift
  text      1–2 kurze Sätze
  cta       Knopf-Text; dazu zeigt ein gekritzelter Pfeil nach unten zum Link-Sticker
Oben 220 px und unten 300 px bleiben frei (Instagram-Leiste, Link-Sticker, WhatsApp-Antwortfeld)."""
import asyncio, json, math, re, sys
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

THEMES = {
    # Hintergrund, Muster, Überschrift, Text, Handschrift, Sticker-Hintergrund, Sticker-Text, Kritzel 1, Kritzel 2, Blob
    'rot':   dict(bg='#8C2C1D', dot='rgba(251,241,234,.16)', h='#FBF1EA', t='#F3E2D6', hand='#DDBFA8', bb='#DDBFA8', bt='#8C2C1D', d1='#FBF1EA', d2='#DDBFA8', blob='#A63A27', cta_bg='#FBF1EA', cta_t='#8C2C1D'),
    'ton':   dict(bg='#DDBFA8', dot='rgba(251,241,234,.55)', h='#8C2C1D', t='#5A3F35', hand='#8C2C1D', bb='#8C2C1D', bt='#FBF1EA', d1='#8C2C1D', d2='#FBF1EA', blob='#E9D3C1', cta_bg='#8C2C1D', cta_t='#FBF1EA'),
    'creme': dict(bg='#F3E7DA', dot='rgba(140,44,29,.10)', h='#8C2C1D', t='#5A3F35', hand='#8C2C1D', bb='#8C2C1D', bt='#FBF1EA', d1='#8C2C1D', d2='#DDBFA8', blob='#E8D5C3', cta_bg='#8C2C1D', cta_t='#FBF1EA'),
}


def burst_path(cx, cy, r1, r2, n=18):
    pts = []
    for i in range(n * 2):
        a = math.pi * i / n - math.pi / 2
        r = r1 if i % 2 == 0 else r2
        pts.append(f'{cx + r * math.cos(a):.1f},{cy + r * math.sin(a):.1f}')
    return 'M' + ' L'.join(pts) + 'Z'


def esc(t):
    return (t or '').replace('&', '&amp;').replace('<', '&lt;')


def css(th):
    return f'''
@font-face{{font-family:"Bricolage Grotesque";font-weight:600 800;src:url({F}BricolageGrotesque-Bold.woff)}}
@font-face{{font-family:"Bricolage Grotesque";font-weight:300 599;src:url({F}BricolageGrotesque-Regular.woff)}}
@font-face{{font-family:"Outfit";font-weight:300 500;src:url({F}Outfit-Regular.woff)}}
@font-face{{font-family:"Homemade Apple";src:url({F}HomemadeApple-Regular.woff)}}
*{{box-sizing:border-box;margin:0}}
html,body{{width:1080px;height:1920px}}
body{{position:relative;overflow:hidden;font-family:Outfit,sans-serif;background:radial-gradient(circle,{th['dot']} 0 3px,transparent 4px) 0 0/58px 58px,{th['bg']}}}
.blob{{position:absolute;left:50%;top:640px;width:1000px;height:900px;transform:translate(-50%,-50%) rotate(-8deg);border-radius:58% 42% 47% 53%/52% 46% 54% 48%;background:{th['blob']}}}
.logo{{position:absolute;right:58px;top:150px;width:112px;z-index:5}}
.hand{{position:absolute;left:84px;top:236px;z-index:6;font-family:"Homemade Apple";font-size:46px;color:{th['hand']};transform:rotate(-7deg)}}
.pola{{position:absolute;left:50%;top:330px;z-index:3;width:680px;padding:28px 28px 0;background:#FFFBF6;box-shadow:0 26px 50px rgba(40,20,14,.35);transform:translateX(-50%) rotate(-3.5deg)}}
.pola .ph{{width:100%;height:700px;overflow:hidden;background:#E4D2BF}}
.pola .ph img{{width:100%;height:100%;object-fit:cover;display:block}}
.pola .cap{{height:118px;display:flex;align-items:center;justify-content:center;font-family:"Homemade Apple";font-size:40px;color:#8C2C1D;padding-top:6px}}
.tape{{position:absolute;width:190px;height:58px;background:rgba(221,191,168,.82);border-left:3px dashed rgba(255,255,255,.55);border-right:3px dashed rgba(255,255,255,.55);z-index:4}}
.t1{{left:-52px;top:-14px;transform:rotate(-32deg)}}.t2{{right:-52px;top:-14px;transform:rotate(30deg)}}
.house{{position:absolute;left:50%;top:300px;z-index:3;width:560px;transform:translateX(-50%) rotate(-2deg)}}
.house svg{{width:100%;height:auto;overflow:visible;filter:drop-shadow(0 26px 34px rgba(40,20,14,.35))}}
.adv-n{{font-family:"Bricolage Grotesque";font-weight:600;font-size:15px;fill:#8C2C1D}}
.burst{{position:absolute;right:70px;top:300px;z-index:7;width:270px;height:270px;transform:rotate(12deg)}}
.burst svg{{position:absolute;inset:0;width:100%;height:100%;filter:drop-shadow(0 10px 18px rgba(40,20,14,.3))}}
.burst span{{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:{th['bt']};text-align:center;line-height:1}}
.burst b{{font-family:"Bricolage Grotesque";font-weight:600;font-size:66px;letter-spacing:-.02em}}
.burst i{{font-style:normal;font-family:"Homemade Apple";font-size:28px;margin-top:10px}}
.bottom{{position:absolute;left:0;right:0;top:1222px;bottom:300px;z-index:6;display:flex;flex-direction:column;align-items:center;text-align:center;padding:0 80px}}
h1{{font-family:"Bricolage Grotesque";font-weight:600;font-size:{{TS}}px;line-height:.98;letter-spacing:-.025em;color:{th['h']}}}
.script{{font-family:"Homemade Apple";font-size:52px;white-space:nowrap;color:{th['hand']};transform:rotate(-4deg);margin:2px 0 6px}}
p{{font-size:36px;line-height:1.35;color:{th['t']};max-width:860px;margin-top:16px}}
.cta{{position:relative;margin-top:26px;display:inline-flex;align-items:center;gap:14px;padding:20px 46px;border-radius:999px;background:{th['cta_bg']};color:{th['cta_t']};font-size:36px;font-weight:500;transform:rotate(-2deg);box-shadow:0 10px 0 rgba(40,20,14,.18)}}
.arrow{{position:absolute;right:-150px;top:10px;width:140px;height:150px;fill:none;stroke:{th['d1']};stroke-width:5;stroke-linecap:round;stroke-linejoin:round}}
.d{{position:absolute;z-index:8}}
'''


def page(s):
    th = THEMES[s.get('theme', 'ton')]
    photo = s.get('photo', '')
    if Path(photo).name in VERBOTEN:
        raise SystemExit(f'{photo} nicht für Werbung verwenden.')
    if 'keramik' in json.dumps(s, ensure_ascii=False).lower():
        raise SystemExit('Das Wort „Keramik“ darf nicht vorkommen.')
    if s.get('house'):
        art = f'<div class="house">{HOUSE}</div>'
    else:
        art = (f'<div class="pola"><span class="tape t1"></span><span class="tape t2"></span>'
               f'<div class="ph"><img src="{(REPO / photo).as_uri()}" style="object-position:{s.get("pos", "50% 50%")}"></div>'
               f'<div class="cap">{esc(s.get("note", ""))}</div></div>')
    burst = ''
    if s.get('burst'):
        big, _, small = s['burst'].partition('|')
        burst = (f'<div class="burst"><svg viewBox="0 0 270 270"><path d="{burst_path(135, 135, 133, 112)}" fill="{th["bb"]}"/>'
                 f'<circle cx="135" cy="135" r="96" fill="none" stroke="{th["bt"]}" stroke-width="2.5" stroke-dasharray="6 7" opacity=".6"/></svg>'
                 f'<span><b style="font-size:{66 if len(big)<=4 else (50 if len(big)<=6 else 40)}px">{esc(big)}</b>{f"<i style=\"font-size:{28 if len(small)<=10 else 22}px\">{esc(small)}</i>" if small else ""}</span></div>')
    d1, d2 = th['d1'], th['d2']
    doodles = (f'<svg class="d" viewBox="0 0 24 24" style="left:58px;top:560px;width:62px;fill:{d2};transform:rotate(-14deg)"><path d="{HEART}"/></svg>'
               f'<svg class="d" viewBox="0 0 24 24" style="left:110px;top:1080px;width:44px;fill:{d1}"><path d="{STAR}"/></svg>'
               f'<svg class="d" viewBox="0 0 24 24" style="right:66px;top:930px;width:70px;fill:{d2}"><path d="{STAR}"/></svg>'
               f'<svg class="d" viewBox="0 0 24 24" style="right:140px;top:1150px;width:34px;fill:{d1};transform:rotate(12deg)"><path d="{HEART}"/></svg>'
               f'<svg class="d" viewBox="0 0 120 40" style="left:40px;top:1170px;width:110px;fill:none;stroke:{d2};stroke-width:5;stroke-linecap:round"><path d="M4 28c14-22 26 10 40-6s26 10 40-4 24 6 32-6"/></svg>')
    title = esc(s.get('title', ''))
    ts = s.get('title_size') or (118 if max(len(x) for x in title.split('|')) <= 14 else 98)
    arrow = ('<svg class="arrow" viewBox="0 0 140 150"><path d="M20 10c50 0 92 22 86 70-3 22-14 36-28 48"/><path d="M60 118l18 14 4-24"/></svg>') if s.get('cta') else ''
    bottom = ('<div class="bottom">'
              + f'<h1>{title.replace("|", "<br>")}</h1>'
              + (f'<div class="script">{esc(s["script"])}</div>' if s.get('script') else '')
              + (f'<p>{esc(s["text"])}</p>' if s.get('text') else '')
              + (f'<span class="cta">{esc(s["cta"])}{arrow}</span>' if s.get('cta') else '')
              + '</div>')
    hand = f'<div class="hand">{esc(s["hand"])}</div>' if s.get('hand') else ''
    return (f'<!doctype html><html><head><meta charset="utf-8"><style>{css(th).replace("{TS}", str(ts))}</style></head><body>'
            f'<div class="blob"></div><img class="logo" src="{IMG}forma-logo.png">{hand}{art}{burst}{doodles}{bottom}</body></html>')


async def main():
    stories = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
    out = Path(sys.argv[2]).resolve()
    out.mkdir(parents=True, exist_ok=True)
    only = sys.argv[3:]
    async with async_playwright() as p:
        b = await p.chromium.launch()
        for s in stories:
            if only and not any(o in s['file'] for o in only):
                continue
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

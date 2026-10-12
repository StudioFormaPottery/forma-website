# Baut die saisonalen Kopfbilder für alle Website-Mails: img/mail/<thema>.png (1200 × 300, wird mit 600 px angezeigt).
# Ausführen: python3 tools/mail/build_headers.py (aus dem Repo-Ordner).
import asyncio, os, random, base64
from playwright.async_api import async_playwright
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
R, K, B, P, S, SAND = '#8C2C1D', '#674B40', '#DDBFA8', '#FBF1EA', '#E4D2BF', '#EEE2D4'
G = '#674B40'  # Tannen und Stiele in Braun (keine dritte Akzentfarbe)
def leaf(c): return f'<path d="M0-22C14-14 14 10 0 22-14 10-14-14 0-22z" fill="{c}"/><path d="M0-18V20" stroke="{P}" stroke-width="1.6"/>'
def acorn(): return f'<ellipse cx="0" cy="4" rx="9" ry="11" fill="{P}"/><path d="M-11-2a11 8 0 0122 0z" fill="{K}"/><path d="M0-10v-5" stroke="{K}" stroke-width="2.4" stroke-linecap="round"/>'
def star(c, s=1): return f'<path transform="scale({s})" d="M0-16c1.2 9 7 14.8 16 16-9 1.2-14.8 7-16 16-1.2-9-7-14.8-16-16 9-1.2 14.8-7 16-16z" fill="{c}"/>'
def tree(c): return f'<path d="M0-26L18 4H8L20 22H-20L-8 4H-18z" fill="{c}"/><rect x="-3" y="22" width="6" height="6" fill="{K}"/>'
def gift(): return f'<rect x="-16" y="-10" width="32" height="26" rx="3" fill="{R}"/><rect x="-18" y="-16" width="36" height="9" rx="2" fill="{K}"/><path d="M0-16v32" stroke="{P}" stroke-width="3"/><path d="M0-16c-8-10-16-4-8 0M0-16c8-10 16-4 8 0" fill="none" stroke="{P}" stroke-width="2.4"/>'
def flake(c): return f'<g stroke="{c}" stroke-width="2.6" stroke-linecap="round">' + ''.join(f'<path transform="rotate({a})" d="M0 0V-18M0-11l-5-5M0-11l5-5"/>' for a in range(0, 360, 60)) + '</g>'
def heart(c): return f'<path d="M0 16C-24 0-14-20 0-8 14-20 24 0 0 16z" fill="{c}"/>'
def egg(c, d): return f'<ellipse rx="14" ry="19" fill="{c}"/><path d="M-13 0c5-4 9 4 13 0s9 4 13 0" stroke="{d}" stroke-width="2.4" fill="none"/>'
def tulip(c): return f'<path d="M0 26V0" stroke="{G}" stroke-width="3"/><path d="M-10-14c0 14 20 14 20 0l-5 5-5-9-5 9z" fill="{c}"/>'
def flower(c): return ''.join(f'<ellipse transform="rotate({a}) translate(0 -9)" rx="6" ry="9" fill="{c}"/>' for a in range(0, 360, 72)) + f'<circle r="5" fill="{K}"/>'
def sun(): return f'<circle r="13" fill="{R}"/>' + ''.join(f'<path transform="rotate({a})" d="M0-18v-7" stroke="{R}" stroke-width="3" stroke-linecap="round"/>' for a in range(0, 360, 45))
def wave(c): return f'<path d="M-24 0c8-8 16 8 24 0s16-8 24 0" stroke="{c}" stroke-width="3.4" fill="none" stroke-linecap="round"/>'
def confetti(): return ''.join(f'<rect x="{x}" y="{y}" width="5" height="10" rx="2" transform="rotate({a} {x} {y})" fill="{c}"/>' for x, y, a, c in [(-14,-6,20,R),(4,-14,-30,K),(12,6,50,B),(-4,10,-10,R)])
def mask(): return f'<path d="M-22-6c8-6 16-2 22 2 6-4 14-8 22-2 0 10-6 16-12 16-6 0-8-6-10-6s-4 6-10 6c-6 0-12-6-12-16z" fill="{R}"/><circle cx="-10" cy="1" r="3.4" fill="{P}"/><circle cx="10" cy="1" r="3.4" fill="{P}"/>'
def candle(): return f'<rect x="-6" y="-6" width="12" height="28" rx="3" fill="{P}"/><path d="M0-8c-5-6 0-12 0-16 4 6 5 10 0 16z" fill="{R}"/>'
THEMES = {
    'herbst':    [lambda: leaf(R), lambda: leaf(K), acorn, lambda: star(P, .6)],
    'advent':    [lambda: tree(G), lambda: star(R), gift, candle, lambda: star(K, .7)],
    'winter':    [lambda: flake(P), lambda: flake(K), lambda: star(P, .7), lambda: tree(G)],
    'fasching':  [confetti, mask, lambda: star(R, .7), confetti],
    'valentin':  [lambda: heart(R), lambda: heart(K), lambda: heart(P), lambda: star(R, .6)],
    'ostern':    [lambda: egg(R, P), lambda: egg(B, R), lambda: egg(P, K), lambda: tulip(R)],
    'fruehling': [lambda: flower(R), lambda: flower(P), lambda: tulip(K), lambda: leaf(G)],
    'sommer':    [sun, lambda: wave(R), lambda: wave(K), lambda: flower(P)],
}
def banner(name, items):
    rnd = random.Random(name)
    parts = []
    # Motive links und rechts, die Mitte bleibt frei für das Logo
    spots = [(70,80),(150,200),(230,95),(300,215),(380,70),(95,250),(440,170),(820,80),(760,200),(900,215),(980,85),(1060,190),(1130,90),(1110,255),(870,150)]
    for i, (x, y) in enumerate(spots):
        it = items[i % len(items)]
        parts.append(f'<g transform="translate({x} {y}) rotate({rnd.randint(-30,30)}) scale({rnd.uniform(1.4,2.0):.2f})">{it()}</g>')
    dots = ''.join(f'<circle cx="{rnd.randint(20,1180)}" cy="{rnd.randint(20,280)}" r="{rnd.uniform(2,4):.1f}" fill="{P}" opacity=".7"/>' for _ in range(26))
    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="300" viewBox="0 0 1200 300">
<rect width="1200" height="300" fill="{B}"/>{dots}{''.join(parts)}
<circle cx="600" cy="140" r="96" fill="{B}"/>
<path d="M0 270c150-36 300 30 450 0s300-36 450 0 225 24 300 6V300H0z" fill="{P}"/></svg>'''
def review_card(name, items):
    """Bewertungs-Karte für die Google-Bewertungs-Mail: 5 Sterne, ringsum Saison-Motive."""
    rnd = random.Random(name + 'review')
    spots = [(90,90),(60,250),(150,350),(230,62),(1110,90),(1140,250),(1050,350),(970,62)]
    parts = []
    for i, (x, y) in enumerate(spots):
        it = items[i % len(items)]
        parts.append(f'<g transform="translate({x} {y}) rotate({rnd.randint(-30,30)}) scale({rnd.uniform(1.3,1.8):.2f})">{it()}</g>')
    dots = ''.join(f'<circle cx="{rnd.randint(30,1170)}" cy="{rnd.randint(30,390)}" r="{rnd.uniform(2,4):.1f}" fill="{P}" opacity=".8"/>' for _ in range(20))
    big = ''.join(f'<g transform="translate({420 + i*90} 225)"><path d="M0-38l11 23 25 3-18 18 5 25-23-12-23 12 5-25-18-18 25-3z" fill="{R}" stroke="{R}" stroke-width="6" stroke-linejoin="round"/></g>' for i in range(5))
    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="420" viewBox="0 0 1200 420">
<rect width="1200" height="420" rx="44" fill="{B}"/>{dots}{''.join(parts)}
<text x="600" y="140" text-anchor="middle" font-family="Bricolage, sans-serif" font-weight="650" font-size="58" fill="{R}">Wie war's bei FORMA?</text>
{big}
<text x="600" y="330" text-anchor="middle" font-family="Outfit, sans-serif" font-size="30" fill="{K}">Ein, zwei Sätze auf Google · dauert nur eine Minute</text></svg>'''

async def main():
    os.makedirs(os.path.join(ROOT, 'img', 'mail'), exist_ok=True)
    logo = 'data:image/png;base64,' + base64.b64encode(open(os.path.join(ROOT, 'img', 'forma-logo.png'), 'rb').read()).decode()
    async with async_playwright() as p:
        b = await p.chromium.launch()
        pg = await b.new_page(viewport={'width': 1200, 'height': 300})
        for name, items in THEMES.items():
            html = f'<html><body style="margin:0">{banner(name, items)}<img src="{logo}" style="position:absolute;left:520px;top:58px;width:160px;height:160px;object-fit:contain"></body></html>'
            await pg.set_content(html); await pg.wait_for_timeout(250)
            await pg.screenshot(path=os.path.join(ROOT, 'img', 'mail', name + '.png'), clip={'x': 0, 'y': 0, 'width': 1200, 'height': 300})
        f64 = lambda n: 'data:font/woff;base64,' + base64.b64encode(open(os.path.join(ROOT, 'fonts', n), 'rb').read()).decode()
        css = f"<style>@font-face{{font-family:Bricolage;font-weight:600 800;src:url({f64('BricolageGrotesque-Bold.woff')})}}@font-face{{font-family:Outfit;src:url({f64('Outfit-Regular.woff')})}}</style>"
        pg2 = await b.new_page(viewport={'width': 1200, 'height': 420})
        for name, items in THEMES.items():
            await pg2.set_content(f'<html><head>{css}</head><body style="margin:0;background:{P}">{review_card(name, items)}</body></html>'); await pg2.evaluate('document.fonts.ready'); await pg2.wait_for_timeout(400)
            await pg2.screenshot(path=os.path.join(ROOT, 'img', 'mail', 'bewertung-' + name + '.png'), clip={'x': 0, 'y': 0, 'width': 1200, 'height': 420})
        await b.close()
asyncio.run(main())

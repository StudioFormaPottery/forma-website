# Rendert die beiden Gutschein-Hintergründe (A4 quer, 300 dpi) gegen den lokalen Server.
# Aufruf im Repo: php -S 127.0.0.1:8765 & python3 tools/mv-gutschein/build.py
import asyncio, os, sys
from playwright.async_api import async_playwright
base = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8765'
out = os.path.join(os.path.dirname(__file__), '..', '..', 'api', 'mv-gutschein')
async def main():
    os.makedirs(out, exist_ok=True)
    async with async_playwright() as p:
        b = await p.chromium.launch()
        pg = await b.new_page(viewport={'width': 1123, 'height': 794}, device_scale_factor=3.125)
        for d in ('1', '2'):
            await pg.goto(f'{base}/tools/mv-gutschein/design.html?d={d}', wait_until='networkidle')
            await pg.evaluate('document.fonts.ready')
            await pg.wait_for_timeout(300)
            await pg.screenshot(path=os.path.join(out, f'design{d}.png'), clip={'x': 0, 'y': 0, 'width': 1122.5, 'height': 793.7})
        await b.close()
asyncio.run(main())

# Rendert die FORMA-Halloween-Posts als PNG (Instagram 4:5, Story 9:16, Pinterest 2:3) + ZIP.
# Lokal: php -S 127.0.0.1:8765 im Repo-Ordner, dann python3 tools/social/make_halloween.py <ausgabeordner>
import asyncio, sys, os, zipfile
from playwright.async_api import async_playwright
out = sys.argv[1] if len(sys.argv) > 1 else 'halloween-posts'
os.makedirs(out, exist_ok=True)
F = {'feed': (1080, 1350, 'Instagram-4x5'), 'story': (1080, 1920, 'Story-9x16'), 'pin': (1000, 1500, 'Pinterest-2x3')}
N = {'1': '1-30Okt-Pottery-bemalen', '2': '2-31Okt-Spin-and-Go', '3': '3-1Nov-Pottery-bemalen'}
async def main():
    async with async_playwright() as p:
        b = await p.chromium.launch(); files = []
        for k, n in N.items():
            for f, (w, h, lab) in F.items():
                pg = await b.new_page(viewport={'width': w, 'height': h})
                await pg.goto(f'http://127.0.0.1:8765/tools/social/halloween.html?p={k}&f={f}', wait_until='networkidle')
                await pg.wait_for_timeout(500)
                fn = os.path.join(out, f'FORMA-Halloween-{n}-{lab}.png')
                await (await pg.query_selector('#c')).screenshot(path=fn); files.append(fn); await pg.close()
        await b.close()
    with zipfile.ZipFile(os.path.join(out, 'FORMA-Halloween-Posts.zip'), 'w', zipfile.ZIP_DEFLATED) as z:
        for fn in files: z.write(fn, os.path.basename(fn))
    print('\n'.join(files))
asyncio.run(main())

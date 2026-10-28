#!/usr/bin/env python3
"""Movecia-Werbebilder (Vivien, 27.10.2026) im selben Stil wie Wochenbilder und Pins (make_week.page):
je Anzeige und Sprache Instagram 4:5, WhatsApp/Story 9:16 und Pinterest 2:3, alles zusätzlich als ZIP.
Aufruf im Repo-Ordner, lokaler Server auf 127.0.0.1:8765: python3 tools/movecia-ads/make_ads.py tools/movecia-ads/ads.json ausgabeordner
Preise und Termine vorher immer mit mv_cfg_raw() / natal_packages() abgleichen, abgelaufene Early-Birds rausnehmen."""
import json, os, sys, zipfile
sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'movecia-week'))
import make_week
from playwright.sync_api import sync_playwright

src, out = sys.argv[1], sys.argv[2]
cfg = json.load(open(src, encoding='utf-8'))
os.makedirs(out, exist_ok=True)
tmp = os.path.join(os.path.dirname(os.path.abspath(make_week.__file__)), '_render'); os.makedirs(tmp, exist_ok=True)
files = []
with sync_playwright() as pw:
    b = pw.chromium.launch()
    for ad in cfg['ads']:
        for lang, p in ad['langs'].items():
            for tag, story, pin, h in [('Instagram-4x5', False, False, 1350), ('WhatsApp-9x16', True, False, 1920), ('Pinterest-2x3', True, True, 1620)]:
                q = dict(p)
                if pin: q['btn'] = p.get('pinbtn', 'Mehr erfahren')
                open(os.path.join(tmp, 'p.html'), 'w', encoding='utf-8').write(make_week.page(q, story, pin=pin))
                pg = b.new_page(viewport={'width': 1080, 'height': h})
                pg.goto(make_week.BASE + '/tools/movecia-week/_render/p.html'); pg.wait_for_timeout(500)
                name = f'{ad["name"]}_{lang.upper()}_{tag}.png'
                pg.screenshot(path=os.path.join(out, name)); pg.close(); files.append(name)
    b.close()
z = os.path.join(out, cfg['zip'])
with zipfile.ZipFile(z, 'w', zipfile.ZIP_DEFLATED) as zf:
    for f in files: zf.write(os.path.join(out, f), f)
print(z, len(files))

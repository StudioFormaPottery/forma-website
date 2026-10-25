#!/usr/bin/env python3
"""Movecia-Pin (1080 x 1620, 2:3) im Stil der Wochenbilder.
Aufruf im Repo-Ordner, lokaler Server auf 127.0.0.1:8765: python3 tools/pinterest-movecia/make_pin.py pin.json
pin.json = ein Post wie in tools/movecia-week (type, kicker, title, em, text, items, photo, cta, url, dark …) plus "file" (Zielpfad, z. B. img/pins/movecia/2026-10-26-box-breathing.jpg)."""
import json, os, sys
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'movecia-week'))
import make_week
from playwright.sync_api import sync_playwright
from PIL import Image

p = json.load(open(sys.argv[1], encoding='utf-8'))
tmp = os.path.join(os.path.dirname(os.path.abspath(make_week.__file__)), '_render')
os.makedirs(tmp, exist_ok=True)
open(os.path.join(tmp, 'p.html'), 'w', encoding='utf-8').write(make_week.page(p, True, pin=True))
png = os.path.join(tmp, 'pin.png')
with sync_playwright() as pw:
    b = pw.chromium.launch(); pg = b.new_page(viewport={'width': 1080, 'height': 1620})
    pg.goto(make_week.BASE + '/tools/movecia-week/_render/p.html'); pg.wait_for_timeout(600)
    pg.screenshot(path=png); b.close()
Image.open(png).convert('RGB').save(p['file'], quality=88)
print(p['file'])

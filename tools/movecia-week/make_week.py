#!/usr/bin/env python3
"""Movecia Wochen-Paket (Vivien, 25.10.2026): pro Tag ein fertiges Bild mit Text,
Instagram 4:5 (1080x1350) und WhatsApp-Status/Kanal 9:16 (1080x1920), dazu Texte.txt und ZIP.
Aufruf: python3 make_week.py woche.json ausgabeordner   (lokaler PHP-Server auf 127.0.0.1:8765 aus dem Repo-Ordner)
Typen: quote, photo, steps, poll, offer. Felder: file, type, kicker, title (| = Umbruch), em (kursives Akzentwort),
text, items (Liste), photo (Pfad ab Repo), cta, url, dark (bool)."""
import json, sys, os, zipfile, html
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8765'
sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'workbooks'))
import icons as wbicons

# Movecia-Handschrift (Vivien, 27.10.2026): Pflaume/Beige/Blush/Pfirsich, Cormorant + Hanken, fließende Wellen,
# Kiesel-Zeichen, Themen-Icons wie in den Workbooks, Pastell-Karten, Fotos im Bogen mit Wellenkante.
# Nur die neuen Fotos (img/movecia/fotos/ev-*.jpg, pt-*.jpg). Alte Bilder werden automatisch ersetzt.
OLD_PHOTOS = {'art-fold.jpg': 'ev-method-foto.jpg', 'art-reach.jpg': 'ev-ueber-mich.jpg', 'fold.jpg': 'ev-method-foto.jpg',
              'reach.jpg': 'ev-ueber-mich.jpg', 'art-schwanger.jpg': 'ev-natal.jpg', 'schwanger.jpg': 'ev-natal.jpg',
              'schwanger-kueche.jpg': 'ev-natal-sitzend.jpg'}
TYPE_ICON = {'quote': 'herz', 'steps': 'uebung', 'poll': 'stimmung', 'offer': 'stern', 'photo': 'sonne'}
PASTELS = ['#FBF9F6', '#F3E2D8', '#EEDFE0', '#F8EFE7', '#E8D9DD']

def photo_path(p):
    ph = p.get('photo') or ''
    base = os.path.basename(ph)
    if base in OLD_PHOTOS:
        print('Hinweis: altes Foto', base, 'ersetzt durch', OLD_PHOTOS[base], file=sys.stderr)
        return 'img/movecia/fotos/' + OLD_PHOTOS[base]
    if ph and not (base.startswith('ev-') or base.startswith('pt-')):
        print('Achtung: Foto', base, 'ist kein neues Movecia-Foto (ev-*/pt-*)', file=sys.stderr)
    return ph

WAVES = """<svg class="waves" viewBox="0 0 1080 300" preserveAspectRatio="none"><path class="w1" d="M0 120 C180 50 360 50 540 120 S900 190 1080 110 V300 H0Z"/><path class="w2" d="M0 170 C200 230 380 230 560 170 S900 110 1080 175 V300 H0Z"/><path class="w3" d="M0 220 C220 175 420 175 600 220 S940 265 1080 215 V300 H0Z"/></svg>"""
LINES = """<svg class="lines" viewBox="0 0 1080 200" preserveAspectRatio="none"><path d="M-20 120 C200 40 380 40 560 110 S900 190 1100 90"/><path d="M-20 150 C200 80 380 80 560 140 S900 210 1100 120" stroke-dasharray="3 12"/></svg>"""
PEBBLE = """<svg class="pebble" viewBox="17 14 94 94"><path class="pb-soft" d="M56 32 C 76 29, 92 44, 91 64 C 90 84, 72 96, 53 92 C 34 88, 25 72, 29 53 C 32 40, 43 33, 56 32 Z" transform="translate(-6 -4)"/><path class="pb-line" d="M44 30 C 62 20, 90 28, 98 50 C 104 68, 92 78, 84 88 C 76 100, 50 98, 36 88 C 22 78, 22 58, 28 46 C 32 38, 38 33, 44 30 Z" transform="translate(7 4) translate(63 60) scale(0.85) translate(-63 -60)"/><circle class="pb-dot" cx="81" cy="54" r="10"/></svg>"""

CSS = """
@font-face{font-family:Cormorant;font-weight:300 700;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:Cormorant;font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:Hanken;font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
*{box-sizing:border-box;margin:0}
body{width:1080px;height:1350px;overflow:hidden;background:#F3EAE1;color:#2A2026;font-family:Hanken,sans-serif;position:relative}
body.s{height:1920px}
body.pin{height:1620px}
body.dark{background:#2E1D27;color:#F7F1EE}
body::before{content:"";position:absolute;inset:0;background:radial-gradient(48% 34% at 88% 10%,rgba(232,214,202,.9),transparent 70%),radial-gradient(40% 30% at 6% 40%,rgba(234,223,222,.85),transparent 70%);z-index:0}
.dark::before{background:radial-gradient(48% 34% at 88% 10%,rgba(125,94,104,.55),transparent 70%),radial-gradient(40% 30% at 6% 50%,rgba(107,86,88,.5),transparent 70%)}
.waves{position:absolute;left:0;bottom:0;width:100%;height:300px;z-index:1}
.s .waves{height:420px}
.waves .w1{fill:#E4DCD1}.waves .w2{fill:#E6DBCF;opacity:.85}.waves .w3{fill:#F6EEE7}
.dark .waves .w1{fill:#5A4750}.dark .waves .w2{fill:#3A2631;opacity:.8}.dark .waves .w3{fill:#55434A}
.lines{position:absolute;left:0;width:100%;height:200px;top:46%;z-index:1;opacity:.5}
.lines path{fill:none;stroke:#4A3040;stroke-width:1.2;opacity:.3}.dark .lines path{stroke:#E6DBCF;opacity:.3}
.pebble{position:absolute;right:-40px;top:150px;width:330px;z-index:1;opacity:.95}
.s .pebble{top:260px;width:360px}
.pebble .pb-soft{fill:#E6DBCF}.pebble .pb-line{fill:none;stroke:#4A3040;stroke-width:1.6}.pebble .pb-dot{fill:#4A3040}
.dark .pebble .pb-soft{fill:#3A2631}.dark .pebble .pb-line{stroke:#E6DBCF}.dark .pebble .pb-dot{fill:#E6DBCF}
.dot{position:absolute;border-radius:50%;background:#4A3040;opacity:.35;z-index:1}
.wrap{position:absolute;inset:0;padding:80px 84px 96px;display:flex;flex-direction:column;z-index:2}
.s .wrap{padding:180px 90px 250px}
.pin .wrap{padding:96px 90px 120px}
.top{display:flex;justify-content:space-between;align-items:center}
.brand{display:flex;align-items:center;gap:14px;font-family:Cormorant,serif;font-size:46px;background:rgba(251,248,244,.85);padding:10px 26px 10px 14px;border-radius:99px}
.brand img{width:44px}.brand i{display:inline-block;width:11px;height:11px;border-radius:50%;background:#4A3040}
.dark .brand{background:rgba(251,245,247,.08)}.dark .brand i{background:#E6DBCF}
.day{font-size:23px;letter-spacing:.2em;text-transform:uppercase;font-weight:500;color:#3A2631;background:rgba(251,248,244,.85);padding:12px 22px;border-radius:99px}
.dark .day{color:#E4DCD1;background:rgba(251,245,247,.08)}
.ib{display:grid;place-items:center;width:96px;height:96px;border-radius:50%;background:#FBF9F6;box-shadow:0 0 0 10px rgba(251,248,244,.45);margin-bottom:26px}
.dark .ib{background:#F3E2D8;box-shadow:0 0 0 10px rgba(243,226,216,.12)}
.ib svg{width:62px;height:62px}
.k{font-size:24px;letter-spacing:.2em;text-transform:uppercase;font-weight:500;color:#4A3040}
.dark .k{color:#E6DBCF}
h1{font-family:Cormorant,serif;font-weight:500;font-size:112px;line-height:1.04;margin-top:20px;max-width:900px}
.s h1{font-size:124px}
h1 em{font-style:italic;color:#4A3040}.dark h1 em{color:#E6DBCF}
.text{font-size:34px;line-height:1.45;margin-top:28px;max-width:860px;color:#2E1D27}
.dark .text{color:#E4DCD1}
.mid{margin:auto 0}
.steps{display:grid;gap:16px;margin-top:36px}
.steps div{display:grid;grid-template-columns:64px 1fr;gap:22px;align-items:center;border-radius:30px;padding:22px 28px;font-size:31px;line-height:1.35;color:#2A2026}
.steps span{display:grid;place-items:center;width:64px;height:64px;border-radius:46% 54% 60% 40%/50% 42% 58% 50%;background:rgba(255,255,255,.8);color:#4A3040;font-family:Cormorant,serif;font-size:36px}
.poll{display:grid;gap:18px;margin-top:40px}
.poll div{border-radius:99px;padding:26px 40px;font-size:35px;display:flex;justify-content:space-between;align-items:center;color:#2A2026}
.poll i{width:34px;height:34px;border-radius:50%;border:2.5px solid #4A3040;display:block}
.foot{margin-top:auto;display:flex;justify-content:space-between;align-items:flex-end;gap:24px;position:relative}
.url{font-size:26px;color:#3A2631}.url b{display:block;font-family:Cormorant,serif;font-weight:500;font-size:42px;color:#2A2026}
.dark .url{color:#E6DBCF}.dark .url b{color:#F7F1EE}
.btn{background:#4A3040;color:#F7F1EE;border-radius:99px;padding:20px 36px;font-size:27px;white-space:nowrap}
.dark .btn{background:#E6DBCF;color:#2E1D27}
.t-quote h1{font-size:132px}.s.t-quote h1{font-size:146px}
.sig{font-family:Cormorant,serif;font-style:italic;font-size:48px;color:#4A3040;margin-top:26px}
.dark .sig{color:#E6DBCF}
.deal{margin-top:28px;background:#2E1D27;color:#F7F1EE;border-radius:34px;padding:32px 38px;font-size:29px;line-height:1.55;position:relative;overflow:hidden}
.deal::after{content:"";position:absolute;right:-60px;bottom:-80px;width:220px;height:220px;border-radius:46% 54% 60% 40%/50% 42% 58% 50%;background:#E6DBCF;opacity:.12}
.deal b{font-family:Cormorant,serif;font-weight:500;font-size:84px;display:block;line-height:1.05;margin-bottom:14px}
/* Foto im Bogen mit Wellenkante (wie die Angebotskarten auf der Website) */
.ph{position:relative;z-index:2;margin:34px 0 0;height:560px;border-radius:300px 300px 0 0;background-size:cover;background-position:50% 40%;
 -webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 500' preserveAspectRatio='none'%3E%3Cpath d='M0 0h400v468c-40 22-90 30-140 16S140 446 90 458 20 482 0 476z'/%3E%3C/svg%3E") 0 0/100% 100% no-repeat}
.s .ph{height:720px;margin-top:50px}
.pin .ph{height:600px;margin-top:34px}
.pin.t-offer .ph{height:360px}.pin.t-offer h1{font-size:86px}.pin.t-offer .wrap{padding-top:80px}.pin.t-offer .text{font-size:29px;margin-top:16px}.pin.t-offer .deal{margin-top:20px}
.s.t-offer .ph{height:580px}
.photo h1{font-size:96px}.f.photo .ph{height:500px}.f.photo:has(.chips) .ph{height:400px}.f.t-offer .ph{height:400px;background-position:50% 62%}.f.t-offer h1{font-size:84px}.f.t-offer .deal{margin-top:18px;padding:22px 30px}.f.t-offer .text{font-size:28px;margin-top:14px}.f.t-offer .deal b{font-size:66px}.f.photo .body{margin-top:22px!important}.s.photo h1{font-size:110px}
.photo .text{font-size:31px;margin-top:20px}
.photo .ib{position:absolute;right:46px;bottom:-30px;margin:0;z-index:3}
.phbox{position:relative}
.chips{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}.chips span{background:rgba(251,248,244,.9);border-radius:99px;padding:12px 22px;font-size:25px;color:#2E1D27}.dark .chips span{background:rgba(251,245,247,.1);color:#E4DCD1}
.dark .steps div{color:#2A2026}
"""

def esc(x): return html.escape(x or '')

def title(p):
    t = esc(p.get('title', '')).replace('|', '<br>')
    if p.get('em'): t += ' <em>' + esc(p['em']) + '</em>'
    return t

def ibubble(p):
    k = p.get('icon') or TYPE_ICON.get(p['type'], 'herz')
    if k not in wbicons.ICONS: k = 'herz'
    return f'<div class="ib">{wbicons.icon(k, 62)}</div>'

def page(p, story, pin=False):
    cls = (['pin', 's'] if pin else ['s' if story else 'f']) + ['t-' + p['type']] + (['dark'] if p.get('dark') else [])
    logo = '/img/movecia/movecia-zeichen-hell.svg' if p.get('dark') else '/img/movecia/movecia-zeichen-klein.svg'
    day = f'<span class="day">{esc(p["day"])}</span>' if p.get('day') else ''
    top = f'<div class="top"><div class="brand"><img src="{logo}"><span>Movecia<i></i></span></div>{day}</div>'
    foot = ''
    if p.get('cta') or p.get('url'):
        foot = f'<div class="foot"><div class="url">{esc(p.get("cta",""))}<b>{esc(p.get("url",""))}</b></div><span class="btn">{esc(p.get("btn", "Link in Bio"))} →</span></div>'
    has_photo = p['type'] == 'photo' or bool(p.get('photo'))
    body = ('' if has_photo else ibubble(p)) + f'<p class="k">{esc(p.get("kicker",""))}</p><h1>{title(p)}</h1>'
    if p.get('text'): body += f'<p class="text">{esc(p["text"])}</p>'
    if p['type'] == 'steps':
        mark = (lambda i: wbicons.icon('herz', 34)) if p.get('bullets') else (lambda i: str(i + 1))
        body += '<div class="steps">' + ''.join(f'<div style="background:{PASTELS[(i + 1) % 5]}"><span>{mark(i)}</span>{esc(s)}</div>' for i, s in enumerate(p['items'])) + '</div>'
    if p['type'] == 'poll':
        body += '<div class="poll">' + ''.join(f'<div style="background:{PASTELS[(i + 1) % 5]}"><span>{esc(s)}</span><i></i></div>' for i, s in enumerate(p['items'])) + '</div>'
    if p['type'] == 'offer': body += f'<div class="deal"><b>{esc(p["price"])}</b>{esc(p["deal"])}</div>'
    if p.get('chips'): body += '<div class="chips">' + ''.join(f'<span>{esc(c)}</span>' for c in p['chips']) + '</div>'
    if p.get('sig'): body += f'<p class="sig">{esc(p["sig"])}</p>'
    dots = '<span class="dot" style="width:14px;height:14px;left:7%;top:24%"></span><span class="dot" style="width:9px;height:9px;left:62%;top:9%"></span><span class="dot" style="width:11px;height:11px;right:9%;top:58%;background:#E6DBCF;opacity:.9"></span>'
    deco = WAVES + LINES + dots
    if has_photo:
        cls.append('photo')
        pos = f';background-position:{esc(p["pos"])}' if p.get('pos') else ''
        ph = f'<div class="phbox"><div class="ph" style="background-image:url(/{esc(photo_path(p))}){pos}"></div>{ibubble(p)}</div>'
        inner = f'{deco}<div class="wrap">{top}{ph}<div class="body" style="margin-top:28px">{body}</div>{foot}</div>'
    else:
        inner = f'{deco}{PEBBLE}<div class="wrap">{top}<div class="mid">{body}</div>{foot}</div>'
    return f'<!doctype html><html><head><meta charset="utf-8"><style>{CSS}</style></head><body class="{" ".join(cls)}">{inner}</body></html>'

def main(src, out):
    week = json.load(open(src, encoding='utf-8'))
    os.makedirs(out, exist_ok=True)
    tmp = os.path.join(os.path.dirname(os.path.abspath(__file__)), '_render')
    os.makedirs(tmp, exist_ok=True)
    files = []
    with sync_playwright() as pw:
        b = pw.chromium.launch()
        for p in week['posts']:
            for story, tag, h in [(False, 'Instagram-4x5', 1350), (True, 'WhatsApp-9x16', 1920)]:
                fn = os.path.join(tmp, 'p.html')
                open(fn, 'w', encoding='utf-8').write(page(p, story))
                pg = b.new_page(viewport={'width': 1080, 'height': h})
                pg.goto(BASE + '/tools/movecia-week/_render/p.html'); pg.wait_for_timeout(500)
                name = f'{p["file"]}_{tag}.png'
                pg.screenshot(path=os.path.join(out, name)); pg.close()
                files.append(name)
        b.close()
    txt = [week.get('title', 'Movecia Woche'), '']
    for p in week['posts']:
        txt += [f'— {p["day"]} —', 'Instagram-Caption:', p['caption'], '', 'WhatsApp-Text:', p['whatsapp'], '']
    open(os.path.join(out, 'Texte.txt'), 'w', encoding='utf-8').write('\n'.join(txt))
    files.append('Texte.txt')
    z = os.path.join(out, week['zip'])
    with zipfile.ZipFile(z, 'w', zipfile.ZIP_DEFLATED) as zf:
        for f in files: zf.write(os.path.join(out, f), f)
    print(z)

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])

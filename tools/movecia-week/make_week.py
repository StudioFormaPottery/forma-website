#!/usr/bin/env python3
"""Movecia Wochen-Paket (Vivien, 25.10.2026): pro Tag ein fertiges Bild mit Text,
Instagram 4:5 (1080x1350) und WhatsApp-Status/Kanal 9:16 (1080x1920), dazu Texte.txt und ZIP.
Aufruf: python3 make_week.py woche.json ausgabeordner   (lokaler PHP-Server auf 127.0.0.1:8765 aus dem Repo-Ordner)
Typen: quote, photo, steps, poll, offer. Felder: file, type, kicker, title (| = Umbruch), em (kursives Akzentwort),
text, items (Liste), photo (Pfad ab Repo), cta, url, dark (bool)."""
import json, sys, os, zipfile, html
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8765'
CSS = """
@font-face{font-family:Cormorant;font-weight:300 700;src:url(/fonts/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:Cormorant;font-weight:300 700;font-style:italic;src:url(/fonts/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:Hanken;font-weight:300 700;src:url(/fonts/HankenGrotesk-VF.woff) format("woff")}
*{box-sizing:border-box;margin:0}
body{width:1080px;height:1350px;overflow:hidden;background:#F5E9DF;color:#2E2529;font-family:Hanken,sans-serif;position:relative}
body.s{height:1920px}
body.dark{background:#4A3A41;color:#FBF5F7}
.blob{position:absolute;border-radius:62% 38% 46% 54%/55% 45% 55% 45%}
.wrap{position:absolute;inset:0;padding:84px;display:flex;flex-direction:column;z-index:2}
.s .wrap{padding:180px 90px 260px}
.top{display:flex;justify-content:space-between;align-items:center}
.brand{display:flex;align-items:center;gap:14px;font-family:Cormorant,serif;font-size:44px}
.brand img{width:42px}.brand i{display:inline-block;width:11px;height:11px;border-radius:50%;background:#7D5E68}
.dark .brand i{background:#E8D6CA}
.k{font-size:25px;letter-spacing:.2em;text-transform:uppercase;font-weight:500;color:#7D5E68}
.dark .k{color:#E8D6CA}
h1{font-family:Cormorant,serif;font-weight:500;font-size:112px;line-height:.98;margin-top:22px}
.s h1{font-size:124px}
h1 em{font-style:italic;color:#7D5E68}.dark h1 em{color:#E8D6CA}
.text{font-size:34px;line-height:1.45;margin-top:30px;max-width:880px;color:#4A3A41}
.dark .text{color:#EADFDE}
.mid{margin:auto 0}
.steps{display:grid;gap:18px;margin-top:38px}
.steps div{display:grid;grid-template-columns:62px 1fr;gap:22px;align-items:center;background:#FCF6F0;border-radius:28px;padding:22px 28px;font-size:31px;line-height:1.35}
.steps span{display:grid;place-items:center;width:62px;height:62px;border-radius:50%;background:#E8D6CA;font-family:Cormorant,serif;font-size:34px}
.poll{display:grid;gap:20px;margin-top:44px}
.poll div{border:2px solid #E8D6CA;border-radius:99px;padding:28px 40px;font-size:36px;display:flex;justify-content:space-between}
.dark .poll div{border-color:rgba(232,214,202,.5)}
.foot{margin-top:auto;display:flex;justify-content:space-between;align-items:flex-end;gap:24px}
.url{font-size:27px;color:#7D5E68}.url b{display:block;font-family:Cormorant,serif;font-weight:500;font-size:42px;color:#4A3A41}
.dark .url{color:#E8D6CA}.dark .url b{color:#FBF5F7}
.btn{background:#7D5E68;color:#FBF5F7;border-radius:99px;padding:20px 36px;font-size:27px;white-space:nowrap}
.dark .btn{background:#E8D6CA;color:#4A3A41}
.ph{position:absolute;left:0;right:0;top:0;height:46%;background-size:cover;background-position:50% 35%;z-index:1}
.ph::before{content:"";position:absolute;inset:0;background:rgba(214,160,122,.17);mix-blend-mode:multiply}
.ph::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(245,233,223,0) 55%,#F5E9DF 100%)}
.photo .top .brand{background:rgba(252,246,240,.9);padding:10px 24px 10px 14px;border-radius:99px}
.photo .body{margin-top:auto}
.photo .k{display:inline-block;background:rgba(252,246,240,.94);color:#6B5658;padding:12px 24px;border-radius:99px}
.t-quote h1{font-size:134px}.s.t-quote h1{font-size:146px}
.sig{font-family:Cormorant,serif;font-style:italic;font-size:46px;color:#7D5E68;margin-top:28px}
.dark .sig{color:#E8D6CA}
.deal{margin-top:30px;background:#4A3A41;color:#FBF5F7;border-radius:32px;padding:30px 36px;font-size:30px;line-height:1.4}
.deal b{font-family:Cormorant,serif;font-weight:500;font-size:84px;display:block;line-height:1}
"""

def esc(x): return html.escape(x or '')

def title(p):
    t = esc(p.get('title', '')).replace('|', '<br>')
    if p.get('em'): t += ' <em>' + esc(p['em']) + '</em>'
    return t

def page(p, story):
    cls = ['s' if story else 'f', 't-' + p['type']] + (['dark'] if p.get('dark') else [])
    logo = '/img/movecia/movecia-zeichen-hell.svg' if p.get('dark') else '/img/movecia/movecia-zeichen-klein.svg'
    top = f'<div class="top"><div class="brand"><img src="{logo}"><span>Movecia<i></i></span></div><span class="k">{esc(p.get("day",""))}</span></div>'
    foot = ''
    if p.get('cta') or p.get('url'):
        foot = f'<div class="foot"><div class="url">{esc(p.get("cta",""))}<b>{esc(p.get("url",""))}</b></div><span class="btn">Link in Bio →</span></div>'
    body = f'<p class="k">{esc(p.get("kicker",""))}</p><h1>{title(p)}</h1>'
    if p.get('text'): body += f'<p class="text">{esc(p["text"])}</p>'
    if p['type'] == 'steps': body += '<div class="steps">' + ''.join(f'<div><span>{i+1}</span>{esc(s)}</div>' for i, s in enumerate(p['items'])) + '</div>'
    if p['type'] == 'poll': body += '<div class="poll">' + ''.join(f'<div><span>{esc(s)}</span><span>○</span></div>' for s in p['items']) + '</div>'
    if p['type'] == 'offer': body += f'<div class="deal"><b>{esc(p["price"])}</b>{esc(p["deal"])}</div>'
    if p.get('sig'): body += f'<p class="sig">{esc(p["sig"])}</p>'
    deco = '<span class="blob" style="width:560px;height:520px;right:-200px;top:-170px;background:' + ('rgba(232,214,202,.10)' if p.get('dark') else '#EADFDE') + '"></span>'
    if p['type'] == 'photo' or p.get('photo'):
        cls.append('photo')
        inner = f'<div class="ph" style="background-image:url(/{esc(p["photo"])})"></div><div class="wrap">{top}<div class="body">{body}</div>{foot}</div>'
    else:
        inner = f'{deco}<div class="wrap">{top}<div class="mid">{body}</div>{foot}</div>'
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

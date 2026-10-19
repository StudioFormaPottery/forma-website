#!/usr/bin/env python3
"""Wild Hearts Wochenplan als PDF (A4) aus einer JSON-Datei.
Aufruf: python3 build_week.py woche.json Ausgabe.pdf
JSON: {"range": "26. Okt – 1. Nov 2026", "theme": "...", "theme_text": "...",
       "posts": [{"day": "Mo 26.10.", "title": "...", "text": "...", "poll_q": "...", "poll_opts": ["..."], "photo": "..."}] (7 Stück),
       "feed": [{"day": "Mo 26.10.", "title": "...", "desc": "...", "caption": "..."}],
       "stories": ["..."], "tip": "..."}"""
import html, json, os, sys
from playwright.sync_api import sync_playwright
E = html.escape
HERE = os.path.dirname(os.path.abspath(__file__))
FONTS = os.path.join(HERE, '..', '..', 'fonts')
RHYTHM = [("Mo", "Check-in + Geschichte"), ("Di", "Ehrliche Frage"), ("Mi", "Bewegungs-Impuls"), ("Do", "Du bist nicht allein"),
          ("Fr", "Was dich leuchten lässt"), ("Sa", "Erlaubnis zu ruhen"), ("So", "Rückblick")]

def css():
    f = lambda n: 'file://' + os.path.abspath(os.path.join(FONTS, n))
    return f'''<style>
@font-face{{font-family:"Italiana";src:url({f("Italiana-Regular.woff")})}}
@font-face{{font-family:"Outfit";src:url({f("Outfit-Regular.woff")})}}
@font-face{{font-family:"Outfit";font-weight:600;src:url({f("Outfit-Bold.woff")})}}
@font-face{{font-family:"Hand";src:url({f("HomemadeApple-Regular.woff")})}}
@page{{size:A4;margin:0}}*{{box-sizing:border-box;margin:0;padding:0}}
body{{font-family:Outfit;color:#33211B;font-size:9.6pt;line-height:1.45}}
.pg{{width:210mm;height:297mm;padding:15mm 14mm 13mm;position:relative;overflow:hidden;page-break-after:always;background:#F8F1E8}}
.pg:last-child{{page-break-after:auto}}.hand{{font-family:Hand;line-height:1.4}}.ser{{font-family:Italiana;font-weight:400}}
h1{{font-family:Italiana;font-weight:400;font-size:32pt;color:#6E2A1F;line-height:1.02}}
h2{{font-family:Italiana;font-weight:400;font-size:20pt;color:#6E2A1F;line-height:1.05}}
.kick{{font-size:7.5pt;letter-spacing:.24em;text-transform:uppercase;color:#674B40}}
.card{{background:#FFFBF6;border:1px solid #DCC5B0;border-radius:5mm;padding:4.2mm 5mm;break-inside:avoid}}
.grid2{{display:grid;grid-template-columns:1fr 1fr;gap:4mm}}
.post .hd{{display:flex;justify-content:space-between;align-items:baseline}}.post .day{{font-weight:600;color:#93402F}}
.post .t{{font-family:Italiana;font-size:14pt;color:#3A2620}}.post .txt{{white-space:pre-line;margin-top:2mm;font-size:8.6pt}}
.poll{{margin-top:2.5mm;background:#DCE0D2;border-radius:3mm;padding:2.2mm 3mm;font-size:8.4pt}}
.photo{{margin-top:2mm;font-size:8pt;color:#674B40}}.photo:before{{content:"📷 "}}
.row{{display:flex;gap:3mm;align-items:flex-start;padding:2mm 0;border-bottom:1px dashed #DCC5B0;break-inside:avoid}}
.row .d{{flex:0 0 17mm;font-weight:600;color:#93402F}}.row .c{{flex:1}}.row .tt{{font-family:Italiana;font-size:12pt;color:#3A2620}}
.row .cap{{font-family:Hand;font-size:7.8pt;color:#6E2A1F;margin-top:.8mm}}
.tick{{display:inline-block;width:3.6mm;height:3.6mm;border:1px solid #93402F;border-radius:1mm}}
.rh{{display:grid;grid-template-columns:repeat(7,1fr);gap:2mm;margin-top:2mm}}.rh div{{background:#FFFBF6;border:1px solid #DCC5B0;border-radius:2.5mm;padding:2mm;font-size:7.6pt}}
.rh b{{display:block;font-family:Italiana;font-size:12pt;color:#93402F;font-weight:400}}
.num{{position:absolute;bottom:7mm;right:12mm;font-family:Hand;color:#93402F;font-size:9pt}}
</style>'''

def post(p):
    opts = ' · '.join(E(o) for o in p['poll_opts'])
    return (f'<div class="card post"><div class="hd"><span class="day">{E(p["day"])}</span><span class="tick"></span></div>'
            f'<p class="t">{E(p["title"])}</p><p class="txt">{E(p["text"])}</p>'
            f'<div class="poll"><b>Umfrage:</b> {E(p["poll_q"])}<br>{opts}</div><p class="photo">{E(p["photo"])}</p></div>')

def build(d):
    rh = ''.join(f'<div><b>{a}</b>{E(b)}</div>' for a, b in RHYTHM)
    feed = ''.join(f'<div class="row"><span class="d">{E(x["day"])}</span><span class="c"><span class="tt">{E(x["title"])}</span><br>{E(x["desc"])}<p class="cap">{E(x["caption"])}</p></span><span class="tick"></span></div>' for x in d.get('feed', []))
    st = ''.join(f'<li style="margin-top:.8mm">{E(s)}</li>' for s in d.get('stories', []))
    p1 = (f'<section class="pg"><p class="hand" style="font-size:13pt;color:#93402F">deine Woche, Schritt für Schritt ♡</p>'
          f'<h1>Wild Hearts Wochenplan</h1><p class="kick" style="margin-top:3mm">{E(d["range"])}</p>'
          f'<div class="card" style="margin-top:6mm;background:#93402F;border:0;color:#F5E8DD"><p class="kick" style="color:#E7C4AE">Thema der Woche</p>'
          f'<p class="ser" style="font-size:20pt;color:#F8F1E8;margin-top:1mm">{E(d["theme"])}</p><p style="margin-top:1.5mm">{E(d.get("theme_text", ""))}</p></div>'
          f'<h2 style="margin-top:6mm">Rhythmus im Channel</h2><div class="rh">{rh}</div>'
          f'<h2 style="margin-top:6mm">Feed @movementwithviv</h2><p style="font-size:8.5pt">Karussell oder stummes Video, Captions auf Englisch.</p><div style="margin-top:1mm">{feed}</div>'
          f'<div class="card" style="margin-top:5mm;background:#DCE0D2;border:0"><p class="ser" style="font-size:13pt">Storys diese Woche</p><ul style="margin:1mm 0 0 4mm">{st}</ul></div>'
          f'<span class="num">1</span></section>')
    ps = d['posts']
    tip = d.get('tip', '')
    tipcard = f'<div class="card" style="background:#DCE0D2;border:0"><p class="hand" style="font-size:12pt;color:#93402F">kleiner Tipp</p><p style="margin-top:2mm">{E(tip)}</p><p style="margin-top:2mm">Die Texte sind Vorschläge. Pass sie gern an, damit jedes Wort nach dir klingt.</p></div>'
    p2 = f'<section class="pg"><p class="kick">Channel · jeden Morgen 7–8 Uhr</p><h2>{E(ps[0]["day"])} bis {E(ps[3]["day"])}</h2><div class="grid2" style="margin-top:4mm">{"".join(post(p) for p in ps[:4])}</div><span class="num">2</span></section>'
    p3 = f'<section class="pg"><p class="kick">Channel · jeden Morgen 7–8 Uhr</p><h2>{E(ps[4]["day"])} bis {E(ps[-1]["day"])}</h2><div class="grid2" style="margin-top:4mm">{"".join(post(p) for p in ps[4:])}{tipcard}</div><span class="num">3</span></section>'
    return '<html><head><meta charset="utf-8">' + css() + '</head><body>' + p1 + p2 + p3 + '</body></html>'

if __name__ == '__main__':
    data = json.load(open(sys.argv[1], encoding='utf-8'))
    out = os.path.abspath(sys.argv[2])
    tmp = out[:-4] + '.html'
    open(tmp, 'w', encoding='utf-8').write(build(data))
    with sync_playwright() as p:
        b = p.chromium.launch(); pg = b.new_page()
        pg.goto('file://' + tmp); pg.wait_for_timeout(800)
        pg.pdf(path=out, format='A4', print_background=True, prefer_css_page_size=True)
    os.remove(tmp)
    print(out)

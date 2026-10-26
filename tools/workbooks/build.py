#!/usr/bin/env python3
"""Movecia-Workbooks als PDF (Vivien, 26.10.2026: alle Workbooks neu, cute, mit Themen-Icons).

Inhalte: api/seed/workbooks/content/<key>.<lang>.json (Format siehe SCHEMA.md) und für Prä-/Postnatal
api/natal-units.inc.php + content/natal-wissen.de.json. Ausgabe: api/seed/workbooks/pdf/<key>.<lang>.pdf
(Ordner per .htaccess gesperrt, ausgeliefert nur über die Mitglieder-Downloads).

Aufruf:  python3 tools/workbooks/build.py [key ...]        (ohne key: alle)
         python3 tools/workbooks/build.py --html KEY.LANG   (nur HTML zum Anschauen)
"""
import html, json, os, re, subprocess, sys
sys.path.insert(0, os.path.dirname(__file__))
from icons import icon, pose

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
CONTENT = os.path.join(ROOT, 'api', 'seed', 'workbooks', 'content')
OUT = os.path.join(ROOT, 'api', 'seed', 'workbooks', 'pdf')
e = lambda s: html.escape(str(s or ''), quote=True)
FONTS = 'file://' + os.path.join(ROOT, 'fonts')
LOGO = 'file://' + os.path.join(ROOT, 'img', 'movecia', 'movecia-zeichen-klein.svg')

T = {
    'de': dict(fact='Gut zu wissen', tip='Mein Tipp für dich', notes='Meine Notizen', week=['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
               prio='Meine drei wichtigsten Dinge', care='Das mache ich für mich', ingredients='Zutaten', steps='So geht’s',
               for_='für', page='Seite', only='nur für dich', time='Dauer', serves='Portionen', inhalt='Was dich erwartet',
               letter='Ein Brief für dich', mood='Wie geht es mir heute?', done='Geschafft?', inhale='ein', exhale='aus',
               breathcap='Atme mit dem Kreis: ein, wenn er wächst, aus, wenn er sanft kleiner wird.', exs='Deine Übungsabfolge',
               home='Für zu Hause', journal='Dein Journal', imp='Impuls', imp_t='Worum es heute ging', think='Meine Gedanken dazu',
               merk='Das nimmst du mit', med_after='Nimm dir danach einen Moment, bevor du aufstehst.', round='ein Kreis pro Durchgang'),
    'en': dict(fact='Good to know', tip='My tip for you', notes='My notes', week=['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
               prio='My three most important things', care='What I do for myself', ingredients='Ingredients', steps='How to',
               for_='for', page='Page', only='just for you', time='Time', serves='Serves', inhalt='What awaits you',
               letter='A letter for you', mood='How am I today?', done='Done?', inhale='in', exhale='out',
               breathcap='Breathe with the circle: in as it grows, out as it softly gets smaller.', exs='Your sequence',
               home='At home', journal='Your journal', imp='Talk', imp_t='What today was about', think='My thoughts',
               merk='What you take with you', med_after='Take a moment before you get up.', round='one circle per round'),
}

CSS = """
@font-face{font-family:"Cormorant";font-weight:300 700;src:url(FONTS/CormorantGaramond-VF.woff) format("woff")}
@font-face{font-family:"Cormorant";font-style:italic;font-weight:300 700;src:url(FONTS/CormorantGaramond-Italic-VF.woff) format("woff")}
@font-face{font-family:"Hanken";font-weight:300 700;src:url(FONTS/HankenGrotesk-VF.woff) format("woff")}
@font-face{font-family:"Hand";src:url(FONTS/HomemadeApple-Regular.woff) format("woff")}
@page{size:A4}
:root{--bg:#F2ECE4;--plum:#7D5E68;--deep:#4A3A41;--ink:#2E2529;--muted:#7D7076;--blush:#EADFDE;--peach:#E8D6CA;--rose:#D9B8B4;--line:#E3D8CD;--soft:#FBF4F2}
*{box-sizing:border-box}
html{-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;font-family:"Hanken",sans-serif;font-size:10.4pt;line-height:1.62;color:var(--ink)}
p{margin:0 0 2.4mm}
h1,h2,h3,h4{font-family:"Cormorant",serif;font-weight:500;margin:0;line-height:1.08}
.k{font-size:7.4pt;letter-spacing:.18em;text-transform:uppercase;color:var(--plum);font-weight:600;margin:0}
/* Deckblatt */
.cover{height:297mm;position:relative;overflow:hidden;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 26mm;break-after:page}
.cover .blob1{position:absolute;width:130mm;height:110mm;left:40mm;top:38mm;background:var(--peach);border-radius:58% 42% 50% 50%/48% 56% 44% 52%;opacity:.75}
.cover .blob2{position:absolute;width:90mm;height:90mm;right:-24mm;bottom:-20mm;background:var(--blush);border-radius:50%}
.cover .blob3{position:absolute;width:60mm;height:60mm;left:-18mm;bottom:40mm;background:var(--blush);border-radius:46% 54% 50% 50%;opacity:.7}
.cover .ring{position:absolute;width:118mm;height:100mm;left:44mm;top:40mm;border:1.4px solid var(--plum);border-radius:52% 48% 46% 54%/56% 46% 54% 44%;transform:rotate(9deg);opacity:.55}
.cover>*{position:relative}
.cover .logo{position:absolute;top:16mm;left:0;right:0;display:flex;justify-content:center;align-items:center;gap:2mm;font-family:"Cormorant";font-size:16pt;color:var(--plum)}
.cover .logo img{width:7mm;height:7mm}
.cover .big svg{width:52mm;height:52mm;filter:drop-shadow(0 3mm 4mm rgba(125,94,104,.18))}
.cover .tape{display:inline-block;margin:9mm 0 3mm;background:#fff;padding:1.6mm 5mm;font-size:8pt;letter-spacing:.16em;text-transform:uppercase;color:var(--plum);font-weight:600;transform:rotate(-2deg);border-radius:1mm;box-shadow:0 1mm 3mm rgba(74,58,65,.08)}
.cover h1{font-size:46pt;line-height:.98;max-width:150mm}
.cover .sub{font-family:"Cormorant";font-style:italic;font-size:17pt;color:var(--plum);margin-top:3mm;max-width:130mm}
.cover .for{font-family:"Hand";font-size:10pt;color:var(--muted);margin-top:12mm;display:flex;gap:3mm;align-items:flex-end}
.cover .for span{display:inline-block;width:55mm;border-bottom:1.3px dotted var(--plum);height:6mm}
.cover .sp{position:absolute;color:var(--plum);font-size:14pt}
.cover .merkbox{background:rgba(255,255,255,.7);border-radius:6mm;padding:5mm 6mm;margin-top:10mm;text-align:left;max-width:140mm}
.cover .merkbox ul{list-style:none;margin:2mm 0 0;padding:0}
.cover .merkbox li{display:flex;gap:2.6mm;margin:1.6mm 0;font-size:10pt}
.cover .merkbox li svg{flex:0 0 5mm;width:5mm;height:5mm;margin-top:.6mm}
/* Kapitel */
.ch{break-before:page}
.ch.first{break-before:auto}
.ch.cont{break-before:auto;margin-top:10mm}
.chh{display:flex;align-items:center;gap:5mm;margin-bottom:5mm;padding-bottom:4mm;border-bottom:1.3px dashed var(--line)}
.badge{flex:0 0 19mm;height:19mm;border-radius:46% 54% 50% 50%/55% 45% 55% 45%;background:var(--bg);display:flex;align-items:center;justify-content:center;border:1.2px dashed var(--rose)}
.badge svg{width:13mm;height:13mm}
.chh h2{font-size:24pt;margin-top:.6mm}
.chintro{font-family:"Cormorant";font-style:italic;font-size:13.5pt;color:var(--plum);line-height:1.35;margin:0 0 4mm}
h3.sh{font-size:15.5pt;margin:5mm 0 2mm;color:var(--deep);break-after:avoid}
ul.l,ol.l{margin:1mm 0 3mm;padding-left:5.5mm}
ul.l li,ol.l li{margin:.8mm 0}
ul.l li::marker{color:var(--rose)}
ol.l li::marker{color:var(--plum);font-family:"Cormorant";font-weight:600}
.box{break-inside:avoid;border-radius:4.5mm;padding:4mm 5mm;margin:3.5mm 0}
.fact{background:var(--bg)}
.fact .bh{display:flex;align-items:center;gap:2.4mm;margin-bottom:1.4mm}
.fact .bh svg{width:7mm;height:7mm}
.fact h4{font-size:13pt}
.tip{background:var(--soft);border:1px solid var(--blush);display:flex;gap:3mm;transform:rotate(-.4deg)}
.tip svg{flex:0 0 8mm;width:8mm;height:8mm}
.tip .tl{font-family:"Hand";font-size:8pt;color:var(--plum);margin-bottom:.6mm}
.tip p{font-family:"Cormorant";font-style:italic;font-size:12.6pt;line-height:1.38;color:var(--deep);margin:0}
.quote{break-inside:avoid;text-align:center;margin:6mm 6mm;padding:6mm 4mm;position:relative}
.quote::before,.quote::after{content:"✦";position:absolute;color:var(--rose);font-size:11pt}
.quote::before{left:6mm;top:2mm}.quote::after{right:8mm;bottom:2mm}
.quote p{font-family:"Cormorant";font-style:italic;font-size:19pt;line-height:1.2;color:var(--plum);margin:0}
.ex{border:1.2px solid var(--line);background:#fff}
.ex .bh{display:flex;align-items:center;gap:3mm;margin-bottom:2mm}
.ex .bh svg{width:9mm;height:9mm}
.ex h4{font-size:14pt}
.chip{display:inline-flex;align-items:center;gap:1.2mm;background:var(--bg);color:var(--plum);font-size:7.8pt;font-weight:600;border-radius:9mm;padding:.6mm 2.6mm .6mm 1.4mm;margin-left:auto;white-space:nowrap}
.chip svg{width:4mm;height:4mm}
.prompts{break-inside:auto;margin:3mm 0}
.prompts h4{font-size:13.5pt;display:flex;align-items:center;gap:2mm;margin-bottom:1mm}
.prompts h4 svg{width:7mm;height:7mm}
.q{break-inside:avoid;margin-top:3mm}
.q p{font-weight:500;margin:0}
.ln{display:block;height:8.2mm;border-bottom:1.1px dotted var(--rose)}
.check{list-style:none;padding:0;margin:2mm 0 0;columns:2;column-gap:7mm}
.check.one{columns:1}
.check li{display:flex;gap:2.4mm;align-items:flex-start;margin:1.2mm 0;break-inside:avoid}
.check li::before{content:"";flex:0 0 4mm;height:4mm;border:1.2px solid var(--plum);border-radius:1.2mm;margin-top:.8mm}
table.t{width:100%;border-collapse:separate;border-spacing:0;margin-top:2mm;font-size:9pt}
table.t th{background:var(--blush);color:var(--deep);font-weight:600;text-align:left;padding:2mm 2.4mm;font-size:8.4pt}
table.t th:first-child{border-radius:2.6mm 0 0 0}table.t th:last-child{border-radius:0 2.6mm 0 0}
table.t td{border-bottom:1px dotted var(--rose);padding:2.2mm 2.4mm;height:9mm;vertical-align:top}
table.t td+td,table.t th+th{border-left:1px solid var(--line)}
.scale .row{display:flex;align-items:center;gap:3mm;margin:2mm 0;break-inside:avoid}
.scale .row span.lab{flex:0 0 40mm;font-size:9.4pt}
.scale .dots{display:flex;gap:1.6mm}
.scale .dots i{width:6.2mm;height:6.2mm;border-radius:50%;border:1.1px solid var(--rose);font-style:normal;font-size:6.8pt;color:var(--muted);display:flex;align-items:center;justify-content:center}
.scale .ends{display:flex;justify-content:space-between;font-size:7.6pt;color:var(--muted);margin-left:43mm;max-width:79mm}
.tracker table{width:100%;border-collapse:collapse;margin-top:2mm;font-size:9pt}
.tracker th{font-size:7.6pt;color:var(--plum);font-weight:600;padding:1mm}
.tracker td{padding:1.4mm 1mm;border-bottom:1px dotted var(--line)}
.tracker td.c{text-align:center}
.tracker td.c i{display:inline-block;width:5mm;height:5mm;border-radius:50%;border:1.1px solid var(--rose)}
.breath{display:flex;gap:6mm;align-items:center}
.ring{position:relative;flex:0 0 40mm;height:40mm;display:flex;align-items:center;justify-content:center}
.ring span{position:absolute;border-radius:50%}
.ring .r1{inset:0;border:1.2px dashed var(--rose)}.ring .r2{inset:6mm;background:var(--bg);border:1.2px solid var(--peach)}.ring .r3{inset:12mm;background:var(--peach)}
.ring b{position:relative;font-family:"Cormorant";font-size:12pt;color:var(--plum)}
.breath .pat{font-family:"Cormorant";font-size:17pt;color:var(--plum);letter-spacing:.06em}
.planner{break-before:page;break-after:page}
.planner .days{display:grid;grid-template-columns:1fr 1fr;gap:3mm;margin-top:3mm}
.planner .day{border:1.2px solid var(--line);border-radius:4mm;padding:2.4mm 3mm;height:44mm}
.planner .day b{font-family:"Cormorant";font-size:13pt;color:var(--plum)}
.planner .day .ln{height:7.6mm}
.planner .side{border-radius:4mm;padding:2.4mm 3mm;height:44mm;background:var(--bg)}
.recipe{break-inside:avoid;border:1.2px solid var(--line);background:#fff}
.recipe .meta{display:flex;gap:2mm;margin:1mm 0 2.4mm}
.recipe .cols{display:grid;grid-template-columns:38% 1fr;gap:5mm}
.recipe h5{font-size:7.4pt;letter-spacing:.16em;text-transform:uppercase;color:var(--plum);margin:0 0 1mm}
.recipe ul,.recipe ol{margin:0;padding-left:4.6mm;font-size:9.2pt}
.recipe .note{font-family:"Cormorant";font-style:italic;font-size:11.5pt;color:var(--muted);margin-top:2mm}
.notes{margin-top:4mm}
/* Prä-/Postnatal-Übungen */
.nex{display:flex;gap:4mm;padding:3.6mm 0;border-top:1.2px dashed var(--line);break-inside:avoid}
.nex:first-of-type{border-top:0}
.nex .pi{position:relative;flex:0 0 19mm;height:19mm;border-radius:50%;background:var(--blush);display:flex;align-items:center;justify-content:center}
.nex .pi svg{width:13mm;height:13mm}
.nex .pi b{position:absolute;top:-1mm;right:-1mm;width:6.6mm;height:6.6mm;border-radius:50%;background:var(--plum);color:#fff;font-family:"Cormorant";font-size:10pt;display:flex;align-items:center;justify-content:center;border:1.6px solid #fff}
.nex h4{font-size:14.5pt}
.nex .tipn{font-family:"Cormorant";font-style:italic;font-size:12pt;color:var(--deep);background:var(--soft);border-radius:3mm;padding:1.6mm 3mm;margin-top:1.6mm}
.done{display:flex;align-items:center;gap:2mm;font-size:8.4pt;color:var(--muted);margin-top:3mm}
.done i{width:5mm;height:5mm;border-radius:50%;border:1.1px solid var(--rose)}
.done em{font-family:"Hand";font-style:normal;font-size:7pt}
.med p{font-family:"Cormorant";font-size:13.6pt;line-height:1.5}
.homebox{background:var(--bg);border-radius:4.5mm;padding:4mm 5mm;font-size:10.6pt}
.moods{display:flex;gap:3mm;margin-top:2mm}
.moods i{width:10mm;height:10mm;border-radius:50%;background:var(--blush);display:flex;align-items:center;justify-content:center;color:var(--plum);font-style:normal;font-size:12pt}
.close{display:flex;gap:3mm;align-items:center;font-family:"Cormorant";font-style:italic;font-size:14pt;color:var(--plum);margin-top:6mm;break-inside:avoid}
.close svg{flex:0 0 10mm;width:10mm;height:10mm}
/* Brief + Übersicht */
.letter p{font-size:10.8pt}
.letter .hi{font-family:"Cormorant";font-style:italic;font-size:17pt;color:var(--plum);margin-bottom:3mm}
.letter .sig{font-family:"Hand";font-size:11pt;color:var(--plum);margin-top:5mm}
.ov{display:grid;grid-template-columns:1fr 1fr;gap:3.4mm;margin-top:6mm}
.ov div{display:flex;gap:3mm;background:var(--bg);border-radius:4mm;padding:3.4mm 3.6mm;break-inside:avoid}
.ov svg{flex:0 0 10mm;width:10mm;height:10mm}
.ov b{font-family:"Cormorant";font-size:12.6pt;font-weight:600;display:block;line-height:1.15}
.ov span{font-size:8.6pt;color:var(--muted)}
.end{break-before:page;height:240mm;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}
.end svg{width:30mm;height:30mm}
.end p{font-family:"Cormorant";font-style:italic;font-size:20pt;color:var(--plum);max-width:120mm;line-height:1.25;margin-top:6mm}
.end .sig{font-family:"Hand";font-size:12pt;margin-top:6mm}
""".replace('FONTS', FONTS)


def lines(n):
    return ''.join('<span class="ln"></span>' for _ in range(n))


def block(b, L):
    t = b.get('t')
    if t == 'p':
        return f'<p>{e(b["text"])}</p>'
    if t == 'h':
        return f'<h3 class="sh">{e(b["text"])}</h3>'
    if t == 'list':
        return '<ul class="l">' + ''.join(f'<li>{e(x)}</li>' for x in b['items']) + '</ul>'
    if t == 'steps':
        return '<ol class="l">' + ''.join(f'<li>{e(x)}</li>' for x in b['items']) + '</ol>'
    if t == 'fact':
        return f'<div class="box fact"><div class="bh">{icon("impuls", 26)}<div><p class="k">{L["fact"]}</p><h4>{e(b.get("title"))}</h4></div></div><p>{e(b.get("text"))}</p></div>'
    if t == 'tip':
        return f'<div class="box tip">{icon("tipp", 30)}<div><p class="tl">{L["tip"]}</p><p>{e(b["text"])}</p></div></div>'
    if t == 'quote':
        return f'<div class="quote"><p>{e(b["text"])}</p></div>'
    if t == 'exercise':
        st = b.get('steps') or []
        return (f'<div class="box ex"><div class="bh">{icon("uebung", 34)}<h4>{e(b.get("title"))}</h4>'
                + (f'<span class="chip">{icon("uhr", 15)}{e(b["time"])}</span>' if b.get('time') else '') + '</div>'
                + (f'<p>{e(b["intro"])}</p>' if b.get('intro') else '')
                + ('<ol class="l">' + ''.join(f'<li>{e(x)}</li>' for x in st) + '</ol>' if st else '') + '</div>')
    if t == 'prompts':
        n = int(b.get('lines') or 3)
        return ('<div class="prompts">' + (f'<h4>{icon("journal", 26)}{e(b["title"])}</h4>' if b.get('title') else '')
                + ''.join(f'<div class="q"><p>{e(q)}</p>{lines(n)}</div>' for q in b['items']) + '</div>')
    if t == 'checklist':
        it = b['items']
        return (f'<div class="box fact"><div class="bh">{icon("haken", 26)}<h4>{e(b.get("title"))}</h4></div>'
                + f'<ul class="check{" one" if len(it) < 6 or max(len(x) for x in it) > 48 else ""}">' + ''.join(f'<li>{e(x)}</li>' for x in it) + '</ul></div>')
    if t == 'table':
        cols = b['cols']
        rows = b['rows'] if isinstance(b['rows'], list) else [[''] * len(cols) for _ in range(int(b['rows']))]
        return (f'<div class="box" style="padding:0;margin-top:4mm"><h4 style="font-size:13.5pt;margin-bottom:1mm">{e(b.get("title"))}</h4><table class="t"><tr>'
                + ''.join(f'<th>{e(c)}</th>' for c in cols) + '</tr>'
                + ''.join('<tr>' + ''.join(f'<td>{e(c)}</td>' for c in (r + [''] * len(cols))[:len(cols)]) + '</tr>' for r in rows) + '</table></div>')
    if t == 'scale':
        dots = ''.join(f'<i>{i}</i>' for i in range(1, 11))
        return (f'<div class="box fact scale"><div class="bh">{icon("stimmung", 26)}<h4>{e(b.get("title"))}</h4></div>'
                + ''.join(f'<div class="row"><span class="lab">{e(x)}</span><span class="dots">{dots}</span></div>' for x in b['items'])
                + (f'<div class="ends"><span>{e(b.get("low"))}</span><span>{e(b.get("high"))}</span></div>' if b.get('low') else '') + '</div>')
    if t == 'tracker':
        return (f'<div class="box fact tracker"><div class="bh">{icon("kalender", 26)}<h4>{e(b.get("title"))}</h4></div><table><tr><th></th>'
                + ''.join(f'<th>{d}</th>' for d in L['week']) + '</tr>'
                + ''.join(f'<tr><td>{e(x)}</td>' + '<td class="c"><i></i></td>' * 7 + '</tr>' for x in b['items']) + '</table></div>')
    if t == 'breath':
        return (f'<div class="box fact"><div class="breath"><div class="ring"><span class="r1"></span><span class="r2"></span><span class="r3"></span><b>{L["inhale"]} · {L["exhale"]}</b></div>'
                f'<div><p class="k">Breathwork</p><h4>{e(b.get("title"))}</h4>' + (f'<p class="pat">{e(b["pattern"])}</p>' if b.get('pattern') else '')
                + f'<p>{e(b.get("text"))}</p></div></div></div>')
    if t == 'planner':
        days = ''.join(f'<div class="day"><b>{d}</b>{lines(4)}</div>' for d in L['week'])
        return (f'<div class="planner"><div class="chh"><span class="badge">{icon("kalender")}</span><div><p class="k">Planner</p><h2>{e(b.get("title"))}</h2></div></div>'
                f'<div class="days">{days}<div class="side"><p class="k">{L["prio"]}</p>{lines(4)}</div></div>'
                f'<div class="days" style="grid-template-columns:1fr"><div class="side" style="height:auto"><p class="k">{L["care"]}</p>{lines(2)}</div></div></div>')
    if t == 'recipe':
        meta = ''.join(x for x in [f'<span class="chip" style="margin:0">{icon("uhr", 15)}{e(b["time"])}</span>' if b.get('time') else '',
                                   f'<span class="chip" style="margin:0">{icon("herz", 15)}{L["serves"]}: {e(b["serves"])}</span>' if b.get('serves') else ''])
        return (f'<div class="box recipe"><div class="ex bh" style="display:flex;align-items:center;gap:3mm">{icon("topf", 34)}<h4 style="font-size:15pt">{e(b.get("title"))}</h4></div>'
                f'<div class="meta">{meta}</div><div class="cols"><div><h5>{L["ingredients"]}</h5><ul>' + ''.join(f'<li>{e(x)}</li>' for x in b.get('ingredients', []))
                + f'</ul></div><div><h5>{L["steps"]}</h5><ol>' + ''.join(f'<li>{e(x)}</li>' for x in b.get('steps', [])) + '</ol></div></div>'
                + (f'<p class="note">{e(b["note"])}</p>' if b.get('note') else '') + '</div>')
    if t == 'notes':
        return f'<div class="notes prompts"><h4>{icon("journal", 26)}{e(b.get("title") or L["notes"])}</h4>{lines(int(b.get("lines") or 6))}</div>'
    if t == 'pagebreak':
        return '<div style="break-after:page"></div>'
    # interne Blöcke für Prä-/Postnatal
    if t == 'nex':
        o = ''
        for i, (nm, dose, how, tip) in enumerate(b['items']):
            o += (f'<div class="nex"><div class="pi">{icon(pose(nm), 48)}<b>{i + 1}</b></div><div><h4>{e(nm)}</h4>'
                  f'<span class="chip" style="margin:1mm 0 1.4mm">{icon("uhr", 15)}{e(dose)}</span><p>{e(how)}</p>'
                  + (f'<p class="tipn">{e(tip)}</p>' if tip else '') + '</div></div>')
        return o + f'<p class="done">{L["done"]} <i></i><i></i><i></i> <em>{L["round"]}</em></p>'
    if t == 'med':
        return '<div class="med">' + ''.join(f'<p>{e(x)}</p>' for x in b['text'].split('\n\n')) + '</div>' + f'<div class="box fact" style="display:flex;gap:3mm;align-items:center">{icon("zweig", 30)}<p style="margin:0">{L["med_after"]}</p></div>'
    if t == 'home':
        return f'<div class="homebox">{e(b["text"])}</div>'
    if t == 'mood':
        return f'<div class="box fact"><div class="bh">{icon("stimmung", 26)}<h4>{L["mood"]}</h4></div><div class="moods"><i>☁</i><i>◌</i><i>☀</i><i>♡</i></div>{lines(2)}</div>'
    raise ValueError('unbekannter Block: ' + str(t))


def chapter(c, L):
    return (f'<section class="ch{" cont" if c.get("cont") else ""}"><div class="chh"><span class="badge">{icon(c.get("icon", "stern"))}</span><div><p class="k">{e(c.get("kicker"))}</p><h2>{e(c["title"])}</h2></div></div>'
            + (f'<p class="chintro">{e(c["intro"])}</p>' if c.get('intro') else '') + ''.join(block(b, L) for b in c.get('blocks', [])) + '</section>')


def render(wb, cover_svg=None, merk=None):
    L = T[wb.get('lang', 'de')]
    big = cover_svg or icon(wb.get('icon', 'stern'), 150)
    cov = (f'<section class="cover"><span class="blob1"></span><span class="blob3"></span><span class="blob2"></span><span class="ring"></span>'
           f'<div class="logo"><img src="{LOGO}">Movecia</div><span class="sp" style="left:34mm;top:60mm">✦</span><span class="sp" style="right:40mm;top:52mm;color:#D9B8B4;font-size:18pt">✧</span><span class="sp" style="right:52mm;top:128mm;color:#D9B8B4">♡</span>'
           f'<div class="big">{big}</div><p class="tape">{e(wb.get("eyebrow"))}</p><h1>{e(wb["title"])}</h1>'
           + (f'<p class="sub">{e(wb["subtitle"])}</p>' if wb.get('subtitle') else '')
           + (f'<div class="merkbox"><p class="k">{L["merk"]}</p><ul>' + ''.join(f'<li>{icon("merk", 20)}<span>{e(m)}</span></li>' for m in merk) + '</ul></div>' if merk else '')
           + f'<p class="for">{L["for_"]} <span></span></p></section>')
    body = ''
    if wb.get('letter'):
        ps = wb['letter']
        body += (f'<section class="letter"><div class="chh"><span class="badge">{icon("herz")}</span><div><p class="k">{L["letter"]}</p><h2>{"Hallo, du" if L is T["de"] else "Hello, you"}</h2></div></div>'
                 + ''.join(f'<p>{e(p)}</p>' for p in ps) + '<p class="sig">♡ Vivien</p>')
        if wb.get('overview'):
            body += f'<p class="k" style="margin-top:7mm">{L["inhalt"]}</p><div class="ov">' + ''.join(
                f'<div>{icon(o.get("icon", "stern"), 38)}<p style="margin:0"><b>{e(o["title"])}</b><span>{e(o.get("text"))}</span></p></div>' for o in wb['overview']) + '</div>'
        body += '</section>'
    body += ''.join(chapter(c, L) for c in wb['chapters'])
    if wb.get('closing'):
        body += f'<section class="end">{icon(wb.get("icon", "stern"), 120)}<p>{e(wb["closing"])}</p><p class="sig">♡ Vivien</p></section>'
    doc = lambda b: f'<!doctype html><html lang="{wb.get("lang", "de")}"><head><meta charset="utf-8"><title>{e(wb["title"])}</title><style>{CSS}</style></head><body>{b}</body></html>'
    return doc(cov), doc(body.replace('<section class="ch">', '<section class="ch first">', 1) if not wb.get('letter') else body)


def natal_books():
    data = json.loads(subprocess.check_output(['php', os.path.join(os.path.dirname(__file__), 'natal_dump.php')]))
    wp = os.path.join(CONTENT, 'natal-wissen.de.json')
    wissen = json.load(open(wp, encoding='utf8')) if os.path.exists(wp) else {}
    L = T['de']
    for k, u in data.items():
        phase = 'Pränatal' if k.startswith('prae') else 'Postnatal'
        num = int(re.sub(r'\D', '', k))
        ch = [
            dict(icon='impuls', kicker=L['imp'], title=L['imp_t'], blocks=[{'t': 'p', 'text': x} for x in u['impuls'].split('\n\n')] + [{'t': 'notes', 'title': L['think'], 'lines': 4}]),
            dict(icon='uebung', kicker=L['exs'], title='So bewegen wir uns', blocks=[{'t': 'nex', 'items': u['ex']}]),
            dict(icon='atem', kicker='Breathwork', title=u['breath'][0], blocks=[{'t': 'breath', 'title': '', 'text': L['breathcap']}] + [{'t': 'p', 'text': x} for x in u['breath'][1].split('\n\n')]),
            dict(cont=True, icon='meditation', kicker='Meditation', title=u['med'][0], blocks=[{'t': 'med', 'text': u['med'][1]}]),
        ]
        w = wissen.get(k)
        if w:
            ch.append(dict(icon=w.get('icon', 'impuls'), kicker=L['fact'], title=w.get('title', L['fact']), intro=w.get('intro'), blocks=w.get('blocks', [])))
        ch.append(dict(icon='home', kicker='Für dich', title=L['home'], blocks=[{'t': 'home', 'text': u['home']}, {'t': 'tracker', 'title': 'Meine Woche', 'items': ['Morgens', 'Mittags', 'Abends']}, {'t': 'mood'},
                                                                              {'t': 'prompts', 'title': L['journal'], 'items': u['journal'], 'lines': 3}]))
        wb = dict(key='natal-' + k, lang='de', eyebrow=f'Workbook · {phase} · Einheit {num}', title=u['title'], subtitle=u['theme'], chapters=ch, closing=u['close'])
        yield wb, u['icon_svg'], u['merk']


def pdf(docs, path):
    from playwright.sync_api import sync_playwright
    cover, body = docs
    title = re.search(r'<title>(.*?)</title>', body).group(1)
    parts = []
    with sync_playwright() as p:
        b = p.chromium.launch()
        for i, h in enumerate([cover, body]):
            tmp = f'{path}.{i}.html'
            open(tmp, 'w', encoding='utf8').write(h)
            pg = b.new_page()
            pg.goto('file://' + tmp, wait_until='networkidle')
            pg.wait_for_timeout(300)
            out = f'{path}.{i}.pdf'
            if i == 0:
                pg.pdf(path=out, format='A4', print_background=True, margin={'top': '0', 'bottom': '0', 'left': '0', 'right': '0'}, page_ranges='1')
            else:
                pg.pdf(path=out, format='A4', print_background=True, display_header_footer=True,
                       header_template='<div style="width:100%;font-size:7px;color:#7D7076;padding:0 17mm;display:flex;justify-content:space-between;font-family:Georgia,serif;letter-spacing:.08em"><span>MOVECIA</span><span>' + title + '</span></div>',
                       footer_template='<div style="width:100%;font-size:7px;color:#7D7076;text-align:center;font-family:Georgia,serif"><span class="pageNumber"></span> / <span class="totalPages"></span> &#183; &#9825; &#183; moveciamovement.com</div>',
                       margin={'top': '20mm', 'bottom': '18mm', 'left': '17mm', 'right': '17mm'})
            parts.append(out)
            os.remove(tmp)
        b.close()
    subprocess.check_call(['qpdf', '--empty', '--pages', *parts, '--', path])
    for f in parts:
        os.remove(f)


def main():
    os.makedirs(OUT, exist_ok=True)
    args = sys.argv[1:]
    if args[:1] == ['--html']:
        key = args[1]
        if key.startswith('natal-'):
            for wb, svg, merk in natal_books():
                if wb['key'] + '.de' == key:
                    print(''.join(render(wb, svg, merk)))
        else:
            print(''.join(render(json.load(open(os.path.join(CONTENT, key + '.json'), encoding='utf8')))))
        return
    jobs = []
    for f in sorted(os.listdir(CONTENT)):
        if f.endswith('.json') and not f.startswith('natal-wissen'):
            wb = json.load(open(os.path.join(CONTENT, f), encoding='utf8'))
            jobs.append((wb['key'] + '.' + wb['lang'], lambda wb=wb: render(wb)))
    if os.path.exists(os.path.join(CONTENT, 'natal-wissen.de.json')):
        for wb, svg, merk in natal_books():
            jobs.append((wb['key'] + '.de', lambda wb=wb, svg=svg, merk=merk: render(wb, svg, merk)))
    for name, fn in jobs:
        if args and not any(name.startswith(a) for a in args):
            continue
        out = os.path.join(OUT, name + '.pdf')
        pdf(fn(), out)
        print('ok', name, os.path.getsize(out) // 1024, 'KB')


if __name__ == '__main__':
    main()

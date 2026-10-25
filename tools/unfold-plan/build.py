#!/usr/bin/env python3
# Baut aus uebungen.py + videos.py: api/seed/unfold-videos.json (für die Website) und den Filmplan als HTML (für das PDF).
# Aufruf: python3 tools/unfold-plan/build.py OUT_DIR
import json, sys, os, datetime as dt, html
sys.path.insert(0, os.path.dirname(__file__))
from uebungen import U
from videos import V, MONTHS

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
OUT = sys.argv[1] if len(sys.argv) > 1 else '.'
START = dt.date(2027, 1, 10)           # erster Live-Call (Sonntag)
MON1 = dt.date(2027, 1, 11)            # erste Woche Montag
MDE = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']
WD = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
CAT = {'Somatic': 'somatic', 'Atem': 'breath', 'Movement': 'flow', 'Yoga': 'flow', 'Bodyweight': 'body'}
e = html.escape

def de(d): return f'{WD[d.weekday()]}, {d.day}. {MDE[d.month]} {d.year}'
def month_of(w):
    for a, b, *rest in MONTHS:
        if a <= w <= b: return (a, b, *rest)

# Prüfen
for v in V:
    for _, _, ex in v['b']:
        for k, _ in ex: assert k in U, (v['de'], k)
assert len(V) == 26

seed = []
for i, v in enumerate(V):
    d = MON1 + dt.timedelta(days=7 * (v['w'] - 1) + (0 if v['s'] == 'A' else 3))
    v['date'] = d
    styles = []
    for _, _, ex in v['b']:
        for k, _ in ex:
            s = U[k][1]
            if s not in styles: styles.append(s)
    v['styles'] = styles
    # Hauptart für die Filter im Mitgliederbereich
    main = 'body' if v['s'] == 'A' else ('somatic' if 'Somatic' in styles[:2] else 'flow')
    m = month_of(v['w'])
    seed.append({'week': v['w'], 'slot': v['s'], 'month': MONTHS.index(m), 'unlock': d.isoformat(), 'minutes': v['min'],
        'energy': 'full' if v['s'] == 'A' else 'tired', 'cat': main,
        'title_de': f"Woche {v['w']} · {v['de']}", 'title_en': f"Week {v['w']} · {v['en']}",
        'text_de': v['ziel'] + ' Mit ' + ', '.join(styles) + '.', 'text_en': v['sum_en'],
        'script_de': '\n'.join(f"[{b[0]} · ca. {b[1]} Min.]\n" + '\n'.join(f"{U[k][0]} – {dose}" for k, dose in b[2]) + '\n' for b in v['b'])})
os.makedirs(os.path.join(ROOT, 'api', 'seed'), exist_ok=True)
json.dump({'start': START.isoformat(), 'months': [{'from': a, 'to': b, 'de': c, 'en': d2, 'intro_de': f, 'intro_en': g} for a, b, c, d2, f, g in MONTHS], 'videos': seed},
          open(os.path.join(ROOT, 'api', 'seed', 'unfold-videos.json'), 'w'), ensure_ascii=False, indent=1)

# ---------------- Filmplan (HTML → PDF) ----------------
css = open(os.path.join(os.path.dirname(__file__), 'plan.css')).read()
fonts = os.path.join(ROOT, 'fonts')
css = css.replace('FONTS', 'file://' + fonts)
h = [f'<!doctype html><html lang="de"><head><meta charset="utf-8"><style>{css}</style></head><body>']

# Deckblatt
h.append(f'''<section class="page cover"><div class="blob b1"></div><div class="blob b2"></div>
<img class="logo" src="file://{ROOT}/img/movecia/movecia-zeichen.svg">
<p class="k">Unfold · The Mindful Becoming</p><h1>Dein Filmplan</h1><p class="sub">26 Bewegungsvideos für drei Monate</p>
<div class="facts"><span>Start mit dem ersten Live-Call am So, 10. Jänner 2027</span><span>2 Videos pro Woche · 13 Wochen</span><span>Yoga · Bodyweight · Somatic · Movement</span></div>
<p class="foot">Movecia · nur für Vivien · Stand 25. Oktober 2026</p></section>''')

# So filmst du
h.append('''<section class="page"><p class="k">Bevor du filmst</p><h2>So ist der Plan aufgebaut</h2>
<div class="cols"><div>
<h3>Zwei Videos pro Woche</h3><p><b>Video A · Full Day</b> (ca. 30 Min.) erscheint jeden <b>Montag</b>. Es ist das kräftigere Programm mit allen vier Elementen: Ankommen, Bewegung, Kraft ohne Erschöpfung, Yoga-Flow und Ausklingen.</p>
<p><b>Video B · Tired Day</b> (ca. 15 Min.) erscheint jeden <b>Donnerstag</b>. Es ist sanft, viel am Boden, mit Somatic, Atem und Ruhe, für die Tage, an denen nicht mehr geht.</p>
<h3>Drei Monate, drei Kapitel</h3>
<p><b>Wurzeln</b> (Woche 1–4): Erden, Atem, Füße und Becken.<br><b>Knospen</b> (Woche 5–8): Wirbelsäule, Herzraum, Kraft wächst.<br><b>Entfalten</b> (Woche 9–13): Gleichgewicht, Raum und Fluss.</p>
<h3>Ablauf wie immer</h3><p>Du filmst die Abfolge ohne zu sprechen. Danach schreibe ich dir zu jedem Video das Voice-over mit Zeitmarken auf Deutsch und Englisch, du sprichst es mit dem Teleprompter ein. Die Abfolgen liegen schon als Sprechtext-Gerüst bei jeder Lektion in deiner Verwaltung.</p>
</div><div>
<h3>Beim Filmen</h3><ul>
<li>Querformat, Handy auf Hüfthöhe, schräg von vorne (etwa 45°), dein ganzer Körper ist immer im Bild, auch im Liegen und in breiten Haltungen.</li>
<li>Matte quer zur Kamera, das Licht kommt seitlich vom Fenster.</li>
<li>Ruhige, einfarbige Kleidung, die zu Beige und Pflaume passt.</li>
<li>Jede Übung so lange halten, wie es im Plan steht, und Wiederholungen ruhig mitzählen, damit das Voice-over später genau passt.</li>
<li>Zwischen den Übungen zwei, drei Sekunden still sein, das macht den Schnitt leichter.</li>
<li>Immer beide Seiten vollständig zeigen.</li>
<li>Bei den Tired-Day-Videos gern die leichtere Variante zeigen (sie steht bei jeder Übung).</li>
<li>Musik erst beim Schnitt dazugeben (Epidemic Sound).</li>
</ul>
<h3>Hochladen</h3><p>Verwaltung → Movecia → Method &amp; Unfold → Unfold-Monat → Lektion → Video auswählen. Jedes Video öffnet sich am Erscheinungstag von selbst.</p>
</div></div></section>''')

# Übersicht
rows = []
for v in V:
    rows.append(f"<tr class='{'a' if v['s']=='A' else 'b'}'><td>{v['w']}</td><td>{e(de(v['date']))}</td><td><b>{e(v['de'])}</b><br><span class='mut'>{e(', '.join(v['styles']))}</span></td><td>{v['min']} Min.</td><td>{'Full Day' if v['s']=='A' else 'Tired Day'}</td><td class='box'>☐ ☐ ☐</td></tr>")
h.append(f'''<section class="page"><p class="k">Übersicht</p><h2>Alle 26 Videos</h2>
<p class="mut small">Kästchen: gefilmt · Voice-over Deutsch · Voice-over Englisch. Gut wäre: Woche 1–4 bis 3. Jänner gefilmt, Woche 5–8 bis 31. Jänner, Woche 9–13 bis 28. Februar.</p>
<table class="ov"><thead><tr><th>Wo.</th><th>Erscheint</th><th>Video</th><th>Dauer</th><th>Typ</th><th>✓</th></tr></thead><tbody>{''.join(rows)}</tbody></table></section>''')

# Ein Video pro Seite (oder mehr)
cur_m = None
for v in V:
    m = month_of(v['w'])
    if m != cur_m:
        cur_m = m
        h.append(f'''<section class="page chapter"><div class="blob b1"></div><p class="k">Woche {m[0]}–{m[1]}</p><h1>{e(m[2])}</h1><p class="sub">{e(m[4])}</p></section>''')
    blocks = []
    for name, mins, ex in v['b']:
        items = ''.join(f'''<div class="ex"><div class="exh"><b>{e(U[k][0])}</b><span class="tag t-{U[k][1].lower()}">{e(U[k][1])}</span><span class="dose">{e(dose)}</span></div>
<p>{e(U[k][2])}</p><p class="tip"><b>Achte auf:</b> {e(U[k][3])} <span class="mut">· Leichter: {e(U[k][4])}</span></p></div>''' for k, dose in ex)
        blocks.append(f'<div class="blk"><h4>{e(name)} <span>ca. {mins} Min.</span></h4>{items}</div>')
    h.append(f'''<section class="vid"><div class="vh"><div><p class="k">Woche {v['w']} · Video {v['s']} · {'Full Day' if v['s']=='A' else 'Tired Day'}</p><h2>{e(v['de'])}</h2><p class="en">{e(v['en'])}</p></div>
<div class="meta"><span>Erscheint {e(de(v['date']))}</span><span>ca. {v['min']} Minuten</span><span>{e(v['mat'])}</span></div></div>
<p class="ziel">{e(v['ziel'])}</p>{''.join(blocks)}
<p class="check">☐ gefilmt &nbsp;&nbsp; ☐ Voice-over Deutsch &nbsp;&nbsp; ☐ Voice-over Englisch &nbsp;&nbsp; ☐ hochgeladen</p></section>''')

h.append('</body></html>')
open(os.path.join(OUT, 'filmplan.html'), 'w').write(''.join(h))
print('ok', len(V), 'Videos,', sum(v['min'] for v in V), 'Minuten')

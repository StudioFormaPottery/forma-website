# Baut api/seed/flow-voice.json v3 (Vivien, 31.10.2026): Flows nach Gruppen geordnet, Übungen mit zwei Seiten
# als eigene Schritte (rechts, dann links, je 1 Minute), Voice-over-Texte aus
#   texte/seiten.json   rechte Seite je Übung {name: {de, en}} (linke Seite = gespiegelt erzeugt)
#   texte/thema.json    neue Themen-Anfänge/-Abschlüsse und Atemübungen im Fersensitz {id: {de, en}}
# Aufruf: python3 tools/produktion/flows.py   (danach in method-lib nichts ändern, die Version v steuert den Abgleich)
import json, os, re, sys, collections
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from katalog import K, order, NATAL_SIDED, SPLIT

ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
OUT = os.path.join(ROOT, 'api', 'seed', 'flow-voice.json')
V = 3

old = json.load(open(OUT))
if old.get('v', 0) >= V and 'quelle' in old:
    old = json.load(open(os.path.join(HERE, 'texte', 'flow-voice-v2.json')))
else:
    json.dump(old, open(os.path.join(HERE, 'texte', 'flow-voice-v2.json'), 'w'), ensure_ascii=False, indent=1)
FV = old['flows']
prod = json.load(open(os.path.join(ROOT, 'api', 'seed', 'produktion.json')))
SEITEN = json.load(open(os.path.join(HERE, 'texte', 'seiten.json')))
THEMA = json.load(open(os.path.join(HERE, 'texte', 'thema.json')))

EN = {}
for st in list(prod['steps'].values()) + [n.get('steps') or [] for n in prod['new']]:
    for x in st:
        EN.setdefault(x[0], x[1])

METHOD = {'Dein erster Flow', 'Morgen-Flow für dunkle Tage', 'Somatisches Loslassen', 'Kraft ohne Erschöpfung', 'k:method:m1:abendflow'}
ATEM = [n for n, (g, _) in K.items() if g == 'atem']
# Flows ohne Ankommen auf der Matte bekommen eine Kindhaltung am Anfang
ADD_KIND = {'Woche 7 · Wachsen in die Länge', 'Woche 9 · Gleichgewicht finden', 'Somatisches Loslassen'}

# neutrale Texte = häufigster Text einer Übung über alle Flows
cnt = collections.defaultdict(collections.Counter)
for f, v in FV.items():
    for x in v:
        cnt[x[0]][(x[1], x[2])] += 1
NEUTRAL = {n: c.most_common(1)[0][0] for n, c in cnt.items()}
for n in ATEM:  # Atemübungen: Fassung im Fersensitz
    t = THEMA.get('E:' + n)
    if t and n in NEUTRAL:
        NEUTRAL[n] = (t['de'], t['en'] or NEUTRAL[n][1])

DE_SWAP = {'rechts': 'links', 'rechte': 'linke', 'rechten': 'linken', 'rechter': 'linker', 'rechtes': 'linkes', 'rechtem': 'linkem'}
DE_SWAP.update({v: k for k, v in list(DE_SWAP.items())})


def mirror(t, en=False):
    if en:
        return re.sub(r'\b(right|left|Right|Left)\b', lambda m: {'right': 'left', 'left': 'right', 'Right': 'Left', 'Left': 'Right'}[m.group(1)], t)
    def f(m):
        w = m.group(0); r = DE_SWAP[w.lower()]
        return r[0].upper() + r[1:] if w[0].isupper() else r
    return re.sub(r'\b(rechts|rechte|rechten|rechter|rechtes|rechtem|links|linke|linken|linker|linkes|linkem)\b', f, t, flags=re.I)


def en_marks(t):
    return t.replace('[Vorbereitung]', '[Prep]').replace('[Übung]', '[Exercise]').replace('[Ausklang]', '[Closing]')


def side_text(n, side):
    s = SEITEN[n]
    if side == 'r':
        return s['de'], s['en']
    return mirror(s['de']), mirror(s['en'], True)


flows = {}
for ref, v in FV.items():
    names = [x[0] for x in v]
    own = {}
    for x in v:
        own.setdefault(x[0], (x[1], x[2]))
    if ref in ADD_KIND:
        names = ['Kindhaltung (Balasana)'] + names
    seq = order(names)
    steps = []
    for i, (n, side) in enumerate(seq):
        if side:
            de, en = side_text(n, side)
        else:
            de, en = own.get(n) or NEUTRAL.get(n, ('', ''))
            if ('C:%s:%s' % (ref, n)) in THEMA:                 # Themen-Text steht jetzt mitten im Flow
                t = THEMA['C:%s:%s' % (ref, n)]; de, en = t['de'], t['en'] or en
            elif n in ATEM and own.get(n) and own[n][0] == cnt[n].most_common(1)[0][0][0]:
                de, en = NEUTRAL[n]                               # neutrale Atemübung → Fassung im Fersensitz
        if i == 0:
            t = THEMA.get('A:' + ref) or THEMA.get('D:' + ref)
            if t:
                de, en = t['de'], t['en'] or en
        if i == len(seq) - 1 and ('B:' + ref) in THEMA:
            t = THEMA['B:' + ref]; de, en = t['de'], t['en'] or en
        if not de:
            raise SystemExit('Kein Text: %s / %s %s' % (ref, n, side))
        steps.append([n, EN.get(n, n), side, de, en_marks(en), 60 if side else 120])
    flows[ref] = steps

# Bewegungsvideos (ohne Sprechen): The Method Monat 1 nach Gruppen, Prä-/Postnatal nur Seiten (Reihenfolge der Einheit bleibt)
for nw in prod['new']:
    if nw.get('mode') != 'move' or not nw.get('steps'):
        continue
    key = 'k:' + nw['ukey']
    src = {x[0]: x for x in nw['steps']}
    if nw['ukey'].startswith('method:'):
        seq = order([x[0] for x in nw['steps']])
    else:
        seq = []
        for x in nw['steps']:
            seq += [(x[0], 'r'), (x[0], 'l')] if x[0] in NATAL_SIDED else [(x[0], '')]
    steps = []
    for n, side in seq:
        x = src.get(n)
        if side and n in SEITEN:
            de, en = side_text(n, side)
        elif x:
            de, en = x[2], x[3]
        else:
            de, en = NEUTRAL.get(n, ('', ''))
        steps.append([n, (x[1] if x else EN.get(n, n)), side, de, en_marks(en), 60 if side else 120])
    flows[key] = steps

json.dump({'v': V, 'quelle': 'tools/produktion/flows.py', 'flows': flows}, open(OUT, 'w'), ensure_ascii=False, indent=1)
print('flow-voice.json v%d: %d Videos' % (V, len(flows)))
for ref, st in flows.items():
    sec = sum(10 + x[5] for x in st)
    print('%-45s %2d Schritte  %2d Min.' % (ref[:45], len(st), round(sec / 60)))

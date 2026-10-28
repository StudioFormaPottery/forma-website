#!/usr/bin/env python3
# Produktionsplan für alle Movecia-Videos (Vivien, 26.10.2026): Übungslisten, „fertig bis“-Daten und die neuen
# Einträge (Talking Heads für Method und Unfold, Talking Head + Bewegungsvideo je Prä-/Postnatal-Einheit).
# Ergebnis: api/seed/produktion.json, übernommen von mv_prod_sync() in api/method-lib.php.
# Aufruf: python3 tools/produktion/build.py TALK_JSON [TALK_JSON …]
#   (Talking-Head-Skripte als JSON {key: {title_de,title_en,text_de,text_en,minutes,de,en}})
import json, sys, os, subprocess, datetime as dt
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
sys.path.insert(0, HERE)
sys.path.insert(0, os.path.join(ROOT, 'tools', 'unfold-plan'))
from namen import N
from videos import V, MONTHS

D = dt.date
talks = {}
for f in sys.argv[1:]:
    talks.update(json.load(open(f)))

# --- Übungslisten -----------------------------------------------------------------------------------
ex = {}
for v in V:
    title = f"Woche {v['w']} · {v['de']}"
    seen, lst = set(), []
    for _, _, items in v['b']:
        for k, _ in items:
            if k in seen: continue
            seen.add(k); lst.append(list(N[k]))
    ex[title] = lst

ex.update({
    'Dein erster Flow': [['Atembeobachtung in Rückenlage', 'Breath awareness lying down'], ['Beckenkippen (Pelvic Tilts)', 'Pelvic tilts'], ['Scheibenwischer', 'Windshield wipers'], ['Katze-Kuh', 'Cat-Cow'], ['Kindhaltung (Balasana)', "Child's pose"], ['Herabschauender Hund', 'Downward-facing dog'], ['Roll Up in den Stand', 'Roll up to standing'], ['Berghaltung (Tadasana)', 'Mountain pose'], ['Seitbeuge im Stand', 'Standing side bend'], ['Krieger 2 (Virabhadrasana II)', 'Warrior II'], ['Savasana', 'Savasana']],
    'Atem, der dich auffängt': [['Verlängertes Ausatmen (4 ein, 6 aus)', 'Extended exhale (in for 4, out for 6)']],
    'Morgen-Flow für dunkle Tage': [['Beckenkippen (Pelvic Tilts)', 'Pelvic tilts'], ['Glückliches Baby (Ananda Balasana)', 'Happy baby'], ['Drehung in Rückenlage', 'Supine twist'], ['Katze-Kuh', 'Cat-Cow'], ['Thread the Needle', 'Thread the needle'], ['Herabschauender Hund', 'Downward-facing dog'], ['Roll Up und Armkreisen', 'Roll up and arm circles'], ['Berghaltung mit Händen am Herzen', 'Mountain pose, hands on heart']],
    'Somatisches Loslassen': [['Ausschütteln (Shaking)', 'Shaking'], ['Gewichtsverlagerung im Stand', 'Standing weight shifts'], ['Body Tapping', 'Body tapping'], ['Seitliches Zusammenziehen in Rückenlage (Somatics)', 'Somatic side bend lying down'], ['Beckenkippen mit langsamem Loslassen', 'Pelvic tilts with slow release'], ['Kopf rollen in Rückenlage', 'Head rolls lying down'], ['Savasana', 'Savasana']],
    'Kraft ohne Erschöpfung': [['Box Breathing', 'Box breathing'], ['Armkreisen und Hüftkreise im Stand', 'Arm and hip circles'], ['Roll Down', 'Roll down'], ['Katze-Kuh und Herabschauender Hund', 'Cat-Cow and downward dog'], ['Kniebeuge (Squat)', 'Squat'], ['Ausfallschritt rückwärts (Reverse Lunge)', 'Reverse lunge'], ['Liegestütz an der Wand oder auf den Knien', 'Wall or knee push-up'], ['Glute Bridge', 'Glute bridge'], ['Dead Bug', 'Dead bug'], ['Unterarmstütz (Forearm Plank)', 'Forearm plank'], ['Kindhaltung und liegende Taube', "Child's pose and reclined pigeon"], ['Savasana', 'Savasana']],
    'Box Breathing für volle Tage': [['Box Breathing (4-4-4-4)', 'Box breathing (4-4-4-4)']],
    'Abendmeditation: Rückzug': [['Verlängertes Ausatmen', 'Extended exhale'], ['Body Scan', 'Body scan'], ['Den Tag ablegen (Visualisierung)', 'Putting the day down (visualisation)']],
})

# --- Fertig-bis (Sonntage; Vivien kann jedes Datum in der Verwaltung ändern) ----------------------------
due = {
    'Willkommen in der Method': D(2026, 11, 8),
    'Dein erster Flow': D(2026, 11, 15), 'Atem, der dich auffängt': D(2026, 11, 15),
    'Morgen-Flow für dunkle Tage': D(2026, 11, 22),
    'Somatisches Loslassen': D(2026, 11, 29), 'Box Breathing für volle Tage': D(2026, 11, 29),
    'Kraft ohne Erschöpfung': D(2026, 12, 6), 'Abendmeditation: Rückzug': D(2026, 12, 6),
}
MON1 = D(2027, 1, 11)
for v in V:
    rel = MON1 + dt.timedelta(days=7 * (v['w'] - 1) + (0 if v['s'] == 'A' else 3))
    d = rel - dt.timedelta(days=7)
    d -= dt.timedelta(days=(d.weekday() + 1) % 7)  # auf den Sonntag davor
    due[f"Woche {v['w']} · {v['de']}"] = d

# --- Neue Einträge ------------------------------------------------------------------------------------
new = []
def talk(key, aud, prog, sort, unlock, d):
    t = talks.get(key)
    if not t: raise SystemExit('Talking Head fehlt: ' + key)
    new.append({'ukey': key, 'aud': aud, 'prog': prog, 'sort': sort, 'unlock': unlock, 'cat': 'talk', 'energy': 'any',
                'minutes': int(t.get('minutes') or 6), 'title_de': t['title_de'], 'title_en': t['title_en'],
                'text_de': t['text_de'], 'text_en': t['text_en'], 'de': t['de'], 'en': t['en'], 'ex': [], 'due': d.isoformat()})

talk('method:rueckzug', 'method', 'Rückzug & Ruhe', -1, '', D(2026, 11, 22))
talk('unfold:willkommen', 'unfold', 0, -2, '2027-01-10', D(2026, 12, 13))
talk('unfold:monat1', 'unfold', 0, -1, '2027-01-10', D(2026, 12, 20))
starts = {i: MON1 + dt.timedelta(days=7 * (m[0] - 1)) for i, m in enumerate(MONTHS)}
talk('unfold:monat2', 'unfold', 1, -1, (starts[1] - dt.timedelta(days=1)).isoformat(), D(2027, 1, 31))
talk('unfold:monat3', 'unfold', 2, -1, (starts[2] - dt.timedelta(days=1)).isoformat(), D(2027, 2, 28))

# Prä- & Postnatal: je Einheit Talking Head („Vertiefung“) + Bewegungsvideo. Pränatal zuerst (laufende Kundin),
# eine Einheit pro Woche, Postnatal schließt an.
units = json.loads(subprocess.check_output(['php', '-r', '''
require "api/natal-units.inc.php";
$o = [];
foreach (NATAL_UNIT_KEYS as $k) { $u = natal_units()[$k]; $o[$k] = ["title" => $u["title"], "theme" => $u["theme"], "ex" => $u["ex"], "breath" => $u["breath"], "med" => $u["med"], "close" => $u["close"]]; }
echo json_encode($o);
'''], cwd=ROOT))
en = json.load(open(os.path.join(ROOT, 'api', 'seed', 'natal-units-en.json')))
KEYS = ['prae1', 'prae2', 'prae3', 'prae4', 'prae5', 'prae6', 'post1', 'post2', 'post3', 'post4', 'post5', 'post6', 'post7', 'post8']
first = D(2026, 11, 8)

def move_script(u, lang, k):
    x = u if lang == "de" else en[k]
    title = u['title'] if lang == 'de' else x['title']
    exs = u['ex'] if lang == 'de' else x['ex']
    br = u['breath'] if lang == 'de' else x['breath']
    md = u['med'] if lang == 'de' else x['med']
    close = u['close'] if lang == 'de' else x['close']
    if lang == 'de':
        out = ['[Bewegungsvideo · Voice-over · du zeigst die Übungen, ca. 20 Min.]', '',
               '[0:00 · ANKOMMEN]',
               f'Schön, dass du da bist. In diesem Video gehen wir gemeinsam durch die Übungen aus unserer Einheit „{title}“, damit du sie zu Hause jederzeit in deinem eigenen Tempo wiederholen kannst. Leg dir eine Matte, ein Kissen und eine Decke bereit, und wenn sich während der Übungen etwas nicht gut anfühlt, machst du eine Pause oder lässt die Übung einfach weg.', '']
    else:
        out = ['[Movement video · voice-over · you demonstrate, approx. 20 min.]', '',
               '[0:00 · ARRIVING]',
               f'Lovely to have you here. In this video we move together through the exercises from our session "{title}", so that you can repeat them at home whenever you like and at your own pace. Have a mat, a cushion and a blanket ready, and if anything does not feel good along the way, take a break or simply leave that exercise out.', '']
    for e in exs:
        out += [f'[{e[0]} · {e[1]}]', e[2], f'[{e[3]}]' if len(e) > 3 and e[3] else '', '']
    out += [f'[Breathwork · {br[0]}]', br[1], '', f'[{"Meditation" if lang == "de" else "Meditation"} · {md[0]}]', md[1], '',
            '[ABSCHLUSS]' if lang == 'de' else '[CLOSING]', close]
    return '\n'.join(l for l in out if l is not None).replace('\n\n\n', '\n\n').strip()

for i, k in enumerate(KEYS):
    u = units[k]
    d = first + dt.timedelta(days=7 * i)
    prog = 'prae' if k.startswith('prae') else 'post'
    t = talks.get('natal:' + k) or talks.get(k)
    if not t: raise SystemExit('Talking Head fehlt: ' + k)
    new.append({'ukey': f'natal:{k}:talk', 'aud': 'natal', 'prog': prog, 'sort': i * 2, 'unlock': '', 'cat': 'talk', 'energy': 'any',
                'minutes': int(t.get('minutes') or 8), 'title_de': t['title_de'], 'title_en': t['title_en'], 'text_de': t['text_de'], 'text_en': t['text_en'],
                'de': t['de'], 'en': t['en'], 'ex': [], 'due': d.isoformat(), 'unit': k})
    exl = [[e[0], en[k]['ex'][j][0]] for j, e in enumerate(u['ex'])] + [[u['breath'][0], en[k]['breath'][0]]]  # Meditation gehört ins Talking-Head-/Live-Format, nicht in den 30/45-Sek.-Player
    new.append({'ukey': f'natal:{k}:move', 'aud': 'natal', 'prog': prog, 'sort': i * 2 + 1, 'unlock': '', 'cat': 'flow', 'energy': 'any',
                'minutes': 20, 'title_de': u['title'] + ' · Bewegungsvideo', 'title_en': en[k]['title'] + ' · Movement video',
                'text_de': 'Alle Übungen dieser Einheit zum Mitmachen, in deinem Tempo.', 'text_en': 'All exercises from this session to follow along, at your own pace.',
                'de': move_script(u, "de", k), 'en': move_script(u, "en", k), 'ex': exl, 'due': d.isoformat(), 'unit': k})


# --- Einheitliche Übungsnamen (Vivien, 26.10.2026): eine Übung = ein Clip in der Mediathek, überall wiederverwendet.
# Gibt es keinen eindeutigen deutschen Namen, steht der gängige englische Name auch im Deutschen.
CANON = {}
def _c(de, en, *alts):
    for a in (de,) + alts: CANON[a] = (de, en)
_c('Verlängertes Ausatmen', 'Extended exhale', 'Verlängerte Ausatmung')
_c('Ausschütteln (Shaking)', 'Shaking', 'Shaking', 'Shaking (Ausschütteln)', 'Schütteln')
_c('Abklopfen (Body Tapping)', 'Body tapping', 'Body Tapping', 'Body Tapping (Abklopfen)', 'Abklopfen')
_c('Katze-Kuh', 'Cat-Cow', 'Katze und Kuh', 'Katze-Kuh (Marjaryasana-Bitilasana)')
_c('Herabschauender Hund (Adho Mukha Svanasana)', 'Downward-facing dog', 'Herabschauender Hund')
_c('Kindhaltung (Balasana)', "Child's pose", 'Kindhaltung')
_c('Endentspannung (Savasana)', 'Final relaxation (Savasana)', 'Savasana')
_c('Drehung in Rückenlage (Supta Matsyendrasana)', 'Supine twist', 'Liegende Drehung', 'Drehung in Rückenlage')
_c('Glute Bridge (Beckenheben)', 'Glute bridge', 'Glute Bridge', 'Schulterbrücke (Glute Bridge)')
_c('Brücke (Setu Bandhasana)', 'Bridge pose', 'Schulterbrücke')
_c('Wandsitz (Wall Sit)', 'Wall sit', 'Wall Sit', 'Wandsitz')
_c('Göttin (Utkata Konasana)', 'Goddess pose', 'Göttin')
_c('Happy Baby (Ananda Balasana)', 'Happy baby', 'Happy Baby', 'Glückliches Baby (Ananda Balasana)')
_c('Hoher Ausfallschritt (Crescent Lunge)', 'Crescent lunge', 'High Lunge', 'High Lunge (hoher Ausfallschritt)')
_c('Ausfallschritt rückwärts (Reverse Lunge)', 'Reverse lunge', 'Reverse Lunge', 'Ausfallschritt rückwärts')
_c('Seitlicher Ausfallschritt (Side Lunge)', 'Side lunge', 'Side Lunge', 'Side Lunge (seitlicher Ausfallschritt)', 'Seitlicher Ausfallschritt')
_c('Kniebeuge (Squat)', 'Squat', 'Kniebeuge', 'Kniebeugen')
_c('Clamshell', 'Clamshell', 'Clamshell', 'Clamshell in Seitlage')
_c('Seitliches Beinheben (Side Leg Lift)', 'Side-lying leg lift', 'Side Leg Lift', 'Seitliches Beinheben')
_c('Eidechse (Lizard Pose)', 'Lizard pose', 'Eidechse (Utthan Pristhasana)')
_c('Figur 4 in Rückenlage', 'Supine figure four', 'Liegende Taube (Figure Four)')
_c('Seitlicher Winkel (Parsvakonasana)', 'Extended side angle', 'Extended Side Angle')
_c('Sphinx (Salamba Bhujangasana)', 'Sphinx pose', 'Sphinx')
_c('Roll Down im Stand', 'Standing roll down', 'Roll Down')
_c('Dead Bug', 'Dead bug')
_c('Bird Dog', 'Bird dog', 'Bird Dog (Vierfüßlerstand mit Arm und Bein)')
_c('Box Breathing', 'Box breathing')
_c('Thread the Needle', 'Thread the needle', 'Thread the Needle (Faden durchs Nadelöhr)')
_c('Floor Flow', 'Floor flow', 'Floor Flow (Bodenfluss)')
_c('Füße erden', 'Grounding the feet', 'Füße spüren im Stand')
_c('Hände auf Herz und Bauch', 'Hands on heart and belly', 'Atembeobachtung, Hände auf Herz und Bauch')
_c('Doppeltes Einatmen mit langer Ausatmung', 'Double inhale, long exhale', 'Physiologischer Seufzer (doppeltes Einatmen)')
_c('Becken-Achter im Stand', 'Standing pelvic figure eights', 'Hüftachten im Stand')
_c('Hip Hinge im Stand', 'Standing hip hinge', 'Hip Hinge (Good Morning)')
_c('Beckenkippen (Pelvic Tilts)', 'Pelvic tilts', 'Becken kippen in Rückenlage')
_c('Kopf rollen in Rückenlage', 'Head rolls lying down', 'Kopf rollen im Liegen')
_c('Seitliches Zusammenziehen in Rückenlage (Somatics)', 'Lateral contraction lying down', 'Seitliches Zusammenziehen in Rückenlage', 'Somatische Seitbeuge im Liegen (Hanna Somatics)')
_c('Swimming in Bauchlage', 'Swimming')
_c('Knie pendeln (Scheibenwischer)', 'Windshield wipers', 'Knie pendeln', 'Scheibenwischer')
_c('Beine an der Wand (Viparita Karani)', 'Legs up the wall', 'Beine an die Wand')
_c('Selbstumarmung (Butterfly Hug)', 'Self-hug (butterfly hug)', 'Selbstumarmung')
_c('Baum (Vrksasana)', 'Tree pose')
_c('Brustdehnung im Türrahmen', 'Doorway chest stretch', 'Brustdehnung')
_c('Rudern mit Band', 'Band row')
_c('Seitstütz auf dem Knie', 'Kneeling side plank', 'Seitstütz auf den Knien')
_c('Langsames Krabbeln (Bear Crawl)', 'Slow bear crawl', 'Langsamer Bear Crawl', 'Langsames Krabbeln')
_c('Bear Plank', 'Bear plank', 'Bear Hold', 'Bear Plank (schwebende Knie)')
_c('Reach Flow (Arme in alle Richtungen)', 'Reach flow', 'Arm Sweeps in alle Richtungen')
_c('Wirbelsäulenwelle im Stand (Body Roll)', 'Standing spinal wave', 'Wirbelsäulenwelle im Stand', 'Body Roll')
_c('Ankommen im Liegen', 'Arriving lying down', 'im Bett oder auf der Matte')
_c('Unterarmstütz (Forearm Plank)', 'Forearm plank')
_c('Dreieck (Trikonasana)', 'Triangle pose')
_c('Krieger 2 (Virabhadrasana II)', 'Warrior II')
_c('Tiefer Ausfallschritt (Anjaneyasana)', 'Low lunge')
def canon(de, en):
    return CANON.get(de, (de, en))

# --- Schritte je Video (Vivien, 26.10.2026): Übung, Sprechtext DE/EN pro Übung, Sekunden ------------------
# Flow: Clip läuft in Schleife, Timer (Standard 2 Min. bzw. „N Min.“ aus dem Skript), Voice-over pro Übung.
# Bewegungsvideo: gleiche Übungen, Timer 30/45 Sek., ohne Sprechen. Form: [[de, en, text_de, text_en, sekunden], …]
import re
TIME = re.compile(r'^\d{1,2}:\d{2}$')
def sections(text):
    out, cur = [], None
    for line in (text or '').split('\n'):
        m = re.match(r'^\[(.+)\]\s*$', line.strip())
        if m and ('·' in m.group(1) or re.match(r'^\d+:\d\d', m.group(1)) or m.group(1).strip().isupper()):
            h = m.group(1).strip()
            if h.startswith(('Bewegungsvideo', 'Movement video', 'Talking Head', 'Talking head')): continue
            cur = [h, []]; out.append(cur)
        elif cur is not None:
            cur[1].append(line)
    return [(h, '\n'.join(t).strip()) for h, t in out]
def hname(h):
    parts = [x.strip() for x in h.split('·') if x.strip()]
    parts = [x for x in parts if not TIME.match(x)]
    real = [x for x in parts if not (x.isupper() and len(x) > 2) and not re.match(r'^(ca\.\s*)?\d', x)]
    return real[0] if real else None
def secs_of(h):
    m = re.search(r'(\d+)(?:\s*[–-]\s*(\d+))?\s*Min', h)
    return max(60, min(900, int(m.group(2) or m.group(1)) * 60)) if m else 120
def merged(secs):
    res, pending = [], ''
    for h, t in secs:
        n = hname(h)
        if n is None:
            if res and re.search(r'ABSCHLUSS|CLOSING|AUSKLANG', h.upper()):
                res[-1][2] = (res[-1][2] + '\n\n' + t).strip()
            else:
                pending = (pending + '\n\n' + t).strip()
            continue
        res.append([n, h, ((pending + '\n\n') if pending else '') + t]); pending = ''
    if pending and res: res[-1][2] += '\n\n' + pending
    return res
def steps_for(de_text, en_text, exlist):
    md, me = merged(sections(de_text)), merged(sections(en_text))
    if len(me) != len(md): me = None
    out = []
    for i, (n, h, t) in enumerate(md):
        if len(md) == len(exlist): de_n, en_n = exlist[i][0], exlist[i][1]
        else: de_n, en_n = n, (me[i][0] if me else n)
        de_n, en_n = canon(de_n, en_n)
        out.append([de_n, en_n, t, me[i][2] if me else '', secs_of(h)])
    return out

lscripts = json.load(open(os.path.join(ROOT, 'api', 'seed', 'lesson-scripts.json')))
steps = {}
for title, sc in lscripts.items():
    if title in ('Willkommen in der Method',): continue
    st = steps_for(sc.get('de', ''), sc.get('en', ''), ex.get(title, []))
    if st: steps[title] = st
for n in new:
    if n['cat'] == 'talk': continue
    if n['ukey'].startswith('natal:'):
        k = n['unit']; u = units[k]; e = en[k]
        st = []
        for j, x in enumerate(u['ex']):
            ex_en = e['ex'][j]
            tde = x[2] + (('\n[' + x[3] + ']') if len(x) > 3 and x[3] else '')
            ten = ex_en[2] + (('\n[' + ex_en[3] + ']') if len(ex_en) > 3 and ex_en[3] else '')
            sec = 120 if not re.search(r'Min', x[1]) else secs_of(x[1])
            st.append([*canon(x[0], ex_en[0]), tde, ten, sec])
        st.append([*canon(u['breath'][0], e['breath'][0]), u['breath'][1], e['breath'][1], 120])
        intro_de = f'Schön, dass du da bist. Wir gehen gemeinsam durch die Übungen aus unserer Einheit „{u["title"]}“, in deinem eigenen Tempo. Wenn sich etwas nicht gut anfühlt, machst du eine Pause oder lässt die Übung einfach weg.'
        intro_en = f'Lovely to have you here. We move together through the exercises from our session "{e["title"]}", at your own pace. If anything does not feel good, take a break or simply leave that exercise out.'
        st[0][2] = intro_de + '\n\n' + st[0][2]; st[0][3] = intro_en + '\n\n' + st[0][3]
        st[-1][2] += '\n\n' + u['close']; st[-1][3] += '\n\n' + e['close']
        n['steps'] = st; n['mode'] = 'move'; n['ex'] = [[a, b] for a, b, *_ in st]
    else:
        n['steps'] = steps_for(n['de'], n['en'], n['ex'])

# --- The Method · Monat 1 „Rückzug & Ruhe“ mit 12 Videos (Vivien, 26.10.2026) -------------------------------
# Rhythmus: Talking Head zum Monatsthema, dann immer Flow → Bewegungsvideo → Talking Head (Impuls, Mental Load,
# Breathwork, geführte Meditation). Flow-Texte je Übung aus texte/method-m1.json (mittlere Übungen themenneutral,
# damit jede Aufnahme in künftigen Flows wiederverwendet werden kann). Bewegungsvideos nur aus vorhandenen Übungen.
M1 = 'Rückzug & Ruhe'
mfile = os.path.join(HERE, 'texte', 'method-m1.json')
mj = json.load(open(mfile)) if os.path.exists(mfile) else {'steps': {}, 'talk': {}}
for t, st in mj['steps'].items():
    st = [[*canon(x[0], x[1]), x[2], x[3], int(x[4])] for x in st]
    mj['steps'][t] = st
    if t in steps: steps[t] = st; ex[t] = [[x[0], x[1]] for x in st]
for k, t in mj['talk'].items():
    if k == 'method:rueckzug':
        for n in new:
            if n['ukey'] == k: n.update({kk: t[kk] for kk in ('title_de', 'title_en', 'text_de', 'text_en', 'de', 'en')}); n['minutes'] = int(t.get('minutes') or 5)
    else:
        talks[k] = t
M1_DUE = {'method:m1:warm': D(2026, 11, 29), 'method:m1:mitte': D(2026, 12, 6), 'method:m1:mentalload': D(2026, 12, 6),
          'method:m1:ruecken': D(2026, 12, 13), 'method:m1:abendflow': D(2026, 12, 13), 'method:m1:beine': D(2026, 12, 13)}
if 'method:m1:mentalload' in talks:
    talk('method:m1:mentalload', 'method', M1, 6, '', M1_DUE['method:m1:mentalload'])
if 'Abendflow: Schwere ablegen' in mj['steps']:
    st = mj['steps']['Abendflow: Schwere ablegen']
    new.append({'ukey': 'method:m1:abendflow', 'aud': 'method', 'prog': M1, 'sort': 10, 'unlock': '', 'cat': 'flow', 'energy': 'tired',
                'minutes': round(sum(x[4] for x in st) / 60), 'title_de': 'Abendflow: Schwere ablegen', 'title_en': 'Evening flow: letting go of the heaviness',
                'text_de': 'Ein ruhiger Flow für den Abend, der dir hilft, die Last des Tages abzulegen und weich in die Nacht zu gleiten.',
                'text_en': 'A calm evening flow that helps you put down the weight of the day and drift softly into the night.',
                'de': '', 'en': '', 'ex': [[x[0], x[1]] for x in st], 'steps': st, 'mode': 'flow', 'due': M1_DUE['method:m1:abendflow'].isoformat()})
MOVES = [
    ('method:m1:warm', 2, 'Warm werden', 'Warming up', 'Kurze Übungen, die deinen Körper an kalten Tagen sanft aufwärmen. Du wählst 30 oder 45 Sekunden pro Übung und hörst dazu deine eigene Musik.',
     'Short exercises that gently warm your body on cold days. Choose 30 or 45 seconds per exercise and play your own music.',
     ['Ausschütteln (Shaking)', 'Armkreisen und Hüftkreise im Stand', 'Wirbelsäulenwelle im Stand (Body Roll)', 'Roll Down im Stand', 'Katze-Kuh', 'Thread the Needle', 'Herabschauender Hund (Adho Mukha Svanasana)', 'Tiefer Ausfallschritt (Anjaneyasana)', 'Kniebeuge (Squat)', 'Berghaltung (Tadasana)']),
    ('method:m1:mitte', 5, 'Starke Mitte', 'A strong centre', 'Ruhige Kraft für Bauch, Rücken und Beckenboden, Übung für Übung mit Timer.',
     'Calm strength for your belly, back and pelvic floor, one exercise at a time with a timer.',
     ['Beckenkippen (Pelvic Tilts)', 'Glute Bridge (Beckenheben)', 'Dead Bug', 'Bird Dog', 'Unterarmstütz (Forearm Plank)', 'Seitstütz auf dem Knie', 'Swimming in Bauchlage', 'Toe Taps', 'Kindhaltung (Balasana)']),
    ('method:m1:ruecken', 8, 'Rücken und Schultern lösen', 'Releasing back and shoulders', 'Für alle Tage, an denen du viel getragen, gebückt und gesessen hast.',
     'For all the days when you have carried, bent and sat a lot.',
     ['Katze-Kuh', 'Thread the Needle', 'Open Book (Rotation der Brustwirbelsäule in Seitlage)', 'Sphinx (Salamba Bhujangasana)', 'Kleine Kobra (Bhujangasana)', 'Brustdehnung im Türrahmen', 'Wall Angels an der Wand', 'Kindhaltung (Balasana)', 'Drehung in Rückenlage (Supta Matsyendrasana)']),
    ('method:m1:beine', 11, 'Beine und Hüften', 'Legs and hips', 'Kräftige Beine, bewegliche Hüften: kurze Übungen mit Timer, ganz ohne Hast.',
     'Strong legs, mobile hips: short exercises with a timer, without any rush.',
     ['Kniebeuge (Squat)', 'Ausfallschritt rückwärts (Reverse Lunge)', 'Seitlicher Ausfallschritt (Side Lunge)', 'Wadenheben', 'Clamshell', 'Seitliches Beinheben (Side Leg Lift)', 'Glute Bridge (Beckenheben)', 'Eidechse (Lizard Pose)', 'Figur 4 in Rückenlage']),
]
en_of = {de: en for de, en in CANON.values()}
for x in [x for st in list(steps.values()) + [n.get('steps') or [] for n in new] for x in st]: en_of.setdefault(x[0], x[1])
# Sprechtexte für Bewegungsvideos aus den Flows übernehmen (wortgleich): im Bewegungsvideo werden sie nicht abgespielt,
# aber schaltet Vivien es auf „Flow“ um, ist es mit den gespeicherten Aufnahmen sofort eingesprochen
text_of = {}
for st in list(mj['steps'].values()):
    for idx, x in enumerate(st):
        if 0 < idx < len(st) - 1 and x[2]: text_of.setdefault(x[0], (x[2], x[3]))
for key, sort, tde, ten, xde, xen, lst in MOVES:
    miss = [d for d in lst if d not in en_of]
    if miss: raise SystemExit('Übung ohne englischen Namen: ' + ', '.join(miss))
    st = [[d, en_of[d], *text_of.get(d, ('', '')), 45] for d in lst]
    new.append({'ukey': key, 'aud': 'method', 'prog': M1, 'sort': sort, 'unlock': '', 'cat': 'body', 'energy': 'any', 'minutes': 8,
                'title_de': tde, 'title_en': ten, 'text_de': xde, 'text_en': xen, 'de': '', 'en': '', 'ex': [[x[0], x[1]] for x in st],
                'steps': st, 'mode': 'move', 'due': M1_DUE[key].isoformat()})
# feste Reihenfolge im Monat (Titel bestehender Lektionen bzw. ukey neuer)
M1_ORDER = ['k:method:rueckzug', 't:Morgen-Flow für dunkle Tage', 'k:method:m1:warm', 't:Box Breathing für volle Tage', 't:Somatisches Loslassen',
            'k:method:m1:mitte', 'k:method:m1:mentalload', 't:Kraft ohne Erschöpfung', 'k:method:m1:ruecken', 't:Abendmeditation: Rückzug',
            'k:method:m1:abendflow', 'k:method:m1:beine']

out = {'v': 11, 'welcome': mj.get('welcome'), 'm1_order': M1_ORDER, 'm1_steps': list(mj['steps'].keys()), 'steps': steps, 'ex': ex, 'due': {k: v.isoformat() for k, v in due.items()}, 'new': new,
       'natal_programs': {'prae': ['Pränatal · Videos', 'Prenatal · Videos'], 'post': ['Postnatal · Videos', 'Postnatal · Videos']}}
path = os.path.join(ROOT, 'api', 'seed', 'produktion.json')
json.dump(out, open(path, 'w'), ensure_ascii=False, indent=1)
print('geschrieben:', path, len(ex), 'Übungslisten,', len(new), 'neue Einträge')

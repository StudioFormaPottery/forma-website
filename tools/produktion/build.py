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

out = {'v': 2, 'ex': ex, 'due': {k: v.isoformat() for k, v in due.items()}, 'new': new,
       'natal_programs': {'prae': ['Pränatal · Videos', 'Prenatal · Videos'], 'post': ['Postnatal · Videos', 'Postnatal · Videos']}}
path = os.path.join(ROOT, 'api', 'seed', 'produktion.json')
json.dump(out, open(path, 'w'), ensure_ascii=False, indent=1)
print('geschrieben:', path, len(ex), 'Übungslisten,', len(new), 'neue Einträge')

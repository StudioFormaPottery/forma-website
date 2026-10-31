# Übungskatalog (Vivien, 31.10.2026): wo wird die Übung gemacht (Gruppe) und hat sie zwei Seiten?
#
# Ein Flow läuft immer in dieser Reihenfolge der Gruppen, damit niemand ständig aufstehen und sich hinlegen muss:
#   1 atem      Ankommen mit dem Atem, im Fersensitz oder in der Kindhaltung
#   2 matte     Kindhaltung, Vierfüßlerstand
#   3 brett     Herabschauender Hund, Bear Plank, Unterarmstütz … (Kraft auf Händen und Knien/Füßen)
#   4 stand     Ausfallschritte, Standhaltungen, somatische Übungen im Stehen
#   5 hocke     Malasana, Girlande (vom Stand hinunter)
#   6 sitz      Sitzen und Knien (Tor, Vorbeuge)
#   7 seite     Seitlage, Seitstütz
#   8 bauch     Bauchlage
#   9 ruecken   Rückenlage
#  10 ende      Body Scan, Endentspannung
# Übungen mit zwei Seiten: innerhalb einer Gruppe erst alle mit rechts, dann alle mit links (je 1 Minute).
# Kombi-Schritte werden in einzelne Übungen zerlegt (SPLIT).

GROUPS = ['atem', 'matte', 'brett', 'stand', 'hocke', 'sitz', 'seite', 'bauch', 'ruecken', 'ende']

# name_de: (gruppe, zwei_seiten)
K = {
    # 1 Atem / Ankommen
    'Hände auf Herz und Bauch': ('atem', False),
    'Verlängertes Ausatmen': ('atem', False),
    'Flankenatmung': ('atem', False),
    'Box Breathing': ('atem', False),
    'Doppeltes Einatmen mit langer Ausatmung': ('atem', False),
    'Summen (Bhramari) und Voo-Ton': ('atem', False),
    'Selbstumarmung (Butterfly Hug)': ('atem', False),
    'Orientieren': ('atem', False),
    # 2 Matte: Kind, Vierfüßler
    'Kindhaltung (Balasana)': ('matte', False),
    'Wirbelsäulenwelle im Vierfüßlerstand': ('matte', False),
    'Hüftkreise im Vierfüßlerstand': ('matte', False),
    'Katze-Kuh': ('matte', False),
    'Katze-Kuh und Herabschauender Hund': ('matte', False),
    'Floor Flow': ('matte', False),
    'Thread the Needle': ('matte', True),
    'Bird Dog': ('matte', True),
    # 3 Brett / Kopf unter dem Herzen
    'Langsames Krabbeln (Bear Crawl)': ('brett', False),
    'Bear Plank': ('brett', False),
    'Liegestütz an der Wand oder auf den Knien': ('brett', False),
    'Unterarmstütz (Forearm Plank)': ('brett', False),
    'Herabschauender Hund (Adho Mukha Svanasana)': ('brett', False),
    # 4 Stand
    'Füße erden': ('stand', False),
    'Ausschütteln (Shaking)': ('stand', False),
    'Nachspüren': ('stand', False),
    'Abklopfen (Body Tapping)': ('stand', False),
    'Gewichtsverlagerung im Stand': ('stand', False),
    'Wiegen im Stand': ('stand', False),
    'Achtsames Gehen': ('stand', False),
    'Berghaltung (Tadasana)': ('stand', False),
    'Berghaltung mit Händen am Herzen': ('stand', False),
    'Roll Down im Stand': ('stand', False),
    'Roll Up in den Stand': ('stand', False),
    'Roll Up in den Stand, dann Arm Circles': ('stand', False),
    'Armspiralen': ('stand', False),
    'Armkreisen und Hüftkreise im Stand': ('stand', False),
    'Reach Flow (Arme in alle Richtungen)': ('stand', False),
    'Wirbelsäulenwelle im Stand (Body Roll)': ('stand', False),
    'Becken-Achter im Stand': ('stand', False),
    'Hip Hinge im Stand': ('stand', False),
    'Langsame Kniebeuge': ('stand', False),
    'Kniebeuge (Squat)': ('stand', False),
    'Wadenheben': ('stand', False),
    'Wandsitz (Wall Sit)': ('stand', False),
    'Stuhl (Utkatasana)': ('stand', False),
    'Göttin (Utkata Konasana)': ('stand', False),
    'Brustdehnung im Türrahmen': ('stand', False),
    'Wall Angels an der Wand': ('stand', False),
    'Seitbeuge im Stand': ('stand', True),
    'Ausfallschritt rückwärts (Reverse Lunge)': ('stand', True),
    'Seitlicher Ausfallschritt (Side Lunge)': ('stand', True),
    'Tiefer Ausfallschritt (Anjaneyasana)': ('stand', True),
    'Hoher Ausfallschritt (Crescent Lunge)': ('stand', True),
    'Krieger 2 (Virabhadrasana II)': ('stand', True),
    'Umgekehrter Krieger (Viparita Virabhadrasana)': ('stand', True),
    'Seitlicher Winkel (Parsvakonasana)': ('stand', True),
    'Dreieck (Trikonasana)': ('stand', True),
    'Eidechse (Lizard Pose)': ('stand', True),
    'Baum (Vrksasana)': ('stand', True),
    # 5 Hocke
    'Girlande (Malasana)': ('hocke', False),
    'Malasana mit Gewichtsverlagerung': ('hocke', False),
    # 6 Sitz & Knien
    'Halber Roll Back im Sitzen': ('sitz', False),
    'Sitzende Vorbeuge (Paschimottanasana)': ('sitz', False),
    'Tor (Parighasana)': ('sitz', True),
    # 7 Seitlage
    'Seitstütz auf dem Knie': ('seite', True),
    'Clamshell': ('seite', True),
    'Seitliches Beinheben (Side Leg Lift)': ('seite', True),
    'Open Book (Rotation der Brustwirbelsäule in Seitlage)': ('seite', True),
    # 8 Bauch
    'Swimming in Bauchlage': ('bauch', False),
    'Sphinx (Salamba Bhujangasana)': ('bauch', False),
    'Kleine Kobra (Bhujangasana)': ('bauch', False),
    # 9 Rücken
    'Ankommen im Liegen': ('ruecken', False),
    'Atembeobachtung in Rückenlage': ('ruecken', False),
    'Beckenkippen (Pelvic Tilts)': ('ruecken', False),
    'Beckenkippen mit langsamem Loslassen': ('ruecken', False),
    'Pandiculation in Rückenlage (Rücken wölben und lösen)': ('ruecken', False),
    'Kopf rollen in Rückenlage': ('ruecken', False),
    'Knie pendeln (Scheibenwischer)': ('ruecken', False),
    'Brücke (Setu Bandhasana)': ('ruecken', False),
    'Glute Bridge (Beckenheben)': ('ruecken', False),
    'Glute Bridge mit Marschieren': ('ruecken', False),
    'Dead Bug': ('ruecken', False),
    'Toe Taps': ('ruecken', False),
    'Happy Baby (Ananda Balasana)': ('ruecken', False),
    'Beine an der Wand (Viparita Karani)': ('ruecken', False),
    'Seitliches Zusammenziehen in Rückenlage (Somatics)': ('ruecken', True),
    'Figur 4 in Rückenlage': ('ruecken', True),
    'Drehung in Rückenlage (Supta Matsyendrasana)': ('ruecken', True),
    # 10 Ende
    'Body Scan': ('ende', False),
    'Endentspannung (Savasana)': ('ende', False),
}

# Kombi-Schritte → einzelne Übungen (dann gelten Seiten und Gruppen je Übung)
SPLIT = {
    'Kleiner Fluss: Krieger 2, Reverse Warrior, Extended Side Angle': ['Krieger 2 (Virabhadrasana II)', 'Umgekehrter Krieger (Viparita Virabhadrasana)', 'Seitlicher Winkel (Parsvakonasana)'],
    'Tiefer Ausfallschritt, High Lunge, Krieger 2, Reverse Warrior': ['Tiefer Ausfallschritt (Anjaneyasana)', 'Hoher Ausfallschritt (Crescent Lunge)', 'Krieger 2 (Virabhadrasana II)', 'Umgekehrter Krieger (Viparita Virabhadrasana)'],
    'High Lunge, Krieger 2, Dreieck': ['Hoher Ausfallschritt (Crescent Lunge)', 'Krieger 2 (Virabhadrasana II)', 'Dreieck (Trikonasana)'],
    'Kindhaltung und liegende Taube': ['Kindhaltung (Balasana)', 'Figur 4 in Rückenlage'],
    'Katze-Kuh und Herabschauender Hund': ['Katze-Kuh', 'Herabschauender Hund (Adho Mukha Svanasana)'],
}

# Übungen mit zwei Seiten in den Prä-/Postnatal-Bewegungsvideos (dort bleibt die Reihenfolge der Einheit)
NATAL_SIDED = {'Clamshell', 'Bird Dog', 'Seitstütz auf dem Knie', 'Seitstütz', 'Hüftbeuger dehnen im Halbkniestand',
               'Open Book (Rotation der Brustwirbelsäule in Seitlage)', 'Schulterkreisen in Seitlage', 'Ausfallschritt rückwärts (Reverse Lunge)',
               'Einbeinige Schulterbrücke (Single-Leg Glute Bridge)', 'Einbeinstand', 'Einbeinige Kniebeuge am Stuhl', 'Wadenheben einbeinig',
               'Seitliche Dehnung im Sitzen', 'Drehung in Rückenlage (Supta Matsyendrasana)', 'Figur 4 in Rückenlage'}


def order(names):
    """Reihenfolge nach Gruppen, je Gruppe: einseitige Übungen davor, dann alle rechts, dann alle links,
    einseitige Übungen, die ursprünglich nach der letzten Seiten-Übung kamen, danach.
    Gibt [(name, side)] zurück, side = 'r' | 'l' | ''."""
    flat = []
    for n in names:
        flat += SPLIT.get(n, [n])
    # doppelte Übungen hintereinander zusammenfassen
    seen = []
    for n in flat:
        if n not in K:
            raise KeyError('Nicht im Katalog: ' + n)
        seen.append(n)
    out = []
    for g in GROUPS:
        items = [n for n in seen if K[n][0] == g]
        if not items:
            continue
        sided = [i for i, n in enumerate(items) if K[n][1]]
        if not sided:
            out += [(n, '') for n in items]
            continue
        first, last = sided[0], sided[-1]
        before = [n for i, n in enumerate(items) if i < first and not K[n][1]]
        middle = [n for i, n in enumerate(items) if first < i < last and not K[n][1]]
        after = [n for i, n in enumerate(items) if i > last and not K[n][1]]
        run = []
        for n in [items[i] for i in sided]:
            if n not in run:
                run.append(n)
        out += [(n, '') for n in before]
        out += [(n, 'r') for n in run] + [(n, 'l') for n in run]
        out += [(n, '') for n in middle + after]
    # gleiche Übung zweimal direkt hintereinander (z. B. nach dem Zerlegen) nur einmal
    res = []
    for x in out:
        if res and res[-1] == x:
            continue
        res.append(x)
    return res

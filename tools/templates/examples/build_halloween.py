"""FORMA Template-Shop · Halloween: Fledermaus-Wandteelicht, Hexenhut-Windlicht, Gespenst-Windlicht (DE + EN)."""
import asyncio, math, os
from playwright.async_api import async_playwright
REPO = '/home/claude/repo-forma-website/'
R = 'file://' + REPO
D = os.path.dirname(os.path.abspath(__file__)) + '/'
r, b, k, p, s, ink = '#8C2C1D', '#DDBFA8', '#674B40', '#FBF1EA', '#E4D2BF', '#33211B'
LW = .5

def f(x): return f'{x:.2f}'
def star_path(R_, cx=0, cy=0, ri=.5, rot=-90, t=.18):
    P = [(cx + (R_ if i % 2 == 0 else R_ * ri) * math.cos(math.radians(rot + i * 36)), cy + (R_ if i % 2 == 0 else R_ * ri) * math.sin(math.radians(rot + i * 36))) for i in range(10)]
    lerp = lambda a, c, u: (a[0] + (c[0] - a[0]) * u, a[1] + (c[1] - a[1]) * u)
    d = ''
    for i in range(10):
        pr, cu, nx = P[i - 1], P[i], P[(i + 1) % 10]
        a = lerp(cu, pr, t); c = lerp(cu, nx, t)
        d += ('M' if i == 0 else 'L') + f'{f(a[0])} {f(a[1])} Q{f(cu[0])} {f(cu[1])} {f(c[0])} {f(c[1])} '
    return d + 'Z'
def circ(cx, cy, rr): return f'M{f(cx - rr)} {f(cy)} a{rr} {rr} 0 1 0 {2 * rr} 0 a{rr} {rr} 0 1 0 {-2 * rr} 0Z'
def ell(cx, cy, rx, ry): return f'M{f(cx - rx)} {f(cy)} a{rx} {ry} 0 1 0 {2 * rx} 0 a{rx} {ry} 0 1 0 {-2 * rx} 0Z'

# ------------------------------------------------------------------ geometry (mm)
BAT = ('M0 -36 L-7 -50 L-12 -33 C-30 -44 -60 -47 -90 -30 C-80 -18 -80 -4 -84 9 C-70 1 -56 5 -52 17 '
       'C-44 9 -32 11 -26 23 C-18 15 -8 19 0 35 C8 19 18 15 26 23 C32 11 44 9 52 17 C56 5 70 1 84 9 '
       'C80 -4 80 -18 90 -30 C60 -47 30 -44 12 -33 L7 -50 Z')
SHELF = 'M-40 0 L40 0 A40 40 0 0 1 -40 0 Z'

def cone(d, h, tab=8, wavy=0):
    """Cone sector: apex (0,0), first edge along +x, opening angle theta (CCW, y down = clockwise visually)."""
    L = math.hypot(d / 2, h); th = 2 * math.pi * (d / 2) / L
    pts = []
    n = 120
    for i in range(n + 1):
        a = th * i / n
        rr = L + (wavy * math.sin(i / n * math.pi * 12) if wavy else 0)
        pts.append((rr * math.cos(a), rr * math.sin(a)))
    d_ = 'M0 0 L' + ' L'.join(f'{f(x)} {f(y)}' for x, y in pts) + ' Z'
    # overlap tab along the first edge (y<0 side)
    tabd = f'M3 0 L{f(L - 3)} 0 L{f(L - 8)} {-tab} L8 {-tab} Z'
    return d_, tabd, L, th

def bbox_cone(L, th, tab):
    xs = [0, L, L * math.cos(th)]; ys = [-tab, L * math.sin(th)]
    if th > math.pi / 2: ys.append(L)
    return min(xs), -tab, max(xs), max(max(ys), L * math.sin(min(th, math.pi / 2)))

def polar(L, a): return L * math.cos(a), L * math.sin(a)

# ------------------------------------------------------------------ icons
SPARK = 'M12 0c.9 6.4 5.6 11.1 12 12-6.4.9-11.1 5.6-12 12-.9-6.4-5.6-11.1-12-12C6.4 11.1 11.1 6.4 12 0z'
def flame(x, y, sc=1): return f'<g transform="translate({x} {y}) scale({sc})"><path d="M0 -16c-7 7-8 13 0 16 8-3 7-9 0-16z" fill="{r}"/><path d="M0 -8c-3 3-3 6 0 8 3-2 3-5 0-8z" fill="{p}"/></g>'
def icon_bat():
    return f'''<svg viewBox="-120 -80 240 190" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
<circle cx="0" cy="10" r="70" fill="{p}" opacity=".55"/>
<g transform="scale(1.1)"><path d="{BAT}" fill="{k}"/><circle cx="0" cy="-26" r="3.6" fill="#EEE2D4"/>
<circle cx="-5" cy="-20" r="2.2" fill="{p}"/><circle cx="5" cy="-20" r="2.2" fill="{p}"/></g>
<path d="M-46 12 L46 12 A46 22 0 0 1 -46 12 Z" fill="{r}"/><path d="M-46 12 A46 22 0 0 0 46 12 L46 20 A46 22 0 0 1 -46 20 Z" fill="#6E2216"/>
<rect x="-16" y="0" width="32" height="12" rx="3" fill="{p}"/>{flame(0, 0, 1)}
<path transform="translate(-104 40) scale(.6)" fill="{r}" d="{SPARK}"/><path transform="translate(88 -60) scale(.5)" fill="{b}" d="{SPARK}"/><path transform="translate(92 54) scale(.4)" fill="{k}" d="{SPARK}"/>
</svg>'''
def icon_hat():
    dots = ''.join(f'<path d="{star_path(4.5, x, y, .5)}" fill="#F4D9A8"/>' for x, y in [(-6, -10), (8, 6), (-14, 24), (6, 36), (18, 26), (-2, 52)])
    return f'''<svg viewBox="-120 -120 240 230" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
<ellipse cx="0" cy="80" rx="80" ry="22" fill="{p}" opacity=".8"/>
<path d="M-50 70 C-30 30 -20 -20 -4 -70 C2 -92 30 -100 40 -86 C26 -84 16 -76 14 -60 C24 -20 36 30 50 70 Z" fill="{r}"/>{dots}
<path d="M-96 74 C-80 62 -60 80 -40 70 C-20 62 0 80 20 70 C40 62 60 80 80 70 C88 66 96 70 98 74 C80 98 -78 100 -96 74 Z" fill="{k}"/>
<path d="M-48 62 Q0 74 48 62" stroke="{b}" stroke-width="7" fill="none" stroke-linecap="round"/>
<path transform="translate(-102 -70) scale(.6)" fill="{r}" d="{SPARK}"/><path transform="translate(70 -24) scale(.45)" fill="{b}" d="{SPARK}"/><path transform="translate(-84 0) scale(.4)" fill="{k}" d="{SPARK}"/>
</svg>'''
def icon_ghost():
    return f'''<svg viewBox="-120 -120 240 230" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
<ellipse cx="0" cy="84" rx="74" ry="14" fill="{k}" opacity=".16"/>
<path d="M-56 80 C-48 30 -36 -20 -10 -74 C-4 -90 16 -96 30 -86 C20 -84 12 -78 10 -66 C34 -20 48 30 56 80 C44 72 36 88 24 80 C12 72 4 88 -8 80 C-20 72 -28 88 -40 80 C-46 76 -52 78 -56 80 Z" fill="{p}" stroke="{k}" stroke-width="2.5"/>
<path d="M-50 22 C-66 20 -74 34 -68 44 C-62 40 -56 36 -48 36Z" fill="{p}" stroke="{k}" stroke-width="2.5"/><path d="M50 22 C66 20 74 34 68 44 C62 40 56 36 48 36Z" fill="{p}" stroke="{k}" stroke-width="2.5"/>
<ellipse cx="-12" cy="6" rx="6" ry="9" fill="{r}"/><ellipse cx="14" cy="6" rx="6" ry="9" fill="{r}"/><ellipse cx="1" cy="32" rx="9" ry="12" fill="{r}"/>
<ellipse cx="1" cy="34" rx="5" ry="7" fill="#F4D9A8" opacity=".8"/>
<path transform="translate(-100 -60) scale(.6)" fill="{r}" d="{SPARK}"/><path transform="translate(74 -70) scale(.5)" fill="{b}" d="{SPARK}"/><path transform="translate(86 20) scale(.4)" fill="{k}" d="{SPARK}"/>
</svg>'''

# ------------------------------------------------------------------ step sketches (viewBox 0 0 120 92)
O = f'stroke="{k}" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"'
def st_roll(mm='5'): return f'<rect x="12" y="52" width="96" height="20" rx="4" fill="{b}"/><rect x="18" y="30" width="84" height="14" rx="7" fill="{s}" {O}/><path d="M8 37h10M102 37h10" {O}/><path d="M112 52v20" stroke="{r}" stroke-width="2"/><text x="113" y="66" font-size="8" fill="{r}">{mm}</text>'
def st_cut(shape): return f'<rect x="4" y="10" width="112" height="76" rx="6" fill="{b}"/>{shape}<path d="M100 18 l12 -14" stroke="{k}" stroke-width="3" stroke-linecap="round"/>'
def st_straw(shape): return f'{shape}<rect x="76" y="2" width="7" height="36" rx="3.5" fill="{r}" transform="rotate(20 80 20)"/>'
def st_lamp(icon_inner): return icon_inner
def st_dry(): return f'<rect x="10" y="66" width="100" height="6" rx="3" fill="{k}"/><path d="M30 64 L40 30 L50 64Z" fill="{b}"/><path d="M70 64 L80 34 L90 64Z" fill="{b}"/><path d="M24 20c4-6 10-6 14 0M84 16c4-6 10-6 14 0" {O} opacity=".6"/>'

STEPS = {
 'bat': lambda i: [st_roll(), st_cut(f'<g transform="translate(56 44) scale(.48)"><path d="{BAT}" fill="{p}" stroke="{r}" stroke-width="3" stroke-dasharray="7 5"/></g>'),
   f'<g transform="translate(60 54) scale(.5)"><path d="{BAT}" fill="{b}"/></g><path d="M8 72 Q60 40 112 72" stroke="{k}" stroke-width="7" fill="none" stroke-linecap="round" opacity=".45"/><path d="M60 12v14M54 20l6 6 6-6" {O}/>',
   f'<g transform="translate(60 40) scale(.5)"><path d="{BAT}" fill="{b}"/></g><path d="M38 52 L82 52 A22 14 0 0 1 38 52Z" fill="{r}"/><path d="M38 52 A22 14 0 0 0 82 52" stroke="{k}" stroke-width="2.4" fill="none"/>',
   st_dry(),
   f'<rect x="4" y="4" width="112" height="84" rx="6" fill="{s}"/><circle cx="60" cy="16" r="3" fill="{k}"/><g transform="translate(60 40) scale(.48)"><path d="{BAT}" fill="{k}"/></g><path d="M40 52 L80 52 A20 12 0 0 1 40 52Z" fill="{r}"/>' + flame(60, 50, .8)][i],
 'hat': lambda i: [st_roll('4–5'), st_cut(f'<path d="M30 80 L60 14 L90 80 Q60 92 30 80Z" fill="{p}" stroke="{r}" stroke-width="1.6" stroke-dasharray="4 3"/>'),
   st_straw(f'<path d="M30 80 L60 14 L90 80 Q60 92 30 80Z" fill="{b}"/>' + ''.join(f'<path d="{star_path(4, x, y)}" fill="#fff"/>' for x, y in [(54, 50), (66, 64), (50, 72), (62, 36)])),
   f'<path d="M40 80 L60 14 L80 80Z" fill="{b}"/><path d="M60 14 L66 80" stroke="{k}" stroke-width="2" stroke-dasharray="3 3"/><path d="M20 60c8-6 8-14 0-20M100 60c-8-6-8-14 0-20" {O}/>',
   f'<path d="M10 78 C24 70 36 84 50 76 C64 68 76 84 90 76 C98 72 106 74 110 78 C90 92 28 92 10 78Z" fill="{k}"/><path d="M40 76 L60 16 L80 76Z" fill="{b}"/><path d="M60 16 C62 6 74 4 80 10" stroke="{b}" stroke-width="7" fill="none" stroke-linecap="round"/>',
   f'<path d="M10 78 C24 70 36 84 50 76 C64 68 76 84 90 76 C98 72 106 74 110 78 C90 92 28 92 10 78Z" fill="{k}"/><path d="M40 76 L60 16 L80 76Z" fill="{r}"/>' + ''.join(f'<path d="{star_path(3.6, x, y)}" fill="#F4D9A8"/>' for x, y in [(56, 50), (66, 62), (52, 68), (62, 36)])][i],
 'ghost': lambda i: [st_roll('4–5'), st_cut(f'<path d="M30 80 L60 14 L90 80 Q60 92 30 80Z" fill="{p}" stroke="{r}" stroke-width="1.6" stroke-dasharray="4 3"/>'),
   f'<path d="M30 80 L60 14 L90 80 Q60 92 30 80Z" fill="{b}"/><ellipse cx="53" cy="56" rx="3.5" ry="5" fill="#fff"/><ellipse cx="67" cy="56" rx="3.5" ry="5" fill="#fff"/><ellipse cx="60" cy="70" rx="5" ry="6.5" fill="#fff"/><path d="M96 20 l12 -14" stroke="{k}" stroke-width="3" stroke-linecap="round"/>',
   f'<path d="M40 80 L60 14 L80 80Z" fill="{b}"/><path d="M60 14 L66 80" stroke="{k}" stroke-width="2" stroke-dasharray="3 3"/><path d="M20 60c8-6 8-14 0-20M100 60c-8-6-8-14 0-20" {O}/>',
   f'<path d="M38 80 L58 20 C60 10 72 8 78 14 C70 14 66 18 64 24 L82 80Z" fill="{b}"/><path d="M40 52 C28 50 24 60 28 66 C32 62 36 60 42 60Z" fill="{b}" stroke="{k}" stroke-width="1.6"/><path d="M80 52 C92 50 96 60 92 66 C88 62 84 60 78 60Z" fill="{b}" stroke="{k}" stroke-width="1.6"/>',
   f'<path d="M38 80 L58 20 C60 10 72 8 78 14 C70 14 66 18 64 24 L82 80 C76 76 72 84 66 80 C60 76 56 84 50 80 C46 77 42 80 38 80Z" fill="{p}" stroke="{k}" stroke-width="1.6"/><ellipse cx="55" cy="50" rx="3" ry="4.5" fill="{r}"/><ellipse cx="66" cy="50" rx="3" ry="4.5" fill="{r}"/><ellipse cx="60" cy="64" rx="4.5" ry="6" fill="{r}"/>' + flame(60, 70, .5)][i],
}

# ------------------------------------------------------------------ texts
AIR = {'de': 'Lufttrocknender Ton: nur mit LED-Teelicht verwenden.', 'en': 'Air-dry clay: use with an LED tealight only.'}
T = {
 'bat': {
  'de': dict(title='Fledermaus-Wandteelicht', sub='Slab-Template · Halloween', lvl='Mittel · ca. 2 Stunden', size='ca. 18 × 9 cm, für 1 Teelicht',
    intro='Eine Fledermaus mit geschwungenen Flügeln für die Wand, mit kleiner Ablage für ein Teelicht. Hängt an einem Nagel und sorgt im Herbst für ein gemütlich-gruseliges Licht.',
    needs=['Ton (ca. 500 g)', 'Nudelholz & 2 Holzleisten (5 mm)', 'Nadel oder Cuttermesser', 'Trinkhalm für das Aufhängeloch', 'Schlicker & Schwamm', 'Ein zusammengerolltes Handtuch'],
    steps=['Ton 5 mm dick ausrollen und glatt streichen.', 'Rückwand A, Ablage B und Rand C ausschneiden.', 'Loch im Kopf stechen. Flügel über ein gerolltes Handtuch legen, damit sie sich leicht wölben.', 'Wenn der Ton lederhart ist: Ablage B an der gestrichelten Linie ansetzen, Rand C rundherum anbringen.', 'Langsam abgedeckt trocknen lassen, dann brennen und glasieren.', 'An einen Nagel hängen, Teelicht auf die Ablage, fertig!'],
    tips=['Stütze die Ablage beim Trocknen mit einem Röllchen Küchenpapier, so bleibt sie waagrecht.', 'Augen und Ohren mit einer Nadel ritzen statt ausschneiden, dann bleibt alles stabil.', 'Steinzeug mit echtem Teelicht, ' + AIR['de'][0].lower() + AIR['de'][1:]],
    pages=[('Schablone A · Rückwand', 'Ausschneiden entlang der durchgezogenen Linie. Kreis: Aufhängeloch. Gestrichelt: hier sitzt die Ablage.'),
           ('Schablonen B & C · Ablage und Rand', 'B ist die Ablage für das Teelicht, C der Rand rundherum (bei Bedarf etwas kürzen).')],
    share='Zeig mir deine Fledermaus! Markiere @forma.potteryartcraft'),
  'en': dict(title='Bat Wall Tealight', sub='Slab template · Halloween', lvl='Medium · approx. 2 hours', size='approx. 18 × 9 cm, for 1 tealight',
    intro='A bat with curved wings for your wall, with a little shelf for a tealight. It hangs on a nail and brings a cosy-spooky glow to autumn evenings.',
    needs=['Clay (approx. 500 g)', 'Rolling pin & 2 guide sticks (5 mm)', 'Needle tool or craft knife', 'A drinking straw for the hanging hole', 'Slip & sponge', 'A rolled-up towel'],
    steps=['Roll out the clay 5 mm thick and smooth it.', 'Cut out back plate A, shelf B and lip C.', 'Punch the hole in the head. Lay the wings over a rolled towel so they curve slightly.', 'Once leather-hard: attach shelf B along the dashed line, then add lip C around the edge.', 'Let it dry slowly under cover, then fire and glaze.', 'Hang it on a nail, add a tealight and enjoy!'],
    tips=['Support the shelf with a roll of kitchen paper while drying so it stays level.', 'Score the eyes and ears with a needle instead of cutting them, so everything stays sturdy.', 'Stoneware with a real tealight. ' + AIR['en']],
    pages=[('Template A · Back plate', 'Cut along the solid line. Circle: hanging hole. Dashed: this is where the shelf goes.'),
           ('Templates B & C · Shelf and lip', 'B is the tealight shelf, C the lip around it (trim if needed).')],
    share='Show me your bat! Tag @forma.potteryartcraft')},
 'hat': {
  'de': dict(title='Hexenhut-Windlicht', sub='Slab-Template · Halloween', lvl='Mittel · ca. 2 Stunden', size='ca. 14 cm breit, 13 cm hoch',
    intro='Ein schiefer Hexenhut mit gewellter Krempe und kleinen Sternen, durch die das Licht funkelt. Einfach über ein Teelicht stellen.',
    needs=['Ton (ca. 600 g)', 'Nudelholz & 2 Holzleisten (4–5 mm)', 'Nadel oder Cuttermesser', 'Trinkhalme in zwei Größen', 'Schlicker & Schwamm', 'Papier und Klebeband für einen Stützkegel'],
    steps=['Ton 4–5 mm dick ausrollen und glatt streichen.', 'Kegel A und Krempe B ausschneiden.', 'Sterne und Punkte im Kegel mit Trinkhalm oder Nadel ausstechen.', 'Kegel über einen Papierkegel legen, die Naht auf der Lasche mit Schlicker schließen.', 'Kegel auf die Krempe setzen und verstreichen. Die Spitze leicht zur Seite knicken, die Krempe wellen.', 'Trocknen lassen, brennen, über ein Teelicht stellen, fertig!'],
    tips=['Den Papierkegel erst entfernen, wenn der Ton lederhart ist.', 'Nicht zu viele Löcher nah an der Naht, dort braucht der Hut Halt.', 'Steinzeug mit echtem Teelicht, ' + AIR['de'][0].lower() + AIR['de'][1:]],
    pages=[('Schablone A · Hut-Kegel', 'Ausschneiden entlang der durchgezogenen Linie. Grau: Klebelasche für die Naht. Sterne und Kreise: ausstechen.'),
           ('Schablone B · Krempe', 'Innen den Kreis ausschneiden, dort sitzt der Kegel. Die Welle darfst du frei nachformen.')],
    share='Zeig mir deinen Hexenhut! Markiere @forma.potteryartcraft'),
  'en': dict(title='Witch Hat Lantern', sub='Slab template · Halloween', lvl='Medium · approx. 2 hours', size='approx. 14 cm wide, 13 cm tall',
    intro='A wonky witch hat with a wavy brim and little stars that let the light sparkle through. Simply place it over a tealight.',
    needs=['Clay (approx. 600 g)', 'Rolling pin & 2 guide sticks (4–5 mm)', 'Needle tool or craft knife', 'Drinking straws in two sizes', 'Slip & sponge', 'Paper and tape for a support cone'],
    steps=['Roll out the clay 4–5 mm thick and smooth it.', 'Cut out cone A and brim B.', 'Punch the stars and dots in the cone with a straw or needle.', 'Wrap the cone around a paper cone and close the seam over the tab with slip.', 'Set the cone onto the brim and blend. Bend the tip slightly, wave the brim.', 'Let it dry, fire, place it over a tealight and enjoy!'],
    tips=['Remove the paper cone only once the clay is leather-hard.', 'Keep the holes away from the seam, that is where the hat needs strength.', 'Stoneware with a real tealight. ' + AIR['en']],
    pages=[('Template A · Hat cone', 'Cut along the solid line. Grey: tab for the seam. Stars and circles: punch out.'),
           ('Template B · Brim', 'Cut out the inner circle, that is where the cone sits. Shape the wave freely.')],
    share='Show me your witch hat! Tag @forma.potteryartcraft')},
 'ghost': {
  'de': dict(title='Gespenst-Windlicht', sub='Slab-Template · Halloween', lvl='Einfach · ca. 1,5 Stunden', size='ca. 8 cm breit, 12 cm hoch',
    intro='Ein freundliches kleines Gespenst mit Zipfelmütze und Ärmchen. Über ein Teelicht gestellt, leuchten Augen und Mund in der Dunkelheit.',
    needs=['Ton (ca. 400 g)', 'Nudelholz & 2 Holzleisten (4–5 mm)', 'Nadel oder Cuttermesser', 'Schlicker & Schwamm', 'Papier und Klebeband für einen Stützkegel'],
    steps=['Ton 4–5 mm dick ausrollen und glatt streichen.', 'Gespenst A und zwei Ärmchen B ausschneiden.', 'Augen und Mund ausschneiden, Kanten mit feuchtem Finger glätten.', 'Über einen Papierkegel legen und die Naht auf der Lasche mit Schlicker schließen.', 'Ärmchen seitlich ansetzen, die Spitze verspielt zur Seite knicken.', 'Trocknen lassen, brennen, über ein Teelicht stellen, fertig!'],
    tips=['Die gewellte Unterkante lässt Luft an das Teelicht, so brennt es schön.', 'Ganz in Weiß glasiert oder roh gelassen sieht das Gespenst besonders schön aus.', 'Steinzeug mit echtem Teelicht, ' + AIR['de'][0].lower() + AIR['de'][1:]],
    pages=[('Schablone A · Gespenst', 'Ausschneiden entlang der durchgezogenen Linie. Grau: Klebelasche für die Naht. Augen und Mund ausschneiden.'),
           ('Schablone B · Ärmchen', 'Zweimal ausschneiden, eins davon gespiegelt.')],
    share='Zeig mir dein Gespenst! Markiere @forma.potteryartcraft'),
  'en': dict(title='Ghost Lantern', sub='Slab template · Halloween', lvl='Easy · approx. 1.5 hours', size='approx. 8 cm wide, 12 cm tall',
    intro='A friendly little ghost with a floppy hat tip and tiny arms. Placed over a tealight, its eyes and mouth glow in the dark.',
    needs=['Clay (approx. 400 g)', 'Rolling pin & 2 guide sticks (4–5 mm)', 'Needle tool or craft knife', 'Slip & sponge', 'Paper and tape for a support cone'],
    steps=['Roll out the clay 4–5 mm thick and smooth it.', 'Cut out ghost A and two arms B.', 'Cut out the eyes and mouth, smooth the edges with a damp finger.', 'Wrap it around a paper cone and close the seam over the tab with slip.', 'Attach the arms at the sides and bend the tip playfully.', 'Let it dry, fire, place it over a tealight and enjoy!'],
    tips=['The wavy bottom edge lets air reach the tealight so it burns nicely.', 'Glazed all white or left raw, the ghost looks especially lovely.', 'Stoneware with a real tealight. ' + AIR['en']],
    pages=[('Template A · Ghost', 'Cut along the solid line. Grey: tab for the seam. Cut out the eyes and mouth.'),
           ('Template B · Arms', 'Cut twice, flip one of them.')],
    share='Show me your ghost! Tag @forma.potteryartcraft')},
}
COM = {'de': dict(youneed='Das brauchst du', print_h='Drucken', stepsT='So geht’s', tip='Tipp', test='Testquadrat 5 × 5 cm',
                  print='Drucke die Schablonen in Originalgröße (100 %, „Tatsächliche Größe“), nie „An Seite anpassen“. Das Testquadrat muss genau 5 × 5 cm messen.',
                  legal='© FORMA Earth &amp; Art · Nur für den privaten Gebrauch. Weitergabe und Verkauf der Datei sind nicht erlaubt.'),
       'en': dict(youneed='You will need', print_h='Printing', stepsT='How it works', tip='Tip', test='Test square 5 × 5 cm',
                  print='Print the templates at full size (100 %, “Actual size”), never “Fit to page”. The test square must measure exactly 5 × 5 cm.',
                  legal='© FORMA Earth &amp; Art · For personal use only. Sharing or selling the file is not permitted.')}

# ------------------------------------------------------------------ template sheets
def svgbox(x, y, w, h, vb, inner):
    return f'<svg width="{w}mm" height="{h}mm" viewBox="{vb}" style="position:absolute;left:{x}mm;top:{y}mm;overflow:visible">{inner}</svg>'
def lab(x, y, t, sz=14): return f'<text x="{x}" y="{y}" text-anchor="middle" font-family="B" font-size="{sz}" fill="{b}">{t}</text>'
CUT = f'fill="#fff" stroke="{ink}" stroke-width="{LW}" stroke-linejoin="round"'
HOLE = f'fill="none" stroke="{ink}" stroke-width=".45"'
DASH = f'fill="none" stroke="{r}" stroke-width=".4" stroke-dasharray="2.4 1.6"'
TAB = f'fill="#EFE6DC" stroke="{ink}" stroke-width=".35" stroke-dasharray="1.6 1.2"'
def testsq(x, y, t): return f'<div class="testsq" style="position:absolute;left:{x}mm;top:{y}mm;margin:0">{t}</div>'

def sheets_bat(t, c):
    A = svgbox(12, 46, 186, 92, '-93 -53 186 92', f'<path d="{BAT}" {CUT}/><path d="{circ(0, -26, 3.5)}" {HOLE}/><path d="M-40 10 L40 10" {DASH}/>' + lab(0, -2, 'A'))
    p3 = (A, testsq(80, 175, c['test']))
    B = svgbox(22, 40, 90, 46, '-45 -3 90 46', f'<path d="{SHELF}" {CUT}/>' + lab(0, 22, 'B'))
    C = svgbox(22, 110, 136, 18, '-3 -2 136 18', f'<rect x="0" y="0" width="130" height="14" rx="1" {CUT}/>' + lab(65, 11, 'C', 10))
    return [p3, (B, C, testsq(130, 36, c['test']))]

def sheets_cone(kind, t, c):
    if kind == 'hat':
        d_, tabd, L, th = cone(80, 130)
    else:
        d_, tabd, L, th = cone(80, 120, wavy=2.2)
    x0, y0, x1, y1 = bbox_cone(L, th, 8)
    w, h = x1 - x0 + 6, y1 - y0 + 6
    extra = ''
    mid = th / 2
    if kind == 'hat':
        for rr, da, sz in [(48, -.18, 4.2), (62, .22, 5), (78, -.05, 5.5), (92, .26, 4.5), (100, -.24, 5), (112, .05, 6), (118, -.32, 4), (70, -.4, 3.8), (88, .45, 3.6)]:
            x, y = polar(rr, mid + da)
            extra += f'<path d="{star_path(sz, x, y, .5, rot=-90 + math.degrees(mid + da))}" {HOLE}/>'
        for rr, da in [(56, .42), (84, -.36), (106, .38), (40, .08)]:
            x, y = polar(rr, mid + da); extra += f'<path d="{circ(x, y, 2.5)}" {HOLE}/>'
    else:
        ang = math.degrees(mid)
        ex, ey = polar(86, mid)
        extra = f'<g transform="translate({f(ex)} {f(ey)}) rotate({f(ang - 90)})"><path d="{ell(-10, 0, 5, 7.5)}" {HOLE}/><path d="{ell(10, 0, 5, 7.5)}" {HOLE}/><path d="{ell(0, 20, 7, 9)}" {HOLE}/></g>'
    lx, ly = polar(L * .3, mid)
    A = svgbox(max(6, (210 - w) / 2), 44, w, h, f'{f(x0 - 3)} {f(y0 - 3)} {f(w)} {f(h)}', f'<path d="{tabd}" {TAB}/><path d="{d_}" {CUT}/>{extra}' + lab(f(lx), f(ly), 'A'))
    p3 = (A, testsq(14, 218, c['test']))
    if kind == 'hat':
        pts = []
        for i in range(181):
            a = 2 * math.pi * i / 180; rr = 68 + 4 * math.sin(a * 9)
            pts.append((rr * math.cos(a), rr * math.sin(a)))
        brim = 'M' + ' L'.join(f'{f(x)} {f(y)}' for x, y in pts) + 'Z'
        B = svgbox(31, 40, 148, 148, '-74 -74 148 148', f'<path d="{brim} {circ(0, 0, 40)}" {CUT} fill-rule="evenodd"/>' + lab(0, -50, 'B'))
        return [p3, (B, testsq(80, 200, c['test']))]
    arm = 'M0 0 C-14 -2 -22 8 -18 18 C-12 14 -6 12 2 12 Z'
    B = svgbox(30, 50, 60, 52, '-24 -6 30 26', f'<path d="{arm}" {CUT}/>' + lab(-8, 9, 'B', 6))
    B2 = svgbox(120, 50, 60, 52, '-6 -6 30 26', f'<path d="{arm}" transform="scale(-1 1)" {CUT}/>' + lab(8, 9, 'B', 6))
    return [p3, (B, B2, testsq(80, 130, c['test']))]

# ------------------------------------------------------------------ pages
exec(open(REPO + 'tools/templates/examples/css_snip.txt').read().replace('def build(lang):', 'def _css(lang):').replace('    t=TXT[lang]\n', '') + '    return css\n')
EXTRA = '.step svg{height:21mm!important;margin-bottom:2mm!important}.step p{font-size:9.8pt!important}.tips{margin-top:5mm!important}.tips .box{padding:4.5mm!important}.tpl h2{font-size:17pt!important}'
def foot(t, n): return f'<div class="pfoot"><img src="{R}img/forma-logo.png"><span>{t["title"]} · FORMA Template-Shop</span><span>{n}</span></div>'
def page(inner, cls=''): return f'<section class="page {cls}">{inner}</section>'
ICON = {'bat': icon_bat, 'hat': icon_hat, 'ghost': icon_ghost}
def build(kind, lang):
    t = T[kind][lang]; c = COM[lang]
    P = [page(f'''<span class="blob" style="width:150mm;height:150mm;right:-60mm;top:-50mm;background:{b}"></span>
<span class="blob" style="width:90mm;height:90mm;left:-40mm;bottom:20mm;background:{s}"></span>
<span class="lang">{lang.upper()}</span>
<img class="logo" src="{R}img/forma-logo.png"><p class="kick">{t['sub']}</p><h1>{t['title']}</h1>
<div class="ic" style="height:96mm;width:124mm;margin:12mm 0 8mm">{ICON[kind]()}</div>
<div class="chips"><span>{t['lvl']}</span><span>{t['size']}</span></div>
<p class="intro">{t['intro']}</p>''', 'cover')]
    steps = ''.join(f'<div class="step"><svg viewBox="0 0 120 92">{STEPS[kind](i)}</svg><b>{i + 1}</b><p style="display:inline">{st}</p></div>' for i, st in enumerate(t['steps']))
    tips = ''.join(f'<div class="box"><h3>{c["tip"]}</h3><p>{x}</p></div>' for x in t['tips'])
    P.append(page(f'''<div class="two"><div class="box"><h3>{c['youneed']}</h3><ul>{''.join(f'<li>{n}</li>' for n in t['needs'])}</ul></div>
<div><h3>{c['print_h']}</h3><p class="printnote">{c['print']}</p></div></div>
<h2>{c['stepsT']}</h2><div class="steps">{steps}</div><div class="tips">{tips}</div>{foot(t, 2)}'''))
    sh = sheets_bat(t, c) if kind == 'bat' else sheets_cone(kind, t, c)
    for i, parts in enumerate(sh):
        last = i == len(sh) - 1
        tail = f'<div style="position:absolute;left:16mm;right:16mm;bottom:18mm"><p class="share">{t["share"]}</p><p class="legal">{c["legal"]}</p></div>' if last else ''
        P.append(page(f'<div class="lbl"><h2 style="margin-bottom:1mm">{t["pages"][i][0]}</h2><p>{t["pages"][i][1]}</p></div>{"".join(parts)}{tail}{foot(t, 3 + i)}', 'tpl'))
    return f'<!doctype html><html lang="{lang}"><meta charset="utf-8"><style>{_css(lang)}{EXTRA}</style><body>{"".join(P)}</body></html>'

KEYS = {'bat': 'fledermaus-wandteelicht', 'hat': 'hexenhut-windlicht', 'ghost': 'gespenst-windlicht'}
async def main():
    async with async_playwright() as pw:
        br = await pw.chromium.launch(); pg = await br.new_page()
        for kind, key in KEYS.items():
            for lang in ('de', 'en'):
                fn = f'{key}-{lang}'
                open(D + fn + '.html', 'w').write(build(kind, lang))
                await pg.goto('file://' + D + fn + '.html'); await pg.wait_for_timeout(700)
                await pg.pdf(path=D + fn + '.pdf', format='A4', print_background=True, prefer_css_page_size=True)
            html = f'<!doctype html><meta charset=utf-8><body style="margin:0;width:1200px;height:900px;background:#EEE2D4;display:grid;place-items:center;position:relative;overflow:hidden"><span style="position:absolute;width:560px;height:560px;border-radius:50%;background:{b};right:-200px;top:-260px"></span><span style="position:absolute;width:320px;height:320px;border-radius:50%;background:{s};left:-110px;bottom:-90px"></span><div style="width:760px;height:620px;position:relative">{ICON[kind]()}</div></body>'
            p2 = await br.new_page(viewport={'width': 1200, 'height': 900}); await p2.set_content(html); await p2.wait_for_timeout(200)
            await p2.screenshot(path=D + key + '.jpg', type='jpeg', quality=88); await p2.close()
        await br.close()
asyncio.run(main())

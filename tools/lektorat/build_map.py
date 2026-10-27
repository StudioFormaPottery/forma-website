#!/usr/bin/env python3
"""Sprach-Lektorat 27.10.2026: Ersetzungsliste alt → neu für Texte, die schon in der Datenbank stehen.

Vergleicht die Quellen vor dem Lektorat (Git-Stand BASE) mit dem aktuellen Stand und schreibt
api/seed/lektorat-2026-10-27.json = {"v": 1, "map": {alter Text: neuer Text}, "names": {alter Name: neuer Name}}.
mv_lektorat_sync() in api/method-lib.php ersetzt damit nur Felder, die noch genau den alten Seed-Text enthalten
(eigene Änderungen von Vivien bleiben unberührt), und Voice-over-Texte nur, wenn dazu noch keine Aufnahme existiert.
Aufruf: python3 tools/lektorat/build_map.py
"""
import json, os, subprocess, difflib

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
BASE = '5a437c7'
OUT = os.path.join(ROOT, 'api', 'seed', 'lektorat-2026-10-27.json')


def old(path):
    return subprocess.check_output(['git', '-C', ROOT, 'show', f'{BASE}:{path}']).decode()


def new(path):
    return open(os.path.join(ROOT, path), encoding='utf-8').read()


M, NAMES = {}, {}


def pair(a, b):
    if isinstance(a, str) and isinstance(b, str) and a != b and a.strip():
        M[a] = b


def walk(a, b):
    """gleiche JSON-Struktur parallel durchlaufen, geänderte Texte paaren"""
    if isinstance(a, dict) and isinstance(b, dict):
        for k in a:
            if k in b: walk(a[k], b[k])
    elif isinstance(a, list) and isinstance(b, list) and len(a) == len(b):
        for x, y in zip(a, b): walk(x, y)
    else:
        pair(a, b)


for p in ['api/seed/produktion.json', 'api/seed/lesson-scripts.json', 'api/seed/unfold-videos.json']:
    walk(json.loads(old(p)), json.loads(new(p)))

# Übungsnamen in den Schritten (Name DE, Name EN) getrennt führen
o, n = json.loads(old('api/seed/produktion.json')), json.loads(new('api/seed/produktion.json'))
def steps(j):
    return list(j['steps'].values()) + [x.get('steps') or [] for x in j['new']]
for sa, sb in zip(steps(o), steps(n)):
    for x, y in zip(sa, sb):
        if x[0] != y[0]: NAMES[x[0]] = y[0]
        if x[1] != y[1]: NAMES[x[1]] = y[1]
        M.pop(x[0], None); M.pop(x[1], None)

# PHP: String-Literale alt/neu paarweise (Lektionstexte im Seed, Gathering-Vorlagen, Teleprompter-Skripte)
PHP = r'''$f = $argv[1]; $out = [];
foreach (token_get_all(file_get_contents($f)) as $t)
  if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) { $s = $t[1];
    $out[] = $s[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($s, 1, -1)) : stripcslashes(substr($s, 1, -1)); }
echo json_encode($out, JSON_UNESCAPED_UNICODE);'''
def literals(src):
    tmp = '/tmp/_lekt_src.php'
    open(tmp, 'w', encoding='utf-8').write(src)
    return json.loads(subprocess.check_output(['php', '-r', PHP, tmp]))

for p in ['api/method-lib.php', 'api/movecia-lib.php', 'wildhearts/gathering/skripte.inc.php', 'wildhearts/gathering/skripte-de.inc.php']:
    a, b = literals(old(p)), literals(new(p))
    for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, a, b, autojunk=False).get_opcodes():
        if tag == 'replace' and i2 - i1 == j2 - j1:
            for x, y in zip(a[i1:i2], b[j1:j2]):
                if len(x) > 12: pair(x, y)

json.dump({'v': 1, 'map': M, 'names': NAMES}, open(OUT, 'w', encoding='utf-8'), ensure_ascii=False, indent=0)
print(f'{len(M)} Texte, {len(NAMES)} Namen → {OUT}')

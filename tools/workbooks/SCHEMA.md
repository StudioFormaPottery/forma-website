# Movecia-Workbooks · Inhaltsformat

Jedes Workbook ist eine JSON-Datei in `tools/workbooks/content/<key>.<lang>.json`. `tools/workbooks/build.py` rendert daraus ein PDF (A4, Movecia-Design mit Icons). Die Inhalte bestimmen alles, das Layout macht der Renderer.

```json
{
  "key": "unfold-m1",
  "lang": "de",
  "eyebrow": "Unfold · Monat 1 · Woche 1–4",
  "title": "Wurzeln",
  "subtitle": "Ankommen & Raum schaffen",
  "icon": "keim",
  "letter": ["Absatz 1 …", "Absatz 2 …"],
  "overview": [{"icon": "kompass", "title": "Woche 1 · Wo stehst du gerade?", "text": "ein Satz"}],
  "chapters": [
    {"icon": "kompass", "kicker": "Woche 1", "title": "Wo stehst du gerade?", "intro": "ein bis zwei Sätze", "blocks": [ … ]}
  ],
  "closing": "ein warmer Schlusssatz"
}
```

`letter` = persönlicher Brief von Vivien am Anfang (3–6 Absätze). `overview` = Inhaltsübersicht (optional). Jedes Kapitel beginnt auf einer neuen Seite.

## Blöcke (`blocks`)

| t | Felder | wofür |
|---|---|---|
| `p` | `text` | normaler Absatz |
| `h` | `text` | Zwischenüberschrift |
| `list` | `items` [str] | Aufzählung |
| `steps` | `items` [str] | nummerierte Schritte |
| `fact` | `title`, `text` | Kasten „Gut zu wissen“ (Wissen, Hintergrund) |
| `tip` | `text` | „Bestie-Tipp“: kleiner, persönlicher Tipp von Vivien |
| `quote` | `text` | Affirmation oder Leitsatz, groß gesetzt |
| `exercise` | `title`, `time`, `intro`, `steps` [str] | Übung/Reflexion zum Mitmachen |
| `prompts` | `title` (optional), `items` [str], `lines` (Zeilen je Frage, Standard 3) | Journal-Fragen mit Schreiblinien |
| `checklist` | `title`, `items` [str] | Liste zum Abhaken |
| `table` | `title`, `cols` [str], `rows` (Zahl leerer Zeilen) oder [[str]] (vorausgefüllt, "" = leer) | Tabelle zum Ausfüllen |
| `scale` | `title`, `items` [str], `low`, `high` | Skala 1–10 zum Einkreisen |
| `tracker` | `title`, `items` [str] | Wochen-Tracker Mo–So zum Ankreuzen |
| `breath` | `title`, `pattern` (z. B. "4 · 4 · 4 · 4"), `text` | Atemübung mit Atemkreis |
| `planner` | `title` | ganze Seite Wochenplaner (Mo–So, Prioritäten, Selbstfürsorge, Notizen) |
| `recipe` | `title`, `time`, `serves`, `ingredients` [str], `steps` [str], `note` | Rezept |
| `notes` | `title` (optional), `lines` | freie Notizlinien |
| `pagebreak` | – | neue Seite erzwingen |

## Icons (Feld `icon`)
impuls, uebung, atem, meditation, home, journal, merk, tipp, uhr, stimmung, wasser, zweig, kopf, kalender, akku, schild, herz, stern, keim, blume, berg, mond, sonne, kerze, tee, topf, haken, schritte, feier, welle, kompass, spiegel, schmetterling, nest, rakete, umarmung, schluessel

## Ton (Vivien, 26.10.2026: „sehr umfangreich informieren, like I talk to my bestie, aber professionell“)
- Du-Form, warm, nah und ehrlich, als würde Vivien mit ihrer besten Freundin bei einem Tee sprechen, dabei fachlich fundiert und klar.
- Schönes Hochdeutsch, keine Umgangssprache, kein Dialekt, keine Jugendsprache, keine Emojis.
- Fließende, ganze Sätze. Keine abgehackten Kurzsätze oder Satzfragmente als Stilmittel, keine Ketten aus Einwortsätzen.
- Keine Gedankenstriche als Stilmittel, kein Muster „nicht X, sondern Y“, keine Dreierlisten aus Gewohnheit, keine leeren Kalendersprüche, kein Therapie-Jargon.
- Kein „tun“ als Füllverb („was man tut“), lieber ein genaues Verb.
- Konkret statt allgemein: echte Beispiele aus dem Alltag einer Frau mit Familie, Job und Mental Load, echte Übungen mit ihren gängigen Namen (Yoga, Somatics, Breathwork).
- Wissen korrekt und vorsichtig formulieren (Nervensystem, Stress, Schlaf, Gewohnheiten). Keine Heilversprechen, keine Diagnosen. Wo es passt, freundlich darauf hinweisen, sich bei anhaltender Erschöpfung oder seelischer Belastung Unterstützung bei Ärztin oder Therapeutin zu holen.
- Vivien lebt mit Mann und vier Kindern in Mathon im Paznaun (Tirol), hat ein Töpferstudio (FORMA), Pferde, unterrichtet Yoga/Bodyweight/Mindful Movement. Persönliche Bezüge sparsam und allgemein halten, nichts Konkretes erfinden (keine erfundenen Erlebnisse mit Namen, Zahlen oder Daten).

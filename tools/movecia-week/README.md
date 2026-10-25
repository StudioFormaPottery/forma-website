# Movecia Wochenpaket (Vivien, 25.10.2026)

Vivien möchte für ihre Community (Instagram @movementwithviv, WhatsApp-Kanal/Status) jeden Tag ein fertiges Bild mit Text,
das sie nur noch posten muss. Der frühere Wild-Hearts-Wochenplan (PDF mit Ideen) hat für sie nicht funktioniert.

Jeden **Sonntag** kommt das Paket für die **kommende Woche Montag bis Sonntag**:
- 7 Bilder Instagram 4:5 (1080 × 1350) und dieselben 7 als WhatsApp 9:16 (1080 × 1920), Dateiname mit Wochentag und Datum
- `Texte.txt`: Instagram-Caption mit Hashtags und kurzer WhatsApp-Text pro Tag
- alles als ZIP, damit sie es auf einmal aufs iPhone laden kann, dazu eine Vorschau-Übersicht

## Bauen
1. Lokalen Server im Repo starten: `FORMA_DATA_DIR=/tmp/x php -S 127.0.0.1:8765`
2. Wochen-JSON schreiben (Vorlage: `2026-10-26.json`, Felder im Docstring von `make_week.py`), Dateiname = Datum des Montags
3. `python3 tools/movecia-week/make_week.py tools/movecia-week/<datum>.json <ausgabeordner>`
4. Jedes Bild ansehen, Überlappungen oder abgeschnittene Texte korrigieren

## Inhalt
- Typen abwechseln: Gedanke (quote), kleine Übung (steps), Frage/Umfrage (poll), Foto mit Angebot (photo/offer), Journaling (quote, dark).
- Pro Woche höchstens zwei Angebots-Posts (Method, Unfold), der Rest schenkt echten Wert: Breathwork, kurze Übungen, Journaling-Fragen, Gedanken.
- Übungen nur echte Übungen aus Yoga, Pilates, Bodyweight, Somatics mit gängigen deutschen/englischen Namen (Katze-Kuh/Cat-Cow, Kindhaltung, Glute Bridge, Bird Dog …), keine Fantasienamen.
- Preise und Termine immer live aus `api/method-lib.php` (`mv_cfg_raw()`): Early-Bird Method bis 1.12.2026 (€ 29 statt € 39), Unfold Early-Bird bis 11.1.2027 (€ 450 statt € 490, 3 × € 165 statt 3 × € 175). Nach Ablauf nur noch die regulären Preise nennen. Gathering-Preise nie erwähnen. Inner Circle und Jahreskreis gibt es nicht mehr.
- Sprache: schönes, schlichtes Hochdeutsch, warm, fließende ganze Sätze. Keine Floskeln, keine Dreierketten, keine „nicht X, sondern Y“-Sätze, keine Gedankenstriche-Ketten, keine Therapie-Sprache, keine erfundenen Geschichten.
- Hashtags: 8–10 pro Caption, Mischung aus groß (#mindfulmovement, #yogafürfrauen) und Nische (#tiredday, #mentalload), immer #movecia.
- Bereits verwendete Themen in `history.md` eintragen und nicht wiederholen.
- Fotos nur aus `img/movecia/fotos/` (art-*.jpg bevorzugt), keine Fotos vom Töpfern.

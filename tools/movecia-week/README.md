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
- Übungen nur echte Übungen aus Yoga, Bodyweight, Somatics mit gängigen deutschen/englischen Namen (Katze-Kuh/Cat-Cow, Kindhaltung, Glute Bridge, Bird Dog …), keine Fantasienamen.
- Preise und Termine immer live aus `api/method-lib.php` (`mv_cfg_raw()`): Early-Bird Method bis 1.12.2026 (€ 29 statt € 39), Unfold Early-Bird bis 11.1.2027 (€ 450 statt € 490, 3 × € 165 statt 3 × € 175). Nach Ablauf nur noch die regulären Preise nennen. Gathering-Preise nie erwähnen. Inner Circle und Jahreskreis gibt es nicht mehr.
- Sprache: schönes, schlichtes Hochdeutsch, warm, fließende ganze Sätze. Keine Floskeln, keine Dreierketten, keine „nicht X, sondern Y“-Sätze, keine Gedankenstriche-Ketten, keine Therapie-Sprache, keine erfundenen Geschichten.
- Hashtags: 8–10 pro Caption, Mischung aus groß (#mindfulmovement, #yogafürfrauen) und Nische (#tiredday, #mentalload), immer #movecia.
- Bereits verwendete Themen in `history.md` eintragen und nicht wiederholen.
- Neue Fotos (28.10.2026, Everygen, von Vivien hochgeladen): `ev-katze-kuh.jpg` (Katze-Kuh), `ev-lunge.jpg` (Crescent Lunge, Arme hoch), `ev-savasana.jpg` (Savasana am Abend mit Kerzen), `ev-post-baby.jpg` (mit Neugeborenem im Arm, Gesicht erkennbar: nicht für die Website), `ev-post-trage.jpg` (von hinten mit Baby in der Trage), `ev-post-bauch.jpg` (Hände auf dem Bauch, ohne Gesicht). Fotos abwechseln, damit sich nichts ständig wiederholt.
- Fotos nur die neuen Bilder `img/movecia/fotos/ev-*.jpg` bzw. `pt-*.jpg` (heller Studio-Look unterm Dachstuhl, cremefarbene Outfits), nie art-*, fold, reach, keine Fotos vom Töpfern. Alte Pfade ersetzt `make_week.py` automatisch und meldet es.

## Look (Vivien, 27.10.2026: alles cute, modern, eine Movecia-Handschrift)
- `make_week.page()` ist die eine Vorlage für Wochenbilder, Pins (`tools/pinterest-movecia/make_pin.py`) und Werbung (`tools/movecia-ads/make_ads.py`): weicher Farbverlauf, drei Wellen unten, feine Pflaume-Linien, Kiesel-Zeichen, Themen-Icon in der runden Blase (Feld `icon`, Namen aus `tools/workbooks/icons.py`, sonst passend zum Typ), Listen als Pastell-Karten (`bullets: true` = Herzchen statt Zahlen), Fotos im Bogen mit Wellenkante (`pos` = Bildausschnitt, z. B. "50% 22%"), `chips` = kleine Infos.
- Nach jedem Bauen alle Bilder ansehen: nichts darf unten abgeschnitten sein oder in die Fußzeile laufen.

## Ton der Texte (Vivien, 31.10.2026, Pflicht)
Captions und WhatsApp-Texte klingen wie ein Telefonat mit der besten Freundin: ungeniert, ehrlich, mitten rein ins Problem aus dem Mama-Alltag, im Gespräch der Shift zu Selbstliebe und „sich selbst nicht vergessen“, Bewegung als Ventil (egal welche, nie Leistungssport), klare Haltung. Movecia nur unterschwellig, nie direkt verkaufen, kein „deshalb gibt es Movecia“. Angebots-Posts nennen die Fakten, erzählen aber trotzdem wie eine Freundin. Slogan „Bewegung beginnt bei dir.“ sparsam. Schönes Hochdeutsch, kein Dialekt.

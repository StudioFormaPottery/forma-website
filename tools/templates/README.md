# FORMA Templates: wöchentlicher Ablauf

Dieser Ordner ist nicht öffentlich (per `.htaccess` gesperrt). Hier liegen die Werkzeuge,
mit denen die Pottery-Templates für den Shop gebaut werden, und der Wochenplan.

## Jeden Freitag um 8 Uhr ein neues Template im Shop

Ablauf für die automatische Wochen-Session (läuft freitags früh, vor 8 Uhr):

1. Im Plan unten den Eintrag für diesen Freitag nehmen. Ist kein Eintrag da, ein neues Template
   wählen, das zu Saison und Stil passt und **nicht** auf der Ausschlussliste steht.
2. Template bauen wie die Beispiele in `examples/` (am nächsten dran: `build_cup.py`,
   `build_leaf.py`, `build_star.py`). Gemeinsames CSS: `examples/css_snip.txt`.
   Ausführen mit `python3 <skript>` in einem leeren Arbeitsordner, Playwright ist installiert.
   - Immer **zwei PDFs: Deutsch und Englisch**, A4, Schablonen in Originalgröße (100 %),
     mit Testquadrat 5 × 5 cm.
   - Aufbau: Titelseite (kleine Vektor-Illustration, Chips mit Level/Dauer/Größe, kurzer Intro-Text),
     Seite 2 „Das brauchst du“ + „So geht’s“ (6 Schritte mit kleinen Skizzen) + 3 Tipps,
     danach Schablonen-Seiten, am Ende Hinweis Instagram @forma.potteryartcraft und Nutzungsrechte.
   - Stil: wenig Text, viele kleine Vektor-Skizzen, verspielt, Scandi-minimalistisch.
     Farben nur Sand `#EEE2D4`, `#E4D2BF`, `#DDBFA8`, Tonrot `#8C2C1D`, Braun `#674B40`,
     Hell `#FBF1EA`, Text `#33211B`. Keine dritte Akzentfarbe, kein Orange.
     Schriften: Bricolage Grotesque (Titel, kräftig aber nicht zu fett), Outfit (Text),
     Italiana (Kicker). Anhänger: Loch immer innerhalb des Motivs.
   - Materialliste klein halten: Ton, Nudelholz, 2 Holzleisten, Messer, Schwamm, ggf. Trinkhalm.
   - Das Wort „Keramik“ nie verwenden (lieber Ton, Pottery, Werkstück).
   - Fußzeile: „<Titel> · FORMA Template-Shop“. Rechte: „Nur für den privaten Gebrauch.
     Weitergabe und Verkauf der Datei sind nicht erlaubt.“
3. Vorschaubild 1200 × 900 px (JPG) mit der Titel-Illustration auf Sand-Hintergrund,
   wie `Teetasse_Vorschau.jpg`.
4. Dateien ablegen in `api/seed/weekly/`:
   `<key>-de.pdf`, `<key>-en.pdf`, `<key>.jpg` (key = kleinbuchstaben-mit-bindestrich).
5. In `api/seed/weekly.json` einen Eintrag ergänzen:
   ```json
   {"key":"igel-zahnstocher","title":"Igel-Zahnstocherhalter","description":"…2–3 Sätze, Deutsch…",
    "season":"herbst","level":1,"price_cents":399,"publish_at":"2026-10-16T08:00"}
   ```
   `level`: 1 Einfach, 2 Mittel, 3 Anspruchsvoll. `season`: herbst | winter | fruehling | sommer | ganzjaehrig.
   Die Website stellt das Template zur Zeit `publish_at` (Wiener Zeit) automatisch online.
6. Prüfen: PDFs öffnen (`pdftoppm`), Seiten ansehen, nichts überlappt, alles auf A4.
   `php -l api/lib.php` falls PHP geändert wurde (normalerweise nicht nötig).
7. Commit + Push auf `main` (Hostinger übernimmt automatisch). Commit-Nachricht auf Deutsch.

## Ausschlussliste (Adventkalender 2026, nicht im Shop verkaufen)

Steinzeug-Kalender: 3D-Steckanhänger (Kugel, Stern, Tanne, Herz), organische Untersetzer,
Ringschälchen, Löffelablage, Serviettenringe, Eierbecher, Räucherschale, Knoblauch-/Ingwerreibe,
kleine Löffelchen, Salz- & Pfefferschälchen, Seifenschale, Teelichthalter, Kekstellerchen,
Servierbrettchen, Daumendruck-Becher, Becher mit Henkel, Espressotasse, Matcha-Schale mit Ausguss,
Müslischale, Butterdose, Ablage-Tablett, Stabkerzenhalter, Blumentopf mit Untersetzer,
Obstschale mit Henkel-Ohren.

Paper-Clay-Kalender: Geschenkanhänger, Duftsteine, Platzkartenhalter, Foto-/Kartenhalter,
Sternen-Girlande, Wandhaken, Mini-Bilderrahmen, Teelichthalter für Glaseinsatz, Schlüsselablage,
Schmuckschale mit Henkel-Ohren, Pinsel-/Stiftebecher, Übertopf, Schmuckständer, Wandteller Mond,
Tonperlen-Girlande, Mobile, Deko-Tablett, Schmuckdose, Buchstütze, Lichterhäuschen.

Schon im Shop / Maker Club: Kürbisteller, Herbst-Teetasse, Blatt-Schälchen, Sternen-Anhänger.

## Plan Oktober 2026

| Freitag 8 Uhr | Template | Level | Saison |
| --- | --- | --- | --- |
| 16.10. | Igel-Zahnstocherhalter (kleiner Igel, Stacheln = Zahnstocher) | Einfach | herbst |
| 23.10. | Vogel-Futterschale zum Aufhängen (für den Garten, Steinzeug) | Mittel | herbst |
| 30.10. | Herbstkranz aus Tonblättern zum Aufhängen | Einfach | herbst |

Preis jeweils € 3,99.

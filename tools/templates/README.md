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
5b. Shop-Slides erzeugen: `python3 tools/templates/slides.py <key>` (vorher den Key in `SRC` ergänzen, falls er nicht
   unter `api/seed/weekly/` liegt). Das legt `img/shop/<key>-2.jpg` (Blick in die Anleitung) und `-3.jpg`
   (Schablonen) an; der Shop zeigt sie automatisch als Slides 2 und 3 neben dem Titelbild.
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

## Stil der Wochen-Templates

Scandi, Hygge, Interior, DIY: ruhige, organische Formen, Wellenränder, schlichte Henkel,
Dinge für ein gemütliches Zuhause, die man wirklich benutzt. Inspiration: Pinterest, Etsy und der
Stil von Pottery by Denise (klare, alltagstaugliche Schablonen), aber nie nachmachen: eigene Formen,
eigene Illustrationen, eigene Texte. Keine niedlichen Tierfiguren, keine Kitsch-Motive.

## Geplant: Christmas for Kids (1.11.2026, 8 Uhr)

`kids-anhaenger` (Weihnachtsanhänger für Kinder) und `kids-3d-anhaenger` (3D-Anhänger für Kinder), je € 2,99,
Kategorie Weihnachten & Winter, Alter Kinder 7–10. Stehen schon in `api/seed/weekly.json` und gehen automatisch online.

## Plan Oktober 2026

| Freitag 8 Uhr | Template | Level | Saison |
| --- | --- | --- | --- |
| 16.10. | Wollknäuel-Schale (Yarn Bowl) mit geschwungenem Fadenschlitz | Mittel | herbst |
| 23.10. | Kerzen-Löschhütchen mit kleinem Griff | Einfach | herbst |
| 30.10. | Welliger Spiegelrahmen (runder Spiegel zum Einkleben) | Mittel | ganzjaehrig |

Preis jeweils € 3,99.

## Maker Club: Gratis-Template des Monats

Läuft am 20. jedes Monats (automatische Session) für den **nächsten** Monat:

1. Template laut Plan unten bauen (gleicher Stil und Aufbau wie oben, aber **Einfach**, ca. 30 Minuten,
   Fußzeile „<Titel> · FORMA Maker Club“, Rechte: „Gratis-Template für Mitglieder im FORMA Maker Club.
   Nur für den privaten Gebrauch, Weitergabe und Verkauf sind nicht erlaubt.“). Beispiel: `examples/build_star.py`.
2. Dateien nach `api/seed/maker/` (`<key>-de.pdf`, `<key>-en.pdf`, `<key>.jpg` 1200 × 900) und
   zusätzlich das Vorschaubild öffentlich als `img/maker-<monat-name>.jpg` (für den Newsletter).
3. In `api/seed/maker.json` Eintrag ergänzen:
   `{"key":"maker-tannen","month":"2026-12","title":"Stehende Ton-Tannen","text":"2 Sätze für die Website"}`
   Die Website stellt am 1. des Monats automatisch um.
4. Commit + Push auf `main`.
5. Newsletter in MailerLite (Connector) anlegen, im Stil der bisherigen Maker-Club-Mails
   (siehe `examples/maker-newsletter-vorlage.html`): Gruppe **Maker Club** (ID 200974300347368932),
   Absender hello@forma-pottery.com, Name „Vivien · FORMA Earth & Art“, Sprache ID 5.
   Button-Link immer `https://www.forma-pottery.com/api/maker-mail.php?e={$email}` (direkter Download).
   Inhalt: kurze, warme Einstimmung zur Jahreszeit, das Template, ein kleiner Tipp oder ein saisonales
   Rezept/DIY, **immer ein kurzer Teaser „Was gerade im Atelier ansteht“** (nächste Kurse, neue Angebote, Specials,
   mit Link zur Website), Hinweis auf den Shop, Signatur-Bild `img/signatur-liebe-zum-ton.png`.
6. Eine Kopie mit „[Test]“ im Betreff an die Gruppe **Test (nur Vivien)** (ID 201062038181512426) sofort senden,
   das Original für den 1. des Monats, 8:00 Uhr (Zeitzone-ID 370, Europe/Vienna) planen.
7. Vivien kurz Bescheid geben (Template, Vorschau, Mail geplant).

| Monat | Gratis-Template | key |
| --- | --- | --- |
| 2026-11 | Sternen-Anhänger (fertig, Mail geplant) | maker-sterne |
| 2026-12 | Stehende Ton-Tannen in drei Größen, aus der Platte gerollt | maker-tannen |
| 2027-01 | Teebeutel-Ablage, schlicht mit Wellenrand | maker-teebeutel |
| 2027-02 | Kleines Herz-Schild mit Stempel-Botschaft zum Hinstellen | maker-herzschild |
| 2027-03 | Osterei-Anhänger zum Bemalen (Ostern 28. 3. 2027) | maker-osterei |
| 2027-04 | Kräuterschilder für Balkon und Garten | maker-kraeuter |

Ab Mai 2027: selbst passende, einfache Ideen wählen (Scandi/Hygge, nicht auf der Ausschlussliste) und hier eintragen.

## Atelier-Newsletter „Neues aus dem Atelier“ (alle Abonnent:innen)

Wird in derselben Sitzung am 20. mit vorbereitet und für den **15. des nächsten Monats, 8:00 Uhr** geplant
(so kommen Maker-Mail am 1. und Atelier-Newsletter am 15. nie am selben Tag).

- Gruppe **Website** (ID 200959703678714898). Darin sind alle: Pop-up-Anmeldungen und auch alle Maker-Club-Mitglieder.
  Das Pop-up auf der Website ist die Anmeldung für diesen Newsletter, es bleibt so.
- Gleiches Design wie die Maker-Mails (Farben, Logo, Signatur), aber **kein Template und kein Download**.
- Inhalt: was gerade im Atelier los ist (kommende Kurse und Termine mit Buchungs-Link, neue Angebote, Specials,
  Gutscheine zur passenden Zeit), ein Foto-/Illustrationsbild, ein kleines saisonales Rezept oder Ritual
  (z. B. Rauch, Salz & Mondlicht, Räucherbündel, Wintertee), kurzer Hinweis auf den Maker Club (`/#templates`).
- Kurse und Termine aus `api/lib.php` (FORMA_SERIES, Migrationen) bzw. der Website ablesen, nichts erfinden.
- Eine „[Test]“-Kopie sofort an die Gruppe **Test (nur Vivien)** senden, das Original planen, Vivien Bescheid geben.

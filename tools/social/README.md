# FORMA Story-Paket (Instagram-Story + WhatsApp-Status)

Vivien möchte, dass in ihrer WhatsApp-Story immer etwas drin ist und sie für Instagram fertige Story-Bilder
mit Texten hat. Jeden Samstag früh kommt deshalb ein Paket für die kommende Woche (Sa–Fr):

- **7 WhatsApp-Status-Bilder** (1080 × 1920 PNG), eins pro Tag, Dateiname mit Wochentag und Datum.
- **4–6 Instagram-Story-Bilder** als kleine Geschichte (z. B. Hallo → Neues aus dem Atelier → Angebot → Link).
- **Instagram-Karussell** (4 Fotos 1080 × 1350, ohne Text) mit Caption.
- **Texte.txt**: Story-Sticker (Umfrage, Link-Sticker mit URL), Karussell-Caption mit Hashtags,
  WhatsApp-Text pro Tag.
- Alles zusätzlich als **ZIP**, damit Vivien es auf einmal aufs iPhone speichern kann.

## Bauen
`python3 tools/social/make_story.py <stories.json> <ausgabeordner>`

Look seit 16.10.2026 (Vivien: „cuter, more eye-catchy, so richtig BAM“): Scrapbook-Stil mit schrägem Polaroid
und Washi-Tape, Zacken-Sticker, große Überschrift plus handgeschriebenes Akzentwort (Homemade Apple),
Herzchen/Sterne, gekritzelter Pfeil zum Link-Sticker. Hintergründe wechseln ab: `rot` / `creme` / `ton`.

Felder: `file`, `theme`, `photo` (oder `house: true` für das Adventkalender-Haus), `pos`, `note`
(Polaroid-Unterschrift, kurz), `hand` (kleiner Einstieg oben links), `burst` („NEU|im Atelier“, oben kurz),
`title` (`|` = Umbruch, max. ~16 Zeichen pro Zeile), `script` (max. ~16 Zeichen), `text` (1–2 kurze Sätze),
`cta`. Beispiel-Woche: 17.–23. 10. 2026 (Tassen, Pottery bemalen ab 19. 10., Adventkalender, Spin & Go,
Gutschein, Pottery Pause). Seit 18. 10. ist die Pottery Pause im Herbst ausgebucht: nur noch als Teaser
für Frühjahr 2027 (4 Plätze, FORMA Post abonnieren), nicht mehr als buchbar bewerben.

## Inhalt
- Was in der Woche wirklich ansteht: aus `index.html`, `api/lib.php`, `api/seed/*.json` und
  `tools/pinterest/log.json` ablesen. Nichts erfinden, keine Platzzahlen nennen. Preise und Details
  immer genau wie gerade auf der Website, nie aus älteren Storys, Mails oder Flyern übernehmen
  (Abschnitt „Werbematerial“ in `CLAUDE.md`).
- Mischung: Einblick ins Atelier (Fotos), ein Angebot pro Tag, ab und zu FORMA Post / Maker Club / Gutscheine.
- Fotos abwechseln, farblich zusammenpassend (Website-Fotos aus `img/`). Nie: `vivien-toepfern.jpg`,
  `drehen.jpg`, `vivien.jpg`, `vivien-mit-tochter.jpg`. Spiegel-Selfies `vivien-spiegel*.jpg` sind erlaubt.
- Wortwahl: nie „Keramik“, nie Brennen/Glasieren (stattdessen „Fertigstellung“). Warm, kurz, Du-Form.
- Jedes Bild vor dem Versand ansehen (Text nicht abgeschnitten, nichts überlappt).

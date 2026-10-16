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
`python3 tools/social/make_story.py <stories.json> <ausgabeordner>` (Felder: `file`, `photo`, `pos`, `pill`,
`title` mit `|` für Zeilenumbruch, `text`, `cta`, optional `seal` {ring, big, small}, `house: true` für das
Adventkalender-Haus). Beispiel-Woche: 17.–23. 10. 2026 (Tassen, Pottery bemalen ab 19. 10., Adventkalender,
Spin & Go, Gutschein, Pottery Pause).

## Inhalt
- Was in der Woche wirklich ansteht: aus `index.html`, `api/lib.php`, `api/seed/*.json` und
  `tools/pinterest/log.json` ablesen. Nichts erfinden, keine Platzzahlen nennen.
- Mischung: Einblick ins Atelier (Fotos), ein Angebot pro Tag, ab und zu FORMA Post / Maker Club / Gutscheine.
- Fotos abwechseln, farblich zusammenpassend (Website-Fotos aus `img/`). Nie: `vivien-toepfern.jpg`,
  `drehen.jpg`, `vivien.jpg`, `vivien-mit-tochter.jpg`. Spiegel-Selfies `vivien-spiegel*.jpg` sind erlaubt.
- Wortwahl: nie „Keramik“, nie Brennen/Glasieren (stattdessen „Fertigstellung“). Warm, kurz, Du-Form.
- Jedes Bild vor dem Versand ansehen (Text nicht abgeschnitten, nichts überlappt).

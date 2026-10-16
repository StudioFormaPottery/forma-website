# FORMA auf Pinterest: 3 Pins pro Woche

Vivien möchte, dass Claude für FORMA **dreimal pro Woche** einen Pin einstellt: einmal vormittags,
einmal mittags, einmal abends (geplante Aufgaben Di vormittags, Do mittags, So abends).
Immer mit den **neuesten Infos** (aktuelle Kurse, Specials, Events, Newsletter, Shop, Gutscheine)
und **immer mit einem anderen Foto**. Aus jeder neuen Werbung (Instagram/WhatsApp-Bild, Flyer)
werden außerdem sofort Pins gemacht.

Konto: pinterest.at/formapottery (Business). Pinnwände: „Töpferkurse im Paznaun“ (Kurse, Specials,
ID 1130333275167605357), „Pottery Templates & DIY“ (Adventkalender, Templates, Maker Club),
„Teambuilding & Events“, „Geschenkideen aus Ton“ (Gutscheine).

## Ablauf pro Lauf

1. **Was ist gerade aktuell?** `index.html` (Angebote, Events, Shop), `api/lib.php` und
   `api/seed/*.json` lesen. Nur echte, aktuelle Angebote verwenden, nichts erfinden.
   Abgelaufenes weglassen (z. B. Pottery Pause nach dem 4. 12. 2026).
2. **Thema wählen** mit `log.json`: nicht dasselbe Thema wie in den letzten 3 Pins.
   Themen und Links:
   | Thema | Link |
   |---|---|
   | Pottery Pause (5 Freitagabende, 6. 11. – 4. 12. 2026, € 295) | https://www.forma-pottery.com/#pottery-pause |
   | Pottery bemalen im Advent (ab Nov., Mi & Do 15–18 Uhr) | https://www.forma-pottery.com/#pottery-bemalen |
   | FORMA Adventkalender (24 Templates, bald im Shop, ohne Preis) | https://www.forma-pottery.com/#adventkalender |
   | Spin & Go (Mi & Fr 9–11 Uhr, € 70 inkl. Werkstück) | https://www.forma-pottery.com/#kurs-spin |
   | Private Pottery (Handbuilding, € 65/Std., inkl. Ton und Fertigstellung bis 5 kg) | https://www.forma-pottery.com/#kurs-private |
   | Gutscheine | https://www.forma-pottery.com/#gutschein-kaufen |
   | Teambuilding (4–8 Personen) | https://www.forma-pottery.com/team/ |
   | FORMA Post (Newsletter) | https://www.forma-pottery.com/#forma-post |
   | Maker Club (Gratis-Template jeden Monat) | https://www.forma-pottery.com/#maker-club |
   | Template-Shop (nur wenn geöffnet) | https://www.forma-pottery.com/#shop |
3. **Foto wählen**: aus `img/` ein Foto, das in `log.json` noch nicht (oder am längsten nicht)
   verwendet wurde. Ansehen, bevor es genommen wird.
   **Nie verwenden:** `vivien-toepfern.jpg`, `drehen.jpg`, `vivien.jpg`, `vivien-mit-tochter.jpg`
   (Vivien ist darauf zu sehen) und das Brennofen-Foto. `make_pin.py` blockiert diese Dateien.
4. **Pin bauen**: kleine JSON-Datei schreiben und `python3 tools/pinterest/make_pin.py pin.json`
   ausführen (im Repo-Ordner). Felder:
   `file` (z. B. `img/pins/2026-10-20-spin-and-go.jpg`), `photo`, `photo_pos`, optional `seal`
   (`ring`, `big`, `small`), `house` (true = Lebkuchenhaus), `pill`, `title` (`|` = Zeilenumbruch),
   `title_size`, `sub`, `text`, `chips` (Liste), `cta`. Ergebnis ansehen und korrigieren.
   Texte warm und einladend, Wortwahl wie auf der Website: nie „Keramik“, nie Brennen/Glasieren,
   stattdessen „Fertigstellung“.
5. **Veröffentlichen**: Bild committen und auf `main` pushen, ca. 1–2 Minuten warten, bis
   `https://www.forma-pottery.com/img/pins/<datei>` erreichbar ist.
6. **Pin anlegen** in **Chrome** auf Viviens Computer (Claude in Chrome; dort ist sie bei Pinterest
   angemeldet, im Browser der Claude-App nicht; nie Passwörter eingeben). So hat es am 16. 10. 2026 geklappt:
   - Pin-Builder öffnen: `https://www.pinterest.at/pin/create/button/?url=<Link>&media=<Bild-URL>&description=<Text>`
     (alles URL-codiert). Es erscheint die Pinnwand-Liste; die Reihenfolge wechselt, darum per Bildschirmfoto
     prüfen, über die richtige Pinnwand fahren (hover) und auf deren roten Knopf „Merken“ klicken.
   - „Gemerkt auf …“ bestätigt das Speichern. Die Pin-ID steht danach oben unter
     `https://www.pinterest.at/formapottery/_created/` (neuester zuerst).
   - Der Pin-Builder setzt keinen Titel: Pin-Seite öffnen → „Mehr Aktionen“ → „Pin bearbeiten“ →
     Felder „Titel“ und „Alternativtext“ ausfüllen → „Merken“. Nie auf „Löschen“ klicken.
   Titel max. 100 Zeichen, Beschreibung 2–4 Sätze mit Suchbegriffen wie Töpferkurs, Töpfern,
   Pottery, Paznaun, Ischgl, Tirol, Advent, DIY, plus 3–5 Hashtags.
7. **Logbuch**: Eintrag in `log.json` ergänzen (Datum, Thema, Foto, Bild, Link, Board, Pin-URL),
   committen und pushen.
8. Vivien kurz auf Deutsch Bescheid geben (Thema, Bild, Link zum Pin). Klappt etwas nicht
   (Computer aus, nicht angemeldet), klar sagen, was fehlt, und den fertigen Pin als Bild mitschicken.

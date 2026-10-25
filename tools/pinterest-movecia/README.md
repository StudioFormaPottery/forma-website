# Movecia auf Pinterest: 3 Pins pro Woche (Vivien, 25.10.2026)

Vivien will maximale Sichtbarkeit, damit Frauen Method, Unfold und Prä-/Postnatal buchen.
Geplante Aufgaben: Montag, Mittwoch, Freitag. Profil: verknüpftes Unternehmensprofil „Movecia · Mindful Movement“
unter Viviens FORMA-Pinterest-Login (oben rechts Pfeil neben dem Profilbild → zum Movecia-Profil wechseln).
Gibt es das Profil noch nicht: nichts anlegen (Konten anlegen ist verboten), Vivien kurz Bescheid geben und den fertigen Pin als Bild schicken.

Pinnwände (beim ersten Lauf anlegen, falls sie fehlen): Mindful Movement für zu Hause · Breathwork & Atemübungen ·
Yoga für müde Tage · Mental Load & Selbstfürsorge für Mamas · Schwangerschaft & Rückbildung · Journaling-Fragen.

## Ablauf
1. `log.json` lesen: nicht dasselbe Thema wie in den letzten 3 Pins, kein Foto/Typ doppelt hintereinander.
2. Thema wählen. Mischung: etwa jeder dritte Pin ein Angebot, die anderen schenken Wert (kurze Übung, Atemübung,
   Journaling-Frage, Gedanke) und verlinken trotzdem auf die passende Seite. Ideen gern aus `tools/movecia-week/*.json`.
   | Thema | Link | Pinnwand |
   |---|---|---|
   | Mindful Movement Method (Start 1.12.2026, Early-Bird bis 1.12. € 29 statt € 39, danach € 39) | https://moveciamovement.com/method/ | Mindful Movement für zu Hause |
   | Unfold (Start 10.1.2027, 10 Plätze, Early-Bird bis 11.1. € 450 statt € 490) | https://moveciamovement.com/unfold/ | Mental Load & Selbstfürsorge für Mamas |
   | Prä- & Postnatal Training (Studio Mathon oder online, kostenloses Kennenlerngespräch) | https://moveciamovement.com/natal/ | Schwangerschaft & Rückbildung |
   | Breathwork-Übung | https://moveciamovement.com/method/ | Breathwork & Atemübungen |
   | Yoga/Somatic-Übung für müde Tage | https://moveciamovement.com/method/ | Yoga für müde Tage |
   | Journaling-Frage | https://moveciamovement.com/unfold/ | Journaling-Fragen |
   Preise immer live aus `api/method-lib.php` (`mv_cfg_raw`), abgelaufene Early-Birds nie nennen, Gathering-Preise nie.
   Nur echte Übungen mit gängigen Namen (Katze-Kuh, Kindhaltung, Glute Bridge, Box Breathing …).
3. Pin bauen: JSON wie ein Wochen-Post plus `file` = `img/pins/movecia/<datum>-<thema>.jpg`, lokalen Server starten
   (`FORMA_DATA_DIR=/tmp/x php -S 127.0.0.1:8765`), `python3 tools/pinterest-movecia/make_pin.py pin.json`, Bild ansehen.
   Fotos nur aus `img/movecia/fotos/` (art-*.jpg bevorzugt). Für natal: `schwanger.jpg`/`art-schwanger.jpg`.
4. Bild committen, auf `main` pushen, warten bis `https://moveciamovement.com/img/pins/movecia/<datei>` erreichbar ist.
5. In **Chrome** (Claude in Chrome) zum Movecia-Profil wechseln und den Pin anlegen, wie in `tools/pinterest/README.md`
   Schritt 6 beschrieben (im Pin-JSON `"btn": "Mehr erfahren"` setzen; Pin-Builder-URL, richtige Pinnwand, danach Titel setzen). Titel mit Suchbegriffen
   (z. B. „Box Breathing: 3 Minuten gegen Stress | Atemübung für Frauen“), Beschreibung 2–3 Sätze + 3–5 Hashtags.
   Nie Passwörter eingeben, sonst nichts am Konto ändern.
6. `log.json` ergänzen (datum, thema, foto, datei, pinnwand, link, status, pin_url), committen, pushen.
7. Vivien kurz Bescheid geben: Thema, Pin-Bild, Link.

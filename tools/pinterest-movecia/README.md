# Movecia auf Pinterest: 3 Pins pro Woche (Vivien, 25.10.2026)

Vivien will maximale Sichtbarkeit, damit Frauen Method, Unfold und Prä-/Postnatal buchen.
Geplante Aufgaben: Montag, Mittwoch, Freitag. Profil (seit 28.10.2026): „Movecia · Mindful Movement“, pinterest.at/moveciamovement
(hello@moveciamovement.com), verknüpft mit FORMA und Frida & Knox: in Chrome oben rechts Pfeil neben dem Profilbild → Movecia wählen.
Profil eingerichtet (Profilbild `img/movecia/movecia-profilbild-1080.png`, Kurzinfo, Website, Impressum-Link /terms/), alle 6 Pinnwände angelegt, dazu „Mindful Movement Teacher Training“ (Vivien, 28.10.2026: Teaser posten, Start November 2027, Ausbildung in Yoga, Somatic Movement und Bodyweight, insgesamt 550 Stunden – beim Teacher Training NIE „Pilates“ nennen; Link auf moveciamovement.com/teacher-training/ mit Warteliste).
Ist Movecia nicht im Menü: nichts anlegen, Vivien Bescheid geben und den fertigen Pin als Bild schicken.

Veröffentlichen am einfachsten (seit 28.10.2026): https://at.pinterest.com/pin-creation-tool/ → Datei-Upload mit dem Pin aus dem Repo
(file_upload, kein Warten auf die Live-Seite nötig) → Titel, Beschreibung, Link, Pinnwand, unter „Weitere Optionen“ Alt-Text → „Veröffentlichen“.
Fotos: Hochformat-Fotos 640 px werden im breiten Bogen stark vergrößert und unscharf; besser große Fotos (ev-ueber-mich, pt-*, ev-start*, ev-method, ev-natal-sitzend) nehmen und Person ganz sichtbar, eher klein im Raum zeigen (Vivien, 28.10.2026: „weiter nach hinten“). Für Hochformat-Fotos gibt es Breitfassungen mit sanft verlängerten Rändern, z. B. `ev-ueber-mich-weit.jpg`, `pos` dann um 50% 60%. In der Chrome-Leiste vor jedem Pin prüfen, dass oben „Movecia · Mindful Movement“ aktiv ist (der Login wechselt manchmal auf Frida & Knox).
Alte/ersetzte Pins nicht löschen (nur Vivien), sondern per „Pin bearbeiten“ in die private Pinnwand „Archiv“ verschieben.
Pin-URL: neuester Pin unter https://at.pinterest.com/moveciamovement/_created/.
Nach „Pinnwand erstellen“ kommt ein Fenster mit Pin-Vorschlägen: dort NICHTS anklicken außer „Fertig“, sonst wird ein fremder Pin gemerkt.

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
   | The Mindful Becoming Teacher Training (Start 3.11.2027, 550 Std., buchbar; Early-Bird € 3.890 statt € 4.900 bis 31.3.2027, max. 14 Plätze; nie Pilates; Preis im Kennenlerngespräch nie nennen) | https://moveciamovement.com/academy/teacher-training/ | The Mindful Becoming Teacher Training |
   Preise immer live aus `api/method-lib.php` (`mv_cfg_raw`), abgelaufene Early-Birds nie nennen, Gathering-Preise nie.
   Nur echte Übungen mit gängigen Namen (Katze-Kuh, Kindhaltung, Glute Bridge, Box Breathing …).
3. Pin bauen (Look automatisch wie Website und Wochenbilder, siehe `tools/movecia-week/README.md` „Look“): JSON wie ein Wochen-Post plus `file` = `img/pins/movecia/<datum>-<thema>.jpg`, lokalen Server starten
   (`FORMA_DATA_DIR=/tmp/x php -S 127.0.0.1:8765`), `python3 tools/pinterest-movecia/make_pin.py pin.json`, Bild ansehen.
   Fotos (Vivien, 27.10.2026): IMMER die neuen Bilder `img/movecia/fotos/ev-*.jpg` (heller Studio-Look mit Bergblick),
   nie mehr art-*.jpg, fold.jpg, reach.jpg. Für natal: `ev-natal.jpg`/`ev-natal-sitzend.jpg`. Bevorzugt Bilder, auf denen
   das Gesicht nicht erkennbar ist.
   Neue Yoga-Bilder (27.10.2026, Everygen, Gesicht nicht erkennbar) – noch ins Repo holen, sobald media.viewmax.io erreichbar ist
   (auf 1080 bzw. max. 1400 px Breite, als `ev-pin-kamel.jpg`, `ev-pin-baum.jpg`, `ev-pin-kind.jpg`):
   Kamel (kniend, von hinten): https://media.viewmax.io/users/wLzJXedAfqnV6720mvoVrSCNPAyriMzN/generated/ac6a961f-5def-40c9-b7ff-bfb234a77055.jpg
   Baum (von hinten vor dem Fenster): https://media.viewmax.io/users/wLzJXedAfqnV6720mvoVrSCNPAyriMzN/generated/a035d17e-0b0e-4aeb-9e8e-e9898860ae39.jpg
   Kindhaltung (mit Decke und Tee): https://media.viewmax.io/users/wLzJXedAfqnV6720mvoVrSCNPAyriMzN/generated/59e65d5e-9fac-476c-8033-f4762a57157b.jpg
4. Bild committen, auf `main` pushen, warten bis `https://moveciamovement.com/img/pins/movecia/<datei>` erreichbar ist.
5. In **Chrome** (Claude in Chrome) zum Movecia-Profil wechseln und den Pin anlegen, wie in `tools/pinterest/README.md`
   Schritt 6 beschrieben (im Pin-JSON `"btn": "Mehr erfahren"` setzen; Pin-Builder-URL, richtige Pinnwand, danach Titel setzen). Titel mit Suchbegriffen
   (z. B. „Box Breathing: 3 Minuten gegen Stress | Atemübung für Frauen“), Beschreibung 2–3 Sätze + 3–5 Hashtags.
   Nie Passwörter eingeben, sonst nichts am Konto ändern.
6. `log.json` ergänzen (datum, thema, foto, datei, pinnwand, link, status, pin_url), committen, pushen.
7. Vivien kurz Bescheid geben: Thema, Pin-Bild, Link.

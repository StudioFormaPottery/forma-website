# FORMA Website – Arbeitsnotizen

Website des FORMA Töpferstudios (Vivien Malonga) unter www.forma-pottery.com.

## Veröffentlichung
- Hosting: Hostinger, Webhosting-Paket. Hostinger übernimmt den Branch `main` automatisch nach `public_html` (Auto-Deployment).
- Jede Änderung, die auf `main` gepusht wird, ist wenige Minuten später live. Es gibt keinen Build-Schritt.
- Größere Änderungen erst als Entwurf zeigen, Kleinigkeiten (Termine, Tippfehler) direkt pushen, wenn Vivien das möchte.

## Aufbau
- `index.html`: die gesamte Seite als Onepager mit Inline-CSS. Bewusst nur ein heller Look, kein Dark Mode.
- `fonts/`: selbst gehostete Schriften (Bricolage Grotesque, Italiana, Outfit; SIL OFL). Kein Google Fonts, damit die Datenschutzerklärung stimmt.
- `img/`: Fotos. Alle mit dem warmen, erdigen Look bearbeitet (wärmer, etwas mehr Kontrast, leichter Sepia-Anteil, feines Korn), max. 1400 px.
- `video/`: Studio-Videos als H.264-MP4, 720 px breit, ohne Ton, mit Poster-Bild in `img/`.

## Design
- Farben: Sand `#EEE2D4`, Hafer `#E4D2BF`, Ton `#DDBFA8`, FORMA-Rot `#8C2C1D`, Terracotta `#B05A38`, Text `#33211B`.
- Überschriften in Bricolage Grotesque, kräftig, aber nicht zu fett. Vivien mag keine sehr fetten Schriften.
- Wellen-Übergänge zwischen den Bereichen, schwebende Tonformen im Kopfbereich.
- Das Brennofen-Foto (Keramik im Kreis auf dem Ofen) nicht verwenden.

## Inhalte, die sich ändern
- Platzhalter (`<span class="placeholder">`) stehen noch bei den Zeiten für Spin & Go und das offene Atelier.
- Pottery-Pause-Buchung läuft über Zenamu: https://app.zenamu.com/events/2561ae37441453905e1030eca840ef25
- Wenn neue Dienste dazukommen (Newsletter, Formulare, eingebettete Inhalte), die Datenschutzerklärung im Footer anpassen.

## Nicht anfassen (DNS bei Hostinger)
- E-Mail-Einträge (MX, hostingermail, autodiscover, autoconfig, _dmarc, SPF) und MailerLite-Einträge bleiben unverändert.

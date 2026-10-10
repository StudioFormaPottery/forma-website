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

## Eigenes Buchungssystem (seit 10.10.2026, ersetzt Zenamu)
- `api/courses.php` liefert die kommenden, sichtbaren Termine mit freien Plätzen; `api/book.php` nimmt Anmeldungen an (Platzprüfung in einer Transaktion, Honeypot-Feld `website`, Mindestzeit 3 s, max. 6 Anmeldungen pro Stunde und IP-Hash).
- `api/lib.php`: gemeinsame Funktionen, Absender/Empfänger der Mails, Startinhalt (Pottery Pause mit 1 Platz).
- Daten: SQLite unter `../forma-data/buchungen.sqlite`, also außerhalb von `public_html`. Bleibt bei Deployments erhalten. Nie ins Repository legen.
- Mails über PHP `mail()` von hello@forma-pottery.com: Bestätigung an die Kundin, Benachrichtigung an hello@forma-pottery.com.
- `verwaltung/index.php`: Viviens Kursverwaltung. Erstes Passwort und „Passwort vergessen“ laufen über einen Link per Mail an hello@forma-pottery.com (1 Stunde gültig). Termine anlegen/bearbeiten/ausblenden/löschen, Anmeldungen sehen, stornieren, Personen von Hand eintragen.
- Kurse werden nur in der Verwaltung gepflegt, nicht im HTML. Die Angebotskarten in `index.html` beschreiben die Formate, der Bereich `#anmeldung` lädt die echten Termine.
- Lokal testen: im Repo-Ordner `FORMA_DATA_DIR=/tmp/x FORMA_MAIL_LOG=/tmp/mail.log php -S 127.0.0.1:8765`.
- `.htaccess` sperrt `.md`, `.sqlite` und `api/lib.php` für Web-Zugriffe.

## Inhalte, die sich ändern
- Platzhalter (`<span class="placeholder">`) stehen noch bei den Zeiten für Spin & Go und das offene Atelier.
- Wenn neue Dienste dazukommen (Newsletter, Formulare, eingebettete Inhalte), die Datenschutzerklärung im Footer anpassen.

## Nicht anfassen (DNS bei Hostinger)
- E-Mail-Einträge (MX, hostingermail, autodiscover, autoconfig, _dmarc, SPF) und MailerLite-Einträge bleiben unverändert.

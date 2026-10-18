# FORMA Website – Arbeitsnotizen

Website des FORMA Töpferstudios (Vivien Malonga) unter www.forma-pottery.com.

## Veröffentlichung
- Hosting: Hostinger, Webhosting-Paket. Hostinger übernimmt den Branch `main` automatisch nach `public_html` (Auto-Deployment).
- Jede Änderung, die auf `main` gepusht wird, ist wenige Minuten später live. Es gibt keinen Build-Schritt.
- Größere Änderungen erst als Entwurf zeigen, Kleinigkeiten (Termine, Tippfehler) direkt pushen, wenn Vivien das möchte.

## Aufbau
- `index.html`: die gesamte Seite als Onepager mit Inline-CSS. Bewusst nur ein heller Look, kein Dark Mode.
- `fonts/`: selbst gehostete Schriften (Bricolage Grotesque, Italiana, Outfit; SIL OFL). Kein Google Fonts, damit die Datenschutzerklärung stimmt.
- `img/`: Fotos im natürlichen, hellen Look wie das Über-mich-Bild (kein Sepia mehr!): nur Helligkeit und Wärme sanft an `vivien-toepfern.jpg` angeglichen, max. 1400 px. Videos genauso (leicht heller, minimal wärmer).
- `video/`: Studio-Videos als H.264-MP4, 720 px breit, ohne Ton, mit Poster-Bild in `img/`.
- Galerie `#galerie` im Studio-Bereich (seit 16.10.2026): zarter Wellenrahmen mit Herzchen/Sternchen, 6 Kacheln (Video `ergebnisse.mp4`, Tassen, Anhänger, 3D-Baum, Becher, Schalen). „Hallo!“-Foto: `vivien-spiegel.jpg` (Vivien hat die Spiegel-Selfies ausdrücklich für Website, Pinterest und Posts freigegeben).

## Design
- Farben: Sand `#EEE2D4`, Hafer `#E4D2BF`, Ton `#DDBFA8`, FORMA-Rot `#8C2C1D`, Terracotta `#B05A38`, Text `#33211B`.
- Überschriften in Bricolage Grotesque, kräftig, aber nicht zu fett. Vivien mag keine sehr fetten Schriften.
- Wellen-Übergänge zwischen den Bereichen, schwebende Tonformen im Kopfbereich.
- Das Brennofen-Foto (Keramik im Kreis auf dem Ofen) nicht verwenden.
- Studio-Bereich (16.10.2026): nur die Galerie „Frisch aus dem Atelier“, keine extra Videoreihe darüber. Im großen Bogen links wechseln die Videos (ergebnisse, krug, regal, details, stempel), in den anderen Feldern wechseln die Fotos ruhig (Liste `PICS` im Skript). Über mich bleibt `vivien-mit-tochter.jpg`, das Spiegel-Foto gehört in die Galerie.

## Wortwahl
- Nie „Keramik“, nie Brennen, Glasieren, Brennkosten o. Ä. auf der Website oder in Mails. Stattdessen „Fertigstellung“: „Für die Fertigstellung wird eine Gebühr von € 25 pro Kilo erhoben, abgerechnet bei Abholung deiner Werkstücke.“
- FORMA ist ein Workshop-Atelier: Man besucht aktiv einen Kurs und praktiziert das Handwerk selbst.

## Eigenes Buchungssystem (seit 10.10.2026, ersetzt Zenamu)
- `api/courses.php` liefert die kommenden, sichtbaren Termine mit freien Plätzen; `api/book.php` nimmt Anmeldungen an (Platzprüfung in einer Transaktion, Honeypot-Feld `website`, Mindestzeit 3 s, max. 6 Anmeldungen pro Stunde und IP-Hash).
- Restplätze sind nur intern (Verwaltung). Gäste sehen nie „noch X Plätze frei“, nur „Plätze frei“ oder „ausgebucht“. Die Personen-Auswahl geht aber nur bis zur Zahl freier Plätze (1 frei → nur 1 wählbar), damit Gäste sofort einen anderen Termin nehmen. Beim Offenen Atelier sind Stunden gesperrt, in denen nicht genug Plätze für die gewählte Personenzahl frei sind. Gilt für Startseite, /urlaub/, /links/ und Umbuchen.
- Plätze pro Kurs (Vivien, 16.10.2026): Spin & Go 4 (Restplatz trägt sie bei Bedarf selbst ein), Private Pottery 1, Offenes Atelier 5 pro Stunde (5 Scheiben), Pottery bemalen 6. Pottery Pause wie in der Verwaltung.
- `api/lib.php`: gemeinsame Funktionen, Absender/Empfänger der Mails, Startinhalt (Pottery Pause mit 1 Platz).
- Daten: SQLite unter `../forma-data/buchungen.sqlite`, also außerhalb von `public_html`. Bleibt bei Deployments erhalten. Nie ins Repository legen.
- Mails: Bestätigung an die Kundin, Benachrichtigung an hello@forma-pottery.com. Versand per SMTP über das Hostinger-Postfach hello@forma-pottery.com (ssl://smtp.hostinger.com:465), sobald Vivien in der Verwaltung unter „E-Mail-Versand“ das Postfach-Passwort hinterlegt hat (liegt in `../forma-data/mail.json`, nie ins Repo). Ohne Passwort Rückfall auf PHP `mail()`, das oft im Spam landet.
- SMTP lokal testen: `FORMA_SMTP_HOST=tcp://127.0.0.1:2525` mit einem Test-Mailserver.
- `verwaltung/index.php`: Viviens Kursverwaltung. Erstes Passwort und „Passwort vergessen“ laufen über einen Link per Mail an hello@forma-pottery.com (1 Stunde gültig). Termine anlegen/bearbeiten/ausblenden/löschen, Anmeldungen sehen, stornieren, Personen von Hand eintragen.
- Kurse werden nur in der Verwaltung gepflegt, nicht im HTML. Die Angebotskarten in `index.html` beschreiben die Formate, der Bereich `#anmeldung` lädt die echten Termine.
- Lokal testen: im Repo-Ordner `FORMA_DATA_DIR=/tmp/x FORMA_MAIL_LOG=/tmp/mail.log php -S 127.0.0.1:8765`.
- `.htaccess` sperrt `.md`, `.sqlite` und `api/lib.php` für Web-Zugriffe.

## Zeiten (Stand Oktober 2026)
- Öffnungszeiten Studio: Mo–Fr 8–10 Uhr.
- Spin & Go: Mi + Fr 9–11 Uhr, findet ab 2 Personen statt, € 70 p. P., online bezahlt.
- Offenes Atelier: Do 8–11 Uhr, € 30/Std., Brennen & Glasieren € 22/kg, Bezahlung vor Ort.
- Pottery Pause: freitags 18–20 Uhr, 5 Termine, € 295, online bezahlt.
- Private Pottery: nach Vereinbarung, € 65/Std.
- Diese Zeiten stehen in `index.html` an drei Stellen: Kopfbereich (`.hero-week`), Angebotskarten, Kontakt (`.week-plan`).
- Farben: nur Sand/Ton, FORMA-Rot und Braun. Keine dritte Akzentfarbe (Vivien mag das Orange nicht), `--clay-soft` ist deshalb Braun.
- Buchen: Jeder Knopf mit `data-book="Kursname"` öffnet direkt das Buchungsfenster (Schritt 1 Kurs, 2 Kalender, 3 Angaben), ohne zu scrollen. Keine langen Terminlisten.
- Menü (Seitenleiste über `#nav-toggle`, seit 14.10.2026): oben FORMA Post, dann Kurse ▸ (Spin & Go `#kurs-spin`, Private Pottery `#kurs-private`, Pottery Pause `#pottery-pause`, Events) · Teambuilding · Gutscheine · Shop & Maker Club · Hallo! · Infos ▸ (FAQ, Gut zu wissen, Kontakt). „Kurs buchen“ nur oben in der Leiste.
- Pottery Pause ist eine eigene Special-Karte (`.pp-special`) über dem Kursraster, nicht Teil davon. Teambuilding-Karte hell (Ton), FORMA-Rot nur in Kreisen/Akzenten.
- Maker-Post-Mails: Template-Bild max. 260 px breit und mittig (Vorlage `tools/templates/examples/maker-newsletter-vorlage.html`), sonst wirkt es riesig.
- Saisonale Deko: kleine SVG-Icons (Herbst, Winter, Frühling, Sommer), automatisch nach Monat oder in der Verwaltung unter „Pop-up & Deko“ festlegbar (`api/deko.php`, Setting `deko`). Vorschau mit `/?deko=winter`.

## Online-Bezahlung (Stripe Checkout)
- Pro Termin `price_cents` > 0 und Stripe-Schlüssel hinterlegt → `api/book.php` legt die Buchung als `offen` an, erstellt eine Checkout-Session und leitet weiter. Plätze bleiben 40 Min. reserviert (`FORMA_HOLD_SECONDS`).
- `api/paid.php` (success_url) und `api/cancel.php` (cancel_url) prüfen ein Token pro Buchung. Bestätigt wird nur, wenn Stripe `payment_status = paid` meldet. `forma_reconcile_payments()` gleicht offene Zahlungen bei jedem Laden der Termine nach (falls jemand das Fenster nach der Zahlung schließt). Mails gehen genau einmal raus (`bookings.mailed`).
- Vivien trägt den geheimen Schlüssel selbst in der Verwaltung unter Einstellungen ein (`../forma-data/stripe.json`). Nie im Chat oder Repo. Testmodus zuerst (Testkarte 4242 4242 4242 4242).
- Lokal testen: `FORMA_STRIPE_API=http://127.0.0.1:12111` mit einem Fake-Server, `FORMA_SITE_URL=http://127.0.0.1:8765`.

## Serientermine
- Verwaltung → „+ Serientermine“: Vorlagen Spin & Go und Offenes Atelier (`FORMA_SERIES` in lib.php). Die Migration `seeded_v2` hat Mi/Fr Spin & Go und Do Atelier bis 31.12.2026 angelegt (je 4 Plätze).

## Saison-Pop-up und E-Mail-Liste
- Verwaltung → „Saison-Pop-up“: Titel, Text, Zeitraum, Bild (liegt in `../forma-data/popup.*`, ausgeliefert über `api/popup-image.php`). Vorschau: `/?popup=vorschau`. Erscheint einmal pro Browser-Sitzung (sessionStorage).
- Eintragung über `api/subscribe.php` mit Double-Opt-in (`api/confirm.php`, Abmeldung `api/unsubscribe.php`). Tabelle `subscribers`. CSV-Export in der Verwaltung.
- MailerLite: Vivien trägt einen API-Schlüssel unter Einstellungen ein (`../forma-data/mailerlite.json`). Bestätigte Adressen gehen dann automatisch in die MailerLite-Gruppe „Website“.

## Inhalte, die sich ändern
- Wenn neue Dienste dazukommen (Newsletter, Formulare, eingebettete Inhalte), die Datenschutzerklärung im Footer anpassen.

## Nicht anfassen (DNS bei Hostinger)
- E-Mail-Einträge (MX, hostingermail, autodiscover, autoconfig, _dmarc, SPF) und MailerLite-Einträge bleiben unverändert.

## Gutscheine (seit 10.10.2026)
- Kauf über „Gutschein kaufen“ (`data-voucher`) → `api/gutschein.php` (Stripe Checkout) → `api/gutschein-bezahlt.php` vergibt die fortlaufende Nummer FORMA-JJJJ-0001 und schickt die Mails. PDF: `api/gutschein-pdf.php?v=ID&k=TOKEN` (FPDF in `api/fpdf/`, Hintergründe und Textpositionen in `api/gutschein/design1|2.*`, erzeugt per Playwright aus HTML-Vorlagen).
- Produkte und Preise in `FORMA_VOUCHERS` (lib.php): Spin & Go € 70 pro Platz, Private Pottery € 65 pro Stunde, Offenes Atelier € 30 pro Stunde. Formulierung „2 Plätze im Spin & Go Workshop“, nicht „Einheiten“.
- Zwei Designs zur Wahl („Bogen“, „Freiformen“), weißes Papier, keine Fotos. Kein Ablaufdatum auf dem Gutschein.
- Verwaltung → Gutscheine: Liste, als eingelöst markieren, CSV für die Buchhaltung.

## Private Pottery (seit 10.10.2026)
- „Projekt buchen“ (`data-private`) öffnet ein Fenster: Stunden 1–4 mit Projektbeispielen (1 Std. Magnete/2 Espressotassen, 2 Std. Tassen, Espresso-Set, Teller, große Schale, 3–4 Std. Salatschüssel, Service), Projektbeschreibung, Wunschtermin. Direkt bezahlen (€ 65 × Stunden) oder „Erst mal anfragen“ ohne Zahlung. `api/private.php`, Tabelle `private_bookings`, Verwaltung → Private Pottery.

## Private Pottery verlängern (seit 16.10.2026)
- Online buchbar sind nur 1–4 Stunden (`api/private.php`). Mehr Stunden schaltet Vivien in der Verwaltung frei: bei jeder bezahlten Buchung (Liste Private Pottery und Kalender-Karte) „+ Stunden verlängern“ (0,5–8 Std.).
- Tabelle `private_extensions` (offen / bezahlt / zurueckgezogen). Gast bekommt Mail mit Link `api/private-plus.php?e=ID&k=TOKEN` → Stripe (Betrag = Stunden × FORMA_PRIVATE_HOUR, am Server). Erst nach Zahlung werden Stunden und Betrag der Buchung gutgeschrieben (`forma_private_plus_check`), dann lassen sie sich im Kalender einplanen.
- Nach Zahlung: Danke-Mail, Info an hello@, eigene Rechnung (Rechnungsart `private_plus`). Erinnerung erneut schicken oder zurückziehen geht in der Verwaltung.
- Bewertungs-Mail wartet, solange eine Verlängerung offen ist oder bezahlte Stunden noch nicht eingeplant sind.

## Templates & FORMA Maker Club (seit 10.10.2026)
- Bereich `#templates` (Menü „Templates“): oben der Maker Club (Gratis-Template des Monats per E-Mail, Double-Opt-in über `api/subscribe.php` mit source „Maker Club“, Download `api/maker-download.php?t=TOKEN`, MailerLite-Gruppe „Maker Club“), darunter bezahlte Templates mit Filtern Jahreszeit/Alter.
- Kauf: `api/template-buy.php` (Stripe, Häkchen zum Verzicht auf das Rücktrittsrecht bei digitalen Inhalten), `api/template-bezahlt.php`, Download `api/template-download.php`. Tabellen `templates`, `template_orders`. Dateien in `../forma-data/templates/`.
- Verwaltung → Templates: Template des Monats hochladen, Templates anlegen (PDF, Vorschaubild, Preis, Jahreszeit, Alter, Schwierigkeit), Verkäufe + CSV.
- Templates immer Deutsch + Englisch, Anleitungen mit wenig Text und viel Bild.

## Link in Bio
- `links/index.html` = eigene Link-in-Bio-Seite für Instagram: www.forma-pottery.com/links
- Zieht automatisch: aktuelles Special (api/popup.php), nächsten freien Termin (api/courses.php), WhatsApp-Community-Link (api/deko.php)
- Direktlinks auf der Startseite: `#jetzt-buchen`, `#private-pottery`, `#gutschein-kaufen`, `#special` (öffnet Pop-up), `#shop`

## Rechnungen (seit 12.10.2026)
- `api/invoices.php`: nach jeder Online-Zahlung (Kurs, Private Pottery, Template) automatisch Rechnung mit eigenem Nummernkreis `FO-<Jahr>-001` (Kürzel in der Verwaltung), PDF mit FPDF (`api/vendor/fpdf`, Schriften Outfit/Bricolage), Mail mit PDF an Kundin und an hello@.
- Steuer: Kurse immer AT 20 %; Templates EU → AT 20 % solange nicht OSS-registriert (Schwelle 10.000 €), mit Häkchen „OSS“ der Satz des Wohnsitzlandes (`FORMA_EU_VAT`); Drittland → 0 % mit Hinweis.
- Google Tabelle: optional per Google-Dienstkonto (JSON-Schlüssel in der Verwaltung hochladen, liegt nur im Datenordner) → trägt in „Rechnungen“ und „Kurse & Leistungen“ ein.
- Gutscheine bekommen keine Rechnung (Mehrzweckgutschein, USt. erst bei Einlösung).

## Hotelseite /urlaub/ (Monatskalender seit 14.10.2026)
- Spin & Go als Monatskalender (`.mcal` in `assets/gast.css`), Daten serverseitig aus `forma_upcoming_courses`, ausgebuchte Tage gestrichelt.
- „Platz buchen“ führt zu `/?termin=ID`: die Startseite öffnet dann direkt das Buchungsformular für genau diesen Termin.

## Alte Gutscheine einlösen (seit 16.10.2026)
- Für Papier-Gutscheine, die schon bezahlt sind, aber keine Nummer haben: Verwaltung → Kalender → Tag antippen → „Gast mit altem Gutschein eintragen“ (auch Knopf unter Gutscheine). Spin & Go am Tag wählen oder neuen Termin anlegen (standardmäßig nicht auf der Website).
- `forma_old_voucher_booking()` in lib.php: legt den Gutschein nach (`kind = 'alt'`, Nummer `ALT-JJJJ-001` oder die vom Papier mit Präfix ALT-), bucht den Platz als bestätigt (amount_cents 0, voucher_cents), löst ein, schickt Bestätigung + Kalender + Rechnung. Bewertungs-Mail und FORMA Post laufen wie bei jeder Buchung. Restwert → Code steht in der Mail.
- Rechnung: Leistung wird bei der Einlösung mit 20 % verrechnet, Spalte `invoices.pay_note` statt Stripe-Hinweis („bereits bezahlt: eingelöst mit Gutschein …“), in der Google-Tabelle „bezahlt (alter Gutschein)“ / „Gutschein-Einlösung“. Deckt der Gutschein nicht alles, keine Rechnung (Rest vor Ort).
- Gutschein-Links (seit 16.10.2026): Verwaltung → Gutscheine → „Link für alten Gutschein“ (Anrede liebe/lieber + Name, gespeichert als „liebe|Name“, Einheiten, Wert, Notiz; „Nachricht kopieren“ kopiert die fertige WhatsApp-Nachricht mit Begrüßung und Link) erzeugt einen Einmal-Link `/einloesen/?t=TOKEN` (Tabelle `voucher_links`). Gast wählt im Monatskalender einen sichtbaren Spin & Go Termin mit genug freien Plätzen, gibt Name, E-Mail, Telefon, Rechnungsadresse an → `api/einloesen.php` sperrt den Link, ruft `forma_old_voucher_booking()` (via_link) auf. Rechnungsadresse steht in `bookings.bill_address` und auf der Rechnung. „Neuen Link erzeugen“ tauscht das Token, der alte Link ist dann ungültig. `/einloesen/` ist in robots.txt gesperrt.
- Regel (Vivien, 16.10.2026): Ein Gutschein-Link = ein Gutschein = genau 1 Platz. Hat der Gutschein mehr Einheiten/Wert, bleibt der Rest als Guthaben (Code kommt in der Bestätigungsmail). Begleitung mit eigenem Gutschein bekommt einen eigenen Link. Vorschau: /einloesen/?t=vorschau (bucht nichts).

## Atelier-Kasse Pottery bemalen (seit 16.10.2026)
- Sortiment Pottery bemalen (Vivien, 16.10.2026): Tasse mit Henkel € 18, Espressotasse € 15, kleine Bowl € 15, große Bowl € 22, Mug mit Daumendruck € 18, FORMA Mug € 22. Keine Teller, Untersetzer, Unterteller mehr. Liste doppelt: `FORMA_BEMALEN_ITEMS` (lib.php) und `BEMALEN_ITEMS` (index.html).
- Verwaltung → Kasse (`?view=kasse`, auch „Kasse öffnen“ auf jedem Pottery-bemalen-Termin): Kacheln mit Bildern antippen (Atelierbeitrag + Stücke), angemeldete Gäste per Tipp übernehmen, dann „Bar bezahlt“ (sofort Rechnung) oder „Bezahl-Link“ (Mail + WhatsApp-Nachricht zum Kopieren). Spontane Gäste ohne Anmeldung: Häkchen „Spontan dazugekommen“ (automatisch an, wenn Atelierbeitrag angetippt und kein angemeldeter Gast gewählt) trägt sie als bestätigte Buchung ohne eigene Zahlung in den Termin ein (Personen = Anzahl Atelierbeiträge). Offene Links: Erinnerung, „Doch bar bezahlt“, zurückziehen.
- Code: `api/kasse.php`, Bezahlseite `api/kasse-zahlen.php?s=ID&k=TOKEN`, Tabelle `kasse_sales`, Rechnungsart `kasse`, Abgleich in `forma_reconcile_payments`.
- Rechnung: Positionen „Pottery bemalen Workshop · <Stück>“ mit „Werkstück zum Bemalen inkl. Farben und Fertigstellung“, nie „Keramik“. Bar: pay_note „… bar im Atelier bezahlt.“, Google-Tabelle „bezahlt (bar)“ / „Atelier-Kasse (bar)“, CSV „bar (Atelier-Kasse)“.

## Web-App (seit 17.10.2026, einfache Version; richtige App erst 2027, kein App Store)
- Startseite installierbar: `manifest.webmanifest` (Name „Studio FORMA“, Icons `img/app/forma-*.png` = Logo auf Sand), `sw.js` (Seiten immer frisch aus dem Netz, nur offline die letzte Startseite; fonts/img/assets zwischengespeichert; api/, verwaltung/, video/ nie). Bei größeren Bild-Austauschen `VERSION` in sw.js hochzählen.
- Verwaltung eigene App: `verwaltung/manifest.webmanifest` („FORMA Verwaltung“, Scope /verwaltung/), Icon `img/app/verwaltung-*.png` = FORMA-Kreis mit F (wie im Logo) und „VERWALTUNG“ (Quelle 1024 px: `verwaltung-1024.png`).
- iPhone: Safari → Teilen → „Zum Home-Bildschirm“. Android: Chrome → „App installieren“.

## Wild Hearts Collective (seit 18.10.2026)
- `wildhearts/index.html` = englische Link-in-Bio-Seite für Viviens Community (Movecia, nicht FORMA). moveciamovement.com ist bei Hostinger als geparkte Domain an diese Website gehängt; `.htaccess` zeigt dort auf der Startseite `wildhearts/` (Adresse bleibt stehen).
- Design im FORMA-Stil (Bogenfoto, Tonformen, Welle), Rot weicher (`#93402F`), Italiana-Titel, wirbelnde Jahreszeiten-Icons nur am Rand.
- Anmeldung geht direkt an das MailerLite-Formular „Wild Hearts – Link in Bio“ (Gruppe „Wild Hearts“, Double-Opt-in), nicht über `api/subscribe.php` – so landen die Frauen nie in der FORMA Post.
- Noch nirgends Retreats erwähnen (Vivien, 18.10.2026). Inner Circle mit Mentoring ab 2027 als Warteliste.
- Link-Seite seit 18.10.2026 im Stil des Reset-PDFs: Polaroid mit Klebeband (`img/wildhearts/viv-stretch.jpg`), Handschrift „Hand“ (`fonts/HomemadeApple-Regular.woff`), Geschenk-Karte Reset Routine (Salbei), Brief (Weinrot), Affirmation (Dunkelbraun). Fensterfotos: `cover.jpg`, `fenster-quadrat.jpg`.
- Reset Routine: PDFs in `wildhearts/files/` (EN `Reset-Routine-for-Tired-Days.pdf`, DE `Reset-Routine-fuer-muede-Tage.pdf`, Quellen außerhalb des Repos). Download-Seite mit Sprachwahl: moveciamovement.com/reset/ (= `wildhearts/reset/`, noindex). Link steht in der MailerLite-Automation „Wild Hearts – Willkommen + Reset Routine“ (Auslöser: tritt Gruppe „Wild Hearts“ bei).
- Erstes Gathering: Anfang Dezember 2026. Videochat über Whereby (Vivien hat ein Konto, Schlüssel nur in der Verwaltung, nie im Chat/Repo).
- Verwaltung → „Movecia Movement“ (dunkelgrüner Knopf unter der Begrüßung, `?view=movecia`, Code in `verwaltung/movecia.php`, eigene Farben Salbei/Moos): Zahl der MailerLite-Gruppe „Wild Hearts“ (über den vorhandenen MailerLite-Schlüssel), Gatherings anlegen, Anmeldungen, „gerade im Videochat“, Einstellungen Whereby-Schlüssel (`../forma-data/movecia-whereby.json`) und Postfach-Passwort hello@moveciamovement.com (`movecia-mail.json`).
- Gatherings: `api/movecia-lib.php` (Tabellen `mm_gatherings`, `mm_signups`, Mails nur von hello@moveciamovement.com, nie FORMA-Absender), `api/gathering.php` (GET nächstes Gathering, POST Anmeldung / ping), Seite moveciamovement.com/gathering (= `wildhearts/gathering/index.php`). Persönlicher Link `/gathering/?k=TOKEN`, Raum (Whereby, roomMode group, eingebettet per iframe) öffnet 15 Min. vor Beginn bis 1 Std. nach Ende. Erinnerungen 24 Std. und 1 Std. vorher, angestoßen bei Seitenaufrufen. „Im Raum“ = Ping in den letzten 2 Min.
- Lokal testen: `MM_WHEREBY_API=http://127.0.0.1:12112` (Fake-Server), `MM_SITE_URL=http://127.0.0.1:8765`.

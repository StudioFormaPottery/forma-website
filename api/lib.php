<?php
// Gemeinsame Funktionen für das FORMA Buchungssystem.
// Daten liegen außerhalb von public_html, damit sie bei einem neuen Deployment
// erhalten bleiben und nicht über das Web abrufbar sind.

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'lib.php') {
    http_response_code(404);
    exit;
}

const FORMA_NOTIFY_EMAIL = 'hello@forma-pottery.com';
const FORMA_FROM_EMAIL   = 'hello@forma-pottery.com';
const FORMA_FROM_NAME    = 'FORMA Earth & Art';
const FORMA_SITE_URL     = 'https://www.forma-pottery.com';
const FORMA_PHONE        = '+43 677 63 90 70 80';
const FORMA_ADDRESS      = 'FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon';
const FORMA_HOLD_SECONDS = 2400;

date_default_timezone_set('Europe/Vienna');

function forma_data_dir(): string
{
    $env = getenv('FORMA_DATA_DIR');
    if ($env) {
        $dir = $env;
    } else {
        // .../domains/forma-pottery.com/public_html/api -> .../domains/forma-pottery.com/forma-data
        $dir = dirname(__DIR__, 2) . '/forma-data';
    }
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Datenordner kann nicht angelegt werden: ' . $dir);
        }
    }
    return $dir;
}

function forma_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . forma_data_dir() . '/buchungen.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    forma_migrate($pdo);
    return $pdo;
}

function forma_migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        date_text TEXT NOT NULL,
        start_date TEXT NOT NULL,
        capacity INTEGER NOT NULL DEFAULT 1,
        min_participants INTEGER NOT NULL DEFAULT 1,
        price_text TEXT NOT NULL DEFAULT \'\',
        description TEXT NOT NULL DEFAULT \'\',
        visible INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT \'\',
        persons INTEGER NOT NULL DEFAULT 1,
        message TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'bestaetigt\',
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL DEFAULT \'\',
        source TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'offen\',
        token TEXT NOT NULL,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        confirmed_at TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS vouchers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nr TEXT,
        product TEXT NOT NULL,
        qty INTEGER NOT NULL,
        amount_cents INTEGER NOT NULL,
        recipient TEXT NOT NULL DEFAULT \'\',
        sender TEXT NOT NULL DEFAULT \'\',
        message TEXT NOT NULL DEFAULT \'\',
        design INTEGER NOT NULL DEFAULT 1,
        buyer_name TEXT NOT NULL DEFAULT \'\',
        buyer_email TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'offen\',
        stripe_session TEXT NOT NULL DEFAULT \'\',
        token TEXT NOT NULL,
        mailed INTEGER NOT NULL DEFAULT 0,
        note TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        paid_at TEXT,
        redeemed_at TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS private_bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL DEFAULT \'buchung\',
        hours INTEGER NOT NULL DEFAULT 0,
        amount_cents INTEGER NOT NULL DEFAULT 0,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT \'\',
        persons INTEGER NOT NULL DEFAULT 1,
        project TEXT NOT NULL DEFAULT \'\',
        wish TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'offen\',
        stripe_session TEXT NOT NULL DEFAULT \'\',
        token TEXT NOT NULL,
        mailed INTEGER NOT NULL DEFAULT 0,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        paid_at TEXT,
        done INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT \'\',
        season TEXT NOT NULL DEFAULT \'ganzjaehrig\',
        age TEXT NOT NULL DEFAULT \'erwachsene\',
        level INTEGER NOT NULL DEFAULT 1,
        price_cents INTEGER NOT NULL DEFAULT 0,
        file TEXT NOT NULL DEFAULT \'\',
        image TEXT NOT NULL DEFAULT \'\',
        visible INTEGER NOT NULL DEFAULT 1,
        sort INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS template_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        template_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        amount_cents INTEGER NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'offen\',
        stripe_session TEXT NOT NULL DEFAULT \'\',
        token TEXT NOT NULL,
        mailed INTEGER NOT NULL DEFAULT 0,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        paid_at TEXT
    )');
    forma_add_column($pdo, 'courses', 'price_cents', 'INTEGER NOT NULL DEFAULT 0');
    forma_add_column($pdo, 'bookings', 'amount_cents', 'INTEGER NOT NULL DEFAULT 0');
    forma_add_column($pdo, 'bookings', 'stripe_session', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'bookings', 'pay_token', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'bookings', 'paid_at', 'TEXT');
    forma_add_column($pdo, 'bookings', 'mailed', 'INTEGER NOT NULL DEFAULT 0');
    forma_add_column($pdo, 'templates', 'file_en', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'private_bookings', 'contact', "TEXT NOT NULL DEFAULT 'email'");

    // Startinhalt beim allerersten Aufruf: der laufende Pottery-Pause-Kurs.
    if (forma_setting($pdo, 'seeded') === null) {
        $stmt = $pdo->prepare('INSERT INTO courses (title, date_text, start_date, capacity, min_participants, price_text, description, visible, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
        $stmt->execute([
            'Pottery Pause · 5-Wochen-Kurs',
            'Freitags, 6. 11. – 4. 12. 2026, jeweils 18–20 Uhr',
            '2026-11-06',
            1,
            1,
            '€ 295 für alle fünf Termine',
            'Fünf Freitagabende an der Töpferscheibe, 8 eigene Werkstücke. Für Einsteiger und Fortgeschrittene.',
            date('c'),
        ]);
        forma_set_setting($pdo, 'seeded', '1');
    }

    // Oktober 2026: feste Wochentermine (Spin & Go Mi + Fr, offenes Atelier Do) bis Jahresende,
    // Online-Preis für Pottery Pause und ein Entwurf für das Advents-Pop-up.
    if (forma_setting($pdo, 'seeded_v2') === null) {
        $pdo->exec("UPDATE courses SET price_cents = 29500 WHERE title LIKE 'Pottery Pause%' AND price_cents = 0");
        $from = date('Y-m-d', strtotime('+1 day'));
        forma_create_series($pdo, FORMA_SERIES['spin'], [3, 5], $from, '2026-12-31');
        forma_create_series($pdo, FORMA_SERIES['atelier'], [4], $from, '2026-12-31');
        if (forma_setting($pdo, 'popup') === null) {
            forma_set_setting($pdo, 'popup', json_encode([
                'active' => false,
                'eyebrow' => 'Special im Advent',
                'title' => 'Pottery bemalen im Advent',
                'text' => "Gestalte deine Weihnachtsgeschenke dieses Jahr selbst: Tassen, Schalen und kleine Teller bemalen, ganz entspannt bei Kerzenlicht im Studio.\nDie Termine kommen bald. Trag dich ein, dann erfährst du als Erste davon.",
                'date_line' => 'Ab November · Mi & Do 15–18 Uhr',
                'start' => date('Y-m-d'),
                'end' => '2026-12-20',
                'signup' => true,
                'button_text' => '',
                'button_link' => '',
                'image' => '',
                'version' => 1,
            ], JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'seeded_v2', '1');
    }

    // 10.10.2026: Vivien möchte das Advents-Pop-up sofort einschalten.
    if (forma_setting($pdo, 'popup_on_v3') === null) {
        $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
        if (is_array($p)) {
            $p['active'] = true;
            $p['start'] = '2026-10-10';
            $p['end'] = $p['end'] ?: '2026-12-20';
            $p['version'] = (int)($p['version'] ?? 1) + 1;
            forma_set_setting($pdo, 'popup', json_encode($p, JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'popup_on_v3', '1');
    }

    // 10.10.2026: Advents-Pop-up weihnachtlich gestalten (Girlande, Sternchen, Advents-Bild).
    if (forma_setting($pdo, 'popup_theme_v4') === null) {
        $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
        if (is_array($p)) {
            $p['theme'] = 'advent';
            $p['version'] = (int)($p['version'] ?? 1) + 1;
            forma_set_setting($pdo, 'popup', json_encode($p, JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'popup_theme_v4', '1');
    }

    // 10.10.2026: Vivien hat diese Spin-&-Go-Termine abgesagt. Ohne Anmeldungen löschen, sonst nur ausblenden.
    if (forma_setting($pdo, 'remove_dates_v5') === null) {
        foreach (['2026-10-16', '2026-10-21', '2026-12-23', '2026-12-25', '2026-12-30'] as $day) {
            $stmt = $pdo->prepare('SELECT id FROM courses WHERE title = ? AND start_date = ?');
            $stmt->execute(['Spin & Go', $day]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                $has = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE course_id = ? AND status IN ('bestaetigt','offen')");
                $has->execute([$cid]);
                if ((int)$has->fetchColumn() > 0) {
                    $pdo->prepare('UPDATE courses SET visible = 0 WHERE id = ?')->execute([$cid]);
                } else {
                    $pdo->prepare('DELETE FROM courses WHERE id = ?')->execute([$cid]);
                }
            }
        }
        forma_set_setting($pdo, 'remove_dates_v5', '1');
    }

    // 10.10.2026: Die Advents-Kurse starten schon Ende Oktober.
    if (forma_setting($pdo, 'popup_date_v6') === null) {
        $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
        if (is_array($p) && str_contains((string)($p['date_line'] ?? ''), 'Ende November')) {
            $p['date_line'] = str_replace('Ende November', 'Ende Oktober', $p['date_line']);
            $p['version'] = (int)($p['version'] ?? 1) + 1;
            forma_set_setting($pdo, 'popup', json_encode($p, JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'popup_date_v6', '1');
    }

    // Maker Club: api/seed/maker.json listet die Gratis-Templates der kommenden Monate.
    // Jeder Eintrag landet im maker_schedule und wird am 1. des Monats automatisch aktiv.
    $makerFile = __DIR__ . '/seed/maker.json';
    if (is_file($makerFile)) {
        $plan = json_decode((string)file_get_contents($makerFile), true);
        if (is_array($plan)) {
            foreach ($plan as $mp) {
                $key = preg_replace('/[^a-z0-9-]/', '', (string)($mp['key'] ?? ''));
                $month = (string)($mp['month'] ?? '');
                if ($key === '' || !preg_match('/^\d{4}-\d{2}$/', $month) || forma_setting($pdo, 'makerplan_' . $key) !== null) continue;
                $seed = __DIR__ . '/seed/maker';
                $dir = forma_template_dir();
                $ok = true;
                foreach (["$key-de.pdf", "$key-en.pdf", "$key.jpg"] as $f) {
                    if (!is_file("$dir/seed-$f")) $ok = $ok && is_file("$seed/$f") && @copy("$seed/$f", "$dir/seed-$f");
                }
                if (!$ok) continue;
                $sched = json_decode(forma_setting($pdo, 'maker_schedule') ?? '', true);
                $sched = is_array($sched) ? $sched : [];
                $sched[$month] = ['title' => (string)($mp['title'] ?? ''), 'text' => (string)($mp['text'] ?? ''),
                    'file' => "seed-$key-de.pdf", 'file_en' => "seed-$key-en.pdf", 'image' => "seed-$key.jpg"];
                forma_set_setting($pdo, 'maker_schedule', json_encode($sched, JSON_UNESCAPED_UNICODE));
                forma_set_setting($pdo, 'makerplan_' . $key, '1');
            }
        }
    }

    // Wöchentliche Templates: api/seed/weekly.json listet neue Shop-Templates mit Veröffentlichungszeit.
    // Jedes Template geht automatisch online, sobald publish_at erreicht ist (Zeit in Europe/Vienna).
    $weeklyFile = __DIR__ . '/seed/weekly.json';
    if (is_file($weeklyFile)) {
        $weekly = json_decode((string)file_get_contents($weeklyFile), true);
        if (is_array($weekly)) {
            $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna'));
            foreach ($weekly as $w) {
                $key = preg_replace('/[^a-z0-9-]/', '', (string)($w['key'] ?? ''));
                if ($key === '' || forma_setting($pdo, 'weekly_' . $key) !== null) continue;
                try { $at = new DateTimeImmutable((string)($w['publish_at'] ?? ''), new DateTimeZone('Europe/Vienna')); } catch (Throwable $e) { continue; }
                if ($now < $at) continue;
                $seed = __DIR__ . '/seed/weekly';
                $dir = forma_template_dir();
                $ok = true;
                foreach (["$key-de.pdf", "$key-en.pdf", "$key.jpg"] as $f) {
                    if (!is_file("$dir/seed-$f")) $ok = $ok && is_file("$seed/$f") && @copy("$seed/$f", "$dir/seed-$f");
                }
                if (!$ok) continue;
                $has = $pdo->prepare('SELECT COUNT(*) FROM templates WHERE title = ?');
                $has->execute([(string)$w['title']]);
                if ((int)$has->fetchColumn() === 0) {
                    $pdo->prepare('INSERT INTO templates (title, description, season, age, level, price_cents, file, file_en, image, visible, sort, created_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,?)')
                        ->execute([(string)$w['title'], (string)($w['description'] ?? ''), (string)($w['season'] ?? 'ganzjaehrig'), 'erwachsene', (int)($w['level'] ?? 1),
                            (int)($w['price_cents'] ?? 399), "seed-$key-de.pdf", "seed-$key-en.pdf", "seed-$key.jpg", (int)($w['sort'] ?? 0), date('c')]);
                }
                forma_set_setting($pdo, 'weekly_' . $key, '1');
            }
        }
    }

    // 11.10.2026: Pottery bemalen im Advent, Mi & Do 15–18 Uhr, 4.11.–17.12.2026. Erst ab 19.10. sichtbar.
    if (forma_setting($pdo, 'bemalen_v16') === null) {
        forma_create_series($pdo, FORMA_SERIES['bemalen'], [3, 4], '2026-11-04', '2026-12-17');
        $pdo->exec("UPDATE courses SET visible = 0 WHERE title = 'Pottery bemalen'");
        forma_set_setting($pdo, 'bemalen_v16', '1');
    }
    // Ab 19.10.2026 buchbar: Termine sichtbar, Pop-up mit Buchungsknopf.
    if (date('Y-m-d') >= '2026-10-19' && forma_setting($pdo, 'bemalen_live_v17') === null) {
        $pdo->exec("UPDATE courses SET visible = 1 WHERE title = 'Pottery bemalen'");
        $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
        if (is_array($p) && str_contains((string)($p['title'] ?? ''), 'Pottery bemalen')) {
            $p['eyebrow'] = 'Jetzt buchbar';
            $p['date_line'] = 'Ab 4. November · Mi & Do 15–18 Uhr';
            $p['text'] = "Gestalte deine Weihnachtsgeschenke dieses Jahr selbst: Tassen, Schalen und kleine Teller bemalen, ganz entspannt bei Kerzenlicht im Studio.\nJeden Mittwoch und Donnerstag von 15 bis 18 Uhr, abholen kannst du deine Stücke am Montag darauf. Die Plätze sind begrenzt, such dir gleich deinen Termin aus.";
            $p['signup'] = false;
            $p['button_text'] = 'Jetzt Termin buchen';
            $p['button_link'] = '#pottery-bemalen';
            $p['version'] = (int)($p['version'] ?? 1) + 1;
            forma_set_setting($pdo, 'popup', json_encode($p, JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'bemalen_live_v17', '1');
    }

    // 12.10.2026: Keine Wörter wie Brennen/Glasieren auf der Website, stattdessen „Fertigstellung“.
    if (forma_setting($pdo, 'fertigstellung_v18') === null) {
        foreach (['price_text', 'description'] as $col) {
            $pdo->exec("UPDATE courses SET $col = REPLACE($col, 'Brennen & Glasieren € 25/kg', 'Fertigstellung € 25/kg bei Abholung') WHERE $col LIKE '%Brennen & Glasieren%'");
            $pdo->exec("UPDATE courses SET $col = REPLACE($col, 'Brennen und Glasieren', 'Fertigstellung') WHERE $col LIKE '%Brennen und Glasieren%'");
            $pdo->exec("UPDATE courses SET $col = REPLACE($col, 'Material und Brennen', 'Material und Fertigstellung') WHERE $col LIKE '%Material und Brennen%'");
        }
        forma_set_setting($pdo, 'fertigstellung_v18', '1');
    }

    // 11.10.2026: Offenes Atelier jetzt € 25/kg.
    if (forma_setting($pdo, 'atelier_kg_v15') === null) {
        $pdo->exec("UPDATE courses SET price_text = REPLACE(price_text, '€ 22/kg', '€ 25/kg') WHERE price_text LIKE '%€ 22/kg%'");
        $pdo->exec("UPDATE courses SET description = REPLACE(description, '€ 22/kg', '€ 25/kg') WHERE description LIKE '%€ 22/kg%'");
        forma_set_setting($pdo, 'atelier_kg_v15', '1');
    }

    // 11.10.2026: Pottery bemalen im Advent ab November, Mi & Do 15–18 Uhr, Abholung am Montag darauf.
    if (forma_setting($pdo, 'popup_advent_v14') === null) {
        $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
        if (is_array($p) && str_contains((string)($p['date_line'] ?? ''), 'Ende Oktober')) {
            $p['date_line'] = 'Ab November · Mi & Do 15–18 Uhr';
            $p['text'] = "Gestalte deine Weihnachtsgeschenke dieses Jahr selbst: Tassen, Schalen und kleine Teller bemalen, ganz entspannt bei Kerzenlicht im Studio.\nJeden Mittwoch und Donnerstag von 15 bis 18 Uhr, abholen kannst du deine Stücke am Montag darauf. Trag dich ein, dann erfährst du als Erste, wann die Anmeldung startet.";
            $p['version'] = (int)($p['version'] ?? 1) + 1;
            forma_set_setting($pdo, 'popup', json_encode($p, JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'popup_advent_v14', '1');
    }

    // 10.10.2026: Offenes Atelier an Heiligabend und Silvester abgesagt.
    if (forma_setting($pdo, 'remove_dates_v7') === null) {
        foreach (['2026-12-24', '2026-12-31'] as $day) {
            $stmt = $pdo->prepare('SELECT id FROM courses WHERE title = ? AND start_date = ?');
            $stmt->execute(['Offenes Atelier', $day]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                $has = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE course_id = ? AND status IN ('bestaetigt','offen')");
                $has->execute([$cid]);
                $pdo->prepare((int)$has->fetchColumn() > 0 ? 'UPDATE courses SET visible = 0 WHERE id = ?' : 'DELETE FROM courses WHERE id = ?')->execute([$cid]);
            }
        }
        forma_set_setting($pdo, 'remove_dates_v7', '1');
    }

    // 11.10.2026: Zweites Herbst-Template im Shop: Herbst-Teetasse (Deutsch & Englisch).
    if (forma_setting($pdo, 'tpl_teetasse_v13') === null) {
        $seed = __DIR__ . '/seed';
        $dir = forma_template_dir();
        $ok = true;
        foreach (['teetasse-de.pdf', 'teetasse-en.pdf', 'teetasse.jpg'] as $f) {
            if (!is_file("$dir/seed-$f")) {
                $ok = $ok && is_file("$seed/$f") && @copy("$seed/$f", "$dir/seed-$f");
            }
        }
        if ($ok) {
            $has = $pdo->prepare('SELECT COUNT(*) FROM templates WHERE title = ?');
            $has->execute(['Herbst-Teetasse']);
            if ((int)$has->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO templates (title, description, season, age, level, price_cents, file, file_en, image, visible, sort, created_at) VALUES (?,?,?,?,?,?,?,?,?,1,1,?)')
                    ->execute(['Herbst-Teetasse', 'Eine schlichte Teetasse aus der Platte mit Henkel, kleinem Herbstblatt und passender Untertasse. Anfängerfreundlich, mit Schablonen in Originalgröße und einer Schritt-für-Schritt-Anleitung in Bildern.',
                        'herbst', 'erwachsene', 1, 399, 'seed-teetasse-de.pdf', 'seed-teetasse-en.pdf', 'seed-teetasse.jpg', date('c')]);
            }
            forma_set_setting($pdo, 'tpl_teetasse_v13', '1');
        }
    }

    // 11.10.2026: Gratis-Template für November vorab planen (Sternen-Anhänger), wird am 1. 11. automatisch aktiv.
    if (forma_setting($pdo, 'maker_sterne_v12') === null) {
        $seed = __DIR__ . '/seed';
        $dir = forma_template_dir();
        $ok = true;
        foreach (['maker-sterne-de.pdf', 'maker-sterne-en.pdf', 'maker-sterne.jpg'] as $f) {
            if (!is_file("$dir/seed-$f")) {
                $ok = $ok && is_file("$seed/$f") && @copy("$seed/$f", "$dir/seed-$f");
            }
        }
        if ($ok) {
            $sched = json_decode(forma_setting($pdo, 'maker_schedule') ?? '', true);
            $sched = is_array($sched) ? $sched : [];
            $sched['2026-11'] = ['title' => 'Sternen-Anhänger', 'text' => 'Kleine Sterne für den Adventskranz, als Geschenkanhänger oder für den Baum. Ganz einfach, in 30 Minuten gemacht und wunderbar mit Kindern.',
                'file' => 'seed-maker-sterne-de.pdf', 'file_en' => 'seed-maker-sterne-en.pdf', 'image' => 'seed-maker-sterne.jpg'];
            forma_set_setting($pdo, 'maker_schedule', json_encode($sched, JSON_UNESCAPED_UNICODE));
            forma_set_setting($pdo, 'maker_sterne_v12', '1');
        }
    }

    // 11.10.2026: Gratis-Template des Monats (Oktober): Blatt-Schälchen, Deutsch & Englisch.
    if (forma_setting($pdo, 'maker_blatt_v11') === null) {
        $m = forma_maker($pdo);
        if ($m['file'] === '') {
            $seed = __DIR__ . '/seed';
            $dir = forma_template_dir();
            $ok = true;
            foreach (['maker-blatt-de.pdf', 'maker-blatt-en.pdf', 'maker-blatt.jpg'] as $f) {
                if (!is_file("$dir/seed-$f")) {
                    $ok = $ok && is_file("$seed/$f") && @copy("$seed/$f", "$dir/seed-$f");
                }
            }
            if ($ok) {
                $m['title'] = 'Blatt-Schälchen';
                $m['text'] = 'Ein kleines Schälchen in Blattform für Ringe, Teebeutel oder Herbstdeko. Ganz einfach, in 30 Minuten gemacht und auch schön mit Kindern.';
                $m['file'] = 'seed-maker-blatt-de.pdf';
                $m['file_en'] = 'seed-maker-blatt-en.pdf';
                $m['image'] = 'seed-maker-blatt.jpg';
                $m['version'] = (int)$m['version'] + 1;
                forma_set_setting($pdo, 'maker', json_encode($m, JSON_UNESCAPED_UNICODE));
                forma_set_setting($pdo, 'maker_blatt_v11', '1');
            }
        } else {
            forma_set_setting($pdo, 'maker_blatt_v11', '1');
        }
    }

    // 11.10.2026: Erstes Shop-Template „Kürbisteller“ (Deutsch & Englisch) einstellen.
    if (forma_setting($pdo, 'tpl_kuerbis_v10') === null) {
        $seed = __DIR__ . '/seed';
        $dir = forma_template_dir();
        $ok = true;
        foreach (['kuerbisteller-de.pdf', 'kuerbisteller-en.pdf', 'kuerbisteller.jpg'] as $f) {
            if (!is_file("$dir/seed-$f")) {
                $ok = $ok && is_file("$seed/$f") && @copy("$seed/$f", "$dir/seed-$f");
            }
        }
        if ($ok) {
            $has = $pdo->prepare('SELECT COUNT(*) FROM templates WHERE title = ?');
            $has->execute(['Kürbisteller']);
            if ((int)$has->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO templates (title, description, season, age, level, price_cents, file, file_en, image, visible, sort, created_at) VALUES (?,?,?,?,?,?,?,?,?,1,0,?)')
                    ->execute(['Kürbisteller', 'Ein flacher Teller in Kürbisform mit sanft hochgebogenem Rand, kleinem Stiel und Blatt. Mit Schablonen in Originalgröße und einer Schritt-für-Schritt-Anleitung in Bildern. Perfekt für Kekse, Herbstdeko oder als Frühstücksteller.',
                        'herbst', 'erwachsene', 1, 399, 'seed-kuerbisteller-de.pdf', 'seed-kuerbisteller-en.pdf', 'seed-kuerbisteller.jpg', date('c')]);
            }
            forma_set_setting($pdo, 'tpl_kuerbis_v10', '1');
        }
    }

    // 11.10.2026: Das Wort „Keramik“ soll nirgends auf der Website stehen.
    if (forma_setting($pdo, 'no_keramik_v9') === null) {
        $pdo->exec("UPDATE courses SET description = REPLACE(description, 'Keramikstücke', 'Werkstücke') WHERE description LIKE '%Keramik%'");
        forma_set_setting($pdo, 'no_keramik_v9', '1');
    }

    // 10.10.2026: Link zur WhatsApp-Community eintragen (falls noch keiner gesetzt ist).
    if (forma_setting($pdo, 'community_v8') === null) {
        if ((string)(forma_setting($pdo, 'community') ?? '') === '') {
            forma_set_setting($pdo, 'community', 'https://chat.whatsapp.com/Kw3hNMTZToU95lJahyKVrE');
        }
        forma_set_setting($pdo, 'community_v8', '1');
    }
}

/** Vorlagen für Serientermine. */
const FORMA_SERIES = [
    'spin' => [
        'title' => 'Spin & Go',
        'from' => '9', 'to' => '11',
        'capacity' => 4, 'min' => 2,
        'price_text' => '€ 70 pro Person, inkl. eines Werkstücks',
        'price_cents' => 7000,
        'description' => 'Zwei Stunden an der Töpferscheibe, begleitet von Anfang an. Für Einsteiger und Fortgeschrittene.',
    ],
    'atelier' => [
        'title' => 'Offenes Atelier',
        'from' => '8', 'to' => '11',
        'capacity' => 4, 'min' => 1,
        'price_text' => '€ 30 pro Stunde, Fertigstellung € 25/kg bei Abholung, Bezahlung vor Ort',
        'price_cents' => 0,
        'description' => 'Selbstständig an eigenen Projekten arbeiten. Kein betreuter Kurs, für alle mit Vorerfahrung.',
    ],
    'bemalen' => [
        'title' => 'Pottery bemalen',
        'from' => '15', 'to' => '18',
        'capacity' => 8, 'min' => 1,
        'price_text' => '€ 15 Atelierbeitrag pro Person, dazu dein Stück ab € 15 (Becher € 18, Teller € 22), vor Ort',
        'price_cents' => 1500,
        'description' => 'Tassen, Schalen und kleine Teller bemalen, ganz entspannt bei Kerzenlicht. Online zahlst du den Atelierbeitrag, dein Stück suchst du dir vor Ort aus. Abholung am Montag darauf.',
    ],
];

const FORMA_WEEKDAYS = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

function forma_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    if (!in_array($column, $cols, true)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

/** „Mi, 14. 10. 2026 · 9–11 Uhr“ */
function forma_date_text(string $ymd, string $from, string $to): string
{
    $t = strtotime($ymd);
    return FORMA_WEEKDAYS[(int)date('N', $t)] . ', ' . date('j. n. Y', $t) . ' · ' . $from . '–' . $to . ' Uhr';
}

/**
 * Legt für jeden gewählten Wochentag zwischen $from und $to einen Termin an.
 * Bereits vorhandene Termine (gleicher Name, gleicher Tag) werden übersprungen.
 * @param int[] $weekdays ISO-Wochentage, 1 = Montag
 */
function forma_create_series(PDO $pdo, array $tpl, array $weekdays, string $from, string $to): int
{
    $exists = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE title = ? AND start_date = ?');
    $insert = $pdo->prepare('INSERT INTO courses (title, date_text, start_date, capacity, min_participants, price_text, price_cents, description, visible, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $count = 0;
    $day = strtotime($from);
    $end = strtotime($to);
    for ($i = 0; $day <= $end && $i < 400; $i++, $day = strtotime('+1 day', $day)) {
        if (!in_array((int)date('N', $day), $weekdays, true)) {
            continue;
        }
        $ymd = date('Y-m-d', $day);
        $exists->execute([$tpl['title'], $ymd]);
        if ((int)$exists->fetchColumn() > 0) {
            continue;
        }
        $insert->execute([$tpl['title'], forma_date_text($ymd, (string)$tpl['from'], (string)$tpl['to']), $ymd,
            (int)$tpl['capacity'], (int)$tpl['min'], $tpl['price_text'], (int)$tpl['price_cents'], $tpl['description'], date('c')]);
        $count++;
    }
    return $count;
}

function forma_setting(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (string)$v;
}

function forma_set_setting(PDO $pdo, string $key, ?string $value): void
{
    if ($value === null) {
        $pdo->prepare('DELETE FROM settings WHERE key = ?')->execute([$key]);
        return;
    }
    $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

function forma_booked(PDO $pdo, int $courseId): int
{
    // Offene Online-Zahlungen halten den Platz 40 Minuten lang frei.
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0) FROM bookings WHERE course_id = ?
        AND (status = 'bestaetigt' OR (status = 'offen' AND created_at >= ?))");
    $stmt->execute([$courseId, date('c', time() - FORMA_HOLD_SECONDS)]);
    return (int)$stmt->fetchColumn();
}

// Anmeldeschluss: am Vorabend um 17 Uhr. Liefert das früheste Kursdatum, das gerade noch buchbar ist.
const FORMA_BOOKING_CUTOFF_HOUR = 17;
function forma_first_bookable_date(?int $now = null): string
{
    $now = $now ?? time();
    $days = (int)date('G', $now) < FORMA_BOOKING_CUTOFF_HOUR ? 1 : 2;
    return date('Y-m-d', strtotime(date('Y-m-d', $now) . " +$days day"));
}

function forma_upcoming_courses(PDO $pdo, bool $onlyVisible = true): array
{
    $sql = 'SELECT * FROM courses WHERE start_date >= ?' . ($onlyVisible ? ' AND visible = 1' : '') . ' ORDER BY start_date, id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$onlyVisible ? forma_first_bookable_date() : date('Y-m-d')]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['booked'] = forma_booked($pdo, (int)$r['id']);
        $r['free'] = max(0, (int)$r['capacity'] - $r['booked']);
    }
    return $rows;
}

function forma_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function forma_clean(string $s, int $max): string
{
    $s = trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $s) ?? '');
    return mb_substr($s, 0, $max);
}

function forma_mail_config(): ?array
{
    $file = forma_data_dir() . '/mail.json';
    if (!is_file($file)) {
        return null;
    }
    $cfg = json_decode((string)file_get_contents($file), true);
    return (is_array($cfg) && !empty($cfg['password'])) ? $cfg : null;
}

function forma_save_mail_password(?string $password): void
{
    $file = forma_data_dir() . '/mail.json';
    if ($password === null || $password === '') {
        @unlink($file);
        return;
    }
    file_put_contents($file, json_encode(['password' => $password]));
    @chmod($file, 0600);
}

/** Schlichte HTML-Fassung einer Text-Mail: Links klickbar, Zeilen nur mit Link werden zum Button. */
function forma_mail_html(string $text): string
{
    $label = function (string $url): string {
        if (str_contains($url, 'confirm.php')) return 'E-Mail-Adresse bestätigen';
        if (str_contains($url, 'maker-download') || str_contains($url, 'template-download') || str_contains($url, 'maker-mail')) return 'Template herunterladen';
        if (str_contains($url, 'gutschein-pdf')) return 'Gutschein herunterladen';
        if (str_contains($url, 'cancel.php')) return 'Buchung ansehen';
        if (str_contains($url, '/verwaltung')) return 'Zur Verwaltung';
        return 'Link öffnen';
    };
    $out = '';
    foreach (preg_split("/\r?\n/", rtrim($text)) as $line) {
        $t = trim($line);
        if (preg_match('~^https?://\S+$~', $t)) {
            $u = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
            $out .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0 4px"><tr><td style="border-radius:999px;background:#8C2C1D">'
                . '<a href="' . $u . '" style="display:inline-block;padding:13px 28px;font-family:Arial,Helvetica,sans-serif;font-size:16px;color:#FBF1EA;text-decoration:none;border-radius:999px">' . htmlspecialchars($label($t), ENT_QUOTES, 'UTF-8') . '</a></td></tr></table>'
                . '<p style="margin:0 0 4px;font-size:12px;color:#674B40;word-break:break-all">Falls der Button nicht geht: <a href="' . $u . '" style="color:#8C2C1D">' . $u . '</a></p>';
            continue;
        }
        $h = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        $h = preg_replace('~(https?://[^\s<]+[^\s<.,;:!?)\]“”"\'])~u', '<a href="$1" style="color:#8C2C1D">$1</a>', $h);
        $h = preg_replace('~(?<![/\w.@])(www\.forma-pottery\.com)(?![\w/])~', '<a href="https://$1" style="color:#8C2C1D">$1</a>', $h);
        $h = preg_replace('~([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})~', '<a href="mailto:$1" style="color:#8C2C1D">$1</a>', $h);
        $out .= $t === '' ? '<div style="height:12px"></div>' : '<div>' . $h . '</div>';
    }
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#EEE2D4"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEE2D4"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FBF1EA;border-radius:22px;overflow:hidden">'
        . '<tr><td align="center" style="background:#DDBFA8;padding:22px 30px 18px"><img src="https://www.forma-pottery.com/img/forma-logo.png" width="64" height="64" alt="FORMA Earth &amp; Art" style="display:block;width:64px;height:64px">'
        . '<div style="margin-top:8px;font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#8C2C1D">FORMA Earth &amp; Art</div></td></tr>'
        . '<tr><td style="padding:28px 30px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#33211B">'
        . $out . '<img src="https://www.forma-pottery.com/img/signatur-liebe-zum-ton.png" width="240" alt="Mit ganz viel Liebe zum Ton" style="display:block;width:240px;max-width:80%;height:auto;margin:16px 0 0 -6px"></td></tr>'
        . '<tr><td align="center" style="background:#E4D2BF;padding:18px 24px;font-family:Arial,Helvetica,sans-serif;font-size:12.5px;line-height:1.7;color:#674B40">FORMA Earth &amp; Art · Silvrettastraße 58 · 6562 Mathon<br><a href="https://www.forma-pottery.com" style="color:#674B40">www.forma-pottery.com</a> · <a href="https://www.instagram.com/forma.potteryartcraft" style="color:#674B40">Instagram</a></td></tr></table></td></tr></table></body></html>';
}

/** Baut Text + HTML als multipart/alternative. Gibt [Content-Type, Körper] zurück. */
function forma_mail_mime(string $body): array
{
    $b = 'forma' . bin2hex(random_bytes(10));
    $enc = fn(string $s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
    $mime = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($body) . "\r\n"
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc(forma_mail_html($body)) . "\r\n"
        . "--$b--";
    return ['multipart/alternative; boundary="' . $b . '"', $mime];
}

function forma_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    if (getenv('FORMA_MAIL_LOG')) {
        // Testbetrieb: Mails zusätzlich in eine Datei schreiben.
        file_put_contents(getenv('FORMA_MAIL_LOG'), "TO: $to\nSUBJECT: $subject\n$body\n-----\n", FILE_APPEND);
        if (!getenv('FORMA_SMTP_HOST')) {
            return true;
        }
    }
    $cfg = forma_mail_config();
    if ($cfg) {
        try {
            forma_smtp_send($cfg['password'], $to, $subject, $body, $replyTo);
            return true;
        } catch (Throwable $e) {
            error_log('FORMA SMTP: ' . $e->getMessage());
            // Fällt unten auf den einfachen Versand zurück.
        }
    }
    [$ctype, $mime] = forma_mail_mime($body);
    $headers = [
        'From' => mb_encode_mimeheader(FORMA_FROM_NAME, 'UTF-8') . ' <' . FORMA_FROM_EMAIL . '>',
        'Reply-To' => $replyTo ?: FORMA_FROM_EMAIL,
        'MIME-Version' => '1.0',
        'Content-Type' => $ctype,
    ];
    return @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $mime, $headers, '-f' . FORMA_FROM_EMAIL);
}

/**
 * Versand über das Postfach hello@forma-pottery.com bei Hostinger (SMTP mit Anmeldung).
 * So werden die Mails mit der Domain signiert und landen nicht im Spam.
 */
function forma_smtp_send(string $password, string $to, string $subject, string $body, ?string $replyTo): void
{
    $host = getenv('FORMA_SMTP_HOST') ?: 'ssl://smtp.hostinger.com:465';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($host, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("Verbindung fehlgeschlagen: $errstr");
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $cmd = function (?string $line, array $ok) use ($fp, $read): string {
        if ($line !== null) {
            fwrite($fp, $line . "\r\n");
        }
        $resp = $read();
        if (!in_array((int)substr($resp, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP-Antwort: ' . trim($resp));
        }
        return $resp;
    };
    try {
        $cmd(null, [220]);
        $cmd('EHLO forma-pottery.com', [250]);
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(FORMA_FROM_EMAIL), [334]);
        $cmd(base64_encode($password), [235]);
        $cmd('MAIL FROM:<' . FORMA_FROM_EMAIL . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        [$ctype, $mime] = forma_mail_mime($body);
        $headers = [
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@forma-pottery.com>',
            'From: ' . mb_encode_mimeheader(FORMA_FROM_NAME, 'UTF-8') . ' <' . FORMA_FROM_EMAIL . '>',
            'To: <' . $to . '>',
            'Reply-To: ' . ($replyTo ?: FORMA_FROM_EMAIL),
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'MIME-Version: 1.0',
            'Content-Type: ' . $ctype,
        ];
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $mime . "\r\n.";
        $cmd($data, [250]);
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/** Basis-Adresse der Website (lokal zum Testen per Umgebungsvariable überschreibbar). */
function forma_site_url(): string
{
    return rtrim(getenv('FORMA_SITE_URL') ?: FORMA_SITE_URL, '/');
}

function forma_euro(int $cents): string
{
    return '€ ' . ($cents % 100 === 0 ? (string)intdiv($cents, 100) : number_format($cents / 100, 2, ',', '.'));
}

function forma_first_name(string $name): string
{
    return preg_split('/\s+/', trim($name))[0] ?? $name;
}

// --- Stripe -------------------------------------------------------------------

function forma_stripe_key(): ?string
{
    $file = forma_data_dir() . '/stripe.json';
    if (!is_file($file)) {
        return null;
    }
    $cfg = json_decode((string)file_get_contents($file), true);
    return (is_array($cfg) && !empty($cfg['secret_key'])) ? (string)$cfg['secret_key'] : null;
}

function forma_save_stripe_key(?string $key): void
{
    $file = forma_data_dir() . '/stripe.json';
    if ($key === null || $key === '') {
        @unlink($file);
        return;
    }
    file_put_contents($file, json_encode(['secret_key' => $key]));
    @chmod($file, 0600);
}

function forma_stripe_test_mode(?string $key = null): bool
{
    $key = $key ?? forma_stripe_key() ?? '';
    return str_contains($key, '_test_');
}

/** Aufruf der Stripe-API. Wirft eine Exception mit verständlicher Meldung, wenn etwas schiefgeht. */
function forma_stripe(string $method, string $path, array $params = [], ?string $key = null): array
{
    $key = $key ?? forma_stripe_key();
    if (!$key) {
        throw new RuntimeException('Stripe ist nicht eingerichtet.');
    }
    $base = rtrim(getenv('FORMA_STRIPE_API') ?: 'https://api.stripe.com', '/');
    $url = $base . $path;
    $body = http_build_query($params, '', '&');
    if ($method === 'GET' && $body !== '') {
        $url .= '?' . $body;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => $key . ':',
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Stripe-Version: 2024-06-20'],
    ]);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('Stripe nicht erreichbar: ' . $err);
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Unerwartete Antwort von Stripe.');
    }
    if ($status >= 400) {
        throw new RuntimeException('Stripe: ' . ($data['error']['message'] ?? ('HTTP ' . $status)));
    }
    return $data;
}

/**
 * Prüft eine offene Online-Buchung bei Stripe. Ist sie bezahlt, wird sie bestätigt und die Mails gehen raus.
 * Gibt den neuen Status zurück: bestaetigt, offen oder abgebrochen.
 */
function forma_check_payment(PDO $pdo, array $booking): string
{
    if ($booking['status'] !== 'offen' || $booking['stripe_session'] === '') {
        return (string)$booking['status'];
    }
    $session = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($booking['stripe_session']));
    if (($session['payment_status'] ?? '') === 'paid' || ($session['payment_status'] ?? '') === 'no_payment_required') {
        forma_confirm_paid($pdo, (int)$booking['id'], (int)($session['amount_total'] ?? $booking['amount_cents']));
        return 'bestaetigt';
    }
    if (($session['status'] ?? '') === 'expired') {
        $pdo->prepare("UPDATE bookings SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([(int)$booking['id']]);
        return 'abgebrochen';
    }
    return 'offen';
}

function forma_confirm_paid(PDO $pdo, int $bookingId, int $amountCents): void
{
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'bestaetigt', paid_at = ?, amount_cents = ? WHERE id = ? AND status IN ('offen', 'abgebrochen')");
    $stmt->execute([date('c'), $amountCents, $bookingId]);
    forma_send_booking_mails($pdo, $bookingId);
}

/** Gleicht offene Zahlungen mit Stripe ab (höchstens einmal pro Minute). */
function forma_reconcile_payments(PDO $pdo, bool $force = false): void
{
    if (!forma_stripe_key()) {
        return;
    }
    $last = (int)(forma_setting($pdo, 'reconciled_at') ?? 0);
    if (!$force && time() - $last < 60) {
        return;
    }
    forma_set_setting($pdo, 'reconciled_at', (string)time());
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE status = 'offen' AND stripe_session != '' AND created_at >= ?");
    $stmt->execute([date('c', time() - 3 * 86400)]);
    foreach ($stmt->fetchAll() as $b) {
        try {
            forma_check_payment($pdo, $b);
        } catch (Throwable $e) {
            error_log('FORMA reconcile: ' . $e->getMessage());
        }
    }
    $stmt = $pdo->prepare("SELECT * FROM template_orders WHERE status = 'offen' AND stripe_session != '' AND created_at >= ?");
    $stmt->execute([date('c', time() - 3 * 86400)]);
    foreach ($stmt->fetchAll() as $to) {
        try {
            forma_template_check($pdo, $to);
        } catch (Throwable $e) {
            error_log('FORMA reconcile template: ' . $e->getMessage());
        }
    }
    $stmt = $pdo->prepare("SELECT * FROM private_bookings WHERE status = 'offen' AND stripe_session != '' AND created_at >= ?");
    $stmt->execute([date('c', time() - 3 * 86400)]);
    foreach ($stmt->fetchAll() as $pb) {
        try {
            forma_private_check($pdo, $pb);
        } catch (Throwable $e) {
            error_log('FORMA reconcile private: ' . $e->getMessage());
        }
    }
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE status = 'offen' AND stripe_session != '' AND created_at >= ?");
    $stmt->execute([date('c', time() - 3 * 86400)]);
    foreach ($stmt->fetchAll() as $v) {
        try {
            forma_voucher_check($pdo, $v);
        } catch (Throwable $e) {
            error_log('FORMA reconcile voucher: ' . $e->getMessage());
        }
    }
}

/** Bestätigung an die Kundin und Info an Vivien. Wird pro Buchung nur einmal verschickt. */
function forma_send_booking_mails(PDO $pdo, int $bookingId): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    $stmt = $pdo->prepare("SELECT b.*, c.title, c.date_text, c.price_text, c.min_participants, c.capacity
        FROM bookings b JOIN courses c ON c.id = b.course_id WHERE b.id = ? AND b.status = 'bestaetigt' AND b.mailed = 0");
    $stmt->execute([$bookingId]);
    $b = $stmt->fetch();
    if (!$b) {
        $pdo->exec('ROLLBACK');
        return;
    }
    $pdo->prepare('UPDATE bookings SET mailed = 1 WHERE id = ?')->execute([$bookingId]);
    $pdo->exec('COMMIT');

    $first = forma_first_name($b['name']);
    $persons = (int)$b['persons'];
    $personsText = $persons === 1 ? '1 Person' : "$persons Personen";
    $paid = (int)$b['amount_cents'] > 0 && $b['paid_at'];
    $free = max(0, (int)$b['capacity'] - forma_booked($pdo, (int)$b['course_id']));
    $phone = FORMA_PHONE;
    $site = forma_site_url();

    $payLine = $paid
        ? 'Bezahlt: ' . forma_euro((int)$b['amount_cents']) . ' (online, danke!)'
        : 'Preis: ' . $b['price_text'];
    $intro = $paid
        ? "danke für deine Buchung! Deine Zahlung ist angekommen und dein Platz für „{$b['title']}“ ist fix. Ich freue mich schon sehr auf dich."
        : "danke, dass du dich für „{$b['title']}“ angemeldet hast! Deine Anmeldung ist verbindlich bei mir eingegangen, und ich freue mich schon sehr auf dich.";
    $minNote = '';
    if ((int)$b['min_participants'] > 1) {
        $minNote = "\nDer Termin findet ab {$b['min_participants']} Personen statt. Falls es zu wenige Anmeldungen gibt, melde ich mich rechtzeitig bei dir"
            . ($paid ? ' und wir finden einen neuen Termin oder du bekommst dein Geld zurück.' : '.') . "\n";
    }

    $customer = "Hallo $first,\n\n$intro\n\nHier noch einmal alles auf einen Blick:\n\n"
        . "Kurs: {$b['title']}\nTermin: {$b['date_text']}\nPersonen: $personsText\n$payLine\nOrt: FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon\n"
        . $minNote
        . "\nBitte komm in Kleidung, die schmutzig werden darf. Schürzen und Werkzeug habe ich im Studio.\n"
        . "\nFalls doch etwas dazwischenkommt: Bis 48 Stunden vor Kursbeginn kannst du kostenlos stornieren. Danach werden 50 % der Kursgebühr fällig, wenn du ohne Absage nicht kommst, der volle Betrag. Melde dich aber bitte trotzdem jederzeit bei mir, gemeinsam finden wir bestimmt eine Lösung.\n"
        . "\nFalls du Fragen hast oder doch verhindert bist, antworte einfach auf diese E-Mail oder ruf mich an: $phone\n\nBis bald im Studio!\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n";

    $admin = ($paid ? 'Neue bezahlte Buchung über die Website' : 'Neue Kursanmeldung über die Website') . "\n\n"
        . "Kurs: {$b['title']}\nTermin: {$b['date_text']}\nPersonen: $personsText\n"
        . ($paid ? 'Bezahlt: ' . forma_euro((int)$b['amount_cents']) . " über Stripe\n" : "Bezahlung: vor Ort\n")
        . "\nName: {$b['name']}\nE-Mail: {$b['email']}\nTelefon: {$b['phone']}\nNachricht: {$b['message']}\n\n"
        . "Freie Plätze danach: $free\n\nAlle Buchungen: $site/verwaltung/\n";

    forma_mail($b['email'], ($paid ? 'Dein Platz ist fix: ' : 'Deine Anmeldung: ') . $b['title'], $customer);
    forma_mail(FORMA_NOTIFY_EMAIL, ($paid ? 'Bezahlt: ' : 'Neue Anmeldung: ') . $b['title'] . ' (' . $b['name'] . ')', $admin, $b['email']);
}

// --- MailerLite -------------------------------------------------------------------

function forma_mailerlite(): ?array
{
    $file = forma_data_dir() . '/mailerlite.json';
    $cfg = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return (is_array($cfg) && !empty($cfg['api_key'])) ? $cfg : null;
}

function forma_save_mailerlite(?array $cfg): void
{
    $file = forma_data_dir() . '/mailerlite.json';
    if ($cfg === null) {
        @unlink($file);
        return;
    }
    file_put_contents($file, json_encode($cfg));
    @chmod($file, 0600);
}

function forma_ml_request(string $method, string $path, ?array $body, string $key): array
{
    $base = rtrim(getenv('FORMA_MAILERLITE_API') ?: 'https://connect.mailerlite.com', '/');
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    if ($raw === false || $status >= 400) {
        throw new RuntimeException('MailerLite: ' . (is_array($data) ? ($data['message'] ?? 'HTTP ' . $status) : 'nicht erreichbar'));
    }
    return is_array($data) ? $data : [];
}

/** Sucht die Gruppe „Website“ in MailerLite oder legt sie an. Gibt die Gruppen-ID zurück. */
function forma_ml_group(string $key, string $name = 'Website'): string
{
    $list = forma_ml_request('GET', '/api/groups?limit=100&filter%5Bname%5D=' . rawurlencode($name), null, $key);
    foreach ($list['data'] ?? [] as $g) {
        if (($g['name'] ?? '') === $name) {
            return (string)$g['id'];
        }
    }
    $g = forma_ml_request('POST', '/api/groups', ['name' => $name], $key);
    return (string)($g['data']['id'] ?? '');
}

/** Überträgt eine bestätigte Adresse (oder eine Abmeldung) an MailerLite. Fehler werden nur protokolliert. */
function forma_ml_sync(string $email, string $name, bool $active = true, bool $maker = false): bool
{
    $cfg = forma_mailerlite();
    if (!$cfg) {
        return false;
    }
    $body = ['email' => $email, 'status' => $active ? 'active' : 'unsubscribed'];
    if ($active) {
        $groups = [(string)($cfg['group_id'] ?? '')];
        if ($maker) {
            if (empty($cfg['maker_group_id'])) {
                try {
                    $cfg['maker_group_id'] = forma_ml_group($cfg['api_key'], 'Maker Club');
                    forma_save_mailerlite($cfg);
                } catch (Throwable $e) {
                    error_log('FORMA MailerLite Maker-Gruppe: ' . $e->getMessage());
                }
            }
            $groups[] = (string)($cfg['maker_group_id'] ?? '');
        }
        $body['groups'] = array_values(array_filter($groups));
        if ($name !== '') {
            $body['fields'] = ['name' => $name];
        }
        $body['opted_in_at'] = date('Y-m-d H:i:s');
    }
    try {
        forma_ml_request('POST', '/api/subscribers', $body, $cfg['api_key']);
        return true;
    } catch (Throwable $e) {
        error_log('FORMA MailerLite: ' . $e->getMessage());
        return false;
    }
}

// --- Gutscheine ----------------------------------------------------------------

const FORMA_VOUCHERS = [
    'spin' => [
        'name' => 'Spin & Go Workshop', 'price' => 7000, 'one' => 'Platz', 'many' => 'Plätze', 'small' => 'im Spin & Go Workshop',
        'desc' => 'Ein Workshop dauert 2 Stunden: begleitet an der Töpferscheibe, inkl. eines Werkstücks pro Platz.',
    ],
    'private' => [
        'name' => 'Private Pottery', 'price' => 6500, 'one' => 'Stunde', 'many' => 'Stunden', 'small' => 'Private Pottery',
        'desc' => 'Persönliche Einzelstunde Handbuilding, inkl. deinem handgefertigten Werkstück, Material und Fertigstellung. Zum Schluss wird bemalt.',
    ],
    'atelier' => [
        'name' => 'Offenes Atelier', 'price' => 3000, 'one' => 'Stunde', 'many' => 'Stunden', 'small' => 'im Offenen Atelier',
        'desc' => 'Selbstständig im Studio arbeiten, Ton, Werkzeug und Maschinen vor Ort nutzen. Nur für Töpfernde mit Vorerfahrung, die ohne Anleitung arbeiten. Fertigstellung € 25/kg, bei Abholung.',
    ],
];

/** „2 Plätze“ / „1 Stunde“ */
function forma_voucher_qty(array $v): string
{
    $p = FORMA_VOUCHERS[$v['product']];
    return (int)$v['qty'] . ' ' . ((int)$v['qty'] === 1 ? $p['one'] : $p['many']);
}

function forma_voucher_title(array $v): string
{
    return forma_voucher_qty($v) . ' ' . FORMA_VOUCHERS[$v['product']]['small'];
}

/** Bezahlung bestätigen, fortlaufende Nummer vergeben (FORMA-JJJJ-0001) und Mails verschicken. */
function forma_voucher_paid(PDO $pdo, int $id): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? AND status IN ('offen', 'abgebrochen')");
    $stmt->execute([$id]);
    if ($stmt->fetch()) {
        $year = date('Y');
        $n = (int)$pdo->query("SELECT COUNT(*) FROM vouchers WHERE nr LIKE 'FORMA-$year-%'")->fetchColumn() + 1;
        $nr = sprintf('FORMA-%s-%04d', $year, $n);
        $pdo->prepare("UPDATE vouchers SET status = 'bezahlt', paid_at = ?, nr = ? WHERE id = ?")->execute([date('c'), $nr, $id]);
    }
    $pdo->exec('COMMIT');
    forma_voucher_mails($pdo, $id);
}

function forma_voucher_link(array $v): string
{
    return forma_site_url() . '/api/gutschein-pdf.php?v=' . (int)$v['id'] . '&k=' . $v['token'];
}

function forma_voucher_mails(PDO $pdo, int $id): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? AND status = 'bezahlt' AND mailed = 0");
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if (!$v) {
        $pdo->exec('ROLLBACK');
        return;
    }
    $pdo->prepare('UPDATE vouchers SET mailed = 1 WHERE id = ?')->execute([$id]);
    $pdo->exec('COMMIT');
    $first = forma_first_name($v['buyer_name']);
    $title = forma_voucher_title($v);
    $amount = forma_euro((int)$v['amount_cents']);
    $link = forma_voucher_link($v);
    $phone = FORMA_PHONE;
    $site = forma_site_url();
    forma_mail($v['buyer_email'], 'Dein FORMA Gutschein ' . $v['nr'], "Hallo $first,\n\nvielen Dank für deinen Gutschein-Kauf! Was für ein schönes Geschenk.\n\n"
        . "Gutschein: $title\nWert: $amount\nGutschein-Nr.: {$v['nr']}\n" . ($v['recipient'] ? "Für: {$v['recipient']}\n" : '')
        . "\nHier kannst du den Gutschein jederzeit als PDF herunterladen und ausdrucken:\n$link\n\n"
        . "Zum Einlösen meldet sich die beschenkte Person einfach bei mir, telefonisch oder per WhatsApp unter $phone oder per E-Mail an hello@forma-pottery.com, und wir finden gemeinsam einen Termin.\n\n"
        . "Herzliche Grüße\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n");
    forma_mail(FORMA_NOTIFY_EMAIL, 'Gutschein verkauft: ' . $v['nr'] . ' (' . $amount . ')', "Ein Gutschein wurde online gekauft und bezahlt.\n\n"
        . "Nr.: {$v['nr']}\nInhalt: $title\nBetrag: $amount\nFür: {$v['recipient']}\nVon: {$v['sender']}\n\n"
        . "Käufer:in: {$v['buyer_name']}\nE-Mail: {$v['buyer_email']}\n\nPDF: $link\nAlle Gutscheine: $site/verwaltung/?view=gutscheine\n", $v['buyer_email']);
}

function forma_voucher_check(PDO $pdo, array $v): string
{
    if ($v['status'] !== 'offen' || $v['stripe_session'] === '') {
        return (string)$v['status'];
    }
    $session = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($v['stripe_session']));
    if (($session['payment_status'] ?? '') === 'paid') {
        forma_voucher_paid($pdo, (int)$v['id']);
        return 'bezahlt';
    }
    if (($session['status'] ?? '') === 'expired') {
        $pdo->prepare("UPDATE vouchers SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([(int)$v['id']]);
        return 'abgebrochen';
    }
    return 'offen';
}

// --- Private Pottery -------------------------------------------------------------

const FORMA_PRIVATE_HOUR = 6500;

function forma_private_mails(PDO $pdo, int $id): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    $stmt = $pdo->prepare("SELECT * FROM private_bookings WHERE id = ? AND mailed = 0 AND (kind = 'anfrage' OR status = 'bezahlt')");
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b) {
        $pdo->exec('ROLLBACK');
        return;
    }
    $pdo->prepare('UPDATE private_bookings SET mailed = 1 WHERE id = ?')->execute([$id]);
    $pdo->exec('COMMIT');
    $first = forma_first_name($b['name']);
    $phone = FORMA_PHONE;
    $site = forma_site_url();
    $hours = (int)$b['hours'] === 1 ? '1 Stunde' : $b['hours'] . ' Stunden';
    $persons = (int)$b['persons'] === 1 ? '1 Person' : $b['persons'] . ' Personen';
    $viaPhone = ($b['contact'] ?? 'email') === 'telefon';
    $contactLine = $viaPhone ? "Bitte melden per: TELEFON ({$b['phone']})" : 'Bitte melden per: E-Mail';
    $isTeam = str_starts_with((string)$b['project'], 'Teambuilding/Event:');
    if ($b['kind'] === 'anfrage') {
        forma_mail($b['email'], $isTeam ? 'Deine Anfrage für ein Teambuilding-Event' : 'Deine Anfrage für Private Pottery', "Hallo $first,\n\nvielen Dank für deine Nachricht! Ich sehe mir " . ($isTeam ? 'eure Idee' : 'dein Projekt') . " an und melde mich so bald wie möglich " . ($viaPhone ? 'telefonisch' : 'per E-Mail') . " bei dir.\n\n"
            . "Dein Projekt: {$b['project']}\nWunschtermin: {$b['wish']}\n\nWenn dir noch etwas einfällt, antworte einfach auf diese E-Mail oder ruf mich an: $phone\n\nHerzlich,\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n");
        forma_mail(FORMA_NOTIFY_EMAIL, ($isTeam ? 'Anfrage Teambuilding/Event (' : 'Anfrage Private Pottery (') . $b['name'] . ')', "Neue Anfrage für " . ($isTeam ? 'Teambuilding/Event' : 'Private Pottery (noch nicht gebucht)') . "\n$contactLine\n\nName: {$b['name']}\nE-Mail: {$b['email']}\nTelefon: {$b['phone']}\nPersonen: $persons\n\nProjekt:\n{$b['project']}\n\nWunschtermin: {$b['wish']}\n\nAlle Anfragen: $site/verwaltung/?view=private\n", $b['email']);
        return;
    }
    $amount = forma_euro((int)$b['amount_cents']);
    forma_mail($b['email'], 'Deine Private Pottery Stunden sind gebucht', "Hallo $first,\n\nwie schön, dass wir dein Projekt gemeinsam umsetzen! Deine Zahlung ist angekommen.\n\n"
        . "Gebucht: $hours Private Pottery ($persons)\nBezahlt: $amount\nDein Projekt: {$b['project']}\nWunschtermin: {$b['wish']}\n\n"
        . "Ich melde mich in den nächsten Tagen bei dir, damit wir den genauen Termin festmachen. Die Stunden können wir gern auch auf mehrere Termine aufteilen.\n"
        . "Falls doch etwas dazwischenkommt: Bis 48 Stunden vor unserem Termin kannst du kostenlos stornieren, danach werden 50 % fällig, wenn du ohne Absage nicht kommst, der volle Betrag. Melde dich aber bitte trotzdem jederzeit, gemeinsam finden wir eine Lösung.\n\n"
        . "Bis bald im Studio!\nVivien\nFORMA Earth & Art\n$phone\nwww.forma-pottery.com\n");
    forma_mail(FORMA_NOTIFY_EMAIL, 'Private Pottery gebucht: ' . $hours . ' (' . $b['name'] . ')', "Neue bezahlte Buchung Private Pottery\n$contactLine\n\n$hours · $amount bezahlt über Stripe\nPersonen: $persons\n\nName: {$b['name']}\nE-Mail: {$b['email']}\nTelefon: {$b['phone']}\n\nProjekt:\n{$b['project']}\n\nWunschtermin: {$b['wish']}\n\nBitte Termin bestätigen. Übersicht: $site/verwaltung/?view=private\n", $b['email']);
}

function forma_private_check(PDO $pdo, array $b): string
{
    if ($b['status'] !== 'offen' || $b['stripe_session'] === '') {
        return (string)$b['status'];
    }
    $session = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($b['stripe_session']));
    if (($session['payment_status'] ?? '') === 'paid') {
        $pdo->prepare("UPDATE private_bookings SET status = 'bezahlt', paid_at = ? WHERE id = ? AND status = 'offen'")->execute([date('c'), (int)$b['id']]);
        forma_private_mails($pdo, (int)$b['id']);
        return 'bezahlt';
    }
    if (($session['status'] ?? '') === 'expired') {
        $pdo->prepare("UPDATE private_bookings SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([(int)$b['id']]);
        return 'abgebrochen';
    }
    return 'offen';
}

// --- Templates & Maker Club --------------------------------------------------------

const FORMA_SEASONS = ['herbst' => 'Herbst', 'winter' => 'Weihnachten & Winter', 'fruehling' => 'Frühling', 'sommer' => 'Sommer', 'ganzjaehrig' => 'Ganzjährig'];
const FORMA_AGES = ['kinder46' => 'Kinder 4–6 (mit Hilfe)', 'kinder710' => 'Kinder 7–10', 'erwachsene' => 'Ab 11 & Erwachsene', 'erfahren' => 'Mit Töpfererfahrung'];

function forma_template_dir(): string
{
    $dir = forma_data_dir() . '/templates';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

/** Das aktuelle Gratis-Template des Maker Clubs (Titel, Text, PDF, Bild) */
// Template des Monats: das zuletzt in der Verwaltung gespeicherte oder ein vorab geplantes
// (Einstellung „maker_schedule“, Schlüssel = Monat „JJJJ-MM“). Es gilt jeweils das neueste,
// dessen Monat schon begonnen hat. So wechselt es am Monatsersten automatisch.
function forma_maker(PDO $pdo): array
{
    $d = ['title' => '', 'text' => '', 'file' => '', 'file_en' => '', 'image' => '', 'version' => 1, 'month' => ''];
    $m = json_decode(forma_setting($pdo, 'maker') ?? '', true);
    $m = is_array($m) ? array_merge($d, $m) : $d;
    $now = date('Y-m');
    $sched = json_decode(forma_setting($pdo, 'maker_schedule') ?? '', true);
    if (is_array($sched)) {
        $best = null;
        foreach ($sched as $month => $entry) {
            if (is_array($entry) && $month <= $now && $month > (string)$m['month'] && ($best === null || $month > $best)) {
                $best = $month;
            }
        }
        if ($best !== null) {
            $m = array_merge($d, $sched[$best], ['month' => $best, 'version' => (int)str_replace('-', '', $best)]);
        }
    }
    return $m;
}

function forma_maker_planned(PDO $pdo): array
{
    $sched = json_decode(forma_setting($pdo, 'maker_schedule') ?? '', true);
    $out = [];
    foreach (is_array($sched) ? $sched : [] as $month => $entry) {
        if ($month > date('Y-m')) $out[$month] = $entry['title'] ?? '';
    }
    ksort($out);
    return $out;
}

function forma_maker_link(array $sub): string
{
    return forma_site_url() . '/api/maker-download.php?t=' . $sub['token'];
}

function forma_template_order_paid(PDO $pdo, int $id): void
{
    $pdo->prepare("UPDATE template_orders SET status = 'bezahlt', paid_at = ? WHERE id = ? AND status IN ('offen','abgebrochen')")->execute([date('c'), $id]);
    $pdo->exec('BEGIN IMMEDIATE');
    $stmt = $pdo->prepare("SELECT o.*, t.file_en FROM template_orders o LEFT JOIN templates t ON t.id = o.template_id WHERE o.id = ? AND o.status = 'bezahlt' AND o.mailed = 0");
    $stmt->execute([$id]);
    $o = $stmt->fetch();
    if (!$o) {
        $pdo->exec('ROLLBACK');
        return;
    }
    $pdo->prepare('UPDATE template_orders SET mailed = 1 WHERE id = ?')->execute([$id]);
    $pdo->exec('COMMIT');
    $first = forma_first_name($o['name']);
    $link = forma_site_url() . '/api/template-download.php?o=' . $o['id'] . '&k=' . $o['token'];
    $amount = forma_euro((int)$o['amount_cents']);
    forma_mail($o['email'], 'Dein FORMA Template: ' . $o['title'], "Hallo $first,\n\nvielen Dank für deinen Kauf! Hier kannst du dein Template jederzeit herunterladen" . (($o['file_en'] ?? '') !== '' ? ' (auf Deutsch und auf Englisch)' : '') . ":\n\n$link\n\n"
        . "Template: {$o['title']}\nBezahlt: $amount\n\nViel Freude beim Töpfern! Ich freu mich, wenn du mir dein Ergebnis auf Instagram zeigst: @forma.potteryartcraft\n\nHerzlich,\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n");
    forma_mail(FORMA_NOTIFY_EMAIL, 'Template verkauft: ' . $o['title'] . ' (' . $amount . ')', "Ein Template wurde verkauft.\n\nTemplate: {$o['title']}\nBetrag: $amount\nKäufer:in: {$o['name']}\nE-Mail: {$o['email']}\n\nAlle Verkäufe: " . forma_site_url() . "/verwaltung/?view=templates\n", $o['email']);
}

function forma_template_check(PDO $pdo, array $o): string
{
    if ($o['status'] !== 'offen' || $o['stripe_session'] === '') {
        return (string)$o['status'];
    }
    $session = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($o['stripe_session']));
    if (($session['payment_status'] ?? '') === 'paid') {
        forma_template_order_paid($pdo, (int)$o['id']);
        return 'bezahlt';
    }
    if (($session['status'] ?? '') === 'expired') {
        $pdo->prepare("UPDATE template_orders SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([(int)$o['id']]);
        return 'abgebrochen';
    }
    return 'offen';
}

// --- Pop-up & E-Mail-Liste ------------------------------------------------------

function forma_popup(PDO $pdo): array
{
    $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
    $defaults = ['active' => false, 'eyebrow' => '', 'title' => '', 'text' => '', 'date_line' => '', 'start' => '', 'end' => '',
        'signup' => true, 'button_text' => '', 'button_link' => '', 'image' => '', 'version' => 1, 'theme' => ''];
    return is_array($p) ? array_merge($defaults, $p) : $defaults;
}

function forma_popup_live(array $p): bool
{
    $today = date('Y-m-d');
    return $p['active'] && $p['title'] !== ''
        && ($p['start'] === '' || $p['start'] <= $today)
        && ($p['end'] === '' || $p['end'] >= $today);
}

/** Einfache Seite für Bestätigungs- und Abmeldelinks. */
function forma_message_page(string $title, string $text, string $btnText = '', string $btnUrl = '', string $btn2Text = '', string $btn2Url = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $t = forma_h($title);
    $x = nl2br(forma_h($text));
    $extra = $btnUrl !== '' ? '<a class="b" href="' . forma_h($btnUrl) . '" style="margin-right:8px">' . forma_h($btnText) . '</a>' : '';
    $extra .= $btn2Url !== '' ? '<a class="b" href="' . forma_h($btn2Url) . '" style="margin-right:8px">' . forma_h($btn2Text) . '</a>' : '';
    echo <<<HTML
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>$t · FORMA</title><link rel="icon" href="/img/forma-logo.png">
<style>@font-face{font-family:"Outfit";src:url(/fonts/Outfit-Regular.woff) format("woff")}@font-face{font-family:"Bricolage Grotesque";src:url(/fonts/BricolageGrotesque-Bold.woff) format("woff")}@font-face{font-family:"Italiana";src:url(/fonts/Italiana-Regular.woff) format("woff")}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#DDBFA8;color:#33211B;font:18px/1.6 "Outfit",system-ui,sans-serif;padding:16px;box-sizing:border-box}
main{background:#EEE2D4;max-width:520px;width:100%;padding:56px 32px 36px;border-radius:220px 220px 28px 28px;text-align:center;box-sizing:border-box}
img{width:72px;margin:0 auto 10px;display:block}p.e{font-family:"Italiana",serif;font-size:28px;color:#8C2C1D;margin:0}
h1{font-family:"Bricolage Grotesque",sans-serif;font-weight:650;color:#8C2C1D;font-size:34px;line-height:1.1;margin:6px 0 14px;letter-spacing:-.02em}
a.b{display:inline-block;margin-top:22px;background:#8C2C1D;color:#FBF1EA;border-radius:999px;padding:12px 24px;text-decoration:none}</style></head>
<body><main><img src="/img/forma-logo.png" alt="FORMA"><p class="e">FORMA Earth &amp; Art</p><h1>$t</h1><p>$x</p>$extra<a class="b" href="/" style="background:transparent;color:#8C2C1D;border:1.5px solid #8C2C1D">Zur Website</a></main></body></html>
HTML;
    exit;
}

function forma_h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

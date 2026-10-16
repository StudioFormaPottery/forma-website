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
    forma_old_links_schema($pdo);
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
    forma_add_column($pdo, 'bookings', 'extras', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'subscribers', 'reminded_at', 'TEXT');
    forma_add_column($pdo, 'bookings', 'mailed', 'INTEGER NOT NULL DEFAULT 0');
    forma_add_column($pdo, 'templates', 'file_en', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'private_bookings', 'contact', "TEXT NOT NULL DEFAULT 'email'");
    forma_add_column($pdo, 'bookings', 'review_sent', 'TEXT');
    forma_add_column($pdo, 'private_bookings', 'review_sent', 'TEXT');
    forma_add_column($pdo, 'private_bookings', 'wish_date', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'private_bookings', 'wish_time', "TEXT NOT NULL DEFAULT ''");
    forma_add_column($pdo, 'private_bookings', 'call_time', "TEXT NOT NULL DEFAULT ''");
    $pdo->exec('CREATE TABLE IF NOT EXISTS private_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        private_id INTEGER NOT NULL,
        date TEXT NOT NULL,
        start TEXT NOT NULL,
        hours REAL NOT NULL DEFAULT 1,
        note TEXT NOT NULL DEFAULT \'\',
        mailed_at TEXT,
        created_at TEXT NOT NULL
    )');
    // Verlängerungen: Vivien schaltet zusätzliche Stunden frei, der Gast bezahlt sie über einen eigenen Link.
    $pdo->exec('CREATE TABLE IF NOT EXISTS private_extensions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        private_id INTEGER NOT NULL,
        hours REAL NOT NULL,
        amount_cents INTEGER NOT NULL,
        note TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'offen\',
        token TEXT NOT NULL,
        stripe_session TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        paid_at TEXT
    )');

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
                        ->execute([(string)$w['title'], (string)($w['description'] ?? ''), (string)($w['season'] ?? 'ganzjaehrig'), array_key_exists((string)($w['age'] ?? ''), FORMA_AGES) ? (string)$w['age'] : 'erwachsene', (int)($w['level'] ?? 1),
                            (int)($w['price_cents'] ?? 399), "seed-$key-de.pdf", "seed-$key-en.pdf", "seed-$key.jpg", (int)($w['sort'] ?? 0), date('c')]);
                }
                forma_set_setting($pdo, 'weekly_' . $key, '1');
            }
        }
    }

    // 12.10.2026: Rechnungsnummern laufen fortlaufend wie in der Buchhaltung weiter (manuell bis 18 → Website ab 19).
    if (forma_setting($pdo, 'invoice_numbers_v21') === null) {
        if ((forma_setting($pdo, 'invoice_prefix') ?? 'FO') === 'FO') forma_set_setting($pdo, 'invoice_prefix', '');
        if (forma_setting($pdo, 'invoice_start_2026') === null) forma_set_setting($pdo, 'invoice_start_2026', '19');
        forma_set_setting($pdo, 'invoice_numbers_v21', '1');
    }

    // 12.10.2026: Rechnungen 16–18 (Pottery Pause) wurden nie ausgestellt → die Website macht mit 16 weiter.
    if (forma_setting($pdo, 'invoice_numbers_v22') === null) {
        $max = 0;
        try { $max = (int)$pdo->query('SELECT COALESCE(MAX(seq), 0) FROM invoices WHERE year = 2026')->fetchColumn(); } catch (Throwable $e) { $max = 0; }
        if ($max < 16) forma_set_setting($pdo, 'invoice_start_2026', '16');
        forma_set_setting($pdo, 'invoice_numbers_v22', '1');
    }
    // 12.10.2026: Text zum Template des Monats ohne „schön mit Kindern“, dafür Zeit und Tonart.
    if (forma_setting($pdo, 'maker_text_v23') === null) {
        $fix = function ($x) use (&$fix) {
            if (is_array($x)) return array_map($fix, $x);
            return is_string($x) ? str_replace('Ganz einfach, in 30 Minuten gemacht und auch schön mit Kindern.', 'Ganz einfach in 30–40 Minuten selbst gemacht, aus Steinzeugton oder Paperclay.', $x) : $x;
        };
        foreach (['maker', 'maker_schedule'] as $key) {
            $v = json_decode(forma_setting($pdo, $key) ?? '', true);
            if (is_array($v)) forma_set_setting($pdo, $key, json_encode($fix($v), JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'maker_text_v23', '1');
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
            $p['eyebrow'] = 'Special im Advent · jetzt buchbar';
            $p['title'] = 'Pottery bemalen';
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

    // 12.10.2026: Pop-up ohne doppeltes „Advent“ (Kicker „Special im Advent“, Titel „Pottery bemalen“), Anmeldestart dazu.
    if (forma_setting($pdo, 'popup_title_v24') === null) {
        $p = json_decode(forma_setting($pdo, 'popup') ?? '', true);
        if (is_array($p) && ($p['title'] ?? '') === 'Pottery bemalen im Advent') {
            $p['title'] = 'Pottery bemalen';
            if (date('Y-m-d') < '2026-10-19' && !str_contains((string)($p['date_line'] ?? ''), 'Anmeldung')) $p['date_line'] = trim((string)$p['date_line']) . ' · Anmeldung ab 19. Oktober';
            forma_set_setting($pdo, 'popup', json_encode($p, JSON_UNESCAPED_UNICODE));
        }
        forma_set_setting($pdo, 'popup_title_v24', '1');
    }

    // 16.10.2026: Plätze pro Kurs laut Vivien: Spin & Go 4, Offenes Atelier 8 pro Stunde, Pottery bemalen 8.
    // Nur kommende Termine, nie unter die schon gebuchte Anzahl. Spin & Go bleibt, wie es ist (Restplatz trägt sie selbst ein).
    if (forma_setting($pdo, 'capacity_v25') === null) {
        foreach (['atelier', 'bemalen'] as $k) {
            $t = FORMA_SERIES[$k];
            $ids = $pdo->prepare('SELECT id FROM courses WHERE title = ? AND start_date >= ? AND capacity < ?');
            $ids->execute([$t['title'], date('Y-m-d'), $t['capacity']]);
            foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                $pdo->prepare('UPDATE courses SET capacity = ? WHERE id = ?')->execute([$t['capacity'], $cid]);
            }
        }
        $pdo->prepare('UPDATE courses SET description = ? WHERE title = ?')->execute([FORMA_SERIES['atelier']['description'], FORMA_SERIES['atelier']['title']]);
        forma_set_setting($pdo, 'capacity_v25', '1');
    }
    // 12.10.2026: Offenes Atelier online buchbar, stundenweise, 5 Plätze pro Stunde, € 30 pro Stunde.
    if (forma_setting($pdo, 'atelier_online_v20') === null) {
        $t = FORMA_SERIES['atelier'];
        $pdo->prepare('UPDATE courses SET price_cents = ?, capacity = ?, price_text = ?, description = ? WHERE title = ?')
            ->execute([$t['price_cents'], $t['capacity'], $t['price_text'], $t['description'], $t['title']]);
        forma_set_setting($pdo, 'atelier_online_v20', '1');
    }

    // 12.10.2026: Pottery bemalen mit 6 Plätzen, Atelierbeitrag vorab, Stücke direkt mitbuchen.
    if (forma_setting($pdo, 'bemalen_items_v19') === null) {
        $t = FORMA_SERIES['bemalen'];
        $pdo->prepare('UPDATE courses SET price_text = ?, description = ? WHERE title = ?')->execute([$t['price_text'], $t['description'], $t['title']]);
        $ids = $pdo->prepare('SELECT id FROM courses WHERE title = ? AND capacity > 6');
        $ids->execute([$t['title']]);
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $cid) {
            $pdo->prepare('UPDATE courses SET capacity = ? WHERE id = ?')->execute([max(6, forma_booked($pdo, (int)$cid)), $cid]);
        }
        forma_set_setting($pdo, 'bemalen_items_v19', '1');
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
                $m['text'] = 'Ein kleines Schälchen in Blattform für Ringe, Teebeutel oder Herbstdeko. Ganz einfach in 30–40 Minuten selbst gemacht, aus Steinzeugton oder Paperclay.';
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

    // 13.10.2026: Gutscheincodes zum Online-Einlösen, Einlöse-Buchungen, Newsletter-Ablehnung bei Buchungen.
    if (forma_setting($pdo, 'voucher_codes_v25') === null) {
        forma_add_column($pdo, 'vouchers', 'code', 'TEXT');
        forma_add_column($pdo, 'vouchers', 'kind', "TEXT NOT NULL DEFAULT 'kauf'");
        forma_add_column($pdo, 'bookings', 'voucher_id', 'INTEGER');
        forma_add_column($pdo, 'bookings', 'voucher_cents', 'INTEGER NOT NULL DEFAULT 0');
        forma_add_column($pdo, 'private_bookings', 'voucher_id', 'INTEGER');
        forma_add_column($pdo, 'private_bookings', 'voucher_cents', 'INTEGER NOT NULL DEFAULT 0');
        forma_add_column($pdo, 'bookings', 'no_post', 'INTEGER NOT NULL DEFAULT 0');
        forma_add_column($pdo, 'private_bookings', 'no_post', 'INTEGER NOT NULL DEFAULT 0');
        forma_add_column($pdo, 'vouchers', 'no_post', 'INTEGER NOT NULL DEFAULT 0');
        forma_add_column($pdo, 'template_orders', 'no_post', 'INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS vouchers_code ON vouchers(code)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS voucher_uses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            voucher_id INTEGER NOT NULL,
            ref_kind TEXT NOT NULL,
            ref_id INTEGER NOT NULL DEFAULT 0,
            cents INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT \'reserviert\',
            note TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS voucher_tries (ip_hash TEXT NOT NULL, at INTEGER NOT NULL)');
        foreach ($pdo->query("SELECT id FROM vouchers WHERE status IN ('bezahlt','eingeloest') AND (code IS NULL OR code = '')")->fetchAll() as $r) {
            $pdo->prepare('UPDATE vouchers SET code = ? WHERE id = ?')->execute([forma_voucher_new_code(), $r['id']]);
        }
        forma_set_setting($pdo, 'voucher_codes_v25', '1');
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
        'capacity' => 8, 'min' => 1,
        'price_text' => '€ 30 pro Stunde, du wählst 1 bis 3 Stunden · Fertigstellung € 25/kg bei Abholung',
        'price_cents' => 3000,
        'description' => 'Selbstständig an eigenen Projekten arbeiten. Such dir aus, welche Stunden du bleiben möchtest. Kein betreuter Kurs, für alle mit Vorerfahrung.',
    ],
    'bemalen' => [
        'title' => 'Pottery bemalen',
        'from' => '15', 'to' => '18',
        'capacity' => 8, 'min' => 1,
        'price_text' => '€ 15 Atelierbeitrag pro Person, vorab online. Dein Stück wählst du gleich mit aus (ab € 5)',
        'price_cents' => 1500,
        'description' => 'Tassen, Teller und Mugs bemalen, ganz entspannt bei Kerzenlicht. Mit dem Atelierbeitrag ist dein Platz reserviert, dein Stück kannst du gleich mitbuchen. Gefällt dir vor Ort noch mehr, rechnen wir es im Atelier ab. Abholung am Montag darauf.',
    ],
];

/** Stücke zum Bemalen (Pottery bemalen), Preise in Cent. Gleiche Liste steht in index.html (BEMALEN_ITEMS). */
const FORMA_BEMALEN_ITEMS = [
    'tasse' => ['Tasse mit Henkel', 1800],
    'espresso' => ['Espressotasse', 1500],
    'teller' => ['Frühstücksteller', 1500],
    'untersetzer' => ['Untersetzer', 500],
    'unterteller' => ['Unterteller für Tasse', 900],
    'daumen' => ['Mug mit Daumendruck', 1800],
    'formamug' => ['FORMA Mug', 2200],
];

/** Aus einer Eingabe wie ['tasse' => 2] eine geprüfte Liste machen. */
function forma_bemalen_pick($raw): array
{
    $out = [];
    if (!is_array($raw)) return $out;
    foreach (FORMA_BEMALEN_ITEMS as $key => [$label, $cents]) {
        $q = (int)($raw[$key] ?? 0);
        if ($q > 0) $out[] = ['key' => $key, 'name' => $label, 'qty' => min($q, 10), 'cents' => $cents];
    }
    return $out;
}

function forma_bemalen_text(array $items): string
{
    return implode(', ', array_map(fn($i) => $i['qty'] . '× ' . $i['name'] . ' (' . forma_euro($i['cents']) . ')', $items));
}

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

/** Offenes Atelier: stundenweise buchbar, 5 Plätze pro Stunde. */
const FORMA_ATELIER_SLOTS = [8, 9, 10];
const FORMA_ATELIER_SEATS = 8;

function forma_is_atelier(array $course): bool
{
    return ($course['title'] ?? '') === FORMA_SERIES['atelier']['title'];
}

function forma_atelier_slot_label(int $h): string
{
    return $h . '–' . ($h + 1) . ' Uhr';
}

/** Freie Plätze je Stunde. Ältere Reservierungen ohne Stundenangabe belegen alle Stunden. */
function forma_atelier_free(PDO $pdo, int $courseId, int $seats = FORMA_ATELIER_SEATS): array
{
    $used = array_fill_keys(FORMA_ATELIER_SLOTS, 0);
    $stmt = $pdo->prepare("SELECT persons, extras FROM bookings WHERE course_id = ? AND (status = 'bestaetigt' OR (status = 'offen' AND created_at >= ?))");
    $stmt->execute([$courseId, date('c', time() - FORMA_HOLD_SECONDS)]);
    foreach ($stmt->fetchAll() as $b) {
        $items = json_decode((string)$b['extras'], true);
        $hit = false;
        foreach (is_array($items) ? $items : [] as $it) {
            if (preg_match('/^slot(\d+)$/', (string)($it['key'] ?? ''), $m) && isset($used[(int)$m[1]])) {
                $used[(int)$m[1]] += (int)($it['qty'] ?? $b['persons']);
                $hit = true;
            }
        }
        if (!$hit) foreach ($used as $h => $_) $used[$h] += (int)$b['persons'];
    }
    return array_map(fn($u) => max(0, $seats - $u), $used);
}

function forma_upcoming_courses(PDO $pdo, bool $onlyVisible = true): array
{
    $sql = 'SELECT * FROM courses WHERE start_date >= ?' . ($onlyVisible ? ' AND visible = 1' : '') . ' ORDER BY start_date, id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$onlyVisible ? forma_first_bookable_date() : date('Y-m-d')]);
    $rows = $stmt->fetchAll();
    // Pottery bemalen ist erst ab 19.10.2026 buchbar, egal was in der Verwaltung eingestellt ist.
    if ($onlyVisible && date('Y-m-d') < '2026-10-19') {
        $rows = array_values(array_filter($rows, fn($r) => $r['title'] !== FORMA_SERIES['bemalen']['title']));
    }
    foreach ($rows as &$r) {
        $r['booked'] = forma_booked($pdo, (int)$r['id']);
        $r['free'] = max(0, (int)$r['capacity'] - $r['booked']);
        if (forma_is_atelier($r)) {
            $r['slots'] = forma_atelier_free($pdo, (int)$r['id'], (int)$r['capacity']);
            $r['free'] = max($r['slots']);
        }
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

/** Ostersonntag (gregorianisch) als Zeitstempel. */
function forma_easter(int $y): int
{
    $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31); $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return mktime(12, 0, 0, $month, $day, $y);
}

/** Saisonales Mail-Design: herbst, advent, winter, fasching, valentin, ostern, fruehling, sommer (Bilder in img/mail/). */
function forma_mail_theme(?int $ts = null): string
{
    $ts = $ts ?? time();
    $y = (int)date('Y', $ts); $md = date('md', $ts); $day = 86400;
    $easter = forma_easter($y);
    if ($ts >= $easter - 14 * $day && $ts <= $easter + 2 * $day) return 'ostern';
    if ($ts >= $easter - 53 * $day && $ts <= $easter - 46 * $day) return 'fasching'; // Faschingswoche bis Aschermittwoch
    if ($md >= '0201' && $md <= '0214') return 'valentin';
    if ($md >= '1120') return 'advent';
    if ($md <= '0320') return 'winter';
    if ($md <= '0531') return 'fruehling';
    if ($md <= '0831') return 'sommer';
    return 'herbst';
}

/** Schlichte HTML-Fassung einer Text-Mail: Links klickbar, Zeilen nur mit Link werden zum Button. */
function forma_mail_html(string $text): string
{
    $label = function (string $url): string {
        if (str_contains($url, 'confirm.php')) return 'E-Mail-Adresse bestätigen';
        if (str_contains($url, 'maker-download') || str_contains($url, 'template-download') || str_contains($url, 'maker-mail')) return 'Template herunterladen';
        if (str_contains($url, 'gutschein-pdf')) return 'Gutschein herunterladen';
        if (str_contains($url, 'cancel.php')) return 'Buchung ansehen';
        if (str_contains($url, 'private-zahlen.php') || str_contains($url, 'private-plus.php')) return 'Jetzt online bezahlen';
        if (str_contains($url, 'kurs-aendern.php')) return 'Neuen Termin wählen oder stornieren';
        if (str_contains($url, '/verwaltung')) return 'Zur Verwaltung';
        if (str_contains($url, 'g.page/r/') || str_contains($url, 'writereview')) return 'Jetzt bewerten ★';
        return 'Link öffnen';
    };
    $out = '';
    $calRow = false;
    foreach (preg_split("/\r?\n/", rtrim($text)) as $line) {
        $t = trim($line);
        // Kalender-Links als kleine Buttons nebeneinander
        if (preg_match('~^(Apple Kalender[^:]*|Google Kalender[^:]*): (https?://\S+)$~u', $t, $cm)) {
            $isApple = str_starts_with($cm[1], 'Apple');
            $lbl = '+ ' . ($isApple ? 'Apple Kalender' : (str_contains($cm[1], ',') ? 'Google · ' . trim(explode(',', $cm[1], 2)[1]) : 'Google Kalender'));
            if (!$calRow) { $out .= '<div style="margin:8px 0 2px">'; $calRow = true; }
            $out .= '<a href="' . htmlspecialchars($cm[2], ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 18px;border-radius:999px;font-size:15px;text-decoration:none;'
                . ($isApple ? 'background:#8C2C1D;color:#FBF1EA' : 'background:#FFFFFF;color:#8C2C1D;border:1.5px solid #8C2C1D') . '">'
                . htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') . '</a>';
            continue;
        }
        if ($calRow) { $out .= '</div>'; $calRow = false; }
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
    if ($calRow) $out .= '</div>';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#EEE2D4"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEE2D4"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FBF1EA;border-radius:22px;overflow:hidden">'
        . '<tr><td style="background:#DDBFA8;padding:0;line-height:0"><img src="' . FORMA_SITE_URL . '/img/mail/' . forma_mail_theme() . '.png" width="600" alt="FORMA Earth &amp; Art" style="display:block;width:100%;max-width:600px;height:auto"></td></tr>'
        . '<tr><td style="padding:28px 30px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#33211B">'
        . $out . '<img src="https://www.forma-pottery.com/img/signatur-liebe-zum-ton.png" width="240" alt="Mit ganz viel Liebe zum Ton" style="display:block;width:240px;max-width:80%;height:auto;margin:16px 0 0 -6px"></td></tr>'
        . '<tr><td align="center" style="background:#E4D2BF;padding:18px 24px;font-family:Arial,Helvetica,sans-serif;font-size:12.5px;line-height:1.7;color:#674B40">FORMA Earth &amp; Art · Silvrettastraße 58 · 6562 Mathon<br><a href="https://www.forma-pottery.com" style="color:#674B40">www.forma-pottery.com</a> · <a href="https://www.instagram.com/forma.potteryartcraft" style="color:#674B40">Instagram</a></td></tr></table></td></tr></table></body></html>';
}

/** Baut Text + HTML als multipart/alternative. Gibt [Content-Type, Körper] zurück. */
function forma_mail_mime(string $body, array $attachments = []): array
{
    $b = 'forma' . bin2hex(random_bytes(10));
    $enc = fn(string $s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
    $mime = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($body) . "\r\n"
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($GLOBALS['FORMA_MAIL_HTML'] ?? forma_mail_html($body)) . "\r\n"
        . "--$b--";
    if (!$attachments) {
        return ['multipart/alternative; boundary="' . $b . '"', $mime];
    }
    // Mit Anhängen (z. B. Rechnung als PDF): multipart/mixed um den Text-/HTML-Teil
    $m = 'formamix' . bin2hex(random_bytes(10));
    $out = "--$m\r\nContent-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n" . $mime . "\r\n";
    foreach ($attachments as [$name, $data, $type]) {
        $out .= "--$m\r\nContent-Type: $type; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$name\"\r\n\r\n" . $enc($data) . "\r\n";
    }
    return ['multipart/mixed; boundary="' . $m . '"', $out . "--$m--"];
}

function forma_mail(string $to, string $subject, string $body, ?string $replyTo = null, array $attachments = []): bool
{
    if (!empty($GLOBALS['FORMA_TEST'])) {
        // Testbuchung aus der Verwaltung: Mails nur an Vivien, mit [TEST] im Betreff, und für die Vorschau merken.
        $toAdmin = strcasecmp(trim($to), FORMA_NOTIFY_EMAIL) === 0;
        $GLOBALS['FORMA_TEST_MAILS'][] = ['an' => $toAdmin ? 'An dich' : 'An den Gast', 'subject' => $subject, 'html' => $GLOBALS['FORMA_MAIL_HTML'] ?? forma_mail_html($body), 'files' => array_column($attachments, 0)];
        if (empty($GLOBALS['FORMA_TEST_SEND'])) return true;
        $subject = '[TEST] ' . ($toAdmin ? '' : '(an den Gast) ') . $subject;
        $to = FORMA_NOTIFY_EMAIL;
        $replyTo = null;
    }
    if (getenv('FORMA_MAIL_LOG')) {
        // Testbetrieb: Mails zusätzlich in eine Datei schreiben.
        file_put_contents(getenv('FORMA_MAIL_LOG'), "TO: $to\nSUBJECT: $subject\n$body" . ($attachments ? 'ANHANG: ' . implode(', ', array_column($attachments, 0)) . "\n" : '') . "-----\n", FILE_APPEND);
        foreach ($attachments as [$n, $d]) @file_put_contents(dirname(getenv('FORMA_MAIL_LOG')) . '/mail_' . $n, $d);
        if (!getenv('FORMA_SMTP_HOST')) {
            return true;
        }
    }
    $cfg = forma_mail_config();
    if ($cfg) {
        try {
            forma_smtp_send($cfg['password'], $to, $subject, $body, $replyTo, $attachments);
            return true;
        } catch (Throwable $e) {
            error_log('FORMA SMTP: ' . $e->getMessage());
            // Fällt unten auf den einfachen Versand zurück.
        }
    }
    [$ctype, $mime] = forma_mail_mime($body, $attachments);
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
function forma_smtp_send(string $password, string $to, string $subject, string $body, ?string $replyTo, array $attachments = []): void
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
        [$ctype, $mime] = forma_mail_mime($body, $attachments);
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
    try { forma_voucher_sweep($pdo); } catch (Throwable $e) {}
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
    foreach ($pdo->query("SELECT * FROM private_bookings WHERE status = 'anfrage' AND stripe_session != '' ORDER BY id DESC LIMIT 20")->fetchAll() as $pb) {
        try {
            forma_private_check($pdo, $pb);
        } catch (Throwable $e) {
            error_log('FORMA reconcile private later: ' . $e->getMessage());
        }
    }
    foreach ($pdo->query("SELECT * FROM private_extensions WHERE status = 'offen' AND stripe_session != '' ORDER BY id DESC LIMIT 20")->fetchAll() as $px) {
        try {
            forma_private_plus_check($pdo, $px);
        } catch (Throwable $e) {
            error_log('FORMA reconcile private plus: ' . $e->getMessage());
        }
    }
    try { forma_voucher_sweep($pdo); } catch (Throwable $e) { error_log('FORMA voucher sweep: ' . $e->getMessage()); }
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

/**
 * Kundinnen kommen nach einer Buchung automatisch auf die E-Mail-Liste (Bestandskundinnen-Regel § 174 Abs. 4 TKG):
 * Hinweis im Buchungsformular, Abmeldelink in jeder Mail. Wer sich schon abgemeldet hat, bleibt abgemeldet.
 * Gibt den Abmeldelink zurück, oder '' wenn die Person nicht (mehr) auf der Liste steht.
 */
function forma_auto_subscribe(PDO $pdo, string $email, string $name, string $source): string
{
    if (!empty($GLOBALS['FORMA_TEST'])) return forma_site_url() . '/api/unsubscribe.php?t=TEST';
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || forma_setting($pdo, 'auto_newsletter_off') === '1') return '';
    $st = $pdo->prepare('SELECT * FROM subscribers WHERE email = ?');
    $st->execute([$email]);
    $s = $st->fetch();
    if ($s && $s['status'] === 'abgemeldet') return '';
    if (!$s) {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("INSERT INTO subscribers (email, name, source, status, token, created_at, confirmed_at) VALUES (?, ?, ?, 'bestaetigt', ?, ?, ?)")
            ->execute([$email, $name, $source, $token, date('c'), date('c')]);
    } else {
        $token = (string)$s['token'];
        if ($s['status'] !== 'bestaetigt') $pdo->prepare("UPDATE subscribers SET status = 'bestaetigt', confirmed_at = ? WHERE id = ?")->execute([date('c'), $s['id']]);
        if ($s['name'] === '' && $name !== '') $pdo->prepare('UPDATE subscribers SET name = ? WHERE id = ?')->execute([$name, $s['id']]);
    }
    if (!$s || $s['status'] !== 'bestaetigt') {
        try { forma_ml_sync($email, $name, true); } catch (Throwable $e) { error_log('FORMA auto newsletter: ' . $e->getMessage()); }
    }
    return forma_site_url() . '/api/unsubscribe.php?t=' . $token;
}

function forma_newsletter_note(string $unsub): string
{
    if ($unsub === '') return '';
    return "\nPS: Mit deiner Buchung bist du jetzt auch in meinem kleinen Atelier-Newsletter dabei. Keine Sorge, kein Spam! Ich bombardiere dich nicht jeden Tag mit Nachrichten, sondern schreibe dir nur ab und zu, wenn es neue Kurse, Specials oder schöne Ideen aus dem Atelier gibt.\n"
        . "Magst du lieber keine Post von mir? Dann kannst du dich hier jederzeit mit einem Klick abmelden: $unsub\n";
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
    $vc = (int)($b['voucher_cents'] ?? 0);
    $paid = ((int)$b['amount_cents'] > 0 || $vc > 0) && $b['paid_at'];
    $free = max(0, (int)$b['capacity'] - forma_booked($pdo, (int)$b['course_id']));
    $phone = FORMA_PHONE;
    $site = forma_site_url();

    $payLine = $paid
        ? 'Bezahlt: ' . forma_paid_text((int)$b['amount_cents'], $vc)
        : 'Preis: ' . $b['price_text'] . ($vc > 0 ? ' (davon ' . forma_euro($vc) . ' mit Gutschein bezahlt, den Rest bitte vor Ort)' : '');
    $intro = $paid
        ? ((int)$b['amount_cents'] <= 0 && $vc > 0
            ? "schön, dass du deinen Gutschein einlöst! Dein Platz für „{$b['title']}“ ist fix und mit deinem Gutschein schon vollständig bezahlt. Ich freue mich schon sehr auf dich."
            : "danke für deine Buchung! Deine Zahlung ist angekommen und dein Platz für „{$b['title']}“ ist fix. Ich freue mich schon sehr auf dich.")
        : "danke, dass du dich für „{$b['title']}“ angemeldet hast! Deine Anmeldung ist verbindlich bei mir eingegangen, und ich freue mich schon sehr auf dich.";
    $minNote = '';
    if ((int)$b['min_participants'] > 1) {
        $minNote = "\nDer Termin findet ab {$b['min_participants']} Personen statt. Falls es zu wenige Anmeldungen gibt, melde ich mich rechtzeitig bei dir"
            . ($paid ? ((int)$b['amount_cents'] <= 0 && $vc > 0 ? ' und wir finden einen neuen Termin, dein Gutschein bleibt dir natürlich erhalten.' : ' und wir finden einen neuen Termin oder du bekommst dein Geld zurück.') : '.') . "\n";
    }

    $pieces = json_decode((string)($b['extras'] ?? ''), true);
    $isAtelier = $b['title'] === FORMA_SERIES['atelier']['title'];
    $termin = $isAtelier ? preg_replace('/ · .*$/u', '', (string)$b['date_text']) : $b['date_text'];
    $piecesLine = is_array($pieces) && $pieces ? ($isAtelier ? 'Deine Zeiten: ' . implode(', ', array_map(fn($i) => preg_replace('/^Offenes Atelier /', '', $i['name']), $pieces)) . ' (' . count($pieces) . ($pieces && count($pieces) === 1 ? ' Stunde' : ' Stunden') . ")\n" : 'Deine Stücke: ' . forma_bemalen_text($pieces) . "\n") : '';
    $unsub = '';
    if (empty($b['no_post'])) try { $unsub = forma_auto_subscribe($pdo, (string)$b['email'], (string)$b['name'], 'Kursbuchung: ' . $b['title']); } catch (Throwable $e) { error_log('FORMA auto newsletter: ' . $e->getMessage()); }
    $restNote = '';
    if (!empty($b['voucher_id'])) {
        $vs = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? AND kind = 'alt'");
        $vs->execute([(int)$b['voucher_id']]);
        if (($ov = $vs->fetch()) && ($rb = forma_voucher_balance($pdo, $ov)) > 0) {
            $restNote = "\nAuf deinem Gutschein sind danach noch " . forma_euro($rb) . " übrig. Damit du ihn jederzeit einlösen kannst, hat er jetzt einen Code: {$ov['code']}\nGib ihn einfach bei deiner nächsten Buchung auf www.forma-pottery.com vor dem Bezahlen ein.\n";
        }
    }
    $calTxt = ''; $calAtt = [];
    try { [$calTxt, $calAtt] = forma_cal_block($pdo, 'b', $bookingId); } catch (Throwable $e) { error_log('FORMA cal: ' . $e->getMessage()); }
    $customer = "Hallo $first,\n\n$intro\n\nHier noch einmal alles auf einen Blick:\n\n"
        . "Kurs: {$b['title']}\nTermin: $termin\nPersonen: $personsText\n$piecesLine$payLine\nOrt: FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon\n"
        . $minNote
        . $restNote
        . $calTxt
        . "\nBitte komm in Kleidung, die schmutzig werden darf. Schürzen und Werkzeug habe ich im Studio.\n"
        . "\nFalls doch etwas dazwischenkommt: Bis 48 Stunden vor Kursbeginn kannst du kostenlos stornieren. Danach werden 50 % der Kursgebühr fällig, wenn du ohne Absage nicht kommst, der volle Betrag. Melde dich aber bitte trotzdem jederzeit bei mir, gemeinsam finden wir bestimmt eine Lösung.\n"
        . "\nFalls du Fragen hast oder doch verhindert bist, antworte einfach auf diese E-Mail oder ruf mich an: $phone\n\nBis bald im Studio!\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n"
        . forma_newsletter_note($unsub);

    $byVivien = str_starts_with((string)$b['message'], 'Von Vivien eingetragen');
    $byLink = str_starts_with((string)$b['message'], 'Über deinen Gutschein-Link gebucht');
    $admin = ($byLink ? 'Gutschein eingelöst! ' . forma_first_name((string)$b['name']) . ' hat über deinen Gutschein-Link selbst einen Termin gebucht (' . preg_replace('/^Über deinen Gutschein-Link gebucht · /u', '', (string)$b['message']) . ').' : ($byVivien ? 'Von dir in der Verwaltung eingetragen (' . preg_replace('/^Von Vivien eingetragen · /u', '', (string)$b['message']) . ')' : ($paid ? 'Neue bezahlte Buchung über die Website' : 'Neue Kursanmeldung über die Website'))) . "\n\n"
        . "Kurs: {$b['title']}\nTermin: $termin\nPersonen: $personsText\n" . $piecesLine
        . ($paid ? ((int)$b['amount_cents'] > 0 ? 'Bezahlt: ' . forma_euro((int)$b['amount_cents']) . ' über Stripe' . ($vc > 0 ? ' + ' . forma_euro($vc) . ' Gutschein' : '') : 'Bezahlt mit Gutschein: ' . forma_euro($vc)) . "\n" : 'Bezahlung: vor Ort' . ($vc > 0 ? ' (' . forma_euro($vc) . ' schon mit Gutschein bezahlt)' : '') . "\n")
        . (!empty($b['no_post']) ? "Möchte keine FORMA Post bekommen.\n" : '')
        . "\nName: {$b['name']}\nE-Mail: {$b['email']}\nTelefon: {$b['phone']}\n" . (($b['bill_address'] ?? '') !== '' ? "Rechnungsadresse: " . str_replace("\n", ', ', (string)$b['bill_address']) . "\n" : '') . "Nachricht: {$b['message']}\n\n"
        . "Freie Plätze danach: $free\n\nAlle Buchungen: $site/verwaltung/\n";

    forma_mail($b['email'], ($paid ? 'Dein Platz ist fix: ' : 'Deine Anmeldung: ') . $b['title'], $customer, null, $calAtt);
    forma_mail(FORMA_NOTIFY_EMAIL, ($byLink ? 'Gutschein-Link eingelöst: ' : ($paid ? 'Bezahlt: ' : 'Neue Anmeldung: ')) . $b['title'] . ' (' . $b['name'] . ')', $admin, $b['email']);
    if ($paid) forma_invoice_for($pdo, 'kurs', $bookingId);
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
        'desc' => 'Persönliche Einzelstunde Handbuilding, inkl. Ton und Fertigstellung bis 5 kg, das Projekt besprechen wir vorher gemeinsam. Zum Schluss wird bemalt.',
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
    if (($v['kind'] ?? 'kauf') !== 'kauf') return 'Wertgutschein über ' . forma_euro((int)$v['amount_cents']);
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
        $pdo->prepare("UPDATE vouchers SET status = 'bezahlt', paid_at = ?, nr = ?, code = ? WHERE id = ?")->execute([date('c'), $nr, forma_voucher_new_code(), $id]);
    }
    $pdo->exec('COMMIT');
    forma_voucher_mails($pdo, $id);
}

// --- Gutscheincodes: online bei jeder Buchung einlösbar ------------------------------------------
// Der Code ist zufällig (12 Zeichen aus 32 = 60 Bit) und wird erst nach bestätigter Zahlung vergeben.
// Wertgutschein: der Euro-Betrag wird angerechnet, ein Restwert bleibt auf dem Gutschein.

const FORMA_TEMPLATE_LINK_DAYS = 30;
const FORMA_CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const FORMA_VOUCHER_TERMS = 'Wertgutschein ohne Ablaufdatum. Einlösbar für alle Kurse, Workshops und Private Pottery im FORMA Atelier, online mit dem Gutscheincode oder direkt im Atelier. Ein Restwert bleibt auf dem Gutschein erhalten. Keine Barauszahlung. Bitte gut aufbewahren: Wer den Code kennt, kann ihn einlösen.';

function forma_voucher_new_code(): string
{
    $c = '';
    for ($i = 0; $i < 12; $i++) $c .= FORMA_CODE_CHARS[random_int(0, 31)];
    return 'FORMA-' . substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4);
}

function forma_voucher_norm(string $code): string
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if (str_starts_with($c, 'FORMA')) $c = substr($c, 5);
    $c = strtr($c, ['0' => 'O', '1' => 'I']);
    return strlen($c) === 12 ? 'FORMA-' . substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4) : '';
}

/** Restwert: Betrag minus alle reservierten und eingelösten Beträge. */
function forma_voucher_balance(PDO $pdo, array $v): int
{
    if ($v['status'] === 'eingeloest') return 0;
    if ($v['status'] !== 'bezahlt') return 0;
    $st = $pdo->prepare("SELECT COALESCE(SUM(cents), 0) FROM voucher_uses WHERE voucher_id = ? AND status IN ('reserviert','eingeloest')");
    $st->execute([$v['id']]);
    return max(0, (int)$v['amount_cents'] - (int)$st->fetchColumn());
}

/** Sucht einen Gutschein per Code, mit Bremse gegen Durchprobieren (max. 8 Fehlversuche pro Stunde und Gerät). */
function forma_voucher_lookup(PDO $pdo, string $code): array
{
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'forma-gs');
    $pdo->prepare('DELETE FROM voucher_tries WHERE at < ?')->execute([time() - 3600]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM voucher_tries WHERE ip_hash = ?');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 8) return ['ok' => false, 'error' => 'Zu viele Versuche. Bitte probier es in einer Stunde noch einmal oder ruf mich an: ' . FORMA_PHONE];
    $norm = forma_voucher_norm($code);
    $v = null;
    if ($norm !== '') {
        $st = $pdo->prepare('SELECT * FROM vouchers WHERE code = ?');
        $st->execute([$norm]);
        $v = $st->fetch() ?: null;
    }
    if (!$v) {
        $pdo->prepare('INSERT INTO voucher_tries (ip_hash, at) VALUES (?, ?)')->execute([$ip, time()]);
        return ['ok' => false, 'error' => 'Diesen Gutscheincode kenne ich leider nicht. Bitte prüf ihn noch einmal (er steht auf deinem Gutschein, z. B. FORMA-AB12-CD34-EF56).'];
    }
    $bal = forma_voucher_balance($pdo, $v);
    if ($bal <= 0) return ['ok' => false, 'error' => 'Dieser Gutschein ist schon vollständig eingelöst.'];
    return ['ok' => true, 'voucher' => $v, 'balance' => $bal];
}

/** Rechnet einen Gutschein auf eine Buchung an. Muss innerhalb einer Transaktion laufen. */
function forma_voucher_use(PDO $pdo, int $voucherId, string $refKind, int $refId, int $cents, bool $final): void
{
    if ($cents <= 0) return;
    $pdo->prepare('INSERT INTO voucher_uses (voucher_id, ref_kind, ref_id, cents, status, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$voucherId, $refKind, $refId, $cents, $final ? 'eingeloest' : 'reserviert', date('c')]);
    if ($final) forma_voucher_after_use($pdo, $voucherId);
}

function forma_voucher_after_use(PDO $pdo, int $voucherId): void
{
    $st = $pdo->prepare('SELECT * FROM vouchers WHERE id = ?');
    $st->execute([$voucherId]);
    $v = $st->fetch();
    if (!$v || $v['status'] !== 'bezahlt') return;
    $open = $pdo->prepare("SELECT COUNT(*) FROM voucher_uses WHERE voucher_id = ? AND status = 'reserviert'");
    $open->execute([$voucherId]);
    if (forma_voucher_balance($pdo, $v) <= 0 && (int)$open->fetchColumn() === 0) {
        $pdo->prepare("UPDATE vouchers SET status = 'eingeloest', redeemed_at = ? WHERE id = ?")->execute([date('c'), $voucherId]);
    }
}

/** Reservierte Beträge: bezahlt → eingelöst, nicht bezahlt (nach 40 Min.) → wieder frei. */
function forma_voucher_sweep(PDO $pdo): void
{
    foreach ($pdo->query("SELECT * FROM voucher_uses WHERE status = 'reserviert'")->fetchAll() as $u) {
        $paid = false;
        if ($u['ref_kind'] === 'kurs') {
            $st = $pdo->prepare("SELECT status FROM bookings WHERE id = ?");
            $st->execute([$u['ref_id']]);
            $s = $st->fetchColumn();
            $paid = $s === 'bestaetigt';
        } elseif ($u['ref_kind'] === 'private') {
            $st = $pdo->prepare("SELECT status FROM private_bookings WHERE id = ?");
            $st->execute([$u['ref_id']]);
            $s = $st->fetchColumn();
            $paid = $s === 'bezahlt';
        } else {
            $s = false;
        }
        if ($paid) {
            $pdo->prepare("UPDATE voucher_uses SET status = 'eingeloest' WHERE id = ?")->execute([$u['id']]);
            forma_voucher_after_use($pdo, (int)$u['voucher_id']);
        } elseif ($s === false || strtotime((string)$u['created_at']) < time() - 40 * 60) {
            $pdo->prepare("UPDATE voucher_uses SET status = 'frei' WHERE id = ?")->execute([$u['id']]);
        }
    }
}

/** Für die Mails: „€ 30 online + Gutschein € 40“ */
function forma_paid_text(int $cash, int $voucher): string
{
    if ($voucher <= 0) return forma_euro($cash) . ' (online, danke!)';
    if ($cash <= 0) return forma_euro($voucher) . ' mit deinem Gutschein';
    return forma_euro($cash) . ' online + ' . forma_euro($voucher) . ' mit Gutschein';
}

/** Ein Gutschein als Entschuldigung, z. B. wenn ein Kurs nicht stattfinden kann. Gibt den Code zurück. */
function forma_voucher_gift(PDO $pdo, int $cents, string $name, string $email, string $note): array
{
    $year = date('Y');
    $n = (int)$pdo->query("SELECT COUNT(*) FROM vouchers WHERE nr LIKE 'FORMA-$year-%'")->fetchColumn() + 1;
    $nr = sprintf('FORMA-%s-%04d', $year, $n);
    $code = forma_voucher_new_code();
    $pdo->prepare("INSERT INTO vouchers (nr, product, qty, amount_cents, recipient, sender, message, design, buyer_name, buyer_email, status, token, mailed, note, created_at, paid_at, code, kind)
        VALUES (?, 'spin', 1, ?, ?, 'Vivien', '', 1, ?, ?, 'bezahlt', ?, 1, ?, ?, ?, ?, 'geschenk')")
        ->execute([$nr, $cents, $name, $name, $email, bin2hex(random_bytes(16)), $note, date('c'), date('c'), $code]);
    return ['id' => (int)$pdo->lastInsertId(), 'nr' => $nr, 'code' => $code];
}

/**
 * Alter Gutschein (Papier, schon bezahlt, noch ohne Nummer): Vivien trägt den Gast in der Verwaltung ein.
 * Legt den Gutschein nach (kind 'alt', Nummer ALT-JJJJ-001 oder die Nummer vom Papier), bucht den Platz,
 * löst den Gutschein darauf ein und verschickt Bestätigung, Kalendereintrag und Rechnung.
 * Bewertungs-Mail und FORMA Post laufen danach wie bei jeder anderen Buchung.
 * $d: course_id, name, email, phone, persons, value_cents, nr, no_post, note
 */
function forma_old_voucher_booking(PDO $pdo, array $d): array
{
    $st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $st->execute([(int)$d['course_id']]);
    $c = $st->fetch();
    if (!$c) throw new RuntimeException('Diesen Termin gibt es nicht mehr.');
    $persons = max(1, (int)$d['persons']);
    $unit = (int)$c['price_cents'] > 0 ? (int)$c['price_cents'] : (int)FORMA_SERIES['spin']['price_cents'];
    $full = $unit * $persons;
    $value = max(0, (int)$d['value_cents']);
    if ($value <= 0) throw new RuntimeException('Bitte gib den Wert des Gutscheins an.');
    $use = min($value, $full);

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        if (forma_booked($pdo, (int)$c['id']) + $persons > (int)$c['capacity']) {
            throw new RuntimeException(!empty($d['via_link'])
                ? 'An diesem Termin sind leider nicht mehr genug Plätze frei. Bitte such dir einen anderen Tag aus.'
                : 'An diesem Termin sind nicht mehr genug Plätze frei (' . max(0, (int)$c['capacity'] - forma_booked($pdo, (int)$c['id'])) . ' frei). Du kannst die Platzanzahl beim Termin erhöhen.');
        }
        $nr = trim((string)($d['nr'] ?? ''));
        if ($nr !== '') {
            $nr = mb_substr(preg_replace('/\s+/', ' ', $nr), 0, 40);
            if (!preg_match('/^(ALT|FORMA)-/i', $nr)) $nr = 'ALT-' . $nr;
            $chk = $pdo->prepare('SELECT COUNT(*) FROM vouchers WHERE nr = ?');
            $chk->execute([$nr]);
            if ((int)$chk->fetchColumn() > 0) throw new RuntimeException('Die Gutscheinnummer ' . $nr . ' gibt es schon. Lass das Feld leer, dann vergebe ich eine neue.');
        } else {
            $year = date('Y');
            $n = (int)$pdo->query("SELECT COUNT(*) FROM vouchers WHERE nr LIKE 'ALT-$year-%'")->fetchColumn() + 1;
            $nr = sprintf('ALT-%s-%03d', $year, $n);
        }
        $now = date('c');
        $pdo->prepare("INSERT INTO vouchers (nr, product, qty, amount_cents, recipient, sender, message, design, buyer_name, buyer_email, status, token, mailed, note, created_at, paid_at, code, kind, no_post)
            VALUES (?, 'spin', ?, ?, ?, '', '', 1, ?, ?, 'bezahlt', ?, 1, ?, ?, ?, ?, 'alt', ?)")
            ->execute([$nr, $persons, $value, $d['name'], $d['name'], $d['email'], bin2hex(random_bytes(16)),
                trim('Alter Gutschein, schon bezahlt, von Vivien nachgetragen. ' . (string)($d['note'] ?? '')), $now, $now, forma_voucher_new_code(), (int)!empty($d['no_post'])]);
        $vid = (int)$pdo->lastInsertId();
        $fully = $use >= $full;
        forma_old_links_schema($pdo);
        $pdo->prepare("INSERT INTO bookings (course_id, name, email, phone, persons, message, status, created_at, amount_cents, paid_at, voucher_id, voucher_cents, no_post, bill_address)
            VALUES (?, ?, ?, ?, ?, ?, 'bestaetigt', ?, 0, ?, ?, ?, ?, ?)")
            ->execute([(int)$c['id'], $d['name'], $d['email'], (string)($d['phone'] ?? ''), $persons,
                (!empty($d['via_link']) ? 'Über deinen Gutschein-Link gebucht' : 'Von Vivien eingetragen') . ' · alter Gutschein ' . $nr . (trim((string)($d['note'] ?? '')) !== '' ? ' · ' . trim((string)$d['note']) : ''),
                $now, $fully ? $now : null, $vid, $use, (int)!empty($d['no_post']), (string)($d['address'] ?? '')]);
        $bid = (int)$pdo->lastInsertId();
        forma_voucher_use($pdo, $vid, 'kurs', $bid, $use, true);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
        throw $e;
    }
    forma_send_booking_mails($pdo, $bid);
    return ['booking' => $bid, 'voucher' => $vid, 'nr' => $nr, 'used' => $use, 'rest_value' => $value - $use, 'rest_pay' => $full - $use];
}

// --- Gutschein-Links: Einmal-Link für Gäste mit altem Gutschein ---------------------------------
// Vivien erstellt in der Verwaltung pro Gast einen Link (/einloesen/?t=…), der Gast wählt selbst einen Spin & Go Termin.
// Der Link funktioniert genau einmal. Neu erzeugen macht den alten Link ungültig.

function forma_old_links_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL DEFAULT '',
        persons INTEGER NOT NULL DEFAULT 1,
        value_cents INTEGER NOT NULL,
        note TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'offen',
        booking_id INTEGER,
        created_at TEXT NOT NULL,
        used_at TEXT
    )");
    forma_add_column($pdo, 'bookings', 'bill_address', "TEXT NOT NULL DEFAULT ''");
    $done = true;
}

/** Label „liebe|Claudia Huber“ → ['liebe', 'Claudia Huber'] (Anrede für die WhatsApp-Nachricht). */
function forma_old_link_name(string $label): array
{
    if (preg_match('/^(liebe|lieber)\|(.*)$/u', $label, $m)) return [$m[1], trim($m[2])];
    return ['', trim($label)];
}

function forma_old_link_url(array $l): string
{
    return forma_site_url() . '/einloesen/?t=' . $l['token'];
}

function forma_old_link_get(PDO $pdo, string $token): ?array
{
    forma_old_links_schema($pdo);
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $st = $pdo->prepare('SELECT * FROM voucher_links WHERE token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
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
        . "Gutschein: $title\nWert: $amount\nGutschein-Nr.: {$v['nr']}\nGutscheincode: {$v['code']}\n" . ($v['recipient'] ? "Für: {$v['recipient']}\n" : '')
        . "\nHier kannst du den Gutschein jederzeit als PDF herunterladen und ausdrucken:\n$link\n\n"
        . "So wird er eingelöst: Die beschenkte Person bucht ihren Kurs ganz normal auf www.forma-pottery.com und gibt vor dem Bezahlen den Gutscheincode ein. Der Wert wird sofort abgezogen. Oder sie meldet sich bei mir, telefonisch oder per WhatsApp unter $phone, und wir finden gemeinsam einen Termin.\n\n"
        . "Gut zu wissen: " . FORMA_VOUCHER_TERMS . "\n\n"
        . "Bis bald,\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n");
    forma_mail(FORMA_NOTIFY_EMAIL, 'Gutschein verkauft: ' . $v['nr'] . ' (' . $amount . ')', "Ein Gutschein wurde online gekauft und bezahlt.\n\n"
        . "Nr.: {$v['nr']}\nCode: {$v['code']}\nInhalt: $title\nBetrag: $amount\nFür: {$v['recipient']}\nVon: {$v['sender']}\n\n"
        . "Käufer:in: {$v['buyer_name']}\nE-Mail: {$v['buyer_email']}\n\nPDF: $link\nAlle Gutscheine: $site/verwaltung/?view=gutscheine\n", $v['buyer_email']);
    if ((int)$v['amount_cents'] > 0 && ($v['kind'] ?? 'kauf') === 'kauf') forma_invoice_for($pdo, 'gutschein', $id);
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
    $callTime = trim((string)($b['call_time'] ?? ''));
    $contactLine = $viaPhone ? "Bitte melden per: TELEFON ({$b['phone']})" . ($callTime !== '' ? ", am besten $callTime" : '') : 'Bitte melden per: E-Mail';
    $isTeam = str_starts_with((string)$b['project'], 'Teambuilding/Event:');
    $wish = forma_wish_text($b) ?: 'noch offen';
    $planUrl = $site . '/verwaltung/?view=kalender&assign=' . (int)$b['id'];
    if ($b['kind'] === 'anfrage') {
        forma_mail($b['email'], $isTeam ? 'Deine Anfrage für ein Teambuilding-Event' : 'Deine Anfrage für Private Pottery', "Hallo $first,\n\nvielen Dank für deine Nachricht! Ich sehe mir " . ($isTeam ? 'eure Idee' : 'dein Projekt') . " an und melde mich so bald wie möglich " . ($viaPhone ? 'telefonisch' . ($callTime !== '' ? " ($callTime)" : '') : 'per E-Mail') . " bei dir.\n\n"
            . "Dein Projekt: {$b['project']}\nWunschtermin: $wish\n\nWenn dir noch etwas einfällt, antworte einfach auf diese E-Mail oder ruf mich an: $phone\n\nBis bald,\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n");
        forma_mail(FORMA_NOTIFY_EMAIL, ($isTeam ? 'Anfrage Teambuilding/Event (' : 'Anfrage Private Pottery (') . $b['name'] . ')', "Neue Anfrage für " . ($isTeam ? 'Teambuilding/Event' : 'Private Pottery (noch nicht gebucht)') . "\n$contactLine\n\nName: {$b['name']}\nE-Mail: {$b['email']}\nTelefon: {$b['phone']}\nPersonen: $persons\n\nProjekt:\n{$b['project']}\n\nWunschtermin: $wish\n\nIm Kalender einplanen: $planUrl\n", $b['email']);
        return;
    }
    $vc = (int)($b['voucher_cents'] ?? 0);
    $amount = $vc > 0 ? forma_paid_text((int)$b['amount_cents'], $vc) : forma_euro((int)$b['amount_cents']);
    $unsub = '';
    if (empty($b['no_post'])) try { $unsub = forma_auto_subscribe($pdo, (string)$b['email'], (string)$b['name'], 'Buchung: Private Pottery'); } catch (Throwable $e) { error_log('FORMA auto newsletter: ' . $e->getMessage()); }
    $what = $isTeam ? preg_replace('/^Teambuilding\/Event:\s*/', '', (string)$b['project']) : "$hours Private Pottery ($persons)";
    forma_mail($b['email'], $isTeam ? 'Euer Teambuilding-Event ist gebucht' : 'Deine Private Pottery Stunden sind gebucht', "Hallo $first,\n\n" . ($isTeam ? 'wie schön, dass ihr gemeinsam töpfern kommt!' : 'wie schön, dass wir dein Projekt gemeinsam umsetzen!') . " Deine Zahlung ist angekommen.\n\n"
        . "Gebucht: $what\nBezahlt: $amount\n" . ($isTeam ? '' : "Dein Projekt: {$b['project']}\n") . "Wunschtermin: $wish\n\n"
        . "Ich schaue mir deinen Wunschtermin an und schicke dir eine eigene Bestätigung, sobald dein Termin fix ist. Deine Stunden können wir auch gern auf mehrere Tage aufteilen, du bekommst dann für jeden Termin eine Bestätigung.\n"
        . "Falls doch etwas dazwischenkommt: Bis 48 Stunden vor unserem Termin kannst du kostenlos stornieren, danach werden 50 % fällig, wenn du ohne Absage nicht kommst, der volle Betrag. Melde dich aber bitte trotzdem jederzeit, gemeinsam finden wir eine Lösung.\n\n"
        . "Bis bald im Studio!\nVivien\nFORMA Earth & Art\n$phone\nwww.forma-pottery.com\n" . forma_newsletter_note($unsub));
    forma_mail(FORMA_NOTIFY_EMAIL, ($isTeam ? 'Teambuilding gebucht: ' . $persons : 'Private Pottery gebucht: ' . $hours) . ' (' . $b['name'] . ')', ($isTeam ? "Neue bezahlte Buchung Teambuilding\n" : "Neue bezahlte Buchung Private Pottery\n") . "$contactLine\n\n$hours · $amount bezahlt über Stripe\nPersonen: $persons\n\nName: {$b['name']}\nE-Mail: {$b['email']}\nTelefon: {$b['phone']}\n\nProjekt:\n{$b['project']}\n\nWunschtermin: $wish\n\nJetzt im Kalender einplanen (Gast bekommt automatisch die Bestätigung): $planUrl\n", $b['email']);
    forma_invoice_for($pdo, 'private', $id);
}

function forma_private_check(PDO $pdo, array $b): string
{
    $later = $b['status'] === 'anfrage' && $b['stripe_session'] !== '';
    if (($b['status'] !== 'offen' && !$later) || $b['stripe_session'] === '') {
        return (string)$b['status'];
    }
    $session = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($b['stripe_session']));
    if (($session['payment_status'] ?? '') === 'paid') {
        $st = $pdo->prepare("UPDATE private_bookings SET status = 'bezahlt', paid_at = ? WHERE id = ? AND status = ?");
        $st->execute([date('c'), (int)$b['id'], $later ? 'anfrage' : 'offen']);
        try { forma_voucher_sweep($pdo); } catch (Throwable $e) {}
        if ($later) {
            if ($st->rowCount() > 0) forma_private_paid_later($pdo, (int)$b['id']);
        } else {
            forma_private_mails($pdo, (int)$b['id']);
        }
        return 'bezahlt';
    }
    if (($session['status'] ?? '') === 'expired') {
        if ($later) {
            $pdo->prepare("UPDATE private_bookings SET stripe_session = '' WHERE id = ? AND status = 'anfrage'")->execute([(int)$b['id']]);
            return 'anfrage';
        }
        $pdo->prepare("UPDATE private_bookings SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([(int)$b['id']]);
        return 'abgebrochen';
    }
    return $later ? 'anfrage-offen' : 'offen';
}

/** Bezahl-Link für eine Anfrage, die Vivien im Kalender eingeplant hat. */
function forma_private_pay_url(array $b): string
{
    return forma_site_url() . '/api/private-zahlen.php?b=' . (int)$b['id'] . '&k=' . rawurlencode((string)$b['token']);
}

/** Eine Anfrage wurde nachträglich über den Bezahl-Link bezahlt: Danke an den Gast, Info an Vivien, Rechnung. */
function forma_private_paid_later(PDO $pdo, int $id): void
{
    $st = $pdo->prepare("SELECT * FROM private_bookings WHERE id = ? AND status = 'bezahlt'");
    $st->execute([$id]);
    $b = $st->fetch();
    if (!$b) return;
    $first = forma_first_name((string)$b['name']);
    $hours = forma_hours_text((float)$b['hours']);
    $amount = forma_euro((int)$b['amount_cents']);
    $sessions = forma_private_sessions($pdo, $id);
    $list = implode("\n", array_map(fn($x) => '· ' . forma_de_date((string)$x['date']) . ' um ' . forma_de_time((string)$x['start']), $sessions));
    forma_mail((string)$b['email'], 'Danke, deine Zahlung ist angekommen', "Hallo $first,\n\ndanke! Deine Zahlung für $hours Private Pottery ($amount) ist angekommen. Damit ist alles fix"
        . ($sessions ? ":\n\n$list\n" : ".\n") . "\nDie Rechnung bekommst du in einer eigenen E-Mail. Ich freue mich schon sehr auf dich!\n\nBis bald im Studio!\nVivien\nFORMA Earth & Art\n" . FORMA_PHONE . "\nwww.forma-pottery.com\n");
    forma_mail(FORMA_NOTIFY_EMAIL, 'Bezahlt: Private Pottery (' . $b['name'] . ')', "{$b['name']} hat ihre Private-Pottery-Termine über den Bezahl-Link bezahlt.\n\n$hours · $amount über Stripe\n" . ($sessions ? "\n$list\n" : '') . "\nÜbersicht: " . forma_site_url() . "/verwaltung/?view=private\n", (string)$b['email']);
    forma_invoice_for($pdo, 'private', $id);
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
    // Die PDFs kommen direkt als Anhang mit, der Download-Link gilt zusätzlich FORMA_TEMPLATE_LINK_DAYS Tage.
    $att = [];
    $tp = $pdo->prepare('SELECT file, file_en FROM templates WHERE id = ?');
    $tp->execute([(int)$o['template_id']]);
    $tf = $tp->fetch() ?: ['file' => '', 'file_en' => ''];
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', strtr((string)$o['title'], ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']));
    $size = 0;
    foreach (['' => $tf['file'], '-EN' => $tf['file_en']] as $suf => $fn) {
        $path = forma_template_dir() . '/' . basename((string)$fn);
        if ((string)$fn !== '' && is_file($path) && ($size += filesize($path)) < 15 * 1024 * 1024) $att[] = ['FORMA-Template-' . $slug . $suf . '.pdf', (string)file_get_contents($path), 'application/pdf'];
    }
    forma_mail($o['email'], 'Dein FORMA Template: ' . $o['title'], "Hallo $first,\n\nvielen Dank für deinen Kauf! "
        . ($att ? 'Dein Template hängt direkt an dieser E-Mail' . (count($att) > 1 ? ' (auf Deutsch und auf Englisch)' : '') . ". Speicher es dir am besten gleich ab.\n\nZusätzlich kannst du es " . FORMA_TEMPLATE_LINK_DAYS . " Tage lang hier herunterladen:\n$link\n\n"
            : "Hier kannst du dein Template " . FORMA_TEMPLATE_LINK_DAYS . " Tage lang herunterladen" . (($o['file_en'] ?? '') !== '' ? ' (auf Deutsch und auf Englisch)' : '') . ":\n\n$link\n\n")
        . "Template: {$o['title']}\nBezahlt: $amount\n\nViel Freude beim Töpfern! Ich freu mich, wenn du mir dein Ergebnis auf Instagram zeigst: @forma.potteryartcraft\n\nBis bald,\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n", null, $att);
    forma_mail(FORMA_NOTIFY_EMAIL, 'Template verkauft: ' . $o['title'] . ' (' . $amount . ')', "Ein Template wurde verkauft.\n\nTemplate: {$o['title']}\nBetrag: $amount\nKäufer:in: {$o['name']}\nE-Mail: {$o['email']}\n\nAlle Verkäufe: " . forma_site_url() . "/verwaltung/?view=templates\n", $o['email']);
    forma_invoice_for($pdo, 'template', $id);
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


/** Einmalige Erinnerung, wenn eine Anmeldung nach 12 Stunden noch nicht bestätigt ist (läuft höchstens alle 20 Minuten). */
function forma_confirm_reminders(PDO $pdo): void
{
    $last = (int)(forma_setting($pdo, 'reminders_last') ?? 0);
    if (time() - $last < 1200) return;
    forma_set_setting($pdo, 'reminders_last', (string)time());
    $stmt = $pdo->prepare("SELECT * FROM subscribers WHERE status = 'offen' AND reminded_at IS NULL AND created_at <= ? AND created_at >= ? LIMIT 20");
    $stmt->execute([date('c', time() - 12 * 3600), date('c', time() - 7 * 86400)]);
    foreach ($stmt->fetchAll() as $s) {
        $pdo->prepare('UPDATE subscribers SET reminded_at = ? WHERE id = ?')->execute([date('c'), $s['id']]);
        $maker = str_contains((string)$s['source'], 'Maker');
        $hello = $s['name'] !== '' ? 'Hallo ' . forma_first_name($s['name']) . ',' : 'Hallo,';
        $body = "$hello\n\nschön, dass du dich " . ($maker ? 'für den FORMA Maker Club' : 'für die FORMA Post') . " eingetragen hast! "
            . "Deine E-Mail-Adresse ist aber noch nicht bestätigt.\n\n"
            . "Magst du kurz auf den Button klicken? Dann darf ich dich über alles Neue im Atelier informieren"
            . ($maker ? ' und du bekommst gleich dein Gratis-Template' : '') . ".\n\n"
            . forma_site_url() . '/api/confirm.php?t=' . $s['token'] . "\n\n"
            . "Falls du es dir anders überlegt hast, ist das völlig in Ordnung. Dann ignoriere diese E-Mail einfach, ich schreibe dir nicht noch einmal.\n\n"
            . "Bis bald,\nVivien\n";
        forma_mail((string)$s['email'], 'Kurze Erinnerung: Bitte bestätige deine E-Mail-Adresse', $body);
    }
}

const FORMA_REVIEW_URL = 'https://g.page/r/CQ-fySEcCogPEBM/review';

function forma_review_url(PDO $pdo): string
{
    $u = trim((string)(forma_setting($pdo, 'review_url') ?? ''));
    return $u !== '' ? $u : FORMA_REVIEW_URL;
}

/** Ende eines Termins als Zeitstempel (Atelier: letzte gebuchte Stunde), oder null wenn unklar. */
function forma_course_end(array $row): ?int
{
    $date = (string)($row['start_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    if (str_contains(mb_strtolower((string)$row['title']), 'woche')) return null; // mehrwöchige Kurse: kein Einzeltermin
    $end = null;
    if (forma_is_atelier($row)) {
        $x = json_decode((string)($row['extras'] ?? ''), true);
        $slots = [];
        foreach ((is_array($x) ? $x : []) as $it) {
            if (is_array($it) && preg_match('/^slot(\d{1,2})$/', (string)($it['key'] ?? ''), $mm)) $slots[] = (int)$mm[1];
        }
        if ($slots) $end = sprintf('%02d:00', max($slots) + 1);
    }
    if ($end === null && preg_match('/(\d{1,2})(?:[:.](\d{2}))?\s*[–-]\s*(\d{1,2})(?:[:.](\d{2}))?\s*Uhr/u', (string)$row['date_text'], $m)) {
        $end = sprintf('%02d:%02d', (int)$m[3], (int)($m[4] ?? 0));
    }
    if ($end === null) return null;
    $ts = strtotime("$date $end");
    return $ts === false ? null : $ts;
}

function forma_review_text(string $name, string $what): string
{
    $hello = $name !== '' ? 'Hallo ' . forma_first_name($name) . ',' : 'Hallo,';
    return "$hello\n\n"
        . "danke für deinen Besuch bei „{$what}“ im Atelier FORMA! Ich hoffe, du hattest eine sehr schöne und gemütliche Zeit.\n\n"
        . "Magst du mir einen kleinen Gefallen tun? Eine kurze Bewertung auf Google hilft mir sehr, weil andere so das Atelier finden. "
        . "Ein, zwei Sätze reichen völlig und es dauert nur eine Minute.\n\n"
        . "%URL%\n\n"
        . "Wenn etwas nicht gepasst hat, schreib mir bitte direkt zurück, dann kümmere ich mich darum.\n\n"
        . "Thank you for visiting the FORMA studio! If you enjoyed it, a short Google review would mean a lot to me.\n\n"
        . "Bis bald,\nVivien\n";
}

/** Schön gestaltete HTML-Fassung der Bewertungs-Mail (Saison-Kopfbild und Saison-Bewertungskarte). */
function forma_review_html(string $name, string $what, string $url): string
{
    $e = fn($x) => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
    $theme = forma_mail_theme(); $img = FORMA_SITE_URL . '/img/mail/';
    $u = $e($url); $first = $name !== '' ? forma_first_name($name) : '';
    $f = 'font-family:Arial,Helvetica,sans-serif;';
    $p = fn($t, $st = '') => '<p style="margin:0 0 16px;' . $f . 'font-size:16px;line-height:1.65;color:#33211B;' . $st . '">' . $t . '</p>';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Danke für deinen Besuch</title></head>'
        . '<body style="margin:0;padding:0;background:#EEE2D4">'
        . '<div style="display:none;max-height:0;overflow:hidden">Danke für deinen Besuch im Atelier FORMA! Magst du mir eine kurze Google-Bewertung schenken?</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEE2D4"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FBF1EA;border-radius:26px;overflow:hidden">'
        . '<tr><td style="background:#DDBFA8;padding:0;line-height:0"><img src="' . $img . $theme . '.png" width="600" alt="FORMA Earth &amp; Art" style="display:block;width:100%;max-width:600px;height:auto"></td></tr>'
        . '<tr><td style="padding:26px 34px 6px">'
        . '<p style="margin:0 0 6px;font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:19px;color:#674B40">Schön, dass du da warst</p>'
        . '<h1 style="margin:0 0 18px;font-family:Georgia,\'Times New Roman\',serif;font-weight:normal;font-size:34px;line-height:1.15;color:#8C2C1D">Hallo' . ($first !== '' ? ' ' . $e($first) : '') . '!</h1>'
        . $p('Danke für deinen Besuch bei <b>„' . $e($what) . '“</b> im Atelier FORMA! Ich hoffe, du hattest eine sehr schöne und gemütliche Zeit.')
        . $p('Magst du mir einen kleinen Gefallen tun? Eine kurze Bewertung auf Google hilft mir sehr, weil andere so das Atelier finden.')
        . '</td></tr>'
        . '<tr><td style="padding:4px 26px 0"><a href="' . $u . '" style="text-decoration:none"><img src="' . $img . 'bewertung-' . $theme . '.png" width="548" alt="Wie war\'s bei FORMA? ★★★★★ Jetzt auf Google bewerten" style="display:block;width:100%;max-width:548px;height:auto;border-radius:20px"></a></td></tr>'
        . '<tr><td align="center" style="padding:20px 34px 6px">'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-radius:999px;background:#8C2C1D"><a href="' . $u . '" style="display:inline-block;padding:15px 34px;' . $f . 'font-size:16px;color:#FBF1EA;text-decoration:none;border-radius:999px">Jetzt auf Google bewerten ★</a></td></tr></table>'
                . '</td></tr>'
        . '<tr><td style="padding:22px 34px 8px">'
        . $p('Wenn etwas nicht gepasst hat, schreib mir bitte direkt zurück, dann kümmere ich mich darum.')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3E6DA;border-radius:16px"><tr><td style="padding:14px 18px;' . $f . 'font-size:14px;line-height:1.6;color:#674B40;font-style:italic">Thank you for visiting the FORMA studio! If you enjoyed it, a short Google review would mean a lot to me.</td></tr></table>'
        . '<p style="margin:20px 0 0;' . $f . 'font-size:16px;line-height:1.6;color:#33211B">Bis bald,<br>Vivien</p>'
        . '<img src="https://www.forma-pottery.com/img/signatur-liebe-zum-ton.png" width="240" alt="Mit ganz viel Liebe zum Ton" style="display:block;width:240px;max-width:80%;height:auto;margin:10px 0 18px -6px">'
        . '</td></tr>'
        . '<tr><td align="center" style="background:#E4D2BF;padding:18px 24px;' . $f . 'font-size:12.5px;line-height:1.7;color:#674B40">FORMA Earth &amp; Art · Silvrettastraße 58 · 6562 Mathon<br><a href="https://www.forma-pottery.com" style="color:#674B40">www.forma-pottery.com</a> · <a href="https://www.instagram.com/forma.potteryartcraft" style="color:#674B40">Instagram</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

function forma_review_send(PDO $pdo, string $to, string $name, string $what): bool
{
    $url = forma_review_url($pdo);
    $GLOBALS['FORMA_MAIL_HTML'] = forma_review_html($name, $what, $url);
    try {
        return forma_mail($to, 'Danke für deinen Besuch bei FORMA', str_replace('%URL%', $url, forma_review_text($name, $what)));
    } finally {
        unset($GLOBALS['FORMA_MAIL_HTML']);
    }
}

/** Bewertungs-Mail ca. 1 Stunde nach Kursende (läuft höchstens alle 15 Minuten). */
function forma_review_mails(PDO $pdo, bool $force = false): int
{
    if (forma_setting($pdo, 'review_off') === '1') return 0;
    $last = (int)(forma_setting($pdo, 'review_last') ?? 0);
    if (!$force && time() - $last < 900) return 0;
    forma_set_setting($pdo, 'review_last', (string)time());
    $stmt = $pdo->prepare("SELECT b.id, b.name, b.email, b.extras, b.course_id, c.title, c.date_text, c.start_date
        FROM bookings b JOIN courses c ON c.id = b.course_id
        WHERE b.status = 'bestaetigt' AND b.review_sent IS NULL AND c.start_date BETWEEN ? AND ?");
    $stmt->execute([date('Y-m-d', time() - 2 * 86400), date('Y-m-d')]);
    $sent = 0; $seen = [];
    foreach ($stmt->fetchAll() as $b) {
        $end = forma_course_end($b);
        if ($end === null) { $pdo->prepare("UPDATE bookings SET review_sent = 'uebersprungen' WHERE id = ?")->execute([$b['id']]); continue; }
        if (time() < $end + 3600) continue;
        $pdo->prepare('UPDATE bookings SET review_sent = ? WHERE id = ?')->execute([date('c'), $b['id']]);
        $key = mb_strtolower(trim((string)$b['email'])) . '#' . $b['start_date'];
        if (isset($seen[$key]) || !filter_var($b['email'], FILTER_VALIDATE_EMAIL)) continue;
        $seen[$key] = true;
        $title = preg_replace('/\s*·.*$/u', '', (string)$b['title']);
        forma_review_send($pdo, (string)$b['email'], (string)$b['name'], $title);
        $sent++;
    }
    try { $sent += forma_review_private_sessions($pdo); } catch (Throwable $e) { error_log('FORMA review private sessions: ' . $e->getMessage()); }
    return $sent;
}

// --- Private Pottery: Termine im Kalender ---------------------------------------

const FORMA_WISH_TIMES = ['vormittag' => 'vormittags', 'nachmittag' => 'nachmittags', 'abend' => 'abends', 'egal' => 'Uhrzeit egal'];
const FORMA_WEEKDAYS_LONG = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
const FORMA_MONTHS = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

/** „Montag, 19. November 2026“ */
function forma_de_date(string $ymd, bool $year = true): string
{
    $t = strtotime($ymd . ' 12:00');
    if (!$t) return $ymd;
    return FORMA_WEEKDAYS_LONG[(int)date('w', $t)] . ', ' . (int)date('j', $t) . '. ' . FORMA_MONTHS[(int)date('n', $t)] . ($year ? ' ' . date('Y', $t) : '');
}

/** „19 Uhr“ oder „19:30 Uhr“ */
function forma_de_time(string $hm): string
{
    [$h, $m] = array_map('intval', explode(':', $hm . ':0'));
    return $m ? sprintf('%d:%02d Uhr', $h, $m) : "$h Uhr";
}

function forma_hours_text(float $h): string
{
    $n = rtrim(rtrim(number_format($h, 1, ',', ''), '0'), ',');
    return $n . ($h == 1.0 ? ' Stunde' : ' Stunden');
}

/** Wunschtermin lesbar: „Donnerstag, 19. November 2026, abends · auch möglich: …“ */
function forma_wish_text(array $b): string
{
    if (str_starts_with(trim((string)($b['wish'] ?? '')), '1. Wunsch:')) return trim((string)$b['wish']);
    $parts = [];
    if (!empty($b['wish_date'])) {
        $parts[] = forma_de_date((string)$b['wish_date']) . (!empty($b['wish_time']) && isset(FORMA_WISH_TIMES[$b['wish_time']]) ? ', ' . FORMA_WISH_TIMES[$b['wish_time']] : '');
    }
    if (trim((string)($b['wish'] ?? '')) !== '') $parts[] = ($parts ? 'auch möglich: ' : '') . trim((string)$b['wish']);
    return implode(' · ', $parts);
}

function forma_private_sessions(PDO $pdo, int $pid): array
{
    $st = $pdo->prepare('SELECT * FROM private_sessions WHERE private_id = ? ORDER BY date, start');
    $st->execute([$pid]);
    return $st->fetchAll();
}

/** Bereits eingeplante Stunden einer Buchung. */
function forma_private_planned(PDO $pdo, int $pid): float
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(hours), 0) FROM private_sessions WHERE private_id = ?');
    $st->execute([$pid]);
    return (float)$st->fetchColumn();
}

/** Kalendereintrag (.ics) mit einem oder mehreren Terminen. */
function forma_ics(array $events): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $utc = new DateTimeZone('UTC');
    $f = fn(DateTime $d) => (clone $d)->setTimezone($utc)->format('Ymd\THis\Z');
    $esc = fn(string $v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $v);
    $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//FORMA Earth & Art//Atelier//DE\r\nMETHOD:PUBLISH\r\n";
    foreach ($events as $e) {
        $from = new DateTime($e['date'] . ' ' . $e['start'], $tz);
        $to = (clone $from)->modify('+' . (int)round($e['hours'] * 60) . ' minutes');
        $out .= "BEGIN:VEVENT\r\nUID:{$e['uid']}@forma-pottery.com\r\nDTSTAMP:" . $f(new DateTime('now', $tz)) . "\r\nDTSTART:" . $f($from) . "\r\nDTEND:" . $f($to) . "\r\n"
            . 'SUMMARY:' . $esc($e['summary']) . "\r\nLOCATION:" . $esc('FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon') . "\r\nDESCRIPTION:" . $esc($e['desc']) . "\r\nEND:VEVENT\r\n";
    }
    return $out . "END:VCALENDAR\r\n";
}

/** Geheimer Schlüssel für Kalender-Links (wird beim ersten Mal zufällig erzeugt, liegt nur in der Datenbank). */
function forma_cal_sig(PDO $pdo, string $kind, int $id): string
{
    $secret = forma_setting($pdo, 'ics_secret');
    if ($secret === null || strlen($secret) < 32) {
        $secret = bin2hex(random_bytes(24));
        forma_set_setting($pdo, 'ics_secret', $secret);
    }
    return substr(hash_hmac('sha256', "$kind:$id", $secret), 0, 24);
}

/** Alle Termine einer Buchung als Liste: kind 'b' = Kursbuchung, 'p' = Private Pottery. */
function forma_booking_events(PDO $pdo, string $kind, int $id): array
{
    $events = [];
    if ($kind === 'p') {
        $st = $pdo->prepare('SELECT * FROM private_bookings WHERE id = ?');
        $st->execute([$id]);
        $b = $st->fetch();
        if (!$b) return [];
        foreach (forma_private_sessions($pdo, $id) as $x) {
            $events[] = ['uid' => 'private-' . $x['id'], 'date' => $x['date'], 'start' => $x['start'], 'hours' => (float)$x['hours'],
                'summary' => str_starts_with((string)$b['project'], 'Teambuilding/Event:') ? 'Teambuilding im Atelier FORMA' : 'Private Pottery im Atelier FORMA',
                'desc' => (str_starts_with((string)$b['project'], 'Teambuilding/Event:') ? preg_replace('/^Teambuilding\/Event:\s*/', '', (string)$b['project']) : 'Dein Projekt: ' . $b['project']) . "\nBei Fragen: " . FORMA_PHONE];
        }
        return $events;
    }
    $st = $pdo->prepare('SELECT b.*, c.title, c.date_text, c.start_date FROM bookings b JOIN courses c ON c.id = b.course_id WHERE b.id = ?');
    $st->execute([$id]);
    $b = $st->fetch();
    if (!$b) return [];
    $title = preg_replace('/\s*·.*$/u', '', (string)$b['title']);
    $summary = $title . ' im Atelier FORMA';
    $desc = 'Deine Buchung bei FORMA Earth & Art. Bitte komm in Kleidung, die schmutzig werden darf.' . "\nBei Fragen: " . FORMA_PHONE;
    $blocks = [];
    if (forma_is_atelier($b)) {
        $x = json_decode((string)($b['extras'] ?? ''), true);
        $slots = [];
        foreach ((is_array($x) ? $x : []) as $it) {
            if (is_array($it) && preg_match('/^slot(\d{1,2})$/', (string)($it['key'] ?? ''), $mm)) $slots[] = (int)$mm[1];
        }
        sort($slots);
        foreach ($slots as $h) {
            $n = count($blocks);
            if ($n && $blocks[$n - 1][1] === $h) $blocks[$n - 1][1] = $h + 1;
            else $blocks[] = [$h, $h + 1];
        }
        $blocks = array_map(fn($bl) => [sprintf('%02d:00', $bl[0]), (float)($bl[1] - $bl[0])], $blocks);
    }
    if (!$blocks && preg_match('/(\d{1,2})(?:[:.](\d{2}))?\s*[–-]\s*(\d{1,2})(?:[:.](\d{2}))?\s*Uhr/u', (string)$b['date_text'], $m)) {
        $from = (int)$m[1] * 60 + (int)($m[2] ?? 0);
        $to = (int)$m[3] * 60 + (int)($m[4] ?? 0);
        if ($to > $from) $blocks[] = [sprintf('%02d:%02d', intdiv($from, 60), $from % 60), ($to - $from) / 60];
    }
    if (!$blocks) return [];
    // Mehrwöchige Kurse (z. B. „Freitags, 6. 11. – 4. 12. 2026“): jede Woche ein Termin bis zum Enddatum
    $dates = [(string)$b['start_date']];
    if (preg_match('/[–-]\s*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/u', (string)$b['date_text'], $e)) {
        $end = sprintf('%04d-%02d-%02d', $e[3], $e[2], $e[1]);
        $d = (string)$b['start_date'];
        while (($d = date('Y-m-d', strtotime($d . ' +7 days'))) <= $end && count($dates) < 20) $dates[] = $d;
    }
    foreach ($dates as $k => $d) {
        foreach ($blocks as $j => [$start, $hours]) {
            $events[] = ['uid' => 'booking-' . $id . '-' . $k . '-' . $j, 'date' => $d, 'start' => $start, 'hours' => $hours,
                'summary' => $summary . (count($dates) > 1 ? ' (' . ($k + 1) . '/' . count($dates) . ')' : ''), 'desc' => $desc];
        }
    }
    return $events;
}

/** Google-Kalender-Link für einen Termin. */
function forma_google_cal_url(array $e): string
{
    $tz = new DateTimeZone('Europe/Vienna');
    $from = new DateTime($e['date'] . ' ' . $e['start'], $tz);
    $to = (clone $from)->modify('+' . (int)round($e['hours'] * 60) . ' minutes');
    $f = fn(DateTime $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE', 'text' => $e['summary'], 'dates' => $f($from) . '/' . $f($to),
        'details' => $e['desc'], 'location' => 'FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon', 'ctz' => 'Europe/Vienna',
    ], '', '&', PHP_QUERY_RFC3986);
}

/** Textblock mit „In deinen Kalender“-Links (wird in der HTML-Mail zu Buttons) und der .ics-Anhang. */
function forma_cal_block(PDO $pdo, string $kind, int $id): array
{
    $events = forma_booking_events($pdo, $kind, $id);
    if (!$events) return ['', []];
    $apple = forma_site_url() . '/api/kalender.php?k=' . $kind . '&id=' . $id . '&s=' . forma_cal_sig($pdo, $kind, $id);
    $txt = "\n" . (count($events) === 1 ? 'Termin mit einem Klick in deinen Kalender übernehmen:' : 'Alle ' . count($events) . ' Termine mit einem Klick in deinen Kalender übernehmen:') . "\n";
    $txt .= 'Apple Kalender (iPhone, iPad, Mac): ' . $apple . "\n";
    $weekly = count($events) > 1;
    foreach ($events as $k => $e) {
        if ($k > 0 && ($e['start'] !== $events[0]['start'] || $e['hours'] != $events[0]['hours'] || $e['date'] !== date('Y-m-d', strtotime($events[$k - 1]['date'] . ' +7 days')))) $weekly = false;
    }
    if (count($events) === 1) {
        $txt .= 'Google Kalender: ' . forma_google_cal_url($events[0]) . "\n";
    } elseif ($weekly) {
        $first = $events[0];
        $first['summary'] = preg_replace('/ \(\d+\/\d+\)$/', '', (string)$first['summary']);
        $txt .= 'Google Kalender: ' . forma_google_cal_url($first) . '&recur=' . rawurlencode('RRULE:FREQ=WEEKLY;COUNT=' . count($events)) . "\n";
    } else {
        foreach ($events as $e) $txt .= 'Google Kalender, ' . forma_de_date((string)$e['date'], false) . ': ' . forma_google_cal_url($e) . "\n";
    }
    $txt .= '(Für andere Kalender-Apps ' . (count($events) === 1 ? 'hängt der Termin' : 'hängen die Termine') . " auch als Datei an dieser E-Mail.)\n";
    return [$txt, [['FORMA-Termin' . (count($events) > 1 ? 'e' : '') . '.ics', forma_ics($events), 'text/calendar']]];
}

/** Eine gesammelte Bestätigung mit allen eingeplanten Private-Pottery-Terminen einer Buchung. */
function forma_private_confirm_mail(PDO $pdo, int $pid, string $note = ''): bool
{
    $st = $pdo->prepare("SELECT * FROM private_bookings WHERE id = ? AND status IN ('bezahlt','anfrage')");
    $st->execute([$pid]);
    $b = $st->fetch();
    $sessions = forma_private_sessions($pdo, $pid);
    if (!$b || !$sessions || !filter_var($b['email'], FILTER_VALIDATE_EMAIL)) return false;
    $first = forma_first_name((string)$b['name']);
    $phone = FORMA_PHONE;
    $one = count($sessions) === 1;
    $planned = forma_private_planned($pdo, $pid);
    $lines = [];
    foreach ($sessions as $k => $x) {
        $lines[] = ($one ? '' : ($k + 1) . '. Termin: ') . forma_de_date((string)$x['date']) . ' um ' . forma_de_time((string)$x['start']) . ' (' . forma_hours_text((float)$x['hours']) . ')';
    }
    $paid = $b['status'] === 'bezahlt';
    $team = str_starts_with((string)$b['project'], 'Teambuilding/Event:');
    $intro = $team
        ? ($one ? 'juhu, euer Teambuilding-Termin steht! Ich freue mich schon sehr auf euch.' : 'juhu, eure Teambuilding-Termine stehen! Hier sind alle ' . count($sessions) . ' Termine auf einen Blick. Ich freue mich schon sehr auf euch.')
        : ($one
        ? "juhu, dein Private-Pottery-Termin steht! Ich freue mich schon sehr auf dich."
        : "juhu, deine Private-Pottery-Termine stehen! Hier sind alle " . count($sessions) . " Termine auf einen Blick, damit du genau weißt, wann wir uns sehen. Ich freue mich schon sehr auf dich.");
    $rest = $paid && !$team ? max(0, (float)$b['hours'] - $planned) : 0;
    [$calTxt, $calAtt] = forma_cal_block($pdo, 'p', $pid);
    $payTxt = '';
    if (!$paid && $planned > 0 && forma_stripe_key() && !str_starts_with((string)$b['project'], 'Teambuilding/Event:')) {
        $payTxt = "\nDamit dein Termin fix für dich reserviert ist, bezahl bitte die " . forma_hours_text($planned) . ' (' . forma_euro((int)round($planned * FORMA_PRIVATE_HOUR)) . ") ganz bequem online, mit Karte, Apple Pay oder Google Pay:\n"
            . forma_private_pay_url($b) . "\n";
    }
    $body = "Hallo $first,\n\n$intro\n\n"
        . ($one ? 'Wann: ' . $lines[0] . "\n" : implode("\n", $lines) . "\n")
        . ($paid ? ($team ? 'Gebucht und bezahlt: ' . preg_replace('/^Teambuilding\/Event:\s*/', '', (string)$b['project']) . "\n" : 'Gebucht und bezahlt: ' . forma_hours_text((float)$b['hours']) . " Private Pottery\n") : '')
        . ($team ? ($paid ? '' : 'Euer Event: ' . preg_replace('/^Teambuilding\/Event:\s*/', '', (string)$b['project']) . "\n") : "Dein Projekt: {$b['project']}\n") . "Wo: FORMA Töpferstudio, Silvrettastraße 58, 6562 Mathon\n"
        . $payTxt
        . ($rest > 0 ? "\nDu hast dann noch " . forma_hours_text($rest) . " offen, die planen wir gemeinsam ein, wann es dir passt.\n" : '')
        . (trim($note) !== '' ? "\n" . trim($note) . "\n" : '')
        . $calTxt
        . "\nBitte komm in Kleidung, die schmutzig werden darf. Schürzen, Werkzeug und Ton habe ich alles im Studio.\n"
        . "\nFalls doch etwas dazwischenkommt: Bis 48 Stunden vorher kannst du kostenlos verschieben oder stornieren. Melde dich einfach, antworte auf diese E-Mail oder ruf mich an: $phone\n\nBis bald im Studio!\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n";
    $unsub = '';
    if (empty($b['no_post'])) try { $unsub = forma_auto_subscribe($pdo, (string)$b['email'], (string)$b['name'], 'Buchung: Private Pottery'); } catch (Throwable $e) { error_log('FORMA auto newsletter: ' . $e->getMessage()); }
    $body .= forma_newsletter_note($unsub);
    $subject = $one ? 'Dein Termin: ' . forma_de_date((string)$sessions[0]['date'], false) . ' um ' . forma_de_time((string)$sessions[0]['start']) : 'Deine ' . count($sessions) . ' Private-Pottery-Termine';
    $ok = forma_mail((string)$b['email'], $subject, $body, null, $calAtt);
    if ($ok) $pdo->prepare('UPDATE private_sessions SET mailed_at = ? WHERE private_id = ?')->execute([date('c'), $pid]);
    return $ok;
}


// --- Private Pottery verlängern ---------------------------------------------------

/** Alle Verlängerungen einer Buchung (ohne zurückgezogene). */
function forma_private_plus_list(PDO $pdo, int $pid): array
{
    $st = $pdo->prepare("SELECT * FROM private_extensions WHERE private_id = ? AND status != 'zurueckgezogen' ORDER BY id");
    $st->execute([$pid]);
    return $st->fetchAll();
}

/** Gibt es eine angebotene, noch nicht bezahlte Verlängerung (der letzten 21 Tage)? Dann wartet die Bewertungs-Mail. */
function forma_private_plus_open(PDO $pdo, int $pid): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM private_extensions WHERE private_id = ? AND status = 'offen' AND created_at >= ?");
    $st->execute([$pid, date('c', time() - 21 * 86400)]);
    return (int)$st->fetchColumn() > 0;
}

function forma_private_plus_url(array $x): string
{
    return forma_site_url() . '/api/private-plus.php?e=' . (int)$x['id'] . '&k=' . rawurlencode((string)$x['token']);
}

/** Mail mit Bezahl-Link für die zusätzlichen Stunden an den Gast. */
function forma_private_plus_mail(PDO $pdo, array $x, bool $again = false): bool
{
    $st = $pdo->prepare('SELECT * FROM private_bookings WHERE id = ?');
    $st->execute([(int)$x['private_id']]);
    $b = $st->fetch();
    if (!$b || !filter_var($b['email'], FILTER_VALIDATE_EMAIL)) return false;
    $first = forma_first_name((string)$b['name']);
    $hours = forma_hours_text((float)$x['hours']);
    $note = trim((string)$x['note']);
    $body = "Hallo $first,\n\n"
        . ($again ? "kleine Erinnerung: " : "wie schön, dass du an deinem Projekt weiterarbeiten möchtest! ")
        . "Ich habe dir $hours Private Pottery zusätzlich freigeschaltet.\n\n"
        . "Zusätzlich: $hours Private Pottery\nBetrag: " . forma_euro((int)$x['amount_cents']) . " (€ 65 pro Stunde, inkl. Ton und Fertigstellung)\n"
        . (trim((string)$b['project']) !== '' ? "Dein Projekt: {$b['project']}\n" : '')
        . ($note !== '' ? "\n$note\n" : '')
        . "\nHier kannst du die Stunden ganz bequem online bezahlen, mit Karte, Apple Pay oder Google Pay:\n"
        . forma_private_plus_url($x) . "\n\n"
        . "Sobald die Zahlung angekommen ist, bekommst du eine Bestätigung und die Rechnung. Danach plane ich die neuen Termine mit dir ein und schicke dir alle Termine gesammelt per E-Mail.\n\n"
        . "Hast du Fragen? Antworte einfach auf diese E-Mail oder ruf mich an: " . FORMA_PHONE . "\n\nBis bald im Studio!\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n";
    return forma_mail((string)$b['email'], ($again ? 'Erinnerung: ' : '') . "Noch $hours Private Pottery: dein Bezahl-Link", $body);
}

/** Vivien schaltet zusätzliche Stunden frei: Eintrag anlegen und Bezahl-Link an den Gast schicken. */
function forma_private_plus_offer(PDO $pdo, int $pid, float $hours, string $note = ''): ?array
{
    $hours = round($hours * 2) / 2;
    if ($hours < 0.5 || $hours > 8) return null;
    $st = $pdo->prepare("SELECT * FROM private_bookings WHERE id = ? AND status = 'bezahlt'");
    $st->execute([$pid]);
    $b = $st->fetch();
    if (!$b || str_starts_with((string)$b['project'], 'Teambuilding/Event:')) return null;
    $amount = (int)round($hours * FORMA_PRIVATE_HOUR);   // Preis immer hier am Server berechnet
    $pdo->prepare('INSERT INTO private_extensions (private_id, hours, amount_cents, note, status, token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$pid, $hours, $amount, trim(mb_substr($note, 0, 800)), 'offen', bin2hex(random_bytes(16)), date('c')]);
    $x = $pdo->query('SELECT * FROM private_extensions WHERE id = ' . (int)$pdo->lastInsertId())->fetch();
    $x['mailed'] = forma_private_plus_mail($pdo, $x);
    return $x;
}

/** Prüft eine Verlängerung bei Stripe. Bezahlt: Stunden gutschreiben, Mails, Rechnung. */
function forma_private_plus_check(PDO $pdo, array $x): string
{
    if ($x['status'] !== 'offen' || $x['stripe_session'] === '') return (string)$x['status'];
    $session = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($x['stripe_session']));
    if (($session['payment_status'] ?? '') === 'paid') {
        $pdo->exec('BEGIN IMMEDIATE');
        $st = $pdo->prepare("UPDATE private_extensions SET status = 'bezahlt', paid_at = ? WHERE id = ? AND status = 'offen'");
        $st->execute([date('c'), (int)$x['id']]);
        $fresh = $st->rowCount() > 0;
        if ($fresh) {
            // Die Stunden kommen erst jetzt zur Buchung dazu: Erst dann lassen sie sich im Kalender einplanen.
            $pdo->prepare('UPDATE private_bookings SET hours = hours + ?, amount_cents = amount_cents + ?, done = 0 WHERE id = ?')
                ->execute([(float)$x['hours'], (int)$x['amount_cents'], (int)$x['private_id']]);
        }
        $pdo->exec('COMMIT');
        if ($fresh) forma_private_plus_paid($pdo, (int)$x['id']);
        return 'bezahlt';
    }
    if (($session['status'] ?? '') === 'expired') {
        // Link bleibt gültig, beim nächsten Klick gibt es eine neue Bezahlseite.
        $pdo->prepare("UPDATE private_extensions SET stripe_session = '' WHERE id = ? AND status = 'offen'")->execute([(int)$x['id']]);
    }
    return 'offen';
}

/** Verlängerung bezahlt: Danke an den Gast, Info an Vivien, Rechnung. */
function forma_private_plus_paid(PDO $pdo, int $xid): void
{
    $st = $pdo->prepare("SELECT x.*, p.name, p.email, p.hours AS total_hours FROM private_extensions x JOIN private_bookings p ON p.id = x.private_id WHERE x.id = ? AND x.status = 'bezahlt'");
    $st->execute([$xid]);
    $x = $st->fetch();
    if (!$x) return;
    $first = forma_first_name((string)$x['name']);
    $hours = forma_hours_text((float)$x['hours']);
    $amount = forma_euro((int)$x['amount_cents']);
    $rest = max(0, (float)$x['total_hours'] - forma_private_planned($pdo, (int)$x['private_id']));
    forma_mail((string)$x['email'], 'Danke, deine zusätzlichen Stunden sind gebucht', "Hallo $first,\n\ndanke! Deine Zahlung für $hours Private Pottery ($amount) ist angekommen, damit sind die zusätzlichen Stunden fix für dich reserviert.\n\n"
        . "Ich melde mich bei dir, damit wir die neuen Termine ausmachen. Danach bekommst du alle Termine gesammelt per E-Mail, mit Kalendereintrag. Die Rechnung kommt in einer eigenen E-Mail.\n\n"
        . "Ich freue mich, dass wir dein Projekt gemeinsam weitermachen!\n\nBis bald im Studio!\nVivien\nFORMA Earth & Art\n" . FORMA_PHONE . "\nwww.forma-pottery.com\n");
    forma_mail(FORMA_NOTIFY_EMAIL, 'Verlängerung bezahlt: ' . $hours . ' Private Pottery (' . $x['name'] . ')',
        "{$x['name']} hat die Verlängerung bezahlt.\n\n$hours · $amount über Stripe\nNoch nicht eingeplant: " . forma_hours_text($rest) . "\n\nJetzt im Kalender einplanen und danach die Bestätigung schicken:\n"
        . forma_site_url() . '/verwaltung/?view=kalender&assign=' . (int)$x['private_id'] . "\n", (string)$x['email']);
    forma_invoice_for($pdo, 'private_plus', $xid);
}

/** Bewertungs-Mail ca. 1 Stunde nach dem letzten eingeplanten Private-Pottery-Termin. */
function forma_review_private_sessions(PDO $pdo): int
{
    $rows = $pdo->query("SELECT p.id, p.hours, p.status, MAX(s.date || ' ' || s.start) AS last_start
        FROM private_bookings p JOIN private_sessions s ON s.private_id = p.id
        WHERE p.review_sent IS NULL GROUP BY p.id")->fetchAll();
    $sent = 0;
    foreach ($rows as $r) {
        $planned = forma_private_planned($pdo, (int)$r['id']);
        if ($r['status'] === 'bezahlt' && $planned + 0.01 < (float)$r['hours']) continue; // es fehlen noch Stunden
        if (forma_private_plus_open($pdo, (int)$r['id'])) continue; // Verlängerung angeboten, aber noch nicht bezahlt
        $st = $pdo->prepare("SELECT hours FROM private_sessions WHERE private_id = ? AND (date || ' ' || start) = ?");
        $st->execute([$r['id'], $r['last_start']]);
        $end = strtotime((string)$r['last_start']) + (int)round((float)$st->fetchColumn() * 3600);
        if (time() < $end + 3600 || time() > $end + 3 * 86400) continue;
        $pdo->prepare('UPDATE private_bookings SET done = 1 WHERE id = ?')->execute([$r['id']]);
        if (forma_review_private($pdo, (int)$r['id'])) $sent++;
    }
    return $sent;
}

/** Private Pottery: Bewertungs-Mail, sobald der Termin in der Verwaltung als erledigt markiert wird. */
function forma_review_private(PDO $pdo, int $id): bool
{
    if (forma_setting($pdo, 'review_off') === '1') return false;
    $stmt = $pdo->prepare('SELECT * FROM private_bookings WHERE id = ? AND done = 1 AND review_sent IS NULL');
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b || !filter_var($b['email'], FILTER_VALIDATE_EMAIL)) return false;
    $pdo->prepare('UPDATE private_bookings SET review_sent = ? WHERE id = ?')->execute([date('c'), $id]);
    forma_review_send($pdo, (string)$b['email'], (string)$b['name'], str_starts_with((string)$b['project'], 'Teambuilding/Event:') ? 'Teambuilding' : 'Private Pottery');
    return true;
}

/** Zusätzliche Shop-Slides (Blick in die Anleitung und die Schablonen): img/shop/<key>-2.jpg, -3.jpg */
function forma_template_slides(string $file): array
{
    if (!preg_match('/^seed-(.+)-de\.pdf$/', $file, $m)) return [];
    $out = [];
    foreach ([2, 3] as $n) {
        $f = "img/shop/{$m[1]}-$n.jpg";
        if (is_file(__DIR__ . '/../' . $f)) $out[] = $f . '?v=' . filemtime(__DIR__ . '/../' . $f);
    }
    return $out;
}

require_once __DIR__ . '/invoices.php';
require_once __DIR__ . '/minimum.php';

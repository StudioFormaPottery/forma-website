<?php
// Movecia Mindful Movement Method (On-Demand-Mitgliedschaft) und Unfold – The Mindful Becoming.
// Eigenes Modul (Vivien, 25.10.2026): Mitgliedschaften über Stripe, Konten, Bibliothek mit Monatsprogrammen,
// Videos bei Bunny Stream, Fortschritt, Journal, Workbooks, Rechnungen im Movecia-Design.
// Schlüssel (Stripe, Bunny, Postfach) liegen nur im Datenordner, nie im Repo.
declare(strict_types=1);
require_once __DIR__ . '/movecia-lib.php';

const MV_COOKIE = 'mvx';
const MV_HOLD = 40 * 60;          // so lange hält eine offene Unfold-Zahlung einen Platz
const MV_CATS = [
    'flow' => ['Movecia Movement', 'Movecia Movement'],
    'somatic' => ['Somatic Movement', 'Somatic Movement'],
    'breath' => ['Breathwork', 'Breathwork'],
    'meditation' => ['Meditation', 'Meditation'],
    'body' => ['Bodyweight ohne Erschöpfung', 'Bodyweight without Exhaustion'],
    'talk' => ['Impuls', 'Talk'],
];
const MV_ENERGY = [
    'any' => ['Für jeden Tag', 'Any day'],
    'tired' => ['Tired Day', 'Tired Day'],
    'full' => ['Full Day', 'Full Day'],
];

// --- Einstellungen ------------------------------------------------------------------------------

/**
 * Preise und Termine, so wie sie gespeichert sind (für die Verwaltung).
 * Vivien 25.10.2026: Early-Bird bis zum Start, danach regulär.
 * Method: bis inkl. 1.12.2026 € 29 / 3 Monate € 75 / Jahr € 240, danach € 39 / € 111 (= € 37 im Monat) / € 396 (= € 33 im Monat).
 * Unfold (Vivien, 30.10.2026 abends): Early-Bird bis inkl. 20.12.2026 € 450 oder 3 × € 165, ab 21.12.2026 € 550 oder 3 × € 200.
 * Wer zum Early-Bird-Preis bucht, behält ihn, solange das Abo läuft (Preis hängt fest am Stripe-Abo).
 */
function mv_cfg_raw(): array
{
    $def = [
        'launch' => '2026-12-01',
        'm1' => 3900, 'm3' => 11100, 'm12' => 39600,                // Method regulär (ab 2.12.2026)
        'eb_m1' => 2900, 'eb_m3' => 7500, 'eb_m12' => 24000,        // Method Early-Bird
        'eb_m_until' => '2026-12-01',
        'g_m1' => 1900, 'g_m3' => 6500, 'g_m12' => 19900,           // Method im Gathering (erste Zahlung), Early-Bird-Phase
        'u_once' => 55000, 'u_inst' => 20000, 'u_n' => 3, 'g_u_once' => 39000,   // Unfold regulär (ab 21.12.2026)
        'eb_u_once' => 45000, 'eb_u_inst' => 16500, 'eb_u_until' => '2026-12-20',
        'u_seats' => 10, 'u_start' => '2027-01-10', 'u_end' => '2027-04-11', 'u_open' => 1,
        'u_call_de' => 'Sonntag, 19:00 Uhr', 'u_call_en' => 'Sunday, 8:15 pm (Vienna time)',
    ];
    $c = mm_cfg('method') ?? [];
    // Vivien 25.10.2026: Raten sichtbar teurer (3 × € 165 statt 3 × € 160), alter gespeicherter Wert wird einmal angehoben
    if ((int)($c['u_inst'] ?? 0) === 16000 && empty($c['inst_v2'])) { $c['u_inst'] = 16500; $c['inst_v2'] = 1; mm_save_cfg('method', $c); }
    // Vivien 25.10.2026 abends: gespeicherte alte Preise werden zu Early-Bird, die neuen regulären Preise gelten danach
    if (empty($c['price_v3'])) {
        foreach (['m1', 'm3', 'm12', 'u_once', 'u_inst'] as $k) unset($c[$k]);
        $c['price_v3'] = 1;
        mm_save_cfg('method', $c);
    }
    // Vivien 30.10.2026: Unfold Early-Bird nur noch bis 29.12.2026, danach € 550 (alte gespeicherte Werte werden einmal ersetzt)
    if (empty($c['price_v4'])) {
        foreach (['u_once', 'u_inst', 'eb_u_until'] as $k) unset($c[$k]);
        $c['price_v4'] = 1;
        mm_save_cfg('method', $c);
    }
    // Vivien 30.10.2026 abends: Unfold Early-Bird nur bis 20.12.2026 (gespeichertes Datum wird einmal ersetzt)
    if (empty($c['price_v5'])) {
        unset($c['eb_u_until']);
        $c['price_v5'] = 1;
        mm_save_cfg('method', $c);
    }
    foreach ($def as $k => $v) $def[$k] = is_int($v) ? (int)($c[$k] ?? $v) : (string)($c[$k] ?? $v);
    return $def;
}

/** Gilt gerade der Early-Bird-Preis? $what: method oder unfold. Bis 23:59 des letzten Tages (Wien). */
function mv_eb(string $what, ?array $c = null): bool
{
    $c = $c ?? mv_cfg_raw();
    $until = $what === 'unfold' ? $c['eb_u_until'] : $c['eb_m_until'];
    return $until !== '' && time() <= mv_ts($until, '23:59');
}

/**
 * Preise, die gerade gelten. m1 … u_inst sind die aktuellen Preise, r_* die regulären (für „statt“-Angaben),
 * eb_m / eb_u ob gerade Early-Bird ist, eb_m_end / eb_u_end das Ende als Zeitstempel.
 */
function mv_cfg(): array
{
    $c = mv_cfg_raw();
    $em = mv_eb('method', $c);
    $eu = mv_eb('unfold', $c);
    foreach (['m1', 'm3', 'm12', 'u_once', 'u_inst'] as $k) $c['r_' . $k] = $c[$k];
    if ($em) { $c['m1'] = $c['eb_m1']; $c['m3'] = $c['eb_m3']; $c['m12'] = $c['eb_m12']; }
    else {
        // Nach dem Early-Bird: im Gathering gibt es für die erste Zahlung wieder den Early-Bird-Preis
        $c['g_m1'] = $c['eb_m1']; $c['g_m3'] = $c['eb_m3']; $c['g_m12'] = $c['eb_m12'];
    }
    if ($eu) { $c['u_once'] = $c['eb_u_once']; $c['u_inst'] = $c['eb_u_inst']; }
    $c['eb_m'] = $em; $c['eb_u'] = $eu;
    $c['eb_m_end'] = mv_ts($c['eb_m_until'], '23:59');
    $c['eb_u_end'] = mv_ts($c['eb_u_until'], '23:59');
    return $c;
}

function mv_save_cfg(array $in): void
{
    mm_save_cfg('method', array_merge(mm_cfg('method') ?? [], $in));
}

function mv_secret(): string
{
    $f = forma_data_dir() . '/movecia-method-secret';
    $v = is_file($f) ? trim((string)file_get_contents($f)) : '';
    if (strlen($v) < 32) { $v = bin2hex(random_bytes(24)); @file_put_contents($f, $v); @chmod($f, 0600); }
    return $v;
}

function mv_ts(string $date, string $time = '09:00'): int
{
    return (new DateTimeImmutable($date . ' ' . $time, new DateTimeZone('Europe/Vienna')))->getTimestamp();
}

function mv_launch(): int { return mv_ts(mv_cfg()['launch']); }
function mv_launched(): bool { return time() >= mv_launch(); }

function mv_eur(int $c): string
{
    return '€ ' . ($c % 100 === 0 ? number_format($c / 100, 0, ',', '.') : number_format($c / 100, 2, ',', '.'));
}

function mv_date(int $t, string $lang = 'de'): string
{
    $d = (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('Europe/Vienna'));
    if ($lang === 'en') return $d->format('j F Y');
    $m = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    return $d->format('j') . '. ' . $m[(int)$d->format('n')] . ' ' . $d->format('Y');
}

/** Pläne mit Anzeigetext. „g“ = Gathering-Preis für die erste Zahlung. */
function mv_plans(): array
{
    $c = mv_cfg();
    return [
        'm1' => ['kind' => 'method', 'cents' => $c['m1'], 'g' => $c['g_m1'], 'months' => 1, 'de' => 'monatlich', 'en' => 'monthly', 'per_de' => 'pro Monat', 'per_en' => 'per month'],
        'm3' => ['kind' => 'method', 'cents' => $c['m3'], 'g' => $c['g_m3'], 'months' => 3, 'de' => '3 Monate', 'en' => '3 months', 'per_de' => 'alle 3 Monate', 'per_en' => 'every 3 months'],
        'm12' => ['kind' => 'method', 'cents' => $c['m12'], 'g' => $c['g_m12'], 'months' => 12, 'de' => '1 Jahr', 'en' => '1 year', 'per_de' => 'pro Jahr', 'per_en' => 'per year'],
        'u1' => ['kind' => 'unfold', 'cents' => $c['u_once'], 'g' => $c['g_u_once'], 'months' => 0, 'de' => 'auf einmal', 'en' => 'in full', 'per_de' => 'einmalig', 'per_en' => 'once'],
        'u3' => ['kind' => 'unfold', 'cents' => $c['u_inst'], 'g' => 0, 'months' => 1, 'de' => $c['u_n'] . ' Raten', 'en' => $c['u_n'] . ' payments', 'per_de' => 'pro Monat, ' . $c['u_n'] . ' ×', 'per_en' => 'per month, ' . $c['u_n'] . ' ×'],
    ];
}

// --- Datenbank ------------------------------------------------------------------------------------

function mv_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL DEFAULT \'\',
        pass_hash TEXT NOT NULL DEFAULT \'\',
        lang TEXT NOT NULL DEFAULT \'de\',
        reset_token TEXT NOT NULL DEFAULT \'\',
        reset_until TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL,
        last_login TEXT
    )');
    // Eine Zeile pro Kauf (Method-Abo oder Unfold)
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_subs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL,
        plan TEXT NOT NULL,
        email TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL DEFAULT \'\',
        lang TEXT NOT NULL DEFAULT \'de\',
        call_lang TEXT NOT NULL DEFAULT \'\',
        amount_cents INTEGER NOT NULL,
        first_cents INTEGER NOT NULL,
        special INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT \'offen\',
        token TEXT NOT NULL,
        stripe_session TEXT NOT NULL DEFAULT \'\',
        sub_id TEXT NOT NULL DEFAULT \'\',
        customer TEXT NOT NULL DEFAULT \'\',
        period_end INTEGER NOT NULL DEFAULT 0,
        cancel_end INTEGER NOT NULL DEFAULT 0,
        bill_start INTEGER NOT NULL DEFAULT 0,
        consent_digital INTEGER NOT NULL DEFAULT 0,
        consent_live INTEGER NOT NULL DEFAULT 0,
        address TEXT NOT NULL DEFAULT \'\',
        country TEXT NOT NULL DEFAULT \'AT\',
        source TEXT NOT NULL DEFAULT \'web\',
        test INTEGER NOT NULL DEFAULT 0,
        synced_at INTEGER NOT NULL DEFAULT 0,
        mailed INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        paid_at TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sub_row INTEGER NOT NULL,
        ref TEXT NOT NULL UNIQUE,
        cents INTEGER NOT NULL,
        period_start INTEGER NOT NULL DEFAULT 0,
        period_end INTEGER NOT NULL DEFAULT 0,
        paid_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_programs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        audience TEXT NOT NULL DEFAULT \'method\',
        title_de TEXT NOT NULL,
        title_en TEXT NOT NULL DEFAULT \'\',
        intro_de TEXT NOT NULL DEFAULT \'\',
        intro_en TEXT NOT NULL DEFAULT \'\',
        theme TEXT NOT NULL DEFAULT \'\',
        unlock TEXT NOT NULL DEFAULT \'\',
        sort INTEGER NOT NULL DEFAULT 0,
        visible INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_lessons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        program_id INTEGER NOT NULL,
        title_de TEXT NOT NULL,
        title_en TEXT NOT NULL DEFAULT \'\',
        text_de TEXT NOT NULL DEFAULT \'\',
        text_en TEXT NOT NULL DEFAULT \'\',
        cat TEXT NOT NULL DEFAULT \'flow\',
        energy TEXT NOT NULL DEFAULT \'any\',
        minutes INTEGER NOT NULL DEFAULT 0,
        video_de TEXT NOT NULL DEFAULT \'\',
        video_en TEXT NOT NULL DEFAULT \'\',
        sort INTEGER NOT NULL DEFAULT 0,
        visible INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        program_id INTEGER NOT NULL,
        label_de TEXT NOT NULL,
        label_en TEXT NOT NULL DEFAULT \'\',
        lang TEXT NOT NULL DEFAULT \'both\',
        file TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_progress (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER NOT NULL,
        lesson_id INTEGER NOT NULL,
        done_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_favs (member_id INTEGER NOT NULL, lesson_id INTEGER NOT NULL, PRIMARY KEY (member_id, lesson_id))');
    // Journal: nur die Frau selbst sieht ihre Einträge (verschlüsselt gespeichert, nicht in der Verwaltung)
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_journal (
        member_id INTEGER NOT NULL,
        day TEXT NOT NULL,
        body TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        PRIMARY KEY (member_id, day)
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mv_login_fail (ip_hash TEXT NOT NULL, at TEXT NOT NULL)');
    // Sprechtexte (Voice-over/Talking Head) je Lektion für den Teleprompter
    $lc = array_column($pdo->query('PRAGMA table_info(mv_lessons)')->fetchAll(), 'name');
    if (!in_array('script_de', $lc, true)) { $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN script_de TEXT NOT NULL DEFAULT ''"); $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN script_en TEXT NOT NULL DEFAULT ''"); }
    // eigenes Freischaltdatum pro Lektion (Unfold: zwei Bewegungsvideos pro Woche), leer = mit dem Programm
    if (!in_array('unlock', $lc, true)) $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN unlock TEXT NOT NULL DEFAULT ''");
    // Produktion (Vivien, 26.10.2026): fester Schlüssel für angelegte Einträge, „fertig bis“, Übungsliste, Häkchen
    if (!in_array('ukey', $lc, true)) {
        $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN ukey TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN due TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN ex TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN prod TEXT NOT NULL DEFAULT ''");
    }
    // Flow & Mediathek (Vivien, 26.10.2026): Schritte je Video (Übung, Voice-over-Text DE/EN, Sekunden), Art flow/move
    if (!in_array('steps', $lc, true)) {
        $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN steps TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN mode TEXT NOT NULL DEFAULT ''");
    }
    // Musik darunter je Video (Vivien, 30.10.2026: „pro Video ein oder zwei Lieder, nicht pro Clip“): '' = automatisch, 'off' = keine, sonst Lied-IDs „3,7“
    if (!in_array('bgm', $lc, true)) $pdo->exec("ALTER TABLE mv_lessons ADD COLUMN bgm TEXT NOT NULL DEFAULT ''");
    // Voice-over-Bibliothek (Vivien, 26.10.2026): jede eingesprochene Übung bleibt mit Text gespeichert und wird in
    // neuen Flows wiederverwendet, sobald dort derselbe Text zur selben Übung steht (text_hash)
    $pdo->exec("CREATE TABLE IF NOT EXISTS mv_voices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ckey TEXT NOT NULL,
        lang TEXT NOT NULL,
        text_hash TEXT NOT NULL,
        text TEXT NOT NULL DEFAULT '',
        file TEXT NOT NULL,
        lesson_id INTEGER NOT NULL DEFAULT 0,
        step INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS mv_voices_k ON mv_voices (ckey, lang, text_hash)');
    // Mediathek: jede Übung genau einmal gefilmt, überall wiederverwendet (nach Jahreszeit und Stil sortiert)
    $pdo->exec("CREATE TABLE IF NOT EXISTS mv_clips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ckey TEXT NOT NULL UNIQUE,
        name_de TEXT NOT NULL,
        name_en TEXT NOT NULL DEFAULT '',
        guid TEXT NOT NULL DEFAULT '',
        seasons TEXT NOT NULL DEFAULT '',
        styles TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT ''
    )");
    // Vorab-Buchungen (Vivien, 25.10.2026): Mail „dein Zugang ist offen“ am Starttag genau einmal
    $sc = array_column($pdo->query('PRAGMA table_info(mv_subs)')->fetchAll(), 'name');
    if (!in_array('open_mailed', $sc, true)) $pdo->exec('ALTER TABLE mv_subs ADD COLUMN open_mailed INTEGER NOT NULL DEFAULT 0');
    // Google-Bewertung nach dem Ende von Unfold (Vivien, 25.10.2026)
    if (!in_array('review_sent', $sc, true)) $pdo->exec('ALTER TABLE mv_subs ADD COLUMN review_sent INTEGER NOT NULL DEFAULT 0');
    // Testzugänge: eigener Startzeitpunkt (Vivien, 25.10.2026)
    if (!in_array('open_from', $sc, true)) $pdo->exec('ALTER TABLE mv_subs ADD COLUMN open_from INTEGER NOT NULL DEFAULT 0');
    // Monat für Monat ab dem eigenen Starttag (Vivien, 25.10.2026): bis zu welchem Monat die Mail schon raus ist
    if (!in_array('month_mailed', $sc, true)) $pdo->exec('ALTER TABLE mv_subs ADD COLUMN month_mailed INTEGER NOT NULL DEFAULT 1');
    // E-Mail zuerst bestätigen (Vivien, 25.10.2026): erst danach Willkommensmail und alle Infos
    $mc = array_column($pdo->query('PRAGMA table_info(mv_members)')->fetchAll(), 'name');
    if (!in_array('email_ok', $mc, true)) {
        $pdo->exec('ALTER TABLE mv_members ADD COLUMN email_ok INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE mv_members ADD COLUMN confirm_sent INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE mv_members ADD COLUMN confirm_reminded INTEGER NOT NULL DEFAULT 0');
        // Wer schon ein Passwort hat oder schon eine Mail bekommen hat, gilt als bestätigt
        $pdo->exec("UPDATE mv_members SET email_ok = 1 WHERE pass_hash <> '' OR lower(email) IN (SELECT lower(email) FROM mv_subs WHERE mailed = 1)");
    }
    // Programme ohne Monatsnamen (Vivien, 25.10.2026): jede startet bei Monat 1, egal in welchem Kalendermonat
    if (empty((mm_cfg('method') ?? [])['names_v1'])) {
        $months = 'Jänner|Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember|January|February|March|May|June|July|October|December';
        foreach ($pdo->query("SELECT id, title_de, title_en, intro_de, intro_en FROM mv_programs WHERE audience = 'method'")->fetchAll() as $p) {
            $tde = preg_replace('/^(' . $months . ')\s*·\s*/u', '', (string)$p['title_de']);
            $ten = preg_replace('/^(' . $months . ')\s*·\s*/u', '', (string)$p['title_en']);
            $ide = str_replace('Der Dezember ist laut und voll.', 'Manche Wochen sind laut und voll.', (string)$p['intro_de']);
            $ien = str_replace('December is loud and full.', 'Some weeks are loud and full.', (string)$p['intro_en']);
            $pdo->prepare('UPDATE mv_programs SET title_de = ?, title_en = ?, intro_de = ?, intro_en = ? WHERE id = ?')->execute([$tde, $ten, $ide, $ien, $p['id']]);
        }
        mv_save_cfg(['names_v1' => 1]);
    }
    mv_scripts_sync($pdo);
    mv_prod_sync($pdo);
    mv_voice_migrate($pdo);
    mv_lektorat_sync($pdo);
    mv_nopilates_migrate($pdo);
    mv_workbooks_sync($pdo);
}

/** Kein „Pilates“ bei Movecia (Vivien, 28.10.2026): dieselben Ersetzungen wie im Repo, einmal auch auf die gespeicherten Daten. */
function mv_nopilates_map(): array
{
    return [
        ' (Pilates)' => '',
        'die Clamshell aus dem Pilates' => 'die Clamshell',
        'the clamshell from Pilates' => 'the clamshell',
        'Ich unterrichte Yoga, Pilates und achtsame Bewegung' => 'Ich unterrichte Yoga, Bodyweight-Training und achtsame Bewegung',
        'I teach yoga, Pilates and mindful movement' => 'I teach yoga, bodyweight training and mindful movement',
        'weitere Ausbildungen, Pilates, somatische Arbeit' => 'weitere Ausbildungen, Bodyweight-Training, somatische Arbeit',
        'More courses. Pilates. Somatic work' => 'More courses. Bodyweight training. Somatic work',
        'More trainings. Pilates. Somatic work' => 'More trainings. Bodyweight training. Somatic work',
        'Stunden Yoga, Pilates, Rehabilitation' => 'Stunden Yoga, Bodyweight-Training, Rehabilitation',
        'Ausbildung gemacht. Yoga, Pilates, Rehabilitation' => 'Ausbildung gemacht. Yoga, Bodyweight-Training, Rehabilitation',
        'hours of yoga, Pilates, rehab' => 'hours of yoga, bodyweight training, rehab',
        'hours of training. Yoga, Pilates, rehab' => 'hours of training. Yoga, bodyweight training, rehab',
        'Übungen aus Yoga, Pilates und Somatics' => 'Übungen aus Yoga, Bodyweight und Somatics',
        'exercises from yoga, Pilates and somatics' => 'exercises from yoga, bodyweight training and somatics',
        'Yoga, Pilates, Somatic Movement und Breathwork' => 'Yoga, Bodyweight-Training, Somatic Movement und Breathwork',
        'Kurze Videos aus Yoga, Pilates und Somatic Movement' => 'Kurze Videos aus Yoga, Bodyweight und Somatic Movement',
        'Yoga und Pilates in der Gruppe' => 'Yoga und Bodyweight-Training in der Gruppe',
        'Yoga, Pilates and gentle movement' => 'Yoga, bodyweight training and gentle movement',
        'Yoga, Pilates und sanfte Bewegung' => 'Yoga, Bodyweight-Training und sanfte Bewegung',
        'yoga, Pilates, forest walks' => 'yoga, bodyweight training, forest walks',
        'Movecia – Yoga, Pilates & Mindful Movement' => 'Movecia – Yoga, Bodyweight & Mindful Movement',
        'Personal Trainerin für Yoga, Pilates und prä-/postnatales Training' => 'Personal Trainerin für Yoga, Bodyweight-Training und prä-/postnatales Training',
        '\'Pränatal Pilates\', ' => '',
        'YOGA · PILATES · MINDFUL MOVEMENT' => 'YOGA · BODYWEIGHT · MINDFUL MOVEMENT',
        'Yoga · Pilates · Mindful Movement' => 'Yoga · Bodyweight · Mindful Movement',
        '#pilatesonline' => '#bodyweighttraining',
        'Yoga/Pilates/Mindful Movement' => 'Yoga/Bodyweight/Mindful Movement'
    ];
}

function mv_nopilates_str($v)
{
    if (is_string($v)) return strtr($v, mv_nopilates_map());
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = mv_nopilates_str($x); }
    return $v;
}

/** Text oder JSON-Text: JSON wird ausgepackt, ersetzt und genauso wieder eingepackt (Umlaute bleiben lesbar). */
function mv_nopilates_field(string $s): string
{
    if ($s === '' || stripos($s, 'pilates') === false) return $s;
    $t = ltrim($s);
    if ($t !== '' && ($t[0] === '[' || $t[0] === '{')) {
        $j = json_decode($s, true);
        if (is_array($j)) return json_encode(mv_nopilates_str($j), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return mv_nopilates_str($s);
}

function mv_nopilates_migrate(PDO $pdo): void
{
    if (!empty((mm_cfg('method') ?? [])['nopilates_v1'])) return;
    try {
        $pdo->beginTransaction();
        $cols = ['title_de', 'title_en', 'text_de', 'text_en', 'script_de', 'script_en', 'ex', 'steps'];
        foreach ($pdo->query('SELECT id, ' . implode(', ', $cols) . ' FROM mv_lessons')->fetchAll() as $r) {
            $set = []; $val = [];
            foreach ($cols as $c) { $n = mv_nopilates_field((string)$r[$c]); if ($n !== (string)$r[$c]) { $set[] = "$c = ?"; $val[] = $n; } }
            if ($set) { $val[] = $r['id']; $pdo->prepare('UPDATE mv_lessons SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($val); }
        }
        foreach ($pdo->query('SELECT id, title_de, title_en, intro_de, intro_en FROM mv_programs')->fetchAll() as $r) {
            $n = array_map(fn($x) => mv_nopilates_field((string)$x), [$r['title_de'], $r['title_en'], $r['intro_de'], $r['intro_en']]);
            if ($n !== [$r['title_de'], $r['title_en'], $r['intro_de'], $r['intro_en']]) $pdo->prepare('UPDATE mv_programs SET title_de = ?, title_en = ?, intro_de = ?, intro_en = ? WHERE id = ?')->execute([...$n, $r['id']]);
        }
        // Mediathek: Name ändert sich → neuer Schlüssel; gibt es ihn schon, bleibt der Clip mit Video
        foreach ($pdo->query('SELECT * FROM mv_clips')->fetchAll() as $c) {
            $de = mv_nopilates_str((string)$c['name_de']); $en = mv_nopilates_str((string)$c['name_en']);
            if ($de === $c['name_de'] && $en === $c['name_en']) continue;
            $nk = mv_clip_key($de);
            $ex = $pdo->prepare('SELECT * FROM mv_clips WHERE ckey = ? AND id <> ?'); $ex->execute([$nk, $c['id']]); $o = $ex->fetch();
            if ($o) {
                if ($o['guid'] === '' && $c['guid'] !== '') $pdo->prepare('UPDATE mv_clips SET guid = ? WHERE id = ?')->execute([$c['guid'], $o['id']]);
                $pdo->prepare('DELETE FROM mv_clips WHERE id = ?')->execute([$c['id']]);
            } else {
                $pdo->prepare('UPDATE mv_clips SET ckey = ?, name_de = ?, name_en = ? WHERE id = ?')->execute([$nk, $de, $en, $c['id']]);
            }
            $pdo->prepare('UPDATE mv_voices SET ckey = ? WHERE ckey = ?')->execute([$nk, $c['ckey']]);
        }
        foreach ($pdo->query('SELECT id, ckey, text FROM mv_voices')->fetchAll() as $v) {
            $t = mv_nopilates_str((string)$v['text']);
            $k = (string)$v['ckey'];
            if (str_contains($k, '-pilates')) $k = trim(str_replace('-pilates', '', $k), '-');
            if ($t !== $v['text'] || $k !== $v['ckey']) $pdo->prepare('UPDATE mv_voices SET ckey = ?, text = ?, text_hash = ? WHERE id = ?')->execute([$k, $t, mv_text_hash($t), $v['id']]);
        }
        $gc = array_column($pdo->query('PRAGMA table_info(mm_gatherings)')->fetchAll(), 'name');
        if (in_array('script', $gc, true)) {
            foreach ($pdo->query('SELECT id, title, description AS descr, script FROM mm_gatherings')->fetchAll() as $g) {
                $n = array_map(fn($x) => mv_nopilates_field((string)$x), [$g['title'], $g['descr'], $g['script']]);
                if ($n !== [$g['title'], $g['descr'], $g['script']]) $pdo->prepare('UPDATE mm_gatherings SET title = ?, description = ?, script = ? WHERE id = ?')->execute([...$n, $g['id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('nopilates: ' . $e->getMessage()); return; }
    // Teleprompter-Dateien im Datenordner
    foreach (['movecia-prompter.json', 'movecia-natal-prompter.json', 'movecia-uebung.json'] as $f) {
        $p = forma_data_dir() . '/' . $f;
        if (!is_file($p)) continue;
        $s = (string)file_get_contents($p);
        $n = mv_nopilates_field($s);
        if ($n !== $s) file_put_contents($p, $n, LOCK_EX);
    }
    mv_save_cfg(['nopilates_v1' => 1]);
}

/**
 * Sprach-Lektorat (Vivien, 27.10.2026: „Solche Fehler müssen definitiv vermieden werden“, nie wörtlich übersetzen).
 * api/seed/lektorat-2026-10-27.json (gebaut von tools/lektorat/build_map.py) = alter Seed-Text → korrigierter Text.
 * Ersetzt nur Felder, die noch genau den alten Text enthalten (eigene Änderungen bleiben), Voice-over-Texte nur,
 * wenn für diese Übung noch keine eigene Aufnahme existiert. Läuft einmal (mm_cfg('lektorat')).
 */
function mv_lektorat_sync(PDO $pdo): void
{
    $c = mm_cfg('lektorat') ?? [];
    $j = json_decode((string)@file_get_contents(__DIR__ . '/seed/lektorat-2026-10-27.json'), true);
    if (!$j || (int)($c['v'] ?? 0) >= (int)$j['v']) return;
    if ((int)$pdo->query('SELECT COUNT(*) FROM mv_lessons')->fetchColumn() === 0) return;
    $map = $j['map'] ?? [];
    $names = $j['names'] ?? [];
    $fix = fn($v) => is_string($v) && isset($map[$v]) ? $map[$v] : $v;
    $n = 0;
    $u = $pdo->prepare('UPDATE mv_lessons SET title_en = ?, text_de = ?, text_en = ?, script_de = ?, script_en = ?, steps = ?, ex = ? WHERE id = ?');
    foreach ($pdo->query('SELECT * FROM mv_lessons')->fetchAll() as $l) {
        $row = [$fix($l['title_en']), $fix($l['text_de']), $fix($l['text_en']), $fix($l['script_de']), $fix($l['script_en'])];
        $st = json_decode((string)($l['steps'] ?? ''), true);
        $stOut = (string)($l['steps'] ?? '');
        $exOut = (string)($l['ex'] ?? '');
        if (is_array($st) && $st) {
            foreach ($st as $i => &$x) {
                if (!is_array($x)) continue;
                if (isset($names[$x[0] ?? ''])) $x[0] = $names[$x[0]];
                if (isset($names[$x[1] ?? ''])) $x[1] = $names[$x[1]];
                if (!glob(mv_voice_dir() . '/L' . (int)$l['id'] . '-' . $i . '-de.*')) $x[2] = $fix($x[2] ?? '');
                if (!glob(mv_voice_dir() . '/L' . (int)$l['id'] . '-' . $i . '-en.*')) $x[3] = $fix($x[3] ?? '');
            }
            unset($x);
            $stOut = json_encode($st, JSON_UNESCAPED_UNICODE);
            $exOut = json_encode(array_map(fn($x) => [$x[0], $x[1]], $st), JSON_UNESCAPED_UNICODE);
        }
        $old = [$l['title_en'], $l['text_de'], $l['text_en'], $l['script_de'], $l['script_en'], (string)($l['steps'] ?? ''), (string)($l['ex'] ?? '')];
        $neu = [...$row, $stOut, $exOut];
        if ($neu !== $old) { $u->execute([...$neu, $l['id']]); $n++; }
    }
    $u = $pdo->prepare('UPDATE mv_programs SET title_en = ?, intro_de = ?, intro_en = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id, title_en, intro_de, intro_en FROM mv_programs')->fetchAll() as $p) {
        $neu = [$fix($p['title_en']), $fix($p['intro_de']), $fix($p['intro_en'])];
        if ($neu !== [$p['title_en'], $p['intro_de'], $p['intro_en']]) { $u->execute([...$neu, $p['id']]); $n++; }
    }
    if ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'mm_gatherings'")->fetchColumn()) {
        $u = $pdo->prepare('UPDATE mm_gatherings SET description = ?, script = ? WHERE id = ?');
        foreach ($pdo->query('SELECT id, description, script FROM mm_gatherings')->fetchAll() as $g) {
            $neu = [$fix($g['description']), $fix($g['script'])];
            if ($neu !== [$g['description'], $g['script']]) { $u->execute([...$neu, $g['id']]); $n++; }
        }
    }
    $c['v'] = (int)$j['v'];
    $c['n'] = $n;
    $c['at'] = date('c');
    mm_save_cfg('lektorat', $c);
}

/**
 * Workbooks für Method und Unfold (Vivien, 26.10.2026: alle Workbooks neu, sehr umfangreich, DE + EN).
 * PDFs aus tools/workbooks/build.py liegen in api/seed/workbooks/pdf (gesperrt), werden in den Datei-Ordner kopiert
 * und als Programm-Dateien eingetragen. Neue Fassung: Version hochzählen, dann werden die Dateien ersetzt.
 */
function mv_workbooks_sync(PDO $pdo): void
{
    $ver = 10; // 2: ohne Schatten (Vivien, 26.10.2026); 3–5: Sprach-Lektorat (27.10.2026); 6: neuer Look mit Foto im Bogen und Wellen (28.10.2026); 10: ohne „Pilates“ (28.10.2026)
    $c = mm_cfg('workbooks') ?? [];
    if ((int)($c['v'] ?? 0) >= $ver) return;
    $src = __DIR__ . '/seed/workbooks/pdf/';
    $map = [
        ['method-ankommen', "audience = 'method' AND theme = 'ankommen'", 'Workbook · Ankommen', 'Workbook · Arrive'],
        ['method-rueckzug', "audience = 'method' AND title_de LIKE '%Rückzug%'", 'Workbook · Rückzug & Ruhe', 'Workbook · Retreat & Rest'],
        ['unfold-m1', "audience = 'unfold' AND title_de LIKE 'Monat 1%'", 'Workbook · Wurzeln', 'Workbook · Roots'],
        ['unfold-m2', "audience = 'unfold' AND title_de LIKE 'Monat 2%'", 'Workbook · Knospen', 'Workbook · Buds'],
        ['unfold-m3', "audience = 'unfold' AND title_de LIKE 'Monat 3%'", 'Workbook · Entfalten', 'Workbook · Unfold'],
    ];
    foreach ($map as $m) foreach (['de', 'en'] as $lang) if (!is_file($src . $m[0] . '.' . $lang . '.pdf')) return; // erst wenn alle Sprachen fertig sind
    $done = 0;
    foreach ($map as [$key, $where, $lde, $len]) {
        $pid = (int)$pdo->query('SELECT id FROM mv_programs WHERE ' . $where . ' ORDER BY id LIMIT 1')->fetchColumn();
        if (!$pid) continue;
        foreach (['de', 'en'] as $lang) {
            $file = $src . $key . '.' . $lang . '.pdf';
            if (!is_file($file)) continue;
            $name = 'wb-' . $key . '.' . $lang . '.pdf';
            if (!@copy($file, mv_file_dir() . '/' . $name)) continue;
            $q = $pdo->prepare('SELECT id FROM mv_files WHERE file = ?');
            $q->execute([$name]);
            if (!$q->fetchColumn()) $pdo->prepare('INSERT INTO mv_files (program_id, label_de, label_en, lang, file, created_at) VALUES (?,?,?,?,?,?)')->execute([$pid, $lde, $len, $lang, $name, date('c')]);
            $done++;
        }
    }
    if ($done) mm_save_cfg('workbooks', ['v' => $ver, 'files' => $done, 'at' => date('c')]);
}

// --- Konten ---------------------------------------------------------------------------------------

function mv_member_by_email(PDO $pdo, string $email): ?array
{
    $q = $pdo->prepare('SELECT * FROM mv_members WHERE email = ?');
    $q->execute([strtolower(trim($email))]);
    return $q->fetch() ?: null;
}

function mv_member(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM mv_members WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

/** Legt das Konto (noch ohne Passwort) an, falls es fehlt. */
function mv_member_ensure(PDO $pdo, string $email, string $name, string $lang): array
{
    $m = mv_member_by_email($pdo, $email);
    if ($m) return $m;
    $pdo->prepare('INSERT INTO mv_members (email, name, lang, created_at) VALUES (?,?,?,?)')->execute([strtolower(trim($email)), $name, $lang === 'en' ? 'en' : 'de', date('c')]);
    return mv_member_by_email($pdo, $email);
}

/** Bestätigungslink für die E-Mail-Adresse (30 Tage gültig). */
function mv_confirm_link(array $m): string
{
    $exp = time() + 30 * 86400;
    return mm_site() . '/api/method.php?bestaetigen=' . (int)$m['id'] . '&e=' . $exp . '&k=' . hash_hmac('sha256', 'ok:' . (int)$m['id'] . ':' . $exp . ':' . strtolower((string)$m['email']), mv_secret());
}

function mv_confirm_ok(PDO $pdo, int $id, int $exp, string $k): ?array
{
    $m = mv_member($pdo, $id);
    if (!$m || $exp < time() || !hash_equals(hash_hmac('sha256', 'ok:' . $id . ':' . $exp . ':' . strtolower((string)$m['email']), mv_secret()), $k)) return null;
    return $m;
}

/** Kurze Mail „Bitte bestätige deine E-Mail“, sonst nichts (Vivien, 25.10.2026). */
function mv_send_confirm(PDO $pdo, array $m, array $r, bool $reminder = false): void
{
    $en = ($r['lang'] ?? $m['lang']) === 'en';
    $first = forma_first_name((string)($r['name'] ?: $m['name']) ?: ($en ? 'friend' : 'meine Liebe'));
    $title = $r['kind'] === 'unfold' ? 'Unfold – The Mindful Becoming' : 'Movecia Mindful Movement Method';
    $lines = $en ? [
        "Dear $first,",
        ($reminder ? 'a gentle reminder: ' : '') . "thank you for booking $title! Your payment has come through.",
        'Please confirm your email address with one click. As soon as you do, I will send you your welcome email with all the information, your invoice and your access details.',
    ] : [
        "Hallo $first,",
        ($reminder ? 'eine kleine Erinnerung: ' : '') . "danke für deine Buchung von $title! Deine Zahlung ist angekommen.",
        'Bitte bestätige noch kurz deine E-Mail-Adresse mit einem Klick. Erst danach schicke ich dir deine Willkommensmail mit allen Infos, deiner Rechnung und deinem Zugang.',
    ];
    $pfx = (int)($r['test'] ?? 0) === 1 ? '[TEST] ' : '';
    mv_send_member_mail($r, $pfx . ($en ? 'Please confirm your email' : 'Bitte bestätige deine E-Mail-Adresse'), $en ? 'one last click ♡' : 'nur noch ein Klick ♡', $title, $lines, mv_confirm_link($m), $en ? 'Confirm my email' : 'E-Mail bestätigen', '');
    $pdo->prepare('UPDATE mv_members SET confirm_sent = ?' . ($reminder ? ', confirm_reminded = 1' : '') . ' WHERE id = ?')->execute([time(), $m['id']]);
}

/** Nach der Zahlung: bestätigte Adresse → Willkommensmail, sonst zuerst Bestätigung. */
function mv_after_paid(PDO $pdo, array $r): void
{
    mv_notify_viv($pdo, $r);
    $m = mv_member_ensure($pdo, (string)$r['email'], (string)$r['name'], (string)$r['lang']);
    if ((int)$m['email_ok'] === 1) { mv_welcome($pdo, $r); return; }
    mv_send_confirm($pdo, $m, $r);
}

/** Klick auf den Bestätigungslink: Adresse gilt, alle wartenden Willkommensmails gehen raus. */
function mv_confirm(PDO $pdo, array $m): void
{
    $pdo->prepare('UPDATE mv_members SET email_ok = 1 WHERE id = ?')->execute([$m['id']]);
    foreach (mv_subs_of($pdo, (string)$m['email']) as $s) if ((int)$s['mailed'] === 0) mv_welcome($pdo, $s);
    mv_invoices_release($pdo, (string)$m['email']);
}

/** Zurückgehaltene Rechnungen nach der Bestätigung verschicken. */
function mv_invoices_release(PDO $pdo, string $email): void
{
    require_once __DIR__ . '/invoices.php';
    $q = $pdo->prepare("SELECT id FROM invoices WHERE lower(email) = ? AND mailed = 0 AND kind IN ('mv_method','mv_unfold')");
    try { $q->execute([strtolower($email)]); } catch (Throwable $e) { return; }
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) { try { forma_invoice_send($pdo, (int)$id); } catch (Throwable $e) { error_log('MV invoice release: ' . $e->getMessage()); } }
}

/** Erinnerung nach 2 Tagen, genau einmal. */
function mv_confirm_run(PDO $pdo): void
{
    $q = $pdo->prepare("SELECT * FROM mv_members WHERE email_ok = 0 AND confirm_sent > 0 AND confirm_sent < ? AND confirm_reminded = 0 LIMIT 20");
    $q->execute([time() - 2 * 86400]);
    foreach ($q->fetchAll() as $m) {
        $subs = array_filter(mv_subs_of($pdo, (string)$m['email']), fn($s) => (int)$s['mailed'] === 0);
        if (!$subs) continue;
        try { mv_send_confirm($pdo, $m, reset($subs), true); } catch (Throwable $e) { error_log('MV confirm reminder: ' . $e->getMessage()); }
    }
}

function mv_sig(int $id, int $exp, string $hash): string
{
    return hash_hmac('sha256', "mv:$id:$exp:" . substr($hash, -16), mv_secret());
}

function mv_login(PDO $pdo, array $m): void
{
    $exp = time() + 90 * 86400;
    $v = $m['id'] . '.' . $exp . '.' . mv_sig((int)$m['id'], $exp, (string)$m['pass_hash']);
    setcookie(MV_COOKIE, $v, ['expires' => $exp, 'path' => '/', 'secure' => str_starts_with(mm_site(), 'https'), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[MV_COOKIE] = $v;
    mm_dev_login($pdo, 'mv', (int)$m['id']);
    $pdo->prepare('UPDATE mv_members SET last_login = ? WHERE id = ?')->execute([date('c'), $m['id']]);
}

function mv_logout(): void
{
    setcookie(MV_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE[MV_COOKIE]);
}

function mv_current(PDO $pdo): ?array
{
    $c = (string)($_COOKIE[MV_COOKIE] ?? '');
    if (!preg_match('/^(\d+)\.(\d+)\.([0-9a-f]{64})$/', $c, $x) || (int)$x[2] < time()) return null;
    $m = mv_member($pdo, (int)$x[1]);
    if (!$m || $m['pass_hash'] === '' || !hash_equals(mv_sig((int)$m['id'], (int)$x[2], (string)$m['pass_hash']), $x[3])) return null;
    if (!mm_dev_check($pdo, 'mv', (int)$m['id'])) { mv_logout(); return null; } // ein Zugang = eine Person
    return $m;
}

function mv_set_password(PDO $pdo, array $m, string $pw): array
{
    $pdo->prepare("UPDATE mv_members SET pass_hash = ?, reset_token = '', reset_until = '' WHERE id = ?")->execute([password_hash($pw, PASSWORD_DEFAULT), $m['id']]);
    return mv_member($pdo, (int)$m['id']);
}

function mv_throttled(PDO $pdo, string $ip): bool
{
    $pdo->prepare('DELETE FROM mv_login_fail WHERE at < ?')->execute([date('c', time() - 86400)]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM mv_login_fail WHERE ip_hash = ? AND at >= ?');
    $q->execute([$ip, date('c', time() - 900)]);
    return (int)$q->fetchColumn() >= 10;
}

function mv_fail(PDO $pdo, string $ip): void
{
    $pdo->prepare('INSERT INTO mv_login_fail (ip_hash, at) VALUES (?,?)')->execute([$ip, date('c')]);
}

function mv_area(): string { return mm_site() . '/mitglieder/'; }

/** Link „Passwort festlegen“ (nach dem Kauf und für vergessene Passwörter), 7 Tage bzw. 1 Stunde gültig. */
function mv_pw_link(PDO $pdo, array $m, int $hours): string
{
    $tok = bin2hex(random_bytes(20));
    $pdo->prepare('UPDATE mv_members SET reset_token = ?, reset_until = ? WHERE id = ?')->execute([hash('sha256', $tok), date('c', time() + $hours * 3600), $m['id']]);
    return mv_area() . '?pw=' . $tok;
}

function mv_by_reset(PDO $pdo, string $tok): ?array
{
    if (!preg_match('/^[0-9a-f]{40}$/', $tok)) return null;
    $q = $pdo->prepare('SELECT * FROM mv_members WHERE reset_token = ? AND reset_until >= ?');
    $q->execute([hash('sha256', $tok), date('c')]);
    return $q->fetch() ?: null;
}

function mv_send_reset(PDO $pdo, string $email): void
{
    $m = mv_member_by_email($pdo, $email);
    if (!$m || !mv_subs_of($pdo, (string)$m['email'], true)) return;
    $en = $m['lang'] === 'en';
    $link = mv_pw_link($pdo, $m, 1);
    $first = forma_first_name((string)$m['name'] ?: ($en ? 'there' : 'meine Liebe'));
    $t = $en ? "Hi $first,\n\nHere you can choose a new password for your Movecia members area. The link is valid for one hour.\n\n$link\n\nIf you didn't request this, you can simply ignore this email."
        : "Hallo $first,\n\nhier kannst du dir ein neues Passwort für deinen Movecia-Mitgliederbereich aussuchen. Der Link gilt eine Stunde.\n\n$link\n\nWenn du das nicht angefordert hast, ignorier diese E-Mail einfach.";
    $html = '<p>' . ($en ? 'Hi ' : 'Hallo ') . forma_h($first) . ',</p><p>' . ($en ? 'here you can choose a new password for your Movecia members area. The link is valid for one hour.' : 'hier kannst du dir ein neues Passwort für deinen Movecia-Mitgliederbereich aussuchen. Der Link gilt eine Stunde.') . '</p>'
        . mm_button($link, $en ? 'Choose a new password' : 'Neues Passwort wählen') . '<p style="font-size:13px;color:#6E6168;">' . ($en ? 'If you didn\'t request this, you can simply ignore this email.' : 'Wenn du das nicht angefordert hast, ignorier diese E-Mail einfach.') . '</p>';
    mm_mail((string)$m['email'], $en ? 'Your new Movecia password' : 'Dein neues Passwort für Movecia', $t, mm_mail_html($en ? 'New password' : 'Neues Passwort', $html, $m['lang']), [], 'Vivien · Movecia');
}

// --- Käufe und Zugang ---------------------------------------------------------------------------

function mv_sub(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM mv_subs WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

/** Käufe einer E-Mail (nur bezahlte/aktive, auf Wunsch auch beendete). */
function mv_subs_of(PDO $pdo, string $email, bool $withEnded = false): array
{
    $q = $pdo->prepare("SELECT * FROM mv_subs WHERE lower(email) = ? AND status IN ('aktiv'" . ($withEnded ? ",'beendet'" : '') . ") ORDER BY id");
    $q->execute([strtolower(trim($email))]);
    return $q->fetchAll();
}

/** Belegte Unfold-Plätze (bezahlt + offene Zahlungen in der Haltezeit). */
function mv_unfold_taken(PDO $pdo, int $except = 0): int
{
    $q = $pdo->prepare("SELECT COUNT(*) FROM mv_subs WHERE kind = 'unfold' AND test = 0 AND id <> ? AND (status IN ('aktiv','beendet') OR (status = 'offen' AND created_at >= ?))");
    $q->execute([$except, date('c', time() - MV_HOLD)]);
    return (int)$q->fetchColumn();
}

function mv_unfold_free(PDO $pdo): int
{
    return max(0, mv_cfg()['u_seats'] - mv_unfold_taken($pdo));
}

/** Ende der Unfold-Begleitung. Unfold ist ein eigenes Programm, ohne Method-Zugang (Vivien, 25.10.2026). */
function mv_unfold_end(): int { return mv_ts(mv_cfg()['u_end'], '23:59'); }

/**
 * Ab wann eine Buchung offen ist (Vivien, 25.10.2026: wer vor dem Start bucht, sieht bis dahin nichts):
 * Unfold am Starttag (0:00 Wien), Method am Tag der ersten Abbuchung, nach dem Start sofort ab Kauf.
 */
function mv_open_at(array $s): int
{
    if ((int)($s['open_from'] ?? 0) > 0) return (int)$s['open_from'];
    if ($s['kind'] === 'unfold') return mv_ts(mv_cfg()['u_start'], '00:00');
    $paid = strtotime((string)($s['paid_at'] ?? '')) ?: 0;
    return max((int)$s['bill_start'], $paid);
}

/**
 * Ende der Rücktrittsfrist: 14 Tage ab dem Start (bei Vorab-Buchung freiwillig verlängert,
 * Vivien 25.10.2026), sonst 14 Tage ab der Zahlung. Großzügig bis 23:59 dieses Tages.
 */
function mv_withdraw_until(array $s): int
{
    $paid = strtotime((string)($s['paid_at'] ?? '')) ?: time();
    $from = max($paid, mv_open_at($s));
    return mv_ts((new DateTimeImmutable('@' . ($from + 14 * 86400)))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('Y-m-d'), '23:59');
}

/**
 * Testteilnehmerin (Vivien, 25.10.2026): Zugang ohne Zahlung für Method, Unfold oder beides, damit sie den
 * Mitgliederbereich so sieht wie eine Kundin. $o: kinds (['method','unfold']), started, waive, lang, name, email (optional).
 * Ohne E-Mail wird eine Platzhalter-Adresse vergeben und keine Mail verschickt. Zählt nicht als Platz bei Unfold.
 */
function mv_test_create(PDO $pdo, array $o): array
{
    $c = mv_cfg();
    $email = strtolower(trim((string)($o['email'] ?? '')));
    $mail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    if (!$mail) $email = 'test-' . bin2hex(random_bytes(3)) . '@test.moveciamovement.com';
    $lang = ($o['lang'] ?? '') === 'en' ? 'en' : 'de';
    $name = forma_clean((string)($o['name'] ?? ''), 80) ?: 'Test';
    $m = mv_member_ensure($pdo, $email, $name, $lang);
    if ((string)$m['pass_hash'] === '') {
        $pdo->prepare('UPDATE mv_members SET pass_hash = ? WHERE id = ?')->execute([password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT), $m['id']]);
        $m = mv_member($pdo, (int)$m['id']);
    }
    $now = time();
    foreach (array_unique((array)($o['kinds'] ?? ['method'])) as $kind) {
        if (!in_array($kind, ['method', 'unfold'], true)) continue;
        $open = !empty($o['started']) ? $now - 3600 : ($kind === 'unfold' ? mv_ts($c['u_start'], '00:00') : max(mv_launch(), $now + 86400));
        $pdo->prepare("INSERT INTO mv_subs (kind, plan, email, name, lang, call_lang, amount_cents, first_cents, status, token, consent_digital, consent_live, source, test, created_at, paid_at, period_end, bill_start, open_from, open_mailed, review_sent) VALUES (?,?,?,?,?,?,0,0,'aktiv',?,?,1,'test',1,?,?,?,?,?,?,1)")
            ->execute([$kind, $kind === 'unfold' ? 'u1' : 'm1', $email, $name, $lang, $lang, bin2hex(random_bytes(16)), !empty($o['waive']) ? 1 : 0, date('c'), date('c'), $open + 30 * 86400, $kind === 'method' ? $open : 0, $open, $mail ? 0 : 1]);
        if ($mail) { $pdo->prepare('UPDATE mv_members SET email_ok = 1 WHERE id = ?')->execute([$m['id']]); $ts = mv_sub($pdo, (int)$pdo->lastInsertId()); mv_notify_viv($pdo, $ts); mv_welcome($pdo, $ts); }
    }
    return $m;
}

/** Knopf „Als Testteilnehmerin einloggen“: Link 10 Minuten gültig, nur für Testzugänge. */
function mv_test_login_link(int $memberId): string
{
    $exp = time() + 600;
    return mm_site() . '/mitglieder/?testlogin=' . $memberId . '&e=' . $exp . '&k=' . hash_hmac('sha256', "tl:$memberId:$exp", mv_secret());
}

function mv_test_login_ok(PDO $pdo, int $memberId, int $exp, string $k): ?array
{
    if ($exp < time() || !hash_equals(hash_hmac('sha256', "tl:$memberId:$exp", mv_secret()), $k)) return null;
    $m = mv_member($pdo, $memberId);
    if (!$m) return null;
    $q = $pdo->prepare("SELECT COUNT(*) FROM mv_subs WHERE lower(email) = ? AND source = 'test'");
    $q->execute([strtolower((string)$m['email'])]);
    return (int)$q->fetchColumn() > 0 ? $m : null;
}

/** Startet diese Buchung erst in der Zukunft? */
function mv_pending(array $s): bool { return mv_open_at($s) > time(); }

/**
 * Was darf diese Frau sehen?
 * method: Bibliothek offen · starter: nur Ankommen (Widerrufsfrist ohne Sofort-Zugang) · unfold: Unfold-Kauf oder null
 * pending: Buchungen, die erst später starten (bis dahin kein Zugang), open_at: frühester Start davon
 */
function mv_access(PDO $pdo, string $email): array
{
    $subs = mv_subs_of($pdo, $email);
    $now = time();
    $method = false; $starter = true; $unfold = null; $ulock = false; $until = 0; $methodSub = null; $pending = [];
    foreach ($subs as $s) {
        if (mv_open_at($s) > $now) { $pending[] = $s; continue; }
        if ($s['kind'] === 'unfold') {
            // Unfold ist ein eigenes Programm: kein Zugang zur Method-Bibliothek
            if ($now <= mv_unfold_end()) { $unfold = $s; $until = max($until, mv_unfold_end()); $ulock = (int)$s['consent_digital'] !== 1 && mv_withdraw_until($s) > $now; }
            continue;
        } else {
            // Abo: aktiv bis Periodenende, 2 Tage Puffer für Abbuchungen
            $end = max((int)$s['period_end'], (int)$s['bill_start']);
            if ($end === 0 || $end + 2 * 86400 >= $now) { $method = true; $until = max($until, $end); $methodSub = $methodSub ?: $s; }
        }
        if ((int)$s['consent_digital'] === 1 || mv_withdraw_until($s) <= $now) $starter = false;
    }
    // Konto-Ansicht zeigt eine vorgebuchte Method trotzdem (Kündigen vor dem Start)
    if (!$methodSub) foreach ($pending as $s) if ($s['kind'] === 'method') { $methodSub = $s; break; }
    $openAt = $pending ? min(array_map('mv_open_at', $pending)) : 0;
    $month = ($method && $methodSub) ? mv_member_month($pdo, $methodSub) : ['n' => 0, 'next_at' => 0, 'start' => 0];
    return ['month' => $month, 'method' => $method, 'starter' => $method && $starter, 'unfold' => $unfold, 'ulock' => $ulock, 'until' => $until, 'sub' => $methodSub, 'subs' => $subs, 'pending' => $pending, 'open_at' => $openAt];
}

// --- Stripe ---------------------------------------------------------------------------------------

/** Rabatt „X € weniger, einmal“ (Gathering-Preis für die erste Zahlung), einmal angelegt und gemerkt. */
function mv_coupon_once(int $off): string
{
    $cfg = mm_cfg('coupons') ?? [];
    $key = 'once' . $off . (forma_stripe_test_mode() ? 't' : 'l');
    if (!empty($cfg[$key])) return (string)$cfg[$key];
    $c = forma_stripe('POST', '/v1/coupons', ['amount_off' => $off, 'currency' => 'eur', 'duration' => 'once', 'name' => 'Gathering-Preis']);
    $cfg[$key] = (string)$c['id'];
    mm_save_cfg('coupons', $cfg);
    return (string)$c['id'];
}

/**
 * Legt den Kauf an und gibt die Stripe-Bezahlseite zurück. Preise bestimmt immer der Server.
 * $special = true nur, wenn ein Gathering-Angebot gerade gilt (prüft der Aufrufer).
 */
function mv_checkout(PDO $pdo, array $in, bool $special = false, string $source = 'web'): string
{
    mv_schema($pdo);
    $plans = mv_plans();
    $plan = (string)($in['plan'] ?? '');
    if (!isset($plans[$plan])) throw new RuntimeException('Bitte wähl eine Zahlungsart.');
    $p = $plans[$plan];
    $c = mv_cfg();
    $lang = ($in['lang'] ?? 'de') === 'en' ? 'en' : 'de';
    $en = $lang === 'en';
    $name = forma_clean((string)($in['name'] ?? ''), 80);
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $fromRoom = $source !== 'web';
    if (!$fromRoom && ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new RuntimeException($en ? 'Please enter your name and email.' : 'Bitte gib deinen Namen und deine E-Mail an.');
    $digital = !empty($in['digital']) ? 1 : 0;
    $live = !empty($in['live']) ? 1 : 0;
    // Eine klare Wahl (Vivien, 25.10.2026): Verzicht auf das Rücktrittsrecht ja oder nein
    if (isset($in['wr'])) {
        if (!in_array($in['wr'], ['verzicht', 'behalten'], true)) throw new RuntimeException($en ? 'Please choose whether you want to keep your right of withdrawal.' : 'Bitte wähle, ob du dein Rücktrittsrecht behalten möchtest.');
        $digital = $in['wr'] === 'verzicht' ? 1 : 0;
        $live = 1;
    }
    $callLang = in_array($in['call_lang'] ?? '', ['de', 'en'], true) ? $in['call_lang'] : $lang;
    // Unfold gibt es seit 30.10.2026 nur auf Deutsch (Vivien): Mails, Call und Mitgliederbereich immer Deutsch
    if ($p['kind'] === 'unfold') { $lang = 'de'; $en = false; $callLang = 'de'; }
    if ($p['kind'] === 'unfold') {
        if (!$c['u_open']) throw new RuntimeException($en ? 'Unfold is not open for booking right now.' : 'Unfold ist gerade nicht buchbar.');
        if (mv_unfold_free($pdo) <= 0) throw new RuntimeException($en ? 'Unfold is fully booked. Join the list and I\'ll let you know when the next round starts.' : 'Unfold ist ausgebucht. Trag dich in die Liste ein, dann sage ich dir Bescheid, wenn die nächste Runde startet.');
        if (!$live) throw new RuntimeException($en ? 'Please tick the box about when your access starts.' : 'Bitte setz das Häkchen zum Beginn deines Zugangs.');
        // Schon gebucht?
        if ($email !== '') foreach (mv_subs_of($pdo, $email) as $s) if ($s['kind'] === 'unfold') throw new RuntimeException($en ? 'You are already booked onto Unfold. Simply log in to your members area.' : 'Du bist für Unfold schon angemeldet. Melde dich einfach im Mitgliederbereich an.');
    } else {
        if ($email !== '') foreach (mv_subs_of($pdo, $email) as $s) if ($s['kind'] === 'method') throw new RuntimeException($en ? 'You already have a membership. Simply log in to your members area.' : 'Du hast schon eine Mitgliedschaft. Melde dich einfach im Mitgliederbereich an.');
    }
    $special = $special && $p['g'] > 0 && $p['g'] < $p['cents'];
    $first = $special ? $p['g'] : $p['cents'];
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO mv_subs (kind, plan, email, name, lang, call_lang, amount_cents, first_cents, special, token, consent_digital, consent_live, source, test, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$p['kind'], $plan, $email, $name, $lang, $p['kind'] === 'unfold' ? $callLang : '', $p['cents'], $first, $special ? 1 : 0, $tok, $digital, $live, $source, forma_stripe_test_mode() ? 1 : 0, date('c')]);
    $sid = (int)$pdo->lastInsertId();
    $site = mm_site();
    $title = $p['kind'] === 'unfold' ? 'Unfold – The Mindful Becoming' : 'Movecia Mindful Movement Method';
    $sub = $p['kind'] === 'method' || $plan === 'u3';
    $price = ['currency' => 'eur', 'unit_amount' => $p['cents'], 'product_data' => ['name' => $title . ' · ' . ($en ? $p['en'] : $p['de']), 'description' => 'Movecia – Personal Fitness, Vivien Malonga']];
    if ($sub) $price['recurring'] = $plan === 'm12' ? ['interval' => 'year'] : ['interval' => 'month', 'interval_count' => $plan === 'm3' ? 3 : 1];
    $terms = ($en ? 'Terms and cancellation: ' : 'Bedingungen und Rücktritt: ') . $site . '/terms';
    $params = [
        'mode' => $sub ? 'subscription' : 'payment',
        'locale' => $en ? 'en' : 'de',
        'billing_address_collection' => 'required',
        'client_reference_id' => 'mv-' . $sid,
        'metadata' => ['mv_sub' => (string)$sid],
        'line_items' => [['quantity' => 1, 'price_data' => $price]],
        'custom_text' => ['submit' => ['message' => $terms]],
        'expires_at' => time() + 31 * 60,
        'success_url' => $site . '/api/method.php?paid=' . $sid . '&k=' . $tok,
        'cancel_url' => $site . ($p['kind'] === 'unfold' ? '/unfold/' : '/method/') . '?abbruch=1' . ($en ? '&lang=en' : ''),
        'branding_settings' => ['display_name' => 'Movecia – Personal Fitness'],
    ];
    if ($email !== '') $params['customer_email'] = $email;
    if ($sub) $params['subscription_data'] = ['metadata' => ['mv_sub' => (string)$sid], 'description' => $title];
    else $params['payment_intent_data'] = ['statement_descriptor_suffix' => 'MOVECIA', 'description' => $title];
    // Vor dem Start der Method: jetzt buchen, Karte hinterlegen, erste Abbuchung am Starttag (Stripe braucht 48 Std. Vorlauf)
    $launch = mv_launch();
    if ($p['kind'] === 'method' && $launch > time() + 50 * 3600) {
        $params['subscription_data']['trial_end'] = $launch;
        $params['payment_method_collection'] = 'always';
        $params['custom_text']['submit']['message'] = ($en ? 'You won\'t be charged today. The Method opens on ' . mv_date($launch, 'en') . ', and your first payment will be taken then. ' : 'Heute wird nichts abgebucht. Die Method startet am ' . mv_date($launch) . ', dann wird die erste Zahlung eingezogen. ') . $terms;
        $pdo->prepare('UPDATE mv_subs SET bill_start = ? WHERE id = ?')->execute([$launch, $sid]);
    }
    if ($special) $params['discounts'] = [['coupon' => mv_coupon_once($p['cents'] - $p['g'])]];
    else $params['allow_promotion_codes'] = 'true';
    try {
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params, null, '2025-09-30.clover');
    } catch (Throwable $e) {
        // ältere Kontoeinstellungen kennen branding_settings nicht
        unset($params['branding_settings']);
        $s = forma_stripe('POST', '/v1/checkout/sessions', $params);
    }
    $pdo->prepare('UPDATE mv_subs SET stripe_session = ? WHERE id = ?')->execute([(string)$s['id'], $sid]);
    return (string)$s['url'];
}

/** Prüft einen offenen Kauf bei Stripe. Bei Zahlung: Konto, Mails, Rechnung (genau einmal). */
function mv_check_paid(PDO $pdo, array $r): string
{
    if ($r['status'] !== 'offen' || $r['stripe_session'] === '') return (string)$r['status'];
    $s = forma_stripe('GET', '/v1/checkout/sessions/' . rawurlencode($r['stripe_session']));
    if (($s['status'] ?? '') === 'expired') { $pdo->prepare("UPDATE mv_subs SET status = 'abgebrochen' WHERE id = ? AND status = 'offen'")->execute([$r['id']]); return 'abgebrochen'; }
    if (($s['status'] ?? '') !== 'complete' || !in_array(($s['payment_status'] ?? ''), ['paid', 'no_payment_required'], true)) return 'offen'; // Sicherheit 31.10.2026: nur wenn das Geld wirklich da ist (nicht schon bei „complete“ einer Lastschrift)
    $cd = $s['customer_details'] ?? [];
    $a = $cd['address'] ?? [];
    $country = strtoupper((string)($a['country'] ?? 'AT')) ?: 'AT';
    $addr = implode("\n", array_filter([(string)($a['line1'] ?? ''), (string)($a['line2'] ?? ''), trim(($a['postal_code'] ?? '') . ' ' . ($a['city'] ?? '')), $country !== 'AT' ? $country : '']));
    $name = $r['name'] ?: (string)($cd['name'] ?? '');
    $email = $r['email'] ?: strtolower((string)($cd['email'] ?? ''));
    $u = $pdo->prepare("UPDATE mv_subs SET status = 'aktiv', paid_at = ?, sub_id = ?, customer = ?, address = ?, country = ?, name = ?, email = ? WHERE id = ? AND status = 'offen'");
    $u->execute([date('c'), (string)($s['subscription'] ?? ''), (string)($s['customer'] ?? ''), $addr, $country, $name, $email, $r['id']]);
    if ($u->rowCount() !== 1) return 'aktiv';
    $r = mv_sub($pdo, (int)$r['id']);
    if ($r['kind'] === 'unfold' && $r['sub_id'] !== '') {
        // Raten: Abo endet kurz nach der letzten Rate
        try { forma_stripe('POST', '/v1/subscriptions/' . rawurlencode($r['sub_id']), ['cancel_at' => strtotime('+' . (mv_cfg()['u_n'] - 1) . ' months +10 days')]); }
        catch (Throwable $e) { error_log('MV unfold cancel_at: ' . $e->getMessage()); }
    }
    if ($r['sub_id'] === '') {
        // Einmalzahlung: gleich als Zahlung mit Rechnung erfassen
        mv_payment($pdo, $r, 'cs:' . $r['stripe_session'], (int)($s['amount_total'] ?? $r['first_cents']), time(), 0);
    } else {
        mv_sync($pdo, $r, true);
    }
    mv_after_paid($pdo, mv_sub($pdo, (int)$r['id']));
    return 'aktiv';
}

/** Zahlung erfassen und Rechnung ausstellen (je Zahlung einmal). */
function mv_payment(PDO $pdo, array $r, string $ref, int $cents, int $start, int $end): void
{
    if ($cents <= 0) return;
    $ins = $pdo->prepare('INSERT OR IGNORE INTO mv_payments (sub_row, ref, cents, period_start, period_end, paid_at) VALUES (?,?,?,?,?,?)');
    $ins->execute([$r['id'], $ref, $cents, $start, $end, date('c')]);
    if ($ins->rowCount() !== 1) return;
    require_once __DIR__ . '/invoices.php';
    // Stripe-Testmodus: Rechnung nur zeigen, keine Nummer verbrauchen
    $was = $GLOBALS['FORMA_TEST'] ?? null;
    if ((int)$r['test'] === 1) $GLOBALS['FORMA_TEST'] = true;
    forma_invoice_for($pdo, $r['kind'] === 'unfold' ? 'mv_unfold' : 'mv_method', (int)$pdo->lastInsertId());
    $GLOBALS['FORMA_TEST'] = $was;
}

/** Abo bei Stripe abgleichen: Periodenende, Kündigung, neue Zahlungen (mit Rechnung). */
function mv_sync(PDO $pdo, array $r, bool $force = false): array
{
    if ($r['sub_id'] === '' || (!$force && (int)$r['synced_at'] > time() - 6 * 3600 && (int)$r['period_end'] > time())) return $r;
    try {
        $s = forma_stripe('GET', '/v1/subscriptions/' . rawurlencode($r['sub_id']));
        $st = (string)($s['status'] ?? '');
        $end = (int)($s['current_period_end'] ?? $s['items']['data'][0]['current_period_end'] ?? 0);
        $cancelEnd = !empty($s['cancel_at_period_end']) ? $end : (int)($s['cancel_at'] ?? 0);
        $status = in_array($st, ['active', 'trialing', 'past_due'], true) ? 'aktiv' : ($st === 'incomplete' ? 'aktiv' : 'beendet');
        $inv = forma_stripe('GET', '/v1/invoices', ['subscription' => $r['sub_id'], 'status' => 'paid', 'limit' => 24]);
        foreach (array_reverse($inv['data'] ?? []) as $i) {
            if ((int)($i['amount_paid'] ?? 0) <= 0) continue;
            $line = $i['lines']['data'][0]['period'] ?? [];
            mv_payment($pdo, $r, 'in:' . $i['id'], (int)$i['amount_paid'], (int)($line['start'] ?? 0), (int)($line['end'] ?? 0));
        }
        // Unfold bleibt bis zum Ende der Begleitung, wenn die Raten fertig sind – aber nur, wenn wirklich alle Raten bezahlt sind
        // (Sicherheit 31.10.2026: sonst bliebe der Zugang nach der ersten Rate und gesperrter Karte offen).
        if ($r['kind'] === 'unfold' && $status === 'beendet') {
            $np = $pdo->prepare('SELECT COUNT(*) FROM mv_payments WHERE sub_row = ?');
            $np->execute([$r['id']]);
            if ((int)$np->fetchColumn() >= max(1, (int)mv_cfg()['u_n'])) $status = 'aktiv';
        }
        $pdo->prepare('UPDATE mv_subs SET period_end = ?, cancel_end = ?, status = ?, synced_at = ? WHERE id = ?')->execute([$end, $cancelEnd, $status, time(), $r['id']]);
    } catch (Throwable $e) {
        error_log('MV sync: ' . $e->getMessage());
    }
    return mv_sub($pdo, (int)$r['id']);
}

/** Läuft gedrosselt bei Seitenaufrufen: offene Käufe nachprüfen, Abos abgleichen. */
function mv_tick(PDO $pdo): void
{
    mv_schema($pdo);
    $f = forma_data_dir() . '/movecia-method-tick';
    if (is_file($f) && (int)file_get_contents($f) > time() - 600) return;
    @file_put_contents($f, (string)time());
    mv_open_run($pdo);
    mv_review_run($pdo);
    mv_confirm_run($pdo);
    mv_month_run($pdo);
    if (!forma_stripe_key()) return;
    $open = $pdo->prepare("SELECT * FROM mv_subs WHERE status = 'offen' AND stripe_session <> '' AND created_at >= ? ORDER BY id LIMIT 10");
    $open->execute([date('c', time() - 2 * 86400)]);
    foreach ($open->fetchAll() as $r) { try { mv_check_paid($pdo, $r); } catch (Throwable $e) { error_log('MV tick: ' . $e->getMessage()); } }
    $due = $pdo->prepare("SELECT * FROM mv_subs WHERE status = 'aktiv' AND sub_id <> '' AND (synced_at < ? OR period_end < ?) ORDER BY synced_at LIMIT 10");
    $due->execute([time() - 6 * 3600, time()]);
    foreach ($due->fetchAll() as $r) mv_sync($pdo, $r, true);
}

function mv_cancel(PDO $pdo, array $r, bool $cancel): void
{
    if ($r['sub_id'] === '' || $r['kind'] !== 'method') throw new RuntimeException('Nicht möglich.');
    forma_stripe('POST', '/v1/subscriptions/' . rawurlencode($r['sub_id']), ['cancel_at_period_end' => $cancel ? 'true' : 'false']);
    mv_sync($pdo, $r, true);
    $r = mv_sub($pdo, (int)$r['id']);
    $en = $r['lang'] === 'en';
    $end = max((int)$r['period_end'], (int)$r['bill_start']);
    mm_mail(MM_FROM_EMAIL, ($cancel ? 'Kündigung: ' : 'Kündigung zurückgenommen: ') . 'Method · ' . ($r['name'] ?: $r['email']), ($r['name'] ?: 'Jemand') . ' (' . $r['email'] . ') hat die Method-Mitgliedschaft ' . ($cancel ? 'gekündigt, Zugang bis ' . date('d.m.Y', $end) : 'doch weiterlaufen lassen') . '.', mm_mail_html('Method', '<p>' . forma_h(($r['name'] ?: 'Jemand') . ' (' . $r['email'] . ') hat die Method-Mitgliedschaft ' . ($cancel ? 'gekündigt, Zugang bis ' . date('d.m.Y', $end) : 'doch weiterlaufen lassen') . '.') . '</p>', 'de'));
    if ($cancel) {
        $first = forma_first_name((string)$r['name'] ?: ($en ? 'there' : 'meine Liebe'));
        $t = $en ? "Hi $first,\n\nYour Method membership has been cancelled. You'll keep full access until " . mv_date($end, 'en') . ", and you won't be charged again.\n\nIf you change your mind, you can simply continue in your members area.\n\nThank you for moving with me."
            : "Hallo $first,\n\ndeine Method-Mitgliedschaft ist gekündigt. Du hast noch bis " . mv_date($end) . " vollen Zugang, danach wird nichts mehr abgebucht.\n\nWenn du es dir anders überlegst, kannst du im Mitgliederbereich einfach weitermachen.\n\nDanke, dass du dich mit mir bewegt hast.";
        mm_mail((string)$r['email'], $en ? 'Your Method membership has been cancelled' : 'Deine Method-Mitgliedschaft ist gekündigt', $t, mm_mail_html('Method', nl2br(forma_h($t)), $r['lang']), [], 'Vivien · Movecia');
    }
}

// --- Mails nach dem Kauf ----------------------------------------------------------------------------

function mv_legal(array $r): string
{
    $en = $r['lang'] === 'en';
    $site = mm_site();
    $open = mv_open_at($r);
    $pre = $open > time();
    $until = mv_withdraw_until($r);
    $d = fn(int $t) => mv_date($t, $en ? 'en' : 'de');
    $isU = $r['kind'] === 'unfold';
    $x = '';
    if (!$isU && (int)$r['bill_start'] > time()) $x .= $en ? 'Nothing is charged before the start. ' : 'Vor dem Start wird nichts abgebucht. ';
    // Rücktrittsfrist (Vivien, 25.10.2026): bei Vorab-Buchung erst ab dem Start, also bis 14 Tage nach dem Start
    $x .= $pre
        ? ($en ? 'Your right of withdrawal: because your access only starts on ' . $d($open) . ', your 14-day withdrawal period only starts then too. You can withdraw without giving a reason until ' . $d($until) . '. If you withdraw before the start, you will get a full refund. '
               : 'Dein Rücktrittsrecht: Weil dein Zugang erst am ' . $d($open) . ' beginnt, beginnt auch deine 14-tägige Rücktrittsfrist erst an diesem Tag. Du kannst also bis ' . $d($until) . ' ohne Angabe von Gründen zurücktreten. Trittst du vor dem Start zurück, bekommst du alles zurück. ')
        : ($en ? 'You can withdraw from your booking without giving a reason until ' . $d($until) . '. ' : 'Du kannst bis ' . $d($until) . ' ohne Angabe von Gründen von deiner Buchung zurücktreten. ');
    if ($isU) $x .= $en ? 'If you withdraw after the programme has started, you only pay for the part already used. ' : 'Ab dem Start zahlst du bei einem Rücktritt nur den Teil, der bis dahin genutzt wurde. ';
    else $x .= $en ? 'You can cancel your membership at any time, effective at the end of the period you have paid for, right in your members area. ' : 'Du kannst deine Mitgliedschaft jederzeit zum Ende des bezahlten Zeitraums kündigen, direkt im Mitgliederbereich. ';
    if ($isU) $x .= (int)$r['consent_digital'] === 1
        ? ($en ? 'Your choice: you waive your right of withdrawal and use everything from the start, including all videos and workbooks (§ 18 (1) 11 FAGG). '
               : 'Deine Wahl: Du verzichtest auf dein Rücktrittsrecht und nutzt ab dem Start gleich alles, auch alle Videos und Workbooks (§ 18 Abs. 1 Z 11 FAGG). ')
        : ($en ? 'Your choice: you keep your right of withdrawal. Live calls and videos start as planned, and the workbook downloads unlock on ' . $d($until + 60) . '. '
               : 'Deine Wahl: Du behältst dein Rücktrittsrecht. Live-Calls und Videos starten wie geplant, die Workbook-Downloads öffnen sich am ' . $d($until + 60) . '. ');
    else $x .= (int)$r['consent_digital'] === 1
        ? ($en ? 'Confirmation: you expressly agreed that each of your monthly programmes with videos and downloads (digital content) is unlocked in full as soon as it opens, starting with month 1 ' . ($pre ? 'on ' . $d($open) : 'today') . ', and you acknowledged that you lose your right of withdrawal for this digital content with the unlocking (§ 18 (1) 11 FAGG). You made this choice once for the whole membership, renewals do not start a new withdrawal period. '
               : 'Bestätigung: Du hast ausdrücklich zugestimmt, dass deine Monatsprogramme mit Videos und Downloads (digitale Inhalte) jeweils gleich ganz freigeschaltet werden, beginnend mit Monat 1 ' . ($pre ? 'am ' . $d($open) : 'ab heute') . ', und zur Kenntnis genommen, dass du dein Rücktrittsrecht für diese digitalen Inhalte mit der Freischaltung verlierst (§ 18 Abs. 1 Z 11 FAGG). Diese Wahl gilt einmal für die ganze Mitgliedschaft, Verlängerungen lösen keine neue Rücktrittsfrist aus. ')
        : ($en ? 'You kept your right of withdrawal: you start with the welcome module, and your month 1 unlocks once your withdrawal period has ended, on ' . $d($until + 60) . '. This choice applies once, and renewals do not start a new withdrawal period. '
               : 'Du hast dein Rücktrittsrecht behalten: Du startest mit dem Ankommen-Modul, dein Monat 1 öffnet sich nach deiner Rücktrittsfrist am ' . $d($until + 60) . '. Das gilt einmal, Verlängerungen lösen keine neue Rücktrittsfrist aus. ');
    return $x . ($en ? 'Terms, cancellation policy and withdrawal form: ' : 'Bedingungen, Rücktritt und Widerrufsformular: ') . $site . '/terms · ' . ($en ? 'Withdraw: ' : 'Vertrag widerrufen: ') . $site . '/withdraw';
}

function mv_welcome(PDO $pdo, array $r): void
{
    if ((int)$r['mailed'] === 1) return;
    $pdo->prepare('UPDATE mv_subs SET mailed = 1 WHERE id = ?')->execute([$r['id']]);
    $en = $r['lang'] === 'en';
    $m = mv_member_ensure($pdo, (string)$r['email'], (string)$r['name'], (string)$r['lang']);
    $first = forma_first_name((string)$r['name'] ?: ($en ? 'friend' : 'meine Liebe'));
    $p = mv_plans()[$r['plan']];
    $c = mv_cfg();
    $price = mv_eur((int)$r['first_cents']) . ($r['plan'] === 'u1' ? '' : ' ' . ($en ? $p['per_en'] : $p['per_de']));
    if ((int)$r['special'] === 1 && $r['plan'] !== 'u1') $price .= $en ? ' (first payment at the Gathering price, then ' . mv_eur((int)$r['amount_cents']) . ')' : ' (erste Zahlung zum Gathering-Preis, danach ' . mv_eur((int)$r['amount_cents']) . ')';
    $isU = $r['kind'] === 'unfold';
    $title = $isU ? 'Unfold – The Mindful Becoming' : 'Movecia Mindful Movement Method';
    $open = mv_open_at($r);
    $pre = $open > time();
    $call = $isU ? ($r['call_lang'] === 'en' ? $c['u_call_en'] . ($en ? ' (English)' : ' (Englisch)') : $c['u_call_de'] . ($en ? ' (German)' : ' (Deutsch)')) : '';
    $legal = mv_legal($r);
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    if ($pre) {
        // Vorab-Buchung (Vivien, 25.10.2026): nur Danke + Freischaltdatum, noch kein Login-Link
        $pdo->prepare('UPDATE mv_subs SET open_mailed = 0 WHERE id = ?')->execute([$r['id']]);
        $lines = $en ? [
            "Dear $first,",
            "thank you so much, you're in! Your booking for $title ($price) is confirmed" . ((int)$r['bill_start'] > time() ? ', and nothing will be charged until the start' : '') . '.',
            'I am still getting everything ready for you, so there is nothing to see just yet. Your access opens on ' . mv_date($open, 'en') . '. On that day you will receive an email with your link to the members area, and from then on everything is open to you.',
        ] : [
            "Hallo $first,",
            "danke von Herzen, du bist dabei! Deine Buchung für $title ($price) ist bestätigt" . ((int)$r['bill_start'] > time() ? ', abgebucht wird erst am Starttag' : '') . '.',
            'Gerade bereite ich noch alles für dich vor, deshalb gibt es jetzt noch nichts zu sehen. Dein Zugang schaltet sich am ' . mv_date($open) . ' frei. An diesem Tag bekommst du eine E-Mail mit deinem Link zum Mitgliederbereich, und ab dann ist alles für dich offen.',
        ];
        if ($isU) $lines[] = $en ? 'Our first live call: ' . mv_date(mv_ts($c['u_start']), 'en') . ', ' . $call . '.' : 'Unser erster Live-Call: ' . mv_date(mv_ts($c['u_start'])) . ', ' . $call . '.';
        $lines[] = $en ? 'Your 14-day withdrawal period only begins when your access starts, so you can withdraw until ' . mv_date(mv_withdraw_until($r), 'en') . '.' : 'Deine 14-tägige Rücktrittsfrist beginnt erst mit dem Start, du kannst also bis ' . mv_date(mv_withdraw_until($r)) . ' zurücktreten.';
        $btn = ''; $link = '';
        $subj = $en ? 'Thank you, you\'re in: ' : 'Danke, du bist dabei: ';
    } else {
        $pdo->prepare('UPDATE mv_subs SET open_mailed = 1 WHERE id = ?')->execute([$r['id']]);
        $hasPw = $m['pass_hash'] !== '';
        $link = $hasPw ? mv_area() : mv_pw_link($pdo, $m, 24 * 7);
        $btn = $hasPw ? ($en ? 'Open my members area' : 'Zum Mitgliederbereich') : ($en ? 'Choose my password' : 'Passwort festlegen');
        $lines = $en ? [
            "Dear $first,",
            "how lovely, you're in! Your booking for $title ($price) is confirmed.",
            $isU ? 'Your weekly live call: ' . $call . '. Your members area with the Unfold videos, workbooks and recordings is open to you until ' . mv_date(mv_unfold_end(), 'en') . '.' : 'Your members area is ready, so you can start month 1 right away. Your next month opens on ' . mv_date(mv_month_at(mv_open_at($r), 1), 'en') . '.',
            $hasPw ? 'Log in with your email and your password.' : 'Choose your password once, then you can log in from any device with your email.',
        ] : [
            "Hallo $first,",
            "wie schön, du bist dabei! Deine Buchung für $title ($price) ist bestätigt.",
            $isU ? 'Dein wöchentlicher Live-Call: ' . $call . '. Dein Mitgliederbereich mit den Unfold-Videos, Workbooks und Aufnahmen ist bis ' . mv_date(mv_unfold_end()) . ' für dich offen.' : 'Du kannst sofort mit Monat 1 loslegen, dein Mitgliederbereich ist bereit. Dein nächster Monat öffnet sich am ' . mv_date(mv_month_at(mv_open_at($r), 1)) . '.',
            $hasPw ? 'Melde dich einfach mit deiner E-Mail und deinem Passwort an.' : 'Leg einmal dein Passwort fest, danach meldest du dich auf jedem Gerät mit deiner E-Mail an.',
        ];
        $subj = $en ? 'You\'re in: ' : 'Du bist dabei: ';
    }
    mv_send_member_mail($r, $pfx . $subj . $title, $en ? 'you\'re in ♡' : 'du bist dabei ♡', $title, $lines, $link, $btn, $legal);
}

/** Info an Vivien gleich nach der Zahlung (auch wenn die Kundin ihre Adresse noch nicht bestätigt hat). */
function mv_notify_viv(PDO $pdo, array $r): void
{
    $c = mv_cfg();
    $p = mv_plans()[$r['plan']];
    $isU = $r['kind'] === 'unfold';
    $title = $isU ? 'Unfold – The Mindful Becoming' : 'Movecia Mindful Movement Method';
    $open = mv_open_at($r);
    $pre = $open > time();
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    $v = 'Hallo Viv,' . "\n\n" . ($r['name'] ?: 'Jemand') . ' (' . $r['email'] . ') hat gerade ' . $title . ' gebucht: ' . $p['de'] . ', ' . mv_eur((int)$r['first_cents']) . ((int)$r['special'] ? ' (Gathering-Preis)' : '')
        . ((int)$r['bill_start'] > time() ? ', Abbuchung ab ' . date('d.m.Y', (int)$r['bill_start']) : '') . ($isU ? ', Call auf ' . ($r['call_lang'] === 'en' ? 'Englisch' : 'Deutsch') . '. Noch ' . mv_unfold_free($pdo) . ' von ' . $c['u_seats'] . ' Plätzen frei' : '')
        . ($pre ? '. Zugang öffnet sich automatisch am ' . date('d.m.Y', $open) . ' (dann geht die Freischalt-Mail raus), Rücktritt möglich bis ' . date('d.m.Y', mv_withdraw_until($r)) : '') . ".\n\nAlle Mitglieder: " . forma_site_url() . "/verwaltung/?view=method\n";
    mm_mail(MM_FROM_EMAIL, $pfx . 'Neue Buchung: ' . ($isU ? 'Unfold' : 'Method') . ' · ' . ($r['name'] ?: $r['email']), $v, mm_mail_html('Neue Buchung', nl2br(forma_h($v)), 'de'));
}

/** Gemeinsamer Aufbau der Mails an Mitglieder (Kopf, Absätze, Knopf, Rechtliches klein). */
function mv_send_member_mail(array $r, string $subject, string $hand, string $title, array $lines, string $link, string $btn, string $legal): void
{
    $en = $r['lang'] === 'en';
    $q = $en ? 'If you have any questions, simply reply to this email.' : 'Wenn du Fragen hast, antworte einfach auf diese E-Mail.';
    $text = implode("\n\n", $lines) . ($link !== '' ? "\n\n$btn: $link" : '') . "\n\n$q" . ($en ? "\n\nSee you soon,\nVivien" : "\n\nBis bald,\nVivien") . ($legal !== '' ? "\n\n$legal" : '');
    $html = '<p style="margin:0 0 6px;font-family:\'Brush Script MT\',cursive;font-size:24px;color:#4A3040;">' . forma_h($hand) . '</p><h1 style="font-family:Georgia,serif;font-weight:400;font-size:28px;color:#3A2631;margin:0 0 14px;">' . forma_h($title) . '</h1>';
    foreach ($lines as $l) $html .= '<p>' . forma_h($l) . '</p>';
    $html .= ($link !== '' ? mm_button($link, $btn) : '') . '<p>' . $q . '</p>'
        . ($legal !== '' ? '<p style="font-size:13px;color:#6E6168;border-top:1px solid #D3C9BC;padding-top:12px;margin-top:22px;">' . forma_h($legal) . '</p>' : '');
    mm_mail((string)$r['email'], $subject, $text, mm_mail_html($title, $html, (string)$r['lang']), [], 'Vivien · Movecia');
}

/** Am Starttag: „Dein Zugang ist offen“ mit Login- bzw. Passwort-Link, genau einmal pro Buchung. */
function mv_open_mail(PDO $pdo, array $r): void
{
    $u = $pdo->prepare("UPDATE mv_subs SET open_mailed = 1 WHERE id = ? AND open_mailed = 0");
    $u->execute([$r['id']]);
    if ($u->rowCount() !== 1) return;
    $en = $r['lang'] === 'en';
    $c = mv_cfg();
    $m = mv_member_ensure($pdo, (string)$r['email'], (string)$r['name'], (string)$r['lang']);
    $first = forma_first_name((string)$r['name'] ?: ($en ? 'friend' : 'meine Liebe'));
    $isU = $r['kind'] === 'unfold';
    $title = $isU ? 'Unfold – The Mindful Becoming' : 'Movecia Mindful Movement Method';
    $hasPw = $m['pass_hash'] !== '';
    $link = $hasPw ? mv_area() : mv_pw_link($pdo, $m, 24 * 14);
    $btn = $hasPw ? ($en ? 'Open my members area' : 'Zum Mitgliederbereich') : ($en ? 'Choose my password' : 'Passwort festlegen');
    $call = $r['call_lang'] === 'en' ? $c['u_call_en'] : $c['u_call_de'];
    $until = mv_withdraw_until($r);
    $lines = $en ? [
        "Dear $first,",
        "today is the day: your access to $title is now open!",
        $isU ? 'In your members area you will find the Unfold videos, your workbooks, the link to our live call (' . $call . ') and all the recordings.' : 'You begin with month 1, which is waiting for you in your members area. Your next month opens on ' . mv_date(mv_month_at(mv_open_at($r), 1), 'en') . ', and from then on a new month opens on the same day each month.',
        $hasPw ? 'Log in with your email and your password.' : 'Choose your password once, then you can log in from any device with your email.',
        'As promised, your 14-day withdrawal period starts today and runs until ' . mv_date($until, 'en') . '.',
    ] : [
        "Hallo $first,",
        "heute ist es so weit: Dein Zugang zu $title ist offen!",
        $isU ? 'In deinem Mitgliederbereich findest du die Unfold-Videos, deine Workbooks, den Link zu unserem Live-Call (' . $call . ') und alle Aufnahmen.' : 'Du beginnst mit Monat 1, er wartet in deinem Mitgliederbereich auf dich. Dein nächster Monat öffnet sich am ' . mv_date(mv_month_at(mv_open_at($r), 1)) . ', und so geht es jeden Monat an deinem Starttag weiter.',
        $hasPw ? 'Melde dich einfach mit deiner E-Mail und deinem Passwort an.' : 'Leg einmal dein Passwort fest, danach meldest du dich auf jedem Gerät mit deiner E-Mail an.',
        'Wie versprochen beginnt deine 14-tägige Rücktrittsfrist heute und läuft bis ' . mv_date($until) . '.',
    ];
    if ((int)$r['consent_digital'] !== 1 && !$isU) $lines[] = $en ? 'Until then, you begin with the welcome module, and after that your month 1 opens automatically.' : 'Bis dahin beginnst du mit dem Ankommen-Modul, danach öffnet sich dein Monat 1 von selbst.';
    $pfx = (int)$r['test'] === 1 ? '[TEST] ' : '';
    mv_send_member_mail($r, $pfx . ($en ? 'Your access is open: ' : 'Dein Zugang ist offen: ') . $title, $en ? 'here we go ♡' : 'es geht los ♡', $title, $lines, $link, $btn, mv_legal($r));
}

/** Bewertungs-Mail am Morgen nach dem Ende von Unfold (10 Uhr), genau einmal, sobald ein Bewertungslink hinterlegt ist. */
function mv_review_run(PDO $pdo): void
{
    if (mm_review_url() === '' || time() < mv_unfold_end() + 10 * 3600) return;
    $q = $pdo->query("SELECT * FROM mv_subs WHERE kind = 'unfold' AND status = 'aktiv' AND review_sent = 0 AND email <> '' ORDER BY id LIMIT 30");
    foreach ($q->fetchAll() as $r) {
        if (mv_open_at($r) > time()) continue; // gehört zu einer späteren Runde
        $u = $pdo->prepare('UPDATE mv_subs SET review_sent = 1 WHERE id = ? AND review_sent = 0');
        $u->execute([$r['id']]);
        if ($u->rowCount() !== 1) continue;
        try { mm_review_mail((string)$r['email'], (string)$r['name'], (string)$r['lang'], 'unfold', (int)$r['test'] === 1); } catch (Throwable $e) { error_log('MV review: ' . $e->getMessage()); }
    }
}

/** Freischalt-Mails für alle Vorab-Buchungen, deren Start erreicht ist. */
function mv_open_run(PDO $pdo): void
{
    $q = $pdo->query("SELECT * FROM mv_subs WHERE status = 'aktiv' AND mailed = 1 AND open_mailed = 0 AND email <> '' ORDER BY id LIMIT 40");
    foreach ($q->fetchAll() as $r) {
        if (mv_open_at($r) > time()) continue;
        try { mv_open_mail($pdo, $r); } catch (Throwable $e) { error_log('MV open mail: ' . $e->getMessage()); }
    }
}

// --- Rechnungen (Movecia-Design, gleicher Nummernkreis) ---------------------------------------------

function mv_invoice_source(PDO $pdo, string $kind, int $payId): ?array
{
    $q = $pdo->prepare('SELECT p.*, s.kind AS skind, s.plan, s.name, s.email, s.address, s.country, s.special FROM mv_payments p JOIN mv_subs s ON s.id = p.sub_row WHERE p.id = ?');
    $q->execute([$payId]);
    $p = $q->fetch();
    if (!$p || (int)$p['cents'] <= 0) return null;
    $pl = mv_plans()[$p['plan']] ?? ['de' => ''];
    $from = (int)$p['period_start'] ?: time();
    $per = (int)$p['period_end'] ? ', Zeitraum ' . date('d.m.Y', $from) . ' bis ' . date('d.m.Y', (int)$p['period_end'] - 1) : '';
    if ($p['skind'] === 'unfold') {
        $c = mv_cfg();
        $text = 'Unfold – The Mindful Becoming' . ($p['plan'] === 'u3' ? ' · Rate' : '');
        $detail = '3-monatige Online-Begleitung ' . date('d.m.Y', mv_ts($c['u_start'])) . ' bis ' . date('d.m.Y', mv_unfold_end()) . ': wöchentliche Live-Calls per Video, Workbooks, Impuls-Videos, Bewegungsvideos' . ((int)$p['special'] && $p['plan'] === 'u1' ? ' · Gathering-Preis' : '');
        $service = date('d.m.Y', mv_ts($c['u_start']));
    } else {
        $text = 'Movecia Mindful Movement Method · Mitgliedschaft ' . $pl['de'];
        $detail = 'On-Demand-Videobibliothek (Movement Flows, Somatic Movement, Breathwork, Meditation), Monatsprogramme mit Workbooks' . $per;
        $service = date('d.m.Y', $from);
    }
    return ['name' => $p['name'], 'email' => $p['email'], 'session' => '', 'address' => $p['address'], 'country' => $p['country'] ?: 'AT', 'service' => $service,
        'items' => [['text' => $text, 'detail' => $detail, 'qty' => 1, 'unit' => (int)$p['cents']]],
        'gross' => (int)$p['cents'], 'paid' => $p['paid_at'], 'type' => 'Movecia',
        'pay_note' => 'Der Betrag wurde am ' . date('d.m.Y', strtotime((string)$p['paid_at'])) . ' online bezahlt (Kartenzahlung über Stripe, gemeinsames Konto FORMA & Movecia). Buchungsvermerk: Leistung von Movecia – Personal Fitness.'];
}

// --- Monat für Monat ab dem eigenen Starttag -------------------------------------------------------
// Vivien 25.10.2026: Jede startet mit Monat 1. Der nächste Monat öffnet sich genau an ihrem Starttag im
// Folgemonat (Start am 25. → immer am 25.; Start am 31. → in kürzeren Monaten am letzten Tag).
// Wer kündigt und später wiederkommt, macht bei dem Monat weiter, den sie schon erreicht hatte.

/** Zeitpunkt, an dem Monat ($k + 1) aufgeht: Start + k Monate, Tag auf das Monatsende begrenzt. */
function mv_month_at(int $start, int $k): int
{
    $tz = new DateTimeZone('Europe/Vienna');
    $d = (new DateTimeImmutable('@' . $start))->setTimezone($tz);
    $day = (int)$d->format('j');
    $first = $d->modify('first day of this month')->modify('+' . $k . ' months');
    $last = (int)$first->format('t');
    return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), min($day, $last))->setTime(0, 0)->getTimestamp();
}

/** Bereits erreichte Monate aus früheren, beendeten Mitgliedschaften derselben Frau. */
function mv_month_offset(PDO $pdo, array $s): int
{
    $q = $pdo->prepare("SELECT MAX(month_mailed) FROM mv_subs WHERE kind = 'method' AND lower(email) = ? AND id < ? AND status = 'beendet'");
    $q->execute([strtolower((string)$s['email']), (int)$s['id']]);
    return (int)$q->fetchColumn();
}

/**
 * In welchem Monat ist sie? ['n' => 1…, 'next_at' => Zeitpunkt des nächsten Monats, 'start' => Starttag].
 * Ohne Verzicht auf das Rücktrittsrecht sieht sie in den ersten 14 Tagen nur das Ankommen (siehe mv_access 'starter').
 */
function mv_member_month(PDO $pdo, array $s): array
{
    $start = mv_open_at($s);
    $now = time();
    $k = 0;
    while ($k < 240 && mv_month_at($start, $k + 1) <= $now) $k++;
    $off = isset($s['id']) ? mv_month_offset($pdo, $s) : 0;
    return ['n' => $off + $k + 1, 'next_at' => mv_month_at($start, $k + 1), 'start' => $start, 'off' => $off];
}

/** Method-Programme in ihrer festen Reihenfolge: Ankommen zuerst, dann Monat 1, 2, 3 … (nach Datum, Reihenfolge). */
function mv_method_months(PDO $pdo): array
{
    $q = $pdo->query("SELECT * FROM mv_programs WHERE audience = 'method' AND visible = 1 ORDER BY CASE WHEN unlock = '' THEN 0 ELSE 1 END, unlock ASC, sort, id");
    $out = ['welcome' => [], 'months' => []];
    foreach ($q->fetchAll() as $p) {
        if ($p['unlock'] === '' || $p['theme'] === 'ankommen') $out['welcome'][] = $p; else $out['months'][] = $p;
    }
    return $out;
}

/**
 * Was sie von der Method sieht: Ankommen + Monat 1 bis n (neuester zuerst), dazu der nächste Monat als Vorschau.
 * Rückgabe: ['open' => [['p' => Programm, 'n' => Monatsnummer oder 0 fürs Ankommen]], 'next' => ['p', 'n', 'at'] oder null]
 */
function mv_method_view(PDO $pdo, array $acc): array
{
    $mm = mv_method_months($pdo);
    $n = (int)($acc['month']['n'] ?? 0);
    $open = [];
    foreach (array_slice($mm['months'], 0, max(0, $n)) as $i => $p) $open[] = ['p' => $p, 'n' => $i + 1];
    $open = array_reverse($open);
    foreach ($mm['welcome'] as $p) $open[] = ['p' => $p, 'n' => 0];
    $next = isset($mm['months'][$n]) ? ['p' => $mm['months'][$n], 'n' => $n + 1, 'at' => (int)$acc['month']['next_at']] : null;
    return ['open' => $open, 'next' => $next];
}

/** Wann hat sie jede Lektion zum ersten Mal ganz gemacht? [lesson_id => 'Y-m-d'] (Wiener Datum) */
function mv_done_first(PDO $pdo, int $mid): array
{
    $q = $pdo->prepare('SELECT lesson_id, MIN(done_at) d FROM mv_progress WHERE member_id = ? GROUP BY lesson_id');
    $q->execute([$mid]);
    $tz = new DateTimeZone('Europe/Vienna');
    $out = [];
    foreach ($q->fetchAll() as $r) $out[(int)$r['lesson_id']] = (new DateTimeImmutable((string)$r['d']))->setTimezone($tz)->format('Y-m-d');
    return $out;
}

/**
 * The Method als begleitendes Programm (Vivien, 26.10.2026, Stand 20:23): Das Ankommen (3 Videos) ist sofort ganz offen,
 * „Starte hier“ ist das erste Video (erklärt die Method). Danach geht es Video für Video: das nächste Monatsvideo öffnet
 * sich, sobald das vorige ganz gemacht ist (gleich danach, auch am selben Tag), das erste Monatsvideo nach dem
 * „Starte hier“-Video. Ein neuer Monat frühestens an ihrem Monatstag. Gedacht sind etwa drei Termine pro Woche.
 * Rückgabe: ['state' => [lesson_id => ['open', 'why' => ''|'prev'|'date'|'starter', 'at', 'm']], 'current', 'month',
 *            'order', 'start' => lesson_id des „Starte hier“-Videos]
 */
function mv_method_path(PDO $pdo, array $acc, array $doneFirst, ?int $now = null): array
{
    $now = $now ?? time();
    $mm = mv_method_months($pdo);
    $start = (int)($acc['month']['start'] ?? $now);
    $off = (int)($acc['month']['off'] ?? 0);
    $welcome = []; $months = [];
    foreach ($mm['welcome'] as $p) foreach (mv_lessons($pdo, (int)$p['id'], true) as $l) $welcome[] = $l;
    foreach ($mm['months'] as $i => $p) foreach (mv_lessons($pdo, (int)$p['id'], true) as $l) $months[] = [$l, $i + 1];
    $state = []; $order = []; $month = 0;
    $first = $welcome ? (int)$welcome[0]['id'] : 0;
    foreach ($welcome as $l) { $id = (int)$l['id']; $order[] = $id; $state[$id] = ['open' => true, 'why' => '', 'at' => 0, 'm' => 0]; }
    $prevOk = !$first || isset($doneFirst[$first]);
    $blocked = false;
    foreach ($months as [$l, $m]) {
        $id = (int)$l['id']; $order[] = $id;
        $isDone = isset($doneFirst[$id]);
        if ($blocked) { $state[$id] = ['open' => false, 'why' => 'prev', 'at' => 0, 'm' => $m]; continue; }
        $why = ''; $at = 0;
        if (!$prevOk && !$isDone) $why = 'prev';
        elseif (!empty($acc['starter'])) $why = 'starter';
        elseif ($m > 1) { $at = mv_month_at($start, max(0, $m - 1 - $off)); if ($at > $now && !$isDone) $why = 'date'; }
        $open = $why === '' || $isDone;
        $state[$id] = ['open' => $open, 'why' => $open ? '' : $why, 'at' => $at, 'm' => $m];
        if ($open) $month = max($month, $m);
        if (!$open) $blocked = true;
        $prevOk = $isDone;
    }
    // „Heute dran“: erst „Starte hier“, dann das nächste offene Monatsvideo (Ankommen-Videos 2 und 3 sind frei wählbar)
    if ($first && !isset($doneFirst[$first])) $current = $first;
    else { $current = 0; foreach ($months as [$l]) { $id = (int)$l['id']; if (($state[$id]['open'] ?? false) && !isset($doneFirst[$id])) { $current = $id; break; } } }
    return ['state' => $state, 'current' => $current, 'month' => $month, 'order' => $order, 'start' => $first];
}

/** Darf sie dieses Method-Programm sehen (z. B. für Downloads)? */
function mv_method_program_open(PDO $pdo, array $acc, int $programId): bool
{
    if (!$acc['method']) return false;
    foreach (mv_method_view($pdo, $acc)['open'] as $x) if ((int)$x['p']['id'] === $programId) return !$acc['starter'] || $x['p']['theme'] === 'ankommen';
    return false;
}

/** Mail „Dein Monat X ist da“ am Freischalttag, genau einmal pro Monat und Mitglied. */
function mv_month_run(PDO $pdo): void
{
    $q = $pdo->query("SELECT * FROM mv_subs WHERE kind = 'method' AND status = 'aktiv' AND mailed = 1 AND open_mailed = 1 AND email <> '' ORDER BY id LIMIT 200");
    $months = null;
    foreach ($q->fetchAll() as $s) {
        if (mv_open_at($s) > time()) continue;
        $mm = mv_member_month($pdo, $s);
        if ($mm['n'] <= (int)$s['month_mailed']) continue;
        // Programm Schritt für Schritt (26.10.2026): Mail erst, wenn sie den Monat auch wirklich erreicht hat
        $mq = $pdo->prepare('SELECT id FROM mv_members WHERE lower(email) = ?');
        $mq->execute([strtolower((string)$s['email'])]);
        $mid = (int)$mq->fetchColumn();
        if ($mm['n'] > 1) $mm['n'] = min($mm['n'], max(1, mv_method_path($pdo, ['month' => $mm, 'starter' => false], $mid ? mv_done_first($pdo, $mid) : [])['month']));
        if ($mm['n'] <= (int)$s['month_mailed']) continue;
        $u = $pdo->prepare('UPDATE mv_subs SET month_mailed = ? WHERE id = ? AND month_mailed < ?');
        $u->execute([$mm['n'], $s['id'], $mm['n']]);
        if ($u->rowCount() !== 1) continue;
        $months = $months ?? mv_method_months($pdo)['months'];
        $p = $months[$mm['n'] - 1] ?? null;
        $en = $s['lang'] === 'en';
        $first = forma_first_name((string)$s['name'] ?: ($en ? 'friend' : 'meine Liebe'));
        $pt = $p ? mv_t($p, 'title', $en ? 'en' : 'de') : '';
        $lines = $en ? [
            "Dear $first,",
            'your month ' . $mm['n'] . ' is here' . ($pt !== '' ? ': ' . $pt : '') . '. It is waiting for you in your members area, along with its workbook.',
            'Just start with the first video. One small step is enough.',
        ] : [
            "Hallo $first,",
            'dein Monat ' . $mm['n'] . ' ist da' . ($pt !== '' ? ': ' . $pt : '') . '. Er wartet in deinem Mitgliederbereich auf dich, zusammen mit dem Workbook.',
            'Fang einfach mit dem ersten Video an. Ein kleiner Schritt reicht.',
        ];
        $pfx = (int)$s['test'] === 1 ? '[TEST] ' : '';
        try { mv_send_member_mail($s, $pfx . ($en ? 'Your month ' . $mm['n'] . ' is open' : 'Dein Monat ' . $mm['n'] . ' ist offen'), $en ? 'something new for you ♡' : 'etwas Neues für dich ♡', 'Movecia Mindful Movement Method', $lines, mv_area(), $en ? 'Open my month' : 'Zu meinem Monat', ''); }
        catch (Throwable $e) { error_log('MV month mail: ' . $e->getMessage()); }
    }
}

// --- Bibliothek -------------------------------------------------------------------------------------

function mv_programs(PDO $pdo, string $audience = 'method', bool $onlyOpen = false): array
{
    $q = $pdo->prepare('SELECT * FROM mv_programs WHERE audience = ? ' . ($onlyOpen ? "AND visible = 1 AND (unlock = '' OR unlock <= ?) " : 'AND ? = ? ') . 'ORDER BY CASE WHEN unlock = \'\' THEN 0 ELSE 1 END, unlock ' . ($audience === 'unfold' ? 'ASC' : 'DESC') . ', sort, id');
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
    $q->execute($onlyOpen ? [$audience, $today] : [$audience, 1, 1]);
    return $q->fetchAll();
}

function mv_program(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM mv_programs WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function mv_lessons(PDO $pdo, int $programId, bool $onlyVisible = false): array
{
    $q = $pdo->prepare('SELECT * FROM mv_lessons WHERE program_id = ?' . ($onlyVisible ? ' AND visible = 1' : '') . ' ORDER BY sort, id');
    $q->execute([$programId]);
    return $q->fetchAll();
}

function mv_lesson(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM mv_lessons WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function mv_files(PDO $pdo, int $programId): array
{
    $q = $pdo->prepare('SELECT * FROM mv_files WHERE program_id = ? ORDER BY id');
    $q->execute([$programId]);
    return $q->fetchAll();
}

function mv_file_dir(): string
{
    $d = forma_data_dir() . '/method-files';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}

/** Ankommen-Modul und erstes Monatsprogramm einmal anlegen, damit Vivien nur noch Videos dazulegt. */
function mv_seed(PDO $pdo): void
{
    mv_schema($pdo);
    if ((int)$pdo->query('SELECT COUNT(*) FROM mv_programs')->fetchColumn() > 0) return;
    $now = date('c');
    $ins = $pdo->prepare('INSERT INTO mv_programs (audience, title_de, title_en, intro_de, intro_en, theme, unlock, sort, created_at) VALUES (?,?,?,?,?,?,?,?,?)');
    $ins->execute(['method', 'Ankommen', 'Arrive', 'Schön, dass du da bist. Hier lernst du die Method kennen, findest deinen Platz und deine ersten kleinen Rituale. Fang einfach an, es ist nie zu spät.', 'I am so glad you are here. This is where you get to know the Method and find your place and your first small rituals. Simply begin, it is never too late.', 'ankommen', '', 0, $now]);
    $a = (int)$pdo->lastInsertId();
    $ins->execute(['method', 'Rückzug & Ruhe', 'Retreat & Rest', 'Manche Wochen sind laut und voll. Dieses Programm schenkt dir kleine, ruhige Inseln: sanfte Flows für müde Tage, Atem für volle Tage und Momente nur für dich.', 'Some weeks are loud and full. This programme gives you little islands of calm: gentle flows for tired days, breath for full days and moments just for you.', 'winter', '2026-12-01', 1, $now]);
    $d = (int)$pdo->lastInsertId();
    $l = $pdo->prepare('INSERT INTO mv_lessons (program_id, title_de, title_en, text_de, text_en, cat, energy, minutes, sort, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ([
        [$a, 'Willkommen in der Method', 'Welcome to the Method', 'Ein paar Worte von mir: was dich hier erwartet und wie du die Bibliothek für dich nutzt.', 'A few words from me: what is waiting for you here and how to make the library your own.', 'talk', 'any', 6],
        [$a, 'Dein erster Flow', 'Your first flow', 'Ein sanfter Einstieg, bei dem du deinen Körper wieder spürst.', 'A gentle start to feel your body again.', 'flow', 'any', 15],
        [$a, 'Atem, der dich auffängt', 'Breath that holds you', 'Eine kurze Atemübung für jeden Moment, in dem es zu viel wird.', 'A short breathing practice for any moment when it all gets too much.', 'breath', 'tired', 5],
        [$d, 'Morgen-Flow für dunkle Tage', 'Morning flow for dark days', 'Langsam wach werden, Wärme in den Körper bringen.', 'Waking up slowly, bringing warmth into the body.', 'flow', 'tired', 15],
        [$d, 'Somatisches Loslassen', 'Somatic release', 'Schütteln, Wiegen, Nachspüren: Spannung darf gehen.', 'Shaking, swaying, noticing: letting tension go.', 'somatic', 'tired', 15],
        [$d, 'Kraft ohne Erschöpfung', 'Strength without exhaustion', 'Bodyweight in deinem Tempo, du bleibst bei dir.', 'Bodyweight training at your own pace, staying connected to yourself.', 'body', 'full', 30],
        [$d, 'Box Breathing für volle Tage', 'Box breathing for full days', 'Vier Takte, die dein Nervensystem beruhigen.', 'Four counts that calm your nervous system.', 'breath', 'full', 5],
        [$d, 'Abendmeditation: Rückzug', 'Evening meditation: retreat', 'Den Tag ablegen und weich werden.', 'Letting go of the day and softening.', 'meditation', 'tired', 15],
    ] as $i => $x) $l->execute([...$x, $i, $now]);
    mv_seed_unfold($pdo);
    mv_scripts_sync($pdo, true);
    mv_prod_sync($pdo, true);
}

/** Unfold: drei Monate mit je einem Programm, darin die 26 Bewegungsvideos aus dem Filmplan (api/seed/unfold-videos.json). */
function mv_seed_unfold(PDO $pdo): void
{
    $j = json_decode((string)@file_get_contents(__DIR__ . '/seed/unfold-videos.json'), true);
    if (!$j) return;
    $start = new DateTimeImmutable($j['start']);
    $now = date('c');
    $ids = [];
    $p = $pdo->prepare('INSERT INTO mv_programs (audience, title_de, title_en, intro_de, intro_en, theme, unlock, sort, created_at) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($j['months'] as $i => $m) {
        $unlock = $start->modify('+' . (($m['from'] - 1) * 7) . ' days')->format('Y-m-d');
        $p->execute(['unfold', 'Monat ' . ($i + 1) . ' · ' . $m['de'], 'Month ' . ($i + 1) . ' · ' . $m['en'], $m['intro_de'], $m['intro_en'], strtolower($m['de']), $unlock, $i, $now]);
        $ids[$i] = (int)$pdo->lastInsertId();
    }
    $l = $pdo->prepare('INSERT INTO mv_lessons (program_id, title_de, title_en, text_de, text_en, cat, energy, minutes, sort, unlock, script_de, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($j['videos'] as $i => $v) $l->execute([$ids[$v['month']], $v['title_de'], $v['title_en'], $v['text_de'], $v['text_en'], $v['cat'], $v['energy'], $v['minutes'], $i, $v['unlock'], $v['script_de'], $now]);
}

/**
 * Sprechtexte (Voice-over/Talking Head) DE + EN für alle Unfold- und Method-Lektionen (Vivien, 25.10.2026:
 * Storytelling statt Übungslisten, echte Übungsnamen). Quelle api/seed/lesson-scripts.json, Schlüssel = title_de.
 * Überschreibt nur leere Texte oder das alte Übungsgerüst aus dem Filmplan, nie eigene Änderungen.
 */
function mv_scripts_sync(PDO $pdo, bool $force = false): void
{
    $ver = 1;
    $c = mm_cfg('scripts') ?? [];
    if (!$force && (int)($c['v'] ?? 0) >= $ver) return;
    $map = json_decode((string)@file_get_contents(__DIR__ . '/seed/lesson-scripts.json'), true) ?: [];
    $old = [];
    $seed = json_decode((string)@file_get_contents(__DIR__ . '/seed/unfold-videos.json'), true) ?: [];
    foreach ($seed['videos'] ?? [] as $v) $old[$v['title_de']] = trim((string)$v['script_de']);
    $u = $pdo->prepare('UPDATE mv_lessons SET script_de = ?, script_en = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id, title_de, script_de, script_en FROM mv_lessons')->fetchAll() as $l) {
        $t = $map[$l['title_de']] ?? null;
        if (!$t) continue;
        $de = trim((string)$l['script_de']);
        $newDe = ($de === '' || $de === ($old[$l['title_de']] ?? " ")) ? $t['de'] : (string)$l['script_de'];
        $newEn = trim((string)$l['script_en']) === '' ? $t['en'] : (string)$l['script_en'];
        if ($newDe !== $l['script_de'] || $newEn !== $l['script_en']) $u->execute([$newDe, $newEn, $l['id']]);
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM mv_lessons')->fetchColumn() > 0) { $c['v'] = $ver; mm_save_cfg('scripts', $c); }
}

/**
 * Produktionsplan (Vivien, 26.10.2026): Übungslisten und „fertig bis“ für alle Videos, dazu die Talking Heads für
 * Method und Unfold und je Prä-/Postnatal-Einheit ein Talking Head („Vertiefung“) und ein Bewegungsvideo.
 * Quelle api/seed/produktion.json (gebaut von tools/produktion/build.py). Legt nur an, was fehlt (Schlüssel ukey),
 * und setzt Übungen/Datum nur, wo noch nichts steht. Nie eigene Änderungen überschreiben.
 */
function mv_prod_sync(PDO $pdo, bool $force = false): void
{
    $j = json_decode((string)@file_get_contents(__DIR__ . '/seed/produktion.json'), true);
    if (!$j) return;
    $c = mm_cfg('produktion') ?? [];
    if (!$force && (int)($c['v'] ?? 0) >= (int)$j['v']) return;
    if ((int)$pdo->query('SELECT COUNT(*) FROM mv_lessons')->fetchColumn() === 0) return;
    $now = date('c');
    $u = $pdo->prepare("UPDATE mv_lessons SET ex = CASE WHEN ex = '' THEN ? ELSE ex END, due = CASE WHEN due = '' THEN ? ELSE due END WHERE title_de = ?");
    foreach ($j['ex'] as $t => $list) $u->execute([json_encode($list, JSON_UNESCAPED_UNICODE), (string)($j['due'][$t] ?? ''), $t]);
    foreach ($j['due'] as $t => $d) if (!isset($j['ex'][$t])) $u->execute(['', $d, $t]);
    $pdo->exec("UPDATE mv_lessons SET ex = '' WHERE ex = '\"\"'");
    // Programme finden bzw. für Prä-/Postnatal anlegen
    $prog = function ($aud, $ref) use ($pdo, $j, $now): int {
        if ($aud === 'natal') {
            $q = $pdo->prepare("SELECT id FROM mv_programs WHERE audience = 'natal' AND theme = ?");
            $q->execute(['natal-' . $ref]);
            if ($id = (int)$q->fetchColumn()) return $id;
            [$de, $en] = $j['natal_programs'][$ref];
            $pdo->prepare("INSERT INTO mv_programs (audience, title_de, title_en, theme, sort, created_at) VALUES ('natal',?,?,?,?,?)")->execute([$de, $en, 'natal-' . $ref, $ref === 'prae' ? 0 : 1, $now]);
            return (int)$pdo->lastInsertId();
        }
        $ps = mv_programs($pdo, $aud);
        if (is_int($ref)) return (int)($ps[$ref]['id'] ?? 0);
        foreach ($ps as $p) if ($p['title_de'] === $ref) return (int)$p['id'];
        return (int)($ps[1]['id'] ?? $ps[0]['id'] ?? 0);
    };
    // v2 (Vivien, 26.10.2026: kein Gymnastikball): Prä-/Postnatal-Einträge vom 26.10. einmal mit den neuen Texten und Übungen auffrischen
    if ((int)($c['v'] ?? 0) === 1) {
        $up = $pdo->prepare('UPDATE mv_lessons SET title_de = ?, title_en = ?, text_de = ?, text_en = ?, script_de = ?, script_en = ?, ex = ? WHERE ukey = ?');
        foreach ($j['new'] as $n) if (str_starts_with($n['ukey'], 'natal:') || str_starts_with($n['ukey'], 'unfold:') || str_starts_with($n['ukey'], 'method:'))
            $up->execute([$n['title_de'], $n['title_en'], $n['text_de'], $n['text_en'], $n['de'], $n['en'], $n['ex'] ? json_encode($n['ex'], JSON_UNESCAPED_UNICODE) : '', $n['ukey']]);
    }
    // v3 (Vivien, 26.10.2026: Flow mit Voice-over je Übung, Mediathek): vorhandene Clips in die Mediathek übernehmen,
    // dann Schritte (Übung + Sprechtext + Sekunden) und Übungslisten aus dem Seed setzen
    if ((int)($c['v'] ?? 0) < 3) {
        foreach ($pdo->query("SELECT * FROM mv_lessons WHERE prod LIKE '%\"c%'")->fetchAll() as $l) {
            $ex = mv_lesson_ex($l);
            foreach (mv_lesson_clips_old($l) as $i => $g) if (isset($ex[$i])) mv_clip_set($pdo, (string)$ex[$i][0], (string)($ex[$i][1] ?? ''), $g);
        }
        $js = fn($st) => [json_encode($st, JSON_UNESCAPED_UNICODE), json_encode(array_map(fn($x) => [$x[0], $x[1]], $st), JSON_UNESCAPED_UNICODE)];
        $setT = $pdo->prepare("UPDATE mv_lessons SET steps = ?, ex = ? WHERE title_de = ? AND ukey = ''");
        foreach ($j['steps'] ?? [] as $t => $st) $setT->execute([...$js($st), $t]);
        $setK = $pdo->prepare('UPDATE mv_lessons SET steps = ?, ex = ?, mode = ? WHERE ukey = ?');
        foreach ($j['new'] as $n) if (!empty($n['steps'])) $setK->execute([...$js($n['steps']), (string)($n['mode'] ?? ''), $n['ukey']]);
        foreach ($j['steps'] ?? [] as $st) foreach ($st as $x) mv_clip_ensure($pdo, $x[0], $x[1]);
        foreach ($j['new'] as $n) foreach ($n['steps'] ?? [] as $x) mv_clip_ensure($pdo, $x[0], $x[1]);
    }
    // v4 (Vivien, 26.10.2026): Method Monat 1 mit 12 Videos, neue Voice-over-Texte je Übung, Talking Head zum Monat neu
    if (in_array((int)($c['v'] ?? 0), [3, 4, 5, 6], true)) {
        $js = fn($st) => [json_encode($st, JSON_UNESCAPED_UNICODE), json_encode(array_map(fn($x) => [$x[0], $x[1]], $st), JSON_UNESCAPED_UNICODE)];
        $setT = $pdo->prepare("UPDATE mv_lessons SET steps = ?, ex = ? WHERE title_de = ? AND ukey = ''");
        foreach ($j['m1_steps'] ?? [] as $t) if (isset($j['steps'][$t])) $setT->execute([...$js($j['steps'][$t]), $t]);
        $up = $pdo->prepare('UPDATE mv_lessons SET title_de = ?, title_en = ?, text_de = ?, text_en = ?, script_de = ?, script_en = ?, minutes = ? WHERE ukey = ?');
        // v7: „Starte hier“-Video erklärt den Aufbau der Method (neuer Sprechtext)
        if (!empty($j['welcome']['de'])) $pdo->prepare("UPDATE mv_lessons SET script_de = ?, script_en = ? WHERE title_de = ? AND ukey = ''")->execute([$j['welcome']['de'], $j['welcome']['en'], $j['welcome']['title']]);
        // v7: Bewegungsvideos von Monat 1 bekommen die Sprechtexte der Flows (für ein späteres Umschalten auf Flow)
        $setK = $pdo->prepare('UPDATE mv_lessons SET steps = ? WHERE ukey = ?');
        foreach ($j['new'] as $n) if (($n['mode'] ?? '') === 'move' && str_starts_with($n['ukey'], 'method:m1:')) $setK->execute([json_encode($n['steps'], JSON_UNESCAPED_UNICODE), $n['ukey']]);
        foreach ($j['new'] as $n) if ($n['ukey'] === 'method:rueckzug') $up->execute([$n['title_de'], $n['title_en'], $n['text_de'], $n['text_en'], $n['de'], $n['en'], (int)$n['minutes'], $n['ukey']]);
    }
    $has = $pdo->prepare('SELECT COUNT(*) FROM mv_lessons WHERE ukey = ?');
    $ins = $pdo->prepare('INSERT INTO mv_lessons (program_id, title_de, title_en, text_de, text_en, cat, energy, minutes, sort, unlock, script_de, script_en, ukey, due, ex, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($j['new'] as $n) {
        $has->execute([$n['ukey']]);
        if ((int)$has->fetchColumn() > 0) continue;
        $pid = $prog($n['aud'], $n['prog']);
        if (!$pid) continue;
        $ins->execute([$pid, $n['title_de'], $n['title_en'], $n['text_de'], $n['text_en'], $n['cat'], $n['energy'], (int)$n['minutes'], (int)$n['sort'], (string)$n['unlock'],
            $n['de'], $n['en'], $n['ukey'], $n['due'], $n['ex'] ? json_encode($n['ex'], JSON_UNESCAPED_UNICODE) : '', $now]);
        if (!empty($n['steps'])) $pdo->prepare('UPDATE mv_lessons SET steps = ?, ex = ?, mode = ? WHERE id = ?')->execute([json_encode($n['steps'], JSON_UNESCAPED_UNICODE), json_encode(array_map(fn($x) => [$x[0], $x[1]], $n['steps']), JSON_UNESCAPED_UNICODE), (string)($n['mode'] ?? ''), (int)$pdo->lastInsertId()]);
    }
    // v8 (Vivien, 26.10.2026): „Willkommen in der Method“ gefühlvoller, mehr Herz statt Erklärung
    if ((int)($c['v'] ?? 0) < 8 && !empty($j['welcome']['de'])) $pdo->prepare("UPDATE mv_lessons SET script_de = ?, script_en = ? WHERE title_de = ? AND ukey = ''")->execute([$j['welcome']['de'], $j['welcome']['en'], $j['welcome']['title']]);
    // feste Reihenfolge der Videos in Monat 1
    if ((int)($c['v'] ?? 0) < 7 && !empty($j['m1_order'])) {
        $pid = $prog('method', 'Rückzug & Ruhe');
        foreach ($j['m1_order'] as $pos => $ref) {
            [$t, $v] = explode(':', $ref, 2);
            $pdo->prepare('UPDATE mv_lessons SET sort = ? WHERE program_id = ? AND ' . ($t === 'k' ? 'ukey' : 'title_de') . ' = ?')->execute([$pos, $pid, $v]);
        }
    }
    $c['v'] = (int)$j['v'];
    mm_save_cfg('produktion', $c);
}

/**
 * Schritte eines Videos (Vivien, 26.10.2026): je Übung Name DE/EN, Voice-over-Text DE/EN und Sekunden.
 * Flow: Clip läuft in Schleife, Timer = Sekunden (Standard 2 Min.), Viviens Voice-over je Übung.
 * Bewegungsvideo: dieselben Clips, die Teilnehmerin wählt 30 oder 45 Sekunden, ohne Sprechen.
 */
function mv_lesson_steps(array $l): array
{
    $out = [];
    $s = json_decode((string)($l['steps'] ?? ''), true);
    if (is_array($s) && $s) {
        foreach ($s as $x) $out[] = ['de' => (string)$x[0], 'en' => (string)(($x[1] ?? '') ?: $x[0]), 'tde' => (string)($x[2] ?? ''), 'ten' => (string)($x[3] ?? ''), 'sec' => max(20, min(900, (int)($x[4] ?? 120)))];
        return $out;
    }
    $e = json_decode((string)($l['ex'] ?? ''), true);
    if (is_array($e)) foreach ($e as $x) if (is_array($x) && ($x[0] ?? '') !== '') $out[] = ['de' => (string)$x[0], 'en' => (string)(($x[1] ?? '') ?: $x[0]), 'tde' => '', 'ten' => '', 'sec' => 120];
    return $out;
}

/** Schritte speichern (Form [[de, en, text_de, text_en, sek], …]) und Übungsliste mitziehen. */
function mv_lesson_steps_save(PDO $pdo, int $id, array $steps): void
{
    $raw = array_map(fn($x) => [$x['de'], $x['en'], $x['tde'], $x['ten'], (int)$x['sec']], $steps);
    $pdo->prepare('UPDATE mv_lessons SET steps = ?, ex = ? WHERE id = ?')->execute([json_encode($raw, JSON_UNESCAPED_UNICODE), json_encode(array_map(fn($x) => [$x[0], $x[1]], $raw), JSON_UNESCAPED_UNICODE), $id]);
}

/** Übungsliste einer Lektion: [[de, en], …] */
function mv_lesson_ex(array $l): array
{
    return array_map(fn($x) => [$x['de'], $x['en']], mv_lesson_steps($l));
}

/** flow = mit Voice-over und festem Timer je Übung, move = Bewegungsvideo mit 30/45 Sek. ohne Sprechen */
function mv_lesson_mode(array $l): string
{
    $m = (string)($l['mode'] ?? '');
    if ($m === 'flow' || $m === 'move') return $m;
    return str_ends_with((string)($l['ukey'] ?? ''), ':move') ? 'move' : 'flow';
}

function mv_lesson_prod(array $l): array
{
    $x = json_decode((string)($l['prod'] ?? ''), true);
    return is_array($x) ? $x : [];
}

/** Aus kurzen Übungs-Clips gebaut? Alles außer Talking Heads. Breathwork und (geführte) Meditation sind Talking Heads
 *  (Vivien, 26.10.2026): ein ganzes Video je Sprache mit Teleprompter. */
function mv_lesson_is_clips(array $l): bool
{
    return !in_array($l['cat'], ['talk', 'breath', 'meditation'], true) && mv_lesson_steps($l) !== [];
}

/** Clips aus der ersten Fassung (prod c<i>, vor der Mediathek) */
function mv_lesson_clips_old(array $l): array
{
    $out = [];
    foreach (mv_lesson_prod($l) as $k => $v) if (preg_match('/^c(\d+)$/', (string)$k, $m) && is_string($v) && $v !== '') $out[(int)$m[1]] = $v;
    return $out;
}

// --- Mediathek: jede Übung einmal gefilmt, überall wiederverwendet ---------------------------------------

const MV_SEASONS = ['fruehling' => ['Frühling', 'Spring', '🌸'], 'sommer' => ['Sommer', 'Summer', '☀️'], 'herbst' => ['Herbst', 'Autumn', '🍂'], 'winter' => ['Winter', 'Winter', '❄️'], 'immer' => ['Ganzjährig', 'All year', '♾️']];
const MV_STYLES = ['yoga' => 'Yoga', 'pilates' => 'Tiefenmuskulatur', 'somatic' => 'Somatic', 'bodyweight' => 'Bodyweight', 'mobility' => 'Mobility', 'atem' => 'Atem', 'ruhe' => 'Ruhe & Meditation', 'natal' => 'Prä-/Postnatal'];

/** Schlüssel einer Übung: der Name, klein geschrieben, ohne Sonderzeichen (gleicher Name = gleicher Clip) */
function mv_clip_key(string $name): string
{
    $n = strtr(mb_strtolower(trim($name)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'é' => 'e']);
    $k = trim((string)preg_replace('/[^a-z0-9]+/', '-', $n), '-');
    return $k !== '' ? $k : substr(md5($name), 0, 12);
}

function mv_clips_all(PDO $pdo, bool $fresh = false): array
{
    static $c = null;
    if ($c === null || $fresh) {
        $c = [];
        foreach ($pdo->query('SELECT * FROM mv_clips ORDER BY name_de')->fetchAll() as $r) $c[$r['ckey']] = $r;
    }
    return $c;
}

/** Übung in der Mediathek anlegen (falls neu), mit Stil-Vorschlag aus dem Namen */
function mv_clip_ensure(PDO $pdo, string $de, string $en = ''): array
{
    $k = mv_clip_key($de);
    $all = mv_clips_all($pdo);
    if (isset($all[$k])) return $all[$k];
    $low = mb_strtolower($de . ' ' . $en);
    $sty = [];
    foreach (['pilates' => 'pilates|clamshell|dead bug|bird dog|swimming|roll up|toe tap|heel slide|side leg', 'somatic' => 'somati|shaking|ausschütteln|abklopfen|tapping|orientieren|nachspüren|pandiculation|pendeln|wiegen|selbstumarmung',
        'bodyweight' => 'kniebeuge|squat|lunge|ausfallschritt|plank|stütz|wandsitz|wall|liegestütz|bear|bridge|wadenheben|rudern|hüpfen', 'atem' => 'atem|atmung|ausatm|breath|box|summen|einatmen',
        'ruhe' => 'savasana|body scan|meditation|den tag|ausklang|ruhe', 'yoga' => 'asana|hund|krieger|kindhaltung|baum|göttin|dreieck|taube|katze|eidechse|sphinx|kobra|malasana|happy baby|thread|figur 4|drehung|berghaltung|tor ',
        'mobility' => 'kreise|kreisen|welle|roll down|hip hinge|mobil|achter|seitbeuge|reach|flow'] as $st => $re) if (preg_match('/' . $re . '/u', $low)) $sty[] = $st;
    $pdo->prepare('INSERT OR IGNORE INTO mv_clips (ckey, name_de, name_en, styles, seasons, created_at) VALUES (?,?,?,?,?,?)')->execute([$k, $de, $en !== '' ? $en : $de, implode(',', $sty), 'immer', date('c')]);
    return mv_clips_all($pdo, true)[$k];
}

function mv_clip_set(PDO $pdo, string $de, string $en, string $guid): void
{
    $c = mv_clip_ensure($pdo, $de, $en);
    $pdo->prepare('UPDATE mv_clips SET guid = ?, updated_at = ? WHERE id = ?')->execute([$guid, date('c'), $c['id']]);
    mv_clips_all($pdo, true);
}

/** Clip je Schritt: [i => Mediathek-Zeile] (fehlt eine Übung in der Mediathek, wird sie angelegt) */
function mv_lesson_clip_rows(PDO $pdo, array $l): array
{
    $out = [];
    foreach (mv_lesson_steps($l) as $i => $x) $out[$i] = mv_clips_all($pdo)[mv_clip_key($x['de'])] ?? mv_clip_ensure($pdo, $x['de'], $x['en']);
    return $out;
}

/** In wie vielen Videos kommt jede Übung vor? [ckey => [[Lektion-ID, Titel], …]] */
function mv_clip_usage(PDO $pdo): array
{
    $u = [];
    foreach ($pdo->query("SELECT id, title_de, steps, ex, cat FROM mv_lessons WHERE visible = 1 AND cat <> 'talk'")->fetchAll() as $l)
        foreach (mv_lesson_steps($l) as $x) $u[mv_clip_key($x['de'])][(int)$l['id']] = (string)$l['title_de'];
    return $u;
}

// --- Voice-over je Übung (nur Flow) ------------------------------------------------------------------------

function mv_voice_dir(): string
{
    $d = forma_data_dir() . '/voice';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

/** Eigene Aufnahme dieser Lektion/Übung, sonst eine gespeicherte Aufnahme derselben Übung mit genau demselben Text. */
function mv_voice_path(int $lid, int $i, string $g): ?string
{
    $g = $g === 'en' ? 'en' : 'de';
    foreach (glob(mv_voice_dir() . '/L' . $lid . '-' . $i . '-' . $g . '.*') ?: [] as $f) return $f;
    $t = mv_voice_take_for($lid, $i, $g);
    return $t ? $t['path'] : null;
}

/** Normalisierter Text-Fingerabdruck (Leerraum egal) */
function mv_text_hash(string $t): string
{
    return sha1(trim((string)preg_replace('/\s+/u', ' ', $t)));
}

/** Gespeicherte Aufnahme aus der Bibliothek für diese Übung (gleiche Übung, Sprache und gleicher Text) */
function mv_voice_take_for(int $lid, int $i, string $g): ?array
{
    static $cache = [];
    $k = $lid . ':' . $i . ':' . $g;
    if (array_key_exists($k, $cache)) return $cache[$k];
    $pdo = forma_db();
    $l = mv_lesson($pdo, $lid);
    $st = $l ? mv_lesson_steps($l) : [];
    $x = $st[$i] ?? null;
    $txt = $x ? ($g === 'en' ? $x['ten'] : $x['tde']) : '';
    if (!$x || trim($txt) === '') return $cache[$k] = null;
    try {
        $q = $pdo->prepare('SELECT * FROM mv_voices WHERE ckey = ? AND lang = ? AND text_hash = ? ORDER BY id DESC');
        $q->execute([mv_clip_key($x['de']), $g, mv_text_hash($txt)]);
        foreach ($q->fetchAll() as $r) { $f = mv_voice_dir() . '/' . $r['file']; if (is_file($f)) return $cache[$k] = $r + ['path' => $f]; }
    } catch (Throwable $e) {}
    return $cache[$k] = null;
}

/** Neue Aufnahme in die Bibliothek legen (eigene Datei-Kopie, damit sie bleibt, auch wenn die Lektion neu eingesprochen wird) */
function mv_voice_take_add(PDO $pdo, int $lid, int $i, string $g, string $src): void
{
    $l = mv_lesson($pdo, $lid);
    $x = $l ? (mv_lesson_steps($l)[$i] ?? null) : null;
    if (!$x || !is_file($src)) return;
    $txt = $g === 'en' ? $x['ten'] : $x['tde'];
    $name = 'T' . bin2hex(random_bytes(6)) . '.' . strtolower(pathinfo($src, PATHINFO_EXTENSION));
    if (!@copy($src, mv_voice_dir() . '/' . $name)) return;
    $pdo->prepare('INSERT INTO mv_voices (ckey, lang, text_hash, text, file, lesson_id, step, created_at) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([mv_clip_key($x['de']), $g, mv_text_hash($txt), $txt, $name, $lid, $i, date('c')]);
}

/** Alle gespeicherten Aufnahmen einer Übung in einer Sprache (neueste zuerst) */
function mv_voice_takes(PDO $pdo, string $ckey, string $g): array
{
    $q = $pdo->prepare('SELECT * FROM mv_voices WHERE ckey = ? AND lang = ? ORDER BY id DESC');
    $q->execute([$ckey, $g === 'en' ? 'en' : 'de']);
    return array_values(array_filter($q->fetchAll(), fn($r) => is_file(mv_voice_dir() . '/' . $r['file'])));
}

/** Zahl der gespeicherten Aufnahmen je Übung: [ckey => ['de' => n, 'en' => n]] */
function mv_voice_counts(PDO $pdo): array
{
    $o = [];
    foreach ($pdo->query('SELECT ckey, lang, COUNT(*) n FROM mv_voices GROUP BY ckey, lang')->fetchAll() as $r) $o[$r['ckey']][$r['lang']] = (int)$r['n'];
    return $o;
}

/** Einmalig: Aufnahmen von vor der Bibliothek dort eintragen */
function mv_voice_migrate(PDO $pdo): void
{
    $c = mm_cfg('voices') ?? [];
    if ((int)($c['v'] ?? 0) >= 1) return;
    foreach (glob(mv_voice_dir() . '/L*-*-*.*') ?: [] as $f)
        if (preg_match('/^L(\d+)-(\d+)-(de|en)\./', basename($f), $m)) mv_voice_take_add($pdo, (int)$m[1], (int)$m[2], $m[3], $f);
    mm_save_cfg('voices', ['v' => 1]);
}

function mv_take_sig(int $id): string { return substr(hash_hmac('sha256', 'take:' . $id, mv_secret()), 0, 24); }
function mv_take_url(int $id): string { return '/api/method-voice.php?t=' . $id . '&k=' . mv_take_sig($id); }

function mv_voice_sig(int $lid, int $i, string $g): string
{
    return substr(hash_hmac('sha256', 'voice:' . $lid . ':' . $i . ':' . $g, mv_secret()), 0, 24);
}

function mv_voice_url(int $lid, int $i, string $g): string
{
    $f = mv_voice_path($lid, $i, $g);
    return $f ? '/api/method-voice.php?l=' . $lid . '&i=' . $i . '&g=' . $g . '&v=' . filemtime($f) . '&k=' . mv_voice_sig($lid, $i, $g) : '';
}

/** Stand eines Videos: wie viele Clips und Voice-overs fehlen noch? */
/** Zielgruppe (Programm-audience) einer Lektion: method, unfold, natal, academy … */
function mv_lesson_aud(PDO $pdo, array $l): string
{
    static $cache = [];
    $pid = (int)($l['program_id'] ?? 0);
    if (!isset($cache[$pid])) { $q = $pdo->prepare('SELECT audience FROM mv_programs WHERE id = ?'); $q->execute([$pid]); $cache[$pid] = (string)($q->fetchColumn() ?: 'method'); }
    return $cache[$pid];
}

/** Nur auf Deutsch (Vivien, 30.10.2026): Englisch gibt es nur noch bei The Method. Unfold, Prä-/Postnatal und Academy brauchen keine englischen Videos, Voice-overs oder Teleprompter-Texte. */
function mv_lesson_de_only(PDO $pdo, array $l): bool
{
    return mv_lesson_aud($pdo, $l) !== 'method';
}

function mv_lesson_status(PDO $pdo, array $l): array
{
    $st = mv_lesson_steps($l);
    $deOnly = mv_lesson_de_only($pdo, $l);
    if (!mv_lesson_is_clips($l)) {
        $d = (string)$l['video_de'] !== ''; $e = (string)$l['video_en'] !== '';
        return ['n' => 0, 'clips' => 0, 'de' => (int)$d, 'en' => (int)$e, 'flow' => false, 'deonly' => $deOnly, 'done' => $d && ($e || $deOnly)];
    }
    $rows = mv_lesson_clip_rows($pdo, $l);
    $flow = mv_lesson_mode($l) === 'flow';
    $c = $d = $e = 0;
    foreach ($st as $i => $x) {
        if (($rows[$i]['guid'] ?? '') !== '') $c++;
        if ($flow) { if (mv_voice_path((int)$l['id'], $i, 'de')) $d++; if (!$deOnly && mv_voice_path((int)$l['id'], $i, 'en')) $e++; }
    }
    $n = count($st);
    return ['n' => $n, 'clips' => $c, 'de' => $d, 'en' => $e, 'flow' => $flow, 'deonly' => $deOnly, 'done' => $n > 0 && $c >= $n && (!$flow || ($d >= $n && ($deOnly || $e >= $n)))];
}

/** Ist das Video fertig? Aus Clips: jede Übung hat einen Clip (Flow: und beide Voice-overs). Talking Head: DE und EN. */
function mv_lesson_done(array $l): bool
{
    return mv_lesson_status(forma_db(), $l)['done'];
}

/** Musik-Vorschläge für den Player (Spotify-Playlists aus der Verwaltung, sonst ruhiges Klavier) */
function mv_music_lists(): array
{
    $out = function_exists('natal_spotify_lists') ? natal_spotify_lists() : [];
    if (!$out) $out = [['title' => 'Peaceful Piano', 'url' => 'https://open.spotify.com/embed/playlist/37i9dQZF1DX4sWSpwq3LiO?utm_source=generator&theme=0']];
    return $out;
}

/**
 * Daten für den Player im Mitgliederbereich. Nur wenn alles da ist (Clips, beim Flow auch Voice-over), sonst null.
 * $preview: Vorschau in der Verwaltung, zeigt auch Unfertiges (fehlende Clips als Platzhalter, Text statt Voice-over).
 */
function mv_flow_data(array $l, string $lang, bool $preview = false): ?array
{
    if (!mv_lesson_is_clips($l)) return null;
    if ($lang === 'en' && mv_lesson_de_only(forma_db(), $l)) $lang = 'de'; // Unfold/Prä-/Postnatal nur Deutsch
    $bunny = mv_bunny()['lib'] !== '';
    if (!$bunny && !$preview) return null;
    $pdo = forma_db();
    $rows = mv_lesson_clip_rows($pdo, $l);
    $mode = mv_lesson_mode($l);
    $g = $lang === 'en' ? 'en' : 'de';
    $items = [];
    foreach (mv_lesson_steps($l) as $i => $x) {
        $guid = (string)($rows[$i]['guid'] ?? '');
        $src = $guid !== '' && $bunny ? mv_embed($guid, true) . '&autoplay=true&loop=true&muted=true' : '';
        if ($src === '' && !$preview) return null;
        $voice = $mode === 'flow' ? mv_voice_url((int)$l['id'], $i, $g) : '';
        if ($mode === 'flow' && $voice === '' && !$preview) return null;
        $items[] = ['name' => $g === 'en' ? $x['en'] : $x['de'], 'src' => $src, 'thumb' => $guid !== '' ? mv_thumb($guid) : '', 'sec' => $x['sec'], 'voice' => $voice] + ($preview ? ['text' => $g === 'en' ? $x['ten'] : $x['tde']] : []);
    }
    return ['lang' => $g, 'mode' => $mode, 'title' => mv_t($l, 'title', $g), 'items' => $items, 'music' => mv_music_lists(), 'preview' => $preview,
        'bgm' => array_map(fn($r) => ['url' => mv_bgm_url((int)$r['id']), 't' => (string)$r['title'], 'dur' => (float)$r['dur']], mv_bgm_rows($pdo, $l))];
}

// --- Musik darunter je Video (Vivien, 30.10.2026) ------------------------------------------------------------
// Clips sind stumm. Für das ganze Bewegungsvideo / den ganzen Flow laufen ein oder zwei Lieder aus dem Musik-Ordner
// (Verwaltung → Movecia → Musik) im Hintergrund, beim Flow leise unter dem Voice-over. Ohne Auswahl wählt der Server
// selbst passend zum Video (stabil je Video), damit Vivien nichts tun muss.
function mv_bgm_all(PDO $pdo): array
{
    static $all = null;
    if ($all !== null) return $all;
    try { $all = $pdo->query('SELECT id, title, file, dur, tags FROM mm_music ORDER BY id')->fetchAll(); } catch (Throwable $e) { $all = []; }
    return $all;
}
/** Wie lange läuft das Video ungefähr (Sekunden)? Bewegungsvideo mit 45 s je Übung gerechnet. */
function mv_bgm_need(array $l): int
{
    $n = 0; $sum = 0;
    foreach (mv_lesson_steps($l) as $x) { $n++; $sum += max(20, (int)($x['sec'] ?? 120)); }
    return mv_lesson_mode($l) === 'move' ? $n * 46 + 20 : $sum + 20;
}
/** Automatischer Vorschlag: passende Ordner, stabil gemischt je Video ($seed = andere Mischung). */
function mv_bgm_auto(PDO $pdo, array $l, int $seed = 0): array
{
    $t = mb_strtolower((string)$l['title_de'] . ' ' . (string)$l['title_en']);
    $want = mv_lesson_mode($l) === 'move' ? ['flow', 'lebendig', 'morgen'] : ['meditation', 'flow', 'abend'];
    if (preg_match('/abend|nacht|schlaf|ruhe|loslassen|evening|sleep|savasana|schwere/u', $t)) $want = ['abend', 'meditation', 'flow'];
    elseif (preg_match('/morgen|morning|aufwachen|wach|energie|kraft|stark/u', $t)) $want = ['morgen', 'flow', 'lebendig'];
    $all = array_values(array_filter(mv_bgm_all($pdo), fn($r) => is_file(forma_data_dir() . '/musik/' . basename((string)$r['file']))));
    if (!$all) return [];
    $score = function ($r) use ($want) { $tg = explode(',', (string)$r['tags']); foreach ($want as $i => $w) if (in_array($w, $tg, true)) return $i; return 9; };
    usort($all, fn($a, $b) => [$score($a), crc32($l['id'] . ':' . $seed . ':' . $a['id'])] <=> [$score($b), crc32($l['id'] . ':' . $seed . ':' . $b['id'])]);
    $need = mv_bgm_need($l); $out = []; $t2 = 0;
    foreach ($all as $r) { $out[] = $r; $t2 += max(30, (float)$r['dur'] > 0 ? (float)$r['dur'] : 150) - 2.5; if ($t2 >= $need || count($out) >= 4) break; }
    return $out;
}
/** Die Lieder dieses Videos (gewählt oder automatisch), [] = keine Musik. */
function mv_bgm_rows(PDO $pdo, array $l): array
{
    $v = trim((string)($l['bgm'] ?? ''));
    if ($v === 'off') return [];
    if ($v === '') return mv_bgm_auto($pdo, $l);
    $by = []; foreach (mv_bgm_all($pdo) as $r) $by[(int)$r['id']] = $r;
    $out = []; foreach (explode(',', $v) as $id) if (isset($by[(int)$id])) $out[] = $by[(int)$id];
    return $out ?: mv_bgm_auto($pdo, $l);
}
function mv_bgm_sig(int $id): string { return substr(hash_hmac('sha256', 'bgm:' . $id, mv_secret()), 0, 24); }
function mv_bgm_url(int $id): string { return '/api/method-voice.php?m=' . $id . '&k=' . mv_bgm_sig($id); }

/** Prä-/Postnatal-Videos einer Einheit: ['talk' => Lektion|null, 'move' => Lektion|null] */
function mv_natal_videos(PDO $pdo, string $unit): array
{
    $q = $pdo->prepare('SELECT * FROM mv_lessons WHERE ukey = ? AND visible = 1');
    $out = [];
    foreach (['talk', 'move'] as $k) { $q->execute(['natal:' . $unit . ':' . $k]); $out[$k] = $q->fetch() ?: null; }
    return $out;
}

function mv_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
}

// --- Fortschritt & Journal ------------------------------------------------------------------------------

function mv_done_ids(PDO $pdo, int $mid): array
{
    $q = $pdo->prepare('SELECT DISTINCT lesson_id FROM mv_progress WHERE member_id = ?');
    $q->execute([$mid]);
    return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

/** An wie vielen Tagen diese Woche (ab Montag) war sie bei sich? */
function mv_week_days(PDO $pdo, int $mid): int
{
    $mon = (new DateTimeImmutable('monday this week', new DateTimeZone('Europe/Vienna')))->format('c');
    $q = $pdo->prepare('SELECT COUNT(DISTINCT substr(done_at, 1, 10)) FROM mv_progress WHERE member_id = ? AND done_at >= ?');
    $q->execute([$mid, $mon]);
    return (int)$q->fetchColumn();
}

function mv_fav_ids(PDO $pdo, int $mid): array
{
    $q = $pdo->prepare('SELECT lesson_id FROM mv_favs WHERE member_id = ?');
    $q->execute([$mid]);
    return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

function mv_crypt_key(): string { return hash('sha256', 'journal:' . mv_secret(), true); }

function mv_journal_get(PDO $pdo, int $mid, string $day): string
{
    $q = $pdo->prepare('SELECT body FROM mv_journal WHERE member_id = ? AND day = ?');
    $q->execute([$mid, $day]);
    $b = (string)($q->fetchColumn() ?: '');
    if ($b === '') return '';
    $raw = base64_decode($b);
    if (strlen($raw) < 29) return '';
    $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16);
    return (string)openssl_decrypt(substr($raw, 28), 'aes-256-gcm', mv_crypt_key(), OPENSSL_RAW_DATA, $iv, $tag);
}

function mv_journal_set(PDO $pdo, int $mid, string $day, string $text): void
{
    $text = mb_substr(trim($text), 0, 5000);
    if ($text === '') { $pdo->prepare('DELETE FROM mv_journal WHERE member_id = ? AND day = ?')->execute([$mid, $day]); return; }
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($text, 'aes-256-gcm', mv_crypt_key(), OPENSSL_RAW_DATA, $iv, $tag);
    $pdo->prepare('INSERT INTO mv_journal (member_id, day, body, updated_at) VALUES (?,?,?,?) ON CONFLICT(member_id, day) DO UPDATE SET body = excluded.body, updated_at = excluded.updated_at')
        ->execute([$mid, $day, base64_encode($iv . $tag . $ct), date('c')]);
}

function mv_journal_days(PDO $pdo, int $mid): array
{
    $q = $pdo->prepare('SELECT day FROM mv_journal WHERE member_id = ? ORDER BY day DESC LIMIT 60');
    $q->execute([$mid]);
    return $q->fetchAll(PDO::FETCH_COLUMN);
}

/** Eine Frage pro Tag (wiederholt sich nach der Liste). */
function mv_prompt(string $day, string $lang): string
{
    $de = ['Was hat dein Körper heute gebraucht, und hast du es ihm gegeben?', 'Wofür bist du heute dankbar, auch wenn es klein ist?', 'Was darfst du heute loslassen?', 'Wann hast du dich heute ganz bei dir gefühlt?', 'Was würdest du tun, wenn niemand zuschaut?', 'Welcher Gedanke kam heute immer wieder? Stimmt er?', 'Was gibt dir gerade Energie, und was nimmt sie dir?', 'Welche kleine Sache möchtest du morgen für dich tun?', 'Wo sagst du Ja, obwohl du Nein meinst?', 'Was würdest du deiner besten Freundin heute sagen?', 'Wie fühlt sich dein Atem gerade an?', 'Was ist heute gelungen?', 'Welche Idee lässt dich nicht los?', 'Wann hast du zuletzt laut gelacht?', 'Was brauchst du, um dich sicher zu fühlen?', 'Worauf freust du dich diese Woche?', 'Was möchtest du dir selbst verzeihen?', 'Welche Bewegung hat sich heute gut angefühlt?', 'Was hast du heute über dich gelernt?', 'Wenn dein Körper sprechen könnte, was würde er sagen?', 'Welche Grenze möchtest du liebevoll ziehen?'];
    $en = ['What did your body need today, and did you give it?', 'What are you grateful for today, even if it is small?', 'What can you let go of today?', 'When did you feel fully yourself today?', 'What would you do if nobody was watching?', 'Which thought kept coming back today? Is it true?', 'What gives you energy right now, and what takes it?', 'What small thing would you like to do for yourself tomorrow?', 'Where do you say yes when you mean no?', 'What would you tell your best friend today?', 'How does your breath feel right now?', 'What went well today?', 'Which idea won\'t let you go?', 'When did you last laugh out loud?', 'What do you need to feel safe?', 'What are you looking forward to this week?', 'What would you like to forgive yourself for?', 'Which movement felt good today?', 'What did you learn about yourself today?', 'If your body could speak, what would it say?', 'Which boundary would you like to set lovingly?'];
    $list = $lang === 'en' ? $en : $de;
    return $list[(int)floor(strtotime($day . ' 12:00') / 86400) % count($list)];
}

// --- Bunny Stream ---------------------------------------------------------------------------------------

function mv_bunny(): array
{
    $c = mm_cfg('bunny') ?? [];
    return ['lib' => (string)($c['lib'] ?? ''), 'key' => (string)($c['key'] ?? ''), 'token' => (string)($c['token'] ?? ''), 'cdn' => (string)($c['cdn'] ?? '')];
}

function mv_bunny_ok(): bool { $b = mv_bunny(); return $b['lib'] !== '' && $b['key'] !== ''; }

function mv_bunny_req(string $method, string $path, ?array $body = null): array
{
    $b = mv_bunny();
    if (!mv_bunny_ok()) throw new RuntimeException('Bunny Stream ist noch nicht eingerichtet.');
    $base = rtrim(getenv('MV_BUNNY_API') ?: 'https://video.bunnycdn.com', '/');
    $ch = curl_init($base . '/library/' . rawurlencode($b['lib']) . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['AccessKey: ' . $b['key'], 'Accept: application/json', 'Content-Type: application/json']]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code >= 400) throw new RuntimeException('Bunny antwortet nicht wie erwartet (' . $code . '). Bitte prüf Library-ID und API-Schlüssel.');
    return json_decode((string)$raw, true) ?: [];
}

/** Neues Video anlegen und die Unterschrift für den direkten Upload vom Gerät zu Bunny (TUS) liefern. */
function mv_bunny_new(string $title): array
{
    $b = mv_bunny();
    $v = mv_bunny_req('POST', '/videos', ['title' => $title]);
    $guid = (string)($v['guid'] ?? '');
    if ($guid === '') throw new RuntimeException('Bunny hat kein Video angelegt.');
    $exp = time() + 6 * 3600;
    return ['guid' => $guid, 'lib' => $b['lib'], 'expire' => $exp, 'sig' => hash('sha256', $b['lib'] . $b['key'] . $exp . $guid),
        'endpoint' => getenv('MV_BUNNY_TUS') ?: 'https://video.bunnycdn.com/tusupload'];
}

/** Status: 0 angelegt, 1 hochgeladen, 2 wird verarbeitet, 3 wird umgewandelt, 4 fertig, 5/6 Fehler. */
function mv_bunny_info(string $guid): ?array
{
    if ($guid === '' || !mv_bunny_ok()) return null;
    try { return mv_bunny_req('GET', '/videos/' . rawurlencode($guid)); } catch (Throwable $e) { return null; }
}

function mv_bunny_delete(string $guid): void
{
    if ($guid === '' || !mv_bunny_ok()) return;
    try { mv_bunny_req('DELETE', '/videos/' . rawurlencode($guid)); } catch (Throwable $e) { error_log('MV bunny delete: ' . $e->getMessage()); }
}

/** Player-Adresse (mit Token, wenn der Kopierschutz eingeschaltet ist). */
function mv_embed(string $guid, bool $clip = false): string
{
    $b = mv_bunny();
    if ($guid === '' || $b['lib'] === '') return '';
    $u = 'https://iframe.mediadelivery.net/embed/' . rawurlencode($b['lib']) . '/' . rawurlencode($guid) . '?preload=true&responsive=true' . ($clip ? '' : '&autoplay=false&muted=false'); // Talking Heads: kein Autoplay, sonst startet der Browser sie stumm (Vivien, 30.10.2026: „wieder kein Ton“). Übungs-Clips (clip=true) hängen autoplay/muted selbst an.
    if ($b['token'] !== '') {
        $exp = time() + 4 * 3600;
        $u .= '&token=' . hash('sha256', $b['token'] . $guid . $exp) . '&expires=' . $exp;
    }
    return $u;
}

function mv_thumb(string $guid): string
{
    $b = mv_bunny();
    if ($guid === '' || $b['cdn'] === '') return '';
    return 'https://' . preg_replace('~^https?://~', '', rtrim($b['cdn'], '/')) . '/' . rawurlencode($guid) . '/thumbnail.jpg';
}

/** Video in der Sprache der Frau, sonst das andere. */
function mv_video_for(array $l, string $lang): string
{
    return $lang === 'en' ? ($l['video_en'] ?: $l['video_de']) : ($l['video_de'] ?: $l['video_en']);
}

function mv_t(array $row, string $field, string $lang): string
{
    $v = (string)($row[$field . '_' . $lang] ?? '');
    return $v !== '' ? $v : (string)($row[$field . '_' . ($lang === 'en' ? 'de' : 'en')] ?? '');
}

// --- Gathering-Angebot (Karte im Live-Raum) -----------------------------------------------------------

/** Käufe aus der Gathering-Angebotskarte: Method (Plan m1/m3/m12) oder Unfold (u1/u3). */
function mv_spotlight_checkout(PDO $pdo, array $sp, string $name, string $email, string $plan, int $consent, int $digital, string $lang = 'en'): string
{
    $live = time() <= (int)$sp['until'];
    $kind = $sp['offer'];
    if ($kind === 'method' && !in_array($plan, ['m1', 'm3', 'm12'], true)) $plan = 'm1';
    if ($kind === 'unfold' && !in_array($plan, ['u1', 'u3'], true)) $plan = 'u1';
    return mv_checkout($pdo, ['plan' => $plan, 'name' => $name, 'email' => $email, 'lang' => $lang, 'digital' => $digital, 'live' => $consent ?: 1], $live, (string)$sp['scope']);
}

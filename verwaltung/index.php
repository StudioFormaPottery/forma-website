<?php
// Verwaltung für Kurse und Anmeldungen – nur für Vivien.
declare(strict_types=1);
require dirname(__DIR__) . '/api/lib.php';

$secure = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');
session_set_cookie_params(['lifetime' => 0, 'path' => '/verwaltung/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
session_name('forma_admin');
session_start();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$pdo = forma_db();
$self = strtok($_SERVER['REQUEST_URI'] ?? '/verwaltung/', '?') ?: '/verwaltung/';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function go(string $url, ?string $flash = null): void
{
    if ($flash !== null) {
        $_SESSION['flash'] = $flash;
    }
    header('Location: ' . $url);
    exit;
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sitzung abgelaufen. Bitte lade die Seite neu.');
    }
}

function send_setup_link(PDO $pdo): bool
{
    $last = (int)(forma_setting($pdo, 'setup_sent_at') ?? 0);
    if (time() - $last < 300) {
        return false;
    }
    $token = bin2hex(random_bytes(24));
    forma_set_setting($pdo, 'setup_token', hash('sha256', $token));
    forma_set_setting($pdo, 'setup_expires', (string)(time() + 3600));
    forma_set_setting($pdo, 'setup_sent_at', (string)time());
    $link = FORMA_SITE_URL . '/verwaltung/?token=' . $token;
    $body = "Hallo Vivien,\n\nhier ist dein Link, um das Passwort für die Kursverwaltung festzulegen:\n\n$link\n\nDer Link ist eine Stunde gültig und funktioniert nur einmal.\nFalls du das nicht angefordert hast, kannst du diese E-Mail einfach ignorieren.\n";
    return forma_mail(FORMA_NOTIFY_EMAIL, 'Passwort für die FORMA Kursverwaltung', $body);
}

function euro_to_cents(string $v): int
{
    $v = str_replace([' ', '€'], '', trim($v));
    if ($v === '') {
        return 0;
    }
    if (str_contains($v, ',')) {
        $v = str_replace(['.', ','], ['', '.'], $v);
    }
    return max(0, (int)round((float)$v * 100));
}

/** Verkleinert große Fotos auf max. 1400 px, damit das Pop-up schnell lädt. */
function shrink_image(string $src, string $target, array $info): bool
{
    if (!function_exists('imagecreatetruecolor') || ($info[0] <= 1400 && $info[1] <= 1400)) {
        return false;
    }
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        default => false,
    };
    if (!$img) {
        return false;
    }
    $scale = 1400 / max($info[0], $info[1]);
    $out = imagescale($img, (int)round($info[0] * $scale), (int)round($info[1] * $scale), IMG_BICUBIC);
    if ($info[2] === IMAGETYPE_PNG) {
        imagesavealpha($out, true);
        return imagepng($out, $target, 7);
    }
    return $info[2] === IMAGETYPE_WEBP ? imagewebp($out, $target, 84) : imagejpeg($out, $target, 84);
}

/** Speichert eine hochgeladene Datei (PDF oder Bild) im Template-Ordner. Gibt den Dateinamen zurück, '' wenn nichts hochgeladen wurde. */
function store_upload(string $field, string $kind, string $prefix): string
{
    $up = $_FILES[$field] ?? null;
    if (!$up || ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($up['error'] ?? 1) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Die Datei konnte nicht hochgeladen werden. Bitte versuch es mit einer kleineren Datei.');
    }
    $dir = forma_template_dir();
    $base = $prefix . '-' . bin2hex(random_bytes(4));
    if ($kind === 'pdf') {
        $head = (string)file_get_contents($up['tmp_name'], false, null, 0, 5);
        if ($head !== '%PDF-' || $up['size'] > 40 * 1024 * 1024) {
            throw new RuntimeException('Bitte lade eine PDF-Datei bis 40 MB hoch.');
        }
        move_uploaded_file($up['tmp_name'], "$dir/$base.pdf");
        return "$base.pdf";
    }
    $info = @getimagesize($up['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext || $up['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Bitte lade ein JPG-, PNG- oder WebP-Bild bis 10 MB hoch.');
    }
    if (!shrink_image($up['tmp_name'], "$dir/$base.$ext", $info)) {
        move_uploaded_file($up['tmp_name'], "$dir/$base.$ext");
    }
    return "$base.$ext";
}

$hash = forma_setting($pdo, 'admin_hash');
$loggedIn = !empty($_SESSION['admin']);
$action = $_POST['action'] ?? '';
$view = $_GET['view'] ?? 'start';

// --- Ohne Anmeldung erlaubte Aktionen -------------------------------------
if ($action === 'send_link') {
    check_csrf();
    $ok = send_setup_link($pdo);
    go($self, $ok
        ? 'Der Link wurde an ' . FORMA_NOTIFY_EMAIL . ' geschickt. Schau in dein Postfach (auch im Spam-Ordner).'
        : 'Ein Link wurde gerade erst verschickt. Bitte warte ein paar Minuten und schau in dein Postfach.');
}

if (isset($_GET['token']) || $action === 'set_password') {
    $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
    $valid = $token !== ''
        && hash_equals(forma_setting($pdo, 'setup_token') ?? '-', hash('sha256', $token))
        && time() < (int)(forma_setting($pdo, 'setup_expires') ?? 0);
    if (!$valid) {
        go($self, 'Dieser Link ist abgelaufen oder wurde schon benutzt. Fordere einfach einen neuen an.');
    }
    $error = '';
    if ($action === 'set_password') {
        check_csrf();
        $p1 = (string)($_POST['p1'] ?? '');
        $p2 = (string)($_POST['p2'] ?? '');
        if (mb_strlen($p1) < 10) {
            $error = 'Das Passwort braucht mindestens 10 Zeichen.';
        } elseif ($p1 !== $p2) {
            $error = 'Die beiden Passwörter stimmen nicht überein.';
        } else {
            forma_set_setting($pdo, 'admin_hash', password_hash($p1, PASSWORD_DEFAULT));
            forma_set_setting($pdo, 'setup_token', null);
            forma_set_setting($pdo, 'setup_expires', null);
            forma_set_setting($pdo, 'login_fails', '0');
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            go($self, 'Dein Passwort ist gespeichert. Willkommen in deiner Kursverwaltung!');
        }
    }
    render_page('Passwort festlegen', function () use ($token, $error) { ?>
        <h1>Passwort festlegen</h1>
        <p class="muted">Wähle ein Passwort mit mindestens 10 Zeichen. Damit meldest du dich künftig hier an.</p>
        <?php if ($error): ?><p class="alert"><?= forma_h($error) ?></p><?php endif; ?>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
            <input type="hidden" name="action" value="set_password">
            <input type="hidden" name="token" value="<?= forma_h($token) ?>">
            <label for="p1">Neues Passwort</label>
            <input id="p1" name="p1" type="password" autocomplete="new-password" required minlength="10">
            <label for="p2">Passwort wiederholen</label>
            <input id="p2" name="p2" type="password" autocomplete="new-password" required minlength="10">
            <button class="btn" type="submit">Passwort speichern</button>
        </form>
    <?php });
}

if ($action === 'login') {
    check_csrf();
    $lockUntil = (int)(forma_setting($pdo, 'lock_until') ?? 0);
    if (time() < $lockUntil) {
        go($self, 'Zu viele Fehlversuche. Bitte warte 15 Minuten.');
    }
    if ($hash && password_verify((string)($_POST['password'] ?? ''), $hash)) {
        forma_set_setting($pdo, 'login_fails', '0');
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        go($self);
    }
    $fails = (int)(forma_setting($pdo, 'login_fails') ?? 0) + 1;
    forma_set_setting($pdo, 'login_fails', (string)$fails);
    if ($fails >= 5) {
        forma_set_setting($pdo, 'lock_until', (string)(time() + 900));
        forma_set_setting($pdo, 'login_fails', '0');
    }
    go($self, 'Das Passwort stimmt nicht.');
}

if (!$loggedIn) {
    render_page('Anmelden', function () use ($hash, $flash) { ?>
        <h1>Kursverwaltung</h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <?php if (!$hash): ?>
            <div class="card stack">
                <p>Willkommen! Für den ersten Start schicke ich dir einen Link an <strong><?= forma_h(FORMA_NOTIFY_EMAIL) ?></strong>. Damit legst du dein Passwort fest.</p>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                    <input type="hidden" name="action" value="send_link">
                    <button class="btn" type="submit">Link per E-Mail schicken</button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" class="card stack">
                <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                <input type="hidden" name="action" value="login">
                <label for="password">Passwort</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
                <button class="btn" type="submit">Anmelden</button>
            </form>
            <form method="post" class="center">
                <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                <input type="hidden" name="action" value="send_link">
                <button class="link" type="submit">Passwort vergessen? Neuen Link per E-Mail schicken</button>
            </form>
        <?php endif; ?>
    <?php });
}

// --- Ab hier nur angemeldet -------------------------------------------------
if ($action !== '') {
    check_csrf();
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    go($self);
}

if ($action === 'save_course') {
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        forma_clean((string)($_POST['title'] ?? ''), 120),
        forma_clean((string)($_POST['date_text'] ?? ''), 160),
        preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['start_date'] ?? '')) ? $_POST['start_date'] : date('Y-m-d'),
        max(0, min(200, (int)($_POST['capacity'] ?? 1))),
        max(1, min(50, (int)($_POST['min_participants'] ?? 1))),
        forma_clean((string)($_POST['price_text'] ?? ''), 120),
        forma_clean((string)($_POST['description'] ?? ''), 600),
        !empty($_POST['visible']) ? 1 : 0,
        euro_to_cents((string)($_POST['price_online'] ?? '')),
    ];
    if ($data[0] === '' || $data[1] === '') {
        go($self . '?view=edit' . ($id ? '&id=' . $id : ''), 'Bitte gib mindestens einen Namen und den Termin an.');
    }
    if ($id > 0) {
        $pdo->prepare('UPDATE courses SET title=?, date_text=?, start_date=?, capacity=?, min_participants=?, price_text=?, description=?, visible=?, price_cents=? WHERE id=?')
            ->execute([...$data, $id]);
        go($self . '?view=course&id=' . $id, 'Gespeichert.');
    }
    $pdo->prepare('INSERT INTO courses (title, date_text, start_date, capacity, min_participants, price_text, description, visible, price_cents, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([...$data, date('c')]);
    go($self, 'Der Termin ist angelegt' . ($data[7] ? ' und auf der Website sichtbar.' : '.'));
}

if ($action === 'toggle_visible') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE courses SET visible = 1 - visible WHERE id = ?')->execute([$id]);
    go($self, 'Gespeichert.');
}

if ($action === 'delete_course') {
    $id = (int)($_POST['id'] ?? 0);
    if (empty($_POST['confirm'])) {
        go($self . '?view=edit&id=' . $id, 'Bitte setze das Häkchen, um den Termin wirklich zu löschen.');
    }
    $pdo->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
    go($self, 'Der Termin wurde gelöscht.');
}

if ($action === 'booking_status') {
    $bid = (int)($_POST['booking_id'] ?? 0);
    $cid = (int)($_POST['id'] ?? 0);
    $status = ($_POST['status'] ?? '') === 'storniert' ? 'storniert' : 'bestaetigt';
    $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status, $bid]);
    go($self . '?view=course&id=' . $cid, $status === 'storniert' ? 'Die Anmeldung ist storniert, der Platz ist wieder frei.' : 'Die Anmeldung ist wieder aktiv.');
}

if ($action === 'add_booking') {
    $cid = (int)($_POST['id'] ?? 0);
    $name = forma_clean((string)($_POST['name'] ?? ''), 120);
    if ($name === '') {
        go($self . '?view=course&id=' . $cid, 'Bitte gib einen Namen an.');
    }
    $pdo->prepare('INSERT INTO bookings (course_id, name, email, phone, persons, message, status, created_at) VALUES (?,?,?,?,?,?,\'bestaetigt\',?)')
        ->execute([$cid, $name, forma_clean((string)($_POST['email'] ?? ''), 160), forma_clean((string)($_POST['phone'] ?? ''), 40),
            max(1, (int)($_POST['persons'] ?? 1)), 'Von Vivien eingetragen', date('c')]);
    go($self . '?view=course&id=' . $cid, 'Die Person ist eingetragen.');
}

if ($action === 'link_create') {
    $persons = max(1, min(8, (int)($_POST['persons'] ?? 1)));
    $value = euro_to_cents((string)($_POST['value'] ?? ''));
    if ($value <= 0) $value = $persons * (int)FORMA_SERIES['spin']['price_cents'];
    $pdo->prepare('INSERT INTO voucher_links (token, label, persons, value_cents, note, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([bin2hex(random_bytes(16)), forma_clean((string)($_POST['label'] ?? ''), 120), $persons, $value, forma_clean((string)($_POST['note'] ?? ''), 300), date('c')]);
    go($self . '?view=gutscheine&neu=' . (int)$pdo->lastInsertId() . '#links', 'Der Link ist fertig. Kopier ihn und schick ihn per WhatsApp oder E-Mail weiter.');
}

if ($action === 'link_renew' || $action === 'link_delete') {
    $lid = (int)($_POST['lid'] ?? 0);
    if ($action === 'link_renew') {
        $pdo->prepare("UPDATE voucher_links SET token = ?, created_at = ? WHERE id = ? AND status = 'offen'")->execute([bin2hex(random_bytes(16)), date('c'), $lid]);
        go($self . '?view=gutscheine&neu=' . $lid . '#links', 'Neuer Link erzeugt. Der alte Link funktioniert ab jetzt nicht mehr.');
    }
    $pdo->prepare("UPDATE voucher_links SET status = 'geloescht' WHERE id = ? AND status = 'offen'")->execute([$lid]);
    go($self . '?view=gutscheine#links', 'Der Link ist gelöscht und funktioniert nicht mehr.');
}

if ($action === 'old_voucher') {
    $date = (string)($_POST['date'] ?? '');
    $back = $self . '?view=kalender&m=' . substr($date, 0, 7) . '&d=' . rawurlencode($date) . '&alt=1';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) go($self . '?view=kalender', 'Bitte tipp zuerst im Kalender auf einen Tag.');
    $name = forma_clean((string)($_POST['name'] ?? ''), 120);
    $email = mb_strtolower(forma_clean((string)($_POST['email'] ?? ''), 160));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) go($back, 'Bitte gib Name und eine gültige E-Mail-Adresse an, damit die Bestätigung ankommt.');
    $persons = max(1, min(8, (int)($_POST['persons'] ?? 1)));
    $cid = (string)($_POST['course'] ?? '');
    if ($cid === 'neu') {
        $from = (string)($_POST['from'] ?? '09:00'); $to = (string)($_POST['to'] ?? '11:00');
        if (!preg_match('/^\d{2}:\d{2}$/', $from) || !preg_match('/^\d{2}:\d{2}$/', $to) || $to <= $from) go($back, 'Bitte gib eine gültige Uhrzeit an (z. B. 9:00 bis 11:00).');
        $tpl = FORMA_SERIES['spin'];
        $hm = fn($t) => (int)substr($t, 0, 2) . (substr($t, 3, 2) !== '00' ? ':' . substr($t, 3, 2) : '');
        $pdo->prepare('INSERT INTO courses (title, date_text, start_date, capacity, min_participants, price_text, price_cents, description, visible, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$tpl['title'], forma_date_text($date, $hm($from), $hm($to)), $date, max((int)$tpl['capacity'], $persons), 1, $tpl['price_text'], (int)$tpl['price_cents'], $tpl['description'], isset($_POST['public']) ? 1 : 0, date('c')]);
        $cid = (int)$pdo->lastInsertId();
    }
    try {
        $r = forma_old_voucher_booking($pdo, [
            'course_id' => (int)$cid, 'name' => $name, 'email' => $email, 'phone' => forma_clean((string)($_POST['phone'] ?? ''), 40),
            'persons' => $persons, 'value_cents' => euro_to_cents((string)($_POST['value'] ?? '')), 'nr' => forma_clean((string)($_POST['nr'] ?? ''), 40),
            'no_post' => !isset($_POST['post']), 'note' => forma_clean((string)($_POST['note'] ?? ''), 300),
        ]);
    } catch (Throwable $e) {
        go($back, $e->getMessage());
    }
    $first = forma_first_name($name);
    $msg = "Fertig! $first ist eingetragen, der Gutschein hat die Nummer {$r['nr']} und ist eingelöst. "
        . ($r['rest_pay'] > 0
            ? "$first hat die Bestätigung bekommen. Der Gutschein deckt nicht alles ab, " . forma_euro($r['rest_pay']) . ' zahlt sie vor Ort (dafür geht keine Rechnung raus).'
            : "$first hat die Bestätigung mit Kalendereintrag und die Rechnung per E-Mail bekommen.")
        . ($r['rest_value'] > 0 ? ' Auf dem Gutschein bleiben ' . forma_euro($r['rest_value']) . ' Restwert (siehe Gutscheine).' : '')
        . ' Die Bitte um eine Google-Bewertung geht automatisch nach dem Kurs raus.';
    go($self . '?view=kalender&m=' . substr($date, 0, 7) . '&d=' . rawurlencode($date), $msg);
}

if ($action === 'save_mail') {
    $pw = (string)($_POST['mail_password'] ?? '');
    if ($pw === '') {
        go($self . '?view=einstellungen', 'Bitte gib das Passwort deines Postfachs ein.');
    }
    try {
        forma_smtp_send($pw, FORMA_NOTIFY_EMAIL, 'Test: E-Mail-Versand ist eingerichtet',
            "Hallo Vivien,\n\ndiese Test-Mail kommt von deiner Website. Ab jetzt gehen alle Bestätigungen über dein Postfach " . FORMA_FROM_EMAIL . " raus.\n", null);
        forma_save_mail_password($pw);
        go($self . '?view=einstellungen', 'Geschafft! Der E-Mail-Versand läuft jetzt über dein Postfach. Eine Test-Mail ist unterwegs an ' . FORMA_NOTIFY_EMAIL . '.');
    } catch (Throwable $e) {
        error_log('FORMA SMTP Test: ' . $e->getMessage());
        go($self . '?view=einstellungen', 'Die Anmeldung beim Postfach hat nicht geklappt. Bitte prüfe das Passwort von ' . FORMA_FROM_EMAIL . ' und versuch es noch einmal.');
    }
}

if ($action === 'remove_mail') {
    forma_save_mail_password(null);
    go($self . '?view=einstellungen', 'Der Versand über dein Postfach ist ausgeschaltet.');
}

if ($action === 'create_series') {
    $weekdays = array_values(array_filter(array_map('intval', (array)($_POST['weekdays'] ?? [])), fn($d) => $d >= 1 && $d <= 7));
    $from = (string)($_POST['from_date'] ?? '');
    $to = (string)($_POST['to_date'] ?? '');
    $tpl = [
        'title' => forma_clean((string)($_POST['title'] ?? ''), 120),
        'from' => forma_clean((string)($_POST['time_from'] ?? ''), 8),
        'to' => forma_clean((string)($_POST['time_to'] ?? ''), 8),
        'capacity' => max(1, min(200, (int)($_POST['capacity'] ?? 4))),
        'min' => max(1, min(50, (int)($_POST['min_participants'] ?? 1))),
        'price_text' => forma_clean((string)($_POST['price_text'] ?? ''), 120),
        'price_cents' => euro_to_cents((string)($_POST['price_online'] ?? '')),
        'description' => forma_clean((string)($_POST['description'] ?? ''), 600),
    ];
    $validDates = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $from <= $to;
    if ($tpl['title'] === '' || !$weekdays || !$validDates || $tpl['from'] === '' || $tpl['to'] === '') {
        go($self . '?view=series', 'Bitte gib Name, Wochentage, Uhrzeit und Zeitraum an.');
    }
    if ((strtotime($to) - strtotime($from)) > 370 * 86400) {
        go($self . '?view=series', 'Bitte leg höchstens ein Jahr auf einmal an.');
    }
    $n = forma_create_series($pdo, $tpl, $weekdays, $from, $to);
    go($self, $n === 0 ? 'Es gab keine neuen Termine anzulegen (sie existieren schon).' : ($n === 1 ? '1 Termin ist angelegt.' : "$n Termine sind angelegt und auf der Website sichtbar."));
}

if ($action === 'save_stripe') {
    $key = trim((string)($_POST['stripe_key'] ?? ''));
    if (!preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $key)) {
        go($self . '?view=einstellungen', 'Das sieht nicht wie ein geheimer Stripe-Schlüssel aus. Er beginnt mit „sk_test_“, „sk_live_“ oder „rk_live_“.');
    }
    try {
        forma_stripe('GET', '/v1/checkout/sessions', ['limit' => 1], $key);
        forma_save_stripe_key($key);
        go($self . '?view=einstellungen', forma_stripe_test_mode($key)
            ? 'Stripe ist im Testmodus verbunden. Du kannst jetzt mit der Testkarte 4242 4242 4242 4242 eine Buchung ausprobieren.'
            : 'Stripe ist verbunden. Ab jetzt bezahlen deine Kundinnen Kurse direkt online.');
    } catch (Throwable $e) {
        error_log('FORMA Stripe Test: ' . $e->getMessage());
        go($self . '?view=einstellungen', 'Stripe hat den Schlüssel nicht angenommen. Bitte kopiere ihn noch einmal vollständig aus deinem Stripe-Dashboard.');
    }
}

if ($action === 'remove_stripe') {
    forma_save_stripe_key(null);
    go($self . '?view=einstellungen', 'Stripe ist getrennt. Anmeldungen laufen wieder ohne Online-Bezahlung.');
}

if ($action === 'save_mailerlite') {
    $key = trim((string)($_POST['ml_key'] ?? ''));
    if (strlen($key) < 20) {
        go($self . '?view=einstellungen', 'Bitte füge den vollständigen API-Schlüssel aus MailerLite ein.');
    }
    try {
        $group = forma_ml_group($key);
        forma_save_mailerlite(['api_key' => $key, 'group_id' => $group]);
        $n = 0;
        foreach ($pdo->query("SELECT email, name FROM subscribers WHERE status = 'bestaetigt'")->fetchAll() as $r) {
            $n += forma_ml_sync($r['email'], $r['name']) ? 1 : 0;
        }
        go($self . '?view=einstellungen', 'MailerLite ist verbunden. Neue bestätigte Adressen landen ab jetzt automatisch in der Gruppe „Website“.' . ($n ? " $n vorhandene Kontakte wurden übertragen." : ''));
    } catch (Throwable $e) {
        error_log('FORMA MailerLite Test: ' . $e->getMessage());
        go($self . '?view=einstellungen', 'MailerLite hat den Schlüssel nicht angenommen. Bitte erstelle in MailerLite unter Integrationen → API einen neuen Schlüssel und füge ihn vollständig ein.');
    }
}

if ($action === 'remove_mailerlite') {
    forma_save_mailerlite(null);
    go($self . '?view=einstellungen', 'MailerLite ist getrennt. Neue Adressen sammeln sich nur noch hier in deiner Liste.');
}

if ($action === 'save_review') {
    $u = trim((string)($_POST['review_url'] ?? ''));
    if ($u !== '' && !preg_match('~^https://~', $u)) go($self . '?view=einstellungen', 'Der Link muss mit https:// beginnen.');
    forma_set_setting($pdo, 'review_url', $u === '' ? null : $u);
    forma_set_setting($pdo, 'review_off', isset($_POST['review_on']) ? null : '1');
    go($self . '?view=einstellungen', isset($_POST['review_on']) ? 'Gespeichert. Gäste bekommen eine Stunde nach dem Kurs die Bitte um eine Google-Bewertung.' : 'Gespeichert. Die Bewertungs-Mails sind ausgeschaltet.');
}

if ($action === 'save_community') {
    $link = trim((string)($_POST['community'] ?? ''));
    if ($link !== '' && !preg_match('~^https://(chat\.whatsapp\.com|whatsapp\.com|www\.whatsapp\.com)/~', $link)) {
        go($self . '?view=einstellungen', 'Das sieht nicht wie ein WhatsApp-Einladungslink aus. Er beginnt mit https://chat.whatsapp.com/');
    }
    forma_set_setting($pdo, 'community', $link === '' ? null : $link);
    go($self . '?view=einstellungen', $link === '' ? 'Der Community-Knopf ist ausgeblendet.' : 'Gespeichert. Der Knopf „WhatsApp-Community beitreten“ ist jetzt auf der Website.');
}

if ($action === 'save_deko') {
    $m = (string)($_POST['deko'] ?? 'auto');
    forma_set_setting($pdo, 'deko', in_array($m, ['auto', 'herbst', 'winter', 'fruehling', 'sommer', 'aus'], true) ? $m : 'auto');
    go($self . '?view=popup', 'Die saisonale Deko ist gespeichert.');
}

if ($action === 'save_popup') {
    $p = forma_popup($pdo);
    $p['active'] = !empty($_POST['active']);
    $p['eyebrow'] = forma_clean((string)($_POST['eyebrow'] ?? ''), 60);
    $p['title'] = forma_clean((string)($_POST['title'] ?? ''), 90);
    $p['text'] = trim(preg_replace("/\r\n?/", "\n", mb_substr((string)($_POST['text'] ?? ''), 0, 700)) ?? '');
    $p['date_line'] = forma_clean((string)($_POST['date_line'] ?? ''), 90);
    $p['start'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['start'] ?? '')) ? $_POST['start'] : '';
    $p['end'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['end'] ?? '')) ? $_POST['end'] : '';
    $p['signup'] = !empty($_POST['signup']);
    $p['theme'] = ($_POST['theme'] ?? '') === 'advent' ? 'advent' : '';
    $p['button_text'] = forma_clean((string)($_POST['button_text'] ?? ''), 40);
    $link = forma_clean((string)($_POST['button_link'] ?? ''), 300);
    $p['button_link'] = ($link === '' || preg_match('~^(#|/|https://)~', $link)) ? $link : '';
    if (!empty($_POST['remove_image']) && $p['image'] !== '') {
        @unlink(forma_data_dir() . '/' . basename($p['image']));
        $p['image'] = '';
    }
    $up = $_FILES['image'] ?? null;
    if ($up && ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $info = @getimagesize($up['tmp_name']);
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext || $up['size'] > 8 * 1024 * 1024) {
            go($self . '?view=popup', 'Bitte lade ein JPG-, PNG- oder WebP-Bild bis 8 MB hoch.');
        }
        foreach (glob(forma_data_dir() . '/popup.*') ?: [] as $old) {
            @unlink($old);
        }
        $target = forma_data_dir() . '/popup.' . $ext;
        if (!shrink_image($up['tmp_name'], $target, $info)) {
            move_uploaded_file($up['tmp_name'], $target);
        }
        $p['image'] = 'popup.' . $ext;
    } elseif ($up && ($up['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) {
        go($self . '?view=popup', 'Das Bild konnte nicht hochgeladen werden. Bitte versuch es mit einem kleineren Bild.');
    }
    if ($p['title'] === '') {
        go($self . '?view=popup', 'Bitte gib dem Pop-up einen Titel.');
    }
    $p['version'] = (int)$p['version'] + 1;
    forma_set_setting($pdo, 'popup', json_encode($p, JSON_UNESCAPED_UNICODE));
    go($self . '?view=popup', $p['active'] ? (forma_popup_live($p) ? 'Gespeichert. Das Pop-up ist jetzt auf der Website zu sehen.' : 'Gespeichert. Das Pop-up erscheint im eingestellten Zeitraum.') : 'Gespeichert. Das Pop-up ist ausgeschaltet.');
}

if ($action === 'min_decide') {
    $cid = (int)($_POST['id'] ?? 0);
    forma_min_schema($pdo);
    if (($_POST['to'] ?? '') === 'statt') {
        $pdo->prepare("UPDATE courses SET min_status = 'findet_statt', min_at = ? WHERE id = ?")->execute([date('c'), $cid]);
        go($self . '?view=course&id=' . $cid, 'Gespeichert: Der Kurs findet statt. Die Gäste bekommen keine Nachricht.');
    }
    $n = forma_min_inform($pdo, $cid);
    go($self . '?view=course&id=' . $cid, $n === 1 ? 'Der Gast ist informiert und kann jetzt selbst umbuchen oder stornieren.' : "$n Gäste sind informiert und können jetzt selbst umbuchen oder stornieren.");
}

if ($action === 'voucher_partial') {
    $vid = (int)($_POST['vid'] ?? 0);
    $cents = (int)round((float)str_replace(',', '.', (string)($_POST['eur'] ?? '0')) * 100);
    $st = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? AND status = 'bezahlt'");
    $st->execute([$vid]);
    $v = $st->fetch();
    if (!$v || $cents <= 0) go($self . '?view=gutscheine', 'Bitte gib einen Betrag ein.');
    $bal = forma_voucher_balance($pdo, $v);
    if ($cents > $bal) go($self . '?view=gutscheine', 'Auf diesem Gutschein sind nur noch ' . forma_euro($bal) . ' offen.');
    $pdo->exec('BEGIN IMMEDIATE');
    $pdo->prepare("INSERT INTO voucher_uses (voucher_id, ref_kind, ref_id, cents, status, note, created_at) VALUES (?, 'atelier', 0, ?, 'eingeloest', ?, ?)")
        ->execute([$vid, $cents, forma_clean((string)($_POST['note'] ?? ''), 120), date('c')]);
    forma_voucher_after_use($pdo, $vid);
    $pdo->exec('COMMIT');
    go($self . '?view=gutscheine', forma_euro($cents) . ' abgezogen. Restwert: ' . forma_euro($bal - $cents) . '.');
}

if ($action === 'voucher_status') {
    $vid = (int)($_POST['vid'] ?? 0);
    $redeem = ($_POST['to'] ?? '') === 'eingeloest';
    $pdo->prepare("UPDATE vouchers SET status = ?, redeemed_at = ? WHERE id = ? AND status IN ('bezahlt','eingeloest')")
        ->execute([$redeem ? 'eingeloest' : 'bezahlt', $redeem ? date('c') : null, $vid]);
    go($self . '?view=gutscheine', $redeem ? 'Der Gutschein ist als eingelöst markiert.' : 'Der Gutschein ist wieder offen.');
}

if ($action === 'save_maker') {
    $m = forma_maker($pdo);
    try {
        $pdf = store_upload('maker_pdf', 'pdf', 'maker');
        $pdfEn = store_upload('maker_pdf_en', 'pdf', 'maker');
        $img = store_upload('maker_image', 'image', 'maker');
    } catch (Throwable $e) {
        go($self . '?view=templates', $e->getMessage());
    }
    if ($pdf !== '') { if ($m['file']) @unlink(forma_template_dir() . '/' . basename($m['file'])); $m['file'] = $pdf; }
    if ($pdfEn !== '') { if ($m['file_en']) @unlink(forma_template_dir() . '/' . basename($m['file_en'])); $m['file_en'] = $pdfEn; }
    if ($img !== '') { if ($m['image']) @unlink(forma_template_dir() . '/' . basename($m['image'])); $m['image'] = $img; }
    $m['title'] = forma_clean((string)($_POST['maker_title'] ?? ''), 90);
    $m['text'] = forma_clean((string)($_POST['maker_text'] ?? ''), 400);
    $m['version'] = (int)$m['version'] + 1;
    $m['month'] = date('Y-m');
    forma_set_setting($pdo, 'maker', json_encode($m, JSON_UNESCAPED_UNICODE));
    go($self . '?view=templates', 'Das Template des Monats ist gespeichert' . ($m['file'] ? ' und auf der Website verfügbar.' : '. Lade noch die PDF hoch, damit man es herunterladen kann.'));
}

if ($action === 'shop_toggle') {
    $open = forma_setting($pdo, 'shop_open') === '1';
    forma_set_setting($pdo, 'shop_open', $open ? '0' : '1');
    go($self . '?view=templates', $open ? 'Der Shop ist geschlossen. Das Kauffenster ist ausgeblendet.' : 'Der Shop ist geöffnet. Templates können jetzt online gekauft werden.');
}

if ($action === 'save_template') {
    $id = (int)($_POST['tid'] ?? 0);
    $title = forma_clean((string)($_POST['title'] ?? ''), 90);
    if ($title === '') {
        go($self . '?view=template_edit' . ($id ? '&id=' . $id : ''), 'Bitte gib dem Template einen Namen.');
    }
    try {
        $pdf = store_upload('pdf', 'pdf', 'tpl');
        $pdfEn = store_upload('pdf_en', 'pdf', 'tpl');
        $img = store_upload('image', 'image', 'tpl');
    } catch (Throwable $e) {
        go($self . '?view=template_edit' . ($id ? '&id=' . $id : ''), $e->getMessage());
    }
    $season = array_key_exists($_POST['season'] ?? '', FORMA_SEASONS) ? $_POST['season'] : 'ganzjaehrig';
    $age = array_key_exists($_POST['age'] ?? '', FORMA_AGES) ? $_POST['age'] : 'erwachsene';
    $vals = [$title, forma_clean((string)($_POST['description'] ?? ''), 600), $season, $age, max(1, min(3, (int)($_POST['level'] ?? 1))),
        euro_to_cents((string)($_POST['price'] ?? '')), !empty($_POST['visible']) ? 1 : 0, (int)($_POST['sort'] ?? 0)];
    if ($id > 0) {
        $old = $pdo->prepare('SELECT * FROM templates WHERE id = ?'); $old->execute([$id]); $old = $old->fetch();
        $pdo->prepare('UPDATE templates SET title=?, description=?, season=?, age=?, level=?, price_cents=?, visible=?, sort=?' . ($pdf ? ', file=?' : '') . ($pdfEn ? ', file_en=?' : '') . ($img ? ', image=?' : '') . ' WHERE id=?')
            ->execute(array_merge($vals, $pdf ? [$pdf] : [], $pdfEn ? [$pdfEn] : [], $img ? [$img] : [], [$id]));
    } else {
        $pdo->prepare('INSERT INTO templates (title, description, season, age, level, price_cents, visible, sort, file, file_en, image, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array_merge($vals, [$pdf ?: '', $pdfEn ?: '', $img ?: '', date('c')]));
    }
    go($self . '?view=templates', 'Das Template ist gespeichert.');
}

if ($action === 'delete_template') {
    $pdo->prepare('UPDATE templates SET visible = 0, sort = 9999 WHERE id = ?')->execute([(int)($_POST['tid'] ?? 0)]);
    go($self . '?view=templates', 'Das Template ist ausgeblendet. Bereits gekaufte Downloads funktionieren weiter.');
}

if ($action === 'private_done') {
    $pid = (int)($_POST['pid'] ?? 0);
    $pdo->prepare('UPDATE private_bookings SET done = 1 - done WHERE id = ?')->execute([$pid]);
    $rv = false;
    try { $rv = forma_review_private($pdo, $pid); } catch (Throwable $e) { error_log('FORMA review private: ' . $e->getMessage()); }
    go($self . '?view=private', $rv ? 'Gespeichert. Die Bitte um eine Google-Bewertung ist an den Gast verschickt.' : 'Gespeichert.');
}

if ($action === 'private_assign') {
    $pid = (int)($_POST['pid'] ?? 0);
    $date = (string)($_POST['date'] ?? '');
    $start = (string)($_POST['start'] ?? '');
    $hours = round((float)str_replace(',', '.', (string)($_POST['hours'] ?? '0')) * 2) / 2;
    $note = trim(mb_substr((string)($_POST['note'] ?? ''), 0, 800));
    $st = $pdo->prepare("SELECT * FROM private_bookings WHERE id = ? AND status IN ('bezahlt','anfrage')");
    $st->execute([$pid]);
    $b = $st->fetch();
    $back = $self . '?view=kalender&m=' . substr($date, 0, 7) . '&d=' . rawurlencode($date) . '&assign=' . $pid;
    if (!$b) go($self . '?view=kalender', 'Diese Buchung gibt es nicht mehr.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $start) || $hours < 0.5 || $hours > 8) {
        go($back, 'Bitte wähl Tag, Uhrzeit und Dauer aus.');
    }
    $pdo->prepare('INSERT INTO private_sessions (private_id, date, start, hours, note, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$pid, $date, $start, $hours, $note, date('c')]);
    $first = forma_first_name((string)$b['name']);
    $rest = $b['status'] === 'bezahlt' ? max(0, (float)$b['hours'] - forma_private_planned($pdo, $pid)) : 0;
    $msg = 'Vorgemerkt: ' . $first . ' am ' . forma_de_date($date, false) . ' um ' . forma_de_time($start) . '.'
        . ($rest > 0 ? ' Noch ' . forma_hours_text($rest) . ' offen, tipp einfach auf den nächsten Tag.' : ' Jetzt nur noch oben auf „Bestätigung schicken“ tippen.');
    go($self . '?view=kalender&m=' . substr($date, 0, 7) . '&d=' . rawurlencode($date) . '&assign=' . $pid, $msg);
}

if ($action === 'private_confirm') {
    $pid = (int)($_POST['pid'] ?? 0);
    $ok = false;
    try { $ok = forma_private_confirm_mail($pdo, $pid, (string)($_POST['note'] ?? '')); } catch (Throwable $e) { error_log('FORMA private confirm: ' . $e->getMessage()); }
    $st = $pdo->prepare('SELECT name FROM private_bookings WHERE id = ?');
    $st->execute([$pid]);
    $first = forma_first_name((string)$st->fetchColumn());
    go($self . '?view=kalender&m=' . rawurlencode((string)($_POST['m'] ?? '')), $ok ? 'Fertig! ' . $first . ' hat die Bestätigung mit allen Terminen per E-Mail bekommen.' : 'Die Bestätigung konnte leider nicht verschickt werden.');
}

if ($action === 'private_session_delete') {
    $sid = (int)($_POST['sid'] ?? 0);
    $st = $pdo->prepare('SELECT s.*, p.name, p.email FROM private_sessions s JOIN private_bookings p ON p.id = s.private_id WHERE s.id = ?');
    $st->execute([$sid]);
    $s = $st->fetch();
    if (!$s) go($self . '?view=kalender', 'Diesen Termin gibt es nicht mehr.');
    $back = $self . '?view=kalender&m=' . substr((string)$s['date'], 0, 7) . '&d=' . rawurlencode((string)$s['date']);
    $first = forma_first_name((string)$s['name']);
    $pdo->prepare('DELETE FROM private_sessions WHERE id = ?')->execute([$sid]);
    if (!empty($_POST['notify']) && $s['mailed_at'] && filter_var($s['email'], FILTER_VALIDATE_EMAIL)) {
        forma_mail((string)$s['email'], 'Dein Termin am ' . forma_de_date((string)$s['date'], false) . ' wird verschoben',
            "Hallo $first,\n\ndein Private-Pottery-Termin am " . forma_de_date((string)$s['date']) . ' um ' . forma_de_time((string)$s['start']) . " findet so leider nicht statt. Keine Sorge, deine Stunden bleiben dir natürlich erhalten! Ich melde mich gleich bei dir und wir finden einen neuen Termin. Sobald er fix ist, bekommst du wieder eine Bestätigung.\n\nBis bald,\nVivien\nFORMA Earth & Art\n" . FORMA_PHONE . "\nwww.forma-pottery.com\n");
    }
    go($back . '&assign=' . (int)$s['private_id'], 'Der Termin ist gelöscht' . (!empty($_POST['notify']) && $s['mailed_at'] ? ' und ' . $first . ' ist informiert' : '') . '. Tipp auf einen neuen Tag, um ihn neu einzuplanen.');
}

if ($action === 'resend_confirm') {
    $st = $pdo->prepare("SELECT * FROM subscribers WHERE id = ? AND status = 'offen'");
    $st->execute([(int)($_POST['sid'] ?? 0)]);
    $sub = $st->fetch();
    if (!$sub) go($self . '?view=liste', 'Diese Adresse ist schon bestätigt oder nicht mehr da.');
    $maker = str_contains((string)$sub['source'], 'Maker');
    $hello = $sub['name'] !== '' ? 'Hallo ' . forma_first_name($sub['name']) . ',' : 'Hallo,';
    forma_mail((string)$sub['email'], 'Bitte bestätige deine E-Mail-Adresse', "$hello\n\nschön, dass du dich " . ($maker ? 'für den FORMA Maker Club' : 'für meinen Newsletter') . " eingetragen hast! Magst du noch kurz deine E-Mail-Adresse bestätigen? Ein Klick auf den Button genügt.\n\n"
        . forma_site_url() . '/api/confirm.php?t=' . $sub['token'] . "\n\nFalls du es dir anders überlegt hast, ignoriere diese E-Mail einfach.\n\nBis bald,\nVivien\n");
    $pdo->prepare('UPDATE subscribers SET reminded_at = ? WHERE id = ?')->execute([date('c'), (int)$sub['id']]);
    go($self . '?view=liste', 'Die Bestätigungs-Mail ist nochmal an ' . $sub['email'] . ' rausgegangen.');
}

if ($action === 'delete_subscriber') {
    $pdo->prepare('DELETE FROM subscribers WHERE id = ?')->execute([(int)($_POST['sid'] ?? 0)]);
    go($self . '?view=liste', 'Der Kontakt ist gelöscht.');
}

if ($action === 'invoice_settings') {
    $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['prefix'] ?? ''));
    forma_set_setting($pdo, 'invoice_prefix', $prefix);
    $nextNr = (int)($_POST['next_nr'] ?? 0);
    if ($nextNr > 0) {
        [$cur] = forma_invoice_next($pdo, (int)date('Y'));
        if ($nextNr < $cur) {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE year = ? AND seq >= ?');
            $chk->execute([(int)date('Y'), $nextNr]);
            if ((int)$chk->fetchColumn() > 0) go($self . '?view=rechnungen', 'Diese Nummer ist schon vergeben. Die nächste freie Nummer ist ' . $cur . '.');
        }
        forma_set_setting($pdo, 'invoice_start_' . date('Y'), (string)$nextNr);
    }
    forma_set_setting($pdo, 'oss_active', !empty($_POST['oss']) ? '1' : '0');
    $sheet = trim((string)($_POST['sheet_id'] ?? ''));
    if (preg_match('~/d/([A-Za-z0-9_-]{20,})~', $sheet, $m)) $sheet = $m[1];
    forma_set_setting($pdo, 'sheet_id', preg_replace('/[^A-Za-z0-9_-]/', '', $sheet));
    if (!empty($_FILES['gkey']['tmp_name']) && is_uploaded_file($_FILES['gkey']['tmp_name'])) {
        $k = json_decode((string)file_get_contents($_FILES['gkey']['tmp_name']), true);
        if (!is_array($k) || empty($k['private_key']) || empty($k['client_email'])) {
            go($self . '?view=rechnungen', 'Das ist keine gültige Schlüsseldatei eines Google-Dienstkontos (JSON).');
        }
        file_put_contents(forma_data_dir() . '/google-key.json', json_encode($k));
        @chmod(forma_data_dir() . '/google-key.json', 0600);
    }
    forma_sheet_sync($pdo);
    go($self . '?view=rechnungen', 'Gespeichert.');
}

if ($action === 'invoice_sample') {
    forma_invoice_sample_send();
    go($self . '?view=rechnungen', 'Die Testmails (Bestätigung und Muster-Rechnung) sind unterwegs an ' . FORMA_NOTIFY_EMAIL . '.');
}

if ($action === 'invoice_resend') {
    $id = (int)($_POST['iid'] ?? 0);
    $pdo->prepare('UPDATE invoices SET mailed = 0 WHERE id = ?')->execute([$id]);
    forma_invoice_send($pdo, $id);
    go($self . '?view=rechnungen', 'Die Rechnung wurde noch einmal verschickt.');
}

// --- Ansichten -------------------------------------------------------------
if ($view === 'rechnung_pdf') {
    forma_invoice_schema($pdo);
    $inv = forma_invoice_get($pdo, (int)($_GET['id'] ?? 0));
    if (!$inv) { http_response_code(404); exit('Nicht gefunden'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Rechnung_' . $inv['nr'] . '.pdf"');
    echo forma_invoice_pdf($inv);
    exit;
}

if ($view === 'rechnungen_export') {
    forma_invoice_schema($pdo);
    $rows = $pdo->query('SELECT * FROM invoices ORDER BY year, seq')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="forma-online-rechnungen-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Firma', 'Rechnungsnr.', 'Rechnungsdatum = Zahlungseingang', 'Leistungsdatum', 'Kunde', 'E-Mail', 'Land', 'Leistung', 'Brutto', 'Netto', 'USt.-Satz', 'USt.', 'Zahlungsart'], ';');
    $de = fn(int $c) => number_format($c / 100, 2, ',', '');
    foreach ($rows as $r) {
        $items = implode(' + ', array_map(fn($i) => $i['qty'] . '× ' . $i['text'], json_decode($r['items'], true)));
        fputcsv($out, ['Forma', $r['nr'], date('d.m.Y', strtotime($r['issued_at'])), $r['service_date'], $r['name'], $r['email'], $r['country'], $items,
            $de((int)$r['gross_cents']), $de((int)$r['net_cents']), rtrim(rtrim(number_format((float)$r['vat_rate'], 1, ',', ''), '0'), ',') . ' %', $de((int)$r['vat_cents']), 'online (Stripe)'], ';');
    }
    exit;
}

if ($view === 'tests' || $action === 'test_run') {
    require_once dirname(__DIR__) . '/api/testbuchung.php';
    $result = null;
    if ($action === 'test_run') {
        $offer = (string)($_POST['offer'] ?? '');
        if (isset(FORMA_TEST_OFFERS[$offer])) $result = forma_test_run($pdo, $offer, isset($_POST['send']));
    }
    render_page('Testbuchungen', function () use ($self, $result) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Testbuchungen</h1>
        <p class="muted">Ein Klick spielt eine Buchung komplett durch: freier Termin, Stripe-Bezahlseite, alle E-Mails, Rechnung, Terminbestätigung und Bewertungs-Mail. Danach wird alles wieder gelöscht. Die Testbuchung taucht nirgends auf, es wird nichts abgebucht und keine Rechnungsnummer verbraucht.</p>
        <?php if ($result): ?>
        <div class="card tres">
            <h2 style="margin-top:0">Ergebnis: <?= forma_h($result['title']) ?></h2>
            <ul class="tsteps">
            <?php foreach ($result['steps'] as [$st, $txt]): ?><li class="ts-<?= forma_h($st) ?>"><span><?= $st === 'ok' ? '✓' : ($st === 'fail' ? '✗' : 'i') ?></span><?= forma_h($txt) ?></li><?php endforeach; ?>
            </ul>
            <?php foreach ($result['mails'] as $k => $m): ?>
            <details class="tmail"<?= $k === 0 ? ' open' : '' ?>><summary><span class="pill"><?= forma_h($m['an']) ?></span> <?= forma_h($m['subject']) ?><?= $m['files'] ? ' <span class="muted small">· Anhang: ' . forma_h(implode(', ', $m['files'])) . '</span>' : '' ?></summary>
                <iframe sandbox="" loading="lazy" srcdoc="<?= forma_h($m['html']) ?>"></iframe></details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="tgrid">
        <?php foreach (FORMA_TEST_OFFERS as $key => [$title, $sub, $ic]): ?>
            <form method="post" class="card ttile"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="test_run"><input type="hidden" name="offer" value="<?= forma_h($key) ?>">
                <span class="ic"><?= admin_icon(['kurs' => 'kalender', 'private' => 'private', 'team' => 'team', 'gutschein' => 'gutschein', 'template' => 'template'][$ic] ?? 'test') ?></span>
                <strong><?= forma_h($title) ?></strong><span class="muted small"><?= forma_h($sub) ?></span>
                <label class="check small"><input type="checkbox" name="send" value="1" checked> Mails an mich schicken</label>
                <button class="btn" type="submit">Testbuchung starten</button>
            </form>
        <?php endforeach; ?>
        </div>
        <p class="muted small">Ohne Häkchen siehst du die Mails nur hier als Vorschau. Mit Häkchen kommen sie mit „[TEST]“ im Betreff an <?= forma_h(FORMA_NOTIFY_EMAIL) ?>, so wie sie auch der Gast bekommen würde.</p>
    <?php });
    exit;
}

if ($view === 'rechnungen') {
    forma_invoice_schema($pdo);
    forma_sheet_sync($pdo);
    $rows = $pdo->query('SELECT * FROM invoices ORDER BY id DESC LIMIT 300')->fetchAll();
    $sum = array_sum(array_column($rows, 'gross_cents'));
    $gkey = forma_google_key();
    $cfg = ['prefix' => forma_setting($pdo, 'invoice_prefix') ?? '', 'oss' => forma_setting($pdo, 'oss_active') === '1', 'sheet' => forma_setting($pdo, 'sheet_id') ?? '', 'err' => forma_setting($pdo, 'sheet_error') ?? ''];
    render_page('Rechnungen', function () use ($self, $flash, $rows, $sum, $gkey, $cfg) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Rechnungen</h1>
        <p class="muted">Nach jeder Online-Zahlung (Kurs, Private Pottery, Template) wird automatisch eine Rechnung erstellt und als PDF an die Kundin und an dich geschickt. Eigener Nummernkreis für Online-Verkäufe, lückenlos pro Jahr.</p>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <p><a class="btn" href="<?= forma_h($self) ?>?view=rechnungen_export">Liste herunterladen (CSV für die Buchhaltung)</a></p>
        <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="invoice_sample"><button class="link" type="submit">Testmail mit Muster-Rechnung an mich schicken</button></form>
        <p class="muted small"><?= count($rows) ?> Rechnungen · <?= forma_h(forma_euro((int)$sum)) ?> brutto</p>
        <div class="list">
        <?php foreach ($rows as $r): ?>
            <div class="card booking">
                <div class="booking-head"><strong><?= forma_h($r['nr']) ?> · <?= forma_h($r['name']) ?></strong><span class="pill pill-ok"><?= forma_h(forma_euro((int)$r['gross_cents'])) ?> bezahlt</span></div>
                <div class="muted small"><?= forma_h(date('d.m.Y', strtotime($r['issued_at']))) ?> · <?= forma_h($r['country']) ?> · USt. <?= forma_h((string)(float)$r['vat_rate']) ?> % · <?= $r['synced'] ? 'in Google Tabelle eingetragen' : 'noch nicht in Google Tabelle' ?></div>
                <div><a href="<?= forma_h($self) ?>?view=rechnung_pdf&amp;id=<?= (int)$r['id'] ?>" target="_blank">PDF ansehen</a></div>
                <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="invoice_resend"><input type="hidden" name="iid" value="<?= (int)$r['id'] ?>"><button class="link" type="submit">Noch einmal an die Kundin schicken</button></form>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><p class="muted">Noch keine Online-Rechnungen.</p><?php endif; ?>
        </div>
        <h2>Einstellungen</h2>
        <form class="card" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="invoice_settings">
            <label>Nächste Rechnungsnummer<input name="next_nr" type="number" min="1" value="<?= (int)forma_invoice_next($GLOBALS['pdo'], (int)date('Y'))[0] ?>"></label>
            <p class="muted small">Die Website zählt fortlaufend weiter wie deine Buchhaltung. Schreibst du selbst eine Rechnung, trag hier danach die nächste freie Nummer ein, dann gibt es keine doppelte Nummer.</p>
            <label>Kürzel vor der Nummer (leer lassen für 19, 20, 21 …)<input name="prefix" value="<?= forma_h($cfg['prefix']) ?>" maxlength="8"></label>
            <label class="check"><input type="checkbox" name="oss" value="1" <?= $cfg['oss'] ? 'checked' : '' ?>> Ich bin für OSS registriert: Templates mit dem Steuersatz des Wohnsitzlandes verrechnen (erst nötig ab 10.000 € Online-Verkäufen in andere EU-Länder pro Jahr)</label>
            <label>Google Tabelle für die Buchhaltung (Link oder ID)<input name="sheet_id" value="<?= forma_h($cfg['sheet']) ?>"></label>
            <label>Google-Dienstkonto-Schlüssel (JSON-Datei) <?= $gkey ? '· hinterlegt für ' . forma_h($gkey['client_email']) : '· noch nicht hinterlegt' ?><input type="file" name="gkey" accept=".json,application/json"></label>
            <?php if ($gkey): ?><p class="muted small">Gib die Google Tabelle für <strong><?= forma_h($gkey['client_email']) ?></strong> als Bearbeiter:in frei. Dann trägt die Website jede neue Rechnung in die Blätter „Rechnungen“ und „Kurse &amp; Leistungen“ ein.</p><?php endif; ?>
            <?php if ($cfg['err']): ?><p class="alert">Letzter Fehler beim Eintragen: <?= forma_h($cfg['err']) ?></p><?php endif; ?>
            <button class="btn" type="submit">Speichern</button>
        </form>
    <?php });
}

if ($view === 'export') {
    $rows = $pdo->query("SELECT email, name, source, confirmed_at FROM subscribers WHERE status = 'bestaetigt' ORDER BY confirmed_at")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="forma-email-liste-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['email', 'name', 'quelle', 'bestaetigt_am'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r['email'], $r['name'], $r['source'], $r['confirmed_at'] ? date('d.m.Y', strtotime($r['confirmed_at'])) : ''], ';');
    }
    exit;
}

if ($view === 'gutscheine_export') {
    $rows = $pdo->query("SELECT * FROM vouchers WHERE status IN ('bezahlt','eingeloest') ORDER BY paid_at")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="forma-gutscheine-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Gutschein-Nr', 'Verkauft am', 'Inhalt', 'Betrag EUR', 'Fuer', 'Von', 'Kaeufer', 'E-Mail', 'Status', 'Eingeloest am'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r['nr'], date('d.m.Y', strtotime($r['paid_at'])), forma_voucher_title($r), number_format($r['amount_cents'] / 100, 2, ',', ''),
            $r['recipient'], $r['sender'], $r['buyer_name'], $r['buyer_email'], $r['status'] === 'eingeloest' ? 'eingelöst' : 'offen',
            $r['redeemed_at'] ? date('d.m.Y', strtotime($r['redeemed_at'])) : ''], ';');
    }
    exit;
}

if ($view === 'templates_export') {
    $rows = $pdo->query("SELECT * FROM template_orders WHERE status = 'bezahlt' ORDER BY paid_at")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="forma-template-verkaeufe-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Bestellung', 'Datum', 'Template', 'Betrag EUR', 'Name', 'E-Mail'], ';');
    foreach ($rows as $r) {
        fputcsv($out, ['T-' . $r['id'], date('d.m.Y', strtotime($r['paid_at'])), $r['title'], number_format($r['amount_cents'] / 100, 2, ',', ''), $r['name'], $r['email']], ';');
    }
    exit;
}

if ($view === 'template_edit') {
    $id = (int)($_GET['id'] ?? 0);
    $t = ['id' => 0, 'title' => '', 'description' => '', 'season' => 'herbst', 'age' => 'erwachsene', 'level' => 1, 'price_cents' => 500, 'file' => '', 'image' => '', 'visible' => 1, 'sort' => 0];
    if ($id) { $st = $pdo->prepare('SELECT * FROM templates WHERE id = ?'); $st->execute([$id]); $t = $st->fetch() ?: $t; }
    render_page($id ? 'Template bearbeiten' : 'Neues Template', function () use ($self, $flash, $t) { ?>
        <p><a href="<?= forma_h($self) ?>?view=templates">← Zu den Templates</a></p>
        <h1><?= $t['id'] ? 'Template bearbeiten' : 'Neues Template' ?></h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="save_template"><input type="hidden" name="tid" value="<?= (int)$t['id'] ?>">
            <label for="title">Name</label><input id="title" name="title" value="<?= forma_h($t['title']) ?>" placeholder="z. B. Weihnachtsbaum-Anhänger" required>
            <label for="description">Beschreibung</label><textarea id="description" name="description" rows="3" placeholder="Was ist drin? z. B. Schablone A4, Bild-Anleitung, Materialliste, Deutsch &amp; Englisch"><?= forma_h($t['description']) ?></textarea>
            <div class="row">
                <div class="stack"><label for="season">Jahreszeit</label><select id="season" name="season"><?php foreach (FORMA_SEASONS as $k => $l): ?><option value="<?= $k ?>" <?= $t['season'] === $k ? 'selected' : '' ?>><?= forma_h($l) ?></option><?php endforeach; ?></select></div>
                <div class="stack"><label for="age">Für wen</label><select id="age" name="age"><?php foreach (FORMA_AGES as $k => $l): ?><option value="<?= $k ?>" <?= $t['age'] === $k ? 'selected' : '' ?>><?= forma_h($l) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="row">
                <div class="stack"><label for="level">Schwierigkeit</label><select id="level" name="level"><?php foreach ([1 => 'leicht', 2 => 'mittel', 3 => 'anspruchsvoll'] as $k => $l): ?><option value="<?= $k ?>" <?= (int)$t['level'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
                <div class="stack"><label for="price">Preis in €</label><input id="price" name="price" inputmode="decimal" value="<?= forma_h(rtrim(rtrim(number_format($t['price_cents'] / 100, 2, ',', ''), '0'), ',')) ?>"></div>
            </div>
            <label for="pdf">PDF auf Deutsch <?= $t['file'] ? '<span class="muted">(vorhanden, nur hochladen zum Ersetzen)</span>' : '' ?></label><input id="pdf" name="pdf" type="file" accept="application/pdf">
            <label for="pdf_en">PDF auf Englisch <span class="muted"><?= ($t['file_en'] ?? '') ? '(vorhanden, nur hochladen zum Ersetzen)' : '(optional)' ?></span></label><input id="pdf_en" name="pdf_en" type="file" accept="application/pdf">
            <label for="image">Vorschaubild <?= $t['image'] ? '<span class="muted">(vorhanden)</span>' : '' ?></label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
            <label for="sort">Reihenfolge <span class="muted">(kleine Zahl = weiter vorne)</span></label><input id="sort" name="sort" type="number" value="<?= (int)$t['sort'] ?>">
            <label class="check"><input type="checkbox" name="visible" value="1" <?= $t['visible'] ? 'checked' : '' ?>> Auf der Website anzeigen</label>
            <button class="btn" type="submit">Speichern</button>
        </form>
        <?php if ($t['id']): ?>
        <form method="post" class="card stack danger"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="delete_template"><input type="hidden" name="tid" value="<?= (int)$t['id'] ?>"><button class="btn btn-danger" type="submit">Template entfernen</button></form>
        <?php endif; ?>
    <?php });
}

if ($view === 'templates') {
    forma_reconcile_payments($pdo, true);
    $m = forma_maker($pdo);
    $makers = (int)$pdo->query("SELECT COUNT(*) FROM subscribers WHERE status = 'bestaetigt' AND source LIKE '%Maker%'")->fetchColumn();
    $tpls = $pdo->query('SELECT t.*, (SELECT COUNT(*) FROM template_orders o WHERE o.template_id = t.id AND o.status = \'bezahlt\') AS sold FROM templates t ORDER BY t.visible DESC, t.sort, t.id DESC')->fetchAll();
    $orders = $pdo->query("SELECT * FROM template_orders WHERE status = 'bezahlt' ORDER BY id DESC LIMIT 50")->fetchAll();
    render_page('Templates & Maker Club', function () use ($self, $flash, $m, $makers, $tpls, $orders, $pdo) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Templates &amp; Maker Club</h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <form method="post" class="card" style="display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="shop_toggle">
            <span><strong>Template-Shop: <?= forma_setting($pdo, 'shop_open') === '1' ? 'geöffnet' : 'noch geschlossen' ?></strong><br><span class="muted small"><?= forma_setting($pdo, 'shop_open') === '1' ? 'Gäste können Templates online kaufen.' : 'Gäste sehen die Templates, aber noch kein Kauffenster („Bald im Shop“).' ?></span></span>
            <button class="btn<?= forma_setting($pdo, 'shop_open') === '1' ? ' btn-ghost' : '' ?>" type="submit"><?= forma_setting($pdo, 'shop_open') === '1' ? 'Shop wieder schließen' : 'Shop jetzt öffnen' ?></button>
        </form>
        <h2>FORMA Maker Club · Template des Monats</h2>
        <form method="post" enctype="multipart/form-data" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="save_maker">
            <p><strong><?= $makers ?></strong> <?= $makers === 1 ? 'Mitglied' : 'Mitglieder' ?> im Maker Club. Sie stehen in MailerLite in der Gruppe „Maker Club“ (sobald MailerLite verbunden ist).</p>
            <?php foreach (forma_maker_planned($pdo) as $pm => $pt): ?><p class="muted">Schon geplant: ab <?= forma_h(date('m/Y', strtotime($pm . '-01'))) ?> automatisch „<?= forma_h($pt) ?>“.</p><?php endforeach; ?>
            <label for="maker_title">Name des Templates</label><input id="maker_title" name="maker_title" value="<?= forma_h($m['title']) ?>" placeholder="z. B. Herbstblatt-Schälchen">
            <label for="maker_text">Kurze Beschreibung</label><input id="maker_text" name="maker_text" value="<?= forma_h($m['text']) ?>" placeholder="z. B. Ein kleines Schälchen in Blattform, ideal für Ringe oder Teebeutel">
            <label for="maker_pdf">PDF auf Deutsch <?= $m['file'] ? '<span class="muted">(vorhanden, nur hochladen zum Ersetzen)</span>' : '' ?></label><input id="maker_pdf" name="maker_pdf" type="file" accept="application/pdf">
            <label for="maker_pdf_en">PDF auf Englisch <span class="muted"><?= $m['file_en'] ? '(vorhanden, nur hochladen zum Ersetzen)' : '(optional)' ?></span></label><input id="maker_pdf_en" name="maker_pdf_en" type="file" accept="application/pdf">
            <label for="maker_image">Vorschaubild <?= $m['image'] ? '<span class="muted">(vorhanden)</span>' : '' ?></label><input id="maker_image" name="maker_image" type="file" accept="image/jpeg,image/png,image/webp">
            <p class="muted small">Tipp: Jeden Monat hier das neue Template hochladen und danach in MailerLite eine kurze Mail an die Gruppe „Maker Club“ schicken.</p>
            <button class="btn" type="submit">Speichern</button>
        </form>
        <h2>Templates zum Kaufen</h2>
        <p><a class="btn" href="<?= forma_h($self) ?>?view=template_edit">+ Neues Template</a></p>
        <div class="list">
        <?php foreach ($tpls as $t): ?>
            <a class="card course-link<?= $t['visible'] ? '' : ' is-cancelled' ?>" href="<?= forma_h($self) ?>?view=template_edit&amp;id=<?= (int)$t['id'] ?>">
                <strong><?= forma_h($t['title']) ?></strong>
                <span class="muted small"><?= forma_h(FORMA_SEASONS[$t['season']] ?? '') ?> · <?= forma_h(FORMA_AGES[$t['age']] ?? '') ?> · <?= forma_h(forma_euro((int)$t['price_cents'])) ?><?= $t['file'] ? '' : ' · <b>PDF fehlt</b>' ?><?= $t['visible'] ? '' : ' · ausgeblendet' ?></span>
                <span class="pill"><?= (int)$t['sold'] ?> verkauft</span>
            </a>
        <?php endforeach; ?>
        <?php if (!$tpls): ?><p class="muted">Noch keine Templates angelegt.</p><?php endif; ?>
        </div>
        <h2>Verkäufe</h2>
        <p><a href="<?= forma_h($self) ?>?view=templates_export">Liste herunterladen (CSV)</a></p>
        <div class="list">
        <?php foreach ($orders as $o): ?>
            <div class="card booking"><div class="booking-head"><strong><?= forma_h($o['title']) ?></strong><span class="pill pill-ok"><?= forma_h(forma_euro((int)$o['amount_cents'])) ?></span></div>
            <div class="muted small"><?= forma_h(date('d. m. Y', strtotime($o['paid_at']))) ?> · <?= forma_h($o['name']) ?> · <span class="sel"><?= forma_h($o['email']) ?></span></div></div>
        <?php endforeach; ?>
        <?php if (!$orders): ?><p class="muted">Noch keine Verkäufe.</p><?php endif; ?>
        </div>
    <?php });
}

if ($view === 'kalender') {
    forma_reconcile_payments($pdo, true);
    $assignId = (int)($_GET['assign'] ?? 0);
    $assign = null;
    if ($assignId) {
        $st = $pdo->prepare("SELECT * FROM private_bookings WHERE id = ? AND status IN ('bezahlt','anfrage')");
        $st->execute([$assignId]);
        $assign = $st->fetch() ?: null;
    }
    $month = (string)($_GET['m'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = $assign && $assign['wish_date'] !== '' && $assign['wish_date'] >= date('Y-m-d') ? substr($assign['wish_date'], 0, 7) : date('Y-m');
    }
    $sel = (string)($_GET['d'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sel)) $sel = '';
    $first = $month . '-01';
    $last = date('Y-m-t', strtotime($first));
    // Offene Private-Pottery-Buchungen (noch nicht alle Stunden eingeplant)
    $open = [];
    foreach ($pdo->query("SELECT * FROM private_bookings WHERE status IN ('bezahlt','anfrage') AND done = 0 ORDER BY id")->fetchAll() as $b) {
        $b['planned'] = forma_private_planned($pdo, (int)$b['id']);
        $b['rest'] = $b['status'] === 'bezahlt' ? max(0, (float)$b['hours'] - $b['planned']) : null;
        if ($b['rest'] === null ? $b['planned'] == 0.0 : $b['rest'] > 0) $open[] = $b;
    }
    if ($assign) {
        $assign['planned'] = forma_private_planned($pdo, (int)$assign['id']);
        $assign['rest'] = $assign['status'] === 'bezahlt' ? max(0, (float)$assign['hours'] - $assign['planned']) : null;
        $assign['sessions'] = forma_private_sessions($pdo, (int)$assign['id']);
    }
    $events = [];
    $st = $pdo->prepare('SELECT * FROM courses WHERE start_date BETWEEN ? AND ? ORDER BY start_date, id');
    $st->execute([$first, $last]);
    foreach ($st->fetchAll() as $c) {
        $time = preg_match('/(\d{1,2}(?::\d{2})?\s*[–-]\s*\d{1,2}(?::\d{2})?)\s*Uhr/u', (string)$c['date_text'], $m) ? str_replace(' ', '', $m[1]) : '';
        $events[$c['start_date']][] = ['type' => 'kurs', 'sort' => $time ?: '99', 'time' => $time, 'title' => preg_replace('/\s*·.*$/u', '', (string)$c['title']), 'booked' => forma_booked($pdo, (int)$c['id']), 'cap' => (int)$c['capacity'], 'id' => (int)$c['id'], 'visible' => (int)$c['visible']];
    }
    $st = $pdo->prepare('SELECT s.*, p.name, p.project, p.email, p.phone, p.status FROM private_sessions s JOIN private_bookings p ON p.id = s.private_id WHERE s.date BETWEEN ? AND ? ORDER BY s.start');
    $st->execute([$first, $last]);
    foreach ($st->fetchAll() as $s) {
        $events[$s['date']][] = ['type' => 'private', 'sort' => sprintf('%05s', $s['start']), 'time' => forma_de_time((string)$s['start']), 's' => $s];
    }
    foreach ($open as $b) {
        if ($b['wish_date'] !== '' && $b['wish_date'] >= $first && $b['wish_date'] <= $last) {
            $events[$b['wish_date']][] = ['type' => 'wunsch', 'sort' => 'zz', 'b' => $b];
        }
    }
    foreach ($events as &$list) usort($list, fn($a, $b) => strcmp((string)$a['sort'], (string)$b['sort']));
    unset($list);
    $defaultStart = ['vormittag' => '09:00', 'nachmittag' => '14:00', 'abend' => '18:00'][$assign['wish_time'] ?? ''] ?? '18:00';
    render_page('Kalender', function () use ($self, $flash, $assign, $open, $events, $month, $sel, $first, $defaultStart, $pdo) {
        $q = fn(array $p) => forma_h($self . '?' . http_build_query(array_merge(['view' => 'kalender'], array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0))));
        $aid = $assign ? (int)$assign['id'] : 0;
        $prev = date('Y-m', strtotime($first . ' -1 month'));
        $next = date('Y-m', strtotime($first . ' +1 month'));
        $startW = ((int)date('N', strtotime($first))) - 1;
        $days = (int)date('t', strtotime($first));
        $today = date('Y-m-d'); ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <div class="top"><h1>Kalender</h1><a class="link" href="<?= forma_h($self) ?>?view=private">Alle Private-Pottery-Buchungen</a></div>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <?php if ($assign): ?>
        <div class="card assign">
            <div class="booking-head"><strong>Termin einplanen für <?= forma_h($assign['name']) ?></strong>
            <span class="pill<?= $assign['status'] === 'bezahlt' ? ' pill-ok' : '' ?>"><?= $assign['status'] === 'bezahlt'
                ? forma_h(forma_hours_text((float)$assign['hours']) . ' bezahlt' . ($assign['planned'] > 0 ? ($assign['rest'] > 0 ? ' · noch ' . forma_hours_text((float)$assign['rest']) . ' offen' : ' · alle eingeplant') : ''))
                : 'Anfrage, noch nicht bezahlt' ?></span></div>
            <div><?= nl2br(forma_h($assign['project'])) ?></div>
            <div class="small">📞 <?= ($assign['contact'] ?? 'email') === 'telefon' ? '<strong>Möchte angerufen werden</strong>' . (($assign['call_time'] ?? '') !== '' ? ', am besten ' . forma_h($assign['call_time']) : '') . ': <a href="tel:' . forma_h(preg_replace('/[^\d+]/', '', (string)$assign['phone'])) . '">' . forma_h($assign['phone']) . '</a>' : 'Melden per E-Mail: ' . forma_h($assign['email']) . ($assign['phone'] ? ' · ' . forma_h($assign['phone']) : '') ?></div>
            <?php $w = forma_wish_text($assign); if ($w): ?><div><span class="wish-dot"></span> Wunsch: <strong><?= forma_h($w) ?></strong></div><?php endif; ?>
            <?php $pending = array_filter($assign['sessions'], fn($s) => !$s['mailed_at']); $fn = forma_h(forma_first_name((string)$assign['name'])); ?>
            <?php if ($assign['sessions']): ?>
            <ul class="sess">
                <?php foreach ($assign['sessions'] as $k => $s): ?><li><a href="<?= $q(['m' => substr((string)$s['date'], 0, 7), 'd' => $s['date'], 'assign' => $aid]) ?>#tag"><?= forma_h(forma_de_date((string)$s['date']) . ', ' . forma_de_time((string)$s['start'])) ?></a> · <?= forma_h(forma_hours_text((float)$s['hours'])) ?> <span class="pill<?= $s['mailed_at'] ? ' pill-ok' : ' pill-wait' ?>"><?= $s['mailed_at'] ? 'bestätigt' : 'vorgemerkt' ?></span></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ($assign['rest'] === null || $assign['rest'] > 0): ?><p class="small muted">Tipp im Kalender auf den Tag, an dem ihr euch trefft. Mehrere Stunden kannst du auf verschiedene Tage verteilen. Wenn alles eingeplant ist, schickst du <?= $fn ?> eine einzige Bestätigung mit allen Terminen.</p><?php endif; ?>
            <?php if ($pending): ?>
            <form method="post" class="stack confirm-form">
                <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="private_confirm"><input type="hidden" name="pid" value="<?= $aid ?>"><input type="hidden" name="m" value="<?= forma_h($month) ?>">
                <label for="c-note">Persönliche Nachricht <span class="muted small">(optional, steht in der Bestätigung)</span></label>
                <textarea id="c-note" name="note" rows="2" maxlength="800" placeholder="z. B. Ich freu mich schon, deine Tassen mit dir zu formen!"></textarea>
                <button class="btn" type="submit">Bestätigung mit <?= count($assign['sessions']) === 1 ? 'dem Termin' : 'allen ' . count($assign['sessions']) . ' Terminen' ?> an <?= $fn ?> schicken</button>
                <?php if ($assign['status'] !== 'bezahlt' && forma_stripe_key()): $pl = forma_private_planned($pdo, $aid); ?><span class="small"><strong>Noch nicht bezahlt:</strong> In der Mail ist ein Knopf „Jetzt online bezahlen“ über <?= forma_h(forma_hours_text($pl) . ' · ' . forma_euro((int)round($pl * FORMA_PRIVATE_HOUR))) ?>. Sobald sie bezahlt, bekommt sie automatisch die Rechnung.</span><?php endif; ?>
                <span class="muted small">Geht an <?= forma_h($assign['email']) ?>, mit Kalendereintrag im Anhang<?= $assign['rest'] !== null && $assign['rest'] > 0 ? '. Achtung: es sind noch ' . forma_h(forma_hours_text((float)$assign['rest'])) . ' nicht eingeplant' : '' ?>.</span>
            </form>
            <?php elseif ($assign['sessions']): ?><p class="small"><strong>Alles bestätigt.</strong> <?= $fn ?> hat ihre Termine per E-Mail. Die Bitte um eine Google-Bewertung geht automatisch ca. 1 Stunde nach dem letzten Termin raus.</p><?php endif; ?>
            <a class="link small" href="<?= $q(['m' => $month, 'd' => $sel]) ?>"><?= $pending ? 'Später weitermachen' : 'Schließen' ?></a>
        </div>
        <?php endif; ?>
        <div class="cal-wrap">
        <div class="cal-main">
            <div class="cal-nav">
                <a class="btn btn-ghost" href="<?= $q(['m' => $prev, 'assign' => $aid]) ?>" aria-label="Voriger Monat">‹</a>
                <h2><?= forma_h(FORMA_MONTHS[(int)substr($month, 5, 2)] . ' ' . substr($month, 0, 4)) ?></h2>
                <a class="btn btn-ghost" href="<?= $q(['m' => $next, 'assign' => $aid]) ?>" aria-label="Nächster Monat">›</a>
                <a class="link small" href="<?= $q(['m' => date('Y-m'), 'd' => $today, 'assign' => $aid]) ?>">Heute</a>
            </div>
            <div class="cal">
                <?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $wd): ?><div class="cal-wd"><?= $wd ?></div><?php endforeach; ?>
                <?php for ($i = 0; $i < $startW; $i++): ?><div class="cal-day empty"></div><?php endfor; ?>
                <?php for ($d = 1; $d <= $days; $d++): $date = sprintf('%s-%02d', $month, $d); $ev = $events[$date] ?? [];
                    $isWish = $assign && $assign['wish_date'] === $date; ?>
                    <a class="cal-day<?= $date === $today ? ' today' : '' ?><?= $date === $sel ? ' sel' : '' ?><?= $date < $today ? ' past' : '' ?><?= $isWish ? ' wish' : '' ?>" href="<?= $q(['m' => $month, 'd' => $date, 'assign' => $aid]) ?>#tag">
                        <span class="num"><?= $d ?></span>
                        <?php foreach (array_slice($ev, 0, 4) as $e): ?>
                            <?php if ($e['type'] === 'kurs'): ?><span class="ev ev-kurs"><?= forma_h(trim($e['time'] . ' ' . $e['title'])) ?></span>
                            <?php elseif ($e['type'] === 'private'): ?><span class="ev ev-private<?= $e['s']['mailed_at'] ? '' : ' pending' ?>"><?= forma_h($e['time'] . ' ' . forma_first_name((string)$e['s']['name'])) ?></span>
                            <?php else: ?><span class="ev ev-wish">Wunsch: <?= forma_h(forma_first_name((string)$e['b']['name'])) ?></span><?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (count($ev) > 4): ?><span class="ev more">+<?= count($ev) - 4 ?></span><?php endif; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <p class="legend small muted"><span class="ev ev-kurs">Kurs</span> <span class="ev ev-private">Private Pottery</span> <span class="ev ev-private pending">vorgemerkt</span> <span class="ev ev-wish">Wunschtermin</span></p>
            <?php if ($sel === ''): ?><p class="small muted">Gast mit altem Gutschein? Tipp auf den Tag, an dem sie kommen möchte, dann findest du unten „Gast mit altem Gutschein eintragen“.</p><?php endif; ?>

            <?php if ($sel !== ''): $ev = $events[$sel] ?? []; ?>
            <div class="card day-panel" id="tag">
                <h2><?= forma_h(forma_de_date($sel)) ?></h2>
                <?php if (!$ev): ?><p class="muted">An diesem Tag ist noch nichts eingetragen.</p><?php endif; ?>
                <div class="list day-list">
                <?php foreach ($ev as $e): ?>
                    <?php if ($e['type'] === 'kurs'): ?>
                        <a class="day-item kurs" href="<?= forma_h($self) ?>?view=course&amp;id=<?= $e['id'] ?>"><strong><?= forma_h($e['time'] ? $e['time'] . ' Uhr' : '') ?></strong> <?= forma_h($e['title']) ?> <span class="muted small">· <?= $e['booked'] ?>/<?= $e['cap'] ?> belegt<?= $e['visible'] ? '' : ' · ausgeblendet' ?></span></a>
                    <?php elseif ($e['type'] === 'private'): $s = $e['s']; ?>
                        <div class="day-item private">
                            <div><strong><?= forma_h(forma_de_time((string)$s['start'])) ?></strong> Private Pottery · <strong><?= forma_h($s['name']) ?></strong> <span class="muted small">· <?= forma_h(forma_hours_text((float)$s['hours'])) ?></span></div>
                            <div class="small"><?= forma_h(mb_strimwidth((string)$s['project'], 0, 140, ' …')) ?></div>
                            <div class="small muted"><?= forma_h($s['email']) ?><?= $s['phone'] ? ' · ' . forma_h($s['phone']) : '' ?> · <?= $s['mailed_at'] ? 'bestätigt am ' . forma_h(date('d. m., H:i', strtotime($s['mailed_at']))) : '<strong>vorgemerkt, noch nicht bestätigt</strong>' ?></div>
                            <div class="row-actions">
                                <a class="link small" href="<?= $q(['m' => $month, 'd' => $sel, 'assign' => (int)$s['private_id']]) ?>">Alle Termine von <?= forma_h(forma_first_name((string)$s['name'])) ?><?= $s['mailed_at'] ? '' : ' · bestätigen' ?></a>
                                <details class="del"><summary class="link small">Verschieben / löschen</summary>
                                    <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="private_session_delete"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>">
                                    <?php if ($s['mailed_at']): ?><label class="check small"><input type="checkbox" name="notify" value="1" checked> <?= forma_h(forma_first_name((string)$s['name'])) ?> per E-Mail Bescheid geben</label><?php endif; ?>
                                    <button class="btn btn-danger" type="submit">Termin löschen</button>
                                    <span class="muted small">Die Stunden bleiben ihr erhalten und du kannst sie gleich neu einplanen.</span></form>
                                </details>
                            </div>
                        </div>
                    <?php else: $b = $e['b']; ?>
                        <a class="day-item wunsch" href="<?= $q(['m' => $month, 'd' => $sel, 'assign' => (int)$b['id']]) ?>#tag"><span class="wish-dot"></span> Wunschtermin von <strong><?= forma_h($b['name']) ?></strong> <span class="muted small">· <?= forma_h(FORMA_WISH_TIMES[$b['wish_time']] ?? '') ?></span> <span class="small">Einplanen →</span></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>

                <?php if ($assign && ($assign['rest'] === null || $assign['rest'] > 0)): $max = $assign['rest'] ?? 4.0; ?>
                <form method="post" class="assign-form stack">
                    <h3>Private Pottery mit <?= forma_h(forma_first_name((string)$assign['name'])) ?> eintragen</h3>
                    <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="private_assign"><input type="hidden" name="pid" value="<?= $aid ?>"><input type="hidden" name="date" value="<?= forma_h($sel) ?>">
                    <div class="row">
                        <div class="stack"><label for="a-start">Uhrzeit</label><input id="a-start" name="start" type="time" step="900" value="<?= forma_h($defaultStart) ?>" required></div>
                        <div class="stack"><label for="a-hours">Wie lange?</label><select id="a-hours" name="hours">
                            <?php $def = $assign['rest'] === null ? max(1.0, min(4.0, (float)$assign['hours'] ?: 2.0)) : (float)$assign['rest']; for ($h = 0.5; $h <= max(1, $max) + 0.001; $h += 0.5): ?><option value="<?= $h ?>"<?= abs($h - $def) < 0.01 ? ' selected' : '' ?>><?= forma_h(forma_hours_text($h)) ?></option><?php endfor; ?>
                        </select></div>
                    </div>
                    <?php if ($assign['rest'] !== null && $assign['rest'] > 1): ?><p class="small muted">Du willst die <?= forma_h(forma_hours_text((float)$assign['rest'])) ?> aufteilen? Wähl hier z. B. 1 Stunde und tipp danach auf den nächsten Tag.</p><?php endif; ?>
                    <button class="btn" type="submit">Termin vormerken</button>
                    <span class="muted small">Es geht noch keine E-Mail raus. Die Bestätigung schickst du oben, sobald alle Termine eingetragen sind.</span>
                </form>
                <?php elseif ($open): ?>
                <form method="get" class="stack assign-pick"><input type="hidden" name="view" value="kalender"><input type="hidden" name="m" value="<?= forma_h($month) ?>"><input type="hidden" name="d" value="<?= forma_h($sel) ?>">
                    <label for="pick">Private Pottery an diesem Tag einplanen</label>
                    <select id="pick" name="assign" onchange="this.form.submit()"><option value="">Buchung auswählen …</option>
                    <?php foreach ($open as $b): ?><option value="<?= (int)$b['id'] ?>"><?= forma_h($b['name'] . ' · ' . ($b['rest'] !== null ? 'noch ' . forma_hours_text((float)$b['rest']) : 'Anfrage')) ?></option><?php endforeach; ?>
                    </select><noscript><button class="btn btn-ghost" type="submit">Auswählen</button></noscript>
                </form>
                <?php endif; ?>

                <?php $spins = array_values(array_filter($ev, fn($e) => $e['type'] === 'kurs' && str_starts_with((string)$e['title'], 'Spin'))); ?>
                <details class="oldv"<?= isset($_GET['alt']) ? ' open' : '' ?>>
                    <summary>🎟 Gast mit altem Gutschein eintragen</summary>
                    <form method="post" class="stack oldv-form">
                        <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="old_voucher"><input type="hidden" name="date" value="<?= forma_h($sel) ?>">
                        <p class="small muted">Für Gutscheine, die schon bezahlt sind, aber noch keine Nummer haben. Der Gast bekommt die Terminbestätigung mit Kalendereintrag und die Rechnung, nach dem Kurs die Bitte um eine Google-Bewertung.</p>
                        <label for="o-course">Spin & Go am <?= forma_h(forma_de_date($sel, false)) ?></label>
                        <select id="o-course" name="course" onchange="document.getElementById('o-new').hidden=this.value!=='neu'">
                            <?php foreach ($spins as $e): ?><option value="<?= (int)$e['id'] ?>"><?= forma_h(trim(($e['time'] ? $e['time'] . ' Uhr ' : '') . $e['title'])) ?> · <?= (int)$e['booked'] ?>/<?= (int)$e['cap'] ?> belegt</option><?php endforeach; ?>
                            <option value="neu"<?= $spins ? '' : ' selected' ?>>Neuer Spin & Go Termin an diesem Tag</option>
                        </select>
                        <div id="o-new" class="stack"<?= $spins ? ' hidden' : '' ?>>
                            <div class="row">
                                <div class="stack"><label for="o-from">Von</label><input id="o-from" name="from" type="time" step="900" value="09:00"></div>
                                <div class="stack"><label for="o-to">Bis</label><input id="o-to" name="to" type="time" step="900" value="11:00"></div>
                            </div>
                            <label class="check small"><input type="checkbox" name="public" value="1"> Termin auch auf der Website buchbar machen (sonst nur für diesen Gast)</label>
                        </div>
                        <div class="row">
                            <div class="stack"><label for="o-name">Name</label><input id="o-name" name="name" required maxlength="120" autocomplete="off"></div>
                            <div class="stack"><label for="o-mail">E-Mail</label><input id="o-mail" name="email" type="email" required maxlength="160" autocomplete="off"></div>
                        </div>
                        <div class="row">
                            <div class="stack"><label for="o-phone">Telefon <span class="muted small">(optional)</span></label><input id="o-phone" name="phone" type="tel" maxlength="40" autocomplete="off"></div>
                            <div class="stack"><label for="o-pers">Personen</label><select id="o-pers" name="persons" onchange="var v=document.getElementById('o-val');if(!v.dataset.touched)v.value=this.value*70"><?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></div>
                        </div>
                        <div class="row">
                            <div class="stack"><label for="o-val">Wert des Gutscheins in €</label><input id="o-val" name="value" inputmode="decimal" value="70" required oninput="this.dataset.touched=1"></div>
                            <div class="stack"><label for="o-nr">Gutscheinnummer <span class="muted small">(leer = wird vergeben)</span></label><input id="o-nr" name="nr" maxlength="40" placeholder="z. B. ALT-<?= date('Y') ?>-001" autocomplete="off"></div>
                        </div>
                        <label for="o-note">Notiz <span class="muted small">(nur für dich, optional)</span></label>
                        <input id="o-note" name="note" maxlength="300" placeholder="z. B. Weihnachtsgutschein 2025 von der Oma">
                        <label class="check small"><input type="checkbox" name="post" value="1" checked> Bekommt die FORMA Post (Abmeldelink steht in jeder Mail)</label>
                        <button class="btn" type="submit">Eintragen &amp; Bestätigung mit Rechnung schicken</button>
                        <span class="muted small">Der Gutschein wird mit dem Kurspreis verrechnet (€ 70 pro Person) und als eingelöst gespeichert. Bleibt Wert übrig, steht der Restwert unter Gutscheine.</span>
                    </form>
                </details>
            </div>
            <?php endif; ?>
        </div>
        <aside class="cal-side">
            <h2>Zum Einplanen</h2>
            <?php if (!$open): ?><p class="muted small">Alles eingeplant. Neue Private-Pottery-Buchungen erscheinen automatisch hier.</p><?php endif; ?>
            <div class="list side-list">
            <?php foreach ($open as $b): $w = forma_wish_text($b); ?>
                <a class="card side-item<?= $b['id'] == $aid ? ' active' : '' ?>" href="<?= $q(['m' => $b['wish_date'] !== '' && $b['wish_date'] >= date('Y-m-d') ? substr($b['wish_date'], 0, 7) : $month, 'd' => $b['wish_date'] >= date('Y-m-d') ? $b['wish_date'] : '', 'assign' => (int)$b['id']]) ?>#tag">
                    <strong><?= forma_h($b['name']) ?></strong>
                    <span class="pill<?= $b['status'] === 'bezahlt' ? ' pill-ok' : '' ?>"><?= $b['status'] === 'bezahlt' ? forma_h('noch ' . forma_hours_text((float)$b['rest']) . ' · bezahlt') : 'Anfrage' ?></span>
                    <?php if ($w): ?><span class="small"><span class="wish-dot"></span> <?= forma_h($w) ?></span><?php endif; ?>
                    <span class="small muted"><?= forma_h(mb_strimwidth((string)$b['project'], 0, 90, ' …')) ?></span>
                    <?php if (($b['contact'] ?? '') === 'telefon'): ?><span class="small">📞 anrufen<?= ($b['call_time'] ?? '') !== '' ? ', am besten ' . forma_h($b['call_time']) : '' ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            </div>
        </aside>
        </div>
    <?php });
}

if ($view === 'private') {
    forma_reconcile_payments($pdo, true);
    $rows = $pdo->query("SELECT * FROM private_bookings WHERE status IN ('bezahlt','anfrage') ORDER BY done, id DESC")->fetchAll();
    render_page('Private Pottery', function () use ($self, $flash, $rows, $pdo) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Private Pottery</h1>
        <p class="muted">Bezahlte Buchungen und Anfragen. Mach mit jeder Person den Termin aus, trag ihn im Kalender ein und schick ihr die Bestätigung. Nach dem letzten Termin geht die Bitte um eine Google-Bewertung automatisch raus.</p>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="list">
        <?php foreach ($rows as $b): $paid = $b['status'] === 'bezahlt'; ?>
            <div class="card booking<?= $b['done'] ? ' is-cancelled' : '' ?>">
                <div class="booking-head"><strong><?= forma_h($b['name']) ?></strong>
                    <span class="pill<?= $paid ? ' pill-ok' : '' ?>"><?= $paid ? forma_h(((int)$b['hours'] === 1 ? '1 Stunde' : $b['hours'] . ' Stunden') . ' · ' . forma_euro((int)$b['amount_cents']) . ' bezahlt') : 'Anfrage, noch nicht gebucht' ?></span></div>
                <div><?= nl2br(forma_h($b['project'])) ?></div>
                <?php $w = forma_wish_text($b); if ($w): ?><div class="small"><span class="wish-dot"></span> Wunsch: <?= forma_h($w) ?></div><?php endif; ?>
                <?php $ss = forma_private_sessions($pdo, (int)$b['id']); if ($ss): ?><ul class="sess small"><?php foreach ($ss as $s): ?><li><?= forma_h(forma_de_date((string)$s['date']) . ', ' . forma_de_time((string)$s['start']) . ' · ' . forma_hours_text((float)$s['hours'])) ?> <span class="pill<?= $s['mailed_at'] ? ' pill-ok' : ' pill-wait' ?>"><?= $s['mailed_at'] ? 'bestätigt' : 'vorgemerkt' ?></span></li><?php endforeach; ?></ul><?php endif; ?>
                <?php if (!$b['done']): ?><a class="btn btn-ghost btn-sm" href="<?= forma_h($self) ?>?view=kalender&amp;assign=<?= (int)$b['id'] ?>">📅 <?= $ss ? 'Termine im Kalender ansehen' : 'Im Kalender einplanen' ?></a><?php endif; ?>
                <div class="sel small"><?= forma_h($b['email']) ?><?= $b['phone'] ? ' · ' . forma_h($b['phone']) : '' ?></div>
                <div><strong>Melden per <?= ($b['contact'] ?? 'email') === 'telefon' ? 'Telefon' . (($b['call_time'] ?? '') !== '' ? ', am besten ' . forma_h($b['call_time']) : '') : 'E-Mail' ?></strong><?= (int)$b['hours'] > 0 && !$paid ? ' · geschätzt ' . ((int)$b['hours'] === 1 ? '1 Stunde' : (int)$b['hours'] . ' Stunden') : '' ?></div>
                <div class="muted small"><?= $paid ? 'Gebucht' : 'Angefragt' ?> am <?= forma_h(date('d. m. Y, H:i', strtotime($b['created_at']))) ?></div>
                <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="private_done"><input type="hidden" name="pid" value="<?= (int)$b['id'] ?>">
                <button class="link" type="submit"><?= $b['done'] ? 'Wieder als offen markieren' : 'Als erledigt abhaken' ?></button></form>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><p class="muted">Noch keine Buchungen oder Anfragen.</p><?php endif; ?>
        </div>
    <?php });
}

if ($view === 'gutscheine') {
    forma_reconcile_payments($pdo, true);
    $vs = $pdo->query("SELECT * FROM vouchers WHERE status IN ('bezahlt','eingeloest') ORDER BY id DESC")->fetchAll();
    $sold = array_filter($vs, fn($x) => ($x['kind'] ?? 'kauf') === 'kauf');
    $sum = array_sum(array_column($sold, 'amount_cents'));
    $links = $pdo->query("SELECT l.*, c.date_text FROM voucher_links l LEFT JOIN bookings b ON b.id = l.booking_id LEFT JOIN courses c ON c.id = b.course_id WHERE l.status != 'geloescht' ORDER BY l.status = 'offen' DESC, l.id DESC LIMIT 40")->fetchAll();
    $neu = (int)($_GET['neu'] ?? 0);
    render_page('Gutscheine', function () use ($self, $flash, $vs, $sum, $links, $neu) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Gutscheine</h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="card stack">
            <p><strong><?= count(array_filter($vs, fn($x) => ($x['kind'] ?? 'kauf') === 'kauf')) ?></strong> verkaufte Gutscheine · zusammen <strong><?= forma_h(forma_euro((int)$sum)) ?></strong></p>
            <p class="muted small">Jeder bezahlte Gutschein bekommt automatisch eine fortlaufende Nummer und einen geheimen Code. Mit dem Code lösen Gäste ihn online bei der Buchung ein, das wird hier automatisch abgezogen. Löst jemand im Atelier ein, ziehst du den Betrag hier selbst ab. Der Restwert bleibt auf dem Gutschein.</p>
            <p style="display:flex;gap:12px;flex-wrap:wrap"><a class="btn" href="<?= forma_h($self) ?>?view=kalender&amp;d=<?= date('Y-m-d') ?>&amp;alt=1#tag">🎟 Alten Gutschein einlösen</a><a class="btn btn-ghost" href="<?= forma_h($self) ?>?view=gutscheine_export">Liste herunterladen (CSV)</a></p>
        </div>
        <div class="card stack links-card" id="links">
            <h2>🎟 Link für alten Gutschein</h2>
            <p class="muted small">Für Gäste mit einem alten Gutschein ohne Nummer. Du schickst ihnen einen persönlichen Link, sie suchen sich selbst einen Spin &amp; Go Termin aus und geben ihre Daten ein. Der Gutschein bekommt dabei automatisch eine Nummer, der Gast bekommt Bestätigung und Rechnung (bezahlt mit Gutschein), du eine Info-Mail. Jeder Link funktioniert nur einmal.</p>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="link_create">
                <div class="row">
                    <div class="stack"><label for="l-label">Für wen? <span class="muted small">(Name zur Begrüßung)</span></label><input id="l-label" name="label" maxlength="120" placeholder="z. B. Anna Huber" autocomplete="off"></div>
                    <div class="stack"><label for="l-pers">Personen auf dem Gutschein</label><select id="l-pers" name="persons" onchange="var v=document.getElementById('l-val');if(!v.dataset.touched)v.value=this.value*70"><?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></div>
                </div>
                <div class="row">
                    <div class="stack"><label for="l-val">Wert in €</label><input id="l-val" name="value" inputmode="decimal" value="70" oninput="this.dataset.touched=1"></div>
                    <div class="stack"><label for="l-note">Notiz <span class="muted small">(nur für dich)</span></label><input id="l-note" name="note" maxlength="300" placeholder="z. B. Weihnachtsgutschein 2025" autocomplete="off"></div>
                </div>
                <button class="btn" type="submit">Link erstellen</button>
            </form>
            <?php if ($links): ?>
            <div class="list">
            <?php foreach ($links as $l): $url = forma_old_link_url($l); $open = $l['status'] === 'offen';
                $who = $l['label'] !== '' ? $l['label'] : 'Gast'; $wa = 'https://wa.me/?text=' . rawurlencode('Hallo ' . ($l['label'] !== '' ? forma_first_name($l['label']) : '') . "! Schön, dass du deinen FORMA Gutschein einlöst. Hier kannst du dir ganz einfach deinen Spin & Go Termin aussuchen: $url\nBis bald, Vivien"); ?>
                <div class="card booking link-item<?= $open ? '' : ' is-cancelled' ?><?= (int)$l['id'] === $neu ? ' is-new' : '' ?>">
                    <div class="booking-head"><strong><?= forma_h($who) ?></strong><span class="pill<?= $open ? '' : ' pill-ok' ?>"><?= $open ? 'wartet auf Buchung' : 'eingelöst' . ($l['used_at'] ? ' am ' . forma_h(date('d. m.', strtotime($l['used_at']))) : '') ?></span></div>
                    <div class="small"><?= (int)$l['persons'] ?> <?= (int)$l['persons'] === 1 ? 'Person' : 'Personen' ?> · <?= forma_h(forma_euro((int)$l['value_cents'])) ?><?= $l['note'] !== '' ? ' · ' . forma_h($l['note']) : '' ?></div>
                    <?php if ($open): ?>
                    <div class="link-row"><input class="link-url" value="<?= forma_h($url) ?>" readonly onclick="this.select()"><button class="btn btn-ghost btn-sm" type="button" onclick="var i=this.previousElementSibling;i.select();(navigator.clipboard?navigator.clipboard.writeText(i.value):Promise.reject()).then(()=>{this.textContent='Kopiert ✓'}).catch(()=>{document.execCommand('copy');this.textContent='Kopiert ✓'})">Kopieren</button></div>
                    <div class="row-actions">
                        <a class="btn btn-sm" href="<?= forma_h($wa) ?>" target="_blank" rel="noopener">Per WhatsApp schicken</a>
                        <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="link_renew"><input type="hidden" name="lid" value="<?= (int)$l['id'] ?>"><button class="link small" type="submit">Neuen Link erzeugen</button></form>
                        <form method="post" onsubmit="return confirm('Link wirklich löschen?')"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="link_delete"><input type="hidden" name="lid" value="<?= (int)$l['id'] ?>"><button class="link small" type="submit">Löschen</button></form>
                    </div>
                    <?php else: ?><div class="muted small">Gebucht: <?= forma_h((string)$l['date_text']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="list" style="margin-top:16px">
        <?php foreach ($vs as $v): $done = $v['status'] === 'eingeloest'; ?>
            <div class="card booking<?= $done ? ' is-cancelled' : '' ?>">
                <?php $bal = forma_voucher_balance($GLOBALS['pdo'], $v); $uses = $GLOBALS['pdo']->prepare("SELECT * FROM voucher_uses WHERE voucher_id = ? AND status != 'frei' ORDER BY id"); $uses->execute([$v['id']]); $uses = $uses->fetchAll(); ?>
                <div class="booking-head"><strong><?= forma_h($v['nr']) ?><?= ($v['kind'] ?? 'kauf') === 'alt' ? ' · alter Gutschein, nachgetragen' : (($v['kind'] ?? 'kauf') !== 'kauf' ? ' · Geschenk von dir' : '') ?></strong><span class="pill<?= $done ? '' : ' pill-ok' ?>"><?= $done ? 'eingelöst' . ($v['redeemed_at'] ? ' am ' . forma_h(date('d. m. Y', strtotime($v['redeemed_at']))) : '') : ($bal < (int)$v['amount_cents'] ? 'Restwert ' . forma_h(forma_euro($bal)) : 'offen') ?></span></div>
                <div><?= forma_h(forma_voucher_title($v)) ?> · <?= forma_h(forma_euro((int)$v['amount_cents'])) ?></div>
                <div class="small">Code: <span class="sel" style="font-weight:600;letter-spacing:.04em"><?= forma_h((string)$v['code']) ?></span></div>
                <?php foreach ($uses as $u): ?><div class="muted small">− <?= forma_h(forma_euro((int)$u['cents'])) ?> <?= $u['ref_kind'] === 'atelier' ? 'im Atelier eingelöst' : (($v['kind'] ?? '') === 'alt' ? 'für einen Kurstermin eingelöst' : 'online eingelöst') ?><?= $u['status'] === 'reserviert' ? ' (Zahlung läuft gerade)' : '' ?> · <?= forma_h(date('d. m. Y', strtotime($u['created_at']))) ?><?= $u['note'] !== '' ? ' · ' . forma_h($u['note']) : '' ?></div><?php endforeach; ?>
                <?php if ($v['recipient'] || $v['sender']): ?><div class="muted small"><?= $v['recipient'] ? 'Für ' . forma_h($v['recipient']) : '' ?><?= $v['recipient'] && $v['sender'] ? ' · ' : '' ?><?= $v['sender'] ? 'von ' . forma_h($v['sender']) : '' ?></div><?php endif; ?>
                <div class="muted small"><?= ($v['kind'] ?? '') === 'alt' ? 'Nachgetragen am' : 'Gekauft am' ?> <?= forma_h(date('d. m. Y', strtotime($v['paid_at']))) ?> von <?= forma_h($v['buyer_name']) ?></div>
                <div class="sel small"><?= forma_h($v['buyer_email']) ?></div>
                <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center">
                    <a href="<?= forma_h(forma_voucher_link($v)) ?>" target="_blank" rel="noopener">PDF ansehen</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="voucher_status"><input type="hidden" name="vid" value="<?= (int)$v['id'] ?>"><input type="hidden" name="to" value="<?= $done ? 'bezahlt' : 'eingeloest' ?>">
                    <button class="link" type="submit"><?= $done ? 'Doch nicht eingelöst' : 'Ganz als eingelöst markieren' ?></button></form>
                </div>
                <?php if (!$done && $bal > 0): ?><form method="post" class="vredeem"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="voucher_partial"><input type="hidden" name="vid" value="<?= (int)$v['id'] ?>">
                    <label class="small">Im Atelier einlösen: Betrag in €<input name="eur" inputmode="decimal" placeholder="z. B. 30" style="max-width:120px"></label><input name="note" placeholder="wofür? (optional)" class="small" style="max-width:220px"><button class="btn btn-ghost" type="submit" style="padding:7px 16px">Abziehen</button></form><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$vs): ?><p class="muted">Noch keine Gutscheine verkauft.</p><?php endif; ?>
        </div>
    <?php });
}

if ($view === 'series') {
    render_page('Serientermine', function () use ($self, $flash) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Serientermine anlegen</h1>
        <p class="muted">Legt für jeden gewählten Wochentag im Zeitraum einen eigenen Termin an. Termine, die es schon gibt, werden übersprungen.</p>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <form method="post" class="card stack" id="series">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
            <input type="hidden" name="action" value="create_series">
            <label for="tpl">Vorlage</label>
            <select id="tpl">
                <option value="">Eigener Kurs</option>
                <?php foreach (FORMA_SERIES as $k => $t): ?><option value="<?= forma_h($k) ?>" <?= $k === 'spin' ? 'selected' : '' ?>><?= forma_h($t['title']) ?></option><?php endforeach; ?>
            </select>
            <label for="title">Name des Kurses</label><input id="title" name="title" required>
            <fieldset class="days"><legend>Wochentage</legend>
                <?php foreach (FORMA_WEEKDAYS as $n => $d): ?><label class="check"><input type="checkbox" name="weekdays[]" value="<?= $n ?>"> <?= $d ?></label><?php endforeach; ?>
            </fieldset>
            <div class="row">
                <div class="stack"><label for="time_from">Von (Uhr)</label><input id="time_from" name="time_from" placeholder="9" required></div>
                <div class="stack"><label for="time_to">Bis (Uhr)</label><input id="time_to" name="time_to" placeholder="11" required></div>
            </div>
            <div class="row">
                <div class="stack"><label for="from_date">Ab</label><input id="from_date" name="from_date" type="date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div>
                <div class="stack"><label for="to_date">Bis</label><input id="to_date" name="to_date" type="date" value="<?= date('Y-m-d', strtotime('+3 months')) ?>" required></div>
            </div>
            <div class="row">
                <div class="stack"><label for="capacity">Plätze pro Termin</label><input id="capacity" name="capacity" type="number" min="1"></div>
                <div class="stack"><label for="min_participants">Findet statt ab</label><input id="min_participants" name="min_participants" type="number" min="1"></div>
            </div>
            <label for="price_text">Preis, wie er auf der Website steht</label><input id="price_text" name="price_text">
            <label for="price_online">Online bezahlen: Betrag pro Person in € <span class="muted">(leer lassen = Bezahlung vor Ort)</span></label><input id="price_online" name="price_online" inputmode="decimal">
            <label for="description">Kurze Beschreibung</label><textarea id="description" name="description" rows="3"></textarea>
            <button class="btn" type="submit">Termine anlegen</button>
        </form>
        <script>
        (function(){
          var T=<?= json_encode(FORMA_SERIES, JSON_UNESCAPED_UNICODE) ?>, days={spin:[3,5],atelier:[4]};
          var f=document.getElementById('series'), sel=document.getElementById('tpl');
          function v(n,x){f.elements.namedItem(n).value=x;}
          function fill(){var t=T[sel.value];if(!t)return;
            v('title',t.title);v('time_from',t.from);v('time_to',t.to);v('capacity',t.capacity);v('min_participants',t.min);
            v('price_text',t.price_text);v('price_online',t.price_cents?String(t.price_cents/100).replace('.',','):'');v('description',t.description);
            f.querySelectorAll('input[name="weekdays[]"]').forEach(function(c){c.checked=(days[sel.value]||[]).indexOf(+c.value)>-1;});}
          sel.addEventListener('change',fill);fill();
        })();
        </script>
    <?php });
}

if ($view === 'einstellungen') {
    render_page('Einstellungen', function () use ($self, $flash) { $key = forma_stripe_key(); ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Einstellungen</h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <h2>Online-Bezahlung mit Stripe</h2>
        <?php if ($key): ?>
            <div class="card stack">
                <p><span class="pill<?= forma_stripe_test_mode($key) ? '' : ' pill-ok' ?>"><?= forma_stripe_test_mode($key) ? 'Testmodus' : 'Live' ?></span></p>
                <p class="muted"><?= forma_stripe_test_mode($key)
                    ? 'Im Testmodus wird kein echtes Geld bewegt. Probiere eine Buchung mit der Testkarte 4242 4242 4242 4242 (beliebiges Ablaufdatum in der Zukunft, beliebige Prüfziffer). Wenn alles passt, trägst du hier deinen Live-Schlüssel ein.'
                    : 'Kurse mit Online-Preis werden direkt bei der Buchung bezahlt. Das Geld landet auf deinem Stripe-Konto, Rückerstattungen machst du im Stripe-Dashboard.' ?></p>
                <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="remove_stripe"><button class="link" type="submit">Stripe trennen</button></form>
            </div>
        <?php endif; ?>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
            <input type="hidden" name="action" value="save_stripe">
            <p><?= $key ? 'Schlüssel wechseln (z. B. vom Test- auf den Live-Schlüssel):' : 'Damit deine Kundinnen Kurse online bezahlen können, verbindest du hier einmal dein Stripe-Konto.' ?></p>
            <ol class="muted small steps">
                <li>Melde dich bei <strong>dashboard.stripe.com</strong> an.</li>
                <li>Geh auf <strong>Entwickler → API-Schlüssel</strong>. Zum Ausprobieren oben den <strong>Testmodus</strong> einschalten.</li>
                <li>Beim <strong>Geheimschlüssel</strong> auf „Anzeigen“ klicken, kopieren und hier einfügen. Er beginnt mit „sk_test_“ bzw. „sk_live_“.</li>
            </ol>
            <label for="stripe_key">Geheimer Schlüssel</label>
            <input id="stripe_key" name="stripe_key" type="password" autocomplete="off" required>
            <button class="btn" type="submit">Prüfen und speichern</button>
            <p class="muted small">Der Schlüssel wird nur auf deinem Server gespeichert und nie angezeigt. Bitte gib ihn niemandem weiter, auch nicht per Chat oder Mail.</p>
        </form>
        <h2>Google-Bewertungen</h2>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="save_review">
            <p class="muted small">Eine Stunde nach jedem Kurs bekommt jeder Gast, der online gebucht hat, automatisch eine kurze Mail mit der Bitte um eine Google-Bewertung. Bei Private Pottery geht sie raus, sobald du den Termin als erledigt markierst.</p>
            <label><input type="checkbox" name="review_on" value="1" <?= forma_setting($GLOBALS['pdo'], 'review_off') === '1' ? '' : 'checked' ?>> Bewertungs-Mails verschicken</label>
            <label for="review_url">Link zur Google-Bewertung</label>
            <input id="review_url" name="review_url" value="<?= forma_h(forma_review_url($GLOBALS['pdo'])) ?>">
            <button class="btn" type="submit">Speichern</button>
        </form>
        <h2>WhatsApp-Community</h2>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="save_community">
            <p class="muted small">In WhatsApp deine Community öffnen → „Mitglieder einladen“ → „Link kopieren“ und hier einfügen. Dann erscheint auf der Website im Kontaktbereich der Knopf „WhatsApp-Community beitreten“. Feld leer lassen und speichern blendet ihn wieder aus.</p>
            <label for="community">Einladungslink</label>
            <input id="community" name="community" value="<?= forma_h(forma_setting($GLOBALS['pdo'], 'community') ?? '') ?>" placeholder="https://chat.whatsapp.com/…">
            <button class="btn" type="submit">Speichern</button>
        </form>
        <h2>MailerLite (E-Mail-Liste)</h2>
        <?php if (forma_mailerlite()): ?>
            <div class="card stack">
                <p><span class="pill pill-ok">Verbunden</span></p>
                <p class="muted">Wer sich über das Pop-up einträgt und die Adresse bestätigt, landet automatisch in MailerLite in der Gruppe „Website“. Abmeldungen werden ebenfalls übertragen.</p>
                <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="remove_mailerlite"><button class="link" type="submit">MailerLite trennen</button></form>
            </div>
        <?php else: ?>
            <form method="post" class="card stack">
                <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                <input type="hidden" name="action" value="save_mailerlite">
                <p>Verbinde deine E-Mail-Liste mit MailerLite, dann landen neue Kontakte automatisch dort und du kannst ihnen direkt Newsletter schicken.</p>
                <ol class="muted small steps">
                    <li>In MailerLite links auf <strong>Integrationen</strong> klicken.</li>
                    <li>Bei <strong>MailerLite API</strong> auf „Verwenden“ und dann „Neuen Token erstellen“, Name z. B. „Website“.</li>
                    <li>Den Schlüssel kopieren und hier einfügen.</li>
                </ol>
                <label for="ml_key">API-Schlüssel von MailerLite</label>
                <input id="ml_key" name="ml_key" type="password" autocomplete="off" required>
                <button class="btn" type="submit">Verbinden</button>
            </form>
        <?php endif; ?>
        <h2>E-Mail-Versand</h2>
        <?php if (forma_mail_config()): ?>
            <div class="card stack">
                <p><span class="pill pill-ok">Eingerichtet</span></p>
                <p class="muted">Bestätigungen und Benachrichtigungen gehen über dein Postfach <?= forma_h(FORMA_FROM_EMAIL) ?> raus.</p>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                    <input type="hidden" name="action" value="remove_mail">
                    <button class="link" type="submit">Ausschalten</button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" class="card stack">
                <p>Damit deine E-Mails nicht im Spam landen, verschickt die Website sie am besten über dein Postfach <strong><?= forma_h(FORMA_FROM_EMAIL) ?></strong>. Gib dafür hier einmal das Passwort dieses Postfachs ein. Es wird nur auf deinem Server gespeichert.</p>
                <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                <input type="hidden" name="action" value="save_mail">
                <label for="mail_password">Passwort von <?= forma_h(FORMA_FROM_EMAIL) ?></label>
                <input id="mail_password" name="mail_password" type="password" autocomplete="off" required>
                <button class="btn" type="submit">Speichern und Test-Mail schicken</button>
            </form>
        <?php endif; ?>
    <?php });
}

if ($view === 'popup') {
    $p = forma_popup($pdo);
    render_page('Saison-Pop-up', function () use ($self, $flash, $p) { $live = forma_popup_live($p); ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Saison-Pop-up</h1>
        <p class="muted">Ein kleines Fenster, das beim Besuch der Website einmal aufgeht, z. B. für Specials im Advent oder in den Ferien. Besucherinnen können sich dort für deine E-Mail-Liste eintragen.</p>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <p><span class="pill<?= $live ? ' pill-ok' : '' ?>"><?= $live ? 'Gerade auf der Website sichtbar' : ($p['active'] ? 'Eingeschaltet, aber außerhalb des Zeitraums' : 'Ausgeschaltet') ?></span>
           · <a href="/?popup=vorschau" target="_blank" rel="noopener">Vorschau ansehen</a></p>
        <form method="post" enctype="multipart/form-data" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
            <input type="hidden" name="action" value="save_popup">
            <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Pop-up auf der Website anzeigen</label>
            <div class="row">
                <div class="stack"><label for="start">Sichtbar ab</label><input id="start" name="start" type="date" value="<?= forma_h($p['start']) ?>"></div>
                <div class="stack"><label for="end">Sichtbar bis</label><input id="end" name="end" type="date" value="<?= forma_h($p['end']) ?>"></div>
            </div>
            <label for="eyebrow">Kleine Zeile oben <span class="muted">(z. B. „Special im Advent“)</span></label><input id="eyebrow" name="eyebrow" value="<?= forma_h($p['eyebrow']) ?>">
            <label for="ptitle">Titel</label><input id="ptitle" name="title" value="<?= forma_h($p['title']) ?>" required>
            <label for="ptext">Text</label><textarea id="ptext" name="text" rows="5"><?= forma_h($p['text']) ?></textarea>
            <label for="date_line">Termin oder Zeitraum <span class="muted">(optional)</span></label><input id="date_line" name="date_line" value="<?= forma_h($p['date_line']) ?>">
            <label for="image">Bild oder Flyer <span class="muted">(optional, JPG/PNG)</span></label>
            <?php if ($p['image']): ?><img class="thumb" src="../api/popup-image.php?v=<?= (int)$p['version'] ?>" alt=""><label class="check"><input type="checkbox" name="remove_image" value="1"> Bild entfernen</label><?php endif; ?>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
            <label for="theme">Gestaltung</label>
            <select id="theme" name="theme">
                <option value="" <?= $p['theme'] === '' ? 'selected' : '' ?>>Schlicht</option>
                <option value="advent" <?= $p['theme'] === 'advent' ? 'selected' : '' ?>>Weihnachtlich (Girlande und rieselnde Sternchen)</option>
            </select>
            <label class="check"><input type="checkbox" name="signup" value="1" <?= $p['signup'] ? 'checked' : '' ?>> Eintragen für die E-Mail-Liste anbieten</label>
            <div class="row">
                <div class="stack"><label for="button_text">Zusätzlicher Knopf <span class="muted">(optional)</span></label><input id="button_text" name="button_text" value="<?= forma_h($p['button_text']) ?>" placeholder="z. B. Jetzt buchen"></div>
                <div class="stack"><label for="button_link">Ziel des Knopfs</label><input id="button_link" name="button_link" value="<?= forma_h($p['button_link']) ?>" placeholder="#anmeldung"></div>
            </div>
            <button class="btn" type="submit">Speichern</button>
        </form>
        <h2>Saisonale Deko auf der Website</h2>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
            <input type="hidden" name="action" value="save_deko">
            <p class="muted small">Kleine Icons, die zur Jahreszeit passen: Herbstblätter, Kürbis und Tasse, im Winter Schneeflocken und Tannen, im Frühling Tulpen, im Sommer Sonne und Berge. „Automatisch“ wechselt von selbst nach Monat.</p>
            <label for="deko">Deko</label>
            <select id="deko" name="deko">
                <?php $cur = forma_setting($GLOBALS['pdo'], 'deko') ?? 'auto';
                foreach (['auto' => 'Automatisch nach Jahreszeit', 'herbst' => 'Herbst', 'winter' => 'Winter', 'fruehling' => 'Frühling', 'sommer' => 'Sommer', 'aus' => 'Keine Deko'] as $k => $l): ?>
                <option value="<?= $k ?>" <?= $cur === $k ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <p class="muted small">Vorschau: <a href="/?deko=herbst" target="_blank" rel="noopener">Herbst</a> · <a href="/?deko=winter" target="_blank" rel="noopener">Winter</a> · <a href="/?deko=fruehling" target="_blank" rel="noopener">Frühling</a> · <a href="/?deko=sommer" target="_blank" rel="noopener">Sommer</a></p>
            <button class="btn" type="submit">Speichern</button>
        </form>
    <?php });
}

if ($view === 'liste') {
    $subs = $pdo->query("SELECT * FROM subscribers ORDER BY status = 'bestaetigt' DESC, created_at DESC")->fetchAll();
    $confirmed = count(array_filter($subs, fn($s) => $s['status'] === 'bestaetigt'));
    render_page('E-Mail-Liste', function () use ($self, $flash, $subs, $confirmed) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>E-Mail-Liste</h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="card stack">
            <p><strong><?= $confirmed ?></strong> bestätigte <?= $confirmed === 1 ? 'Kontakt' : 'Kontakte' ?></p>
            <p class="muted small">Hier stehen alle, die sich über das Pop-up eingetragen und ihre Adresse per Klick bestätigt haben. <?= forma_mailerlite() ? 'Bestätigte Kontakte werden automatisch an MailerLite (Gruppe „Website“) übertragen.' : 'Für einen Newsletter lädst du die Liste herunter und importierst sie in MailerLite, oder du verbindest MailerLite unter Einstellungen, dann geht das automatisch.' ?></p>
            <p><a class="btn" href="<?= forma_h($self) ?>?view=export">Liste herunterladen (CSV)</a></p>
        </div>
        <div class="list" style="margin-top:16px">
        <?php foreach ($subs as $s): ?>
            <div class="card booking<?= $s['status'] === 'bestaetigt' ? '' : ' is-cancelled' ?>">
                <div class="booking-head"><span class="sel"><?= forma_h($s['email']) ?></span>
                    <span class="pill"><?= ['bestaetigt' => 'bestätigt', 'offen' => 'noch nicht bestätigt', 'abgemeldet' => 'abgemeldet'][$s['status']] ?? forma_h($s['status']) ?></span></div>
                <?php if ($s['name'] || $s['source']): ?><div class="muted small"><?= forma_h(trim($s['name'] . ($s['source'] ? ' · über „' . $s['source'] . '“' : ''), ' ·')) ?></div><?php endif; ?>
                <?php if ($s['status'] === 'offen'):
                    $ct = strtotime((string)$s['created_at']);
                    if (!empty($s['reminded_at'])) $rm = 'Eingetragen am ' . date('d. m. Y, H:i', $ct) . ' · Erinnerung verschickt am ' . date('d. m. Y, H:i', strtotime((string)$s['reminded_at']));
                    elseif ($ct > time() - 12 * 3600) $rm = 'Eingetragen am ' . date('d. m. Y, H:i', $ct) . ' · Erinnerung geht automatisch ca. ' . date('d. m., H:i', $ct + 12 * 3600) . ' Uhr raus';
                    elseif ($ct < time() - 7 * 86400) $rm = 'Eingetragen am ' . date('d. m. Y', $ct) . ' · keine automatische Erinnerung mehr (älter als 7 Tage)';
                    else $rm = 'Eingetragen am ' . date('d. m. Y, H:i', $ct) . ' · Erinnerung geht in Kürze raus'; ?>
                    <div class="muted small"><?= forma_h($rm) ?></div>
                    <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="resend_confirm"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="link" type="submit">Bestätigungs-Mail jetzt nochmal schicken</button></form>
                <?php endif; ?>
                <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="delete_subscriber"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="link" type="submit">Löschen</button></form>
            </div>
        <?php endforeach; ?>
        </div>
    <?php });
}

if ($view === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $c = ['id' => 0, 'title' => '', 'date_text' => '', 'start_date' => date('Y-m-d'), 'capacity' => 6, 'min_participants' => 1, 'price_text' => '', 'description' => '', 'visible' => 1, 'price_cents' => 0];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        $c = $stmt->fetch() ?: $c;
    }
    render_page($id ? 'Termin bearbeiten' : 'Neuer Termin', function () use ($c, $self, $flash) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1><?= $c['id'] ? 'Termin bearbeiten' : 'Neuer Termin' ?></h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
            <input type="hidden" name="action" value="save_course">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <label for="title">Name des Kurses</label>
            <input id="title" name="title" value="<?= forma_h($c['title']) ?>" placeholder="z. B. Spin &amp; Go" required>
            <label for="date_text">Termin, wie er auf der Website steht</label>
            <input id="date_text" name="date_text" value="<?= forma_h($c['date_text']) ?>" placeholder="z. B. Mittwoch, 18. 11. 2026, 9–11 Uhr" required>
            <label for="start_date">Erster Kurstag <span class="muted">(danach verschwindet der Termin automatisch von der Website)</span></label>
            <input id="start_date" name="start_date" type="date" value="<?= forma_h($c['start_date']) ?>" required>
            <div class="row">
                <div class="stack"><label for="capacity">Plätze insgesamt</label><input id="capacity" name="capacity" type="number" min="0" value="<?= (int)$c['capacity'] ?>"></div>
                <div class="stack"><label for="min_participants">Findet statt ab</label><input id="min_participants" name="min_participants" type="number" min="1" value="<?= (int)$c['min_participants'] ?>"></div>
            </div>
            <label for="price_text">Preis</label>
            <input id="price_text" name="price_text" value="<?= forma_h($c['price_text']) ?>" placeholder="z. B. € 70 pro Person, inkl. eines Werkstücks">
            <label for="price_online">Online bezahlen: Betrag pro Person in € <span class="muted">(leer lassen = Bezahlung vor Ort)</span></label>
            <input id="price_online" name="price_online" inputmode="decimal" value="<?= (int)$c['price_cents'] > 0 ? forma_h(rtrim(rtrim(number_format($c['price_cents'] / 100, 2, ',', ''), '0'), ',')) : '' ?>" placeholder="z. B. 70">
            <label for="description">Kurze Beschreibung (optional)</label>
            <textarea id="description" name="description" rows="3"><?= forma_h($c['description']) ?></textarea>
            <label class="check"><input type="checkbox" name="visible" value="1" <?= $c['visible'] ? 'checked' : '' ?>> Auf der Website anzeigen</label>
            <button class="btn" type="submit">Speichern</button>
        </form>
        <?php if ($c['id']): ?>
        <form method="post" class="card stack danger">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
            <input type="hidden" name="action" value="delete_course">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <label class="check"><input type="checkbox" name="confirm" value="1"> Ja, diesen Termin mit allen Anmeldungen endgültig löschen</label>
            <button class="btn btn-danger" type="submit">Termin löschen</button>
        </form>
        <?php endif; ?>
    <?php });
}

if ($view === 'course') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) {
        go($self, 'Diesen Termin gibt es nicht mehr.');
    }
    forma_reconcile_payments($pdo, true);
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE course_id = ? AND status != 'abgebrochen' ORDER BY status, created_at");
    $stmt->execute([$id]);
    $bookings = $stmt->fetchAll();
    $booked = forma_booked($pdo, $id);
    render_page($c['title'], function () use ($c, $bookings, $booked, $self, $flash) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1><?= forma_h($c['title']) ?></h1>
        <p class="muted"><?= forma_h($c['date_text']) ?> · <?= $booked ?> von <?= (int)$c['capacity'] ?> Plätzen belegt<?= $c['visible'] ? '' : ' · <strong>auf der Website ausgeblendet</strong>' ?></p>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <p><a class="btn btn-ghost" href="<?= forma_h($self) ?>?view=edit&amp;id=<?= (int)$c['id'] ?>">Termin bearbeiten</a></p>
        <?php $ms = (string)($c['min_status'] ?? ''); if ((int)$c['min_participants'] > 1 && $booked > 0 && $booked < (int)$c['min_participants'] && in_array($ms, ['', 'gemeldet', 'gast_informiert'], true) && $c['start_date'] >= date('Y-m-d')): ?>
        <div class="card notice minbox">
            <span class="ic"><?= admin_icon('kalender') ?></span>
            <div class="stack">
            <?php if ($ms === 'gast_informiert'): ?>
                <strong>Die Gäste sind informiert.</strong>
                <span class="muted small">Sie wählen gerade selbst: anderer Termin oder stornieren (Geld zurück + <?= FORMA_MIN_GIFT_PERCENT ?>-%-Gutschein). Was sie gewählt haben, siehst du unten bei der Anmeldung und per Mail.</span>
            <?php else: ?>
                <strong>Nur <?= $booked ?> von mindestens <?= (int)$c['min_participants'] ?> Personen angemeldet.</strong>
                <span class="muted small">Du entscheidest. Hat sich noch jemand telefonisch angemeldet? Dann trag die Person unten ein oder klick einfach auf „Findet statt“.</span>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
                    <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="min_decide"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="to" value="statt"><button class="btn" type="submit">Kurs findet statt</button></form>
                    <form method="post" onsubmit="return confirm('Gäste jetzt informieren? Sie bekommen eine Mail und können selbst umbuchen oder stornieren.')"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="min_decide"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="to" value="info"><button class="btn btn-ghost" type="submit">Gäste informieren</button></form>
                </div>
            <?php endif; ?>
            </div>
        </div>
        <?php elseif ($ms === 'findet_statt'): ?><p class="muted small">✓ Du hast entschieden: Der Kurs findet statt.</p><?php endif; ?>
        <h2>Anmeldungen</h2>
        <?php if (!$bookings): ?><p class="muted">Noch keine Anmeldungen.</p><?php endif; ?>
        <div class="list">
        <?php foreach ($bookings as $b): $cancel = $b['status'] === 'storniert'; $open = $b['status'] === 'offen'; ?>
            <div class="card booking<?= $cancel ? ' is-cancelled' : '' ?>">
                <div class="booking-head">
                    <strong><?= forma_h($b['name']) ?></strong>
                    <span class="pill"><?= (int)$b['persons'] ?> <?= (int)$b['persons'] === 1 ? 'Person' : 'Personen' ?><?= $cancel ? ' · storniert' : '' ?><?= $open ? ' · Zahlung läuft' : '' ?><?= (!$open && $b['paid_at']) ? ' · ' . forma_h(forma_euro((int)$b['amount_cents'])) . ' bezahlt' : '' ?></span>
                </div>
                <?php if ($b['email']): ?><div class="sel"><?= forma_h($b['email']) ?></div><?php endif; ?>
                <?php if ($b['phone']): ?><div class="sel"><?= forma_h($b['phone']) ?></div><?php endif; ?>
                <?php if ($b['message']): ?><div class="muted">„<?= forma_h($b['message']) ?>“</div><?php endif; ?>
                <div class="muted small">Angemeldet am <?= forma_h(date('d. m. Y, H:i', strtotime($b['created_at']))) ?></div>
                <?php if (($b['min_state'] ?? '') === 'gefragt'): ?><div class="small" style="color:var(--clay)">Informiert, Antwort steht noch aus</div><?php elseif (($b['min_state'] ?? '') === 'storniert'): ?><div class="small" style="color:var(--clay)">Hat wegen zu weniger Anmeldungen storniert<?= (int)($b['refund_cents'] ?? 0) > 0 ? ' · ' . forma_h(forma_euro((int)$b['refund_cents'])) . ' automatisch erstattet' : '' ?></div><?php endif; ?>
                <?php if ((int)($b['voucher_cents'] ?? 0) > 0): ?><div class="muted small"><?= forma_h(forma_euro((int)$b['voucher_cents'])) ?> mit Gutschein bezahlt</div><?php endif; ?>
                <?php if (!empty($b['no_post'])): ?><div class="muted small">Möchte keine FORMA Post</div><?php endif; ?>
                <?php if ($cancel && $b['paid_at'] && (int)($b['refund_cents'] ?? 0) === 0): ?><div class="muted small">Online bezahlt. Die Rückerstattung machst du im Stripe-Dashboard unter Zahlungen.</div><?php endif; ?>
                <?php if (!$open): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                    <input type="hidden" name="action" value="booking_status">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <input type="hidden" name="status" value="<?= $cancel ? 'bestaetigt' : 'storniert' ?>">
                    <button class="link" type="submit"><?= $cancel ? 'Wieder aktivieren' : 'Stornieren (Platz wird frei)' ?></button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <h2>Person selbst eintragen</h2>
        <p class="muted">Zum Beispiel, wenn sich jemand per Telefon oder WhatsApp anmeldet. Es wird keine E-Mail verschickt.</p>
        <form method="post" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
            <input type="hidden" name="action" value="add_booking">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <label for="bn">Name</label><input id="bn" name="name" required>
            <div class="row">
                <div class="stack"><label for="bp">Telefon</label><input id="bp" name="phone"></div>
                <div class="stack"><label for="bpers">Personen</label><input id="bpers" name="persons" type="number" min="1" value="1"></div>
            </div>
            <label for="be">E-Mail (optional)</label><input id="be" name="email" type="email">
            <button class="btn" type="submit">Eintragen</button>
        </form>
    <?php });
}

// Übersicht
forma_reconcile_payments($pdo);
$upcoming = forma_upcoming_courses($pdo, false);
$groups = [];
foreach ($upcoming as $c) {
    $groups[$c['title']][] = $c;
}
$stmt = $pdo->prepare('SELECT * FROM courses WHERE start_date < ? ORDER BY start_date DESC LIMIT 20');
$stmt->execute([date('Y-m-d')]);
$past = $stmt->fetchAll();
$subCount = (int)$pdo->query("SELECT COUNT(*) FROM subscribers WHERE status = 'bestaetigt'")->fetchColumn();
$popupLive = forma_popup_live(forma_popup($pdo));
$voucherOpen = (int)$pdo->query("SELECT COUNT(*) FROM vouchers WHERE status = 'bezahlt'")->fetchColumn();
$privateOpen = (int)$pdo->query("SELECT COUNT(*) FROM private_bookings WHERE status IN ('bezahlt','anfrage') AND done = 0")->fetchColumn();
// Agenda für die nächsten 7 Tage: Kurse mit Anmeldungen und Private-Pottery-Termine
$agenda = [];
$st = $pdo->prepare('SELECT * FROM courses WHERE start_date BETWEEN ? AND ? ORDER BY start_date, id');
$st->execute([date('Y-m-d'), date('Y-m-d', strtotime('+6 days'))]);
foreach ($st->fetchAll() as $c) {
    $bk = forma_booked($pdo, (int)$c['id']);
    $time = preg_match('/(\d{1,2}(?::\d{2})?)\s*[–-]\s*\d{1,2}(?::\d{2})?\s*Uhr/u', (string)$c['date_text'], $m) ? $m[1] : '';
    $agenda[$c['start_date']][] = ['sort' => sprintf('%05s', strlen($time) <= 2 ? $time . ':00' : $time), 'time' => $time !== '' ? forma_de_time($time) : '', 'title' => preg_replace('/\s*·.*$/u', '', (string)$c['title']), 'sub' => $bk . ' / ' . $c['capacity'] . ' angemeldet', 'href' => '?view=course&id=' . $c['id'], 'kind' => $bk > 0 ? 'kurs' : 'kurs leer'];
}
$st = $pdo->prepare('SELECT s.*, p.name FROM private_sessions s JOIN private_bookings p ON p.id = s.private_id WHERE s.date BETWEEN ? AND ?');
$st->execute([date('Y-m-d'), date('Y-m-d', strtotime('+6 days'))]);
foreach ($st->fetchAll() as $x) {
    $agenda[$x['date']][] = ['sort' => sprintf('%05s', $x['start']), 'time' => forma_de_time((string)$x['start']), 'title' => 'Private Pottery · ' . $x['name'], 'sub' => forma_hours_text((float)$x['hours']) . ($x['mailed_at'] ? '' : ' · noch nicht bestätigt'), 'href' => '?view=kalender&m=' . substr((string)$x['date'], 0, 7) . '&d=' . $x['date'] . '#tag', 'kind' => 'private'];
}
ksort($agenda);
foreach ($agenda as &$l) usort($l, fn($a, $b) => strcmp($a['sort'], $b['sort']));
unset($l);
$toPlan = 0;
foreach ($pdo->query("SELECT id, hours, status FROM private_bookings WHERE status IN ('bezahlt','anfrage') AND done = 0")->fetchAll() as $pb) {
    $pl = forma_private_planned($pdo, (int)$pb['id']);
    if ($pb['status'] === 'bezahlt' ? $pl + 0.01 < (float)$pb['hours'] : $pl == 0.0) $toPlan++;
}
$newBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'bestaetigt' AND created_at >= '" . date('c', strtotime('-7 days')) . "'")->fetchColumn();
try { forma_min_check($pdo); } catch (Throwable $e) { error_log('FORMA min: ' . $e->getMessage()); }
$minCourses = $pdo->query("SELECT * FROM courses WHERE min_status = 'gemeldet' AND start_date >= '" . date('Y-m-d') . "' ORDER BY start_date")->fetchAll();
render_page('Kursverwaltung', function () use ($groups, $past, $self, $flash, $pdo, $subCount, $popupLive, $voucherOpen, $privateOpen, $agenda, $toPlan, $newBookings, $minCourses) {
    $h = (int)date('G');
    $hello = $h < 11 ? 'Guten Morgen' : ($h < 17 ? 'Hallo' : 'Guten Abend'); ?>
    <section class="hero">
        <span class="deco-slot d1"></span><span class="deco-slot d2"></span><span class="deco-slot d3"></span><span class="deco-slot d4"></span>
        <div class="hero-text">
            <p class="eyebrow"><?= forma_h(forma_de_date(date('Y-m-d'))) ?></p>
            <h1><?= $hello ?>, Vivien!</h1>
            <p class="muted">Schön, dass du da bist. Hier ist alles, was in deinem Atelier gerade los ist.</p>
        </div>
        <div class="stats">
            <a class="stat" href="<?= forma_h($self) ?>?view=kalender"><b><?= $toPlan ?></b><span>Private Pottery zum Einplanen</span></a>
            <a class="stat" href="#termine"><b><?= $newBookings ?></b><span>neue Buchungen in 7 Tagen</span></a>
            <a class="stat" href="<?= forma_h($self) ?>?view=liste"><b><?= $subCount ?></b><span>Kontakte im Newsletter</span></a>
        </div>
        <form method="post" class="logout"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="logout"><button class="link small" type="submit">Abmelden</button></form>
    </section>
    <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
    <?php if (!forma_stripe_key()): ?><p class="alert">Die Online-Bezahlung ist noch nicht eingerichtet. Bis dahin werden alle Kurse ohne Bezahlung reserviert. <a href="<?= forma_h($self) ?>?view=einstellungen">Jetzt Stripe verbinden</a></p><?php endif; ?>
    <?php foreach ($minCourses as $mc): ?><a class="card notice" href="<?= forma_h($self) ?>?view=course&amp;id=<?= (int)$mc['id'] ?>"><span class="ic"><?= admin_icon('kalender') ?></span><span><strong>Zu wenige Anmeldungen: <?= forma_h($mc['title']) ?>, <?= forma_h(preg_replace('/\s*·.*$/u', '', (string)$mc['date_text'])) ?></strong><br><span class="muted small">Entscheide: findet statt oder Gäste informieren →</span></span></a><?php endforeach; ?>
    <?php if ($toPlan > 0): ?><a class="card notice" href="<?= forma_h($self) ?>?view=kalender"><span class="ic"><?= admin_icon('private') ?></span><span><strong><?= $toPlan === 1 ? '1 Private-Pottery-Buchung wartet' : $toPlan . ' Private-Pottery-Buchungen warten' ?> auf ihren Termin.</strong><br><span class="muted small">Im Kalender einplanen und Bestätigung schicken →</span></span></a><?php endif; ?>
    <div class="dash">
        <section class="agenda card">
            <h2>Die nächsten 7 Tage</h2>
            <?php if (!$agenda): ?><p class="muted">Keine Termine in den nächsten 7 Tagen.</p><?php endif; ?>
            <?php foreach ($agenda as $d => $items): ?>
                <div class="ag-day"><div class="ag-date"><b><?= (int)date('j', strtotime($d)) ?></b><span><?= forma_h(mb_substr(FORMA_WEEKDAYS_LONG[(int)date('w', strtotime($d))], 0, 2)) ?></span><?php if ($d === date('Y-m-d')): ?><em>heute</em><?php endif; ?></div>
                <div class="ag-items"><?php foreach ($items as $it): ?><a class="ag-item <?= forma_h($it['kind']) ?>" href="<?= forma_h($self . $it['href']) ?>"><strong><?= forma_h($it['time']) ?></strong> <?= forma_h($it['title']) ?> <span class="muted small">· <?= forma_h($it['sub']) ?></span></a><?php endforeach; ?></div></div>
            <?php endforeach; ?>
            <a class="link small" href="<?= forma_h($self) ?>?view=kalender">Ganzen Kalender öffnen →</a>
        </section>
        <nav class="tiles">
            <a class="tile hl" href="<?= forma_h($self) ?>?view=kalender"><span class="ic"><?= admin_icon('kalender') ?></span><strong>Kalender</strong><span class="muted small">Kurse &amp; Private Pottery</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=private"><span class="ic"><?= admin_icon('private') ?></span><strong>Private Pottery</strong><span class="muted small"><?= $privateOpen ?> offen</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=edit"><span class="ic"><?= admin_icon('plus') ?></span><strong>Einzeltermin</strong><span class="muted small">z. B. ein Special-Kurs</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=series"><span class="ic"><?= admin_icon('serie') ?></span><strong>Serientermine</strong><span class="muted small">z. B. jeden Mi &amp; Fr</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=templates"><span class="ic"><?= admin_icon('template') ?></span><strong>Templates</strong><span class="muted small">&amp; Maker Club</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=gutscheine"><span class="ic"><?= admin_icon('gutschein') ?></span><strong>Gutscheine</strong><span class="muted small"><?= $voucherOpen ?> offen</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=rechnungen"><span class="ic"><?= admin_icon('rechnung') ?></span><strong>Rechnungen</strong><span class="muted small">automatisch nach Zahlung</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=liste"><span class="ic"><?= admin_icon('mail') ?></span><strong>E-Mail-Liste</strong><span class="muted small"><?= $subCount ?> <?= $subCount === 1 ? 'Kontakt' : 'Kontakte' ?></span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=popup"><span class="ic"><?= admin_icon('popup') ?></span><strong>Pop-up &amp; Deko</strong><span class="muted small"><?= $popupLive ? 'gerade sichtbar' : 'ausgeschaltet' ?> · Deko</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=tests"><span class="ic"><?= admin_icon('test') ?></span><strong>Testbuchungen</strong><span class="muted small">jedes Angebot ausprobieren</span></a>
            <a class="tile" href="<?= forma_h($self) ?>?view=einstellungen"><span class="ic"><?= admin_icon('settings') ?></span><strong>Einstellungen</strong><span class="muted small">Stripe, MailerLite, E-Mail</span></a>
        </nav>
    </div>
    <h2 id="termine">Alle kommenden Kurstermine</h2>
    <?php if (!$groups): ?><p class="muted">Gerade sind keine Termine eingetragen.</p><?php endif; ?>
    <?php foreach ($groups as $title => $list): $bookedSum = array_sum(array_column($list, 'booked')); ?>
    <details class="group" <?= count($list) <= 3 || $bookedSum > 0 ? 'open' : '' ?>>
        <summary><strong><?= forma_h($title) ?></strong> <span class="muted small"><?= count($list) ?> <?= count($list) === 1 ? 'Termin' : 'Termine' ?> · <?= $bookedSum ?> <?= $bookedSum === 1 ? 'Person' : 'Personen' ?> angemeldet</span></summary>
        <div class="list">
        <?php foreach ($list as $c): $full = $c['free'] <= 0; ?>
            <div class="card course">
                <a class="course-link" href="<?= forma_h($self) ?>?view=course&amp;id=<?= (int)$c['id'] ?>">
                    <span><?= forma_h($c['date_text']) ?></span>
                    <span class="pill<?= $full ? ' pill-full' : '' ?><?= $c['booked'] > 0 && !$full ? ' pill-ok' : '' ?>"><?= (int)$c['booked'] ?> / <?= (int)$c['capacity'] ?> belegt<?= $full ? ' · ausgebucht' : '' ?><?= (int)$c['price_cents'] > 0 ? ' · online ' . forma_h(forma_euro((int)$c['price_cents'])) : '' ?></span>
                </a>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                    <input type="hidden" name="action" value="toggle_visible">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="link" type="submit"><?= $c['visible'] ? 'Sichtbar · ausblenden' : 'Ausgeblendet · anzeigen' ?></button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    </details>
    <?php endforeach; ?>
    <?php if ($past): ?>
    <details class="past">
        <summary>Vergangene Termine</summary>
        <div class="list">
        <?php foreach ($past as $c): ?>
            <a class="card course-link" href="<?= forma_h($self) ?>?view=course&amp;id=<?= (int)$c['id'] ?>">
                <strong><?= forma_h($c['title']) ?></strong>
                <span class="muted"><?= forma_h($c['date_text']) ?> · <?= forma_booked($pdo, (int)$c['id']) ?> Teilnehmende</span>
            </a>
        <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
<?php });

function admin_icon(string $k): string
{
    $p = [
        'kalender' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/><circle cx="8.5" cy="14.5" r="1.2" fill="currentColor"/><circle cx="12" cy="14.5" r="1.2" fill="currentColor"/>',
        'private' => '<path d="M6 10c0-3 2.7-5 6-5s6 2 6 5c0 2-1 3-1 5v1H7v-1c0-2-1-3-1-5z"/><path d="M7 19h10M9 10c.5 1 1.6 1.5 3 1.5s2.5-.5 3-1.5"/>',
        'plus' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'serie' => '<path d="M4 12a8 8 0 0114-5.3M20 12a8 8 0 01-14 5.3"/><path d="M18 3v4h-4M6 21v-4h4"/>',
        'template' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h4"/>',
        'gutschein' => '<rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M12 8c-1-3-5-4-5-1.5S10 8 12 8zm0 0c1-3 5-4 5-1.5S14 8 12 8z"/>',
        'rechnung' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M4 7l8 6 8-6"/>',
        'popup' => '<path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4z"/><path d="M18 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8zM6 16l.6 1.4L8 18l-1.4.6L6 20l-.6-1.4L4 18l1.4-.6z"/>',
        'test' => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 001.8 3h10.4a2 2 0 001.8-3l-5-9V3"/><path d="M7.5 14h9"/>',
        'team' => '<circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><path d="M2.5 20c.5-3.5 2.8-5.5 5.5-5.5s5 2 5.5 5.5M10.5 20c.5-3.5 2.8-5.5 5.5-5.5s5 2 5.5 5.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
    ][$k] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function render_page(string $title, callable $body): void
{ ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= forma_h($title) ?> · FORMA</title>
<link rel="icon" type="image/png" href="../img/forma-logo.png">
<style>
@font-face{font-family:"Outfit";font-weight:300 500;font-display:swap;src:url(../fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:600 800;font-display:swap;src:url(../fonts/Outfit-Bold.woff) format("woff")}
@font-face{font-family:"Bricolage Grotesque";font-weight:600 800;font-display:swap;src:url(../fonts/BricolageGrotesque-Bold.woff) format("woff")}
:root{--paper:#EEE2D4;--card:#F8F1EA;--ink:#33211B;--soft:#674B40;--clay:#8C2C1D;--line:#D9C5B2;color-scheme:light}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:18.5px/1.55 "Outfit",system-ui,sans-serif}
main{max-width:720px;margin:0 auto;padding:24px 16px 64px}
h1,h2{font-family:"Bricolage Grotesque","Outfit",sans-serif;color:var(--clay);letter-spacing:-.01em;line-height:1.15}
h1{font-size:30px;margin:8px 0 12px}h2{font-size:21px;margin:32px 0 12px}
a{color:var(--clay)}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--ink);font-size:13px;letter-spacing:.14em;text-transform:uppercase}
.brand img{width:44px;height:44px;object-fit:contain}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:16px 18px}
.stack{display:flex;flex-direction:column;gap:8px;min-width:0}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.list{display:flex;flex-direction:column;gap:12px}
label{font-weight:500;font-size:15px}
input,textarea{font:inherit;padding:11px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--ink);width:100%}
input:focus,textarea:focus{outline:3px solid #B05A38;outline-offset:1px}
.check{display:flex;gap:10px;align-items:center;font-weight:400}
.check input{width:auto}
.btn{display:inline-block;background:var(--clay);color:#FBF1EA;border:1.5px solid var(--clay);border-radius:999px;padding:11px 22px;font:inherit;font-weight:500;text-decoration:none;cursor:pointer;min-height:46px}
.btn-ghost{background:transparent;color:var(--clay)}
.btn-danger{background:#fff;color:#9b1c1c;border-color:#9b1c1c}
.danger{margin-top:24px;border-color:#e3b6ae}
.link{background:none;border:0;padding:6px 0;font:inherit;color:var(--clay);text-decoration:underline;cursor:pointer}
.muted{color:var(--soft)}.small{font-size:14px}.center{text-align:center;margin-top:12px}
.alert{background:#fff;border:1px solid #B05A38;border-radius:12px;padding:12px 14px}
.pill{display:inline-block;background:#EAD8C6;border-radius:999px;padding:2px 10px;font-size:14px;width:max-content}
.pill-full{background:var(--clay);color:#FBF1EA}
.course{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
.course-link{display:flex;flex-direction:column;gap:4px;text-decoration:none;color:var(--ink);min-width:0;flex:1 1 260px}
.booking{display:flex;flex-direction:column;gap:4px}
.tgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin:18px 0}
.ttile{display:flex;flex-direction:column;gap:6px}.ttile .ic{width:40px;height:40px;color:var(--clay)}.ttile .ic svg{width:100%;height:100%}.ttile input{accent-color:var(--clay)}.ttile .btn{margin-top:auto;padding:9px 14px;font-size:16px}
.tres{margin:16px 0}.tsteps{list-style:none;padding:0;margin:0 0 14px;display:flex;flex-direction:column;gap:6px}
.tsteps li{display:flex;gap:10px;align-items:flex-start;font-size:16px}.tsteps li span{flex:none;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;background:#5E7B4E}
.tsteps .ts-fail span{background:#9b1c1c}.tsteps .ts-info span{background:#B8956F}
.tmail{border-top:1px solid var(--line);padding:8px 0}.tmail summary{cursor:pointer;font-weight:500}.tmail iframe{width:100%;height:520px;border:1px solid var(--line);border-radius:12px;background:#fff;margin-top:8px}
.booking-head{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
.is-cancelled{opacity:.6}
.sel{user-select:all;overflow-wrap:anywhere}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px}
.tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:8px 0 4px}
.tile{display:flex;flex-direction:column;gap:2px;padding:14px 16px;border-radius:16px;background:var(--card);border:1px solid var(--line);text-decoration:none;color:var(--ink)}
.tile:hover{border-color:var(--clay)}
.group{margin-bottom:12px}.group summary{cursor:pointer;padding:8px 0;list-style-position:outside}
.group .list{margin-top:6px;gap:8px}.group .card{padding:10px 14px}
.pill-ok{background:#DCE3C8}
select{font:inherit;padding:11px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--ink);width:100%}
.days{border:0;padding:0;margin:4px 0;display:flex;flex-wrap:wrap;gap:6px 18px}.days legend{font-weight:500;font-size:15px;margin-bottom:6px;padding:0}
.steps{margin:0;padding-left:20px;display:grid;gap:4px}
.thumb{max-width:220px;border-radius:12px}
.past{margin-top:28px}.past summary{cursor:pointer;font-weight:500;margin-bottom:12px}
@media (max-width:480px){.row{grid-template-columns:1fr}}
/* Kopf & Saison-Icons */
.head{display:flex;justify-content:space-between;align-items:center;gap:12px}
.head-deco{display:flex;gap:6px}
.deco-slot{display:inline-block;width:34px;height:34px;transform:rotate(var(--r,0deg));animation:sway 7s ease-in-out infinite alternate}
.deco-slot svg{width:100%;height:100%;display:block;filter:drop-shadow(0 3px 6px rgba(103,48,30,.14))}
@keyframes sway{from{transform:rotate(calc(var(--r,0deg) - 4deg)) translateY(0)}to{transform:rotate(calc(var(--r,0deg) + 4deg)) translateY(-3px)}}
@media (prefers-reduced-motion:reduce){.deco-slot{animation:none}}
.no-deco .deco-slot{display:none}
.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:13px;color:var(--soft);margin:0}
/* Startseite */
.hero{position:relative;background:linear-gradient(135deg,#F8F1EA,#F1E3D3);border:1px solid var(--line);border-radius:28px;padding:22px 20px 18px;margin:14px 0 16px;overflow:hidden}
.hero h1{margin:4px 0 6px}
.hero-text{max-width:620px;position:relative;z-index:1}
.hero .deco-slot{position:absolute;width:44px;height:44px;opacity:.95}
.hero .d1{right:16px;top:14px}.hero .d2{right:70px;top:54px;width:34px;height:34px}.hero .d3{right:22px;top:96px;width:38px;height:38px}.hero .d4{display:none}
.hero-text{padding-right:110px}
.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px;position:relative;z-index:1}
.stat{display:flex;flex-direction:column;background:#FFFAF5;border:1px solid var(--line);border-radius:18px;padding:12px 14px;text-decoration:none;color:var(--ink)}
.stat b{font-family:"Bricolage Grotesque","Outfit",sans-serif;font-size:30px;color:var(--clay);line-height:1.1}
.stat span{font-size:14px;color:var(--soft);line-height:1.3}
.stat:hover,.tile:hover{border-color:var(--clay)}
.logout{position:absolute;right:18px;bottom:10px;z-index:1}
.notice{display:flex;gap:14px;align-items:center;text-decoration:none;color:var(--ink);border-color:#B05A38;background:#FFF7F0;margin-bottom:16px}
.ic{display:inline-flex;width:40px;height:40px;flex:0 0 40px;border-radius:12px;background:#F1E3D3;color:var(--clay);align-items:center;justify-content:center}
.ic svg{width:24px;height:24px}
.dash{display:grid;gap:16px}
.tiles{margin:0}
.tile{gap:4px}.tile .ic{margin-bottom:6px}
.tile.hl{background:#F6E6DA;border-color:#C98F72}
.agenda h2{margin-top:0}
.ag-day{display:flex;gap:14px;padding:10px 0;border-top:1px solid var(--line)}
.ag-date{flex:0 0 46px;display:flex;flex-direction:column;align-items:center;line-height:1.1}
.ag-date b{font-family:"Bricolage Grotesque","Outfit",sans-serif;font-size:26px;color:var(--clay)}
.ag-date span{font-size:13px;color:var(--soft);text-transform:uppercase;letter-spacing:.08em}
.ag-date em{font-style:normal;font-size:11px;background:var(--clay);color:#FBF1EA;border-radius:999px;padding:1px 7px;margin-top:3px}
.ag-items{display:flex;flex-direction:column;gap:6px;min-width:0}
.ag-item{text-decoration:none;color:var(--ink);padding:6px 10px;border-radius:10px;background:#F3E7DA;border-left:4px solid #C9A88E}
.ag-item.private{background:#F6E2D9;border-left-color:var(--clay)}
.ag-item.leer{opacity:.65}
/* Kalender */
.cal-wrap{display:grid;gap:18px}
.cal-nav{display:flex;align-items:center;gap:10px;margin:6px 0 10px}
.cal-nav h2{margin:0;min-width:180px;text-align:center}
.cal-nav .btn{min-height:40px;padding:4px 16px;font-size:22px;line-height:1}
.cal{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px}
.cal-wd{text-align:center;font-size:13px;color:var(--soft);text-transform:uppercase;letter-spacing:.08em;padding:4px 0}
.cal-day{min-height:74px;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:5px;display:flex;flex-direction:column;gap:3px;text-decoration:none;color:var(--ink);min-width:0;overflow:hidden}
.cal-day.empty{background:transparent;border:0}
.cal-day.past{opacity:.55}
.cal-day.today .num{background:var(--clay);color:#FBF1EA;border-radius:999px;padding:0 7px}
.cal-day.sel{outline:3px solid var(--clay);outline-offset:-1px}
.cal-day.wish{background:#FFF3EA;border:2px dashed #B05A38}
.cal-day:hover{border-color:var(--clay)}
.num{font-weight:600;font-size:14px;align-self:flex-start}
.ev{display:block;font-size:11.5px;line-height:1.25;padding:2px 5px;border-radius:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#EAD8C6}
.ev-private{background:var(--clay);color:#FBF1EA}
.ev-private.pending{background:#fff;color:var(--clay);border:1.5px dashed var(--clay)}
.ev-wish{background:#fff;border:1.5px dashed #B05A38;color:#8a3f24}
.ev.more{background:none;color:var(--soft)}
.legend{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.legend .ev{display:inline-block;font-size:13px}
.wish-dot{display:inline-block;width:10px;height:10px;border-radius:50%;border:2px dashed #B05A38;vertical-align:middle}
.day-panel{margin-top:16px;scroll-margin-top:16px}
.day-panel h2{margin-top:0}
.day-list{display:flex!important;flex-direction:column;gap:8px}
.day-item{display:block;padding:10px 12px;border-radius:12px;background:#F3E7DA;border-left:4px solid #C9A88E;text-decoration:none;color:var(--ink)}
.day-item.private{background:#F6E2D9;border-left-color:var(--clay)}
.day-item.wunsch{background:#fff;border:1.5px dashed #B05A38}
.row-actions{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;margin-top:4px}
.del summary{list-style:none;cursor:pointer}.del summary::-webkit-details-marker{display:none}
.del form{margin-top:8px}
.assign-form,.assign-pick{margin-top:16px;padding-top:14px;border-top:1px solid var(--line)}
.assign-form h3{margin:0;font-size:20px;color:var(--clay)}
.oldv{margin-top:16px;padding-top:14px;border-top:1px solid var(--line)}
.oldv summary{cursor:pointer;font-weight:600;color:var(--clay);font-size:17px;list-style-position:inside}
.oldv-form{margin-top:12px}
.links-card{margin-top:16px}.links-card h2{margin:0}
.link-row{display:flex;gap:8px;align-items:center}.link-row .link-url{flex:1;min-width:0;font-size:14px}
.link-item .row-actions{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
.link-item.is-new{outline:2px solid var(--clay)}
.oldv-form [hidden]{display:none!important}
.oldv-form p{margin:0}
.assign{border:2px solid var(--clay);display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.confirm-form{margin-top:6px;padding:12px;border-radius:14px;background:#FFF7F0}
.sess{margin:4px 0;padding-left:18px;display:grid;gap:4px}
.pill-wait{background:#fff;border:1.5px dashed var(--clay);color:var(--clay)}
.btn-sm{min-height:38px;padding:6px 14px;font-size:15px;width:max-content;margin-top:4px}
.side-list{display:flex!important;flex-direction:column;gap:8px}
.side-item{display:flex;flex-direction:column;gap:4px;text-decoration:none;color:var(--ink);padding:12px 14px}
.side-item.active{border:2px solid var(--clay)}
.cal-side h2{margin-top:0}
@media (max-width:640px){
  .stats{grid-template-columns:1fr 1fr 1fr;gap:6px}.stat{padding:10px}.stat b{font-size:24px}.stat span{font-size:12px}
  .hero-text{padding-right:56px}.hero .d2{display:none}.hero .d3{top:66px}
  .cal{gap:2px}.cal-day{min-height:54px;padding:3px;border-radius:8px}
  .ev{font-size:0;height:6px;padding:0;border-radius:3px}
  .legend .ev{font-size:12px;height:auto;padding:2px 6px}
  .cal-nav h2{min-width:0;font-size:20px}
  .logout{position:static;margin-top:10px}
}
/* Desktop: ganze Bildschirmbreite, größere Schrift */
@media (min-width:900px){
  body{font-size:21.5px}
  main{max-width:1500px;padding:32px 48px 80px}
  h1{font-size:46px}h2{font-size:30px;margin-top:44px}
  .brand{font-size:16px}.brand img{width:68px;height:68px}
  .btn{font-size:20px;min-height:52px;padding:12px 26px}
  .link{font-size:19px}
  .alert{font-size:20px}
  .muted{font-size:20px}
  .tiles{grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
  .tile{padding:22px 24px}.tile strong{font-size:23px}
  .group .list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
  .group .card{padding:14px 18px}
  .group summary{font-size:24px}
  .list{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px}
  form.card,div.card.stack{max-width:none}
  .card{padding:20px 24px}
  label{font-size:19px}
  input,textarea,select{font-size:20px;padding:13px 15px}
  .small{font-size:17.5px}
  .pill{font-size:17px;padding:3px 12px}
  .hero{padding:30px 34px 24px}
  .hero .deco-slot{width:60px;height:60px}.hero .d1{right:40px;top:22px}.hero .d2{right:130px;top:70px;width:46px;height:46px}.hero .d3{right:46px;top:120px;width:50px;height:50px}.hero .d4{display:block;right:230px;top:24px;width:40px;height:40px}
  .hero-text{padding-right:280px;max-width:none}
  .stats{max-width:900px}.stat b{font-size:38px}.stat span{font-size:16px}
  .deco-slot{width:44px;height:44px}
  .dash{grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);align-items:start}
  .dash .tiles{grid-template-columns:repeat(2,minmax(0,1fr))}
  .cal-wrap{grid-template-columns:minmax(0,1fr) 360px;align-items:start}
  .cal-day{min-height:118px;padding:8px}
  .ev{font-size:14px;padding:3px 7px}
  .num{font-size:17px}
  .ic{width:48px;height:48px;flex-basis:48px}.ic svg{width:28px;height:28px}
}
@media (min-width:1300px){.dash .tiles{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (min-width:1200px){
  form.card.stack{display:grid;grid-template-columns:minmax(0,1fr);max-width:900px}
}
</style>
</head>
<body>
<main>
<header class="head"><a class="brand" href="<?= forma_h(strtok($_SERVER['REQUEST_URI'] ?? '/verwaltung/', '?') ?: '/verwaltung/') ?>"><img src="../img/forma-logo.png" alt=""><span>FORMA · Atelier-Verwaltung</span></a><span class="head-deco"><span class="deco-slot"></span><span class="deco-slot"></span><span class="deco-slot"></span></span></header>
<?php $body(); ?>
</main>
<script src="deko.js?v=1" defer></script>
</body>
</html>
<?php exit;
}

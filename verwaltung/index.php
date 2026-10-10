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
        $img = store_upload('maker_image', 'image', 'maker');
    } catch (Throwable $e) {
        go($self . '?view=templates', $e->getMessage());
    }
    if ($pdf !== '') { if ($m['file']) @unlink(forma_template_dir() . '/' . basename($m['file'])); $m['file'] = $pdf; }
    if ($img !== '') { if ($m['image']) @unlink(forma_template_dir() . '/' . basename($m['image'])); $m['image'] = $img; }
    $m['title'] = forma_clean((string)($_POST['maker_title'] ?? ''), 90);
    $m['text'] = forma_clean((string)($_POST['maker_text'] ?? ''), 400);
    $m['version'] = (int)$m['version'] + 1;
    forma_set_setting($pdo, 'maker', json_encode($m, JSON_UNESCAPED_UNICODE));
    go($self . '?view=templates', 'Das Template des Monats ist gespeichert' . ($m['file'] ? ' und auf der Website verfügbar.' : '. Lade noch die PDF hoch, damit man es herunterladen kann.'));
}

if ($action === 'save_template') {
    $id = (int)($_POST['tid'] ?? 0);
    $title = forma_clean((string)($_POST['title'] ?? ''), 90);
    if ($title === '') {
        go($self . '?view=template_edit' . ($id ? '&id=' . $id : ''), 'Bitte gib dem Template einen Namen.');
    }
    try {
        $pdf = store_upload('pdf', 'pdf', 'tpl');
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
        $pdo->prepare('UPDATE templates SET title=?, description=?, season=?, age=?, level=?, price_cents=?, visible=?, sort=?' . ($pdf ? ', file=?' : '') . ($img ? ', image=?' : '') . ' WHERE id=?')
            ->execute(array_merge($vals, $pdf ? [$pdf] : [], $img ? [$img] : [], [$id]));
    } else {
        $pdo->prepare('INSERT INTO templates (title, description, season, age, level, price_cents, visible, sort, file, image, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array_merge($vals, [$pdf, $img, date('c')]));
    }
    go($self . '?view=templates', 'Das Template ist gespeichert.');
}

if ($action === 'delete_template') {
    $pdo->prepare('UPDATE templates SET visible = 0, sort = 9999 WHERE id = ?')->execute([(int)($_POST['tid'] ?? 0)]);
    go($self . '?view=templates', 'Das Template ist ausgeblendet. Bereits gekaufte Downloads funktionieren weiter.');
}

if ($action === 'private_done') {
    $pdo->prepare('UPDATE private_bookings SET done = 1 - done WHERE id = ?')->execute([(int)($_POST['pid'] ?? 0)]);
    go($self . '?view=private', 'Gespeichert.');
}

if ($action === 'delete_subscriber') {
    $pdo->prepare('DELETE FROM subscribers WHERE id = ?')->execute([(int)($_POST['sid'] ?? 0)]);
    go($self . '?view=liste', 'Der Kontakt ist gelöscht.');
}

// --- Ansichten -------------------------------------------------------------
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
            <label for="pdf">PDF zum Herunterladen <?= $t['file'] ? '<span class="muted">(vorhanden, nur hochladen zum Ersetzen)</span>' : '' ?></label><input id="pdf" name="pdf" type="file" accept="application/pdf">
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
    render_page('Templates & Maker Club', function () use ($self, $flash, $m, $makers, $tpls, $orders) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Templates &amp; Maker Club</h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <h2>FORMA Maker Club · Template des Monats</h2>
        <form method="post" enctype="multipart/form-data" class="card stack">
            <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="save_maker">
            <p><strong><?= $makers ?></strong> <?= $makers === 1 ? 'Mitglied' : 'Mitglieder' ?> im Maker Club. Sie stehen in MailerLite in der Gruppe „Maker Club“ (sobald MailerLite verbunden ist).</p>
            <label for="maker_title">Name des Templates</label><input id="maker_title" name="maker_title" value="<?= forma_h($m['title']) ?>" placeholder="z. B. Herbstblatt-Schälchen">
            <label for="maker_text">Kurze Beschreibung</label><input id="maker_text" name="maker_text" value="<?= forma_h($m['text']) ?>" placeholder="z. B. Ein kleines Schälchen in Blattform, ideal für Ringe oder Teebeutel">
            <label for="maker_pdf">PDF <?= $m['file'] ? '<span class="muted">(vorhanden, nur hochladen zum Ersetzen)</span>' : '' ?></label><input id="maker_pdf" name="maker_pdf" type="file" accept="application/pdf">
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

if ($view === 'private') {
    forma_reconcile_payments($pdo, true);
    $rows = $pdo->query("SELECT * FROM private_bookings WHERE status IN ('bezahlt','anfrage') ORDER BY done, id DESC")->fetchAll();
    render_page('Private Pottery', function () use ($self, $flash, $rows) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Private Pottery</h1>
        <p class="muted">Bezahlte Buchungen und Anfragen. Mach mit jeder Person den Termin aus und hake sie danach als erledigt ab.</p>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="list">
        <?php foreach ($rows as $b): $paid = $b['status'] === 'bezahlt'; ?>
            <div class="card booking<?= $b['done'] ? ' is-cancelled' : '' ?>">
                <div class="booking-head"><strong><?= forma_h($b['name']) ?></strong>
                    <span class="pill<?= $paid ? ' pill-ok' : '' ?>"><?= $paid ? forma_h(((int)$b['hours'] === 1 ? '1 Stunde' : $b['hours'] . ' Stunden') . ' · ' . forma_euro((int)$b['amount_cents']) . ' bezahlt') : 'Anfrage, noch nicht gebucht' ?></span></div>
                <div><?= nl2br(forma_h($b['project'])) ?></div>
                <?php if ($b['wish']): ?><div class="muted small">Wunschtermin: <?= forma_h($b['wish']) ?></div><?php endif; ?>
                <div class="sel small"><?= forma_h($b['email']) ?><?= $b['phone'] ? ' · ' . forma_h($b['phone']) : '' ?></div>
                <div class="muted small"><?= $paid ? 'Gebucht' : 'Angefragt' ?> am <?= forma_h(date('d. m. Y, H:i', strtotime($b['created_at']))) ?></div>
                <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="private_done"><input type="hidden" name="pid" value="<?= (int)$b['id'] ?>">
                <button class="link" type="submit"><?= $b['done'] ? 'Wieder als offen markieren' : 'Termin ausgemacht / erledigt' ?></button></form>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><p class="muted">Noch keine Buchungen oder Anfragen.</p><?php endif; ?>
        </div>
    <?php });
}

if ($view === 'gutscheine') {
    forma_reconcile_payments($pdo, true);
    $vs = $pdo->query("SELECT * FROM vouchers WHERE status IN ('bezahlt','eingeloest') ORDER BY id DESC")->fetchAll();
    $sum = array_sum(array_column($vs, 'amount_cents'));
    render_page('Gutscheine', function () use ($self, $flash, $vs, $sum) { ?>
        <p><a href="<?= forma_h($self) ?>">← Zur Übersicht</a></p>
        <h1>Gutscheine</h1>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <div class="card stack">
            <p><strong><?= count($vs) ?></strong> verkaufte Gutscheine · zusammen <strong><?= forma_h(forma_euro((int)$sum)) ?></strong></p>
            <p class="muted small">Jeder online gekaufte Gutschein bekommt automatisch eine fortlaufende Nummer. Wenn jemand einen Gutschein einlöst, markierst du ihn hier als eingelöst. Die Liste kannst du für die Buchhaltung herunterladen.</p>
            <p><a class="btn" href="<?= forma_h($self) ?>?view=gutscheine_export">Liste herunterladen (CSV)</a></p>
        </div>
        <div class="list" style="margin-top:16px">
        <?php foreach ($vs as $v): $done = $v['status'] === 'eingeloest'; ?>
            <div class="card booking<?= $done ? ' is-cancelled' : '' ?>">
                <div class="booking-head"><strong><?= forma_h($v['nr']) ?></strong><span class="pill<?= $done ? '' : ' pill-ok' ?>"><?= $done ? 'eingelöst am ' . forma_h(date('d. m. Y', strtotime($v['redeemed_at']))) : 'offen' ?></span></div>
                <div><?= forma_h(forma_voucher_title($v)) ?> · <?= forma_h(forma_euro((int)$v['amount_cents'])) ?></div>
                <?php if ($v['recipient'] || $v['sender']): ?><div class="muted small"><?= $v['recipient'] ? 'Für ' . forma_h($v['recipient']) : '' ?><?= $v['recipient'] && $v['sender'] ? ' · ' : '' ?><?= $v['sender'] ? 'von ' . forma_h($v['sender']) : '' ?></div><?php endif; ?>
                <div class="muted small">Gekauft am <?= forma_h(date('d. m. Y', strtotime($v['paid_at']))) ?> von <?= forma_h($v['buyer_name']) ?></div>
                <div class="sel small"><?= forma_h($v['buyer_email']) ?></div>
                <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center">
                    <a href="<?= forma_h(forma_voucher_link($v)) ?>" target="_blank" rel="noopener">PDF ansehen</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="voucher_status"><input type="hidden" name="vid" value="<?= (int)$v['id'] ?>"><input type="hidden" name="to" value="<?= $done ? 'bezahlt' : 'eingeloest' ?>">
                    <button class="link" type="submit"><?= $done ? 'Doch nicht eingelöst' : 'Als eingelöst markieren' ?></button></form>
                </div>
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
                <?php if ($cancel && $b['paid_at']): ?><div class="muted small">Online bezahlt. Die Rückerstattung machst du im Stripe-Dashboard unter Zahlungen.</div><?php endif; ?>
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
render_page('Kursverwaltung', function () use ($groups, $past, $self, $flash, $pdo, $subCount, $popupLive, $voucherOpen, $privateOpen) { ?>
    <div class="top">
        <h1>Kursverwaltung</h1>
        <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="logout"><button class="link" type="submit">Abmelden</button></form>
    </div>
    <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
    <?php if (!forma_stripe_key()): ?><p class="alert">Die Online-Bezahlung ist noch nicht eingerichtet. Bis dahin werden alle Kurse ohne Bezahlung reserviert. <a href="<?= forma_h($self) ?>?view=einstellungen">Jetzt Stripe verbinden</a></p><?php endif; ?>
    <nav class="tiles">
        <a class="tile" href="<?= forma_h($self) ?>?view=edit"><strong>+ Einzeltermin</strong><span class="muted small">z. B. ein Special-Kurs</span></a>
        <a class="tile" href="<?= forma_h($self) ?>?view=series"><strong>+ Serientermine</strong><span class="muted small">z. B. jeden Mi &amp; Fr</span></a>
        <a class="tile" href="<?= forma_h($self) ?>?view=popup"><strong>Pop-up &amp; Deko</strong><span class="muted small"><?= $popupLive ? 'gerade sichtbar' : 'ausgeschaltet' ?> · Deko</span></a>
        <a class="tile" href="<?= forma_h($self) ?>?view=templates"><strong>Templates</strong><span class="muted small">&amp; Maker Club</span></a>
        <a class="tile" href="<?= forma_h($self) ?>?view=private"><strong>Private Pottery</strong><span class="muted small"><?= $privateOpen ?> offen</span></a>
        <a class="tile" href="<?= forma_h($self) ?>?view=gutscheine"><strong>Gutscheine</strong><span class="muted small"><?= $voucherOpen ?> offen</span></a>
        <a class="tile" href="<?= forma_h($self) ?>?view=liste"><strong>E-Mail-Liste</strong><span class="muted small"><?= $subCount ?> <?= $subCount === 1 ? 'Kontakt' : 'Kontakte' ?></span></a>
        <a class="tile" href="<?= forma_h($self) ?>?view=einstellungen"><strong>Einstellungen</strong><span class="muted small">Stripe, MailerLite, E-Mail</span></a>
    </nav>
    <h2>Kommende Termine</h2>
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
}
@media (min-width:1200px){
  form.card.stack{display:grid;grid-template-columns:minmax(0,1fr);max-width:900px}
}
</style>
</head>
<body>
<main>
<a class="brand" href="/"><img src="../img/forma-logo.png" alt=""><span>FORMA · Kursverwaltung</span></a>
<?php $body(); ?>
</main>
</body>
</html>
<?php exit;
}

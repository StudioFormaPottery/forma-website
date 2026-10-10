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
    ];
    if ($data[0] === '' || $data[1] === '') {
        go($self . '?view=edit' . ($id ? '&id=' . $id : ''), 'Bitte gib mindestens einen Namen und den Termin an.');
    }
    if ($id > 0) {
        $pdo->prepare('UPDATE courses SET title=?, date_text=?, start_date=?, capacity=?, min_participants=?, price_text=?, description=?, visible=? WHERE id=?')
            ->execute([...$data, $id]);
        go($self . '?view=course&id=' . $id, 'Gespeichert.');
    }
    $pdo->prepare('INSERT INTO courses (title, date_text, start_date, capacity, min_participants, price_text, description, visible, created_at) VALUES (?,?,?,?,?,?,?,?,?)')
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
        go($self, 'Bitte gib das Passwort deines Postfachs ein.');
    }
    try {
        forma_smtp_send($pw, FORMA_NOTIFY_EMAIL, 'Test: E-Mail-Versand ist eingerichtet',
            "Hallo Vivien,\n\ndiese Test-Mail kommt von deiner Website. Ab jetzt gehen alle Bestätigungen über dein Postfach " . FORMA_FROM_EMAIL . " raus.\n", null);
        forma_save_mail_password($pw);
        go($self, 'Geschafft! Der E-Mail-Versand läuft jetzt über dein Postfach. Eine Test-Mail ist unterwegs an ' . FORMA_NOTIFY_EMAIL . '.');
    } catch (Throwable $e) {
        error_log('FORMA SMTP Test: ' . $e->getMessage());
        go($self, 'Die Anmeldung beim Postfach hat nicht geklappt. Bitte prüfe das Passwort von ' . FORMA_FROM_EMAIL . ' und versuch es noch einmal.');
    }
}

if ($action === 'remove_mail') {
    forma_save_mail_password(null);
    go($self, 'Der Versand über dein Postfach ist ausgeschaltet.');
}

// --- Ansichten -------------------------------------------------------------
if ($view === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $c = ['id' => 0, 'title' => '', 'date_text' => '', 'start_date' => date('Y-m-d'), 'capacity' => 6, 'min_participants' => 1, 'price_text' => '', 'description' => '', 'visible' => 1];
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
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE course_id = ? ORDER BY status, created_at');
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
        <?php foreach ($bookings as $b): $cancel = $b['status'] === 'storniert'; ?>
            <div class="card booking<?= $cancel ? ' is-cancelled' : '' ?>">
                <div class="booking-head">
                    <strong><?= forma_h($b['name']) ?></strong>
                    <span class="pill"><?= (int)$b['persons'] ?> <?= (int)$b['persons'] === 1 ? 'Person' : 'Personen' ?><?= $cancel ? ' · storniert' : '' ?></span>
                </div>
                <?php if ($b['email']): ?><div class="sel"><?= forma_h($b['email']) ?></div><?php endif; ?>
                <?php if ($b['phone']): ?><div class="sel"><?= forma_h($b['phone']) ?></div><?php endif; ?>
                <?php if ($b['message']): ?><div class="muted">„<?= forma_h($b['message']) ?>“</div><?php endif; ?>
                <div class="muted small">Angemeldet am <?= forma_h(date('d. m. Y, H:i', strtotime($b['created_at']))) ?></div>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>">
                    <input type="hidden" name="action" value="booking_status">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <input type="hidden" name="status" value="<?= $cancel ? 'bestaetigt' : 'storniert' ?>">
                    <button class="link" type="submit"><?= $cancel ? 'Wieder aktivieren' : 'Stornieren (Platz wird frei)' ?></button>
                </form>
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
$upcoming = forma_upcoming_courses($pdo, false);
$stmt = $pdo->prepare('SELECT * FROM courses WHERE start_date < ? ORDER BY start_date DESC LIMIT 20');
$stmt->execute([date('Y-m-d')]);
$past = $stmt->fetchAll();
render_page('Kursverwaltung', function () use ($upcoming, $past, $self, $flash, $pdo) { ?>
    <div class="top">
        <h1>Kursverwaltung</h1>
        <form method="post"><input type="hidden" name="csrf" value="<?= forma_h(csrf()) ?>"><input type="hidden" name="action" value="logout"><button class="link" type="submit">Abmelden</button></form>
    </div>
    <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
    <p><a class="btn" href="<?= forma_h($self) ?>?view=edit">+ Neuen Termin anlegen</a></p>
    <h2>Kommende Termine</h2>
    <?php if (!$upcoming): ?><p class="muted">Gerade sind keine Termine eingetragen.</p><?php endif; ?>
    <div class="list">
    <?php foreach ($upcoming as $c): $full = $c['free'] <= 0; ?>
        <div class="card course">
            <a class="course-link" href="<?= forma_h($self) ?>?view=course&amp;id=<?= (int)$c['id'] ?>">
                <strong><?= forma_h($c['title']) ?></strong>
                <span class="muted"><?= forma_h($c['date_text']) ?></span>
                <span class="pill<?= $full ? ' pill-full' : '' ?>"><?= (int)$c['booked'] ?> / <?= (int)$c['capacity'] ?> belegt<?= $full ? ' · ausgebucht' : '' ?></span>
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
    <h2>E-Mail-Versand</h2>
    <?php if (forma_mail_config()): ?>
        <div class="card stack">
            <p><span class="pill">Eingerichtet</span></p>
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
@font-face{font-family:"Outfit";font-weight:300 500;src:url(../fonts/Outfit-Regular.woff) format("woff")}
@font-face{font-family:"Outfit";font-weight:600 800;src:url(../fonts/Outfit-Bold.woff) format("woff")}
@font-face{font-family:"Bricolage Grotesque";font-weight:600 800;src:url(../fonts/BricolageGrotesque-Bold.woff) format("woff")}
:root{--paper:#EEE2D4;--card:#F8F1EA;--ink:#33211B;--soft:#674B40;--clay:#8C2C1D;--line:#D9C5B2;color-scheme:light}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:17px/1.55 "Outfit",system-ui,sans-serif}
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
.past{margin-top:28px}.past summary{cursor:pointer;font-weight:500;margin-bottom:12px}
@media (max-width:480px){.row{grid-template-columns:1fr}}
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

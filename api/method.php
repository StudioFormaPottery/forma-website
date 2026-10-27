<?php
// Movecia Method & Unfold: Kauf starten, Rückkehr von Stripe, Workbook-Downloads (nur Mitglieder).
declare(strict_types=1);
require __DIR__ . '/method-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
mv_schema($pdo);

// Rückkehr von Stripe
if (isset($_GET['paid'])) {
    $r = mv_sub($pdo, (int)$_GET['paid']);
    if (!$r || !hash_equals((string)$r['token'], (string)($_GET['k'] ?? ''))) mm_page('oh', 'Link nicht gültig · Link not valid', 'Dieser Link funktioniert leider nicht. Wenn du schon bezahlt hast, bekommst du in Kürze eine E-Mail von mir. · This link does not work. If you have already paid, you will receive an email from me shortly.');
    try { mv_check_paid($pdo, $r); } catch (Throwable $e) { error_log('MV paid: ' . $e->getMessage()); }
    header('Location: /mitglieder/?start=' . (int)$r['id'] . '&k=' . $r['token'] . ($r['lang'] === 'en' ? '&lang=en' : ''), true, 303);
    exit;
}

// E-Mail bestätigen (Vivien, 25.10.2026): erst danach Willkommensmail, Rechnung und Zugang
if (isset($_GET['bestaetigen'])) {
    $m = mv_confirm_ok($pdo, (int)$_GET['bestaetigen'], (int)($_GET['e'] ?? 0), (string)($_GET['k'] ?? ''));
    if (!$m) mm_page('oh', 'Link nicht gültig · Link not valid', 'Dieser Link funktioniert leider nicht mehr. Schreib mir einfach, dann schicke ich dir einen neuen. · This link no longer works. Just write to me and I will send you a new one.');
    $was = (int)$m['email_ok'] === 1;
    try { mv_confirm($pdo, $m); } catch (Throwable $e) { error_log('MV confirm: ' . $e->getMessage()); }
    $en = $m['lang'] === 'en';
    $pending = array_filter(mv_subs_of($pdo, (string)$m['email']), fn($s) => mv_open_at($s) > time());
    $m = mv_member($pdo, (int)$m['id']);
    if (!$pending && $m['pass_hash'] === '') { header('Location: ' . mv_pw_link($pdo, $m, 24 * 7), true, 303); exit; }
    if (!$pending) { header('Location: /mitglieder/' . ($en ? '?lang=en' : ''), true, 303); exit; }
    $at = min(array_map('mv_open_at', $pending));
    mm_page('ok', $en ? 'Thank you, your email is confirmed' : 'Danke, deine E-Mail ist bestätigt',
        ($was ? '' : ($en ? 'I\'ve just sent you your welcome email with all the information. ' : 'Ich habe dir gerade deine Willkommensmail mit allen Infos geschickt. '))
        . ($en ? 'Your access unlocks on ' . mv_date($at, 'en') . '. On that day you will get an email with your link to the members area.' : 'Dein Zugang schaltet sich am ' . mv_date($at) . ' frei. An diesem Tag bekommst du eine E-Mail mit deinem Link zum Mitgliederbereich.'));
}

// Workbook / Planner / Rezepte herunterladen
if (isset($_GET['file'])) {
    $m = mv_current($pdo);
    $q = $pdo->prepare('SELECT f.*, p.audience, p.unlock, p.visible, p.theme FROM mv_files f JOIN mv_programs p ON p.id = f.program_id WHERE f.id = ?');
    $q->execute([(int)$_GET['file']]);
    $f = $q->fetch();
    $acc = $m ? mv_access($pdo, (string)$m['email']) : null;
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
    // Method: Monat für Monat ab dem eigenen Starttag (Vivien, 25.10.2026), Unfold: nach Datum
    $ok = $f && $acc && (int)$f['visible'] === 1
        && ($f['audience'] === 'unfold' ? (($f['unlock'] === '' || $f['unlock'] <= $today) && $acc['unfold'] !== null && empty($acc['ulock'])) : mv_method_program_open($pdo, $acc, (int)$f['program_id']));
    $path = $f ? mv_file_dir() . '/' . basename((string)$f['file']) : '';
    if (!$ok || !is_file($path)) { http_response_code(404); mm_page('oh', 'Nicht verfügbar · Not available', 'Diese Datei gibt es nur im Mitgliederbereich. · This file is only available in the members area.', '/mitglieder/', 'Mitgliederbereich · Members area'); }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'application/octet-stream'));
    header('Content-Disposition: ' . ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', mv_t($f, 'label', (string)$m['lang'])) . '.' . $ext . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

// Kauf starten
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $in = $_POST ?: (json_decode((string)file_get_contents('php://input'), true) ?: []);
    if (!empty($in['website'])) { echo json_encode(['error' => 'Nope.']); exit; }
    try {
        echo json_encode(['url' => mv_checkout($pdo, $in)]);
    } catch (Throwable $e) {
        http_response_code(400);
        $msg = $e->getMessage();
        if (str_contains($msg, 'Stripe')) $msg = ($in['lang'] ?? '') === 'en' ? 'Payment is not working right now. Please try again in a moment or send me a message.' : 'Die Bezahlung klappt gerade nicht. Bitte versuch es gleich noch einmal oder schreib mir.';
        echo json_encode(['error' => $msg]);
    }
    exit;
}

// Freie Unfold-Plätze für die Seite
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['unfold_free' => mv_unfold_free($pdo), 'unfold_open' => (bool)mv_cfg()['u_open'], 'launched' => mv_launched()]);

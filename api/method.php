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
    if (!$r || !hash_equals((string)$r['token'], (string)($_GET['k'] ?? ''))) mm_page('oh', 'Link not valid', 'This link does not work. If you have paid, you will get an email from me shortly.');
    try { mv_check_paid($pdo, $r); } catch (Throwable $e) { error_log('MV paid: ' . $e->getMessage()); }
    header('Location: /mitglieder/?start=' . (int)$r['id'] . '&k=' . $r['token'] . ($r['lang'] === 'en' ? '&lang=en' : ''), true, 303);
    exit;
}

// Workbook / Planner / Rezepte herunterladen
if (isset($_GET['file'])) {
    $m = mv_current($pdo);
    $q = $pdo->prepare('SELECT f.*, p.audience, p.unlock, p.visible, p.theme FROM mv_files f JOIN mv_programs p ON p.id = f.program_id WHERE f.id = ?');
    $q->execute([(int)$_GET['file']]);
    $f = $q->fetch();
    $acc = $m ? mv_access($pdo, (string)$m['email']) : null;
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
    $ok = $f && $acc && ($f['unlock'] === '' || $f['unlock'] <= $today) && (int)$f['visible'] === 1
        && ($f['audience'] === 'unfold' ? $acc['unfold'] !== null : ($acc['method'] && (!$acc['starter'] || $f['theme'] === 'ankommen')));
    $path = $f ? mv_file_dir() . '/' . basename((string)$f['file']) : '';
    if (!$ok || !is_file($path)) { http_response_code(404); mm_page('oh', 'Not available', 'This file is only available in the members area.', '/mitglieder/', 'Members area'); }
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
        if (str_contains($msg, 'Stripe')) $msg = ($in['lang'] ?? '') === 'en' ? 'Payment is not available right now. Please try again in a moment or write to me.' : 'Die Bezahlung klappt gerade nicht. Bitte versuch es gleich noch einmal oder schreib mir.';
        echo json_encode(['error' => $msg]);
    }
    exit;
}

// Freie Unfold-Plätze für die Seite
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['unfold_free' => mv_unfold_free($pdo), 'unfold_open' => (bool)mv_cfg()['u_open'], 'launched' => mv_launched()]);

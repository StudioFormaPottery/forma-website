<?php
// Movecia-Gutscheine: kaufen (→ Stripe), Rückkehr von Stripe, PDF, Bezahl-Link aus der Verwaltung, Code prüfen.
declare(strict_types=1);
require __DIR__ . '/mv-gutschein-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
mvg_schema($pdo);

// Rückkehr von Stripe
if (isset($_GET['paid'])) {
    $v = mvg_auth($pdo, (int)$_GET['paid'], (string)($_GET['k'] ?? ''));
    if (!$v) mm_page('oh', 'Link nicht gültig', 'Dieser Link funktioniert leider nicht. Wenn du bezahlt hast, bekommst du gleich eine E-Mail von mir.');
    try { mvg_check($pdo, $v); } catch (Throwable $e) { error_log('MVG paid: ' . $e->getMessage()); }
    header('Location: /gutschein/?ok=' . (int)$v['id'] . '&k=' . $v['token'] . ($v['lang'] === 'en' ? '&lang=en' : ''), true, 303);
    exit;
}

// E-Mail bestätigen (Vivien, 26.10.2026): erst danach kommen Gutschein und Rechnung
if (isset($_GET['bestaetigen'])) {
    $v = mvg_confirm_ok($pdo, (int)$_GET['bestaetigen'], (string)($_GET['k'] ?? ''));
    if (!$v) mm_page('oh', 'Link nicht gültig', 'Dieser Link funktioniert leider nicht. Schreib mir einfach an hello@moveciamovement.com, dann schicke ich dir deinen Gutschein.');
    try { mvg_confirm($pdo, $v); } catch (Throwable $e) { error_log('MVG confirm: ' . $e->getMessage()); }
    header('Location: /gutschein/?ok=' . (int)$v['id'] . '&k=' . $v['token'] . '&bestaetigt=1' . ($v['lang'] === 'en' ? '&lang=en' : ''), true, 303);
    exit;
}

// Gutschein als PDF (nur bezahlt und mit bestätigter E-Mail-Adresse)
if (isset($_GET['pdf'])) {
    $v = mvg_auth($pdo, (int)$_GET['pdf'], (string)($_GET['k'] ?? ''));
    if ($v && $v['status'] === 'offen') { try { mvg_check($pdo, $v); $v = mvg_get($pdo, (int)$v['id']); } catch (Throwable $e) {} }
    if (!$v || !in_array($v['status'], ['bezahlt', 'eingeloest'], true)) { http_response_code(404); mm_page('oh', 'Noch nicht da', 'Diesen Gutschein gibt es nicht oder er ist noch nicht bezahlt.'); }
    if ((int)$v['email_ok'] !== 1) mm_page('oh', 'Bitte bestätige zuerst deine E-Mail', 'Ich habe dir eine E-Mail mit einem Bestätigungs-Link geschickt. Sobald du darauf tippst, bekommst du deinen Gutschein als PDF.');
    $pdf = mvg_pdf($v);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="Movecia-Gutschein-' . $v['nr'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

// Bezahl-Link aus der Verwaltung: öffnet die Stripe-Bezahlseite
if (isset($_GET['pay'])) {
    $v = mvg_auth($pdo, (int)$_GET['pay'], (string)($_GET['k'] ?? ''));
    if (!$v) mm_page('oh', 'Link nicht gültig', 'Dieser Bezahl-Link funktioniert leider nicht (mehr). Schreib mir einfach, dann schicke ich dir einen neuen.');
    if ($v['status'] === 'zurueckgezogen') mm_page('oh', 'Link nicht mehr gültig', 'Dieser Bezahl-Link gilt nicht mehr. Schreib mir einfach, wenn du den Gutschein noch möchtest.');
    if ($v['status'] !== 'offen') { header('Location: /gutschein/?ok=' . (int)$v['id'] . '&k=' . $v['token'], true, 303); exit; }
    try { header('Location: ' . mvg_checkout($pdo, $v), true, 303); }
    catch (Throwable $e) { error_log('MVG pay: ' . $e->getMessage()); mm_page('oh', 'Gerade nicht erreichbar', 'Die Online-Bezahlung klappt gerade nicht. Bitte versuch es gleich noch einmal.'); }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
$in = $_POST ?: (json_decode((string)file_get_contents('php://input'), true) ?: []);
if (!empty($in['website'])) forma_json(['ok' => true]);
$en = ($in['lang'] ?? '') === 'en';
$action = (string)($in['action'] ?? '');

// Code prüfen (Buchungsformulare und Einlöse-Seite). Abgerechnet wird erst beim Buchen am Server.
if ($action === 'check') {
    $r = mvg_lookup($pdo, (string)($in['code'] ?? ''), $en ? 'en' : 'de');
    if (!$r['ok']) forma_json($r, 422);
    $v = $r['voucher'];
    $p = mvg_product($v);
    $fresh = mvg_used($pdo, (int)$v['id']) === 0;
    forma_json(['ok' => true, 'balance' => $r['balance'], 'balance_text' => mv_eur($r['balance']), 'product' => $v['product'], 'group' => $p['group'],
        'program' => $fresh && $p['group'] !== 'wert', 'months' => (int)($p['months'] ?? 0), 'natal' => (string)($p['natal'] ?? ''),
        'title' => mvg_title($v, $en ? 'en' : 'de'), 'target' => mvg_target_url($v)]);
}

if ($action === 'buy') {
    $loaded = (int)($in['t'] ?? 0);
    if ($loaded > 0 && ((int)(microtime(true) * 1000) - $loaded) < 3000) forma_json(['ok' => false, 'error' => $en ? 'Please try again in a few seconds.' : 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);
    $name = forma_clean((string)($in['buyer_name'] ?? ''), 120);
    $email = strtolower(forma_clean((string)($in['buyer_email'] ?? ''), 160));
    $err = [];
    if (mb_strlen($name) < 2) $err[] = $en ? 'Please enter your name.' : 'Bitte gib deinen Namen an.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = $en ? 'Please enter a valid email address.' : 'Bitte gib eine gültige E-Mail-Adresse an.';
    if (empty($in['consent'])) $err[] = $en ? 'Please confirm the privacy note.' : 'Bitte bestätige noch die Hinweise zum Datenschutz.';
    if ($err) forma_json(['ok' => false, 'error' => implode(' ', $err)], 422);
    if (!forma_stripe_key()) forma_json(['ok' => false, 'error' => $en ? 'Vouchers cannot be bought online right now. Write to me at hello@moveciamovement.com and I will make one for you.' : 'Gutscheine kannst du gerade nicht online kaufen. Schreib mir an hello@moveciamovement.com, dann stelle ich dir einen aus.'], 503);
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'mvg');
    $c = $pdo->prepare('SELECT COUNT(*) FROM mv_vouchers WHERE ip_hash = ? AND created_at >= ?');
    $c->execute([$ip, date('c', time() - 3600)]);
    if ((int)$c->fetchColumn() >= 8) forma_json(['ok' => false, 'error' => $en ? 'Please try again later.' : 'Bitte versuch es später noch einmal.'], 429);
    $amount = (int)round((float)str_replace(',', '.', (string)($in['amount'] ?? '0')) * 100);
    try {
        $v = mvg_create($pdo, ['product' => (string)($in['product'] ?? ''), 'amount_cents' => $amount, 'design' => (int)($in['design'] ?? 1), 'lang' => $en ? 'en' : 'de',
            'recipient' => (string)($in['recipient'] ?? ''), 'sender' => (string)($in['sender'] ?? ''), 'message' => (string)($in['message'] ?? ''),
            'buyer_name' => $name, 'buyer_email' => $email, 'via' => 'web', 'ip_hash' => $ip]);
    } catch (RuntimeException $e) {
        forma_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
    try {
        forma_json(['ok' => true, 'redirect' => mvg_checkout($pdo, $v)]);
    } catch (Throwable $e) {
        error_log('MVG buy: ' . $e->getMessage());
        $pdo->prepare("UPDATE mv_vouchers SET status = 'abgebrochen' WHERE id = ?")->execute([$v['id']]);
        forma_json(['ok' => false, 'error' => $en ? 'Online payment is not available right now. Please try again in a moment.' : 'Die Online-Bezahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.'], 502);
    }
}

forma_json(['ok' => false, 'error' => 'Unbekannte Aktion.'], 400);

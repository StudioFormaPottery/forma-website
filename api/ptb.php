<?php
// Movecia · Personal Training: Anfrage senden, E-Mail bestätigen, bezahlen, Kalenderdatei, belegte Zeiten.
declare(strict_types=1);
require __DIR__ . '/ptb-lib.php';
$pdo = forma_db();
ptb_schema($pdo);
ptb_tick($pdo);

// E-Mail-Adresse bestätigen (Link aus der Mail)
if (isset($_GET['bestaetigen'])) {
    $o = ptb_email_ok($pdo, (string)$_GET['bestaetigen']);
    if (!$o) { http_response_code(404); header('Content-Type: text/html; charset=utf-8'); exit('Dieser Link funktioniert leider nicht mehr. Bitte schreib mir an ' . MM_FROM_EMAIL . ' · This link no longer works. Please email me at ' . MM_FROM_EMAIL . '.'); }
    header('Location: ' . ptb_link($o) . '&ok=1', true, 303);
    exit;
}
// Kalenderdatei mit allen fixen Terminen
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['ics'])) {
    $o = ptb_order_auth($pdo, (int)($_GET['o'] ?? 0), (string)($_GET['k'] ?? ''));
    if (!$o || $o['status'] !== 'bezahlt') { http_response_code(404); exit('Nicht gefunden.'); }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="Movecia-Personal-Training.ics"');
    echo ptb_ics(ptb_sessions($pdo, (int)$o['id'], true), ptb_en($o));
    exit;
}
// Belegte Zeiten für den Kalender auf der Seite (nur Uhrzeiten, keine Namen)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['busy'])) {
    header('Cache-Control: no-store');
    forma_json(['ok' => true, 'busy' => ptb_busy($pdo, date('Y-m-d'), date('Y-m-d', strtotime('+6 months')))]);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
$in = $_POST;
if (!empty($in['website'])) forma_json(['ok' => true]);
$action = (string)($in['action'] ?? '');
$en = ($in['lang'] ?? '') === 'en';
$t = fn(string $de, string $enTxt) => $en ? $enTxt : $de;
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'ptb');

if ($action === 'request') {
    $loaded = (int)($in['t'] ?? 0);
    if ($loaded > 0 && ((int)(microtime(true) * 1000) - $loaded) < 3000) forma_json(['ok' => false, 'error' => $t('Bitte versuch es in ein paar Sekunden noch einmal.', 'Please try again in a few seconds.')], 429);
    $name = forma_clean((string)($in['name'] ?? ''), 120);
    $email = strtolower(forma_clean((string)($in['email'] ?? ''), 160));
    $phone = forma_clean((string)($in['phone'] ?? ''), 40);
    $hotel = forma_clean((string)($in['hotel'] ?? ''), 120);
    $note = trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($in['note'] ?? '')) ?? '', 0, 1000));
    $want = max(1, min(PTB_MAX_UNITS, (int)($in['units'] ?? 1)));
    $wishes = ptb_clean_wishes($pdo, (string)($in['wishes'] ?? '[]'));
    $err = [];
    if (count($wishes) < $want) $err[] = $t('Bitte wähl für jede Einheit einen Tag und eine Uhrzeit. Eine Zeit ist inzwischen vielleicht schon vergeben, dann nimm bitte eine andere.', 'Please choose a day and a time for each session. One of the times may have just been taken, so please pick another.');
    if (mb_strlen($name) < 2) $err[] = $t('Bitte gib deinen Namen an.', 'Please enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = $t('Bitte gib eine gültige E-Mail-Adresse an.', 'Please enter a valid email address.');
    if (mb_strlen(preg_replace('/\D/', '', $phone)) < 6) $err[] = $t('Bitte gib deine Handynummer an, damit ich dich bei Terminfragen erreiche.', 'Please enter your mobile number so I can reach you about your times.');
    if (empty($in['consent'])) $err[] = $t('Bitte bestätige die Bedingungen und den Datenschutz.', 'Please confirm the terms and privacy notice.');
    if ($err) forma_json(['ok' => false, 'error' => implode(' ', $err)], 422);
    $c = $pdo->prepare('SELECT COUNT(*) FROM mm_ptb_orders WHERE ip_hash = ? AND created_at >= ?');
    $c->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$c->fetchColumn() >= 6) forma_json(['ok' => false, 'error' => $t('Bitte versuch es später noch einmal oder ruf mich an: ', 'Please try again later or call me: ') . NATAL_COMPANY['phone']], 429);
    $wishes = array_slice($wishes, 0, $want);
    $tok = bin2hex(random_bytes(16));
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare("INSERT INTO mm_ptb_orders (units, unit_cents, amount_cents, name, email, phone, hotel, note, lang, status, token, email_token, ip_hash, consent_terms, created_at) VALUES (?,?,?,?,?,?,?,?,?, 'email', ?, ?, ?, 1, ?)")
            ->execute([$want, ptb_unit_cents($want), ptb_unit_cents($want) * $want, $name, $email, $phone, $hotel, $note, $en ? 'en' : 'de', $tok, bin2hex(random_bytes(16)), $ipHash, date('c')]);
        $oid = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO mm_ptb_sessions (order_id, pos, wish_date, wish_time, date, start, status, created_at) VALUES (?,?,?,?,?,?, 'offen', ?)");
        foreach ($wishes as $i => $w) $ins->execute([$oid, $i, $w['date'], $w['time'], $w['date'], $w['time'], date('c')]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        error_log('PTB request: ' . $e->getMessage());
        forma_json(['ok' => false, 'error' => $t('Das hat gerade nicht geklappt. Bitte versuch es noch einmal.', "Something went wrong. Please try again.")], 500);
    }
    $o = ptb_order($pdo, $oid);
    ptb_mail_confirm($o);
    forma_json(['ok' => true, 'redirect' => ptb_link($o) . '&neu=1']);
}

$o = ptb_order_auth($pdo, (int)($in['o'] ?? 0), (string)($in['k'] ?? ''));
if (!$o) forma_json(['ok' => false, 'error' => $t('Dieser Link funktioniert leider nicht.', 'Sorry, this link does not work.')], 404);

if ($action === 'resend') {
    if ($o['status'] !== 'email') forma_json(['ok' => true]);
    $f = forma_data_dir() . '/ptb-resend-' . (int)$o['id'];
    if (is_file($f) && filemtime($f) > time() - 120) forma_json(['ok' => false, 'error' => $t('Die Mail ist gerade erst unterwegs. Schau bitte auch im Spam-Ordner nach.', 'The email is already on its way. Please check your spam folder too.')], 429);
    @touch($f);
    ptb_mail_confirm($o);
    forma_json(['ok' => true]);
}

if ($action === 'pay') {
    if ($o['status'] === 'bezahlt') forma_json(['ok' => true, 'redirect' => ptb_link($o)]);
    if ($o['status'] !== 'angebot') forma_json(['ok' => false, 'error' => $t('Bezahlen geht erst, wenn ich deine Termine bestätigt habe.', 'You can pay once I have confirmed your times.')], 409);
    // Termine noch frei? (inzwischen könnte jemand anderes bezahlt haben)
    $busy = ptb_busy($pdo, date('Y-m-d'), date('Y-m-d', strtotime('+7 months')), (int)$o['id']);
    foreach (ptb_sessions($pdo, (int)$o['id'], true) as $s) {
        if (strtotime($s['date'] . ' ' . $s['start']) < time() + 3600 || !ptb_slot_free($busy, (string)$s['date'], (string)$s['start'])) {
            forma_json(['ok' => false, 'error' => $t('Ein Termin ist leider nicht mehr frei. Schreib mir bitte kurz, dann finden wir eine neue Zeit: ', 'Sorry, one of the times is no longer available. Please message me and we\'ll find a new one: ') . NATAL_COMPANY['phone']], 409);
        }
    }
    if (!forma_stripe_key()) forma_json(['ok' => false, 'error' => $t('Die Online-Zahlung ist gerade nicht erreichbar. Bitte schreib mir an ', 'Online payment is unavailable right now. Please email me at ') . MM_FROM_EMAIL], 503);
    try {
        forma_json(['ok' => true, 'redirect' => ptb_checkout($pdo, $o)]);
    } catch (Throwable $e) {
        error_log('PTB pay: ' . $e->getMessage());
        forma_json(['ok' => false, 'error' => $t('Die Online-Zahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.', 'Online payment is unavailable right now. Please try again in a moment.')], 502);
    }
}
forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 400);

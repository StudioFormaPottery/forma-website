<?php
// Movecia Academy: Buchung (Anzahlung über Stripe), Rückkehr von Stripe, weitere Teilzahlungen, Kennenlerngespräch,
// Mitgliederbereich (Notizen speichern, Chat senden/abrufen).
declare(strict_types=1);
require __DIR__ . '/academy-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
ac_schema($pdo);
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'academy');

// Rückkehr von Stripe (erste Zahlung → Danke-Seite, spätere → Mitgliederbereich)
if (isset($_GET['paid'])) {
    $r = ac_booking($pdo, (int)$_GET['paid']);
    if (!$r || !hash_equals((string)$r['token'], (string)($_GET['k'] ?? ''))) mm_page('oh', 'Link nicht gültig · Link not valid', 'Dieser Link funktioniert leider nicht. Wenn du schon bezahlt hast, bekommst du in Kürze eine E-Mail von mir. · This link does not work. If you have already paid, you will receive an email from me shortly.');
    $n = max(1, (int)($_GET['p'] ?? 1));
    try { ac_check_part($pdo, $r, $n); } catch (Throwable $e) { error_log('AC paid: ' . $e->getMessage()); }
    $r = ac_booking($pdo, (int)$r['id']);
    $en = $r['lang'] === 'en';
    if ($r['status'] !== 'bezahlt') mm_page('oh', $en ? 'Almost there' : 'Fast geschafft', $en ? 'Your payment has not come through yet. If you have paid, you will get your confirmation by email in a few minutes.' : 'Deine Zahlung ist noch nicht angekommen. Wenn du bezahlt hast, bekommst du deine Bestätigung in ein paar Minuten per E-Mail.', '/academy/teacher-training/' . ($en ? '?lang=en' : ''), $en ? 'Back to the training' : 'Zurück zur Ausbildung');
    header('Location: ' . ($n === 1 ? '/academy/teacher-training/?danke=1' . ($en ? '&lang=en' : '') . '#top' : '/academy/mitglieder/?bezahlt=1' . ($en ? '&lang=en' : '')), true, 303);
    exit;
}

// Nächste offene Teilzahlung bezahlen (Link in Mail und im Mitgliederbereich)
if (isset($_GET['pay'])) {
    $r = ac_booking($pdo, (int)$_GET['pay']);
    if (!$r || $r['status'] !== 'bezahlt' || !hash_equals((string)$r['token'], (string)($_GET['k'] ?? ''))) mm_page('oh', 'Link nicht gültig · Link not valid', 'Dieser Link funktioniert leider nicht. Schreib mir einfach. · This link does not work. Just write to me.');
    $p = ac_next_part($r);
    $en = $r['lang'] === 'en';
    if (!$p) mm_page('ok', $en ? 'All paid' : 'Alles bezahlt', $en ? 'Everything is already paid. Thank you so much!' : 'Es ist schon alles bezahlt. Danke dir von Herzen!', ac_area($r['lang']), $en ? 'Members area' : 'Zum Mitgliederbereich');
    try { header('Location: ' . ac_part_checkout($pdo, $r, $p), true, 303); exit; }
    catch (Throwable $e) { error_log('AC pay: ' . $e->getMessage()); mm_page('oh', $en ? 'Not right now' : 'Gerade nicht', $en ? 'Payment is not working right now. Please try again in a moment.' : 'Die Bezahlung klappt gerade nicht. Bitte versuch es gleich noch einmal.'); }
}

$in = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ($_POST ?: (json_decode((string)file_get_contents('php://input'), true) ?: [])) : [];
$action = (string)($in['action'] ?? ($_GET['action'] ?? ''));
$en = ($in['lang'] ?? '') === 'en';

// Mitgliederbereich: Notizen und Chat (nur angemeldet, Buchung mit Anzahlung)
if (in_array($action, ['note', 'chat', 'chat_get'], true)) {
    $m = mv_current($pdo);
    $r = $m ? ac_booking_of($pdo, (string)$m['email']) : null;
    if (!$r) forma_json(['ok' => false, 'error' => $en ? 'Please log in again.' : 'Bitte melde dich neu an.'], 403);
    if ($action === 'note') { ac_note_set($pdo, (int)$m['id'], (string)($in['text'] ?? '')); forma_json(['ok' => true, 'at' => date('H:i')]); }
    if ($action === 'chat') {
        try { ac_msg_send($pdo, $r, (string)($in['text'] ?? '')); } catch (Throwable $e) { forma_json(['ok' => false, 'error' => $e->getMessage()], 400); }
    }
    forma_json(['ok' => true, 'msgs' => ac_msgs($pdo, (int)$r['id'])]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($in['website'])) forma_json(['ok' => false, 'error' => 'Nope.'], 400);
    $loaded = (int)($in['t'] ?? 0);
    if ($loaded > 0 && ((int)(microtime(true) * 1000) - $loaded) < 3000) forma_json(['ok' => false, 'error' => $en ? 'Please try again in a few seconds.' : 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);

    if ($action === 'call') {
        try { $r = ac_call_request($pdo, $in, $ipHash); }
        catch (Throwable $e) { error_log('AC call: ' . $e->getMessage()); forma_json(['ok' => false, 'error' => $en ? 'That didn\'t work just now. Please try again.' : 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.'], 500); }
        forma_json($r, $r['ok'] ? 200 : 409);
    }

    if ($action === 'book') {
        $q = $pdo->prepare('SELECT COUNT(*) FROM ac_bookings WHERE ip_hash = ? AND created_at >= ?');
        $q->execute([$ipHash, date('c', time() - 3600)]);
        if ((int)$q->fetchColumn() >= 6) forma_json(['ok' => false, 'error' => $en ? 'Please try again later.' : 'Bitte versuch es später noch einmal.'], 429);
        try {
            forma_json(['ok' => true, 'url' => ac_checkout($pdo, $in, $ipHash)]);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Stripe')) { error_log('AC checkout: ' . $msg); $msg = $en ? 'Payment is not working right now. Please try again in a moment or send me a message.' : 'Die Bezahlung klappt gerade nicht. Bitte versuch es gleich noch einmal oder schreib mir.'; }
            forma_json(['ok' => false, 'error' => $msg], 400);
        }
    }
    forma_json(['ok' => false], 400);
}

$offer = ac_offer_by_token($pdo, (string)($_GET['angebot'] ?? ''));
forma_json(['open' => ac_free($pdo) > 0 && (bool)ac_cfg()['open'], 'offer_until' => ac_offer_live($offer) ? (int)$offer['offer_until'] : 0, 'now' => time()]);

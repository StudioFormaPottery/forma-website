<?php
// Movecia Academy: Buchung Teacher Training (Stripe), Rückkehr von Stripe, Kennenlerngespräch anfragen, Angebot aus dem Gespräch.
declare(strict_types=1);
require __DIR__ . '/academy-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
ac_schema($pdo);
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'academy');

// Rückkehr von Stripe
if (isset($_GET['paid'])) {
    $r = ac_booking($pdo, (int)$_GET['paid']);
    if (!$r || !hash_equals((string)$r['token'], (string)($_GET['k'] ?? ''))) mm_page('oh', 'Link nicht gültig · Link not valid', 'Dieser Link funktioniert leider nicht. Wenn du schon bezahlt hast, bekommst du in Kürze eine E-Mail von mir. · This link does not work. If you have already paid, you will receive an email from me shortly.');
    try { ac_check_paid($pdo, $r); } catch (Throwable $e) { error_log('AC paid: ' . $e->getMessage()); }
    $r = ac_booking($pdo, (int)$r['id']);
    $en = $r['lang'] === 'en';
    if ($r['status'] !== 'bezahlt') mm_page('oh', $en ? 'Almost there' : 'Fast geschafft', $en ? 'Your payment has not come through yet. If you have paid, you will get your confirmation by email in a few minutes.' : 'Deine Zahlung ist noch nicht angekommen. Wenn du bezahlt hast, bekommst du deine Bestätigung in ein paar Minuten per E-Mail.', '/academy/teacher-training/' . ($en ? '?lang=en' : ''), $en ? 'Back to the training' : 'Zurück zur Ausbildung');
    header('Location: /academy/teacher-training/?danke=1' . ($en ? '&lang=en' : ''), true, 303);
    exit;
}

$in = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ($_POST ?: (json_decode((string)file_get_contents('php://input'), true) ?: [])) : [];
$action = (string)($in['action'] ?? ($_GET['action'] ?? ''));
$en = ($in['lang'] ?? '') === 'en';

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

// Status für die Seite: freie Plätze (nur ja/nein), Countdown eines Gesprächsangebots
$offer = ac_offer_by_token($pdo, (string)($_GET['angebot'] ?? ''));
forma_json(['open' => ac_free($pdo) > 0 && (bool)ac_cfg()['open'], 'offer_until' => ac_offer_live($offer) ? (int)$offer['offer_until'] : 0, 'now' => time()]);

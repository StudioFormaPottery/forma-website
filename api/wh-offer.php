<?php
// Angebot am Ende eines Gatherings: Teilnehmerinnen fragen die Einblendung ab und buchen,
// Viv blendet aus ihrem Gastgeberinnen-Fenster ein und aus (mit dem geheimen Testraum-Token).
declare(strict_types=1);
require __DIR__ . '/movecia-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$pdo = forma_db();
mm_schema($pdo);
mm_offer_schema($pdo);
$out = function (array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };

$in = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : $_GET;
$act = (string)($in['action'] ?? 'get');
$k = (string)($in['k'] ?? '');
$t = (string)($in['t'] ?? '');

// Wer fragt? Teilnehmerin (persönlicher Link) oder Viv/Testgast (Testraum-Token)
$me = null; $scope = ''; $host = false;
if ($k !== '') {
    $s = $pdo->prepare("SELECT * FROM mm_signups WHERE token = ? AND status = 'angemeldet'");
    $s->execute([$k]);
    $me = $s->fetch() ?: null;
    if (!$me) $out(['ok' => false], 403);
    $scope = 'g:' . (int)$me['gathering_id'];
} elseif ($t !== '') {
    $tr = mm_cfg('testroom') ?? [];
    if (empty($tr['token']) || !hash_equals((string)$tr['token'], $t)) $out(['ok' => false], 403);
    $host = empty($in['gast']);
    $gid = (int)($in['g'] ?? 0);
    $scope = $gid ? 'g:' . $gid : 'test';
    if ($gid && !$host) $scope = 'test';
} else {
    $out(['ok' => false], 403);
}

if ($act === 'get') {
    $sp = mm_spotlight($pdo, $scope);
    $r = ['ok' => true, 'offer' => mm_spotlight_public($sp)];
    if ($host) {
        // offene Zahlungen dieses Raums ab und zu bei Stripe nachfragen (falls jemand das Fenster nach dem Bezahlen schließt)
        $chk = mm_cfg('ordercheck') ?? [];
        if (time() - (int)($chk['at'] ?? 0) > 20 && forma_stripe_key()) {
            mm_save_cfg('ordercheck', ['at' => time()]);
            $op = $pdo->prepare("SELECT * FROM mm_orders WHERE scope = ? AND status = 'offen' AND created_at >= ? LIMIT 5");
            $op->execute([$scope, date('c', time() - 3 * 3600)]);
            foreach ($op->fetchAll() as $o) { try { mm_order_check($pdo, $o); } catch (Throwable $e) { error_log('MM order poll: ' . $e->getMessage()); } }
        }
        $c = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(amount_cents),0) AS sum FROM mm_orders WHERE scope = ? AND status = 'bezahlt'");
        $c->execute([$scope]);
        $r['booked'] = $c->fetch();
        $r['stripe'] = forma_stripe_key() ? (forma_stripe_test_mode() ? 'test' : 'live') : 'none';
    }
    $out($r);
}

if ($act === 'show' || $act === 'hide') {
    if (!$host) $out(['ok' => false], 403);
    if ($act === 'hide') { mm_spotlight_hide($pdo, $scope); $out(['ok' => true, 'offer' => null]); }
    try {
        $sp = mm_spotlight_show($pdo, $scope, (string)($in['offer'] ?? ''), mm_euro_in((string)($in['special'] ?? '')), (int)($in['minutes'] ?? 25));
        // Eingaben als Vorschlag fürs nächste Mal merken
        $cfg = mm_cfg('offers') ?? [];
        $cfg[$sp['offer']] = array_merge(is_array($cfg[$sp['offer']] ?? null) ? $cfg[$sp['offer']] : [], ['special_cents' => (int)$sp['special_cents'], 'minutes' => max(1, (int)round(((int)$sp['until'] - (int)$sp['shown_at']) / 60))]);
        mm_save_cfg('offers', $cfg);
        $out(['ok' => true, 'offer' => mm_spotlight_public($sp)]);
    } catch (Throwable $e) {
        $out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($act === 'buy') {
    $sp = mm_spotlight($pdo, $scope);
    if (!$sp || (int)$sp['id'] !== (int)($in['id'] ?? 0)) $out(['ok' => false, 'error' => 'This offer is not available right now.'], 400);
    if (!forma_stripe_key()) $out(['ok' => false, 'error' => 'Online payment is not set up yet. Please write to ' . MM_FROM_EMAIL . ' and Viv will send you a payment link.'], 400);
    try {
        $url = mm_order_checkout($pdo, $sp, $me ? (string)$me['name'] : '', $me ? (string)$me['email'] : '');
        $out(['ok' => true, 'redirect' => $url]);
    } catch (Throwable $e) {
        error_log('MM order Stripe: ' . $e->getMessage());
        $out(['ok' => false, 'error' => 'The payment page is not reachable right now. Please try again in a moment.'], 502);
    }
}
$out(['ok' => false], 400);

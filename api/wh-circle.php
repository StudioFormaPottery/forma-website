<?php
// Inner Circle direkt buchen (Einladungslink /circle/?ref=CODE oder ohne Einladung zum regulären Preis).
declare(strict_types=1);
require __DIR__ . '/movecia-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$pdo = forma_db();
mm_schema($pdo);
mm_offer_schema($pdo);
try { mm_send_ref_mails($pdo); mm_send_stay_mails($pdo); } catch (Throwable $e) { error_log('MM circle mails: ' . $e->getMessage()); }
$out = function (array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (!empty($in['website'])) $out(['ok' => true]);
// Bleibe-Code einlösen (Mail „A little gift, if you would like to stay“)
if (($in['action'] ?? '') === 'stay') {
    $r = mm_stay_order($pdo, (string)($in['stay'] ?? ''));
    if (!$r) $out(['ok' => false, 'error' => 'This code is no longer valid. You can still join at the regular price.'], 400);
    if (!forma_stripe_key()) $out(['ok' => false, 'error' => 'Please write to ' . MM_FROM_EMAIL . '.'], 400);
    try {
        $res = mm_stay_redeem($pdo, $r, empty($in['consent']) ? 0 : 1, empty($in['digital']) ? 0 : 1);
        $out(['ok' => true] + $res);
    } catch (Throwable $e) {
        error_log('MM stay: ' . $e->getMessage());
        $out(['ok' => false, 'error' => 'Something went wrong just now. Please try again in a moment.'], 502);
    }
}
if ((int)($in['elapsed'] ?? 0) < 2500) $out(['ok' => false, 'error' => 'One moment please, then try again.'], 400);
$name = trim(mb_substr((string)($in['name'] ?? ''), 0, 80));
$email = strtolower(trim(mb_substr((string)($in['email'] ?? ''), 0, 160)));
if (mb_strlen($name) < 2) $out(['ok' => false, 'error' => 'Please add your first name.'], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $out(['ok' => false, 'error' => 'Please check your email address.'], 400);
if (empty($in['consent'])) $out(['ok' => false, 'error' => 'Please tick the first box so your access can start right away.'], 400);
if (!forma_stripe_key()) $out(['ok' => false, 'error' => 'Online payment is not set up yet. Please write to ' . MM_FROM_EMAIL . '.'], 400);
$o = mm_offers()['circle'];
$ref = mm_ref_order($pdo, (string)($in['ref'] ?? ''));
if ($ref && strtolower((string)$ref['email']) === $email) $ref = null; // sich selbst einladen zählt nicht
$sp = ['id' => 0, 'scope' => 'circle', 'offer' => 'circle', 'title' => $o['title'], 'text' => $o['text'], 'billing' => 'monthly',
    'regular_cents' => $o['price_cents'], 'special_cents' => $o['special_cents'], 'until' => 0, 'items' => '', 'referred_by' => $ref ? (int)$ref['id'] : 0];
try {
    $plan = !$ref && in_array($in['plan'] ?? '', ['p6', 'p12'], true) ? (string)$in['plan'] : 'full';
    $url = mm_order_checkout($pdo, $sp, $name, $email, '', $plan, 1, empty($in['digital']) ? 0 : 1);
    $out(['ok' => true, 'redirect' => $url]);
} catch (Throwable $e) {
    error_log('MM circle checkout: ' . $e->getMessage());
    $out(['ok' => false, 'error' => 'The payment page is not reachable right now. Please try again in a moment.'], 502);
}

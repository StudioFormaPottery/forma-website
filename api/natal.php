<?php
// Movecia · Prä-/Postnatal Training: Paket buchen (→ Stripe), erneut bezahlen, Wunschtermine anfragen, Kalenderdatei.
declare(strict_types=1);
require __DIR__ . '/natal-lib.php';
$pdo = forma_db();
natal_schema($pdo);
natal_bonus_tick($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['ics'])) {
    $o = natal_order_auth($pdo, (int)($_GET['o'] ?? 0), (string)($_GET['k'] ?? ''));
    if (!$o) { http_response_code(404); exit('Nicht gefunden.'); }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="Movecia-Termine.ics"');
    echo natal_ics(natal_sessions($pdo, (int)$o['id']), '', natal_is_online($o));
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
$in = $_POST;
if (!empty($in['website'])) forma_json(['ok' => true]);
$action = (string)($in['action'] ?? '');
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'natal');

if ($action === 'subscribe') {
    $loaded = (int)($in['t'] ?? 0);
    if ($loaded > 0 && ((int)(microtime(true) * 1000) - $loaded) < 2000) forma_json(['ok' => false, 'error' => 'Einen Moment bitte, dann versuch es noch einmal.'], 429);
    $name = forma_clean((string)($in['name'] ?? ''), 80);
    $email = strtolower(forma_clean((string)($in['email'] ?? ''), 160));
    if (mb_strlen($name) < 2) forma_json(['ok' => false, 'error' => 'Bitte gib deinen Vornamen ein.'], 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) forma_json(['ok' => false, 'error' => 'Bitte prüf deine E-Mail-Adresse.'], 422);
    if (empty($in['consent'])) forma_json(['ok' => false, 'error' => 'Bitte setz das Häkchen, damit ich dir schreiben darf.'], 422);
    $c = $pdo->prepare('SELECT COUNT(*) FROM mm_natal_subs WHERE ip_hash = ? AND created_at >= ?');
    $c->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$c->fetchColumn() >= 6) forma_json(['ok' => false, 'error' => 'Zu viele Anmeldungen von hier. Bitte versuch es später noch einmal.'], 429);
    $mode = ($in['mode'] ?? '') === 'online' ? 'online' : 'studio';
    $source = in_array($in['source'] ?? '', ['natal', 'bio'], true) ? $in['source'] : 'natal';
    forma_json(natal_news_subscribe($pdo, $name, $email, $mode, $source, $ipHash));
}

// Movecia-Gutschein prüfen (Vivien, 31.10.2026): zeigt den Restwert, abgerechnet wird erst beim Buchen
if ($action === 'voucher') {
    $r = mv_voucher_lookup($pdo, (string)($in['code'] ?? ''));
    if (!$r['ok']) forma_json(['ok' => false, 'error' => $r['error']], 422);
    forma_json(['ok' => true, 'code' => $r['voucher']['code'], 'nr' => $r['voucher']['nr'], 'balance' => $r['balance']]);
}

if ($action === 'buy') {
    $loaded = (int)($in['t'] ?? 0);
    if ($loaded > 0 && ((int)(microtime(true) * 1000) - $loaded) < 3000) forma_json(['ok' => false, 'error' => 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);
    $packs = natal_packages();
    $key = (string)($in['package'] ?? '');
    // Mix & Match (Vivien, 30.10.2026): nur über ein persönliches Angebot, Zusammensetzung und Preis kommen vom Server
    $mixOffer = null;
    if (str_starts_with($key, 'x_') && ($in['a'] ?? '') !== '') {
        $q = $pdo->prepare('SELECT * FROM mm_natal_offers WHERE token = ?');
        $q->execute([(string)$in['a']]);
        $mixOffer = ($q->fetch() ?: null);
        // Sie wählt die Aufteilung selbst (Vivien, 30.10.2026): Gesamtzahl bleibt, Online-Anzahl aus dem Formular
        $mx0 = $mixOffer ? natal_offer_mix($mixOffer) : null;
        $mx = $mx0 ? natal_mix_calc($mx0['base'], $mx0['units'] - max(0, min($mx0['units'], (int)($in['mix_online'] ?? $mx0['online']))), max(0, min($mx0['units'], (int)($in['mix_online'] ?? $mx0['online'])))) : null;
        if ($mx && $mx['package'] === $key) $packs[$key] = $mx + ['unit_cents' => $mx['studio'] ? $mx['unit_studio'] : $mx['unit_online'], 'mode' => 'mix'];
        else $mixOffer = null;
    }
    $name = forma_clean((string)($in['name'] ?? ''), 120);
    $email = strtolower(forma_clean((string)($in['email'] ?? ''), 160));
    $phone = forma_clean((string)($in['phone'] ?? ''), 40);
    $due = (string)($in['due_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || !strtotime($due)) $due = '';
    $note = trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($in['note'] ?? '')) ?? '', 0, 1000));
    $err = [];
    if (!isset($packs[$key])) $err[] = 'Bitte wähl ein Paket aus.';
    if (mb_strlen($name) < 2) $err[] = 'Bitte gib deinen Namen an.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
    if (mb_strlen(preg_replace('/\D/', '', $phone)) < 6) $err[] = 'Bitte gib deine Telefonnummer an, damit ich dich bei Terminfragen erreiche.';
    if (empty($in['consent'])) $err[] = 'Bitte bestätige die Hinweise zum Datenschutz.';
    [$health, $hErr] = natal_health_clean($in);
    $err = array_merge($err, $hErr);
    if (empty($in['start_now'])) $err[] = 'Bitte bestätige, dass die Termine schon in der Rücktrittsfrist beginnen dürfen.';
    if ($err) forma_json(['ok' => false, 'error' => implode(' ', $err)], 422);
    $code = trim((string)($in['code'] ?? ''));
    $vr = $code !== '' ? mv_voucher_lookup($pdo, $code) : null;
    if ($vr && !$vr['ok']) forma_json(['ok' => false, 'error' => $vr['error']], 422);
    $c = $pdo->prepare('SELECT COUNT(*) FROM mm_natal_orders WHERE ip_hash = ? AND created_at >= ?');
    $c->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$c->fetchColumn() >= 8) forma_json(['ok' => false, 'error' => 'Bitte versuch es später noch einmal oder ruf mich an: ' . NATAL_COMPANY['phone']], 429);
    $p = $packs[$key];
    // Kennenlern-Bonus: Preis pro Einheit bestimmt der Server
    $bonusCall = ($in['bonus'] ?? '') !== '' ? natal_bonus_valid($pdo, (string)$in['bonus']) : null;
    $bp = $bonusCall && !$mixOffer ? (natal_bonus_prices(natal_bonus_cfg()['percent'])[$key] ?? null) : null;
    if ($bp) { $p['unit_cents'] = $bp['unit']; $p['title'] .= ' · Kennenlern-Bonus'; }
    $wishes = natal_clean_wishes((string)($in['wishes'] ?? '[]'));
    $wishes = $mixOffer ? natal_wishes_fit($wishes, ['studio' => $p['studio'], 'online' => $p['online']]) : array_slice($wishes, 0, $p['units']);
    $amount = $mixOffer ? (int)$p['cents'] : $p['unit_cents'] * $p['units'];
    $offerId = null;
    if (($in['a'] ?? '') !== '') {
        $q = $pdo->prepare('SELECT id FROM mm_natal_offers WHERE token = ?');
        $q->execute([(string)$in['a']]);
        $offerId = ($q->fetchColumn() ?: null);
    }
    $tok = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO mm_natal_orders (offer_id, package, title, units, unit_cents, amount_cents, name, email, phone, due_date, note, wishes, token, ip_hash, consent_start, created_at, mix_online) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)')
        ->execute([$offerId, $key, $p['title'], $p['units'], $p['unit_cents'], $amount, $name, $email, $phone, $due, $note, json_encode($wishes), $tok, $ipHash, date('c'), $mixOffer ? (int)$p['online'] : 0]);
    $o = natal_order($pdo, (int)$pdo->lastInsertId());
    natal_health_save($pdo, (int)$o['id'], $health);
    if ($bp) { $pdo->prepare('UPDATE mm_natal_orders SET bonus_call_id = ? WHERE id = ?')->execute([$bonusCall['id'], $o['id']]); $o['bonus_call_id'] = $bonusCall['id']; }
    if ($vr) {
        // Gutschein reservieren (in einer Transaktion, damit derselbe Restwert nicht zweimal verwendet wird)
        $pdo->exec('BEGIN IMMEDIATE');
        $v = $pdo->query('SELECT * FROM mm_vouchers WHERE id = ' . (int)$vr['voucher']['id'])->fetch();
        $vc = min(mv_voucher_balance($pdo, $v), $amount);
        if ($vc > 0) {
            $pdo->prepare("INSERT INTO mm_voucher_uses (voucher_id, order_id, cents, status, created_at) VALUES (?,?,?,'reserviert',?)")->execute([$v['id'], $o['id'], $vc, date('c')]);
            $pdo->prepare('UPDATE mm_natal_orders SET voucher_id = ?, voucher_cents = ? WHERE id = ?')->execute([$v['id'], $vc, $o['id']]);
        }
        $pdo->exec('COMMIT');
        if ($vc <= 0) { $pdo->prepare("UPDATE mm_natal_orders SET status = 'abgebrochen' WHERE id = ?")->execute([$o['id']]); forma_json(['ok' => false, 'error' => 'Dieser Gutschein ist gerade schon vollständig verwendet.'], 422); }
        $o = natal_order($pdo, (int)$o['id']);
        // Deckt der Gutschein alles, ist die Buchung sofort fix, ganz ohne Stripe
        if ($vc >= $amount) {
            natal_paid_now($pdo, $o);
            forma_json(['ok' => true, 'redirect' => mm_site() . '/api/natal-bezahlt.php?o=' . $o['id'] . '&k=' . $o['token']]);
        }
    }
    if (!forma_stripe_key()) forma_json(['ok' => false, 'error' => 'Die Online-Zahlung ist gerade nicht erreichbar. Bitte schreib mir an ' . MM_FROM_EMAIL . '.'], 503);
    try {
        forma_json(['ok' => true, 'redirect' => natal_checkout($pdo, $o)]);
    } catch (Throwable $e) {
        error_log('Natal buy: ' . $e->getMessage());
        $pdo->prepare("UPDATE mm_natal_orders SET status = 'abgebrochen' WHERE id = ?")->execute([$o['id']]);
        forma_json(['ok' => false, 'error' => 'Die Online-Zahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.'], 502);
    }
}

if ($action === 'call') {
    $loaded = (int)($in['t'] ?? 0);
    if ($loaded > 0 && ((int)(microtime(true) * 1000) - $loaded) < 3000) forma_json(['ok' => false, 'error' => 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);
    $date = (string)($in['date'] ?? '');
    $slot = (string)($in['slot'] ?? '');
    $name = forma_clean((string)($in['name'] ?? ''), 120);
    $email = strtolower(forma_clean((string)($in['email'] ?? ''), 160));
    $phone = forma_clean((string)($in['phone'] ?? ''), 40);
    $note = trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($in['note'] ?? '')) ?? '', 0, 600));
    $err = [];
    if (!natal_call_bookable($date, $slot)) $err[] = 'Bitte wähl einen freien Termin im Kalender.';
    if (mb_strlen($name) < 2) $err[] = 'Bitte gib deinen Namen an.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
    if (empty($in['consent'])) $err[] = 'Bitte bestätige die Hinweise zum Datenschutz.';
    if ($err) forma_json(['ok' => false, 'error' => implode(' ', $err)], 422);
    natal_call_schema($pdo);
    $c = $pdo->prepare('SELECT COUNT(*) FROM mm_natal_calls WHERE ip_hash = ? AND created_at >= ?');
    $c->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$c->fetchColumn() >= 4) forma_json(['ok' => false, 'error' => 'Bitte versuch es später noch einmal.'], 429);
    $offerId = null;
    if (($in['a'] ?? '') !== '') { $q = $pdo->prepare('SELECT id FROM mm_natal_offers WHERE token = ?'); $q->execute([(string)$in['a']]); $offerId = ($q->fetchColumn() ?: null); }
    try {
        $r = natal_book_call($pdo, ['date' => $date, 'slot' => $slot, 'name' => $name, 'email' => $email, 'phone' => $phone, 'note' => $note, 'mode' => ($in['mode'] ?? '') === 'online' ? 'online' : 'studio', 'offer_id' => $offerId, 'ip_hash' => $ipHash]);
    } catch (Throwable $e) {
        error_log('Natal call: ' . $e->getMessage());
        forma_json(['ok' => false, 'error' => 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.'], 500);
    }
    forma_json($r, $r['ok'] ? 200 : 409);
}

$o = natal_order_auth($pdo, (int)($in['o'] ?? 0), (string)($in['k'] ?? ''));
if (!$o) forma_json(['ok' => false, 'error' => 'Dieser Link funktioniert leider nicht.'], 404);

if ($action === 'pay') {
    if ($o['status'] !== 'offen') forma_json(['ok' => true, 'redirect' => natal_link($o)]);
    try {
        forma_json(['ok' => true, 'redirect' => natal_checkout($pdo, $o)]);
    } catch (Throwable $e) {
        error_log('Natal pay: ' . $e->getMessage());
        forma_json(['ok' => false, 'error' => 'Die Online-Zahlung ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.'], 502);
    }
}

if ($action === 'health') {
    if ($o['status'] !== 'bezahlt') forma_json(['ok' => false, 'error' => 'Das geht erst nach der Zahlung.'], 409);
    [$health, $hErr] = natal_health_clean($in);
    if ($hErr) forma_json(['ok' => false, 'error' => implode(' ', $hErr)], 422);
    $first = natal_health_get($o) === null;
    natal_health_save($pdo, (int)$o['id'], $health);
    $fl = natal_health_flags($health);
    $red = count(array_filter($fl, fn($x) => $x[0] === 'rot'));
    natal_mail(natal_notify_email(), 'Gesundheitsfragebogen ' . ($first ? 'ausgefüllt' : 'geändert') . ': ' . $o['name'], 'Gesundheitsfragebogen', [
        $o['name'] . ' hat ihren Gesundheitsfragebogen ' . ($first ? 'ausgefüllt' : 'geändert') . '.',
        $red ? '⚠ ' . $red . ($red === 1 ? ' Hinweis braucht' : ' Hinweise brauchen') . ' eine ärztliche Freigabe. Die Details stehen nur in der Verwaltung.' : 'Die Details stehen in der Verwaltung.',
    ], [natal_admin_link() . '&k=' . (int)$o['id'] . '#k' . (int)$o['id'], 'In der Verwaltung ansehen']);
    forma_json(['ok' => true]);
}

if ($action === 'wish') {
    if ($o['status'] !== 'bezahlt') forma_json(['ok' => false, 'error' => 'Termine kannst du nach der Zahlung auswählen.'], 409);
    $wishes = natal_clean_wishes((string)($in['wishes'] ?? '[]'));
    if (!$wishes) forma_json(['ok' => false, 'error' => 'Bitte wähl mindestens einen Tag im Kalender aus.'], 422);
    $note = trim(mb_substr(preg_replace("/[\x00-\x09\x0B-\x1F\x7F]/u", '', (string)($in['note'] ?? '')) ?? '', 0, 400));
    try {
        $new = natal_add_wishes($pdo, $o, $wishes, $note);
    } catch (Throwable $e) {
        error_log('Natal wish: ' . $e->getMessage());
        forma_json(['ok' => false, 'error' => 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.'], 500);
    }
    if (!$new) forma_json(['ok' => false, 'error' => 'Für diese Tage hast du schon angefragt oder es sind keine Einheiten mehr frei.'], 409);
    forma_json(['ok' => true, 'count' => count($new)]);
}
forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 400);

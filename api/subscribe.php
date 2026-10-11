<?php
// Eintrag in die E-Mail-Liste (mit Bestätigungslink, Double-Opt-in).
declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    forma_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
}
$in = $_POST;
if (!empty($in['website'])) {
    forma_json(['ok' => true]);
}
$loadedAt = (int)($in['t'] ?? 0);
if ($loadedAt > 0 && ((int)(microtime(true) * 1000) - $loadedAt) < 2000) {
    forma_json(['ok' => false, 'error' => 'Bitte versuch es in ein paar Sekunden noch einmal.'], 429);
}
$email = mb_strtolower(forma_clean((string)($in['email'] ?? ''), 160));
$name = forma_clean((string)($in['name'] ?? ''), 80);
$source = forma_clean((string)($in['source'] ?? ''), 120);
if (mb_strlen($name) < 2) {
    forma_json(['ok' => false, 'error' => 'Bitte gib noch deinen Vornamen an.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    forma_json(['ok' => false, 'error' => 'Bitte gib eine gültige E-Mail-Adresse an.'], 422);
}
if (empty($in['consent'])) {
    forma_json(['ok' => false, 'error' => 'Bitte bestätige noch, dass ich dir schreiben darf.'], 422);
}

try {
    $pdo = forma_db();
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'forma');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM subscribers WHERE ip_hash = ? AND created_at >= ?');
    $stmt->execute([$ipHash, date('c', time() - 3600)]);
    if ((int)$stmt->fetchColumn() >= 5) {
        forma_json(['ok' => false, 'error' => 'Bitte versuch es später noch einmal.'], 429);
    }
    $stmt = $pdo->prepare('SELECT * FROM subscribers WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    $isMaker = str_contains($source, 'Maker');
    if ($row && $row['status'] === 'bestaetigt') {
        if ($isMaker) {
            if (!str_contains($row['source'], 'Maker')) {
                $pdo->prepare('UPDATE subscribers SET source = ? WHERE id = ?')->execute([trim($row['source'] . ', Maker Club', ', '), $row['id']]);
                forma_ml_sync($email, $row['name'], true, true);
            }
            $m = forma_maker($pdo);
            forma_mail($email, 'Dein Template des Monats von FORMA', "Hallo,\n\nschön, dass du im FORMA Maker Club bist! Hier ist dein Gratis-Template des Monats" . ($m['title'] ? " („{$m['title']}“)" : '') . ":\n\n" . forma_maker_link($row) . "\n\nViel Freude beim Töpfern!\nVivien\nFORMA Earth & Art\n");
            forma_json(['ok' => true, 'already' => true, 'maker' => true]);
        }
        forma_json(['ok' => true, 'already' => true]);
    }
    $token = bin2hex(random_bytes(20));
    if ($row) {
        $pdo->prepare("UPDATE subscribers SET token = ?, status = 'offen', name = ?, source = ?, ip_hash = ?, created_at = ? WHERE id = ?")
            ->execute([$token, $name ?: $row['name'], $source, $ipHash, date('c'), $row['id']]);
    } else {
        $pdo->prepare("INSERT INTO subscribers (email, name, source, status, token, ip_hash, created_at) VALUES (?, ?, ?, 'offen', ?, ?, ?)")
            ->execute([$email, $name, $source, $token, $ipHash, date('c')]);
    }
} catch (Throwable $e) {
    error_log('FORMA subscribe: ' . $e->getMessage());
    forma_json(['ok' => false, 'error' => 'Das hat gerade nicht geklappt. Bitte versuch es später noch einmal.'], 500);
}

$hello = $name !== '' ? 'Hallo ' . forma_first_name($name) . ',' : 'Hallo,';
$link = forma_site_url() . '/api/confirm.php?t=' . $token;
$isMaker = str_contains($source, 'Maker');
$body = "$hello\n\n" . ($isMaker ? "schön, dass du in den FORMA Maker Club möchtest! Jeden Monat bekommst du ein Pottery-Template zum Selbermachen gratis von mir.\n\n" : "schön, dass du von neuen Kursen und Specials im FORMA Studio hören möchtest!" . ($source !== '' ? " Sobald die Termine für „{$source}“ feststehen, erfährst du es als Erste." : '') . "\n\n")
    . "Bitte bestätige kurz deine E-Mail-Adresse mit einem Klick auf diesen Link" . ($isMaker ? '. Gleich danach kannst du dein Gratis-Template herunterladen' : '') . ":\n\n$link\n\n"
    . "Erst danach stehst du auf meiner Liste. Falls du dich nicht eingetragen hast, ignoriere diese E-Mail einfach, dann passiert nichts.\n\n"
    . "Bis bald,\nVivien\nFORMA Earth & Art\nwww.forma-pottery.com\n"
    . ($isMaker ? '' : "\nP.S. Kennst du schon den FORMA Maker Club? Jeden Monat ein Pottery-Template gratis, dazu kleine Rezepte zur Jahreszeit und DIY-Ideen wie Räucherbündel: " . forma_site_url() . "/#templates\n");
forma_mail($email, $isMaker ? 'Bitte bestätigen: dein Gratis-Template wartet' : 'Bitte bestätige deine E-Mail-Adresse', $body);
forma_json(['ok' => true, 'confirm' => true]);

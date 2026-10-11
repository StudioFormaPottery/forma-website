<?php
// Bestätigungslink aus der Mail: Eintrag in die E-Mail-Liste abschließen.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$token = (string)($_GET['t'] ?? '');
$pdo = forma_db();
$stmt = $pdo->prepare('SELECT * FROM subscribers WHERE token = ?');
$stmt->execute([$token]);
$s = $token !== '' ? $stmt->fetch() : false;
if (!$s) {
    forma_message_page('Link nicht gefunden', 'Dieser Link ist leider nicht mehr gültig. Trag dich auf der Website einfach noch einmal ein.');
}
if ($s['status'] !== 'bestaetigt') {
    $pdo->prepare("UPDATE subscribers SET status = 'bestaetigt', confirmed_at = ? WHERE id = ?")->execute([date('c'), $s['id']]);
    forma_ml_sync($s['email'], $s['name'], true, str_contains($s['source'], 'Maker'));
    $count = (int)$pdo->query("SELECT COUNT(*) FROM subscribers WHERE status = 'bestaetigt'")->fetchColumn();
    forma_mail(FORMA_NOTIFY_EMAIL, 'Neu auf deiner E-Mail-Liste: ' . $s['email'],
        "Neue Eintragung auf deiner E-Mail-Liste\n\nE-Mail: {$s['email']}\nName: {$s['name']}\nÜber: {$s['source']}\n\nDeine Liste hat jetzt $count Kontakte.\nAlle Kontakte: " . forma_site_url() . "/verwaltung/?view=liste\n" . (forma_mailerlite() ? "Die Adresse ist auch in MailerLite in der Gruppe „Website“ eingetragen.\n" : ''));
}
if (str_contains($s['source'], 'Maker')) {
    $m = forma_maker($pdo);
    forma_message_page('Willkommen im Maker Club!', "Danke, deine E-Mail-Adresse ist bestätigt. Ab jetzt bekommst du jeden Monat ein Gratis-Template von mir." . ($m['file'] !== '' ? "\nHier ist gleich das Template des Monats" . ($m['title'] ? ": {$m['title']}" : '') . '.' : "\nDas erste Template schicke ich dir, sobald es fertig ist."), $m['file'] !== '' ? 'Template herunterladen' : '', $m['file'] !== '' ? forma_maker_link($s) : '');
}
forma_message_page('Du bist dabei!', "Danke, deine E-Mail-Adresse ist bestätigt. Sobald es neue Kurse oder Specials gibt, schreibe ich dir als Erstes.\n\nPsst, kennst du schon den FORMA Maker Club? Jeden Monat ein Pottery-Template gratis, dazu kleine Rezepte zur Jahreszeit und DIY-Ideen wie Räucherbündel.", 'Zum Maker Club', '/#templates');

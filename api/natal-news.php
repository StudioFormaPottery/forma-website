<?php
// Newsletter Prä- & Postnatal: Bestätigungslink (?t=) und Abmelden (?ab=). Danach zurück auf moveciamovement.com/natal.
declare(strict_types=1);
require __DIR__ . '/natal-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
natal_schema($pdo);
$back = mm_site() . '/natal/';

if (isset($_GET['ab'])) {
    $sub = natal_sub_by_token($pdo, (string)$_GET['ab']);
    if ($sub && $sub['status'] !== 'abgemeldet') {
        $pdo->prepare("UPDATE mm_natal_subs SET status = 'abgemeldet', unsub_at = ? WHERE id = ?")->execute([date('c'), $sub['id']]);
        natal_news_ml($pdo, $sub, false);
    }
    header('Location: ' . $back . '?news=ab', true, 302);
    exit;
}

$sub = natal_sub_by_token($pdo, (string)($_GET['t'] ?? ''));
if (!$sub) { header('Location: ' . $back . '?news=alt', true, 302); exit; }
if ($sub['status'] !== 'bestaetigt') {
    $u = $pdo->prepare("UPDATE mm_natal_subs SET status = 'bestaetigt', confirmed_at = ? WHERE id = ? AND status <> 'bestaetigt'");
    $u->execute([date('c'), $sub['id']]);
    if ($u->rowCount() === 1) {
        natal_news_welcome($sub);
        natal_news_ml($pdo, $sub);
        natal_mail(natal_notify_email(), 'Neue Anmeldung Prä- & Postnatal: ' . $sub['name'], 'Neue Interessentin',
            [$sub['name'] . ' (' . $sub['email'] . ') hat sich für Infos zum Prä- und Postnatal-Training angemeldet und ihre Adresse bestätigt.' . ($sub['mode'] === 'online' ? ' Sie hat sich die Online-Variante angesehen.' : ''), 'Sie hat gerade die Mail mit allen Paketen bekommen.'],
            [natal_admin_link() . '#newsletter', 'In der Verwaltung']);
    }
}
header('Location: ' . $back . '?news=ok' . ($sub['mode'] === 'online' ? '&m=online' : ''), true, 302);
exit;

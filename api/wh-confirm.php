<?php
// Wild Hearts: Bestätigungslink aus der Mail. Danach Willkommensmail mit Download und Übertrag nach MailerLite.
declare(strict_types=1);
require __DIR__ . '/movecia-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
$sub = mm_sub_by_token($pdo, (string)($_GET['t'] ?? ''));
if (!$sub) mm_page('hmm…', 'Link not found', 'This link doesn\'t work anymore. Simply sign up again on moveciamovement.com, it only takes a moment.', '/', 'Back to moveciamovement.com');
if ($sub['status'] !== 'bestaetigt') {
    $u = $pdo->prepare("UPDATE mm_subscribers SET status = 'bestaetigt', confirmed_at = ? WHERE id = ? AND status <> 'bestaetigt'");
    $u->execute([date('c'), $sub['id']]);
    if ($u->rowCount() === 1) {
        mm_mail_welcome($sub);
        mm_ml_push($pdo, $sub);
        if (in_array($sub['source'], ['mentoring', 'year', 'circle'], true)) {
            // Viv bekommt Bescheid, wenn jemand Interesse an einem bezahlten Angebot zeigt
            $what = ['mentoring' => 'Mentoring für ihr Herzensbusiness', 'year' => 'die Jahresreise', 'circle' => 'den Inner Circle (Warteliste)'][$sub['source']];
            $t = "Hallo Viv,\n\n{$sub['name']} ({$sub['email']}) interessiert sich für $what und hat ihre E-Mail-Adresse gerade bestätigt.\nSprache: " . ($sub['lang'] === 'de' ? 'Deutsch' : 'Englisch') . "\n\nDu siehst sie in der Verwaltung unter Movecia Movement → E-Mail-Liste.\n";
            mm_mail(MM_FROM_EMAIL, 'Neue Interessentin: ' . $sub['name'] . ' · ' . $what, $t, mm_mail_html('Neue Interessentin', nl2br(forma_h($t)), 'de'));
        }
    }
}
header('Location: /reset/?ok=1' . ($sub['lang'] === 'de' ? '&lang=de' : ''), true, 302);
exit;

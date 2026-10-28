<?php
// Wild Hearts: Bestätigungslink aus der Mail. Danach Willkommensmail mit Download und Übertrag nach MailerLite.
declare(strict_types=1);
require __DIR__ . '/movecia-lib.php';
header('Cache-Control: no-store');
$pdo = forma_db();
$sub = mm_sub_by_token($pdo, (string)($_GET['t'] ?? ''));
if (!$sub) mm_page('hmm…', 'Link not found', 'This link no longer works. Simply sign up again on moveciamovement.com, it only takes a moment.', '/', 'Back to moveciamovement.com');
if ($sub && $sub['source'] === 'teacher') $sub['source'] = 'academy'; // frühere Teaser-Seite /teacher-training/ → Academy-Liste
if ($sub['status'] !== 'bestaetigt') {
    $u = $pdo->prepare("UPDATE mm_subscribers SET status = 'bestaetigt', confirmed_at = ? WHERE id = ? AND status <> 'bestaetigt'");
    $u->execute([date('c'), $sub['id']]);
    if ($u->rowCount() === 1) {
        if ($sub['source'] === 'academy') { require_once __DIR__ . '/academy-lib.php'; ac_interest_info_mail($sub); }
        elseif ($sub['source'] === 'unfold') mm_mail_unfold_info($sub); else mm_mail_welcome($sub);
        mm_ml_push($pdo, $sub);
        if (in_array($sub['source'], ['mentoring', 'year', 'circle', 'retreat', 'unfold', 'academy'], true)) mm_notify_interest($sub, (string)$sub['source']);
    }
}
if ($sub['source'] === 'academy') { header('Location: /academy/teacher-training/?liste=1#liste', true, 302); exit; }
if ($sub['source'] === 'unfold') { header('Location: /unfold/?ok=1&lang=' . ($sub['lang'] === 'de' ? 'de' : 'en') . '#infos', true, 302); exit; }
header('Location: /reset/?ok=1' . ($sub['lang'] === 'de' ? '&lang=de' : ''), true, 302);
exit;

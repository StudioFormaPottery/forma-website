<?php
// Liefert das aktuelle Saison-Pop-up (oder nichts).
declare(strict_types=1);
require __DIR__ . '/lib.php';

try {
    $p = forma_popup(forma_db());
    $preview = isset($_GET['vorschau']);
    if (!$preview && !forma_popup_live($p)) {
        forma_json(['ok' => true, 'popup' => null]);
    }
    forma_json(['ok' => true, 'popup' => [
        'id' => 'p' . (int)$p['version'],
        'eyebrow' => $p['eyebrow'],
        'title' => $p['title'],
        'text' => $p['text'],
        'date_line' => $p['date_line'],
        'signup' => (bool)$p['signup'],
        'button_text' => $p['button_text'],
        'button_link' => $p['button_link'],
        'image' => $p['image'] !== '' ? 'api/popup-image.php?v=' . (int)$p['version'] : '',
    ]]);
} catch (Throwable $e) {
    error_log('FORMA popup: ' . $e->getMessage());
    forma_json(['ok' => true, 'popup' => null]);
}

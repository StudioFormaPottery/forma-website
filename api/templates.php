<?php
// Öffentliche Liste der Templates und das aktuelle Template des Monats.
declare(strict_types=1);
require __DIR__ . '/lib.php';
try {
    $pdo = forma_db();
    $online = forma_stripe_key() !== null && forma_setting($pdo, 'shop_open') === '1';
    $out = [];
    foreach ($pdo->query('SELECT * FROM templates WHERE visible = 1 ORDER BY sort, id DESC')->fetchAll() as $t) {
        $out[] = ['id' => (int)$t['id'], 'title' => $t['title'], 'description' => $t['description'], 'season' => $t['season'],
            'season_label' => FORMA_SEASONS[$t['season']] ?? '', 'age' => $t['age'], 'age_label' => FORMA_AGES[$t['age']] ?? '',
            'level' => (int)$t['level'], 'price_cents' => (int)$t['price_cents'],
            'image' => $t['image'] !== '' ? 'api/template-image.php?id=' . (int)$t['id'] . '&v=' . substr(md5($t['image'] . '|' . (string)@filemtime(forma_template_dir() . '/' . basename((string)$t['image']))), 0, 8) : '', 'buyable' => $online && $t['file'] !== '', 'langs' => $t['file_en'] !== '' ? 'Deutsch & Englisch' : '', 'slides' => forma_template_slides((string)$t['file'])];
    }
    $m = forma_maker($pdo);
    forma_json(['ok' => true, 'templates' => $out, 'maker' => ['title' => $m['title'], 'text' => $m['text'],
        'image' => $m['image'] !== '' ? 'api/template-image.php?maker=' . (int)$m['version'] : '', 'ready' => $m['file'] !== '']]);
} catch (Throwable $e) {
    error_log('FORMA templates: ' . $e->getMessage());
    forma_json(['ok' => false], 500);
}

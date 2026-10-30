<?php
// Liefert das aktuelle Saison-Pop-up (oder nichts).
declare(strict_types=1);
require __DIR__ . '/lib.php';

try {
    $pdo = forma_db();
    $p = forma_popup($pdo);
    $preview = isset($_GET['vorschau']);
    // Halloween (Vivien, 30.10.2026): jedes Jahr vom 30.10. bis 1.11. automatisch ein Halloween-Pop-up für Pottery bemalen,
    // außer die Deko ist in der Verwaltung ausgeschaltet. Ab 2.11. wieder das normale Pop-up.
    $md = (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('m-d');
    $hwOn = ($md >= '10-30' && $md <= '10-31') || $md === '11-01';
    if ((($hwOn && !$preview && (forma_setting($pdo, 'deko') ?? 'auto') !== 'aus') || ($preview && ($_GET['thema'] ?? '') === 'halloween'))) {
        forma_json(['ok' => true, 'popup' => [
            'id' => 'hw' . date('Y'),
            'eyebrow' => 'Halloween im Atelier',
            'title' => 'Gruselig schön: Pottery bemalen',
            'text' => '',
            'date_line' => 'Ab November · Mi & Do 15–18 Uhr',
            'signup' => true,
            'button_text' => 'Platz reservieren',
            'button_link' => '#pottery-bemalen',
            'image' => 'img/bemalen-hand.jpg',
            'theme' => 'halloween',
        ]]);
    }
    // Weihnachtszeit (Vivien, 30.10.2026): vom 2.11. bis 23.12. zwei weihnachtliche Pop-ups im Wechsel –
    // Adventkalender zum Selbermachen (bis 30.11.) und Pottery bemalen. Welches kommt, entscheidet die Startseite
    // pro Besuch (list); 'popup' ist das erste (Link in Bio, #special).
    $xmOn = $md >= '11-02' && $md <= '12-23';
    if (($xmOn && (forma_setting($pdo, 'deko') ?? 'auto') !== 'aus') || ($preview && ($_GET['thema'] ?? '') === 'weihnachten')) {
        $y = date('Y');
        $list = [];
        if ($md <= '11-30' || $preview) {
            $list[] = [
                'id' => 'xa' . $y,
                'eyebrow' => 'Bald im Shop · 24 Türchen',
                'title' => 'Der FORMA Adventkalender zum Selbermachen',
                'text' => 'Jeden Tag im Advent ein Türchen und eine kleine Pottery-Idee: Anhänger, Schälchen und Geschenke aus Ton, gemütlich am Küchentisch gemacht.',
                'date_line' => '24 Templates zum Herunterladen',
                'signup' => true,
                'button_text' => 'Mehr erfahren',
                'button_link' => '#adventkalender',
                'image' => 'img/adventkalender.jpg',
                'theme' => 'advent',
            ];
        }
        $list[] = [
            'id' => 'xb' . $y,
            'eyebrow' => 'Special im Advent',
            'title' => 'Pottery bemalen bei Kerzenlicht',
            'text' => 'Gestalte deine Weihnachtsgeschenke dieses Jahr selbst: Tassen, Bowls und Mugs bemalen, mit einer Tasse Tee im Atelier.',
            'date_line' => 'Mi & Do 15–18 Uhr · stundenweise',
            'signup' => false,
            'button_text' => 'Platz reservieren',
            'button_link' => '#pottery-bemalen',
            'image' => 'img/bemalen-hand.jpg',
            'theme' => 'advent',
        ];
        forma_json(['ok' => true, 'popup' => $list[0], 'list' => $list]);
    }
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
        'image' => $p['image'] !== '' ? 'api/popup-image.php?v=' . (int)$p['version'] : ((($p['theme'] ?? '') === 'advent' || isset($_GET['thema'])) ? 'img/popup-advent.jpg?v=4' : ''),
        'theme' => ($preview && isset($_GET['thema'])) ? preg_replace('/[^a-z]/', '', (string)$_GET['thema']) : (string)($p['theme'] ?? ''),
    ]]);
} catch (Throwable $e) {
    error_log('FORMA popup: ' . $e->getMessage());
    forma_json(['ok' => true, 'popup' => null]);
}

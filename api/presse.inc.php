<?php
// Presse-Mails (Vivien, 25.10.2026): Lokalzeitungen und Gemeinde über FORMA und Movecia informieren.
// Je Empfänger eine Mail pro Marke, im Stil der jeweiligen Website, mit Fotos im Anhang.
// Verschickt wird nur aus der Verwaltung (?view=presse), nach Vorschau und Freigabe durch Vivien.
declare(strict_types=1);

const PRESSE_TO = [
    'rundschau' => ['name' => 'Rundschau, Redaktion Landeck', 'email' => 'redaktion.landeck@rundschau.at', 'hello' => 'Liebe Redaktion der Rundschau Landeck,', 'kind' => 'presse'],
    'meinbezirk' => ['name' => 'MeinBezirk Tirol, Georg Herrmann', 'email' => 'georg.herrmann@meinbezirk.at', 'hello' => 'Sehr geehrter Herr Herrmann,', 'kind' => 'meinbezirk'],
];

const PRESSE_PHOTOS = [
    'forma' => [['img/atelier-ecke.jpg', 'Studio-FORMA_Atelier.jpg'], ['img/studio.jpg', 'Studio-FORMA_Studio.jpg'], ['img/scheiben-abend.jpg', 'Studio-FORMA_Toepferscheiben.jpg'], ['img/drehen.jpg', 'Studio-FORMA_An-der-Scheibe.jpg']],
    'movecia' => [['img/movecia/fotos/ev-start.jpg', 'Movecia_Mindful-Movement.jpg'], ['img/movecia/fotos/ev-natal-sitzend.jpg', 'Movecia_Ankommen.jpg'], ['img/movecia/fotos/ev-method-karte.jpg', 'Movecia_Method.jpg'], ['img/movecia/fotos/ev-unfold.jpg', 'Movecia_Unfold.jpg'], ['img/movecia/fotos/ev-natal.jpg', 'Movecia_Praenatal.jpg'], ['img/movecia/fotos/ev-unfold-karte.jpg', 'Movecia_Journaling.jpg'], ['img/movecia/fotos/ev-start-arme.jpg', 'Movecia_Bewegung.jpg'], ['img/movecia/movecia-logo-quer.png', 'Movecia_Logo.png']],
];

/** Alle sechs Mails: Schlüssel brand-empfänger. */
function presse_keys(): array
{
    $k = [];
    foreach (array_keys(PRESSE_TO) as $to) foreach (['forma', 'movecia'] as $b) $k[] = $b . '-' . $to;
    return $k;
}

function presse_release(string $brand): array
{
    if ($brand === 'forma') return [
        'title' => 'Töpfern im Paznaun: In Mathon öffnet ein Workshop-Atelier für Einheimische und Gäste',
        'paras' => [
            'Mathon, Oktober 2026. Wer im Paznaun selbst an der Töpferscheibe sitzen möchte, musste bisher weit fahren. Mit dem Studio FORMA hat Vivien Malonga in Mathon ein Workshop-Atelier eröffnet, in dem man das Handwerk unter Anleitung selbst ausprobiert, allein, mit Freundinnen oder als Team.',
            'Das Angebot reicht vom zweistündigen Kurs „Spin & Go“ an der Drehscheibe über Private Pottery für ein eigenes Projekt bis zu Teambuilding-Events für Firmen und Vereine. In der Adventzeit kommt „Pottery bemalen“ dazu: Mittwochs und donnerstags von 15 bis 18 Uhr bemalen Groß und Klein Tassen und Schalen, die nach der Fertigstellung ein persönliches Weihnachtsgeschenk werden.',
            '„Ich wollte einen Ort schaffen, an dem die Kreativität frei fließen darf und an dem man entdeckt, dass die eigenen Hände etwas Wunderschönes erschaffen können. Hier kommt man ins Formen“, sagt Vivien Malonga, Mutter von vier Kindern. Gebucht und bezahlt wird direkt auf www.forma-pottery.com, dort gibt es auch Gutscheine.',
        ],
        'facts' => [
            'Spin & Go: Mittwoch und Freitag 9 bis 11 Uhr, € 70 pro Person inklusive eines Werkstücks',
            'Pottery bemalen im Advent: Mittwoch und Donnerstag 15 bis 18 Uhr, stundenweise buchbar',
            'Private Pottery und Teambuilding nach Vereinbarung',
            'Kontakt: Vivien Malonga, hello@forma-pottery.com, +43 677 63 90 70 80, Instagram @forma.potteryartcraft',
        ],
    ];
    return [
        'title' => 'Bewegung beginnt bei dir: Paznaunerin startet Online-Programm für Frauen mit vollem Kopf',
        'paras' => [
            'Mathon, Oktober 2026. Familie, Arbeit, der ganze unsichtbare Mental Load: Viele Frauen kommen im Alltag selbst zu kurz. Die Bewegungstrainerin Vivien Malonga aus Mathon im Paznaun bringt deshalb mit Movecia ihr Wissen aus Yoga, Pilates, Somatic Movement und Breathwork online, damit Frauen zu Hause in wenigen Minuten am Tag wieder bei sich ankommen.',
            'Am 1. Dezember startet die Mindful Movement Method, eine Online-Mitgliedschaft mit Videos für müde und für volle Tage. Jede Teilnehmerin beginnt bei Monat 1 und bekommt jeden Monat ein neues Programm mit Workbook, auf Deutsch und Englisch. Am 10. Jänner 2027 beginnt außerdem „Unfold – The Mindful Becoming“, eine dreimonatige Begleitung in kleiner Runde mit höchstens zehn Frauen, wöchentlichem Live-Call, Bewegungsvideos und Workbooks zu Mental Load und Organisation.',
            'Vor Ort im Studio und online bietet Vivien Malonga zudem Prä- und Postnatal-Training an, also Begleitung durch die Schwangerschaft und die Rückbildung danach.',
            '„Ich habe selbst vier Kinder und weiß, wie schnell man sich vergisst. Bewegung muss nicht anstrengend sein, sie darf sich gut anfühlen“, sagt die Gründerin.',
        ],
        'facts' => [
            'Mindful Movement Method: Start 1. Dezember 2026, Early-Bird bis 1. Dezember € 29 statt € 39 im Monat',
            'Unfold: Start 10. Jänner 2027, 10 Plätze, Early-Bird bis 11. Jänner € 450 statt € 490',
            'Prä- und Postnatal-Training: im Studio in Mathon oder online',
            'Infos und Buchung: www.moveciamovement.com · Kontakt: Vivien Malonga, hello@moveciamovement.com, Instagram @movementwithviv',
        ],
    ];
}

/** Persönlicher Teil der Mail (Sie-Form), je nach Empfänger. */
function presse_intro(string $brand, string $to): array
{
    $t = PRESSE_TO[$to];
    $f = $brand === 'forma';
    $who = $f
        ? 'mein Name ist Vivien Malonga, ich lebe mit meiner Familie in Mathon. In diesem Herbst habe ich hier das Studio FORMA eröffnet, ein Workshop-Atelier, in dem Einheimische und Gäste selbst an der Töpferscheibe arbeiten und ihre eigenen Stücke gestalten.'
        : 'mein Name ist Vivien Malonga, ich lebe mit meiner Familie in Mathon und begleite Frauen mit Yoga, Pilates, Somatic Movement und Breathwork. Mit Movecia bringe ich dieses Angebot jetzt auch online, damit Frauen im ganzen Tal und darüber hinaus mitmachen können.';
    $ask = match ($t['kind']) {
        'gemeinde' => 'Ich würde mich sehr freuen, wenn Sie in der nächsten Gemeindezeitung oder auf der Website der Gemeinde kurz darauf hinweisen könnten. Den Text unten dürfen Sie gern kürzen oder anpassen, die Fotos im Anhang sind zur freien Verwendung. Und falls es einen Redaktionsschluss für die nächste Ausgabe gibt, sagen Sie mir gern Bescheid.',
        'meinbezirk' => 'Vielleicht ist das eine Geschichte für die Ausgabe Landeck. Unten finden Sie einen kurzen Pressetext, im Anhang Fotos zur freien Verwendung. Falls jemand anderer in Ihrem Team für den Bezirk Landeck zuständig ist, leiten Sie meine Nachricht sehr gern weiter.',
        default => 'Vielleicht ist das eine Geschichte für Ihre Leserinnen und Leser im Oberland. Unten finden Sie einen kurzen Pressetext, im Anhang Fotos zur freien Verwendung.',
    };
    $visit = $f
        ? 'Sehr gern lade ich Sie auch ins Atelier nach Mathon ein, zum Zuschauen oder gleich zum Mitmachen.'
        : 'Für ein Gespräch oder eine kleine Probeeinheit nehme ich mir sehr gern Zeit.';
    $ps = $f
        ? 'PS: Parallel starte ich mit Movecia ein Bewegungsangebot für Frauen. Dazu schicke ich Ihnen eine eigene kurze Nachricht, damit beides übersichtlich bleibt.'
        : 'PS: Mein Töpferatelier Studio FORMA in Mathon stelle ich Ihnen in einer eigenen kurzen Nachricht vor, damit beides übersichtlich bleibt.';
    return ['hello' => $t['hello'], 'paras' => [$who, $ask . ' ' . $visit], 'ps' => $ps];
}

function presse_subject(string $brand, string $to): string
{
    $pre = PRESSE_TO[$to]['kind'] === 'gemeinde' ? 'Für die Gemeindezeitung: ' : 'Neues aus dem Paznaun: ';
    return $pre . ($brand === 'forma' ? 'Töpferatelier Studio FORMA in Mathon' : 'Online-Bewegungsprogramm Movecia aus Mathon');
}

function presse_sign(string $brand): array
{
    return $brand === 'forma'
        ? ['Bis bald', 'Vivien Malonga', 'Studio FORMA · Mathon', '+43 677 63 90 70 80 · hello@forma-pottery.com · www.forma-pottery.com']
        : ['Bis bald', 'Vivien Malonga', 'Movecia · Mindful Movement · Mathon', '+43 677 63 90 70 80 · hello@moveciamovement.com · www.moveciamovement.com'];
}

/** Text-Fassung (auch Grundlage der FORMA-HTML-Mail). */
function presse_text(string $brand, string $to): string
{
    $i = presse_intro($brand, $to);
    $r = presse_release($brand);
    $s = presse_sign($brand);
    return $i['hello'] . "\n\n" . implode("\n\n", $i['paras']) . "\n\nIch freue mich auf Ihre Rückmeldung.\n\n" . implode("\n", $s)
        . "\n\n" . $i['ps'] . "\n\n— Pressetext —\n\n" . $r['title'] . "\n\n" . implode("\n\n", $r['paras'])
        . "\n\nAuf einen Blick:\n" . implode("\n", array_map(fn($x) => '· ' . $x, $r['facts']))
        . "\n\nFotos zur freien Verwendung im Anhang (Foto: " . ($brand === 'forma' ? 'Studio FORMA' : 'Movecia') . ').';
}

/** HTML im Stil der jeweiligen Website. */
function presse_html(string $brand, string $to): string
{
    $h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $i = presse_intro($brand, $to);
    $r = presse_release($brand);
    $s = presse_sign($brand);
    $f = $brand === 'forma';
    $site = $f ? 'https://www.forma-pottery.com' : 'https://moveciamovement.com';
    $ink = $f ? '#33211B' : '#2E2529';
    $acc = $f ? '#8C2C1D' : '#7D5E68';
    $soft = $f ? '#E4D2BF' : '#EADFDE';
    $serif = $f ? 'Georgia,serif' : 'Georgia,\'Cormorant Garamond\',serif';
    $pics = '';
    foreach (array_chunk(array_slice(PRESSE_PHOTOS[$brand], 0, 4), 2) as $row) {
        $pics .= '<tr>';
        foreach ($row as [$p]) $pics .= '<td width="50%" style="padding:4px"><img src="' . $site . '/img/presse/' . basename($p) . '" width="260" alt="" style="display:block;width:100%;height:auto;border-radius:14px"></td>';
        $pics .= '</tr>';
    }
    $body = '<p style="margin:0 0 14px">' . $h($i['hello']) . '</p>';
    foreach ($i['paras'] as $p) $body .= '<p style="margin:0 0 14px">' . $h($p) . '</p>';
    $body .= '<p style="margin:0 0 14px">Ich freue mich auf Ihre Rückmeldung.</p>'
        . '<p style="margin:0 0 4px">' . $h($s[0]) . '</p><p style="margin:0;font-family:' . $serif . ';font-size:22px;color:' . $acc . '">' . $h($s[1]) . '</p>'
        . '<p style="margin:2px 0 0;font-size:13px;color:#7D7076">' . $h($s[2]) . '<br>' . $h($s[3]) . '</p>'
        . '<p style="margin:18px 0 0;font-size:14px;color:#7D7076">' . $h($i['ps']) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 6px">' . $pics . '</table>'
        . '<div style="margin:18px 0 0;padding:22px 24px;border-radius:18px;background:' . $soft . '">'
        . '<p style="margin:0 0 6px;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:' . $acc . '">Pressetext</p>'
        . '<p style="margin:0 0 12px;font-family:' . $serif . ';font-size:22px;line-height:1.25;color:' . $ink . '">' . $h($r['title']) . '</p>';
    foreach ($r['paras'] as $p) $body .= '<p style="margin:0 0 12px;font-size:15px">' . $h($p) . '</p>';
    $body .= '<p style="margin:14px 0 6px;font-weight:bold;font-size:15px">Auf einen Blick</p><ul style="margin:0;padding-left:18px;font-size:15px">';
    foreach ($r['facts'] as $x) $body .= '<li style="margin:0 0 4px">' . $h($x) . '</li>';
    $body .= '</ul><p style="margin:12px 0 0;font-size:13px;color:#7D7076">Fotos zur freien Verwendung im Anhang (Foto: ' . ($f ? 'Studio FORMA' : 'Movecia') . ').</p></div>';

    if ($f) {
        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#EEE2D4"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEE2D4"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FBF1EA;border-radius:22px;overflow:hidden">'
            . '<tr><td style="background:#DDBFA8;padding:0;line-height:0"><img src="' . $site . '/img/mail/' . forma_mail_theme() . '.png" width="600" alt="FORMA Earth &amp; Art" style="display:block;width:100%;max-width:600px;height:auto"></td></tr>'
            . '<tr><td style="padding:28px 30px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#33211B">' . $body . '</td></tr>'
            . '<tr><td align="center" style="background:#E4D2BF;padding:18px 24px;font-family:Arial,Helvetica,sans-serif;font-size:12.5px;line-height:1.7;color:#674B40">FORMA Earth &amp; Art · Mathon im Paznaun<br><a href="' . $site . '" style="color:#674B40">www.forma-pottery.com</a> · <a href="https://www.instagram.com/forma.potteryartcraft" style="color:#674B40">Instagram</a></td></tr></table></td></tr></table></body></html>';
    }
    return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#E8DED2"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E8DED2"><tr><td align="center" style="padding:26px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#F2ECE4;border-radius:24px;font-family:Helvetica,Arial,sans-serif;color:#2E2529">'
        . '<tr><td style="padding:24px 30px 0"><img src="' . $site . '/img/movecia/movecia-mail.png" width="160" alt="Movecia." style="display:block;border:0;width:160px;height:auto"></td></tr>'
        . '<tr><td style="padding:18px 30px 28px;font-size:16px;line-height:1.65">' . $body . '</td></tr></table>'
        . '<p style="max-width:600px;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#7D7076;margin:16px auto 0">Vivien Malonga · Movecia · Mathon im Paznaun · <a href="' . $site . '" style="color:#7D7076">moveciamovement.com</a></p>'
        . '</td></tr></table></body></html>';
}

function presse_attachments(string $brand): array
{
    $out = [];
    foreach (PRESSE_PHOTOS[$brand] as [$p, $name]) {
        $file = dirname(__DIR__) . '/' . $p;
        if (is_file($file)) $out[] = [$name, (string)file_get_contents($file), str_ends_with($p, '.png') ? 'image/png' : 'image/jpeg'];
    }
    return $out;
}

function presse_log(): array
{
    $f = forma_data_dir() . '/presse.json';
    return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
}

/** Verschickt eine Presse-Mail. $preview = true: an Viviens eigenes Postfach mit [VORSCHAU]. */
function presse_send(string $key, bool $preview): bool
{
    [$brand, $to] = explode('-', $key, 2);
    if (!isset(PRESSE_TO[$to]) || !in_array($brand, ['forma', 'movecia'], true)) throw new RuntimeException('Unbekannte Mail.');
    $log = presse_log();
    if (!$preview && !empty($log[$key]['sent'])) throw new RuntimeException('Diese Mail ist schon verschickt.');
    $subject = ($preview ? '[VORSCHAU] ' : '') . presse_subject($brand, $to);
    $text = presse_text($brand, $to);
    $html = presse_html($brand, $to);
    $att = presse_attachments($brand);
    if ($brand === 'forma') {
        $GLOBALS['FORMA_MAIL_HTML'] = $html;
        $ok = forma_mail($preview ? FORMA_NOTIFY_EMAIL : PRESSE_TO[$to]['email'], $subject, $text, null, $att);
        unset($GLOBALS['FORMA_MAIL_HTML']);
    } else {
        $ok = mm_mail($preview ? MM_FROM_EMAIL : PRESSE_TO[$to]['email'], $subject, $text, $html, $att, 'Vivien Malonga · Movecia');
    }
    if ($ok) {
        $log[$key][$preview ? 'preview' : 'sent'] = date('c');
        @file_put_contents(forma_data_dir() . '/presse.json', json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $ok;
}

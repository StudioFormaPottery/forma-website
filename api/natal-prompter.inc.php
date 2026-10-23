<?php
// Teleprompter für Vivien im Movecia-Videoraum (nur Gastgeberinnen-Link).
// Skripte je Einheit, vorbefüllt mit dem Ablauf aus dem Curriculum. Gespeichert in ../forma-data/movecia-natal-prompter.json.
declare(strict_types=1);

const NATAL_SCRIPT_TITLES = [
    'kennenlernen' => 'Kennenlerngespräch',
    'prae1' => 'Pränatal 1 · Ankommen im Körper', 'prae2' => 'Pränatal 2 · Beckenboden: anspannen und loslassen',
    'prae3' => 'Pränatal 3 · Rücken entlasten, Kraft für den Alltag', 'prae4' => 'Pränatal 4 · Der Atem als Werkzeug für die Wehen',
    'prae5' => 'Pränatal 5 · Geburtspositionen und Loslassen', 'prae6' => 'Pränatal 6 · Gut ins Wochenbett',
    'post1' => 'Postnatal 1 · Ankommen nach der Geburt', 'post2' => 'Postnatal 2 · Den Beckenboden neu spüren',
    'post3' => 'Postnatal 3 · Tiefe Bauchmuskeln und Rektusdiastase', 'post4' => 'Postnatal 4 · Haltung, Tragen und Stillen',
    'post5' => 'Postnatal 5 · Kraft für den Alltag mit Baby', 'post6' => 'Postnatal 6 · Nervensystem, Schlafmangel und Mental Load',
    'post7' => 'Postnatal 7 · Zurück zur Lieblingsbewegung', 'post8' => 'Postnatal 8 · Dein Programm für zu Hause',
    'frei' => 'Eigenes Skript',
];

/** Ablauf je Einheit aus dem Curriculum (Impuls, Bewegung, Breathwork, Meditation, Für zu Hause). */
function natal_script_defaults(): array
{
    $u = [
        'prae1' => ['Was sich gerade im Körper verändert: Relaxin macht die Bänder weicher, der Schwerpunkt wandert nach vorn, die Rippen weiten sich. Atmung, Bauch und Beckenboden sind ein Team.', 'Katze-Kuh im Vierfüßlerstand, Beckenkreisen auf dem Ball, aufrechtes Stehen und Sitzen im Alltag.', '360°-Atmung: Hände seitlich an die Rippen, in die Hände einatmen, langsam ausatmen.', 'Kurzer Body-Scan im Seitliegen, Hände am Bauch, Kontakt zum Baby.', 'Dreimal täglich fünf Atemzüge 360°-Atmung.'],
        'prae2' => ['Wo der Beckenboden liegt und was er trägt. Für die Geburt ist Loslassen genauso wichtig wie Kraft. Dammmassage ab der 34. SSW erwähnen, mit Verweis auf die Hebamme.', 'Beckenboden im Sitzen spüren (anspannen, halten, ganz lösen), Clamshells in Seitlage, unterstützte Hocke mit Ball oder Stuhl.', 'Einatmen und weich werden lassen, ausatmen und sanft heben.', 'Visualisierung „Öffnen“: eine Blüte, die sich mit jedem Ausatmen weiter öffnet.', 'Täglich zweimal zehn Atemzüge mit bewusstem Loslassen beim Einatmen.'],
        'prae3' => ['Rückenschmerz, Ischias und Symphyse: woher sie kommen und was hilft. Richtig heben, tragen und aufstehen, gute Schlafpositionen mit Kissen.', 'Kniebeuge an der Wand, Rudern mit Band, Vierfüßler mit Arm- und Beinheben, Seitstütz auf den Knien.', 'Bei jeder Kraftbewegung ausatmen, nie die Luft anhalten.', 'Sanfte progressive Muskelentspannung in Seitlage.', 'Kniebeuge und Rudern, je zweimal zehn, an drei Tagen pro Woche.'],
        'prae4' => ['Die Geburtsphasen einfach erklärt: Eröffnung, Übergang, Austreibung. Der Kreislauf aus Angst, Spannung und Schmerz und wie eine lange Ausatmung beruhigt.', 'Wehenpositionen: Vierfüßler, Kniestand über dem Ball, Hocke, Becken kreisen und wiegen, an die Wand lehnen.', 'Wellenatmung (ein 4, aus 6–8), Tönen auf „Ooo“ oder „Aaah“ mit weichem Kiefer.', 'Visualisierung „Jede Welle kommt und geht“.', 'Täglich fünf Minuten Wellenatmung, gern in einer Wehenposition.'],
        'prae5' => ['Warum aufrechte Positionen helfen, wie die Begleitperson unterstützen kann (Kreuzbeinmassage, Gegendruck, an die Atmung erinnern), Wünsche für die Geburt.', 'Positions-Flow von Stand über Kniestand bis Seitlage, Griffe für die Begleitperson üben.', 'Sanftes Atmen nach unten für die Austreibungsphase, ohne Luftanhalten, kein Pressen.', 'Affirmationen: „Mein Körper weiß, was er tut. Ich öffne mich mit jeder Welle.“', 'Lieblingsaffirmation aufschreiben und sichtbar aufhängen.'],
        'prae6' => ['Das Wochenbett realistisch: Ruhe, Hilfe organisieren, über die Seite aufstehen, Atmung und Beckenboden sanft wecken. Babyblues und Wochenbettdepression unterscheiden, wo es Hilfe gibt.', 'Die Lieblingssequenz aus allen Einheiten als ruhiges Programm zum Mitnehmen.', 'Lieblingsatmung festigen plus Notfall-Atmung (ein 4, aus 6).', 'Längere Ruhe, eine kurze Yoga-Nidra-Reise.', 'Wochenbett-Liste: wer hilft wobei, was sie für sich braucht. Ausmachen, dass sie schreibt, wenn das Baby da ist.'],
        'post1' => ['Raum für ihre Geburtsgeschichte, vor allem zuhören. Was jetzt heilt: Gebärmutter, Beckenboden, Bauch, Hormone. Kurz erfragen: Inkontinenz, Druckgefühl, Narbe, Schmerzen.', 'Sehr sanft: Rückenlage mit aufgestellten Beinen, Becken kippen, Seitlage. Diastase-Selbsttest, Breite notieren.', 'Ausatmen und Beckenboden und Unterbauch sanft nach innen und oben.', 'Body-Scan „Ich bin angekommen“, mit Dankbarkeit für den eigenen Körper.', 'Täglich zweimal fünf verbundene Atemzüge, gern beim Stillen.'],
        'post2' => ['Was mit dem Beckenboden bei der Geburt passiert ist und wie er sich erholt. Über die Seite aufstehen, beim Husten vorher anspannen, beim Heben ausatmen. Wann Beckenboden-Physio sinnvoll ist.', 'Beckenboden schnell und langsam aktivieren, mit Brücke und Clamshells.', 'Die „Knack“-Atmung: vor dem Husten oder Heben ausatmen und den Beckenboden mitnehmen.', 'Mitgefühl mit dem eigenen Körper, Hand auf dem Unterbauch.', 'Dreimal täglich zehn kurze und fünf lange Aktivierungen.'],
        'post3' => ['Was eine Rektusdiastase ist, warum die Spannung der Bauchmitte wichtiger ist als die Fingerbreiten, vorerst keine Crunches, Coning erkennen.', 'Querer Bauchmuskel, Heel Slides, Toe Taps, vereinfachter Dead Bug, Vierfüßler mit Atem.', 'Ausatmen und die Bauchmitte schließen wie ein Reißverschluss von unten nach oben.', 'Entspannung in Rückenlage, Kissen unter den Knien.', 'Heel Slides und Toe Taps, je zweimal zehn, an vier Tagen pro Woche.'],
        'post4' => ['Nacken und Schultern beim Stillen und Tragen, ergonomisch heben (Babyschale, Kinderwagen), gute Stillposition mit Kissen.', 'Brustwirbelsäule mobilisieren, Schulterblätter mit Band kräftigen, Brust dehnen, Kniebeuge mit Baby oder Gewicht.', 'Einatmen und Schultern heben, ausatmen und schwer werden lassen.', 'Eine 5-Minuten-Pause für überall im Alltag.', 'Nach dem Stillen eine Brustdehnung und zehn Schulterkreise.'],
        'post5' => ['Belastung langsam steigern und Warnzeichen erkennen: Druck im Becken, Urinverlust, Coning, Schmerz.', 'Kniebeugen, Ausfallschritt, einbeinige Brücke, Seitstütz, Rudern.', 'Ausatmen in der Anstrengung, einatmen in der Entlastung.', 'Visualisierung „Ich bin stark und getragen“.', 'Ein 10-Minuten-Kraftprogramm, dreimal pro Woche.'],
        'post6' => ['Was Schlafmangel und Hormone mit Stimmung und Kraft machen, Babyblues und Wochenbettdepression, Selbstfürsorge in Minuten, den Mental Load sichtbar machen.', 'Ruhig und somatisch: Schütteln, Wiegen, sanfte Mobilisation.', 'Verlängerte Ausatmung und Summen (Bienenatmung).', 'Yoga Nidra, etwa 15 Minuten.', 'Jeden Tag drei Minuten Mini-Pause mit Summen.'],
        'post7' => ['Wann Laufen, Springen und Sport wieder sinnvoll sind (Laufen meist frühestens ab 12 Wochen und nur ohne Beschwerden), Schritt für Schritt zurück.', 'Belastungs-Check: Einbeinstand, einbeinige Kniebeuge, zügiges Gehen, leichtes Hüpfen nur ohne Beschwerden; danach Ganzkörper-Flow.', 'Atem im Rhythmus der Bewegung.', 'Visualisierung ihrer Lieblingsbewegung in ein paar Monaten.', 'Zweimal pro Woche 20 Minuten zügig mit dem Kinderwagen gehen.'],
        'post8' => ['Rückblick: Was hat sich verändert, worauf ist sie stolz? Wie es weitergehen kann.', 'Ihr persönliches 15-Minuten-Programm gemeinsam durchgehen und aufschreiben.', 'Ihre Lieblingsatmung als Ritual für den Alltag.', 'Abschlussritual mit Affirmation: „Ich bin zurück bei mir.“', 'Das Programm. Um eine Google-Bewertung bitten.'],
    ];
    $out = ['kennenlernen' => "Kennenlerngespräch · 30 Minuten\n\n[Begrüßung]\nSchön, dass du da bist, {name}.\n\n[Fragen]\nWie geht es dir gerade? In welcher Woche bist du bzw. wie alt ist dein Baby?\nWas wünschst du dir von unserem Training?\nGibt es Beschwerden oder etwas, worauf ich achten soll?\nHast du die Freigabe von Ärztin oder Hebamme?\n\n[Angebot]\nIm Studio oder online, Einzelstunde oder Paket. Kombi: ich begleite dich vor und nach der Geburt.\n\n[Abschluss]\nOffene Fragen? Nächster Schritt: Paket wählen und Wunschtermine aussuchen.", 'frei' => ''];
    foreach ($u as $k => [$imp, $bew, $atm, $med, $home]) {
        $out[$k] = NATAL_SCRIPT_TITLES[$k] . "\n\n[0–5 Min · Ankommen]\nHallo {name}, wie geht es dir heute? Wie schläfst du, gibt es Beschwerden oder Fragen seit dem letzten Mal?\nDrei ruhige Atemzüge zum Ankommen.\n\n[5–15 Min · Impuls]\n$imp\n\n[15–35 Min · Bewegung]\n$bew\n\n[35–45 Min · Breathwork]\n$atm\n\n[45–55 Min · Meditation & Entspannung]\n$med\n\n[55–60 Min · Abschluss]\nFür zu Hause: $home\nWie hat es sich angefühlt? Nächster Termin.";
    }
    return $out;
}

function natal_prompter_cfg(): array
{
    $f = forma_data_dir() . '/movecia-natal-prompter.json';
    $c = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    $c = is_array($c) ? $c : [];
    return ['texts' => (array)($c['texts'] ?? []), 'speed' => (float)($c['speed'] ?? 1.2), 'size' => (int)($c['size'] ?? 30)];
}

function natal_prompter_save(array $in): void
{
    $c = natal_prompter_cfg();
    if (isset($in['key'], $in['text']) && isset(NATAL_SCRIPT_TITLES[$in['key']])) $c['texts'][$in['key']] = mb_substr((string)$in['text'], 0, 40000);
    if (isset($in['reset']) && isset(NATAL_SCRIPT_TITLES[$in['reset']])) unset($c['texts'][$in['reset']]);
    if (isset($in['speed'])) $c['speed'] = max(0.2, min(6, (float)$in['speed']));
    if (isset($in['size'])) $c['size'] = max(16, min(64, (int)$in['size']));
    $f = forma_data_dir() . '/movecia-natal-prompter.json';
    file_put_contents($f, json_encode($c, JSON_UNESCAPED_UNICODE));
    @chmod($f, 0600);
}

/** Welche Einheit ist dieser Termin? Kennenlerngespräch → kennenlernen; sonst praeN/postN nach Reihenfolge im Paket (Kombi: erst 6 prä, dann post). */
function natal_script_key(PDO $pdo, array $room): string
{
    if ($room['kind'] === 'c') return 'kennenlernen';
    $s = $room['row'];
    $o = natal_order($pdo, (int)$s['order_id']);
    if (!$o) return 'frei';
    $n = 0;
    foreach (natal_sessions($pdo, (int)$o['id']) as $x) {
        if ($x['status'] === 'abgesagt') continue;
        $n++;
        if ((int)$x['id'] === (int)$s['id']) break;
    }
    $pk = preg_replace('/^o_/', '', (string)$o['package']);
    if ($pk === 'prae') return 'prae' . min(6, $n);
    if ($pk === 'post') return 'post' . min(8, $n);
    if ($pk === 'kombi') return $n <= 6 ? 'prae' . $n : 'post' . min(8, $n - 6);
    // Einzelstunde: vor dem Geburtstermin Pränatal 1, danach Postnatal 1
    return ((string)$o['due_date'] !== '' && (string)$s['date'] > (string)$o['due_date']) ? 'post1' : 'prae1';
}

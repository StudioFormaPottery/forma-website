<?php
// „Als Teilnehmerin ansehen“ (Vivien, 26.10.2026): zu jedem Programm und jeder Seite ein Knopf, der zeigt,
// was eine Teilnehmerin sieht. Beispielansichten (?demo=…) ohne echtes Konto, dazu die echten Testzugänge.
// Eingebunden aus verwaltung/movecia.php (?view=vorschau), nur angemeldet.
declare(strict_types=1);

// Gathering-Raum als Teilnehmerin: eigener Testraum, Gast-Link
if ($action === 'mm_testroom_guest') {
    try { $t = mm_testroom(); } catch (Throwable $e) { go($self . '?view=vorschau', 'Der Testraum konnte nicht geöffnet werden: ' . $e->getMessage()); }
    header('Location: ' . mm_testroom_link($t, true));
    exit;
}

if ($view === 'vorschau') {
    require_once dirname(__DIR__) . '/api/method-lib.php';
    mv_schema($pdo);
    $site = mm_site();
    // Beispiel-Lektionen für die Lektionsansicht
    $first = fn(string $aud, string $cat = '') => (function () use ($pdo, $aud, $cat) {
        foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) if ($cat === '' || $l['cat'] === $cat) return $l;
        return null;
    })();
    $mFlow = $first('method', 'flow');
    $mTalk = $first('method', 'talk');
    $uMove = $first('unfold', 'body');
    $uTalk = $first('unfold', 'talk');
    // Player-Vorschau: ein Flow (mit Voice-over) und ein Bewegungsvideo (30/45 Sek.)
    $pFlow = $pMove = null;
    foreach (['method', 'unfold', 'natal'] as $aud) foreach (mv_programs($pdo, $aud) as $p) foreach (mv_lessons($pdo, (int)$p['id']) as $l) {
        if (!mv_lesson_is_clips($l)) continue;
        if (mv_lesson_mode($l) === 'flow') $pFlow = $pFlow ?? $l; else $pMove = $pMove ?? $l;
    }

    render_page('Als Teilnehmerin ansehen', function () use ($self, $flash, $site, $mFlow, $mTalk, $uMove, $uTalk, $pFlow, $pMove) {
        $h = 'forma_h';
        $tile = function (string $href, string $ic, string $title, string $text, bool $ext = true) use ($h): void { ?>
            <a href="<?= $h($href) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : ' class="real"' ?>><span class="ic"><?= $ic ?></span><b><?= $title ?></b><span><?= $text ?></span></a>
        <?php };
        $post = function (string $action, string $ic, string $title, string $text) use ($h): void { ?>
            <form method="post" target="_blank"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="<?= $h($action) ?>"><button type="submit"><span class="ic"><?= $ic ?></span><b><?= $title ?></b><span><?= $text ?></span></button></form>
        <?php };
        $m = $site . '/mitglieder/'; ?>
<style>
body{background:#F2ECE4}h1,h2{color:#6B5658}
@media(min-width:900px){main{max-width:1240px;padding-left:32px;padding-right:32px}body{font-size:19.5px}}
.nh{border-radius:26px;background:#4A3A41;color:#FBF5F7;padding:22px 24px}.nh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:34px;margin:2px 0 4px}.nh p{margin:0;color:#EADFDE}.nh .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
.grp{margin-top:26px}.grp h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:28px;margin:0 0 4px;color:#4A3A41}.grp>p{margin:0 0 12px;color:#7D7076;font-size:15.5px;max-width:70ch}
.qa{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px}
.qa form{margin:0}
.qa a,.qa button{display:flex;flex-direction:column;gap:4px;width:100%;height:100%;box-sizing:border-box;text-align:left;padding:16px;border-radius:20px;border:1px solid #DDD2C6;background:#FBF8F4;color:#2E2529;text-decoration:none;font:inherit;cursor:pointer}
.qa a:hover,.qa button:hover{border-color:#7D5E68}
.qa b{font-size:17px;font-weight:600;color:#4A3A41}
.qa span{font-size:14px;color:#7D7076;line-height:1.35;overflow-wrap:anywhere}
.qa .ic{font-size:26px;line-height:1}
.qa .real{background:#EADFDE;border-color:#D9C3C6}
.hint{font-size:14.5px;color:#7D7076;background:#FBF8F4;border:1px dashed #DDD2C6;border-radius:16px;padding:12px 16px;margin-top:14px}
</style>
<?php mm_subnav('vorschau'); ?>
<section class="nh"><p class="eyebrow">Movecia · Vorschau</p><h1>Als Teilnehmerin ansehen</h1><p>Jeder Knopf öffnet in einem neuen Tab genau die Ansicht, die eine Teilnehmerin hat. Die Beispielansichten brauchen kein Konto, es wird dabei nichts gebucht und nichts verschickt. Die rosa Kacheln sind echte Testzugänge, mit denen du alles selbst durchklicken kannst.</p></section>
<?php if ($flash): ?><p class="alert"><?= $h($flash) ?></p><?php endif; ?>

<section class="grp" id="player"><h2>Der Übungs-Player</h2><p>So laufen Flow und Bewegungsvideo bei den Teilnehmerinnen: deine Übung, runder Timer, Sonnenaufgang-Klang in den letzten 5 Sekunden, eigene Musik. In der Vorschau kannst du überspringen, die Frauen nicht.</p><div class="qa">
<?php
        if ($pFlow) $tile($self . '?view=videos&play=' . (int)$pFlow['id'], '🌊', 'Flow mit Voice-over', $h($pFlow['title_de']) . ' · jede Übung so lange wie eingestellt, deine Stimme dazu');
        if ($pMove) $tile($self . '?view=videos&play=' . (int)$pMove['id'], '⏱', 'Bewegungsvideo 30/45 Sek.', $h($pMove['title_de']) . ' · ohne Sprechen, Timer nach Wahl');
?>
</div></section>

<section class="grp" id="seiten"><h2>Deine Seiten</h2><p>Was jede Besucherin sieht, bevor sie bucht.</p><div class="qa">
<?php
        $tile($site . '/', '🏠', 'Hauptseite', 'moveciamovement.com');
        $tile($site . '/method/', '🌱', 'Method', 'Verkaufsseite der Mitgliedschaft mit Preisen');
        $tile($site . '/unfold/', '🌸', 'Unfold', 'Seite und Buchung des 3-Monats-Programms');
        $tile($site . '/academy/', '🎓', 'Academy', 'Übersicht der Ausbildungen');
        $tile($site . '/academy/teacher-training/', '🧘', 'Mindful Becoming Teacher Training', 'Seite, Kennenlerngespräch und Buchung');
        $tile($site . '/natal/', '🤰', 'Prä- &amp; Postnatal · Studio', 'Angebot, Einheiten, Kennenlerngespräch, Buchung');
        $tile($site . '/natal/online/', '💻', 'Prä- &amp; Postnatal · Online', 'Gleiche Seite, startet auf Online');
        $tile($site . '/gathering/', '✨', 'Gathering-Anmeldung', 'Anmeldeseite fürs nächste kostenlose Gathering');
        $tile($site . '/links/', '🔗', 'Link in Bio', 'Die Seite hinter deinem Instagram-Link');
        $tile($site . '/terms', '📄', 'Bedingungen', 'Preise, Rücktrittsrecht, Widerruf');
?>
</div></section>

<section class="grp" id="method"><h2>Mindful Movement Method</h2><p>Der Mitgliederbereich, wie eine Method-Teilnehmerin ihn sieht.</p><div class="qa">
<?php
        $tile($m . '?demo=1', '📚', 'Mitgliederbereich', 'Bibliothek mit dem aktuellen Monat, Journal, Konto');
        $tile($m . '?demo=1&lang=en', '🇬🇧', 'Auf Englisch', 'Derselbe Bereich in englischer Sprache');
        if ($mFlow) $tile($m . '?demo=1&l=' . (int)$mFlow['id'], '🎬', 'Ein Bewegungsvideo', $h($mFlow['title_de']) . ' · im Mitgliederbereich, sobald alles hochgeladen ist');
        if ($mTalk) $tile($m . '?demo=1&l=' . (int)$mTalk['id'], '🗣', 'Ein Talking Head', $h($mTalk['title_de']));
        $tile($m . '?demo=1&starter=1', '⏳', 'Während der Widerrufsfrist', 'Wer das Rücktrittsrecht behalten hat: erst das Ankommen-Modul');
        $tile($m . '?demo=1&warte=1', '📅', 'Vor dem Start', 'Gebucht, aber noch nicht freigeschaltet: Warteseite mit Startdatum');
        $tile($m, '🔑', 'Login-Seite', 'So meldet sich eine Teilnehmerin an');
        $tile($self . '?view=method#test', '🧪', 'Echter Testzugang', 'Als Testteilnehmerin einloggen und alles selbst durchklicken', false);
?>
</div></section>

<section class="grp" id="unfold"><h2>Unfold · The Mindful Becoming</h2><p>Der Unfold-Bereich im Mitgliederbereich, so wie ihn eine Teilnehmerin in der zweiten Woche sieht.</p><div class="qa">
<?php
        $tile($m . '?demo=1&nurunfold=1', '🌸', 'Nur Unfold gebucht', 'Wochenplan, Videos, Workbooks, Live-Call');
        $tile($m . '?demo=1&unfold=1', '🌿', 'Unfold und Method', 'Wer beides gebucht hat');
        if ($uMove) $tile($m . '?demo=1&unfold=1&l=' . (int)$uMove['id'], '🎬', 'Ein Bewegungsvideo', $h($uMove['title_de']));
        if ($uTalk) $tile($m . '?demo=1&unfold=1&l=' . (int)$uTalk['id'], '🗣', 'Ein Talking Head', $h($uTalk['title_de']));
        $tile($self . '?view=method#test', '🧪', 'Echter Testzugang', 'Testteilnehmerin mit Unfold anlegen, auch „vor dem Start“', false);
?>
</div></section>

<section class="grp" id="academy"><h2>Academy · The Mindful Becoming Teacher Training</h2><p>Der Mitgliederbereich gleich nach der Anzahlung: Countdown bis zum Start, dein Willkommensvideo, Notizen, Chat mit dir und die Zahlungen.</p><div class="qa">
<?php
        $tile($site . '/academy/mitglieder/?demo=1', '🎓', 'Mitgliederbereich', 'Countdown, Willkommensvideo, Workbook, Notizen, Chat, Zahlungen');
        $tile($site . '/academy/mitglieder/', '🔑', 'Login-Seite', 'So meldet sich eine Teilnehmerin an');
        $tile($site . '/academy/teacher-training/#liste', '💌', 'Interessentinnen-Liste', 'Formular „Setz deinen Namen auf die Liste“ (bei ausgebucht: Warteliste)');
        $tile($site . '/?popup=method', '🪟', 'Pop-up Startseite · Method', 'Bis 1.12.2026 automatisch');
        $tile($site . '/?popup=unfold', '🪟', 'Pop-up Startseite · Unfold', 'Bis 10.1.2027 automatisch');
        $tile($site . '/?popup=teacher', '🪟', 'Pop-up Startseite · Mindful Becoming Teacher Training', 'Danach automatisch');
        $tile($self . '?view=academy#chat', '💬', 'Chat beantworten', 'Nachrichten der Teilnehmerinnen in der Verwaltung', false);
?>
</div></section>

<section class="grp" id="natal"><h2>Prä- &amp; Postnatal</h2><p>Der Bereich für Kundinnen mit Paket. Nach jeder Einheit kommen dort Workbook, Aufnahme, Vertiefung und Bewegungsvideo dazu.</p><div class="qa">
<?php
        $tile($site . '/natal/mitglied/?demo=prae', '🤰', 'Pränatal-Kundin', 'Zwei Einheiten erledigt, mit Workbook, Aufnahme und Videos');
        $tile($site . '/natal/mitglied/?demo=post', '👶', 'Postnatal-Kundin', 'Nach der Geburt, mit den Postnatal-Einheiten');
        $tile($site . '/natal/mitglied/?demo=vorschau', '⏳', 'Gerade gebucht', 'Während der Widerrufsfrist: nur Begrüßung und Workbook-Vorschau');
        $tile($site . '/natal/mitglied/', '🔑', 'Login-Seite', 'So meldet sich eine Kundin an');
        $tile($self . '?view=natal#testkundin', '🧪', 'Echte Testkundin', 'Persönliche Terminseite, Videoraum, Mails und Mitgliederbereich durchspielen', false);
?>
</div></section>

<section class="grp" id="live"><h2>Live &amp; Videoraum</h2><p>Wie Teilnehmerinnen ein Gathering oder eine Online-Einheit erleben.</p><div class="qa">
<?php
        $post('mm_testroom_guest', '🎥', 'Gathering als Teilnehmerin', 'Dein Testraum aus Sicht einer Teilnehmerin, mit Angebotskarte und Fragen');
        $post('mm_testroom', '🎙️', 'Gathering als Gastgeberin', 'Derselbe Testraum mit Teleprompter, Aufnahme und Angeboten');
        $tile($self . '?view=natal#testkundin', '💻', 'Online-Einheit · Videoraum', 'Testkundin mit „Online-Termin in 20 Min.“ anlegen, dann „Videoraum als Kundin“', false);
?>
</div></section>
<p class="hint">Tipp: Die Beispielansichten öffnest du am besten in einem privaten Fenster oder abgemeldet. Bist du gerade als Testteilnehmerin eingeloggt, zeigt der Mitgliederbereich deinen Testzugang statt des Beispiels.</p>
<?php
    });
    exit;
}

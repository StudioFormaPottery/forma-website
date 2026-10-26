<?php
// Presse & Gemeinde (Vivien, 25.10.2026): sechs vorbereitete Mails (FORMA und Movecia an Rundschau, MeinBezirk, Gemeinde Ischgl).
// Ansehen → „Vorschau an mich“ → „Jetzt senden“. Eingebunden aus verwaltung/movecia.php (?view=presse), nur angemeldet.
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/presse.inc.php';

$pBack = $self . '?view=presse';
if ($action === 'mm_presse_send' || $action === 'mm_presse_preview') {
    $key = (string)($_POST['key'] ?? '');
    try {
        $ok = presse_send($key, $action === 'mm_presse_preview');
        go($pBack . '#' . $key, $ok ? ($action === 'mm_presse_preview' ? 'Vorschau ist an dein Postfach unterwegs.' : 'Verschickt.') : 'Die Mail konnte nicht verschickt werden. Bitte prüf unter Einstellungen das Postfach-Passwort.');
    } catch (Throwable $e) { go($pBack . '#' . $key, $e->getMessage()); }
}

if ($view === 'presse') {
    if (isset($_GET['mail'])) {
        $key = (string)$_GET['mail'];
        if (!in_array($key, presse_keys(), true)) { http_response_code(404); exit('Nicht gefunden'); }
        [$b, $t] = explode('-', $key, 2);
        header('Content-Type: text/html; charset=utf-8');
        echo presse_html($b, $t);
        exit;
    }
    render_page('Presse & Gemeinde', function () use ($self, $flash) {
        $log = presse_log();
        $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '">';
        mm_subnav('presse'); ?>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <h1>Presse &amp; Gemeinde</h1>
        <p class="muted">Je Empfänger eine Mail für FORMA und eine für Movecia, mit Fotos im Anhang. Erst ansehen, dann „Vorschau an mich“, dann „Jetzt senden“. Jede Mail geht nur einmal raus.</p>
        <?php foreach (PRESSE_TO as $to => $r): ?>
            <h2><?= forma_h($r['name']) ?> <span class="small muted"><?= forma_h($r['email']) ?></span></h2>
            <div class="g2">
            <?php foreach (['forma' => 'FORMA', 'movecia' => 'Movecia'] as $b => $bn): $key = "$b-$to"; $st = $log[$key] ?? []; ?>
                <div class="card stack" id="<?= $key ?>">
                    <b><?= $bn ?></b>
                    <p class="small"><?= forma_h(presse_subject($b, $to)) ?></p>
                    <p class="small muted"><?= !empty($st['sent']) ? '✓ verschickt am ' . date('d.m.Y H:i', strtotime($st['sent'])) : (isset(PRESSE_SCHEDULE[$key]) ? 'geplant für ' . date('d.m.Y H:i', strtotime(PRESSE_SCHEDULE[$key])) . ' Uhr · ' : '') . (!empty($st['preview']) ? 'Vorschau geschickt am ' . date('d.m.Y H:i', strtotime($st['preview'])) : 'noch nicht verschickt') ?></p>
                    <div class="row" style="display:flex;gap:8px;flex-wrap:wrap">
                        <a class="btn btn-sm" href="?view=presse&amp;mail=<?= $key ?>" target="_blank">Ansehen</a>
                        <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_presse_preview"><input type="hidden" name="key" value="<?= $key ?>"><button class="btn btn-sm" type="submit">Vorschau an mich</button></form>
                        <?php if (empty($st['sent'])): ?><form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_presse_send"><input type="hidden" name="key" value="<?= $key ?>"><button class="btn btn-sm" type="submit">Jetzt senden</button></form><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endforeach;
    });
    exit;
}

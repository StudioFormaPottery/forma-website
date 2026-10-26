<?php
// Movecia · Gutscheine (Vivien, 26.10.2026): wer hat gekauft, ist bezahlt oder noch offen, Rechnungsnummer,
// wo eingelöst, Restwert. Gutschein selbst ausstellen (Bezahl-Link mit QR-Code, bar oder als Geschenk).
// Eingebunden aus verwaltung/movecia.php (?view=mvgutscheine), nur angemeldet.
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/mv-gutschein-lib.php';

mvg_schema($pdo);
$vgBack = $self . '?view=mvgutscheine';

if ($action === 'mm_vg_create') {
    $pay = in_array($_POST['pay'] ?? '', ['link', 'bar', 'geschenk'], true) ? (string)$_POST['pay'] : 'link';
    $amount = (int)round((float)str_replace(['.', ','], ['', '.'], (string)($_POST['amount'] ?? '0')) * 100);
    try {
        $v = mvg_create($pdo, ['product' => (string)($_POST['product'] ?? ''), 'amount_cents' => $amount, 'design' => (int)($_POST['design'] ?? 1), 'lang' => (string)($_POST['lang'] ?? 'de'),
            'recipient' => (string)($_POST['recipient'] ?? ''), 'sender' => (string)($_POST['sender'] ?? ''), 'message' => (string)($_POST['message'] ?? ''),
            'buyer_name' => (string)($_POST['buyer_name'] ?? ''), 'buyer_email' => (string)($_POST['buyer_email'] ?? ''),
            'via' => 'verwaltung', 'kind' => $pay === 'geschenk' ? 'geschenk' : 'kauf', 'note' => forma_clean((string)($_POST['note'] ?? ''), 200)]);
        if ($pay !== 'link') {
            mvg_paid($pdo, (int)$v['id'], $pay);
            $v = mvg_get($pdo, (int)$v['id']);
            go($vgBack . '&neu=' . $v['id'] . '#g' . $v['id'], 'Gutschein ' . $v['nr'] . ' ist ausgestellt' . ($v['buyer_email'] !== '' ? ((int)$v['email_ok'] === 1 ? ', die Mail mit PDF ist unterwegs.' : '. Die Käuferin bestätigt zuerst ihre E-Mail, dann kommt der Gutschein. Ausdrucken kannst du ihn gleich hier.') : '. Das PDF kannst du gleich hier öffnen.'));
        }
        if (!empty($_POST['send_mail']) && filter_var((string)$v['buyer_email'], FILTER_VALIDATE_EMAIL)) {
            $msg = mvg_pay_message($v);
            mm_mail((string)$v['buyer_email'], 'Dein Movecia Gutschein: Bezahl-Link', $msg, mm_mail_html('Dein Gutschein', '<p>' . nl2br(forma_h($msg)) . '</p>' . mm_button(mvg_pay_link($v), 'Jetzt bezahlen'), 'de'), [], 'Vivien · Movecia');
        }
        go($vgBack . '&qr=' . $v['id'] . '#qr');
    } catch (Throwable $e) {
        go($vgBack . '#neu', $e->getMessage());
    }
}

if (in_array($action, ['mm_vg_cash', 'mm_vg_cancel', 'mm_vg_resend', 'mm_vg_deduct', 'mm_vg_check', 'mm_vg_reconfirm'], true)) {
    $v = mvg_get($pdo, (int)($_POST['vid'] ?? 0));
    if (!$v) go($vgBack, 'Diesen Gutschein gibt es nicht.');
    if ($action === 'mm_vg_check') {
        try { $st = mvg_check($pdo, $v); } catch (Throwable $e) { $st = 'offen'; }
        go($vgBack . '#g' . $v['id'], $st === 'bezahlt' ? 'Bezahlt! Der Gutschein hat jetzt seine Nummer, die Mail ist unterwegs.' : 'Noch keine Zahlung angekommen.');
    }
    if ($action === 'mm_vg_cash') {
        if ($v['status'] !== 'offen') go($vgBack, 'Dieser Gutschein ist schon bezahlt.');
        mvg_paid($pdo, (int)$v['id'], 'bar');
        $v = mvg_get($pdo, (int)$v['id']);
        go($vgBack . '#g' . $v['id'], 'Bar bezahlt. Der Gutschein hat jetzt die Nummer ' . $v['nr'] . ', Mail mit PDF und Rechnung sind unterwegs.');
    }
    if ($action === 'mm_vg_cancel') {
        $pdo->prepare("UPDATE mv_vouchers SET status = 'zurueckgezogen' WHERE id = ? AND status = 'offen'")->execute([$v['id']]);
        go($vgBack, 'Zurückgezogen. Der Bezahl-Link funktioniert nicht mehr.');
    }
    if ($action === 'mm_vg_reconfirm') {
        mvg_send_confirm($pdo, $v);
        go($vgBack . '#g' . $v['id'], 'Die Bestätigungsmail ist noch einmal unterwegs an ' . $v['buyer_email'] . '.');
    }
    if ($action === 'mm_vg_resend') {
        mvg_mails($pdo, (int)$v['id'], true);
        go($vgBack . '#g' . $v['id'], 'Die Gutschein-Mail mit PDF ist noch einmal unterwegs an ' . $v['buyer_email'] . '.');
    }
    if ($action === 'mm_vg_deduct') {
        $cents = (int)round((float)str_replace(['.', ','], ['', '.'], (string)($_POST['amount'] ?? '0')) * 100);
        $bal = mvg_balance($pdo, $v);
        if ($cents <= 0) go($vgBack . '#g' . $v['id'], 'Bitte gib einen Betrag ein.');
        if ($cents > $bal) go($vgBack . '#g' . $v['id'], 'Auf diesem Gutschein sind nur noch ' . mv_eur($bal) . ' offen.');
        mvg_use($pdo, (int)$v['id'], 'hand', 0, $cents, true, 'von Hand: ' . (forma_clean((string)($_POST['note'] ?? ''), 120) ?: 'im Studio'));
        go($vgBack . '#g' . $v['id'], mv_eur($cents) . ' abgezogen. Restwert: ' . mv_eur($bal - $cents) . '.');
    }
}

if ($view === 'mvgutschein_pdf') {
    $v = mvg_get($pdo, (int)($_GET['id'] ?? 0));
    if (!$v || !in_array($v['status'], ['bezahlt', 'eingeloest'], true)) { http_response_code(404); exit('Nicht gefunden'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Movecia-Gutschein-' . $v['nr'] . '.pdf"');
    echo mvg_pdf($v);
    exit;
}

if ($view === 'mvgutschein_status') {
    $v = mvg_get($pdo, (int)($_GET['id'] ?? 0));
    if ($v && $v['status'] === 'offen') { try { mvg_check($pdo, $v); $v = mvg_get($pdo, (int)$v['id']); } catch (Throwable $e) {} }
    header('Content-Type: application/json');
    echo json_encode(['status' => $v['status'] ?? '', 'nr' => $v['nr'] ?? '']);
    exit;
}

/** Rechnung zum Gutscheinkauf (falls ausgestellt) */
$vgInvoice = function (int $vid) use ($pdo): ?array {
    require_once dirname(__DIR__) . '/api/invoices.php';
    forma_invoice_schema($pdo);
    $q = $pdo->prepare("SELECT id, nr FROM invoices WHERE kind = 'mv_gutschein' AND ref_id = ?");
    $q->execute([$vid]);
    return $q->fetch() ?: null;
};

if ($view === 'mvgutscheine_export') {
    $rows = $pdo->query("SELECT * FROM mv_vouchers WHERE status NOT IN ('abgebrochen') ORDER BY id")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Movecia-Gutscheine-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Gutschein-Nr.', 'Gekauft am', 'Bezahlt am', 'Gutschein', 'Wert', 'Bezahlt', 'Zahlungsart', 'Rechnung', 'Käufer:in', 'E-Mail', 'Für', 'Eingelöst', 'Restwert', 'Status', 'Code'], ';');
    foreach ($rows as $v) {
        $inv = $vgInvoice((int)$v['id']);
        $bal = mvg_balance($pdo, $v);
        $paid = in_array($v['status'], ['bezahlt', 'eingeloest'], true);
        fputcsv($o, [$v['nr'] ?: '(noch keine)', date('d.m.Y', strtotime((string)$v['created_at'])), $v['paid_at'] ? date('d.m.Y', strtotime((string)$v['paid_at'])) : '', mvg_title($v),
            number_format((int)$v['amount_cents'] / 100, 2, ',', ''), $paid ? ($v['kind'] === 'geschenk' ? 'Geschenk' : 'ja') : 'nein, offen',
            ['stripe' => 'online (Stripe)', 'bar' => 'bar', 'geschenk' => 'kostenlos'][$v['pay_method']] ?? '', $inv['nr'] ?? '', $v['buyer_name'], $v['buyer_email'], $v['recipient'],
            number_format(($paid ? (int)$v['amount_cents'] - $bal : 0) / 100, 2, ',', ''), number_format($bal / 100, 2, ',', ''), mvg_state($pdo, $v)[1], $paid ? $v['code'] : ''], ';');
    }
    fclose($o);
    exit;
}

if ($view === 'mvgutscheine') {
    try { mvg_tick($pdo, true); } catch (Throwable $e) {}
    $all = $pdo->query("SELECT * FROM mv_vouchers WHERE status NOT IN ('abgebrochen') ORDER BY id DESC")->fetchAll();
    $open = array_values(array_filter($all, fn($v) => $v['status'] === 'offen' && $v['via'] !== 'web'));
    $done = array_values(array_filter($all, fn($v) => in_array($v['status'], ['bezahlt', 'eingeloest'], true)));
    $sold = array_filter($done, fn($v) => $v['kind'] === 'kauf' && (int)$v['test'] === 0);
    $restSum = 0; foreach ($done as $v) $restSum += mvg_balance($pdo, $v);
    $qr = isset($_GET['qr']) ? mvg_get($pdo, (int)$_GET['qr']) : null;
    $neu = (int)($_GET['neu'] ?? 0);
    $ps = mvg_products();
    render_page('Movecia · Gutscheine', function () use ($pdo, $self, $flash, $open, $done, $sold, $restSum, $qr, $neu, $ps, $vgInvoice) {
        $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '">'; ?>
<style>
:root{--paper:#F2ECE4;--card:#FBF8F4;--soft:#7D7076;--line:#DDD2C6;--ink:#2E2529}
body{background:var(--paper)}h1,h2{color:#6B5658}
.nh{position:relative;overflow:hidden;border-radius:26px;background:#4A3A41;color:#FBF5F7;padding:24px 24px 22px}
.nh::after{content:"";position:absolute;width:220px;height:210px;right:-80px;top:-90px;border-radius:62% 38% 46% 54%/55% 45% 55% 45%;background:rgba(232,214,202,.3)}
.nh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:34px;margin:2px 0 4px;position:relative}.nh p{position:relative;margin:0;color:#EADFDE}.nh .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
.ns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}@media(min-width:700px){.ns{grid-template-columns:repeat(4,minmax(0,1fr))}}
.ns a,.ns span.st{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:12px 14px;color:var(--ink);text-decoration:none;display:flex;flex-direction:column}
.ns b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-variant-numeric:lining-nums;font-size:32px;line-height:1.1;color:#7D5E68}.ns a span,.ns span.st span{font-size:14px;color:var(--soft);line-height:1.3}
.ns .red b{color:#B5544A}
.nl{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.nl a{display:inline-block;padding:7px 14px;border-radius:999px;background:var(--card);border:1px solid var(--line);text-decoration:none;color:#6B5658;font-size:15px}
.btn{background:#7D5E68;border-color:#7D5E68;color:#FBF5F7}.btn-ghost{background:transparent;color:#7D5E68}.link{color:#7D5E68}
.vg{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:16px 18px;margin-bottom:10px;display:grid;gap:6px}
.vg.is-new{box-shadow:0 0 0 2px #7D5E68}
.vg-h{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:baseline}
.vg-h strong{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:24px;color:var(--ink)}
.pill{display:inline-block;border-radius:999px;padding:2px 11px;font-size:13px;background:#EADFDE;color:#6B5658;white-space:nowrap}
.pill.ok{background:#7D5E68;color:#FBF5F7}.pill.wait{background:#F3D9C9;color:#7A3D25}.pill.done{background:#E9E4DE;color:#7D7076}.pill.off{background:#eee;color:#999}
.vg dl{display:grid;grid-template-columns:auto 1fr;gap:2px 14px;margin:4px 0 0;font-size:15px}
.vg dt{color:var(--soft)}.vg dd{margin:0}
.vg .acts{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:6px}
.vg .acts form{display:inline}
.vg details summary{cursor:pointer;color:#7D5E68;font-size:15px}
.vg-form{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:18px;display:grid;gap:12px}
.vg-form .g2{display:grid;gap:10px}@media(min-width:760px){.vg-form .g2{grid-template-columns:1fr 1fr}}
.vg-form label{display:grid;gap:4px;font-size:15px;color:var(--soft)}
.radio-row{display:flex;flex-wrap:wrap;gap:8px}
.radio-row label{display:flex;gap:6px;align-items:center;border:1px solid var(--line);border-radius:999px;padding:6px 12px;background:#fff;color:var(--ink);cursor:pointer}
.qrbox{background:#fff;border-radius:18px;padding:14px;display:inline-block;line-height:0}
.qrbox svg{width:min(280px,72vw);height:auto}
.vg-msg{width:100%;box-sizing:border-box;font:inherit;font-size:15px;border-radius:14px;border:1px solid var(--line);padding:12px;background:#fff;min-height:170px}
</style>
        <?php mm_subnav('mvgutscheine'); ?>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>
        <section class="nh"><p class="eyebrow">Movecia</p><h1>Gutscheine</h1><p>Wer gekauft hat, ob bezahlt ist, Rechnungsnummer, wo eingelöst und was noch drauf ist.</p></section>
        <div class="ns">
            <span class="st"><b><?= count($sold) ?></b><span>verkauft · <?= forma_h(mv_eur(array_sum(array_column($sold, 'amount_cents')))) ?></span></span>
            <a href="#offen" class="<?= $open ? 'red' : '' ?>"><b><?= count($open) ?></b><span>noch nicht bezahlt<?= $open ? ' · ' . forma_h(mv_eur(array_sum(array_column($open, 'amount_cents')))) : '' ?></span></a>
            <span class="st"><b><?= forma_h(mv_eur($restSum)) ?></b><span>noch offen auf Gutscheinen (Restwerte)</span></span>
            <span class="st"><b><?= count(array_filter($done, fn($v) => mvg_state($pdo, $v)[0] === 'eingeloest')) ?></b><span>ganz eingelöst</span></span>
        </div>
        <div class="nl">
            <a href="#neu">＋ Gutschein ausstellen</a>
            <a href="?view=mvgutscheine_export">Liste herunterladen (CSV)</a>
            <a href="<?= forma_h(mm_site()) ?>/gutschein/" target="_blank">Gutschein-Seite ansehen</a>
        </div>

        <?php if ($qr && $qr['status'] === 'offen'): $msg = mvg_pay_message($qr); ?>
        <h2 id="qr">Bezahl-Link für <?= forma_h($qr['buyer_name'] ?: 'den Gutschein') ?></h2>
        <div class="vg" id="vg-result" data-id="<?= (int)$qr['id'] ?>">
            <p><?= forma_h(mvg_title($qr)) ?> · <strong><?= forma_h(mv_eur((int)$qr['amount_cents'])) ?></strong></p>
            <p class="small muted">Zum Scannen am Handy (z. B. im Studio) oder die Nachricht per WhatsApp schicken. Sobald bezahlt ist, bekommt der Gutschein seine Nummer, die Käuferin das PDF und die Rechnung, und er rutscht unten in die Liste.</p>
            <div class="qrbox" id="vg-qrcode" data-url="<?= forma_h(mvg_pay_link($qr)) ?>"></div>
            <p id="vg-wait" class="small muted">⏳ wartet auf Zahlung …</p>
            <p id="vg-paid" hidden><strong>Bezahlt ✓</strong> Gutschein-Nr. <span id="vg-nr"></span></p>
            <textarea class="vg-msg" id="vg-m"><?= forma_h($msg) ?></textarea>
            <div class="acts">
                <button class="btn btn-sm" type="button" data-copy-from="vg-m">Nachricht kopieren</button>
                <a class="btn btn-ghost btn-sm" href="#" data-wa-from="vg-m" target="_blank" rel="noopener">Per WhatsApp schicken</a>
                <button class="link small" type="button" data-copy="<?= forma_h(mvg_pay_link($qr)) ?>">nur Link kopieren</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($open): ?>
        <h2 id="offen">⏳ Noch nicht bezahlt</h2>
        <p class="small muted">Gutscheine, die du ausgestellt hast und bei denen die Zahlung noch fehlt. Sobald bezahlt ist, rutschen sie nach unten und bekommen ihre Nummer.</p>
        <?php foreach ($open as $v): $hid = $csrf . '<input type="hidden" name="vid" value="' . (int)$v['id'] . '">'; ?>
            <div class="vg" id="g<?= (int)$v['id'] ?>">
                <div class="vg-h"><strong><?= forma_h($v['buyer_name'] ?: 'ohne Namen') ?> · <?= forma_h(mv_eur((int)$v['amount_cents'])) ?></strong><span class="pill wait">Betrag offen</span></div>
                <div class="small"><?= forma_h(mvg_title($v)) ?><?= $v['recipient'] !== '' ? ' · für ' . forma_h($v['recipient']) : '' ?></div>
                <div class="small muted">Ausgestellt am <?= forma_h(date('d.m.Y, H:i', strtotime((string)$v['created_at']))) ?><?= $v['buyer_email'] !== '' ? ' · ' . forma_h($v['buyer_email']) : '' ?></div>
                <div class="acts">
                    <a class="btn btn-sm" href="?view=mvgutscheine&amp;qr=<?= (int)$v['id'] ?>#qr">QR-Code &amp; Nachricht</a>
                    <form method="post"><?= $hid ?><button class="btn btn-ghost btn-sm" name="action" value="mm_vg_check">Zahlung prüfen</button></form>
                    <form method="post" onsubmit="return confirm('Bar bezahlt? Dann bekommt der Gutschein jetzt seine Nummer, Mail und Rechnung gehen raus.')"><?= $hid ?><button class="link small" name="action" value="mm_vg_cash">bar bezahlt</button></form>
                    <form method="post" onsubmit="return confirm('Wirklich zurückziehen? Der Bezahl-Link funktioniert dann nicht mehr.')"><?= $hid ?><button class="link small" name="action" value="mm_vg_cancel">zurückziehen</button></form>
                </div>
            </div>
        <?php endforeach; endif; ?>

        <h2>Alle Gutscheine</h2>
        <?php if (!$done): ?><p class="muted">Noch keine verkauft. Sobald jemand auf moveciamovement.com/gutschein kauft oder du einen ausstellst, steht er hier.</p><?php endif; ?>
        <?php foreach ($done as $v): [$st, $stT, $stC] = mvg_state($pdo, $v); $inv = $vgInvoice((int)$v['id']); $uses = mvg_uses($pdo, (int)$v['id']); $bal = mvg_balance($pdo, $v);
            $hid = $csrf . '<input type="hidden" name="vid" value="' . (int)$v['id'] . '">'; ?>
            <div class="vg<?= (int)$v['id'] === $neu ? ' is-new' : '' ?>" id="g<?= (int)$v['id'] ?>">
                <div class="vg-h"><strong><?= forma_h(mvg_title($v)) ?></strong><span class="pill <?= $stC ?>"><?= forma_h($stT) ?></span></div>
                <dl>
                    <dt>Nr.</dt><dd><?= forma_h($v['nr']) ?><?= (int)$v['test'] ? ' <span class="pill">Test</span>' : '' ?> · Code <code><?= forma_h($v['code']) ?></code></dd>
                    <dt>Wert</dt><dd><?= forma_h(mv_eur((int)$v['amount_cents'])) ?><?= $bal > 0 && $bal < (int)$v['amount_cents'] ? ' · noch ' . forma_h(mv_eur($bal)) . ' drauf' : '' ?></dd>
                    <dt>Gekauft</dt><dd><?= forma_h($v['buyer_name']) ?><?= $v['buyer_email'] !== '' ? ' · ' . forma_h($v['buyer_email']) : '' ?><?= $v['buyer_email'] !== '' ? ((int)$v['email_ok'] === 1 ? ' · <span class="pill">E-Mail bestätigt, Gutschein verschickt</span>' : ' · <span class="pill wait">E-Mail noch nicht bestätigt, Gutschein wartet</span>') : '' ?></dd>
                    <?php if ($v['recipient'] !== '' || $v['sender'] !== ''): ?><dt>Für</dt><dd><?= forma_h($v['recipient'] ?: '–') ?><?= $v['sender'] !== '' ? ' · von ' . forma_h($v['sender']) : '' ?></dd><?php endif; ?>
                    <dt>Bezahlt</dt><dd><?= forma_h(date('d.m.Y', strtotime((string)$v['paid_at']))) ?> · <?= forma_h(['stripe' => 'online', 'bar' => 'bar', 'geschenk' => 'Geschenk von dir, kostenlos'][$v['pay_method']] ?? $v['pay_method']) ?><?= $v['via'] === 'web' ? ' · über die Website' : ' · von dir ausgestellt' ?></dd>
                    <dt>Rechnung</dt><dd><?= $inv ? '<a href="?view=rechnung_pdf&amp;id=' . (int)$inv['id'] . '" target="_blank">' . forma_h($inv['nr']) . '</a>' : ($v['kind'] === 'geschenk' ? 'keine (Geschenk)' : ((int)$v['test'] ? 'keine (Testmodus)' : '–')) ?></dd>
                    <dt>Design</dt><dd><?= forma_h(MVG_DESIGNS[(int)$v['design']]['de']) ?> · <?= $v['lang'] === 'en' ? 'Englisch' : 'Deutsch' ?></dd>
                    <?php if ($uses): ?><dt>Eingelöst</dt><dd><?php foreach ($uses as $u): ?><div><?= forma_h($u['when']) ?> · <?= forma_h($u['what']) ?> · <?= forma_h(mv_eur($u['cents'])) ?><?= $u['status'] === 'reserviert' ? ' (Zahlung läuft)' : '' ?></div><?php endforeach; ?></dd><?php endif; ?>
                </dl>
                <div class="acts">
                    <a class="btn btn-sm" href="?view=mvgutschein_pdf&amp;id=<?= (int)$v['id'] ?>" target="_blank">PDF ansehen</a>
                    <?php if ($v['buyer_email'] !== '' && (int)$v['email_ok'] !== 1): ?><form method="post"><?= $hid ?><button class="btn btn-ghost btn-sm" name="action" value="mm_vg_reconfirm">Bestätigungsmail nochmal</button></form>
                    <?php elseif ($v['buyer_email'] !== ''): ?><form method="post" onsubmit="return confirm('Gutschein-Mail mit PDF noch einmal an <?= forma_h($v['buyer_email']) ?> schicken?')"><?= $hid ?><button class="btn btn-ghost btn-sm" name="action" value="mm_vg_resend">Mail nochmal schicken</button></form><?php endif; ?>
                    <?php if ($bal > 0): ?>
                    <details><summary>Betrag von Hand abziehen</summary>
                        <form method="post" class="acts"><?= $hid ?><input type="hidden" name="action" value="mm_vg_deduct">
                            <input name="amount" inputmode="decimal" placeholder="Betrag in €" style="max-width:9em" required>
                            <input name="note" placeholder="wofür, z. B. Einzelstunde im Studio" style="max-width:20em">
                            <button class="btn btn-sm" type="submit">abziehen</button></form>
                        <p class="small muted">Für Einlösungen, die nicht online laufen. Online werden Gutscheine beim Buchen automatisch abgezogen.</p>
                    </details>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <h2 id="neu">＋ Gutschein ausstellen</h2>
        <form method="post" class="vg-form" id="vgf"><?= $csrf ?><input type="hidden" name="action" value="mm_vg_create">
            <label>Gutschein
                <select name="product" id="vg-prod">
                    <?php foreach ($ps as $k => $p): ?><option value="<?= $k ?>" data-cents="<?= (int)$p['cents'] ?>"><?= forma_h($k === 'wert' ? 'Wertgutschein (Betrag frei)' : $p['name']['de'] . ' · ' . mv_eur((int)$p['cents'])) ?></option><?php endforeach; ?>
                </select></label>
            <label id="vg-amt" hidden>Betrag in €<input name="amount" inputmode="decimal" placeholder="100"></label>
            <div class="g2">
                <label>Design<select name="design"><?php foreach (MVG_DESIGNS as $d => $n): ?><option value="<?= $d ?>"><?= forma_h($n['de']) ?></option><?php endforeach; ?></select></label>
                <label>Sprache<select name="lang"><option value="de">Deutsch</option><option value="en">Englisch</option></select></label>
            </div>
            <div class="g2">
                <label>Für (steht auf dem Gutschein)<input name="recipient" maxlength="60"></label>
                <label>Von<input name="sender" maxlength="60"></label>
            </div>
            <label>Ein paar Worte (optional)<input name="message" maxlength="200"></label>
            <div class="g2">
                <label>Käuferin: Name<input name="buyer_name" maxlength="120" required></label>
                <label>E-Mail (für PDF und Rechnung)<input type="email" name="buyer_email" maxlength="160"></label>
            </div>
            <div><p class="small muted" style="margin:0 0 6px">Zahlung</p>
                <div class="radio-row">
                    <label><input type="radio" name="pay" value="link" checked> Bezahl-Link / QR-Code (online)</label>
                    <label><input type="radio" name="pay" value="bar"> schon bar bezahlt</label>
                    <label><input type="radio" name="pay" value="geschenk"> Geschenk von mir (kostenlos, ohne Rechnung)</label>
                </div></div>
            <label style="display:flex;gap:8px;align-items:center;color:var(--ink)"><input type="checkbox" name="send_mail" value="1" checked> Bezahl-Link auch per E-Mail schicken</label>
            <label>Notiz (nur für dich)<input name="note" maxlength="200"></label>
            <div><button class="btn" type="submit">Gutschein ausstellen</button></div>
            <p class="small muted">Gutschein-Nummer, Code und Rechnung gibt es erst, wenn Stripe die Zahlung meldet (oder du „bar bezahlt“ wählst). Per Mail kommt der Gutschein erst, wenn die Käuferin ihre E-Mail-Adresse bestätigt hat; ausdrucken kannst du ihn hier jederzeit über „PDF ansehen“. Bei „bar bezahlt“ sofort, mit Vermerk „bar bezahlt“ auf der Rechnung. Ein Geschenk von dir bekommt keine Rechnung.</p>
        </form>
        <script src="qrcode.js"></script>
        <script>
        (function(){
            var box=document.getElementById('vg-qrcode');
            if(box&&window.qrcode){var q=qrcode(0,'M');q.addData(box.dataset.url);q.make();box.innerHTML=q.createSvgTag(6,2);}
            document.querySelectorAll('[data-copy]').forEach(function(b){b.addEventListener('click',function(){var t=b.dataset.copy;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent='Kopiert ✓'}).catch(function(){prompt('Link kopieren:',t)})})});
            document.querySelectorAll('[data-copy-from]').forEach(function(b){b.addEventListener('click',function(){var t=document.getElementById(b.dataset.copyFrom);t.select();(navigator.clipboard?navigator.clipboard.writeText(t.value):Promise.reject()).then(function(){b.textContent='Nachricht kopiert ✓'}).catch(function(){document.execCommand('copy');b.textContent='Nachricht kopiert ✓'})})});
            document.querySelectorAll('[data-wa-from]').forEach(function(a){a.addEventListener('click',function(){a.href='https://wa.me/?text='+encodeURIComponent(document.getElementById(a.dataset.waFrom).value)})});
            var r=document.getElementById('vg-result');
            if(r){var n=0,t=setInterval(function(){if(++n>300){clearInterval(t);return}
                fetch('?view=mvgutschein_status&id='+r.dataset.id,{cache:'no-store'}).then(function(x){return x.json()}).then(function(d){
                    if(d&&(d.status==='bezahlt'||d.status==='eingeloest')){clearInterval(t);document.getElementById('vg-wait').hidden=true;document.getElementById('vg-nr').textContent=d.nr;document.getElementById('vg-paid').hidden=false;try{navigator.vibrate&&navigator.vibrate(120)}catch(e){}}
                }).catch(function(){})},4000)}
            var s=document.getElementById('vg-prod'),a=document.getElementById('vg-amt');
            function u(){a.hidden=s.value!=='wert';a.querySelector('input').required=s.value==='wert'}
            s.addEventListener('change',u);u();
        })();
        </script>
        <?php
    });
    exit;
}

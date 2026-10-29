<?php
// „Neu reingekommen“ auf der FORMA-Startseite der Verwaltung (Vivien, 29.10.2026: „sobald eine Buchung reinkommt,
// will ich sofort sehen, wer was gebucht oder gekauft hat, ohne zu suchen“). Letzte 7 Tage, neueste zuerst.
// Alles seit dem letzten Besuch ist als „neu“ markiert (Setting admin_feed_seen).
declare(strict_types=1);

function admin_feed(PDO $pdo, int $days = 7): array
{
    $since = date('c', strtotime('-' . $days . ' days'));
    $out = [];
    $add = function (string $at, string $ic, string $text, string $sub, string $href) use (&$out) { if ($at !== '') $out[] = compact('at', 'ic', 'text', 'sub', 'href'); };
    try { // Kursbuchungen (bestätigt = bezahlt bzw. ohne Zahlung reserviert)
        $st = $pdo->prepare("SELECT b.*, c.title AS ctitle, c.date_text FROM bookings b JOIN courses c ON c.id = b.course_id
            WHERE b.status = 'bestaetigt' AND COALESCE(b.paid_at, b.created_at) >= ? ORDER BY b.id DESC LIMIT 60");
        $st->execute([$since]);
        foreach ($st->fetchAll() as $b) {
            $p = (int)$b['persons'];
            $by = (string)$b['manual_pay'] !== '' ? ' (von dir eingetragen)' : '';
            $extra = [];
            $extra[] = $p . ' ' . ($p === 1 ? 'Person' : 'Personen');
            if ((int)$b['voucher_id'] > 0) $extra[] = 'mit Gutschein';
            if ((int)$b['amount_cents'] > 0) $extra[] = forma_euro((int)$b['amount_cents']) . ' online bezahlt';
            elseif ($b['manual_pay'] === 'bar') $extra[] = 'bar bezahlt';
            elseif ($b['manual_pay'] === 'vorort') $extra[] = 'zahlt vor Ort';
            $add((string)($b['paid_at'] ?: $b['created_at']), '🗓', $b['name'] . ' hat ' . preg_replace('/\s*·.*$/u', '', (string)$b['ctitle']) . ' gebucht' . $by,
                preg_replace('/\s*·.*$/u', '', (string)$b['date_text']) . ' · ' . implode(' · ', $extra), '?view=course&id=' . (int)$b['course_id']);
        }
    } catch (Throwable $e) {}
    try { // Gutscheine: gekauft/bezahlt, in der Kasse ausgestellt (noch offen), eingelöst
        $st = $pdo->prepare("SELECT * FROM vouchers WHERE (paid_at >= ? OR redeemed_at >= ? OR (via = 'kasse' AND status = 'offen' AND created_at >= ?)) AND COALESCE(kind,'kauf') <> 'alt' ORDER BY id DESC LIMIT 60");
        $st->execute([$since, $since, $since]);
        foreach ($st->fetchAll() as $v) {
            $who = trim((string)$v['buyer_name']) ?: 'Jemand';
            $what = forma_voucher_title($v);
            $for = trim((string)$v['recipient']) !== '' ? ' · für ' . $v['recipient'] : '';
            if ((string)$v['paid_at'] !== '' && $v['paid_at'] >= $since) {
                $how = $v['pay_method'] === 'bar' ? 'bar bezahlt' : ($v['via'] === 'kasse' ? 'per QR/Link bezahlt' : 'online gekauft');
                $add((string)$v['paid_at'], '🎟', $who . ' hat einen Gutschein ' . ($v['via'] === 'kasse' ? 'bezahlt' : 'gekauft'), $what . ' · ' . forma_euro((int)$v['amount_cents']) . ' · ' . $how . ($v['nr'] ? ' · ' . $v['nr'] : '') . $for, '?view=gutscheine');
            } elseif ($v['status'] === 'offen') {
                $add((string)$v['created_at'], '⏳', 'Gutschein für ' . $who . ' ausgestellt, noch nicht bezahlt', $what . ' · ' . forma_euro((int)$v['amount_cents']) . $for, '?view=gutscheine');
            }
            if ((string)$v['redeemed_at'] !== '' && $v['redeemed_at'] >= $since) $add((string)$v['redeemed_at'], '✓', 'Gutschein eingelöst' . ($v['nr'] ? ' (' . $v['nr'] . ')' : ''), $what . ' · ' . $who, '?view=gutscheine');
        }
    } catch (Throwable $e) {}
    try { // Private Pottery
        $st = $pdo->prepare("SELECT * FROM private_bookings WHERE status IN ('bezahlt','anfrage') AND COALESCE(paid_at, created_at) >= ? ORDER BY id DESC LIMIT 40");
        $st->execute([$since]);
        foreach ($st->fetchAll() as $p) {
            $paid = $p['status'] === 'bezahlt';
            $add((string)($p['paid_at'] ?: $p['created_at']), '🏺', $p['name'] . ($paid ? ' hat Private Pottery gebucht' : ' hat Private Pottery angefragt'),
                ((int)$p['hours'] ? forma_hours_text((float)$p['hours']) . ' · ' : '') . ((int)$p['persons'] > 1 ? (int)$p['persons'] . ' Personen · ' : '') . ($paid ? forma_euro((int)$p['amount_cents']) . ' bezahlt' : 'noch ohne Zahlung') . (trim((string)$p['wish_date']) !== '' ? ' · Wunsch ' . date('j.n.', strtotime((string)$p['wish_date'])) : ''), '?view=private');
        }
    } catch (Throwable $e) {}
    try { // Atelier-Kasse (Pottery bemalen)
        $st = $pdo->prepare("SELECT * FROM kasse_sales WHERE status = 'bezahlt' AND paid_at >= ? ORDER BY id DESC LIMIT 40");
        $st->execute([$since]);
        foreach ($st->fetchAll() as $k) $add((string)$k['paid_at'], '🎨', $k['name'] . ' hat an der Kasse bezahlt', forma_euro((int)$k['amount_cents']) . ' · ' . ($k['method'] === 'bar' ? 'bar' : 'per Link'), '?view=kasse');
    } catch (Throwable $e) {}
    try { // Templates
        $st = $pdo->prepare("SELECT * FROM template_orders WHERE status = 'bezahlt' AND paid_at >= ? ORDER BY id DESC LIMIT 40");
        $st->execute([$since]);
        foreach ($st->fetchAll() as $t) $add((string)$t['paid_at'], '📄', $t['name'] . ' hat ein Template gekauft', $t['title'] . ' · ' . forma_euro((int)$t['amount_cents']), '?view=templates');
    } catch (Throwable $e) {}
    try { // Gruppen-Angebote: bestätigt / bezahlt
        $st = $pdo->prepare("SELECT * FROM offers WHERE confirmed_at >= ? OR paid_at >= ? ORDER BY id DESC LIMIT 20");
        $st->execute([$since, $since]);
        foreach ($st->fetchAll() as $o) {
            if ((string)$o['confirmed_at'] !== '' && $o['confirmed_at'] >= $since) $add((string)$o['confirmed_at'], '📨', $o['org'] . ' hat dein Angebot bestätigt', forma_offer_kids($o) . ' Kinder · ' . forma_euro(forma_offer_total($o)), '?view=angebote&id=' . (int)$o['id'] . '#a' . (int)$o['id']);
            if ((string)$o['paid_at'] !== '' && $o['paid_at'] >= $since) $add((string)$o['paid_at'], '💶', $o['org'] . ' hat bezahlt', forma_euro(forma_offer_total($o)) . ($o['pay_method'] === 'bar' ? ' · bar' : ' · online'), '?view=angebote&id=' . (int)$o['id'] . '#a' . (int)$o['id']);
        }
    } catch (Throwable $e) {}
    usort($out, fn($a, $b) => strcmp($b['at'], $a['at']));
    return $out;
}

function admin_feed_when(string $at): string
{
    $t = strtotime($at);
    $d = date('Y-m-d', $t);
    if ($d === date('Y-m-d')) return 'heute ' . date('G:i', $t);
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'gestern ' . date('G:i', $t);
    return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)date('w', $t)] . ', ' . date('j.n.', $t);
}

/** Box „Neu reingekommen“. $self = Verwaltungs-URL. Merkt den Besuch für die „neu“-Punkte. */
function admin_feed_box(PDO $pdo, string $self): void
{
    $items = admin_feed($pdo);
    $seen = (string)(forma_setting($pdo, 'admin_feed_seen') ?? '');
    forma_set_setting($pdo, 'admin_feed_seen', date('c'));
    $new = count(array_filter($items, fn($i) => $i['at'] > $seen));
    $h = 'forma_h'; ?>
    <section class="card feed" id="neu">
        <div class="feed-h"><h2>Neu reingekommen</h2><span class="muted small"><?= $new ? '<b class="feed-n">' . $new . ' neu</b> seit deinem letzten Besuch · ' : '' ?>letzte 7 Tage</span></div>
        <?php if (!$items): ?><p class="muted">In den letzten 7 Tagen ist noch nichts reingekommen.</p><?php endif; ?>
        <?php foreach ($items as $i => $it): if ($i === 6): ?><details class="feed-more"><summary><?= count($items) - 6 ?> weitere anzeigen</summary><?php endif; ?>
            <a class="feed-it<?= $it['at'] > $seen ? ' is-new' : '' ?>" href="<?= $h($self . $it['href']) ?>"><span class="feed-ic"><?= $it['ic'] ?></span><span class="feed-tx"><strong><?= $h($it['text']) ?></strong><span class="muted small"><?= $h($it['sub']) ?></span></span><span class="feed-at muted small"><?= $h(admin_feed_when($it['at'])) ?></span></a>
        <?php endforeach; if (count($items) > 6): ?></details><?php endif; ?>
    </section>
    <style>
    .feed{margin:14px 0}.feed-h{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:.3rem 1rem;margin-bottom:.4rem}.feed-h h2{margin:0}
    .feed-n{color:#8C2C1D}
    .feed-it{display:flex;align-items:flex-start;gap:.8rem;padding:.7rem .4rem;border-top:1px solid rgba(51,33,27,.1);text-decoration:none;color:inherit;border-radius:12px}
    .feed-it:first-of-type{border-top:0}.feed-it:hover{background:rgba(238,226,212,.55)}
    .feed-ic{flex:0 0 2.2rem;height:2.2rem;display:grid;place-items:center;border-radius:50%;background:#EEE2D4;font-size:1.05rem}
    .feed-it.is-new .feed-ic{background:#F6E3DC;box-shadow:0 0 0 2px #8C2C1D;position:relative}.feed-it.is-new .feed-ic::after{content:"";position:absolute;right:-2px;top:-2px;width:9px;height:9px;border-radius:50%;background:#8C2C1D}
    .feed-tx{flex:1;min-width:0;display:flex;flex-direction:column;gap:.1rem}.feed-tx strong{font-weight:500}
    .feed-at{white-space:nowrap;padding-top:.15rem}
    .feed-more summary{cursor:pointer;color:#8C2C1D;padding:.6rem .4rem;font-size:.95rem}
    @media(max-width:560px){.feed-it{flex-wrap:wrap}.feed-at{order:-1;flex-basis:100%;padding:0 0 0 3rem}}
    </style>
<?php }

/** Kleines Feld statt einzelner Angebots-Boxen: wie viele Angebote unterwegs sind. */
function admin_offer_chip(PDO $pdo, string $self): void
{
    $open = 0; $unpaid = 0;
    foreach (forma_offers($pdo) as $o) { if ($o['status'] === 'offen') $open++; elseif ($o['status'] === 'bestaetigt') $unpaid++; }
    if (!$open && !$unpaid) return; ?>
    <a class="card notice offer-chip" href="<?= forma_h($self) ?>?view=angebote"><span class="ic"><?= admin_icon('angebot') ?></span><span><strong><?= $open ? $open . ' ' . ($open === 1 ? 'Angebot' : 'Angebote') . ' verschickt' : '' ?><?= $open && $unpaid ? ' · ' : '' ?><?= $unpaid ? $unpaid . ' bestätigt, noch nicht bezahlt' : '' ?></strong><br><span class="muted small"><?= $open ? 'warten auf Bestätigung · ' : '' ?>Alle Angebote ansehen →</span></span></a>
<?php }

<?php
// moveciamovement.com/gutschein – Gutscheine kaufen und einlösen (Vivien, 26.10.2026).
// Unfold, Method (1/3/12 Monate), Prä- & Postnatal Pakete (Studio/online) und Wertgutscheine, zwei Designs zum Ausdrucken.
// ?ok=ID&k=TOKEN  Danke-Ansicht mit PDF  ·  ?code=…  Gutschein einlösen  ·  ?abbruch=1 Zahlung abgebrochen
declare(strict_types=1);
require dirname(__DIR__, 2) . '/api/mv-gutschein-lib.php';
require __DIR__ . '/../assets/layout.inc.php';
header('Cache-Control: no-store');
$pdo = forma_db();
mvg_schema($pdo);
try { mvg_tick($pdo); } catch (Throwable $e) { error_log('MVG tick: ' . $e->getMessage()); }
$site = mm_site();
$ps = mvg_products();
$eu = fn(int $x) => mv_eur($x);
$h = 'forma_h';

$done = null;
if (isset($_GET['ok']) || isset($_GET['offen'])) {
    $done = mvg_auth($pdo, (int)($_GET['ok'] ?? $_GET['offen']), (string)($_GET['k'] ?? ''));
    if ($done && $done['status'] === 'offen') { try { mvg_check($pdo, $done); $done = mvg_get($pdo, (int)$done['id']); } catch (Throwable $e) {} }
}
$code = mvg_norm((string)($_GET['code'] ?? ''));
$natal = ['studio' => [], 'online' => []];
foreach ($ps as $k => $p) if ($p['group'] === 'natal') $natal[$p['online'] ? 'online' : 'studio'][$k] = $p;
$private = $done || $code !== '' || isset($_GET['abbruch']);
if ($private) header('X-Robots-Tag: noindex');
mvl_head('Gutscheine · Yoga, Mindful Movement & Prä-/Postnatal Training verschenken | Movecia',
    'Schenk ein Stück Ruhe: Gutscheine für Unfold, die Movecia Method, Prä- & Postnatal Training mit Vivien Malonga oder einen Wertgutschein. Zwei schlichte Designs zum Ausdrucken, sofort per E-Mail.',
    $site . '/gutschein/', '/img/movecia/og-start.jpg', '<style>
.gs-hero h1{font-size:clamp(3rem,8vw,6.4rem)}
.gs-grid{display:grid;gap:clamp(1.4rem,3vw,2.6rem);margin-top:1.4rem}
@media(min-width:1000px){.gs-grid{grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);align-items:start}.gs-side{position:sticky;top:110px}}
.gs-step{background:var(--card);border-radius:var(--r);padding:clamp(1.2rem,2.6vw,2rem);margin-bottom:14px}
.gs-step>h3{font-size:1.7rem;font-weight:500;display:flex;align-items:baseline;gap:.6rem;margin-bottom:1rem}
.gs-step>h3 i{font-style:normal;font-family:var(--sans);font-size:.8rem;letter-spacing:.14em;color:var(--plum);border:1px solid var(--plum);border-radius:999px;width:2em;height:2em;display:inline-grid;place-items:center;flex:none;transform:translateY(-.2em)}
.gs-types{display:grid;gap:10px;grid-template-columns:repeat(2,minmax(0,1fr))}
@media(min-width:700px){.gs-types{grid-template-columns:repeat(4,minmax(0,1fr))}}
.gs-type{position:relative;border:1px solid var(--line);border-radius:18px;padding:1rem .95rem;cursor:pointer;background:#fff;display:flex;flex-direction:column;gap:.15rem;transition:border-color .2s,box-shadow .2s}
.gs-type input{position:absolute;opacity:0;pointer-events:none}
.gs-type b{font-family:var(--serif);font-weight:500;font-size:1.45rem;line-height:1.1}
.gs-type small{color:var(--muted);font-size:.86rem;line-height:1.35}
.gs-type .pr{margin-top:auto;padding-top:.5rem;color:var(--plum);font-size:.92rem}
.gs-type:has(input:checked){border-color:var(--plum);box-shadow:0 0 0 2px var(--plum)}
.gs-sub{margin-top:1rem;display:none}
.gs-sub.on{display:block}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip-r{position:relative}
.chip-r input{position:absolute;opacity:0;pointer-events:none}
.chip-r>span{display:inline-flex;flex-direction:column;border:1px solid var(--line);background:#fff;border-radius:14px;padding:.55rem .9rem;cursor:pointer;font-size:.95rem;line-height:1.3}
.chip-r>span small{color:var(--muted);font-size:.8rem}
.chip-r input:checked+span{border-color:var(--plum);box-shadow:0 0 0 1.5px var(--plum);background:var(--card)}
.chip-r input:focus-visible+span,.gs-type:has(input:focus-visible){outline:2px solid var(--plum);outline-offset:2px}
.gs-mode{display:inline-flex;background:#fff;border:1px solid var(--line);border-radius:999px;padding:3px;margin-bottom:.8rem}
.gs-mode button{font:inherit;font-size:.92rem;border:0;background:none;border-radius:999px;padding:.4em 1.1em;cursor:pointer;color:var(--muted)}
.gs-mode button[aria-pressed=true]{background:var(--plum);color:var(--on)}
.gs-own{display:flex;align-items:center;gap:.5rem;margin-top:.7rem;max-width:16rem}
.gs-own input{width:8rem!important}
.gs-designs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.gs-design{position:relative;cursor:pointer;border-radius:16px;border:1px solid var(--line);background:#fff;padding:8px;display:flex;flex-direction:column;gap:.4rem}
.gs-design input{position:absolute;opacity:0;pointer-events:none}
.gs-design img{width:100%;height:auto;aspect-ratio:297/210;object-fit:cover;border-radius:10px;display:block;box-shadow:0 1px 3px rgba(46,37,41,.08)}
.gs-design span{font-size:.92rem;padding:0 .3rem .2rem}
.gs-design:has(input:checked){border-color:var(--plum);box-shadow:0 0 0 2px var(--plum)}
.gs-fields{display:grid;gap:.8rem}
@media(min-width:700px){.gs-fields.two{grid-template-columns:1fr 1fr}}
.gs-fields label{display:grid;gap:.3rem;font-size:.92rem;color:var(--muted)}
.gs-fields textarea{min-height:5.2em;resize:vertical}
.gs-chk{display:flex;gap:.6rem;align-items:flex-start;font-size:.92rem;color:var(--muted);margin-top:.4rem}
.gs-chk input{margin-top:.3rem;accent-color:var(--plum);width:18px;height:18px;flex:none}
/* Vorschau: Text über dem Hintergrund, Maße wie im PDF (A4 quer) */
.pv{container-type:inline-size;position:relative;aspect-ratio:297/210;border-radius:14px;overflow:hidden;background:#fff center/cover no-repeat;box-shadow:0 30px 60px -36px rgba(74,58,65,.55),0 2px 6px rgba(46,37,41,.06)}
.pv .tx{position:absolute;color:#2E2529;line-height:1.05}
.pv[data-d="1"] .tx{left:50.5%;top:23.3%;width:41.7%;text-align:left}
.pv[data-d="2"] .tx{left:13%;top:26.4%;width:74%;text-align:center}
.pv .lb{font:600 1.02cqw/1 var(--sans);letter-spacing:.42em;color:#7D5E68}
.pv .bg{font-family:var(--serif);font-weight:500;font-size:5.2cqw;margin-top:1.4cqw;line-height:1}
.pv .bg.sm{font-size:4cqw}
.pv .sb{font-family:var(--serif);font-style:italic;font-size:2.02cqw;color:#7D5E68;margin-top:.7cqw}
.pv .ds{font:400 1.02cqw/1.55 var(--sans);color:#7D7076;margin-top:1.4cqw}
.pv[data-d="2"] .ds{margin-left:auto;margin-right:auto;width:64%}
.pv .wh{font-family:var(--serif);font-size:1.9cqw;margin-top:2cqw}
.pv[data-d="1"] .wh div{border-bottom:.08cqw solid #DDD2C6;width:71%;padding-bottom:.25cqw;margin-bottom:1cqw}
.pv .wh small{font:400 .9cqw var(--sans);color:#7D7076;display:inline-block;width:4.1cqw}
.pv[data-d="2"] .wh small{width:auto;margin-right:.6cqw}
.pv .ms{font-family:var(--serif);font-style:italic;font-size:1.6cqw;color:#7D5E68;margin-top:1.2cqw;line-height:1.3}
.pv .ft{position:absolute;font:400 .88cqw var(--sans);color:#7D7076}
.pv[data-d="1"] .ft{left:50.5%;top:84.6%}
.pv[data-d="2"] .ft{left:0;right:0;top:88.4%;text-align:center}
.gs-sum{display:flex;justify-content:space-between;align-items:baseline;gap:1rem;margin-top:1.2rem;padding-top:1rem;border-top:1px solid var(--line)}
.gs-sum b{font-family:var(--serif);font-weight:500;font-size:2.2rem;line-height:1}
.gs-how{display:grid;gap:.6rem;margin-top:1.2rem;padding:0;list-style:none;counter-reset:s}
.gs-how li{counter-increment:s;display:grid;grid-template-columns:2.2rem 1fr;gap:.6rem;align-items:start;color:var(--muted);font-size:.95rem}
.gs-how li::before{content:counter(s);display:grid;place-items:center;width:2rem;height:2rem;border-radius:50%;border:1px solid var(--plum);color:var(--plum);font-family:var(--serif);font-size:1.1rem}
.gs-done{background:var(--card);border-radius:var(--r);padding:clamp(1.4rem,3vw,2.4rem);display:grid;gap:1.2rem}
@media(min-width:900px){.gs-done{grid-template-columns:1fr 1fr;align-items:center}}
.gs-redeem{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem}
.gs-redeem input{flex:1 1 14em;letter-spacing:.08em;text-transform:uppercase}
.gs-res{margin-top:1rem}
.gs-res .cta{margin-top:.8rem}
</style>', []);
?>
<body>
<?php mvl_top('gutschein'); ?>
<main>
<?php if ($done && in_array($done['status'], ['bezahlt', 'eingeloest'], true) && (int)$done['email_ok'] !== 1): ?>
<section class="w"><div class="in hero-x gs-hero">
  <p class="k"><span class="de">Danke, deine Zahlung ist angekommen</span><span class="en">Thank you, your payment has arrived</span></p>
  <h1><span class="de">Nur noch <em>ein Klick</em></span><span class="en">Just <em>one more click</em></span></h1>
  <p class="lead"><span class="de">Ich habe dir eine E-Mail an <?= $h($done['buyer_email']) ?> geschickt. Bitte bestätige darin deine E-Mail-Adresse, dann bekommst du deinen Gutschein als PDF zum Ausdrucken mit seinem Code und deine Rechnung. Schau auch im Spam-Ordner nach, falls sie nicht gleich da ist.</span><span class="en">I have sent an email to <?= $h($done['buyer_email']) ?>. Please confirm your email address there, then you will receive your voucher as a PDF to print, with its code, and your invoice. Have a look in your spam folder too if it does not arrive straight away.</span></p>
</div></section>
<?php elseif ($done && in_array($done['status'], ['bezahlt', 'eingeloest'], true)): $L = $done['lang'] === 'en'; ?>
<section class="w"><div class="in hero-x gs-hero">
  <p class="k"><span class="de">Danke von Herzen</span><span class="en">Thank you so much</span></p>
  <?php if (isset($_GET['bestaetigt'])): ?><p class="note" style="margin-bottom:1rem;max-width:40em"><span class="de">Danke, deine E-Mail-Adresse ist bestätigt. Gutschein und Rechnung sind unterwegs zu dir.</span><span class="en">Thank you, your email is confirmed. Voucher and invoice are on their way to you.</span></p><?php endif; ?>
  <h1><span class="de">Dein Gutschein <em>ist da</em></span><span class="en">Your voucher <em>is ready</em></span></h1>
  <div class="gs-done" style="margin-top:2rem">
    <div>
      <p class="k"><?= $h($done['nr']) ?></p>
      <h2 style="margin:.4rem 0 .6rem;font-size:clamp(1.9rem,3.4vw,2.8rem)"><?= $h(mvg_title($done, $done['lang'])) ?></h2>
      <p class="mut"><span class="de">Ich habe dir den Gutschein als PDF per E-Mail geschickt<?= $done['buyer_email'] !== '' ? ' (an ' . $h($done['buyer_email']) . ')' : '' ?>. Die Rechnung kommt in einer eigenen E-Mail. Druck ihn am besten auf weißes Papier, A4 quer.</span><span class="en">I have sent you the voucher as a PDF by email<?= $done['buyer_email'] !== '' ? ' (to ' . $h($done['buyer_email']) . ')' : '' ?>. The invoice comes in a separate email. Print it on white paper, A4 landscape.</span></p>
      <div class="cta"><a class="btn" href="<?= $h(mvg_pdf_link($done)) ?>" target="_blank" rel="noopener"><span class="de">Gutschein ansehen</span><span class="en">View voucher</span></a><a class="btn line" href="<?= $h(mvg_pdf_link($done)) ?>&amp;dl=1"><span class="de">Herunterladen</span><span class="en">Download</span></a></div>
    </div>
    <div class="pv" data-d="<?= (int)$done['design'] ?>" style="background-image:url(/img/movecia/gutschein/design<?= (int)$done['design'] ?>.jpg)" aria-hidden="true">
      <?php $dp = mvg_product($done); $big = $done['product'] === 'wert' ? $eu((int)$done['amount_cents']) : $dp['title'][$L ? 'en' : 'de']; ?>
      <div class="tx"><div class="lb"><?= $L ? 'GIFT VOUCHER' : 'GUTSCHEIN' ?></div><div class="bg<?= mb_strlen($big) > 16 ? ' sm' : '' ?>"><?= $h($big) ?></div><div class="sb"><?= $h($done['product'] === 'wert' ? ($L ? 'for everything at Movecia' : 'für alles bei Movecia') : $dp['sub'][$L ? 'en' : 'de']) ?></div>
        <div class="wh"><?php if ($done['recipient'] !== ''): ?><div><small><?= $L ? 'for' : 'für' ?></small> <?= $h($done['recipient']) ?></div><?php endif; ?><?php if ($done['sender'] !== ''): ?><div><small><?= $L ? 'from' : 'von' ?></small> <?= $h($done['sender']) ?></div><?php endif; ?></div></div>
      <div class="ft">Nr. <?= $h($done['nr']) ?> · Code <?= $h($done['code']) ?></div>
    </div>
  </div>
</div></section>
<?php elseif ($done && $done['status'] === 'offen'): ?>
<section class="w"><div class="in hero-x gs-hero">
  <p class="k"><span class="de">Fast geschafft</span><span class="en">Almost there</span></p>
  <h1><span class="de">Noch nicht <em>bezahlt</em></span><span class="en">Not paid <em>yet</em></span></h1>
  <p class="lead"><span class="de">Die Zahlung für deinen Gutschein ist noch nicht angekommen. Wenn du gerade bezahlt hast, lade die Seite in einer Minute neu. Sonst kannst du es hier noch einmal versuchen.</span><span class="en">The payment for your voucher has not arrived yet. If you have just paid, reload this page in a minute. Otherwise you can try again here.</span></p>
  <div class="cta"><a class="btn" href="<?= $h(mvg_pay_link($done)) ?>"><span class="de">Jetzt bezahlen</span><span class="en">Pay now</span> · <?= $h($eu((int)$done['amount_cents'])) ?></a></div>
</div></section>
<?php else: ?>
<section class="w"><div class="in hero-x gs-hero">
  <p class="k"><span class="de">Gutscheine zum Verschenken</span><span class="en">Gift vouchers</span></p>
  <h1><span class="de">Schenk ein Stück <em>Ruhe</em></span><span class="en">Give a little <em>calm</em></span></h1>
  <p class="sub"><span class="de">für jemanden, der sich Zeit für sich verdient hat.</span><span class="en">for someone who deserves some time for herself.</span></p>
  <p class="lead"><span class="de">Verschenk Unfold, Monate in der Method, ein Prä- &amp; Postnatal Paket oder einfach einen Betrag. Du suchst dir eines von zwei schlichten Designs aus und bekommst den Gutschein sofort als PDF zum Ausdrucken.</span><span class="en">Give Unfold, months of the Method, a pre- &amp; postnatal package or simply an amount. Choose one of two simple designs and get the voucher straight away as a PDF to print.</span></p>
  <?php if (isset($_GET['abbruch'])): ?><p class="note" style="margin-top:1.4rem;max-width:40em"><span class="de">Die Zahlung wurde abgebrochen, es wurde nichts abgebucht. Du kannst es unten einfach noch einmal versuchen.</span><span class="en">The payment was cancelled, nothing was charged. You can simply try again below.</span></p><?php endif; ?>
  <div class="cta"><a class="btn" href="#kaufen"><span class="de">Gutschein aussuchen</span><span class="en">Choose a voucher</span> →</a><a class="btn line" href="#einloesen"><span class="de">Gutschein einlösen</span><span class="en">Redeem a voucher</span></a></div>
</div></section>

<section class="w" id="kaufen"><div class="in">
<form id="gf" novalidate>
<div class="gs-grid">
  <div>
    <div class="gs-step">
      <h3><i>1</i><span class="de">Was möchtest du verschenken?</span><span class="en">What would you like to give?</span></h3>
      <div class="gs-types" role="radiogroup">
        <label class="gs-type"><input type="radio" name="grp" value="unfold" checked><b>Unfold</b><small><span class="de">3 Monate Begleitung</span><span class="en">3 months of guidance</span></small><span class="pr"><?= $h($eu($ps['unfold']['cents'])) ?></span></label>
        <label class="gs-type"><input type="radio" name="grp" value="method"><b>Method</b><small><span class="de">Monate on demand</span><span class="en">Months on demand</span></small><span class="pr"><span class="de">ab</span><span class="en">from</span> <?= $h($eu($ps['method_m1']['cents'])) ?></span></label>
        <label class="gs-type"><input type="radio" name="grp" value="natal"><b><span class="de">Prä- &amp; Postnatal</span><span class="en">Pre- &amp; postnatal</span></b><small><span class="de">1:1 Training</span><span class="en">1:1 training</span></small><span class="pr"><span class="de">ab</span><span class="en">from</span> <?= $h($eu(min(array_column(array_merge($natal['studio'], $natal['online']), 'cents')))) ?></span></label>
        <label class="gs-type"><input type="radio" name="grp" value="wert"><b><span class="de">Betrag</span><span class="en">Amount</span></b><small><span class="de">für alles bei Movecia</span><span class="en">for everything</span></small><span class="pr"><span class="de">frei wählbar</span><span class="en">your choice</span></span></label>
      </div>

      <div class="gs-sub on" data-grp="unfold">
        <p class="mut" style="font-size:.95rem"><span class="de">Ein Platz in Unfold – The Mindful Becoming: 3 Monate mit wöchentlichem Live-Call, zwei Bewegungsvideos pro Woche, Workbooks und kleinen Ritualen. Gilt für die nächste Runde mit freien Plätzen.</span><span class="en">A place in Unfold – The Mindful Becoming: 3 months with a weekly live call, two movement videos a week, workbooks and small rituals. Valid for the next round with free places.</span></p>
        <div class="chips" style="display:none"><label class="chip-r"><input type="radio" name="p_unfold" value="unfold" data-cents="<?= (int)$ps['unfold']['cents'] ?>" checked><span>Unfold</span></label></div>
      </div>
      <div class="gs-sub" data-grp="method">
        <div class="chips">
          <?php foreach (['method_m1', 'method_m3', 'method_m12'] as $i => $k): $p = $ps[$k]; ?>
          <label class="chip-r"><input type="radio" name="p_method" value="<?= $k ?>" data-cents="<?= (int)$p['cents'] ?>"<?= $i === 1 ? ' checked' : '' ?>><span><span class="de"><?= $h($p['title']['de']) ?></span><span class="en"><?= $h($p['title']['en']) ?></span><small><?= $h($eu((int)$p['cents'])) ?></small></span></label>
          <?php endforeach; ?>
        </div>
        <p class="mut" style="font-size:.9rem;margin-top:.7rem"><span class="de">Ohne Abo: Die Monate schalten sich mit dem Gutschein frei und enden von selbst.</span><span class="en">No subscription: the months unlock with the voucher and simply end.</span></p>
      </div>
      <div class="gs-sub" data-grp="natal">
        <div class="gs-mode" role="group"><button type="button" data-nm="studio" aria-pressed="true"><span class="de">Im Studio</span><span class="en">In the studio</span></button><button type="button" data-nm="online" aria-pressed="false">Online</button></div>
        <?php foreach ($natal as $md => $list): ?>
        <div class="chips" data-nmode="<?= $md ?>"<?= $md === 'online' ? ' hidden' : '' ?>>
          <?php foreach ($list as $k => $p): ?>
          <label class="chip-r"><input type="radio" name="p_natal" value="<?= $k ?>" data-cents="<?= (int)$p['cents'] ?>"<?= $k === 'natal_prae' ? ' checked' : '' ?>><span><span class="de"><?= $h($p['title']['de']) ?></span><span class="en"><?= $h($p['title']['en']) ?></span><small><?= $h($eu((int)$p['cents'])) ?> · <span class="de"><?= $h(explode(' · ', $p['sub']['de'])[0]) ?></span><span class="en"><?= $h(explode(' · ', $p['sub']['en'])[0]) ?></span></small></span></label>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="gs-sub" data-grp="wert">
        <div class="chips">
          <?php foreach ([5000, 10000, 15000, 25000] as $i => $c): ?><label class="chip-r"><input type="radio" name="w_amt" value="<?= $c ?>"<?= $i === 1 ? ' checked' : '' ?>><span><?= $h($eu($c)) ?></span></label><?php endforeach; ?>
          <label class="chip-r"><input type="radio" name="w_amt" value="own"><span><span class="de">eigener Betrag</span><span class="en">other amount</span></span></label>
        </div>
        <div class="gs-own" hidden><span>€</span><input type="text" inputmode="decimal" name="w_own" placeholder="80" maxlength="5" aria-label="Betrag in Euro"><small class="mut"><?= $h($eu(MVG_WERT_MIN)) ?> – <?= $h($eu(MVG_WERT_MAX)) ?></small></div>
        <p class="mut" style="font-size:.9rem;margin-top:.7rem"><span class="de">Einlösbar für Unfold, die Method und das Prä- &amp; Postnatal Training. Ein Rest bleibt auf dem Gutschein.</span><span class="en">For Unfold, the Method and pre- &amp; postnatal training. Any remaining value stays on the voucher.</span></p>
      </div>
    </div>

    <div class="gs-step">
      <h3><i>2</i><span class="de">Dein Design</span><span class="en">Your design</span></h3>
      <div class="gs-designs">
        <?php foreach (MVG_DESIGNS as $d => $n): ?>
        <label class="gs-design"><input type="radio" name="design" value="<?= $d ?>"<?= $d === 1 ? ' checked' : '' ?>><img src="/img/movecia/gutschein/design<?= $d ?>.jpg" alt="" loading="lazy" width="600" height="424"><span><span class="de"><?= $h($n['de']) ?></span><span class="en"><?= $h($n['en']) ?></span></span></label>
        <?php endforeach; ?>
      </div>
      <p class="mut" style="font-size:.9rem;margin-top:.7rem"><span class="de">Beide auf weißem Grund, damit sie zu Hause schön drucken: A4 quer, am besten auf etwas dickerem Papier.</span><span class="en">Both on a white background so they print nicely at home: A4 landscape, ideally on slightly thicker paper.</span></p>
    </div>

    <div class="gs-step">
      <h3><i>3</i><span class="de">Persönlich machen</span><span class="en">Make it personal</span> <small class="mut" style="font-family:var(--sans);font-size:.85rem"><span class="de">(optional)</span><span class="en">(optional)</span></small></h3>
      <div class="gs-fields two">
        <label><span class="de">Für</span><span class="en">For</span><input type="text" name="recipient" maxlength="60" placeholder="Anna"></label>
        <label><span class="de">Von</span><span class="en">From</span><input type="text" name="sender" maxlength="60" placeholder="Lisa"></label>
      </div>
      <div class="gs-fields" style="margin-top:.8rem">
        <label><span class="de">Ein paar Worte</span><span class="en">A few words</span><textarea name="message" maxlength="200" placeholder=""></textarea></label>
        <label><span class="de">Sprache des Gutscheins</span><span class="en">Voucher language</span><select name="vlang"><option value="de">Deutsch</option><option value="en">English</option></select></label>
      </div>
    </div>

    <div class="gs-step">
      <h3><i>4</i><span class="de">Deine Angaben</span><span class="en">Your details</span></h3>
      <div class="gs-fields two">
        <label><span class="de">Vor- und Nachname</span><span class="en">Full name</span><input type="text" name="buyer_name" autocomplete="name" required maxlength="120"></label>
        <label>E-Mail<input type="email" name="buyer_email" autocomplete="email" required maxlength="160"></label>
      </div>
      <label class="gs-chk"><input type="checkbox" name="consent" value="1" required><span><span class="de">Meine Angaben werden nur für den Gutschein, die Rechnung und die Zahlung verwendet (<a href="/terms/">Datenschutz &amp; Bedingungen</a>).</span><span class="en">My details are only used for the voucher, the invoice and the payment (<a href="/terms/">privacy &amp; terms</a>).</span></span></label>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="gs-sum"><span><span class="mut" id="sumt"></span></span><b id="sumc"></b></div>
      <div style="margin-top:1rem"><button class="btn" type="submit" id="gb"><span class="de">Weiter zur Zahlung</span><span class="en">Continue to payment</span> →</button></div>
      <p class="err" id="ge" hidden style="margin-top:1rem"></p>
      <p class="mut" style="font-size:.88rem;margin-top:.9rem"><span class="de">Sichere Zahlung über Stripe. Danach bestätigst du kurz deine E-Mail-Adresse und bekommst den Gutschein als PDF und die Rechnung.</span><span class="en">Secure payment with Stripe. Then you quickly confirm your email address and get the voucher as a PDF and the invoice.</span></p>
    </div>
  </div>

  <aside class="gs-side">
    <p class="k" style="margin-bottom:.8rem"><span class="de">Vorschau</span><span class="en">Preview</span></p>
    <div class="pv" id="pv" data-d="1" style="background-image:url(/img/movecia/gutschein/design1.jpg)" aria-hidden="true">
      <div class="tx"><div class="lb" id="pv-lb">GUTSCHEIN</div><div class="bg" id="pv-bg"></div><div class="sb" id="pv-sb"></div><div class="ds" id="pv-ds"></div><div class="wh" id="pv-wh"></div><div class="ms" id="pv-ms"></div></div>
      <div class="ft">Nr. MOV-<?= date('Y') ?>-0001 · Code MOV-····-····-····</div>
    </div>
    <ul class="gs-how">
      <li><span class="de">Du bezahlst sicher online, bestätigst kurz deine E-Mail-Adresse und bekommst den Gutschein gleich danach als PDF, mit Nummer und persönlichem Code.</span><span class="en">You pay securely online, quickly confirm your email address and get the voucher right after as a PDF, with a number and a personal code.</span></li>
      <li><span class="de">Ausdrucken, schön falten, verschenken. Auf der Rückseite steht, wie man ihn einlöst.</span><span class="en">Print, fold, give. The back explains how to redeem it.</span></li>
      <li><span class="de">Eingelöst wird online mit dem Code bei der Buchung. Kein Ablaufdatum.</span><span class="en">It is redeemed online with the code when booking. No expiry date.</span></li>
    </ul>
  </aside>
</div>
</form>
</div></section>
<?php endif; ?>

<section class="w sec" id="einloesen"><div class="in" style="max-width:900px">
  <p class="k"><span class="de">Gutschein bekommen?</span><span class="en">Received a voucher?</span></p>
  <h2 style="margin:.6rem 0 .8rem"><span class="de">Gutschein <em>einlösen</em></span><span class="en">Redeem your <em>voucher</em></span></h2>
  <p class="mut"><span class="de">Gib deinen Code ein, dann siehst du, wofür er gilt und wie viel noch drauf ist, und kommst direkt zur passenden Buchung.</span><span class="en">Enter your code to see what it is for and how much is left, and go straight to the right booking.</span></p>
  <div class="gs-redeem"><input type="text" id="rc" value="<?= $h($code) ?>" placeholder="MOV-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" maxlength="24" aria-label="Gutscheincode"><button class="btn" type="button" id="rb"><span class="de">Prüfen</span><span class="en">Check</span></button></div>
  <div class="gs-res" id="rr" hidden></div>
</div></section>

<section class="w sec"><div class="in" style="max-width:900px">
  <p class="k"><span class="de">Fragen</span><span class="en">Questions</span></p>
  <h2 style="margin:.6rem 0 1rem"><span class="de">Gut zu <em>wissen</em></span><span class="en">Good to <em>know</em></span></h2>
  <details class="q"><summary><span class="de">Wie lange gilt ein Gutschein?</span><span class="en">How long is a voucher valid?</span></summary><p><span class="de">Ohne Ablaufdatum. Ein Rest bleibt auf dem Gutschein, bis alles eingelöst ist.</span><span class="en">There is no expiry date. Any remaining value stays on the voucher until everything is used.</span></p></details>
  <details class="q"><summary><span class="de">Was, wenn der Preis bis dahin steigt?</span><span class="en">What if the price goes up?</span></summary><p><span class="de">Ein Gutschein für Unfold, die Method oder ein Prä- &amp; Postnatal Paket deckt genau dieses Angebot ganz, auch wenn es inzwischen mehr kostet. Bei einem Wertgutschein zählt der Betrag.</span><span class="en">A voucher for Unfold, the Method or a pre- &amp; postnatal package covers exactly that offer in full, even if it costs more by then. For a gift voucher with an amount, the amount counts.</span></p></details>
  <details class="q"><summary><span class="de">Kann ich einen Gutschein auch für etwas anderes verwenden?</span><span class="en">Can I use a voucher for something else?</span></summary><p><span class="de">Ja, dann zählt sein Wert. Ist das andere Angebot teurer, zahlst du die Differenz online, ist es günstiger, bleibt der Rest auf dem Gutschein.</span><span class="en">Yes, then its value counts. If the other offer costs more, you pay the difference online; if it costs less, the rest stays on the voucher.</span></p></details>
  <details class="q"><summary><span class="de">Unfold ist ausgebucht, was jetzt?</span><span class="en">Unfold is fully booked, what now?</span></summary><p><span class="de">Dann gilt der Gutschein für die nächste Runde. Schreib mir einfach, dann merke ich dir einen Platz vor.</span><span class="en">Then the voucher is valid for the next round. Simply write to me and I will note you down.</span></p></details>
  <details class="q"><summary><span class="de">Bekomme ich eine Rechnung?</span><span class="en">Do I get an invoice?</span></summary><p><span class="de">Ja, gleich nach der Zahlung per E-Mail, im Movecia-Design.</span><span class="en">Yes, right after the payment by email.</span></p></details>
</div></section>
</main>
<?php mvl_foot(); ?>
<script>
(function(){
  var root=document.documentElement;
  function de(){return root.dataset.l!=='en'}
  function eur(c){return '€ '+(c%100?(c/100).toFixed(2).replace('.',','):String(c/100).replace(/\B(?=(\d{3})+(?!\d))/g,'.'))}
  var P=<?= json_encode(array_map(fn($p) => ['g' => $p['group'], 'c' => $p['cents'], 't' => $p['title'], 's' => $p['sub'], 'd' => $p['desc'], 'n' => $p['name']], $ps), JSON_UNESCAPED_UNICODE) ?>;
  var f=document.getElementById('gf');
  // Einlösen
  var rb=document.getElementById('rb'),rc=document.getElementById('rc'),rr=document.getElementById('rr');
  async function redeem(){
    var c=rc.value.trim();if(!c)return;rb.disabled=true;
    try{var r=await fetch('/api/mv-gutschein.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'check',code:c,lang:de()?'de':'en'})});var j=await r.json();
      rr.hidden=false;
      if(!j.ok){rr.innerHTML='<p class="err"></p>';rr.firstChild.textContent=j.error;return}
      var D=de(),code=encodeURIComponent(c.toUpperCase()),q='?code='+code+(D?'':'&lang=en');
      var h='<div class="note"><p class="k" style="margin-bottom:.3rem">'+(D?'Gültig':'Valid')+'</p><p style="font-family:var(--serif);font-size:1.7rem;line-height:1.15"></p><p class="mut">'+(j.program?(D?'Deckt dieses Angebot ganz.':'Covers this offer in full.'):(D?'Noch drauf: ':'Value left: ')+j.balance_text)+'</p></div><div class="cta">';
      if(j.group==='wert'||!j.program){h+='<a class="btn" href="/unfold/'+q+'#buchen">Unfold</a><a class="btn line" href="/method/'+q+'#buchen">Method</a><a class="btn line" href="/natal/'+q+'#buchen">'+(D?'Prä- &amp; Postnatal':'Pre- &amp; postnatal')+'</a>'}
      else h+='<a class="btn" href="'+j.target+'">'+(D?'Jetzt einlösen':'Redeem now')+' →</a>';
      rr.innerHTML=h+'</div>';rr.querySelector('.note p:nth-child(2)').textContent=j.title;
    }catch(e){rr.hidden=false;rr.innerHTML='<p class="err">'+(de()?'Das hat gerade nicht geklappt.':'That did not work.')+'</p>'}
    finally{rb.disabled=false}
  }
  rb.addEventListener('click',redeem);rc.addEventListener('keydown',function(e){if(e.key==='Enter')redeem()});
  if(rc.value){redeem();setTimeout(function(){document.getElementById('einloesen').scrollIntoView()},50)}
  if(!f)return;
  var loaded=Date.now(),pv=document.getElementById('pv');
  var nmode='studio';
  function grp(){return f.querySelector('input[name=grp]:checked').value}
  function own(){var v=parseFloat((f.w_own.value||'').replace(',','.'));return isFinite(v)?Math.round(v*100):0}
  function sel(){
    var g=grp();
    if(g==='wert'){var a=f.querySelector('input[name=w_amt]:checked').value;return {k:'wert',c:a==='own'?own():parseInt(a,10)}}
    var r=f.querySelector('input[name=p_'+g+']:checked');return {k:r.value,c:parseInt(r.dataset.cents,10)};
  }
  function upd(){
    var g=grp();
    f.querySelectorAll('.gs-sub').forEach(function(s){s.classList.toggle('on',s.dataset.grp===g)});
    f.querySelector('.gs-own').hidden=!(g==='wert'&&f.querySelector('input[name=w_amt]:checked').value==='own');
    var s=sel(),p=P[s.k],L=f.vlang.value==='en'?'en':'de',d=f.design.value;
    pv.dataset.d=d;pv.style.backgroundImage='url(/img/movecia/gutschein/design'+d+'.jpg)';
    var big=s.k==='wert'?(s.c?eur(s.c):'€ …'):p.t[L];
    document.getElementById('pv-lb').textContent=L==='en'?'GIFT VOUCHER':'GUTSCHEIN';
    var bg=document.getElementById('pv-bg');bg.textContent=big;bg.classList.toggle('sm',big.length>(d==='1'?16:18));
    document.getElementById('pv-sb').textContent=s.k==='wert'?(L==='en'?'for everything at Movecia':'für alles bei Movecia'):p.s[L];
    document.getElementById('pv-ds').textContent=p.d[L];
    var wh=document.getElementById('pv-wh'),to=f.recipient.value.trim(),fr=f.sender.value.trim();wh.innerHTML='';
    [[L==='en'?'for':'für',to],[L==='en'?'from':'von',fr]].forEach(function(a){if(!a[1])return;var x=document.createElement('div'),sm=document.createElement('small');sm.textContent=a[0];x.appendChild(sm);x.appendChild(document.createTextNode(' '+a[1]));if(d==='2'){x.style.display='inline';if(wh.childNodes.length)wh.appendChild(document.createTextNode('  ·  '))}wh.appendChild(x)});
    var m=f.message.value.trim();document.getElementById('pv-ms').textContent=m?'„'+m+'“':'';
    document.getElementById('sumt').textContent=s.k==='wert'?(de()?'Wertgutschein':'Gift voucher'):p.n[de()?'de':'en'];
    document.getElementById('sumc').textContent=s.c?eur(s.c):'–';
  }
  f.addEventListener('input',upd);f.addEventListener('change',upd);
  document.addEventListener('mvlang',function(){if(!f.dataset.vl)f.vlang.value=de()?'de':'en';upd()});
  f.vlang.addEventListener('change',function(){f.dataset.vl=1});
  f.vlang.value=de()?'de':'en';
  f.querySelectorAll('[data-nm]').forEach(function(b){b.addEventListener('click',function(){
    nmode=b.dataset.nm;f.querySelectorAll('[data-nm]').forEach(function(x){x.setAttribute('aria-pressed',String(x===b))});
    var cur=f.querySelector('input[name=p_natal]:checked').value.replace('natal_o_','natal_').replace('natal_','');
    f.querySelectorAll('[data-nmode]').forEach(function(c){c.hidden=c.dataset.nmode!==nmode});
    var n=f.querySelector('input[name=p_natal][value="natal_'+(nmode==='online'?'o_':'')+cur+'"]');if(n)n.checked=true;upd();
  })});
  upd();
  var e=document.getElementById('ge'),b=document.getElementById('gb');
  f.addEventListener('submit',async function(ev){ev.preventDefault();e.hidden=true;var D=de(),s=sel();
    if(s.k==='wert'&&(s.c<<?= MVG_WERT_MIN ?>||s.c><?= MVG_WERT_MAX ?>)){e.textContent=D?'Bitte wähl einen Betrag zwischen <?= mv_eur(MVG_WERT_MIN) ?> und <?= mv_eur(MVG_WERT_MAX) ?>.':'Please choose an amount between <?= mv_eur(MVG_WERT_MIN) ?> and <?= mv_eur(MVG_WERT_MAX) ?>.';e.hidden=false;return}
    if(f.buyer_name.value.trim().length<2||f.buyer_email.value.indexOf('@')<1){e.textContent=D?'Bitte gib deinen Namen und deine E-Mail an.':'Please enter your name and email.';e.hidden=false;return}
    if(!f.consent.checked){e.textContent=D?'Bitte bestätige noch die Hinweise zum Datenschutz.':'Please confirm the privacy note.';e.hidden=false;return}
    var d={action:'buy',product:s.k,amount:s.k==='wert'?String(s.c/100):'',design:f.design.value,lang:f.vlang.value,recipient:f.recipient.value,sender:f.sender.value,message:f.message.value,buyer_name:f.buyer_name.value,buyer_email:f.buyer_email.value,consent:1,website:f.website.value,t:loaded};
    b.disabled=true;
    try{var r=await fetch('/api/mv-gutschein.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});var j=await r.json();
      if(j.ok&&j.redirect){location.href=j.redirect;return}e.textContent=j.error||'…';e.hidden=false}
    catch(x){e.textContent=D?'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.':'That did not work. Please try again.';e.hidden=false}
    b.disabled=false});
})();
</script>
</body>
</html>

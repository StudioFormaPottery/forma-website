<?php
// moveciamovement.com/academy/mitglieder/ – Mitgliederbereich Teacher Training vor dem Start (Vivien, 28.10.2026, Runde 2)
// Zugang gleich nach der Anzahlung (gleiche Konten wie Method/Unfold: mv_members, Cookie, höchstens 3 Geräte, eins gleichzeitig).
// Countdown bis 3.11.2027, Willkommensvideo (Studio-Lektion academy:welcome), Workbook „Schön, dass du da bist“ (kommt bald),
// private Notizen (verschlüsselt), Fragen & Antworten, persönlicher Chat mit Vivien, Zahlungsübersicht mit Bezahl-Knopf.
// Nur Deutsch (Vivien, 28.10.2026). ?demo=1 Beispielansicht ohne Konto. noindex.
declare(strict_types=1);
require_once __DIR__ . '/../../assets/icons.inc.php';
require dirname(__DIR__, 3) . '/api/academy-lib.php';
require __DIR__ . '/../../assets/layout.inc.php';
$GLOBALS['mvl_de_only'] = true; // Ausbildung nur auf Deutsch (Vivien, 28.10.2026)
ob_start('mv_strip_en');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$GLOBALS['mvl_noindex'] = true;
$pdo = forma_db();
ac_schema($pdo);
try { ac_tick($pdo); } catch (Throwable $e) { error_log('AC tick: ' . $e->getMessage()); }
$h = 'forma_h';
$self = '/academy/mitglieder/';
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . 'mv');
$go = function (string $q = '') use ($self) { header('Location: ' . $self . $q, true, 303); exit; };
$me = mv_current($pdo);
$r = $me ? ac_booking_of($pdo, (string)$me['email']) : null;
$demo = !$r && isset($_GET['demo']);
$lang = 'de'; // nur Deutsch; wer mit Englisch kommt, sieht oben einen kurzen Hinweis (mvl_head, .en-note)
$L = fn(string $de, string $en) => ($lang === 'en' ? $en : $de);
$err = ''; $note = '';
if (!$me && ($kt = mm_dev_kicked_text($lang)) !== '') $err = $kt;

// --- Aktionen ------------------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $a = (string)($_POST['a'] ?? '');
    if (!empty($_POST['website'])) $go();
    if ($a === 'login') {
        if (mv_throttled($pdo, $ip)) $err = $L('Zu viele Versuche. Bitte warte eine Viertelstunde oder setz dein Passwort neu.', 'Too many attempts. Please wait 15 minutes or reset your password.');
        else {
            $m = mv_member_by_email($pdo, strtolower(trim((string)($_POST['email'] ?? ''))));
            if ($m && $m['pass_hash'] !== '' && password_verify((string)($_POST['pw'] ?? ''), (string)$m['pass_hash'])) { mv_login($pdo, $m); $go(); }
            mv_fail($pdo, $ip);
            $err = $L('E-Mail oder Passwort stimmen nicht. Versuch es noch einmal oder setz dein Passwort neu.', 'Your email or password is incorrect. Please try again or set a new password.');
        }
    }
    if ($a === 'setpw') {
        $m = mv_by_reset($pdo, (string)($_POST['t'] ?? ''));
        $pw = (string)($_POST['pw'] ?? '');
        if (!$m) $err = $L('Dieser Link funktioniert nicht mehr. Fordere unten einfach einen neuen an.', 'This link no longer works. Just request a new one below.');
        elseif (mb_strlen($pw) < 8) $err = $L('Bitte wähl ein Passwort mit mindestens 8 Zeichen.', 'Please choose a password with at least 8 characters.');
        elseif ($pw !== (string)($_POST['pw2'] ?? '')) $err = $L('Die beiden Passwörter stimmen nicht überein.', 'The two passwords don’t match.');
        else { mv_login($pdo, mv_set_password($pdo, $m, $pw)); $go('?willkommen=1'); }
    }
    if ($a === 'reset') {
        if (!mv_throttled($pdo, $ip)) {
            mv_fail($pdo, $ip);
            $em = strtolower(trim((string)($_POST['email'] ?? '')));
            $b = ac_booking_of($pdo, $em);
            $m = $b ? mv_member_ensure($pdo, $em, (string)$b['name'], (string)$b['lang']) : null;
            if ($m) {
                $en = $b['lang'] === 'en';
                $first = forma_first_name((string)$b['name']);
                try { mv_send_member_mail(['lang' => $b['lang'], 'email' => $em], $en ? 'Your new password' : 'Dein neues Passwort', $en ? 'here you go ♡' : 'hier ist dein Link ♡', AC_SHORT,
                    $en ? ["Dear $first,", 'here is your link to choose a new password for your Teacher Training members area. It works for one hour.'] : ["Hallo $first,", 'hier ist dein Link für ein neues Passwort für deinen Teacher-Training-Mitgliederbereich. Er funktioniert eine Stunde lang.'],
                    ac_pw_link($pdo, $m, 1), $en ? 'Choose a new password' : 'Neues Passwort wählen', ''); } catch (Throwable $e) { error_log('AC reset: ' . $e->getMessage()); }
            }
        }
        $note = $L('Wenn es für diese E-Mail einen Platz im Teacher Training gibt, ist der Link jetzt unterwegs. Schau auch im Spam-Ordner nach.', 'If there is a Teacher Training place for this email, the link is on its way. Please also check your spam folder.');
    }
    if ($me && $a === 'logout') { mm_dev_logout($pdo); mv_logout(); $go('?bye=1'); }
}
$css = '<link rel="stylesheet" href="/wildhearts/assets/academy.css?v=9">';

// --- Nicht angemeldet ---------------------------------------------------------------------------
if (!$r && !$demo) {
    $resetTok = (string)($_GET['pw'] ?? '');
    $resetM = $resetTok !== '' ? mv_by_reset($pdo, $resetTok) : null;
    $mode = $resetTok !== '' ? 'reset' : (isset($_GET['vergessen']) ? 'forgot' : 'login');
    if ($me && !$r) $err = $L('Für dein Konto gibt es noch keinen Platz im Teacher Training. Schreib mir gern, wenn das nicht stimmt.', 'There is no Teacher Training place for your account yet. Please write to me if that is not right.');
    mvl_head($L('Teacher Training · Mitgliederbereich', 'Teacher Training · Members area') . ' | Movecia Academy', '', mm_site() . $self, '/img/movecia/og-academy.jpg', $css);
    ?>
<body class="acad acm-auth">
<header class="w site-top"><div class="in top"><a class="brand" href="/academy/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia</span></a><div class="top-r acm-lang"></div></div></header>
<p class="en-note">This training is held in German only.</p>
<?= ac_band('Mitgliederbereich') ?>
<main class="w"><div class="in acm-authbox">
  <?= ac_seal('acm-sealbig') ?>
  <p class="k">Teacher Training 2027</p>
  <?php if ($mode === 'reset'): ?>
    <h1><?= $resetM ? $L('Wähl dein <em>Passwort</em>', 'Choose your <em>password</em>') : $L('Link abgelaufen', 'Link expired') ?></h1>
    <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
    <?php if ($resetM): ?>
    <p class="mut"><?= $L('Für', 'For') ?> <b><?= $h((string)$resetM['email']) ?></b></p>
    <form method="post" class="stack"><input type="hidden" name="a" value="setpw"><input type="hidden" name="t" value="<?= $h($resetTok) ?>">
      <div class="field"><label for="pw"><?= $L('Passwort (mind. 8 Zeichen)', 'Password (at least 8 characters)') ?></label><input id="pw" type="password" name="pw" autocomplete="new-password" required minlength="8"></div>
      <div class="field"><label for="pw2"><?= $L('Passwort wiederholen', 'Repeat password') ?></label><input id="pw2" type="password" name="pw2" autocomplete="new-password" required minlength="8"></div>
      <button class="btn" type="submit"><?= $L('Speichern und hinein', 'Save and come in') ?></button>
    </form>
    <?php else: ?><p><a class="btn line" href="?vergessen=1&amp;lang=<?= $lang ?>"><?= $L('Neuen Link anfordern', 'Request a new link') ?></a></p><?php endif; ?>
  <?php elseif ($mode === 'forgot'): ?>
    <h1><?= $L('Kein <em>Problem</em>', 'No <em>problem</em>') ?></h1>
    <?php if ($note): ?><p class="note"><?= $h($note) ?></p><?php endif; ?>
    <p class="mut"><?= $L('Gib deine E-Mail ein, du bekommst einen Link für ein neues Passwort.', 'Enter your email and you will get a link to set a new password.') ?></p>
    <form method="post" class="stack"><input type="hidden" name="a" value="reset"><input class="sr" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="field"><label for="em">E-Mail</label><input id="em" type="email" name="email" autocomplete="email" required></div>
      <button class="btn" type="submit"><?= $L('Link schicken', 'Send link') ?></button>
    </form>
    <p class="small"><a href="?lang=<?= $lang ?>"><?= $L('Zurück zur Anmeldung', 'Back to login') ?></a></p>
  <?php else: ?>
    <h1><?= isset($_GET['bye']) ? $L('Bis <em>bald</em>', 'See you <em>soon</em>') : $L('Schön, dass du <em>da bist</em>', 'So glad <em>you’re here</em>') ?></h1>
    <p class="mut"><?= $L('Melde dich mit deiner E-Mail und deinem Passwort an.', 'Log in with your email and password.') ?></p>
    <?php if ($err): ?><p class="err"><?= $h($err) ?></p><?php endif; ?>
    <form method="post" class="stack"><input type="hidden" name="a" value="login"><input class="sr" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="field"><label for="em">E-Mail</label><input id="em" type="email" name="email" autocomplete="email" required></div>
      <div class="field"><label for="pw"><?= $L('Passwort', 'Password') ?></label><input id="pw" type="password" name="pw" autocomplete="current-password" required></div>
      <button class="btn" type="submit"><?= $L('Anmelden', 'Log in') ?></button>
    </form>
    <p class="small"><a href="?vergessen=1&amp;lang=<?= $lang ?>"><?= $L('Passwort vergessen?', 'Forgot your password?') ?></a> · <a href="/academy/teacher-training/<?= $lang === 'en' ? '?lang=en' : '' ?>"><?= $L('Zur Ausbildung', 'About the training') ?></a></p>
  <?php endif; ?>
</div></main>
<?php mvl_foot(); ?>
</body></html>
<?php
    exit;
}

// --- Mitgliederbereich ---------------------------------------------------------------------------
$c = ac_cfg();
if ($demo) $r = ac_demo_booking();
$first = forma_first_name((string)$r['name']);
$lesson = ac_welcome_lesson($pdo);
$embed = $lesson ? mv_embed(mv_video_for($lesson, $lang)) : '';
$parts = ac_parts($r);
$next = ac_next_part($r);
$noteText = $demo ? '' : ac_note_get($pdo, (int)$me['id']);
$msgs = $demo ? [
    ['who' => 'tn', 'text' => $L('Hallo Vivien! Ich freu mich so. Brauche ich für den Start schon eine eigene Matte und Blöcke?', 'Hi Vivien! I’m so excited. Do I need my own mat and blocks for the start?'), 'created_at' => date('c', time() - 86400)],
    ['who' => 'viv', 'text' => $L('Hallo Lena, wie schön! Eine Matte reicht für den Anfang völlig. Blöcke, Gurt und Polster kommen erst in Modul 3 dazu, eine Liste bekommst du rechtzeitig ♡', 'Hi Lena, how lovely! A mat is all you need to begin. Blocks, a strap and a bolster only come in with module 3, and you will get a list in good time ♡'), 'created_at' => date('c', time() - 80000)],
] : ac_msgs($pdo, (int)$r['id']);
$faq = array_map(fn($q) => $lang === 'en' ? [$q[2], $q[3]] : [$q[0], $q[1]], ac_member_faq());
// „Häufig gefragt“-Chips im Chat → Index in $faq
$chips = [];
foreach (ac_member_chips() as [$lab, $q]) { foreach ($faq as $i => $f) if ($f[0] === $q) { $chips[] = [$lab, $i]; break; } }
$hours = ac_hours();
$hr = (int)(new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('G');
$hello = $hr < 11 ? $L('Guten Morgen', 'Good morning') : ($hr < 17 ? $L('Hallo', 'Hello') : $L('Guten Abend', 'Good evening'));
$spark = fn(string $cls) => '<svg class="spk ' . $cls . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1c1 6 5 10 11 11-6 1-10 5-11 11-1-6-5-10-11-11 6-1 10-5 11-11z"/></svg>';
mvl_head($L('Teacher Training · Mitgliederbereich', 'Teacher Training · Members area') . ' | Movecia Academy', '', mm_site() . $self, '/img/movecia/og-academy.jpg', $css);
?>
<body class="acad acm">
<header class="w site-top"><div class="in top">
  <a class="brand" href="/academy/"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Movecia</span></a>
  <div class="top-r acm-lang">
    <?php if (!$demo): ?><form method="post"><input type="hidden" name="a" value="logout"><button class="acm-out" type="submit">Abmelden</button></form><?php endif; ?>
  </div>
</div></header>
<p class="en-note">This training is held in German only.</p>
<?= ac_band('Mitgliederbereich') ?>
<main>
<?php if ($demo): ?><p class="acm-demo w"><?= $L('Beispielansicht · so sieht der Mitgliederbereich für eine Teilnehmerin aus', 'Example view · this is what a participant sees') ?></p><?php endif; ?>

<section class="acm-hero has-hw w" id="top"><?= mv_waves(false, 'acm-waves') ?><div class="in">
  <?= $spark('s1') . $spark('s2') . $spark('s3') ?>
  <div class="acm-hello">
    <?= ac_seal('acm-seal') ?>
    <div>
      <p class="k">Mindful Becoming Teacher Training 2027</p>
      <h1><?= $h($hello) ?>, <em><?= $h($first) ?></em></h1>
      <p class="acm-lead"><?= isset($_GET['willkommen']) ? $L('Dein Zugang ist eingerichtet. Willkommen in deinem Teacher-Training-Zuhause ♡', 'Your access is all set. Welcome to your Teacher Training home ♡') : (isset($_GET['bezahlt']) ? $L('Danke dir, deine Zahlung ist angekommen ♡', 'Thank you, your payment has come through ♡') : $L('Hier wartet alles auf dich, bis wir gemeinsam starten.', 'Everything is waiting for you here until we begin together.')) ?></p>
    </div>
  </div>
  <div class="acm-count" data-start="<?= (int)$c['start_ts'] ?>" aria-live="polite">
    <p class="acm-cl"><?= $L('Noch so lange bis zu unserem Start am', 'Until we begin on') ?> <b><?= $h(mv_date($c['start_ts'], $lang)) ?></b></p>
    <div class="acm-cd"><span><b data-u="d">–</b><i><?= $L('Tage', 'days') ?></i></span><span><b data-u="h">–</b><i><?= $L('Stunden', 'hours') ?></i></span><span><b data-u="m">–</b><i><?= $L('Minuten', 'minutes') ?></i></span><span><b data-u="s">–</b><i><?= $L('Sekunden', 'seconds') ?></i></span></div>
  </div>
</div></section>

<div class="w"><div class="in acm-grid">
  <div class="acm-col">
    <section class="acm-card acm-video">
      <p class="k"><?= $L('Mein Willkommen an dich', 'My welcome to you') ?></p>
      <?php if ($embed !== ''): ?><div class="acm-frame"><iframe src="<?= $h($embed) ?>" loading="lazy" allow="accelerometer; gyroscope; encrypted-media; picture-in-picture" allowfullscreen title="<?= $L('Willkommensvideo', 'Welcome video') ?>"></iframe></div>
      <?php else: ?><div class="acm-ph"><span class="acm-play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5.5v13l11-6.5z"/></svg></span><p><b><?= $L('Mein Willkommensvideo kommt bald', 'My welcome video is coming soon') ?></b><br><?= $L('Ich nehme es gerade für dich auf. Sobald es da ist, findest du es genau hier.', 'I am recording it for you right now. As soon as it is ready, you will find it right here.') ?></p></div><?php endif; ?>
    </section>

    <section class="acm-card acm-wb">
      <div class="acm-wbcover"><img src="/img/movecia/fotos/ev-ueber-mich.jpg" alt="" loading="lazy"><span><?= $L('Workbook', 'Workbook') ?></span></div>
      <div>
        <p class="k"><?= $L('Dein erstes Workbook', 'Your first workbook') ?></p>
        <h2><?= $L('Schön, dass du <em>da bist</em>', 'So glad <em>you’re here</em>') ?></h2>
        <p class="mut"><?= $L('Intentionen setzen, deine Geschichte mit Bewegung, Affirmationen für die Zeit bis zum Start. Es kommt bald hierher, ich sag dir Bescheid.', 'Setting your intentions, your own story with movement, and affirmations for the time until we start. It will be here soon, and I will let you know.') ?></p>
        <span class="acm-soon"><?= $L('kommt bald', 'coming soon') ?></span>
      </div>
    </section>

    <section class="acm-card acm-notes">
      <p class="k"><?= $L('Nur für dich', 'Just for you') ?></p>
      <h2><?= $L('Meine <em>Notizen</em>', 'My <em>notes</em>') ?></h2>
      <p class="mut small"><?= $L('Fragen, Wünsche, Intentionen. Alles wird verschlüsselt gespeichert, niemand außer dir kann es lesen, auch ich nicht.', 'Questions, hopes, intentions. Everything is stored encrypted, nobody but you can read it, not even me.') ?></p>
      <textarea id="acNote" rows="8" placeholder="<?= $L('Was ich mir für dieses Jahr wünsche …', 'What I hope for this year …') ?>"><?= $h($noteText) ?></textarea>
      <p class="acm-saved small" id="acSaved" aria-live="polite"></p>
    </section>
  </div>

  <div class="acm-col">
    <section class="acm-card acm-chat" id="chat">
      <div class="acm-chathead"><img src="/img/movecia/fotos/ev-profil.jpg" alt=""><div><p class="k"><?= $L('Dein Draht zu mir', 'Your line to me') ?></p><h2><?= $L('Schreib <em>mir</em>', 'Write to <em>me</em>') ?></h2></div><?= ac_birds() ?></div>
      <div class="acm-chips" role="group" aria-label="Häufig gefragt"><p class="acm-chips-k">Häufig gefragt</p><div class="acm-chips-r" id="acChips">
        <?php foreach ($chips as [$lab, $fi]): ?><button type="button" class="acm-chip" data-f="<?= (int)$fi ?>"><?= $h($lab) ?></button><?php endforeach; ?>
      </div></div>
      <div class="acm-msgs" id="acMsgs" aria-live="polite"></div>
      <div class="acm-sugg" id="acSugg" hidden><p class="small"><b><?= $L('Vielleicht hilft dir das schon:', 'Maybe this already helps:') ?></b></p><div id="acSuggList"></div></div>
      <form id="acChat" class="acm-send"><textarea id="acText" rows="2" maxlength="2000" placeholder="<?= $L('Deine Nachricht an Vivien …', 'Your message to Vivien …') ?>" required></textarea><button class="btn" type="submit"><?= $L('Senden', 'Send') ?></button></form>
      <p class="mut small"><?= $L('Ich antworte dir persönlich, meist innerhalb von ein, zwei Tagen. Du bekommst dann eine kurze Mail.', 'I will answer you personally, usually within a day or two, and you will get a short email when I do.') ?></p>
    </section>

    <section class="acm-card acm-pay">
      <p class="k"><?= $L('Deine Zahlungen', 'Your payments') ?></p>
      <h2><?= $h(ac_eur((int)$r['total_cents'], $lang)) ?><?= (int)$r['eb'] ? ' <span class="acm-eb">Early Bird</span>' : '' ?></h2>
      <ul class="acm-parts">
        <?php foreach ($parts as $p): $paid = $p['paid'] !== ''; ?>
        <li class="<?= $paid ? 'ok' : '' ?>"><span class="acm-tick" aria-hidden="true"><?= $paid ? '✓' : '' ?></span><span><b><?= $h(ac_eur((int)$p['cents'], $lang)) ?></b> · <?= $h(ac_part_label($r, (int)$p['n'], $lang)) ?><br><small><?= $paid ? $L('bezahlt am ', 'paid on ') . $h(mv_date(strtotime((string)$p['paid']), $lang)) : ($p['due'] === '' ? $L('jetzt fällig', 'due now') : $L('fällig bis ', 'due by ') . $h(ac_d((string)$p['due'], $lang))) ?></small></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($next): ?><a class="btn" href="<?= $demo ? '#' : $h(ac_pay_link($r)) ?>"><?= $L('Jetzt ', 'Pay ') . $h(ac_eur((int)$next['cents'], $lang)) . $L(' bezahlen', ' now') ?></a><p class="mut small" style="margin-top:.6rem"><?= $L('Etwa zehn Tage vor jedem Termin bekommst du auch einen Bezahl-Link per Mail.', 'About ten days before each date you will also get a payment link by email.') ?></p>
      <?php else: ?><p class="note"><?= $L('Alles bezahlt. Danke dir von Herzen ♡', 'All paid. Thank you so much ♡') ?></p><?php endif; ?>
    </section>
  </div>
</div></div>

<section class="w acm-faq tint t3"><div class="in">
  <p class="k"><?= $L('Fragen & Antworten', 'Questions & answers') ?></p>
  <h2><?= $L('Was du vielleicht <em>wissen möchtest</em>', 'What you might <em>like to know</em>') ?></h2>
  <div class="cute-faq">
    <?php foreach ($faq as $i => $q): ?><details id="faq-<?= $i ?>"><summary><?= $h($q[0]) ?></summary><p><?= $h($q[1]) ?></p></details><?php endforeach; ?>
  </div>
  <p class="mut small" style="margin-top:1.2rem"><a href="/terms/#teacher"><?= $L('Bedingungen & Rücktrittsrecht', 'Terms & right of withdrawal') ?></a> · <a href="/academy/teacher-training/<?= $lang === 'en' ? '?lang=en' : '' ?>"><?= $L('Alles über die Ausbildung', 'All about the training') ?></a></p>
</div></section>
</main>
<?php mvl_foot(); ?>
<script>
(function(){
  var demo=<?= $demo ? 'true' : 'false' ?>, en=<?= $lang === 'en' ? 'true' : 'false' ?>;
  var T=function(d,e){return en?e:d};
  // Countdown
  var cd=document.querySelector('.acm-count'),start=+cd.dataset.start*1000;
  function pad(n){return n<10?'0'+n:''+n}
  function tick(){var s=Math.max(0,Math.floor((start-Date.now())/1000));if(s===0&&!cd.dataset.go){cd.dataset.go=1;cd.querySelector('.acm-cl').innerHTML='<b>Heute geht es los ♡</b> Modul 1 ist für dich offen.'}var v={d:Math.floor(s/86400),h:pad(Math.floor(s%86400/3600)),m:pad(Math.floor(s%3600/60)),s:pad(s%60)};cd.querySelectorAll('[data-u]').forEach(function(b){b.textContent=v[b.dataset.u]})}
  tick();setInterval(tick,1000);
  function api(data){data.lang=en?'en':'de';return fetch('/api/academy.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(data)}).then(function(r){return r.json()})}
  // Notizen
  var nt=document.getElementById('acNote'),sv=document.getElementById('acSaved'),tm=null;
  nt.addEventListener('input',function(){clearTimeout(tm);sv.textContent=T('…','…');tm=setTimeout(function(){if(demo){sv.textContent=T('Im Beispiel wird nichts gespeichert.','Nothing is saved in the example.');return}api({action:'note',text:nt.value}).then(function(j){sv.textContent=j.ok?T('Gespeichert um ','Saved at ')+j.at+' ♡':(j.error||'')}).catch(function(){sv.textContent=T('Nicht gespeichert, bitte prüf deine Verbindung.','Not saved, please check your connection.')})},900)});
  // Chat
  var msgs=<?= json_encode(array_map(fn($m) => ['who' => $m['who'], 'text' => $m['text'], 'at' => $m['created_at']], $msgs), JSON_UNESCAPED_UNICODE) ?>;
  var faq=<?= json_encode($faq, JSON_UNESCAPED_UNICODE) ?>;
  var box=document.getElementById('acMsgs'),first=<?= json_encode($first) ?>;
  function esc(s){return s.replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
  function when(iso){var d=new Date(iso);return d.toLocaleDateString(en?'en-GB':'de-AT',{day:'numeric',month:'short'})+' · '+d.toLocaleTimeString(en?'en-GB':'de-AT',{hour:'2-digit',minute:'2-digit'})}
  // Antworten auf „Häufig gefragt“ (nur hier im Browser, gehen nicht an Vivien)
  var faqA=[];
  function faqHtml(a){var f=faq[a.f];return '<div class="bub q">'+esc(a.lab)+'</div><div class="bub viv fa" data-i="'+a.f+'"><span class="fa-k">'+T('Häufig gefragt','Often asked')+'</span>'+esc(f[1])
    +'<a class="fa-more" href="#faq-'+a.f+'">'+T('Mehr in Fragen &amp; Antworten','More in questions &amp; answers')+' →</a>'
    +(a.ok?'<span class="fa-ok">'+T('Schön, dass es dir hilft ♡','So glad it helps ♡')+'</span>':'<span class="fa-b"><button type="button" class="fa-yes">'+T('Danke, das hilft ✓','Thanks, that helps ✓')+'</button><button type="button" class="fa-ask">'+T('Ich möchte Vivien direkt schreiben','I want to write to Vivien')+'</button></span>')+'</div>'}
  function draw(){var h=msgs.map(function(m){return '<div class="bub '+m.who+'">'+esc(m.text).replace(/\n/g,'<br>')+'<small>'+(m.who==='viv'?'Vivien':esc(first))+' · '+when(m.at)+'</small></div>'}).join('')+faqA.map(faqHtml).join('');
    box.innerHTML=h||('<p class="acm-empty">'+T('Noch keine Nachrichten. Schreib mir einfach, ich freu mich auf dich ♡','No messages yet. Just write to me, I would love to hear from you ♡')+'</p>');box.scrollTop=box.scrollHeight}
  draw();
  document.getElementById('acChips').addEventListener('click',function(e){var b=e.target.closest('.acm-chip');if(!b)return;var f=+b.dataset.f;
    faqA=faqA.filter(function(a){return a.f!==f});faqA.push({f:f,lab:b.textContent,ok:false});b.classList.add('done');draw();
    var qs=box.querySelectorAll('.bub.q');if(qs.length)box.scrollTop=qs[qs.length-1].offsetTop-box.offsetTop-8});
  box.addEventListener('click',function(e){var bub=e.target.closest('.fa');if(!bub)return;var i=+bub.dataset.i,a=faqA.filter(function(x){return x.f===i})[0];
    if(e.target.closest('.fa-yes')){a.ok=true;draw()}
    else if(e.target.closest('.fa-ask')){ta.value=a.lab+' ';ta.focus();ta.setSelectionRange(ta.value.length,ta.value.length);ta.scrollIntoView({block:'center',behavior:'smooth'})}
    else if(e.target.closest('.fa-more')){e.preventDefault();var d=document.getElementById('faq-'+i);if(d){d.open=true;d.classList.add('hl');d.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(function(){d.classList.remove('hl')},2400)}}});
  if(!demo)setInterval(function(){if(document.hidden)return;api({action:'chat_get'}).then(function(j){if(j.ok&&j.msgs.length!==msgs.length){msgs=j.msgs.map(function(m){return{who:m.who,text:m.text,at:m.created_at}});draw()}}).catch(function(){})},20000);
  // Vorschläge aus den Fragen & Antworten, bevor man schickt
  var ta=document.getElementById('acText'),sg=document.getElementById('acSugg'),sl=document.getElementById('acSuggList');
  var stop=/^(ich|und|oder|der|die|das|ein|eine|einen|wie|was|wann|wo|ist|bin|habe|hab|mit|für|auf|den|dem|zu|im|in|es|du|mir|mich|the|and|how|what|when|where|is|do|does|i|my|a|an|to|of|for|in|on|can|it|you|me)$/;
  function words(s){return (s.toLowerCase().match(/[a-zäöüß0-9]{3,}/g)||[]).filter(function(w){return !stop.test(w)})}
  function root(w){return w.slice(0,5)}
  ta.addEventListener('input',function(){var q=words(ta.value).map(root);if(q.length<1||ta.value.length<8){sg.hidden=true;return}
    var sc=faq.map(function(f,i){var t=words(f[0]+' '+f[0]+' '+f[1]).map(root),n=0;q.forEach(function(w){if(t.indexOf(w)>=0)n++});return[n,i]}).filter(function(x){return x[0]>=Math.min(2,q.length)}).sort(function(a,b){return b[0]-a[0]}).slice(0,2);
    if(!sc.length){sg.hidden=true;return}
    sl.innerHTML=sc.map(function(x){var f=faq[x[1]];return '<details><summary>'+esc(f[0])+'</summary><p>'+esc(f[1])+'</p></details>'}).join('');sg.hidden=false});
  document.getElementById('acChat').addEventListener('submit',function(e){e.preventDefault();var t=ta.value.trim();if(!t)return;var b=this.querySelector('button');b.disabled=true;
    var done=function(){ta.value='';sg.hidden=true;b.disabled=false;draw()};
    if(demo){msgs.push({who:'tn',text:t,at:new Date().toISOString()});done();return}
    api({action:'chat',text:t}).then(function(j){if(!j.ok){alert(j.error||T('Das hat nicht geklappt.','That didn’t work.'));b.disabled=false;return}msgs=j.msgs.map(function(m){return{who:m.who,text:m.text,at:m.created_at}});done()}).catch(function(){alert(T('Bitte prüf deine Verbindung.','Please check your connection.'));b.disabled=false})});
})();
</script>
<?php if (!$demo): ?><script src="/wildhearts/assets/geraet.js?v=1" data-kind="mv" defer></script><?php endif; ?>
</body></html>

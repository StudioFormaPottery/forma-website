<?php
// moveciamovement.com/unfold/call/ – Live-Call für Unfold-Teilnehmerinnen (eingebetteter Whereby-Raum, je Sprache einer).
// Nur angemeldete Mitglieder mit Unfold-Buchung. ?l=de|en wechselt einmal in die andere Sprachgruppe.
declare(strict_types=1);
require dirname(__DIR__, 3) . '/api/method-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$pdo = forma_db();
mv_schema($pdo);
$me = mv_current($pdo);
if (!$me) { header('Location: /mitglieder/', true, 303); exit; }
$acc = mv_access($pdo, (string)$me['email']);
if (!$acc['unfold']) { header('Location: /mitglieder/', true, 303); exit; }
$en = $me['lang'] === 'en';
$cl = in_array($_GET['l'] ?? '', ['de', 'en'], true) ? $_GET['l'] : ($acc['unfold']['call_lang'] ?: $me['lang']);
$cfg = mm_cfg('method') ?? [];
$room = (string)($cfg['u_room_' . $cl] ?? '');
$c = mv_cfg();
$h = 'forma_h';
$src = $room !== '' ? $room . (str_contains($room, '?') ? '&' : '?') . http_build_query(['displayName' => forma_first_name((string)$me['name']), 'lang' => $en ? 'en' : 'de', 'background' => 'off', 'chat' => 'on', 'people' => 'on']) : '';
?><!doctype html>
<html lang="<?= $en ? 'en' : 'de' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Unfold · Live-Call</title>
<link rel="icon" href="/img/movecia/movecia-favicon-64.png">
<link rel="stylesheet" href="/wildhearts/assets/mv.css?v=1">
<style>
html,body{height:100%}body{display:flex;flex-direction:column}
.bar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:10px 16px;border-bottom:1px solid var(--line);flex-wrap:wrap}
.bar p{margin:0;font-size:.9rem;color:var(--muted)}
iframe{flex:1;width:100%;border:0;background:var(--deep)}
.empty{flex:1;display:grid;place-items:center;text-align:center;padding:2rem}
</style>
<script src="/wildhearts/assets/schutz.js?v=1"></script>
</head>
<body>
<div class="bar"><a class="brand" href="/mitglieder/?tab=unfold"><img src="/img/movecia/movecia-zeichen-klein.svg" alt=""><span>Unfold<i></i></span></a>
  <p><?= $cl === 'en' ? $h($c['u_call_en']) . ' · English' : $h($c['u_call_de']) . ' · Deutsch' ?> · <a href="?l=<?= $cl === 'en' ? 'de' : 'en' ?>"><?= $en ? 'switch to the ' . ($cl === 'en' ? 'German' : 'English') . ' group' : 'zur ' . ($cl === 'en' ? 'deutschen' : 'englischen') . ' Gruppe wechseln' ?></a></p></div>
<?php if ($src): ?><iframe src="<?= $h($src) ?>" allow="camera; microphone; fullscreen; speaker; display-capture; autoplay" title="Unfold Live-Call"></iframe>
<?php else: ?><div class="empty"><div><h2><?= $en ? 'The room opens soon' : 'Der Raum öffnet bald' ?></h2><p class="mut"><?= $en ? 'The link appears here before the first call.' : 'Der Link erscheint hier vor dem ersten Call.' ?></p></div></div><?php endif; ?>
<?php if (!empty($me)) { ?><script src="/wildhearts/assets/geraet.js?v=1" data-kind="mv" defer></script><?php } ?>
</body>
</html>

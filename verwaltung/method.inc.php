<?php
// Verwaltung → Movecia → „Method & Unfold“ (?view=method): Programme und Videos (Upload direkt zu Bunny Stream),
// Workbooks, Sprechtexte mit Teleprompter, Mitglieder, Preise, Unfold-Plätze und Calls, Einstellungen.
// Wird von verwaltung/movecia.php eingebunden (angemeldet, CSRF geprüft).
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/method-lib.php';
mv_schema($pdo);
mv_seed($pdo);
$vBack = $self . '?view=method';

$mvJson = function (array $x): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($x); exit; };

if ($action === 'mm_mv_bunny_save') {
    $old = mv_bunny();
    $new = [
        'lib' => preg_replace('/\D+/', '', (string)($_POST['lib'] ?? '')),
        'cdn' => preg_replace('~^https?://~', '', trim((string)($_POST['cdn'] ?? ''), " /")),
        'key' => trim((string)($_POST['key'] ?? '')) ?: $old['key'],
        'token' => trim((string)($_POST['token'] ?? '')) ?: $old['token'],
    ];
    if ($new['lib'] === '' || $new['key'] === '') go($vBack . '#bunny', 'Bitte trag mindestens die Library-ID und den API-Schlüssel ein.');
    mm_save_cfg('bunny', $new);
    try { mv_bunny_req('GET', '/videos?page=1&itemsPerPage=1'); go($vBack . '#bunny', 'Bunny ist verbunden. Du kannst jetzt Videos hochladen.'); }
    catch (Throwable $e) { go($vBack . '#bunny', 'Gespeichert, aber Bunny antwortet noch nicht: ' . $e->getMessage()); }
}
if ($action === 'mm_mv_bunny_remove') { mm_save_cfg('bunny', null); go($vBack . '#bunny', 'Bunny ist getrennt.'); }

if ($action === 'mm_mv_cfg') {
    $e = fn(string $k) => mm_euro_in((string)($_POST[$k] ?? ''));
    $in = [];
    foreach (['m1', 'm3', 'm12', 'eb_m1', 'eb_m3', 'eb_m12', 'g_m1', 'g_m3', 'g_m12', 'u_once', 'u_inst', 'eb_u_once', 'eb_u_inst', 'g_u_once'] as $k) if (($v = $e($k)) > 0) $in[$k] = $v;
    foreach (['launch', 'u_start', 'u_end', 'eb_m_until', 'eb_u_until'] as $k) if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST[$k] ?? ''))) $in[$k] = (string)$_POST[$k];
    $in['u_seats'] = max(1, min(40, (int)($_POST['u_seats'] ?? 10)));
    $in['u_open'] = !empty($_POST['u_open']) ? 1 : 0;
    $in['u_call_de'] = forma_clean((string)($_POST['u_call_de'] ?? ''), 80);
    $in['u_call_en'] = forma_clean((string)($_POST['u_call_en'] ?? ''), 80);
    mv_save_cfg($in);
    go($vBack . '#preise', 'Gespeichert. Die Seiten zeigen sofort die neuen Angaben.');
}

if ($action === 'mm_mv_rooms') {
    if (mm_whereby_key() === '') go($vBack . '#unfold', 'Trag zuerst unter Movecia → Einstellungen deinen Whereby-Schlüssel ein.');
    $c = mv_cfg();
    $out = [];
    foreach (['de'] as $lg) { // nur Deutsch (Vivien, 30.10.2026)
        $r = mm_whereby_request('POST', '/meetings', ['endDate' => gmdate('Y-m-d\TH:i:s.000\Z', mv_unfold_end() + 14 * 86400), 'roomMode' => 'group', 'roomNamePrefix' => 'unfold-' . $lg, 'fields' => ['hostRoomUrl']], mm_whereby_key());
        if (empty($r['roomUrl'])) go($vBack . '#unfold', 'Whereby hat keinen Raum zurückgegeben. Bitte versuch es noch einmal.');
        $out['u_room_' . $lg] = (string)$r['roomUrl'];
        $out['u_host_' . $lg] = (string)($r['hostRoomUrl'] ?? '');
    }
    mv_save_cfg($out);
    go($vBack . '#unfold', 'Der Unfold-Videoraum ist angelegt. Er bleibt bis zwei Wochen nach dem Ende offen.');
}

if ($action === 'mm_mv_prog_save') {
    $id = (int)($_POST['id'] ?? 0);
    $f = [
        'audience' => ($_POST['audience'] ?? '') === 'unfold' ? 'unfold' : 'method',
        'title_de' => forma_clean((string)($_POST['title_de'] ?? ''), 120),
        'title_en' => forma_clean((string)($_POST['title_en'] ?? ''), 120),
        'intro_de' => mb_substr(trim((string)($_POST['intro_de'] ?? '')), 0, 1500),
        'intro_en' => mb_substr(trim((string)($_POST['intro_en'] ?? '')), 0, 1500),
        'unlock' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['unlock'] ?? '')) ? (string)$_POST['unlock'] : '',
        'theme' => preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_POST['theme'] ?? ''))),
        'sort' => (int)($_POST['sort'] ?? 0),
        'visible' => !empty($_POST['visible']) ? 1 : 0,
    ];
    if ($f['title_de'] === '') go($vBack . ($id ? '&p=' . $id : '') . '#prog', 'Bitte gib einen Titel ein.');
    if ($id && (mv_program($pdo, $id)['audience'] ?? '') === 'natal') $f['audience'] = 'natal'; // Prä-/Postnatal-Videos bleiben dort
    if ($id) {
        $pdo->prepare('UPDATE mv_programs SET audience=?, title_de=?, title_en=?, intro_de=?, intro_en=?, unlock=?, theme=?, sort=?, visible=? WHERE id=?')->execute([...array_values($f), $id]);
    } else {
        $pdo->prepare('INSERT INTO mv_programs (audience, title_de, title_en, intro_de, intro_en, unlock, theme, sort, visible, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([...array_values($f), date('c')]);
        $id = (int)$pdo->lastInsertId();
    }
    go($vBack . '&p=' . $id, 'Programm gespeichert.');
}
if ($action === 'mm_mv_prog_delete') {
    $id = (int)($_POST['id'] ?? 0);
    foreach (mv_lessons($pdo, $id) as $l) { mv_bunny_delete($l['video_de']); mv_bunny_delete($l['video_en']); }
    foreach (mv_files($pdo, $id) as $f) @unlink(mv_file_dir() . '/' . basename($f['file']));
    $pdo->prepare('DELETE FROM mv_lessons WHERE program_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM mv_files WHERE program_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM mv_programs WHERE id = ?')->execute([$id]);
    go($vBack . '#programme', 'Programm gelöscht.');
}

if ($action === 'mm_mv_lesson_save') {
    $id = (int)($_POST['id'] ?? 0);
    $pid = (int)($_POST['program_id'] ?? 0);
    $f = [
        'program_id' => $pid,
        'title_de' => forma_clean((string)($_POST['title_de'] ?? ''), 120),
        'title_en' => forma_clean((string)($_POST['title_en'] ?? ''), 120),
        'text_de' => mb_substr(trim((string)($_POST['text_de'] ?? '')), 0, 2000),
        'text_en' => mb_substr(trim((string)($_POST['text_en'] ?? '')), 0, 2000),
        'cat' => isset(MV_CATS[$_POST['cat'] ?? '']) ? (string)$_POST['cat'] : 'flow',
        'energy' => isset(MV_ENERGY[$_POST['energy'] ?? '']) ? (string)$_POST['energy'] : 'any',
        'minutes' => max(0, min(240, (int)($_POST['minutes'] ?? 0))),
        'sort' => (int)($_POST['sort'] ?? 0),
        'visible' => !empty($_POST['visible']) ? 1 : 0,
        'script_de' => mb_substr((string)($_POST['script_de'] ?? ''), 0, 30000),
        'script_en' => mb_substr((string)($_POST['script_en'] ?? ''), 0, 30000),
        'unlock' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['unlock'] ?? '')) ? (string)$_POST['unlock'] : '',
    ];
    if ($f['title_de'] === '' || !mv_program($pdo, $pid)) go($vBack . '&p=' . $pid, 'Bitte gib einen Titel ein.');
    if ($id) {
        $pdo->prepare('UPDATE mv_lessons SET program_id=?, title_de=?, title_en=?, text_de=?, text_en=?, cat=?, energy=?, minutes=?, sort=?, visible=?, script_de=?, script_en=?, unlock=? WHERE id=?')->execute([...array_values($f), $id]);
    } else {
        $pdo->prepare('INSERT INTO mv_lessons (program_id, title_de, title_en, text_de, text_en, cat, energy, minutes, sort, visible, script_de, script_en, unlock, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...array_values($f), date('c')]);
        $id = (int)$pdo->lastInsertId();
    }
    go($vBack . '&l=' . $id, 'Gespeichert.');
}
if ($action === 'mm_mv_lesson_delete') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    if ($l) { mv_bunny_delete($l['video_de']); mv_bunny_delete($l['video_en']); $pdo->prepare('DELETE FROM mv_lessons WHERE id = ?')->execute([$l['id']]); $pdo->prepare('DELETE FROM mv_progress WHERE lesson_id = ?')->execute([$l['id']]); }
    go($vBack . '&p=' . (int)($l['program_id'] ?? 0), 'Lektion gelöscht.');
}

// Video-Upload: Server legt das Video bei Bunny an, der Browser lädt die Datei direkt dorthin (TUS), dann wird es eingetragen
if ($action === 'mm_mv_video_new') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $lg = ($_POST['lang'] ?? '') === 'en' ? 'en' : 'de';
    if (!$l) $mvJson(['error' => 'Lektion nicht gefunden.']);
    try { $mvJson(mv_bunny_new(mv_t($l, 'title', $lg) . ' · ' . strtoupper($lg))); } catch (Throwable $e) { $mvJson(['error' => $e->getMessage()]); }
}
if ($action === 'mm_mv_video_set') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $lg = ($_POST['lang'] ?? '') === 'en' ? 'en' : 'de';
    $guid = preg_replace('/[^a-f0-9-]/', '', (string)($_POST['guid'] ?? ''));
    if (!$l || $guid === '') $mvJson(['error' => 'Fehlt.']);
    if ($l['video_' . $lg] !== '' && $l['video_' . $lg] !== $guid) mv_bunny_delete($l['video_' . $lg]);
    $pdo->prepare('UPDATE mv_lessons SET video_' . $lg . ' = ? WHERE id = ?')->execute([$guid, $l['id']]);
    $mvJson(['ok' => 1]);
}
if ($action === 'mm_mv_video_clear') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $lg = ($_POST['lang'] ?? '') === 'en' ? 'en' : 'de';
    if ($l) { mv_bunny_delete($l['video_' . $lg]); $pdo->prepare('UPDATE mv_lessons SET video_' . $lg . " = '' WHERE id = ?")->execute([$l['id']]); }
    go($vBack . '&l=' . (int)($l['id'] ?? 0), 'Video entfernt.');
}

// Mediathek (Vivien, 26.10.2026): jede Übung wird einmal gefilmt und steckt dann in jedem Video, das sie enthält.
// Clip finden über die Mediathek-ID (c) oder über Video + Übungsnummer (id, i).
$mvClip = function () use ($pdo): ?array {
    if (!empty($_POST['c'])) { $q = $pdo->prepare('SELECT * FROM mv_clips WHERE id = ?'); $q->execute([(int)$_POST['c']]); return $q->fetch() ?: null; }
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $st = $l ? mv_lesson_steps($l) : [];
    $i = (int)($_POST['i'] ?? -1);
    return isset($st[$i]) ? mv_clip_ensure($pdo, $st[$i]['de'], $st[$i]['en']) : null;
};
if ($action === 'mm_mv_clip_new') {
    $c = $mvClip();
    if (!$c) $mvJson(['error' => 'Übung nicht gefunden.']);
    try { $mvJson(mv_bunny_new('Übung · ' . $c['name_de'])); } catch (Throwable $e) { $mvJson(['error' => $e->getMessage()]); }
}
if ($action === 'mm_mv_clip_set') {
    $c = $mvClip();
    $guid = preg_replace('/[^a-f0-9-]/', '', (string)($_POST['guid'] ?? ''));
    if (!$c || $guid === '') $mvJson(['error' => 'Fehlt.']);
    if ($c['guid'] !== '' && $c['guid'] !== $guid) mv_bunny_delete((string)$c['guid']);
    $pdo->prepare('UPDATE mv_clips SET guid = ?, updated_at = ? WHERE id = ?')->execute([$guid, date('c'), $c['id']]);
    mv_clips_all($pdo, true);
    $used = count(mv_clip_usage($pdo)[$c['ckey']] ?? []);
    $mvJson(['ok' => 1, 'c' => (int)$c['id'], 'view' => mv_embed($guid), 'used' => $used]);
}
if ($action === 'mm_mv_clip_clear') {
    $c = $mvClip();
    if ($c && $c['guid'] !== '') { mv_bunny_delete((string)$c['guid']); $pdo->prepare("UPDATE mv_clips SET guid = '', updated_at = ? WHERE id = ?")->execute([date('c'), $c['id']]); }
    $mvJson(['ok' => 1]);
}
if ($action === 'mm_mv_clip_tags') {
    $c = $mvClip();
    if (!$c) $mvJson(['error' => 'Fehlt.']);
    $se = array_values(array_intersect(array_keys(MV_SEASONS), (array)($_POST['seasons'] ?? [])));
    $sy = array_values(array_intersect(array_keys(MV_STYLES), (array)($_POST['styles'] ?? [])));
    $pdo->prepare('UPDATE mv_clips SET seasons = ?, styles = ?, updated_at = ? WHERE id = ?')->execute([implode(',', $se), implode(',', $sy), date('c'), $c['id']]);
    $mvJson(['ok' => 1]);
}
// Voice-over je Übung (Flow): direkt im Browser aufgenommen oder als Datei hochgeladen
if ($action === 'mm_mv_voice') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $i = (int)($_POST['i'] ?? -1);
    $g = ($_POST['g'] ?? '') === 'en' ? 'en' : 'de';
    $st = $l ? mv_lesson_steps($l) : [];
    $f = $_FILES['audio'] ?? null;
    if (!$l || !isset($st[$i]) || !$f || (int)$f['error'] !== 0 || (int)$f['size'] < 500) $mvJson(['error' => 'Die Aufnahme ist nicht angekommen. Bitte noch einmal.']);
    if ((int)$f['size'] > 60 * 1024 * 1024) $mvJson(['error' => 'Die Datei ist zu groß (höchstens 60 MB).']);
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $mime = (string)(@mime_content_type((string)$f['tmp_name']) ?: '');
    if (!in_array($ext, ['webm', 'ogg', 'mp4', 'm4a', 'mp3', 'wav', 'aac'], true)) $ext = str_contains($mime, 'mp4') ? 'm4a' : (str_contains($mime, 'ogg') ? 'ogg' : (str_contains($mime, 'mpeg') ? 'mp3' : (str_contains($mime, 'wav') ? 'wav' : 'webm')));
    if ($mime !== '' && !preg_match('~^(audio|video)/|application/octet-stream~', $mime)) $mvJson(['error' => 'Das ist keine Tonaufnahme.']);
    if ($old = mv_voice_path((int)$l['id'], $i, $g)) @unlink($old);
    $dest = mv_voice_dir() . '/L' . (int)$l['id'] . '-' . $i . '-' . $g . '.' . $ext;
    if (!move_uploaded_file((string)$f['tmp_name'], $dest)) $mvJson(['error' => 'Speichern hat nicht geklappt.']);
    // in die Voice-over-Bibliothek: steht dieselbe Übung mit demselben Text in einem neuen Flow, ist sie dort sofort fertig
    try { mv_voice_take_add($pdo, (int)$l['id'], $i, $g, $dest); } catch (Throwable $e) { error_log('voice take: ' . $e->getMessage()); }
    $mvJson(['ok' => 1, 'url' => mv_voice_url((int)$l['id'], $i, $g), 'status' => mv_lesson_status($pdo, $l)]);
}
// Gespeicherte Aufnahmen einer Übung (zum Anhören und Übernehmen)
if ($action === 'mm_mv_voice_takes') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $st = $l ? mv_lesson_steps($l) : [];
    $i = (int)($_POST['i'] ?? -1);
    $g = ($_POST['g'] ?? '') === 'en' ? 'en' : 'de';
    if (!isset($st[$i])) $mvJson(['error' => 'Fehlt.']);
    $out = [];
    foreach (mv_voice_takes($pdo, mv_clip_key($st[$i]['de']), $g) as $t) {
        $src = mv_lesson($pdo, (int)$t['lesson_id']);
        $out[] = ['id' => (int)$t['id'], 'url' => mv_take_url((int)$t['id']), 'text' => (string)$t['text'], 'from' => $src ? (string)$src['title_de'] : '', 'at' => date('d.m.Y', strtotime((string)$t['created_at']))];
    }
    $mvJson(['ok' => 1, 'takes' => $out]);
}
// Vorhandene Aufnahme aus der Bibliothek übernehmen: der Text der Übung wird der Text dieser Aufnahme
if ($action === 'mm_mv_voice_use') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $i = (int)($_POST['i'] ?? -1);
    $g = ($_POST['g'] ?? '') === 'en' ? 'en' : 'de';
    $q = $pdo->prepare('SELECT * FROM mv_voices WHERE id = ?'); $q->execute([(int)($_POST['take'] ?? 0)]); $t = $q->fetch();
    $st = $l ? mv_lesson_steps($l) : [];
    if (!$t || !isset($st[$i]) || $t['lang'] !== $g || $t['ckey'] !== mv_clip_key($st[$i]['de'])) $mvJson(['error' => 'Diese Aufnahme passt nicht zu dieser Übung.']);
    $st[$i][$g === 'en' ? 'ten' : 'tde'] = (string)$t['text'];
    mv_lesson_steps_save($pdo, (int)$l['id'], $st);
    foreach (glob(mv_voice_dir() . '/L' . (int)$l['id'] . '-' . $i . '-' . $g . '.*') ?: [] as $old) @unlink($old);
    $l = mv_lesson($pdo, (int)$l['id']);
    $mvJson(['ok' => 1, 'url' => mv_take_url((int)$t['id']), 'text' => (string)$t['text'], 'status' => mv_lesson_status($pdo, $l)]);
}
if ($action === 'mm_mv_voice_clear') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $g = ($_POST['g'] ?? '') === 'en' ? 'en' : 'de';
    if ($l && ($f = mv_voice_path((int)$l['id'], (int)($_POST['i'] ?? -1), $g))) @unlink($f);
    $mvJson(['ok' => 1]);
}
// Sprechtext oder Dauer einer Übung ändern, Art des Videos (Flow mit Voice-over / Bewegungsvideo 30–45 Sek.)
if ($action === 'mm_mv_step') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $i = (int)($_POST['i'] ?? -1);
    $st = $l ? mv_lesson_steps($l) : [];
    if (!isset($st[$i])) $mvJson(['error' => 'Fehlt.']);
    $k = (string)($_POST['k'] ?? '');
    if ($k === 'sec') $st[$i]['sec'] = max(20, min(900, (int)$_POST['v']));
    elseif ($k === 'tde' || $k === 'ten') $st[$i][$k] = mb_substr(str_replace("\r", '', (string)$_POST['v']), 0, 6000);
    else $mvJson(['error' => 'Fehlt.']);
    mv_lesson_steps_save($pdo, (int)$l['id'], $st);
    $mvJson(['ok' => 1]);
}
if ($action === 'mm_mv_mode') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $m = ($_POST['mode'] ?? '') === 'move' ? 'move' : 'flow';
    if (!$l) $mvJson(['error' => 'Fehlt.']);
    $pdo->prepare('UPDATE mv_lessons SET mode = ? WHERE id = ?')->execute([$m, $l['id']]);
    // Umschalten auf Flow: Übungen ohne Sprechtext bekommen den Text der neuesten gespeicherten Aufnahme dieser Übung,
    // dann sind sie sofort eingesprochen
    if ($m === 'flow') {
        $st = mv_lesson_steps($l); $ch = false;
        foreach ($st as $i => $x) foreach (['de' => 'tde', 'en' => 'ten'] as $g => $k) {
            if (trim($x[$k]) !== '') continue;
            $t = mv_voice_takes($pdo, mv_clip_key($x['de']), $g)[0] ?? null;
            if ($t) { $st[$i][$k] = (string)$t['text']; $ch = true; }
        }
        if ($ch) { mv_lesson_steps_save($pdo, (int)$l['id'], $st); $l = mv_lesson($pdo, (int)$l['id']); }
    }
    $l['mode'] = $m;
    $mvJson(['ok' => 1, 'status' => mv_lesson_status($pdo, $l)]);
}
if ($action === 'mm_mv_due') {
    $l = mv_lesson($pdo, (int)($_POST['id'] ?? 0));
    $d = (string)($_POST['due'] ?? '');
    if (!$l || ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))) $mvJson(['error' => 'Fehlt.']);
    $pdo->prepare('UPDATE mv_lessons SET due = ? WHERE id = ?')->execute([$d, $l['id']]);
    $mvJson(['ok' => 1]);
}

if ($action === 'mm_mv_file_add') {
    $pid = (int)($_POST['program_id'] ?? 0);
    $up = $_FILES['file'] ?? null;
    if (!mv_program($pdo, $pid) || !$up || $up['error'] !== UPLOAD_ERR_OK) go($vBack . '&p=' . $pid . '#dateien', 'Die Datei ist nicht angekommen. Ist sie vielleicht zu groß?');
    $ext = strtolower(pathinfo((string)$up['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'zip'], true)) go($vBack . '&p=' . $pid . '#dateien', 'Bitte lade ein PDF (oder PNG/JPG/ZIP) hoch.');
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    move_uploaded_file($up['tmp_name'], mv_file_dir() . '/' . $name);
    $label = forma_clean((string)($_POST['label_de'] ?? ''), 80) ?: 'Workbook';
    $pdo->prepare('INSERT INTO mv_files (program_id, label_de, label_en, lang, file, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$pid, $label, forma_clean((string)($_POST['label_en'] ?? ''), 80) ?: $label, in_array($_POST['lang'] ?? '', ['de', 'en'], true) ? $_POST['lang'] : 'both', $name, date('c')]);
    go($vBack . '&p=' . $pid . '#dateien', 'Datei hochgeladen. Die Frauen sehen sie im Programm.');
}
if ($action === 'mm_mv_file_delete') {
    $q = $pdo->prepare('SELECT * FROM mv_files WHERE id = ?');
    $q->execute([(int)($_POST['id'] ?? 0)]);
    $f = $q->fetch();
    if ($f) { @unlink(mv_file_dir() . '/' . basename($f['file'])); $pdo->prepare('DELETE FROM mv_files WHERE id = ?')->execute([$f['id']]); }
    go($vBack . '&p=' . (int)($f['program_id'] ?? 0) . '#dateien', 'Datei gelöscht.');
}

// Als Mitglied testen: Zugang ohne Zahlung (Method oder Unfold), Mail mit Passwort-Link an die angegebene Adresse
if ($action === 'mm_mv_test') {
    $was = (string)($_POST['kind'] ?? 'method');
    $kinds = $was === 'beides' ? ['method', 'unfold'] : [$was === 'unfold' ? 'unfold' : 'method'];
    $m = mv_test_create($pdo, ['kinds' => $kinds, 'started' => ($_POST['phase'] ?? 'start') === 'start', 'waive' => ($_POST['wr'] ?? '') === 'verzicht',
        'lang' => $_POST['lang'] ?? 'de', 'name' => $_POST['name'] ?? 'Test', 'email' => $_POST['email'] ?? '']);
    header('Location: ' . mv_test_login_link((int)$m['id']), true, 303);
    exit;
}
if ($action === 'mm_mv_test_login') {
    $m = mv_member($pdo, (int)($_POST['mid'] ?? 0));
    if (!$m) go($vBack . '#test', 'Diesen Testzugang gibt es nicht mehr.');
    header('Location: ' . mv_test_login_link((int)$m['id']), true, 303);
    exit;
}
if ($action === 'mm_mv_test_delete') {
    $r = mv_sub($pdo, (int)($_POST['id'] ?? 0));
    if ($r && (int)$r['test'] === 1 && $r['source'] === 'test') {
        $pdo->prepare('DELETE FROM mv_subs WHERE id = ?')->execute([$r['id']]);
        if (!$pdo->query('SELECT COUNT(*) FROM mv_subs WHERE lower(email) = ' . $pdo->quote(strtolower($r['email'])))->fetchColumn()) {
            $m = mv_member_by_email($pdo, (string)$r['email']);
            if ($m) foreach (['mv_progress', 'mv_favs', 'mv_journal'] as $t) $pdo->prepare("DELETE FROM $t WHERE member_id = ?")->execute([$m['id']]);
            $pdo->prepare('DELETE FROM mv_members WHERE lower(email) = ?')->execute([strtolower($r['email'])]);
        }
    }
    go($vBack . '#test', 'Testzugang gelöscht.');
}
if ($action === 'mm_mv_sync') {
    $n = 0;
    foreach ($pdo->query("SELECT * FROM mv_subs WHERE status IN ('offen','aktiv') AND stripe_session <> ''")->fetchAll() as $r) {
        try { $r['status'] === 'offen' ? mv_check_paid($pdo, $r) : mv_sync($pdo, $r, true); $n++; } catch (Throwable $e) { error_log('MV sync all: ' . $e->getMessage()); }
    }
    go($vBack . '#mitglieder', $n . ' Buchungen mit Stripe abgeglichen.');
}
if ($action === 'mm_mv_resend') {
    $r = mv_sub($pdo, (int)($_POST['id'] ?? 0));
    if ($r) { $pdo->prepare('UPDATE mv_subs SET mailed = 0 WHERE id = ?')->execute([$r['id']]); mv_welcome($pdo, mv_sub($pdo, (int)$r['id'])); }
    go($vBack . '#mitglieder', 'Willkommensmail mit Zugang nochmal geschickt.');
}

if ($view === 'method') {
    // Video-Status für die Anzeige beim Hochladen
    if (isset($_GET['vstatus'])) { $i = mv_bunny_info(preg_replace('/[^a-f0-9-]/', '', (string)$_GET['vstatus'])); $mvJson(['status' => (int)($i['status'] ?? -1), 'progress' => (int)($i['encodeProgress'] ?? 0), 'length' => (int)($i['length'] ?? 0)]); }
    try { mv_tick($pdo); } catch (Throwable $e) { error_log('MV tick: ' . $e->getMessage()); }

    // Teleprompter für Sprechtexte (Vollbild, nur für Vivien)
    if (isset($_GET['prompter'])) {
        $l = mv_lesson($pdo, (int)$_GET['prompter']);
        $lg = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'de';
        $txt = $l ? (string)$l['script_' . $lg] : '';
        header('Content-Type: text/html; charset=utf-8'); ?><!doctype html><html lang="<?= $lg ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Teleprompter</title>
<style>body{margin:0;background:#1E1719;color:#F6EFEA;font:400 44px/1.5 Georgia,serif}#t{padding:40vh 8vw 80vh;white-space:pre-wrap;max-width:1100px;margin:0 auto}.ui{position:fixed;left:0;right:0;bottom:0;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;padding:12px;background:rgba(30,23,25,.9);font:15px system-ui}.ui button{font:inherit;padding:9px 16px;border-radius:999px;border:1px solid #6B5658;background:#4A3A41;color:#F6EFEA;cursor:pointer}.mir #t{transform:scaleX(-1)}.hint{color:#A8959B;font-size:.55em}.mark{color:#E8D6CA;font-size:.6em}body::before{content:"";position:fixed;left:0;right:0;top:38vh;height:2px;background:rgba(232,214,202,.25)}/* Blick in die Kamera (Vivien, 30.10.2026): Text schmal ganz oben unter der Linse, darunter dunkel */body.cam #t{padding-top:5vh;max-width:min(640px,92vw)}body.cam::before{top:calc(5vh + .75em)}body.cam::after{content:"";position:fixed;inset:0;pointer-events:none;z-index:1;background:linear-gradient(#1E1719,rgba(30,23,25,0) 4vh,rgba(30,23,25,0) 22vh,#1E1719 32vh)}.ui{z-index:2}.ui button.on{background:#7D5E68;border-color:#7D5E68}</style></head><body>
<div id="t"><?= preg_replace(['/\[([^\]]+)\]/', '/^(\d{1,2}:\d{2})/m'], ['<span class="hint">[$1]</span>', '<span class="mark">$1</span>'], forma_h($txt !== '' ? $txt : ($lg === 'en' ? 'No script yet. Add it in the lesson.' : 'Noch kein Sprechtext. Trag ihn bei der Lektion ein.'))) ?></div>
<div class="ui"><button id="go">▶ Start</button><button data-s="-1">langsamer</button><button data-s="1">schneller</button><button data-z="-4">A−</button><button data-z="4">A+</button><button id="top">⟲</button><button id="mir">Spiegeln</button><button id="cam" class="on">📷 Blick in die Kamera</button></div>
<script>let run=false,sp=1.2,fs=44,y=0;const go=document.getElementById('go');function loop(){if(run){window.scrollBy(0,sp);}requestAnimationFrame(loop)}loop();
function t(){run=!run;go.textContent=run?'❚❚ Pause':'▶ Start'}go.onclick=t;document.getElementById('t').onclick=t;document.addEventListener('keydown',e=>{if(e.code==='Space'){e.preventDefault();t()}});
document.querySelectorAll('[data-s]').forEach(b=>b.onclick=()=>{sp=Math.max(.2,sp+(+b.dataset.s)*.3)});document.querySelectorAll('[data-z]').forEach(b=>b.onclick=()=>{fs=Math.max(24,fs+(+b.dataset.z));document.body.style.fontSize=fs+'px'});
document.getElementById('top').onclick=()=>scrollTo(0,0);document.getElementById('mir').onclick=()=>document.body.classList.toggle('mir');const cb=document.getElementById('cam');function cm(on){document.body.classList.toggle('cam',on);cb.classList.toggle('on',on);try{localStorage.setItem('tp-cam',on?'1':'0')}catch(e){}}let c0=true;try{c0=localStorage.getItem('tp-cam')!=='0'}catch(e){}cm(c0);cb.onclick=()=>cm(!document.body.classList.contains('cam'));</script></body></html><?php exit;
    }

    $pEdit = isset($_GET['p']) ? mv_program($pdo, (int)$_GET['p']) : null;
    $lEdit = isset($_GET['l']) ? mv_lesson($pdo, (int)$_GET['l']) : null;
    $newLessonFor = isset($_GET['neu']) ? mv_program($pdo, (int)$_GET['neu']) : null;
    render_page('Method & Unfold', function () use ($self, $flash, $pdo, $pEdit, $lEdit, $newLessonFor) {
        $c = mv_cfg();
        $raw = mv_cfg_raw();
        $b = mv_bunny();
        $plans = mv_plans();
        $csrf = '<input type="hidden" name="csrf" value="' . forma_h(csrf()) . '">';
        ob_start();
        $tsubs = $pdo->query("SELECT s.*, m.id AS mid FROM mv_subs s LEFT JOIN mv_members m ON lower(m.email) = lower(s.email) WHERE s.source = 'test' ORDER BY s.id DESC LIMIT 30")->fetchAll();
        $tby = []; foreach ($tsubs as $t) $tby[(int)$t['mid']][] = $t; ?>
        <h2 id="test">🧪 Als Testteilnehmerin ansehen</h2>
        <div class="card stack" style="background:#F6EFEC;border-color:#D9C5C4">
            <p class="small muted" style="margin:0">Ein Klick legt eine Testteilnehmerin ohne Zahlung an und öffnet ihren Mitgliederbereich in einem neuen Tab, genau wie ihn eine Kundin sieht. Sie zählt nicht als Unfold-Platz und taucht nicht im Umsatz auf. Ohne E-Mail geht keine Mail raus.</p>
            <form method="post" target="_blank" class="stack"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_test">
                <div class="g3"><label>Was<select name="kind"><option value="method">The Method</option><option value="unfold">Unfold</option><option value="beides">Method + Unfold</option></select></label>
                    <label>Zeitpunkt<select name="phase"><option value="start">schon gestartet</option><option value="vorher">vor dem Start (Warteseite)</option></select></label>
                    <label>Rücktrittsrecht<select name="wr"><option value="verzicht">verzichtet (alles offen)</option><option value="behalten">behalten (erste 14 Tage)</option></select></label></div>
                <div class="g3"><label>Name<input name="name" value="Test Vivien"></label><label>Sprache<select name="lang"><option value="de">Deutsch</option><option value="en">Englisch</option></select></label><label>E-Mail <span class="small muted">(optional, dann echte Mails)</span><input type="email" name="email" placeholder="leer lassen"></label></div>
                <div><button class="btn" type="submit">Als Testteilnehmerin einloggen →</button></div>
            </form>
            <p class="small muted" style="margin:0">Für Prä- &amp; Postnatal: <a href="?view=natal#testkundin">dort „Als Kundin testen“</a>.</p>
            <?php foreach ($tby as $mid => $ts): if (!$mid) continue; $t0 = $ts[0]; ?>
            <div class="ln"><span><strong><?= forma_h($t0['name']) ?></strong> <span class="small muted">· <?= forma_h(implode(' + ', array_map(fn($t) => $t['kind'] === 'unfold' ? 'Unfold' : 'Method', $ts))) ?> · <?= (int)$t0['open_from'] > time() ? 'vor dem Start' : 'gestartet' ?> · <?= (int)$t0['consent_digital'] ? 'verzichtet' : 'Rücktrittsrecht behalten' ?><?= str_ends_with((string)$t0['email'], '@test.moveciamovement.com') ? '' : ' · ' . forma_h($t0['email']) ?></span></span>
                <span class="acts"><form method="post" target="_blank"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_test_login"><input type="hidden" name="mid" value="<?= (int)$mid ?>"><button class="link small" type="submit">einloggen</button></form>
                <?php foreach ($ts as $t): ?><form method="post" onsubmit="return confirm('Testzugang löschen?')"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_test_delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="link small" type="submit">löschen<?= count($ts) > 1 ? ' (' . ($t['kind'] === 'unfold' ? 'Unfold' : 'Method') . ')' : '' ?></button></form><?php endforeach; ?></span></div>
            <?php endforeach; ?>
        </div>

        <h2 id="bunny"><?= mv_bunny_ok() ? 'Bunny Stream (Videos)' : 'Schritt 1: Bunny Stream verbinden' ?></h2>
        <?php if (!mv_bunny_ok()): ?><p class="card" style="background:#EADFDE;border-color:#D9C5C4;font-size:15px">Hier trägst du deine Bunny-Daten ein (Library-ID, CDN Hostname, API Key und Token-Schlüssel). Erst danach kannst du bei jeder Lektion Videos hochladen. Sobald Bunny verbunden ist, rutscht dieser Bereich nach unten.</p><?php endif; ?>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_bunny_save">
            <p class="small muted">In Bunny: Stream → deine Library „Movecia Method“ → <b>API</b>. Dort stehen Video Library ID, CDN Hostname und API Key. Den Token-Schlüssel findest du unter <b>Security</b> (dort auch „Embed view token authentication“ einschalten), dann können die Videos nur im Mitgliederbereich abgespielt werden.</p>
            <div class="g2"><label>Video Library ID<input name="lib" value="<?= forma_h($b['lib']) ?>" inputmode="numeric"></label><label>CDN Hostname<input name="cdn" value="<?= forma_h($b['cdn']) ?>" placeholder="vz-xxxxxxxx-xxx.b-cdn.net"></label></div>
            <div class="g2"><label>API Key<input type="password" name="key" placeholder="<?= $b['key'] !== '' ? 'gespeichert · leer lassen zum Behalten' : '' ?>" autocomplete="off"></label><label>Token Authentication Key<input type="password" name="token" placeholder="<?= $b['token'] !== '' ? 'gespeichert · leer lassen zum Behalten' : 'optional, für den Kopierschutz' ?>" autocomplete="off"></label></div>
            <button class="btn" type="submit">Speichern und prüfen</button>
            <?php if (mv_bunny_ok()): ?><span class="small">✓ verbunden<?= $b['token'] !== '' ? ', Kopierschutz an' : ', Kopierschutz noch aus' ?></span><?php endif; ?>
        </form>
        <?php if (mv_bunny_ok()): ?><form method="post" onsubmit="return confirm('Bunny trennen?')" style="margin-top:8px"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_bunny_remove"><button class="link small" type="submit">Bunny trennen</button></form><?php endif; ?>
        <?php $bunnyHtml = ob_get_clean();
        $eu = fn(int $x) => $x ? rtrim(rtrim(number_format($x / 100, 2, ',', ''), '0'), ',') : '';
        $subs = $pdo->query("SELECT * FROM mv_subs WHERE status IN ('aktiv','beendet') OR (status = 'offen' AND created_at >= '" . date('c', time() - 86400) . "') ORDER BY id DESC LIMIT 200")->fetchAll();
        $real = array_filter($subs, fn($s) => $s['source'] !== 'test');
        $mActive = count(array_filter($real, fn($s) => $s['kind'] === 'method' && $s['status'] === 'aktiv'));
        $uTaken = mv_unfold_taken($pdo);
        $mrr = 0; foreach ($real as $s) if ($s['kind'] === 'method' && $s['status'] === 'aktiv' && !(int)$s['cancel_end']) $mrr += (int)round($s['amount_cents'] / ($plans[$s['plan']]['months'] ?? 1));
        $progs = array_merge(mv_programs($pdo, 'method'), mv_programs($pdo, 'unfold'));
        $site = mm_site();
        $catOpt = function (string $cur) { $o = ''; foreach (MV_CATS as $k => $n) $o .= '<option value="' . $k . '"' . ($k === $cur ? ' selected' : '') . '>' . forma_h($n[0]) . '</option>'; return $o; };
        $enOpt = function (string $cur) { $o = ''; foreach (MV_ENERGY as $k => $n) $o .= '<option value="' . $k . '"' . ($k === $cur ? ' selected' : '') . '>' . forma_h($n[0]) . '</option>'; return $o; }; ?>
<style>
:root{--paper:#F2ECE4;--card:#FBF8F4;--clay:#7D5E68;--soft:#7D7076;--line:#DDD2C6;--ink:#2E2529}
body{background:var(--paper)}h1,h2{color:#6B5658}
.nh{position:relative;overflow:hidden;border-radius:26px;background:#4A3A41;color:#FBF5F7;padding:24px 24px 22px}
.nh::after{content:"";position:absolute;width:220px;height:210px;right:-80px;top:-90px;border-radius:62% 38% 46% 54%/55% 45% 55% 45%;background:rgba(232,214,202,.3)}
.nh h1{color:#FBF5F7;font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:34px;margin:2px 0 4px;position:relative}.nh p{position:relative;margin:0;color:#EADFDE}.nh .eyebrow{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:#E8D6CA}
.ns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}@media(min-width:700px){.ns{grid-template-columns:repeat(4,minmax(0,1fr))}}
.ns a,.ns span.st{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:12px 14px;color:var(--ink);text-decoration:none;display:flex;flex-direction:column}
.ns b{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-variant-numeric:lining-nums;font-size:32px;line-height:1.1;color:#7D5E68}.ns a span,.ns span.st span{font-size:14px;color:var(--soft);line-height:1.3}
.nl{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.nl a{display:inline-block;padding:7px 14px;border-radius:999px;background:var(--card);border:1px solid var(--line);text-decoration:none;color:#6B5658;font-size:15px}
.btn{background:#7D5E68;border-color:#7D5E68;color:#FBF5F7}.btn-ghost{background:transparent;color:#7D5E68}.link{color:#7D5E68}
.chip{display:inline-block;border-radius:999px;padding:1px 10px;font-size:13px;background:#EADFDE;color:#6B5658}.chip.ok{background:#7D5E68;color:#FBF5F7}.chip.wait{background:#E8D6CA}
.ln{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;border-top:1px solid var(--line);padding:9px 0;align-items:center}
.g2{display:grid;gap:10px}@media(min-width:800px){.g2{grid-template-columns:1fr 1fr}}
.g3{display:grid;gap:10px;grid-template-columns:repeat(3,minmax(0,1fr))}
.prow{display:grid;gap:4px;border-top:1px solid var(--line);padding:10px 0}
.prow a.t{font-family:"Cormorant Garamond",Georgia,serif;font-weight:500;font-size:21px;color:var(--ink);text-decoration:none}
.vbox{border:1px dashed var(--line);border-radius:16px;padding:12px;display:grid;gap:8px}
.vbar{height:8px;border-radius:9px;background:#EADFDE;overflow:hidden}.vbar i{display:block;height:100%;width:0;background:#7D5E68;transition:width .3s}
textarea.script{min-height:220px;font-size:15px;line-height:1.55}
details.more summary{cursor:pointer;color:#7D5E68}
label.inl{display:flex;gap:8px;align-items:center}
</style>
        <p><a href="<?= forma_h($self) ?>?view=movecia">← Movecia</a> · <a href="<?= forma_h($self) ?>?view=method">Method &amp; Unfold</a></p>
        <?php if ($flash): ?><p class="alert"><?= forma_h($flash) ?></p><?php endif; ?>

<?php if ($lEdit || $newLessonFor):
        $l = $lEdit ?: ['id' => 0, 'program_id' => $newLessonFor['id'], 'title_de' => '', 'title_en' => '', 'text_de' => '', 'text_en' => '', 'cat' => 'flow', 'energy' => 'any', 'minutes' => 15, 'sort' => count(mv_lessons($pdo, (int)$newLessonFor['id'])), 'visible' => 1, 'video_de' => '', 'video_en' => '', 'script_de' => '', 'script_en' => '', 'unlock' => ''];
        $p = mv_program($pdo, (int)$l['program_id']);
        $deOnly = ($p['audience'] ?? 'method') !== 'method'; // nur Method gibt es auf Englisch (Vivien, 30.10.2026)
        $LANGS = $deOnly ? ['de' => 'Deutsch'] : ['de' => 'Deutsch', 'en' => 'Englisch']; ?>
        <h1><?= $l['id'] ? forma_h($l['title_de']) : 'Neue Lektion' ?></h1>
        <p class="muted">in <a href="<?= forma_h($self) ?>?view=method&amp;p=<?= (int)$p['id'] ?>"><?= forma_h($p['title_de']) ?></a></p>
        <?php if ($l['id']): ?>
        <h2>Videos</h2>
        <?php if (!mv_bunny_ok()): ?><p class="card">Damit du Videos hochladen kannst, trag zuerst unten auf der <a href="<?= forma_h($self) ?>?view=method#bunny">Method-Seite unter „Bunny Stream“</a> deine Library-ID und den API-Schlüssel ein.</p>
        <?php else: ?>
        <div class="g2">
        <?php foreach ($LANGS as $lg => $ln): $g = $l['video_' . $lg]; $info = $g ? mv_bunny_info($g) : null; ?>
            <div class="vbox" data-lang="<?= $lg ?>">
                <b>Video <?= $ln ?></b>
                <?php if ($g): ?>
                    <?php if (($info['status'] ?? 0) === 4): ?><iframe src="<?= forma_h(mv_embed($g)) ?>" style="width:100%;aspect-ratio:16/9;border:0;border-radius:12px" allowfullscreen></iframe><span class="muted small">Fertig · <?= (int)round(($info['length'] ?? 0) / 60) ?> Min.</span>
                    <?php else: ?><span class="muted small vst" data-guid="<?= forma_h($g) ?>">Wird bei Bunny verarbeitet …</span><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Video wirklich entfernen?')"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_video_clear"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="lang" value="<?= $lg ?>"><button class="link small" type="submit">Video entfernen</button></form>
                <?php endif; ?>
                <label class="small muted"><?= $g ? 'Durch neues Video ersetzen' : 'Video auswählen (MP4/MOV vom Handy oder Computer)' ?></label>
                <input type="file" accept="video/*" class="vfile">
                <div class="vbar" hidden><i></i></div><span class="small muted vmsg"></span>
            </div>
        <?php endforeach; ?>
        </div>
        <p class="small muted">Das Video geht direkt von deinem Gerät zu Bunny, du kannst große Dateien hochladen. Lass die Seite offen, bis „fertig hochgeladen“ erscheint. Danach wandelt Bunny es in ein paar Minuten für alle Geräte um.</p>
        <?php endif; endif; ?>

        <h2>Angaben</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_lesson_save"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="program_id" value="<?= (int)$l['program_id'] ?>">
            <div class="g2"><label>Titel (Deutsch)<input name="title_de" value="<?= forma_h($l['title_de']) ?>" required></label><?php if (!$deOnly): ?><label>Titel (Englisch)<input name="title_en" value="<?= forma_h($l['title_en']) ?>"></label><?php endif; ?></div>
            <div class="g2"><label>Kurzbeschreibung (Deutsch)<textarea name="text_de" rows="3"><?= forma_h($l['text_de']) ?></textarea></label><?php if (!$deOnly): ?><label>Kurzbeschreibung (Englisch)<textarea name="text_en" rows="3"><?= forma_h($l['text_en']) ?></textarea></label><?php endif; ?></div>
            <div class="g3"><label>Art<select name="cat"><?= $catOpt($l['cat']) ?></select></label><label>Energie<select name="energy"><?= $enOpt($l['energy']) ?></select></label><label>Minuten<input name="minutes" type="number" min="0" max="240" value="<?= (int)$l['minutes'] ?>"></label></div>
            <div class="g3"><label>Reihenfolge<input name="sort" type="number" value="<?= (int)$l['sort'] ?>"></label><label>Erscheint am <span class="small muted">(leer = mit dem Programm)</span><input type="date" name="unlock" value="<?= forma_h((string)$l['unlock']) ?>"></label><label class="inl"><input type="checkbox" name="visible" value="1" <?= $l['visible'] ? 'checked' : '' ?>> sichtbar für Mitglieder</label></div>
            <details class="more" <?= ($l['script_de'] . $l['script_en']) !== '' ? 'open' : '' ?>><summary>Sprechtext für Voice-over / Teleprompter</summary>
                <p class="small muted">Zeitmarken wie „0:45“ am Zeilenanfang werden im Teleprompter hervorgehoben, [Hinweise in eckigen Klammern] erscheinen klein.</p>
                <div class="g2"><label>Deutsch<textarea class="script" name="script_de"><?= forma_h($l['script_de']) ?></textarea></label><?php if (!$deOnly): ?><label>Englisch<textarea class="script" name="script_en"><?= forma_h($l['script_en']) ?></textarea></label><?php endif; ?></div>
                <?php if ($l['id']): ?><p class="acts"><a class="btn btn-ghost btn-sm" target="_blank" href="<?= forma_h($self) ?>?view=method&amp;prompter=<?= (int)$l['id'] ?>&amp;lang=de">Teleprompter Deutsch</a> <?php if (!$deOnly): ?><a class="btn btn-ghost btn-sm" target="_blank" href="<?= forma_h($self) ?>?view=method&amp;prompter=<?= (int)$l['id'] ?>&amp;lang=en">Teleprompter Englisch</a><?php endif; ?></p><?php endif; ?>
            </details>
            <button class="btn" type="submit">Speichern</button>
        </form>
        <?php if ($l['id']): ?><form method="post" onsubmit="return confirm('Lektion mit Videos löschen?')" style="margin-top:12px"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_lesson_delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="link small" type="submit">Lektion löschen</button></form><?php endif; ?>

        <script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js"></script>
        <script>
        (function(){
          const csrf=<?= json_encode(csrf()) ?>, lid=<?= (int)$l['id'] ?>;
          function post(a,data){const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in data)f.append(k,data[k]);return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())}
          function watch(el,g){const t=setInterval(()=>fetch(location.pathname+'?view=method&vstatus='+g,{credentials:'same-origin'}).then(r=>r.json()).then(j=>{
            if(j.status===4){clearInterval(t);el.textContent='Fertig umgewandelt ✓ – lade die Seite neu, um es anzusehen.';}
            else if(j.status>=5){clearInterval(t);el.textContent='Bunny konnte das Video nicht umwandeln. Bitte versuch es mit einer anderen Datei.';}
            else el.textContent='Bunny wandelt das Video um'+(j.progress?' ('+j.progress+' %)':'')+' …';}),5000)}
          document.querySelectorAll('.vst').forEach(el=>watch(el,el.dataset.guid));
          document.querySelectorAll('.vbox').forEach(box=>{const inp=box.querySelector('.vfile');if(!inp)return;
            inp.addEventListener('change',()=>{const file=inp.files[0];if(!file)return;const bar=box.querySelector('.vbar'),fill=bar.querySelector('i'),msg=box.querySelector('.vmsg');
              msg.textContent='Wird vorbereitet …';inp.disabled=true;
              post('mm_mv_video_new',{id:lid,lang:box.dataset.lang}).then(j=>{if(j.error){msg.textContent=j.error;inp.disabled=false;return;}
                bar.hidden=false;const up=new tus.Upload(file,{endpoint:j.endpoint,retryDelays:[0,3000,10000,30000,60000],chunkSize:50*1024*1024,
                  headers:{AuthorizationSignature:j.sig,AuthorizationExpire:String(j.expire),VideoId:j.guid,LibraryId:String(j.lib)},
                  metadata:{filetype:file.type,title:file.name},
                  onError:e=>{msg.textContent='Upload unterbrochen: '+e.message+' – wähl die Datei einfach nochmal aus.';inp.disabled=false},
                  onProgress:(s,t)=>{const p=Math.round(s/t*100);fill.style.width=p+'%';msg.textContent='Lädt hoch … '+p+' %'},
                  onSuccess:()=>{post('mm_mv_video_set',{id:lid,lang:box.dataset.lang,guid:j.guid}).then(()=>{msg.textContent='Fertig hochgeladen ✓';watch(msg,j.guid)})}});
                up.start();}).catch(()=>{msg.textContent='Das hat nicht geklappt. Bitte lade die Seite neu.';inp.disabled=false});});});
        })();
        </script>

<?php elseif ($pEdit || isset($_GET['neuprog'])):
        $p = $pEdit ?: ['id' => 0, 'audience' => ($_GET['neuprog'] ?? '') === 'unfold' ? 'unfold' : 'method', 'title_de' => '', 'title_en' => '', 'intro_de' => '', 'intro_en' => '', 'unlock' => '', 'theme' => '', 'sort' => 0, 'visible' => 1]; ?>
        <h1><?= $p['id'] ? forma_h($p['title_de']) : 'Neues Programm' ?></h1>
        <?php if ($p['id']): $ls = mv_lessons($pdo, (int)$p['id']); ?>
        <h2>Lektionen</h2>
        <div class="card">
            <?php if (!$ls): ?><p class="muted small">Noch keine Lektion.</p><?php endif; ?>
            <?php foreach ($ls as $l): ?><div class="ln"><span><a href="<?= forma_h($self) ?>?view=method&amp;l=<?= (int)$l['id'] ?>"><strong><?= forma_h($l['title_de']) ?></strong></a><br><span class="small muted"><?= $l['unlock'] !== '' ? 'ab ' . date('d.m.', strtotime($l['unlock'])) . ' · ' : '' ?><?= forma_h(MV_CATS[$l['cat']][0] ?? '') ?> · <?= (int)$l['minutes'] ?> Min. · <?= forma_h(MV_ENERGY[$l['energy']][0] ?? '') ?><?= $l['visible'] ? '' : ' · ausgeblendet' ?></span></span>
                <span class="acts"><span class="chip <?= $l['video_de'] ? 'ok' : '' ?>">DE <?= $l['video_de'] ? '✓' : '–' ?></span><span class="chip <?= $l['video_en'] ? 'ok' : '' ?>">EN <?= $l['video_en'] ? '✓' : '–' ?></span></span></div><?php endforeach; ?>
            <p style="margin-top:10px"><a class="btn btn-sm" href="<?= forma_h($self) ?>?view=method&amp;neu=<?= (int)$p['id'] ?>">+ Lektion</a></p>
        </div>
        <h2 id="dateien">Workbooks &amp; Downloads</h2>
        <div class="card stack">
            <?php foreach (mv_files($pdo, (int)$p['id']) as $f): ?><div class="ln"><span><?= forma_h($f['label_de']) ?> <span class="small muted">· <?= $f['lang'] === 'both' ? 'für alle' : ($f['lang'] === 'de' ? 'nur Deutsch' : 'nur Englisch') ?></span></span>
                <form method="post" onsubmit="return confirm('Datei löschen?')"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_file_delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="link small" type="submit">löschen</button></form></div><?php endforeach; ?>
            <form method="post" enctype="multipart/form-data" class="stack"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_file_add"><input type="hidden" name="program_id" value="<?= (int)$p['id'] ?>">
                <div class="g3"><label>Name (Deutsch)<input name="label_de" placeholder="Workbook Dezember"></label><label>Name (Englisch)<input name="label_en" placeholder="December workbook"></label><label>Für<select name="lang"><option value="both">alle</option><option value="de">nur Deutsch</option><option value="en">nur Englisch</option></select></label></div>
                <input type="file" name="file" accept=".pdf,.png,.jpg,.jpeg,.zip" required><button class="btn btn-sm" type="submit">Hochladen</button></form>
            <p class="small muted">Workbook, Planner, Meal-Prep oder Rezepte: Die Frauen sehen sie als Knöpfe beim Programm.</p>
        </div>
        <?php endif; ?>
        <h2 id="prog">Angaben</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_prog_save"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <div class="g2"><label>Für<select name="audience"><option value="method" <?= $p['audience'] === 'method' ? 'selected' : '' ?>>Method (alle Mitglieder)</option><option value="unfold" <?= $p['audience'] === 'unfold' ? 'selected' : '' ?>>Unfold (nur Unfold-Teilnehmerinnen)</option></select></label>
                <label>Freischalten am (leer = sofort, z. B. Ankommen)<input type="date" name="unlock" value="<?= forma_h($p['unlock']) ?>"></label></div>
            <div class="g2"><label>Titel (Deutsch)<input name="title_de" value="<?= forma_h($p['title_de']) ?>" required placeholder="Jänner · Neubeginn"></label><label>Titel (Englisch)<input name="title_en" value="<?= forma_h($p['title_en']) ?>" placeholder="January · New beginnings"></label></div>
            <div class="g2"><label>Einleitung (Deutsch)<textarea name="intro_de" rows="4"><?= forma_h($p['intro_de']) ?></textarea></label><label>Einleitung (Englisch)<textarea name="intro_en" rows="4"><?= forma_h($p['intro_en']) ?></textarea></label></div>
            <div class="g3"><label>Reihenfolge<input type="number" name="sort" value="<?= (int)$p['sort'] ?>"></label><label>Kürzel <span class="small muted">(ankommen = auch in den ersten 14 Tagen offen)</span><input name="theme" value="<?= forma_h($p['theme']) ?>"></label><label class="inl"><input type="checkbox" name="visible" value="1" <?= $p['visible'] ? 'checked' : '' ?>> sichtbar</label></div>
            <button class="btn" type="submit">Speichern</button>
        </form>
        <?php if ($p['id']): ?><form method="post" onsubmit="return confirm('Programm mit allen Lektionen, Videos und Dateien löschen?')" style="margin-top:12px"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_prog_delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="link small" type="submit">Programm löschen</button></form><?php endif; ?>

<?php else: ?>
        <?php mm_subnav('method'); ?>
        <section class="nh"><p class="eyebrow">Movecia · On-Demand</p><h1>Method &amp; Unfold</h1><p>Programme und Videos, Mitglieder, Unfold-Plätze und Preise.</p><p style="margin-top:12px"><a href="<?= forma_h($self) ?>?view=vorschau#method" style="display:inline-block;padding:8px 16px;border-radius:999px;background:#E8D6CA;color:#4A3A41;text-decoration:none;font-size:15px">👁 Als Teilnehmerin ansehen</a></p></section>
        <div class="ns">
            <a href="#mitglieder"><b><?= $mActive ?></b><span>aktive Method-Mitglieder</span></a>
            <a href="#unfold"><b><?= $uTaken ?>/<?= (int)$c['u_seats'] ?></b><span>Unfold-Plätze belegt</span></a>
            <span class="st"><b><?= forma_h(mv_eur($mrr)) ?></b><span>Method pro Monat (ungefähr)</span></span>
            <a href="#programme"><b><?= count($progs) ?></b><span>Programme</span></a>
        </div>
        <div class="nl">
            <a href="<?= forma_h($site) ?>/method/" target="_blank">Method-Seite</a>
            <a href="<?= forma_h($site) ?>/unfold/" target="_blank">Unfold-Seite</a>
            <a href="<?= forma_h($site) ?>/mitglieder/?demo=1" target="_blank">Mitgliederbereich (Beispiel)</a>
            <a href="<?= forma_h($site) ?>/mitglieder/" target="_blank">Login für Mitglieder</a>
            <a href="#bunny"<?= mv_bunny_ok() ? '' : ' style="background:#7D5E68;color:#FBF5F7"' ?>>⚙ Bunny (Videos)<?= mv_bunny_ok() ? ' ✓' : ' verbinden' ?></a>
        </div>
        <?php if (!mv_bunny_ok()) echo $bunnyHtml; ?>

        <h2 id="programme">Programme</h2>
        <div class="card">
            <?php foreach ($progs as $p): $n = count(mv_lessons($pdo, (int)$p['id'])); ?>
            <div class="prow"><a class="t" href="<?= forma_h($self) ?>?view=method&amp;p=<?= (int)$p['id'] ?>"><?= forma_h($p['title_de']) ?></a>
                <span class="small muted"><?= $p['audience'] === 'unfold' ? 'Unfold' : 'Method' ?> · <?= $p['unlock'] === '' ? 'sofort offen' : 'ab ' . date('d.m.Y', strtotime($p['unlock'])) ?> · <?= $n ?> Lektion<?= $n === 1 ? '' : 'en' ?><?= $p['visible'] ? '' : ' · ausgeblendet' ?></span></div>
            <?php endforeach; ?>
            <p class="acts" style="margin-top:10px"><a class="btn btn-sm" href="<?= forma_h($self) ?>?view=method&amp;neuprog=method">+ Monatsprogramm Method</a> <a class="btn btn-ghost btn-sm" href="<?= forma_h($self) ?>?view=method&amp;neuprog=unfold">+ Unfold-Monat</a></p>
            <p class="small muted">Jedes Programm öffnet sich am eingetragenen Tag von selbst (z. B. am 1. des Monats). Neue Mitglieder sehen alle bisherigen Monate.</p>
        </div>

        <h2 id="mitglieder">Mitglieder</h2>
        <div class="card">
            <?php if (!$real): ?><p class="muted small">Noch niemand. Sobald jemand bucht, steht sie hier und du bekommst eine Mail.</p><?php endif; ?>
            <?php foreach ($real as $s): $pl = $plans[$s['plan']] ?? ['de' => $s['plan']]; ?>
            <div class="ln"><span><strong><?= forma_h($s['name'] ?: $s['email']) ?></strong> <span class="small muted"><?= forma_h($s['email']) ?></span><br>
                <span class="small muted"><?= $s['kind'] === 'unfold' ? 'Unfold' : 'Method' ?> · <?= forma_h($pl['de']) ?> · <?= forma_h(mv_eur((int)$s['first_cents'])) ?><?= (int)$s['special'] ? ' (Gathering-Preis)' : '' ?> · seit <?= forma_h(date('d.m.Y', strtotime((string)($s['paid_at'] ?: $s['created_at'])))) ?><?= $s['kind'] === 'unfold' ? ' · Call ' . ($s['call_lang'] === 'en' ? 'Englisch' : 'Deutsch') : '' ?><?= (int)$s['bill_start'] > time() ? ' · Abbuchung ab ' . date('d.m.Y', (int)$s['bill_start']) : '' ?><?= (int)$s['cancel_end'] ? ' · gekündigt zum ' . date('d.m.Y', (int)$s['cancel_end']) : '' ?></span></span>
                <span class="acts"><span class="chip <?= $s['status'] === 'aktiv' ? 'ok' : 'wait' ?>"><?= ['aktiv' => 'aktiv', 'beendet' => 'beendet', 'offen' => 'Zahlung offen'][$s['status']] ?? forma_h($s['status']) ?></span>
                <?php if ($s['status'] === 'aktiv'): ?><form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_resend"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="link small" type="submit">Zugang nochmal schicken</button></form><?php endif; ?></span></div>
            <?php endforeach; ?>
            <form method="post" style="margin-top:10px"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_sync"><button class="btn btn-ghost btn-sm" type="submit">Mit Stripe abgleichen</button></form>
            <p class="small muted">Rechnungen gehen nach jeder Zahlung automatisch raus (Movecia-Design, Kopie an hello@forma-pottery.com), auch bei jeder Verlängerung.</p>
        </div>

        <h2 id="unfold">Unfold</h2>
        <div class="card stack">
            <p>Start <?= forma_h(date('d.m.Y', mv_ts($c['u_start']))) ?> · Ende <?= forma_h(date('d.m.Y', mv_unfold_end())) ?> · <?= $uTaken ?> von <?= (int)$c['u_seats'] ?> Plätzen belegt · <?= $c['u_open'] ? 'buchbar' : 'gerade nicht buchbar' ?></p>
            <?php $cfgRaw = mm_cfg('method') ?? []; ?>
            <?php if (!empty($cfgRaw['u_room_de'])): ?>
                <p class="small">Videoraum: <a href="<?= forma_h((string)($cfgRaw['u_host_de'] ?? $cfgRaw['u_room_de'])) ?>" target="_blank">als Gastgeberin öffnen</a></p>
                <p class="small muted">Die Teilnehmerinnen kommen über den Knopf „Zum Call“ in ihrem Mitgliederbereich hinein (ab einem Tag vor dem Start sichtbar).</p>
            <?php else: ?>
                <form method="post"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_rooms"><button class="btn btn-sm" type="submit">Videoraum für die Calls anlegen</button></form>
                <p class="small muted">Ein Raum, gleicher Link für alle Wochen. Unfold gibt es nur auf Deutsch.</p>
            <?php endif; ?>
        </div>

        <h2 id="preise">Preise &amp; Termine</h2>
        <form method="post" class="card stack"><?= $csrf ?><input type="hidden" name="action" value="mm_mv_cfg">
            <b>Method</b>
            <p class="small muted">Gerade gilt: <?= $c['eb_m'] ? 'Early-Bird bis ' . date('d.m.Y', $c['eb_m_end']) : 'regulärer Preis' ?>. Wer zum Early-Bird-Preis bucht, behält ihn, solange das Abo läuft.</p>
            <div class="g3"><label>Early-Bird monatlich €<input name="eb_m1" value="<?= $eu($raw['eb_m1']) ?>"></label><label>Early-Bird 3 Monate €<input name="eb_m3" value="<?= $eu($raw['eb_m3']) ?>"></label><label>Early-Bird 1 Jahr €<input name="eb_m12" value="<?= $eu($raw['eb_m12']) ?>"></label></div>
            <label>Early-Bird gilt bis einschließlich<input type="date" name="eb_m_until" value="<?= forma_h($raw['eb_m_until']) ?>"></label>
            <div class="g3"><label>danach monatlich €<input name="m1" value="<?= $eu($raw['m1']) ?>"></label><label>danach 3 Monate €<input name="m3" value="<?= $eu($raw['m3']) ?>"></label><label>danach 1 Jahr €<input name="m12" value="<?= $eu($raw['m12']) ?>"></label></div>
            <div class="g3"><label>Gathering: 1. Monat €<input name="g_m1" value="<?= $eu($raw['g_m1']) ?>"></label><label>Gathering: 3 Monate €<input name="g_m3" value="<?= $eu($raw['g_m3']) ?>"></label><label>Gathering: 1. Jahr €<input name="g_m12" value="<?= $eu($raw['g_m12']) ?>"></label></div>
            <p class="small muted">Die Gathering-Preise gelten während der Early-Bird-Zeit. Danach bekommt man im Gathering für die erste Zahlung den Early-Bird-Preis.</p>
            <label>Start der Method (vorher buchbar, Abbuchung ab diesem Tag)<input type="date" name="launch" value="<?= forma_h($c['launch']) ?>"></label>
            <b>Unfold</b>
            <p class="small muted">Gerade gilt: <?= $c['eb_u'] ? 'Early-Bird bis ' . date('d.m.Y', $c['eb_u_end']) : 'regulärer Preis' ?>.</p>
            <div class="g3"><label>Early-Bird auf einmal €<input name="eb_u_once" value="<?= $eu($raw['eb_u_once']) ?>"></label><label>Early-Bird Rate (3 ×) €<input name="eb_u_inst" value="<?= $eu($raw['eb_u_inst']) ?>"></label><label>Early-Bird bis einschließlich<input type="date" name="eb_u_until" value="<?= forma_h($raw['eb_u_until']) ?>"></label></div>
            <div class="g3"><label>danach auf einmal €<input name="u_once" value="<?= $eu($raw['u_once']) ?>"></label><label>danach Rate (3 ×) €<input name="u_inst" value="<?= $eu($raw['u_inst']) ?>"></label><label>Info-Gathering €<input name="g_u_once" value="<?= $eu($raw['g_u_once']) ?>"></label></div>
            <div class="g3"><label>Start<input type="date" name="u_start" value="<?= forma_h($c['u_start']) ?>"></label><label>Ende<input type="date" name="u_end" value="<?= forma_h($c['u_end']) ?>"></label><label>Plätze<input type="number" name="u_seats" min="1" max="40" value="<?= (int)$c['u_seats'] ?>"></label></div>
            <label>Live-Call (nur Deutsch)<input name="u_call_de" value="<?= forma_h($c['u_call_de']) ?>"></label><input type="hidden" name="u_call_en" value="<?= forma_h($c['u_call_en']) ?>">
            <label class="inl"><input type="checkbox" name="u_open" value="1" <?= $c['u_open'] ? 'checked' : '' ?>> Unfold ist buchbar</label>
            <button class="btn" type="submit">Speichern</button>
        </form>

        <?php if (mv_bunny_ok()) echo $bunnyHtml; ?>
<?php endif;
    });
    exit;
}

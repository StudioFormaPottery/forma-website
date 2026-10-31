<?php
// „✨ KI-Filter“ (Vivien, 31.10.2026: „wie die KI-Filter auf Instagram, aber in meiner Verwaltung“):
// Foto wählen → Filter antippen → die KI (Google Gemini, Bildbearbeitung) macht das Foto im Look neu,
// Person, Gesicht, Tattoos, Pose und Raum bleiben gleich. Ergebnis „In Fotos sichern“ (Web Share).
// Schlüssel nur in ../forma-data/movecia-gemini.json (nie im Repo), Ergebnisse in ../forma-data/kifilter/
// (nur angemeldet abrufbar, nach 30 Tagen automatisch gelöscht). ?view=kifilter, Aktionen mm_kf_*.
declare(strict_types=1);

/** Die Filter. Prompt immer englisch (versteht das Modell am besten). */
const KF_FILTERS = [
    'dunkel' => ['🌙', 'Dunkel & verträumt', 'Dunkler Raum, ein warmer goldener Lichtstreifen, weiche Pflanzenschatten',
        'Relight this photo into a cinematic, low-key, dreamy "golden shadow" look. The room falls into deep, soft darkness (charcoal and deep brown tones, no purple or coloured LED light). A single warm, golden late-afternoon sunbeam enters from the side through a window and falls in a soft-edged diagonal stripe across the person\'s face, shoulders and raised arms, so the skin glows warm and natural with gentle highlights while the rest of the body sinks into shadow. Add soft, slightly blurred shadows of large monstera leaves on the wall behind, cast by the same sunbeam. Rich deep blacks, high but gentle contrast, warm amber highlights, true-to-life skin texture with a subtle healthy sheen, light film grain. Calm, intimate, mindful, dreamy mood like a still from an arthouse film.'],
    'hell' => ['☀️', 'Hell & luftig', 'Weiches Morgenlicht am Fenster, Creme- und Beigetöne',
        'Relight this photo with soft, airy morning window light: bright but gentle daylight from the side, creamy whites, warm beige and blush tones, soft shadows, a light hazy glow on the window side, no harsh contrast and no purple or coloured LED tint. Natural, fresh skin with true texture. Calm, clean, light editorial wellness look.'],
    'verspielt' => ['🌸', 'Hell & verspielt', 'Pastell, sanfte Lichtreflexe und ein Hauch Magie',
        'Relight this photo into a bright, playful, dreamy look: soft pastel tones (blush, peach, cream, a touch of lavender), warm sunlight with gentle lens flares and a few soft round bokeh light spots and sparkles floating in the air, delicate rainbow light reflections (like light through a prism) on the wall, airy and happy mood, no purple LED tint. Natural skin tones with true texture, still photorealistic, not a cartoon.'],
];
const KF_KEEP = 'Keep the photo exactly the same otherwise: the same person with the same face, identity, facial expression, hair, skin tone, body, tattoos, clothing and pose, the same camera angle and framing, the same room, furniture, plants and objects in the same places. Do not add or remove people or objects, do not change the body shape, do not beautify or reshape the face. Photorealistic result, no text, no logo, no watermark, no frame.';
const KF_MODELS = ['gemini-3.1-flash-image-preview', 'gemini-2.5-flash-image'];
const KF_RATIOS = ['1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9'];

function kf_key(): string { return (string)(mm_cfg('gemini')['api_key'] ?? ''); }
function kf_dir(): string { $d = forma_data_dir() . '/kifilter'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function kf_index(): array { $j = json_decode((string)@file_get_contents(kf_dir() . '/index.json'), true); return is_array($j) ? $j : []; }
function kf_save_index(array $l): void { file_put_contents(kf_dir() . '/index.json', json_encode(array_values($l), JSON_UNESCAPED_UNICODE)); }
function kf_cleanup(): void
{
    $l = kf_index(); $keep = []; $lim = time() - 30 * 86400;
    foreach ($l as $r) { if (($r['t'] ?? 0) < $lim) { @unlink(kf_dir() . '/' . $r['f']); @unlink(kf_dir() . '/' . ($r['o'] ?? '-')); } else $keep[] = $r; }
    if (count($keep) !== count($l)) kf_save_index($keep);
}
function kf_http(string $url, ?array $body = null, int $timeout = 20): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    return [$code, json_decode($raw, true) ?: [], $err];
}
/** Bild an Gemini schicken, gibt [mime, bytes] zurück oder wirft eine verständliche Meldung. */
function kf_generate(string $img, string $mime, string $prompt, string $ratio, ?array $ref): array
{
    $key = kf_key(); if ($key === '') throw new RuntimeException('Bitte zuerst unten deinen Google-Schlüssel eintragen.');
    $parts = [['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($img)]]];
    if ($ref) {
        $parts[] = ['inline_data' => ['mime_type' => $ref[0], 'data' => base64_encode($ref[1])]];
        $prompt = 'Image 1 is the photo to edit. Image 2 is only a style reference: match its lighting, mood and colour grading, but take NOTHING else from it (not the person, not the room, not the pose). ' . $prompt;
    }
    $parts[] = ['text' => $prompt];
    $body = ['contents' => [['role' => 'user', 'parts' => $parts]],
        'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE'], 'imageConfig' => ['aspectRatio' => $ratio]]];
    $last = 'Die KI hat kein Bild zurückgegeben. Bitte versuch es noch einmal.';
    foreach (KF_MODELS as $m) {
        [$code, $j, $err] = kf_http((getenv('KF_GEMINI_API') ?: 'https://generativelanguage.googleapis.com') . '/v1beta/models/' . $m . ':generateContent?key=' . rawurlencode($key), $body, 150);
        if ($code === 200) {
            foreach (($j['candidates'][0]['content']['parts'] ?? []) as $p) {
                $d = $p['inlineData'] ?? $p['inline_data'] ?? null;
                if ($d && !empty($d['data'])) return [(string)($d['mimeType'] ?? $d['mime_type'] ?? 'image/png'), base64_decode((string)$d['data'])];
            }
            $why = (string)($j['candidates'][0]['finishReason'] ?? $j['promptFeedback']['blockReason'] ?? '');
            $last = $why !== '' && $why !== 'STOP' ? 'Die KI wollte dieses Foto nicht bearbeiten (' . $why . '). Probier ein anderes Foto.' : $last;
            continue;
        }
        $msg = (string)($j['error']['message'] ?? $err);
        if ($code === 400 && stripos($msg, 'API key') !== false) throw new RuntimeException('Google nimmt den Schlüssel nicht an. Bitte trag ihn unten neu ein.');
        if ($code === 429) $last = 'Gerade zu viele Anfragen oder das Guthaben bei Google ist aufgebraucht. Bitte in einer Minute noch einmal (oder Abrechnung in Google AI Studio prüfen).';
        elseif ($code === 403) $last = 'Google erlaubt die Bildbearbeitung mit diesem Schlüssel noch nicht. In Google AI Studio die Abrechnung (Billing) für das Projekt aktivieren.';
        elseif ($code !== 404) $last = 'Google meldet: ' . mb_substr($msg, 0, 200);
    }
    throw new RuntimeException($last);
}
function kf_ref(string $fid): ?array
{
    $f = kf_dir() . '/ref-' . $fid . '.jpg';
    return is_file($f) ? ['image/jpeg', (string)file_get_contents($f)] : null;
}

if ($view === 'kifilter' || str_starts_with((string)$action, 'mm_kf_')) {
    $kfJson = function (array $x): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; };
    $self = strtok((string)($_SERVER['REQUEST_URI'] ?? '/verwaltung/'), '?');

    if ($action === 'mm_kf_key') {
        $key = trim((string)($_POST['gemini_key'] ?? ''));
        if ($key === '') { mm_save_cfg('gemini', null); go($self . '?view=kifilter', 'Schlüssel entfernt.'); }
        [$code] = kf_http((getenv('KF_GEMINI_API') ?: 'https://generativelanguage.googleapis.com') . '/v1beta/models?key=' . rawurlencode($key));
        if ($code !== 200) go($self . '?view=kifilter#schluessel', 'Google hat den Schlüssel nicht angenommen. Bitte kopiere ihn noch einmal vollständig aus Google AI Studio (beginnt meist mit „AIza“).');
        mm_save_cfg('gemini', ['api_key' => $key]);
        go($self . '?view=kifilter', '✓ Verbunden. Jetzt einfach ein Foto wählen.');
    }
    if ($action === 'mm_kf_run') {
        @set_time_limit(300);
        $f = $_FILES['b'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) $kfJson(['error' => 'Das Foto ist nicht angekommen (Fehler ' . (int)($f['error'] ?? -1) . ').']);
        $fid = (string)($_POST['filter'] ?? 'dunkel'); if (!isset(KF_FILTERS[$fid])) $fid = 'dunkel';
        $ratio = (string)($_POST['ratio'] ?? '9:16'); if (!in_array($ratio, KF_RATIOS, true)) $ratio = '9:16';
        $wish = trim(mb_substr((string)($_POST['wish'] ?? ''), 0, 300));
        $img = (string)file_get_contents($f['tmp_name']);
        $mime = (string)(@getimagesizefromstring($img)['mime'] ?? '');
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) $kfJson(['error' => 'Bitte ein Foto (JPG oder PNG) wählen.']);
        $prompt = KF_FILTERS[$fid][3] . ' ' . KF_KEEP . ($wish !== '' ? ' Additional wish from the photographer (follow it, it is written in German): ' . $wish : '');
        try { [$om, $out] = kf_generate($img, $mime, $prompt, $ratio, kf_ref($fid)); }
        catch (Throwable $e) { $kfJson(['error' => $e->getMessage()]); }
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $ext = $om === 'image/jpeg' ? 'jpg' : ($om === 'image/webp' ? 'webp' : 'png');
        file_put_contents(kf_dir() . '/' . $id . '.' . $ext, $out);
        file_put_contents(kf_dir() . '/' . $id . '-orig.jpg', $img);
        $l = kf_index(); array_unshift($l, ['id' => $id, 'f' => $id . '.' . $ext, 'o' => $id . '-orig.jpg', 'flt' => $fid, 't' => time()]);
        kf_save_index($l);
        $kfJson(['ok' => true, 'id' => $id, 'url' => '?view=kifilter&img=' . $id, 'name' => 'Movecia-' . KF_FILTERS[$fid][1] . '-' . $id . '.' . $ext]);
    }
    if ($action === 'mm_kf_del') {
        $id = (string)($_POST['id'] ?? ''); $l = kf_index();
        foreach ($l as $i => $r) if ($r['id'] === $id) { @unlink(kf_dir() . '/' . $r['f']); @unlink(kf_dir() . '/' . $r['o']); unset($l[$i]); }
        kf_save_index($l); $kfJson(['ok' => true]);
    }
    if ($action === 'mm_kf_ref') {
        $fid = (string)($_POST['filter'] ?? ''); $f = $_FILES['b'] ?? null;
        if (!isset(KF_FILTERS[$fid])) $kfJson(['error' => 'Unbekannter Filter.']);
        if (($_POST['del'] ?? '') === '1') { @unlink(kf_dir() . '/ref-' . $fid . '.jpg'); $kfJson(['ok' => true]); }
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) $kfJson(['error' => 'Das Vorbild ist nicht angekommen.']);
        move_uploaded_file($f['tmp_name'], kf_dir() . '/ref-' . $fid . '.jpg');
        $kfJson(['ok' => true]);
    }
    if ($view === 'kifilter' && isset($_GET['img'])) {
        $id = (string)$_GET['img']; $orig = isset($_GET['o']);
        foreach (kf_index() as $r) if ($r['id'] === $id) {
            $p = kf_dir() . '/' . ($orig ? $r['o'] : $r['f']);
            if (is_file($p)) { header('Content-Type: ' . (@getimagesize($p)['mime'] ?? 'image/png')); header('Cache-Control: private, max-age=86400'); readfile($p); exit; }
        }
        http_response_code(404); exit;
    }
    if ($view === 'kifilter' && isset($_GET['ref'])) {
        $p = kf_dir() . '/ref-' . preg_replace('/[^a-z]/', '', (string)$_GET['ref']) . '.jpg';
        if (is_file($p)) { header('Content-Type: image/jpeg'); header('Cache-Control: private, no-cache'); readfile($p); exit; }
        http_response_code(404); exit;
    }
    if ($view === 'kifilter') {
        kf_cleanup();
        $hasKey = kf_key() !== ''; $list = array_slice(kf_index(), 0, 60);
        $refs = []; foreach (KF_FILTERS as $k => $_) $refs[$k] = is_file(kf_dir() . '/ref-' . $k . '.jpg');
        render_page('KI-Filter', function () use ($hasKey, $list, $refs) { $h = 'forma_h'; ?>
<style>
main{max-width:1240px}
.mh p{margin:0 0 18px;color:#6B5658;font-size:19px;line-height:1.5;max-width:70ch}
.kf-step{display:flex;align-items:center;gap:10px;margin:18px 0 10px!important;font-size:26px!important}
.kf-step span{font:500 16px/1 "Hanken Grotesk",system-ui,sans-serif;width:30px;height:30px;border-radius:50%;background:#EADFDE;color:#4A3A41;display:inline-flex;align-items:center;justify-content:center}
.kf-q{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 6px}.kf-q[hidden]{display:none}
.kf-q div{position:relative;width:76px;height:100px;border-radius:12px;overflow:hidden;background:#EADFDE}.kf-q img{width:100%;height:100%;object-fit:cover}
.kf-q button{position:absolute;top:3px;right:3px;width:24px;height:24px;border-radius:50%;border:0;background:rgba(46,37,41,.6);color:#fff;font-size:12px;cursor:pointer}
.kf-extra{margin:0 0 14px}.kf-extra>summary{cursor:pointer;font-size:16px;color:#7D5E68;padding:6px 0;margin-bottom:8px}
.kf-go{display:block;width:100%;max-width:520px;margin:4px 0 18px;font:500 22px/1.2 "Hanken Grotesk",system-ui,sans-serif;min-height:60px;padding:14px 26px;border-radius:999px;border:0;background:#7D5E68;color:#FBF5F7;cursor:pointer;box-shadow:0 14px 26px -16px rgba(74,58,65,.8)}
.kf-go:disabled{opacity:.45;cursor:default;box-shadow:none}
.kf-flt{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 14px}
.kf-flt button{font:inherit;text-align:left;padding:14px 16px;border-radius:22px;border:1.5px solid #E6DCD2;background:#FBF8F4;color:#4A3A41;cursor:pointer;min-height:96px;transition:border-color .15s,background .15s}
.kf-flt button i{font-style:normal;font-size:24px;display:block}
.kf-flt button b{display:block;font:500 23px/1.15 "Cormorant Garamond",Georgia,serif;margin:4px 0 2px}
.kf-flt button span{display:block;font-size:15px;color:#7D7076;line-height:1.4}
.kf-flt button[aria-pressed=true]{background:#4A3A41;border-color:#4A3A41;color:#FBF5F7}
.kf-flt button[aria-pressed=true] span{color:#EADFDE}
.kf-opt{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:0 0 12px;font-size:16px;color:#6B5658}
.kf-opt button{font:inherit;font-size:15px;padding:7px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;min-height:40px}
.kf-opt button[aria-pressed=true]{background:#7D5E68;border-color:#7D5E68;color:#fff}
.kf-wish{width:100%;box-sizing:border-box;font:inherit;font-size:16px;padding:11px 14px;border-radius:14px;border:1.5px solid #DDD2C6;background:#fff;margin:0 0 14px}
.drop{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:30px 18px;border:2px dashed #C9B0B8;border-radius:28px;background:linear-gradient(135deg,#FBF8F4,#F4E9E3);cursor:pointer;margin-bottom:12px}
.drop.on{border-color:#7D5E68;background:#EADFDE}
.drop b{font:500 28px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41}.drop span{font-size:17px;color:#6B5658}.drop i{font-style:normal;font-size:38px}
.msg{font-size:18px;color:#6B5658;margin:0 0 14px}.msg.ok{color:#3F6B3E}.msg.err{color:#A0413B}.msg:empty{display:none}
.kf-res{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px}
.kf-card{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:22px;padding:10px;display:flex;flex-direction:column;gap:8px}
.kf-pic{position:relative;border-radius:16px;overflow:hidden;background:#EADFDE;user-select:none;-webkit-user-select:none;-webkit-touch-callout:none}
.kf-pic img{display:block;width:100%;height:auto}.kf-pic img.o{position:absolute;inset:0;height:100%;object-fit:cover}
.kf-pic .o{opacity:0;transition:opacity .15s}.kf-pic.show-o .o{opacity:1}
.kf-pic .tag{position:absolute;left:8px;top:8px;background:rgba(251,248,244,.9);color:#4A3A41;font-size:13px;padding:3px 10px;border-radius:999px}
.kf-pic.wait{aspect-ratio:3/4;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;color:#EADFDE;font-size:15px;text-align:center;padding:16px}
.kf-pic.wait:before{content:"";width:34px;height:34px;border-radius:50%;border:3px solid #7D5E68;border-top-color:#EADFDE;animation:kfspin 1s linear infinite}
@keyframes kfspin{to{transform:rotate(360deg)}}
.kf-btns{display:flex;flex-wrap:nowrap;gap:6px;align-items:center}
.kf-btns button,.kf-btns a{font:inherit;font-size:15px;padding:8px 14px;border-radius:999px;border:1.5px solid #DDD2C6;background:#fff;color:#4A3A41;cursor:pointer;text-decoration:none;min-height:40px;display:inline-flex;align-items:center}
.kf-btns .main{background:#7D5E68;border-color:#7D5E68;color:#fff}
.kf-btns .x{margin-left:auto;padding:8px 10px!important;border-color:transparent;color:#B3A3A8}
.kf-all{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 14px}.kf-all[hidden]{display:none}
.kf-all button{font:inherit;font-size:17px;min-height:48px;padding:10px 22px;border-radius:999px;border:0;background:#4A3A41;color:#fff;cursor:pointer}
.kf-more{background:#FBF8F4;border:1.5px solid #E6DCD2;border-radius:22px;padding:4px 16px;margin:22px 0 12px}
.kf-more>summary{cursor:pointer;list-style:none;padding:12px 0;font:500 22px/1.2 "Cormorant Garamond",Georgia,serif;color:#4A3A41}.kf-more>summary::-webkit-details-marker{display:none}
.kf-more p{font-size:16px;color:#6B5658;line-height:1.5;margin:0 0 10px}
.kf-refs{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 14px}
.kf-ref{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:16px;background:#fff;border:1.5px solid #E6DCD2;font-size:15px;color:#4A3A41}
.kf-ref img{width:40px;height:56px;object-fit:cover;border-radius:8px}
.kf-ref button,.kf-ref label{font:inherit;font-size:14px;border:0;background:none;color:#7D5E68;cursor:pointer;text-decoration:underline;padding:4px}
.kf-key form{display:flex;flex-wrap:wrap;gap:8px}.kf-key input{flex:1 1 260px;font:inherit;font-size:16px;padding:11px 14px;border-radius:14px;border:1.5px solid #DDD2C6}
.kf-key button{font:inherit;font-size:16px;padding:10px 20px;border-radius:999px;border:0;background:#7D5E68;color:#fff;cursor:pointer}
.kf-setup{background:#F4E9E3;border-radius:22px;padding:18px 20px;margin:0 0 18px}
.kf-setup h2{margin:0 0 6px}.kf-setup ol{margin:6px 0 12px;padding-left:22px;font-size:17px;color:#4A3A41;line-height:1.6}
@media(max-width:640px){.kf-btns{flex-wrap:wrap}.kf-flt{grid-template-columns:1fr 1fr}.kf-flt button{min-height:84px;padding:12px}.kf-flt button span{display:none}.kf-res{grid-template-columns:1fr 1fr;gap:8px}.kf-btns button,.kf-btns a{font-size:14px;padding:6px 10px}}
</style>
<?php mm_subnav('kifilter'); ?>
<div class="mh"><h1>✨ KI-Filter</h1>
  <p>Foto wählen, Look antippen, auf <b>KI-Filter generieren</b> tippen. Die KI macht dein Foto im gewählten Look neu, du, deine Tattoos, deine Pose und der Raum bleiben gleich. Danach direkt <b>In Fotos sichern</b>.</p></div>
<?php if (!$hasKey): ?>
<div class="kf-setup kf-key" id="schluessel"><h2>Einmal verbinden</h2>
  <ol><li>Öffne <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a> und melde dich mit deinem Google-Konto an.</li>
  <li>Tippe auf <b>„API-Schlüssel erstellen“</b> und kopiere ihn.</li>
  <li>Bei <b>Billing</b> eine Zahlungsart hinterlegen (Bildbearbeitung gibt es nur damit, pro Foto ein paar Cent).</li>
  <li>Schlüssel hier einfügen, fertig.</li></ol>
  <form method="post"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_kf_key">
    <input name="gemini_key" type="password" autocomplete="off" placeholder="Google API-Schlüssel (AIza…)"><button type="submit">Verbinden</button></form>
  <p class="small muted" style="margin:10px 0 0">Der Schlüssel liegt nur auf deinem Server, nie auf GitHub.</p></div>
<?php endif; ?>
<h2 class="kf-step"><span>1</span>Foto wählen</h2>
<label class="drop" id="drop"><i>📷</i><b><?= $hasKey ? 'Foto wählen oder hierher ziehen' : 'Zuerst oben verbinden' ?></b><span>Gern mehrere auf einmal</span><input type="file" id="pick" accept="image/*" multiple hidden<?= $hasKey ? '' : ' disabled' ?>></label>
<div class="kf-q" id="q" hidden></div>
<h2 class="kf-step"><span>2</span>Look wählen</h2>
<div class="kf-flt" id="flt"><?php $first = true; foreach (KF_FILTERS as $k => $f): ?><button type="button" data-f="<?= $k ?>" aria-pressed="<?= $first ? 'true' : 'false' ?>"><i><?= $f[0] ?></i><b><?= $h($f[1]) ?></b><span><?= $h($f[2]) ?></span></button><?php $first = false; endforeach; ?></div>
<details class="kf-extra"><summary>Format &amp; Extra-Wunsch</summary>
<div class="kf-opt" id="rat"><span>Format:</span>
  <button type="button" data-r="auto" aria-pressed="true">Wie das Foto</button><button type="button" data-r="9:16" aria-pressed="false">Story 9:16</button><button type="button" data-r="4:5" aria-pressed="false">Beitrag 4:5</button><button type="button" data-r="1:1" aria-pressed="false">Quadrat</button></div>
<input class="kf-wish" id="wish" maxlength="300" placeholder="Extra-Wunsch, wenn du magst (z. B. „etwas mehr Licht im Gesicht“)"></details>
<button type="button" class="kf-go" id="go" disabled>✨ KI-Filter generieren</button>
<p class="msg" id="msg"></p>
<div class="kf-all" id="all" hidden><button type="button" id="shareAll">📲 Alle neuen in Fotos sichern</button></div>
<div class="kf-res" id="res"><?php foreach ($list as $r): $fl = KF_FILTERS[$r['flt']] ?? KF_FILTERS['dunkel']; ?>
  <div class="kf-card" data-id="<?= $h($r['id']) ?>"><div class="kf-pic"><img class="n" loading="lazy" src="?view=kifilter&amp;img=<?= $h($r['id']) ?>" alt=""><img class="o" loading="lazy" src="?view=kifilter&amp;img=<?= $h($r['id']) ?>&amp;o=1" alt=""><span class="tag"><?= $fl[0] . ' ' . $h($fl[1]) ?></span></div>
  <div class="kf-btns"><button type="button" class="main" data-save>📲 In Fotos sichern</button><button type="button" data-cmp>Vorher</button><button type="button" class="x" data-del title="Löschen">✕</button></div></div>
<?php endforeach; ?></div>
<?php if (!$list): ?><p class="muted" id="empty" style="font-size:16px">Deine bearbeiteten Fotos erscheinen hier und bleiben 30 Tage, dann räumen sie sich von selbst weg.</p><?php endif; ?>
<details class="kf-more"><summary>📌 Vorbild-Bilder für die Filter</summary>
  <p>Wenn du ein Beispielbild hast, das genau den Look zeigt (z. B. dein Meta-AI-Bild), leg es hier beim Filter ab. Die KI nimmt davon nur Licht und Stimmung, nicht die Person oder den Raum. So wird der Look noch gleichmäßiger.</p>
  <div class="kf-refs"><?php foreach (KF_FILTERS as $k => $f): ?><div class="kf-ref" data-ref="<?= $k ?>"><?php if ($refs[$k]): ?><img src="?view=kifilter&amp;ref=<?= $k ?>&amp;v=<?= time() ?>" alt=""><?php endif; ?><span><?= $f[0] . ' ' . $h($f[1]) ?></span>
    <label><?= $refs[$k] ? 'ändern' : '＋ Vorbild' ?><input type="file" accept="image/*" hidden></label><?php if ($refs[$k]): ?><button type="button" data-rdel>entfernen</button><?php endif; ?></div><?php endforeach; ?></div></details>
<?php if ($hasKey): ?><details class="kf-more kf-key" id="schluessel"><summary>⚙️ Google-Schlüssel</summary>
  <p>✓ Verbunden. Neuen Schlüssel einfügen, um ihn zu ersetzen, oder leer lassen und speichern, um die Verbindung zu trennen.</p>
  <form method="post" style="margin-bottom:14px"><input type="hidden" name="csrf" value="<?= $h(csrf()) ?>"><input type="hidden" name="action" value="mm_kf_key">
    <input name="gemini_key" type="password" autocomplete="off" placeholder="Neuer Schlüssel"><button type="submit">Speichern</button></form></details><?php endif; ?>
<script>
(function(){
  const $=id=>document.getElementById(id),csrf=<?= json_encode(csrf()) ?>;
  const post=(a,d,file,name)=>{const f=new FormData();f.append('csrf',csrf);f.append('action',a);for(const k in d)f.append(k,d[k]);if(file)f.append('b',file,name||'foto.jpg');return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(r=>r.json())};
  let flt='dunkel',rat='auto';const fresh=[];
  const sel=(box,attr,set)=>box.querySelectorAll('['+attr+']').forEach(b=>b.onclick=()=>{box.querySelectorAll('['+attr+']').forEach(x=>x.setAttribute('aria-pressed','false'));b.setAttribute('aria-pressed','true');set(b.getAttribute(attr))});
  sel($('flt'),'data-f',v=>flt=v);sel($('rat'),'data-r',v=>rat=v);
  const RATIOS=[[1,1],[2,3],[3,2],[3,4],[4,3],[4,5],[5,4],[9,16],[16,9]];
  const near=(w,h)=>{let best='9:16',d=9;RATIOS.forEach(([a,b])=>{const x=Math.abs(Math.log((w/h)/(a/b)));if(x<d){d=x;best=a+':'+b}});return best};
  // Foto verkleinern (max. 2048 px) und als JPG schicken – auch HEIC vom iPhone
  async function prep(file){let bmp;try{bmp=await createImageBitmap(file,{imageOrientation:'from-image'})}catch(e){bmp=await new Promise((ok,no)=>{const i=new Image();i.onload=()=>ok(i);i.onerror=no;i.src=URL.createObjectURL(file)})}
    const w=bmp.width,h=bmp.height,s=Math.min(1,2048/Math.max(w,h)),c=document.createElement('canvas');c.width=Math.round(w*s);c.height=Math.round(h*s);c.getContext('2d').drawImage(bmp,0,0,c.width,c.height);
    return {blob:await new Promise(r=>c.toBlob(r,'image/jpeg',.9)),ratio:near(w,h),url:c.toDataURL('image/jpeg',.6)}}
  const canShare=f=>!!(navigator.canShare&&navigator.canShare({files:f}));
  async function asFile(card){if(card._file)return card._file;const r=await fetch(card.querySelector('img.n').src,{credentials:'same-origin'});const b=await r.blob();const ext=b.type.includes('jpeg')?'jpg':b.type.includes('webp')?'webp':'png';
    card._file=new File([b],'Movecia-'+card.dataset.id+'.'+ext,{type:b.type});return card._file}
  async function save(cards){const files=[];for(const c of cards)files.push(await asFile(c));
    if(canShare(files)){try{await navigator.share({files});msg('✓ Gesichert. Die Fotos liegen jetzt in deinen Fotos.','ok')}catch(e){}return}
    files.forEach(f=>{const a=document.createElement('a');a.href=URL.createObjectURL(f);a.download=f.name;document.body.appendChild(a);a.click();a.remove()})}
  function msg(t,c){$('msg').className='msg'+(c?' '+c:'');$('msg').textContent=t}
  function wire(card){const pic=card.querySelector('.kf-pic');
    const s=card.querySelector('[data-save]');if(s)s.onclick=()=>save([card]);
    const v=card.querySelector('[data-cmp]');if(v){const on=e=>{e.preventDefault();pic.classList.add('show-o');v.textContent='Nachher'},off=()=>{pic.classList.remove('show-o');v.textContent='Vorher'};
      v.addEventListener('pointerdown',on);['pointerup','pointerleave','pointercancel'].forEach(t=>v.addEventListener(t,off));v.onclick=e=>e.preventDefault()}
    const d=card.querySelector('[data-del]');if(d)d.onclick=()=>{if(confirm('Dieses Foto löschen?'))post('mm_kf_del',{id:card.dataset.id}).then(()=>card.remove())}}
  document.querySelectorAll('.kf-card').forEach(wire);
  async function run(files){if(!files.length)return;
    const e=$('empty');if(e)e.remove();const name=document.querySelector('#flt [aria-pressed=true] b').textContent;let ok=0,bad=0;
    for(let i=0;i<files.length;i++){msg((files.length>1?'Foto '+(i+1)+' von '+files.length+': ':'')+'„'+name+'“ wird angewendet … (etwa 15–30 Sekunden)');
      const card=document.createElement('div');card.className='kf-card';card.innerHTML='<div class="kf-pic wait">Die KI malt dein Licht …</div>';$('res').prepend(card);
      try{const p=await prep(files[i]);const r=rat==='auto'?p.ratio:rat;
        const j=await post('mm_kf_run',{filter:flt,ratio:r,wish:$('wish').value.trim()},p.blob,'foto.jpg');
        if(!j.ok)throw new Error(j.error||'Das hat nicht geklappt.');
        card.dataset.id=j.id;card.innerHTML='<div class="kf-pic"><img class="n" src="'+j.url+'" alt=""><img class="o" src="'+j.url+'&o=1" alt=""><span class="tag">'+document.querySelector('#flt [aria-pressed=true] i').textContent+' '+name+'</span></div><div class="kf-btns"><button type="button" class="main" data-save>📲 In Fotos sichern</button><button type="button" data-cmp>Vorher</button><button type="button" class="x" data-del title="Löschen">✕</button></div>';
        wire(card);fresh.push(card);ok++}
      catch(err){card.remove();bad++;msg(err.message||'Das hat nicht geklappt.','err')}}
    if(ok){msg('✓ Fertig'+(ok>1?': '+ok+' Fotos':'')+'. Tippe auf „In Fotos sichern“.'+(bad?' '+bad+' hat nicht geklappt.':''),'ok');$('all').hidden=fresh.length<2}}
  $('shareAll').onclick=()=>save(fresh.filter(c=>c.isConnected));
  const drop=$('drop'),pick=$('pick');let queue=[];
  const okf=f=>/^image\//.test(f.type)||/\.(jpe?g|png|heic|heif|webp)$/i.test(f.name);
  function showQ(){const q=$('q');q.innerHTML='';queue.forEach((f,i)=>{const d=document.createElement('div');const im=document.createElement('img');im.src=URL.createObjectURL(f);const x=document.createElement('button');x.type='button';x.textContent='✕';x.onclick=()=>{queue.splice(i,1);showQ()};d.append(im,x);q.append(d)});
    q.hidden=!queue.length;$('go').disabled=!queue.length;$('go').textContent=queue.length>1?'✨ KI-Filter generieren ('+queue.length+' Fotos)':'✨ KI-Filter generieren';
    drop.querySelector('b').textContent=queue.length?'Noch ein Foto dazu':'Foto wählen oder hierher ziehen'}
  const add=fl=>{queue=queue.concat([...fl].filter(okf));showQ()};
  pick.onchange=()=>{add(pick.files);pick.value=''};
  $('go').onclick=async()=>{if(!queue.length)return;const list=queue;queue=[];showQ();$('go').disabled=true;await run(list);showQ();$('res').scrollIntoView({behavior:'smooth',block:'start'})};
  ['dragenter','dragover'].forEach(t=>drop.addEventListener(t,e=>{e.preventDefault();drop.classList.add('on')}));
  ['dragleave','drop'].forEach(t=>drop.addEventListener(t,e=>{e.preventDefault();drop.classList.remove('on')}));
  drop.addEventListener('drop',e=>{if(!pick.disabled)add(e.dataTransfer.files)});
  document.querySelectorAll('.kf-ref').forEach(r=>{const k=r.dataset.ref,inp=r.querySelector('input');
    inp.onchange=async()=>{if(!inp.files[0])return;const p=await prep(inp.files[0]);const j=await post('mm_kf_ref',{filter:k},p.blob,'ref.jpg');if(j.ok)location.reload();else alert(j.error)};
    const d=r.querySelector('[data-rdel]');if(d)d.onclick=()=>post('mm_kf_ref',{filter:k,del:'1'}).then(()=>location.reload())});
})();
</script>
<?php
        });
        exit;
    }
}

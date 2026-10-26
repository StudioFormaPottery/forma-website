<?php
// Movecia · Wasserzeichen in den Mitglieder-Videos (Vivien, 26.10.2026)
// Datensparsam: kein Name, keine E-Mail, nur eine kleine Affirmation und ein persönlicher Zugangs-Code
// (pseudonym, nur Vivien kann ihn in der Verwaltung einer Teilnehmerin zuordnen). Steht auf jedem Bildschirmfoto mit drauf.
declare(strict_types=1);

function mm_wm_code(string $kind, int $id): string
{
    $key = $kind === 'natal' ? (function_exists('natal_secret') ? natal_secret() : '') : (function_exists('mv_secret') ? mv_secret() : '');
    if ($key === '') $key = 'movecia-wm';
    return ($kind === 'natal' ? 'MN-' : 'MV-') . strtoupper(substr(hash_hmac('sha256', "wm:$kind:$id", $key), 0, 6));
}

/** Code → Teilnehmerin (für die Verwaltung). */
function mm_wm_lookup(PDO $pdo, string $code): ?array
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^(MV|MN)-[0-9A-F]{6}$/', $code)) return null;
    $kind = str_starts_with($code, 'MN') ? 'natal' : 'mv';
    $t = $kind === 'natal' ? 'mm_natal_members' : 'mv_members';
    try { $rows = $pdo->query("SELECT id, email, name FROM $t")->fetchAll(); } catch (Throwable $e) { return null; }
    foreach ($rows as $r) if (mm_wm_code($kind, (int)$r['id']) === $code) return ['kind' => $kind, 'id' => (int)$r['id'], 'email' => (string)$r['email'], 'name' => (string)$r['name']];
    return null;
}

function mm_wm_script(string $kind, int $id, string $lang = 'de', string $name = ''): string
{
    $first = trim((string)strtok(trim($name), ' '));
    return '<script src="/wildhearts/assets/wasserzeichen.js?v=1" data-code="' . htmlspecialchars(mm_wm_code($kind, $id), ENT_QUOTES) . '" data-name="' . htmlspecialchars($first, ENT_QUOTES) . '" data-lang="' . ($lang === 'en' ? 'en' : 'de') . '" defer></script>';
}

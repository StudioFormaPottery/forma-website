<?php
// Zwei-Stufen-Anmeldung für die Verwaltung (Vivien, 31.10.2026): Passwort + 6-stelliger Code aus Duo
// (oder jeder anderen Authenticator-App). Standard TOTP (RFC 6238, 30 s, SHA-1, 6 Ziffern).
// Settings: tf_secret (Base32), tf_on ('1'), tf_backup (JSON mit SHA-256 der Ersatzcodes), tf_last (letzter benutzter Zeitschritt),
// tf_trust (Schlüssel für „Gerät 30 Tage merken“; neu würfeln = alle gemerkten Geräte vergessen).
declare(strict_types=1);

const TF_TRUST_DAYS = 30;
const TF_COOKIE = 'forma_tf';

function tf_b32_encode(string $bin): string
{
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $a[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function tf_b32_decode(string $s): string
{
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s));
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
    return $out;
}

function tf_code(string $secret, int $step): string
{
    $h = hash_hmac('sha1', pack('N2', 0, $step), tf_b32_decode($secret), true);
    $o = ord($h[19]) & 0x0f;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Prüft einen Code (±30 s Spielraum). Mit $pdo wird jeder Code nur einmal angenommen. */
function tf_check(string $secret, string $code, ?PDO $pdo = null): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6 || $secret === '') return false;
    $now = intdiv(time(), 30);
    $last = $pdo ? (int)(forma_setting($pdo, 'tf_last') ?? 0) : 0;
    foreach ([0, -1, 1] as $d) {
        $s = $now + $d;
        if ($s <= $last) continue;
        if (hash_equals(tf_code($secret, $s), $code)) {
            if ($pdo) forma_set_setting($pdo, 'tf_last', (string)$s);
            return true;
        }
    }
    return false;
}

function tf_on(PDO $pdo): bool
{
    return forma_setting($pdo, 'tf_on') === '1' && (string)(forma_setting($pdo, 'tf_secret') ?? '') !== '';
}

function tf_new_secret(): string
{
    return tf_b32_encode(random_bytes(20));
}

function tf_uri(string $secret): string
{
    return 'otpauth://totp/' . rawurlencode('FORMA Verwaltung:' . FORMA_NOTIFY_EMAIL) . '?secret=' . $secret . '&issuer=' . rawurlencode('FORMA Verwaltung') . '&digits=6&period=30';
}

/** 10 neue Ersatzcodes (je einmal gültig). Gibt sie im Klartext zurück, gespeichert werden nur Prüfsummen. */
function tf_new_backup(PDO $pdo): array
{
    $codes = [];
    $hashes = [];
    for ($i = 0; $i < 10; $i++) {
        $c = strtoupper(substr(tf_b32_encode(random_bytes(6)), 0, 4) . '-' . substr(tf_b32_encode(random_bytes(6)), 0, 4));
        $codes[] = $c;
        $hashes[] = hash('sha256', str_replace('-', '', $c));
    }
    forma_set_setting($pdo, 'tf_backup', json_encode($hashes));
    return $codes;
}

function tf_backup_left(PDO $pdo): int
{
    return count(json_decode((string)(forma_setting($pdo, 'tf_backup') ?? '[]'), true) ?: []);
}

function tf_use_backup(PDO $pdo, string $code): bool
{
    $c = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $code));
    if (strlen($c) !== 8) return false;
    $list = json_decode((string)(forma_setting($pdo, 'tf_backup') ?? '[]'), true) ?: [];
    $h = hash('sha256', $c);
    foreach ($list as $i => $x) {
        if (hash_equals((string)$x, $h)) {
            unset($list[$i]);
            forma_set_setting($pdo, 'tf_backup', json_encode(array_values($list)));
            return true;
        }
    }
    return false;
}

function tf_trust_key(PDO $pdo): string
{
    $k = (string)(forma_setting($pdo, 'tf_trust') ?? '');
    if ($k === '') { $k = bin2hex(random_bytes(32)); forma_set_setting($pdo, 'tf_trust', $k); }
    return $k;
}

/** Ist dieses Gerät gemerkt? (Cookie mit Ablaufdatum, signiert; gilt nicht mehr nach Passwortänderung oder „alle vergessen“.) */
function tf_trusted(PDO $pdo): bool
{
    $c = (string)($_COOKIE[TF_COOKIE] ?? '');
    if (!preg_match('/^(\d+)\.([0-9a-f]{64})$/', $c, $m)) return false;
    if ((int)$m[1] < time()) return false;
    $sig = hash_hmac('sha256', $m[1] . '|' . (string)(forma_setting($pdo, 'admin_ver') ?? '0'), tf_trust_key($pdo));
    return hash_equals($sig, $m[2]);
}

function tf_trust_set(PDO $pdo, bool $secure): void
{
    $exp = time() + TF_TRUST_DAYS * 86400;
    $sig = hash_hmac('sha256', $exp . '|' . (string)(forma_setting($pdo, 'admin_ver') ?? '0'), tf_trust_key($pdo));
    setcookie(TF_COOKIE, $exp . '.' . $sig, ['expires' => $exp, 'path' => '/verwaltung/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
}

function tf_forget_devices(PDO $pdo): void
{
    forma_set_setting($pdo, 'tf_trust', bin2hex(random_bytes(32)));
}

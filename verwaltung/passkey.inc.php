<?php
// Anmeldung mit Passkey / Face ID für die Verwaltung (Vivien, 31.10.2026: „mein komplettes Business ist in dieser Verwaltung“).
// WebAuthn ohne Fremdbibliothek: Der Browser liefert den öffentlichen Schlüssel als SPKI (getPublicKey), geprüft wird mit openssl.
// Ein Passkey mit Face ID / Touch ID / Geräte-Code (userVerification = required) zählt als zwei Stufen: Gerät + Gesicht/Code.
// Setting `pk_list` = JSON [{id, rp, pem, alg, count, name, at, last}], `pk_user` = zufällige Nutzer-ID.
declare(strict_types=1);

function pk_b64u(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function pk_unb64u(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true); }

/** rpId = Domain ohne „www.“, damit Passkeys auf forma-pottery.com und www.forma-pottery.com gelten. */
function pk_rp(): string
{
    $h = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
    return str_starts_with($h, 'www.') ? substr($h, 4) : $h;
}

function pk_origin_ok(string $origin): bool
{
    $rp = pk_rp();
    $p = parse_url($origin);
    if (!$p || empty($p['host'])) return false;
    $host = strtolower($p['host']);
    $local = in_array($rp, ['localhost', '127.0.0.1'], true);
    if (($p['scheme'] ?? '') !== 'https' && !$local) return false;
    return $host === $rp || $host === 'www.' . $rp;
}

function pk_list(PDO $pdo): array
{
    return json_decode((string)(forma_setting($pdo, 'pk_list') ?? '[]'), true) ?: [];
}

function pk_save(PDO $pdo, array $list): void
{
    forma_set_setting($pdo, 'pk_list', json_encode(array_values($list)));
}

/** Passkeys, die auf dieser Domain gelten */
function pk_here(PDO $pdo): array
{
    return array_values(array_filter(pk_list($pdo), fn($k) => ($k['rp'] ?? '') === pk_rp()));
}

function pk_user_id(PDO $pdo): string
{
    $u = (string)(forma_setting($pdo, 'pk_user') ?? '');
    if ($u === '') { $u = pk_b64u(random_bytes(16)); forma_set_setting($pdo, 'pk_user', $u); }
    return $u;
}

function pk_challenge(string $kind): string
{
    $c = random_bytes(32);
    $_SESSION['pk_' . $kind] = ['c' => pk_b64u($c), 'at' => time()];
    return pk_b64u($c);
}

/** Prüft clientDataJSON (Art, Challenge aus der Sitzung, Herkunft). Challenge gilt nur einmal und 5 Minuten. */
function pk_client_ok(string $cdj, string $type, string $kind): bool
{
    $ch = $_SESSION['pk_' . $kind] ?? null;
    unset($_SESSION['pk_' . $kind]);
    if (!$ch || time() - (int)$ch['at'] > 300) return false;
    $d = json_decode($cdj, true);
    if (!is_array($d) || ($d['type'] ?? '') !== $type) return false;
    if (!hash_equals((string)$ch['c'], (string)($d['challenge'] ?? ''))) return false;
    return pk_origin_ok((string)($d['origin'] ?? ''));
}

/** authenticatorData: richtige Domain, Nutzerin anwesend (UP) und bestätigt mit Face ID/Code (UV). */
function pk_auth_ok(string $ad, bool $needAttested = false): bool
{
    if (strlen($ad) < 37) return false;
    if (!hash_equals(hash('sha256', pk_rp(), true), substr($ad, 0, 32))) return false;
    $f = ord($ad[32]);
    if (!($f & 0x01) || !($f & 0x04)) return false;
    if ($needAttested && !($f & 0x40)) return false;
    return true;
}

function pk_pem(string $der): string
{
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function pk_register_options(PDO $pdo): array
{
    return [
        'challenge' => pk_challenge('reg'),
        'rp' => ['id' => pk_rp(), 'name' => 'FORMA Verwaltung'],
        'user' => ['id' => pk_user_id($pdo), 'name' => 'Vivien · FORMA Verwaltung', 'displayName' => 'FORMA Verwaltung'],
        'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
        'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'],
        'attestation' => 'none',
        'timeout' => 120000,
        'exclude' => array_map(fn($k) => $k['id'], pk_here($pdo)),
    ];
}

/** Neuen Passkey speichern. Gibt '' bei Erfolg zurück, sonst eine Meldung für Vivien. */
function pk_register(PDO $pdo, array $in): string
{
    $id = (string)($in['id'] ?? '');
    $cdj = pk_unb64u((string)($in['cdj'] ?? ''));
    $ad = pk_unb64u((string)($in['ad'] ?? ''));
    $der = pk_unb64u((string)($in['pk'] ?? ''));
    $alg = (int)($in['alg'] ?? 0);
    if ($id === '' || $der === '' || !in_array($alg, [-7, -257], true)) return 'Dieser Browser kann leider keinen Passkey für die Verwaltung anlegen. Bitte nimm Safari oder Chrome in der neuesten Version.';
    if (!pk_client_ok($cdj, 'webauthn.create', 'reg') || !pk_auth_ok($ad, true)) return 'Das hat nicht geklappt. Bitte versuch es noch einmal.';
    // Die Schlüssel-ID im authenticatorData muss die gemeldete sein
    $len = unpack('n', substr($ad, 53, 2))[1] ?? 0;
    if (!hash_equals(pk_unb64u($id), substr($ad, 55, $len))) return 'Das hat nicht geklappt. Bitte versuch es noch einmal.';
    $pem = pk_pem($der);
    $key = openssl_pkey_get_public($pem);
    if (!$key) return 'Das hat nicht geklappt. Bitte versuch es noch einmal.';
    // Der öffentliche Schlüssel muss im authenticatorData stecken (bindet ihn an das Gerät)
    $det = openssl_pkey_get_details($key);
    $parts = $alg === -7 ? [$det['ec']['x'] ?? '', $det['ec']['y'] ?? ''] : [ltrim($det['rsa']['n'] ?? '', "\0")];
    foreach ($parts as $p) if ($p === '' || !str_contains(substr($ad, 55 + $len), $p)) return 'Das hat nicht geklappt. Bitte versuch es noch einmal.';
    $list = pk_list($pdo);
    foreach ($list as $k) if ($k['id'] === $id) return 'Dieser Passkey ist schon gespeichert.';
    $name = trim(mb_substr(strip_tags((string)($in['name'] ?? '')), 0, 40)) ?: 'Mein Gerät';
    $list[] = ['id' => $id, 'rp' => pk_rp(), 'pem' => $pem, 'alg' => $alg, 'count' => unpack('N', substr($ad, 33, 4))[1], 'name' => $name, 'at' => time(), 'last' => 0];
    pk_save($pdo, $list);
    return '';
}

function pk_login_options(): array
{
    return ['challenge' => pk_challenge('login'), 'rpId' => pk_rp(), 'userVerification' => 'required', 'timeout' => 120000];
}

/** Anmeldung prüfen. true = Passkey gültig (mit Face ID/Code bestätigt). */
function pk_login(PDO $pdo, array $in): bool
{
    $id = (string)($in['id'] ?? '');
    $cdjRaw = pk_unb64u((string)($in['cdj'] ?? ''));
    $ad = pk_unb64u((string)($in['ad'] ?? ''));
    $sig = pk_unb64u((string)($in['sig'] ?? ''));
    if (!pk_client_ok($cdjRaw, 'webauthn.get', 'login') || !pk_auth_ok($ad) || $sig === '') return false;
    $list = pk_list($pdo);
    foreach ($list as $i => $k) {
        if (!hash_equals((string)$k['id'], $id) || ($k['rp'] ?? '') !== pk_rp()) continue;
        if (openssl_verify($ad . hash('sha256', $cdjRaw, true), $sig, (string)$k['pem'], OPENSSL_ALGO_SHA256) !== 1) return false;
        $cnt = unpack('N', substr($ad, 33, 4))[1];
        // Zähler darf nicht zurückgehen (kopierter Schlüssel); Apple/Google-Passkeys senden 0
        if ($cnt !== 0 && (int)$k['count'] !== 0 && $cnt <= (int)$k['count']) return false;
        $list[$i]['count'] = $cnt;
        $list[$i]['last'] = time();
        pk_save($pdo, $list);
        return true;
    }
    return false;
}

function pk_remove(PDO $pdo, string $id): void
{
    pk_save($pdo, array_filter(pk_list($pdo), fn($k) => $k['id'] !== $id));
}

/** Gemeinsames Skript (Base64url-Umwandlung, Anlegen, Anmelden) */
function pk_js(): string
{
    return <<<'JS'
<script>
window.PK=(function(){
  function b(s){s=s.replace(/-/g,'+').replace(/_/g,'/');while(s.length%4)s+='=';var r=atob(s),a=new Uint8Array(r.length);for(var i=0;i<r.length;i++)a[i]=r.charCodeAt(i);return a.buffer;}
  function u(buf){var a=new Uint8Array(buf),s='';for(var i=0;i<a.length;i++)s+=String.fromCharCode(a[i]);return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');}
  function post(data){var f=new FormData();for(var k in data)f.append(k,data[k]);return fetch(location.pathname,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();});}
  function ok(){return !!(window.PublicKeyCredential&&navigator.credentials);}
  function name(){var ua=navigator.userAgent;return /iPhone/.test(ua)?'iPhone':/iPad/.test(ua)?'iPad':/Android/.test(ua)?'Android-Handy':/Mac/.test(ua)?'MacBook':/Windows/.test(ua)?'Windows-Computer':'Mein Gerät';}
  function add(csrf,msg,nm){
    if(!ok()){msg('Dieser Browser kann keine Passkeys.');return;}
    post({csrf:csrf,action:'pk_reg_opts'}).then(function(o){
      return navigator.credentials.create({publicKey:{challenge:b(o.challenge),rp:o.rp,user:{id:b(o.user.id),name:o.user.name,displayName:o.user.displayName},pubKeyCredParams:o.pubKeyCredParams,authenticatorSelection:o.authenticatorSelection,attestation:'none',timeout:o.timeout,excludeCredentials:(o.exclude||[]).map(function(i){return{type:'public-key',id:b(i)};})}});
    }).then(function(c){
      var r=c.response;if(!r.getPublicKey||!r.getAuthenticatorData){throw new Error('alt');}
      var n=(nm||'').trim();
      return post({csrf:csrf,action:'pk_reg_done',id:u(c.rawId),cdj:u(r.clientDataJSON),ad:u(r.getAuthenticatorData()),pk:u(r.getPublicKey()),alg:r.getPublicKeyAlgorithm(),name:n||name()});
    }).then(function(j){if(j.ok)location.reload();else msg(j.msg);}).catch(function(e){msg(e&&e.message==='alt'?'Dieser Browser ist zu alt für Passkeys. Bitte Safari oder Chrome aktualisieren.':'Abgebrochen oder nicht geklappt. Du kannst es einfach noch einmal versuchen.');});
  }
  function login(csrf,msg){
    if(!ok()){msg('Dieser Browser kann keine Passkeys. Melde dich mit dem Passwort an.');return;}
    post({csrf:csrf,action:'pk_login_opts'}).then(function(o){
      return navigator.credentials.get({publicKey:{challenge:b(o.challenge),rpId:o.rpId,userVerification:'required',timeout:o.timeout,allowCredentials:[]}});
    }).then(function(c){var r=c.response;
      return post({csrf:csrf,action:'pk_login_done',id:u(c.rawId),cdj:u(r.clientDataJSON),ad:u(r.authenticatorData),sig:u(r.signature)});
    }).then(function(j){if(j.ok)location.href=j.go;else msg(j.msg);}).catch(function(){msg('Abgebrochen oder nicht geklappt. Versuch es noch einmal oder nimm dein Passwort.');});
  }
  return {add:add,login:login,ok:ok};
})();
</script>
JS;
}

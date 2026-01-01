<?php
// htdocs/api/tools/decode_and_verify_token.php
// Temporary debug endpoint to decode a token and verify its HMAC signature with a secret.
// Usage:
//  - Open in browser: /api/tools/decode_and_verify_token.php?token=...&secret=...
//  - Or POST token and optional secret.
// Remove this file after debugging.

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) $data .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($data, '-_', '+/'));
}

$token = $_REQUEST['token'] ?? null;
$provided_secret = $_REQUEST['secret'] ?? null;

if (!$token) {
    echo json_encode(['ok'=>false,'error'=>'token_required','usage'=>'GET/POST token=... [&secret=...]'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}

// Determine secret: prefer provided, else env, else common fallback (show warning)
$env_secret = getenv('TOKEN_SECRET') ?: null;
$secret = $provided_secret ?: $env_secret ?: null;

$parts = explode('.', $token, 2);
if (count($parts) !== 2) {
    echo json_encode(['ok'=>false,'error'=>'invalid_token_format','token'=>$token], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}
list($payload_b64, $sig_b64) = $parts;
$payload_json = base64url_decode($payload_b64);
$payload = json_decode($payload_json, true);

$result = [
    'ok' => true,
    'token' => $token,
    'payload_b64' => $payload_b64,
    'signature_b64_in_token' => $sig_b64,
    'payload_json' => $payload_json,
    'payload' => $payload,
    'secret_source' => $provided_secret ? 'provided' : ($env_secret ? 'env:TOKEN_SECRET' : 'none_provided')
];

// Compute expected signature for candidate secrets
$secrets_to_test = [];
if ($provided_secret) $secrets_to_test[] = $provided_secret;
if ($env_secret && $env_secret !== $provided_secret) $secrets_to_test[] = $env_secret;
// common fallback candidates you might have used by accident
$secrets_to_test[] = 'temporary_change_this_to_a_strong_secret';
$secrets_to_test[] = 'replace_this_with_a_long_random_secret_change_me';
$secrets_to_test[] = 'testsecret';
$secrets_to_test = array_values(array_unique(array_filter($secrets_to_test)));

$checked = [];
foreach ($secrets_to_test as $s) {
    $raw = hash_hmac('sha256', $payload_b64, $s, true);
    $expected_b64 = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    $match = function_exists('hash_equals') ? hash_equals($expected_b64, $sig_b64) : ($expected_b64 === $sig_b64);
    $checked[] = [
        'secret_snippet' => substr($s, 0, 8) . '...' . (strlen($s) > 8 ? '' : ''),
        'expected_signature_b64' => $expected_b64,
        'matches' => $match
    ];
    if ($match) $result['matching_secret_snippet'] = substr($s,0,12) . '...';
}
$result['checked'] = $checked;

if (!isset($result['matching_secret_snippet'])) {
    $result['warning'] = 'No matching secret found among tested candidates. Ensure the service that generated the token and the verifier use the same TOKEN_SECRET.';
    if (!$secret) $result['note'] = 'No secret provided and no env TOKEN_SECRET found on this process.';
}

echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
exit;
?>
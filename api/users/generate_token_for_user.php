<?php
// htdocs/api/users/generate_code_for_user.php
// Generate a short numeric code (e.g. 6 digits), store its hash in verification_tokens,
// and return the code and jti in JSON (for testing). In production you may avoid returning the code.
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/db.php';

$mysqli = @connectDB();
if (!$mysqli) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_connect_failed']); exit; }

// read inputs
$raw = @file_get_contents('php://input');
$input = $_REQUEST;
if ($raw) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct,'application/json') !== false) {
        $j = @json_decode($raw, true);
        if (is_array($j)) $input = array_merge($input, $j);
    } else {
        parse_str($raw, $parsed);
        if (is_array($parsed)) $input = array_merge($input, $parsed);
    }
}

$user_id = isset($input['user_id']) && $input['user_id'] !== '' ? (int)$input['user_id'] : null;
$phone = isset($input['phone']) && $input['phone'] !== '' ? trim((string)$input['phone']) : null;
$ttl = isset($input['ttl']) ? max(60,(int)$input['ttl']) : 900; // seconds
$origin = isset($input['origin']) ? trim((string)$input['origin']) : 'manual-code';

// require phone or user_id
if (!$user_id && !$phone) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'user_id أو phone مطلوب.','code'=>null]);
    exit;
}

// if phone provided but not user_id, try find user
if (!$user_id && $phone) {
    $s = $mysqli->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
    if ($s) {
        $s->bind_param('s', $phone);
        @$s->execute();
        $r = $s->get_result();
        if ($row = $r->fetch_assoc()) $user_id = (int)$row['id'];
        $s->close();
    }
    if (!$user_id) {
        // create user minimal? For generator we can require existing or return error.
        // Here we return error; higher-level register_and_send will create user.
        http_response_code(404);
        echo json_encode(['ok'=>false,'message'=>'user_not_found_for_phone','code'=>null]);
        exit;
    }
}

// generate numeric code (6 digits)
try {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
} catch (Throwable $e) {
    // fallback
    $code = (string)mt_rand(100000,999999);
}

$jti = bin2hex(random_bytes(12));
$iat = time();
$exp = $iat + $ttl;
$issued_at_dt = date('Y-m-d H:i:s', $iat);
$expires_at_dt = date('Y-m-d H:i:s', $exp);
$token_hash = password_hash($code, PASSWORD_DEFAULT);

$ip_raw = $_SERVER['REMOTE_ADDR'] ?? null;
$ip_bin = null;
if ($ip_raw) {
    $packed = @inet_pton($ip_raw);
    if ($packed !== false) $ip_bin = $packed;
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

// Insert row into verification_tokens
try {
    $stmt = $mysqli->prepare("
        INSERT INTO verification_tokens
          (jti, user_id, channel, token_hash, expires_at, used, attempts, ip, created_at, phone, issued_at, origin, issuer_user_agent, issuer_ip)
        VALUES (?, ?, 'code', ?, ?, 0, 0, ?, NOW(), ?, ?, ?, ?, ?)
    ");
    if (!$stmt) throw new RuntimeException('prepare_failed: ' . ($mysqli->error ?? ''));

    // bind params
    // order: jti(s), user_id(i), token_hash(s), expires_at(s), ip(bin or null), phone(s), issued_at(s), origin(s), ua(s), issuer_ip(s)
    $params = [
        $jti,
        $user_id,
        $token_hash,
        $expires_at_dt,
        $ip_bin,
        $phone,
        $issued_at_dt,
        $origin,
        $ua,
        $ip_raw
    ];
    $types = '';
    foreach ($params as $p) { $types .= is_int($p) ? 'i' : 's'; }
    $bind_names = [];
    $bind_names[] = $types;
    for ($i=0;$i<count($params);$i++) $bind_names[] = &$params[$i];
    if (!call_user_func_array([$stmt,'bind_param'],$bind_names)) {
        throw new RuntimeException('bind_param_failed: ' . ($stmt->error ?? ''));
    }
    if (!$stmt->execute()) throw new RuntimeException('execute_failed: ' . ($stmt->error ?? ''));
    $insert_id = $stmt->insert_id;
    $stmt->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'db_insert_failed','details'=>$e->getMessage()]);
    exit;
}

// return code (for testing); in prod you may omit the 'code' field and rely on WhatsApp only
echo json_encode([
    'ok' => true,
    'method' => 'code',
    'code' => $code,
    'jti' => $jti,
    'id' => $insert_id,
    'expires_at' => $expires_at_dt
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
exit;
?>
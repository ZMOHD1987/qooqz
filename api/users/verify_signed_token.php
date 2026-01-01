<?php
// htdocs/api/users/verify_signed_token.php
// Robust endpoint to verify a signed token (supports GET, POST form, JSON body, Authorization Bearer).
// - If decode_only=1 -> verify signature/expiry and return payload (incl. code) WITHOUT marking token used.
// - Otherwise -> verify, mark verification_tokens.used and activate users.is_active = 1.
//
// Writes helpful debug lines to logs/verify_signed_token_debug.log when token is missing or verification fails
// (remove/disable logs in production).

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$debugLog = $logDir . '/verify_signed_token_debug.log';

// small logger
function log_debug($obj) {
    global $debugLog;
    $line = '[' . date('c') . '] ' . json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($debugLog, $line, FILE_APPEND | LOCK_EX);
}

// read raw body + merge GET/POST/JSON
$raw = @file_get_contents('php://input');
$input = [];
if (!empty($_GET)) $input = array_merge($input, $_GET);
if (!empty($_POST)) $input = array_merge($input, $_POST);

$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if ($raw) {
    if (stripos($ct, 'application/json') !== false) {
        $j = @json_decode($raw, true);
        if (is_array($j)) $input = array_merge($input, $j);
    } else {
        parse_str($raw, $parsed);
        if (is_array($parsed)) $input = array_merge($input, $parsed);
    }
}

// also accept Authorization: Bearer <token>
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!$authHeader && function_exists('getallheaders')) {
    $h = getallheaders();
    if (!empty($h['Authorization'])) $authHeader = $h['Authorization'];
    if (!$authHeader && !empty($h['authorization'])) $authHeader = $h['authorization'];
}
if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
    if (empty($input['token'])) $input['token'] = $m[1];
}

// extract values
$token = isset($input['token']) ? trim((string)$input['token']) : '';
$decode_only = isset($input['decode_only']) && ($input['decode_only'] === 1 || strtolower((string)$input['decode_only']) === 'true' || (string)$input['decode_only'] === '1');

// if token absent -> helpful debug + error
if (!$token) {
    // Log a short snapshot for debugging (do NOT log any sensitive bodies in production)
    log_debug([
        'event' => 'token_missing',
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'query_keys' => array_keys($_GET ?? []),
        'post_keys' => array_keys($_POST ?? []),
        'raw_length' => is_string($raw) ? strlen($raw) : 0,
        'remote' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'hint' => 'Send token as GET ?token=.. or POST/JSON body {"token":"..."} or Authorization: Bearer <token>'
    ]);
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'token required','error_code'=>1009], JSON_UNESCAPED_UNICODE);
    exit;
}

// load dependencies
require_once __DIR__ . '/../config/db.php';
$tokenServiceLoaded = false;
$verifyFn = null;
$ts_get_secret_exists = false;
if (file_exists(__DIR__ . '/../lib/token_service.php')) {
    require_once __DIR__ . '/../lib/token_service.php';
    // check available verify function names used in different setups
    if (function_exists('verify_signed_token')) { $verifyFn = 'verify_signed_token'; $tokenServiceLoaded = true; }
    elseif (function_exists('verify_and_decode_signed_token')) { $verifyFn = 'verify_and_decode_signed_token'; $tokenServiceLoaded = true; }
    elseif (function_exists('ts_verify_token')) { $verifyFn = 'ts_verify_token'; $tokenServiceLoaded = true; }
    if (function_exists('ts_get_secret')) $ts_get_secret_exists = true;
}

// If no token service present, log and error
if (!$tokenServiceLoaded) {
    log_debug(['event'=>'token_service_missing','note'=>'lib/token_service.php not found or no verify function']);
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'token service missing','error_code'=>1019], JSON_UNESCAPED_UNICODE);
    exit;
}

// perform token verification via available function and secret (if supported)
try {
    $secret = $ts_get_secret_exists ? ts_get_secret() : null;
    if ($verifyFn === 'verify_signed_token') {
        $payload = verify_signed_token($token, $secret ?? '');
    } elseif ($verifyFn === 'verify_and_decode_signed_token') {
        $payload = verify_and_decode_signed_token($token, $secret ?? '');
    } elseif ($verifyFn === 'ts_verify_token') {
        $payload = ts_verify_token($token, $secret ?? '');
    } else {
        throw new RuntimeException('no_verify_fn_available');
    }
    if (!is_array($payload)) throw new RuntimeException('invalid_payload_returned');
} catch (Throwable $e) {
    // log error for debugging (do not leak secret or token)
    log_debug(['event'=>'token_verify_failed','err'=>$e->getMessage(),'token_snippet'=>substr($token,0,16)]);
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'invalid_token','details'=>$e->getMessage(),'error_code'=>1010], JSON_UNESCAPED_UNICODE);
    exit;
}

// payload should include user_id, jti and optionally code
// Normalize payload keys
$user_id = isset($payload['user_id']) ? $payload['user_id'] : null;
$jti = isset($payload['jti']) ? $payload['jti'] : null;
$code_in_payload = isset($payload['code']) ? (string)$payload['code'] : (isset($payload['otp']) ? (string)$payload['otp'] : '');

// If decode_only -> return payload and (if present) code
if ($decode_only) {
    $resp = [
        'success' => true,
        'message' => 'decoded',
        'payload' => $payload,
        'user_id' => $user_id,
        'jti' => $jti,
        'code' => $code_in_payload
    ];
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}

// Otherwise perform activation: locate verification_tokens by jti, check used/expired, mark used + activate user
$mysqli = @connectDB();
if (!$mysqli) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'db_connect_failed','error_code'=>1020], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!$jti) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'missing_jti_in_token','error_code'=>1011], JSON_UNESCAPED_UNICODE); exit; }

    $stmt = $mysqli->prepare("SELECT id,user_id,used,expires_at,token_hash FROM verification_tokens WHERE jti = ? LIMIT 1");
    if (!$stmt) throw new RuntimeException('db_prepare_failed: ' . $mysqli->error);
    $stmt->bind_param('s', $jti);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'token_row_not_found','error_code'=>1012], JSON_UNESCAPED_UNICODE); exit; }
    if (!empty($row['used'])) { http_response_code(409); echo json_encode(['success'=>false,'message'=>'already_used','error_code'=>1013], JSON_UNESCAPED_UNICODE); exit; }
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) { http_response_code(410); echo json_encode(['success'=>false,'message'=>'expired','error_code'=>1014], JSON_UNESCAPED_UNICODE); exit; }

    // If token_hash present, verify token matches stored hash (optional but recommended)
    if (!empty($row['token_hash'])) {
        if (!password_verify($token, $row['token_hash'])) {
            http_response_code(401);
            echo json_encode(['success'=>false,'message'=>'token_mismatch','error_code'=>1015], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Mark used and activate user in transaction
    $mysqli->begin_transaction();

    $v_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $v_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $upd = $mysqli->prepare("UPDATE verification_tokens SET used = 1, used_at = NOW(), verifier_ip = ?, verifier_user_agent = ? WHERE id = ? LIMIT 1");
    if ($upd) { $upd->bind_param('ssi', $v_ip, $v_ua, $row['id']); $upd->execute(); $upd->close(); }

    $uid = (int)$row['user_id'];
    $u = $mysqli->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? LIMIT 1");
    if ($u) { $u->bind_param('i', $uid); $u->execute(); $u->close(); }

    $mysqli->commit();

    echo json_encode(['success'=>true,'message'=>'verified','user_id'=>$uid,'jti'=>$jti], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if ($mysqli) { try { $mysqli->rollback(); } catch (Throwable $_) {} }
    log_debug(['event'=>'activation_exception','err'=>$e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'exception','error'=>$e->getMessage(),'error_code'=>1021], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
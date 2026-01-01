<?php
// htdocs/api/users/verify_token.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function tlog($msg) { 
    global $logDir; 
    @file_put_contents($logDir . '/verify_token.log', date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX); 
}

function reply($payload, $http_status = 200) {
    http_response_code($http_status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

// Read input
$raw = @file_get_contents('php://input');
$input = json_decode($raw, true) ?: [];

tlog('Token verification request: ' . json_encode($input));

$token = isset($input['token']) ? trim($input['token']) : '';

if (empty($token)) {
    reply(['success'=>false, 'message'=>'token_required'], 400);
}

// Verify token using token service
$tokenServicePath = __DIR__ . '/../lib/token_service.php';
if (!file_exists($tokenServicePath)) {
    reply(['success'=>false, 'message'=>'token_service_unavailable'], 500);
}

require_once $tokenServicePath;

if (!function_exists('verify_signed_token')) {
    reply(['success'=>false, 'message'=>'token_verification_unavailable'], 500);
}

try {
    $secret = function_exists('ts_get_secret') ? ts_get_secret() : '';
    $payload = verify_signed_token($token, $secret);
    
    if (!$payload) {
        reply(['success'=>false, 'message'=>'invalid_token'], 401);
    }
    
    tlog('Token payload: ' . json_encode($payload));
    
    // Check expiration
    $now = time();
    if (isset($payload['exp']) && $payload['exp'] < $now) {
        reply(['success'=>false, 'message'=>'token_expired'], 410);
    }
    
    // Get data from payload
    $user_id = $payload['user_id'] ?? null;
    $jti = $payload['jti'] ?? null;
    $code = $payload['code'] ?? null;
    $session_token = $payload['session_token'] ?? null;
    
    if (!$user_id || !$jti || !$code) {
        reply(['success'=>false, 'message'=>'invalid_token_payload'], 400);
    }
    
    // Verify with database
    require_once __DIR__ . '/../../api/config/db.php';
    $mysqli = @connectDB();
    if (!$mysqli) {
        reply(['success'=>false, 'message'=>'db_connect_failed'], 500);
    }
    
    // Find token
    $stmt = $mysqli->prepare("SELECT id, user_id, token_hash, used, attempts, expires_at, note FROM verification_tokens WHERE jti = ? AND user_id = ? LIMIT 1");
    if (!$stmt) {
        tlog('Prepare failed: ' . $mysqli->error);
        reply(['success'=>false, 'message'=>'db_error'], 500);
    }
    
    $stmt->bind_param('si', $jti, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    
    if (!$row) {
        reply(['success'=>false, 'message'=>'token_not_found'], 404);
    }
    
    if (!empty($row['used'])) {
        reply(['success'=>false, 'message'=>'token_already_used'], 409);
    }
    
    // Check expiry
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
        reply(['success'=>false, 'message'=>'token_expired'], 410);
    }
    
    // SESSION VALIDATION
    $session_valid = false;
    $session_id = null;
    
    // Check if token has session information in note
    if (!empty($row['note'])) {
        $note_data = json_decode($row['note'], true);
        if (isset($note_data['session_token'])) {
            $stored_session_token = $note_data['session_token'];
            
            // If session_token from payload matches stored session_token
            if ($session_token && $session_token === $stored_session_token) {
                // Verify session is still valid
                $stmt_session = $mysqli->prepare("SELECT id FROM user_sessions WHERE token = ? AND user_id = ? AND revoked = 0 AND expires_at > NOW() LIMIT 1");
                $stmt_session->bind_param('si', $session_token, $user_id);
                $stmt_session->execute();
                $session_result = $stmt_session->get_result();
                $session_data = $session_result ? $session_result->fetch_assoc() : null;
                $stmt_session->close();
                
                if ($session_data) {
                    $session_valid = true;
                    $session_id = $session_data['id'];
                    tlog("Session validated: session_id=" . $session_id);
                } else {
                    tlog("Session invalid or expired: token=" . $session_token);
                }
            } else {
                tlog("Session token mismatch: payload=" . ($session_token ?: 'empty') . " stored=" . $stored_session_token);
            }
        }
    }
    
    // If token has session info but session validation failed
    if (!empty($row['note']) && !$session_valid) {
        reply(['success'=>false, 'message'=>'invalid_session_or_wrong_browser','details'=>'هذا الرابط يعمل فقط في نفس المتصفح الذي تم التسجيل منه'], 403);
    }
    
    // Verify code
    if (!password_verify($code, $row['token_hash'])) {
        // Increment attempts
        $attempts = (int)$row['attempts'] + 1;
        $upd = $mysqli->prepare("UPDATE verification_tokens SET attempts = ? WHERE id = ?");
        $upd->bind_param('ii', $attempts, $row['id']);
        $upd->execute();
        $upd->close();
        
        if ($attempts >= 5) {
            $blk = $mysqli->prepare("UPDATE verification_tokens SET used = 1 WHERE id = ?");
            $blk->bind_param('i', $row['id']);
            $blk->execute();
            $blk->close();
            tlog("Token blocked due to too many attempts: id=" . $row['id']);
        }
        
        reply(['success'=>false, 'message'=>'invalid_code'], 401);
    }
    
    // Mark token as used and activate user
    $mysqli->begin_transaction();
    
    $upd = $mysqli->prepare("UPDATE verification_tokens SET used = 1, used_at = NOW(), verifier_ip = ?, verifier_user_agent = ? WHERE id = ?");
    $v_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $v_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $upd->bind_param('ssi', $v_ip, $v_ua, $row['id']);
    $upd->execute();
    $upd->close();
    
    // Update user activation
    $u = $mysqli->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ?");
    $u->bind_param('i', $row['user_id']);
    $u->execute();
    $u->close();
    
    // If session is valid, update session expiration (extend it by 30 days)
    if ($session_valid && $session_id) {
        $new_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $upd_session = $mysqli->prepare("UPDATE user_sessions SET expires_at = ? WHERE id = ?");
        $upd_session->bind_param('si', $new_expires, $session_id);
        $upd_session->execute();
        $upd_session->close();
        tlog("Session expiration extended: session_id=" . $session_id);
    }
    
    $mysqli->commit();
    
    tlog("User {$row['user_id']} activated via token {$jti}, session_valid=" . ($session_valid ? 'yes' : 'no'));
    
    $response = [
        'success' => true,
        'message' => 'account_activated',
        'user_id' => (int)$row['user_id'],
        'session_validated' => $session_valid
    ];
    
    if ($session_valid) {
        $response['session_message'] = 'تم التفعيل في الجلسة الصحيحة';
    } else {
        $response['session_message'] = 'تم التفعيل بدون تحقق الجلسة';
    }
    
    reply($response);
    
} catch (Throwable $e) {
    tlog('Exception: ' . $e->getMessage());
    reply(['success'=>false, 'message'=>'verification_failed', 'details'=>$e->getMessage()], 500);
}
?>
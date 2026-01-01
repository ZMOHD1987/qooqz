<?php
// htdocs/api/users/send_verification_code.php
// إنشاء رابط التحقق وربطه بالجلسة (بدون إرسال فعلي عبر واتساب)
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function slog($msg) { 
    global $logDir; 
    @file_put_contents($logDir . '/send_verification_code.log', date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX); 
}

function reply($payload, $http_status = 200) {
    http_response_code($http_status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

// Read input
$raw = @file_get_contents('php://input');
$input = $_POST;
if ($raw && empty($input)) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $j = @json_decode($raw, true);
        if (is_array($j)) $input = $j;
    } else {
        parse_str($raw, $parsed);
        if (is_array($parsed)) $input = array_merge($input, $parsed);
    }
}

slog('request_input: ' . json_encode($input, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

// Validate
$user_id = isset($input['user_id']) && $input['user_id'] !== '' ? (int)$input['user_id'] : null;
$channel = isset($input['channel']) && $input['channel'] !== '' ? trim((string)$input['channel']) : 'whatsapp';
$ttl = isset($input['ttl']) && is_numeric($input['ttl']) ? max(60, (int)$input['ttl']) : 900;
$session_token = isset($input['session_token']) ? trim((string)$input['session_token']) : null;

if (!$user_id) {
    reply(['ok'=>false, 'error'=>'user_id_required'], 400);
}

// Validate session token if provided
if ($session_token && strlen($session_token) !== 64) {
    reply(['ok'=>false, 'error'=>'invalid_session_token'], 400);
}

require_once __DIR__ . '/../../api/config/db.php';
$mysqli = @connectDB();
if (!$mysqli) {
    reply(['ok'=>false,'error'=>'db_connect_failed'], 500);
}

try {
    // Get user info
    $stmt = $mysqli->prepare("SELECT id, username, email, phone, is_active FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        slog('prepare_failed: ' . $mysqli->error);
        reply(['ok'=>false,'error'=>'db_prepare_failed'], 500);
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    
    if (!$user) {
        slog('user_not_found: ' . $user_id);
        reply(['ok'=>false,'error'=>'user_not_found'], 404);
    }
    
    slog('Processing user: ' . json_encode($user));
    
    // تحقق من صحة الجلسة إذا وجدت
    $session_id = null;
    if ($session_token) {
        $stmt_session = $mysqli->prepare("SELECT id FROM user_sessions WHERE token = ? AND user_id = ? AND revoked = 0 AND expires_at > NOW() LIMIT 1");
        $stmt_session->bind_param('si', $session_token, $user_id);
        $stmt_session->execute();
        $session_result = $stmt_session->get_result();
        $session_data = $session_result ? $session_result->fetch_assoc() : null;
        $stmt_session->close();
        
        if (!$session_data) {
            slog('invalid_session: token=' . $session_token . ' user_id=' . $user_id);
            reply(['ok'=>false,'error'=>'invalid_or_expired_session','message'=>'الجلسة غير صالحة أو منتهية'], 401);
        }
        
        $session_id = $session_data['id'];
        slog('Valid session found: session_id=' . $session_id);
    }
    
    // Mark old tokens as used
    $update = $mysqli->prepare("UPDATE verification_tokens SET used = 1 WHERE user_id = ? AND used = 0 AND channel = ?");
    if ($update) {
        $update->bind_param('is', $user_id, $channel);
        $update->execute();
        $update->close();
    }
    
    // Generate new code
    try { 
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); 
    } catch (Throwable $e) { 
        $code = (string)mt_rand(100000, 999999); 
    }
    
    $jti = bin2hex(random_bytes(12));
    $iat = time();
    $exp = $iat + $ttl;
    $issued_at = gmdate('Y-m-d H:i:s', $iat);
    $expires_at = gmdate('Y-m-d H:i:s', $exp);
    
    // Get timezone
    $user_timezone = 'UTC';
    $tz_stmt = $mysqli->prepare("SELECT timezone FROM users WHERE id = ? LIMIT 1");
    if ($tz_stmt) {
        $tz_stmt->bind_param('i', $user_id);
        $tz_stmt->execute();
        $tz_res = $tz_stmt->get_result();
        if ($tz_row = $tz_res->fetch_assoc() && !empty($tz_row['timezone'])) {
            $user_timezone = $tz_row['timezone'];
        }
        $tz_stmt->close();
    }
    
    // Compute local expiry
    try {
        $dtLocal = new DateTime('@' . $exp);
        $dtLocal->setTimezone(new DateTimeZone($user_timezone));
        $expires_at_local = $dtLocal->format('Y-m-d H:i:s');
    } catch (Throwable $_) {
        $expires_at_local = $expires_at;
    }
    
    // Hash code
    $token_hash = password_hash($code, PASSWORD_DEFAULT);
    
    // Insert verification token with session reference
    $stmt = $mysqli->prepare("
        INSERT INTO verification_tokens 
        (jti, user_id, channel, token_hash, expires_at, expires_at_local, user_tz, ip, phone, issued_at, origin, issuer_user_agent, issuer_ip) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        slog('prepare_failed for insert: ' . $mysqli->error);
        reply(['ok'=>false,'error'=>'db_prepare_failed','details'=>$mysqli->error], 500);
    }
    
    $origin = $session_id ? 'session_linked' : 'manual_resend';
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $issuer_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $phone = $user['phone'] ?? null;
    
    $stmt->bind_param(
        'sisssssssssss',
        $jti,
        $user_id,
        $channel,
        $token_hash,
        $expires_at,
        $expires_at_local,
        $user_timezone,
        $ip,
        $phone,
        $issued_at,
        $origin,
        $ua,
        $issuer_ip
    );
    
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        slog('insert_failed: ' . $err);
        reply(['ok'=>false,'error'=>'insert_failed','details'=>$err], 500);
    }
    
    $token_id = $stmt->insert_id;
    $stmt->close();
    
    // إذا كان هناك جلسة، نقوم بتخزين معلومات الجلسة في حقل note
    if ($session_id) {
        $note_data = ['session_token' => $session_token, 'session_id' => $session_id];
        $note_json = json_encode($note_data);
        
        $update_note = $mysqli->prepare("UPDATE verification_tokens SET note = ? WHERE id = ?");
        $update_note->bind_param('si', $note_json, $token_id);
        $update_note->execute();
        $update_note->close();
        
        slog("Verification token $token_id linked to session $session_id");
    }
    
    slog("New verification token created: id=$token_id, user_id=$user_id, session_linked=" . ($session_id ? 'yes' : 'no'));
    
    // Generate verification link
    $link = '';
    $tokenServicePath = __DIR__ . '/../lib/token_service.php';
    if (file_exists($tokenServicePath)) {
        require_once $tokenServicePath;
        if (function_exists('generate_signed_token')) {
            $payload = [
                'user_id' => $user_id, 
                'jti' => $jti, 
                'iat' => $iat, 
                'exp' => $exp, 
                'code' => $code,
                'session_token' => $session_token // تضمين session token في التوكن الموقع
            ];
            try {
                $secret = function_exists('ts_get_secret') ? ts_get_secret() : null;
                $token = generate_signed_token($payload, $secret ?? '');
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $link = $scheme . '://' . $host . '/verify_token.html?token=' . rawurlencode($token);
            } catch (Throwable $e) {
                slog('token_generation_failed: ' . $e->getMessage());
            }
        }
    }
    
    if (empty($link)) {
        slog('cannot_generate_verification_link');
        reply(['ok'=>false,'error'=>'cannot_generate_verification_link','message'=>'لا يمكن إنشاء رابط التحقق حاليًا. حاول مرة أخرى.'], 500);
    }
    
    // Prepare WhatsApp message for manual sending
    $whatsapp_message = "مرحباً " . ($user['username'] ?? '') . "،\n\n";
    $whatsapp_message .= "رابط تفعيل حسابك:\n";
    $whatsapp_message .= $link . "\n\n";
    $whatsapp_message .= "صالح حتى: " . $expires_at_local . "\n\n";
    $whatsapp_message .= "ملاحظة: هذا الرابط يعمل فقط في نفس المتصفح الذي سجلت فيه.";
    
    // Prepare response
    $response = [
        'ok' => true,
        'success' => true,
        'user_id' => $user_id,
        'channel' => $channel,
        'expires_at' => $expires_at,
        'expires_at_local' => $expires_at_local,
        'user_tz' => $user_timezone,
        'message' => 'تم إنشاء رابط التفعيل بنجاح',
        'link' => $link,
        'phone' => $phone,
        'session_linked' => ($session_id ? true : false),
        'whatsapp_message' => $whatsapp_message,
        'instructions' => 'انسخ الرابط أدناه وأرسله عبر واتساب يدوياً إلى رقم هاتفك'
    ];
    
    reply($response, 200);
    
} catch (Throwable $e) {
    slog('exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    reply(['ok'=>false,'error'=>'exception','details'=>$e->getMessage()], 500);
}
?>
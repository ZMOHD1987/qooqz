<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$mysqli = connectDB();
if (!$mysqli || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database not available']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

// قراءة الإدخال (JSON أو form)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$identifier = isset($input['identifier']) ? trim($input['identifier']) : null;
$password   = isset($input['password']) ? $input['password'] : null;

if ($identifier && strlen($identifier) > 255) $identifier = substr($identifier, 0, 255);
if ($password && strlen($password) > 256) $password = substr($password, 0, 256);

if (!$identifier || !$password) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'identifier and password required']);
    exit;
}

try {
    // حاول جلب preferred_language إن كان موجوداً في الجدول
    // الافتراض: العمود موجود؛ إن لم يكن، سيقع استثناء وسنلتقطه ونعطي default لاحقاً
    $sql = "SELECT id, username, email, password_hash, role_id, is_active, preferred_language FROM users WHERE email = ? OR username = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: '.$mysqli->error);
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Invalid credentials']);
        exit;
    }

    if (isset($user['is_active']) && !(int)$user['is_active']) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'User inactive']);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Invalid credentials']);
        exit;
    }

    // إنشاء توكن جديد (raw) وتخزين هاشه في DB
    $rawToken = bin2hex(random_bytes(32)); // 64 hex chars
    $tokenHash = hash('sha256', $rawToken);
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $ins = $mysqli->prepare("INSERT INTO user_sessions (user_id, token, user_agent, ip, created_at, expires_at, revoked) VALUES (?, ?, ?, ?, NOW(), ?, 0)");
    if (!$ins) throw new Exception('Prepare session failed: '.$mysqli->error);
    $ins->bind_param('issss', $user['id'], $tokenHash, $ua, $ip, $expires_at);
    $ok = $ins->execute();
    $ins->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Server error (session create failed)']);
        exit;
    }

    // بدء الجلسة وتجنّب session fixation
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_regenerate_id(true);

    // ملأ بيانات الجلسة الأساسية
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = $user['role_id'];

    // حفظ اللغة المفضلة (إن وجدت) في الجلسة مع fallback
    $preferred_lang = 'en';
    if (!empty($user['preferred_language'])) {
        $preferred_lang = $user['preferred_language'];
    } elseif (!empty($user['language'])) {
        $preferred_lang = $user['language'];
    }
    $_SESSION['preferred_language'] = $preferred_lang;
    $_SESSION['html_direction'] = in_array($preferred_lang, ['ar','fa','he']) ? 'rtl' : 'ltr';

    // إعداد الكوكي الآمن
    $cookieName = 'session_token';
    $cookieValue = $rawToken;
    $cookieExpire = time() + 60*60*24*30;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if (PHP_VERSION_ID >= 70300) {
        setcookie($cookieName, $cookieValue, [
            'expires' => $cookieExpire,
            'path' => '/',
            'domain' => '', // ضع نطاقًا إذا تريد: '.mzmz.rf.gd'
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        $cookieStr = rawurlencode($cookieName) . '=' . rawurlencode($cookieValue)
            . '; Expires=' . gmdate('D, d M Y H:i:s T', $cookieExpire)
            . '; Path=/'
            . ($secure ? '; Secure' : '')
            . '; HttpOnly'
            . '; SameSite=Lax';
        header('Set-Cookie: ' . $cookieStr, false);
    }

    $responseUser = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role_id' => (int)$user['role_id'],
        'preferred_language' => $preferred_lang,
        'direction' => $_SESSION['html_direction']
    ];

    echo json_encode([
        'success' => true,
        'expires_at' => $expires_at,
        'user' => $responseUser
    ]);
    exit;

} catch (Exception $e) {
    // If the query failed because column doesn't exist, try fallback select without preferred_language
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        try {
            $sql2 = "SELECT id, username, email, password_hash, role_id, is_active FROM users WHERE email = ? OR username = ? LIMIT 1";
            $stmt2 = $mysqli->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param('ss', $identifier, $identifier);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                $user = $res2->fetch_assoc();
                $stmt2->close();
                if (!$user) {
                    http_response_code(401);
                    echo json_encode(['success'=>false,'message'=>'Invalid credentials']);
                    exit;
                }
                if (!password_verify($password, $user['password_hash'])) {
                    http_response_code(401);
                    echo json_encode(['success'=>false,'message'=>'Invalid credentials']);
                    exit;
                }
                if (session_status() === PHP_SESSION_NONE) session_start();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                // fallback language
                $_SESSION['preferred_language'] = 'en';
                $_SESSION['html_direction'] = 'ltr';
                // Note: session_token insertion earlier failed path won't be re-attempted here.
                echo json_encode(['success'=>true,'user'=>[
                    'id'=> (int)$user['id'],
                    'username'=>$user['username'],
                    'email'=>$user['email'],
                    'role_id'=> (int)$user['role_id'],
                    'preferred_language'=> 'en',
                    'direction' => 'ltr'
                ]]);
                exit;
            }
        } catch (Exception $e2) {
            // fall through to generic error
        }
    }

    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
    @file_put_contents(__DIR__ . '/../../logs/errors.log', '['.date('c').'] login error: '.$e->getMessage().PHP_EOL, FILE_APPEND);
    exit;
}
?>
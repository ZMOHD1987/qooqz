<?php
// /api/users/validate_token.php (مخطط مبسط)
session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? $_COOKIE['session_token'] ?? '';

if (!$token) {
    echo json_encode(['success'=>false,'message'=>'No token']);
    exit;
}

// تحقق من التوكن في DB (مثال PDO)
$pdo = new PDO(...);
$stmt = $pdo->prepare("SELECT u.id, u.username, u.email, u.preferred_language, u.role_id FROM users u JOIN user_tokens t ON t.user_id=u.id WHERE t.token = ? LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // اعد بناء الجلسة server-side
    $_SESSION['user'] = $user;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = $user['role_id'];
    // language
    if (!empty($user['preferred_language'])) {
        $_SESSION['preferred_language'] = $user['preferred_language'];
    } else {
        $_SESSION['preferred_language'] = 'en';
    }
    $_SESSION['html_direction'] = in_array($_SESSION['preferred_language'], ['ar','fa','he']) ? 'rtl' : 'ltr';

    echo json_encode(['success'=>true,'user'=>$user]);
    exit;
} else {
    // توكن غير صالح — حذف الكوكي وارجاع false
    setcookie('session_token','',time()-3600,'/','.mzmz.rf.gd', isset($_SERVER['HTTPS']), true);
    echo json_encode(['success'=>false,'message'=>'Invalid token']);
    exit;
}
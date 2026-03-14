<?php
declare(strict_types=1);
/**
 * Check Session API - التحقق من حالة الجلسة
 * المسار: htdocs/api/users/check_session.php
 * 
 * يستخدم للتحقق من أن المستخدم مسجل دخول وجلسته نشطة
 */

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إعداد headers
header('Content-Type: application/json; charset=utf-8');

/**
 * دالة الاستجابة
 */
function respond(bool $success, string $message, array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// فحص وجود الجلسة
if (empty($_SESSION['user_id']) || empty($_SESSION['logged_in'])) {
    respond(false, 'غير مسجل دخول', [], 401);
}

// فحص انتهاء صلاحية الجلسة (مثلاً بعد ساعتين من عدم النشاط)
$sessionTimeout = 7200; // ساعتان بالثواني
$lastActivity = $_SESSION['last_activity'] ?? 0;

if ($lastActivity > 0 && (time() - $lastActivity) > $sessionTimeout) {
    // انتهت صلاحية الجلسة
    $_SESSION = [];
    session_destroy();
    respond(false, 'انتهت صلاحية الجلسة. سجل دخولك مرة أخرى', [], 401);
}

// تحديث آخر نشاط
$_SESSION['last_activity'] = time();

// إعادة بيانات المستخدم
$userData = [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'role_id' => $_SESSION['role_id'] ?? 0,
    'role_name' => $_SESSION['role_name'] ?? '',
    'permissions' => $_SESSION['permissions'] ?? [],
    'login_time' => $_SESSION['login_time'] ?? 0,
    'session_duration' => time() - ($_SESSION['login_time'] ?? time())
];

respond(true, 'الجلسة نشطة', $userData, 200);

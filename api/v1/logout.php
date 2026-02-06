<?php
declare(strict_types=1);
/**
 * Logout API - تسجيل الخروج
 * المسار: htdocs/api/users/logout.php
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
function respond(bool $success, string $message, int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// تسجيل نشاط الخروج
$userId = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'unknown';

// تسجيل في السجل
$logFile = __DIR__ . '/../../logs/login_errors.log';
if (is_writable(dirname($logFile))) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = "[{$timestamp}] IP: {$ip} | Logout successful | User: {$username} (ID: {$userId})\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// حذف جميع بيانات الجلسة
$_SESSION = [];

// حذف كوكي الجلسة
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// تدمير الجلسة
session_destroy();

// الاستجابة
respond(true, 'تم تسجيل الخروج بنجاح', 200);

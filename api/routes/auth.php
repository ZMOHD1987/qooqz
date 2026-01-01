<?php
// htdocs/api/routes/auth.php
// Routes for authentication endpoints (maps HTTP requests to AuthController methods)

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/rate_limit.php';

// تأكد من تهيئة الـ CORS و الـ Content-Type (يمكن تخصيص حسب الحاجة)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// ===========================================
// مسار بسيط: استخراج الجزء بعد /api/auth
// أمثلة مسارات مدعومة:
//  - /api/auth/register
//  - /api/auth/login
//  - /api/auth/refresh
//  - /api/auth/logout
//  - /api/auth/me
//  - /api/auth/send-otp
//  - /api/auth/verify-otp
//  - /api/auth/forgot-password
//  - /api/auth/reset-password
//  - /api/auth/send-verify-email
//  - /api/auth/verify-email (GET token=...)
// ===========================================

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// حاول إزالة أي جزء قبل /api/auth
$baseSegment = '/api/auth';
$path = parse_url($requestUri, PHP_URL_PATH);

// إزالة المسار الأساسي إن وُجد
$actionPath = '/';
if (strpos($path, $baseSegment) !== false) {
    $actionPath = substr($path, strpos($path, $baseSegment) + strlen($baseSegment));
}
$actionPath = trim($actionPath, '/');
$actionParts = $actionPath === '' ? [] : explode('/', $actionPath);

// الميثود
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// بسيط: تحديد الفعل بناءً على الجزء الأول من المسار أو على الميثود/مسار الجذر
$action = $actionParts[0] ?? '';

// ===========================================
// توجيه الطلبات
// ===========================================
try {
    switch ($action) {
        case 'register':
            if ($method === 'POST') {
                AuthController::register();
                exit;
            }
            break;

        case 'login':
            if ($method === 'POST') {
                AuthController::login();
                exit;
            }
            break;

        case 'refresh':
            if ($method === 'POST') {
                AuthController::refresh();
                exit;
            }
            break;

        case 'logout':
            if ($method === 'POST') {
                AuthController::logout();
                exit;
            }
            break;

        case 'me':
            if ($method === 'GET') {
                AuthController::me();
                exit;
            }
            break;

        case 'send-otp':
            if ($method === 'POST') {
                AuthController::sendOTP();
                exit;
            }
            break;

        case 'verify-otp':
            if ($method === 'POST') {
                AuthController::verifyOTP();
                exit;
            }
            break;

        case 'forgot-password':
            if ($method === 'POST') {
                AuthController::forgotPassword();
                exit;
            }
            break;

        case 'reset-password':
            if ($method === 'POST') {
                AuthController::resetPassword();
                exit;
            }
            break;

        case 'send-verify-email':
            if ($method === 'POST') {
                AuthController::sendVerifyEmail();
                exit;
            }
            break;

        case 'verify-email':
            // could be GET with token query param
            if ($method === 'GET' || $method === 'POST') {
                AuthController::verifyEmail();
                exit;
            }
            break;

        case '':
            // If no extra segment, allow POST to login or register based on a 'action' param
            if ($method === 'POST') {
                $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
                $sub = $body['action'] ?? $_POST['action'] ?? null;
                if ($sub === 'login') { AuthController::login(); exit; }
                if ($sub === 'register') { AuthController::register(); exit; }
                // otherwise 404 below
            }
            break;
    }

    // If no route matched — return 404
    Response::error('Endpoint not found', 404);

} catch (Throwable $e) {
    // خطأ غير متوقع
    error_log("Auth route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>
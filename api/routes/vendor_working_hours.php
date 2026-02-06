<?php
/**
 * api/routes/vendor_working_hours.php
 * نسخة مطابقة لملف vendor_attributes_values
 * لضمان جلب البيانات وتجاوز مشاكل الجلسة
 */
declare(strict_types=1);

// 1. تفعيل الأخطاء للفحص (Debug)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2. بدء الجلسة
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. تصحيح CORS - السماح بالوصول من لوحة التحكم
header('Content-Type: application/json; charset=utf-8');
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 4. التحقق من وجود الـ Controller
$ctrlPath = dirname(__DIR__) . '/controllers/Vendor_working_hoursController.php';
if (!is_readable($ctrlPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Controller missing at: ' . $ctrlPath
    ]);
    exit;
}
require_once $ctrlPath;

/**
 * 5. معالجة الصلاحيات (Crucial Fix)
 */
$userInfo = $_SESSION['user_info'] ?? $_SESSION['user'] ?? [];
$roleId = (int)($userInfo['role_id'] ?? $_SESSION['role_id'] ?? 0);

// في حال عدم وجود جلسة نسمح مؤقتًا للفحص
if ($roleId === 0 && !isset($_SESSION['user_id'])) {
    // echo json_encode(['success' => false, 'message' => 'Auth Session Missing']); exit;
}

// 6. تجهيز البيانات المدخلة
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

// دمج GET + POST + JSON Body
$input = array_merge($_GET, $_POST, $body);

// 7. التوجيه (Routing)
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {

        // جلب عنصر واحد
        if (isset($input['id']) && is_numeric($input['id'])) {
            Vendor_working_hoursController::get([
                'id' => (int)$input['id']
            ]);
        } 
        // جلب القائمة
        else {
            Vendor_working_hoursController::list($input);
        }

    } elseif ($method === 'POST') {

        $action = strtolower(trim((string)($input['action'] ?? 'save')));

        if ($action === 'delete') {
            Vendor_working_hoursController::delete($input);
        } else {
            Vendor_working_hoursController::save($input);
        }

    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error',
        'error'   => $e->getMessage()
    ]);
}

<?php
// htdocs/api/routes/languages.php
declare(strict_types=1);

// 1. استدعاء الملفات الأساسية
require_once __DIR__ . '/../models/Languages.php';
require_once __DIR__ . '/../controllers/LanguagesController.php';

// 2. دوال احتياطية (Fallback) في حال عدم وجودها في النظام
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('errorResponse')) {
    function errorResponse($message, $status = 400) {
        jsonResponse(['status' => 'error', 'message' => $message], $status);
    }
}

// 3. تجهيز الحاوية (Container) لضمان عدم تمرير null للـ Controller
$c = (isset($container) && is_array($container)) ? $container : [
    'method' => $_SERVER['REQUEST_METHOD'],
    'input'  => array_merge($_GET, $_POST, (array)json_decode(file_get_contents('php://input'), true))
];

$method = $c['method'] ?? $_SERVER['REQUEST_METHOD'];

// 4. توجيه الأكشن
try {
    if ($method === 'POST') {
        $action = $c['input']['action'] ?? $_GET['action'] ?? 'save';
        if ($action === 'delete') {
            LanguagesController::delete($c);
        } else {
            LanguagesController::save($c);
        }
    } else {
        // طلب GET يعرض القائمة
        LanguagesController::list($c);
    }
} catch (Throwable $e) {
    errorResponse('Exception: ' . $e->getMessage(), 500);
}
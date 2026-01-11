<?php
// api/routes/users.php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../controllers/UserController.php';

// التأكد من بدء الجلسة للتحقق من CSRF
if (session_status() === PHP_SESSION_NONE) session_start();

$method = $_SERVER['REQUEST_METHOD'];
$input = array_merge($_POST, $_GET);

// قراءة بيانات JSON إن وجدت
$raw = @file_get_contents('php://input');
$jsonData = @json_decode($raw, true);
if (is_array($jsonData)) $input = array_merge($input, $jsonData);

$action = $input['action'] ?? '';

if ($method === 'POST') {
    // 1. التحقق من CSRF Token
    $clientToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $serverToken = $_SESSION['csrf_token'] ?? '';

    if (empty($clientToken) || $clientToken !== $serverToken) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid CSRF token',
            'debug' => ['received' => $clientToken, 'expected' => $serverToken]
        ]);
        exit;
    }

    // 2. توجيه الأكشن
    switch (strtolower((string)$action)) {
        case 'create':
        case 'update':
        case 'save':
            UserController::save($input);
            break;
        case 'delete':
            UserController::delete($input);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
            break;
    }
    exit;
}

if ($method === 'GET') {
    isset($input['id']) ? UserController::get($input) : UserController::list($input);
    exit;
}

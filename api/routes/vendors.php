<?php
declare(strict_types=1);

// ===== عرض الأخطاء أثناء التطوير =====
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ===== تحديد مسار المشروع =====
$baseDir = dirname(__DIR__);

// ===== تضمين قاعدة البيانات =====
require_once $baseDir . '/config/db.php';

// ===== تضمين الموديل والفاليداتور والكنترولر =====
require_once $baseDir . '/models/Vendor.php';
require_once $baseDir . '/validators/VendorsValidator.php';
require_once $baseDir . '/controllers/VendorController.php';

// ===== إنشاء الكنترولر =====
try {
    $controller = new VendorController();
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Controller initialization failed: ' . $e->getMessage()
    ]);
    exit;
}

// ===== التعامل مع الطلبات =====
$id = $_SERVER['ROUTE_ID'] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if ($id) $response = $controller->show((int)$id);
        elseif (isset($_GET['parents'])) $response = $controller->parents();
        else $response = $controller->index($_GET);
        break;

    case 'POST':
        if (isset($_POST['action']) && $_POST['action'] === 'toggle_verify') {
            $response = $controller->toggleVerify((int)$id, (int)($_POST['value'] ?? 0));
        } else {
            $response = $controller->store($_POST);
        }
        break;

    case 'PUT':
    case 'PATCH':
        // قراءة بيانات JSON أو FORM
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $response = $controller->update((int)$id, $data);
        break;

    case 'DELETE':
        $response = $controller->delete((int)$id);
        break;

    default:
        $response = ['success'=>false,'message'=>'Method not allowed'];
        break;
}

// ===== إرسال JSON response =====
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

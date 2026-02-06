<?php
declare(strict_types=1);

// ========================================================
// ملف الراوتر: api/routes/brands.php
// ========================================================

// عرض الأخطاء أثناء التطوير فقط
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ---------------------------------------------------------
// تحديد المسار الأساسي للمشروع
// ---------------------------------------------------------
$baseDir = $_SERVER['DOCUMENT_ROOT'];

// ---------------------------------------------------------
// تضمين الملفات المطلوبة
// ---------------------------------------------------------
$requiredFiles = [
    $baseDir . '/api/config/db.php',
    $baseDir . '/api/models/Brand.php',
    $baseDir . '/api/validators/BrandValidator.php',
    $baseDir . '/api/controllers/BrandController.php',
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'خطأ في تهيئة النظام',
            'detail'  => 'الملف غير موجود: ' . basename($file)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    require_once $file;
}

// ---------------------------------------------------------
// إنشاء الـ Controller
// ---------------------------------------------------------
try {
    $db = connectDB();
    $controller = new BrandController($db);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'فشل تهيئة الـ Controller أو الاتصال بقاعدة البيانات',
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------
// قراءة بيانات الطلب
// ---------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// دعم تمرير id في المسار (مثال: /brands/5)
$id = $_SERVER['ROUTE_ID'] ?? null;
if ($id !== null) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    $id = ($id !== false && $id > 0) ? $id : null;
}

// قراءة البيانات الواردة (body أو query params)
$input = match (true) {
    in_array($method, ['POST', 'PUT', 'PATCH']) 
        => json_decode(file_get_contents('php://input'), true) ?: ($_POST ?? []),
    default => $_GET ?? []
};

// ---------------------------------------------------------
// معالجة الطلب حسب الـ HTTP Method
// ---------------------------------------------------------
$response = match ($method) {
    'GET'    => $id ? $controller->BrandController_show($id) : $controller->BrandController_index(),
    
    'POST'   => $controller->BrandController_save(),
    
    'PUT', 
    'PATCH'  => $id 
                ? $controller->BrandController_save($id)
                : ['success' => false, 'message' => 'معرف الماركة مطلوب للتعديل'],
    
    'DELETE' => $id 
                ? $controller->BrandController_delete($id)
                : ['success' => false, 'message' => 'معرف الماركة مطلوب للحذف'],
    
    default  => [
        'success' => false,
        'message' => 'طريقة الطلب غير مدعومة',
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
    ]
};

// ---------------------------------------------------------
// إرسال الرد النهائي
// ---------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

// تحسين كود الحالة (status code) حسب نوع الرد
http_response_code(match (true) {
    isset($response['success']) && $response['success'] === true => 200,
    isset($response['errors']) => 422,
    str_contains($response['message'] ?? '', 'not found') => 404,
    default => 400
});

// إذا كان الرد ليس JSON بالفعل (مثل echo من Controller) تجاهل
if (!is_array($response)) {
    exit;
}

echo json_encode(
    $response,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
exit;

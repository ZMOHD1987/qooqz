<?php
// htdocs/api/routes/product.php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/ProductController.php';

// إنشاء الاتصال بقاعدة البيانات
$conn = connectDB();

// إنشاء الـ Controller
$controller = new ProductController($conn);

// ===== Dispatcher بسيط بناءً على REQUEST_METHOD =====
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // إذا تم تمرير id أو slug، عرض المنتج الواحد
            if (isset($_GET['id']) || isset($_GET['slug'])) {
                $controller->ProductController_show($_GET['id'] ?? $_GET['slug']);
            } else {
                // قائمة المنتجات مع pagination
                $controller->ProductController_index();
            }
            break;

        case 'POST':
            // يمكن تمرير action: delete, update_stock, أو save
            $action = $_POST['action'] ?? 'save';

            switch ($action) {
                case 'delete':
                    if (!isset($_POST['id'])) {
                        http_response_code(400);
                        echo json_encode(['success'=>false,'message'=>'Product ID required']);
                        exit;
                    }
                    $controller->ProductController_delete((int)$_POST['id']);
                    break;

                case 'update_stock':
                    // تحديث المخزون فقط
                    $controller->ProductController_updateStock($_POST ?? []);
                    break;

                default:
                    // حفظ منتج جديد أو تحديث موجود
                    $id = $_POST['id'] ?? null;
                    $controller->ProductController_save($id);
                    break;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success'=>false,'message'=>'Method Not Allowed']);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'message'=>'Internal Server Error',
        'debug'=>$e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

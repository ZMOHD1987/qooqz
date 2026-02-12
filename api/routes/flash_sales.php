<?php
declare(strict_types=1);

require_once __DIR__ . '/../v1/models/flash_sales/repositories/PdoFlashSalesRepository.php';
require_once __DIR__ . '/../v1/models/flash_sales/services/FlashSalesService.php';
require_once __DIR__ . '/../v1/models/flash_sales/controllers/FlashSalesController.php';
require_once __DIR__ . '/../v1/models/flash_sales/validators/FlashSalesValidator.php';

$pdo = getPDO();
$repo = new PdoFlashSalesRepository($pdo);
$service = new FlashSalesService($repo);
$controller = new FlashSalesController($service);
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                $stats = $controller->stats();
                json_response(['success' => true, 'message' => 'OK', 'data' => $stats]);
                break;
            }
            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                $item = $controller->find((int)$_GET['id']);
                if (!$item) { http_response_code(404); json_response(['success' => false, 'message' => 'Flash sale not found']); break; }
                json_response(['success' => true, 'message' => 'OK', 'data' => $item]);
            } else {
                $filters = [];
                if (isset($_GET['is_active']))  $filters['is_active'] = $_GET['is_active'];
                if (isset($_GET['status']))     $filters['status'] = $_GET['status'];
                if (isset($_GET['search']))     $filters['search'] = $_GET['search'];
                if (isset($_GET['limit']))      $filters['limit'] = $_GET['limit'];
                if (isset($_GET['offset']))     $filters['offset'] = $_GET['offset'];
                $result = $controller->list($filters);
                json_response(['success' => true, 'message' => 'OK', 'data' => $result]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $errors = FlashSalesValidator::validateCreate($data);
            if ($errors) { http_response_code(422); json_response(['success' => false, 'message' => implode(', ', $errors)]); break; }
            $id = $controller->create($data);
            json_response(['success' => true, 'message' => 'Flash sale created', 'data' => ['id' => $id]]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); json_response(['success' => false, 'message' => 'ID is required']); break; }
            $errors = FlashSalesValidator::validateUpdate($data);
            if ($errors) { http_response_code(422); json_response(['success' => false, 'message' => implode(', ', $errors)]); break; }
            $controller->update($id, $data);
            json_response(['success' => true, 'message' => 'Flash sale updated']);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); json_response(['success' => false, 'message' => 'ID is required']); break; }
            $controller->delete($id);
            json_response(['success' => true, 'message' => 'Flash sale deleted']);
            break;

        default:
            http_response_code(405);
            json_response(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    json_response(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}

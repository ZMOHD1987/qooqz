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
            $fid = (int)($_GET['flash_sale_id'] ?? 0);
            if ($fid <= 0) { http_response_code(400); json_response(['success' => false, 'message' => 'flash_sale_id is required']); break; }
            $products = $controller->getProducts($fid);
            json_response(['success' => true, 'message' => 'OK', 'data' => $products]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $errors = FlashSalesValidator::validateProduct($data);
            if ($errors) { http_response_code(422); json_response(['success' => false, 'message' => implode(', ', $errors)]); break; }
            $id = $controller->addProduct($data);
            json_response(['success' => true, 'message' => 'Product added to flash sale', 'data' => ['id' => $id]]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); json_response(['success' => false, 'message' => 'ID is required']); break; }
            $controller->updateProduct($id, $data);
            json_response(['success' => true, 'message' => 'Product updated']);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); json_response(['success' => false, 'message' => 'ID is required']); break; }
            $controller->deleteProduct($id);
            json_response(['success' => true, 'message' => 'Product removed from flash sale']);
            break;

        default:
            http_response_code(405);
            json_response(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    json_response(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}

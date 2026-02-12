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
            $lang = isset($_GET['language_code']) ? $_GET['language_code'] : null;
            $translations = $controller->getTranslations($fid, $lang);
            json_response(['success' => true, 'message' => 'OK', 'data' => $translations]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $errors = FlashSalesValidator::validateTranslation($data);
            if ($errors) { http_response_code(422); json_response(['success' => false, 'message' => implode(', ', $errors)]); break; }
            $controller->saveTranslation($data);
            json_response(['success' => true, 'message' => 'Translation saved']);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id > 0) {
                $controller->deleteTranslation($id);
                json_response(['success' => true, 'message' => 'Translation deleted']);
                break;
            }
            $fid = (int)($_GET['flash_sale_id'] ?? 0);
            $lang = $_GET['language_code'] ?? '';
            if ($fid > 0 && $lang) {
                $controller->deleteTranslationsByLang($fid, $lang);
                json_response(['success' => true, 'message' => 'Translations deleted']);
                break;
            }
            http_response_code(400);
            json_response(['success' => false, 'message' => 'id or (flash_sale_id + language_code) required']);
            break;

        default:
            http_response_code(405);
            json_response(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    json_response(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}

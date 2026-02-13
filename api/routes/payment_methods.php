<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Riyadh');

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';
require_once dirname(__DIR__) . '/v1/models/payment_methods/repositories/PdoPaymentMethodsRepository.php';
require_once dirname(__DIR__) . '/v1/models/payment_methods/validators/PaymentMethodsValidator.php';
require_once dirname(__DIR__) . '/v1/models/payment_methods/services/PaymentMethodsService.php';
require_once dirname(__DIR__) . '/v1/models/payment_methods/controllers/PaymentMethodsController.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

try {
    $pdo        = $GLOBALS['ADMIN_DB'];
    $method     = $_SERVER['REQUEST_METHOD'];
    $service    = new PaymentMethodsService($pdo);
    $controller = new PaymentMethodsController($service);

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $item = $controller->find((int)$_GET['id']);
                if (!$item) {
                    ResponseFormatter::error('Not found', 404);
                    exit;
                }
                ResponseFormatter::success($item);
            } else {
                $filters = [];
                if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

                $result = $controller->list(
                    isset($_GET['limit']) ? (int)$_GET['limit'] : 100,
                    isset($_GET['offset']) ? (int)$_GET['offset'] : 0,
                    $filters,
                    $_GET['order_by'] ?? 'method_name',
                    $_GET['order_dir'] ?? 'ASC'
                );
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = $controller->create($data);
            ResponseFormatter::success(['id' => $id], 'Created');
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            $controller->update($id, $data);
            ResponseFormatter::success(['id' => $id], 'Updated');
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            $controller->delete($id);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 400);
}

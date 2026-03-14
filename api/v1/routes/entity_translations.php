<?php
declare(strict_types=1);

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

// Set timezone
date_default_timezone_set('Asia/Riyadh');

// Load dependencies
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// Load classes
$modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntityTranslationsRepository.php';
require_once $modelsPath . '/services/EntityTranslationsService.php';
require_once $modelsPath . '/controllers/EntityTranslationsController.php';

// CORS headers
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

// Start session if needed
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Database connection
if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

try {
    // Initialize dependencies
    $pdo = $GLOBALS['ADMIN_DB'];
    $repo = new PdoEntityTranslationsRepository($pdo);
    $service = new EntityTranslationsService($repo);
    $controller = new EntityTranslationsController($service);

    // Handle preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // Get request method and input
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawInput = file_get_contents('php://input');
    $data = $rawInput ? json_decode($rawInput, true) : [];
    
    // Merge POST/GET data
    if ($method === 'POST' && !empty($_POST)) {
        $data = array_merge($data, $_POST);
    }
    
    // Parse query string for GET parameters not in $_GET (if any issues)
    // $_GET is usually sufficient

    switch ($method) {
        case 'GET':
            if (isset($_GET['entity_id'])) {
                $entityId = (int)$_GET['entity_id'];
                $result = $controller->getByEntity($entityId);
                ResponseFormatter::success($result);
            } else {
                // If specific ID is requested (not common for this route structure but possible)
               ResponseFormatter::error('entity_id is required', 400);
            }
            break;

        case 'POST':
        case 'PUT':
            if (empty($data['entity_id']) || empty($data['language_code'])) {
                 ResponseFormatter::error('entity_id and language_code are required', 400);
                 exit;
            }
            $id = $controller->save($data);
            ResponseFormatter::success(['id' => $id], 'Saved successfully', 201);
            break;

        case 'DELETE':
             if (isset($data['id'])) {
                 $id = (int)$data['id'];
                 $result = $controller->delete($id);
                 ResponseFormatter::success($result, 'Deleted successfully');
             } elseif (isset($_GET['id'])) {
                 $id = (int)$_GET['id'];
                 $result = $controller->delete($id);
                 ResponseFormatter::success($result, 'Deleted successfully');
             } else {
                 ResponseFormatter::error('ID is required', 400);
             }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (Throwable $e) {
    error_log("Error in entity_translations: " . $e->getMessage(), 3, __DIR__ . '/../../error_log.txt');
    ResponseFormatter::error('Internal Server Error: ' . $e->getMessage(), 500);
}


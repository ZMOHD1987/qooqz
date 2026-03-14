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
require_once $modelsPath . '/repositories/PdoEntitiesWorkingHoursRepository.php';
require_once $modelsPath . '/validators/EntitiesWorkingHoursValidator.php';
require_once $modelsPath . '/services/EntitiesWorkingHoursService.php';
require_once $modelsPath . '/controllers/EntitiesWorkingHoursController.php';

// CORS headers
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Access-Control-Max-Age: 86400');
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
    $repo = new PdoEntitiesWorkingHoursRepository($pdo);
    $service = new EntitiesWorkingHoursService($repo);
    $controller = new EntitiesWorkingHoursController($service);

    // Handle preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // Get request method and path
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $queryString = $_SERVER['QUERY_STRING'] ?? '';

    // Parse query parameters
    parse_str($queryString, $queryParams);
    
    // Get input data
    $rawInput = file_get_contents('php://input');
    $inputData = $rawInput ? json_decode($rawInput, true) : [];
    
    // Merge POST data if exists
    if ($method === 'POST' && !empty($_POST)) {
        $inputData = array_merge($inputData, $_POST);
    }

    // Route the request
    switch ($method) {
        case 'GET':
            handleGetRequest($controller, $queryParams, $path);
            break;

        case 'POST':
            handlePostRequest($controller, $inputData, $path);
            break;

        case 'PUT':
            handlePutRequest($controller, $inputData, $path);
            break;

        case 'DELETE':
            handleDeleteRequest($controller, $inputData, $path);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    // Validation errors
    safe_log('warning', 'entities_working_hours.validation', [
        'error' => $e->getMessage(),
        'method' => $method ?? 'unknown',
        'path' => $path ?? 'unknown'
    ]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (RuntimeException $e) {
    // Business logic errors
    $code = $e->getCode() ?: 400;
    safe_log('error', 'entities_working_hours.business', [
        'error' => $e->getMessage(),
        'code' => $code,
        'method' => $method ?? 'unknown',
        'path' => $path ?? 'unknown'
    ]);
    ResponseFormatter::error($e->getMessage(), $code);

} catch (Throwable $e) {
    // System errors
    safe_log('critical', 'entities_working_hours.system', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'method' => $method ?? 'unknown',
        'path' => $path ?? 'unknown'
    ]);
    ResponseFormatter::error('Internal Server Error', 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest(
    EntitiesWorkingHoursController $controller, 
    array $queryParams, 
    string $path
): void {
    // Check if requesting by entity ID
    if (preg_match('#/entities_working_hours/entity/(\d+)#', $path, $matches)) {
        try {
            $entityId = (int)$matches[1];
            $result = $controller->getByEntity($entityId);
            ResponseFormatter::success($result);
        } catch (Throwable $e) {
            error_log("Error in entities_working_hours path GET: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 3, __DIR__ . '/../../error_log.txt');
            ResponseFormatter::error('Internal Server Error: ' . $e->getMessage(), 500);
        }
        return;
    }

    if (isset($queryParams['entity_id']) && is_numeric($queryParams['entity_id'])) {
        try {
            $entityId = (int)$queryParams['entity_id'];
            $result = $controller->getByEntity($entityId);
            ResponseFormatter::success($result);
        } catch (Throwable $e) {
            error_log("Error in entities_working_hours GET: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 3, __DIR__ . '/../../error_log.txt');
            ResponseFormatter::error('Internal Server Error: ' . $e->getMessage(), 500);
        }
        return;
    }

    // Check if requesting single record
    if (isset($queryParams['id']) && is_numeric($queryParams['id'])) {
        $id = (int)$queryParams['id'];
        $result = $controller->get($id);
        ResponseFormatter::success($result);
        return;
    }

    // List all records with pagination
    $result = $controller->list($queryParams);
    ResponseFormatter::success($result);
}

/**
 * Handle POST requests
 */
function handlePostRequest(
    EntitiesWorkingHoursController $controller, 
    array $data, 
    string $path
): void {
    // Check if bulk create
    if (strpos($path, '/bulk') !== false) {
        $result = $controller->createBulk($data);
        ResponseFormatter::success($result, 'Created successfully', 201);
        return;
    }

    // Single create
    try {
        $id = $controller->create($data);
        ResponseFormatter::success(['id' => $id], 'Created successfully', 201);
    } catch (Throwable $e) {
        error_log("Error in entities_working_hours POST: " . $e->getMessage(), 3, __DIR__ . '/../../error_log.txt');
        throw $e;
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest(
    EntitiesWorkingHoursController $controller, 
    array $data, 
    string $path
): void {
    // Extract ID from path or data
    $id = null;
    
    if (preg_match('#/entities_working_hours/(\d+)#', $path, $matches)) {
        $id = (int)$matches[1];
    } elseif (isset($data['id'])) {
        $id = (int)$data['id'];
    }

    if (!$id) {
        ResponseFormatter::error('ID is required for update', 400);
        return;
    }

    // Remove ID from data to prevent updating it
    unset($data['id']);
    
    $result = $controller->update($id, $data);
    ResponseFormatter::success($result, 'Updated successfully');
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest(
    EntitiesWorkingHoursController $controller, 
    array $data, 
    string $path
): void {
    // Check if deleting by entity ID via query param (POST/DELETE body)
    if (isset($data['entity_id'])) {
        $controller->deleteByEntity((int)$data['entity_id']);
        ResponseFormatter::success(null, 'Deleted successfully');
        return;
    }

    // Check if deleting by entity ID via GET query param (Standard DELETE)
    if (isset($_GET['entity_id'])) {
        $controller->deleteByEntity((int)$_GET['entity_id']);
        ResponseFormatter::success(null, 'Deleted successfully');
        return;
    }
    
    // Check if deleting by entity ID via path
    if (preg_match('#/entities_working_hours/entity/(\d+)#', $path, $matches)) {
        $entityId = (int)$matches[1];
        $controller->deleteByEntity($entityId);
        ResponseFormatter::success(null, 'Deleted successfully');
        return;
    }

    // Extract ID from path or data
    $id = null;
    
    if (preg_match('#/entities_working_hours/(\d+)#', $path, $matches)) {
        $id = (int)$matches[1];
    } elseif (isset($data['id'])) {
        $id = (int)$data['id'];
    }

    if (!$id) {
        ResponseFormatter::error('ID is required for deletion', 400);
        return;
    }

    $controller->delete($id);
    ResponseFormatter::success(null, 'Deleted successfully');
}


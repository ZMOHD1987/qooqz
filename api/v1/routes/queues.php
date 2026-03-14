<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

date_default_timezone_set('Asia/Riyadh');

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';

// MVC Components
require_once API_VERSION_PATH . '/models/queues/repositories/PdoQueuesRepository.php';
require_once API_VERSION_PATH . '/models/queues/validators/QueuesValidator.php';
require_once API_VERSION_PATH . '/models/queues/services/QueuesService.php';
require_once API_VERSION_PATH . '/models/queues/controllers/QueuesController.php';

// CORS (already handled in bootstrap but keeping explicit if needed)
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
        throw new Exception('Database connection failed');
    }

    // Initialize MVC
    $repository = new PdoQueuesRepository($GLOBALS['ADMIN_DB']);
    $service    = new QueuesService($repository);
    $controller = new QueuesController($service);

    // Request Data
    $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawInput = file_get_contents('php://input');
    $data     = $rawInput ? json_decode($rawInput, true) : [];
    if ($method === 'POST' && !empty($_POST)) {
        $data = array_merge($data ?? [], $_POST);
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path       = parse_url($requestUri, PHP_URL_PATH);
    
    // Determine sub-route
    $subRoute = '';
    if (strpos($path, '/queues/stats') !== false)   $subRoute = '/stats';
    elseif (strpos($path, '/queues/retry') !== false)   $subRoute = '/retry';
    elseif (strpos($path, '/queues/archive') !== false) $subRoute = '/archive';
    elseif (strpos($path, '/queues/purge') !== false)   $subRoute = '/purge';
    elseif (strpos($path, '/queues/names') !== false)   $subRoute = '/names';

    $controller->handleRequest($method, $subRoute, $data, $_GET);

} catch (Throwable $e) {
    safe_log('error', 'Critical error in queues route: ' . $e->getMessage());
    require_once API_VERSION_SHARED . '/core/ResponseFormatter.php';
    ResponseFormatter::error('Internal Server Error: ' . $e->getMessage(), 500);
}



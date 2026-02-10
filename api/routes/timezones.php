<?php
declare(strict_types=1);

/**
 * API Route: Timezones
 *
 * Supports:
 *  - GET  /api/timezones                      -> list all
 *  - GET  /api/timezones/{id}                 -> get single by id (numeric id)
 *  - GET  /api/timezones/{timezone}           -> get single by timezone string (e.g. UTC)
 *  - GET  /api/timezones?timezone=UTC         -> get by timezone (query)
 *  - POST /api/timezones                      -> create
 *  - PUT  /api/timezones                      -> update (accept JSON body with id)
 *  - DELETE /api/timezones                    -> delete (accept JSON body with id)
 *
 * This route expects the model files under API_VERSION_PATH (e.g. api/v1/models/...)
 */

$baseDir = dirname(__DIR__);

require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// Load model dependencies - ensure API_VERSION_PATH is defined in bootstrap.php
require_once API_VERSION_PATH . '/models/timezones/repositories/PdoTimezonesRepository.php';
require_once API_VERSION_PATH . '/models/timezones/validators/TimezonesValidator.php';
require_once API_VERSION_PATH . '/models/timezones/services/TimezonesService.php';
require_once API_VERSION_PATH . '/models/timezones/controllers/TimezonesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

$repo = new PdoTimezonesRepository($pdo);
$validator = new TimezonesValidator();
$service = new TimezonesService($repo, $validator);
$controller = new TimezonesController($service);

try {
    // Determine effective HTTP method (support override)
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }

    // Read request body
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    // Determine path segment after /api/timezones
    // Use REQUEST_URI and strip query string
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $uriPath = explode('?', $requestUri, 2)[0];

    // Normalize prefix that identifies this route (may vary depending on server config)
    // Try to locate '/api/timezones' in the path and get remainder after it
    $routeBase = '/api/timezones';
    $after = '';

    $pos = strpos($uriPath, $routeBase);
    if ($pos !== false) {
        $after = substr($uriPath, $pos + strlen($routeBase));
    } else {
        // fallback: check script name inclusion (if request routed to Kernel)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName && strpos($uriPath, $scriptName) === 0) {
            $after = substr($uriPath, strlen($scriptName));
        } else {
            // default no extra path
            $after = '';
        }
    }

    $after = trim($after, "/ \t\n\r\0\x0B");
    $segments = $after === '' ? [] : explode('/', $after);

    switch ($method) {
        case 'GET':
            // If direct id or timezone in path: /api/timezones/{seg}
            if (!empty($segments[0])) {
                $seg = urldecode($segments[0]);
                if (ctype_digit($seg)) {
                    // numeric id
                    $id = (int)$seg;
                    $item = $repo->getById($id);
                    if (!$item) {
                        ResponseFormatter::error('Timezone not found', 404);
                        return;
                    }
                    ResponseFormatter::success($item);
                    return;
                } else {
                    // treat as timezone string
                    $tz = $seg;
                    $item = $repo->getByTimezone($tz);
                    if (!$item) {
                        ResponseFormatter::error('Timezone not found', 404);
                        return;
                    }
                    ResponseFormatter::success($item);
                    return;
                }
            }

            // Otherwise check query params ?id= or ?timezone=
            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $item = $repo->getById($id);
                if (!$item) {
                    ResponseFormatter::error('Timezone not found', 404);
                    return;
                }
                ResponseFormatter::success($item);
                return;
            }

            if (!empty($_GET['timezone'])) {
                $tz = $_GET['timezone'];
                $item = $repo->getByTimezone($tz);
                if (!$item) {
                    ResponseFormatter::error('Timezone not found', 404);
                    return;
                }
                ResponseFormatter::success($item);
                return;
            }

            // No specific filter: list all
            $list = $controller->list();
            ResponseFormatter::success($list);
            return;

        case 'POST':
            // Create
            $result = $controller->store($data);
            ResponseFormatter::success($result);
            return;

        case 'PUT':
            $result = $controller->update($data);
            ResponseFormatter::success($result);
            return;

        case 'DELETE':
            $result = $controller->delete($data);
            ResponseFormatter::success($result);
            return;

        default:
            ResponseFormatter::error('Method not allowed: ' . $method, 405);
            return;
    }
} catch (InvalidArgumentException $e) {
    safe_log('warning', 'Timezones validation error', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'Timezones runtime error', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('error', 'Timezones route failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
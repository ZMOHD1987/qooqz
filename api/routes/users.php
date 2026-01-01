<?php
// htdocs/api/routes/users.php
// Routes for user endpoints (maps HTTP requests to UserController methods)

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type (تعديل حسب الحاجة)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/users
$baseSegment = '/api/users';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

$actionPath = '/';
if (strpos($path, $baseSegment) !== false) {
    $actionPath = substr($path, strpos($path, $baseSegment) + strlen($baseSegment));
}
$actionPath = trim($actionPath, '/');
$actionParts = $actionPath === '' ? [] : explode('/', $actionPath);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $actionParts[0] ?? '';

// helper to get id or next segment
$id = null;
if (isset($actionParts[0]) && is_numeric($actionParts[0])) {
    $id = (int)$actionParts[0];
    // shift action to next segment if exists
    $subAction = $actionParts[1] ?? null;
} else {
    $subAction = $actionParts[1] ?? null;
}

try {
    switch ($action) {
        // Public / authenticated user endpoints
        case '':
            // /api/users  - could be list (admin) or invalid by default
            if ($method === 'GET') {
                UserController::listUsers();
                exit;
            }
            break;

        case 'me':
            if ($method === 'GET') {
                UserController::me();
                exit;
            }
            break;

        case 'profile':
            if ($method === 'PUT' || $method === 'POST') {
                // support PUT (clients may send POST with _method override)
                UserController::updateProfile();
                exit;
            }
            break;

        case 'avatar':
            if ($method === 'POST') {
                UserController::uploadAvatar();
                exit;
            }
            break;

        case 'count':
            if ($method === 'GET') {
                // optional: simple route to count users (admin)
                UserController::stats();
                exit;
            }
            break;

        default:
            // If first segment is numeric treat as user id target: /api/users/{id} [...]
            if (is_numeric($action)) {
                $targetId = (int)$action;

                // /api/users/{id}  GET
                if ($method === 'GET' && !isset($actionParts[1])) {
                    UserController::getUser($targetId);
                    exit;
                }

                // /api/users/{id}  PUT
                if (($method === 'PUT' || $method === 'POST') && !isset($actionParts[1])) {
                    UserController::updateUser($targetId);
                    exit;
                }

                // /api/users/{id}  DELETE
                if ($method === 'DELETE') {
                    UserController::deleteUser($targetId);
                    exit;
                }

                // /api/users/{id}/suspend
                if (isset($actionParts[1]) && $actionParts[1] === 'suspend' && $method === 'POST') {
                    UserController::suspendUser($targetId);
                    exit;
                }

                // /api/users/{id}/unsuspend
                if (isset($actionParts[1]) && $actionParts[1] === 'unsuspend' && $method === 'POST') {
                    UserController::unsuspendUser($targetId);
                    exit;
                }

                // /api/users/{id}/stats
                if (isset($actionParts[1]) && $actionParts[1] === 'stats' && $method === 'GET') {
                    UserController::stats($targetId);
                    exit;
                }
            } else {
                // Non-numeric first segment routes (admin listing, stats etc.)
                if ($action === 'stats' && $method === 'GET') {
                    UserController::stats();
                    exit;
                }
            }
            break;
    }

    // إذا لم يتطابق أي مسار
    Response::error('Endpoint not found', 404);

} catch (Throwable $e) {
    error_log("Users route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>
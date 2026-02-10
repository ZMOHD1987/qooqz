<?php
declare(strict_types=1);
/**
 * Users Routes
 * URL: /api/users
 */

defined('API_ENTRY') or die('Direct access not allowed');

/*
|--------------------------------------------------------------------------
| Load dependencies (STRICT ORDER)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../v1/models/Account/models/User.php';
require_once __DIR__ . '/../v1/models/Account/validators/UserValidator.php';
require_once __DIR__ . '/../v1/models/Account/controllers/UserController.php';

/*
|--------------------------------------------------------------------------
| Get Request Data
|--------------------------------------------------------------------------
*/
$requestMethod = $GLOBALS['requestMethod'] ?? $_SERVER['REQUEST_METHOD'];
$requestBody = $GLOBALS['requestBody'] ?? [];
$routeParams = $GLOBALS['routeParams'] ?? [];

// دمج GET params مع routeParams
if (!empty($_GET)) {
    $routeParams = array_merge($routeParams, $_GET);
}

/*
|--------------------------------------------------------------------------
| Controller Instance
|--------------------------------------------------------------------------
*/
try {
    $controller = new UserController();
    
    /*
    |--------------------------------------------------------------------------
    | Route Dispatcher
    |--------------------------------------------------------------------------
    */
    switch ($requestMethod) {
        case 'GET':
            // GET /api/users/123 - عرض مستخدم واحد
            if (!empty($routeParams['id'])) {
                $controller->show($routeParams);
            } 
            // GET /api/users - عرض القائمة
            else {
                $controller->index($routeParams);
            }
            break;

        case 'POST':
            // POST /api/users - إنشاء مستخدم جديد
            $controller->store($requestBody);
            break;

        case 'PUT':
        case 'PATCH':
            // PUT /api/users/123 - تحديث مستخدم
            if (empty($routeParams['id'])) {
                ResponseFormatter::badRequest('Missing user ID in URL');
                exit;
            }
            
            $requestBody['id'] = (int)$routeParams['id'];
            $controller->update($requestBody);
            break;

        case 'DELETE':
            // DELETE /api/users/123 - حذف مستخدم
            if (empty($routeParams['id'])) {
                ResponseFormatter::badRequest('Missing user ID in URL');
                exit;
            }
            
            $controller->delete(['id' => (int)$routeParams['id']]);
            break;

        default:
            ResponseFormatter::methodNotAllowed("Method {$requestMethod} not allowed");
            exit;
    }
    
} catch (Throwable $e) {
    Logger::error('Users Route Error: ' . $e->getMessage(), [
        'method' => $requestMethod,
        'params' => $routeParams,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    ResponseFormatter::serverError(
        defined('APP_DEBUG') && APP_DEBUG 
            ? $e->getMessage() 
            : 'Error processing request'
    );
    exit;
}
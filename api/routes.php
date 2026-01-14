<?php
/**
 * Unified API Router
 * - Web / Admin / Mobile
 * - Shared hosting compatible
 * - No framework required
 */

/* ===============================
 * 1) BOOTSTRAP CONTEXT
 * =============================== */
require_once __DIR__ . '/bootstrap_admin_context.php';

/* ===============================
 * 2) BASIC HEADERS
 * =============================== */
header('X-Powered-By: Unified-PHP-API');

/* ===============================
 * 3) REQUEST INFO
 * =============================== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

/* Remove /api prefix safely */
$basePath = '/api';
$path = $uri;

if (strpos($uri, $basePath) === 0) {
    $path = substr($uri, strlen($basePath));
}

$path = '/' . trim($path, '/');
$path = $path === '/' ? '/' : $path;

/* ===============================
 * 4) RESPONSE HELPERS
 * =============================== */
function jsonResponse($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse(string $message, int $status = 400, array $extra = []): void {
    jsonResponse(array_merge([
        'status'  => 'error',
        'message' => $message,
    ], $extra), $status);
}

/* ===============================
 * 5) CONTAINER (SHARED CONTEXT)
 * =============================== */
$container = [
    'db'      => $GLOBALS['ADMIN_DB']   ?? null,
    'user'    => $GLOBALS['ADMIN_USER'] ?? null,
    'session' => &$_SESSION,
    'method'  => $method,
    'path'    => $path,
];

/* ===============================
 * 6) ROUTES DEFINITIONS
 * =============================== */
$routes = [

    // -------- Health Check --------
    [
        'method' => 'GET',
        'pattern' => '#^/$#',
        'handler' => function ($c) {
            jsonResponse([
                'status' => 'ok',
                'time'   => date('c'),
                'user'   => $c['user'] ? $c['user']['username'] : null,
            ]);
        }
    ],

    // -------- Example: Themes --------
    [
        'method' => 'GET',
        'pattern' => '#^/themes$#',
        'handler' => function ($c) {
            require_once __DIR__ . '/controllers/ThemeController.php';
            ThemeController_index($c);
        }
    ],

    // -------- Example: Login --------
    [
        'method' => 'POST',
        'pattern' => '#^/auth/login$#',
        'handler' => function ($c) {
            require_once __DIR__ . '/controllers/AuthController.php';
            AuthController_login($c);
        }
    ],

    // -------- Products Routes --------
    [
        'method'  => 'GET',
        'pattern' => '#^/products/?$#',
        'handler' => function ($c) {
            require_once __DIR__ . '/routes/products.php';
        }
    ],
    [
        'method'  => 'POST',
        'pattern' => '#^/products/?$#',
        'handler' => function ($c) {
            require_once __DIR__ . '/routes/products.php';
        }
    ],
    [
        'method'  => 'GET',
        'pattern' => '#^/products/(.+)$#',
        'handler' => function ($c, $id) {
            $_SERVER['PRODUCT_ID_FROM_ROUTE'] = $id;
            require_once __DIR__ . '/routes/products.php';
        }
    ],
    [
        'method'  => 'POST',
        'pattern' => '#^/products/(.+)$#',
        'handler' => function ($c, $id) {
            $_SERVER['PRODUCT_ID_FROM_ROUTE'] = $id;
            require_once __DIR__ . '/routes/products.php';
        }
    ],
    [
        'method'  => 'PUT',
        'pattern' => '#^/products/(.+)$#',
        'handler' => function ($c, $id) {
            $_SERVER['PRODUCT_ID_FROM_ROUTE'] = $id;
            require_once __DIR__ . '/routes/products.php';
        }
    ],
    [
        'method'  => 'DELETE',
        'pattern' => '#^/products/(.+)$#',
        'handler' => function ($c, $id) {
            $_SERVER['PRODUCT_ID_FROM_ROUTE'] = $id;
            require_once __DIR__ . '/routes/products.php';
        }
    ],
    
    // -------- Product Meta (categories, brands, attributes) --------
    [
        'method'  => 'GET',
        'pattern' => '#^/product_meta$#',
        'handler' => function ($c) {
            require_once __DIR__ . '/product_meta.php';
        }
    ],

    [
    'method'  => 'GET',
    'pattern' => '#^/home$#',
    'handler' => function ($c) {
        require_once __DIR__ . '/controllers/HomeController.php';
        HomeController_index($c);
    }
],
    
    
   // -------- Public UI Bootstrap --------
[
    'method'  => 'GET',
    'pattern' => '#^/bootstrap_public_ui$#',
    'handler' => function ($c) {
        require_once __DIR__ . '/bootstrap_public_ui.php';

        if (isset($GLOBALS['PUBLIC_UI'])) {
            jsonResponse($GLOBALS['PUBLIC_UI']);
        }

        errorResponse('Public UI bootstrap failed', 500);
    }
],
 
    
    
    
    
    
    
    
    // -------- Legacy / Independent Handler --------
    [
        'method' => 'GET',
        'pattern' => '#^/independent-drivers$#',
        'handler' => function ($c) {
            // Legacy compatibility
            global $db;
            $db = $c['db'];

            require_once __DIR__ . '/../admin/fragments/IndependentDriver.php';
        }
    ],

];

/* ===============================
 * 7) ROUTER EXECUTION
 * =============================== */
try {

    foreach ($routes as $route) {

        if (
            $method === $route['method'] &&
            preg_match($route['pattern'], $path, $matches)
        ) {
            array_shift($matches); // remove full match
            $route['handler']($container, ...$matches);
            exit;
        }
    }

    // No route matched
    errorResponse('Route not found', 404, [
        'method' => $method,
        'path'   => $path,
    ]);

} catch (Throwable $e) {

    // Log error safely
    $logDir = __DIR__ . '/error_log.txt';
    @file_put_contents(
        $logDir,
        '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    errorResponse('Internal server error', 500);
}

<?php
declare(strict_types=1);

/**
 * QOOQZ – Public API Routes
 * ----------------------------------
 * جميع المسارات العامة (Web + Mobile)
 * هذا الملف يُحمَّل داخل api/index.php
 */

/**
 * ملاحظة مهمة:
 * ملف index.php يعرّف المتغير $routes = [];
 * لذلك نحن نضيف عليه فقط
 */

if (!isset($routes) || !is_array($routes)) {
    $routes = [];
}

/* =========================================================
 * HEALTH CHECK
 * GET /api/
 * ========================================================= */
$routes[] = [
    'method'  => 'GET',
    'pattern' => '#^/$#',
    'handler' => function (): void {
        echo json_encode([
            'status'  => 'ok',
            'service' => 'QOOQZ Public API',
            'time'    => date('c')
        ], JSON_UNESCAPED_UNICODE);
    }
];

/* =========================================================
 * HOME
 * GET /api/home
 * ========================================================= */
$routes[] = [
    'method'  => 'GET',
    'pattern' => '#^/home$#',
    'handler' => function (): void {

        require_once __DIR__ . '/../controllers/HomeController.php';

        $controller = new HomeController();
        $controller->index();
    }
];

/* =========================================================
 * OPTIONAL – UI BOOTSTRAP (DEBUG / MOBILE)
 * GET /api/ui
 * ========================================================= */
$routes[] = [
    'method'  => 'GET',
    'pattern' => '#^/ui$#',
    'handler' => function (): void {

        if (isset($GLOBALS['PUBLIC_UI'])) {
            echo json_encode([
                'status' => 'ok',
                'ui'     => $GLOBALS['PUBLIC_UI']
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Public UI not initialized'
        ], JSON_UNESCAPED_UNICODE);
    }
];

/* =========================================================
 * 404 FALLBACK (اختياري – للوضوح)
 * ========================================================= */
/*
$routes[] = [
    'method'  => 'GET',
    'pattern' => '#.*#',
    'handler' => function (): void {
        http_response_code(404);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Route not found'
        ]);
    }
];
*/

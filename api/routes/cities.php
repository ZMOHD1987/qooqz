<?php
declare(strict_types=1);

/**
 * API Route: Cities - WITH FULL TRANSLATION SUPPORT
 *
 * Supports:
 *  - GET  /api/cities                          -> list (supports filters via query: id, country_id, name, language, page, per_page)
 *  - GET  /api/cities/{identifier}             -> get single by id|name (identifier in path)
 *  - POST /api/cities                          -> create (JSON body)
 *  - PUT  /api/cities                          -> update (JSON body with id)
 *  - DELETE /api/cities                        -> delete (JSON body with id)
 *
 * Note: returns translation name when ?language=xx is provided (or Accept-Language header fallback).
 */

$baseDir = dirname(__DIR__);

// ===== تحميل bootstrap =====
require_once $baseDir . '/bootstrap.php';

// ===== تحميل ResponseFormatter =====
require_once $baseDir . '/shared/core/ResponseFormatter.php';

// ===== تحميل safe_helpers =====
require_once $baseDir . '/shared/helpers/safe_helpers.php';

// ===== تحميل قاعدة البيانات =====
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات cities =====
require_once API_VERSION_PATH . '/models/cities/repositories/PdoCitiesRepository.php';
require_once API_VERSION_PATH . '/models/cities/validators/CitiesValidator.php';
require_once API_VERSION_PATH . '/models/cities/services/CitiesService.php';
require_once API_VERSION_PATH . '/models/cities/controllers/CitiesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// إنشاء الاعتمادات
$repo      = new PdoCitiesRepository($pdo);
$validator = new CitiesValidator();
$service   = new CitiesService($repo, $validator);
$controller = new CitiesController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    // method override support
    if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }

    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    // determine path suffix after /api/cities
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $uriPath = explode('?', $requestUri, 2)[0];
    $routeBase = '/api/cities';
    $after = '';
    $pos = strpos($uriPath, $routeBase);
    if ($pos !== false) {
        $after = substr($uriPath, $pos + strlen($routeBase));
    } else {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName && strpos($uriPath, $scriptName) === 0) {
            $after = substr($uriPath, strlen($scriptName));
        }
    }
    $after = trim($after, "/ \t\n\r\0\x0B");
    $segments = $after === '' ? [] : explode('/', $after);

    // ✅ إصلاح: دعم الترجمة مثل الدول تماماً
    // determine requested language: ?language=xx or Accept-Language
    $lang = $_GET['language'] ?? null; // ✅ استخدام 'language' بدلاً من 'lang'
    if (!$lang) {
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($accept) {
            // pick first language code (e.g. "en-US,en;q=0.9" -> "en")
            $parts = explode(',', $accept);
            if (!empty($parts[0])) {
                $lang = substr(trim($parts[0]), 0, 2);
            }
        }
    }

    switch ($method) {
        case 'GET':
            // path identifier given?
            if (!empty($segments[0])) {
                $identifier = urldecode($segments[0]);
                $item = $repo->findWithTranslation($identifier, $lang);
                if (!$item) {
                    ResponseFormatter::error('City not found', 404);
                    return;
                }
                ResponseFormatter::success($item);
                return;
            }

            // ✅ إصلاح: دعم فلاتر الترجمة مع 'language' parameter
            // query filters: id, country_id, name, page, per_page, language
            $filters = [];
            foreach (['id','country_id','name','page','per_page'] as $k) {
                if (isset($_GET[$k])) $filters[$k] = $_GET[$k];
            }
            if (!empty($_GET['language'])) $filters['lang'] = $_GET['language']; // ✅ استخدام 'language'
            elseif ($lang) $filters['lang'] = $lang;

            $result = $controller->list($filters);

            // ✅ إصلاح هيكل البيانات مثل الدول
            if (isset($result['data']) && is_array($result['data'])) {
                $responseData = $result['data'];
            } elseif (isset($result['items']) && is_array($result['items'])) {
                $responseData = $result['items'];
            } else {
                $responseData = $result;
            }

            $finalResponse = [
                'data' => $responseData,
                'meta' => $result['meta'] ?? [
                    'total' => count($responseData),
                    'page' => 1,
                    'per_page' => count($responseData),
                    'pages' => 1
                ]
            ];

            ResponseFormatter::success($finalResponse);
            return;

        case 'POST':
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
            ResponseFormatter::error('Method not allowed', 405);
            return;
    }
} catch (InvalidArgumentException $e) {
    safe_log('warning', 'Cities validation error', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'Cities runtime error', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('error', 'Cities route failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
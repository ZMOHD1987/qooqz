<?php
declare(strict_types=1);

/**
 * API Route: Countries - FIXED VERSION
 *
 * Supports:
 *  - GET  /api/countries                         -> list (supports filters via query: id, iso2, iso3, name, currency_code, lang, page, per_page)
 *  - GET  /api/countries/{identifier}            -> get single by id|iso2|iso3|name (identifier in path)
 *  - POST /api/countries                         -> create (JSON body)
 *  - PUT  /api/countries                         -> update (JSON body with id)
 *  - DELETE /api/countries                       -> delete (JSON body with id)
 *
 * Note: returns translation name when ?lang=xx is provided (or Accept-Language header fallback).
 */

$baseDir = dirname(__DIR__);

require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// Load model files
require_once API_VERSION_PATH . '/models/countries/repositories/PdoCountriesRepository.php';
require_once API_VERSION_PATH . '/models/countries/validators/CountriesValidator.php';
require_once API_VERSION_PATH . '/models/countries/services/CountriesService.php';
require_once API_VERSION_PATH . '/models/countries/controllers/CountriesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

$repo = new PdoCountriesRepository($pdo);
$validator = new CountriesValidator();
$service = new CountriesService($repo, $validator);
$controller = new CountriesController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    // method override support
    if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }

    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    // determine path suffix after /api/countries
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $uriPath = explode('?', $requestUri, 2)[0];
    $routeBase = '/api/countries';
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

    // determine requested language: ?lang=xx or Accept-Language
    $lang = $_GET['lang'] ?? null;
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
                $item = $repo->getByIdentifier($identifier, $lang);
                if (!$item) {
                    ResponseFormatter::error('Country not found', 404);
                    return;
                }
                ResponseFormatter::success($item);
                return;
            }

            // query filters: id, iso2, iso3, name, currency_code, page, per_page, lang
            $filters = [];
            foreach (['id','iso2','iso3','name','currency_code','page','per_page'] as $k) {
                if (isset($_GET[$k])) $filters[$k] = $_GET[$k];
            }
            if (!empty($_GET['lang'])) $filters['lang'] = $_GET['lang'];
            elseif ($lang) $filters['lang'] = $lang;

            $result = $controller->list($filters);
            
            // ✅ إصلاح: التأكد من أن البيانات ترجع بالشكل الصحيح
            if (isset($result['data']) && is_array($result['data'])) {
                // البيانات موجودة في result.data
                $responseData = $result['data'];
            } elseif (isset($result['items']) && is_array($result['items'])) {
                // البيانات موجودة في result.items
                $responseData = $result['items'];
            } else {
                // البيانات هي result نفسه
                $responseData = $result;
            }
            
            // التأكد من أن الـ response يحتوي على data و meta
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
    safe_log('warning', 'Countries validation error', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'Countries runtime error', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('error', 'Countries route failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
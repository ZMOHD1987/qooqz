<?php
declare(strict_types=1);

// api/routes/countries.php

// ===== مسار api =====
$baseDir = dirname(__DIR__);

// ===== تحميل bootstrap =====
require_once $baseDir . '/bootstrap.php';

// ===== تحميل ResponseFormatter =====
require_once $baseDir . '/shared/core/ResponseFormatter.php';

// ===== تحميل safe_helpers =====
require_once $baseDir . '/shared/helpers/safe_helpers.php';

// ===== تحميل قاعدة البيانات =====
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات countries =====
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

// إنشاء الاعتمادات
$repo      = new PdoCountriesRepository($pdo);
$validator = new CountriesValidator();
$service   = new CountriesService($repo, $validator);
$controller = new CountriesController($service);

// توجيه الطلب
try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            if ($id) {
                ResponseFormatter::success(
                    $controller->show((int)$id)
                );
            } else {
                ResponseFormatter::success(
                    $controller->list()
                );
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ResponseFormatter::success(
                $controller->create($data)
            );
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ResponseFormatter::success(
                $controller->update($data)
            );
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $controller->delete($data);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('error', 'Countries route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}
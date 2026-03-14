<?php
declare(strict_types=1);

// api/routes/country_taxes.php

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

// ===== تحميل ملفات country_taxes =====
require_once API_VERSION_PATH . '/models/country_taxes/repositories/PdoCountryTaxesRepository.php';
require_once API_VERSION_PATH . '/models/country_taxes/validators/CountryTaxesValidator.php';
require_once API_VERSION_PATH . '/models/country_taxes/services/CountryTaxesService.php';
require_once API_VERSION_PATH . '/models/country_taxes/controllers/CountryTaxesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// إنشاء الاعتمادات
$repo      = new PdoCountryTaxesRepository($pdo);
$validator = new CountryTaxesValidator();
$service   = new CountryTaxesService($repo, $validator);
$controller = new CountryTaxesController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /country_taxes/by_country?country_id=1
    if ($method === 'GET' && str_contains($uri, '/country_taxes/by_country')) {
        $countryId = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
        if ($countryId <= 0) {
            throw new InvalidArgumentException('Valid country_id is required');
        }
        ResponseFormatter::success(
            $controller->getByCountry($countryId)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/country_taxes/by_tax_class')) {
        $taxClassId = isset($_GET['tax_class_id']) ? (int)$_GET['tax_class_id'] : 0;
        if ($taxClassId <= 0) {
            throw new InvalidArgumentException('Valid tax_class_id is required');
        }
        ResponseFormatter::success(
            $controller->getByTaxClass($taxClassId)
        );
    } elseif ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            ResponseFormatter::success(
                $controller->get($id)
            );
        } else {
            ResponseFormatter::success(
                $controller->list()
            );
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->create($data)
        );
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->update($data)
        );
    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller->delete($data);
        ResponseFormatter::success(['deleted' => true]);
    } else {
        ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('error', 'Country taxes route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}
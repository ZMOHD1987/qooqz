<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/products';
require_once $modelsPath . '/repositories/PdoProductTranslationsRepository.php';
require_once $modelsPath . '/services/ProductTranslationsService.php';
require_once $modelsPath . '/controllers/ProductTranslationsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) ResponseFormatter::error('Database not initialized', 500);

$repo = new PdoProductTranslationsRepository($pdo);
$service = new ProductTranslationsService($repo);
$controller = new ProductTranslationsController($service);

$user = $_SESSION['user'] ?? [];
$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$languageCode = $user['preferred_language'] ?? 'en';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $filters = $_GET ?? [];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
        $orderBy = $_GET['order_by'] ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';

        if ($id !== null) {
            $res = $controller->get($tenantId, $id, $languageCode);
            ResponseFormatter::success($res);
        } else {
            // When listing translations for a specific product, show ALL languages
            $langFilter = !empty($filters['product_id']) ? null : $languageCode;
            $res = $controller->list($tenantId, $langFilter, $limit, $offset, $filters, $orderBy, $orderDir);
            ResponseFormatter::success($res);
        }
        exit;
    }

    if ($method === 'POST') {
        $res = $controller->create($data);

        // Auto-sync SEO translation
        try {
            $prodId = $data['product_id'] ?? null;
            $lc = $data['language_code'] ?? null;
            if ($prodId && $lc) {
                SeoAutoManager::syncTranslation($pdo, 'product', (int)$prodId, $lc, [
                    'name'        => $data['name'] ?? '',
                    'description' => $data['description'] ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            // SEO sync failure should not break translation creation
        }

        ResponseFormatter::success($res, 'Created', 201);
        exit;
    }

    if ($method === 'PUT') {
        $res = $controller->update($data);

        // Auto-sync SEO translation
        try {
            $prodId = $data['product_id'] ?? null;
            $lc = $data['language_code'] ?? null;
            if ($prodId && $lc) {
                SeoAutoManager::syncTranslation($pdo, 'product', (int)$prodId, $lc, [
                    'name'        => $data['name'] ?? '',
                    'description' => $data['description'] ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            // SEO sync failure should not break translation update
        }

        ResponseFormatter::success($res, 'Updated');
        exit;
    }

    if ($method === 'DELETE') {
        $id = $data['id'] ?? null;
        if ($id === null) ResponseFormatter::error('ID required', 400);
        $res = $controller->delete((int)$id);
        ResponseFormatter::success($res, 'Deleted');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);
} catch(Throwable $e){
    safe_log('critical','product_translations.fatal',['error'=>$e->getMessage()]);
    $msg = ($e instanceof InvalidArgumentException) ? $e->getMessage() : 'Internal server error';
    ResponseFormatter::error($msg,500);
}
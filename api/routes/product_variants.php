<?php
declare(strict_types=1);

require_once __DIR__.'/../bootstrap.php';
require_once __DIR__.'/../shared/core/ResponseFormatter.php';
require_once __DIR__.'/../shared/config/db.php';

$variantsPath = API_VERSION_PATH.'/models/product_variants';
require_once $variantsPath.'/repositories/PdoProductVariantsRepository.php';
require_once $variantsPath.'/validators/ProductVariantValidator.php';
require_once $variantsPath.'/services/ProductVariantService.php';
require_once $variantsPath.'/controllers/ProductVariantController.php';

if(session_status()===PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if(!$pdo instanceof PDO) ResponseFormatter::error('Database not initialized',500);

$repo = new PdoProductVariantsRepository($pdo);
$validator = new ProductVariantValidator();
$service = new ProductVariantService($repo,$validator);
$controller = new ProductVariantController($service);

$user = $_SESSION['user'] ?? [];
$roles = $user['roles'] ?? ($_SESSION['roles'] ?? []);
$isSuperAdmin = in_array('super_admin',$roles,true);
$sessionTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : $sessionTenantId;
if(!$isSuperAdmin && ($tenantId===null || $tenantId !== $sessionTenantId)) {
    ResponseFormatter::error('Unauthorized',403);
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw,true) : [];

    $filters = $_GET;
    $limit = isset($filters['limit']) ? (int)$filters['limit'] : null;
    $offset = isset($filters['offset']) ? (int)$filters['offset'] : null;
    $orderBy = $filters['order_by'] ?? 'id';
    $orderDir = $filters['order_dir'] ?? 'DESC';
    $languageCode = $_GET['language_code'] ?? null;

    switch($method){
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            http_response_code(204);
            exit;

        case 'GET':
            if(!empty($_GET['id'])){
                $item = $controller->listWithTranslations((int)$tenantId, $languageCode, null, null, ['id'=>$_GET['id']]);
                ResponseFormatter::success($item ?? []);
            } elseif(!empty($_GET['variant_id']) && !empty($_GET['translations'])){
                $translations = $controller->getTranslations((int)$_GET['variant_id']);
                ResponseFormatter::success($translations);
            } else {
                $items = $controller->listWithTranslations((int)$tenantId, $languageCode, $limit, $offset, $filters, $orderBy, $orderDir);
                ResponseFormatter::success(['items'=>$items]);
            }
            break;

        case 'POST':
        case 'PUT':
            if(!empty($data['translation'])){
                $controller->saveTranslation((int)$data['variant_id'], $data['translation']['language_code'], $data['translation']['name']);
                ResponseFormatter::success(['saved'=>true]);
            } else {
                $id = $controller->createOrUpdate((int)$tenantId, $data);
                ResponseFormatter::success(['id'=>$id]);
            }
            break;

        case 'DELETE':
            if(empty($data['id'])) ResponseFormatter::error('Missing id',400);
            $deleted = $controller->delete((int)$tenantId,(int)$data['id']);
            ResponseFormatter::success(['deleted'=>$deleted]);
            break;

        default:
            ResponseFormatter::error('Method not allowed',405);
    }

}catch(Throwable $e){
    ResponseFormatter::error($e->getMessage(),500);
}
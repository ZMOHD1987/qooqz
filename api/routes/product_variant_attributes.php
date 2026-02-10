<?php
declare(strict_types=1);

require_once __DIR__.'/../bootstrap.php';
require_once __DIR__.'/../shared/core/ResponseFormatter.php';
require_once __DIR__.'/../shared/config/db.php';

$path = API_VERSION_PATH.'/models/product_variants';
require_once $path.'/repositories/PdoProductVariantAttributesRepository.php';
require_once $path.'/validators/ProductVariantAttributesValidator.php';
require_once $path.'/services/ProductVariantAttributesService.php';
require_once $path.'/controllers/ProductVariantAttributesController.php';

if(session_status()===PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if(!$pdo instanceof PDO) ResponseFormatter::error('Database not initialized',500);

$repo = new PdoProductVariantAttributesRepository($pdo);
$validator = new ProductVariantAttributesValidator();
$service = new ProductVariantAttributesService($repo,$validator);
$controller = new ProductVariantAttributesController($service);

$user = $_SESSION['user'] ?? [];
$roles = $user['roles'] ?? ($_SESSION['roles'] ?? []);
$isSuperAdmin = in_array('super_admin',$roles,true);
$sessionTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : $sessionTenantId;
if(!$isSuperAdmin && ($tenantId===null || $tenantId !== $sessionTenantId)) {
    ResponseFormatter::error('Unauthorized',403);
}

try{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw,true) : [];

    $filters = $_GET;
    $limit = isset($filters['limit'])?(int)$filters['limit']:null;
    $offset = isset($filters['offset'])?(int)$filters['offset']:null;
    $orderBy = $filters['order_by'] ?? 'id';
    $orderDir = $filters['order_dir'] ?? 'DESC';

    switch($method){
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            http_response_code(204);
            exit;

        case 'GET':
            if(!empty($_GET['id'])){
                $item = $controller->get((int)$tenantId,(int)$_GET['id']);
                ResponseFormatter::success($item ?? []);
            } else {
                $items = $controller->list((int)$tenantId,$limit,$offset,$filters,$orderBy,$orderDir);
                $total = $controller->count((int)$tenantId,$filters);
                ResponseFormatter::success(['items'=>$items,'total'=>$total]);
            }
            break;

        case 'POST':
            $id = $controller->create((int)$tenantId,$data);
            ResponseFormatter::success(['id'=>$id],'Created',201);
            break;

        case 'PUT':
            $id = $controller->update((int)$tenantId,$data);
            ResponseFormatter::success(['id'=>$id],'Updated');
            break;

        case 'DELETE':
            if(empty($data['id'])) ResponseFormatter::error('Missing id',400);
            $deleted = $controller->delete((int)$tenantId,(int)$data['id']);
            ResponseFormatter::success(['deleted'=>$deleted],'Deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed',405);
    }

}catch(Throwable $e){
    ResponseFormatter::error($e->getMessage(),500);
}
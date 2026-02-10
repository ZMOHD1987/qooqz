<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once API_VERSION_PATH . '/models/entities/repositories/PdoEntityTypesRepository.php';
require_once API_VERSION_PATH . '/models/entities/validators/EntityTypesValidator.php';
require_once API_VERSION_PATH . '/models/entities/services/EntityTypesService.php';
require_once API_VERSION_PATH . '/models/entities/controllers/EntityTypesController.php';

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoEntityTypesRepository($pdo);
$service = new EntityTypesService($repo);
$controller = new EntityTypesController($service);
$validator = new EntityTypesValidator();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(1000, max(1, (int)($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $orderBy  = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'code' => $_GET['code'] ?? null,
        'name' => $_GET['name'] ?? null
    ];

    switch ($method) {
        case 'GET':
            if (!empty($_GET['id'])) {
                ResponseFormatter::success(
                    $controller->get((int)$_GET['id'])
                );
            } else {
                $res = $controller->list($limit,$offset,$filters,$orderBy,$orderDir);
                ResponseFormatter::success([
                    'items' => $res['items'],
                    'meta' => [
                        'total' => $res['total'],
                        'page' => $page,
                        'per_page' => $limit
                    ]
                ]);
            }
            break;

        case 'POST':
            $validator->validate($data);
            $id = $controller->save($data);
            ResponseFormatter::success(['id'=>$id],'Created',201);
            break;

        case 'PUT':
            $validator->validate($data,true);
            $id = $controller->save($data);
            ResponseFormatter::success(['id'=>$id],'Updated');
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing ID',400);
            }
            ResponseFormatter::success([
                'deleted'=>$controller->delete((int)$data['id'])
            ]);
            break;

        default:
            ResponseFormatter::error('Method not allowed',405);
    }
} catch (Throwable $e) {
    safe_log('error','entity_types', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(),500);
}

<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);

require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

require_once API_VERSION_PATH . '/models/images/repositories/PdoImageTypesRepository.php';
require_once API_VERSION_PATH . '/models/images/validators/ImageTypesValidator.php';
require_once API_VERSION_PATH . '/models/images/services/ImageTypesService.php';
require_once API_VERSION_PATH . '/models/images/controllers/ImageTypesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

$repo = new PdoImageTypesRepository($pdo);
$service = new ImageTypesService($repo);
$controller = new ImageTypesController($service);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];

    // ===== Resolve by CODE =====
    if ($method === 'GET' && str_contains($uri, '/image-types/resolve')) {
        $code = $_GET['code'] ?? '';
        if ($code === '') {
            throw new InvalidArgumentException('Code is required');
        }

        ResponseFormatter::success(
            $controller->resolve($code)
        );
        return;
    }

    // ===== GET list or single =====
    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        ResponseFormatter::success(
            $id ? $controller->get($id) : $controller->list()
        );
        return;
    }

    // ===== CREATE =====
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->create($data)
        );
        return;
    }

    // ===== UPDATE =====
    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        ResponseFormatter::success(
            $controller->update((int)$data['id'], $data)
        );
        return;
    }

    // ===== DELETE =====
    if ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $controller->delete((int)$data['id']);
        ResponseFormatter::success(['deleted' => true]);
        return;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);

} catch (Throwable $e) {
    safe_log('error', 'ImageTypes route failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error('Internal server error', 500);
}

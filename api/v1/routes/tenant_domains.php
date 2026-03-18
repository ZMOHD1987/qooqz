<?php
declare(strict_types=1);

/**
 * api/v1/routes/tenant_domains.php
 *
 * CRUD + lifecycle endpoints for the tenant_domains table.
 *
 * Endpoints
 * ─────────
 * GET    /api/tenant_domains?tenant_id=N               List domains for a tenant
 * GET    /api/tenant_domains/{id}                       Get single domain
 * POST   /api/tenant_domains                            Create domain
 * PUT    /api/tenant_domains/{id}                       Update domain
 * DELETE /api/tenant_domains/{id}                       Delete domain
 * POST   /api/tenant_domains/{id}/verify                Mark as verified
 * POST   /api/tenant_domains/{id}/ssl                   Update SSL status
 */

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/tenant_domains';
require_once $modelsPath . '/Contracts/TenantDomainsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoTenantDomainsRepository.php';
require_once $modelsPath . '/validators/TenantDomainsValidator.php';
require_once $modelsPath . '/services/TenantDomainsService.php';
require_once $modelsPath . '/controllers/TenantDomainsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

$repo       = new PdoTenantDomainsRepository($pdo);
$service    = new TenantDomainsService($repo);
$controller = new TenantDomainsController($service);

try {
    $method      = $_SERVER['REQUEST_METHOD'];
    $uri         = $_SERVER['REQUEST_URI'] ?? '';
    $path        = parse_url($uri, PHP_URL_PATH) ?? '';
    parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $qp);

    // Extract numeric ID and optional sub-action from path
    // e.g. /api/tenant_domains/42/verify  → id=42, action='verify'
    $id     = null;
    $action = null;
    $parts  = explode('/', trim($path, '/'));
    $idx    = array_search('tenant_domains', $parts);
    if ($idx !== false && isset($parts[$idx + 1]) && is_numeric($parts[$idx + 1])) {
        $id = (int)$parts[$idx + 1];
        $action = $parts[$idx + 2] ?? null; // 'verify' | 'ssl' | null
    }

    if ($method === 'OPTIONS') {
        http_response_code(204);
        return;
    }

    // ── POST /{id}/verify ────────────────────────────────────
    if ($method === 'POST' && $id && $action === 'verify') {
        ResponseFormatter::success(
            $controller->markVerified($id),
            'Domain marked as verified'
        );
        return;
    }

    // ── POST /{id}/ssl ───────────────────────────────────────
    if ($method === 'POST' && $id && $action === 'ssl') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->updateSslStatus($id, $data),
            'SSL status updated'
        );
        return;
    }

    // ── GET list ─────────────────────────────────────────────
    if ($method === 'GET' && !$id) {
        if (empty($qp['tenant_id'])) {
            throw new InvalidArgumentException('tenant_id query parameter is required');
        }
        $tenantId = (int)$qp['tenant_id'];
        $limit    = isset($qp['limit'])  ? min(200, max(1, (int)$qp['limit']))  : 50;
        $offset   = isset($qp['offset']) ? max(0, (int)$qp['offset'])           : 0;

        $filters = [];
        foreach (['type', 'ssl_status', 'is_verified', 'search'] as $f) {
            if (isset($qp[$f]) && $qp[$f] !== '') $filters[$f] = $qp[$f];
        }

        ResponseFormatter::success($controller->list($tenantId, $filters, $limit, $offset));
        return;
    }

    // ── GET single ───────────────────────────────────────────
    if ($method === 'GET' && $id) {
        ResponseFormatter::success($controller->get($id));
        return;
    }

    // ── POST create ──────────────────────────────────────────
    if ($method === 'POST' && !$id) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        ResponseFormatter::success($controller->create($data), 'Domain created', 201);
        return;
    }

    // ── PUT / PATCH update ───────────────────────────────────
    if (in_array($method, ['PUT', 'PATCH'], true) && $id) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        ResponseFormatter::success($controller->update($id, $data), 'Domain updated');
        return;
    }

    // ── DELETE ───────────────────────────────────────────────
    if ($method === 'DELETE' && $id) {
        $controller->delete($id);
        ResponseFormatter::success(['deleted' => true], 'Domain deleted');
        return;
    }

    ResponseFormatter::error('Method or route not allowed', 405, [
        'endpoints' => [
            'GET    /api/tenant_domains?tenant_id=N   – List domains',
            'GET    /api/tenant_domains/{id}          – Get domain',
            'POST   /api/tenant_domains               – Create domain',
            'PUT    /api/tenant_domains/{id}          – Update domain',
            'DELETE /api/tenant_domains/{id}          – Delete domain',
            'POST   /api/tenant_domains/{id}/verify   – Mark verified',
            'POST   /api/tenant_domains/{id}/ssl      – Update SSL status',
        ],
    ]);

} catch (InvalidArgumentException $e) {
    $decoded = json_decode($e->getMessage(), true);
    if (is_array($decoded)) {
        ResponseFormatter::error('Validation failed', 422, $decoded);
    } else {
        ResponseFormatter::error($e->getMessage(), 422);
    }
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    safe_log('error', 'tenant_domains route failed', [
        'error'  => $e->getMessage(),
        'file'   => $e->getFile(),
        'line'   => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}

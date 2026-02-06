<?php
declare(strict_types=1);

// api/routes/tenants.php

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

// ===== تحميل ملفات tenants =====
require_once API_VERSION_PATH . '/models/tenants/repositories/PdoTenantsRepository.php';
require_once API_VERSION_PATH . '/models/tenants/validators/TenantsValidator.php';
require_once API_VERSION_PATH . '/models/tenants/services/TenantsService.php';
require_once API_VERSION_PATH . '/models/tenants/controllers/TenantsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// إنشاء الاعتمادات
$repo      = new PdoTenantsRepository($pdo);
$validator = new TenantsValidator();
$service   = new TenantsService($repo, $validator);
$controller = new TenantsController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($uri, PHP_URL_PATH);
    $query = parse_url($uri, PHP_URL_QUERY);
    parse_str($query ?? '', $queryParams);

    // استخراج ID من المسار: /api/routes/tenants/123
    $id = null;
    $segments = explode('/', trim($path, '/'));
    $tenantsIndex = array_search('tenants', $segments);
    if ($tenantsIndex !== false && isset($segments[$tenantsIndex + 1])) {
        $possibleId = $segments[$tenantsIndex + 1];
        if (is_numeric($possibleId)) {
            $id = (int)$possibleId;
        }
    }

    // ════════════════════════════════════════════════════════════
    // GET /tenants - قائمة مع فلاتر و pagination
    // ════════════════════════════════════════════════════════════
    if ($method === 'GET' && !$id && !isset($queryParams['action'])) {
        $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
        $perPage = isset($queryParams['per_page']) ? min(100, max(1, (int)$queryParams['per_page'])) : 10;
        $offset = ($page - 1) * $perPage;

        $filters = [];
        if (!empty($queryParams['search'])) {
            $filters['search'] = trim($queryParams['search']);
        }
        if (!empty($queryParams['status'])) {
            $filters['status'] = trim($queryParams['status']);
        }
        if (!empty($queryParams['owner_user_id'])) {
            $filters['owner_user_id'] = (int)$queryParams['owner_user_id'];
        }

        $items = $controller->list($perPage, $offset, $filters);
        $total = $controller->count($filters);

        ResponseFormatter::success([
            'items' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'last_page' => ceil($total / $perPage),
                'filters' => $filters
            ]
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // GET /tenants/{id} - عرض واحد
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'GET' && $id) {
        ResponseFormatter::success(
            $controller->get($id)
        );
    }

    // ════════════════════════════════════════════════════════════
    // GET /tenants?action=stats - إحصائيات
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'GET' && isset($queryParams['action']) && $queryParams['action'] === 'stats') {
        ResponseFormatter::success(
            $controller->getStats()
        );
    }

    // ════════════════════════════════════════════════════════════
    // GET /tenants/active - النشطة فقط
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'GET' && str_contains($path, '/tenants/active')) {
        ResponseFormatter::success(
            $controller->getActive()
        );
    }

    // ════════════════════════════════════════════════════════════
    // GET /tenants/by_domain?domain=example.com
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'GET' && str_contains($path, '/tenants/by_domain')) {
        $domain = $queryParams['domain'] ?? '';
        if (empty($domain)) {
            throw new InvalidArgumentException('Domain is required');
        }
        ResponseFormatter::success(
            $controller->getByDomain($domain)
        );
    }

    // ════════════════════════════════════════════════════════════
    // POST /tenants - إنشاء
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'POST' && !$id) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        ResponseFormatter::success(
            $controller->create($data),
            'Tenant created successfully',
            201
        );
    }

    // ════════════════════════════════════════════════════════════
    // PUT /tenants/{id} - تحديث
    // ════════════════════════════════════════════════════════════
    elseif (($method === 'PUT' || $method === 'PATCH') && $id) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        $data['id'] = $id;
        ResponseFormatter::success(
            $controller->update($data, $id),
            'Tenant updated successfully'
        );
    }

    // ════════════════════════════════════════════════════════════
    // DELETE /tenants/{id} - حذف
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'DELETE' && $id) {
        $controller->delete(['id' => $id, 'user_id' => $queryParams['user_id'] ?? null]);
        ResponseFormatter::success(
            ['deleted' => true],
            'Tenant deleted successfully'
        );
    }

    // ════════════════════════════════════════════════════════════
    // POST /tenants/activate - تفعيل متعدد
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'POST' && str_contains($path, '/tenants/activate')) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        ResponseFormatter::success(
            $controller->activate($data),
            'Tenants activated successfully'
        );
    }

    // ════════════════════════════════════════════════════════════
    // POST /tenants/suspend - تعليق متعدد
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'POST' && str_contains($path, '/tenants/suspend')) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        ResponseFormatter::success(
            $controller->suspend($data),
            'Tenants suspended successfully'
        );
    }

    // ════════════════════════════════════════════════════════════
    // POST /tenants?action=bulk-status - تحديث حالة متعدد
    // ════════════════════════════════════════════════════════════
    elseif ($method === 'POST' && isset($queryParams['action']) && $queryParams['action'] === 'bulk-status') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        ResponseFormatter::success(
            $controller->bulkUpdateStatus($data),
            'Bulk status update completed'
        );
    }

    // ════════════════════════════════════════════════════════════
    // Method not allowed
    // ════════════════════════════════════════════════════════════
    else {
        ResponseFormatter::error('Method not allowed', 405, [
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
            'available_endpoints' => [
                'GET /tenants - List with pagination and filters',
                'GET /tenants/{id} - Get single tenant',
                'GET /tenants?action=stats - Get statistics',
                'GET /tenants/active - Get active tenants',
                'GET /tenants/by_domain?domain=example.com - Get by domain',
                'POST /tenants - Create new tenant',
                'PUT /tenants/{id} - Update tenant',
                'DELETE /tenants/{id} - Delete tenant',
                'POST /tenants/activate - Activate multiple tenants',
                'POST /tenants/suspend - Suspend multiple tenants',
                'POST /tenants?action=bulk-status - Bulk status update'
            ]
        ]);
    }

} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    safe_log('error', 'Tenants route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'uri'   => $uri,
        'method' => $method
    ]);

    ResponseFormatter::error('Internal server error', 500);
}
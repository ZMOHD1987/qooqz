<?php
declare(strict_types=1);

// ============================================================
// Bootstrap — نفس النمط المستخدم في كل routes المشروع
// ============================================================
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';

// ============================================================
// Dependencies — Interface أولاً دائماً
// ============================================================
require_once API_VERSION_PATH . '/models/homepage_sections/Contracts/HomepageSectionsRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/homepage_sections/repositories/PdoHomepageSectionsRepository.php';
require_once API_VERSION_PATH . '/models/homepage_sections/validators/HomepageSectionsValidator.php';
require_once API_VERSION_PATH . '/models/homepage_sections/services/HomepageSectionsService.php';
require_once API_VERSION_PATH . '/models/homepage_sections/controllers/HomepageSectionsController.php';

// ============================================================
// Database
// ============================================================
/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Service unavailable', 503);
    exit;
}

// ============================================================
// Tenant + User
// ============================================================
$tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
$userId   = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

if ($tenantId === 0) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

// ============================================================
// Wiring
// ============================================================
$controller = new HomepageSectionsController(
    new HomepageSectionsService(
        new PdoHomepageSectionsRepository($pdo),
        new HomepageSectionsValidator()
    )
);

// ============================================================
// Request parsing
// ============================================================
$method   = $_SERVER['REQUEST_METHOD'];
$uriPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', trim($uriPath, '/'))));

// استخراج ID و sub-route من URI
// مثال: /homepage_sections/42/translations → id=42, subRoute='translations'
$id       = null;
$subRoute = null;

foreach ($segments as $i => $seg) {
    if (ctype_digit($seg) && (int) $seg > 0) {
        $id       = (int) $seg;
        $subRoute = $segments[$i + 1] ?? null;
        break;
    }
    if (in_array($seg, ['active', 'types', 'languages'], true) && $id === null) {
        $subRoute = $seg;
    }
}

// ============================================================
// Dispatch
// ============================================================
try {

    switch ($method) {

        // ----------------------------------------------------
        // GET
        // ----------------------------------------------------
        case 'GET':

            // GET /homepage_sections/languages
            if ($subRoute === 'languages') {
                ResponseFormatter::success($controller->languages());
                break;
            }

            // GET /homepage_sections/active
            if ($subRoute === 'active') {
                ResponseFormatter::success($controller->getActive($tenantId, $userId));
                break;
            }

            // GET /homepage_sections/types
            if ($subRoute === 'types') {
                ResponseFormatter::success($controller->sectionTypes($tenantId));
                break;
            }

            // GET /homepage_sections/{id}/translations
            if ($id !== null && $subRoute === 'translations') {
                ResponseFormatter::success($controller->translations($tenantId, $id));
                break;
            }

            // GET /homepage_sections/{id}
            if ($id !== null) {
                ResponseFormatter::success($controller->get($tenantId, $id, $userId));
                break;
            }

            // GET /homepage_sections
            ResponseFormatter::success($controller->list($tenantId, $userId));
            break;

        // ----------------------------------------------------
        // POST
        // ----------------------------------------------------
        case 'POST':
            $data = json_decode((string) file_get_contents('php://input'), true) ?? [];

            // POST /homepage_sections/{id}/translations
            if ($id !== null && $subRoute === 'translations') {
                $translations = $data['translations'] ?? $data;
                ResponseFormatter::success(
                    $controller->saveTranslations($tenantId, $id, $translations, $userId),
                    'Translations saved successfully'
                );
                break;
            }

            // POST /homepage_sections
            ResponseFormatter::success(
                $controller->create($tenantId, $data, $userId),
                'Section created successfully',
                201
            );
            break;

        // ----------------------------------------------------
        // PUT
        // ----------------------------------------------------
        case 'PUT':
            $data = json_decode((string) file_get_contents('php://input'), true) ?? [];

            if ($id !== null && empty($data['id'])) {
                $data['id'] = $id;
            }

            if (empty($data['id'])) {
                ResponseFormatter::error('Section ID is required for update.', 422);
                break;
            }

            ResponseFormatter::success(
                $controller->update($tenantId, $data, $userId),
                'Section updated successfully'
            );
            break;

        // ----------------------------------------------------
        // DELETE
        // ----------------------------------------------------
        case 'DELETE':

            if ($id !== null) {
                $controller->delete($tenantId, $id, $userId);
                ResponseFormatter::success(['deleted' => true], 'Section deleted successfully');
                break;
            }

            $input    = json_decode((string) file_get_contents('php://input'), true) ?? [];
            $deleteId = isset($input['id']) && ctype_digit((string) $input['id'])
                            ? (int) $input['id'] : null;

            if ($deleteId === null) {
                ResponseFormatter::error('Section ID is required.', 400);
                break;
            }

            $controller->delete($tenantId, $deleteId, $userId);
            ResponseFormatter::success(['deleted' => true], 'Section deleted successfully');
            break;

        // ----------------------------------------------------
        default:
            ResponseFormatter::error('Method not allowed.', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('warning', '[HomepageSections] Validation failed', [
        'tenant_id' => $tenantId,
        'method'    => $method,
        'error'     => $e->getMessage(),
    ]);
    $decoded = json_decode($e->getMessage(), true);
    ResponseFormatter::error($decoded ?? $e->getMessage(), 422);

} catch (RuntimeException $e) {
    $code = in_array((int) $e->getCode(), [400, 404, 409, 422], true)
                ? (int) $e->getCode() : 400;
    ResponseFormatter::error($e->getMessage(), $code);

} catch (PDOException $e) {
    safe_log('error', '[HomepageSections] Database error', [
        'tenant_id' => $tenantId,
        'method'    => $method,
        'code'      => $e->getCode(),
        'error'     => $e->getMessage(),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
    ]);
    ResponseFormatter::error('A database error occurred. Please try again later.', 500);

} catch (Throwable $e) {
    safe_log('error', '[HomepageSections] Unexpected error', [
        'tenant_id' => $tenantId,
        'method'    => $method,
        'error'     => $e->getMessage(),
        'trace'     => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error('An unexpected error occurred. Please try again later.', 500);
}

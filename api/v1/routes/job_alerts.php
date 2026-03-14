<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobAlertsRepository.php';
require_once $modelsPath . '/validators/JobAlertsValidator.php';
require_once $modelsPath . '/services/JobAlertsService.php';
require_once $modelsPath . '/controllers/JobAlertsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoJobAlertsRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobAlertsValidator();
$service = new JobAlertsService($repo);
$controller = new JobAlertsController($service);

// ================================
// User authentication check
// ================================
$user = $_SESSION['user'] ?? [];
$userId = isset($_GET['user_id']) && is_numeric($_GET['user_id'])
    ? (int)$_GET['user_id']
    : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);

if ($userId === null) {
    ResponseFormatter::error('user_id required', 401);
    exit;
}

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'created_at';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters
    $filters = [];
    
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $filters['id'] = (int)$_GET['id'];
    }
    if (isset($_GET['job_type']) && $_GET['job_type'] !== '') {
        $filters['job_type'] = $_GET['job_type'];
    }
    if (isset($_GET['experience_level']) && $_GET['experience_level'] !== '') {
        $filters['experience_level'] = $_GET['experience_level'];
    }
    if (isset($_GET['country_id']) && is_numeric($_GET['country_id'])) {
        $filters['country_id'] = (int)$_GET['country_id'];
    }
    if (isset($_GET['city_id']) && is_numeric($_GET['city_id'])) {
        $filters['city_id'] = (int)$_GET['city_id'];
    }
    if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
        $filters['is_active'] = (int)$_GET['is_active'];
    }
    if (isset($_GET['frequency']) && $_GET['frequency'] !== '') {
        $filters['frequency'] = $_GET['frequency'];
    }
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $filters['search'] = $_GET['search'];
    }
    if (isset($_GET['salary_min']) && is_numeric($_GET['salary_min'])) {
        $filters['salary_min'] = (float)$_GET['salary_min'];
    }
    if (isset($_GET['salary_max']) && is_numeric($_GET['salary_max'])) {
        $filters['salary_max'] = (float)$_GET['salary_max'];
    }

    // Validate filters if not empty
    if (!empty($filters)) {
        $validator->validateFilters($filters);
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // ================================
            // Special endpoints
            // ================================

            // Get user statistics
            if (isset($_GET['statistics'])) {
                $stats = $controller->getStatistics($userId);
                ResponseFormatter::success($stats);
                break;
            }

            // Get due alerts for cron jobs
            if (isset($_GET['due_alerts'])) {
                $frequency = $_GET['frequency'] ?? 'daily';
                $dueAlerts = $controller->getDueAlerts($frequency);
                ResponseFormatter::success(['alerts' => $dueAlerts]);
                break;
            }

            // Check if user can create more alerts
            if (isset($_GET['can_create'])) {
                $maxAlerts = isset($_GET['max_alerts']) ? (int)$_GET['max_alerts'] : 10;
                $canCreate = $controller->canCreateAlert($userId, $maxAlerts);
                $activeCount = $controller->getActiveAlertsCount($userId);
                ResponseFormatter::success([
                    'can_create' => $canCreate,
                    'active_alerts' => $activeCount,
                    'max_alerts' => $maxAlerts
                ]);
                break;
            }

            // Get valid frequencies
            if (isset($_GET['valid_frequencies'])) {
                ResponseFormatter::success([
                    'frequencies' => $validator->getValidFrequencies()
                ]);
                break;
            }

            // Get valid job types
            if (isset($_GET['valid_job_types'])) {
                ResponseFormatter::success([
                    'job_types' => $validator->getValidJobTypes()
                ]);
                break;
            }

            // Get valid experience levels
            if (isset($_GET['valid_experience_levels'])) {
                ResponseFormatter::success([
                    'experience_levels' => $validator->getValidExperienceLevels()
                ]);
                break;
            }

            // Get single alert by ID
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get($userId, (int)$_GET['id']);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Alert not found', 404);
                }
                break;
            }

            // List all alerts with filters
            $result = $controller->list($userId, $limit, $offset, $filters, $orderBy, $orderDir);
            $total = $result['total'];
            ResponseFormatter::success([
                'items' => $result['items'],
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $limit,
                    'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => $total > 0 ? min($offset + $limit, $total) : 0
                ]
            ]);
            break;

        case 'POST':
            // Validate data
            $validator->validate($data, false);

            // Check if user can create more alerts
            $maxAlerts = 50; // يمكن تغييره حسب الخطة
            if (!$controller->canCreateAlert($userId, $maxAlerts)) {
                ResponseFormatter::error("Maximum alerts limit ({$maxAlerts}) reached", 400);
                exit;
            }

            $newId = $controller->create($userId, $data);
            ResponseFormatter::success(['id' => $newId], 'Alert created', 201);
            break;

        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('ID required', 400);
                exit;
            }

            // Validate data
            $validator->validate($data, true);

            $updatedId = $controller->update($userId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Alert updated');
            break;

        case 'PATCH':
            // Toggle alert active status
            if (isset($_GET['toggle_active'])) {
                if (empty($_GET['id'])) {
                    ResponseFormatter::error('ID required', 400);
                    exit;
                }
                $toggled = $controller->toggleActive($userId, (int)$_GET['id']);
                ResponseFormatter::success(['toggled' => $toggled], 'Status toggled');
                break;
            }

            // Batch update status
            if (isset($_GET['batch_update'])) {
                if (empty($data['alert_ids']) || !is_array($data['alert_ids'])) {
                    ResponseFormatter::error('alert_ids array required', 400);
                    exit;
                }
                if (!isset($data['is_active'])) {
                    ResponseFormatter::error('is_active required', 400);
                    exit;
                }
                
                $result = $controller->batchUpdateStatus(
                    $userId,
                    $data['alert_ids'],
                    (bool)$data['is_active']
                );
                ResponseFormatter::success($result, 'Batch update completed');
                break;
            }

            // Update last sent timestamp (for cron jobs)
            if (isset($_GET['update_last_sent'])) {
                if (empty($_GET['alert_id'])) {
                    ResponseFormatter::error('alert_id required', 400);
                    exit;
                }
                $updated = $controller->updateLastSent((int)$_GET['alert_id']);
                ResponseFormatter::success(['updated' => $updated], 'Last sent updated');
                break;
            }

            ResponseFormatter::error('Invalid PATCH request', 400);
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('ID required', 400);
                exit;
            }

            try {
                $deleted = $controller->delete($userId, (int)$data['id']);
                ResponseFormatter::success(['deleted' => $deleted], 'Alert deleted');
            } catch (RuntimeException $e) {
                ResponseFormatter::error($e->getMessage(), 400);
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_alerts.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_alerts.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'job_alerts.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}

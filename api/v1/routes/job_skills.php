<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobSkillsRepository.php';
require_once $modelsPath . '/validators/JobSkillsValidator.php';
require_once $modelsPath . '/services/JobSkillsService.php';
require_once $modelsPath . '/controllers/JobSkillsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoJobSkillsRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobSkillsValidator();
$service = new JobSkillsService($repo, $validator);
$controller = new JobSkillsController($service);

// ================================
// User authentication check (optional, adjust as needed)
// ================================
$userId = isset($_GET['user_id']) && is_numeric($_GET['user_id'])
    ? (int)$_GET['user_id']
    : null;

if ($userId === null) {
    // For job skills, user_id might not be required if it's admin-only or job-based
    // But keeping similar to alerts for consistency
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    if ($userId === null) {
        ResponseFormatter::error('user_id required', 401);
        exit;
    }
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
    $orderBy = $_GET['order_by'] ?? 'skill_name';
    $orderDir = $_GET['order_dir'] ?? 'ASC';

    // Collect filters
    $filters = [];
    
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $filters['id'] = (int)$_GET['id'];
    }
    if (isset($_GET['job_id']) && is_numeric($_GET['job_id'])) {
        $filters['job_id'] = (int)$_GET['job_id'];
    }
    if (isset($_GET['proficiency_level']) && $_GET['proficiency_level'] !== '') {
        $filters['proficiency_level'] = $_GET['proficiency_level'];
    }
    if (isset($_GET['is_required']) && $_GET['is_required'] !== '') {
        $filters['is_required'] = (int)$_GET['is_required'];
    }
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $filters['search'] = $_GET['search'];
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

            // Get proficiency levels
            if (isset($_GET['proficiency_levels'])) {
                ResponseFormatter::success([
                    'proficiency_levels' => $controller->getProficiencyLevels()
                ]);
                break;
            }

            // Get skills by job ID
            if (isset($_GET['job_id']) && is_numeric($_GET['job_id'])) {
                $requiredOnly = isset($_GET['required_only']) && $_GET['required_only'] == '1';
                $skills = $controller->getByJob((int)$_GET['job_id'], $requiredOnly);
                ResponseFormatter::success(['skills' => $skills]);
                break;
            }

            // Get single skill by ID
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id']);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Skill not found', 404);
                }
                break;
            }

            // List all skills with filters
            $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir);
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

            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Skill created', 201);
            break;

        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('ID required', 400);
                exit;
            }

            // Validate data
            $validator->validate($data, true);

            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Skill updated');
            break;

        case 'PATCH':
            // Bulk update skills for a job
            if (isset($_GET['bulk_update'])) {
                if (empty($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
                    ResponseFormatter::error('job_id required', 400);
                    exit;
                }
                if (empty($data['skills']) || !is_array($data['skills'])) {
                    ResponseFormatter::error('skills array required', 400);
                    exit;
                }
                
                $result = $controller->bulkUpdate((int)$_GET['job_id'], $data['skills']);
                ResponseFormatter::success(['updated' => $result], 'Skills updated');
                break;
            }

            // Duplicate skills from another job
            if (isset($_GET['duplicate_from_job'])) {
                if (empty($_GET['source_job_id']) || !is_numeric($_GET['source_job_id'])) {
                    ResponseFormatter::error('source_job_id required', 400);
                    exit;
                }
                if (empty($_GET['target_job_id']) || !is_numeric($_GET['target_job_id'])) {
                    ResponseFormatter::error('target_job_id required', 400);
                    exit;
                }
                
                $duplicated = $controller->duplicateFromJob(
                    (int)$_GET['source_job_id'], 
                    (int)$_GET['target_job_id']
                );
                ResponseFormatter::success(['duplicated' => $duplicated], 'Skills duplicated');
                break;
            }

            ResponseFormatter::error('Invalid PATCH request', 400);
            break;

        case 'DELETE':
            // Delete all skills for a job
            if (isset($_GET['delete_by_job'])) {
                if (empty($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
                    ResponseFormatter::error('job_id required', 400);
                    exit;
                }
                
                $deleted = $controller->deleteByJob((int)$_GET['job_id']);
                ResponseFormatter::success(['deleted' => $deleted], 'Skills deleted for job');
                break;
            }

            // Delete single skill
            if (empty($data['id'])) {
                ResponseFormatter::error('ID required', 400);
                exit;
            }

            try {
                $deleted = $controller->delete((int)$data['id']);
                ResponseFormatter::success(['deleted' => $deleted], 'Skill deleted');
            } catch (RuntimeException $e) {
                ResponseFormatter::error($e->getMessage(), 400);
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_skills.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_skills.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'job_skills.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}

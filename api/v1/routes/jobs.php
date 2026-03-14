<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobsRepository.php';
require_once $modelsPath . '/validators/JobsValidator.php';
require_once $modelsPath . '/services/JobsService.php';
require_once $modelsPath . '/controllers/JobsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoJobsRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobsValidator();
$service = new JobsService($repo, $validator);
$controller = new JobsController($service);

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $lang = $_GET['lang'] ?? 'ar';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters
    $filters = [
        'entity_id' => $_GET['entity_id'] ?? null,
        'job_type' => $_GET['job_type'] ?? null,
        'employment_type' => $_GET['employment_type'] ?? null,
        'experience_level' => $_GET['experience_level'] ?? null,
        'category' => $_GET['category'] ?? null,
        'department' => $_GET['department'] ?? null,
        'country_id' => $_GET['country_id'] ?? null,
        'city_id' => $_GET['city_id'] ?? null,
        'is_remote' => isset($_GET['is_remote']) ? (int)$_GET['is_remote'] : null,
        'status' => $_GET['status'] ?? null,
        'is_featured' => isset($_GET['is_featured']) ? (int)$_GET['is_featured'] : null,
        'is_urgent' => isset($_GET['is_urgent']) ? (int)$_GET['is_urgent'] : null,
        'salary_negotiable' => isset($_GET['salary_negotiable']) ? (int)$_GET['salary_negotiable'] : null,
        'search' => $_GET['search'] ?? null,
        'salary_min' => $_GET['salary_min'] ?? null,
        'salary_max' => $_GET['salary_max'] ?? null,
        'deadline_after' => $_GET['deadline_after'] ?? null
    ];

    // Remove null filters
    $filters = array_filter($filters, fn($v) => $v !== null);

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // Get single job by ID
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $jobId = (int)$_GET['id'];
                
                // Get translations if requested
                if (isset($_GET['with_translations']) && $_GET['with_translations'] === '1') {
                    $job = $controller->get($jobId, $lang);
                    if ($job) {
                        $job['translations'] = $controller->getTranslations($jobId);
                    }
                    ResponseFormatter::success($job);
                } else {
                    $item = $controller->get($jobId, $lang);
                    
                    // Increment views if not in preview mode
                    if ($item && (!isset($_GET['preview']) || $_GET['preview'] !== '1')) {
                        $controller->incrementViews($jobId);
                    }
                    
                    ResponseFormatter::success($item);
                }
            }
            // Get job by slug
            elseif (isset($_GET['slug'])) {
                $item = $controller->getBySlug($_GET['slug'], $lang);
                
                // Increment views if not in preview mode
                if ($item && (!isset($_GET['preview']) || $_GET['preview'] !== '1')) {
                    $controller->incrementViews((int)$item['id']);
                }
                
                ResponseFormatter::success($item);
            }
            // Get translations for a specific job
            elseif (isset($_GET['job_id']) && isset($_GET['translations'])) {
                $translations = $controller->getTranslations((int)$_GET['job_id']);
                ResponseFormatter::success($translations);
            }
            // Get featured jobs
            elseif (isset($_GET['featured']) && $_GET['featured'] === '1') {
                $featuredLimit = isset($_GET['featured_limit']) ? (int)$_GET['featured_limit'] : 10;
                $items = $controller->getFeatured($featuredLimit, $lang);
                ResponseFormatter::success([
                    'items' => $items,
                    'total' => count($items)
                ]);
            }
            // Get urgent jobs
            elseif (isset($_GET['urgent']) && $_GET['urgent'] === '1') {
                $urgentLimit = isset($_GET['urgent_limit']) ? (int)$_GET['urgent_limit'] : 10;
                $items = $controller->getUrgent($urgentLimit, $lang);
                ResponseFormatter::success([
                    'items' => $items,
                    'total' => count($items)
                ]);
            }
            // Get remote jobs
            elseif (isset($_GET['remote']) && $_GET['remote'] === '1') {
                $remoteLimit = isset($_GET['remote_limit']) ? (int)$_GET['remote_limit'] : 10;
                $items = $controller->getRemote($remoteLimit, $lang);
                ResponseFormatter::success([
                    'items' => $items,
                    'total' => count($items)
                ]);
            }
            // Search jobs
            elseif (isset($_GET['q']) || isset($_GET['search'])) {
                $keyword = $_GET['q'] ?? $_GET['search'];
                $result = $controller->search($keyword, $limit, $offset, $lang);
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
            }
            // List all jobs with filters
            else {
                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
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
            }
            break;

        case 'POST':
            // Add language_code to data if not present
            if (!isset($data['language_code'])) {
                $data['language_code'] = $lang;
            }

            $newId = $controller->create($data);

            ResponseFormatter::success(['id' => $newId], 'Job created successfully', 201);
            break;

        case 'PUT':
            // Add language_code to data if not present
            if (!isset($data['language_code'])) {
                $data['language_code'] = $lang;
            }

            $updatedId = $controller->update($data);

            ResponseFormatter::success(['id' => $updatedId], 'Job updated successfully');
            break;

        case 'PATCH':
            // Handle specific PATCH operations
            
            // Update status
            if (isset($_GET['id']) && isset($data['status'])) {
                $jobId = (int)$_GET['id'];
                $updated = $controller->updateStatus($jobId, $data['status']);
                ResponseFormatter::success(['updated' => $updated], 'Status updated successfully');
            }
            // Publish job
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'publish') {
                $jobId = (int)$_GET['id'];
                $published = $controller->publish($jobId);
                ResponseFormatter::success(['published' => $published], 'Job published successfully');
            }
            // Close job
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'close') {
                $jobId = (int)$_GET['id'];
                $closed = $controller->close($jobId);
                ResponseFormatter::success(['closed' => $closed], 'Job closed successfully');
            }
            // Mark as filled
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'filled') {
                $jobId = (int)$_GET['id'];
                $filled = $controller->markAsFilled($jobId);
                ResponseFormatter::success(['filled' => $filled], 'Job marked as filled');
            }
            // Cancel job
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'cancel') {
                $jobId = (int)$_GET['id'];
                $cancelled = $controller->cancel($jobId);
                ResponseFormatter::success(['cancelled' => $cancelled], 'Job cancelled successfully');
            }
            // Increment applications
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'increment_applications') {
                $jobId = (int)$_GET['id'];
                $incremented = $controller->incrementApplications($jobId);
                ResponseFormatter::success(['incremented' => $incremented], 'Application count incremented');
            }
            // Save/Update translation
            elseif (isset($_GET['job_id']) && isset($_GET['translation'])) {
                $jobId = (int)$_GET['job_id'];
                $languageCode = $data['language_code'] ?? $lang;
                $saved = $controller->saveTranslation($jobId, $languageCode, $data);
                ResponseFormatter::success(['saved' => $saved], 'Translation saved successfully');
            }
            // Delete translation
            elseif (isset($_GET['job_id']) && isset($_GET['delete_translation']) && isset($_GET['lang_code'])) {
                $jobId = (int)$_GET['job_id'];
                $langCode = $_GET['lang_code'];
                $deleted = $controller->deleteTranslation($jobId, $langCode);
                ResponseFormatter::success(['deleted' => $deleted], 'Translation deleted successfully');
            }
            else {
                ResponseFormatter::error('Invalid PATCH request', 400);
            }
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing job ID for deletion', 400);
                exit;
            }
            $deleted = $controller->delete((int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Job deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'jobs.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'jobs.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'jobs.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}


<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobApplicationQuestionsRepository.php';
require_once $modelsPath . '/validators/JobApplicationQuestionsValidator.php';
require_once $modelsPath . '/services/JobApplicationQuestionsService.php';
require_once $modelsPath . '/controllers/JobApplicationQuestionsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoJobApplicationQuestionsRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobApplicationQuestionsValidator();
$service = new JobApplicationQuestionsService($repo, $validator);
$controller = new JobApplicationQuestionsController($service);

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 100;
    $offset = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'sort_order';
    $orderDir = $_GET['order_dir'] ?? 'ASC';

    // Collect filters
    $filters = [
        'job_id' => $_GET['job_id'] ?? null,
        'question_type' => $_GET['question_type'] ?? null,
        'is_required' => isset($_GET['is_required']) ? (int)$_GET['is_required'] : null,
        'search' => $_GET['search'] ?? null
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
            // Get question types
            if (isset($_GET['question_types'])) {
                $types = $controller->getQuestionTypes();
                ResponseFormatter::success($types);
            }
            // Get questions by job
            elseif (isset($_GET['job_id']) && isset($_GET['by_job'])) {
                $jobId = (int)$_GET['job_id'];
                $requiredOnly = isset($_GET['required_only']) && $_GET['required_only'] === '1';
                $items = $controller->getByJob($jobId, $requiredOnly);
                ResponseFormatter::success($items);
            }
            // Get single question
            elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id']);
                ResponseFormatter::success($item);
            }
            // List all questions
            else {
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
            }
            break;

        case 'POST':
            // Bulk create
            if (isset($data['bulk_create']) && isset($data['job_id']) && isset($data['questions'])) {
                $jobId = (int)$data['job_id'];
                $questions = $data['questions'];
                $createdIds = $controller->bulkCreate($jobId, $questions);
                ResponseFormatter::success([
                    'created_ids' => $createdIds,
                    'count' => count($createdIds)
                ], 'Questions created successfully', 201);
            }
            // Duplicate from another job
            elseif (isset($data['duplicate_from']) && isset($data['source_job_id']) && isset($data['target_job_id'])) {
                $sourceJobId = (int)$data['source_job_id'];
                $targetJobId = (int)$data['target_job_id'];
                $duplicated = $controller->duplicateFromJob($sourceJobId, $targetJobId);
                ResponseFormatter::success(['duplicated' => $duplicated], 'Questions duplicated successfully', 201);
            }
            // Create single question
            else {
                $newId = $controller->create($data);
                ResponseFormatter::success(['id' => $newId], 'Question created successfully', 201);
            }
            break;

        case 'PUT':
            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Question updated successfully');
            break;

        case 'PATCH':
            // Update sort order
            if (isset($_GET['id']) && isset($data['sort_order'])) {
                $id = (int)$_GET['id'];
                $sortOrder = (int)$data['sort_order'];
                $updated = $controller->updateSortOrder($id, $sortOrder);
                ResponseFormatter::success(['updated' => $updated], 'Sort order updated successfully');
            }
            // Reorder questions (batch)
            elseif (isset($_GET['action']) && $_GET['action'] === 'reorder') {
                if (empty($data['order']) || !is_array($data['order'])) {
                    ResponseFormatter::error('Order data is required', 400);
                    exit;
                }
                $reordered = $controller->reorder($data['order']);
                ResponseFormatter::success(['reordered' => $reordered], 'Questions reordered successfully');
            }
            else {
                ResponseFormatter::error('Invalid PATCH request', 400);
            }
            break;

        case 'DELETE':
            // Delete all questions for a job
            if (isset($_GET['job_id']) && isset($_GET['delete_all'])) {
                $jobId = (int)$_GET['job_id'];
                $deleted = $controller->deleteByJob($jobId);
                ResponseFormatter::success(['deleted' => $deleted], 'All questions deleted successfully');
            }
            // Delete single question
            elseif (!empty($data['id'])) {
                $deleted = $controller->delete((int)$data['id']);
                ResponseFormatter::success(['deleted' => $deleted], 'Question deleted successfully');
            }
            else {
                ResponseFormatter::error('Missing question ID for deletion', 400);
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_application_questions.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_application_questions.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'job_application_questions.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}

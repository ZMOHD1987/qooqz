<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobApplicationAnswersRepository.php';
require_once $modelsPath . '/validators/JobApplicationAnswersValidator.php';
require_once $modelsPath . '/services/JobApplicationAnswersService.php';
require_once $modelsPath . '/controllers/JobApplicationAnswersController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoJobApplicationAnswersRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobApplicationAnswersValidator();
$service = new JobApplicationAnswersService($repo, $validator);
$controller = new JobApplicationAnswersController($service);

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
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'ASC';

    // Collect filters
    $filters = [
        'application_id' => $_GET['application_id'] ?? null,
        'question_id' => $_GET['question_id'] ?? null,
        'job_id' => $_GET['job_id'] ?? null,
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
            // Get answers by application
            if (isset($_GET['application_id']) && isset($_GET['by_application'])) {
                $applicationId = (int)$_GET['application_id'];
                $items = $controller->getByApplication($applicationId);
                ResponseFormatter::success($items);
            }
            // Get answer by application and question
            elseif (isset($_GET['application_id']) && isset($_GET['question_id'])) {
                $applicationId = (int)$_GET['application_id'];
                $questionId = (int)$_GET['question_id'];
                $item = $controller->getByApplicationAndQuestion($applicationId, $questionId);
                ResponseFormatter::success($item);
            }
            // Get answers by question
            elseif (isset($_GET['question_id']) && isset($_GET['by_question'])) {
                $questionId = (int)$_GET['question_id'];
                $items = $controller->getByQuestion($questionId);
                ResponseFormatter::success($items);
            }
            // Get question statistics
            elseif (isset($_GET['question_id']) && isset($_GET['statistics'])) {
                $questionId = (int)$_GET['question_id'];
                $stats = $controller->getQuestionStatistics($questionId);
                ResponseFormatter::success($stats);
            }
            // Check required answers
            elseif (isset($_GET['application_id']) && isset($_GET['check_required'])) {
                $applicationId = (int)$_GET['application_id'];
                $check = $controller->checkRequiredAnswers($applicationId);
                ResponseFormatter::success($check);
            }
            // Get single answer
            elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id']);
                ResponseFormatter::success($item);
            }
            // List all answers
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
            // Bulk save answers
            if (isset($data['bulk_save']) && isset($data['application_id']) && isset($data['answers'])) {
                $applicationId = (int)$data['application_id'];
                $answers = $data['answers'];
                $questions = $data['questions'] ?? [];
                
                // Submit with validation
                if (!empty($questions)) {
                    $saved = $controller->submitApplicationAnswers($applicationId, $answers, $questions);
                } else {
                    $saved = $controller->bulkSave($applicationId, $answers);
                }
                
                ResponseFormatter::success([
                    'saved' => $saved,
                    'count' => count($answers)
                ], 'Answers saved successfully', 201);
            }
            // Create single answer
            else {
                $newId = $controller->create($data);
                ResponseFormatter::success(['id' => $newId], 'Answer created successfully', 201);
            }
            break;

        case 'PUT':
            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Answer updated successfully');
            break;

        case 'DELETE':
            // Delete by application
            if (isset($_GET['application_id']) && isset($_GET['delete_all'])) {
                $applicationId = (int)$_GET['application_id'];
                $deleted = $controller->deleteByApplication($applicationId);
                ResponseFormatter::success(['deleted' => $deleted], 'All answers deleted successfully');
            }
            // Delete by question
            elseif (isset($_GET['question_id']) && isset($_GET['delete_all'])) {
                $questionId = (int)$_GET['question_id'];
                $deleted = $controller->deleteByQuestion($questionId);
                ResponseFormatter::success(['deleted' => $deleted], 'All answers deleted successfully');
            }
            // Delete single answer
            elseif (!empty($data['id'])) {
                $deleted = $controller->delete((int)$data['id']);
                ResponseFormatter::success(['deleted' => $deleted], 'Answer deleted successfully');
            }
            else {
                ResponseFormatter::error('Missing answer ID for deletion', 400);
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_application_answers.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_application_answers.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'job_application_answers.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}

<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobInterviewsRepository.php';
require_once $modelsPath . '/validators/JobInterviewsValidator.php';
require_once $modelsPath . '/services/JobInterviewsService.php';
require_once $modelsPath . '/controllers/JobInterviewsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoJobInterviewsRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobInterviewsValidator();
$service = new JobInterviewsService($repo, $validator);
$controller = new JobInterviewsController($service);

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
    $orderBy = $_GET['order_by'] ?? 'interview_date';
    $orderDir = $_GET['order_dir'] ?? 'ASC';

    // Collect filters
    $filters = [
        'application_id' => $_GET['application_id'] ?? null,
        'job_id' => $_GET['job_id'] ?? null,
        'interview_type' => $_GET['interview_type'] ?? null,
        'status' => $_GET['status'] ?? null,
        'rating' => $_GET['rating'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'today' => $_GET['today'] ?? null,
        'upcoming' => $_GET['upcoming'] ?? null,
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
            // Get interview types
            if (isset($_GET['interview_types'])) {
                $types = $controller->getInterviewTypes();
                ResponseFormatter::success($types);
            }
            // Get statuses
            elseif (isset($_GET['statuses'])) {
                $statuses = $controller->getStatuses();
                ResponseFormatter::success($statuses);
            }
            // Get statistics
            elseif (isset($_GET['statistics'])) {
                $statsFilters = [];
                if (isset($_GET['job_id'])) {
                    $statsFilters['job_id'] = $_GET['job_id'];
                }
                $stats = $controller->getStatistics($statsFilters);
                ResponseFormatter::success($stats);
            }
            // Get interviews by application
            elseif (isset($_GET['application_id']) && isset($_GET['by_application'])) {
                $applicationId = (int)$_GET['application_id'];
                $items = $controller->getByApplication($applicationId);
                ResponseFormatter::success($items);
            }
            // Get single interview
            elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id']);
                ResponseFormatter::success($item);
            }
            // List all interviews
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
            // Schedule interview
            if (isset($data['schedule']) && $data['schedule'] === true) {
                unset($data['schedule']);
                $newId = $controller->schedule($data);
                ResponseFormatter::success(['id' => $newId], 'Interview scheduled successfully', 201);
            }
            // Create interview
            else {
                $newId = $controller->create($data);
                ResponseFormatter::success(['id' => $newId], 'Interview created successfully', 201);
            }
            break;

        case 'PUT':
            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Interview updated successfully');
            break;

        case 'PATCH':
            // Update status
            if (isset($_GET['id']) && isset($data['status'])) {
                $id = (int)$_GET['id'];
                $updated = $controller->updateStatus($id, $data['status']);
                ResponseFormatter::success(['updated' => $updated], 'Status updated successfully');
            }
            // Add feedback
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'feedback') {
                $id = (int)$_GET['id'];
                if (empty($data['feedback'])) {
                    ResponseFormatter::error('feedback is required', 400);
                    exit;
                }
                $rating = isset($data['rating']) ? (int)$data['rating'] : null;
                $added = $controller->addFeedback($id, $data['feedback'], $rating);
                ResponseFormatter::success(['added' => $added], 'Feedback added successfully');
            }
            // Reschedule
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'reschedule') {
                $id = (int)$_GET['id'];
                if (empty($data['new_date'])) {
                    ResponseFormatter::error('new_date is required', 400);
                    exit;
                }
                $newDuration = isset($data['new_duration']) ? (int)$data['new_duration'] : null;
                $rescheduled = $controller->reschedule($id, $data['new_date'], $newDuration);
                ResponseFormatter::success(['rescheduled' => $rescheduled], 'Interview rescheduled successfully');
            }
            // Confirm
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'confirm') {
                $confirmed = $controller->confirm((int)$_GET['id']);
                ResponseFormatter::success(['confirmed' => $confirmed], 'Interview confirmed');
            }
            // Complete
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'complete') {
                $completed = $controller->complete((int)$_GET['id']);
                ResponseFormatter::success(['completed' => $completed], 'Interview marked as completed');
            }
            // Cancel
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'cancel') {
                $cancelled = $controller->cancel((int)$_GET['id']);
                ResponseFormatter::success(['cancelled' => $cancelled], 'Interview cancelled');
            }
            // Mark no show
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'no_show') {
                $marked = $controller->markNoShow((int)$_GET['id']);
                ResponseFormatter::success(['marked' => $marked], 'Marked as no show');
            }
            else {
                ResponseFormatter::error('Invalid PATCH request', 400);
            }
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing interview ID for deletion', 400);
                exit;
            }
            $deleted = $controller->delete((int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Interview deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_interviews.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_interviews.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'job_interviews.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}

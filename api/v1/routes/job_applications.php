<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobApplicationsRepository.php';
require_once $modelsPath . '/validators/JobApplicationsValidator.php';
require_once $modelsPath . '/services/JobApplicationsService.php';
require_once $modelsPath . '/controllers/JobApplicationsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoJobApplicationsRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobApplicationsValidator();
$service = new JobApplicationsService($repo, $validator);
$controller = new JobApplicationsController($service);

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
        'job_id' => $_GET['job_id'] ?? null,
        'user_id' => $_GET['user_id'] ?? null,
        'status' => $_GET['status'] ?? null,
        'rating' => $_GET['rating'] ?? null,
        'search' => $_GET['search'] ?? null,
        'salary_min' => $_GET['salary_min'] ?? null,
        'salary_max' => $_GET['salary_max'] ?? null,
        'experience_min' => $_GET['experience_min'] ?? null,
        'experience_max' => $_GET['experience_max'] ?? null,
        'rating_min' => $_GET['rating_min'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'reviewed' => $_GET['reviewed'] ?? null
    ];

    $filters = array_filter($filters, fn($v) => $v !== null);

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // Get applications by job
            if (isset($_GET['job_id']) && isset($_GET['by_job'])) {
                $jobId = (int)$_GET['job_id'];
                $items = $controller->getByJob($jobId, $lang);
                ResponseFormatter::success($items);
            }
            // Get applications by user
            elseif (isset($_GET['user_id']) && isset($_GET['by_user'])) {
                $userId = (int)$_GET['user_id'];
                $items = $controller->getByUser($userId, $lang);
                ResponseFormatter::success($items);
            }
            // Check if user applied
            elseif (isset($_GET['check_applied']) && isset($_GET['job_id']) && isset($_GET['user_id'])) {
                $hasApplied = $controller->hasApplied((int)$_GET['job_id'], (int)$_GET['user_id']);
                ResponseFormatter::success(['has_applied' => $hasApplied]);
            }
            // Get statistics
            elseif (isset($_GET['job_id']) && isset($_GET['statistics'])) {
                $jobId = (int)$_GET['job_id'];
                $stats = $controller->getStatistics($jobId);
                ResponseFormatter::success($stats);
            }
            // Get single application
            elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id'], $lang);
                ResponseFormatter::success($item);
            }
            // List all applications
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
            // Capture IP address
            $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;

            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Application submitted successfully', 201);
            break;

        case 'PUT':
            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Application updated successfully');
            break;

        case 'PATCH':
            // Update status
            if (isset($_GET['id']) && isset($data['status'])) {
                $id = (int)$_GET['id'];
                $updated = $controller->updateStatus($id, $data['status']);
                ResponseFormatter::success(['updated' => $updated], 'Status updated successfully');
            }
            // Update rating
            elseif (isset($_GET['id']) && isset($data['rating'])) {
                $id = (int)$_GET['id'];
                $rating = (int)$data['rating'];
                $reviewedBy = isset($data['reviewed_by']) ? (int)$data['reviewed_by'] : null;
                $updated = $controller->updateRating($id, $rating, $reviewedBy);
                ResponseFormatter::success(['updated' => $updated], 'Rating updated successfully');
            }
            // Add review
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'review') {
                $id = (int)$_GET['id'];
                if (empty($data['notes']) || empty($data['reviewed_by'])) {
                    ResponseFormatter::error('notes and reviewed_by are required', 400);
                    exit;
                }
                $added = $controller->addReview($id, $data['notes'], (int)$data['reviewed_by']);
                ResponseFormatter::success(['added' => $added], 'Review added successfully');
            }
            // Shortlist
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'shortlist') {
                $updated = $controller->shortlist((int)$_GET['id']);
                ResponseFormatter::success(['updated' => $updated], 'Application shortlisted');
            }
            // Reject
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'reject') {
                $updated = $controller->reject((int)$_GET['id']);
                ResponseFormatter::success(['updated' => $updated], 'Application rejected');
            }
            // Schedule interview
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'schedule_interview') {
                $updated = $controller->scheduleInterview((int)$_GET['id']);
                ResponseFormatter::success(['updated' => $updated], 'Interview scheduled');
            }
            // Mark interviewed
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'mark_interviewed') {
                $updated = $controller->markInterviewed((int)$_GET['id']);
                ResponseFormatter::success(['updated' => $updated], 'Marked as interviewed');
            }
            // Make offer
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'make_offer') {
                $updated = $controller->makeOffer((int)$_GET['id']);
                ResponseFormatter::success(['updated' => $updated], 'Offer made');
            }
            // Accept offer
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'accept_offer') {
                $updated = $controller->acceptOffer((int)$_GET['id']);
                ResponseFormatter::success(['updated' => $updated], 'Offer accepted');
            }
            // Withdraw
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'withdraw') {
                $updated = $controller->withdraw((int)$_GET['id']);
                ResponseFormatter::success(['updated' => $updated], 'Application withdrawn');
            }
            // Under review
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'under_review') {
                $updated = $controller->moveToUnderReview((int)$_GET['id']);
                ResponseFormatter::success(['updated' => $updated], 'Moved to under review');
            }
            else {
                ResponseFormatter::error('Invalid PATCH request', 400);
            }
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing application ID for deletion', 400);
                exit;
            }
            $deleted = $controller->delete((int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Application deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_applications.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_applications.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'job_applications.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}

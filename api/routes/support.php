<?php
// htdocs/api/routes/support.php
// Routes for support endpoints (maps HTTP requests to SupportController methods)
// Supports: tickets CRUD, replies, assign/transfer, close/reopen, categories, stats

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/SupportController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type (تعديل حسب الحاجة)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/support
$baseSegment = '/api/support';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

$actionPath = '/';
if (strpos($path, $baseSegment) !== false) {
    $actionPath = substr($path, strpos($path, $baseSegment) + strlen($baseSegment));
}
$actionPath = trim($actionPath, '/');
$actionParts = $actionPath === '' ? [] : explode('/', $actionPath);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$first = $actionParts[0] ?? '';

// helpers
$isNumericFirst = isset($actionParts[0]) && is_numeric($actionParts[0]);
$id = $isNumericFirst ? (int)$actionParts[0] : null;
$second = $actionParts[1] ?? null;

try {
    switch (true) {
        // /api/support  GET (list tickets) or POST (create ticket)
        case $first === '' && $method === 'GET':
            SupportController::index();
            exit;

        case $first === '' && $method === 'POST':
            SupportController::create();
            exit;

        // /api/support/stats  GET
        case $first === 'stats' && $method === 'GET':
            SupportController::stats();
            exit;

        // /api/support/categories  GET/POST for ticket categories
        case $first === 'categories' && $method === 'GET' && !isset($actionParts[1]):
            SupportController::listCategories();
            exit;

        case $first === 'categories' && $method === 'POST' && !isset($actionParts[1]):
            SupportController::createCategory();
            exit;

        // /api/support/categories/{id} GET/PUT/DELETE
        case $first === 'categories' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'GET':
            SupportController::showCategory((int)$actionParts[1]);
            exit;

        case $first === 'categories' && isset($actionParts[1]) && is_numeric($actionParts[1]) && ($method === 'PUT' || $method === 'POST'):
            SupportController::updateCategory((int)$actionParts[1]);
            exit;

        case $first === 'categories' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'DELETE':
            SupportController::deleteCategory((int)$actionParts[1]);
            exit;

        // /api/support/{id}  GET show ticket
        case $isNumericFirst && $method === 'GET' && !$second:
            SupportController::show($id);
            exit;

        // /api/support/{id}  PUT/POST update ticket
        case $isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second:
            SupportController::update($id);
            exit;

        // /api/support/{id}  DELETE delete ticket
        case $isNumericFirst && $method === 'DELETE' && !$second:
            SupportController::delete($id);
            exit;

        // /api/support/{id}/reply  POST add reply to ticket
        case $isNumericFirst && $second === 'reply' && $method === 'POST':
            SupportController::reply($id);
            exit;

        // /api/support/{id}/assign  POST assign ticket to agent
        case $isNumericFirst && $second === 'assign' && $method === 'POST':
            SupportController::assign($id);
            exit;

        // /api/support/{id}/transfer  POST transfer ticket to another team/agent
        case $isNumericFirst && $second === 'transfer' && $method === 'POST':
            SupportController::transfer($id);
            exit;

        // /api/support/{id}/close  POST close ticket
        case $isNumericFirst && $second === 'close' && $method === 'POST':
            SupportController::close($id);
            exit;

        // /api/support/{id}/reopen  POST reopen ticket
        case $isNumericFirst && $second === 'reopen' && $method === 'POST':
            SupportController::reopen($id);
            exit;

        // /api/support/{id}/attachments  POST upload attachment to ticket
        case $isNumericFirst && $second === 'attachments' && $method === 'POST':
            SupportController::uploadAttachment($id);
            exit;

        // /api/support/agent-tickets  GET tickets assigned to current agent
        case $first === 'agent-tickets' && $method === 'GET':
            SupportController::agentTickets();
            exit;

        // /api/support/unassigned  GET unassigned tickets list
        case $first === 'unassigned' && $method === 'GET':
            SupportController::unassigned();
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Support route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>
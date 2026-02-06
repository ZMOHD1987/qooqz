<?php
// htdocs/api/routes/notifications.php
// Routes for notification endpoints (maps HTTP requests to NotificationController methods)
// Supports: listing, sending, viewing, updating, deleting, mark read/unread, subscriptions, preferences, counts, webhooks

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type (تعديل حسب الحاجة)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/notifications
$baseSegment = '/api/notifications';
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
        // /api/notifications  GET (list notifications) or POST (send/create notification)
        case $first === '' && $method === 'GET':
            NotificationController::index();
            exit;

        case $first === '' && $method === 'POST':
            // sending a notification (admin or system) or creating a user notification
            NotificationController::create();
            exit;

        // /api/notifications/count  GET -> total count (optional admin)
        case $first === 'count' && $method === 'GET':
            NotificationController::count();
            exit;

        // /api/notifications/unread-count  GET -> unread count for current user
        case $first === 'unread-count' && $method === 'GET':
            NotificationController::unreadCount();
            exit;

        // /api/notifications/{id}  GET show a notification
        case $isNumericFirst && $method === 'GET' && !$second:
            NotificationController::show($id);
            exit;

        // /api/notifications/{id}  PUT/POST update a notification (mark read, update content, etc.)
        case $isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second:
            NotificationController::update($id);
            exit;

        // /api/notifications/{id} DELETE
        case $isNumericFirst && $method === 'DELETE' && !$second:
            NotificationController::delete($id);
            exit;

        // /api/notifications/mark-read  POST with { ids: [..] } or { id: x }
        case $first === 'mark-read' && $method === 'POST':
            NotificationController::markRead();
            exit;

        // /api/notifications/mark-unread  POST
        case $first === 'mark-unread' && $method === 'POST':
            NotificationController::markUnread();
            exit;

        // /api/notifications/mark-all-read  POST
        case $first === 'mark-all-read' && $method === 'POST':
            NotificationController::markAllRead();
            exit;

        // /api/notifications/subscriptions  GET list user's subscriptions or POST to subscribe
        case $first === 'subscriptions' && $method === 'GET':
            NotificationController::listSubscriptions();
            exit;

        case $first === 'subscriptions' && $method === 'POST':
            NotificationController::subscribe();
            exit;

        // /api/notifications/subscriptions/{id} DELETE -> unsubscribe
        case $first === 'subscriptions' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'DELETE':
            NotificationController::unsubscribe((int)$actionParts[1]);
            exit;

        // /api/notifications/preferences  GET/POST to get or update notification preferences for current user
        case $first === 'preferences' && $method === 'GET':
            NotificationController::preferences();
            exit;

        case $first === 'preferences' && ($method === 'POST' || $method === 'PUT'):
            NotificationController::savePreferences();
            exit;

        // /api/notifications/send  POST - explicit send endpoint (admin/system)
        case $first === 'send' && $method === 'POST':
            NotificationController::send();
            exit;

        // /api/notifications/webhook  POST - handle incoming push/webhook callbacks (e.g., from FCM, APNs, third-party)
        case $first === 'webhook' && $method === 'POST':
            NotificationController::handleWebhook();
            exit;

        // /api/notifications/stats  GET - notification stats (admin)
        case $first === 'stats' && $method === 'GET':
            NotificationController::stats();
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Notifications route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>
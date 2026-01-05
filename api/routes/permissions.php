<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// api/routes/permissions.php
// Standalone endpoint for permissions API (RBAC)
// Includes bootstrap.php for DB connection, session, helpers
// Handles GET and POST requests with actions

// Include bootstrap for DB, session, helpers
require_once __DIR__ . '/../bootstrap.php';

// Load dependencies
require_once __DIR__ . '/../models/Permissions.php';
require_once __DIR__ . '/../validators/PermissionsValidator.php';
require_once __DIR__ . '/../controllers/PermissionsController.php';

/* =========================================
 * CORS handling (admin UI friendly)
 * ========================================= */

if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowed = ['http://localhost', 'http://localhost:3000', 'http://127.0.0.1'];

    if (defined('ADMIN_UI_ORIGIN')) {
        $allowed[] = ADMIN_UI_ORIGIN;
    }

    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* =========================================
 * Read input (JSON or POST)
 * ========================================= */

$raw = @file_get_contents('php://input');
$input = @json_decode($raw, true);
if ($input === null) {
    $input = $_POST;
}

// Merge GET params for convenience
if (!empty($_GET)) {
    $input = array_merge($input, $_GET);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $input['action'] ?? '';

/* =========================================
 * GET requests
 * ========================================= */

if ($method === 'GET') {

    // Fetch single permission (legacy or normal)
    if (!empty($input['_fetch_row']) && !empty($input['id'])) {
        PermissionsController::get($input);
        exit;
    }

    if (isset($input['id'])) {
        PermissionsController::get($input);
        exit;
    }

    // List permissions
    PermissionsController::list($input);
    exit;
}

/* =========================================
 * POST requests
 * ========================================= */

if ($method === 'POST') {

    if (!$action) {
        respond(['success' => false, 'message' => 'Missing action parameter'], 400);
        exit;
    }

    switch (strtolower($action)) {

        case 'save':
            PermissionsController::save($input);
            break;

        case 'delete':
            PermissionsController::delete($input);
            break;

        case 'assign_to_role':
            PermissionsController::assignToRole($input);
            break;

        case 'remove_from_role':
            PermissionsController::removeFromRole($input);
            break;

        case 'role_permissions':
            PermissionsController::rolePermissions($input);
            break;

        default:
            respond(['success' => false, 'message' => 'Unknown action'], 400);
            break;
    }

    exit;
}

/* =========================================
 * Method not allowed
 * ========================================= */

respond(['success' => false, 'message' => 'Method not allowed'], 405);

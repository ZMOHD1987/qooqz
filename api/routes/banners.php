<?php
// api/routes/banners.php
// Standalone endpoint for banners API
// Includes bootstrap.php for DB connection, session, helpers
// Handles GET and POST requests with actions

// Include bootstrap for DB, session, helpers
require_once __DIR__ . '/../bootstrap.php';

// Load dependencies
require_once __DIR__ . '/../models/Banner.php';
require_once __DIR__ . '/../validators/BannerValidator.php';
require_once __DIR__ . '/../controllers/BannerController.php';

// CORS handling (already in bootstrap, but reinforce for admin UI)
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowed = ['http://localhost', 'http://localhost:3000', 'http://127.0.0.1'];
    // Add production domain if configured
    if (defined('ADMIN_UI_ORIGIN')) $allowed[] = ADMIN_UI_ORIGIN;
    
    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Read input (JSON body or POST)
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
$action = isset($input['action']) ? $input['action'] : '';

// Handle GET requests
if ($method === 'GET') {
    // Support legacy _fetch_row parameter
    if (!empty($input['_fetch_row']) && !empty($input['id'])) {
        BannerController::get($input);
        exit;
    }
    
    // Single banner by id
    if (isset($input['id'])) {
        BannerController::get($input);
        exit;
    }
    
    // List all banners
    BannerController::list($input);
    exit;
}

// Handle POST requests
if ($method === 'POST') {
    if (!$action) {
        respond(['success' => false, 'message' => 'Missing action parameter'], 400);
        exit;
    }

    switch (strtolower($action)) {
        case 'save':
            BannerController::save($input);
            break;
        case 'delete':
            BannerController::delete($input);
            break;
        case 'toggle_active':
            BannerController::toggleActive($input);
            break;
        case 'translations':
            BannerController::translations($input);
            break;
        default:
            respond(['success' => false, 'message' => 'Unknown action'], 400);
            break;
    }
    exit;
}

// Method not allowed
respond(['success' => false, 'message' => 'Method not allowed'], 405);
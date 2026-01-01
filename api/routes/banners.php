<?php
// htdocs/api/routes/banners.php
// Minimal dispatcher for banners endpoints.
// Expected to be included from a bootstrap that sets $conn, session and helpers.

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../controllers/BannerController.php';

// read input (JSON body) or fallback to $_POST
$raw = @file_get_contents('php://input');
$input = json_decode($raw, true);
if ($input === null) $input = $_POST;

// method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($input['action']) ? $input['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// GET requests: list or single
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        BannerController::get($_GET);
    } else {
        BannerController::list($_GET);
    }
    exit;
}

// POST requests: use action param
if ($method === 'POST') {
    if (!$action) return respond(['success'=>false,'message'=>'Missing action'],400);
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
            respond(['success'=>false,'message'=>'Unknown action'],400);
            break;
    }
    exit;
}

respond(['success'=>false,'message'=>'Method not allowed'],405);
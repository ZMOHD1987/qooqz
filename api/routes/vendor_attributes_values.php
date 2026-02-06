<?php
declare(strict_types=1);

/**
 * Vendor Attributes Values API Router
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

/* ================= CORS ================= */
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ================= Load Controller ================= */
$controllerPath = dirname(__DIR__) . '/controllers/Vendor_attributes_valuesController.php';
if (!is_readable($controllerPath)) {
    echo json_encode(['success' => false, 'message' => 'Controller not found']);
    exit;
}
require_once $controllerPath;

/* ================= Prepare Input ================= */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

$_POST = array_merge($_POST, $body);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ================= Routing ================= */
try {

    if ($method === 'GET') {

        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            Vendor_attributes_valuesController::get(['id' => (int)$_GET['id']]);
        } else {
            Vendor_attributes_valuesController::list();
        }

    } elseif ($method === 'POST') {

        $action = strtolower($_POST['action'] ?? 'save');

        if ($action === 'delete') {
            Vendor_attributes_valuesController::delete();
        } else {
            Vendor_attributes_valuesController::save();
        }

    } else {

        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);

    }

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error',
        'error'   => $e->getMessage()
    ]);
}

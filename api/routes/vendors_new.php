<?php
/**
 * api/routes/vendors_new.php
 * New routing file for vendors - uses refactored VendorControllerNew
 * Replaces api/vendors.php monolithic script
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error handling
set_exception_handler(function(Throwable $e){
    error_log("[VendorRoutes] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error: ' . $e->getMessage()]);
    exit;
});

// Load dependencies
$baseDir = realpath(__DIR__ . '/..');
require_once $baseDir . '/config/db.php';
require_once $baseDir . '/controllers/VendorControllerNew.php';

// Create connection
try {
    $conn = connectDB();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database connection failed']);
    exit;
}

// Instantiate controller and dispatch
try {
    $controller = new VendorControllerNew($conn);
    $controller->dispatch();
} catch (Throwable $e) {
    error_log("[VendorRoutes] Dispatch error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Request failed']);
    exit;
}

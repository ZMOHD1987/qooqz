<?php
declare(strict_types=1);

/**
 * /api/routes/products.php
 * API endpoint for Products management - Following Vendor pattern
 */

// Start session
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log directory
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
ini_set('error_log', $logDir . '/error_debug.log');

// Simple log function
function log_msg($msg) {
    error_log("[products.php] " . $msg);
}

log_msg("=== NEW REQUEST START ===");
log_msg("Method: " . $_SERVER['REQUEST_METHOD']);
log_msg("Time: " . date('Y-m-d H:i:s'));

// Set headers
header('Content-Type: application/json; charset=utf-8');

// Simple CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load database
require_once __DIR__ . '/../config/db.php';
$conn = connectDB();
if (!$conn || $conn->connect_error) {
    log_msg("Database connection failed: " . ($conn->connect_error ?? 'Unknown'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

log_msg("Database connected successfully");

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    log_msg("Generated new CSRF token");
}

// Get current user
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1;

log_msg("User ID: $user_id, Is Admin: " . ($is_admin ? 'Yes' : 'No'));

$method = $_SERVER['REQUEST_METHOD'];

// Load controller
require_once __DIR__ . '/../controllers/ProductController.php';

try {
    $controller = new ProductController($conn);
    
    // Parse path to get ID if present
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $id = null;
    
    // Find ID in path (after 'products')
    $productsIndex = array_search('products', $segments);
    if ($productsIndex !== false && isset($segments[$productsIndex + 1]) && is_numeric($segments[$productsIndex + 1])) {
        $id = (int)$segments[$productsIndex + 1];
    }
    
    log_msg("Parsed ID from path: " . ($id ?: 'none'));
    
    // Route to appropriate method
    if ($method === 'GET' && !$id) {
        // List products
        $controller->index();
    } elseif ($method === 'GET' && $id) {
        // Get single product
        $controller->show($id);
    } elseif ($method === 'POST' && !$id) {
        // Create product
        $controller->create();
    } elseif (($method === 'POST' || $method === 'PUT') && $id) {
        // Update product
        $controller->update($id);
    } elseif ($method === 'DELETE' && $id) {
        // Delete product
        $controller->delete($id);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
    }
    
} catch (Throwable $e) {
    log_msg("Error: " . $e->getMessage());
    log_msg("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error', 'error' => $e->getMessage()]);
}

<?php
// api/routes/roles.php
// Simple endpoint for roles API (for dropdowns/lookups)
declare(strict_types=1);

// Include bootstrap for DB, session, helpers
$bootstrapPath = dirname(__DIR__) . '/bootstrap.php';
if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
}

// Load model
$modelPath = dirname(__DIR__) . '/models/Roles.php';
if (file_exists($modelPath)) {
    require_once $modelPath;
}

// CORS handling
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

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check if Roles class exists
    if (!class_exists('Roles')) {
        throw new Exception('Roles model not loaded');
    }
    
    // Get all roles
    $roles = Roles::all();
    
    // Return response
    echo json_encode([
        'success' => true,
        'count' => count($roles),
        'data' => $roles
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading roles: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

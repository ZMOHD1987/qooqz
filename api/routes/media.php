<?php
declare(strict_types=1);

// Require classes with absolute paths
$baseDir = dirname(__DIR__); // api/
require_once $baseDir . '/controllers/MediaController.php';
require_once $baseDir . '/models/MediaModel.php';
require_once $baseDir . '/validators/MediaValidator.php';

// Check if classes exist
if (!class_exists('MediaController')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'MediaController class not found']);
    exit;
}
if (!class_exists('MediaModel')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'MediaModel class not found']);
    exit;
}
if (!class_exists('MediaValidator')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'MediaValidator class not found']);
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) session_start();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = $_SESSION['user']['id'] ?? 1;

$controller = new MediaController();

if ($method === 'GET') {
    $params = $_GET;
    $params['user_id'] = $userId;
    $response = $controller->index($params);
} elseif ($method === 'POST') {
    if ($action === 'crop') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $response = $controller->crop($id, $data);
    } elseif ($action === 'resize') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $response = $controller->resize($id, $data);
    } elseif ($action === 'change_quality') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $response = $controller->changeQuality($id, $data);
    } else {
        $data = $_POST;
        $data['user_id'] = $userId;
        $data['files'] = $_FILES['files'] ?? [];
        $response = $controller->store($data);
    }
} elseif ($method === 'PUT') {
    if ($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $response = $controller->update($id, $data);
    } else {
        $response = ['success' => false, 'message' => 'ID required for PUT'];
    }
} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    if (!empty($data['ids'])) {
        $response = $controller->deleteMultiple($data['ids']);
    } elseif ($id) {
        $response = $controller->delete($id);
    } else {
        $response = ['success' => false, 'message' => 'ID or IDs required'];
    }
} else {
    $response = ['success' => false, 'message' => 'Method not allowed'];
}

header('Content-Type: application/json');
echo json_encode($response);
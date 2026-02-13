<?php
declare(strict_types=1);

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

date_default_timezone_set('Asia/Riyadh');

// Load dependencies
$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// CORS headers
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

// Session
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Database connection
if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

try {
    $pdo    = $GLOBALS['ADMIN_DB'];
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $discountId = (int)($_GET['discount_id'] ?? 0);
            if ($discountId <= 0) { ResponseFormatter::error('discount_id is required', 400); break; }

            $stmt = $pdo->prepare("SELECT * FROM discount_actions WHERE discount_id = :discount_id ORDER BY id");
            $stmt->execute([':discount_id' => $discountId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ResponseFormatter::success($items);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $required = ['discount_id', 'action_type', 'action_value'];
            $missing = [];
            foreach ($required as $f) {
                if (!isset($data[$f]) || $data[$f] === '') $missing[] = $f;
            }
            if ($missing) { ResponseFormatter::error('Missing required fields: ' . implode(', ', $missing), 422); break; }

            $stmt = $pdo->prepare("INSERT INTO discount_actions (discount_id, action_type, action_value) VALUES (:discount_id, :action_type, :action_value)");
            $stmt->execute([
                ':discount_id'  => (int)$data['discount_id'],
                ':action_type'  => $data['action_type'],
                ':action_value' => $data['action_value'],
            ]);
            ResponseFormatter::success(['id' => (int)$pdo->lastInsertId()], 'Action created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }

            $allowed = ['action_type', 'action_value'];
            $sets   = [];
            $params = [':id' => $id];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[]          = "$col = :$col";
                    $params[":$col"] = $data[$col];
                }
            }
            if (empty($sets)) { ResponseFormatter::error('No fields to update', 422); break; }

            $stmt = $pdo->prepare("UPDATE discount_actions SET " . implode(', ', $sets) . " WHERE id = :id");
            $stmt->execute($params);
            ResponseFormatter::success(null, 'Action updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $stmt = $pdo->prepare("DELETE FROM discount_actions WHERE id = :id");
            $stmt->execute([':id' => $id]);
            ResponseFormatter::success(null, 'Action deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

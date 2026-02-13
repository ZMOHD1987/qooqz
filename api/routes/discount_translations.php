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
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

            $stmt = $pdo->prepare("SELECT * FROM discount_translations WHERE discount_id = :discount_id ORDER BY language_code");
            $stmt->execute([':discount_id' => $discountId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ResponseFormatter::success($items);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $required = ['discount_id', 'language_code', 'name'];
            $missing = [];
            foreach ($required as $f) {
                if (!isset($data[$f]) || $data[$f] === '') $missing[] = $f;
            }
            if ($missing) { ResponseFormatter::error('Missing required fields: ' . implode(', ', $missing), 422); break; }

            $stmt = $pdo->prepare("INSERT INTO discount_translations (discount_id, language_code, name, description, terms_conditions, marketing_badge) VALUES (:discount_id, :language_code, :name, :description, :terms_conditions, :marketing_badge) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), terms_conditions = VALUES(terms_conditions), marketing_badge = VALUES(marketing_badge)");
            $stmt->execute([
                ':discount_id'      => (int)$data['discount_id'],
                ':language_code'    => $data['language_code'],
                ':name'             => $data['name'],
                ':description'      => $data['description'] ?? null,
                ':terms_conditions' => $data['terms_conditions'] ?? null,
                ':marketing_badge'  => $data['marketing_badge'] ?? null,
            ]);
            $id = (int)$pdo->lastInsertId();
            ResponseFormatter::success(['id' => $id], 'Translation saved', 201);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $stmt = $pdo->prepare("DELETE FROM discount_translations WHERE id = :id");
            $stmt->execute([':id' => $id]);
            ResponseFormatter::success(null, 'Translation deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

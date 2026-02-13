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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
            if (isset($_GET['stats'])) {
                $discountId = (int)($_GET['discount_id'] ?? 0);
                $where  = '';
                $params = [];
                if ($discountId > 0) {
                    $where = ' WHERE discount_id = :discount_id';
                    $params[':discount_id'] = $discountId;
                }
                $stmt = $pdo->prepare("SELECT COUNT(*) AS total_redemptions, COALESCE(SUM(amount_discounted), 0) AS total_amount_discounted, COUNT(DISTINCT user_id) AS unique_users FROM discount_redemptions" . $where);
                $stmt->execute($params);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                ResponseFormatter::success($stats);
                break;
            }

            $discountId = (int)($_GET['discount_id'] ?? 0);
            if ($discountId <= 0) { ResponseFormatter::error('discount_id is required', 400); break; }

            $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM discount_redemptions WHERE discount_id = :discount_id");
            $countStmt->execute([':discount_id' => $discountId]);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT * FROM discount_redemptions WHERE discount_id = :discount_id ORDER BY redeemed_at DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':discount_id', $discountId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ResponseFormatter::success(['items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $required = ['discount_id', 'user_id', 'order_id', 'amount_discounted', 'currency_code'];
            $missing = [];
            foreach ($required as $f) {
                if (!isset($data[$f]) || $data[$f] === '') $missing[] = $f;
            }
            if ($missing) { ResponseFormatter::error('Missing required fields: ' . implode(', ', $missing), 422); break; }

            $stmt = $pdo->prepare("INSERT INTO discount_redemptions (discount_id, user_id, order_id, amount_discounted, currency_code, redeemed_at) VALUES (:discount_id, :user_id, :order_id, :amount_discounted, :currency_code, NOW())");
            $stmt->execute([
                ':discount_id'       => (int)$data['discount_id'],
                ':user_id'           => (int)$data['user_id'],
                ':order_id'          => (int)$data['order_id'],
                ':amount_discounted' => $data['amount_discounted'],
                ':currency_code'     => $data['currency_code'],
            ]);
            ResponseFormatter::success(['id' => (int)$pdo->lastInsertId()], 'Redemption recorded', 201);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

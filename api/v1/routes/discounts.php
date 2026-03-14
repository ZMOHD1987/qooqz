<?php
declare(strict_types=1);

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

date_default_timezone_set('Asia/Riyadh');

// Load dependencies
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';
require_once dirname(__DIR__, 2) . '/v1/models/discounts/repositories/PdoDiscountsRepository.php';

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
            if (isset($_GET['stats'])) {
                $stmt = $pdo->prepare("
                    SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN status = 'active' AND (ends_at IS NULL OR ends_at >= NOW()) THEN 1 ELSE 0 END) AS active,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
                        SUM(CASE WHEN ends_at IS NOT NULL AND ends_at < NOW() THEN 1 ELSE 0 END) AS expired,
                        SUM(CASE WHEN starts_at IS NOT NULL AND starts_at > NOW() AND status = 'active' THEN 1 ELSE 0 END) AS scheduled
                    FROM discounts
                ");
                $stmt->execute();
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                ResponseFormatter::success($stats);
                break;
            }
            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                $stmt = $pdo->prepare("SELECT * FROM discounts WHERE id = :id");
                $stmt->execute([':id' => (int)$_GET['id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) { ResponseFormatter::error('Discount not found', 404); break; }
                ResponseFormatter::success($item);
            } else {
                $where  = [];
                $params = [];
                if (isset($_GET['entity_id'])) {
                    $where[]            = 'entity_id = :entity_id';
                    $params[':entity_id'] = $_GET['entity_id'];
                }
                if (isset($_GET['status'])) {
                    $where[]          = 'status = :status';
                    $params[':status'] = $_GET['status'];
                }
                if (isset($_GET['type'])) {
                    $where[]        = 'type = :type';
                    $params[':type'] = $_GET['type'];
                }
                if (isset($_GET['search'])) {
                    $where[]          = 'code LIKE :search';
                    $params[':search'] = '%' . $_GET['search'] . '%';
                }
                $sql = "SELECT * FROM discounts";
                if ($where) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }
                $sql .= ' ORDER BY created_at DESC';

                // Count
                $countSql  = "SELECT COUNT(*) FROM discounts" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = (int)$countStmt->fetchColumn();

                $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                $sql   .= ' LIMIT :limit OFFSET :offset';

                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                ResponseFormatter::success(['items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $required = ['entity_id', 'type', 'currency_code'];
            $missing = [];
            foreach ($required as $f) {
                if (!isset($data[$f]) || $data[$f] === '') $missing[] = $f;
            }
            if ($missing) { ResponseFormatter::error('Missing required fields: ' . implode(', ', $missing), 422); break; }

            $stmt = $pdo->prepare("INSERT INTO discounts (entity_id, type, code, auto_apply, priority, is_stackable, currency_code, max_redemptions, max_redemptions_per_user, current_redemptions, starts_at, ends_at, status, created_by, created_at, updated_at) VALUES (:entity_id, :type, :code, :auto_apply, :priority, :is_stackable, :currency_code, :max_redemptions, :max_redemptions_per_user, :current_redemptions, :starts_at, :ends_at, :status, :created_by, NOW(), NOW())");
            $stmt->execute([
                ':entity_id'              => $data['entity_id'],
                ':type'                   => $data['type'],
                ':code'                   => $data['code'] ?? null,
                ':auto_apply'             => (int)($data['auto_apply'] ?? 0),
                ':priority'               => (int)($data['priority'] ?? 0),
                ':is_stackable'           => (int)($data['is_stackable'] ?? 0),
                ':currency_code'          => $data['currency_code'],
                ':max_redemptions'        => $data['max_redemptions'] ?? null,
                ':max_redemptions_per_user' => $data['max_redemptions_per_user'] ?? null,
                ':current_redemptions'    => (int)($data['current_redemptions'] ?? 0),
                ':starts_at'              => $data['starts_at'] ?? null,
                ':ends_at'                => $data['ends_at'] ?? null,
                ':status'                 => $data['status'] ?? 'active',
                ':created_by'             => $data['created_by'] ?? null,
            ]);
            ResponseFormatter::success(['id' => (int)$pdo->lastInsertId()], 'Discount created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }

            $allowed = ['entity_id', 'type', 'code', 'auto_apply', 'priority', 'is_stackable', 'currency_code', 'max_redemptions', 'max_redemptions_per_user', 'current_redemptions', 'starts_at', 'ends_at', 'status', 'created_by'];
            $sets   = [];
            $params = [':id' => $id];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[]           = "$col = :$col";
                    $params[":$col"]  = $data[$col];
                }
            }
            if (empty($sets)) { ResponseFormatter::error('No fields to update', 422); break; }
            $sets[] = 'updated_at = NOW()';

            $stmt = $pdo->prepare("UPDATE discounts SET " . implode(', ', $sets) . " WHERE id = :id");
            $stmt->execute($params);
            ResponseFormatter::success(null, 'Discount updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $stmt = $pdo->prepare("DELETE FROM discounts WHERE id = :id");
            $stmt->execute([':id' => $id]);
            ResponseFormatter::success(null, 'Discount deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}


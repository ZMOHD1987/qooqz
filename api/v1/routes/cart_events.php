<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ================================
// Tenant & Auth check
// ================================
$user = $_SESSION['user'] ?? [];
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Allowed order columns
    $allowedOrder = ['id', 'cart_id', 'event_type', 'actor_type', 'created_at'];
    if (!in_array($orderBy, $allowedOrder, true)) $orderBy = 'id';
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // Get single event by ID
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM cart_events WHERE id = :id");
                $stmt->execute([':id' => (int)$_GET['id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                ResponseFormatter::success($item ?: null);
                break;
            }

            // Build query
            $where = ['1=1'];
            $params = [];

            if (isset($_GET['cart_id']) && is_numeric($_GET['cart_id'])) {
                $where[] = 'ce.cart_id = :cart_id';
                $params[':cart_id'] = (int)$_GET['cart_id'];
            }
            if (!empty($_GET['event_type'])) {
                $where[] = 'ce.event_type = :event_type';
                $params[':event_type'] = $_GET['event_type'];
            }
            if (!empty($_GET['actor_type'])) {
                $where[] = 'ce.actor_type = :actor_type';
                $params[':actor_type'] = $_GET['actor_type'];
            }
            if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
                $where[] = 'ce.entity_id = :entity_id';
                $params[':entity_id'] = (int)$_GET['entity_id'];
            }

            $whereStr = implode(' AND ', $where);

            // Count
            $countSql = "SELECT COUNT(*) FROM cart_events ce WHERE {$whereStr}";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch
            $sql = "SELECT ce.* FROM cart_events ce WHERE {$whereStr} ORDER BY ce.{$orderBy} {$orderDir} LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ResponseFormatter::success([
                'items' => $items,
                'meta'  => [
                    'total'       => $total,
                    'page'        => $page,
                    'per_page'    => $limit,
                    'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                    'from'        => $total > 0 ? $offset + 1 : 0,
                    'to'          => $total > 0 ? min($offset + $limit, $total) : 0
                ]
            ]);
            break;

        case 'POST':
            // Validate required fields
            if (empty($data['cart_id']) || !is_numeric($data['cart_id'])) {
                ResponseFormatter::error('cart_id is required', 422);
                exit;
            }
            if (empty($data['event_type'])) {
                ResponseFormatter::error('event_type is required', 422);
                exit;
            }
            $validActors = ['user', 'admin', 'system'];
            $actorType = $data['actor_type'] ?? 'system';
            if (!in_array($actorType, $validActors, true)) {
                ResponseFormatter::error('actor_type must be user, admin, or system', 422);
                exit;
            }

            $entityId = isset($data['entity_id']) && is_numeric($data['entity_id'])
                ? (int)$data['entity_id']
                : (isset($_SESSION['entity_id']) ? (int)$_SESSION['entity_id'] : (int)($tenantId ?? 1));

            $stmt = $pdo->prepare("
                INSERT INTO cart_events (entity_id, cart_id, event_type, actor_type, actor_id, related_item_id, old_value, new_value, note)
                VALUES (:entity_id, :cart_id, :event_type, :actor_type, :actor_id, :related_item_id, :old_value, :new_value, :note)
            ");
            $stmt->execute([
                ':entity_id'       => $entityId,
                ':cart_id'         => (int)$data['cart_id'],
                ':event_type'      => $data['event_type'],
                ':actor_type'      => $actorType,
                ':actor_id'        => isset($data['actor_id']) && is_numeric($data['actor_id']) ? (int)$data['actor_id'] : null,
                ':related_item_id' => isset($data['related_item_id']) && is_numeric($data['related_item_id']) ? (int)$data['related_item_id'] : null,
                ':old_value'       => $data['old_value'] ?? null,
                ':new_value'       => $data['new_value'] ?? null,
                ':note'            => isset($data['note']) ? substr($data['note'], 0, 255) : null
            ]);
            $newId = (int)$pdo->lastInsertId();
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing event ID for deletion', 400);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM cart_events WHERE id = :id");
            $deleted = $stmt->execute([':id' => (int)$data['id']]);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'cart_events.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('critical', 'cart_events.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error', 500);
}


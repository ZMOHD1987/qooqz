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
require_once dirname(__DIR__, 2) . '/v1/models/stock_movements/repositories/PdoStockMovementsRepository.php';
require_once dirname(__DIR__, 2) . '/v1/models/stock_movements/validators/StockMovementsValidator.php';
require_once dirname(__DIR__, 2) . '/v1/models/stock_movements/services/StockMovementsService.php';
require_once dirname(__DIR__, 2) . '/v1/models/stock_movements/controllers/StockMovementsController.php';

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
                $repo = new PdoStockMovementsRepository($pdo);
                $filters = [];
                if (isset($_GET['product_id'])) $filters['product_id'] = $_GET['product_id'];
                if (isset($_GET['type'])) $filters['type'] = $_GET['type'];
                if (isset($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
                if (isset($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
                $stats = $repo->stats($filters);
                ResponseFormatter::success($stats);
                break;
            }

            if (isset($_GET['barcode']) && $_GET['barcode'] !== '') {
                $repo = new PdoStockMovementsRepository($pdo);
                $row = $repo->lookupByBarcode(trim($_GET['barcode']));
                if (!$row) {
                    ResponseFormatter::error('Barcode not found', 404);
                    break;
                }
                ResponseFormatter::success($row);
                break;
            }

            if (isset($_GET['sku']) && $_GET['sku'] !== '') {
                $sku = trim($_GET['sku']);
                $lang = $_GET['lang'] ?? ($_SESSION['user']['preferred_language'] ?? 'ar');
                // Search products.sku
                $stmt = $pdo->prepare("
                    SELECT p.id, p.sku, p.barcode, p.stock_quantity, p.stock_status,
                           pt.name AS product_name, NULL AS variant_id
                    FROM products p
                    LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
                    WHERE p.sku = :sku
                    LIMIT 1
                ");
                $stmt->execute([':sku' => $sku, ':lang' => $lang]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    // Search product_variants.sku
                    $stmt2 = $pdo->prepare("
                        SELECT p.id, pv.sku, pv.barcode, pv.stock_quantity, 'variant' AS stock_status,
                               pt.name AS product_name, pv.id AS variant_id
                        FROM product_variants pv
                        JOIN products p ON p.id = pv.product_id
                        LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
                        WHERE pv.sku = :sku
                        LIMIT 1
                    ");
                    $stmt2->execute([':sku' => $sku, ':lang' => $lang]);
                    $row = $stmt2->fetch(PDO::FETCH_ASSOC);
                }
                if (!$row) {
                    ResponseFormatter::error('SKU not found', 404);
                    break;
                }
                ResponseFormatter::success($row);
                break;
            }

            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                $stmt = $pdo->prepare("
                    SELECT sm.*, pt.name AS product_name
                    FROM product_stock_movements sm
                    LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
                    WHERE sm.id = :id
                ");
                $stmt->execute([':id' => (int)$_GET['id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) { ResponseFormatter::error('Stock movement not found', 404); break; }
                ResponseFormatter::success($item);
            } elseif (isset($_GET['product_id']) && (int)$_GET['product_id'] > 0) {
                $stmt = $pdo->prepare("
                    SELECT sm.*, pt.name AS product_name
                    FROM product_stock_movements sm
                    LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'
                    WHERE sm.product_id = :product_id
                    ORDER BY sm.created_at DESC
                ");
                $stmt->execute([':product_id' => (int)$_GET['product_id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ResponseFormatter::success($items);
            } else {
                $where  = [];
                $params = [];
                if (isset($_GET['type']) && $_GET['type'] !== '') {
                    $where[]        = 'sm.type = :type';
                    $params[':type'] = $_GET['type'];
                }
                if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
                    $where[]             = 'sm.created_at >= :date_from';
                    $params[':date_from'] = $_GET['date_from'];
                }
                if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
                    $where[]           = 'sm.created_at <= :date_to';
                    $params[':date_to'] = $_GET['date_to'] . ' 23:59:59';
                }
                if (isset($_GET['search']) && $_GET['search'] !== '') {
                    $where[] = '(EXISTS (
                        SELECT 1 FROM product_translations pt2
                        WHERE pt2.product_id = sm.product_id AND pt2.name LIKE :search
                    ) OR EXISTS (
                        SELECT 1 FROM products p2
                        WHERE p2.id = sm.product_id AND (p2.sku LIKE :search_sku OR p2.barcode LIKE :search_barcode)
                    ))';
                    $params[':search']         = '%' . $_GET['search'] . '%';
                    $params[':search_sku']     = '%' . $_GET['search'] . '%';
                    $params[':search_barcode'] = '%' . $_GET['search'] . '%';
                }

                $sql = "SELECT sm.*, pt.name AS product_name
                        FROM product_stock_movements sm
                        LEFT JOIN product_translations pt ON pt.product_id = sm.product_id AND pt.language_code = 'en'";
                if ($where) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }
                $sql .= ' ORDER BY sm.created_at DESC';

                // Count
                $countSql  = "SELECT COUNT(*) FROM product_stock_movements sm" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
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

            $validation = StockMovementsValidator::validateCreate($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                break;
            }

            $repo = new PdoStockMovementsRepository($pdo);
            $id = $repo->create($data);
            ResponseFormatter::success(['id' => $id], 'Stock movement created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($_GET['id'] ?? ($data['id'] ?? 0));
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }

            $validation = StockMovementsValidator::validateCreate($data);
            if (!$validation['valid']) {
                ResponseFormatter::error('Validation failed: ' . implode(', ', $validation['errors']), 422);
                break;
            }

            // Get old record to reverse stock
            $repo = new PdoStockMovementsRepository($pdo);
            $old = $repo->find($id);
            if (!$old) { ResponseFormatter::error('Movement not found', 404); break; }

            // Reverse old stock change
            $reverseQty = -1 * (int)$old['change_quantity'];
            if ($old['variant_id']) {
                $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + :qty WHERE id = :vid")
                    ->execute([':qty' => $reverseQty, ':vid' => $old['variant_id']]);
            }
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid")
                ->execute([':qty' => $reverseQty, ':pid' => $old['product_id']]);

            // Update movement record
            $pdo->prepare("
                UPDATE product_stock_movements
                SET product_id = :product_id, variant_id = :variant_id, change_quantity = :qty,
                    type = :type, reference_id = :ref_id, notes = :notes
                WHERE id = :id
            ")->execute([
                ':product_id' => (int)$data['product_id'],
                ':variant_id' => isset($data['variant_id']) ? (int)$data['variant_id'] : null,
                ':qty' => (int)$data['change_quantity'],
                ':type' => $data['type'],
                ':ref_id' => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
                ':notes' => $data['notes'] ?? null,
                ':id' => $id
            ]);

            // Apply new stock change
            $newQty = (int)$data['change_quantity'];
            if (isset($data['variant_id']) && $data['variant_id']) {
                $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + :qty WHERE id = :vid")
                    ->execute([':qty' => $newQty, ':vid' => (int)$data['variant_id']]);
            }
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid")
                ->execute([':qty' => $newQty, ':pid' => (int)$data['product_id']]);

            ResponseFormatter::success(['id' => $id], 'Stock movement updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $repo = new PdoStockMovementsRepository($pdo);
            $repo->delete($id);
            ResponseFormatter::success(null, 'Stock movement deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}


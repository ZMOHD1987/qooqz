<?php
declare(strict_types=1);

final class PdoPosSessionsRepository implements PosSessionsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'pos_sessions';
    private const ALLOWED_ORDER_BY = [
        'ps.id', 'ps.opened_at', 'ps.closed_at', 'ps.status',
        'ps.total_sales', 'ps.opening_balance',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =====================================================
     * Shared SELECT fragment
     * ===================================================== */
    private function baseSelect(): string
    {
        return "SELECT ps.*,
                       tu.user_id AS cashier_system_user_id,
                       u.username AS cashier_name,
                       e.store_name,
                       e.tenant_id AS entity_tenant_id
                FROM pos_sessions ps
                LEFT JOIN tenant_users tu ON ps.cashier_user_id = tu.id
                LEFT JOIN users u ON tu.user_id = u.id
                LEFT JOIN entities e ON ps.entity_id = e.id";
    }

    /* =====================================================
     * List + Filters + Pagination
     * ===================================================== */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'ps.opened_at',
        string $orderDir = 'DESC'
    ): array {
        $where  = ['ps.tenant_id = :tenant_id'];
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['entity_id'])) {
            $where[] = 'ps.entity_id = :entity_id';
            $params[':entity_id'] = (int)$filters['entity_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['open', 'closed'], true)) {
            $where[] = 'ps.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['cashier_user_id'])) {
            $where[] = 'ps.cashier_user_id = :cashier_user_id';
            $params[':cashier_user_id'] = (int)$filters['cashier_user_id'];
        }

        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'ps.opened_at';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $sql = $this->baseSelect() . " $whereClause ORDER BY $orderBy $orderDir";

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
     * Count
     * ===================================================== */
    public function count(int $tenantId, array $filters = []): int
    {
        $where  = ['tenant_id = :tenant_id'];
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['entity_id'])) {
            $where[] = 'entity_id = :entity_id';
            $params[':entity_id'] = (int)$filters['entity_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['open', 'closed'], true)) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['cashier_user_id'])) {
            $where[] = 'cashier_user_id = :cashier_user_id';
            $params[':cashier_user_id'] = (int)$filters['cashier_user_id'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM " . self::TABLE . " $whereClause");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /* =====================================================
     * Find by ID
     * ===================================================== */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            $this->baseSelect() . " WHERE ps.id = :id AND ps.tenant_id = :tid LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':tid' => $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =====================================================
     * Find open session
     * ===================================================== */
    public function findOpen(int $tenantId, ?int $entityId = null): ?array
    {
        $where  = "ps.tenant_id = :tid AND ps.status = 'open'";
        $params = [':tid' => $tenantId];

        if ($entityId !== null) {
            $where .= ' AND ps.entity_id = :eid';
            $params[':eid'] = $entityId;
        }

        $stmt = $this->pdo->prepare(
            $this->baseSelect() . " WHERE $where ORDER BY ps.opened_at DESC LIMIT 1"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =====================================================
     * Open a session
     * ===================================================== */
    public function open(int $tenantId, array $data): int
    {
        // Guard: no open session for the same entity
        $existing = $this->findOpen($tenantId, (int)($data['entity_id'] ?? 0));
        if ($existing) {
            throw new RuntimeException('A session is already open for this entity. Close it first.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO pos_sessions (tenant_id, entity_id, cashier_user_id, opening_balance, status, opened_at)
            VALUES (:tid, :eid, :cuid, :bal, 'open', NOW())
        ");
        $stmt->execute([
            ':tid'  => $tenantId,
            ':eid'  => (int)$data['entity_id'],
            ':cuid' => isset($data['cashier_user_id']) ? (int)$data['cashier_user_id'] : null,
            ':bal'  => (float)($data['opening_balance'] ?? 0),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /* =====================================================
     * Close a session
     * ===================================================== */
    public function close(int $tenantId, int $sessionId, array $data): bool
    {
        $session = $this->find($tenantId, $sessionId);
        if (!$session) {
            throw new RuntimeException('Session not found');
        }
        if ($session['status'] === 'closed') {
            throw new RuntimeException('Session is already closed');
        }

        // Compute totals from linked orders
        $totals = $this->fetchOne("
            SELECT
                COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.grand_total ELSE 0 END), 0) AS total_sales,
                COALESCE(SUM(CASE WHEN p.payment_method LIKE '%cash%' THEN o.grand_total ELSE 0 END), 0) AS total_cash
            FROM orders o
            LEFT JOIN (
                SELECT order_id, MAX(payment_method) AS payment_method FROM payments GROUP BY order_id
            ) p ON p.order_id = o.id
            WHERE o.pos_session_id = :sid AND o.tenant_id = :tid
        ", [':sid' => $sessionId, ':tid' => $tenantId]) ?? [];

        $stmt = $this->pdo->prepare("
            UPDATE pos_sessions
            SET status = 'closed',
                closed_at = NOW(),
                closing_balance = :cb,
                total_cash  = :tc
            WHERE id = :id AND tenant_id = :tid
        ");
        return $stmt->execute([
            ':cb'  => isset($data['closing_balance']) ? (float)$data['closing_balance'] : (float)$session['opening_balance'],
            ':tc'  => (float)($totals['total_cash'] ?? 0),
            ':id'  => $sessionId,
            ':tid' => $tenantId,
        ]);
        // Note: total_sales is a STORED GENERATED column on the DB side; no manual update needed.
    }

    /* =====================================================
     * Create POS order
     * ===================================================== */
    public function createOrder(int $tenantId, array $data): array
    {
        $sessionId     = (int)($data['session_id'] ?? 0);
        $entityId      = (int)($data['entity_id'] ?? 0);
        $items         = $data['items'] ?? [];
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $amountPaid    = (float)($data['amount_paid'] ?? 0);
        $discount      = (float)($data['discount_amount'] ?? 0);
        $notes         = $data['notes'] ?? '';
        $cashierUserId = isset($data['cashier_user_id']) ? (int)$data['cashier_user_id'] : null;
        $customerId    = isset($data['customer_id']) ? (int)$data['customer_id'] : 1;

        // Verify open session
        $session = $this->fetchOne(
            "SELECT * FROM pos_sessions WHERE id = :id AND tenant_id = :tid AND status = 'open'",
            [':id' => $sessionId, ':tid' => $tenantId]
        );
        if (!$session) {
            throw new RuntimeException('Session not found or not open');
        }

        // Calculate totals
        $subtotal   = 0.0;
        $taxAmount  = 0.0;
        $orderItems = [];

        foreach ($items as $item) {
            $qty       = max(1, (int)($item['quantity'] ?? 1));
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $salePrice = isset($item['sale_price']) ? (float)$item['sale_price'] : $unitPrice;
            $taxRate   = (float)($item['tax_rate'] ?? 0);

            $itemSubtotal = $salePrice * $qty;
            $itemTax      = $itemSubtotal * ($taxRate / 100);

            $subtotal  += $itemSubtotal;
            $taxAmount += $itemTax;

            $orderItems[] = [
                'product_id'   => (int)($item['product_id'] ?? 0),
                'product_name' => $item['product_name'] ?? 'Product',
                'sku'          => $item['sku'] ?? '',
                'quantity'     => $qty,
                'unit_price'   => $unitPrice,
                'sale_price'   => $salePrice,
                'tax_rate'     => $taxRate,
                'tax_amount'   => $itemTax,
                'subtotal'     => $itemSubtotal,
                'total'        => $itemSubtotal + $itemTax,
            ];
        }

        $totalAmount = $subtotal + $taxAmount;
        $grandTotal  = max(0.0, $totalAmount - $discount);
        $orderNumber = 'POS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (
                    tenant_id, entity_id, order_number, user_id, order_type,
                    status, payment_status, subtotal, tax_amount, discount_amount,
                    total_amount, grand_total, currency_code,
                    customer_notes, pos_session_id, cashier_user_id,
                    branch_entity_id, sales_channel, payment_method, created_at
                ) VALUES (
                    :tid, :eid, :onum, :uid, 'pos',
                    'completed', 'paid', :sub, :tax, :disc,
                    :tot, :grand, 'SAR',
                    :notes, :sid, :cuid,
                    :beid, 'pos', :pm, NOW()
                )
            ");
            $stmt->execute([
                ':tid'   => $tenantId,
                ':eid'   => $entityId,
                ':onum'  => $orderNumber,
                ':uid'   => $customerId,
                ':sub'   => $subtotal,
                ':tax'   => $taxAmount,
                ':disc'  => $discount,
                ':tot'   => $totalAmount,
                ':grand' => $grandTotal,
                ':notes' => $notes,
                ':sid'   => $sessionId,
                ':cuid'  => $cashierUserId,
                ':beid'  => $entityId,
                ':pm'    => $paymentMethod,
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            $iStmt = $this->pdo->prepare("
                INSERT INTO order_items (
                    tenant_id, order_id, entity_id, product_id,
                    product_name, sku, quantity, unit_price, sale_price,
                    tax_rate, tax_amount, subtotal, total, currency_code, created_at
                ) VALUES (
                    :tid, :oid, :eid, :pid,
                    :pname, :sku, :qty, :up, :sp,
                    :tr, :ta, :sub, :tot, 'SAR', NOW()
                )
            ");
            foreach ($orderItems as $oi) {
                $iStmt->execute([
                    ':tid'   => $tenantId,
                    ':oid'   => $orderId,
                    ':eid'   => $entityId,
                    ':pid'   => $oi['product_id'],
                    ':pname' => $oi['product_name'],
                    ':sku'   => $oi['sku'],
                    ':qty'   => $oi['quantity'],
                    ':up'    => $oi['unit_price'],
                    ':sp'    => $oi['sale_price'],
                    ':tr'    => $oi['tax_rate'],
                    ':ta'    => $oi['tax_amount'],
                    ':sub'   => $oi['subtotal'],
                    ':tot'   => $oi['total'],
                ]);
            }

            $this->pdo->prepare("
                UPDATE pos_sessions
                SET total_cash = total_cash + :cash,
                    total_card = total_card + :card
                WHERE id = :id AND tenant_id = :tid
            ")->execute([
                ':cash' => in_array($paymentMethod, ['cash', 'mixed'], true) ? $grandTotal : 0,
                ':card' => in_array($paymentMethod, ['card', 'mixed'], true) ? $grandTotal : 0,
                ':id'   => $sessionId,
                ':tid'  => $tenantId,
            ]);

            // Record payment in payments table so session close can compute totals correctly
            $paymentNumber = 'PAY-POS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
            $this->pdo->prepare("
                INSERT INTO payments
                    (payment_number, entity_id, order_id, user_id, payment_method,
                     amount, currency_code, status, payment_type, created_at, updated_at)
                VALUES
                    (:pnum, :eid, :oid, :uid, :pm,
                     :amt, 'SAR', 'paid', 'pos_order', NOW(), NOW())
            ")->execute([
                ':pnum' => $paymentNumber,
                ':eid'  => $entityId,
                ':oid'  => $orderId,
                ':uid'  => $customerId,
                ':pm'   => $paymentMethod,
                ':amt'  => $grandTotal,
            ]);

            $this->pdo->commit();

            return [
                'order_id'     => $orderId,
                'order_number' => $orderNumber,
                'grand_total'  => $grandTotal,
                'change'       => max(0.0, $amountPaid - $grandTotal),
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* =====================================================
     * Orders for a session (with optional date/payment filters)
     * ===================================================== */
    public function sessionOrders(int $tenantId, int $sessionId, array $filters = []): array
    {
        $where  = ['o.tenant_id = :tid', 'o.pos_session_id = :sid'];
        $params = [':tid' => $tenantId, ':sid' => $sessionId];

        // Date range filter (inclusive)
        if (!empty($filters['date_from'])) {
            $where[]              = 'DATE(o.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]            = 'DATE(o.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        // Payment method filter – exact match (LIKE with escaped special chars)
        if (!empty($filters['payment_method'])) {
            // Escape LIKE special characters to prevent pattern manipulation
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['payment_method']);
            $where[]                   = 'o.payment_method LIKE :payment_method ESCAPE \'\\\\\'';
            $params[':payment_method'] = '%' . $escaped . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare("
            SELECT o.*, u.username AS customer_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            $whereClause
            ORDER BY o.created_at DESC
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
     * Internal helpers
     * ===================================================== */
    private function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
<?php
declare(strict_types=1);

final class PdoOrdersRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'order_number', 'user_id', 'status', 'payment_status',
        'total_amount', 'grand_total', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'user_id', 'status', 'payment_status', 'fulfillment_status', 'order_type'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "SELECT o.* FROM orders o 
                INNER JOIN entities e ON o.user_id = e.user_id 
                WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND o.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // Date range filters
        if (!empty($filters['created_from'])) {
            $sql .= " AND o.created_at >= :created_from";
            $params[':created_from'] = $filters['created_from'];
        }
        if (!empty($filters['created_to'])) {
            $sql .= " AND o.created_at <= :created_to";
            $params[':created_to'] = $filters['created_to'];
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY o.{$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit !== null) $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM orders o 
                INNER JOIN entities e ON o.user_id = e.user_id 
                WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND o.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['created_from'])) {
            $sql .= " AND o.created_at >= :created_from";
            $params[':created_from'] = $filters['created_from'];
        }
        if (!empty($filters['created_to'])) {
            $sql .= " AND o.created_at <= :created_to";
            $params[':created_to'] = $filters['created_to'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.* FROM orders o
            INNER JOIN entities e ON o.user_id = e.user_id
            WHERE e.tenant_id = :tenant_id AND o.id = :id LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByOrderNumber(int $tenantId, string $orderNumber): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.* FROM orders o
            INNER JOIN entities e ON o.user_id = e.user_id
            WHERE e.tenant_id = :tenant_id AND o.order_number = :order_number LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':order_number' => $orderNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private const ORDER_COLUMNS = [
        'order_number', 'user_id', 'cart_id', 'order_type', 'status', 'payment_status',
        'fulfillment_status', 'subtotal', 'tax_amount', 'shipping_cost', 'discount_amount',
        'coupon_discount', 'loyalty_points_discount', 'wallet_amount_used', 'total_amount',
        'grand_total', 'currency_code', 'coupon_code', 'loyalty_points_used', 'loyalty_points_earned',
        'shipping_address_id', 'billing_address_id', 'delivery_company_id', 'estimated_delivery_date',
        'actual_delivery_date', 'customer_notes', 'internal_notes', 'ip_address', 'user_agent',
        'is_gift', 'gift_message', 'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
        'cancellation_reason', 'assigned_driver_id'
    ];

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::ORDER_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // Generate order number if not provided
        if (empty($params[':order_number'])) {
            $params[':order_number'] = $this->generateOrderNumber($tenantId);
        }

        // Set defaults
        if (empty($params[':currency_code'])) $params[':currency_code'] = 'SAR';
        if (empty($params[':status'])) $params[':status'] = 'pending';
        if (empty($params[':payment_status'])) $params[':payment_status'] = 'pending';
        if (empty($params[':fulfillment_status'])) $params[':fulfillment_status'] = 'unfulfilled';
        if (empty($params[':order_type'])) $params[':order_type'] = 'online';

        if ($isUpdate) {
            // Verify order belongs to tenant
            $checkStmt = $this->pdo->prepare("
                SELECT o.id FROM orders o
                INNER JOIN entities e ON o.user_id = e.user_id
                WHERE o.id = :id AND e.tenant_id = :tenant_id
            ");
            $checkStmt->execute([':id' => $data['id'], ':tenant_id' => $tenantId]);
            if (!$checkStmt->fetch()) {
                throw new RuntimeException('Order not found or access denied');
            }

            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE orders SET
                    order_number = :order_number, user_id = :user_id, cart_id = :cart_id,
                    order_type = :order_type, status = :status, payment_status = :payment_status,
                    fulfillment_status = :fulfillment_status, subtotal = :subtotal, tax_amount = :tax_amount,
                    shipping_cost = :shipping_cost, discount_amount = :discount_amount,
                    coupon_discount = :coupon_discount, loyalty_points_discount = :loyalty_points_discount,
                    wallet_amount_used = :wallet_amount_used, total_amount = :total_amount,
                    grand_total = :grand_total, currency_code = :currency_code, coupon_code = :coupon_code,
                    loyalty_points_used = :loyalty_points_used, loyalty_points_earned = :loyalty_points_earned,
                    shipping_address_id = :shipping_address_id, billing_address_id = :billing_address_id,
                    delivery_company_id = :delivery_company_id, estimated_delivery_date = :estimated_delivery_date,
                    actual_delivery_date = :actual_delivery_date, customer_notes = :customer_notes,
                    internal_notes = :internal_notes, ip_address = :ip_address, user_agent = :user_agent,
                    is_gift = :is_gift, gift_message = :gift_message, confirmed_at = :confirmed_at,
                    shipped_at = :shipped_at, delivered_at = :delivered_at, cancelled_at = :cancelled_at,
                    cancellation_reason = :cancellation_reason, assigned_driver_id = :assigned_driver_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        // Verify user belongs to tenant
        $checkStmt = $this->pdo->prepare("SELECT id FROM users WHERE id = :user_id AND tenant_id = :tenant_id");
        $checkStmt->execute([':user_id' => $params[':user_id'], ':tenant_id' => $tenantId]);
        if (!$checkStmt->fetch()) {
            throw new RuntimeException('User not found or access denied');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO orders (
                order_number, user_id, cart_id, order_type, status, payment_status,
                fulfillment_status, subtotal, tax_amount, shipping_cost, discount_amount,
                coupon_discount, loyalty_points_discount, wallet_amount_used, total_amount,
                grand_total, currency_code, coupon_code, loyalty_points_used, loyalty_points_earned,
                shipping_address_id, billing_address_id, delivery_company_id, estimated_delivery_date,
                actual_delivery_date, customer_notes, internal_notes, ip_address, user_agent,
                is_gift, gift_message, confirmed_at, shipped_at, delivered_at, cancelled_at,
                cancellation_reason, assigned_driver_id
            ) VALUES (
                :order_number, :user_id, :cart_id, :order_type, :status, :payment_status,
                :fulfillment_status, :subtotal, :tax_amount, :shipping_cost, :discount_amount,
                :coupon_discount, :loyalty_points_discount, :wallet_amount_used, :total_amount,
                :grand_total, :currency_code, :coupon_code, :loyalty_points_used, :loyalty_points_earned,
                :shipping_address_id, :billing_address_id, :delivery_company_id, :estimated_delivery_date,
                :actual_delivery_date, :customer_notes, :internal_notes, :ip_address, :user_agent,
                :is_gift, :gift_message, :confirmed_at, :shipped_at, :delivered_at, :cancelled_at,
                :cancellation_reason, :assigned_driver_id
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        // First verify tenant ownership
        $checkStmt = $this->pdo->prepare("
            SELECT o.id FROM orders o
            INNER JOIN entities e ON o.user_id = e.user_id
            WHERE o.id = :id AND e.tenant_id = :tenant_id
        ");
        $checkStmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        if (!$checkStmt->fetch()) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE orders SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }

    private function generateOrderNumber(int $tenantId): string
    {
        $prefix = 'ORD';
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return "{$prefix}-{$date}-{$random}";
    }
}

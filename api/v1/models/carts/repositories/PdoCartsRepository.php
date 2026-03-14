<?php
declare(strict_types=1);

final class PdoCartsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'user_id', 'session_id', 'total_items', 'subtotal', 
        'total_amount', 'status', 'last_activity_at', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'user_id', 'session_id', 'device_id', 'status', 'entity_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT c.*
            FROM carts c
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
        ";
        $params = [':tenant_id' => $tenantId];

        // Apply dynamic filters
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['session_id', 'device_id'])) {
                    $sql .= " AND c.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND c.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // Ordering
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY c.{$orderBy} {$orderDir}";

        // Pagination
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

    // ================================
    // Count for pagination
    // ================================
    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM carts c
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['session_id', 'device_id'])) {
                    $sql .= " AND c.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND c.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*
            FROM carts c
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
            AND c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by session
    // ================================
    public function findBySession(int $tenantId, string $sessionId, int $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*
            FROM carts c
            WHERE c.entity_id = :entity_id
            AND c.session_id = :session_id
            AND c.status = 'active'
            ORDER BY c.last_activity_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':entity_id' => $entityId,
            ':session_id' => $sessionId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by user
    // ================================
    public function findByUser(int $tenantId, int $userId, int $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*
            FROM carts c
            WHERE c.entity_id = :entity_id
            AND c.user_id = :user_id
            AND c.status = 'active'
            ORDER BY c.last_activity_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':entity_id' => $entityId,
            ':user_id' => $userId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Create / Update
    // ================================
    private const CART_COLUMNS = [
        'entity_id', 'user_id', 'session_id', 'device_id', 'ip_address',
        'total_items', 'subtotal', 'tax_amount', 'shipping_cost', 
        'discount_amount', 'total_amount', 'currency_code', 'coupon_code',
        'discount_id', 'items', 'loyalty_points_used', 'status',
        'last_activity_at', 'converted_to_order_id', 'expires_at'
    ];

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // Extract allowed columns
        $params = [];
        foreach (self::CART_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // Set defaults
        if (empty($params[':currency_code'])) {
            $params[':currency_code'] = 'SAR';
        }
        if (empty($params[':status'])) {
            $params[':status'] = 'active';
        }
        if (empty($params[':total_items'])) {
            $params[':total_items'] = 0;
        }

        // Update last_activity_at
        $params[':last_activity_at'] = date('Y-m-d H:i:s');

        if ($isUpdate) {
            // Verify cart belongs to tenant
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM carts 
                WHERE id = :id 
                AND entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
            ");
            $checkStmt->execute([':id' => $data['id'], ':tenant_id' => $tenantId]);
            if (!$checkStmt->fetch()) {
                throw new RuntimeException('Cart not found or access denied');
            }

            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE carts SET
                    entity_id = :entity_id,
                    user_id = :user_id,
                    session_id = :session_id,
                    device_id = :device_id,
                    ip_address = :ip_address,
                    total_items = :total_items,
                    subtotal = :subtotal,
                    tax_amount = :tax_amount,
                    shipping_cost = :shipping_cost,
                    discount_amount = :discount_amount,
                    total_amount = :total_amount,
                    currency_code = :currency_code,
                    coupon_code = :coupon_code,
                    discount_id = :discount_id,
                    items = :items,
                    loyalty_points_used = :loyalty_points_used,
                    status = :status,
                    last_activity_at = :last_activity_at,
                    converted_to_order_id = :converted_to_order_id,
                    expires_at = :expires_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        // Verify entity belongs to tenant
        $checkStmt = $this->pdo->prepare("
            SELECT id FROM entities WHERE id = :entity_id AND tenant_id = :tenant_id
        ");
        $checkStmt->execute([':entity_id' => $params[':entity_id'], ':tenant_id' => $tenantId]);
        if (!$checkStmt->fetch()) {
            throw new RuntimeException('Entity not found or access denied');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO carts (
                entity_id, user_id, session_id, device_id, ip_address,
                total_items, subtotal, tax_amount, shipping_cost,
                discount_amount, total_amount, currency_code, coupon_code,
                discount_id, items, loyalty_points_used, status,
                last_activity_at, converted_to_order_id, expires_at
            ) VALUES (
                :entity_id, :user_id, :session_id, :device_id, :ip_address,
                :total_items, :subtotal, :tax_amount, :shipping_cost,
                :discount_amount, :total_amount, :currency_code, :coupon_code,
                :discount_id, :items, :loyalty_points_used, :status,
                :last_activity_at, :converted_to_order_id, :expires_at
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete (soft delete - mark as expired)
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE carts 
            SET status = 'expired', updated_at = CURRENT_TIMESTAMP
            WHERE id = :id 
            AND entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    // ================================
    // Convert to order
    // ================================
    public function convertToOrder(int $tenantId, int $cartId, int $orderId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE carts 
            SET status = 'converted', 
                converted_to_order_id = :order_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :cart_id 
            AND entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
        ");
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':cart_id' => $cartId,
            ':order_id' => $orderId
        ]);
    }
}

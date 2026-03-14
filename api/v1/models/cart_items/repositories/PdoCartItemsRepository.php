<?php
declare(strict_types=1);

final class PdoCartItemsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'cart_id', 'product_id', 'quantity', 'unit_price', 
        'total', 'added_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'cart_id', 'product_id', 'product_variant_id', 'entity_id', 'sku'
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
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
        ";
        $params = [':tenant_id' => $tenantId];

        // Apply dynamic filters
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'sku') {
                    $sql .= " AND ci.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND ci.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // Ordering
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ci.{$orderBy} {$orderDir}";

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
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'sku') {
                    $sql .= " AND ci.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND ci.{$col} = :{$col}";
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
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
            AND ci.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by cart
    // ================================
    public function findByCart(int $tenantId, int $cartId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            WHERE c.entity_id IN (
                SELECT id FROM entities WHERE tenant_id = :tenant_id
            )
            AND ci.cart_id = :cart_id
            ORDER BY ci.added_at ASC
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':cart_id' => $cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================
    private const CART_ITEM_COLUMNS = [
        'cart_id', 'product_id', 'product_variant_id', 'entity_id',
        'product_name', 'sku', 'quantity', 'unit_price', 'sale_price',
        'discount_amount', 'tax_rate', 'tax_amount', 'subtotal', 'total',
        'currency_code', 'selected_attributes', 'special_instructions',
        'is_gift', 'gift_message'
    ];

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // Extract allowed columns
        $params = [];
        foreach (self::CART_ITEM_COLUMNS as $col) {
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
        if (empty($params[':quantity'])) {
            $params[':quantity'] = 1;
        }
        if (empty($params[':is_gift'])) {
            $params[':is_gift'] = 0;
        }

        // Calculate totals if not provided
        $quantity = (int)($params[':quantity'] ?? 1);
        $unitPrice = (float)($params[':unit_price'] ?? 0);
        $salePrice = isset($params[':sale_price']) && $params[':sale_price'] !== null 
            ? (float)$params[':sale_price'] 
            : $unitPrice;
        $discountAmount = (float)($params[':discount_amount'] ?? 0);
        $taxRate = (float)($params[':tax_rate'] ?? 0);

        // Calculate subtotal
        $subtotal = $quantity * $salePrice - $discountAmount;
        $params[':subtotal'] = $subtotal;

        // Calculate tax
        $taxAmount = $subtotal * ($taxRate / 100);
        $params[':tax_amount'] = $taxAmount;

        // Calculate total
        $params[':total'] = $subtotal + $taxAmount;

        if ($isUpdate) {
            // Verify item belongs to tenant
            $checkStmt = $this->pdo->prepare("
                SELECT ci.id 
                FROM cart_items ci
                INNER JOIN carts c ON ci.cart_id = c.id
                WHERE ci.id = :id 
                AND c.entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
            ");
            $checkStmt->execute([':id' => $data['id'], ':tenant_id' => $tenantId]);
            if (!$checkStmt->fetch()) {
                throw new RuntimeException('Cart item not found or access denied');
            }

            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE cart_items SET
                    cart_id = :cart_id,
                    product_id = :product_id,
                    product_variant_id = :product_variant_id,
                    entity_id = :entity_id,
                    product_name = :product_name,
                    sku = :sku,
                    quantity = :quantity,
                    unit_price = :unit_price,
                    sale_price = :sale_price,
                    discount_amount = :discount_amount,
                    tax_rate = :tax_rate,
                    tax_amount = :tax_amount,
                    subtotal = :subtotal,
                    total = :total,
                    currency_code = :currency_code,
                    selected_attributes = :selected_attributes,
                    special_instructions = :special_instructions,
                    is_gift = :is_gift,
                    gift_message = :gift_message,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            
            // Update cart totals
            $this->updateCartTotals($tenantId, (int)$params[':cart_id']);
            
            return (int)$data['id'];
        }

        // Verify cart belongs to tenant
        $checkStmt = $this->pdo->prepare("
            SELECT c.id 
            FROM carts c
            WHERE c.id = :cart_id 
            AND c.entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
        ");
        $checkStmt->execute([':cart_id' => $params[':cart_id'], ':tenant_id' => $tenantId]);
        if (!$checkStmt->fetch()) {
            throw new RuntimeException('Cart not found or access denied');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO cart_items (
                cart_id, product_id, product_variant_id, entity_id,
                product_name, sku, quantity, unit_price, sale_price,
                discount_amount, tax_rate, tax_amount, subtotal, total,
                currency_code, selected_attributes, special_instructions,
                is_gift, gift_message
            ) VALUES (
                :cart_id, :product_id, :product_variant_id, :entity_id,
                :product_name, :sku, :quantity, :unit_price, :sale_price,
                :discount_amount, :tax_rate, :tax_amount, :subtotal, :total,
                :currency_code, :selected_attributes, :special_instructions,
                :is_gift, :gift_message
            )
        ");
        $stmt->execute($params);
        $newId = (int)$this->pdo->lastInsertId();
        
        // Update cart totals
        $this->updateCartTotals($tenantId, (int)$params[':cart_id']);
        
        return $newId;
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        // Get cart_id before deleting
        $getCartStmt = $this->pdo->prepare("
            SELECT ci.cart_id
            FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            WHERE ci.id = :id 
            AND c.entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
        ");
        $getCartStmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $cartId = $getCartStmt->fetchColumn();
        
        if (!$cartId) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM cart_items 
            WHERE id = :id
        ");
        $result = $stmt->execute([':id' => $id]);
        
        // Update cart totals
        if ($result) {
            $this->updateCartTotals($tenantId, (int)$cartId);
        }
        
        return $result;
    }

    // ================================
    // Update cart totals
    // ================================
    private function updateCartTotals(int $tenantId, int $cartId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE carts c
            SET 
                c.total_items = (
                    SELECT COALESCE(SUM(quantity), 0) 
                    FROM cart_items 
                    WHERE cart_id = :cart_id
                ),
                c.subtotal = (
                    SELECT COALESCE(SUM(subtotal), 0) 
                    FROM cart_items 
                    WHERE cart_id = :cart_id2
                ),
                c.tax_amount = (
                    SELECT COALESCE(SUM(tax_amount), 0) 
                    FROM cart_items 
                    WHERE cart_id = :cart_id3
                ),
                c.total_amount = (
                    SELECT COALESCE(SUM(total), 0) 
                    FROM cart_items 
                    WHERE cart_id = :cart_id4
                ),
                c.last_activity_at = CURRENT_TIMESTAMP,
                c.updated_at = CURRENT_TIMESTAMP
            WHERE c.id = :cart_id5
            AND c.entity_id IN (SELECT id FROM entities WHERE tenant_id = :tenant_id)
        ");
        
        $stmt->execute([
            ':cart_id' => $cartId,
            ':cart_id2' => $cartId,
            ':cart_id3' => $cartId,
            ':cart_id4' => $cartId,
            ':cart_id5' => $cartId,
            ':tenant_id' => $tenantId
        ]);
    }
}

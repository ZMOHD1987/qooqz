<?php
declare(strict_types=1);

final class PdoOrderItemsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir, string $lang): array
    {
        $sql = "SELECT oi.* FROM order_items oi 
                INNER JOIN entities e ON oi.entity_id = e.id 
                WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['order_id'])) {
            $sql .= " AND oi.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        if (!empty($filters['product_id'])) {
            $sql .= " AND oi.product_id = :product_id";
            $params[':product_id'] = $filters['product_id'];
        }

        $sql .= " ORDER BY oi.id DESC";
        if ($limit) $sql .= " LIMIT :limit";
        if ($offset) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        if ($limit) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters): int
    {
        $sql = "SELECT COUNT(*) FROM order_items oi 
                INNER JOIN entities e ON oi.entity_id = e.id 
                WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];
        if (!empty($filters['order_id'])) {
            $sql .= " AND oi.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang): ?array
    {
        $stmt = $this->pdo->prepare("SELECT oi.* FROM order_items oi 
            INNER JOIN entities e ON oi.entity_id = e.id 
            WHERE e.tenant_id = :tenant_id AND oi.id = :id LIMIT 1");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);
        $fields = ['order_id', 'entity_id', 'product_id', 'product_variant_id', 'product_name', 'sku', 'quantity', 'unit_price', 'sale_price', 'discount_amount', 'tax_rate', 'tax_amount', 'subtotal', 'total', 'currency_code'];
        $params = [];
        foreach ($fields as $f) $params[':' . $f] = $data[$f] ?? null;

        if ($isUpdate) {
            $params[':id'] = $data['id'];
            $stmt = $this->pdo->prepare("UPDATE order_items SET order_id=:order_id, entity_id=:entity_id, product_id=:product_id, product_variant_id=:product_variant_id, product_name=:product_name, sku=:sku, quantity=:quantity, unit_price=:unit_price, sale_price=:sale_price, discount_amount=:discount_amount, tax_rate=:tax_rate, tax_amount=:tax_amount, subtotal=:subtotal, total=:total, currency_code=:currency_code WHERE id=:id");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("INSERT INTO order_items (order_id, entity_id, product_id, product_variant_id, product_name, sku, quantity, unit_price, sale_price, discount_amount, tax_rate, tax_amount, subtotal, total, currency_code) VALUES (:order_id, :entity_id, :product_id, :product_variant_id, :product_name, :sku, :quantity, :unit_price, :sale_price, :discount_amount, :tax_rate, :tax_amount, :subtotal, :total, :currency_code)");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM order_items WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}

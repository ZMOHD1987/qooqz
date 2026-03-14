<?php
declare(strict_types=1);

final class PdoOrderReviewsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir, string $lang): array
    {
        $sql = "SELECT r.* FROM order_reviews r 
                INNER JOIN orders o ON r.order_id = o.id 
                INNER JOIN entities e ON o.user_id = e.user_id 
                WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];
        
        if (!empty($filters['order_id'])) {
            $sql .= " AND r.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        if (!empty($filters['vendor_id'])) {
            $sql .= " AND r.vendor_id = :vendor_id";
            $params[':vendor_id'] = $filters['vendor_id'];
        }
        
        $sql .= " ORDER BY r.id DESC";
        if ($limit) $sql .= " LIMIT :limit";
        if ($offset) $sql .= " OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters): int
    {
        $sql = "SELECT COUNT(*) FROM order_reviews r 
                INNER JOIN orders o ON r.order_id = o.id 
                INNER JOIN entities e ON o.user_id = e.user_id 
                WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];
        
        if (!empty($filters['order_id'])) {
            $sql .= " AND r.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.* FROM order_reviews r 
            INNER JOIN orders o ON r.order_id = o.id 
            INNER JOIN entities e ON o.user_id = e.user_id 
            WHERE e.tenant_id = :tenant_id AND r.id = :id 
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);
        $fields = ['order_id', 'user_id', 'vendor_id', 'delivery_company_id', 'overall_rating', 'product_quality_rating', 'delivery_rating', 'service_rating', 'comment', 'is_approved'];
        $params = [];
        foreach ($fields as $f) {
            $params[':' . $f] = $data[$f] ?? null;
        }
        
        if ($isUpdate) {
            $params[':id'] = $data['id'];
            $stmt = $this->pdo->prepare("
                UPDATE order_reviews SET 
                    order_id=:order_id, user_id=:user_id, vendor_id=:vendor_id, 
                    delivery_company_id=:delivery_company_id, overall_rating=:overall_rating, 
                    product_quality_rating=:product_quality_rating, delivery_rating=:delivery_rating, 
                    service_rating=:service_rating, comment=:comment, is_approved=:is_approved 
                WHERE id=:id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO order_reviews (order_id, user_id, vendor_id, delivery_company_id, overall_rating, product_quality_rating, delivery_rating, service_rating, comment, is_approved) 
            VALUES (:order_id, :user_id, :vendor_id, :delivery_company_id, :overall_rating, :product_quality_rating, :delivery_rating, :service_rating, :comment, :is_approved)
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM order_reviews WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}

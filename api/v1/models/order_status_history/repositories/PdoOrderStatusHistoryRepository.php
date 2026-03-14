<?php
declare(strict_types=1);

final class PdoOrderStatusHistoryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir, string $lang): array
    {
        $sql = "SELECT h.* FROM order_status_history h 
                INNER JOIN orders o ON h.order_id = o.id 
                INNER JOIN entities e ON o.user_id = e.user_id 
                WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];
        
        if (!empty($filters['order_id'])) {
            $sql .= " AND h.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        
        $sql .= " ORDER BY h.created_at DESC";
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
        $sql = "SELECT COUNT(*) FROM order_status_history h 
                INNER JOIN orders o ON h.order_id = o.id 
                INNER JOIN entities e ON o.user_id = e.user_id 
                WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];
        
        if (!empty($filters['order_id'])) {
            $sql .= " AND h.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT h.* FROM order_status_history h 
            INNER JOIN orders o ON h.order_id = o.id 
            INNER JOIN entities e ON o.user_id = e.user_id 
            WHERE e.tenant_id = :tenant_id AND h.id = :id 
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $fields = ['order_id', 'status', 'notes', 'notified_customer', 'changed_by', 'ip_address'];
        $params = [];
        foreach ($fields as $f) {
            $params[':' . $f] = $data[$f] ?? null;
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO order_status_history (order_id, status, notes, notified_customer, changed_by, ip_address) 
            VALUES (:order_id, :status, :notes, :notified_customer, :changed_by, :ip_address)
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }
}

<?php
declare(strict_types=1);

final class PdoProductStockAlertsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit = null, ?int $offset = null, array $filters = [], string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        $sql = "SELECT * FROM product_stock_alerts WHERE 1=1";
        $params = [];

        if (isset($filters['product_id'])) {
            $sql .= " AND product_id = :product_id";
            $params[':product_id'] = $filters['product_id'];
        }

        if (isset($filters['user_id'])) {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $val, $type);
        }
        if ($limit !== null) $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM product_stock_alerts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE product_stock_alerts SET
                    product_id = :product_id,
                    variant_id = :variant_id,
                    user_id = :user_id,
                    email = :email,
                    is_notified = :is_notified,
                    notified_at = :notified_at,
                    created_at = :created_at
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $data['id'],
                ':product_id' => $data['product_id'],
                ':variant_id' => $data['variant_id'] ?? null,
                ':user_id' => $data['user_id'],
                ':email' => $data['email'],
                ':is_notified' => $data['is_notified'] ?? 0,
                ':notified_at' => $data['notified_at'] ?? null,
                ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO product_stock_alerts
                (product_id, variant_id, user_id, email, is_notified, notified_at, created_at)
            VALUES
                (:product_id, :variant_id, :user_id, :email, :is_notified, :notified_at, :created_at)
        ");
        $stmt->execute([
            ':product_id' => $data['product_id'],
            ':variant_id' => $data['variant_id'] ?? null,
            ':user_id' => $data['user_id'],
            ':email' => $data['email'],
            ':is_notified' => $data['is_notified'] ?? 0,
            ':notified_at' => $data['notified_at'] ?? null,
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM product_stock_alerts WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM product_stock_alerts WHERE 1=1";
        $params = [];

        if (isset($filters['product_id'])) {
            $sql .= " AND product_id = :product_id";
            $params[':product_id'] = $filters['product_id'];
        }

        if (isset($filters['user_id'])) {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
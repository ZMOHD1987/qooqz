<?php
declare(strict_types=1);

final class PdoProductReviewsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function all(array $filters = [], ?int $limit = null, ?int $offset = null, string $orderBy = 'created_at', string $orderDir = 'DESC'): array {
        $sql = "SELECT * FROM product_reviews WHERE 1=1";
        $params = [];

        foreach (['product_id','user_id','is_verified_purchase','is_approved'] as $f) {
            if (isset($filters[$f])) {
                $sql .= " AND {$f} = :{$f}";
                $params[":{$f}"] = $filters[$f];
            }
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k=>$v) {
            $stmt->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
        }
        if ($limit !== null) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM product_reviews WHERE 1=1";
        $params = [];

        foreach (['product_id','user_id','is_verified_purchase','is_approved'] as $f) {
            if (isset($filters[$f])) {
                $sql .= " AND {$f} = :{$f}";
                $params[":{$f}"] = $filters[$f];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM product_reviews WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data): int {
        $isUpdate = !empty($data['id']);
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE product_reviews SET
                    product_id=:product_id,
                    user_id=:user_id,
                    rating=:rating,
                    title=:title,
                    comment=:comment,
                    is_verified_purchase=:is_verified_purchase,
                    is_approved=:is_approved,
                    helpful_count=:helpful_count,
                    updated_at=NOW()
                WHERE id=:id
            ");
            $stmt->execute(array_merge($data, [':id'=>$data['id']]));
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO product_reviews
                (product_id, user_id, rating, title, comment, is_verified_purchase, is_approved, helpful_count, created_at, updated_at)
            VALUES
                (:product_id, :user_id, :rating, :title, :comment, :is_verified_purchase, :is_approved, :helpful_count, NOW(), NOW())
        ");
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM product_reviews WHERE id = :id");
        return $stmt->execute([':id'=>$id]);
    }
}
<?php
declare(strict_types=1);

final class PdoProduct_categoriesRepository
{
    private PDO $pdo;
    private const TABLE = 'product_categories';
    private const ALLOWED_ORDER_BY = ['id', 'product_id', 'category_id', 'is_primary', 'sort_order'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =====================================================
     * List + Filters + Pagination
     * ===================================================== */
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $params = [];
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE 1=1';

        if (!empty($filters['product_id'])) {
            $sql .= ' AND product_id = :product_id';
            $params['product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND category_id = :category_id';
            $params['category_id'] = (int)$filters['category_id'];
        }

        if (isset($filters['is_primary'])) {
            $sql .= ' AND is_primary = :is_primary';
            $params['is_primary'] = (int)$filters['is_primary'];
        }

        // ORDER BY safe
        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'id';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
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
    public function count(array $filters = []): int
    {
        $params = [];
        $sql = 'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE 1=1';

        if (!empty($filters['product_id'])) {
            $sql .= ' AND product_id = :product_id';
            $params['product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND category_id = :category_id';
            $params['category_id'] = (int)$filters['category_id'];
        }

        if (isset($filters['is_primary'])) {
            $sql .= ' AND is_primary = :is_primary';
            $params['is_primary'] = (int)$filters['is_primary'];
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }

        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /* =====================================================
     * Find by ID
     * ===================================================== */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =====================================================
     * Save (Insert / Update)
     * ===================================================== */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . "
                SET product_id = :product_id, category_id = :category_id, is_primary = :is_primary, sort_order = :sort_order
                WHERE id = :id
            ");
            $stmt->execute([
                'product_id'  => (int)$data['product_id'],
                'category_id' => (int)$data['category_id'],
                'is_primary'  => (int)($data['is_primary'] ?? 0),
                'sort_order'  => (int)($data['sort_order'] ?? 0),
                'id'          => (int)$data['id'],
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (product_id, category_id, is_primary, sort_order)
            VALUES (:product_id, :category_id, :is_primary, :sort_order)
        ");
        $stmt->execute([
            'product_id'  => (int)$data['product_id'],
            'category_id' => (int)$data['category_id'],
            'is_primary'  => (int)($data['is_primary'] ?? 0),
            'sort_order'  => (int)($data['sort_order'] ?? 0),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /* =====================================================
     * Delete
     * ===================================================== */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}

<?php
declare(strict_types=1);

final class PdoProduct_typesRepository
{
    private PDO $pdo;

    private const TABLE = 'product_types';
    private const ALLOWED_ORDER_BY = ['id', 'code', 'name', 'is_active'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =====================================================
     * WHERE Builder (مشترك)
     * ===================================================== */
    private function buildWhere(array $filters, array &$params): string
    {
        $where = [];

        if (!empty($filters['search'])) {
            $where[] = '(code LIKE :search OR name LIKE :search OR description LIKE :search)';
            $params['search'] = '%' . trim((string)$filters['search']) . '%';
        }

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = (int)$filters['is_active'];
        }

        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
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

        $sql = 'SELECT * FROM ' . self::TABLE;
        $sql .= $this->buildWhere($filters, $params);

        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'id';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);

        // Bind regular filters
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        // Bind limit/offset explicitly
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
     * Count (Pagination)
     * ===================================================== */
    public function count(array $filters = []): int
    {
        $params = [];
        $sql = 'SELECT COUNT(*) FROM ' . self::TABLE;
        $sql .= $this->buildWhere($filters, $params);

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /* =====================================================
     * Find by ID
     * ===================================================== */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =====================================================
     * Find by Code (Unique)
     * ===================================================== */
    public function findByCode(string $code, ?int $ignoreId = null): ?array
    {
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE code = :code';
        $params = ['code' => $code];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =====================================================
     * Save (Insert / Update)
     * ===================================================== */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        // Check uniqueness
        if (!empty($data['code'])) {
            $exists = $this->findByCode($data['code'], $isUpdate ? (int)$data['id'] : null);
            if ($exists) {
                throw new RuntimeException('Product type code already exists');
            }
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . "
                SET code = :code, name = :name, description = :description, is_active = :is_active
                WHERE id = :id
            ");
            $stmt->execute([
                'code'        => $data['code'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active'   => (int)($data['is_active'] ?? 1),
                'id'          => (int)$data['id'],
            ]);
            return (int)$data['id'];
        }

        // Insert
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (code, name, description, is_active)
            VALUES (:code, :name, :description, :is_active)
        ");
        $stmt->execute([
            'code'        => $data['code'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => (int)($data['is_active'] ?? 1),
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

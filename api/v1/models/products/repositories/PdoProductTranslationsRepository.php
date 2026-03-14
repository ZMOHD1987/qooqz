<?php
declare(strict_types=1);

final class PdoProductTranslationsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id','name','language_code','product_id'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters, search, pagination
    // ================================
    public function all(
        int $tenantId,
        ?string $languageCode = null,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT pt.* 
                FROM product_translations pt
                INNER JOIN products p ON pt.product_id = p.id
                WHERE p.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        if ($languageCode !== null) {
            $sql .= " AND pt.language_code = :language_code";
            $params[':language_code'] = $languageCode;
        }

        if (!empty($filters['product_id'])) {
            $sql .= " AND pt.product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }
        if (!empty($filters['name'])) {
            $sql .= " AND pt.name LIKE :name";
            $params[':name'] = '%'.$filters['name'].'%';
        }

        // Ordering
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        // Pagination
        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $tenantId, int $id, ?string $languageCode = null): ?array
    {
        $sql = "SELECT pt.* 
                FROM product_translations pt
                INNER JOIN products p ON pt.product_id = p.id
                WHERE pt.id = :id AND p.tenant_id = :tenant_id";
        $params = [':id'=>$id, ':tenant_id'=>$tenantId];
        if ($languageCode !== null) {
            $sql .= " AND pt.language_code = :language_code";
            $params[':language_code'] = $languageCode;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Save (Create / Update)
    // ================================
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        // Extract only valid translation columns to prevent SQLSTATE[HY093]
        $params = [
            ':product_id'        => $data['product_id'] ?? null,
            ':language_code'     => $data['language_code'] ?? null,
            ':name'              => $data['name'] ?? '',
            ':short_description' => $data['short_description'] ?? null,
            ':description'       => $data['description'] ?? null,
            ':specifications'    => $data['specifications'] ?? null,
            ':meta_title'        => $data['meta_title'] ?? null,
            ':meta_description'  => $data['meta_description'] ?? null,
            ':meta_keywords'     => $data['meta_keywords'] ?? null,
        ];

        if ($isUpdate) {
            $params[':id'] = $data['id'];
            $stmt = $this->pdo->prepare("
                UPDATE product_translations SET
                    product_id = :product_id,
                    language_code = :language_code,
                    name = :name,
                    short_description = :short_description,
                    description = :description,
                    specifications = :specifications,
                    meta_title = :meta_title,
                    meta_description = :meta_description,
                    meta_keywords = :meta_keywords
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO product_translations
                (product_id, language_code, name, short_description, description, specifications, meta_title, meta_description, meta_keywords)
            VALUES
                (:product_id, :language_code, :name, :short_description, :description, :specifications, :meta_title, :meta_description, :meta_keywords)
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM product_translations WHERE id = :id");
        return $stmt->execute([':id'=>$id]);
    }
}
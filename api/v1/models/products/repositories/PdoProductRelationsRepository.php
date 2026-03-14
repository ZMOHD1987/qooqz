<?php
declare(strict_types=1);

/**
 * PdoProductRelationsRepository
 *
 * PDO implementation of ProductRelationsRepositoryInterface.
 *
 * Location: api/v1/modules/products/repositories/PdoProductRelationsRepository.php
 */
final class PdoProductRelationsRepository implements ProductRelationsRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'pr.id', 'pr.product_id', 'pr.related_product_id', 'pr.relation_type', 'pr.sort_order'
    ];

    private const FILTERABLE_COLUMNS = [
        'product_id', 'related_product_id', 'relation_type'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // Interface Implementation
    // =========================================================================

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'pr.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $mainNameField    = $lang === 'ar' ? 'pt_main.name' : 'COALESCE(pt_main.name, p_main.name)';
        $relatedNameField = $lang === 'ar' ? 'pt_rel.name'  : 'COALESCE(pt_rel.name, p_rel.name)';

        $sql = "
            SELECT pr.*,
                   {$mainNameField}    AS main_product_name,
                   {$relatedNameField} AS related_product_name
            FROM product_relations pr
            INNER JOIN products p_main ON pr.product_id = p_main.id
            INNER JOIN products p_rel  ON pr.related_product_id = p_rel.id
            INNER JOIN entities e      ON p_main.tenant_id = e.id
            LEFT JOIN product_translations pt_main
                   ON p_main.id = pt_main.product_id AND pt_main.language_code = :lang_main
            LEFT JOIN product_translations pt_rel
                   ON p_rel.id  = pt_rel.product_id  AND pt_rel.language_code  = :lang_rel
            WHERE e.tenant_id = :tenant_id
        ";

        $params = [
            ':tenant_id' => $tenantId,
            ':lang_main'  => $lang,
            ':lang_rel'   => $lang,
        ];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'pr.id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        return $this->fetchAll($sql, $params);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM product_relations pr
            INNER JOIN products p_main ON pr.product_id = p_main.id
            INNER JOIN entities e      ON p_main.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $mainNameField    = $lang === 'ar' ? 'pt_main.name' : 'COALESCE(pt_main.name, p_main.name)';
        $relatedNameField = $lang === 'ar' ? 'pt_rel.name'  : 'COALESCE(pt_rel.name, p_rel.name)';

        $sql = "
            SELECT pr.*,
                   {$mainNameField}    AS main_product_name,
                   {$relatedNameField} AS related_product_name
            FROM product_relations pr
            INNER JOIN products p_main ON pr.product_id = p_main.id
            INNER JOIN products p_rel  ON pr.related_product_id = p_rel.id
            INNER JOIN entities e      ON p_main.tenant_id = e.id
            LEFT JOIN product_translations pt_main
                   ON p_main.id = pt_main.product_id AND pt_main.language_code = :lang_main
            LEFT JOIN product_translations pt_rel
                   ON p_rel.id  = pt_rel.product_id  AND pt_rel.language_code  = :lang_rel
            WHERE e.tenant_id = :tenant_id
              AND pr.id = :id
            LIMIT 1
        ";

        $params = [
            ':tenant_id' => $tenantId,
            ':id'         => $id,
            ':lang_main'  => $lang,
            ':lang_rel'   => $lang,
        ];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $tenantId, array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->validateProductBelongsToTenant((int) $data['product_id'],         $tenantId);
            $this->validateProductBelongsToTenant((int) $data['related_product_id'],  $tenantId);
            $this->checkDuplicate((int) $data['product_id'], (int) $data['related_product_id'], $data['relation_type']);

            $stmt = $this->pdo->prepare("
                INSERT INTO product_relations (product_id, related_product_id, relation_type, sort_order)
                VALUES (:product_id, :related_product_id, :relation_type, :sort_order)
            ");
            $stmt->execute([
                ':product_id'        => $data['product_id'],
                ':related_product_id' => $data['related_product_id'],
                ':relation_type'      => $data['relation_type'],
                ':sort_order'         => $data['sort_order'] ?? 0,
            ]);

            $newId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $newId;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Relation ID required for update.');
        }

        $this->pdo->beginTransaction();
        try {
            $check = $this->pdo->prepare("
                SELECT pr.id
                FROM product_relations pr
                INNER JOIN products p ON pr.product_id = p.id
                INNER JOIN entities e ON p.tenant_id = e.id
                WHERE e.tenant_id = :tenant_id AND pr.id = :id
            ");
            $check->execute([':tenant_id' => $tenantId, ':id' => $data['id']]);
            if (!$check->fetch()) {
                throw new InvalidArgumentException('Relation not found or access denied.');
            }

            $fields = [];
            $params = [':id' => $data['id']];

            if (isset($data['product_id'])) {
                $this->validateProductBelongsToTenant((int) $data['product_id'], $tenantId);
                $fields[] = 'product_id = :product_id';
                $params[':product_id'] = $data['product_id'];
            }
            if (isset($data['related_product_id'])) {
                $this->validateProductBelongsToTenant((int) $data['related_product_id'], $tenantId);
                $fields[] = 'related_product_id = :related_product_id';
                $params[':related_product_id'] = $data['related_product_id'];
            }
            if (isset($data['relation_type'])) {
                $fields[] = 'relation_type = :relation_type';
                $params[':relation_type'] = $data['relation_type'];
            }
            if (array_key_exists('sort_order', $data)) {
                $fields[] = 'sort_order = :sort_order';
                $params[':sort_order'] = $data['sort_order'];
            }

            if (empty($fields)) {
                $this->pdo->commit();
                return true;
            }

            $sql  = 'UPDATE product_relations SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            $this->pdo->commit();
            return $result;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $tenantId, int $id): bool
    {
        // Portable subquery instead of MySQL-only multi-table DELETE
        $stmt = $this->pdo->prepare("
            DELETE FROM product_relations
            WHERE id = :id
              AND product_id IN (
                  SELECT p.id FROM products p
                  INNER JOIN entities e ON p.tenant_id = e.id
                  WHERE e.tenant_id = :tenant_id
              )
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    public function deleteByProduct(int $tenantId, int $productId, ?string $relationType = null): bool
    {
        $this->validateProductBelongsToTenant($productId, $tenantId);

        $sql    = 'DELETE FROM product_relations WHERE product_id = :product_id';
        $params = [':product_id' => $productId];

        if ($relationType !== null) {
            $sql .= ' AND relation_type = :relation_type';
            $params[':relation_type'] = $relationType;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function getRelatedProducts(
        int $tenantId,
        int $productId,
        ?string $relationType = null,
        string $lang = 'ar'
    ): array {
        $nameField = $lang === 'ar' ? 'pt.name' : 'COALESCE(pt.name, p.name)';

        $sql = "
            SELECT pr.*,
                   {$nameField} AS related_product_name
            FROM product_relations pr
            INNER JOIN products p  ON pr.related_product_id = p.id
            INNER JOIN entities e  ON p.tenant_id = e.id
            LEFT JOIN product_translations pt
                   ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id
              AND pr.product_id = :product_id
        ";

        $params = [
            ':tenant_id'  => $tenantId,
            ':product_id' => $productId,
            ':lang'        => $lang,
        ];

        if ($relationType !== null) {
            $sql .= ' AND pr.relation_type = :relation_type';
            $params[':relation_type'] = $relationType;
        }

        $sql .= ' ORDER BY pr.sort_order ASC, pr.id ASC';

        return $this->fetchAll($sql, $params);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Apply filterable columns to SQL query and params array.
     *
     * @param string $sql
     * @param array  $params
     * @param array  $filters
     * @return array{0: string, 1: array}
     */
    private function applyFilters(string $sql, array $params, array $filters): array
    {
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND pr.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }
        return [$sql, $params];
    }

    /**
     * Execute a SELECT query and return all rows.
     */
    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ensure a product belongs to the given tenant.
     *
     * @throws InvalidArgumentException
     */
    private function validateProductBelongsToTenant(int $productId, int $tenantId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id FROM products p
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE p.id = :product_id AND e.tenant_id = :tenant_id
        ");
        $stmt->execute([':product_id' => $productId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('Product not found or does not belong to this tenant.');
        }
    }

    /**
     * Reject duplicate relations before insert.
     *
     * @throws InvalidArgumentException
     */
    private function checkDuplicate(int $productId, int $relatedProductId, string $relationType): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM product_relations
            WHERE product_id         = :product_id
              AND related_product_id = :related_product_id
              AND relation_type      = :relation_type
        ");
        $stmt->execute([
            ':product_id'         => $productId,
            ':related_product_id'  => $relatedProductId,
            ':relation_type'       => $relationType,
        ]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('This relation already exists.');
        }
    }
}
<?php
declare(strict_types=1);

final class PdoSeoMetaRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'entity_type', 'entity_id', 'tenant_id', 'created_at'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // Find by ID (with translations)
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM seo_meta WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $row['translations'] = $this->getTranslations($id);
        return $row;
    }

    // ================================
    // List with filters, ordering, pagination
    // ================================
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT sm.* FROM seo_meta sm WHERE 1=1";
        $params = [];

        if (isset($filters['entity_type']) && $filters['entity_type'] !== '') {
            $sql .= " AND sm.entity_type = :entity_type";
            $params[':entity_type'] = $filters['entity_type'];
        }

        if (isset($filters['entity_id']) && $filters['entity_id'] !== '') {
            $sql .= " AND sm.entity_id = :entity_id";
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['tenant_id']) && $filters['tenant_id'] !== '') {
            $sql .= " AND sm.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND (sm.canonical_url LIKE :search OR EXISTS (
                SELECT 1 FROM seo_meta_translations smt
                WHERE smt.seo_meta_id = sm.id AND (smt.meta_title LIKE :search_t OR smt.meta_description LIKE :search_t2)
            ))";
            $params[':search']    = '%' . trim($filters['search']) . '%';
            $params[':search_t']  = '%' . trim($filters['search']) . '%';
            $params[':search_t2'] = '%' . trim($filters['search']) . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY sm.{$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit  !== null) $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM seo_meta sm WHERE 1=1";
        $params = [];

        if (isset($filters['entity_type']) && $filters['entity_type'] !== '') {
            $sql .= " AND sm.entity_type = :entity_type";
            $params[':entity_type'] = $filters['entity_type'];
        }

        if (isset($filters['entity_id']) && $filters['entity_id'] !== '') {
            $sql .= " AND sm.entity_id = :entity_id";
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['tenant_id']) && $filters['tenant_id'] !== '') {
            $sql .= " AND sm.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND (sm.canonical_url LIKE :search OR EXISTS (
                SELECT 1 FROM seo_meta_translations smt
                WHERE smt.seo_meta_id = sm.id AND (smt.meta_title LIKE :search_t OR smt.meta_description LIKE :search_t2)
            ))";
            $params[':search']    = '%' . trim($filters['search']) . '%';
            $params[':search_t']  = '%' . trim($filters['search']) . '%';
            $params[':search_t2'] = '%' . trim($filters['search']) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Save (Insert or Update)
    // ================================
    public function save(array $data): int
    {
        if (!empty($data['id'])) {
            return $this->update((int)$data['id'], $data);
        }
        return $this->create($data);
    }

    // ================================
    // Create
    // ================================
    private function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO seo_meta (tenant_id, entity_type, entity_id, canonical_url, robots, schema_markup)
            VALUES (:tenant_id, :entity_type, :entity_id, :canonical_url, :robots, :schema_markup)
        ");

        $stmt->execute([
            ':tenant_id'     => $data['tenant_id'] ?? null,
            ':entity_type'   => $data['entity_type'],
            ':entity_id'     => (int)$data['entity_id'],
            ':canonical_url' => $data['canonical_url'] ?? null,
            ':robots'        => $data['robots'] ?? null,
            ':schema_markup' => $data['schema_markup'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Update
    // ================================
    private function update(int $id, array $data): int
    {
        $setClauses = [];
        $params = [':id' => $id];

        $allowed = ['tenant_id', 'entity_type', 'entity_id', 'canonical_url', 'robots', 'schema_markup'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[] = "{$col} = :{$col}";
                $params[':' . $col] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException("No valid fields provided for update");
        }

        $sql = "UPDATE seo_meta SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $id;
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        // Translations are deleted via ON DELETE CASCADE
        $stmt = $this->pdo->prepare("DELETE FROM seo_meta WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Get by entity_type + entity_id
    // ================================
    public function getByEntity(string $entityType, int $entityId, ?int $tenantId = null): ?array
    {
        $sql = "SELECT * FROM seo_meta WHERE entity_type = :entity_type AND entity_id = :entity_id";
        $params = [
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
        ];

        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $row['translations'] = $this->getTranslations((int)$row['id']);
        return $row;
    }

    // ================================
    // Translations CRUD
    // ================================
    public function getTranslations(int $seoMetaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM seo_meta_translations
            WHERE seo_meta_id = :seo_meta_id
        ");
        $stmt->execute([':seo_meta_id' => $seoMetaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTranslation(array $data): int
    {
        // Upsert: check if translation exists for this seo_meta_id + language_code
        $stmt = $this->pdo->prepare("
            SELECT id FROM seo_meta_translations
            WHERE seo_meta_id = :seo_meta_id AND language_code = :language_code
        ");
        $stmt->execute([
            ':seo_meta_id'   => $data['seo_meta_id'],
            ':language_code' => $data['language_code'],
        ]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $setClauses = [];
            $params = [':id' => (int)$existingId];

            $allowed = ['meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'og_image'];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $setClauses[] = "{$col} = :{$col}";
                    $params[':' . $col] = $data[$col];
                }
            }

            if (!empty($setClauses)) {
                $sql = "UPDATE seo_meta_translations SET " . implode(', ', $setClauses) . " WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }

            return (int)$existingId;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO seo_meta_translations (seo_meta_id, language_code, meta_title, meta_description, meta_keywords, og_title, og_description, og_image)
            VALUES (:seo_meta_id, :language_code, :meta_title, :meta_description, :meta_keywords, :og_title, :og_description, :og_image)
        ");
        $stmt->execute([
            ':seo_meta_id'      => $data['seo_meta_id'],
            ':language_code'    => $data['language_code'],
            ':meta_title'       => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':meta_keywords'    => $data['meta_keywords'] ?? null,
            ':og_title'         => $data['og_title'] ?? null,
            ':og_description'   => $data['og_description'] ?? null,
            ':og_image'         => $data['og_image'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function deleteTranslation(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM seo_meta_translations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Stats: count by entity_type
    // ================================
    public function stats(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT entity_type, COUNT(*) as count
            FROM seo_meta
            GROUP BY entity_type
            ORDER BY count DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
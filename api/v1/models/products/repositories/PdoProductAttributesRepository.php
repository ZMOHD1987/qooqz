<?php
declare(strict_types=1);

// api/v1/models/products/repositories/PdoProductAttributesRepository.php

final class PdoProductAttributesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $attributeTypeId = null, string $lang = 'en'): array
    {
        $sql = "
            SELECT pa.id, pa.slug, pa.attribute_type_id, pa.is_filterable, pa.is_visible, pa.is_required, pa.is_variation, pa.sort_order, pa.created_at, pa.updated_at, pa.is_global,
                   COALESCE(pat.name, pa.slug) AS name,
                   COALESCE(pat.description, '') AS description,
                   at.name AS attribute_type_name
            FROM product_attributes pa
            LEFT JOIN product_attribute_translations pat ON pa.id = pat.attribute_id AND pat.language_code = :lang
            LEFT JOIN attribute_types at ON pa.attribute_type_id = at.id
            WHERE 1=1
        ";

        $params = [':lang' => $lang];

        if ($attributeTypeId) {
            $sql .= " AND pa.attribute_type_id = :attributeTypeId";
            $params[':attributeTypeId'] = $attributeTypeId;
        }

        $sql .= " ORDER BY pa.sort_order ASC, pa.slug ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(string $slug, string $lang = 'en', bool $allTranslations = false): ?array
    {
        if ($allTranslations) {
            $row = $this->findBySlug($slug);
            if ($row) {
                $row['translations'] = $this->getTranslations($row['id']);
            }
            return $row;
        }

        $stmt = $this->pdo->prepare("
            SELECT pa.*, 
                   COALESCE(pat.name, pa.slug) AS name,
                   COALESCE(pat.description, '') AS description,
                   at.name AS attribute_type_name
            FROM product_attributes pa
            LEFT JOIN product_attribute_translations pat ON pa.id = pat.attribute_id AND pat.language_code = :lang
            LEFT JOIN attribute_types at ON pa.attribute_type_id = at.id
            WHERE pa.slug = :slug
            LIMIT 1
        ");

        $stmt->execute([':lang' => $lang, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pa.*, at.name AS attribute_type_name
            FROM product_attributes pa
            LEFT JOIN attribute_types at ON pa.attribute_type_id = at.id
            WHERE pa.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pa.*, at.name AS attribute_type_name
            FROM product_attributes pa
            LEFT JOIN attribute_types at ON pa.attribute_type_id = at.id
            WHERE pa.slug = :slug
            LIMIT 1
        ");

        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->findById((int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE product_attributes
                SET slug = :slug,
                    attribute_type_id = :attribute_type_id,
                    is_filterable = :is_filterable,
                    is_visible = :is_visible,
                    is_required = :is_required,
                    is_variation = :is_variation,
                    sort_order = :sort_order,
                    is_global = :is_global,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':slug'              => $data['slug'],
                ':attribute_type_id' => (int)$data['attribute_type_id'],
                ':is_filterable'     => (int)($data['is_filterable'] ?? 1),
                ':is_visible'        => (int)($data['is_visible'] ?? 1),
                ':is_required'       => (int)($data['is_required'] ?? 0),
                ':is_variation'      => (int)($data['is_variation'] ?? 0),
                ':sort_order'        => (int)($data['sort_order'] ?? 0),
                ':is_global'         => (int)($data['is_global'] ?? 1),
                ':id'                => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_attributes
                    (slug, attribute_type_id, is_filterable, is_visible, is_required, is_variation, sort_order, is_global, created_at)
                VALUES
                    (:slug, :attribute_type_id, :is_filterable, :is_visible, :is_required, :is_variation, :sort_order, :is_global, NOW())
            ");

            $stmt->execute([
                ':slug'              => $data['slug'],
                ':attribute_type_id' => (int)$data['attribute_type_id'],
                ':is_filterable'     => (int)($data['is_filterable'] ?? 1),
                ':is_visible'        => (int)($data['is_visible'] ?? 1),
                ':is_required'       => (int)($data['is_required'] ?? 0),
                ':is_variation'      => (int)($data['is_variation'] ?? 0),
                ':sort_order'        => (int)($data['sort_order'] ?? 0),
                ':is_global'         => (int)($data['is_global'] ?? 1)
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        // Save translations
        if (!empty($data['translations'])) {
            $this->saveTranslations($id, $data['translations']);
        }

        // Log the action
        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(string $slug, ?int $userId = null): bool
    {
        $oldData = $this->findBySlug($slug);

        if (!$oldData) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            // Delete translations
            $this->pdo->prepare("DELETE FROM product_attribute_translations WHERE attribute_id = :attribute_id")
                ->execute([':attribute_id' => $oldData['id']]);

            // Delete attribute
            $stmt = $this->pdo->prepare("
                DELETE FROM product_attributes
                WHERE slug = :slug
            ");

            $result = $stmt->execute([':slug' => $slug]);

            // Log the action
            if ($userId) {
                $this->logAction($userId, 'delete', $oldData['id'], $oldData, null);
            }

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function deleteById(int $id, ?int $userId = null): bool
    {
        $oldData = $this->findById($id);

        if (!$oldData) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            // Delete translations
            $this->pdo->prepare("DELETE FROM product_attribute_translations WHERE attribute_id = :attribute_id")
                ->execute([':attribute_id' => $id]);

            // Delete attribute
            $stmt = $this->pdo->prepare("
                DELETE FROM product_attributes
                WHERE id = :id
            ");

            $result = $stmt->execute([':id' => $id]);

            // Log the action
            if ($userId) {
                $this->logAction($userId, 'delete', $id, $oldData, null);
            }

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function saveTranslations(int $attributeId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_attribute_translations (attribute_id, language_code, name, description)
            VALUES (:attribute_id, :lang, :name, :description)
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)
        ");

        foreach ($translations as $lang => $data) {
            $stmt->execute([
                ':attribute_id' => $attributeId,
                ':lang'         => $lang,
                ':name'         => $data['name'] ?? null,
                ':description'  => $data['description'] ?? null
            ]);
        }
    }

    public function getTranslations(int $attributeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pat.language_code, pat.name, pat.description, l.name AS language_name
            FROM product_attribute_translations pat
            LEFT JOIN languages l ON pat.language_code = l.code
            WHERE pat.attribute_id = :attribute_id
        ");

        $stmt->execute([':attribute_id' => $attributeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row['language_code']] = [
                'name'           => $row['name'],
                'description'    => $row['description'],
                'language_name'  => $row['language_name']
            ];
        }

        return $translations;
    }

    private function logAction(int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode([
                'old' => $oldData,
                'new' => $newData
            ]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        }

        // Assuming entity_logs table exists and tenant_id is optional (set to 0 for global)
        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (0, :userId, 'product_attribute', :entityId, :action, :changes, :ip, NOW())
        ");

        $stmt->execute([
            ':userId'   => $userId,
            ':entityId' => $entityId,
            ':action'   => $action,
            ':changes'  => $changes,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
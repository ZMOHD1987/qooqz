<?php
declare(strict_types=1);

// api/v1/models/product_attribute_values/repositories/PdoProductAttributeValuesRepository.php

final class PdoProductAttributeValuesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $attributeId = null, string $lang = 'en'): array
    {
        $sql = "
            SELECT pav.id, pav.attribute_id, pav.value, pav.slug, pav.sort_order, pav.is_active,
                   COALESCE(pavt.label, pav.value) AS label,
                   pa.slug AS attribute_slug
            FROM product_attribute_values pav
            LEFT JOIN product_attribute_value_translations pavt ON pav.id = pavt.attribute_value_id AND pavt.language_code = :lang
            LEFT JOIN product_attributes pa ON pav.attribute_id = pa.id
            WHERE 1=1
        ";

        $params = [':lang' => $lang];

        if ($attributeId) {
            $sql .= " AND pav.attribute_id = :attributeId";
            $params[':attributeId'] = $attributeId;
        }

        $sql .= " ORDER BY pav.attribute_id ASC, pav.sort_order ASC, pav.slug ASC";

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
            SELECT pav.*, 
                   COALESCE(pavt.label, pav.value) AS label,
                   pa.slug AS attribute_slug
            FROM product_attribute_values pav
            LEFT JOIN product_attribute_value_translations pavt ON pav.id = pavt.attribute_value_id AND pavt.language_code = :lang
            LEFT JOIN product_attributes pa ON pav.attribute_id = pa.id
            WHERE pav.slug = :slug
            LIMIT 1
        ");

        $stmt->execute([':lang' => $lang, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pav.*, pa.slug AS attribute_slug
            FROM product_attribute_values pav
            LEFT JOIN product_attributes pa ON pav.attribute_id = pa.id
            WHERE pav.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pav.*, pa.slug AS attribute_slug
            FROM product_attribute_values pav
            LEFT JOIN product_attributes pa ON pav.attribute_id = pa.id
            WHERE pav.slug = :slug
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
                UPDATE product_attribute_values
                SET value = :value,
                    slug = :slug,
                    sort_order = :sort_order,
                    is_active = :is_active
                WHERE id = :id
            ");

            $stmt->execute([
                ':value'      => $data['value'],
                ':slug'       => $data['slug'],
                ':sort_order' => (int)($data['sort_order'] ?? 0),
                ':is_active'  => (int)($data['is_active'] ?? 1),
                ':id'         => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_attribute_values
                    (attribute_id, value, slug, sort_order, is_active)
                VALUES
                    (:attribute_id, :value, :slug, :sort_order, :is_active)
            ");

            $stmt->execute([
                ':attribute_id' => (int)$data['attribute_id'],
                ':value'        => $data['value'],
                ':slug'         => $data['slug'],
                ':sort_order'   => (int)($data['sort_order'] ?? 0),
                ':is_active'    => (int)($data['is_active'] ?? 1)
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
            $this->pdo->prepare("DELETE FROM product_attribute_value_translations WHERE attribute_value_id = :attribute_value_id")
                ->execute([':attribute_value_id' => $oldData['id']]);

            // Delete value
            $stmt = $this->pdo->prepare("
                DELETE FROM product_attribute_values
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
            $this->pdo->prepare("DELETE FROM product_attribute_value_translations WHERE attribute_value_id = :attribute_value_id")
                ->execute([':attribute_value_id' => $id]);

            // Delete value
            $stmt = $this->pdo->prepare("
                DELETE FROM product_attribute_values
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

    public function saveTranslations(int $attributeValueId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_attribute_value_translations (attribute_value_id, language_code, label)
            VALUES (:attribute_value_id, :lang, :label)
            ON DUPLICATE KEY UPDATE label = VALUES(label)
        ");

        foreach ($translations as $lang => $data) {
            $stmt->execute([
                ':attribute_value_id' => $attributeValueId,
                ':lang'               => $lang,
                ':label'              => $data['label'] ?? null
            ]);
        }
    }

    public function getTranslations(int $attributeValueId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pavt.language_code, pavt.label, l.name AS language_name
            FROM product_attribute_value_translations pavt
            LEFT JOIN languages l ON pavt.language_code = l.code
            WHERE pavt.attribute_value_id = :attribute_value_id
        ");

        $stmt->execute([':attribute_value_id' => $attributeValueId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row['language_code']] = [
                'label'         => $row['label'],
                'language_name' => $row['language_name']
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
            VALUES (0, :userId, 'product_attribute_value', :entityId, :action, :changes, :ip, NOW())
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
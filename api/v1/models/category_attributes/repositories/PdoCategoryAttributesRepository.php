<?php
declare(strict_types=1);

// api/v1/models/category_attributes/repositories/PdoCategoryAttributesRepository.php

final class PdoCategoryAttributesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $categoryId = null, string $lang = 'en'): array
    {
        $sql = "
            SELECT ca.id, ca.category_id, ca.attribute_id, ca.is_required, ca.sort_order, ca.created_at, ca.updated_at,
                   COALESCE(cat.name, '') AS name,
                   COALESCE(cat.description, '') AS description
            FROM category_attributes ca
            LEFT JOIN category_attribute_translations cat 
                ON ca.id = cat.category_attribute_id AND cat.language_code = :lang
            JOIN categories c ON ca.category_id = c.id
            WHERE c.tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId, ':lang' => $lang];

        if ($categoryId) {
            $sql .= " AND ca.category_id = :categoryId";
            $params[':categoryId'] = $categoryId;
        }

        $sql .= " ORDER BY ca.category_id ASC, ca.sort_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId, int $id, string $lang = 'en', bool $allTranslations = false): ?array
    {
        if ($allTranslations) {
            $row = $this->findById($tenantId, $id);
            if ($row) {
                $row['translations'] = $this->getTranslations($id);
            }
            return $row;
        }

        $stmt = $this->pdo->prepare("
            SELECT ca.*, 
                   COALESCE(cat.name, '') AS name,
                   COALESCE(cat.description, '') AS description
            FROM category_attributes ca
            LEFT JOIN category_attribute_translations cat 
                ON ca.id = cat.category_attribute_id AND cat.language_code = :lang
            JOIN categories c ON ca.category_id = c.id
            WHERE c.tenant_id = :tenantId AND ca.id = :id
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ca.*
            FROM category_attributes ca
            JOIN categories c ON ca.category_id = c.id
            WHERE c.tenant_id = :tenantId AND ca.id = :id
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->findById($tenantId, (int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE category_attributes
                SET category_id = :category_id,
                    attribute_id = :attribute_id,
                    is_required = :is_required,
                    sort_order = :sort_order,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':category_id' => (int)$data['category_id'],
                ':attribute_id' => (int)$data['attribute_id'],
                ':is_required'  => (int)($data['is_required'] ?? 0),
                ':sort_order'   => (int)($data['sort_order'] ?? 0),
                ':id'           => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO category_attributes
                    (category_id, attribute_id, is_required, sort_order, created_at)
                VALUES
                    (:category_id, :attribute_id, :is_required, :sort_order, NOW())
            ");

            $stmt->execute([
                ':category_id' => (int)$data['category_id'],
                ':attribute_id' => (int)$data['attribute_id'],
                ':is_required'  => (int)($data['is_required'] ?? 0),
                ':sort_order'   => (int)($data['sort_order'] ?? 0)
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        // Save translations
        if (!empty($data['translations'])) {
            $this->saveTranslations($id, $data['translations']);
        }

        // Log the action
        if ($userId) {
            $this->logAction($tenantId, $userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->findById($tenantId, $id);

        if (!$oldData) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            // Delete translations
            $this->pdo->prepare("DELETE FROM category_attribute_translations WHERE category_attribute_id = :category_attribute_id")
                ->execute([':category_attribute_id' => $id]);

            // Delete category attribute
            $stmt = $this->pdo->prepare("
                DELETE FROM category_attributes
                WHERE id = :id
            ");

            $result = $stmt->execute([':id' => $id]);

            // Log the action
            if ($userId) {
                $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
            }

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function getByCategory(int $tenantId, int $categoryId, string $lang = 'en'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ca.id, ca.attribute_id, ca.is_required, ca.sort_order,
                   COALESCE(cat.name, '') AS name,
                   COALESCE(cat.description, '') AS description
            FROM category_attributes ca
            LEFT JOIN category_attribute_translations cat 
                ON ca.id = cat.category_attribute_id AND cat.language_code = :lang
            JOIN categories c ON ca.category_id = c.id
            WHERE c.tenant_id = :tenantId AND ca.category_id = :categoryId
            ORDER BY ca.sort_order ASC
        ");

        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang, ':categoryId' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTranslations(int $categoryAttributeId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO category_attribute_translations (category_attribute_id, language_code, name, description)
            VALUES (:category_attribute_id, :lang, :name, :description)
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)
        ");

        foreach ($translations as $lang => $data) {
            $stmt->execute([
                ':category_attribute_id' => $categoryAttributeId,
                ':lang'                  => $lang,
                ':name'                  => $data['name'] ?? null,
                ':description'           => $data['description'] ?? null
            ]);
        }
    }

    public function getTranslations(int $categoryAttributeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, name, description
            FROM category_attribute_translations
            WHERE category_attribute_id = :category_attribute_id
        ");

        $stmt->execute([':category_attribute_id' => $categoryAttributeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row['language_code']] = [
                'name'        => $row['name'],
                'description' => $row['description']
            ];
        }

        return $translations;
    }

    private function logAction(int $tenantId, int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
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

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (:tenantId, :userId, 'category_attribute', :entityId, :action, :changes, :ip, NOW())
        ");

        $stmt->execute([
            ':tenantId' => $tenantId,
            ':userId'   => $userId,
            ':entityId' => $entityId,
            ':action'   => $action,
            ':changes'  => $changes,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
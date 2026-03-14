<?php
declare(strict_types=1);

// api/v1/models/products/repositories/PdoProductAttributeTranslationsRepository.php

final class PdoProductAttributeTranslationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $attributeId = null, ?string $languageCode = null): array
    {
        $sql = "
            SELECT pat.id, pat.attribute_id, pat.language_code, pat.name, pat.description,
                   pa.slug AS attribute_slug, l.name AS language_name
            FROM product_attribute_translations pat
            LEFT JOIN product_attributes pa ON pat.attribute_id = pa.id
            LEFT JOIN languages l ON pat.language_code = l.code
            WHERE 1=1
        ";

        $params = [];

        if ($attributeId) {
            $sql .= " AND pat.attribute_id = :attributeId";
            $params[':attributeId'] = $attributeId;
        }

        if ($languageCode) {
            $sql .= " AND pat.language_code = :languageCode";
            $params[':languageCode'] = $languageCode;
        }

        $sql .= " ORDER BY pat.attribute_id ASC, pat.language_code ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pat.*, pa.slug AS attribute_slug, l.name AS language_name
            FROM product_attribute_translations pat
            LEFT JOIN product_attributes pa ON pat.attribute_id = pa.id
            LEFT JOIN languages l ON pat.language_code = l.code
            WHERE pat.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByAttributeAndLanguage(int $attributeId, string $languageCode): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pat.*, pa.slug AS attribute_slug, l.name AS language_name
            FROM product_attribute_translations pat
            LEFT JOIN product_attributes pa ON pat.attribute_id = pa.id
            LEFT JOIN languages l ON pat.language_code = l.code
            WHERE pat.attribute_id = :attributeId AND pat.language_code = :languageCode
            LIMIT 1
        ");

        $stmt->execute([':attributeId' => $attributeId, ':languageCode' => $languageCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE product_attribute_translations
                SET name = :name,
                    description = :description
                WHERE id = :id
            ");

            $stmt->execute([
                ':name'        => $data['name'],
                ':description' => $data['description'] ?? null,
                ':id'          => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_attribute_translations
                    (attribute_id, language_code, name, description)
                VALUES
                    (:attribute_id, :language_code, :name, :description)
            ");

            $stmt->execute([
                ':attribute_id'  => (int)$data['attribute_id'],
                ':language_code' => $data['language_code'],
                ':name'          => $data['name'],
                ':description'   => $data['description'] ?? null
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        // Log the action
        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($id);

        if (!$oldData) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM product_attribute_translations
            WHERE id = :id
        ");

        $result = $stmt->execute([':id' => $id]);

        // Log the action
        if ($userId && $result) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
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
            VALUES (0, :userId, 'product_attribute_translation', :entityId, :action, :changes, :ip, NOW())
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
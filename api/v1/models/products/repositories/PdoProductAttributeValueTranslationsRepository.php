<?php
declare(strict_types=1);

// api/v1/models/product_attribute_value_translations/repositories/PdoProductAttributeValueTranslationsRepository.php

final class PdoProductAttributeValueTranslationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $attributeValueId = null, ?string $languageCode = null): array
    {
        $sql = "
            SELECT pavt.id, pavt.attribute_value_id, pavt.language_code, pavt.label,
                   pav.slug AS attribute_value_slug, l.name AS language_name
            FROM product_attribute_value_translations pavt
            LEFT JOIN product_attribute_values pav ON pavt.attribute_value_id = pav.id
            LEFT JOIN languages l ON pavt.language_code = l.code
            WHERE 1=1
        ";

        $params = [];

        if ($attributeValueId) {
            $sql .= " AND pavt.attribute_value_id = :attributeValueId";
            $params[':attributeValueId'] = $attributeValueId;
        }

        if ($languageCode) {
            $sql .= " AND pavt.language_code = :languageCode";
            $params[':languageCode'] = $languageCode;
        }

        $sql .= " ORDER BY pavt.attribute_value_id ASC, pavt.language_code ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pavt.*, pav.slug AS attribute_value_slug, l.name AS language_name
            FROM product_attribute_value_translations pavt
            LEFT JOIN product_attribute_values pav ON pavt.attribute_value_id = pav.id
            LEFT JOIN languages l ON pavt.language_code = l.code
            WHERE pavt.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByAttributeValueAndLanguage(int $attributeValueId, string $languageCode): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pavt.*, pav.slug AS attribute_value_slug, l.name AS language_name
            FROM product_attribute_value_translations pavt
            LEFT JOIN product_attribute_values pav ON pavt.attribute_value_id = pav.id
            LEFT JOIN languages l ON pavt.language_code = l.code
            WHERE pavt.attribute_value_id = :attributeValueId AND pavt.language_code = :languageCode
            LIMIT 1
        ");

        $stmt->execute([':attributeValueId' => $attributeValueId, ':languageCode' => $languageCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE product_attribute_value_translations
                SET label = :label
                WHERE id = :id
            ");

            $stmt->execute([
                ':label' => $data['label'],
                ':id'    => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_attribute_value_translations
                    (attribute_value_id, language_code, label)
                VALUES
                    (:attribute_value_id, :language_code, :label)
            ");

            $stmt->execute([
                ':attribute_value_id' => (int)$data['attribute_value_id'],
                ':language_code'      => $data['language_code'],
                ':label'              => $data['label']
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
            DELETE FROM product_attribute_value_translations
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
            VALUES (0, :userId, 'product_attribute_value_translation', :entityId, :action, :changes, :ip, NOW())
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
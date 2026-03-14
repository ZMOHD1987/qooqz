<?php
declare(strict_types=1);

// api/v1/models/product_attribute_assignments/repositories/PdoProductAttributeAssignmentsRepository.php

final class PdoProductAttributeAssignmentsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $productId = null, ?int $attributeId = null): array
    {
        $sql = "
            SELECT paa.id, paa.product_id, paa.attribute_id, paa.attribute_value_id, paa.custom_value, paa.created_at, paa.updated_at,
                   pa.slug AS attribute_slug, pav.slug AS attribute_value_slug
            FROM product_attribute_assignments paa
            LEFT JOIN product_attributes pa ON paa.attribute_id = pa.id
            LEFT JOIN product_attribute_values pav ON paa.attribute_value_id = pav.id
            WHERE 1=1
        ";

        $params = [];

        if ($productId) {
            $sql .= " AND paa.product_id = :productId";
            $params[':productId'] = $productId;
        }

        if ($attributeId) {
            $sql .= " AND paa.attribute_id = :attributeId";
            $params[':attributeId'] = $attributeId;
        }

        $sql .= " ORDER BY paa.product_id ASC, paa.attribute_id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT paa.*, pa.slug AS attribute_slug, pav.slug AS attribute_value_slug
            FROM product_attribute_assignments paa
            LEFT JOIN product_attributes pa ON paa.attribute_id = pa.id
            LEFT JOIN product_attribute_values pav ON paa.attribute_value_id = pav.id
            WHERE paa.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT paa.id, paa.attribute_id, paa.attribute_value_id, paa.custom_value,
                   pa.slug AS attribute_slug, pav.slug AS attribute_value_slug, pav.value AS attribute_value
            FROM product_attribute_assignments paa
            LEFT JOIN product_attributes pa ON paa.attribute_id = pa.id
            LEFT JOIN product_attribute_values pav ON paa.attribute_value_id = pav.id
            WHERE paa.product_id = :productId
            ORDER BY pa.sort_order ASC
        ");

        $stmt->execute([':productId' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE product_attribute_assignments
                SET product_id = :product_id,
                    attribute_id = :attribute_id,
                    attribute_value_id = :attribute_value_id,
                    custom_value = :custom_value,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':product_id'        => (int)$data['product_id'],
                ':attribute_id'      => (int)$data['attribute_id'],
                ':attribute_value_id' => $data['attribute_value_id'] ? (int)$data['attribute_value_id'] : null,
                ':custom_value'      => $data['custom_value'] ?? null,
                ':id'                => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_attribute_assignments
                    (product_id, attribute_id, attribute_value_id, custom_value, created_at)
                VALUES
                    (:product_id, :attribute_id, :attribute_value_id, :custom_value, NOW())
            ");

            $stmt->execute([
                ':product_id'        => (int)$data['product_id'],
                ':attribute_id'      => (int)$data['attribute_id'],
                ':attribute_value_id' => $data['attribute_value_id'] ? (int)$data['attribute_value_id'] : null,
                ':custom_value'      => $data['custom_value'] ?? null
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
            DELETE FROM product_attribute_assignments
            WHERE id = :id
        ");

        $result = $stmt->execute([':id' => $id]);

        // Log the action
        if ($userId && $result) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    public function deleteByProduct(int $productId, ?int $userId = null): bool
    {
        $assignments = $this->getByProduct($productId);

        if (empty($assignments)) {
            return true;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM product_attribute_assignments
                WHERE product_id = :productId
            ");

            $result = $stmt->execute([':productId' => $productId]);

            // Log the action for each deleted assignment
            if ($userId && $result) {
                foreach ($assignments as $assignment) {
                    $this->logAction($userId, 'delete', $assignment['id'], $assignment, null);
                }
            }

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
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
            VALUES (0, :userId, 'product_attribute_assignment', :entityId, :action, :changes, :ip, NOW())
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
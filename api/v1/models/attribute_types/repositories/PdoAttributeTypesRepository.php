<?php
declare(strict_types=1);

// api/v1/models/attribute_types/repositories/PdoAttributeTypesRepository.php

final class PdoAttributeTypesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(bool $activeOnly = false): array
    {
        $sql = "
            SELECT id, code, name, has_values, is_multi, is_visual, is_active
            FROM attribute_types
        ";

        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }

        $sql .= " ORDER BY name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(string $code): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM attribute_types
            WHERE code = :code
            LIMIT 1
        ");

        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM attribute_types
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->findById((int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE attribute_types
                SET code = :code,
                    name = :name,
                    has_values = :has_values,
                    is_multi = :is_multi,
                    is_visual = :is_visual,
                    is_active = :is_active
                WHERE id = :id
            ");

            $stmt->execute([
                ':code'      => $data['code'],
                ':name'      => $data['name'],
                ':has_values' => (int)($data['has_values'] ?? 0),
                ':is_multi'  => (int)($data['is_multi'] ?? 0),
                ':is_visual' => (int)($data['is_visual'] ?? 0),
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':id'        => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO attribute_types
                    (code, name, has_values, is_multi, is_visual, is_active)
                VALUES
                    (:code, :name, :has_values, :is_multi, :is_visual, :is_active)
            ");

            $stmt->execute([
                ':code'      => $data['code'],
                ':name'      => $data['name'],
                ':has_values' => (int)($data['has_values'] ?? 0),
                ':is_multi'  => (int)($data['is_multi'] ?? 0),
                ':is_visual' => (int)($data['is_visual'] ?? 0),
                ':is_active' => (int)($data['is_active'] ?? 1)
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        // Log the action if userId provided (assuming entity_logs table exists)
        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(string $code, ?int $userId = null): bool
    {
        $oldData = $this->find($code);

        if (!$oldData) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM attribute_types
            WHERE code = :code
        ");

        $result = $stmt->execute([':code' => $code]);

        // Log the action
        if ($userId && $result) {
            $this->logAction($userId, 'delete', $oldData['id'], $oldData, null);
        }

        return $result;
    }

    public function deleteById(int $id, ?int $userId = null): bool
    {
        $oldData = $this->findById($id);

        if (!$oldData) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM attribute_types
            WHERE id = :id
        ");

        $result = $stmt->execute([':id' => $id]);

        // Log the action
        if ($userId && $result) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    public function getActive(): array
    {
        return $this->all(true);
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
            VALUES (0, :userId, 'attribute_type', :entityId, :action, :changes, :ip, NOW())
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
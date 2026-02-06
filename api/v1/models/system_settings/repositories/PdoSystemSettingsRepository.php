<?php
declare(strict_types=1);

// api/v1/models/system_settings/repositories/PdoSystemSettingsRepository.php

final class PdoSystemSettingsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?string $category = null): array
    {
        $sql = "
            SELECT id, tenant_id, setting_key, setting_value, setting_type, category, description, is_public, is_editable, created_at, updated_at
            FROM system_settings
            WHERE tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId];

        if ($category) {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }

        $sql .= " ORDER BY category ASC, setting_key ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId, string $key): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM system_settings
            WHERE tenant_id = :tenantId AND setting_key = :key
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM system_settings
            WHERE tenant_id = :tenantId AND id = :id
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE system_settings
                SET setting_value = :value,
                    setting_type = :type,
                    category = :category,
                    description = :description,
                    is_public = :is_public,
                    is_editable = :is_editable,
                    updated_at = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $stmt->execute([
                ':value'       => $data['setting_value'],
                ':type'        => $data['setting_type'] ?? 'text',
                ':category'    => $data['category'],
                ':description' => $data['description'] ?? null,
                ':is_public'   => (int)($data['is_public'] ?? 0),
                ':is_editable' => (int)($data['is_editable'] ?? 1),
                ':tenantId'    => $tenantId,
                ':id'          => (int)$data['id']
            ]);

            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO system_settings
                (tenant_id, setting_key, setting_value, setting_type, category, description, is_public, is_editable, created_at)
            VALUES
                (:tenantId, :key, :value, :type, :category, :description, :is_public, :is_editable, NOW())
        ");

        $stmt->execute([
            ':tenantId'    => $tenantId,
            ':key'         => $data['setting_key'],
            ':value'       => $data['setting_value'],
            ':type'        => $data['setting_type'] ?? 'text',
            ':category'    => $data['category'],
            ':description' => $data['description'] ?? null,
            ':is_public'   => (int)($data['is_public'] ?? 0),
            ':is_editable' => (int)($data['is_editable'] ?? 1)
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, string $key): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM system_settings
            WHERE tenant_id = :tenantId AND setting_key = :key
        ");

        return $stmt->execute([':tenantId' => $tenantId, ':key' => $key]);
    }

    public function deleteById(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM system_settings
            WHERE tenant_id = :tenantId AND id = :id
        ");

        return $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
    }

    public function getCategories(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT category
            FROM system_settings
            WHERE tenant_id = :tenantId
            ORDER BY category ASC
        ");

        $stmt->execute([':tenantId' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getPublicSettings(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT setting_key, setting_value, setting_type
            FROM system_settings
            WHERE tenant_id = :tenantId AND is_public = 1
            ORDER BY category ASC, setting_key ASC
        ");

        $stmt->execute([':tenantId' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
<?php
declare(strict_types=1);

// api/v1/models/design_settings/repositories/PdoDesignSettingsRepository.php

final class PdoDesignSettingsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?string $category = null, ?int $themeId = null): array
    {
        $sql = "
            SELECT id, theme_id, setting_key, setting_name, setting_value, setting_type, category, is_active, sort_order, created_at, updated_at, tenant_id
            FROM design_settings
            WHERE tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId];

        if ($category) {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY category ASC, sort_order ASC, setting_name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId, string $key, ?int $themeId = null): ?array
    {
        $sql = "
            SELECT *
            FROM design_settings
            WHERE tenant_id = :tenantId AND setting_key = :key
        ";

        $params = [':tenantId' => $tenantId, ':key' => $key];

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM design_settings
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
                UPDATE design_settings
                SET theme_id = :theme_id,
                    setting_key = :key,
                    setting_name = :name,
                    setting_value = :value,
                    setting_type = :type,
                    category = :category,
                    is_active = :is_active,
                    sort_order = :sort_order,
                    updated_at = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $stmt->execute([
                ':theme_id'   => $data['theme_id'] ?? null,
                ':key'        => $data['setting_key'],
                ':name'       => $data['setting_name'],
                ':value'      => $data['setting_value'] ?? null,
                ':type'       => $data['setting_type'] ?? 'text',
                ':category'   => $data['category'] ?? 'other',
                ':is_active'  => (int)($data['is_active'] ?? 1),
                ':sort_order' => (int)($data['sort_order'] ?? 0),
                ':tenantId'   => $tenantId,
                ':id'         => (int)$data['id']
            ]);

            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO design_settings
                (tenant_id, theme_id, setting_key, setting_name, setting_value, setting_type, category, is_active, sort_order, created_at)
            VALUES
                (:tenantId, :theme_id, :key, :name, :value, :type, :category, :is_active, :sort_order, NOW())
        ");

        $stmt->execute([
            ':tenantId'   => $tenantId,
            ':theme_id'   => $data['theme_id'] ?? null,
            ':key'        => $data['setting_key'],
            ':name'       => $data['setting_name'],
            ':value'      => $data['setting_value'] ?? null,
            ':type'       => $data['setting_type'] ?? 'text',
            ':category'   => $data['category'] ?? 'other',
            ':is_active'  => (int)($data['is_active'] ?? 1),
            ':sort_order' => (int)($data['sort_order'] ?? 0)
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, string $key, ?int $themeId = null): bool
    {
        $sql = "
            DELETE FROM design_settings
            WHERE tenant_id = :tenantId AND setting_key = :key
        ";

        $params = [':tenantId' => $tenantId, ':key' => $key];

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteById(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM design_settings
            WHERE tenant_id = :tenantId AND id = :id
        ");

        return $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
    }

    public function getCategories(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT category
            FROM design_settings
            WHERE tenant_id = :tenantId
            ORDER BY category ASC
        ");

        $stmt->execute([':tenantId' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getActiveSettings(int $tenantId, ?int $themeId = null): array
    {
        $sql = "
            SELECT setting_key, setting_value, setting_type
            FROM design_settings
            WHERE tenant_id = :tenantId AND is_active = 1
        ";

        $params = [':tenantId' => $tenantId];

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY category ASC, sort_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bulkUpdate(int $tenantId, array $settings): bool
    {
        $this->pdo->beginTransaction();

        try {
            foreach ($settings as $setting) {
                $this->save($tenantId, $setting);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
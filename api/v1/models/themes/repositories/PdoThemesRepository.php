<?php
declare(strict_types=1);

// api/v1/models/themes/repositories/PdoThemesRepository.php

final class PdoThemesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, bool $activeOnly = false): array
    {
        $sql = "
            SELECT id, name, slug, description, thumbnail_url, preview_url, version, author, is_active, is_default, created_at, updated_at, tenant_id
            FROM themes
            WHERE tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY is_default DESC, name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM themes
            WHERE tenant_id = :tenantId AND slug = :slug
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM themes
            WHERE tenant_id = :tenantId AND id = :id
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getActive(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM themes
            WHERE tenant_id = :tenantId AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDefault(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM themes
            WHERE tenant_id = :tenantId AND is_default = 1
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE themes
                SET name = :name,
                    slug = :slug,
                    description = :description,
                    thumbnail_url = :thumbnail_url,
                    preview_url = :preview_url,
                    version = :version,
                    author = :author,
                    is_active = :is_active,
                    is_default = :is_default,
                    updated_at = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $stmt->execute([
                ':name'          => $data['name'],
                ':slug'          => $data['slug'],
                ':description'   => $data['description'] ?? null,
                ':thumbnail_url' => $data['thumbnail_url'] ?? null,
                ':preview_url'   => $data['preview_url'] ?? null,
                ':version'       => $data['version'] ?? '1.0.0',
                ':author'        => $data['author'] ?? null,
                ':is_active'     => (int)($data['is_active'] ?? 0),
                ':is_default'    => (int)($data['is_default'] ?? 0),
                ':tenantId'      => $tenantId,
                ':id'            => (int)$data['id']
            ]);

            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO themes
                (tenant_id, name, slug, description, thumbnail_url, preview_url, version, author, is_active, is_default, created_at)
            VALUES
                (:tenantId, :name, :slug, :description, :thumbnail_url, :preview_url, :version, :author, :is_active, :is_default, NOW())
        ");

        $stmt->execute([
            ':tenantId'      => $tenantId,
            ':name'          => $data['name'],
            ':slug'          => $data['slug'],
            ':description'   => $data['description'] ?? null,
            ':thumbnail_url' => $data['thumbnail_url'] ?? null,
            ':preview_url'   => $data['preview_url'] ?? null,
            ':version'       => $data['version'] ?? '1.0.0',
            ':author'        => $data['author'] ?? null,
            ':is_active'     => (int)($data['is_active'] ?? 0),
            ':is_default'    => (int)($data['is_default'] ?? 0)
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, string $slug): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM themes
            WHERE tenant_id = :tenantId AND slug = :slug
        ");

        return $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);
    }

    public function deleteById(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM themes
            WHERE tenant_id = :tenantId AND id = :id
        ");

        return $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
    }

    public function activate(int $tenantId, string $slug): bool
    {
        $this->pdo->beginTransaction();

        try {
            // Deactivate all themes for this tenant
            $this->pdo->prepare("
                UPDATE themes
                SET is_active = 0
                WHERE tenant_id = :tenantId
            ")->execute([':tenantId' => $tenantId]);

            // Activate the specified theme
            $stmt = $this->pdo->prepare("
                UPDATE themes
                SET is_active = 1
                WHERE tenant_id = :tenantId AND slug = :slug
            ");

            $result = $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function setDefault(int $tenantId, string $slug): bool
    {
        $this->pdo->beginTransaction();

        try {
            // Unset default for all themes for this tenant
            $this->pdo->prepare("
                UPDATE themes
                SET is_default = 0
                WHERE tenant_id = :tenantId
            ")->execute([':tenantId' => $tenantId]);

            // Set default for the specified theme
            $stmt = $this->pdo->prepare("
                UPDATE themes
                SET is_default = 1
                WHERE tenant_id = :tenantId AND slug = :slug
            ");

            $result = $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
<?php
declare(strict_types=1);

// api/v1/models/brands/repositories/PdoBrandsRepository.php

final class PdoBrandsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, bool $featuredOnly = false, string $lang = 'en'): array
    {
        $sql = "
            SELECT b.id, b.tenant_id, b.slug, b.logo_url, b.banner_url, b.website_url, 
                   b.is_active, b.is_featured, b.sort_order, b.created_at, b.updated_at,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId, ':lang' => $lang];

        if ($featuredOnly) {
            $sql .= " AND b.is_featured = 1";
        }

        $sql .= " ORDER BY b.sort_order ASC, b.slug ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId, string $slug, string $lang = 'en', bool $allTranslations = false): ?array
    {
        if ($allTranslations) {
            $row = $this->findBySlug($tenantId, $slug);
            if ($row) {
                $row['translations'] = $this->getTranslations($row['id']);
            }
            return $row;
        }

        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId AND b.slug = :slug
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM brands
            WHERE tenant_id = :tenantId AND id = :id
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(int $tenantId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM brands
            WHERE tenant_id = :tenantId AND slug = :slug
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->findById($tenantId, (int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE brands
                SET slug = :slug,
                    logo_url = :logo_url,
                    banner_url = :banner_url,
                    website_url = :website_url,
                    is_active = :is_active,
                    is_featured = :is_featured,
                    sort_order = :sort_order,
                    updated_at = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $stmt->execute([
                ':slug'       => $data['slug'],
                ':logo_url'   => $data['logo_url'] ?? null,
                ':banner_url' => $data['banner_url'] ?? null,
                ':website_url' => $data['website_url'] ?? null,
                ':is_active'  => (int)($data['is_active'] ?? 1),
                ':is_featured' => (int)($data['is_featured'] ?? 0),
                ':sort_order' => (int)($data['sort_order'] ?? 0),
                ':tenantId'   => $tenantId,
                ':id'         => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO brands
                    (tenant_id, slug, logo_url, banner_url, website_url, is_active, is_featured, sort_order, created_at)
                VALUES
                    (:tenantId, :slug, :logo_url, :banner_url, :website_url, :is_active, :is_featured, :sort_order, NOW())
            ");

            $stmt->execute([
                ':tenantId'    => $tenantId,
                ':slug'        => $data['slug'],
                ':logo_url'    => $data['logo_url'] ?? null,
                ':banner_url'  => $data['banner_url'] ?? null,
                ':website_url' => $data['website_url'] ?? null,
                ':is_active'   => (int)($data['is_active'] ?? 1),
                ':is_featured' => (int)($data['is_featured'] ?? 0),
                ':sort_order'  => (int)($data['sort_order'] ?? 0)
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

    public function delete(int $tenantId, string $slug, ?int $userId = null): bool
    {
        $oldData = $this->findBySlug($tenantId, $slug);

        if (!$oldData) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            // Delete translations
            $this->pdo->prepare("DELETE FROM brand_translations WHERE brand_id = :brand_id")
                ->execute([':brand_id' => $oldData['id']]);

            // Delete brand
            $stmt = $this->pdo->prepare("
                DELETE FROM brands
                WHERE tenant_id = :tenantId AND slug = :slug
            ");

            $result = $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);

            // Log the action
            if ($userId) {
                $this->logAction($tenantId, $userId, 'delete', $oldData['id'], $oldData, null);
            }

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function deleteById(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->findById($tenantId, $id);

        if (!$oldData) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            // Delete translations
            $this->pdo->prepare("DELETE FROM brand_translations WHERE brand_id = :brand_id")
                ->execute([':brand_id' => $id]);

            // Delete brand
            $stmt = $this->pdo->prepare("
                DELETE FROM brands
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $result = $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);

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

    public function getActiveBrands(int $tenantId, string $lang = 'en'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.slug, b.logo_url, b.website_url, b.sort_order,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId AND b.is_active = 1
            ORDER BY b.sort_order ASC, b.slug ASC
        ");

        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeaturedBrands(int $tenantId, string $lang = 'en'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.slug, b.logo_url, b.banner_url, b.website_url, b.sort_order,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId AND b.is_featured = 1 AND b.is_active = 1
            ORDER BY b.sort_order ASC, b.slug ASC
        ");

        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTranslations(int $brandId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO brand_translations (brand_id, language_code, name, description, meta_title, meta_description)
            VALUES (:brand_id, :lang, :name, :description, :meta_title, :meta_description)
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description)
        ");

        foreach ($translations as $lang => $data) {
            $stmt->execute([
                ':brand_id'          => $brandId,
                ':lang'              => $lang,
                ':name'              => $data['name'] ?? null,
                ':description'       => $data['description'] ?? null,
                ':meta_title'        => $data['meta_title'] ?? null,
                ':meta_description'  => $data['meta_description'] ?? null
            ]);
        }
    }

    public function getTranslations(int $brandId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, name, description, meta_title, meta_description
            FROM brand_translations
            WHERE brand_id = :brand_id
        ");

        $stmt->execute([':brand_id' => $brandId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row['language_code']] = [
                'name'             => $row['name'],
                'description'      => $row['description'],
                'meta_title'       => $row['meta_title'],
                'meta_description' => $row['meta_description']
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
            VALUES (:tenantId, :userId, 'brand', :entityId, :action, :changes, :ip, NOW())
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
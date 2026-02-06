<?php
declare(strict_types=1);

// api/v1/models/homepage_sections/repositories/PdoHomepageSectionsRepository.php

final class PdoHomepageSectionsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?string $sectionType = null, ?int $themeId = null, string $lang = 'en'): array
    {
        $sql = "
            SELECT hs.id, hs.tenant_id, hs.theme_id, hs.section_type, hs.layout_type, hs.items_per_row, 
                   hs.background_color, hs.text_color, hs.padding, hs.custom_css, hs.custom_html, 
                   hs.data_source, hs.is_active, hs.sort_order, hs.created_at, hs.updated_at,
                   COALESCE(hst.title, hs.title) AS title,
                   COALESCE(hst.subtitle, hs.subtitle) AS subtitle
            FROM homepage_sections hs
            LEFT JOIN homepage_section_translations hst 
                ON hs.id = hst.section_id AND hst.language_code = :lang
            WHERE hs.tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId, ':lang' => $lang];

        if ($sectionType) {
            $sql .= " AND hs.section_type = :sectionType";
            $params[':sectionType'] = $sectionType;
        }

        if ($themeId) {
            $sql .= " AND hs.theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY hs.sort_order ASC, hs.section_type ASC";

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
            SELECT hs.*, 
                   COALESCE(hst.title, hs.title) AS title,
                   COALESCE(hst.subtitle, hs.subtitle) AS subtitle
            FROM homepage_sections hs
            LEFT JOIN homepage_section_translations hst 
                ON hs.id = hst.section_id AND hst.language_code = :lang
            WHERE hs.tenant_id = :tenantId AND hs.id = :id
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM homepage_sections
            WHERE tenant_id = :tenantId AND id = :id
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
                UPDATE homepage_sections
                SET section_type = :section_type,
                    title = :title,
                    subtitle = :subtitle,
                    layout_type = :layout_type,
                    items_per_row = :items_per_row,
                    background_color = :background_color,
                    text_color = :text_color,
                    padding = :padding,
                    custom_css = :custom_css,
                    custom_html = :custom_html,
                    data_source = :data_source,
                    is_active = :is_active,
                    sort_order = :sort_order,
                    theme_id = :theme_id,
                    updated_at = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $stmt->execute([
                ':section_type'    => $data['section_type'],
                ':title'           => $data['title'] ?? null,
                ':subtitle'        => $data['subtitle'] ?? null,
                ':layout_type'     => $data['layout_type'] ?? 'grid',
                ':items_per_row'   => (int)($data['items_per_row'] ?? 4),
                ':background_color' => $data['background_color'] ?? '#FFFFFF',
                ':text_color'      => $data['text_color'] ?? '#000000',
                ':padding'         => $data['padding'] ?? '40px 0',
                ':custom_css'      => $data['custom_css'] ?? null,
                ':custom_html'     => $data['custom_html'] ?? null,
                ':data_source'     => $data['data_source'] ?? null,
                ':is_active'       => (int)($data['is_active'] ?? 1),
                ':sort_order'      => (int)($data['sort_order'] ?? 0),
                ':theme_id'        => $data['theme_id'] ?? null,
                ':tenantId'        => $tenantId,
                ':id'              => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO homepage_sections
                    (tenant_id, section_type, title, subtitle, layout_type, items_per_row, background_color, text_color, padding, custom_css, custom_html, data_source, is_active, sort_order, theme_id, created_at)
                VALUES
                    (:tenantId, :section_type, :title, :subtitle, :layout_type, :items_per_row, :background_color, :text_color, :padding, :custom_css, :custom_html, :data_source, :is_active, :sort_order, :theme_id, NOW())
            ");

            $stmt->execute([
                ':tenantId'        => $tenantId,
                ':section_type'    => $data['section_type'],
                ':title'           => $data['title'] ?? null,
                ':subtitle'        => $data['subtitle'] ?? null,
                ':layout_type'     => $data['layout_type'] ?? 'grid',
                ':items_per_row'   => (int)($data['items_per_row'] ?? 4),
                ':background_color' => $data['background_color'] ?? '#FFFFFF',
                ':text_color'      => $data['text_color'] ?? '#000000',
                ':padding'         => $data['padding'] ?? '40px 0',
                ':custom_css'      => $data['custom_css'] ?? null,
                ':custom_html'     => $data['custom_html'] ?? null,
                ':data_source'     => $data['data_source'] ?? null,
                ':is_active'       => (int)($data['is_active'] ?? 1),
                ':sort_order'      => (int)($data['sort_order'] ?? 0),
                ':theme_id'        => $data['theme_id'] ?? null
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

        $this->pdo->beginTransaction();

        try {
            // Delete translations
            $this->pdo->prepare("DELETE FROM homepage_section_translations WHERE section_id = :section_id")
                ->execute([':section_id' => $id]);

            // Delete section
            $stmt = $this->pdo->prepare("
                DELETE FROM homepage_sections
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $result = $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);

            // Log the action
            if ($userId && $oldData) {
                $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
            }

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function getSectionTypes(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT section_type
            FROM homepage_sections
            WHERE tenant_id = :tenantId
            ORDER BY section_type ASC
        ");

        $stmt->execute([':tenantId' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getActiveSections(int $tenantId, string $lang = 'en', ?int $themeId = null): array
    {
        $sql = "
            SELECT hs.id, hs.section_type, hs.layout_type, hs.items_per_row, hs.background_color, 
                   hs.text_color, hs.padding, hs.custom_css, hs.custom_html, hs.data_source, 
                   hs.sort_order, hs.theme_id,
                   COALESCE(hst.title, hs.title) AS title,
                   COALESCE(hst.subtitle, hs.subtitle) AS subtitle
            FROM homepage_sections hs
            LEFT JOIN homepage_section_translations hst 
                ON hs.id = hst.section_id AND hst.language_code = :lang
            WHERE hs.tenant_id = :tenantId AND hs.is_active = 1
        ";

        $params = [':tenantId' => $tenantId, ':lang' => $lang];

        if ($themeId) {
            $sql .= " AND hs.theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY hs.sort_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTranslations(int $sectionId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO homepage_section_translations (section_id, language_code, title, subtitle)
            VALUES (:section_id, :lang, :title, :subtitle)
            ON DUPLICATE KEY UPDATE title = VALUES(title), subtitle = VALUES(subtitle)
        ");

        foreach ($translations as $lang => $data) {
            $stmt->execute([
                ':section_id' => $sectionId,
                ':lang'       => $lang,
                ':title'      => $data['title'] ?? null,
                ':subtitle'   => $data['subtitle'] ?? null
            ]);
        }
    }

    public function getTranslations(int $sectionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, title, subtitle
            FROM homepage_section_translations
            WHERE section_id = :section_id
        ");

        $stmt->execute([':section_id' => $sectionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row['language_code']] = [
                'title'    => $row['title'],
                'subtitle' => $row['subtitle']
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
            VALUES (:tenantId, :userId, 'homepage_section', :entityId, :action, :changes, :ip, NOW())
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